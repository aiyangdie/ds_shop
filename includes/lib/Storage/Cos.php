<?php
namespace lib\Storage;

/**
 * 腾讯云 COS 简易 PutObject（REST + 签名，无需 SDK）
 */
class Cos implements Driver
{
    private $secretId;
    private $secretKey;
    private $bucket;
    private $region;
    private $cdn;
    private $scheme;

    public function __construct($cfg)
    {
        $this->secretId = isset($cfg['secret_id']) ? $cfg['secret_id'] : '';
        $this->secretKey = isset($cfg['secret_key']) ? $cfg['secret_key'] : '';
        $this->bucket = isset($cfg['bucket']) ? $cfg['bucket'] : '';
        $this->region = isset($cfg['region']) ? $cfg['region'] : 'ap-guangzhou';
        $this->cdn = isset($cfg['cdn']) ? rtrim($cfg['cdn'], '/') : '';
        $this->scheme = !empty($cfg['https']) ? 'https' : 'https';
    }

    public function put($localPath, $objectKey, $mime = 'application/octet-stream')
    {
        if ($this->secretId === '' || $this->secretKey === '' || $this->bucket === '') {
            return array('ok' => false, 'error' => 'COS 配置不完整');
        }
        $objectKey = ltrim(str_replace('\\', '/', $objectKey), '/');
        $host = $this->bucket . '.cos.' . $this->region . '.myqcloud.com';
        $url = $this->scheme . '://' . $host . '/' . $objectKey;
        $body = @file_get_contents($localPath);
        if ($body === false) return array('ok' => false, 'error' => '读取文件失败');
        $size = strlen($body);
        $now = time();
        $signTime = ($now - 60) . ';' . ($now + 3600);
        $httpString = "put\n/" . $objectKey . "\n\nhost=" . strtolower($host) . "\n";
        $sha1Http = sha1($httpString);
        $stringToSign = "sha1\n{$signTime}\n{$sha1Http}\n";
        $signKey = hash_hmac('sha1', $signTime, $this->secretKey);
        $signature = hash_hmac('sha1', $stringToSign, $signKey);
        $auth = 'q-sign-algorithm=sha1'
            . '&q-ak=' . $this->secretId
            . '&q-sign-time=' . $signTime
            . '&q-key-time=' . $signTime
            . '&q-header-list=host'
            . '&q-url-param-list='
            . '&q-signature=' . $signature;

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => array(
                'Host: ' . $host,
                'Content-Type: ' . $mime,
                'Content-Length: ' . $size,
                'Authorization: ' . $auth,
            ),
        ));
        $resp = curl_exec($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $err = curl_error($ch);
        curl_close($ch);
        if ($code < 200 || $code >= 300) {
            return array('ok' => false, 'error' => 'COS上传失败 HTTP' . $code . ($err ? ' ' . $err : '') . ' ' . mb_substr(strval($resp), 0, 120));
        }
        $public = $this->cdn !== '' ? ($this->cdn . '/' . $objectKey) : $url;
        return array('ok' => true, 'url' => $public, 'size' => $size);
    }
}
