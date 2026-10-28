<?php
require_once __DIR__ . '/../config/config.php';
require_manager();
$page_title = 'Treatment Categories';

function save_extra_categories(array $names): void
{
    app_setting_save(['extra_categories' => implode(',', array_values(array_unique($names)))]);
}

$cats = category_list();
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($action === 'add') {
        if ($name === '' || strpos($name, ',') !== false) { flash('error', 'Enter a category name (no commas).'); }
        elseif (isset($cats[$name])) { flash('error', 'That category already exists.'); }
        else { save_extra_categories(array_merge(array_keys(array_filter($cats, fn($n) => $n === 0)), [$name])); flash('success', 'Category added.'); }
    } elseif ($action === 'rename') {
        $old = trim($_POST['old'] ?? '');
        if ($name === '' || strpos($name, ',') !== false) { flash('error', 'Enter a new name (no commas).'); }
        elseif ($name !== $old && isset($cats[$name])) { flash('error', 'A category with that name already exists.'); }
        elseif (isset($cats[$old])) {
            Supabase::update('services', ['category' => 'eq.' . $old], ['category' => $name]);
            $extras = array_keys(array_filter($cats, fn($n) => $n === 0));
            $extras = array_map(fn($c) => $c === $old ? $name : $c, $extras);
            save_extra_categories($extras);
            log_audit('rename_category', 'service_category', null, $old . ' -> ' . $name);
            flash('success', 'Category renamed on all its services.');
        }
    } elseif ($action === 'delete') {
        $old = trim($_POST['old'] ?? '');
        if (($cats[$old] ?? 1) > 0) { flash('error', 'Only empty categories can be deleted. Move its services first.'); }
        else { save_extra_categories(array_diff(array_keys(array_filter($cats, fn($n) => $n === 0)), [$old])); flash('success', 'Category deleted.'); }
    }
    redirect('settings/categories.php');
}

require __DIR__ . '/../includes/header.php';
?>

<p><a href="<?= base_url('settings/index.php') ?>">← All settings</a></p>
<div class="settings-title" style="margin-bottom:16px;">
  <span class="settings-icon" style="background:#FFEDD5;width:46px;height:46px;font-size:22px;">🦷</span>
  <div>
    <h1 style="margin:0;">Treatment Categories</h1>
    <div class="settings-sub">Group your services (braces, cleaning, extraction, filling…). Prices are edited under Treatments.</div>
  </div>
</div>

<form method="post" class="card" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:18px;">
  <input type="hidden" name="action" value="add">
  <div style="flex:1;min-width:200px;"><label>New category</label><input type="text" name="name" placeholder="e.g. Orthodontics" required></div>
  <button class="btn">+ Add category</button>
</form>

<table class="data">
  <tr><th>Category</th><th>Services</th><th></th></tr>
  <?php if (!count($cats)): ?><tr><td colspan="3" style="color:var(--slate);">No categories yet.</td></tr><?php endif; ?>
  <?php foreach ($cats as $name => $count): $rid = 'cat-' . md5($name); ?>
    <tr>
      <td><span class="badge teal"><?= h($name) ?></span></td>
      <td><?= (int)$count ?></td>
      <td style="white-space:nowrap;">
        <button type="button" class="btn small outline" onclick="document.getElementById('<?= $rid ?>').style.display='table-row'">Rename</button>
        <?php if ($count === 0): ?>
        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this empty category?')">
          <input type="hidden" name="action" value="delete"><input type="hidden" name="old" value="<?= h($name) ?>">
          <button class="btn small danger">Delete</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <tr id="<?= $rid ?>" style="display:none;">
      <td colspan="3">
        <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
          <input type="hidden" name="action" value="rename"><input type="hidden" name="old" value="<?= h($name) ?>">
          <div><label>New name</label><input type="text" name="name" value="<?= h($name) ?>" required></div>
          <button class="btn small">Save</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
