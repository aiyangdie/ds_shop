<?php
/**
 * 我的站点域名：默认用主站二级域名（无需解析）；可选绑定独立域名
 */
include("../includes/common.php");
$title = '我的站点域名';
include './head.php';
if ($islogin2 == 1) {
} else {
	exit("<script language='javascript'>window.location.href='./login.php';</script>");
}
if ($userrow['power'] == 0) {
	showmsg('你没有权限使用此功能！', 3);
}

$mainHost = $_SERVER['HTTP_HOST'];
if (strpos($mainHost, ':') !== false) {
	$mainHost = substr($mainHost, 0, strpos($mainHost, ':'));
}
$subDomain = isset($userrow['domain']) ? trim($userrow['domain']) : '';
$remain = array_filter(array_map('trim', explode(',', isset($conf['fenzhan_remain']) ? $conf['fenzhan_remain'] : '')));
// 独立域名 CNAME 到已经能打开的店铺域名（或主站域名），不展示服务器 IP
$cnameTarget = $subDomain !== '' ? $subDomain : ($remain ? reset($remain) : $mainHost);
$cnameTarget = preg_replace('/:\d+$/', '', $cnameTarget);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$ownDomain = isset($userrow['domain2']) ? trim($userrow['domain2']) : '';
$shopUrl = $subDomain !== '' ? $scheme . '://' . $subDomain . '/' : '';
$ownUrl = $ownDomain !== '' ? $scheme . '://' . $ownDomain . '/' : '';

$msg = '';
$msgType = 'success';

