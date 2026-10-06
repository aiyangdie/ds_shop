<?php

declare(strict_types=1);

$GLOBALS['mailLog'] = [];
$GLOBALS['getCurlLog'] = [];
$GLOBALS['getCurlReturn'] = '成功';
$GLOBALS['thirdPluginLog'] = [];
$GLOBALS['thirdPluginReturn'] = ['code' => 0, 'id' => 'UP999', 'message' => 'ok'];

require dirname(__DIR__, 2) . '/deobfuscated/stages/mail-stubs.php';

function get_curl()
{
    $GLOBALS['getCurlLog'][] = func_get_args();
    return $GLOBALS['getCurlReturn'];
}

function checkEmail($value)
{
    return (bool) preg_match('/^[\w\.\-]+@\w+([\.\-]\w+)*\.\w+$/', $value) && strlen($value) <= 60;
}

if (!class_exists('plugins\\third_daishua', false)) {
    eval('namespace plugins { class third_daishua {
        public $config;
        public function __construct($config) { $this->config = $config; }
        public function do_goods() {
            $GLOBALS["thirdPluginLog"][] = ["args"=>func_get_args()];
            return $GLOBALS["thirdPluginReturn"];
        }
    }}');
}

require dirname(__DIR__, 2) . '/deobfuscated/recovered/core-simple.php';
require dirname(__DIR__, 2) . '/deobfuscated/recovered/core-network.php';
require dirname(__DIR__, 2) . '/deobfuscated/recovered/core-notify.php';
require dirname(__DIR__, 2) . '/deobfuscated/recovered/core-order.php';

final class TraceResult
{
    private $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function fetch() { return empty($this->rows) ? false : array_shift($this->rows); }
}

final class TraceDB
{
    public $calls = [];
    public $orders = [];
    public $tools = [];
    public $shequ = [];
    public $faka = [];
    public $sites = [];
    public $cart = [];
    public $insertId = 500;

    public function beginTransaction() { $this->calls[] = ['beginTransaction']; return true; }
    public function commit() { $this->calls[] = ['commit']; return true; }
    public function rollBack() { $this->calls[] = ['rollBack']; return true; }
    public function lastInsertId() { $this->calls[] = ['lastInsertId']; return $this->insertId; }

    public function getColumn($query, $params = [])
    {
        $this->calls[] = ['getColumn', $query];
        if (stripos($query, 'power') !== false && preg_match("/zid='?(\\d+)/", $query, $m) && isset($this->sites[$m[1]])) {
            return $this->sites[$m[1]]['power'];
        }
        if (stripos($query, 'pre_faka') !== false) {
            $n = 0;
            foreach ($this->faka as $row) {
                if (empty($row['orderid'])) {
                    $n++;
                }
            }
            return $n;
        }
        return 0;
    }

    public function getRow($query, $params = [])
    {
        $this->calls[] = ['getRow', $query];
        if (stripos($query, 'pre_orders') !== false && preg_match("/id='?(\\d+)/", $query, $m) && isset($this->orders[$m[1]])) {
            return $this->orders[$m[1]];
        }
        if (stripos($query, 'pre_tools') !== false && preg_match("/tid='?(\\d+)/", $query, $m) && isset($this->tools[$m[1]])) {
            return $this->tools[$m[1]];
        }
        if (stripos($query, 'pre_shequ') !== false && preg_match("/id='?(\\d+)/", $query, $m) && isset($this->shequ[$m[1]])) {
            return $this->shequ[$m[1]];
        }
        if (stripos($query, 'pre_site') !== false && preg_match("/zid='?(\\d+)/", $query, $m) && isset($this->sites[$m[1]])) {
            return $this->sites[$m[1]];
        }
        return null;
    }

    public function query($query)
    {
        $this->calls[] = ['query', $query];
        if (stripos($query, 'pre_cart') !== false) {
            $rows = [];
            if (preg_match('/IN \\(([^)]+)\\)/i', $query, $m)) {
                foreach (explode(',', $m[1]) as $id) {
                    $id = trim($id, " '");
                    if (isset($this->cart[$id])) {
                        $rows[] = $this->cart[$id];
                    }
                }
            }
            return new TraceResult($rows);
        }
        $limit = 100;
        if (preg_match('/LIMIT\\s+(\\d+)/i', $query, $m)) {
            $limit = (int) $m[1];
        }
        $available = [];
        foreach ($this->faka as $row) {
            if (empty($row['orderid'])) {
                $available[] = $row;
            }
        }
        return new TraceResult(array_slice($available, 0, $limit));
    }

