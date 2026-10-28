<?php
require_once __DIR__ . '/../config/config.php';
require_payment_access();
$page_title = 'POS — Make Sale';

if (!isset($_SESSION['pos_cart'])) { $_SESSION['pos_cart'] = []; } // item_id => qty

// checkout requires an open shift — everything else (browsing/searching) is fine without one
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
    require_open_shift();
}

// add item to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $itemId = $_POST['item_id'];
    $qty = max(1, (float)($_POST['qty'] ?? 1));
    $_SESSION['pos_cart'][$itemId] = ($_SESSION['pos_cart'][$itemId] ?? 0) + $qty;
    redirect('pos/index.php' . (($_GET['q'] ?? '') !== '' ? '?q=' . urlencode($_GET['q']) : ''));
}

// remove item from cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    unset($_SESSION['pos_cart'][$_POST['item_id']]);
    redirect('pos/index.php');
}

// increase quantity of an item already in the cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'increase') {
    $itemId = $_POST['item_id'];
    if (isset($_SESSION['pos_cart'][$itemId])) { $_SESSION['pos_cart'][$itemId] += 1; }
    redirect('pos/index.php');
}

// decrease quantity of an item already in the cart (removes it once it hits 0)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'decrease') {
    $itemId = $_POST['item_id'];
    if (isset($_SESSION['pos_cart'][$itemId])) {
        $_SESSION['pos_cart'][$itemId] -= 1;
        if ($_SESSION['pos_cart'][$itemId] <= 0) { unset($_SESSION['pos_cart'][$itemId]); }
    }
    redirect('pos/index.php');
}

// clear cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear') {
    $_SESSION['pos_cart'] = [];
    redirect('pos/index.php');
}

// checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
    if (empty($_SESSION['pos_cart'])) {
        flash('error', 'Cart is empty.');
        redirect('pos/index.php');
    }

    $saleItems = [];
    $total = 0;

    foreach ($_SESSION['pos_cart'] as $itemId => $qty) {
        $item = Supabase::selectOne('inventory_items', ['id' => 'eq.' . $itemId]);
        if (!$item) continue;
        $subtotal = (float)$item['unit_price'] * $qty;
        $total += $subtotal;
        $saleItems[] = [
            'item_id' => $itemId,
            'item_name' => $item['item_name'],
            'quantity' => $qty,
            'unit_price' => $item['unit_price'],
            'subtotal' => $subtotal,
        ];
    }

    $saleNo = generate_pos_sale_no();
    $patientId = $_POST['patient_id'] ?: null;
    $sale = Supabase::insert('pos_sales', [
        'sale_no' => $saleNo,
        'patient_id' => $patientId,
        'total_amount' => $total,
        'payment_method' => $_POST['payment_method'],
        'cashier_id' => current_staff()['id'],
        'shift_id' => get_open_shift()['id'],
    ]);
    $saleId = $sale[0]['id'] ?? null;

    if (!$saleId) {
        // ---- Temporary diagnostic: show the real Supabase error ----
        $debugUrl = rtrim(SUPABASE_URL, '/') . '/rest/v1/pos_sales';
        $debugPayload = json_encode([
            'sale_no' => $saleNo,
            'patient_id' => $patientId,
            'total_amount' => $total,
            'payment_method' => $_POST['payment_method'],
            'cashier_id' => current_staff()['id'],
        ]);
        $ch = curl_init($debugUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $debugPayload);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        flash('error', 'Could not create the sale record. HTTP ' . $code . ': ' . $resp);
        redirect('pos/index.php');
    }

    foreach ($saleItems as $si) {
        $si['sale_id'] = $saleId;
        Supabase::insert('pos_sale_items', $si);

        // deduct stock + log movement
        $item = Supabase::selectOne('inventory_items', ['id' => 'eq.' . $si['item_id']]);
        Supabase::update('inventory_items', ['id' => 'eq.' . $si['item_id']], [
            'quantity' => max(0, (float)$item['quantity'] - $si['quantity']),
        ]);
        Supabase::insert('inventory_movements', [
            'item_id' => $si['item_id'],
            'movement_type' => 'out',
            'quantity' => $si['quantity'],
            'reason' => 'POS sale ' . $saleNo,
            'moved_by' => current_staff()['id'],
        ]);
    }

    $_SESSION['pos_cart'] = [];
    redirect('pos/receipt.php?id=' . $saleId);
}

