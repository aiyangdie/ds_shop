<?php
/** @var \lib\PdoHelper $DB */
include '../includes/common.php';

adminpermission('site', 3);
if (!isset($islogin)) {
    echo <<<hhh
<script>window.location.href="./login.php"</script>
hhh;
    die();
}
!defined('IS_AJAX') && define('IS_AJAX', $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');

if (IS_AJAX) {
    @header('Content-type: application/json;charset=utf-8');
    $act = $_GET['act'] ?? '';
    if ($act === 'get_list') {
        $sql = "1";
        if (isset($_POST['kw'])) {
            $kw = daddslashes($_POST['kw']);
            if (is_numeric($kw)) {
                $kw  = intval($kw);
                $sql .= " AND `id` = {$kw}";
            } else {
                $sql .= " AND (`name` LIKE '%{$kw}%' OR `domain` = '{$kw}')";
            }
        }
        $pageSize = intval($_POST['page_size'] ?? 8);
        $page     = intval($_POST['page'] ?? 1);
        $total    = $DB->getColumn('SELECT count(`id`) FROM `pre_apps` WHERE ' . $sql);
        $pages    = ceil($total / $pageSize);
        $offset   = $pageSize * ($page - 1);

        $data = $DB->getAll("SELECT * FROM `pre_apps` WHERE $sql ORDER BY `id` DESC LIMIT {$offset},{$pageSize}");
        exit(json_encode(['code' => 0, 'message' => 'succeed', 'data' => [
            'list'  => $data,
            'total' => $total,
            'pages' => $pages
        ]]));
    } else if ($act === 'del') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id || !$DB->find('apps', 'id', ['id' => $id])) {
            exit(json_encode(['code' => -1, 'message' => '记录不存在！']));
        }
        $DB->delete('apps', ['id' => $id]);
        exit(json_encode(['code' => 0, 'message' => '删除成功']));
    } else if ($act === 'store') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id || !$DB->find('apps', 'id', ['id' => $id])) {
            exit(json_encode(['code' => -1, 'message' => '记录不存在！']));
        }
        [$iosLinkUrl, $androidLinkUrl] = [$_POST['ios_url'] ?? '', $_POST['android_url'] ?? ''];
        if (!$iosLinkUrl || !$androidLinkUrl) {
            exit(json_encode(['code' => -2, 'message' => '苹果和安卓终端下载地址为必填项']));
        }
        if ($DB->update('apps', ['ios_url' => $iosLinkUrl, 'android_url' => $androidLinkUrl, 'status' => 1], ['id' => $id])) {
            exit(json_encode(['code' => 0, 'message' => '保存成功']));
        } else {
            exit(json_encode(['code' => -3, 'message' => '保存失败,' . $DB->error()]));
        }
    } else if ($act === 'retry_get') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id || !$row = $DB->find('apps', '*', ['id' => $id])) {
            exit(json_encode(['code' => -1, 'message' => '记录不存在！']));
        } else if ($row['status'] == 2) {
            exit(json_encode(['code' => -2, 'message' => '状态已是制作中，不能再次修改！']));
        }
        $DB->update('apps', ['status' => 2, 'android_url' => '', 'ios_url' => ''], ['id' => $id]);
        exit(json_encode(['code' => 0, 'message' => '保存成功']));
    } else if ($act === 'upload') {
        if (count($_FILES) > 1) {
            exit(json_encode(['code' => -1, 'message' => '最多只能上传一个文件！']));
        }
        $file = $_FILES['file'] ?? [];
        if ($file['error'] > 0) {
            exit(json_encode(['code' => -1, 'message' => '文件有错误，请检查完后再重新上传！']));
        }
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename  = random(32) . '.' . $extension;
        if (in_array($extension , ['php' , 'java' , 'exe' , 'jsp' , 'jspx' , 'dll' ])) {
            exit(json_encode(['code' => -2 , 'message' => '文件后缀格式不允许被上传！']));
        }
        $savePath = dirname(SYSTEM_ROOT) . '/assets/app_package/';
        !is_dir($savePath) && @mkdir($savePath , 0755 , true);
        if (! move_uploaded_file($file['tmp_name'] , $fullFilePath = $savePath . $filename)) {
            exit(json_encode(['code' => -3 , 'message' => '文件上传失败！']));
        }
        exit(json_encode(['code' => 0, 'message' => 'succeed', 'data' => [
            'url' => '/assets/app_package/' . $filename,
        ]]));
    } else {
        exit(json_encode(['code' => -1, 'message' => 'NoAction']));
    }
}

$title = 'APP列表';
include "head.php";

