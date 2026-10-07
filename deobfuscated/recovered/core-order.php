<?php

/**
 * Recovered do_goods (mode 11): dock / faka / curl fulfillment for one order.
 */

function do_goods($orderId, $url = null, $post = null)
{
    global $DB, $conf;

    if ($url) {
        return get_curl($url, $post);
    }

    $order = $DB->getRow("SELECT * FROM pre_orders WHERE id='{$orderId}' LIMIT 1");
    $tool = null;
    if ($order) {
        $tool = $DB->getRow("SELECT * FROM pre_tools WHERE tid='{$order['tid']}' LIMIT 1");
    }

    if (!$order || !$tool) {
        return '该商品未配置对接或自动发卡';
    }

    $isCurl = isset($tool['is_curl']) ? (int) $tool['is_curl'] : 0;

    if ($isCurl === 4) {
        return do_goods_faka($order, $tool);
    }

    if ($isCurl === 2) {
        return do_goods_shequ($order, $tool);
    }

    if ($isCurl === 1) {
        return do_goods_curl($order, $tool);
    }

    return '该商品未配置对接或自动发卡';
}

function do_goods_faka(array $order, array $tool)
{
    global $DB, $conf;

    $num = isset($order['value']) ? (int) $order['value'] : 1;
    if ($num < 1) {
        $num = 1;
    }

    $DB->beginTransaction();
    $result = $DB->query(
        "SELECT * FROM pre_faka WHERE tid='{$tool['tid']}' AND orderid=0 ORDER BY kid ASC LIMIT {$num} FOR UPDATE"
    );

    $cards = [];
    while ($result && ($row = $result->fetch())) {
        $cards[] = $row;
    }

    if (!$cards) {
        $DB->commit();
        $DB->exec("UPDATE `pre_orders` SET `status`='0',`djzt`='4' WHERE `id`='{$order['id']}'");
        return '卡密库存不足，发卡失败！';
    }

    $kmdata = '';
    $now = date('Y-m-d H:i:s');
    foreach ($cards as $card) {
        $DB->exec(
            "UPDATE `pre_faka` SET `orderid`='{$order['id']}',`usetime`='{$now}' WHERE `kid`='{$card['kid']}'"
        );
        if (isset($card['pw']) && $card['pw'] !== '' && $card['pw'] !== null) {
            $kmdata .= '卡号：' . $card['km'] . ' 密码：' . $card['pw'] . '<br/>';
        } else {
            $kmdata .= $card['km'] . '<br/>';
        }
    }

    $DB->commit();
    $DB->exec("UPDATE `pre_orders` SET `status`='1',`djzt`='3' WHERE `id`='{$order['id']}'");

    if (!empty($order['input']) && function_exists('checkEmail') && checkEmail($order['input'])) {
        $template = isset($conf['faka_mail']) ? $conf['faka_mail'] : '';
        $body = str_replace(
            ['[kmdata]', '[alert]', '[name]', '[date]', '[email]', '[domain]', '[sitename]'],
            [
                $kmdata,
                isset($tool['alert']) ? $tool['alert'] : '',
                isset($tool['name']) ? $tool['name'] : '',
                $now,
                $order['input'],
                isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
                isset($conf['sitename']) ? $conf['sitename'] : '',
            ],
            $template
        );
        $subject = (isset($conf['sitename']) ? $conf['sitename'] : '') . ' 卡密购买提醒';
        send_mail($order['input'], $subject, $body);
    }

    return '发卡成功！';
}

