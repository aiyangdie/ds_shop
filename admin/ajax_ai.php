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
        array('id' => 'deepseek', 'name' => 'DeepSeek', 'api_base' => 'https://api.deepseek.com/v1', 'models' => array('deepseek-chat', 'deepseek-reasoner'), 'hint' => '性价比高'),
        array('id' => 'openai', 'name' => 'OpenAI', 'api_base' => 'https://api.openai.com/v1', 'models' => array('gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini'), 'hint' => '官方 OpenAI'),
        array('id' => 'qwen', 'name' => '通义千问', 'api_base' => 'https://dashscope.aliyuncs.com/compatible-mode/v1', 'models' => array('qwen-plus', 'qwen-turbo', 'qwen-max'), 'hint' => '阿里云兼容模式'),
        array('id' => 'moonshot', 'name' => 'Kimi', 'api_base' => 'https://api.moonshot.cn/v1', 'models' => array('moonshot-v1-8k', 'moonshot-v1-32k', 'moonshot-v1-128k'), 'hint' => '长上下文'),
        array('id' => 'zhipu', 'name' => '智谱 GLM', 'api_base' => 'https://open.bigmodel.cn/api/paas/v4', 'models' => array('glm-4-flash', 'glm-4-air', 'glm-4'), 'hint' => '智谱'),
        array('id' => 'siliconflow', 'name' => '硅基流动', 'api_base' => 'https://api.siliconflow.cn/v1', 'models' => array('deepseek-ai/DeepSeek-V3', 'Qwen/Qwen2.5-72B-Instruct'), 'hint' => '聚合模型'),
        array('id' => 'custom', 'name' => '自定义', 'api_base' => '', 'models' => array(), 'hint' => 'OpenAI 兼容网关'),
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
        'session_limit' => isset($conf['ai_session_limit']) ? intval($conf['ai_session_limit']) : 40,
        'message_limit' => isset($conf['ai_message_limit']) ? intval($conf['ai_message_limit']) : 80,
    );
}

$store = new \lib\Ai\Store($DB);
try {
    $store->ensureSchema();
} catch (Exception $e) {
    exit(json_encode(array('code' => -1, 'msg' => '初始化 AI 数据表失败：' . $e->getMessage())));
}

