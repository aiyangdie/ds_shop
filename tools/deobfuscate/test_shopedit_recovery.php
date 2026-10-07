<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = file_get_contents($root . '/deobfuscated/recovered/admin/shopedit.php');
if ($src === false) {
    throw new RuntimeException('missing recovered shopedit');
}

foreach ([
    "\$title = '商品管理'",
    "adminpermission('shop', 1)",
    "\$my === 'add_submit'",
    "\$my === 'edit_submit'",
    "\$my === 'delete'",
    'INSERT INTO `pre_tools`',
    'UPDATE `pre_tools` SET `cid`=:cid',
    '保存错误，商品名称和价格不能为空',
    'assets/js/shopedit.js?ver=',
    'shopdesc_editor',
    'select2/4.0.10',
    'display_third_title($res[\'type\'])',
    'onsubmit="return checkinput()"',
] as $needle) {
    if (strpos($src, $needle) === false) {
        throw new RuntimeException('missing fragment: ' . $needle);
    }
}
if (substr_count($src, 'goto ') !== 0) {
    throw new RuntimeException('recovered shopedit still contains goto');
}
echo "shopedit recovery checks passed\n";
