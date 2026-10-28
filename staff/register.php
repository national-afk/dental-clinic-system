<?php
require_once __DIR__ . '/../config/config.php';
require_manager();
$page_title = 'Staff Registration';

$roles = assignable_roles();
$info = role_info();
$old = $_POST;              // keeps the form filled in after an error
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $extra = $info[$role]['extra'] ?? [];

    if (!isset($roles[$role])) $errors[] = 'Choose a role you are allowed to assign.';
    if ($name === '') $errors[] = 'Full name is required.';
    if (!preg_match('/^[A-Za-z0-9._@-]{3,40}$/', $username)) $errors[] = 'Username must be 3–40 characters (letters, numbers, . _ - @).';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email address is not valid.';
    if (strlen($pass) < password_min_length()) $errors[] = 'Password must be at least ' . password_min_length() . ' characters.';
    if ($pass !== ($_POST['password_confirm'] ?? '')) $errors[] = 'The two passwords do not match.';
    if (in_array('specialization', $extra, true) && trim($_POST['specialization'] ?? '') === '') $errors[] = 'Specialty is required for a ' . role_label($role) . '.';
    if (in_array('license_number', $extra, true) && trim($_POST['license_number'] ?? '') === '') $errors[] = 'License number is required for a ' . role_label($role) . '.';
    if (!$errors && Supabase::selectOne('staff', ['username' => 'eq.' . $username])) $errors[] = 'That username is already taken.';

    $photoUrl = null;
    if (!$errors && !empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) { $errors[] = 'Photo must be png, jpg, gif or webp.'; }
        else {
            $dir = __DIR__ . '/../uploads/staff/'; if (!is_dir($dir)) mkdir($dir, 0755, true);
            $safe = 'staff_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $safe);
            $photoUrl = base_url('uploads/staff/' . $safe);
        }
    }

    if (!$errors) {
        $created = Supabase::insert('staff', [
            'staff_code' => generate_staff_code(),
            'full_name' => $name, 'username' => $username, 'email' => $email,
            'password_hash' => password_hash($pass, PASSWORD_BCRYPT),
            'role' => $role, 'phone' => trim($_POST['phone'] ?? ''), 'gender' => $_POST['gender'] ?? '',
            'date_of_birth' => ($_POST['date_of_birth'] ?? '') ?: null, 'address' => trim($_POST['address'] ?? ''),
            'photo_url' => $photoUrl, 'department' => trim($_POST['department'] ?? ''),
            'employment_status' => in_array($_POST['employment_status'] ?? '', ['active', 'on_leave'], true) ? $_POST['employment_status'] : 'active',
            'date_joined' => ($_POST['date_joined'] ?? '') ?: date('Y-m-d'),
            'specialization' => in_array('specialization', $extra, true) ? trim($_POST['specialization']) : null,
            'license_number' => in_array('license_number', $extra, true) ? trim($_POST['license_number']) : null,
            'license_expiry' => in_array('license_expiry', $extra, true) ? (($_POST['license_expiry'] ?? '') ?: null) : null,
            'active' => true,
        ] + staff_personal_fields($_POST));
        if (!$created) {
            $errors[] = 'The account could not be saved. Run add_staff_role_fields.sql (and the earlier staff migrations) in Supabase, then try again.';
        } else {
            $newId = $created[0]['id'];
            $basic = max(0, (float)($_POST['basic_salary'] ?? 0));
            if ($basic > 0) {
                Supabase::insert('staff_salary', [
                    'staff_id' => $newId, 'basic_salary' => $basic,
                    'payment_method' => isset(salary_pay_methods()[$_POST['salary_method'] ?? '']) ? $_POST['salary_method'] : 'cash',
                ]);
            }
            log_audit('register_staff', 'staff', $newId, $username . ' (' . $role . ')');
            flash('success', $name . ' registered as ' . role_label($role) . '. Staff ID ' . $created[0]['staff_code'] . '.');
            redirect('staff/view.php?id=' . $newId);
        }
    }
    foreach ($errors as $e) { flash('error', $e); break; }
}

$selected = $old['role'] ?? array_key_first(array_diff_key($roles, ['superior' => 1, 'manager' => 1])) ?? array_key_first($roles);
$val = fn($k, $d = '') => h($old[$k] ?? $d);

require __DIR__ . '/../includes/header.php';
?>
<p><a href="<?= base_url('staff/index.php') ?>">← Manage Staff</a></p>
<div class="section-title">🧑‍⚕️ Staff Registration</div>

