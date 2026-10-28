<?php
require_once __DIR__ . '/../config/config.php';
require_payment_access();
$page_title = can_manage_prices() ? 'Report' : 'My Sales';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$filters = [
    'paid_at' => 'gte.' . $from . 'T00:00:00',
    'and' => '(paid_at.lte.' . $to . 'T23:59:59)',
    'select' => '*,invoices(invoice_no,patient_id,patients(full_name,phone)),staff(full_name)',
    'order' => 'paid_at.desc',
];

// Cashiers (and any non-manager role) only ever see their own collected payments
if (!can_manage_prices()) {
    $filters['received_by'] = 'eq.' . current_staff()['id'];
}

$payments = Supabase::select('payments', $filters);

$total = array_sum(array_column($payments, 'amount'));

$byMethod = array_fill_keys(array_keys(PAYMENT_METHODS), 0);
foreach ($payments as $p) {
    $byMethod[$p['method']] = ($byMethod[$p['method']] ?? 0) + (float)$p['amount'];
}

$byCashier = [];
foreach ($payments as $p) {
    $name = $p['staff']['full_name'] ?? 'Unknown';
    $byCashier[$name] = ($byCashier[$name] ?? 0) + (float)$p['amount'];
}

$byDay = [];
foreach ($payments as $p) {
    $day = substr($p['paid_at'], 0, 10);
    $byDay[$day] = ($byDay[$day] ?? 0) + (float)$p['amount'];
}
ksort($byDay);

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">📈 <?= can_manage_prices() ? 'Report' : 'My Sales' ?></div>

<form method="get" class="card" style="margin-bottom:18px;display:flex;gap:14px;align-items:end;">
  <div><label>From</label><input type="date" name="from" value="<?= h($from) ?>"></div>
  <div><label>To</label><input type="date" name="to" value="<?= h($to) ?>"></div>
  <button class="btn outline">Filter</button>
</form>

<div class="stat-strip">
  <div class="stat">
    <div class="num"><?= money($total) ?></div>
    <div class="label">Total collected</div>
  </div>
  <div class="stat">
    <div class="num"><?= money($byMethod['cash'] ?? 0) ?></div>
    <div class="label">Cash</div>
  </div>
  <div class="stat">
    <div class="num"><?= money($byMethod['card'] ?? 0) ?></div>
    <div class="label">Card</div>
  </div>
  <div class="stat">
    <div class="num"><?= money($byMethod['zaad'] ?? 0) ?></div>
    <div class="label">ZAAD</div>
  </div>
  <div class="stat">
    <div class="num"><?= money($byMethod['edahab'] ?? 0) ?></div>
    <div class="label">eDahab</div>
  </div>
  <div class="stat">
    <div class="num"><?= money($byMethod['premier'] ?? 0) ?></div>
    <div class="label">Premier Wallet</div>
  </div>
</div>

<div class="section-title">👤 Collected by staff</div>
<table class="data">
  <tr><th>Staff</th><th>Total collected</th></tr>
  <?php if (!count($byCashier)): ?><tr><td colspan="2" style="color:var(--slate);">No payments in this range.</td></tr><?php endif; ?>
  <?php foreach ($byCashier as $name => $amt): ?>
    <tr><td><?= h($name) ?></td><td><?= money($amt) ?></td></tr>
  <?php endforeach; ?>
</table>

<div class="section-title">📅 Daily totals</div>
<table class="data">
  <tr><th>Date</th><th>Total</th></tr>
  <?php if (!count($byDay)): ?><tr><td colspan="2" style="color:var(--slate);">No payments in this range.</td></tr><?php endif; ?>
  <?php foreach ($byDay as $day => $amt): ?>
    <tr><td><?= h(date('d M Y', strtotime($day))) ?></td><td><?= money($amt) ?></td></tr>
  <?php endforeach; ?>
</table>

<div class="section-title">🧾 All payments in range</div>
<table class="data">
  <tr><th>Date</th><th>Invoice</th><th>Patient</th><th>Phone</th><th>Amount</th><th>Method</th><th>Received by</th></tr>
  <?php if (!count($payments)): ?><tr><td colspan="7" style="color:var(--slate);">No payments found.</td></tr><?php endif; ?>
  <?php foreach ($payments as $p): ?>
    <tr>
      <td><?= h(date('d M Y H:i', strtotime($p['paid_at']))) ?></td>
      <td><?= h($p['invoices']['invoice_no'] ?? '—') ?></td>
      <td><?= h($p['invoices']['patients']['full_name'] ?? '—') ?></td>
      <td><?= h($p['invoices']['patients']['phone']) ? '<a href="tel:' . h($p['invoices']['patients']['phone']) . '">' . h($p['invoices']['patients']['phone']) . '</a>' : '—' ?></td>
      <td><?= money($p['amount']) ?></td>
      <td><span class="badge gray"><?= h(payment_method_label($p['method'])) ?></span></td>
      <td><?= h($p['staff']['full_name'] ?? '—') ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
