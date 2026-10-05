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

echo "core-simple parity checks passed\n";

