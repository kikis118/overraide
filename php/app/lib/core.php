<?php
declare(strict_types=1);

// Core helpers: config, front matter + small YAML subset, content store.

const POST_TYPES = [
    'proposal' => 'Proposal',
    'technical-test' => 'Technical Test',
    'film-review' => 'Film Review',
    'reading-notes' => 'Reading Notes',
    'shoot-day' => 'Shoot Day',
    'journal' => 'Journal',
    'reflection' => 'Reflection',
];
const PAGES = ['film' => 'The film', 'marking' => 'Marking guide', 'about' => 'About', 'proposal' => 'Proposal (one page, unlisted)'];

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function config(string $key, $default = null) {
    static $cfg = null;
    if ($cfg === null) {
        $file = APP_DIR . '/config.php';
        $cfg = is_file($file) ? (require $file) : [];
    }
    return $cfg[$key] ?? $default;
}

function content_dir(string $sub = ''): string { return APP_DIR . '/content' . ($sub !== '' ? '/' . $sub : ''); }

/* ---------- YAML subset (front matter + references.yml) ---------- */

function yaml_parse(string $text): array {
    $lines = [];
    foreach (preg_split('/\r?\n/', $text) as $raw) {
        if (trim($raw) === '' || preg_match('/^\s*#/', $raw)) continue;
        $content = ltrim($raw, ' ');
        $lines[] = [strlen($raw) - strlen($content), rtrim($content)];
    }
    $i = 0;
    $out = yaml_block($lines, $i, 0);
    if ($i < count($lines)) throw new RuntimeException('Bad indentation near: ' . $lines[$i][1]);
    return $out;
}

function yaml_block(array $lines, int &$i, int $indent): array {
    $out = [];
    while ($i < count($lines) && $lines[$i][0] === $indent) {
        $content = $lines[$i][1];
        if (!preg_match('/^([A-Za-z0-9_.\-]+):(?:\s+(.*))?$/u', $content, $m)) {
            throw new RuntimeException('Cannot read line: ' . $content);
        }
        $key = $m[1];
        $val = trim($m[2] ?? '');
        $i++;
        if ($val === '' || $val[0] === '#') {
            if ($i < count($lines) && $lines[$i][0] > $indent) {
                $out[$key] = yaml_block($lines, $i, $lines[$i][0]);
            } else {
                $out[$key] = null;
            }
        } else {
            $out[$key] = yaml_value($val);
        }
    }
    return $out;
}

function yaml_strip_comment(string $s): string {
    $q = '';
    $len = strlen($s);
    for ($k = 0; $k < $len; $k++) {
        $c = $s[$k];
        if ($q !== '') {
            if ($c === '\\' && $q === '"') { $k++; continue; }
            if ($c === $q) $q = '';
        } elseif ($c === '"' || $c === "'") {
            $q = $c;
        } elseif ($c === '#' && ($k === 0 || ctype_space($s[$k - 1]))) {
            return rtrim(substr($s, 0, $k));
        }
    }
    return $s;
}

function yaml_split(string $s): array {
    $parts = []; $depth = 0; $q = ''; $cur = '';
    $len = strlen($s);
    for ($k = 0; $k < $len; $k++) {
        $c = $s[$k];
        if ($q !== '') {
            $cur .= $c;
            if ($c === '\\' && $q === '"' && $k + 1 < $len) { $cur .= $s[++$k]; continue; }
            if ($c === $q) $q = '';
            continue;
        }
        if ($c === '"' || $c === "'") { $q = $c; $cur .= $c; continue; }
        if ($c === '[' || $c === '{') $depth++;
        if ($c === ']' || $c === '}') $depth--;
        if ($c === ',' && $depth === 0) { $parts[] = trim($cur); $cur = ''; continue; }
        $cur .= $c;
    }
    if (trim($cur) !== '') $parts[] = trim($cur);
    return $parts;
}

function yaml_value(string $val) {
    $val = yaml_strip_comment($val);
    if ($val === '' || $val === '~' || $val === 'null') return null;
    $c = $val[0];
    if ($c === '"') {
        $d = json_decode($val);
        if (!is_string($d)) throw new RuntimeException('Bad quoted string: ' . $val);
        return $d;
    }
    if ($c === "'") {
        if (substr($val, -1) !== "'") throw new RuntimeException('Bad quoted string: ' . $val);
        return str_replace("''", "'", substr($val, 1, -1));
    }
    if ($c === '[') {
        if (substr($val, -1) !== ']') throw new RuntimeException('Unclosed [ in: ' . $val);
        return array_map('yaml_value', yaml_split(substr($val, 1, -1)));
    }
    if ($c === '{') {
        if (substr($val, -1) !== '}') throw new RuntimeException('Unclosed { in: ' . $val);
        $map = [];
        foreach (yaml_split(substr($val, 1, -1)) as $pair) {
            if (!preg_match('/^([A-Za-z0-9_.\-]+):\s*(.*)$/su', $pair, $m)) throw new RuntimeException('Bad map entry: ' . $pair);
            $map[$m[1]] = yaml_value($m[2]);
        }
        return $map;
    }
    if ($val === 'true') return true;
    if ($val === 'false') return false;
    if (preg_match('/^-?\d+$/', $val)) return (int)$val;
    return $val;
}

function yaml_str(string $s): string {
    return json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}

/* ---------- front matter ---------- */

function split_front(string $raw): array {
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    if (preg_match('/\A---\n(.*?)\n---[ \t]*(?:\n|\z)(.*)\z/s', $raw, $m)) return [$m[1], ltrim($m[2], "\n")];
    return ['', $raw];
}

