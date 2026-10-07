<?php
function get_curl($url,$post=0,$referer=0,$cookie=0,$header=0,$ua=0,$nobaody=0,$addheader=0){
	global $conf;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	$httpheader[] = "Accept: */*";
	$httpheader[] = "Accept-Encoding: gzip,deflate,sdch";
	$httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
	$httpheader[] = "Connection: close";
	if($addheader){
		$httpheader = array_merge($httpheader, $addheader);
	}
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	if($post){
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	}
	curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
	if($header){
		curl_setopt($ch, CURLOPT_HEADER, TRUE);
	}
	if($cookie){
		curl_setopt($ch, CURLOPT_COOKIE, $cookie);
	}
	if($referer){
		if($referer==1){
			curl_setopt($ch, CURLOPT_REFERER, 'http://m.qzone.com/infocenter?g_f=');
		}else{
			curl_setopt($ch, CURLOPT_REFERER, $referer);
		}
	}
	if($ua){
		curl_setopt($ch, CURLOPT_USERAGENT,$ua);
	}else{
		curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36');
	}
	if($nobaody){
		curl_setopt($ch, CURLOPT_NOBODY,1);
	}
	// 应用后台代理服务器设置（修复原 proxy_n 保存失败后即使保存也无法生效的问题）
	if(!empty($conf['proxy']) && intval($conf['proxy'])==1 && !empty($conf['proxy_server'])){
		$proxy_port = !empty($conf['proxy_port']) ? $conf['proxy_port'] : '80';
		curl_setopt($ch, CURLOPT_PROXY, $conf['proxy_server'].':'.$proxy_port);
		$proxy_type = isset($conf['proxy_type']) ? strtolower($conf['proxy_type']) : 'http';
		if($proxy_type==='sock5' || $proxy_type==='socks5'){
			curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
		}elseif($proxy_type==='sock4' || $proxy_type==='socks4'){
			curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
		}else{
			curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
		}
		if(!empty($conf['proxy_user'])){
			$proxy_auth = $conf['proxy_user'];
			if(isset($conf['proxy_pwd']) && $conf['proxy_pwd']!==''){
				$proxy_auth .= ':'.$conf['proxy_pwd'];
			}
			curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_auth);
		}
	}
	curl_setopt($ch, CURLOPT_ENCODING, "gzip");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	$ret = curl_exec($ch);
	curl_close($ch);
	return $ret;
}
function real_ip($type=0){
$ip = $_SERVER['REMOTE_ADDR'];
if($type<=0 && isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
	foreach ($matches[0] AS $xip) {
		if (filter_var($xip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			$ip = $xip;
			break;
		}
	}
} elseif ($type<=0 && isset($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
	$ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif ($type<=1 && isset($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
	$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
} elseif ($type<=1 && isset($_SERVER['HTTP_X_REAL_IP']) && filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
	$ip = $_SERVER['HTTP_X_REAL_IP'];
}
return $ip;
}
function get_ip_city($ip)
{
    $url = 'http://whois.pconline.com.cn/ipJson.jsp?json=true&ip=';
    $city = get_curl($url . $ip);
	$city = mb_convert_encoding($city, "UTF-8", "GB2312");
    $city = json_decode($city, true);
    if ($city['city']) {
        $location = $city['pro'].$city['city'];
    } else {
        $location = $city['pro'];
    }
	if($location){
		return $location;
	}else{
		return false;
	}
}
function daddslashes($string) {
	if(is_array($string)) {
		foreach($string as $key => $val) {
			$string[$key] = daddslashes($val);
		}
	} else {
		$string = addslashes($string);
	}
	return $string;
}

function strexists($string, $find) {
	return !(strpos($string, $find) === FALSE);
}

function dstrpos($string, $arr) {
	if(empty($string)) return false;
	foreach((array)$arr as $v) {
		if(strpos($string, $v) !== false) {
			return true;
		}
	}
	return false;
}

function checkmobile() {
	$useragent = strtolower($_SERVER['HTTP_USER_AGENT']);
	$ualist = array('android', 'midp', 'nokia', 'mobile', 'iphone', 'ipod', 'blackberry', 'windows phone');
	if((dstrpos($useragent, $ualist) || strexists($_SERVER['HTTP_ACCEPT'], "VND.WAP") || strexists($_SERVER['HTTP_VIA'],"wap"))){
		return true;
	}else{
		return false;
	}
}
function checkEmail($value)
{
	if (preg_match("/^[\w\.\-]+@\w+([\.\-]\w+)*\.\w+$/", $value) && strlen($value) <= 60) {
		return true;
	} else {
		return false;
	}
}
/**
 * 取中间文本
 * @param string $str
 * @param string $leftStr
 * @param string $rightStr
 */
function getSubstr($str, $leftStr, $rightStr)
{
	$left = strpos($str, $leftStr);
	$start = $left+strlen($leftStr);
	$right = strpos($str, $rightStr, $start);
	if($left < 0) return '';
	if($right>0){
		return substr($str, $start, $right-$start);
	}else{
		return substr($str, $start);
	}
}

function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
	$ckey_length = 4;
	$key = md5($key);
	$keya = md5(substr($key, 0, 16));
	$keyb = md5(substr($key, 16, 16));
	$keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';
	$cryptkey = $keya.md5($keya.$keyc);
	$key_length = strlen($cryptkey);
	$string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
	$string_length = strlen($string);
	$result = '';
	$box = range(0, 255);
	$rndkey = array();
	for($i = 0; $i <= 255; $i++) {
		$rndkey[$i] = ord($cryptkey[$i % $key_length]);
	}
	for($j = $i = 0; $i < 256; $i++) {
		$j = ($j + $box[$i] + $rndkey[$i]) % 256;
		$tmp = $box[$i];
		$box[$i] = $box[$j];
		$box[$j] = $tmp;
	}
	for($a = $j = $i = 0; $i < $string_length; $i++) {
		$a = ($a + 1) % 256;
		$j = ($j + $box[$a]) % 256;
		$tmp = $box[$a];
		$box[$a] = $box[$j];
		$box[$j] = $tmp;
		$result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
	}
	if($operation == 'DECODE') {
		if(((int)substr($result, 0, 10) == 0 || (int)substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
			return substr($result, 26);
		} else {
			return '';
		}
	} else {
		return $keyc.str_replace('=', '', base64_encode($result));
	}
}

function random($length) {
	$seed = base_convert(md5(microtime().$_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
	$seed = $numeric ? (str_replace('0', '', $seed).'012340567890') : ($seed.'zZ'.strtoupper($seed));
	$hash = '';
	$max = strlen($seed) - 1;
	for($i = 0; $i < $length; $i++) {
		$hash .= $seed[mt_rand(0, $max)];
	}
	return $hash;
}
function get_rand($proArr)
{
	$result = "";
	$proSum = array_sum($proArr);
	foreach ($proArr as $key => $proCur) {
		$randNum = mt_rand(1, $proSum);
		if ($randNum <= $proCur) {
			$result = $key;
			break;
		}
		$proSum -= $proCur;
	}
	unset($proArr);
	return $result;
}
function showmsg($content = '未知的异常',$type = 4,$back = false)
{
switch($type)
{
case 1:
	$panel="success";
break;
case 2:
	$panel="info";
break;
case 3:
	$panel="warning";
break;
case 4:
	$panel="danger";
break;
}

echo '<div class="panel panel-'.$panel.'">
      <div class="panel-heading">
        <h3 class="panel-title">提示信息</h3>
        </div>
        <div class="panel-body">';
echo $content;

if ($back) {
	echo '<hr/><a href="'.$back.'"><< 返回上一页</a>';
}
else
    echo '<hr/><a href="javascript:history.back(-1)"><< 返回上一页</a>';

echo '</div>
    </div>';
exit;
}

if(!function_exists("is_https")){
	function is_https() {
		if(isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443){
			return true;
		}elseif(isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on' || $_SERVER['HTTPS'] == '1')){
			return true;
		}elseif(isset($_SERVER['HTTP_X_CLIENT_SCHEME']) && $_SERVER['HTTP_X_CLIENT_SCHEME'] == 'https'){
			return true;
		}elseif(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'){
			return true;
		}elseif(isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https'){
			return true;
		}elseif(isset($_SERVER['HTTP_EWS_CUSTOME_SCHEME']) && $_SERVER['HTTP_EWS_CUSTOME_SCHEME'] == 'https'){
			return true;
		}
		return false;
	}
}

function yile_getSign($param, $key)
{
    $signPars = "";
    ksort($param);
    foreach ($param as $k => $v) {
        if ("sign" != $k && "" != $v) {
            $signPars .= $k . "=" . $v . "&";
        }
    }
    $signPars = trim($signPars, '&');
    $signPars .= $key;
    $sign = md5($signPars);
    return $sign;
}
function getServerIp(){
	$url = 'http://members.3322.org/dyndns/getip';
	$url2 = 'https://www.bt.cn/Api/getIpAddress';
	if($data = get_curl($url2)){
		return $data;
	}else{
		$data = get_curl($url);
		return $data;
	}
}
function checkIfActive($string) {
	$array=explode(',',$string);
	$php_self=substr($_SERVER['REQUEST_URI'],strrpos($_SERVER['REQUEST_URI'],'/')+1,strrpos($_SERVER['REQUEST_URI'],'.')-strrpos($_SERVER['REQUEST_URI'],'/')-1);
	if (in_array($php_self,$array)){
		return 'active';
	}elseif (isset($_GET['mod']) && in_array(str_replace('_n','',$_GET['mod']),$array)){
		return 'active';
	}else
		return null;
}
/**
 * 后台/分站后台的目录绝对地址，避免 /admin、/user 无斜杠时相对链接跳到前台首页
 */
function site_section_base($section){
	$section = trim($section, '/');
	$path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
	if ($path === null || $path === '') $path = '/';
	if (preg_match('#^(.*/'.preg_quote($section, '#').')(/|$)#', $path, $m)) {
		$p = $m[1].'/';
	} else {
		$p = '/'.$section.'/';
	}
	$scheme = (function_exists('is_https') && is_https()) ? 'https' : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
	$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
	return $scheme.'://'.$host.$p;
}
function checkRefererHost(){
	if(!$_SERVER['HTTP_REFERER'])return false;
	$url_arr = parse_url($_SERVER['HTTP_REFERER']);
	$http_host = $_SERVER['HTTP_HOST'];
	if(strpos($http_host,':'))$http_host = substr($http_host, 0, strpos($http_host, ':'));
	return $url_arr['host'] === $http_host;
}
/**
 * 站点 Favicon 相对路径（相对网站根）
 */
function site_favicon_url(){
	global $conf;
	if(!empty($conf['default_ico_url'])){
		$u = $conf['default_ico_url'];
		if(strpos($u, 'http://')===0 || strpos($u, 'https://')===0 || strpos($u, '//')===0){
			return $u;
		}
		$local = ROOT . ltrim(str_replace('\\','/',$u), '/');
		if(is_file($local)) return $u;
	}
	if(!empty($conf['favicon'])){
		$u = $conf['favicon'];
		$local = ROOT . ltrim(str_replace('\\','/',$u), '/');
		if(is_file($local)) return $u;
	}
	if(is_file(ROOT.'assets/img/favicon.png')) return 'assets/img/favicon.png';
	if(is_file(ROOT.'favicon.ico')) return 'favicon.ico';
	if(is_file(ROOT.'assets/img/logo.png')) return 'assets/img/logo.png';
	return 'favicon.ico';
}

/**
 * 输出 &lt;link rel="icon"&gt;，可选缓存破除参数
 */
function echo_site_favicon_link($base=''){
	if(!defined('ROOT') || !function_exists('site_favicon_url')) return;
	$url = site_favicon_url();
	if($url==='' || $url===null) return;
	if(strpos($url, 'http://')!==0 && strpos($url, 'https://')!==0 && strpos($url, '//')!==0){
		$abs = ROOT . ltrim(str_replace('\\','/',$url), '/');
		$ver = is_file($abs) ? ('?v='.filemtime($abs)) : '';
		$url = ($base!=='' ? rtrim($base,'/').'/' : '') . ltrim($url,'/') . $ver;
	}
	$href = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
	echo '<link rel="shortcut icon" href="'.$href.'">'."\n";
	echo '<link rel="icon" href="'.$href.'">'."\n";
}

/**
 * 安全上传图片并统一保存为 PNG，返回提示文案
 */
function upload_site_image($file, $dest){
	if(empty($file) || !isset($file['error']) || $file['error']!==UPLOAD_ERR_OK){
		return '上传失败：未选择文件或上传出错';
	}
	if(!is_uploaded_file($file['tmp_name'])){
		return '上传失败：非法文件来源';
	}
	if($file['size']<=0 || $file['size']>5*1024*1024){
		return '上传失败：文件大小需在 5MB 以内';
	}
	$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
	$allow = array('png','jpg','jpeg','gif','webp','bmp');
	if(!in_array($ext, $allow, true)){
		return '上传失败：仅支持 png/jpg/jpeg/gif/webp/bmp';
	}
	$info = @getimagesize($file['tmp_name']);
	if($info===false || empty($info[2])){
		return '上传失败：文件不是有效图片';
	}
	$dir = dirname($dest);
	if(!is_dir($dir) && !@mkdir($dir, 0755, true)){
		return '上传失败：目录不可写';
	}
	if(!@move_uploaded_file($file['tmp_name'], $dest) && !@copy($file['tmp_name'], $dest)){
		return '上传失败：无法写入目标文件';
	}
	@chmod($dest, 0644);
	return '成功上传文件！（可能需要清空浏览器缓存才能看到效果，按 Ctrl+F5 刷新）';
}
?>