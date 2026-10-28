<?php
require_once __DIR__ . '/../config/config.php';
require_payment_access();
$page_title = 'Cashier Shift';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'open') {
    Supabase::insert('cashier_shifts', [
        'cashier_id' => current_staff()['id'],
        'shift_type' => $_POST['shift_type'] ?? 'custom',
        'opening_cash' => (float)($_POST['opening_cash'] ?? 0),
        'status' => 'open',
    ]);
    flash('success', 'Shift opened. You can now make sales.');
    redirect('pos/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close') {
    $shift = get_open_shift();
    if ($shift) {
        Supabase::update('cashier_shifts', ['id' => 'eq.' . $shift['id']], [
            'closed_at' => date('c'),
            'closing_cash' => (float)($_POST['closing_cash'] ?? 0),
            'notes' => trim($_POST['notes'] ?? ''),
            'status' => 'closed',
        ]);
        flash('success', 'Shift closed.');
    }
    redirect('pos/shift.php');
}

$openShift = get_open_shift();
$shiftSales = [];
$shiftTotal = 0;
if ($openShift) {
    $shiftSales = Supabase::select('pos_sales', ['shift_id' => 'eq.' . $openShift['id'], 'order' => 'created_at.desc']);
    $shiftTotal = array_sum(array_column($shiftSales, 'total_amount'));
}

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">🕒 Cashier Shift</div>

<?php if (!$openShift): ?>
  <div class="card" style="max-width:420px;">
    <h3>Open a new shift</h3>
    <p style="color:var(--slate);">You must open a shift before making POS sales.</p>
    <form method="post">
      <input type="hidden" name="action" value="open">
      <label>Shift type</label>
      <select name="shift_type">
        <option value="morning">Morning Shift (8:00 AM – 2:00 PM)</option>
        <option value="evening">Evening Shift (2:00 PM – 8:00 PM)</option>
        <option value="custom">Custom / Other</option>
      </select>
      <div style="height:10px;"></div>
      <label>Opening cash amount</label>
      <input type="number" step="0.01" name="opening_cash" value="0" required>
      <div style="height:14px;"></div>
      <button class="btn" style="width:100%;justify-content:center;">Open Shift</button>
    </form>
  </div>
<?php else: ?>
  <div class="stat-strip">
    <div class="stat">
      <div class="num"><?= h(ucfirst($openShift['shift_type'] ?? 'Custom')) ?></div>
      <div class="label">Shift type</div>
    </div>
    <div class="stat">
      <div class="num"><?= h(date('H:i', strtotime($openShift['opened_at']))) ?></div>
      <div class="label">Shift opened</div>
    </div>
    <div class="stat">
      <div class="num"><?= money($openShift['opening_cash']) ?></div>
      <div class="label">Opening cash</div>
    </div>
    <div class="stat">
      <div class="num"><?= count($shiftSales) ?></div>
      <div class="label">Sales this shift</div>
    </div>
    <div class="stat">
      <div class="num"><?= money($shiftTotal) ?></div>
      <div class="label">Total sold</div>
    </div>
  </div>

  <div class="card" style="max-width:420px;margin-bottom:20px;">
    <h3>Close this shift</h3>
    <form method="post">
      <input type="hidden" name="action" value="close">
      <label>Closing cash amount (counted in drawer)</label>
      <input type="number" step="0.01" name="closing_cash" required>
      <div style="height:10px;"></div>
      <label>Notes (optional)</label>
      <textarea name="notes"></textarea>
      <div style="height:14px;"></div>
      <button class="btn danger" style="width:100%;justify-content:center;" onclick="return confirm('Close this shift? You will need to open a new one to make more sales.')">Close Shift</button>
    </form>
  </div>

  <div class="section-title">Sales this shift</div>
  <table class="data">
    <tr><th>Sale #</th><th>Time</th><th>Total</th><th>Method</th></tr>
    <?php if (!count($shiftSales)): ?><tr><td colspan="4" style="color:var(--slate);">No sales yet this shift.</td></tr><?php endif; ?>
    <?php foreach ($shiftSales as $s): ?>
      <tr>
        <td><a href="<?= base_url('pos/receipt.php?id=' . $s['id']) ?>"><?= h($s['sale_no']) ?></a></td>
        <td><?= h(date('H:i', strtotime($s['created_at']))) ?></td>
        <td><?= money($s['total_amount']) ?></td>
        <td><span class="badge gray"><?= h(payment_method_label($s['payment_method'])) ?></span></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