switch ($act) {
    case 'presets':
        exit(json_encode(array('code' => 0, 'data' => ai_presets())));
        break;

    case 'get_config':
        $c = ai_get_runtime_conf($conf);
        unset($c['api_key'], $c['api_token']);
        exit(json_encode(array('code' => 0, 'data' => $c)));
        break;

    case 'save_config':
        $fields = array('ai_enabled', 'ai_provider', 'ai_api_base', 'ai_model', 'ai_temperature', 'ai_max_tokens', 'ai_system_prompt', 'ai_session_limit', 'ai_message_limit');
        foreach ($fields as $f) {
            if (isset($_POST[$f])) saveSetting($f, $_POST[$f]);
        }
        if (isset($_POST['ai_api_key']) && $_POST['ai_api_key'] !== '' && $_POST['ai_api_key'] !== '***keep***') {
            saveSetting('ai_api_key', trim($_POST['ai_api_key']));
        }
        if (isset($_POST['ai_api_token'])) {
            $tok = trim($_POST['ai_api_token']);
            if ($tok === '***regen***' || $tok === '') {
                $tok = bin2hex(random_bytes(16));
                saveSetting('ai_api_token', $tok);
            } elseif ($tok !== '***keep***') {
                saveSetting('ai_api_token', $tok);
            }
        }
        $CACHE->clear();
        $conf = $CACHE->pre_fetch();
        $out = ai_get_runtime_conf($conf);
        unset($out['api_key']);
        if (isset($tok) && $tok !== '***keep***') $out['api_token'] = $tok;
        else unset($out['api_token']);
        exit(json_encode(array('code' => 0, 'msg' => '已保存', 'data' => $out)));
        break;

    case 'test':
        $c = ai_get_runtime_conf($conf);
        if ($c['api_key'] === '') exit(json_encode(array('code' => -1, 'msg' => '请先填写 API Key')));
        try {
            $client = new \lib\Ai\Client($c['api_base'], $c['api_key'], 60);
            $resp = $client->ping($c['model']);
            $text = isset($resp['choices'][0]['message']['content']) ? $resp['choices'][0]['message']['content'] : '';
            exit(json_encode(array('code' => 0, 'msg' => '连通成功', 'reply' => $text, 'model' => $c['model'])));
        } catch (Exception $e) {
            exit(json_encode(array('code' => -1, 'msg' => $e->getMessage())));
        }
        break;

    case 'sessions':
        $c = ai_get_runtime_conf($conf);
        $list = $store->listSessions($c['session_limit']);
        exit(json_encode(array('code' => 0, 'data' => $list, 'limit' => $c['session_limit']), JSON_UNESCAPED_UNICODE));
        break;

    case 'session_create':
        $c = ai_get_runtime_conf($conf);
        $title = isset($_POST['title']) ? trim($_POST['title']) : '新对话';
        $id = $store->createSession($title, $c['model']);
        $store->pruneSessions($c['session_limit']);
        exit(json_encode(array('code' => 0, 'id' => $id, 'data' => $store->getSession($id)), JSON_UNESCAPED_UNICODE));
        break;

    case 'session_get':
        $id = intval(isset($_GET['id']) ? $_GET['id'] : (isset($_POST['id']) ? $_POST['id'] : 0));
        $c = ai_get_runtime_conf($conf);
        $session = $store->getSession($id);
        if (!$session || intval($session['status']) !== 1) exit(json_encode(array('code' => -1, 'msg' => '对话不存在')));
        $messages = $store->listMessages($id, $c['message_limit']);
        $logs = $store->listLogs(array('session_id' => $id, 'limit' => 100));
        exit(json_encode(array('code' => 0, 'session' => $session, 'messages' => $messages, 'logs' => $logs), JSON_UNESCAPED_UNICODE));
        break;

    case 'session_rename':
        $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        if (!$store->renameSession($id, $title)) exit(json_encode(array('code' => -1, 'msg' => '重命名失败')));
        exit(json_encode(array('code' => 0, 'msg' => '已重命名')));
        break;

    case 'session_delete':
        $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        $store->deleteSession($id);
        exit(json_encode(array('code' => 0, 'msg' => '已删除对话（操作日志仍保留）')));
        break;

    case 'logs':
        $opts = array(
            'session_id' => isset($_GET['session_id']) ? intval($_GET['session_id']) : 0,
            'tool_name' => isset($_GET['tool_name']) ? trim($_GET['tool_name']) : '',
            'ok' => isset($_GET['ok']) ? $_GET['ok'] : '',
            'keyword' => isset($_GET['keyword']) ? trim($_GET['keyword']) : '',
            'limit' => isset($_GET['limit']) ? intval($_GET['limit']) : 50,
        );
        $list = $store->listLogs($opts);
        exit(json_encode(array('code' => 0, 'data' => $list), JSON_UNESCAPED_UNICODE));
        break;

    case 'log_get':
        $id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
        $row = $store->getLog($id);
        if (!$row) exit(json_encode(array('code' => -1, 'msg' => '日志不存在')));
        exit(json_encode(array('code' => 0, 'data' => $row), JSON_UNESCAPED_UNICODE));
        break;

    case 'chat':
        $c = ai_get_runtime_conf($conf);
        if (!$c['enabled']) exit(json_encode(array('code' => -1, 'msg' => 'AI 未启用，请先在模型配置中开启')));
        if ($c['api_key'] === '') exit(json_encode(array('code' => -1, 'msg' => '请先配置 API Key')));

        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input)) $input = $_POST;
        $userMsg = isset($input['message']) ? trim($input['message']) : '';
        $sessionId = isset($input['session_id']) ? intval($input['session_id']) : 0;
        if ($userMsg === '') exit(json_encode(array('code' => -1, 'msg' => '消息不能为空')));

        if ($sessionId > 0) {
            $session = $store->getSession($sessionId);
            if (!$session || intval($session['status']) !== 1) {
                exit(json_encode(array('code' => -1, 'msg' => '对话不存在或已删除')));
            }
        } else {
            $title = mb_substr($userMsg, 0, 30);
            $sessionId = $store->createSession($title, $c['model']);
            $store->pruneSessions($c['session_limit']);
        }

        // 优先用库内历史，保证刷新后可续聊
        $messages = $store->historyForModel($sessionId, 20);
        $messages[] = array('role' => 'user', 'content' => $userMsg);
        $store->addMessage($sessionId, 'user', $userMsg, null, null);
        $store->touchSession($sessionId, array('inc_msg' => 1, 'model' => $c['model']));

        $requestId = 'ai_' . date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8);
        $ip = function_exists('real_ip') ? real_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
        $turnLogs = array();

        try {
            $client = new \lib\Ai\Client($c['api_base'], $c['api_key'], 180);
            $tools = new \lib\Ai\Tools($DB, $conf, $CACHE);
            $agent = new \lib\Ai\Agent($client, $tools, array(
                'model' => $c['model'],
                'temperature' => $c['temperature'],
                'max_tokens' => $c['max_tokens'],
                'system_prompt' => $c['system_prompt'],
                'max_rounds' => 8,
                'on_tool' => function ($item) use ($store, $sessionId, $c, $ip, $requestId, &$turnLogs) {
                    $ok = !empty($item['ok']);
                    $logId = $store->addLog(array(
                        'session_id' => $sessionId,
                        'message_id' => 0,
                        'tool_name' => $item['name'],
                        'tool_label' => isset($item['label']) ? $item['label'] : \lib\Ai\Store::toolLabel($item['name']),
                        'arguments' => $item['arguments'],
                        'result' => $item['result'],
                        'ok' => $ok,
                        'operator' => 'admin',
                        'ip' => $ip,
                        'model' => $c['model'],
                        'duration_ms' => isset($item['duration_ms']) ? $item['duration_ms'] : 0,
                        'request_id' => $requestId,
                    ));
                    $item['log_id'] = $logId;
                    $turnLogs[] = array(
                        'log_id' => $logId,
                        'name' => $item['name'],
                        'label' => isset($item['label']) ? $item['label'] : $item['name'],
                        'ok' => $ok,
                        'duration_ms' => isset($item['duration_ms']) ? $item['duration_ms'] : 0,
                        'arguments' => $item['arguments'],
                        'result' => $item['result'],
                    );
                    $store->touchSession($sessionId, array('inc_tool' => 1));
                },
            ));
            $result = $agent->run($messages);
            $assistantId = $store->addMessage($sessionId, 'assistant', $result['reply'], $result['tool_trace'], $result['usage']);
            $store->touchSession($sessionId, array('inc_msg' => 1));
            // 回填 message_id 到本轮日志（简化：仅记录在返回中；库内 message_id 保持 0 也可接受，或批量更新）
            if ($assistantId && $turnLogs) {
                $ids = array();
                foreach ($turnLogs as $tl) {
                    if (!empty($tl['log_id'])) $ids[] = intval($tl['log_id']);
                }
                if ($ids) {
                    $idList = implode(',', $ids);
                    $DB->exec("UPDATE pre_ai_log SET message_id='" . intval($assistantId) . "' WHERE id IN ($idList)");
                }
            }
            $store->pruneMessages($sessionId, $c['message_limit']);

            // 若仍是默认标题，用首句更新
            $session = $store->getSession($sessionId);
            if ($session && ($session['title'] === '新对话' || $session['msg_count'] <= 2)) {
                $store->touchSession($sessionId, array('title' => mb_substr($userMsg, 0, 30)));
            }

            exit(json_encode(array(
                'code' => 0,
                'session_id' => $sessionId,
                'reply' => $result['reply'],
                'tool_trace' => $result['tool_trace'],
                'turn_logs' => $turnLogs,
                'usage' => $result['usage'],
                'request_id' => $requestId,
            ), JSON_UNESCAPED_UNICODE));
        } catch (Exception $e) {
            $store->addLog(array(
                'session_id' => $sessionId,
                'tool_name' => 'system_error',
                'tool_label' => '系统错误',
                'arguments' => array('message' => $userMsg),
                'result' => array('ok' => false, 'error' => $e->getMessage()),
                'ok' => false,
                'operator' => 'admin',
                'ip' => $ip,
                'model' => $c['model'],
                'request_id' => $requestId,
            ));
            $store->addMessage($sessionId, 'assistant', '错误：' . $e->getMessage(), null, null);
            exit(json_encode(array('code' => -1, 'msg' => $e->getMessage(), 'session_id' => $sessionId, 'request_id' => $requestId)));
        }
        break;

    case 'tools':
        $tools = new \lib\Ai\Tools($DB, $conf, $CACHE);
        $defs = $tools->definitions();
        $names = array();
        foreach ($defs as $d) {
            $n = $d['function']['name'];
            $names[] = array(
                'name' => $n,
                'label' => \lib\Ai\Store::toolLabel($n),
                'description' => $d['function']['description'],
            );
        }
        exit(json_encode(array('code' => 0, 'data' => $names), JSON_UNESCAPED_UNICODE));
        break;

    default:
        exit(json_encode(array('code' => -1, 'msg' => 'unknown act')));
}
