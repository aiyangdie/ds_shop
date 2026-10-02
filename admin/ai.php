<?php
/**
 * AI 运营助手：多对话框历史 + 详细操作日志（体验优化）
 */
include("../includes/common.php");
$title = 'AI助手';
include './head.php';
if ($islogin != 1) exit("<script>window.location.href='./login.php';</script>");
$aiOn = !empty($conf['ai_enabled']) && intval($conf['ai_enabled']) === 1;
$model = isset($conf['ai_model']) ? htmlspecialchars($conf['ai_model']) : '未配置';
$welcome = "我是彩虹自助下单商城的运营助手，可以直接帮你操作系统。\n\n"
    . "经营、订单、商品、分站、配置、货源、支付、工单、发卡、提现等，用中文说即可。\n\n"
    . "左侧可切换历史对话；右侧是操作日志，点开可看完整参数和结果。退款、充值、批量改状态等敏感操作会先请你确认。";
?>
<style>
.ai-layout{display:flex;gap:12px;height:calc(100vh - 140px);min-height:560px}
.ai-side,.ai-logpane{width:250px;flex-shrink:0;background:#fff;border:1px solid #e5e7eb;border-radius:6px;display:flex;flex-direction:column;overflow:hidden}
.ai-logpane{width:310px}
.ai-main{flex:1;min-width:0;display:flex;flex-direction:column;background:#fff;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden}
.ai-side-hd,.ai-log-hd,.ai-main-hd{padding:10px 12px;border-bottom:1px solid #eee;font-weight:600;background:#fafbfc}
.ai-side-bd,.ai-log-bd{flex:1;overflow-y:auto;padding:8px}
.ai-session{padding:8px 10px;border-radius:6px;cursor:pointer;margin-bottom:4px;border:1px solid transparent;position:relative}
.ai-session:hover{background:#f5f7fa}
.ai-session.active{background:#eef5ff;border-color:#cfe2ff}
.ai-session .t{font-size:13px;color:#222;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding-right:42px}
.ai-session .m{font-size:11px;color:#999;margin-top:2px}
.ai-session .ops{position:absolute;right:6px;top:8px;display:none}
.ai-session:hover .ops,.ai-session.active .ops{display:block}
.ai-session .ops button{padding:0 4px;border:0;background:transparent;color:#888}
.ai-session .ops button:hover{color:#333}
.ai-chat{flex:1;overflow-y:auto;background:linear-gradient(180deg,#f7f8fa 0%,#f3f5f8 100%);padding:14px}
.ai-msg{margin-bottom:14px;max-width:90%;animation:aiFade .2s ease}
.ai-msg.user{margin-left:auto;text-align:right}
.ai-msg .bubble{display:inline-block;text-align:left;padding:10px 14px;border-radius:12px;line-height:1.65;white-space:pre-wrap;word-break:break-word;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.ai-msg.user .bubble{background:#3f9eff;color:#fff;border-bottom-right-radius:4px}
.ai-msg.assistant .bubble{background:#fff;border:1px solid #e8eaed;border-bottom-left-radius:4px;color:#222}
.ai-msg.assistant.error .bubble{background:#fff5f5;border-color:#f5c2c7;color:#b42318}
.ai-msg.assistant.thinking .bubble{color:#666;font-style:italic}
.ai-msg .meta{font-size:12px;color:#999;margin:4px 2px}
.ai-tools{font-size:12px;margin-top:8px;text-align:left;background:#f8fafc;border:1px solid #eef0f3;border-radius:6px;padding:6px 8px}
.ai-tools .ok{color:#067647}
.ai-tools .fail{color:#b42318}
.ai-input{padding:10px 12px;border-top:1px solid #eee;background:#fff}
.ai-input-row{display:flex;gap:8px;align-items:flex-end}
.ai-input textarea{flex:1;min-height:56px;max-height:160px;resize:vertical;border-radius:8px}
.ai-input .hint{font-size:11px;color:#aaa;margin-top:6px}
.ai-log-item{padding:8px 10px;border:1px solid #eee;border-radius:6px;margin-bottom:6px;font-size:12px;cursor:pointer;transition:background .15s}
.ai-log-item:hover{background:#f8fafc}
.ai-log-item.fail{border-color:#f5c2c7;background:#fff8f8}
.ai-log-item .n{font-weight:600;color:#333}
.ai-log-item .s{color:#888;margin-top:2px}
.ai-suggest{padding:8px 12px 0;display:flex;flex-wrap:wrap;gap:6px}
.ai-status{font-size:12px;color:#888;font-weight:normal;margin-left:8px}
.ai-filter{padding:0 8px 8px}
.ai-filter input{width:100%;height:30px;font-size:12px}
@keyframes aiFade{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
.ai-dots span{display:inline-block;width:6px;height:6px;margin:0 2px;border-radius:50%;background:#999;animation:aiDot 1s infinite ease-in-out}
.ai-dots span:nth-child(2){animation-delay:.15s}
.ai-dots span:nth-child(3){animation-delay:.3s}
@keyframes aiDot{0%,80%,100%{opacity:.3;transform:scale(.8)}40%{opacity:1;transform:scale(1)}}
#aiLogModal pre{max-height:280px;overflow:auto;background:#f7f8fa;border:1px solid #eee;padding:10px;border-radius:4px;white-space:pre-wrap;word-break:break-word;font-size:12px}
@media (max-width:1200px){.ai-logpane{display:none}.ai-side{width:210px}}
@media (max-width:768px){.ai-layout{flex-direction:column;height:auto}.ai-side{width:100%;max-height:160px}.ai-main{min-height:420px}}
</style>
<div class="col-xs-12 center-block" style="float:none;">
    <div class="block" style="margin-bottom:0">
        <div class="block-title">
            <h3><i class="fa fa-magic"></i>&nbsp;AI 运营助手</h3>
            <div class="block-options pull-right">
                <span class="text-muted" style="margin-right:8px">模型：<?php echo $model ?></span>
                <a href="./ai_log.php" class="btn btn-sm btn-default">全部日志</a>
                <a href="./ai_set.php" class="btn btn-sm btn-default">模型配置</a>
            </div>
        </div>
        <?php if (!$aiOn) { ?>
            <div class="alert alert-warning" style="margin:12px">AI 尚未启用。请先到 <a href="./ai_set.php">模型配置</a> 开启并填写 API Key。</div>
        <?php } ?>
        <div class="ai-layout" style="margin:0 12px 12px">
            <div class="ai-side">
                <div class="ai-side-hd clearfix">
                    历史对话
                    <button type="button" class="btn btn-xs btn-primary pull-right" id="btnNew" <?php echo $aiOn ? '' : 'disabled'; ?>>新建</button>
                </div>
                <div class="ai-filter"><input type="text" id="sessionFilter" class="form-control" placeholder="搜索对话标题"></div>
                <div class="ai-side-bd" id="sessionList"></div>
            </div>
            <div class="ai-main">
                <div class="ai-main-hd clearfix">
                    <span id="chatTitle">新对话</span>
                    <span class="ai-status" id="chatStatus"></span>
                    <div class="pull-right" id="chatActions" style="display:none">
                        <button type="button" class="btn btn-xs btn-default" id="btnRename">重命名</button>
                        <button type="button" class="btn btn-xs btn-danger" id="btnDelete">删除</button>
                    </div>
                </div>
                <div class="ai-suggest" id="suggestBar">
                    <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn ? '' : 'disabled'; ?>>今天经营数据怎么样</button>
                    <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn ? '' : 'disabled'; ?>>列出未处理订单</button>
                    <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn ? '' : 'disabled'; ?>>查看对接货源站点</button>
                    <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn ? '' : 'disabled'; ?>>你能做什么</button>
                </div>
                <div class="ai-chat" id="chatBox"></div>
                <div class="ai-input">
                    <div class="ai-input-row">
                        <textarea id="userInput" class="form-control" placeholder="用中文描述要做的事，Enter 发送，Shift+Enter 换行" <?php echo $aiOn ? '' : 'disabled'; ?>></textarea>
                        <button type="button" class="btn btn-primary" id="btnSend" style="min-width:88px;height:40px" <?php echo $aiOn ? '' : 'disabled'; ?>>发送</button>
                    </div>
                    <div class="hint">敏感操作会要求确认 · 所有工具调用都会写入右侧日志</div>
                </div>
            </div>
            <div class="ai-logpane">
                <div class="ai-log-hd clearfix">操作日志 <small class="text-muted">当前对话</small>
                    <button type="button" class="btn btn-xs btn-default pull-right" id="btnRefreshLogs">刷新</button>
                </div>
                <div class="ai-log-bd" id="logList"><div class="text-muted" style="padding:8px">暂无操作</div></div>
            </div>
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
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">关闭</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var aiOn = <?php echo $aiOn ? 'true' : 'false'; ?>;
    var welcomeText = <?php echo json_encode($welcome, JSON_UNESCAPED_UNICODE); ?>;
    var sessionId = 0;
    var busy = false;
    var sessionCache = [];
    var thinkingEl = null;

    function cleanText(text) {
        text = String(text == null ? '' : text);
        text = text.replace(/\*\*?/g, '').replace(/__/g, '').replace(/^#{1,6}\s*/gm, '').replace(/`+/g, '');
        text = text.replace(/^\s*[-*+]\s+/gm, '').replace(/^\s*>\s?/gm, '');
        try { text = text.replace(/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}]/gu, ''); } catch (e) {}
        return text.replace(/\n{3,}/g, '\n\n').replace(/^\s+|\s+$/g, '');
    }

    function relTime(s) {
        if (!s) return '';
        var t = Date.parse(String(s).replace(/-/g, '/'));
        if (!t) return s;
        var diff = Math.floor((Date.now() - t) / 1000);
        if (diff < 60) return '刚刚';
        if (diff < 3600) return Math.floor(diff / 60) + '分钟前';
        if (diff < 86400) return Math.floor(diff / 3600) + '小时前';
        if (diff < 86400 * 7) return Math.floor(diff / 86400) + '天前';
        return String(s).slice(5, 16);
    }

    function setBusy(on) {
        busy = !!on;
        $('#btnSend').prop('disabled', !aiOn || busy).html(busy ? '<i class="fa fa-spinner fa-spin"></i>' : '发送');
        $('#userInput').prop('disabled', !aiOn || busy);
        $('#suggestBar .sug').prop('disabled', !aiOn || busy);
        $('#btnNew').prop('disabled', !aiOn || busy);
    }

    function showThinking() {
        removeThinking();
        thinkingEl = $('<div class="ai-msg assistant thinking"></div>');
        thinkingEl.append($('<div class="meta"></div>').text('助手'));
        thinkingEl.append($('<div class="bubble"></div>').html('正在处理 <span class="ai-dots"><span></span><span></span><span></span></span>'));
        $('#chatBox').append(thinkingEl);
        $('#chatBox').scrollTop($('#chatBox')[0].scrollHeight);
    }

    function removeThinking() {
        if (thinkingEl) { thinkingEl.remove(); thinkingEl = null; }
        $('.ai-msg.thinking').remove();
    }

    function appendMsg(role, text, tools, isError) {
        var el = $('<div class="ai-msg"></div>').addClass(role);
        if (isError) el.addClass('error');
        el.append($('<div class="meta"></div>').text(role === 'user' ? '你' : '助手'));
        el.append($('<div class="bubble"></div>').text(role === 'assistant' ? cleanText(text) : (text || '')));
        if (tools && tools.length) {
            var t = $('<div class="ai-tools"></div>');
            tools.forEach(function (x) {
                var ok = !(x.result && x.result.ok === false) && x.ok !== false;
                var label = x.label || x.name;
                t.append($('<div></div>').addClass(ok ? 'ok' : 'fail').text((ok ? '已完成：' : '未完成：') + label));
            });
            el.append(t);
        }
        $('#chatBox').append(el);
        $('#chatBox').scrollTop($('#chatBox')[0].scrollHeight);
    }

    function showWelcome() {
        $('#chatBox').html('');
        appendMsg('assistant', welcomeText);
        $('#chatActions').hide();
        $('#chatStatus').text('');
    }

    function updateTitleBar(title) {
        $('#chatTitle').text(title || (sessionId ? ('对话 #' + sessionId) : '新对话'));
        if (sessionId) $('#chatActions').show();
        else $('#chatActions').hide();
    }

    function renderSessions(list) {
        sessionCache = list || [];
        var kw = $.trim($('#sessionFilter').val() || '').toLowerCase();
        var box = $('#sessionList').empty();
        var filtered = sessionCache.filter(function (s) {
            if (!kw) return true;
            return String(s.title || '').toLowerCase().indexOf(kw) >= 0 || String(s.id).indexOf(kw) >= 0;
        });
        if (!filtered.length) {
            box.html('<div class="text-muted" style="padding:8px">' + (kw ? '没有匹配的对话' : '暂无历史，点击新建开始') + '</div>');
            return;
        }
        filtered.forEach(function (s) {
            var item = $('<div class="ai-session"></div>').attr('data-id', s.id).toggleClass('active', Number(s.id) === Number(sessionId));
            item.append($('<div class="t"></div>').text(s.title || ('对话 #' + s.id)));
            item.append($('<div class="m"></div>').text(relTime(s.updatetime) + ' · ' + (s.msg_count || 0) + ' 条 · ' + (s.tool_count || 0) + ' 次操作'));
            var ops = $('<div class="ops"></div>');
            ops.append($('<button type="button" title="删除"><i class="fa fa-trash"></i></button>').on('click', function (e) {
                e.stopPropagation();
                deleteSession(s.id);
            }));
            item.append(ops);
            item.on('click', function () { loadSession(s.id); });
            box.append(item);
        });
    }

    function openLogDetail(id) {
        $.getJSON('ajax_ai.php?act=log_get&id=' + id, function (res) {
            if (res.code !== 0) return layerMsg(res.msg || '读取失败');
            var d = res.data;
            var args = d.arguments || '';
            var result = d.result || '';
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
    }

    function renderLogs(logs, prepend) {
        var box = $('#logList');
        if (!prepend) box.empty();
        if ((!logs || !logs.length) && !prepend) {
            box.html('<div class="text-muted empty-log" style="padding:8px">暂无操作</div>');
            return;
        }
        box.find('.empty-log').remove();
        var frag = $(document.createDocumentFragment ? document.createDocumentFragment() : []);
        // jQuery way: build array of nodes
        var nodes = [];
        (logs || []).forEach(function (l) {
            var ok = (typeof l.ok === 'boolean') ? l.ok : (Number(l.ok) === 1);
            var id = l.log_id || l.id;
            var item = $('<div class="ai-log-item"></div>').toggleClass('fail', !ok).attr('data-id', id);
            item.append($('<div class="n"></div>').text((l.tool_label || l.label || l.tool_name || l.name) + (ok ? '' : '（失败）')));
            item.append($('<div class="s"></div>').text((l.addtime ? relTime(l.addtime) + ' · ' : '') + (l.duration_ms || 0) + 'ms' + (id ? (' · #' + id) : '')));
            item.on('click', function () { if (id) openLogDetail(id); });
            nodes.push(item);
        });
        if (prepend) {
            for (var i = nodes.length - 1; i >= 0; i--) box.prepend(nodes[i]);
        } else {
            nodes.forEach(function (n) { box.append(n); });
        }
    }

    function layerMsg(msg) {
        if (window.layer && layer.msg) layer.msg(msg);
        else alert(msg);
    }

    function refreshSessions(cb) {
        $.getJSON('ajax_ai.php?act=sessions', function (res) {
            if (res.code === 0) renderSessions(res.data || []);
            if (cb) cb();
        });
    }

    function refreshLogsOnly() {
        if (!sessionId) { renderLogs([]); return; }
        $.getJSON('ajax_ai.php?act=logs&session_id=' + sessionId + '&limit=100', function (res) {
            if (res.code === 0) renderLogs(res.data || []);
        });
    }

    function loadSession(id) {
        if (busy) return;
        sessionId = Number(id) || 0;
        if (!sessionId) {
            updateTitleBar('新对话');
            showWelcome();
            renderLogs([]);
            refreshSessions();
            return;
        }
        $('#chatStatus').text('加载中…');
        $.getJSON('ajax_ai.php?act=session_get&id=' + sessionId, function (res) {
            if (res.code !== 0) {
                layerMsg(res.msg || '加载失败');
                $('#chatStatus').text('');
                return;
            }
            updateTitleBar(res.session.title || ('对话 #' + sessionId));
            $('#chatBox').html('');
            if (!res.messages || !res.messages.length) showWelcome();
            else {
                res.messages.forEach(function (m) {
                    appendMsg(m.role, m.content, m.tool_trace || [], m.role === 'assistant' && String(m.content).indexOf('错误') === 0);
                });
                $('#chatActions').show();
            }
            renderLogs(res.logs || []);
            $('#chatStatus').text((res.messages ? res.messages.length : 0) + ' 条消息');
            refreshSessions();
            $('#userInput').focus();
        });
    }

    function newSession() {
        if (busy) return;
        sessionId = 0;
        updateTitleBar('新对话');
        showWelcome();
        renderLogs([]);
        $('.ai-session').removeClass('active');
        $('#userInput').focus();
    }

    function renameSession() {
        if (!sessionId) return;
        var cur = $('#chatTitle').text();
        var title = prompt('请输入新的对话标题', cur);
        if (title == null) return;
        title = $.trim(title);
        if (!title) return;
        $.post('ajax_ai.php?act=session_rename', { id: sessionId, title: title }, function (res) {
            if (typeof res === 'string') try { res = JSON.parse(res); } catch (e) {}
            if (res.code === 0) {
                updateTitleBar(title);
                refreshSessions();
            } else layerMsg(res.msg || '重命名失败');
        });
    }

    function deleteSession(id) {
        id = Number(id || sessionId);
        if (!id) return;
        if (!confirm('确定删除该对话？操作日志仍会保留。')) return;
        $.post('ajax_ai.php?act=session_delete', { id: id }, function (res) {
            if (typeof res === 'string') try { res = JSON.parse(res); } catch (e) {}
            if (res.code !== 0) return layerMsg(res.msg || '删除失败');
            if (Number(sessionId) === id) newSession();
            refreshSessions();
        });
    }

    function send(text) {
        text = $.trim(text || '');
        if (!text || busy || !aiOn) return;
        setBusy(true);
        appendMsg('user', text);
        $('#userInput').val('').focus();
        showThinking();
        $('#chatStatus').text('处理中…');
        $.ajax({
            url: 'ajax_ai.php?act=chat',
            method: 'POST',
            contentType: 'application/json; charset=UTF-8',
            dataType: 'json',
            data: JSON.stringify({ message: text, session_id: sessionId || 0 }),
            timeout: 180000,
            success: function (res) {
                removeThinking();
                if (res.session_id) {
                    sessionId = res.session_id;
                    updateTitleBar($('#chatTitle').text() === '新对话' ? text.slice(0, 30) : $('#chatTitle').text());
                }
                if (res.code === 0) {
                    appendMsg('assistant', res.reply, res.tool_trace || []);
                    if (res.turn_logs && res.turn_logs.length) {
                        var mapped = res.turn_logs.map(function (x) {
                            return {
                                id: x.log_id,
                                log_id: x.log_id,
                                tool_name: x.name,
                                tool_label: x.label,
                                ok: x.ok ? 1 : 0,
                                duration_ms: x.duration_ms,
                                addtime: new Date().toISOString().slice(0, 19).replace('T', ' ')
                            };
                        });
                        renderLogs(mapped, true);
                    }
                    var u = res.usage || {};
                    var tip = '';
                    if (u.total_tokens) tip = 'tokens ' + u.total_tokens;
                    if (res.request_id) tip += (tip ? ' · ' : '') + res.request_id;
                    $('#chatStatus').text(tip || '完成');
                    refreshSessions();
                } else {
                    appendMsg('assistant', res.msg || '未知错误', null, true);
                    $('#chatStatus').text('失败');
                    if (res.session_id) refreshSessions();
                }
            },
            error: function (xhr) {
                removeThinking();
                appendMsg('assistant', '请求失败，HTTP ' + xhr.status + '，请稍后重试', null, true);
                $('#chatStatus').text('网络错误');
            },
            complete: function () {
                setBusy(false);
                $('#userInput').focus();
            }
        });
    }

    $('#btnNew').on('click', newSession);
    $('#btnRename').on('click', renameSession);
    $('#btnDelete').on('click', function () { deleteSession(sessionId); });
    $('#btnRefreshLogs').on('click', refreshLogsOnly);
    $('#btnSend').on('click', function () { send($('#userInput').val()); });
    $('#userInput').on('keydown', function (e) {
        if (e.keyCode === 13 && !e.shiftKey) { e.preventDefault(); send($(this).val()); }
    });
    $('#sessionFilter').on('input', function () { renderSessions(sessionCache); });
    $('.sug').on('click', function () { send($(this).text()); });

    showWelcome();
    refreshSessions();
    if (aiOn) $('#userInput').focus();
})();
</script>
