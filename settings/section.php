<?php
require_once __DIR__ . '/../config/config.php';
require_manager();
require_once __DIR__ . '/../includes/settings_schema.php';

$sections = settings_sections();
$key = $_GET['s'] ?? '';
if (!isset($sections[$key]) || !empty($sections[$key]['href'])) { redirect('settings/index.php'); }
$sec = $sections[$key];
$page_title = $sec['title'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clinic = [];
    $kv = [];
    $error = null;

    foreach ($sec['fields'] as $f) {
        $k = $f['key'];
        $type = $f['type'];
        $posted = $_POST[$k] ?? null;

        if ($type === 'file') {
            $input = $f['input'] ?? $k;
            if (!empty($_FILES[$input]['name']) && $_FILES[$input]['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) { $error = 'Logo must be an image (png, jpg, gif, webp, svg).'; continue; }
                $dir = __DIR__ . '/../uploads/logo/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $safe = 'logo_' . time() . '.' . $ext;
                move_uploaded_file($_FILES[$input]['tmp_name'], $dir . $safe);
                $clinic[$k] = base_url('uploads/logo/' . $safe);
            }
            continue;
        }

        if ($type === 'checkbox') { $val = !empty($posted); }
        elseif ($type === 'multicheck') { $val = implode(',', array_filter((array)$posted, fn($x) => isset($f['options'][$x]))); if ($val === '') { $val = implode(',', array_keys($f['options'])); } }
        elseif ($type === 'number') {
            $val = trim((string)$posted);
            if ($val !== '' && !is_numeric($val)) { $error = ($f['label'] ?? $k) . ' must be a number.'; continue; }
            if ($val !== '' && isset($f['min']) && (float)$val < $f['min']) { $error = ($f['label'] ?? $k) . ' must be at least ' . $f['min'] . '.'; continue; }
        }
        elseif ($type === 'select') { $val = isset($f['options'][$posted]) ? $posted : ($f['default'] ?? array_key_first($f['options'])); }
        else { $val = trim((string)$posted); }

        if (!empty($f['required']) && $val === '') { $error = ($f['label'] ?? $k) . ' is required.'; continue; }

        if (($f['store'] ?? '') === 'clinic') {
            if ($type === 'number') { $clinic[$k] = $val === '' ? null : (float)$val; }
            else { $clinic[$k] = $val; }
        } else {
            $kv[$k] = is_bool($val) ? ($val ? '1' : '0') : $val;
        }
    }

    if ($error) {
        flash('error', $error);
    } else {
        if ($clinic) { $clinic['updated_at'] = date('c'); Supabase::update('clinic_settings', ['id' => 'eq.1'], $clinic); }
        if ($kv) { app_setting_save($kv); }
        log_audit('update_settings', 'settings', $key, $sec['title']);
        flash('success', $sec['title'] . ' saved.');
    }
    redirect('settings/section.php?s=' . $key);
}

require __DIR__ . '/../includes/header.php';
?>

<p><a href="<?= base_url('settings/index.php') ?>">← All settings</a></p>

<div class="settings-title" style="margin-bottom:16px;">
  <span class="settings-icon" style="background:<?= h($sec['tint']) ?>;width:46px;height:46px;font-size:22px;"><?= $sec['icon'] ?></span>
  <div>
    <h1 style="margin:0;"><?= h($sec['title']) ?></h1>
    <div class="settings-sub"><?= h($sec['desc']) ?></div>
  </div>
</div>

<?php if (!empty($sec['note'])): ?>
  <div class="alert" style="background:#FDF0DB;color:#9A6412;"><?= h($sec['note']) ?></div>
<?php endif; ?>

<form method="post" class="card form-grid" enctype="multipart/form-data">
<?php foreach ($sec['fields'] as $f):
    $k = $f['key']; $val = settings_value($f); $full = !empty($f['full']); ?>
  <div class="<?= $full ? 'full' : '' ?>">
  <?php if ($f['type'] === 'checkbox'): ?>
    <label style="display:flex;align-items:center;gap:8px;font-weight:500;">
      <input type="checkbox" name="<?= h($k) ?>" value="1" style="width:auto;" <?= !empty($val) && $val !== '0' ? 'checked' : '' ?>>
      <?= h($f['label']) ?>
    </label>
  <?php else: ?>
    <label><?= h($f['label']) ?><?= !empty($f['required']) ? ' *' : '' ?></label>
    <?php if ($f['type'] === 'select'): ?>
      <select name="<?= h($k) ?>">
        <?php foreach ($f['options'] as $ov => $ol): ?>
          <option value="<?= h($ov) ?>" <?= (string)$val === (string)$ov ? 'selected' : '' ?>><?= h($ol) ?></option>
        <?php endforeach; ?>
      </select>
    <?php elseif ($f['type'] === 'multicheck'): $on = array_filter(explode(',', (string)$val)); ?>
      <div style="display:flex;gap:16px;flex-wrap:wrap;">
        <?php foreach ($f['options'] as $ov => $ol): ?>
          <label style="display:flex;align-items:center;gap:6px;font-weight:500;">
            <input type="checkbox" name="<?= h($k) ?>[]" value="<?= h($ov) ?>" style="width:auto;" <?= in_array($ov, $on, true) ? 'checked' : '' ?>> <?= h($ol) ?>
          </label>
        <?php endforeach; ?>
      </div>
    <?php elseif ($f['type'] === 'textarea'): ?>
      <textarea name="<?= h($k) ?>" placeholder="<?= h($f['placeholder'] ?? '') ?>"><?= h($val) ?></textarea>
    <?php elseif ($f['type'] === 'file'): ?>
      <?php if (!empty($val)): ?><div style="margin-bottom:10px;"><img src="<?= h($val) ?>" alt="Current" style="max-height:70px;border:1px solid var(--line);border-radius:8px;padding:6px;background:#fff;"></div><?php endif; ?>
      <input type="file" name="<?= h($f['input'] ?? $k) ?>" accept="image/*">
      <p style="color:var(--slate);font-size:12.5px;margin:6px 0 0;">PNG, JPG or SVG. Leave empty to keep the current one.</p>
    <?php elseif ($f['type'] === 'color'): ?>
      <input type="color" name="<?= h($k) ?>" value="<?= h($val) ?>" style="height:42px;padding:4px;">
    <?php else: ?>
      <input type="<?= h($f['type']) ?>" name="<?= h($k) ?>" value="<?= h($val) ?>"
             <?= isset($f['min']) ? 'min="' . h($f['min']) . '"' : '' ?> <?= isset($f['step']) ? 'step="' . h($f['step']) . '"' : '' ?>
             placeholder="<?= h($f['placeholder'] ?? '') ?>" <?= !empty($f['required']) ? 'required' : '' ?>>
    <?php endif; ?>
  <?php endif; ?>
  <?php if (!empty($f['help'])): ?><p style="color:var(--slate);font-size:12.5px;margin:6px 0 0;"><?= h($f['help']) ?></p><?php endif; ?>
  </div>
<?php endforeach; ?>
  <div class="full"><button class="btn">Save Settings</button> <a class="btn outline" href="<?= base_url('settings/index.php') ?>">Cancel</a></div>
</form>

<?php if (!empty($sec['after'])) { include __DIR__ . '/../' . $sec['after']; } ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
