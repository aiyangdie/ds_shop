<?php
/**
 * Recovered admin/clone.php (goto-flattened original).
 * Incremental clone shows a picker then inserts missing class/shequ/price/tools.
 * Full clone truncates local catalog tables and copies IDs from the source.
 * Not wired into the live admin path until a side-by-side check passes.
 */
include '../includes/common.php';
$title = '克隆站点';
include './head.php';
if ($islogin == 1) {
} else {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

adminpermission('super', 1);

echo '    <div class="col-sm-12 col-md-10 col-lg-8 center-block" style="float: none;">' . "\r\n";

$esc = static function ($value) {
    return addslashes((string) $value);
};

$cloneJs = <<<'JS'
<script src="//cdnjs.kinqin.com/layer/3.0.1/layer.js"></script>
<script>
function SelectAll(cid,chkAll) {
	var items = $('.class'+cid);
	for (i = 0; i < items.length; i++) {
		if (items[i].id.indexOf("tid") != -1 && items[i].type == "checkbox") {
			items[i].checked = chkAll.checked;
		}
	}
}
function checkurl(){
	var url = $("input[name='url']").val();
	if(url.indexOf('http')>=0 && url.substr(-1) == '/'){
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : "POST",
			url : "ajax.php?act=checkclone",
			data : {url:url},
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 1){
					layer.msg('连通性良好，可以克隆');
				}else if(data.code == 2){
					layer.alert('无法自己克隆自己');
				}else if(data.code == 3){
					layer.alert('对方网站的源码被用记事本改过，请先在对方网站清理BOM头部');
				}else{
					layer.alert('对方网站无法连接或存在金盾或云锁等防火墙');
				}
			} ,
			error:function(data){
				layer.msg('目标URL连接超时');
				return false;
			}
		});
	}else{
		layer.alert('URL必须以 http:// 开头，以 / 结尾');
	}
}
</script>
JS;

