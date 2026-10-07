<?php
/**
 * 自助下单系统
**/
include("../includes/common.php");
$title='自助下单系统管理中心';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

$mysqlversion=$DB->getColumn("select VERSION()");
$sec_msg = sec_check();
$checkupdate = '';
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
?>
<style>
@media (max-width:767px){
.showcountl{padding-right: 5px;}
.showcountr{padding-left: 5px;}
}
a.widget{display:block;color:inherit;}
a.widget:hover,a.widget:focus{text-decoration:none;color:inherit;}
a.widget:hover .widget-content{opacity:.92;}
</style>
<div class="col-xs-6 col-lg-4 showcountl">
	<a href="./list.php" class="widget" title="查看全部订单">
	<div class="widget-content widget-content-mini text-right clearfix">
		<div class="widget-icon pull-left themed-background">
			<i class="fa fa-list-ol text-light-op"></i>
		</div>
		<h2 class="widget-heading h3">
		<strong><span id="count1">-</span></strong>
		</h2>
		<span class="text-muted">订单总数</span>
	</div>
	</a>
</div>
<div class="col-xs-6 col-lg-4 showcountr">
	<a href="./list.php?type=0" class="widget" title="去处理待处理订单">
	<div class="widget-content widget-content-mini text-right clearfix">
		<div class="widget-icon pull-left themed-background-success">
			<i class="fa fa-first-order text-light-op"></i>
		</div>
		<h2 class="widget-heading h3 text-success">
		<strong><span id="count3">-</span></strong>
		</h2>
		<span class="text-muted">待处理订单</span>
	</div>
	</a>
</div>
<div class="col-xs-6 col-lg-4 showcountl">
	<a href="./list.php?type=2" class="widget" title="查看正在处理的订单">
	<div class="widget-content widget-content-mini text-right clearfix">
		<div class="widget-icon pull-left themed-background-info">
			<i class="fa fa-spinner text-light-op"></i>
		</div>
		<h2 class="widget-heading h3 text-info">
		<strong><span id="count19">-</span></strong>
		</h2>
		<span class="text-muted">处理中订单</span>
	</div>
	</a>
</div>
<div class="col-xs-6 col-lg-4 showcountr">
	<a href="./list.php?type=3" class="widget" title="去处理异常订单">
	<div class="widget-content widget-content-mini text-right clearfix">
		<div class="widget-icon pull-left themed-background-danger">
			<i class="fa fa-exclamation-triangle text-light-op"></i>
		</div>
		<h2 class="widget-heading h3 text-danger">
		<strong><span id="count18">-</span></strong>
		</h2>
		<span class="text-muted">异常订单</span>
	</div>
	</a>
</div>
<div class="col-xs-6 col-lg-4 showcountl">
	<a href="./list.php?starttime=<?php echo $today?>&endtime=<?php echo $today?>" class="widget" title="查看今日订单">
	<div class="widget-content widget-content-mini text-right clearfix">
		<div class="widget-icon pull-left themed-background-warning">
			<i class="fa fa-briefcase text-light-op"></i>
		</div>
		<h2 class="widget-heading h3 text-warning">
		<strong>+ <span id="count4">-</span></strong>
		</h2>
		<span class="text-muted">今日订单数</span>
	</div>
	</a>
</div>
<div class="col-xs-6 col-lg-4 showcountr">
	<a href="./payorder.php" class="widget" title="查看支付记录（今日交易额）">
	<div class="widget-content widget-content-mini text-right clearfix">
		<div class="widget-icon pull-left themed-background-danger">
			<i class="fa fa-rmb text-light-op"></i>
		</div>
		<h2 class="widget-heading h3 text-danger">
		<strong>$ <span id="count5">-</span></strong>
		</h2>
		<span class="text-muted">今日交易额</span>
	</div>
	</a>
</div>
<div class="col-xs-6 col-lg-4 showcountl">
	<a href="./list.php?starttime=<?php echo $today?>&endtime=<?php echo $today?>" class="widget" title="只统计已完成和正在处理的订单">
	<div class="widget-content widget-content-mini text-right clearfix">
		<div class="widget-icon pull-left themed-background-warning" style="background-color:#e9d706 !important;">
			<i class="fa fa-briefcase text-light-op"></i>
		</div>
		<h2 class="widget-heading h3 text-warning" style="color:#e9d706">
		<strong>$ <span id="count15">-</span></strong>
		</h2>
		<span class="text-muted">今日收益</span>
	</div>
	</a>
