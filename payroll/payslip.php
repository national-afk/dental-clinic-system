<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$page_title = 'Payslip';

$id = $_GET['id'] ?? '';
$ps = Supabase::selectOne('payslips', ['id' => 'eq.' . $id]);
$st = $ps ? Supabase::selectOne('staff', ['id' => 'eq.' . $ps['staff_id']]) : null;
if (!$ps || !$st) { flash('error', 'Payslip not found.'); redirect('payroll/my.php'); }

$isOwner = $st['id'] === current_staff()['id'];
$canManage = can_manage_prices() && can_manage_staff_member($st['role']);
if (!$canManage && !($isOwner && $ps['status'] === 'paid')) {
    flash('error', 'You can only open your own paid payslips.'); redirect('payroll/my.php');
}
$draft = $ps['status'] === 'draft';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    $action = $_POST['action'] ?? '';
    if ($action === 'pay') {
        $m = isset(salary_pay_methods()[$_POST['payment_method'] ?? '']) ? $_POST['payment_method'] : 'cash';
        if (payslip_mark_paid($id, $m)) {
            log_audit('pay_salary', 'payslip', $id, $ps['payslip_no'] . ' — ' . money($ps['net_pay']) . ' via ' . $m);
            flash('success', 'Salary paid and recorded in Expenses.');
        } else { flash('error', 'This payslip was already paid.'); }
    } elseif ($draft && $action === 'add_item') {
        $kind = ($_POST['kind'] ?? '') === 'deduction' ? 'deduction' : 'earning';
        $label = trim($_POST['label'] ?? ''); $amt = (float)($_POST['amount'] ?? 0);
        if ($label === '' || $amt <= 0) { flash('error', 'Enter a label and an amount above zero.'); }
        else { payslip_add_item($id, $kind, $label, $amt); payslip_recalc($id); }
    } elseif ($draft && $action === 'remove_item') {
        Supabase::delete('payslip_items', ['id' => 'eq.' . ($_POST['item_id'] ?? ''), 'payslip_id' => 'eq.' . $id]);
        payslip_recalc($id);
    } elseif ($draft && $action === 'save_basic') {
        Supabase::update('payslips', ['id' => 'eq.' . $id], ['basic_salary' => max(0, round((float)$_POST['basic_salary'], 2)), 'notes' => trim($_POST['notes'] ?? '')]);
        payslip_recalc($id);
        flash('success', 'Payslip updated.');
    } elseif ($draft && $action === 'delete') {
        Supabase::delete('payslips', ['id' => 'eq.' . $id]);
        log_audit('delete_payslip', 'payslip', $id, $ps['payslip_no']);
        flash('success', 'Draft payslip deleted.'); redirect('payroll/index.php?month=' . substr($ps['period_month'], 0, 7));
    }
    redirect('payroll/payslip.php?id=' . $id);
}

$items = Supabase::select('payslip_items', ['payslip_id' => 'eq.' . $id, 'order' => 'kind.asc']);
$earnings = array_values(array_filter($items, fn($i) => $i['kind'] === 'earning'));
$deductions = array_values(array_filter($items, fn($i) => $i['kind'] === 'deduction'));
$att = attendance_summary($st['id'], $ps['period_month']);
$settings = clinic_settings();
$back = $canManage ? base_url('payroll/index.php?month=' . substr($ps['period_month'], 0, 7)) : base_url('payroll/my.php');

require __DIR__ . '/../includes/header.php';
?>
<p><a href="<?= h($back) ?>">← Back</a></p>
<div class="section-title">
  🧾 Payslip <?= h($ps['payslip_no']) ?>
  <span><button type="button" class="btn small outline" onclick="window.print()">🖨 Print</button></span>
</div>

<div class="card" id="receipt-header" style="text-align:center;margin-bottom:16px;">
  <?php if (!empty($settings['logo_url'])): ?><img src="<?= h($settings['logo_url']) ?>" alt="Logo" style="height:48px;object-fit:contain;margin-bottom:6px;"><?php endif; ?>
  <h3 style="margin:0;"><?= h($settings['clinic_name']) ?></h3>
  <p style="color:var(--slate);margin:2px 0 0;">PAYSLIP — <?= h(date('F Y', strtotime($ps['period_month']))) ?></p>
</div>

<div class="form-grid">
  <div class="card">
    <h3>Employee</h3>
    <p><strong><?= h($st['full_name']) ?></strong><br>
       Staff ID: <?= h($st['staff_code']) ?: '—' ?><br>
       Role: <?= h(role_label($st['role'])) ?><?= !empty($st['department']) ? ' · ' . h($st['department']) : '' ?><br>
       Phone: <?= h($st['phone']) ?: '—' ?></p>
  </div>
  <div class="card">
    <h3>Period &amp; status</h3>
    <p>Month: <strong><?= h(date('F Y', strtotime($ps['period_month']))) ?></strong><br>
       Status: <?= $draft ? '<span class="badge amber">Draft — not paid yet</span>' : '<span class="badge green">Paid</span>' ?><br>
       <?php if (!$draft): ?>Paid on <?= h(date('d M Y H:i', strtotime($ps['paid_at']))) ?> via <?= h(payment_method_label((string)$ps['payment_method'])) ?><br><?php endif; ?>
       Attendance: <?= (int)$att['days'] ?> day(s), <?= h($att['hours']) ?> h</p>
  </div>