function do_goods_shequ(array $order, array $tool)
{
    global $DB;

    $shequId = isset($tool['shequ']) ? (int) $tool['shequ'] : 0;
    $shequ = $DB->getRow("SELECT * FROM pre_shequ WHERE id='{$shequId}' LIMIT 1");
    if (!$shequ) {
        return '未配置好网站对接信息';
    }

    $inputs = [
        isset($order['input']) ? $order['input'] : '',
        isset($order['input2']) ? $order['input2'] : '',
        isset($order['input3']) ? $order['input3'] : '',
        isset($order['input4']) ? $order['input4'] : '',
        isset($order['input5']) ? $order['input5'] : '',
    ];
    $num = isset($order['value']) ? $order['value'] : 1;
    $result = third_call(
        $shequ['type'],
        $shequ,
        'do_goods',
        [
            isset($tool['goods_id']) ? $tool['goods_id'] : 0,
            isset($tool['goods_type']) ? $tool['goods_type'] : 0,
            isset($tool['goods_param']) ? $tool['goods_param'] : '',
            $num,
            $inputs,
            isset($order['money']) ? $order['money'] : 0,
            isset($order['tradeno']) ? $order['tradeno'] : '',
            isset($tool['inputs']) ? $tool['inputs'] : '',
        ]
    );

    $param = $shequ['type'] . ':' . $shequ['id']
        . ' goods_id:' . (isset($tool['goods_id']) ? $tool['goods_id'] : '')
        . ' num:' . $num
        . ' data:' . http_build_query($inputs);

    if (is_array($result) && isset($result['code']) && (int) $result['code'] === 0) {
        $djorder = isset($result['id']) ? $result['id'] : null;
        $DB->exec(
            'UPDATE `pre_orders` SET `status`=:status,`djzt`=:djzt,`djorder`=:djorder,result=NULL WHERE `id`=:orderid',
            [
                ':status' => 1,
                ':djzt' => 1,
                ':djorder' => $djorder,
                ':orderid' => $order['id'],
            ]
        );
        $message = '下单成功!订单号:' . $djorder;
        log_result('社区对接', $param, $result, 0);
        return $message;
    }

    if (is_array($result) && (isset($result['message']) || isset($result['msg']))) {
        $error = isset($result['message']) ? $result['message'] : $result['msg'];
        log_result('社区对接', $param, $result, 0);
        return '下单失败：' . $error;
    }

    // Non-array / unexpected payload: still mark as docked without upstream order id.
    if (is_string($result) && $result !== '') {
        $DB->exec(
            'UPDATE `pre_orders` SET `status`=:status,`djzt`=:djzt,`djorder`=:djorder,result=NULL WHERE `id`=:orderid',
            [
                ':status' => 1,
                ':djzt' => 1,
                ':djorder' => null,
                ':orderid' => $order['id'],
            ]
        );
        log_result('社区对接', $param, $result, 0);
        return '下单成功!订单号:';
    }

    log_result('社区对接', $param, $result, 0);
    return '下单失败请查看日志';
}

function do_goods_curl(array $order, array $tool)
{
    $inputs = [
        isset($order['input']) ? $order['input'] : '',
        isset($order['input2']) ? $order['input2'] : '',
        isset($order['input3']) ? $order['input3'] : '',
        isset($order['input4']) ? $order['input4'] : '',
        isset($order['input5']) ? $order['input5'] : '',
    ];

    $curl = isset($tool['curl']) ? $tool['curl'] : '';
    $response = do_curl(
        $curl,
        $inputs,
        isset($order['value']) ? $order['value'] : 1,
        isset($tool['name']) ? $tool['name'] : '',
        isset($order['money']) ? $order['money'] : 0,
        isset($tool['price']) ? $tool['price'] : 0,
        isset($order['id']) ? $order['id'] : 0
    );

    $decoded = json_decode($response, true);
    if (is_array($decoded)) {
        $param = 'url:' . $curl . ' data:' . http_build_query($inputs);
        log_result('自动访问URL', $param, $decoded, 0);
    }

    // Protected core always returns an empty string for the is_curl=1 path.
    return '';
}

/**
 * Recovered doOrder (mode 7): create a goods order and optionally fulfill it.
 *
 * @param array $pay Paid trade row (or cart-derived row)
 * @param bool  $profit Whether to write site profit via lib\Price
 * @return int Inserted order id
 */
