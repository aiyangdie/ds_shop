<?php
/**
 * 插入一条测试打包任务到队列（用本机可访问的站点 URL）
 */
require dirname(dirname(__DIR__)) . '/config.php';
$m = new mysqli($dbconfig['host'], $dbconfig['user'], $dbconfig['pwd'], $dbconfig['dbname'], intval($dbconfig['port']));
$m->set_charset('utf8mb4');
$t = $dbconfig['dbqz'] . '_apps';

// ensure columns
$cols = array('url','theme','icon_path','splash_path','progress','error','build_log','build_status','nonav','updatetime');
foreach ($cols as $c) {
    $r = $m->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    if ($r && $r->num_rows === 0) {
        // best-effort; db_setup already did this
    }
}

$url = 'http://127.0.0.1:8080/';
$domain = '127.0.0.1';
$name = '本站测试App';
$package = 'com.appshell.site.z1local';
$now = date('Y-m-d H:i:s');

$exist = $m->query("SELECT id FROM `$t` WHERE domain='$domain' LIMIT 1");
if ($exist && $exist->num_rows) {
    $id = intval($exist->fetch_assoc()['id']);
    $m->query("UPDATE `$t` SET name='$name',package='$package',url='$url',theme='#1f6feb',status=2,build_status=0,progress=0,error=NULL,android_url=NULL,updatetime='$now',zid=1 WHERE id=$id");
} else {
    $m->query("INSERT INTO `$t` (zid,taskid,domain,name,icon,package,addtime,status,url,theme,progress,build_status,updatetime) VALUES (1,0,'$domain','$name','/assets/uploads/apps/default_icon.png','$package','$now',2,'$url','#1f6feb',0,0,'$now')");
    $id = intval($m->insert_id);
    $m->query("UPDATE `$t` SET taskid=$id WHERE id=$id");
}
echo "queued id=$id package=$package\n";
echo "run: tools\\appbuild\\run_worker.bat\n";
