<?php
include("../includes/common.php");
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$title = 'AI助手';
include './head.php';

$aiOn = !empty($conf['ai_enabled']) && intval($conf['ai_enabled']) === 1
    && (!isset($conf['ai_user_enabled']) || intval($conf['ai_user_enabled']) === 1);
$siteName = !empty($userrow['sitename']) ? $userrow['sitename'] : (!empty($conf['sitename']) ? $conf['sitename'] : '本站');
$asName = !empty($conf['ai_assistant_name']) ? $conf['ai_assistant_name'] : '助手';
$power = intval($userrow['power']);
?>
<link rel="stylesheet" href="<?php echo $cdnserver?>assets/css/ai-chat.css?ver=<?php echo filemtime(ROOT.'assets/css/ai-chat.css'); ?>">
<div class="wrapper ai-page">
<div class="col-sm-12">
<?php if (!$aiOn) { ?>
<div class="alert alert-warning">用户端 AI 未启用，请联系站长在后台「AI模型配置」中开启。</div>
<?php } ?>
<p class="ai-hint">只能查看和操作你自己的数据。刷新会恢复上次对话。Enter 发送，Shift+Enter 换行。</p>
<div class="ai-wrap ai-theme-user">
<div class="row">
  <div class="col-md-3 ai-sidebar-col">
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
  <div class="col-md-9 ai-main-col">
    <div class="panel panel-default ai-chat-panel">
      <div class="panel-heading clearfix">
        <button type="button" class="btn btn-xs btn-default ai-mobile-sessions" id="btnSessions" aria-label="打开历史对话"><i class="fa fa-comments-o"></i> 历史</button>
        <span id="chatTitle">新对话</span>
        <small class="text-muted" id="aiBrandSub"><?php echo htmlspecialchars($siteName); ?> · <?php echo htmlspecialchars($asName); ?></small>
        <span class="pull-right">
          <button type="button" class="btn btn-xs btn-default" id="btnRename" style="display:none">重命名</button>
          <button type="button" class="btn btn-xs btn-danger" id="btnDelete" style="display:none">删除</button>
        </span>
      </div>
      <div class="panel-body">
        <div class="ai-sugs">
          <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn?'':'disabled';?>>查我最近的订单</button>
          <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn?'':'disabled';?>>我的余额还有多少</button>
          <?php if(!empty($conf['workorder_open'])){ ?>
          <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn?'':'disabled';?>>列出我的工单</button>
          <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn?'':'disabled';?>>帮我看看最近订单售后怎么处理</button>
          <?php } ?>
          <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn?'':'disabled';?>>有什么热门商品推荐</button>
          <?php if($power>0){ ?>
          <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn?'':'disabled';?>>查看我的站点信息</button>
          <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn?'':'disabled';?>>把我的站名改成……</button>
          <?php } ?>
        </div>
        <div class="ai-box" id="chatBox"></div>
        <div class="ai-input-row">
          <textarea id="userInput" class="form-control" rows="2" placeholder="例如：查一下我未完成的订单" <?php echo $aiOn?'':'disabled';?>></textarea>
          <button class="btn btn-warning" type="button" id="btnStop" style="display:none" aria-label="停止生成"><i class="fa fa-stop"></i><span>停止</span></button>
          <button class="btn btn-info" type="button" id="btnSend" <?php echo $aiOn?'':'disabled';?> aria-label="发送消息"><i class="fa fa-paper-plane"></i><span>发送</span></button>
        </div>
        <div class="ai-input-meta">
          <span id="chatStatus"></span>
          <span id="aiCharCount">0/2000</span>
        </div>
      </div>
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
  model: <?php echo json_encode(isset($conf['ai_model']) ? $conf['ai_model'] : '', JSON_UNESCAPED_UNICODE); ?>,
  endpoint: 'ajax_ai.php',
  lsKey: 'user',
  showLogs: false,
  maxLen: 2000,
  welcome: <?php echo json_encode('我是「'.$siteName.'」的'.$asName.'。可以帮你查订单、余额、工单'.($power>0?'，以及修改本站显示信息':'').'。复杂售后请提交工单，我不能直接退款。刷新页面会自动恢复上次对话。', JSON_UNESCAPED_UNICODE); ?>
};
</script>
<?php include './foot.php'; ?>
<script src="<?php echo $cdnserver?>assets/js/ai-chat.js?ver=<?php echo filemtime(ROOT.'assets/js/ai-chat.js'); ?>"></script>
