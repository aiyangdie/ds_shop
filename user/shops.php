<?php
/**
 * 批量下单
 **/
include("../includes/common.php");
$title = '批量下单';
include './head.php';
if ($islogin2 == 1) {
} else exit("<script language='javascript'>window.location.href='./login.php';</script>");

$usershop            = true;
$addsalt             = md5(mt_rand(0, 999) . time());
$_SESSION['addsalt'] = $addsalt;
$x                   = new \lib\hieroglyphy();
$addsalt_js          = $x->hieroglyphyString($addsalt);

$rs           = $DB->query("SELECT * FROM pre_class WHERE active=1 order by sort asc");
$select       = '<option value="0">请选择分类</option>';
$select_count = 0;
while ($res = $rs->fetch()) {
    if ($is_fenzhan && in_array($res['cid'], $classhide)) continue;
    $select_count++;
    $select .= '<option value="' . $res['cid'] . '">' . $res['name'] . '</option>';
}
if ($select_count == 0) $hideclass = true;
?>
<style>
    img.logo {
        width: 22px;
        margin: -2px 5px 0 5px;
    }

    .onclick {
        cursor: pointer;
        touch-action: manipulation;
    }

    #alert_frame img {
        max-width: 100%
    }
</style>
<div class="wrapper">
    <div class="col-xs-12 center-block" style="float: none;">
        <div class="panel panel">
            <div class="panel-heading font-bold"
                 style="background: linear-gradient(to right,#14b7ff,#b221ff);color: white;">
                批量下单
                <span class="pull-right" style="<?php echo !$islogin2 ? 'display:none' : null; ?>">
					余额：<?php echo $userrow['rmb'] ?>元
				</span>
            </div>
            <div class="panel-body">
                <div id="goodTypeContents">
                    <?php echo $conf['alert'] ?>
                    <?php if ($conf['search_open'] == 1) { ?>
                        <div class="form-group" id="display_searchBar">
                            <div class="input-group">
                                <div class="input-group-addon">搜索商品</div>
                                <input type="text" id="searchkw" class="form-control" placeholder="搜索商品"
                                       onkeydown="if(event.keyCode==13){$('#doSearch').click()}"/>
                                <div class="input-group-addon"><span class="glyphicon glyphicon-search onclick"
                                                                     title="搜索" id="doSearch"></span></div>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="form-group"
                         id="display_selectclass"<?php if ($hideclass) { ?> style="display:none;"<?php } ?>>
                        <div class="input-group">
                            <div class="input-group-addon">选择分类</div>
                            <select name="tid" id="cid" class="form-control"><?php echo $select ?></select>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-addon">选择商品</div>
                            <select name="tid" id="tid" class="form-control" onchange="getPoint();">
                                <option value="0">请选择商品</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="display_price"
                         style="display:none;center;color:#4169E1;font-weight:bold">
                        <div class="input-group">
                            <div class="input-group-addon">商品价格</div>
                            <input type="text" name="need" id="need" class="form-control"
                                   style="center;color:#4169E1;font-weight:bold" disabled/>
                        </div>
                    </div>
                    <div class="form-group" id="display_left" style="display:none;">
                        <div class="input-group">
                            <div class="input-group-addon">库存数量</div>
                            <input type="text" name="leftcount" id="leftcount" class="form-control" disabled/>
                        </div>
                    </div>
                    <div class="form-group" id="display_num" style="display:none;">
                        <div class="input-group">
                            <div class="input-group-addon">下单份数</div>
                            <span class="input-group-btn"><input id="num_min" type="button" class="btn btn-info"
                                                                 style="border-radius: 0px;" value="━"></span>
                            <input id="num" name="num" class="form-control" type="number" min="1" value="1"/>
                            <span class="input-group-btn"><input id="num_add" type="button" class="btn btn-info"
                                                                 style="border-radius: 0px;" value="✚"></span>
                        </div>
                    </div>
                    <div id="alert_frame" class="alert alert-success animated rubberBand"
                         style="display:none;background: linear-gradient(to right,#71D7A2,#5ED1D7);font-weight: bold;color:white;"></div>

                    <div class="form-group">
                        <label>下单账号：</label><br/>
                        <font color="green">请输入【<span id="inputsname" style="color:red">下单账号</span>】，多个用回车换行，如果有多个参数用|隔开</font><br/>
                        <textarea type="text" name="inputvalues" id="inputvalues" class="form-control" required
                                  placeholder="" rows="10"></textarea>
                    </div>
                    <div class="form-group">
                        <input type="submit" id="submit_buy" class="btn btn-primary btn-block" value="立即购买">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" align="left" id="batch-pay-modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
         aria-hidden="true" data-backdrop="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel">大批量下单 - 结果响应 <span class="text-danger">[下单过程中，请勿关闭该页面]</span>
                    </h5>
                </div>
                <div class="modal-body" style="height:500px; overflow:auto">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th>序号</th>
                                <th>下单信息</th>
                            </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default btn-sm" id="close" data-dismiss="modal" disabled>关闭
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="all-show">查看全部</button>
                    <button type="button" class="btn btn-success btn-sm" id="succeed-show">成功: <span
                                id="succeed-total">0</span> 条
                    </button>
                    <button type="button" class="btn btn-danger  btn-sm" id="failed-show">失败：<span
                                id="failed-total">0</span>条
                        <button type="button" class="btn btn-warning btn-sm" id="failed-result" style="display: none" data-toggle="modal"
                                data-target="#failed-result-modal">失败结果
                        </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" align="left" id="failed-result-modal" tabindex="-1" role="dialog"
         aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel">失败条目结果集</h5>
                </div>
                <div class="modal-body" style="height:500px; overflow:auto">
                    <textarea class="form-control" id="failed-text" cols="30" rows="10"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">关闭</button>
                    <button type="button" class="btn btn-primary" id="copy-btn" data-clipboard-target="#failed-text">复制
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include './foot.php'; ?>
<script src="<?php echo $cdnpublic ?>jquery.lazyload/1.9.1/jquery.lazyload.min.js"></script>
<script src="<?php echo $cdnpublic ?>jquery-cookie/1.4.1/jquery.cookie.min.js"></script>
<script src="<?php echo $cdnpublic ?>clipboard.js/1.7.1/clipboard.min.js"></script>
<script type="text/javascript">
    var isModal = false;
    var homepage = false;
    var islogin =<?php echo $islogin2 ? '1' : '0'?>;
    var hashsalt =<?php echo $addsalt_js?>;
    $(function () {
        $("img.lazy").lazyload({effect: "fadeIn"});
    });
    $(document).ready(function () {
        var clipboard = new Clipboard('#copy-btn');
        clipboard.on('success', function (e) {
            layer.msg('复制成功！', {time: 1000, icon: 1});
        });
        clipboard.on('error', function (e) {
            layer.msg('复制失败！建议更换其他最新版浏览器！', {time: 2000, icon: 2});
        });
    })
</script>
<script src="../assets/js/usershops.js?ver=<?php echo VERSION ?>"></script>
</body>
</html>