<?php
/**
 * 分站域名说明（主站管理员）
 */
include("../includes/common.php");
$title = '分站域名说明';
include './head.php';
if ($islogin != 1) exit("<script>window.location.href='./login.php';</script>");

$domains = isset($conf['fenzhan_domain']) ? trim($conf['fenzhan_domain']) : '';
$remain = isset($conf['fenzhan_remain']) ? trim($conf['fenzhan_remain']) : '';
$buy = !empty($conf['fenzhan_buy']);
$cnameShow = $remain !== '' ? explode(',', $remain)[0] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
?>
<div class="col-xs-12 col-sm-10 col-lg-10 center-block" style="float:none;">
	<div class="block">
		<div class="block-title"><h3 class="panel-title"><i class="fa fa-globe"></i>&nbsp;分站域名说明</h3></div>

		<div class="alert alert-success">
			<b>分站用户默认什么都不用做。</b>开通时选的 <code>前缀.你的域名</code> 已经可用，复制推广即可。
			只有他们要「用自己买的域名」时，才需要 CNAME 到 <code><?php echo htmlspecialchars($cnameShow) ?></code>。
		</div>

		<div class="row">
			<div class="col-md-6">
				<div class="panel panel-primary">
					<div class="panel-heading font-bold"><i class="fa fa-magic"></i> 用平台域名（默认）</div>
					<div class="panel-body">
						<p>你作为主站要一次性配好，之后每个分站自动生效：</p>
						<ol>
							<li>DNS 泛解析：主机 <code>*</code>，A 记录 → 服务器 IP</li>
							<li>主域 <code>@</code> / <code>www</code> 也解析到同一台</li>
							<li>后台填写「可选分站域名」「保留域名」，并开启自助开通</li>
							<li>Nginx 用同一站点接 <code>主域</code> 和 <code>*.主域</code></li>
						</ol>
						<table class="table table-bordered table-condensed" style="margin-bottom:0">
							<tr><th width="120">自助开通</th><td><?php echo $buy ? '<font color="green">已开</font>' : '<font color="red">未开</font>'; ?>　<a href="./set.php?mod=fenzhan">去配置</a></td></tr>
							<tr><th>可选分站域名</th><td><code><?php echo $domains !== '' ? htmlspecialchars($domains) : '未填'; ?></code></td></tr>
							<tr><th>保留域名</th><td><code><?php echo $remain !== '' ? htmlspecialchars($remain) : '未填'; ?></code></td></tr>
						</table>
					</div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="panel panel-info">
					<div class="panel-heading font-bold"><i class="fa fa-link"></i> 用他们自己的域名（可选）</div>
					<div class="panel-body">
						<p>分站用户把新域名 <b>CNAME 到平台已有域名</b>（他们的二级域名或主站域名），<b>不要让他们填服务器 IP</b>。</p>
						<table class="table table-bordered table-condensed">
							<tr><th>类型</th><td>CNAME</td></tr>
							<tr><th>主机记录</th><td>www 或 @</td></tr>
							<tr><th>记录值</th><td><code><?php echo htmlspecialchars($cnameShow) ?></code></td></tr>
						</table>
						<p class="text-muted" style="margin-bottom:0">程序用访问的 Host 匹配分站 <code>domain</code> / <code>domain2</code>。HTTPS 用 CDN 或给该域名签证书。</p>
					</div>
				</div>
			</div>
		</div>

		<div class="panel panel-default">
			<div class="panel-heading font-bold">Nginx 示例</div>
			<div class="panel-body">
<pre style="margin-bottom:8px;">server {
    listen 80;
    server_name shop.com *.shop.com;
    root /www/wwwroot/ds_shop;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}</pre>
				<p class="help-block" style="margin-bottom:0">独立域名再加一条 server，root 相同。本机 PHP 内置 8080 无法泛解析，公网请用 Nginx/Apache。</p>
			</div>
		</div>
		<a class="btn btn-primary" href="./set.php?mod=fenzhan">分站相关配置</a>
		<a class="btn btn-default" href="./sitelist.php">分站列表</a>
	</div>
</div>
</body>
</html>
