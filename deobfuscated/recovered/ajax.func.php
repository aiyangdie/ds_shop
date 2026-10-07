<?php

/**
 * Readable replacement for includes/ajax.func.php.
 *
 * Assembled from ajax-basic.php and ajax-rest.php.
 * This file is self-contained and can be copied to includes/ajax.func.php.
 *
 * Recovered functions 1-17:
 * getDatePoint, getFakaInput, uploadimg, setToolSort, setClassSort,
 * getshareid, validate_qzone, getshuoshuo, getrizhi, get_app_token,
 * processInvite, fanghongdwz, qrcodelogin, vaptcha_verify,
 * display_third_title, article_url, adminpermission
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
    global $conf;

    $token = (string) $token;
    if ($token === '') {
        return false;
    }

    $api = isset($conf['captcha_verify_url']) ? trim((string) $conf['captcha_verify_url']) : '';
    if ($api !== '') {
        $response = get_curl($api, http_build_query([
            'id' => $id,
            'secretkey' => $secretKey,
            'scene' => 0,
            'token' => $token,
            'ip' => $ip,
        ]));
        $result = json_decode($response, true);
        return isset($result['success']) && (int) $result['success'] === 1;
    }

    if (isset($_SESSION['vc_code']) && strtolower((string) $_SESSION['vc_code']) === strtolower($token)) {
        return true;
    }
    return isset($_SESSION['captcha_token']) && hash_equals((string) $_SESSION['captcha_token'], $token);
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

// ---- from ajax-rest.php ----

function getDatePoint()
{
    global $DB;

    $orders = [];
    $money = [];
    $date = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime('-' . $i . ' day'));
        $start = $day . ' 00:00:00';
        $end = date('Y-m-d', strtotime($day . ' +1 day')) . ' 00:00:00';
        $idx = 6 - $i;
        $count = (int) $DB->getColumn("SELECT count(*) FROM pre_orders WHERE addtime>='$start' AND addtime<'$end'");
        $sum = $DB->getColumn("SELECT sum(money) FROM `pre_pay` WHERE addtime>='$start' AND addtime<'$end' AND `status`=1");
        $orders[] = [$idx, $count];
        $money[] = [$idx, round((float) $sum, 2)];
        $date[] = [$idx, date('m-d', strtotime($day))];
    }

    return ['orders' => $orders, 'money' => $money, 'date' => $date];
}

function uploadimg($file)
{
    if (!is_array($file) || empty($file['tmp_name']) || empty($file['name']) || !is_file($file['tmp_name'])) {
        return false;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'gif', 'jpeg', 'webp', 'bmp'], true)) {
        return false;
    }
    $root = defined('ROOT') ? ROOT : dirname(__DIR__, 2) . '/';
    $filename = md5_file($file['tmp_name']) . '.' . $ext;
    $relative = 'assets/img/' . $filename;
    if (!@copy($file['tmp_name'], $root . $relative)) {
        return false;
    }
    return $relative;
}

function setToolSort($cid, $tid, $sort = 0)
{
    global $DB;

    $data = $DB->getRow("SELECT * FROM pre_tools WHERE tid='$tid' LIMIT 1");
    if (!$data) {
        return false;
    }
    $cid = intval($cid);
    $cur = (int) $data['sort'];
    $sort = (int) $sort;
    if ($sort === 1) {
        $new = $DB->getRow("SELECT tid,sort FROM pre_tools WHERE cid='$cid' AND sort<'$cur' ORDER BY sort DESC LIMIT 1");
        if (!$new) {
            return false;
        }
        $DB->exec("UPDATE pre_tools SET sort='{$new['sort']}' WHERE tid='$tid'");
        $DB->exec("UPDATE pre_tools SET sort='$cur' WHERE tid='{$new['tid']}'");
        return true;
    }
    if ($sort === 2) {
        $new = $DB->getRow("SELECT tid,sort FROM pre_tools WHERE cid='$cid' AND sort>'$cur' ORDER BY sort ASC LIMIT 1");
        if (!$new) {
            return false;
        }
        $DB->exec("UPDATE pre_tools SET sort='{$new['sort']}' WHERE tid='$tid'");
        $DB->exec("UPDATE pre_tools SET sort='$cur' WHERE tid='{$new['tid']}'");
        return true;
    }
    if ($sort === 0) {
        $DB->exec("UPDATE pre_tools SET sort=sort+1 WHERE cid='$cid' AND sort<'$cur'");
        $min = $DB->getRow("SELECT tid,sort FROM pre_tools WHERE cid='$cid' ORDER BY sort ASC LIMIT 1");
        $DB->exec("UPDATE pre_tools SET sort='" . (int) $min['sort'] . "' WHERE tid='$tid'");
        return true;
    }
    if ($sort === 3) {
        $DB->exec("UPDATE pre_tools SET sort=sort-1 WHERE cid='$cid' AND sort>'$cur'");
        $max = $DB->getRow("SELECT tid,sort FROM pre_tools WHERE cid='$cid' ORDER BY sort DESC LIMIT 1");
        $DB->exec("UPDATE pre_tools SET sort='" . (int) $max['sort'] . "' WHERE tid='$tid'");
        return true;
    }
    return false;
}

function setClassSort($cid, $sort = 0)
{
    global $DB;

    $data = $DB->getRow("SELECT * FROM pre_class WHERE cid='$cid' LIMIT 1");
    if (!$data) {
        return false;
    }
    $cur = (int) $data['sort'];
    $sort = (int) $sort;
    if ($sort === 1) {
        $new = $DB->getRow("SELECT cid,sort FROM pre_class WHERE sort<'$cur' ORDER BY sort DESC LIMIT 1");
        if (!$new) {
            return false;
        }
        $DB->exec("UPDATE pre_class SET sort='{$new['sort']}' WHERE cid='$cid'");
        $DB->exec("UPDATE pre_class SET sort='$cur' WHERE cid='{$new['cid']}'");
        return true;
    }
    if ($sort === 2) {
        $new = $DB->getRow("SELECT cid,sort FROM pre_class WHERE sort>'$cur' ORDER BY sort ASC LIMIT 1");
        if (!$new) {
            return false;
        }
        $DB->exec("UPDATE pre_class SET sort='{$new['sort']}' WHERE cid='$cid'");
        $DB->exec("UPDATE pre_class SET sort='$cur' WHERE cid='{$new['cid']}'");
        return true;
    }
    if ($sort === 0) {
        $DB->exec("UPDATE pre_class SET sort=sort+1 WHERE sort<'$cur'");
        $min = $DB->getRow('SELECT cid,sort FROM pre_class ORDER BY sort ASC LIMIT 1');
        $DB->exec("UPDATE pre_class SET sort='" . (int) $min['sort'] . "' WHERE cid='$cid'");
        return true;
    }
    if ($sort === 3) {
        $DB->exec("UPDATE pre_class SET sort=sort-1 WHERE sort>'$cur'");
        $max = $DB->getRow('SELECT cid,sort FROM pre_class ORDER BY sort DESC LIMIT 1');
        $DB->exec("UPDATE pre_class SET sort='" . (int) $max['sort'] . "' WHERE cid='$cid'");
        return true;
    }
    return false;
}

function getshareid($url)
{
    $url = trim((string) $url);
    if ($url === '' || stripos($url, 'http') === false) {
        return ['code' => -1, 'msg' => '链接格式不正确'];
    }

    $songid = null;
    $songid2 = null;
    if (preg_match('/photoId=([^&]+)/i', $url, $m)) {
        $songid = $m[1];
        if (preg_match('/userId=([^&]+)/i', $url, $u)) {
            $songid2 = $u[1];
        }
    } elseif (preg_match('/item_id=([^&]+)/i', $url, $m) || preg_match('/feed_id=([^&]+)/i', $url, $m) || preg_match('/[?&]vid=([^&]+)/i', $url, $m)) {
        $songid = $m[1];
    } elseif (preg_match('~/(?:video|item|media|detail|photo|group|profile|feed|personal|room)/([^/?&]+)~i', $url, $m)) {
        $songid = $m[1];
    } elseif (preg_match('#/node/play\?#i', $url) || preg_match('#/song/play#i', $url) || preg_match('/y\.qq\.com|kg\.qq\.com/i', $url)) {
        if (preg_match('/[?&]s=([^&]+)/i', $url, $m) || preg_match('/[?&]id=([^&]+)/i', $url, $m) || preg_match('#/song/(\d+)#i', $url, $m)) {
            $songid = $m[1];
        }
    } elseif (preg_match('/[?&]id=([^&]+)/i', $url, $m)) {
        $songid = $m[1];
    }

    if ($songid === null || $songid === '') {
        return ['code' => -1, 'msg' => '无法识别此链接'];
    }
    $out = ['code' => 0, 'msg' => 'succ', 'songid' => $songid];
    if ($songid2 !== null) {
        $out['songid2'] = $songid2;
    }
    return $out;
}

function get_app_token($key)
{
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
    return authcode($key, 'ENCODE', 'APP' . $host . 'KEY');
}

function processInvite($code)
{
    global $DB, $conf, $clientip;

    $code = trim((string) $code);
    if ($code === '') {
        return false;
    }
    $row = $DB->getRow('SELECT * FROM `pre_invite` WHERE `key` = :key LIMIT 1', [':key' => $code]);
    if (!$row || (int) $row['status'] !== 0) {
        return false;
    }
    $shop = $DB->getRow('SELECT * FROM `pre_inviteshop` WHERE `id`=:id LIMIT 1', [':id' => $row['nid']]);
    if ($shop && (int) $shop['active'] === 1 && (int) $shop['type'] === 1 && !empty($conf['captcha_open'])) {
        return 'captcha';
    }

    setcookie('invitecode', $code, time() + 604800, '/');
    $_COOKIE['invitecode'] = $code;

    if ($shop && (int) $shop['active'] === 1 && !empty($clientip)) {
        $log = $DB->getRow(
            'SELECT * FROM `pre_invitelog` WHERE `iid`=:iid AND `ip`=:ip',
            [':iid' => $row['id'], ':ip' => $clientip]
        );
        if (!$log) {
            $DB->exec(
                'INSERT INTO `pre_invitelog`(`iid`,`type`,`date`,`ip`,`status`) VALUES (:iid, 0, NOW(), :ip, 0)',
                [':iid' => $row['id'], ':ip' => $clientip]
            );
        }
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

    $cacheKey = 'dwz_' . md5($url);
    if (!$force && isset($CACHE) && is_object($CACHE) && method_exists($CACHE, 'read')) {
        $cached = $CACHE->read($cacheKey);
        if ($cached) {
            return $cached;
        }
    }

    if ((int) $conf['fanghong_api'] === 9 && empty($conf['fanghong_url'])) {
        return $url;
    }

    $short = $url;
    if (!empty($conf['fanghong_url'])) {
        $api = str_replace(
            ['[url]', '[longurl]', '{url}', '%url%'],
            urlencode($url),
            $conf['fanghong_url']
        );
        if ($api === $conf['fanghong_url']) {
            $api .= (substr($conf['fanghong_url'], -1) === '=' ? urlencode($url) : ((strpos($conf['fanghong_url'], '?') !== false ? '&' : '?') . 'url=' . urlencode($url)));
        }
        $response = get_curl($api);
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            foreach (['ae_url', 'dwz1', 'url', 'short', 'data'] as $field) {
                if (!empty($decoded[$field]) && is_string($decoded[$field]) && strpos($decoded[$field], '/') !== false) {
                    $short = $decoded[$field];
                    break;
                }
            }
            if ($short === $url && !empty($decoded['msg'])) {
                return $decoded['msg'];
            }
        } elseif (is_string($response) && preg_match('#https?://[^\s"\'<>]+#i', $response, $m)) {
            $short = $m[0];
        }
    }

    if ($short !== $url && isset($CACHE) && is_object($CACHE) && method_exists($CACHE, 'save')) {
        $CACHE->save($cacheKey, $short);
    }
    return $short;
}

function qrcodelogin($image = null)
{
    if (!empty($_SESSION['findpwd_qq'])) {
        $uin = (string) $_SESSION['findpwd_qq'];
        return ['code' => 1, 'saveOK' => 0, 'msg' => 'succ', 'uin' => $uin];
    }
    return ['code' => -2, 'saveOK' => -1, 'msg' => '请使用本站QQ扫码完成验证'];
}
