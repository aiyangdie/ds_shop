/**
 * AI助手页面脚本（后台风格）
 */
(function () {
    var on = !!(window.AI_PAGE && AI_PAGE.on);
    var sid = 0, busy = false;
    var welcome = '我是彩虹自助下单商城的运营助手，可直接操作系统。\n\n支持经营查看、订单处理、商品分类、分站、配置、货源、支付、工单、发卡、提现等。\n敏感操作会先请你确认，所有工具调用都会记日志。';

    function clean(t) {
        t = String(t == null ? '' : t);
        return t.replace(/\*\*?/g, '').replace(/`+/g, '').replace(/^#{1,6}\s*/gm, '').replace(/^\s*[-*+]\s+/gm, '').replace(/\n{3,}/g, '\n\n').trim();
    }
    function tip(m) { if (window.layer) layer.msg(m); else alert(m); }
    function scrollChat() { var b = $('#chatBox')[0]; if (b) b.scrollTop = b.scrollHeight; }

    function addMsg(role, text, tools, err) {
        var el = $('<div class="ai-msg"></div>').addClass(role === 'user' ? 'user' : (err ? 'bot err' : 'bot'));
        el.append($('<div class="m"></div>').text(role === 'user' ? '你' : '助手'));
        el.append($('<div class="b"></div>').text(role === 'user' ? text : clean(text)));
        if (tools && tools.length) {
            var box = $('<div class="ai-tool"></div>');
            tools.forEach(function (x) {
                var ok = !(x.result && x.result.ok === false) && x.ok !== false;
                box.append($('<div></div>').addClass(ok ? 'ok' : 'bad').text((ok ? '完成：' : '失败：') + (x.label || x.name)));
            });
            el.append(box);
        }
        $('#chatBox').append(el);
        scrollChat();
    }

    function thinking(show) {
        $('#chatBox .thinking').remove();
        if (!show) return;
        $('#chatBox').append('<div class="ai-msg bot thinking"><div class="m">助手</div><div class="b">处理中…</div></div>');
        scrollChat();
    }

    function setBusy(v) {
        busy = v;
        $('#btnSend,#userInput,.sug,#btnNew').prop('disabled', !on || busy);
        $('#btnSend').text(busy ? '…' : '发送');
    }

    function renderSessions(list) {
        var box = $('#sessionList').empty();
        if (!list || !list.length) return box.html('<div class="text-muted">暂无历史</div>');
        list.forEach(function (s) {
            var item = $('<div class="ai-item"></div>').toggleClass('on', Number(s.id) === sid);
            item.append($('<div class="t"></div>').text(s.title || ('对话#' + s.id)));
            item.append($('<div class="s"></div>').text((s.updatetime || '') + ' · ' + (s.msg_count || 0) + '条'));
            item.on('click', function () { loadSession(s.id); });
            box.append(item);
        });
    }

    function renderLogs(logs, prepend) {
        var box = $('#logList');
        if (!prepend) box.empty();
        if ((!logs || !logs.length) && !prepend) return box.html('<div class="text-muted">暂无</div>');
        (logs || []).forEach(function (l) {
            var ok = (typeof l.ok === 'boolean') ? l.ok : Number(l.ok) === 1;
            var id = l.log_id || l.id;
            var item = $('<div class="ai-log"></div>').toggleClass('bad', !ok);
            item.append($('<div></div>').text((l.tool_label || l.label || l.tool_name || l.name) + (ok ? '' : '（失败）')));
            item.append($('<div class="text-muted"></div>').text((l.duration_ms || 0) + 'ms' + (id ? ' · #' + id : '')));
            item.on('click', function () { if (id) showLog(id); });
            prepend ? box.prepend(item) : box.append(item);
        });
    }

    function showLog(id) {
        $.getJSON('ajax_ai.php?act=log_get&id=' + id, function (res) {
            if (res.code !== 0) return tip(res.msg || '读取失败');
            var d = res.data, a = d.arguments || '', r = d.result || '';
            try { a = JSON.stringify(JSON.parse(a), null, 2); } catch (e) {}
            try { r = JSON.stringify(JSON.parse(r), null, 2); } catch (e) {}
            var html = '<p>时间：' + (d.addtime || '') + '　耗时：' + (d.duration_ms || 0) + 'ms</p>'
                + '<p>请求号：' + (d.request_id || '-') + '　IP：' + (d.ip || '-') + '</p>'
                + '<p><b>参数</b></p><pre style="max-height:200px;overflow:auto;background:#f7f7f7;padding:8px">' + $('<div>').text(a).html() + '</pre>'
                + '<p><b>结果</b></p><pre style="max-height:200px;overflow:auto;background:#f7f7f7;padding:8px">' + $('<div>').text(r).html() + '</pre>';
            if (window.layer) layer.open({ type: 1, title: (d.tool_label || d.tool_name), area: ['640px', '480px'], content: '<div style="padding:12px">' + html + '</div>' });
            else tip('请安装 layer 或查看操作日志页');
        });
    }

    function refreshSessions() {
        $.getJSON('ajax_ai.php?act=sessions', function (res) {
            if (res.code === 0) renderSessions(res.data || []);
        });
    }

    function titleBar(title) {
        $('#chatTitle').text(title || (sid ? ('对话#' + sid) : '新对话'));
        $('#btnRename,#btnDelete').toggle(!!sid);
    }

    function loadSession(id) {
        if (busy) return;
        sid = Number(id) || 0;
        if (!sid) {
            titleBar('新对话');
            $('#chatBox').empty();
            addMsg('assistant', welcome);
            renderLogs([]);
            refreshSessions();
            return;
        }
        $.getJSON('ajax_ai.php?act=session_get&id=' + sid, function (res) {
            if (res.code !== 0) return tip(res.msg || '加载失败');
            titleBar(res.session.title || ('对话#' + sid));
            $('#chatBox').empty();
            if (!res.messages || !res.messages.length) addMsg('assistant', welcome);
            else res.messages.forEach(function (m) {
                addMsg(m.role, m.content, m.tool_trace || [], String(m.content || '').indexOf('错误') === 0);
            });
            renderLogs(res.logs || []);
            refreshSessions();
        });
    }

    function send(text) {
        text = $.trim(text || '');
        if (!text || busy || !on) return;
        setBusy(true);
        addMsg('user', text);
        $('#userInput').val('');
        thinking(true);
        $('#chatStatus').text('处理中…');
        $.ajax({
            url: 'ajax_ai.php?act=chat',
            method: 'POST',
            contentType: 'application/json; charset=UTF-8',
            dataType: 'json',
            data: JSON.stringify({ message: text, session_id: sid || 0 }),
            timeout: 180000,
            success: function (res) {
                thinking(false);
                if (res.session_id) {
                    sid = res.session_id;
                    titleBar($('#chatTitle').text() === '新对话' ? text.slice(0, 30) : $('#chatTitle').text());
                }
                if (res.code === 0) {
                    addMsg('assistant', res.reply, res.tool_trace || []);
                    if (res.turn_logs && res.turn_logs.length) {
                        renderLogs(res.turn_logs.map(function (x) {
                            return { id: x.log_id, log_id: x.log_id, tool_label: x.label, tool_name: x.name, ok: x.ok ? 1 : 0, duration_ms: x.duration_ms };
                        }), true);
                    }
                    var u = res.usage || {};
                    $('#chatStatus').text((u.total_tokens ? ('tokens ' + u.total_tokens + ' · ') : '') + (res.request_id || '完成'));
                    refreshSessions();
                } else {
                    addMsg('assistant', res.msg || '失败', null, true);
                    $('#chatStatus').text('失败');
                    if (res.session_id) refreshSessions();
                }
            },
            error: function (xhr) {
                thinking(false);
                addMsg('assistant', '请求失败 HTTP ' + xhr.status, null, true);
                $('#chatStatus').text('网络错误');
            },
            complete: function () { setBusy(false); }
        });
    }

    $('#btnNew').on('click', function () { loadSession(0); });
    $('#btnRename').on('click', function () {
        if (!sid) return;
        var t = prompt('对话标题', $('#chatTitle').text());
        if (t == null || !$.trim(t)) return;
        $.post('ajax_ai.php?act=session_rename', { id: sid, title: $.trim(t) }, function (res) {
            if (typeof res === 'string') try { res = JSON.parse(res); } catch (e) {}
            if (res.code === 0) { titleBar($.trim(t)); refreshSessions(); }
            else tip(res.msg || '失败');
        });
    });
    $('#btnDelete').on('click', function () {
        if (!sid || !confirm('确定删除该对话？日志仍保留。')) return;
        $.post('ajax_ai.php?act=session_delete', { id: sid }, function () { loadSession(0); refreshSessions(); });
    });
    $('#btnRefreshLogs').on('click', function () {
        if (!sid) return renderLogs([]);
        $.getJSON('ajax_ai.php?act=logs&session_id=' + sid + '&limit=100', function (res) {
            if (res.code === 0) renderLogs(res.data || []);
        });
    });
    $('#btnSend').on('click', function () { send($('#userInput').val()); });
    $('#userInput').on('keydown', function (e) {
        if (e.keyCode === 13 && !e.shiftKey) { e.preventDefault(); send($(this).val()); }
    });
    $('.sug').on('click', function () { send($(this).text()); });

    loadSession(0);
})();
