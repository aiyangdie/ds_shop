<?php
/**
 * 一键配置本地 App 工厂
 * 用法: php tools/appbuild/setup_local.php
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}
$root = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
chdir($root);
require_once $root . 'includes/common.php';

$sdk = '';
$candidates = array(
    getenv('ANDROID_HOME') ?: '',
    getenv('ANDROID_SDK_ROOT') ?: '',
    (getenv('LOCALAPPDATA') ?: '') . DIRECTORY_SEPARATOR . 'Android' . DIRECTORY_SEPARATOR . 'Sdk',
    'C:\\Android\\Sdk',
);
foreach ($candidates as $c) {
    if ($c && is_dir($c) && is_dir($c . DIRECTORY_SEPARATOR . 'platforms')) {
        $sdk = $c;
        break;
    }
}

$jdk17 = '';
$jdkRoots = array(
    'C:\\Program Files\\Eclipse Adoptium\\jdk-17.0.20.101-hotspot',
    'C:\\Program Files\\Eclipse Adoptium\\jdk-17.0.20.8-hotspot',
);
foreach ($jdkRoots as $j) {
    if (is_file($j . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'java.exe')) {
        $jdk17 = $j;
        break;
    }
}

$prefix = 'com.appshell.site';
if (!empty($conf['sitename'])) {
    $slug = preg_replace('/[^a-z0-9]/', '', strtolower(preg_replace('/[\x{4e00}-\x{9fff}]/u', '', $conf['sitename'])));
    if ($slug === '') $slug = 'site';
    $prefix = 'com.' . substr($slug, 0, 12) . '.app';
    if (!preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/i', $prefix)) {
        $prefix = 'com.appshell.site';
    }
}

$settings = array(
    'appcreate_mode' => 'local',
    'appcreate_open' => '1',
    'appcreate_diy' => '1',
    'appcreate_package_prefix' => $prefix,
    'appcreate_sdk_dir' => $sdk,
    'appcreate_theme' => isset($conf['appcreate_theme']) && $conf['appcreate_theme'] !== '' ? $conf['appcreate_theme'] : '#1f6feb',
    'appcreate_nonav' => isset($conf['appcreate_nonav']) ? $conf['appcreate_nonav'] : '0',
    'appcreate_price' => isset($conf['appcreate_price']) ? $conf['appcreate_price'] : '0',
    'appcreate_price2' => isset($conf['appcreate_price2']) ? $conf['appcreate_price2'] : '0',
);

foreach ($settings as $k => $v) {
    saveSetting($k, $v);
    echo "set $k = $v\n";
}
if (isset($CACHE) && is_object($CACHE)) {
    $CACHE->clear();
}

// 写入环境提示文件给 worker
$envFile = $root . 'tools/appbuild/env.local.php';
$envPhp = "<?php\nreturn " . var_export(array(
    'ANDROID_SDK_DIR' => $sdk,
    'JAVA_HOME' => $jdk17,
), true) . ";\n";
file_put_contents($envFile, $envPhp);
echo "wrote tools/appbuild/env.local.php\n";

// 确保输出目录与默认图标
$out = $root . 'assets/uploads/apps';
if (!is_dir($out)) mkdir($out, 0755, true);
if (!is_file($out . '/default_icon.png')) {
    $gen = $root . 'tools/gen_icons.php';
    if (is_file($gen)) {
        passthru(PHP_BINARY . ' ' . escapeshellarg($gen));
    }
}

// 初始化表结构
$factory = new \lib\AppFactory($DB, array_merge($conf, $settings));
echo "schema ok\n";
echo "queue=" . $factory->countQueued() . "\n";
echo "DONE. mode=local sdk=" . ($sdk !== '' ? $sdk : '(missing)') . " jdk=" . ($jdk17 !== '' ? $jdk17 : '(missing)') . "\n";
