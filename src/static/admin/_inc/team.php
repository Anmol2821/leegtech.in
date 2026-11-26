<?php
/*
 * Lead tracker: team members, who is signed in, what each person may see, assignments and tasks.
 * - The owner signs in with the original password (admin.json) and sees everything.
 * - Team members (users table) sign in with their own password and see only work assigned to them:
 *   leads, matters, deadlines and tasks. They never see amounts or payments, and cannot delete, import,
 *   export, assign or change settings (except their own password).
 * - A deadline without its own assignee belongs to whoever handles its matter.
 * Included by /admin/index.php and _inc/daily.php.
 */
declare(strict_types=1);
if (!defined('LT_ADMIN')) { http_response_code(404); exit; }

function team_migrate(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT,
        pw_hash TEXT NOT NULL, active INTEGER DEFAULT 1, created_at TEXT NOT NULL, digest_last TEXT)');
    $db->exec('CREATE TABLE IF NOT EXISTS tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, note TEXT, due_on TEXT,
        assigned_to INTEGER, lead_id INTEGER, matter_id INTEGER, done_at TEXT, created_at TEXT NOT NULL, created_by TEXT)');
    $db->exec('CREATE INDEX IF NOT EXISTS tasks_open ON tasks(done_at, due_on)');
    $add = ['leads' => ['assigned_to' => 'INTEGER'], 'matters' => ['assigned_to' => 'INTEGER'], 'deadlines' => ['assigned_to' => 'INTEGER'],
        'admin_sessions' => ['user_id' => 'INTEGER DEFAULT 0'], 'activity' => ['by_name' => 'TEXT'], 'matter_log' => ['by_name' => 'TEXT'],
        'users' => ['perms' => "TEXT DEFAULT ''"]];
    foreach ($add as $table => $cols) {
        $have = array_column($db->query("PRAGMA table_info($table)")->fetchAll(), 'name');
        foreach ($cols as $c => $t) if (!in_array($c, $have, true)) $db->exec("ALTER TABLE $table ADD COLUMN $c $t");
    }
}

/* ---------------- Who is signed in, and what they may do ---------------- */

/** Extra powers the owner can give a team member. Without any, a member sees only work assigned to them. */
const PERMS = [
    'leads' => ['All leads', 'See and work on every lead and follow-up, not only their own'],
    'clients' => ['All clients & applications', 'See and work on every client, matter, deadline and the IP tabs, not only their own'],
    'money' => ['Fees & payments', 'See quotes, fees and balances, and record payments'],
    'assign' => ['Assign work', 'Assign leads, matters, deadlines and tasks to others'],
    'import' => ['Import & download', 'Import sheets and download the leads CSV'],
    'portal' => ['Client portal', 'Give, reset or switch off client portal access'],
    'delete' => ['Delete', 'Delete leads, clients, matters, deadlines and documents'],
];

function perms_of(?string $s): array {
    $p = explode(',', (string)$s);
    if (in_array('all', $p, true)) array_push($p, 'leads', 'clients'); // earlier combined power
    return array_values(array_intersect(array_keys(PERMS), $p));
}

/** Sees everything in at least one area (used for person filters and team views). */
function sees_all_any(): bool { return can('leads') || can('clients'); }

/** True for the owner, or a team member the owner gave this power. */
function can(string $perm): bool {
    $m = me();
    if (!$m) return false;
    return $m['owner'] || in_array($perm, $m['perms'], true);
}

function require_can(string $perm, string $back = '/admin/'): void {
    if (!can($perm)) redirect($back, 'You do not have permission for that. Ask the owner.');
}

/** Who new work goes to from a form: the chosen person if allowed to assign, else the person themself (owner = not assigned). */
function assignee_from_post(): ?int {
    if (!can('assign')) return is_owner() ? null : my_id();
    return posted_person() ?? (is_owner() ? null : my_id());
}

function owner_name(): string { $n = trim((string)(admin_config()['owner_name'] ?? '')); return $n !== '' ? $n : 'Owner'; }