function doOrder($pay, $profit)
{
    global $DB, $conf, $date;

    $inputs = explode('|', isset($pay['input']) ? $pay['input'] : '');
    $inputColumns = [];
    for ($i = 0; $i < 5; $i++) {
        $inputColumns[$i] = array_key_exists($i, $inputs) ? $inputs[$i] : null;
    }

    $tid = isset($pay['tid']) ? $pay['tid'] : 0;
    $num = isset($pay['num']) ? $pay['num'] : 1;
    $tool = $DB->getRow("select * from pre_tools where tid='{$tid}' limit 1");

    $isCurl = ($tool && isset($tool['is_curl'])) ? (int) $tool['is_curl'] : 0;
    $djzt = ($isCurl === 1 || $isCurl === 2) ? 2 : 0;

    $cost = 0;
    if ($tool) {
        if (!empty($tool['prid'])) {
            $cost = $pay['money'] * 1;
        } else {
            $cost = (isset($tool['cost2']) ? $tool['cost2'] : 0) * $num;
        }
    }

    $DB->exec(
        'INSERT INTO `pre_orders` (`tid`,`zid`,`input`,`input2`,`input3`,`input4`,`input5`,`value`,`userid`,`addtime`,`tradeno`,`money`,`cost`,`status`,`djzt`) VALUES (:tid, :zid, :input, :input2, :input3, :input4, :input5, :value, :userid, :addtime, :tradeno, :money, :cost, :status, :djzt)',
        [
            ':tid' => $tid,
            ':zid' => isset($pay['zid']) ? $pay['zid'] : 0,
            ':input' => $inputColumns[0],
            ':input2' => $inputColumns[1],
            ':input3' => $inputColumns[2],
            ':input4' => $inputColumns[3],
            ':input5' => $inputColumns[4],
            ':value' => $num,
            ':userid' => isset($pay['userid']) ? $pay['userid'] : 0,
            ':addtime' => $date,
            ':tradeno' => isset($pay['trade_no']) ? $pay['trade_no'] : '',
            ':money' => isset($pay['money']) ? $pay['money'] : 0,
            ':cost' => $cost,
            ':status' => 0,
            ':djzt' => $djzt,
        ]
    );
    $orderId = $DB->lastInsertId();

    $DB->exec("UPDATE pre_tools SET sales=sales+{$num} WHERE tid='{$tid}'");
    if ($tool && !empty($tool['stock'])) {
        $DB->exec("UPDATE pre_tools SET stock=stock-{$num} WHERE tid='{$tid}'");
    }

    $notifyStatus = 0;
    $dockFail = null;
    $dockParam = '';

    if ($isCurl === 4) {
        do_order_faka($orderId, $tid, $num, $tool, $inputs);
    } elseif ($isCurl === 2 && empty($pay['blockdj'])) {
        $shequ = do_order_shequ($orderId, $tool, $inputs, $num, $pay, $dockParam, $dockFail);
        if ($shequ) {
            $notifyStatus = 1;
        }
    } elseif ($isCurl === 2 && !empty($pay['blockdj'])) {
        $DB->exec("UPDATE `pre_orders` SET `status`='1',`djzt`='0' WHERE `id`='{$orderId}'");
    } elseif ($isCurl === 1 && empty($pay['blockdj'])) {
        do_order_curl($orderId, $tool, $inputs, $num, $pay);
        $notifyStatus = 1;
    }

    if ($profit) {
        $price = new \lib\Price(isset($pay['zid']) ? $pay['zid'] : 0);
        $price->setToolInfo($tid, $tool);
        $price->setToolProfit(
            $tid,
            $num,
            $tool ? $tool['name'] : null,
            isset($pay['money']) ? $pay['money'] : 0,
            $orderId,
            isset($pay['userid']) ? $pay['userid'] : 0
        );
    }

    $toolName = $tool ? $tool['name'] : null;
    $inputName = $tool ? $tool['input'] : null;
    $inputNames = $tool ? $tool['inputs'] : null;
    $payType = array_key_exists('type', $pay) ? $pay['type'] : null;

    if ($dockFail !== null) {
        if (!empty($conf['message_duijie']) && class_exists('lib\\MessageSend', false)) {
            \lib\MessageSend::orderbuy_fail(
                $toolName,
                $inputName,
                $inputNames,
                $inputs,
                isset($pay['money']) ? $pay['money'] : 0,
                $num,
                $payType,
                0,
                $dockParam,
                $dockFail
            );
        }
    } elseif (!empty($conf['message_buy']) && class_exists('lib\\MessageSend', false)) {
        \lib\MessageSend::orderbuy(
            $toolName,
            $inputName,
            $inputNames,
            $inputs,
            isset($pay['money']) ? $pay['money'] : 0,
            $num,
            $payType,
            $notifyStatus
        );
    }

    return $orderId;
}

