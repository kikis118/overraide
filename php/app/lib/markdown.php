<?php
declare(strict_types=1);

// A small, dependency-free Markdown renderer covering what the weblog uses:
// headings (with anchor ids), paragraphs, emphasis, links, images, lists (nested),
// block quotes, fenced code, tables, rules and raw HTML (the author is the only writer).

final class Markdown
{
    private array $stash = [];
    private array $slugs = [];

    public function __construct(private string $base = '') {}

    public function render(string $text): string
    {
        $this->slugs = [];
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return $this->blocks(explode("\n", $text));
    }

    private const BLOCK_HTML = '/^\s*<\/?(?:div|figure|figcaption|iframe|video|audio|section|details|summary|table|p|ul|ol|pre|blockquote|h[1-6]|hr|center|style|script)\b/i';
    private const LIST_ITEM = '/^(\s*)([-*+]|\d{1,9}[.)])\s+(.*)$/';
    private const HR = '/^\s{0,3}([-*_])(?:\s*\1){2,}\s*$/';

    private function blocks(array $lines): string
    {
        $out = '';
        $n = count($lines);
        $i = 0;
        while ($i < $n) {
            $line = $lines[$i];
            if (trim($line) === '') { $i++; continue; }

            if (preg_match('/^\s*(`{3,}|~{3,})\s*([\w+-]*)\s*$/', $line, $m)) {
                $fence = $m[1];
                $i++;
                $code = [];
                $close = '/^\s*' . preg_quote($fence[0], '/') . '{' . strlen($fence) . ',}\s*$/';
                while ($i < $n && !preg_match($close, $lines[$i])) { $code[] = $lines[$i]; $i++; }
                $i++;
                $cls = $m[2] !== '' ? ' class="language-' . $this->e($m[2]) . '"' : '';
                $out .= '<pre><code' . $cls . '>' . $this->e(implode("\n", $code)) . "\n</code></pre>\n";
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.+?)(?:\s+#+)?\s*$/', $line, $m)) {
                $level = strlen($m[1]);
                $inner = $this->inline($m[2]);
                $out .= "<h$level id=\"" . $this->slug($m[2]) . "\">$inner</h$level>\n";
                $i++;
                continue;
            }
            if (preg_match(self::HR, $line)) { $out .= "<hr>\n"; $i++; continue; }
            if (preg_match('/^\s{0,3}>/', $line)) {
                $q = [];
                while ($i < $n && preg_match('/^\s{0,3}>\s?(.*)$/', $lines[$i], $m)) { $q[] = $m[1]; $i++; }
                $out .= "<blockquote>\n" . $this->blocks($q) . "</blockquote>\n";
                continue;
            }
            if (preg_match(self::BLOCK_HTML, $line)) {
                $h = [];
                while ($i < $n && trim($lines[$i]) !== '') { $h[] = $lines[$i]; $i++; }
                $out .= $this->urls(implode("\n", $h)) . "\n";
                continue;
            }
            if ($i + 1 < $n && str_contains($line, '|') && preg_match('/^\s*\|?\s*:?-{2,}:?\s*(?:\|\s*:?-{2,}:?\s*)*\|?\s*$/', $lines[$i + 1])) {
                $out .= $this->table($lines, $i);
                continue;
            }
            if (preg_match(self::LIST_ITEM, $line)) {
                $out .= $this->listBlock($lines, $i);
                continue;
            }
            // paragraph
            $p = [];
            while ($i < $n && trim($lines[$i]) !== '' && !$this->startsBlock($lines[$i], $p)) { $p[] = $lines[$i]; $i++; }
            if (!$p) { $p[] = $lines[$i]; $i++; }
            $out .= '<p>' . $this->inline(implode("\n", array_map('ltrim', $p))) . "</p>\n";
        }
        return $out;
    }

    private function startsBlock(string $line, array $para): bool
    {
        if (!$para) return false;
        return (bool)(preg_match('/^(#{1,6})\s+/', $line) || preg_match('/^\s*(`{3,}|~{3,})/', $line)
            || preg_match(self::HR, $line) || preg_match('/^\s{0,3}>/', $line) || preg_match(self::BLOCK_HTML, $line)
            || preg_match('/^\s*([-*+]|1[.)])\s+\S/', $line));
    }

