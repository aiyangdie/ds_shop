<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php instrument_eval_payloads.php <source.php> <output.php>\n");
    exit(2);
}

$source = file_get_contents($argv[1]);
if ($source === false) {
    fwrite(STDERR, "Unable to read {$argv[1]}\n");
    exit(1);
}

$tokens = token_get_all($source);
$output = '';
$depth = 0;
$evalDepths = [];
$pendingEval = false;
$injected = false;
$evalCount = 0;

$captureHelper = <<<'PHP'

function deob_capture_eval_payload($code) {
    static $sequence = 0;
    $sequence++;
    $directory = __DIR__ . '/../deobfuscated/stages/runtime-eval';
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    $name = sprintf('eval-%04d-%s.phpfrag', $sequence, substr(hash('sha256', $code), 0, 16));
    file_put_contents($directory . '/' . $name, $code);
    return $code;
}

PHP;

foreach ($tokens as $token) {
    $id = is_array($token) ? $token[0] : null;
    $text = is_array($token) ? $token[1] : $token;

    if (!$injected && $id === T_OPEN_TAG) {
        $output .= $text . $captureHelper;
        $injected = true;
        continue;
    }

    if ($id === T_EVAL) {
        $pendingEval = true;
        $evalCount++;
        $output .= $text;
        continue;
    }

    if ($text === '(') {
        $depth++;
        $output .= $text;
        if ($pendingEval) {
            $output .= 'deob_capture_eval_payload(';
            $evalDepths[] = $depth;
            $pendingEval = false;
        }
        continue;
    }

    if ($text === ')') {
        if ($evalDepths && end($evalDepths) === $depth) {
            $output .= ')';
            array_pop($evalDepths);
        }
        $output .= $text;
        $depth--;
        continue;
    }

    $output .= $text;
}

if (file_put_contents($argv[2], $output) === false) {
    fwrite(STDERR, "Unable to write {$argv[2]}\n");
    exit(1);
}

printf("Instrumented %d eval expression(s).\n", $evalCount);

