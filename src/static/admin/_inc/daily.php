<?php
/*
 * Daily summary email. Run by an hourly cron job on Hostinger (hPanel > Advanced > Cron Jobs), command:
 *   /usr/bin/php /home/u784176160/domains/leegtech.in/public_html/admin/_inc/daily.php
 * Sends at most one email per day, on the first run at or after 9am India time. Command line only.
 * Test without sending: php daily.php --preview > preview.html
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('LT_ADMIN', true);
require __DIR__ . '/app.php';
require __DIR__ . '/clients.php';
require __DIR__ . '/team.php';

if (in_array('--preview', $argv, true)) {
    $u = null;
    foreach ($argv as $arg) if (preg_match('/^--user=(\d+)$/', $arg, $mm)) $u = cdb()->query('SELECT * FROM users WHERE id = ' . (int)$mm[1])->fetch() ?: null;
    [$subject, $html] = digest_build($u); echo "<!-- $subject -->\n" . $html; exit;
}
$cfg = admin_config();
if (!$cfg) { echo "Tracker not set up yet\n"; exit; }
$day = today();
if ((int)date('G') < 9) { echo "Before 9am IST\n"; exit; }
if (!digest_enabled()) echo "Owner email is off\n";
elseif (($cfg['digest_last'] ?? '') === $day) echo "Owner: already sent today\n";
else {
    digest_mark_sent($day); // mark first, so a slow mail server never causes two emails
    echo digest_send() ? 'Sent to ' . digest_to() . "\n" : "mail() failed\n";
}
// Each active team member with an email gets their own summary (only their work, no amounts).
$db = cdb();
foreach ($db->query("SELECT * FROM users WHERE active = 1 AND email IS NOT NULL AND email != ''")->fetchAll() as $u) {
    if (($u['digest_last'] ?? '') === $day) continue;
    $db->prepare('UPDATE users SET digest_last = ? WHERE id = ?')->execute([$day, $u['id']]);
    echo (digest_send(null, $u) ? 'Sent to ' : 'mail() failed for ') . $u['name'] . "\n";
}
