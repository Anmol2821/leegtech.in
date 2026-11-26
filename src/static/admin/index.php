<?php
/*
 * Leegtech lead tracker (private). https://leegtech.in/admin/
 * Screens: Today (?p=home), Leads (?p=leads), Lead (?p=lead&id=), Add (?p=add), Settings (?p=settings), CSV (?p=export)
 * Shared code: _inc/app.php. Website enquiries come in from /api/lead.php into the same database.
 */
declare(strict_types=1);
define('LT_ADMIN', true);
require __DIR__ . '/_inc/app.php';
require __DIR__ . '/_inc/clients.php';
require __DIR__ . '/_inc/portfolio.php';
require __DIR__ . '/_inc/team.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$p = get('p') ?: 'home';
$self = '/admin/';
cdb(); // create/upgrade tables before anything else

// Reject cross-site form posts.
if ($method === 'POST') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . $host, PHP_URL_HOST)) fail('Not allowed.', 403);
}

/* ---------- First visit: choose the password ---------- */
if (admin_config() === null) {
    $err = null;
    if ($method === 'POST' && post('action') === 'setup') {
        if (login_locked()) $err = 'Too many attempts. Wait 15 minutes and try again.';
        elseif (!password_verify(implode('-', str_split(preg_replace('/[^A-Z0-9]/', '', strtoupper(post('code', 60))) ?? '', 5)), SETUP_CODE_HASH)) { record_login_fail(); $err = 'That setup code is not right.'; }
        elseif ($e = password_problem($_POST['password'] ?? '', $_POST['password2'] ?? '')) $err = $e;
        else { save_password((string)$_POST['password']); start_session(); redirect($self, 'Password saved. Welcome!'); }
    }
    page('Set up', '<div class="auth"><h1>Set up your lead tracker</h1>
        <p class="muted">Enter the one-time setup code you were given, then choose the password you will use to sign in.</p>'
        . ($err ? '<p class="alert">' . h($err) . '</p>' : '') . '
        <form method="post" class="stack"><input type="hidden" name="action" value="setup">
        <label>Setup code<input name="code" required autocomplete="off" autocapitalize="characters" placeholder="XXXXX-XXXXX-XXXXX-XXXXX"></label>
        <label>New password (at least 10 characters)<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
        <label>Repeat password<input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
        <button class="btn primary">Save and sign in</button></form></div>', false);
}

/* ---------- Sign in ---------- */
if (current_session() && !me()) end_session(); // switched-off team member
if (!current_session() || !me()) {
    $err = null;
    if ($method === 'POST' && post('action') === 'login') {
        if (login_locked()) $err = 'Too many wrong attempts. Wait 15 minutes and try again.';
        elseif (($uid = login_user((string)($_POST['password'] ?? ''))) !== null) { start_session($uid); redirect($self); }
        else { record_login_fail(); $err = 'Wrong password.'; }
    }
    page('Sign in', '<div class="auth"><h1>Leegtech leads</h1>' . ($err ? '<p class="alert">' . h($err) . '</p>' : '') . '
        <form method="post" class="stack"><input type="hidden" name="action" value="login">
        <label>Password<input type="password" name="password" required autofocus autocomplete="current-password"></label>
        <button class="btn primary">Sign in</button></form></div>', false);
}


/* ---------- Actions (all POST, signed in) ---------- */
if ($method === 'POST') {
    check_csrf();
    $a = post('action');
    $db = db();

    if ($a === 'logout') { end_session(); redirect($self); }
    if ($a === 'logout_all') { end_session(true); redirect($self); }

    if ($a === 'password') {
        $hash = is_owner() ? admin_config()['password_hash'] : (string)user_row(my_id())['pw_hash'];
        if (!password_verify((string)($_POST['current'] ?? ''), $hash)) redirect($self . '?p=settings', 'Current password is wrong.');
        if ($e = password_problem($_POST['password'] ?? '', $_POST['password2'] ?? '')) redirect($self . '?p=settings', $e);
        if (password_taken((string)$_POST['password'], my_id())) redirect($self . '?p=settings', 'Choose a different password.');
        if (is_owner()) save_password((string)$_POST['password']);
        else $db->prepare('UPDATE users SET pw_hash = ? WHERE id = ?')->execute([password_hash((string)$_POST['password'], PASSWORD_DEFAULT), my_id()]);
        redirect($self . '?p=settings', 'Password changed.');
    }

    if (str_starts_with($a, 'c_')) clients_action($a);
    if (str_starts_with($a, 'team_')) team_action($a);
    if (str_starts_with($a, 't_')) task_action($a);
    if ($a === 'bulk_assign') bulk_assign_action();
    if (in_array($a, ['digest', 'digest_test', 'lines'], true)) require_owner($self . '?p=settings');

    if ($a === 'digest') {
        $to = strtolower(post('digest_to', 160));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) redirect($self . '?p=settings', 'Enter a valid email address.');
        save_digest_settings($to, post('digest_on', 1) === '1');
        redirect($self . '?p=settings', 'Daily email settings saved.');
    }
    if ($a === 'digest_test') {
        redirect($self . '?p=settings', digest_send() ? 'Test email sent to ' . digest_to() . '. Check the inbox (and spam) in a minute.' : 'The email could not be sent from the server.');
    }

    if ($a === 'lines') {
        $lines = [];
        foreach ((array)($_POST['lines'] ?? []) as $l) {
            $l = is_string($l) ? trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $l) ?? '') : '';
            $l = mb_substr($l, 0, 60);
            if ($l !== '' && !in_array($l, $lines, true)) $lines[] = $l;
        }
        if (!$lines) redirect($self . '?p=settings', 'Keep at least one WhatsApp number.');
        $old = wa_lines();
        save_lines($lines);
        // Renamed numbers: move existing leads to the new name (same position in the list).
        $up = $db->prepare('UPDATE leads SET line = ? WHERE line = ?');
        foreach ($old as $i => $o) if (isset($lines[$i]) && $lines[$i] !== $o && !in_array($o, $lines, true)) $up->execute([$lines[$i], $o]);
        redirect($self . '?p=settings', 'WhatsApp numbers saved.');
    }

    if ($a === 'add') {
        $name = post('name', 120); $phone = post('phone', 25);
        $key = phone_key($phone);
        if ($name === '' || strlen($key) < 8) redirect($self . '?p=add', 'Enter a name and a valid phone number.');
        $note = post('note', 2000);
        $st = $db->prepare('SELECT id FROM leads WHERE phone_key = ? AND COALESCE(is_test, 0) = 0 ORDER BY id DESC LIMIT 1');
        $st->execute([$key]);
        if ($dup = $st->fetchColumn()) {
            if (!can_see('leads', (int)$dup)) redirect($self . '?p=add', 'This number is already in the tracker under someone else. Ask the owner to assign it to you.');
            if ($note !== '') log_activity((int)$dup, 'note', $note);
            redirect($self . '?p=lead&id=' . $dup, 'This number is already saved, so its existing lead is open.' . ($note !== '' ? ' Your note was added.' : ''));
        }
        $source = in_array(post('source'), SOURCES, true) ? post('source') : 'Other';
        $fu = post('follow_up', 10); if (!valid_date($fu)) $fu = null;
        $amt = post('quoted_amount', 12); $amt = $amt !== '' && ctype_digit($amt) && show_money() ? (int)$amt : null;
        $who = is_owner() ? posted_person() : my_id();
        $line = in_array(post('line', 60), wa_lines(), true) ? post('line', 60) : null;
        if ($line) setcookie('lt_line', $line, ['expires' => time() + 365 * 86400, 'path' => '/admin/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
        $db->prepare('INSERT INTO leads (created_at, event_id, name, phone, phone_key, email, service, city, source, ad_code, line, quoted_amount, follow_up, stage, updated_at, consent, is_test, assigned_to)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)')
            ->execute([now_utc(), 'adm-' . bin2hex(random_bytes(6)), $name, $phone, $key, strtolower(post('email', 160)), post('service', 160), post('city', 80),
                $source, strtoupper(post('ad_code', 30)), $line, $amt, $fu, 'New', now_utc(), 'n/a', $who]);
        $id = (int)$db->lastInsertId();
        notify_assigned($who, 'Lead: ' . $name, '?p=lead&id=' . $id, post('service', 160));
        log_activity($id, 'created', 'Lead added (' . $source . ($line ? ', ' . $line : '') . ')');
        if ($note !== '') log_activity($id, 'note', $note);
        redirect($self . '?p=lead&id=' . $id, 'Lead saved.');
    }

    $id = (int)post('id', 12);
    $lead = $id ? find_lead($id) : null;
    if (!$lead) redirect($self . '?p=leads', 'That lead was not found.');
    $back = $self . '?p=lead&id=' . $id;
    $stage = $lead['stage'] ?: 'New';
    require_see('leads', $id);
    if (in_array($a, ['pay', 'delete', 'assign'], true)) require_owner($back);

    if ($a === 'assign') {
        $who = posted_person();
        if ($who !== ($lead['assigned_to'] === null ? null : (int)$lead['assigned_to'])) {
            $db->prepare('UPDATE leads SET assigned_to = ?, updated_at = ? WHERE id = ?')->execute([$who, now_utc(), $id]);
            log_activity($id, 'note', $who ? 'Assigned to ' . person_name($who) : 'Unassigned');
            notify_assigned($who, 'Lead: ' . ($lead['name'] ?: 'No name'), '?p=lead&id=' . $id, trim(($lead['service'] ?: '') . ($lead['follow_up'] ? ' · follow up ' . nice_date($lead['follow_up']) : '')));
        }
        redirect($back, $who ? 'Assigned to ' . person_name($who) . '.' : 'Unassigned.');
    }

    $setStage = function (string $new) use ($db, $id, $stage) {
        if ($new === $stage || !in_array($new, STAGES, true)) return;
        $db->prepare('UPDATE leads SET stage = ?, won_at = CASE WHEN ? = \'Won\' THEN ? ELSE won_at END, updated_at = ? WHERE id = ?')
            ->execute([$new, $new, now_utc(), now_utc(), $id]);
        log_activity($id, 'stage', $stage . ' → ' . $new);
    };
    $setFollowUp = function (?string $d) use ($db, $id, $lead) {
        if ($d === ($lead['follow_up'] ?: null)) return;
        $db->prepare('UPDATE leads SET follow_up = ?, updated_at = ? WHERE id = ?')->execute([$d, now_utc(), $id]);
        log_activity($id, 'followup', $d ? 'Follow-up set for ' . nice_date($d) . ' (' . $d . ')' : 'Follow-up cleared');
    };

    if ($a === 'stage') {
        $new = post('stage');
        $setStage($new);
        if (in_array($new, ['Won', 'Lost'], true)) $setFollowUp(null);
        redirect($back, $new === 'Won' && !$lead['client_id'] ? 'Won! Tap "Make client" to start tracking the work.' : 'Stage: ' . $new);
    }
    if ($a === 'followup') {
        $days = post('days', 3);
        $d = $days === 'clear' ? null : (ctype_digit($days) ? plus_days((int)$days) : (valid_date(post('date', 10)) ? post('date', 10) : false));
        if ($d === false) redirect($back, 'Pick a date.');
        $setFollowUp($d);
        redirect($back, $d ? 'Follow-up: ' . nice_date($d) : 'Follow-up cleared');
    }
    if ($a === 'note') {
        $n = post('note', 2000);
        if ($n !== '') log_activity($id, 'note', $n);
        redirect($back, $n !== '' ? 'Note added.' : null);
    }
    if ($a === 'edit') {
        $source = in_array(post('source'), SOURCES, true) ? post('source') : ($lead['source'] ?: 'Other');
        $amt = post('quoted_amount', 12); $amt = $amt !== '' && ctype_digit($amt) ? (int)$amt : null;
        if (!show_money()) $amt = $lead['quoted_amount'] === null ? null : (int)$lead['quoted_amount'];
        $phone = post('phone', 25) ?: (string)$lead['phone'];
        $line = post('line', 60);
        $line = $line === '' ? null : (in_array($line, wa_lines(), true) || $line === $lead['line'] ? $line : $lead['line']);
        $db->prepare('UPDATE leads SET name = ?, phone = ?, phone_key = ?, email = ?, city = ?, service = ?, source = ?, ad_code = ?, line = ?, quoted_amount = ?, lost_reason = ?, updated_at = ? WHERE id = ?')
            ->execute([post('name', 120) ?: $lead['name'], $phone, phone_key($phone), strtolower(post('email', 160)), post('city', 80), post('service', 160),
                $source, strtoupper(post('ad_code', 30)), $line, $amt, post('lost_reason', 200), now_utc(), $id]);
        if ($amt !== null && $amt !== ($lead['quoted_amount'] === null ? null : (int)$lead['quoted_amount'])) log_activity($id, 'note', 'Quoted ' . rupees($amt));
        redirect($back, 'Saved.');
    }
    if ($a === 'pay') {
        [$st, $rec] = payment_from_post($lead['quoted_amount'] === null ? null : (int)$lead['quoted_amount'], $lead['payment_status'], $lead['received'] === null ? null : (int)$lead['received']);
        $db->prepare('UPDATE leads SET payment_status = ?, received = ?, updated_at = ? WHERE id = ?')->execute([$st, $rec, now_utc(), $id]);
        if ($st !== ($lead['payment_status'] ?: 'Pending') || $rec !== ($lead['received'] === null ? null : (int)$lead['received']))
            log_activity($id, 'note', 'Payment: ' . $st . ($rec ? ', received ' . rupees($rec) : ''));
        redirect($back, 'Payment: ' . $st);
    }
    if ($a === 'delete') {
        if (post('confirm') !== 'yes') redirect($back, 'Tick the box to confirm deleting.');
        $db->prepare('DELETE FROM activity WHERE lead_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM leads WHERE id = ?')->execute([$id]);
        redirect($self . '?p=leads', 'Lead deleted.');
    }
    redirect($back);
}

/* ---------- Screens ---------- */
$db = db();
$notTest = 'COALESCE(is_test, 0) = 0';
$open = "COALESCE(stage, 'New') IN ('" . implode("','", OPEN_STAGES) . "')";

if ($p === 'tasks') tasks_page();

if ($p === 'export') {
    require_owner($self . '?p=settings');
    $rows = $db->query("SELECT * FROM leads WHERE $notTest ORDER BY id")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leegtech-leads-' . today() . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $cols = ['id', 'created_at', 'name', 'phone', 'email', 'service', 'city', 'startup_status', 'message', 'source', 'ad_code', 'stage',
        'line', 'quoted_amount', 'payment_status', 'received', 'follow_up', 'won_at', 'lost_reason', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'landing_page'];
    fputcsv($out, array_merge(['Lead no.', 'Date (IST)'], array_slice($cols, 2)), ',', '"', '');
    foreach ($rows as $r) {
        $vals = [$r['id'], ist($r['created_at'], 'Y-m-d H:i')];
        foreach (array_slice($cols, 2) as $c) $vals[] = $c === 'stage' ? ($r['stage'] ?: 'New') : (string)($r[$c] ?? '');
        $vals = array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : $v, $vals); // no spreadsheet formulas
        fputcsv($out, $vals, ',', '"', '');
    }
    exit;
}

