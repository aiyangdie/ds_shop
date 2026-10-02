<?php
/**
 * App 打包 Worker（CLI，不依赖混淆 common.php）
 *
 * 用法：
 *   php tools/appbuild/worker.php --once
 *   php tools/appbuild/worker.php --id=123
 *   php tools/appbuild/worker.php --demo
 *   php tools/appbuild/worker.php --cleanup
 *   php tools/appbuild/worker.php --build-demo1   # 直接编 demo1 验证 SDK
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
chdir($root);

$opts = array(
    'once' => false,
    'prepare_only' => false,
    'cleanup' => false,
    'demo' => false,
    'build_demo1' => false,
    'id' => 0,
    'limit' => 1,
);
foreach ($argv as $i => $a) {
    if ($i === 0) continue;
    if ($a === '--once') $opts['once'] = true;
    if ($a === '--prepare-only') $opts['prepare_only'] = true;
    if ($a === '--cleanup') $opts['cleanup'] = true;
    if ($a === '--demo') $opts['demo'] = true;
    if ($a === '--build-demo1') $opts['build_demo1'] = true;
    if (strpos($a, '--id=') === 0) $opts['id'] = intval(substr($a, 5));
    if (strpos($a, '--limit=') === 0) $opts['limit'] = max(1, intval(substr($a, 8)));
}
if ($opts['once']) $opts['limit'] = 1;

$envLocal = __DIR__ . DIRECTORY_SEPARATOR . 'env.local.php';
$ENV = is_file($envLocal) ? include $envLocal : array();
if (!is_array($ENV)) $ENV = array();

applyBuildEnv($ENV);

if (!empty($opts['demo'])) {
    demoPrepare(pathFactory($root), $root);
    exit(0);
}

if (!empty($opts['build_demo1'])) {
    $demoDir = $root . 'tools/appbuild/jobs/demo1/project';
    if (!is_dir($demoDir)) {
        demoPrepare(pathFactory($root), $root);
    }
    $ok = buildProjectDir($demoDir, $root . 'assets/uploads/apps/demo1.apk', $ENV);
    exit($ok ? 0 : 2);
}

// DB 模式
require $root . 'config.php';
$mysqli = openDb($dbconfig);
$conf = loadAppConf($mysqli, $dbconfig['dbqz']);
$factory = new CliAppStore($mysqli, $dbconfig['dbqz'], $root, $conf);

if (!empty($opts['cleanup'])) {
    echo "cleanup touched=" . $factory->cleanup(30) . "\n";
    exit(0);
}

if ($opts['id'] > 0) {
    $row = $factory->get($opts['id']);
    if (!$row) {
        fwrite(STDERR, "job not found\n");
        exit(1);
    }
    exit(processJob($factory, $row, $opts['prepare_only'], $root, $conf, $ENV) ? 0 : 2);
}

$list = $factory->listQueued($opts['limit']);
if (!$list) {
    echo "queue empty\n";
    exit(0);
}
$fail = 0;
foreach ($list as $row) {
    echo "build #{$row['id']} {$row['package']} ...\n";
    if (!processJob($factory, $row, $opts['prepare_only'], $root, $conf, $ENV)) $fail++;
}
exit($fail > 0 ? 2 : 0);

function applyBuildEnv($ENV)
{
    if (!empty($ENV['JAVA_HOME']) && is_dir($ENV['JAVA_HOME'])) {
        putenv('JAVA_HOME=' . $ENV['JAVA_HOME']);
        putenv('PATH=' . $ENV['JAVA_HOME'] . DIRECTORY_SEPARATOR . 'bin' . PATH_SEPARATOR . getenv('PATH'));
    }
    $sdk = !empty($ENV['ANDROID_SDK_DIR']) ? $ENV['ANDROID_SDK_DIR'] : '';
    if ($sdk !== '' && is_dir($sdk)) {
        putenv('ANDROID_HOME=' . $sdk);
        putenv('ANDROID_SDK_ROOT=' . $sdk);
    }
}

function openDb($dbconfig)
{
    $m = @new mysqli($dbconfig['host'], $dbconfig['user'], $dbconfig['pwd'], $dbconfig['dbname'], intval($dbconfig['port']));
    if ($m->connect_errno) {
        fwrite(STDERR, 'DB fail: ' . $m->connect_error . "\n");
        exit(1);
    }
    $m->set_charset('utf8mb4');
    return $m;
}

function loadAppConf(mysqli $m, $qz)
{
    $table = $qz . '_config';
    $conf = array();
    $rs = $m->query("SELECT k,v FROM `$table` WHERE k LIKE 'appcreate_%' OR k LIKE 'ai_storage_%' OR k='localurl'");
    if ($rs) {
        while ($row = $rs->fetch_assoc()) {
            $conf[$row['k']] = $row['v'];
        }
    }
    return $conf;
}

function pathFactory($root)
{
    return new class($root) {
        private $root;
        public function __construct($root) { $this->root = $root; }
        public function templateDir() { return $this->root . 'tools' . DIRECTORY_SEPARATOR . 'appshell'; }
        public function jobsDir() {
            $d = $this->root . 'tools' . DIRECTORY_SEPARATOR . 'appbuild' . DIRECTORY_SEPARATOR . 'jobs';
            if (!is_dir($d)) @mkdir($d, 0755, true);
            return $d;
        }
        public function outputDir() {
            $d = $this->root . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'apps';
            if (!is_dir($d)) @mkdir($d, 0755, true);
            return $d;
        }
    };
}

class CliAppStore
{
    private $db;
    private $table;
    private $root;
    private $conf;

    public function __construct(mysqli $db, $qz, $root, $conf)
    {
        $this->db = $db;
        $this->table = $qz . '_apps';
        $this->root = $root;
        $this->conf = $conf;
    }

    public function templateDir() { return $this->root . 'tools/appshell'; }
    public function jobsDir() {
        $d = $this->root . 'tools/appbuild/jobs';
        if (!is_dir($d)) @mkdir($d, 0755, true);
        return $d;
    }
    public function outputDir() {
        $d = $this->root . 'assets/uploads/apps';
        if (!is_dir($d)) @mkdir($d, 0755, true);
        return $d;
    }

    public function get($id)
    {
        $id = intval($id);
        $rs = $this->db->query("SELECT * FROM `{$this->table}` WHERE id=$id LIMIT 1");
        return $rs ? $rs->fetch_assoc() : null;
    }

    public function listQueued($limit)
    {
        $limit = intval($limit);
        $rs = $this->db->query("SELECT * FROM `{$this->table}` WHERE build_status=0 AND status=2 ORDER BY id ASC LIMIT $limit");
        $out = array();
        if ($rs) while ($r = $rs->fetch_assoc()) $out[] = $r;
        return $out;
    }

    public function markBuilding($id)
    {
        $id = intval($id);
        $now = date('Y-m-d H:i:s');
        $this->db->query("UPDATE `{$this->table}` SET build_status=1,status=2,progress=10,updatetime='$now' WHERE id=$id");
    }

    public function markProgress($id, $progress, $log = '')
    {
        $id = intval($id);
        $progress = max(0, min(100, intval($progress)));
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE `{$this->table}` SET progress=$progress,updatetime='$now'";
        if ($log !== '') {
            $log = $this->db->real_escape_string(mb_substr($log, -4000));
            $sql .= ",build_log=CONCAT(IFNULL(build_log,''),'$log')";
        }
        $sql .= " WHERE id=$id";
        $this->db->query($sql);
    }

    public function markSuccess($id, $androidUrl, $iconUrl = '', $extra = array())
    {
        $id = intval($id);
        $now = date('Y-m-d H:i:s');
        $u = $this->db->real_escape_string($androidUrl);
        $sql = "UPDATE `{$this->table}` SET build_status=2,status=1,progress=100,error=NULL,android_url='$u',updatetime='$now'";
        if ($iconUrl !== '') {
            $sql .= ",icon='" . $this->db->real_escape_string($iconUrl) . "'";
        }
        if (isset($extra['file_size'])) {
            $sql .= ",file_size=" . intval($extra['file_size']);
        }
        if (!empty($extra['storage_driver'])) {
            $sql .= ",storage_driver='" . $this->db->real_escape_string(substr($extra['storage_driver'], 0, 16)) . "'";
        }
        $sql .= " WHERE id=$id";
        $this->db->query($sql);
    }

    public function markFailed($id, $error, $log = '')
    {
        $id = intval($id);
        $now = date('Y-m-d H:i:s');
        $e = $this->db->real_escape_string(mb_substr($error, 0, 480));
        $sql = "UPDATE `{$this->table}` SET build_status=3,status=0,error='$e',updatetime='$now'";
        if ($log !== '') {
            $sql .= ",build_log=CONCAT(IFNULL(build_log,''),'" . $this->db->real_escape_string(mb_substr($log, -2000)) . "')";
        }
        $sql .= " WHERE id=$id";
        $this->db->query($sql);
    }

    public function cleanup($days = 30)
    {
        $days = max(1, intval($days));
        $cut = date('Y-m-d H:i:s', time() - $days * 86400);
        $n = 0;
        $rs = $this->db->query("SELECT id FROM `{$this->table}` WHERE (build_status=3 OR status=0) AND updatetime<'$cut' LIMIT 200");
        if ($rs) {
            while ($r = $rs->fetch_assoc()) {
                rrmdir($this->jobsDir() . DIRECTORY_SEPARATOR . $r['id']);
                $n++;
            }
        }
        return $n;
    }
}

function demoPrepare($factory, $root)
{
    $template = $factory->templateDir();
    if (!is_dir($template . '/app')) {
        fwrite(STDERR, "template missing: $template\n");
        exit(1);
    }
    $demos = array(
        array('id' => 'demo1', 'name' => 'DemoA', 'package' => 'com.appshell.site.z1001demo', 'url' => 'https://a.example.com/', 'theme' => '#1f6feb'),
        array('id' => 'demo2', 'name' => 'DemoB', 'package' => 'com.appshell.site.z1002demo', 'url' => 'https://b.example.com/', 'theme' => '#e67e22'),
    );
    foreach ($demos as $d) {
        $jobDir = $factory->jobsDir() . DIRECTORY_SEPARATOR . $d['id'];
        $project = $jobDir . DIRECTORY_SEPARATOR . 'project';
        if (is_dir($project)) rrmdir($project);
        rcopy($template, $project);
        applyProjectConfig($project, $d['name'], $d['package'], $d['url'], $d['theme'], false, '', '', $root);
        if (!is_dir($jobDir)) mkdir($jobDir, 0755, true);
        file_put_contents($jobDir . '/job.json', json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo "prepared $jobDir package={$d['package']}\n";
    }
    echo "demo ok\n";
}

function processJob($factory, $row, $prepareOnly, $root, $conf, $ENV)
{
    $id = intval($row['id']);
    $factory->markBuilding($id);
    $factory->markProgress($id, 15, "\n[" . date('H:i:s') . "] start\n");

    $template = $factory->templateDir();
    if (!is_dir($template . DIRECTORY_SEPARATOR . 'app')) {
        $factory->markFailed($id, '壳模板不存在 tools/appshell');
        return false;
    }

    $jobDir = $factory->jobsDir() . DIRECTORY_SEPARATOR . $id;
    $projectDir = $jobDir . DIRECTORY_SEPARATOR . 'project';
    if (is_dir($projectDir)) rrmdir($projectDir);
    if (!is_dir($jobDir)) mkdir($jobDir, 0755, true);

    try {
        rcopy($template, $projectDir);
        $factory->markProgress($id, 30, "template copied\n");

        $name = strval($row['name']);
        $package = strval($row['package']);
        $url = !empty($row['url']) ? strval($row['url']) : ('http://' . $row['domain']);
        $theme = !empty($row['theme']) ? $row['theme'] : '#1f6feb';
        $nonav = !empty($row['nonav']);
        $iconPath = isset($row['icon_path']) ? strval($row['icon_path']) : '';
        $splashPath = isset($row['splash_path']) ? strval($row['splash_path']) : '';

        applyProjectConfig($projectDir, $name, $package, $url, $theme, $nonav, $iconPath, $splashPath, $root);
        writeLocalProperties($projectDir, $conf, $ENV);
        $factory->markProgress($id, 45, "config applied package=$package\n");

        if ($prepareOnly) {
            file_put_contents($jobDir . '/PREPARED.txt', "prepared only\n");
            $factory->markProgress($id, 90, "prepare-only\n");
            $factory->markFailed($id, '仅完成工程准备（--prepare-only）');
            return true;
        }

        $outName = 'app_' . $id . '_' . preg_replace('/[^a-z0-9._]/i', '', $package) . '.apk';
        $dest = $factory->outputDir() . DIRECTORY_SEPARATOR . $outName;
        $factory->markProgress($id, 55, "gradle assembleRelease\n");
        $ok = buildProjectDir($projectDir, $dest, $ENV, $jobDir);
        if (!$ok) {
            $log = is_file($jobDir . '/build.log') ? file_get_contents($jobDir . '/build.log') : '';
            $factory->markFailed($id, 'Gradle 构建失败', mb_substr($log, -2000));
            return false;
        }

        $factory->markProgress($id, 90, "publish apk\n");
        $publish = publishApk($dest, $outName, $root, $conf);
        $publicUrl = $publish['url'];
        $extra = array(
            'file_size' => isset($publish['size']) ? intval($publish['size']) : (int)@filesize($dest),
            'storage_driver' => isset($publish['driver']) ? $publish['driver'] : 'local',
        );
        $iconUrl = $iconPath !== '' ? ('/' . ltrim($iconPath, '/')) : '';
        $factory->markSuccess($id, $publicUrl, $iconUrl, $extra);
        $factory->markProgress($id, 100, "apk => $publicUrl driver={$extra['storage_driver']}\n");
        echo "success #$id => $publicUrl ({$extra['storage_driver']})\n";
        return true;
    } catch (Exception $e) {
        $factory->markFailed($id, $e->getMessage());
        return false;
    }
}

function writeLocalProperties($projectDir, $conf, $ENV)
{
    $sdk = '';
    if (!empty($conf['appcreate_sdk_dir'])) $sdk = $conf['appcreate_sdk_dir'];
    if ($sdk === '' && !empty($ENV['ANDROID_SDK_DIR'])) $sdk = $ENV['ANDROID_SDK_DIR'];
    if ($sdk === '' && getenv('ANDROID_HOME')) $sdk = getenv('ANDROID_HOME');
    if ($sdk === '' || !is_dir($sdk)) return false;
    $sdkProp = str_replace('\\', '/', $sdk);
    file_put_contents($projectDir . '/local.properties', 'sdk.dir=' . $sdkProp . "\n");
    return true;
}

/**
 * 发布 APK：优先走站点存储驱动（与附件同一套 cloud/local），失败则保留本地相对路径
 */
