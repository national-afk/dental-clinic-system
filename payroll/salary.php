<?php
require_once __DIR__ . '/../config/config.php';
require_manager();
$page_title = 'Salary setup';

$staffId = $_GET['staff'] ?? '';
$st = Supabase::selectOne('staff', ['id' => 'eq.' . $staffId]);
if (!$st || !can_manage_staff_member($st['role'])) { flash('error', 'Staff member not found or not allowed.'); redirect('payroll/index.php'); }
$pf = Supabase::selectOne('staff_salary', ['staff_id' => 'eq.' . $staffId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $num = fn($k) => max(0, round((float)($_POST[$k] ?? 0), 2));
    $row = [
        'basic_salary' => $num('basic_salary'), 'transport_allowance' => $num('transport_allowance'),
        'other_allowance' => $num('other_allowance'), 'standard_deduction' => $num('standard_deduction'),
        'payment_method' => isset(salary_pay_methods()[$_POST['payment_method'] ?? '']) ? $_POST['payment_method'] : 'cash',
        'payment_account' => trim($_POST['payment_account'] ?? ''), 'notes' => trim($_POST['notes'] ?? ''), 'updated_at' => date('c'),
    ];
    $ok = $pf ? Supabase::update('staff_salary', ['id' => 'eq.' . $pf['id']], $row)
              : Supabase::insert('staff_salary', $row + ['staff_id' => $staffId]);
    if ($ok === false) { flash('error', 'Could not save. Run add_payroll.sql in Supabase first.'); }
    else {
        log_audit('set_salary', 'staff', $staffId, $st['username'] . ': basic ' . $row['basic_salary']);
        flash('success', 'Salary saved for ' . $st['full_name'] . '. It applies to payslips created from now on.');
    }
    redirect('payroll/index.php');
}

require __DIR__ . '/../includes/header.php';
$v = fn($k, $d = '') => h($pf[$k] ?? $d);
?>
<p><a href="<?= base_url('payroll/index.php') ?>">← Payroll</a></p>
<div class="section-title">💼 Salary — <?= h($st['full_name']) ?> <span class="badge teal"><?= h(role_label($st['role'])) ?></span></div>

<form method="post" class="card form-grid">
  <div><label>Basic monthly salary *</label><input type="number" step="0.01" min="0" name="basic_salary" value="<?= $v('basic_salary', '0') ?>" required></div>
  <div><label>Transport allowance</label><input type="number" step="0.01" min="0" name="transport_allowance" value="<?= $v('transport_allowance', '0') ?>"></div>
  <div><label>Other allowance</label><input type="number" step="0.01" min="0" name="other_allowance" value="<?= $v('other_allowance', '0') ?>"></div>
  <div><label>Standard monthly deduction (insurance, etc.)</label><input type="number" step="0.01" min="0" name="standard_deduction" value="<?= $v('standard_deduction', '0') ?>"></div>
  <div>
    <label>Paid by</label>
    <select name="payment_method">
      <?php foreach (salary_pay_methods() as $k => $l): ?><option value="<?= h($k) ?>" <?= ($pf['payment_method'] ?? 'cash') === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div><label>Mobile money number / bank account</label><input type="text" name="payment_account" value="<?= $v('payment_account') ?>" placeholder="e.g. 063 4123456"></div>
  <div class="full"><label>Notes</label><textarea name="notes"><?= $v('notes') ?></textarea></div>
  <div class="full"><button class="btn">Save salary</button> <a class="btn outline" href="<?= base_url('payroll/index.php') ?>">Cancel</a></div>
</form>
<p style="color:var(--slate);font-size:12.5px;">Changes apply to payslips created after saving; existing payslips are not touched.</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
