var pagesize = 30;
var checkflag1 = "false";

function check1(field) {
    if (checkflag1 == "false") {
        for (i = 0; i < field.length; i++) {
            field[i].checked = true;
        }
        checkflag1 = "true";
        return "false";
    } else {
        for (i = 0; i < field.length; i++) {
            field[i].checked = false;
        }
        checkflag1 = "false";
        return "true";
    }
}

function unselectall1() {
    if (document.form1.chkAll1.checked) {
        document.form1.chkAll1.checked = document.form1.chkAll1.checked & 0;
        checkflag1 = "false";
    }
}

function listTable(query) {
    var url = window.document.location.href.toString();
    var queryString = url.split("?")[1];
    query = query || queryString;
    if (query == 'start' || query == undefined) {
        query = '';
        history.replaceState({}, null, './list.php');
    } else if (query != undefined) {
        history.replaceState({}, null, './list.php?' + query);
    }
    layer.closeAll();
    var ii = layer.load(2, {shade: [0.1, '#fff']});
    $.ajax({
        type: 'GET',
        url: 'list-table.php?num=' + pagesize + '&' + query,
        dataType: 'html',
        cache: false,
        success: function (data) {
            layer.close(ii);
            $("#listTable").html(data)
        },
        error: function (data) {
            layer.msg('服务器错误');
            return false;
        }
    });
}

function searchOrder() {
    var kw = $("input[name='kw']").val();
    var type = $("select[name='type']").val();
    var tid = $("input[name='tid']").val();
    var zid = $("input[name='zid']").val();
    var uid = $("input[name='uid']").val();
    var starttime = $("input[name='starttime']").val();
    var endtime = $("input[name='endtime']").val();
    var addstr = "";
    if (tid != '') addstr = "tid=" + tid + "&";
    else if (zid != '') addstr = "zid=" + zid + "&";
    else if (uid != '') addstr = "uid=" + uid + "&";
    if (starttime != '') addstr += "starttime=" + starttime + "&";
    if (endtime != '') addstr += "endtime=" + endtime + "&";
    if (kw == '') {
        listTable(addstr + 'type=' + type);
    } else {
        $("select[name='type']").val(-1);
        listTable(addstr + 'kw=' + kw);
    }
    return false;
}

function clearOrder() {
    $("input[name='kw']").val('');
    $("input[name='starttime']").val('');
    $("input[name='endtime']").val('');
    listTable('start')
}

function operation() {
    var ii = layer.load(2, {shade: [0.1, '#fff']});
    $.ajax({
        type: 'POST',
        url: 'ajax_order.php?act=operation',
        data: $('#form1').serialize(),
        dataType: 'json',
        success: function (data) {
            layer.close(ii);
            if (data.code == 0) {
                listTable();
                layer.alert(data.msg);
            } else {
                layer.alert(data.msg);
            }
        },
        error: function (data) {
            layer.msg('请求超时');
            listTable();
        }
    });
    return false;
}

function showStatus(id) {
    var ii = layer.load(2, {shade: [0.1, '#fff']});
    $.ajax({
        type: 'GET',
        url: 'ajax_order.php?act=showStatus&id=' + id,
        dataType: 'json',
        success: function (data) {
            layer.close(ii);
            if (data.code == 0) {
                var item = '以下数据来自' + data.domain + '  商品ID：<a ' + (data.shopurl ? 'href="' + data.shopurl + '"' : 'javascript:;') + ' target="_blank" rel="noreferrer">' + data.shopid + '</a><br/><table class="table">';
                if (typeof data.list.order_state !== "undefined" && data.list.order_state && typeof data.list.now_num !== "undefined") {
                    item += '<tr><td class="warning">订单ID</td><td>' + data.list.orderid + '</td><td class="warning">订单状态</td><td><font color=blue>' + data.list.order_state + '</font></td></tr><tr><td class="warning">下单数量</td><td>' + data.list.num + '</td><td class="warning">下单时间</td><td>' + data.list.add_time + '</td></tr><tr><td class="warning">初始数量</td><td>' + data.list.start_num + '</td><td class="warning">当前数量</td><td>' + data.list.now_num + '</td></tr>';
                } else {
                    $.each(data.list, function (i, v) {
                        item += '<tr><td class="warning">' + i + '</td><td>' + v + '</td></tr>';
                    });
                }
                item += '</table>';
                var area = [$(window).width() > 400 ? '400px' : '100%', ';max-height:100%'];
                layer.open({
                    type: 1,
                    area: area,
                    title: '订单进度查询',
                    skin: 'layui-layer-rim',
                    content: item,
                    shadeClose: true
                });
            } else {
                layer.alert(data.msg, {shadeClose: true});
            }
        },
        error: function (data) {
            layer.msg('服务器错误');
            return false;
        }
    });
}

