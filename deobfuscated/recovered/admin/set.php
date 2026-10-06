<?php
/**
 * Recovered admin/set.php (goto-flattened original, ~18546 gotos).
 * Dispatcher + forms reconstructed from explode string tables.
 * Most panels POST to ajax.php?act=set via saveSetting(); *_n mods save here.
 * Not wired into the live admin path until a side-by-side check passes.
 */
include '../includes/common.php';
$title = '后台管理';
include './head.php';
if ($islogin == 1) {
} else {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

adminpermission('set', 1);

$mod = isset($_GET['mod']) ? $_GET['mod'] : 'site';

function recovered_conf($key, $default = '')
{
    global $conf;
    return htmlspecialchars((string) (isset($conf[$key]) ? $conf[$key] : $default), ENT_QUOTES, 'UTF-8');
}

function recovered_select($name, $options, $default = null)
{
    global $conf;
    if ($default === null) {
        $default = isset($conf[$name]) ? $conf[$name] : '';
    }
    $html = '<select class="form-control" name="' . htmlspecialchars($name) . '" default="' . htmlspecialchars((string) $default) . '">';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . htmlspecialchars((string) $value) . '">' . $label . '</option>';
    }
    return $html . '</select>';
}

function recovered_input($name, $placeholder = '', $type = 'text')
{
    $ph = $placeholder !== '' ? ' placeholder="' . htmlspecialchars($placeholder) . '"' : '';
    return '<input type="' . htmlspecialchars($type) . '" name="' . htmlspecialchars($name) . '" value="' . recovered_conf($name) . '" class="form-control"' . $ph . '/>';
}

function recovered_textarea($name, $rows = 5, $placeholder = '')
{
    $ph = $placeholder !== '' ? ' placeholder="' . htmlspecialchars($placeholder) . '"' : '';
    return '<textarea class="form-control" name="' . htmlspecialchars($name) . '" rows="' . intval($rows) . '" style="width:100%;"' . $ph . '>' . recovered_conf($name) . '</textarea>';
}

function recovered_group($label, $inner, $col = 2)
{
    $right = 12 - $col;
    return '<div class="form-group">' . "\n" . '	  <label class="col-sm-' . $col . ' control-label">' . $label . '</label>' . "\n" . '	  <div class="col-sm-' . $right . '">' . $inner . '</div>' . "\n" . '</div>';
}

function recovered_save_keys($keys)
{
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            saveSetting($key, $_POST[$key]);
        }
    }
}

$yesno = ['0' => '否', '1' => '是'];
$offon = ['0' => '关闭', '1' => '开启'];
$onoff = ['0' => '开启', '1' => '关闭'];

if ($mod === 'account_n' && isset($_POST['do']) && $_POST['do'] === 'submit') {
    $user = trim((string) $_POST['user']);
    $oldpwd = (string) $_POST['oldpwd'];
    $newpwd = (string) $_POST['newpwd'];
    $newpwd2 = (string) $_POST['newpwd2'];
    if ($user === '') {
        showmsg('用户名不能为空！', 3);
    }
    if ($oldpwd !== (string) $conf['admin_pwd']) {
        showmsg('旧密码不正确！', 3);
    }
    if ($newpwd !== $newpwd2) {
        showmsg('两次输入的密码不一致！', 3);
    }
    saveSetting('admin_user', $user);
    if ($newpwd !== '') {
        saveSetting('admin_pwd', $newpwd);
    }
    $CACHE->clear();
    unset($_SESSION['admin_token']);
    showmsg('修改成功！请重新登录', 1);
}

if ($mod === 'captcha_n' && $_POST) {
    $open = isset($_POST['captcha_open']) ? $_POST['captcha_open'] : '0';
    if ($open !== '0' && (trim((string) $_POST['captcha_id']) === '' || trim((string) $_POST['captcha_key']) === '')) {
        showmsg('请填写好ID和KEY再选择开启！', 3);
    }
    recovered_save_keys(['captcha_open', 'captcha_id', 'captcha_key', 'captcha_open_free', 'captcha_open_reg', 'captcha_open_login', 'ip_type']);
    $CACHE->clear();
    showmsg('修改成功！', 1);
}

if ($mod === 'defend_n' && isset($_POST['defendid'])) {
    $defendid = intval($_POST['defendid']);
    $code = "<?php\r\n//防CC模块设置\r\ndefine('CC_Defender', " . $defendid . ");\r\n?>";
    file_put_contents(ROOT . 'includes/base.php', $code);
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    showmsg('修改成功！', 1);
}

if ($mod === 'proxy_n' && isset($_POST['do']) && $_POST['do'] === 'submit') {
    recovered_save_keys(['proxy', 'proxy_server', 'proxy_port', 'proxy_user', 'proxy_pwd', 'proxy_type']);
    $CACHE->clear();
    showmsg('修改成功！', 1);
}

if ($mod === 'alipay_n' && $_POST) {
    recovered_save_keys(['alipay_appid', 'alipay_publickey', 'alipay_privatekey']);
    $CACHE->clear();
    showmsg('修改成功！', 1);
}
if ($mod === 'alipay2_n' && $_POST) {
    recovered_save_keys(['alipay_getway', 'alipay_pid', 'alipay_key']);
    $CACHE->clear();
    showmsg('修改成功！', 1);
}
if ($mod === 'qqpay_n' && $_POST) {
    recovered_save_keys(['qqpay_mchid', 'qqpay_key']);
    $CACHE->clear();
    showmsg('修改成功！', 1);
}
if ($mod === 'wxpay_n' && $_POST) {
    recovered_save_keys(['wxpay_appid', 'wxpay_mchid', 'wxpay_key', 'wxpay_appsecret', 'wxpay_domain']);
    $CACHE->clear();
    showmsg('修改成功！', 1);
}
if ($mod === 'epay_n' && $_POST) {
    if (isset($_POST['account']) || isset($_POST['username'])) {
        $account = isset($_POST['account']) ? trim((string) $_POST['account']) : '';
        $username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
        $type = isset($_POST['type']) ? trim((string) $_POST['type']) : '';
        if ($account === '' || $username === '') {
            showmsg('保存错误,请确保每项都不为空!', 3);
        }
        $base = recovered_epay_base();
        $site = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $url = $base . 'api.php?act=change&pid=' . urlencode((string) $conf['epay_pid']) . '&key=' . urlencode((string) $conf['epay_key']) . '&account=' . urlencode($account) . '&username=' . urlencode($username) . '&url=' . urlencode($site);
        if ($type !== '') {
            $url .= '&type=' . urlencode($type);
        }
        $arr = json_decode((string) get_curl($url), true);
        showmsg((isset($arr['msg']) ? $arr['msg'] : '修改成功!'), (isset($arr['code']) && intval($arr['code']) === 1) ? 1 : 3);
    }
    if (file_exists(ROOT . 'admin/pay.lock') && !isset($_GET['unlockpay'])) {
        recovered_save_keys(['epay_url', 'epay_url2', 'epay_url3']);
    } else {
        recovered_save_keys(['epay_url', 'epay_pid', 'epay_key', 'epay_url2', 'epay_pid2', 'epay_key2', 'epay_url3', 'epay_pid3', 'epay_key3']);
    }
    $CACHE->clear();
    showmsg('修改成功！', 1);
}
if ($mod === 'codepay_n' && $_POST) {
    recovered_save_keys(['codepay_id', 'codepay_key']);
    $CACHE->clear();
    showmsg('修改成功！', 1);
}
if ($mod === 'micropay_n' && $_POST) {
    recovered_save_keys(['micropay_pid', 'micropay_key', 'micropayapi', 'micropay_mchid']);
    $CACHE->clear();
    showmsg('修改成功！', 1);
}

