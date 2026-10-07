<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = file_get_contents($root . '/deobfuscated/recovered/admin/shoplist.php');
if ($src === false) {
    throw new RuntimeException('missing recovered shoplist');
}

foreach ([
    "\$title = '商品管理'",
    "adminpermission('shop', 1)",
    'SELECT * FROM pre_price order by id asc',
    "\$my === 'qk2'",
    'TRUNCATE TABLE `pre_tools`',
    'shopedit.php?my=add',
    'listTable',
    'assets/js/shoplist.js?ver=',
    'priceselect',
] as $needle) {
    if (strpos($src, $needle) === false) {
        throw new RuntimeException('missing fragment: ' . $needle);
    }
}
if (substr_count($src, 'goto ') !== 0) {
    throw new RuntimeException('recovered shoplist still contains goto');
}
echo "shoplist recovery checks passed\n";
