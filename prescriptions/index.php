<?php
require_once __DIR__ . '/../config/config.php';
$page_title = 'Prescriptions';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!can_diagnose()) {
        flash('error', 'Only a Doctor or Manager can write a prescription.');
        redirect('prescriptions/index.php');
    }
    Supabase::insert('prescriptions', [
        'patient_id' => $_POST['patient_id'],
        'doctor_id' => current_staff()['id'],
        'medicine_name' => trim($_POST['medicine_name']),
        'dosage' => trim($_POST['dosage']),
        'instructions' => trim($_POST['instructions']),
        'prescribed_date' => $_POST['prescribed_date'],
    ]);
    flash('success', 'Prescription saved.');
    redirect('prescriptions/index.php');
}

$patients = Supabase::select('patients', ['order' => 'full_name.asc']);
$medicines = Supabase::select('inventory_items', ['item_type' => 'eq.medicine', 'order' => 'item_name.asc']);

$filters = ['select' => '*,patients(full_name),staff(full_name)', 'order' => 'prescribed_date.desc'];
// Doctors only see their own prescriptions; Managers see everything
if (is_doctor() && !can_manage_prices()) {
    $filters['doctor_id'] = 'eq.' . current_staff()['id'];
}
$prescriptions = Supabase::select('prescriptions', $filters);

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">💊 Prescriptions</div>

<?php if (can_diagnose()): ?>
<form method="post" class="card form-grid">
  <div>
    <label>Patient *</label>
    <select name="patient_id" required>
      <option value="">Select…</option>
      <?php foreach ($patients as $p): ?><option value="<?= h($p['id']) ?>"><?= h($p['full_name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div>
    <label>Medicine *</label>
    <input type="text" name="medicine_name" list="medicine-list" required placeholder="e.g. Amoxicillin 500mg">
    <datalist id="medicine-list">
      <?php foreach ($medicines as $m): ?><option value="<?= h($m['item_name']) ?>"><?php endforeach; ?>
    </datalist>
  </div>
  <div>
    <label>Dosage</label>
    <input type="text" name="dosage" placeholder="e.g. 1 tablet, 3x daily">
  </div>
  <div>
    <label>Date</label>
    <input type="date" name="prescribed_date" value="<?= date('Y-m-d') ?>">
  </div>
  <div class="full">
    <label>Instructions</label>
    <textarea name="instructions" placeholder="e.g. Take after meals for 5 days"></textarea>
  </div>
  <div class="full">
    <button class="btn">Save prescription</button>
  </div>
</form>
<?php endif; ?>

<div class="section-title"><?= (is_doctor() && !can_manage_prices()) ? 'My prescriptions' : 'All prescriptions' ?></div>
<table class="data">
  <tr><th>Date</th><th>Patient</th><th>Medicine</th><th>Dosage</th><th>Instructions</th><th>Doctor</th></tr>
  <?php if (!count($prescriptions)): ?><tr><td colspan="6" style="color:var(--slate);">No prescriptions yet.</td></tr><?php endif; ?>
  <?php foreach ($prescriptions as $rx): ?>
    <tr>
      <td><?= h($rx['prescribed_date']) ?></td>
      <td><?= h($rx['patients']['full_name'] ?? '—') ?></td>
      <td><?= h($rx['medicine_name']) ?></td>
      <td><?= h($rx['dosage']) ?: '—' ?></td>
      <td><?= h($rx['instructions']) ?: '—' ?></td>
      <td><?= h($rx['staff']['full_name'] ?? '—') ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
