<?php
/** Rendered under the Notification Settings form (included by settings/section.php). */
$gw = gateway_status();
$logRows = Supabase::select('notification_log', ['select' => '*,patients(full_name)', 'order' => 'created_at.desc', 'limit' => 15]);
$rows = [
    ['SMS',      $gw['sms'],      'TWILIO_SID, TWILIO_TOKEN and TWILIO_SMS_FROM'],
    ['WhatsApp', $gw['whatsapp'], 'TWILIO_SID, TWILIO_TOKEN and TWILIO_WHATSAPP_FROM'],
    ['Email',    $gw['email'],    'MAIL_FROM (and SMTP_HOST / SMTP_USER / SMTP_PASS for an SMTP server)'],
];
?>
<div class="section-title" style="margin-top:26px;">📡 Gateway status</div>
<div class="card">
  <table class="data" style="margin-bottom:10px;">
    <?php foreach ($rows as [$name, $on, $need]): ?>
      <tr>
        <td style="width:110px;"><strong><?= h($name) ?></strong></td>
        <td style="width:140px;"><?= $on ? '<span class="badge green">Ready</span>' : '<span class="badge red">Not configured</span>' ?></td>
        <td style="color:var(--slate);"><?= $on ? 'Credentials found in config/config.php.' : 'Set ' . h($need) . ' in <code>config/config.php</code>.' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <p style="color:var(--slate);font-size:13px;margin:0;">
    Passwords and API tokens stay in <code>config/config.php</code> on your server on purpose — they are never saved in the database.
    No gateway yet? The <strong>💬 WhatsApp</strong> and <strong>✉ SMS</strong> buttons on the Appointments and Braces pages already work:
    they open WhatsApp / your phone's SMS app with the message filled in.
  </p>
</div>

<div class="section-title" style="margin-top:22px;">🧪 Send a test message</div>
<form method="post" action="<?= base_url('settings/notify_tools.php') ?>" class="card" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
  <input type="hidden" name="action" value="test">
  <div style="min-width:150px;">
    <label>Channel</label>
    <select name="channel">
      <?php foreach (['sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'email' => 'Email'] as $k => $l): ?>
        <option value="<?= h($k) ?>" <?= app_setting('notify_channel', 'system') === $k ? 'selected' : '' ?>><?= h($l) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div style="flex:1;min-width:220px;"><label>Phone number or email</label><input type="text" name="to" placeholder="e.g. 0634123456 or name@example.com" required></div>
  <button class="btn">Send test</button>
</form>

<div class="section-title" style="margin-top:22px;">⏰ Send due reminders</div>
<div class="card">
  <p style="margin-top:0;color:var(--slate);">
    Sends reminders for appointments and braces follow-ups that fall <strong><?= (int)app_setting('reminder_days_before', '1') ?> day(s)</strong> from today,
    by <strong><?= h(app_setting('notify_channel', 'system')) ?></strong>. Anyone already reminded is skipped, so it is safe to press more than once.
  </p>
  <form method="post" action="<?= base_url('settings/notify_tools.php') ?>" style="display:inline;">
    <input type="hidden" name="action" value="send_due">
    <button class="btn">Send due reminders now</button>
  </form>
  <p style="color:var(--slate);font-size:13px;margin-bottom:0;">
    To send them automatically every day, schedule <code>cron/send_reminders.php</code> (see the comment at the top of that file).
  </p>
</div>

<div class="section-title" style="margin-top:22px;">📜 Recent messages</div>
<table class="data">
  <tr><th>Time</th><th>Type</th><th>Patient</th><th>Channel</th><th>To</th><th>Result</th></tr>
  <?php if (!count($logRows)): ?><tr><td colspan="6" style="color:var(--slate);">Nothing sent yet.</td></tr><?php endif; ?>
  <?php foreach ($logRows as $l): ?>
    <tr>
      <td><?= h(date('d M H:i', strtotime($l['created_at']))) ?></td>
      <td><?= h(ucfirst($l['kind'])) ?></td>
      <td><?= h($l['patients']['full_name'] ?? '—') ?></td>
      <td><span class="badge gray"><?= h($l['channel']) ?></span></td>
      <td><?= h($l['to_address']) ?: '—' ?></td>
      <td><?= $l['status'] === 'sent' ? '<span class="badge green">Sent</span>' : '<span class="badge red" title="' . h($l['error']) . '">Failed</span> <span style="color:var(--slate);font-size:12px;">' . h(mb_strimwidth((string)$l['error'], 0, 70, '…')) . '</span>' ?></td>
    </tr>
  <?php endforeach; ?>
</table>
