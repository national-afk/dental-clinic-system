<?php
require_once __DIR__ . '/../config/config.php';
require_manager();
$page_title = 'Salary advances';

$staffList = payroll_staff();
$allowed = array_column($staffList, 'full_name', 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $sid = $_POST['staff_id'] ?? ''; $amt = round((float)($_POST['amount'] ?? 0), 2);
        if (!isset($allowed[$sid]) || $amt <= 0) { flash('error', 'Choose a staff member and an amount above zero.'); }
        else {
            $ok = Supabase::insert('salary_advances', [
                'staff_id' => $sid, 'amount' => $amt, 'monthly_deduction' => max(0, round((float)($_POST['monthly_deduction'] ?? 0), 2)),
                'advance_date' => $_POST['advance_date'] ?: date('Y-m-d'), 'reason' => trim($_POST['reason'] ?? ''), 'given_by' => current_staff()['id'],
            ]);
            if ($ok === false) flash('error', 'Could not save. Run add_payroll.sql in Supabase first.');
            else { log_audit('salary_advance', 'staff', $sid, money($amt)); flash('success', 'Advance recorded. It will be deducted from the next payslip(s).'); }
        }
    } elseif ($action === 'delete') {
        $adv = Supabase::selectOne('salary_advances', ['id' => 'eq.' . ($_POST['id'] ?? '')]);
        if ($adv && isset($allowed[$adv['staff_id']]) && (float)$adv['repaid'] == 0 && Supabase::delete('salary_advances', ['id' => 'eq.' . $adv['id']])) flash('success', 'Advance deleted.');
        else flash('error', 'Only an advance that has not been used in any payslip can be deleted.');
    }
    redirect('payroll/advances.php');
}

$advances = [];
foreach (Supabase::select('salary_advances', ['order' => 'advance_date.desc']) as $a) {
    if (isset($allowed[$a['staff_id']])) $advances[] = $a;
}

require __DIR__ . '/../includes/header.php';
?>
<p><a href="<?= base_url('payroll/index.php') ?>">← Payroll</a></p>
<div class="section-title">💸 Salary advances</div>

<form method="post" class="card form-grid">
  <input type="hidden" name="action" value="add">
  <div><label>Staff member *</label>
    <select name="staff_id" required><option value="">Select…</option>
      <?php foreach ($allowed as $sid => $nm): ?><option value="<?= h($sid) ?>"><?= h($nm) ?></option><?php endforeach; ?></select></div>
  <div><label>Amount *</label><input type="number" step="0.01" min="0.01" name="amount" required></div>
  <div><label>Deduct per month (0 = all at once)</label><input type="number" step="0.01" min="0" name="monthly_deduction" value="0"></div>
  <div><label>Date</label><input type="date" name="advance_date" value="<?= date('Y-m-d') ?>"></div>
  <div class="full"><label>Reason</label><input type="text" name="reason"></div>
  <div class="full"><button class="btn">Record advance</button></div>
</form>

<table class="data" style="margin-top:20px;">
  <tr><th>Date</th><th>Staff</th><th>Amount</th><th>Repaid</th><th>Balance</th><th>Reason</th><th></th></tr>
  <?php if (!count($advances)): ?><tr><td colspan="7" style="color:var(--slate);">No advances recorded.</td></tr><?php endif; ?>
  <?php foreach ($advances as $a): $bal = (float)$a['amount'] - (float)$a['repaid']; ?>
    <tr>
      <td><?= h(date('d M Y', strtotime($a['advance_date']))) ?></td>
      <td><?= h($allowed[$a['staff_id']]) ?></td>
      <td><?= money($a['amount']) ?></td>
      <td><?= money($a['repaid']) ?></td>
      <td><?= $bal > 0.004 ? '<span class="badge amber">' . h(money($bal)) . '</span>' : '<span class="badge green">Cleared</span>' ?></td>
      <td><?= h($a['reason']) ?: '—' ?></td>
      <td><?php if ((float)$a['repaid'] == 0): ?><form method="post" onsubmit="return confirm('Delete this advance?')" style="display:inline;"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($a['id']) ?>"><button class="btn small danger">Delete</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
