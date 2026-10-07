<?php

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php inspect_serialized_static.php <dump.bin>\n");
    exit(2);
}

$raw = file_get_contents($argv[1]);
if ($raw === false) {
    fwrite(STDERR, "Unable to read {$argv[1]}\n");
    exit(1);
}

$data = unserialize($raw, ['allowed_classes' => false]);

function printablePreview(string $value): string
{
    $preview = substr($value, 0, 120);
    return preg_replace_callback('/[^\x20-\x7E]/', static function (array $match): string {
        return sprintf('\\x%02X', ord($match[0]));
    }, $preview);
}

function summarize($value, int $depth = 0)
{
    if (!is_array($value)) {
        if (is_string($value)) {
            return [
                'type' => 'string',
                'bytes' => strlen($value),
                'preview' => printablePreview($value),
                'sha256' => hash('sha256', $value),
            ];
        }
        return ['type' => gettype($value), 'value' => $value];
    }

    $summary = [
        'type' => 'array',
        'count' => count($value),
        'key_sample' => [],
        'value_sample' => [],
    ];
    $limit = 30;
    foreach ($value as $key => $item) {
        $summary['key_sample'][] = is_string($key)
            ? ['base64' => base64_encode($key), 'preview' => printablePreview($key)]
            : $key;
        if ($depth < 3) {
            $summary['value_sample'][] = summarize($item, $depth + 1);
        }
        if (--$limit === 0) {
            break;
        }
    }
    return $summary;
}

echo json_encode(
    summarize($data),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
) . PHP_EOL;

