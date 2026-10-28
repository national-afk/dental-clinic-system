<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$page_title = 'Staff profile';

$id = $_GET['id'] ?? current_staff()['id'];
$st = Supabase::selectOne('staff', ['id' => 'eq.' . $id]);
$isSelf = $st && $st['id'] === current_staff()['id'];
if (!$st || (!$isSelf && !can_manage_prices())) { flash('error', 'Staff member not found.'); redirect('index.php'); }

$canEdit = $isSelf || can_manage_staff_member($st['role']);
$canPay = can_manage_prices() && can_manage_staff_member($st['role']);
$profile = $canPay ? Supabase::selectOne('staff_salary', ['staff_id' => 'eq.' . $id]) : null;
$slips = ($canPay || $isSelf)
    ? Supabase::select('payslips', ['staff_id' => 'eq.' . $id, 'order' => 'period_month.desc', 'limit' => 6] + ($canPay ? [] : ['status' => 'eq.paid']))
    : [];
$att = attendance_summary($id, date('Y-m-01'));

require __DIR__ . '/../includes/header.php';
$initials = strtoupper(substr($st['full_name'], 0, 1));
?>
<p><a href="<?= base_url('staff/index.php') ?>">← Manage Staff</a></p>

<div class="card" style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;margin-bottom:18px;">
  <?php if (!empty($st['photo_url'])): ?>
    <img src="<?= h($st['photo_url']) ?>" alt="" style="width:88px;height:88px;border-radius:50%;object-fit:cover;border:2px solid var(--line);">
  <?php else: ?>
    <div style="width:88px;height:88px;border-radius:50%;background:var(--teal-pale);color:var(--teal-deep);display:flex;align-items:center;justify-content:center;font-size:34px;font-weight:800;"><?= h($initials) ?></div>
  <?php endif; ?>
  <div style="flex:1;min-width:200px;">
    <h2 style="margin:0;"><?= h($st['full_name']) ?></h2>
    <div style="margin:6px 0;">
      <span class="badge <?= is_manager_role($st['role']) ? 'amber' : 'teal' ?>"><?= h(role_label($st['role'])) ?></span>
      <?= $st['active'] ? '<span class="badge green">Account active</span>' : '<span class="badge red">Account disabled</span>' ?>
      <span class="badge gray"><?= h(ucfirst(str_replace('_', ' ', (string)($st['employment_status'] ?? 'active')))) ?></span>
    </div>
    <div style="color:var(--slate);">Staff ID <?= h($st['staff_code']) ?: '—' ?> · @<?= h($st['username']) ?></div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <?php if ($canEdit): ?><a class="btn" href="<?= base_url('staff/edit.php?id=' . $st['id']) ?>">✏️ Edit profile</a><?php endif; ?>
    <?php if ($canPay): ?><a class="btn outline" href="<?= base_url('payroll/salary.php?staff=' . $st['id']) ?>">💼 Salary</a><?php endif; ?>
  </div>
</div>

<div class="form-grid">
  <div class="card">
    <h3>Personal information</h3>
    <table class="info-list">
      <tr><td>Full name</td><td><?= h($st['full_name']) ?></td></tr>
      <tr><td>Gender</td><td><?= h($st['gender']) ?: '—' ?></td></tr>
      <tr><td>Date of birth</td><td><?= !empty($st['date_of_birth']) ? h(date('d M Y', strtotime($st['date_of_birth']))) . ' <span style="color:var(--slate);">(' . h(age_from($st['date_of_birth'])) . ')</span>' : '—' ?></td></tr>
      <tr><td>Marital status</td><td><?= h($st['marital_status']) ?: '—' ?></td></tr>
      <tr><td>Nationality</td><td><?= h($st['nationality']) ?: '—' ?></td></tr>
      <tr><td>Blood group</td><td><?= !empty($st['blood_group']) ? '<span class="badge red">' . h($st['blood_group']) . '</span>' : '—' ?></td></tr>
      <tr><td>Phone</td><td><?= !empty($st['phone']) ? '<a href="tel:' . h($st['phone']) . '">' . h($st['phone']) . '</a>' : '—' ?></td></tr>
      <tr><td>Email</td><td><?= !empty($st['email']) ? '<a href="mailto:' . h($st['email']) . '">' . h($st['email']) . '</a>' : '—' ?></td></tr>
      <tr><td>Address</td><td><?= h($st['address']) ?: '—' ?></td></tr>
      <?php if ($canEdit): ?><tr><td>National ID / passport</td><td><?= h($st['national_id']) ?: '—' ?></td></tr><?php endif; ?>
      <tr><td>Education</td><td><?= h($st['qualification']) ?: '—' ?></td></tr>
    </table>
  </div>
  <div class="card">
    <h3>Employment</h3>
    <p>Department: <?= h($st['department']) ?: '—' ?><br>
       Date joined: <?= !empty($st['date_joined']) ? h(date('d M Y', strtotime($st['date_joined']))) : '—' ?><?= service_length($st['date_joined'] ?? null) !== '' ? ' <span style="color:var(--slate);">(' . h(service_length($st['date_joined'])) . ' of service)</span>' : '' ?><br>
       This month: <?= (int)$att['days'] ?> day(s) present, <?= h($att['hours']) ?> h</p>
  </div>
