<?php
require_once __DIR__ . '/../config/config.php';
require_manager();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'test') {
        $channel = $_POST['channel'] ?? '';
        $to = trim($_POST['to'] ?? '');
        $body = render_template("Test message from {clinic}. If you can read this, {channel} reminders are working.", [
            'clinic' => clinic_settings()['clinic_name'], 'channel' => $channel,
        ]);
        [$ok, $info, $dest] = send_message($channel, ['phone' => $to, 'email' => $to], $body, 'Test message');
        log_notification('test', null, null, null, $channel ?: 'system', $dest ?: $to, $body, $ok, $ok ? '' : $info);
        log_audit('notify_test', 'settings', $channel, $ok ? 'ok' : $info);
        flash($ok ? 'success' : 'error', $ok ? "Test message sent to $dest." : "Test failed: $info");
    } elseif ($action === 'send_due') {
        $r = send_due_reminders();
        log_audit('send_due_reminders', 'settings', $r['channel'], "sent={$r['sent']} failed={$r['failed']} skipped={$r['skipped']}");
        if ($r['channel'] === 'system') {
            flash('error', $r['errors'][0]);
        } else {
            $msg = "Reminders: {$r['sent']} sent, {$r['failed']} failed, {$r['skipped']} skipped (already sent).";
            if ($r['errors']) { $msg .= ' First error: ' . $r['errors'][0]; }
            flash($r['failed'] ? 'error' : 'success', $msg);
        }
    }
}
redirect('settings/section.php?s=notifications');
