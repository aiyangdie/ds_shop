<?php
/**
 * 外部 AI / Agent 调用入口（无需登录后台页面）
 * Authorization: Bearer {ai_api_token}
 * POST JSON: {"message":"...","history":[...]}
 */
$nosession = true;
include("./includes/common.php");
@header('Content-Type: application/json; charset=UTF-8');
@header('Access-Control-Allow-Origin: *');
@header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

function ai_api_bearer()
{
    $h = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) $h = $_SERVER['HTTP_AUTHORIZATION'];
    elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $h = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'authorization') {
                $h = $v;
                break;
            }
        }
    }
    if (stripos($h, 'Bearer ') === 0) {
        return trim(substr($h, 7));
    }
    if (!empty($_GET['token'])) return trim($_GET['token']);
    if (!empty($_POST['token'])) return trim($_POST['token']);
    return '';
}

$token = ai_api_bearer();
$expect = isset($conf['ai_api_token']) ? $conf['ai_api_token'] : '';
if ($expect === '' || $token === '' || !hash_equals($expect, $token)) {
    http_response_code(401);
    exit(json_encode(array('code' => 401, 'msg' => '无效或未配置 ai_api_token')));
}

if (empty($conf['ai_enabled']) || intval($conf['ai_enabled']) !== 1) {
    exit(json_encode(array('code' => -1, 'msg' => 'AI 未启用')));
}
if (empty($conf['ai_api_key'])) {
    exit(json_encode(array('code' => -1, 'msg' => '未配置模型 API Key')));
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) $input = $_POST;
$act = isset($_GET['act']) ? $_GET['act'] : (isset($input['act']) ? $input['act'] : 'chat');

if ($act === 'tools') {
    $tools = new \lib\Ai\Tools($DB, $conf, $CACHE);
    $defs = $tools->definitions();
    $names = array();
    foreach ($defs as $d) {
        $names[] = array(
            'name' => $d['function']['name'],
            'description' => $d['function']['description'],
        );
    }
    exit(json_encode(array('code' => 0, 'data' => $names), JSON_UNESCAPED_UNICODE));
}

if ($act === 'tool') {
    $name = isset($input['name']) ? $input['name'] : '';
    $args = isset($input['arguments']) && is_array($input['arguments']) ? $input['arguments'] : array();
    if ($name === '') exit(json_encode(array('code' => -1, 'msg' => 'name 必填')));
    $tools = new \lib\Ai\Tools($DB, $conf, $CACHE);
    $result = $tools->execute($name, $args);
    exit(json_encode(array('code' => 0, 'result' => $result), JSON_UNESCAPED_UNICODE));
}

$userMsg = isset($input['message']) ? trim($input['message']) : '';
$history = isset($input['history']) && is_array($input['history']) ? $input['history'] : array();
if ($userMsg === '') {
    exit(json_encode(array('code' => -1, 'msg' => 'message 不能为空。也可用 act=tools / act=tool 直接调工具。')));
}

$messages = array();
foreach ($history as $h) {
    if (!isset($h['role'], $h['content'])) continue;
    if ($h['role'] !== 'user' && $h['role'] !== 'assistant') continue;
    $messages[] = array('role' => $h['role'], 'content' => strval($h['content']));
}
$messages[] = array('role' => 'user', 'content' => $userMsg);

try {
    $client = new \lib\Ai\Client(
        isset($conf['ai_api_base']) ? $conf['ai_api_base'] : '',
        $conf['ai_api_key'],
        180
    );
    $tools = new \lib\Ai\Tools($DB, $conf, $CACHE);
    $agent = new \lib\Ai\Agent($client, $tools, array(
        'model' => isset($conf['ai_model']) ? $conf['ai_model'] : 'deepseek-chat',
        'temperature' => isset($conf['ai_temperature']) ? floatval($conf['ai_temperature']) : 0.2,
        'max_tokens' => isset($conf['ai_max_tokens']) ? intval($conf['ai_max_tokens']) : 4096,
        'system_prompt' => isset($conf['ai_system_prompt']) ? $conf['ai_system_prompt'] : '',
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