function djOrder(id) {
    var ii = layer.load(2, {shade: [0.1, '#fff']});
    $.ajax({
        type: 'GET',
        url: 'ajax_order.php?act=djOrder&id=' + id,
        dataType: 'json',
        success: function (data) {
            layer.close(ii);
            if (data.code == 0) {
                layer.msg(data.msg, {shadeClose: true});
                listTable();
            } else {
                layer.alert(data.msg, {shadeClose: true});
            }
        },
        error: function (data) {
            layer.msg('服务器错误');
            return false;
        }
    });
}

function showOrder(id) {
    var ii = layer.load(2, {shade: [0.1, '#fff']});
    $.ajax({
        type: 'GET',
        url: 'ajax_order.php?act=order&id=' + id,
        dataType: 'json',
        success: function (data) {
            layer.close(ii);
            if (data.code == 0) {
                layer.open({
                    type: 1,
                    title: '订单详情',
                    skin: 'layui-layer-rim',
                    content: data.data,
                    shadeClose: true
                });
            } else {
                layer.alert(data.msg);
            }
        },
        error: function (data) {
            layer.msg('服务器错误');
            return false;
        }
    });
}

function inputOrder(id) {
    var ii = layer.load(2, {shade: [0.1, '#fff']});
    $.ajax({
        type: 'GET',
        url: 'ajax_order.php?act=order2&id=' + id,
        dataType: 'json',
        success: function (data) {
            layer.close(ii);
            if (data.code == 0) {
                layer.open({
                    type: 1,
                    title: '修改数据',
                    skin: 'layui-layer-rim',
                    content: data.data,
                    shadeClose: true
                });
            } else {
                layer.alert(data.msg);
            }
        },
        error: function (data) {
            layer.msg('服务器错误');
            return false;
        }
    });
}

function inputNum(id) {
    var ii = layer.load(2, {shade: [0.1, '#fff']});
    $.ajax({
        type: 'GET',
        url: 'ajax_order.php?act=order3&id=' + id,
        dataType: 'json',
        success: function (data) {
            layer.close(ii);
            if (data.code == 0) {
                layer.open({
                    type: 1,
                    title: '修改份数',
                    skin: 'layui-layer-rim',
                    content: data.data,
                    shadeClose: true
                });
            } else {
                layer.alert(data.msg);
            }
        },
        error: function (data) {
            layer.msg('服务器错误');
            return false;
        }
    });
}

function refund(id) {
    var ii = layer.load(2, {shade: [0.1, '#fff']});
    $.ajax({
        type: 'POST',
        url: 'ajax_order.php?act=getmoney',
        data: {id: id},
        dataType: 'json',
        success: function (data) {
            layer.close(ii);
            if (data.code == 0) {
                layer.prompt({title: '填写退款金额', value: data.money, formType: 0}, function (text, index) {
                    var ii = layer.load(2, {shade: [0.1, '#fff']});
                    $.ajax({
                        type: 'POST',
                        url: 'ajax_order.php?act=refund',
                        data: {id: id, money: text},
                        dataType: 'json',
                        success: function (data) {
                            layer.close(ii);
                            if (data.code == 0) {
                                layer.alert(data.msg, {icon: 1, shadeClose: true}, function () {
                                    listTable()
                                });
                            } else {
                                layer.alert(data.msg, {shadeClose: true});
                            }
                        },
                        error: function (data) {
                            layer.msg('服务器错误');
                            return false;
                        }
                    });
                });
            } else {
                layer.alert(data.msg);
            }
        },
        error: function (data) {
            layer.msg('服务器错误');
            return false;
        }
    });
}