function publishApk($localApk, $outName, $root, $conf)
{
    $size = @filesize($localApk);
    $localUrl = '/assets/uploads/apps/' . $outName;

    $autoload = $root . 'includes/autoloader.php';
    if (is_file($autoload)) {
        require_once $autoload;
        if (class_exists('Autoloader')) {
            \Autoloader::register();
        }
    }
    // 手动 require 存储类（CLI 无 common）
    foreach (array('Driver.php', 'Local.php', 'Cos.php', 'Oss.php', 'Qiniu.php', 'Manager.php') as $f) {
        $p = $root . 'includes/lib/Storage/' . $f;
        if (is_file($p)) require_once $p;
    }

    if (!class_exists('\\lib\\Storage\\Manager')) {
        return array('url' => $localUrl, 'driver' => 'local', 'size' => intval($size));
    }

    $siteUrl = '';
    if (!empty($conf['localurl'])) {
        $siteUrl = rtrim($conf['localurl'], '/');
    }
    // 从 config 再读 localurl
    if ($siteUrl === '' && !empty($GLOBALS['dbconfig'])) {
        // ignore
    }

    try {
        $mgr = new \lib\Storage\Manager($conf, $root, $siteUrl);
        // App 存储策略：appcreate_storage=local|cloud，默认跟随 ai_storage_driver
        $useCloud = \lib\Storage\Manager::appUseCloud($conf);
        if (!$useCloud) {
            // 强制本地：仍用 Local 驱动保证 URL 一致
            $confLocal = $conf;
            $confLocal['ai_storage_driver'] = 'local';
            $mgr = new \lib\Storage\Manager($confLocal, $root, $siteUrl);
        }
        $res = $mgr->uploadAppApk($localApk, $outName);
        if (!empty($res['ok']) && !empty($res['url'])) {
            return array(
                'url' => $res['url'],
                'driver' => isset($res['driver']) ? $res['driver'] : $mgr->driverName(),
                'size' => isset($res['size']) ? intval($res['size']) : intval($size),
            );
        }
        echo "cloud publish fail: " . (isset($res['error']) ? $res['error'] : 'unknown') . " ; fallback local\n";
    } catch (Exception $e) {
        echo "cloud publish exception: " . $e->getMessage() . "\n";
    }
    return array('url' => $localUrl, 'driver' => 'local', 'size' => intval($size));
}

