<?php
/**
 * 不经过混淆 common.php，直接写库配置本地 App 工厂
 */
$root = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
chdir($root);
require $root . 'config.php';

$host = $dbconfig['host'];
$port = isset($dbconfig['port']) ? intval($dbconfig['port']) : 3306;
$user = $dbconfig['user'];
$pwd = $dbconfig['pwd'];
$dbname = $dbconfig['dbname'];
$qz = isset($dbconfig['dbqz']) ? $dbconfig['dbqz'] : 'pre';
$table = $qz . '_config';
$appsTable = $qz . '_apps';

$mysqli = @new mysqli($host, $user, $pwd, $dbname, $port);
if ($mysqli->connect_errno) {
    fwrite(STDERR, 'DB connect fail: ' . $mysqli->connect_error . "\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');

function upsertConfig(mysqli $db, $table, $k, $v)
{
    $k = $db->real_escape_string($k);
    $v = $db->real_escape_string(strval($v));
    $exists = $db->query("SELECT v FROM `$table` WHERE k='$k' LIMIT 1");
    if ($exists && $exists->num_rows > 0) {
        $db->query("UPDATE `$table` SET v='$v' WHERE k='$k'");
    } else {
        $db->query("INSERT INTO `$table` (k,v) VALUES ('$k','$v')");
    }
    echo "config $k = $v\n";
}

$sdk = '';
$candidates = array(
    getenv('ANDROID_HOME') ?: '',
    getenv('ANDROID_SDK_ROOT') ?: '',
    (getenv('LOCALAPPDATA') ?: '') . '\\Android\\Sdk',
    'C:\\Android\\Sdk',
);
foreach ($candidates as $c) {
    if ($c && is_dir($c) && is_dir($c . DIRECTORY_SEPARATOR . 'platforms')) {
        $sdk = $c;
        break;
    }
}

$jdk17 = '';
foreach (array(
    'C:\\Program Files\\Eclipse Adoptium\\jdk-17.0.20.101-hotspot',
    'C:\\Program Files\\Eclipse Adoptium\\jdk-17.0.20.8-hotspot',
) as $j) {
    if (is_file($j . '\\bin\\java.exe')) {
        $jdk17 = $j;
        break;
    }
}

$settings = array(
    'appcreate_mode' => 'local',
    'appcreate_open' => '1',
    'appcreate_diy' => '1',
    'appcreate_package_prefix' => 'com.appshell.site',
    'appcreate_sdk_dir' => $sdk,
    'appcreate_theme' => '#1f6feb',
    'appcreate_nonav' => '0',
    'appcreate_price' => '0',
    'appcreate_price2' => '0',
    'appcreate_storage' => 'auto',
);
foreach ($settings as $k => $v) {
    upsertConfig($mysqli, $table, $k, $v);
}

// 清缓存字段
upsertConfig($mysqli, $table, 'cache', '');

// apps 表补字段
$cols = array(
    'url' => "VARCHAR(255) DEFAULT NULL",
    'theme' => "VARCHAR(32) DEFAULT NULL",
    'icon_path' => "VARCHAR(255) DEFAULT NULL",
    'splash_path' => "VARCHAR(255) DEFAULT NULL",
    'progress' => "TINYINT UNSIGNED NOT NULL DEFAULT 0",
    'error' => "VARCHAR(500) DEFAULT NULL",
    'build_log' => "MEDIUMTEXT NULL",
    'build_status' => "TINYINT NOT NULL DEFAULT 0",
    'nonav' => "TINYINT NOT NULL DEFAULT 0",
    'updatetime' => "DATETIME DEFAULT NULL",
    'file_size' => "INT UNSIGNED NOT NULL DEFAULT 0",
    'storage_driver' => "VARCHAR(16) DEFAULT NULL",
);
foreach ($cols as $name => $ddl) {
    $chk = $mysqli->query("SHOW COLUMNS FROM `$appsTable` LIKE '$name'");
    if ($chk && $chk->num_rows === 0) {
        if (!$mysqli->query("ALTER TABLE `$appsTable` ADD COLUMN `$name` $ddl")) {
            echo "warn alter $name: " . $mysqli->error . "\n";
        } else {
            echo "alter add $name\n";
        }
    }
}

$envFile = $root . 'tools/appbuild/env.local.php';
file_put_contents($envFile, "<?php\nreturn " . var_export(array(
    'ANDROID_SDK_DIR' => $sdk,
    'JAVA_HOME' => $jdk17,
), true) . ";\n");
echo "wrote env.local.php\n";

$out = $root . 'assets/uploads/apps';
if (!is_dir($out)) mkdir($out, 0755, true);
if (!is_file($out . '/default_icon.png') && is_file($root . 'tools/gen_icons.php')) {
    passthru('"' . PHP_BINARY . '" "' . $root . 'tools/gen_icons.php"');
}

echo "DONE sdk=$sdk\njdk=$jdk17\n";
$mysqli->close();
