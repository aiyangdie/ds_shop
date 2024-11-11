<?php
/**
 * 彩虹易支付同步通知页面
 */

require_once("./inc.php");

$out_trade_no = isset($_GET['out_trade_no'])?daddslashes($_GET['out_trade_no']):exit('error');
$srow=$DB->getRow("SELECT * FROM pre_pay WHERE trade_no='{$out_trade_no}' LIMIT 1");
$pay_config = get_pay_api($srow['channel']);

require_once(SYSTEM_ROOT."epay/epay.config.php");
require_once(SYSTEM_ROOT."epay/epay_notify.class.php");

//计算得出通知验证结果
$alipayNotify = new AlipayNotify($alipay_config);
$verify_result = $alipayNotify->verifyReturn();
if($verify_result && ($conf['alipay_api']==2 || $conf['qqpay_api']==2 || $conf['wxpay_api']==2 || $conf['qqpay_api']==8 || $conf['wxpay_api']==8 || $conf['wxpay_api']==9) && !empty($pay_config['pid']) && !empty($pay_config['key'])) {

	//支付宝交易号

	$trade_no = daddslashes($_GET['trade_no']);

	//交易状态
	$trade_status = $_GET['trade_status'];

	//金额
	$money = $_GET['money'];

    if($_GET['trade_status'] == 'TRADE_FINISHED' || $_GET['trade_status'] == 'TRADE_SUCCESS') {
		if($srow['status']==0 && round($srow['money'],2)==round($money,2)){
			if($DB->exec("UPDATE `pre_pay` SET `status` ='1' WHERE `trade_no`='{$out_trade_no}'")){
				$DB->exec("UPDATE `pre_pay` SET `endtime` ='$date',`api_trade_no` ='$trade_no' WHERE `trade_no`='{$out_trade_no}'");
				processOrder($srow);
			}
			showalert('您所购买的商品已付款成功，感谢购买！',1,$out_trade_no,$srow['tid']);
		}else{
			showalert('您所购买的商品已付款成功，感谢购买！',1,$out_trade_no,$srow['tid']);
		}
    }
    else {
      echo "trade_status=".$_GET['trade_status'];
    }
}
else {
    //验证失败
	showalert('验证失败！',4,'shop');
}

?>