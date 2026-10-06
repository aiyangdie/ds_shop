<?php
/**
 * Recovered includes/common.php (goto-flattened original).
 * Bootstrap reconstructed from decoded string tables plus readable siblings
 * (member.php, function.php, Cache, PdoHelper). Not wired into the live path.
 */
if (defined('IN_CRONLITE')) {
    return;
}

error_reporting(0);
define('IN_CRONLITE', true);
define('VERSION', '2065');
define('DB_VERSION', '2055');
define('SYSTEM_ROOT', dirname(__FILE__) . '/');
define('ROOT', dirname(SYSTEM_ROOT) . '/');
define('TEMPLATE_ROOT', ROOT . 'template/');
define('PLUGIN_ROOT', SYSTEM_ROOT . 'plugins/');
date_default_timezone_set('PRC');
$date = date('Y-m-d H:i:s');

if (empty($nosession)) {
    session_start();
}

if (!function_exists('is_https')) {
    function is_https()
    {
        if (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) === 'on' || $_SERVER['HTTPS'] == 1)) {
            return true;
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if (isset($_SERVER['HTTP_X_CLIENT_SCHEME']) && strtolower($_SERVER['HTTP_X_CLIENT_SCHEME']) === 'https') {
            return true;
        }
        return false;
    }
}

include_once SYSTEM_ROOT . 'autoloader.php';
Autoloader::register();
include_once SYSTEM_ROOT . 'base.php';

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

if (file_exists(SYSTEM_ROOT . '360safe/360webscan.php')) {
    require_once SYSTEM_ROOT . '360safe/360webscan.php';
}

require ROOT . 'config.php';

if (empty($dbconfig['user']) || empty($dbconfig['pwd']) || empty($dbconfig['dbname'])) {
    header('Content-type:text/html;charset=utf-8');
    exit('你还没安装！<a href="/install/">点此安装</a>');
}

$DB = new \lib\PdoHelper($dbconfig);
if ($DB->query('select * from pre_config where 1') === false) {
    header('Content-type:text/html;charset=utf-8');
    exit('你还没安装！<a href="/install/">点此安装</a>');
}

$CACHE = new \lib\Cache();
$conf = $CACHE->pre_fetch();
define('SYS_KEY', isset($conf['syskey']) ? $conf['syskey'] : '');
$password_hash = '!@#%!s!0';
if (!empty($conf['authcode'])) {
    define('DIST_ID', $conf['authcode']);
}

if (isset($conf['version']) && $conf['version'] < DB_VERSION && empty($install)) {
    header('Content-type:text/html;charset=utf-8');
    exit('请先完成网站升级！<a href="/install/update.php"><font color=red>点此升级</font></a>');
}
if (isset($conf['version']) && strpos((string) $conf['version'], '.') !== false && empty($install)) {
    header('Content-type:text/html;charset=utf-8');
    exit('网站数据版本不支持，请先完成数据转换');
}

include_once SYSTEM_ROOT . 'function.php';

if (!empty($conf['qqjump']) && isset($_SERVER['HTTP_USER_AGENT'])
    && strpos($_SERVER['HTTP_USER_AGENT'], 'QQ/') !== false
    && strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') === false) {
    include TEMPLATE_ROOT . 'default/jump.php';
    exit;
}

if (file_exists(SYSTEM_ROOT . 'txprotect.php') && empty($nosecu) && empty($nosession)) {
    include_once SYSTEM_ROOT . 'txprotect.php';
}

