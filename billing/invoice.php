<?php
require_once __DIR__ . '/../config/config.php';
require_payment_access();
$page_title = 'Invoice';

$id = $_GET['id'] ?? '';
$invoice = Supabase::selectOne('invoices', ['id' => 'eq.' . $id, 'select' => '*,patients(full_name,phone),treatments(*,services(name))']);
if (!$invoice) { flash('error', 'Invoice not found.'); redirect('billing/index.php'); }

/** Recalculate an invoice's paid_amount/status from its actual payment rows. */
function recalc_invoice(string $invoiceId): void
{
    $invoice = Supabase::selectOne('invoices', ['id' => 'eq.' . $invoiceId]);
    $payments = Supabase::select('payments', ['invoice_id' => 'eq.' . $invoiceId]);
    $paid = array_sum(array_column($payments, 'amount'));
    $balance = (float)$invoice['total_amount'] - (float)$invoice['discount'] - $paid;
    $status = $balance <= 0 && $paid > 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
    Supabase::update('invoices', ['id' => 'eq.' . $invoiceId], ['paid_amount' => $paid, 'status' => $status]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'record') {
    $amount = (float)$_POST['amount'];
    Supabase::insert('payments', [
        'invoice_id' => $id,
        'amount' => $amount,
        'method' => $_POST['method'],
        'received_by' => current_staff()['id'],
    ]);
    recalc_invoice($id);
    log_audit('record_payment', 'invoice', $id, money($amount) . ' via ' . $_POST['method']);
    flash('success', 'Payment recorded.');
    redirect('billing/invoice.php?id=' . $id);
}

// Manager only: edit an existing payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'edit_payment') {
    require_manager();
    Supabase::update('payments', ['id' => 'eq.' . $_POST['payment_id']], [
        'amount' => (float)$_POST['amount'],
        'method' => $_POST['method'],
    ]);
    recalc_invoice($id);
    flash('success', 'Payment updated.');
    redirect('billing/invoice.php?id=' . $id);
}

// Cashier/Manager: issue a refund (recorded as a negative payment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'refund') {
    $amount = abs((float)$_POST['amount']);
    Supabase::insert('payments', [
        'invoice_id' => $id,
        'amount' => -$amount,
        'method' => $_POST['method'],
        'notes' => 'REFUND: ' . trim($_POST['reason']),
        'received_by' => current_staff()['id'],
    ]);
    recalc_invoice($id);
    flash('success', 'Refund recorded.');
    redirect('billing/invoice.php?id=' . $id);
}

// Manager only: delete a payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete_payment') {
    require_manager();
    Supabase::delete('payments', ['id' => 'eq.' . $_POST['payment_id']]);
    recalc_invoice($id);
    flash('success', 'Payment deleted.');
    redirect('billing/invoice.php?id=' . $id);
}

$payments = Supabase::select('payments', ['invoice_id' => 'eq.' . $id, 'select' => '*,staff(full_name)', 'order' => 'paid_at.desc']);

require __DIR__ . '/../includes/header.php';
$settings = clinic_settings();
?>

<div class="section-title">
  🧾 Invoice <?= h($invoice['invoice_no']) ?>
  <span><a class="btn small" target="_blank" href="<?= h(thermal_url('billing/thermal.php', ['id' => $id])) ?>">🖨 Print receipt</a> <a class="btn outline small" onclick="window.print()" href="#">Print page</a></span>
</div>

<div class="card" id="receipt-header" style="text-align:center;margin-bottom:16px;">
  <?php if (!empty($settings['logo_url'])): ?>
    <img src="<?= h($settings['logo_url']) ?>" alt="Logo" style="height:48px;object-fit:contain;margin-bottom:6px;">
  <?php endif; ?>
  <h3 style="margin:0;"><?= h($settings['clinic_name']) ?></h3>
  <p style="color:var(--slate);margin:2px 0 0;">Payment Receipt</p>
  <?php if (app_setting('invoice_terms', '') !== ''): ?><p style="color:var(--slate);font-size:12.5px;margin:6px 0 0;"><?= nl2br(h(app_setting('invoice_terms'))) ?></p><?php endif; ?>
</div>

