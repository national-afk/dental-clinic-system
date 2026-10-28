<?php
require_once __DIR__ . '/../config/config.php';
require_payment_access();
$page_title = 'Receipt';

$id = $_GET['id'] ?? '';
$sale = Supabase::selectOne('pos_sales', ['id' => 'eq.' . $id]);
if (!$sale) { flash('error', 'Sale not found.'); redirect('pos/index.php'); }

$cashier = $sale['cashier_id'] ? Supabase::selectOne('staff', ['id' => 'eq.' . $sale['cashier_id']]) : null;
$patient = $sale['patient_id'] ? Supabase::selectOne('patients', ['id' => 'eq.' . $sale['patient_id']]) : null;
$items = Supabase::select('pos_sale_items', ['sale_id' => 'eq.' . $id]);

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
  🧾 Receipt <?= h($sale['sale_no']) ?>
  <span><a class="btn small" target="_blank" href="<?= h(thermal_url('pos/thermal.php', ['id' => $id])) ?>">🖨 Print receipt</a> <a class="btn outline small" onclick="window.print()" href="#">Print page</a></span>
</div>

<div class="card" style="max-width:420px;">
  <h3 style="text-align:center;"><?= h(APP_NAME) ?></h3>
  <p style="text-align:center;color:var(--slate);margin-top:0;">Sale receipt</p>
  <p><strong>Receipt #:</strong> <?= h($sale['sale_no']) ?><br>
     <strong>Date:</strong> <?= h(date('d M Y H:i', strtotime($sale['created_at']))) ?><br>
     <strong>Customer:</strong> <?= h($patient['full_name'] ?? 'Walk-in customer') ?><br>
     <strong>Served by:</strong> <?= h($cashier['full_name'] ?? '—') ?></p>
  <table class="data">
    <tr><th>Item</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
    <?php foreach ($items as $i): ?>
      <tr>
        <td><?= h($i['item_name']) ?></td>
        <td><?= h($i['quantity']) ?></td>
        <td><?= money($i['unit_price']) ?></td>
        <td><?= money($i['subtotal']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <p style="font-size:18px;font-weight:700;margin-top:12px;">Total: <?= money($sale['total_amount']) ?></p>
  <?php if (money_secondary($sale['total_amount']) !== ''): ?>
    <p style="color:var(--slate);margin-top:-8px;">≈ <?= money_secondary($sale['total_amount']) ?></p>
  <?php endif; ?>
  <p><span class="badge gray"><?= h(payment_method_label($sale['payment_method'])) ?></span></p>
  <p style="text-align:center;color:var(--slate);font-size:12.5px;margin-top:20px;">Thank you!</p>
</div>

<p style="margin-top:16px;"><a href="<?= base_url('pos/index.php') ?>">← New sale</a></p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
