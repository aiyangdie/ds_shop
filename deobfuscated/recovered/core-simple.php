<?php

/**
 * First manually recovered functions from includes/core.func.php.
 *
 * These implementations are intentionally kept outside the live include path
 * until the complete core replacement passes parity tests.
 */

function getSetting($key, $fromDatabase = false)
{
    global $CACHE, $DB;

    if ($fromDatabase) {
        return $DB->getColumn(
            'SELECT v FROM pre_config WHERE k=:key limit 1',
            [':key' => $key]
        );
    }

    // The protected implementation invokes the cache getter but does not
    // propagate its return value. Keep that observed behavior for parity.
    $CACHE->get($key);
    return null;
}

function saveSetting($key, $value)
{
    global $DB;

    return $DB->exec(
        'REPLACE INTO pre_config SET v=:value,k=:key',
        [':key' => $key, ':value' => $value]
    );
}

function epay_check($unused)
{
    return true;
}

function addPointRecord($siteId, $point = 0, $action = '提成', $remark = null)
{
    global $DB;

    $DB->exec(
        'INSERT INTO `pre_points` (`zid`, `action`, `point`, `bz`, `addtime`) VALUES (:zid, :action, :point, :bz, NOW())',
        [':zid' => $siteId, ':action' => $action, ':point' => $point, ':bz' => $remark]
    );
}

function log_result($action, $parameters, $result, $status = 0)
{
    global $DB;

    if (is_array($result) && isset($result['code'], $result['id']) && (int) $result['code'] === 0) {
        $resultText = '下单成功!订单号:' . $result['id'];
    } elseif (is_array($result) && isset($result['message'])) {
        $resultText = $result['message'];
    } elseif (is_array($result) && isset($result['msg'])) {
        $resultText = $result['msg'];
    } else {
        $resultText = htmlspecialchars(json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    $DB->exec(
        'INSERT INTO `pre_logs` (`action`, `param`, `result`, `addtime`, `status`) VALUES (:action, :param, :res, NOW(), :status)',
        [':action' => $action, ':param' => $parameters, ':res' => $resultText, ':status' => $status]
    );
}

function batchSql($sql)
{
    global $DB;

    $count = 0;
    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }

        $DB->exec($statement);
        $count++;
    }

    return $count;
}

function rm_dir($directory)
{
    if (!is_dir($directory)) {
        return false;
    }

    $handle = opendir($directory);
    if ($handle === false) {
        return false;
    }

    while (($entry = readdir($handle)) !== false) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($path)) {
            rm_dir($path);
        } else {
            unlink($path);
        }
    }
    closedir($handle);

    return rmdir($directory);
}

