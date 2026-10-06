<?php
declare(strict_types=1);

// HTML for every page. Plain PHP, no template engine.

function url(string $path = ''): string { return ($GLOBALS['BASE'] ?? '') . $path; }

function md(string $text): string { static $m = null; $m ??= new Markdown($GLOBALS['BASE'] ?? ''); return $m->render($text); }

function layout(string $title, string $body, array $opt = []): string
{
    $site = config('site_name', 'overraide');
    $desc = $opt['description'] ?? 'Development log for an experimental short film.';
    $admin = is_admin();
    $t = $title !== '' ? e($title) . ' · ' . e($site) : e($site);
    $nav = '';
    foreach ([['/', 'Posts'], ['/film/', 'The film'], ['/marking/', 'Marking guide'], ['/references/', 'References'], ['/about/', 'About']] as [$href, $label]) {
        $nav .= '<a href="' . url($href) . '">' . $label . '</a>';
    }
    $bar = '';
    if ($admin) {
        $edit = isset($opt['edit']) ? '<a href="' . url($opt['edit']) . '">Edit this page</a>' : '';
        $bar = '<div class="adminbar"><div class="wrap"><span class="dot"></span> Admin'
            . $edit
            . '<a href="' . url('/admin/new') . '">New post</a>'
            . '<a href="' . url('/admin/') . '">All content</a>'
            . '<form method="post" action="' . url('/admin/logout') . '"><input type="hidden" name="csrf" value="' . e(csrf_token()) . '"><button>Sign out</button></form>'
            . '</div></div>';
    }
    $footerLogin = $admin ? '' : ' · <a class="quiet" href="' . url('/admin/') . '">Admin</a>';
    $robots = !empty($opt['noindex']) ? '<meta name="robots" content="noindex">' : '';
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . "<title>$t</title><meta name=\"description\" content=\"" . e($desc) . "\">$robots"
        . '<link rel="icon" href="' . url('/favicon.svg') . '" type="image/svg+xml"><link rel="stylesheet" href="' . url('/assets/style.css') . '">'
        . '</head><body class="' . ($admin ? 'is-admin' : '') . '">' . $bar
        . '<header class="site"><div class="wrap"><a class="name" href="' . url('/') . '">OVERRAIDE</a><nav>' . $nav . '</nav></div></header>'
        . '<main class="wrap">' . $body . '</main>'
        . '<footer class="site"><div class="wrap">CIN506 Experimental Film and Animation · Ulster University' . $footerLogin . '</div></footer>'
        . ($opt['script'] ?? '') . '</body></html>';
}

function view_home(): string
{
    $admin = is_admin();
    $posts = list_posts($admin);
    $used = [];
    foreach (POST_TYPES as $k => $label) foreach ($posts as $p) if ($p['data']['type'] === $k) { $used[$k] = $label; break; }

    $b = '<h1>overraide</h1><p class="meta">Development log for OVERRAIDE, an experimental short film.</p>';
    if (count($used) > 1) {
        $b .= '<div class="filters" id="filters"><button class="on" data-type="all">All</button>';
        foreach ($used as $k => $label) $b .= '<button data-type="' . e($k) . '">' . e($label) . '</button>';
        $b .= '</div>';
    }
    if (!$posts) $b .= '<p>No posts yet.</p>';
    foreach ($posts as $p) {
        $d = $p['data'];
        $b .= '<a class="card" href="' . url('/posts/' . $p['slug'] . '/') . '" data-type="' . e((string)$d['type']) . '"><div class="meta">'
            . '<span class="tag">' . e(POST_TYPES[$d['type']] ?? (string)$d['type']) . '</span>'
            . '<time datetime="' . e((string)$d['date']) . '">' . e(format_date((string)$d['date'])) . '</time>'
            . (!empty($d['draft']) ? '<span class="unverified">draft</span>' : '') . '</div>'
            . '<h2>' . e((string)$d['title']) . '</h2>'
            . (!empty($d['summary']) ? '<p>' . e((string)$d['summary']) . '</p>' : '') . '</a>';
    }
    $script = <<<'JS'
<script>
const bar = document.getElementById("filters");
bar && bar.addEventListener("click", (e) => {
  const btn = e.target.closest("button"); if (!btn) return;
  bar.querySelectorAll("button").forEach((b) => b.classList.toggle("on", b === btn));
  document.querySelectorAll(".card").forEach((c) => { c.hidden = btn.dataset.type !== "all" && c.dataset.type !== btn.dataset.type; });
});
</script>
JS;
    return layout('', $b, ['script' => $script]);
}

