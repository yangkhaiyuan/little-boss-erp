 <?php
use eagle\modules\util\helpers\TranslateHelper;
use yii\helpers\Url;
use eagle\widgets\ESort;
$sort = new ESort(['isAjax'=>true,'attributes'=>['main_image','name','parent_sku']]);
$this->registerJsFile( \Yii::getAlias('@web') . '/js/project/listing/wish_data.js',['depends' => ['yii\web\JqueryAsset','yii\bootstrap\BootstrapAsset']]);
$this->registerJsFile( \Yii::getAlias('@web') . '/js/project/listing/wish_create.js',['depends' => ['yii\web\JqueryAsset','yii\jui\JuiAsset','yii\bootstrap\BootstrapPluginAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."js/project/listing/fanben_capture.js", ['depends' => ['yii\jui\JuiAsset','yii\bootstrap\BootstrapPluginAsset']]);
$this->registerCssFile(\Yii::getAlias('@web')."/css/batchImagesUploader.css");	
$this->registerCssFile(\Yii::getAlias('@web')."/css/listing/wish_list.css");
$this->registerJsFile(\Yii::getAlias('@web')."/js/ajaxfileupload.js", ['depends'=> ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/listing/wish_batchImagesUploader.js", ['depends'=> ['yii\bootstrap\BootstrapPluginAsset']]);
$wishdata['wish_fanben_id'] = empty($WishFanbenModel->id)?"":$WishFanbenModel->id;
$wishdata['site_id'] = empty($WishFanbenModel->site_id)?"":$WishFanbenModel->site_id;
$wishdata['wish_product_id'] = empty($WishFanbenModel->wish_product_id)?"":$WishFanbenModel->wish_product_id;
$wishdata['wish_parent_sku'] = empty($WishFanbenModel->parent_sku)?"":$WishFanbenModel->parent_sku;
$wishdata['wish_product_name'] =empty($WishFanbenModel->name)?"":htmlspecialchars($WishFanbenModel->name);
$wishdata['wish_product_tags'] =empty($WishFanbenModel->tags)?"":$WishFanbenModel->tags;
$wishdata['wish_product_upc'] =empty($WishFanbenModel->upc)?"":$WishFanbenModel->upc;
$wishdata['wish_product_brand'] =empty($WishFanbenModel->brand)?"":$WishFanbenModel->brand;
$wishdata['wish_product_landing_page_url'] =empty($WishFanbenModel->landing_page_url)?"":$WishFanbenModel->landing_page_url;
$wishdata['wish_product_description'] =empty($WishFanbenModel->description)?"":$WishFanbenModel->description;
$wishdata['wish_product_shipping_time']  = empty($WishFanbenModel->shipping_time)?"":$WishFanbenModel->shipping_time;
$wishdata['wish_product_msrp'] =empty($WishFanbenModel->msrp)?"0":$WishFanbenModel->msrp;
$wishdata['wish_product_price'] = empty($WishFanbenModel->price)?"0":$WishFanbenModel->price;
$wishdata['wish_product_shipping'] = empty($WishFanbenModel->shipping)?"0":$WishFanbenModel->shipping;
$wishdata['wish_product_count'] = empty($WishFanbenModel->inventory)?0:$WishFanbenModel->inventory;
//wish_fanben_variance
$wishdata['main_image'] =empty($WishFanbenModel->main_image)?"":$WishFanbenModel->main_image;
$wishdata['extra_image_1'] =empty($WishFanbenModel->extra_image_1)?"":$WishFanbenModel->extra_image_1;
$wishdata['extra_image_2'] =empty($WishFanbenModel->extra_image_2)?"":$WishFanbenModel->extra_image_2;
$wishdata['extra_image_3'] =empty($WishFanbenModel->extra_image_3)?"":$WishFanbenModel->extra_image_3;
$wishdata['extra_image_4'] =empty($WishFanbenModel->extra_image_4)?"":$WishFanbenModel->extra_image_4;
$wishdata['extra_image_5'] =empty($WishFanbenModel->extra_image_5)?"":$WishFanbenModel->extra_image_5;
$wishdata['extra_image_6'] =empty($WishFanbenModel->extra_image_6)?"":$WishFanbenModel->extra_image_6;
$wishdata['extra_image_7'] =empty($WishFanbenModel->extra_image_7)?"":$WishFanbenModel->extra_image_7;
$wishdata['extra_image_8'] =empty($WishFanbenModel->extra_image_8)?"":$WishFanbenModel->extra_image_8;
$wishdata['extra_image_9'] =empty($WishFanbenModel->extra_image_9)?"":$WishFanbenModel->extra_image_9;
$wishdata['extra_image_10'] =empty($WishFanbenModel->extra_image_10)?"":$WishFanbenModel->extra_image_10;

$shipping_time = array('5-10','7-14','10-15','14-21','21-28');
if(!in_array($wishdata['wish_product_shipping_time'],$shipping_time) && $wishdata['wish_product_shipping_time']){
	$wishdata['shipping_short_time']= explode('-',$wishdata['wish_product_shipping_time'])[0];
	$wishdata['shipping_long_time']= explode('-',$wishdata['wish_product_shipping_time'])[1];
	$wishdata['wish_product_shipping_time'] ='other';
	// var_dump($wishdata['shipping_long_time']);
}

$ImageList = [];
$row['thumbnail'] = $wishdata['main_image'];
$row['original'] = $wishdata['main_image'];
$ImageList[] = $row;

// ???????????? ?????? 
for($i=1;$i<=10;$i++){
	$row['thumbnail'] = $wishdata['extra_image_'.$i] ;
	$row['original'] = $wishdata['extra_image_'.$i];
	$ImageList[] = $row;
}
if(!empty($WishFanbenModel->main_image)){
	$this->registerJs("fanbenCapture.existingMainImage=".json_encode($WishFanbenModel->main_image).";", \yii\web\View::POS_READY);
}
if (! empty($ImageList)){
	$this->registerJs("fanbenCapture.existingImages=".json_encode($ImageList).";" , \yii\web\View::POS_READY);
	$this->registerJs("fanbenCapture.initPhotosSetting();" , \yii\web\View::POS_READY);
}

// var_dump(json_encode($WishFanbenModel->main_image));

//variance ?????? ?????? 
if (!empty($WishFanbenVarianceData)){
	$this->registerJs("fanbenCapture.existingSizeType=".json_encode(json_decode($WishFanbenModel->addinfo)->size).";", \yii\web\View::POS_READY);
	$this->registerJs("fanbenCapture.existtingVarianceList=".json_encode($WishFanbenVarianceData).";" , \yii\web\View::POS_READY);
	$this->registerJs("fanbenCapture.fillVarianceData();" , \yii\web\View::POS_READY);
	
}

// var_dump($WishFanbenModel);
// var_dump($ImageList);
// echo '<pre>';
// print_r($wishdata);
// print_r($WishFanbenVarianceData);
// exit;
?>
<?php
	$active = $lb_status == 1 ? '?????????':'????????????';
	echo $this->render('//layouts/new/left_menu_2',[
		'menu'=> \eagle\modules\listing\helpers\WishHelper::getMenu(),
		'active'=>$active
	]);
?>	
<div class="panel panel-default">
	<div class="panel-body col-xs-11 mbottom20">
		<form id="fanben-capture" name="fanben_capture" method="post"  class="block">
			<input type="hidden" name="wish_fanben_id" value="<?=$wishdata['wish_fanben_id']?>">
			<div class="row">
				<?php if(!isset($WishFanbenModel->id)): ?>
					<div class="col-xs-2 mTop10">
						<button type="button" class="btn btn-primary btn-block" onclick="cite()">????????????</button>
					</div>
				<?php endif;?>
			</div>
			<div class="col-xs-12 mTop20 wish_product_baseinfo">
				 <div class="border" style="height:30px;">
				 	<a name="wish_product_baseinfo"><span class="title-center">??????????????????</span></a>
				 </div>
			</div>
			<div class="col-xs-10 col-xs-offset-1">
				<div class="form-group">
					<div class="row mTop20 wish_site_id_list">
						<label for="wish_site_id" class="col-xs-2 control-label text-right">
							????????????:						
						</label>
						<div class="col-xs-4">	
							<select class="wish_site_id col-xs-10  form-control" name="site_id" style="margin-top:0;height:30px;">
								<?php if(count($store_name) > 1): ?>	
									<option value="">??????wish??????</option>
								<?php endif;?>
								<?php if(isset($store_name)): ?>
									<?php foreach($store_name as $key => $val): ?>
											<option value="<?=$val['site_id']?>" <?php if(isset($WishFanbenModel)):?><?php if($WishFanbenModel['site_id']== $val['site_id']):?> selected<?php endif;?><?php elseif(count($store_name) == 1): ?>selected<?php endif;?>><?=$val['store_name']?></option>
									<?php endforeach;?>
								<?php endif;?>
							</select>
						</div>
					</div>
				</div>
				<div class="form-group">	
					<div class="row mTop20 product_title">
						<label for="wish_product_name" class="col-xs-2 control-label text-right mTop10">
							<i class="red">* </i>????????????:
							<span qtipkey="wish_edit_title_tip"></span>
						</label>
						<div class="col-xs-8">
							<input class="col-xs-10 form-control" id="wish_product_name" type="text" name="name" value="<?=$wishdata['wish_product_name']?>">
						</div>
					</div>
					<div class="row mTop10">
						<div class="col-xs-10 col-xs-offset-2 example">
							<span>???: Men's Dress Casual Shirt Navy<span>
						</div>
					</div>
				</div>
				<div class="form-group">	
					<div class="row mTop10">
						<label for="wish_product_tag" class="col-xs-2 control-label text-right mTop5">
							<i class="red">* </i>????????????:
						</label>
						<div class="col-xs-8" id="goods_tags">
							<input class="col-xs-10 form-control ui-autocomplete-input noBorder" placeholder="???????????????????????? ?????? ??? ?????? ????????????" id="wish_product_tags" type="text" name="tags" value="<?=$wishdata['wish_product_tags'] ?>" autocomplete="off">
						</div>
						<span class="col-xs-1"><i style="color:#FF9600" class="tags_num">0</i>/10</span>
					</div>
					<div class="row mTop10">
						<label for="wish_product_tag" class="col-xs-2 control-label text-nowrap"></label>
						<div class="col-xs-10 col-xs-offset-2 example">
							<span > ??????Women,Women Fanshion???????????????2????????????????????????10??? <span>
						</div>
					</div>
				</div>
				<div class="form-group">
					<div class="row mTop10">
						<label for="wish_product_description" class="col-xs-2 control-label text-right mTop10">
							<i class="red">* </i>????????????:
						</label>
						<div class="col-xs-8">
							<textarea class="form-control " id="wish_product_description" name="description" rows="6" ><?=$wishdata['wish_product_description']?></textarea>
						</div>
					</div>
				</div>
				<div class="form-group">	
					<div class="row mTop10">
						<label for="wish_product_mainSku" class="col-xs-2 control-label text-right mTop10">
							<i class="red">* </i>???SKU:
						</label>
						<div class="col-xs-4">
							<input class="col-xs-10 form-control" id="wish_product_parentSku" type="text" name="parent_sku" value="<?=$wishdata['wish_parent_sku']?>">
						</div>
						<div class="col-xs-4 sku_tips"></div>
					</div>
				</div>
				<div class="form-group">	
					<div class="row mTop10">
						<label for="wish_product_price" class="col-xs-2 control-label text-right mTop10">
							<i class="red">* </i>??????:
						</label>
						<div class="col-xs-4">
							<div class="input-group">
								<input class="form-control" id="wish_product_price" aria-describedby="money-type" type="text" name="price" value="<?php if(!empty($wishdata['wish_product_price'])):?><?=$wishdata['wish_product_price']?><?php endif;?>">
								<span class="input-group-addon" id="money-type">USD</span>
							</div>
						</div>
					</div>
				</div>
				<div class="form-group">	
					<div class="row mTop20">
						<label for="wish_product_count" class="col-xs-2 control-label text-right mTop10">
							<i class="red">* </i>??????:
						</label>
						<div class="col-xs-4">
							<input class="col-xs-10 form-control" id="wish_product_count" type="text" name="inventory" value="<?php if(!empty($wishdata['wish_product_count'])):?><?=$wishdata['wish_product_count']?><?php endif;?>">
						</div>
					</div>
				</div>
				<div class="form-group">	
					<div class="row mTop10">
						<label for="wish_product_shipping" class="col-xs-2 control-label text-right mTop10">
							<i class="red">* </i>??????:
						</label>
						<div class="col-xs-4">
							<div class="input-group">
								<input class="form-control" id="wish_product_shipping" aria-describedby="money-type" type="text" name="shipping" value="<?php if(!empty($wishdata['wish_product_shipping'])):?><?=$wishdata['wish_product_shipping']?><?php endif;?>">
								<span class="input-group-addon" id="money-type">USD</span>
							</div>
						</div>
					</div>
				</div>
				<div class="form-group">
					<div class="row mTop20">
						<label for="wish_product_shipping_time" class="col-xs-2 control-label text-right">
							<i class="red">* </i>????????????:
						</label>
						<div class="col-xs-7 mLeft30">
							<div class="col-xs-12" id="wish_product_shipping_time">
								<span class="col-xs-2 w100"><input type="radio" name="shipping_time" value="5-10" <?php if($wishdata['wish_product_shipping_time'] == '5-10'): ?> checked="checked" <?php endif;?>/>5-10</span>
								<span class="col-xs-2 w100"><input type="radio" name="shipping_time" value="7-14" <?php if($wishdata['wish_product_shipping_time'] == '7-14'): ?> checked="checked" <?php endif;?> />7-14</span>
								<span class="col-xs-2 w100"><input type="radio" name="shipping_time" value="10-15" <?php if($wishdata['wish_product_shipping_time'] == '10-15'): ?> checked="checked" <?php endif;?> />10-15</span>
								<span class="col-xs-2 w100"><input type="radio" name="shipping_time" value="14-21" <?php if($wishdata['wish_product_shipping_time'] == '14-21'): ?> checked="checked" <?php endif;?>/>14-21</span>
								<span class="col-xs-2 w100"><input type="radio" name="shipping_time" value="21-28" <?php if($wishdata['wish_product_shipping_time'] == '21-28'): ?> checked="checked" <?php endif;?>/>21-28</span>
							</div>
							<div class="col-xs-12 mTop10">
								<span class="col-xs-2"><input type="radio" name="shipping_time" value="other"<?php if($wishdata['wish_product_shipping_time'] =="other"): ?> checked="checked" <?php endif ?>/>??????</span>
								<span class="col-xs-10">
									<input class="col-xs-5" type="text" name="shipping_short_time"  <?php if($wishdata['wish_product_shipping_time'] == "other"): ?> value="<?=$wishdata['shipping_short_time'];?>"<?php else: ?>disabled <?php endif?> placeholder="???????????????"/>
									<span class="col-xs-1">-</span>
									<input class="col-xs-5" type="text" name="shipping_long_time" <?php if($wishdata['wish_product_shipping_time'] == "other"): ?>value="<?=$wishdata['shipping_long_time']; ?>"<?php else: ?>disabled <?php endif?>  placeholder="???????????????"/>
								</span> 
							</div>
						</div>
					</div>
				</div>
				<div class="form-group">	
					<div class="row mTop20">
						<label for="wish_product_sale_price" class="col-xs-2 control-label text-right">
							MSRP??????????????????):
						</label>
						<div class="col-xs-4">
							<div class="input-group">
								<input class="form-control" id="wish_product_sale_price" aria-describedby="money-type" type="text" name="msrp" value="<?php if(!empty($wishdata['wish_product_msrp'])):?><?=$wishdata['wish_product_msrp'];?><?php endif;?>">
								<span class="input-group-addon" id="money-type">USD</span>
							</div>
						</div>
					</div>
				</div>
				<div class="form-group">
					<div class="row mTop20">
						<label for="wish_product_brand" class="col-xs-2 control-label text-right mTop10">
							??????:
						</label>
						<div class="col-xs-4">
							<input class="form-control" id="wish_product_brand" type="text" name="brand" value="<?=$wishdata['wish_product_brand']?>">
						</div>
					</div>
				</div>
				<div class="form-group">	
					<div class="row mTop10">
						<label for="wish_product_upc" class="col-xs-2 control-label text-right">
							UPC?????????????????????):
						</label>
						<div class="col-xs-4">
							<input class="form-control" id="wish_product_upc" type="text" name="upc" value="<?=$wishdata['wish_product_upc']?>">
						</div>
					</div>
				</div>
				<div class="form-group">
					<div class="row mTop10">
						<label for="wish_product_ladding_page_url" class="col-xs-2 control-label text-right">
							Ladding Page URL:
						</label>
						<div class="col-xs-8">
							<input class="form-control" id="wish_product_ladding_page_url" type="text" name="landing_page_url" value="<?=$wishdata['wish_product_landing_page_url'] ?>">
						</div>
					</div>
				</div>
			</div>
			<div class="col-xs-12 mTop50 wish_product_image">
				<div class="border" style="height:30px;">
				 	<a name="wish_product_image"><span class="title-center">??????????????????</span></a>	
				</div>
			</div>
			 <!--????????????-->
			<div class="col-xs-12">
			 	<div class="col-xs-1 text-right minW120 mTop35">
			 		<label><i style="color:red;">*</i> ????????????:</label>
			 	</div>
			 	<div class="col-xs-10">
		 			<div role="image-uploader-container">
						<div class="col-xs-12" style="margin-bottom:20px;">
							<button type="button" class="btn btn-default col-xs-2" id="btn-uploader" style="margin-right:20px;"><span class="glyphicon glyphicon-folder-open"></span> <?= TranslateHelper::t('?????????????????????'); ?></button>
							<button type="button" class="btn btn-default col-xs-2" id="btn-upload-from-lib" data-toggle="modal" data-target="#addImagesBox" ><span class="glyphicon glyphicon-globe"></span> <?= TranslateHelper::t('?????????URL??????'); ?>
							</button>
							<span class="col-xs-3" style="color:red;font-size:12px;margin-top:10px;">(???????????????????????????)</span>
						</div>
					</div>
			 	</div>
			</div>
			<div class="col-xs-12 mTop50 wish_product_variance">
				 <div class="border" style="height:30px;">
				 	<a name="wish_product_variance"><span class="title-center">??????????????????</span></a>
				 </div>
			</div>
			 <!--????????????-->
			<div class="col-xs-12 mTop50">
			 	<div class="col-xs-12 text-right mTop30" style="margin-left:-15px;">
			 		<div class="col-xs-1">
			 			<label>??????:</label>
			 		</div>
		 			<div class="form-group col-xs-11">
	 					<div class="input-group col-xs-4" style="margin-top:-3px;">
	 						<input type="text" class="form-control ui-autocomplete-input" id="otherColor" name="otherColor"  placeholder="???????????????????????????" autocomplete="off">
		 					<span class="input-group-btn">	
	 							<button class="btn btn-success minW120 colorAdd"  type="button" style="height:30px">??????</button>
	 						</span>
	 					</div>
		 			</div>
			 	</div>
			 	<div class="col-xs-12">
			 		<div class="goods_select" style="height:auto;">

			 			<div class="form-group mTop20" id="goodsColor" style="margin-bottom:30px;position:relative;min-height:150px;">
			 			</div>
			 		</div>
			 	</div>
			</div>
			 <!--????????????-->
			<div class="col-xs-12 mTop50">
			 	<div class="col-xs-1 text-right mTop25">
			 		<label>??????:</label>
			 	</div>
			 	<div class="col-xs-11">
				 	<div id="mytab" style="height:auto;">
				 		<div class="form-group mTop20" style="min-height:32px;">
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20 noCss" rel="Man">?????????<span class="size_tips"></span></a>
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="Women">?????????</a>
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="Child">?????????</a>
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="ChildShoes">???/??????</a>
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="AdditionalApparelSizes">??????????????????</a>
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="Numbers">?????????</a>
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="Bras">?????????</a>
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="Macbooks">????????????</a>
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="Smartphones">????????????</a>	
						</div>
				 		<div class="form-group" style="min-height:32px;">
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="Gaming">?????????</a>
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="Headphones">?????????</a>
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="Bedding">????????????</a>
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="Memory">????????????</a>
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="Area">???????????????</a>
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="Length">?????????</a>
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="Volume">?????????</a>
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="Voltage">?????????</a>
							<a href="javascript:void(0);" class="col-xs-1 btn  sizeBtn mRight20" rel="Weight">?????????</a>
						</div>
						<div class="form-group" style="min-height:32px;">
							<a href="javascript:void(0);" class="col-xs-1 btn sizeBtn mRight20" rel="Shapes">?????????</a>
							<a href="javascript:void(0);" class="col-xs-1 btn sizeBtn mRight20" rel="Others">?????????</a>
						</div>
						<div id="commoditySize" class="form-group tab-content" style="margin:20px 0 20px -30px;">
							<div class="col-xs-12" id="size_content" style="min-height:60px;">
								<div class="col-xs-12" id="sizeAdd" style="height:auto;"></div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!--??????????????????-->
			<div class="col-xs-12">
				
				<div class="col-xs-11 col-xs-offset-1">
					<div class="mTop10 goodsList" style="min-height:105px;margin-left:-20px;">
						<input type="hidden" name="opt_method" value="">
						<div class="form-group">
							<div class="col-xs-12">
								<a class="btn btn-success col-xs-1 minW120" onclick="AddVariance();">??????????????????</a>
								<span class="col-xs-6" style="font-size:12px;color:red;margin-top:10px;">(* ??????????????????????????????SKU?????????wish??????????????????)<span>
							</div>
							<div class="col-xs-12 mTop10">
								<table class="table table-striped">
									<thead>
										<tr class="bgColor1">
											<th class="col-xs-1">??????</th>
											<th class="col-xs-1">??????</th>
											<th class="col-xs-3">SKU</th>
											<th class="">??????(USD)<a  class="create_btn" onclick="createNum('price');">[????????????]</a></th>
											<th class="col-xs-1">??????<a class="create_btn" onclick="createNum('inventory');">[????????????]</a></th>
											<th class="">??????(USD)<a class="create_btn" onclick="createNum('shipping');">[????????????]</a></th>
											<th class="col-xs-1">??????</th>
											<th class="col-xs-1">??????</th>
										</tr>
									</thead>
									<tbody id="goodsList">
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
				
			</div> 
		</form>
	</div>
	<div class="left-panel col-xs-1" id="floatnav">
		<div class="left_panel_first"></div>
		<p>
			<a onclick="goto('wish_product_baseinfo')" style="color: rgb(51,51,51)">????????????</a>
		</p>
		<p>
			<a onclick="goto('wish_product_image')" style="color: rgb(51,51,51);">????????????</a>	
		</p>
		<p>
			<a onclick="goto('wish_product_variance')" style="color: rgb(51,51,51);">????????????</a>
		</p>
		<div href="#wish_product_variance" class="left_panel_last"></div>
	</div>
	<div class="col-xs-12 bottom_btn p0">
		<div class="col-xs-4 col-xs-offset-4">	
			<div class="col-xs-4 minW120">
				<button type="button" class="btn btn-warning btn-block mTop10" onclick="checkReplica()">Wish????????????</button>
			</div>
			<div class="col-xs-4 minW120">
				<button type="button" class="btn btn-success btn-block mTop10" onclick="javascript:return save(1)">??????</button>
			</div>
			<div class="col-xs-4 minW120">
				<button  type="button" class="btn btn-success btn-block mTop10" onclick="javascript:return save(2)">???????????????</button>
			</div>
		</div>
	</div>
	<div class="modal fade bs-modal-lg" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel" id="common_modal">
		<div class="modal-dialog modal-sm">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="hide();"><span aria-hidden="true">&times;</span></button>
				</div>
				<div class="modal-body">
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">??????</button>
					<button type="button" class="btn btn-primary" onclick="hide();">??????</button>
				</div>
			</div>
		</div>
	</div> 
	<div class="modal fade bs-modal-lg"  id="cite_modal" tabindex="2">
		<div class="modal-dialog modal-lg">
			<div class="modal-content" style="border:0;">
				<div class="modal-header" style="background-color:#364655;">
					<button type="button" class="close" style="color:white;"???data-dismiss="modal" aria-label="Close" onclick="hide();"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" style="color:white;">????????????</h4>
				</div>
				<div class="modal-body">
					<div class="cite_goods_box">
						<div class="container">
							<div class="row">
								<div class="col-xs-2 wish_id_list">
									<select class="wish_cite_site_id col-xs-12 p0 form-control" name="site_id" style="margin-top:0;">
									</select>
								</div>
								<div class="col-xs-4">
									<div class="input-group">
										<div class="input-group-btn cite_goods_menu">
											<select name="select_status" class="btn" style="border:1px solid #B9D6E8;height:30px;width:90px;">
												<option value="parent_sku">SKU</option>
												<option value="name">??????</option>
											</select>
										</div>
										<input type="text" class="form-control" style="border-left:0;" name="search_key" autocomplete="off" value="">
										<span class="input-group-btn">
											<button class="btn btn-success" style="height:30px;width:50px;font-size:16px;" type="button" onclick="AjaxCite();">
												<span class="glyphicon glyphicon-search"></span>
											</button>
										</span>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">??????</button>
					<button type="button" class="btn btn-primary" data-dismiss="modal" onclick="fillCiteGoodInfo()">??????</button>
				</div>
			</div>
		</div>
	</div>
</div>


