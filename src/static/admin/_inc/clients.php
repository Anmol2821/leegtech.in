<?php
/*
 * Lead tracker: clients, matters (each filing / job for a client), deadlines and the daily summary email.
 * Included by /admin/index.php (screens + actions) and by _inc/daily.php (hourly cron, sends the email once a day).
 * Deadlines are typed in by the team from the official notice; nothing here calculates statutory dates.
 */
declare(strict_types=1);
if (!defined('LT_ADMIN')) { http_response_code(404); exit; }

const MATTER_STATUSES = ['Documents pending', 'Drafting', 'Ready to file', 'Filed', 'Under examination', 'Objection / FER received',
    'Reply filed', 'Hearing scheduled', 'Published / advertised', 'Registered / granted', 'Completed', 'On hold'];
const DEADLINE_KINDS = ['Filing due', 'Reply to examination report / FER', 'Reply to objection', 'Hearing', 'Opposition', 'Renewal',
    'Annual / ROC filing', 'GST / tax return', 'Documents from client', 'Payment from client', 'Other'];
const DEADLINE_WINDOW = 15; // days ahead shown on Today and in the email

function clients_migrate(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS clients (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL, name TEXT NOT NULL,
        phone TEXT, phone_key TEXT, email TEXT, city TEXT, company TEXT, notes TEXT, lead_id INTEGER, updated_at TEXT)');
    $db->exec('CREATE TABLE IF NOT EXISTS matters (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER NOT NULL, created_at TEXT NOT NULL,
        service TEXT, title TEXT, app_no TEXT, status TEXT, invoice_no TEXT, amount INTEGER, paid INTEGER DEFAULT 0, closed INTEGER DEFAULT 0, updated_at TEXT)');
    $db->exec('CREATE TABLE IF NOT EXISTS deadlines (id INTEGER PRIMARY KEY AUTOINCREMENT, matter_id INTEGER NOT NULL, kind TEXT, due_on TEXT NOT NULL,
        note TEXT, done_at TEXT, created_at TEXT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS matter_log (id INTEGER PRIMARY KEY AUTOINCREMENT, matter_id INTEGER NOT NULL, created_at TEXT NOT NULL, text TEXT)');
    $db->exec('CREATE INDEX IF NOT EXISTS matters_client ON matters(client_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS deadlines_due ON deadlines(due_on)');
    $have = array_column($db->query('PRAGMA table_info(leads)')->fetchAll(), 'name');
    if (!in_array('client_id', $have, true)) $db->exec('ALTER TABLE leads ADD COLUMN client_id INTEGER');
    $have = array_column($db->query('PRAGMA table_info(matters)')->fetchAll(), 'name');
    if (!in_array('payment_status', $have, true)) {
        $db->exec("ALTER TABLE matters ADD COLUMN payment_status TEXT DEFAULT 'Pending'");
        $db->exec("UPDATE matters SET payment_status = CASE WHEN paid = 1 THEN 'Fully paid' ELSE 'Pending' END");
    }
    if (!in_array('received', $have, true)) $db->exec('ALTER TABLE matters ADD COLUMN received INTEGER');
    if (!in_array('filed_on', $have, true)) $db->exec('ALTER TABLE matters ADD COLUMN filed_on TEXT');
}

function cdb(): PDO { static $done = false; $db = db(); if (!$done) { $done = true; clients_migrate($db); if (function_exists('portfolio_migrate')) portfolio_migrate($db); if (function_exists('team_migrate')) team_migrate($db); if (function_exists('portal_migrate')) portal_migrate($db); } return $db; }

function find_row(string $table, int $id): ?array {
    $st = cdb()->prepare("SELECT * FROM $table WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function mlog(int $matterId, string $text): void {
    cdb()->prepare('INSERT INTO matter_log (matter_id, created_at, text, by_name) VALUES (?, ?, ?, ?)')->execute([$matterId, now_utc(), $text, function_exists('by_name') ? by_name() : null]);
}

function opt_list(array $items, ?string $current, string $blank = ''): string {
    $o = $blank !== '' ? '<option value="">' . h($blank) . '</option>' : '';
    $found = !$current;
    foreach ($items as $i) { $sel = $i === $current; $found = $found || $sel; $o .= '<option' . ($sel ? ' selected' : '') . '>' . h($i) . '</option>'; }
    if (!$found) $o .= '<option selected>' . h((string)$current) . '</option>';
    return $o;
}

function due_class(string $d): string { return $d < today() ? 'late' : ($d <= plus_days(3) ? 'due' : ''); }

/** Open deadlines with client and matter, due on or before $until (null = all). $scope: SQL on d/m; default = what the signed-in person may see. */
function open_deadlines(?string $until, ?string $scope = null): array {
    $scope ??= deadline_scope('d', 'm') . ' AND ' . who_sql('COALESCE(d.assigned_to, m.assigned_to)');
    $sql = 'SELECT d.*, m.service, m.title, m.app_no, m.applicant, m.client_id, m.assigned_to matter_assigned, c.name client_name, c.phone client_phone FROM deadlines d
        JOIN matters m ON m.id = d.matter_id JOIN clients c ON c.id = m.client_id WHERE d.done_at IS NULL AND m.closed = 0 AND ' . $scope;
    $args = [];
    if ($until) { $sql .= ' AND d.due_on <= ?'; $args[] = $until; }
    $st = cdb()->prepare($sql . ' ORDER BY d.due_on, d.id');
    $st->execute($args);
    return $st->fetchAll();
}

function deadline_card(array $d, bool $showClient = true): string {
    $f = csrf_field() . '<input type="hidden" name="deadline_id" value="' . (int)$d['id'] . '">';
    $left = (int)round((strtotime($d['due_on']) - strtotime(today())) / 86400);
    $when = $left < 0 ? abs($left) . ' day' . ($left === -1 ? '' : 's') . ' overdue' : ($left === 0 ? 'Due today' : 'in ' . $left . ' day' . ($left === 1 ? '' : 's'));
    return '<div class="lead"><a class="body" href="?p=matter&amp;id=' . (int)$d['matter_id'] . '">
      <div class="top"><strong>' . h($d['kind'] ?: 'Deadline') . '</strong><span class="' . due_class($d['due_on']) . ' small">' . h(nice_date($d['due_on'])) . '</span></div>
      <div class="muted small">' . ($showClient ? h($d['client_name']) . ' · ' : '') . h(trim(($d['service'] ?? '') . ($d['title'] ? ' – ' . $d['title'] : ''))) . ($d['app_no'] ? ' · ' . h($d['app_no']) : '') . '</div>
      <div class="meta small"><span class="' . due_class($d['due_on']) . '">⏳ ' . h($when) . '</span>' . ($d['applicant'] ? '<span>' . h($d['applicant']) . '</span>' : '') . ($d['note'] ? '<span>' . h($d['note']) . '</span>' : '')
        . (can('all') && ($who = (int)($d['assigned_to'] ?: $d['matter_assigned'])) ? '<span>👤 ' . h(person_name($who)) . '</span>' : '') . '</div>
    </a><form method="post">' . $f . '<button class="btn sm" name="action" value="c_deadline_done">Done</button></form></div>';
}

function client_card(array $c): string {
    return '<div class="lead"><a class="body" href="?p=client&amp;id=' . (int)$c['id'] . '">
      <div class="top"><strong>' . h($c['name']) . '</strong>' . ((int)$c['open_matters'] ? '<span class="badge st-contacted">' . (int)$c['open_matters'] . ' open</span>' : '') . '</div>
      <div class="muted small">' . h(implode(' · ', array_filter([$c['company'], $c['city'], $c['phone']]))) . '</div>
      <div class="meta small">' . ($c['next_due'] ? '<span class="' . due_class($c['next_due']) . '">⏳ Next deadline ' . h(nice_date($c['next_due'])) . '</span>' : '')
        . ((int)$c['unpaid'] && show_money() ? '<span class="late">' . (int)$c['unpaid'] . ' payment' . ((int)$c['unpaid'] === 1 ? '' : 's') . ' pending</span>' : '') . '</div>
    </a>' . ($c['phone'] ? '<a class="btn wa sm" href="https://wa.me/' . h(wa_number((string)$c['phone'])) . '" target="_blank" rel="noopener">WhatsApp</a>' : '') . '</div>';
}

/** Find a client by phone, or create one from the given details. Returns client id. */
function client_for(array $x, ?int $leadId = null): int {
    $db = cdb();
    $key = phone_key((string)($x['phone'] ?? ''));
    if (strlen($key) >= 8) {
        $st = $db->prepare('SELECT id FROM clients WHERE phone_key = ? LIMIT 1');
        $st->execute([$key]);
        if ($id = $st->fetchColumn()) return (int)$id;
    }
    $db->prepare('INSERT INTO clients (created_at, name, phone, phone_key, email, city, company, lead_id, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([now_utc(), $x['name'] ?: 'No name', $x['phone'] ?? '', $key, strtolower((string)($x['email'] ?? '')), $x['city'] ?? '', $x['company'] ?? '', $leadId, now_utc()]);
    return (int)$db->lastInsertId();
}

function create_matter(int $clientId, string $service, string $title = '', string $status = 'Documents pending', ?int $amount = null, ?int $assignee = null): int {
    if ($assignee === null && !is_owner()) $assignee = my_id();
    cdb()->prepare('INSERT INTO matters (client_id, created_at, service, title, status, amount, updated_at, ip_type, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$clientId, now_utc(), $service, $title, $status, $amount, now_utc(), function_exists('ip_type_for_service') ? ip_type_for_service($service) : '', $assignee]);
    $id = (int)cdb()->lastInsertId();
    mlog($id, 'Matter opened' . ($service ? ': ' . $service : ''));
    return $id;
}

/* ---------------- Actions ---------------- */

function clients_action(string $a): void {
    $db = cdb();
    $self = '/admin/';

    if ($a === 'c_import_preview' || $a === 'c_import_run') require_can('import', '/admin/?p=clients');
    if ($a === 'c_import_preview') import_preview();
    if ($a === 'c_import_run') import_run();

    if ($a === 'c_from_lead') {
        $lead = find_lead((int)post('id', 12));
        if (!$lead) redirect($self . '?p=leads', 'That lead was not found.');
        require_see('leads', (int)$lead['id']);
        if ($lead['client_id'] && find_row('clients', (int)$lead['client_id'])) redirect($self . '?p=client&id=' . $lead['client_id']);
        $cid = client_for($lead, (int)$lead['id']);
        $db->prepare('UPDATE leads SET client_id = ?, updated_at = ? WHERE id = ?')->execute([$cid, now_utc(), $lead['id']]);
        $mid = create_matter($cid, (string)$lead['service'], '', 'Documents pending', $lead['quoted_amount'] === null ? null : (int)$lead['quoted_amount'], $lead['assigned_to'] ? (int)$lead['assigned_to'] : null);
        $pst = $lead['payment_status'] ?: 'Pending';
        $db->prepare('UPDATE matters SET payment_status = ?, received = ?, paid = ? WHERE id = ?')->execute([$pst, $lead['received'], $pst === 'Fully paid' ? 1 : 0, $mid]);
        if ($pst !== 'Pending' || $lead['received']) mlog($mid, 'Payment from lead: ' . $pst . ($lead['received'] ? ', received ' . rupees((int)$lead['received']) : ''));
        log_activity((int)$lead['id'], 'stage', 'Converted to client #' . $cid);
        redirect($self . '?p=matter&id=' . $mid, 'Client and matter created. Add the application number and deadlines below.');
    }

    if ($a === 'c_new') {
        $name = post('name', 120);
        if ($name === '') redirect($self . '?p=newclient', 'Enter the client name.');
        if (!can('all') && post('service', 160) === '') redirect($self . '?p=newclient', 'Choose the service (the first matter) so the client shows in your work.');
        $existing = null;
        $key = phone_key(post('phone', 25));
        if (strlen($key) >= 8) { $st = $db->prepare('SELECT id FROM clients WHERE phone_key = ?'); $st->execute([$key]); $existing = $st->fetchColumn() ?: null; }
        $cid = $existing ? (int)$existing : client_for(['name' => $name, 'phone' => post('phone', 25), 'email' => post('email', 160), 'city' => post('city', 80), 'company' => post('company', 120)]);
        $service = post('service', 160);
        if ($service !== '') {
            $mid = create_matter($cid, $service, post('title', 160), in_array(post('status'), MATTER_STATUSES, true) ? post('status') : 'Documents pending');
            if (($app = post('app_no', 60)) !== '') $db->prepare('UPDATE matters SET app_no = ? WHERE id = ?')->execute([$app, $mid]);
            redirect($self . '?p=matter&id=' . $mid, $existing ? 'This number already belongs to a client, so the matter was added to them.' : 'Client saved.');
        }
        redirect($self . '?p=client&id=' . $cid, $existing ? 'This number already belongs to a client, so that client is open.' : 'Client saved.');
    }

    if ($a === 'c_edit') {
        $c = find_row('clients', (int)post('client_id', 12));
        if (!$c) redirect($self . '?p=clients', 'Client not found.');
        require_see('clients', (int)$c['id']);
        $phone = post('phone', 25);
        $db->prepare('UPDATE clients SET name = ?, phone = ?, phone_key = ?, email = ?, city = ?, company = ?, notes = ?, updated_at = ? WHERE id = ?')
            ->execute([post('name', 120) ?: $c['name'], $phone, phone_key($phone), strtolower(post('email', 160)), post('city', 80), post('company', 120), post('notes', 3000), now_utc(), $c['id']]);
        redirect($self . '?p=client&id=' . $c['id'], 'Saved.');
    }

    if ($a === 'c_add_matter') {
        $c = find_row('clients', (int)post('client_id', 12));
        if (!$c) redirect($self . '?p=clients', 'Client not found.');
        require_see('clients', (int)$c['id']);
        $mid = create_matter((int)$c['id'], post('service', 160), post('title', 160), 'Documents pending');
        redirect($self . '?p=matter&id=' . $mid, 'Matter added.');
    }

    if ($a === 'c_delete_client') {
        require_can('delete', $self . '?p=clients');
        $c = find_row('clients', (int)post('client_id', 12));
        if (!$c) redirect($self . '?p=clients');
        if (post('confirm') !== 'yes') redirect($self . '?p=client&id=' . $c['id'], 'Tick the box to confirm deleting.');
        $mids = $db->prepare('SELECT id FROM matters WHERE client_id = ?'); $mids->execute([$c['id']]);
        foreach ($mids->fetchAll(PDO::FETCH_COLUMN) as $mid) delete_matter((int)$mid);
        $db->prepare('UPDATE leads SET client_id = NULL WHERE client_id = ?')->execute([$c['id']]);
        $db->prepare('DELETE FROM clients WHERE id = ?')->execute([$c['id']]);
        redirect($self . '?p=clients', 'Client deleted.');
    }

    if ($a === 'c_deadline_assign') {
        require_can('assign');
        $d = find_row('deadlines', (int)post('deadline_id', 12));
        if (!$d) redirect($self, 'Deadline not found.');
        $who = posted_person();
        $db->prepare('UPDATE deadlines SET assigned_to = ? WHERE id = ?')->execute([$who, $d['id']]);
        mlog((int)$d['matter_id'], $d['kind'] . ' (' . nice_date($d['due_on']) . '): ' . ($who ? 'assigned to ' . person_name($who) : 'same person as the matter'));
        if ($who && $who !== (int)$d['assigned_to']) notify_assigned($who, $d['kind'] . ' due ' . nice_date($d['due_on']), '?p=matter&id=' . $d['matter_id']);
        redirect($self . '?p=matter&id=' . $d['matter_id'], 'Saved.');
    }

    if ($a === 'c_deadline_done' || $a === 'c_deadline_undo' || $a === 'c_deadline_delete') {
        $d = find_row('deadlines', (int)post('deadline_id', 12));
        if (!$d) redirect($self, 'Deadline not found.');
        require_see('matters', (int)$d['matter_id']);
        if ($a === 'c_deadline_delete') require_can('delete', $self . '?p=matter&id=' . $d['matter_id']);
        if ($a === 'c_deadline_done') { $db->prepare('UPDATE deadlines SET done_at = ? WHERE id = ?')->execute([now_utc(), $d['id']]); mlog((int)$d['matter_id'], 'Done: ' . $d['kind'] . ' (due ' . nice_date($d['due_on']) . ')'); }
        if ($a === 'c_deadline_undo') { $db->prepare('UPDATE deadlines SET done_at = NULL WHERE id = ?')->execute([$d['id']]); mlog((int)$d['matter_id'], 'Reopened: ' . $d['kind']); }
        if ($a === 'c_deadline_delete') { $db->prepare('DELETE FROM deadlines WHERE id = ?')->execute([$d['id']]); mlog((int)$d['matter_id'], 'Removed deadline: ' . $d['kind'] . ' (' . $d['due_on'] . ')'); }
        $back = post('back', 20) === 'matter' ? $self . '?p=matter&id=' . $d['matter_id'] : ($_SERVER['HTTP_REFERER'] ?? $self);
        if (!str_starts_with((string)parse_url($back, PHP_URL_PATH), '/admin/')) $back = $self;
        redirect($back, $a === 'c_deadline_done' ? 'Marked done.' : 'Updated.');
    }

    // Everything below works on one matter.
    $m = find_row('matters', (int)post('matter_id', 12));
    if (!$m) redirect($self . '?p=clients', 'Matter not found.');
    $mid = (int)$m['id'];
    $back = $self . '?p=matter&id=' . $mid;
    require_see('matters', $mid);
    if ($a === 'c_pay') require_can('money', $back);
    if ($a === 'c_delete_matter') require_can('delete', $back);
    if ($a === 'c_assign') require_can('assign', $back);

    if ($a === 'c_assign') {
        $who = posted_person();
        if ($who !== ($m['assigned_to'] === null ? null : (int)$m['assigned_to'])) {
            $db->prepare('UPDATE matters SET assigned_to = ?, updated_at = ? WHERE id = ?')->execute([$who, now_utc(), $mid]);
            mlog($mid, $who ? 'Assigned to ' . person_name($who) : 'Unassigned');
            notify_assigned($who, trim(($m['service'] ?: 'Matter') . ($m['applicant'] ? ' – ' . $m['applicant'] : '')), '?p=matter&id=' . $mid, $m['app_no'] ? 'Application no. ' . $m['app_no'] : '');
        }
        redirect($back, $who ? 'Assigned to ' . person_name($who) . '.' : 'Unassigned.');
    }

    if ($a === 'c_status') {
        $s = post('status', 60);
        if (in_array($s, MATTER_STATUSES, true) && $s !== $m['status']) {
            $db->prepare('UPDATE matters SET status = ?, updated_at = ? WHERE id = ?')->execute([$s, now_utc(), $mid]);
            mlog($mid, 'Status: ' . ($m['status'] ?: '–') . ' → ' . $s);
            if ($s === 'Filed' && !$m['filed_on']) {
                $db->prepare('UPDATE matters SET filed_on = ? WHERE id = ?')->execute([today(), $mid]);
                mlog($mid, 'Filing date set to today (' . today() . '). Change it under Edit details if needed.');
            }
        }
        redirect($back, 'Status: ' . $s);
    }
    if ($a === 'c_add_deadline') {
        $due = post('due_on', 10);
        if (!valid_date($due)) redirect($back, 'Pick the due date.');
        $kind = in_array(post('kind'), DEADLINE_KINDS, true) ? post('kind') : 'Other';
        $who = can('assign') ? posted_person() : ((int)$m['assigned_to'] === my_id() ? null : my_id());
        $db->prepare('INSERT INTO deadlines (matter_id, kind, due_on, note, created_at, assigned_to) VALUES (?, ?, ?, ?, ?, ?)')->execute([$mid, $kind, $due, post('note', 300), now_utc(), $who]);
        mlog($mid, 'Deadline added: ' . $kind . ' on ' . nice_date($due) . ' (' . $due . ')' . ($who ? ' for ' . person_name($who) : ''));
        notify_assigned($who ?: ($m['assigned_to'] ? (int)$m['assigned_to'] : null), $kind . ' due ' . nice_date($due), '?p=matter&id=' . $mid, trim(($m['service'] ?: '') . ($m['applicant'] ? ' – ' . $m['applicant'] : '') . ($m['app_no'] ? ' · No. ' . $m['app_no'] : '')));
        redirect($back, 'Deadline saved: ' . nice_date($due));
    }
    if ($a === 'c_matter_note') {
        if (($n = post('note', 2000)) !== '') mlog($mid, $n);
        redirect($back, 'Note added.');
    }
    if ($a === 'c_matter_edit') {
        $amt = post('amount', 12); $amt = $amt !== '' && ctype_digit($amt) ? (int)$amt : null;
        if (!show_money()) { $amt = $m['amount'] === null ? null : (int)$m['amount']; $_POST['invoice_no'] = (string)$m['invoice_no']; }
        $filed = post('filed_on', 10); $filed = valid_date($filed) ? $filed : null;
        $ipt = array_key_exists(post('ip_type', 12), IP_TYPES) ? post('ip_type', 12) : (post('ip_type', 12) === 'none' ? '' : ip_type_for_service(post('service', 160)));
        $db->prepare('UPDATE matters SET service = ?, title = ?, app_no = ?, invoice_no = ?, amount = ?, filed_on = ?, applicant = ?, office_status = ?, ip_type = ?, updated_at = ? WHERE id = ?')
            ->execute([post('service', 160), post('title', 160), post('app_no', 60), post('invoice_no', 60), $amt, $filed, post('applicant', 160), post('office_status', 400), $ipt, now_utc(), $mid]);
        if ($filed !== ($m['filed_on'] ?: null)) mlog($mid, $filed ? 'Filing date: ' . nice_date($filed) . ' (' . $filed . ')' : 'Filing date cleared');
        if ($amt !== null && $m['payment_status'] !== 'Fully paid' && $m['received'] !== null && (int)$m['received'] >= $amt)
            $db->prepare("UPDATE matters SET payment_status = 'Fully paid', paid = 1 WHERE id = ?")->execute([$mid]);
        if (post('app_no', 60) !== (string)$m['app_no'] && post('app_no', 60) !== '') mlog($mid, 'Application / reference no.: ' . post('app_no', 60));
        redirect($back, 'Saved.');
    }
    if ($a === 'c_pay') {
        $oldRec = $m['received'] === null ? null : (int)$m['received'];
        [$st, $rec] = payment_from_post($m['amount'] === null ? null : (int)$m['amount'], $m['payment_status'], $oldRec);
        $db->prepare('UPDATE matters SET payment_status = ?, received = ?, paid = ?, updated_at = ? WHERE id = ?')->execute([$st, $rec, $st === 'Fully paid' ? 1 : 0, now_utc(), $mid]);
        if ($st !== ($m['payment_status'] ?: 'Pending') || $rec !== $oldRec) mlog($mid, 'Payment: ' . $st . ($rec ? ', received ' . rupees($rec) : ''));
        redirect($back, 'Payment: ' . $st);
    }
    if ($a === 'c_close') {
        $closed = post('closed', 1) === '1' ? 1 : 0;
        $db->prepare('UPDATE matters SET closed = ?, updated_at = ? WHERE id = ?')->execute([$closed, now_utc(), $mid]);
        mlog($mid, $closed ? 'Matter closed' : 'Matter reopened');
        redirect($back, $closed ? 'Matter closed. Its deadlines no longer show as due.' : 'Matter reopened.');
    }
    if ($a === 'c_delete_matter') {
        if (post('confirm') !== 'yes') redirect($back, 'Tick the box to confirm deleting.');
        delete_matter($mid);
        redirect($self . '?p=client&id=' . $m['client_id'], 'Matter deleted.');
    }
    redirect($back);
}

function delete_matter(int $mid): void {
    $db = cdb();
    $db->prepare('DELETE FROM deadlines WHERE matter_id = ?')->execute([$mid]);
    $db->prepare('DELETE FROM matter_log WHERE matter_id = ?')->execute([$mid]);
    $db->prepare('DELETE FROM matters WHERE id = ?')->execute([$mid]);
}

/* ---------------- Screens ---------------- */

function clients_page(string $p): void {
    $db = cdb();

    if ($p === 'clients') {
        $q = get('q', 80); $filter = get('f', 10);
        $where = [client_scope('c')]; $args = [];
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $digits = preg_replace('/\D/', '', $q);
            $where[] = "(c.name LIKE ? ESCAPE '\\' OR c.company LIKE ? ESCAPE '\\' OR c.email LIKE ? ESCAPE '\\' OR c.city LIKE ? ESCAPE '\\'
                OR EXISTS (SELECT 1 FROM matters m2 WHERE m2.client_id = c.id AND (m2.app_no LIKE ? ESCAPE '\\' OR m2.title LIKE ? ESCAPE '\\' OR m2.service LIKE ? ESCAPE '\\' OR m2.applicant LIKE ? ESCAPE '\\'))"
                . (strlen($digits) >= 4 ? ' OR c.phone_key LIKE ?' : '') . ')';
            array_push($args, $like, $like, $like, $like, $like, $like, $like, $like);
            if (strlen($digits) >= 4) $args[] = '%' . $digits . '%';
        }
        if ($filter === 'unpaid' && show_money()) $where[] = 'EXISTS (SELECT 1 FROM matters m3 WHERE m3.client_id = c.id AND m3.paid = 0 AND m3.amount > 0)';
        $st = $db->prepare('SELECT c.*,
            (SELECT COUNT(*) FROM matters m WHERE m.client_id = c.id AND m.closed = 0) open_matters,
            (SELECT COUNT(*) FROM matters m WHERE m.client_id = c.id AND m.paid = 0 AND m.amount > 0) unpaid,
            (SELECT MIN(d.due_on) FROM deadlines d JOIN matters m ON m.id = d.matter_id WHERE m.client_id = c.id AND d.done_at IS NULL AND m.closed = 0) next_due
            FROM clients c WHERE ' . implode(' AND ', $where) . ' ORDER BY c.name COLLATE NOCASE LIMIT 500');
        $st->execute($args);
        $rows = $st->fetchAll();
        $pending = show_money() ? (int)$db->query('SELECT COALESCE(SUM(MAX(amount - COALESCE(received, 0), 0)), 0) FROM matters WHERE paid = 0 AND amount > 0')->fetchColumn() : 0;
        page('Clients', portfolio_tabs('clients') . '<h1>Clients</h1>
        <div class="actions"><a class="btn primary" href="?p=newclient">+ New client</a><a class="btn" href="?p=deadlines">All deadlines</a></div>
        <form class="search" method="get"><input type="hidden" name="p" value="clients"><input type="search" name="q" value="' . h($q) . '" placeholder="Search name, phone, application no.">
          ' . (show_money() ? '<select name="f"><option value="">All clients</option><option value="unpaid"' . ($filter === 'unpaid' ? ' selected' : '') . '>Payment pending</option></select>' : '') . '<button class="btn">Search</button></form>
        ' . ($pending ? '<p class="small">Balance pending across all matters: <strong class="late">' . h(rupees($pending)) . '</strong> · <a href="?p=clients&amp;f=unpaid">show</a></p>' : '') . '
        <div class="list">' . (implode('', array_map('client_card', $rows)) ?: '<p class="muted">No clients yet. When a lead is won, open it and tap "Make client", or add one with "+ New client".</p>') . '</div>');
    }

    if ($p === 'newclient') {
        page('New client', '<h1>New client</h1><p class="muted small">For existing clients and work that did not come through a lead. For a won lead, open the lead and tap "Make client" instead.</p>
        <form method="post" class="stack">' . csrf_field() . '<input type="hidden" name="action" value="c_new">
          <label>Client name *<input name="name" required maxlength="120"></label>
          <label>Phone / WhatsApp<input name="phone" type="tel" inputmode="tel" maxlength="25"></label>
          <label>Company / brand<input name="company" maxlength="120"></label>
          <div class="row2"><label>Email<input name="email" type="email" maxlength="160"></label><label>City<input name="city" maxlength="80"></label></div>
          <h2>First matter (optional)</h2>
          <label>Service' . service_select('') . '</label>
          <label>Title<input name="title" maxlength="160" placeholder="e.g. brand name, invention title, company name"></label>
          <div class="row2"><label>Application / ref. no.<input name="app_no" maxlength="60"></label>
          <label>Status<select name="status">' . opt_list(MATTER_STATUSES, 'Documents pending') . '</select></label></div>
          <button class="btn primary big">Save client</button></form>');
    }

    if ($p === 'client') {
        $c = find_row('clients', (int)get('id', 12));
        if (!$c) redirect('/admin/?p=clients', 'Client not found.');
        $cid = (int)$c['id'];
        require_see('clients', $cid);
        $st = $db->prepare('SELECT m.*, (SELECT MIN(due_on) FROM deadlines d WHERE d.matter_id = m.id AND d.done_at IS NULL) next_due FROM matters m WHERE client_id = ? AND ' . matter_scope('m') . ' ORDER BY closed, id DESC');
        $st->execute([$cid]);
        $ms = '';
        foreach ($st->fetchAll() as $m) $ms .= '<div class="lead"><a class="body" href="?p=matter&amp;id=' . (int)$m['id'] . '">
            <div class="top"><strong>' . h($m['service'] ?: 'Matter') . '</strong><span class="badge ' . ($m['closed'] ? 'st-lost' : (function_exists('status_class') ? status_class((string)$m['status']) : 'st-consulted')) . '">' . h($m['closed'] ? 'Closed' : ($m['status'] ?: 'Open')) . '</span></div>
            <div class="muted small">' . h(implode(' · ', array_filter([$m['applicant'], $m['title'], $m['app_no']]))) . '</div>
            <div class="meta small">' . ($m['next_due'] && !$m['closed'] ? '<span class="' . due_class($m['next_due']) . '">⏳ ' . h(nice_date($m['next_due'])) . '</span>' : '')
            . ($m['amount'] && show_money() ? '<span class="' . ($m['paid'] ? '' : 'late') . '">' . h(rupees((int)$m['amount'])) . ' · ' . h($m['payment_status'] ?: 'Pending') . '</span>' : '')
            . ($m['filed_on'] ? '<span>Filed ' . h(nice_date($m['filed_on'])) . '</span>' : '') . (can('all') && $m['assigned_to'] ? '<span>👤 ' . h(person_name((int)$m['assigned_to'])) . '</span>' : '') . '</div></a></div>';
        $lead = $c['lead_id'] ? find_lead((int)$c['lead_id']) : null;
        $f = csrf_field() . '<input type="hidden" name="client_id" value="' . $cid . '">';
        $contact = '<div class="actions">' . ($c['phone'] ? '<a class="btn wa" href="https://wa.me/' . h(wa_number((string)$c['phone'])) . '" target="_blank" rel="noopener">WhatsApp</a><a class="btn" href="tel:+' . h(wa_number((string)$c['phone'])) . '">Call</a>' : '')
            . ($c['email'] ? '<a class="btn" href="mailto:' . h($c['email']) . '">Email</a>' : '') . '</div>';
        page($c['name'], '<p class="small"><a href="?p=clients">← All clients</a></p>
        <h1>' . h($c['name']) . '</h1><p class="muted">' . h(implode(' · ', array_filter([$c['company'], $c['city'], $c['phone'], $c['email']]))) . '</p>' . $contact .
        ($c['notes'] ? '<p class="card">' . nl2br(h($c['notes'])) . '</p>' : '') . '
        <h2>Matters</h2><div class="list">' . ($ms ?: '<p class="muted">No matters yet.</p>') . '</div>
        ' . portal_admin_card($c) . documents_admin_html($cid, null) . '
        <details class="card"><summary>+ Add a matter</summary><form method="post" class="stack">' . $f . '<input type="hidden" name="action" value="c_add_matter">
          <label>Service' . service_select('') . '</label><label>Title<input name="title" maxlength="160" placeholder="e.g. brand name, invention title"></label>
          <button class="btn primary">Add matter</button></form></details>
        <details class="card"><summary>Edit client</summary><form method="post" class="stack">' . $f . '<input type="hidden" name="action" value="c_edit">
          <label>Name<input name="name" maxlength="120" value="' . h($c['name']) . '"></label>
          <label>Phone<input name="phone" type="tel" maxlength="25" value="' . h($c['phone']) . '"></label>
          <label>Company / brand<input name="company" maxlength="120" value="' . h($c['company']) . '"></label>
          <div class="row2"><label>Email<input name="email" type="email" maxlength="160" value="' . h($c['email']) . '"></label><label>City<input name="city" maxlength="80" value="' . h($c['city']) . '"></label></div>
          <label>Notes<textarea name="notes" rows="3" maxlength="3000">' . h($c['notes']) . '</textarea></label>
          <button class="btn primary">Save</button></form></details>
        ' . ($lead ? '<p class="small muted">From lead <a href="?p=lead&amp;id=' . (int)$lead['id'] . '">#' . (int)$lead['id'] . '</a> (' . h($lead['source']) . ($lead['ad_code'] ? ', ' . h($lead['ad_code']) : '') . ')</p>' : '') . '
        ' . (can('delete') ? '<details class="card"><summary>Delete this client</summary><form method="post" class="stack">' . $f . '<input type="hidden" name="action" value="c_delete_client">
          <label class="check"><input type="checkbox" name="confirm" value="yes" required> Yes, permanently delete this client, all their matters and deadlines</label>
          <button class="btn danger">Delete</button></form></details>' : ''));
    }

    if ($p === 'matter') {
        $m = find_row('matters', (int)get('id', 12));
        if (!$m) redirect('/admin/?p=clients', 'Matter not found.');
        $mid = (int)$m['id'];
        require_see('matters', $mid);
        $c = find_row('clients', (int)$m['client_id']) ?? ['id' => 0, 'name' => '(deleted client)', 'phone' => ''];
        $f = csrf_field() . '<input type="hidden" name="matter_id" value="' . $mid . '">';
        $st = $db->prepare('SELECT * FROM deadlines WHERE matter_id = ? ORDER BY done_at IS NOT NULL, due_on');
        $st->execute([$mid]);
        $dl = '';
        foreach ($st->fetchAll() as $d) {
            $df = csrf_field() . '<input type="hidden" name="deadline_id" value="' . (int)$d['id'] . '"><input type="hidden" name="back" value="matter">';
            $done = $d['done_at'] !== null;
            $dl .= '<li class="dl' . ($done ? ' done' : '') . '"><div><strong>' . h($d['kind']) . '</strong> <span class="' . ($done ? 'muted' : due_class($d['due_on'])) . '">' . h(nice_date($d['due_on'])) . '</span>'
                . ($d['note'] ? '<div class="small muted">' . h($d['note']) . '</div>' : '')
                . (!$done && can('assign') && team_names() ? '<form method="post" class="inline assign">' . $df . '<input type="hidden" name="action" value="c_deadline_assign">' . person_select('assigned_to', $d['assigned_to'] ? (int)$d['assigned_to'] : null, 'Same as matter') . '<button class="btn sm">Save</button></form>'
                    : ($d['assigned_to'] ? '<div class="small muted">👤 ' . h(person_name((int)$d['assigned_to'])) . '</div>' : '')) . ($done ? '<div class="small muted">Done ' . h(ist($d['done_at'], 'j M')) . '</div>' : '') . '</div>
                <form method="post" class="chips">' . $df . ($done ? '<button class="chip" name="action" value="c_deadline_undo">Undo</button>' : '<button class="chip on" name="action" value="c_deadline_done">Done</button>')
                . '</form>' . (can('delete') ? '<details class="rm"><summary class="small">Remove</summary><form method="post">' . $df . '<button class="btn sm danger" name="action" value="c_deadline_delete">Yes, remove this deadline</button></form></details>' : '') . '</li>';
        }
        $st = $db->prepare('SELECT * FROM matter_log WHERE matter_id = ? ORDER BY id DESC');
        $st->execute([$mid]);
        $log = '';
        foreach ($st->fetchAll() as $x) $log .= '<li class="k-note"><span class="muted small">' . h(ist($x['created_at'], 'j M, g:i a')) . ($x['by_name'] ? ' · ' . h($x['by_name']) : '') . '</span><div>' . nl2br(h($x['text'])) . '</div></li>';
        $kinds = opt_list(DEADLINE_KINDS, DEADLINE_KINDS[0]);
        page(($m['service'] ?: 'Matter') . ' · ' . $c['name'], '<p class="small"><a href="?p=client&amp;id=' . (int)$c['id'] . '">← ' . h($c['name']) . '</a></p>
        <h1>' . h($m['service'] ?: 'Matter') . ($m['closed'] ? ' <span class="badge st-lost">Closed</span>' : '') . '</h1>
        ' . ($m['applicant'] ? '<p><strong>Applicant:</strong> ' . h($m['applicant']) . '</p>' : '') . '
        <p class="muted">' . h(implode(' · ', array_filter([$m['title'], $m['app_no'] ? 'No. ' . $m['app_no'] : '', $m['filed_on'] ? 'Filed ' . nice_date($m['filed_on']) : 'Not filed yet', IP_TYPES[$m['ip_type'] ?? ''] ?? '', '#' . $mid]))) . '</p>
        ' . ($c['phone'] ? '<div class="actions"><a class="btn wa" href="https://wa.me/' . h(wa_number((string)$c['phone'])) . '" target="_blank" rel="noopener">WhatsApp contact</a><a class="btn" href="?p=client&amp;id=' . (int)$c['id'] . '">' . h(mb_strimwidth((string)$c['name'], 0, 28, '…')) . '</a></div>' : '') . '
        ' . assign_card('<input type="hidden" name="matter_id" value="' . $mid . '"><input type="hidden" name="action" value="c_assign">', $m['assigned_to'] ? (int)$m['assigned_to'] : null, 'Handled by') . '
        <section class="card"><h2>Status</h2><form method="post" class="inline">' . $f . '<input type="hidden" name="action" value="c_status">
          <select name="status">' . opt_list(MATTER_STATUSES, $m['status']) . '</select><button class="btn">Update</button></form>
          ' . ($m['office_status'] ? '<p class="small"><span class="muted">Office status:</span> ' . h($m['office_status']) . '</p>' : '') . '</section>
        <section class="card"><h2>Deadlines</h2><ul class="dls">' . ($dl ?: '<li class="muted">No deadlines yet.</li>') . '</ul>
          <form method="post" class="stack addl">' . $f . '<input type="hidden" name="action" value="c_add_deadline">
            <div class="row2"><label>What<select name="kind">' . $kinds . '</select></label><label>Due on<input type="date" name="due_on" required></label></div>
            <label>Note<input name="note" maxlength="300" placeholder="e.g. from examination report dated …"></label>
            ' . (can('assign') && team_names() ? '<label>For' . person_select('assigned_to', null, 'Same as matter') . '</label>' : '') . '
            <button class="btn primary">Add deadline</button>
            <p class="small muted">Take the due date from the official notice or portal. The tracker does not calculate legal deadlines.</p></form></section>
        ' . (show_money() ? payment_card('<input type="hidden" name="matter_id" value="' . $mid . '"><input type="hidden" name="action" value="c_pay">', 'Fee', $m['amount'] === null ? null : (int)$m['amount'], $m['payment_status'], $m['received'] === null ? null : (int)$m['received'])
          . ($m['invoice_no'] ? '<p class="small muted">Vyapar invoice ' . h($m['invoice_no']) . '</p>' : '') : '') . tasks_section('matter_id', $mid) . documents_admin_html((int)$m['client_id'], $mid, $m['portal_note']) . '
        <section class="card"><h2>Notes</h2><form method="post" class="stack">' . $f . '<input type="hidden" name="action" value="c_matter_note">
          <textarea name="note" rows="2" required maxlength="2000" placeholder="e.g. Filed today, receipt shared with client"></textarea><button class="btn primary">Add note</button></form>
          <ul class="timeline">' . ($log ?: '<li class="muted">No notes yet.</li>') . '</ul></section>
        <details class="card"><summary>Edit details</summary><form method="post" class="stack">' . $f . '<input type="hidden" name="action" value="c_matter_edit">
          <label>Service' . service_select((string)$m['service']) . '</label>
          <label>Title<input name="title" maxlength="160" value="' . h($m['title']) . '" placeholder="e.g. brand name, invention title"></label>
          <label>Applicant<input name="applicant" maxlength="160" value="' . h($m['applicant']) . '" placeholder="Name on the application, if different from the client"></label>
          <label>Tab<select name="ip_type"><option value="none">None (not patent, design, trademark or copyright)</option>' . implode('', array_map(fn($k) => '<option value="' . $k . '"' . (($m['ip_type'] ?? '') === $k ? ' selected' : '') . '>' . IP_TYPES[$k] . '</option>', array_keys(IP_TYPES))) . '</select></label>
          <label>Office status (as shown by the office)<textarea name="office_status" rows="2" maxlength="400">' . h($m['office_status']) . '</textarea></label>
          <div class="row2"><label>Application / reference no.<input name="app_no" maxlength="60" value="' . h($m['app_no']) . '"></label>
          <label>Filed on<input type="date" name="filed_on" value="' . h($m['filed_on'] ?: '') . '"></label></div>
          ' . (show_money() ? '<div class="row2"><label>Vyapar invoice no.<input name="invoice_no" maxlength="60" value="' . h($m['invoice_no']) . '"></label>
          <label>Amount (₹)<input name="amount" inputmode="numeric" pattern="[0-9]*" maxlength="12" value="' . h($m['amount'] === null ? '' : (string)$m['amount']) . '"></label></div>' : '') . '
          <button class="btn primary">Save</button></form></details>
        <details class="card"><summary>' . ($m['closed'] ? 'Reopen' : 'Close') . ' or delete</summary>
          <form method="post">' . $f . '<input type="hidden" name="action" value="c_close"><button class="btn" name="closed" value="' . ($m['closed'] ? '0' : '1') . '">' . ($m['closed'] ? 'Reopen matter' : 'Close matter (work finished)') . '</button></form>
          ' . (can('delete') ? '<form method="post" class="stack mt">' . $f . '<input type="hidden" name="action" value="c_delete_matter">
          <label class="check"><input type="checkbox" name="confirm" value="yes" required> Yes, permanently delete this matter and its deadlines</label><button class="btn danger">Delete matter</button></form>' : '') . '</details>');
    }

    if ($p === 'deadlines') {
        $range = get('r', 10) ?: '60';
        $until = $range === 'all' ? null : plus_days((int)$range);
        $rows = open_deadlines($until);
        $chip = fn($v, $l) => '<a class="chip' . ($range === $v ? ' on' : '') . '" href="?p=deadlines&amp;r=' . $v . (who_filter() !== '' ? '&amp;who=' . h(who_filter()) : '') . '">' . $l . '</a>';
        page('Deadlines', '<h1>Deadlines</h1>' . (who_select() ? '<form class="search" method="get"><input type="hidden" name="p" value="deadlines"><input type="hidden" name="r" value="' . h($range) . '">' . who_select() . '<button class="btn">Show</button></form>' : '') . '<nav class="chips scroll">' . $chip('15', 'Next 15 days') . $chip('60', 'Next 60 days') . $chip('all', 'All open') . '</nav>
        <div class="list">' . (implode('', array_map('deadline_card', $rows)) ?: '<p class="muted">No open deadlines in this period.</p>') . '</div>');
    }
}

/** Today-screen block: overdue deadlines and those due within the window. */
function today_deadlines_html(): string {
    $rows = open_deadlines(plus_days(DEADLINE_WINDOW));
    return '<section><h2>Deadlines, next ' . DEADLINE_WINDOW . ' days' . ($rows ? ' <span class="n">' . count($rows) . '</span>' : '') . '</h2>
      <div class="list">' . (implode('', array_map('deadline_card', $rows)) ?: '<p class="muted">No deadlines due. <a href="?p=deadlines">See all</a></p>') . '</div></section>';
}

/* ---------------- Daily summary email ---------------- */

function digest_to(): string {
    $t = admin_config()['digest_to'] ?? 'info@leegtech.in';
    return filter_var($t, FILTER_VALIDATE_EMAIL) ? $t : 'info@leegtech.in';
}

function digest_enabled(): bool { return (admin_config()['digest_on'] ?? true) !== false; }

function save_digest_settings(string $to, bool $on): void {
    $cfg = admin_config();
    if (!$cfg) return;
    $cfg['digest_to'] = $to; $cfg['digest_on'] = $on;
    file_put_contents(admin_file(), json_encode($cfg, JSON_PRETTY_PRINT), LOCK_EX);
}

function digest_mark_sent(string $day): void {
    $cfg = admin_config();
    if (!$cfg) return;
    $cfg['digest_last'] = $day;
    file_put_contents(admin_file(), json_encode($cfg, JSON_PRETTY_PRINT), LOCK_EX);
}

/** Builds [subject, html] for today's summary: the owner's (everything) or one team member's (only their work, no money). */
function digest_build(?array $user = null): array {
    $db = cdb();
    $t = today();
    $notTest = 'COALESCE(is_test, 0) = 0';
    $open = "COALESCE(stage, 'New') IN ('" . implode("','", OPEN_STAGES) . "')";
    $uperms = $user ? perms_of($user['perms'] ?? '') : [];
    $uid = $user && !in_array('all', $uperms, true) ? (int)$user['id'] : null;
    $money = !$user || in_array('money', $uperms, true);
    $dls = open_deadlines(plus_days(DEADLINE_WINDOW), $uid ? 'COALESCE(d.assigned_to, m.assigned_to) = ' . $uid : '1=1');
    $st = $db->prepare("SELECT * FROM leads WHERE $notTest AND $open AND follow_up IS NOT NULL AND follow_up <= ? " . ($uid ? "AND assigned_to = $uid " : '') . 'ORDER BY follow_up, id');
    $st->execute([$t]); $fus = $st->fetchAll();
    $since = (new DateTime('yesterday 09:00'))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    $st = $db->prepare("SELECT * FROM leads WHERE $notTest AND created_at >= ? " . ($uid ? "AND assigned_to = $uid " : '') . 'ORDER BY id DESC'); $st->execute([$since]); $newLeads = $st->fetchAll();
    $untouched = $uid ? 0 : (int)$db->query("SELECT COUNT(*) FROM leads WHERE $notTest AND COALESCE(stage, 'New') = 'New' AND (follow_up IS NULL OR follow_up = '')")->fetchColumn();
    $pending = $uid || !$money ? 0 : (int)$db->query('SELECT COALESCE(SUM(MAX(amount - COALESCE(received, 0), 0)), 0) FROM matters WHERE paid = 0 AND amount > 0')->fetchColumn();
    $st = $db->prepare('SELECT * FROM tasks WHERE done_at IS NULL AND (due_on IS NULL OR due_on <= ?) ' . ($uid ? "AND assigned_to = $uid " : '') . 'ORDER BY due_on IS NULL, due_on, id LIMIT 100');
    $st->execute([plus_days(1)]); $tasks = $st->fetchAll();

    $base = 'https://leegtech.in/admin/';
    $overdue = count(array_filter($dls, fn($d) => $d['due_on'] < $t));
    $subject = ($user ? $user['name'] . ', your day' : 'Leegtech today') . ': ' . count($dls) . ' deadline' . (count($dls) === 1 ? '' : 's') . ($overdue ? " ($overdue overdue)" : '') . ', ' . count($fus) . ' follow-up' . (count($fus) === 1 ? '' : 's') . ', ' . count($tasks) . ' task' . (count($tasks) === 1 ? '' : 's');

    $li = function (string $href, string $main, string $sub, string $color = '#1b1f24') {
        return '<tr><td style="padding:8px 0;border-bottom:1px solid #e5e7eb"><a href="' . h($href) . '" style="color:' . $color . ';font-weight:600;text-decoration:none">' . $main . '</a><br><span style="color:#5d6673;font-size:13px">' . $sub . '</span></td></tr>';
    };
    $sec = fn(string $title, string $rows, string $empty) => '<h2 style="font-size:16px;margin:24px 0 6px">' . $title . '</h2>' . ($rows ? '<table style="width:100%;border-collapse:collapse">' . $rows . '</table>' : '<p style="color:#5d6673;margin:0">' . $empty . '</p>');

    $r = '';
    foreach ($dls as $d) {
        $late = $d['due_on'] < $t;
        $who = !$uid && ($w = (int)($d['assigned_to'] ?: $d['matter_assigned'])) ? ' · ' . person_name($w) : '';
        $r .= $li($base . '?p=matter&id=' . $d['matter_id'], ($late ? 'OVERDUE ' : '') . h(nice_date($d['due_on'])) . ' – ' . h($d['kind']),
            h($d['client_name']) . ' · ' . h(trim(($d['service'] ?? '') . ($d['applicant'] ? ' – ' . $d['applicant'] : ($d['title'] ? ' – ' . $d['title'] : '')))) . ($d['app_no'] ? ' · ' . h($d['app_no']) : '') . h($who), $late || $d['due_on'] <= plus_days(3) ? '#c0262d' : '#1b1f24');
    }
    $html = $sec('Deadlines in the next ' . DEADLINE_WINDOW . ' days', $r, 'None.');
    $r = '';
    foreach ($fus as $l) $r .= $li($base . '?p=lead&id=' . $l['id'], h($l['name'] ?: 'No name') . ($l['follow_up'] < $t ? ' (overdue)' : ''), h(($l['service'] ?: 'Service not set') . ' · ' . ($l['stage'] ?: 'New') . ' · ' . nice_date($l['follow_up'])), $l['follow_up'] < $t ? '#c0262d' : '#1b1f24');
    $html .= $sec('Lead follow-ups due', $r, 'None due today.');
    $r = '';
    foreach ($tasks as $k) $r .= $li($base . ($k['matter_id'] ? '?p=matter&id=' . $k['matter_id'] : ($k['lead_id'] ? '?p=lead&id=' . $k['lead_id'] : '?p=tasks')), h($k['title']) . ($k['due_on'] && $k['due_on'] < $t ? ' (overdue)' : ''),
        h(($k['due_on'] ? 'Due ' . nice_date($k['due_on']) : 'No due date') . (!$uid && $k['assigned_to'] ? ' · ' . person_name((int)$k['assigned_to']) : '')), $k['due_on'] && $k['due_on'] < $t ? '#c0262d' : '#1b1f24');
    $html .= $sec('Tasks due today or tomorrow', $r, 'None.');
    $r = '';
    foreach ($newLeads as $l) $r .= $li($base . '?p=lead&id=' . $l['id'], h($l['name'] ?: 'No name'), h(($l['service'] ?: 'Service not set') . ' · ' . ($l['source'] ?: '') . ' · ' . ist($l['created_at'], 'j M, g:i a')));
    $html .= $sec($uid ? 'New leads assigned to you' : 'New leads since yesterday 9am', $r, 'None.');
    $extra = [];
    if ($untouched) $extra[] = $untouched . ' new lead' . ($untouched === 1 ? '' : 's') . ' without a follow-up date';
    if ($pending) $extra[] = 'Payment pending: ' . rupees($pending);
    if ($extra) $html .= '<p style="margin-top:20px">' . h(implode(' · ', $extra)) . '</p>';

    $doc = '<!doctype html><html><body style="margin:0;background:#f5f6f8;font-family:Arial,Helvetica,sans-serif;color:#1b1f24">
      <div style="max-width:600px;margin:0 auto;padding:20px;background:#fff">
      <h1 style="font-size:20px;margin:0 0 4px">Good morning! Here is ' . h(date('l, j F')) . '</h1>
      <p style="margin:0"><a href="' . $base . '" style="color:#1f4fd1">Open the lead tracker</a></p>' . $html . '
      <p style="color:#8a929c;font-size:12px;margin-top:28px">Sent by your lead tracker at leegtech.in/admin. Change the address or turn this off in Settings.</p></div></body></html>';
    return [$subject, $doc];
}

function digest_send(?string $to = null, ?array $user = null): bool {
    [$subject, $html] = digest_build($user);
    return send_mail($to ?: ($user ? (string)$user['email'] : digest_to()), $subject, $html);
}
