<?php
namespace lib;

/**
 * 自建安卓 App 打包工厂（local 模式）
 * 状态机：queued(0) → building(1) → success(2) | failed(3)
 * pre_apps.status 对外：1=可下载，2=生成中，0=失败/停用
 */
class AppFactory
{
    /** @var \lib\PdoHelper */
    private $DB;
    /** @var array */
    private $conf;
    /** @var string */
    private $root;
    public $msg = '';
    public $taskid = 0;
    public $fileid = '';

    const BS_QUEUED = 0;
    const BS_BUILDING = 1;
    const BS_SUCCESS = 2;
    const BS_FAILED = 3;

    public function __construct($DB, $conf = array())
    {
        $this->DB = $DB;
        $this->conf = is_array($conf) ? $conf : array();
        $this->root = defined('ROOT') ? ROOT : (dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR);
        $this->ensureSchema();
    }

    public static function isLocalMode($conf)
    {
        $mode = isset($conf['appcreate_mode']) ? strtolower(trim($conf['appcreate_mode'])) : 'remote';
        return $mode === 'local';
    }

    public function ensureSchema()
    {
        static $done = false;
        if ($done) return;
        $done = true;
        $cols = array(
            'url' => "url VARCHAR(255) DEFAULT NULL",
            'theme' => "theme VARCHAR(32) DEFAULT NULL",
            'icon_path' => "icon_path VARCHAR(255) DEFAULT NULL",
            'splash_path' => "splash_path VARCHAR(255) DEFAULT NULL",
            'progress' => "progress TINYINT UNSIGNED NOT NULL DEFAULT 0",
            'error' => "error VARCHAR(500) DEFAULT NULL",
            'build_log' => "build_log MEDIUMTEXT",
            'build_status' => "build_status TINYINT NOT NULL DEFAULT 0",
            'nonav' => "nonav TINYINT NOT NULL DEFAULT 0",
            'updatetime' => "updatetime DATETIME DEFAULT NULL",
            'file_size' => "file_size INT UNSIGNED NOT NULL DEFAULT 0",
            'storage_driver' => "storage_driver VARCHAR(16) DEFAULT NULL",
        );
        foreach ($cols as $name => $ddl) {
            try {
                $exist = $this->DB->getAll("SHOW COLUMNS FROM `pre_apps` LIKE '" . addslashes($name) . "'");
                if (!$exist || count($exist) === 0) {
                    $this->DB->exec("ALTER TABLE `pre_apps` ADD COLUMN $ddl");
                }
            } catch (\Exception $e) {
                // ignore
            }
        }
    }

    public function packagePrefix()
    {
        $p = isset($this->conf['appcreate_package_prefix']) ? trim($this->conf['appcreate_package_prefix']) : '';
        if ($p === '' || !preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/i', $p)) {
            $p = 'com.appshell.site';
        }
        return strtolower($p);
    }

    public function makePackage($zid, $domain = '')
    {
        $zid = max(1, intval($zid));
        $suffix = 'z' . $zid;
        if ($domain !== '') {
            $hash = substr(preg_replace('/[^a-z0-9]/', '', strtolower($domain)), 0, 12);
            if ($hash !== '') $suffix .= $hash;
        }
        return $this->packagePrefix() . '.' . $suffix;
    }

