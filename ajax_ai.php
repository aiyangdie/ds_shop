<?php
/**
 * 前台 AI 客服入口（访客）
 */
include("./includes/common.php");
@header('Content-Type: application/json; charset=UTF-8');

if (!checkRefererHost()) {
    exit(json_encode(array('code' => 403, 'msg' => 'forbidden')));
}

$act = isset($_GET['act']) ? daddslashes($_GET['act']) : (isset($_POST['act']) ? daddslashes($_POST['act']) : 'bootstrap');
$aiOn = !empty($conf['ai_enabled']) && intval($conf['ai_enabled']) === 1;
$shopOn = !isset($conf['ai_shop_enabled']) || intval($conf['ai_shop_enabled']) === 1;
$channel = 'shop';

// 稳定访客身份：独立 cookie，避免 cookiesid/session 变化导致历史丢了
function shop_ai_guest_id()
{
    $name = 'ai_guest';
    $gid = isset($_COOKIE[$name]) ? preg_replace('/[^a-zA-Z0-9]/', '', strval($_COOKIE[$name])) : '';
    if ($gid === '' || strlen($gid) < 16) {
        try {
            $gid = bin2hex(random_bytes(16));
        } catch (Exception $e) {
            $gid = md5(uniqid((isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') . mt_rand(), true));
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie($name, $gid, time() + 86400 * 400, '/', '', $secure, true);
        $_COOKIE[$name] = $gid;
    }
    return 'g:' . substr($gid, 0, 32);
}

$ownerId = shop_ai_guest_id();
$cookiesid = isset($cookiesid) ? strval($cookiesid) : '';


function shop_ai_conf($conf)
{
    return array(
        'enabled' => !empty($conf['ai_enabled']) && intval($conf['ai_enabled']) === 1,
        'shop_enabled' => !isset($conf['ai_shop_enabled']) || intval($conf['ai_shop_enabled']) === 1,
        'api_base' => isset($conf['ai_api_base']) ? $conf['ai_api_base'] : '',
        'api_key' => isset($conf['ai_api_key']) ? $conf['ai_api_key'] : '',
        'model' => isset($conf['ai_model']) ? $conf['ai_model'] : 'deepseek-chat',
        'temperature' => isset($conf['ai_temperature']) ? floatval($conf['ai_temperature']) : 0.3,
        'max_tokens' => isset($conf['ai_max_tokens']) ? min(2048, intval($conf['ai_max_tokens'])) : 2048,
        'session_limit' => 20,
        'message_limit' => 40,
        'shop_rate' => isset($conf['ai_shop_rate']) ? max(5, min(60, intval($conf['ai_shop_rate']))) : 20,
        'shop_daily' => isset($conf['ai_shop_daily']) ? max(10, min(500, intval($conf['ai_shop_daily']))) : 100,
        'shop_prompt' => isset($conf['ai_shop_prompt']) ? $conf['ai_shop_prompt'] : '',
        'sitename' => isset($conf['sitename']) && $conf['sitename'] !== '' ? $conf['sitename'] : '本站',
        'assistant_name' => !empty($conf['ai_assistant_name']) ? $conf['ai_assistant_name'] : '助手',
        'kfqq' => isset($conf['kfqq']) ? $conf['kfqq'] : '',
    );
}

function shop_ai_rate_info($rate)
{
    $ip = function_exists('real_ip') ? real_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0');
    $key = 'ai_shop_rate_' . md5($ip);
    $now = time();
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = array();
    }
    $arr = array();
    foreach ($_SESSION[$key] as $t) {
        if ($now - intval($t) < 60) $arr[] = intval($t);
    }
    $_SESSION[$key] = $arr;
    $used = count($arr);
    return array(
        'ok' => $used < $rate,
        'used' => $used,
        'limit' => $rate,
        'remain' => max(0, $rate - $used),
        'key' => $key,
    );
}

function shop_ai_rate_hit($key)
{
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) $_SESSION[$key] = array();
    $_SESSION[$key][] = time();
}

/** 日对话次数（按 guest + IP） */
function shop_ai_daily_info($guestId, $limit)
{
    $ip = function_exists('real_ip') ? real_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0');
    $day = date('Ymd');
    $key = 'ai_shop_daily_' . $day . '_' . md5($guestId . '|' . $ip);
    $used = isset($_SESSION[$key]) ? intval($_SESSION[$key]) : 0;
    return array(
        'ok' => $used < $limit,
        'used' => $used,
        'limit' => $limit,
        'remain' => max(0, $limit - $used),
        'key' => $key,
    );
}

function shop_ai_daily_hit($key)
{
    if (!isset($_SESSION[$key])) $_SESSION[$key] = 0;
    $_SESSION[$key] = intval($_SESSION[$key]) + 1;
}

/** 转人工防刷 */
function shop_ai_handoff_guard($DB, $guestId, $problem)
{
    $ip = function_exists('real_ip') ? real_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0');
    $g = addslashes($guestId);
    $ipSql = addslashes($ip);
    $recent = $DB->getRow("SELECT id,problem,addtime FROM pre_ai_cs WHERE guest_id='$g' AND status<2 ORDER BY id DESC LIMIT 1");
    if ($recent) {
        $ago = time() - strtotime($recent['addtime']);
        if ($ago < 600) {
            return array('ok' => false, 'msg' => '你有未完结工单 #' . $recent['id'] . '，请等待处理（10分钟内勿重复提交）', 'cs_id' => intval($recent['id']));
        }
        $p1 = mb_substr(preg_replace('/\s+/', '', $problem), 0, 40);
        $p0 = mb_substr(preg_replace('/\s+/', '', strval($recent['problem'])), 0, 40);
        if ($p1 !== '' && $p1 === $p0) {
            return array('ok' => false, 'msg' => '相同问题已提交为工单 #' . $recent['id'] . '，请勿重复', 'cs_id' => intval($recent['id']));
        }
    }
    $dayGuest = intval($DB->getColumn("SELECT count(*) FROM pre_ai_cs WHERE guest_id='$g' AND DATE(addtime)=CURDATE()"));
    if ($dayGuest >= 5) {
        return array('ok' => false, 'msg' => '今日转人工次数已达上限，请明天再试或联系客服QQ');
    }
    $dayIp = intval($DB->getColumn("SELECT count(*) FROM pre_ai_cs WHERE ip='$ipSql' AND DATE(addtime)=CURDATE()"));
    if ($dayIp >= 20) {
        return array('ok' => false, 'msg' => '当前网络转人工过于频繁，请稍后再试');
    }
    $coolKey = 'ai_handoff_cool_' . md5($guestId);
    if (!empty($_SESSION[$coolKey]) && time() - intval($_SESSION[$coolKey]) < 600) {
        return array('ok' => false, 'msg' => '提交过于频繁，请稍后再试');
    }
    return array('ok' => true, 'cool_key' => $coolKey);
}

$c = shop_ai_conf($conf);

if ($act === 'bootstrap') {
    $kfqq = $c['kfqq'];
    $kfUrl = './?mod=kf';
    if ($kfqq !== '') {
        $kfUrl = 'https://wpa.qq.com/msgrd?v=3&uin=' . urlencode($kfqq) . '&site=qq&menu=yes';
    }
    exit(json_encode(array(
        'code' => 0,
        'data' => array(
            'on' => $aiOn && $shopOn && $c['api_key'] !== '',
            'sitename' => $c['sitename'],
            'assistant' => $c['assistant_name'],
            'kfqq' => $kfqq,
            'kf_url' => $kfUrl,
            'rate_limit' => $c['shop_rate'],
            'handoff' => true,
            'aftersale' => true,
            'welcome' => '你好，我是' . $c['assistant_name'] . '。可以帮你解释商品、推荐、说明怎么填下单信息、查订单和售后自助。解决不了可转人工。刷新后仍可继续上次对话。',
        ),
    ), JSON_UNESCAPED_UNICODE));
}

if (!$aiOn || !$shopOn) {
    exit(json_encode(array('code' => -1, 'msg' => '前台 AI 客服未启用')));
}
if ($c['api_key'] === '') {
    exit(json_encode(array('code' => -1, 'msg' => '站点未配置 AI')));
}

$store = new \lib\Ai\Store($DB);
try {
    $store->ensureSchema();
} catch (Exception $e) {
    exit(json_encode(array('code' => -1, 'msg' => '初始化失败')));
}

if ($act === 'history') {
    $sessionId = intval(isset($_GET['session_id']) ? $_GET['session_id'] : (isset($_POST['session_id']) ? $_POST['session_id'] : 0));
    if ($sessionId <= 0) {
        exit(json_encode(array('code' => 0, 'session_id' => 0, 'messages' => array())));
    }
    $session = $store->getSession($sessionId, $channel, $ownerId);
    if (!$session || intval($session['status']) !== 1) {
        exit(json_encode(array('code' => 0, 'session_id' => 0, 'messages' => array(), 'msg' => '会话已失效')));
    }
    $msgs = $store->listMessages($sessionId, $c['message_limit']);
    $out = array();
    foreach ($msgs as $m) {
        if ($m['role'] !== 'user' && $m['role'] !== 'assistant') continue;
        $out[] = array(
            'role' => $m['role'],
            'content' => strval($m['content']),
            'addtime' => isset($m['addtime']) ? $m['addtime'] : '',
        );
    }
    exit(json_encode(array(
        'code' => 0,
        'session_id' => $sessionId,
        'messages' => $out,
    ), JSON_UNESCAPED_UNICODE));
}

if ($act === 'my_cs') {
    $mine = $DB->getAll("SELECT id,problem,status,reply,addtime,updatetime,order_no FROM pre_ai_cs WHERE guest_id='" . addslashes($ownerId) . "' ORDER BY id DESC LIMIT 10");
    if (!$mine) $mine = array();
    $hasUnread = false;
    foreach ($mine as $row) {
        if (intval($row['status']) >= 1 && !empty($row['reply'])) {
            $hasUnread = true;
            break;
        }
    }
    exit(json_encode(array('code' => 0, 'data' => $mine, 'has_reply' => $hasUnread), JSON_UNESCAPED_UNICODE));
}

if ($act === 'handoff') {
    $rateInfo = shop_ai_rate_info($c['shop_rate']);
    if (!$rateInfo['ok']) {
        exit(json_encode(array('code' => -1, 'msg' => '操作太频繁，请稍后再试')));
    }
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) $input = $_POST;
    $contact = isset($input['contact']) ? trim(strval($input['contact'])) : '';
    $problem = isset($input['problem']) ? trim(strval($input['problem'])) : '';
    $orderNo = isset($input['order_no']) ? trim(strval($input['order_no'])) : '';
    $sessionId = isset($input['session_id']) ? intval($input['session_id']) : 0;
    $summary = isset($input['summary']) ? mb_substr(trim(strval($input['summary'])), 0, 800) : '';
    // 联系方式改为可选（轻量），问题可空则用「请求人工」
    if ($problem === '') $problem = '请求人工客服';
    if ($sessionId > 0) {
        $session = $store->getSession($sessionId, $channel, $ownerId);
        if (!$session) $sessionId = 0;
    }
    if ($summary === '' && $sessionId > 0) {
        $msgs = $store->listMessages($sessionId, 8);
        $parts = array();
        foreach ($msgs as $m) {
            if ($m['role'] !== 'user' && $m['role'] !== 'assistant') continue;
            $parts[] = ($m['role'] === 'user' ? '用户' : '助手') . '：' . mb_substr(strval($m['content']), 0, 120);
        }
        $summary = implode("\n", $parts);
    }
    shop_ai_rate_hit($rateInfo['key']);
    $ip = function_exists('real_ip') ? real_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    $zid = 0;
    if (!empty($islogin2) && !empty($userrow['zid'])) {
        $zid = intval($userrow['zid']);
    }
    $ticket = $store->openCsThread(array(
        'session_id' => $sessionId,
        'channel' => 'shop',
        'contact' => $contact,
        'order_no' => $orderNo,
        'problem' => $problem,
        'summary' => $summary,
        'guest_id' => $ownerId,
        'zid' => $zid,
        'ip' => $ip,
    ));
    if (!$ticket) {
        exit(json_encode(array('code' => -1, 'msg' => '打开会话失败')));
    }
    if ($sessionId > 0) {
        $store->addMessage($sessionId, 'assistant', '已进入人工客服会话 #' . $ticket['id'] . '，可直接发文字或图片。', null, null);
    }
    $messages = $store->listCsMessages(intval($ticket['id']), 0, 50);
    exit(json_encode(array(
        'code' => 0,
        'msg' => '已进入客服会话',
        'cs_id' => intval($ticket['id']),
        'ticket' => array(
            'id' => intval($ticket['id']),
            'status' => intval($ticket['status']),
            'contact' => $ticket['contact'],
            'staff_joined' => intval(isset($ticket['staff_joined']) ? $ticket['staff_joined'] : 0),
            'ai_paused' => intval(isset($ticket['ai_paused']) ? $ticket['ai_paused'] : 0),
        ),
        'messages' => $messages,
    ), JSON_UNESCAPED_UNICODE));
}

if ($act === 'cs_open') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) $input = $_POST;
    $contact = isset($input['contact']) ? trim(strval($input['contact'])) : '';
    $problem = isset($input['problem']) ? trim(strval($input['problem'])) : '进入在线客服';
    $orderNo = isset($input['order_no']) ? trim(strval($input['order_no'])) : '';
    $sessionId = isset($input['session_id']) ? intval($input['session_id']) : 0;
    $ip = function_exists('real_ip') ? real_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    $zid = (!empty($islogin2) && !empty($userrow['zid'])) ? intval($userrow['zid']) : 0;
    $ticket = $store->openCsThread(array(
        'session_id' => $sessionId,
        'channel' => 'shop',
        'contact' => $contact,
        'order_no' => $orderNo,
        'problem' => $problem,
        'guest_id' => $ownerId,
        'zid' => $zid,
        'ip' => $ip,
    ));
    if (!$ticket) exit(json_encode(array('code' => -1, 'msg' => '打开失败')));
    $store->migrateLegacyCsMessages($ticket);
    $messages = $store->listCsMessages(intval($ticket['id']), 0, 100);
    exit(json_encode(array(
        'code' => 0,
        'cs_id' => intval($ticket['id']),
        'ticket' => array(
            'id' => intval($ticket['id']),
            'status' => intval($ticket['status']),
            'contact' => $ticket['contact'],
            'staff_joined' => intval(isset($ticket['staff_joined']) ? $ticket['staff_joined'] : 0),
            'ai_paused' => intval(isset($ticket['ai_paused']) ? $ticket['ai_paused'] : 0),
        ),
        'messages' => $messages,
    ), JSON_UNESCAPED_UNICODE));
}