</div>
<?php if ($canEdit): ?>
<div class="card" style="margin-top:16px;">
  <h3>Emergency contact</h3>
  <?php if (!empty($st['emergency_contact_name']) || !empty($st['emergency_contact_phone'])): ?>
    <p style="margin:0;"><strong><?= h($st['emergency_contact_name']) ?: '—' ?></strong>
      <?= !empty($st['emergency_contact_relation']) ? '(' . h($st['emergency_contact_relation']) . ')' : '' ?><br>
      <?= !empty($st['emergency_contact_phone']) ? '<a href="tel:' . h($st['emergency_contact_phone']) . '">' . h($st['emergency_contact_phone']) . '</a>' : '—' ?></p>
  <?php else: ?>
    <p style="margin:0;color:var(--slate);">Not added yet.<?= $isSelf ? ' <a href="' . base_url('staff/edit.php?id=' . $st['id']) . '">Add it</a>' : '' ?></p>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php if (in_array($st['role'], ['doctor', 'nurse'], true)): ?>
<div class="card" style="margin-top:16px;">
  <h3>Professional details</h3>
  <p style="margin:0;">
    <?php if ($st['role'] === 'doctor'): ?>Specialty: <strong><?= h($st['specialization']) ?: '—' ?></strong><br><?php endif; ?>
    License no.: <strong><?= h($st['license_number']) ?: '—' ?></strong><br>
    License expiry: <?php
      if (!empty($st['license_expiry'])) {
          $exp = strtotime($st['license_expiry']); $days = (int)floor(($exp - time()) / 86400);
          echo h(date('d M Y', $exp)) . ' ';
          echo $days < 0 ? '<span class="badge red">Expired</span>' : ($days <= 60 ? '<span class="badge amber">Expires in ' . $days . ' days</span>' : '<span class="badge green">Valid</span>');
      } else { echo '—'; } ?>
  </p>
</div>
<?php endif; ?>

<?php if ($canPay): ?>
<div class="section-title" style="margin-top:24px;">💼 Salary</div>
<div class="card">
  <?php if ($profile): ?>
    <p style="margin:0;">Basic <strong><?= money($profile['basic_salary']) ?></strong>
      · Transport <?= money($profile['transport_allowance']) ?> · Other allowance <?= money($profile['other_allowance']) ?>
      · Standard deduction <?= money($profile['standard_deduction']) ?>
      · Paid by <?= h(payment_method_label((string)$profile['payment_method'])) ?><?= !empty($profile['payment_account']) ? ' (' . h($profile['payment_account']) . ')' : '' ?></p>
  <?php else: ?>
    <p style="margin:0;color:var(--slate);">No salary set yet. <a href="<?= base_url('payroll/salary.php?staff=' . $st['id']) ?>">Set salary</a></p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (count($slips)): ?>
<div class="section-title" style="margin-top:24px;">🧾 Recent payslips</div>
<table class="data">
  <tr><th>Month</th><th>Payslip #</th><th>Net pay</th><th>Status</th><th></th></tr>
  <?php foreach ($slips as $p): ?>
    <tr>
      <td><?= h(date('F Y', strtotime($p['period_month']))) ?></td><td><?= h($p['payslip_no']) ?></td><td><?= money($p['net_pay']) ?></td>
      <td><?= $p['status'] === 'paid' ? '<span class="badge green">Paid</span>' : '<span class="badge amber">Draft</span>' ?></td>
      <td><a class="btn small outline" href="<?= base_url('payroll/payslip.php?id=' . $p['id']) ?>">Open</a></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
