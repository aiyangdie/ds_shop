<?php

declare(strict_types=1);

/**
 * Parity checks for recovered send_mail / sysmsg / sec_check.
 * Uses mail stubs; does not send real email.
 */

$GLOBALS['mailLog'] = [];
$GLOBALS['mailStubFail'] = false;

require dirname(__DIR__, 2) . '/deobfuscated/stages/mail-stubs.php';
require dirname(__DIR__, 2) . '/deobfuscated/recovered/core-notify.php';

function assertTrue($cond, string $label): void
{
    if (!$cond) {
        throw new RuntimeException('Assertion failed: ' . $label);
    }
}

$conf = [
    'sitename' => '测试站',
    'mail_smtp' => 'smtp.example.test',
    'mail_port' => '465',
    'mail_name' => 'noreply@example.test',
    'mail_name2' => '发件人昵称',
    'mail_pwd' => 'secret-pass',
    'mail_apiuser' => 'api-user',
    'mail_apikey' => 'api-key',
    'mail_cloud' => 0,
];
$dbconfig = ['user' => 'dbu', 'pwd' => 'Aa1!bbbb'];

// SMTP 465 / ssl
$GLOBALS['mailLog'] = [];
assertTrue(send_mail('to@example.test', '主题', '<b>正文</b>') === true, 'smtp-465 returns true');
$props = [];
foreach ($GLOBALS['mailLog'] as $entry) {
    if (($entry['PHPMailer'] ?? '') === 'send') {
        $props = $entry['props_snapshot'];
    }
}
assertTrue(($props['SMTPSecure'] ?? null) === 'ssl', 'port 465 uses ssl');
assertTrue(($props['Port'] ?? null) === 465, 'port cast to int');
assertTrue(($props['Timeout'] ?? null) === 5, 'timeout 5');

// SMTP 587 / tls
$conf['mail_port'] = '587';
$GLOBALS['mailLog'] = [];
send_mail('to@example.test', '主题', '正文');
foreach ($GLOBALS['mailLog'] as $entry) {
    if (($entry['PHPMailer'] ?? '') === 'send') {
        assertTrue(($entry['props_snapshot']['SMTPSecure'] ?? null) === 'tls', 'port 587 uses tls');
    }
}

// SMTP 25 / AutoTLS false
$conf['mail_port'] = '25';
$GLOBALS['mailLog'] = [];
send_mail('to@example.test', '主题', '正文');
foreach ($GLOBALS['mailLog'] as $entry) {
    if (($entry['PHPMailer'] ?? '') === 'send') {
        assertTrue(($entry['props_snapshot']['SMTPAutoTLS'] ?? null) === false, 'port 25 disables AutoTLS');
        assertTrue(!isset($entry['props_snapshot']['SMTPSecure']), 'port 25 has no SMTPSecure');
    }
}

// cloud 1 Sendcloud
$conf['mail_cloud'] = 1;
$conf['mail_port'] = '465';
$GLOBALS['mailLog'] = [];
assertTrue(send_mail('to@example.test', '主题', '正文') === true, 'sendcloud true');
assertTrue($GLOBALS['mailLog'][0]['ctor'] === 'Sendcloud', 'sendcloud ctor');
assertTrue($GLOBALS['mailLog'][1]['from'] === '发件人昵称', 'sendcloud from=mail_name2');
assertTrue($GLOBALS['mailLog'][1]['from_name'] === '测试站', 'sendcloud from_name=sitename');

// cloud 2 Aliyun
$conf['mail_cloud'] = 2;
$GLOBALS['mailLog'] = [];
send_mail('to@example.test', '主题', '正文');
assertTrue($GLOBALS['mailLog'][0]['ctor'] === 'Aliyun', 'aliyun ctor');

// from-name override for SMTP
$conf['mail_cloud'] = 0;
$GLOBALS['mailLog'] = [];
send_mail('to@example.test', '主题', '正文', 'alias@example.test');
foreach ($GLOBALS['mailLog'] as $entry) {
    if (($entry['PHPMailer'] ?? '') === 'setFrom') {
        assertTrue($entry['args'] === ['noreply@example.test', 'alias@example.test'], 'from-name override');
    }
}

// sec_check cases
$conf = ['admin_user' => 'boss', 'admin_pwd' => '123456'];
$dbconfig = ['user' => 'dbu', 'pwd' => 'Aa1!bbbb'];
$msgs = sec_check();
assertTrue(strpos(implode("\n", $msgs), '默认管理员密码') !== false, 'default admin pwd');

$conf = ['admin_user' => 'boss', 'admin_pwd' => 'abcde'];
$msgs = sec_check();
assertTrue(strpos(implode("\n", $msgs), '管理员密码过于简单') !== false, 'short admin pwd');

$conf = ['admin_user' => 'bossman', 'admin_pwd' => 'bossman'];
$msgs = sec_check();
assertTrue(strpos(implode("\n", $msgs), '用户名与密码相同') !== false, 'admin user=pwd');

$conf = ['admin_user' => 'boss', 'admin_pwd' => 'Aa1!bbbb', 'kfqq' => 'Aa1!bbbb'];
$msgs = sec_check();
assertTrue(strpos(implode("\n", $msgs), '管理员密码过于简单') !== false, 'kfqq as password');

$dbconfig = ['user' => 'dbu', 'pwd' => ''];
$conf = ['admin_user' => 'boss', 'admin_pwd' => 'Aa1!bbbb'];
$msgs = sec_check();
assertTrue(strpos(implode("\n", $msgs), '数据库密码过于简单') !== false, 'empty db pwd');

$dbconfig = ['user' => 'same', 'pwd' => 'sameuser1']; // len>=6 non-numeric, different
// actually same user=pwd with strong enough password
$dbconfig = ['user' => 'sameuser', 'pwd' => 'sameuser'];
$msgs = sec_check();
assertTrue(strpos(implode("\n", $msgs), '数据库用户名与密码相同') !== false, 'db user=pwd');

// sysmsg HTML
ob_start();
sysmsg('测试异常信息', false);
$html = ob_get_clean();
assertTrue(strpos($html, '站点提示信息') !== false, 'sysmsg title');
assertTrue(strpos($html, '测试异常信息') !== false, 'sysmsg body');

echo "core-notify parity checks passed\n";
