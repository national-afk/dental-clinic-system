<?php
require_once __DIR__ . '/../config/config.php';
require_manager();
$page_title = 'All Shifts';

$shifts = Supabase::select('cashier_shifts', ['select' => '*,staff(full_name)', 'order' => 'opened_at.desc']);

require __DIR__ . '/../includes/header.php';
?>

<div class="section-title">🕒 All Shifts (Manager only)</div>

<table class="data">
  <tr><th>Cashier</th><th>Type</th><th>Opened</th><th>Closed</th><th>Opening Cash</th><th>Closing Cash</th><th>Status</th></tr>
  <?php if (!count($shifts)): ?><tr><td colspan="7" style="color:var(--slate);">No shifts recorded yet.</td></tr><?php endif; ?>
  <?php foreach ($shifts as $s): ?>
    <tr>
      <td><?= h($s['staff']['full_name'] ?? '—') ?></td>
      <td><span class="badge gray"><?= h(ucfirst($s['shift_type'] ?? 'custom')) ?></span></td>
      <td><?= h(date('d M Y H:i', strtotime($s['opened_at']))) ?></td>
      <td><?= $s['closed_at'] ? h(date('d M Y H:i', strtotime($s['closed_at']))) : '—' ?></td>
      <td><?= money($s['opening_cash']) ?></td>
      <td><?= $s['closing_cash'] !== null ? money($s['closing_cash']) : '—' ?></td>
      <td><?= $s['status'] === 'open' ? '<span class="badge amber">Open</span>' : '<span class="badge gray">Closed</span>' ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