    public function exec($query, $params = [])
    {
        $this->calls[] = ['exec', $query, $params];
        if (stripos($query, 'INSERT INTO `pre_orders`') !== false || stripos($query, 'INSERT INTO pre_orders') !== false
            || stripos($query, 'INSERT INTO `pre_site`') !== false) {
            $this->insertId++;
            return 1;
        }
        if (stripos($query, 'pre_faka') !== false && preg_match("/kid='?(\\d+)/", $query, $m) && isset($this->faka[$m[1]])) {
            $this->faka[$m[1]]['orderid'] = 1;
        }
        return 1;
    }
}

function assertTrue($cond, $label)
{
    if (!$cond) {
        throw new RuntimeException('Assertion failed: ' . $label);
    }
}

$conf = [
    'sitename' => '测试站',
    'faka_mail' => "您好，您购买的[name]卡密如下：\n[kmdata]\n[alert]",
    'mail_cloud' => 1,
    'mail_apiuser' => 'u',
    'mail_apikey' => 'k',
    'mail_name2' => 'from@test',
    'message_buy' => 1,
    'message_duijie' => 1,
    'message_fakastock' => 1,
    'fenzhan_gift' => '100:10|50:5',
    'fenzhan_free' => 5,
    'fenzhan_cost' => 10,
    'fenzhan_cost2' => 20,
    'title' => '主站标题',
    'keywords' => 'kw',
    'description' => 'desc',
    'tixian_limit' => 0,
];
$date = '2026-10-05 12:00:00';
$_SERVER['HTTP_HOST'] = 'shop.test';
$GLOBALS['messageSendLog'] = [];
$GLOBALS['priceLog'] = [];

if (!class_exists('lib\\MessageSend', false)) {
    eval('namespace lib { class MessageSend {
        static public function orderbuy() { $GLOBALS["messageSendLog"][] = ["orderbuy", func_get_args()]; }
        static public function orderbuy_fail() { $GLOBALS["messageSendLog"][] = ["orderbuy_fail", func_get_args()]; }
        static public function faka_stock() { $GLOBALS["messageSendLog"][] = ["faka_stock", func_get_args()]; }
    }}');
}
if (!class_exists('lib\\Price', false)) {
    eval('namespace lib { class Price {
        public function __construct($zid, $siterow = null) { $GLOBALS["priceLog"][] = ["ctor", $zid]; }
        public function setToolInfo($tid, $row = null) { $GLOBALS["priceLog"][] = ["setToolInfo", $tid]; }
        public function setToolProfit($tid, $num, $name, $money, $orderid, $userid = 0) {
            $GLOBALS["priceLog"][] = ["setToolProfit", $tid, $num, $name, $money, $orderid, $userid];
            return true;
        }
    }}');
}

$baseOrder = [
    'id' => 101, 'tid' => 5, 'input' => '123456@qq.com', 'input2' => 'x2', 'input3' => 'x3',
    'input4' => 'x4', 'input5' => 'x5', 'value' => 2, 'money' => '10.00', 'tradeno' => 'T20261005001',
    'status' => 0, 'djzt' => 0, 'userid' => 0,
];

// override path
$DB = new TraceDB();
$GLOBALS['getCurlLog'] = [];
$GLOBALS['getCurlReturn'] = '成功';
assertTrue(do_goods(101, 'https://up.test/override?u=[input]', 'x=[num]') === '成功', 'override returns curl body');
assertTrue($GLOBALS['getCurlLog'][0] === ['https://up.test/override?u=[input]', 'x=[num]'], 'override raw get_curl args');

// faka success
$DB = new TraceDB();
$DB->orders[101] = $baseOrder;
$DB->tools[5] = ['tid'=>5,'name'=>'测试卡密','is_curl'=>4,'shequ'=>0,'goods_id'=>0,'goods_type'=>0,'goods_param'=>'','curl'=>'','alert'=>'请妥善保管','price'=>'5.00','inputs'=>''];
$DB->faka = [
    1 => ['kid'=>1,'km'=>'CARD-AAA','pw'=>'p1','orderid'=>0],
    2 => ['kid'=>2,'km'=>'CARD-BBB','pw'=>'p2','orderid'=>0],
];
$GLOBALS['mailLog'] = [];
assertTrue(do_goods(101) === '发卡成功！', 'faka success message');
assertTrue($GLOBALS['mailLog'][1]['sub'] === '测试站 卡密购买提醒', 'faka mail subject');
assertTrue(strpos($GLOBALS['mailLog'][1]['msg'], '卡号：CARD-AAA 密码：p1') !== false, 'faka mail body');