<?php if (count($errors) > 1): ?>
  <div class="alert error"><ul style="margin:0;padding-left:18px;"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" id="reg-form" novalidate>

  <h3 style="margin:6px 0 10px;">Choose the role</h3>
  <div class="role-cards">
    <?php foreach ($roles as $rk => $rl): ?>
      <label class="role-card <?= $selected === $rk ? 'selected' : '' ?>">
        <input type="radio" name="role" value="<?= h($rk) ?>" <?= $selected === $rk ? 'checked' : '' ?>>
        <span class="rc-title"><?= h($rl) ?></span>
        <span class="rc-sub"><?= h($info[$rk]['summary'] ?? '') ?></span>
      </label>
    <?php endforeach; ?>
  </div>

  <div class="card role-panel" id="role-panel" style="margin-top:14px;">
    <div class="form-grid">
      <div><strong>Can</strong><ul class="can" id="rp-can"></ul></div>
      <div><strong>Cannot</strong><ul class="cannot" id="rp-cannot"></ul></div>
    </div>
  </div>

  <h3 style="margin:24px 0 10px;">Personal details</h3>
  <div class="card form-grid">
    <div><label>Full name *</label><input type="text" name="full_name" value="<?= $val('full_name') ?>" required></div>
    <div><label>Phone</label><input type="text" name="phone" value="<?= $val('phone') ?>"></div>
    <div><label>Email</label><input type="email" name="email" value="<?= $val('email') ?>"></div>
    <div><label>Gender</label>
      <select name="gender"><option value="">Select…</option><?php foreach (['Male', 'Female'] as $g): ?><option <?= ($old['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select></div>
    <div><label>Date of birth</label><input type="date" name="date_of_birth" value="<?= $val('date_of_birth') ?>"></div>
    <div><label>Profile photo</label><input type="file" name="photo" accept="image/*"></div>
    <div class="full"><label>Address</label><input type="text" name="address" value="<?= $val('address') ?>"></div>
    <div><label>National ID / passport no.</label><input type="text" name="national_id" value="<?= $val('national_id') ?>"></div>
    <div><label>Nationality</label><input type="text" name="nationality" value="<?= $val('nationality') ?>" placeholder="e.g. Somali"></div>
    <div><label>Marital status</label>
      <select name="marital_status"><option value="">Select…</option><?php foreach (MARITAL_STATUSES as $m): ?><option <?= ($old['marital_status'] ?? '') === $m ? 'selected' : '' ?>><?= $m ?></option><?php endforeach; ?></select></div>
    <div><label>Blood group</label>
      <select name="blood_group"><option value="">Select…</option><?php foreach (BLOOD_GROUPS as $b): ?><option <?= ($old['blood_group'] ?? '') === $b ? 'selected' : '' ?>><?= $b ?></option><?php endforeach; ?></select></div>
    <div class="full"><label>Education / qualification</label><input type="text" name="qualification" value="<?= $val('qualification') ?>" placeholder="e.g. Bachelor of Dental Surgery, Nursing diploma"></div>
    <div class="full"><strong>Emergency contact</strong></div>
    <div><label>Contact name</label><input type="text" name="emergency_contact_name" value="<?= $val('emergency_contact_name') ?>"></div>
    <div><label>Relationship</label><input type="text" name="emergency_contact_relation" value="<?= $val('emergency_contact_relation') ?>"></div>
    <div class="full"><label>Contact phone</label><input type="text" name="emergency_contact_phone" value="<?= $val('emergency_contact_phone') ?>"></div>
  </div>

  <div id="pro-section" style="display:none;">
    <h3 style="margin:24px 0 10px;">Professional details</h3>
    <div class="card form-grid">
      <div data-extra="specialization"><label>Specialty *</label><input type="text" name="specialization" value="<?= $val('specialization') ?>" placeholder="e.g. Orthodontist, General dentist, Endodontist"></div>
      <div data-extra="license_number"><label>License number *</label><input type="text" name="license_number" value="<?= $val('license_number') ?>"></div>
      <div data-extra="license_expiry"><label>License expiry</label><input type="date" name="license_expiry" value="<?= $val('license_expiry') ?>"></div>
    </div>
  </div>

  <h3 style="margin:24px 0 10px;">Employment &amp; salary</h3>
  <div class="card form-grid">
    <div><label>Department</label><input type="text" name="department" value="<?= $val('department') ?>" placeholder="e.g. Clinical, Front desk"></div>
    <div><label>Employment status</label>
      <select name="employment_status"><option value="active">Active</option><option value="on_leave" <?= ($old['employment_status'] ?? '') === 'on_leave' ? 'selected' : '' ?>>On leave</option></select></div>
    <div><label>Date joined</label><input type="date" name="date_joined" value="<?= $val('date_joined', date('Y-m-d')) ?>"></div>
    <div><label>Basic monthly salary (optional)</label><input type="number" step="0.01" min="0" name="basic_salary" value="<?= $val('basic_salary') ?>"></div>
    <div><label>Salary paid by</label>
      <select name="salary_method"><?php foreach (salary_pay_methods() as $k => $l): ?><option value="<?= h($k) ?>" <?= ($old['salary_method'] ?? '') === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
  </div>

  <h3 style="margin:24px 0 10px;">Sign-in account</h3>
  <div class="card form-grid">
    <div><label>Username *</label><input type="text" name="username" value="<?= $val('username') ?>" autocomplete="off" required></div>
    <div></div>
    <div><label>Password * (min <?= (int)password_min_length() ?> characters)</label><input type="password" name="password" autocomplete="new-password" required></div>
    <div><label>Confirm password *</label><input type="password" name="password_confirm" autocomplete="new-password" required></div>
  </div>

  <div style="margin-top:18px;">
    <button class="btn">Register staff</button>
    <a class="btn outline" href="<?= base_url('staff/index.php') ?>">Cancel</a>
  </div>
</form>

<script>
var ROLE_INFO = <?= json_encode($info, JSON_UNESCAPED_UNICODE) ?>;
function applyRole() {
  var r = (document.querySelector('input[name=role]:checked') || {}).value, i = ROLE_INFO[r] || {can: [], cannot: [], extra: []};
  document.querySelectorAll('.role-card').forEach(function (c) { c.classList.toggle('selected', c.querySelector('input').checked); });
  function fill(id, items) { document.getElementById(id).innerHTML = items.length ? items.map(function (t) { var li = document.createElement('li'); li.textContent = t; return li.outerHTML; }).join('') : '<li style="list-style:none;color:var(--slate);">Nothing restricted.</li>'; }
  fill('rp-can', i.can); fill('rp-cannot', i.cannot);
  document.getElementById('pro-section').style.display = i.extra.length ? 'block' : 'none';
  document.querySelectorAll('[data-extra]').forEach(function (d) { d.style.display = i.extra.indexOf(d.dataset.extra) !== -1 ? '' : 'none'; });
}
document.querySelectorAll('input[name=role]').forEach(function (r) { r.addEventListener('change', applyRole); });
applyRole();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
