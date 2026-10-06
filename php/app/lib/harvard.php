<?php
declare(strict_types=1);

// Harvard (Cite Them Right) formatting. Port of src/lib/harvard.ts; returns HTML.

function hv_esc(string $s): string { return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s); }

function hv_names(array $list): string
{
    $n = count($list);
    if ($n === 0) return '';
    if ($n === 1) return hv_esc($list[0]);
    if ($n > 3) return hv_esc($list[0]) . ' <i>et al.</i>';
    return implode(', ', array_map('hv_esc', array_slice($list, 0, -1))) . ' and ' . hv_esc($list[$n - 1]);
}

// "Vertov, D." -> "D. Vertov"
function hv_forename(string $n): string
{
    $parts = array_map('trim', explode(',', $n, 2));
    return isset($parts[1]) && $parts[1] !== '' ? $parts[1] . ' ' . $parts[0] : $n;
}

function harvard(array $r): string
{
    $authors = (array)($r['authors'] ?? []);
    $who = hv_names($authors);
    $yr = '(' . hv_esc((string)($r['year'] ?? '')) . ')';
    $title = hv_esc((string)($r['title'] ?? ''));
    $container = hv_esc((string)($r['container'] ?? ''));
    $pub = implode(': ', array_map('hv_esc', array_filter([(string)($r['place'] ?? ''), (string)($r['publisher'] ?? '')])));
    if (!empty($r['doi'])) $link = ' doi:' . hv_esc((string)$r['doi']) . '.';
    elseif (!empty($r['url'])) $link = ' Available at: ' . hv_esc((string)$r['url']) . (!empty($r['accessed']) ? ' (Accessed: ' . hv_esc((string)$r['accessed']) . ')' : '') . '.';
    else $link = '';
    $pages = !empty($r['pages']) ? ', pp. ' . hv_esc((string)$r['pages']) : '';

    switch ($r['kind'] ?? '') {
        case 'book':
            return "$who $yr <i>$title</i>." . ($pub ? " $pub." : '') . $link;
        case 'chapter':
            $eds = '';
            if (!empty($r['editors'])) $eds = hv_names((array)$r['editors']) . ' (ed' . (count($r['editors']) > 1 ? 's' : '') . '.) ';
            return "$who $yr '$title', in $eds<i>$container</i>." . ($pub ? " $pub" : '') . "$pages.$link";
        case 'article':
            $vol = !empty($r['volume']) ? ', ' . hv_esc((string)$r['volume']) . (!empty($r['issue']) ? '(' . hv_esc((string)$r['issue']) . ')' : '') : '';
            return "$who $yr '$title', <i>$container</i>$vol$pages.$link";
        case 'film':
            $medium = hv_esc((string)($r['medium'] ?? 'Film'));
            return "<i>$title</i> $yr Directed by " . hv_names(array_map('hv_forename', $authors)) . ". [$medium]"
                . (!empty($r['country']) ? ' ' . hv_esc((string)$r['country']) : '')
                . (!empty($r['publisher']) ? ': ' . hv_esc((string)$r['publisher']) : '') . ".$link";
        case 'artwork':
            $medium = hv_esc((string)($r['medium'] ?? 'Artwork'));
            return "$who $yr <i>$title</i> [$medium]." . (!empty($r['container']) ? " $container." : '') . $link;
        default: // web
            return ($who !== '' ? $who : "<i>$container</i>") . " $yr <i>$title</i>.$link";
    }
}

function harvard_sort_key(array $r): string
{
    return strtolower((((array)($r['authors'] ?? []))[0] ?? ($r['title'] ?? '')) . ' ' . ($r['year'] ?? ''));
}
