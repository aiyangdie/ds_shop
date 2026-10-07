<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = file_get_contents($root . '/deobfuscated/recovered/admin/pricejk.php');
if ($src === false) {
    throw new RuntimeException('missing recovered pricejk');
}

foreach ([
    "\$title = '对接价格监控'",
    "adminpermission('shequ', 1)",
    "saveSetting('pricejk_cid'",
    "saveSetting('pricejk_edit'",
    "saveSetting('pricejk_time'",
    "saveSetting('pricejk_yile'",
    'pricejk_lasttime',
    'pricejk_status',
    'cron.php?do=pricejk',
    'pricejk_cid[]',
] as $needle) {
    if (strpos($src, $needle) === false) {
        throw new RuntimeException('missing fragment: ' . $needle);
    }
}
if (substr_count($src, 'goto ') !== 0) {
    throw new RuntimeException('recovered pricejk still contains goto');
}
echo "pricejk recovery checks passed\n";
