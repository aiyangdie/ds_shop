<?php
/**
 * 网站图标设置：Logo / Favicon / 背景 / 登录小图标
 * 明文页，避免依赖混淆的 set.php?mod=upimg
 */
include '../includes/common.php';
if ($islogin != 1) {
    exit("<script>window.location.href='./login.php';</script>");
}
adminpermission('set', 1);

$targets = array(
    'logo' => array(
        'label' => '网站 Logo',
        'hint' => '首页、导航栏主 Logo，建议透明底 PNG，宽 200～400px',
        'path' => ROOT . 'assets/img/logo.png',
        'url' => '../assets/img/logo.png',
        'accept' => 'image/png,image/jpeg,image/gif,image/webp',
        'preview_class' => '',
    ),
    'favicon' => array(
        'label' => '浏览器图标',
        'hint' => '浏览器标签页小图标，建议正方形 PNG/ICO，64×64 或 128×128',
        'path' => ROOT . 'assets/img/favicon.png',
        'url' => '../assets/img/favicon.png',
        'accept' => 'image/png,image/x-icon,image/vnd.microsoft.icon,image/jpeg,image/webp,.ico',
        'preview_class' => 'is-favicon',
    ),
    'bj' => array(
        'label' => '页面背景',
        'hint' => '部分模板全站背景图（bj.png），建议横向大图或可平铺纹理',
        'path' => ROOT . 'assets/img/bj.png',
        'url' => '../assets/img/bj.png',
        'accept' => 'image/png,image/jpeg,image/gif,image/webp',
        'preview_class' => 'is-bg',
    ),
    'logo2' => array(
        'label' => '登录小图标',
        'hint' => '部分模板登录页 QQ 等按钮背景（logo2.png）',
        'path' => ROOT . 'assets/img/logo2.png',
        'url' => '../assets/img/logo2.png',
        'accept' => 'image/png,image/jpeg,image/gif,image/webp',
        'preview_class' => '',
    ),
);

$message = '';
$messageType = 'success';

if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $message = rawurldecode(strval($_GET['msg']));
    $messageType = (isset($_GET['t']) && $_GET['t'] === 'd') ? 'danger' : ((isset($_GET['t']) && $_GET['t'] === 'w') ? 'warning' : 'success');
}

function icon_set_flash_redirect($msg, $type = 'success')
{
    $t = ($type === 'danger') ? 'd' : (($type === 'warning') ? 'w' : 's');
    header('Location: ./icon_set.php?t=' . $t . '&msg=' . rawurlencode($msg));
    exit;
}

