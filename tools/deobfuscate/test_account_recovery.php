<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = file_get_contents($root . '/deobfuscated/recovered/admin/account.php');
if ($src === false) {
    throw new RuntimeException('missing recovered account');
}

foreach ([
    "\$title = '员工管理'",
    "adminpermission('account', 1)",
    "\$my === 'add_submit'",
    "insert into `pre_account`",
    'admin_user',
    'permission[]',
    'update pre_account set username=:username',
    "DELETE FROM pre_account WHERE id=",
    'SELECT count(*) from pre_account',
] as $needle) {
    if (strpos($src, $needle) === false) {
        throw new RuntimeException('missing fragment: ' . $needle);
    }
}
if (substr_count($src, 'goto ') !== 0) {
    throw new RuntimeException('recovered account still contains goto');
}
echo "account recovery checks passed\n";
