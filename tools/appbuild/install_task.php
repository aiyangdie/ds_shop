<?php
/**
 * 注册 Windows 计划任务：每分钟跑一次 worker
 * 以管理员 PowerShell 执行: php tools/appbuild/install_task.php
 */
$bat = realpath(__DIR__ . '/run_worker.bat');
if (!$bat) {
    fwrite(STDERR, "run_worker.bat missing\n");
    exit(1);
}
$taskName = 'DsShopAppBuildWorker';
$cmd = 'schtasks /Create /F /TN "' . $taskName . '" /SC MINUTE /MO 1 /TR "\\"' . $bat . '\\"" /RL LIMITED';
echo $cmd . "\n";
passthru($cmd, $code);
if ($code === 0) {
    echo "OK: scheduled task $taskName every 1 minute\n";
} else {
    echo "FAILED ($code). Please run as Administrator, or manually create task pointing to:\n$bat\n";
}
exit($code);