// faka zero stock
$DB = new TraceDB();
$DB->orders[101] = $baseOrder;
$DB->tools[5] = ['tid'=>5,'name'=>'测试卡密','is_curl'=>4,'shequ'=>0,'goods_id'=>0,'goods_type'=>0,'goods_param'=>'','curl'=>'','alert'=>'','price'=>'5.00','inputs'=>''];
$DB->faka = [];
assertTrue(do_goods(101) === '卡密库存不足，发卡失败！', 'faka zero stock');

// shequ success
$DB = new TraceDB();
$DB->orders[101] = $baseOrder;
$DB->tools[5] = ['tid'=>5,'name'=>'对接商品','is_curl'=>2,'shequ'=>9,'goods_id'=>77,'goods_type'=>1,'goods_param'=>'param','curl'=>'','inputs'=>'qq|pass','price'=>'5.00'];
$DB->shequ[9] = ['id'=>9,'type'=>'daishua','username'=>'u','password'=>'p','url'=>'http://up.test/'];
$GLOBALS['thirdPluginReturn'] = ['code' => 0, 'id' => 'UP999', 'message' => 'ok'];
$GLOBALS['thirdPluginLog'] = [];
assertTrue(do_goods(101) === '下单成功!订单号:UP999', 'shequ success');
assertTrue($GLOBALS['thirdPluginLog'][0]['args'][0] === 77, 'shequ goods_id arg');

// shequ missing
$DB = new TraceDB();
$DB->orders[101] = $baseOrder;
$DB->tools[5] = ['tid'=>5,'name'=>'对接商品','is_curl'=>2,'shequ'=>9,'goods_id'=>77,'goods_type'=>1,'goods_param'=>'','curl'=>'','price'=>'5.00'];
assertTrue(do_goods(101) === '未配置好网站对接信息', 'shequ missing');

// shequ fail
$DB = new TraceDB();
$DB->orders[101] = $baseOrder;
$DB->tools[5] = ['tid'=>5,'name'=>'对接商品','is_curl'=>2,'shequ'=>9,'goods_id'=>77,'goods_type'=>1,'goods_param'=>'param','curl'=>'','inputs'=>'qq|pass','price'=>'5.00'];
$DB->shequ[9] = ['id'=>9,'type'=>'daishua','username'=>'u','password'=>'p','url'=>'http://up.test/'];
$GLOBALS['thirdPluginReturn'] = ['code' => -1, 'message' => '货源拒绝'];
assertTrue(do_goods(101) === '下单失败：货源拒绝', 'shequ fail');

// curl json logs and returns empty
$DB = new TraceDB();
$DB->orders[101] = $baseOrder;
$DB->tools[5] = ['tid'=>5,'name'=>'CURL商品','is_curl'=>1,'shequ'=>0,'goods_id'=>0,'goods_type'=>0,'goods_param'=>'','curl'=>'https://up.test/buy?u=[input]&n=[num]','price'=>'5.00','inputs'=>''];
$GLOBALS['getCurlReturn'] = json_encode(['code' => 0, 'id' => 'C100'], JSON_UNESCAPED_UNICODE);
$GLOBALS['getCurlLog'] = [];
assertTrue(do_goods(101) === '', 'curl json returns empty string');
$logExec = null;
foreach ($DB->calls as $call) {
    if ($call[0] === 'exec' && strpos($call[1], 'pre_logs') !== false) {
        $logExec = $call;
    }
}
assertTrue($logExec !== null && $logExec[2][':action'] === '自动访问URL', 'curl json logs 自动访问URL');
assertTrue($logExec[2][':res'] === '下单成功!订单号:C100', 'curl json log message');

