<?php
/**
 * AI操作日志
 */
include("../includes/common.php");
$title = 'AI操作日志';
include './head.php';
if ($islogin != 1) exit("<script language='javascript'>window.location.href='./login.php';</script>");
$store = new \lib\Ai\Store($DB);
$store->ensureSchema();
?>
<div class="col-xs-12 col-sm-10 col-lg-10 center-block" style="float:none;">
<div class="block">
<div class="block-title clearfix">
  <h3 class="panel-title"><i class="fa fa-list-alt"></i>&nbsp;AI操作日志</h3>
  <div class="block-options pull-right"><a href="./ai.php" class="btn btn-xs btn-primary">返回对话</a></div>
</div>
<div class="alert alert-info">记录每次工具调用的参数、结果、耗时与请求号。删除对话后日志仍保留。</div>
<form class="form-inline" id="filterForm" style="margin-bottom:12px" onsubmit="return false;">
  <input type="text" class="form-control" name="keyword" placeholder="关键词" style="width:160px">
  <input type="text" class="form-control" name="tool_name" placeholder="工具名" style="width:120px">
  <input type="number" class="form-control" name="session_id" placeholder="对话ID" style="width:100px">
  <select class="form-control" name="ok"><option value="">全部</option><option value="1">成功</option><option value="0">失败</option></select>
  <button type="button" class="btn btn-primary" id="btnSearch">查询</button>
</form>
<div class="table-responsive">
<table class="table table-striped table-bordered table-hover">
<thead><tr><th>ID</th><th>时间</th><th>对话</th><th>操作</th><th>结果</th><th>耗时</th><th>IP</th><th>详情</th></tr></thead>
<tbody id="logBody"><tr><td colspan="8" class="text-center text-muted">加载中…</td></tr></tbody>
</table>
</div>
</div>
</div>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script src="assets/js/ai_log.js?ver=<?php echo defined('VERSION')?VERSION:time(); ?>"></script>
</body></html>