/** The signed-in person: ['id' => 0 for the owner, 'name', 'email', 'owner' => bool], or null. */
function me(): ?array {
    static $me = false;
    if ($me !== false) return $me;
    $me = null;
    if (PHP_SAPI === 'cli') return null;
    $s = current_session();
    if (!$s) return null;
    $uid = (int)($s['user_id'] ?? 0);
    if ($uid === 0) return $me = ['id' => 0, 'name' => owner_name(), 'email' => function_exists('digest_to') ? digest_to() : '', 'owner' => true, 'perms' => array_keys(PERMS)];
    $u = user_row($uid);
    if (!$u || !(int)$u['active']) return null;
    return $me = ['id' => $uid, 'name' => $u['name'], 'email' => (string)$u['email'], 'owner' => false, 'perms' => perms_of($u['perms'] ?? '')];
}

function is_owner(): bool { $m = me(); return $m !== null && $m['owner']; }
function my_id(): int { return (int)(me()['id'] ?? 0); }
function show_money(): bool { return can('money'); }
function by_name(): ?string { return PHP_SAPI === 'cli' ? null : (me()['name'] ?? null); }

function user_row(int $id): ?array {
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** id => name of team members (active only unless $all). */
function team_names(bool $all = false): array {
    static $cache = [];
    $k = $all ? 'all' : 'active';
    if (!isset($cache[$k])) $cache[$k] = db()->query('SELECT id, name FROM users' . ($all ? '' : ' WHERE active = 1') . ' ORDER BY name COLLATE NOCASE')->fetchAll(PDO::FETCH_KEY_PAIR);
    return $cache[$k];
}

function person_name(?int $id): string { return $id ? (team_names(true)[$id] ?? 'Former member') : ''; }

/** Owner or team member whose password this is: 0 (owner), user id, or null. */
function login_user(string $password): ?int {
    $cfg = admin_config();
    if ($cfg && password_verify($password, $cfg['password_hash'])) return 0;
    foreach (db()->query('SELECT id, pw_hash FROM users WHERE active = 1')->fetchAll() as $u)
        if (password_verify($password, $u['pw_hash'])) return (int)$u['id'];
    return null;
}

/** Each person signs in with only a password, so no two people may share one. */
function password_taken(string $password, ?int $exceptUser): bool {
    $cfg = admin_config();
    if ($exceptUser !== 0 && $cfg && password_verify($password, $cfg['password_hash'])) return true;
    foreach (db()->query('SELECT id, pw_hash FROM users')->fetchAll() as $u)
        if ((int)$u['id'] !== $exceptUser && password_verify($password, $u['pw_hash'])) return true;
    return false;
}

/* ---------------- What each person may see (SQL fragments; ids are integers) ---------------- */

function lead_scope(string $a = 'leads'): string {
    if (can('leads')) return '1=1';
    $u = my_id();
    return "($a.assigned_to = $u OR EXISTS (SELECT 1 FROM tasks tq WHERE tq.lead_id = $a.id AND tq.assigned_to = $u AND tq.done_at IS NULL))";
}

function matter_scope(string $a = 'm'): string {
    if (can('clients')) return '1=1';
    $u = my_id();
    return "($a.assigned_to = $u OR EXISTS (SELECT 1 FROM deadlines dq WHERE dq.matter_id = $a.id AND dq.assigned_to = $u AND dq.done_at IS NULL)
        OR EXISTS (SELECT 1 FROM tasks tq WHERE tq.matter_id = $a.id AND tq.assigned_to = $u AND tq.done_at IS NULL))";
}