function buildProjectDir($projectDir, $destApk, $ENV, $jobDir = null)
{
    if ($jobDir === null) $jobDir = dirname($projectDir);
    writeLocalProperties($projectDir, array(), $ENV);

    if (!is_file($projectDir . '/local.properties')) {
        fwrite(STDERR, "local.properties missing (SDK?)\n");
        return false;
    }

    // Windows 中文用户目录会导致 Gradle agent 失败：拷到 ASCII 路径构建
    $buildDir = $projectDir;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $asciiRoot = 'C:\\appbuild\\work';
        if (!is_dir($asciiRoot)) @mkdir($asciiRoot, 0755, true);
        $asciiDir = $asciiRoot . DIRECTORY_SEPARATOR . 'job_' . md5($projectDir);
        if (is_dir($asciiDir)) rrmdir($asciiDir);
        rcopy($projectDir, $asciiDir);
        writeLocalProperties($asciiDir, array(), $ENV);
        $buildDir = $asciiDir;
    }

    $gradle = '';
    if (!empty($ENV['GRADLE_BIN']) && is_file($ENV['GRADLE_BIN'])) {
        $gradle = '"' . $ENV['GRADLE_BIN'] . '"';
    } elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        if (is_file('C:\\Gradle\\gradle-8.2\\bin\\gradle.bat')) {
            $gradle = '"C:\\Gradle\\gradle-8.2\\bin\\gradle.bat"';
        } elseif (is_file($buildDir . '/gradlew.bat')) {
            $gradle = 'gradlew.bat';
        } else {
            $gradle = 'gradle';
        }
    } else {
        if (is_file($buildDir . '/gradlew')) $gradle = './gradlew';
        else $gradle = 'gradle';
    }

    $cmd = $gradle . ' assembleRelease --no-daemon';
    $logFile = $jobDir . DIRECTORY_SEPARATOR . 'build.log';
    $cwd = getcwd();
    chdir($buildDir);
    $output = array();
    $code = 0;
    echo "RUN in $buildDir: $cmd\n";
    exec($cmd . ' 2>&1', $output, $code);
    chdir($cwd);
    $log = implode("\n", $output);
    file_put_contents($logFile, $log);
    echo mb_substr($log, -800) . "\n";
    if ($code !== 0) {
        fwrite(STDERR, "gradle exit=$code\n");
        return false;
    }
    $apk = findApk($buildDir);
    if (!$apk) {
        fwrite(STDERR, "apk not found\n");
        return false;
    }
    $destDir = dirname($destApk);
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    if (!@copy($apk, $destApk)) {
        fwrite(STDERR, "copy apk failed\n");
        return false;
    }
    echo "APK => $destApk\n";
    return true;
}

