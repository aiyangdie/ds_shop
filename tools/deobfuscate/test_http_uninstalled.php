<?php
$base = 'http://127.0.0.1:18082';
$paths = ['/', '/install/', '/admin/login.php', '/user/login.php', '/ajax.php'];
foreach ($paths as $p) {
    $c = @file_get_contents($base . $p);
    if ($c === false) {
        echo $p . " FETCH_FAIL\n";
        continue;
    }
    $kind = 'OTHER';
    if (strpos($c, '你还没安装') !== false) {
        $kind = 'UNINSTALLED';
    } elseif (strpos($c, '安装') !== false) {
        $kind = 'INSTALL_UI';
    }
    echo $p . ' ' . strlen($c) . ' ' . $kind . "\n";
}
