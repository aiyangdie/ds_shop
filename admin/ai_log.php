<?php
/**
 * AI 操作审计日志（详细、可追溯）
 */
include("../includes/common.php");
$title = 'AI操作日志';
include './head.php';
if ($islogin != 1) exit("<script>window.location.href='./login.php';</script>");

$store = new \lib\Ai\Store($DB);
$store->ensureSchema();
?>
<div class="col-xs-12 col-sm-10 col-lg-10 center-block" style="float:none;">
    <div class="block">
        <div class="block-title">
            <h3><i class="fa fa-list-alt"></i>&nbsp;AI 操作日志</h3>
            <div class="block-options pull-right">
                <a href="./ai.php" class="btn btn-sm btn-primary">返回对话</a>
            </div>
        </div>
        <div class="alert alert-info">
            这里记录 AI 每一次工具调用的参数、结果、耗时、IP 与请求编号。对话删除后日志仍会保留，便于排查问题。
        </div>
        <form class="form-inline" id="filterForm" style="margin-bottom:12px" onsubmit="return false;">
            <input type="text" class="form-control" name="keyword" placeholder="关键词（参数/结果）" style="width:180px">
            <input type="text" class="form-control" name="tool_name" placeholder="工具名" style="width:140px">
            <input type="number" class="form-control" name="session_id" placeholder="对话ID" style="width:100px">
            <select class="form-control" name="ok">
                <option value="">全部结果</option>
                <option value="1">成功</option>
                <option value="0">失败</option>
            </select>
            <button type="button" class="btn btn-primary" id="btnSearch">查询</button>
        </form>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>时间</th>
                    <th>对话</th>
                    <th>操作</th>
                    <th>结果</th>
                    <th>耗时</th>
                    <th>IP</th>
                    <th>详情</th>
                </tr>
                </thead>
                <tbody id="logBody">
                <tr><td colspan="8" class="text-center text-muted">加载中…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="modal fade" id="aiLogModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="aiLogModalTitle">操作详情</h4>
            </div>
            <div class="modal-body" id="aiLogModalBody"></div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">关闭</button></div>
        </div>
    </div>
</div>
<style>#aiLogModal pre{max-height:280px;overflow:auto;background:#f7f8fa;border:1px solid #eee;padding:10px;border-radius:4px;white-space:pre-wrap;word-break:break-word;font-size:12px}</style>
<script>
(function () {
    function load() {
        var q = $('#filterForm').serialize();
        $('#logBody').html('<tr><td colspan="8" class="text-center text-muted">加载中…</td></tr>');
        $.getJSON('ajax_ai.php?act=logs&limit=80&' + q, function (res) {
            if (res.code !== 0) {
                $('#logBody').html('<tr><td colspan="8" class="text-danger">' + (res.msg || '失败') + '</td></tr>');
                return;
            }
            var rows = res.data || [];
            if (!rows.length) {
                $('#logBody').html('<tr><td colspan="8" class="text-center text-muted">暂无日志</td></tr>');
                return;
            }
            var html = '';
            rows.forEach(function (l) {
                var ok = Number(l.ok) === 1;
                html += '<tr>' +
                    '<td>' + l.id + '</td>' +
                    '<td>' + (l.addtime || '') + '</td>' +
                    '<td><a href="./ai.php" class="open-session" data-id="' + (l.session_id || '') + '">' + (l.session_id || '-') + '</a></td>' +
                    '<td>' + (l.tool_label || l.tool_name) + '<div class="text-muted" style="font-size:11px">' + l.tool_name + '</div></td>' +
                    '<td>' + (ok ? '<span class="text-success">成功</span>' : '<span class="text-danger">失败</span>') + (l.error_msg ? '<div class="text-danger" style="font-size:11px">' + $('<div>').text(l.error_msg).html() + '</div>' : '') + '</td>' +
                    '<td>' + (l.duration_ms || 0) + 'ms</td>' +
                    '<td>' + (l.ip || '') + '</td>' +
                    '<td><button type="button" class="btn btn-xs btn-default btn-detail" data-id="' + l.id + '">查看</button></td>' +
                    '</tr>';
            });
            $('#logBody').html(html);
        });
    }
    $('#btnSearch').on('click', load);
    $('#filterForm').on('keydown', function (e) { if (e.keyCode === 13) { e.preventDefault(); load(); } });
    $(document).on('click', '.btn-detail', function () {
        var id = $(this).data('id');
        $.getJSON('ajax_ai.php?act=log_get&id=' + id, function (res) {
            if (res.code !== 0) return alert(res.msg || '失败');
            var d = res.data;
            var args = d.arguments || '', result = d.result || '';
            try { args = JSON.stringify(JSON.parse(args), null, 2); } catch (e) {}
            try { result = JSON.stringify(JSON.parse(result), null, 2); } catch (e) {}
            $('#aiLogModalTitle').text((d.tool_label || d.tool_name) + (Number(d.ok) === 1 ? ' · 成功' : ' · 失败'));
            $('#aiLogModalBody').html(
                '<p><b>时间</b> ' + (d.addtime || '') + ' &nbsp; <b>耗时</b> ' + (d.duration_ms || 0) + 'ms</p>' +
                '<p><b>对话</b> #' + (d.session_id || '-') + ' &nbsp; <b>请求号</b> ' + (d.request_id || '-') + '</p>' +
                '<p><b>模型</b> ' + (d.model || '-') + ' &nbsp; <b>IP</b> ' + (d.ip || '-') + '</p>' +
                (d.error_msg ? ('<p class="text-danger"><b>错误</b> ' + $('<div>').text(d.error_msg).html() + '</p>') : '') +
                '<h5>参数</h5><pre></pre><h5>结果</h5><pre></pre>'
            );
            $('#aiLogModalBody pre').eq(0).text(args);
            $('#aiLogModalBody pre').eq(1).text(result);
            $('#aiLogModal').modal('show');
        });
    });
    load();
})();
</script>
