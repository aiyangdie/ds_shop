<?php
/**
 * Recovered admin/article.php (goto-flattened original).
 * Not wired into the live admin path until a side-by-side check passes.
 */
include '../includes/common.php';
$title = '文章列表';
include './head.php';
if ($islogin == 1) {
} else {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

adminpermission('article', 1);

echo '    <div class="col-sm-12 col-md-10 center-block" style="float: none;">' . "\r\n";

$my = isset($_GET['my']) ? $_GET['my'] : null;
$editorScripts = '<script charset="utf-8" src="../assets/kindeditor/kindeditor-all-min.js"></script>
<script charset="utf-8" src="../assets/kindeditor/zh-CN.js"></script>
<script src="assets/js/editor.js?ver=' . VERSION . '"></script>';

if ($my === 'add') {
    echo '<div class="block">
<div class="block-title"><h3 class="panel-title">添加文章</h3></div>
<div class="">
  <form action="./article.php?my=add_submit" method="post" class="form-horizontal" role="form">
    <div class="form-group">
	  <label class="col-sm-2 control-label">文章标题</label>
	  <div class="col-sm-10"><input type="text" name="title" value="" class="form-control" required/></div>
	</div>
	<div class="form-group">
	  <label class="col-sm-2 control-label">SEO关键词</label>
	  <div class="col-sm-10"><input type="text" name="keywords" value="" class="form-control" placeholder="可留空"/></div>
	</div>
	<div class="form-group">
	  <label class="col-sm-2 control-label">SEO描述</label>
	  <div class="col-sm-10"><textarea id="description" class="form-control" name="description" rows="2" placeholder="可留空"></textarea></div>
	</div>
	<div class="form-group">
	  <label class="col-sm-2 control-label">文章内容</label>
	  <div class="col-sm-10"><textarea id="editor_id" class="form-control" name="content" rows="8" style="width:100%;"></textarea></div>
	</div>
	<div class="form-group">
	  <label class="col-sm-2 control-label">是否置顶</label>
	  <div class="col-sm-10"><select class="form-control" name="top"><option value="0">否</option><option value="1">是</option></select></div>
	</div>
	<div class="form-group">
	  <label class="col-sm-2 control-label">发布时间</label>
	  <div class="col-sm-10"><input type="date" name="addtime" value="' . date('Y-m-d') . '" class="form-control"/></div>
	</div>
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="发布" class="btn btn-primary btn-block"/><br/>
	 </div>
  </form>
  <br/><a href="./article.php">>>返回文章列表</a>
</div>
' . $editorScripts;
} elseif ($my === 'edit') {
    $id = intval($_GET['id']);
    $row = $DB->getRow("select * from pre_article where id='{$id}' limit 1");
    if (!$row) {
        showmsg('当前记录不存在！', 3);
    }
    $addDate = substr((string) $row['addtime'], 0, 10);
    echo '<div class="block">
<div class="block-title"><h3 class="panel-title">修改通知</h3></div>
  <form action="./article.php?my=edit_submit&id=' . $id . '" method="post" class="form-horizontal" role="form">
    <div class="form-group">
	  <label class="col-sm-2 control-label">文章标题</label>
	  <div class="col-sm-10"><input type="text" name="title" value="' . htmlspecialchars((string) $row['title']) . '" class="form-control" required/></div>
	</div>
	<div class="form-group">
	  <label class="col-sm-2 control-label">SEO关键词</label>
	  <div class="col-sm-10"><input type="text" name="keywords" value="' . htmlspecialchars((string) $row['keywords']) . '" class="form-control" placeholder="可留空"/></div>
	</div>
	<div class="form-group">
	  <label class="col-sm-2 control-label">SEO描述</label>
	  <div class="col-sm-10"><textarea id="description" class="form-control" name="description" rows="2" placeholder="可留空">' . htmlspecialchars((string) $row['description']) . '</textarea></div>
	</div>
	<div class="form-group">
	  <label class="col-sm-2 control-label">文章内容</label>
	  <div class="col-sm-10"><textarea id="editor_id" class="form-control" name="content" rows="8" style="width:100%;">' . htmlspecialchars((string) $row['content']) . '</textarea></div>
	</div>
	<div class="form-group">
	  <label class="col-sm-2 control-label">是否置顶</label>
	  <div class="col-sm-10"><select class="form-control" name="top" default="' . htmlspecialchars((string) $row['top']) . '"><option value="0">否</option><option value="1">是</option></select></div>
	</div>
	<div class="form-group">
	  <label class="col-sm-2 control-label">发布时间</label>
	  <div class="col-sm-10"><input type="date" name="addtime" value="' . htmlspecialchars($addDate) . '" class="form-control"/></div>
	</div>
	  <div class="col-sm-offset-2 col-sm-10"><input type="submit" name="submit" value="修改" class="btn btn-primary btn-block"/><br/>
	 </div>
  </form>
  <br/><a href="./article.php">>>返回文章列表</a>
</div>
' . $editorScripts . '
<script>
var items = $("select[default]");
for (i = 0; i < items.length; i++) {
	$(items[i]).val($(items[i]).attr("default")||0);
}
</script>
';
} elseif ($my === 'add_submit') {
    $title = trim($_POST['title']);
    $content = $_POST['content'];
    $keywords = trim($_POST['keywords']);
    $description = trim($_POST['description']);
    $addtime = trim($_POST['addtime']) . ' ' . date('H:i:s');
    $top = intval($_POST['top']);
    if ($title === '' || $content === '') {
        showmsg('保存错误,请确保必填项都不为空!', 3);
    } elseif ($DB->getRow("select * from pre_article where title='{$title}' limit 1")) {
        showmsg('文章标题已存在！', 3);
    } else {
        $sql = 'INSERT INTO `pre_article` (`title`,`content`,`keywords`,`description`,`addtime`,`top`,`active`) VALUES (:title, :content, :keywords, :description, :addtime, :top, :active)';
        $ok = $DB->exec($sql, [
            ':title' => $title,
            ':content' => $content,
            ':keywords' => $keywords,
            ':description' => $description,
            ':addtime' => $addtime,
            ':top' => $top,
            ':active' => 1,
        ]);
        if ($ok) {
            showmsg('添加文章成功！<br/><br/><a href="./article.php">>>返回文章列表</a>', 1);
        } else {
            showmsg('添加文章失败！' . $DB->error(), 4);
        }
    }
} elseif ($my === 'edit_submit') {
    $id = intval($_GET['id']);
    if (!$DB->getRow("select * from pre_article where id='{$id}' limit 1")) {
        showmsg('当前记录不存在！', 3);
    }
    $title = trim($_POST['title']);
    $content = $_POST['content'];
    $keywords = trim($_POST['keywords']);
    $description = trim($_POST['description']);
    $addtime = trim($_POST['addtime']) . ' ' . date('H:i:s');
    $top = intval($_POST['top']);
    if ($title === '' || $content === '') {
        showmsg('保存错误,请确保必填项都不为空!', 3);
    }
    $sql = 'UPDATE pre_article SET title=:title,content=:content,keywords=:keywords,description=:description,addtime=:addtime,top=:top WHERE id=:id';
    $ok = $DB->exec($sql, [
        ':title' => $title,
        ':content' => $content,
        ':keywords' => $keywords,
        ':description' => $description,
        ':addtime' => $addtime,
        ':top' => $top,
        ':id' => $id,
    ]);
    if ($ok !== false) {
        showmsg('修改文章成功！<br/><br/><a href="./article.php">>>返回文章列表</a>', 1);
    } else {
        showmsg('修改文章失败！' . $DB->error(), 4);
    }
} elseif ($my === 'delete') {
    $id = intval($_GET['id']);
    $ok = $DB->exec('DELETE FROM pre_article WHERE id=:id', [':id' => $id]);
    if ($ok !== false) {
        showmsg('删除成功！<br/><br/><a href="./article.php">>>返回文章列表</a>', 1);
    } else {
        showmsg('删除失败！' . $DB->error(), 4);
    }
} else {
    $link = '';
    if (!empty($_GET['kw'])) {
        $kw = daddslashes($_GET['kw']);
        $sql = " title LIKE '%{$kw}%'";
        $numrows = $DB->getColumn("SELECT count(*) from pre_article where{$sql}");
        $link = '&kw=' . urlencode($_GET['kw']);
        $con = $numrows;
    } else {
        $sql = ' 1';
        $numrows = $DB->getColumn('SELECT count(*) from pre_article');
        $con = $numrows;
    }
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
<h2>系统共有 <b>' . $con . '</b> 篇文章</h2>
<form action="article.php" method="GET" class="form-inline">
 <a href="./article.php?my=add" class="btn btn-primary"><i class="fa fa-plus"></i>&nbsp;添加文章</a>
  <div class="form-group">
    <input type="text" class="form-control" name="kw" placeholder="请输入文章标题">
  </div>
  <button type="submit" class="btn btn-info">搜索</button>&nbsp;<a href="./set.php?mod=rewrite" class="btn btn-default"><i class="fa fa-cog"></i>&nbsp;伪静态配置</a>
</form>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th>ID</th><th>文章标题</th><th>发布时间</th><th>浏览量</th><th>状态</th><th>操作</th></tr></thead>
          <tbody>
';
    $rs = $DB->query("SELECT * FROM pre_article WHERE{$sql} order by id desc limit {$offset},{$pagesize}");
    while ($res = $rs->fetch()) {
        $status = ((int) $res['active'] === 1)
            ? '<span class="btn btn-xs btn-success" onclick="setActive(' . $res['id'] . ',0)">显示</span>'
            : '<span class="btn btn-xs btn-warning" onclick="setActive(' . $res['id'] . ',1)">隐藏</span>';
        $topMark = ((int) $res['top'] === 1) ? '[置顶]' : '';
        echo '<tr><td><b>' . $res['id'] . '</b></td><td>' . $topMark . $res['title']
            . '</td><td>' . $res['addtime'] . '</td><td>' . $res['count'] . '</td><td>' . $status
            . '</td><td><a class="btn btn-xs btn-success" href="../?mod=article&id=' . $res['id']
            . '" target="_blank">查看</a>&nbsp;<a href="./article.php?my=edit&id=' . $res['id']
            . '" class="btn btn-info btn-xs">编辑</a>&nbsp;<a href="./article.php?my=delete&id=' . $res['id']
            . '" class="btn btn-xs btn-danger" onclick="return confirm(\'你确实要删除此文章吗？\');">删除</a></td></tr>';
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
        echo '<li><a href="article.php?page=' . $first . $link . '">首页</a></li>';
        echo '<li><a href="article.php?page=' . $prev . $link . '">&laquo;</a></li>';
    } else {
        echo '<li class="disabled"><a>首页</a></li>';
        echo '<li class="disabled"><a>&laquo;</a></li>';
    }
    $start = $page - 10 > 1 ? $page - 10 : 1;
    $end = $page + 10 < $pages ? $page + 10 : $pages;
    for ($i = $start; $i < $page; $i++) {
        echo '<li><a href="article.php?page=' . $i . $link . '">' . $i . '</a></li>';
    }
    echo '<li class="disabled"><a>' . $page . '</a></li>';
    for ($i = $page + 1; $i <= $end; $i++) {
        echo '<li><a href="article.php?page=' . $i . $link . '">' . $i . '</a></li>';
    }
    if ($page < $pages) {
        echo '<li><a href="article.php?page=' . $next . $link . '">&raquo;</a></li>';
        echo '<li><a href="article.php?page=' . $last . $link . '">尾页</a></li>';
    } else {
        echo '<li class="disabled"><a>&raquo;</a></li>';
        echo '<li class="disabled"><a>尾页</a></li>';
    }
    echo '</ul>';
    echo '    </div>
<script src="' . $cdnpublic . 'layer/3.1.1/layer.js"></script>
<script src="assets/js/article.js?ver=' . VERSION . '"></script>
</body>
</html>';
}
