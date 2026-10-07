<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = file_get_contents($root . '/deobfuscated/recovered/admin/set.php');
if ($src === false) {
    throw new RuntimeException('missing recovered set.php');
}

foreach ([
    "\$title = '后台管理'",
    "adminpermission('set', 1)",
    "\$mod === 'account_n'",
    "\$mod === 'captcha_n'",
    "\$mod === 'defend_n'",
    "\$mod === 'proxy_n'",
    "\$mod === 'copygg_n'",
    "\$mod === 'epay_n'",
    "\$mod === 'mailcon_reset'",
    "includes/base.php",
    "define('CC_Defender'",
    'ajax.php?act=set',
    'ajax.php?act=qdcount',
    'ajax.php?act=thirdloginunbind',
    'function saveSetting(obj)',
    'function changeTemplate(template)',
    'function thirdloginbind(type)',
    'fenzhan_domain',
    'sitename',
    'cron.php?do=daily&key=',
    'cron.php?do=pricejk&key=',
    'cron.php?do=updatestatus&key=',
    'api.php?act=siteinfo',
    '无法自己复制自己',
    'admin/pay.lock',
    'faka_mail',
    'alipay_api',
    'wxpay_appid',
    'captcha_open',
    'qiandao_reward',
    'login_qq',
    'anounce',
    'set.php?mod=upwxqrcode',
    '找到BOM并已自动去除',
    "\$mod === 'epay_settle'",
    "\$mod === 'epay_order'",
    "recovered_epay_query('query')",
    "recovered_epay_query('settle'",
    "recovered_epay_query('orders'",
    'api.php?act=change&pid=',
    'function checkepayurl(var1,var2)',
    'micropay_n',
    'captcha_verify_url',
    'wechat_webhook',
    '自定义验证接口',
    '本站QQ扫码登录',
] as $needle) {
    if (strpos($src, $needle) === false) {
        throw new RuntimeException('missing fragment: ' . $needle);
    }
}
if (substr_count($src, 'goto ') !== 0) {
    throw new RuntimeException('recovered set.php still contains goto');
}

$mods = ['account', 'bind', 'site', 'fenzhan', 'template', 'captcha', 'defend', 'qiandao', 'proxy', 'cloneset', 'invite', 'dwz', 'mail', 'oauth', 'cron', 'gonggao', 'mailcon', 'copygg', 'pay', 'upimg', 'upbgimg', 'upwxqrcode'];
foreach ($mods as $mod) {
    if (strpos($src, "\$mod === '" . $mod . "'") === false) {
        throw new RuntimeException('missing mod dispatcher: ' . $mod);
    }
}

echo "set.php recovery checks passed\n";
