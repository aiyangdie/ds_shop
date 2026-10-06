<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = file_get_contents($root . '/deobfuscated/recovered/ajax.func.php');
$basic = file_get_contents($root . '/deobfuscated/recovered/ajax-basic.php');
$rest = file_get_contents($root . '/deobfuscated/recovered/ajax-rest.php');
if ($src === false || $basic === false || $rest === false) {
    throw new RuntimeException('missing recovered ajax files');
}

foreach (['ajax-basic.php', 'ajax-rest.php'] as $inc) {
    if (strpos($src, $inc) === false) {
        throw new RuntimeException('ajax.func.php missing include ' . $inc);
    }
}

foreach ([
    'function getFakaInput(',
    'function validate_qzone(',
    'function getshuoshuo(',
    'function getrizhi(',
    'function vaptcha_verify(',
    'function display_third_title(',
    'function article_url(',
    'function adminpermission(',
    'captcha_verify_url',
] as $needle) {
    if (strpos($basic, $needle) === false) {
        throw new RuntimeException('missing basic fragment: ' . $needle);
    }
}

foreach ([
    'function getDatePoint(',
    'function uploadimg(',
    'function setToolSort(',
    'function setClassSort(',
    'function getshareid(',
    'function get_app_token(',
    'function processInvite(',
    'function fanghongdwz(',
    'function qrcodelogin(',
    'SELECT sum(money) FROM `pre_pay`',
    'assets/img/',
    'dwz_',
    '请使用本站QQ扫码完成验证',
    "APP' . \$host . 'KEY",
] as $needle) {
    if (strpos($rest, $needle) === false) {
        throw new RuntimeException('missing rest fragment: ' . $needle);
    }
}

$conf = [];
if (!function_exists('get_curl')) {
    function get_curl(...$arguments)
    {
        $GLOBALS['calls'][] = $arguments;
        return $GLOBALS['response'];
    }
}
if (!function_exists('authcode')) {
    function authcode($string, $operation = 'DECODE', $key = '')
    {
        return $operation . ':' . $key . ':' . $string;
    }
}

require $root . '/deobfuscated/recovered/ajax.func.php';

$conf = ['faka_input' => 5, 'faka_inputname' => '自定义'];
if (getFakaInput() !== '自定义') {
    throw new RuntimeException('getFakaInput custom failed');
}

$conf = ['article_rewrite' => 1];
if (article_url(12, 'x=1') !== './article-12.html?x=1') {
    throw new RuntimeException('article_url rewrite failed');
}

$conf = ['captcha_verify_url' => 'https://own.test/verify'];
$GLOBALS['response'] = '{"success":1}';
$GLOBALS['calls'] = [];
if (!vaptcha_verify('VID', 'SECRET', 'TOKEN', '1.2.3.4')) {
    throw new RuntimeException('vaptcha_verify failed');
}

$share = getshareid('https://music.163.com/#/song?id=12345');
if ($share['code'] !== 0 || $share['songid'] !== '12345') {
    throw new RuntimeException('getshareid netease failed');
}

$adminuserrow = null;
if (adminpermission('shop', 0) !== true) {
    throw new RuntimeException('super admin permission failed');
}

class RecoveredAjaxFakeDb
{
    public $row;

    public function getRow($sql, $params = [])
    {
        return $this->row;
    }

    public function getColumn($sql, $params = [])
    {
        return 1;
    }

    public function exec($sql, $params = [])
    {
        return true;
    }
}

$DB = new class {
    public function getColumn($sql, $params = [])
    {
        return stripos($sql, 'sum(money)') !== false ? 1.5 : 2;
    }
};
$chart = getDatePoint();
if (!isset($chart['orders'], $chart['money'], $chart['date']) || count($chart['date']) !== 7) {
    throw new RuntimeException('getDatePoint shape failed');
}

$DB = new RecoveredAjaxFakeDb();
$DB->row = ['tid' => 8, 'cid' => 2, 'sort' => 5];
if (!setToolSort(2, 8, 0)) {
    throw new RuntimeException('setToolSort top failed');
}

$conf = ['captcha_open' => 1];
$DB->row = ['id' => 3, 'status' => 0, 'nid' => 3, 'key' => 'abc123', 'active' => 1, 'type' => 1];
if (processInvite('abc123') !== 'captcha') {
    throw new RuntimeException('processInvite captcha failed');
}

$conf = ['fanghong_api' => 9, 'fanghong_url' => 'https://dwz.example/?u=[url]'];
$GLOBALS['response'] = '{"url":"https://t.cn/short"}';
$CACHE = new class {
    public $store = [];
    public function read($k)
    {
        return isset($this->store[$k]) ? $this->store[$k] : null;
    }
    public function save($k, $v)
    {
        $this->store[$k] = $v;
    }
};
if (fanghongdwz('https://shop.example/?i=1') !== 'https://t.cn/short') {
    throw new RuntimeException('fanghongdwz custom api failed');
}

echo "ajax.func.php recovery checks passed\n";