function recovered_epay_base()
{
    global $conf;
    $url = isset($conf['epay_url']) ? rtrim((string) $conf['epay_url'], '/') . '/' : '';
    return $url;
}

function recovered_epay_query($act, $extra = '')
{
    global $conf;
    $base = recovered_epay_base();
    if ($base === '' || empty($conf['epay_pid']) || empty($conf['epay_key'])) {
        return null;
    }
    $site = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $url = $base . 'api.php?act=' . $act . '&pid=' . urlencode((string) $conf['epay_pid']) . '&key=' . urlencode((string) $conf['epay_key']) . '&url=' . urlencode($site) . $extra;
    $raw = get_curl($url);
    $arr = json_decode((string) $raw, true);
    return is_array($arr) ? $arr : null;
}

if ($mod === 'mailcon_reset') {
    $tpl = '<b>商品名称：</b> [name]<br/><b>购买时间：</b>[date]<br/><b>以下是你的卡密信息：</b><br/>[kmdata]<br/>----------<br/><b>使用说明：</b><br/>[alert]<br/>----------<br/>';
    saveSetting('faka_mail', $tpl);
    $CACHE->clear();
    showmsg('修改成功！', 1);
}

if ($mod === 'copygg_n' && isset($_POST['do']) && $_POST['do'] === 'submit') {
    $url = trim((string) $_POST['url']);
    $host = parse_url($url, PHP_URL_HOST);
    if ($host && strcasecmp((string) $host, (string) $_SERVER['HTTP_HOST']) === 0) {
        showmsg('无法自己复制自己', 3);
    }
    $raw = get_curl(rtrim($url, '/') . '/api.php?act=siteinfo');
    $arr = json_decode((string) $raw, true);
    if (!$arr) {
        showmsg('获取数据失败，对方网站无法连接或存在金盾或云锁等防火墙。', 3);
    }
    $picked = isset($_POST['content']) && is_array($_POST['content']) ? $_POST['content'] : [];
    foreach ($picked as $field) {
        if (isset($arr[$field])) {
            saveSetting($field, $arr[$field]);
        }
    }
    $CACHE->clear();
    showmsg('修改成功！', 1);
}

if ($mod === 'mailtest') {
    $to = !empty($conf['mail_recv']) ? $conf['mail_recv'] : $conf['mail_name'];
    $ok = send_mail($to, $conf['sitename'] . ' - 邮件发送测试', '这是一封测试邮件，说明发信配置可用。');
    showmsg($ok ? '测试邮件已发送到 ' . htmlspecialchars((string) $to) : '发送失败', $ok ? 1 : 3);
}

if ($mod === 'cleanbom') {
    $file = ROOT . 'config.php';
    $data = file_get_contents($file);
    if (substr($data, 0, 3) === "\xEF\xBB\xBF") {
        file_put_contents($file, substr($data, 3));
        showmsg('找到BOM并已自动去除', 1);
    }
    showmsg('没有找到BOM', 1);
}

$uploadMods = ['upimg' => ['assets/img/logo.png', '更改首页LOGO', 'setimg'], 'upbgimg' => ['assets/img/bj.png', '更改首页背景图', 'bj'], 'upwxqrcode' => ['assets/img/wxqrcode.png', '上传客服微信二维码', 'upwxqrcode']];
if (isset($uploadMods[$mod]) && isset($_POST['s']) && isset($_FILES['file'])) {
    $target = ROOT . $uploadMods[$mod][0];
    if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
        showmsg('成功上传文件!<br>（可能需要清空浏览器缓存才能看到效果，按Ctrl+F5即可一键刷新缓存）', 1);
    }
    showmsg('上传失败，可能没有文件写入权限', 3);
}
if ($mod === 'delwxqrcode') {
    @unlink(ROOT . 'assets/img/wxqrcode.png');
    exit("<script language='javascript'>alert('删除成功');window.location.href='./set.php?mod=upwxqrcode';</script>");
}

echo '<div class="col-xs-12 col-sm-10 col-lg-8 center-block" style="float: none;">';
echo '<div class="block">';

