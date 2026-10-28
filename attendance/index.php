<?php
require_once __DIR__ . '/../config/config.php';
$page_title = 'Attendance';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check_in') {
    Supabase::insert('doctor_attendance', [
        'doctor_id' => current_staff()['id'],
        'shift_type' => $_POST['shift_type'] ?? null,
        'status' => 'in',
    ]);
    flash('success', 'Checked in. Have a great shift!');
    redirect('attendance/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check_out') {
    $open = get_open_attendance();
    if ($open) {
        Supabase::update('doctor_attendance', ['id' => 'eq.' . $open['id']], [
            'check_out' => date('c'),
            'notes' => trim($_POST['notes'] ?? ''),
            'status' => 'out',
        ]);
        flash('success', 'Checked out.');
    }
    redirect('attendance/index.php');
}

$isMgr = can_manage_prices();
$myOpen = get_open_attendance();

$myHistory = Supabase::select('doctor_attendance', [
    'doctor_id' => 'eq.' . current_staff()['id'],
    'order' => 'check_in.desc',
    'limit' => 20,
]);

$allRecords = [];
if ($isMgr) {
    $allRecords = Supabase::select('doctor_attendance', [
        'select' => '*,staff(full_name)',
        'order' => 'check_in.desc',
        'limit' => 100,
    ]);
}

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">🕘 Attendance</div>

<div class="card" style="max-width:420px;margin-bottom:22px;">
  <?php if (!$myOpen): ?>
    <h3>You are not checked in</h3>
    <form method="post">
      <input type="hidden" name="action" value="check_in">
      <label>Shift</label>
      <select name="shift_type">
        <option value="morning">Morning (8:00 AM – 2:00 PM)</option>
        <option value="evening">Evening (2:00 PM – 8:00 PM)</option>
      </select>
      <div style="height:12px;"></div>
      <button class="btn" style="width:100%;justify-content:center;">✅ Check In</button>
    </form>
  <?php else: ?>
    <h3>Checked in since <?= h(date('H:i', strtotime($myOpen['check_in']))) ?></h3>
    <?php if (!empty($myOpen['shift_type'])): ?><p><span class="badge teal"><?= h(ucfirst($myOpen['shift_type'])) ?> shift</span></p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="action" value="check_out">
      <label>Notes (optional)</label>
      <textarea name="notes" placeholder="e.g. patients seen, handover notes"></textarea>
      <div style="height:12px;"></div>
      <button class="btn danger" style="width:100%;justify-content:center;">🚪 Check Out</button>
    </form>
  <?php endif; ?>
</div>

<div class="section-title">My recent attendance</div>
<table class="data">
  <tr><th>Check in</th><th>Check out</th><th>Shift</th><th>Duration</th><th>Notes</th></tr>
  <?php if (!count($myHistory)): ?><tr><td colspan="5" style="color:var(--slate);">No records yet.</td></tr><?php endif; ?>
  <?php foreach ($myHistory as $r): ?>
    <tr>
      <td><?= h(date('d M Y H:i', strtotime($r['check_in']))) ?></td>
      <td><?= $r['check_out'] ? h(date('d M Y H:i', strtotime($r['check_out']))) : '<span class="badge amber">Still in</span>' ?></td>
      <td><?= !empty($r['shift_type']) ? h(ucfirst($r['shift_type'])) : '—' ?></td>
      <td><?php
        if ($r['check_out']) {
            $mins = (strtotime($r['check_out']) - strtotime($r['check_in'])) / 60;
            printf('%dh %dm', intdiv((int)$mins, 60), (int)$mins % 60);
        } else { echo '—'; }
      ?></td>
      <td><?= h($r['notes']) ?: '—' ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php if ($isMgr): ?>
  <div class="section-title" style="margin-top:30px;">All staff — attendance log (Manager)</div>
  <table class="data">
    <tr><th>Staff</th><th>Check in</th><th>Check out</th><th>Status</th><th>Notes</th></tr>
    <?php if (!count($allRecords)): ?><tr><td colspan="5" style="color:var(--slate);">No records yet.</td></tr><?php endif; ?>
    <?php foreach ($allRecords as $r): ?>
      <tr>
        <td><?= h($r['staff']['full_name'] ?? '—') ?></td>
        <td><?= h(date('d M Y H:i', strtotime($r['check_in']))) ?></td>
        <td><?= $r['check_out'] ? h(date('d M Y H:i', strtotime($r['check_out']))) : '—' ?></td>
        <td><?= $r['status'] === 'in' ? '<span class="badge amber">Checked in</span>' : '<span class="badge gray">Checked out</span>' ?></td>
        <td><?= h($r['notes']) ?: '—' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
