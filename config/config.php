<?php
/**
 * Central configuration.
 *
 * IMPORTANT ABOUT THE KEY BELOW
 * ------------------------------------------------------------------
 * `sb_publishable_...` is Supabase's new "publishable" (anon) key.
 * It is safe to ship to a browser, but it only works through
 * PostgREST if you enable Row Level Security policies that allow
 * the `anon` role to act (the schema.sql included in /supabase does
 * this for you, since this whole app is only reachable through the
 * PHP admin panel — never call Supabase directly from JS with this
 * key in this project).
 *
 * For a production clinic system, create a SERVICE ROLE key in
 * Supabase (Project Settings -> API) and put it in SUPABASE_KEY
 * instead, then tighten the RLS policies in schema.sql so that only
 * the service role can write. The service role key must NEVER be
 * sent to the browser — only used here, server-side, in PHP.
 */

// ---- Supabase project settings -----------------------------------
define('SUPABASE_URL', 'https://gmnhsztcgsmjfuoumiud.supabase.co');
define('SUPABASE_KEY', 'sb_publishable_mQ7iI6D0dXTgtC92ZJ3xow_Qz2iOTRr'); // anon/publishable key

// ---- App settings ---------------------------------------------------
define('APP_NAME', 'Future Dental Clinic');
define('APP_TIMEZONE', 'Africa/Mogadishu');
define('APP_CURRENCY', '$');

// ---- Message gateways for reminders (keep secrets HERE, never in the database) ----
// SMS + WhatsApp via Twilio (https://console.twilio.com). Leave empty to disable.
define('TWILIO_SID', '');             // e.g. ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
define('TWILIO_TOKEN', '');           // Auth token
define('TWILIO_SMS_FROM', '');        // your Twilio number, e.g. +1415XXXXXXX
define('TWILIO_WHATSAPP_FROM', '');   // e.g. whatsapp:+14155238886 (Twilio sandbox or approved sender)

// Email: set MAIL_FROM to enable. Fill SMTP_* to send through an SMTP server (Gmail, Outlook, hosting mail);
// leave SMTP_HOST empty to use PHP's mail() (needs a configured mail server on the host).
define('MAIL_FROM', '');              // e.g. clinic@yourdomain.com
define('SMTP_HOST', '');              // e.g. smtp.gmail.com
define('SMTP_PORT', 587);             // 587 (tls) or 465 (ssl)
define('SMTP_SECURE', 'tls');         // 'tls' | 'ssl' | ''
define('SMTP_USER', '');
define('SMTP_PASS', '');              // for Gmail use an App Password

// Secret for running reminders from a scheduler over HTTP: cron/send_reminders.php?key=THIS
define('CRON_KEY', '');

date_default_timezone_set(APP_TIMEZONE);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/supabase_client.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notify.php';
require_once __DIR__ . '/../includes/payroll.php';

apply_runtime_settings();
