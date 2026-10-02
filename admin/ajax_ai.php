<?php
include("../includes/common.php");
if ($islogin != 1) {
    @header('Content-Type: application/json; charset=UTF-8');
    exit(json_encode(array('code' => -1, 'msg' => '请先登录后台')));
}

$act = isset($_GET['act']) ? daddslashes($_GET['act']) : (isset($_POST['act']) ? daddslashes($_POST['act']) : null);
@header('Content-Type: application/json; charset=UTF-8');

if (!checkRefererHost()) {
    exit(json_encode(array('code' => 403, 'msg' => 'forbidden')));
}

function ai_presets()
{
    return array(
        array(
            'id' => 'deepseek',
            'name' => 'DeepSeek',
            'api_base' => 'https://api.deepseek.com/v1',
            'models' => array('deepseek-chat', 'deepseek-reasoner'),
            'hint' => '性价比高，适合日常运营指令',
        ),
        array(
            'id' => 'openai',
            'name' => 'OpenAI',
            'api_base' => 'https://api.openai.com/v1',
            'models' => array('gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini'),
            'hint' => '官方 OpenAI',
        ),
        array(
            'id' => 'qwen',
            'name' => '通义千问 (DashScope兼容)',
            'api_base' => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
            'models' => array('qwen-plus', 'qwen-turbo', 'qwen-max'),
            'hint' => '阿里云百炼 OpenAI 兼容模式',
        ),
        array(
            'id' => 'moonshot',
            'name' => '月之暗面 Kimi',
            'api_base' => 'https://api.moonshot.cn/v1',
            'models' => array('moonshot-v1-8k', 'moonshot-v1-32k', 'moonshot-v1-128k'),
            'hint' => '长上下文',
        ),
        array(
            'id' => 'zhipu',
            'name' => '智谱 GLM',
            'api_base' => 'https://open.bigmodel.cn/api/paas/v4',
            'models' => array('glm-4-flash', 'glm-4-air', 'glm-4'),
            'hint' => '智谱 OpenAI 兼容接口',
        ),
        array(
            'id' => 'siliconflow',
            'name' => '硅基流动',
            'api_base' => 'https://api.siliconflow.cn/v1',
            'models' => array('deepseek-ai/DeepSeek-V3', 'Qwen/Qwen2.5-72B-Instruct'),
            'hint' => '聚合多家开源模型',
        ),
        array(
            'id' => 'custom',
            'name' => '自定义 OpenAI 兼容',
            'api_base' => '',
            'models' => array(),
            'hint' => '任意兼容 /v1/chat/completions 的网关',
        ),
    );
}

function ai_get_runtime_conf($conf)
{
    return array(
        'enabled' => !empty($conf['ai_enabled']) && intval($conf['ai_enabled']) === 1,
        'provider' => isset($conf['ai_provider']) ? $conf['ai_provider'] : 'deepseek',
        'api_base' => isset($conf['ai_api_base']) ? $conf['ai_api_base'] : 'https://api.deepseek.com/v1',
        'api_key' => isset($conf['ai_api_key']) ? $conf['ai_api_key'] : '',
        'api_key_set' => !empty($conf['ai_api_key']),
        'model' => isset($conf['ai_model']) ? $conf['ai_model'] : 'deepseek-chat',
        'temperature' => isset($conf['ai_temperature']) ? floatval($conf['ai_temperature']) : 0.2,
        'max_tokens' => isset($conf['ai_max_tokens']) ? intval($conf['ai_max_tokens']) : 4096,
        'system_prompt' => isset($conf['ai_system_prompt']) ? $conf['ai_system_prompt'] : '',
        'api_token' => isset($conf['ai_api_token']) ? $conf['ai_api_token'] : '',
        'api_token_set' => !empty($conf['ai_api_token']),
    );
}

