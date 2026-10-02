<?php
namespace lib\Storage;

/**
 * 附件存储管理器：根据配置选择 local / cos / oss / qiniu
 */
class Manager
{
    /** @var array */
    private $conf;
    /** @var string */
    private $siteRoot;
    /** @var string */
    private $siteUrl;

    public function __construct($conf, $siteRoot = '', $siteUrl = '')
    {
        $this->conf = is_array($conf) ? $conf : array();
        $this->siteRoot = $siteRoot !== '' ? $siteRoot : (defined('ROOT') ? ROOT : dirname(dirname(dirname(__DIR__))) . '/');
        $this->siteUrl = $siteUrl !== '' ? rtrim($siteUrl, '/') : '';
    }

    public function driverName()
    {
        $d = isset($this->conf['ai_storage_driver']) ? strtolower(trim($this->conf['ai_storage_driver'])) : 'local';
        if (!in_array($d, array('local', 'cos', 'oss', 'qiniu'), true)) $d = 'local';
        return $d;
    }

    public function getDriver()
    {
        $name = $this->driverName();
        $cfg = $this->driverConfig($name);
        if ($name === 'cos') return new Cos($cfg);
        if ($name === 'oss') return new Oss($cfg);
        if ($name === 'qiniu') return new Qiniu($cfg);
        $root = $this->siteRoot . 'assets/uploads';
        $base = ($this->siteUrl !== '' ? $this->siteUrl : '') . '/assets/uploads';
        return new Local($root, $base);
    }

    public function driverConfig($name)
    {
        return array(
            'secret_id' => isset($this->conf['ai_storage_ak']) ? $this->conf['ai_storage_ak'] : '',
            'access_key' => isset($this->conf['ai_storage_ak']) ? $this->conf['ai_storage_ak'] : '',
            'secret_key' => isset($this->conf['ai_storage_sk']) ? $this->conf['ai_storage_sk'] : '',
            'bucket' => isset($this->conf['ai_storage_bucket']) ? $this->conf['ai_storage_bucket'] : '',
            'region' => isset($this->conf['ai_storage_region']) ? $this->conf['ai_storage_region'] : '',
            'endpoint' => isset($this->conf['ai_storage_endpoint']) ? $this->conf['ai_storage_endpoint'] : '',
            'cdn' => isset($this->conf['ai_storage_cdn']) ? $this->conf['ai_storage_cdn'] : '',
            'domain' => isset($this->conf['ai_storage_cdn']) ? $this->conf['ai_storage_cdn'] : '',
            'https' => true,
        );
    }

    /**
     * 上传客服图片
     * @return array{ok:bool,url?:string,error?:string,size?:int,key?:string,driver?:string}
     */
    public function uploadCsImage($tmpPath, $origName = '', $mime = '')
    {
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allow = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        if (!in_array($ext, $allow, true)) {
            // 尝试从 mime 推断
            $map = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp');
            if ($mime && isset($map[$mime])) $ext = $map[$mime];
            else $ext = 'jpg';
        }
        if (!in_array($ext, $allow, true)) {
            return array('ok' => false, 'error' => '仅支持 jpg/png/gif/webp');
        }
        if (!is_file($tmpPath)) {
            return array('ok' => false, 'error' => '临时文件不存在');
        }
        $size = @filesize($tmpPath);
        if ($size === false || $size <= 0) return array('ok' => false, 'error' => '空文件');
        if ($size > 5 * 1024 * 1024) return array('ok' => false, 'error' => '图片不能超过 5MB');

        // 简单图片头校验
        $info = @getimagesize($tmpPath);
        if ($info === false) return array('ok' => false, 'error' => '不是有效图片');

        $key = 'cs/' . date('Y/m') . '/' . date('d') . '_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 16) . '.' . $ext;
        $mime = $info['mime'] ? $info['mime'] : 'image/jpeg';
        $driver = $this->getDriver();
        $res = $driver->put($tmpPath, $key, $mime);
        if (!empty($res['ok'])) {
            $res['key'] = $key;
            $res['driver'] = $this->driverName();
        }
        return $res;
    }

    /**
     * 上传分站 App APK（复用同一存储驱动：local/cos/oss/qiniu）
     * @return array{ok:bool,url?:string,error?:string,size?:int,key?:string,driver?:string}
     */
    public function uploadAppApk($localPath, $objectName = '')
    {
        if (!is_file($localPath)) {
            return array('ok' => false, 'error' => 'APK 文件不存在');
        }
        $size = @filesize($localPath);
        if ($size === false || $size <= 0) {
            return array('ok' => false, 'error' => '空文件');
        }
        if ($size > 80 * 1024 * 1024) {
            return array('ok' => false, 'error' => 'APK 不能超过 80MB');
        }
        $ext = strtolower(pathinfo($objectName !== '' ? $objectName : $localPath, PATHINFO_EXTENSION));
        if ($ext !== 'apk') {
            $objectName = ($objectName !== '' ? preg_replace('/\.[^.]+$/', '', $objectName) : ('app_' . date('YmdHis'))) . '.apk';
        }
        $base = $objectName !== '' ? basename($objectName) : ('app_' . date('YmdHis') . '.apk');
        $base = preg_replace('/[^a-zA-Z0-9._-]/', '_', $base);
        $key = 'apps/' . date('Y/m') . '/' . $base;
        $driver = $this->getDriver();
        $res = $driver->put($localPath, $key, 'application/vnd.android.package-archive');
        if (!empty($res['ok'])) {
            $res['key'] = $key;
            $res['driver'] = $this->driverName();
            $res['size'] = intval($size);
        }
        return $res;
    }

    /** App 包是否走云存储（与附件同一套驱动配置） */
    public static function appUseCloud($conf)
    {
        $forced = isset($conf['appcreate_storage']) ? strtolower(trim($conf['appcreate_storage'])) : '';
        if ($forced === 'local') return false;
        if ($forced === 'cloud' || $forced === 'cos' || $forced === 'oss' || $forced === 'qiniu') return true;
        // 默认：附件驱动不是 local 则 App 也上云
        $d = isset($conf['ai_storage_driver']) ? strtolower(trim($conf['ai_storage_driver'])) : 'local';
        return in_array($d, array('cos', 'oss', 'qiniu'), true);
    }
}
