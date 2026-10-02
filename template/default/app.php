<?php
/**
 * APP 下载页：生成中 / 成功 / 失败 三种状态
 */
if (!defined('IN_CRONLITE')) exit();

if (strpos($_SERVER['HTTP_USER_AGENT'], 'QQ/') !== false || strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
    header('Content-type:text/html;charset=utf-8');
    include ROOT . 'template/default/jump.php';
    exit;
}

$id = intval($_GET['id']);
$row = $DB->getRow("SELECT * FROM pre_apps WHERE id='$id' LIMIT 1");
if (!$row) {
    exit("<script language='javascript'>alert('当前APP不存在！');history.go(-1);</script>");
}

$bs = intval(isset($row['build_status']) ? $row['build_status'] : -1);
$status = intval($row['status']);
$isLocal = class_exists('\\lib\\AppFactory') && \lib\AppFactory::isLocalMode($conf);
$progress = intval(isset($row['progress']) ? $row['progress'] : 0);
$addtime = isset($row['addtime']) ? $row['addtime'] : '';
$uptime = isset($row['updatetime']) ? $row['updatetime'] : $addtime;
$duration = 0;
if ($addtime && $uptime) {
    $duration = max(0, strtotime($uptime) - strtotime($addtime));
}
$fileSize = isset($row['file_size']) ? intval($row['file_size']) : 0;
$storage = isset($row['storage_driver']) ? $row['storage_driver'] : '';
$icon = !empty($row['icon']) ? $row['icon'] : './assets/uploads/apps/default_icon.png';
$name = htmlspecialchars($row['name']);
$errorMsg = !empty($row['error']) ? $row['error'] : '';

// remote 兼容：status=2 尝试拉第三方结果
if ($status === 2 && !$isLocal && intval($row['taskid']) > 0 && !empty($conf['appcreate_key'])) {
    $app = new \lib\AppCreate($conf['appcreate_key']);
    $res = $app->querytask($row['taskid']);
    if ($res && is_array($res) && $res['status'] == 1) {
        $android_url = $res['lanzou_url'] ? $res['lanzou_url'] : $res['android_url'];
        $ios_url = $res['ios_url'];
        $DB->update('apps', array(
            'taskid' => $res['id'],
            'name' => $res['name'],
            'package' => $res['package'],
            'android_url' => $android_url,
            'ios_url' => $ios_url,
            'icon' => $res['icon'],
            'addtime' => $res['created_at'],
            'status' => 1,
            'build_status' => 2,
            'progress' => 100,
            'updatetime' => date('Y-m-d H:i:s'),
        ), array('id' => $row['id']));
        $row = $DB->getRow("SELECT * FROM pre_apps WHERE id='$id' LIMIT 1");
        $status = 1;
        $bs = 2;
    }
}

function app_fmt_size($n)
{
    $n = intval($n);
    if ($n <= 0) return '';
    if ($n < 1024) return $n . ' B';
    if ($n < 1048576) return round($n / 1024, 1) . ' KB';
    return round($n / 1048576, 2) . ' MB';
}

function app_fmt_duration($sec)
{
    $sec = intval($sec);
    if ($sec <= 0) return '';
    if ($sec < 60) return $sec . ' 秒';
    return floor($sec / 60) . ' 分 ' . ($sec % 60) . ' 秒';
}

$pageState = 'ready';
if ($isLocal) {
    if ($bs === 3 || ($status === 0 && $errorMsg !== '' && $bs !== 2)) $pageState = 'failed';
    elseif ($status === 2 || $bs === 0 || $bs === 1) $pageState = 'building';
    elseif ($status === 0 && $bs === 2) $pageState = 'offline';
    elseif ($status === 1 && !empty($row['android_url'])) $pageState = 'ready';
    elseif ($status === 0) $pageState = 'offline';
    else $pageState = 'failed';
} else {
    if ($status === 0) $pageState = 'offline';
    elseif ($status === 2) $pageState = 'building';
    else $pageState = 'ready';
}

