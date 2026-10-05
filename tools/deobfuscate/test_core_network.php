<?php

declare(strict_types=1);

if (!extension_loaded('curl')) {
    fwrite(STDERR, "curl extension is required\n");
    exit(2);
}

$conf = [];
require dirname(__DIR__, 2) . '/deobfuscated/recovered/core-network.php';

$fixture = $argv[1] ?? 'http://127.0.0.1:18081';
$get = json_decode(curl_get($fixture . '/get?a=1'), true);
if ($get['method'] !== 'GET' || $get['query']['a'] !== '1' || strpos($get['headers']['User-Agent'], 'Android 4.4.1') === false) {
    throw new RuntimeException('curl_get parity failure');
}

$post = json_decode(shequ_get_curl($fixture . '/post', 'x=1', 'https://ref.test/', 'sid=abc', 0, ['X-Test: yes']), true);
if ($post['method'] !== 'POST' || $post['body'] !== 'x=1' || $post['headers']['Referer'] !== 'https://ref.test/' || $post['headers']['Cookie'] !== 'sid=abc' || $post['headers']['X-Test'] !== 'yes') {
    throw new RuntimeException('shequ_get_curl parity failure');
}

echo "core-network parity checks passed\n";