function client_scope(string $a = 'c'): string {
    if (can('clients')) return '1=1';
    return "(EXISTS (SELECT 1 FROM matters mq WHERE mq.client_id = $a.id AND " . matter_scope('mq') . ")
        OR EXISTS (SELECT 1 FROM leads lq WHERE lq.client_id = $a.id AND " . lead_scope('lq') . '))';
}

/** Deadlines: own assignee, else the matter's. $alias d = deadlines, m = matters. */
function deadline_scope(string $d = 'd', string $m = 'm'): string {
    if (can('clients')) return '1=1';
    return "COALESCE($d.assigned_to, $m.assigned_to) = " . my_id();
}

function can_see(string $table, int $id): bool {
    if (can($table === 'leads' ? 'leads' : 'clients')) return true;
    $scope = ['leads' => lead_scope('x'), 'matters' => matter_scope('x'), 'clients' => client_scope('x')][$table] ?? '0';
    $st = db()->prepare("SELECT 1 FROM $table x WHERE x.id = ? AND $scope");
    $st->execute([$id]);
    return (bool)$st->fetchColumn();
}

function require_owner(string $back = '/admin/'): void { if (!is_owner()) redirect($back, 'Only the owner can do that.'); }
function require_see(string $table, int $id): void { if (!can_see($table, $id)) redirect('/admin/', 'That is not assigned to you.'); }

/* ---------------- Assignment UI and notifications ---------------- */

function person_select(string $name, ?int $current, string $blank = 'Not assigned'): string {
    $o = '<select name="' . h($name) . '"><option value="">' . h($blank) . '</option>';
    foreach (team_names() as $id => $n) $o .= '<option value="' . (int)$id . '"' . ((int)$current === (int)$id ? ' selected' : '') . '>' . h($n) . '</option>';
    if ($current && !isset(team_names()[$current])) $o .= '<option value="' . (int)$current . '" selected>' . h(person_name($current)) . ' (switched off)</option>';
    return $o . '</select>';
}

function posted_person(string $name = 'assigned_to'): ?int {
    $v = post($name, 12);
    return $v !== '' && ctype_digit($v) && isset(team_names()[(int)$v]) ? (int)$v : null;
}

/** Filter for lists: '' all, 'none' unassigned, or a member id. */
function who_filter(): string { $w = get('who', 12); return $w === 'none' || ($w !== '' && ctype_digit($w)) ? $w : ''; }

function who_sql(string $col): string {
    $w = who_filter();
    if (!sees_all_any() || $w === '') return '1=1';
    return $w === 'none' ? "$col IS NULL" : "$col = " . (int)$w;
}

function who_select(): string {
    if (!sees_all_any() || !team_names()) return '';
    $w = who_filter();
    $o = '<select name="who" aria-label="Person"><option value="">Everyone</option><option value="none"' . ($w === 'none' ? ' selected' : '') . '>Not assigned</option>';
    foreach (team_names(true) as $id => $n) $o .= '<option value="' . (int)$id . '"' . ($w === (string)$id ? ' selected' : '') . '>' . h($n) . '</option>';
    return $o . '</select>';
}

function send_mail(string $to, string $subject, string $html): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $headers = implode("\r\n", ['From: Leegtech Leads <info@leegtech.in>', 'MIME-Version: 1.0', 'Content-Type: text/html; charset=UTF-8', 'X-Auto-Response-Suppress: All']);
    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers, '-finfo@leegtech.in');
    if (!$ok) error_log('mail() failed for ' . $to);
    return $ok;
}