function do_order_faka($orderId, $tid, $num, $tool, array $inputs)
{
    global $DB, $conf;

    $DB->beginTransaction();
    $result = $DB->query(
        "SELECT * FROM pre_faka WHERE tid='{$tid}' AND orderid=0 ORDER BY kid ASC LIMIT {$num} FOR UPDATE"
    );

    $cards = [];
    while ($result && ($row = $result->fetch())) {
        $cards[] = $row;
    }

    if (!$cards) {
        $DB->commit();
        $DB->exec("UPDATE `pre_orders` SET `status`='0',`djzt`='4' WHERE `id`='{$orderId}'");
        return;
    }

    $kmdata = '';
    foreach ($cards as $card) {
        $DB->exec("UPDATE `pre_faka` SET `orderid`='{$orderId}',`usetime`=NOW() WHERE `kid`='{$card['kid']}'");
        if (isset($card['pw']) && $card['pw'] !== '' && $card['pw'] !== null) {
            $kmdata .= '卡号：' . $card['km'] . ' 密码：' . $card['pw'] . '<br/>';
        } else {
            $kmdata .= $card['km'] . '<br/>';
        }
    }

    $DB->commit();
    $DB->exec("UPDATE `pre_orders` SET `status`='1',`djzt`='3' WHERE `id`='{$orderId}'");

    $left = $DB->getColumn("SELECT count(*) FROM pre_faka WHERE tid='{$tid}' AND orderid=0");
    if (!empty($conf['message_fakastock']) && class_exists('lib\\MessageSend', false)) {
        \lib\MessageSend::faka_stock($tool ? $tool['name'] : '', $left);
    }

    $email = isset($inputs[0]) ? $inputs[0] : '';
    if ($email && function_exists('checkEmail') && checkEmail($email)) {
        $now = date('Y-m-d H:i:s');
        $template = isset($conf['faka_mail']) ? $conf['faka_mail'] : '';
        // Protected doOrder path substitutes [alert] from tools.desc (not alert).
        $body = str_replace(
            ['[kmdata]', '[alert]', '[name]', '[date]', '[email]', '[domain]', '[sitename]'],
            [
                $kmdata,
                ($tool && isset($tool['desc'])) ? $tool['desc'] : '',
                ($tool && isset($tool['name'])) ? $tool['name'] : '',
                $now,
                $email,
                isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
                isset($conf['sitename']) ? $conf['sitename'] : '',
            ],
            $template
        );
        $subject = (isset($conf['sitename']) ? $conf['sitename'] : '') . ' 卡密购买提醒';
        send_mail($email, $subject, $body);
    }
}

