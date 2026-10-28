<?php
require_once __DIR__ . '/../config/config.php';
require_manager();
require_once __DIR__ . '/../includes/settings_schema.php';
$page_title = 'Settings';
$sections = settings_sections();

require __DIR__ . '/../includes/header.php';
?>

<div class="settings-head">
  <div class="settings-title">
    <span class="settings-gear">⚙️</span>
    <div>
      <h1>Settings</h1>
      <div class="settings-sub">Manage your clinic settings and preferences</div>
    </div>
  </div>
  <div class="settings-search">
    <span>🔍</span>
    <input type="search" id="settings-search" placeholder="Search settings..." autocomplete="off">
  </div>
</div>

<div class="settings-grid" id="settings-grid">
<?php foreach ($sections as $key => $sec):
    $href = !empty($sec['href']) ? base_url($sec['href']) : base_url('settings/section.php?s=' . $key); ?>
  <div class="settings-card" data-search="<?= h(strtolower($sec['title'] . ' ' . $sec['desc'])) ?>">
    <div class="settings-card-top">
      <div class="settings-icon" style="background:<?= h($sec['tint']) ?>;"><?= $sec['icon'] ?></div>
      <div>
        <div class="settings-card-title"><?= h($sec['title']) ?></div>
        <div class="settings-card-desc"><?= h($sec['desc']) ?></div>
      </div>
    </div>
    <a class="settings-open" href="<?= h($href) ?>">Open Settings →</a>
  </div>
<?php endforeach; ?>
</div>
<div id="settings-empty" class="card" style="display:none;color:var(--slate);">No settings match your search.</div>

<script>
document.getElementById('settings-search').addEventListener('input', function () {
  var q = this.value.trim().toLowerCase(), shown = 0;
  document.querySelectorAll('#settings-grid .settings-card').forEach(function (c) {
    var hit = !q || c.dataset.search.indexOf(q) !== -1;
    c.style.display = hit ? '' : 'none';
    if (hit) shown++;
  });
  document.getElementById('settings-empty').style.display = shown ? 'none' : 'block';
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
