<?php
include("../includes/common.php");
@header('Content-Type: application/json; charset=UTF-8');

if ($islogin2 != 1) {
    exit(json_encode(array('code' => -1, 'msg' => '请先登录用户中心')));
}
if (!checkRefererHost()) {
    exit(json_encode(array('code' => 403, 'msg' => 'forbidden')));
}

$act = isset($_GET['act']) ? daddslashes($_GET['act']) : (isset($_POST['act']) ? daddslashes($_POST['act']) : null);
$aiOn = !empty($conf['ai_enabled']) && intval($conf['ai_enabled']) === 1;
$userOn = !isset($conf['ai_user_enabled']) || intval($conf['ai_user_enabled']) === 1;
$channel = 'user';
$ownerId = strval($userrow['zid']);
$power = intval($userrow['power']);

function user_ai_conf($conf)
{
    return array(
        'enabled' => !empty($conf['ai_enabled']) && intval($conf['ai_enabled']) === 1,
        'user_enabled' => !isset($conf['ai_user_enabled']) || intval($conf['ai_user_enabled']) === 1,
        'api_base' => isset($conf['ai_api_base']) ? $conf['ai_api_base'] : '',
        'api_key' => isset($conf['ai_api_key']) ? $conf['ai_api_key'] : '',
        'model' => isset($conf['ai_model']) ? $conf['ai_model'] : 'deepseek-chat',
        'temperature' => isset($conf['ai_temperature']) ? floatval($conf['ai_temperature']) : 0.2,
        'max_tokens' => isset($conf['ai_max_tokens']) ? intval($conf['ai_max_tokens']) : 4096,
        'session_limit' => isset($conf['ai_session_limit']) ? intval($conf['ai_session_limit']) : 40,
        'message_limit' => isset($conf['ai_message_limit']) ? intval($conf['ai_message_limit']) : 80,
        'user_prompt' => isset($conf['ai_user_prompt']) ? $conf['ai_user_prompt'] : '',
        'sitename' => isset($conf['sitename']) && $conf['sitename'] !== '' ? $conf['sitename'] : '本站',
        'assistant_name' => !empty($conf['ai_assistant_name']) ? $conf['ai_assistant_name'] : '助手',
    );
}

$store = new \lib\Ai\Store($DB);
try {
    $store->ensureSchema();
} catch (Exception $e) {
    exit(json_encode(array('code' => -1, 'msg' => '初始化失败：' . $e->getMessage())));
}

