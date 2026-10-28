<?php
require_once __DIR__ . '/../config/config.php';
require_manager();

$allTables = [
    'patients', 'dental_chart', 'appointments', 'treatments', 'invoices',
    'payments', 'inventory_items', 'inventory_movements', 'expenses',
    'pos_sales', 'pos_sale_items', 'staff',
];

$table = $_GET['table'] ?? '';

if ($table === 'all') {
    $data = [];
    foreach ($allTables as $t) {
        $rows = Supabase::select($t);
        if ($t === 'staff') {
            foreach ($rows as &$r) { unset($r['password_hash']); }
        }
        $data[$t] = $rows;
    }
    $filename = 'dental_clinic_full_backup_' . date('Y-m-d') . '.json';
} elseif (in_array($table, $allTables, true)) {
    $data = Supabase::select($table);
    if ($table === 'staff') {
        foreach ($data as &$r) { unset($r['password_hash']); }
    }
    $filename = $table . '_backup_' . date('Y-m-d') . '.json';
} else {
    http_response_code(400);
    exit('Invalid table.');
}

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo json_encode($data, JSON_PRETTY_PRINT);