</div>
<div class="col-xs-6 col-lg-4 showcountr">
	<a href="./list.php?starttime=<?php echo $yesterday?>&endtime=<?php echo $yesterday?>" class="widget" title="只统计已完成和正在处理的订单">
	<div class="widget-content widget-content-mini text-right clearfix">
		<div class="widget-icon pull-left themed-background-danger" style="background-color:#f59797 !important;">
			<i class="fa fa-rmb text-light-op"></i>
		</div>
		<h2 class="widget-heading h3 text-danger" style="color:#f59797">
		<strong>$ <span id="count16">-</span></strong>
		</h2>
		<span class="text-muted">昨日收益</span>
	</div>
	</a>
</div>
<div class="col-xs-6 col-lg-4 showcountl">
	<a href="./list.php?type=1" class="widget" title="查看已完成订单">
	<div class="widget-content widget-content-mini text-right clearfix">
		<div class="widget-icon pull-left themed-background-success">
			<i class="fa fa-check-circle text-light-op"></i>
		</div>
		<h2 class="widget-heading h3 text-success">
		<strong><span id="count2">-</span></strong>
		</h2>
		<span class="text-muted">已完成订单</span>
	</div>
	</a>
</div>
<div class="col-xs-6 col-lg-4 showcountr">
	<a href="./workorder.php?status=0" class="widget" title="去处理待办工单">
	<div class="widget-content widget-content-mini text-right clearfix">
		<div class="widget-icon pull-left themed-background-warning">
			<i class="fa fa-comment text-light-op"></i>
		</div>
		<h2 class="widget-heading h3 text-warning">
		<strong><span id="count17">-</span></strong>
		</h2>
		<span class="text-muted">待处理工单</span>
	</div>
	</a>
</div>
<div class="col-xs-6 col-lg-4 showcountl">
	<a href="./tixian.php" class="widget" title="去审核提现申请">
	<div class="widget-content widget-content-mini text-right clearfix">
		<div class="widget-icon pull-left themed-background-danger">
			<i class="fa fa-money text-light-op"></i>
		</div>
		<h2 class="widget-heading h3 text-danger">
		<strong><span id="count26">-</span></strong>
		</h2>
		<span class="text-muted">待审提现笔数</span>
	</div>
	</a>
</div>
<div class="col-xs-6 col-lg-4 showcountr">
	<a href="./shoplist.php" class="widget" title="管理商品">
	<div class="widget-content widget-content-mini text-right clearfix">
		<div class="widget-icon pull-left themed-background">
			<i class="fa fa-shopping-cart text-light-op"></i>
		</div>
		<h2 class="widget-heading h3">
		<strong><span id="count21">-</span></strong>
		</h2>
		<span class="text-muted">商品总数</span>
	</div>
	</a>
</div>
</div>
<div class="row">
<div class="col-sm-6 col-lg-8">
	<div class="widget">
		<div class="widget-content border-bottom">
一周交易与订单统计
		</div>
		<div class="widget-content border-bottom themed-background-muted">
			<div id="chart-classic-dash" style="height: 393px;">
			</div>
		</div>
		<div class="widget-content widget-content-full">
			<div class="row text-center">
				<div class="col-xs-4 push-inner-top-bottom border-right">
					<a href="./payorder.php" style="color:inherit;text-decoration:none;" title="查看支付记录">
					<h4 class="widget-heading"><i class="fa fa-qq text-dark push-bit"></i>&nbsp;QQ钱包交易额<br>
					<center><span id="count12">-</span>元</center></h4>
					</a>
				</div>
				<div class="col-xs-4 push-inner-top-bottom">
					<a href="./payorder.php" style="color:inherit;text-decoration:none;" title="查看支付记录">
					<h4 class="widget-heading"><i class="fa fa-wechat text-dark push-bit"></i>&nbsp;微信交易额<br>
					<center><span id="count13">-</span>元</center></h4>
					</a>
				</div>
				<div class="col-xs-4 push-inner-top-bottom border-left">
					<a href="./payorder.php" style="color:inherit;text-decoration:none;" title="查看支付记录">
					<h4 class="widget-heading"><i class="fa fa-credit-card text-dark push-bit"></i>&nbsp;支付宝交易额<br>
					<center><span id="count14">-</span>元</center></h4>
					</a>
				</div>
			</div>
		</div>
	</div>
<div class="widget">
<div class="widget-content border-bottom">
<span class="pull-right text-muted"><i class="fa fa-tasks"></i></span>
待办事项
</div>
	<ul class="list-group" id="todo-list">
		<li class="list-group-item"><span class="text-muted">正在加载待办数据...</span></li>
	</ul>
</div>
<div class="widget">
<div class="widget-content border-bottom">
<span class="pull-right text-muted"><i class="fa fa-shield"></i></span>
安全中心
</div>
	<ul class="list-group">
