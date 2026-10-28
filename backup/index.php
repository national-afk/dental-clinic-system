<?php
require_once __DIR__ . '/../config/config.php';
require_manager();
$page_title = 'Backup / Database';

$tables = [
    'patients' => 'Patients',
    'dental_chart' => 'Dental Chart',
    'appointments' => 'Appointments',
    'treatments' => 'Treatments',
    'invoices' => 'Invoices',
    'payments' => 'Payments',
    'inventory_items' => 'Inventory Items',
    'inventory_movements' => 'Inventory Movements',
    'expenses' => 'Expenses',
    'pos_sales' => 'POS Sales',
    'pos_sale_items' => 'POS Sale Items',
    'staff' => 'Staff (passwords excluded)',
];

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">🗄️ Backup / Database (Manager only)</div>

<div class="card" style="margin-bottom:18px;color:var(--slate);">
  Download a JSON snapshot of any table below. Keep these files somewhere safe —
  they contain your clinic's data and can be used to restore records manually if needed.
</div>

<table class="data">
  <tr><th>Table</th><th></th></tr>
  <?php foreach ($tables as $table => $label): ?>
    <tr>
      <td><?= h($label) ?></td>
      <td><a class="btn small outline" href="<?= base_url('backup/export.php?table=' . $table) ?>">⬇ Download JSON</a></td>
    </tr>
  <?php endforeach; ?>
  <tr>
    <td><strong>Full backup (all tables above)</strong></td>
    <td><a class="btn small" href="<?= base_url('backup/export.php?table=all') ?>">⬇ Download full backup</a></td>
  </tr>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
