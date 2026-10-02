<?php
/**
 * APP 管理中心：列表 / 上架 / 下架 / 删除 / 重试
 */
include '../includes/common.php';
adminpermission('site', 3);
if ($islogin != 1) {
    exit("<script>window.location.href='./login.php';</script>");
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_GET['act']) && $_GET['act'] !== '');

if ($isAjax && isset($_GET['act'])) {
    @header('Content-Type: application/json; charset=UTF-8');
    if (!checkRefererHost()) exit(json_encode(array('code' => 403, 'msg' => 'forbidden')));

    $factory = new \lib\AppFactory($DB, $conf);
    $act = daddslashes($_GET['act']);

    if ($act === 'list') {
        $kw = isset($_POST['kw']) ? trim(strval($_POST['kw'])) : (isset($_GET['kw']) ? trim(strval($_GET['kw'])) : '');
        $filter = isset($_POST['filter']) ? strval($_POST['filter']) : (isset($_GET['filter']) ? strval($_GET['filter']) : 'all');
        $page = max(1, intval(isset($_POST['page']) ? $_POST['page'] : (isset($_GET['page']) ? $_GET['page'] : 1)));
        $pageSize = min(50, max(5, intval(isset($_POST['page_size']) ? $_POST['page_size'] : 12)));
        $where = '1=1';
        if ($kw !== '') {
            if (ctype_digit($kw)) {
                $where .= ' AND id=' . intval($kw);
            } else {
                $k = addslashes($kw);
                $where .= " AND (name LIKE '%$k%' OR domain LIKE '%$k%' OR package LIKE '%$k%')";
            }
        }
        if ($filter === 'online') $where .= ' AND status=1';
        elseif ($filter === 'building') $where .= ' AND status=2';
        elseif ($filter === 'offline') $where .= ' AND status=0 AND IFNULL(build_status,0)=2';
        elseif ($filter === 'failed') $where .= ' AND (IFNULL(build_status,0)=3 OR (status=0 AND IFNULL(error,\'\')<>\'\'))';
        elseif ($filter === 'queued') $where .= ' AND status=2 AND IFNULL(build_status,0)=0';

        $total = intval($DB->getColumn("SELECT count(*) FROM pre_apps WHERE $where"));
        $pages = max(1, ceil($total / $pageSize));
        if ($page > $pages) $page = $pages;
        $offset = ($page - 1) * $pageSize;
        $list = $DB->getAll("SELECT * FROM pre_apps WHERE $where ORDER BY id DESC LIMIT $offset,$pageSize");
        if (!$list) $list = array();

        $stats = array(
            'all' => intval($DB->getColumn("SELECT count(*) FROM pre_apps")),
            'online' => intval($DB->getColumn("SELECT count(*) FROM pre_apps WHERE status=1")),
            'building' => intval($DB->getColumn("SELECT count(*) FROM pre_apps WHERE status=2")),
            'offline' => intval($DB->getColumn("SELECT count(*) FROM pre_apps WHERE status=0 AND IFNULL(build_status,0)=2")),
            'failed' => intval($DB->getColumn("SELECT count(*) FROM pre_apps WHERE IFNULL(build_status,0)=3 OR (status=0 AND IFNULL(error,'')<>'')")),
        );

        exit(json_encode(array(
            'code' => 0,
            'data' => array(
                'list' => $list,
                'total' => $total,
                'pages' => $pages,
                'page' => $page,
                'stats' => $stats,
                'mode' => \lib\AppFactory::isLocalMode($conf) ? 'local' : 'remote',
            ),
        ), JSON_UNESCAPED_UNICODE));
    }

    if ($act === 'offline') {
        $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        $row = $DB->getRow("SELECT * FROM pre_apps WHERE id='$id' LIMIT 1");
        if (!$row) exit(json_encode(array('code' => -1, 'msg' => '记录不存在')));
        $DB->exec("UPDATE pre_apps SET status=0,updatetime='" . date('Y-m-d H:i:s') . "' WHERE id='$id'");
        exit(json_encode(array('code' => 0, 'msg' => '已下架，下载页将不可用')));
    }

    if ($act === 'online') {
        $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        $row = $DB->getRow("SELECT * FROM pre_apps WHERE id='$id' LIMIT 1");
        if (!$row) exit(json_encode(array('code' => -1, 'msg' => '记录不存在')));
        if (empty($row['android_url']) && empty($row['ios_url'])) {
            exit(json_encode(array('code' => -1, 'msg' => '没有可下载地址，无法上架')));
        }
        $DB->exec("UPDATE pre_apps SET status=1,build_status=2,progress=100,error=NULL,updatetime='" . date('Y-m-d H:i:s') . "' WHERE id='$id'");
        exit(json_encode(array('code' => 0, 'msg' => '已上架')));
    }

    if ($act === 'retry') {
        $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        if (!\lib\AppFactory::isLocalMode($conf)) {
            // remote：清空链接改制作中（兼容旧逻辑）
            $row = $DB->getRow("SELECT * FROM pre_apps WHERE id='$id' LIMIT 1");
            if (!$row) exit(json_encode(array('code' => -1, 'msg' => '记录不存在')));
            $DB->exec("UPDATE pre_apps SET status=2,build_status=0,progress=0,android_url='',ios_url='',error=NULL,updatetime='" . date('Y-m-d H:i:s') . "' WHERE id='$id'");
            exit(json_encode(array('code' => 0, 'msg' => '已标记制作中（第三方模式）')));
        }
        if ($factory->retry($id)) {
            exit(json_encode(array('code' => 0, 'msg' => '已重新入队，请等待 Worker 打包')));
        }
        exit(json_encode(array('code' => -1, 'msg' => $factory->msg ?: '重试失败')));
    }

    if ($act === 'del') {
        $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        $row = $DB->getRow("SELECT * FROM pre_apps WHERE id='$id' LIMIT 1");
        if (!$row) exit(json_encode(array('code' => -1, 'msg' => '记录不存在')));
        // 尝试删本地 APK
        if (!empty($row['android_url']) && strpos($row['android_url'], '/assets/uploads/apps/') !== false) {
            $path = ROOT . ltrim(parse_url($row['android_url'], PHP_URL_PATH) ?: $row['android_url'], '/');
            $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
            if (is_file($path) && strpos(realpath($path), realpath(ROOT . 'assets/uploads/apps')) === 0) {
                @unlink($path);
            }
        }
        $jobDir = ROOT . 'tools/appbuild/jobs/' . $id;
        if (is_dir($jobDir)) {
            // 轻量清理
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($jobDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) {
                $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            }
            @rmdir($jobDir);
        }
        $DB->exec("DELETE FROM pre_apps WHERE id='$id'");
        exit(json_encode(array('code' => 0, 'msg' => '已删除')));
    }

    if ($act === 'batch') {
        $ids = isset($_POST['ids']) ? $_POST['ids'] : array();
        $op = isset($_POST['op']) ? strval($_POST['op']) : '';
        if (!is_array($ids) || !$ids) exit(json_encode(array('code' => -1, 'msg' => '未选择')));
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);
        if (!$ids) exit(json_encode(array('code' => -1, 'msg' => '未选择')));
        $in = implode(',', $ids);
        $n = 0;
        if ($op === 'offline') {
            $n = $DB->exec("UPDATE pre_apps SET status=0,updatetime='" . date('Y-m-d H:i:s') . "' WHERE id IN ($in)");
        } elseif ($op === 'online') {
            $n = $DB->exec("UPDATE pre_apps SET status=1,updatetime='" . date('Y-m-d H:i:s') . "' WHERE id IN ($in) AND (android_url<>'' OR ios_url<>'')");
        } elseif ($op === 'del') {
            foreach ($ids as $id) {
                $row = $DB->getRow("SELECT * FROM pre_apps WHERE id='$id' LIMIT 1");
                if (!$row) continue;
                if (!empty($row['android_url']) && strpos($row['android_url'], '/assets/uploads/apps/') !== false) {
                    $rel = parse_url($row['android_url'], PHP_URL_PATH);
                    $path = ROOT . ltrim($rel ? $rel : $row['android_url'], '/');
                    if (is_file($path)) @unlink($path);
                }
                $DB->exec("DELETE FROM pre_apps WHERE id='$id'");
                $n++;
            }
        } else {
            exit(json_encode(array('code' => -1, 'msg' => '未知操作')));
        }
        exit(json_encode(array('code' => 0, 'msg' => '已处理', 'count' => intval($n))));
    }

    if ($act === 'save_links') {
        $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        $android = isset($_POST['android_url']) ? trim(strval($_POST['android_url'])) : '';
        $ios = isset($_POST['ios_url']) ? trim(strval($_POST['ios_url'])) : '';
        $name = isset($_POST['name']) ? trim(strval($_POST['name'])) : '';
        $row = $DB->getRow("SELECT * FROM pre_apps WHERE id='$id' LIMIT 1");
        if (!$row) exit(json_encode(array('code' => -1, 'msg' => '记录不存在')));
        $sets = "android_url='" . addslashes($android) . "',ios_url='" . addslashes($ios) . "',updatetime='" . date('Y-m-d H:i:s') . "'";
        if ($name !== '') $sets .= ",name='" . addslashes(mb_substr($name, 0, 120)) . "'";
        if ($android !== '' || $ios !== '') $sets .= ",status=1,build_status=2,progress=100";
        $DB->exec("UPDATE pre_apps SET $sets WHERE id='$id'");
        exit(json_encode(array('code' => 0, 'msg' => '已保存')));
    }

    exit(json_encode(array('code' => -1, 'msg' => 'unknown act')));
}

