<?php
/**
 * 代理服务器设置（明文重建，绕过原 set.php?mod=proxy_n 无法保存的问题）
**/
include("../includes/common.php");
$title='代理服务器设置';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
adminpermission('set', 1);
?>
<div class="col-xs-12 col-sm-10 col-lg-8 center-block" style="float: none;">
<div class="block">
<div class="block-title"><h3 class="panel-title">代理服务器设置</h3></div>
<form onsubmit="return saveSetting(this)" method="post" class="form-horizontal" role="form">
	<div class="form-group">
	  <label class="col-sm-3 control-label">代理服务器开关</label>
	  <div class="col-sm-9"><select class="form-control" name="proxy" default="<?php echo htmlspecialchars(isset($conf['proxy'])?$conf['proxy']:'0'); ?>">
		<option value="0">关闭</option>
		<option value="1">开启</option>
	  </select></div>
	</div>
	<div class="form-group">
	  <label class="col-sm-3 control-label">代理IP</label>
	  <div class="col-sm-9"><input type="text" name="proxy_server" value="<?php echo htmlspecialchars(isset($conf['proxy_server'])?$conf['proxy_server']:''); ?>" class="form-control" placeholder="例如 127.0.0.1"/></div>
	</div>
	<div class="form-group">
	  <label class="col-sm-3 control-label">代理端口</label>
	  <div class="col-sm-9"><input type="text" name="proxy_port" value="<?php echo htmlspecialchars(isset($conf['proxy_port'])?$conf['proxy_port']:''); ?>" class="form-control" placeholder="例如 8080"/></div>
	</div>
	<div class="form-group">
	  <label class="col-sm-3 control-label">代理账号</label>
	  <div class="col-sm-9"><input type="text" name="proxy_user" value="<?php echo htmlspecialchars(isset($conf['proxy_user'])?$conf['proxy_user']:''); ?>" class="form-control" placeholder="无账号可留空"/></div>
	</div>
	<div class="form-group">
	  <label class="col-sm-3 control-label">代理密码</label>
	  <div class="col-sm-9"><input type="text" name="proxy_pwd" value="<?php echo htmlspecialchars(isset($conf['proxy_pwd'])?$conf['proxy_pwd']:''); ?>" class="form-control" placeholder="无密码可留空"/></div>
	</div>
	<div class="form-group">
	  <label class="col-sm-3 control-label">代理协议</label>
	  <div class="col-sm-9"><select class="form-control" name="proxy_type" default="<?php echo htmlspecialchars(isset($conf['proxy_type'])?$conf['proxy_type']:'http'); ?>">
		<option value="http">HTTP</option>
		<option value="https">HTTPS</option>
		<option value="sock4">SOCK4</option>
		<option value="sock5">SOCK5</option>
	  </select></div>
	</div>
	<div class="form-group">
	  <div class="col-sm-offset-3 col-sm-9">
		<input type="submit" name="submit" value="保存设置" class="btn btn-primary form-control"/>
	  </div>
	</div>
</form>
<div class="alert alert-info">
<span class="glyphicon glyphicon-info-sign"></span>
本功能适用于国外服务器对接一些屏蔽国外访问的网站，开启后使用国内代理服务器进行对接。<br/>
自定义代理可以使用 Windows 服务器 + CCProxy 软件搭建。<br/>
<b>注意：如果网站更换主机之后需要重新修改当前配置。</b><br/>
<small>说明：本页通过 ajax.php?act=set 明文保存，修复原「代理服务器设置开启无法保存数据」问题。</small>
</div>
</div>
</div>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script>
var items = $("select[default]");
for (var i = 0; i < items.length; i++) {
	$(items[i]).val($(items[i]).attr("default") || 0);
}
function saveSetting(obj){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax.php?act=set',
		data : $(obj).serialize(),
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert('设置保存成功！', {
					icon: 1,
					closeBtn: false
				}, function(){ window.location.reload(); });
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		},
		error:function(){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
	return false;
}
</script>
</body>
</html>
