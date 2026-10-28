<?php
/**
 * Reminder sending: SMS + WhatsApp (Twilio), Email (SMTP or mail()), plus no-gateway click-to-chat links.
 * Credentials live in config/config.php; preferences/templates live in Settings -> Notification Settings.
 */

const DEFAULT_APPOINTMENT_TEMPLATE = "Hello {patient}, this is a reminder of your appointment at {clinic} on {date} at {time}. Please call {clinic_phone} if you need to reschedule.";
const DEFAULT_BRACES_TEMPLATE = "Hello {patient}, your braces follow-up at {clinic} is due on {date}. Please book your visit. {clinic_phone}";

/** "063 4123456" / "0634123456" / "+252634123456" / "00252634123456"  ->  "+252634123456" */
function normalize_phone(string $raw): string
{
    $p = preg_replace('/[^\d+]/', '', $raw);
    if ($p === '' || $p === '+') return '';
    if ($p[0] === '+') return $p;
    if (strpos($p, '00') === 0) return '+' . substr($p, 2);
    if ($p[0] === '0') $p = substr($p, 1);
    return app_setting('default_country_code', '+252') . $p;
}

function render_template(string $tpl, array $vars): string
{
    $pairs = [];
    foreach ($vars as $k => $v) { $pairs['{' . $k . '}'] = (string)$v; }
    return trim(strtr($tpl, $pairs));
}

function whatsapp_link(string $phone, string $message): string
{
    return 'https://wa.me/' . ltrim(normalize_phone($phone), '+') . '?text=' . rawurlencode($message);
}

function sms_link(string $phone, string $message): string
{
    return 'sms:' . normalize_phone($phone) . '?body=' . rawurlencode($message);
}

function gateway_status(): array
{
    $twilio = TWILIO_SID !== '' && TWILIO_TOKEN !== '';
    return [
        'sms'      => $twilio && TWILIO_SMS_FROM !== '',
        'whatsapp' => $twilio && TWILIO_WHATSAPP_FROM !== '',
        'email'    => MAIL_FROM !== '',
    ];
}

function appointment_message(array $appt): string
{
    $s = clinic_settings();
    return render_template(app_setting('appointment_template', DEFAULT_APPOINTMENT_TEMPLATE), [
        'patient' => $appt['patients']['full_name'] ?? 'Patient',
        'date' => date('d/m/Y', strtotime($appt['appointment_date'])),
        'time' => substr($appt['appointment_time'], 0, 5),
        'clinic' => $s['clinic_name'],
        'clinic_phone' => $s['clinic_phone'] ?? '',
    ]);
}

function braces_message(array $case): string
{
    $s = clinic_settings();
    return render_template(app_setting('braces_template', DEFAULT_BRACES_TEMPLATE), [
        'patient' => $case['patients']['full_name'] ?? 'Patient',
        'date' => date('d/m/Y', strtotime($case['next_visit_date'])),
        'clinic' => $s['clinic_name'],
        'clinic_phone' => $s['clinic_phone'] ?? '',
    ]);
}

/** @return array [bool ok, string errorOrInfo] */
function send_twilio(string $to, string $from, string $body): array
{
    if (!function_exists('curl_init')) return [false, 'PHP cURL extension is not enabled.'];
    $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_SID . '/Messages.json');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['To' => $to, 'From' => $from, 'Body' => $body]),
        CURLOPT_USERPWD => TWILIO_SID . ':' . TWILIO_TOKEN,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) return [false, 'Could not reach Twilio: ' . $err];
    $json = json_decode($res, true);
    if ($code >= 200 && $code < 300) return [true, $json['sid'] ?? 'queued'];
    return [false, 'Twilio error ' . $code . ': ' . ($json['message'] ?? $res)];
}