function applyProjectConfig($projectDir, $name, $package, $url, $theme, $nonav, $iconPath, $splashPath, $root)
{
    $cfg = array(
        'url' => $url,
        'theme' => $theme,
        'name' => $name,
        'hideNav' => (bool)$nonav,
        'version' => '1.0.0',
    );
    $assets = $projectDir . '/app/src/main/assets';
    if (!is_dir($assets)) mkdir($assets, 0755, true);
    file_put_contents($assets . '/config.json', json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    $safeName = htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    file_put_contents($projectDir . '/app/src/main/res/values/strings.xml', "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<resources>\n    <string name=\"app_name\">{$safeName}</string>\n</resources>\n");

    $safeTheme = preg_match('/^#[0-9A-Fa-f]{6}$/', $theme) ? $theme : '#1F6FEB';
    file_put_contents($projectDir . '/app/src/main/res/values/colors.xml', "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<resources>\n    <color name=\"colorPrimary\">{$safeTheme}</color>\n</resources>\n");

    $gradle = $projectDir . '/app/build.gradle';
    if (is_file($gradle)) {
        $g = file_get_contents($gradle);
        $g = preg_replace("/applicationId\\s+\"[^\"]+\"/", 'applicationId "' . $package . '"', $g);
        $g = preg_replace("/namespace\\s+'[^']+'/", "namespace '" . $package . "'", $g);
        file_put_contents($gradle, $g);
    }

    $manifest = $projectDir . '/app/src/main/AndroidManifest.xml';
    if (is_file($manifest)) {
        $m = file_get_contents($manifest);
        $m = preg_replace('/package=\"[^\"]+\"/', 'package="' . $package . '"', $m);
        file_put_contents($manifest, $m);
    }

    $javaRoot = $projectDir . '/app/src/main/java';
    $oldDir = $javaRoot . '/com/appshell/host';
    $parts = explode('.', $package);
    $newDir = $javaRoot . '/' . implode('/', $parts);
    if (is_dir($oldDir) && $package !== 'com.appshell.host') {
        if (!is_dir($newDir)) mkdir($newDir, 0755, true);
        $srcFile = $oldDir . '/MainActivity.java';
        if (is_file($srcFile)) {
            $code = file_get_contents($srcFile);
            $code = preg_replace('/^package\\s+[^;]+;/m', 'package ' . $package . ';', $code);
            file_put_contents($newDir . '/MainActivity.java', $code);
            @unlink($srcFile);
        }
    }

    if ($iconPath !== '') {
        $abs = $root . ltrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $iconPath), DIRECTORY_SEPARATOR);
        if (is_file($abs)) {
            foreach (array('mipmap-hdpi', 'mipmap-mdpi', 'mipmap-xhdpi', 'mipmap-xxhdpi') as $mip) {
                @copy($abs, $projectDir . "/app/src/main/res/$mip/ic_launcher.png");
            }
        }
    }
    if ($splashPath !== '') {
        $abs = $root . ltrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $splashPath), DIRECTORY_SEPARATOR);
        if (is_file($abs)) {
            @copy($abs, $projectDir . '/app/src/main/res/drawable/splash.png');
        }
    }
}