?>
<div class="col-sm-12 col-md-12 " id="page-app">
    <div class="block">
        <div class="block-title clearfix">
            <div class="block-options pull-right">
                <button class="btn btn-sm btn-default" @click="query = {};loadRecord();" data-toggle="tooltip"
                        data-title="刷新"><em class="fa fa-refresh"></em></button>
            </div>
            <h2>数据展览</h2>
        </div>

        <div class="table-responsive">
            <div class="form-inline">
                <button type="button" @click="loadRecord();" class="btn btn-sm btn-primary"><em
                            class="fa fa-search"></em></button>
                <div class="form-group">
                    <input type="text" class="form-control" v-model="query.kw" placeholder="输入要搜索的内容"/>
                </div>
            </div>
            <hr>
            <table class="table table-striped table-hover table-bordered">
                <thead>
                <tr>
                    <th nowrap class="text-center">#</th>
                    <th nowrap class="text-center">APP名称</th>
                    <th nowrap class="text-center">域名</th>
                    <th nowrap class="text-center">创建时间</th>
                    <th nowrap class="text-center">状态</th>
                    <th nowrap class="text-center">操作</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="app in data['list'] || []">
                    <td v-text="app.id" class="text-center"></td>
                    <td class="text-center">
                        <span title="点击进入详情页">{{ app.name }}</span>
                    </td>
                    <td class="text-center">
                        <a target="_blank" :href="'http://' + app.domain">{{ app.domain }}</a>
                    </td>
                    <td v-text="app.addtime" class="text-center"></td>
                    <td class="text-center">
                        <span class="label label-default" v-if="app.status == 0">预热中</span>
                        <span class="label label-info" v-else-if="app.status == 2">制作中</span>
                        <span class="label label-success" v-else-if="app.status == 1">已完成</span>
                        <span class="label label-danger" v-else>未知 # {{ app.status }}</span>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-xs btn-effect-ripple btn-danger" @click="del(app.id)"><em
                                    class="fa fa-times"></em> 删除
                        </button>
                        <button class="btn btn-xs btn-effect-ripple btn-info" @click="item = app;action='edit';"
                                data-target="#modal-detail" data-toggle="modal"><em class="fa fa-edit"></em> 编辑
                        </button>
                        <button class="btn btn-xs btn-effect-ripple btn-success" @click="retry_get(app.id)"><em
                                    class="fa fa-edit"></em> 重新获取
                        </button>
                    </td>
                </tr>
                </tbody>

            </table>
            <div class="sub-header"></div>
            <div class="text-center">
                <pagination :total="data.total || 0" :page_size="query.page_size" :current_page="query.page"
                            :page_group="6"
                            @page-phange="changePage"></pagination>
            </div>
        </div>
    </div>
    <div id="modal-detail" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <ul class="nav nav-tabs" data-toggle="tabs">
                        <li><a href="#modal-tabs-home"><i class="fa fa-eye"></i> 查看</a></li>
                        <li :class="{active : action === 'edit'}"><a href="#modal-tabs-settings"><i
                                        class="fa fa-edit"></i> 修改</a></li>
                    </ul>
                </div>
                <div class="modal-body">
                    <div class="tab-content">
                        <div class="tab-pane" id="modal-tabs-home">
                            <div class="list-group">
                                <a href="javascript:void(0)" class="list-group-item">
                                    <span class="badge">#</span>
                                    <h4 class="list-group-item-heading"><strong>{{ item.id }}</strong></h4>
                                </a>
                                <a href="javascript:void(0)" class="list-group-item">
                                    <span class="badge">APP名称</span>
                                    <h4 class="list-group-item-heading"><strong>{{ item.name }}</strong></h4>
                                </a>
                                <a href="javascript:void(0)" class="list-group-item">
                                    <span class="badge">包名（package）</span>
                                    <h4 class="list-group-item-heading"><strong>{{ item.package }}</strong></h4>
                                </a>
                                <a href="javascript:void(0)" class="list-group-item">
                                    <span class="badge">打包编号（taskid）</span>
                                    <h4 class="list-group-item-heading"><strong>{{ item.taskid }}</strong></h4>
                                </a>
                                <a href="javascript:void(0)" class="list-group-item">
                                    <span class="badge">域名（domain）</span>
                                    <h4 class="list-group-item-heading"><strong>{{ item.domain }}</strong></h4>
                                </a>
                                <a href="javascript:void(0)" class="list-group-item">
                                    <span class="badge">创建时间（addtime）</span>
                                    <h4 class="list-group-item-heading"><strong>{{ item.addtime }}</strong></h4>
                                </a>
                                <a href="javascript:void(0)" class="list-group-item">
                                    <span class="badge" @click="window.open(item.android_url , '_blank');"><i
                                                class="fa fa-cloud-download"></i></span>
                                    <h4 class="list-group-item-heading"><strong>Android终端下载</strong></h4>
                                    <p class="mt-1 list-group-item-text">{{ item.android_url }}</p>
                                </a>
                                <a href="javascript:void(0)" class="list-group-item">
                                    <span class="badge" @click="window.open(item.ios_url , '_blank');"><i
                                                class="fa fa-cloud-download"></i></span>
                                    <h4 class="list-group-item-heading"><strong>IOS终端下载</strong></h4>
                                    <p class="mt-1 list-group-item-text">{{ item.ios_url }}</p>
                                </a>
                                <a href="javascript:void(0)" class="list-group-item"
                                   @click="window.open('<?php echo $siteurl ?>?mod=app&id=' + item.id , '_blank');">
                                    <span class="badge"><i class="fa fa-cloud-download"></i></span>
                                    <h4 class="list-group-item-heading"><strong>双终端下载地址</strong></h4>
                                </a>
                            </div>
                        </div>
                        <div class="tab-pane" id="modal-tabs-settings" :class="{active : action === 'edit'}">
                            <form @submit.prevent.self="store" method="post" class="form-bordered"
                                  onsubmit="return false;">
                                <input type="hidden" name="id" :value="item.id"/>
                                <div class="form-group">
                                    <label for="example-nf-android-url">安卓下载地址：</label>
                                    <div class="input-group">
                                        <input type="text" id="example-nf-android-url" name="android_url"
                                               :value="item.android_url" class="form-control"/>
                                        <div class="input-group-btn">
                                            <button type="button" class="btn btn-info"
                                                    @click="uploadAttach($('#example-nf-android-url'))"><em
                                                        class="fa fa-upload"></em></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="example-nf-ios-url">苹果下载地址：</label>
                                    <div class="input-group">
                                        <input type="text" id="example-nf-ios-url" name="ios_url" :value="item.ios_url"
                                               class="form-control"/>
                                        <div class="input-group-btn">
                                            <button type="button" class="btn btn-info"
                                                    @click="uploadAttach($('#example-nf-ios-url'))"><em
                                                        class="fa fa-upload"></em></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group form-actions">
                                    <button type="submit" class="btn btn-effect-ripple btn-primary">保存</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?php echo $cdnpublic ?>vue/2.6.14/vue.min.js"></script>
