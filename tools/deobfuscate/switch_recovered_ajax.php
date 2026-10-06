<?php

/**
 * Safely swap includes/ajax.func.php with the recovered readable helpers.
 *
 * Usage:
 *   php tools/deobfuscate/switch_recovered_ajax.php on
 *   php tools/deobfuscate/switch_recovered_ajax.php off
 *   php tools/deobfuscate/switch_recovered_ajax.php status
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$live = $root . '/includes/ajax.func.php';
$backup = $root . '/includes/ajax.func.protected.php';
$recovered = $root . '/deobfuscated/recovered/ajax.func.php';
$marker = $root . '/includes/.ajax-recovered';

$mode = isset($argv[1]) ? strtolower($argv[1]) : 'status';

if (!is_file($recovered)) {
    fwrite(STDERR, "Recovered ajax.func.php missing.\n");
    exit(1);
}

if ($mode === 'status') {
    $using = is_file($marker) ? 'recovered' : 'protected';
    echo "ajax.func.php mode: {$using}\n";
    echo "live: {$live}\n";
    echo "backup: " . (is_file($backup) ? $backup : '(none)') . "\n";
    exit(0);
}

if ($mode === 'on') {
    if (is_file($marker)) {
        echo "Already using recovered ajax.func.php.\n";
        exit(0);
    }
    if (!is_file($backup)) {
        if (!copy($live, $backup)) {
            fwrite(STDERR, "Failed to backup protected ajax.func.php.\n");
            exit(1);
        }
        echo "Backed up protected ajax.func.php to includes/ajax.func.protected.php\n";
    }
    if (!copy($recovered, $live)) {
        fwrite(STDERR, "Failed to install recovered ajax.func.php.\n");
        exit(1);
    }
    file_put_contents($marker, date('c') . "\n");
    echo "Installed recovered ajax.func.php into includes/ajax.func.php\n";
    exit(0);
}

if ($mode === 'off') {
    if (!is_file($backup)) {
        fwrite(STDERR, "No protected backup found; cannot restore.\n");
        exit(1);
    }
    if (!copy($backup, $live)) {
        fwrite(STDERR, "Failed to restore protected ajax.func.php.\n");
        exit(1);
    }
    if (is_file($marker)) {
        unlink($marker);
    }
    echo "Restored protected ajax.func.php into includes/ajax.func.php\n";
    exit(0);
}

fwrite(STDERR, "Usage: php switch_recovered_ajax.php [on|off|status]\n");
exit(2);
