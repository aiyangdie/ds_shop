<?php
/**
 * AI 模型配置：接入多家 OpenAI 兼容接口
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
                这是通用框架：站点名、助手称呼都可自定义。也可在对话里让 AI 执行「把站名改成xxx」。配置兼容 OpenAI 的模型后即可用自然语言操作系统。
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
                <label class="col-sm-2 control-label">助手称呼</label>
                <div class="col-sm-8">
                    <input type="text" class="form-control" name="ai_assistant_name" id="ai_assistant_name" placeholder="助手 / 客服小X 等，可自定义">
                    <p class="help-block">对话里 AI 的自称；站点名用 sitename。两者都可在对话里让 AI 改，不必单独翻设置页。</p>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">系统提示词</label>
                <div class="col-sm-8">
                    <textarea class="form-control" name="ai_system_prompt" id="ai_system_prompt" rows="4" placeholder="留空使用内置提示词。可用变量 {sitename} {assistant_name}"></textarea>
                    <p class="help-block">仅用于后台运营助手。用户端/前台可单独配置下方提示词。</p>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">用户端 AI</label>
                <div class="col-sm-3">
                    <select name="ai_user_enabled" id="ai_user_enabled" class="form-control">
                        <option value="1">开启</option>
                        <option value="0">关闭</option>
                    </select>
                </div>
                <label class="col-sm-2 control-label">前台客服 AI</label>
                <div class="col-sm-3">
                    <select name="ai_shop_enabled" id="ai_shop_enabled" class="form-control">
                        <option value="1">开启</option>
                        <option value="0">关闭</option>
                    </select>
                    <p class="help-block" style="margin-top:6px">解释/推荐/查单/售后；转人工工单见 <a href="./ai_cs.php">客服工单</a></p>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">前台限流</label>
                <div class="col-sm-3">
                    <input type="number" min="5" max="60" class="form-control" name="ai_shop_rate" id="ai_shop_rate" value="20">
                    <p class="help-block">同一 IP 每分钟最多提问次数</p>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">前台日上限</label>
                <div class="col-sm-3">
                    <input type="number" min="10" max="500" class="form-control" name="ai_shop_daily" id="ai_shop_daily" value="100">
                    <p class="help-block">同一访客+IP 每日最多对话次数</p>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">用户端提示词</label>
                <div class="col-sm-8">
                    <textarea class="form-control" name="ai_user_prompt" id="ai_user_prompt" rows="3" placeholder="留空用内置用户端提示词。变量 {sitename} {assistant_name}"></textarea>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">前台客服提示词</label>
                <div class="col-sm-8">
                    <textarea class="form-control" name="ai_shop_prompt" id="ai_shop_prompt" rows="3" placeholder="留空用内置前台客服提示词。变量 {sitename} {assistant_name}"></textarea>
                </div>
            </div>

            <hr>
            <h4 style="margin:16px 0 12px"><i class="fa fa-cloud-upload"></i> 客服附件存储</h4>
            <p class="help-block" style="margin-top:0">图片走统一驱动：本地 / 腾讯COS / 阿里OSS / 七牛。库内只存 URL。</p>
            <div class="form-group">
                <label class="col-sm-2 control-label">存储驱动</label>
                <div class="col-sm-3">
                    <select name="ai_storage_driver" id="ai_storage_driver" class="form-control">
                        <option value="local">本地 assets/uploads/cs</option>
                        <option value="cos">腾讯云 COS</option>
                        <option value="oss">阿里云 OSS</option>
                        <option value="qiniu">七牛云</option>
                    </select>
                </div>
            </div>
            <div class="form-group storage-cloud">
                <label class="col-sm-2 control-label">AccessKey / SecretId</label>
                <div class="col-sm-8">
                    <input type="password" class="form-control" name="ai_storage_ak" id="ai_storage_ak" placeholder="留空不修改" autocomplete="off">
                    <p class="help-block" id="storageAkHint"></p>
                </div>
            </div>
            <div class="form-group storage-cloud">
                <label class="col-sm-2 control-label">SecretKey</label>
                <div class="col-sm-8">
                    <input type="password" class="form-control" name="ai_storage_sk" id="ai_storage_sk" placeholder="留空不修改" autocomplete="off">
                    <p class="help-block" id="storageSkHint"></p>
                </div>
            </div>
            <div class="form-group storage-cloud">
                <label class="col-sm-2 control-label">Bucket</label>
                <div class="col-sm-3">
                    <input type="text" class="form-control" name="ai_storage_bucket" id="ai_storage_bucket" placeholder="桶名">
                </div>
                <label class="col-sm-2 control-label">Region / Zone</label>
                <div class="col-sm-3">
                    <input type="text" class="form-control" name="ai_storage_region" id="ai_storage_region" placeholder="如 ap-guangzhou / z0">
                </div>
            </div>
            <div class="form-group storage-cloud">
                <label class="col-sm-2 control-label">Endpoint</label>
                <div class="col-sm-8">
                    <input type="text" class="form-control" name="ai_storage_endpoint" id="ai_storage_endpoint" placeholder="OSS 用，如 oss-cn-hangzhou.aliyuncs.com">
                </div>
            </div>
            <div class="form-group storage-cloud">
                <label class="col-sm-2 control-label">CDN / 访问域名</label>
                <div class="col-sm-8">
                    <input type="text" class="form-control" name="ai_storage_cdn" id="ai_storage_cdn" placeholder="https://img.example.com （七牛必填）">
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

    function toggleStorage() {
        var d = $('#ai_storage_driver').val();
        $('.storage-cloud').toggle(d !== 'local');
    }
    $('#ai_storage_driver').on('change', toggleStorage);

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
            $('#ai_assistant_name').val(d.assistant_name || '');
            $('#ai_user_enabled').val(d.user_enabled ? '1' : '0');
            $('#ai_shop_enabled').val(d.shop_enabled ? '1' : '0');
            $('#ai_shop_rate').val(d.shop_rate || 20);
            $('#ai_shop_daily').val(d.shop_daily || 100);
            $('#ai_user_prompt').val(d.user_prompt || '');
            $('#ai_shop_prompt').val(d.shop_prompt || '');
            $('#ai_storage_driver').val(d.storage_driver || 'local');
            $('#ai_storage_bucket').val(d.storage_bucket || '');
            $('#ai_storage_region').val(d.storage_region || '');
            $('#ai_storage_endpoint').val(d.storage_endpoint || '');
            $('#ai_storage_cdn').val(d.storage_cdn || '');
            $('#storageAkHint').text(d.storage_ak_set ? '已保存 AK（输入新值可覆盖）' : '尚未配置');
            $('#storageSkHint').text(d.storage_sk_set ? '已保存 SK（输入新值可覆盖）' : '尚未配置');
            toggleStorage();
            $('#keyHint').text(d.api_key_set ? '已保存 Key（输入新值可覆盖）' : '尚未配置 Key');
            if (d.api_token_set) {
                $('#ai_api_token_display').val('已生成（点击重新生成可轮换）');
            }
        });
    }

    function toggleStorage() {
        var d = $('#ai_storage_driver').val() || 'local';
        $('.storage-cloud').toggle(d !== 'local');
    }
    $('#ai_storage_driver').on('change', toggleStorage);

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
        if (!$('#ai_storage_ak').val()) payload.ai_storage_ak = '***keep***';
        if (!$('#ai_storage_sk').val()) payload.ai_storage_sk = '***keep***';
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
                $('#ai_storage_ak').val('');
                $('#ai_storage_sk').val('');
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
