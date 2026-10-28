<?php
require_once __DIR__ . '/../config/config.php';
require_manager();
$page_title = 'Payroll';

$ym = preg_match('/^\d{4}-\d{2}$/', $_REQUEST['month'] ?? '') ? $_REQUEST['month'] : date('Y-m');
$monthStart = $ym . '-01';

$staffList = payroll_staff();
$profiles = [];
foreach (Supabase::select('staff_salary') as $p) { $profiles[$p['staff_id']] = $p; }
$slips = [];
foreach (Supabase::select('payslips', ['period_month' => 'eq.' . $monthStart]) as $p) { $slips[$p['staff_id']] = $p; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $made = 0;
    foreach ($staffList as $s) {
        if ($action === 'generate_one' && $s['id'] !== ($_POST['staff_id'] ?? '')) continue;
        if (isset($slips[$s['id']]) || empty($profiles[$s['id']]) || (float)$profiles[$s['id']]['basic_salary'] <= 0) continue;
        if (payslip_generate($s, $profiles[$s['id']], $monthStart)) { $made++; }
    }
    if ($made) log_audit('generate_payslips', 'payroll', $ym, "$made payslip(s)");
    flash($made ? 'success' : 'error', $made ? "$made payslip(s) created for " . date('F Y', strtotime($monthStart)) . '. Review each one, then mark it paid.'
        : 'Nothing to create: everyone already has a payslip, or has no salary set. (Run add_payroll.sql if this is the first time.)');
    redirect('payroll/index.php?month=' . $ym);
}

$totalNet = 0; $totalPaid = 0; $draftCount = 0; $missing = 0;
foreach ($staffList as $s) {
    $sl = $slips[$s['id']] ?? null;
    if ($sl) { $totalNet += (float)$sl['net_pay']; if ($sl['status'] === 'paid') $totalPaid += (float)$sl['net_pay']; else $draftCount++; }
    elseif (!empty($profiles[$s['id']]) && (float)$profiles[$s['id']]['basic_salary'] > 0) { $missing++; }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
  💰 Payroll — <?= h(date('F Y', strtotime($monthStart))) ?>
  <span>
    <a class="btn small outline" href="<?= base_url('payroll/advances.php') ?>">Salary advances</a>
  </span>
</div>

<form method="get" class="card" style="margin-bottom:16px;display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
  <div><label>Month</label><input type="month" name="month" value="<?= h($ym) ?>" onchange="this.form.submit()"></div>
  <?php if ($missing): ?>
  <button type="submit" formmethod="post" name="action" value="generate_all" class="btn" onclick="return confirm('Create <?= (int)$missing ?> draft payslip(s) for <?= h(date('F Y', strtotime($monthStart))) ?>?')">⚙ Create payslips for everyone (<?= (int)$missing ?>)</button>
  <?php endif; ?>
</form>

<div class="stat-strip">
  <div class="stat"><div class="num"><?= money($totalNet) ?></div><div class="label">Payroll this month</div></div>
  <div class="stat"><div class="num"><?= money($totalPaid) ?></div><div class="label">Paid</div></div>
  <div class="stat warn"><div class="num"><?= money($totalNet - $totalPaid) ?></div><div class="label">Still to pay</div></div>
  <div class="stat"><div class="num"><?= (int)$draftCount ?></div><div class="label">Payslips waiting to be paid</div></div>
</div>

<table class="data">
  <tr><th>Staff</th><th>Role</th><th>Basic salary</th><th>Net pay</th><th>Status</th><th></th></tr>
  <?php if (!count($staffList)): ?><tr><td colspan="6" style="color:var(--slate);">No staff found.</td></tr><?php endif; ?>
  <?php foreach ($staffList as $s):
      $pf = $profiles[$s['id']] ?? null; $sl = $slips[$s['id']] ?? null; ?>
    <tr>
      <td><a href="<?= base_url('staff/view.php?id=' . $s['id']) ?>"><?= h($s['full_name']) ?></a><br><small style="color:var(--slate);"><?= h($s['staff_code']) ?></small></td>
      <td><span class="badge <?= is_manager_role($s['role']) ? 'amber' : 'teal' ?>"><?= h(role_label($s['role'])) ?></span></td>
      <td><?= $pf ? money($pf['basic_salary']) : '<span style="color:var(--slate);">Not set</span>' ?></td>
      <td><?= $sl ? money($sl['net_pay']) : '—' ?></td>
      <td><?php
        if ($sl) echo $sl['status'] === 'paid' ? '<span class="badge green">Paid</span>' : '<span class="badge amber">Draft</span>';
        else echo '<span class="badge gray">No payslip</span>'; ?></td>
      <td style="white-space:nowrap;">
        <a class="btn small outline" href="<?= base_url('payroll/salary.php?staff=' . $s['id']) ?>"><?= $pf ? 'Edit salary' : 'Set salary' ?></a>
        <?php if ($sl): ?>
          <a class="btn small" href="<?= base_url('payroll/payslip.php?id=' . $sl['id']) ?>">Payslip</a>
        <?php elseif ($pf && (float)$pf['basic_salary'] > 0): ?>
          <form method="post" style="display:inline;"><input type="hidden" name="month" value="<?= h($ym) ?>"><input type="hidden" name="action" value="generate_one"><input type="hidden" name="staff_id" value="<?= h($s['id']) ?>"><button class="btn small outline">Create payslip</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<p style="color:var(--slate);font-size:12.5px;">
  Paying a payslip also records the amount in Expenses (category “Salaries”). Managers see only Doctor, Nurse and Cashier pay; Superior sees everyone.
</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
