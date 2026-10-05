<?php

declare(strict_types=1);

$recordedGetCurlCalls = [];
function get_curl(...$arguments)
{
    $GLOBALS['recordedGetCurlCalls'][] = $arguments;
    return '{"code":0}';
}

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

$conf = ['wechat_api' => 1, 'wechat_apptoken' => 'TOKEN', 'wechat_appuid' => 'UID'];
send_wechat('标题', '内容');
if ($recordedGetCurlCalls[0][0] !== 'https://wxpusher.zjiecode.com/api/send/message' || $recordedGetCurlCalls[0][7] !== ['Content-Type: application/json; charset=UTF-8']) {
    throw new RuntimeException('send_wechat Wxpusher parity failure');
}
$recordedGetCurlCalls = [];
$conf = ['wechat_api' => 2, 'wechat_sckey' => 'KEY'];
send_wechat('标题', '内容');
if ($recordedGetCurlCalls[0][0] !== 'https://sc.ftqq.com/KEY.send') {
    throw new RuntimeException('send_wechat ServerChan parity failure');
}

echo "core-network parity checks passed\n";