function findApk($projectDir)
{
    $candidates = array(
        $projectDir . '/app/build/outputs/apk/release/app-release.apk',
        $projectDir . '/app/build/outputs/apk/release/app-release-unsigned.apk',
        $projectDir . '/app/build/outputs/apk/debug/app-debug.apk',
    );
    foreach ($candidates as $c) {
        if (is_file($c)) return $c;
    }
    $dir = $projectDir . '/app/build/outputs/apk';
    if (!is_dir($dir)) return null;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $f) {
        if ($f->isFile() && preg_match('/\\.apk$/i', $f->getFilename())) {
            return $f->getPathname();
        }
    }
    return null;
}

function rcopy($src, $dst)
{
    $src = rtrim($src, '/\\');
    $dst = rtrim($dst, '/\\');
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    $dir = opendir($src);
    while (false !== ($file = readdir($dir))) {
        if ($file === '.' || $file === '..') continue;
        if ($file === '.gradle' || $file === 'build' || $file === '.idea') continue;
        $from = $src . DIRECTORY_SEPARATOR . $file;
        $to = $dst . DIRECTORY_SEPARATOR . $file;
        if (is_dir($from)) rcopy($from, $to);
        else copy($from, $to);
    }
    closedir($dir);
}

function rrmdir($dir)
{
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) rrmdir($path);
        else @unlink($path);
    }
    @rmdir($dir);
}
