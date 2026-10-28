<?php
/**
 * Settings hub definition. Each section becomes a card on settings/index.php and,
 * unless it has a 'href', a form on settings/section.php?s=<key>.
 *
 * Field 'store':  'clinic' = column in clinic_settings,  'kv' = row in app_settings.
 * Field 'type':   text | email | number | select | checkbox | color | textarea | file | multicheck
 */
function settings_timezones(): array
{
    return [
        'Africa/Mogadishu' => 'Mogadishu (EAT, UTC+3)', 'Africa/Nairobi' => 'Nairobi (EAT)',
        'Africa/Addis_Ababa' => 'Addis Ababa (EAT)', 'Africa/Djibouti' => 'Djibouti (EAT)',
        'Asia/Riyadh' => 'Riyadh (UTC+3)', 'Asia/Dubai' => 'Dubai (UTC+4)',
        'Europe/London' => 'London', 'UTC' => 'UTC',
    ];
}

function settings_sections(): array
{
    $yesno = ['1' => 'Yes', '0' => 'No'];
    return [
        'clinic_profile' => [
            'title' => 'Clinic Profile', 'icon' => '🏢', 'tint' => '#DBEAFE',
            'desc' => 'Clinic name, logo, address, phone, email and other details.',
            'fields' => [
                ['key' => 'clinic_name', 'store' => 'clinic', 'type' => 'text', 'label' => 'Clinic name', 'required' => true],
                ['key' => 'logo_url', 'store' => 'clinic', 'type' => 'file', 'label' => 'Clinic logo', 'input' => 'logo', 'full' => true],
                ['key' => 'clinic_address', 'store' => 'clinic', 'type' => 'text', 'label' => 'Address'],
                ['key' => 'clinic_phone', 'store' => 'clinic', 'type' => 'text', 'label' => 'Phone'],
                ['key' => 'clinic_email', 'store' => 'kv', 'type' => 'email', 'label' => 'Email'],
            ],
        ],
        'general' => [
            'title' => 'General Settings', 'icon' => '⚙️', 'tint' => '#EDE9FE',
            'desc' => 'Timezone and currency preferences used across the system.',
            'fields' => [
                ['key' => 'timezone', 'store' => 'kv', 'type' => 'select', 'label' => 'Timezone', 'options' => settings_timezones(), 'default' => APP_TIMEZONE],
                ['key' => 'currency_symbol', 'store' => 'clinic', 'type' => 'text', 'label' => 'Currency symbol', 'required' => true],
                ['key' => 'currency_code', 'store' => 'clinic', 'type' => 'text', 'label' => 'Currency code', 'placeholder' => 'USD'],
            ],
        ],
        'appearance' => [
            'title' => 'Appearance & Theme', 'icon' => '🎨', 'tint' => '#FCE7F3',
            'desc' => 'Light/dark mode, sidebar colors and compact layout.',
            'fields' => [
                ['key' => 'theme', 'store' => 'kv', 'type' => 'select', 'label' => 'Theme', 'options' => ['light' => 'Light', 'dark' => 'Dark'], 'default' => 'light'],
                ['key' => 'sidebar_color', 'store' => 'kv', 'type' => 'color', 'label' => 'Sidebar color', 'default' => '#16302C'],
                ['key' => 'compact', 'store' => 'kv', 'type' => 'checkbox', 'label' => 'Compact layout (tighter tables and cards)', 'full' => true],
            ],
        ],
        'clinical' => [
            'title' => 'Clinical Settings', 'icon' => '🩺', 'tint' => '#D1FAE5',
            'desc' => 'Default clinical options and treatment configuration.',
            'fields' => [
                ['key' => 'default_treatment_status', 'store' => 'kv', 'type' => 'select', 'label' => 'Default status for new treatments',
                 'options' => ['planned' => 'Planned', 'in_progress' => 'In progress', 'completed' => 'Completed'], 'default' => 'in_progress'],
                ['key' => 'allergy_options', 'store' => 'kv', 'type' => 'textarea', 'full' => true,
                 'label' => 'Allergy choices on the patient form (comma separated)',
                 'default' => 'None, Penicillin, Latex, Local Anesthetic, Aspirin / NSAIDs, Iodine, Other'],
            ],
        ],
        'categories' => [
            'title' => 'Treatment Categories', 'icon' => '🦷', 'tint' => '#FFEDD5',
            'desc' => 'Manage treatment categories like braces, cleaning, extraction, filling and more.',
            'href' => 'settings/categories.php',
        ],
        'appointments' => [
            'title' => 'Appointment Settings', 'icon' => '📅', 'tint' => '#DBEAFE',
            'desc' => 'Appointment duration, working hours and booking rules.',
            'fields' => [
                ['key' => 'opening_time', 'store' => 'kv', 'type' => 'time', 'label' => 'Clinic opens', 'default' => '08:00'],
                ['key' => 'closing_time', 'store' => 'kv', 'type' => 'time', 'label' => 'Clinic closes', 'default' => '20:00'],
                ['key' => 'default_duration', 'store' => 'kv', 'type' => 'number', 'label' => 'Appointment duration (minutes)', 'default' => '30', 'min' => 5],
            ],
        ],
        'braces' => [
            'title' => 'Braces Tracking', 'icon' => '🔗', 'tint' => '#EDE9FE',
            'desc' => 'Follow-up interval, next visit and overdue reminders.',
            'fields' => [
                ['key' => 'braces_default_interval', 'store' => 'kv', 'type' => 'number', 'label' => 'Default follow-up interval (days)', 'default' => '30', 'min' => 1],
                ['key' => 'braces_alerts', 'store' => 'kv', 'type' => 'select', 'label' => 'Show Due Today / Overdue alerts on the Dashboard', 'options' => $yesno, 'default' => '1'],
            ],
        ],
        'billing' => [
            'title' => 'Billing & Payments', 'icon' => '💳', 'tint' => '#D1FAE5',
            'desc' => 'Payment methods, secondary currency and exchange rate.',
            'fields' => [
                ['key' => 'payment_methods_enabled', 'store' => 'kv', 'type' => 'multicheck', 'label' => 'Payment methods accepted', 'full' => true,
                 'options' => PAYMENT_METHODS, 'default' => implode(',', array_keys(PAYMENT_METHODS))],
                ['key' => 'secondary_currency_name', 'store' => 'clinic', 'type' => 'text', 'label' => 'Secondary currency', 'placeholder' => 'Somali Shilling'],
                ['key' => 'secondary_currency_code', 'store' => 'clinic', 'type' => 'text', 'label' => 'Secondary currency code', 'placeholder' => 'SOS'],
                ['key' => 'exchange_rate', 'store' => 'clinic', 'type' => 'number', 'label' => 'Exchange rate (1 main = ? secondary)', 'step' => '0.0001', 'placeholder' => '569.21', 'full' => true],
            ],
        ],
        'receipt' => [
            'title' => 'Receipt Printer Settings', 'icon' => '🖨️', 'tint' => '#FCE7F3',
            'desc' => '58mm / 80mm thermal or A4 receipt layout and print options.',
            'fields' => [
                ['key' => 'receipt_paper', 'store' => 'clinic', 'type' => 'select', 'label' => 'Printer / paper model', 'options' => RECEIPT_PAPERS, 'default' => '80mm'],
                ['key' => 'receipt_auto_print', 'store' => 'clinic', 'type' => 'checkbox', 'label' => 'Open the print dialog automatically'],
                ['key' => 'receipt_footer', 'store' => 'clinic', 'type' => 'text', 'label' => 'Receipt footer message', 'full' => true, 'placeholder' => 'Thank you for choosing us!'],
            ],
        ],
        'invoice' => [
            'title' => 'Invoice Templates', 'icon' => '🧾', 'tint' => '#CCFBF1',
            'desc' => 'Invoice numbering prefixes and terms printed on invoices.',
            'fields' => [
                ['key' => 'invoice_prefix', 'store' => 'kv', 'type' => 'text', 'label' => 'Invoice number prefix', 'default' => 'INV'],
                ['key' => 'patient_prefix', 'store' => 'kv', 'type' => 'text', 'label' => 'Patient ID prefix', 'default' => 'PT'],
                ['key' => 'pos_prefix', 'store' => 'kv', 'type' => 'text', 'label' => 'POS receipt prefix', 'default' => 'POS'],
                ['key' => 'invoice_terms', 'store' => 'kv', 'type' => 'textarea', 'full' => true, 'label' => 'Terms / notes printed on invoices', 'placeholder' => 'e.g. Payment is due on the day of treatment.'],
            ],
        ],
        'users' => [
            'title' => 'User & Permissions', 'icon' => '👥', 'tint' => '#FEF3C7',
            'desc' => 'Manage staff roles, access control and user permissions.',
            'href' => 'staff/index.php',
        ],
        'security' => [
            'title' => 'Security Settings', 'icon' => '🛡️', 'tint' => '#DBEAFE',
            'desc' => 'Session timeout, password policy and audit logging.',
            'fields' => [
                ['key' => 'session_timeout_minutes', 'store' => 'kv', 'type' => 'number', 'label' => 'Auto-logout after idle minutes (0 = never)', 'default' => '0', 'min' => 0],
                ['key' => 'password_min_length', 'store' => 'kv', 'type' => 'number', 'label' => 'Minimum password length for new/reset passwords', 'default' => '6', 'min' => 4],
                ['key' => 'audit_enabled', 'store' => 'kv', 'type' => 'select', 'label' => 'Audit logging', 'options' => ['1' => 'On', '0' => 'Off'], 'default' => '1'],
            ],
        ],
        'notifications' => [
            'title' => 'Notification Settings', 'icon' => '🔔', 'tint' => '#D1FAE5',
            'desc' => 'Appointment and braces reminders by SMS, WhatsApp or Email.',
            'after' => 'settings/partials/notify_tools.php',
            'fields' => [
                ['key' => 'notify_channel', 'store' => 'kv', 'type' => 'select', 'label' => 'Send reminders by', 'options' => ['system' => 'System alert only (no message)', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'email' => 'Email'], 'default' => 'system'],
                ['key' => 'reminder_days_before', 'store' => 'kv', 'type' => 'number', 'label' => 'Send reminder this many days before the visit', 'default' => '1', 'min' => 0],
                ['key' => 'default_country_code', 'store' => 'kv', 'type' => 'text', 'label' => 'Default country code for local numbers', 'default' => '+252', 'placeholder' => '+252'],
                ['key' => 'appointment_template', 'store' => 'kv', 'type' => 'textarea', 'full' => true, 'label' => 'Appointment reminder message', 'default' => DEFAULT_APPOINTMENT_TEMPLATE,
                 'help' => 'Placeholders: {patient} {date} {time} {clinic} {clinic_phone}'],
                ['key' => 'braces_template', 'store' => 'kv', 'type' => 'textarea', 'full' => true, 'label' => 'Braces follow-up message', 'default' => DEFAULT_BRACES_TEMPLATE,
                 'help' => 'Placeholders: {patient} {date} {clinic} {clinic_phone}'],
            ],
        ],
        'backup' => [
            'title' => 'Backup & Database', 'icon' => '🗄️', 'tint' => '#EDE9FE',
            'desc' => 'Backup management, database export and system tools.',
            'href' => 'backup/index.php',
        ],
    ];
}

/** Current value of a field (from clinic_settings or app_settings, falling back to its default). */
function settings_value(array $f)
{
    if (($f['store'] ?? '') === 'clinic') {
        $v = clinic_settings()[$f['key']] ?? null;
        return $v ?? ($f['default'] ?? '');
    }
    return app_setting($f['key'], $f['default'] ?? '');
}