$search = trim($_GET['q'] ?? '');
$itemFilters = ['order' => 'item_name.asc'];
if ($search !== '') { $itemFilters['item_name'] = 'ilike.*' . $search . '*'; }
$items = Supabase::select('inventory_items', $itemFilters);
$itemsById = [];
foreach (Supabase::select('inventory_items') as $i) { $itemsById[$i['id']] = $i; }

$cartTotal = 0;
foreach ($_SESSION['pos_cart'] as $id => $qty) {
    if (isset($itemsById[$id])) { $cartTotal += (float)$itemsById[$id]['unit_price'] * $qty; }
}

$patients = Supabase::select('patients', ['order' => 'full_name.asc']);

require __DIR__ . '/../includes/header.php';
$openShift = get_open_shift();
?>

<div class="section-title">
  🧾 POS — Make Sale
  <a class="btn small outline" href="<?= base_url('pos/shift.php') ?>"><?= $openShift ? '🕒 Shift open' : '🕒 Open Shift' ?></a>
</div>

<?php if (!$openShift): ?>
  <div class="alert error">
    No shift is open. <a href="<?= base_url('pos/shift.php') ?>">Open a shift</a> before making sales.
  </div>
<?php endif; ?>

<div class="form-grid">
  <div>
    <form method="get" class="card" style="margin-bottom:14px;display:flex;gap:10px;">
      <input type="text" name="q" placeholder="Search products…" value="<?= h($search) ?>">
      <button class="btn outline">Search</button>
    </form>

    <table class="data">
      <tr><th>Item</th><th>Price</th><th>In stock</th><th></th></tr>
      <?php if (!count($items)): ?><tr><td colspan="4" style="color:var(--slate);">No items found.</td></tr><?php endif; ?>
      <?php foreach ($items as $i): ?>
        <tr>
          <td><?= h($i['item_name']) ?></td>
          <td><?= money($i['unit_price']) ?></td>
          <td><?= h($i['quantity']) ?> <?= h($i['unit']) ?></td>
          <td>
            <form method="post" style="display:flex;gap:6px;">
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="item_id" value="<?= h($i['id']) ?>">
              <input type="number" name="qty" value="1" min="1" style="width:60px;">
              <button class="btn small">+ Add</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div>
    <div class="card">
      <h3>🛒 Cart</h3>
      <?php if (empty($_SESSION['pos_cart'])): ?>
        <p style="color:var(--slate);">Cart is empty. Add products from the list.</p>
      <?php else: ?>
        <table class="data" style="margin-bottom:14px;">
          <tr><th>Item</th><th>Qty</th><th>Subtotal</th><th></th></tr>
          <?php foreach ($_SESSION['pos_cart'] as $id => $qty): $it = $itemsById[$id] ?? null; if (!$it) continue; ?>
            <tr>
              <td><?= h($it['item_name']) ?></td>
              <td style="white-space:nowrap;">
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="decrease">
                  <input type="hidden" name="item_id" value="<?= h($id) ?>">
                  <button type="submit" class="btn small outline" style="padding:2px 9px;">−</button>
                </form>
                <span style="display:inline-block;min-width:22px;text-align:center;"><?= h($qty) ?></span>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="increase">
                  <input type="hidden" name="item_id" value="<?= h($id) ?>">
                  <button type="submit" class="btn small outline" style="padding:2px 9px;">+</button>
                </form>
              </td>
              <td><?= money($it['unit_price'] * $qty) ?></td>
              <td>
                <form method="post">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="item_id" value="<?= h($id) ?>">
                  <button class="btn small outline">✕</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
        <p style="font-size:18px;font-weight:700;">Total: <?= money($cartTotal) ?></p>
        <?php if (!$openShift): ?>
          <p style="color:var(--clay);font-size:13px;">Open a shift before completing this sale.</p>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="action" value="checkout">
          <label>Customer (optional)</label>
          <select name="patient_id">
            <option value="">Walk-in customer</option>
            <?php foreach ($patients as $p): ?>
              <option value="<?= h($p['id']) ?>"><?= h($p['full_name']) ?> (<?= h($p['patient_code']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <div style="height:12px;"></div>
          <label>Payment method</label>
          <select name="payment_method">
            <?php foreach (enabled_payment_methods() as $key => $label): ?>
              <option value="<?= h($key) ?>"><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
          <div style="height:12px;"></div>
          <button class="btn" style="width:100%;justify-content:center;">Complete Sale &amp; Print Receipt</button>
        </form>
        <form method="post" style="margin-top:8px;">
          <input type="hidden" name="action" value="clear">
          <button class="btn outline small" style="width:100%;justify-content:center;" onclick="return confirm('Clear the whole cart?')">Clear cart</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