if (isset($_POST['submit'])) {
    $url = trim($_POST['url']);
    $key = trim($_POST['key']);
    $type = isset($_POST['type']) ? intval($_POST['type']) : 1;
    $host = parse_url($url, PHP_URL_HOST);
    if ($host === $_SERVER['HTTP_HOST']) {
        showmsg('无法自己克隆自己', 3);
    }
    @ini_set('memory_limit', '512M');
    $raw = get_curl($url . 'api.php?act=clone&key=' . urlencode($key));
    $arr = json_decode($raw, true);
    if (!is_array($arr) || !isset($arr['code'])) {
        showmsg('克隆失败，返回数据解析错误。', 4);
    }
    if ((int) $arr['code'] !== 1) {
        showmsg('克隆失败，原因：' . (isset($arr['msg']) ? $arr['msg'] : ''), 4);
    }
    if (empty($arr['class']) || empty($arr['tools']) || count($arr['tools']) < 1) {
        showmsg('对方网站数据量过少。', 3);
    }
    if (!isset($arr['price'])) {
        showmsg('对方网站程序版本较低', 3);
    }

    $classList = $arr['class'];
    $toolsList = $arr['tools'];
    $shequList = isset($arr['shequ']) && is_array($arr['shequ']) ? $arr['shequ'] : [];
    $priceList = is_array($arr['price']) ? $arr['price'] : [];

    if ($type === 1 && empty($_POST['tid'])) {
        $className = [];
        foreach ($classList as $class) {
            $className[$class['cid']] = $class['name'];
        }
        $byClass = [];
        foreach ($toolsList as $tool) {
            $byClass[$tool['cid']][] = $tool;
        }
        echo '<div class="block">
        <div class="block-title"><h3 class="panel-title">请选择要克隆的商品</h3></div>
        <div class="">
          <form action="?" method="POST" role="form">
		  <input type="hidden" name="url" value="' . htmlspecialchars($url) . '">
		  <input type="hidden" name="key" value="' . htmlspecialchars($key) . '">
		  <input type="hidden" name="type" value="1">
		  <div id="class">
';
        foreach ($byClass as $cid => $tools) {
            $cname = isset($className[$cid]) ? $className[$cid] : ('分类' . $cid);
            echo '		  <a class="panel-title" data-toggle="collapse" data-parent="#class" href="#class' . $cid . '"><div class="list-group-item list-group-item-success">
		  <span class="pull-right"><i class="fa fa-chevron-down"></i></span>' . htmlspecialchars((string) $cname) . '</div></a>
		  <div id="class' . $cid . '" class="panel-collapse collapse">
			<table class="table table-bordered" style="table-layout: fixed;">
			<tbody>
			<tr><td><label class="csscheckbox csscheckbox-primary">全选<input type="checkbox" onclick="SelectAll(' . $cid . ',this)"><span></span></label>&nbsp;ID</td><td>商品名称</td><td>状态</td></tr>
';
            foreach ($tools as $tool) {
                $st = ((int) $tool['active'] === 1)
                    ? '<span class="btn btn-xs btn-success">上架中</span>'
                    : '<span class="btn btn-xs btn-warning">已下架</span>';
                $vis = empty($tool['close'])
                    ? '<span class="btn btn-xs btn-success">显示</span>'
                    : '<span class="btn btn-xs btn-warning">隐藏</span>';
                echo '<tr><td><label class="csscheckbox csscheckbox-primary"><input name="tid[]" type="checkbox" class="class' . $cid . '" id="tid" value="' . intval($tool['tid']) . '"><span></span>&nbsp;' . intval($tool['tid']) . '<label></label></label></td><td>' . htmlspecialchars((string) $tool['name']) . '</td><td>' . $st . '&nbsp;' . $vis . '</td></tr>';
            }
            echo '			</tbody>
			</table>
		</div>
';
        }
        echo '              <p><input type="submit" name="submit" value="确定克隆" class="btn btn-primary btn-block"/></p>
          </form>
        </div>
      </div>
' . $cloneJs;
        exit;
    }

    $success = 0;
    if ($type === 0) {
        $DB->exec('TRUNCATE TABLE `pre_class`');
        foreach ($classList as $class) {
            $ok = $DB->exec("INSERT INTO `pre_class` (`cid`,`name`,`sort`,`active`,`shopimg`) VALUES ('"
                . $esc($class['cid']) . "','" . $esc($class['name']) . "','" . $esc($class['sort']) . "','"
                . $esc($class['active']) . "','" . $esc($class['shopimg']) . "')");
            if ($ok) {
                $success++;
            }
        }
        $DB->exec('TRUNCATE TABLE `pre_shequ`');
        foreach ($shequList as $shequ) {
            $ok = $DB->exec("INSERT INTO `pre_shequ` (`id`,`url`,`username`,`password`,`type`) VALUES ('"
                . $esc($shequ['id']) . "','" . $esc($shequ['url']) . "',NULL,NULL,'" . $esc($shequ['type']) . "')");
            if ($ok) {
                $success++;
            }
        }
        $DB->exec('TRUNCATE TABLE `pre_price`');
        foreach ($priceList as $price) {
            $ok = $DB->exec("INSERT INTO `pre_price` (`id`,`kind`,`name`,`p_0`,`p_1`,`p_2`) VALUES ('"
                . $esc($price['id']) . "','" . $esc($price['kind']) . "','" . $esc($price['name']) . "','"
                . $esc($price['p_0']) . "','" . $esc($price['p_1']) . "','" . $esc($price['p_2']) . "')");
            if ($ok) {
                $success++;
            }
        }
        $DB->exec('TRUNCATE TABLE `pre_tools`');
        foreach ($toolsList as $tool) {
            $ok = $DB->exec("INSERT INTO `pre_tools` (`tid`,`cid`,`name`,`price`,`cost`,`cost2`,`prid`,`prices`,`input`,`inputs`,`desc`,`alert`,`shopimg`,`value`,`is_curl`,`curl`,`shequ`,`goods_id`,`goods_type`,`goods_param`,`repeat`,`multi`,`min`,`max`,`validate`,`sort`,`close`,`active`) VALUES ('"
                . $esc($tool['tid']) . "','" . $esc($tool['cid']) . "','" . $esc($tool['name']) . "','"
                . $esc($tool['price']) . "','" . $esc($tool['cost']) . "','" . $esc($tool['cost2']) . "','"
                . $esc($tool['prid']) . "','" . $esc($tool['prices']) . "','" . $esc($tool['input']) . "','"
                . $esc($tool['inputs']) . "','" . $esc($tool['desc']) . "','" . $esc($tool['alert']) . "','"
                . $esc($tool['shopimg']) . "','" . $esc($tool['value']) . "','" . $esc($tool['is_curl']) . "','"
                . $esc($tool['curl']) . "','" . $esc($tool['shequ']) . "','" . $esc($tool['goods_id']) . "','"
                . $esc($tool['goods_type']) . "','" . $esc($tool['goods_param']) . "','" . $esc($tool['repeat']) . "','"
                . $esc($tool['multi']) . "','" . $esc($tool['min']) . "','" . $esc($tool['max']) . "','"
                . $esc($tool['validate']) . "','" . $esc($tool['sort']) . "','" . $esc($tool['close']) . "','"
                . $esc($tool['active']) . "')");
            if ($ok) {
                $success++;
            }
        }
    } else {
        $selected = [];
        if (!empty($_POST['tid']) && is_array($_POST['tid'])) {
            foreach ($_POST['tid'] as $tid) {
                $selected[(int) $tid] = true;
            }
        }
        $classMap = [];
        $shequMap = [];
        $priceMap = [];
        $cloneShequ = 0;
        foreach ($toolsList as $tool) {
            if ($selected && empty($selected[(int) $tool['tid']])) {
                continue;
            }
            $srcClass = null;
            foreach ($classList as $class) {
                if ((int) $class['cid'] === (int) $tool['cid']) {
                    $srcClass = $class;
                    break;
                }
            }
            $cid = 0;
            if ($srcClass) {
                if (isset($classMap[$srcClass['cid']])) {
                    $cid = $classMap[$srcClass['cid']];
                } else {
                    $exist = $DB->getColumn("select cid from pre_class where name='" . $esc($srcClass['name']) . "' limit 1");
                    if ($exist) {
                        $cid = $exist;
                    } else {
                        $DB->exec("INSERT INTO `pre_class` (`name`,`sort`,`active`,`shopimg`) VALUES ('"
                            . $esc($srcClass['name']) . "','" . $esc($srcClass['sort']) . "','"
                            . $esc($srcClass['active']) . "','" . $esc($srcClass['shopimg']) . "')");
                        $cid = $DB->lastInsertId();
                        $success++;
                    }
                    $classMap[$srcClass['cid']] = $cid;
                }
            }
            $shequId = 0;
            if (!empty($tool['shequ'])) {
                $srcShequ = null;
                foreach ($shequList as $shequ) {
                    if ((int) $shequ['id'] === (int) $tool['shequ']) {
                        $srcShequ = $shequ;
                        break;
                    }
                }
                if ($srcShequ) {
                    $shequUrl = $srcShequ['url'];
                    if (strpos($shequUrl, 'http') === 0) {
                        $shequUrl = preg_replace('#^https?://#', '', $shequUrl);
                    }
                    if (isset($shequMap[$srcShequ['id']])) {
                        $shequId = $shequMap[$srcShequ['id']];
                    } else {
                        $exist = $DB->getColumn("select id from pre_shequ where url='" . $esc($shequUrl) . "' limit 1");
                        if ($exist) {
                            $shequId = $exist;
                        } else {
                            $DB->exec("INSERT INTO `pre_shequ` (`url`,`username`,`password`,`type`) VALUES ('"
                                . $esc($shequUrl) . "',NULL,NULL,'" . $esc($srcShequ['type']) . "')");
                            $shequId = $DB->lastInsertId();
                            $success++;
                        }
                        $shequMap[$srcShequ['id']] = $shequId;
                        $cloneShequ = $shequId;
                    }
                }
            }
            $prid = isset($tool['prid']) ? $tool['prid'] : 0;
            if (!empty($tool['prid'])) {
                $srcPrice = null;
                foreach ($priceList as $price) {
                    if ((int) $price['id'] === (int) $tool['prid']) {
                        $srcPrice = $price;
                        break;
                    }
                }
                if ($srcPrice) {
                    if (isset($priceMap[$srcPrice['id']])) {
                        $prid = $priceMap[$srcPrice['id']];
                    } else {
                        $exist = $DB->getColumn("select id from pre_price where kind='" . $esc($srcPrice['kind'])
                            . "' and name='" . $esc($srcPrice['name']) . "' limit 1");
                        if ($exist) {
                            $prid = $exist;
                        } else {
                            $DB->exec("INSERT INTO `pre_price` (`kind`,`name`,`p_0`,`p_1`,`p_2`) VALUES ('"
                                . $esc($srcPrice['kind']) . "','" . $esc($srcPrice['name']) . "','"
                                . $esc($srcPrice['p_0']) . "','" . $esc($srcPrice['p_1']) . "','"
                                . $esc($srcPrice['p_2']) . "')");
                            $prid = $DB->lastInsertId();
                            $success++;
                        }
                        $priceMap[$srcPrice['id']] = $prid;
                    }
                }
            }
            $exists = $DB->getColumn("select tid from pre_tools where name='" . $esc($tool['name'])
                . "' and is_curl='" . $esc($tool['is_curl']) . "' limit 1");
            if ($exists) {
                continue;
            }
            $ok = $DB->exec("INSERT INTO `pre_tools` (`cid`,`name`,`price`,`cost`,`cost2`,`prid`,`prices`,`input`,`inputs`,`desc`,`alert`,`shopimg`,`value`,`is_curl`,`curl`,`shequ`,`goods_id`,`goods_type`,`goods_param`,`repeat`,`multi`,`min`,`max`,`validate`,`sort`,`close`,`active`) VALUES ('"
                . $esc($cid) . "','" . $esc($tool['name']) . "','" . $esc($tool['price']) . "','"
                . $esc($tool['cost']) . "','" . $esc($tool['cost2']) . "','" . $esc($prid) . "','"
                . $esc($tool['prices']) . "','" . $esc($tool['input']) . "','" . $esc($tool['inputs']) . "','"
                . $esc($tool['desc']) . "','" . $esc($tool['alert']) . "','" . $esc($tool['shopimg']) . "','"
                . $esc($tool['value']) . "','" . $esc($tool['is_curl']) . "','" . $esc($tool['curl']) . "','"
                . $esc($shequId) . "','" . $esc($tool['goods_id']) . "','" . $esc($tool['goods_type']) . "','"
                . $esc($tool['goods_param']) . "','" . $esc($tool['repeat']) . "','" . $esc($tool['multi']) . "','"
                . $esc($tool['min']) . "','" . $esc($tool['max']) . "','" . $esc($tool['validate']) . "','"
                . $esc($tool['sort']) . "','" . $esc($tool['close']) . "','" . $esc($tool['active']) . "')");
            if ($ok) {
                $success++;
            }
        }
        if ($cloneShequ) {
            saveSetting('clone_shequ', $cloneShequ);
            $CACHE->update();
        }
    }
    showmsg('克隆完成，SQL成功执行' . $success . '句。<br/><br/><a href="./clone.php">>>返回</a>', 1);
}

