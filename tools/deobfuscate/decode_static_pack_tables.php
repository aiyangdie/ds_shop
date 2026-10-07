<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php decode_static_pack_tables.php <source.php> <output.php>\n");
    exit(2);
}

$source = file_get_contents($argv[1]);
if ($source === false) {
    fwrite(STDERR, "Unable to read {$argv[1]}\n");
    exit(1);
}

$tables = [];
$tablePattern = <<<'REGEX'
~if\s*\(\s*!defined\(\s*"([A-Za-z_][A-Za-z0-9_]*)"\s*\)\s*\)\s*define\(\s*"\1"\s*,\s*"([A-Za-z_][A-Za-z0-9_]*)"\s*\)\s*;\s*\$GLOBALS\[\1\]\s*=\s*explode\(\s*"([^"]+)"\s*,\s*"([^"]*)"\s*\)\s*;~
REGEX;

preg_match_all($tablePattern, $source, $matches, PREG_SET_ORDER);
foreach ($matches as $match) {
    $tables[$match[1]] = explode($match[3], $match[4]);
}

$decoded = 0;
$packPattern = <<<'REGEX'
~pack\(\s*\$GLOBALS\[([A-Za-z_][A-Za-z0-9_]*)\]\[((?:0x[0-9A-Fa-f]+)|(?:0[0-7]*)|(?:[1-9][0-9]*))\]\s*,\s*\$GLOBALS\[\1\]\[((?:0x[0-9A-Fa-f]+)|(?:0[0-7]*)|(?:[1-9][0-9]*))\]\s*\)~
REGEX;

$source = preg_replace_callback(
    $packPattern,
    static function (array $match) use (&$tables, &$decoded): string {
        if (!isset($tables[$match[1]])) {
            return $match[0];
        }

        $formatIndex = intval($match[2], 0);
        $valueIndex = intval($match[3], 0);
        $table = $tables[$match[1]];
        if (!isset($table[$formatIndex], $table[$valueIndex]) || $table[$formatIndex] !== 'H*') {
            return $match[0];
        }

        $value = @pack('H*', $table[$valueIndex]);
        if ($value === false) {
            return $match[0];
        }

        $decoded++;
        return var_export($value, true);
    },
    $source
);

if (file_put_contents($argv[2], $source) === false) {
    fwrite(STDERR, "Unable to write {$argv[2]}\n");
    exit(1);
}

printf("Found %d static table(s); decoded %d direct pack expression(s).\n", count($tables), $decoded);