$download_url = !empty($row['android_url']) ? $row['android_url'] : '';
$ios_url = !empty($row['ios_url']) ? $row['ios_url'] : '';
$isIos = (strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone') !== false || strpos($_SERVER['HTTP_USER_AGENT'], 'iPad') !== false);
if ($isIos && $ios_url !== '' && $pageState === 'ready') {
    $download_url = $ios_url;
}

$siteShow = isset($siteurl) ? $siteurl : '';
$selfUrl = (is_https() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=0">
    <title><?php echo $name; ?> - <?php echo $pageState === 'building' ? '生成中' : ($pageState === 'failed' ? '生成失败' : ($pageState === 'offline' ? '已下架' : '下载APP')); ?></title>
    <style>
        :root{--bg:#070b17;--surface:rgba(16,24,45,.82);--line:rgba(255,255,255,.09);--text:#f8fafc;--muted:#94a3b8;--accent:#6d5dfc;--accent2:#20c7f5;--ok:#34d399;--warn:#fbbf24;--bad:#fb7185}
        *{box-sizing:border-box}html{min-height:100%;background:var(--bg)}
        body{margin:0;min-height:100vh;overflow-x:hidden;font-family:Inter,ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;color:var(--text);background:radial-gradient(circle at 12% 15%,rgba(109,93,252,.25),transparent 32%),radial-gradient(circle at 88% 12%,rgba(32,199,245,.18),transparent 28%),linear-gradient(145deg,#080c18,#0b1021 48%,#070b17)}
        body:before,body:after{content:"";position:fixed;z-index:0;border-radius:50%;pointer-events:none}body:before{width:380px;height:380px;left:-170px;bottom:-160px;background:rgba(77,70,229,.12)}body:after{width:320px;height:320px;right:-150px;top:20%;background:rgba(14,165,233,.1)}
        .page{position:relative;z-index:1;min-height:100vh;display:flex;flex-direction:column}.topbar{width:min(1120px,calc(100% - 40px));margin:0 auto;padding:28px 0 10px;display:flex;align-items:center;justify-content:space-between}.brand{display:flex;align-items:center;gap:10px;font-size:13px;font-weight:750;letter-spacing:.08em}.brand-mark{width:28px;height:28px;border-radius:9px;background:linear-gradient(135deg,var(--accent),var(--accent2));box-shadow:0 8px 22px rgba(78,110,255,.35);position:relative}.brand-mark:after{content:"";position:absolute;inset:7px;border:2px solid rgba(255,255,255,.88);border-radius:50%}.secure{display:flex;align-items:center;gap:7px;color:var(--muted);font-size:12px}.secure svg{width:15px;color:var(--ok)}
        .wrap{width:min(1120px,calc(100% - 40px));margin:auto;padding:34px 0 64px}.card{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1.05fr) minmax(360px,.75fr);background:linear-gradient(145deg,rgba(20,29,53,.9),rgba(11,17,33,.86));border:1px solid var(--line);border-radius:32px;box-shadow:0 38px 110px rgba(0,0,0,.48),inset 0 1px 0 rgba(255,255,255,.04);backdrop-filter:blur(22px)}.card:before{content:"";position:absolute;width:420px;height:420px;left:-170px;top:-210px;border-radius:50%;background:radial-gradient(circle,rgba(109,93,252,.28),transparent 68%)}
        .hero{position:relative;padding:68px 64px 56px;display:flex;flex-direction:column;justify-content:center;min-height:620px}.eyebrow{display:flex;align-items:center;gap:10px;margin-bottom:26px;color:#a5b4fc;font-size:11px;font-weight:750;letter-spacing:.16em;text-transform:uppercase}.eyebrow:before{content:"";width:28px;height:1px;background:linear-gradient(90deg,var(--accent2),transparent)}.app-head{display:flex;align-items:center;gap:22px}.icon{width:92px;height:92px;flex:0 0 92px;border-radius:25px;display:block;object-fit:cover;background:#fff;border:1px solid rgba(255,255,255,.16);box-shadow:0 18px 45px rgba(0,0,0,.3),0 0 0 7px rgba(255,255,255,.035)}h1{margin:0 0 10px;font-size:clamp(30px,4vw,48px);line-height:1.08;letter-spacing:-.04em}.subtitle{margin:28px 0 0;max-width:500px;color:#aebbd0;font-size:16px;line-height:1.8}.sub{color:var(--muted);font-size:12px}.badge{display:inline-flex;align-items:center;gap:7px;padding:6px 11px;border:1px solid transparent;border-radius:999px;font-size:12px;font-weight:700}.badge:before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor;box-shadow:0 0 12px currentColor}.badge.ok{background:rgba(52,211,153,.1);border-color:rgba(52,211,153,.18);color:var(--ok)}.badge.wait{background:rgba(251,191,36,.1);border-color:rgba(251,191,36,.18);color:var(--warn)}.badge.bad{background:rgba(251,113,133,.1);border-color:rgba(251,113,133,.18);color:var(--bad)}
        .features{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:42px}.feature{padding:16px 15px;border:1px solid var(--line);border-radius:16px;background:rgba(255,255,255,.025)}.feature svg{width:20px;height:20px;margin-bottom:12px;color:#8b9cff}.feature strong{display:block;color:#edf2ff;font-size:13px;margin-bottom:4px}.feature span{color:#7787a2;font-size:11px;line-height:1.5}.meta{margin-top:34px;border-top:1px solid var(--line);padding-top:18px}.meta-row{display:flex;justify-content:space-between;gap:12px;padding:7px 0;font-size:12px}.meta-row span:first-child{color:var(--muted);flex-shrink:0}.meta-row span:last-child{color:#cbd5e1;text-align:right;word-break:break-all}
        .download-panel{margin:18px;padding:42px 38px;border:1px solid rgba(255,255,255,.08);border-radius:24px;background:linear-gradient(160deg,rgba(25,36,64,.92),rgba(12,19,36,.96));display:flex;flex-direction:column;justify-content:center;box-shadow:inset 0 1px 0 rgba(255,255,255,.04)}.panel-label{margin:0 0 8px;color:#8c9bb3;font-size:11px;font-weight:700;letter-spacing:.13em}.panel-title{margin:0;font-size:24px;letter-spacing:-.03em}.panel-desc{margin:10px 0 0;color:var(--muted);font-size:13px;line-height:1.7}.bar{height:8px;background:rgba(255,255,255,.08);border-radius:999px;overflow:hidden;margin:24px 0 10px}.bar>i{display:block;height:100%;width:<?php echo max(2, min(100, $progress)); ?>%;background:linear-gradient(90deg,var(--accent),var(--accent2));border-radius:999px}
        .btn{display:flex;align-items:center;justify-content:center;gap:9px;width:100%;text-align:center;text-decoration:none;background:linear-gradient(110deg,#6758f5,#437af7 55%,#20bde8);color:#fff;border:0;border-radius:14px;padding:15px 18px;font-size:15px;font-weight:750;margin-top:22px;box-shadow:0 16px 34px rgba(73,94,238,.33);transition:.2s ease}.btn:hover{transform:translateY(-2px);filter:brightness(1.08);box-shadow:0 20px 42px rgba(73,94,238,.42)}.btn:focus-visible{outline:3px solid rgba(99,198,255,.5);outline-offset:3px}.btn svg{width:18px;height:18px}.btn.ghost{background:rgba(255,255,255,.06);color:#dbeafe;box-shadow:none;border:1px solid var(--line)}.tips{margin-top:22px;padding:15px 16px;background:rgba(255,255,255,.035);border:1px solid var(--line);border-radius:14px;font-size:12px;color:#8fa0ba;line-height:1.7}.tips b{display:block;color:#d8e3f7;margin-bottom:5px}.qr-wrap{display:flex;align-items:center;gap:16px;margin-top:26px;padding-top:24px;border-top:1px solid var(--line)}.qr{width:116px;height:116px;flex:0 0 116px;padding:8px;border-radius:14px;background:#fff}.qr canvas,.qr img{display:block;width:100px!important;height:100px!important;border-radius:5px}.qr-copy strong{display:block;color:#e7efff;font-size:13px;margin-bottom:6px}.qr-copy span{color:#7f8ea7;font-size:11px;line-height:1.6}.err{color:var(--bad);font-size:13px;line-height:1.7;margin:22px 0 0}.footer{padding:0 20px 28px;color:#56657e;font-size:11px;text-align:center}
        @media(max-width:820px){.topbar{width:min(100% - 28px,620px);padding-top:18px}.wrap{width:min(100% - 24px,620px);padding:18px 0 40px}.card{grid-template-columns:1fr;border-radius:26px}.hero{min-height:0;padding:38px 28px 26px}.download-panel{margin:0 12px 12px;padding:30px 26px}.features{margin-top:30px}}
        @media(max-width:520px){.secure span{display:none}.hero{padding:32px 22px 22px}.app-head{gap:17px}.icon{width:76px;height:76px;flex-basis:76px;border-radius:21px}h1{font-size:28px}.subtitle{margin-top:22px;font-size:14px}.features{grid-template-columns:1fr;gap:8px}.feature{display:grid;grid-template-columns:24px 1fr;column-gap:10px;padding:12px 13px}.feature svg{grid-row:1/3;margin:2px 0 0}.download-panel{padding:26px 20px}.qr-wrap{justify-content:center}}
        @media(prefers-reduced-motion:reduce){*,*:before,*:after{transition:none!important;animation:none!important}}
    </style>
</head>
<body>
<div class="page">
    <header class="topbar">
        <div class="brand"><span class="brand-mark"></span><span>OFFICIAL DOWNLOAD</span></div>
        <div class="secure"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg><span>官方安全下载</span></div>
    </header>
    <main class="wrap">
        <div class="card">
            <section class="hero">
                <div class="eyebrow">Your store, always with you</div>
                <div class="app-head">
                    <img class="icon" src="<?php echo htmlspecialchars($icon); ?>" alt="<?php echo $name; ?> 应用图标">
                    <div>
                        <h1><?php echo $name; ?></h1>
                        <?php if ($pageState === 'building') { ?>
                            <span class="badge wait">正在生成 <?php echo $progress; ?>%</span>
                        <?php } elseif ($pageState === 'failed') { ?>
                            <span class="badge bad">生成失败</span>
                        <?php } elseif ($pageState === 'offline') { ?>
                            <span class="badge wait">已下架</span>
                        <?php } else { ?>
                            <span class="badge ok">可下载安装</span>
                        <?php } ?>
                    </div>
                </div>
                <p class="subtitle">专属移动客户端，为你提供更快捷、更沉浸的访问体验。无需复杂配置，下载安装后即可进入站点。</p>
                <div class="features">
                    <div class="feature"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8Z"/></svg><strong>即开即用</strong><span>轻量流畅，快速进入</span></div>
                    <div class="feature"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="2" width="14" height="20" rx="3"/><path d="M9 18h6"/></svg><strong>专属体验</strong><span>适配移动端操作习惯</span></div>
                    <div class="feature"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg><strong>安全可靠</strong><span>官方构建，本站直供</span></div>
                </div>
                <div class="meta">
                    <?php if (!empty($row['package'])) { ?><div class="meta-row"><span>应用标识</span><span><?php echo htmlspecialchars($row['package']); ?></span></div><?php } ?>
                    <?php if ($pageState === 'ready' && $fileSize > 0) { ?><div class="meta-row"><span>安装包大小</span><span><?php echo app_fmt_size($fileSize); ?></span></div><?php } ?>
                    <?php if ($pageState === 'ready' && $storage !== '') { ?><div class="meta-row"><span>文件来源</span><span><?php echo $storage === 'local' ? '本站安全存储' : '云端加速存储'; ?></span></div><?php } ?>
                    <?php if ($pageState === 'ready' && $uptime) { ?><div class="meta-row"><span>最近构建</span><span><?php echo htmlspecialchars($uptime); ?></span></div><?php } ?>
                </div>
            </section>

            <section class="download-panel">
                <p class="panel-label">ANDROID APPLICATION</p>
                <h2 class="panel-title"><?php echo $pageState === 'ready' ? '获取 Android 客户端' : ($pageState === 'building' ? '正在为你构建' : ($pageState === 'offline' ? '应用暂时下架' : '构建未完成')); ?></h2>
                <p class="panel-desc"><?php echo $pageState === 'ready' ? '建议使用手机浏览器扫码访问，或直接下载安装包。' : ($pageState === 'building' ? '通常只需 1～3 分钟，本页将自动更新最新进度。' : '请联系站点管理员获取更多信息。'); ?></p>

                <?php if ($pageState === 'building') { ?>
                    <div class="bar"><i></i></div><p class="sub">当前进度 <?php echo $progress; ?>%，本页每 5 秒自动刷新</p>
                <?php } ?>
                <?php if ($pageState === 'failed') { ?><p class="err"><?php echo htmlspecialchars($errorMsg !== '' ? $errorMsg : '生成失败，请返回后台重试'); ?></p><?php } ?>
                <?php if ($pageState === 'offline') { ?><p class="err">该 APP 已下架，暂不可下载。如有需要请联系站点管理员。</p><?php } ?>

                <?php if ($pageState === 'ready' && $download_url !== '') { ?>
                    <a class="btn" id="dlBtn" href="<?php echo htmlspecialchars($download_url); ?>" aria-label="下载 <?php echo $name; ?> Android 安装包"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>立即下载 Android 版</a>
                    <div class="qr-wrap"><div class="qr" id="qrcode"></div><div class="qr-copy"><strong>手机扫码下载</strong><span>使用浏览器扫描二维码<br>即可在手机上打开本页</span></div></div>
                    <div class="tips"><b>安装提示</b>微信内请点击右上角并选择“在浏览器打开”。首次安装时，按系统提示允许浏览器安装未知应用即可。</div>
                <?php } elseif ($pageState === 'building') { ?>
                    <a class="btn ghost" href="javascript:location.reload()">刷新构建进度</a>
                <?php } else { ?>
                    <a class="btn ghost" href="javascript:history.go(-1)">返回上一页</a>
                <?php } ?>
            </section>
        </div>
    </main>
    <footer class="footer">© <?php echo date('Y'); ?> <?php echo $name; ?> · Official Application Distribution</footer>
</div>
<?php if ($pageState === 'building') { ?>
<script>setTimeout(function(){ location.reload(); }, 5000);</script>
<?php } ?>
<?php if ($pageState === 'ready' && $download_url !== '') { ?>
<script src="<?php echo $cdnpublic; ?>jquery/1.12.4/jquery.min.js"></script>
<script src="<?php echo $cdnpublic; ?>jquery.qrcode/1.0/jquery.qrcode.min.js"></script>
<script>
(function () {
    var pageUrl = <?php echo json_encode($selfUrl, JSON_UNESCAPED_UNICODE); ?>;
    $('#qrcode').qrcode({ width: 100, height: 100, text: pageUrl });
})();
</script>
<?php } ?>
</body>
</html>
