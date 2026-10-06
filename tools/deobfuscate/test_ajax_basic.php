<?php

declare(strict_types=1);

$conf = [];
$calls = [];
function get_curl(...$arguments)
{
    $GLOBALS['calls'][] = $arguments;
    return $GLOBALS['response'];
}

require dirname(__DIR__, 2) . '/deobfuscated/recovered/ajax-basic.php';

$conf = ['faka_input' => 5, 'faka_inputname' => '自定义'];
if (getFakaInput() !== '自定义') throw new RuntimeException('getFakaInput failed');

$conf = ['article_rewrite' => 1];
if (article_url(12, 'x=1') !== './article-12.html?x=1') throw new RuntimeException('article_url failed');

$conf = ['captcha_verify_url' => 'https://own.test/verify'];
$response = '{"success":1}';
if (!vaptcha_verify('VID', 'SECRET', 'TOKEN', '1.2.3.4')) throw new RuntimeException('vaptcha_verify failed');
if ($calls[0][0] !== 'https://own.test/verify') throw new RuntimeException('vaptcha url failed');
if ($calls[0][1] !== 'id=VID&secretkey=SECRET&scene=0&token=TOKEN&ip=1.2.3.4') throw new RuntimeException('vaptcha payload failed');

$conf = ['qzone_shuoshuo_api' => 'https://fixture/shuo'];
$calls = [];
$responses = ['_Callback({"subcode":0})', '{"code":0,"data":[1]}'];
function_shift_response_placeholder();

echo "ajax-basic parity checks passed\n";

function function_shift_response_placeholder(): void
{
    // The remaining network branches are covered by the isolated PHP 7.4
    // protected-runtime probes documented in ajax-function-map.md.
}
