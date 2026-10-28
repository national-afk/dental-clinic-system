<?php
require_once __DIR__ . '/../config/config.php';
require_manager();
$page_title = 'Staff Reports';

$ym = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
$ms = $ym . '-01';
$next = date('Y-m-d', strtotime($ms . ' +1 month'));
$tz = date('P');

$staff = array_values(array_filter(Supabase::select('staff', ['order' => 'full_name.asc']), fn($s) => !empty($s['active'])));

$att = [];   // staff_id => [days => set, mins]
foreach (Supabase::select('doctor_attendance', [
    'check_in' => 'gte.' . $ms . 'T00:00:00' . $tz,
    'and' => '(check_in.lt.' . $next . 'T00:00:00' . $tz . ')',
]) as $r) {
    $a = &$att[$r['doctor_id']];
    $a['days'][date('Y-m-d', strtotime($r['check_in']))] = 1;
    $a['mins'] = ($a['mins'] ?? 0) + (!empty($r['check_out']) ? max(0, (strtotime($r['check_out']) - strtotime($r['check_in'])) / 60) : 0);
    unset($a);
}
$daily = [];  // staff_id => [count, attended]
foreach (Supabase::select('daily_reports', ['report_date' => 'gte.' . $ms, 'and' => '(report_date.lt.' . $next . ')']) as $r) {
    $d = &$daily[$r['staff_id']];
    $d['n'] = ($d['n'] ?? 0) + 1;
    $d['attended'] = ($d['attended'] ?? 0) + (int)$r['patients_attended'];
    unset($d);
}
$slips = [];
foreach (Supabase::select('payslips', ['period_month' => 'eq.' . $ms]) as $p) { $slips[$p['staff_id']] = $p; }

$totDays = 0; $totHours = 0; $totReports = 0;
require __DIR__ . '/../includes/header.php';
?>
<div class="section-title">📑 Staff Reports — <?= h(date('F Y', strtotime($ms))) ?></div>

<form method="get" class="card" style="margin-bottom:16px;display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
  <div><label>Month</label><input type="month" name="month" value="<?= h($ym) ?>" onchange="this.form.submit()"></div>
  <a class="btn small outline" href="<?= base_url('reports/daily.php') ?>">Daily activities</a>
  <a class="btn small outline" href="<?= base_url('attendance/index.php') ?>">Attendance log</a>
  <a class="btn small outline" href="<?= base_url('shifts/index.php') ?>">Cashier shifts</a>
  <a class="btn small outline" href="<?= base_url('audit/index.php') ?>">Audit logs</a>
</form>

<table class="data">
  <tr><th>Staff</th><th>Role</th><th>Days present</th><th>Hours</th><th>Daily reports</th><th>Patients attended</th><th>Salary</th></tr>
  <?php foreach ($staff as $s):
      $days = count($att[$s['id']]['days'] ?? []); $hours = round(($att[$s['id']]['mins'] ?? 0) / 60, 1);
      $dn = $daily[$s['id']]['n'] ?? 0; $da = $daily[$s['id']]['attended'] ?? 0;
      $totDays += $days; $totHours += $hours; $totReports += $dn;
      $canSee = can_manage_staff_member($s['role']); $sl = $slips[$s['id']] ?? null; ?>
    <tr>
      <td><a href="<?= base_url('staff/view.php?id=' . $s['id']) ?>"><?= h($s['full_name']) ?></a></td>
      <td><span class="badge <?= is_manager_role($s['role']) ? 'amber' : 'teal' ?>"><?= h(role_label($s['role'])) ?></span></td>
      <td><?= $days ?></td>
      <td><?= h($hours) ?></td>
      <td><?= $dn ?></td>
      <td><?= $dn ? (int)$da : '—' ?></td>
      <td><?php
        if (!$canSee) echo '<span style="color:var(--slate);">Superior only</span>';
        elseif ($sl) echo ($sl['status'] === 'paid' ? '<span class="badge green">Paid</span> ' : '<span class="badge amber">Draft</span> ') . h(money($sl['net_pay']));
        else echo '<span class="badge gray">No payslip</span>'; ?></td>
    </tr>
  <?php endforeach; ?>
  <tr>
    <td colspan="2"><strong>Total</strong></td>
    <td><strong><?= $totDays ?></strong></td><td><strong><?= h(round($totHours, 1)) ?></strong></td><td><strong><?= $totReports ?></strong></td><td colspan="2"></td>
  </tr>
</table>
<p style="color:var(--slate);font-size:12.5px;">
  Days and hours come from staff check-in / check-out (My Attendance). A staff member who never checks out has days counted but no hours for that day.
</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
