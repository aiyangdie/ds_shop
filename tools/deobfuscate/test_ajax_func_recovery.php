<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = file_get_contents($root . '/deobfuscated/recovered/ajax.func.php');
if ($src === false) {
    throw new RuntimeException('missing recovered ajax.func.php');
}

foreach ([
    'function getDatePoint(',
    'function getFakaInput(',
    'function uploadimg(',
    'function setToolSort(',
    'function setClassSort(',
    'function getshareid(',
    'function validate_qzone(',
    'function getshuoshuo(',
    'function getrizhi(',
    'function get_app_token(',
    'function processInvite(',
    'function fanghongdwz(',
    'function qrcodelogin(',
    'function vaptcha_verify(',
    'function display_third_title(',
    'function article_url(',
    'function adminpermission(',
    'assets/img/Product/',
    'pre_orders WHERE addtime',
    'pre_invite',
    'https://0.vaptcha.com/verify',
] as $needle) {
    if (strpos($src, $needle) === false) {
        throw new RuntimeException('missing fragment: ' . $needle);
    }
}

foreach (['eval(', 'goto '] as $banned) {
    if (strpos($src, $banned) !== false) {
        throw new RuntimeException('recovered ajax.func.php still contains ' . trim($banned));
    }
}

$conf = [];
$calls = [];
$GLOBALS['response'] = '';
if (!function_exists('get_curl')) {
    function get_curl(...$arguments)
    {
        $GLOBALS['calls'][] = $arguments;
        return $GLOBALS['response'];
    }
}

require $root . '/deobfuscated/recovered/ajax.func.php';

$conf = ['faka_input' => 5, 'faka_inputname' => '自定义'];
if (getFakaInput() !== '自定义') {
    throw new RuntimeException('getFakaInput custom failed');
}
$conf = ['faka_input' => 3];
if (getFakaInput() !== 'hide') {
    throw new RuntimeException('getFakaInput hide failed');
}

$conf = ['article_rewrite' => 1];
if (article_url(12, 'x=1') !== './article-12.html?x=1') {
    throw new RuntimeException('article_url rewrite failed');
}
$conf = ['article_rewrite' => 0];
if (article_url() !== './?mod=articlelist') {
    throw new RuntimeException('article_url list failed');
}

$GLOBALS['response'] = '{"success":1}';
$calls = [];
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
$adminuserrow = ['permission' => 'order,shop'];
if (adminpermission('shop', 0) !== true || adminpermission('set', 3) !== false) {
    throw new RuntimeException('staff permission failed');
}

class RecoveredAjaxFakeDb
{
    public $row;
    public $column = 0;
    public $execs = [];

    public function getRow($sql, $params = [])
    {
        return $this->row;
    }

    public function getColumn($sql, $params = [])
    {
        return $this->column;
    }

    public function exec($sql, $params = [])
    {
        $this->execs[] = $sql;
        return true;
    }
}

$DB = new RecoveredAjaxFakeDb();
$series = [];
$DB = new class {
    public $n = 0;
    public function getColumn($sql, $params = [])
    {
        if (stripos($sql, 'sum(money)') !== false) {
            return 1.5;
        }
        return $this->n++;
    }
};
$chart = getDatePoint();
if (!isset($chart['orders'], $chart['money'], $chart['date']) || count($chart['date']) !== 7) {
    throw new RuntimeException('getDatePoint shape failed');
}

$DB = new RecoveredAjaxFakeDb();
$DB->row = ['tid' => 8, 'cid' => 2, 'sort' => 5];
$DB->column = 1;
if (!setToolSort(2, 8, 0)) {
    throw new RuntimeException('setToolSort top failed');
}

$conf = ['captcha_open' => 1];
$DB->row = ['id' => 3, 'status' => 0, 'key' => 'abc123'];
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
