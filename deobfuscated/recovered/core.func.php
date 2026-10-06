<?php

/**
 * Readable replacement for includes/core.func.php.
 *
 * Assembled from deobfuscated/recovered/core-*.php.
 * Not wired into the live include path until parity checks pass.
 *
 * Recovered modes 1-24:
 * curl_get, shequ_get_curl, send_mail, send_wechat, getSetting, saveSetting,
 * doOrder, processOrder, do_curl, third_call, do_goods, changeUserMoney,
 * addPointRecord, rollbackPoint, log_result, sysmsg, batchSql, rm_dir,
 * sec_check, epay_check, pay_api, get_pay_api, merge_site_conf, ordername_replace
 */

// ---- from core-simple.php ----

function getSetting($key, $fromDatabase = false)
{
    global $CACHE, $DB;

    if ($fromDatabase) {
        return $DB->getColumn(
            'SELECT v FROM pre_config WHERE k=:key limit 1',
            [':key' => $key]
        );
    }

    // The protected implementation invokes the cache getter but does not
    // propagate its return value. Keep that observed behavior for parity.
    $CACHE->get($key);
    return null;
}

function saveSetting($key, $value)
{
    global $DB;

    return $DB->exec(
        'REPLACE INTO pre_config SET v=:value,k=:key',
        [':key' => $key, ':value' => $value]
    );
}

function epay_check($unused)
{
    return true;
}

function third_call($type, $config, $method, $arguments = [])
{
    $class = '\\plugins\\third_' . $type;
    if (!class_exists($class) || !method_exists($class, $method)) {
        return false;
    }

    $plugin = new $class($config);
    return call_user_func_array([$plugin, $method], $arguments);
}

function addPointRecord($siteId, $point = 0, $action = '提成', $remark = null)
{
    global $DB;

    $DB->exec(
        'INSERT INTO `pre_points` (`zid`, `action`, `point`, `bz`, `addtime`) VALUES (:zid, :action, :point, :bz, NOW())',
        [':zid' => $siteId, ':action' => $action, ':point' => $point, ':bz' => $remark]
    );
}

function changeUserMoney($siteId, $money, $increase = true, $action = null, $remark = null, $orderId = null)
{
    global $DB, $conf;

    if ($money <= 0) {
        return false;
    }

    $site = $DB->getRow("SELECT zid,rmb,rmbtc FROM pre_site WHERE zid='{$siteId}' LIMIT 1");
    if (!$site) {
        return false;
    }

    $newBalance = $increase ? $site['rmb'] + $money : $site['rmb'] - $money;
    $newWithdrawable = $site['rmbtc'];
    $status = 0;

    if ($increase && ($action === '提成' || $action === '奖励')) {
        if (!$conf['tixian_limit'] || ($conf['tixian_limit'] == 1 && !$conf['tixian_days'])) {
            $newWithdrawable += $money;
            $status = 1;
        }
    } elseif ($increase && $action === '退回') {
        $newWithdrawable += $money;
    } elseif (!$increase && $newWithdrawable > $newBalance) {
        $newWithdrawable = $newBalance;
    }

    $result = $DB->exec(
        "UPDATE `pre_site` SET `rmb`='{$newBalance}',`rmbtc`='{$newWithdrawable}' WHERE `zid`='{$siteId}'"
    );
    $DB->exec(
        'INSERT INTO `pre_points` (`zid`, `action`, `point`, `bz`, `addtime`, `orderid`, `status`) VALUES (:zid, :action, :point, :bz, NOW(), :orderid, :status)',
        [
            ':zid' => $siteId,
            ':action' => $action,
            ':point' => $money,
            ':bz' => $remark,
            ':orderid' => $orderId,
            ':status' => $status,
        ]
    );

    return $result;
}

function rollbackPoint($orderId)
{
    global $DB;

    $result = $DB->query(
        "SELECT A.id,A.zid,A.point,A.status,B.rmb,B.rmbtc FROM pre_points A LEFT JOIN pre_site B ON A.zid=B.zid WHERE A.orderid='{$orderId}' AND A.action='提成' LIMIT 2"
    );
    while ($row = $result->fetch()) {
        $set = '`rmb`=`rmb`-' . $row['point'];
        if ($row['status']) {
            $set .= ',`rmbtc`=`rmbtc`-' . $row['point'];
        }
        $DB->exec("UPDATE pre_site SET {$set} WHERE zid='{$row['zid']}'");
        $DB->exec("DELETE FROM pre_points WHERE id='{$row['id']}'");
    }

    return true;
}

function log_result($action, $parameters, $result, $status = 0)
{
    global $DB;

    if (is_array($result) && isset($result['code'], $result['id']) && (int) $result['code'] === 0) {
        $resultText = '下单成功!订单号:' . $result['id'];
    } elseif (is_array($result) && isset($result['message'])) {
        $resultText = $result['message'];
    } elseif (is_array($result) && isset($result['msg'])) {
        $resultText = $result['msg'];
    } else {
        $resultText = htmlspecialchars(json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    $DB->exec(
        'INSERT INTO `pre_logs` (`action`, `param`, `result`, `addtime`, `status`) VALUES (:action, :param, :res, NOW(), :status)',
        [':action' => $action, ':param' => $parameters, ':res' => $resultText, ':status' => $status]
    );
}

function batchSql($sql)
{
    global $DB;

    $count = 0;
    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }

        $DB->exec($statement);
        $count++;
    }

    return $count;
}

function rm_dir($directory)
{
    if (!is_dir($directory)) {
        return false;
    }

    $handle = opendir($directory);
    if ($handle === false) {
        return false;
    }

    while (($entry = readdir($handle)) !== false) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($path)) {
            rm_dir($path);
        } else {
            unlink($path);
        }
    }
    closedir($handle);

    return rmdir($directory);
}

