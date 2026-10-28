<?php
require_once __DIR__ . '/../config/config.php';
$page_title = 'Pharmacy / Inventory';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'item') {
    require_manage_pharmacy();
    Supabase::insert('inventory_items', [
        'item_name' => trim($_POST['item_name']),
        'item_type' => $_POST['item_type'],
        'category' => trim($_POST['category']),
        'unit' => trim($_POST['unit']) ?: 'pcs',
        'quantity' => (float)$_POST['quantity'],
        'low_stock_threshold' => (float)$_POST['low_stock_threshold'],
        'unit_price' => (float)$_POST['unit_price'],
        'expiry_date' => $_POST['expiry_date'] ?: null,
    ]);
    flash('success', 'Item added to inventory.');
    redirect('pharmacy/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'movement') {
    $item = Supabase::selectOne('inventory_items', ['id' => 'eq.' . $_POST['item_id']]);
    $qty = (float)$_POST['quantity'];
    $type = $_POST['movement_type'];
    $newQty = $type === 'in' ? (float)$item['quantity'] + $qty : (float)$item['quantity'] - $qty;

    Supabase::update('inventory_items', ['id' => 'eq.' . $item['id']], ['quantity' => max(0, $newQty)]);
    Supabase::insert('inventory_movements', [
        'item_id' => $item['id'],
        'movement_type' => $type,
        'quantity' => $qty,
        'reason' => trim($_POST['reason']),
        'moved_by' => current_staff()['id'],
    ]);
    flash('success', 'Stock ' . ($type === 'in' ? 'received' : 'issued') . '.');
    redirect('pharmacy/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'edit_item') {
    require_manage_pharmacy();
    Supabase::update('inventory_items', ['id' => 'eq.' . $_POST['id']], [
        'item_name' => trim($_POST['item_name']),
        'item_type' => $_POST['item_type'],
        'category' => trim($_POST['category']),
        'unit' => trim($_POST['unit']) ?: 'pcs',
        'low_stock_threshold' => (float)$_POST['low_stock_threshold'],
        'unit_price' => (float)$_POST['unit_price'],
        'expiry_date' => $_POST['expiry_date'] ?: null,
    ]);
    flash('success', 'Item updated.');
    redirect('pharmacy/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete_item') {
    require_manage_pharmacy();
    Supabase::delete('inventory_items', ['id' => 'eq.' . $_POST['id']]);
    flash('success', 'Item deleted.');
    redirect('pharmacy/index.php');
}

$search = trim($_GET['q'] ?? '');
$itemFilters = ['order' => 'item_name.asc'];
if ($search !== '') {
    $itemFilters['item_name'] = 'ilike.*' . $search . '*';
}
$items = Supabase::select('inventory_items', $itemFilters);
$movements = Supabase::select('inventory_movements', ['select' => '*,inventory_items(item_name)', 'order' => 'moved_at.desc', 'limit' => 20]);

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">💊 Inventory</div>

<form method="get" class="card" style="margin-bottom:14px;display:flex;gap:10px;">
  <input type="text" name="q" placeholder="Search item name…" value="<?= h($search) ?>">
  <button class="btn outline">Search</button>
</form>

<table class="data">
  <tr><th>#</th><th>Item</th><th>Category</th><th>Quantity</th><th>Unit price</th><th>Expiry Date</th><th>Status</th><?= can_manage_pharmacy() ? '<th></th>' : '' ?></tr>
  <?php if (!count($items)): ?><tr><td colspan="8" style="color:var(--slate);">No items found.</td></tr><?php endif; ?>
  <?php $n = 0; foreach ($items as $i): $n++;
    $low = (float)$i['quantity'] <= (float)$i['low_stock_threshold'];
    $expiringSoon = !empty($i['expiry_date']) && strtotime($i['expiry_date']) <= strtotime('+90 days');
    $expired = !empty($i['expiry_date']) && strtotime($i['expiry_date']) < time();
    if ($expired) { $statusBadge = '<span class="badge red">🔴 Expired</span>'; }
    elseif ($expiringSoon) { $statusBadge = '<span class="badge amber">🟡 Expiring</span>'; }
    elseif ($low) { $statusBadge = '<span class="badge amber">🟡 Low Stock</span>'; }
    else { $statusBadge = '<span class="badge green">🟢 In Stock</span>'; }
  ?>
    <tr>
      <td><?= $n ?></td>
      <td><?= h($i['item_name']) ?> <span class="badge gray"><?= h(ucfirst($i['item_type'])) ?></span></td>
      <td><?= h($i['category']) ?: '—' ?></td>
      <td><?= h($i['quantity']) ?> <?= h($i['unit']) ?></td>
      <td><?= money($i['unit_price']) ?></td>
      <td><?= $i['expiry_date'] ? h(date('m/Y', strtotime($i['expiry_date']))) : '—' ?></td>
      <td><?= $statusBadge ?></td>
      <?php if (can_manage_pharmacy()): ?>
      <td style="white-space:nowrap;">
        <button class="btn small outline" onclick="document.getElementById('edit-<?= h($i['id']) ?>').style.display='table-row'">Edit</button>
        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this item permanently?')">
          <input type="hidden" name="form" value="delete_item">
          <input type="hidden" name="id" value="<?= h($i['id']) ?>">
          <button class="btn small danger">Delete</button>
        </form>
      </td>
      <?php endif; ?>
    </tr>
    <?php if (can_manage_pharmacy()): ?>
    <tr id="edit-<?= h($i['id']) ?>" style="display:none;">
      <td colspan="8">
        <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
          <input type="hidden" name="form" value="edit_item">
          <input type="hidden" name="id" value="<?= h($i['id']) ?>">
          <div><label>Name</label><input type="text" name="item_name" value="<?= h($i['item_name']) ?>" required></div>
          <div><label>Type</label>
            <select name="item_type">
              <option value="material" <?= $i['item_type']==='material'?'selected':'' ?>>Material</option>
              <option value="medicine" <?= $i['item_type']==='medicine'?'selected':'' ?>>Medicine</option>
            </select>
          </div>
          <div><label>Category</label><input type="text" name="category" value="<?= h($i['category']) ?>" placeholder="Antibiotic, Painkiller…"></div>
          <div><label>Unit</label><input type="text" name="unit" value="<?= h($i['unit']) ?>"></div>
          <div><label>Low stock at</label><input type="number" step="0.01" name="low_stock_threshold" value="<?= h($i['low_stock_threshold']) ?>"></div>
          <div><label>Unit price</label><input type="number" step="0.01" name="unit_price" value="<?= h($i['unit_price']) ?>"></div>
          <div><label>Expiry date</label><input type="date" name="expiry_date" value="<?= h($i['expiry_date']) ?>"></div>
          <button class="btn small">Save</button>
        </form>
      </td>
    </tr>
    <?php endif; ?>
  <?php endforeach; ?>
</table>

<div class="form-grid" style="margin-top:24px;">
  <div class="card">
    <h3>+ Add new item</h3>
    <?php if (!can_manage_pharmacy()): ?>
      <p style="color:var(--slate);">Only a Manager can add new inventory items. You can still record stock in/out on the right.</p>
    <?php else: ?>
    <form method="post">
      <input type="hidden" name="form" value="item">
      <label>Item name</label>
      <input type="text" name="item_name" required>
      <div style="height:10px;"></div>
      <label>Type</label>
      <select name="item_type">
        <option value="material">Dental material</option>
        <option value="medicine">Medicine</option>
      </select>
      <div style="height:10px;"></div>
      <label>Category</label>
      <input type="text" name="category" placeholder="e.g. Antibiotic, Painkiller, Anesthetic, Oral Care">
      <div style="height:10px;"></div>
      <label>Unit (e.g. pcs, box, ml)</label>
      <input type="text" name="unit" value="pcs">
      <div style="height:10px;"></div>
      <label>Starting quantity</label>
      <input type="number" step="0.01" name="quantity" value="0">
      <div style="height:10px;"></div>
      <label>Low stock threshold</label>
      <input type="number" step="0.01" name="low_stock_threshold" value="5">
      <div style="height:10px;"></div>
      <label>Unit price</label>
      <input type="number" step="0.01" name="unit_price" value="0">
      <div style="height:10px;"></div>
      <label>Expiry date</label>
      <input type="date" name="expiry_date">
      <div style="height:14px;"></div>
      <button class="btn">Add item</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Stock in / out</h3>
    <form method="post">
      <input type="hidden" name="form" value="movement">
      <label>Item</label>
      <select name="item_id" required>
        <option value="">Select…</option>
        <?php foreach ($items as $i): ?><option value="<?= h($i['id']) ?>"><?= h($i['item_name']) ?></option><?php endforeach; ?>
      </select>
      <div style="height:10px;"></div>
      <label>Movement</label>
      <select name="movement_type">
        <option value="in">Stock in (received)</option>
        <option value="out">Stock out (used)</option>
      </select>
      <div style="height:10px;"></div>
      <label>Quantity</label>
      <input type="number" step="0.01" name="quantity" required>
      <div style="height:10px;"></div>
      <label>Reason / note</label>
      <input type="text" name="reason" placeholder="e.g. used in extraction, new delivery">
      <div style="height:14px;"></div>
      <button class="btn">Save movement</button>
    </form>
  </div>
</div>

<div class="section-title" style="margin-top:26px;">Recent stock movements</div>
<table class="data">
  <tr><th>Date</th><th>Item</th><th>Movement</th><th>Qty</th><th>Reason</th></tr>
  <?php if (!count($movements)): ?><tr><td colspan="5" style="color:var(--slate);">No movements yet.</td></tr><?php endif; ?>
  <?php foreach ($movements as $m): ?>
    <tr>
      <td><?= h(date('d M Y H:i', strtotime($m['moved_at']))) ?></td>
      <td><?= h($m['inventory_items']['item_name'] ?? '—') ?></td>
      <td><span class="badge <?= $m['movement_type']==='in'?'green':'amber' ?>"><?= h(ucfirst($m['movement_type'])) ?></span></td>
      <td><?= h($m['quantity']) ?></td>
      <td><?= h($m['reason']) ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