if ($p === 'add') {
    $opts = '';
    foreach (SOURCES as $s) $opts .= '<option' . ($s === 'Meta ad (WhatsApp)' ? ' selected' : '') . '>' . h($s) . '</option>';
    page('Add lead', '<h1>Add a lead</h1>
    <form method="post" class="stack">' . csrf_field() . '<input type="hidden" name="action" value="add">
      <label>Name *<input name="name" required maxlength="120" autocomplete="off"></label>
      <label>Phone / WhatsApp *<input name="phone" type="tel" required inputmode="tel" maxlength="25" placeholder="98xxxxxxxx" autocomplete="off"></label>
      <label>Service' . service_select('') . '</label>
      <div class="row2"><label>Source<select name="source">' . $opts . '</select></label>
      <label>Ad code<input name="ad_code" maxlength="30" placeholder="e.g. TM1" autocapitalize="characters"></label></div>
      <label>Came in on' . line_select(is_string($_COOKIE['lt_line'] ?? null) && in_array($_COOKIE['lt_line'], wa_lines(), true) ? $_COOKIE['lt_line'] : wa_lines()[0]) . '</label>
      <label>Note<textarea name="note" rows="2" maxlength="2000" placeholder="What they need, budget, anything useful"></textarea></label>
      <div class="row2"><label>Follow up on<input type="date" name="follow_up" value="' . h(plus_days(1)) . '"></label>
      ' . (show_money() ? '<label>Quote (₹)<input name="quoted_amount" inputmode="numeric" pattern="[0-9]*" maxlength="12"></label>' : '') . '</div>
      ' . (is_owner() && team_names() ? '<label>Assign to' . person_select('assigned_to', null, 'Me (not assigned)') . '</label>' : '') . '
      <details><summary>More details</summary><div class="stack">
        <label>Email<input name="email" type="email" maxlength="160"></label>
        <label>City<input name="city" maxlength="80"></label></div></details>
      <button class="btn primary big">Save lead</button>
    </form>');
}

if ($p === 'lead') {
    $lead = find_lead((int)get('id', 12));
    if (!$lead) redirect($self . '?p=leads', 'That lead was not found.');
    $id = (int)$lead['id'];
    require_see('leads', $id);
    $stage = $lead['stage'] ?: 'New';
    $st = $db->prepare('SELECT * FROM activity WHERE lead_id = ? ORDER BY id DESC');
    $st->execute([$id]);
    $acts = $st->fetchAll();

    $contact = '<div class="actions">';
    if ($lead['phone']) {
        $contact .= '<a class="btn wa" href="https://wa.me/' . h(wa_number($lead['phone'])) . '" target="_blank" rel="noopener">WhatsApp</a>';
        $contact .= '<a class="btn" href="tel:+' . h(wa_number($lead['phone'])) . '">Call</a>';
    }
    if ($lead['email']) $contact .= '<a class="btn" href="mailto:' . h($lead['email']) . '">Email</a>';
    $contact .= '</div>';

    $stageBtns = '';
    foreach (STAGES as $s) $stageBtns .= '<button name="stage" value="' . h($s) . '" class="chip st-' . strtolower($s) . ($s === $stage ? ' on' : '') . '">' . h($s) . '</button>';

    $fu = $lead['follow_up'];
    $fuText = $fu ? '<strong class="' . ($fu < today() ? 'late' : ($fu === today() ? 'due' : '')) . '">' . h(nice_date($fu)) . '</strong>' : '<span class="muted">Not set</span>';

    $info = [];
    foreach ([['Phone', $lead['phone']], ['Email', $lead['email']], ['City', $lead['city']], ['Service', $lead['service']],
        ['Startup status', $lead['startup_status']], ['Message', $lead['message']], ['Source', $lead['source']], ['Ad code', $lead['ad_code']], ['Came in on', $lead['line']],
        ['Quote', $lead['quoted_amount'] !== null && show_money() ? rupees((int)$lead['quoted_amount']) : ''], ['Lost reason', $lead['lost_reason']],
        ['Enquiry date', ist($lead['created_at'])], ['Form page', $lead['page_url']],
        ['Campaign', trim(implode(' / ', array_filter([$lead['utm_source'], $lead['utm_medium'], $lead['utm_campaign'], $lead['utm_content']])))]] as [$k, $v])
        if ((string)$v !== '') $info[] = '<dt>' . h($k) . '</dt><dd>' . nl2br(h((string)$v)) . '</dd>';

    $timeline = '';
    foreach ($acts as $x) $timeline .= '<li class="k-' . h($x['kind']) . '"><span class="muted small">' . h(ist($x['created_at'], 'j M, g:i a')) . ($x['by_name'] ? ' · ' . h($x['by_name']) : '') . '</span><div>' . nl2br(h($x['text'])) . '</div></li>';

    $srcOpts = '';
    foreach (SOURCES as $s) $srcOpts .= '<option' . ($s === $lead['source'] ? ' selected' : '') . '>' . h($s) . '</option>';
    if ($lead['source'] && !in_array($lead['source'], SOURCES, true)) $srcOpts .= '<option selected>' . h($lead['source']) . '</option>';

    $f = csrf_field() . '<input type="hidden" name="id" value="' . $id . '">';
    page($lead['name'] ?: 'Lead', '<p class="small"><a href="?p=leads">← All leads</a></p>
    <h1>' . h($lead['name'] ?: 'No name') . ' <span class="badge st-' . strtolower($stage) . '">' . h($stage) . '</span></h1>
    <p class="muted">' . h($lead['service'] ?: 'Service not set') . ' · #' . $id . '</p>' . $contact . assign_card('<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="assign">', $lead['assigned_to'] ? (int)$lead['assigned_to'] : null) . convert_card($lead) . '
    <section class="card"><h2>Stage</h2><form method="post" class="chips">' . $f . '<input type="hidden" name="action" value="stage">' . $stageBtns . '</form></section>
    <section class="card"><h2>Next follow-up: ' . $fuText . '</h2>
      <form method="post" class="chips">' . $f . '<input type="hidden" name="action" value="followup">
        <button class="chip" name="days" value="1">Tomorrow</button><button class="chip" name="days" value="3">In 3 days</button>
        <button class="chip" name="days" value="7">In a week</button><button class="chip" name="days" value="30">In a month</button>' .
        ($fu ? '<button class="chip" name="days" value="clear">Clear</button>' : '') . '</form>
      <form method="post" class="inline">' . $f . '<input type="hidden" name="action" value="followup"><input type="hidden" name="days" value="date">
        <input type="date" name="date" required value="' . h($fu ?: '') . '"><button class="btn">Set date</button></form></section>
    ' . (show_money() ? payment_card('<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="pay">', 'Quote', $lead['quoted_amount'] === null ? null : (int)$lead['quoted_amount'], $lead['payment_status'], $lead['received'] === null ? null : (int)$lead['received']) : '') . tasks_section('lead_id', $id) . '
    <section class="card"><h2>Notes</h2>
      <form method="post" class="stack">' . $f . '<input type="hidden" name="action" value="note">
        <textarea name="note" rows="2" required maxlength="2000" placeholder="e.g. Called, sent documents list, will confirm by Friday"></textarea>
        <button class="btn primary">Add note</button></form>
      <ul class="timeline">' . ($timeline ?: '<li class="muted">No notes yet.</li>') . '</ul></section>
    <section class="card"><h2>Details</h2><dl class="info">' . implode('', $info) . '</dl></section>
    <details class="card"><summary>Edit details</summary>
      <form method="post" class="stack">' . $f . '<input type="hidden" name="action" value="edit">
        <label>Name<input name="name" maxlength="120" value="' . h($lead['name']) . '"></label>
        <label>Phone<input name="phone" type="tel" maxlength="25" value="' . h($lead['phone']) . '"></label>
        <label>Email<input name="email" type="email" maxlength="160" value="' . h($lead['email']) . '"></label>
        <label>City<input name="city" maxlength="80" value="' . h($lead['city']) . '"></label>
        <label>Service' . service_select((string)$lead['service']) . '</label>
        <div class="row2"><label>Source<select name="source">' . $srcOpts . '</select></label>
        <label>Ad code<input name="ad_code" maxlength="30" value="' . h($lead['ad_code']) . '"></label></div>
        <label>Came in on' . line_select($lead['line']) . '</label>
        ' . (show_money() ? '<label>Quote (₹)<input name="quoted_amount" inputmode="numeric" pattern="[0-9]*" maxlength="12" value="' . h($lead['quoted_amount'] === null ? '' : (string)$lead['quoted_amount']) . '"></label>' : '') . '
        <label>Lost reason (if lost)<input name="lost_reason" maxlength="200" value="' . h($lead['lost_reason']) . '" placeholder="e.g. price, chose someone else, not reachable"></label>
        <button class="btn primary">Save details</button></form></details>
    ' . (is_owner() ? '<details class="card"><summary>Delete this lead</summary>
      <form method="post" class="stack">' . $f . '<input type="hidden" name="action" value="delete">
        <label class="check"><input type="checkbox" name="confirm" value="yes" required> Yes, permanently delete this lead and its notes</label>
        <button class="btn danger">Delete</button></form></details>' : ''));
}

if (in_array($p, ['ip', 'import'], true)) portfolio_page($p);
if (in_array($p, ['clients', 'client', 'matter', 'deadlines', 'newclient'], true)) clients_page($p);

if ($p === 'leads') {
    $stageF = get('stage'); $q = get('q', 80); $srcF = get('source', 60); $lineF = get('line', 60); $tests = get('tests') === '1';
    $where = [$tests ? '1=1' : $notTest, lead_scope('leads'), who_sql('assigned_to')]; $args = [];
    if ($stageF === 'open') $where[] = $open;
    elseif (in_array($stageF, STAGES, true)) { $where[] = "COALESCE(stage, 'New') = ?"; $args[] = $stageF; }
    if ($srcF !== '') { $where[] = 'source = ?'; $args[] = $srcF; }
    if ($lineF !== '') { $where[] = 'line = ?'; $args[] = $lineF; }
    if ($q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $digits = preg_replace('/\D/', '', $q);
        $where[] = "(name LIKE ? ESCAPE '\\' OR email LIKE ? ESCAPE '\\' OR service LIKE ? ESCAPE '\\' OR ad_code LIKE ? ESCAPE '\\' OR city LIKE ? ESCAPE '\\'" . (strlen($digits) >= 4 ? ' OR phone_key LIKE ?' : '') . ')';
        array_push($args, $like, $like, $like, $like, $like);
        if (strlen($digits) >= 4) $args[] = '%' . $digits . '%';
    }
    $st = $db->prepare('SELECT * FROM leads WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 300');
    $st->execute($args);
    $rows = $st->fetchAll();

    $counts = $db->query("SELECT COALESCE(stage, 'New') s, COUNT(*) n FROM leads WHERE $notTest AND " . lead_scope('leads') . ' AND ' . who_sql('assigned_to') . ' GROUP BY s')->fetchAll(PDO::FETCH_KEY_PAIR);
    $openN = 0; foreach (OPEN_STAGES as $s) $openN += (int)($counts[$s] ?? 0);
    $chip = function (string $val, string $label, int $n) use ($stageF, $q, $srcF, $lineF) {
        $qs = http_build_query(array_filter(['p' => 'leads', 'stage' => $val, 'q' => $q, 'source' => $srcF, 'line' => $lineF, 'who' => who_filter()]));
        return '<a class="chip' . ($stageF === $val ? ' on' : '') . '" href="?' . h($qs) . '">' . h($label) . ' <span class="n">' . $n . '</span></a>';
    };
    $chips = $chip('', 'All', array_sum(array_map('intval', $counts))) . $chip('open', 'Open', $openN);
    foreach (STAGES as $s) $chips .= $chip($s, $s, (int)($counts[$s] ?? 0));
    $srcOpts = '<option value="">All sources</option>';
    foreach (SOURCES as $s) $srcOpts .= '<option' . ($s === $srcF ? ' selected' : '') . '>' . h($s) . '</option>';
    $lineOpts = '<option value="">All numbers</option>';
    foreach (wa_lines() as $l) $lineOpts .= '<option' . ($l === $lineF ? ' selected' : '') . '>' . h($l) . '</option>';

    page('Leads', '<h1>Leads</h1>
    <form class="search" method="get"><input type="hidden" name="p" value="leads"><input type="hidden" name="stage" value="' . h($stageF) . '">
      <input type="search" name="q" value="' . h($q) . '" placeholder="Search name, phone, service, ad code">
      <select name="source">' . $srcOpts . '</select><select name="line">' . $lineOpts . '</select>' . who_select() . '<button class="btn">Search</button></form>
    <nav class="chips scroll">' . $chips . '</nav>
    <div class="list">' . (implode('', array_map('lead_card', $rows)) ?: '<p class="muted">No leads here yet.</p>') . '</div>
    <p class="small muted">' . count($rows) . ' shown' . (count($rows) >= 300 ? ' (latest 300; search to narrow)' : '') . ' · <a href="?p=leads&amp;tests=' . ($tests ? '0' : '1') . '">' . ($tests ? 'Hide' : 'Show') . ' test entries</a></p>' . bulk_assign_form('leads', array_column($rows, 'id'), 'leads'));
}

if ($p === 'settings' && !is_owner()) {
    page('Settings', '<h1>Settings</h1><p class="muted">Signed in as <strong>' . h(me()['name']) . '</strong>.</p>
    <section class="card"><h2>Change your password</h2><form method="post" class="stack">' . csrf_field() . '<input type="hidden" name="action" value="password">
      <label>Current password<input type="password" name="current" required autocomplete="current-password"></label>
      <label>New password (at least 10 characters)<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
      <label>Repeat new password<input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
      <button class="btn primary">Change password</button></form></section>
    <section class="card"><h2>Sign out</h2><div class="actions">
      <form method="post">' . csrf_field() . '<button class="btn" name="action" value="logout">Sign out</button></form>
      <form method="post">' . csrf_field() . '<button class="btn danger" name="action" value="logout_all">Sign out on all my devices</button></form></div></section>');
}

if ($p === 'settings') {
    page('Settings', '<h1>Settings</h1>' . team_settings_html() . '
    <section class="card"><h2>Download all leads</h2><p class="muted">A spreadsheet (CSV) of every lead, for backup or Excel.</p><a class="btn" href="?p=export">Download CSV</a></section>
    <section class="card"><h2>Daily summary email</h2><p class="muted">Every morning after 9am: deadlines due in the next ' . DEADLINE_WINDOW . ' days, lead follow-ups due, and new leads.</p>
      <form method="post" class="stack">' . csrf_field() . '<input type="hidden" name="action" value="digest">
        <label>Send to<input name="digest_to" type="email" required maxlength="160" value="' . h(digest_to()) . '"></label>
        <label class="check"><input type="checkbox" name="digest_on" value="1"' . (digest_enabled() ? ' checked' : '') . '> Send the daily email</label>
        <button class="btn primary">Save</button></form>
      <form method="post" class="mt">' . csrf_field() . '<button class="btn" name="action" value="digest_test">Send a test email now</button></form></section>
    <section class="card"><h2>Your WhatsApp numbers</h2><p class="muted">Name each number so you can record which one a lead messaged. Renaming keeps existing leads on that number. Leave a box empty to remove it.</p>
      <form method="post" class="stack">' . csrf_field() . '<input type="hidden" name="action" value="lines">' .
      implode('', array_map(fn($i) => '<label>Number ' . ($i + 1) . '<input name="lines[]" maxlength="60" value="' . h(wa_lines()[$i] ?? '') . '" placeholder="e.g. Business 89823 46105"></label>', range(0, count(wa_lines())))) . '
      <button class="btn primary">Save numbers</button></form></section>
    <section class="card"><h2>Change password</h2><form method="post" class="stack">' . csrf_field() . '<input type="hidden" name="action" value="password">
      <label>Current password<input type="password" name="current" required autocomplete="current-password"></label>
      <label>New password (at least 10 characters)<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
      <label>Repeat new password<input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
      <button class="btn primary">Change password</button></form></section>
    <section class="card"><h2>Sign out</h2><div class="actions">
      <form method="post">' . csrf_field() . '<button class="btn" name="action" value="logout">Sign out</button></form>
      <form method="post">' . csrf_field() . '<button class="btn danger" name="action" value="logout_all">Sign out on all devices</button></form></div></section>');
}

/* Today (home) */
$t = today();
$ls = lead_scope('leads');
$due = $db->prepare("SELECT * FROM leads WHERE $notTest AND $open AND $ls AND follow_up IS NOT NULL AND follow_up <= ? ORDER BY follow_up, id");
$due->execute([$t]); $due = $due->fetchAll();
$fresh = $db->query("SELECT * FROM leads WHERE $notTest AND $ls AND COALESCE(stage, 'New') = 'New' AND (follow_up IS NULL OR follow_up = '') ORDER BY id DESC LIMIT 30")->fetchAll();
$soon = $db->prepare("SELECT COUNT(*) FROM leads WHERE $notTest AND $open AND $ls AND follow_up > ? AND follow_up <= ?");
$myTasks = open_tasks('(t.due_on IS NULL OR t.due_on <= ?)' . (is_owner() ? ' AND t.assigned_to IS NULL' : ''), [plus_days(3)], 50);
$soon->execute([$t, plus_days(7)]); $soon = (int)$soon->fetchColumn();

$monthStart = (new DateTime(date('Y-m-01 00:00:00')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
$m = $db->prepare("SELECT COUNT(*) n, SUM(stage = 'Won') won FROM leads WHERE $notTest AND created_at >= ?");
$m->execute([$monthStart]); $m = $m->fetch();
$w = $db->prepare("SELECT COUNT(*) n, COALESCE(SUM(quoted_amount), 0) v FROM leads WHERE $notTest AND stage = 'Won' AND won_at >= ?");
$w->execute([$monthStart]); $w = $w->fetch();
$bySrc = $db->prepare("SELECT COALESCE(NULLIF(source, ''), 'Other') s, COUNT(*) n, SUM(stage = 'Won') won FROM leads WHERE $notTest AND created_at >= ? GROUP BY s ORDER BY n DESC");
$bySrc->execute([$monthStart]); $bySrc = $bySrc->fetchAll();
$byLine = $db->prepare("SELECT line l, COUNT(*) n, SUM(stage = 'Won') won FROM leads WHERE $notTest AND created_at >= ? AND line IS NOT NULL AND line != '' GROUP BY l ORDER BY n DESC");
$byLine->execute([$monthStart]); $byLine = $byLine->fetchAll();
$byAd = $db->prepare("SELECT ad_code a, COUNT(*) n, SUM(stage = 'Won') won FROM leads WHERE $notTest AND created_at >= ? AND ad_code IS NOT NULL AND ad_code != '' GROUP BY a ORDER BY n DESC LIMIT 12");
$byAd->execute([$monthStart]); $byAd = $byAd->fetchAll();

$tbl = function (array $rows, string $head) {
    if (!$rows) return '';
    $o = '<table class="mini"><tr><th>' . h($head) . '</th><th>Leads</th><th>Won</th></tr>';
    foreach ($rows as $r) $o .= '<tr><td>' . h(reset($r)) . '</td><td>' . (int)$r['n'] . '</td><td>' . (int)$r['won'] . '</td></tr>';
    return $o . '</table>';
};

page('Today', '<h1>Today <span class="muted small">' . h(date('D, j M')) . '</span></h1>
<a class="btn primary big" href="?p=add">+ Add a lead</a>
' . team_summary_html() . today_deadlines_html() . '
<section><h2>' . (is_owner() && team_names() ? 'Your tasks' : 'Tasks') . ($myTasks ? ' <span class="n">' . count($myTasks) . '</span>' : '') . '</h2>
  <div class="list">' . (implode('', array_map('task_card', $myTasks)) ?: '<p class="muted">No tasks due in the next 3 days.</p>') . '</div>
  <p class="small"><a href="?p=tasks">All tasks</a> · <a href="?p=tasks&amp;new=1#new">+ New task</a></p></section>
<section><h2>Follow up today' . ($due ? ' <span class="n">' . count($due) . '</span>' : '') . '</h2>
  <div class="list">' . (implode('', array_map('lead_card', $due)) ?: '<p class="muted">Nothing due. ' . ($soon ? $soon . ' follow-up(s) in the next 7 days.' : '') . '</p>') . '</div></section>
<section><h2>New, not yet followed up' . ($fresh ? ' <span class="n">' . count($fresh) . '</span>' : '') . '</h2>
  <div class="list">' . (implode('', array_map('lead_card', $fresh)) ?: '<p class="muted">All new leads have a follow-up date.</p>') . '</div></section>
' . (is_owner() ? '<section class="card"><h2>This month</h2>
  <div class="stats"><div><b>' . (int)$m['n'] . '</b><span>new leads</span></div><div><b>' . (int)$w['n'] . '</b><span>won</span></div><div><b>' . h(rupees((int)$w['v'])) . '</b><span>won value</span></div></div>
  ' . $tbl(array_map(fn($r) => ['Source' => $r['s'], 'n' => $r['n'], 'won' => $r['won']], $bySrc), 'Source') . $tbl(array_map(fn($r) => ['Line' => $r['l'], 'n' => $r['n'], 'won' => $r['won']], $byLine), 'WhatsApp number') . $tbl(array_map(fn($r) => ['Ad' => $r['a'], 'n' => $r['n'], 'won' => $r['won']], $byAd), 'Ad code') . '
</section>' : ''));

/* ---------- Templates ---------- */

function service_select(string $current): string {
    $o = '<select name="service"><option value="">Select a service</option>';
    $found = $current === '';
    foreach (service_names() as $g) {
        $o .= '<optgroup label="' . h($g['group']) . '">';
        foreach ($g['items'] as $n) { $sel = $n === $current; $found = $found || $sel; $o .= '<option' . ($sel ? ' selected' : '') . '>' . h($n) . '</option>'; }
        $o .= '</optgroup>';
    }
    foreach (['Not sure, need advice', 'Other'] as $n) { $sel = $n === $current; $found = $found || $sel; $o .= '<option' . ($sel ? ' selected' : '') . '>' . h($n) . '</option>'; }
    if (!$found) $o .= '<option selected>' . h($current) . '</option>';
    return $o . '</select>';
}

function convert_card(array $lead): string {
    if ($lead['client_id'] && ($c = find_row('clients', (int)$lead['client_id']))) return '<p class="card">Client: <a href="?p=client&amp;id=' . (int)$c['id'] . '"><strong>' . h($c['name']) . '</strong></a></p>';
    $won = ($lead['stage'] ?: 'New') === 'Won';
    return '<form method="post" class="card' . ($won ? ' hl' : '') . '">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$lead['id'] . '">
      ' . ($won ? '<p><strong>Won.</strong> Make this a client to track the filing, application number, deadlines and payment.</p>' : '<p class="small muted">Already working for them? Make this a client to track the filing and deadlines.</p>') . '
      <button class="btn' . ($won ? ' primary' : '') . '" name="action" value="c_from_lead">Make client</button></form>';
}

function lead_card(array $r): string {
    $stage = $r['stage'] ?: 'New';
    $fu = $r['follow_up'];
    $fuHtml = $fu && in_array($stage, OPEN_STAGES, true) ? '<span class="' . ($fu < today() ? 'late' : ($fu === today() ? 'due' : '')) . '">⏰ ' . h(nice_date($fu)) . '</span>' : '';
    $wa = $r['phone'] ? '<a class="btn wa sm" href="https://wa.me/' . h(wa_number((string)$r['phone'])) . '" target="_blank" rel="noopener" aria-label="WhatsApp ' . h($r['name']) . '">WhatsApp</a>' : '';
    return '<div class="lead"><a class="body" href="?p=lead&amp;id=' . (int)$r['id'] . '">
      <div class="top"><strong>' . h($r['name'] ?: 'No name') . '</strong><span class="badge st-' . strtolower($stage) . '">' . h($stage) . '</span></div>
      <div class="muted small">' . h($r['service'] ?: 'Service not set') . '</div>
      <div class="meta small">' . $fuHtml . '<span>' . h($r['source'] ?: '') . ($r['ad_code'] ? ' · ' . h($r['ad_code']) : '') . (!empty($r['line']) ? ' · ' . h($r['line']) : '') . '</span><span>' . h(ist($r['created_at'], 'j M')) . '</span>' . (!empty($r['payment_status']) && $r['payment_status'] !== 'Pending' && show_money() ? '<span>₹ ' . h($r['payment_status']) . '</span>' : '') . (is_owner() && !empty($r['assigned_to']) ? '<span>👤 ' . h(person_name((int)$r['assigned_to'])) . '</span>' : '') . (!empty($r['is_test']) ? '<span class="late">TEST</span>' : '') . '</div>
    </a>' . $wa . '</div>';
}

function page(string $title, string $body, bool $nav = true): void {
    global $p;
    $msg = flash();
    $tab = fn($k, $label, $also = []) => '<a href="?p=' . $k . '"' . ($p === $k || in_array($p, $also, true) ? ' aria-current="page"' : '') . '>' . $label . '</a>';
    echo '<!doctype html><html lang="en-IN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><title>' . h($title) . ' · Leegtech leads</title>
<link rel="stylesheet" href="/admin/admin.css?v=7"><link rel="icon" href="/favicon.ico"></head><body>'
    . ($nav ? '<header class="bar">' . ($p !== 'home' ? '<a href="' . h(back_href()) . '" class="back" id="backbtn" aria-label="Back" title="Back">←</a>' : '')
        . '<a href="?p=home" class="brand">Leegtech leads</a><a href="?p=settings" class="small">' . (me() && !is_owner() ? h(me()['name']) . ' · ' : '') . 'Settings</a></header>' : '')
    . '<main>' . ($msg ? '<p class="flash" role="status">' . h($msg) . '</p>' : '') . $body . '</main>'
    . ($nav ? '<nav class="tabs">' . $tab('home', 'Today') . $tab('leads', 'Leads') . $tab('clients', 'Clients', ['clients', 'client', 'matter', 'ip', 'import', 'newclient', 'deadlines']) . $tab('add', '+ Add') . '</nav>' : '')
    . ($nav ? '<script src="/admin/admin.js?v=1" defer></script>' : '') . '</body></html>';
    exit;
}

/** Where the back button goes when there is no earlier screen in this tab (e.g. opened from the email). */
function back_href(): string {
    global $p;
    if ($p === 'lead') return '?p=leads';
    if ($p === 'matter' && ($m = find_row('matters', (int)get('id', 12)))) return '?p=client&id=' . (int)$m['client_id'];
    if ($p === 'import') return '?p=ip&t=' . (array_key_exists(get('t', 12), IP_TYPES) ? get('t', 12) : 'design');
    if (in_array($p, ['client', 'matter', 'ip', 'newclient', 'deadlines'], true)) return '?p=clients';
    return '?p=home';
}
