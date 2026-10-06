<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$skip = [
    '/deobfuscated/',
    '/tools/deobfuscate/',
    '/includes/lib/mail/PHPMailer/',
    '/vendor/',
];

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    $rel = substr($path, strlen(str_replace('\\', '/', $root)) + 1);
    $skipHit = false;
    foreach ($skip as $part) {
        if (strpos('/' . $rel, $part) !== false) {
            $skipHit = true;
            break;
        }
    }
    if ($skipHit) {
        continue;
    }
    $src = file_get_contents($path);
    if ($src === false) {
        continue;
    }
    $goto = preg_match_all('/\bgoto\s+/i', $src);
    $eval = preg_match_all('/\beval\s*\(/i', $src);
    $ccc = (int) (strpos($src, 'define("CCC') !== false || strpos($src, 'define("A_A__') !== false);
    $nested = (int) (strpos($src, 'base64_decode') !== false && strpos($src, 'eval(') !== false && strlen($src) > 20000);
    if ($goto || $ccc || $nested) {
        $files[] = [
            'file' => $rel,
            'goto' => $goto,
            'eval' => $eval,
            'marker' => $ccc ? 'hex-goto' : ($nested ? 'nested-eval' : ''),
            'bytes' => strlen($src),
        ];
    }
}

usort($files, static function ($a, $b) {
    return $b['goto'] <=> $a['goto'] ?: strcmp($a['file'], $b['file']);
});

echo "Live obfuscated PHP files (excluding recovered copies and decoder tools):\n";
foreach ($files as $row) {
    echo sprintf(
        "%6d goto  %6d eval  %-12s %s\n",
        $row['goto'],
        $row['eval'],
        $row['marker'],
        $row['file']
    );
}
echo 'count=' . count($files) . "\n";
