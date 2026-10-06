<?php

/**
 * Run all tools/deobfuscate/test_*.php scripts.
 */

declare(strict_types=1);

$dir = __DIR__;
$php = PHP_BINARY;
$failed = 0;
$ran = 0;
$files = glob($dir . '/test_*.php');
sort($files);
foreach ($files as $file) {
    if (basename($file) === 'test_http_uninstalled.php') {
        continue;
    }
    $ran++;
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($file);
    passthru($cmd, $code);
    if ($code !== 0) {
        $failed++;
        fwrite(STDERR, "FAIL " . basename($file) . " exit={$code}\n");
    }
}
echo "Ran {$ran} tests, failures={$failed}\n";
exit($failed > 0 ? 1 : 0);