<?php
foreach($sec_msg as $row){
	echo $row;
}
if(count($sec_msg)==0)echo '<li class="list-group-item"><span class="btn-sm btn-success">正常</span>&nbsp;暂未发现网站安全问题</li>';
?>
	</ul>
</div>
</div>
<div class="col-sm-4">
	<div class="widget">
		<div class="widget-content border-bottom">
			<span class="pull-right text-muted"><i class="fa fa-circle"></i></span>
分站统计
		</div>
		<div class="widget-content widget-content-full-top-bottom border-bottom">
			<div class="row text-center">
				<div class="col-xs-6 push-inner-top-bottom border-right">
					<a href="./sitelist.php" style="color:inherit;text-decoration:none;" title="分站列表">
					<h4 class="widget-heading"><i class="fa fa-sitemap text-dark push"></i>&nbsp;分站/用户总数<br>
					<center><span id="count6">-</span>个</center></h4>
					</a>
				</div>
				<div class="col-xs-6 push-inner-top-bottom">
					<a href="./sitelist.php" style="color:inherit;text-decoration:none;" title="分站列表">
					<h4 class="widget-heading"><i class="fa fa-cloud text-dark push"></i>&nbsp;今日新开分站<br>
					<center><span id="count7">-</span>个</center></h4>
					</a>
				</div>
			</div>
		</div>
		<div class="widget-content widget-content-full border-bottom">
			<div class="row text-center">
				<div class="col-xs-6 push-inner-top-bottom border-right">
					<a href="./record.php" style="color:inherit;text-decoration:none;" title="查看余额明细">
					<h4 class="widget-heading"><i class="fa fa-rmb text-dark push"></i>&nbsp;今日分站提成<br>
					<center><span id="count8">-</span>元</center></h4>
					</a>
				</div>
				<div class="col-xs-6 push-inner-top-bottom">
					<a href="./tixian.php" style="color:inherit;text-decoration:none;" title="去处理提现">
					<h4 class="widget-heading"><i class="fa fa-money text-dark push"></i>&nbsp;待处理提现<br>
					<center><span id="count11">-</span>元</center></h4>
					</a>
				</div>
			</div>
		</div>
		<div class="widget-content widget-content-full border-bottom">
			<div class="row text-center">
				<div class="col-xs-6 push-inner-top-bottom border-right">
					<a href="./workorder.php?status=0" style="color:inherit;text-decoration:none;" title="待处理工单">
					<h4 class="widget-heading"><i class="fa fa-comment text-dark push"></i>&nbsp;待处理工单<br>
					<center><span id="count24">-</span>个</center></h4>
					</a>
				</div>
				<div class="col-xs-6 push-inner-top-bottom">
					<a href="./workorder.php?status=1" style="color:inherit;text-decoration:none;" title="处理中工单">
					<h4 class="widget-heading"><i class="fa fa-comments text-dark push"></i>&nbsp;处理中工单<br>
					<center><span id="count25">-</span>个</center></h4>
					</a>
				</div>
			</div>
		</div>
		<div class="widget-content widget-content-full">
			<div class="row text-center">
				<div class="col-xs-6 push-inner-top-bottom border-right">
					<a href="./classlist.php" style="color:inherit;text-decoration:none;" title="分类管理">
					<h4 class="widget-heading"><i class="fa fa-th-large text-dark push"></i>&nbsp;商品分类<br>
					<center><span id="count23">-</span>个</center></h4>
					</a>
				</div>
				<div class="col-xs-6 push-inner-top-bottom">
					<a href="./shoplist.php" style="color:inherit;text-decoration:none;" title="下架商品">
					<h4 class="widget-heading"><i class="fa fa-ban text-dark push"></i>&nbsp;下架商品<br>
					<center><span id="count22">-</span>个</center></h4>
					</a>
				</div>
			</div>
		</div>
	</div>

<div class="widget">
<div class="widget-content border-bottom">
<span class="pull-right text-muted"><i class="fa fa-info-circle"></i></span>环境信息
</div>
<ul class="nav nav-pills nav-stacked">
	<li>
		<a><b>PHP 版本：</b><?php echo phpversion() ?>&nbsp;&nbsp;&nbsp;<b>MySQL 版本：</b><?php echo $mysqlversion ?></a>
	</li>
	<li>
		<a><b>服务器软件：</b><?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'], ENT_QUOTES, 'UTF-8') ?></a>
	</li>
	<li>
		<a><b>服务器时间：</b><?php echo $date ?></a>
	</li>
	<li>
		<a href="./payorder.php"><b>今日支付笔数：</b><span id="count27">-</span> 笔</a>
	</li>
	<li>
		<a href="./list.php?type=4"><b>已退单：</b><span id="count20">-</span> 笔</a>
	</li>
