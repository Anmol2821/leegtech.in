<?php
/*
 * Leegtech enquiry endpoint.
 * Saves each enquiry-form submission to a SQLite database stored OUTSIDE public_html
 * (domains/leegtech.in/leads-data/leads.sqlite) and mirrors it to leads.csv in the same folder,
 * so the owner can download it from hPanel > File Manager. The browser then continues to WhatsApp.
 * No credentials are needed: the database is a file owned by the hosting account.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function out(int $code, array $body): void { http_response_code($code); echo json_encode($body); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(405, ['ok' => false, 'error' => 'method']);

// Only accept submissions from our own pages.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$allowed = '#^https://(www\.)?leegtech\.in(/|$)#';
if (!preg_match($allowed, $origin ?: $referer)) out(403, ['ok' => false, 'error' => 'origin']);

$raw = file_get_contents('php://input', false, null, 0, 20000) ?: '';
$in = [];
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') !== false) { $in = json_decode($raw, true) ?: []; } else { parse_str($raw, $in); }

$s = function (string $k, int $max = 200) use ($in): string {
    $v = isset($in[$k]) && is_scalar($in[$k]) ? (string)$in[$k] : '';
    $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '');
    return mb_substr($v, 0, $max);
};

// Honeypot: bots fill the hidden field. Pretend success so they learn nothing.
if ($s('company_website') !== '') out(200, ['ok' => true, 'id' => 'hp']);

$lead = [
    'event_id' => preg_match('/^[0-9a-z-]{6,40}$/', $s('event_id', 40)) ? $s('event_id', 40) : bin2hex(random_bytes(8)),
    'name' => $s('name', 120),
    'phone' => $s('phone', 25),
    'email' => strtolower($s('email', 160)),
    'service' => $s('service', 160),
    'startup_status' => $s('startup_status', 80),
    'city' => $s('city', 80),
    'message' => $s('message', 1000),
    'page_url' => $s('page_url', 500),
    'landing_page' => $s('landing_page', 500),
    'referrer' => $s('referrer', 300),
    'utm_source' => $s('utm_source'), 'utm_medium' => $s('utm_medium'), 'utm_campaign' => $s('utm_campaign'),
    'utm_term' => $s('utm_term'), 'utm_content' => $s('utm_content'),
    'gclid' => $s('gclid', 300), 'fbclid' => $s('fbclid', 300),
    'consent' => $s('consent', 10) === 'yes' ? 'yes' : 'no',
    'is_test' => $s('_test', 1) === '1' ? 1 : 0,
];

// Server-side validation (mirrors the form).
$digits = preg_replace('/\D/', '', $lead['phone']);
if ($lead['name'] === '' || !preg_match('/^[+]?[0-9 ()-]{10,16}$/', $lead['phone']) || strlen($digits) < 10
    || !filter_var($lead['email'], FILTER_VALIDATE_EMAIL) || $lead['service'] === '' || $lead['consent'] !== 'yes') {
    out(422, ['ok' => false, 'error' => 'invalid']);
}

$dir = dirname(__DIR__, 2) . '/leads-data';
if (!is_dir($dir) && !mkdir($dir, 0700, true)) out(500, ['ok' => false, 'error' => 'storage']);
if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n"); // defence in depth if the folder is ever exposed

try {
    $db = new PDO('sqlite:' . $dir . '/leads.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec('CREATE TABLE IF NOT EXISTS leads (
        id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL, event_id TEXT UNIQUE,
        name TEXT, phone TEXT, email TEXT, service TEXT, startup_status TEXT, city TEXT, message TEXT,
        page_url TEXT, landing_page TEXT, referrer TEXT,
        utm_source TEXT, utm_medium TEXT, utm_campaign TEXT, utm_term TEXT, utm_content TEXT, gclid TEXT, fbclid TEXT,
        consent TEXT, user_agent TEXT, ip_hash TEXT, is_test INTEGER DEFAULT 0)');
    @chmod($dir . '/leads.sqlite', 0600);

    // Privacy-friendly rate limit: hash the IP with a random per-site salt kept in the data folder.
    $saltFile = $dir . '/.salt';
    if (!is_file($saltFile)) { file_put_contents($saltFile, bin2hex(random_bytes(16)), LOCK_EX); @chmod($saltFile, 0600); }
    $ipHash = hash('sha256', trim((string)file_get_contents($saltFile)) . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
    $st = $db->prepare("SELECT COUNT(*) FROM leads WHERE ip_hash = ? AND created_at > ?");
    $st->execute([$ipHash, gmdate('Y-m-d\TH:i:s\Z', time() - 600)]);
    if ((int)$st->fetchColumn() >= 5) out(429, ['ok' => false, 'error' => 'rate']);

    $row = $lead + [
        'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
        'ip_hash' => $ipHash,
    ];
    $cols = array_keys($row);
    $ins = $db->prepare('INSERT OR IGNORE INTO leads (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')');
    $ins->execute(array_values($row));
    $isNew = $ins->rowCount() > 0;
} catch (Throwable $e) {
    error_log('lead.php: ' . $e->getMessage());
    out(500, ['ok' => false, 'error' => 'db']);
}

// Mirror real leads to a CSV the owner can download from File Manager (IST time for readability).
if ($isNew && !$row['is_test']) {
    $csv = $dir . '/leads.csv';
    $head = ['Date (IST)', 'Name', 'Phone', 'Email', 'Service', 'Startup status', 'City', 'Message', 'Page URL', 'Landing page',
        'Referrer', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'Event ID'];
    $ist = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i');
    $vals = [$ist, $row['name'], $row['phone'], $row['email'], $row['service'], $row['startup_status'], $row['city'], $row['message'],
        $row['page_url'], $row['landing_page'], $row['referrer'], $row['utm_source'], $row['utm_medium'], $row['utm_campaign'],
        $row['utm_term'], $row['utm_content'], $row['gclid'], $row['fbclid'], $row['event_id']];
    // Stop spreadsheet formula injection.
    $vals = array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : $v, $vals);
    $new = !is_file($csv);
    if ($fh = @fopen($csv, 'a')) {
        if (flock($fh, LOCK_EX)) {
            if ($new) { fwrite($fh, "\xEF\xBB\xBF"); fputcsv($fh, $head, ',', '"', ''); }
            fputcsv($fh, $vals, ',', '"', '');
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        @chmod($csv, 0600);
    }
}

out(200, ['ok' => true, 'id' => $row['event_id']]);
