<?php
/**
 * Recovered admin/invite.php (goto-flattened original).
 * List rows are loaded via invite.js. Not wired into the live admin path until a side-by-side check passes.
 */
include '../includes/common.php';
$title = '推广商品列表';
include './head.php';
if ($islogin == 1) {
} else {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

adminpermission('shop', 1);

echo '    <div class="col-md-12 center-block" style="float: none;">' . "\r\n";

$my = isset($_GET['my']) ? $_GET['my'] : null;

$classOptions = '<option value="0">请选择商品分类</option>';
$rs = $DB->query('SELECT * FROM pre_class WHERE 1 order by sort asc');
while ($res = $rs->fetch()) {
    $classOptions .= '<option value="' . $res['cid'] . '">' . $res['name'] . '</option>';
}

$helpFooter = '<div class="panel-footer">
<span class="glyphicon glyphicon-info-sign"></span>奖励条件说明：<br/>
【下单金额】被推广用户打开链接下单达到该金额值，推广用户获得该商品奖励<br/>
【累计访问】打开推广链接的IP数量达到该数值，推广用户获得该商品奖励<br/><br/>
奖励次数说明：<br/>
【一次性】一个推广链接只能发放一次奖励（适用于钻和会员等商品）<br/>
【可多次】一个推广链接可发放多次奖励，只要达到条件即可（适用于名片赞等商品）<br/>
注：奖励条件为累计访问的只能是一次性
<script src="' . $cdnpublic . 'layer/3.1.1/layer.js"></script>
<script src="assets/js/inviteedit.js?ver=' . VERSION . '"></script>';

if ($my === 'add') {
    echo '<div class="block">
<div class="block-title"><h3 class="panel-title">添加推广商品</h3></div>
<div class="">
  <form action="./invite.php?my=add_submit" method="post" class="form" role="form">
  <div class="form-group">
	<div class="input-group">
		<span class="input-group-addon">
			选择商品
		</span>
		<select id="cid" class="form-control">' . $classOptions . '</select>
		<select id="tid" name="tid" class="form-control"></select>
	</div>
  </div>
  <div class="form-group">
	<div class="input-group">
		<span class="input-group-addon">奖励条件</span>
		<select id="type" name="type" class="form-control"><option value="0">下单金额</option><option value="1">累计访问</option></select>
		<input type="text" id="value" name="value" value="" class="form-control" placeholder="输入下单金额"/>
	</div>
  </div>
  <div class="form-group">
	<div class="input-group">
		<span class="input-group-addon">奖励次数</span>
		<select id="type" name="times" class="form-control"><option value="0">一次性</option><option value="1">可多次</option></select>
	</div>
  </div>
  <div class="form-group">
	<div class="input-group">
		<span class="input-group-addon">排序数字</span>
		<input type="number" min="1" max="1000" name="sort" value="" class="form-control" placeholder="数字越小越靠前"/>
	</div>
  </div>
	<div class="form-group">
	  <input type="submit" name="submit" value="添加" class="btn btn-primary btn-block"/>
  </form>
  <br/><a href="./invite.php">>>返回商品列表</a>
</div>
' . $helpFooter;
} elseif ($my === 'edit') {
    $id = intval($_GET['id']);
    $row = $DB->getRow("select * from pre_inviteshop where id='{$id}' limit 1");
    if (!$row) {
        showmsg('当前记录不存在！', 3);
    }
    $toolName = $DB->getColumn("SELECT name FROM pre_tools WHERE tid='" . intval($row['tid']) . "' limit 1");
    echo '<div class="block">
<div class="block-title"><h3 class="panel-title">修改推广商品</h3></div>
  <form action="./invite.php?my=edit_submit&id=' . $id . '" method="post" class="form" role="form">
  <div class="form-group">
	<div class="input-group">
		<span class="input-group-addon">商品名称</span>
		<input type="text" id="tid" value="' . htmlspecialchars((string) $toolName) . '" class="form-control" disabled/>
	</div>
  </div>
  <div class="form-group">
	<div class="input-group">
		<span class="input-group-addon">奖励条件</span>
		<select id="type" name="type" class="form-control" default="' . htmlspecialchars((string) $row['type']) . '"><option value="0">下单金额</option><option value="1">累计访问</option></select>
		<input type="text" id="value" name="value" value="' . htmlspecialchars((string) $row['value']) . '" class="form-control" placeholder="输入下单金额"/>
	</div>
  </div>
  <div class="form-group">
	<div class="input-group">
		<span class="input-group-addon">奖励次数</span>
		<select id="type" name="times" class="form-control" default="' . htmlspecialchars((string) $row['times']) . '"><option value="0">一次性</option><option value="1">可多次</option></select>
	</div>
  </div>
  <div class="form-group">
	<div class="input-group">
		<span class="input-group-addon">排序数字</span>
		<input type="number" min="1" max="1000" name="sort" value="' . htmlspecialchars((string) $row['sort']) . '" class="form-control" placeholder="数字越小越靠前"/>
	</div>
  </div>
	  <input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/>
  </form>
  <br/><a href="./invite.php">>>返回商品列表</a>
</div>
' . $helpFooter;
} elseif ($my === 'add_submit') {
    $tid = intval($_POST['tid']);
    $type = intval($_POST['type']);
    $value = trim($_POST['value']);
    $times = intval($_POST['times']);
    $sort = intval($_POST['sort']);
    if ($tid < 1 || $value === '') {
        showmsg('保存错误,请确保每项都不为空!', 3);
    } elseif ($DB->getRow("select * from pre_inviteshop where tid='{$tid}' limit 1")) {
        showmsg('该商品已经添加过了！', 3);
    } else {
        $sql = 'insert into `pre_inviteshop` (`tid`,`type`,`value`,`times`,`sort`,`addtime`,`active`) values (:tid, :type, :value, :times, :sort, NOW(), \'1\')';
        $ok = $DB->exec($sql, [
            ':tid' => $tid,
            ':type' => $type,
            ':value' => $value,
            ':times' => $times,
            ':sort' => $sort,
        ]);
        if ($ok) {
            showmsg('添加推广商品成功！<br/><br/><a href="./invite.php">>>返回商品列表</a>', 1);
        } else {
            showmsg('添加推广商品失败！' . $DB->error(), 4);
        }
    }
} elseif ($my === 'edit_submit') {
    $id = intval($_GET['id']);
    if (!$DB->getRow("select * from pre_inviteshop where id='{$id}' limit 1")) {
        showmsg('当前记录不存在！', 3);
    }
    $type = intval($_POST['type']);
    $value = trim($_POST['value']);
    $times = intval($_POST['times']);
    $sort = intval($_POST['sort']);
    if ($value === '') {
        showmsg('保存错误,请确保每项都不为空!', 3);
    }
    $sql = 'update `pre_inviteshop` set `type`=:type, `value`=:value, `times`=:times, `sort`=:sort where `id`=:id';
    $ok = $DB->exec($sql, [
        ':type' => $type,
        ':value' => $value,
        ':times' => $times,
        ':sort' => $sort,
        ':id' => $id,
    ]);
    if ($ok !== false) {
        showmsg('修改推广商品成功！<br/><br/><a href="./invite.php">>>返回商品列表</a>', 1);
    } else {
        showmsg('修改推广商品失败！' . $DB->error(), 4);
    }
} else {
    echo '<div class="block">
<div class="block-title clearfix">
<h2 id="blocktitle"></h2>
<span class="pull-right"><select id="pagesize" class="form-control"><option value="30">30</option><option value="50">50</option><option value="60">60</option><option value="80">80</option><option value="100">100</option></select><span>
</span></span>
  <form onsubmit="return searchItem()" method="GET" class="form-inline">
  <a href="./invite.php?my=add" class="btn btn-primary"><i class="fa fa-plus"></i>&nbsp;添加推广商品</a>
    <input type="text" class="form-control" name="kw" placeholder="请输入商品名称">
  <button type="submit" class="btn btn-info">搜索</button>&nbsp;
  <a href="javascript:listTable(\'start\')" class="btn btn-default" title="刷新商品列表"><i class="fa fa-refresh"></i></a>
</form>

<div id="listTable"></div>
    </div>
      
<script src="assets/js/invite.js?ver=' . VERSION . '"></script>
</body>
</html>';
}
