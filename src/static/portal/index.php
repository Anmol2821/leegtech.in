<?php
/*
 * Leegtech client portal. https://leegtech.in/portal/
 * Clients the owner has given access to sign in with their mobile number (or email) and password and see only their own
 * matters. Shared code: /admin/_inc/portal_lib.php (the /admin/_inc folder is blocked from the web).
 */
declare(strict_types=1);
define('LT_ADMIN', true);
require __DIR__ . '/../admin/_inc/app.php';
require __DIR__ . '/../admin/_inc/clients.php';
require __DIR__ . '/../admin/_inc/portfolio.php';
require __DIR__ . '/../admin/_inc/team.php';
require __DIR__ . '/../admin/_inc/portal_lib.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');
cdb();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) fail('Not allowed.', 403);
}

function ppage(string $title, string $body, ?array $client = null): void {
    $msg = flash();
    echo '<!doctype html><html lang="en-IN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><title>' . h($title) . ' · Leegtech client portal</title>
<link rel="stylesheet" href="/admin/admin.css?v=8"><link rel="icon" href="/favicon.ico"></head><body class="portal">'
    . ($client ? '<header class="bar"><span class="brand">Leegtech · ' . h(mb_strimwidth((string)$client['name'], 0, 30, '…')) . '</span>
        <form method="post"><input type="hidden" name="csrf" value="' . h($client['csrf']) . '"><button class="linkbtn" name="action" value="logout">Sign out</button></form></header>' : '')
    . '<main>' . ($msg ? '<p class="flash" role="status">' . h($msg) . '</p>' : '') . $body . '
    <p class="small muted mt">Questions? <a href="https://wa.me/' . OFFICE_WHATSAPP . '" target="_blank" rel="noopener">WhatsApp Leegtech</a>. Status shown here is updated by our team and may differ from the official record for a short time; the official IP office record prevails.</p></main>
    <script src="/portal/portal.js?v=1" defer></script></body></html>';
    exit;
}

function pflash_redirect(string $msg): void {
    setcookie('lt_flash', $msg, ['expires' => time() + 60, 'path' => '/portal/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    header('Location: /portal/', true, 303);
    exit;
}

/* ---------- Sign in ---------- */
$client = portal_session();
if (!$client) {
    $err = null;
    if ($method === 'POST' && post('action') === 'login') {
        if (login_locked()) $err = 'Too many attempts. Please wait 15 minutes and try again.';
        elseif (($cid = portal_find(post('login', 160), (string)($_POST['password'] ?? ''))) !== null) { portal_start($cid); header('Location: /portal/', true, 303); exit; }
        else { record_login_fail(); $err = 'That mobile number / email and password do not match.'; }
    }
    ppage('Sign in', '<div class="auth"><h1>Leegtech client portal</h1><p class="muted">Check the status of your applications and upload documents.</p>' . ($err ? '<p class="alert">' . h($err) . '</p>' : '') . '
      <form method="post" class="stack"><input type="hidden" name="action" value="login">
        <label>Mobile number or email<input name="login" required autocomplete="username" inputmode="email"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="btn primary">Sign in</button></form>
      <p class="small muted mt">No password yet? Ask Leegtech on WhatsApp to give you portal access.</p></div>');
}

$cid = (int)$client['id'];
$db = cdb();

/* ---------- Actions ---------- */
if ($method === 'POST') {
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals((string)$client['csrf'], $t)) fail('This form expired. Go back, reload the page and try again.', 400);
    $a = post('action');
    if ($a === 'logout') { portal_end(); header('Location: /portal/', true, 303); exit; }
    if ($a === 'upload') {
        $mid = (int)post('matter_id', 12) ?: null;
        $m = null;
        if ($mid) { $m = find_row('matters', $mid); if (!$m || (int)$m['client_id'] !== $cid) pflash_redirect('That application was not found.'); }
        [$id, $err] = save_document($cid, $mid, (string)$client['name'], true);
        if ($id) notify_client_upload($client, $m, (string)($_POST['file_name'] ?? 'document'));
        pflash_redirect($err ?? 'Thank you, your document was uploaded. Our team has been notified.');
    }
    header('Location: /portal/', true, 303); exit;
}

if (get('doc') !== '') {
    $d = find_row('documents', (int)get('doc', 12));
    if (!$d || (int)$d['client_id'] !== $cid) { http_response_code(404); exit('File not found.'); }
    send_document($d);
}

/* ---------- Home ---------- */
$st = $db->prepare('SELECT * FROM matters WHERE client_id = ? ORDER BY closed, COALESCE(filed_on, created_at) DESC, id DESC');
$st->execute([$cid]);
$matters = $st->fetchAll();
$dst = $db->prepare('SELECT kind, due_on FROM deadlines WHERE matter_id = ? AND done_at IS NULL AND due_on >= ? ORDER BY due_on');
$doc = $db->prepare('SELECT * FROM documents WHERE matter_id = ? ORDER BY id DESC');
$csrf = '<input type="hidden" name="csrf" value="' . h($client['csrf']) . '">';
$max = max_upload_bytes();

$cards = ''; $balance = 0; $openN = 0;
foreach ($matters as $m) {
    $mid = (int)$m['id'];
    $dst->execute([$mid, today()]); $dls = $dst->fetchAll();
    $doc->execute([$mid]); $docs = $doc->fetchAll();
    $bal = $m['amount'] ? max(0, (int)$m['amount'] - (int)$m['received']) : 0;
    if (($m['payment_status'] ?? '') !== 'Fully paid') $balance += $bal;
    if (!$m['closed']) $openN++;
    $dl = '';
    foreach ($dls as $d) $dl .= '<li><strong>' . h($d['kind']) . '</strong> · <span class="' . due_class($d['due_on']) . '">' . h(nice_date($d['due_on'])) . '</span></li>';
    $dc = '';
    foreach ($docs as $d) $dc .= '<li><a href="?doc=' . (int)$d['id'] . '">' . h($d['name']) . '</a> <span class="small muted">' . h(ist($d['created_at'], 'j M Y')) . ((int)$d['by_client'] ? ' · uploaded by you' : ' · from Leegtech') . '</span></li>';
    $cards .= '<section class="card pm' . ($m['closed'] ? ' closed' : '') . '">
      <div class="top"><h2>' . h($m['applicant'] ?: ($m['title'] ?: ($m['service'] ?: 'Application'))) . '</h2><span class="badge ' . status_class((string)$m['status']) . '">' . h($m['closed'] ? 'Completed' : ($m['status'] ?: 'In progress')) . '</span></div>
      <p class="muted small">' . h(implode(' · ', array_filter([$m['service'], $m['title'] && $m['applicant'] ? $m['title'] : '', $m['app_no'] ? 'Application no. ' . $m['app_no'] : '', $m['filed_on'] ? 'Filed ' . date('j M Y', strtotime($m['filed_on'])) : '']))) . '</p>'
      . ($m['office_status'] ? '<p class="small"><span class="muted">Office status:</span> ' . h($m['office_status']) . '</p>' : '')
      . ($m['portal_note'] ? '<p class="note">' . nl2br(h($m['portal_note'])) . '</p>' : '')
      . ($dl ? '<h3>Coming up</h3><ul class="plain">' . $dl . '</ul>' : '')
      . ($m['amount'] ? '<h3>Fees</h3><p class="small">Fee ' . h(rupees((int)$m['amount'])) . ' · Received ' . h(rupees((int)$m['received'])) . ' · '
          . (($m['payment_status'] ?? '') === 'Fully paid' ? '<strong>Fully paid</strong>' : 'Balance <strong class="' . ($bal ? 'late' : '') . '">' . h(rupees($bal)) . '</strong>') . '</p>' : '')
      . '<h3>Documents</h3>' . ($dc ? '<ul class="docs">' . $dc . '</ul>' : '<p class="small muted">None yet.</p>')
      . (!$m['closed'] ? '<details><summary>Upload a document</summary><form method="post" class="stack mt b64up" data-max="' . $max . '">' . $csrf . '<input type="hidden" name="action" value="upload">
          <input type="hidden" name="matter_id" value="' . $mid . '"><input type="hidden" name="file_b64" value=""><input type="hidden" name="file_name" value="">
          <label>File (PDF, photo, Word or Excel, up to ' . h(human_size($max)) . ')<input type="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.txt"></label>
          <button class="btn primary">Upload</button></form></details>' : '') . '</section>';
}

ppage('Your applications', '<h1>Your applications</h1>
  <p class="muted">' . $openN . ' in progress' . ($balance ? ' · Balance due <strong class="late">' . h(rupees($balance)) . '</strong>' : '') . '</p>'
  . ($cards ?: '<p class="muted">No applications to show yet.</p>'), $client);
