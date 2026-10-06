<?php

/**
 * Syntax-check PHP files with the current interpreter (use PHP 7.4).
 *
 * Usage:
 *   C:\tools\php74\php.exe tools/deobfuscate/lint_php74.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$skipNames = [
    '/deobfuscated/stages/',
    '/deobfuscated/dumps/',
    '/vendor/',
    '/.git/',
    '/tools/appbuild/gradle-',
];

$php = PHP_BINARY;
$errors = 0;
$checked = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    if (strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    $rel = substr($path, strlen(str_replace('\\', '/', $root)));
    $skip = false;
    foreach ($skipNames as $needle) {
        if (strpos($rel, $needle) !== false) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }
    $checked++;
    $cmd = escapeshellarg($php) . ' -l ' . escapeshellarg($file->getPathname());
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    if ($code !== 0) {
        $errors++;
        echo implode("\n", $out) . "\n";
    }
}

echo "Checked {$checked} PHP files, errors={$errors}\n";
exit($errors > 0 ? 1 : 0);