    /** 保存上传的图标/启动图，返回相对站点路径 */
    public function saveUpload($tmpPath, $origName, $kind = 'icon')
    {
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, array('jpg', 'jpeg', 'png'), true)) {
            $this->msg = '上传图片格式错误';
            return false;
        }
        $info = @getimagesize($tmpPath);
        if ($info === false) {
            $this->msg = '不是有效图片';
            return false;
        }
        $dir = $this->root . 'assets/uploads/apps';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = $kind . '_' . date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $dest = $dir . DIRECTORY_SEPARATOR . $name;
        if (!@move_uploaded_file($tmpPath, $dest) && !@copy($tmpPath, $dest)) {
            $this->msg = '保存图片失败';
            return false;
        }
        $this->fileid = 'assets/uploads/apps/' . $name;
        return $this->fileid;
    }

    /**
     * 提交本地打包任务
     * @return int|false app id
     */
    public function submit($opts)
    {
        $name = isset($opts['name']) ? trim(strval($opts['name'])) : '';
        $url = isset($opts['url']) ? trim(strval($opts['url'])) : '';
        $zid = isset($opts['zid']) ? intval($opts['zid']) : 1;
        $theme = isset($opts['theme']) ? trim(strval($opts['theme'])) : '#1f6feb';
        $iconPath = isset($opts['icon_path']) ? trim(strval($opts['icon_path'])) : '';
        $splashPath = isset($opts['splash_path']) ? trim(strval($opts['splash_path'])) : '';
        $nonav = !empty($opts['nonav']) ? 1 : 0;

        if ($name === '') {
            $this->msg = '应用名称不能为空';
            return false;
        }
        if ($url === '' || !strpos($url, '.')) {
            $this->msg = '应用网址不正确';
            return false;
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'http://' . $url;
        }
        $parts = parse_url($url);
        $domain = isset($parts['host']) ? $parts['host'] : '';
        if ($domain === '') {
            $this->msg = '无法解析域名';
            return false;
        }
        // 分站只能绑自己的域名
        if (!empty($opts['allowed_hosts']) && is_array($opts['allowed_hosts'])) {
            $okHost = false;
            foreach ($opts['allowed_hosts'] as $h) {
                if (strcasecmp($domain, $h) === 0) { $okHost = true; break; }
            }
            if (!$okHost) {
                $this->msg = '应用网址必须是本站域名';
                return false;
            }
        }

        $package = $this->makePackage($zid, $domain);
        $now = date('Y-m-d H:i:s');
        $iconUrl = $iconPath !== '' ? ('/' . ltrim($iconPath, '/')) : '/assets/uploads/apps/default_icon.png';

        $row = $this->DB->getRow("SELECT * FROM pre_apps WHERE domain='" . addslashes($domain) . "' LIMIT 1");
        if ($row) {
            $id = intval($row['id']);
            // 已在排队/构建中
            if (intval($row['status']) === 2 && in_array(intval($row['build_status']), array(self::BS_QUEUED, self::BS_BUILDING), true)) {
                $this->msg = '该域名已有生成任务进行中，请稍候在「我的生成」查看';
                $this->taskid = $id;
                return false;
            }
            $this->DB->exec("UPDATE pre_apps SET zid=:z,name=:n,url=:u,domain=:d,package=:p,theme=:t,icon=:ic,icon_path=:ip,splash_path=:sp,nonav=:nv,status=2,build_status=0,progress=0,error=NULL,android_url=NULL,updatetime=:now,addtime=IF(addtime IS NULL,:now2,addtime) WHERE id=:id", array(
                ':z' => $zid,
                ':n' => mb_substr($name, 0, 120),
                ':u' => $url,
                ':d' => $domain,
                ':p' => $package,
                ':t' => $theme,
                ':ic' => $iconUrl,
                ':ip' => $iconPath,
                ':sp' => $splashPath,
                ':nv' => $nonav,
                ':now' => $now,
                ':now2' => $now,
                ':id' => $id,
            ));
        } else {
            $this->DB->exec("INSERT INTO pre_apps (zid,taskid,domain,name,icon,package,android_url,ios_url,addtime,status,url,theme,icon_path,splash_path,progress,build_status,nonav,updatetime) VALUES (:z,0,:d,:n,:ic,:p,NULL,NULL,:a,2,:u,:t,:ip,:sp,0,0,:nv,:up)", array(
                ':z' => $zid,
                ':d' => $domain,
                ':n' => mb_substr($name, 0, 120),
                ':ic' => $iconUrl,
                ':p' => $package,
                ':a' => $now,
                ':u' => $url,
                ':t' => $theme,
                ':ip' => $iconPath,
                ':sp' => $splashPath,
                ':nv' => $nonav,
                ':up' => $now,
            ));
            $id = intval($this->DB->lastInsertId());
        }
        // taskid 对本地模式复用为 app id，便于兼容旧查询
        $this->DB->exec("UPDATE pre_apps SET taskid='$id' WHERE id='$id'");
        $this->taskid = $id;
        $this->writeJobMeta($id);
        return $id;
    }

    public function writeJobMeta($id)
    {
        $id = intval($id);
        $row = $this->DB->getRow("SELECT * FROM pre_apps WHERE id='$id' LIMIT 1");
        if (!$row) return;
        $jobDir = $this->jobsDir() . DIRECTORY_SEPARATOR . $id;
        if (!is_dir($jobDir)) @mkdir($jobDir, 0755, true);
        $meta = array(
            'id' => $id,
            'zid' => intval($row['zid']),
            'name' => $row['name'],
            'url' => isset($row['url']) ? $row['url'] : '',
            'domain' => $row['domain'],
            'package' => $row['package'],
            'theme' => isset($row['theme']) ? $row['theme'] : '#1f6feb',
            'icon_path' => isset($row['icon_path']) ? $row['icon_path'] : '',
            'splash_path' => isset($row['splash_path']) ? $row['splash_path'] : '',
            'nonav' => !empty($row['nonav']),
            'queued_at' => date('c'),
        );
        file_put_contents($jobDir . DIRECTORY_SEPARATOR . 'job.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function jobsDir()
    {
        $d = $this->root . 'tools/appbuild/jobs';
        if (!is_dir($d)) @mkdir($d, 0755, true);
        return $d;
    }

    public function templateDir()
    {
        return $this->root . 'tools/appshell';
    }

    public function outputDir()
    {
        $d = $this->root . 'assets/uploads/apps';
        if (!is_dir($d)) @mkdir($d, 0755, true);
        return $d;
    }

    public function queryById($id)
    {
        $id = intval($id);
        return $this->DB->getRow("SELECT * FROM pre_apps WHERE id='$id' LIMIT 1");
    }

    public function queryByDomain($domain)
    {
        $domain = addslashes($domain);
        return $this->DB->getRow("SELECT * FROM pre_apps WHERE domain='$domain' LIMIT 1");
    }

    public function queryForApi($domainOrId)
    {
        $row = is_numeric($domainOrId)
            ? $this->queryById(intval($domainOrId))
            : $this->queryByDomain($domainOrId);
        if (!$row) {
            $this->msg = '未找到生成任务';
            return null;
        }
        $bs = intval(isset($row['build_status']) ? $row['build_status'] : 0);
        $statusMap = array(
            self::BS_QUEUED => 0,
            self::BS_BUILDING => 0,
            self::BS_SUCCESS => 1,
            self::BS_FAILED => -1,
        );
        $remoteLike = array(
            'id' => intval($row['id']),
            'name' => $row['name'],
            'package' => $row['package'],
            'icon' => $row['icon'],
            'android_url' => $row['android_url'],
            'ios_url' => isset($row['ios_url']) ? $row['ios_url'] : '',
            'lanzou_url' => '',
            'status' => isset($statusMap[$bs]) ? $statusMap[$bs] : 0,
            'progress' => intval(isset($row['progress']) ? $row['progress'] : 0),
            'error' => isset($row['error']) ? $row['error'] : '',
            'build_status' => $bs,
            'created_at' => $row['addtime'],
            'updated_at' => isset($row['updatetime']) ? $row['updatetime'] : $row['addtime'],
            'finished_at' => ($bs === self::BS_SUCCESS && !empty($row['updatetime'])) ? $row['updatetime'] : '',
            'duration_sec' => self::calcDuration($row),
            'file_size' => isset($row['file_size']) ? intval($row['file_size']) : 0,
            'storage' => isset($row['storage_driver']) ? $row['storage_driver'] : '',
            'url' => isset($row['url']) ? $row['url'] : '',
        );
        return $remoteLike;
    }

    public static function calcDuration($row)
    {
        if (empty($row['addtime']) || empty($row['updatetime'])) return 0;
        $a = strtotime($row['addtime']);
        $b = strtotime($row['updatetime']);
        if (!$a || !$b || $b < $a) return 0;
        return intval($b - $a);
    }

    public function markBuilding($id)
    {
        $id = intval($id);
        $now = date('Y-m-d H:i:s');
        $this->DB->exec("UPDATE pre_apps SET build_status=1,status=2,progress=10,updatetime='$now' WHERE id='$id'");
    }

    public function markProgress($id, $progress, $logAppend = '')
    {
        $id = intval($id);
        $progress = max(0, min(100, intval($progress)));
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE pre_apps SET progress='$progress',updatetime='$now'";
        if ($logAppend !== '') {
            $logAppend = addslashes(mb_substr($logAppend, -4000));
            $sql .= ",build_log=CONCAT(IFNULL(build_log,''),'$logAppend')";
        }
        $sql .= " WHERE id='$id'";
        $this->DB->exec($sql);
    }

    public function markSuccess($id, $androidUrl, $iconUrl = '', $extra = array())
    {
        $id = intval($id);
        $now = date('Y-m-d H:i:s');
        $sets = "build_status=2,status=1,progress=100,error=NULL,android_url='" . addslashes($androidUrl) . "',updatetime='$now'";
        if ($iconUrl !== '') {
            $sets .= ",icon='" . addslashes($iconUrl) . "'";
        }
        if (isset($extra['file_size'])) {
            $sets .= ",file_size='" . intval($extra['file_size']) . "'";
        }
        if (!empty($extra['storage_driver'])) {
            $sets .= ",storage_driver='" . addslashes(mb_substr($extra['storage_driver'], 0, 16)) . "'";
        }
        $this->DB->exec("UPDATE pre_apps SET $sets WHERE id='$id'");
    }

    public function markFailed($id, $error, $logAppend = '')
    {
        $id = intval($id);
        $now = date('Y-m-d H:i:s');
        $error = mb_substr(strval($error), 0, 480);
        $sql = "UPDATE pre_apps SET build_status=3,status=0,error='" . addslashes($error) . "',updatetime='$now'";
        if ($logAppend !== '') {
            $sql .= ",build_log=CONCAT(IFNULL(build_log,''),'" . addslashes(mb_substr($logAppend, -4000)) . "')";
        }
        $sql .= " WHERE id='$id'";
        $this->DB->exec($sql);
    }

    /** 重新入队失败任务 */
    public function retry($id)
    {
        $id = intval($id);
        $row = $this->queryById($id);
        if (!$row) {
            $this->msg = '任务不存在';
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $this->DB->exec("UPDATE pre_apps SET build_status=0,status=2,progress=0,error=NULL,updatetime='$now' WHERE id='$id'");
        $this->writeJobMeta($id);
        $this->taskid = $id;
        return true;
    }

    /** 清理过期失败产物与过旧 job 目录 */
    public function cleanup($days = 30)
    {
        $days = max(1, intval($days));
        $cut = date('Y-m-d H:i:s', time() - $days * 86400);
        $n = 0;
        $rows = $this->DB->getAll("SELECT id,android_url FROM pre_apps WHERE (build_status=3 OR status=0) AND updatetime<'$cut' LIMIT 200");
        if (!$rows) $rows = array();
        foreach ($rows as $r) {
            $jobDir = $this->jobsDir() . DIRECTORY_SEPARATOR . intval($r['id']);
            $this->rrmdir($jobDir);
            $n++;
        }
        // 清理输出目录中无引用的旧 apk（仅匹配 app_*.apk）
        $out = $this->outputDir();
        foreach (glob($out . DIRECTORY_SEPARATOR . 'app_*.apk') ?: array() as $f) {
            if (filemtime($f) < time() - $days * 86400) {
                $base = basename($f);
                $used = $this->DB->getColumn("SELECT count(*) FROM pre_apps WHERE android_url LIKE '%" . addslashes($base) . "%'");
                if (intval($used) === 0) @unlink($f);
            }
        }
        return $n;
    }

    public function listQueued($limit = 5)
    {
        $limit = min(20, max(1, intval($limit)));
        return $this->DB->getAll("SELECT * FROM pre_apps WHERE build_status=0 AND status=2 ORDER BY id ASC LIMIT $limit");
    }

    public function countQueued()
    {
        return intval($this->DB->getColumn("SELECT count(*) FROM pre_apps WHERE build_status IN (0,1) AND status=2"));
    }

    private function rrmdir($dir)
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) $this->rrmdir($path);
            else @unlink($path);
        }
        @rmdir($dir);
    }
}
