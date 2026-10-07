<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = file_get_contents($root . '/deobfuscated/recovered/admin/sitelist.php');
if ($src === false) {
    throw new RuntimeException('missing recovered sitelist');
}

foreach ([
    "\$title = '分站管理'",
    "adminpermission('site', 1)",
    "\$my === 'replace_do'",
    "\$my === 'add2_submit'",
    'INSERT INTO `pre_site`',
    'fenzhan_remain',
    'assets/js/sitelist.js?ver=',
    'UPDATE pre_site SET `anounce`=NULL',
    'siteprice.php?zid=',
] as $needle) {
    if (strpos($src, $needle) === false) {
        throw new RuntimeException('missing fragment: ' . $needle);
    }
}
if (substr_count($src, 'goto ') !== 0) {
    throw new RuntimeException('recovered sitelist still contains goto');
}
echo "sitelist recovery checks passed\n";
