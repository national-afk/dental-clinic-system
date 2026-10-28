<?php
require_once __DIR__ . '/../config/config.php';
$page_title = 'Lab Orders';

$ORDER_TYPES = ['crown' => 'Crown', 'bridge' => 'Bridge', 'denture' => 'Denture', 'other' => 'Other'];
$STATUSES = ['ordered' => 'Ordered', 'in_progress' => 'In Progress', 'ready' => 'Ready', 'delivered' => 'Delivered'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'create') {
    Supabase::insert('lab_orders', [
        'patient_id' => $_POST['patient_id'],
        'order_type' => $_POST['order_type'],
        'expected_delivery_date' => $_POST['expected_delivery_date'] ?: null,
        'notes' => trim($_POST['notes']),
        'created_by' => current_staff()['id'],
    ]);
    flash('success', 'Lab order created.');
    redirect('lab/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'update_status') {
    $update = ['status' => $_POST['status']];
    if ($_POST['status'] === 'delivered') { $update['delivered_date'] = date('Y-m-d'); }
    Supabase::update('lab_orders', ['id' => 'eq.' . $_POST['id']], $update);
    flash('success', 'Lab order updated.');
    redirect('lab/index.php');
}

$orders = Supabase::select('lab_orders', ['select' => '*,patients(full_name,phone)', 'order' => 'order_date.desc']);
$patients = Supabase::select('patients', ['order' => 'full_name.asc']);

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">🦷 Lab Orders</div>

<form method="post" class="card form-grid">
  <input type="hidden" name="form" value="create">
  <div>
    <label>Patient *</label>
    <select name="patient_id" required>
      <option value="">Select…</option>
      <?php foreach ($patients as $p): ?><option value="<?= h($p['id']) ?>"><?= h($p['full_name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div>
    <label>Order type *</label>
    <select name="order_type" required>
      <?php foreach ($ORDER_TYPES as $k => $v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div>
    <label>Expected delivery date</label>
    <input type="date" name="expected_delivery_date">
  </div>
  <div class="full">
    <label>Notes</label>
    <textarea name="notes" placeholder="Shade, size, lab name, instructions…"></textarea>
  </div>
  <div class="full"><button class="btn">Create lab order</button></div>
</form>

<div class="section-title" style="margin-top:24px;">📋 All lab orders</div>
<table class="data">
  <tr><th>Patient</th><th>Type</th><th>Ordered</th><th>Expected</th><th>Delivered</th><th>Status</th><th></th></tr>
  <?php if (!count($orders)): ?><tr><td colspan="7" style="color:var(--slate);">No lab orders yet.</td></tr><?php endif; ?>
  <?php foreach ($orders as $o): ?>
    <tr>
      <td><?= h($o['patients']['full_name'] ?? '—') ?></td>
      <td><span class="badge gray"><?= h($ORDER_TYPES[$o['order_type']] ?? ucfirst($o['order_type'])) ?></span></td>
      <td><?= h($o['order_date']) ?></td>
      <td><?= h($o['expected_delivery_date']) ?: '—' ?></td>
      <td><?= h($o['delivered_date']) ?: '—' ?></td>
      <td>
        <?php $badgeClass = ['ordered'=>'gray','in_progress'=>'amber','ready'=>'teal','delivered'=>'green'][$o['status']] ?? 'gray'; ?>
        <span class="badge <?= $badgeClass ?>"><?= h($STATUSES[$o['status']] ?? ucfirst($o['status'])) ?></span>
      </td>
      <td>
        <form method="post" style="display:flex;gap:6px;">
          <input type="hidden" name="form" value="update_status">
          <input type="hidden" name="id" value="<?= h($o['id']) ?>">
          <select name="status" onchange="this.form.submit()">
            <?php foreach ($STATUSES as $k => $v): ?>
              <option value="<?= h($k) ?>" <?= $o['status']===$k?'selected':'' ?>><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
