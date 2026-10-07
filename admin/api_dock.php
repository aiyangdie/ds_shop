<?php
/**
 * 货源 API 对接管理：测试连通、拉取商品、查看调用日志
 */
include("../includes/common.php");
$title = '货源API对接';
include './head.php';
if ($islogin != 1) exit("<script>window.location.href='./login.php';</script>");

$shequ = $DB->getRow("SELECT * FROM pre_shequ WHERE type='daishua' ORDER BY id ASC LIMIT 1");
$tools = $DB->query("SELECT tid,name,price,shequ,goods_id,is_curl,active,close FROM pre_tools WHERE is_curl=2 ORDER BY tid ASC")->fetchAll();
$logFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'supplier' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'api.log';
$logTail = is_file($logFile) ? implode('', array_slice(file($logFile), -30)) : '';

$msg = '';
$msgType = 'info';
$pullList = null;

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    if (!$shequ) {
        $msg = '请先导入对接配置（install/_seed_api_dock.sql）或在对接站点管理中添加同系统对接';
        $msgType = 'danger';
    } else {
        $plugin = new \plugins\third_daishua($shequ);

        if ($action === 'test_goods') {
            $list = $plugin->goods_list();
            if (is_array($list)) {
                $msg = '连通成功，货源返回 ' . count($list) . ' 个商品';
                $msgType = 'success';
                $pullList = $list;
            } else {
                $msg = '拉取失败：' . (is_string($list) ? $list : json_encode($list, JSON_UNESCAPED_UNICODE));
                $msgType = 'danger';
            }
        } elseif ($action === 'test_pay') {
            $gid = intval($_POST['goods_id'] ?: 1001);
            $ret = $plugin->do_goods($gid, 0, null, 1, ['test@example.com'], 0, 'TEST' . time(), '');
            if (isset($ret['code']) && (int)$ret['code'] === 0) {
                $msg = '下单测试成功，货源订单号：' . $ret['id'] . (isset($ret['kmdata']) ? '；卡密：' . (is_array($ret['kmdata']) ? json_encode($ret['kmdata'], JSON_UNESCAPED_UNICODE) : $ret['kmdata']) : '');
                $msgType = 'success';
            } else {
                $msg = '下单测试失败：' . (isset($ret['message']) ? $ret['message'] : json_encode($ret, JSON_UNESCAPED_UNICODE));
                $msgType = 'danger';
            }
            if (is_file($logFile)) $logTail = implode('', array_slice(file($logFile), -30));
        } elseif ($action === 'clear_log') {
            @file_put_contents($logFile, '');
            $logTail = '';
            $msg = '日志已清空';
            $msgType = 'success';
        }
    }
}
?>
<div class="col-xs-12 col-sm-10 col-lg-10 center-block" style="float: none;">
    <div class="block">
        <div class="block-title"><h3><i class="fa fa-plug"></i>&nbsp;货源 API 对接</h3></div>

        <?php if ($msg) { ?>
            <div class="alert alert-<?php echo $msgType ?>"><?php echo htmlspecialchars($msg) ?></div>
        <?php } ?>

        <div class="alert alert-info" style="margin-bottom:15px">
            协议规范与自建资源站说明已单独分类：
            <a href="./supplier_api.php"><b>资源站协议</b></a>
            （完整文档 <code>supplier/API.md</code>）
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><b>当前对接站点</b></div>
            <div class="panel-body">
                <?php if ($shequ) { ?>
                    <p>ID：<?php echo (int)$shequ['id'] ?> ｜ 类型：<?php echo htmlspecialchars($shequ['type']) ?> ｜ 状态：<?php echo $shequ['status'] ? '启用' : '停用' ?></p>
                    <p>URL：<code>http<?php echo $shequ['protocol'] == 1 ? 's' : '' ?>://<?php echo htmlspecialchars($shequ['url']) ?></code></p>
                    <p>账号：<?php echo htmlspecialchars($shequ['username']) ?> ｜ 备注：<?php echo htmlspecialchars($shequ['remark']) ?></p>
                    <p class="text-muted">实际请求形如：<code>/api.php?act=pay</code>（同系统协议）</p>
                    <a class="btn btn-default btn-sm" href="./shequlist.php">去对接站点管理修改</a>
                <?php } else { ?>
                    <p class="text-danger">尚未配置同系统对接站点。请执行 <code>install/_seed_api_dock.sql</code> 或手动添加。</p>
                <?php } ?>
            </div>
        </div>

        <div class="panel panel-info">
            <div class="panel-heading"><b>连通性测试</b></div>
            <div class="panel-body">
                <form method="post" class="form-inline" style="margin-bottom:10px">
                    <input type="hidden" name="action" value="test_goods">
                    <button type="submit" class="btn btn-info">拉取货源商品列表</button>
                </form>
                <form method="post" class="form-inline">
                    <input type="hidden" name="action" value="test_pay">
                    <label>测试商品ID</label>
                    <input type="number" name="goods_id" value="1001" class="form-control" style="width:100px">
                    <button type="submit" class="btn btn-success">真实 HTTP 下单测试</button>
                </form>
                <p class="help-block" style="margin-top:10px">
                    下单会真实调用资源站服务引擎并交付结果（如体检报告/兑换码）。建议 tid：
                    <code>1001</code> 体检、<code>1002</code> 文本、<code>1004</code> 兑换码；
                    <code>1003</code> AI文案需已配置主站或资源站 AI Key。
                </p>
            </div>
        </div>

        <?php if (is_array($pullList)) { ?>
            <div class="panel panel-success">
                <div class="panel-heading"><b>货源商品</b></div>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                        <tr>
                            <th>货源ID</th>
                            <th>分类</th>
                            <th>名称</th>
                            <th>价格</th>
                            <th>库存</th>
                            <th>关闭</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pullList as $g) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($g['id']) ?></td>
                                <td><?php echo htmlspecialchars(isset($g['cid']) ? $g['cid'] : '-') ?></td>
                                <td><?php echo htmlspecialchars($g['name']) ?></td>
                                <td><?php echo htmlspecialchars($g['price']) ?></td>
                                <td><?php echo htmlspecialchars(isset($g['stock']) && $g['stock'] !== null ? $g['stock'] : '-') ?></td>
                                <td><?php echo !empty($g['close']) ? '是' : '否' ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>

        <div class="panel panel-warning">
            <div class="panel-heading"><b>本站已对接商品（is_curl=2）</b></div>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                    <tr>
                        <th>本站tid</th>
                        <th>名称</th>
                        <th>售价</th>
                        <th>货源站</th>
                        <th>货源商品</th>
                        <th>状态</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$tools) { ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">暂无对接商品</td>
                        </tr>
                    <?php } else {
                        foreach ($tools as $t) { ?>
                            <tr>
                                <td><?php echo (int)$t['tid'] ?></td>
                                <td><?php echo htmlspecialchars($t['name']) ?></td>
                                <td><?php echo htmlspecialchars($t['price']) ?></td>
                                <td><?php echo (int)$t['shequ'] ?></td>
                                <td><?php echo (int)$t['goods_id'] ?></td>
                                <td><?php echo $t['active'] ? ($t['close'] ? '维护' : '上架') : '下架' ?></td>
                            </tr>
                        <?php }
                    } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <b>货源 API 调用日志</b>
                <form method="post" style="display:inline;float:right">
                    <input type="hidden" name="action" value="clear_log">
                    <button type="submit" class="btn btn-xs btn-default">清空</button>
                </form>
            </div>
            <div class="panel-body">
                <pre style="max-height:280px;overflow:auto;background:#1e1e1e;color:#d4d4d4;padding:12px;"><?php echo $logTail ? htmlspecialchars($logTail) : '（暂无日志，完成一次下单测试后会出现）' ?></pre>
            </div>
        </div>
    </div>
</div>
</body>
</html>
