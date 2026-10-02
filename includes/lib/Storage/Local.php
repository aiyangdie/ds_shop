<?php
namespace lib\Storage;

class Local implements Driver
{
    private $root;
    private $publicBase;

    public function __construct($root, $publicBase)
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
        $this->publicBase = rtrim($publicBase, '/');
    }

    public function put($localPath, $objectKey, $mime = 'application/octet-stream')
    {
        $objectKey = ltrim(str_replace('\\', '/', $objectKey), '/');
        $dest = $this->root . '/' . $objectKey;
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!@copy($localPath, $dest) && !@rename($localPath, $dest)) {
            $data = @file_get_contents($localPath);
            if ($data === false || @file_put_contents($dest, $data) === false) {
                return array('ok' => false, 'error' => '本地保存失败');
            }
        }
        $size = @filesize($dest);
        $url = $this->publicBase . '/' . $objectKey;
        return array('ok' => true, 'url' => $url, 'size' => intval($size));
    }
}
