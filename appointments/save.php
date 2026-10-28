<?php
require_once __DIR__ . '/../config/config.php';
require_appointment_access();
$page_title = 'Book appointment';

$id = $_GET['id'] ?? null;
$appt = $id ? Supabase::selectOne('appointments', ['id' => 'eq.' . $id]) : null;
$preselectedPatient = $_GET['patient_id'] ?? ($appt['patient_id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'patient_id' => $_POST['patient_id'],
        'doctor_id' => $_POST['doctor_id'] ?: null,
        'service_id' => $_POST['service_id'] ?: null,
        'appointment_date' => $_POST['appointment_date'],
        'appointment_time' => $_POST['appointment_time'],
        'notes' => trim($_POST['notes']),
    ];

    $open = app_setting('opening_time', '08:00'); $close = app_setting('closing_time', '20:00');
    if (substr($data['appointment_time'], 0, 5) < $open || substr($data['appointment_time'], 0, 5) > $close) {
        flash('error', "The clinic is open from $open to $close. Please pick a time within working hours.");
        redirect('appointments/save.php' . ($id ? '?id=' . $id : ''));
    }

    if ($id) {
        $data['status'] = 'rescheduled';
        Supabase::update('appointments', ['id' => 'eq.' . $id], $data);
        flash('success', 'Appointment rescheduled.');
    } else {
        $data['status'] = 'booked';
        Supabase::insert('appointments', $data);
        flash('success', 'Appointment booked.');
    }
    redirect('appointments/index.php?date=' . $data['appointment_date']);
}

$patients = Supabase::select('patients', ['order' => 'full_name.asc']);
$doctors = Supabase::select('staff', ['role' => 'eq.doctor', 'active' => 'eq.true']);
if (!count($doctors)) { $doctors = Supabase::select('staff', ['active' => 'eq.true']); }
$services = Supabase::select('services', ['active' => 'eq.true', 'order' => 'name.asc']);

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><?= $appt ? 'Reschedule appointment' : 'Book appointment' ?></div>

<form method="post" class="card">
  <div class="form-grid">
    <div>
      <label>Patient *</label>
      <select name="patient_id" required>
        <option value="">Select patient…</option>
        <?php foreach ($patients as $p): ?>
          <option value="<?= h($p['id']) ?>" <?= $preselectedPatient === $p['id'] ? 'selected' : '' ?>><?= h($p['full_name']) ?> (<?= h($p['patient_code']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Doctor</label>
      <select name="doctor_id">
        <option value="">Unassigned</option>
        <?php foreach ($doctors as $d): ?>
          <option value="<?= h($d['id']) ?>" <?= ($appt['doctor_id'] ?? '') === $d['id'] ? 'selected' : '' ?>><?= h($d['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Treatment / service</label>
      <select name="service_id">
        <option value="">Select…</option>
        <?php foreach ($services as $s): ?>
          <option value="<?= h($s['id']) ?>" <?= ($appt['service_id'] ?? '') === $s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div></div>
    <div>
      <label>Date *</label>
      <input type="date" name="appointment_date" required value="<?= h($appt['appointment_date'] ?? date('Y-m-d')) ?>">
    </div>
    <div>
      <label>Time *</label>
      <input type="time" name="appointment_time" required value="<?= h($appt['appointment_time'] ?? app_setting('opening_time', '08:00')) ?>">
    </div>
    <div class="full">
      <label>Notes</label>
      <textarea name="notes"><?= h($appt['notes'] ?? '') ?></textarea>
    </div>
  </div>
  <div style="margin-top:18px;">
    <button class="btn"><?= $appt ? 'Save changes' : 'Book appointment' ?></button>
    <a class="btn outline" href="<?= base_url('appointments/index.php') ?>">Cancel</a>
  </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