    private function listBlock(array $lines, int &$i): string
    {
        $n = count($lines);
        preg_match(self::LIST_ITEM, $lines[$i], $first);
        $indent0 = strlen($first[1]);
        $ordered = ctype_digit($first[2][0]);
        $start = $ordered ? (int)$first[2] : 1;
        $items = [];
        $loose = false;
        $cur = null;
        while ($i < $n) {
            $line = $lines[$i];
            if (trim($line) === '') {
                $j = $i + 1;
                while ($j < $n && trim($lines[$j]) === '') $j++;
                if ($j >= $n) break;
                $nextIndent = strlen($lines[$j]) - strlen(ltrim($lines[$j]));
                $isItem = preg_match(self::LIST_ITEM, $lines[$j], $nm);
                if ($nextIndent > $indent0 || ($isItem && $nextIndent === $indent0 && ctype_digit($nm[2][0]) === $ordered)) {
                    if ($cur !== null) { $cur['lines'][] = ''; $items[count($items) - 1] = $cur; }
                    $loose = true;
                    $i++;
                    continue;
                }
                break;
            }
            $indent = strlen($line) - strlen(ltrim($line));
            if (preg_match(self::LIST_ITEM, $line, $m) && $indent <= $indent0 + 1 && $indent >= $indent0) {
                if (ctype_digit($m[2][0]) !== $ordered) break;
                $cur = ['lines' => [$m[3]], 'width' => $indent + strlen($m[2]) + 1];
                $items[] = $cur;
                $i++;
                continue;
            }
            if ($cur !== null && $indent > $indent0) {
                $cur['lines'][] = substr($line, min($cur['width'], $indent));
                $items[count($items) - 1] = $cur;
                $i++;
                continue;
            }
            if ($cur !== null && $indent <= $indent0 && !preg_match(self::LIST_ITEM, $line) && !$this->startsBlock($line, ['x'])) {
                $cur['lines'][] = ltrim($line);
                $items[count($items) - 1] = $cur;
                $i++;
                continue;
            }
            break;
        }
        $tag = $ordered ? 'ol' : 'ul';
        $startAttr = $ordered && $start !== 1 ? ' start="' . $start . '"' : '';
        $html = "<$tag$startAttr>\n";
        foreach ($items as $it) {
            $inner = $this->blocks($it['lines']);
            if (!$loose) $inner = preg_replace('/\A<p>(.*?)<\/p>\n/s', '$1' . "\n", $inner, 1);
            $html .= '<li>' . rtrim($inner, "\n") . "</li>\n";
        }
        return $html . "</$tag>\n";
    }

    private function table(array $lines, int &$i): string
    {
        $cells = fn(string $l) => array_map('trim', preg_split('/(?<!\\\\)\|/', trim(trim($l), '|')));
        $head = $cells($lines[$i]);
        $aligns = array_map(function ($c) {
            $l = str_starts_with($c, ':'); $r = str_ends_with($c, ':');
            return $l && $r ? 'center' : ($r ? 'right' : ($l ? 'left' : ''));
        }, $cells($lines[$i + 1]));
        $i += 2;
        $td = fn(string $tag, array $row) => implode('', array_map(
            fn($c, $k) => "<$tag" . (($aligns[$k] ?? '') ? ' style="text-align:' . $aligns[$k] . '"' : '') . '>' . $this->inline(str_replace('\|', '|', $c)) . "</$tag>",
            $row, array_keys($row)));
        $html = "<table>\n<thead><tr>" . $td('th', $head) . "</tr></thead>\n<tbody>\n";
        while ($i < count($lines) && trim($lines[$i]) !== '' && str_contains($lines[$i], '|')) {
            $html .= '<tr>' . $td('td', $cells($lines[$i])) . "</tr>\n";
            $i++;
        }
        return $html . "</tbody>\n</table>\n";
    }