/** Emails a team member that something was assigned to them (not when they assign it themselves). */
function notify_assigned(?int $userId, string $what, string $path, string $detail = ''): void {
    if (!$userId || $userId === my_id()) return;
    $u = user_row($userId);
    if (!$u || !(int)$u['active'] || !$u['email']) return;
    $url = 'https://leegtech.in/admin/' . $path;
    send_mail((string)$u['email'], 'New work for you: ' . $what,
        '<!doctype html><html><body style="font-family:Arial,Helvetica,sans-serif;color:#1b1f24"><div style="max-width:560px;margin:0 auto;padding:20px">
        <p>Hi ' . h($u['name']) . ',</p><p>' . h(by_name() ?: owner_name()) . ' assigned you: <strong>' . h($what) . '</strong></p>'
        . ($detail !== '' ? '<p style="color:#5d6673">' . h($detail) . '</p>' : '') . '
        <p><a href="' . h($url) . '" style="display:inline-block;background:#1f4fd1;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none">Open in the tracker</a></p>
        <p style="color:#8a929c;font-size:12px">Leegtech lead tracker</p></div></body></html>');
}

/** Small "assigned to" form for the owner. $hidden carries the record id and action. */
function assign_card(string $hidden, ?int $current, string $label = 'Assigned to'): string {
    if (!can('assign')) return $current ? '<p class="small muted">' . h($label) . ': ' . h(person_name($current)) . '</p>' : '';
    if (!team_names() && !$current) return '<p class="small muted">' . h($label) . ': you. <a href="?p=settings#team">Add team members</a> to assign work.</p>';
    return '<form method="post" class="inline assign">' . csrf_field() . $hidden . '<span class="small muted">' . h($label) . '</span>' . person_select('assigned_to', $current) . '<button class="btn">Save</button></form>';
}

/* ---------------- Tasks ---------------- */

function task_card(array $t, bool $showWhere = true): string {
    $f = csrf_field() . '<input type="hidden" name="task_id" value="' . (int)$t['id'] . '">';
    $done = $t['done_at'] !== null;
    $where = '';
    if ($showWhere && $t['lead_id']) $where = '<a href="?p=lead&amp;id=' . (int)$t['lead_id'] . '">Lead: ' . h($t['lead_name'] ?? ('#' . $t['lead_id'])) . '</a>';
    if ($showWhere && $t['matter_id']) $where = '<a href="?p=matter&amp;id=' . (int)$t['matter_id'] . '">' . h(trim(($t['matter_label'] ?? '') ?: ('Matter #' . $t['matter_id']))) . '</a>';
    return '<div class="task' . ($done ? ' done' : '') . '"><div class="body"><strong>' . h($t['title']) . '</strong>
      <div class="meta small">' . ($t['due_on'] ? '<span class="' . ($done ? 'muted' : due_class($t['due_on'])) . '">⏰ ' . h(nice_date($t['due_on'])) . '</span>' : '')
        . ($t['assigned_to'] && (sees_all_any() || (int)$t['assigned_to'] !== my_id()) ? '<span>👤 ' . h(person_name((int)$t['assigned_to'])) . '</span>' : (is_owner() && !$t['assigned_to'] ? '<span>👤 you</span>' : ''))
        . ($where ? '<span>' . $where . '</span>' : '') . ($t['note'] ? '<span>' . h($t['note']) . '</span>' : '') . '</div></div>
      <form method="post">' . $f . '<button class="btn sm' . ($done ? '' : ' primary') . '" name="action" value="' . ($done ? 't_undo' : 't_done') . '">' . ($done ? 'Undo' : 'Done') . '</button></form></div>';
}

/** Open tasks the signed-in person may see. */
function open_tasks(string $extraWhere = '1=1', array $args = [], int $limit = 200): array {
    $scope = can('leads') && can('clients') ? '1=1' : '(t.assigned_to = ' . my_id() . (can('leads') ? ' OR t.lead_id IS NOT NULL' : '') . (can('clients') ? ' OR t.matter_id IS NOT NULL' : '') . ')';
    $st = db()->prepare("SELECT t.*, l.name lead_name, (m.service || CASE WHEN COALESCE(m.applicant, '') != '' THEN ' – ' || m.applicant ELSE '' END) matter_label
        FROM tasks t LEFT JOIN leads l ON l.id = t.lead_id LEFT JOIN matters m ON m.id = t.matter_id
        WHERE t.done_at IS NULL AND $scope AND $extraWhere ORDER BY t.due_on IS NULL, t.due_on, t.id LIMIT $limit");
    $st->execute($args);
    return $st->fetchAll();
}

/** Tasks block for a lead or matter page, with an add form. */
function tasks_section(string $field, int $id): string {
    $st = db()->prepare("SELECT * FROM tasks WHERE $field = ? AND (done_at IS NULL OR done_at > ?) " . (can($field === 'lead_id' ? 'leads' : 'clients') ? '' : 'AND assigned_to = ' . my_id()) . ' ORDER BY done_at IS NOT NULL, due_on IS NULL, due_on, id');
    $st->execute([$id, gmdate('Y-m-d\TH:i:s\Z', time() - 14 * 86400)]);
    $rows = $st->fetchAll();
    return '<section class="card"><h2>Tasks' . ($rows ? ' <span class="n">' . count(array_filter($rows, fn($t) => $t['done_at'] === null)) . '</span>' : '') . '</h2>
      <div class="list">' . (implode('', array_map(fn($t) => task_card($t, false), $rows)) ?: '<p class="muted small">No tasks.</p>') . '</div>
      <details class="mt"><summary>+ Add a task</summary>' . task_form('<input type="hidden" name="' . $field . '" value="' . $id . '">') . '</details></section>';
}

function task_form(string $hidden = ''): string {
    return '<form method="post" class="stack mt">' . csrf_field() . '<input type="hidden" name="action" value="t_add">' . $hidden . '
      <label>Task<input name="title" required maxlength="200" placeholder="e.g. Collect signed POA from client"></label>
      <div class="row2"><label>Due on<input type="date" name="due_on" value="' . h(plus_days(1)) . '"></label>'
      . (can('assign') && team_names() ? '<label>For' . person_select('assigned_to', null, 'Me') . '</label>' : '') . '</div>
      <label>Note<input name="note" maxlength="300"></label><button class="btn primary">Add task</button></form>';
}

function task_action(string $a): void {
    $db = cdb();
    $back = $_SERVER['HTTP_REFERER'] ?? '/admin/';
    if (!str_starts_with((string)parse_url($back, PHP_URL_PATH), '/admin/')) $back = '/admin/';
    if ($a === 't_add') {
        $title = post('title', 200);
        if ($title === '') redirect($back, 'Enter the task.');
        $leadId = (int)post('lead_id', 12) ?: null; $matterId = (int)post('matter_id', 12) ?: null;
        if ($leadId) require_see('leads', $leadId);
        if ($matterId) require_see('matters', $matterId);
        $who = assignee_from_post();
        $due = valid_date(post('due_on', 10)) ? post('due_on', 10) : null;
        $db->prepare('INSERT INTO tasks (title, note, due_on, assigned_to, lead_id, matter_id, created_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$title, post('note', 300), $due, $who, $leadId, $matterId, now_utc(), by_name()]);
        $tid = (int)$db->lastInsertId();
        $txt = 'Task added: ' . $title . ($due ? ' (due ' . nice_date($due) . ')' : '') . ($who ? ' for ' . person_name($who) : '');
        if ($leadId) log_activity($leadId, 'note', $txt);
        if ($matterId) mlog($matterId, $txt);
        notify_assigned($who, $title, $matterId ? '?p=matter&id=' . $matterId : ($leadId ? '?p=lead&id=' . $leadId : '?p=tasks'), $due ? 'Due ' . nice_date($due) : '');
        redirect($back, 'Task added' . ($who ? ' for ' . person_name($who) : '') . '.');
    }
    $t = find_row('tasks', (int)post('task_id', 12));
    if (!$t) redirect($back, 'Task not found.');
    $mayTouch = (int)$t['assigned_to'] === my_id() || (can('leads') && can('clients')) || ($t['lead_id'] && can('leads')) || ($t['matter_id'] && can('clients'));
    if (!$mayTouch) redirect('/admin/', 'That task is not yours.');
    $done = $a === 't_done';
    $db->prepare('UPDATE tasks SET done_at = ? WHERE id = ?')->execute([$done ? now_utc() : null, $t['id']]);
    $txt = ($done ? 'Task done: ' : 'Task reopened: ') . $t['title'];
    if ($t['lead_id']) log_activity((int)$t['lead_id'], 'note', $txt);
    if ($t['matter_id']) mlog((int)$t['matter_id'], $txt);
    redirect($back, $done ? 'Task done.' : 'Task reopened.');
}

function tasks_page(): void {
    $w = who_filter();
    $extra = sees_all_any() && $w !== '' ? ($w === 'none' ? 't.assigned_to IS NULL' : 't.assigned_to = ' . (int)$w) : '1=1';
    $rows = open_tasks($extra, [], 500);
    page('Tasks', '<h1>Tasks</h1>' . (who_select() ? '<form class="search" method="get"><input type="hidden" name="p" value="tasks">' . who_select() . '<button class="btn">Show</button></form>' : '') . '
    <div class="list">' . (implode('', array_map('task_card', $rows)) ?: '<p class="muted">No open tasks.</p>') . '</div>
    <details class="card mt" id="new"' . (get('new') === '1' ? ' open' : '') . '><summary>+ New task</summary>' . task_form() . '</details>');
}

/* ---------------- Team settings (owner) ---------------- */

function team_action(string $a): void {
    require_owner('/admin/?p=settings');
    $db = cdb();
    $back = '/admin/?p=settings#team';
    if ($a === 'team_owner_name') {
        $cfg = admin_config(); $cfg['owner_name'] = mb_substr(post('owner_name', 60), 0, 60);
        file_put_contents(admin_file(), json_encode($cfg, JSON_PRETTY_PRINT), LOCK_EX);
        redirect($back, 'Saved.');
    }
    if ($a === 'team_add') {
        $name = post('name', 60); $email = strtolower(post('email', 160)); $pw = (string)($_POST['password'] ?? '');
        if ($name === '') redirect($back, 'Enter their name.');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) redirect($back, 'That email address is not valid.');
        if ($e = password_problem($pw, $pw)) redirect($back, $e);
        if (password_taken($pw, null)) redirect($back, 'That password is already used by someone. Choose a different one.');
        $db->prepare('INSERT INTO users (name, email, pw_hash, active, created_at, perms) VALUES (?, ?, ?, 1, ?, ?)')->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT), now_utc(), posted_perms()]);
        redirect($back, $name . ' added. Give them the password and the link leegtech.in/admin.');
    }
    $u = user_row((int)post('user_id', 12));
    if (!$u) redirect($back, 'Team member not found.');
    if ($a === 'team_update') {
        $email = strtolower(post('email', 160));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) redirect($back, 'That email address is not valid.');
        $db->prepare('UPDATE users SET name = ?, email = ?, perms = ? WHERE id = ?')->execute([post('name', 60) ?: $u['name'], $email, posted_perms(), $u['id']]);
        $pw = (string)($_POST['password'] ?? '');
        if ($pw !== '') {
            if ($e = password_problem($pw, $pw)) redirect($back, $e);
            if (password_taken($pw, (int)$u['id'])) redirect($back, 'That password is already used by someone. Choose a different one.');
            $db->prepare('UPDATE users SET pw_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
            $db->prepare('DELETE FROM admin_sessions WHERE user_id = ?')->execute([$u['id']]);
        }
        redirect($back, 'Saved' . ($pw !== '' ? '. They need to sign in again with the new password.' : '.'));
    }
    if ($a === 'team_toggle') {
        $on = (int)$u['active'] ? 0 : 1;
        $db->prepare('UPDATE users SET active = ? WHERE id = ?')->execute([$on, $u['id']]);
        if (!$on) $db->prepare('DELETE FROM admin_sessions WHERE user_id = ?')->execute([$u['id']]);
        redirect($back, $u['name'] . ($on ? ' can sign in again.' : ' is switched off and signed out. Their work stays assigned to them until you reassign it.'));
    }
    redirect($back);
}

