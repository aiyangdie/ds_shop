<?php

/**
 * First readable Ajax batch (8 helpers).
 * Loaded by recovered/ajax.func.php.
 */

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
