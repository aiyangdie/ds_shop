<?php
/**
 * Recovered admin/shequlist.php (goto-flattened original).
 * Not wired into the live admin path until a side-by-side check passes.
 */
include '../includes/common.php';
$title = '网站对接配置';
include './head.php';
if ($islogin == 1) {
} else {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

adminpermission('shequ', 1);

echo '    <div class="col-sm-12 col-md-10 center-block" style="float: none;">' . "\r\n";

$plugins = \lib\Plugin::getThirdPluginsList();
$pluginMap = [];
$pluginJs = [];
$typeOptions = '';
foreach ($plugins as $plugin) {
    $code = $plugin['code'];
    $pluginMap[$code] = $plugin;
    $pluginJs[$code] = [
        'input' => isset($plugin['input']) ? $plugin['input'] : [],
        'showip' => !empty($plugin['showip']),
    ];
    $typeOptions .= '<option value="' . htmlspecialchars((string) $code) . '">'
        . htmlspecialchars((string) $plugin['title']) . '</option>';
}

$my = isset($_GET['my']) ? $_GET['my'] : null;

$shequJs = '<script src="//cdnjs.kinqin.com/layer/3.0.1/layer.js"></script>
<script>
var pluginArray = ' . json_encode($pluginJs, JSON_UNESCAPED_UNICODE) . ';
function confirmdel(isclone){
	if(isclone == 1){
		return confirm(\'删除此对接站点会导致克隆的商品对接下单失败，你确实要删除此对接站点吗？\');
	}else{
		return confirm(\'你确实要删除此对接站点吗？\');
	}
}
function checkurl(){
	var url = $("input[name=\'url\']").val();
	if(url == \'\'){layer.alert(\'请先填写网站域名！\');return false;}
	if(url.indexOf(\'http://\')<0 && url.indexOf(\'https://\')<0 && url.substr(-1) != \'/\'){
		var ii = layer.load(2, {shade:[0.1,\'#fff\']});
		$.ajax({
			type : "POST",
			url : "ajax.php?act=checkshequ",
			data : {url:url},
			dataType : \'json\',
			success : function(data) {
				layer.close(ii);
				if(data.code == 1){
					layer.msg(\'连通性良好\');
				}else{
					layer.alert(\'该网站由于防火墙原因国外主机无法连接，请使用国内主机\');
				}
			} ,
			error:function(data){
				layer.msg(\'目标社区连接超时\');
				return false;
			}
		});
	}else{
		layer.alert(\'网站域名不能带http和/符号，只填写域名\');
	}
}
$(document).ready(function(){
$("select[name=\'type\']").change(function(){
	var type = $(this).val();
	var plugin = pluginArray[type];
	if(plugin){
		var input = plugin.input;
		$("#url").text(input.url + \':\');
		$("#username").text(input.username + \':\');
		$("#password").text(input.password + \':\');
		if(input.paypwd){
			$("#paypwd").text(input.paypwd + \':\');
			$("#paypwd_show").show();
		}else{
			$("#paypwd_show").hide();
		}
		if(input.paytype){
			$("#paytype").text(input.paytype + \':\');
			$("#paytype_show").show();
		}else{
			$("#paytype_show").hide();
		}
		if(plugin.showip){
			$.ajax({
				type : "GET",
				url : "ajax.php?act=getServerIp",
				async: true,
				dataType : \'json\',
				success : function(data) {
					$("#alert-footer").html(\'<font color=red>请设置当前服务器IP为白名单：\'+data.ip+\'</font>\');
				}
			});
		}else{
			$("#alert-footer").html(\'\');
		}
	}
});
if($("select[name=\'type\']").length>0){
	$("select[name=\'type\']").change();
}
});
</script>';

if ($my === 'add') {
    echo '<div class="block">
<div class="block-title"><h3 class="panel-title">添加一个社区/卡盟网站对接</h3></div>
<div class="">
<form action="./shequlist.php?my=add_submit" method="POST">
<div class="form-group">
<label>对接网站类型:</label><span class="pull-right">[<a href="?my=refresh">刷新对接插件列表</a>]</span><br>
<div class="input-group">
<select class="form-control" name="type">' . $typeOptions . '</select>
<a tabindex="0" class="input-group-addon" role="button" data-toggle="popover" data-trigger="focus" title="" data-placement="bottom" data-content="对接网站类型是指要对接的网站所使用的网站程序类型，并不代表特定的网站！具体是什么类型请咨询对接网站客服"><span class="glyphicon glyphicon-info-sign"></span></a>
</div>
</div>
<div class="form-group">
<label id="url">网站域名:</label><br>
<div class="row">
<div class="col-xs-4 col-md-3" style="padding-right: 0px;">
	<select class="form-control" name="protocol"><option value="0" selected>http://</option><option value="1">https://</option></select>
</div>
<div class="col-xs-8 col-md-9" style="padding-left: 0px;">
	<input type="text" class="form-control" name="url" value="" required placeholder="输入对接网站域名">
</div>
</div>
</div>
<div class="form-group">
<label id="username">登录账号:</label><br>
<input type="text" class="form-control" name="username" value="" required>
</div>
<div class="form-group">
<label id="password">登录密码:</label><br>
<input type="text" class="form-control" name="password" value="" required>
</div>
<div class="form-group" id="paypwd_show" style="display:none;">
<label id="paypwd">支付密码:</label><br>
<input type="text" class="form-control" name="paypwd" value="" placeholder="没有请留空">
</div>
<div class="form-group" id="paytype_show" style="display:none;">
<label id="paytype">支付方式:</label><br>
<select class="form-control" name="paytype"><option value="0">点数</option><option value="1" selected>余额</option></select>
</div>
<div class="form-group">
<label>备注:</label><br>
<input type="text" class="form-control" name="remark" value="" placeholder="选填">
</div>
<input type="submit" class="btn btn-primary btn-block" value="确定添加"></form>
<br/><a href="./shequlist.php">>>返回对接列表</a>
</div>
<div id="alert-footer"></div>
</div>
' . $shequJs;
} elseif ($my === 'edit') {
    $id = intval($_GET['id']);
    $row = $DB->getRow('SELECT * FROM pre_shequ WHERE id=:id LIMIT 1', [':id' => $id]);
    if (!$row) {
        showmsg('当前记录不存在！', 3);
    }
    echo '<div class="block">
<div class="block-title"><h3 class="panel-title">修改对接网站信息</h3></div>
<form action="./shequlist.php?my=edit_submit&id=' . $id . '" method="POST">
<div class="form-group">
<label>对接网站类型:</label><span class="pull-right">[<a href="?my=refresh">刷新对接插件列表</a>]</span><br>
<div class="input-group">
<select class="form-control" name="type" default="' . htmlspecialchars((string) $row['type']) . '">' . $typeOptions . '</select>
<a tabindex="0" class="input-group-addon" role="button" data-toggle="popover" data-trigger="focus" title="" data-placement="bottom" data-content="对接网站类型是指要对接的网站所使用的网站程序类型，并不代表特定的网站！具体是什么类型请咨询对接网站客服"><span class="glyphicon glyphicon-info-sign"></span></a>
</div>
</div>
<div class="form-group">
<label id="url">网站域名:</label><br>
<div class="row">
<div class="col-xs-4 col-md-3" style="padding-right: 0px;">
	<select class="form-control" name="protocol" default="' . htmlspecialchars((string) $row['protocol']) . '"><option value="0">http://</option><option value="1">https://</option></select>
</div>
<div class="col-xs-8 col-md-9" style="padding-left: 0px;">
	<input type="text" class="form-control" name="url" value="' . htmlspecialchars((string) $row['url']) . '" required placeholder="输入对接网站域名">
</div>
</div>
</div>
<div class="form-group">
<label id="username">登录账号:</label><br>
<input type="text" class="form-control" name="username" value="' . htmlspecialchars((string) $row['username']) . '" required>
</div>
<div class="form-group">
<label id="password">登录密码:</label><br>
<input type="text" class="form-control" name="password" value="' . htmlspecialchars((string) $row['password']) . '" required>
</div>
<div class="form-group" id="paypwd_show" style="display:none;">
<label id="paypwd">支付密码:</label><br>
<input type="text" class="form-control" name="paypwd" value="' . htmlspecialchars((string) $row['paypwd']) . '" placeholder="没有请留空">
</div>
<div class="form-group" id="paytype_show" style="display:none;">
<label id="paytype">支付方式:</label><br>
<select class="form-control" name="paytype" default="' . htmlspecialchars((string) $row['paytype']) . '"><option value="0">点数</option><option value="1">余额</option></select>
</div>
<div class="form-group">
<label>下单成功后订单状态:</label><br>
<div class="input-group">
<select class="form-control" name="result" default="' . htmlspecialchars((string) $row['result']) . '"><option value="1">已完成（默认）</option><option value="2">正在处理</option></select>
<a tabindex="0" class="input-group-addon" role="button" data-toggle="popover" data-trigger="focus" title="" data-placement="bottom" data-content="下单成功后如果为正在处理，用户前台查询订单的时候会自动同步订单状态，如果对接订单已完成会自动将本站订单状态也改成已完成。"><span class="glyphicon glyphicon-info-sign"></span></a>
</div>
</div>
<div class="form-group">
<label>备注:</label><br>
<input type="text" class="form-control" name="remark" value="' . htmlspecialchars((string) $row['remark']) . '" placeholder="选填">
</div>
<input type="submit" class="btn btn-primary btn-block" value="确定修改"></form>
<script>
var items = $("select[default]");
for (i = 0; i < items.length; i++) {
	$(items[i]).val($(items[i]).attr("default")||0);
}
</script>
<br/><a href="./shequlist.php">>>返回对接列表</a>
</div>
<div id="alert-footer"></div>
</div>
' . $shequJs;
} elseif ($my === 'add_submit') {
    $url = trim($_POST['url']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $paypwd = isset($_POST['paypwd']) ? trim($_POST['paypwd']) : '';
    $paytype = isset($_POST['paytype']) ? intval($_POST['paytype']) : 0;
    $type = trim($_POST['type']);
    $remark = isset($_POST['remark']) ? trim($_POST['remark']) : '';
    $protocol = isset($_POST['protocol']) ? intval($_POST['protocol']) : 0;
    if ($url === '' || $username === '' || $password === '' || $type === '') {
        showmsg('保存错误,请确保每项都不为空!', 3);
    } elseif (strpos($url, '/') !== false) {
        showmsg('仅填写域名，不要带有/等符号', 3);
    } elseif ($DB->getRow('SELECT * FROM pre_shequ WHERE url=:url AND username=:username LIMIT 1', [
        ':url' => $url,
        ':username' => $username,
    ])) {
        showmsg('你所添加的记录已存在！', 3);
    } else {
        $sql = 'INSERT INTO `pre_shequ` (`url`,`username`,`password`,`paypwd`,`paytype`,`type`,`remark`,`protocol`) VALUES (:url, :username, :password, :paypwd, :paytype, :type, :remark, :protocol)';
        $ok = $DB->exec($sql, [
            ':url' => $url,
            ':username' => $username,
            ':password' => $password,
            ':paypwd' => $paypwd,
            ':paytype' => $paytype,
            ':type' => $type,
            ':remark' => $remark,
            ':protocol' => $protocol,
        ]);
        if ($ok) {
            showmsg('添加对接网站成功！<br/><br/><a href="./shequlist.php">>>返回对接网站列表</a>', 1);
        } else {
            showmsg('添加对接网站失败！' . $DB->error(), 4);
        }
    }
} elseif ($my === 'edit_submit') {
    $id = intval($_GET['id']);
    if (!$DB->getRow("SELECT * FROM pre_shequ WHERE id='{$id}' LIMIT 1")) {
        showmsg('当前记录不存在！', 3);
    }
    $url = trim($_POST['url']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $paypwd = isset($_POST['paypwd']) ? trim($_POST['paypwd']) : '';
    $paytype = isset($_POST['paytype']) ? intval($_POST['paytype']) : 0;
    $type = trim($_POST['type']);
    $result = isset($_POST['result']) ? intval($_POST['result']) : 1;
    $remark = isset($_POST['remark']) ? trim($_POST['remark']) : '';
    $protocol = isset($_POST['protocol']) ? intval($_POST['protocol']) : 0;
    if ($url === '' || $username === '' || $password === '' || $type === '') {
        showmsg('保存错误,请确保每项都不为空!', 3);
    } elseif (strpos($url, '/') !== false) {
        showmsg('仅填写域名，不要带有/等符号', 3);
    }
    $sql = "UPDATE pre_shequ SET url=:url,username=:username,password=:password,paypwd=:paypwd,paytype=:paytype,type=:type,result=:result,remark=:remark,protocol=:protocol,status='0' WHERE id=:id";
    $ok = $DB->exec($sql, [
        ':url' => $url,
        ':username' => $username,
        ':password' => $password,
        ':paypwd' => $paypwd,
        ':paytype' => $paytype,
        ':type' => $type,
        ':result' => $result,
        ':remark' => $remark,
        ':protocol' => $protocol,
        ':id' => $id,
    ]);
    if ($ok !== false) {
        showmsg('修改对接网站成功！<br/><br/><a href="./shequlist.php">>>返回对接网站列表</a>', 1);
    } else {
        showmsg('修改对接网站失败！' . $DB->error(), 4);
    }
} elseif ($my === 'delete') {
    $id = intval($_GET['id']);
    if ($DB->exec("DELETE FROM pre_shequ WHERE id='{$id}'") !== false) {
        showmsg('删除成功！<br/><br/><a href="./shequlist.php">>>返回对接网站列表</a>', 1);
    } else {
        showmsg('删除失败！' . $DB->error(), 4);
    }
} elseif ($my === 'refresh') {
    \lib\Plugin::refreshThirdPluginsList();
    exit("<script language='javascript'>alert('刷新对接插件列表成功！');window.history.back();</script>");
} else {
    $numrows = $DB->getColumn('SELECT count(*) FROM pre_shequ');
    echo '<div class="modal" align="left" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">关闭</span>
				</button>
				<h4 class="modal-title">对接插件说明</h4>
			</div>
			<div class="modal-body">
			对接插件目录：/includes/plugins/，请将符合要求的对接插件源码上传到该目录，插件名称必须以third_开头，然后点击 <a href="?my=refresh">刷新插件列表</a> 即可显示在对接网站类型列表中。
			<div class="modal-footer">
				<button type="button" class="btn btn-white" data-dismiss="modal">关闭</button>
		</div>
	</div>
</div>
<div class="block">
<div class="block-title clearfix">
<h2>系统共有 <b>' . $numrows . '</b> 个对接网站</h2>
<a href="./shequlist.php?my=add" class="btn btn-primary"><i class="fa fa-plus"></i>&nbsp;添加一个对接站点</a>&nbsp;<a href="javascript:$(\'#myModal\').modal(\'show\');" class="btn btn-default"><i class="fa fa-question-circle-o"></i>&nbsp;对接插件</a>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th>ID</th><th>网站域名</th><th>类型</th><th>用户名</th><th>密码</th><th>备注</th><th>操作</th></tr></thead>
          <tbody>
';
    $rs = $DB->query('SELECT * FROM pre_shequ WHERE 1 ORDER BY id ASC');
    while ($res = $rs->fetch()) {
        $code = $res['type'];
        if (isset($pluginMap[$code])) {
            $color = ($code === 'daishua') ? 'orange' : 'blue';
            $typeHtml = '<font color=' . $color . '>' . htmlspecialchars((string) $pluginMap[$code]['title']) . '</font>';
        } else {
            $typeHtml = '<font color=grey>已移除</font>';
        }
        $proto = ((int) $res['protocol'] === 1) ? 'https://' : 'http://';
        $isClone = (isset($conf['clone_shequ']) && (string) $conf['clone_shequ'] === (string) $res['id']) ? '1' : '0';
        echo '<tr><td><b>' . $res['id'] . '</b></td><td><a href="' . $proto . htmlspecialchars((string) $res['url'])
            . '/" target="_blank" rel="noreferrer">' . htmlspecialchars((string) $res['url'])
            . '</a></td><td>' . $typeHtml . '</td><td>' . htmlspecialchars((string) $res['username'])
            . '</td><td>******</td><td>' . htmlspecialchars((string) $res['remark'])
            . '</td><td><a href="./shequlist.php?my=edit&id=' . $res['id']
            . '" class="btn btn-info btn-xs">编辑</a>&nbsp;<a href="./shequlist.php?my=delete&id=' . $res['id']
            . '" class="btn btn-xs btn-danger" onclick="return confirmdel(' . $isClone . ')">删除</a></td></tr>';
    }
    echo '          </tbody>
        </table>
      </div>
    </div>
  </div>
' . $shequJs;
}
