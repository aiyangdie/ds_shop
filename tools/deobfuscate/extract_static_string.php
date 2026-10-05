<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php extract_static_string.php <dump.bin> <output.bin>\n");
    exit(2);
}

$raw = file_get_contents($argv[1]);
$data = $raw === false ? false : unserialize($raw, ['allowed_classes' => false]);
if (!is_array($data) || count($data) !== 1 || !is_string(reset($data))) {
    fwrite(STDERR, "Unexpected static dump structure\n");
    exit(1);
}

$payload = reset($data);
if (file_put_contents($argv[2], $payload) === false) {
    fwrite(STDERR, "Unable to write {$argv[2]}\n");
    exit(1);
}

printf("Extracted %d payload bytes.\n", strlen($payload));

