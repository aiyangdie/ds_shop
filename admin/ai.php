<?php
/**
 * AI运营助手
 */
include("../includes/common.php");
$title = 'AI助手';
include './head.php';
if ($islogin != 1) exit("<script language='javascript'>window.location.href='./login.php';</script>");
$aiOn = !empty($conf['ai_enabled']) && intval($conf['ai_enabled']) === 1;
$model = isset($conf['ai_model']) ? htmlspecialchars($conf['ai_model']) : '未配置';
?>
<style>
.ai-box{height:520px;overflow-y:auto;background:#f9f9f9;border:1px solid #eee;padding:10px;margin-bottom:10px}
.ai-msg{margin:0 0 10px;max-width:92%}
.ai-msg.user{margin-left:auto;text-align:right}
.ai-msg .b{display:inline-block;text-align:left;padding:8px 12px;border-radius:4px;line-height:1.6;white-space:pre-wrap;word-break:break-word}
.ai-msg.user .b{background:#337ab7;color:#fff}
.ai-msg.bot .b{background:#fff;border:1px solid #ddd}
.ai-msg.err .b{background:#f2dede;border:1px solid #ebccd1;color:#a94442}
.ai-msg .m{font-size:12px;color:#999;margin-bottom:3px}
.ai-tool{font-size:12px;color:#666;margin-top:4px}
.ai-tool .ok{color:#3c763d}
.ai-tool .bad{color:#a94442}
.ai-side{max-height:520px;overflow-y:auto}
.ai-item{padding:8px;border:1px solid #eee;margin-bottom:6px;cursor:pointer;border-radius:3px}
.ai-item:hover{background:#f5f5f5}
.ai-item.on{background:#e8f4ff;border-color:#b8d6f2}
.ai-item .t{font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ai-item .s{font-size:12px;color:#999}
.ai-log{padding:6px 8px;border:1px solid #eee;margin-bottom:6px;cursor:pointer;font-size:12px}
.ai-log.bad{background:#fdf7f7;border-color:#f1c1c1}
</style>
<div class="col-xs-12 col-sm-10 col-lg-10 center-block" style="float:none;">
<div class="block">
<div class="block-title">
  <h3 class="panel-title"><i class="fa fa-magic"></i>&nbsp;AI助手 <small>模型：<?php echo $model ?></small></h3>
  <div class="block-options pull-right">
    <a href="./ai_log.php" class="btn btn-xs btn-default">操作日志</a>
    <a href="./ai_set.php" class="btn btn-xs btn-default">模型配置</a>
  </div>
</div>

<?php if (!$aiOn) { ?>
<div class="alert alert-warning">请先在 <a href="./ai_set.php">模型配置</a> 中启用 AI 并填写 API Key。</div>
<?php } ?>

<div class="row">
  <div class="col-md-3">
    <div class="panel panel-default">
      <div class="panel-heading">历史对话
        <button type="button" class="btn btn-xs btn-primary pull-right" id="btnNew" <?php echo $aiOn?'':'disabled';?>>新建</button>
      </div>
      <div class="panel-body ai-side" id="sessionList"><div class="text-muted">加载中…</div></div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="panel panel-default">
      <div class="panel-heading clearfix">
        <span id="chatTitle">新对话</span>
        <span class="pull-right">
          <button type="button" class="btn btn-xs btn-default" id="btnRename" style="display:none">重命名</button>
          <button type="button" class="btn btn-xs btn-danger" id="btnDelete" style="display:none">删除</button>
        </span>
      </div>
      <div class="panel-body">
        <div style="margin-bottom:8px">
          <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn?'':'disabled';?>>今天经营数据怎么样</button>
          <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn?'':'disabled';?>>列出未处理订单</button>
          <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn?'':'disabled';?>>你能做什么</button>
        </div>
        <div class="ai-box" id="chatBox"></div>
        <div class="input-group">
          <textarea id="userInput" class="form-control" rows="2" placeholder="用中文描述需求，Enter发送" <?php echo $aiOn?'':'disabled';?>></textarea>
          <span class="input-group-btn" style="vertical-align:bottom">
            <button class="btn btn-primary" type="button" id="btnSend" style="height:54px" <?php echo $aiOn?'':'disabled';?>>发送</button>
          </span>
        </div>
        <p class="help-block" id="chatStatus" style="margin:6px 0 0"></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="panel panel-default">
      <div class="panel-heading">操作日志
        <button type="button" class="btn btn-xs btn-default pull-right" id="btnRefreshLogs">刷新</button>
      </div>
      <div class="panel-body ai-side" id="logList"><div class="text-muted">暂无</div></div>
    </div>
  </div>
</div>
</div>
</div>
<script>
window.AI_PAGE = { on: <?php echo $aiOn ? 'true' : 'false'; ?> };
</script>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script src="assets/js/ai.js?ver=<?php echo defined('VERSION')?VERSION:time(); ?>"></script>
</body></html>
