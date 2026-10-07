<?php

declare(strict_types=1);

final class RecordingCache
{
    public $calls = [];

    public function get($key)
    {
        $this->calls[] = ['get', [$key]];
        return 'cache-hit';
    }
}

final class RecordingDatabase
{
    public $calls = [];
    public $row = null;
    public $queryRows = [];

    public function getColumn($query, $parameters = [])
    {
        $this->calls[] = ['getColumn', [$query, $parameters]];
        return 'db-hit';
    }

    public function exec($query, $parameters = [])
    {
        $this->calls[] = ['exec', [$query, $parameters]];
        return 'db-hit';
    }

    public function getRow($query)
    {
        $this->calls[] = ['getRow', [$query]];
        return $this->row;
    }

    public function query($query)
    {
        $this->calls[] = ['query', [$query]];
        return new RecordingResult($this->queryRows);
    }
}

final class RecordingResult
{
    private $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function fetch() { return array_shift($this->rows); }
}

eval(<<<'PHP'
namespace plugins;
class third_test
{
    private $config;
    public function __construct($config) { $this->config = $config; }
    public function ping($value) { return [$this->config, $value]; }
}
PHP
);

function assertSameValue($expected, $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ' mismatch: ' . var_export($actual, true));
    }
}

$CACHE = new RecordingCache();
$DB = new RecordingDatabase();
$conf = ['tixian_limit' => 1, 'tixian_days' => 3];
require dirname(__DIR__, 2) . '/deobfuscated/recovered/core-simple.php';

assertSameValue(null, getSetting('alpha', false), 'getSetting cache result');
assertSameValue([['get', ['alpha']]], $CACHE->calls, 'getSetting cache calls');

$CACHE->calls = [];
$DB->calls = [];
assertSameValue('db-hit', getSetting('beta', true), 'getSetting database result');
assertSameValue([
    ['getColumn', ['SELECT v FROM pre_config WHERE k=:key limit 1', [':key' => 'beta']]],
], $DB->calls, 'getSetting database calls');

$DB->calls = [];
assertSameValue('db-hit', saveSetting('gamma', 'delta'), 'saveSetting result');
assertSameValue([
    ['exec', ['REPLACE INTO pre_config SET v=:value,k=:key', [':key' => 'gamma', ':value' => 'delta']]],
], $DB->calls, 'saveSetting calls');

assertSameValue(true, epay_check('anything'), 'epay_check result');
assertSameValue([['token' => 7], 'hello'], third_call('test', ['token' => 7], 'ping', ['hello']), 'third_call result');
assertSameValue(false, third_call('missing', [], 'ping'), 'third_call missing class');
assertSameValue(false, third_call('test', [], 'missing'), 'third_call missing method');

$DB->calls = [];
assertSameValue(null, addPointRecord(12, -3.5, '测试备注', 99), 'addPointRecord result');
assertSameValue([[
    'exec',
    [
        'INSERT INTO `pre_points` (`zid`, `action`, `point`, `bz`, `addtime`) VALUES (:zid, :action, :point, :bz, NOW())',
        [':zid' => 12, ':action' => '测试备注', ':point' => -3.5, ':bz' => 99],
    ],
]], $DB->calls, 'addPointRecord calls');

$DB->calls = [];
$DB->row = ['zid' => 8, 'rmb' => 22, 'rmbtc' => 20];
assertSameValue('db-hit', changeUserMoney(8, 5, false, '消费', '备注', 77), 'changeUserMoney result');
assertSameValue("UPDATE `pre_site` SET `rmb`='17',`rmbtc`='17' WHERE `zid`='8'", $DB->calls[1][1][0], 'changeUserMoney update');
assertSameValue(0, $DB->calls[2][1][1][':status'], 'changeUserMoney status');

$DB->calls = [];
$DB->queryRows = [['id' => 3, 'zid' => 8, 'point' => 5, 'status' => 1, 'rmb' => 100, 'rmbtc' => 20]];
assertSameValue(true, rollbackPoint(66), 'rollbackPoint result');
assertSameValue("UPDATE pre_site SET `rmb`=`rmb`-5,`rmbtc`=`rmbtc`-5 WHERE zid='8'", $DB->calls[1][1][0], 'rollbackPoint update');
assertSameValue("DELETE FROM pre_points WHERE id='3'", $DB->calls[2][1][0], 'rollbackPoint delete');

$DB->calls = [];
assertSameValue(null, log_result('create', ['a' => 1], ['code' => 0, 'id' => 88], 1), 'log_result result');
assertSameValue('下单成功!订单号:88', $DB->calls[0][1][1][':res'], 'log_result success text');

$DB->calls = [];
assertSameValue(2, batchSql(" INSERT INTO a VALUES (1); UPDATE a SET x=2; "), 'batchSql result');
assertSameValue('INSERT INTO a VALUES (1)', $DB->calls[0][1][0], 'batchSql first statement');
assertSameValue('UPDATE a SET x=2', $DB->calls[1][1][0], 'batchSql second statement');

$fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'recovered-core-' . bin2hex(random_bytes(6));
mkdir($fixture . DIRECTORY_SEPARATOR . 'nested', 0777, true);
file_put_contents($fixture . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'file.txt', 'test');
assertSameValue(true, rm_dir($fixture), 'rm_dir result');
assertSameValue(false, file_exists($fixture), 'rm_dir removed fixture');
assertSameValue(false, rm_dir($fixture), 'rm_dir missing result');

$before = time();
$orderName = ordername_replace('购买[name]-[order]-[time]', '测试商品', 'T2026');
$after = time();
assertSameValue(true, strpos($orderName, '购买测试商品-T2026-') === 0, 'ordername_replace prefix');
$orderTime = (int) substr($orderName, strrpos($orderName, '-') + 1);
assertSameValue(true, $orderTime >= $before && $orderTime <= $after, 'ordername_replace time');

$merged = merge_site_conf(
    ['template' => 'main', 'kfqq' => 'main', 'fenzhan_template' => 1, 'fenzhan_kfqq' => 1, 'fenzhan_edithtml' => 0],
    ['sitename' => '分站', 'template' => 'sub', 'kfqq' => 'subqq', 'anounce' => 'blocked']
);
assertSameValue('分站', $merged['sitename'], 'merge_site_conf site name');
assertSameValue('sub', $merged['template'], 'merge_site_conf template');
assertSameValue('subqq', $merged['kfqq'], 'merge_site_conf contact');
assertSameValue(false, array_key_exists('anounce', $merged), 'merge_site_conf blocked html');

$conf = ['epay_url' => 'one', 'epay_url2' => 'two', 'epay_url3' => 'three'];
assertSameValue('one', pay_api(false), 'pay_api primary');
assertSameValue('two', pay_api(true, 2), 'pay_api secondary');
assertSameValue(null, pay_api(true, 9), 'pay_api missing');

$conf += [
    'alipay_api' => 2,
    'wxpay_api' => 9,
    'epay_pid' => 'pid1', 'epay_key' => 'key1',
    'epay_pid3' => 'pid3', 'epay_key3' => 'key3',
];
assertSameValue(
    ['url' => 'one', 'pid' => 'pid1', 'key' => 'key1', 'channel' => 'epay1'],
    get_pay_api('alipay'),
    'get_pay_api primary'
);
assertSameValue(
    ['url' => 'three', 'pid' => 'pid3', 'key' => 'key3', 'channel' => 'epay3'],
    get_pay_api('wxpay'),
    'get_pay_api third'
);

echo "core-simple parity checks passed\n";

