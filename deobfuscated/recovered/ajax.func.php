<?php

/**
 * Readable replacement for includes/ajax.func.php.
 *
 * 17 wrappers reconstructed from the isolated PHP 7.4 capture plus call sites.
 * Not wired into the live include path until an explicit switch.
 */

// ---- from ajax-basic.php ----

function getFakaInput()
{
    global $conf;

    $labels = ['你的邮箱', '手机号码', '你的ＱＱ', 'hide', '取卡密码'];
    $mode = isset($conf['faka_input']) ? (int) $conf['faka_input'] : 0;
    if ($mode === 5) {
        return $conf['faka_inputname'];
    }
    return isset($labels[$mode]) ? $labels[$mode] : $labels[0];
}

function validate_qzone($uin)
{
    $url = 'https://h5.qzone.qq.com/proxy/domain/r.qzone.qq.com/cgi-bin/qzone_dynamic_v7.cgi?uin=' . $uin . '&param=848';
    $response = get_curl($url);
    $response = str_replace(['_Callback(', ')'], '', (string) $response);
    json_decode($response, true);

    return true;
}

function getshuoshuo($uin, $page = 1)
{
    global $conf;

    if (empty($conf['qzone_shuoshuo_api'])) {
        return ['code' => -1, 'msg' => '未配置获取空间说说列表接口'];
    }
    if (!validate_qzone($uin)) {
        return ['code' => -1, 'msg' => '你的QQ空间设置了访问权限，无法获取！'];
    }

    $response = get_curl($conf['qzone_shuoshuo_api'], 'uin=' . urlencode($uin) . '&page=' . urlencode($page));
    $result = json_decode($response, true);
    if (!is_array($result)) {
        return ['code' => -1, 'msg' => '获取说说列表失败，请稍后再试'];
    }
    if (isset($result['code']) && (int) $result['code'] === 0) {
        return ['code' => 0, 'msg' => '获取说说列表成功！', 'data' => $result['data']];
    }
    return $result;
}

function getrizhi($uin, $page = 1)
{
    global $conf;

    if (empty($conf['qzone_rizhi_api'])) {
        return ['code' => -1, 'msg' => '未配置获取空间日志列表接口'];
    }
    if (!validate_qzone($uin)) {
        return ['code' => -1, 'msg' => '你的QQ空间设置了访问权限，无法获取！'];
    }

    $response = get_curl($conf['qzone_rizhi_api'], 'uin=' . urlencode($uin) . '&page=' . urlencode($page));
    $result = json_decode($response, true);
    if (!is_array($result)) {
        return ['code' => -1, 'msg' => '获取日志失败，请稍后再试'];
    }
    if (isset($result['code']) && (int) $result['code'] === 0) {
        return ['code' => 0, 'msg' => '获取日志成功！', 'data' => $result['data']];
    }
    return $result;
}

function vaptcha_verify($id, $secretKey, $token, $ip)
{
    $response = get_curl('https://0.vaptcha.com/verify', http_build_query([
        'id' => $id,
        'secretkey' => $secretKey,
        'scene' => 0,
        'token' => $token,
        'ip' => $ip,
    ]));
    $result = json_decode($response, true);
    return isset($result['success']) && (int) $result['success'] === 1;
}

function display_third_title($code)
{
    if (class_exists('\\lib\\Plugin')) {
        foreach ((array) \lib\Plugin::getThirdPluginsList() as $plugin) {
            if (isset($plugin['code']) && $plugin['code'] === $code) {
                $color = $code === 'daishua' ? 'orange' : 'blue';
                return '<font color=' . $color . '>' . $plugin['title'] . '</font>';
            }
        }
    }
    return '<font color=grey>已移除</font>';
}

function article_url($id = 0, $query = null)
{
    global $conf;

    if (!empty($conf['article_rewrite'])) {
        $url = $id ? './article-' . $id . '.html' : './articlelist.html';
        return $query === null || $query === '' ? $url : $url . '?' . $query;
    }
    $url = $id ? './?mod=article&id=' . $id : './?mod=articlelist';
    return $query === null || $query === '' ? $url : $url . '&' . $query;
}