function post_refs(array $data, array $all): array
{
    $ids = array_merge((array)($data['refs'] ?? []), isset($data['film']['ref']) ? [$data['film']['ref']] : [], !empty($data['reading']) ? [$data['reading']] : []);
    $out = [];
    foreach (array_unique($ids) as $id) if (isset($all[$id])) $out[$id] = $all[$id];
    uasort($out, fn($a, $b) => strcmp(harvard_sort_key($a), harvard_sort_key($b)));
    return $out;
}

function view_post(array $p): string
{
    $d = $p['data'];
    $all = load_references();
    $b = '<article><div class="meta"><span class="tag">' . e(POST_TYPES[$d['type']] ?? (string)$d['type']) . '</span>'
        . '<span>Published <time datetime="' . e((string)$d['date']) . '">' . e(format_date((string)$d['date'])) . '</time></span>'
        . (!empty($d['day']) ? '<span>Shoot day ' . e((string)$d['day']) . (!empty($d['setups']) ? ' · setups ' . e(implode(', ', (array)$d['setups'])) : '') . '</span>' : '')
        . (!empty($d['draft']) ? '<span class="unverified">draft: only you can see this</span>' : '')
        . '</div><h1>' . e((string)$d['title']) . '</h1>';
    if (!empty($d['film']) && is_array($d['film'])) {
        $f = $d['film'];
        $b .= '<dl class="filmbox"><dt>Film</dt><dd><i>' . e((string)($f['title'] ?? '')) . '</i></dd><dt>Director</dt><dd>' . e((string)($f['director'] ?? ''))
            . '</dd><dt>Year</dt><dd>' . e((string)($f['year'] ?? '')) . '</dd><dt>Country</dt><dd>' . e((string)($f['country'] ?? '')) . '</dd></dl>';
    }
    if (!empty($d['reading']) && isset($all[$d['reading']])) {
        $b .= '<div class="filmbox"><span style="color:var(--dim)">Reading · </span>' . harvard($all[$d['reading']]) . '</div>';
    }
    $b .= md($p['body']);
    $refs = post_refs($d, $all);
    if ($refs) {
        $b .= '<h2 id="references">References</h2><ul class="refs">';
        foreach ($refs as $r) $b .= '<li>' . harvard($r) . (is_admin() && empty($r['verified']) ? ' <span class="unverified">⚠ unverified</span>' : '') . '</li>';
        $b .= '</ul>';
    }
    $b .= '</article>';
    return layout((string)$d['title'], $b, ['description' => (string)($d['summary'] ?? ''), 'edit' => '/admin/edit/post/' . $p['slug']]);
}

function view_page(array $pg): string
{
    $title = (string)$pg['data']['title'];
    $body = md($pg['body']);
    if (!preg_match('/<h1[\s>]/', $body)) $body = '<h1>' . e($title) . '</h1>' . $body;
    return layout($title, $body, ['description' => (string)($pg['data']['description'] ?? ''), 'edit' => '/admin/edit/page/' . $pg['name']]);
}

function view_references(): string
{
    $refs = load_references();
    uasort($refs, fn($a, $b) => strcmp(harvard_sort_key($a), harvard_sort_key($b)));
    $groups = ['Reading' => ['book', 'chapter', 'article', 'web'], 'Films and works' => ['film', 'artwork']];
    $b = '<h1>References</h1><p class="meta">Harvard style.</p>';
    $pending = count(array_filter($refs, fn($r) => empty($r['verified'])));
    if (is_admin() && $pending) $b .= '<p class="unverified">⚠ ' . $pending . ' of ' . count($refs) . ' references not yet verified against the source (only shown to you).</p>';
    foreach ($groups as $label => $kinds) {
        $list = array_filter($refs, fn($r) => in_array($r['kind'] ?? '', $kinds, true));
        if (!$list) continue;
        $b .= '<h2>' . e($label) . '</h2><ul class="refs">';
        foreach ($list as $r) $b .= '<li>' . harvard($r) . '</li>';
        $b .= '</ul>';
    }
    return layout('References', $b, ['edit' => '/admin/edit/references']);
}

