<?php
/**
 * 资源站 API（daishua 协议 v1.0）+ 可售服务引擎
 * 规范：API.md | 服务：lib/Services.php
 */
header('Content-Type: application/json; charset=UTF-8');
error_reporting(0);

$DATA_DIR = __DIR__ . '/data';
$GOODS_FILE = $DATA_DIR . '/goods.json';
$CARDS_FILE = $DATA_DIR . '/cards.json';
$ORDERS_FILE = $DATA_DIR . '/orders.json';

require_once __DIR__ . '/lib/Services.php';

$cfgFile = __DIR__ . '/config.php';
$cfg = is_file($cfgFile) ? include $cfgFile : [];
if (!is_array($cfg)) $cfg = [];
$USER = isset($cfg['user']) ? $cfg['user'] : 'supplier';
$PASS = isset($cfg['pass']) ? $cfg['pass'] : 'supplier123';
$ALLOW_REMOTE = !empty($cfg['allow_remote']);
$SITE_NAME = isset($cfg['name']) ? $cfg['name'] : '演示资源站';
$SITE_VER = isset($cfg['version']) ? $cfg['version'] : '1.0';

$REMOTE_ADDR = isset($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : '';
if (!$ALLOW_REMOTE && $REMOTE_ADDR !== '127.0.0.1' && $REMOTE_ADDR !== '::1') {
	http_response_code(403);
	exit(json_encode(['code' => 403, 'message' => '本资源站未开放远程访问（config.php 中 allow_remote=false）'], JSON_UNESCAPED_UNICODE));
}

if (!is_dir($DATA_DIR)) {
	@mkdir($DATA_DIR, 0755, true);
}

$LOCK_HANDLE = @fopen($DATA_DIR . '/supplier.lock', 'c+');
if (!$LOCK_HANDLE || !@flock($LOCK_HANDLE, LOCK_EX)) {
	http_response_code(503);
	exit(json_encode(['code' => 503, 'message' => '货源数据暂时不可用'], JSON_UNESCAPED_UNICODE));
}

function read_json($file, $default = []) {
	if (!is_file($file)) return $default;
	$data = json_decode(file_get_contents($file), true);
	return is_array($data) ? $data : $default;
}

function write_json($file, $data) {
	return file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
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

function goods_stock($g, $cards) {
	if (isset($g['stock_mode']) && $g['stock_mode'] === 'unlimited') return 9999;
	if (!empty($g['service']) && $g['service'] !== 'faka') return 9999;
	if (!empty($g['isfaka'])) return stock_of($g['tid'], $cards);
	return null;
}

// 若仍是旧版假货商品文件，提示可覆盖；仅在文件不存在时写入默认可售目录
if (!is_file($GOODS_FILE)) {
	// 默认目录由 goods.json 仓库文件提供；此处兜底
	write_json($GOODS_FILE, [
		['tid' => 1001, 'cid' => 1, 'name' => '网站体检报告（即时交付）', 'price' => '2.00', 'value' => 1, 'input' => '网站地址', 'inputs' => '', 'desc' => '真实体检', 'alert' => '', 'close' => 0, 'multi' => 1, 'min' => 1, 'max' => 5, 'isfaka' => 1, 'shopimg' => '', 'service' => 'site_check', 'stock_mode' => 'unlimited'],
	]);
	write_json($CARDS_FILE, []);
	write_json($ORDERS_FILE, []);
}

$act = isset($_GET['act']) ? $_GET['act'] : '';
$user = isset($_POST['user']) ? trim($_POST['user']) : '';
$pass = isset($_POST['pass']) ? trim($_POST['pass']) : '';
$svcEngine = new SupplierServices($DATA_DIR, $cfg);

if ($act === 'ping') {
	exit(json_encode([
		'code' => 0,
		'protocol' => 'daishua',
		'version' => $SITE_VER,
		'name' => $SITE_NAME,
		'services' => array_keys(SupplierServices::catalog()),
	], JSON_UNESCAPED_UNICODE));
}

// 兑换码核销：可只校验 code；仍要求对接账号防刷
if ($act === 'redeem') {
	if (!auth_ok($user, $pass)) {
		http_response_code(401);
		exit(json_encode(['code' => -1, 'message' => '用户名或密码不正确'], JSON_UNESCAPED_UNICODE));
	}
	$code = isset($_POST['code']) ? $_POST['code'] : '';
	$use = isset($_POST['use']) ? $_POST['use'] : 1;
	exit(json_encode($svcEngine->redeem($code, $use), JSON_UNESCAPED_UNICODE));
}

if ($act === 'servicelist') {
	if (!auth_ok($user, $pass)) {
		http_response_code(401);
		exit(json_encode(['code' => -1, 'message' => '用户名或密码不正确'], JSON_UNESCAPED_UNICODE));
	}
	exit(json_encode(['code' => 0, 'data' => SupplierServices::catalog()], JSON_UNESCAPED_UNICODE));
}

if (!auth_ok($user, $pass)) {
	http_response_code(401);
	exit(json_encode(['code' => -1, 'message' => '用户名或密码不正确'], JSON_UNESCAPED_UNICODE));
}

$goods = read_json($GOODS_FILE);
$cards = read_json($CARDS_FILE);
$orders = read_json($ORDERS_FILE);

if ($act === 'classlist') {
	$data = [
		['cid' => 1, 'name' => '即时交付服务', 'active' => 1, 'sort' => 1],
		['cid' => 2, 'name' => 'AI 能力', 'active' => 1, 'sort' => 2],
		['cid' => 3, 'name' => '人工服务', 'active' => 1, 'sort' => 3],
	];
	exit(json_encode(['code' => 0, 'msg' => 'succ', 'data' => $data, 'count' => count($data)], JSON_UNESCAPED_UNICODE));
}

if ($act === 'goodslistbycid') {
	$cid = intval(isset($_POST['cid']) ? $_POST['cid'] : 0);
	$data = [];
	foreach ($goods as $g) {
		if ($cid && (int)$g['cid'] !== $cid) continue;
		$stock = goods_stock($g, $cards);
		$data[] = [
			'tid' => $g['tid'], 'cid' => $g['cid'], 'sort' => 10, 'name' => $g['name'],
			'value' => $g['value'], 'price' => $g['price'], 'input' => $g['input'],
			'inputs' => $g['inputs'], 'desc' => $g['desc'], 'alert' => $g['alert'],
			'shopimg' => $g['shopimg'], 'validate' => 0, 'valiserv' => '',
			'repeat' => 1, 'multi' => $g['multi'], 'close' => (int)$g['close'],
			'prices' => '', 'min' => $g['min'], 'max' => $g['max'], 'sales' => 0,
			'isfaka' => (int)$g['isfaka'], 'stock' => $stock,
			'service' => isset($g['service']) ? $g['service'] : '',
		];
	}
	exit(json_encode(['code' => 0, 'msg' => 'succ', 'data' => $data, 'count' => count($data)], JSON_UNESCAPED_UNICODE));
}

if ($act === 'goodslist') {
	$data = [];
	foreach ($goods as $g) {
		$stock = goods_stock($g, $cards);
		$data[] = [
			'tid' => $g['tid'],
			'cid' => $g['cid'],
			'name' => $g['name'],
			'shopimg' => $g['shopimg'],
			'close' => (int)$g['close'],
			'price' => $g['price'],
			'isfaka' => (int)$g['isfaka'],
			'stock' => $stock,
			'id' => $g['tid'],
			'service' => isset($g['service']) ? $g['service'] : '',
		];
	}
	exit(json_encode(['code' => 0, 'data' => $data], JSON_UNESCAPED_UNICODE));
}

if ($act === 'goodsdetails') {
	$tid = intval(isset($_POST['tid']) ? $_POST['tid'] : 0);
	$tool = null;
	foreach ($goods as $g) {
		if ((int)$g['tid'] === $tid) { $tool = $g; break; }
	}
	if (!$tool) exit(json_encode(['code' => -1, 'message' => '商品不存在'], JSON_UNESCAPED_UNICODE));
	$stock = goods_stock($tool, $cards);
	$data = [
		'tid' => $tool['tid'], 'cid' => $tool['cid'], 'sort' => 10, 'name' => $tool['name'],
		'value' => $tool['value'], 'price' => $tool['price'], 'prices' => '',
		'input' => $tool['input'], 'inputs' => $tool['inputs'], 'desc' => $tool['desc'],
		'alert' => $tool['alert'], 'shopimg' => $tool['shopimg'], 'repeat' => 1,
		'multi' => $tool['multi'], 'min' => $tool['min'], 'max' => $tool['max'],
		'close' => (int)$tool['close'], 'isfaka' => (int)$tool['isfaka'], 'stock' => $stock,
		'service' => isset($tool['service']) ? $tool['service'] : '',
	];
	exit(json_encode(['code' => 0, 'data' => $data], JSON_UNESCAPED_UNICODE));
}

if ($act === 'pay') {
	$tid = intval(isset($_POST['tid']) ? $_POST['tid'] : 0);
	$num = max(1, intval(isset($_POST['num']) ? $_POST['num'] : 1));
	$input1 = isset($_POST['input1']) ? trim($_POST['input1']) : '';
	$input2 = isset($_POST['input2']) ? trim($_POST['input2']) : '';
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
	$status = 0;

	if (!empty($tool['service']) && $tool['service'] !== 'faka') {
		$delivered = $svcEngine->deliver($tool, $num, $input1, $input2, $orderid);
		if (empty($delivered['ok'])) {
			exit(json_encode(['code' => -1, 'message' => isset($delivered['message']) ? $delivered['message'] : '服务交付失败'], JSON_UNESCAPED_UNICODE));
		}
		$status = isset($delivered['status']) ? intval($delivered['status']) : 1;
		if (!empty($delivered['faka'])) {
			$result['faka'] = true;
			$result['kmdata'] = $delivered['kmdata'];
		}
		if (!empty($delivered['receipt'])) {
			$result['message'] = $delivered['receipt'];
		}
		if (!empty($delivered['meta']['ticket'])) {
			$result['ticket'] = $delivered['meta']['ticket'];
		}
	} elseif ($tool['isfaka']) {
		// 兼容旧版预置卡密
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
		$kmstr = [];
		foreach ($picked as $p) {
			$kmstr[] = $p['card'] . ($p['pass'] ? ' ' . $p['pass'] : '');
		}
		$result['kmdata'] = implode("\n", $kmstr);
		$status = 1;
	}

	$orders[] = [
		'orderid' => $orderid,
		'tid' => $tid,
		'num' => $num,
		'input1' => $input1,
		'input2' => $input2,
		'service' => isset($tool['service']) ? $tool['service'] : '',
		'addtime' => date('Y-m-d H:i:s'),
		'status' => $status,
	];
	write_json($ORDERS_FILE, $orders);
	file_put_contents($DATA_DIR . '/api.log', date('c') . " PAY tid=$tid svc=" . (isset($tool['service']) ? $tool['service'] : '-') . " num=$num order=$orderid\n", FILE_APPEND);
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