$title = 'APP管理';
include './head.php';
$mode = \lib\AppFactory::isLocalMode($conf) ? 'local' : 'remote';
?>
<style>
.app-admin .stat-row{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px}
.app-admin .stat{
  flex:1;min-width:110px;background:#fff;border:1px solid #e8ecf1;border-radius:10px;
  padding:12px 14px;cursor:pointer;transition:border-color .15s,box-shadow .15s;
}
.app-admin .stat:hover,.app-admin .stat.active{border-color:#1f6feb;box-shadow:0 4px 14px rgba(31,111,235,.12)}
.app-admin .stat b{display:block;font-size:22px;line-height:1.2;color:#0f172a}
.app-admin .stat span{font-size:12px;color:#64748b}
.app-admin .toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px}
.app-admin .app-card{
  border:1px solid #e8ecf1;border-radius:12px;padding:14px;margin-bottom:12px;background:#fff;
  display:flex;gap:14px;align-items:flex-start;
}
.app-admin .app-card .ico{
  width:56px;height:56px;border-radius:12px;object-fit:cover;background:#f1f5f9;flex-shrink:0;
}
.app-admin .app-card .body{flex:1;min-width:0}
.app-admin .app-card .title{font-size:16px;font-weight:600;margin:0 0 4px}
.app-admin .app-card .meta{font-size:12px;color:#64748b;line-height:1.6}
.app-admin .app-card .ops{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.app-admin .badge-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600}
.app-admin .b-ok{background:#dcfce7;color:#16a34a}
.app-admin .b-wait{background:#ffedd5;color:#d97706}
.app-admin .b-off{background:#e2e8f0;color:#475569}
.app-admin .b-bad{background:#fee2e2;color:#dc2626}
.app-admin .bar{height:6px;background:#e2e8f0;border-radius:99px;overflow:hidden;margin-top:6px;max-width:220px}
.app-admin .bar i{display:block;height:100%;background:#1f6feb}
@media (max-width:640px){.app-admin .app-card{flex-direction:column}}
</style>
<div class="col-xs-12 col-sm-10 col-lg-10 center-block app-admin" style="float:none;" id="appAdmin">
<div class="block">
  <div class="block-title clearfix">
    <h2><i class="fa fa-mobile"></i> APP 管理中心
      <small class="text-muted">模式：<?php echo $mode === 'local' ? '本地工厂' : '第三方'; ?></small>
    </h2>
    <div class="block-options pull-right">
      <a href="./appCreate.php" class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> 生成 / 配置</a>
      <a href="./ai_set.php" class="btn btn-sm btn-default">附件/云存储</a>
    </div>
  </div>

  <div class="stat-row" id="statRow">
    <div class="stat active" data-filter="all"><b id="sAll">-</b><span>全部</span></div>
    <div class="stat" data-filter="online"><b id="sOnline">-</b><span>已上架</span></div>
    <div class="stat" data-filter="building"><b id="sBuilding">-</b><span>生成中</span></div>
    <div class="stat" data-filter="offline"><b id="sOffline">-</b><span>已下架</span></div>
    <div class="stat" data-filter="failed"><b id="sFailed">-</b><span>失败</span></div>
  </div>

  <div class="toolbar">
    <input type="text" class="form-control" id="kw" placeholder="搜名称 / 域名 / 包名 / ID" style="max-width:240px">
    <button type="button" class="btn btn-primary" id="btnSearch"><i class="fa fa-search"></i> 查询</button>
    <button type="button" class="btn btn-default" id="btnRefresh"><i class="fa fa-refresh"></i></button>
    <span class="text-muted" style="margin-left:auto;font-size:12px" id="batchTip"></span>
    <button type="button" class="btn btn-warning btn-sm" id="btnBatchOff">批量下架</button>
    <button type="button" class="btn btn-success btn-sm" id="btnBatchOn">批量上架</button>
    <button type="button" class="btn btn-danger btn-sm" id="btnBatchDel">批量删除</button>
  </div>

  <div id="listBox"><div class="text-center text-muted" style="padding:40px">加载中…</div></div>
  <div class="text-center" style="margin-top:12px">
    <ul class="pagination" id="pager" style="margin:0"></ul>
  </div>
</div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">编辑 APP #<span id="editId"></span></h4>
      </div>
      <div class="modal-body">
        <div class="form-group"><label>名称</label><input class="form-control" id="editName"></div>
        <div class="form-group"><label>安卓下载地址</label><input class="form-control" id="editAndroid"></div>
        <div class="form-group"><label>iOS 下载地址</label><input class="form-control" id="editIos"></div>
        <p class="help-block">保存有下载地址时会自动设为已上架。</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
        <button type="button" class="btn btn-primary" id="btnSaveEdit">保存</button>
      </div>
    </div>
  </div>
</div>

<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script>
(function () {
  var filter = 'all', page = 1, pageSize = 12, selected = {};
  var siteurl = <?php echo json_encode(rtrim(isset($siteurl)?$siteurl:'', '/'), JSON_UNESCAPED_UNICODE); ?>;

  function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
  function dur(a, u) {
    if (!a || !u) return '';
    var s = Math.floor((Date.parse(String(u).replace(/-/g,'/')) - Date.parse(String(a).replace(/-/g,'/'))) / 1000);
    if (s <= 0) return '';
    if (s < 60) return s + '秒';
    return Math.floor(s/60) + '分' + (s%60) + '秒';
  }
  function sizeFmt(n) {
    n = parseInt(n || 0, 10);
    if (n <= 0) return '';
    if (n < 1048576) return (n/1024).toFixed(1) + 'KB';
    return (n/1048576).toFixed(2) + 'MB';
  }
  function stateOf(r) {
    var st = Number(r.status), bs = Number(r.build_status || 0);
    if (bs === 3 || (st === 0 && r.error)) return {cls:'b-bad', text:'失败'};
    if (st === 2) return {cls:'b-wait', text: bs===0 ? '排队中' : '生成中'};
    if (st === 1) return {cls:'b-ok', text:'已上架'};
    if (st === 0 && bs === 2) return {cls:'b-off', text:'已下架'};
    return {cls:'b-off', text:'不可用'};
  }
  function selectedIds() {
    return Object.keys(selected).filter(function (k) { return selected[k]; }).map(Number);
  }
  function updateBatchTip() {
    var n = selectedIds().length;
    $('#batchTip').text(n ? ('已选 ' + n + ' 项') : '');
  }

  function load() {
    var ii = layer.load(2);
    $.post('app_list.php?act=list', { kw: $('#kw').val(), filter: filter, page: page, page_size: pageSize }, function (res) {
      layer.close(ii);
      if (!res || res.code !== 0) {
        $('#listBox').html('<div class="alert alert-danger">' + esc((res && res.msg) || '加载失败') + '</div>');
        return;
      }
      var d = res.data || {};
      var st = d.stats || {};
      $('#sAll').text(st.all || 0);
      $('#sOnline').text(st.online || 0);
      $('#sBuilding').text(st.building || 0);
      $('#sOffline').text(st.offline || 0);
      $('#sFailed').text(st.failed || 0);

      var list = d.list || [];
      if (!list.length) {
        $('#listBox').html('<div class="text-center text-muted" style="padding:48px">暂无 APP 记录。可去 <a href="./appCreate.php">生成配置</a> 创建。</div>');
        $('#pager').empty();
        return;
      }
      var html = '';
      list.forEach(function (r) {
        var s = stateOf(r);
        var icon = r.icon || '../assets/uploads/apps/default_icon.png';
        var checked = selected[r.id] ? ' checked' : '';
        html += '<div class="app-card">'
          + '<input type="checkbox" class="sel" data-id="'+r.id+'"'+checked+' style="margin-top:20px">'
          + '<img class="ico" src="'+esc(icon)+'" onerror="this.src=\'../assets/uploads/apps/default_icon.png\'">'
          + '<div class="body">'
          + '<div class="title">'+esc(r.name||'未命名')+' <span class="badge-pill '+s.cls+'">'+s.text+'</span> <small class="text-muted">#'+r.id+'</small></div>'
          + '<div class="meta">'
          + '域名 <a href="http://'+esc(r.domain)+'" target="_blank">'+esc(r.domain)+'</a>'
          + (r.package ? (' · 包名 '+esc(r.package)) : '')
          + '<br>提交 '+esc(r.addtime||'-')
          + (r.updatetime ? (' · 更新 '+esc(r.updatetime)) : '')
          + (dur(r.addtime, r.updatetime) ? (' · 耗时 '+dur(r.addtime, r.updatetime)) : '')
          + (sizeFmt(r.file_size) ? (' · '+sizeFmt(r.file_size)) : '')
          + (r.storage_driver ? (' · 存储 '+esc(r.storage_driver)) : '')
          + (r.error ? ('<br><span class="text-danger">'+esc(r.error)+'</span>') : '')
          + '</div>';
        if (Number(r.status) === 2) {
          var p = Math.max(0, Math.min(100, Number(r.progress||0)));
          html += '<div class="bar"><i style="width:'+p+'%"></i></div><small class="text-muted">进度 '+p+'%</small>';
        }
        html += '<div class="ops">'
          + '<a class="btn btn-xs btn-primary" target="_blank" href="'+(siteurl||'..')+'/?mod=app&id='+r.id+'">下载页</a>';
        if (r.android_url) html += '<a class="btn btn-xs btn-default" target="_blank" href="'+esc(r.android_url)+'">APK</a>';
        if (Number(r.status) === 1) html += '<button type="button" class="btn btn-xs btn-warning btn-off" data-id="'+r.id+'">下架</button>';
        if (Number(r.status) !== 1 && (r.android_url || r.ios_url)) html += '<button type="button" class="btn btn-xs btn-success btn-on" data-id="'+r.id+'">上架</button>';
        if (Number(r.build_status) === 3 || Number(r.status) === 0) html += '<button type="button" class="btn btn-xs btn-info btn-retry" data-id="'+r.id+'">重新生成</button>';
        html += '<button type="button" class="btn btn-xs btn-default btn-edit" data-id="'+r.id+'">编辑</button>'
          + '<button type="button" class="btn btn-xs btn-danger btn-del" data-id="'+r.id+'">删除</button>'
          + '</div></div></div>';
      });
      $('#listBox').html(html);

      // pager
      var pages = d.pages || 1;
      var ph = '';
      for (var i = 1; i <= pages; i++) {
        ph += '<li class="'+(i===page?'active':'')+'"><a href="javascript:;" data-p="'+i+'">'+i+'</a></li>';
      }
      $('#pager').html(ph);
      updateBatchTip();
    }, 'json');
  }

  function postAct(act, data, okMsg) {
    var ii = layer.load(2);
    $.post('app_list.php?act=' + act, data, function (res) {
      layer.close(ii);
      if (!res || res.code !== 0) return layer.msg((res && (res.msg || res.message)) || '失败');
      layer.msg(okMsg || res.msg || '完成');
      load();
    }, 'json');
  }

  $('#statRow').on('click', '.stat', function () {
    $('.stat').removeClass('active');
    $(this).addClass('active');
    filter = $(this).data('filter');
    page = 1;
    load();
  });
  $('#btnSearch').on('click', function () { page = 1; load(); });
  $('#btnRefresh').on('click', load);
  $('#kw').on('keydown', function (e) { if (e.keyCode === 13) { page = 1; load(); } });
  $('#pager').on('click', 'a', function () { page = Number($(this).data('p')) || 1; load(); });

  $('#listBox').on('change', '.sel', function () {
    selected[$(this).data('id')] = this.checked;
    updateBatchTip();
  });
  $('#listBox').on('click', '.btn-off', function () {
    var id = $(this).data('id');
    layer.confirm('下架后用户打开下载页将提示不可用，确认？', function (idx) {
      layer.close(idx); postAct('offline', {id: id}, '已下架');
    });
  });
  $('#listBox').on('click', '.btn-on', function () { postAct('online', {id: $(this).data('id')}, '已上架'); });
  $('#listBox').on('click', '.btn-retry', function () {
    var id = $(this).data('id');
    layer.confirm('将重新排队打包（本地模式）或标记制作中，确认？', function (idx) {
      layer.close(idx); postAct('retry', {id: id}, '已提交');
    });
  });
  $('#listBox').on('click', '.btn-del', function () {
    var id = $(this).data('id');
    layer.confirm('删除后不可恢复（含本地 APK 文件尝试清理），确认删除 #' + id + '？', function (idx) {
      layer.close(idx);
      postAct('del', {id: id}, '已删除');
      delete selected[id];
    });
  });

  var editCache = {};
  $('#listBox').on('click', '.btn-edit', function () {
    var id = $(this).data('id');
    // 从当前列表 DOM 不够完整，重新拉一页找
    $.post('app_list.php?act=list', { kw: String(id), filter: 'all', page: 1, page_size: 5 }, function (res) {
      var row = null;
      (res.data && res.data.list || []).forEach(function (r) { if (Number(r.id) === Number(id)) row = r; });
      if (!row) return layer.msg('记录不存在');
      editCache = row;
      $('#editId').text(row.id);
      $('#editName').val(row.name || '');
      $('#editAndroid').val(row.android_url || '');
      $('#editIos').val(row.ios_url || '');
      $('#editModal').modal('show');
    }, 'json');
  });
  $('#btnSaveEdit').on('click', function () {
    postAct('save_links', {
      id: editCache.id,
      name: $('#editName').val(),
      android_url: $('#editAndroid').val(),
      ios_url: $('#editIos').val()
    }, '已保存');
    $('#editModal').modal('hide');
  });

  function batch(op, tip) {
    var ids = selectedIds();
    if (!ids.length) return layer.msg('请先勾选');
    layer.confirm(tip, function (idx) {
      layer.close(idx);
      postAct('batch', { ids: ids, op: op }, '批量完成');
      selected = {};
      updateBatchTip();
    });
  }
  $('#btnBatchOff').on('click', function () { batch('offline', '批量下架所选？'); });
  $('#btnBatchOn').on('click', function () { batch('online', '批量上架所选？（无下载地址的会跳过）'); });
  $('#btnBatchDel').on('click', function () { batch('del', '批量删除所选？不可恢复'); });

  load();
  setInterval(function () {
    // 有生成中时自动刷新
    if (filter === 'building' || filter === 'all' || filter === 'queued') load();
  }, 15000);
})();
</script>
</body>
</html>
