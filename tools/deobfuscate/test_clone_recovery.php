<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = file_get_contents($root . '/deobfuscated/recovered/admin/clone.php');
if ($src === false) {
    throw new RuntimeException('missing recovered clone');
}

foreach ([
    "\$title = '克隆站点'",
    "adminpermission('super', 1)",
    'api.php?act=clone&key=',
    '无法自己克隆自己',
    'TRUNCATE TABLE `pre_class`',
    'INSERT INTO `pre_tools`',
    'clone_shequ',
    'ajax.php?act=checkclone',
    'set.php?mod=cloneset',
] as $needle) {
    if (strpos($src, $needle) === false) {
        throw new RuntimeException('missing fragment: ' . $needle);
    }
}
if (substr_count($src, 'goto ') !== 0) {
    throw new RuntimeException('recovered clone still contains goto');
}
echo "clone recovery checks passed\n";
