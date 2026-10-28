<?php

function require_login()
{
    if (empty($_SESSION['staff_id'])) {
        header('Location: ' . base_url('login.php'));
        exit;
    }
    // Security setting: automatic logout after N idle minutes (0 = never)
    $mins = (int)app_setting('session_timeout_minutes', '0');
    if ($mins > 0 && isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $mins * 60) {
        session_unset();
        session_destroy();
        header('Location: ' . base_url('login.php?expired=1'));
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function current_staff()
{
    return [
        'id' => $_SESSION['staff_id'] ?? null,
        'name' => $_SESSION['staff_name'] ?? 'Guest',
        'role' => $_SESSION['staff_role'] ?? 'staff',
    ];
}

/** The five roles of the system, highest first. */
const STAFF_ROLES = [
    'superior' => 'Superior',
    'manager'  => 'Manager',
    'doctor'   => 'Doctor',
    'nurse'    => 'Nurse',
    'cashier'  => 'Cashier',
];

function current_role(): string { return strtolower($_SESSION['staff_role'] ?? ''); }

/** Superior = top role. Only a Superior can create or change Managers and other Superiors. */
function is_superior(): bool { return current_role() === 'superior'; }

/** Manager-level roles ('admin' is the legacy name of Manager). */
function is_manager_role(string $role): bool { return in_array(strtolower($role), ['superior', 'manager', 'admin'], true); }

function role_label(string $role): string
{
    return STAFF_ROLES[strtolower($role)] ?? ucfirst(str_replace('_', ' ', $role));
}

/** Roles the signed-in user may assign: Superior -> all five; Manager -> Doctor, Nurse, Cashier. */
function assignable_roles(): array
{
    if (is_superior()) return STAFF_ROLES;
    return array_diff_key(STAFF_ROLES, ['superior' => 1, 'manager' => 1]);
}

/** May the signed-in user edit / disable / reset / delete an account that has $targetRole? */
function can_manage_staff_member(string $targetRole): bool
{
    return is_manager_role($targetRole) ? is_superior() : can_manage_prices();
}

/** Manager-level access: prices, pharmacy items, staff, settings, reports. */
function can_manage_prices(): bool
{
    $role = strtolower($_SESSION['staff_role'] ?? '');
    return is_manager_role($role);
}

/** True for roles allowed to handle money (Billing, POS, Shifts): Manager + Cashier. Doctors don't. */
function can_handle_payments(): bool
{
    $role = strtolower($_SESSION['staff_role'] ?? '');
    return in_array($role, ['superior', 'manager', 'admin', 'cashier'], true);
}

/** True for Nurse role. */
function is_nurse(): bool
{
    return strtolower($_SESSION['staff_role'] ?? '') === 'nurse';
}

/** Manager-level roles can add/edit/delete pharmacy items. Others can only do stock in/out. */
function can_manage_pharmacy(): bool
{
    $role = strtolower($_SESSION['staff_role'] ?? '');
    return in_array($role, ['superior', 'manager', 'admin'], true);
}

function require_manage_pharmacy()
{
    if (!can_manage_pharmacy()) {
        flash('error', 'Only a Manager can do that.');
        redirect('pharmacy/index.php');
    }
}

/** Only Doctor/Manager can create or change a diagnosis (dental chart condition) or write prescriptions. */
function can_diagnose(): bool
{
    $role = strtolower($_SESSION['staff_role'] ?? '');
    return in_array($role, ['superior', 'manager', 'admin', 'doctor'], true);
}

/** Works out Upcoming / Due Today / Overdue from a braces case's next_visit_date. */
function braces_status(string $nextVisitDate): array
{
    $today = date('Y-m-d');
    $daysLeft = (int)((strtotime($nextVisitDate) - strtotime($today)) / 86400);
    if ($daysLeft < 0) return ['label' => 'Overdue', 'badge' => 'red', 'emoji' => '🔴', 'days_left' => $daysLeft];
    if ($daysLeft === 0) return ['label' => 'Due Today', 'badge' => 'green', 'emoji' => '🟢', 'days_left' => 0];
    return ['label' => 'Upcoming', 'badge' => 'amber', 'emoji' => '🟡', 'days_left' => $daysLeft];
}

/** Writes one row to audit_logs. Never blocks the request if logging fails. */
function log_audit(string $action, string $entityType = null, string $entityId = null, string $details = null): void
{
    if (app_setting('audit_enabled', '1') !== '1') { return; }
    try {
        Supabase::insert('audit_logs', [
            'staff_id' => current_staff()['id'],
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
        ]);
    } catch (\Throwable $e) {
        // logging must never break the page
    }
}

/** Blocks the page entirely for anyone who isn't allowed to handle money. */
function require_payment_access()
{
    if (!can_handle_payments()) {
        flash('error', 'Only Manager or Cashier accounts can access Billing/POS.');
        redirect('index.php');
    }
}

/** Blocks the page/action entirely for anyone who isn't Manager/Admin. */
function require_manager()
{
    if (!can_manage_prices()) {
        flash('error', 'Only a Manager can do that.');
        redirect('index.php');
    }
}

/** Manager AND Cashier can manage appointments (book/confirm/cancel/reschedule). Doctors are view-only. */
function can_manage_appointments(): bool
{
    $role = strtolower($_SESSION['staff_role'] ?? '');
    return in_array($role, ['superior', 'manager', 'admin', 'cashier'], true);
}

function require_appointment_access()
{
    if (!can_manage_appointments()) {
        flash('error', 'You do not have permission to manage appointments.');
        redirect('appointments/index.php');
    }
}

/** Reads clinic-wide settings (name, currency) from the DB, cached per-request. */
function clinic_settings(): array
{
    static $cached = null;
    if ($cached !== null) return $cached;
    $row = Supabase::selectOne('clinic_settings', ['id' => 'eq.1']);
    $cached = [
        'clinic_name' => $row['clinic_name'] ?? APP_NAME,
        'currency_symbol' => $row['currency_symbol'] ?? APP_CURRENCY,
        'currency_code' => $row['currency_code'] ?? 'USD',
        'secondary_currency_name' => $row['secondary_currency_name'] ?? null,
        'secondary_currency_code' => $row['secondary_currency_code'] ?? null,
        'exchange_rate' => $row['exchange_rate'] ?? null,
        'logo_url' => $row['logo_url'] ?? null,
        'clinic_address' => $row['clinic_address'] ?? null,
        'clinic_phone' => $row['clinic_phone'] ?? null,
        'receipt_footer' => $row['receipt_footer'] ?? 'Thank you for choosing us!',
        'receipt_auto_print' => $row['receipt_auto_print'] ?? true,
        'receipt_paper' => $row['receipt_paper'] ?? '80mm',
    ];
    return $cached;
}

/** Returns the current staff member's OPEN shift row, or null if none is open. */
function get_open_shift(): ?array
{
    static $cached = 'unset';
    if ($cached !== 'unset') return $cached;
    $staffId = current_staff()['id'];
    $cached = Supabase::selectOne('cashier_shifts', [
        'cashier_id' => 'eq.' . $staffId,
        'status' => 'eq.open',
    ]);
    return $cached;
}

/** Blocks POS sales if the current staff member has no open shift. */
function require_open_shift(): void
{
    if (!get_open_shift()) {
        flash('error', 'Please open a shift before making a sale.');
        redirect('pos/shift.php');
    }
}

function is_doctor(): bool
{
    $role = strtolower($_SESSION['staff_role'] ?? '');
    return $role === 'doctor';
}

/** Returns the current doctor's open (not checked-out) attendance row, or null. */
function get_open_attendance()
{
    static $cached = 'unset';
    if ($cached !== 'unset') return $cached;
    $cached = Supabase::selectOne('doctor_attendance', [
        'doctor_id' => 'eq.' . current_staff()['id'],
        'status' => 'eq.in',
    ]);
    return $cached;
}

/** Works out the app's base URL so links work whatever subfolder it's installed in. */
function base_url(string $path = ''): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $root = rtrim(str_replace('\\', '/', dirname($script)), '/');
    // walk up out of module folders (patients/, billing/, etc.) to the app root
    $known = module_folders();
    foreach ($known as $folder) {
        if (str_ends_with($root, '/' . $folder)) {
            $root = substr($root, 0, -strlen('/' . $folder));
        }
    }
    return $root . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

function flash(string $key, string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return;
    }
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function money($amount): string
{
    return clinic_settings()['currency_symbol'] . number_format((float)$amount, 2);
}

/** Returns the same amount converted to the secondary currency, formatted, or '' if not configured. */
function money_secondary($amount): string
{
    $s = clinic_settings();
    if (empty($s['exchange_rate']) || empty($s['secondary_currency_code'])) return '';
    $converted = (float)$amount * (float)$s['exchange_rate'];
    return number_format($converted, 2) . ' ' . $s['secondary_currency_code'];
}

function h($str): string
{
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function generate_patient_code(): string
{
    $n = Supabase::rpc('get_next_number', ['counter_id' => 'patient']);
    if ($n === false || $n === null) { return app_setting('patient_prefix','PT') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)); } // fallback
    return app_setting('patient_prefix','PT') . '-' . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
}

function generate_invoice_no(): string
{
    $n = Supabase::rpc('get_next_number', ['counter_id' => 'invoice']);
    if ($n === false || $n === null) { return app_setting('invoice_prefix','INV') . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)); } // fallback
    return app_setting('invoice_prefix','INV') . '-' . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
}

