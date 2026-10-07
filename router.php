<?php
// Local router: ignore PHP 8.x deprecations from obfuscated core
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_local_error.log');
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
        return true;
    }
    return false;
});

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($uri === null || $uri === '') {
    $uri = '/';
}
$qs = (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '') ? ('?' . $_SERVER['QUERY_STRING']) : '';

// /admin、/user 无尾斜杠时，相对菜单会跳到站点根目录（前台首页）
if (preg_match('#^/(admin|user|supplier|install)$#', $uri)) {
    header('Location: ' . $uri . '/' . $qs, true, 301);
    return true;
}

$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $uri);

if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    if (preg_match('/\.php$/i', $file)) {
        chdir(dirname($file));
        require $file;
        return true;
    }
    return false;
}

if ($uri !== '/' && is_dir($file)) {
    if (substr($uri, -1) !== '/') {
        header('Location: ' . $uri . '/' . $qs, true, 301);
        return true;
    }
    $index = rtrim($file, '/\\') . DIRECTORY_SEPARATOR . 'index.php';
    if (file_exists($index)) {
        chdir(dirname($index));
        require $index;
        return true;
    }
}

// 后台/分站路径找不到时不要落到前台首页
if (preg_match('#^/(admin|user)(/|$)#', $uri)) {
    http_response_code(404);
    $back = (strpos($uri, '/user') === 0) ? '/user/' : '/admin/';
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><meta charset="utf-8"><title>页面不存在</title>';
    echo '<p>后台页面不存在。</p><p><a href="'.htmlspecialchars($back).'">返回后台</a> · <a href="/">网站首页</a></p>';
    return true;
}

chdir(__DIR__);
require __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
