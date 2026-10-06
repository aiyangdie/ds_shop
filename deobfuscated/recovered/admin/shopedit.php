<?php
/**
 * Recovered admin/shopedit.php (goto-flattened original).
 * Form UI + add/edit/delete for pre_tools. JS lives in assets/js/shopedit.js.
 * Not wired into the live admin path until a side-by-side check passes.
 */
include '../includes/common.php';
$title = '商品管理';
include './head.php';
if ($islogin == 1) {
} else {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

adminpermission('shop', 1);

echo '<link rel="stylesheet" href="' . $cdnpublic . 'select2/4.0.10/css/select2.min.css">
<script src="' . $cdnpublic . 'select2/4.0.10/js/select2.min.js"></script>
<style>
	.select2-selection.select2-selection--single {
		height: 32px;
	}
	.select2-container--default.select2-selection--single {
		padding: 5px;
	}
#GoodsInfo img{max-width:100%}
</style>
';

echo '<div class="modal" align="left" id="inputabout" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
        <h4 class="modal-title" id="myModalLabel">输入框标题说明</h4>
      </div>
      <div class="modal-body">
	  使用以下输入框标题可实现特殊的转换功能<br/>
	  自动从链接和文字取出链接：<a href="javascript:changeinput(\'作品链接\')">作品链接</a>、<a href="javascript:changeinput(\'视频链接\')">视频链接</a>、<a href="javascript:changeinput(\'分享链接\')">分享链接</a><br/>
	  自动获取音乐/视频ID：<a href="javascript:changeinput(\'作品ID\')">作品ID</a>、<a href="javascript:changeinput(\'视频ID\')">视频ID</a><br/>
	  注：在输入框名称后面加[shareid]、[shareurl]可以分别有获取ID、获取URL功能
	  </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
    </div>
  </div>
</div>
<div class="modal" align="left" id="inputsabout" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
        <h4 class="modal-title" id="myModalLabel">更多输入框标题说明</h4>
      </div>
      <div class="modal-body">
	  获取空间说说列表：<a href="javascript:changeinputs(\'说说ID\')">说说ID</a>、<a href="javascript:changeinputs(\'说说链接\')">说说链接</a><br/>
	  获取空间日志列表：<a href="javascript:changeinputs(\'日志ID\')">日志ID</a>、<a href="javascript:changeinputs(\'日志链接\')">日志链接</a><br/>
	  作品地址获取：<a href="javascript:changeinputs(\'自定义[zpid]\')">自定义[zpid]</a><br/>
	  收货地址获取：<a href="javascript:changeinputs(\'收货地址\')">收货地址</a>、<a href="javascript:changeinputs(\'收件人姓名\')">收件人姓名</a>、<a href="javascript:changeinputs(\'联系电话\')">联系电话</a><br/>
	  显示倍数输入框：<a href="javascript:changeinputs(\'自定义[multi]\')">自定义[multi]</a><br/>
	  域名填写校验：<a href="javascript:changeinputs(\'域名[domain]\')">域名[domain]</a><br/><hr/>
	  显示选择框，在名称后面加{选择1,选择2}，例如：<a href="javascript:changeinputs(\'分类名{普及版,专业版}\')">分类名{普及版,专业版}</a>
	  </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
    </div>
  </div>
</div>
';

$classselect = '<option value="0">未分类</option>';
$rs = $DB->query('SELECT * FROM pre_class WHERE active=1 order by sort asc');
while ($res = $rs->fetch()) {
    $classselect .= '<option value="' . $res['cid'] . '">' . htmlspecialchars((string) $res['name']) . '</option>';
}

$shequselect = '';
$rs = $DB->query('SELECT * FROM pre_shequ order by id asc');
while ($res = $rs->fetch()) {
    $remark = $res['remark'] ? ' (' . htmlspecialchars((string) $res['remark']) . ')' : '';
    $shequselect .= '<option value="' . $res['id'] . '" type="' . htmlspecialchars((string) $res['type'])
        . '" domain="' . htmlspecialchars((string) $res['url']) . '">[' . $res['id'] . '] '
        . htmlspecialchars((string) $res['url']) . $remark . '</option>';
}

$priceselect = $_SESSION['priceselect'] ?? null;
if (!$priceselect) {
    $priceselect = '<option value="0">不使用加价模板</option>';
    $pricelist = $DB->getAll('SELECT * FROM pre_price ORDER BY id ASC');
    foreach ($pricelist as $res) {
        $unit = (isset($res['kind']) && (int) $res['kind'] === 1) ? '倍' : '元';
        $priceselect .= '<option value="' . $res['id'] . '" kind="' . $res['kind']
            . '" p_2="' . $res['p_2'] . '" p_1="' . $res['p_1'] . '" p_0="' . $res['p_0']
            . '" >' . htmlspecialchars((string) $res['name']) . '(' . $res['p_2'] . $unit . '|'
            . $res['p_1'] . $unit . '|' . $res['p_0'] . $unit . ')</option>';
    }
}

$backurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'shoplist.php';
$my = isset($_GET['my']) ? $_GET['my'] : null;

$collectPost = static function () {
    $is_curl = isset($_POST['is_curl']) ? intval($_POST['is_curl']) : 0;
    $prid = isset($_POST['prid']) ? intval($_POST['prid']) : 0;
    if ($prid > 0) {
        $price = isset($_POST['price1']) ? trim($_POST['price1']) : '';
        $cost = 0;
        $cost2 = 0;
    } else {
        $price = isset($_POST['price']) ? trim($_POST['price']) : '';
        $cost = isset($_POST['cost']) ? trim($_POST['cost']) : '';
        $cost2 = isset($_POST['cost2']) ? trim($_POST['cost2']) : '';
        if ($cost === '') {
            $cost = $price;
        }
        if ($cost2 === '') {
            $cost2 = $cost;
        }
    }
    $curl = isset($_POST['curl']) ? trim($_POST['curl']) : '';
    $goods_param = isset($_POST['goods_param']) ? trim($_POST['goods_param']) : '';
    if ($is_curl === 1) {
        $goods_param = isset($_POST['curl_post']) ? trim($_POST['curl_post']) : '';
    } elseif ($is_curl === 5) {
        $goods_param = isset($_POST['showcontent']) ? $_POST['showcontent'] : '';
        $curl = '';
    }
    return [
        'cid' => isset($_POST['cid']) ? intval($_POST['cid']) : 0,
        'name' => isset($_POST['name']) ? trim($_POST['name']) : '',
        'price' => $price,
        'cost' => $cost,
        'cost2' => $cost2,
        'prid' => $prid,
        'prices' => isset($_POST['prices']) ? trim($_POST['prices']) : '',
        'input' => isset($_POST['input']) ? trim($_POST['input']) : '',
        'inputs' => isset($_POST['inputs']) ? trim($_POST['inputs']) : '',
        'desc' => isset($_POST['desc']) ? $_POST['desc'] : '',
        'alert' => isset($_POST['alert']) ? trim($_POST['alert']) : '',
        'shopimg' => isset($_POST['shopimg']) ? trim($_POST['shopimg']) : '',
        'value' => isset($_POST['value']) ? trim($_POST['value']) : '1',
        'is_curl' => $is_curl,
        'curl' => $curl,
        'shequ' => isset($_POST['shequ']) ? intval($_POST['shequ']) : 0,
        'goods_id' => isset($_POST['goods_id']) ? trim($_POST['goods_id']) : '',
        'goods_type' => isset($_POST['goods_type']) ? trim($_POST['goods_type']) : '',
        'goods_param' => $goods_param,
        'repeat' => isset($_POST['repeat']) ? intval($_POST['repeat']) : 0,
        'multi' => isset($_POST['multi']) ? intval($_POST['multi']) : 1,
        'min' => isset($_POST['min']) ? trim($_POST['min']) : '',
        'max' => isset($_POST['max']) ? trim($_POST['max']) : '',
        'validate' => isset($_POST['validate']) ? intval($_POST['validate']) : 0,
        'valiserv' => isset($_POST['valiserv']) ? trim($_POST['valiserv']) : '',
        'backurl' => isset($_POST['backurl']) && $_POST['backurl'] !== '' ? $_POST['backurl'] : 'shoplist.php',
    ];
};

$validateTool = static function (array $data) {
    if ($data['name'] === '' || $data['price'] === '') {
        showmsg('保存错误，商品名称和价格不能为空！', 3);
    }
    if ($data['prid'] == 0) {
        if ($data['cost'] !== '' && (float) $data['price'] < (float) $data['cost']) {
            showmsg('销售价格不能低于普及版价格！', 3);
        }
        if ($data['cost2'] !== '' && (float) $data['cost'] < (float) $data['cost2']) {
            showmsg('普及版价格不能低于专业版价格！', 3);
        }
    }
    if ($data['is_curl'] === 2 && $data['shequ'] < 1) {
        showmsg('请选择对接社区！', 3);
    }
    if ($data['prices'] !== '' && !preg_match('/^[0-9.|,]+$/', $data['prices'])) {
        showmsg('批发价格优惠设置格式填写不正确！', 3);
    }
};

if ($my === 'add_submit') {
    $data = $collectPost();
    $validateTool($data);
    $sql = 'INSERT INTO `pre_tools` (`cid`,`name`,`price`,`cost`,`cost2`,`prid`,`prices`,`input`,`inputs`,`desc`,`alert`,`shopimg`,`value`,`is_curl`,`curl`,`shequ`,`goods_id`,`goods_type`,`goods_param`,`repeat`,`multi`,`min`,`max`,`validate`,`valiserv`,`close`,`active`,`addtime`) VALUES (:cid,:name,:price,:cost,:cost2,:prid,:prices,:input,:inputs,:desc,:alert,:shopimg,:value,:is_curl,:curl,:shequ,:goods_id,:goods_type,:goods_param,:repeat,:multi,:min,:max,:validate,:valiserv,0,1,NOW())';
    $ok = $DB->exec($sql, [
        ':cid' => $data['cid'],
        ':name' => $data['name'],
        ':price' => $data['price'],
        ':cost' => $data['cost'],
        ':cost2' => $data['cost2'],
        ':prid' => $data['prid'],
        ':prices' => $data['prices'],
        ':input' => $data['input'],
        ':inputs' => $data['inputs'],
        ':desc' => $data['desc'],
        ':alert' => $data['alert'],
        ':shopimg' => $data['shopimg'],
        ':value' => $data['value'] === '' ? 1 : $data['value'],
        ':is_curl' => $data['is_curl'],
        ':curl' => $data['curl'],
        ':shequ' => $data['shequ'],
        ':goods_id' => $data['goods_id'],
        ':goods_type' => $data['goods_type'],
        ':goods_param' => $data['goods_param'],
        ':repeat' => $data['repeat'],
        ':multi' => $data['multi'],
        ':min' => $data['min'],
        ':max' => $data['max'],
        ':validate' => $data['validate'],
        ':valiserv' => $data['valiserv'],
    ]);
    if ($ok) {
        $tid = $DB->lastInsertId();
        $DB->exec("UPDATE `pre_tools` SET `sort`='{$tid}' WHERE `tid`='{$tid}'");
        showmsg('添加商品成功！<br/><br/><a href="shopedit.php?my=edit&tid=' . $tid
            . '">>>编辑当前商品</a><br/><a href="' . htmlspecialchars($data['backurl']) . '">>>返回商品列表</a>', 1);
    }
    showmsg('添加商品失败！' . $DB->error(), 4);
}

if ($my === 'edit_submit') {
    $tid = intval($_GET['tid']);
    if (!$DB->getRow("SELECT * FROM pre_tools WHERE tid='{$tid}' LIMIT 1")) {
        showmsg('当前记录不存在！', 3);
    }
    $data = $collectPost();
    $validateTool($data);
    $sql = 'UPDATE `pre_tools` SET `cid`=:cid,`name`=:name,`price`=:price,`cost`=:cost,`cost2`=:cost2,`prid`=:prid,`prices`=:prices,`input`=:input,`inputs`=:inputs,`desc`=:desc,`alert`=:alert,`shopimg`=:shopimg,`value`=:value,`is_curl`=:is_curl,`curl`=:curl,`shequ`=:shequ,`goods_id`=:goods_id,`goods_type`=:goods_type,`goods_param`=:goods_param,`repeat`=:repeat,`multi`=:multi,`min`=:min,`max`=:max,`validate`=:validate,`valiserv`=:valiserv WHERE `tid`=:tid';
    $ok = $DB->exec($sql, [
        ':cid' => $data['cid'],
        ':name' => $data['name'],
        ':price' => $data['price'],
        ':cost' => $data['cost'],
        ':cost2' => $data['cost2'],
        ':prid' => $data['prid'],
        ':prices' => $data['prices'],
        ':input' => $data['input'],
        ':inputs' => $data['inputs'],
        ':desc' => $data['desc'],
        ':alert' => $data['alert'],
        ':shopimg' => $data['shopimg'],
        ':value' => $data['value'] === '' ? 1 : $data['value'],
        ':is_curl' => $data['is_curl'],
        ':curl' => $data['curl'],
        ':shequ' => $data['shequ'],
        ':goods_id' => $data['goods_id'],
        ':goods_type' => $data['goods_type'],
        ':goods_param' => $data['goods_param'],
        ':repeat' => $data['repeat'],
        ':multi' => $data['multi'],
        ':min' => $data['min'],
        ':max' => $data['max'],
        ':validate' => $data['validate'],
        ':valiserv' => $data['valiserv'],
        ':tid' => $tid,
    ]);
    if ($ok !== false) {
        showmsg('修改商品成功！<br/><br/><a href="' . htmlspecialchars($data['backurl']) . '">>>返回商品列表</a>', 1);
    }
    showmsg('修改商品失败！' . $DB->error(), 4);
}

if ($my === 'delete') {
    $tid = intval($_GET['tid']);
    $back = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'shoplist.php';
    if ($DB->exec("DELETE FROM pre_tools WHERE tid='{$tid}'") !== false) {
        showmsg('删除成功！<br/><br/><a href="' . htmlspecialchars($back) . '">>>返回商品列表</a>', 1);
    }
    showmsg('删除失败！' . $DB->error(), 4);
}

$row = null;
$isEdit = ($my === 'edit');
if ($isEdit) {
    $tid = intval($_GET['tid']);
    $row = $DB->getRow('SELECT * FROM pre_tools WHERE tid=:tid LIMIT 1', [':tid' => $tid]);
    if (!$row) {
        showmsg('当前记录不存在！', 3);
    }
}

$v = static function ($key, $default = '') use ($row) {
    return $row ? (isset($row[$key]) ? $row[$key] : $default) : $default;
};

$is_curl = (int) $v('is_curl', 0);
$show1 = ($is_curl === 1) ? '' : 'display:none;';
$show2 = ($is_curl === 2) ? '' : 'display:none;';
$show3 = ($is_curl === 5) ? '' : 'display:none;';
$curlPostVal = ($is_curl === 1) ? $v('goods_param') : '';
$showcontentVal = ($is_curl === 5) ? $v('goods_param') : '';
$formAction = $isEdit
    ? './shopedit.php?my=edit_submit&tid=' . intval($v('tid'))
    : './shopedit.php?my=add_submit';
$defaultCid = $isEdit ? $v('cid') : (isset($_GET['cid']) ? intval($_GET['cid']) : 0);
?>
<form action="<?php echo $formAction; ?>" method="POST" onsubmit="return checkinput()">
<div class="col-sm-12 col-md-6">
<div class="block">
<div class="block-title"><h3 class="panel-title">商品类型与对接设置</h3></div>
<input type="hidden" name="backurl" value="<?php echo htmlspecialchars($backurl); ?>"/>
<div class="">
<div class="form-group">
<label>购买成功后的动作:</label><br>
<select class="form-control" name="is_curl"<?php echo $isEdit ? ' default="' . $is_curl . '"' : ''; ?>><option value="0">0_无</option><option value="2">自动提交到社区/卡盟</option><option value="1">自定义访问URL/POST</option><option value="4">自动发卡密</option><option value="3">自动发送提醒邮件/微信</option><option value="5">直接显示指定内容</option></select>
</div>
<div id="curl_display1" style="<?php echo $show1; ?>">
<label>URL:</label><br>
<input type="text" class="form-control" name="curl" id="curl" value="<?php echo htmlspecialchars((string) $v('curl')); ?>">
<label>POST:</label><br>
<input type="text" class="form-control" name="curl_post" id="curl_post" value="<?php echo htmlspecialchars((string) $curlPostVal); ?>" placeholder="无POST内容请留空">
<font color="green">无POST内容请留空，POST格式：a=123&b=456<br/>变量代码：<br/>
<a href="#" onclick="Addstr('curl','[input]');return false">[input]</a>&nbsp;第一个输入框内容<br/>
<a href="#" onclick="Addstr('curl','[input2]');return false">[input2]</a>&nbsp;第二个输入框内容<br/>
<a href="#" onclick="Addstr('curl','[input3]');return false">[input3]</a>&nbsp;第三个输入框内容<br/>
<a href="#" onclick="Addstr('curl','[input4]');return false">[input4]</a>&nbsp;第四个输入框内容<br/>
<a href="#" onclick="Addstr('curl','[name]');return false">[name]</a>&nbsp;商品名称<br/>
<a href="#" onclick="Addstr('curl','[price]');return false">[price]</a>&nbsp;商品价格<br/>
<a href="#" onclick="Addstr('curl','[num]');return false">[num]</a>&nbsp;下单数量<br/>
<a href="#" onclick="Addstr('curl','[time]');return false">[time]</a>&nbsp;当前时间戳<br/></font>
<br/>
</div>
<div id="curl_display3" style="<?php echo $show3; ?>">
<label>购买后直接显示的内容:</label><br>
<textarea class="form-control" name="showcontent" rows="3" placeholder="用户购买后直接在订单详情显示的内容，支持HTML代码"><?php echo htmlspecialchars((string) $showcontentVal); ?></textarea>
</div>
<div id="curl_display2" style="<?php echo $show2; ?>">
<label>选择对接网站:</label>&nbsp;(<a href="shequlist.php" target="_blank">添加</a>)<br>
<select class="form-control" name="shequ"<?php echo $isEdit ? ' default="' . htmlspecialchars((string) $v('shequ')) . '"' : ''; ?>><?php echo $shequselect; ?></select>
<div class="form-group" id="show_goodsclass">
<label>选择对接分类:</label><br>
<div class="input-group">
<select class="form-control" id="goodsclass" name="goods_class"></select>
<span class="input-group-addon btn btn-success" id="getClass">获取</span>
</div></div>
<div class="form-group" id="show_goodslist">
<label>选择对接商品:</label><br>
<div class="input-group">
<select class="form-control" id="goodslist"></select>
<span class="input-group-addon btn btn-success" id="getGoods">获取</span>
</div></div>
<div class="form-group" id="goods_id">
<label>商品ID（goods_id）:</label><br>
<input type="text" class="form-control" name="goods_id" value="<?php echo htmlspecialchars((string) $v('goods_id')); ?>">
</div>
<div class="form-group" id="goods_type">
<label>类型ID（goods_type）:</label><br>
<input type="text" class="form-control" name="goods_type" value="<?php echo htmlspecialchars((string) $v('goods_type')); ?>">
</div>
<div class="form-group" id="goods_type_select_form" style="display:none;">
	<label>商品类型:</label><br>
	<select class="form-control" id="goods_type_select"<?php echo $isEdit ? ' default="' . htmlspecialchars((string) $v('goods_type')) . '"' : ''; ?>>
		<option value="0">代充</option>
		<option value="1">发卡</option>
	</select>
</div>
<div class="form-group" id="goods_param">
<label id="goods_param_name">参数名:</label><br>
<input type="text" class="form-control" name="goods_param" value="<?php echo htmlspecialchars((string) ($is_curl === 2 ? $v('goods_param', 'qq') : 'qq')); ?>">
<pre><font color="green">对应输入框标题，多个参数请用|隔开</font></pre>
</div>
<div class="form-group" id="show_value">
<label>默认数量信息:</label><br>
<input type="number" class="form-control" name="value" id="value" value="<?php echo htmlspecialchars((string) $v('value')); ?>" placeholder="用于对接社区使用或导出时显示" onkeyup="changeNum()">
<input type="hidden" id="price" value="">
<div id="GoodsInfo" class="alert alert-info" style="display:none;"></div>
</div></div>
</div>
<div class="block-title"><h3 class="panel-title">商品基本信息设置</h3></div>
<div class="form-group">
<label>*商品分类:</label><br>
<select name="cid" class="form-control" default="<?php echo intval($defaultCid); ?>"><?php echo $classselect; ?></select>
</div>
<div class="form-group">
<label>*商品名称:</label><br>
<input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars((string) $v('name')); ?>" required>
</div>
<div class="form-group">
<label>加价模板:</label>&nbsp;(<a href="./price.php" target="_blank">管理</a>)<br>
<select name="prid" class="form-control"<?php echo $isEdit ? ' default="' . htmlspecialchars((string) $v('prid')) . '"' : ''; ?>><?php echo $priceselect; ?></select>
</div>
<div class="form-group" id="prid1" style="display:none;">
<label>*成本价格:</label><br>
<input type="text" class="form-control" name="price1" value="<?php echo ((int) $v('prid') > 0) ? htmlspecialchars((string) $v('price')) : ''; ?>">
</div>
<table class="table table-striped table-bordered table-condensed" id="prid0" style="display:none;">
<tbody>
<tr align="center"><td>*销售价格</td><td>普及版价格</td><td>专业版价格</td></tr>
<tr>
<td><input type="text" name="price" value="<?php echo ((int) $v('prid') === 0) ? htmlspecialchars((string) $v('price')) : ''; ?>" class="form-control input-sm"/></td>
<td><input type="text" name="cost" value="<?php echo htmlspecialchars((string) $v('cost')); ?>" class="form-control input-sm" placeholder="不填写则同步销售价格"/></td>
<td><input type="text" name="cost2" value="<?php echo htmlspecialchars((string) $v('cost2')); ?>" class="form-control input-sm" placeholder="不填写则同步普及版价格"/></td>
</tr>
</tbody>
</table>
<div class="form-group">
<label>批发价格优惠设置:</label><br>
<input type="text" class="form-control" name="prices" value="<?php echo htmlspecialchars((string) $v('prices')); ?>" placeholder="不懂请勿填写">
<pre><font color="green">填写格式：购满x个|减少x元单价,购满x个|减少x元单价  例如10|0.1,20|0.3,30|0.5</font></pre>
</div>
<div class="form-group">
<label>第一个输入框标题:</label><br>
<div class="input-group">
<input type="text" class="form-control" name="input" value="<?php echo htmlspecialchars((string) $v('input')); ?>" placeholder="留空默认为“下单账号”"><span class="input-group-btn"><a href="#inputabout" data-toggle="modal" class="btn btn-info" title="说明"><i class="glyphicon glyphicon-exclamation-sign"></i></a></span>
</div></div>
<div class="form-group">
<label>更多输入框标题:</label><br>
<div class="input-group">
<input type="text" class="form-control" name="inputs" value="<?php echo htmlspecialchars((string) $v('inputs')); ?>" placeholder="留空则不显示更多输入框"><span class="input-group-btn"><a href="#inputsabout" data-toggle="modal" class="btn btn-info" title="说明"><i class="glyphicon glyphicon-exclamation-sign"></i></a></span>
</div>
<pre><font color="green">多个输入框请用|隔开(不能超过4个)</font></pre>
</div>
<div class="form-group">
<label>商品简介:</label>(没有请留空)<br>
<textarea class="form-control" id="editor_id" name="desc" rows="3" style="width:100%" placeholder="当选择该商品时自动显示，支持HTML代码"><?php echo htmlspecialchars((string) $v('desc')); ?></textarea>
</div>
<div class="form-group">
<label>提示内容:</label>(没有请留空)<br>
<input type="text" class="form-control" name="alert" value="<?php echo htmlspecialchars((string) $v('alert')); ?>" placeholder="当选择该商品时自动弹出提示，不支持HTML代码">
</div>
<div class="form-group">
<label>商品图片:</label><br>
<input type="file" id="file" onchange="fileUpload()" style="display:none;"/>
<div class="input-group">
<input type="text" class="form-control" id="shopimg" name="shopimg" value="<?php echo htmlspecialchars((string) $v('shopimg')); ?>" placeholder="填写图片URL，没有请留空"><span class="input-group-btn"><a href="javascript:fileSelect()" class="btn btn-success" title="上传图片"><i class="glyphicon glyphicon-upload"></i></a><a href="javascript:fileView()" class="btn btn-warning" title="查看图片"><i class="glyphicon glyphicon-picture"></i></a></span>
</div></div>
<div class="form-group">
<label>*显示数量选择框:</label><br>
<select class="form-control" name="multi"<?php echo $isEdit ? ' default="' . htmlspecialchars((string) $v('multi', '1')) . '"' : ''; ?>><option value="1">1_是</option><option value="0">0_否</option></select>
</div>
<table class="table table-striped table-bordered table-condensed" id="multi0" style="display:none;">
<tr align="center"><td>最小下单数量</td><td>最大下单数量</td></tr>
<tr>
<td><input type="text" name="min" class="form-control input-sm" value="<?php echo htmlspecialchars((string) $v('min')); ?>" placeholder="留空则默认为1"/></td>
<td><input type="text" name="max" class="form-control input-sm" value="<?php echo htmlspecialchars((string) $v('max')); ?>" placeholder="留空则不限数量"/></td>
</tr>
</table>
<div class="form-group">
<label>允许重复下单:</label><br>
<div class="input-group">
<select class="form-control" name="repeat" default="<?php echo htmlspecialchars((string) $v('repeat', '1')); ?>"><option value="0">0_否</option><option value="1">1_是</option></select>
<a tabindex="0" class="input-group-addon" role="button" data-toggle="popover" data-trigger="focus" title="" data-placement="bottom" data-content="是指相同下单输入内容（非同一用户）当天只能下单一次，或上一条订单未处理的情况下不能重复下单"><span class="glyphicon glyphicon-info-sign"></span></a>
</div></div>
<div class="form-group">
<label>验证操作:</label><br>
<select class="form-control" name="validate"<?php echo $isEdit ? ' default="' . htmlspecialchars((string) $v('validate')) . '"' : ''; ?>><option value="0">不开启验证</option><option value="1">验证QQ空间是否有访问权限</option><option value="2">验证已开通服务(符合则禁止下单)</option><option value="3">验证已开通服务(符合则不对接社区)</option></select>
</div>
<div class="form-group" id="valiserv" style="display:none;">
<label>需要验证的已开通服务:</label><br>
<select class="form-control" name="valiserv"<?php echo $isEdit ? ' default="' . htmlspecialchars((string) $v('valiserv')) . '"' : ''; ?>><option value="vip">QQ会员</option><option value="svip">超级会员</option><option value="red">红钻贵族</option><option value="green">绿钻贵族</option><option value="sgreen">绿钻豪华版</option><option value="yellow">黄钻贵族</option><option value="syellow">豪华黄钻</option><option value="hollywood">腾讯视频VIP</option><option value="qqmsey">付费音乐包</option><option value="qqmstw">豪华付费音乐包</option><option value="weiyun">微云会员</option><option value="sweiyun">微云超级会员</option></select>
</div>
<input type="submit" class="btn btn-primary btn-block" value="<?php echo $isEdit ? '确定修改' : '确定添加'; ?>">
<br/><a href="<?php echo htmlspecialchars($isEdit ? $backurl : 'shoplist.php'); ?>">>>返回商品列表</a>
</div></div></div>
</form>
<script>
var isAdd = <?php echo $isEdit ? 'false' : 'true'; ?>;
</script>
<script src="<?php echo $cdnpublic; ?>layer/3.1.1/layer.js"></script>
<script src="assets/js/shopedit.js?ver=<?php echo VERSION; ?>"></script>
<?php
if (!empty($conf['shopdesc_editor'])) {
    echo '<script charset="utf-8" src="../assets/kindeditor/kindeditor-all-min.js"></script>
<script charset="utf-8" src="../assets/kindeditor/zh-CN.js"></script>
<script>
KindEditor.ready(function(K) {
	window.editor = K.create(\'#editor_id\', {
		resizeType : 1,
		allowUpload : false,
		allowPreviewEmoticons : false,
		uploadJson : \'./ajax.php?act=article_upload\',
		items : [
			\'fontname\', \'fontsize\', \'|\', \'forecolor\', \'hilitecolor\', \'bold\', \'italic\', \'underline\',
			\'removeformat\',\'formatblock\',\'hr\', \'|\', \'justifyleft\', \'justifycenter\', \'justifyright\', \'insertorderedlist\',
			\'insertunorderedlist\', \'|\', \'image\', \'link\',\'unlink\', \'code\', \'|\',\'fullscreen\',\'source\',\'preview\']
	});
});
</script>
';
}
echo '</body>
</html>';