function view_404(): string
{
    return layout('Not found', '<h1>Not found</h1><p>That page does not exist. <a href="' . url('/') . '">Back to the posts</a>.</p>');
}

/* ---------- admin ---------- */

function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }

function view_login(string $msg = ''): string
{
    $b = '<h1>Admin</h1><p class="meta">Enter your PIN to get the editing controls.</p>'
        . ($msg ? '<p class="notice err">' . e($msg) . '</p>' : '')
        . '<form method="post" action="' . url('/admin/login') . '" class="stack narrow">' . csrf_field()
        . '<input type="password" name="pin" inputmode="numeric" autocomplete="current-password" placeholder="PIN" autofocus required>'
        . '<button class="btn" type="submit">Sign in</button></form>';
    return layout('Admin', $b, ['noindex' => true]);
}

function view_dashboard(string $flash = ''): string
{
    $b = '<h1>Content</h1>' . ($flash ? '<p class="notice ok">' . e($flash) . '</p>' : '')
        . '<p><a class="btn" href="' . url('/admin/new') . '">New post</a></p><h2>Posts</h2><ul class="admin-list">';
    foreach (list_posts(true) as $p) {
        $d = $p['data'];
        $b .= '<li><a href="' . url('/admin/edit/post/' . $p['slug']) . '">' . e((string)$d['title']) . '</a>'
            . '<span class="meta"><span class="tag">' . e(POST_TYPES[$d['type']] ?? (string)$d['type']) . '</span>' . e(format_date((string)$d['date']))
            . (!empty($d['draft']) ? '<span class="unverified">draft</span>' : '<span class="live">live</span>')
            . '<a href="' . url('/posts/' . $p['slug'] . '/') . '">view</a></span></li>';
    }
    $b .= '</ul><h2>Pages</h2><ul class="admin-list">';
    foreach (PAGES as $name => $label) $b .= '<li><a href="' . url('/admin/edit/page/' . $name) . '">' . e($label) . '</a></li>';
    $b .= '<li><a href="' . url('/admin/edit/references') . '">Reference list</a></li></ul>';
    $b .= '<h2>Safety</h2><p class="meta">Every save keeps the previous version in <code>app/content/.history</code>, and deleted posts are kept there too.</p>';
    return layout('Content', $b, ['noindex' => true]);
}

function textfield(string $name, string $label, string $val, string $extra = '', string $hint = ''): string
{
    return '<label>' . e($label) . '<input name="' . e($name) . '" value="' . e($val) . '" ' . $extra . '></label>' . ($hint ? '<p class="hint">' . e($hint) . '</p>' : '');
}

