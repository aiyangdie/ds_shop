<?php
namespace lib\Storage;

/**
 * 阿里云 OSS 简易 PutObject（REST + 签名）
 */
class Oss implements Driver
{
    private $accessKeyId;
    private $accessKeySecret;
    private $bucket;
    private $endpoint;
    private $cdn;

    public function __construct($cfg)
    {
        $this->accessKeyId = isset($cfg['access_key']) ? $cfg['access_key'] : (isset($cfg['secret_id']) ? $cfg['secret_id'] : '');
        $this->accessKeySecret = isset($cfg['secret_key']) ? $cfg['secret_key'] : '';
        $this->bucket = isset($cfg['bucket']) ? $cfg['bucket'] : '';
        $ep = isset($cfg['endpoint']) ? $cfg['endpoint'] : (isset($cfg['region']) ? $cfg['region'] : 'oss-cn-hangzhou.aliyuncs.com');
        $ep = preg_replace('#^https?://#', '', $ep);
        $this->endpoint = $ep;
        $this->cdn = isset($cfg['cdn']) ? rtrim($cfg['cdn'], '/') : '';
    }

    public function put($localPath, $objectKey, $mime = 'application/octet-stream')
    {
        if ($this->accessKeyId === '' || $this->accessKeySecret === '' || $this->bucket === '') {
            return array('ok' => false, 'error' => 'OSS 配置不完整');
        }
        $objectKey = ltrim(str_replace('\\', '/', $objectKey), '/');
        $host = $this->bucket . '.' . $this->endpoint;
        $url = 'https://' . $host . '/' . $objectKey;
        $body = @file_get_contents($localPath);
        if ($body === false) return array('ok' => false, 'error' => '读取文件失败');
        $size = strlen($body);
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $resource = '/' . $this->bucket . '/' . $objectKey;
        $canonical = "PUT\n\n{$mime}\n{$date}\n{$resource}";
        $signature = base64_encode(hash_hmac('sha1', $canonical, $this->accessKeySecret, true));
        $auth = 'OSS ' . $this->accessKeyId . ':' . $signature;

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => array(
                'Host: ' . $host,
                'Date: ' . $date,
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
            return array('ok' => false, 'error' => 'OSS上传失败 HTTP' . $code . ($err ? ' ' . $err : '') . ' ' . mb_substr(strval($resp), 0, 120));
        }
        $public = $this->cdn !== '' ? ($this->cdn . '/' . $objectKey) : $url;
        return array('ok' => true, 'url' => $public, 'size' => $size);
    }
}
