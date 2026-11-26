<?php
/*
 * Leegtech lead tracker: shared code (database, login, helpers).
 * Data lives in the same private folder as /api/lead.php: domains/leegtech.in/leads-data/ (outside public_html).
 *   leads.sqlite  website enquiries (written by /api/lead.php) + leads added here, notes, login sessions
 *   admin.json    the admin password hash (created on first setup)
 * This folder is blocked from the web by _inc/.htaccess; only /admin/index.php includes it.
 */
declare(strict_types=1);
if (!defined('LT_ADMIN')) { http_response_code(404); exit; }

date_default_timezone_set('Asia/Kolkata');
require __DIR__ . '/config.php';

const STAGES = ['New', 'Contacted', 'Consulted', 'Quoted', 'Won', 'Lost'];
const OPEN_STAGES = ['New', 'Contacted', 'Consulted', 'Quoted'];
const SOURCES = ['Meta ad (WhatsApp)', 'Website form', 'Google', 'Referral', 'Phone call', 'Walk-in', 'Existing client', 'Other'];
const PAY_STATUSES = ['Pending', 'Advance received', 'Partially paid', 'Fully paid'];
const SESSION_DAYS = 30;
const COOKIE = 'lt_admin';

function data_dir(): string {
    $d = getenv('LT_DATA_DIR') ?: dirname(__DIR__, 3) . '/leads-data';
    if (!is_dir($d) && !mkdir($d, 0700, true)) fail('Storage folder is missing and could not be created.');
    if (!is_file($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "Require all denied\n");
    return $d;
}

function fail(string $msg, int $code = 500): void { http_response_code($code); echo '<!doctype html><meta charset="utf-8"><p>' . h($msg) . '</p>'; exit; }

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function now_utc(): string { return gmdate('Y-m-d\TH:i:s\Z'); }

/** Last 10 digits of an Indian mobile number, or all digits for other numbers (used for duplicates and WhatsApp links). */
function phone_key(string $phone): string {
    $d = preg_replace('/\D/', '', $phone) ?? '';
    if (strlen($d) === 12 && str_starts_with($d, '91')) return substr($d, 2);
    if (strlen($d) === 11 && $d[0] === '0') return substr($d, 1);
    return $d;
}

function wa_number(string $phone): string {
    $k = phone_key($phone);
    return strlen($k) === 10 ? '91' . $k : $k;
}

function db(): PDO {
    static $db = null;
    if ($db) return $db;
    $file = data_dir() . '/leads.sqlite';
    try {
        $db = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    } catch (Throwable $e) { error_log('admin db: ' . $e->getMessage()); fail('Could not open the leads database.'); }
    $db->exec('PRAGMA busy_timeout = 5000');
    migrate($db);
    @chmod($file, 0600);
    return $db;
}

function migrate(PDO $db): void {
    // Same table /api/lead.php creates, so either side can run first.
    $db->exec('CREATE TABLE IF NOT EXISTS leads (
        id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL, event_id TEXT UNIQUE,
        name TEXT, phone TEXT, email TEXT, service TEXT, startup_status TEXT, city TEXT, message TEXT,
        page_url TEXT, landing_page TEXT, referrer TEXT,
        utm_source TEXT, utm_medium TEXT, utm_campaign TEXT, utm_term TEXT, utm_content TEXT, gclid TEXT, fbclid TEXT,
        consent TEXT, user_agent TEXT, ip_hash TEXT, is_test INTEGER DEFAULT 0)');
    $have = array_column($db->query('PRAGMA table_info(leads)')->fetchAll(), 'name');
    $add = [
        'stage' => "TEXT DEFAULT 'New'", 'source' => "TEXT DEFAULT 'Website form'", 'ad_code' => 'TEXT',
        'quoted_amount' => 'INTEGER', 'follow_up' => 'TEXT', 'lost_reason' => 'TEXT', 'won_at' => 'TEXT',
        'updated_at' => 'TEXT', 'phone_key' => 'TEXT', 'line' => 'TEXT', 'payment_status' => "TEXT DEFAULT 'Pending'", 'received' => 'INTEGER',
    ];
    foreach ($add as $col => $def) if (!in_array($col, $have, true)) $db->exec("ALTER TABLE leads ADD COLUMN $col $def");
    $db->exec('CREATE TABLE IF NOT EXISTS activity (id INTEGER PRIMARY KEY AUTOINCREMENT, lead_id INTEGER NOT NULL,
        created_at TEXT NOT NULL, kind TEXT NOT NULL, text TEXT)');
    $db->exec('CREATE INDEX IF NOT EXISTS activity_lead ON activity(lead_id)');
    $db->exec('CREATE TABLE IF NOT EXISTS admin_sessions (token_hash TEXT PRIMARY KEY, csrf TEXT NOT NULL,
        created_at TEXT NOT NULL, expires_at INTEGER NOT NULL, user_agent TEXT)');
    $db->exec('CREATE TABLE IF NOT EXISTS admin_login_fails (ip_hash TEXT NOT NULL, at INTEGER NOT NULL)');
    // Website leads arrive without phone_key: fill it in.
    $rows = $db->query('SELECT id, phone FROM leads WHERE phone_key IS NULL')->fetchAll();
    if ($rows) {
        $up = $db->prepare('UPDATE leads SET phone_key = ? WHERE id = ?');
        foreach ($rows as $r) $up->execute([phone_key((string)$r['phone']), $r['id']]);
    }
}

/* ---------------- Login ---------------- */

function admin_file(): string { return data_dir() . '/admin.json'; }

function admin_config(): ?array {
    $f = admin_file();
    if (!is_file($f)) return null;
    $j = json_decode((string)file_get_contents($f), true);
    return is_array($j) && !empty($j['password_hash']) ? $j : null;
}

function save_password(string $password): void {
    $cfg = admin_config() ?? [];
    $cfg['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    $cfg['updated_at'] = now_utc();
    $f = admin_file();
    if (file_put_contents($f, json_encode($cfg, JSON_PRETTY_PRINT), LOCK_EX) === false) fail('Could not save the password.');
    @chmod($f, 0600);
}

/** Names of the business's WhatsApp numbers ("lines"), editable in Settings. */
function wa_lines(): array {
    $l = admin_config()['lines'] ?? null;
    return is_array($l) && $l ? array_values($l) : ['WhatsApp 1', 'WhatsApp 2'];
}

function save_lines(array $lines): void {
    $cfg = admin_config();
    if (!$cfg) return;
    $cfg['lines'] = $lines;
    $f = admin_file();
    if (file_put_contents($f, json_encode($cfg, JSON_PRETTY_PRINT), LOCK_EX) === false) fail('Could not save settings.');
    @chmod($f, 0600);
}

function line_select(?string $current, bool $withBlank = true): string {
    $o = '<select name="line">' . ($withBlank ? '<option value="">Not WhatsApp / not known</option>' : '');
    $found = !$current;
    foreach (wa_lines() as $l) { $sel = $l === $current; $found = $found || $sel; $o .= '<option' . ($sel ? ' selected' : '') . '>' . h($l) . '</option>'; }
    if (!$found) $o .= '<option selected>' . h((string)$current) . '</option>';
    return $o . '</select>';
}

function password_problem(string $p, string $p2): ?string {
    if (strlen($p) < 10) return 'Use at least 10 characters.';
    if ($p !== $p2) return 'The two passwords do not match.';
    return null;
}

function ip_hash(): string {
    $salt = data_dir() . '/.salt';
    if (!is_file($salt)) { file_put_contents($salt, bin2hex(random_bytes(16)), LOCK_EX); @chmod($salt, 0600); }
    return hash('sha256', trim((string)file_get_contents($salt)) . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
}

/** True when too many wrong passwords recently (5 per device or 30 overall in 15 minutes). */
function login_locked(): bool {
    $db = db();
    $since = time() - 900;
    $db->prepare('DELETE FROM admin_login_fails WHERE at < ?')->execute([time() - 86400]);
    $st = $db->prepare('SELECT COUNT(*) FROM admin_login_fails WHERE ip_hash = ? AND at > ?');
    $st->execute([ip_hash(), $since]);
    if ((int)$st->fetchColumn() >= 5) return true;
    $st = $db->prepare('SELECT COUNT(*) FROM admin_login_fails WHERE at > ?');
    $st->execute([$since]);
    return (int)$st->fetchColumn() >= 30;
}

function record_login_fail(): void { db()->prepare('INSERT INTO admin_login_fails (ip_hash, at) VALUES (?, ?)')->execute([ip_hash(), time()]); }

function is_https(): bool { return ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || ($_SERVER['SERVER_PORT'] ?? '') === '443'; }

function set_cookie(string $value, int $expires): void {
    setcookie(COOKIE, $value, ['expires' => $expires, 'path' => '/admin/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
}

function start_session(): void {
    $token = bin2hex(random_bytes(32));
    $exp = time() + SESSION_DAYS * 86400;
    db()->prepare('INSERT INTO admin_sessions (token_hash, csrf, created_at, expires_at, user_agent) VALUES (?, ?, ?, ?, ?)')
        ->execute([hash('sha256', $token), bin2hex(random_bytes(16)), now_utc(), $exp, mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200)]);
    set_cookie($token, $exp);
}

/** The current login session row, or null. */
function current_session(): ?array {
    static $s = false;
    if ($s !== false) return $s;
    $s = null;
    $token = $_COOKIE[COOKIE] ?? '';
    if (!is_string($token) || !preg_match('/^[0-9a-f]{64}$/', $token)) return null;
    $db = db();
    $db->prepare('DELETE FROM admin_sessions WHERE expires_at < ?')->execute([time()]);
    $st = $db->prepare('SELECT * FROM admin_sessions WHERE token_hash = ?');
    $st->execute([hash('sha256', $token)]);
    $s = $st->fetch() ?: null;
    return $s;
}

function end_session(bool $everywhere = false): void {
    $s = current_session();
    if ($everywhere) db()->exec('DELETE FROM admin_sessions');
    elseif ($s) db()->prepare('DELETE FROM admin_sessions WHERE token_hash = ?')->execute([$s['token_hash']]);
    set_cookie('', time() - 3600);
}

function csrf(): string { return current_session()['csrf'] ?? ''; }

function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf()) . '">'; }

function check_csrf(): void {
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || csrf() === '' || !hash_equals(csrf(), $t)) fail('This form expired. Go back, reload the page and try again.', 400);
}

/* ---------------- Helpers ---------------- */

function post(string $k, int $max = 500): string {
    $v = $_POST[$k] ?? '';
    if (!is_string($v)) return '';
    $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '');
    return mb_substr($v, 0, $max);
}

