<?php
namespace lib\Ai;

/**
 * OpenAI 兼容 Chat Completions 客户端（DeepSeek / 通义 / 智谱 / 硅基流动 / 自定义均可）
 */
class Client
{
    private $apiBase;
    private $apiKey;
    private $timeout;

    public function __construct($apiBase, $apiKey, $timeout = 120)
    {
        $this->apiBase = rtrim((string)$apiBase, '/');
        $this->apiKey = (string)$apiKey;
        $this->timeout = max(30, intval($timeout));
    }

    /**
     * @param array $payload chat/completions body
     * @return array decoded JSON
     * @throws \Exception
     */
    public function chat(array $payload)
    {
        if ($this->apiBase === '' || $this->apiKey === '') {
            throw new \Exception('请先在「AI模型配置」中填写 API Base 与 API Key');
        }
        $url = $this->apiBase . '/chat/completions';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new \Exception('请求体 JSON 编码失败');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json',
            ),
        ));
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);

        if ($errno) {
            throw new \Exception('模型请求失败: ' . $err);
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \Exception('模型返回非 JSON（HTTP ' . $code . '）: ' . mb_substr((string)$raw, 0, 300));
        }
        if ($code >= 400) {
            $msg = isset($data['error']['message']) ? $data['error']['message'] : (isset($data['message']) ? $data['message'] : $raw);
            throw new \Exception('模型接口错误 HTTP ' . $code . ': ' . $msg);
        }
        return $data;
    }

    /**
     * 简单连通性测试
     */
    public function ping($model)
    {
        return $this->chat(array(
            'model' => $model,
            'messages' => array(
                array('role' => 'user', 'content' => '请只回复：ok'),
            ),
            'max_tokens' => 16,
            'temperature' => 0,
        ));
    }
}