function setStatus(name, status) {
    if (status == 6) {
        refund(name);
        return false;
    }
    var ii = layer.load(2, {shade: [0.1, '#fff']});
    $.ajax({
        type: 'get',
        url: 'ajax_order.php',
        data: 'act=setStatus&name=' + name + '&status=' + status,
        dataType: 'json',
        success: function (ret) {
            layer.close(ii);
            if (ret['code'] != 200) {
                alert(ret['msg'] ? ret['msg'] : '操作失败');
            }
            listTable();
        },
        error: function (data) {
            layer.msg('服务器错误');
            return false;
        }
    });
}

function setResult(id, title) {
    var title = title || '异常原因';
    var ii = layer.load(2, {shade: [0.1, '#fff']});
    $.ajax({
        type: 'POST',
        url: 'ajax_order.php?act=result',
        data: {id: id},
        dataType: 'json',
        success: function (data) {
            layer.close(ii);
            if (data.code == 0) {
                var pro = layer.prompt({
                    title: '填写' + title,
                    value: data.result,
                    formType: 2,
                    shadeClose: true
                }, function (text, index) {
                    var ii = layer.load(2, {shade: [0.1, '#fff']});
                    $.ajax({
                        type: 'POST',
                        url: 'ajax_order.php?act=setresult',
                        data: {id: id, result: text},
                        dataType: 'json',
                        success: function (data) {
                            layer.close(ii);
                            if (data.code == 0) {
                                layer.close(pro);
                                layer.msg('填写' + title + '成功', {time: 500, icon: 1});
                            } else {
                                layer.alert(data.msg, {shadeClose: true});
                            }
                        },
                        error: function (data) {
                            layer.msg('服务器错误');
                            return false;
                        }
                    });
                });
            } else {
                layer.alert(data.msg);
            }
        },
        error: function (data) {
            layer.msg('服务器错误');
            return false;
        }
    });
}

function saveOrder(id) {
    var inputvalue = $("#inputvalue").val();
    if (inputvalue == '' || $("#inputvalue2").val() == '' || $("#inputvalue3").val() == '' || $("#inputvalue4").val() == '' || $("#inputvalue5").val() == '') {
        layer.alert('请确保每项不能为空！');
        return false;
    }
    if ($('#inputname').html() == '下单ＱＱ' && (inputvalue.length < 5 || inputvalue.length > 11)) {
        layer.alert('请输入正确的QQ号！');
        return false;
    }
    $('#save').val('Loading');
    var ii = layer.load(2, {shade: [0.1, '#fff']});
    $.ajax({
        type: "POST",
        url: "ajax_order.php?act=editOrder",
        data: {
            id: id,
            inputvalue: inputvalue,
            inputvalue2: $("#inputvalue2").val(),
            inputvalue3: $("#inputvalue3").val(),
            inputvalue4: $("#inputvalue4").val(),
            inputvalue5: $("#inputvalue5").val()
        },
        dataType: 'json',
        success: function (data) {
            layer.close(ii);
            if (data.code == 0) {
                layer.msg('保存成功！');
                listTable();
            } else {
                layer.alert(data.msg);
            }
            $('#save').val('保存');
        }
    });
}

function saveOrderNum(id) {
    var num = $("#num").val();
    if (num == '') {
        layer.alert('请确保每项不能为空！');
        return false;
    }
    $('#save').val('Loading');
    var ii = layer.load(2, {shade: [0.1, '#fff']});
    $.ajax({
        type: "POST",
        url: "ajax_order.php?act=editOrderNum",
        data: {id: id, num: num},
        dataType: 'json',
        success: function (data) {
            layer.close(ii);
            if (data.code == 0) {
                layer.msg('保存成功！');
                listTable();
            } else {
                layer.alert(data.msg);
            }
            $('#save').val('保存');
        }
    });
}

$(document).ready(function () {
    listTable();
    $('.input-datepicker, .input-daterange').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        clearBtn: true,
        language: 'zh-CN'
    });
    $("#pagesize").change(function () {
        var size = $(this).val();
        pagesize = size;
        listTable();
    });
})

var ordersucc = 0;
var orderfail = 0;

