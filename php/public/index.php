<?php
declare(strict_types=1);

// Front controller. Everything that is not a real file (assets/, media/) lands here.
// Run locally:  php -S 127.0.0.1:8099 -t php/public php/public/index.php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (PHP_SAPI === 'cli-server') { // let the dev server serve real files itself
    $real = realpath(__DIR__ . $path);
    if ($path !== '/' && $real !== false && is_file($real) && strpos($real, realpath(__DIR__)) === 0 && substr($real, -4) !== '.php') return false;
}

ini_set('display_errors', '0');
define('APP_DIR', is_dir(__DIR__ . '/app') ? __DIR__ . '/app' : dirname(__DIR__) . '/app');
foreach (['core', 'markdown', 'harvard', 'auth', 'views'] as $lib) require APP_DIR . '/lib/' . $lib . '.php';

// base path when the site lives in a sub-folder (e.g. https://example.com/blog/)
$root = str_replace('\\', '/', (string)realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')));
$here = str_replace('\\', '/', (string)realpath(__DIR__));
$BASE = config('base_path');
if ($BASE === null) $BASE = ($root !== '' && strpos($here, $root) === 0) ? rtrim(substr($here, strlen($root)), '/') : '';
$GLOBALS['BASE'] = $BASE;
if ($BASE !== '' && strpos($path, $BASE) === 0) $path = substr($path, strlen($BASE)) ?: '/';
$route = '/' . trim(rawurldecode($path), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
if (is_admin()) header('Cache-Control: private, no-store');

function send(string $html, int $code = 200): never { http_response_code($code); header('Content-Type: text/html; charset=utf-8'); echo $html; exit; }
function go(string $to): never { header('Location: ' . url($to)); exit; }
function require_admin(): void { if (!is_admin()) go('/admin/'); }
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') send(view_404(), 405);
    if (!csrf_ok()) { http_response_code(400); exit('Session expired. Go back, reload the page and try again.'); }
}
function json_out(array $d, int $code = 200): never { http_response_code($code); header('Content-Type: application/json'); echo json_encode($d); exit; }

/* ---------- public pages ---------- */

if ($route === '/' && $method === 'GET') send(view_home());
if ($route === '/references' && $method === 'GET') send(view_references());
if (preg_match('#^/posts/([a-z0-9-]+)$#', $route, $m) && $method === 'GET') {
    $p = find_post($m[1]);
    if ($p && (is_admin() || empty($p['data']['draft']))) send(view_post($p));
    send(view_404(), 404);
}
if (preg_match('#^/(film|marking|about|proposal)$#', $route, $m) && $method === 'GET') {
    $pg = find_page($m[1]);
    $pg ? send(view_page($pg)) : send(view_404(), 404);
}

/* ---------- admin ---------- */

if ($route === '/admin' && $method === 'GET') {
    auth_boot(!isset($_COOKIE['overraide_admin']));
    send(is_admin() ? view_dashboard((string)($_GET['saved'] ?? '') !== '' ? 'Saved.' : ((string)($_GET['deleted'] ?? '') !== '' ? 'Deleted. A copy is kept in the history folder.' : '')) : view_login());
}
if ($route === '/admin/login') {
    auth_boot(true);
    if (!csrf_ok()) send(view_login('Session expired, try again.'), 400);
    [$ok, $msg] = login_with_pin((string)($_POST['pin'] ?? ''));
    $ok ? go('/admin/') : send(view_login($msg), 401);
}
if ($route === '/admin/logout') { require_post(); logout(); go('/'); }

if ($route === '/admin/new' && $method === 'GET') {
    require_admin();
    send(view_editor('post', ['isnew' => true, 'key' => '', 'title' => '', 'type' => 'journal', 'date' => date('Y-m-d'), 'summary' => '', 'draft' => true, 'refs' => '', 'other' => '', 'body' => '']));
}

if (preg_match('#^/admin/edit/post/([a-z0-9-]+)$#', $route, $m) && $method === 'GET') {
    require_admin();
    $p = find_post($m[1]) ?? send(view_404(), 404);
    $d = $p['data'];
    // everything except the fields that have their own inputs is kept verbatim
    $other = [];
    $skip = false;
    foreach (explode("\n", $p['front']) as $line) {
        if (preg_match('/^([A-Za-z0-9_]+):/', $line, $k)) $skip = in_array($k[1], ['title', 'type', 'date', 'summary', 'draft', 'refs'], true);
        if (!$skip) $other[] = $line;
    }
    send(view_editor('post', [
        'isnew' => false, 'key' => $p['slug'], 'title' => (string)$d['title'], 'type' => (string)$d['type'], 'date' => (string)$d['date'],
        'summary' => (string)($d['summary'] ?? ''), 'draft' => !empty($d['draft']), 'refs' => implode(', ', (array)($d['refs'] ?? [])),
        'other' => trim(implode("\n", $other)), 'body' => $p['body'],
    ]));
}
if (preg_match('#^/admin/edit/page/(film|marking|about|proposal)$#', $route, $m) && $method === 'GET') {
    require_admin();
    $pg = find_page($m[1]) ?? send(view_404(), 404);
    send(view_editor('page', ['key' => $m[1], 'title' => (string)$pg['data']['title'], 'description' => (string)($pg['data']['description'] ?? ''), 'body' => $pg['body']]));
}
if ($route === '/admin/edit/references' && $method === 'GET') {
    require_admin();
    send(view_editor('references', ['key' => '', 'body' => (string)@file_get_contents(content_dir('references.yml'))]));
}

if ($route === '/admin/preview') {
    require_admin(); require_post();
    header('Content-Type: text/html; charset=utf-8');
    echo md((string)($_POST['body'] ?? ''));
    exit;
}

if ($route === '/admin/upload') {
    require_admin(); require_post();
    $f = $_FILES['file'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) json_out(['error' => 'No file received.'], 400);
    if ($f['size'] > 12 * 1024 * 1024) json_out(['error' => 'File is over 12 MB.'], 400);
    $info = @getimagesize($f['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$ext) json_out(['error' => 'Only JPG, PNG, GIF or WebP images.'], 400);
    $name = slugify(pathinfo((string)$f['name'], PATHINFO_FILENAME));
    $dir = __DIR__ . '/media';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $target = "$name.$ext";
    for ($k = 2; is_file("$dir/$target"); $k++) $target = "$name-$k.$ext";
    if (!move_uploaded_file($f['tmp_name'], "$dir/$target")) json_out(['error' => 'Could not save the file.'], 500);
    json_out(['path' => '/media/' . $target]);
}

if ($route === '/admin/save') {
    require_admin(); require_post();
    $mode = (string)($_POST['mode'] ?? '');
    $key = (string)($_POST['key'] ?? '');
    $body = str_replace(["\r\n", "\r"], "\n", (string)($_POST['body'] ?? ''));

    if ($mode === 'references') {
        $errs = validate_references($body);
        if ($errs) send(view_editor('references', ['key' => '', 'body' => $body], $errs), 422);
        write_atomic(content_dir('references.yml'), rtrim($body, "\n") . "\n");
        go('/admin/?saved=1');
    }

    if ($mode === 'page' && isset(PAGES[$key])) {
        $title = trim((string)($_POST['title'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $front = 'title: ' . yaml_str($title) . ($desc !== '' ? "\ndescription: " . yaml_str($desc) : '');
        if ($title === '') send(view_editor('page', ['key' => $key, 'title' => $title, 'description' => $desc, 'body' => $body], ['Title is required.']), 422);
        write_atomic(content_dir('pages') . "/$key.md", join_front($front, $body));
        go('/' . $key . '/');
    }

    if ($mode === 'post') {
        $isnew = $key === '';
        if (!$isnew && !find_post($key)) send(view_404(), 404);
        $f = [
            'isnew' => $isnew, 'key' => $key, 'title' => trim((string)($_POST['title'] ?? '')), 'type' => (string)($_POST['type'] ?? 'journal'),
            'date' => (string)($_POST['date'] ?? ''), 'summary' => trim((string)($_POST['summary'] ?? '')), 'draft' => !empty($_POST['draft']),
            'refs' => trim((string)($_POST['refs'] ?? '')), 'other' => trim(str_replace("\r", '', (string)($_POST['other'] ?? ''))), 'body' => $body,
        ];
        if (!empty($_POST['delete']) && !$isnew) {
            $file = content_dir('posts') . "/$key.md";
            keep_history($file, '.deleted');
            unlink($file);
            go('/admin/?deleted=1');
        }
        $refIds = array_values(array_filter(array_map('trim', explode(',', $f['refs']))));
        $front = 'title: ' . yaml_str($f['title']) . "\ntype: {$f['type']}\ndate: {$f['date']}\nsummary: " . yaml_str($f['summary'])
            . "\ndraft: " . ($f['draft'] ? 'true' : 'false') . "\nrefs: [" . implode(', ', $refIds) . ']'
            . ($f['other'] !== '' ? "\n" . $f['other'] : '');
        try { $data = yaml_parse($front); $errs = validate_post($data, load_references()); }
        catch (RuntimeException $ex) { $errs = ['Front matter problem: ' . $ex->getMessage()]; }
        if ($errs) send(view_editor('post', $f, $errs), 422);
        if ($isnew) {
            $key = $f['date'] . '-' . slugify($f['title']);
            if (is_file(content_dir('posts') . "/$key.md")) send(view_editor('post', $f, ['A post with that date and title already exists.']), 422);
        }
        write_atomic(content_dir('posts') . "/$key.md", join_front($front, $body));
        go('/admin/edit/post/' . $key . '?saved=1');
    }
    send(view_404(), 404);
}

send(view_404(), 404);
