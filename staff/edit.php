<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$page_title = 'Edit staff';

$id = $_GET['id'] ?? current_staff()['id'];
$st = Supabase::selectOne('staff', ['id' => 'eq.' . $id]);
$isSelf = $st && $st['id'] === current_staff()['id'];
if (!$st || !($isSelf || (can_manage_prices() && can_manage_staff_member($st['role'])))) {
    flash('error', 'You are not allowed to edit this profile.'); redirect('index.php');
}
$manager = can_manage_prices();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    if ($name === '' || $username === '') { flash('error', 'Full name and username are required.'); redirect('staff/edit.php?id=' . $id); }

    $row = [
        'full_name' => $name, 'username' => $username, 'email' => trim($_POST['email'] ?? ''), 'phone' => trim($_POST['phone'] ?? ''),
        'gender' => $_POST['gender'] ?? '', 'date_of_birth' => ($_POST['date_of_birth'] ?? '') ?: null, 'address' => trim($_POST['address'] ?? ''),
    ] + staff_personal_fields($_POST);
    if ($manager) {   // HR fields are for Manager / Superior only
        $row['department'] = trim($_POST['department'] ?? '');
        $row['employment_status'] = in_array($_POST['employment_status'] ?? '', ['active', 'on_leave', 'terminated'], true) ? $_POST['employment_status'] : 'active';
        $row['date_joined'] = ($_POST['date_joined'] ?? '') ?: null;
        if (in_array($st['role'], ['doctor', 'nurse'], true)) {
            if ($st['role'] === 'doctor') $row['specialization'] = trim($_POST['specialization'] ?? '');
            $row['license_number'] = trim($_POST['license_number'] ?? '');
            $row['license_expiry'] = ($_POST['license_expiry'] ?? '') ?: null;
        }
        if ($row['employment_status'] === 'terminated' && !$isSelf) { $row['active'] = false; }   // terminated staff cannot sign in
    }
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            $dir = __DIR__ . '/../uploads/staff/'; if (!is_dir($dir)) mkdir($dir, 0755, true);
            $safe = 'staff_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $safe);
            $row['photo_url'] = base_url('uploads/staff/' . $safe);
        } else { flash('error', 'Photo must be png, jpg, gif or webp.'); redirect('staff/edit.php?id=' . $id); }
    }

    if (Supabase::update('staff', ['id' => 'eq.' . $id], $row) === false) {
        flash('error', 'Could not save. That username may already be used by someone else.');
        redirect('staff/edit.php?id=' . $id);
    }
    if ($isSelf) { $_SESSION['staff_name'] = $name; }
    log_audit('edit_staff', 'staff', $id, $username);
    flash('success', 'Profile saved.');
    redirect('staff/view.php?id=' . $id);
}

require __DIR__ . '/../includes/header.php';
$v = fn($k) => h($st[$k] ?? '');
?>
<p><a href="<?= base_url('staff/view.php?id=' . $id) ?>">← Profile</a></p>
<div class="section-title">✏️ Edit staff — <?= h($st['full_name']) ?></div>

<form method="post" class="card form-grid" enctype="multipart/form-data">
  <div><label>Full name *</label><input type="text" name="full_name" value="<?= $v('full_name') ?>" required></div>
  <div><label>Username *</label><input type="text" name="username" value="<?= $v('username') ?>" required></div>
  <div><label>Email</label><input type="email" name="email" value="<?= $v('email') ?>"></div>
  <div><label>Phone</label><input type="text" name="phone" value="<?= $v('phone') ?>"></div>
  <div><label>Gender</label>
    <select name="gender"><option value="">Select…</option>
      <?php foreach (['Male', 'Female'] as $g): ?><option <?= ($st['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select></div>
  <div><label>Date of birth</label><input type="date" name="date_of_birth" value="<?= $v('date_of_birth') ?>"></div>
  <div class="full"><label>Address</label><input type="text" name="address" value="<?= $v('address') ?>"></div>
  <div><label>National ID / passport no.</label><input type="text" name="national_id" value="<?= $v('national_id') ?>"></div>
  <div><label>Nationality</label><input type="text" name="nationality" value="<?= $v('nationality') ?>" placeholder="e.g. Somali"></div>
  <div><label>Marital status</label>
    <select name="marital_status"><option value="">Select…</option><?php foreach (MARITAL_STATUSES as $m): ?><option <?= ($st['marital_status'] ?? '') === $m ? 'selected' : '' ?>><?= $m ?></option><?php endforeach; ?></select></div>
  <div><label>Blood group</label>
    <select name="blood_group"><option value="">Select…</option><?php foreach (BLOOD_GROUPS as $b): ?><option <?= ($st['blood_group'] ?? '') === $b ? 'selected' : '' ?>><?= $b ?></option><?php endforeach; ?></select></div>
  <div class="full"><label>Education / qualification</label><input type="text" name="qualification" value="<?= $v('qualification') ?>" placeholder="e.g. Bachelor of Dental Surgery, Nursing diploma"></div>
  <div class="full"><h3 style="margin:8px 0 0;">Emergency contact</h3></div>
  <div><label>Contact name</label><input type="text" name="emergency_contact_name" value="<?= $v('emergency_contact_name') ?>"></div>
  <div><label>Relationship</label><input type="text" name="emergency_contact_relation" value="<?= $v('emergency_contact_relation') ?>" placeholder="e.g. Spouse, Brother"></div>
  <div class="full"><label>Contact phone</label><input type="text" name="emergency_contact_phone" value="<?= $v('emergency_contact_phone') ?>"></div>
  <div class="full">
    <label>Profile photo</label>
    <?php if (!empty($st['photo_url'])): ?><div style="margin-bottom:8px;"><img src="<?= h($st['photo_url']) ?>" alt="" style="height:70px;border-radius:50%;"></div><?php endif; ?>
    <input type="file" name="photo" accept="image/*">
  </div>
  <?php if ($manager): ?>
  <div><label>Department</label><input type="text" name="department" value="<?= $v('department') ?>" placeholder="e.g. Clinical, Front desk, Finance"></div>
  <div><label>Employment status</label>
    <select name="employment_status">
      <?php foreach (['active' => 'Active', 'on_leave' => 'On leave', 'terminated' => 'Terminated (account is disabled)'] as $k => $l): ?>
        <option value="<?= $k ?>" <?= ($st['employment_status'] ?? 'active') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div><label>Date joined</label><input type="date" name="date_joined" value="<?= $v('date_joined') ?>"></div>
  <?php if ($st['role'] === 'doctor'): ?><div><label>Specialty</label><input type="text" name="specialization" value="<?= $v('specialization') ?>"></div><?php endif; ?>
  <?php if (in_array($st['role'], ['doctor', 'nurse'], true)): ?>
  <div><label>License number</label><input type="text" name="license_number" value="<?= $v('license_number') ?>"></div>
  <div><label>License expiry</label><input type="date" name="license_expiry" value="<?= $v('license_expiry') ?>"></div>
  <?php endif; ?>
  <?php endif; ?>
  <div class="full"><button class="btn">Save profile</button> <a class="btn outline" href="<?= base_url('staff/view.php?id=' . $id) ?>">Cancel</a></div>
</form>
<p style="color:var(--slate);font-size:12.5px;">Role and password are changed from Manage Staff (“Role / password”).</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
