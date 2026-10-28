<?php
/**
 * Sidebar menu.  Dashboard · CLINICAL · FINANCE · MANAGEMENT · MY WORK · Logout
 * Each item: [label, href, match, visible]. "match" = list of "folder/file.php" paths (or "dir:folder") that light it up.
 */
function render_sidebar(): void
{
$current = basename($_SERVER['SCRIPT_NAME']);
$dir = basename(dirname($_SERVER['SCRIPT_NAME']));
$cur = in_array($dir, module_folders(), true) ? $dir . '/' . $current : $current;   // e.g. "patients/index.php" or "index.php"
$isManager = can_manage_prices();
$me = current_staff()['id'];
$viewingSelf = !isset($_GET['id']) || $_GET['id'] === $me;

$is = function (array $match) use ($cur, $dir) {
    foreach ($match as $m) {
        if ($m === $cur || ($m === 'dir:' . $dir)) return true;
    }
    return false;
};

$menu = [
    '' => [
        ['📊', 'Dashboard', 'index.php', ['index.php'], true],
    ],
    'Clinical' => [
        ['🧑‍⚕️', 'Patients', 'patients/index.php', ['dir:patients'], true],
        ['📅', 'Appointments', 'appointments/index.php', ['dir:appointments'], true],
        ['🦷', 'Dental Chart', 'dental-chart/index.php', ['dir:dental-chart'], true],
        ['🩺', 'Treatments', 'treatments/index.php', ['dir:treatments'], true],
        ['🔗', 'Braces Follow-up', 'braces/index.php', ['dir:braces'], true],
        ['💊', 'Prescriptions', 'prescriptions/index.php', ['dir:prescriptions'], true],
        ['🧪', 'Lab Orders', 'lab/index.php', ['dir:lab'], true],
        ['❤️', 'Vital Signs', 'nurse/index.php', ['dir:nurse'], is_nurse() || $isManager],
    ],
    'Finance' => [
        ['🧾', 'POS / Make Sale', 'pos/index.php', ['pos/index.php', 'pos/receipt.php', 'pos/thermal.php'], can_handle_payments()],
        ['🕒', 'Cashier Shift', 'pos/shift.php', ['pos/shift.php', 'dir:shifts'], can_handle_payments()],
        ['💳', 'Billing & Payments', 'billing/index.php', ['billing/index.php', 'billing/invoice.php', 'billing/thermal.php'], can_handle_payments()],
        ['📈', $isManager ? 'Sales Report' : 'My Sales', 'billing/report.php', ['billing/report.php'], can_handle_payments()],
        ['💸', 'Expenses', 'expenses/index.php', ['dir:expenses'], $isManager],
    ],
    'Management' => [
        ['🗂️', 'Staff Category', 'staff/categories.php', ['staff/categories.php', 'staff/index.php', 'staff/edit.php'], $isManager],
        ['➕', 'Staff Registration', 'staff/register.php', ['staff/register.php'], $isManager],
        ['💰', 'Staff Salary', 'payroll/index.php', ['payroll/index.php', 'payroll/salary.php', 'payroll/advances.php', 'payroll/payslip.php'], $isManager],
        ['📑', 'Staff Reports', 'staff/reports.php', ['staff/reports.php', 'dir:audit'], $isManager],
        ['📦', 'Inventory', 'pharmacy/index.php', ['dir:pharmacy'], true],
        ['⚙️', 'Settings', 'settings/index.php', ['dir:settings', 'dir:backup'], $isManager],
    ],
    'My Work' => [
        ['🕘', 'My Attendance', 'attendance/index.php', ['dir:attendance'], true],
        ['📋', 'Daily Report', 'reports/daily.php', ['dir:reports'], true],
        ['🧾', 'My Payslips', 'payroll/my.php', $isManager ? ['payroll/my.php'] : ['payroll/my.php', 'payroll/payslip.php'], true],
        ['👤', 'My Profile', 'staff/view.php', $viewingSelf ? ['staff/view.php'] : [], true],
    ],
];
// Viewing someone else's profile belongs to Staff Category
if (!$viewingSelf) { $menu['Management'][0][3][] = 'staff/view.php'; }
$s = clinic_settings();
?>
<div class="sidebar">
  <div class="brand" style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
    <span style="display:flex;align-items:center;gap:10px;">
      <?php if (!empty($s['logo_url'])): ?>
        <img src="<?= h($s['logo_url']) ?>" alt="Logo" style="height:30px;width:30px;object-fit:contain;border-radius:6px;background:#fff;">
      <?php else: ?><span style="font-size:24px;">🦷</span><?php endif; ?>
      <span>
        <?= h($s['clinic_name']) ?>
        <small>Clinic Management System</small>
      </span>
    </span>
    <button type="button" class="sidebar-close-btn" onclick="toggleSidebar(false)" aria-label="Close menu">✕</button>
  </div>
  <nav>
<?php foreach ($menu as $section => $items):
    $visible = array_filter($items, fn($i) => $i[4]);
    if (!$visible) continue; ?>
<?php if ($section !== ''): ?>
    <div class="section-label"><?= h($section) ?></div>
<?php endif; ?>
<?php foreach ($visible as [$icon, $label, $href, $match]): ?>
    <a class="<?= $is($match) ? 'active' : '' ?>" href="<?= base_url($href) ?>"><?= $icon ?> <?= h($label) ?></a>
<?php endforeach; ?>
<?php endforeach; ?>
    <a class="logout-link" href="<?= base_url('logout.php') ?>">🚪 Logout</a>
  </nav>
</div>
<?php
}
render_sidebar();
