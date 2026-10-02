<?php
require dirname(dirname(__DIR__)) . '/config.php';
$m = new mysqli($dbconfig['host'], $dbconfig['user'], $dbconfig['pwd'], $dbconfig['dbname'], intval($dbconfig['port']));
$m->set_charset('utf8mb4');
$t = $dbconfig['dbqz'] . '_config';
$m->query("UPDATE `$t` SET v='C:\\\\Android\\\\Sdk' WHERE k='appcreate_sdk_dir'");
if ($m->affected_rows === 0) {
    $m->query("INSERT INTO `$t` (k,v) VALUES ('appcreate_sdk_dir','C:\\\\Android\\\\Sdk')");
}
echo "sdk updated\n";
