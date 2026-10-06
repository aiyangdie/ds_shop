<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = file_get_contents($root . '/deobfuscated/recovered/admin/orderjk.php');
if ($src === false) {
    throw new RuntimeException('missing recovered orderjk');
}

$required = [
    "\$title = '订单状态监控'",
    "adminpermission('shequ', 1)",
    "saveSetting('updatestatus'",
    "saveSetting('updatestatus_interval'",
    'updatestatus_lasttime',
    'cron.php?do=updatestatus',
    'showresult()',
];

foreach ($required as $needle) {
    if (strpos($src, $needle) === false) {
        throw new RuntimeException('missing fragment: ' . $needle);
    }
}

if (substr_count($src, 'goto ') !== 0) {
    throw new RuntimeException('recovered orderjk still contains goto');
}

echo "orderjk recovery checks passed\n";
