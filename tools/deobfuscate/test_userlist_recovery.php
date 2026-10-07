<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = file_get_contents($root . '/deobfuscated/recovered/admin/userlist.php');
if ($src === false) {
    throw new RuntimeException('missing recovered userlist');
}

foreach ([
    "\$title = '用户管理'",
    "adminpermission('site', 1)",
    "\$my === 'add'",
    "\$my === 'edit'",
    "\$my === 'add_submit'",
    "\$my === 'edit_submit'",
    "insert into `pre_site`",
    'SELECT count(*) from pre_site WHERE power=0',
    'assets/js/userlist.js?ver=',
    '用户名已存在',
] as $needle) {
    if (strpos($src, $needle) === false) {
        throw new RuntimeException('missing fragment: ' . $needle);
    }
}
if (substr_count($src, 'goto ') !== 0) {
    throw new RuntimeException('recovered userlist still contains goto');
}
echo "userlist recovery checks passed\n";