function join_front(string $front, string $body): string {
    return "---\n" . rtrim($front, "\n") . "\n---\n\n" . ltrim($body, "\n");
}

/* ---------- files ---------- */

function write_atomic(string $path, string $data): void {
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Cannot create ' . $dir);
    if (is_file($path)) keep_history($path);
    $tmp = $path . '.tmp' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $data, LOCK_EX) === false) throw new RuntimeException('Cannot write ' . $path);
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Cannot replace ' . $path);
    }
}

// every overwrite or delete keeps the previous version, so nothing is ever lost to a bad save
function keep_history(string $path, string $suffix = ''): void {
    $dir = content_dir('.history');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = basename($path) . '.' . gmdate('Ymd-His') . $suffix . '.bak';
    @copy($path, $dir . '/' . $name);
    $old = glob($dir . '/' . basename($path) . '.*.bak') ?: [];
    sort($old);
    foreach (array_slice($old, 0, max(0, count($old) - 25)) as $f) @unlink($f);
}

/* ---------- content ---------- */

function parse_post_file(string $file): ?array {
    $raw = @file_get_contents($file);
    if ($raw === false) return null;
    [$front, $body] = split_front($raw);
    try { $data = yaml_parse($front); } catch (RuntimeException $ex) { $data = ['_error' => $ex->getMessage()]; }
    $data += ['title' => basename($file, '.md'), 'type' => 'journal', 'date' => '1970-01-01', 'draft' => false, 'refs' => []];
    return [
        'slug' => basename($file, '.md'),
        'file' => $file,
        'data' => $data,
        'body' => $body,
        'front' => $front,
    ];
}

function list_posts(bool $includeDrafts): array {
    $posts = [];
    foreach (glob(content_dir('posts') . '/*.md') ?: [] as $file) {
        $p = parse_post_file($file);
        if (!$p) continue;
        if (!$includeDrafts && !empty($p['data']['draft'])) continue;
        $posts[] = $p;
    }
    usort($posts, fn($a, $b) => [$b['data']['date'], $b['slug']] <=> [$a['data']['date'], $a['slug']]);
    return $posts;
}

function find_post(string $slug): ?array {
    if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) return null;
    $file = content_dir('posts') . '/' . $slug . '.md';
    return is_file($file) ? parse_post_file($file) : null;
}

function load_references(): array {
    $file = content_dir('references.yml');
    try { return is_file($file) ? yaml_parse(file_get_contents($file)) : []; } catch (RuntimeException $ex) { return []; }
}

function find_page(string $name): ?array {
    if (!isset(PAGES[$name])) return null;
    $file = content_dir('pages') . '/' . $name . '.md';
    if (!is_file($file)) return null;
    [$front, $body] = split_front(file_get_contents($file));
    try { $data = yaml_parse($front); } catch (RuntimeException $ex) { $data = []; }
    return ['name' => $name, 'file' => $file, 'data' => $data + ['title' => PAGES[$name]], 'body' => $body];
}

function slugify(string $title): string {
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
        if ($t !== false && $t !== '') $title = $t;
    }
    $s = strtolower($title);
    $s = preg_replace('/[^a-z0-9\s-]/', '', $s);
    $s = trim(preg_replace('/[\s_-]+/', '-', $s), '-');
    return substr($s, 0, 60) ?: 'post';
}

function format_date(string $d): string {
    try { return (new DateTimeImmutable($d))->format('j F Y'); } catch (Exception $ex) { return $d; }
}

// Same rules as the Astro schema: a bad post is rejected before it is saved.
function validate_post(array $data, array $refs): array {
    $errors = [];
    if (trim((string)($data['title'] ?? '')) === '') $errors[] = 'Title is required.';
    if (!isset(POST_TYPES[$data['type'] ?? ''])) $errors[] = 'Unknown post type.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($data['date'] ?? '')) || !strtotime((string)$data['date'])) $errors[] = 'Date must look like 2026-10-06.';
    if (($data['type'] ?? '') === 'film-review') {
        $f = $data['film'] ?? null;
        if (!is_array($f) || empty($f['title']) || empty($f['director']) || empty($f['year']) || empty($f['country']) || empty($f['ref'])) {
            $errors[] = 'A film review needs a film: block with title, director, year, country and ref.';
        }
    }
    if (($data['type'] ?? '') === 'reading-notes' && empty($data['reading'])) $errors[] = 'Reading notes need reading: <reference id>.';
    $ids = array_merge((array)($data['refs'] ?? []), isset($data['film']['ref']) ? [$data['film']['ref']] : [], !empty($data['reading']) ? [$data['reading']] : []);
    foreach ($ids as $id) {
        if (!isset($refs[$id])) $errors[] = "Reference '$id' is not in the reference list.";
    }
    return $errors;
}

function validate_references(string $text): array {
    try { $refs = yaml_parse($text); } catch (RuntimeException $ex) { return ['Could not read the reference list: ' . $ex->getMessage()]; }
    $errors = [];
    $kinds = ['book', 'chapter', 'article', 'film', 'web', 'artwork'];
    foreach ($refs as $id => $r) {
        if (!is_array($r)) { $errors[] = "$id: not an entry."; continue; }
        if (!in_array($r['kind'] ?? '', $kinds, true)) $errors[] = "$id: kind must be one of " . implode(', ', $kinds) . '.';
        if (!isset($r['title']) || !isset($r['year'])) $errors[] = "$id: needs title and year.";
    }
    return $errors;
}
