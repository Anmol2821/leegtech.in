<?php
/*
 * Client portal (shared code). The owner switches the portal on per client in the tracker (client page). The client then
 * signs in at https://leegtech.in/portal/ with their mobile number or email and a password the owner shares with them.
 * Clients see only their own matters: status, office status, upcoming deadlines (no internal notes), fee/received/balance,
 * the owner's "documents needed" message, and they can upload documents. Never: team notes, matter log, other clients.
 * Uploaded files are stored outside public_html in leads-data/uploads/ and are only served through a signed-in check.
 * Used by /portal/index.php and /admin/index.php.
 */
declare(strict_types=1);
if (!defined('LT_ADMIN')) { http_response_code(404); exit; }

const PORTAL_COOKIE = 'lt_portal';
const DOC_TYPES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
    'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'txt' => 'text/plain'];
const OFFICE_WHATSAPP = '918982346105';

function portal_migrate(PDO $db): void {
    $have = array_column($db->query('PRAGMA table_info(clients)')->fetchAll(), 'name');
    foreach (['portal_on' => 'INTEGER DEFAULT 0', 'portal_hash' => 'TEXT', 'portal_since' => 'TEXT', 'portal_last' => 'TEXT'] as $c => $t)
        if (!in_array($c, $have, true)) $db->exec("ALTER TABLE clients ADD COLUMN $c $t");
    $have = array_column($db->query('PRAGMA table_info(matters)')->fetchAll(), 'name');
    if (!in_array('portal_note', $have, true)) $db->exec('ALTER TABLE matters ADD COLUMN portal_note TEXT');
    $db->exec('CREATE TABLE IF NOT EXISTS portal_sessions (token_hash TEXT PRIMARY KEY, client_id INTEGER NOT NULL, csrf TEXT NOT NULL, created_at TEXT NOT NULL, expires_at INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS documents (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER NOT NULL, matter_id INTEGER, name TEXT NOT NULL,
        stored TEXT NOT NULL, size INTEGER, mime TEXT, created_at TEXT NOT NULL, by_name TEXT, by_client INTEGER DEFAULT 0)');
    $db->exec('CREATE INDEX IF NOT EXISTS documents_matter ON documents(matter_id)');
}

/* ---------------- Passwords and sessions ---------------- */

function portal_new_password(): string {
    $abc = 'abcdefghjkmnpqrstuvwxyz23456789';
    $s = '';
    for ($i = 0; $i < 10; $i++) $s .= $abc[random_int(0, strlen($abc) - 1)];
    return substr($s, 0, 5) . '-' . substr($s, 5);
}

/** The login a client types: their mobile number if they have one, else email. */
function portal_login_id(array $c): string {
    return strlen(phone_key((string)$c['phone'])) >= 8 ? phone_key((string)$c['phone']) : strtolower((string)$c['email']);
}

function portal_find(string $login, string $password): ?int {
    $login = trim($login);
    $key = phone_key($login);
    $st = cdb()->prepare('SELECT id, portal_hash FROM clients WHERE portal_on = 1 AND portal_hash IS NOT NULL AND (lower(email) = ? OR (? != \'\' AND phone_key = ?))');
    $st->execute([strtolower($login), strlen($key) >= 8 ? $key : '', $key]);
    foreach ($st->fetchAll() as $c) if (password_verify(str_replace(' ', '', strtolower($password)), $c['portal_hash'])) return (int)$c['id'];
    return null;
}

