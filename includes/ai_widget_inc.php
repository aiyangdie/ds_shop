<?php
/**
 * 前台 AI 客服浮窗挂载（模板脚部 include）
 */
if (!defined('IN_CRONLITE')) return;
if (empty($conf['ai_enabled']) || intval($conf['ai_enabled']) !== 1) return;
if (isset($conf['ai_shop_enabled']) && intval($conf['ai_shop_enabled']) !== 1) return;
$cdn = isset($cdnserver) ? $cdnserver : './';
$ver = @filemtime(ROOT . 'assets/css/ai-widget.css');
if (!$ver) $ver = defined('VERSION') ? VERSION : time();
$jsVer = @filemtime(ROOT . 'assets/js/ai-widget.js');
if (!$jsVer) $jsVer = $ver;
?>
<script>
window.AI_WIDGET = {
  endpoint: 'ajax_ai.php',
  css: <?php echo json_encode($cdn . 'assets/css/ai-widget.css?ver=' . $ver); ?>
};
</script>
<script src="<?php echo $cdn; ?>assets/js/ai-widget.js?ver=<?php echo $jsVer; ?>"></script>
<?php
