<?php

/**
 * Install recovered readable PHP over protected copies.
 * Relies on Git history for rollback; does not leave .protected files.
 *
 * Usage:
 *   php tools/deobfuscate/install_recovered.php
 *   php tools/deobfuscate/install_recovered.php status
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$recovered = $root . '/deobfuscated/recovered';

$map = [
    'core.func.php' => 'includes/core.func.php',
    'ajax.func.php' => 'includes/ajax.func.php',
    'common.php' => 'includes/common.php',
    'admin/account.php' => 'admin/account.php',
    'admin/article.php' => 'admin/article.php',
    'admin/classlist.php' => 'admin/classlist.php',
    'admin/clone.php' => 'admin/clone.php',
    'admin/fakalist.php' => 'admin/fakalist.php',
    'admin/invite.php' => 'admin/invite.php',
    'admin/orderjk.php' => 'admin/orderjk.php',
    'admin/pricejk.php' => 'admin/pricejk.php',
    'admin/set.php' => 'admin/set.php',
    'admin/shequlist.php' => 'admin/shequlist.php',
    'admin/shopedit.php' => 'admin/shopedit.php',
    'admin/shoplist.php' => 'admin/shoplist.php',
    'admin/sitelist.php' => 'admin/sitelist.php',
    'admin/userlist.php' => 'admin/userlist.php',
];

$mode = isset($argv[1]) ? strtolower($argv[1]) : 'install';

$obfuscatedHint = static function (string $src): bool {
    return (bool) preg_match('/\bgoto\s+/', $src)
        || (strpos($src, 'base64_decode') !== false && strpos($src, 'eval(') !== false && strlen($src) > 20000);
};

if ($mode === 'status') {
    foreach ($map as $from => $to) {
        $path = $root . '/' . $to;
        $src = is_file($path) ? file_get_contents($path) : '';
        $state = $src === '' ? 'missing' : ($obfuscatedHint($src) ? 'protected' : 'readable');
        echo str_pad($to, 32) . $state . "\n";
    }
    exit(0);
}

foreach ($map as $from => $to) {
    $srcPath = $recovered . '/' . $from;
    $destPath = $root . '/' . $to;
    if (!is_file($srcPath)) {
        fwrite(STDERR, "Missing recovered file: {$srcPath}\n");
        exit(1);
    }
    $src = file_get_contents($srcPath);
    if ($from === 'ajax.func.php' && strpos($src, "require __DIR__") !== false) {
        fwrite(STDERR, "ajax.func.php is not assembled. Run assemble_ajax_func.php first.\n");
        exit(1);
    }
    $dir = dirname($destPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (file_put_contents($destPath, $src) === false) {
        fwrite(STDERR, "Failed to write {$destPath}\n");
        exit(1);
    }
    echo "Installed {$to}\n";
}

foreach (['includes/core.func.protected.php', 'includes/ajax.func.protected.php', 'includes/.core-recovered', 'includes/.ajax-recovered'] as $leftover) {
    $path = $root . '/' . $leftover;
    if (is_file($path)) {
        unlink($path);
        echo "Removed leftover {$leftover}\n";
    }
}

echo "Recovered readable sources are now the live copies in this tree.\n";
