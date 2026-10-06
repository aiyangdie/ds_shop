<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = file_get_contents($root . '/deobfuscated/recovered/admin/fakalist.php');
if ($src === false) {
    throw new RuntimeException('missing recovered fakalist');
}

foreach ([
    "\$title = '发卡库存管理'",
    "adminpermission('faka', 1)",
    'SELECT * FROM pre_class WHERE active=1 order by sort asc',
    "\$my === 'move'",
    "update pre_tools set active=1 where tid=",
    "update pre_tools set active=0 where tid=",
    "DELETE FROM pre_tools WHERE tid=",
    'SELECT count(*) from pre_tools where is_curl=4',
    'leftcount',
    'sellcount',
    'fakakms.php?tid=',
    'ajax_shop.php?act=setTools',
] as $needle) {
    if (strpos($src, $needle) === false) {
        throw new RuntimeException('missing fragment: ' . $needle);
    }
}
if (substr_count($src, 'goto ') !== 0) {
    throw new RuntimeException('recovered fakalist still contains goto');
}
echo "fakalist recovery checks passed\n";
