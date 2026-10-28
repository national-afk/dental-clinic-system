<?php
require_once __DIR__ . '/../config/config.php';
$page_title = 'Braces Follow-up';

// Start a new braces case (Doctor/Manager only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start') {
    if (!can_diagnose()) { flash('error', 'Only a Doctor or Manager can start a braces case.'); redirect('braces/index.php'); }

    $startDate = $_POST['start_date'];
    $interval = (int)$_POST['interval_days'];
    $nextVisit = date('Y-m-d', strtotime($startDate . ' + ' . $interval . ' days'));

    $case = Supabase::insert('braces_cases', [
        'patient_id' => $_POST['patient_id'],
        'doctor_id' => current_staff()['id'],
        'start_date' => $startDate,
        'interval_days' => $interval,
        'next_visit_date' => $nextVisit,
        'notes' => trim($_POST['notes']),
    ]);
    $caseId = $case[0]['id'] ?? null;

    if ($caseId) {
        Supabase::insert('braces_visits', [
            'braces_case_id' => $caseId,
            'visit_type' => 'started',
            'visit_date' => $startDate,
            'recorded_by' => current_staff()['id'],
        ]);
        Supabase::insert('braces_visits', [
            'braces_case_id' => $caseId,
            'visit_type' => 'adjustment_due',
            'visit_date' => $nextVisit,
            'recorded_by' => current_staff()['id'],
        ]);
    }

    log_audit('start_braces_case', 'braces_case', $caseId, $_POST['patient_id']);
    flash('success', 'Braces case started. Next visit: ' . date('d M Y', strtotime($nextVisit)));
    redirect('braces/index.php');
}

// Mark today's adjustment as completed -> auto-schedule the next one
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_adjustment') {
    if (!can_diagnose()) { flash('error', 'Only a Doctor or Manager can complete an adjustment.'); redirect('braces/index.php'); }

    $case = Supabase::selectOne('braces_cases', ['id' => 'eq.' . $_POST['case_id']]);
    if ($case) {
        $today = date('Y-m-d');
        $nextVisit = date('Y-m-d', strtotime($today . ' + ' . $case['interval_days'] . ' days'));

        Supabase::insert('braces_visits', [
            'braces_case_id' => $case['id'],
            'visit_type' => 'adjustment_completed',
            'visit_date' => $today,
            'notes' => trim($_POST['notes'] ?? ''),
            'recorded_by' => current_staff()['id'],
        ]);
        Supabase::update('braces_cases', ['id' => 'eq.' . $case['id']], ['next_visit_date' => $nextVisit]);
        Supabase::insert('braces_visits', [
            'braces_case_id' => $case['id'],
            'visit_type' => 'adjustment_due',
            'visit_date' => $nextVisit,
            'recorded_by' => current_staff()['id'],
        ]);

        log_audit('complete_braces_adjustment', 'braces_case', $case['id']);
        flash('success', 'Adjustment completed. Next visit: ' . date('d M Y', strtotime($nextVisit)));
    }
    redirect('braces/index.php');
}

// Mark a case as completed/removed (braces off)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'finish_case') {
    if (!can_diagnose()) { flash('error', 'Only a Doctor or Manager can do that.'); redirect('braces/index.php'); }
    Supabase::update('braces_cases', ['id' => 'eq.' . $_POST['case_id']], ['status' => 'completed']);
    flash('success', 'Braces case marked as completed.');
    redirect('braces/index.php');
}

$cases = Supabase::select('braces_cases', [
    'status' => 'eq.active',
    'select' => '*,patients(full_name,phone)',
    'order' => 'next_visit_date.asc',
]);

$dueToday = 0; $upcoming = 0; $overdue = 0; $thisMonth = 0;
$monthPrefix = date('Y-m');
foreach ($cases as $c) {
    $s = braces_status($c['next_visit_date']);
    if ($s['label'] === 'Due Today') $dueToday++;
    elseif ($s['label'] === 'Overdue') $overdue++;
    else $upcoming++;
    if (strpos($c['start_date'], $monthPrefix) === 0) $thisMonth++;
}

