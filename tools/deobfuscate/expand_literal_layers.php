<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php expand_literal_layers.php <source.php> <output.php>\n");
    exit(2);
}

$source = file_get_contents($argv[1]);
if ($source === false) {
    fwrite(STDERR, "Unable to read {$argv[1]}\n");
    exit(1);
}

$stats = ['base64' => 0];

$source = preg_replace_callback(
    <<<'REGEX'
~eval\s*\(\s*base64_decode\s*\(\s*(['"])([A-Za-z0-9+/=\r\n]+)\1\s*\)\s*\)\s*;~i
REGEX,
    static function (array $match) use (&$stats): string {
        $decoded = base64_decode(preg_replace('/\s+/', '', $match[2]), true);
        if ($decoded === false) {
            return $match[0];
        }
        $stats['base64']++;
        return "/* expanded literal base64 payload */\n" . $decoded . "\n";
    },
    $source
);

if (file_put_contents($argv[2], $source) === false) {
    fwrite(STDERR, "Unable to write {$argv[2]}\n");
    exit(1);
}

printf(
    "Expanded %d base64 literal layer(s).\n",
    $stats['base64']
);

