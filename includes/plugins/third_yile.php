<?php
namespace plugins;

class third_yile{

	private $config = [];

	static public $info = [
		'name'        => 'third_yile',  //插件名称，必须和类名一致
		'type'        => 'third',  //插件类型，固定为third
		'title'       => '亿乐SUP',  //插件显示名称
		'author'      => '彩虹',
		'version'     => '1.0',
		'link'        => '',
		'sort'        => 11,  //在对接列表显示的排序号
		'showedit'    => false,  //是否在编辑商品页面插入html
		'showip'      => true,  //是否显示加ip白名单提示
		'pricejk'     => 1,  //价格监控模式，2为可以下单时检查，1为直接监控批量更新
		'input' => [  //配置对接站点的输入框名称
			'url' => '网站域名',
			'username' => 'AppID',
			'password' => '秘钥',
			'paypwd' => false,
			'paytype' => false,
		],
	];

	public function __construct($config)
	{
		$this->config = $config;
	}

	/**
     * 提交到对接网站
	 * @param int $goods_id 商品ID
	 * @param int $goods_type 类型ID
	 * @param string $goods_param 参数名
	 * @param int $num 下单数量（下单份数×默认数量信息）
	 * @param array $input 下单输入框内容
	 * @param string $money 订单金额
	 * @param string $tradeno 支付订单号
	 * @param string $inputsname 商品其他输入框标题
     * @return array 返回信息（code=0成功，-1失败，message是提示信息）
     */
	public function do_goods($goods_id, $goods_type, $goods_param, $num = 1, $input = array(), $money, $tradeno, $inputsname)
	{
		$inputname = explode('|',$goods_param);
		$result['code'] = -1;
		$url = '/openapi/customer/Goods/Buy';
		$buy_params = [];
		$i = 0;
		foreach($inputname as $key){
			if($key == 'need_num_0'){
				$buy_params[$key] = (string)$num;
				$num = 1;
			}else{
				$buy_params[$key] = $input[$i];
				$i++;
			}
		}
		$param = ['goods_sn'=>$goods_id, 'buy_number'=>$num, 'buy_params'=>$buy_params];
		$data = $this->get_curl($url,$param);
		$json = json_decode($data,true);
		if (isset($json['code']) && $json['code']==0) {
			$result = array(
				'code' => 0,
				'id' => $json['data']['order_sn']
			);
			if(isset($json['data']['buy_card_code_list']) && count($json['data']['buy_card_code_list']) > 0){
				$result['faka'] = true;
				$kmdata = [];
				foreach($json['data']['buy_card_code_list'] as $card){
					$kmdata[] = ['card' => $card];
				}
				$result['kmdata'] = $kmdata;
			}
		} elseif(isset($json['message'])){
			$result['message'] = $this->handle_message($json['message']);
		} else{
			$result['message'] = $data;
		}
		return $result;
	}

	/**
     * 商品列表
     * @return array
     */
	public function goods_list(){
		$url = '/openapi/customer/Goods/List';
		$ret = $this->get_curl($url);
		if (!$ret = json_decode($ret, true)) {
			return '打开对接网站失败';
		} elseif ($ret['code'] !== 0) {
			return $this->handle_message($ret['message']);
		} else {
			$list = array();
			foreach ($ret['data'] as $v) {
				$list[] = array(
					'id' => $v['serial_number'],
					'name' => $v['name'],
					'price' => $v['price'],
					'stock' => $v['stock'],
					'is_close' => $v['is_close']
				);
			}
			return $list;
		}
	}

	/**
     * 商品详情
	 * @param int $goods_id 商品ID
     * @return array
     */
	public function goods_info($goods_id){
		$url = '/openapi/customer/Goods/Show';
		$param = ['goods_sn'=>$goods_id];
		$ret = $this->get_curl($url,$param);
		if (!$ret = json_decode($ret, true)) {
			return '打开对接网站失败';
		} elseif ($ret['code'] !== 0) {
			return $this->handle_message($ret['message']);
		} else {
			$result = $ret['data'];
			$result['image'] = $result['image_urls'][0];
			if($result['image'] && substr($result['image'],0,1)=='/'){
				$result['image'] = ($this->config['protocol']==1?'https://':'http://') . $this->config['url'].$result['image'];
			}
			$result['input'] = $result['buy_params'][0]['name'];
			$inputs = '';
			$goodsparam = '';
			foreach($result['buy_params'] as $row){
				$goodsparam .= $row['key'].'|';
				if($row['key'] == 'need_num_0' && $row['type'] == 7){
					$result['buy_max_limit'] = $row['verify']['max'];
					$result['buy_min_limit'] = $row['verify']['min'];
					continue;
				}
				if($result['input'] == $row['name']) continue;
				if($row['type'] == 3 || $row['type'] == 5){
					$inputs .= $row['name'].'{'.$row['type_config'].'}|';
				}elseif($row['type'] == 7 || $row['type'] == 8){
					$inputs .= $row['name'].'[multi]|';
				}else{
					$inputs .= $row['name'].'|';
				}
			}
			$result['inputs'] = trim($inputs, '|');
			$result['goodsparam'] = trim($goodsparam, '|');
			return $result;
		}
	}
	
