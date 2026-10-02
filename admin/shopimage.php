<?php
/**
 * 商品图片管理。独立于混淆的商品编辑页，便于维护与排障。
 */
include '../includes/common.php';
if ($islogin != 1) exit("<script>window.location.href='./login.php';</script>");
adminpermission('shop', 2);

$tid = isset($_GET['tid']) ? intval($_GET['tid']) : 0;
$tool = $DB->getRow("SELECT tid,name,shopimg FROM pre_tools WHERE tid='$tid' LIMIT 1");
if (!$tool) exit('商品不存在');

$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkRefererHost()) exit('请求来源校验失败');
    $action = isset($_POST['action']) ? strval($_POST['action']) : 'save';
    $shopimg = isset($_POST['shopimg']) ? trim(strval($_POST['shopimg'])) : '';

    if ($action === 'clear') {
        $shopimg = '';
    } elseif (!empty($_FILES['image']) && isset($_FILES['image']['error']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'), true)) $ext = 'png';
        $relativePath = 'assets/uploads/products/product_' . $tid . '_' . date('YmdHis') . '.' . $ext;
        $uploadMessage = upload_site_image($_FILES['image'], ROOT . $relativePath);
        if (strpos($uploadMessage, '成功') !== 0) {
            $message = $uploadMessage;
            $messageType = 'danger';
        } else {
            $shopimg = $relativePath;
        }
    }

    if ($message === '') {
        if ($shopimg !== '' && !preg_match('#^(https?://|/|assets/)#i', $shopimg)) {
            $message = '图片地址仅支持 http(s)、站点绝对路径或 assets/ 下的本地路径';
            $messageType = 'danger';
        } else {
            $result = $DB->exec('UPDATE pre_tools SET shopimg=:shopimg WHERE tid=:tid', array(':shopimg' => $shopimg, ':tid' => $tid));
            if ($result !== false) {
                $tool['shopimg'] = $shopimg;
                $message = $action === 'clear' ? '商品图片已清空' : '商品图片已保存';
            } else {
                $message = '保存失败：' . $DB->error();
                $messageType = 'danger';
            }
        }
    }
}

$title = '商品图片管理';
include './head.php';
$preview = $tool['shopimg'] !== '' ? $tool['shopimg'] : '../assets/img/Product/noimg.png';
if ($tool['shopimg'] !== '' && !preg_match('#^https?://#i', $tool['shopimg']) && substr($tool['shopimg'], 0, 1) !== '/') {
    $preview = '../' . $tool['shopimg'];
}
?>
<div class="col-xs-12 col-sm-9 col-lg-7 center-block" style="float:none;">
  <div class="block">
    <div class="block-title">
      <h3><i class="fa fa-picture-o"></i>&nbsp;商品图片</h3>
      <div class="block-options pull-right"><a href="./shoplist.php" class="btn btn-sm btn-default"><i class="fa fa-arrow-left"></i> 返回商品列表</a></div>
    </div>
    <?php if ($message !== '') { ?><div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>
    <div class="alert alert-info">正在设置：#<?php echo $tid; ?> <?php echo htmlspecialchars($tool['name'], ENT_QUOTES, 'UTF-8'); ?>。可上传本地图片，也可填写 HTTPS 图片地址。</div>
    <div class="text-center" style="padding:18px 0 28px;">
      <img id="imagePreview" src="<?php echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'); ?>" alt="商品图片预览" style="width:100%;max-width:420px;height:260px;object-fit:cover;border-radius:16px;border:1px solid #e5e8ed;box-shadow:0 12px 32px rgba(31,45,61,.15);" onerror="this.onerror=null;this.src='../assets/img/Product/noimg.png';">
    </div>
    <form method="post" enctype="multipart/form-data" class="form-horizontal">
      <div class="form-group">
        <label class="col-sm-2 control-label">上传图片</label>
        <div class="col-sm-9"><input type="file" name="image" id="imageFile" class="form-control" accept="image/png,image/jpeg,image/gif,image/webp,image/bmp"><p class="help-block">支持 PNG/JPG/GIF/WebP/BMP，最大 5MB；上传文件优先于地址。</p></div>
      </div>
      <div class="form-group">
        <label class="col-sm-2 control-label">图片地址</label>
        <div class="col-sm-9"><input type="text" name="shopimg" id="shopimg" class="form-control" value="<?php echo htmlspecialchars($tool['shopimg'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://... 或 assets/uploads/..."></div>
      </div>
      <div class="form-group"><div class="col-sm-offset-2 col-sm-9">
        <button type="submit" name="action" value="save" class="btn btn-primary"><i class="fa fa-save"></i> 保存图片</button>
        <button type="submit" name="action" value="clear" class="btn btn-danger" onclick="return confirm('确定清空该商品图片吗？');"><i class="fa fa-trash"></i> 清空图片</button>
      </div></div>
    </form>
  </div>
</div>
<script>
(function(){
  var file = document.getElementById('imageFile');
  var url = document.getElementById('shopimg');
  var preview = document.getElementById('imagePreview');
  file.onchange = function(){ if(this.files && this.files[0]) preview.src = URL.createObjectURL(this.files[0]); };
  url.oninput = function(){ if(!file.files.length && this.value) preview.src = this.value.indexOf('http') === 0 || this.value.charAt(0) === '/' ? this.value : '../' + this.value; };
})();
</script>
</body>
</html>
