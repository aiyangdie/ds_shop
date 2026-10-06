<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'admin/set.php' => 'deobfuscated/recovered/admin/set.php',
    'admin/shopedit.php' => 'deobfuscated/recovered/admin/shopedit.php',
    'admin/fakalist.php' => 'deobfuscated/recovered/admin/fakalist.php',
    'admin/pricejk.php' => 'deobfuscated/recovered/admin/pricejk.php',
    'admin/account.php' => 'deobfuscated/recovered/admin/account.php',
    'admin/classlist.php' => 'deobfuscated/recovered/admin/classlist.php',
];

$decodeTable = static function (string $delimiter, string $payload): array {
    $out = [];
    foreach (explode($delimiter, $payload) as $i => $part) {
        if ($part === 'H*') {
            continue;
        }
        $bin = @pack('H*', $part);
        $out[] = ($bin !== false && $bin !== '') ? $bin : $part;
    }
    return $out;
};

foreach ($files as $liveRel => $recRel) {
    $live = file_get_contents($root . '/' . $liveRel);
    $rec = file_get_contents($root . '/' . $recRel);
    $tables = [];
    if (preg_match_all('/explode\(\s*"([^"]+)"\s*,\s*"([^"]*)"\s*\)/', $live, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $tables[] = $decodeTable($match[1], $match[2]);
        }
    }
    echo "==== {$liveRel} ====\n";
    $shown = 0;
    foreach ($tables as $entries) {
        foreach ($entries as $value) {
            $value = trim(str_replace(["\r", "\n"], ' ', (string) $value));
            if (strlen($value) < 18) {
                continue;
            }
            if (preg_match('/^[A-Za-z0-9_\\\\.|;*\\/=<> -]+$/', $value)) {
                continue;
            }
            if (!preg_match('/SELECT |INSERT |UPDATE |DELETE |showmsg|confirm\(|layer\.|function |adminpermission|ajax_|\.php\?|pre_[a-z]+/i', $value)
                && !preg_match('/[\x{4e00}-\x{9fff}].{8,}/u', $value)) {
                continue;
            }
            $norm = preg_replace('/\s+/', ' ', $value);
            $short = mb_substr($norm, 0, 40);
            if (strpos($rec, $short) !== false || strpos(preg_replace('/\s+/', '', $rec), preg_replace('/\s+/', '', $short)) !== false) {
                continue;
            }
            // collapse whitespace search
            $compactRec = preg_replace('/\s+/', '', $rec);
            $compactVal = preg_replace('/\s+/', '', $norm);
            if (strlen($compactVal) >= 12 && strpos($compactRec, mb_substr($compactVal, 0, 24)) !== false) {
                continue;
            }
            echo '  ? ' . mb_substr($norm, 0, 160) . "\n";
            if (++$shown >= 15) {
                break 2;
            }
        }
    }
    if ($shown === 0) {
        echo "  (no unmatched SQL/UI business strings after whitespace normalize)\n";
    }
}
