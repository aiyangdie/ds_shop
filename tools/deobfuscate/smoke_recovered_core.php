<?php

/**
 * Smoke-load the assembled recovered core without touching the live include path.
 */

declare(strict_types=1);

$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0) Safari/537.36';
$_SERVER['HTTP_HOST'] = 'smoke.test';

$conf = [];
$root = dirname(__DIR__, 2);
require $root . '/deobfuscated/recovered/core.func.php';

$expected = [
    'curl_get', 'shequ_get_curl', 'send_mail', 'send_wechat', 'getSetting', 'saveSetting',
    'doOrder', 'processOrder', 'do_curl', 'third_call', 'do_goods', 'changeUserMoney',
    'addPointRecord', 'rollbackPoint', 'log_result', 'sysmsg', 'batchSql', 'rm_dir',
    'sec_check', 'epay_check', 'pay_api', 'get_pay_api', 'merge_site_conf', 'ordername_replace',
];

foreach ($expected as $name) {
    if (!function_exists($name)) {
        throw new RuntimeException('missing ' . $name);
    }
}

if (epay_check(null) !== true) {
    throw new RuntimeException('epay_check failed');
}
if (ordername_replace('[name]-[order]', '商品A', 'T1') !== '商品A-T1') {
    throw new RuntimeException('ordername_replace failed');
}
if (pay_api(true, 1) !== null) {
    // conf unset is fine; just ensure callable
}

$merged = merge_site_conf(['sitename' => '主站'], ['zid' => 2, 'sitename' => '分站']);
if ($merged['sitename'] !== '分站' || $merged['zid'] !== 2) {
    throw new RuntimeException('merge_site_conf failed');
}

echo "recovered core.func.php smoke checks passed (" . count($expected) . " functions)\n";
