<?php
require_once __DIR__ . '/../config/config.php';
$page_title = 'Appointments';

$date = $_GET['date'] ?? date('Y-m-d');

// quick status change / cancel / reschedule flag — Manager + Cashier
if (isset($_GET['action'], $_GET['id'])) {
    require_appointment_access();
    $id = $_GET['id'];
    switch ($_GET['action']) {
        case 'confirm': Supabase::update('appointments', ['id' => 'eq.' . $id], ['status' => 'confirmed']); break;
        case 'complete': Supabase::update('appointments', ['id' => 'eq.' . $id], ['status' => 'completed']); break;
        case 'cancel': Supabase::update('appointments', ['id' => 'eq.' . $id], ['status' => 'cancelled']); break;
        case 'missed': Supabase::update('appointments', ['id' => 'eq.' . $id], ['status' => 'no_show']); break;
        case 'remind': send_reminder($id); break;
    }
    redirect('appointments/index.php?date=' . $date);
}

function send_reminder(string $appointmentId): void
{
    $appt = Supabase::selectOne('appointments', ['id' => 'eq.' . $appointmentId, 'select' => '*,patients(full_name,phone,email)']);
    if (!$appt) { flash('error', 'Appointment not found.'); return; }
    [$ok, $text] = send_appointment_reminder($appt);
    flash($ok ? 'success' : 'error', $text);
}

$filters = [
    'appointment_date' => 'eq.' . $date,
    'select' => '*,patients(full_name,phone),staff(full_name),services(name)',
    'order' => 'appointment_time.asc',
];
// Doctors only see their own appointments ("My Appointments")
if (is_doctor() && !can_manage_prices()) {
    $filters['doctor_id'] = 'eq.' . current_staff()['id'];
}
$appointments = Supabase::select('appointments', $filters);

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
  📅 <?= (is_doctor() && !can_manage_prices()) ? 'My Appointments' : 'Appointments' ?>
  <?php if (can_manage_appointments()): ?><a class="btn" href="<?= base_url('appointments/save.php') ?>">+ Book appointment</a><?php endif; ?>
</div>

<form method="get" class="card" style="margin-bottom:18px;display:flex;gap:10px;align-items:end;">
  <div>
    <label>Date</label>
    <input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()">
  </div>
</form>

<table class="data">
  <tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Service</th><th>Status</th><th>Reminder</th><th></th></tr>
  <?php if (!count($appointments)): ?>
    <tr><td colspan="7" style="color:var(--slate);">No appointments on this date.</td></tr>
  <?php endif; ?>
  <?php foreach ($appointments as $a): ?>
    <tr>
      <td><?= h(substr($a['appointment_time'],0,5)) ?><span style="color:var(--slate);font-size:12px;"> – <?= h(date('H:i', strtotime($a['appointment_time'] . ' +' . (int)app_setting('default_duration', '30') . ' minutes'))) ?></span></td>
      <td><?= h($a['patients']['full_name'] ?? '—') ?><br><small style="color:var(--slate);"><?= ($a['patients']['phone'] ?? '') ? '<a href="tel:' . h($a['patients']['phone']) . '">' . h($a['patients']['phone']) . '</a>' : '' ?></small></td>
      <td><?= h($a['staff']['full_name'] ?? 'Unassigned') ?></td>
      <td><?= h($a['services']['name'] ?? '—') ?></td>
      <td><span class="badge <?= $a['status']==='completed'?'green':(in_array($a['status'], ['cancelled','no_show'], true)?'red':($a['status']==='confirmed'?'teal':'amber')) ?>"><?= $a['status']==='no_show' ? 'Missed' : h(ucfirst($a['status'])) ?></span></td>
      <td><?= $a['reminder_sent'] ? '<span class="badge green">Sent</span>' : '<span class="badge gray">Not sent</span>' ?></td>
      <?php if (can_manage_appointments()): ?>
      <td style="white-space:nowrap;">
        <a class="btn small outline" href="?date=<?= h($date) ?>&action=confirm&id=<?= $a['id'] ?>">Confirm</a>
        <a class="btn small outline" href="?date=<?= h($date) ?>&action=complete&id=<?= $a['id'] ?>">Complete</a>
        <a class="btn small outline" href="<?= base_url('appointments/save.php?id=' . $a['id']) ?>">Reschedule</a>
        <a class="btn small outline" href="?date=<?= h($date) ?>&action=missed&id=<?= $a['id'] ?>" onclick="return confirm('Mark this patient as having missed the appointment?')">Missed</a>
        <a class="btn small danger" href="?date=<?= h($date) ?>&action=cancel&id=<?= $a['id'] ?>" onclick="return confirm('Cancel this appointment?')">Cancel</a>
        <a class="btn small outline" href="?date=<?= h($date) ?>&action=remind&id=<?= $a['id'] ?>" title="Sends using the channel chosen in Settings → Notification Settings">Send reminder</a>
        <?php if (!empty($a['patients']['phone']) && $a['status'] !== 'cancelled'): ?>
        <a class="btn small outline" target="_blank" rel="noopener" title="Opens WhatsApp with the message ready to send" href="<?= base_url('reports/contact.php?kind=appointment&via=whatsapp&id=' . $a['id']) ?>">💬 WhatsApp</a>
        <a class="btn small outline" title="Opens your phone's SMS app with the message ready" target="_blank" href="<?= base_url('reports/contact.php?kind=appointment&via=sms&id=' . $a['id']) ?>">✉ SMS</a>
        <?php endif; ?>
      </td>
      <?php else: ?>
      <td style="color:var(--slate);">View only</td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
