<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php expand_tokenized_literals.php <source.php> <output.php>\n");
    exit(2);
}

$source = file_get_contents($argv[1]);
if ($source === false) {
    fwrite(STDERR, "Unable to read {$argv[1]}\n");
    exit(1);
}

function decodePhpStringLiteral(string $literal): string
{
    $quote = $literal[0];
    $body = substr($literal, 1, -1);
    if ($quote === "'") {
        return str_replace(["\\\\", "\\'"], ["\\", "'"], $body);
    }

    // Protected sources sampled so far use single-quoted payloads. Supporting
    // only PHP's simple double-quoted escapes avoids evaluating source text.
    return stripcslashes($body);
}

$rawTokens = token_get_all($source);
$tokens = [];
$offset = 0;
foreach ($rawTokens as $token) {
    $text = is_array($token) ? $token[1] : $token;
    $tokens[] = [
        'id' => is_array($token) ? $token[0] : null,
        'text' => $text,
        'start' => $offset,
        'end' => $offset + strlen($text),
    ];
    $offset += strlen($text);
}

$skip = static function (int $index) use ($tokens): int {
    $count = count($tokens);
    while ($index < $count && in_array($tokens[$index]['id'], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
        $index++;
    }
    return $index;
};

$replacements = [];
$stats = ['base64_decode' => 0, 'str_rot13' => 0];
$count = count($tokens);
for ($i = 0; $i < $count; $i++) {
    if ($tokens[$i]['id'] !== T_EVAL) {
        continue;
    }

    $j = $skip($i + 1);
    if (($tokens[$j]['text'] ?? null) !== '(') {
        continue;
    }
    $j = $skip($j + 1);
    $function = strtolower($tokens[$j]['text'] ?? '');
    if ($tokens[$j]['id'] !== T_STRING || !isset($stats[$function])) {
        continue;
    }
    $j = $skip($j + 1);
    if (($tokens[$j]['text'] ?? null) !== '(') {
        continue;
    }
    $j = $skip($j + 1);
    if (($tokens[$j]['id'] ?? null) !== T_CONSTANT_ENCAPSED_STRING) {
        continue;
    }
    $literal = decodePhpStringLiteral($tokens[$j]['text']);
    $j = $skip($j + 1);
    if (($tokens[$j]['text'] ?? null) !== ')') {
        continue;
    }
    $j = $skip($j + 1);
    if (($tokens[$j]['text'] ?? null) !== ')') {
        continue;
    }
    $j = $skip($j + 1);
    if (($tokens[$j]['text'] ?? null) !== ';') {
        continue;
    }

    $decoded = $function === 'base64_decode'
        ? base64_decode(preg_replace('/\s+/', '', $literal), true)
        : str_rot13($literal);
    if ($decoded === false) {
        continue;
    }

    $stats[$function]++;
    $replacements[] = [
        'start' => $tokens[$i]['start'],
        'end' => $tokens[$j]['end'],
        'text' => sprintf("/* expanded literal %s payload */\n%s\n", $function, $decoded),
    ];
}

usort($replacements, static function (array $a, array $b): int {
    return $b['start'] <=> $a['start'];
});
foreach ($replacements as $replacement) {
    $source = substr($source, 0, $replacement['start'])
        . $replacement['text']
        . substr($source, $replacement['end']);
}

if (file_put_contents($argv[2], $source) === false) {
    fwrite(STDERR, "Unable to write {$argv[2]}\n");
    exit(1);
}

printf(
    "Expanded %d Base64 and %d ROT13 literal payload(s) without evaluation.\n",
    $stats['base64_decode'],
    $stats['str_rot13']
);