function adminpermission($permission, $responseType = 0)
{
    global $adminuserrow;

    if (empty($adminuserrow) || !empty($adminuserrow['super'])) {
        return true;
    }
    $permissions = explode(',', isset($adminuserrow['permission']) ? $adminuserrow['permission'] : '');
    if (in_array($permission, $permissions, true)) {
        return true;
    }

    $message = '您的账号没有权限使用此功能';
    $type = (int) $responseType;
    if ($type === 2) {
        exit('{"code":-1001,"msg":"' . $message . '"}');
    }
    if ($type === 3) {
        return false;
    }
    if ($type === 1 && function_exists('showmsg')) {
        showmsg($message, 3);
    }
    return false;
}

// ---- remaining captured wrappers ----

function getDatePoint()
{
    global $DB;

    $orders = [];
    $money = [];
    $date = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime('-' . $i . ' day'));
        $idx = 6 - $i;
        $start = $day . ' 00:00:00';
        $end = $day . ' 23:59:59';
        $count = (int) $DB->getColumn("SELECT count(*) FROM pre_orders WHERE addtime>='$start' AND addtime<='$end'");
        $sum = $DB->getColumn("SELECT IFNULL(sum(money),0) FROM pre_orders WHERE addtime>='$start' AND addtime<='$end' AND status!=4");
        $orders[] = [$idx, $count];
        $money[] = [$idx, round((float) $sum, 2)];
        $date[] = [$idx, date('m-d', strtotime($day))];
    }

    return ['orders' => $orders, 'money' => $money, 'date' => $date];
}

function uploadimg($file)
{
    $tmp = is_array($file) && isset($file['tmp_name']) ? $file['tmp_name'] : $file;
    if (!$tmp || !is_file($tmp)) {
        return false;
    }
    $root = defined('ROOT') ? ROOT : dirname(__DIR__, 2) . '/';
    $dir = $root . 'assets/img/Product/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $filename = 'shop_' . md5_file($tmp) . '.png';
    if (!@copy($tmp, $dir . $filename)) {
        return false;
    }
    return 'assets/img/Product/' . $filename;
}

function setToolSort($cid, $tid, $sort = 0)
{
    global $DB;

    $row = $DB->getRow("SELECT tid,cid,sort FROM pre_tools WHERE tid='$tid' LIMIT 1");
    if (!$row) {
        return false;
    }
    $cid = intval($cid);
    $scope = "cid='$cid'";
    return recovered_ajax_move_sort($DB, 'pre_tools', 'tid', $tid, (int) $row['sort'], (int) $sort, $scope);
}

function setClassSort($cid, $sort = 0)
{
    global $DB;

    $row = $DB->getRow("SELECT cid,sort FROM pre_class WHERE cid='$cid' LIMIT 1");
    if (!$row) {
        return false;
    }
    return recovered_ajax_move_sort($DB, 'pre_class', 'cid', $cid, (int) $row['sort'], (int) $sort, '1=1');
}

function recovered_ajax_move_sort($DB, $table, $pk, $id, $currentSort, $action, $scope)
{
    $id = intval($id);
    if ($action === 0) {
        $min = (int) $DB->getColumn("SELECT sort FROM $table WHERE $scope ORDER BY sort ASC LIMIT 1");
        return $DB->exec("UPDATE $table SET sort='" . ($min - 1) . "' WHERE $pk='$id'") !== false;
    }
    if ($action === 3) {
        $max = (int) $DB->getColumn("SELECT sort FROM $table WHERE $scope ORDER BY sort DESC LIMIT 1");
        return $DB->exec("UPDATE $table SET sort='" . ($max + 1) . "' WHERE $pk='$id'") !== false;
    }
    if ($action === 1) {
        $neighbor = $DB->getRow("SELECT $pk AS id, sort FROM $table WHERE $scope AND sort<'$currentSort' ORDER BY sort DESC LIMIT 1");
    } else {
        $neighbor = $DB->getRow("SELECT $pk AS id, sort FROM $table WHERE $scope AND sort>'$currentSort' ORDER BY sort ASC LIMIT 1");
    }
    if (!$neighbor) {
        return false;
    }
    $DB->exec("UPDATE $table SET sort='{$neighbor['sort']}' WHERE $pk='$id'");
    $DB->exec("UPDATE $table SET sort='$currentSort' WHERE $pk='{$neighbor['id']}'");
    return true;
}

