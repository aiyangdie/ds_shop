<?php
/**
 * 独立货源 API（彩虹同系统协议）
 * 商城通过 third_daishua 真实 HTTP 调用本接口完成下单/发卡
 * 上线后把 shequ.url 改成你的真实货源域名即可
 */
header('Content-Type: application/json; charset=UTF-8');
error_reporting(0);

$DATA_DIR = __DIR__ . '/data';
$GOODS_FILE = $DATA_DIR . '/goods.json';
$CARDS_FILE = $DATA_DIR . '/cards.json';
$ORDERS_FILE = $DATA_DIR . '/orders.json';
$USER = 'supplier';
$PASS = 'supplier123';

if (!is_dir($DATA_DIR)) {
    @mkdir($DATA_DIR, 0755, true);
}

function read_json($file, $default = []) {
    if (!is_file($file)) return $default;
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : $default;
}

function write_json($file, $data) {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function auth_ok($user, $pass) {
    global $USER, $PASS;
    return $user === $USER && $pass === $PASS;
}

function stock_of($tid, $cards) {
    $n = 0;
    foreach ($cards as $c) {
        if ((int)$c['tid'] === (int)$tid && (int)$c['orderid'] === 0) $n++;
    }
    return $n;
}

// 初始化演示货源商品（真实可售的数字商品形态）
if (!is_file($GOODS_FILE)) {
    $goods = [
        ['tid' => 1001, 'cid' => 1, 'name' => '网盘VIP月卡（货源API）', 'price' => '12.00', 'value' => 1, 'input' => '收货邮箱', 'inputs' => '', 'desc' => '通过货源API自动发货', 'alert' => '卡密见订单详情', 'close' => 0, 'multi' => 1, 'min' => 1, 'max' => 5, 'isfaka' => 1, 'shopimg' => ''],
        ['tid' => 1002, 'cid' => 1, 'name' => '短视频去水印额度包', 'price' => '3.50', 'value' => 1, 'input' => '手机号', 'inputs' => '', 'desc' => '货源API下发额度卡密', 'alert' => '请保存卡密', 'close' => 0, 'multi' => 1, 'min' => 1, 'max' => 20, 'isfaka' => 1, 'shopimg' => ''],
        ['tid' => 1003, 'cid' => 2, 'name' => 'AI写作次数包（100次）', 'price' => '9.90', 'value' => 1, 'input' => '收货邮箱', 'inputs' => '', 'desc' => '对接货源API发放兑换码', 'alert' => '兑换码一次性有效', 'close' => 0, 'multi' => 1, 'min' => 1, 'max' => 10, 'isfaka' => 1, 'shopimg' => ''],
        ['tid' => 1004, 'cid' => 2, 'name' => '短信通知接口额度', 'price' => '6.00', 'value' => 1, 'input' => '商户标识', 'inputs' => '', 'desc' => 'API额度卡密', 'alert' => '按卡密说明充值', 'close' => 0, 'multi' => 1, 'min' => 1, 'max' => 10, 'isfaka' => 1, 'shopimg' => ''],
        ['tid' => 1005, 'cid' => 3, 'name' => '人工代办加急（API接单）', 'price' => '15.00', 'value' => 1, 'input' => '联系微信', 'inputs' => '需求说明', 'desc' => '货源接收后返回工单号', 'alert' => '客服将联系你', 'close' => 0, 'multi' => 0, 'min' => 1, 'max' => 1, 'isfaka' => 0, 'shopimg' => ''],
    ];
    write_json($GOODS_FILE, $goods);

    $cards = [];
    $now = date('Y-m-d H:i:s');
    foreach ([1001 => 20, 1002 => 30, 1003 => 25, 1004 => 20] as $tid => $num) {
        for ($i = 1; $i <= $num; $i++) {
            $cards[] = [
                'kid' => $tid * 1000 + $i,
                'tid' => $tid,
                'km' => sprintf('API-%d-%04d-%s', $tid, $i, strtoupper(substr(md5($tid . $i . 'salt'), 0, 8))),
                'pw' => '货源直发',
                'addtime' => $now,
                'orderid' => 0,
            ];
        }
    }
    write_json($CARDS_FILE, $cards);
    write_json($ORDERS_FILE, []);
}

$act = isset($_GET['act']) ? $_GET['act'] : '';
$user = isset($_POST['user']) ? trim($_POST['user']) : '';
$pass = isset($_POST['pass']) ? trim($_POST['pass']) : '';

$goods = read_json($GOODS_FILE);
$cards = read_json($CARDS_FILE);
$orders = read_json($ORDERS_FILE);

if ($act === 'classlist') {
    $data = [
        ['cid' => 1, 'name' => '热门API商品', 'active' => 1, 'sort' => 1],
        ['cid' => 2, 'name' => '额度与工具', 'active' => 1, 'sort' => 2],
        ['cid' => 3, 'name' => '人工服务', 'active' => 1, 'sort' => 3],
    ];
    exit(json_encode(['code' => 0, 'msg' => 'succ', 'data' => $data, 'count' => count($data)], JSON_UNESCAPED_UNICODE));
}

if ($act === 'goodslistbycid') {
    if ($user !== '' || $pass !== '') {
        if (!auth_ok($user, $pass)) {
            exit(json_encode(['code' => -1, 'message' => '用户名或密码不正确'], JSON_UNESCAPED_UNICODE));
        }
    }
    $cid = intval(isset($_POST['cid']) ? $_POST['cid'] : 0);
    $data = [];
    foreach ($goods as $g) {
        if ($cid && (int)$g['cid'] !== $cid) continue;
        $stock = $g['isfaka'] ? stock_of($g['tid'], $cards) : null;
        $data[] = [
            'tid' => $g['tid'], 'cid' => $g['cid'], 'sort' => 10, 'name' => $g['name'],
            'value' => $g['value'], 'price' => $g['price'], 'input' => $g['input'],
            'inputs' => $g['inputs'], 'desc' => $g['desc'], 'alert' => $g['alert'],
            'shopimg' => $g['shopimg'], 'validate' => 0, 'valiserv' => '',
            'repeat' => 1, 'multi' => $g['multi'], 'close' => (int)$g['close'],
            'prices' => '', 'min' => $g['min'], 'max' => $g['max'], 'sales' => 0,
            'isfaka' => (int)$g['isfaka'], 'stock' => $stock,
        ];
    }
    exit(json_encode(['code' => 0, 'msg' => 'succ', 'data' => $data, 'count' => count($data)], JSON_UNESCAPED_UNICODE));
}

if ($act === 'goodslist') {
    if ($user !== '' || $pass !== '') {
        if (!auth_ok($user, $pass)) {
            exit(json_encode(['code' => -1, 'message' => '用户名或密码不正确'], JSON_UNESCAPED_UNICODE));
        }
    }
    $data = [];
    foreach ($goods as $g) {
        $stock = $g['isfaka'] ? stock_of($g['tid'], $cards) : null;
        $data[] = [
            'tid' => $g['tid'],
            'cid' => $g['cid'],
            'name' => $g['name'],
            'shopimg' => $g['shopimg'],
            'close' => (int)$g['close'],
            'price' => $g['price'],
            'isfaka' => (int)$g['isfaka'],
            'stock' => $stock,
            'id' => $g['tid'], // 兼容部分插件
        ];
    }
    exit(json_encode(['code' => 0, 'data' => $data], JSON_UNESCAPED_UNICODE));
}

if ($act === 'goodsdetails') {
    if ($user !== '' || $pass !== '') {
        if (!auth_ok($user, $pass)) {
            exit(json_encode(['code' => -1, 'message' => '用户名或密码不正确'], JSON_UNESCAPED_UNICODE));
        }
    }
    $tid = intval(isset($_POST['tid']) ? $_POST['tid'] : 0);
    $tool = null;
    foreach ($goods as $g) {
        if ((int)$g['tid'] === $tid) { $tool = $g; break; }
    }
    if (!$tool) exit(json_encode(['code' => -1, 'message' => '商品不存在'], JSON_UNESCAPED_UNICODE));
    $stock = $tool['isfaka'] ? stock_of($tid, $cards) : null;
    if ($tool['isfaka'] && $stock === 0) $tool['close'] = 1;
    $data = [
        'tid' => $tool['tid'], 'cid' => $tool['cid'], 'sort' => 10, 'name' => $tool['name'],
        'value' => $tool['value'], 'price' => $tool['price'], 'prices' => '',
        'input' => $tool['input'], 'inputs' => $tool['inputs'], 'desc' => $tool['desc'],
        'alert' => $tool['alert'], 'shopimg' => $tool['shopimg'], 'repeat' => 1,
        'multi' => $tool['multi'], 'min' => $tool['min'], 'max' => $tool['max'],
        'close' => (int)$tool['close'], 'isfaka' => (int)$tool['isfaka'], 'stock' => $stock,
    ];
    exit(json_encode(['code' => 0, 'data' => $data], JSON_UNESCAPED_UNICODE));
}

if ($act === 'pay') {
    if (!auth_ok($user, $pass)) {
        exit(json_encode(['code' => -1, 'message' => '用户名或密码不正确'], JSON_UNESCAPED_UNICODE));
    }
    $tid = intval(isset($_POST['tid']) ? $_POST['tid'] : 0);
    $num = max(1, intval(isset($_POST['num']) ? $_POST['num'] : 1));
    $input1 = isset($_POST['input1']) ? trim($_POST['input1']) : '';
    if ($tid <= 0 || $input1 === '') {
        exit(json_encode(['code' => -1, 'message' => '参数不完整'], JSON_UNESCAPED_UNICODE));
    }
    $tool = null;
    foreach ($goods as $g) {
        if ((int)$g['tid'] === $tid) { $tool = $g; break; }
    }
    if (!$tool || $tool['close']) {
        exit(json_encode(['code' => -1, 'message' => '商品不存在或维护中'], JSON_UNESCAPED_UNICODE));
    }

    $orderid = time() . mt_rand(100, 999);
    $result = ['code' => 0, 'orderid' => (string)$orderid];

    if ($tool['isfaka']) {
        $picked = [];
        foreach ($cards as &$c) {
            if ((int)$c['tid'] === $tid && (int)$c['orderid'] === 0) {
                $c['orderid'] = (int)$orderid;
                $picked[] = ['card' => $c['km'], 'pass' => $c['pw']];
                if (count($picked) >= $num) break;
            }
        }
        unset($c);
        if (count($picked) < $num) {
            exit(json_encode(['code' => -1, 'message' => '货源库存不足'], JSON_UNESCAPED_UNICODE));
        }
        write_json($CARDS_FILE, $cards);
        $result['faka'] = true;
        // 彩虹对接期望 kmdata 字符串或数组
        $kmstr = [];
        foreach ($picked as $p) {
            $kmstr[] = $p['card'] . ($p['pass'] ? ' ' . $p['pass'] : '');
        }
        $result['kmdata'] = implode("\n", $kmstr);
    }

    $orders[] = [
        'orderid' => $orderid,
        'tid' => $tid,
        'num' => $num,
        'input1' => $input1,
        'input2' => isset($_POST['input2']) ? $_POST['input2'] : '',
        'addtime' => date('Y-m-d H:i:s'),
        'status' => $tool['isfaka'] ? 1 : 0,
    ];
    write_json($ORDERS_FILE, $orders);
    // 记录调用日志方便你确认“真的打到API”
    file_put_contents($DATA_DIR . '/api.log', date('c') . " PAY tid=$tid num=$num input=$input1 order=$orderid\n", FILE_APPEND);
    exit(json_encode($result, JSON_UNESCAPED_UNICODE));
}

if ($act === 'search' || $act === 'query') {
    $id = isset($_POST['id']) ? $_POST['id'] : (isset($_GET['id']) ? $_GET['id'] : '');
    foreach ($orders as $o) {
        if ((string)$o['orderid'] === (string)$id) {
            exit(json_encode(['code' => 0, 'status' => $o['status'], 'data' => $o], JSON_UNESCAPED_UNICODE));
        }
    }
    exit(json_encode(['code' => -1, 'message' => '订单不存在'], JSON_UNESCAPED_UNICODE));
}

exit(json_encode(['code' => -1, 'message' => '未知接口 act'], JSON_UNESCAPED_UNICODE));