// $mode: post | page | references
function view_editor(string $mode, array $f, array $errors = [], string $flash = ''): string
{
    $head = match ($mode) { 'post' => $f['isnew'] ? 'New post' : 'Edit post', 'page' => 'Edit page', default => 'Reference list' };
    $b = '<h1>' . e($head) . '</h1>';
    foreach ($errors as $er) $b .= '<p class="notice err">' . e($er) . '</p>';
    if ($flash) $b .= '<p class="notice ok">' . e($flash) . '</p>';
    $b .= '<form method="post" action="' . url('/admin/save') . '" class="stack" id="editor">' . csrf_field()
        . '<input type="hidden" name="mode" value="' . e($mode) . '"><input type="hidden" name="key" value="' . e((string)($f['key'] ?? '')) . '">';
    if ($mode === 'post') {
        $b .= textfield('title', 'Title', (string)$f['title'], 'required');
        $opts = '';
        foreach (POST_TYPES as $k => $l) $opts .= '<option value="' . e($k) . '"' . ($f['type'] === $k ? ' selected' : '') . '>' . e($l) . '</option>';
        $b .= '<div class="row"><label>Type<select name="type">' . $opts . '</select></label>'
            . textfield('date', 'Date it happened', (string)$f['date'], 'type="date" required') . '</div>'
            . '<p class="hint">The date is shown on the post: use the day the work happened, no backdating.</p>'
            . textfield('summary', 'Summary (one line, shown on the home page)', (string)$f['summary'])
            . '<label class="check"><input type="checkbox" name="draft" value="1"' . (!empty($f['draft']) ? ' checked' : '') . '> Draft (only visible to you until you untick this)</label>'
            . textfield('refs', 'References (ids from the reference list, comma separated)', (string)$f['refs'], 'placeholder="robertson-2023, eye-machine"')
            . '<label>Other front matter (film-review needs a film: block, reading-notes needs reading:, shoot-day can use day and setups)<textarea name="other" rows="4" class="mono">' . e((string)$f['other']) . '</textarea></label>';
    } elseif ($mode === 'page') {
        $b .= textfield('title', 'Title', (string)$f['title'], 'required') . textfield('description', 'Description (search engines)', (string)$f['description']);
    } else {
        $b .= '<p class="hint">One entry per id. Set <code>verified: true</code> only after checking against the real source. Kinds: book, chapter, article, film, web, artwork. Saved only if the list is readable.</p>';
    }
    $label = $mode === 'references' ? 'references.yml' : 'Text (Markdown)';
    $b .= '<label>' . $label . '<textarea name="body" id="body" rows="' . ($mode === 'references' ? 28 : 22) . '" class="mono">' . e((string)$f['body']) . '</textarea></label>';
    if ($mode !== 'references') {
        $b .= '<div class="row tools"><label class="btn ghost">Add image<input type="file" id="img" accept="image/*" hidden></label>'
            . '<button class="btn ghost" type="button" id="preview-btn">Preview</button><span class="hint" id="tool-msg"></span></div>'
            . '<div id="preview" class="preview" hidden></div>';
    }
    $b .= '<div class="row"><button class="btn" type="submit">Save</button><a class="btn ghost" href="' . url('/admin/') . '">Cancel</a>';
    if ($mode === 'post' && !$f['isnew']) {
        $b .= '<button class="btn danger" type="submit" name="delete" value="1" formnovalidate onclick="return confirm(\'Delete this post? A copy is kept in the history folder.\')">Delete</button>';
    }
    $b .= '</div></form>';
    $csrf = json_encode(csrf_token());
    $upload = json_encode(url('/admin/upload'));
    $prev = json_encode(url('/admin/preview'));
    $script = <<<JS
<script>
const csrf = $csrf, body = document.getElementById("body"), msg = document.getElementById("tool-msg");
const img = document.getElementById("img"), pbtn = document.getElementById("preview-btn"), pv = document.getElementById("preview");
img && img.addEventListener("change", async () => {
  if (!img.files[0]) return;
  msg.textContent = "Uploading...";
  const fd = new FormData(); fd.append("file", img.files[0]); fd.append("csrf", csrf);
  try {
    const r = await fetch($upload, { method: "POST", body: fd, credentials: "same-origin" });
    const j = await r.json();
    if (!r.ok) throw new Error(j.error || "Upload failed");
    const at = body.selectionStart, add = "\\n\\n![Describe the image](" + j.path + ")\\n\\n";
    body.value = body.value.slice(0, at) + add + body.value.slice(at);
    msg.textContent = "Added " + j.path + ". Edit the description in the brackets.";
  } catch (e) { msg.textContent = e.message; }
  img.value = "";
});
pbtn && pbtn.addEventListener("click", async () => {
  const fd = new FormData(); fd.append("body", body.value); fd.append("csrf", csrf);
  const r = await fetch($prev, { method: "POST", body: fd, credentials: "same-origin" });
  pv.innerHTML = await r.text(); pv.hidden = false;
});
</script>
JS;
    return layout($head, $b, ['noindex' => true, 'script' => $script]);
}
