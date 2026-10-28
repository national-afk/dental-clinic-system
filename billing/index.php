<?php
require_once __DIR__ . '/../config/config.php';
require_payment_access();
$page_title = 'Billing & Payments';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form']) && $_POST['form'] === 'invoice') {
    $treatment = Supabase::selectOne('treatments', ['id' => 'eq.' . $_POST['treatment_id']]);
    $discount = (float)($_POST['discount'] ?? 0);
    $total = (float)$treatment['price'];

    $created = Supabase::insert('invoices', [
        'invoice_no' => generate_invoice_no(),
        'patient_id' => $treatment['patient_id'],
        'treatment_id' => $treatment['id'],
        'total_amount' => $total,
        'discount' => $discount,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);
    flash('success', 'Invoice created.');
    $newId = $created[0]['id'] ?? null;
    redirect($newId ? 'billing/invoice.php?id=' . $newId : 'billing/index.php');
}

// treatments that don't have an invoice yet
$allTreatments = Supabase::select('treatments', ['select' => '*,patients(full_name),services(name)', 'order' => 'created_at.desc']);
$billedTreatmentIds = array_column(Supabase::select('invoices', ['select' => 'treatment_id']), 'treatment_id');
$unbilled = array_filter($allTreatments, fn($t) => !in_array($t['id'], $billedTreatmentIds));

$invoices = Supabase::select('invoices', ['select' => '*,patients(full_name)', 'order' => 'created_at.desc']);

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">🧾 Create invoice from a treatment</div>
<form method="post" class="card" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
  <input type="hidden" name="form" value="invoice">
  <div style="flex:2;min-width:220px;">
    <label>Unbilled treatment</label>
    <select name="treatment_id" required>
      <option value="">Select…</option>
      <?php foreach ($unbilled as $t): ?>
        <option value="<?= h($t['id']) ?>"><?= h($t['patients']['full_name'] ?? '—') ?> — <?= h($t['services']['name'] ?? '—') ?> (<?= money($t['price']) ?>)</option>
      <?php endforeach; ?>
    </select>
  </div>
  <div style="flex:1;min-width:120px;">
    <label>Discount</label>
    <input type="number" step="0.01" name="discount" value="0">
  </div>
  <button class="btn">Create invoice</button>
</form>

<div class="section-title">💳 All invoices</div>
<table class="data">
  <tr><th>Invoice #</th><th>Patient</th><th>Total</th><th>Discount</th><th>Paid</th><th>Balance</th><th>Status</th><th></th></tr>
  <?php if (!count($invoices)): ?><tr><td colspan="8" style="color:var(--slate);">No invoices yet.</td></tr><?php endif; ?>
  <?php foreach ($invoices as $inv): ?>
    <tr>
      <td><?= h($inv['invoice_no']) ?></td>
      <td><?= h($inv['patients']['full_name'] ?? '—') ?></td>
      <td><?= money($inv['total_amount']) ?></td>
      <td><?= money($inv['discount']) ?></td>
      <td><?= money($inv['paid_amount']) ?></td>
      <td><?= money($inv['balance']) ?></td>
      <td><span class="badge <?= $inv['status']==='paid'?'green':($inv['status']==='partial'?'amber':'red') ?>"><?= h(ucfirst($inv['status'])) ?></span></td>
      <td><a class="btn small outline" href="<?= base_url('billing/invoice.php?id=' . $inv['id']) ?>">Open</a></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
