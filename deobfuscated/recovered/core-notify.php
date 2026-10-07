<?php

/**
 * Recovered helpers: send_mail, sysmsg, sec_check (modes 3, 16, 19).
 * Verified against protected core via recording doubles / HTML capture.
 */

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
