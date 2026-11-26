<?php
/*
 * Lead tracker: IP portfolio tabs (Patents, Designs, Trademarks, Copyrights) and CSV import from a status sheet.
 * Included by /admin/index.php after clients.php.
 * Import: the owner uploads a CSV (e.g. a Google Sheet tab downloaded as CSV). Nothing from the file is stored in the
 * code repository; a parsed copy waits in leads-data/ only until the import is confirmed or abandoned (1 hour).
 * Re-importing an updated sheet refreshes status and office status of applications already in the tracker.
 */
declare(strict_types=1);
if (!defined('LT_ADMIN')) { http_response_code(404); exit; }

const IP_TYPES = ['patent' => 'Patents', 'design' => 'Designs', 'trademark' => 'Trademarks', 'copyright' => 'Copyrights'];
const IP_DEFAULT_SERVICE = ['patent' => 'Patent Registration (Complete Specification & Grant)', 'design' => 'Indian Design Registration',
    'trademark' => 'Trademark Registration', 'copyright' => 'Indian Copyright Registration'];

function portfolio_migrate(PDO $db): void {
    $have = array_column($db->query('PRAGMA table_info(matters)')->fetchAll(), 'name');
    foreach (['ip_type' => 'TEXT', 'applicant' => 'TEXT', 'office_status' => 'TEXT'] as $c => $t)
        if (!in_array($c, $have, true)) $db->exec("ALTER TABLE matters ADD COLUMN $c $t");
    $rows = $db->query('SELECT id, service FROM matters WHERE ip_type IS NULL')->fetchAll();
    if ($rows) {
        $up = $db->prepare('UPDATE matters SET ip_type = ? WHERE id = ?');
        foreach ($rows as $r) $up->execute([ip_type_for_service((string)$r['service']), $r['id']]);
    }
    $db->exec('CREATE INDEX IF NOT EXISTS matters_app_no ON matters(app_no)');
}

/** 'patent' | 'design' | 'trademark' | 'copyright' | '' from a service name, using the site's service groups. */
function ip_type_for_service(string $service): string {
    if ($service === '') return '';
    if (stripos($service, 'copyright') !== false) return 'copyright';
    foreach (service_names() as $g) {
        if (!in_array($service, $g['items'], true)) continue;
        $grp = strtolower($g['group']);
        if (str_contains($grp, 'patent')) return 'patent';
        if (str_contains($grp, 'design')) return 'design';
        if (str_contains($grp, 'trademark')) return 'trademark';
        return '';
    }
    foreach (['patent' => 'patent', 'design' => 'design', 'trademark' => 'trademark', 'trade mark' => 'trademark'] as $k => $v)
        if (stripos($service, $k) !== false) return $v;
    return '';
}

/** Tab row shown on Clients and on each portfolio tab. */
function portfolio_tabs(string $active): string {
    $counts = cdb()->query("SELECT COALESCE(ip_type, '') t, COUNT(*) n FROM matters GROUP BY t")->fetchAll(PDO::FETCH_KEY_PAIR);
    $o = '<nav class="chips scroll tabs2"><a class="chip' . ($active === 'clients' ? ' on' : '') . '" href="?p=clients">Clients</a>';
    foreach (IP_TYPES as $k => $label)
        $o .= '<a class="chip' . ($active === $k ? ' on' : '') . '" href="?p=ip&amp;t=' . $k . '">' . $label . ' <span class="n">' . (int)($counts[$k] ?? 0) . '</span></a>';
    return $o . '</nav>';
}

