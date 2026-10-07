<?php

/**
 * Decode hex string tables used by goto-flattened admin pages.
 *
 * Usage:
 *   php tools/deobfuscate/decode_goto_string_tables.php admin/classlist.php deobfuscated/stages/goto/classlist-strings.json
 */

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php decode_goto_string_tables.php <source.php> <output.json> [output.php]\n");
    exit(2);
}

$source = file_get_contents($argv[1]);
if ($source === false) {
    fwrite(STDERR, "Unable to read {$argv[1]}\n");
    exit(1);
}

$tables = [];

$decodeTable = static function (string $delimiter, string $payload): array {
    $parts = explode($delimiter, $payload);
    $decoded = [];
    foreach ($parts as $i => $part) {
        if ($part === 'H*') {
            $decoded[$i] = 'H*';
            continue;
        }
        $bin = @pack('H*', $part);
        $decoded[$i] = ($bin !== false && $bin !== '') ? $bin : $part;
    }
    return $decoded;
};

if (preg_match_all(
    '/\$GLOBALS\[([A-Za-z0-9_]+)\]\s*=\s*explode\(\s*"([^"]+)"\s*,\s*"([^"]*)"\s*\)\s*;/',
    $source,
    $matches,
    PREG_SET_ORDER
)) {
    foreach ($matches as $match) {
        $tables[$match[1]] = $decodeTable($match[2], $match[3]);
    }
}

// Some pages assign the string table via call_user_func_array("explode", array(...)).
if (preg_match_all(
    '/call_user_func_array\(\s*"explode"\s*,\s*array\(\s*"([^"]+)"\s*,\s*"([^"]*)"\s*\)\s*\)/',
    $source,
    $matches,
    PREG_SET_ORDER
)) {
    foreach ($matches as $index => $match) {
        $tables['explode_call_' . $index] = $decodeTable($match[1], $match[2]);
    }
}

// common.php builds explode args as $arr[]=delim; $arr[]=payload; call_user_func_array("explode", $arr).
if (preg_match_all(
    '/\$([A-Za-z0-9_]+)\[\]\s*=\s*"([^"]+)";\s*\$\1\[\]\s*=\s*"((?:H\*|[^"]*))";\s*\$[A-Za-z0-9_]+\s*=\s*call_user_func_array\(\s*"explode"\s*,\s*\$\1\s*\)/',
    $source,
    $matches,
    PREG_SET_ORDER
)) {
    foreach ($matches as $index => $match) {
        $tables['explode_push_' . $index] = $decodeTable($match[2], $match[3]);
    }
}

// Also resolve define("CCC...", "CCC...") aliases used as $GLOBALS keys.
$aliases = [];
if (preg_match_all(
    '/if\s*\(\s*!defined\(\s*"([^"]+)"\s*\)\s*\)\s*define\(\s*"\1"\s*,\s*"([^"]+)"\s*\)\s*;/',
    $source,
    $aliasMatches,
    PREG_SET_ORDER
)) {
    foreach ($aliasMatches as $match) {
        $aliases[$match[1]] = $match[2];
    }
}

$report = [
    'source' => $argv[1],
    'aliases' => $aliases,
    'tables' => $tables,
];

file_put_contents($argv[2], json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo 'Wrote ' . $argv[2] . ' with ' . count($tables) . " table(s)\n";

if ($argc >= 4) {
    $out = $source;
    foreach ($tables as $tableName => $entries) {
        // Prefer the largest non-H* table as the string table.
        $isStringTable = false;
        foreach ($entries as $entry) {
            if ($entry !== 'H*' && strlen((string) $entry) > 2) {
                $isStringTable = true;
                break;
            }
        }
        if (!$isStringTable) {
            continue;
        }

        // Replace pack(H*, hexliteral) already handled elsewhere; here emit a readable map comment.
        $comment = "\n/* decoded string table {$tableName}:\n";
        foreach ($entries as $i => $value) {
            if ($value === 'H*') {
                continue;
            }
            $comment .= sprintf("  [%d] %s\n", $i, str_replace(["\r", "\n"], ['\\r', '\\n'], (string) $value));
        }
        $comment .= "*/\n";
        $out = preg_replace('/^<\?php\s*/', "<?php\n" . $comment, $out, 1);
        break;
    }
    file_put_contents($argv[3], $out);
    echo 'Wrote annotated ' . $argv[3] . "\n";
}

// Print a compact readable dump for the main string table.
foreach ($tables as $name => $entries) {
    if (count($entries) < 5) {
        continue;
    }
    echo "Table {$name} (" . count($entries) . " entries):\n";
    foreach ($entries as $i => $value) {
        if ($value === 'H*') {
            continue;
        }
        $oneLine = str_replace(["\r", "\n"], ['\\r', '\\n'], (string) $value);
        if (strlen($oneLine) > 120) {
            $oneLine = substr($oneLine, 0, 117) . '...';
        }
        echo sprintf("  %3d  %s\n", $i, $oneLine);
    }
}
