<?php
/**
 * 彩虹易支付异步通知页面
 */

require_once("./inc.php");

$out_trade_no = isset($_GET['out_trade_no'])?daddslashes($_GET['out_trade_no']):exit('error');
$srow=$DB->getRow("SELECT * FROM pre_pay WHERE trade_no='{$out_trade_no}' LIMIT 1");
$pay_config = get_pay_api($srow['channel']);

require_once(SYSTEM_ROOT."epay/epay.config.php");
require_once(SYSTEM_ROOT."epay/epay_notify.class.php");

//计算得出通知验证结果
$alipayNotify = new AlipayNotify($alipay_config);
$verify_result = $alipayNotify->verifyNotify();

if($verify_result && ($conf['alipay_api']==2 || $conf['qqpay_api']==2 || $conf['wxpay_api']==2 || $conf['qqpay_api']==8 || $conf['wxpay_api']==8 || $conf['wxpay_api']==9) && !empty($pay_config['pid']) && !empty($pay_config['key'])) {//验证成功

	//支付宝交易号

	$trade_no = daddslashes($_GET['trade_no']);

	//交易状态
	$trade_status = $_GET['trade_status'];

	//金额
	$money = $_GET['money'];

    if ($_GET['trade_status'] == 'TRADE_SUCCESS' && $srow['status']==0 && round($srow['money'],2)==round($money,2)) {
		//付款完成后，支付宝系统发送该交易状态通知
		if($DB->exec("UPDATE `pre_pay` SET `status` ='1' WHERE `trade_no`='{$out_trade_no}'")){
			$DB->exec("UPDATE `pre_pay` SET `endtime` ='$date',`api_trade_no` ='$trade_no' WHERE `trade_no`='{$out_trade_no}'");
			processOrder($srow);
		}
    }

	echo "success";
}
else {
    //验证失败
    echo "fail";
}
?>