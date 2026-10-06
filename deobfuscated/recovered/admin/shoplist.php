<?php
/**
 * Recovered admin/shoplist.php (goto-flattened original).
 * List rows are loaded via shoplist-table.php + assets/js/shoplist.js.
 * Not wired into the live admin path until a side-by-side check passes.
 */
include '../includes/common.php';
$title = '商品管理';
include './head.php';
if ($islogin == 1) {
} else {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

echo "<style>\r
#shoplist tbody tr>td:nth-child(1){max-width:480px;}\r
#shoplist tbody tr>td:nth-child(2){min-width:140px;}\r
#shoplist tbody tr>td:nth-child(3){min-width:90px;}\r
</style>\r
    <div class=\"col-md-12 center-block\" style=\"float: none;\">\r
";

adminpermission('shop', 1);

$priceselect = '<option value="0">不使用加价模板</option>';
$price_class = [0 => '不加价'];
$pricelist = $DB->getAll('SELECT * FROM pre_price order by id asc');
foreach ($pricelist as $res) {
    $unit = (isset($res['kind']) && (int) $res['kind'] === 1) ? '倍' : '元';
    $priceselect .= '<option value="' . $res['id'] . '" kind="' . $res['kind']
        . '" p_2="' . $res['p_2'] . '" p_1="' . $res['p_1'] . '" p_0="' . $res['p_0']
        . '" >' . $res['name'] . '(' . $res['p_2'] . $unit . '|' . $res['p_1'] . $unit . '|' . $res['p_0'] . $unit . ')</option>';
    $price_class[$res['id']] = $res['name'];
}
$_SESSION['priceselect'] = $priceselect;
$_SESSION['price_class'] = $price_class;

$my = isset($_GET['my']) ? $_GET['my'] : null;
if ($my === 'qk2') {
    if ($DB->exec('TRUNCATE TABLE `pre_tools`') !== false) {
        exit("<script language='javascript'>alert('清空成功！');window.location.href='shoplist.php';</script>");
    }
    exit("<script language='javascript'>alert('清空失败！" . $DB->error() . "');history.go(-1);</script>");
}

$cid = isset($_GET['cid']) ? intval($_GET['cid']) : 0;
$resetSort = $cid ? '<li><a href="javascript:reset_sort(' . $cid . ')">重置此分类商品排序</a></li>' : '';
?>
<div class="block">
<div class="block-title clearfix">
<h2 id="blocktitle"></h2>
<span class="pull-right"><select id="pagesize" class="form-control" title="每页显示"><option value="30">30</option><option value="50">50</option><option value="60">60</option><option value="80">80</option><option value="100">100</option></select><span>
</span></span>
</div>
  <form onsubmit="return searchItem()" method="GET" class="form-inline">
  <a href="./shopedit.php?my=add&cid=<?php echo $cid; ?>" class="btn btn-primary"><i class="fa fa-plus"></i>&nbsp;添加商品</a>&nbsp;
  <div class="form-group">
    <input type="text" class="form-control" name="kw" placeholder="请输入商品名称">
  </div>
  <button type="submit" class="btn btn-success">搜索</button>&nbsp;<div class="btn-group">
  <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    更多 <span class="caret"></span>
  </button>
  <ul class="dropdown-menu">
    <?php echo $resetSort; ?>
	<li><a href="./shoprank.php">商品销量排行</a></li>
	<li><a href="javascript:change_shopname()">批量替换商品名称</a></li>
	<li><a href="javascript:change_inputs()">批量替换输入框标题</a></li>
  </ul>
</div>&nbsp;
  <a href="javascript:listTable('start')" class="btn btn-default" title="刷新商品列表"><i class="fa fa-refresh"></i></a>
</form>

<div id="listTable"></div>
    </div>
<font color="grey">提示：查看单个分类的商品列表可进行商品排序操作
  </div>
      
<script src="<?php echo $cdnpublic; ?>layer/3.1.1/layer.js"></script>
<script src="assets/js/shoplist.js?ver=<?php echo VERSION; ?>"></script>
</body>
</html>