function app_card(array $m): string {
    $who = $m['applicant'] ?: $m['client_name'];
    return '<div class="lead"><a class="body" href="?p=matter&amp;id=' . (int)$m['id'] . '">
      <div class="top"><strong>' . h($who) . '</strong><span class="badge ' . status_class((string)$m['status']) . '">' . h($m['status'] ?: 'Open') . '</span></div>
      <div class="muted small">' . h(implode(' · ', array_filter([$m['app_no'] ? 'No. ' . $m['app_no'] : '', $m['filed_on'] ? 'Filed ' . nice_date($m['filed_on']) : '', $m['title']]))) . '</div>
      <div class="meta small">' . ($m['client_name'] && $m['client_name'] !== $who ? '<span>Contact: ' . h($m['client_name']) . '</span>' : '')
        . ($m['next_due'] ? '<span class="' . due_class($m['next_due']) . '">⏳ ' . h(nice_date($m['next_due'])) . '</span>' : '')
        . ($m['office_status'] ? '<span class="clip">' . h(mb_strimwidth((string)$m['office_status'], 0, 90, '…')) . '</span>' : '') . '</div>
    </a></div>';
}

function status_class(string $s): string {
    return match ($s) {
        'Registered / granted', 'Completed' => 'st-won',
        'Objection / FER received', 'Hearing scheduled' => 'st-quoted',
        'On hold' => 'st-lost',
        'Documents pending', 'Drafting', 'Ready to file' => 'st-new',
        default => 'st-consulted',
    };
}

