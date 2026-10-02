<?php
/**
 * AI 运营助手：自然语言直接操作系统（订单/商品/分站/配置）
 */
include("../includes/common.php");
$title = 'AI助手';
include './head.php';
if ($islogin != 1) exit("<script>window.location.href='./login.php';</script>");
$aiOn = !empty($conf['ai_enabled']) && intval($conf['ai_enabled']) === 1;
$model = isset($conf['ai_model']) ? htmlspecialchars($conf['ai_model']) : '未配置';
?>
<style>
.ai-wrap{display:flex;flex-direction:column;height:calc(100vh - 160px);min-height:480px}
.ai-chat{flex:1;overflow-y:auto;background:#f7f8fa;border:1px solid #e4e7ed;border-radius:4px;padding:16px;margin-bottom:12px}
.ai-msg{margin-bottom:14px;max-width:92%}
.ai-msg.user{margin-left:auto;text-align:right}
.ai-msg .bubble{display:inline-block;text-align:left;padding:10px 14px;border-radius:10px;line-height:1.55;white-space:pre-wrap;word-break:break-word}
.ai-msg.user .bubble{background:#3f9eff;color:#fff}
.ai-msg.assistant .bubble{background:#fff;border:1px solid #e5e5e5;color:#333}
.ai-msg .meta{font-size:12px;color:#999;margin:4px 0}
.ai-tools{font-size:12px;color:#666;background:#fffbe6;border:1px dashed #f0d78c;border-radius:4px;padding:8px;margin-top:6px;text-align:left}
.ai-input-row{display:flex;gap:8px}
.ai-input-row textarea{flex:1;resize:vertical;min-height:64px}
.ai-suggest button{margin:0 6px 6px 0}
</style>
<div class="col-xs-12 col-sm-10 col-lg-10 center-block" style="float:none;">
    <div class="block">
        <div class="block-title">
            <h3><i class="fa fa-robot"></i>&nbsp;AI 运营助手</h3>
            <div class="block-options pull-right">
                <span class="text-muted" style="margin-right:10px">模型：<?php echo $model ?></span>
                <a href="./ai_set.php" class="btn btn-sm btn-default"><i class="fa fa-cogs"></i> 模型配置</a>
            </div>
        </div>

        <?php if (!$aiOn) { ?>
            <div class="alert alert-warning">AI 尚未启用。请先到 <a href="./ai_set.php">模型配置</a> 填写 API Key 并开启。</div>
        <?php } ?>

        <div class="ai-suggest">
            <span class="text-muted">试试：</span>
            <button type="button" class="btn btn-xs btn-info sug">今天经营数据怎么样</button>
            <button type="button" class="btn btn-xs btn-info sug">列出最近20笔未处理订单</button>
            <button type="button" class="btn btn-xs btn-info sug">搜索名称含卡密的商品</button>
            <button type="button" class="btn btn-xs btn-info sug">查看对接货源站点</button>
            <button type="button" class="btn btn-xs btn-default" id="btnClear">清空对话</button>
        </div>

        <div class="ai-wrap">
            <div class="ai-chat" id="chatBox">
                <div class="ai-msg assistant">
                    <div class="meta">助手</div>
                    <div class="bubble">你好。我可以直接操作本站：查改订单、上下架商品、管理分类/分站、读改站点配置、拉取货源商品。直接用中文说需求即可。</div>
                </div>
            </div>
            <div class="ai-input-row">
                <textarea id="userInput" class="form-control" placeholder="例如：把商品 tid=12 下架；把订单 1003 标为已完成；给分站 zid=5 充值 10 元（需确认）"></textarea>
                <button type="button" class="btn btn-primary" id="btnSend" style="min-width:88px"><i class="fa fa-paper-plane"></i> 发送</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var history = [];
    var busy = false;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function appendMsg(role, text, tools) {
        var box = $('#chatBox');
        var el = $('<div class="ai-msg"></div>').addClass(role);
        el.append($('<div class="meta"></div>').text(role === 'user' ? '你' : '助手'));
        el.append($('<div class="bubble"></div>').text(text || ''));
        if (tools && tools.length) {
            var t = $('<div class="ai-tools"></div>');
            tools.forEach(function (x) {
                t.append($('<div></div>').text('⚙ ' + x.name + ' → ' + (x.result && x.result.ok === false ? ('失败: ' + (x.result.error || '')) : '完成')));
            });
            el.append(t);
        }
        box.append(el);
        box.scrollTop(box[0].scrollHeight);
    }

    function send(text) {
        text = $.trim(text || '');
        if (!text || busy) return;
        busy = true;
        $('#btnSend').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        appendMsg('user', text);
        history.push({role: 'user', content: text});
        $('#userInput').val('');

        $.ajax({
            url: 'ajax_ai.php?act=chat',
            method: 'POST',
            contentType: 'application/json; charset=UTF-8',
            dataType: 'json',
            data: JSON.stringify({message: text, history: history.slice(0, -1)}),
            timeout: 180000,
            success: function (res) {
                if (res.code === 0) {
                    appendMsg('assistant', res.reply, res.tool_trace || []);
                    history.push({role: 'assistant', content: res.reply || ''});
                    if (history.length > 24) history = history.slice(-24);
                } else {
                    appendMsg('assistant', '错误：' + (res.msg || '未知错误'));
                }
            },
            error: function (xhr) {
                appendMsg('assistant', '请求失败 HTTP ' + xhr.status);
            },
            complete: function () {
                busy = false;
                $('#btnSend').prop('disabled', false).html('<i class="fa fa-paper-plane"></i> 发送');
            }
        });
    }

    $('#btnSend').on('click', function () { send($('#userInput').val()); });
    $('#userInput').on('keydown', function (e) {
        if (e.keyCode === 13 && !e.shiftKey) {
            e.preventDefault();
            send($(this).val());
        }
    });
    $('.sug').on('click', function () { send($(this).text()); });
    $('#btnClear').on('click', function () {
        history = [];
        $('#chatBox').html('');
        appendMsg('assistant', '对话已清空。继续告诉我要处理什么。');
    });
})();
</script>