function onekeyDj() {
    let ids = new Array();
    $('table.orderList tr>td>input[type=checkbox]:checked').each((index, el) => {
        ids.push(el.value);
    })
    if (ids.length < 1) {
        return layer.msg('您还未选中记录！');
    }
    let confirmText = '<div class="col-md-12">';
    confirmText += '<div class="text-center" style="color: #ec1915">《将对接失败的订单重新对接》</div><hr />';
    confirmText += '<div>注：<span style="color: #ec1915">部分订单会因网络延迟而导致对接失败（出现对接失败且社区扣钱的情况）需留意该订单，以免二次对接扣钱导致亏损。</span></div>';
    confirmText += '<hr >';
    confirmText += '<div>订单编号集合: ' + ids.join(',') + '</div>';
    confirmText += '<hr><div class="form-group"><label for="chose_select">选中数量：</label><span class="text-center">共选中' + ids.length + '个记录</span></div>';
    confirmText += '</div>';
    let success = 0, failed = 0;
    layer.confirm(confirmText, {
        title: '重新对接',
        yes: function (index) {
            let html = '<div class="col-md-12" style="margin-top: 15px;height: 350px;;overflow: auto" id="result-modal"></div>';
            let i = null;
            layer.open({
                title: '重新对接订单响应结果',
                type: 1,
                content: html,
                btn: ['关闭' , '成功: <span id="succeed-total">0</span> 条' , '失败: <span id="failed-total">0</span>条' , '查看全部'],
                yes: function (index) {
                    if (success + failed < ids.length) {
                        layer.msg('还有订单未完成，请不要尝试强制关闭此窗口。');
                        return false;
                    } else {
                        layer.close(index);
                        listTable();
                    }
                },
                btn2 : function (index) {
                    $('#result-modal .a').hide();
                    $('#result-modal .b').show();
                    return false;
                },
                btn3: function (index) {
                    $('#result-modal .b').hide();
                    $('#result-modal .a').show();
                    return false;
                },
                btn4: function () {
                    $('#result-modal .a').show();
                    $('#result-modal .b').show();

                    return false;
                },
                success: function () {
                    ids.forEach((id) => {
                        $.ajax({
                            url: './ajax_order.php',
                            type: 'GET',
                            data: {
                                id: id,
                                act: 'djOrder',
                            },
                            async : true,
                            beforeSend() {
                                if (!i) i = layer.load(3);
                                let html = `<div id="retry-order-${id}">订单编号【${id}】：<span class="text-info">对接中....</span></div>`;
                                $('#result-modal').append(html);
                            },
                            complete() {
                                i != null && (layer.close(i) , i = null);
                            },
                            success: function (response) {
                                if (response.code !== 0) {
                                    failed++;
                                    $('#failed-total').text(failed);
                                } else {
                                    success++;
                                    $('#succeed-total').text(success);
                                }
                                let head = `<div class="${response.code !== 0 ? 'a' : 'b'}">订单编号【${id}】：` + (
                                    response.code !== 0 ?
                                        '<span style="color: #ff3a09" >》' + response.msg + '《</span>' :
                                        '<span style="color: #0AA770">》' + response.msg + '《</span>'
                                ) + '</div>';
                                $('#result-modal #retry-order-' + id).html(head);
                            },
                            error: function () {
                                let head = '<div class="a">订单编号【' + id + '】：<span style="color: #ff3a09" >》服务器内部错误《</span></div>< br/>';
                                $('#result-modal #retry-order-' + id).html(head);
                                // $('#result-modal').append(head);
                                failed++;
                                $('#failed-total').text(failed);
                            }
                        });
                    });
                }

            });
        }
    });
}

function djOrder2() {
    if ($(".resubmit").length <= 0) {
        layer.alert('一键补单完成！成功:' + ordersucc + '个，失败:' + orderfail + '个', {icon: 1}, function () {
            ordersucc = 0;
            orderfail = 0;
            listTable();
        });
        return;
    } else {
        var obj = $(".resubmit").first();
        var orderid = obj.attr('data-id');
        layer.msg('正在重新提交订单ID:' + orderid, {icon: 16, time: 10000, shade: [0.3, "#000"]});
        $.ajax({
            type: 'GET',
            url: 'ajax_order.php?act=djOrder&id=' + orderid,
            dataType: 'json',
            success: function (data) {
                if (data.code == 0) {
                    ordersucc++;
                    layer.msg(data.msg, {icon: 1});
                } else {
                    orderfail++;
                    layer.msg(data.msg, {icon: 2});
                }
                obj.removeClass('resubmit');
                djOrder2();
            },
            error: function (data) {
                layer.msg('服务器错误');
                return false;
            }
        });
    }
}

