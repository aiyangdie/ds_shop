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
