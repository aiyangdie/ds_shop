/**
 * 共用 AI 对话页脚本（admin / user）
 * AI_PAGE: { on, sitename, assistant, model, endpoint, welcome, showLogs, lsKey, maxLen }
 */
(function () {
    function boot($) {
        var cfg = window.AI_PAGE || {};
        var on = !!cfg.on;
        var endpoint = cfg.endpoint || 'ajax_ai.php';
        var lsKey = 'ai_sid_' + (cfg.lsKey || 'default');
        var draftKey = 'ai_draft_' + (cfg.lsKey || 'default');
        var maxLen = cfg.maxLen || 2000;
        var sid = 0, busy = false;
        var site = cfg.sitename || '本站';
        var asName = cfg.assistant || '助手';
        var model = cfg.model || '';
        var showLogs = cfg.showLogs !== false;
        var sessionCache = [];
        var lastFailed = '';
        var currentXhr = null;

        function welcomeText() {
            if (cfg.welcome) return cfg.welcome;
            return '我是「' + site + '」的' + asName + '。可以直接问我相关问题。';
        }
        function readSid() {
            try { return parseInt(localStorage.getItem(lsKey) || '0', 10) || 0; } catch (e) { return 0; }
        }
        function writeSid(id) {
            try {
                if (id > 0) localStorage.setItem(lsKey, String(id));
                else localStorage.removeItem(lsKey);
            } catch (e) {}
        }
        function saveDraft(v) {
            try {
                if (v) localStorage.setItem(draftKey, v);
                else localStorage.removeItem(draftKey);
            } catch (e) {}
        }
        function loadDraft() {
            try { return localStorage.getItem(draftKey) || ''; } catch (e) { return ''; }
        }
        function relTime(s) {
            if (!s) return '';
            var t = Date.parse(String(s).replace(/-/g, '/'));
            if (!t) return s;
            var d = Math.floor((Date.now() - t) / 1000);
            if (d < 60) return '刚刚';
            if (d < 3600) return Math.floor(d / 60) + '分钟前';
            if (d < 86400) return Math.floor(d / 3600) + '小时前';
            if (d < 86400 * 7) return Math.floor(d / 86400) + '天前';
            return String(s).slice(0, 16);
        }
        function applySite(s) {
            if (!s) return;
            if (s.sitename) site = s.sitename;
            if (s.assistant_name) asName = s.assistant_name;
            if (window.AI_PAGE) {
                AI_PAGE.sitename = site;
                AI_PAGE.assistant = asName;
            }
            $('#aiBrandSub').text(site + ' · ' + asName + (model ? (' · ' + model) : ''));
        }
        function clean(t) {
            t = String(t == null ? '' : t);
            return t.replace(/\*\*?/g, '').replace(/`+/g, '').replace(/^#{1,6}\s*/gm, '').replace(/^\s*[-*+]\s+/gm, '').replace(/\n{3,}/g, '\n\n').trim();
        }
        function tip(m) { if (window.layer) layer.msg(m); else alert(m); }
        function scrollChat() {
            var b = $('#chatBox')[0];
            if (b) b.scrollTop = b.scrollHeight;
        }
        function closeMobileSessions() {
            $('.ai-wrap').removeClass('ai-sessions-open');
            $('body').removeClass('ai-drawer-lock');
        }
        function setSugsVisible(show) {
            $('.ai-sugs').toggleClass('is-hidden', !show);
        }
        function updateCharCount() {
            var n = ($('#userInput').val() || '').length;
            var el = $('#aiCharCount');
            if (!el.length) return;
            el.text(n + '/' + maxLen).removeClass('warn over');
            if (n > maxLen) el.addClass('over');
            else if (n > maxLen * 0.85) el.addClass('warn');
        }
        function copyText(text) {
            text = String(text || '');
            if (!text) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () { tip('已复制'); }).catch(function () {
                    window.prompt('复制以下内容', text);
                });
            } else {
                window.prompt('复制以下内容', text);
            }
        }

        function addMsg(role, text, tools, err, opts) {
            opts = opts || {};
            var el = $('<div class="ai-msg"></div>').addClass(role === 'user' ? 'user' : (err ? 'bot err' : 'bot'));
            var meta = $('<div class="m"></div>');
            meta.append(document.createTextNode(role === 'user' ? '你' : asName));
            if (role !== 'user' && !opts.thinking) {
                var copyBtn = $('<a href="javascript:;" class="ai-copy">复制</a>');
                copyBtn.on('click', function (e) {
                    e.preventDefault();
                    copyText(clean(text));
                });
                meta.append(document.createTextNode(' · '));
                meta.append(copyBtn);
            }
            el.append(meta);
            el.append($('<div class="b"></div>').text(role === 'user' ? text : clean(text)));
            if (tools && tools.length && showLogs) {
                var box = $('<div class="ai-tool"></div>');
                tools.forEach(function (x) {
                    var ok = !(x.result && x.result.ok === false) && x.ok !== false;
                    box.append($('<div></div>').addClass(ok ? 'ok' : 'bad').text((ok ? '完成：' : '失败：') + (x.label || x.name)));
                });
                el.append(box);
            }
            if (err && opts.retryText) {
                var retry = $('<div class="ai-retry"></div>');
                var btn = $('<button type="button" class="btn btn-xs btn-default">重试</button>');
                btn.on('click', function () { send(opts.retryText); });
                retry.append(btn);
                el.append(retry);
            }
            $('#chatBox').append(el);
            scrollChat();
        }

        function thinking(show) {
            $('#chatBox .thinking').remove();
            if (!show) return;
            var dots = $('<span class="ai-dots"></span>').append('<span></span><span></span><span></span>');
            var b = $('<div class="b"></div>').append(document.createTextNode('处理中 ')).append(dots);
            $('#chatBox').append($('<div class="ai-msg bot thinking"></div>')
                .append($('<div class="m"></div>').text(asName))
                .append(b));
            scrollChat();
        }

        function setBusy(v) {
            busy = v;
            $('#btnSend,#userInput,.sug,#btnNew,#btnRename,#btnDelete,#aiSessionSearch').prop('disabled', !on || busy);
            $('#btnStop').toggle(!!busy);
            if (!on) return;
            if (!busy) {
                $('#btnNew,#btnRename,#btnDelete,.sug,#aiSessionSearch').prop('disabled', false);
                $('#btnRename,#btnDelete').prop('disabled', !sid);
            }
            $('#btnSend').toggleClass('is-busy', !!busy);
            $('#btnSend i').attr('class', busy ? 'fa fa-circle-o-notch fa-spin' : 'fa fa-paper-plane');
            $('#btnSend span').text(busy ? '处理中' : '发送');
        }

        function renderSessions(list) {
            sessionCache = list || [];
            var kw = $.trim($('#aiSessionSearch').val() || '').toLowerCase();
            var filtered = sessionCache;
            if (kw) {
                filtered = sessionCache.filter(function (s) {
                    return String(s.title || '').toLowerCase().indexOf(kw) >= 0 || String(s.id).indexOf(kw) >= 0;
                });
            }
            var box = $('#sessionList').empty();
            if (!filtered.length) {
                return box.html('<div class="ai-empty">' + (kw ? '没有匹配的对话' : '暂无历史对话<br>点「新建」开始提问') + '</div>');
            }
            filtered.forEach(function (s) {
                var item = $('<div class="ai-item"></div>').toggleClass('on', Number(s.id) === sid);
                item.append($('<div class="t"></div>').text(s.title || ('对话#' + s.id)));
                item.append($('<div class="s"></div>').text(relTime(s.updatetime) + ' · ' + (s.msg_count || 0) + '条'));
                item.on('click', function () { loadSession(s.id); closeMobileSessions(); });
                box.append(item);
            });
        }

        function renderLogs(logs, prepend) {
            if (!showLogs || !$('#logList').length) return;
            var box = $('#logList');
            if (!prepend) box.empty();
            if ((!logs || !logs.length) && !prepend) return box.html('<div class="ai-empty">本轮操作会出现在这里</div>');
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
            $.getJSON(endpoint + '?act=log_get&id=' + id, function (res) {
                if (res.code !== 0) return tip(res.msg || '读取失败');
                var d = res.data, a = d.arguments || '', r = d.result || '';
                try { a = JSON.stringify(JSON.parse(a), null, 2); } catch (e) {}
                try { r = JSON.stringify(JSON.parse(r), null, 2); } catch (e) {}
                var html = '<p>时间：' + (d.addtime || '') + '　耗时：' + (d.duration_ms || 0) + 'ms</p>'
                    + '<p>请求号：' + (d.request_id || '-') + '　IP：' + (d.ip || '-') + '</p>'
                    + '<p><b>参数</b></p><pre style="max-height:200px;overflow:auto;background:#f7f7f7;padding:8px">' + $('<div>').text(a).html() + '</pre>'
                    + '<p><b>结果</b></p><pre style="max-height:200px;overflow:auto;background:#f7f7f7;padding:8px">' + $('<div>').text(r).html() + '</pre>';
                if (window.layer) layer.open({ type: 1, title: (d.tool_label || d.tool_name), area: ['640px', '480px'], content: '<div style="padding:12px">' + html + '</div>' });
                else tip('查看失败');
            });
        }

        function refreshSessions() {
            $.getJSON(endpoint + '?act=sessions', function (res) {
                if (res.code === 0) renderSessions(res.data || []);
            });
        }

        function titleBar(title) {
            $('#chatTitle').text(title || (sid ? ('对话#' + sid) : '新对话'));
            $('#btnRename,#btnDelete').toggle(!!sid);
        }

        function stopRequest() {
            if (currentXhr) {
                try { currentXhr.abort(); } catch (e) {}
                currentXhr = null;
            }
            thinking(false);
            setBusy(false);
            $('#chatStatus').text('已取消');
            addMsg('assistant', '已取消本次请求。', null, true);
        }

        function loadSession(id, opts) {
            if (busy) return tip('请先等待当前回复结束，或点「停止」');
            opts = opts || {};
            sid = Number(id) || 0;
            writeSid(sid);
            lastFailed = '';
            if (!sid) {
                titleBar('新对话');
                $('#chatBox').empty();
                addMsg('assistant', welcomeText());
                setSugsVisible(true);
                renderLogs([]);
                refreshSessions();
                if (!opts.silent) $('#chatStatus').text('新对话');
                return;
            }
            $.getJSON(endpoint + '?act=session_get&id=' + sid, function (res) {
                if (res.code !== 0) {
                    writeSid(0);
                    if (opts.restore) return loadSession(0, { silent: true });
                    return tip(res.msg || '加载失败');
                }
                titleBar(res.session.title || ('对话#' + sid));
                $('#chatBox').empty();
                var msgs = res.messages || [];
                if (!msgs.length) {
                    addMsg('assistant', welcomeText());
                    setSugsVisible(true);
                } else {
                    msgs.forEach(function (m) {
                        addMsg(m.role, m.content, m.tool_trace || [], String(m.content || '').indexOf('错误') === 0);
                    });
                    setSugsVisible(false);
                }
                renderLogs(res.logs || []);
                refreshSessions();
                if (opts.restore) $('#chatStatus').text('已恢复上次对话');
            }).fail(function () {
                if (opts.restore) loadSession(0, { silent: true });
            });
        }

        function send(text) {
            text = $.trim(text || '');
            if (!text || busy || !on) return;
            if (text.length > maxLen) {
                tip('内容过长，请控制在 ' + maxLen + ' 字以内');
                return;
            }
            lastFailed = text;
            setBusy(true);
            setSugsVisible(false);
            addMsg('user', text);
            $('#userInput').val('');
            saveDraft('');
            updateCharCount();
            thinking(true);
            $('#chatStatus').text('处理中…');
            currentXhr = $.ajax({
                url: endpoint + '?act=chat',
                method: 'POST',
                contentType: 'application/json; charset=UTF-8',
                dataType: 'json',
                data: JSON.stringify({ message: text, session_id: sid || 0 }),
                timeout: 180000,
                success: function (res) {
                    thinking(false);
                    if (res.site) applySite(res.site);
                    if (res.session_id) {
                        sid = res.session_id;
                        writeSid(sid);
                        var cur = $('#chatTitle').text();
                        titleBar((!cur || cur === '新对话') ? text.slice(0, 30) : cur);
                    }
                    if (res.code === 0) {
                        lastFailed = '';
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
                        addMsg('assistant', res.msg || '失败', null, true, { retryText: text });
                        $('#chatStatus').text('失败');
                        if (res.session_id) refreshSessions();
                    }
                },
                error: function (xhr, status) {
                    thinking(false);
                    if (status === 'abort') return;
                    var msg = '网络错误，请稍后重试';
                    if (status === 'timeout') msg = '请求超时，请重试';
                    else if (xhr.status === 0) msg = '网络中断，请检查网络后重试';
                    else if (xhr.status) msg = '请求失败 HTTP ' + xhr.status;
                    addMsg('assistant', msg, null, true, { retryText: text });
                    $('#chatStatus').text('失败');
                },
                complete: function () {
                    currentXhr = null;
                    setBusy(false);
                    $('#userInput').focus();
                }
            });
        }

        $('#btnNew').on('click', function () { loadSession(0); });
        $('#btnSessions').on('click', function () {
            var $w = $('.ai-wrap');
            $w.toggleClass('ai-sessions-open');
            $('body').toggleClass('ai-drawer-lock', $w.hasClass('ai-sessions-open'));
        });
        $('.ai-wrap').on('click', function (e) {
            if (!$(this).hasClass('ai-sessions-open')) return;
            if ($(e.target).closest('.ai-sidebar-col').length) return;
            closeMobileSessions();
        });
        $(document).on('keydown', function (e) {
            if (e.keyCode === 27) closeMobileSessions();
        });
        $(window).on('resize', function () {
            if (window.innerWidth > 767) closeMobileSessions();
        });
        $('#btnStop').on('click', function () { stopRequest(); });
        $('#btnRename').on('click', function () {
            if (!sid) return;
            var t = prompt('对话标题', $('#chatTitle').text());
            if (t == null || !$.trim(t)) return;
            $.post(endpoint + '?act=session_rename', { id: sid, title: $.trim(t) }, function (res) {
                if (typeof res === 'string') try { res = JSON.parse(res); } catch (e) {}
                if (res.code === 0) { titleBar($.trim(t)); refreshSessions(); }
                else tip(res.msg || '失败');
            });
        });
        $('#btnDelete').on('click', function () {
            if (!sid) return;
            var title = $('#chatTitle').text() || '该对话';
            if (!confirm('确定删除「' + title + '」？')) return;
            var delId = sid;
            $.post(endpoint + '?act=session_delete', { id: delId }, function () {
                if (readSid() === delId) writeSid(0);
                loadSession(0);
                refreshSessions();
            });
        });
        $('#btnRefreshLogs').on('click', function () {
            if (!sid) return renderLogs([]);
            $.getJSON(endpoint + '?act=logs&session_id=' + sid + '&limit=100', function (res) {
                if (res.code === 0) renderLogs(res.data || []);
            });
        });
        $('#btnSend').on('click', function () { send($('#userInput').val()); });
        $('#btnRetry').on('click', function () { if (lastFailed) send(lastFailed); });
        $('#userInput').on('keydown', function (e) {
            if ((e.keyCode === 13 && !e.shiftKey) || (e.keyCode === 13 && e.ctrlKey)) {
                e.preventDefault();
                send($(this).val());
            }
        }).on('input', function () {
            saveDraft($(this).val());
            updateCharCount();
            this.style.height = 'auto';
            this.style.height = Math.min(140, Math.max(48, this.scrollHeight)) + 'px';
        }).on('focus', function () {
            setTimeout(scrollChat, 250);
        });
        $('#aiSessionSearch').on('input', function () { renderSessions(sessionCache); });
        $('.sug').on('click', function () { send($(this).text()); });

        applySite({ sitename: site, assistant_name: asName });
        var draft = loadDraft();
        if (draft) {
            $('#userInput').val(draft);
            updateCharCount();
        }
        $('#btnStop').hide();
        if (!on) {
            $('#chatBox').empty();
            addMsg('assistant', 'AI 未启用，请先完成模型配置。', null, true);
            setBusy(true);
            refreshSessions();
            return;
        }
        var last = readSid();
        if (last > 0) loadSession(last, { restore: true });
        else loadSession(0, { silent: true });
        $('#userInput').focus();
    }

    function waitJQ(n) {
        if (window.jQuery) return boot(window.jQuery);
        if (n > 50) {
            if (window.console) console.error('ai-chat.js: jQuery not found');
            return;
        }
        setTimeout(function () { waitJQ(n + 1); }, 50);
    }
    waitJQ(0);
})();