	/**
     * 订单查询
	 * @param int $orderid 订单ID
	 * @param int $goodsid 商品ID
	 * @param array $value 下单输入框内容
     * @return array
     */
	public function query_order($orderid, $goodsid, $value = []){
		$order_state = array(0=>'等待中',1=>'已付款',2=>'待处理',3=>'处理中',4=>'补单中',5=>'退单中',6=>'已完成',7=>'已退单',8=>'已退款',9=>'处理异常',10=>'下单失败');
		$url = '/openapi/customer/Order/Show';
		$param = ['order_sn'=>$orderid];
		$ret = $this->get_curl($url,$param);
		if (!$ret = json_decode($ret, true)) {
			return false;
		} elseif ($ret['code'] !== 0) {
			return $this->handle_message($ret['message']);
		} else {
			$v = $ret['data'];
			return array('num'=>$v['buy_number'],'start_num'=>$v['callback_start_num'],'now_num'=>$v['callback_current_num'],'order_state'=>$order_state[$v['status']], 'add_time'=>'');
		}
	}

	public function pricejk($shequid,&$success){
		global $DB,$conf;
		$list = $this->goods_list();
		if(is_array($list)){
			$price_arr = array();
			$goods_status_arr = array();
			$stock_arr = array();
			foreach($list as $row){
				$price_arr[$row['id']] = $row['price'];
				$goods_status_arr[$row['id']] = $row['is_close']; //1 关闭 0 正常
				$stock_arr[$row['id']] = $row['stock'];
			}
			$rs2=$DB->query("SELECT * FROM pre_tools WHERE is_curl=2 AND shequ='{$shequid}' AND active=1 AND cid IN ({$conf['pricejk_cid']})");
			while($res2 = $rs2->fetch())
			{
				if($res2['price']==='0.00')continue;
				if(isset($price_arr[$res2['goods_id']]) && $price_arr[$res2['goods_id']]>0 && $res2['prid']>0){
					$price = ceil($price_arr[$res2['goods_id']] * 100)/100;
					if($conf['pricejk_edit']==1 && $price>$res2['price']){
						$DB->exec("update `pre_tools` set `price` ='{$price}' where `tid`='{$res2['tid']}'");
						$success++;
					}elseif($conf['pricejk_edit']==0 && $price!=$res2['price']){
						$DB->exec("update `pre_tools` set `price` ='{$price}' where `tid`='{$res2['tid']}'");
						$success++;
					}
				}
				if(isset($goods_status_arr[$res2['goods_id']])){
					if($goods_status_arr[$res2['goods_id']]==1 && $res2['close']==0){
						$DB->exec("update `pre_tools` set `close`=1 where `tid`='{$res2['tid']}'");
					}elseif($goods_status_arr[$res2['goods_id']]==0 && $res2['close']==1){
						$DB->exec("update `pre_tools` set `close`=0 where `tid`='{$res2['tid']}'");
					}
				}
				if(isset($stock_arr[$res2['goods_id']]) && $stock_arr[$res2['goods_id']]!==-1 && $res2['stock']!=$stock_arr[$res2['goods_id']]){
					$DB->exec("update `pre_tools` set `stock`=:stock where `tid`='{$res2['tid']}'", [':stock'=>$stock_arr[$res2['goods_id']]]);
				}
			}
			return true;
		}else{
			return $list;
		}
	}

	private function get_curl($path,$post=0){
		$url = ($this->config['protocol']==1?'https://':'http://') . $this->config['url'] . $path;
		$AppId = $this->config['username'];
		$AppSecret = $this->config['password'];
		$AppTimestamp = time();
		$AppToken = sha1($AppId . $AppSecret . $path . $AppTimestamp);
		$header[] = "AppId: ".$AppId;
		$header[] = "AppToken: ".$AppToken;
		$header[] = "AppTimestamp: ".$AppTimestamp;
		$header[] = 'Content-Type: application/json; charset=UTF-8';
		if($post){
			$post = json_encode($post);
		}
		return shequ_get_curl($url,$post,0,0,0,$header);
	}

	private function handle_message($message){
		if(is_array($message)){
			return $message['field'].$message['message'];
		}else{
			return $message;
		}
	}
}