function do_order_shequ($orderId, $tool, array $inputs, $num, array $pay, &$dockParam, &$dockFail)
{
    global $DB;

    $shequId = isset($tool['shequ']) ? (int) $tool['shequ'] : 0;
    $shequ = $DB->getRow("SELECT * FROM pre_shequ WHERE id='{$shequId}' LIMIT 1");
    if (!$shequ) {
        $dockFail = '未配置好网站对接信息';
        $dockParam = '';
        return false;
    }

    $result = third_call(
        $shequ['type'],
        $shequ,
        'do_goods',
        [
            isset($tool['goods_id']) ? $tool['goods_id'] : 0,
            isset($tool['goods_type']) ? $tool['goods_type'] : 0,
            isset($tool['goods_param']) ? $tool['goods_param'] : '',
            $num,
            $inputs,
            isset($pay['money']) ? $pay['money'] : 0,
            isset($pay['trade_no']) ? $pay['trade_no'] : '',
            isset($tool['inputs']) ? $tool['inputs'] : '',
        ]
    );

    $dockParam = $shequ['type'] . ':' . $shequ['id']
        . ' goods_id:' . (isset($tool['goods_id']) ? $tool['goods_id'] : '')
        . ' num:' . $num
        . ' data:' . http_build_query($inputs);

    if (is_array($result) && isset($result['code']) && (int) $result['code'] === 0) {
        log_result('社区对接', $dockParam, $result, 0);
        if (!empty($result['km']) || !empty($result['card'])) {
            $km = isset($result['km']) ? $result['km'] : $result['card'];
            $pw = isset($result['pass']) ? $result['pass'] : (isset($result['pw']) ? $result['pw'] : '');
            $DB->exec(
                'INSERT INTO `pre_faka` (`tid`,`km`,`pw`,`orderid`,`addtime`,`usetime`) VALUES (:tid, :km, :pw, :orderid, NOW(), NOW())',
                [
                    ':tid' => $tool['tid'],
                    ':km' => $km,
                    ':pw' => $pw,
                    ':orderid' => $orderId,
                ]
            );
            $DB->exec(
                "UPDATE `pre_orders` SET `status`='1',`djzt`='3',`djorder`=:djorder WHERE `id`=:orderid",
                [':djorder' => isset($result['id']) ? $result['id'] : null, ':orderid' => $orderId]
            );
        } else {
            $DB->exec(
                "UPDATE `pre_orders` SET `status`='1',`djzt`='1',`djorder`=:djorder WHERE `id`=:orderid",
                [':djorder' => isset($result['id']) ? $result['id'] : null, ':orderid' => $orderId]
            );
        }
        return true;
    }

    log_result('社区对接', $dockParam, $result, 0);
    if (is_array($result) && (isset($result['message']) || isset($result['msg']))) {
        $dockFail = isset($result['message']) ? $result['message'] : $result['msg'];
    } else {
        $dockFail = is_string($result) ? $result : '下单失败请查看日志';
    }
    return false;
}

function do_order_curl($orderId, $tool, array $inputs, $num, array $pay)
{
    global $DB;

    $curl = isset($tool['curl']) ? $tool['curl'] : '';
    $response = do_curl(
        $curl,
        $inputs,
        $num,
        isset($tool['name']) ? $tool['name'] : '',
        isset($pay['money']) ? $pay['money'] : 0,
        isset($tool['price']) ? $tool['price'] : 0,
        $orderId
    );

    $decoded = json_decode($response, true);
    if (is_array($decoded)) {
        $param = 'url:' . $curl . ' data:' . http_build_query($inputs);
        log_result('自动访问URL', $param, $decoded, 0);
    }

    $DB->exec(
        "UPDATE `pre_orders` SET `status`='1',`djzt`='1',`djorder`=:djorder WHERE `id`=:orderid",
        [':djorder' => is_array($decoded) && isset($decoded['id']) ? $decoded['id'] : null, ':orderid' => $orderId]
    );
}

/**
 * Recovered processOrder (mode 8): handle paid trades for recharge, sites, cart, and goods.
 *
 * @param array $pay pre_pay row
 * @param bool  $profit Forwarded to doOrder
 * @return bool|int true for recharge/site, otherwise last goods order id
 */
