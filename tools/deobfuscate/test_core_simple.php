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

echo "core-simple parity checks passed\n";