if (defined('CC_Defender') && CC_Defender && empty($nosession) && empty($is_defend_pass)) {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
    $bots = ['baiduspider', 'googlebot', '360spider', 'bingbot', 'yahoo!', 'msnbot', 'yisouspider', 'sosospider', 'sogou web spider', 'sogou inst spider', 'sogou news spider', 'youdaobot', 'bytespider', 'ia_archiver', 'alexa', 'gosospider', 'jikespider', 'etaospider', 'sinaweibobot', 'adsbot-google'];
    $isBot = false;
    foreach ($bots as $bot) {
        if ($bot !== '' && strpos($ua, $bot) !== false) {
            $isBot = true;
            break;
        }
    }
    if (!$isBot) {
        $cookieKey = md5(SYS_KEY . date('Ymd') . $_SERVER['HTTP_HOST']);
        if (!empty($conf['defender_type']) && $conf['defender_type'] === 'slide') {
            if (isset($_GET['defender_hash']) && isset($_COOKIE['defender_key'])) {
                if ($_GET['defender_hash'] === md5($_COOKIE['defender_key'] . $password_hash)) {
                    setcookie('sec_defend', $cookieKey, time() + 3600, '/');
                    setcookie('sec_defend_time', '0', time() - 3600, '/');
                } else {
                    exit('验证失败！');
                }
            }
            if (!isset($_COOKIE['sec_defend']) || $_COOKIE['sec_defend'] !== $cookieKey) {
                header('Content-type:text/html;charset=utf-8');
                if (!isset($_COOKIE['sec_defend_time'])) {
                    echo '浏览器不支持COOKIE或者不正常访问！';
                    exit;
                }
                $slideVal = md5(uniqid((string) mt_rand(), true));
                setcookie('defender_key', $slideVal, time() + 3600, '/');
                $slideSrc = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/user/') !== false)
                    ? '../assets/js/slide.js?_v=' . VERSION
                    : 'assets/js/slide.js?_v=' . VERSION;
                echo '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="UTF-8"><meta http-equiv="X-UA-Compatible" content="IE=edge"><meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no"><title>滑动验证</title><style>.slideBox{position:fixed;top:0;right:0;bottom:0;left:0;text-align:center;font-size:0;white-space:nowrap;overflow:auto}.slideBox:after{content:\'\';display:inline-block;height:100vh;vertical-align:middle}.slider{display:inline-block;vertical-align:middle;text-align:center;font-size:13px;white-space:normal}.slider::before{content:\'人机身份验证，请完成以下操作\';font-size:16px;display:inline-block;margin-bottom:30px}</style></head><body><div class="slideBox"><div class="slider"></div></div><script>window.slideValue=' . json_encode($slideVal) . '</script><script type="text/javascript" src="' . $slideSrc . '"></script></body></html>';
                exit;
            }
        } else {
            if (!isset($_COOKIE['sec_defend']) || $_COOKIE['sec_defend'] !== $cookieKey) {
                header('Content-type:text/html;charset=utf-8');
                echo '<html><head><meta http-equiv="pragma" content="no-cache"><meta http-equiv="cache-control" content="no-cache"><meta http-equiv="content-type" content="text/html;charset=utf-8"><title>正在加载中</title><script>function setCookie(name,value){var exp = new Date();exp.setTime(exp.getTime() + 60*60*1000);document.cookie = name + "="+ escape (value).replace(/\\+/g, \'%2B\') + ";expires=" + exp.toGMTString() + ";path=/";}function getCookie(name){var arr,reg=new RegExp("(^| )"+name+"=([^;]*)(;|$)");if(arr=document.cookie.match(reg))return unescape(arr[2]);else return null;}var sec_defend_time=getCookie(\'sec_defend_time\')||0;sec_defend_time++;setCookie(\'sec_defend\',\'' . $cookieKey . '\');setCookie(\'sec_defend_time\',sec_defend_time);if(sec_defend_time>1)window.location.href="./index.php";else window.location.reload();</script></head><body></body></html>';
                exit;
            }
        }
    }
}

include_once SYSTEM_ROOT . 'core.func.php';
if (file_exists(SYSTEM_ROOT . 'ajax.func.php')) {
    include_once SYSTEM_ROOT . 'ajax.func.php';
}

if (!file_exists(ROOT . 'install/install.lock') && file_exists(ROOT . 'install/index.php')) {
    sysmsg('<h2>检测到无 install.lock 文件</h2><ul><li><font size="4">如果您尚未安装本程序，请<a href="./install/">前往安装</a></font></li><li><font size="4">如果您已经安装本程序，请手动放置一个空的 install.lock 文件到 /install 文件夹下，<b>为了您站点安全，在您完成它之前我们不会工作。</b></font></li></ul><br/><h4>为什么必须建立 install.lock 文件？</h4>它是代刷网的保护文件，如果检测不到它，就会认为站点还没安装，此时任何人都可以安装/重装代刷网。<br/><br/>');
}

$clientip = real_ip(isset($conf['ip_type']) ? intval($conf['ip_type']) : 0);
$scriptpath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$siteurl = (is_https() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . substr($scriptpath, 0, strrpos($scriptpath, '/') + 1);

if (isset($_GET['invite']) && preg_match('/^[0-9a-z]{32}$/i', $_GET['invite'])) {
    $_SESSION['invite'] = $_GET['invite'];
}
if (isset($_COOKIE['mysid']) && preg_match('/^[0-9a-z]{32}$/i', $_COOKIE['mysid'])) {
    $inviteverify = $_COOKIE['mysid'];
}

$domain = $_SERVER['HTTP_HOST'];
$siterow = $DB->getRow("select * from pre_site where domain='{$domain}' or domain2='{$domain}' limit 1");
$is_fenzhan = false;
if ($siterow && (int) $siterow['status'] === 1 && (int) $siterow['power'] > 0) {
    $expired = !empty($conf['fenzhan_expiry']) && !empty($siterow['endtime']) && $siterow['endtime'] < $date;
    if (!$expired) {
        $is_fenzhan = true;
        $conf = array_merge($conf, merge_site_conf($siterow));
    }
}

$cdnlist = [
    '//lib.baomitu.com/',
    'https://cdnjs.snrat.com/ajax/libs/',
    '//lf26-cdn-tos.bytecdntp.com/cdn/expire-1-M/',
    'https://s4.zstatic.net/ajax/libs/',
];
$cdnpublic = $cdnlist[isset($conf['cdnpublic']) ? intval($conf['cdnpublic']) : 0];
if (!isset($cdnlist[intval(isset($conf['cdnpublic']) ? $conf['cdnpublic'] : 0)])) {
    $cdnpublic = $cdnlist[0];
}

include_once SYSTEM_ROOT . 'member.php';