function generate_pos_sale_no(): string
{
    $n = Supabase::rpc('get_next_number', ['counter_id' => 'pos_sale']);
    if ($n === false || $n === null) { return app_setting('pos_prefix','POS') . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)); } // fallback
    return app_setting('pos_prefix','POS') . '-' . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
}

function redirect(string $path)
{
    header('Location: ' . base_url($path));
    exit;
}

const TOOTH_CONDITIONS = [
    'healthy' => 'Healthy',
    'cavity' => 'Cavity',
    'filling' => 'Filling',
    'extraction' => 'Extraction',
    'crown' => 'Crown',
    'root_canal' => 'Root Canal',
    'missing' => 'Missing Tooth',
];

const TREATMENT_STATUSES = [
    'none' => 'None',
    'planned' => 'Planned',
    'in_progress' => 'In Progress',
    'completed' => 'Completed',
];

const PAYMENT_METHODS = [
    'cash' => 'Cash',
    'card' => 'Card',
    'zaad' => 'ZAAD',
    'edahab' => 'eDahab',
    'premier' => 'Premier Wallet',
];

function payment_method_label(string $method): string
{
    return PAYMENT_METHODS[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

/** URL of an 80mm thermal receipt page. Adds auto=1 when "auto print" is enabled in Settings. */
function thermal_url(string $page, array $params = []): string
{
    if (!empty(clinic_settings()['receipt_auto_print'])) { $params['auto'] = 1; }
    return base_url($page . '?' . http_build_query($params));
}

/** One left/right row on the thermal receipt (long left text wraps, right text never does). */
function thermal_row(string $left, string $right = '', bool $bold = false): string
{
    return '<div class="row' . ($bold ? ' bold' : '') . '"><span class="l">' . h($left) . '</span><span class="r">' . h($right) . '</span></div>';
}

const RECEIPT_PAPERS = ['58mm' => '58mm thermal', '80mm' => '80mm thermal', 'a4' => 'A4 page'];

/** Paper size for the current receipt: ?paper= override (if valid), else the Settings default. */
function receipt_paper(): string
{
    $p = $_GET['paper'] ?? clinic_settings()['receipt_paper'];
    return isset(RECEIPT_PAPERS[$p]) ? $p : '80mm';
}

/** Reads one option from the Settings hub (app_settings table). Returns $default if unset or table missing. */
function app_setting(string $key, $default = null)
{
    static $all = null;
    if ($all === null) {
        $all = [];
        foreach (Supabase::select('app_settings') as $row) { $all[$row['key']] = $row['value']; }
    }
    return (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '') ? $all[$key] : $default;
}

/** Saves options to app_settings (insert or update per key). */
function app_setting_save(array $pairs): void
{
    $existing = [];
    foreach (Supabase::select('app_settings', ['select' => 'key']) as $r) { $existing[$r['key']] = true; }
    foreach ($pairs as $k => $v) {
        if (isset($existing[$k])) {
            Supabase::update('app_settings', ['key' => 'eq.' . $k], ['value' => (string)$v, 'updated_at' => date('c')]);
        } else {
            Supabase::insert('app_settings', ['key' => $k, 'value' => (string)$v]);
        }
    }
}

/** Payment methods switched on in Settings -> Billing & Payments (all by default). */
function enabled_payment_methods(): array
{
    $csv = app_setting('payment_methods_enabled', '');
    if ($csv === '') { return PAYMENT_METHODS; }
    $on = array_filter(explode(',', $csv));
    $out = array_intersect_key(PAYMENT_METHODS, array_flip($on));
    return $out ?: PAYMENT_METHODS;
}

function password_min_length(): int
{
    return max(4, (int)app_setting('password_min_length', '6'));
}

/** Applies runtime options (timezone) once per request. */
function apply_runtime_settings(): void
{
    $tz = app_setting('timezone', '');
    if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) { date_default_timezone_set($tz); }
}

/** Categories = every category used by a service + extra ones created here (kept in app_settings). */
function category_list(): array
{
    $used = [];
    foreach (Supabase::select('services', ['select' => 'category']) as $r) {
        $c = trim((string)$r['category']);
        if ($c !== '') { $used[$c] = ($used[$c] ?? 0) + 1; }
    }
    foreach (array_filter(array_map('trim', explode(',', app_setting('extra_categories', '')))) as $c) {
        if (!isset($used[$c])) { $used[$c] = 0; }
    }
    ksort($used, SORT_NATURAL | SORT_FLAG_CASE);
    return $used;
}

/**
 * Figures for the Daily Activities report, counted from the system for one date (clinic-wide).
 * Returns the five numbers plus name lists for the "details" section.
 */
function daily_stats(string $date): array
{
    $appts = Supabase::select('appointments', [
        'appointment_date' => 'eq.' . $date,
        'select' => 'id,patient_id,status,patients(full_name)',
    ]);
    $treatments = Supabase::select('treatments', [
        'treatment_date' => 'eq.' . $date,
        'select' => 'id,patient_id,patients(full_name)',
    ]);

    // Contacts logged that local day (message log: sent reminders + WhatsApp/SMS buttons that were opened)
    $tz = date('P');
    $logs = Supabase::select('notification_log', [
        'created_at' => 'gte.' . $date . 'T00:00:00' . $tz,
        'and' => '(created_at.lte.' . $date . 'T23:59:59' . $tz . ')',
        'status' => 'eq.sent',
        'kind' => 'in.(appointment,braces)',
        'select' => 'patient_id,patients(full_name)',
    ]);

    $attended = []; $missed = []; $contacted = []; $scheduled = 0;
    $today = date('Y-m-d');
    foreach ($appts as $a) {
        $name = $a['patients']['full_name'] ?? '—';
        if ($a['status'] !== 'cancelled') { $scheduled++; }
        if ($a['status'] === 'completed') { $attended[$a['patient_id']] = $name; }
        $unresolvedPast = $date < $today && in_array($a['status'], ['booked', 'confirmed', 'rescheduled'], true);
        if ($a['status'] === 'no_show' || $unresolvedPast) { $missed[$a['id']] = $name; }
    }
    foreach ($treatments as $t) { $attended[$t['patient_id']] = $t['patients']['full_name'] ?? '—'; }
    foreach ($logs as $l) { if (!empty($l['patient_id'])) { $contacted[$l['patient_id']] = $l['patients']['full_name'] ?? '—'; } }

    return [
        'patients_attended' => count($attended),
        'appointments_scheduled' => $scheduled,
        'followups_contacted' => count($contacted),
        'missed_appointments' => count($missed),
        'treatments_assisted' => count($treatments),
        'lists' => ['attended' => array_values($attended), 'missed' => array_values($missed), 'contacted' => array_values($contacted)],
    ];
}

/** What each role is for, what it can and cannot do, and which extra registration fields it needs. */
function role_info(): array
{
    return [
        'superior' => [
            'summary' => 'Top-level owner of the system.',
            'can' => ['Everything a Manager can do', 'Create, edit, disable and delete Managers and other Superiors', 'See and pay every staff salary'],
            'cannot' => [],
            'extra' => [],
        ],
        'manager' => [
            'summary' => 'Runs the clinic day to day.',
            'can' => ['Register and manage Doctors, Nurses and Cashiers', 'Appointments, prices, pharmacy items, expenses, reports', 'Payroll for Doctors, Nurses and Cashiers', 'Settings, backup and audit logs'],
            'cannot' => ['Create or edit Managers and Superiors', 'See Manager or Superior salaries'],
            'extra' => [],
        ],
        'doctor' => [
            'summary' => 'Treats patients.',
            'can' => ['Own appointments and patient records', 'Dental chart, diagnosis and treatment plans', 'Prescriptions, braces tracking, lab orders', 'Attendance and daily activities report'],
            'cannot' => ['Staff management, settings, backup', 'Billing, POS and payroll'],
            'extra' => ['specialization', 'license_number', 'license_expiry'],
        ],
        'nurse' => [
            'summary' => 'Prepares patients and assists treatment.',
            'can' => ['Today\'s appointments and patient information', 'Record vital signs and patient preparation', 'View braces follow-ups and the dental chart', 'Attendance and daily activities report'],
            'cannot' => ['Create or change a diagnosis', 'Prescribe medication', 'Billing, staff management, settings'],
            'extra' => ['license_number', 'license_expiry'],
        ],
        'cashier' => [
            'summary' => 'Front desk and payments.',
            'can' => ['Register and search patients', 'Book, confirm and cancel appointments', 'Billing, POS, receipts and cashier shifts', 'Own sales report'],
            'cannot' => ['Diagnosis or prescriptions', 'Prices, expenses, staff management, settings'],
            'extra' => [],
        ],
    ];
}

function generate_staff_code(): string
{
    $n = Supabase::rpc('get_next_number', ['counter_id' => 'staff']);
    if ($n === false || $n === null) { return 'EMP-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)); }
    return 'EMP-' . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
}

/** Folders that hold app modules (the sidebar and base_url() use this list). */
function module_folders(): array
{
    return ['patients', 'dental-chart', 'appointments', 'treatments', 'billing', 'pharmacy', 'staff', 'expenses', 'pos', 'settings', 'backup', 'shifts', 'attendance', 'prescriptions', 'lab', 'nurse', 'audit', 'reports', 'payroll', 'braces'];
}

const MARITAL_STATUSES = ['Single', 'Married', 'Divorced', 'Widowed'];
const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

/** The personal-information fields of a staff profile that are saved from the edit / registration forms. */
function staff_personal_fields(array $in): array
{
    return [
        'national_id' => trim($in['national_id'] ?? ''),
        'nationality' => trim($in['nationality'] ?? ''),
        'marital_status' => in_array($in['marital_status'] ?? '', MARITAL_STATUSES, true) ? $in['marital_status'] : '',
        'blood_group' => in_array($in['blood_group'] ?? '', BLOOD_GROUPS, true) ? $in['blood_group'] : '',
        'qualification' => trim($in['qualification'] ?? ''),
        'emergency_contact_name' => trim($in['emergency_contact_name'] ?? ''),
        'emergency_contact_phone' => trim($in['emergency_contact_phone'] ?? ''),
        'emergency_contact_relation' => trim($in['emergency_contact_relation'] ?? ''),
    ];
}

/** "28 years" from a birth date, or '' when unknown. */
function age_from(?string $dob): string
{
    if (!$dob) return '';
    $y = (int)date_diff(date_create($dob), date_create('today'))->y;
    return $y . ' year' . ($y === 1 ? '' : 's');
}

/** "2 years 3 months" since a date, or ''. */
function service_length(?string $from): string
{
    if (!$from) return '';
    $d = date_diff(date_create($from), date_create('today'));
    if ($d->invert) return '';
    $parts = [];
    if ($d->y) $parts[] = $d->y . ' year' . ($d->y === 1 ? '' : 's');
    if ($d->m) $parts[] = $d->m . ' month' . ($d->m === 1 ? '' : 's');
    return $parts ? implode(' ', $parts) : 'Less than a month';
}