function posted_perms(): string {
    $p = $_POST['perms'] ?? [];
    return implode(',', array_values(array_intersect(array_keys(PERMS), is_array($p) ? $p : [])));
}

function perms_checkboxes(array $have): string {
    $o = '<fieldset class="perms"><legend>What they can do</legend><p class="small muted">With nothing ticked they see only the work assigned to them.</p>';
    foreach (PERMS as $k => [$label, $help])
        $o .= '<label class="check"><input type="checkbox" name="perms[]" value="' . $k . '"' . (in_array($k, $have, true) ? ' checked' : '') . '> <span><strong>' . h($label) . '</strong><br><span class="small muted">' . h($help) . '</span></span></label>';
    return $o . '</fieldset>';
}

function perms_summary(array $have): string {
    if (!$have) return 'Own work only';
    return implode(', ', array_map(fn($k) => PERMS[$k][0], $have));
}

function team_settings_html(): string {
    $db = cdb();
    $rows = '';
    foreach ($db->query('SELECT * FROM users ORDER BY active DESC, name COLLATE NOCASE')->fetchAll() as $u) {
        $id = (int)$u['id'];
        $n = $db->query("SELECT (SELECT COUNT(*) FROM leads WHERE assigned_to = $id AND COALESCE(stage, 'New') IN ('" . implode("','", OPEN_STAGES) . "')) l,
            (SELECT COUNT(*) FROM matters WHERE assigned_to = $id AND closed = 0) m, (SELECT COUNT(*) FROM tasks WHERE assigned_to = $id AND done_at IS NULL) t")->fetch();
        $f = csrf_field() . '<input type="hidden" name="user_id" value="' . $id . '">';
        $rows .= '<details class="member' . ((int)$u['active'] ? '' : ' off') . '"><summary><strong>' . h($u['name']) . '</strong>' . ((int)$u['active'] ? '' : ' <span class="badge st-lost">Switched off</span>')
            . '<span class="small muted"> · ' . (int)$n['l'] . ' leads · ' . (int)$n['m'] . ' matters · ' . (int)$n['t'] . ' tasks</span><div class="small">' . h(perms_summary(perms_of($u['perms'] ?? ''))) . '</div></summary>
          <p class="small"><a href="?p=leads&amp;who=' . $id . '">Their leads</a> · <a href="?p=ip&amp;t=design&amp;who=' . $id . '">Their designs</a> · <a href="?p=deadlines&amp;r=all&amp;who=' . $id . '">Their deadlines</a> · <a href="?p=tasks&amp;who=' . $id . '">Their tasks</a></p>
          <form method="post" class="stack">' . $f . '<input type="hidden" name="action" value="team_update">
            <div class="row2"><label>Name<input name="name" maxlength="60" value="' . h($u['name']) . '"></label><label>Email (for their daily email)<input name="email" type="email" maxlength="160" value="' . h($u['email']) . '"></label></div>
            <label>New password (leave empty to keep)<input name="password" type="text" minlength="10" maxlength="100" autocomplete="off"></label>
            ' . perms_checkboxes(perms_of($u['perms'] ?? '')) . '
            <button class="btn primary">Save</button></form>
          <form method="post" class="mt">' . $f . '<button class="btn' . ((int)$u['active'] ? ' danger' : '') . '" name="action" value="team_toggle">' . ((int)$u['active'] ? 'Switch off access' : 'Switch access back on') . '</button></form></details>';
    }
    return '<section class="card" id="team"><h2>Team</h2>
      <p class="muted small">Each person signs in at leegtech.in/admin with their own password. By default they see only the work assigned to them; tick powers below to give them more. Only you can manage the team and settings.</p>
      <form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="team_owner_name"><input name="owner_name" maxlength="60" value="' . h(owner_name() === 'Owner' ? '' : owner_name()) . '" placeholder="Your name (shown on notes)" aria-label="Your name"><button class="btn">Save</button></form>
      <div class="members">' . ($rows ?: '<p class="muted small mt">No team members yet.</p>') . '</div>
      <details class="mt"><summary><strong>+ Add a team member</strong></summary>
      <form method="post" class="stack mt">' . csrf_field() . '<input type="hidden" name="action" value="team_add">
        <div class="row2"><label>Name<input name="name" required maxlength="60"></label><label>Email (for their daily email)<input name="email" type="email" maxlength="160"></label></div>
        <label>Password for them (at least 10 characters)<input name="password" type="text" required minlength="10" maxlength="100" autocomplete="off" placeholder="e.g. three words and a number"></label>
        ' . perms_checkboxes([]) . '
        <button class="btn primary">Add team member</button></form></details></section>';
}

/** Owner's Today: open work per person. */
function team_summary_html(): string {
    if (!sees_all_any() || !team_names()) return '';
    $db = cdb(); $t = today(); $soon = plus_days(DEADLINE_WINDOW);
    $rows = '';
    foreach (team_names() + ['none' => 'Not assigned'] as $id => $name) {
        $who = $id === 'none' ? 'IS NULL' : '= ' . (int)$id;
        $r = $db->query("SELECT
            (SELECT COUNT(*) FROM leads WHERE assigned_to $who AND COALESCE(is_test,0)=0 AND COALESCE(stage,'New') IN ('" . implode("','", OPEN_STAGES) . "') AND follow_up IS NOT NULL AND follow_up <= '$t') fu,
            (SELECT COUNT(*) FROM deadlines d JOIN matters m ON m.id = d.matter_id WHERE d.done_at IS NULL AND m.closed = 0 AND COALESCE(d.assigned_to, m.assigned_to) $who AND d.due_on <= '$soon') dl,
            (SELECT COUNT(*) FROM deadlines d JOIN matters m ON m.id = d.matter_id WHERE d.done_at IS NULL AND m.closed = 0 AND COALESCE(d.assigned_to, m.assigned_to) $who AND d.due_on < '$t') late,
            (SELECT COUNT(*) FROM tasks WHERE done_at IS NULL AND assigned_to $who AND (due_on IS NULL OR due_on <= '$t')) tk")->fetch();
        if ($id === 'none' && !array_sum(array_map('intval', $r))) continue;
        $link = '?p=tasks&amp;who=' . $id;
        $rows .= '<tr><td><a href="' . $link . '">' . h($name) . '</a></td><td>' . (int)$r['fu'] . '</td><td>' . (int)$r['dl'] . ((int)$r['late'] ? ' <span class="late">(' . (int)$r['late'] . ' late)</span>' : '') . '</td><td>' . (int)$r['tk'] . '</td></tr>';
    }
    return '<section class="card"><h2>Team today</h2><table class="mini team"><tr><th>Person</th><th>Follow-ups</th><th>Deadlines</th><th>Tasks</th></tr>' . $rows . '</table>
      <p class="small muted">Follow-ups and tasks due today or earlier; deadlines due in the next ' . DEADLINE_WINDOW . ' days.</p></section>';
}

/** Owner bulk-assign: every lead or matter currently listed. */
function bulk_assign_form(string $table, array $ids, string $label): string {
    if (!can('assign') || !$ids || !team_names()) return '';
    return '<details class="card"><summary>Assign all ' . count($ids) . ' ' . h($label) . ' shown</summary>
      <form method="post" class="inline mt">' . csrf_field() . '<input type="hidden" name="action" value="bulk_assign"><input type="hidden" name="table" value="' . h($table) . '">
      <input type="hidden" name="ids" value="' . h(implode(',', array_map('intval', $ids))) . '">' . person_select('assigned_to', null) . '<button class="btn primary">Assign</button></form>
      <p class="small muted">Use the filters and search above first to narrow the list. The person gets one email.</p></details>';
}

function bulk_assign_action(): void {
    require_can('assign');
    $table = post('table', 10) === 'leads' ? 'leads' : 'matters';
    $ids = array_values(array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? ''))))) ;
    $ids = array_slice($ids, 0, 2000);
    $who = posted_person();
    $back = $_SERVER['HTTP_REFERER'] ?? '/admin/';
    if (!str_starts_with((string)parse_url($back, PHP_URL_PATH), '/admin/')) $back = '/admin/';
    if (!$ids) redirect($back, 'Nothing to assign.');
    $db = cdb();
    $db->beginTransaction();
    $up = $db->prepare("UPDATE $table SET assigned_to = ?, updated_at = ? WHERE id = ?");
    $txt = $who ? 'Assigned to ' . person_name($who) : 'Unassigned';
    foreach ($ids as $id) {
        $up->execute([$who, now_utc(), $id]);
        $table === 'leads' ? log_activity($id, 'note', $txt) : mlog($id, $txt);
    }
    $db->commit();
    notify_assigned($who, count($ids) . ' ' . ($table === 'leads' ? 'leads' : 'matters'), $table === 'leads' ? '?p=leads' : '?p=clients');
    redirect($back, count($ids) . ' ' . ($table === 'leads' ? 'leads' : 'matters') . ' ' . ($who ? 'assigned to ' . person_name($who) : 'unassigned') . '.');
}
