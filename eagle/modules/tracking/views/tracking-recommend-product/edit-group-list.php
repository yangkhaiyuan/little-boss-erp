<?php 
use yii\helpers\Html;
use yii\grid\GridView;
use eagle\modules\util\helpers\TranslateHelper;
use yii\helpers\Url;
use yii\jui\Dialog;
use yii\data\Sort;
use yii\widgets\LinkPager;

$this->registerJsFile(\Yii::getAlias('@web')."/js/project/tracking/custom_product.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJs("customProduct.init()", \yii\web\View::POS_READY);

// $platform_array = [
//     '1'=>'bonanza',
//     '2'=>'cdiscount',
// ];
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
	width: 571px;
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
	width:300px;
}
.edit-input{
	width:218px;
}
.select-style{
	height:25px;
	width:200px;
}
.product-bottom{
	margin-left:10px;
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
.new-group-name{
    margin-left: 19px;
    margin-bottom: 6px;
}
.custom-list-top{
	
}
.serach-button{
  border-radius: 0px 3px 3px 0px !important;
  margin-bottom: 2px;
  height: 28px;
}
.custom-table{
	margin-top:10px;
}
.table td{
	border-right:0px solid #d9effc !important;
	border-bottom:1px solid #d9effc !important;
	text-align:center;
	vertical-align: middle !important;
}
.table th{
	text-align:center !important;
	vertical-align: middle !important;
}
.group-background{
	background-color:#F5F5F5;
	padding: 10px;
}
</style>
<div class="tracking-index col2-layout">
	
	<?= $this->render('_menu') ?>
	<div class="base-product group-background">
        <form id="custom_group">
        <div>
            <input type="hidden" id="groupId" name="groupId" value="<?php echo $group_data['id'];?>">
        </div>
        <div class="product-save">
    
        <div class="input-control">
    		<div class="input-group new-group-name">
    		    <label>????????????<span class="red">*</span></label>
    			<input type="text" class="iv-input <?php echo empty($group_data)?"new-input":"edit-input"?>"  data-name="????????????" name="group_name" id="group_name" value="<?php echo !empty($group_data['group_name'])?$group_data['group_name']:'';?>"/>
    		</div>
    	</div>
    	
        <div class="product-save-split">
    		<div class="input-control">
    			<label>????????????</label>
    			<select class="iv-select select-style" data-name="????????????" name="platform" id="platform" onchange="customProduct.customPlatformChange(this);">
    			    <option value="">????????????</option>
    			    <?php 
    			        if(!empty($platform_array)):
                            foreach ($platform_array as $platform_key => $platform_value):				            
    			    ?>
        					<option value="<?php echo $platform_key?>" <?php echo (!empty($group_data['platform'])&&$platform_key == $group_data['platform'])?'selected':''?>><?php echo $platform_value?></option>
    				<?php 
    				        endforeach;
    			        endif;
    				?>
    			</select>
    		</div>
        </div>  
        
        <div class="product-save-split">
        
    		
    		<div class="input-control">
    		    <label>????????????</label>   
    			<select class="iv-select select-style" data-name="????????????" name="seller_id" id="seller_id">
    			    <option value="">????????????</option>
    			    <?php 
    			        if(!empty($seller_array)):
                            foreach ($seller_array as $seller_key => $seller_value):				            
    			    ?>
        					<option value="<?php echo $seller_key?>" <?php echo (!empty($group_data['seller_id'])&&$seller_key == $group_data['seller_id'])?'selected':''?>><?php echo $seller_value?></option>
    				<?php 
    				        endforeach;
    			        endif;
    				?>
    			</select>
    		</div>
       </div>
       
       <div class="product-bottom">
    		<div class="input-control">
    			<div class="input-group">
    			<label>???????????????</label>
    				<textarea class="iv-input comment-area" style="margin-left:2px;" name="group_comment" id="group_comment"><?php echo !empty($group_data['group_comment'])?$group_data['group_comment']:'';?></textarea>
    				</div>
    			</div>
    
    		</div>
    	</div>
        </form>
    </div>
	<div class="custom-list-top">
        
        <div class="custom-table">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="chk_all"></th>
                        <th>??????</th>
                        <th>??????</th>
                        <th style="width: 120px;">SKU</th>
                        <th style="width: 70px;">??????</th>
                        <th>??????</th>
                        <th>??????</th>
                        <th>????????????</th>
                        <th>????????????</th>
                    </tr>
                 </thead>
                 <?php if(!empty($data)):?>
                 <tbody class="lzd_body">
                 <?php $num=1;foreach ($data as $data_detail):?>
                    <tr data-id="<?php echo $data_detail['id']?>" <?php echo $num%2==0?"class='striped-row'":null;$num++;?>>
                        <td><input type="checkbox" id="chk_one"></td>
                        <td><img src="<?php echo $data_detail['photo_url'];?>" style="max-width:60px;max-height:60px;"></td>
                        <td style="word-break:break-all;"><?php echo $data_detail['title'];?></td>
                        <td><?php echo $data_detail['sku'];?></td>
                        <td><?php echo $data_detail['seller_id'];?></td>
                        <td><?php echo $data_detail['price']?>&nbsp;<?php echo $data_detail['currency']?></td>
                        <td><?php echo $data_detail['platform']?></td>
                        <td style="word-break:break-all;"><?php echo $data_detail['comment']?></td>
                        <td><?php echo date("Y-m-d H:i:s",$data_detail['create_time']);?></td>
                    </tr>
                 <?php endforeach;?>
                 </tbody>
                <?php endif;?>
            </table>
        </div>
        <div style="text-align: left;">
            <div class="btn-group" >
            	<?php echo LinkPager::widget(['pagination'=>$pages,'options'=>['class'=>'pagination']]);?>
        	</div>
                <?php echo \eagle\widgets\SizePager::widget(['pagination'=>$pages , 'pageSizeOptions'=>array( 5 , 10 , 20 , 50 ) , 'class'=>'btn-group dropup']);?>
        </div>
    </div>
    <div class="button-group-style">
	   <button class="btn btn-success" onclick="customProduct.saveGroup()">??? ???</button>
	</div>
</div>
