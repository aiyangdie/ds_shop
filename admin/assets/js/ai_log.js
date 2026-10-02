/**
 * AI操作日志页
 */
(function () {
    function load() {
        $('#logBody').html('<tr><td colspan="8" class="text-center text-muted">加载中…</td></tr>');
        $.getJSON('ajax_ai.php?act=logs&limit=80&' + $('#filterForm').serialize(), function (res) {
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
                html += '<tr><td>' + l.id + '</td><td>' + (l.addtime || '') + '</td><td>' + (l.session_id || '-') + '</td>'
                    + '<td>' + (l.tool_label || l.tool_name) + '<div class="text-muted" style="font-size:11px">' + l.tool_name + '</div></td>'
                    + '<td>' + (ok ? '<span class="text-success">成功</span>' : '<span class="text-danger">失败</span>')
                    + (l.error_msg ? '<div class="text-danger" style="font-size:11px">' + $('<div>').text(l.error_msg).html() + '</div>' : '') + '</td>'
                    + '<td>' + (l.duration_ms || 0) + 'ms</td><td>' + (l.ip || '') + '</td>'
                    + '<td><a href="javascript:;" class="btn-detail" data-id="' + l.id + '">查看</a></td></tr>';
            });
            $('#logBody').html(html);
        });
    }
    $('#btnSearch').on('click', load);
    $('#filterForm').on('keydown', function (e) { if (e.keyCode === 13) { e.preventDefault(); load(); } });
    $(document).on('click', '.btn-detail', function () {
        var id = $(this).data('id');
        $.getJSON('ajax_ai.php?act=log_get&id=' + id, function (res) {
            if (res.code !== 0) return layer.msg(res.msg || '失败');
            var d = res.data, a = d.arguments || '', r = d.result || '';
            try { a = JSON.stringify(JSON.parse(a), null, 2); } catch (e) {}
            try { r = JSON.stringify(JSON.parse(r), null, 2); } catch (e) {}
            var html = '<p>时间：' + (d.addtime || '') + '　耗时：' + (d.duration_ms || 0) + 'ms</p>'
                + '<p>请求号：' + (d.request_id || '-') + '　模型：' + (d.model || '-') + '</p>'
                + '<p><b>参数</b></p><pre style="max-height:200px;overflow:auto;background:#f7f7f7;padding:8px">' + $('<div>').text(a).html() + '</pre>'
                + '<p><b>结果</b></p><pre style="max-height:200px;overflow:auto;background:#f7f7f7;padding:8px">' + $('<div>').text(r).html() + '</pre>';
            layer.open({ type: 1, title: d.tool_label || d.tool_name, area: ['700px', '500px'], content: '<div style="padding:12px">' + html + '</div>' });
        });
    });
    load();
})();