switch ($act) {
    case 'sessions':
        $c = user_ai_conf($conf);
        $list = $store->listSessions($c['session_limit'], $channel, $ownerId);
        exit(json_encode(array('code' => 0, 'data' => $list), JSON_UNESCAPED_UNICODE));

    case 'session_get':
        $id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
        $c = user_ai_conf($conf);
        $session = $store->getSession($id, $channel, $ownerId);
        if (!$session || intval($session['status']) !== 1) exit(json_encode(array('code' => -1, 'msg' => '对话不存在')));
        $messages = $store->listMessages($id, $c['message_limit']);
        // 用户端不暴露完整日志参数
        $logs = array();
        foreach ($store->listLogs(array('session_id' => $id, 'limit' => 50)) as $l) {
            $logs[] = array(
                'id' => $l['id'],
                'tool_name' => $l['tool_name'],
                'tool_label' => $l['tool_label'],
                'ok' => $l['ok'],
                'duration_ms' => $l['duration_ms'],
                'addtime' => $l['addtime'],
            );
        }
        exit(json_encode(array('code' => 0, 'session' => $session, 'messages' => $messages, 'logs' => $logs), JSON_UNESCAPED_UNICODE));

    case 'session_rename':
        $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        if (!$store->renameSession($id, $title, $channel, $ownerId)) exit(json_encode(array('code' => -1, 'msg' => '重命名失败')));
        exit(json_encode(array('code' => 0, 'msg' => '已重命名')));

    case 'session_delete':
        $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        $store->deleteSession($id, $channel, $ownerId);
        exit(json_encode(array('code' => 0, 'msg' => '已删除')));

    case 'logs':
        $sid = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
        if ($sid > 0 && !$store->getSession($sid, $channel, $ownerId)) {
            exit(json_encode(array('code' => -1, 'msg' => '对话不存在')));
        }
        $raw = $store->listLogs(array('session_id' => $sid, 'limit' => 50));
        $list = array();
        foreach ($raw as $l) {
            $list[] = array(
                'id' => $l['id'],
                'tool_name' => $l['tool_name'],
                'tool_label' => $l['tool_label'],
                'ok' => $l['ok'],
                'duration_ms' => $l['duration_ms'],
                'addtime' => $l['addtime'],
            );
        }
        exit(json_encode(array('code' => 0, 'data' => $list), JSON_UNESCAPED_UNICODE));

    case 'log_get':
        exit(json_encode(array('code' => -1, 'msg' => '用户端不提供日志详情')));

    case 'chat':
        $c = user_ai_conf($conf);
        if (!$c['enabled'] || !$c['user_enabled']) exit(json_encode(array('code' => -1, 'msg' => '用户端 AI 未启用')));
        if ($c['api_key'] === '') exit(json_encode(array('code' => -1, 'msg' => '站点未配置 AI')));

        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input)) $input = $_POST;
        $userMsg = isset($input['message']) ? trim($input['message']) : '';
        $sessionId = isset($input['session_id']) ? intval($input['session_id']) : 0;
        if ($userMsg === '') exit(json_encode(array('code' => -1, 'msg' => '消息不能为空')));

        if ($sessionId > 0) {
            $session = $store->getSession($sessionId, $channel, $ownerId);
            if (!$session || intval($session['status']) !== 1) {
                exit(json_encode(array('code' => -1, 'msg' => '对话不存在或已删除')));
            }
        } else {
            $sessionId = $store->createSession(mb_substr($userMsg, 0, 30), $c['model'], $channel, $ownerId);
            $store->pruneSessions($c['session_limit'], $channel, $ownerId);
        }

        $messages = $store->historyForModel($sessionId, 20);
        $messages[] = array('role' => 'user', 'content' => $userMsg);
        $store->addMessage($sessionId, 'user', $userMsg, null, null);
        $store->touchSession($sessionId, array('inc_msg' => 1, 'model' => $c['model']));

        $requestId = 'uai_' . date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8);
        $ip = function_exists('real_ip') ? real_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
        $turnLogs = array();
        $operator = 'user:' . $ownerId;

        try {
            $client = new \lib\Ai\Client($c['api_base'], $c['api_key'], 180);
            $tools = new \lib\Ai\Tools($DB, $conf, $CACHE, array(
                'scope' => 'user',
                'actor' => array(
                    'zid' => intval($userrow['zid']),
                    'power' => $power,
                ),
            ));
            $siteName = !empty($userrow['sitename']) ? $userrow['sitename'] : $c['sitename'];
            $agent = new \lib\Ai\Agent($client, $tools, array(
                'model' => $c['model'],
                'temperature' => $c['temperature'],
                'max_tokens' => min(2048, $c['max_tokens']),
                'system_prompt' => $c['user_prompt'],
                'role' => 'user',
                'site' => array(
                    'sitename' => $siteName,
                    'assistant_name' => $c['assistant_name'],
                    'power' => $power,
                ),
                'max_rounds' => 6,
                'on_tool' => function ($item) use ($store, $sessionId, $c, $ip, $requestId, $operator, &$turnLogs) {
                    $ok = !empty($item['ok']);
                    $logId = $store->addLog(array(
                        'session_id' => $sessionId,
                        'tool_name' => $item['name'],
                        'tool_label' => isset($item['label']) ? $item['label'] : \lib\Ai\Store::toolLabel($item['name']),
                        'arguments' => $item['arguments'],
                        'result' => $item['result'],
                        'ok' => $ok,
                        'operator' => $operator,
                        'ip' => $ip,
                        'model' => $c['model'],
                        'duration_ms' => isset($item['duration_ms']) ? $item['duration_ms'] : 0,
                        'request_id' => $requestId,
                    ));
                    $turnLogs[] = array(
                        'log_id' => $logId,
                        'name' => $item['name'],
                        'label' => isset($item['label']) ? $item['label'] : $item['name'],
                        'ok' => $ok,
                        'duration_ms' => isset($item['duration_ms']) ? $item['duration_ms'] : 0,
                    );
                    $store->touchSession($sessionId, array('inc_tool' => 1));
                },
            ));
            $result = $agent->run($messages);
            // 用户端不回传完整 tool 结果细节
            $safeTrace = array();
            foreach ($result['tool_trace'] as $t) {
                $safeTrace[] = array(
                    'name' => $t['name'],
                    'label' => $t['label'],
                    'ok' => $t['ok'],
                    'duration_ms' => $t['duration_ms'],
                );
            }
            $store->addMessage($sessionId, 'assistant', $result['reply'], $safeTrace, $result['usage']);
            $store->touchSession($sessionId, array('inc_msg' => 1));
            $store->pruneMessages($sessionId, $c['message_limit']);
            $session = $store->getSession($sessionId, $channel, $ownerId);
            if ($session && ($session['title'] === '新对话' || $session['msg_count'] <= 2)) {
                $store->touchSession($sessionId, array('title' => mb_substr($userMsg, 0, 30)));
            }
            exit(json_encode(array(
                'code' => 0,
                'session_id' => $sessionId,
                'reply' => $result['reply'],
                'tool_trace' => $safeTrace,
                'turn_logs' => $turnLogs,
                'usage' => $result['usage'],
                'request_id' => $requestId,
                'site' => array('sitename' => $siteName, 'assistant_name' => $c['assistant_name']),
            ), JSON_UNESCAPED_UNICODE));
        } catch (Exception $e) {
            $store->addMessage($sessionId, 'assistant', '错误：' . $e->getMessage(), null, null);
            exit(json_encode(array('code' => -1, 'msg' => $e->getMessage(), 'session_id' => $sessionId)));
        }

    default:
        exit(json_encode(array('code' => -1, 'msg' => 'unknown act')));
}
