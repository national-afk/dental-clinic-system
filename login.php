<?php
require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['staff_id'])) {
    redirect('index.php');
}

$error = !empty($_GET['expired']) ? 'You were signed out after a period of inactivity. Please sign in again.' : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $loginAs = $_POST['login_as'] ?? 'manager'; // which tab was selected

    $user = Supabase::selectOne('staff', [
        'username' => 'eq.' . $username,
        'active' => 'eq.true',
    ]);

    $roleMatches = false;
    if ($user) {
        $role = strtolower($user['role']);
        $roleMatches = $loginAs === 'manager'
            ? is_manager_role($role)
            : !is_manager_role($role); // "Staff" tab covers Cashier / Nurse / Doctor
    }

    if ($user && password_verify($password, $user['password_hash']) && $roleMatches) {
        $_SESSION['staff_id'] = $user['id'];
        $_SESSION['staff_name'] = $user['full_name'];
        $_SESSION['staff_role'] = $user['role'];
        log_audit('login', 'staff', $user['id'], 'Logged in as ' . $user['role']);
        redirect('index.php');
    } elseif ($user && password_verify($password, $user['password_hash']) && !$roleMatches) {
        $error = $loginAs === 'manager'
            ? 'This account is not a Manager/Superior account. Try the Staff tab.'
            : 'This account is a Manager/Superior account. Try the Manager tab.';
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in · <?= h(clinic_settings()['clinic_name']) ?></title>
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>
<div class="login-shell">
  <div class="login-card">
    <h2 style="display:flex;align-items:center;gap:10px;">
      <?php if (!empty(clinic_settings()['logo_url'])): ?>
        <img src="<?= h(clinic_settings()['logo_url']) ?>" alt="Logo" style="height:32px;width:32px;object-fit:contain;">
      <?php else: ?>🦷<?php endif; ?>
      <?= h(clinic_settings()['clinic_name']) ?>
    </h2>
    <p style="color:var(--slate);margin-top:0;">Sign in to the clinic dashboard</p>
    <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
    <?php $selected = $_POST['login_as'] ?? 'manager'; ?>
    <div style="display:flex;border:1px solid var(--line);border-radius:8px;overflow:hidden;margin-bottom:18px;">
      <button type="button" id="tab-manager" onclick="selectTab('manager')" style="flex:1;padding:10px;border:none;cursor:pointer;font-weight:600;font-size:14px;background:<?= $selected==='manager'?'var(--teal-deep)':'var(--white)' ?>;color:<?= $selected==='manager'?'#fff':'var(--ink)' ?>;">👔 Manager / Superior</button>
      <button type="button" id="tab-cashier" onclick="selectTab('cashier')" style="flex:1;padding:10px;border:none;cursor:pointer;font-weight:600;font-size:14px;background:<?= $selected==='cashier'?'var(--teal-deep)':'var(--white)' ?>;color:<?= $selected==='cashier'?'#fff':'var(--ink)' ?>;">🧑‍⚕️ Staff</button>
    </div>
    <form method="post" id="login-form">
      <input type="hidden" name="login_as" id="login_as" value="<?= h($selected) ?>">
      <label>Username</label>
      <input type="text" name="username" required autofocus>
      <div style="height:12px"></div>
      <label>Password</label>
      <input type="password" name="password" required>
      <div style="height:18px"></div>
      <button class="btn" style="width:100%;justify-content:center;">Sign in</button>
    </form>
    <script>
      function selectTab(tab) {
        document.getElementById('login_as').value = tab;
        document.getElementById('tab-manager').style.background = tab === 'manager' ? 'var(--teal-deep)' : 'var(--white)';
        document.getElementById('tab-manager').style.color = tab === 'manager' ? '#fff' : 'var(--ink)';
        document.getElementById('tab-cashier').style.background = tab === 'cashier' ? 'var(--teal-deep)' : 'var(--white)';
        document.getElementById('tab-cashier').style.color = tab === 'cashier' ? '#fff' : 'var(--ink)';
      }
    </script>
    <p style="color:var(--slate);font-size:12.5px;margin-top:18px;text-align:center;">
      🦷 Smile Care Dental Clinic — staff access only
    </p>
  </div>
</div>
</body>
</html>