echo '<div class="block">
        <div class="block-title"><h3 class="panel-title">克隆站点</h3></div>
		<div class="alert alert-info">
            使用此功能可一键克隆目标站点的分类、商品及社区对接数据（除社区账号密码外），方便站长快速丰富网站内容。
		</div>
		<div class="alert alert-danger">
            使用全量克隆将会清空本站所有商品和分类数据，请谨慎操作！
		</div>
		<form action="?" method="POST" role="form">
		    <div class="form-group">
				<div class="input-group"><div class="input-group-addon">站点URL</div>
				<input type="text" name="url" value="" class="form-control" placeholder="http://www.qq.com/" required/>
				<div class="input-group-addon" onclick="checkurl()"><small>检测连通性</small></div>
			</div></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">克隆密钥</div>
				<input type="text" name="key" value="" class="form-control" placeholder="联系目标站点取得" required/>
			</div></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">克隆方式</div>
				<select class="form-control" name="type"><option value="1">增量克隆（不会改变原有数据）</option><option value="0">全量克隆（会清空本站原有的商品数据）</option></select>
			</div></div>
            <p><input type="submit" name="submit" value="确定克隆" class="btn btn-primary btn-block"/></p>
		</form>
		<div class="panel-footer">
          <span class="glyphicon glyphicon-info-sign"></span> 本站克隆密钥<a href="./set.php?mod=cloneset">点此获取</a>
		</div>
    </div>
  </div>
' . $cloneJs;