<div class="form-grid">
  <div class="card">
    <h3>Bill to</h3>
    <p><?= h($invoice['patients']['full_name'] ?? '—') ?></p>
    <p style="color:var(--slate);"><?= h($invoice['patients']['phone'] ?? '') ?></p>
    <p><strong>Service:</strong> <?= h($invoice['treatments']['services']['name'] ?? '—') ?></p>
  </div>
  <div class="card">
    <h3>Amounts</h3>
    <p><strong>Total:</strong> <?= money($invoice['total_amount']) ?></p>
    <p><strong>Discount:</strong> <?= money($invoice['discount']) ?></p>
    <p><strong>Paid:</strong> <?= money($invoice['paid_amount']) ?></p>
    <p><strong>Balance:</strong> <?= money($invoice['balance']) ?><?= money_secondary($invoice['balance']) !== '' ? ' (' . money_secondary($invoice['balance']) . ')' : '' ?> <span class="badge <?= $invoice['status']==='paid'?'green':($invoice['status']==='partial'?'amber':'red') ?>"><?= h(ucfirst($invoice['status'])) ?></span></p>
  </div>
</div>

<?php if ((float)$invoice['balance'] > 0): ?>
<div class="section-title">Record a payment</div>
<form method="post" class="card" style="display:flex;gap:10px;align-items:end;">
  <input type="hidden" name="form" value="record">
  <div><label>Amount</label><input type="number" step="0.01" name="amount" max="<?= h($invoice['balance']) ?>" required></div>
  <div>
    <label>Method</label>
    <select name="method">
      <?php foreach (enabled_payment_methods() as $key => $label): ?>
        <option value="<?= h($key) ?>"><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn">Record payment</button>
</form>
<?php endif; ?>

<?php if ((float)$invoice['paid_amount'] > 0): ?>
<div class="section-title">Issue a refund</div>
<form method="post" class="card" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
  <input type="hidden" name="form" value="refund">
  <div><label>Refund amount</label><input type="number" step="0.01" name="amount" max="<?= h($invoice['paid_amount']) ?>" required></div>
  <div>
    <label>Method</label>
    <select name="method">
      <?php foreach (enabled_payment_methods() as $key => $label): ?>
        <option value="<?= h($key) ?>"><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div style="flex:1;min-width:160px;"><label>Reason</label><input type="text" name="reason" placeholder="e.g. treatment cancelled" required></div>
  <button class="btn danger" onclick="return confirm('Issue this refund? It will be subtracted from the amount paid.')">Issue Refund</button>
</form>
<?php endif; ?>

<div class="section-title">Payment history</div>
<table class="data">
  <tr><th>Date</th><th>Amount</th><th>Method</th><th>Received by</th><?= can_manage_prices() ? '<th></th>' : '' ?></tr>
  <?php if (!count($payments)): ?><tr><td colspan="5" style="color:var(--slate);">No payments recorded yet.</td></tr><?php endif; ?>
  <?php foreach ($payments as $p): ?>
    <?php $isRefund = (float)$p['amount'] < 0; ?>
    <tr>
      <td><?= h(date('d M Y H:i', strtotime($p['paid_at']))) ?></td>
      <td><?= $isRefund ? '<span class="badge red">Refund</span> ' : '' ?><?= money($p['amount']) ?><?= $isRefund && !empty($p['notes']) ? '<br><small style="color:var(--slate);">' . h($p['notes']) . '</small>' : '' ?></td>
      <td><span class="badge gray"><?= h(payment_method_label($p['method'])) ?></span></td>
      <td><?= h($p['staff']['full_name'] ?? '—') ?> <a class="btn small outline" target="_blank" title="Print receipt for this payment" href="<?= h(thermal_url('billing/thermal.php', ['id' => $id, 'payment' => $p['id']])) ?>">🖨 Receipt</a></td>
      <?php if (can_manage_prices()): ?>
      <td style="white-space:nowrap;">
        <button class="btn small outline" onclick="document.getElementById('edit-pay-<?= h($p['id']) ?>').style.display='table-row'">Edit</button>
        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this payment? The invoice balance will be recalculated.')">
          <input type="hidden" name="form" value="delete_payment">
          <input type="hidden" name="payment_id" value="<?= h($p['id']) ?>">
          <button class="btn small danger">Delete</button>
        </form>
      </td>
      <?php endif; ?>
    </tr>
    <?php if (can_manage_prices()): ?>
    <tr id="edit-pay-<?= h($p['id']) ?>" style="display:none;">
      <td colspan="5">
        <form method="post" style="display:flex;gap:8px;align-items:end;">
          <input type="hidden" name="form" value="edit_payment">
          <input type="hidden" name="payment_id" value="<?= h($p['id']) ?>">
          <div><label>Amount</label><input type="number" step="0.01" name="amount" value="<?= h($p['amount']) ?>" required></div>
          <div>
            <label>Method</label>
            <select name="method">
              <?php foreach (PAYMENT_METHODS as $key => $label): ?>
                <option value="<?= h($key) ?>" <?= $p['method']===$key?'selected':'' ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn small">Save</button>
        </form>
      </td>
    </tr>
    <?php endif; ?>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
