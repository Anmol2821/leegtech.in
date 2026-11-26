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

if (in_array('--preview', $argv, true)) { [$subject, $html] = digest_build(); echo "<!-- $subject -->\n" . $html; exit; }
$cfg = admin_config();
if (!$cfg) { echo "Tracker not set up yet\n"; exit; }
if (!digest_enabled()) { echo "Daily email is off\n"; exit; }
$day = today();
if ((int)date('G') < 9) { echo "Before 9am IST\n"; exit; }
if (($cfg['digest_last'] ?? '') === $day) { echo "Already sent today\n"; exit; }
digest_mark_sent($day); // mark first, so a slow mail server never causes two emails
echo digest_send() ? 'Sent to ' . digest_to() . "\n" : "mail() failed\n";