switch ($act) {
    case 'presets':
        exit(json_encode(array('code' => 0, 'data' => ai_presets())));
        break;

    case 'get_config':
        $c = ai_get_runtime_conf($conf);
        unset($c['api_key']); // 不回传明文
        unset($c['api_token']);
        exit(json_encode(array('code' => 0, 'data' => $c)));
        break;

    case 'save_config':
        $fields = array(
            'ai_enabled', 'ai_provider', 'ai_api_base', 'ai_model',
            'ai_temperature', 'ai_max_tokens', 'ai_system_prompt',
        );
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                saveSetting($f, $_POST[$f]);
            }
        }
        if (isset($_POST['ai_api_key']) && $_POST['ai_api_key'] !== '') {
            // 传 ***keep*** 表示保留原值
            if ($_POST['ai_api_key'] !== '***keep***') {
                saveSetting('ai_api_key', trim($_POST['ai_api_key']));
            }
        }
        if (isset($_POST['ai_api_token'])) {
            $tok = trim($_POST['ai_api_token']);
            if ($tok === '***keep***') {
                // skip
            } elseif ($tok === '' || $tok === '***regen***') {
                $tok = bin2hex(random_bytes(16));
                saveSetting('ai_api_token', $tok);
            } else {
                saveSetting('ai_api_token', $tok);
            }
        }
        $CACHE->clear();
        $conf = $CACHE->pre_fetch();
        $out = ai_get_runtime_conf($conf);
        $showToken = isset($tok) ? $tok : null;
        unset($out['api_key']);
        if ($showToken && $showToken !== '***keep***') {
            $out['api_token'] = $showToken;
        } else {
            unset($out['api_token']);
        }
        exit(json_encode(array('code' => 0, 'msg' => '已保存', 'data' => $out)));
        break;

    case 'test':
        $c = ai_get_runtime_conf($conf);
        if ($c['api_key'] === '') {
            exit(json_encode(array('code' => -1, 'msg' => '请先填写 API Key')));
        }
        try {
            $client = new \lib\Ai\Client($c['api_base'], $c['api_key'], 60);
            $resp = $client->ping($c['model']);
            $text = isset($resp['choices'][0]['message']['content']) ? $resp['choices'][0]['message']['content'] : '';
            exit(json_encode(array('code' => 0, 'msg' => '连通成功', 'reply' => $text, 'model' => $c['model'])));
        } catch (Exception $e) {
            exit(json_encode(array('code' => -1, 'msg' => $e->getMessage())));
        }
        break;

    case 'chat':
        $c = ai_get_runtime_conf($conf);
        if (!$c['enabled']) {
            exit(json_encode(array('code' => -1, 'msg' => 'AI 未启用，请先在模型配置中开启')));
        }
        if ($c['api_key'] === '') {
            exit(json_encode(array('code' => -1, 'msg' => '请先配置 API Key')));
        }
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        $userMsg = isset($input['message']) ? trim($input['message']) : '';
        $history = isset($input['history']) && is_array($input['history']) ? $input['history'] : array();
        if ($userMsg === '') {
            exit(json_encode(array('code' => -1, 'msg' => '消息不能为空')));
        }
        // 只保留 user/assistant 简短历史，去掉过大的 tool 中间态
        $messages = array();
        foreach ($history as $h) {
            if (!isset($h['role'], $h['content'])) continue;
            if ($h['role'] !== 'user' && $h['role'] !== 'assistant') continue;
            $messages[] = array('role' => $h['role'], 'content' => strval($h['content']));
            if (count($messages) >= 20) break;
        }
        $messages[] = array('role' => 'user', 'content' => $userMsg);

        try {
            $client = new \lib\Ai\Client($c['api_base'], $c['api_key'], 180);
            $tools = new \lib\Ai\Tools($DB, $conf, $CACHE);
            $agent = new \lib\Ai\Agent($client, $tools, array(
                'model' => $c['model'],
                'temperature' => $c['temperature'],
                'max_tokens' => $c['max_tokens'],
                'system_prompt' => $c['system_prompt'],
                'max_rounds' => 8,
            ));
            $result = $agent->run($messages);
            exit(json_encode(array(
                'code' => 0,
                'reply' => $result['reply'],
                'tool_trace' => $result['tool_trace'],
                'usage' => $result['usage'],
            ), JSON_UNESCAPED_UNICODE));
        } catch (Exception $e) {
            exit(json_encode(array('code' => -1, 'msg' => $e->getMessage())));
        }
        break;

    case 'tools':
        $tools = new \lib\Ai\Tools($DB, $conf, $CACHE);
        $defs = $tools->definitions();
        $names = array();
        foreach ($defs as $d) {
            $names[] = array(
                'name' => $d['function']['name'],
                'description' => $d['function']['description'],
            );
        }
        exit(json_encode(array('code' => 0, 'data' => $names)));
        break;

    default:
        exit(json_encode(array('code' => -1, 'msg' => 'unknown act')));
}
