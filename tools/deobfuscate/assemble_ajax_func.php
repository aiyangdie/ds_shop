<?php

/**
 * Assemble recovered ajax fragments into one drop-in ajax.func.php.
 *
 * Usage:
 *   php tools/deobfuscate/assemble_ajax_func.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$recovered = $root . '/deobfuscated/recovered';
$out = $recovered . '/ajax.func.php';

$parts = [
    'ajax-basic.php',
    'ajax-rest.php',
];

$banner = <<<'PHP'
<?php

/**
 * Readable replacement for includes/ajax.func.php.
 *
 * Assembled from ajax-basic.php and ajax-rest.php.
 * This file is self-contained and can be copied to includes/ajax.func.php.
 *
 * Recovered functions 1-17:
 * getDatePoint, getFakaInput, uploadimg, setToolSort, setClassSort,
 * getshareid, validate_qzone, getshuoshuo, getrizhi, get_app_token,
 * processInvite, fanghongdwz, qrcodelogin, vaptcha_verify,
 * display_third_title, article_url, adminpermission
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
    $src = preg_replace('/^\/\*\*.*?\*\/\s*/s', '', $src, 1);
    $body .= "\n// ---- from {$file} ----\n\n" . rtrim($src) . "\n";
}

file_put_contents($out, $banner . $body);
echo "Wrote {$out} (" . strlen($banner . $body) . " bytes)\n";

$expected = [
    'getDatePoint', 'getFakaInput', 'uploadimg', 'setToolSort', 'setClassSort',
    'getshareid', 'validate_qzone', 'getshuoshuo', 'getrizhi', 'get_app_token',
    'processInvite', 'fanghongdwz', 'qrcodelogin', 'vaptcha_verify',
    'display_third_title', 'article_url', 'adminpermission',
];

if (!function_exists('get_curl')) {
    function get_curl(...$arguments)
    {
        return '';
    }
}

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
if (strpos($banner . $body, "require __DIR__") !== false) {
    fwrite(STDERR, "Assembled ajax.func.php still contains require __DIR__\n");
    exit(3);
}
echo "All 17 public ajax functions are present.\n";
