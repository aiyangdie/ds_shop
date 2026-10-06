<?php
/**
 * Recovered admin/classlist.php (goto-flattened original).
 * Not wired into the live admin path until a side-by-side check passes.
 */
include '../includes/common.php';
$title = '分类管理';
include './head.php';
if ($islogin == 1) {
} else {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

adminpermission('shop', 1);

echo "<style>\r\n#classlist tbody tr>td:nth-child(2){max-width:50px}\r\n</style>\r\n";
echo "<script src=\"assets/js/jquery.dragsort-0.5.2.min.js\"></script>\r\n";
echo "    <div class=\"col-sm-12 col-md-10 center-block\" style=\"float: none;\">\r\n\n";

$my = isset($_GET['my']) ? $_GET['my'] : null;

if ($my === 'qk2') {
    if ($DB->exec('TRUNCATE TABLE `pre_class`') !== false) {
        exit("<script language='javascript'>alert('清空成功！');window.location.href='classlist.php';</script>");
    }
    exit("<script language='javascript'>alert('清空失败！" . $DB->error() . "');history.go(-1);</script>");
}

if ($my === 'classimg') {
    echo "<div class=\"block\">\r
	<div class=\"block-title\">\r
		<h2>修改分类图片&nbsp;[<a href=\"./classlist.php\">返回</a>]</h2>\r
	</div>\r
      <div class=\"table-responsive\">\r
        <table class=\"table table-striped\">\r
          <thead><tr><th>分类名称</th><th style=\"min-width:220px\">图片URL</th></tr></thead>\r
          <tbody><form id=\"classlist\">\r
";
    $rs = $DB->query('SELECT * FROM pre_class WHERE 1 order by sort asc');
    while ($res = $rs->fetch()) {
        echo '<tr><td>' . $res['name']
            . '</td><td><div class="input-group"><input type="file" id="file' . $res['cid']
            . '" onchange="fileUpload(' . $res['cid']
            . ')" style="display:none;"/><input type="text" class="form-control input-sm" name="img['
            . $res['cid'] . ']" value="' . $res['shopimg']
            . '" placeholder="填写图片URL" required><span class="input-group-btn"><a href="javascript:fileSelect('
            . $res['cid'] . ')" class="btn btn-success btn-sm" title="上传图片"><i class="glyphicon glyphicon-upload"></i></a><a href="javascript:getImage('
            . $res['cid'] . ')" class="btn btn-info btn-sm" title="自动获取图片"><i class="glyphicon glyphicon-search"></i></a><a href="javascript:fileView('
            . $res['cid'] . ')" class="btn btn-warning btn-sm" title="查看图片"><i class="glyphicon glyphicon-picture"></i></a></span></div></td></tr>';
    }
    echo '</form><tr><td></td><td><span class="btn btn-primary btn-sm btn-block" onclick="saveAllImages()"><i class="fa fa-floppy-o"></i> 保存全部</span></td></tr>';
    echo "			</form>\r
          </tbody>\r
        </table>\r
      </div>\r
	  <div class=\"panel-footer\">\r
	  <span class=\"glyphicon glyphicon-info-sign\"></span>当前图片仅适用于部分首页模板\r
	  </div>\r
";
} else {
    echo "	<div class=\"block-options pull-right\"><a href=\"javascript:listTable()\" class=\"btn btn-default\" title=\"刷新分类列表\"><i class=\"fa fa-refresh\"></i></a></div>\r
		<h2>商品分类</h2>\r
      <div id=\"listTable\"></div>\r
  </div>\r
</div>\r
";
}

echo "\n<script src=\"" . $cdnpublic . "layer/3.1.1/layer.js\"></script>\r
<script src=\"assets/js/classlist.js?ver=" . VERSION . "\"></script>\r
</body>\r
</html>";