$patients = Supabase::select('patients', ['order' => 'full_name.asc']);

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">🔗 Braces Follow-up</div>

<div class="stat-strip">
  <div class="stat"><div class="num"><?= count($cases) ?></div><div class="label">Active Braces</div></div>
  <div class="stat"><div class="num"><?= $dueToday ?></div><div class="label">🟢 Due Today</div></div>
  <div class="stat"><div class="num"><?= $upcoming ?></div><div class="label">🟡 Upcoming</div></div>
  <div class="stat warn"><div class="num"><?= $overdue ?></div><div class="label">🔴 Overdue</div></div>
  <div class="stat"><div class="num"><?= $thisMonth ?></div><div class="label">📅 Started This Month</div></div>
</div>

<table class="data">
  <tr><th>Patient</th><th>Braces Started</th><th>Next Adjustment</th><th>Days Left</th><th>Status</th><?php if (can_diagnose() || can_manage_appointments()): ?><th></th><?php endif; ?></tr>
  <?php if (!count($cases)): ?><tr><td colspan="6" style="color:var(--slate);">No active braces cases.</td></tr><?php endif; ?>
  <?php foreach ($cases as $c): $s = braces_status($c['next_visit_date']); ?>
    <tr>
      <td><a href="<?= base_url('braces/view.php?id=' . $c['id']) ?>"><?= h($c['patients']['full_name'] ?? '—') ?></a></td>
      <td><?= h(date('d/m/Y', strtotime($c['start_date']))) ?></td>
      <td><?= h(date('d/m/Y', strtotime($c['next_visit_date']))) ?></td>
      <td><?= $s['days_left'] < 0 ? abs($s['days_left']) . ' days overdue' : $s['days_left'] . ' days' ?></td>
      <td><span class="badge <?= $s['badge'] ?>"><?= $s['emoji'] ?> <?= h($s['label']) ?></span></td>
      <?php if (can_diagnose() || can_manage_appointments()): ?>
      <td style="white-space:nowrap;">
        <?php if (!empty($c['patients']['phone'])): ?>
          <a class="btn small outline" target="_blank" rel="noopener" title="Opens WhatsApp with the follow-up message ready" href="<?= base_url('reports/contact.php?kind=braces&via=whatsapp&id=' . $c['id']) ?>">💬 WhatsApp</a>
        <?php endif; ?>
        <?php if (can_diagnose()): ?>
        <form method="post" style="display:inline;">
          <input type="hidden" name="action" value="complete_adjustment">
          <input type="hidden" name="case_id" value="<?= h($c['id']) ?>">
          <button class="btn small outline">Complete adjustment</button>
        </form>
        <form method="post" style="display:inline;" onsubmit="return confirm('Mark this braces case as completed (braces removed)?')">
          <input type="hidden" name="action" value="finish_case">
          <input type="hidden" name="case_id" value="<?= h($c['id']) ?>">
          <button class="btn small outline">Finish case</button>
        </form>
        <?php endif; ?>
      </td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
</table>

<?php if (can_diagnose()): ?>
<div class="section-title" style="margin-top:28px;">+ Start a new braces case</div>
<form method="post" class="card form-grid">
  <input type="hidden" name="action" value="start">
  <div>
    <label>Patient *</label>
    <select name="patient_id" required>
      <option value="">Select…</option>
      <?php foreach ($patients as $p): ?><option value="<?= h($p['id']) ?>"><?= h($p['full_name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div>
    <label>Braces Start Date *</label>
    <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>
  </div>
  <div>
    <label>Follow-up interval (days)</label>
    <input type="number" name="interval_days" value="<?= h(app_setting('braces_default_interval', '30')) ?>" min="1" required>
  </div>
  <div class="full">
    <label>Notes</label>
    <textarea name="notes"></textarea>
  </div>
  <div class="full"><button class="btn">Start braces case</button></div>
</form>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