function portfolio_page(string $p): void {
    $db = cdb();
    if ($p === 'import') { import_form_page(get('t', 12)); }

    $t = array_key_exists(get('t', 12), IP_TYPES) ? get('t', 12) : 'design';
    $q = get('q', 80); $stF = get('s', 60);
    $where = ['m.ip_type = ?']; $args = [$t];
    if ($stF !== '') { $where[] = 'm.status = ?'; $args[] = $stF; }
    if ($q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $where[] = "(m.applicant LIKE ? ESCAPE '\\' OR m.app_no LIKE ? ESCAPE '\\' OR m.title LIKE ? ESCAPE '\\' OR c.name LIKE ? ESCAPE '\\' OR c.email LIKE ? ESCAPE '\\' OR m.office_status LIKE ? ESCAPE '\\')";
        array_push($args, $like, $like, $like, $like, $like, $like);
    }
    $st = $db->prepare('SELECT m.*, c.name client_name,
        (SELECT MIN(due_on) FROM deadlines d WHERE d.matter_id = m.id AND d.done_at IS NULL) next_due
        FROM matters m JOIN clients c ON c.id = m.client_id WHERE ' . implode(' AND ', $where) . ' ORDER BY COALESCE(m.filed_on, m.created_at) DESC, m.id DESC LIMIT 1000');
    $st->execute($args);
    $rows = $st->fetchAll();
    $cnt = $db->prepare('SELECT status, COUNT(*) n FROM matters WHERE ip_type = ? GROUP BY status ORDER BY n DESC');
    $cnt->execute([$t]);
    $counts = $cnt->fetchAll(PDO::FETCH_KEY_PAIR);
    $chip = function (string $val, string $label, int $n) use ($t, $q, $stF) {
        $qs = http_build_query(array_filter(['p' => 'ip', 't' => $t, 's' => $val, 'q' => $q]));
        return '<a class="chip' . ($stF === $val ? ' on' : '') . '" href="?' . h($qs) . '">' . h($label) . ' <span class="n">' . $n . '</span></a>';
    };
    $chips = $chip('', 'All', array_sum(array_map('intval', $counts)));
    foreach ($counts as $s => $n) $chips .= $chip((string)$s, $s ?: 'No status', (int)$n);
    page(IP_TYPES[$t], portfolio_tabs($t) . '<h1>' . h(IP_TYPES[$t]) . '</h1>
    <div class="actions"><a class="btn" href="?p=newclient">+ New client</a><a class="btn" href="?p=import&amp;t=' . $t . '">Import from sheet</a></div>
    <form class="search" method="get"><input type="hidden" name="p" value="ip"><input type="hidden" name="t" value="' . $t . '"><input type="hidden" name="s" value="' . h($stF) . '">
      <input type="search" name="q" value="' . h($q) . '" placeholder="Search applicant, application no., contact"><button class="btn">Search</button></form>
    <nav class="chips scroll">' . $chips . '</nav>
    <div class="list">' . (implode('', array_map('app_card', $rows)) ?: '<p class="muted">Nothing here yet. Add a matter to a client, or use "Import from sheet".</p>') . '</div>
    <p class="small muted">' . count($rows) . ' shown</p>');
}

/* ---------------- CSV import ---------------- */

function import_form_page(string $t, ?string $err = null): void {
    $opts = '';
    foreach (IP_TYPES as $k => $label) $opts .= '<option value="' . $k . '"' . ($k === $t ? ' selected' : '') . '>' . h($label) . '</option>';
    page('Import', portfolio_tabs($t ?: 'design') . '<h1>Import applications from a sheet</h1>
    <p class="muted">In Google Sheets open the tab, then File → Download → Comma-separated values (.csv), and upload that file here. You will see a preview before anything is saved.</p>
    ' . ($err ? '<p class="alert">' . h($err) . '</p>' : '') . '
    <form method="post" enctype="multipart/form-data" class="stack card" id="impf">' . csrf_field() . '<input type="hidden" name="action" value="c_import_preview">
      <input type="hidden" name="csv_b64" value=""><input type="hidden" name="csv_name" value="">
      <label>These applications are<select name="ip_type">' . $opts . '</select></label>
      <label>CSV file<input type="file" name="csv" accept=".csv,text/csv" required></label>
      <button class="btn primary">Preview</button></form><script src="/admin/import.js?v=1" defer></script>
    <section class="card small"><h2>What the importer reads</h2><p>Columns are matched by their heading: applicant name, application number, filing date, contact person, mobile, email and status. Other columns are ignored.</p>
    <ul><li>Each row becomes an application (matter). Rows are grouped into clients by contact email, then phone, then name. An existing client with the same email or phone is reused.</li>
    <li>An application number already in the tracker is not added twice: its status and office status are updated instead, so you can re-upload an updated sheet.</li>
    <li>The office status text is kept as it is, and also mapped to a tracker status. Deadlines are not created; add them on each application.</li></ul></section>');
}

function csv_norm(string $s): string {
    $s = str_replace(["\u{00A0}", "\t", "\r", "\n"], ' ', $s);
    return trim(preg_replace('/\s+/u', ' ', $s) ?? '', " ,");
}

/** Maps sheet headings to fields. */
function import_columns(array $head): array {
    $map = [];
    foreach ($head as $i => $hRaw) {
        $h = strtolower(csv_norm((string)$hRaw));
        $f = null;
        if (preg_match('/applica(nt|tion)\s*(no|num)|app\.?\s*no|application number/', $h)) $f = 'app_no';
        elseif (str_contains($h, 'applicant') || $h === 'name' || str_contains($h, 'client name')) $f = 'applicant';
        elseif (str_contains($h, 'filing') || str_contains($h, 'filed') || $h === 'date') $f = 'filed_on';
        elseif (str_contains($h, 'contact')) $f = 'contact';
        elseif (preg_match('/mob|phone|whatsapp|contact no/', $h)) $f = 'phone';
        elseif (str_contains($h, 'mail')) $f = 'email';
        elseif (str_contains($h, 'status')) $f = 'office_status';
        elseif (str_contains($h, 'title') || str_contains($h, 'mark') || str_contains($h, 'article')) $f = 'title';
        if ($f && !isset($map[$f])) $map[$f] = $i;
    }
    // "Contact Person" vs "Contact No": a contact column with a phone-ish heading is the phone.
    return $map;
}

function import_date(string $s): ?string {
    $s = csv_norm($s);
    if ($s === '') return null;
    if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $s, $m)) { // Indian day/month/year
        return checkdate((int)$m[2], (int)$m[1], (int)$m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : null;
    }
    $s = preg_replace('/\b(Novr|Nov\.)\b/i', 'Nov', $s);
    $s = preg_replace('/,(\S)/', ', $1', $s);
    $s = preg_replace('/^([A-Za-z]+) (\d{1,2}) (\d{4})$/', '$1 $2, $3', $s);
    $t = strtotime($s);
    if (!$t) return null;
    $y = (int)date('Y', $t);
    return $y >= 1990 && $y <= 2100 ? date('Y-m-d', $t) : null;
}

function map_office_status(string $s): string {
    $l = strtolower($s);
    return match (true) {
        $l === '' => 'Filed',
        str_contains($l, 'secrecy'), str_contains($l, 'no action') => 'On hold',
        str_contains($l, 'awaiting') && (str_contains($l, 'reply') || str_contains($l, 'response')) => 'Objection / FER received',
        str_contains($l, 'objected') => 'Objection / FER received',
        str_contains($l, 'reply document received'), str_contains($l, 'reply filed'), str_contains($l, 'response filed') => 'Reply filed',
        str_contains($l, 'hearing') => 'Hearing scheduled',
        str_contains($l, 'amended case'), str_contains($l, 'under examination') => 'Under examination',
        str_contains($l, 'accepted'), str_contains($l, 'registered'), str_contains($l, 'granted'), str_contains($l, 'certificate') => 'Registered / granted',
        str_contains($l, 'published'), str_contains($l, 'advertised') => 'Published / advertised',
        str_contains($l, 'under process'), str_contains($l, 'waiting'), str_contains($l, 'wating'), str_contains($l, 'filed') => 'Filed',
        default => 'Under examination',
    };
}

/** Parses the uploaded CSV into a plan: rows to add/update and rows skipped. */
function import_plan(string $file, string $type): array {
    $fh = fopen($file, 'r');
    if (!$fh) return ['error' => 'Could not read the file.'];
    $first = fgets($fh) ?: '';
    rewind($fh);
    if (str_starts_with($first, "\xEF\xBB\xBF")) fread($fh, 3);
    $head = fgetcsv($fh, 0, ',', '"', '');
    if (!$head) return ['error' => 'The file is empty.'];
    $col = import_columns($head);
    if (!isset($col['app_no'], $col['applicant'])) return ['error' => 'Could not find the applicant name and application number columns. Check the first row has headings like "Name of the Applicant" and "Application Number".'];
    $rows = []; $skipped = []; $seen = []; $line = 1;
    while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
        $line++;
        $g = fn($f) => isset($col[$f]) ? csv_norm((string)($r[$col[$f]] ?? '')) : '';
        $app = preg_replace('/\s+/', '', $g('app_no'));
        $applicant = $g('applicant');
        if ($app === '' && $applicant === '' && implode('', array_map('trim', $r)) === '') continue;
        if ($app === '' || !preg_match('/\d{3,}/', $app)) { $skipped[] = ['line' => $line, 'why' => 'No valid application number', 'text' => trim(implode(' ', array_map('csv_norm', $r)))]; continue; }
        if ($applicant === '') { $skipped[] = ['line' => $line, 'why' => 'No applicant name', 'text' => $app]; continue; }
        if (isset($seen[$app])) { $skipped[] = ['line' => $line, 'why' => 'Same application number as row ' . $seen[$app] . ' (first one kept)', 'text' => $app . ' – ' . $applicant]; continue; }
        $seen[$app] = $line;
        $office = $g('office_status');
        $fd = $g('filed_on');
        $rows[] = ['line' => $line, 'app_no' => $app, 'applicant' => $applicant, 'filed_on' => import_date($fd), 'filed_raw' => $fd,
            'contact' => $g('contact'), 'phone' => $g('phone'), 'email' => strtolower(str_replace(' ', '', $g('email'))), 'title' => $g('title'),
            'office_status' => $office, 'status' => map_office_status($office)];
    }
    fclose($fh);

    // Group rows into clients: email, then phone, then applicant name.
    $groups = [];
    foreach ($rows as $i => $r) {
        $key = $r['email'] !== '' ? 'e:' . $r['email'] : (strlen(phone_key($r['phone'])) >= 8 ? 'p:' . phone_key($r['phone']) : 'n:' . strtolower($r['contact'] ?: $r['applicant']));
        $rows[$i]['group'] = $key;
        $groups[$key][] = $i;
    }
    $db = cdb();
    $clients = [];
    foreach ($groups as $key => $idx) {
        $names = []; $phones = []; $email = '';
        foreach ($idx as $i) {
            if ($rows[$i]['contact'] !== '') $names[$rows[$i]['contact']] = ($names[$rows[$i]['contact']] ?? 0) + 1;
            if (strlen(phone_key($rows[$i]['phone'])) >= 8) $phones[$rows[$i]['phone']] = ($phones[$rows[$i]['phone']] ?? 0) + 1;
            $email = $email ?: $rows[$i]['email'];
        }
        arsort($names); arsort($phones);
        $name = $names ? array_key_first($names) : $rows[$idx[0]]['applicant'];
        $phone = $phones ? (string)array_key_first($phones) : '';
        $existing = null;
        if ($email !== '') { $st = $db->prepare('SELECT id, name FROM clients WHERE lower(email) = ? LIMIT 1'); $st->execute([$email]); $existing = $st->fetch() ?: null; }
        if (!$existing && strlen(phone_key($phone)) >= 8) { $st = $db->prepare('SELECT id, name FROM clients WHERE phone_key = ? LIMIT 1'); $st->execute([phone_key($phone)]); $existing = $st->fetch() ?: null; }
        $clients[$key] = ['name' => $name, 'phone' => $phone, 'email' => $email, 'existing' => $existing ? (int)$existing['id'] : null, 'existing_name' => $existing['name'] ?? null, 'count' => count($idx)];
    }
    // Existing applications (same number) are updated, not duplicated.
    $find = $db->prepare('SELECT id, status, office_status, filed_on FROM matters WHERE replace(app_no, \' \', \'\') = ? LIMIT 1');
    $new = 0; $upd = 0; $same = 0;
    foreach ($rows as $i => $r) {
        $find->execute([$r['app_no']]);
        $m = $find->fetch();
        if (!$m) { $rows[$i]['action'] = 'add'; $new++; continue; }
        $rows[$i]['matter_id'] = (int)$m['id'];
        $changed = $m['office_status'] !== $r['office_status'] || (!$m['filed_on'] && $r['filed_on']);
        $rows[$i]['action'] = $changed ? 'update' : 'same';
        $changed ? $upd++ : $same++;
    }
    return ['type' => $type, 'rows' => $rows, 'clients' => $clients, 'skipped' => $skipped, 'new' => $new, 'update' => $upd, 'same' => $same,
        'bad_dates' => array_values(array_filter($rows, fn($r) => $r['filed_raw'] !== '' && !$r['filed_on']))];
}

function import_preview(): void {
    $type = array_key_exists(post('ip_type', 12), IP_TYPES) ? post('ip_type', 12) : 'design';
    // The page script sends the file base64-encoded in a normal form field (the host's firewall can block raw
    // file uploads); a plain file upload is still accepted when scripts are off.
    $b64 = $_POST['csv_b64'] ?? '';
    $tmp = null;
    if (is_string($b64) && $b64 !== '') {
        if (strlen($b64) > 7 * 1024 * 1024) import_form_page($type, 'That file is too large (over 5 MB).');
        $raw = base64_decode($b64, true);
        if ($raw === false) import_form_page($type, 'The file could not be read. Try again.');
        $tmp = tempnam(sys_get_temp_dir(), 'ltimp');
        file_put_contents($tmp, $raw);
        $path = $tmp;
    } else {
        $f = $_FILES['csv'] ?? null;
        if (!$f || !is_array($f) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) import_form_page($type, 'Choose the CSV file to upload.');
        if ((int)$f['size'] > 5 * 1024 * 1024) import_form_page($type, 'That file is too large (over 5 MB).');
        $path = (string)$f['tmp_name'];
    }
    $plan = import_plan($path, $type);
    if ($tmp) @unlink($tmp);
    if (isset($plan['error'])) import_form_page($type, $plan['error']);
    if (!$plan['rows']) import_form_page($type, 'No application rows were found in the file.');

    // Keep the parsed plan until the owner confirms (old plans are removed after an hour).
    foreach (glob(data_dir() . '/import-*.json') ?: [] as $old) if (filemtime($old) < time() - 3600) @unlink($old);
    $token = bin2hex(random_bytes(12));
    file_put_contents(data_dir() . '/import-' . $token . '.json', json_encode($plan), LOCK_EX);
    @chmod(data_dir() . '/import-' . $token . '.json', 0600);

    $addGroups = array_flip(array_column(array_filter($plan['rows'], fn($r) => $r['action'] === 'add'), 'group'));
    $newClients = count(array_filter($plan['clients'], fn($c, $k) => !$c['existing'] && isset($addGroups[$k]), ARRAY_FILTER_USE_BOTH));
    $statusCounts = array_count_values(array_column(array_filter($plan['rows'], fn($r) => $r['action'] !== 'same'), 'status'));
    arsort($statusCounts);
    $sc = '';
    foreach ($statusCounts as $s => $n) $sc .= '<tr><td>' . h($s) . '</td><td>' . $n . '</td></tr>';
    $sample = '';
    foreach (array_slice($plan['rows'], 0, 12) as $r) {
        $c = $plan['clients'][$r['group']];
        $sample .= '<div class="lead"><div class="body"><div class="top"><strong>' . h($r['applicant']) . '</strong><span class="badge ' . status_class($r['status']) . '">' . h($r['status']) . '</span></div>
          <div class="muted small">No. ' . h($r['app_no']) . ' · ' . ($r['filed_on'] ? 'Filed ' . h(nice_date($r['filed_on'])) : '<span class="late">no filing date</span>') . ' · ' . ($r['action'] === 'add' ? 'new' : ($r['action'] === 'update' ? 'update' : 'no change')) . '</div>
          <div class="meta small"><span>Client: ' . h($c['existing_name'] ?? $c['name']) . ($c['email'] ? ' (' . h($c['email']) . ')' : '') . '</span><span class="clip">' . h(mb_strimwidth($r['office_status'], 0, 90, '…')) . '</span></div></div></div>';
    }
    $sk = '';
    foreach ($plan['skipped'] as $s) $sk .= '<li>Row ' . (int)$s['line'] . ': ' . h($s['why']) . ($s['text'] !== '' ? ' – <span class="muted">' . h(mb_strimwidth($s['text'], 0, 80, '…')) . '</span>' : '') . '</li>';
    foreach ($plan['bad_dates'] as $r) $sk .= '<li>Row ' . (int)$r['line'] . ': filing date "' . h($r['filed_raw']) . '" not understood – imported without a filing date</li>';
    $awaiting = count(array_filter($plan['rows'], fn($r) => $r['status'] === 'Objection / FER received' && $r['action'] !== 'same'));

    page('Import preview', portfolio_tabs($type) . '<h1>Check before importing</h1>
    <section class="card"><div class="stats"><div><b>' . $plan['new'] . '</b><span>new ' . h(strtolower(IP_TYPES[$type])) . '</span></div><div><b>' . $plan['update'] . '</b><span>updated</span></div><div><b>' . $newClients . '</b><span>new clients</span></div></div>
      <p class="small muted">' . count($plan['rows']) . ' rows read · ' . $plan['same'] . ' already up to date · ' . count($plan['clients']) . ' contacts · ' . count($plan['skipped']) . ' skipped</p>
      <table class="mini"><tr><th>Tracker status</th><th>Rows</th></tr>' . $sc . '</table>
      ' . ($awaiting ? '<p class="alert">' . $awaiting . ' application' . ($awaiting === 1 ? ' is' : 's are') . ' awaiting an examination report reply. After importing, add each reply deadline from the official notice.</p>' : '') . '
      <form method="post" class="stack mt">' . csrf_field() . '<input type="hidden" name="action" value="c_import_run"><input type="hidden" name="token" value="' . $token . '">
        <button class="btn primary big">Import ' . ($plan['new'] + $plan['update']) . ' applications</button></form>
      <p class="small"><a href="?p=import&amp;t=' . $type . '">Cancel</a></p></section>
    ' . ($sk ? '<section class="card"><h2>Rows to check</h2><ul class="small">' . $sk . '</ul></section>' : '') . '
    <h2>First rows</h2><div class="list">' . $sample . '</div>');
}

function import_run(): void {
    $token = post('token', 40);
    $file = data_dir() . '/import-' . $token . '.json';
    if (!preg_match('/^[0-9a-f]{24}$/', $token) || !is_file($file)) redirect('/admin/?p=import', 'That preview expired. Upload the file again.');
    $plan = json_decode((string)file_get_contents($file), true);
    @unlink($file);
    if (!is_array($plan) || empty($plan['rows'])) redirect('/admin/?p=import', 'That preview could not be read. Upload the file again.');
    $type = $plan['type'];
    $db = cdb();
    $db->beginTransaction();
    try {
        $clientIds = [];
        foreach ($plan['clients'] as $key => $c) {
            if ($c['existing']) { $clientIds[$key] = (int)$c['existing']; continue; }
            if (!array_filter($plan['rows'], fn($r) => $r['group'] === $key && $r['action'] === 'add')) continue;
            $db->prepare('INSERT INTO clients (created_at, name, phone, phone_key, email, updated_at, notes) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([now_utc(), $c['name'], $c['phone'], phone_key($c['phone']), $c['email'], now_utc(), 'Imported from ' . IP_TYPES[$type] . ' sheet on ' . date('j M Y') . '.']);
            $clientIds[$key] = (int)$db->lastInsertId();
        }
        $add = $db->prepare('INSERT INTO matters (client_id, created_at, service, title, app_no, status, filed_on, ip_type, applicant, office_status, payment_status, paid, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)');
        $upd = $db->prepare('UPDATE matters SET status = ?, office_status = ?, filed_on = COALESCE(filed_on, ?), updated_at = ? WHERE id = ?');
        $old = $db->prepare('SELECT status, office_status FROM matters WHERE id = ?');
        $n = ['add' => 0, 'update' => 0];
        foreach ($plan['rows'] as $r) {
            if ($r['action'] === 'add') {
                $add->execute([$clientIds[$r['group']], now_utc(), IP_DEFAULT_SERVICE[$type], $r['title'], $r['app_no'], $r['status'], $r['filed_on'], $type, $r['applicant'], $r['office_status'], 'Pending', now_utc()]);
                $mid = (int)$db->lastInsertId();
                mlog($mid, 'Imported from sheet. Office status: ' . ($r['office_status'] ?: '–'));
                $n['add']++;
            } elseif ($r['action'] === 'update') {
                $old->execute([$r['matter_id']]); $o = $old->fetch();
                $upd->execute([$r['status'], $r['office_status'], $r['filed_on'], now_utc(), $r['matter_id']]);
                mlog((int)$r['matter_id'], 'Updated from sheet. Office status: ' . ($r['office_status'] ?: '–') . ($o && $o['status'] !== $r['status'] ? ' (status ' . $o['status'] . ' → ' . $r['status'] . ')' : ''));
                $n['update']++;
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('import: ' . $e->getMessage());
        redirect('/admin/?p=import&t=' . $type, 'The import failed and nothing was saved. Please tell Claude: ' . mb_substr($e->getMessage(), 0, 80));
    }
    redirect('/admin/?p=ip&t=' . $type, 'Imported: ' . $n['add'] . ' added, ' . $n['update'] . ' updated.');
}