function icon_set_clear_cache()
{
    global $CACHE;
    if (isset($CACHE) && is_object($CACHE) && method_exists($CACHE, 'clear')) {
        @$CACHE->clear();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkRefererHost()) {
        exit('请求来源校验失败');
    }
    $action = isset($_POST['action']) ? strval($_POST['action']) : 'upload';
    $key = isset($_POST['key']) ? strval($_POST['key']) : '';

    if (!isset($targets[$key])) {
        icon_set_flash_redirect('未知图标类型', 'danger');
    }

    if ($action === 'reset') {
        if ($key === 'favicon') {
            @unlink(ROOT . 'assets/img/favicon.png');
            @unlink(ROOT . 'favicon.ico');
            if (function_exists('saveSetting')) {
                saveSetting('favicon', '');
                saveSetting('default_ico_url', 'assets/img/logo.png');
            }
            icon_set_clear_cache();
            icon_set_flash_redirect('已恢复默认浏览器图标（将使用 Logo）');
        }
        icon_set_flash_redirect('该图标不支持清空，请直接上传覆盖', 'warning');
    }

    if (empty($_FILES['image']) || !isset($_FILES['image']['error']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        icon_set_flash_redirect('请先选择图片文件', 'danger');
    }

    $file = $_FILES['image'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allow = array('png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'ico');
    if (!in_array($ext, $allow, true)) {
        icon_set_flash_redirect('仅支持 png/jpg/gif/webp/bmp/ico', 'danger');
    }

    $dest = $targets[$key]['path'];
    $label = $targets[$key]['label'];

    if ($key === 'favicon' && $ext === 'ico') {
        if (!is_uploaded_file($file['tmp_name']) || $file['size'] <= 0 || $file['size'] > 2 * 1024 * 1024) {
            icon_set_flash_redirect('上传失败：ICO 文件无效或超过 2MB', 'danger');
        }
        $icoRoot = ROOT . 'favicon.ico';
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!@move_uploaded_file($file['tmp_name'], $icoRoot) && !@copy($file['tmp_name'], $icoRoot)) {
            icon_set_flash_redirect('上传失败：无法写入文件', 'danger');
        }
        @copy($icoRoot, $dest);
        if (function_exists('saveSetting')) {
            saveSetting('favicon', 'favicon.ico');
            saveSetting('default_ico_url', 'favicon.ico');
        }
        icon_set_clear_cache();
        icon_set_flash_redirect($label . '上传成功，请 Ctrl+F5 强刷前台');
    }

    $msg = upload_site_image($file, $dest);
    if (strpos($msg, '成功') !== 0) {
        icon_set_flash_redirect($msg, 'danger');
    }
    if ($key === 'favicon') {
        @copy($dest, ROOT . 'favicon.ico');
        if (function_exists('saveSetting')) {
            saveSetting('favicon', 'assets/img/favicon.png');
            saveSetting('default_ico_url', 'assets/img/favicon.png');
        }
        icon_set_clear_cache();
    }
    icon_set_flash_redirect($label . '：' . $msg);
}

function icon_preview_url($rel, $abs)
{
    if (!is_file($abs)) {
        return '';
    }
    return $rel . '?v=' . filemtime($abs);
}

$title = '图标设置';
include './head.php';
?>
<style>
.icon-set-grid{display:flex;flex-wrap:wrap;gap:16px}
.icon-card{
  flex:1 1 260px;max-width:340px;background:#fff;border:1px solid #e8ecf1;border-radius:12px;
  padding:16px;box-shadow:0 6px 18px rgba(31,45,61,.06);
}
.icon-card h4{margin:0 0 6px;font-size:15px;font-weight:600}
.icon-card .hint{font-size:12px;color:#64748b;line-height:1.5;margin-bottom:12px;min-height:40px}
.icon-preview{
  width:100%;height:140px;border-radius:10px;border:1px dashed #d0d7de;background:#f8fafc;
  display:flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:12px;position:relative;
}
.icon-preview img{max-width:90%;max-height:120px;object-fit:contain}
.icon-preview.is-bg img{max-width:100%;max-height:100%;width:100%;height:100%;object-fit:cover}
.icon-preview.is-favicon img{width:64px;height:64px;object-fit:contain;image-rendering:auto}
.icon-preview.empty{color:#94a3b8;font-size:13px}
.icon-preview .live-tag{
  position:absolute;top:8px;right:8px;background:rgba(37,99,235,.9);color:#fff;
  font-size:11px;padding:2px 6px;border-radius:4px;display:none;
}
.icon-preview.has-live .live-tag{display:inline-block}
.icon-card .form-control{margin-bottom:8px}
.icon-card .btn + .btn{margin-top:6px}
@media (max-width:767px){
  .icon-card{max-width:100%}
  .icon-set-grid{gap:12px}
}
</style>
<div class="col-xs-12 col-sm-11 col-lg-10 center-block" style="float:none;">
  <div class="block">
    <div class="block-title clearfix">
      <h3><i class="fa fa-picture-o"></i>&nbsp;图标设置</h3>
      <div class="block-options pull-right">
        <a href="./set.php?mod=upimg" class="btn btn-sm btn-default">旧版 Logo/背景</a>
        <a href="./set.php?mod=site" class="btn btn-sm btn-default">网站信息</a>
      </div>
    </div>
    <?php if ($message !== '') { ?>
      <div class="alert alert-<?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>
    <p class="text-muted" style="margin-bottom:16px">统一管理站点 Logo、Favicon、背景图。选图后可本地预览，上传成功后请用 <b>Ctrl+F5</b> 强刷前台。</p>
    <div class="icon-set-grid">
      <?php foreach ($targets as $key => $item) {
          $preview = icon_preview_url($item['url'], $item['path']);
          if ($key === 'favicon' && $preview === '' && is_file(ROOT . 'assets/img/logo.png')) {
              $preview = '../assets/img/logo.png?v=' . filemtime(ROOT . 'assets/img/logo.png');
          }
          $pclass = trim('icon-preview ' . (isset($item['preview_class']) ? $item['preview_class'] : '') . ($preview === '' ? ' empty' : ''));
          ?>
        <div class="icon-card" data-key="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
          <h4><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></h4>
          <div class="hint"><?php echo htmlspecialchars($item['hint'], ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="<?php echo htmlspecialchars($pclass, ENT_QUOTES, 'UTF-8'); ?>" id="preview-<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="live-tag">本地预览</span>
            <?php if ($preview !== '') { ?>
              <img src="<?php echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php } else { ?>
              <span class="empty-text">暂无图片</span>
            <?php } ?>
          </div>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="key" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="file" name="image" class="form-control js-icon-file" accept="<?php echo htmlspecialchars($item['accept'], ENT_QUOTES, 'UTF-8'); ?>" data-preview="preview-<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" name="action" value="upload" class="btn btn-primary btn-block"><i class="fa fa-upload"></i> 上传替换</button>
            <?php if ($key === 'favicon') { ?>
              <button type="submit" name="action" value="reset" class="btn btn-default btn-block" formnovalidate onclick="return confirm('确定清除自定义 Favicon？');">恢复默认</button>
            <?php } ?>
          </form>
        </div>
      <?php } ?>
    </div>
    <div class="alert alert-info" style="margin-top:18px;margin-bottom:0">
      <i class="fa fa-info-circle"></i>
      路径：Logo <code>assets/img/logo.png</code> · Favicon <code>assets/img/favicon.png</code>（同步 <code>favicon.ico</code>）· 背景 <code>assets/img/bj.png</code>。
      分站可在用户中心单独上传本站 Logo。
    </div>
  </div>
</div>
<script>
(function(){
  function bindPreview(input){
    input.addEventListener('change', function(){
      var box = document.getElementById(input.getAttribute('data-preview'));
      if(!box || !input.files || !input.files[0]) return;
      var file = input.files[0];
      if(!/^image\//.test(file.type) && !/\.ico$/i.test(file.name)) return;
      var url = URL.createObjectURL(file);
      var img = box.querySelector('img');
      if(!img){
        var empty = box.querySelector('.empty-text');
        if(empty) empty.remove();
        img = document.createElement('img');
        box.appendChild(img);
      }
      img.onload = function(){ try{ URL.revokeObjectURL(url); }catch(e){} };
      img.src = url;
      box.classList.remove('empty');
      box.classList.add('has-live');
    });
  }
  var inputs = document.querySelectorAll('.js-icon-file');
  for(var i=0;i<inputs.length;i++) bindPreview(inputs[i]);
})();
</script>
</body>
</html>