/** Minimal SMTP client (AUTH LOGIN, SSL or STARTTLS). */
function send_smtp(string $to, string $subject, string $body): array
{
    $secure = SMTP_SECURE;
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : '') . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 15);
    if (!$fp) return [false, 'SMTP connection failed: ' . $errstr];
    stream_set_timeout($fp, 15);
    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) { $data .= $line; if (strlen($line) < 4 || $line[3] === ' ') break; }
        return $data;
    };
    $cmd = function (string $c) use ($fp, $read) { fwrite($fp, $c . "\r\n"); return $read(); };
    $fail = function (string $msg) use ($fp) { @fclose($fp); return [false, $msg]; };

    $read();
    $cmd('EHLO clinic.local');
    if ($secure === 'tls') {
        if (substr($cmd('STARTTLS'), 0, 3) !== '220') return $fail('SMTP server refused STARTTLS.');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return $fail('TLS handshake failed.');
        $cmd('EHLO clinic.local');
    }
    if (SMTP_USER !== '') {
        $cmd('AUTH LOGIN');
        $cmd(base64_encode(SMTP_USER));
        if (substr($cmd(base64_encode(SMTP_PASS)), 0, 3) !== '235') return $fail('SMTP login failed (check user / password / app password).');
    }
    if (substr($cmd('MAIL FROM:<' . MAIL_FROM . '>'), 0, 1) !== '2') return $fail('SMTP rejected the sender address.');
    if (substr($cmd('RCPT TO:<' . $to . '>'), 0, 1) !== '2') return $fail('SMTP rejected the recipient address.');
    if (substr($cmd('DATA'), 0, 3) !== '354') return $fail('SMTP did not accept DATA.');

    $headers = "From: " . clinic_settings()['clinic_name'] . " <" . MAIL_FROM . ">\r\nTo: <$to>\r\n"
             . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nMIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\nDate: " . date('r') . "\r\n";
    $msg = $headers . "\r\n" . preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $body));
    $msg = str_replace("\n", "\r\n", $msg);
    $reply = $cmd($msg . "\r\n.");
    $cmd('QUIT');
    @fclose($fp);
    return substr($reply, 0, 1) === '2' ? [true, 'sent'] : [false, 'SMTP error: ' . trim($reply)];
}

function send_email(string $to, string $subject, string $body): array
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return [false, 'Invalid email address.'];
    if (SMTP_HOST !== '') return send_smtp($to, $subject, $body);
    $headers = 'From: ' . MAIL_FROM . "\r\nContent-Type: text/plain; charset=UTF-8";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers)
        ? [true, 'sent'] : [false, 'PHP mail() failed. Set SMTP_HOST in config/config.php or configure a mail server.'];
}

/**
 * Sends $body to a patient on one channel. $patient needs phone and/or email.
 * @return array [bool ok, string info/error, string toAddress]
 */
function send_message(string $channel, array $patient, string $body, string $subject = 'Reminder'): array
{
    $gw = gateway_status();
    if ($channel === 'sms' || $channel === 'whatsapp') {
        $phone = normalize_phone((string)($patient['phone'] ?? ''));
        if ($phone === '') return [false, 'Patient has no phone number.', ''];
        if (!$gw[$channel]) return [false, strtoupper($channel === 'sms' ? 'SMS' : 'WhatsApp') . ' gateway is not configured in config/config.php.', $phone];
        if ($channel === 'sms') { [$ok, $info] = send_twilio($phone, TWILIO_SMS_FROM, $body); return [$ok, $info, $phone]; }
        $from = strpos(TWILIO_WHATSAPP_FROM, 'whatsapp:') === 0 ? TWILIO_WHATSAPP_FROM : 'whatsapp:' . TWILIO_WHATSAPP_FROM;
        [$ok, $info] = send_twilio('whatsapp:' . $phone, $from, $body);
        return [$ok, $info, $phone];
    }
    if ($channel === 'email') {
        $email = trim((string)($patient['email'] ?? ''));
        if ($email === '') return [false, 'Patient has no email address.', ''];
        if (!$gw['email']) return [false, 'Email is not configured (MAIL_FROM) in config/config.php.', $email];
        [$ok, $info] = send_email($email, $subject, $body);
        return [$ok, $info, $email];
    }
    return [false, 'No sending channel selected (System alert only).', ''];
}

