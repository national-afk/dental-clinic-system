<?php
require_once __DIR__ . '/../config/config.php';
require_manager();
$page_title = 'Staff Category';

$all = Supabase::select('staff', ['order' => 'full_name.asc']);
$groups = [];
foreach (STAFF_ROLES as $rk => $rl) { $groups[$rk] = []; }
foreach ($all as $st) {
    $r = strtolower($st['role']);
    if ($r === 'admin') $r = 'manager';          // legacy name
    $groups[isset(STAFF_ROLES[$r]) ? $r : 'other'][] = $st;
}
if (empty($groups['other'])) unset($groups['other']);
$activeCount = count(array_filter($all, fn($s) => $s['active']));

require __DIR__ . '/../includes/header.php';
?>
<div class="section-title">
  🗂️ Staff Category
  <span>
    <a class="btn small outline" href="<?= base_url('staff/index.php') ?>">Manage accounts</a>
    <a class="btn small" href="<?= base_url('staff/register.php') ?>">+ Register staff</a>
  </span>
</div>

<div class="stat-strip">
  <div class="stat"><div class="num"><?= count($all) ?></div><div class="label">Total staff</div></div>
  <div class="stat"><div class="num"><?= $activeCount ?></div><div class="label">Active accounts</div></div>
  <?php foreach ($groups as $rk => $members): ?>
    <div class="stat"><div class="num"><?= count($members) ?></div><div class="label"><?= h($rk === 'other' ? 'Other / old roles' : role_label($rk) . (count($members) === 1 ? '' : 's')) ?></div></div>
  <?php endforeach; ?>
</div>

<?php foreach ($groups as $rk => $members): ?>
  <div class="section-title" style="margin-top:22px;">
    <?= h($rk === 'other' ? 'Other / old roles (run update_roles.sql)' : role_label($rk)) ?> <span class="badge gray"><?= count($members) ?></span>
    <?php if ($rk !== 'other'): ?><a class="btn small outline" href="<?= base_url('staff/index.php?role=' . $rk) ?>">Manage</a><?php endif; ?>
  </div>
  <table class="data">
    <tr><th>Staff ID</th><th>Name</th><th>Department</th><th>Phone</th><th>Status</th></tr>
    <?php if (!count($members)): ?><tr><td colspan="5" style="color:var(--slate);">No staff in this category yet.</td></tr><?php endif; ?>
    <?php foreach ($members as $m): ?>
      <tr>
        <td><?= h($m['staff_code']) ?: '—' ?></td>
        <td><a href="<?= base_url('staff/view.php?id=' . $m['id']) ?>"><?= h($m['full_name']) ?></a></td>
        <td><?= h($m['department']) ?: '—' ?></td>
        <td><?= h($m['phone']) ?: '—' ?></td>
        <td><?php
          if (!$m['active']) echo '<span class="badge red">Disabled</span>';
          else echo ($m['employment_status'] ?? 'active') === 'on_leave' ? '<span class="badge amber">On leave</span>' : '<span class="badge green">Active</span>'; ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endforeach; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
