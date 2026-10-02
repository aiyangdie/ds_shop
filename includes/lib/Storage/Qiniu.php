<?php
namespace lib\Storage;

/**
 * 七牛云表单上传（简易）
 */
class Qiniu implements Driver
{
    private $accessKey;
    private $secretKey;
    private $bucket;
    private $domain;
    private $zone;

    public function __construct($cfg)
    {
        $this->accessKey = isset($cfg['access_key']) ? $cfg['access_key'] : (isset($cfg['secret_id']) ? $cfg['secret_id'] : '');
        $this->secretKey = isset($cfg['secret_key']) ? $cfg['secret_key'] : '';
        $this->bucket = isset($cfg['bucket']) ? $cfg['bucket'] : '';
        $this->domain = isset($cfg['cdn']) ? rtrim($cfg['cdn'], '/') : (isset($cfg['domain']) ? rtrim($cfg['domain'], '/') : '');
        $this->zone = isset($cfg['region']) ? $cfg['region'] : 'z0';
    }

    private function uploadHost()
    {
        $map = array(
            'z0' => 'https://upload.qiniup.com',
            'z1' => 'https://upload-z1.qiniup.com',
            'z2' => 'https://upload-z2.qiniup.com',
            'na0' => 'https://upload-na0.qiniup.com',
            'as0' => 'https://upload-as0.qiniup.com',
        );
        return isset($map[$this->zone]) ? $map[$this->zone] : $map['z0'];
    }

    private function urlsafeB64($data)
    {
        return str_replace(array('+', '/'), array('-', '_'), base64_encode($data));
    }

    private function makeToken($key)
    {
        $deadline = time() + 3600;
        $policy = json_encode(array(
            'scope' => $this->bucket . ':' . $key,
            'deadline' => $deadline,
        ));
        $encoded = $this->urlsafeB64($policy);
        $sign = hash_hmac('sha1', $encoded, $this->secretKey, true);
        return $this->accessKey . ':' . $this->urlsafeB64($sign) . ':' . $encoded;
    }

    public function put($localPath, $objectKey, $mime = 'application/octet-stream')
    {
        if ($this->accessKey === '' || $this->secretKey === '' || $this->bucket === '' || $this->domain === '') {
            return array('ok' => false, 'error' => '七牛配置不完整（需 AK/SK/Bucket/域名）');
        }
        $objectKey = ltrim(str_replace('\\', '/', $objectKey), '/');
        $token = $this->makeToken($objectKey);
        $postUrl = $this->uploadHost();

        $cfile = class_exists('CURLFile') ? new \CURLFile($localPath, $mime, basename($objectKey)) : '@' . $localPath;
        $fields = array(
            'token' => $token,
            'key' => $objectKey,
            'file' => $cfile,
        );
        $ch = curl_init($postUrl);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ));
        $resp = curl_exec($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $err = curl_error($ch);
        curl_close($ch);
        if ($code < 200 || $code >= 300) {
            return array('ok' => false, 'error' => '七牛上传失败 HTTP' . $code . ' ' . mb_substr(strval($resp), 0, 120));
        }
        $size = @filesize($localPath);
        $url = $this->domain . '/' . $objectKey;
        return array('ok' => true, 'url' => $url, 'size' => intval($size));
    }
}