// is_curl 0
$DB = new TraceDB();
$DB->orders[101] = $baseOrder;
$DB->tools[5] = ['tid'=>5,'name'=>'手动','is_curl'=>0,'shequ'=>0,'goods_id'=>0,'goods_type'=>0,'goods_param'=>'','curl'=>'','price'=>'5.00'];
assertTrue(do_goods(101) === '该商品未配置对接或自动发卡', 'manual product');

// --- doOrder / processOrder ---
$basePay = [
    'tid' => 5,
    'zid' => 2,
    'input' => '123456@qq.com|p2|p3',
    'num' => 2,
    'name' => '测试商品',
    'money' => '10.00',
    'trade_no' => 'T20261005001',
    'userid' => 'cookie99',
    'type' => 'alipay',
    'blockdj' => 0,
];
$baseTool = [
    'tid' => 5, 'name' => '测试商品', 'is_curl' => 0, 'prid' => 0, 'price' => '5.00',
    'cost' => '3.00', 'cost2' => '4.00', 'stock' => 100, 'shequ' => 0,
    'goods_id' => 0, 'goods_type' => 0, 'goods_param' => '', 'curl' => '',
    'alert' => '请妥善保管', 'desc' => '', 'inputs' => 'qq|pass', 'input' => 'QQ', 'value' => 1,
];

$DB = new TraceDB();
$DB->tools[5] = $baseTool;
$GLOBALS['messageSendLog'] = [];
$GLOBALS['priceLog'] = [];
assertTrue(doOrder($basePay, true) === 501, 'doOrder manual id');
assertTrue($GLOBALS['priceLog'][0][0] === 'ctor', 'doOrder profit ctor');
assertTrue($GLOBALS['messageSendLog'][0][0] === 'orderbuy', 'doOrder buy notify');

$DB = new TraceDB();
$DB->tools[5] = array_merge($baseTool, ['is_curl' => 4, 'name' => '测试卡密']);
$DB->faka = [
    1 => ['kid' => 1, 'km' => 'CARD-AAA', 'pw' => 'p1', 'orderid' => 0],
    2 => ['kid' => 2, 'km' => 'CARD-BBB', 'pw' => 'p2', 'orderid' => 0],
];
$GLOBALS['mailLog'] = [];
$GLOBALS['messageSendLog'] = [];
assertTrue(doOrder($basePay, false) === 501, 'doOrder faka id');
assertTrue(strpos($GLOBALS['mailLog'][1]['msg'], 'CARD-AAA') !== false, 'doOrder faka mail');
assertTrue($GLOBALS['messageSendLog'][0][0] === 'faka_stock', 'doOrder faka stock notify');

$DB = new TraceDB();
$DB->sites[8] = ['zid' => 8, 'rmb' => 20, 'rmbtc' => 0, 'power' => 1];
$pay = $basePay;
$pay['tid'] = -1;
$pay['input'] = '8';
$pay['money'] = '100.00';
assertTrue(processOrder($pay) === true, 'processOrder recharge');

$DB = new TraceDB();
$DB->sites[8] = ['zid' => 8, 'rmb' => 20, 'rmbtc' => 0, 'power' => 1];
$pay['money'] = '120.00';
assertTrue(processOrder($pay) === true, 'processOrder recharge gift');
$giftPoint = null;
foreach ($DB->calls as $call) {
    if ($call[0] === 'exec' && isset($call[2][':action']) && $call[2][':action'] === '赠送') {
        $giftPoint = $call[2];
    }
}
assertTrue($giftPoint !== null && (float) $giftPoint[':point'] === 12.0, 'processOrder gift 10%');

$DB = new TraceDB();
$DB->tools[5] = $baseTool;
$DB->tools[6] = array_merge($baseTool, ['tid' => 6, 'name' => '商品B']);
$DB->cart[11] = [
    'id' => 11, 'tid' => 5, 'input' => 'a@qq.com|x', 'num' => 1, 'money' => '5.00',
    'zid' => 2, 'userid' => 'cookie99', 'blockdj' => 0, 'status' => 1,
];
$DB->cart[12] = [
    'id' => 12, 'tid' => 6, 'input' => 'b@qq.com|y', 'num' => 2, 'money' => '10.00',
    'zid' => 2, 'userid' => 'cookie99', 'blockdj' => 0, 'status' => 1,
];
$pay = $basePay;
$pay['tid'] = -3;
$pay['input'] = '11|12';
assertTrue(processOrder($pay) === 502, 'processOrder cart last id');

echo "core-order parity checks passed\n";
