<?php
use eagle\modules\util\helpers\TranslateHelper;
use yii\helpers\Url;
use eagle\modules\carrier\apihelpers\CarrierApiHelper;
use yii\helpers\Html;
use eagle\modules\catalog\helpers\ProductApiHelper;
use eagle\modules\carrier\models\SysShippingService;
use common\helpers\Helper_Array;
use common\api\carrierAPI\LB_ALIONLINEDELIVERYCarrierAPI;
use eagle\modules\carrier\models\SysCarrierAccount;
$this->registerJsFile ( \Yii::getAlias ( '@web' ) . "/js/project/carrier/submitOrder.js", [ 
		'depends' => [ 
				'yii\web\JqueryAsset' 
		] 
] );

$this->registerJsFile ( \Yii::getAlias ( '@web' ) . "/js/project/carrier/carrieroperate/lbalionlinedelivery.js", [
		'depends' => [
		'yii\web\JqueryAsset'
		]
		] );
?>
<?php $s = SysShippingService::findOne($orderObj->default_shipping_method_code);?>
<?php $declarationInfo = CarrierApiHelper::getDeclarationInfo($orderObj,$s);?>
<?php $aliCarrierAccount = SysCarrierAccount::find()->where(['id'=>$s->carrier_account_id])->one(); ?>
<input type="hidden" name="id" value="<?= $orderObj->order_id;?>">

<input type="hidden" name="total" value="<?= $declarationInfo['total'] ?>">
<input type="hidden" name="currency" value="<?= $declarationInfo['currency'] ?>">
<input type="hidden" name="total_price" value="<?= $declarationInfo['total_price'] ?>">
<input type="hidden" name="total_weight" value="<?= $declarationInfo['total_weight'] ?>">

<script>
<?php $isHomeLanshou = empty($aliCarrierAccount->api_params['HomeLanshou']) ? 'N' : $aliCarrierAccount->api_params['HomeLanshou']; ?>
var isHomeLanshou = '<?= $isHomeLanshou; ?>';
</script>

<dl class="getOrderNo_row1">
	<dt class="getOrderNo_ul1">
		<div class="form-group">
		<?php

		foreach($order_params as $v):
			$field = $v->data_key;
			$data = isset($orderObj->$field)?$orderObj->$field:'';
		?>
			<label>
				<?= $v->carrier_param_name ?>
				<?= $v->is_required==1?'<span class="star">*</span>':''; ?> 
			</label>
			<?php
				if ($v->display_type == 'text'){
					if($v->carrier_param_key == 'AlidomesticTrackingNo'){
						//??????????????????????????????????????????????????????????????????????????????
						$tmpDomesticNo = '';
						if($isHomeLanshou == 'Y'){
							$tmpDomesticNo = 'None';
							if(in_array($s->shipping_method_code, LB_ALIONLINEDELIVERYCarrierAPI::$fourpxChannel)){
								$tmpDomesticNo = '4PX';
							}
						}

						echo Html::input('text',$v->carrier_param_key,$tmpDomesticNo,['style'=>$v->input_style,'class'=>'eagle-form-control']);
					}else{
						echo Html::input('text',$v->carrier_param_key,$data,['style'=>$v->input_style,'class'=>'eagle-form-control']);
					}
				}elseif ($v->display_type == 'dropdownlist'){
					if($v->carrier_param_key == 'AlidomesticLogisticsCompanyId'){
						echo "<div style='display:inline;'>";
						echo Html::dropDownList($v->carrier_param_key,$data,$v->carrier_param_value,['onchange'=>"aliChangeCompany($(this).val());",'style'=>'width:150px;','class'=>'eagle-form-control']);
						
						$tmpHomeLanshouCN = '';
						if($isHomeLanshou == 'Y'){
							$tmpHomeLanshouCN = '????????????';
						}
						echo "<label id='lab_ali_company' style='display:none;margin-left:10px;'>????????????????????????</label><input type='text' class='eagle-form-control' id='domesticLogisticsCompany' name='domesticLogisticsCompany' value='".$tmpHomeLanshouCN."' style='display: none;'>";
						echo '</div>';
					}else{
						echo Html::dropDownList($v->carrier_param_key,$data,$v->carrier_param_value,['style'=>'width:150px;','class'=>'eagle-form-control']);
					}
				}
			 ?>
		</div>
		<?php 
		endforeach;
		if($orderObj->carrier_step==\eagle\modules\order\models\OdOrder::CARRIER_CANCELED){
			echo '<label qtipkey="carrier_extra_id">????????????<span class="star">*</span></label>???????????????(1-9)<input type="text"  name="extra_id" style="width:50px;">';
		}
		?>
		<div class="cleardiv"></div>
<?php foreach($declarationInfo['products'] as $product){?>
		<dd>
		<h5 class='text-success'>????????????<?=$product['name'] ?></h5>
		<?php
		foreach($item_params as $v):
			$field = $v->data_key;
			$data = isset($product[$field])?$product[$field]:'';
		?>
			<div class="form-group">
				<label>
					<?= $v->carrier_param_name ?>
					<?= $v->is_required==1?'<span class="star">*</span>':''; ?>
				</label>
				<?php
					$placeholder = $product == null ?'???????????????SKU,???????????????':'';
					if ($v->display_type == 'text'){
						echo Html::input('text',$v->carrier_param_key.'[]',$data,[
							'style'=>$v->input_style,
							'class'=>'eagle-form-control',
							'placeholder'=>$placeholder,
							'required'=>$v->is_required==1?'required':null
						]);
					}elseif ($v->display_type == 'dropdownlist'){
						echo Html::dropDownList($v->carrier_param_key.'[]',$data,$v->carrier_param_value,['prompt'=>$v->carrier_param_name,'style'=>'width:150px;','class'=>'eagle-form-control']);
					}
				 ?>
			</div>
			<?php endforeach; ?>
		</dd>
<?php }?>
</dl>