</ul>
</div>


    </div>
  </div>
<script>
$(document).ready(function(){
	$('#title').html('正在加载数据中...');
	function n(v){ v = parseFloat(v); return isNaN(v) ? 0 : v; }
	function buildTodo(data){
		var items = [];
		if(n(data.count3) > 0){
			items.push('<li class="list-group-item"><span class="btn-sm btn-warning">待办</span>&nbsp;有 <b>'+data.count3+'</b> 笔待处理订单&nbsp;<a href="./list.php?type=0">立即处理</a></li>');
		}
		if(n(data.count18) > 0){
			items.push('<li class="list-group-item"><span class="btn-sm btn-danger">异常</span>&nbsp;有 <b>'+data.count18+'</b> 笔异常订单&nbsp;<a href="./list.php?type=3">去查看</a></li>');
		}
		if(n(data.count19) > 0){
			items.push('<li class="list-group-item"><span class="btn-sm btn-info">处理中</span>&nbsp;有 <b>'+data.count19+'</b> 笔处理中订单&nbsp;<a href="./list.php?type=2">去查看</a></li>');
		}
		if(n(data.count24) > 0){
			items.push('<li class="list-group-item"><span class="btn-sm btn-warning">工单</span>&nbsp;有 <b>'+data.count24+'</b> 条待处理工单&nbsp;<a href="./workorder.php?status=0">去回复</a></li>');
		}
		if(n(data.count25) > 0){
			items.push('<li class="list-group-item"><span class="btn-sm btn-info">工单</span>&nbsp;有 <b>'+data.count25+'</b> 条处理中工单&nbsp;<a href="./workorder.php?status=1">继续处理</a></li>');
		}
		if(n(data.count26) > 0){
			items.push('<li class="list-group-item"><span class="btn-sm btn-danger">提现</span>&nbsp;有 <b>'+data.count26+'</b> 笔待审提现（合计 '+data.count11+' 元）&nbsp;<a href="./tixian.php">去审核</a></li>');
		}
		if(n(data.count22) > 0){
			items.push('<li class="list-group-item"><span class="btn-sm btn-default">商品</span>&nbsp;有 <b>'+data.count22+'</b> 个下架商品&nbsp;<a href="./shoplist.php">商品管理</a></li>');
		}
		if(items.length === 0){
			items.push('<li class="list-group-item"><span class="btn-sm btn-success">正常</span>&nbsp;暂无需要立即处理的事项</li>');
		}
		$('#todo-list').html(items.join(''));
	}
	$.ajax({
		type : "GET",
		url : "ajax.php?act=getcount",
		dataType : 'json',
		async: true,
		success : function(data) {
			$('#title').html('后台管理首页');
			$('#yxts').html(data.yxts);
			var ids = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28];
			for(var i=0;i<ids.length;i++){
				var k = 'count'+ids[i];
				if(typeof data[k] !== 'undefined') $('#'+k).html(data[k]);
			}
			buildTodo(data);

			var t=$("#chart-classic-dash");$.plot(t,[{label:"订单量",data:data.chart.orders,lines:{show:!0,fill:!0,fillColor:{colors:[{opacity:.6},{opacity:.6}]}},points:{show:!0,radius:5}},{label:"交易量",data:data.chart.money,lines:{show:!0,fill:!0,fillColor:{colors:[{opacity:.6},{opacity:.6}]}},points:{show:!0,radius:5}}],{colors:["#5ccdde","#454e59"],legend:{show:!0,position:"nw",backgroundOpacity:0},grid:{borderWidth:0,hoverable:!0,clickable:!0},yaxis:{show:!1,tickColor:"#f5f5f5",ticks:3},xaxis:{ticks:data.chart.date,tickColor:"#f9f9f9"}});var s=null,r=null;t.bind("plothover",function(o,t,i){if(i){if(s!==i.dataIndex){s=i.dataIndex,$("#chart-tooltip").remove();var l=(i.datapoint[0],i.datapoint[1]);r=1===i.seriesIndex?"$ <strong>"+l+"</strong>":0===i.seriesIndex?"<strong>"+l+"</strong> sales":"<strong>"+l+"</strong> tickets",$('<div id="chart-tooltip" class="chart-tooltip">'+r+"</div>").css({top:i.pageY-45,left:i.pageX+5}).appendTo("body").show()}}else $("#chart-tooltip").remove(),s=null});
		}
	});
})
</script>