function ordername_replace($template, $productName, $tradeNumber)
{
    return str_replace(
        ['[name]', '[order]', '[time]'],
        [$productName, $tradeNumber, time()],
        $template
    );
}

function merge_site_conf($configuration, $site)
{
    $always = [
        'zid', 'sitename', 'title', 'keywords', 'description',
        'ktfz_price', 'ktfz_price2', 'ktfz_domain', 'appurl',
    ];
    foreach ($always as $key) {
        if (array_key_exists($key, $site)) {
            $configuration[$key] = $site[$key];
        }
    }

    if (!empty($configuration['fenzhan_template']) || empty($configuration['template'])) {
        $configuration['template'] = array_key_exists('template', $site) ? $site['template'] : null;
    }
    if (!empty($configuration['fenzhan_edithtml'])) {
        foreach (['anounce', 'bottom', 'modal', 'alert'] as $key) {
            if (array_key_exists($key, $site)) {
                $configuration[$key] = $site[$key];
            }
        }
    }
    if (!empty($configuration['fenzhan_kfqq'])) {
        foreach (['kfqq', 'kfwx'] as $key) {
            if (array_key_exists($key, $site)) {
                $configuration[$key] = $site[$key];
            }
        }
    }

    return $configuration;
}

function pay_api($unused = true, $index = 1)
{
    global $conf;

    $key = $index == 1 ? 'epay_url' : 'epay_url' . $index;
    return array_key_exists($key, $conf) ? $conf[$key] : null;
}

function get_pay_api($paymentType)
{
    global $conf;

    if (!in_array($paymentType, ['alipay', 'qqpay', 'wxpay'], true)) {
        exit('ERROR');
    }

    $api = isset($conf[$paymentType . '_api']) ? (int) $conf[$paymentType . '_api'] : 0;
    if ($api === 2) {
        $index = 1;
    } elseif ($api === 8) {
        $index = 2;
    } elseif ($api === 9) {
        $index = 3;
    } else {
        exit('ERROR');
    }

    $suffix = $index === 1 ? '' : (string) $index;
    return [
        'url' => pay_api(true, $index),
        'pid' => isset($conf['epay_pid' . $suffix]) ? $conf['epay_pid' . $suffix] : null,
        'key' => isset($conf['epay_key' . $suffix]) ? $conf['epay_key' . $suffix] : null,
        'channel' => 'epay' . $index,
    ];
}

// ---- from core-network.php ----

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

    $url = '';
    if (!empty($conf['wechat_webhook'])) {
        $url = trim((string) $conf['wechat_webhook']);
    } elseif (!empty($conf['wechat_sckey']) && preg_match('#^https?://#i', (string) $conf['wechat_sckey'])) {
        $url = trim((string) $conf['wechat_sckey']);
    } elseif (!empty($conf['wechat_apptoken']) && preg_match('#^https?://#i', (string) $conf['wechat_apptoken'])) {
        $url = trim((string) $conf['wechat_apptoken']);
    }
    if ($url === '') {
        return null;
    }

    get_curl($url, http_build_query([
        'text' => $title,
        'desp' => $content,
        'title' => $title,
        'content' => $content,
    ]));
    return null;
}

/**
 * Build a supplier callback URL from placeholders, then GET it.
 *
 * Argument slots after $url map to placeholders (not standard curl options):
 * - $inputs: array/string providing [input]..[input5]
 * - $num/$name/$money/$price/$id: [num]/[name]/[money]/[price]/[id]
 * - [time] is always urlencode(time())
 */
function do_curl($url, $inputs, $num, $name, $money, $price, $id, $unused = null)
{
    $inputValues = [];
    for ($index = 0; $index < 5; $index++) {
        if (is_array($inputs)) {
            $inputValues[$index] = array_key_exists($index, $inputs) ? $inputs[$index] : '';
        } else {
            $asString = (string) $inputs;
            $inputValues[$index] = isset($asString[$index]) ? $asString[$index] : '';
        }
    }

    $search = [
        '[input]', '[input2]', '[input3]', '[input4]', '[input5]',
        '[num]', '[name]', '[money]', '[time]', '[id]', '[price]',
    ];
    $replace = [
        urlencode($inputValues[0]),
        urlencode($inputValues[1]),
        urlencode($inputValues[2]),
        urlencode($inputValues[3]),
        urlencode($inputValues[4]),
        urlencode($num),
        urlencode($name),
        urlencode($money),
        urlencode(time()),
        urlencode($id),
        urlencode($price),
    ];

    return get_curl(str_replace($search, $replace, $url));
}

// ---- from core-notify.php ----

function send_mail($to, $sub, $msg, $fromName = null)
{
    global $conf;

    if (isset($conf['mail_cloud']) && (int) $conf['mail_cloud'] === 1) {
        $client = new \lib\mail\Sendcloud($conf['mail_apiuser'], $conf['mail_apikey']);
        return $client->send($to, $sub, $msg, $conf['mail_name2'], $conf['sitename']);
    }

    if (isset($conf['mail_cloud']) && (int) $conf['mail_cloud'] === 2) {
        $client = new \lib\mail\Aliyun($conf['mail_apiuser'], $conf['mail_apikey']);
        return $client->send($to, $sub, $msg, $conf['mail_name2'], $conf['sitename']);
    }

    $mail = new \lib\mail\PHPMailer\PHPMailer(true);
    $mail->SMTPDebug = 0;
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 5;
    $mail->isSMTP();
    $mail->Host = $conf['mail_smtp'];
    $mail->SMTPAuth = true;
    $mail->Username = $conf['mail_name'];
    $mail->Password = $conf['mail_pwd'];

    $port = isset($conf['mail_port']) ? (int) $conf['mail_port'] : 25;
    if ($port === 465) {
        $mail->SMTPSecure = 'ssl';
    } elseif ($port === 587) {
        $mail->SMTPSecure = 'tls';
    } else {
        $mail->SMTPAutoTLS = false;
    }
    $mail->Port = $port;

    $displayName = $fromName !== null && $fromName !== '' ? $fromName : $conf['sitename'];
    $mail->setFrom($conf['mail_name'], $displayName);
    $mail->addAddress($to);
    $mail->addReplyTo($conf['mail_name'], $displayName);
    $mail->isHTML(true);
    $mail->Subject = $sub;
    $mail->Body = $msg;

    try {
        $mail->send();
        return true;
    } catch (Throwable $e) {
        return $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
    }
}