function getshareid($url)
{
    $url = trim((string) $url);
    if ($url === '' || stripos($url, 'http') === false) {
        return ['code' => -1, 'msg' => '链接格式不正确'];
    }

    if (preg_match('/music\.163\.com/i', $url) && preg_match('/[?&]id=(\d+)/i', $url, $m)) {
        return ['code' => 0, 'songid' => $m[1]];
    }
    if (preg_match('/y\.qq\.com|i\.y\.qq\.com/i', $url)) {
        if (preg_match('/songid=(\d+)/i', $url, $m) || preg_match('/\/song\/(\d+)/i', $url, $m)) {
            return ['code' => 0, 'songid' => $m[1]];
        }
    }
    if (preg_match('/kugou\.com/i', $url) && preg_match('/hash=([0-9A-Fa-f]+)/i', $url, $m)) {
        return ['code' => 0, 'songid' => $m[1]];
    }
    if (preg_match('/photoId=([0-9a-zA-Z_-]+)/i', $url, $m) || preg_match('/\/short-video\/([0-9a-zA-Z_-]+)/i', $url, $m)) {
        $out = ['code' => 0, 'songid' => $m[1]];
        if (preg_match('/userId=([0-9]+)/i', $url, $u)) {
            $out['songid2'] = $u[1];
        }
        return $out;
    }
    if (preg_match('/(?:kuaishou|gifshow)\.com/i', $url) && preg_match('/\/s\/([0-9a-zA-Z]+)/i', $url, $m)) {
        $headers = function_exists('get_curl') ? get_curl($url, 0, 0, 0, 1) : '';
        if (preg_match('/photoId=([0-9a-zA-Z_-]+)/i', (string) $headers, $p)) {
            return ['code' => 0, 'songid' => $p[1]];
        }
        return ['code' => 0, 'songid' => $m[1]];
    }
    if (preg_match('/(?:douyin|iesdouyin)\.com/i', $url)) {
        if (preg_match('/\/video\/(\d+)/i', $url, $m) || preg_match('/modal_id=(\d+)/i', $url, $m)) {
            return ['code' => 0, 'songid' => $m[1]];
        }
        $html = function_exists('get_curl') ? get_curl($url) : '';
        if (preg_match('/\/video\/(\d+)/i', (string) $html, $m)) {
            return ['code' => 0, 'songid' => $m[1]];
        }
    }
    if (preg_match('/\/(?:video|item|note)\/([0-9a-zA-Z_-]+)/i', $url, $m)) {
        return ['code' => 0, 'songid' => $m[1]];
    }
    if (preg_match('/[?&](?:id|vid|aweme_id|mid)=([0-9a-zA-Z_-]+)/i', $url, $m)) {
        return ['code' => 0, 'songid' => $m[1]];
    }

    return ['code' => -1, 'msg' => '无法识别此链接'];
}

function get_app_token($key)
{
    if (function_exists('authcode') && defined('SYS_KEY')) {
        return authcode($key, 'ENCODE', SYS_KEY);
    }
    return md5($key . (defined('SYS_KEY') ? SYS_KEY : ''));
}

