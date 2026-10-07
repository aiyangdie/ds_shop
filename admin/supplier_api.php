<?php
/**
 * 资源站协议：规范摘要 + 搭建说明（分类入口）
 */
include("../includes/common.php");
$title = '资源站协议';
include './head.php';
if ($islogin != 1) exit("<script>window.location.href='./login.php';</script>");

$specPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'supplier' . DIRECTORY_SEPARATOR . 'API.md';
$readmePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'supplier' . DIRECTORY_SEPARATOR . 'README.md';
$cfgPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'supplier' . DIRECTORY_SEPARATOR . 'config.php';
$cfg = is_file($cfgPath) ? include $cfgPath : [];
if (!is_array($cfg)) $cfg = [];

$pingUrl = '';
$shequ = $DB->getRow("SELECT * FROM pre_shequ WHERE type='daishua' ORDER BY id ASC LIMIT 1");
if ($shequ) {
	$pingUrl = ($shequ['protocol'] == 1 ? 'https://' : 'http://') . $shequ['url'] . '/api.php?act=ping';
}
?>
<div class="col-xs-12 col-sm-10 col-lg-10 center-block" style="float: none;">
	<div class="block">
		<div class="block-title">
			<h3><i class="fa fa-book"></i>&nbsp;资源站协议（daishua v1.0）</h3>
		</div>

		<div class="alert alert-info">
			协议只是管道；<strong>能卖的是服务交付</strong>。资源站下单后会真实执行引擎（体检 / 文本整理 / AI 文案 / 兑换码 / 工单），结果作为发货内容返回。
			别人可对接你的站，也可复制 <code>supplier/</code> 自建供货。
		</div>

		<div class="panel panel-success">
			<div class="panel-heading"><b>可售服务（有交付才有价值）</b></div>
			<div class="table-responsive">
				<table class="table table-striped table-bordered" style="margin-bottom:0">
					<thead>
					<tr><th>tid</th><th>商品</th><th>service</th><th>交付物</th><th>依赖</th></tr>
					</thead>
					<tbody>
					<tr><td>1001</td><td>网站体检报告</td><td><code>site_check</code></td><td>可达/HTTPS/耗时/标题报告</td><td>无</td></tr>
					<tr><td>1002</td><td>文本/JSON 整理</td><td><code>text_tool</code></td><td>格式化结果</td><td>无</td></tr>
					<tr><td>1003</td><td>AI 文案一篇</td><td><code>ai_write</code></td><td>成品文案</td><td>AI Key 或主站 AI</td></tr>
					<tr><td>1004</td><td>AI 次数兑换码×10</td><td><code>ai_credit_pack</code></td><td>可核销码</td><td>无（核销用 act=redeem）</td></tr>
					<tr><td>1005</td><td>人工代办工单</td><td><code>ticket</code></td><td>工单号</td><td>人工跟进</td></tr>
					</tbody>
				</table>
			</div>
			<div class="panel-body">
				<p class="text-muted" style="margin-bottom:8px">引擎代码：<code>supplier/lib/Services.php</code>。加新服务 = 写一个 <code>svc_xxx</code> + goods 里挂 <code>service</code>。</p>
				<a class="btn btn-success btn-sm" href="./api_dock.php">去下单测试真实交付</a>
			</div>
		</div>

		<div class="row">
			<div class="col-sm-4">
				<div class="panel panel-primary">
					<div class="panel-heading"><b>① 协议规范</b></div>
					<div class="panel-body">
						<p>接口：分类、商品列表、详情、下单、查单、可选 ping。</p>
						<p class="text-muted">给对接方 / 自建方统一遵守的报文约定。</p>
						<a class="btn btn-primary btn-sm" href="../supplier/API.md" target="_blank">打开 API.md</a>
					</div>
				</div>
			</div>
			<div class="col-sm-4">
				<div class="panel panel-success">
					<div class="panel-heading"><b>② 自建资源站</b></div>
					<div class="panel-body">
						<p>复制 <code>supplier/</code> 到任意 PHP 主机，改 <code>config.php</code> 即可上线。</p>
						<p class="text-muted">本地演示端口 8081：start-supplier.bat</p>
						<a class="btn btn-success btn-sm" href="../supplier/README.md" target="_blank">打开搭建说明</a>
					</div>
				</div>
			</div>
			<div class="col-sm-4">
				<div class="panel panel-warning">
					<div class="panel-heading"><b>③ 本站对接测试</b></div>
					<div class="panel-body">
						<p>连通、拉品、真实 HTTP 下单、看调用日志。</p>
						<p class="text-muted">对接类型请选「同系统对接」。</p>
						<a class="btn btn-warning btn-sm" href="./api_dock.php">打开货源API对接</a>
					</div>
				</div>
			</div>
		</div>

		<div class="panel panel-default">
			<div class="panel-heading"><b>本机资源站配置摘要</b></div>
			<div class="panel-body">
				<table class="table table-bordered table-condensed" style="max-width:640px;margin-bottom:0">
					<tr><th width="140">账号</th><td><code><?php echo htmlspecialchars(isset($cfg['user']) ? $cfg['user'] : '(未配置)') ?></code></td></tr>
					<tr><th>允许远程</th><td><?php echo !empty($cfg['allow_remote']) ? '<span class="text-danger">是（公网可访问）</span>' : '<span class="text-success">否（仅本机 127.0.0.1）</span>'; ?></td></tr>
					<tr><th>站点名</th><td><?php echo htmlspecialchars(isset($cfg['name']) ? $cfg['name'] : '-') ?></td></tr>
					<tr><th>协议版本</th><td><?php echo htmlspecialchars(isset($cfg['version']) ? $cfg['version'] : '1.0') ?></td></tr>
					<tr><th>当前对接 URL</th><td><?php echo $shequ ? '<code>'.htmlspecialchars(($shequ['protocol']==1?'https://':'http://').$shequ['url']).'</code>' : '<span class="text-muted">尚未配置 daishua 站点</span>'; ?></td></tr>
					<?php if ($pingUrl) { ?>
					<tr><th>探测</th><td><a href="<?php echo htmlspecialchars($pingUrl) ?>" target="_blank"><code>act=ping</code></a></td></tr>
					<?php } ?>
				</table>
				<p class="help-block" style="margin-top:12px;margin-bottom:0">
					上线资源站：把 <code>allow_remote</code> 设为 <code>true</code>、改强密码，并使用 HTTPS。本地联调保持 <code>false</code> 更安全。
				</p>
			</div>
		</div>

		<div class="panel panel-default">
			<div class="panel-heading"><b>接口速查</b></div>
			<div class="table-responsive">
				<table class="table table-striped table-bordered">
					<thead>
					<tr>
						<th>act</th>
						<th>用途</th>
						<th>鉴权</th>
						<th>商城场景</th>
					</tr>
					</thead>
					<tbody>
					<tr><td><code>ping</code></td><td>协议探测</td><td>否</td><td>检查版本 / 连通</td></tr>
					<tr><td><code>classlist</code></td><td>分类列表</td><td>是</td><td>批量对接</td></tr>
					<tr><td><code>goodslist</code></td><td>商品简表</td><td>是</td><td>价格监控</td></tr>
					<tr><td><code>goodslistbycid</code></td><td>分类商品</td><td>是</td><td>批量拉品</td></tr>
					<tr><td><code>goodsdetails</code></td><td>商品详情</td><td>是</td><td>同步信息</td></tr>
					<tr><td><code>pay</code></td><td>下单发卡</td><td>是</td><td>付款成功后</td></tr>
					<tr><td><code>search</code></td><td>查订单</td><td>是</td><td>订单查询</td></tr>
					</tbody>
				</table>
			</div>
			<div class="panel-body" style="padding-top:0">
				<p class="text-muted">统一：POST <code>user</code>+<code>pass</code>，JSON 响应，成功 <code>code=0</code>。字段细节见 API.md。</p>
				<a class="btn btn-default" href="./shequlist.php">对接站点管理</a>
				<a class="btn btn-default" href="./pricejk.php">价格监控</a>
				<a class="btn btn-default" href="./batchgoods.php">批量对接商品</a>
			</div>
		</div>

		<?php if (is_file($specPath)) { ?>
		<div class="panel panel-default">
			<div class="panel-heading"><b>规范文件位置</b></div>
			<div class="panel-body">
				<ul style="margin-bottom:0">
					<li><code>supplier/API.md</code><?php echo is_file($specPath) ? ' · 已就绪' : ''; ?></li>
					<li><code>supplier/README.md</code><?php echo is_file($readmePath) ? ' · 已就绪' : ''; ?></li>
					<li><code>supplier/config.sample.php</code> · 给外部搭建者复制</li>
					<li><code>includes/plugins/third_daishua.php</code> · 商城客户端插件</li>
				</ul>
			</div>
		</div>
		<?php } ?>
	</div>
</div>
</body>
</html>
