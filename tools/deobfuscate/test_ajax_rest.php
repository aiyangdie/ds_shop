<?php

declare(strict_types=1);

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
if (!function_exists('authcode')) {
    function authcode($string, $operation = 'DECODE', $key = '')
    {
        return $operation . ':' . $key . ':' . $string;
    }
}

require dirname(__DIR__, 2) . '/deobfuscated/recovered/ajax-rest.php';

class AjaxRestDb
{
    public $queue = [];
    public $sql = [];

    public function getRow($sql, $params = [])
    {
        $this->sql[] = $sql;
        return array_shift($this->queue);
    }

    public function getColumn($sql, $params = [])
    {
        $this->sql[] = $sql;
        if (stripos($sql, 'sum(money)') !== false) {
            return 2.5;
        }
        return 3;
    }

    public function exec($sql, $params = [])
    {
        $this->sql[] = $sql;
        return true;
    }
}

$DB = new AjaxRestDb();
$chart = getDatePoint();
if (count($chart['orders']) !== 7 || strpos(implode("\n", $DB->sql), 'pre_pay') === false) {
    throw new RuntimeException('getDatePoint SQL failed');
}
if (strpos(implode("\n", $DB->sql), "AND addtime<'") === false) {
    throw new RuntimeException('getDatePoint interval failed');
}

$tmp = tempnam(sys_get_temp_dir(), 'img');
file_put_contents($tmp, 'x');
$copied = uploadimg(['tmp_name' => $tmp, 'name' => 'a.png']);
if ($copied === false || strpos($copied, 'assets/img/') !== 0) {
    throw new RuntimeException('uploadimg failed');
}
@unlink($tmp);
if (defined('ROOT')) {
    @unlink(ROOT . $copied);
} else {
    @unlink(dirname(__DIR__, 2) . '/' . $copied);
}

$DB = new AjaxRestDb();
$DB->queue = [
    ['tid' => 8, 'cid' => 2, 'sort' => 5],
    ['tid' => 7, 'sort' => 4],
];
if (!setToolSort(2, 8, 1)) {
    throw new RuntimeException('setToolSort swap failed');
}
if (strpos(implode("\n", $DB->sql), "cid='2' AND sort<'5'") === false) {
    throw new RuntimeException('setToolSort neighbor SQL failed');
}

$DB = new AjaxRestDb();
$DB->queue = [
    ['cid' => 4, 'sort' => 3],
    ['cid' => 5, 'sort' => 4],
];
if (!setClassSort(4, 2)) {
    throw new RuntimeException('setClassSort down failed');
}

$share = getshareid('https://www.kuaishou.com/short-video/abc?photoId=PID1&userId=99');
if ($share['songid'] !== 'PID1' || $share['songid2'] !== '99') {
    throw new RuntimeException('getshareid photoId failed');
}

$_SERVER['HTTP_HOST'] = 'Example.COM';
if (get_app_token('k1') !== 'ENCODE:APPexample.comKEY:k1') {
    throw new RuntimeException('get_app_token failed');
}

$DB = new AjaxRestDb();
$conf = ['captcha_open' => 1];
$DB->queue = [
    ['id' => 3, 'status' => 0, 'nid' => 9, 'key' => 'abc123'],
    ['id' => 9, 'active' => 1, 'type' => 1],
];
if (processInvite('abc123') !== 'captcha') {
    throw new RuntimeException('processInvite captcha failed');
}

$conf = ['fanghong_api' => 9, 'fanghong_url' => 'https://dwz.example/?u=[url]'];
$GLOBALS['response'] = '{"ae_url":"https://t.cn/short"}';
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
    throw new RuntimeException('fanghongdwz ae_url failed');
}
if (!isset($CACHE->store['dwz_' . md5('https://shop.example/?i=1')])) {
    throw new RuntimeException('fanghongdwz cache key failed');
}

$GLOBALS['calls'] = [];
$_SESSION = ['findpwd_qq' => '123456'];
$qr = qrcodelogin('IMGDATA');
if ($qr['uin'] !== '123456' || $qr['code'] !== 1) {
    throw new RuntimeException('qrcodelogin session failed');
}
if (!empty($GLOBALS['calls'])) {
    throw new RuntimeException('qrcodelogin must not call external decode');
}
$_SESSION = [];
$qr = qrcodelogin();
if ($qr['code'] !== -2) {
    throw new RuntimeException('qrcodelogin local prompt failed');
}

echo "ajax-rest parity checks passed\n";
