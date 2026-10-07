<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php extract_literal_payloads.php <source.php> <output-dir>\n");
    exit(2);
}

$sourcePath = $argv[1];
$outputDir = $argv[2];
$source = file_get_contents($sourcePath);
if ($source === false) {
    fwrite(STDERR, "Unable to read {$sourcePath}\n");
    exit(1);
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Unable to create {$outputDir}\n");
    exit(1);
}

// This intentionally handles only literal payloads. It never evaluates decoded text.
$pattern = <<<'REGEX'
~eval\s*\(\s*base64_decode\s*\(\s*(['"])([A-Za-z0-9+/=\r\n]+)\1\s*\)\s*\)\s*;~i
REGEX;

preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE);
$manifest = [];

foreach ($matches[2] as $index => $match) {
    [$encoded, $offset] = $match;
    $decoded = base64_decode(preg_replace('/\s+/', '', $encoded), true);
    if ($decoded === false) {
        continue;
    }

    $number = $index + 1;
    $name = sprintf('literal-%02d.phpfrag', $number);
    file_put_contents($outputDir . DIRECTORY_SEPARATOR . $name, $decoded);
    $manifest[] = [
        'file' => $name,
        'source_offset' => $offset,
        'encoded_bytes' => strlen($encoded),
        'decoded_bytes' => strlen($decoded),
        'sha256' => hash('sha256', $decoded),
    ];
}

file_put_contents(
    $outputDir . DIRECTORY_SEPARATOR . 'manifest.json',
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
);

printf("Extracted %d literal payload(s) without executing them.\n", count($manifest));