if ($act === 'cs_poll') {
    $csId = intval(isset($_GET['cs_id']) ? $_GET['cs_id'] : (isset($_POST['cs_id']) ? $_POST['cs_id'] : 0));
    $afterId = intval(isset($_GET['after_id']) ? $_GET['after_id'] : (isset($_POST['after_id']) ? $_POST['after_id'] : 0));
    $ticket = $store->getCsForGuest($csId, $ownerId);
    if (!$ticket) exit(json_encode(array('code' => -1, 'msg' => '会话不存在')));
    $messages = $store->listCsMessages($csId, $afterId, 100);
    exit(json_encode(array(
        'code' => 0,
        'cs_id' => $csId,
        'ticket' => array(
            'id' => intval($ticket['id']),
            'status' => intval($ticket['status']),
            'staff_joined' => intval(isset($ticket['staff_joined']) ? $ticket['staff_joined'] : 0),
            'ai_paused' => intval(isset($ticket['ai_paused']) ? $ticket['ai_paused'] : 0),
        ),
        'messages' => $messages,
    ), JSON_UNESCAPED_UNICODE));
}

if ($act === 'cs_send') {
    $rateInfo = shop_ai_rate_info($c['shop_rate']);
    if (!$rateInfo['ok']) {
        exit(json_encode(array('code' => -1, 'msg' => '发送太频繁，请稍后再试')));
    }
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) $input = $_POST;
    $csId = isset($input['cs_id']) ? intval($input['cs_id']) : 0;
    $text = isset($input['message']) ? trim(strval($input['message'])) : '';
    $msgType = isset($input['msg_type']) ? strval($input['msg_type']) : 'text';
    $mediaUrl = isset($input['media_url']) ? trim(strval($input['media_url'])) : '';
    $ticket = $store->getCsForGuest($csId, $ownerId);
    if (!$ticket) exit(json_encode(array('code' => -1, 'msg' => '会话不存在')));
    if (intval($ticket['status']) === 2) {
        exit(json_encode(array('code' => -1, 'msg' => '会话已完结，请重新转人工')));
    }
    if ($msgType === 'image') {
        if ($mediaUrl === '' || !preg_match('#^https?://|^/#', $mediaUrl)) {
            exit(json_encode(array('code' => -1, 'msg' => '图片地址无效')));
        }
        shop_ai_rate_hit($rateInfo['key']);
        $mid = $store->addCsMessage($csId, 'user', 'image', $text !== '' ? $text : '[图片]', $mediaUrl, 0, '');
        exit(json_encode(array('code' => 0, 'msg_id' => $mid, 'messages' => $store->listCsMessages($csId, $mid - 1, 5)), JSON_UNESCAPED_UNICODE));
    }
    if ($text === '') exit(json_encode(array('code' => -1, 'msg' => '消息不能为空')));
    if (mb_strlen($text) > 2000) $text = mb_substr($text, 0, 2000);
    shop_ai_rate_hit($rateInfo['key']);
    $mid = $store->addCsMessage($csId, 'user', 'text', $text, '', 0, '');
    $outMsgs = $store->listCsMessages($csId, $mid - 1, 5);
    $ticket = $store->getCsTicket($csId);
    $intent = \lib\Ai\Intent::classify($text);
    $aiMsgs = array();

    if (\lib\Ai\Intent::shouldEscalate($intent)) {
        $store->addCsMessage($csId, 'system', 'text', '已转人工处理：' . ($intent === 'refund_complaint' ? '退款/投诉类问题需人工核实。' : '正在为你排队人工客服。') . '请稍候，也可继续补充说明或发图。', '', 0, 'system');
        $store->setCsAiPaused($csId, true);
        $aiMsgs = $store->listCsMessages($csId, $mid, 10);
    } elseif (\lib\Ai\Intent::shouldAiReply($intent, $ticket) && $c['api_key'] !== '') {
        try {
            $client = new \lib\Ai\Client($c['api_base'], $c['api_key'], 60);
            $ip = function_exists('real_ip') ? real_ip() : '';
            $tools = new \lib\Ai\Tools($DB, $conf, $CACHE, array(
                'scope' => 'shop',
                'actor' => array(
                    'guest_id' => $ownerId,
                    'session_id' => intval($ticket['session_id']),
                    'ip' => $ip,
                    'zid' => intval($ticket['zid']),
                    'cs_id' => $csId,
                ),
            ));
            $hist = $store->listCsMessages($csId, 0, 12);
            $messages = array();
            foreach ($hist as $hm) {
                if ($hm['role'] === 'user') $messages[] = array('role' => 'user', 'content' => strval($hm['content']));
                elseif ($hm['role'] === 'ai' || $hm['role'] === 'staff') $messages[] = array('role' => 'assistant', 'content' => strval($hm['content']));
            }
            if (!$messages) $messages[] = array('role' => 'user', 'content' => $text);
            $agent = new \lib\Ai\Agent($client, $tools, array(
                'model' => $c['model'],
                'temperature' => $c['temperature'],
                'max_tokens' => min(1024, $c['max_tokens']),
                'system_prompt' => ($c['shop_prompt'] ? $c['shop_prompt'] : '') . "\n你在人工客服会话中辅助回答。简洁。退款投诉请提示等待人工。不要催用户填大表单。",
                'role' => 'shop',
                'site' => array(
                    'sitename' => $c['sitename'],
                    'assistant_name' => $c['assistant_name'],
                    'kfqq' => $c['kfqq'],
                ),
                'max_rounds' => 3,
            ));
            $result = $agent->run($messages);
            $reply = isset($result['reply']) ? trim(strval($result['reply'])) : '';
            if ($reply !== '') {
                $store->addCsMessage($csId, 'ai', 'text', $reply, '', 0, 'ai');
            }
            $aiMsgs = $store->listCsMessages($csId, $mid, 10);
        } catch (Exception $e) {
            $store->addCsMessage($csId, 'system', 'text', '助手暂时繁忙，已通知人工客服，请稍候。', '', 0, 'system');
            $aiMsgs = $store->listCsMessages($csId, $mid, 10);
        }
    }

    exit(json_encode(array(
        'code' => 0,
        'msg_id' => $mid,
        'intent' => $intent,
        'messages' => array_merge($outMsgs, $aiMsgs),
        'ticket' => array(
            'id' => $csId,
            'status' => intval($store->getCsTicket($csId)['status']),
            'staff_joined' => intval($store->getCsTicket($csId)['staff_joined']),
            'ai_paused' => intval($store->getCsTicket($csId)['ai_paused']),
        ),
    ), JSON_UNESCAPED_UNICODE));
}

