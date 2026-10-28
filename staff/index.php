<?php
require_once __DIR__ . '/../config/config.php';
require_manager();
$page_title = 'Manage Staff';

/** Loads a staff row and checks the signed-in user is allowed to change it. Returns the row or redirects. */
function load_manageable_staff(string $id, bool $allowSelf = false): array
{
    $t = Supabase::selectOne('staff', ['id' => 'eq.' . $id]);
    if (!$t) { flash('error', 'Staff member not found.'); redirect('staff/index.php'); }
    if (!can_manage_staff_member($t['role'])) {
        flash('error', 'Only a Superior can change a Manager or Superior account.');
        redirect('staff/index.php');
    }
    if (!$allowSelf && $t['id'] === current_staff()['id']) {
        flash('error', 'You cannot do that to your own account.');
        redirect('staff/index.php');
    }
    return $t;
}

/** True if at least one OTHER active Superior exists (so the last one is never removed or demoted). */
function another_superior_exists(string $exceptId): bool
{
    foreach (Supabase::select('staff', ['role' => 'eq.superior', 'active' => 'eq.true', 'select' => 'id']) as $r) {
        if ($r['id'] !== $exceptId) return true;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'toggle') {
    $s = load_manageable_staff($_POST['id']);
    if ($s['role'] === 'superior' && $s['active'] && !another_superior_exists($s['id'])) {
        flash('error', 'You cannot disable the last active Superior.'); redirect('staff/index.php');
    }
    Supabase::update('staff', ['id' => 'eq.' . $_POST['id']], ['active' => !$s['active']]);
    log_audit($s['active'] ? 'disable_staff' : 'enable_staff', 'staff', $s['id'], $s['username']);
    redirect('staff/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'reset_password') {
    if (strlen($_POST['new_password']) < password_min_length()) { flash('error', 'Password must be at least ' . password_min_length() . ' characters (Settings -> Security).'); redirect('staff/index.php'); }
    load_manageable_staff($_POST['id'], true);
    $hash = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
    Supabase::update('staff', ['id' => 'eq.' . $_POST['id']], ['password_hash' => $hash]);
    log_audit('reset_password', 'staff', $_POST['id']);
    flash('success', 'Password reset.');
    redirect('staff/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'change_role') {
    $t = load_manageable_staff($_POST['id']);
    $new = $_POST['role'] ?? '';
    if (!isset(assignable_roles()[$new])) {
        flash('error', 'You are not allowed to assign that role.');
    } elseif ($t['role'] === 'superior' && $new !== 'superior' && !another_superior_exists($t['id'])) {
        flash('error', 'You cannot demote the last Superior.');
    } else {
        Supabase::update('staff', ['id' => 'eq.' . $t['id']], ['role' => $new]);
        log_audit('change_role', 'staff', $t['id'], $t['username'] . ': ' . $t['role'] . ' -> ' . $new);
        flash('success', $t['full_name'] . ' is now ' . role_label($new) . '.');
    }
    redirect('staff/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete') {
    $t = load_manageable_staff($_POST['id']);
    if ($t['role'] === 'superior' && !another_superior_exists($t['id'])) {
        flash('error', 'You cannot delete the last Superior.');
    } elseif (Supabase::delete('staff', ['id' => 'eq.' . $_POST['id']])) {
        log_audit('delete_staff', 'staff', $_POST['id']);
        flash('success', 'Staff account deleted.');
    } else {
        flash('error', 'Could not delete: this staff member has linked records (appointments, payments, etc.). Disable the account instead.');
    }
    redirect('staff/index.php');
}

$search = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$staffFilters = ['order' => 'created_at.asc'];
if ($search !== '') { $staffFilters['or'] = "(full_name.ilike.*{$search}*,username.ilike.*{$search}*,email.ilike.*{$search}*,staff_code.ilike.*{$search}*)"; }
if ($roleFilter !== '') { $staffFilters['role'] = 'eq.' . $roleFilter; }
$staffList = Supabase::select('staff', $staffFilters);

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">👤 Manage Staff <a class="btn" href="<?= base_url('staff/register.php') ?>">+ Register new staff</a></div>

<form method="get" class="card" style="margin-bottom:16px;display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
  <div style="flex:2;min-width:180px;"><label>Search</label><input type="text" name="q" placeholder="Name, username, email, staff ID…" value="<?= h($search) ?>"></div>
  <div style="flex:1;min-width:150px;">
    <label>Role</label>
    <select name="role">
      <option value="">All roles</option>
      <?php foreach (STAFF_ROLES as $r => $rl): ?>
        <option value="<?= h($r) ?>" <?= $roleFilter===$r?'selected':'' ?>><?= h($rl) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn outline">Filter</button>
</form>

<table class="data">
  <tr><th>Staff ID</th><th>Name</th><th>Username</th><th>Role</th><th>Department</th><th>Phone</th><th>Email</th><th>Status</th><th>Date Joined</th><th></th></tr>
  <?php if (!count($staffList)): ?><tr><td colspan="10" style="color:var(--slate);">No staff found.</td></tr><?php endif; ?>
  <?php foreach ($staffList as $s): ?>
    <tr>
      <td><?= h($s['staff_code']) ?: '—' ?></td>
      <td><a href="<?= base_url('staff/view.php?id=' . $s['id']) ?>"><?= h($s['full_name']) ?></a></td>
      <td><?= h($s['username']) ?></td>
      <td><span class="badge <?= is_manager_role($s['role']) ? 'amber' : 'teal' ?>"><?= h(role_label($s['role'])) ?></span></td>
      <td><?= h($s['department']) ?: '—' ?></td>
      <td><?= h($s['phone']) ?: '—' ?></td>
      <td><?= h($s['email']) ?: '—' ?></td>
      <td><?= $s['active'] ? '<span class="badge green">Active</span>' : '<span class="badge red">Disabled</span>' ?></td>
      <td><?= !empty($s['date_joined']) ? h(date('d M Y', strtotime($s['date_joined']))) : '—' ?></td>
      <?php if (!can_manage_staff_member($s['role'])): ?>
      <td style="color:var(--slate);font-size:12.5px;">Superior only</td>
      <?php else: ?>
      <td style="white-space:nowrap;">
        <a class="btn small outline" href="<?= base_url('staff/edit.php?id=' . $s['id']) ?>">Edit</a>
        <form method="post" style="display:inline;">
          <input type="hidden" name="form" value="toggle">
          <input type="hidden" name="id" value="<?= h($s['id']) ?>">
          <button class="btn small outline" onclick="return confirm('<?= $s['active'] ? 'Disable' : 'Enable' ?> this account?')"><?= $s['active'] ? 'Disable' : 'Enable' ?></button>
        </form>
        <button class="btn small outline" onclick="document.getElementById('reset-<?= h($s['id']) ?>').style.display='table-row'">Role / password</button>
        <form method="post" style="display:inline;" onsubmit="return confirm('Permanently delete this staff account? This cannot be undone.')">
          <input type="hidden" name="form" value="delete">
          <input type="hidden" name="id" value="<?= h($s['id']) ?>">
          <button class="btn small danger">Delete</button>
        </form>
      </td>
      <?php endif; ?>
    </tr>
    <tr id="reset-<?= h($s['id']) ?>" style="display:none;">
      <td colspan="10">
        <div style="display:flex;gap:28px;flex-wrap:wrap;">
        <?php if ($s['id'] !== current_staff()['id']): ?>
        <form method="post" style="display:flex;gap:8px;align-items:end;">
          <input type="hidden" name="form" value="change_role">
          <input type="hidden" name="id" value="<?= h($s['id']) ?>">
          <div><label>Role for <?= h($s['username']) ?></label>
            <select name="role">
              <?php foreach (assignable_roles() as $rk => $rl): ?><option value="<?= h($rk) ?>" <?= $s['role']===$rk?'selected':'' ?>><?= h($rl) ?></option><?php endforeach; ?>
            </select></div>
          <button class="btn small">Save role</button>
        </form>
        <?php endif; ?>
        <form method="post" style="display:flex;gap:8px;align-items:end;">
          <input type="hidden" name="form" value="reset_password">
          <input type="hidden" name="id" value="<?= h($s['id']) ?>">
          <div><label>New password for <?= h($s['username']) ?></label><input type="text" name="new_password" required></div>
          <button class="btn small">Save new password</button>
        </form>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<div class="section-title" style="margin-top:30px;">🔐 What each role can do</div>
<table class="data">
  <tr><th>Area</th><th>Superior</th><th>Manager</th><th>Doctor</th><th>Nurse</th><th>Cashier</th></tr>
  <?php
  $Y = '✅'; $N = '—'; $V = 'View';
  $matrix = [
    ['Create / edit / delete Managers & Superiors', $Y, $N, $N, $N, $N],
    ['Create / edit Doctors, Nurses, Cashiers',     $Y, $Y, $N, $N, $N],
    ['Settings, Backup, Audit logs, Expenses',      $Y, $Y, $N, $N, $N],
    ['Prices, pharmacy items, full sales report',      $Y, $Y, $N, $N, 'Own sales'],
    ['Billing, POS, receipts, cashier shifts',      $Y, $Y, $N, $N, $Y],
    ['Book / confirm / cancel appointments',        $Y, $Y, $V, $V, $Y],
    ['Diagnosis, dental chart, prescriptions, braces', $Y, $Y, $Y, $V, $V],
    ['Vital signs & patient preparation',           $Y, $Y, $N, $Y, $N],
    ['Patients, lab orders, daily activities report', $Y, $Y, $Y, $Y, $Y],
  ];
  foreach ($matrix as $row): ?>
    <tr><td><?= h(array_shift($row)) ?></td><?php foreach ($row as $c): ?><td><?= h($c) ?></td><?php endforeach; ?></tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