<script src="<?php echo $cdnpublic ?>layer/3.1.1/layer.js"></script>

<?php
require './comments/pagination.php';
?>
<script type="text/javascript">
    Vue.prototype.$http = function (url, data, method) {
        var layerLoadIndex;
        return new Promise((resolve, reject) => {
            $.ajax({
                data: data,
                url: url,
                type: method || 'post',
                beforeSend() {
                    if (!layerLoadIndex) layerLoadIndex = layer.load();
                },
                complete() {
                    layerLoadIndex && (layer.close(layerLoadIndex) , layerLoadIndex = null);
                },
                success: (response) => {
                    if (response.code !== 0) {
                        layer.alert(response.message, {icon: 2});
                        return;
                    }
                    resolve(response);
                },
                error: () => {
                    layer.alert("请求失败！", {icon: 2});
                    reject({...arguments});
                }
            });
        });
    }
    new Vue({
        el: '#page-app',
        data: {
            query: {
                page_size: 8,
                page: 1,
            },
            data: [],
            item: {},
            action: '',
        },
        methods: {
            changePage: function (page) {
                this.query.page = page;
                this.loadRecord();
            },
            loadRecord: function () {
                this.$http('?act=get_list', this.query).then((response) => {
                    this.data = response.data;
                });
            },
            del: function (id) {
                layer.confirm('确定要删除ID为' + id + '吗？', {
                    title: '操作确认',
                    icon: 7
                }, (index) => {
                    this.$http('?act=del', {id: id}).then((res) => {
                        layer.alert(res.message, {icon: 1});
                        this.loadRecord();
                        layer.close(index);
                    })
                })
            },
            uploadAttach: function (el) {
                let input = document.createElement('input');
                input.type = 'file';
                input.name = 'file';
                input.onchange = function (e) {
                    if (this.files.length < 1) {
                        return layer.alert('请先选择文件！');
                    }
                    let form = new FormData();
                    let layerLoadIndex;
                    form.append('file', this.files[0]);
                    $.ajax({
                        url: '?act=upload',
                        data: form,
                        type: 'post',
                        processData: false,
                        contentType: false,
                        beforeSend() {
                            if (!layerLoadIndex) layerLoadIndex = layer.load();
                        },
                        complete() {
                            layerLoadIndex && (layer.close(layerLoadIndex) , layerLoadIndex = null);
                        },
                        success: function (res) {
                            if (res.code !== 0) {
                                return layer.alert(res.message, {icon: 2});
                            }
                            layer.msg('上传成功', {icon: 1});
                            el.val(res.data.url);
                        },
                        error: function () {
                            return layer.alert('文件上传失败');
                        }
                    });
                };
                input.click();
            },
            store: function (e) {
                let el = $(e.target);
                this.$http('?act=store', el.serialize()).then((res) => {
                    layer.msg(res.message, {icon: 1});
                    this.loadRecord();
                    $('#modal-detail').modal('hide');
                });
            },
            retry_get: function (id) {
                layer.confirm('注：系统会将【安卓】与【苹果】的下载链接清空，清空后会将状态改成【制作中】，当有客户访问下载链接APP会自动向APP厂家获取最新链接并自动保存，望知晓！', {
                    title: '操作确认',
                }, (index) => {
                    this.$http('?act=retry_get', {id: id}).then((res) => {
                        layer.alert(res.message, {icon: 1});
                        this.loadRecord();
                        layer.close(index);
                    });
                });
            }
        },
        mounted: function () {
            this.loadRecord();
        }
    })
</script>
</body>
</html>
