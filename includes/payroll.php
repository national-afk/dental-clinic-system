<?php
/** Payroll helpers: salary profiles, monthly payslips, advances, payment. */

function salary_pay_methods(): array
{
    return PAYMENT_METHODS + ['bank' => 'Bank transfer'];
}

/** "2026-10" -> "2026-10-01" (falls back to the current month). */
function payroll_month_start(?string $ym): string
{
    return preg_match('/^\d{4}-\d{2}$/', (string)$ym) ? $ym . '-01' : date('Y-m-01');
}

function generate_payslip_no(): string
{
    $n = Supabase::rpc('get_next_number', ['counter_id' => 'payslip']);
    if ($n === false || $n === null) { return 'PSL-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)); }
    return 'PSL-' . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
}

/** Active staff the signed-in user may run payroll for (Managers cannot see Manager/Superior pay). */
function payroll_staff(): array
{
    $out = [];
    foreach (Supabase::select('staff', ['active' => 'eq.true', 'order' => 'full_name.asc']) as $s) {
        if (($s['employment_status'] ?? 'active') === 'terminated') continue;
        if (can_manage_staff_member($s['role'])) $out[] = $s;
    }
    return $out;
}

function payslip_recalc(string $id): void
{
    $p = Supabase::selectOne('payslips', ['id' => 'eq.' . $id]);
    if (!$p) return;
    $earn = 0.0; $ded = 0.0;
    foreach (Supabase::select('payslip_items', ['payslip_id' => 'eq.' . $id]) as $it) {
        if ($it['kind'] === 'earning') $earn += (float)$it['amount']; else $ded += (float)$it['amount'];
    }
    $gross = (float)$p['basic_salary'] + $earn;
    Supabase::update('payslips', ['id' => 'eq.' . $id], [
        'gross_pay' => round($gross, 2), 'total_deductions' => round($ded, 2), 'net_pay' => round($gross - $ded, 2),
    ]);
}

function payslip_add_item(string $payslipId, string $kind, string $label, float $amount, ?string $advanceId = null): void
{
    if ($amount <= 0) return;
    Supabase::insert('payslip_items', [
        'payslip_id' => $payslipId, 'kind' => $kind, 'label' => $label, 'amount' => round($amount, 2), 'advance_id' => $advanceId,
    ]);
}

/** Outstanding balance of an advance, not counting amounts already sitting in unpaid (draft) payslips. */
function advance_remaining(array $adv): float
{
    $inDrafts = 0.0;
    foreach (Supabase::select('payslip_items', ['advance_id' => 'eq.' . $adv['id'], 'select' => 'amount,payslips(status)']) as $it) {
        if (($it['payslips']['status'] ?? '') === 'draft') $inDrafts += (float)$it['amount'];
    }
    return max(0.0, (float)$adv['amount'] - (float)$adv['repaid'] - $inDrafts);
}

/** Creates a draft payslip from the staff member's salary profile. Returns its id or null. */
function payslip_generate(array $staff, array $profile, string $monthStart): ?string
{
    $res = Supabase::insert('payslips', [
        'payslip_no' => generate_payslip_no(),
        'staff_id' => $staff['id'],
        'period_month' => $monthStart,
        'basic_salary' => (float)$profile['basic_salary'],
        'status' => 'draft',
        'created_by' => current_staff()['id'],
    ]);
    $id = $res[0]['id'] ?? null;
    if (!$id) return null;

    payslip_add_item($id, 'earning', 'Transport allowance', (float)$profile['transport_allowance']);
    payslip_add_item($id, 'earning', 'Other allowance', (float)$profile['other_allowance']);
    payslip_add_item($id, 'deduction', 'Standard deduction', (float)$profile['standard_deduction']);

    foreach (Supabase::select('salary_advances', ['staff_id' => 'eq.' . $staff['id'], 'order' => 'advance_date.asc']) as $adv) {
        $rem = advance_remaining($adv);
        if ($rem < 0.005) continue;
        $take = (float)$adv['monthly_deduction'] > 0 ? min((float)$adv['monthly_deduction'], $rem) : $rem;
        payslip_add_item($id, 'deduction', 'Advance repayment (' . date('d M Y', strtotime($adv['advance_date'])) . ')', $take, $adv['id']);
    }
    payslip_recalc($id);
    return $id;
}

/** Marks a draft payslip paid, repays advances and records the salary as an expense. */
function payslip_mark_paid(string $id, string $method): bool
{
    $paid = Supabase::update('payslips', ['id' => 'eq.' . $id, 'status' => 'eq.draft'], [
        'status' => 'paid', 'payment_method' => $method, 'paid_at' => date('c'), 'paid_by' => current_staff()['id'],
    ]);
    if (!$paid) return false;           // already paid (or update failed) -> never double-pay
    $p = $paid[0];

    foreach (Supabase::select('payslip_items', ['payslip_id' => 'eq.' . $id, 'advance_id' => 'not.is.null']) as $it) {
        $adv = Supabase::selectOne('salary_advances', ['id' => 'eq.' . $it['advance_id']]);
        if ($adv) Supabase::update('salary_advances', ['id' => 'eq.' . $adv['id']], ['repaid' => round((float)$adv['repaid'] + (float)$it['amount'], 2)]);
    }
    $staff = Supabase::selectOne('staff', ['id' => 'eq.' . $p['staff_id']]);
    Supabase::insert('expenses', [
        'category' => 'Salaries',
        'amount' => (float)$p['net_pay'],
        'expense_date' => date('Y-m-d'),
        'notes' => $p['payslip_no'] . ' — ' . ($staff['full_name'] ?? '') . ' — ' . date('F Y', strtotime($p['period_month'])),
        'recorded_by' => current_staff()['id'],
    ]);
    return true;
}

/** Days present and hours worked (from Attendance check-ins) in a month — shown on the payslip for reference. */
function attendance_summary(string $staffId, string $monthStart): array
{
    $end = date('Y-m-d', strtotime($monthStart . ' +1 month'));
    $tz = date('P');
    $rows = Supabase::select('doctor_attendance', [
        'doctor_id' => 'eq.' . $staffId,
        'check_in' => 'gte.' . $monthStart . 'T00:00:00' . $tz,
        'and' => '(check_in.lt.' . $end . 'T00:00:00' . $tz . ')',
    ]);
    $days = []; $mins = 0;
    foreach ($rows as $r) {
        $days[date('Y-m-d', strtotime($r['check_in']))] = 1;
        if (!empty($r['check_out'])) $mins += max(0, (strtotime($r['check_out']) - strtotime($r['check_in'])) / 60);
    }
    return ['days' => count($days), 'hours' => round($mins / 60, 1)];
}
