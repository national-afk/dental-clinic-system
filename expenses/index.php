<?php
require_once __DIR__ . '/../config/config.php';
require_manager();
$page_title = 'Expenses';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Supabase::insert('expenses', [
        'category' => trim($_POST['category']),
        'amount' => (float)$_POST['amount'],
        'expense_date' => $_POST['expense_date'],
        'notes' => trim($_POST['notes']),
        'recorded_by' => current_staff()['id'],
    ]);
    flash('success', 'Expense recorded.');
    redirect('expenses/index.php');
}

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$expenses = Supabase::select('expenses', [
    'expense_date' => 'gte.' . $from,
    'and' => '(expense_date.lte.' . $to . ')',
    'select' => '*,staff(full_name)',
    'order' => 'expense_date.desc',
]);
$total = array_sum(array_column($expenses, 'amount'));

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">💸 Expenses (Manager only)</div>

<form method="get" class="card" style="margin-bottom:18px;display:flex;gap:14px;align-items:end;">
  <div><label>From</label><input type="date" name="from" value="<?= h($from) ?>"></div>
  <div><label>To</label><input type="date" name="to" value="<?= h($to) ?>"></div>
  <button class="btn outline">Filter</button>
</form>

<div class="stat-strip">
  <div class="stat warn">
    <div class="num"><?= money($total) ?></div>
    <div class="label">Total expenses in range</div>
  </div>
</div>

<div class="section-title">+ Record an expense</div>
<form method="post" class="card form-grid">
  <div><label>Category *</label><input type="text" name="category" placeholder="e.g. Rent, Salaries, Supplies" required></div>
  <div><label>Amount *</label><input type="number" step="0.01" name="amount" required></div>
  <div><label>Date</label><input type="date" name="expense_date" value="<?= date('Y-m-d') ?>"></div>
  <div class="full"><label>Notes</label><textarea name="notes"></textarea></div>
  <div class="full"><button class="btn">Save expense</button></div>
</form>

<div class="section-title">📋 Expense history</div>
<table class="data">
  <tr><th>Date</th><th>Category</th><th>Amount</th><th>Notes</th><th>Recorded by</th></tr>
  <?php if (!count($expenses)): ?><tr><td colspan="5" style="color:var(--slate);">No expenses in this range.</td></tr><?php endif; ?>
  <?php foreach ($expenses as $e): ?>
    <tr>
      <td><?= h($e['expense_date']) ?></td>
      <td><span class="badge gray"><?= h($e['category']) ?></span></td>
      <td><?= money($e['amount']) ?></td>
      <td><?= h($e['notes']) ?: '—' ?></td>
      <td><?= h($e['staff']['full_name'] ?? '—') ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
