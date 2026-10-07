<?php

/**
 * Install or report status of recovered includes/core.func.php.
 * Rollback is Git history, not a leftover .protected file.
 *
 * Usage:
 *   php tools/deobfuscate/assemble_core_func.php
 *   php tools/deobfuscate/switch_recovered_core.php on
 *   php tools/deobfuscate/switch_recovered_core.php status
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$live = $root . '/includes/core.func.php';
$recovered = $root . '/deobfuscated/recovered/core.func.php';

$mode = isset($argv[1]) ? strtolower($argv[1]) : 'status';

$isReadable = static function (string $path): bool {
    if (!is_file($path)) {
        return false;
    }
    $src = file_get_contents($path);
    return strpos($src, 'function getSetting') !== false
        && strpos($src, 'function processOrder') !== false
        && !preg_match('/\bgoto\s+/', $src);
};

if ($mode === 'status') {
    echo 'core.func.php mode: ' . ($isReadable($live) ? 'recovered' : 'unknown') . "\n";
    echo "live: {$live}\n";
    echo 'assembled recovered: ' . (is_file($recovered) ? $recovered : '(missing)') . "\n";
    exit($isReadable($live) ? 0 : 1);
}

if ($mode === 'on') {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/assemble_core_func.php'), $assembleCode);
    if ($assembleCode !== 0) {
        exit($assembleCode ?: 1);
    }
    if (!copy($recovered, $live)) {
        fwrite(STDERR, "Failed to install recovered core.func.php.\n");
        exit(1);
    }
    foreach ([$root . '/includes/core.func.protected.php', $root . '/includes/.core-recovered'] as $leftover) {
        if (is_file($leftover)) {
            unlink($leftover);
        }
    }
    echo "Installed recovered core into includes/core.func.php\n";
    exit(0);
}

if ($mode === 'off') {
    fwrite(STDERR, "Protected copies are not kept in this tree. Restore with: git checkout -- includes/core.func.php\n");
    exit(2);
}

fwrite(STDERR, "Usage: php switch_recovered_core.php [on|status]\n");
exit(2);