if (isset($_POST['act']) && $_POST['act'] === 'save') {
	if (!checkRefererHost()) exit();
	$domain2 = strtolower(trim(strip_tags($_POST['domain2'])));
	$domain2 = preg_replace('/^https?:\/\//', '', $domain2);
	$domain2 = rtrim($domain2, '/');
	if (strpos($domain2, 'www.') === 0) {
		$domain2 = substr($domain2, 4);
	}
	if ($domain2 === '') {
		$DB->exec("UPDATE pre_site SET domain2=NULL WHERE zid=:zid", [':zid' => $userrow['zid']]);
		$userrow['domain2'] = '';
		$ownDomain = '';
		$ownUrl = '';
		$msg = '已取消独立域名，请继续使用上方主站分配的地址。';
	} elseif (!preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)+$/', $domain2)) {
		$msg = '域名格式不正确，不要加 http:// 和 www';
		$msgType = 'danger';
	} elseif ($domain2 === $subDomain || $domain2 === $mainHost) {
		$msg = '不能填写已在使用的主站域名';
		$msgType = 'danger';
	} elseif (in_array($domain2, explode(',', isset($conf['fenzhan_remain']) ? $conf['fenzhan_remain'] : ''), true)
		|| in_array($domain2, explode(',', isset($conf['fenzhan_domain']) ? $conf['fenzhan_domain'] : ''), true)) {
		$msg = '该域名属于平台保留或分站后缀，不能当作独立域名';
		$msgType = 'danger';
	} elseif ($DB->getRow("SELECT zid FROM pre_site WHERE (domain=:d OR domain2=:d) AND zid!=:zid LIMIT 1", [':d' => $domain2, ':zid' => $userrow['zid']])) {
		$msg = '该域名已被其他分站使用';
		$msgType = 'danger';
	} else {
		$DB->exec("UPDATE pre_site SET domain2=:d WHERE zid=:zid", [':d' => $domain2, ':zid' => $userrow['zid']]);
		$userrow['domain2'] = $domain2;
		$ownDomain = $domain2;
		$ownUrl = $scheme . '://' . $domain2 . '/';
		$msg = '已保存。请把新域名 CNAME 解析到 ' . $cnameTarget . '（你现在的店铺域名），不用填服务器 IP。';
	}
}
$headCss = 'background:linear-gradient(to right,#14b7ff,#b221ff);padding:15px;color:#fff;';
?>
<style>
.dz-copy{cursor:pointer}
.dz-dns td,.dz-dns th{vertical-align:middle!important}
.dz-hint{color:#888;font-size:12px;margin-top:8px}
</style>
<div class="wrapper">
	<div class="col-sm-12">
		<?php if ($msg) { ?>
			<div class="alert alert-<?php echo $msgType ?>"><?php echo htmlspecialchars($msg) ?></div>
		<?php } ?>
	</div>

	<div class="col-md-6 col-sm-12">
		<div class="panel panel-default">
			<div class="panel-heading font-bold text-center" style="<?php echo $headCss ?>">
				<h3 class="panel-title"><font color="#fff"><i class="fa fa-check-circle"></i>&nbsp;&nbsp;<b>直接使用（不用绑定）</b></font></h3>
			</div>
			<ul class="list-group no-radius">
				<li class="list-group-item">
					开通分站时已经分配好二级域名，<b>不用去域名商做解析</b>。复制发给客户即可打开你的店铺。
				</li>
				<li class="list-group-item" style="font-weight:bold">
					店铺地址
					<?php if ($shopUrl) { ?>
						<a href="<?php echo htmlspecialchars($shopUrl) ?>" target="_blank" rel="noreferrer" class="pull-right btn btn-primary btn-xs">打开店铺</a>
					<?php } ?>
				</li>
				<li class="list-group-item">
					<?php if ($shopUrl) { ?>
						<div class="input-group">
							<input type="text" class="form-control" id="shopUrl" value="<?php echo htmlspecialchars($shopUrl) ?>" readonly>
							<span class="input-group-btn">
								<button class="btn btn-info dz-copy" type="button" data-clipboard-target="#shopUrl"><i class="fa fa-copy"></i> 复制</button>
							</span>
						</div>
						<p class="dz-hint" style="margin-bottom:0">解析已经指向本平台，前缀由开通时选定。<?php if ($conf['fenzhan_editd'] > 0) { ?>需要换前缀请点右侧「更换前缀」。<?php } ?></p>
					<?php } else { ?>
						<span class="text-muted">尚未分配二级域名，请联系管理员或重新开通分站。</span>
					<?php } ?>
				</li>
				<?php if ($conf['fenzhan_editd'] > 0 && $shopUrl) { ?>
				<li class="list-group-item">
					<a href="cdomain.php" class="btn btn-default btn-sm btn-block">更换前缀（仍用平台域名，一般要收费）</a>
				</li>
				<?php } ?>
			</ul>
		</div>
	</div>

	<div class="col-md-6 col-sm-12">
		<div class="panel panel-default">
			<div class="panel-heading font-bold text-center" style="<?php echo $headCss ?>">
				<h3 class="panel-title"><font color="#fff"><i class="fa fa-link"></i>&nbsp;&nbsp;<b>可选：用自己的域名</b></font></h3>
			</div>
			<div class="panel-body">
				<p class="text-muted">没有自己的域名就<b>不用填</b>。有域名才保存，并按下表解析。</p>
				<form method="post">
					<input type="hidden" name="act" value="save">
					<div class="form-group">
						<label>独立域名</label>
						<input type="text" name="domain2" class="form-control" placeholder="例如 shop.qq.com ，不要加 http"
							   value="<?php echo htmlspecialchars($ownDomain) ?>">
					</div>
					<button type="submit" class="btn btn-primary btn-block">保存独立域名</button>
				</form>
				<?php if ($ownUrl) { ?>
				<hr>
				<label>独立访问地址</label>
				<div class="input-group" style="margin-bottom:10px">
					<input type="text" class="form-control" id="ownUrl" value="<?php echo htmlspecialchars($ownUrl) ?>" readonly>
					<span class="input-group-btn">
						<button class="btn btn-info dz-copy" type="button" data-clipboard-target="#ownUrl"><i class="fa fa-copy"></i> 复制</button>
					</span>
				</div>
				<?php } ?>
			</div>
		</div>
	</div>

	<div class="col-sm-12">
		<div class="panel panel-default">
			<div class="panel-heading font-bold text-center" style="<?php echo $headCss ?>">
				<h3 class="panel-title"><font color="#fff"><i class="fa fa-globe"></i>&nbsp;&nbsp;<b>有自己的域名时：解析到这个域名</b></font></h3>
			</div>
			<div class="panel-body">
				<p>把你新买的域名 <b>CNAME</b> 到下面这个地址（就是你现在已经能打开的店铺域名）。<b>不用填服务器 IP。</b></p>
				<div class="table-responsive">
					<table class="table table-bordered table-striped dz-dns" style="margin-bottom:10px">
						<thead>
						<tr>
							<th>记录类型</th>
							<th>主机记录</th>
							<th>记录值（解析到）</th>
						</tr>
						</thead>
						<tbody>
						<tr>
							<td><b>CNAME</b></td>
							<td>www　或　@</td>
							<td>
								<code id="cnameVal"><?php echo htmlspecialchars($cnameTarget) ?></code>
								<button type="button" class="btn btn-xs btn-info dz-copy" data-clipboard-target="#cnameVal">复制</button>
							</td>
						</tr>
						</tbody>
					</table>
				</div>
				<ul class="text-muted" style="padding-left:18px;margin-bottom:0">
					<li>例如店铺是 <code><?php echo htmlspecialchars($cnameTarget !== '' ? $cnameTarget : '前缀.平台域名') ?></code>，新域名就填这条记录值。</li>
					<li>根域（example.com）若不能 CNAME，只解析 www 也可以。</li>
					<li>生效后用新域名访问即进本分站。清空独立域名并保存可取消。</li>
				</ul>
			</div>
		</div>
		<p class="text-center">
			<a href="./" class="btn btn-default">返回首页</a>
			<a href="uset.php?mod=site" class="btn btn-default">网站信息设置</a>
		</p>
	</div>
</div>
<?php include './foot.php'; ?>
<script src="<?php echo $cdnpublic?>clipboard.js/1.7.1/clipboard.min.js"></script>
<script>
$(function () {
	var clip = new Clipboard('.dz-copy');
	clip.on('success', function () { layer.msg('复制成功', {icon: 1}); });
	clip.on('error', function () { layer.msg('复制失败，请长按后手动复制', {icon: 2}); });
});
</script>
</body>
</html>
