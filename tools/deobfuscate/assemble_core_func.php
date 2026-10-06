<?php

/**
 * Assemble recovered core fragments into one drop-in core.func.php.
 *
 * Usage:
 *   php tools/deobfuscate/assemble_core_func.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$recovered = $root . '/deobfuscated/recovered';
$out = $recovered . '/core.func.php';

$parts = [
    'core-simple.php',
    'core-network.php',
    'core-notify.php',
    'core-order.php',
];

$banner = <<<'PHP'
<?php

/**
 * Readable replacement for includes/core.func.php.
 *
 * Assembled from deobfuscated/recovered/core-*.php.
 * Not wired into the live include path until parity checks pass.
 *
 * Recovered modes 1-24:
 * curl_get, shequ_get_curl, send_mail, send_wechat, getSetting, saveSetting,
 * doOrder, processOrder, do_curl, third_call, do_goods, changeUserMoney,
 * addPointRecord, rollbackPoint, log_result, sysmsg, batchSql, rm_dir,
 * sec_check, epay_check, pay_api, get_pay_api, merge_site_conf, ordername_replace
 */

PHP;

$body = '';
foreach ($parts as $file) {
    $path = $recovered . '/' . $file;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing fragment: {$path}\n");
        exit(1);
    }
    $src = file_get_contents($path);
    $src = preg_replace('/^\s*<\?php\s*/', '', $src, 1);
    // Drop leading file-level docblocks; keep function docs.
    $src = preg_replace('/^\/\*\*.*?\*\/\s*/s', '', $src, 1);
    $body .= "\n// ---- from {$file} ----\n\n" . rtrim($src) . "\n";
}

file_put_contents($out, $banner . $body);
echo "Wrote {$out} (" . strlen($banner . $body) . " bytes)\n";

$expected = [
    'curl_get', 'shequ_get_curl', 'send_mail', 'send_wechat', 'getSetting', 'saveSetting',
    'doOrder', 'processOrder', 'do_curl', 'third_call', 'do_goods', 'changeUserMoney',
    'addPointRecord', 'rollbackPoint', 'log_result', 'sysmsg', 'batchSql', 'rm_dir',
    'sec_check', 'epay_check', 'pay_api', 'get_pay_api', 'merge_site_conf', 'ordername_replace',
];

require $out;
$missing = [];
foreach ($expected as $name) {
    if (!function_exists($name)) {
        $missing[] = $name;
    }
}
if ($missing) {
    fwrite(STDERR, "Missing functions: " . implode(', ', $missing) . "\n");
    exit(2);
}
echo "All 24 public core functions are present.\n";
