<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = file_get_contents($root . '/deobfuscated/recovered/admin/article.php');
if ($src === false) {
    throw new RuntimeException('missing recovered article');
}

foreach ([
    "\$title = '文章列表'",
    "adminpermission('article', 1)",
    "\$my === 'add'",
    "\$my === 'edit_submit'",
    "\$my === 'delete'",
    'INSERT INTO `pre_article`',
    '文章标题已存在',
    'kindeditor-all-min.js',
    'assets/js/article.js?ver=',
    'set.php?mod=rewrite',
] as $needle) {
    if (strpos($src, $needle) === false) {
        throw new RuntimeException('missing fragment: ' . $needle);
    }
}
if (substr_count($src, 'goto ') !== 0) {
    throw new RuntimeException('recovered article still contains goto');
}
echo "article recovery checks passed\n";