if ($act === 'cs_upload') {
    if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        exit(json_encode(array('code' => -1, 'msg' => '请选择图片')));
    }
    $file = $_FILES['file'];
    if (!empty($file['error'])) {
        exit(json_encode(array('code' => -1, 'msg' => '上传错误 ' . intval($file['error']))));
    }
    $siteRoot = defined('ROOT') ? ROOT : (dirname(__FILE__) . DIRECTORY_SEPARATOR);
    $siteUrl = '';
    if (!empty($conf['localurl'])) {
        $siteUrl = rtrim($conf['localurl'], '/');
    } elseif (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $siteUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
    }
    $mgr = new \lib\Storage\Manager($conf, $siteRoot, $siteUrl);
    $res = $mgr->uploadCsImage($file['tmp_name'], isset($file['name']) ? $file['name'] : 'img.jpg', isset($file['type']) ? $file['type'] : '');
    if (empty($res['ok'])) {
        exit(json_encode(array('code' => -1, 'msg' => isset($res['error']) ? $res['error'] : '上传失败')));
    }
    $csId = intval(isset($_POST['cs_id']) ? $_POST['cs_id'] : 0);
    $msgId = 0;
    if ($csId > 0) {
        $ticket = $store->getCsForGuest($csId, $ownerId);
        if ($ticket && intval($ticket['status']) !== 2) {
            $msgId = $store->addCsMessage($csId, 'user', 'image', '[图片]', $res['url'], isset($res['size']) ? intval($res['size']) : 0, '');
        }
    }
    exit(json_encode(array(
        'code' => 0,
        'url' => $res['url'],
        'size' => isset($res['size']) ? intval($res['size']) : 0,
        'driver' => isset($res['driver']) ? $res['driver'] : 'local',
        'msg_id' => $msgId,
        'cs_id' => $csId,
    ), JSON_UNESCAPED_UNICODE));
}