function log_notification(string $kind, ?string $entityId, ?string $forDate, ?string $patientId, string $channel, string $to, string $message, bool $ok, string $error = ''): void
{
    Supabase::insert('notification_log', [
        'kind' => $kind, 'entity_id' => $entityId, 'for_date' => $forDate, 'patient_id' => $patientId,
        'channel' => $channel, 'to_address' => $to, 'message' => $message,
        'status' => $ok ? 'sent' : 'failed', 'error' => $ok ? null : $error,
    ]);
}

function already_notified(string $kind, string $entityId, ?string $forDate): bool
{
    $f = ['kind' => 'eq.' . $kind, 'entity_id' => 'eq.' . $entityId, 'status' => 'eq.sent'];
    if ($forDate) $f['for_date'] = 'eq.' . $forDate;
    return (bool)Supabase::selectOne('notification_log', $f);
}

/** Sends one appointment reminder using the channel chosen in Settings. @return array [ok, text] */
function send_appointment_reminder(array $appt): array
{
    $channel = app_setting('notify_channel', 'system');
    $msg = appointment_message($appt);
    if ($channel === 'system') {
        Supabase::update('appointments', ['id' => 'eq.' . $appt['id']], ['reminder_sent' => true]);
        return [true, 'Marked as reminded. No message was sent because the channel is "System alert" (Settings → Notification Settings).'];
    }
    [$ok, $info, $to] = send_message($channel, $appt['patients'] ?? [], $msg, 'Appointment reminder');
    log_notification('appointment', $appt['id'], $appt['appointment_date'], $appt['patient_id'] ?? null, $channel, $to, $msg, $ok, $ok ? '' : $info);
    if ($ok) Supabase::update('appointments', ['id' => 'eq.' . $appt['id']], ['reminder_sent' => true]);
    return [$ok, $ok ? 'Reminder sent via ' . $channel . ' to ' . $to . '.' : 'Reminder not sent: ' . $info];
}

/** Sends every reminder that is due (appointments + braces visits on today + N days). */
function send_due_reminders(): array
{
    $out = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => [], 'channel' => app_setting('notify_channel', 'system')];
    if ($out['channel'] === 'system') { $out['errors'][] = 'Channel is "System alert" — choose SMS, WhatsApp or Email in Notification Settings.'; return $out; }

    $target = date('Y-m-d', strtotime('+' . (int)app_setting('reminder_days_before', '1') . ' days'));

    $appts = Supabase::select('appointments', [
        'appointment_date' => 'eq.' . $target, 'status' => 'in.(booked,confirmed,rescheduled)',
        'select' => '*,patients(full_name,phone,email)',
    ]);
    foreach ($appts as $a) {
        if (!empty($a['reminder_sent']) || already_notified('appointment', $a['id'], $a['appointment_date'])) { $out['skipped']++; continue; }
        [$ok, $text] = send_appointment_reminder($a);
        if ($ok) $out['sent']++; else { $out['failed']++; $out['errors'][] = ($a['patients']['full_name'] ?? '?') . ': ' . $text; }
    }

    $cases = Supabase::select('braces_cases', [
        'status' => 'eq.active', 'next_visit_date' => 'eq.' . $target,
        'select' => '*,patients(full_name,phone,email)',
    ]);
    foreach ($cases as $c) {
        if (already_notified('braces', $c['id'], $c['next_visit_date'])) { $out['skipped']++; continue; }
        $msg = braces_message($c);
        [$ok, $info, $to] = send_message($out['channel'], $c['patients'] ?? [], $msg, 'Braces follow-up reminder');
        log_notification('braces', $c['id'], $c['next_visit_date'], $c['patient_id'] ?? null, $out['channel'], $to, $msg, $ok, $ok ? '' : $info);
        if ($ok) $out['sent']++; else { $out['failed']++; $out['errors'][] = ($c['patients']['full_name'] ?? '?') . ': ' . $info; }
    }
    return $out;
}
