<?php
/**
 * APP在线生成
**/
include("../includes/common.php");
$title='APP在线生成';
include './head.php';
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<style>
#orderItem .orderTitle{word-break:keep-all;}
#orderItem .orderContent{word-break:break-all;}
.app-user-hero{border-radius:12px;overflow:hidden;margin-bottom:14px;background:linear-gradient(135deg,#0ea5e9,#1d4ed8);color:#fff}
.app-user-hero .panel-body{padding:18px 16px}
.app-user-hero h3{margin:0 0 6px;font-size:18px;font-weight:700}
.app-user-hero p{margin:0;opacity:.92;font-size:13px;line-height:1.6}
.app-user-card{border:1px solid #e8ecf1;border-radius:12px;overflow:hidden}
.app-user-card .nav-tabs{background:#f8fafc}
.app-user-card .nav-tabs>li>a{font-weight:600}
.app-user-tip{font-size:12px;color:#64748b;margin-top:8px;line-height:1.5}
</style>
  <div class="wrapper">
    <div class="col-sm-12 col-md-8 col-lg-6 center-block" style="float: none;">
<?php
if($userrow['power']==0){
	showmsg('你没有权限使用此功能！',3);
}
if(!$conf['appcreate_open'] || (\lib\AppFactory::isLocalMode($conf) ? false : !$conf['appcreate_key']))showmsg('未开启自助生成APP功能',3);

if($userrow['power']==2){
    $price = $conf['appcreate_price2']>0?$conf['appcreate_price2'].'元':'免费';
}else{
    $price = $conf['appcreate_price']>0?$conf['appcreate_price'].'元':'免费';
}

$url = 'http://'.$userrow['domain'];
$select='<option value="'.$url.'">'.$url.'</option>';
if(!empty($userrow['domain2'])){
    $url2 = 'http://'.$userrow['domain2'];
    $select.='<option value="'.$url2.'">'.$url2.'</option>';
}
?>
	<div class="panel app-user-hero">
        <div class="panel-body">
        <h3>把网站打包成专属 APP</h3>
        <p>提交后自动排队打包，完成后可在「我的生成」查看下载页。当前价格：<b><?php echo $price?></b></p>
        </div>
    </div>
    <div class="panel panel-default app-user-card">
        <div class="nav-tabs-alt">
            <ul class="nav nav-tabs nav-justified">
                <li class="active">
                    <a href="#create" data-toggle="tab">生成 APP</a>
                </li>
                <li>
                    <a href="#query" data-toggle="tab" id="tab-query" onclick="querytask()">我的生成</a>
                </li>
            </ul>
		    <div class="panel-body">
                <div id="myTabContent" class="tab-content">
					<div class="tab-pane fade in active" id="create">
			<form method="post" role="form">
			<div class="form-group">
				<div class="input-group">
					<div class="input-group-addon">应用名称</div>
					<input name="name" class="form-control" value="<?php echo $userrow['sitename']?>" maxlength="12"/>
				</div>
			</div>
            <div class="form-group">
				<div class="input-group">
					<div class="input-group-addon">应用网址</div>
                    <select name="url" class="form-control"><?php echo $select?></select>
				</div>
			</div>
            <?php if($conf['appcreate_diy']==1){?>
            <div class="form-group">
                <input type="file" id="file_icon" onchange="fileUpload(this, 'icon')" style="display:none;"/>
				<div class="input-group">
					<div class="input-group-addon">应用图标</div>
					<input name="icon" class="form-control" value="" placeholder="不上传则使用默认应用图标" data-fileid="" disabled/>
                    <div class="input-group-btn"><a href="javascript:fileSelect('file_icon')" class="btn btn-success" title="上传图片"><i class="glyphicon glyphicon-upload"></i></a></div>
				</div>
			</div>
            <div class="form-group">
                <input type="file" id="file_background" onchange="fileUpload(this, 'background')" style="display:none;"/>
				<div class="input-group">
					<div class="input-group-addon">应用启动图</div>
					<input name="background" class="form-control" value="" placeholder="不上传则使用默认应用启动图" data-fileid="" disabled/>
                    <div class="input-group-btn"><a href="javascript:fileSelect('file_background')" class="btn btn-success" title="上传图片"><i class="glyphicon glyphicon-upload"></i></a></div>
				</div>
			</div>
            <?php }?>
			<div class="form-group">
				<input type="button" id="submit" value="立即生成（<?php echo $price?>）" class="btn btn-primary form-control"/>
                <p class="app-user-tip">本地工厂模式下通常 1～3 分钟完成。生成成功后请到「我的生成」打开下载页；上架/下架由主站后台管理。</p>
			</div>
			</form>
            </div>
            <div class="tab-pane fade in" id="query">
                <div id="result"></div>
            </div>
            </div>
		</div>
	</div>
  </div>
</div>
<?php include './foot.php';?>
<script>
function fileSelect(obj){
	$("#"+obj).trigger("click");
}
function fileUpload(obj, des){
	var fileObj = $(obj)[0].files[0];
	if (typeof (fileObj) == "undefined" || fileObj.size <= 0) {
		return;
	}
	var formData = new FormData();
	formData.append("file",fileObj);
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		url: "ajax_user.php?act=app_upload",
		data: formData,
		type: "POST",
		dataType: "json",
		cache: false,
		processData: false,
		contentType: false,
		success: function (data) {
			layer.close(ii);
			if(data.code == 0){
				$("input[name='"+des+"']").val('图片上传成功('+data.fileid+')');
                $("input[name='"+des+"']").attr('data-fileid', data.fileid);
			}else{
				layer.alert(data.msg);
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	})
}
    function querytask(){
        var ii = layer.load(0);
        $("#result").hide();
        $.ajax({
            type : 'GET',
            url : 'ajax_user.php?act=app_query',
            dataType : 'json',
            success : function(data) {
                layer.close(ii);
                if(data.code == 0){
                    var item = '<table class="table table-condensed table-hover" id="orderItem">';
				    item += '<tr><td colspan="6" style="text-align:center" class="orderTitle"><b>APP生成任务结果<a href="javascript:querytask()" class="pull-right btn btn-xs btn-default"><i class="fa fa-refresh"></i>&nbsp;刷新</a></b></td></tr><tr><td class="info orderTitle">应用名称</td><td colspan="5" class="orderContent">'+data.data.name+'</td></tr><tr><td class="info orderTitle">应用网址</td><td colspan="5" class="orderContent">'+data.url+'</td></tr><tr><td class="info orderTitle">提交时间</td><td colspan="5" class="orderContent">'+(data.data.created_at||'')+'</td></tr>';
                    if(data.data.updated_at) item += '<tr><td class="info orderTitle">更新时间</td><td colspan="5" class="orderContent">'+data.data.updated_at+'</td></tr>';
                    if(data.data.finished_at) item += '<tr><td class="info orderTitle">完成时间</td><td colspan="5" class="orderContent">'+data.data.finished_at+'</td></tr>';
                    if(data.data.duration_sec) item += '<tr><td class="info orderTitle">生成耗时</td><td colspan="5" class="orderContent">'+data.data.duration_sec+' 秒</td></tr>';
                    if(typeof data.data.progress!=='undefined') item += '<tr><td class="info orderTitle">进度</td><td colspan="5" class="orderContent">'+data.data.progress+'%</td></tr>';
                    item += '<tr><td class="info orderTitle">任务状态</td><td colspan="5" class="orderContent">'+(data.data.status==1?'<span class="label label-success">成功</span>':data.data.status==-1?'<span class="label label-danger">打包失败</span>':'<span class="label label-warning">正在打包，请稍候点击刷新按钮查看</span>')+'</td></tr>';
                    if(data.data.error) item += '<tr><td class="info orderTitle">失败原因</td><td colspan="5" class="orderContent text-danger">'+data.data.error+'</td></tr>';
                    if(data.data.status==1){
                       item += '<tr><td class="info orderTitle">双端下载页面</td><td colspan="5" class="orderContent"><a href="'+data.download_url+'" target="_blank" style="color:blue">'+data.download_url_show+'</a><br/></td></tr>';
						item += '<tr><td class="info orderTitle">安卓APP下载</td><td colspan="5" class="orderContent"><a href="'+data.android_url+'" target="_blank" style="color:blue">'+data.android_url+'</a></tr>';
                        if(data.data.storage) item += '<tr><td class="info orderTitle">存储</td><td colspan="5" class="orderContent">'+data.data.storage+(data.data.file_size?(' / '+(data.data.file_size/1048576).toFixed(2)+' MB'):'')+'</td></tr>';
						item += '<tr><td class="info orderTitle">iOS APP下载</td><td colspan="5" class="orderContent"><a href="'+data.download_url+'" target="_blank" style="color:blue">'+data.download_url_show+'</a>（必须Safari访问）</tr>';
                        if(navigator.userAgent.indexOf('Windows')>-1){
                            item += '<tr><td class="info orderTitle">扫码下载</td><td colspan="5" class="orderContent"><img style="box-shadow: 3px 3px 16px #eee" src="//api.qrserver.com/v1/create-qr-code/?size=150x150&margin=10&data='+encodeURIComponent(data.download_url_show)+'"></td></tr>';
                        }
                    }
                    item += '</table>';
                    $("#result").html(item);
                    $("#result").show();
                }else{
                    item = '<div class="alert alert-danger"><i class="glyphicon glyphicon-info-sign"></i>&nbsp;'+data.msg+'</div>';
                    $("#result").html(item);
                    $("#result").show();
                }
            }
        });
    }
$(document).ready(function(){
    $("#submit").click(function(){
		var name = $("input[name='name']").val();
		var url = $("select[name='url']").val();
        var icon = $("input[name='icon']").attr('data-fileid');
        var background = $("input[name='background']").attr('data-fileid');
		if(name==''){layer.alert('应用名称不能为空！');return false;}
        var confirmobj = layer.confirm('请确认你的APP信息↓<br/>应用名称：<font color="blue">'+name+'</font><br/>应用网址：<font color="blue">'+url+'</font>', {
            btn: ['确定','取消']
        }, function(){
            var ii = layer.load(0);
            $.ajax({
                type : 'POST',
                url : 'ajax_user.php?act=app_submit',
                data : {name: name, url: url, icon: icon, background: background},
                dataType : 'json',
                success : function(data) {
                    layer.close(ii);
                    if(data.code == 0){
                        layer.alert(data.msg, {icon:1}, function(){
                            $('#tab-query').tab('show');
                            querytask();
                        });
                    }else{
                        layer.alert(data.msg, {icon:2});
                    }
                }
            });
        }, function(){
            layer.close(confirmobj);
        });
	});
})
</script>
</body>
</html>