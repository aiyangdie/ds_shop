<?php

/**
 * Install or report status of recovered includes/ajax.func.php.
 * Rollback is Git history, not a leftover .protected file.
 *
 * Usage:
 *   php tools/deobfuscate/assemble_ajax_func.php
 *   php tools/deobfuscate/switch_recovered_ajax.php on
 *   php tools/deobfuscate/switch_recovered_ajax.php status
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$live = $root . '/includes/ajax.func.php';
$recovered = $root . '/deobfuscated/recovered/ajax.func.php';

$mode = isset($argv[1]) ? strtolower($argv[1]) : 'status';

$isReadable = static function (string $path): bool {
    if (!is_file($path)) {
        return false;
    }
    $src = file_get_contents($path);
    return strpos($src, 'function getDatePoint') !== false
        && strpos($src, "require __DIR__") === false
        && !preg_match('/\bgoto\s+/', $src);
};

if ($mode === 'status') {
    echo 'ajax.func.php mode: ' . ($isReadable($live) ? 'recovered' : 'unknown') . "\n";
    echo "live: {$live}\n";
    echo 'assembled recovered: ' . (is_file($recovered) ? $recovered : '(missing)') . "\n";
    exit($isReadable($live) ? 0 : 1);
}

if ($mode === 'on') {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/assemble_ajax_func.php'), $assembleCode);
    if ($assembleCode !== 0) {
        exit($assembleCode ?: 1);
    }
    if (!is_file($recovered)) {
        fwrite(STDERR, "Recovered ajax.func.php missing after assemble.\n");
        exit(1);
    }
    $src = file_get_contents($recovered);
    if (strpos($src, "require __DIR__") !== false) {
        fwrite(STDERR, "Assembled ajax.func.php still requires sibling files.\n");
        exit(1);
    }
    if (!copy($recovered, $live)) {
        fwrite(STDERR, "Failed to install recovered ajax.func.php.\n");
        exit(1);
    }
    foreach ([$root . '/includes/ajax.func.protected.php', $root . '/includes/.ajax-recovered'] as $leftover) {
        if (is_file($leftover)) {
            unlink($leftover);
        }
    }
    echo "Installed recovered ajax.func.php into includes/ajax.func.php\n";
    exit(0);
}

if ($mode === 'off') {
    fwrite(STDERR, "Protected copies are not kept in this tree. Restore with: git checkout -- includes/ajax.func.php\n");
    exit(2);
}

fwrite(STDERR, "Usage: php switch_recovered_ajax.php [on|status]\n");
exit(2);
