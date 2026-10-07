<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = file_get_contents($root . '/deobfuscated/recovered/admin/invite.php');
if ($src === false) {
    throw new RuntimeException('missing recovered invite');
}

foreach ([
    "\$title = '推广商品列表'",
    "adminpermission('shop', 1)",
    "\$my === 'add'",
    "\$my === 'edit'",
    "\$my === 'add_submit'",
    "insert into `pre_inviteshop`",
    '该商品已经添加过了',
    'assets/js/invite.js?ver=',
    'inviteedit.js?ver=',
    'update `pre_inviteshop`',
] as $needle) {
    if (strpos($src, $needle) === false) {
        throw new RuntimeException('missing fragment: ' . $needle);
    }
}
if (substr_count($src, 'goto ') !== 0) {
    throw new RuntimeException('recovered invite still contains goto');
}
echo "invite recovery checks passed\n";
