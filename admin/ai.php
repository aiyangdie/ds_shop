<?php
/**
 * AI助手（站名/助手称呼来自配置）
 */
include("../includes/common.php");
$title = 'AI助手';
include './head.php';
if ($islogin != 1) exit("<script language='javascript'>window.location.href='./login.php';</script>");
$aiOn = !empty($conf['ai_enabled']) && intval($conf['ai_enabled']) === 1;
$model = isset($conf['ai_model']) ? htmlspecialchars($conf['ai_model']) : '未配置';
$siteName = isset($conf['sitename']) && $conf['sitename'] !== '' ? $conf['sitename'] : '本站';
$asName = !empty($conf['ai_assistant_name']) ? $conf['ai_assistant_name'] : '助手';
?>
<link rel="stylesheet" href="../assets/css/ai-chat.css?ver=<?php echo filemtime(ROOT.'assets/css/ai-chat.css'); ?>">
<div class="col-xs-12 center-block" style="float:none;">
<div class="block" style="margin-bottom:10px">
<div class="block-title" style="margin-bottom:8px">
  <h3 class="panel-title"><i class="fa fa-magic"></i>&nbsp;AI助手 <small id="aiBrandSub"><?php echo htmlspecialchars($siteName); ?> · <?php echo htmlspecialchars($asName); ?> · <?php echo $model; ?></small></h3>
  <div class="block-options pull-right">
    <a href="./ai_cs.php" class="btn btn-xs btn-default">客服中心</a>
    <a href="./ai_log.php" class="btn btn-xs btn-default">操作日志</a>
    <a href="./ai_set.php" class="btn btn-xs btn-default">模型配置</a>
  </div>
</div>
<?php if (!$aiOn) { ?>
<div class="alert alert-warning" style="margin-bottom:8px;padding:8px 12px">请先在 <a href="./ai_set.php">模型配置</a> 启用 AI 并配置 API Key。</div>
<?php } ?>
<p class="ai-hint">写操作会先确认并记日志。刷新页面会自动恢复上次对话。Enter 发送，Shift+Enter 换行。</p>
<div class="ai-wrap">
<div class="row">
  <div class="col-md-2 ai-sidebar-col">
    <div class="panel panel-default ai-sidebar-panel">
      <div class="panel-heading">对话
        <button type="button" class="btn btn-xs btn-primary pull-right" id="btnNew" <?php echo $aiOn?'':'disabled';?>>新建</button>
      </div>
      <div class="panel-body" style="padding-bottom:4px">
        <input type="search" id="aiSessionSearch" class="form-control input-sm" placeholder="搜索对话…" <?php echo $aiOn?'':'disabled';?> style="margin-bottom:8px">
        <div class="ai-side" id="sessionList"><div class="text-muted">加载中…</div></div>
      </div>
    </div>
  </div>
  <div class="col-md-8 ai-main-col">
    <div class="panel panel-default ai-chat-panel">
      <div class="panel-heading clearfix">
        <button type="button" class="btn btn-xs btn-default ai-mobile-sessions" id="btnSessions" aria-label="打开历史对话"><i class="fa fa-comments-o"></i> 历史</button>
        <span id="chatTitle">新对话</span>
        <span class="pull-right">
          <button type="button" class="btn btn-xs btn-default" id="btnRename" style="display:none">重命名</button>
          <button type="button" class="btn btn-xs btn-danger" id="btnDelete" style="display:none">删除</button>
        </span>
      </div>
      <div class="panel-body">
        <div class="ai-sugs">
          <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn?'':'disabled';?>>帮我把站名改成我的品牌名</button>
          <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn?'':'disabled';?>>你能改哪些系统设置</button>
          <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn?'':'disabled';?>>生成10张10元加款卡</button>
          <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn?'':'disabled';?>>发布一条站内通知</button>
          <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn?'':'disabled';?>>列出待处理工单</button>
          <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn?'':'disabled';?>>今天经营数据怎么样</button>
        </div>
        <div class="ai-box" id="chatBox"></div>
        <div class="ai-input-row">
          <textarea id="userInput" class="form-control" rows="2" placeholder="例如：站名改成XX网，助手叫小X；或查未处理订单" <?php echo $aiOn?'':'disabled';?>></textarea>
          <button class="btn btn-warning" type="button" id="btnStop" style="display:none" aria-label="停止生成"><i class="fa fa-stop"></i><span>停止</span></button>
          <button class="btn btn-primary" type="button" id="btnSend" <?php echo $aiOn?'':'disabled';?> aria-label="发送消息"><i class="fa fa-paper-plane"></i><span>发送</span></button>
        </div>
        <div class="ai-input-meta">
          <span id="chatStatus"></span>
          <span id="aiCharCount">0/2000</span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-2 ai-log-col">
    <div class="panel panel-default">
      <div class="panel-heading">日志
        <button type="button" class="btn btn-xs btn-default pull-right" id="btnRefreshLogs">刷新</button>
      </div>
      <div class="panel-body ai-side" id="logList"><div class="text-muted">暂无</div></div>
    </div>
  </div>
</div>
</div>
</div>
</div>
<script>
window.AI_PAGE = {
  on: <?php echo $aiOn ? 'true' : 'false'; ?>,
  sitename: <?php echo json_encode($siteName, JSON_UNESCAPED_UNICODE); ?>,
  assistant: <?php echo json_encode($asName, JSON_UNESCAPED_UNICODE); ?>,
  model: <?php echo json_encode(isset($conf['ai_model']) ? $conf['ai_model'] : '未配置', JSON_UNESCAPED_UNICODE); ?>,
  endpoint: 'ajax_ai.php',
  lsKey: 'admin',
  showLogs: true,
  maxLen: 2000,
  welcome: <?php echo json_encode('我是「'.$siteName.'」的'.$asName.'。站名、助手称呼、公告、商品、订单、分站等都可以直接让我处理。敏感操作会先确认并记日志。', JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script src="assets/js/ai.js?ver=<?php echo defined('VERSION')?VERSION:time(); ?>"></script>
<script src="../assets/js/ai-chat.js?ver=<?php echo filemtime(ROOT.'assets/js/ai-chat.js'); ?>"></script>
</body></html>
