<?php
require_once __DIR__ . '/../config/config.php';
$page_title = 'Treatments & Services';

// add/edit a service in the price list — Manager only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form']) && $_POST['form'] === 'service') {
    require_manager();
    Supabase::insert('services', [
        'name' => trim($_POST['name']),
        'category' => trim($_POST['category']),
        'default_price' => (float)$_POST['default_price'],
    ]);
    flash('success', 'Service added to price list.');
    redirect('treatments/index.php');
}

// log a treatment performed on a patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form']) && $_POST['form'] === 'treatment') {
    $service = Supabase::selectOne('services', ['id' => 'eq.' . $_POST['service_id']]);
    Supabase::insert('treatments', [
        'patient_id' => $_POST['patient_id'],
        'service_id' => $_POST['service_id'],
        'doctor_id' => $_POST['doctor_id'] ?: null,
        'tooth_number' => $_POST['tooth_number'] !== '' ? (int)$_POST['tooth_number'] : null,
        'price' => $_POST['price'] !== '' ? (float)$_POST['price'] : ($service['default_price'] ?? 0),
        'status' => $_POST['status'],
        'treatment_date' => $_POST['treatment_date'],
        'notes' => trim($_POST['notes']),
    ]);
    flash('success', 'Treatment recorded. You can now create an invoice from Billing.');
    redirect('treatments/index.php');
}

$services = Supabase::select('services', ['order' => 'category.asc']);
$patients = Supabase::select('patients', ['order' => 'full_name.asc']);
$doctors = Supabase::select('staff', ['active' => 'eq.true']);
$recentTreatments = Supabase::select('treatments', [
    'select' => '*,patients(full_name),services(name)',
    'order' => 'created_at.desc',
    'limit' => 30,
]);

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">🩺 Log a treatment</div>
<form method="post" class="card">
  <input type="hidden" name="form" value="treatment">
  <div class="form-grid">
    <div>
      <label>Patient *</label>
      <select name="patient_id" required>
        <option value="">Select…</option>
        <?php foreach ($patients as $p): ?><option value="<?= h($p['id']) ?>"><?= h($p['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Service *</label>
      <select name="service_id" required>
        <option value="">Select…</option>
        <?php foreach ($services as $s): ?><option value="<?= h($s['id']) ?>" data-price="<?= h($s['default_price']) ?>"><?= h($s['name']) ?> (<?= money($s['default_price']) ?>)</option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Doctor</label>
      <select name="doctor_id">
        <option value="">Unassigned</option>
        <?php foreach ($doctors as $d): ?><option value="<?= h($d['id']) ?>"><?= h($d['full_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Tooth # (optional)</label>
      <input type="number" name="tooth_number" min="1" max="32">
    </div>
    <div>
      <label>Price</label>
      <input type="number" step="0.01" name="price" id="price-field" placeholder="Uses service default if left blank">
    </div>
    <div>
      <label>Status</label>
      <select name="status">
        <?php $defStatus = app_setting('default_treatment_status', 'in_progress'); ?>
        <option value="planned" <?= $defStatus==='planned'?'selected':'' ?>>Planned</option>
        <option value="in_progress" <?= $defStatus==='in_progress'?'selected':'' ?>>In progress</option>
        <option value="completed" <?= $defStatus==='completed'?'selected':'' ?>>Completed</option>
      </select>
    </div>
    <div>
      <label>Date</label>
      <input type="date" name="treatment_date" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="full">
      <label>Notes</label>
      <textarea name="notes"></textarea>
    </div>
  </div>
  <div style="margin-top:16px;"><button class="btn">Record treatment</button></div>
</form>
<script>
document.querySelector('select[name="service_id"]').addEventListener('change', e => {
  const price = e.target.selectedOptions[0]?.dataset.price;
  if (price) document.getElementById('price-field').placeholder = 'Default: <?= APP_CURRENCY ?>' + price;
});
</script>

<div class="section-title">📋 Recent treatments</div>
<table class="data">
  <tr><th>Date</th><th>Patient</th><th>Service</th><th>Tooth</th><th>Price</th><th>Status</th></tr>
  <?php if (!count($recentTreatments)): ?><tr><td colspan="6" style="color:var(--slate);">No treatments recorded yet.</td></tr><?php endif; ?>
  <?php foreach ($recentTreatments as $t): ?>
    <tr>
      <td><?= h($t['treatment_date']) ?></td>
      <td><?= h($t['patients']['full_name'] ?? '—') ?></td>
      <td><?= h($t['services']['name'] ?? '—') ?></td>
      <td><?= $t['tooth_number'] ? '#'.h($t['tooth_number']) : '—' ?></td>
      <td><?= money($t['price']) ?></td>
      <td><span class="badge <?= $t['status']==='completed'?'green':'amber' ?>"><?= h(ucfirst(str_replace('_',' ',$t['status']))) ?></span></td>
    </tr>
  <?php endforeach; ?>
</table>

<div class="section-title">
  💲 Service price list
</div>
<div class="form-grid">
  <table class="data" style="grid-column:1/-1;">
    <tr><th>Service</th><th>Category</th><th>Default price</th></tr>
    <?php foreach ($services as $s): ?>
      <tr><td><?= h($s['name']) ?></td><td><?= h($s['category']) ?></td><td><?= money($s['default_price']) ?></td></tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if (can_manage_prices()): ?>
<div class="section-title" style="margin-top:26px;">+ Add a new service</div>
<form method="post" class="card" style="display:flex;gap:10px;align-items:end;">
  <input type="hidden" name="form" value="service">
  <div style="flex:2;"><label>Name</label><input type="text" name="name" required></div>
  <div style="flex:1;"><label>Category</label><input type="text" name="category" list="category-list"><datalist id="category-list"><?php foreach (array_keys(category_list()) as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?></datalist></div>
  <div style="flex:1;"><label>Default price</label><input type="number" step="0.01" name="default_price" required></div>
  <button class="btn">Add</button>
</form>
<?php else: ?>
<div class="section-title" style="margin-top:26px;">Service prices</div>
<div class="card" style="color:var(--slate);">Only a Manager can add or change service prices.</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
