<?php
/**
 * 安装 Gradle 到 tools/appbuild/gradle 并生成 wrapper（若缺失）
 */
$root = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
$tools = $root . 'tools' . DIRECTORY_SEPARATOR . 'appbuild' . DIRECTORY_SEPARATOR;
$gradleVer = '8.2';
$gradleHome = $tools . 'gradle-' . $gradleVer;
$zip = $tools . 'gradle-' . $gradleVer . '-bin.zip';
$url = 'https://services.gradle.org/distributions/gradle-' . $gradleVer . '-bin.zip';

if (!is_dir($gradleHome)) {
    echo "Downloading Gradle $gradleVer ...\n";
    $data = @file_get_contents($url);
    if ($data === false) {
        // try curl
        $ch = curl_init($url);
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 300));
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300 || !$data) {
            fwrite(STDERR, "download failed\n");
            exit(1);
        }
    }
    file_put_contents($zip, $data);
    echo "Extracting...\n";
    $zipArchive = new ZipArchive();
    if ($zipArchive->open($zip) !== true) {
        fwrite(STDERR, "zip open failed\n");
        exit(1);
    }
    $zipArchive->extractTo($tools);
    $zipArchive->close();
    @unlink($zip);
}
$gradleBat = $gradleHome . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'gradle.bat';
if (!is_file($gradleBat)) {
    fwrite(STDERR, "gradle.bat missing\n");
    exit(1);
}
echo "Gradle ready: $gradleBat\n";

// 写入路径供 worker 使用
$envFile = $tools . 'env.local.php';
$env = is_file($envFile) ? include $envFile : array();
if (!is_array($env)) $env = array();
$env['GRADLE_BIN'] = $gradleBat;
file_put_contents($envFile, "<?php\nreturn " . var_export($env, true) . ";\n");
echo "updated env.local.php GRADLE_BIN\n";
