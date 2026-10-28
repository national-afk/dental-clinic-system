<?php
require_once __DIR__ . '/../config/config.php';
$page_title = 'Braces History';

$id = $_GET['id'] ?? '';
$case = Supabase::selectOne('braces_cases', ['id' => 'eq.' . $id, 'select' => '*,patients(full_name,phone),staff(full_name)']);
if (!$case) { flash('error', 'Braces case not found.'); redirect('braces/index.php'); }

$visits = Supabase::select('braces_visits', ['braces_case_id' => 'eq.' . $id, 'order' => 'visit_date.asc']);

$VISIT_LABELS = [
    'started' => 'Braces Started',
    'adjustment_due' => 'Adjustment Due',
    'adjustment_completed' => 'Adjustment Completed',
];

$status = $case['status'] === 'active' ? braces_status($case['next_visit_date']) : null;

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
  🦷 Braces History — <?= h($case['patients']['full_name'] ?? '—') ?>
  <a class="btn outline small" href="<?= base_url('braces/index.php') ?>">← Back</a>
</div>

<div class="form-grid">
  <div class="card">
    <h3>Case info</h3>
    <p><strong>Doctor:</strong> <?= h($case['staff']['full_name'] ?? '—') ?></p>
    <p><strong>Start date:</strong> <?= h(date('d M Y', strtotime($case['start_date']))) ?></p>
    <p><strong>Follow-up interval:</strong> every <?= h($case['interval_days']) ?> days</p>
    <p><strong>Notes:</strong> <?= h($case['notes']) ?: '—' ?></p>
  </div>
  <div class="card">
    <h3>Status</h3>
    <?php if ($case['status'] === 'active'): ?>
      <p><strong>Next visit:</strong> <?= h(date('d M Y', strtotime($case['next_visit_date']))) ?></p>
      <p><span class="badge <?= $status['badge'] ?>"><?= $status['emoji'] ?> <?= h($status['label']) ?></span></p>
    <?php else: ?>
      <p><span class="badge gray"><?= h(ucfirst($case['status'])) ?></span></p>
    <?php endif; ?>
  </div>
</div>

<div class="section-title">📜 Braces History</div>
<table class="data">
  <tr><th>Date</th><th>Event</th><th>Notes</th></tr>
  <?php if (!count($visits)): ?><tr><td colspan="3" style="color:var(--slate);">No history yet.</td></tr><?php endif; ?>
  <?php foreach ($visits as $v): ?>
    <tr>
      <td><?= h(date('d/m/Y', strtotime($v['visit_date']))) ?></td>
      <td><span class="badge teal"><?= h($VISIT_LABELS[$v['visit_type']] ?? $v['visit_type']) ?></span></td>
      <td><?= h($v['notes']) ?: '—' ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
