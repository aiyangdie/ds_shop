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
    $index = rtrim($file, '/\\') . DIRECTORY_SEPARATOR . 'index.php';
    if (file_exists($index)) {
        chdir(dirname($index));
        require $index;
        return true;
    }
}

chdir(__DIR__);
require __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
