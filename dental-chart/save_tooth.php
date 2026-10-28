<?php
require_once __DIR__ . '/../config/config.php';
require_login();
header('Content-Type: application/json');

if (!can_diagnose()) {
    echo json_encode(['ok' => false, 'error' => 'Only a Doctor or Manager can change a diagnosis.']);
    exit;
}

$patientId = $_POST['patient_id'] ?? '';
$tooth = (int)($_POST['tooth_number'] ?? 0);
$condition = $_POST['condition'] ?? 'healthy';
$status = $_POST['treatment_status'] ?? 'none';
$notes = trim($_POST['notes'] ?? '');

if (!$patientId || $tooth < 1 || $tooth > 32) {
    echo json_encode(['ok' => false, 'error' => 'Invalid patient or tooth number']);
    exit;
}

$existing = Supabase::selectOne('dental_chart', [
    'patient_id' => 'eq.' . $patientId,
    'tooth_number' => 'eq.' . $tooth,
]);

$payload = [
    'condition' => $condition,
    'treatment_status' => $status,
    'notes' => $notes,
    'updated_at' => date('c'),
];

if ($existing) {
    $ok = Supabase::update('dental_chart', ['id' => 'eq.' . $existing['id']], $payload);
} else {
    $payload['patient_id'] = $patientId;
    $payload['tooth_number'] = $tooth;
    $ok = Supabase::insert('dental_chart', $payload);
}

echo json_encode(['ok' => (bool)$ok]);