function processOrder($pay, $profit = true)
{
    global $DB, $conf;

    $tid = isset($pay['tid']) ? (int) $pay['tid'] : 0;

    if ($tid === -1) {
        $zid = isset($pay['input']) ? (int) $pay['input'] : 0;
        $money = isset($pay['money']) ? $pay['money'] : 0;
        changeUserMoney($zid, $money, true, '充值', '你在线充值了' . $money . '元余额');
        if (!empty($conf['fenzhan_gift'])) {
            foreach (explode('|', $conf['fenzhan_gift']) as $rule) {
                $parts = explode(':', $rule);
                if (count($parts) < 2) {
                    continue;
                }
                if ($money >= $parts[0]) {
                    $gift = round($money * $parts[1] / 100, 2);
                    if ($gift > 0) {
                        changeUserMoney($zid, $gift, true, '赠送', '你参加多充多返活动获赠' . $gift . '元余额');
                    }
                    break;
                }
            }
        }
        return true;
    }

    if ($tid === -2) {
        return process_order_fenzhan($pay);
    }

    if ($tid === -3) {
        $ids = str_replace('|', ',', isset($pay['input']) ? $pay['input'] : '');
        $result = $DB->query("SELECT * FROM pre_cart WHERE id IN ({$ids}) AND status=1 ORDER BY id ASC");
        $lastId = null;
        while ($result && ($cart = $result->fetch())) {
            $DB->exec("UPDATE `pre_cart` SET `status`=2,`endtime`=NOW() WHERE `id`='{$cart['id']}'");
            $row = [
                'tid' => $cart['tid'],
                'zid' => $cart['zid'],
                'input' => $cart['input'],
                'num' => $cart['num'],
                'money' => $cart['money'],
                'trade_no' => isset($pay['trade_no']) ? $pay['trade_no'] : '',
                'userid' => $cart['userid'],
                'blockdj' => isset($cart['blockdj']) ? $cart['blockdj'] : 0,
            ];
            $lastId = doOrder($row, $profit);
        }
        return $lastId;
    }

    $orderId = doOrder($pay, $profit);
    process_order_invite($pay, $profit);
    return $orderId;
}