if ($mod === 'account') {
    echo '<div class="block-title"><h3 class="panel-title">管理员账号配置</h3></div><div class="">';
    echo '<form action="./set.php?mod=account_n" method="post" class="form-horizontal" role="form"><input type="hidden" name="do" value="submit"/>';
    echo recovered_group('用户名', '<input type="text" name="user" value="' . recovered_conf('admin_user') . '" class="form-control" required/>');
    echo recovered_group('旧密码', '<input type="password" name="oldpwd" value="" class="form-control" placeholder="请输入当前的管理员密码"/>');
    echo recovered_group('新密码', '<input type="password" name="newpwd" value="" class="form-control" placeholder="不修改请留空"/>');
    echo recovered_group('重输密码', '<input type="password" name="newpwd2" value="" class="form-control" placeholder="不修改请留空"/>');
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form></div>';
} elseif ($mod === 'bind') {
    echo '<div class="block-title"><h3 class="panel-title">管理员后台微信/QQ扫码登录</h3></div>';
    echo '<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal form-bordered" role="form">';
    echo recovered_group('开启扫码登录', recovered_select('thirdlogin_open', $yesno));
    echo recovered_group('绑定微信', '<div class="input-group"><input type="text" name="thirdlogin_wx" value="' . recovered_conf('thirdlogin_wx') . '" class="form-control" disabled/><span class="input-group-btn"><a href="javascript:thirdloginbind(\'wx\')" class="btn btn-success">绑定</a><a href="javascript:thirdloginunbind(\'wx\')" class="btn btn-danger">解绑</a></span></div>');
    echo recovered_group('绑定QQ', '<div class="input-group"><input type="text" name="thirdlogin_qq" value="' . recovered_conf('thirdlogin_qq') . '" class="form-control" disabled/><span class="input-group-btn"><a href="javascript:thirdloginbind(\'qq\')" class="btn btn-success">绑定</a><a href="javascript:thirdloginunbind(\'qq\')" class="btn btn-danger">解绑</a></span></div>');
    echo recovered_group('关闭密码登录', recovered_select('thirdlogin_closepwd', $yesno));
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';
} elseif ($mod === 'site') {
    echo '<div class="block-title"><h3 class="panel-title">网站信息配置</h3></div>';
    echo '<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">';
    echo recovered_group('网站名称', recovered_input('sitename'));
    echo recovered_group('标题栏后缀', recovered_input('title'));
    echo recovered_group('关键字', recovered_input('keywords'));
    echo recovered_group('网站描述', recovered_input('description'));
    echo recovered_group('隐藏站点名称', recovered_select('sitename_hide', $yesno));
    echo recovered_group('客服ＱＱ', recovered_input('kfqq'));
    echo recovered_group('客服微信', recovered_input('kfwx') . '<a href="./set.php?mod=upwxqrcode">上传微信二维码</a>');
    echo recovered_group('下单前验证', recovered_select('verify_open', $offon));
    echo recovered_group('开启订单查询', recovered_select('search_open', $offon));
    echo recovered_group('发卡联系方式', recovered_select('faka_input', ['0' => '你的邮箱', '1' => '手机号码', '2' => '你的ＱＱ', '4' => '取卡密码', '5' => '自定义', '3' => '(不填写内容)']));
    echo recovered_group('发卡自定义名称', recovered_input('faka_inputname'));
    echo recovered_group('发卡库存显示', recovered_select('faka_showleft', ['0' => '精确数量', '1' => '大概情况']));
    echo recovered_group('显示投诉建议', recovered_select('show_complain', $offon));
    echo recovered_group('显示修改密码', recovered_select('show_changepwd', $offon));
    echo recovered_group('隐藏统计数据', recovered_select('hide_tongji', $offon));
    echo recovered_group('统计缓存时间', recovered_input('tongji_time'));
    echo recovered_group('下单黑名单', recovered_textarea('blacklist', 3, '多个用|隔开'));
    echo recovered_group('背景图来源', recovered_select('ui_bing', ['0' => '自定义背景图片', '1' => '随机美图', '2' => 'Bing每日壁纸', '3' => '渐变背景色']));
    echo recovered_group('背景图显示', recovered_select('ui_background', ['0' => '纵向和横向重复', '1' => '横向重复,纵向拉伸', '2' => '纵向重复,横向拉伸', '3' => '不重复,全屏拉伸']) . '<a href="./set.php?mod=upbgimg">点此上传背景图</a>');
    echo recovered_group('渐变方向', recovered_select('ui_colorto', ['0' => '纵向渐变', '1' => '横向渐变']));
    echo recovered_group('渐变颜色1', recovered_input('ui_color1'));
    echo recovered_group('渐变颜色2', recovered_input('ui_color2'));
    echo recovered_group('商品列表样式', recovered_select('ui_shop', ['0' => '经典模式', '1' => '分类图片宫格', '2' => '分类图片列表', '3' => '分类图片宫格2']));
    echo recovered_group('文章每页数量', recovered_input('articlenum'));
    echo recovered_group('用户中心风格', recovered_select('ui_user', ['0' => '明亮风格（默认）', '1' => '黑色风格']));
    echo recovered_group('购物车功能', recovered_select('shoppingcart', $offon));
    echo recovered_group('生成订单后跳转', recovered_select('build', $offon));
    echo recovered_group('QQ跳转', recovered_select('qqjump', $offon));
    echo recovered_group('API对接密钥', recovered_input('apikey'));
    echo recovered_group('分类折叠', recovered_select('classblock', $offon));
    echo recovered_group('强制登录下单', recovered_select('forcelogin', $offon));
    echo recovered_group('强制余额消费', recovered_select('forcermb', $offon));
    echo recovered_group('强制登录首页', recovered_select('forceloginhome', $offon));
    echo recovered_group('开启批量下单', recovered_select('openbatchorder', $offon));
    echo recovered_group('查询仅本人订单', recovered_select('queryorderlimit', ['0' => '关闭', '1' => '开启']));
    echo recovered_group('商品简介编辑器', recovered_select('shopdesc_editor', $offon));
    echo recovered_group('卡密商品模式', recovered_select('iskami', $offon));
    echo recovered_group('用户自助退款', recovered_select('selfrefund', $offon));
    echo recovered_group('说说API', recovered_input('qzone_shuoshuo_api'));
    echo recovered_group('日志API', recovered_input('qzone_rizhi_api'));
    echo recovered_group('伪静态文章', recovered_select('article_rewrite', $offon));
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';
} elseif ($mod === 'fenzhan') {
    echo '<div class="block-title"><h3 class="panel-title">分站相关配置</h3></div>';
    echo '<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">';
    echo recovered_group('默认分站等级', recovered_select('fenzhan_rank', ['1' => '普及版', '2' => '专业版']));
    echo recovered_group('开放用户中心', recovered_select('user_open', $offon));
    echo recovered_group('默认用户等级', recovered_input('user_level'));
    echo recovered_group('自助开通分站', recovered_select('fenzhan_buy', $offon));
    echo recovered_group('开通成功提醒', recovered_select('fenzhan_regalert', $offon));
    echo recovered_group('随机分站前缀', recovered_select('fenzhan_regrand', $offon));
    echo recovered_group('分站有效期(天)', recovered_input('fenzhan_expiry'));
    echo recovered_group('专业版售价', recovered_input('fenzhan_price2'));
    echo recovered_group('普及版售价', recovered_input('fenzhan_price'));
    echo recovered_group('专业版成本', recovered_input('fenzhan_cost2'));
    echo recovered_group('普及版成本', recovered_input('fenzhan_cost'));
    echo recovered_group('免费开通分站', recovered_select('fenzhan_free', $offon));
    echo recovered_group('允许升级版本', recovered_select('fenzhan_upgrade', $offon));
    echo recovered_group('允许修改域名', recovered_select('fenzhan_editd', $offon));
    echo recovered_group('可绑定域名', recovered_input('fenzhan_domain', '多个用,隔开'));
    echo recovered_group('保留域名', recovered_input('fenzhan_remain', '多个用,隔开'));
    echo recovered_group('分站默认首页', recovered_input('fenzhan_page'));
    echo recovered_group('允许修改公告', recovered_select('fenzhan_edithtml', $offon));
    echo recovered_group('允许更换模板', recovered_select('fenzhan_template', $offon));
    echo recovered_group('允许添加商品', recovered_select('fenzhan_adds', $offon));
    echo recovered_group('显示客服QQ', recovered_select('fenzhan_kfqq', $offon));
    echo recovered_group('售价上限限制', recovered_select('fenzhan_pricelimit', $offon));
    echo recovered_group('开启余额提现', recovered_select('fenzhan_tixian', $offon));
    echo recovered_group('支付宝提现', recovered_select('fenzhan_tixian_alipay', $offon));
    echo recovered_group('微信提现', recovered_select('fenzhan_tixian_wx', $offon));
    echo recovered_group('QQ提现', recovered_select('fenzhan_tixian_qq', $offon));
    echo recovered_group('上传收款图', recovered_select('fenzhan_skimg', $offon));
    echo recovered_group('代付开关', recovered_select('fenzhan_daifu', $offon));
    echo recovered_group('提现费率(%)', recovered_input('tixian_rate'));
    echo recovered_group('最低提现金额', recovered_input('tixian_min'));
    echo recovered_group('每日提现限额', recovered_input('tixian_limit'));
    echo recovered_group('到账延迟天数', recovered_input('tixian_days'));
    echo recovered_group('加款卡功能', recovered_select('fenzhan_jiakuanka', $offon));
    echo recovered_group('赠送余额', recovered_select('fenzhan_gift', $offon));
    echo recovered_group('最低充值金额', recovered_input('recharge_min'));
    echo recovered_group('工单系统', recovered_select('workorder_open', $offon));
    echo recovered_group('工单图片上传', recovered_select('workorder_pic', $offon));
    echo recovered_group('工单问题类型', recovered_textarea('workorder_type', 3));
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';
} elseif ($mod === 'template' || $mod === 'template2') {
    echo '<div class="block-title"><h3 class="panel-title">首页模板设置</h3></div>';
    echo '<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">';
    echo recovered_group('公共静态CDN', recovered_select('cdnpublic', ['4' => '字节跳动CDN', '0' => 'ZstaticCDN', '2' => '未闻花名CDN', '1' => '360CDN']));
    echo recovered_group('自定义静态URL', recovered_input('staticurl'));
    echo recovered_group('手机模板', recovered_select('template_m', ['0' => '与电脑版相同（默认）']));
    echo recovered_group('当前模板', recovered_input('template'));
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';
    echo '<p>点击模板名称可一键更换：</p><div class="row">';
    $dirs = glob(ROOT . 'template/*', GLOB_ONLYDIR);
    if ($dirs) {
        foreach ($dirs as $dir) {
            $name = basename($dir);
            echo '<div class="col-xs-6 col-sm-4"><a href="javascript:changeTemplate(\'' . htmlspecialchars($name) . '\')" class="btn btn-default btn-block">' . htmlspecialchars($name) . '</a></div>';
        }
    }
    echo '</div>';
} elseif ($mod === 'captcha') {
    echo '<div class="block-title"><h3 class="panel-title">滑动验证码设置</h3></div>';
    echo '<form action="./set.php?mod=captcha_n" method="post" class="form-horizontal" role="form">';
    echo recovered_group('验证码开关', recovered_select('captcha_open', ['0' => '关闭', '1' => '极限滑动验证码', '2' => '顶象滑动验证码', '3' => 'VAPTCHA手势验证码']));
    echo recovered_group('ID', recovered_input('captcha_id'));
    echo recovered_group('KEY', recovered_input('captcha_key'));
    echo recovered_group('免费商品开启', recovered_select('captcha_open_free', $offon));
    echo recovered_group('注册开启', recovered_select('captcha_open_reg', $offon));
    echo recovered_group('登录开启', recovered_select('captcha_open_login', $offon));
    echo '<div class="block-title"><h3 class="panel-title">用户IP地址获取设置</h3></div>';
    echo recovered_group('获取方式', recovered_select('ip_type', ['0' => '0_X_FORWARDED_FOR', '1' => '1_X_REAL_IP', '2' => '2_REMOTE_ADDR']));
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';
} elseif ($mod === 'defend') {
    $cur = defined('CC_Defender') ? (string) CC_Defender : '1';
    echo '<div class="block-title"><h3 class="panel-title">防CC模块设置</h3></div>';
    echo '<form action="./set.php?mod=defend_n" method="post" class="form-horizontal" role="form">';
    echo recovered_group('防护等级', recovered_select('defendid', ['1' => '低(推荐)', '2' => '中', '3' => '高', '4' => '滑动验证码'], $cur));
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';
} elseif ($mod === 'qiandao') {
    echo '<div class="block-title"><h3 class="panel-title">签到模块设置</h3></div>';
    echo '<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">';
    echo recovered_group('奖励余额初始值', recovered_input('qiandao_reward'));
    echo recovered_group('每日递增倍数', recovered_input('qiandao_mult'));
    echo recovered_group('最多递增天数', recovered_input('qiandao_day'));
    echo recovered_group('是否限制每个IP签到一次', recovered_select('qiandao_limitip', $yesno));
    echo '<p class="help-block"><span class="glyphicon glyphicon-info-sign"></span>奖励余额初始值填写一个值代表所有类型分站都一样，填写3个值并用|隔开代表不同类型分站不一样</p>';
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';
    echo '<div class="block-title"><h3 class="panel-title" id="title">签到统计</h3></div>';
    echo '<table class="table table-bordered"><tbody><tr>';
    echo '<th class="text-center"><i class="fa fa-user-circle-o"></i> 今日签到<br><span id="count1"></span>人</th>';
    echo '<th class="text-center"><i class="fa fa-user-circle"></i> 昨日签到<br><span id="count2"></span>人</th>';
    echo '<th class="text-center"><i class="fa fa-pie-chart"></i> 累计签到<br><span id="count3"></span>人</th></tr><tr>';
    echo '<th class="text-center"><i class="fa fa-money"></i> 今日送出余额<br><span id="count4"></span>元</th>';
    echo '<th class="text-center"><i class="fa fa-money"></i> 昨日送出余额<br><span id="count5"></span>元</th>';
    echo '<th class="text-center"><i class="fa fa-bar-chart"></i> 累计送出余额<br><span id="count6"></span>元</th>';
    echo '</tr></tbody></table>';
    echo '<script>$.get("ajax.php?act=qdcount",function(data){$("#count1").html(data.count1);$("#count2").html(data.count2);$("#count3").html(data.count3);$("#count4").html(data.count4);$("#count5").html(data.count5);$("#count6").html(data.count6);$("#title").html("签到统计");},"json");</script>';
} elseif ($mod === 'proxy') {
    echo '<div class="block-title"><h3 class="panel-title">代理服务器设置</h3></div>';
    echo '<form action="./set.php?mod=proxy_n" method="post" class="form-horizontal" role="form"><input type="hidden" name="do" value="submit"/>';
    echo recovered_group('代理服务器开关', recovered_select('proxy', $offon));
    echo recovered_group('代理IP', recovered_input('proxy_server'));
    echo recovered_group('代理端口', recovered_input('proxy_port'));
    echo recovered_group('代理账号', recovered_input('proxy_user'));
    echo recovered_group('代理密码', recovered_input('proxy_pwd'));
    echo recovered_group('代理协议', recovered_select('proxy_type', ['http' => 'HTTP', 'https' => 'HTTPS', 'sock4' => 'SOCK4', 'sock5' => 'SOCK5']));
    echo '<p>本功能适用于国外服务器对接一些屏蔽国外访问的网站，开启后使用国内代理服务器进行对接。<br/>自定义代理可以使用Windows服务器+CCProxy软件搭建<br/><b>注意：如果网站更换主机之后需要重新修改当前配置。</b></p>';
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';
} elseif ($mod === 'cloneset') {
    echo '<div class="block-title"><h3 class="panel-title">克隆站点配置</h3></div>';
    echo '<form action="./set.php?mod=shequ_n" method="post" class="form-horizontal" role="form"><input type="hidden" name="do" value="submit"/>';
    echo recovered_group('克隆密钥', '<input type="text" name="key" value="' . recovered_conf('key') . '" class="form-control" readOnly="readOnly"/>');
    echo '<p>此密钥是用于其他站点克隆本站商品<br/><b>注意：克隆是指直接原样复制本站数据，并不是对接到本站！</b><br/>提示：修改API对接密钥可同时重置克隆密钥。</p></form>';
} elseif ($mod === 'invite') {
    echo '<div class="block-title"><h3 class="panel-title">推广链接设置</h3></div>';
    echo '<form onsubmit="return saveSetting(this)" method="post" class="form" role="form">';
    echo '<label>开启推广链接功能</label>' . recovered_select('invite_tid', $offon);
    echo '<label>广告语自定义</label>' . recovered_textarea('invite_content', 5);
    echo '<pre>其中，推广链接用[url]，会自动替换</pre>';
    echo '<input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/><br/>';
    echo '<a href="./invite.php" class="btn btn-info btn-block">进入推广商品列表</a><br/>';
    echo '<a href="./invitelog.php" class="btn btn-default btn-block">查看推广记录</a>';
    echo '<p>推广链接生成地址：/?mod=invite<br/>推广页面模板文件：/template/default/invite.php<br/>如果使用累计次数推广模式，建议先设置好<a href="./set.php?mod=captcha">用户IP地址获取设置</a>，相同IP地址算一次访问。</p></form>';
} elseif ($mod === 'dwz') {
    echo '<div class="block-title"><h3 class="panel-title">防红链接生成接口设置</h3></div>';
    echo '<form onsubmit="return saveSetting(this)" method="post" class="form" role="form">';
    echo '<label>防红接口选择：</label>' . recovered_select('fanghong_api', ['0' => '不使用防红接口', '9' => '自定义防红接口']);
    echo '<div class="form-group" id="fanghong_type"><label>默认生成防红方式：</label>' . recovered_select('fanghong_type', ['1' => 'QQ跳转防红（跳转到其它浏览器）', '2' => 'QQ直接防红（QQ内直接打开,仅安卓）', '3' => '微信跳转防红（专门用于微信内访问）']) . '</div>';
    echo '<div class="form-group" id="fanghong_diy"><label>自定义接口地址：</label><div class="input-group">' . recovered_input('fanghong_url', '不填写则关闭防红链接生成') . '<div class="input-group-addon" onclick="checkurl()"><small>检测地址</small></div></div></div>';
    echo '<input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></form>';
    echo '<div class="block-title"><h3 class="panel-title">获取防红链接</h3></div>';
    echo '<div class="input-group"><span class="input-group-addon">当前网址</span><input class="form-control" id="longurl" value="' . htmlspecialchars((isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/') . '"/></div>';
    echo '<div class="well well-sm">如果您的网址在QQ内报毒或者打不开，您可以使用此功能生成防毒链接！</div>';
    echo '<a class="btn btn-block btn-success" id="create_url">生成我的防红链接</a>';
} elseif ($mod === 'mail') {
    echo '<div class="block-title"><h3 class="panel-title">消息提醒设置</h3></div>';
    echo '<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">';
    echo recovered_group('默认提醒方式', recovered_select('message_type', ['0' => '邮件', '1' => '微信']));
    echo recovered_group('对接下单失败提醒', recovered_select('message_duijie', $offon));
    echo recovered_group('全部商品下单提醒', recovered_select('message_buy', ['0' => '关闭', '1' => '开启', '2' => '开启（不包括免费商品）']));
    echo recovered_group('新工单提醒', recovered_select('message_workorder', $offon));
    echo recovered_group('申请提现提醒', recovered_select('message_tixian', $offon));
    echo recovered_group('发卡库存提醒', recovered_select('message_fakastock', $offon));
    echo '<div class="block-title"><h3 class="panel-title">发信邮箱设置</h3></div>';
    echo recovered_group('发信模式', recovered_select('mail_cloud', ['0' => 'SMTP发信', '1' => '搜狐Sendcloud', '2' => '阿里云邮件推送']));
    echo recovered_group('SMTP服务器', recovered_input('mail_smtp'));
    echo recovered_group('SMTP端口', recovered_input('mail_port'));
    echo recovered_group('邮箱账号', recovered_input('mail_name'));
    echo recovered_group('邮箱密码', recovered_input('mail_pwd'));
    echo recovered_group('API_USER', recovered_input('mail_apiuser'));
    echo recovered_group('API_KEY', recovered_input('mail_apikey'));
    echo recovered_group('发信邮箱', recovered_input('mail_name2'));
    echo recovered_group('收信邮箱', recovered_input('mail_recv', '不填默认为发信邮箱'));
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/><br/>[<a href="set.php?mod=mailtest">给当前邮箱发一封测试邮件</a>]</div></div>';
    echo '<div class="block-title"><h3 class="panel-title">微信消息设置</h3></div>';
    echo recovered_group('微信消息接口', recovered_select('wechat_api', ['0' => 'ServerChan(ftqq)', '1' => 'WxPusher']));
    echo recovered_group('SCKEY', recovered_input('wechat_sckey'));
    echo recovered_group('appToken', recovered_input('wechat_apptoken'));
    echo recovered_group('用户UID', recovered_input('wechat_appuid'));
    echo '</form>';
} elseif ($mod === 'oauth') {
    echo '<div class="block-title"><h3 class="panel-title">快捷登录配置</h3></div>';
    echo '<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">';
    echo recovered_group('QQ快捷登录方式', recovered_select('login_qq', ['0' => '关闭', '1' => '彩虹聚合登录', '2' => '手机QQ扫码登录']), 3);
    echo recovered_group('微信快捷登录方式', recovered_select('login_wx', ['0' => '关闭', '1' => '彩虹聚合登录']), 3);
    echo recovered_group('API接口地址', recovered_input('login_apiurl', 'API地址要以http://或https://开头，以/结尾'), 3);
    echo recovered_group('应用APPID', recovered_input('login_appid'), 3);
    echo recovered_group('应用APPKEY', recovered_input('login_appkey'), 3);
    echo '<p>QQ快捷登录接口是使用彩虹聚合登录系统搭建的站点，并非QQ互联官方接口。<br/>QQ快捷登录开启后请勿随意更换登录API站点，否则会导致之前以QQ快捷登录注册的用户全部无法登录。<br/>手机QQ扫码登录使用更方便，登录凭证以用户注册时填写的QQ为准</p>';
    echo '<div class="form-group"><div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';
} elseif ($mod === 'cron') {
    $cronkey = recovered_conf('cronkey');
    $base = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/';
    echo '<div class="block-title"><h3 class="panel-title">计划任务配置</h3></div>';
    echo '<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">';
    echo recovered_group('监控密钥', recovered_input('cronkey'), 3);
    echo '<div class="form-group"><div class="col-sm-offset-3 col-sm-9"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';
    echo '<div class="block-title"><h3 class="panel-title">计划任务列表</h3></div>';
    echo '<p>请按自己的需要监控以下网址。只能在一个地方监控，千万不要多节点监控或在多处监控，否则会导致数据错乱！</p>';
    echo '<p>每日数据库维护+排行榜奖励发放+提成余额延迟到账（每天0点后执行2次）：</p>';
    echo '<li class="list-group-item">' . htmlspecialchars($base . 'cron.php?do=daily&key=' . $conf['cronkey']) . '</li>';
    echo '<p>社区价格监控（10到60分钟一次）：</p>';
    echo '<li class="list-group-item">' . htmlspecialchars($base . 'cron.php?do=pricejk&key=' . $conf['cronkey']) . '</li>';
    echo '<p>易支付订单补单监控：</p>';
    echo '<li class="list-group-item">' . htmlspecialchars($base . 'cron.php?key=' . $conf['cronkey']) . '</li>';
    echo '<p>订单状态检测：</p>';
    echo '<li class="list-group-item">' . htmlspecialchars($base . 'cron.php?do=updatestatus&key=' . $conf['cronkey']) . '</li>';
} elseif ($mod === 'gonggao') {
    echo '<div class="block-title"><h3 class="panel-title">网站公告配置</h3></div><div class="panel-body">';
    echo '<form onsubmit="return saveSetting(this)" method="post" class="form" role="form">';
    echo '<label>首页公告</label><br/>' . recovered_textarea('anounce', 8);
    echo '<label>首页弹出公告</label><br/>' . recovered_textarea('modal', 5);
    echo '<label>站点工具/友情链接（部分模板显示）</label><br/>' . recovered_textarea('bottom', 5);
    echo '<label>在线下单提示（部分模板显示）</label><br/>' . recovered_textarea('alert', 5);
    echo '<label>订单查询页面公告</label><br/>' . recovered_textarea('gg_search', 5);
    echo '<label>分站后台公告</label><br/>' . recovered_textarea('gg_panel', 5);
    echo '<label>所有分站显示首页公告</label><br/>' . recovered_textarea('gg_announce', 5, '此处公告内容将在所有分站首页公告显示。顺序是先显示此公告再显示分站自定义公告');
    echo '<label>首页底部排版</label><br/>' . recovered_textarea('footer', 3, '可用于统计代码或备案号等');
    echo '<label>支付方式选择页面提示</label><br/>' . recovered_textarea('paymsg', 3);
    echo '<label>APP下载地址</label><br/>' . recovered_input('appurl', '没有请留空');
    echo '<label>APP启动弹出内容</label><br/>' . recovered_textarea('appalert', 3);
    echo '<label>首页背景音乐</label><br/>' . recovered_input('musicurl', '填写音乐的URL');
    echo '<input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/><br/>';
    echo '实用工具：<a href="set.php?mod=copygg">一键复制其他站点排版</a></form></div>';
} elseif ($mod === 'mailcon') {
    echo '<div class="block-title"><h3 class="panel-title">发信邮件模板设置</h3></div>';
    echo '<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">';
    echo recovered_group('发卡邮件模板', '<textarea class="form-control" name="faka_mail" id="faka_mail" rows="6">' . recovered_conf('faka_mail') . '</textarea>');
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/><br/><br/>';
    echo '<a href="./set.php?mod=mailcon_reset" class="btn btn-warning btn-block" onclick="return confirm(\'确定要重置吗？\');">重置模板设置</a></div></div></form>';
    echo '<font color="green">变量代码：<br/>';
    echo '<a href="#" onclick="Addstr(\'faka_mail\',\'[kmdata]\');return false">[kmdata]</a>&nbsp;卡密内容<br/>';
    echo '<a href="#" onclick="Addstr(\'faka_mail\',\'[name]\');return false">[name]</a>&nbsp;商品名称<br/>';
    echo '<a href="#" onclick="Addstr(\'faka_mail\',\'[alert]\');return false">[alert]</a>&nbsp;商品简介<br/>';
    echo '<a href="#" onclick="Addstr(\'faka_mail\',\'[date]\');return false">[date]</a>&nbsp;购买时间<br/>';
    echo '<a href="#" onclick="Addstr(\'faka_mail\',\'[email]\');return false">[email]</a>&nbsp;收信人邮箱<br/></font>';
} elseif ($mod === 'copygg') {
    echo '<div class="block-title"><h3 class="panel-title">一键复制其他站点排版</h3></div>';
    echo '<form action="./set.php?mod=copygg_n" method="post" class="form-horizontal" role="form"><input type="hidden" name="do" value="submit"/>';
    echo recovered_group('站点URL', '<input type="text" name="url" value="" class="form-control" placeholder="http://www.qq.com/" required/>');
    echo recovered_group('复制内容', '<label><input name="content[]" type="checkbox" value="anounce" checked/> 首页公告</label><br/><label><input name="content[]" type="checkbox" value="modal" checked/> 弹出公告</label><br/><label><input name="content[]" type="checkbox" value="bottom" checked/> 底部排版</label><br/><label><input name="content[]" type="checkbox" value="alert" checked/> 下单提示</label><br/><label><input name="content[]" type="checkbox" value="gg_search" checked/> 订单查询公告</label><br/><label><input name="content[]" type="checkbox" value="gg_panel" checked/> 分站后台公告</label>');
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';
} elseif ($mod === 'epay') {
    if (empty($conf['epay_pid']) || empty($conf['epay_key'])) {
        showmsg('你还未填写彩虹易支付商户ID和密钥，请返回填写！', 3);
    }
    $arr = recovered_epay_query('query');
    if (!$arr || (isset($arr['code']) && intval($arr['code']) !== 1 && intval($arr['code']) !== 0)) {
        showmsg('易支付KEY校验失败！', 3);
    }
    if (isset($arr['active']) && intval($arr['active']) === 0) {
        showmsg('该商户已被封禁', 3);
    }
    $money = isset($arr['money']) ? $arr['money'] : '';
    $stype = isset($arr['type']) ? $arr['type'] : (isset($arr['stype']) ? $arr['stype'] : '');
    $account = isset($arr['account']) ? $arr['account'] : '';
    $username = isset($arr['username']) ? $arr['username'] : '';
    echo '<div class="block-title"><h3 class="panel-title">彩虹易支付设置</h3></div>';
    echo '<ul class="nav nav-tabs"><li class="active"><a href="#">彩虹易支付设置</a></li><li><a href="./set.php?mod=epay_order">订单记录</a></li><li><a href="./set.php?mod=epay_settle">结算记录</a></li></ul>';
    echo '<form action="./set.php?mod=epay_n" method="post" class="form-horizontal" role="form">';
    echo '<h4>商户信息查看：</h4>';
    echo recovered_group('商户ID', '<input type="text" name="pid" value="' . htmlspecialchars((string) $conf['epay_pid']) . '" class="form-control" disabled/>');
    echo recovered_group('商户KEY', '<input type="text" name="key" value="****************" class="form-control" disabled/>');
    echo recovered_group('商户余额', '<input type="text" name="money" value="' . htmlspecialchars((string) $money) . '" class="form-control" disabled/>');
    echo '<h4>收款账号设置：</h4>';
    echo recovered_group('结算方式', '<input type="text" name="type" value="' . htmlspecialchars((string) $stype) . '" class="form-control"/>');
    echo recovered_group('结算账号', '<input type="text" name="account" value="' . htmlspecialchars((string) $account) . '" class="form-control"/>');
    echo recovered_group('真实姓名', '<input type="text" name="username" value="' . htmlspecialchars((string) $username) . '" class="form-control"/>');
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="确定修改" class="btn btn-primary btn-block"/></div></div>';
    echo '<h4><span class="glyphicon glyphicon-info-sign"></span> 注意事项</h4><p>1.相关信息请到商户网站进行修改，此处信息仅供参考！</p></form>';
} elseif ($mod === 'epay_settle') {
    $arr = recovered_epay_query('settle', '&limit=20');
    echo '<div class="block-title w h"><h3 class="panel-title">彩虹易支付结算记录</h3></div>';
    echo '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>ID</th><th>结算账号</th><th>结算金额</th><th>手续费</th><th>结算时间</th></tr></thead><tbody>';
    $rows = isset($arr['data']) && is_array($arr['data']) ? $arr['data'] : [];
    foreach ($rows as $row) {
        echo '<tr><td><b>' . htmlspecialchars((string) $row['id']) . '</b></td><td>' . htmlspecialchars((string) $row['account']) . '</td><td><b>' . htmlspecialchars((string) $row['money']) . '</b></td><td><b>' . htmlspecialchars((string) $row['fee']) . '</b></td><td>' . htmlspecialchars((string) $row['time']) . '</td></tr>';
    }
    echo '</tbody></table></div>';
} elseif ($mod === 'epay_order') {
    $arr = recovered_epay_query('orders', '&limit=30');
    echo '<div class="block-title"><h3 class="panel-title">彩虹易支付订单记录</h3></div>订单只展示前30条[<a href="set.php?mod=epay">返回</a>]';
    echo '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>交易号/商户订单号</th><th>付款方式</th><th>商品名称/金额</th><th>创建时间/完成时间</th><th>状态</th></tr></thead><tbody>';
    $rows = isset($arr['data']) && is_array($arr['data']) ? $arr['data'] : [];
    foreach ($rows as $row) {
        $st = isset($row['status']) && intval($row['status']) === 1 ? '<font color=green>已完成</font>' : '<font color=red>未完成</font>';
        echo '<tr><td>' . htmlspecialchars((string) $row['trade_no']) . '<br/>' . htmlspecialchars((string) $row['out_trade_no']) . '</td><td>' . htmlspecialchars((string) $row['type']) . '</td><td>' . htmlspecialchars((string) $row['name']) . '<br/>￥ <b>' . htmlspecialchars((string) $row['money']) . '</b></td><td>' . htmlspecialchars((string) $row['addtime']) . '<br/>' . htmlspecialchars((string) $row['endtime']) . '</td><td>' . $st . '</td></tr>';
    }
    echo '</tbody></table></div>';
} elseif ($mod === 'pay' || $mod === 'alipay' || $mod === 'alipay2' || $mod === 'qqpay' || $mod === 'wxpay' || $mod === 'codepay') {
    echo '<div class="block-title"><h3 class="panel-title">支付接口配置</h3></div>';
    echo '<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">';
    echo recovered_group('支付宝接口', recovered_select('alipay_api', ['0' => '关闭', '1' => '支付宝电脑+手机网站支付', '3' => '支付宝当面付扫码支付', '2' => '彩虹易支付接口', '7' => '卡易信笔笔清支付宝接口']));
    echo recovered_group('QQ钱包接口', recovered_select('qqpay_api', ['0' => '关闭', '1' => 'QQ钱包官方支付接口', '2' => '彩虹易支付接口', '8' => '彩虹易支付接口(备用1)']));
    echo recovered_group('微信支付接口', recovered_select('wxpay_api', ['0' => '关闭', '1' => '微信官方扫码+公众号支付接口', '3' => '微信官方扫码+H5支付接口', '2' => '彩虹易支付接口', '8' => '彩虹易支付接口(备用1)', '9' => '彩虹易支付接口(备用2)']));
    echo recovered_group('订单名称格式', recovered_input('ordername'));
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';

    echo '<div class="block-title"><h3 class="panel-title">支付宝官方支付接口配置</h3></div>';
    echo '<form action="./set.php?mod=alipay_n" method="post" class="form-horizontal" role="form">';
    echo recovered_group('APPID', recovered_input('alipay_appid'));
    echo recovered_group('支付宝公钥', recovered_textarea('alipay_publickey', 4));
    echo recovered_group('应用私钥', recovered_textarea('alipay_privatekey', 4));
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';

    echo '<div class="block-title"><h3 class="panel-title">卡易信支付宝接口配置</h3></div>';
    echo '<form action="./set.php?mod=alipay2_n" method="post" class="form-horizontal" role="form">';
    echo recovered_group('网关', recovered_input('alipay_getway'));
    echo recovered_group('商户PID', recovered_input('alipay_pid'));
    echo recovered_group('商户KEY', recovered_input('alipay_key'));
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';

    echo '<div class="block-title"><h3 class="panel-title">QQ钱包官方接口配置</h3></div>';
    echo '<form action="./set.php?mod=qqpay_n" method="post" class="form-horizontal" role="form">';
    echo recovered_group('商户号', recovered_input('qqpay_mchid'));
    echo recovered_group('商户密钥', recovered_input('qqpay_key'));
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';

    echo '<div class="block-title"><h3 class="panel-title">微信支付官方接口配置</h3></div>';
    echo '<form action="./set.php?mod=wxpay_n" method="post" class="form-horizontal" role="form">';
    echo recovered_group('APPID', recovered_input('wxpay_appid'));
    echo recovered_group('商户号', recovered_input('wxpay_mchid'));
    echo recovered_group('商户密钥', recovered_input('wxpay_key'));
    echo recovered_group('APPSECRET', recovered_input('wxpay_appsecret'));
    echo recovered_group('授权域名', recovered_input('wxpay_domain'));
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';

    echo '<div class="block-title"><h3 class="panel-title">彩虹易支付配置</h3></div>';
    if (file_exists(ROOT . 'admin/pay.lock')) {
        echo '<div class="alert alert-warning">为保障你的资金安全，如需修改支付商户和密钥，请删除<font color=red> admin/pay.lock </font>文件后再修改！</div>';
    }
    echo '<form action="./set.php?mod=epay_n" method="post" class="form-horizontal" role="form">';
    echo recovered_group('接口地址', recovered_input('epay_url', '请填写接口网址'));
    echo recovered_group('商户ID', recovered_input('epay_pid'));
    echo recovered_group('商户密钥', recovered_input('epay_key'));
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><a href="set.php?mod=epay">进入易支付结算设置及订单查询页面</a></div></div>';
    echo '<div class="block-title"><h3 class="panel-title">彩虹易支付（备用1）配置</h3></div>';
    echo recovered_group('接口地址', recovered_input('epay_url2'));
    echo recovered_group('商户ID', recovered_input('epay_pid2'));
    echo recovered_group('商户密钥', recovered_input('epay_key2'));
    echo '<div class="block-title"><h3 class="panel-title">彩虹易支付（备用2）配置</h3></div>';
    echo recovered_group('接口地址', recovered_input('epay_url3'));
    echo recovered_group('商户ID', recovered_input('epay_pid3'));
    echo recovered_group('商户密钥', recovered_input('epay_key3'));
    echo '<p>风险提示：为保障您的资金安全，请勿使用非官方认证的易支付接口！</p>';
    echo '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/></div></div></form>';
} elseif ($mod === 'upimg' || $mod === 'setimg') {
    echo '<div class="block"><div class="block-title"><h3 class="panel-title">更改首页LOGO</h3><div class="block-options pull-right"><a class="btn btn-default" href="set.php?mod=upbgimg">更改背景图</a></div></div>';
    echo '<form action="set.php?mod=upimg" method="POST" enctype="multipart/form-data"><label for="file"></label><input type="file" name="file" id="file" /><input type="hidden" name="s" value="1" /><br><input type="submit" name="submit" value="上传" class="btn btn-primary"/></form>';
    if (file_exists(ROOT . 'assets/img/logo.png')) {
        echo '<br/><img src="../assets/img/logo.png" style="max-width:100%">';
    }
} elseif ($mod === 'upbgimg' || $mod === 'bj') {
    echo '<div class="block-title"><h3 class="panel-title">更改首页背景图</h3></div>';
    echo '<form action="set.php?mod=upbgimg" method="POST" enctype="multipart/form-data"><input type="hidden" name="s" value="1" /><div class="form-group"><label for="file"></label><input type="file" name="file" id="file" /></div><input type="submit" name="submit" value="上传" class="btn btn-primary"/></form>';
} elseif ($mod === 'upwxqrcode') {
    echo '<div class="block"><div class="block-title"><h3 class="panel-title">上传客服微信二维码</h3></div>';
    echo '<form action="set.php?mod=upwxqrcode" method="POST" enctype="multipart/form-data"><input type="hidden" name="s" value="1" /><input type="file" name="file" /><br><input type="submit" name="submit" value="上传" class="btn btn-primary"/></form>';
    echo '<p><a href="./set.php?mod=delwxqrcode" onclick="return confirm(\'确定删除？\');">删除二维码</a></p>';
} else {
    echo '<div class="block-title"><h3 class="panel-title">系统设置</h3></div><p>未知模块：' . htmlspecialchars($mod) . '</p>';
}

echo '</div></div>';
echo '<script src="' . $cdnpublic . 'layer/3.1.1/layer.js"></script>
<script>
var items = $("select[default]");
for (var i = 0; i < items.length; i++) {
	$(items[i]).val($(items[i]).attr("default") || 0);
}
function saveSetting(obj){
	if($("input[name=\'fenzhan_domain\']").length>0){
		var fenzhan_domain = $("input[name=\'fenzhan_domain\']").val();
		$("input[name=\'fenzhan_domain\']").val(fenzhan_domain.replace("，",","));
	}
	if($("input[name=\'fenzhan_remain\']").length>0){
		var fenzhan_remain = $("input[name=\'fenzhan_remain\']").val();
		$("input[name=\'fenzhan_remain\']").val(fenzhan_remain.replace("，",","));
	}
	var ii = layer.load(2, {shade:[0.1,\'#fff\']});
	$.ajax({
		type : "POST",
		url : "ajax.php?act=set",
		data : $(obj).serialize(),
		dataType : "json",
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert("设置保存成功！", {icon: 1, closeBtn: false});
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		},
		error:function(){
			layer.close(ii);
			layer.msg("服务器错误");
		}
	});
	return false;
}
function changeTemplate(template){
	var ii = layer.load(2, {shade:[0.1,\'#fff\']});
	$.ajax({
		type : "POST",
		url : "ajax.php?act=set",
		data : {template:template, template_m:"0"},
		dataType : "json",
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert("更换模板成功！", {icon: 1, closeBtn: false}, function(){ window.location.reload(); });
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		}
	});
}
function thirdloginbind(type){
	var typename = type=="qq"?"QQ":"微信";
	layer.open({
	   type: 2,
	   title: "绑定"+typename+"登录",
	   shadeClose: true,
	   closeBtn:2,
	   scrollbar: false,
	   area: ["310px", "450px"],
	   content: "./bind.php?type="+type
	});
}
function thirdloginunbind(type){
	var typename = type=="qq"?"QQ":"微信";
	var confirmobj = layer.confirm("确定要解绑"+typename+"吗？", {
	  btn: ["确定","取消"]
	}, function(){
		$.post("ajax.php?act=thirdloginunbind", {type:type}, function(arr) {
			if(arr.code==0) {
				layer.alert(typename+"解绑成功！", {icon:1}, function(){ window.location.reload(); });
			}
		}, "json");
	  layer.close(confirmobj);
	});
}
function Addstr(id, str) {
	$("#"+id).val($("#"+id).val()+str);
}
function checkURL(obj)
{
	var url = $(obj).val();
	if (url.indexOf(" ")>=0){
		url = url.replace(/ /g,"");
	}
	if (url.toLowerCase().indexOf("http://")<0 && url.toLowerCase().indexOf("https://")<0){
		url = "http://"+url;
	}
	if (url.slice(url.length-1)!="/"){
		url = url+"/";
	}
	$(obj).val(url);
}
function checkepayurl(var1,var2){
	if($("select[name=\'"+var1+"\']").val() == -1){
		checkURL("input[name=\'"+var2+"\']");
	}
	return true;
}
</script>
';
include './footer.php';
