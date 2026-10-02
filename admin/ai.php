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
$welcome = "我是彩虹自助下单商城的运营助手，可以直接帮你操作系统，主要能做这些事：\n\n"
    . "经营查看\n"
    . "查看今日昨日经营概况（订单数、支付额、分站数、待处理工单等）\n"
    . "搜索、查看订单详情\n\n"
    . "订单处理\n"
    . "修改订单状态（未处理、已完成、处理中、异常、已退款、删除）\n"
    . "对未处理或异常订单退款，必要时重新对接货源下单\n"
    . "批量处理一批订单（需你确认）\n\n"
    . "商品与分站\n"
    . "搜索改价、上下架、新建商品、管理分类\n"
    . "查看分站、启停、余额调整、读写站点配置、查看对接货源\n\n"
    . "直接用中文告诉我要做什么即可。涉及退款、充值、批量改状态时，我会先跟你确认。";
?>
<style>
.ai-wrap{display:flex;flex-direction:column;height:calc(100vh - 160px);min-height:480px}
.ai-chat{flex:1;overflow-y:auto;background:#f7f8fa;border:1px solid #e4e7ed;border-radius:4px;padding:16px;margin-bottom:12px}
.ai-msg{margin-bottom:14px;max-width:92%}
.ai-msg.user{margin-left:auto;text-align:right}
.ai-msg .bubble{display:inline-block;text-align:left;padding:10px 14px;border-radius:10px;line-height:1.65;white-space:pre-wrap;word-break:break-word}
.ai-msg.user .bubble{background:#3f9eff;color:#fff}
.ai-msg.assistant .bubble{background:#fff;border:1px solid #e5e5e5;color:#333}
.ai-msg.assistant.error .bubble{background:#fff5f5;border-color:#f5c2c7;color:#b42318}
.ai-msg .meta{font-size:12px;color:#999;margin:4px 0}
.ai-tools{font-size:12px;color:#666;background:#f8fafc;border:1px solid #e5e7eb;border-radius:4px;padding:8px;margin-top:6px;text-align:left}
.ai-tools .ok{color:#067647}
.ai-tools .fail{color:#b42318}
.ai-input-row{display:flex;gap:8px}
.ai-input-row textarea{flex:1;resize:vertical;min-height:64px}
.ai-suggest button{margin:0 6px 6px 0}
</style>
<div class="col-xs-12 col-sm-10 col-lg-10 center-block" style="float:none;">
    <div class="block">
        <div class="block-title">
            <h3><i class="fa fa-magic"></i>&nbsp;AI 运营助手</h3>
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
            <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn ? '' : 'disabled'; ?>>今天经营数据怎么样</button>
            <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn ? '' : 'disabled'; ?>>列出未处理订单</button>
            <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn ? '' : 'disabled'; ?>>搜索名称含卡密的商品</button>
            <button type="button" class="btn btn-xs btn-info sug" <?php echo $aiOn ? '' : 'disabled'; ?>>查看对接货源站点</button>
            <button type="button" class="btn btn-xs btn-default" id="btnClear">清空对话</button>
        </div>

        <div class="ai-wrap">
            <div class="ai-chat" id="chatBox">
                <div class="ai-msg assistant">
                    <div class="meta">助手</div>
                    <div class="bubble" id="welcomeBubble"></div>
                </div>
            </div>
            <div class="ai-input-row">
                <textarea id="userInput" class="form-control" placeholder="例如：把订单 1003 标为已完成；处理未处理订单；商品 tid=12 下架" <?php echo $aiOn ? '' : 'disabled'; ?>></textarea>
                <button type="button" class="btn btn-primary" id="btnSend" style="min-width:88px" <?php echo $aiOn ? '' : 'disabled'; ?>><i class="fa fa-paper-plane"></i> 发送</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var history = [];
    var busy = false;
    var aiOn = <?php echo $aiOn ? 'true' : 'false'; ?>;
    var welcomeText = <?php echo json_encode($welcome, JSON_UNESCAPED_UNICODE); ?>;
    var toolNames = {
        dashboard_stats: '经营概况',
        search_orders: '搜索订单',
        get_order: '订单详情',
        set_order_status: '修改订单状态',
        batch_set_order_status: '批量改订单状态',
        refund_order: '订单退款',
        redo_dock_order: '重新对接',
        list_goods: '商品列表',
        get_goods: '商品详情',
        update_goods: '更新商品',
        create_goods: '新建商品',
        set_goods_shelf: '商品上下架',
        list_classes: '分类列表',
        save_class: '保存分类',
        list_sites: '分站列表',
        set_site: '更新分站',
        site_recharge: '分站余额',
        get_config: '读取配置',
        update_config: '更新配置',
        list_shequ: '对接站点',
        supplier_pull_goods: '拉取货源商品'
    };

    function cleanText(text) {
        text = String(text == null ? '' : text);
        // 去掉 markdown 星号/井号/反引号等，避免界面出现生硬符号
        text = text.replace(/\*\*?/g, '');
        text = text.replace(/__/g, '');
        text = text.replace(/^#{1,6}\s*/gm, '');
        text = text.replace(/`+/g, '');
        text = text.replace(/^\s*[-*+]\s+/gm, '');
        text = text.replace(/^\s*>\s?/gm, '');
        // 去掉常见装饰性 emoji
        text = text.replace(/[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}]/gu, '');
        text = text.replace(/[ \t]+\n/g, '\n');
        text = text.replace(/\n{3,}/g, '\n\n');
        return text.replace(/^\s+|\s+$/g, '');
    }

    function appendMsg(role, text, tools, isError) {
        var box = $('#chatBox');
        var el = $('<div class="ai-msg"></div>').addClass(role);
        if (isError) el.addClass('error');
        el.append($('<div class="meta"></div>').text(role === 'user' ? '你' : '助手'));
        el.append($('<div class="bubble"></div>').text(role === 'assistant' ? cleanText(text) : (text || '')));
        if (tools && tools.length) {
            var t = $('<div class="ai-tools"></div>');
            tools.forEach(function (x) {
                var ok = !(x.result && x.result.ok === false);
                var label = toolNames[x.name] || x.name;
                var line = $('<div></div>').addClass(ok ? 'ok' : 'fail')
                    .text((ok ? '已完成：' : '未完成：') + label + (ok ? '' : ('（' + ((x.result && x.result.error) || '失败') + '）')));
                t.append(line);
            });
            el.append(t);
        }
        box.append(el);
        box.scrollTop(box[0].scrollHeight);
    }

    $('#welcomeBubble').text(cleanText(welcomeText));

    function send(text) {
        text = $.trim(text || '');
        if (!text || busy) return;
        if (!aiOn) {
            appendMsg('assistant', '请先在模型配置中启用 AI 并填写 API Key。', null, true);
            return;
        }
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
                    history.push({role: 'assistant', content: cleanText(res.reply || '')});
                    if (history.length > 24) history = history.slice(-24);
                } else {
                    appendMsg('assistant', res.msg || '未知错误', null, true);
                    history.pop();
                }
            },
            error: function (xhr) {
                appendMsg('assistant', '请求失败，HTTP ' + xhr.status, null, true);
                history.pop();
            },
            complete: function () {
                busy = false;
                if (aiOn) $('#btnSend').prop('disabled', false).html('<i class="fa fa-paper-plane"></i> 发送');
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
        appendMsg('assistant', welcomeText);
    });
})();
</script>
