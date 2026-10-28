<?php
require_once __DIR__ . '/../config/config.php';
require_manager();
$page_title = 'Audit Logs';

$logs = Supabase::select('audit_logs', [
    'select' => '*,staff(full_name,role)',
    'order' => 'created_at.desc',
    'limit' => 200,
]);

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">📜 Audit Logs (Manager only)</div>
<p style="color:var(--slate);">Showing the most recent 200 actions across the system.</p>

<table class="data">
  <tr><th>Time</th><th>Staff</th><th>Role</th><th>Action</th><th>Entity</th><th>Details</th></tr>
  <?php if (!count($logs)): ?><tr><td colspan="6" style="color:var(--slate);">No audit records yet.</td></tr><?php endif; ?>
  <?php foreach ($logs as $l): ?>
    <tr>
      <td><?= h(date('d M Y H:i', strtotime($l['created_at']))) ?></td>
      <td><?= h($l['staff']['full_name'] ?? '—') ?></td>
      <td><span class="badge gray"><?= h(ucfirst($l['staff']['role'] ?? '—')) ?></span></td>
      <td><span class="badge teal"><?= h(str_replace('_', ' ', $l['action'])) ?></span></td>
      <td><?= h($l['entity_type']) ?: '—' ?></td>
      <td><?= h($l['details']) ?: '—' ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
