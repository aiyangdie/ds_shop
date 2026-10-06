<?php

/**
 * Compare decoded goto string tables against recovered PHP copies.
 *
 * Usage:
 *   php tools/deobfuscate/coverage_goto_recovery.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$map = [
    'admin/set.php' => 'deobfuscated/recovered/admin/set.php',
    'admin/shopedit.php' => 'deobfuscated/recovered/admin/shopedit.php',
    'admin/clone.php' => 'deobfuscated/recovered/admin/clone.php',
    'admin/sitelist.php' => 'deobfuscated/recovered/admin/sitelist.php',
    'admin/shequlist.php' => 'deobfuscated/recovered/admin/shequlist.php',
    'admin/account.php' => 'deobfuscated/recovered/admin/account.php',
    'admin/article.php' => 'deobfuscated/recovered/admin/article.php',
    'admin/invite.php' => 'deobfuscated/recovered/admin/invite.php',
    'admin/fakalist.php' => 'deobfuscated/recovered/admin/fakalist.php',
    'admin/userlist.php' => 'deobfuscated/recovered/admin/userlist.php',
    'admin/pricejk.php' => 'deobfuscated/recovered/admin/pricejk.php',
    'admin/shoplist.php' => 'deobfuscated/recovered/admin/shoplist.php',
    'admin/orderjk.php' => 'deobfuscated/recovered/admin/orderjk.php',
    'admin/classlist.php' => 'deobfuscated/recovered/admin/classlist.php',
];

$decodeTable = static function (string $delimiter, string $payload): array {
    $parts = explode($delimiter, $payload);
    $decoded = [];
    foreach ($parts as $i => $part) {
        if ($part === 'H*') {
            continue;
        }
        $bin = @pack('H*', $part);
        $decoded[$i] = ($bin !== false && $bin !== '') ? $bin : $part;
    }
    return $decoded;
};

foreach ($map as $liveRel => $recRel) {
    $live = file_get_contents($root . '/' . $liveRel);
    $rec = file_get_contents($root . '/' . $recRel);
    $tables = [];
    if (preg_match_all(
        '/\$GLOBALS\[[A-Za-z0-9_]+\]\s*=\s*explode\(\s*"([^"]+)"\s*,\s*"([^"]*)"\s*\)\s*;/',
        $live,
        $matches,
        PREG_SET_ORDER
    )) {
        foreach ($matches as $match) {
            $tables[] = $decodeTable($match[1], $match[2]);
        }
    }
    if (preg_match_all(
        '/call_user_func_array\(\s*"explode"\s*,\s*array\(\s*"([^"]+)"\s*,\s*"([^"]*)"\s*\)\s*\)/',
        $live,
        $matches,
        PREG_SET_ORDER
    )) {
        foreach ($matches as $match) {
            $tables[] = $decodeTable($match[1], $match[2]);
        }
    }

    $missing = [];
    foreach ($tables as $entries) {
        foreach ($entries as $value) {
            $value = (string) $value;
            if (strlen($value) < 8) {
                continue;
            }
            if (preg_match('/^[A-Za-z0-9_\\\\]+$/', $value)) {
                continue;
            }
            if (strpos($rec, $value) === false) {
                $one = str_replace(["\r", "\n"], ['\\r', '\\n'], $value);
                if (strlen($one) > 140) {
                    $one = substr($one, 0, 137) . '...';
                }
                $missing[$one] = true;
            }
        }
    }
    $n = count($missing);
    echo $liveRel . ' missing_unique=' . $n . "\n";
    $i = 0;
    foreach (array_keys($missing) as $line) {
        echo '  - ' . $line . "\n";
        if (++$i >= 12) {
            if ($n > 12) {
                echo '  ... +' . ($n - 12) . " more\n";
            }
            break;
        }
    }
}
