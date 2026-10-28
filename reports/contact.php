<?php
/**
 * Logs a patient contact, then opens WhatsApp / the SMS app with the message ready.
 * Used by the 💬 WhatsApp and ✉ SMS buttons so the Daily Report can count "follow-up patients contacted".
 */
require_once __DIR__ . '/../config/config.php';
require_login();

if (!can_manage_appointments() && !can_diagnose()) { flash('error', 'You do not have permission to contact patients.'); redirect('index.php'); }

$kind = $_GET['kind'] ?? '';
$id = $_GET['id'] ?? '';
$via = $_GET['via'] ?? 'whatsapp';

if ($kind === 'appointment') {
    $rec = Supabase::selectOne('appointments', ['id' => 'eq.' . $id, 'select' => '*,patients(full_name,phone)']);
    $msg = $rec ? appointment_message($rec) : '';
    $forDate = $rec['appointment_date'] ?? null;
} elseif ($kind === 'braces') {
    $rec = Supabase::selectOne('braces_cases', ['id' => 'eq.' . $id, 'select' => '*,patients(full_name,phone)']);
    $msg = $rec ? braces_message($rec) : '';
    $forDate = $rec['next_visit_date'] ?? null;
} else { $rec = null; }

$phone = $rec['patients']['phone'] ?? '';
if (!$rec || $phone === '') { flash('error', 'Patient or phone number not found.'); redirect('index.php'); }

log_notification($kind, $rec['id'], $forDate, $rec['patient_id'] ?? null, $via === 'sms' ? 'sms_link' : 'whatsapp_link', normalize_phone($phone), $msg, true);

$url = $via === 'sms' ? sms_link($phone, $msg) : whatsapp_link($phone, $msg);
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Opening…</title>
<meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="font-family:system-ui,sans-serif;text-align:center;padding:40px;">
  <p>Opening <?= $via === 'sms' ? 'your SMS app' : 'WhatsApp' ?>…</p>
  <p><a href="<?= h($url) ?>">Click here if nothing opens</a></p>
  <script>window.location.href = <?= json_encode($url) ?>;</script>
</body></html>