if ($act === 'chat') {
    $rateInfo = shop_ai_rate_info($c['shop_rate']);
    if (!$rateInfo['ok']) {
        exit(json_encode(array('code' => -1, 'msg' => '提问太频繁，请稍后再试（每分钟最多' . $c['shop_rate'] . '次）', 'rate' => $rateInfo)));
    }
    $dailyInfo = shop_ai_daily_info($ownerId, $c['shop_daily']);
    if (!$dailyInfo['ok']) {
        exit(json_encode(array('code' => -1, 'msg' => '今日对话次数已用完（每日最多' . $c['shop_daily'] . '次），请明天再试或转人工/联系客服')));
    }
    $failKey = 'ai_shop_fail_' . md5($ownerId);
    if (!empty($_SESSION[$failKey]) && is_array($_SESSION[$failKey])) {
        $fails = array();
        $now = time();
        foreach ($_SESSION[$failKey] as $t) {
            if ($now - intval($t) < 300) $fails[] = intval($t);
        }
        $_SESSION[$failKey] = $fails;
        if (count($fails) >= 5) {
            exit(json_encode(array('code' => -1, 'msg' => '客服暂时繁忙，请5分钟后再试或转人工')));
        }
    }
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) $input = $_POST;
    $userMsg = isset($input['message']) ? trim($input['message']) : '';
    $sessionId = isset($input['session_id']) ? intval($input['session_id']) : 0;
    $context = isset($input['context']) && is_array($input['context']) ? $input['context'] : array();
    $tid = isset($context['tid']) ? intval($context['tid']) : 0;
    $cid = isset($context['cid']) ? intval($context['cid']) : 0;
    $tname = isset($context['tname']) ? mb_substr(trim(strval($context['tname'])), 0, 80) : '';
    if ($userMsg === '') exit(json_encode(array('code' => -1, 'msg' => '消息不能为空')));
    if (mb_strlen($userMsg) > 1000) $userMsg = mb_substr($userMsg, 0, 1000);

    shop_ai_rate_hit($rateInfo['key']);
    shop_ai_daily_hit($dailyInfo['key']);

    if ($sessionId > 0) {
        $session = $store->getSession($sessionId, $channel, $ownerId);
        if (!$session || intval($session['status']) !== 1) {
            $sessionId = 0;
        }
    }
    if ($sessionId <= 0) {
        $sessionId = $store->createSession(mb_substr($userMsg, 0, 30), $c['model'], $channel, $ownerId, array('tid' => $tid, 'cid' => $cid));
        $store->pruneSessions($c['session_limit'], $channel, $ownerId);
    } else {
        $store->touchSession($sessionId, array('context' => array('tid' => $tid, 'cid' => $cid)));
    }

    $messages = $store->historyForModel($sessionId, 12);
    $prefix = '';
    if ($tid > 0) $prefix .= '[当前商品tid=' . $tid . ($tname !== '' ? (' 名称=' . $tname) : '') . '] ';
    if ($cid > 0) $prefix .= '[当前分类cid=' . $cid . '] ';
    $messages[] = array('role' => 'user', 'content' => $prefix . $userMsg);
    $store->addMessage($sessionId, 'user', $userMsg, null, null);
    $store->touchSession($sessionId, array('inc_msg' => 1, 'model' => $c['model']));

    $requestId = 'sai_' . date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8);
    $ip = function_exists('real_ip') ? real_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    $operator = 'shop:' . $ip;

    try {
        $client = new \lib\Ai\Client($c['api_base'], $c['api_key'], 120);
        $zidChat = 0;
        if (!empty($islogin2) && !empty($userrow['zid'])) {
            $zidChat = intval($userrow['zid']);
        }
        $tools = new \lib\Ai\Tools($DB, $conf, $CACHE, array(
            'scope' => 'shop',
            'actor' => array(
                'cookiesid' => $cookiesid,
                'ip' => $ip,
                'guest_id' => $ownerId,
                'session_id' => $sessionId,
                'zid' => $zidChat,
                'context' => array('tid' => $tid, 'cid' => $cid, 'tname' => $tname),
            ),
        ));
        $agent = new \lib\Ai\Agent($client, $tools, array(
            'model' => $c['model'],
            'temperature' => $c['temperature'],
            'max_tokens' => $c['max_tokens'],
            'system_prompt' => $c['shop_prompt'],
            'role' => 'shop',
            'site' => array(
                'sitename' => $c['sitename'],
                'assistant_name' => $c['assistant_name'],
                'kfqq' => $c['kfqq'],
                'context_tid' => $tid,
            ),
            'max_rounds' => 5,
            'on_tool' => function ($item) use ($store, $sessionId, $c, $ip, $requestId, $operator) {
                $store->addLog(array(
                    'session_id' => $sessionId,
                    'tool_name' => $item['name'],
                    'tool_label' => isset($item['label']) ? $item['label'] : \lib\Ai\Store::toolLabel($item['name']),
                    'arguments' => $item['arguments'],
                    'result' => $item['result'],
                    'ok' => !empty($item['ok']),
                    'operator' => $operator,
                    'ip' => $ip,
                    'model' => $c['model'],
                    'duration_ms' => isset($item['duration_ms']) ? $item['duration_ms'] : 0,
                    'request_id' => $requestId,
                ));
                $store->touchSession($sessionId, array('inc_tool' => 1));
            },
        ));
        $result = $agent->run($messages);
        $store->addMessage($sessionId, 'assistant', $result['reply'], null, $result['usage']);
        $store->touchSession($sessionId, array('inc_msg' => 1));
        $store->pruneMessages($sessionId, $c['message_limit']);

        $links = array();
        $needHuman = false;
        $csId = 0;
        foreach ($result['tool_trace'] as $t) {
            $tr = isset($t['result']) && is_array($t['result']) ? $t['result'] : array();
            if (!empty($tr['need_human'])) $needHuman = true;
            if (!empty($tr['cs_id'])) $csId = intval($tr['cs_id']);
            if (empty($tr['data']) || !is_array($tr['data'])) continue;
            foreach ($tr['data'] as $g) {
                if (!empty($g['tid']) && !empty($g['name']) && !empty($g['link'])) {
                    $links[] = array(
                        'tid' => $g['tid'],
                        'cid' => isset($g['cid']) ? $g['cid'] : 0,
                        'name' => $g['name'],
                        'price' => isset($g['price']) ? $g['price'] : '',
                        'link' => $g['link'],
                        'reason' => isset($g['reason']) ? $g['reason'] : '',
                    );
                }
            }
            if (count($links) >= 8) break;
        }
        foreach ($result['tool_trace'] as $t) {
            if (!empty($t['result']['goods']) && is_array($t['result']['goods'])) {
                $g = $t['result']['goods'];
                if (!empty($g['tid']) && !empty($g['link'])) {
                    $links[] = array(
                        'tid' => $g['tid'],
                        'cid' => isset($g['cid']) ? $g['cid'] : 0,
                        'name' => isset($g['name']) ? $g['name'] : ('商品' . $g['tid']),
                        'price' => isset($g['price']) ? $g['price'] : '',
                        'link' => $g['link'],
                        'reason' => '',
                    );
                }
            }
        }

        exit(json_encode(array(
            'code' => 0,
            'session_id' => $sessionId,
            'reply' => $result['reply'],
            'links' => $links,
            'need_human' => $needHuman,
            'cs_id' => $csId,
            'request_id' => $requestId,
            'rate' => array(
                'remain' => max(0, $rateInfo['remain'] - 1),
                'limit' => $c['shop_rate'],
                'daily_remain' => max(0, $dailyInfo['remain'] - 1),
                'daily_limit' => $c['shop_daily'],
            ),
            'site' => array(
                'sitename' => $c['sitename'],
                'assistant_name' => $c['assistant_name'],
            ),
        ), JSON_UNESCAPED_UNICODE));
    } catch (Exception $e) {
        if (!isset($_SESSION[$failKey]) || !is_array($_SESSION[$failKey])) $_SESSION[$failKey] = array();
        $_SESSION[$failKey][] = time();
        $store->addMessage($sessionId, 'assistant', '错误：暂时繁忙', null, null);
        exit(json_encode(array('code' => -1, 'msg' => '客服暂时繁忙，请稍后再试或联系人工客服', 'session_id' => $sessionId)));
    }
}

exit(json_encode(array('code' => -1, 'msg' => 'unknown act')));
