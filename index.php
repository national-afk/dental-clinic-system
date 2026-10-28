<?php
require_once __DIR__ . '/config/config.php';
$page_title = 'Dashboard';

$today = date('Y-m-d');
$monthStart = date('Y-m-01');

// ---- Doctor Dashboard: a completely different, focused view ----
if (is_doctor() && !can_manage_prices()) {
    $doctorId = current_staff()['id'];

    $todaysApptsDoctor = Supabase::select('appointments', [
        'appointment_date' => 'eq.' . $today,
        'doctor_id' => 'eq.' . $doctorId,
        'select' => '*,patients(full_name,phone),services(name)',
        'order' => 'appointment_time.asc',
    ]);
    $completedToday = array_filter($todaysApptsDoctor, fn($a) => $a['status'] === 'completed');
    $pendingToday = array_filter($todaysApptsDoctor, fn($a) => in_array($a['status'], ['booked', 'confirmed'], true));

    require __DIR__ . '/includes/header.php';
    ?>

    <h1>🦷 Doctor Dashboard</h1>
    <p style="color:var(--slate);margin-top:0;">Doctor: <strong><?= h(current_staff()['name']) ?></strong></p>

    <div class="stat-strip">
      <div class="stat">
        <div class="num"><?= count($todaysApptsDoctor) ?></div>
        <div class="label">Today's Appointments</div>
      </div>
      <div class="stat">
        <div class="num"><?= count($completedToday) ?></div>
        <div class="label">Completed Treatments</div>
      </div>
      <div class="stat warn">
        <div class="num"><?= count($pendingToday) ?></div>
        <div class="label">Pending Appointments</div>
      </div>
    </div>

    <div class="card" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px;">
      <a class="btn" href="<?= base_url('appointments/index.php') ?>">📅 My Appointments</a>
      <a class="btn outline" href="<?= base_url('patients/index.php') ?>">🧑‍⚕️ Patients</a>
      <a class="btn outline" href="<?= base_url('dental-chart/index.php') ?>">🦷 Dental Chart</a>
      <a class="btn outline" href="<?= base_url('treatments/index.php') ?>">🩺 Treatments</a>
      <a class="btn outline" href="<?= base_url('prescriptions/index.php') ?>">💊 Prescriptions</a>
      <a class="btn outline" href="<?= base_url('logout.php') ?>">🚪 Logout</a>
    </div>

    <div class="section-title">📅 Today's appointments</div>
    <table class="data">
      <tr><th>Time</th><th>Patient</th><th>Service</th><th>Status</th></tr>
      <?php if (!count($todaysApptsDoctor)): ?>
        <tr><td colspan="4" style="color:var(--slate);">No appointments booked for today.</td></tr>
      <?php endif; ?>
      <?php foreach ($todaysApptsDoctor as $a): ?>
        <tr>
          <td><?= h(substr($a['appointment_time'],0,5)) ?></td>
          <td><?= h($a['patients']['full_name'] ?? '—') ?></td>
          <td><?= h($a['services']['name'] ?? '—') ?></td>
          <td><span class="badge <?= $a['status']==='completed'?'green':($a['status']==='cancelled'?'red':'amber') ?>"><?= h(ucfirst($a['status'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </table>

    <?php require __DIR__ . '/includes/footer.php'; exit;
}


// ---- Tirada bukaannada (total patients) ----
$patients = Supabase::select('patients', ['select' => 'id']);
$totalPatients = count($patients);

// ---- Appointments maanta (today's appointments) ----
$todaysAppointments = Supabase::select('appointments', [
    'appointment_date' => 'eq.' . $today,
    'order' => 'appointment_time.asc',
    'select' => '*,patients(full_name,phone),staff(full_name),services(name)',
]);

// ---- Dakhliga maanta / bisha (today's / this month's revenue) ----
$paymentsToday = Supabase::select('payments', [
    'paid_at' => 'gte.' . $today . 'T00:00:00',
]);
$revenueToday = array_sum(array_column($paymentsToday, 'amount'));

$paymentsMonth = Supabase::select('payments', [
    'paid_at' => 'gte.' . $monthStart . 'T00:00:00',
]);
$revenueMonth = array_sum(array_column($paymentsMonth, 'amount'));

// ---- Lacagaha aan la bixin (outstanding balances) ----
$unpaidInvoices = Supabase::select('invoices', [
    'status' => 'in.(unpaid,partial)',
    'select' => '*,patients(full_name)',
    'order' => 'created_at.desc',
]);
$totalOutstanding = array_sum(array_column($unpaidInvoices, 'balance'));

// ---- Treatments socda (ongoing treatments) ----
$ongoingTreatments = Supabase::select('treatments', [
    'status' => 'eq.in_progress',
    'select' => '*,patients(full_name),services(name)',
    'order' => 'treatment_date.desc',
]);

// ---- Alerts: low stock + upcoming (unconfirmed) appointments ----
$lowStockRaw = Supabase::select('inventory_items', ['select' => '*']);
$lowStock = array_filter($lowStockRaw, fn($i) => (float)$i['quantity'] <= (float)$i['low_stock_threshold']);

$upcomingUnconfirmed = Supabase::select('appointments', [
    'appointment_date' => 'eq.' . $today,
    'status' => 'eq.booked',
    'select' => '*,patients(full_name,phone)',
]);

// ---- Braces tracking summary ----
$activeBraces = Supabase::select('braces_cases', ['status' => 'eq.active', 'select' => '*,patients(full_name)']);
$bracesDueToday = []; $bracesOverdue = []; $bracesUpcoming = 0; $bracesThisMonth = 0;
$bracesMonthPrefix = date('Y-m');
foreach ($activeBraces as $bc) {
    $bs = braces_status($bc['next_visit_date']);
    if ($bs['label'] === 'Due Today') $bracesDueToday[] = $bc;
    elseif ($bs['label'] === 'Overdue') $bracesOverdue[] = $bc;
    else $bracesUpcoming++;
    if (strpos($bc['next_visit_date'], $bracesMonthPrefix) === 0) $bracesThisMonth++;
}

require __DIR__ . '/includes/header.php';
?>

<h1>Good day, <?= h(explode(' ', current_staff()['name'])[0]) ?> 👋</h1>
<p style="color:var(--slate);margin-top:0;">Here's what's happening at the clinic today.</p>

<div class="stat-strip">
  <div class="stat">
    <div class="num"><?= $totalPatients ?></div>
    <div class="label">Total patients</div>
  </div>
  <div class="stat">
    <div class="num"><?= count($todaysAppointments) ?></div>
    <div class="label">Appointments today</div>
  </div>
  <div class="stat">
    <div class="num"><?= money($revenueToday) ?></div>
    <div class="label">Revenue today</div>
  </div>
  <div class="stat">
    <div class="num"><?= money($revenueMonth) ?></div>
    <div class="label">Revenue this month</div>
  </div>
  <div class="stat warn">
    <div class="num"><?= money($totalOutstanding) ?></div>
    <div class="label">Outstanding balance</div>
  </div>
  <div class="stat">
    <div class="num"><?= count($ongoingTreatments) ?></div>
    <div class="label">Treatments in progress</div>
  </div>
</div>

<div class="section-title">🦷 Braces</div>
<div class="stat-strip">
  <div class="stat">
    <div class="num"><?= count($activeBraces) ?></div>
    <div class="label">🦷 Active Braces</div>
  </div>
  <div class="stat">
    <div class="num"><?= count($bracesDueToday) ?></div>
    <div class="label">🟢 Due Today</div>
  </div>
  <div class="stat">
    <div class="num"><?= $bracesUpcoming ?></div>
    <div class="label">🟡 Upcoming</div>
  </div>
  <div class="stat warn">
    <div class="num"><?= count($bracesOverdue) ?></div>
    <div class="label">🔴 Overdue</div>
  </div>
  <div class="stat">
    <div class="num"><?= $bracesThisMonth ?></div>
    <div class="label">📅 This Month</div>
  </div>
</div>

<?php if (app_setting('braces_alerts', '1') !== '1') { $bracesOverdue = []; $bracesDueToday = []; } ?>
<?php if (count($lowStock) || count($upcomingUnconfirmed) || count($bracesOverdue) || count($bracesDueToday)): ?>
<div class="section-title">🔔 Alerts &amp; reminders</div>
<div class="card">
  <?php foreach ($upcomingUnconfirmed as $a): ?>
    <div style="padding:6px 0;border-bottom:1px solid var(--line);">
      <span class="badge amber">Unconfirmed</span>
      Appointment today at <?= h(substr($a['appointment_time'],0,5)) ?> for <strong><?= h($a['patients']['full_name'] ?? '—') ?></strong> — not yet confirmed.
    </div>
  <?php endforeach; ?>
  <?php foreach ($lowStock as $i): ?>
    <div style="padding:6px 0;border-bottom:1px solid var(--line);">
      <span class="badge red">Low stock</span>
      <strong><?= h($i['item_name']) ?></strong> is down to <?= h($i['quantity']) ?> <?= h($i['unit']) ?> (reorder threshold: <?= h($i['low_stock_threshold']) ?>).
    </div>
  <?php endforeach; ?>
  <?php foreach ($bracesOverdue as $bc): ?>
    <div style="padding:6px 0;border-bottom:1px solid var(--line);">
      <span class="badge red">🔴 Braces overdue</span>
      <strong><?= h($bc['patients']['full_name'] ?? '—') ?></strong> was due <?= h(date('d M Y', strtotime($bc['next_visit_date']))) ?>.
    </div>
  <?php endforeach; ?>
  <?php foreach ($bracesDueToday as $bc): ?>
    <div style="padding:6px 0;border-bottom:1px solid var(--line);">
      <span class="badge green">🟢 Braces due today</span>
      <strong><?= h($bc['patients']['full_name'] ?? '—') ?></strong> — adjustment due today.
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="section-title">📅 Today's appointments</div>
<table class="data">
  <tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Service</th><th>Status</th></tr>
  <?php if (!count($todaysAppointments)): ?>
    <tr><td colspan="5" style="color:var(--slate);">No appointments booked for today.</td></tr>
  <?php endif; ?>
  <?php foreach ($todaysAppointments as $a): ?>
    <tr>
      <td><?= h(substr($a['appointment_time'],0,5)) ?></td>
      <td><?= h($a['patients']['full_name'] ?? '—') ?></td>
      <td><?= h($a['staff']['full_name'] ?? '—') ?></td>
      <td><?= h($a['services']['name'] ?? '—') ?></td>
      <td><span class="badge <?= $a['status']==='completed'?'green':($a['status']==='cancelled'?'red':'amber') ?>"><?= h(ucfirst($a['status'])) ?></span></td>
    </tr>
  <?php endforeach; ?>
</table>

<div class="section-title">🩺 Ongoing treatments</div>
<table class="data">
  <tr><th>Patient</th><th>Service</th><th>Tooth</th><th>Started</th><th>Status</th></tr>
  <?php if (!count($ongoingTreatments)): ?>
    <tr><td colspan="5" style="color:var(--slate);">No treatments currently in progress.</td></tr>
  <?php endif; ?>
  <?php foreach ($ongoingTreatments as $t): ?>
    <tr>
      <td><?= h($t['patients']['full_name'] ?? '—') ?></td>
      <td><?= h($t['services']['name'] ?? '—') ?></td>
      <td><?= $t['tooth_number'] ? '#'.h($t['tooth_number']) : '—' ?></td>
      <td><?= h($t['treatment_date']) ?></td>
      <td><span class="badge amber">In progress</span></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/includes/footer.php'; ?>
