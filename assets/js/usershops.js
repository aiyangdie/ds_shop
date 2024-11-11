var $_GET = (function () {
    var url = window.document.location.href.toString();
    var u = url.split("?");
    if (typeof (u[1]) == "string") {
        u = u[1].split("&");
        var get = {};
        for (var i in u) {
            var j = u[i].split("=");
            get[j[0]] = j[1];
        }
        return get;
    } else {
        return {};
    }
})();

function getPoint() {
    if ($('#tid option:selected').val() == undefined || $('#tid option:selected').val() == "0") {
        $('#inputsname').html("");
        $('#need').val('');
        $('#display_price').hide();
        $('#display_num').hide();
        $('#display_left').hide();
        $('#alert_frame').hide();
        $('#inputsname').html('下单账号');
        return false;
    }
    history.replaceState({}, null, './shops.php?cid=' + $('#cid').val() + '&tid=' + $('#tid option:selected').val());
    var multi = $('#tid option:selected').attr('multi');
    var count = $('#tid option:selected').attr('count');
    var price = $('#tid option:selected').attr('price');
    var shopimg = $('#tid option:selected').attr('shopimg');
    var close = $('#tid option:selected').attr('close');
    $('#display_price').show();
    if (multi == 1 && count > 1) {
        $('#need').val('￥' + price + "元 ➠ " + count + "个");
    } else {
        $('#need').val('￥' + price + "元");
    }
    if (close == 1) {
        $('#submit_buy').val('当前商品已停止下单');
        $('#submit_buy').html('当前商品已停止下单');
        layer.alert('当前商品维护中，停止下单！');
    } else if (price == 0) {
        $('#submit_buy').val('立即免费领取');
        $('#submit_buy').html('立即免费领取');
    } else {
        $('#submit_buy').val('立即购买');
        $('#submit_buy').html('立即购买');
    }
    if (multi == 1) {
        $('#display_num').show();
    } else {
        $('#display_num').hide();
    }
    var desc = $('#tid option:selected').attr('desc');
    if (desc != '' && alert != 'null') {
        $('#alert_frame').show();
        $('#alert_frame').html(unescape(desc));
    } else {
        $('#alert_frame').hide();
    }
    var inputname = $('#tid option:selected').attr('inputname');
    if (inputname == '' || inputname == 'hide') inputname = '下单账号';
    $('#inputsname').html(inputname);
    var inputsname = $('#tid option:selected').attr('inputsname');
    if (inputsname != '') {
        $.each(inputsname.split('|'), function (i, value) {
            if (value.indexOf('[') > 0 && value.indexOf(']') > 0) {
                value = value.split('[')[0];
            }
            if (value.indexOf('{') > 0 && value.indexOf('}') > 0) {
                value = value.split('{')[0];
            }
            $('#inputsname').append('|' + value);
        });
    }
    $('#inputvalues').attr('placeholder', $('#inputsname').html() + "\r\n" + $('#inputsname').html() + "\r\n......");
    var stock = $('#tid option:selected').attr('stock');
    if ($('#tid option:selected').attr('isfaka') == 1) {
        $('#display_left').show();
        $.ajax({
            type: "POST",
            url: "../ajax.php?act=getleftcount",
            data: {tid: $('#tid option:selected').val()},
            dataType: 'json',
            success: function (data) {
                $('#leftcount').val(data.count)
            }
        });
    } else if (stock != null && stock != '' && stock != 'null') {
        $('#display_left').show();
        $('#leftcount').val(stock);
    } else {
        $('#display_left').hide();
    }
    var alert = $('#tid option:selected').attr('alert');
    if (alert != '' && alert != 'null') {
        var ii = layer.alert('' + unescape(alert) + '', {
            btn: ['我知道了'],
            title: '商品提示'
        }, function () {
            layer.close(ii);
        });
    }
}

function dopay(type, orderid) {
    if (type == 'rmb') {
        var ii = layer.msg('正在提交订单请稍候...', {icon: 16, shade: 0.5, time: 15000});
        $.ajax({
            type: "POST",
            url: "../ajax.php?act=payrmb",
            data: {orderid: orderid},
            dataType: 'json',
            success: function (data) {
                layer.close(ii);
                if (data.code == 1) {
                    alert(data.msg);
                    window.location.href = islogin == 1 ? './list.php' : '../?buyok=1';
                } else if (data.code == -2) {
                    alert(data.msg);
                    window.location.href = islogin == 1 ? './list.php' : '../?buyok=1';
                } else if (data.code == -3) {
                    var confirmobj = layer.confirm('你的余额不足，请充值！', {
                        btn: ['立即充值', '取消']
                    }, function () {
                        window.location.href = './recharge.php';
                    }, function () {
                        layer.close(confirmobj);
                    });
                } else {
                    layer.alert(data.msg);
                }
            }
        });
    } else {
        window.location.href = '../other/submit.php?type=' + type + '&orderid=' + orderid;
    }
}