function processInvite($code)
{
    global $DB, $conf, $clientip;

    $code = trim((string) $code);
    if ($code === '') {
        return false;
    }
    $row = $DB->getRow('SELECT * FROM pre_invite WHERE `key`=:k LIMIT 1', [':k' => $code]);
    if (!$row || (int) $row['status'] !== 0) {
        return false;
    }
    if (!empty($conf['captcha_open'])) {
        return 'captcha';
    }

    setcookie('invitecode', $code, time() + 604800, '/');
    $_COOKIE['invitecode'] = $code;

    if (empty($clientip)) {
        return true;
    }
    $seen = (int) $DB->getColumn('SELECT count(*) FROM pre_invitelog WHERE `ip`=:ip', [':ip' => $clientip]);
    if ($seen === 0) {
        $DB->exec(
            'INSERT INTO `pre_invitelog`(`iid`,`type`,`date`,`ip`,`status`) VALUES (:iid, 1, NOW(), :ip, 0)',
            [':iid' => $row['id'], ':ip' => $clientip]
        );
        $DB->exec('UPDATE `pre_invite` SET `click`=`click`+1 WHERE `id`=:id', [':id' => $row['id']]);
    }
    return true;
}

function fanghongdwz($url, $force = false)
{
    global $conf, $CACHE;

    $url = trim((string) $url);
    if ($url === '' || empty($conf['fanghong_api'])) {
        return $url;
    }

    $cacheKey = 'fanghong_' . md5($url);
    if (!$force && isset($CACHE) && is_object($CACHE) && method_exists($CACHE, 'read')) {
        $cached = $CACHE->read($cacheKey);
        if ($cached) {
            return $cached;
        }
    }

    $short = $url;
    if ((int) $conf['fanghong_api'] === 9 && !empty($conf['fanghong_url'])) {
        $api = str_replace(
            ['[url]', '[longurl]', '{url}', '%url%'],
            urlencode($url),
            $conf['fanghong_url']
        );
        $response = get_curl($api);
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            foreach (['url', 'dwz', 'shorturl', 'short_url', 'result'] as $field) {
                if (!empty($decoded[$field]) && is_string($decoded[$field]) && strpos($decoded[$field], '/') !== false) {
                    $short = $decoded[$field];
                    break;
                }
            }
            if ($short === $url && !empty($decoded['data']) && is_string($decoded['data']) && strpos($decoded['data'], '/') !== false) {
                $short = $decoded['data'];
            }
        } elseif (is_string($response) && strpos($response, 'http') !== false) {
            if (preg_match('#https?://[^\s"\'<>]+#i', $response, $m)) {
                $short = $m[0];
            }
        }
    }

    if ($short !== $url && isset($CACHE) && is_object($CACHE) && method_exists($CACHE, 'save')) {
        $CACHE->save($cacheKey, $short);
    }
    return $short;
}

function qrcodelogin($image)
{
    $image = trim((string) $image);
    if ($image === '') {
        return ['code' => -1, 'msg' => '图片不能为空'];
    }

    $payload = $image;
    if (strpos($image, 'base64,') !== false) {
        $parts = explode('base64,', $image, 2);
        $payload = $parts[1];
    }

    $response = get_curl('https://cli.im/Api/Browser/deqr', 'data=' . urlencode($payload));
    $result = json_decode($response, true);
    $text = '';
    if (is_array($result)) {
        if (!empty($result['data']['RawData'])) {
            $text = $result['data']['RawData'];
        } elseif (!empty($result['data']['utf8'])) {
            $text = $result['data']['utf8'];
        } elseif (!empty($result['text'])) {
            $text = $result['text'];
        }
    }

    $uin = null;
    if (preg_match('/uin=(\d+)/i', $text, $m) || preg_match('/\b([1-9][0-9]{4,11})\b/', $text, $m)) {
        $uin = $m[1];
    }
    if ($uin) {
        $_SESSION['findpwd_qq'] = $uin;
        return ['code' => 1, 'msg' => 'succ', 'uin' => $uin];
    }
    if (is_array($result) && isset($result['msg'])) {
        return $result;
    }
    return ['code' => -1, 'msg' => '二维码解析失败'];
}
