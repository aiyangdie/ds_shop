<?php
/**
 * Recovered admin/account.php (goto-flattened original).
 * Not wired into the live admin path until a side-by-side check passes.
 */
include '../includes/common.php';
$title = '员工管理';
include './head.php';
if ($islogin == 1) {
} else {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

adminpermission('account', 1);

echo '    <div class="col-sm-12 col-md-10 center-block" style="float: none;">' . "\r\n";

$permKeys = [
    'order' => '订单管理',
    'refund' => '订单退款',
    'shop' => '商品管理',
    'price' => '加价模板',
    'faka' => '发卡管理',
    'site' => '分站/用户管理',
    'tixian' => '余额提现',
    'workorder' => '工单管理',
    'message' => '站内通知',
    'article' => '文章管理',
    'shequ' => '对接管理',
    'set' => '系统设置',
    'account' => '员工管理',
];

$my = isset($_GET['my']) ? $_GET['my'] : null;

if ($my === 'add') {
    echo '<div class="block">
<div class="block-title"><h3 class="panel-title">添加一个用户</h3></div>
<div class="">
  <form action="./account.php?my=add_submit" method="post" class="form" role="form">
    <div class="form-group">
	  <label>用户名</label><br/>
	  <input type="text" name="username" value="" class="form-control"/>
	</div>
	<div class="form-group">
	  <label>密码</label><br/>
	  <input type="text" name="password" value="" class="form-control"/>
	</div>
	<div class="form-group">
	  <label>选择可用的功能模块</label><br/>
';
    foreach ($permKeys as $key => $label) {
        echo "\t<label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"permission[]\" value=\"{$key}\"> {$label}</label>\r\n";
    }
    echo '	</div>
	<div class="form-group">
	  <label>是否激活</label><br/>
	  <select class="form-control" name="active"><option value="1">是</option><option value="0">否</option></select>
	</div>
	  <input type="submit" name="submit" value="添加" class="btn btn-primary btn-block"/>
  </form>
  <br/><a href="./account.php">>>返回员工列表</a>
</div>';
} elseif ($my === 'edit') {
    $id = intval($_GET['id']);
    $row = $DB->getRow("select * from pre_account where id='{$id}' limit 1");
    if (!$row) {
        showmsg('当前记录不存在！', 3);
    }
    $checked = $row['permission'] !== '' ? explode(',', $row['permission']) : [];
    echo '<div class="block">
<div class="block-title"><h3 class="panel-title">修改员工账号</h3></div>
  <form action="./account.php?my=edit_submit&id=' . $id . '" method="post" class="form" role="form">
    <div class="form-group">
	  <label>用户名</label><br/>
	  <input type="text" name="username" value="' . htmlspecialchars((string) $row['username']) . '" class="form-control"/>
	</div>
	<div class="form-group">
	  <label>密码</label><br/>
	  <input type="text" name="password" value="' . htmlspecialchars((string) $row['password']) . '" class="form-control"/>
	</div>
	<div class="form-group">
	  <label>选择可用的功能模块</label><br/>
';
    foreach ($permKeys as $key => $label) {
        $mark = in_array($key, $checked, true) ? 'checked' : '';
        echo "\t<label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"permission[]\" value=\"{$key}\" {$mark}> {$label}</label>\r\n";
    }
    echo '	</div>
	<div class="form-group">
	  <label>是否激活</label><br/>
	  <select class="form-control" name="active" default="' . htmlspecialchars((string) $row['active']) . '"><option value="1">是</option><option value="0">否</option></select>
	</div>
	  <input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/>
  </form>
  <br/><a href="./account.php">>>返回员工列表</a>
</div>
<script>
var items = $("select[default]");
for (i = 0; i < items.length; i++) {
	$(items[i]).val($(items[i]).attr("default")||0);
}
</script>
';
} elseif ($my === 'add_submit') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $permission = (!empty($_POST['permission']) && is_array($_POST['permission']))
        ? implode(',', $_POST['permission'])
        : '';
    if ($username === '' || $password === '') {
        showmsg('保存错误,请确保每项都不为空!', 3);
    } elseif ($username === $conf['admin_user'] || $DB->getRow("select * from pre_account where username='{$username}' limit 1")) {
        showmsg('用户名已存在！', 3);
    } else {
        $sql = 'insert into `pre_account` (`username`,`password`,`permission`,`addtime`,`active`) values (:username, :password, :permission, NOW(), \'1\')';
        $ok = $DB->exec($sql, [
            ':username' => $username,
            ':password' => $password,
            ':permission' => $permission,
        ]);
        if ($ok) {
            showmsg('添加用户成功！<br/><br/><a href="./account.php">>>返回员工列表</a>', 1);
        } else {
            showmsg('添加用户失败！' . $DB->error(), 4);
        }
    }
} elseif ($my === 'edit_submit') {
    $id = intval($_GET['id']);
    if (!$DB->getRow("select * from pre_account where id='{$id}' limit 1")) {
        showmsg('当前记录不存在！', 3);
    }
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $permission = (!empty($_POST['permission']) && is_array($_POST['permission']))
        ? implode(',', $_POST['permission'])
        : '';
    $active = intval($_POST['active']);
    if ($username === '' || $password === '') {
        showmsg('保存错误,请确保每项都不为空!', 3);
    } elseif ($username === $conf['admin_user'] || $DB->getColumn("select id from pre_account where username='{$username}' AND id<>'{$id}' limit 1")) {
        showmsg('用户名已存在！', 3);
    } else {
        $sql = 'update pre_account set username=:username, password=:password, permission=:permission ,active=:active where id=:id';
        $ok = $DB->exec($sql, [
            ':username' => $username,
            ':password' => $password,
            ':permission' => $permission,
            ':active' => $active,
            ':id' => $id,
        ]);
        if ($ok !== false) {
            showmsg('修改用户信息成功！<br/><br/><a href="./account.php">>>返回员工列表</a>', 1);
        } else {
            showmsg('修改用户信息失败！' . $DB->error(), 4);
        }
    }
} elseif ($my === 'delete') {
    $id = intval($_GET['id']);
    if ($DB->exec("DELETE FROM pre_account WHERE id='{$id}'") !== false) {
        showmsg('删除成功！<br/><br/><a href="./account.php">>>返回员工列表</a>', 1);
    } else {
        showmsg('删除失败！' . $DB->error(), 4);
    }
} else {
    $numrows = $DB->getColumn('SELECT count(*) from pre_account');
    $pagesize = 30;
    $pages = max(1, (int) ceil($numrows / $pagesize));
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    if ($page < 1) {
        $page = 1;
    }
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = $pagesize * ($page - 1);
    echo '<div class="block">
<div class="block-title clearfix">
<h2>系统共有 <b>' . $numrows . '</b> 个员工账号</h2>
<a href="./account.php?my=add" class="btn btn-primary"><i class="fa fa-plus"></i>&nbsp;添加一个用户</a>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th>ID</th><th>用户名</th><th>权限</th><th>添加时间/上次登录</th><th>状态</th><th>操作</th></tr></thead>
          <tbody>
';
    $rs = $DB->query("SELECT * FROM pre_account WHERE 1 order by id desc limit {$offset},{$pagesize}");
    while ($res = $rs->fetch()) {
        $status = ((int) $res['active'] === 1)
            ? '<span class="label label-success">正常</span>'
            : '<span class="label label-danger">封禁</span>';
        echo '<tr><td><b>' . $res['id'] . '</b></td><td>' . htmlspecialchars((string) $res['username'])
            . '</td><td>' . htmlspecialchars((string) $res['permission'])
            . '</td><td>' . $res['addtime'] . '<br/>' . $res['lasttime']
            . '</td><td>' . $status
            . '</td><td><a href="./account.php?my=edit&id=' . $res['id']
            . '" class="btn btn-info btn-xs">编辑</a>&nbsp;<a href="./account.php?my=delete&id=' . $res['id']
            . '" class="btn btn-xs btn-danger" onclick="return confirm(\'你确实要删除此员工账号吗？\');">删除</a></td></tr>';
    }
    echo '          </tbody>
        </table>
      </div>
';
    echo '<ul class="pagination">';
    $first = 1;
    $prev = $page - 1;
    $next = $page + 1;
    $last = $pages;
    if ($page > 1) {
        echo '<li><a href="account.php?page=' . $first . '">首页</a></li>';
        echo '<li><a href="account.php?page=' . $prev . '">&laquo;</a></li>';
    } else {
        echo '<li class="disabled"><a>首页</a></li>';
        echo '<li class="disabled"><a>&laquo;</a></li>';
    }
    $start = $page - 10 > 1 ? $page - 10 : 1;
    $end = $page + 10 < $pages ? $page + 10 : $pages;
    for ($i = $start; $i < $page; $i++) {
        echo '<li><a href="account.php?page=' . $i . '">' . $i . '</a></li>';
    }
    echo '<li class="disabled"><a>' . $page . '</a></li>';
    for ($i = $page + 1; $i <= $end; $i++) {
        echo '<li><a href="account.php?page=' . $i . '">' . $i . '</a></li>';
    }
    if ($page < $pages) {
        echo '<li><a href="account.php?page=' . $next . '">&raquo;</a></li>';
        echo '<li><a href="account.php?page=' . $last . '">尾页</a></li>';
    } else {
        echo '<li class="disabled"><a>&raquo;</a></li>';
        echo '<li class="disabled"><a>尾页</a></li>';
    }
    echo '</ul>';
    echo '    </div>
  </div>
';
}