function portal_cookie(string $v, int $exp): void {
    setcookie(PORTAL_COOKIE, $v, ['expires' => $exp, 'path' => '/portal/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
}

function portal_start(int $clientId): void {
    $token = bin2hex(random_bytes(32)); $exp = time() + 30 * 86400;
    cdb()->prepare('INSERT INTO portal_sessions (token_hash, client_id, csrf, created_at, expires_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([hash('sha256', $token), $clientId, bin2hex(random_bytes(16)), now_utc(), $exp]);
    cdb()->prepare('UPDATE clients SET portal_last = ? WHERE id = ?')->execute([now_utc(), $clientId]);
    portal_cookie($token, $exp);
}

/** Current portal session joined with its client (only while the portal is still on for them). */
function portal_session(): ?array {
    static $s = false;
    if ($s !== false) return $s;
    $s = null;
    $t = $_COOKIE[PORTAL_COOKIE] ?? '';
    if (!is_string($t) || !preg_match('/^[0-9a-f]{64}$/', $t)) return null;
    cdb()->prepare('DELETE FROM portal_sessions WHERE expires_at < ?')->execute([time()]);
    $st = cdb()->prepare('SELECT s.csrf, s.token_hash, c.* FROM portal_sessions s JOIN clients c ON c.id = s.client_id WHERE s.token_hash = ? AND c.portal_on = 1');
    $st->execute([hash('sha256', $t)]);
    return $s = ($st->fetch() ?: null);
}

function portal_end(): void {
    if ($s = portal_session()) cdb()->prepare('DELETE FROM portal_sessions WHERE token_hash = ?')->execute([$s['token_hash']]);
    portal_cookie('', time() - 3600);
}

/* ---------------- Documents ---------------- */

/** Largest file the server will take, after base64 encoding, capped at 10 MB. */
function max_upload_bytes(): int {
    $toBytes = function (string $v): int {
        $v = trim($v); if ($v === '' || $v === '-1' || $v === '0') return PHP_INT_MAX;
        $n = (int)$v; $u = strtolower(substr($v, -1));
        return $u === 'g' ? $n * 1073741824 : ($u === 'm' ? $n * 1048576 : ($u === 'k' ? $n * 1024 : $n));
    };
    $post = $toBytes((string)ini_get('post_max_size'));
    return (int)min(10 * 1048576, $post === PHP_INT_MAX ? PHP_INT_MAX : (int)floor(($post - 65536) / 1.37));
}

function human_size(int $b): string { return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' KB'; }

/** Saves an uploaded document sent base64-encoded (see portal.js). Returns [document id, error]. */
function save_document(int $clientId, ?int $matterId, string $byName, bool $byClient): array {
    $b64 = $_POST['file_b64'] ?? '';
    $name = trim(preg_replace('/[^\p{L}\p{N} ._()+-]/u', '_', (string)($_POST['file_name'] ?? 'document')) ?? 'document');
    if (!is_string($b64) || $b64 === '') return [null, 'Choose a file to upload.'];
    $raw = base64_decode($b64, true);
    if ($raw === false || $raw === '') return [null, 'The file could not be read. Please try again.'];
    if (strlen($raw) > max_upload_bytes()) return [null, 'That file is too large. The limit is ' . human_size(max_upload_bytes()) . '.'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!isset(DOC_TYPES[$ext])) return [null, 'Please upload a PDF, photo (JPG, PNG), Word, Excel or text file.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($raw) ?: 'application/octet-stream';
    $okMime = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'], 'txt' => ['text/plain']];
    if (isset($okMime[$ext]) && !in_array($mime, $okMime[$ext], true)) return [null, 'That file does not look like a real .' . $ext . ' file.'];
    $st = cdb()->prepare('SELECT COUNT(*) FROM documents WHERE client_id = ? AND by_client = 1 AND created_at > ?');
    $st->execute([$clientId, gmdate('Y-m-d\TH:i:s\Z', time() - 86400)]);
    if ($byClient && (int)$st->fetchColumn() >= 30) return [null, 'Too many uploads today. Please send further documents tomorrow or on WhatsApp.'];
    $dir = data_dir() . '/uploads/c' . $clientId;
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) return [null, 'Storage is not available. Please try later.'];
    $stored = 'c' . $clientId . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (file_put_contents(data_dir() . '/uploads/' . $stored, $raw, LOCK_EX) === false) return [null, 'The file could not be saved. Please try later.'];
    @chmod(data_dir() . '/uploads/' . $stored, 0600);
    cdb()->prepare('INSERT INTO documents (client_id, matter_id, name, stored, size, mime, created_at, by_name, by_client) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$clientId, $matterId, mb_substr($name, 0, 150), $stored, strlen($raw), DOC_TYPES[$ext], now_utc(), $byName, $byClient ? 1 : 0]);
    return [(int)cdb()->lastInsertId(), null];
}

function send_document(array $d): void {
    $file = data_dir() . '/uploads/' . $d['stored'];
    if (!preg_match('#^c\d+/[0-9a-f]{24}\.[a-z]{2,4}$#', (string)$d['stored']) || !is_file($file)) { http_response_code(404); exit('File not found.'); }
    header('Content-Type: ' . ($d['mime'] ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($file));
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode((string)$d['name']));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    readfile($file);
    exit;
}

function delete_document(array $d): void {
    $file = data_dir() . '/uploads/' . $d['stored'];
    if (preg_match('#^c\d+/[0-9a-f]{24}\.[a-z]{2,4}$#', (string)$d['stored']) && is_file($file)) @unlink($file);
    cdb()->prepare('DELETE FROM documents WHERE id = ?')->execute([$d['id']]);
}

/** After a client upload: note it on the matter and email the owner and whoever handles the matter. */
function notify_client_upload(array $client, ?array $matter, string $fileName): void {
    if ($matter) mlog((int)$matter['id'], 'Client uploaded a document: ' . $fileName);
    $what = $matter ? trim(($matter['service'] ?: 'Matter') . ($matter['applicant'] ? ' – ' . $matter['applicant'] : '') . ($matter['app_no'] ? ' · No. ' . $matter['app_no'] : '')) : 'general';
    $url = 'https://leegtech.in/admin/?p=' . ($matter ? 'matter&id=' . $matter['id'] : 'client&id=' . $client['id']);
    $html = '<!doctype html><html><body style="font-family:Arial,Helvetica,sans-serif;color:#1b1f24"><div style="max-width:560px;margin:0 auto;padding:20px">
      <p><strong>' . h($client['name']) . '</strong> uploaded <strong>' . h($fileName) . '</strong> in the client portal.</p><p style="color:#5d6673">' . h($what) . '</p>
      <p><a href="' . h($url) . '" style="display:inline-block;background:#1f4fd1;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none">Open in the tracker</a></p></div></body></html>';
    $to = [digest_to()];
    if ($matter && $matter['assigned_to'] && ($u = user_row((int)$matter['assigned_to'])) && (int)$u['active'] && $u['email']) $to[] = (string)$u['email'];
    foreach (array_unique($to) as $addr) send_mail($addr, 'Document from ' . $client['name'] . ': ' . $fileName, $html);
}

/* ---------------- Tracker side (admin) ---------------- */

/** Owner's card on the client page: switch the portal on/off, reset the password. */
function portal_admin_card(array $c): string {
    if (!can('portal')) return '';
    $f = csrf_field() . '<input type="hidden" name="client_id" value="' . (int)$c['id'] . '">';
    if (!(int)$c['portal_on']) {
        $can = strlen(phone_key((string)$c['phone'])) >= 8 || filter_var((string)$c['email'], FILTER_VALIDATE_EMAIL);
        return '<section class="card"><h2>Client portal <span class="badge st-lost">Off</span></h2>
          <p class="small muted">Let this client sign in at leegtech.in/portal to see the status of their applications, upcoming deadlines, fees and balance, and to upload documents. They never see your notes or other clients.</p>'
          . ($can ? '<form method="post">' . $f . '<button class="btn primary" name="action" value="portal_on">Give portal access</button></form>'
                  : '<p class="small late">Add a mobile number or email to this client first (Edit client).</p>') . '</section>';
    }
    return '<section class="card"><h2>Client portal <span class="badge st-won">On</span></h2>
      <p class="small">Sign-in: <strong>' . h(portal_login_id($c)) . '</strong> at leegtech.in/portal · on since ' . h(ist($c['portal_since'], 'j M Y')) . ' · '
      . ($c['portal_last'] ? 'last signed in ' . h(ist($c['portal_last'], 'j M, g:i a')) : 'not signed in yet') . '</p>
      <div class="actions"><form method="post">' . $f . '<button class="btn" name="action" value="portal_reset">New password</button></form>
      <form method="post">' . $f . '<button class="btn danger" name="action" value="portal_off">Switch off</button></form></div></section>';
}

/** Documents list (+ "documents needed" message on a matter) for tracker pages. */
function documents_admin_html(int $clientId, ?int $matterId, ?string $portalNote = null): string {
    $st = $matterId ? cdb()->prepare('SELECT * FROM documents WHERE matter_id = ? ORDER BY id DESC') : cdb()->prepare('SELECT * FROM documents WHERE client_id = ? ORDER BY id DESC');
    $st->execute([$matterId ?: $clientId]);
    $rows = '';
    foreach ($st->fetchAll() as $d) {
        $rows .= '<li><a href="?p=doc&amp;id=' . (int)$d['id'] . '">' . h($d['name']) . '</a> <span class="small muted">' . h(human_size((int)$d['size'])) . ' · ' . h(ist($d['created_at'], 'j M, g:i a')) . ' · ' . ((int)$d['by_client'] ? 'from client' : h((string)$d['by_name'])) . '</span>'
            . (can('delete') ? '<form method="post" class="inline-del">' . csrf_field() . '<input type="hidden" name="doc_id" value="' . (int)$d['id'] . '"><button class="linkbtn" name="action" value="doc_delete">Delete</button></form>' : '') . '</li>';
    }
    $note = '';
    if ($matterId !== null) {
        $note = '<form method="post" class="stack mt">' . csrf_field() . '<input type="hidden" name="matter_id" value="' . $matterId . '"><input type="hidden" name="action" value="portal_note">
          <label>Message to the client in their portal (e.g. documents needed)<textarea name="portal_note" rows="2" maxlength="1000" placeholder="e.g. Please upload the signed Power of Attorney and a copy of your ID">' . h($portalNote) . '</textarea></label>
          <button class="btn">Save message</button></form>';
    }
    return '<section class="card"><h2>Documents</h2>' . ($rows ? '<ul class="docs">' . $rows . '</ul>' : '<p class="small muted">No documents yet.</p>') . '
      <details class="mt"><summary>Upload a document</summary><form method="post" class="stack mt b64up" data-max="' . max_upload_bytes() . '">' . csrf_field() . '
        <input type="hidden" name="action" value="doc_upload"><input type="hidden" name="client_id" value="' . $clientId . '"><input type="hidden" name="matter_id" value="' . (int)$matterId . '">
        <input type="hidden" name="file_b64" value=""><input type="hidden" name="file_name" value="">
        <label>File (PDF, photo, Word, Excel; up to ' . h(human_size(max_upload_bytes())) . ')<input type="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.txt"></label>
        <button class="btn primary">Upload</button><p class="small muted">The client sees documents you upload here in their portal.</p></form></details>' . $note . '</section>';
}

/** Tracker actions for the portal and documents. Returns only by redirecting or rendering. */
function portal_admin_action(string $a): void {
    $db = cdb();
    if ($a === 'doc_delete') {
        require_can('delete');
        $d = find_row('documents', (int)post('doc_id', 12));
        if ($d) { delete_document($d); if ($d['matter_id']) mlog((int)$d['matter_id'], 'Document deleted: ' . $d['name']); }
        $back = $_SERVER['HTTP_REFERER'] ?? '/admin/';
        redirect(str_starts_with((string)parse_url($back, PHP_URL_PATH), '/admin/') ? $back : '/admin/', 'Document deleted.');
    }
    if ($a === 'doc_upload') {
        $mid = (int)post('matter_id', 12) ?: null;
        $cid = (int)post('client_id', 12);
        if ($mid) { require_see('matters', $mid); $m = find_row('matters', $mid); $cid = (int)$m['client_id']; } else require_see('clients', $cid);
        [$id, $err] = save_document($cid, $mid, (string)by_name(), false);
        if ($id && $mid) mlog($mid, 'Document uploaded for the client: ' . (string)($_POST['file_name'] ?? ''));
        redirect('/admin/?p=' . ($mid ? 'matter&id=' . $mid : 'client&id=' . $cid), $err ?? 'Document uploaded. The client can see it in their portal.');
    }
    if ($a === 'portal_note') {
        $m = find_row('matters', (int)post('matter_id', 12));
        if (!$m) redirect('/admin/', 'Matter not found.');
        require_see('matters', (int)$m['id']);
        $db->prepare('UPDATE matters SET portal_note = ? WHERE id = ?')->execute([post('portal_note', 1000), $m['id']]);
        mlog((int)$m['id'], 'Portal message: ' . (post('portal_note', 1000) ?: '(cleared)'));
        redirect('/admin/?p=matter&id=' . $m['id'], 'Message saved. The client sees it in their portal' . ((int)(find_row('clients', (int)$m['client_id'])['portal_on'] ?? 0) ? '.' : ' once you give them portal access.'));
    }
    require_can('portal');
    $c = find_row('clients', (int)post('client_id', 12));
    if (!$c) redirect('/admin/?p=clients', 'Client not found.');
    $back = '/admin/?p=client&id=' . $c['id'];
    if ($a === 'portal_off') {
        $db->prepare('UPDATE clients SET portal_on = 0, portal_hash = NULL WHERE id = ?')->execute([$c['id']]);
        $db->prepare('DELETE FROM portal_sessions WHERE client_id = ?')->execute([$c['id']]);
        redirect($back, 'Portal switched off. ' . $c['name'] . ' can no longer sign in.');
    }
    if ($a === 'portal_on' || $a === 'portal_reset') {
        if (strlen(phone_key((string)$c['phone'])) < 8 && !filter_var((string)$c['email'], FILTER_VALIDATE_EMAIL)) redirect($back, 'Add a mobile number or email to this client first.');
        $pw = portal_new_password();
        $db->prepare('UPDATE clients SET portal_on = 1, portal_hash = ?, portal_since = COALESCE(portal_since, ?) WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), now_utc(), $c['id']]);
        $db->prepare('DELETE FROM portal_sessions WHERE client_id = ?')->execute([$c['id']]);
        $login = portal_login_id($c);
        $msg = "Hello " . $c['name'] . ",\nYou can now check the status of your applications with Leegtech online.\n\nLink: https://leegtech.in/portal/\nLogin: " . $login . "\nPassword: " . $pw . "\n\nYou can also upload documents there. Please keep this password private.";
        $wa = strlen(phone_key((string)$c['phone'])) >= 8 ? '<a class="btn wa" href="https://wa.me/' . h(wa_number((string)$c['phone'])) . '?text=' . h(rawurlencode($msg)) . '" target="_blank" rel="noopener">Send on WhatsApp</a>' : '';
        $mail = filter_var((string)$c['email'], FILTER_VALIDATE_EMAIL) ? '<a class="btn" href="mailto:' . h($c['email']) . '?subject=' . h(rawurlencode('Your Leegtech client portal')) . '&amp;body=' . h(rawurlencode($msg)) . '">Send by email</a>' : '';
        page('Portal access', '<h1>Portal access for ' . h($c['name']) . '</h1>
          <section class="card"><p>Share these details with the client. <strong>This password is shown only now.</strong> If it is lost, create a new one.</p>
          <dl class="info"><dt>Link</dt><dd>leegtech.in/portal</dd><dt>Login</dt><dd><strong>' . h($login) . '</strong></dd><dt>Password</dt><dd><strong class="pw">' . h($pw) . '</strong></dd></dl>
          <div class="actions">' . $wa . $mail . '</div>
          <label class="mt">Message<textarea rows="7" readonly>' . h($msg) . '</textarea></label></section>
          <p><a class="btn" href="' . h($back) . '">Back to ' . h($c['name']) . '</a></p>');
    }
    redirect($back);
}
