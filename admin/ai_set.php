<?php
/**
 * AI 模型配置：接入多家 OpenAI 兼容接口，供运营助手调用
 */
include("../includes/common.php");
$title = 'AI模型配置';
include './head.php';
if ($islogin != 1) exit("<script>window.location.href='./login.php';</script>");
?>
<div class="col-xs-12 col-sm-10 col-lg-10 center-block" style="float:none;">
    <div class="block">
        <div class="block-title">
            <h3><i class="fa fa-cogs"></i>&nbsp;AI 模型配置</h3>
            <div class="block-options pull-right">
                <a href="./ai.php" class="btn btn-sm btn-primary"><i class="fa fa-comments"></i> 打开 AI 助手</a>
            </div>
        </div>

        <div class="alert alert-info">
            配置任意兼容 OpenAI <code>/v1/chat/completions</code> 的模型后，可在「AI助手」里用自然语言直接查订单、改商品、管分站，无需再点后台页面。
        </div>

        <form id="aiSetForm" class="form-horizontal" onsubmit="return false;">
            <div class="form-group">
                <label class="col-sm-2 control-label">启用 AI</label>
                <div class="col-sm-8">
                    <select name="ai_enabled" id="ai_enabled" class="form-control" style="max-width:200px">
                        <option value="1">开启</option>
                        <option value="0">关闭</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label">快捷预设</label>
                <div class="col-sm-10" id="presetBox"></div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label">Provider</label>
                <div class="col-sm-8">
                    <input type="text" class="form-control" name="ai_provider" id="ai_provider" placeholder="deepseek / openai / custom">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">API Base</label>
                <div class="col-sm-8">
                    <input type="text" class="form-control" name="ai_api_base" id="ai_api_base" placeholder="https://api.deepseek.com/v1">
                    <p class="help-block">不要漏写 <code>/v1</code>；智谱一般为 <code>.../api/paas/v4</code></p>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">API Key</label>
                <div class="col-sm-8">
                    <input type="password" class="form-control" name="ai_api_key" id="ai_api_key" placeholder="留空则不修改已保存的 Key" autocomplete="off">
                    <p class="help-block" id="keyHint"></p>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">模型名</label>
                <div class="col-sm-8">
                    <input type="text" class="form-control" name="ai_model" id="ai_model" list="modelList" placeholder="deepseek-chat">
                    <datalist id="modelList"></datalist>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">Temperature</label>
                <div class="col-sm-3">
                    <input type="number" step="0.1" min="0" max="2" class="form-control" name="ai_temperature" id="ai_temperature" value="0.2">
                </div>
                <label class="col-sm-2 control-label">Max Tokens</label>
                <div class="col-sm-3">
                    <input type="number" class="form-control" name="ai_max_tokens" id="ai_max_tokens" value="4096">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">系统提示词</label>
                <div class="col-sm-8">
                    <textarea class="form-control" name="ai_system_prompt" id="ai_system_prompt" rows="4" placeholder="留空使用内置运营助手提示词"></textarea>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">外部调用 Token</label>
                <div class="col-sm-8">
                    <div class="input-group">
                        <input type="text" class="form-control" id="ai_api_token_display" readonly placeholder="保存后生成">
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default" id="btnRegenToken">重新生成</button>
                        </span>
                    </div>
                    <p class="help-block">供外部 Agent / 脚本调用 <code>/ai_api.php</code>，Header: <code>Authorization: Bearer TOKEN</code></p>
                    <input type="hidden" name="ai_api_token" id="ai_api_token" value="***keep***">
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-8">
                    <button type="button" class="btn btn-primary" id="btnSave"><i class="fa fa-save"></i> 保存配置</button>
                    <button type="button" class="btn btn-success" id="btnTest"><i class="fa fa-plug"></i> 测试连通</button>
                    <a href="./ai.php" class="btn btn-default">去对话</a>
                    <span id="saveMsg" style="margin-left:10px"></span>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var presets = [];
    var regen = false;

    function applyPreset(p) {
        $('#ai_provider').val(p.id);
        if (p.api_base) $('#ai_api_base').val(p.api_base);
        var dl = $('#modelList').empty();
        (p.models || []).forEach(function (m) {
            dl.append($('<option>').val(m));
        });
        if (p.models && p.models.length && !$('#ai_model').val()) {
            $('#ai_model').val(p.models[0]);
        } else if (p.models && p.models.length) {
            $('#ai_model').val(p.models[0]);
        }
    }

    function renderPresets() {
        var box = $('#presetBox').empty();
        presets.forEach(function (p) {
            var btn = $('<button type="button" class="btn btn-sm btn-default" style="margin:0 8px 8px 0"></button>')
                .text(p.name)
                .attr('title', p.hint || '')
                .on('click', function () { applyPreset(p); });
            box.append(btn);
        });
    }

    function loadConfig() {
        $.getJSON('ajax_ai.php?act=get_config', function (res) {
            if (res.code !== 0) return;
            var d = res.data;
            $('#ai_enabled').val(d.enabled ? '1' : '0');
            $('#ai_provider').val(d.provider || '');
            $('#ai_api_base').val(d.api_base || '');
            $('#ai_model').val(d.model || '');
            $('#ai_temperature').val(d.temperature);
            $('#ai_max_tokens').val(d.max_tokens);
            $('#ai_system_prompt').val(d.system_prompt || '');
            $('#keyHint').text(d.api_key_set ? '已保存 Key（输入新值可覆盖）' : '尚未配置 Key');
            if (d.api_token_set) {
                $('#ai_api_token_display').val('已生成（点击重新生成可轮换）');
            }
        });
    }

    $('#btnRegenToken').on('click', function () {
        regen = true;
        $('#ai_api_token').val('***regen***');
        $('#ai_api_token_display').val('将在保存时重新生成…');
    });

    $('#btnSave').on('click', function () {
        var data = $('#aiSetForm').serializeArray();
        var payload = {};
        data.forEach(function (x) { payload[x.name] = x.value; });
        if (!$('#ai_api_key').val()) payload.ai_api_key = '***keep***';
        if (!regen) payload.ai_api_token = '***keep***';
        $.post('ajax_ai.php?act=save_config', payload, function (res) {
            if (typeof res === 'string') try { res = JSON.parse(res); } catch (e) {}
            if (res.code === 0) {
                $('#saveMsg').css('color', 'green').text(res.msg || '已保存');
                if (res.data && res.data.api_token) {
                    $('#ai_api_token_display').val(res.data.api_token);
                    alert('新的外部 Token：\n' + res.data.api_token + '\n请立即复制保存');
                }
                regen = false;
                $('#ai_api_token').val('***keep***');
                $('#ai_api_key').val('');
                loadConfig();
            } else {
                $('#saveMsg').css('color', 'red').text(res.msg || '失败');
            }
        });
    });

    $('#btnTest').on('click', function () {
        $('#saveMsg').css('color', '#666').text('测试中…');
        $.post('ajax_ai.php?act=test', {}, function (res) {
            if (typeof res === 'string') try { res = JSON.parse(res); } catch (e) {}
            if (res.code === 0) {
                $('#saveMsg').css('color', 'green').text('连通成功：' + (res.reply || ''));
            } else {
                $('#saveMsg').css('color', 'red').text(res.msg || '失败');
            }
        });
    });

    $.getJSON('ajax_ai.php?act=presets', function (res) {
        if (res.code === 0) {
            presets = res.data || [];
            renderPresets();
        }
        loadConfig();
    });
})();
</script>