function process_order_fenzhan(array $pay)
{
    global $DB, $conf;

    $parts = explode('|', isset($pay['input']) ? $pay['input'] : '');
    $action = isset($parts[0]) ? $parts[0] : '';
    $gift = isset($conf['fenzhan_free']) ? $conf['fenzhan_free'] : 0;

    if ($action === 'update') {
        $zid = isset($parts[1]) ? (int) $parts[1] : 0;
        $DB->exec(
            'UPDATE `pre_site` SET `power`=:power,`domain`=:domain,`sitename`=:sitename,`title`=:title,`keywords`=:keywords,`description`=:description,`kfqq`=`qq`,`endtime`=:endtime WHERE `zid`=:zid',
            [
                ':power' => isset($parts[2]) ? (int) $parts[2] : 0,
                ':domain' => isset($parts[3]) ? $parts[3] : '',
                ':sitename' => isset($parts[4]) ? $parts[4] : '',
                ':title' => isset($conf['title']) ? $conf['title'] : '',
                ':keywords' => isset($conf['keywords']) ? $conf['keywords'] : '',
                ':description' => isset($conf['description']) ? $conf['description'] : '',
                ':endtime' => isset($parts[5]) ? $parts[5] : '',
                ':zid' => $zid,
            ]
        );
        $userid = isset($pay['userid']) ? $pay['userid'] : $zid;
        $DB->exec("UPDATE `pre_orders` SET `zid`='{$zid}' WHERE `userid`='{$userid}'");
        if ($gift) {
            addPointRecord($zid, $gift, '赠送', '你首次开通分站获赠' . $gift . '元余额');
        }
    } else {
        $power = isset($parts[1]) ? (int) $parts[1] : 1;
        $domain = isset($parts[2]) ? $parts[2] : '';
        $user = isset($parts[3]) ? $parts[3] : '';
        $pwd = isset($parts[4]) ? $parts[4] : '';
        $sitename = isset($parts[5]) ? $parts[5] : '';
        $qq = isset($parts[6]) ? $parts[6] : '';
        $endtime = isset($parts[7]) ? $parts[7] : '';
        $DB->exec(
            'INSERT INTO `pre_site` (`upzid`,`power`,`domain`,`domain2`,`user`,`pwd`,`rmb`,`qq`,`sitename`,`title`,`keywords`,`description`,`kfqq`,`addtime`,`endtime`,`status`) VALUES (:upzid, :power, :domain, NULL, :user, :pwd, :rmb, :qq, :sitename, :title, :keywords, :description, :kfqq, NOW(), :endtime, 1)',
            [
                ':upzid' => isset($pay['zid']) ? (int) $pay['zid'] : 0,
                ':power' => $power,
                ':domain' => $domain,
                ':user' => $user,
                ':pwd' => $pwd,
                ':rmb' => $gift,
                ':qq' => $qq,
                ':sitename' => $sitename,
                ':title' => isset($conf['title']) ? $conf['title'] : '',
                ':keywords' => isset($conf['keywords']) ? $conf['keywords'] : '',
                ':description' => isset($conf['description']) ? $conf['description'] : '',
                ':kfqq' => $qq,
                ':endtime' => $endtime,
            ]
        );
        $zid = $DB->lastInsertId();
        $DB->exec("UPDATE `pre_orders` SET `zid`='{$zid}' WHERE `userid`='{$zid}'");
        if ($gift) {
            addPointRecord($zid, $gift, '赠送', '你首次开通分站获赠' . $gift . '元余额');
        }
    }

    $parent = isset($pay['zid']) ? (int) $pay['zid'] : 0;
    if ($parent > 1) {
        $power = $DB->getColumn("SELECT power FROM pre_site WHERE zid='{$parent}' LIMIT 1");
        $costKey = ((int) $power === 2) ? 'fenzhan_cost2' : 'fenzhan_cost';
        $cost = isset($conf[$costKey]) ? $conf[$costKey] : 0;
        $commission = $pay['money'] - $cost;
        if ($commission > 0) {
            changeUserMoney($parent, $commission, true, '提成', '你网站的用户开通分站获得' . $commission . '元提成');
        }
    }

    return true;
}

function process_order_invite(array $pay, $profit)
{
    global $DB, $conf;

    if (empty($pay['inviteid']) || empty($conf['invite_tid'])) {
        return;
    }

    $log = $DB->getRow("SELECT * FROM `pre_invitelog` WHERE `id`='{$pay['inviteid']}'");
    if (!$log || !empty($log['status'])) {
        return;
    }

    $invite = $DB->getRow("SELECT * FROM `pre_invite` WHERE `id` = '{$log['iid']}'");
    if (!$invite) {
        return;
    }

    $shop = $DB->getRow("SELECT `tid`,`value`,`times` FROM `pre_inviteshop` WHERE `id` = '{$invite['nid']}'");
    if (!$shop) {
        return;
    }

    $extra = '';
    if (isset($shop['times']) && ($invite['count'] + 1) >= $shop['times']) {
        $extra = ',`status`=1';
    }
    $DB->exec('UPDATE `pre_invite` SET `count`=`count`+1' . $extra . ' WHERE `id`=:id', [':id' => $invite['id']]);
    $DB->exec('UPDATE `pre_invitelog` SET `status`=1 WHERE `id`=:id', [':id' => $log['id']]);

    $rewardPay = [
        'tid' => $shop['tid'],
        'zid' => isset($pay['zid']) ? $pay['zid'] : 0,
        'input' => isset($pay['input']) ? $pay['input'] : '',
        'num' => isset($shop['value']) ? $shop['value'] : 1,
        'money' => 0,
        'trade_no' => date('YmdHis') . rand(100, 999),
        'userid' => isset($pay['userid']) ? $pay['userid'] : 0,
        'type' => 'invite',
        'blockdj' => 0,
    ];
    doOrder($rewardPay, $profit);
}
