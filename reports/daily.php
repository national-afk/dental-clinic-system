<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$page_title = 'Daily Activities Report';

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = date('Y-m-d'); }
$me = current_staff();
$isMgr = can_manage_prices();

$fields = [
    'patients_attended'      => 'Total patients attended',
    'appointments_scheduled' => 'Appointments scheduled',
    'followups_contacted'    => 'Follow-up patients contacted',
    'missed_appointments'    => 'Patients who missed appointments',
    'treatments_assisted'    => 'Treatments assisted',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $row = ['report_date' => $date, 'staff_id' => $me['id'], 'notes' => trim($_POST['notes'] ?? ''), 'updated_at' => date('c')];
    foreach ($fields as $k => $_) { $row[$k] = max(0, (int)($_POST[$k] ?? 0)); }

    $existing = Supabase::selectOne('daily_reports', ['report_date' => 'eq.' . $date, 'staff_id' => 'eq.' . $me['id']]);
    $ok = $existing
        ? Supabase::update('daily_reports', ['id' => 'eq.' . $existing['id']], $row)
        : Supabase::insert('daily_reports', $row);
    if ($ok === false) {
        flash('error', 'Could not save the report. Run add_daily_reports.sql in Supabase first.');
    } else {
        log_audit('save_daily_report', 'daily_report', $date);
        flash('success', 'Daily report saved for ' . date('d M Y', strtotime($date)) . '.');
    }
    redirect('reports/daily.php?date=' . $date);
}

$stats = daily_stats($date);
$mine = Supabase::selectOne('daily_reports', ['report_date' => 'eq.' . $date, 'staff_id' => 'eq.' . $me['id']]);

$allReports = [];
if ($isMgr) {
    $allReports = Supabase::select('daily_reports', ['report_date' => 'eq.' . $date, 'select' => '*,staff(full_name,role)', 'order' => 'updated_at.desc']);
}

require __DIR__ . '/../includes/header.php';
$settings = clinic_settings();
?>

<div class="section-title">
  📋 Daily Activities Report
  <span>
    <button type="button" class="btn small outline" onclick="window.print()">🖨 Print</button>
  </span>
</div>

<form method="get" class="card" style="margin-bottom:16px;display:flex;gap:10px;align-items:end;">
  <div><label>Date</label><input type="date" name="date" value="<?= h($date) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()"></div>
  <a class="btn outline small" href="?date=<?= date('Y-m-d') ?>">Today</a>
</form>

<div class="card" id="receipt-header" style="text-align:center;margin-bottom:16px;">
  <h3 style="margin:0;"><?= h($settings['clinic_name']) ?></h3>
  <p style="color:var(--slate);margin:2px 0 0;">Daily Activities — <?= h(date('l, d F Y', strtotime($date))) ?> — <?= h($me['name']) ?></p>
</div>

<form method="post" class="card print-keep">
  <p style="margin-top:0;color:var(--slate);font-size:13.5px;">
    The numbers are counted from the system for this date. Correct any figure if needed, add notes, then save.
  </p>
  <table class="data" style="margin-bottom:16px;">
    <tr><th>Activity</th><th style="width:140px;">Your figure</th><th style="width:160px;">Counted by system</th></tr>
    <?php foreach ($fields as $k => $label):
        $val = $mine[$k] ?? $stats[$k]; ?>
      <tr>
        <td><?= h($label) ?></td>
        <td><input type="number" min="0" name="<?= h($k) ?>" value="<?= h($val) ?>" style="max-width:110px;"></td>
        <td style="color:var(--slate);"><?= (int)$stats[$k] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <label>Notes</label>
  <textarea name="notes" placeholder="Anything to hand over (optional)"><?= h($mine['notes'] ?? '') ?></textarea>
  <div style="margin-top:14px;">
    <button class="btn"><?= $mine ? 'Update my report' : 'Save my report' ?></button>
    <?php if ($mine): ?><span class="badge green" style="margin-left:8px;">Saved <?= h(date('H:i', strtotime($mine['updated_at']))) ?></span><?php endif; ?>
  </div>
</form>

<?php
$detail = [
    'Patients attended'          => $stats['lists']['attended'],
    'Follow-up patients contacted' => $stats['lists']['contacted'],
    'Missed appointments'        => $stats['lists']['missed'],
];
?>
<div class="section-title" style="margin-top:26px;">Details</div>
<div class="form-grid">
<?php foreach ($detail as $title => $names): ?>
  <div class="card">
    <h3 style="font-size:15px;"><?= h($title) ?> (<?= count($names) ?>)</h3>
    <?php if (!count($names)): ?><p style="color:var(--slate);margin:0;">None.</p>
    <?php else: ?><ul style="margin:6px 0 0;padding-left:18px;"><?php foreach ($names as $n): ?><li><?= h($n) ?></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<p style="color:var(--slate);font-size:12.5px;">
  "Missed" = appointments marked Missed, plus past appointments still Booked/Confirmed. "Contacted" = patients reached by a sent reminder
  or by the WhatsApp/SMS buttons. "Treatments assisted" counts the treatments recorded on that date.
</p>

<?php if ($isMgr): ?>
  <div class="section-title" style="margin-top:26px;">All staff reports — <?= h(date('d M Y', strtotime($date))) ?></div>
  <table class="data">
    <tr><th>Staff</th><th>Role</th><th>Attended</th><th>Scheduled</th><th>Contacted</th><th>Missed</th><th>Assisted</th><th>Notes</th></tr>
    <?php if (!count($allReports)): ?><tr><td colspan="8" style="color:var(--slate);">No staff report submitted for this date.</td></tr><?php endif; ?>
    <?php foreach ($allReports as $r): ?>
      <tr>
        <td><?= h($r['staff']['full_name'] ?? '—') ?></td>
        <td><span class="badge gray"><?= h(ucfirst(str_replace('_', ' ', $r['staff']['role'] ?? ''))) ?></span></td>
        <td><?= (int)$r['patients_attended'] ?></td>
        <td><?= (int)$r['appointments_scheduled'] ?></td>
        <td><?= (int)$r['followups_contacted'] ?></td>
        <td><?= (int)$r['missed_appointments'] ?></td>
        <td><?= (int)$r['treatments_assisted'] ?></td>
        <td><?= h($r['notes']) ?: '—' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