function cancel(orderid) {
    layer.closeAll();
    $.ajax({
        type: "POST",
        url: "../ajax.php?act=cancel",
        data: {orderid: orderid, hashsalt: hashsalt},
        dataType: 'json',
        async: true,
        success: function (data) {
            if (data.code == 0) {
            } else {
                layer.msg(data.msg);
                window.location.reload();
            }
        },
        error: function (data) {
            window.location.reload();
        }
    });
}

$(document).ready(function () {
    $("#doSearch").click(function () {
        var kw = $("#searchkw").val();
        if (kw == '') {
            layer.msg('请先输入要搜索的内容', {time: 500});
            return;
        }
        var ii = layer.load(2, {shade: [0.1, '#fff']});
        $("#tid").empty();
        $("#tid").append('<option value="0">请选择商品</option>');
        $.ajax({
            type: "POST",
            url: "../ajax.php?act=gettool",
            data: {kw: kw},
            dataType: 'json',
            success: function (data) {
                layer.close(ii);
                if (data.code == 0) {
                    var num = 0;
                    $.each(data.data, function (i, res) {
                        $("#tid").append('<option value="' + res.tid + '" cid="' + res.cid + '" price="' + res.price + '" desc="' + escape(res.desc) + '" alert="' + escape(res.alert) + '" inputname="' + res.input + '" inputsname="' + res.inputs + '" multi="' + res.multi + '" isfaka="' + res.isfaka + '" count="' + res.value + '" close="' + res.close + '" prices="' + res.prices + '" max="' + res.max + '" min="' + res.min + '" stock="' + res.stock + '">' + res.name + '</option>');
                        num++;
                    });
                    $("#tid").val(0);
                    getPoint();
                    if (num == 0 && cid != 0) layer.msg('<option value="0">没有搜索到相关商品</option>', {
                        icon: 2,
                        time: 500
                    });
                    else layer.msg('成功搜索到' + num + '个商品', {icon: 1, time: 1000});
                } else {
                    layer.alert(data.msg);
                }
            },
            error: function (data) {
                layer.msg('加载失败，请刷新重试');
                return false;
            }
        });
    });
    $('#batch-pay-modal #succeed-show,#failed-show,#all-show').on('click', function () {
        if (this.id === 'succeed-show') {
            $('#batch-pay-modal table>tbody tr:not(.succeed)').hide();
            $('#batch-pay-modal .succeed').show();
        } else if (this.id === 'failed-show') {
            $('#batch-pay-modal table>tbody tr:not(.failed)').hide();
            $('#batch-pay-modal .failed').show();
        } else if (this.id === 'all-show') {
            $('#batch-pay-modal table>tbody tr').show();
        }
    });
    $("#cid").change(function () {
        var cid = $(this).val();
        if (cid > 0) history.replaceState({}, null, './shops.php?cid=' + cid);
        var ii = layer.load(2, {shade: [0.1, '#fff']});
        $("#tid").empty();
        $("#tid").append('<option value="0">请选择商品</option>');
        $.ajax({
            type: "GET",
            url: "../ajax.php?act=gettool&cid=" + cid + "&info=1",
            dataType: 'json',
            success: function (data) {
                layer.close(ii);
                $("#tid").empty();
                $("#tid").append('<option value="0">请选择商品</option>');
                if (data.code == 0) {
                    if (data.info != null) {
                        $("#className").html(data.info.name);
                        if (data.info.shopimg)
                            $("#classImg").attr('src', data.info.shopimg.indexOf("://") > 0 ? data.info.shopimg : '../' + data.info.shopimg);
                        else
                            $("#classImg").attr('src', '');
                    }
                    var num = 0;
                    $.each(data.data, function (i, res) {
                        $("#tid").append('<option value="' + res.tid + '" cid="' + res.cid + '" price="' + res.price + '" desc="' + escape(res.desc) + '" alert="' + escape(res.alert) + '" inputname="' + res.input + '" inputsname="' + res.inputs + '" multi="' + res.multi + '" isfaka="' + res.isfaka + '" count="' + res.value + '" close="' + res.close + '" prices="' + res.prices + '" max="' + res.max + '" min="' + res.min + '" stock="' + res.stock + '">' + res.name + '</option>');
                        num++;
                    });
                    if ($_GET["tid"] && $_GET["cid"] == cid) {
                        var tid = parseInt($_GET["tid"]);
                        $("#tid").val(tid);
                    } else {
                        $("#tid").val(0);
                    }
                    getPoint();
                    if (num == 0 && cid != 0) $("#tid").html('<option value="0">该分类下没有商品</option>');
                } else {
                    layer.alert(data.msg);
                }
            },
            error: function (data) {
                layer.msg('加载失败，请刷新重试');
                return false;
            }
        });
    });
    $("#submit_buy").click(function () {
        var tid = $("#tid").val();
        if (tid == 0) {
            layer.alert('请选择商品！');
            return false;
        }
        var inputvalues = $("#inputvalues").val();
        if (inputvalues == '' || tid == '') {
            layer.alert('请确保每项不能为空！');
            return false;
        }
        let goods = $('select#tid option:selected');

        let data = inputvalues.split("\n").filter((v) => v);
        if (data.length > 30) {
            layer.confirm('温馨提示：您所提交的订单数量已超过系统单批处理上限，需其他特殊方式处理来以确保订单数量的准确性。<hr />' +
                '注：<p><span class="text-danger">1：下单前请先检查数量与实际需要的数量一致。</span></p>' +
                '<p><span class="text-danger">2：在下单过程中请勿关闭该页面，否则会出现丢单情况。【非常重要】</span></p>' +
                '<p class="text-danger">3：该方法仅支持余额付款，下单前请先确保账户余额充足。</p>' +
                '<hr />数量集合：' + data.length + '条。共计金额：' + (data.length * goods.attr('price') * $('#num').val()) + '元', {
                icon: 1,
                btn: ['我已确认无误', '去充值', '关闭'],
            }, function (_index) {
                $('#batch-pay-modal').modal('show');
                $(`#batch-pay-modal table > tbody`).empty();
                $(`#batch-pay-modal #succeed-total`).text(0);
                $(`#batch-pay-modal #failed-total`).text(0);
                let succeed = 0, failed = 0;
                $('#batch-pay-modal #close').attr('disabled', true);
                $('#batch-pay-modal table>thead>tr>th:eq(1)').html(goods.attr('inputname') || '下单信息');
                layer.close(_index);

                let pipeTasks = new Array(),
                    modal = $('#batch-pay-modal'),
                    table = modal.find('table');
                data.forEach((_, index) => {
                    index++;
                    pipeTasks.push(new Promise((resolve, reject) => {
                        $.ajax({
                            url: '../ajax.php?act=pay',
                            data: {
                                inputvalue: _,
                                hashsalt: hashsalt,
                                num: $('#num').val(),
                                tid: tid,
                            },
                            async: true,
                            type: 'post',
                            beforeSend() {
                                let element = `<tr id="order-${index}"><td>${index}</td><td>${_}</td></tr><tr><td colspan="2" id="order-${index}-state-text">当前进展: 下单中....</td></tr>`;
                                table.append(element);
                            },
                            success(ret) {
                                let stateEl = table.find(`tbody #order-${index}-state-text`);
                                if (ret.code !== 0) {
                                    failed++;
                                    stateEl.text("<span class='text-danger'>初始下单失败: " + ret.msg + '</span>');
                                    stateEl.parent('tr').addClass('failed').prev('tr').addClass('failed');
                                    reject({
                                        index: index,
                                        message: '下单失败: ' + ret.msg,
                                        value: _,
                                    });
                                    modal.find('#failed-total').text(failed);
                                } else {
                                    stateEl.html('<span style="color: #77ee7c">创建订单成功,下单中....</span>');
                                    $.ajax({
                                        url: '../ajax.php?act=payrmb',
                                        async: true,
                                        type: 'post',
                                        data: {
                                            orderid: ret.trade_no,
                                        },
                                        beforeSend() {
                                            stateEl.html('<span style="color: #3543ff">开始下单中....</span>');
                                        },
                                        success(ret) {
                                            if (ret.code !== 1) {
                                                failed++;
                                                stateEl.html(`<span style="color: red">下单失败: ${ret.msg}</span>`);
                                                stateEl.parent('tr').addClass('failed').prev('tr').addClass('failed');
                                                reject({
                                                    index: index,
                                                    message: '下单失败: ' + ret.msg,
                                                    value: _,
                                                });
                                                modal.find('#failed-total').text(failed);
                                            } else {
                                                succeed++;
                                                stateEl.html(`<span style="color: green">下单成功，订单编号：${ret.orderid}</span>`);
                                                stateEl.parent('tr').addClass('succeed').prev('tr').addClass('succeed');
                                                resolve({
                                                    index: index,
                                                    message: '下单成功'
                                                });
                                                modal.find('#succeed-total').text(succeed);
                                            }
                                        },
                                        error() {
                                            failed++;
                                            let stateEl = table.find(`tbody #order-${index}-state-text`);
                                            stateEl.html('<span style="color: red">下单失败: 服务无响应！</span>');
                                            stateEl.parent('tr').addClass('failed').prev('tr').addClass('failed');
                                            reject({
                                                index: index,
                                                message: '下单失败: 服务无响应！',
                                                value: _,
                                            });
                                            modal.find('#failed-total').text(failed);
                                        }
                                    });
                                }
                            },
                            error() {
                                failed++;
                                let stateEl = table.find(`tbody #order-${index}-state-text`);
                                stateEl.html('<span style="color: red">下单失败: 内部错误！</span>');
                                stateEl.parent('tr').addClass('failed').prev('tr').addClass('failed');
                                reject({
                                    index: index,
                                    message: '下单失败: 疑似网络原因！',
                                    value: _,
                                });
                                modal.find('#failed-total').text(failed);
                            }
                        });
                    }));
                });

                Promise.all(pipeTasks.map((promise) => promise.catch((res) => {
                    res.error = true;
                    return res;
                }))).then((res) => {
                    const errors = res.filter((result) => result.hasOwnProperty('error'));
                    $('#batch-pay-modal #close').attr('disabled', false);
                    let tipText = `下单已完成。`;
                    if (errors.length > 0) {
                        tipText += '<br /><span class="text-danger">执行过程中发现失败订单，系统已自动整合。（检查无误后可重新提交订单）</span>'
                    } else {
                        tipText += '<br /><span class="text-success">执行过程中均未发生异常与失败，可继续正常下单。</span>';
                    }
                    layer.confirm(tipText, {
                        icon: 1,
                        btn: [errors.length > 0 ? '查看失败条目' : '确定', '关闭'],
                    }, function (index) {
                        layer.close(index);
                        if (errors.length < 1) {
                            return;
                        }
                        let items = '';
                        errors.forEach((item) => {
                            items += item.value + "\r\n";
                        });
                        $('#failed-result-modal #failed-text').val(items);
                        $('#failed-result-modal').modal('show');
                        $('#failed-result').show();
                    });
                });

            }, function (index) {
                window.location.href = './recharge.php';
            })

            return false;
        }

        var ii = layer.load(2, {shade: [0.1, '#fff']});
        $.ajax({
            type: "POST",
            url: "../ajax.php?act=pays",
            data: {tid: tid, inputvalues: inputvalues, num: $("#num").val(), hashsalt: hashsalt},
            dataType: 'json',
            success: function (data) {
                layer.close(ii);
                if (data.code == 0) {
                    var paymsg = '';
                    if (data.pay_alipay > 0) {
                        paymsg += '<button class="btn btn-default btn-block" onclick="dopay(\'alipay\',\'' + data.trade_no + '\')" style="margin-top:10px;"><img src="../assets/img/alipay.png" class="logo">支付宝</button>';
                    }
                    if (data.pay_qqpay > 0) {
                        paymsg += '<button class="btn btn-default btn-block" onclick="dopay(\'qqpay\',\'' + data.trade_no + '\')" style="margin-top:10px;"><img src="../assets/img/qqpay.png" class="logo">QQ钱包</button>';
                    }
                    if (data.pay_wxpay > 0) {
                        paymsg += '<button class="btn btn-default btn-block" onclick="dopay(\'wxpay\',\'' + data.trade_no + '\')" style="margin-top:10px;"><img src="../assets/img/wxpay.png" class="logo">微信支付</button>';
                    }
                    if (data.pay_rmb > 0) {
                        paymsg += '<button class="btn btn-default btn-block" onclick="dopay(\'rmb\',\'' + data.trade_no + '\')" style="margin-top:10px;"><img src="../assets/img/rmb.png" class="logo">余额支付</button>';
                    }
                    if (data.paymsg != null) paymsg += data.paymsg;
                    layer.alert('<center><h2>￥ ' + data.need + '</h2><span class="text-muted">共 ' + data.num + ' 件商品</span><hr>' + paymsg + '<hr><a class="btn btn-default btn-block" onclick="cancel(\'' + data.trade_no + '\')">取消订单</a></center>', {
                        btn: [],
                        title: '提交订单成功',
                        closeBtn: false
                    });
                    $.cookie('user_order', data.trade_no, {path: '/'});
                } else if (data.code == 3) {
                    layer.alert(data.msg, {
                        closeBtn: false
                    }, function () {
                        window.location.reload();
                    });
                } else {
                    layer.alert(data.msg);
                }
            }
        });
    });

    $("#num_add").click(function () {
        var i = parseInt($("#num").val());
        if ($("#need").val() == '') {
            layer.alert('请先选择商品');
            return false;
        }
        var multi = $('#tid option:selected').attr('multi');
        var count = parseInt($('#tid option:selected').attr('count'));
        if (multi == '0') {
            layer.alert('该商品不支持选择数量');
            return false;
        }
        i++;
        $("#num").val(i);
        var price = parseFloat($('#tid option:selected').attr('price'));
        var prices = $('#tid option:selected').attr('prices');
        if (prices != '' || prices != 'null') {
            var discount = 0;
            $.each(prices.split(','), function (index, item) {
                if (i >= parseInt(item.split('|')[0])) discount = parseFloat(item.split('|')[1]);
            });
            price = price - discount;
        }

        var mult = 1;
        price = price * i * mult;
        count = count * i;
        if (count > 1) $('#need').val('￥' + price.toFixed(4) + "元 ➠ " + count + "个");
        else $('#need').val('￥' + price.toFixed(4) + "元");
    });
    $("#num_min").click(function () {
        var i = parseInt($("#num").val());
        if (i <= 1) {
            layer.msg('最低下单一份哦！');
            return false;
        }
        if ($("#need").val() == '') {
            layer.alert('请先选择商品');
            return false;
        }
        var multi = $('#tid option:selected').attr('multi');
        var count = parseInt($('#tid option:selected').attr('count'));
        if (multi == '0') {
            layer.alert('该商品不支持选择数量');
            return false;
        }
        i--;
        if (i <= 0) i = 1;
        $("#num").val(i);
        var price = parseFloat($('#tid option:selected').attr('price'));
        var prices = $('#tid option:selected').attr('prices');
        if (prices != '' || prices != 'null') {
            var discount = 0;
            $.each(prices.split(','), function (index, item) {
                if (i >= parseInt(item.split('|')[0])) discount = parseFloat(item.split('|')[1]);
            });
            price = price - discount;
        }

        var mult = 1;
        price = price * i * mult;
        count = count * i;
        if (count > 1) $('#need').val('￥' + price.toFixed(4) + "元 ➠ " + count + "个");
        else $('#need').val('￥' + price.toFixed(4) + "元");
    });
    $("#num").keyup(function () {
        var i = parseInt($("#num").val());
        if (isNaN(i)) return false;
        var price = parseFloat($('#tid option:selected').attr('price'));
        var count = parseInt($('#tid option:selected').attr('count'));
        var prices = $('#tid option:selected').attr('prices');
        if (i < 1) $("#num").val(1);
        if (prices != '' || prices != 'null') {
            var discount = 0;
            $.each(prices.split(','), function (index, item) {
                if (i >= parseInt(item.split('|')[0])) discount = parseFloat(item.split('|')[1]);
            });
            price = price - discount;
        }

        var mult = 1;
        price = price * i * mult;
        count = count * i;
        if (count > 1) $('#need').val('￥' + price.toFixed(4) + "元 ➠ " + count + "个");
        else $('#need').val('￥' + price.toFixed(4) + "元");
    });

    if ($_GET['cid']) {
        var cid = parseInt($_GET['cid']);
        $("#cid").val(cid);
    }
    $("#cid").change();

});

$(document).ready(function () {
    $('#batch-pay-modal').on('hidden.bs.modal' , function () {
        $('#failed-result').hide();
    })
})