    public function inline(string $s): string
    {
        $this->stash = [];
        $put = function (string $html): string { $this->stash[] = $html; return "\x01" . (count($this->stash) - 1) . "\x02"; };

        $s = preg_replace_callback('/(`+)(.+?)\1/s', fn($m) => $put('<code>' . $this->e(trim($m[2])) . '</code>'), $s);
        $s = preg_replace_callback('/\\\\([\\\\`*_{}\[\]()#+\-.!|~<>])/', fn($m) => $put($this->e($m[1])), $s);
        $s = preg_replace_callback('/!\[([^\]]*)\]\(\s*([^)\s]+)(?:\s+"([^"]*)")?\s*\)/', function ($m) use ($put) {
            $t = isset($m[3]) && $m[3] !== '' ? ' title="' . $this->e($m[3]) . '"' : '';
            return $put('<img src="' . $this->e($this->url($m[2])) . '" alt="' . $this->e($m[1]) . '"' . $t . '>');
        }, $s);
        $s = preg_replace_callback('/\[((?:[^\[\]]|\x01\d+\x02)+)\]\(\s*([^)\s]+)(?:\s+"([^"]*)")?\s*\)/', function ($m) use ($put) {
            $t = isset($m[3]) && $m[3] !== '' ? ' title="' . $this->e($m[3]) . '"' : '';
            return $put('<a href="' . $this->e($this->url($m[2])) . '"' . $t . '>' . $this->emphasis($this->escText($m[1])) . '</a>');
        }, $s);
        $s = preg_replace_callback('/<(https?:\/\/[^>\s]+)>/', fn($m) => $put('<a href="' . $this->e($m[1]) . '">' . $this->e($m[1]) . '</a>'), $s);
        $s = preg_replace_callback('/<\/?[a-zA-Z][^<>]*>/', fn($m) => $put($this->urls($m[0])), $s);
        $s = $this->emphasis($this->escText($s));
        $s = preg_replace('/(?: {2,}|\\\\)\n/', "<br>\n", $s);
        for ($k = 0; $k < 4 && str_contains($s, "\x01"); $k++) {
            $s = preg_replace_callback('/\x01(\d+)\x02/', fn($m) => $this->stash[(int)$m[1]], $s);
        }
        return $s;
    }

    private function escText(string $s): string { return htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8', false); }

    private function emphasis(string $s): string
    {
        $s = preg_replace('/\*\*\*(?=\S)(.+?)(?<=\S)\*\*\*/s', '<strong><em>$1</em></strong>', $s);
        $s = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/s', '<strong>$1</strong>', $s);
        $s = preg_replace('/(?<![\w*])\*(?=[^\s*])(.+?)(?<=[^\s*])\*(?![\w*])/s', '<em>$1</em>', $s);
        $s = preg_replace('/(?<!\w)__(?=\S)(.+?)(?<=\S)__(?!\w)/s', '<strong>$1</strong>', $s);
        $s = preg_replace('/(?<![\w])_(?=\S)(.+?)(?<=\S)_(?![\w])/s', '<em>$1</em>', $s);
        return preg_replace('/~~(?=\S)(.+?)(?<=\S)~~/s', '<del>$1</del>', $s);
    }

    private function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

    // sites served from a sub-path: /media/x.jpg becomes /<base>/media/x.jpg
    private function url(string $u): string
    {
        $u = html_entity_decode($u, ENT_QUOTES, 'UTF-8');
        if ($this->base !== '' && $u !== '' && $u[0] === '/' && !str_starts_with($u, '//') && !str_starts_with($u, $this->base . '/')) return $this->base . $u;
        return $u;
    }

    private function urls(string $html): string
    {
        return preg_replace_callback('/\b(src|href|poster)="([^"]*)"/', fn($m) => $m[1] . '="' . $this->e($this->url($m[2])) . '"', $html);
    }

    private function slug(string $text): string
    {
        $t = strtolower(trim(strip_tags($this->inline($text))));
        $t = preg_replace('/[^\p{L}\p{N}\s_-]/u', '', $t);
        $t = preg_replace('/\s/u', '-', $t);
        $base = $t !== '' ? $t : 'section';
        $slug = $base;
        $k = 0;
        while (isset($this->slugs[$slug])) $slug = $base . '-' . (++$k);
        $this->slugs[$slug] = true;
        return $slug;
    }
}