</div>

<div class="form-grid" style="margin-top:16px;">
  <div>
    <table class="data">
      <tr><th colspan="3">Earnings</th></tr>
      <tr><td>Basic salary</td><td><?= money($ps['basic_salary']) ?></td><td></td></tr>
      <?php foreach ($earnings as $i): ?>
        <tr><td><?= h($i['label']) ?></td><td><?= money($i['amount']) ?></td>
          <td><?php if ($draft && $canManage): ?><form method="post"><input type="hidden" name="action" value="remove_item"><input type="hidden" name="item_id" value="<?= h($i['id']) ?>"><button class="btn small outline" title="Remove">✕</button></form><?php endif; ?></td></tr>
      <?php endforeach; ?>
      <tr><td><strong>Gross pay</strong></td><td colspan="2"><strong><?= money($ps['gross_pay']) ?></strong></td></tr>
    </table>
  </div>
  <div>
    <table class="data">
      <tr><th colspan="3">Deductions</th></tr>
      <?php if (!count($deductions)): ?><tr><td colspan="3" style="color:var(--slate);">None</td></tr><?php endif; ?>
      <?php foreach ($deductions as $i): ?>
        <tr><td><?= h($i['label']) ?></td><td><?= money($i['amount']) ?></td>
          <td><?php if ($draft && $canManage): ?><form method="post"><input type="hidden" name="action" value="remove_item"><input type="hidden" name="item_id" value="<?= h($i['id']) ?>"><button class="btn small outline" title="Remove">✕</button></form><?php endif; ?></td></tr>
      <?php endforeach; ?>
      <tr><td><strong>Total deductions</strong></td><td colspan="2"><strong><?= money($ps['total_deductions']) ?></strong></td></tr>
    </table>
  </div>
</div>

<div class="card" style="margin-top:16px;text-align:center;">
  <div style="color:var(--slate);">NET PAY</div>
  <div class="display" style="font-size:30px;font-weight:800;"><?= money($ps['net_pay']) ?></div>
  <?php if (money_secondary($ps['net_pay']) !== ''): ?><div style="color:var(--slate);">≈ <?= h(money_secondary($ps['net_pay'])) ?></div><?php endif; ?>
  <?php if ((float)$ps['net_pay'] < 0): ?><div class="badge red" style="margin-top:6px;">Deductions are larger than the pay — adjust before paying</div><?php endif; ?>
</div>
<?php if (!empty($ps['notes'])): ?><p style="color:var(--slate);">Notes: <?= h($ps['notes']) ?></p><?php endif; ?>

<?php if ($draft && $canManage): ?>
  <div class="section-title" style="margin-top:26px;">✏️ Adjust this payslip</div>
  <div class="form-grid">
    <form method="post" class="card" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
      <input type="hidden" name="action" value="add_item">
      <div><label>Type</label><select name="kind"><option value="earning">Earning (bonus, overtime…)</option><option value="deduction">Deduction (absence, penalty…)</option></select></div>
      <div style="flex:1;min-width:140px;"><label>Label</label><input type="text" name="label" placeholder="e.g. Overtime 6h" required></div>
      <div style="width:120px;"><label>Amount</label><input type="number" step="0.01" min="0" name="amount" required></div>
      <button class="btn small">Add line</button>
    </form>
    <form method="post" class="card" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
      <input type="hidden" name="action" value="save_basic">
      <div style="width:150px;"><label>Basic salary</label><input type="number" step="0.01" min="0" name="basic_salary" value="<?= h($ps['basic_salary']) ?>"></div>
      <div style="flex:1;min-width:140px;"><label>Notes</label><input type="text" name="notes" value="<?= h($ps['notes']) ?>"></div>
      <button class="btn small outline">Save</button>
    </form>
  </div>

  <div class="section-title" style="margin-top:20px;">💵 Pay salary</div>
  <form method="post" class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
    <input type="hidden" name="action" value="pay">
    <?php $pf = Supabase::selectOne('staff_salary', ['staff_id' => 'eq.' . $st['id']]); ?>
    <div><label>Payment method</label>
      <select name="payment_method"><?php foreach (salary_pay_methods() as $k => $l): ?><option value="<?= h($k) ?>" <?= ($pf['payment_method'] ?? 'cash') === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
    <?php if (!empty($pf['payment_account'])): ?><div style="color:var(--slate);">Send to: <strong><?= h($pf['payment_account']) ?></strong></div><?php endif; ?>
    <button class="btn" onclick="return confirm('Mark <?= h(money($ps['net_pay'])) ?> as paid to <?= h($st['full_name']) ?>? This cannot be undone.')">Mark as paid — <?= money($ps['net_pay']) ?></button>
  </form>
  <form method="post" style="margin-top:10px;" onsubmit="return confirm('Delete this draft payslip?')">
    <input type="hidden" name="action" value="delete"><button class="btn small danger">Delete draft</button>
  </form>
<?php endif; ?>

<div class="card print-only-sign" style="margin-top:20px;display:flex;justify-content:space-between;gap:30px;color:var(--slate);">
  <div style="flex:1;border-top:1px solid var(--line);padding-top:6px;">Employee signature</div>
  <div style="flex:1;border-top:1px solid var(--line);padding-top:6px;">Authorised by</div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
