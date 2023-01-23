<?php
$is_defend = true;
/** @var array $conf */
/** @var string $siteurl */
/** @var \lib\PdoHelper $DB */
include("./includes/common.php");
?>
<!-- 文档撰写者：Lock、云蓝 ：Email: admin@qqzwb.com -->
<!-- 如有不正确的地方需要更正请使用Email告诉我们。感谢您的支持！ !-->

<!DOCTYPE html>
<html lang="zh-CN">
	<head>
		<meta charset="utf-8" />
		<title><?php echo $conf['sitename']?> - 对接文档</title>
		<meta name="viewport" content="user-scalable=no, width=device-width">
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<meta name="renderer" content="webkit">
		<link rel="stylesheet" href="//cdn.staticfile.org/font-awesome/4.7.0/css/font-awesome.min.css" />
		<link rel="stylesheet" href="//cdn.staticfile.org/twitter-bootstrap/3.4.1/css/bootstrap.min.css" type="text/css" />
		<link rel="stylesheet" href="/assets/docs/common.css">
		<link rel="stylesheet" href="/assets/docs/index-top.css">
		<script src="//cdn.staticfile.org/jquery/1.12.4/jquery.min.js"></script>
		<script src="//cdn.staticfile.org/twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
		<script src="//cdn.staticfile.org/jquery-ujs/1.2.2/rails.min.js" async="true"></script>
		<link rel="stylesheet" type="text/css" href="/assets/docs/index.css" />
		<style>
			body {
				color: #000;
			}

			header {
				position: relative;
			}

			.bann {
				content: '';
				background-size: 100%;
				background: #4280cb;
				background: -webkit-gradient(linear, 0 0, 0 100%, from(#4585d2), to(#4280cb));
				background: -moz-linear-gradient(top, #4585d2, #4280cb);
				background: linear-gradient(to bottom, #4585d2, #4280cb);
				top: 0;
				left: 0;
				z-index: -1;
				min-height: 50px;
				width: 100%
			}

			.fl .active {
				color: #3F5061;
				background: #fff;
				border-color: #fff
			}

			.api_block {
				margin-bottom: 4em;
			}
		</style>
	</head>
	<body>
		<header>
			<nav id="main-nav" class="navbar navbar-default" role="navigation">
				<div class="container">
					<div class="row">
						<div class="navbar-header">
							<button type="button" class="toggle navbar-toggle collapsed" data-toggle="collapse"
								data-target=".navbar-top-collapse">
								<span class="sr-only">Toggle navigation</span>
								<span class="icon-bar"></span>
								<span class="icon-bar"></span>
								<span class="icon-bar"></span>
							</button>

						</div>
						<div class="navbar-collapse navbar-top-collapse collapse" style="height: 1px;">
							<ul class="nav navbar-nav navbar-right c_navbar">
							</ul>
							<ul class="nav navbar-nav navbar-right z_navbar">
								<li><a href="/">自助下单</a></li>
                                <?php
                                if (isset($islogin)) echo '<li><a href="/user">用户中心</a></li>';
                                else echo '<li><a href="/user/reg.php">开通分站</a></li>';
                                ?>

							</ul>
						</div>
					</div>
				</div>
			</nav>
		</header>
		<main>
			<div class="bann">
				<div class="col-xs-12" style="text-align:center;">
					<div class="h3" style="color:#ffffff;margin-top: 35px;margin-bottom: 30px;">对接文档</div>
					<div style="clear:both;"></div>
				</div>
				<div style="clear:both;"></div>
			</div>
			<div class="container">
				<div class="row">
					<div class="col-md-3 ">
						<div id="toc" class="bc-sidebar">
							<ul class="nav">
								<hr />
								<li class="toc-h2"><a href="#code-explain">错误码释义</a></li>
								<hr />
								<li class="toc-h2"><a href="#api_helper.clone">[API] 克隆商品信息</a></li>
								<li class="toc-h2"><a href="#api_helper.get_goods">[API] 获取商品</a></li>
								<li class="toc-h2"><a href="#api_helper.get_orders">[API] 获取订单</a></li>
								<li class="toc-h2"><a href="#api_helper.change_order_state">[API] 修改订单状态</a></li>
								<li class="toc-h2"><a href="#api_helper.goods_group">[API] 获取商品分类</a></li>
								<li class="toc-h2"><a href="#api_helper.get_group_goods">[API] 获取指定分类下商品</a></li>
								<li class="toc-h2"><a href="#api_helper.get_diy_price_goods">[API] 获取商品【分站加价】</a></li>
								<li class="toc-h2"><a href="#api_helper.goods_detail">[API] 商品详情</a></li>
								<li class="toc-h2"><a href="#api_helper.goods_stock">[API] 获取商品库存</a></li>
								<li class="toc-h2"><a href="#api_helper.pay_goods">[API] 商品下单</a></li>
								<li class="toc-h2"><a href="#api_helper.search_order">[API] 搜索订单</a></li>
								<li class="toc-h2"><a href="#api_helper.web_site_info">[API] 站点配置信息</a></li>
								<li class="toc-h2"><a href="#api_helper.make_app_token">[API] 生成AppToken</a></li>
								<hr />
								<li class="toc-h2"><a href="#sdk0">SDK下载</a></li>
								<hr />
							</ul>
						</div>
					</div>

					<div class="col-md-9">
						<article class="post page">
							<section class="post-content">
								<hr />
								<div id="toc2" class="api_block">
									<h3>
										协议规则
									</h3>
									<p>传输方式：HTTPS / HTTP</p>
									<p>数据格式：JSON</p>
									<p>字符编码：UTF-8</p>
									<p>请求网关：<?php echo $siteurl?>api.php</p>
								</div>
								<hr>
								<!-- 接口模块渲染视图 !-->

								<div id="api_helper.clone" class="api_block">
									<h3>克隆商品信息</h3>
									<p style="font-size: 15px;">Path：/api/clone.do</p>
									<p style="font-size: 15px;">请求方式：GET</p>
									<p>请求参数列表：</p>
									<table class="table table-bordered table-hover">
										<thead>
											<tr>
												<th nowrap="nowrap" class="text-center">字段名</th>
												<th nowrap="nowrap" class="text-center">变量名</th>
												<th nowrap="nowrap" class="text-center">必填</th>
												<th nowrap="nowrap" class="text-center">类型</th>
												<th nowrap="nowrap" class="text-center">默认值</th>
												<th nowrap="nowrap" class="text-center">描述</th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td nowrap="nowrap" class="text-center">克隆密钥</td>
												<td nowrap="nowrap" class="text-center">key</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
												<td nowrap="nowrap" class="text-center">向站长咨询获取</td>
											</tr>
										</tbody>
									</table>
									<p>响应参数：其余字段</p>
									<table class="table table-bordered table-hover">
										<thead>
											<tr>
												<th nowrap="nowrap" class="text-center">字段名</th>
												<th nowrap="nowrap" class="text-center">变量名</th>
												<th nowrap="nowrap" class="text-center">必填</th>
												<th nowrap="nowrap" class="text-center">类型</th>
												<th nowrap="nowrap" class="text-center">描述</th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td nowrap="nowrap" class="text-center">结果码</td>
												<td nowrap="nowrap" class="text-center">code</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">商品分类</td>
												<td nowrap="nowrap" class="text-center">class</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">List</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
											<tr>
												<td nowrap="nowrap" class="text-center">商品列表</td>
												<td nowrap="nowrap" class="text-center">tools</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">List</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
											<tr>
												<td nowrap="nowrap" class="text-center">社区列表</td>
												<td nowrap="nowrap" class="text-center">shequ</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">List</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">销售价格模板</td>
												<td nowrap="nowrap" class="text-center">price</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">List</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
										</tbody>
									</table>
								</div>
								<hr>

                                <div id="api_helper.get_goods" class="api_block">
									<h3>获取商品【不可对分类进行条件查询】</h3>
									<p style="font-size: 15px;">Path：/api/tools.do</p>
									<p style="font-size: 15px;">请求方式：GET</p>
									<p>请求参数列表：</p>
									<table class="table table-bordered table-hover">
										<thead>
											<tr>
												<th nowrap="nowrap" class="text-center">字段名</th>
												<th nowrap="nowrap" class="text-center">变量名</th>
												<th nowrap="nowrap" class="text-center">必填</th>
												<th nowrap="nowrap" class="text-center">类型</th>
												<th nowrap="nowrap" class="text-center">默认值</th>
												<th nowrap="nowrap" class="text-center">描述</th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td nowrap="nowrap" class="text-center">对接密钥</td>
												<td nowrap="nowrap" class="text-center">key</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
												<td nowrap="nowrap" class="text-center">API对接密钥</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">条数</td>
												<td nowrap="nowrap" class="text-center">limit</td>
												<td nowrap="nowrap" class="text-center">否</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center">50</td>
												<td nowrap="nowrap" class="text-center">获取商品条数</td>
											</tr>
										</tbody>
									</table>
									<p>响应参数：</p>
									<table class="table table-bordered table-hover">
										<thead>
											<tr>
												<th nowrap="nowrap" class="text-center">字段名</th>
												<th nowrap="nowrap" class="text-center">变量名</th>
												<th nowrap="nowrap" class="text-center">必填</th>
												<th nowrap="nowrap" class="text-center">类型</th>
												<th nowrap="nowrap" class="text-center">描述</th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td nowrap="nowrap" class="text-center">商品ID</td>
												<td nowrap="nowrap" class="text-center">tid</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
											<tr>
												<td nowrap="nowrap" class="text-center">分类ID</td>
												<td nowrap="nowrap" class="text-center">cid</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
											<tr>
												<td nowrap="nowrap" class="text-center">排序</td>
												<td nowrap="nowrap" class="text-center">sort</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">商品名称</td>
												<td nowrap="nowrap" class="text-center">name</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">销售价格</td>
												<td nowrap="nowrap" class="text-center">price</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
										</tbody>
									</table>
								</div>
								<hr>

                                <div id="api_helper.get_orders" class="api_block">
									<h3>获取订单</h3>
									<p style="font-size: 15px;">Path：/api/orders.do</p>
									<p style="font-size: 15px;">请求方式：GET</p>
									<p>请求参数列表：</p>
									<table class="table table-bordered table-hover">
										<thead>
											<tr>
												<th nowrap="nowrap" class="text-center">字段名</th>
												<th nowrap="nowrap" class="text-center">变量名</th>
												<th nowrap="nowrap" class="text-center">必填</th>
												<th nowrap="nowrap" class="text-center">类型</th>
												<th nowrap="nowrap" class="text-center">默认值</th>
												<th nowrap="nowrap" class="text-center">描述</th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td nowrap="nowrap" class="text-center">对接密钥</td>
												<td nowrap="nowrap" class="text-center">key</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
												<td nowrap="nowrap" class="text-center">API对接密钥</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">条数</td>
												<td nowrap="nowrap" class="text-center">limit</td>
												<td nowrap="nowrap" class="text-center">否</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center">50</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">商品ID</td>
												<td nowrap="nowrap" class="text-center">tid</td>
												<td nowrap="nowrap" class="text-center">否</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
												<td nowrap="nowrap" class="text-center">商品ID</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">签名</td>
												<td nowrap="nowrap" class="text-center">sign</td>
												<td nowrap="nowrap" class="text-center">否</td>
												<td nowrap="nowrap" class="text-center">mixed</td>
												<td nowrap="nowrap" class="text-center"></td>
												<td nowrap="nowrap" class="text-center">传入此参数代表订单已完成支付交易，会自动将交易状态字段【 status】 变更为已完成</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">格式</td>
												<td nowrap="nowrap" class="text-center">format</td>
												<td nowrap="nowrap" class="text-center">否</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center">json</td>
												<td nowrap="nowrap" class="text-center">获取数据格式,文本根式传入：text</td>
											</tr>
										</tbody>
									</table>
									<p>响应参数：</p>
									<table class="table table-bordered table-hover">
										<thead>
											<tr>
												<th nowrap="nowrap" class="text-center">字段名</th>
												<th nowrap="nowrap" class="text-center">变量名</th>
												<th nowrap="nowrap" class="text-center">必填</th>
												<th nowrap="nowrap" class="text-center">类型</th>
												<th nowrap="nowrap" class="text-center">描述</th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td nowrap="nowrap" class="text-center">订单ID</td>
												<td nowrap="nowrap" class="text-center">id</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
											<tr>
												<td nowrap="nowrap" class="text-center">商品ID</td>
												<td nowrap="nowrap" class="text-center">tid</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
											<tr>
												<td nowrap="nowrap" class="text-center">下单输入框1</td>
												<td nowrap="nowrap" class="text-center">input</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">下单输入框2</td>
												<td nowrap="nowrap" class="text-center">input2</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">下单输入框3</td>
												<td nowrap="nowrap" class="text-center">input3</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">下单输入框4</td>
												<td nowrap="nowrap" class="text-center">input4</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">下单输入框5</td>
												<td nowrap="nowrap" class="text-center">input5</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">份数</td>
												<td nowrap="nowrap" class="text-center">value</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">交易状态</td>
												<td nowrap="nowrap" class="text-center">status</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center">0:未支付，1:已支付</td>
											</tr>
										</tbody>
									</table>
								</div>
								<hr>

                                <div id="api_helper.change_order_state" class="api_block">
									<h3>修改订单状态</h3>
									<p style="font-size: 15px;">Path：/api/change.do</p>
									<p style="font-size: 15px;">请求方式：GET</p>
									<p>请求参数列表：</p>
									<table class="table table-bordered table-hover">
										<thead>
											<tr>
												<th nowrap="nowrap" class="text-center">字段名</th>
												<th nowrap="nowrap" class="text-center">变量名</th>
												<th nowrap="nowrap" class="text-center">必填</th>
												<th nowrap="nowrap" class="text-center">类型</th>
												<th nowrap="nowrap" class="text-center">默认值</th>
												<th nowrap="nowrap" class="text-center">描述</th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td nowrap="nowrap" class="text-center">对接密钥</td>
												<td nowrap="nowrap" class="text-center">key</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
												<td nowrap="nowrap" class="text-center">API对接密钥</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">订单ID</td>
												<td nowrap="nowrap" class="text-center">id</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
												<td nowrap="nowrap" class="text-center">获取订单-响应参数-id</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">变更状态</td>
												<td nowrap="nowrap" class="text-center">zt</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
												<td nowrap="nowrap" class="text-center">1:已完成,2:正在处理,3:异常,4:待处理</td>
											</tr>
										</tbody>
									</table>
									<p>响应参数：</p>
									<table class="table table-bordered table-hover">
										<thead>
											<tr>
												<th nowrap="nowrap" class="text-center">字段名</th>
												<th nowrap="nowrap" class="text-center">变量名</th>
												<th nowrap="nowrap" class="text-center">必填</th>
												<th nowrap="nowrap" class="text-center">类型</th>
												<th nowrap="nowrap" class="text-center">描述</th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td nowrap="nowrap" class="text-center">订单ID</td>
												<td nowrap="nowrap" class="text-center">id</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>

										</tbody>
									</table>
								</div>
								<hr>

                                <div id="api_helper.goods_group" class="api_block">
									<h3>获取商品分类</h3>
									<p style="font-size: 15px;">Path：/api/classlist.do</p>
									<p style="font-size: 15px;">请求方式：GET</p>
									<p>请求参数列表：</p>
									<table class="table table-bordered table-hover">
										<thead>
											<tr>
												<th nowrap="nowrap" class="text-center">字段名</th>
												<th nowrap="nowrap" class="text-center">变量名</th>
												<th nowrap="nowrap" class="text-center">必填</th>
												<th nowrap="nowrap" class="text-center">类型</th>
												<th nowrap="nowrap" class="text-center">默认值</th>
												<th nowrap="nowrap" class="text-center">描述</th>
											</tr>
										</thead>
										<tbody>
										</tbody>
									</table>
									<p>响应参数：</p>
									<table class="table table-bordered table-hover">
										<thead>
											<tr>
												<th nowrap="nowrap" class="text-center">字段名</th>
												<th nowrap="nowrap" class="text-center">变量名</th>
												<th nowrap="nowrap" class="text-center">必填</th>
												<th nowrap="nowrap" class="text-center">类型</th>
												<th nowrap="nowrap" class="text-center">描述</th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td nowrap="nowrap" class="text-center">结果码</td>
												<td nowrap="nowrap" class="text-center">code</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center">1</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">分类ID</td>
												<td nowrap="nowrap" class="text-center">data[][cid]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">分站ID</td>
												<td nowrap="nowrap" class="text-center">data[][zid]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">排序</td>
												<td nowrap="nowrap" class="text-center">data[][sort]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">禁止支付的方式</td>
												<td nowrap="nowrap" class="text-center">data[][blockpay]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center">alipay:支付宝,qqpay:QQ钱包,wxpay:微信支付,rmb:余额</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">是否可用</td>
												<td nowrap="nowrap" class="text-center">data[][active]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center">0:不可用,1:可用</td>
											</tr>

										</tbody>
									</table>
								</div>
								<hr>

                                <div id="api_helper.get_group_goods" class="api_block">
									<h3>获取指定分类下商品</h3>
									<p style="font-size: 15px;">Path：/api/goodslistbycid.do</p>
									<p style="font-size: 15px;">请求方式：POST</p>
									<p>请求参数列表：</p>
									<table class="table table-bordered table-hover">
										<thead>
											<tr>
												<th nowrap="nowrap" class="text-center">字段名</th>
												<th nowrap="nowrap" class="text-center">变量名</th>
												<th nowrap="nowrap" class="text-center">必填</th>
												<th nowrap="nowrap" class="text-center">类型</th>
												<th nowrap="nowrap" class="text-center">默认值</th>
												<th nowrap="nowrap" class="text-center">描述</th>
											</tr>
										</thead>
										<tbody>
                                            <tr>
                                                <td nowrap="nowrap" class="text-center">用户名</td>
                                                <td nowrap="nowrap" class="text-center">user</td>
                                                <td nowrap="nowrap" class="text-center">否</td>
                                                <td nowrap="nowrap" class="text-center">string</td>
                                                <td nowrap="nowrap" class="text-center"></td>
                                                <td  class="text-center">传入分站用户名和密码，商品返回的价格是分站站长成本 + 自定义销售价格</td>
                                            </tr>
                                            <tr>
                                                <td nowrap="nowrap" class="text-center">密码</td>
                                                <td nowrap="nowrap" class="text-center">pass</td>
                                                <td nowrap="nowrap" class="text-center">否</td>
                                                <td nowrap="nowrap" class="text-center">string</td>
                                                <td nowrap="nowrap" class="text-center"></td>
                                                <td nowrap="nowrap" class="text-center"></td>
                                            </tr>
                                            <tr>
                                                <td nowrap="nowrap" class="text-center">商品分类ID</td>
                                                <td nowrap="nowrap" class="text-center">cid</td>
                                                <td nowrap="nowrap" class="text-center">否</td>
                                                <td nowrap="nowrap" class="text-center">int</td>
                                                <td nowrap="nowrap" class="text-center">0</td>
                                                <td nowrap="nowrap" class="text-center"></td>
                                            </tr>
										</tbody>
									</table>
									<p>响应参数：</p>
									<table class="table table-bordered table-hover">
										<thead>
											<tr>
												<th nowrap="nowrap" class="text-center">字段名</th>
												<th nowrap="nowrap" class="text-center">变量名</th>
												<th nowrap="nowrap" class="text-center">必填</th>
												<th nowrap="nowrap" class="text-center">类型</th>
												<th nowrap="nowrap" class="text-center">描述</th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td nowrap="nowrap" class="text-center">结果码</td>
												<td nowrap="nowrap" class="text-center">code</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center">1</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">分类总记录数</td>
												<td nowrap="nowrap" class="text-center">count</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>

                                            <tr>
												<td nowrap="nowrap" class="text-center">商品ID</td>
												<td nowrap="nowrap" class="text-center">data[][tid]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">商品分类ID</td>
												<td nowrap="nowrap" class="text-center">data[][cid]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">排序</td>
												<td nowrap="nowrap" class="text-center">data[][sort]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">商品名称</td>
												<td nowrap="nowrap" class="text-center">data[][name]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">默认数量</td>
												<td nowrap="nowrap" class="text-center">data[][value]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">销售价格</td>
												<td nowrap="nowrap" class="text-center">data[][price]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">首个下单输入框</td>
												<td nowrap="nowrap" class="text-center">data[][input]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">多个下单输入框</td>
												<td nowrap="nowrap" class="text-center">data[][inputs]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">商品简介</td>
												<td nowrap="nowrap" class="text-center">data[][desc]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">下单提示</td>
												<td nowrap="nowrap" class="text-center">data[][alert]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">商品图</td>
												<td nowrap="nowrap" class="text-center">data[][shopimg]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">验证操作</td>
												<td nowrap="nowrap" class="text-center">data[][validate]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center">0:不开启验证<br />1:验证QQ空间是否有访问权限<br />2:验证已开通服务(符合则禁止下单)<br />3:验证已开通服务(符合则不对接社区)</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">下单前验证账户已开通某项指定的服务</td>
												<td nowrap="nowrap" class="text-center">data[][valiserv]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td class="text-center">vip:QQ会员,svip:超级会员,red:红钻贵族<br />green:绿钻规则,sgreen:绿钻豪华版,yellow:黄钻贵族<br>syellow:豪华黄钻,hollywood:腾讯视频VIP,qqmsey:付费音乐包<br />qqmstw:豪华付费音乐包,weiyun:微云,sweiyun:微云超级会员</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">允许重复下单</td>
												<td nowrap="nowrap" class="text-center">data[][repeat]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
                                                <td nowrap="nowrap" class="text-center">0:否,1:是</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">显示数量选择框</td>
												<td nowrap="nowrap" class="text-center">data[][multi]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
                                                <td nowrap="nowrap" class="text-center">0:否,1:是</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">批发价格优惠</td>
												<td nowrap="nowrap" class="text-center">data[][prices]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
                                                <td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">最小下单数量</td>
												<td nowrap="nowrap" class="text-center">data[][min]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
                                                <td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">最大下单数量</td>
												<td nowrap="nowrap" class="text-center">data[][max]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
                                                <td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">是否为发卡商品</td>
												<td nowrap="nowrap" class="text-center">data[][isfaka]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
                                                <td nowrap="nowrap" class="text-center">0:否,1:是</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">库存数量</td>
												<td nowrap="nowrap" class="text-center">data[][stock]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
                                                <td nowrap="nowrap" class="text-center">发卡商品有效</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">是否关闭下单</td>
												<td nowrap="nowrap" class="text-center">data[][close]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
                                                <td nowrap="nowrap" class="text-center"></td>
											</tr>

										</tbody>
									</table>
								</div>
								<hr>


                                <div id="api_helper.get_diy_price_goods" class="api_block">
									<h3>获取商品【分站加价】</h3>
									<p style="font-size: 15px;">Path：/api/goodslist.do</p>
									<p style="font-size: 15px;">请求方式：GET</p>
									<p>请求参数列表：</p>
									<table class="table table-bordered table-hover">
										<thead>
											<tr>
												<th nowrap="nowrap" class="text-center">字段名</th>
												<th nowrap="nowrap" class="text-center">变量名</th>
												<th nowrap="nowrap" class="text-center">必填</th>
												<th nowrap="nowrap" class="text-center">类型</th>
												<th nowrap="nowrap" class="text-center">默认值</th>
												<th nowrap="nowrap" class="text-center">描述</th>
											</tr>
										</thead>
										<tbody>
                                            <tr>
                                                <td nowrap="nowrap" class="text-center">用户名</td>
                                                <td nowrap="nowrap" class="text-center">user</td>
                                                <td nowrap="nowrap" class="text-center">否</td>
                                                <td nowrap="nowrap" class="text-center">string</td>
                                                <td nowrap="nowrap" class="text-center"></td>
                                                <td  class="text-center">POST方式传入，分站用户名。如果传入分站用户名和密码，商品返回的价格是分站站长成本 + 自定义销售价格</td>
                                            </tr>
                                            <tr>
                                                <td nowrap="nowrap" class="text-center">密码</td>
                                                <td nowrap="nowrap" class="text-center">pass</td>
                                                <td nowrap="nowrap" class="text-center">否</td>
                                                <td nowrap="nowrap" class="text-center">string</td>
                                                <td nowrap="nowrap" class="text-center"></td>
                                                <td nowrap="nowrap" class="text-center">分站密码，POST方式传入，</td>
                                            </tr>

										</tbody>
									</table>
									<p>响应参数：</p>
									<table class="table table-bordered table-hover">
										<thead>
											<tr>
												<th nowrap="nowrap" class="text-center">字段名</th>
												<th nowrap="nowrap" class="text-center">变量名</th>
												<th nowrap="nowrap" class="text-center">必填</th>
												<th nowrap="nowrap" class="text-center">类型</th>
												<th nowrap="nowrap" class="text-center">描述</th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td nowrap="nowrap" class="text-center">结果码</td>
												<td nowrap="nowrap" class="text-center">code</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center">1</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">分类总记录数</td>
												<td nowrap="nowrap" class="text-center">count</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>

                                            <tr>
												<td nowrap="nowrap" class="text-center">商品ID</td>
												<td nowrap="nowrap" class="text-center">data[][tid]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">商品分类ID</td>
												<td nowrap="nowrap" class="text-center">data[][cid]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>

                                            <tr>
												<td nowrap="nowrap" class="text-center">商品名称</td>
												<td nowrap="nowrap" class="text-center">data[][name]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">商品图</td>
												<td nowrap="nowrap" class="text-center">data[][shopimg]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">string</td>
												<td nowrap="nowrap" class="text-center"></td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">是否为发卡商品</td>
												<td nowrap="nowrap" class="text-center">data[][isfaka]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
                                                <td nowrap="nowrap" class="text-center">0:否,1:是</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">库存数量</td>
												<td nowrap="nowrap" class="text-center">data[][stock]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
                                                <td nowrap="nowrap" class="text-center">当为null则代表无限库存</td>
											</tr>
                                            <tr>
												<td nowrap="nowrap" class="text-center">是否关闭下单</td>
												<td nowrap="nowrap" class="text-center">data[][close]</td>
												<td nowrap="nowrap" class="text-center">是</td>
												<td nowrap="nowrap" class="text-center">int</td>
                                                <td nowrap="nowrap" class="text-center"></td>
											</tr>

										</tbody>
									</table>
								</div>
								<hr>

                                <div id="api_helper.goods_detail" class="api_block">
                                    <h3>商品详情</h3>
                                    <p style="font-size: 15px;">Path：/api/goodsdetails.do</p>
                                    <p style="font-size: 15px;">请求方式：POST</p>
                                    <p>请求参数列表：</p>
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                        <tr>
                                            <th nowrap="nowrap" class="text-center">字段名</th>
                                            <th nowrap="nowrap" class="text-center">变量名</th>
                                            <th nowrap="nowrap" class="text-center">必填</th>
                                            <th nowrap="nowrap" class="text-center">类型</th>
                                            <th nowrap="nowrap" class="text-center">默认值</th>
                                            <th nowrap="nowrap" class="text-center">描述</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">分站用户名</td>
                                            <td nowrap="nowrap" class="text-center">user</td>
                                            <td nowrap="nowrap" class="text-center">否</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                            <td class="text-center">如果传入分站用户名和密码，商品返回的价格是分站站长成本 + 自定义销售价格</td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">分站密码</td>
                                            <td nowrap="nowrap" class="text-center">pass</td>
                                            <td nowrap="nowrap" class="text-center">否</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                            <td  class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">商品ID</td>
                                            <td nowrap="nowrap" class="text-center">tid</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <p>响应参数：</p>
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                        <tr>
                                            <th nowrap="nowrap" class="text-center">字段名</th>
                                            <th nowrap="nowrap" class="text-center">变量名</th>
                                            <th nowrap="nowrap" class="text-center">必填</th>
                                            <th nowrap="nowrap" class="text-center">类型</th>
                                            <th nowrap="nowrap" class="text-center">描述</th>
                                        </tr>
                                        </thead>
                                        <tbody>

                                        <tr>
                                            <td nowrap="nowrap" class="text-center">商品ID</td>
                                            <td nowrap="nowrap" class="text-center">tid</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">分类ID</td>
                                            <td nowrap="nowrap" class="text-center">cid</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">排序</td>
                                            <td nowrap="nowrap" class="text-center">sort</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">商品名称</td>
                                            <td nowrap="nowrap" class="text-center">name</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">默认数量</td>
                                            <td nowrap="nowrap" class="text-center">value</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>

                                        <tr>
                                            <td nowrap="nowrap" class="text-center">销售价格</td>
                                            <td nowrap="nowrap" class="text-center">price</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">首个下单输入框</td>
                                            <td nowrap="nowrap" class="text-center">input</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">多个下单输入框</td>
                                            <td nowrap="nowrap" class="text-center">inputs</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">商品简介</td>
                                            <td nowrap="nowrap" class="text-center">desc</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">下单提示</td>
                                            <td nowrap="nowrap" class="text-center">alert</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">商品图</td>
                                            <td nowrap="nowrap" class="text-center">shopimg</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">验证操作</td>
                                            <td nowrap="nowrap" class="text-center">validate</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center">0:不开启验证<br />1:验证QQ空间是否有访问权限<br />2:验证已开通服务(符合则禁止下单)<br />3:验证已开通服务(符合则不对接社区)</td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">下单前验证账户已开通某项指定的服务</td>
                                            <td nowrap="nowrap" class="text-center">valiserv</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td class="text-center">vip:QQ会员,svip:超级会员,red:红钻贵族<br />green:绿钻规则,sgreen:绿钻豪华版,yellow:黄钻贵族<br>syellow:豪华黄钻,hollywood:腾讯视频VIP,qqmsey:付费音乐包<br />qqmstw:豪华付费音乐包,weiyun:微云,sweiyun:微云超级会员</td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">允许重复下单</td>
                                            <td nowrap="nowrap" class="text-center">repeat</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center">0:否,1:是</td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">显示数量选择框</td>
                                            <td nowrap="nowrap" class="text-center">multi</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center">0:否,1:是</td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">批发价格优惠</td>
                                            <td nowrap="nowrap" class="text-center">prices</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">最小下单数量</td>
                                            <td nowrap="nowrap" class="text-center">min</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">最大下单数量</td>
                                            <td nowrap="nowrap" class="text-center">max</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">是否为发卡商品</td>
                                            <td nowrap="nowrap" class="text-center">isfaka</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center">0:否,1:是</td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">库存数量</td>
                                            <td nowrap="nowrap" class="text-center">stock</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center">当为null则代表无限库存</td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">是否关闭下单</td>
                                            <td nowrap="nowrap" class="text-center">close</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <hr>

                                <div id="api_helper.goods_stock" class="api_block">
                                    <h3>获取商品库存</h3>
                                    <p style="font-size: 15px;">Path：/api/getleftcount.do</p>
                                    <p style="font-size: 15px;">请求方式：GET</p>
                                    <p>请求参数列表：</p>
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                        <tr>
                                            <th nowrap="nowrap" class="text-center">字段名</th>
                                            <th nowrap="nowrap" class="text-center">变量名</th>
                                            <th nowrap="nowrap" class="text-center">必填</th>
                                            <th nowrap="nowrap" class="text-center">类型</th>
                                            <th nowrap="nowrap" class="text-center">默认值</th>
                                            <th nowrap="nowrap" class="text-center">描述</th>
                                        </tr>
                                        </thead>
                                        <tbody>

                                        <tr>
                                            <td nowrap="nowrap" class="text-center">商品ID</td>
                                            <td nowrap="nowrap" class="text-center">tid</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string / int</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                            <td nowrap="nowrap" class="text-center">多个商品id使用,逗号分隔，最多只能查询20个</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <p>响应参数：</p>
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                        <tr>
                                            <th nowrap="nowrap" class="text-center">字段名</th>
                                            <th nowrap="nowrap" class="text-center">变量名</th>
                                            <th nowrap="nowrap" class="text-center">必填</th>
                                            <th nowrap="nowrap" class="text-center">类型</th>
                                            <th nowrap="nowrap" class="text-center">描述</th>
                                        </tr>
                                        </thead>
                                        <tbody>

                                        <tr>
                                            <td nowrap="nowrap" class="text-center">商品ID</td>
                                            <td nowrap="nowrap" class="text-center">data[][tid]</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center">商品ID：多个条件查询才会以List方式返回</td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">库存数量</td>
                                            <td nowrap="nowrap" class="text-center">data[][stock]</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int / null</td>
                                            <td nowrap="nowrap" class="text-center">商品库存量：多个条件查询才会以List方式返回, 当为null则代表无限库存</td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">库存数量</td>
                                            <td nowrap="nowrap" class="text-center">count</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center">商品库存量：单个条件查询</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <hr>

                                <div id="api_helper.pay_goods" class="api_block">
                                    <h3>商品下单</h3>
                                    <p style="font-size: 15px;">Path：/api/pay.do</p>
                                    <p style="font-size: 15px;">请求方式：POST</p>
                                    <p>请求参数列表：</p>
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                        <tr>
                                            <th nowrap="nowrap" class="text-center">字段名</th>
                                            <th nowrap="nowrap" class="text-center">变量名</th>
                                            <th nowrap="nowrap" class="text-center">必填</th>
                                            <th nowrap="nowrap" class="text-center">类型</th>
                                            <th nowrap="nowrap" class="text-center">默认值</th>
                                            <th nowrap="nowrap" class="text-center">描述</th>
                                        </tr>
                                        </thead>
                                        <tbody>

                                        <tr>
                                            <td nowrap="nowrap" class="text-center">商品ID</td>
                                            <td nowrap="nowrap" class="text-center">tid</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">分站用户名</td>
                                            <td nowrap="nowrap" class="text-center">user</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">分站密码</td>
                                            <td nowrap="nowrap" class="text-center">pass</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">下单输入框1</td>
                                            <td nowrap="nowrap" class="text-center">input1</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">下单输入框2</td>
                                            <td nowrap="nowrap" class="text-center">input2</td>
                                            <td nowrap="nowrap" class="text-center">否</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                            <td nowrap="nowrap" class="text-center">根据商品inputs定义</td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">下单输入框3</td>
                                            <td nowrap="nowrap" class="text-center">input3</td>
                                            <td nowrap="nowrap" class="text-center">否</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                            <td nowrap="nowrap" class="text-center">根据商品inputs定义</td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">下单输入框4</td>
                                            <td nowrap="nowrap" class="text-center">input4</td>
                                            <td nowrap="nowrap" class="text-center">否</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                            <td nowrap="nowrap" class="text-center">根据商品inputs定义</td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">下单输入框5</td>
                                            <td nowrap="nowrap" class="text-center">input5</td>
                                            <td nowrap="nowrap" class="text-center">否</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                            <td nowrap="nowrap" class="text-center">根据商品inputs定义</td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">数量</td>
                                            <td nowrap="nowrap" class="text-center">num</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center">1</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <p>响应参数：</p>
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                        <tr>
                                            <th nowrap="nowrap" class="text-center">字段名</th>
                                            <th nowrap="nowrap" class="text-center">变量名</th>
                                            <th nowrap="nowrap" class="text-center">必填</th>
                                            <th nowrap="nowrap" class="text-center">类型</th>
                                            <th nowrap="nowrap" class="text-center">描述</th>
                                        </tr>
                                        </thead>
                                        <tbody>

                                        <tr>
                                            <td nowrap="nowrap" class="text-center">下单结果</td>
                                            <td nowrap="nowrap" class="text-center">message</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <hr>

                                <div id="api_helper.search_order" class="api_block">
                                    <h3>搜索订单</h3>
                                    <p style="font-size: 15px;">Path：/api/search.do</p>
                                    <p style="font-size: 15px;">请求方式：POST / GET</p>
                                    <p>请求参数列表：</p>
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                        <tr>
                                            <th nowrap="nowrap" class="text-center">字段名</th>
                                            <th nowrap="nowrap" class="text-center">变量名</th>
                                            <th nowrap="nowrap" class="text-center">必填</th>
                                            <th nowrap="nowrap" class="text-center">类型</th>
                                            <th nowrap="nowrap" class="text-center">默认值</th>
                                            <th nowrap="nowrap" class="text-center">描述</th>
                                        </tr>
                                        </thead>
                                        <tbody>

                                        <tr>
                                            <td nowrap="nowrap" class="text-center">订单ID</td>
                                            <td nowrap="nowrap" class="text-center">id</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <p>响应参数：</p>
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                        <tr>
                                            <th nowrap="nowrap" class="text-center">字段名</th>
                                            <th nowrap="nowrap" class="text-center">变量名</th>
                                            <th nowrap="nowrap" class="text-center">必填</th>
                                            <th nowrap="nowrap" class="text-center">类型</th>
                                            <th nowrap="nowrap" class="text-center">描述</th>
                                        </tr>
                                        </thead>
                                        <tbody>

                                        <tr>
                                            <td nowrap="nowrap" class="text-center">查询结果</td>
                                            <td nowrap="nowrap" class="text-center">message</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">对接类型</td>
                                            <td nowrap="nowrap" class="text-center">type</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td  class="text-center">0:本站处理,2:自动提交到社区/卡盟,1:自定义访问URL,4:自动发卡卡密,3:自动发送提醒邮件/微信,5:直接显示指定内容</td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">订单状态</td>
                                            <td nowrap="nowrap" class="text-center">status</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td class="text-center">0:待处理,1:已完成,2:正在处理:3:异常,4:已退单</td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">对接上游平台处理响应结果</td>
                                            <td nowrap="nowrap" class="text-center">data</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">mixed</td>
                                            <td nowrap="nowrap" class="text-center">三方插件处理结果，具有多样性结果，处理失败则返回布尔值 false</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <hr>

                                <div id="api_helper.web_site_info" class="api_block">
                                    <h3>站点基础配置信息</h3>
                                    <p style="font-size: 15px;">Path：/api/siteinfo.do</p>
                                    <p style="font-size: 15px;">请求方式：POST / GET</p>
                                    <p>请求参数列表：</p>
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                        <tr>
                                            <th nowrap="nowrap" class="text-center">字段名</th>
                                            <th nowrap="nowrap" class="text-center">变量名</th>
                                            <th nowrap="nowrap" class="text-center">必填</th>
                                            <th nowrap="nowrap" class="text-center">类型</th>
                                            <th nowrap="nowrap" class="text-center">默认值</th>
                                            <th nowrap="nowrap" class="text-center">描述</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                    <p>响应参数：</p>
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                        <tr>
                                            <th nowrap="nowrap" class="text-center">字段名</th>
                                            <th nowrap="nowrap" class="text-center">变量名</th>
                                            <th nowrap="nowrap" class="text-center">必填</th>
                                            <th nowrap="nowrap" class="text-center">类型</th>
                                            <th nowrap="nowrap" class="text-center">描述</th>
                                        </tr>
                                        </thead>
                                        <tbody>

                                        <tr>
                                            <td nowrap="nowrap" class="text-center">站点名称</td>
                                            <td nowrap="nowrap" class="text-center">sitename</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">客服QQ</td>
                                            <td nowrap="nowrap" class="text-center">kfqq</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">首页公告</td>
                                            <td nowrap="nowrap" class="text-center">anounce</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">首页弹出公告</td>
                                            <td nowrap="nowrap" class="text-center">modal</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">站点工具/友情链接</td>
                                            <td nowrap="nowrap" class="text-center">bottom</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">在线下单提示</td>
                                            <td nowrap="nowrap" class="text-center">alert</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">订单查询页面公告</td>
                                            <td nowrap="nowrap" class="text-center">gg_search</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">分站后台公告</td>
                                            <td nowrap="nowrap" class="text-center">gg_panel</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">程序版本</td>
                                            <td nowrap="nowrap" class="text-center">version</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">程序发行版本号</td>
                                            <td nowrap="nowrap" class="text-center">build</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">累计订单数量</td>
                                            <td nowrap="nowrap" class="text-center">orders</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">累计支付成功订单数量</td>
                                            <td nowrap="nowrap" class="text-center">orders1</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">分站数量</td>
                                            <td nowrap="nowrap" class="text-center">sites</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">APP启动弹出内容</td>
                                            <td nowrap="nowrap" class="text-center">appalert</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <hr>

                                <div id="api_helper.make_app_token" class="api_block">
                                    <h3>生成Apptoken通信密钥</h3>
                                    <p style="font-size: 15px;">Path：/api/token.do</p>
                                    <p style="font-size: 15px;">请求方式：GET</p>
                                    <p>请求参数列表：</p>
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                        <tr>
                                            <th nowrap="nowrap" class="text-center">字段名</th>
                                            <th nowrap="nowrap" class="text-center">变量名</th>
                                            <th nowrap="nowrap" class="text-center">必填</th>
                                            <th nowrap="nowrap" class="text-center">类型</th>
                                            <th nowrap="nowrap" class="text-center">默认值</th>
                                            <th nowrap="nowrap" class="text-center">描述</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">密钥</td>
                                            <td nowrap="nowrap" class="text-center">key</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <p>响应参数：</p>
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                        <tr>
                                            <th nowrap="nowrap" class="text-center">字段名</th>
                                            <th nowrap="nowrap" class="text-center">变量名</th>
                                            <th nowrap="nowrap" class="text-center">必填</th>
                                            <th nowrap="nowrap" class="text-center">类型</th>
                                            <th nowrap="nowrap" class="text-center">描述</th>
                                        </tr>
                                        </thead>
                                        <tbody>

                                        <tr>
                                            <td nowrap="nowrap" class="text-center">Token</td>
                                            <td nowrap="nowrap" class="text-center">token</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">string</td>
                                            <td nowrap="nowrap" class="text-center">加密后的Appkey</td>
                                        </tr>
                                        <tr>
                                            <td nowrap="nowrap" class="text-center">系统时间戳</td>
                                            <td nowrap="nowrap" class="text-center">time</td>
                                            <td nowrap="nowrap" class="text-center">是</td>
                                            <td nowrap="nowrap" class="text-center">int</td>
                                            <td nowrap="nowrap" class="text-center"></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <hr> 

	

								<!-- 接口模块渲染视图   end -->
								<hr />
								<div id="code-explain" class="api_block">
									<h3>错误码列表</h3>
									<p class="text-danger">
										响应码非等于 1 均已视为失败，具体失败原因msg字段会提示。
									</p>
									<table class="table table-bordered table-hover">
										<thead>
											<tr>
												<th nowrap="nowrap" class="text-center">响应码</th>
												<th nowrap="nowrap" class="text-center">错误信息</th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td class="text-center" nowrap="nowrap">1</td>
												<td class="text-center" nowrap="nowrap">请求成功</td>
											</tr>
											<tr>
												<td class="text-center" nowrap="nowrap">-1</td>
												<td class="text-center" nowrap="nowrap">对接失败</td>
											</tr>
                                            <tr>
												<td class="text-center" nowrap="nowrap">-4</td>
												<td class="text-center" nowrap="nowrap">API对接密钥不正确</td>
											</tr>
                                            <tr>
												<td class="text-center" nowrap="nowrap">-5</td>
												<td class="text-center" nowrap="nowrap">缺少参数</td>
											</tr>
										</tbody>
									</table>
								</div>
								<hr />
								<div id="sdk0" class="api_block">
									<h3>SDK下载</h3>
									<blockquote><a onclick="window.alert('暂未撰写');" style="color:blue">SDK.zip</a><br />SDK版本：V<?php echo $conf['version']?>
									</blockquote>
								</div>
							</section>
						</article>
					</div>
				</div>
			</div>
		</main>

		<div class="address">
			<footer>
				<div class="container">
					<div class="row">
						<div class="col-xs-12 col-md-8 col-lg-9">
							<ul class="porduct">
								<h4>产品</h4>
								<li><a href="#" target="_blank">服务条款</a></li>
								<li><a href="<?php echo $_SERVER['SCRIPT_NAME']?>" target="_blank">开发文档</a></li>
							</ul>
							<ul class="price">
								<h4>关于我们</h4>
								<li><?php echo $conf['sitename']?>是一款生活平台、虚拟币及多种服务自助下单平台</li>
							</ul>
							<ul class="about" style="width: 40%;padding-left: 22px;">
								<h4>联系我们</h4>
								<li><strong>QQ：</strong>
									<a href="https://wpa.qq.com/msgrd?v=3&uin=<?php echo $conf['kfqq']?>&Site=pay&Menu=yes"
										target="_blank"><?php echo $conf['kfqq']?></a>
								</li>
								<li><strong>Email：</strong><a href="mailto:><? echo $conf['kfqq']?>@qq.com"><? echo $conf['kfqq']?>@qq.com</a></li>
							</ul>
						</div>
					</div>
				</div>
				<div class="xinxi" style="background-color: #ffffff;">
					<p><? echo $conf['sitename']?>&nbsp;&nbsp;&copy;<? echo date('Y');?>&nbsp;All Rights Reserved.&nbsp;&nbsp;</p>
				</div>
			</footer>
		</div>
	</body>
</html>
