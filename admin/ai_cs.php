<?php
/**
 * 客服中心：微信式聊天台 + 系统工单摘要
 */
include("../includes/common.php");
$title = '客服中心';
include './head.php';
if ($islogin != 1) exit("<script language='javascript'>window.location.href='./login.php';</script>");
$store = new \lib\Ai\Store($DB);
try {
    $store->ensureSchema();
} catch (Exception $e) {
    echo '<div class="col-xs-12"><div class="alert alert-danger">初始化失败：' . htmlspecialchars($e->getMessage()) . '</div></div></body></html>';
    exit;
}
$count0 = intval($DB->getColumn("SELECT count(*) FROM pre_ai_cs WHERE status=0"));
$count1 = intval($DB->getColumn("SELECT count(*) FROM pre_ai_cs WHERE status=1"));
$count2 = intval($DB->getColumn("SELECT count(*) FROM pre_ai_cs WHERE status=2"));
$countAll = intval($DB->getColumn("SELECT count(*) FROM pre_ai_cs"));
$wo0 = 0;
$wo1 = 0;
try {
    $wo0 = intval($DB->getColumn("SELECT count(*) FROM pre_workorder WHERE status=0"));
    $wo1 = intval($DB->getColumn("SELECT count(*) FROM pre_workorder WHERE status=1"));
} catch (Exception $e) {}
$woRows = array();
try {
    $woRows = $DB->getAll("SELECT id,zid,type,orderid,status,addtime,content FROM pre_workorder WHERE status<2 ORDER BY id DESC LIMIT 8");
    if (!$woRows) $woRows = array();
} catch (Exception $e) {
    $woRows = array();
}
?>
<style>
.cs-desk{display:flex;gap:12px;min-height:520px;border:1px solid #e5e5e5;border-radius:6px;overflow:hidden;background:#fff}
.cs-list{width:280px;border-right:1px solid #eee;display:flex;flex-direction:column;background:#fafbfc}
.cs-list-hd{padding:10px;border-bottom:1px solid #eee}
.cs-list-bd{flex:1;overflow-y:auto}
.cs-item{padding:10px 12px;border-bottom:1px solid #f0f0f0;cursor:pointer}
.cs-item:hover,.cs-item.active{background:#eef5ff}
.cs-item .t{font-size:13px;font-weight:600;color:#222;margin-bottom:2px}
.cs-item .s{font-size:12px;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cs-item .m{font-size:11px;color:#aaa;margin-top:2px}
.cs-chat{flex:1;display:flex;flex-direction:column;min-width:0}
.cs-chat-hd{padding:10px 14px;border-bottom:1px solid #eee;background:#f7f8fa;display:flex;justify-content:space-between;align-items:center}
.cs-chat-bd{flex:1;overflow-y:auto;padding:12px;background:#f5f6f8;min-height:320px;max-height:58vh}
.cs-bubble{max-width:78%;margin:0 0 10px;padding:8px 10px;border-radius:10px;font-size:13px;line-height:1.5;word-break:break-word;white-space:pre-wrap}
.cs-bubble.user{background:#fff;border:1px solid #e8ecf1;margin-right:auto}
.cs-bubble.staff{background:#1f6feb;color:#fff;margin-left:auto}
.cs-bubble.ai{background:#e8f5e9;color:#1b5e20;margin-left:auto}
.cs-bubble.system{background:transparent;color:#888;margin:6px auto;text-align:center;font-size:12px;border:0;padding:2px}
.cs-bubble .lab{font-size:11px;opacity:.75;margin-bottom:2px}
.cs-bubble img{max-width:200px;max-height:200px;border-radius:6px;display:block}
.cs-chat-ft{padding:10px;border-top:1px solid #eee;background:#fff}
.cs-chat-ft textarea{width:100%;min-height:64px;resize:vertical;border:1px solid #ddd;border-radius:6px;padding:8px}
.cs-chat-ft .ops{margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.cs-empty{padding:40px;text-align:center;color:#999}
@media (max-width:768px){.cs-desk{flex-direction:column}.cs-list{width:100%;max-height:200px}}
</style>
<div class="col-xs-12 col-sm-10 col-lg-10 center-block" style="float:none;">
<div class="block">
<div class="block-title clearfix">
  <h3 class="panel-title"><i class="fa fa-headphones"></i>&nbsp;客服中心
    <small class="text-muted">微信式会话 + 系统工单</small>
  </h3>
  <div class="block-options pull-right">
    <a href="./ai.php" class="btn btn-xs btn-default">AI对话</a>
    <a href="./ai_set.php" class="btn btn-xs btn-default">存储配置</a>
    <a href="./workorder.php" class="btn btn-xs btn-primary">系统工单完整列表</a>
  </div>
</div>

<ul class="nav nav-tabs" style="margin-bottom:12px">
  <li class="active"><a href="#tabAiCs" data-toggle="tab">在线会话 <span class="badge"><?php echo $count0; ?></span></a></li>
  <li><a href="#tabWo" data-toggle="tab">系统工单待办 <span class="badge"><?php echo ($wo0+$wo1); ?></span></a></li>
</ul>

<div class="tab-content">
<div class="tab-pane active" id="tabAiCs">
<p style="margin-bottom:10px">
  <a href="javascript:;" class="btn btn-primary btn-xs cs-filter" data-status="">全部(<?php echo $countAll?>)</a>
  <a href="javascript:;" class="btn btn-info btn-xs cs-filter" data-status="0">待处理(<?php echo $count0?>)</a>
  <a href="javascript:;" class="btn btn-warning btn-xs cs-filter" data-status="1">进行中(<?php echo $count1?>)</a>
  <a href="javascript:;" class="btn btn-success btn-xs cs-filter" data-status="2">已完结(<?php echo $count2?>)</a>
</p>
<form class="form-inline" id="csFilter" style="margin-bottom:12px" onsubmit="return false;">
  <input type="hidden" name="status" id="csStatus" value="0">
  <input type="text" class="form-control" name="keyword" id="csKeyword" placeholder="联系方式/订单/问题" style="width:200px">
  <button type="button" class="btn btn-primary" id="csSearch">查询</button>
</form>

<div class="cs-desk">
  <div class="cs-list">
    <div class="cs-list-hd"><strong>会话列表</strong></div>
    <div class="cs-list-bd" id="csListBd"><div class="cs-empty">加载中…</div></div>
  </div>
  <div class="cs-chat">
    <div class="cs-chat-hd">
      <div>
        <strong id="csChatTitle">请选择会话</strong>
        <span class="text-muted" id="csChatMeta" style="margin-left:8px;font-size:12px"></span>
      </div>
      <div>
        <button type="button" class="btn btn-xs btn-default" id="csRefreshMsg" disabled>刷新</button>
        <button type="button" class="btn btn-xs btn-success" id="csCloseBtn" disabled>完结</button>
      </div>
    </div>
    <div class="cs-chat-bd" id="csChatBd"><div class="cs-empty">从左侧打开一个会话开始回复</div></div>
    <div class="cs-chat-ft">
      <textarea id="csReplyText" placeholder="输入回复…（Enter 发送，Shift+Enter 换行）" disabled></textarea>
      <div class="ops">
        <label class="btn btn-default btn-sm" style="margin:0">
          发图 <input type="file" id="csFile" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" disabled>
        </label>
        <button type="button" class="btn btn-primary btn-sm" id="csSendBtn" disabled>发送</button>
        <label style="font-weight:normal;margin:0 0 0 8px"><input type="checkbox" id="csCloseAfter"> 发送后完结</label>
        <span class="text-muted" id="csSendTip" style="font-size:12px"></span>
      </div>
    </div>
  </div>
</div>
</div>

<div class="tab-pane" id="tabWo">
<p class="text-muted">以下为未完结系统工单摘要，点「打开」进入完整处理页。</p>
<p>
  <a href="./workorder.php" class="btn btn-xs btn-info">待处理 <?php echo $wo0; ?></a>
  <a href="./workorder.php" class="btn btn-xs btn-warning">处理中 <?php echo $wo1; ?></a>
</p>
<div class="table-responsive">
<table class="table table-striped table-bordered">
<thead><tr><th>ID</th><th>分站</th><th>订单</th><th>状态</th><th>时间</th><th>摘要</th><th></th></tr></thead>
<tbody>
<?php
$stMap = array(0=>'待处理',1=>'已回复',2=>'已完结');
if (!$woRows) {
    echo '<tr><td colspan="7" class="text-center text-muted">暂无待办系统工单</td></tr>';
} else {
    foreach ($woRows as $w) {
        $sum = strip_tags(str_replace('*', ' / ', strval($w['content'])));
        if (function_exists('mb_substr')) $sum = mb_substr($sum, 0, 60);
        else $sum = substr($sum, 0, 60);
        $st = intval($w['status']);
        echo '<tr>';
        echo '<td>'.intval($w['id']).'</td>';
        echo '<td>'.intval($w['zid']).'</td>';
        echo '<td>'.(intval($w['orderid'])?:'-').'</td>';
        echo '<td>'.(isset($stMap[$st])?$stMap[$st]:$st).'</td>';
        echo '<td>'.htmlspecialchars($w['addtime']).'</td>';
        echo '<td>'.htmlspecialchars($sum).'</td>';
        echo '<td><a class="btn btn-xs btn-primary" href="./workorder.php">打开</a></td>';
        echo '</tr>';
    }
}
?>
</tbody>
</table>
</div>
</div>
</div>

</div>
</div>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script src="assets/js/ai_cs.js?ver=<?php echo defined('VERSION')?VERSION:time(); ?>"></script>
</body></html>
