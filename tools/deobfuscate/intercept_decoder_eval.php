<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php intercept_decoder_eval.php <source.php> <output.php>\n");
    exit(2);
}

$source = file_get_contents($argv[1]);
if ($source === false) {
    fwrite(STDERR, "Unable to read {$argv[1]}\n");
    exit(1);
}

// The decoder's key-13 branch evaluates its next decrypted layer. Replacing
// only the four-byte language construct with a four-byte function name keeps
// every subsequent byte at its original offset.
$pattern = '/(==13\s*\))eval(?=\s*\()/';
$instrumented = preg_replace($pattern, '$1capt', $source, 1, $count);
if ($count !== 1 || $instrumented === null) {
    fwrite(STDERR, "Expected exactly one decoder eval site; found {$count}\n");
    exit(1);
}
if (strlen($instrumented) !== strlen($source)) {
    fwrite(STDERR, "Instrumentation changed the file size\n");
    exit(1);
}

if (file_put_contents($argv[2], $instrumented) === false) {
    fwrite(STDERR, "Unable to write {$argv[2]}\n");
    exit(1);
}

printf("Intercepted one decoder eval without changing the %d-byte file size.\n", strlen($source));

