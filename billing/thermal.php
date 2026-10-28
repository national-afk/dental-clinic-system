<?php
require_once __DIR__ . '/../config/config.php';
require_login();
require_payment_access();
require_once __DIR__ . '/../includes/thermal_layout.php';

$id = $_GET['id'] ?? '';
$paymentId = $_GET['payment'] ?? '';

$invoice = Supabase::selectOne('invoices', [
    'id' => 'eq.' . $id,
    'select' => '*,patients(full_name,phone,patient_code),treatments(*,services(name))',
]);
if (!$invoice) { flash('error', 'Invoice not found.'); redirect('billing/index.php'); }

$payments = Supabase::select('payments', [
    'invoice_id' => 'eq.' . $id,
    'select' => '*,staff(full_name)',
    'order' => 'paid_at.asc',
]);

$thisPayment = null;
if ($paymentId !== '') {
    foreach ($payments as $p) { if ($p['id'] === $paymentId) { $thisPayment = $p; break; } }
}

$total    = (float)$invoice['total_amount'];
$discount = (float)$invoice['discount'];
$net      = $total - $discount;
$paid     = (float)$invoice['paid_amount'];
$balance  = $net - $paid;

if ($thisPayment) {
    $docTitle = ((float)$thisPayment['amount'] < 0) ? 'REFUND RECEIPT' : 'PAYMENT RECEIPT';
} else {
    $docTitle = 'INVOICE';
}

$statusLabel = $balance <= 0.004 && $paid > 0 ? 'PAID' : ($paid > 0 ? 'PARTIAL' : 'UNPAID');
$service = $invoice['treatments']['services']['name'] ?? 'Dental service';
$tooth   = $invoice['treatments']['tooth_number'] ?? null;

log_audit('print_thermal_receipt', 'invoice', $id, $invoice['invoice_no'] . ($thisPayment ? ' (payment)' : ''));

thermal_begin($docTitle . ' ' . $invoice['invoice_no'], base_url('billing/invoice.php?id=' . $id));
?>
  <div class="title"><?= h($docTitle) ?></div>
  <div class="sep"></div>

  <?= thermal_row('Invoice #', $invoice['invoice_no']) ?>
  <?= thermal_row('Date', date('d/m/Y H:i', strtotime($thisPayment['paid_at'] ?? $invoice['created_at']))) ?>
  <?= thermal_row('Patient', $invoice['patients']['full_name'] ?? '—') ?>
<?php if (!empty($invoice['patients']['patient_code'])): ?>
  <?= thermal_row('Patient ID', $invoice['patients']['patient_code']) ?>
<?php endif; ?>
<?php if (!empty($invoice['patients']['phone'])): ?>
  <?= thermal_row('Phone', $invoice['patients']['phone']) ?>
<?php endif; ?>
  <div class="sep"></div>

  <div class="item">
    <div class="nm bold"><?= h($service) ?><?= $tooth ? ' (Tooth #' . h($tooth) . ')' : '' ?></div>
    <?= thermal_row('', money($total)) ?>
  </div>
  <div class="sep"></div>

  <?= thermal_row('Subtotal', money($total)) ?>
<?php if ($discount > 0): ?>
  <?= thermal_row('Discount', '-' . money($discount)) ?>
<?php endif; ?>
  <?= thermal_row('TOTAL', money($net), true) ?>
<?php if (money_secondary($net) !== ''): ?>
  <div class="muted" style="text-align:right;">≈ <?= h(money_secondary($net)) ?></div>
<?php endif; ?>

<?php if ($thisPayment): ?>
  <div class="sep"></div>
  <?= thermal_row(((float)$thisPayment['amount'] < 0) ? 'Refunded' : 'Amount paid', money(abs((float)$thisPayment['amount'])), true) ?>
  <?= thermal_row('Method', payment_method_label($thisPayment['method'])) ?>
<?php if (!empty($thisPayment['notes'])): ?>
  <div class="muted"><?= h($thisPayment['notes']) ?></div>
<?php endif; ?>
  <?= thermal_row('Received by', $thisPayment['staff']['full_name'] ?? '—') ?>
  <div class="sep"></div>
  <?= thermal_row('Total paid so far', money($paid)) ?>
  <?= thermal_row('Balance', money(max(0, $balance)), true) ?>
<?php else: ?>
  <div class="sep"></div>
<?php if (count($payments)): ?>
  <div class="bold">Payments</div>
<?php foreach ($payments as $p): ?>
  <?= thermal_row(date('d/m/y', strtotime($p['paid_at'])) . ' ' . payment_method_label($p['method']), money($p['amount'])) ?>
<?php endforeach; ?>
  <div class="sep"></div>
<?php endif; ?>
  <?= thermal_row('Paid', money($paid)) ?>
  <?= thermal_row('Balance due', money(max(0, $balance)), true) ?>
<?php if (money_secondary(max(0, $balance)) !== ''): ?>
  <div class="muted" style="text-align:right;">≈ <?= h(money_secondary(max(0, $balance))) ?></div>
<?php endif; ?>
<?php endif; ?>

  <div class="stamp"><?= h($statusLabel) ?></div>
<?php if (app_setting('invoice_terms', '') !== ''): ?>
  <div class="muted"><?= nl2br(h(app_setting('invoice_terms'))) ?></div>
<?php endif; ?>
<?php thermal_end(); ?>