function get(string $k, int $max = 100): string {
    $v = $_GET[$k] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function valid_date(string $d): bool {
    $dt = DateTime::createFromFormat('!Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
}

function today(): string { return date('Y-m-d'); }

function plus_days(int $n): string { return date('Y-m-d', strtotime("+$n day")); }

/** UTC timestamp -> readable India time. */
function ist(?string $utc, string $fmt = 'j M Y, g:i a'): string {
    if (!$utc) return '';
    try { return (new DateTime($utc))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format($fmt); } catch (Throwable $e) { return $utc; }
}

function nice_date(?string $d): string {
    if (!$d) return '';
    if ($d === today()) return 'Today';
    if ($d === plus_days(1)) return 'Tomorrow';
    if ($d === plus_days(-1)) return 'Yesterday';
    $t = strtotime($d);
    return $t ? date(date('Y', $t) === date('Y') ? 'D, j M' : 'j M Y', $t) : $d;
}

function rupees(?int $n): string {
    if ($n === null) return '';
    // Indian digit grouping: 12,34,567
    $s = (string)abs($n);
    $last3 = substr($s, -3); $rest = substr($s, 0, -3);
    if ($rest !== '') $last3 = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',' . $last3;
    return '₹' . ($n < 0 ? '-' : '') . $last3;
}

function log_activity(int $leadId, string $kind, string $text): void {
    db()->prepare('INSERT INTO activity (lead_id, created_at, kind, text) VALUES (?, ?, ?, ?)')->execute([$leadId, now_utc(), $kind, $text]);
}

function find_lead(int $id): ?array {
    $st = db()->prepare('SELECT * FROM leads WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Service names for the picker, written by build.mjs from the site's service list. */
function service_names(): array {
    $f = __DIR__ . '/services.json';
    $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return is_array($j) ? $j : [];
}

function flash(?string $msg = null): ?string {
    if ($msg !== null) { setcookie('lt_flash', $msg, ['expires' => time() + 60, 'path' => '/admin/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']); return null; }
    $m = $_COOKIE['lt_flash'] ?? null;
    if ($m !== null) setcookie('lt_flash', '', ['expires' => time() - 3600, 'path' => '/admin/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    return is_string($m) ? mb_substr($m, 0, 200) : null;
}

function redirect(string $to, ?string $msg = null): void {
    if ($msg !== null) flash($msg);
    header('Location: ' . $to, true, 303);
    exit;
}

/* ---------------- Payments (shared by leads and matters) ---------------- */

function money_in(string $k): ?int { $v = str_replace([',', ' ', '₹'], '', post($k, 15)); return $v !== '' && ctype_digit($v) ? (int)$v : null; }

/**
 * Works out the new [status, received] from a payment form post.
 * A status button sets the status; "Save" alone derives it from the amounts.
 */
function payment_from_post(?int $total, ?string $status, ?int $received): array {
    $newRec = array_key_exists('received', $_POST) ? money_in('received') : $received;
    $btn = post('payment_status', 30);
    if (in_array($btn, PAY_STATUSES, true)) {
        $st = $btn;
        if ($st === 'Fully paid' && $total && (int)$newRec < $total) $newRec = $total;
        if ($st === 'Pending' && $newRec === null) $newRec = null;
    } else {
        $st = $status ?: 'Pending';
        if ($newRec && $total && $newRec >= $total) $st = 'Fully paid';
        elseif ($newRec && (in_array($st, ['Pending', 'Fully paid'], true) || ($st === 'Advance received' && $newRec > (int)$received))) $st = 'Partially paid';
        elseif (!$newRec && $st !== 'Pending') $st = 'Pending';
    }
    return [$st, $newRec];
}

function pay_badge(?string $status): string {
    $s = $status ?: 'Pending';
    $cls = ['Pending' => 'st-lost', 'Advance received' => 'st-quoted', 'Partially paid' => 'st-quoted', 'Fully paid' => 'st-won'][$s] ?? 'st-lost';
    return '<span class="badge ' . $cls . '">' . h($s) . '</span>';
}

/** Payment card with status buttons and "received so far". $hidden carries the record id and action. */
function payment_card(string $hidden, string $totalLabel, ?int $total, ?string $status, ?int $received): string {
    $st = $status ?: 'Pending';
    $bal = $total !== null ? max(0, $total - (int)$received) : null;
    $line = [];
    if ($total !== null) $line[] = h($totalLabel) . ' <strong>' . h(rupees($total)) . '</strong>';
    if ($received) $line[] = 'Received <strong>' . h(rupees($received)) . '</strong>';
    if ($bal !== null && $st !== 'Fully paid') $line[] = 'Balance <strong class="' . ($bal > 0 ? 'late' : '') . '">' . h(rupees($bal)) . '</strong>';
    $btns = '';
    foreach (PAY_STATUSES as $s) $btns .= '<button class="chip' . ($s === $st ? ' on' : '') . '" name="payment_status" value="' . h($s) . '">' . h($s) . '</button>';
    return '<section class="card"><h2>Payment ' . pay_badge($st) . '</h2>' . ($line ? '<p>' . implode(' · ', $line) . '</p>' : '<p class="small muted">Add the ' . h(strtolower($totalLabel)) . ' amount under "Edit details" to see the balance.</p>') . '
      <form method="post" class="stack">' . csrf_field() . $hidden . '
        <div class="inline"><input name="received" inputmode="numeric" pattern="[0-9,]*" maxlength="12" placeholder="Received so far (₹)" value="' . h($received === null ? '' : (string)$received) . '" aria-label="Received so far in rupees"><button class="btn">Update received</button></div>
        <div class="chips">' . $btns . '</div></form></section>';
}
