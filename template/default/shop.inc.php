<?php
if(!defined('IN_CRONLITE'))exit();
$classhide = explode(',',$siterow['class']);
?>
<style>
.shop-product-main-image{
	display:block;width:100%;max-width:340px;height:auto;aspect-ratio:16/9;margin:0 auto;
	object-fit:contain;border-radius:12px;border:1px solid #edf0f5;
	box-shadow:0 8px 22px rgba(31,45,61,.10);background:#f7f8fa;
}
.shop-category-grid{margin-left:-7px!important;margin-right:-7px!important}
.shop-category-grid>[class*="col-"]{padding-left:7px!important;padding-right:7px!important;margin-bottom:14px}
.shop-category-card{
	display:block;margin:0!important;padding:8px;background:#fff;border:1px solid #edf0f4;
	border-radius:18px;overflow:hidden;text-decoration:none!important;color:#334155!important;
	box-shadow:0 5px 18px rgba(31,45,61,.06);cursor:pointer;
	transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease;
}
.shop-category-card:hover,.shop-category-card:focus{
	transform:translateY(-3px);border-color:#b9e8ef;box-shadow:0 12px 28px rgba(22,164,180,.14);outline:0;
}
.shop-category-card:active{transform:scale(.985)}
.shop-category-card .category-cover{display:block;width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:14px;background:#f6f8fb}
.shop-category-card .widget-content{padding:9px 2px 1px!important}
.shop-category-card .category-title{display:block;font-size:16px;line-height:22px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.shop-category-card .category-count{margin:2px 0 9px!important;color:#a0aaba!important;font-size:13px;line-height:18px}
#goodType .shop-category-card .widget-content>.shop-category-action{
	display:flex!important;align-items:center;justify-content:center;gap:6px;width:100%;height:36px!important;
	border-radius:12px;background:linear-gradient(135deg,#16b8c8,#08a9bb);color:#fff;
	font-size:14px;font-weight:600;box-shadow:0 5px 12px rgba(8,169,187,.18);
}
.shop-category-card:hover .shop-category-action{background:linear-gradient(135deg,#0eafc0,#0799ac)}
@media (max-width:480px){
	#productImagePanel{margin-bottom:12px!important}
	#productImagePanel h3{font-size:22px}
	.shop-product-main-image{max-width:290px;max-height:170px;border-radius:10px;box-shadow:0 5px 16px rgba(31,45,61,.08)}
	#productThumbs{gap:6px!important;margin-top:10px!important;flex-wrap:nowrap!important;overflow-x:auto;justify-content:flex-start!important;padding:0 4px 3px;scrollbar-width:none}
	#productThumbs::-webkit-scrollbar{display:none}
	#productThumbs .product-thumb{width:54px!important;height:54px!important;min-width:54px;border-radius:9px!important}
	.shop-category-grid{margin-left:-5px!important;margin-right:-5px!important}
	.shop-category-grid>[class*="col-"]{padding-left:5px!important;padding-right:5px!important;margin-bottom:10px}
	.shop-category-card{padding:6px;border-radius:16px;box-shadow:0 4px 14px rgba(31,45,61,.05)}
	.shop-category-card .category-cover{border-radius:13px}
	.shop-category-card .widget-content{padding-top:7px!important}
	.shop-category-card .category-title{font-size:15px;line-height:20px}
	.shop-category-card .category-count{margin-bottom:7px!important;font-size:12px}
	#goodType .shop-category-card .widget-content>.shop-category-action{height:34px!important;border-radius:11px;font-size:13px;box-shadow:none}
}
</style>
<?php
if($conf['ui_shop']>0){
//分类图片宫格
?>
	<div id="goodType" <?php if(isset($_GET['cid'])){?>style="display: none"<?php }?>>
<?php if($conf['ui_shop']==1){?>
	<div class="row shop-category-grid">
<?php
$rs=$DB->query("select * from pre_class where active=1 order by sort asc");
while($row = $rs->fetch()){
	if($is_fenzhan && in_array($row['cid'], $classhide))continue;
	if(!empty($row["shopimg"])){
		$productimg = $row["shopimg"];
	}else{
		$productimg = 'assets/img/Product/default.png';
	}
	if($usershop)$productimg='../'.$productimg;
	$count=$DB->getColumn("SELECT count(*) from pre_tools where cid={$row['cid']} and active=1");
?>
		<div class="col-lg-4 col-xs-6">
			<a class="widget animation-fadeInQuick goodTypeChange onclick shop-category-card" data-id="<?php echo $row["cid"]?>" role="button" tabindex="0" aria-label="进入<?php echo htmlspecialchars($row["name"], ENT_QUOTES, 'UTF-8')?>分类">
				<img class="lazy category-cover" data-original="<?php echo $productimg?>" alt="<?php echo htmlspecialchars($row["name"], ENT_QUOTES, 'UTF-8')?>">
				<div class="widget-content text-center">
					<strong class="category-title"><?php echo $row["name"]?></strong>
					<p class="text-muted category-count">分类<?php echo $count?>个商品</p>
					<span class="shop-category-action">进入选购 <i class="fa fa-angle-right" aria-hidden="true"></i></span>
				</div>
			</a>
		</div>
<?php }?>
	</div>
<?php }elseif($conf['ui_shop']==2){?>
<style type="text/css">
	.table>tbody>tr>td{vertical-align: baseline;}
</style>
	<table class="table table-striped table-borderless table-vcenter table-hover">
         <tbody>
<?php
$rs=$DB->query("select * from pre_class where active=1 order by sort asc");
while($row = $rs->fetch()){
	if($is_fenzhan && in_array($row['cid'], $classhide))continue;
	if(!empty($row["shopimg"])){
		$productimg = $row["shopimg"];
	}else{
		$productimg = 'assets/img/Product/default.png';
	}
	if($usershop)$productimg='../'.$productimg;
	$count=$DB->getColumn("SELECT count(*) from pre_tools where cid={$row['cid']} and active=1");
?>
			<tr class="widget animation-fadeInQuick onclick goodTypeChange" data-id="<?php echo $row["cid"]?>">
                <td class="text-center" style="width: 100px;">
                    <img data-original="<?php echo $productimg?>" width="50" style="height:50px" alt="avatar" class="lazy img-circle img-thumbnail img-thumbnail-avatar">
                </td>
                <td>
                    <h3 class="widget-heading h4"><strong><?php echo $row["name"]?></strong></h3>
					<span class="text-muted">分类<?php echo $count?>个商品</span>
                </td>
                <td class="text-right">
                    <span class="btn btn-rounded btn-info">进入选购 <i class="fa fa-angle-right"></i></span>
                </td>
            </tr>
<?php
}
?>
		   </tbody>
        </table>
<?php }elseif($conf['ui_shop']==3){?>
	<div class="row">
<?php
$rs=$DB->query("select * from pre_class where active=1 order by sort asc");
while($row = $rs->fetch()){
	if($is_fenzhan && in_array($row['cid'], $classhide))continue;
	if(!empty($row["shopimg"])){
		$productimg = $row["shopimg"];
	}else{
		$productimg = 'assets/img/Product/default.png';
	}
	if($usershop)$productimg='../'.$productimg;
?>
		<div class="col-lg-3 col-xs-4" style="padding:0px">
		<div class="thumbnail" style="margin-bottom:3px;width:95%;margin: 2px auto;">
			<a class="widget animation-fadeInQuick goodTypeChange onclick" data-id="<?php echo $row["cid"]?>">
			<center style="margin-top:0;">
				<img class="lazy" data-original="<?php echo $productimg?>" style="height: 88px;">
				<strong style="white-space:nowrap"><?php echo $row["name"]?></strong>
				<span class="btn btn-sm btn-info btn-block">进入选购 <i class="fa fa-angle-right"></i></span>
			</center>
			</a>
		</div>
		</div>
<?php }?>
	</div>
<?php }?>
	</div>
	<div id="goodTypeContent" <?php if(!isset($_GET['cid'])){?>style="display: none"<?php }?>>
		<div id="productImagePanel" style="text-align:center;margin-bottom:18px;">
			<h3 style="margin:0 0 6px"><span id="className"></span></h3>
			<p id="productNameTip" class="text-muted" style="margin:0 0 12px;font-size:13px;">请选择商品查看图片</p>
			<img src="assets/img/Product/noimg.png" id="classImg" class="shop-product-main-image" data-category-image="assets/img/Product/noimg.png" alt="商品图片" decoding="async">
			<div id="productThumbs" style="display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin-top:14px;"></div>
		</div>
		<input type="hidden" name="cid" id="cid" value="0"/>
		<div class="form-group">
			<div class="input-group"><div class="input-group-addon">选择商品</div>
			<select name="tid" id="tid" class="form-control" onchange="getPoint();"><option value="0">请选择商品</option></select>
		</div></div>
		<div class="form-group" id="display_price" style="display:none;">
			<div class="input-group"><div class="input-group-addon">商品价格</div>
			<input type="text" name="need" id="need" class="form-control" style="center;color:#4169E1;font-weight:bold" disabled/>
		</div></div>
		<div class="form-group" id="display_left" style="display:none;">
			<div class="input-group"><div class="input-group-addon">库存数量</div>
			<input type="text" name="leftcount" id="leftcount" class="form-control" disabled/>
		</div></div>
		<div class="form-group" id="display_num" style="display:none;">
			<div class="input-group">
			<div class="input-group-addon">下单份数</div>
			<span class="input-group-btn"><input id="num_min" type="button" class="btn btn-info" style="border-radius: 0px;" value="━"></span>
			<input id="num" name="num" class="form-control" type="number" min="1" value="1"/>
			<span class="input-group-btn"><input id="num_add" type="button" class="btn btn-info" style="border-radius: 0px;" value="✚"></span>
		</div></div>
		<div id="inputsname"></div>
		<div id="alert_frame" class="alert alert-success animated rubberBand" style="display:none;background: linear-gradient(to right,#71D7A2,#5ED1D7);font-weight: bold;color:white;"></div>
		<?php if($conf['shoppingcart']==1){?>
		<div class="btn-group btn-group-justified form-group">
			<a class="btn btn-block btn-success" type="button" id="submit_cart_shop">加入购物车</a>
			<a type="submit" id="submit_buy" class="btn btn-block btn-primary">立即购买</a>
		</div>
		<?php }else{?>
		<div class="form-group">
			<input type="submit" id="submit_buy" class="btn btn-primary btn-block" value="立即购买">
		</div>
		<?php }?>
		<div class="form-group"><button type="button" class="btn btn-default btn-block btn-sm backType">返回重选分类</button></div>
	</div>
	<ul class="layui-fixbar" id="alert_cart" style="display:none;">
	  <li class="layui-icon" style="background-color:#3e4425db" onclick="openCart()"><i class="fa fa-shopping-cart"></i><div class="nav-counter" id="cart_count"></div></li>
	</ul>
<?php
}else{
//经典模式
$rs=$DB->query("SELECT * FROM pre_class WHERE active=1 order by sort asc");
$select='<option value="0">请选择分类</option>';
$select_count=0;
while($res = $rs->fetch()){
	if($is_fenzhan && in_array($res['cid'], $classhide))continue;
	$select_count++;
	$select.='<option value="'.$res['cid'].'">'.$res['name'].'</option>';
}
if($select_count==0)$hideclass = true;
else $hideclass = false;
?>
		<div id="goodTypeContents">
			<?php echo $conf['alert']?>
			<div id="productImagePanel" style="text-align:center;margin-bottom:14px;">
				<img src="assets/img/Product/noimg.png" id="classImg" class="shop-product-main-image" data-category-image="assets/img/Product/noimg.png" alt="商品图片" decoding="async">
				<div id="productThumbs" style="display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin-top:10px;"></div>
			</div>
			<?php if($conf['search_open']==1){?>
			<div class="form-group" id="display_searchBar">
				<div class="input-group"><div class="input-group-addon">搜索商品</div>
				<input type="text" id="searchkw" class="form-control" placeholder="搜索商品" onkeydown="if(event.keyCode==13){$('#doSearch').click()}"/>
				<div class="input-group-addon"><span class="glyphicon glyphicon-search onclick" title="搜索" id="doSearch"></span></div>
			</div></div>
			<?php }?>
			<div class="form-group" id="display_selectclass"<?php if($hideclass){?> style="display:none;"<?php }?>>
				<div class="input-group"><div class="input-group-addon">选择分类</div>
				<select name="tid" id="cid" class="form-control"><?php echo $select?></select>
			</div></div>
			<div class="form-group">
				<div class="input-group"><div class="input-group-addon">选择商品</div>
				<select name="tid" id="tid" class="form-control" onchange="getPoint();"><option value="0">请选择商品</option></select>
			</div></div>
			<div class="form-group" id="display_price" style="display:none;center;color:#4169E1;font-weight:bold">
				<div class="input-group"><div class="input-group-addon">商品价格</div>
				<input type="text" name="need" id="need" class="form-control" style="center;color:#4169E1;font-weight:bold" disabled/>
			</div></div>
			<div class="form-group" id="display_left" style="display:none;">
				<div class="input-group"><div class="input-group-addon">库存数量</div>
				<input type="text" name="leftcount" id="leftcount" class="form-control" disabled/>
			</div></div>
			<div class="form-group" id="display_num" style="display:none;">
                <div class="input-group">
                <div class="input-group-addon">下单份数</div>
                <span class="input-group-btn"><input id="num_min" type="button" class="btn btn-info" style="border-radius: 0px;" value="━"></span>
				<input id="num" name="num" class="form-control" type="number" min="1" value="1"/>
				<span class="input-group-btn"><input id="num_add" type="button" class="btn btn-info" style="border-radius: 0px;" value="✚"></span>
			</div></div>
			<div id="inputsname"></div>
			<div id="alert_frame" class="alert alert-success animated rubberBand" style="display:none;background: linear-gradient(to right,#71D7A2,#5ED1D7);font-weight: bold;color:white;"></div>
			<?php if($conf['shoppingcart']==1){?>
			<div class="btn-group btn-group-justified form-group">
			    <a class="btn btn-block btn-success" type="button" id="submit_cart_shop">加入购物车</a>
				<a type="submit" id="submit_buy" class="btn btn-block btn-primary">立即购买</a>
            </div>
			<?php }else{?>
			<div class="form-group">
				<input type="submit" id="submit_buy" class="btn btn-primary btn-block" value="立即购买">
			</div>
			<?php }?>
			<div class="panel-body border-t" id="alert_cart" style="display:none;"><i class="fa fa-shopping-cart"></i>&nbsp;当前购物车已添加<b id="cart_count">0</b>个商品<a class="btn btn-xs btn-danger pull-right" href="javascript:openCart()">购物车列表</a></div>
		</div>
<?php } ?>
