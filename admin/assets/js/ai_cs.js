/**
 * 客服中心：左列表 + 右聊天台（短轮询）
 */
(function () {
    var statusMap = { 0: '待处理', 1: '进行中', 2: '已完结' };
    var curId = 0;
    var lastMsgId = 0;
    var pollTimer = null;
    var seen = {};

    function esc(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }

    function stopPoll() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    function startPoll() {
        stopPoll();
        if (!curId) return;
        pollTimer = setInterval(function () {
            if (!curId) return;
            loadMessages(true);
        }, 2500);
    }

    function loadList() {
        var q = {
            act: 'cs_list',
            status: $('#csStatus').val(),
            keyword: $('#csKeyword').val(),
            limit: 80
        };
        $.getJSON('ajax_ai.php', q, function (res) {
            var bd = $('#csListBd');
            if (!res || res.code !== 0) {
                bd.html('<div class="cs-empty text-danger">' + esc((res && res.msg) || '加载失败') + '</div>');
                return;
            }
            var rows = res.data || [];
            if (!rows.length) {
                bd.html('<div class="cs-empty">暂无会话</div>');
                return;
            }
            var html = '';
            rows.forEach(function (r) {
                var st = Number(r.status);
                var preview = r.reply || r.problem || '';
                html += '<div class="cs-item' + (Number(r.id) === curId ? ' active' : '') + '" data-id="' + r.id + '">'
                    + '<div class="t">#' + r.id + ' · ' + esc(r.contact || '访客') + '</div>'
                    + '<div class="s">' + esc(preview) + '</div>'
                    + '<div class="m">' + esc(r.updatetime || r.addtime) + ' · ' + (statusMap[st] || st)
                    + (Number(r.staff_joined) ? ' · 已接入' : '') + '</div>'
                    + '</div>';
            });
            bd.html(html);
        });
    }

    function setComposer(on) {
        $('#csReplyText,#csSendBtn,#csCloseBtn,#csRefreshMsg,#csFile').prop('disabled', !on);
    }

    function appendMsg(m, silent) {
        if (!m || !m.id) return;
        if (seen[m.id]) return;
        seen[m.id] = 1;
        if (m.id > lastMsgId) lastMsgId = m.id;
        var role = m.role || 'user';
        var lab = role === 'user' ? '访客' : (role === 'staff' ? '客服' : (role === 'ai' ? 'AI' : '系统'));
        var html = '<div class="cs-bubble ' + esc(role) + '" data-id="' + m.id + '">';
        if (role !== 'system') html += '<div class="lab">' + lab + '</div>';
        if (m.msg_type === 'image' && m.media_url) {
            html += '<a href="' + esc(m.media_url) + '" target="_blank" rel="noopener"><img src="' + esc(m.media_url) + '" alt="img"></a>';
            if (m.content && m.content !== '[图片]') html += '<div>' + esc(m.content) + '</div>';
        } else {
            html += esc(m.content || '');
        }
        html += '</div>';
        var bd = $('#csChatBd');
        if (bd.find('.cs-empty').length) bd.empty();
        bd.append(html);
        if (!silent) bd.scrollTop(bd[0].scrollHeight);
    }

    function loadMessages(fromPoll) {
        if (!curId) return;
        var after = fromPoll ? lastMsgId : 0;
        $.getJSON('ajax_ai.php', { act: 'cs_messages', id: curId, after_id: after }, function (res) {
            if (!res || res.code !== 0) {
                if (!fromPoll) layer.msg((res && res.msg) || '加载失败');
                return;
            }
            var d = res.data || {};
            if (!fromPoll) {
                seen = {};
                lastMsgId = 0;
                $('#csChatBd').html('');
                $('#csChatTitle').text('会话 #' + d.id + ' · ' + (d.contact || '访客'));
                $('#csChatMeta').text((statusMap[Number(d.status)] || '') + (d.order_no ? (' · 订单 ' + d.order_no) : '') + (Number(d.ai_paused) ? ' · AI已暂停' : ''));
                setComposer(Number(d.status) !== 2);
            }
            var list = res.messages || [];
            list.forEach(function (m) { appendMsg(m, fromPoll && list.length > 3); });
            if (!fromPoll || list.length) {
                var bd = $('#csChatBd')[0];
                if (bd) bd.scrollTop = bd.scrollHeight;
            }
            if (Number(d.status) === 2) setComposer(false);
        });
    }

    function openChat(id) {
        curId = Number(id) || 0;
        $('.cs-item').removeClass('active');
        $('.cs-item[data-id="' + curId + '"]').addClass('active');
        loadMessages(false);
        startPoll();
        $('#csReplyText').focus();
    }

    function sendText() {
        if (!curId) return;
        var text = $.trim($('#csReplyText').val() || '');
        if (!text) return layer.msg('请输入内容');
        var close = $('#csCloseAfter').is(':checked') ? 1 : 0;
        $('#csSendTip').text('发送中…');
        $.post('ajax_ai.php?act=cs_send_msg', { id: curId, message: text, close: close }, function (r) {
            if (typeof r === 'string') try { r = JSON.parse(r); } catch (e) { r = {}; }
            $('#csSendTip').text('');
            if (r.code !== 0) return layer.msg(r.msg || '失败');
            $('#csReplyText').val('');
            (r.messages || []).forEach(function (m) { appendMsg(m); });
            loadList();
            if (close) {
                setComposer(false);
                $('#csChatMeta').text('已完结');
            }
        });
    }

    function uploadImg() {
        var f = $('#csFile')[0];
        if (!curId || !f.files || !f.files[0]) return;
        var fd = new FormData();
        fd.append('file', f.files[0]);
        fd.append('cs_id', String(curId));
        $('#csSendTip').text('上传中…');
        $.ajax({
            url: 'ajax_ai.php?act=cs_upload',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function (r) {
                if (typeof r === 'string') try { r = JSON.parse(r); } catch (e) { r = {}; }
                $('#csSendTip').text('');
                f.value = '';
                if (r.code !== 0) return layer.msg(r.msg || '上传失败');
                if (r.msg_id) {
                    appendMsg({
                        id: r.msg_id,
                        role: 'staff',
                        msg_type: 'image',
                        media_url: r.url,
                        content: '[图片]'
                    });
                }
                loadList();
            },
            error: function () {
                $('#csSendTip').text('');
                layer.msg('上传失败');
            }
        });
    }

    $('.cs-filter').on('click', function () {
        $('#csStatus').val($(this).data('status'));
        loadList();
    });
    $('#csSearch').on('click', loadList);
    $('#csFilter').on('keydown', function (e) {
        if (e.keyCode === 13) { e.preventDefault(); loadList(); }
    });
    $(document).on('click', '.cs-item', function () {
        openChat($(this).data('id'));
    });
    $('#csSendBtn').on('click', sendText);
    $('#csRefreshMsg').on('click', function () { loadMessages(false); });
    $('#csCloseBtn').on('click', function () {
        if (!curId) return;
        layer.confirm('确认完结此会话？', function (idx) {
            $.post('ajax_ai.php?act=cs_close', { id: curId }, function (r) {
                if (typeof r === 'string') try { r = JSON.parse(r); } catch (e) { r = {}; }
                layer.close(idx);
                if (r.code !== 0) return layer.msg(r.msg || '失败');
                layer.msg('已完结');
                setComposer(false);
                loadList();
                loadMessages(false);
            });
        });
    });
    $('#csFile').on('change', uploadImg);
    $('#csReplyText').on('keydown', function (e) {
        if (e.keyCode === 13 && !e.shiftKey) {
            e.preventDefault();
            sendText();
        }
    });

    loadList();
})();
