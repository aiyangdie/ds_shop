<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php split_dispatcher_payload.php <payload.bin> <output-dir>\n");
    exit(2);
}

$payload = file_get_contents($argv[1]);
if ($payload === false) {
    fwrite(STDERR, "Unable to read {$argv[1]}\n");
    exit(1);
}

$outputDir = $argv[2];
if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Unable to create {$outputDir}\n");
    exit(1);
}

$pattern = '/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*(\([^)]*\))\s*\{(.*?)\}/s';
preg_match_all($pattern, $payload, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
if (count($matches) !== 24) {
    fwrite(STDERR, sprintf("Expected 24 wrappers; found %d\n", count($matches)));
    exit(1);
}

$manifest = [];
foreach ($matches as $index => $match) {
    $start = $match[0][1];
    $end = isset($matches[$index + 1]) ? $matches[$index + 1][0][1] : strlen($payload);
    $segment = substr($payload, $start, $end - $start);
    $mode = $index + 1;
    $name = $match[1][0];
    $file = sprintf('%02d-%s.bin', $mode, $name);
    file_put_contents($outputDir . DIRECTORY_SEPARATOR . $file, $segment);
    $manifest[] = [
        'mode' => $mode,
        'name' => $name,
        'parameters' => $match[2][0],
        'offset' => $start,
        'bytes' => strlen($segment),
        'sha256' => hash('sha256', $segment),
        'file' => $file,
    ];
}

file_put_contents(
    $outputDir . DIRECTORY_SEPARATOR . 'manifest.json',
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
);

printf("Split dispatcher payload into %d function segment(s).\n", count($manifest));

