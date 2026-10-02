<?php
include("../includes/common.php");
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

@header('Content-Type: application/json; charset=UTF-8');

if(!checkRefererHost())exit('{"code":403}');

$localMode = \lib\AppFactory::isLocalMode($conf);

switch($act){
case 'app_upload':
	if(!$localMode && !$conf['appcreate_key'])exit('{"code":-1,"msg":"未配置APP生成平台密钥"}');
	$file = $_FILES['file'];
	if(!$file)exit(json_encode(['code' => -1, 'msg' => '上传失败']));
	$type = strtolower(substr($file['name'], strrpos($file['name'], '.') + 1));
	if (!in_array($type, ['jpg', 'jpeg', 'png'])) {
		exit(json_encode(['code' => -1, 'msg' => '上传图片格式错误']));
	}
	if ($localMode) {
		$factory = new \lib\AppFactory($DB, $conf);
		$path = $factory->saveUpload($file['tmp_name'], $file['name'], isset($_POST['kind']) ? $_POST['kind'] : 'icon');
		if ($path) {
			exit(json_encode(['code' => 0, 'msg' => '图片上传成功', 'fileid' => $path, 'url' => '/' . ltrim($path, '/')]));
		}
		exit(json_encode(['code' => -1, 'msg' => $factory->msg ?: '上传失败']));
	}
	$app = new \lib\AppCreate($conf['appcreate_key']);
	if($app->uploadimg($file['tmp_name'])){
		exit(json_encode(['code' => 0, 'msg' => '图片上传成功', 'fileid' => $app->fileid]));
	}else{
		exit(json_encode(['code' => -1, 'msg' => $app->msg]));
	}
break;
case 'app_submit':
	if(!$localMode && !$conf['appcreate_key'])exit('{"code":-1,"msg":"未配置APP生成平台密钥"}');
	$name=trim(daddslashes($_POST['name']));
	$url=trim(daddslashes($_POST['url']));
	if(empty($name))exit('{"code":-1,"msg":"应用名称不能为空"}');
	if(!preg_match('/^[a-zA-Z0-9\x7f-\xff\.\-\! ]+$/',$name) || strlen($name)<3){
		exit('{"code":-1,"msg":"应用名称不合法"}');
	}
	if(mb_strlen($name, "UTF-8")>12)exit('{"code":-1,"msg":"应用名称长度不能超过12个字"}');
	if(empty($url))exit('{"code":-1,"msg":"应用网址不能为空"}');
	if(!strpos($url,'.'))exit('{"code":-1,"msg":"应用网址不正确"}');
	$icon = '1';
	$background = '2';
	$iconPath = '';
	$splashPath = '';
	if($conf['appcreate_diy']==1){
		$icon = !empty($_POST['icon'])?trim($_POST['icon']):'1';
		$background = !empty($_POST['background'])?trim($_POST['background']):'2';
		if ($localMode) {
			if ($icon !== '1' && strpos($icon, 'assets/uploads/apps/') === 0) $iconPath = $icon;
			if ($background !== '2' && strpos($background, 'assets/uploads/apps/') === 0) $splashPath = $background;
		}
	}
	$theme = $conf['appcreate_theme'];
	if ($localMode) {
		$factory = new \lib\AppFactory($DB, $conf);
		$id = $factory->submit(array(
			'name' => $name,
			'url' => $url,
			'zid' => 1,
			'theme' => $theme,
			'icon_path' => $iconPath,
			'splash_path' => $splashPath,
			'nonav' => !empty($conf['appcreate_nonav']),
		));
		if ($id) {
			$_SESSION['appurl2'] = $url;
			exit(json_encode(['code' => 0, 'msg' => '已加入本地打包队列，请运行 php tools/appbuild/worker.php', 'taskid' => $id]));
		}
		exit(json_encode(['code' => -1, 'msg' => $factory->msg ?: '提交失败']));
	}
	$app = new \lib\AppCreate($conf['appcreate_key']);
	if($app->submittask($name, $url, $icon, $background, $theme, $conf['appcreate_nonav'])){
		$_SESSION['appurl2'] = $url;
		exit(json_encode(['code' => 0, 'msg' => '成功提交生成任务，生成大约需要半分钟，生成成功后请在"我的生成"中查看。', 'taskid' => $app->taskid]));
	}else{
		exit(json_encode(['code' => -1, 'msg' => $app->msg]));
	}
break;
case 'app_query':
	if(!$localMode && !$conf['appcreate_key'])exit('{"code":-1,"msg":"未配置APP生成平台密钥"}');
	$scriptpath=str_replace('\\','/',$_SERVER['SCRIPT_NAME']);
	$scriptpath = substr($scriptpath, 0, strrpos($scriptpath, '/'));
	$scriptpath = substr($scriptpath, 0, strrpos($scriptpath, '/'));
	$siteurl = (is_https() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$scriptpath.'/';
	$url=isset($_SESSION['appurl2'])?$_SESSION['appurl2']:$siteurl;
	$url = !empty($_POST['url'])?trim($_POST['url']):$url;
	$domain = parse_url($url)['host'];
	if ($localMode) {
		$factory = new \lib\AppFactory($DB, $conf);
		$res = $factory->queryForApi($domain);
		if (!$res) exit(json_encode(['code' => -1, 'msg' => $factory->msg ?: '暂无任务']));
		$appurl = '';
		$android_url = isset($res['android_url']) ? $res['android_url'] : '';
		$ios_url = '';
		if (intval($res['status']) === 1) {
			$appurl = '/?mod=app&id=' . intval($res['id']);
		}
		$result=array("code"=>0,"msg"=>"succ","url"=>$url,"download_url"=>$appurl,"download_url_show"=>$siteurl.ltrim($appurl,'/'),"android_url"=>$android_url,"ios_url"=>$ios_url,"data"=>$res,"mode"=>"local","queue"=>$factory->countQueued());
		exit(json_encode($result));
	}
	$app = new \lib\AppCreate($conf['appcreate_key']);
	$res=$app->queryurl($url);
	if($res && is_array($res)){
		$appurl = "";
		if($res['status']==1){
			$android_url = $res['lanzou_url']?$res['lanzou_url']:$res['android_url'];
			$ios_url = $res['ios_url'];
			$approw = $DB->find('apps','*',['domain'=>$domain]);
			if($approw){
				$id = $approw['id'];
				$DB->update('apps',['taskid'=>$res['id'], 'domain'=>$domain, 'name'=>$res['name'], 'package'=>$res['package'], 'android_url'=>$android_url, 'ios_url'=>$ios_url, 'icon'=>$res['icon'], 'addtime'=>$res['created_at'], 'status'=>1], ['id'=>$id]);
			}else{
				$id = $DB->insert('apps',['taskid'=>$res['id'], 'domain'=>$domain, 'name'=>$res['name'], 'package'=>$res['package'], 'android_url'=>$android_url, 'ios_url'=>$ios_url, 'icon'=>$res['icon'], 'addtime'=>$res['created_at'], 'status'=>1]);
			}
			$appurl = '/?mod=app&id='.$id;
		}
		$result=array("code"=>0,"msg"=>"succ","url"=>$url,"download_url"=>$appurl,"download_url_show"=>$url.$appurl,"android_url"=>$android_url,"ios_url"=>$ios_url,"data"=>$res);
		exit(json_encode($result));
	}else{
		exit(json_encode(['code' => -1, 'msg' => $app->msg]));
	}
break;
case 'app_retry':
	$id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
	$factory = new \lib\AppFactory($DB, $conf);
	if ($factory->retry($id)) {
		exit(json_encode(['code' => 0, 'msg' => '已重新入队', 'taskid' => $id]));
	}
	exit(json_encode(['code' => -1, 'msg' => $factory->msg ?: '重试失败']));
break;
case 'app_cleanup':
	$factory = new \lib\AppFactory($DB, $conf);
	$n = $factory->cleanup(isset($_POST['days']) ? intval($_POST['days']) : 30);
	exit(json_encode(['code' => 0, 'msg' => '清理完成', 'count' => $n]));
break;
case 'app_queue':
	$factory = new \lib\AppFactory($DB, $conf);
	$list = $DB->getAll("SELECT id,zid,domain,name,package,status,build_status,progress,error,android_url,addtime,updatetime,file_size,storage_driver FROM pre_apps ORDER BY id DESC LIMIT 50");
	if (!$list) $list = array();
	$st = isset($conf['appcreate_storage']) ? $conf['appcreate_storage'] : 'auto';
	$hint = $st;
	if ($st === 'auto') {
		$hint = 'auto→' . (isset($conf['ai_storage_driver']) ? $conf['ai_storage_driver'] : 'local');
	}
	exit(json_encode(array('code' => 0, 'data' => $list, 'queued' => $factory->countQueued(), 'mode' => $localMode ? 'local' : 'remote', 'storage_hint' => $hint), JSON_UNESCAPED_UNICODE));
break;
case 'app_clean':
	$count = $DB->exec("UPDATE pre_apps SET status=2 WHERE taskid>0 AND status=1");
	if($count!==false){
		exit(json_encode(['code' => 0, 'msg' => '清空成功，影响'.$count.'条数据', 'count' => $count]));
	}else{
		exit(json_encode(['code' => -1, 'msg' => '清空失败'.$DB->error()]));
	}
break;
default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}
