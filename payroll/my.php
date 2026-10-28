<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$page_title = 'My payslips';

$slips = Supabase::select('payslips', ['staff_id' => 'eq.' . current_staff()['id'], 'status' => 'eq.paid', 'order' => 'period_month.desc']);

require __DIR__ . '/../includes/header.php';
?>
<div class="section-title">🧾 My payslips</div>
<table class="data">
  <tr><th>Month</th><th>Payslip #</th><th>Net pay</th><th>Paid on</th><th></th></tr>
  <?php if (!count($slips)): ?><tr><td colspan="5" style="color:var(--slate);">No paid payslips yet.</td></tr><?php endif; ?>
  <?php foreach ($slips as $p): ?>
    <tr>
      <td><?= h(date('F Y', strtotime($p['period_month']))) ?></td>
      <td><?= h($p['payslip_no']) ?></td>
      <td><?= money($p['net_pay']) ?></td>
      <td><?= h(date('d M Y', strtotime($p['paid_at']))) ?> · <?= h(payment_method_label((string)$p['payment_method'])) ?></td>
      <td><a class="btn small outline" href="<?= base_url('payroll/payslip.php?id=' . $p['id']) ?>">View / print</a></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php require __DIR__ . '/../includes/footer.php'; ?>
