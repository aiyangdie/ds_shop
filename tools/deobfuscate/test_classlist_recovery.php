<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$php = $root . '/deobfuscated/recovered/admin/classlist.php';
$src = file_get_contents($php);
if ($src === false) {
    throw new RuntimeException('missing recovered classlist');
}

$required = [
    "include '../includes/common.php'",
    "\$title = '分类管理'",
    "include './head.php'",
    '$islogin == 1',
    "adminpermission('shop', 1)",
    "\$_GET['my']",
    "\$my === 'qk2'",
    'TRUNCATE TABLE `pre_class`',
    '清空成功',
    '清空失败',
    "\$my === 'classimg'",
    'SELECT * FROM pre_class WHERE 1 order by sort asc',
    'saveAllImages()',
    'listTable',
    'assets/js/classlist.js?ver=',
    'jquery.dragsort-0.5.2.min.js',
];

foreach ($required as $needle) {
    if (strpos($src, $needle) === false) {
        throw new RuntimeException('missing fragment: ' . $needle);
    }
}

if (substr_count($src, 'goto ') !== 0) {
    throw new RuntimeException('recovered classlist still contains goto');
}

echo "classlist recovery checks passed\n";
