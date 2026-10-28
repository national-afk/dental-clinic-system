<?php
/**
 * Sends all due reminders (appointments + braces follow-ups).
 *
 * Run it once a day (e.g. 08:00):
 *   Linux cron :  0 8 * * *  php /path/to/dental-clinic-system/cron/send_reminders.php
 *   Windows    :  Task Scheduler -> program "php.exe", argument "C:\xampp\htdocs\dental-clinic-system\cron\send_reminders.php"
 *   Over HTTP  :  https://your-site/cron/send_reminders.php?key=YOUR_CRON_KEY   (set CRON_KEY in config/config.php)
 */
require_once __DIR__ . '/../config/config.php';

if (PHP_SAPI !== 'cli') {
    if (CRON_KEY === '' || !hash_equals(CRON_KEY, (string)($_GET['key'] ?? ''))) { http_response_code(403); exit('Forbidden'); }
    header('Content-Type: text/plain; charset=utf-8');
}

$r = send_due_reminders();
echo date('Y-m-d H:i') . " channel={$r['channel']} sent={$r['sent']} failed={$r['failed']} skipped={$r['skipped']}\n";
foreach ($r['errors'] as $e) echo " - $e\n";
