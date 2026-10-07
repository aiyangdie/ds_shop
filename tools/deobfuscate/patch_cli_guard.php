<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php patch_cli_guard.php <source.php> <output.php>\n");
    exit(2);
}

$source = file_get_contents($argv[1]);
if ($source === false) {
    fwrite(STDERR, "Unable to read {$argv[1]}\n");
    exit(1);
}

$pattern = <<<'REGEX'
~eval\s*\(\s*base64_decode\s*\(\s*(['"])([A-Za-z0-9+/=\r\n]+)\1\s*\)\s*\)\s*;~i
REGEX;

$patchedCount = 0;
$patched = preg_replace_callback(
    $pattern,
    static function (array $match) use (&$patchedCount): string {
        $compact = preg_replace('/\s+/', '', $match[2]);
        $decoded = base64_decode($compact, true);
        if ($decoded === false || strpos($decoded, "php_sapi_name()=='cli'") === false) {
            return $match[0];
        }

        $replacement = str_replace("php_sapi_name()=='cli'", "php_sapi_name()=='dbg'", $decoded, $count);
        if ($count !== 1 || strlen($replacement) !== strlen($decoded)) {
            return $match[0];
        }

        $encoded = base64_encode($replacement);
        if (strlen($encoded) !== strlen($compact)) {
            return $match[0];
        }

        $patchedCount++;
        return str_replace($match[2], $encoded, $match[0]);
    },
    $source
);

if ($patched === null || $patchedCount !== 1) {
    fwrite(STDERR, "Expected one CLI guard payload; patched {$patchedCount}\n");
    exit(1);
}
if (strlen($patched) !== strlen($source)) {
    fwrite(STDERR, "Patch changed the source size\n");
    exit(1);
}
if (file_put_contents($argv[2], $patched) === false) {
    fwrite(STDERR, "Unable to write {$argv[2]}\n");
    exit(1);
}

printf("Patched one CLI guard without changing the %d-byte file size.\n", strlen($source));

