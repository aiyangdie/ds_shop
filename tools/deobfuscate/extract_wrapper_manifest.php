<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php extract_wrapper_manifest.php <payload.bin> <manifest.json>\n");
    exit(2);
}

$payload = file_get_contents($argv[1]);
if ($payload === false) {
    fwrite(STDERR, "Unable to read {$argv[1]}\n");
    exit(1);
}

function escapedBytes(string $value): string
{
    return preg_replace_callback('/[^\x20-\x7E]/', static function (array $match): string {
        return sprintf('\\x%02X', ord($match[0]));
    }, $value);
}

$pattern = '/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*(\([^)]*\))\s*\{(.*?)\}/s';
preg_match_all($pattern, $payload, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

$manifest = [];
foreach ($matches as $match) {
    $name = $match[1][0];
    $parameters = $match[2][0];
    $body = $match[3][0];
    $entry = [
        'name' => $name,
        'parameters' => $parameters,
        'payload_offset' => $match[0][1],
        'wrapper_bytes' => strlen($match[0][0]),
        'body_base64' => base64_encode($body),
        'body_escaped' => escapedBytes($body),
    ];

    if (preg_match('/return\s+(.+?)\(\$__xend_args,(.*?),(\d+)\);/s', $body, $dispatch)) {
        $entry['dispatcher_base64'] = base64_encode($dispatch[1]);
        $entry['dispatcher_escaped'] = escapedBytes($dispatch[1]);
        $entry['selector_base64'] = base64_encode($dispatch[2]);
        $entry['selector_escaped'] = escapedBytes($dispatch[2]);
        $entry['mode'] = (int) $dispatch[3];
    }

    $manifest[] = $entry;
}

if (file_put_contents(
    $argv[2],
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL
) === false) {
    fwrite(STDERR, "Unable to write {$argv[2]}\n");
    exit(1);
}

printf("Extracted %d wrapper definition(s).\n", count($manifest));

