<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = file_get_contents($root . '/deobfuscated/recovered/admin/shequlist.php');
if ($src === false) {
    throw new RuntimeException('missing recovered shequlist');
}

foreach ([
    "\$title = '网站对接配置'",
    "adminpermission('shequ', 1)",
    'Plugin::getThirdPluginsList',
    "\$my === 'refresh'",
    'INSERT INTO `pre_shequ`',
    'UPDATE pre_shequ SET url=:url',
    'clone_shequ',
    'pluginArray',
    'ajax.php?act=checkshequ',
] as $needle) {
    if (strpos($src, $needle) === false) {
        throw new RuntimeException('missing fragment: ' . $needle);
    }
}
if (substr_count($src, 'goto ') !== 0) {
    throw new RuntimeException('recovered shequlist still contains goto');
}
echo "shequlist recovery checks passed\n";
