<?php
require_once __DIR__ . '/../config/config.php';
require_login();
require_payment_access();
require_once __DIR__ . '/../includes/thermal_layout.php';

$id = $_GET['id'] ?? '';
$sale = Supabase::selectOne('pos_sales', ['id' => 'eq.' . $id]);
if (!$sale) { flash('error', 'Sale not found.'); redirect('pos/index.php'); }

$cashier = $sale['cashier_id'] ? Supabase::selectOne('staff', ['id' => 'eq.' . $sale['cashier_id']]) : null;
$patient = $sale['patient_id'] ? Supabase::selectOne('patients', ['id' => 'eq.' . $sale['patient_id']]) : null;
$items = Supabase::select('pos_sale_items', ['sale_id' => 'eq.' . $id]);

log_audit('print_thermal_receipt', 'pos_sale', $id, $sale['sale_no']);

thermal_begin('Receipt ' . $sale['sale_no'], base_url('pos/receipt.php?id=' . $id));
?>
  <div class="title">SALE RECEIPT</div>
  <div class="sep"></div>

  <?= thermal_row('Receipt #', $sale['sale_no']) ?>
  <?= thermal_row('Date', date('d/m/Y H:i', strtotime($sale['created_at']))) ?>
  <?= thermal_row('Customer', $patient['full_name'] ?? 'Walk-in customer') ?>
  <?= thermal_row('Served by', $cashier['full_name'] ?? '—') ?>
  <div class="sep"></div>

<?php foreach ($items as $it): ?>
  <div class="item">
    <div class="nm"><?= h($it['item_name']) ?></div>
    <?= thermal_row(rtrim(rtrim(number_format((float)$it['quantity'], 2, '.', ''), '0'), '.') . ' x ' . money($it['unit_price']), money($it['subtotal'])) ?>
  </div>
<?php endforeach; ?>
  <div class="sep"></div>

  <?= thermal_row('TOTAL', money($sale['total_amount']), true) ?>
<?php if (money_secondary($sale['total_amount']) !== ''): ?>
  <div class="muted" style="text-align:right;">≈ <?= h(money_secondary($sale['total_amount'])) ?></div>
<?php endif; ?>
  <?= thermal_row('Paid by', payment_method_label($sale['payment_method'])) ?>
  <div class="stamp">PAID</div>
<?php thermal_end(); ?>