function sysmsg($message = '未知的异常', $exit = true)
{
    $html = <<<HTML
  
    <!DOCTYPE html>
    <html xmlns="http://www.w3.org/1999/xhtml" lang="zh-CN">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>站点提示信息</title>
        <style type="text/css">
html{background:#eee}body{background:#fff;color:#333;font-family:"微软雅黑","Microsoft YaHei",sans-serif;margin:2em auto;padding:1em 2em;max-width:700px;-webkit-box-shadow:10px 10px 10px rgba(0,0,0,.13);box-shadow:10px 10px 10px rgba(0,0,0,.13);opacity:.8}h1{border-bottom:1px solid #dadada;clear:both;color:#666;font:24px "微软雅黑","Microsoft YaHei",sans-serif;margin:30px 0 0 0;padding:0;padding-bottom:7px}#error-page{margin-top:50px}h3{text-align:center}#error-page p{font-size:9px;line-height:1.5;margin:25px 0 20px}#error-page code{font-family:Consolas,Monaco,monospace}ul li{margin-bottom:10px;font-size:9px}a{color:#21759B;text-decoration:none;margin-top:-10px}a:hover{color:#D54E21}.button{background:#f7f7f7;border:1px solid #ccc;color:#555;display:inline-block;text-decoration:none;font-size:9px;line-height:26px;height:28px;margin:0;padding:0 10px 1px;cursor:pointer;-webkit-border-radius:3px;-webkit-appearance:none;border-radius:3px;white-space:nowrap;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box;-webkit-box-shadow:inset 0 1px 0 #fff,0 1px 0 rgba(0,0,0,.08);box-shadow:inset 0 1px 0 #fff,0 1px 0 rgba(0,0,0,.08);vertical-align:top}.button.button-large{height:29px;line-height:28px;padding:0 12px}.button:focus,.button:hover{background:#fafafa;border-color:#999;color:#222}.button:focus{-webkit-box-shadow:1px 1px 1px rgba(0,0,0,.2);box-shadow:1px 1px 1px rgba(0,0,0,.2)}.button:active{background:#eee;border-color:#999;color:#333;-webkit-box-shadow:inset 0 2px 5px -3px rgba(0,0,0,.5);box-shadow:inset 0 2px 5px -3px rgba(0,0,0,.5)}table{table-layout:auto;border:1px solid #333;empty-cells:show;border-collapse:collapse}th{padding:4px;border:1px solid #333;overflow:hidden;color:#333;background:#eee}td{padding:4px;border:1px solid #333;overflow:hidden;color:#333}
        </style>
    </head>
    <body id="error-page">
        <h3>站点提示信息</h3>{$message}    </body>
    </html>
    
HTML;
    echo $html;
    if ($exit) {
        exit;
    }
}

function sec_check()
{
    global $conf, $dbconfig;

    $messages = [];

    $adminPassword = isset($conf['admin_pwd']) ? (string) $conf['admin_pwd'] : '';
    $adminUser = isset($conf['admin_user']) ? (string) $conf['admin_user'] : '';
    $kfqq = isset($conf['kfqq']) ? (string) $conf['kfqq'] : '';

    if ($adminPassword === '123456') {
        $messages[] = '<li class="list-group-item"><span class="btn-sm btn-danger">重要</span>&nbsp;请及时修改默认管理员密码 <a href="set.php?mod=account">点此进入修改</a></li>';
    } elseif (sec_password_is_weak($adminPassword) || ($kfqq !== '' && $adminPassword === $kfqq)) {
        $messages[] = '<li class="list-group-item"><span class="btn-sm btn-danger">重要</span>&nbsp;网站管理员密码过于简单，请不要使用较短的纯数字或自己的QQ号当做密码</li>';
    } elseif ($adminUser !== '' && $adminUser === $adminPassword) {
        $messages[] = '<li class="list-group-item"><span class="btn-sm btn-danger">重要</span>&nbsp;网站管理员用户名与密码相同，极易被黑客破解，请及时修改密码</li>';
    }

    $dbUser = is_array($dbconfig) && isset($dbconfig['user']) ? (string) $dbconfig['user'] : '';
    $dbPassword = is_array($dbconfig) && isset($dbconfig['pwd']) ? (string) $dbconfig['pwd'] : '';
    if (sec_password_is_weak($dbPassword)) {
        $messages[] = '<li class="list-group-item"><span class="btn-sm btn-danger">重要</span>&nbsp;当前主机的数据库密码过于简单，请不要使用较短的纯数字或自己的QQ号当做数据库密码</li>';
    } elseif ($dbUser !== '' && $dbUser === $dbPassword) {
        $messages[] = '<li class="list-group-item"><span class="btn-sm btn-danger">重要</span>&nbsp;当前主机的数据库用户名与密码相同，请及时修改数据库密码</li>';
    }

    if (version_compare(PHP_VERSION, '7.0.0', '<')) {
        $messages[] = '<li class="list-group-item"><span class="btn-sm btn-warning">提示</span>&nbsp;为了网站更好的性能，建议将当前主机PHP版本切换到PHP7.x或8.x</a></li>';
    }

    $root = defined('ROOT') ? ROOT : (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') . '/' : '');
    if ($root !== '') {
        $dangerous = [
            'readme.txt.zip', 'mini.php.zip', 'index.php.zip', 'cron.php.zip', 'config.php.zip',
            'api.php.zip', 'ajax.php.zip', 'archive.zip', 'wwwroot.zip', 'www.zip', 'web.zip',
            'bf.zip', 'beifen.zip', 'backup.zip', 'yuanma.zip', 'daishua.zip', 'ds.zip',
            'htdocs.zip', 'wz.zip', '1.zip', '2.zip', '123.zip',
        ];
        $foundArchive = false;
        foreach ($dangerous as $name) {
            if (is_file($root . $name)) {
                $foundArchive = true;
                break;
            }
        }
        if (!$foundArchive) {
            foreach (['*.zip', '*.7z', '*.rar'] as $pattern) {
                $matches = glob($root . $pattern);
                if (!empty($matches)) {
                    $foundArchive = true;
                    break;
                }
            }
        }
        if ($foundArchive) {
            $messages[] = '<li class="list-group-item"><span class="btn-sm btn-warning">提示</span>&nbsp;网站根目录存在压缩包文件，可能会被人恶意获取并泄露数据库密码</li>';
        }

        if (!empty(glob($root . 'daishua_release_*')) || !empty(glob($root . 'daishua_update_*'))) {
            // retained for parity with protected checks; no extra message in captured runs
        }
        if (!empty(glob($root . 'assets/img/*.php'))) {
            // retained for parity with protected checks; no extra message in captured runs
        }
    }

    return $messages;
}

function sec_password_is_weak($password)
{
    $password = (string) $password;
    if ($password === '') {
        return true;
    }
    if (is_numeric($password) && strlen($password) <= 10) {
        return true;
    }
    if (!is_numeric($password) && strlen($password) < 6) {
        return true;
    }
    return false;
}

// ---- from core-order.php ----

function do_goods($orderId, $url = null, $post = null)
{
    global $DB, $conf;

    if ($url) {
        return get_curl($url, $post);
    }

    $order = $DB->getRow("SELECT * FROM pre_orders WHERE id='{$orderId}' LIMIT 1");
    $tool = null;
    if ($order) {
        $tool = $DB->getRow("SELECT * FROM pre_tools WHERE tid='{$order['tid']}' LIMIT 1");
    }

    if (!$order || !$tool) {
        return '该商品未配置对接或自动发卡';
    }

    $isCurl = isset($tool['is_curl']) ? (int) $tool['is_curl'] : 0;

    if ($isCurl === 4) {
        return do_goods_faka($order, $tool);
    }

    if ($isCurl === 2) {
        return do_goods_shequ($order, $tool);
    }

    if ($isCurl === 1) {
        return do_goods_curl($order, $tool);
    }

    return '该商品未配置对接或自动发卡';
}

function do_goods_faka(array $order, array $tool)
{
    global $DB, $conf;

    $num = isset($order['value']) ? (int) $order['value'] : 1;
    if ($num < 1) {
        $num = 1;
    }

    $DB->beginTransaction();
    $result = $DB->query(
        "SELECT * FROM pre_faka WHERE tid='{$tool['tid']}' AND orderid=0 ORDER BY kid ASC LIMIT {$num} FOR UPDATE"
    );

    $cards = [];
    while ($result && ($row = $result->fetch())) {
        $cards[] = $row;
    }

    if (!$cards) {
        $DB->commit();
        $DB->exec("UPDATE `pre_orders` SET `status`='0',`djzt`='4' WHERE `id`='{$order['id']}'");
        return '卡密库存不足，发卡失败！';
    }

    $kmdata = '';
    $now = date('Y-m-d H:i:s');
    foreach ($cards as $card) {
        $DB->exec(
            "UPDATE `pre_faka` SET `orderid`='{$order['id']}',`usetime`='{$now}' WHERE `kid`='{$card['kid']}'"
        );
        if (isset($card['pw']) && $card['pw'] !== '' && $card['pw'] !== null) {
            $kmdata .= '卡号：' . $card['km'] . ' 密码：' . $card['pw'] . '<br/>';
        } else {
            $kmdata .= $card['km'] . '<br/>';
        }
    }

    $DB->commit();
    $DB->exec("UPDATE `pre_orders` SET `status`='1',`djzt`='3' WHERE `id`='{$order['id']}'");

    if (!empty($order['input']) && function_exists('checkEmail') && checkEmail($order['input'])) {
        $template = isset($conf['faka_mail']) ? $conf['faka_mail'] : '';
        $body = str_replace(
            ['[kmdata]', '[alert]', '[name]', '[date]', '[email]', '[domain]', '[sitename]'],
            [
                $kmdata,
                isset($tool['alert']) ? $tool['alert'] : '',
                isset($tool['name']) ? $tool['name'] : '',
                $now,
                $order['input'],
                isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
                isset($conf['sitename']) ? $conf['sitename'] : '',
            ],
            $template
        );
        $subject = (isset($conf['sitename']) ? $conf['sitename'] : '') . ' 卡密购买提醒';
        send_mail($order['input'], $subject, $body);
    }

    return '发卡成功！';
}

function do_goods_shequ(array $order, array $tool)
{
    global $DB;

    $shequId = isset($tool['shequ']) ? (int) $tool['shequ'] : 0;
    $shequ = $DB->getRow("SELECT * FROM pre_shequ WHERE id='{$shequId}' LIMIT 1");
    if (!$shequ) {
        return '未配置好网站对接信息';
    }

    $inputs = [
        isset($order['input']) ? $order['input'] : '',
        isset($order['input2']) ? $order['input2'] : '',
        isset($order['input3']) ? $order['input3'] : '',
        isset($order['input4']) ? $order['input4'] : '',
        isset($order['input5']) ? $order['input5'] : '',
    ];
    $num = isset($order['value']) ? $order['value'] : 1;
    $result = third_call(
        $shequ['type'],
        $shequ,
        'do_goods',
        [
            isset($tool['goods_id']) ? $tool['goods_id'] : 0,
            isset($tool['goods_type']) ? $tool['goods_type'] : 0,
            isset($tool['goods_param']) ? $tool['goods_param'] : '',
            $num,
            $inputs,
            isset($order['money']) ? $order['money'] : 0,
            isset($order['tradeno']) ? $order['tradeno'] : '',
            isset($tool['inputs']) ? $tool['inputs'] : '',
        ]
    );

    $param = $shequ['type'] . ':' . $shequ['id']
        . ' goods_id:' . (isset($tool['goods_id']) ? $tool['goods_id'] : '')
        . ' num:' . $num
        . ' data:' . http_build_query($inputs);

    if (is_array($result) && isset($result['code']) && (int) $result['code'] === 0) {
        $djorder = isset($result['id']) ? $result['id'] : null;
        $DB->exec(
            'UPDATE `pre_orders` SET `status`=:status,`djzt`=:djzt,`djorder`=:djorder,result=NULL WHERE `id`=:orderid',
            [
                ':status' => 1,
                ':djzt' => 1,
                ':djorder' => $djorder,
                ':orderid' => $order['id'],
            ]
        );
        $message = '下单成功!订单号:' . $djorder;
        log_result('社区对接', $param, $result, 0);
        return $message;
    }

    if (is_array($result) && (isset($result['message']) || isset($result['msg']))) {
        $error = isset($result['message']) ? $result['message'] : $result['msg'];
        log_result('社区对接', $param, $result, 0);
        return '下单失败：' . $error;
    }

    // Non-array / unexpected payload: still mark as docked without upstream order id.
    if (is_string($result) && $result !== '') {
        $DB->exec(
            'UPDATE `pre_orders` SET `status`=:status,`djzt`=:djzt,`djorder`=:djorder,result=NULL WHERE `id`=:orderid',
            [
                ':status' => 1,
                ':djzt' => 1,
                ':djorder' => null,
                ':orderid' => $order['id'],
            ]
        );
        log_result('社区对接', $param, $result, 0);
        return '下单成功!订单号:';
    }

    log_result('社区对接', $param, $result, 0);
    return '下单失败请查看日志';
}

function do_goods_curl(array $order, array $tool)
{
    $inputs = [
        isset($order['input']) ? $order['input'] : '',
        isset($order['input2']) ? $order['input2'] : '',
        isset($order['input3']) ? $order['input3'] : '',
        isset($order['input4']) ? $order['input4'] : '',
        isset($order['input5']) ? $order['input5'] : '',
    ];

    $curl = isset($tool['curl']) ? $tool['curl'] : '';
    $response = do_curl(
        $curl,
        $inputs,
        isset($order['value']) ? $order['value'] : 1,
        isset($tool['name']) ? $tool['name'] : '',
        isset($order['money']) ? $order['money'] : 0,
        isset($tool['price']) ? $tool['price'] : 0,
        isset($order['id']) ? $order['id'] : 0
    );

    $decoded = json_decode($response, true);
    if (is_array($decoded)) {
        $param = 'url:' . $curl . ' data:' . http_build_query($inputs);
        log_result('自动访问URL', $param, $decoded, 0);
    }

    // Protected core always returns an empty string for the is_curl=1 path.
    return '';
}

/**
 * Recovered doOrder (mode 7): create a goods order and optionally fulfill it.
 *
 * @param array $pay Paid trade row (or cart-derived row)
 * @param bool  $profit Whether to write site profit via lib\Price
 * @return int Inserted order id
 */
function doOrder($pay, $profit)
{
    global $DB, $conf, $date;

    $inputs = explode('|', isset($pay['input']) ? $pay['input'] : '');
    $inputColumns = [];
    for ($i = 0; $i < 5; $i++) {
        $inputColumns[$i] = array_key_exists($i, $inputs) ? $inputs[$i] : null;
    }

    $tid = isset($pay['tid']) ? $pay['tid'] : 0;
    $num = isset($pay['num']) ? $pay['num'] : 1;
    $tool = $DB->getRow("select * from pre_tools where tid='{$tid}' limit 1");

    $isCurl = ($tool && isset($tool['is_curl'])) ? (int) $tool['is_curl'] : 0;
    $djzt = ($isCurl === 1 || $isCurl === 2) ? 2 : 0;

    $cost = 0;
    if ($tool) {
        if (!empty($tool['prid'])) {
            $cost = $pay['money'] * 1;
        } else {
            $cost = (isset($tool['cost2']) ? $tool['cost2'] : 0) * $num;
        }
    }

    $DB->exec(
        'INSERT INTO `pre_orders` (`tid`,`zid`,`input`,`input2`,`input3`,`input4`,`input5`,`value`,`userid`,`addtime`,`tradeno`,`money`,`cost`,`status`,`djzt`) VALUES (:tid, :zid, :input, :input2, :input3, :input4, :input5, :value, :userid, :addtime, :tradeno, :money, :cost, :status, :djzt)',
        [
            ':tid' => $tid,
            ':zid' => isset($pay['zid']) ? $pay['zid'] : 0,
            ':input' => $inputColumns[0],
            ':input2' => $inputColumns[1],
            ':input3' => $inputColumns[2],
            ':input4' => $inputColumns[3],
            ':input5' => $inputColumns[4],
            ':value' => $num,
            ':userid' => isset($pay['userid']) ? $pay['userid'] : 0,
            ':addtime' => $date,
            ':tradeno' => isset($pay['trade_no']) ? $pay['trade_no'] : '',
            ':money' => isset($pay['money']) ? $pay['money'] : 0,
            ':cost' => $cost,
            ':status' => 0,
            ':djzt' => $djzt,
        ]
    );
    $orderId = $DB->lastInsertId();

    $DB->exec("UPDATE pre_tools SET sales=sales+{$num} WHERE tid='{$tid}'");
    if ($tool && !empty($tool['stock'])) {
        $DB->exec("UPDATE pre_tools SET stock=stock-{$num} WHERE tid='{$tid}'");
    }

    $notifyStatus = 0;
    $dockFail = null;
    $dockParam = '';

    if ($isCurl === 4) {
        do_order_faka($orderId, $tid, $num, $tool, $inputs);
    } elseif ($isCurl === 2 && empty($pay['blockdj'])) {
        $shequ = do_order_shequ($orderId, $tool, $inputs, $num, $pay, $dockParam, $dockFail);
        if ($shequ) {
            $notifyStatus = 1;
        }
    } elseif ($isCurl === 2 && !empty($pay['blockdj'])) {
        $DB->exec("UPDATE `pre_orders` SET `status`='1',`djzt`='0' WHERE `id`='{$orderId}'");
    } elseif ($isCurl === 1 && empty($pay['blockdj'])) {
        do_order_curl($orderId, $tool, $inputs, $num, $pay);
        $notifyStatus = 1;
    }

    if ($profit) {
        $price = new \lib\Price(isset($pay['zid']) ? $pay['zid'] : 0);
        $price->setToolInfo($tid, $tool);
        $price->setToolProfit(
            $tid,
            $num,
            $tool ? $tool['name'] : null,
            isset($pay['money']) ? $pay['money'] : 0,
            $orderId,
            isset($pay['userid']) ? $pay['userid'] : 0
        );
    }

    $toolName = $tool ? $tool['name'] : null;
    $inputName = $tool ? $tool['input'] : null;
    $inputNames = $tool ? $tool['inputs'] : null;
    $payType = array_key_exists('type', $pay) ? $pay['type'] : null;

    if ($dockFail !== null) {
        if (!empty($conf['message_duijie']) && class_exists('lib\\MessageSend', false)) {
            \lib\MessageSend::orderbuy_fail(
                $toolName,
                $inputName,
                $inputNames,
                $inputs,
                isset($pay['money']) ? $pay['money'] : 0,
                $num,
                $payType,
                0,
                $dockParam,
                $dockFail
            );
        }
    } elseif (!empty($conf['message_buy']) && class_exists('lib\\MessageSend', false)) {
        \lib\MessageSend::orderbuy(
            $toolName,
            $inputName,
            $inputNames,
            $inputs,
            isset($pay['money']) ? $pay['money'] : 0,
            $num,
            $payType,
            $notifyStatus
        );
    }

    return $orderId;
}

function do_order_faka($orderId, $tid, $num, $tool, array $inputs)
{
    global $DB, $conf;

    $DB->beginTransaction();
    $result = $DB->query(
        "SELECT * FROM pre_faka WHERE tid='{$tid}' AND orderid=0 ORDER BY kid ASC LIMIT {$num} FOR UPDATE"
    );

    $cards = [];
    while ($result && ($row = $result->fetch())) {
        $cards[] = $row;
    }

    if (!$cards) {
        $DB->commit();
        $DB->exec("UPDATE `pre_orders` SET `status`='0',`djzt`='4' WHERE `id`='{$orderId}'");
        return;
    }

    $kmdata = '';
    foreach ($cards as $card) {
        $DB->exec("UPDATE `pre_faka` SET `orderid`='{$orderId}',`usetime`=NOW() WHERE `kid`='{$card['kid']}'");
        if (isset($card['pw']) && $card['pw'] !== '' && $card['pw'] !== null) {
            $kmdata .= '卡号：' . $card['km'] . ' 密码：' . $card['pw'] . '<br/>';
        } else {
            $kmdata .= $card['km'] . '<br/>';
        }
    }

    $DB->commit();
    $DB->exec("UPDATE `pre_orders` SET `status`='1',`djzt`='3' WHERE `id`='{$orderId}'");

    $left = $DB->getColumn("SELECT count(*) FROM pre_faka WHERE tid='{$tid}' AND orderid=0");
    if (!empty($conf['message_fakastock']) && class_exists('lib\\MessageSend', false)) {
        \lib\MessageSend::faka_stock($tool ? $tool['name'] : '', $left);
    }

    $email = isset($inputs[0]) ? $inputs[0] : '';
    if ($email && function_exists('checkEmail') && checkEmail($email)) {
        $now = date('Y-m-d H:i:s');
        $template = isset($conf['faka_mail']) ? $conf['faka_mail'] : '';
        // Protected doOrder path substitutes [alert] from tools.desc (not alert).
        $body = str_replace(
            ['[kmdata]', '[alert]', '[name]', '[date]', '[email]', '[domain]', '[sitename]'],
            [
                $kmdata,
                ($tool && isset($tool['desc'])) ? $tool['desc'] : '',
                ($tool && isset($tool['name'])) ? $tool['name'] : '',
                $now,
                $email,
                isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
                isset($conf['sitename']) ? $conf['sitename'] : '',
            ],
            $template
        );
        $subject = (isset($conf['sitename']) ? $conf['sitename'] : '') . ' 卡密购买提醒';
        send_mail($email, $subject, $body);
    }
}

function do_order_shequ($orderId, $tool, array $inputs, $num, array $pay, &$dockParam, &$dockFail)
{
    global $DB;

    $shequId = isset($tool['shequ']) ? (int) $tool['shequ'] : 0;
    $shequ = $DB->getRow("SELECT * FROM pre_shequ WHERE id='{$shequId}' LIMIT 1");
    if (!$shequ) {
        $dockFail = '未配置好网站对接信息';
        $dockParam = '';
        return false;
    }

    $result = third_call(
        $shequ['type'],
        $shequ,
        'do_goods',
        [
            isset($tool['goods_id']) ? $tool['goods_id'] : 0,
            isset($tool['goods_type']) ? $tool['goods_type'] : 0,
            isset($tool['goods_param']) ? $tool['goods_param'] : '',
            $num,
            $inputs,
            isset($pay['money']) ? $pay['money'] : 0,
            isset($pay['trade_no']) ? $pay['trade_no'] : '',
            isset($tool['inputs']) ? $tool['inputs'] : '',
        ]
    );

    $dockParam = $shequ['type'] . ':' . $shequ['id']
        . ' goods_id:' . (isset($tool['goods_id']) ? $tool['goods_id'] : '')
        . ' num:' . $num
        . ' data:' . http_build_query($inputs);

    if (is_array($result) && isset($result['code']) && (int) $result['code'] === 0) {
        log_result('社区对接', $dockParam, $result, 0);
        if (!empty($result['km']) || !empty($result['card'])) {
            $km = isset($result['km']) ? $result['km'] : $result['card'];
            $pw = isset($result['pass']) ? $result['pass'] : (isset($result['pw']) ? $result['pw'] : '');
            $DB->exec(
                'INSERT INTO `pre_faka` (`tid`,`km`,`pw`,`orderid`,`addtime`,`usetime`) VALUES (:tid, :km, :pw, :orderid, NOW(), NOW())',
                [
                    ':tid' => $tool['tid'],
                    ':km' => $km,
                    ':pw' => $pw,
                    ':orderid' => $orderId,
                ]
            );
            $DB->exec(
                "UPDATE `pre_orders` SET `status`='1',`djzt`='3',`djorder`=:djorder WHERE `id`=:orderid",
                [':djorder' => isset($result['id']) ? $result['id'] : null, ':orderid' => $orderId]
            );
        } else {
            $DB->exec(
                "UPDATE `pre_orders` SET `status`='1',`djzt`='1',`djorder`=:djorder WHERE `id`=:orderid",
                [':djorder' => isset($result['id']) ? $result['id'] : null, ':orderid' => $orderId]
            );
        }
        return true;
    }

    log_result('社区对接', $dockParam, $result, 0);
    if (is_array($result) && (isset($result['message']) || isset($result['msg']))) {
        $dockFail = isset($result['message']) ? $result['message'] : $result['msg'];
    } else {
        $dockFail = is_string($result) ? $result : '下单失败请查看日志';
    }
    return false;
}

function do_order_curl($orderId, $tool, array $inputs, $num, array $pay)
{
    global $DB;

    $curl = isset($tool['curl']) ? $tool['curl'] : '';
    $response = do_curl(
        $curl,
        $inputs,
        $num,
        isset($tool['name']) ? $tool['name'] : '',
        isset($pay['money']) ? $pay['money'] : 0,
        isset($tool['price']) ? $tool['price'] : 0,
        $orderId
    );

    $decoded = json_decode($response, true);
    if (is_array($decoded)) {
        $param = 'url:' . $curl . ' data:' . http_build_query($inputs);
        log_result('自动访问URL', $param, $decoded, 0);
    }

    $DB->exec(
        "UPDATE `pre_orders` SET `status`='1',`djzt`='1',`djorder`=:djorder WHERE `id`=:orderid",
        [':djorder' => is_array($decoded) && isset($decoded['id']) ? $decoded['id'] : null, ':orderid' => $orderId]
    );
}

/**
 * Recovered processOrder (mode 8): handle paid trades for recharge, sites, cart, and goods.
 *
 * @param array $pay pre_pay row
 * @param bool  $profit Forwarded to doOrder
 * @return bool|int true for recharge/site, otherwise last goods order id
 */
function processOrder($pay, $profit = true)
{
    global $DB, $conf;

    $tid = isset($pay['tid']) ? (int) $pay['tid'] : 0;

    if ($tid === -1) {
        $zid = isset($pay['input']) ? (int) $pay['input'] : 0;
        $money = isset($pay['money']) ? $pay['money'] : 0;
        changeUserMoney($zid, $money, true, '充值', '你在线充值了' . $money . '元余额');
        if (!empty($conf['fenzhan_gift'])) {
            foreach (explode('|', $conf['fenzhan_gift']) as $rule) {
                $parts = explode(':', $rule);
                if (count($parts) < 2) {
                    continue;
                }
                if ($money >= $parts[0]) {
                    $gift = round($money * $parts[1] / 100, 2);
                    if ($gift > 0) {
                        changeUserMoney($zid, $gift, true, '赠送', '你参加多充多返活动获赠' . $gift . '元余额');
                    }
                    break;
                }
            }
        }
        return true;
    }

    if ($tid === -2) {
        return process_order_fenzhan($pay);
    }

    if ($tid === -3) {
        $ids = str_replace('|', ',', isset($pay['input']) ? $pay['input'] : '');
        $result = $DB->query("SELECT * FROM pre_cart WHERE id IN ({$ids}) AND status=1 ORDER BY id ASC");
        $lastId = null;
        while ($result && ($cart = $result->fetch())) {
            $DB->exec("UPDATE `pre_cart` SET `status`=2,`endtime`=NOW() WHERE `id`='{$cart['id']}'");
            $row = [
                'tid' => $cart['tid'],
                'zid' => $cart['zid'],
                'input' => $cart['input'],
                'num' => $cart['num'],
                'money' => $cart['money'],
                'trade_no' => isset($pay['trade_no']) ? $pay['trade_no'] : '',
                'userid' => $cart['userid'],
                'blockdj' => isset($cart['blockdj']) ? $cart['blockdj'] : 0,
            ];
            $lastId = doOrder($row, $profit);
        }
        return $lastId;
    }

    $orderId = doOrder($pay, $profit);
    process_order_invite($pay, $profit);
    return $orderId;
}

function process_order_fenzhan(array $pay)
{
    global $DB, $conf;

    $parts = explode('|', isset($pay['input']) ? $pay['input'] : '');
    $action = isset($parts[0]) ? $parts[0] : '';
    $gift = isset($conf['fenzhan_free']) ? $conf['fenzhan_free'] : 0;

    if ($action === 'update') {
        $zid = isset($parts[1]) ? (int) $parts[1] : 0;
        $DB->exec(
            'UPDATE `pre_site` SET `power`=:power,`domain`=:domain,`sitename`=:sitename,`title`=:title,`keywords`=:keywords,`description`=:description,`kfqq`=`qq`,`endtime`=:endtime WHERE `zid`=:zid',
            [
                ':power' => isset($parts[2]) ? (int) $parts[2] : 0,
                ':domain' => isset($parts[3]) ? $parts[3] : '',
                ':sitename' => isset($parts[4]) ? $parts[4] : '',
                ':title' => isset($conf['title']) ? $conf['title'] : '',
                ':keywords' => isset($conf['keywords']) ? $conf['keywords'] : '',
                ':description' => isset($conf['description']) ? $conf['description'] : '',
                ':endtime' => isset($parts[5]) ? $parts[5] : '',
                ':zid' => $zid,
            ]
        );
        $userid = isset($pay['userid']) ? $pay['userid'] : $zid;
        $DB->exec("UPDATE `pre_orders` SET `zid`='{$zid}' WHERE `userid`='{$userid}'");
        if ($gift) {
            addPointRecord($zid, $gift, '赠送', '你首次开通分站获赠' . $gift . '元余额');
        }
    } else {
        $power = isset($parts[1]) ? (int) $parts[1] : 1;
        $domain = isset($parts[2]) ? $parts[2] : '';
        $user = isset($parts[3]) ? $parts[3] : '';
        $pwd = isset($parts[4]) ? $parts[4] : '';
        $sitename = isset($parts[5]) ? $parts[5] : '';
        $qq = isset($parts[6]) ? $parts[6] : '';
        $endtime = isset($parts[7]) ? $parts[7] : '';
        $DB->exec(
            'INSERT INTO `pre_site` (`upzid`,`power`,`domain`,`domain2`,`user`,`pwd`,`rmb`,`qq`,`sitename`,`title`,`keywords`,`description`,`kfqq`,`addtime`,`endtime`,`status`) VALUES (:upzid, :power, :domain, NULL, :user, :pwd, :rmb, :qq, :sitename, :title, :keywords, :description, :kfqq, NOW(), :endtime, 1)',
            [
                ':upzid' => isset($pay['zid']) ? (int) $pay['zid'] : 0,
                ':power' => $power,
                ':domain' => $domain,
                ':user' => $user,
                ':pwd' => $pwd,
                ':rmb' => $gift,
                ':qq' => $qq,
                ':sitename' => $sitename,
                ':title' => isset($conf['title']) ? $conf['title'] : '',
                ':keywords' => isset($conf['keywords']) ? $conf['keywords'] : '',
                ':description' => isset($conf['description']) ? $conf['description'] : '',
                ':kfqq' => $qq,
                ':endtime' => $endtime,
            ]
        );
        $zid = $DB->lastInsertId();
        $DB->exec("UPDATE `pre_orders` SET `zid`='{$zid}' WHERE `userid`='{$zid}'");
        if ($gift) {
            addPointRecord($zid, $gift, '赠送', '你首次开通分站获赠' . $gift . '元余额');
        }
    }

    $parent = isset($pay['zid']) ? (int) $pay['zid'] : 0;
    if ($parent > 1) {
        $power = $DB->getColumn("SELECT power FROM pre_site WHERE zid='{$parent}' LIMIT 1");
        $costKey = ((int) $power === 2) ? 'fenzhan_cost2' : 'fenzhan_cost';
        $cost = isset($conf[$costKey]) ? $conf[$costKey] : 0;
        $commission = $pay['money'] - $cost;
        if ($commission > 0) {
            changeUserMoney($parent, $commission, true, '提成', '你网站的用户开通分站获得' . $commission . '元提成');
        }
    }

    return true;
}

function process_order_invite(array $pay, $profit)
{
    global $DB, $conf;

    if (empty($pay['inviteid']) || empty($conf['invite_tid'])) {
        return;
    }

    $log = $DB->getRow("SELECT * FROM `pre_invitelog` WHERE `id`='{$pay['inviteid']}'");
    if (!$log || !empty($log['status'])) {
        return;
    }

    $invite = $DB->getRow("SELECT * FROM `pre_invite` WHERE `id` = '{$log['iid']}'");
    if (!$invite) {
        return;
    }

    $shop = $DB->getRow("SELECT `tid`,`value`,`times` FROM `pre_inviteshop` WHERE `id` = '{$invite['nid']}'");
    if (!$shop) {
        return;
    }

    $extra = '';
    if (isset($shop['times']) && ($invite['count'] + 1) >= $shop['times']) {
        $extra = ',`status`=1';
    }
    $DB->exec('UPDATE `pre_invite` SET `count`=`count`+1' . $extra . ' WHERE `id`=:id', [':id' => $invite['id']]);
    $DB->exec('UPDATE `pre_invitelog` SET `status`=1 WHERE `id`=:id', [':id' => $log['id']]);

    $rewardPay = [
        'tid' => $shop['tid'],
        'zid' => isset($pay['zid']) ? $pay['zid'] : 0,
        'input' => isset($pay['input']) ? $pay['input'] : '',
        'num' => isset($shop['value']) ? $shop['value'] : 1,
        'money' => 0,
        'trade_no' => date('YmdHis') . rand(100, 999),
        'userid' => isset($pay['userid']) ? $pay['userid'] : 0,
        'type' => 'invite',
        'blockdj' => 0,
    ];
    doOrder($rewardPay, $profit);
}
