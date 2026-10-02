/**
 * 前台 AI 客服浮窗
 * - AI 对话 + 微信式人工会话（同窗气泡、图片、短轮询）
 */
(function () {
    if (window.__AI_WIDGET_LOADED) return;
    window.__AI_WIDGET_LOADED = true;

    var LS_KEY = 'ai_shop_sid';
    var CS_KEY = 'ai_shop_csid';
    var DRAFT_KEY = 'ai_shop_draft';
    var MAX_LEN = 800;

    function qs(sel, el) { return (el || document).querySelector(sel); }
    function endpoint() {
        return (window.AI_WIDGET && AI_WIDGET.endpoint) ? AI_WIDGET.endpoint : 'ajax_ai.php';
    }
    function optionText(el) {
        if (!el || el.tagName !== 'SELECT') return '';
        var opt = el.options[el.selectedIndex];
        return opt ? String(opt.text || '').replace(/^\s+|\s+$/g, '') : '';
    }
    function getContext() {
        var tid = 0, cid = 0, tname = '', cname = '';
        try {
            var tEl = document.getElementById('tid') || qs('select[name=tid],#tid');
            var cEl = document.getElementById('cid') || qs('select[name=cid],#cid');
            if (tEl && tEl.value) { tid = parseInt(tEl.value, 10) || 0; tname = optionText(tEl); }
            if (cEl && cEl.value) { cid = parseInt(cEl.value, 10) || 0; cname = optionText(cEl); }
            if (!tid && /[?&]tid=(\d+)/.test(location.search)) tid = parseInt(RegExp.$1, 10);
            if (!cid && /[?&]cid=(\d+)/.test(location.search)) cid = parseInt(RegExp.$1, 10);
        } catch (e) {}
        return { tid: tid, cid: cid, tname: tname, cname: cname };
    }
    function clean(t) {
        return String(t == null ? '' : t).replace(/\*\*?/g, '').replace(/`+/g, '').replace(/^#{1,6}\s*/gm, '').trim();
    }
    function readSid() {
        try { return parseInt(localStorage.getItem(LS_KEY) || '0', 10) || 0; } catch (e) { return 0; }
    }
    function writeSid(id) {
        try {
            if (id > 0) localStorage.setItem(LS_KEY, String(id));
            else localStorage.removeItem(LS_KEY);
        } catch (e) {}
    }
    function readCsId() {
        try { return parseInt(localStorage.getItem(CS_KEY) || '0', 10) || 0; } catch (e) { return 0; }
    }
    function writeCsId(id) {
        try {
            if (id > 0) localStorage.setItem(CS_KEY, String(id));
            else localStorage.removeItem(CS_KEY);
        } catch (e) {}
    }
    function saveDraft(v) {
        try {
            if (v) localStorage.setItem(DRAFT_KEY, v);
            else localStorage.removeItem(DRAFT_KEY);
        } catch (e) {}
    }
    function loadDraft() {
        try { return localStorage.getItem(DRAFT_KEY) || ''; } catch (e) { return ''; }
    }
    function ensureCss() {
        if (qs('#ai-widget-css')) return;
        var link = document.createElement('link');
        link.id = 'ai-widget-css';
        link.rel = 'stylesheet';
        link.href = (window.AI_WIDGET && AI_WIDGET.css) ? AI_WIDGET.css : 'assets/css/ai-widget.css';
        document.head.appendChild(link);
    }
    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function hasBottomNav() {
        return !!(
            qs('.fui-navbar') || qs('.footerBox') || qs('.dibucss') ||
            qs('.tab-bar') || qs('.van-tabbar') || qs('#footer') ||
            qs('.wap-footer') || qs('.bottom-nav')
        );
    }
    function isMobileView() {
        return window.matchMedia ? window.matchMedia('(max-width:480px)').matches : (window.innerWidth <= 480);
    }

    function boot(cfg) {
        if (!cfg || !cfg.on) return;
        ensureCss();
        var asName = cfg.assistant || '助手';
        var sid = readSid();
        var csId = readCsId();
        var csMode = csId > 0;
        var csLastId = 0;
        var pollTimer = null;
        var busy = false;
        var open = false;
        var historyLoaded = false;
        var unread = false;
        var lastFailed = '';
        var currentXhr = null;
        var handoffOpen = false;
        var seenMsg = {};

        var root = document.createElement('div');
        root.id = 'ai-widget';
        if (hasBottomNav()) root.className = 'has-bottom-nav';
        root.innerHTML =
            '<div class="aiw-mask" id="aiwMask" aria-hidden="true"></div>' +
            '<button type="button" class="aiw-fab" id="aiwFab" aria-label="' + esc(asName) + '">' +
            '  <span class="aiw-fab-text">' + esc(asName) + '</span>' +
            '  <span class="aiw-badge" id="aiwBadge" style="display:none">1</span>' +
            '</button>' +
            '<div class="aiw-panel" id="aiwPanel" style="display:none" role="dialog" aria-modal="true" aria-label="AI客服">' +
            '  <div class="aiw-head">' +
            '    <div class="aiw-head-title"><strong id="aiwTitle">' + esc(asName) + '</strong><small id="aiwSub">在线客服</small></div>' +
            '    <span class="aiw-head-actions">' +
            '      <button type="button" class="aiw-new" id="aiwNew">新对话</button>' +
            '      <button type="button" class="aiw-close" id="aiwClose" aria-label="关闭">×</button>' +
            '    </span>' +
            '  </div>' +
            '  <div class="aiw-ctx" id="aiwCtx" style="display:none"></div>' +
            '  <div class="aiw-sugs" id="aiwSugs"></div>' +
            '  <div class="aiw-box" id="aiwBox"></div>' +
            '  <div class="aiw-links" id="aiwLinks"></div>' +
            '  <div class="aiw-handoff" id="aiwHandoff" style="display:none">' +
            '    <div class="aiw-handoff-title">进入人工客服</div>' +
            '    <input type="text" id="aiwContact" maxlength="60" placeholder="联系方式（选填：QQ/微信/手机）" autocomplete="tel">' +
            '    <input type="text" id="aiwOrder" maxlength="40" placeholder="订单号（可选）">' +
            '    <textarea id="aiwProblem" rows="2" maxlength="400" placeholder="一句话说明问题（可选）"></textarea>' +
            '    <div class="aiw-handoff-btns">' +
            '      <button type="button" class="aiw-h-cancel" id="aiwHCancel">取消</button>' +
            '      <button type="button" class="aiw-h-ok" id="aiwHOk">开始会话</button>' +
            '    </div>' +
            '  </div>' +
            '  <div class="aiw-input">' +
            '    <label class="aiw-img-btn" id="aiwImgBtn" title="发图片" style="display:none">' +
            '      <input type="file" id="aiwFile" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none">' +
            '      <span>图</span>' +
            '    </label>' +
            '    <textarea id="aiwText" rows="1" maxlength="' + MAX_LEN + '" placeholder="问问' + esc(asName) + '…" enterkeyhint="send"></textarea>' +
            '    <button type="button" class="aiw-stop" id="aiwStop" style="display:none">停止</button>' +
            '    <button type="button" id="aiwSend">发送</button>' +
            '  </div>' +
            '  <div class="aiw-foot">' +
            '    <span class="aiw-meta" id="aiwMeta"></span>' +
            '    <span class="aiw-foot-links">' +
            (cfg.kfqq ? ('<a href="https://wpa.qq.com/msgrd?v=3&uin=' + esc(cfg.kfqq) + '&site=qq&menu=yes" target="_blank" rel="noopener">QQ客服</a> · ') : '') +
            '      <a href="javascript:;" id="aiwHuman">转人工</a>' +
            '    </span>' +
            '  </div>' +
            '</div>';
        document.body.appendChild(root);

        var box = qs('#aiwBox', root);
        var linksEl = qs('#aiwLinks', root);
        var ctxEl = qs('#aiwCtx', root);
        var sugsEl = qs('#aiwSugs', root);
        var badge = qs('#aiwBadge', root);
        var meta = qs('#aiwMeta', root);
        var textEl = qs('#aiwText', root);
        var sendBtn = qs('#aiwSend', root);
        var stopBtn = qs('#aiwStop', root);
        var handoffEl = qs('#aiwHandoff', root);
        var imgBtn = qs('#aiwImgBtn', root);
        var fileEl = qs('#aiwFile', root);
        var titleEl = qs('#aiwTitle', root);
        var subEl = qs('#aiwSub', root);

        function setUnread(v) {
            unread = !!v;
            badge.style.display = unread && !open ? 'inline-block' : 'none';
        }
        function setCsMode(on, ticket) {
            csMode = !!on;
            imgBtn.style.display = csMode ? 'inline-flex' : 'none';
            if (csMode) {
                titleEl.textContent = '在线客服';
                subEl.textContent = (ticket && ticket.staff_joined) ? '人工已接入' : '助手+人工协作';
                textEl.placeholder = '发消息给客服…';
                sugsEl.style.display = 'none';
                startPoll();
            } else {
                titleEl.textContent = asName;
                subEl.textContent = '在线客服';
                textEl.placeholder = '问问' + asName + '…';
                sugsEl.style.display = '';
                stopPoll();
            }
        }
        function roleClass(role) {
            if (role === 'user') return 'user';
            if (role === 'staff') return 'staff';
            if (role === 'ai') return 'ai';
            if (role === 'system') return 'system';
            return 'bot';
        }
        function roleLabel(role) {
            if (role === 'staff') return '客服';
            if (role === 'ai') return asName;
            if (role === 'system') return '系统';
            return '';
        }
        function addBubble(role, text, opts) {
            opts = opts || {};
            var key = opts.id ? ('m' + opts.id) : '';
            if (key && seenMsg[key]) return;
            if (key) seenMsg[key] = 1;
            if (opts.id && opts.id > csLastId) csLastId = opts.id;

            var div = document.createElement('div');
            div.className = 'aiw-msg ' + roleClass(role) + (opts.err ? ' err' : '');
            if (opts.id) div.setAttribute('data-id', String(opts.id));
            var label = roleLabel(role);
            if (label && role !== 'user') {
                var lab = document.createElement('div');
                lab.className = 'aiw-msg-lab';
                lab.textContent = label;
                div.appendChild(lab);
            }
            if (opts.msgType === 'image' && opts.mediaUrl) {
                var a = document.createElement('a');
                a.href = opts.mediaUrl;
                a.target = '_blank';
                a.rel = 'noopener';
                a.className = 'aiw-img-wrap';
                var img = document.createElement('img');
                img.src = opts.mediaUrl;
                img.alt = '图片';
                img.className = 'aiw-img';
                a.appendChild(img);
                div.appendChild(a);
                if (text && text !== '[图片]') {
                    var cap = document.createElement('div');
                    cap.className = 'aiw-img-cap';
                    cap.textContent = clean(text);
                    div.appendChild(cap);
                }
            } else {
                var body = document.createElement('div');
                body.className = 'aiw-msg-body';
                body.textContent = role === 'user' ? text : clean(text);
                div.appendChild(body);
            }
            box.appendChild(div);
            if (opts.err && opts.retryText) {
                var row = document.createElement('div');
                row.className = 'aiw-retry';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = '重试';
                btn.onclick = function () { send(opts.retryText); };
                row.appendChild(btn);
                box.appendChild(row);
            }
            if (opts.needHuman) {
                var tip = document.createElement('div');
                tip.className = 'aiw-need-human';
                var hb = document.createElement('button');
                hb.type = 'button';
                hb.textContent = '转人工客服';
                hb.onclick = function () { showHandoff(true); };
                tip.appendChild(hb);
                box.appendChild(tip);
            }
            box.scrollTop = box.scrollHeight;
        }
        function addMsg(role, text, opts) {
            addBubble(role === 'bot' ? 'ai' : role, text, opts);
        }
        function appendCsMessages(list, fromPoll) {
            if (!list || !list.length) return;
            list.forEach(function (m) {
                var wasNew = m.id && !seenMsg['m' + m.id];
                addBubble(m.role || 'user', m.content || '', {
                    id: m.id,
                    msgType: m.msg_type,
                    mediaUrl: m.media_url
                });
                if (fromPoll && wasNew && m.role !== 'user' && !open) setUnread(true);
            });
        }
        function setLinks(list) {
            linksEl.innerHTML = '';
            if (!list || !list.length || csMode) return;
            var seen = {};
            var wrap = document.createElement('div');
            wrap.className = 'aiw-cards';
            list.slice(0, 8).forEach(function (g) {
                if (!g || !g.tid || seen[g.tid]) return;
                seen[g.tid] = 1;
                var a = document.createElement('a');
                a.className = 'aiw-card';
                a.href = g.link || ('?cid=' + (g.cid || '') + '&tid=' + g.tid);
                var title = document.createElement('div');
                title.className = 'aiw-card-name';
                title.textContent = g.name || ('商品' + g.tid);
                var metaLine = document.createElement('div');
                metaLine.className = 'aiw-card-meta';
                metaLine.textContent = (g.price != null && g.price !== '' ? ('¥' + g.price) : '') +
                    (g.reason ? (' · ' + String(g.reason).replace(/^[^·]*·\s*/, '').slice(0, 24)) : '');
                if (!metaLine.textContent.replace(/\s/g, '')) metaLine.textContent = g.reason || '推荐';
                a.appendChild(title);
                a.appendChild(metaLine);
                wrap.appendChild(a);
            });
            linksEl.appendChild(wrap);
        }
        function showWelcome() {
            box.innerHTML = '';
            seenMsg = {};
            addMsg('bot', cfg.welcome || ('你好，我是' + asName));
        }
        function showHandoff(v) {
            handoffOpen = !!v;
            handoffEl.style.display = handoffOpen ? 'block' : 'none';
            if (handoffOpen) {
                var p = qs('#aiwProblem', root);
                if (p && !p.value && lastFailed) p.value = lastFailed.slice(0, 200);
                qs('#aiwContact', root).focus();
            }
        }
        function stopPoll() {
            if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        }
        function startPoll() {
            stopPoll();
            if (!csMode || !csId) return;
            pollTimer = setInterval(function () {
                if (!csMode || !csId) return;
                pollCs();
            }, 2500);
        }
        function pollCs() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', endpoint() + '?act=cs_poll&cs_id=' + csId + '&after_id=' + csLastId, true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                var res = null;
                try { res = JSON.parse(xhr.responseText); } catch (e) {}
                if (!res || res.code !== 0) {
                    if (res && res.msg && /不存在|完结/.test(res.msg)) {
                        exitCsMode();
                    }
                    return;
                }
                appendCsMessages(res.messages || [], true);
                if (res.ticket) {
                    if (Number(res.ticket.status) === 2) {
                        meta.textContent = '会话已完结';
                        setCsMode(true, res.ticket);
                    } else {
                        setCsMode(true, res.ticket);
                    }
                }
            };
            xhr.send();
        }
        function enterCs(res) {
            csId = res.cs_id || (res.ticket && res.ticket.id) || 0;
            writeCsId(csId);
            csLastId = 0;
            seenMsg = {};
            box.innerHTML = '';
            setLinks([]);
            showHandoff(false);
            setCsMode(true, res.ticket || {});
            appendCsMessages(res.messages || [], false);
            meta.textContent = '客服会话 #' + csId;
            textEl.focus();
        }
        function exitCsMode() {
            stopPoll();
            csId = 0;
            writeCsId(0);
            csLastId = 0;
            setCsMode(false);
            meta.textContent = '';
        }
        function openCsSession() {
            if (busy) return;
            var contact = (qs('#aiwContact', root).value || '').replace(/^\s+|\s+$/g, '');
            var problem = (qs('#aiwProblem', root).value || '').replace(/^\s+|\s+$/g, '');
            var orderNo = (qs('#aiwOrder', root).value || '').replace(/^\s+|\s+$/g, '');
            setBusy(true);
            meta.textContent = '接入中…';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', endpoint() + '?act=cs_open', true);
            xhr.setRequestHeader('Content-Type', 'application/json; charset=UTF-8');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                setBusy(false);
                var res = null;
                try { res = JSON.parse(xhr.responseText); } catch (e) {}
                if (!res || res.code !== 0) {
                    meta.textContent = (res && res.msg) ? res.msg : '接入失败';
                    return;
                }
                qs('#aiwContact', root).value = '';
                qs('#aiwProblem', root).value = '';
                qs('#aiwOrder', root).value = '';
                enterCs(res);
            };
            xhr.send(JSON.stringify({
                contact: contact,
                problem: problem || '进入在线客服',
                order_no: orderNo,
                session_id: sid || 0
            }));
        }
        function submitHandoff() { openCsSession(); }
        function resumeCsIfAny(done) {
            if (!csId) {
                if (done) done(false);
                return;
            }
            var xhr = new XMLHttpRequest();
            xhr.open('GET', endpoint() + '?act=cs_poll&cs_id=' + csId + '&after_id=0', true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                var res = null;
                try { res = JSON.parse(xhr.responseText); } catch (e) {}
                if (!res || res.code !== 0) {
                    exitCsMode();
                    if (done) done(false);
                    return;
                }
                csLastId = 0;
                seenMsg = {};
                box.innerHTML = '';
                setCsMode(true, res.ticket || {});
                appendCsMessages(res.messages || [], false);
                meta.textContent = '客服会话 #' + csId;
                if (done) done(true);
            };
            xhr.send();
        }
        function uploadImage(file) {
            if (!csMode || !csId || !file || busy) return;
            if (file.size > 5 * 1024 * 1024) {
                meta.textContent = '图片不能超过 5MB';
                return;
            }
            setBusy(true);
            meta.textContent = '上传中…';
            var fd = new FormData();
            fd.append('file', file);
            fd.append('cs_id', String(csId));
            var xhr = new XMLHttpRequest();
            xhr.open('POST', endpoint() + '?act=cs_upload', true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                setBusy(false);
                var res = null;
                try { res = JSON.parse(xhr.responseText); } catch (e) {}
                if (!res || res.code !== 0) {
                    meta.textContent = (res && res.msg) ? res.msg : '上传失败';
                    return;
                }
                if (res.msg_id) {
                    addBubble('user', '[图片]', { id: res.msg_id, msgType: 'image', mediaUrl: res.url });
                } else if (res.url) {
                    // 上传成功但未绑消息时补发
                    sendCsImage(res.url);
                }
                meta.textContent = '';
                fileEl.value = '';
            };
            xhr.send(fd);
        }
        function sendCsImage(url) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', endpoint() + '?act=cs_send', true);
            xhr.setRequestHeader('Content-Type', 'application/json; charset=UTF-8');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                var res = null;
                try { res = JSON.parse(xhr.responseText); } catch (e) {}
                if (res && res.code === 0) appendCsMessages(res.messages || [], false);
            };
            xhr.send(JSON.stringify({ cs_id: csId, msg_type: 'image', media_url: url, message: '[图片]' }));
        }
        function sendCs(text) {
            setBusy(true);
            // 先本地展示，稍后用服务端 id 去重合并
            addBubble('user', text, { pending: true });
            textEl.value = '';
            saveDraft('');
            meta.textContent = '';
            var xhr = new XMLHttpRequest();
            currentXhr = xhr;
            xhr.open('POST', endpoint() + '?act=cs_send', true);
            xhr.setRequestHeader('Content-Type', 'application/json; charset=UTF-8');
            xhr.timeout = 90000;
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                if (currentXhr === xhr) currentXhr = null;
                setBusy(false);
                var res = null;
                try { res = JSON.parse(xhr.responseText); } catch (e) {}
                if (!res || res.code !== 0) {
                    addMsg('bot', (res && res.msg) || '发送失败', { err: true, retryText: text });
                    return;
                }
                // 去掉无 id 的 pending 用户气泡，再用服务端消息渲染
                var pend = box.querySelectorAll('.aiw-msg.user:not([data-id])');
                for (var i = 0; i < pend.length; i++) {
                    if (pend[i].parentNode) pend[i].parentNode.removeChild(pend[i]);
                }
                appendCsMessages(res.messages || [], false);
                if (res.ticket) setCsMode(true, res.ticket);
            };
            xhr.ontimeout = function () {
                if (currentXhr === xhr) currentXhr = null;
                setBusy(false);
                addMsg('bot', '发送超时', { err: true, retryText: text });
            };
            xhr.send(JSON.stringify({ cs_id: csId, message: text, msg_type: 'text' }));
        }
        function refreshContext() {
            var ctx = getContext();
            if (ctx.tid > 0 || ctx.cid > 0) {
                var label = ctx.tname ? ('正在看：' + ctx.tname) : (ctx.tid ? ('商品 #' + ctx.tid) : '');
                if (!label && ctx.cname) label = '分类：' + ctx.cname;
                if (!label && ctx.cid) label = '分类 #' + ctx.cid;
                ctxEl.style.display = 'block';
                ctxEl.textContent = label;
            } else {
                ctxEl.style.display = 'none';
                ctxEl.textContent = '';
            }
            if (!csMode) renderChips(ctx);
            return ctx;
        }
        function renderChips(ctx) {
            var chips = [];
            if (ctx.tid > 0) {
                chips.push({ q: '解释一下当前商品', t: '解释此商品' });
                chips.push({ q: '这个商品下单信息怎么填', t: '怎么填' });
                chips.push({ q: '推荐几个同类商品', t: '同类推荐' });
            } else {
                chips.push({ q: '有什么热门商品推荐', t: '热门推荐' });
                chips.push({ q: '有哪些商品分类', t: '看分类' });
            }
            chips.push({ q: '我想查订单', t: '查订单' });
            chips.push({ q: '售后帮助：卡密或到账问题怎么处理', t: '售后自助' });
            chips.push({ q: '__handoff__', t: '转人工' });
            sugsEl.innerHTML = '';
            chips.forEach(function (c) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'aiw-chip' + (c.q === '__handoff__' ? ' aiw-chip-human' : '');
                b.textContent = c.t;
                b.onclick = function () {
                    if (c.q === '__handoff__') showHandoff(true);
                    else send(c.q);
                };
                sugsEl.appendChild(b);
            });
        }
        function setBusy(v) {
            busy = v;
            sendBtn.disabled = v;
            textEl.disabled = v;
            sendBtn.style.display = v ? 'none' : 'inline-block';
            stopBtn.style.display = v ? 'inline-block' : 'none';
            var chips = sugsEl.querySelectorAll('.aiw-chip');
            for (var i = 0; i < chips.length; i++) chips[i].disabled = v;
            var hOk = qs('#aiwHOk', root);
            if (hOk) hOk.disabled = v;
            if (fileEl) fileEl.disabled = v;
        }
        function stopRequest() {
            if (currentXhr) {
                try { currentXhr.abort(); } catch (e) {}
                currentXhr = null;
            }
            var thinking = qs('.thinking', box);
            if (thinking && thinking.parentNode) thinking.parentNode.removeChild(thinking);
            setBusy(false);
            meta.textContent = '已取消';
            if (lastFailed) addMsg('bot', '已取消本次请求。', { err: true, retryText: lastFailed });
        }
        function loadHistory(done) {
            if (csMode) {
                if (done) done(true);
                return;
            }
            if (!sid) {
                showWelcome();
                historyLoaded = true;
                if (done) done(false);
                return;
            }
            meta.textContent = '加载记录…';
            var xhr = new XMLHttpRequest();
            xhr.open('GET', endpoint() + '?act=history&session_id=' + sid, true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                historyLoaded = true;
                meta.textContent = '';
                var res = null;
                try { res = JSON.parse(xhr.responseText); } catch (e) {}
                box.innerHTML = '';
                seenMsg = {};
                if (!res || res.code !== 0 || !res.session_id) {
                    sid = 0;
                    writeSid(0);
                    showWelcome();
                    if (done) done(false);
                    return;
                }
                sid = res.session_id;
                writeSid(sid);
                var msgs = res.messages || [];
                if (!msgs.length) showWelcome();
                else {
                    msgs.forEach(function (m) {
                        if (m.role === 'user' || m.role === 'assistant') {
                            addMsg(m.role === 'user' ? 'user' : 'bot', m.content || '');
                        }
                    });
                    meta.textContent = '已恢复历史';
                }
                if (done) done(true);
            };
            xhr.send();
        }
        function newChat() {
            if (busy) return;
            if (csMode) {
                exitCsMode();
            }
            sid = 0;
            writeSid(0);
            lastFailed = '';
            historyLoaded = true;
            setLinks([]);
            showHandoff(false);
            showWelcome();
            meta.textContent = '新对话';
            textEl.focus();
        }
        function send(text) {
            text = String(text || '').replace(/^\s+|\s+$/g, '');
            if (!text || busy) return;
            if (text.length > MAX_LEN) {
                addMsg('bot', '请把问题缩短到 ' + MAX_LEN + ' 字以内', { err: true });
                return;
            }
            if (csMode) {
                sendCs(text);
                return;
            }
            lastFailed = text;
            setBusy(true);
            showHandoff(false);
            addMsg('user', text);
            textEl.value = '';
            saveDraft('');
            var thinking = document.createElement('div');
            thinking.className = 'aiw-msg bot thinking';
            thinking.innerHTML = '思考中<span class="aiw-dots"><span></span><span></span><span></span></span>';
            box.appendChild(thinking);
            box.scrollTop = box.scrollHeight;
            meta.textContent = '';

            var payload = JSON.stringify({ message: text, session_id: sid || 0, context: refreshContext() });
            var xhr = new XMLHttpRequest();
            currentXhr = xhr;
            xhr.open('POST', endpoint() + '?act=chat', true);
            xhr.setRequestHeader('Content-Type', 'application/json; charset=UTF-8');
            xhr.timeout = 120000;
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                if (currentXhr === xhr) currentXhr = null;
                setBusy(false);
                if (thinking.parentNode) thinking.parentNode.removeChild(thinking);
                if (xhr.status === 0) return;
                var res = null;
                try { res = JSON.parse(xhr.responseText); } catch (e) {}
                if (!res) {
                    addMsg('bot', '网络异常，请稍后再试', { err: true, retryText: text });
                    return;
                }
                if (res.session_id) {
                    sid = res.session_id;
                    writeSid(sid);
                }
                if (res.code === 0) {
                    lastFailed = '';
                    addMsg('bot', res.reply || '', { needHuman: !!res.need_human && !res.cs_id });
                    setLinks(res.links || []);
                    if (res.cs_id) {
                        meta.textContent = '可转人工 #' + res.cs_id;
                        writeCsId(res.cs_id);
                    } else if (res.rate && typeof res.rate.remain === 'number') {
                        meta.textContent = '还可提问 ' + res.rate.remain + ' 次/分钟';
                    }
                    if (!open) setUnread(true);
                } else {
                    addMsg('bot', res.msg || '暂时无法回答', { err: true, retryText: text, needHuman: true });
                }
                textEl.focus();
            };
            xhr.ontimeout = function () {
                if (currentXhr === xhr) currentXhr = null;
                setBusy(false);
                if (thinking.parentNode) thinking.parentNode.removeChild(thinking);
                addMsg('bot', '请求超时，请重试', { err: true, retryText: text, needHuman: true });
            };
            xhr.send(payload);
        }
        function syncViewport() {
            var kb = 0;
            try {
                if (window.visualViewport) {
                    var vv = window.visualViewport;
                    kb = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
                }
            } catch (e) {}
            root.style.setProperty('--aiw-kb', kb > 40 ? (kb + 'px') : '0px');
            if (open && box) box.scrollTop = box.scrollHeight;
        }
        function lockBody(on) {
            if (on && isMobileView()) document.body.classList.add('aiw-lock');
            else document.body.classList.remove('aiw-lock');
        }
        function openPanel() {
            open = true;
            var panel = qs('#aiwPanel', root);
            panel.style.display = 'flex';
            root.classList.add('is-open');
            lockBody(true);
            syncViewport();
            setUnread(false);
            refreshContext();
            if (csId) {
                resumeCsIfAny(function () { textEl.focus(); });
                return;
            }
            function afterHist() { textEl.focus(); }
            if (!historyLoaded) loadHistory(function () { afterHist(); });
            else if (sid) {
                historyLoaded = false;
                loadHistory(function () { afterHist(); });
            } else afterHist();
        }
        function closePanel() {
            open = false;
            qs('#aiwPanel', root).style.display = 'none';
            root.classList.remove('is-open');
            lockBody(false);
            root.style.setProperty('--aiw-kb', '0px');
            saveDraft(textEl.value || '');
        }

        qs('#aiwFab', root).onclick = function () {
            if (open) closePanel();
            else openPanel();
        };
        qs('#aiwClose', root).onclick = closePanel;
        qs('#aiwMask', root).onclick = closePanel;
        qs('#aiwNew', root).onclick = newChat;
        qs('#aiwHuman', root).onclick = function (e) {
            if (e && e.preventDefault) e.preventDefault();
            if (csMode) {
                meta.textContent = '已在客服会话中';
                return;
            }
            showHandoff(!handoffOpen);
        };
        qs('#aiwHCancel', root).onclick = function () { showHandoff(false); };
        qs('#aiwHOk', root).onclick = submitHandoff;
        fileEl.onchange = function () {
            if (fileEl.files && fileEl.files[0]) uploadImage(fileEl.files[0]);
        };
        stopBtn.onclick = stopRequest;
        sendBtn.onclick = function () { send(textEl.value); };
        textEl.onkeydown = function (e) {
            e = e || window.event;
            if (e.keyCode === 13 && !e.shiftKey) {
                if (e.preventDefault) e.preventDefault();
                send(this.value);
            }
        };
        textEl.oninput = function () {
            saveDraft(this.value || '');
            this.style.height = 'auto';
            this.style.height = Math.min(120, Math.max(44, this.scrollHeight)) + 'px';
        };
        document.addEventListener('keydown', function (e) {
            e = e || window.event;
            if (open && e.keyCode === 27) {
                if (handoffOpen) showHandoff(false);
                else if (busy) stopRequest();
                else closePanel();
            }
        });
        document.addEventListener('change', function (e) {
            var t = e.target || e.srcElement;
            if (!t) return;
            if (t.id === 'tid' || t.id === 'cid' || t.name === 'tid' || t.name === 'cid') refreshContext();
        });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', syncViewport);
            window.visualViewport.addEventListener('scroll', syncViewport);
        }
        window.addEventListener('resize', function () {
            if (hasBottomNav()) root.classList.add('has-bottom-nav');
            else root.classList.remove('has-bottom-nav');
            if (!isMobileView()) lockBody(false);
            syncViewport();
        });

        var draft = loadDraft();
        if (draft) {
            textEl.value = draft;
            textEl.style.height = Math.min(120, Math.max(44, textEl.scrollHeight)) + 'px';
        }
        refreshContext();
        if (csId) setCsMode(true, {});
    }

    function start() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', endpoint() + '?act=bootstrap', true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.code === 0) boot(res.data);
            } catch (e) {}
        };
        xhr.send();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
