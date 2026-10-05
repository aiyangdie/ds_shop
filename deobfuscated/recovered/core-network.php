<?php

/** Readable network helpers recovered from protected core modes 1 and 2. */

function curl_get($url)
{
    $handle = curl_init($url);
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($handle, CURLOPT_TIMEOUT, 10);
    curl_setopt($handle, CURLOPT_HTTPHEADER, [
        'Accept: */*',
        'Accept-Encoding: gzip,deflate,sdch',
        'Accept-Language: zh-CN,zh;q=0.8',
        'Connection: close',
    ]);
    curl_setopt($handle, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; U; Android 4.4.1; zh-cn; R815T Build/JOP40D) AppleWebKit/533.1 (KHTML, like Gecko)Version/4.0 MQQBrowser/4.5 Mobile Safari/533.1');
    curl_setopt($handle, CURLOPT_ENCODING, 'gzip');
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($handle);
    curl_close($handle);

    return $result;
}

function shequ_get_curl($url, $post = 0, $referer = 0, $cookie = 0, $includeHeader = 0, $additionalHeaders = 0)
{
    global $conf;

    $handle = curl_init();
    curl_setopt($handle, CURLOPT_URL, $url);
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($handle, CURLOPT_TIMEOUT, 30);

    $headers = [
        'Accept: */*',
        'Accept-Encoding: gzip,deflate,sdch',
        'Accept-Language: zh-CN,zh;q=0.8',
        'Connection: close',
    ];
    if ($additionalHeaders) {
        $headers = array_merge($headers, $additionalHeaders);
    }
    curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);

    if ($post) {
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $post);
    }
    if ($includeHeader) {
        curl_setopt($handle, CURLOPT_HEADER, true);
    }
    if ($cookie) {
        curl_setopt($handle, CURLOPT_COOKIE, $cookie);
    }
    if ($referer) {
        curl_setopt($handle, CURLOPT_REFERER, $referer == 1 ? 'http://m.qzone.com/infocenter?g_f=' : $referer);
    }

    curl_setopt($handle, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36');

    if (!empty($conf['proxy']) && (int) $conf['proxy'] === 1 && !empty($conf['proxy_server'])) {
        $port = !empty($conf['proxy_port']) ? $conf['proxy_port'] : '80';
        curl_setopt($handle, CURLOPT_PROXY, $conf['proxy_server'] . ':' . $port);
        $type = isset($conf['proxy_type']) ? strtolower($conf['proxy_type']) : 'http';
        curl_setopt($handle, CURLOPT_PROXYTYPE, $type === 'socks5' || $type === 'sock5' ? CURLPROXY_SOCKS5 : ($type === 'socks4' || $type === 'sock4' ? CURLPROXY_SOCKS4 : CURLPROXY_HTTP));
        if (!empty($conf['proxy_user'])) {
            $credentials = $conf['proxy_user'];
            if (isset($conf['proxy_pwd']) && $conf['proxy_pwd'] !== '') {
                $credentials .= ':' . $conf['proxy_pwd'];
            }
            curl_setopt($handle, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
            curl_setopt($handle, CURLOPT_PROXYUSERPWD, $credentials);
        }
    }

    curl_setopt($handle, CURLOPT_ENCODING, 'gzip');
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($handle);
    curl_close($handle);

    return $result;
}

function send_wechat($title, $content)
{
    global $conf;

    if (empty($conf['wechat_api'])) {
        return null;
    }

    if ((int) $conf['wechat_api'] === 1) {
        $payload = json_encode([
            'appToken' => $conf['wechat_apptoken'],
            'content' => $content,
            'summary' => $title,
            'contentType' => 3,
            'uids' => [$conf['wechat_appuid']],
        ]);
        get_curl(
            'https://wxpusher.zjiecode.com/api/send/message',
            $payload,
            0,
            0,
            0,
            0,
            0,
            ['Content-Type: application/json; charset=UTF-8']
        );
        return null;
    }

    if (!empty($conf['wechat_sckey'])) {
        get_curl(
            'https://sc.ftqq.com/' . $conf['wechat_sckey'] . '.send',
            http_build_query(['text' => $title, 'desp' => $content])
        );
    }

    return null;
}
