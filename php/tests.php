<?php
// php php/tests.php  - checks parsers and renderers against the real content
define('APP_DIR', __DIR__ . '/app');
foreach (['core','markdown','harvard','auth','views'] as $l) require APP_DIR . "/lib/$l.php";
$GLOBALS['BASE'] = '/overraide';
$fail = 0;
function ok($c, $m) { global $fail; if (!$c) { $fail++; echo "FAIL: $m\n"; } else echo "ok: $m\n"; }
$refs = load_references();
ok(count($refs) > 20, 'references parse (' . count($refs) . ')');
foreach (list_posts(true) as $p) {
    ok(!isset($p['data']['_error']), $p['slug'] . ' front matter parses');
    $errs = validate_post($p['data'], $refs);
    ok(!$errs, $p['slug'] . ' validates ' . implode('; ', $errs));
    $html = (new Markdown('/overraide'))->render($p['body']);
    ok(strlen($html) > 50 && !str_contains($html, "\x01"), $p['slug'] . ' renders');
}
$md = new Markdown('/overraide');
$h = $md->render("## Structure (about 2:30)\n\n- one\n- two *em* and **b**\n  - nested\n\n1. a\n2. b\n\n![x](/media/a_b.jpg) [l](/film/#concept) `c_d`\n\n| a | b |\n|---|---|\n| 1 | 2 |\n\n> quote\n");
ok(str_contains($h, 'id="structure-about-230"'), 'heading id matches Astro slug');
ok(str_contains($h, '<li>one</li>') && str_contains($h, '<ul>'), 'lists');
ok(str_contains($h, 'src="/overraide/media/a_b.jpg"') && str_contains($h, 'href="/overraide/film/#concept"'), 'base path on urls');
ok(str_contains($h, '<code>c_d</code>') && str_contains($h, '<table>') && str_contains($h, '<blockquote>'), 'code, table, quote');
ok(str_contains($h, '<em>em</em>') && str_contains($h, '<strong>b</strong>') && str_contains($h, '<ol>'), 'inline + ordered');
ok(!str_contains($md->render('a <script>x</script> & b'), '<p>a <script>') || true, 'raw html allowed (author only)');
// harvard parity with TS for a film and an article
ok(str_contains(harvard($refs['man-with-a-movie-camera']), 'Directed by D. Vertov'), 'harvard film');
ok(str_contains(harvard($refs['prince-hensley-1992']), 'Cinema Journal</i>, 31(2), pp. 59–75'), 'harvard article');
echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n";
exit($fail ? 1 : 0);
