<?php
require_once __DIR__ . '/../config/config.php';
$page_title = 'Nurse — Vital Signs';
if (!is_nurse() && !can_manage_prices()) { flash('error', 'Vital signs are recorded by Nurses.'); redirect('index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Supabase::insert('vital_signs', [
        'patient_id' => $_POST['patient_id'],
        'appointment_id' => $_POST['appointment_id'] ?: null,
        'recorded_by' => current_staff()['id'],
        'blood_pressure' => trim($_POST['blood_pressure']),
        'temperature' => $_POST['temperature'] !== '' ? (float)$_POST['temperature'] : null,
        'pulse' => $_POST['pulse'] !== '' ? (int)$_POST['pulse'] : null,
        'respiration_rate' => $_POST['respiration_rate'] !== '' ? (int)$_POST['respiration_rate'] : null,
        'weight' => $_POST['weight'] !== '' ? (float)$_POST['weight'] : null,
        'notes' => trim($_POST['notes']),
    ]);
    log_audit('record_vital_signs', 'patient', $_POST['patient_id']);
    flash('success', 'Vital signs recorded.');
    redirect('nurse/index.php');
}

$today = date('Y-m-d');
$todaysAppointments = Supabase::select('appointments', [
    'appointment_date' => 'eq.' . $today,
    'select' => '*,patients(full_name,phone),staff(full_name),services(name)',
    'order' => 'appointment_time.asc',
]);

$patients = Supabase::select('patients', ['order' => 'full_name.asc']);

$recentVitals = Supabase::select('vital_signs', [
    'recorded_by' => 'eq.' . current_staff()['id'],
    'select' => '*,patients(full_name)',
    'order' => 'recorded_at.desc',
    'limit' => 20,
]);

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">🩺 Nurse — Today's Appointments</div>
<table class="data">
  <tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Service</th><th>Status</th></tr>
  <?php if (!count($todaysAppointments)): ?>
    <tr><td colspan="5" style="color:var(--slate);">No appointments today.</td></tr>
  <?php endif; ?>
  <?php foreach ($todaysAppointments as $a): ?>
    <tr>
      <td><?= h(substr($a['appointment_time'],0,5)) ?></td>
      <td><?= h($a['patients']['full_name'] ?? '—') ?></td>
      <td><?= h($a['staff']['full_name'] ?? 'Unassigned') ?></td>
      <td><?= h($a['services']['name'] ?? '—') ?></td>
      <td><span class="badge <?= $a['status']==='completed'?'green':($a['status']==='cancelled'?'red':'amber') ?>"><?= h(ucfirst($a['status'])) ?></span></td>
    </tr>
  <?php endforeach; ?>
</table>

<div class="section-title" style="margin-top:28px;">+ Record vital signs</div>
<form method="post" class="card form-grid">
  <div>
    <label>Patient *</label>
    <select name="patient_id" required>
      <option value="">Select…</option>
      <?php foreach ($patients as $p): ?><option value="<?= h($p['id']) ?>"><?= h($p['full_name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div>
    <label>Linked appointment (optional)</label>
    <select name="appointment_id">
      <option value="">None</option>
      <?php foreach ($todaysAppointments as $a): ?>
        <option value="<?= h($a['id']) ?>"><?= h(substr($a['appointment_time'],0,5)) ?> — <?= h($a['patients']['full_name'] ?? '—') ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div><label>Blood pressure</label><input type="text" name="blood_pressure" placeholder="e.g. 120/80"></div>
  <div><label>Temperature (°C)</label><input type="number" step="0.1" name="temperature"></div>
  <div><label>Pulse (bpm)</label><input type="number" name="pulse"></div>
  <div><label>Respiration rate</label><input type="number" name="respiration_rate"></div>
  <div><label>Weight (kg)</label><input type="number" step="0.1" name="weight"></div>
  <div class="full"><label>Notes</label><textarea name="notes" placeholder="Patient preparation, doctor instructions, observations…"></textarea></div>
  <div class="full"><button class="btn">Save vital signs</button></div>
</form>

<div class="section-title" style="margin-top:26px;">Recently recorded (by me)</div>
<table class="data">
  <tr><th>Time</th><th>Patient</th><th>BP</th><th>Temp</th><th>Pulse</th><th>Weight</th><th>Notes</th></tr>
  <?php if (!count($recentVitals)): ?><tr><td colspan="7" style="color:var(--slate);">No vital signs recorded yet.</td></tr><?php endif; ?>
  <?php foreach ($recentVitals as $v): ?>
    <tr>
      <td><?= h(date('d M H:i', strtotime($v['recorded_at']))) ?></td>
      <td><?= h($v['patients']['full_name'] ?? '—') ?></td>
      <td><?= h($v['blood_pressure']) ?: '—' ?></td>
      <td><?= $v['temperature'] !== null ? h($v['temperature']) . '°C' : '—' ?></td>
      <td><?= $v['pulse'] !== null ? h($v['pulse']) : '—' ?></td>
      <td><?= $v['weight'] !== null ? h($v['weight']) . 'kg' : '—' ?></td>
      <td><?= h($v['notes']) ?: '—' ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
