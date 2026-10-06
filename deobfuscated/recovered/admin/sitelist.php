<?php
/**
 * Recovered admin/sitelist.php (goto-flattened original).
 * Table rows are loaded via sitelist-table.php + assets/js/sitelist.js.
 * Not wired into the live admin path until a side-by-side check passes.
 */
include '../includes/common.php';
$title = '分站管理';
include './head.php';
if ($islogin == 1) {
} else {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

adminpermission('site', 1);

echo "<style>\r
.form-inline .form-control {\r
    display: inline-block;\r
    width: auto;\r
    vertical-align: middle;\r
}\r
.form-inline .form-group {\r
    margin-bottom: 0;\r
</style>\r
    <div class=\"col-md-12 center-block\" style=\"float: none;\">\r
";

$my = isset($_GET['my']) ? $_GET['my'] : null;
$endDefault = date('Y-m-d', strtotime('+1 years'));

$domainTaken = static function ($domain) use ($DB, $conf) {
    if ($domain === $_SERVER['HTTP_HOST']) {
        return true;
    }
    $remain = isset($conf['fenzhan_remain']) ? explode('|', $conf['fenzhan_remain']) : [];
    if (in_array($domain, $remain, true)) {
        return true;
    }
    return (bool) $DB->getRow("select * from pre_site where domain='{$domain}' or domain2='{$domain}' limit 1");
};

if ($my === 'replace') {
    echo '<div class="block">
<div class="block-title"><h3 class="panel-title">分站域名批量修改</h3></div>
<div class="">
<form action="./sitelist.php?my=replace_do" method="POST">
<div class="form-group">
	<div class="input-group"><div class="input-group-addon">原域名</div>
	<input type="text" name="olddomain" value="" class="form-control" placeholder="只填写主域名，例如：domain.com" required/>
</div></div>
<div class="form-group">
	<div class="input-group"><div class="input-group-addon">替换为</div>
	<input type="text" name="newdomain" value="" class="form-control" placeholder="只填写主域名，例如：domain.com" required/>
</div></div>
<input type="submit" class="btn btn-primary btn-block" value="确定修改"></form>
<br/><a href="./sitelist.php">>>返回分站列表</a>
</div></div>';
} elseif ($my === 'replace_do') {
    $olddomain = trim($_POST['olddomain']);
    $newdomain = trim($_POST['newdomain']);
    if ($olddomain === '' || $newdomain === '') {
        showmsg('请确保每项都不为空！', 3);
    } elseif ($olddomain === $newdomain) {
        showmsg('原域名和新域名不能一样！', 3);
    } elseif (!preg_match('/^[a-zA-Z0-9\.\-\_]+$/', $newdomain) || strpos($newdomain, '.') === false) {
        showmsg('新域名不合法', 3);
    } else {
        $ok = $DB->exec("update pre_site set domain = replace(domain,'{$olddomain}','{$newdomain}'), domain2 = replace(domain2,'{$olddomain}','{$newdomain}')");
        if ($ok !== false) {
            showmsg('批量修改域名成功！<br/><br/><a href="./sitelist.php">>>返回分站列表</a>', 1);
        } else {
            showmsg('批量修改域名失败！' . $DB->error(), 4);
        }
    }
} elseif ($my === 'add') {
    echo '<div class="block">
<div class="block-title"><h3 class="panel-title">添加一个分站</h3></div>
<div class="">
<form action="./sitelist.php?my=add_submit" method="POST">
<div class="form-group">
<label>分站类型:</label><br>
<select class="form-control" name="power"><option value="1">普及版</option><option value="2">专业版</option></select>
</div>
<div class="form-group">
<label>管理员用户名:</label><br>
<input type="text" class="form-control" name="user" value="" required>
</div>
<div class="form-group">
<label>管理员密码:</label><br>
<input type="text" class="form-control" name="pwd" value="123456" required>
</div>
<div class="form-group">
<label>绑定域名:</label><br>
<input type="text" class="form-control" name="domain" value="" placeholder="分站要用的域名" required>
</div>
<div class="form-group">
<label>站点余额:</label><br>
<input type="text" class="form-control" name="rmb" value="0" required>
</div>
<div class="form-group">
<label>站长QQ:</label><br>
<input type="text" class="form-control" name="qq" value="">
</div>
<div class="form-group">
<label>到期时间:</label><br>
<input type="date" class="form-control" name="endtime" value="' . $endDefault . '" required>
</div>
<input type="submit" class="btn btn-primary btn-block" value="确定添加"></form>
<br/><a href="./sitelist.php">>>返回分站列表</a>
</div></div>';
} elseif ($my === 'add2') {
    $zid = intval($_GET['zid']);
    $row = $DB->getRow("select * from pre_site where zid='{$zid}' limit 1");
    if (!$row) {
        showmsg('当前记录不存在！', 3);
    }
    echo '<div class="block">
<div class="block-title"><h3 class="panel-title">添加一个分站</h3></div>
<div class="">
<form action="./sitelist.php?my=add2_submit&zid=' . $zid . '" method="POST">
<div class="form-group">
<label>分站类型:</label><br>
<select class="form-control" name="power"><option value="1">普及版</option><option value="2">专业版</option></select>
</div>
<div class="form-group">
<label>绑定域名:</label><br>
<input type="text" class="form-control" name="domain" value="" placeholder="分站要用的域名" required>
</div>
<div class="form-group">
<label>到期时间:</label><br>
<input type="date" class="form-control" name="endtime" value="' . $endDefault . '" required>
</div>
<input type="submit" class="btn btn-primary btn-block" value="确定添加"></form>
<br/><a href="./userlist.php">>>返回用户列表</a>
</div></div>';
} elseif ($my === 'edit') {
    $zid = intval($_GET['zid']);
    $row = $DB->getRow("select * from pre_site where zid='{$zid}' limit 1");
    if (!$row) {
        showmsg('当前记录不存在！', 3);
    }
    $endtime = substr((string) $row['endtime'], 0, 10);
    echo '<div class="block">
<div class="block-title"><h3 class="panel-title">修改分站信息</h3><span class="pull-right"><a href="./siteprice.php?zid=' . $zid . '" class="btn btn-default">自定义密价</a></div>
<form action="./sitelist.php?my=edit_submit&zid=' . $zid . '" method="POST">
<div class="form-group">
<label>分站类型:</label><br>
<select class="form-control" name="power" default="' . htmlspecialchars((string) $row['power']) . '"><option value="1">普及版</option><option value="2">专业版</option></select>
</div>
<div class="form-group">
<label>上级站点ID:</label><br>
<input type="text" class="form-control" name="upzid" value="' . htmlspecialchars((string) $row['upzid']) . '" disabled>
</div>
<div class="form-group">
<label>绑定域名:</label><br>
<input type="text" class="form-control" name="domain" value="' . htmlspecialchars((string) $row['domain']) . '" required>
</div>
<div class="form-group">
<label>额外域名:</label><br>
<input type="text" class="form-control" name="domain2" value="' . htmlspecialchars((string) $row['domain2']) . '">
</div>
<div class="form-group">
<label>站点总余额:</label><br>
<input type="text" class="form-control" name="rmb" value="' . htmlspecialchars((string) $row['rmb']) . '" required>
</div>
';
    if (!empty($conf['tixian_limit'])) {
        echo '<div class="form-group">
<label>其中可提现余额:</label><br>
<input type="text" class="form-control" name="rmbtc" value="' . htmlspecialchars((string) $row['rmbtc']) . '">
</div>
';
    }
    echo '<div class="form-group">
<label>站长QQ:</label><br>
<input type="text" class="form-control" name="qq" value="' . htmlspecialchars((string) $row['qq']) . '">
</div>
<div class="form-group">
<label>QQ快捷登录Openid:</label><br>
<input type="text" class="form-control" name="qq_openid" value="' . htmlspecialchars((string) $row['qq_openid']) . '">
</div>
<div class="form-group">
<label>微信快捷登录Openid:</label><br>
<input type="text" class="form-control" name="wx_openid" value="' . htmlspecialchars((string) $row['wx_openid']) . '">
</div>
<div class="form-group">
<label>站点名称:</label><br>
<input type="text" class="form-control" name="sitename" value="' . htmlspecialchars((string) $row['sitename']) . '">
</div>
<div class="form-group">
<label>提现方式:</label><br>
<select class="form-control" name="pay_type" default="' . htmlspecialchars((string) $row['pay_type']) . '"><option value="0">支付宝</option><option value="1">微信</option><option value="2">QQ钱包</option></select>
</div>
<div class="form-group">
<label>提现账号:</label><br>
<input type="text" class="form-control" name="pay_account" value="' . htmlspecialchars((string) $row['pay_account']) . '">
</div>
<div class="form-group">
<label>提现姓名:</label><br>
<input type="text" class="form-control" name="pay_name" value="' . htmlspecialchars((string) $row['pay_name']) . '">
</div>
<div class="form-group">
<label>到期时间:</label><br>
<input type="date" class="form-control" name="endtime" value="' . htmlspecialchars($endtime) . '" required>
</div>
<div class="form-group">
<label>重置密码:</label><br>
<input type="text" class="form-control" name="pwd" value="" placeholder="不重置请留空">
</div>
<input type="submit" class="btn btn-primary btn-block" value="确定修改"></form>
<script>
var items = $("select[default]");
for (i = 0; i < items.length; i++) {
	$(items[i]).val($(items[i]).attr("default")||0);
}
</script></div></div>';
} elseif ($my === 'add_submit') {
    $power = intval($_POST['power']);
    $user = trim($_POST['user']);
    $pwd = trim($_POST['pwd']);
    $domain = strtolower(trim($_POST['domain']));
    $domain2 = isset($_POST['domain2']) ? strtolower(trim($_POST['domain2'])) : '';
    $rmb = trim($_POST['rmb']);
    $qq = isset($_POST['qq']) ? trim($_POST['qq']) : '';
    $endtime = trim($_POST['endtime']);
    $sitename = isset($conf['sitename']) ? $conf['sitename'] : $user;
    $title = isset($conf['title']) ? $conf['title'] : $sitename;
    $keywords = isset($conf['keywords']) ? $conf['keywords'] : '';
    $description = isset($conf['description']) ? $conf['description'] : '';
    if ($user === '' || $pwd === '' || $domain === '' || $endtime === '') {
        showmsg('保存错误,请确保每项都不为空!', 3);
    } elseif (!preg_match('/^[a-zA-Z0-9\.\-\_]+$/', $domain) || strpos($domain, '.') === false) {
        showmsg('绑定域名填写错误', 3);
    } elseif ($DB->getRow("select user from pre_site where user='{$user}' limit 1")) {
        showmsg('用户名已存在！', 3);
    } elseif ($domainTaken($domain)) {
        showmsg('域名已存在！', 3);
    } else {
        $sql = 'INSERT INTO `pre_site` (`power`,`domain`,`domain2`,`user`,`pwd`,`rmb`,`qq`,`sitename`,`title`,`keywords`,`description`,`kfqq`,`addtime`,`endtime`,`status`) VALUES (:power, :domain, :domain2, :user, :pwd, :rmb, :qq, :sitename, :title, :keywords, :description, :kfqq, NOW(), :endtime, 1)';
        $ok = $DB->exec($sql, [
            ':power' => $power,
            ':domain' => $domain,
            ':domain2' => $domain2,
            ':user' => $user,
            ':pwd' => $pwd,
            ':rmb' => $rmb,
            ':qq' => $qq,
            ':sitename' => $sitename,
            ':title' => $title,
            ':keywords' => $keywords,
            ':description' => $description,
            ':kfqq' => $qq,
            ':endtime' => $endtime,
        ]);
        if ($ok) {
            showmsg('添加分站成功！<br/><br/><a href="./sitelist.php">>>返回分站列表</a>', 1);
        } else {
            showmsg('添加分站失败！' . $DB->error(), 4);
        }
    }
} elseif ($my === 'add2_submit') {
    $zid = intval($_GET['zid']);
    if (!$DB->getColumn("select zid from pre_site where zid='{$zid}' limit 1")) {
        showmsg('当前记录不存在！', 3);
    }
    $power = intval($_POST['power']);
    $domain = strtolower(trim($_POST['domain']));
    $domain2 = isset($_POST['domain2']) ? strtolower(trim($_POST['domain2'])) : '';
    $endtime = trim($_POST['endtime']);
    $sitename = isset($conf['sitename']) ? $conf['sitename'] : '';
    $title = isset($conf['title']) ? $conf['title'] : $sitename;
    $keywords = isset($conf['keywords']) ? $conf['keywords'] : '';
    $description = isset($conf['description']) ? $conf['description'] : '';
    if ($domain === '' || $endtime === '') {
        showmsg('保存错误,请确保每项都不为空!', 3);
    } elseif (!preg_match('/^[a-zA-Z0-9\.\-\_]+$/', $domain) || strpos($domain, '.') === false) {
        showmsg('绑定域名填写错误', 3);
    } elseif ($DB->getColumn("select zid from pre_site where domain='{$domain}' limit 1") || $domainTaken($domain)) {
        showmsg('域名已存在！', 3);
    } else {
        $sql = 'UPDATE `pre_site` SET `power`=:power,`domain`=:domain,`domain2`=:domain2,`sitename`=:sitename,`title`=:title,`keywords`=:keywords,`description`=:description,`kfqq`=`qq`,`endtime`=:endtime WHERE `zid`=:zid';
        $ok = $DB->exec($sql, [
            ':power' => $power,
            ':domain' => $domain,
            ':domain2' => $domain2,
            ':sitename' => $sitename,
            ':title' => $title,
            ':keywords' => $keywords,
            ':description' => $description,
            ':endtime' => $endtime,
            ':zid' => $zid,
        ]);
        if ($ok !== false) {
            showmsg('添加分站成功！<br/><br/><a href="./userlist.php">>>返回用户列表</a><br/><a href="./sitelist.php">>>返回分站列表</a>', 1);
        } else {
            showmsg('添加分站失败！' . $DB->error(), 4);
        }
    }
} elseif ($my === 'edit_submit') {
    $zid = intval($_GET['zid']);
    $old = $DB->getRow("select zid,rmb,rmbtc from pre_site where zid='{$zid}' limit 1");
    if (!$old) {
        showmsg('当前记录不存在！', 3);
    }
    $power = intval($_POST['power']);
    $domain = strtolower(trim($_POST['domain']));
    $domain2 = isset($_POST['domain2']) ? strtolower(trim($_POST['domain2'])) : '';
    $rmb = trim($_POST['rmb']);
    $rmbtc = isset($_POST['rmbtc']) ? trim($_POST['rmbtc']) : $old['rmbtc'];
    $qq = isset($_POST['qq']) ? trim($_POST['qq']) : '';
    $qq_openid = isset($_POST['qq_openid']) ? trim($_POST['qq_openid']) : '';
    $wx_openid = isset($_POST['wx_openid']) ? trim($_POST['wx_openid']) : '';
    $sitename = isset($_POST['sitename']) ? trim($_POST['sitename']) : '';
    $pay_type = isset($_POST['pay_type']) ? intval($_POST['pay_type']) : 0;
    $pay_account = isset($_POST['pay_account']) ? trim($_POST['pay_account']) : '';
    $pay_name = isset($_POST['pay_name']) ? trim($_POST['pay_name']) : '';
    $endtime = trim($_POST['endtime']);
    $pwd = isset($_POST['pwd']) ? trim($_POST['pwd']) : '';
    if ($domain === '' || $rmb === '' || $endtime === '') {
        showmsg('保存错误,请确保每项都不为空!', 3);
    }
    $sql = 'UPDATE pre_site SET power=:power,domain=:domain,domain2=:domain2,qq_openid=:qq_openid,wx_openid=:wx_openid,rmb=:rmb,rmbtc=:rmbtc,qq=:qq,sitename=:sitename,pay_type=:pay_type,pay_account=:pay_account,pay_name=:pay_name,endtime=:endtime';
    $params = [
        ':power' => $power,
        ':domain' => $domain,
        ':domain2' => $domain2,
        ':qq_openid' => $qq_openid,
        ':wx_openid' => $wx_openid,
        ':rmb' => $rmb,
        ':rmbtc' => $rmbtc,
        ':qq' => $qq,
        ':sitename' => $sitename,
        ':pay_type' => $pay_type,
        ':pay_account' => $pay_account,
        ':pay_name' => $pay_name,
        ':endtime' => $endtime,
        ':zid' => $zid,
    ];
    if ($pwd !== '') {
        $sql .= ",pwd='{$pwd}'";
    }
    $sql .= ' WHERE zid=:zid';
    $ok = $DB->exec($sql, $params);
    if ($ok !== false) {
        showmsg('修改分站成功！<br/><br/><a href="./sitelist.php">>>返回分站列表</a>', 1);
    } else {
        showmsg('修改分站失败！' . $DB->error(), 4);
    }
} elseif ($my === 'announce') {
    $n = $DB->exec('UPDATE pre_site SET `anounce`=NULL,`modal`=NULL,`bottom`=NULL,`alert`=NULL');
    if ($n !== false) {
        showmsg('清空所有分站公告代码成功！影响行数：' . $n . '<br/><br/><a href="./sitelist.php">>>返回分站列表</a>', 1);
    } else {
        showmsg('清空所有分站公告代码失败！' . $DB->error(), 4);
    }
} elseif ($my === 'price') {
    $n = $DB->exec('UPDATE pre_site SET `price`=NULL,`class`=NULL');
    if ($n !== false) {
        showmsg('清空所有分站商品价格设置成功！影响行数：' . $n . '<br/><br/><a href="./sitelist.php">>>返回分站列表</a>', 1);
    } else {
        showmsg('清空所有分站商品价格设置失败！' . $DB->error(), 4);
    }
} else {
    $numrows = $DB->getColumn('SELECT count(*) FROM pre_site WHERE power>0');
    echo '<div class="modal" align="left" id="search" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
				<h4 class="modal-title" id="myModalLabel">搜索分站</h4>
			</div>
			<div class="modal-body">
				<input type="text" class="form-control" name="kw" placeholder="请输入分站用户名、域名"><br/>
				<button type="button" class="btn btn-primary btn-block" id="search_submit">搜索</button>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
		</div>
	</div>
</div>
<div class="modal" align="left" id="search2" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
        <h4 class="modal-title" id="myModalLabel">分类查看</h4>
      </div>
      <div class="modal-body">
<select name="power" class="form-control"><option value="1">普及版</option><option value="2">专业版</option></select><br/>
<button type="button" class="btn btn-primary btn-block" id="search2_submit">查看</button>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
    </div>
  </div>
<div class="modal" id="modal-rmb">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
				<h4 class="modal-title">余额充值</h4>
			</div>
			<div class="modal-body">
				<form id="form-rmb">
					<input type="hidden" name="zid" value="">
					<div class="form-group">
						<div class="input-group">
							<span class="input-group-btn">
								<select name="do" class="form-control" style="padding: 6px 4px;width:80px">
									<option value="0">充值</option>
									<option value="1">扣除</option>
								</select>
							</span>
							<input type="number" class="form-control" name="rmb" placeholder="输入金额">
							<span class="input-group-addon">元</span>
						</div>
					</div>
							<span class="input-group-addon">备注信息</span>
							<input type="text" class="form-control" name="remark" placeholder="可留空">
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-info" data-dismiss="modal">取消</button>
				<button type="button" class="btn btn-primary" id="recharge">确定</button>
			</div>
		</div>
	</div>
</div>
<div class="block">
<div class="block-title clearfix">
<h2>系统共有 <b>' . $numrows . '</b> 个分站</h2>
<a href="./sitelist.php?my=add" class="btn btn-primary">添加分站</a>&nbsp;<a href="#" data-toggle="modal" data-target="#search" id="search" class="btn btn-success">搜索</a>&nbsp;<a href="#" data-toggle="modal" data-target="#search2" id="search2" class="btn btn-warning">分类查看</a>&nbsp;<div class="btn-group">
  <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    更多 <span class="caret"></span>
  </button>
  <ul class="dropdown-menu">
    <li><a href="./sitelist.php?my=replace">分站域名批量修改</a></li>
	<li><a href="./sitelist.php?my=announce" onclick="return confirm(\'你确实要清空所有分站公告代码吗？清空后默认都显示主站的公告内容\');">一键清空所有分站公告代码</a></li>
	<li><a href="./sitelist.php?my=price" onclick="return confirm(\'你确实要清空所有分站所有分站商品价格设置？清空后默认都恢复到主站设置的商品价格\');">一键清空所有分站商品价格设置</a></li>
  </ul>
</div>&nbsp;
<label class="form-inline" for="tabSort" style="font-weight: normal;">按余额&nbsp;<select class="form-control" id="tabSort" style="font-weight: normal;"><option value="">请选择</option><option value="0">正序</option><option value="1">倒序</option></select>
</label>&nbsp;
<a href="javascript:listTable(\'start\')" class="btn btn-default" title="刷新分站列表"><i class="fa fa-refresh"></i></a>
<div id="listTable"></div>
<script src="' . $cdnpublic . 'layer/3.1.1/layer.js"></script>
<script src="assets/js/sitelist.js?ver=' . VERSION . '"></script>
</body>
</html>';
}
