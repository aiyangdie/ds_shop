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

function third_call($type, $config, $method, $arguments = [])
{
    $class = '\\plugins\\third_' . $type;
    if (!class_exists($class) || !method_exists($class, $method)) {
        return false;
    }

    $plugin = new $class($config);
    return call_user_func_array([$plugin, $method], $arguments);
}

function addPointRecord($siteId, $point = 0, $action = '提成', $remark = null)
{
    global $DB;

    $DB->exec(
        'INSERT INTO `pre_points` (`zid`, `action`, `point`, `bz`, `addtime`) VALUES (:zid, :action, :point, :bz, NOW())',
        [':zid' => $siteId, ':action' => $action, ':point' => $point, ':bz' => $remark]
    );
}

function changeUserMoney($siteId, $money, $increase = true, $action = null, $remark = null, $orderId = null)
{
    global $DB, $conf;

    if ($money <= 0) {
        return false;
    }

    $site = $DB->getRow("SELECT zid,rmb,rmbtc FROM pre_site WHERE zid='{$siteId}' LIMIT 1");
    if (!$site) {
        return false;
    }

    $newBalance = $increase ? $site['rmb'] + $money : $site['rmb'] - $money;
    $newWithdrawable = $site['rmbtc'];
    $status = 0;

    if ($increase && ($action === '提成' || $action === '奖励')) {
        if (!$conf['tixian_limit'] || ($conf['tixian_limit'] == 1 && !$conf['tixian_days'])) {
            $newWithdrawable += $money;
            $status = 1;
        }
    } elseif ($increase && $action === '退回') {
        $newWithdrawable += $money;
    } elseif (!$increase && $newWithdrawable > $newBalance) {
        $newWithdrawable = $newBalance;
    }

    $result = $DB->exec(
        "UPDATE `pre_site` SET `rmb`='{$newBalance}',`rmbtc`='{$newWithdrawable}' WHERE `zid`='{$siteId}'"
    );
    $DB->exec(
        'INSERT INTO `pre_points` (`zid`, `action`, `point`, `bz`, `addtime`, `orderid`, `status`) VALUES (:zid, :action, :point, :bz, NOW(), :orderid, :status)',
        [
            ':zid' => $siteId,
            ':action' => $action,
            ':point' => $money,
            ':bz' => $remark,
            ':orderid' => $orderId,
            ':status' => $status,
        ]
    );

    return $result;
}

function rollbackPoint($orderId)
{
    global $DB;

    $result = $DB->query(
        "SELECT A.id,A.zid,A.point,A.status,B.rmb,B.rmbtc FROM pre_points A LEFT JOIN pre_site B ON A.zid=B.zid WHERE A.orderid='{$orderId}' AND A.action='提成' LIMIT 2"
    );
    while ($row = $result->fetch()) {
        $set = '`rmb`=`rmb`-' . $row['point'];
        if ($row['status']) {
            $set .= ',`rmbtc`=`rmbtc`-' . $row['point'];
        }
        $DB->exec("UPDATE pre_site SET {$set} WHERE zid='{$row['zid']}'");
        $DB->exec("DELETE FROM pre_points WHERE id='{$row['id']}'");
    }

    return true;
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

function ordername_replace($template, $productName, $tradeNumber)
{
    return str_replace(
        ['[name]', '[order]', '[time]'],
        [$productName, $tradeNumber, time()],
        $template
    );
}

function merge_site_conf($configuration, $site)
{
    $always = [
        'zid', 'sitename', 'title', 'keywords', 'description',
        'ktfz_price', 'ktfz_price2', 'ktfz_domain', 'appurl',
    ];
    foreach ($always as $key) {
        if (array_key_exists($key, $site)) {
            $configuration[$key] = $site[$key];
        }
    }

    if (!empty($configuration['fenzhan_template']) || empty($configuration['template'])) {
        $configuration['template'] = array_key_exists('template', $site) ? $site['template'] : null;
    }
    if (!empty($configuration['fenzhan_edithtml'])) {
        foreach (['anounce', 'bottom', 'modal', 'alert'] as $key) {
            if (array_key_exists($key, $site)) {
                $configuration[$key] = $site[$key];
            }
        }
    }
    if (!empty($configuration['fenzhan_kfqq'])) {
        foreach (['kfqq', 'kfwx'] as $key) {
            if (array_key_exists($key, $site)) {
                $configuration[$key] = $site[$key];
            }
        }
    }

    return $configuration;
}

function pay_api($unused = true, $index = 1)
{
    global $conf;

    $key = $index == 1 ? 'epay_url' : 'epay_url' . $index;
    return array_key_exists($key, $conf) ? $conf[$key] : null;
}

function get_pay_api($paymentType)
{
    global $conf;

    if (!in_array($paymentType, ['alipay', 'qqpay', 'wxpay'], true)) {
        exit('ERROR');
    }

    $api = isset($conf[$paymentType . '_api']) ? (int) $conf[$paymentType . '_api'] : 0;
    if ($api === 2) {
        $index = 1;
    } elseif ($api === 8) {
        $index = 2;
    } elseif ($api === 9) {
        $index = 3;
    } else {
        exit('ERROR');
    }

    $suffix = $index === 1 ? '' : (string) $index;
    return [
        'url' => pay_api(true, $index),
        'pid' => isset($conf['epay_pid' . $suffix]) ? $conf['epay_pid' . $suffix] : null,
        'key' => isset($conf['epay_key' . $suffix]) ? $conf['epay_key' . $suffix] : null,
        'channel' => 'epay' . $index,
    ];
}

