<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = file_get_contents($root . '/deobfuscated/recovered/common.php');
if ($src === false) {
    throw new RuntimeException('missing recovered common.php');
}

foreach ([
    "define('IN_CRONLITE', true)",
    "define('VERSION', '2065')",
    "define('DB_VERSION', '2055')",
    'PLUGIN_ROOT',
    '你还没安装',
    'install/update.php',
    'core.func.php',
    'member.php',
    'select * from pre_site where domain=',
    'cdnpublic',
    'sec_defend',
] as $needle) {
    if (strpos($src, $needle) === false) {
        throw new RuntimeException('missing fragment: ' . $needle);
    }
}
if (substr_count($src, 'goto ') !== 0) {
    throw new RuntimeException('recovered common still contains goto');
}
echo "common recovery checks passed\n";
