<?php

/**
 * Assert live drop-in files are readable and not nested-eval / goto payloads.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'includes/core.func.php',
    'includes/ajax.func.php',
    'includes/common.php',
    'admin/account.php',
    'admin/article.php',
    'admin/classlist.php',
    'admin/clone.php',
    'admin/fakalist.php',
    'admin/invite.php',
    'admin/orderjk.php',
    'admin/pricejk.php',
    'admin/set.php',
    'admin/shequlist.php',
    'admin/shopedit.php',
    'admin/shoplist.php',
    'admin/sitelist.php',
    'admin/userlist.php',
];

foreach ($files as $rel) {
    $src = file_get_contents($root . '/' . $rel);
    if ($src === false) {
        throw new RuntimeException('missing ' . $rel);
    }
    if (preg_match('/\bgoto\s+[a-zA-Z_][a-zA-Z0-9_]*\s*;/', $src)) {
        throw new RuntimeException($rel . ' still contains goto labels');
    }
    if (strlen($src) > 20000 && strpos($src, 'base64_decode') !== false && strpos($src, 'eval(') !== false) {
        throw new RuntimeException($rel . ' still looks like nested-eval payload');
    }
}

$ajax = file_get_contents($root . '/includes/ajax.func.php');
if (strpos($ajax, "require __DIR__") !== false) {
    throw new RuntimeException('includes/ajax.func.php still requires sibling files');
}
if (strpos($ajax, '/deobfuscated/') !== false) {
    throw new RuntimeException('includes/ajax.func.php still mentions deobfuscated/');
}
foreach ([
    'getDatePoint', 'getFakaInput', 'uploadimg', 'setToolSort', 'setClassSort',
    'getshareid', 'validate_qzone', 'getshuoshuo', 'getrizhi', 'get_app_token',
    'processInvite', 'fanghongdwz', 'qrcodelogin', 'vaptcha_verify',
    'display_third_title', 'article_url', 'adminpermission',
] as $name) {
    if (strpos($ajax, 'function ' . $name) === false) {
        throw new RuntimeException('includes/ajax.func.php missing function ' . $name);
    }
}

$core = file_get_contents($root . '/includes/core.func.php');
foreach (['getSetting', 'saveSetting', 'processOrder', 'doOrder', 'shequ_get_curl'] as $name) {
    if (strpos($core, 'function ' . $name) === false) {
        throw new RuntimeException('includes/core.func.php missing function ' . $name);
    }
}

echo "live recovered drop-in checks passed\n";
