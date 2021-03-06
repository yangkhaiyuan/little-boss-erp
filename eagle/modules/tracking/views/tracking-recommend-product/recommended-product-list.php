<?php 
use eagle\modules\util\helpers\TranslateHelper;
use eagle\helpers\HtmlHelper;
use yii\helpers\Html;

$this->registerJsFile(\Yii::getAlias('@web')."/js/project/tracking/custom_product.js", ['depends' => ['yii\web\JqueryAsset']]);
// $this->registerJs("customProduct.init();", \yii\web\View::POS_READY);
// $platform_array = [
//     '1'=>'bonanza',
//     '2'=>'cdiscount',
// ];
$currency_array = [
    'USD'=>'USD',
    'EUR'=>'EUR',
];
// $seller_array = [
//     '1'=>'jack',
//     '2'=>'mary',
// ];
?>


<style>
.base-product h3{
	margin: 20px 0;
    font-weight: bold;
    padding-left: 6px;
    border-left: 3px solid #01bdf0;
}
.red{
	color:red;
}
.comment-area{
	width: 558px;
    height: 200px;
}
.product-save{
	width:700px;
}
.product-save-split{
	width:100%;
	height:30px;
	margin-top: 2px;
}
.product-save-split .input-control{
	float:left;
	margin-left:20px;
}
.new-input{
	width:280px;
}
.edit-input{
	width:200px;
}
.select-style{
	height:25px;
	width:150px;
}
.product-bottom{
	margin-left:20px;
}
.product-bottom .input-control{
	margin-top:5px;
}
.product-bottom .bottom-select{
	width:571px
}
.button-group-style{
	text-align:center;
	margin-top: 6px;
}
.button-group-style button{
	width:100px;
}
.custom-tips{
	padding-left: 19px;
	font-size: 13px;
	padding-bottom: 8px;
	color:#01bdf0;
}
</style>
<div class="base-product">
    <form id="custom_product">
    <div>
        <input type="hidden" id="saveType" name="saveType" value="<?php echo !empty($data)?'edit':'save';?>">
        <input type="hidden" id="prouductId" name="prouductId" value="<?php echo !empty($data)?$data['id']:'';?>">
        <input type="hidden" id="accountValue" name="accountValue" value="<?php echo !empty($data['seller_id'])?$data['seller_id']:'';?>">
        <input type="hidden" id="userHabit" name="userHabit" value="<?php echo !empty($userHabit['userHabit'])?$userHabit['userHabit']:'';?>">
    </div>
    <div class="product-save">
        <div class="custom-tips"><span>??????????????????(??????)????????????????????????????????????????????????????????????????????????</span></div>
        <?php if(!empty($data['photo_url'])):?>
            <img src="<?php echo $data['photo_url'];?>" style="width:60px;height:60px;float:left;margin-left:20px;">
        <?php endif;?>
        <div class="product-save-split">
            <div class="input-control">
				<div class="input-group">
				    <label>????????????(???????????????)&nbsp;<span class="red">*</span></label>
					<input type="text" class="iv-input <?php echo empty($data)?"new-input":"edit-input"?>"  data-name="????????????" name="product_name" id="product_name" value="<?php echo !empty($data['product_name'])?$data['product_name']:'';?>"/>
				</div>
			</div>
			
			<div class="input-control">
				<label>????????????</label>
				<select class="iv-select select-style" data-name="????????????" name="platform" id="platform" onchange="customProduct.customPlatformChange(this);">
				    <option value="">????????????</option>
				    <?php 
				        if(!empty($platform_array)):
                            foreach ($platform_array as $platform_key => $platform_value):				            
				    ?>
        					<option value="<?php echo $platform_key?>" 
        					<?php 
        					   if(!empty($data['platform'])&&$platform_key == $data['platform']){
        					       echo 'selected';
        					   }else if(!empty($userHabit['platformHabit'])&&$platform_key == $userHabit['platformHabit']){
        					       echo 'selected';
        					   }else{
        					       echo '';
        					   }
        					?>>
        					<?php echo $platform_value?></option>
					<?php 
					        endforeach;
				        endif;
					?>
				</select>
			</div>
        </div>  
        
        <div class="product-save-split" style="margin-left: 2px;">
        
            <div class="input-control">
				<div class="input-group">
				    <label>??????SKU(???????????????)&nbsp;&nbsp;</label>
					<input type="text" class="iv-input <?php echo empty($data)?"new-input":"edit-input"?>" <?php echo !empty($data)?"style='margin-left:2px;'":''?> name="sku" id="sku" value="<?php echo !empty($data['sku'])?$data['sku']:'';?>"/>
				</div>
			</div>
			
			<div class="input-control">
			    <label>????????????</label>   
				<select class="iv-select select-style" data-name="????????????" name="seller_id" id="seller_id">
				    <option value="">????????????</option>
				</select>
			</div>
	   </div>
	   
	   <div class="product-bottom">
   			<div class="input-control">
				<label>????????????&nbsp;</label>
				<select class="iv-select" style="height:25px;margin-left: 2px;" data-name="????????????" name="group_name" id="group_name" onchange="customProduct.platformChange(this)">
					<option value="">????????????</option>
				    <?php 
				        if(!empty($group_array)):
                            foreach ($group_array as $group_name_key => $group_name_value):				            
				    ?>
        					<option value="<?php echo $group_name_key?>" <?php echo (!empty($data['group_id'])&&$group_name_key == $data['group_id'])?'selected':''?>><?php echo $group_name_value?></option>
					<?php 
					        endforeach;
				        endif;
					?>
				</select>
				<lable id="group_attr">
				    <?php 
				        if(!empty($data)&&!empty($groups_detail)&&isset($groups_detail[$data['group_id']])){
				            echo '<span id="belong_platform" data-name="'.$groups_detail[$data['group_id']]['platform'].'">???????????????'.$groups_detail[$data['group_id']]['platform'].'</span>&nbsp;&nbsp;<span id="belong_seller" data-name="'.$groups_detail[$data['group_id']]['seller_id'].'">???????????????'.$groups_detail[$data['group_id']]['seller_id'].'</span>';
				        }
				    ?>
				</lable> 
			</div>
			
			<div class="input-control">
				<div class="input-group">
				    <label>????????????&nbsp;<span class="red">*</span></label>
					<input type="text" class="iv-input" data-name="????????????" onkeyup="value=value.replace(/[^0-9.]/g,'')" name="price" id="price" value="<?php echo !empty($data['price'])?$data['price']:''?>"/>
					<select class="iv-select" style="height:25px" data-name="??????" name="currency" id="currency">
					    <option value="">??????</option>
						<?php 
        			        if(!empty($currency_array)):
                                foreach ($currency_array as $currency_key => $currency_value):				            
        			    ?>
            					<option value="<?php echo $currency_key?>" 
            					<?php
                					if(!empty($data['currency'])&&$currency_key == $data['currency']){
                					    echo 'selected';
                					}else if(!empty($userHabit['currencyHabit'])&&$currency_key == $userHabit['currencyHabit']){
                					    echo 'selected';
                					}else{
                					    echo '';
                					} 
            					?>>
            					<?php echo $currency_value?></option>
        				<?php 
        				        endforeach;
        			        endif;
        				?>
					</select>
				</div>
			</div>
	
			<div class="input-control">
				<div class="input-group">
				    <label>????????????&nbsp;<span class="red">*</span></label>
					<input type="text" class="iv-input bottom-select" data-name="????????????" name="title" id="title" value="<?php echo !empty($data['title'])?$data['title']:'';?>"/>
				</div>
			</div>
			
			<div class="input-control">
				<div class="input-group">
				    <label>????????????&nbsp;<span class="red">*</span></label>
					<input type="text" class="iv-input bottom-select" data-name="????????????" name="photo_url" id="photo_url" value="<?php echo !empty($data['photo_url'])?$data['photo_url']:'';?>"/>
				</div>
			</div>
			
			<div class="input-control">
				<div class="input-group">
				    <label>????????????&nbsp;<span class="red">*</span></label>
					<input type="text" class="iv-input bottom-select" data-name="????????????" name="product_url" id="product_url" value="<?php echo !empty($data['product_url'])?$data['product_url']:'';?>"/>
				</div>
			</div>
			
			<div class="input-control">
				<div class="input-group">
				<label>????????????&nbsp;&nbsp;<br>(???????????????)</label>
					<textarea class="iv-input comment-area" style="margin-left:2px;" name="comment" id="comment"><?php echo !empty($data['comment'])?$data['comment']:'';?></textarea>
				</div>
			</div>

		</div>
	</div>
    </form>
    <div class="button-group-style">
	   <button class="btn btn-success" id="saveNewProduct">??? ???</button>
	   <button class="btn btn-success" id="windowDisplay">??????</button>
	</div>
</div>