<?php

/**
 * Safely swap includes/core.func.php with the recovered readable core.
 *
 * Usage:
 *   php tools/deobfuscate/switch_recovered_core.php on
 *   php tools/deobfuscate/switch_recovered_core.php off
 *   php tools/deobfuscate/switch_recovered_core.php status
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$live = $root . '/includes/core.func.php';
$backup = $root . '/includes/core.func.protected.php';
$recovered = $root . '/deobfuscated/recovered/core.func.php';
$marker = $root . '/includes/.core-recovered';

$mode = isset($argv[1]) ? strtolower($argv[1]) : 'status';

if (!is_file($recovered)) {
    fwrite(STDERR, "Recovered core missing. Run assemble_core_func.php first.\n");
    exit(1);
}

if ($mode === 'status') {
    $using = is_file($marker) ? 'recovered' : 'protected';
    echo "core.func.php mode: {$using}\n";
    echo "live: {$live}\n";
    echo "backup: " . (is_file($backup) ? $backup : '(none)') . "\n";
    exit(0);
}

if ($mode === 'on') {
    if (is_file($marker)) {
        echo "Already using recovered core.\n";
        exit(0);
    }
    if (!is_file($backup)) {
        if (!copy($live, $backup)) {
            fwrite(STDERR, "Failed to backup protected core.\n");
            exit(1);
        }
        echo "Backed up protected core to includes/core.func.protected.php\n";
    }
    if (!copy($recovered, $live)) {
        fwrite(STDERR, "Failed to install recovered core.\n");
        exit(1);
    }
    file_put_contents($marker, date('c') . "\n");
    echo "Installed recovered core into includes/core.func.php\n";
    exit(0);
}

if ($mode === 'off') {
    if (!is_file($backup)) {
        fwrite(STDERR, "No protected backup found; cannot restore.\n");
        exit(1);
    }
    if (!copy($backup, $live)) {
        fwrite(STDERR, "Failed to restore protected core.\n");
        exit(1);
    }
    if (is_file($marker)) {
        unlink($marker);
    }
    echo "Restored protected core into includes/core.func.php\n";
    exit(0);
}

fwrite(STDERR, "Usage: php switch_recovered_core.php [on|off|status]\n");
exit(2);
