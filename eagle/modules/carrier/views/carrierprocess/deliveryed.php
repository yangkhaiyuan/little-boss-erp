<?php
use yii\helpers\Html;
use yii\helpers\Url;
use eagle\modules\util\helpers\TranslateHelper;
use eagle\models\carrier\SysCarrierParam;
use eagle\models\OdOrderShipped;
use eagle\modules\tracking\models\Tracking;
use eagle\modules\carrier\helpers\CarrierOpenHelper;

$this->registerJsFile ( \Yii::getAlias ( '@web' ) . "/js/project/carrier/orderSearch.js", [
		'depends' => [
		'yii\web\JqueryAsset'
		]
		] );
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/orderCommon.js", ['depends' => ['yii\web\JqueryAsset']]);
if (!empty($_REQUEST['consignee_country_code'])){
	$this->registerJs("OrderCommon.currentNation=".json_encode(array_fill_keys(explode(',', $_REQUEST['consignee_country_code']),true)).";" , \yii\web\View::POS_READY);
}
$this->registerJs("OrderCommon.NationList=".json_encode(@$countrys).";" , \yii\web\View::POS_READY);
$this->registerJs("OrderCommon.NationMapping=".json_encode(@$country_mapping).";" , \yii\web\View::POS_READY);
$this->registerJs("OrderCommon.initNationBox($('div[name=div-select-nation][data-role-id=0]'));" , \yii\web\View::POS_READY);
$this->registerJs("OrderCommon.customCondition=".json_encode(@$custom_condition).";" , \yii\web\View::POS_READY);
$this->registerJs("OrderCommon.initCustomCondtionSelect();" , \yii\web\View::POS_READY);

$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/OrderTag.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/orderOrderList.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJs("OrderTag.TagClassList=".json_encode($OrderTagHelper::getTagColorMapping()).";" , \yii\web\View::POS_READY);

echo Html::hiddenInput('custom_condition_config',@$custom_condition_config);
?>

<?php echo $this->render('_leftmenu');?>

<style>
.btn_tag_qtip a {
  margin-right: 5px;
}
.div_select_tag>.input-group , .div_new_tag>.input-group{
  float: left;
  width: 32%;
  vertical-align: middle;
  padding-right: 10px;
  padding-left: 10px;
  margin-bottom: 10px;
}

.div_select_tag{
	display: inline-block;
	border-bottom: 1px dotted #d4dde4;
	margin-bottom: 10px;
}
.div-input-group{
	  width: 150px;
  display: inline-block;
  vertical-align: middle;
	margin-top:1px;

}
.div_add_tag{
	width: 600px;
}
</style>
<div class="content-wrapper" >
<!-- --------------------------------------------?????? begin--------------------------------------------------------------- -->
	<div>
		<!-- ???????????? -->
		<form class="form-inline" id="form1" name="form1" action="" method="post">
		<div style="margin:30px 0px 0px 0px">
		<!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
		<?=Html::dropDownList('selleruserid',@$_REQUEST['selleruserid'],$selleruserids,['class'=>'iv-input','id'=>'selleruserid','style'=>'margin:0px','prompt'=>'????????????'])?>
		<!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
			<div class="input-group iv-input">
		        <?php $sel = [
					'order_id'=>'??????????????????',
		        	'order_source_order_id'=>'???????????????',
					'sku'=>'SKU',
					'order_source_itemid'=>'???????????????',
					'customer_number'=>'?????????',
					'source_buyer_user_id'=>'????????????',
		        	'consignee'=>'????????????',
					'consignee_email'=>'??????Email',
				]?>
				<?=Html::dropDownList('keys',@$_REQUEST['keys'],$sel,['class'=>'iv-input'])?>
		      	<?=Html::textInput('searchval',@$_REQUEST['searchval'],['class'=>'iv-input','id'=>'num'])?>
		      	
		    </div>
		    <!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
		    <?=Html::checkbox('fuzzy',@$_REQUEST['fuzzy'],['label'=>TranslateHelper::t('????????????')])?>
		    
		    
		    <!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
		    <?=Html::submitButton('??????',['class'=>"iv-btn btn-search btn-spacing-middle",'id'=>'search'])?>
	    	<?=Html::button('??????',['class'=>"iv-btn btn-search btn-spacing-middle",'onclick'=>"javascript:cleform();"])?>
	    	<!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
	    	<a id="simplesearch" href="#" style="font-size:12px;text-decoration:none;" onclick="mutisearch();">????????????<span class="glyphicon glyphicon-menu-down"></span></a>	 
	    	<!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
	    	<?php
	    	if (!empty($custom_condition)){
	    		$sel_custom_condition = array_merge(['??????????????????'] , array_keys($custom_condition));
	    	}else{
	    		$sel_custom_condition =['0'=>'??????????????????'];
	    	}
	    	
	    	echo Html::dropDownList('sel_custom_condition',@$_REQUEST['sel_custom_condition'],$sel_custom_condition,['class'=>'iv-input'])?>
	    	<?=Html::button('?????????????????????',['class'=>"iv-btn btn-search",'onclick'=>"showCustomConditionDialog()",'name'=>'btn_save_custom_condition'])?>
	    	<div style="height:30px"></div>
	    	<!----------------------------------------------------------- ???????????????????????? ----------------------------------------------------------->
	    	<div class="mutisearch" <?php if ($showsearch!='1'){?>style="display: none;"<?php }?>>
	    	<!----------------------------------------------------------- ??????----------------------------------------------------------->
			<div class="input-group"  name="div-select-nation"  data-role-id="0"  style='margin:0px'>
				<?=Html::textInput('consignee_country_code',@$_REQUEST['consignee_country_code'],['class'=>'iv-input','placeholder'=>'???????????????'])?>
			</div>
			<!----------------------------------------------------------- ?????? ----------------------------------------------------------->
			<?=Html::dropDownList('default_warehouse_id',@$_REQUEST['default_warehouse_id'],$warehouse,['class'=>'iv-input','prompt'=>'??????','id'=>'default_warehouse_id'])?>
			<!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
			<?=Html::dropDownList('order_source',@$_REQUEST['order_source'],$OdOrder::$orderSource,['class'=>'iv-input','prompt'=>'????????????','id'=>'order_source'])?>
			<!----------------------------------------------------------- ????????? ----------------------------------------------------------->
			<?php 
			echo Html::dropDownList('default_carrier_code',@$_REQUEST['default_carrier_code'],$carriersProviderList,['class'=>'iv-input','prompt'=>'?????????','id'=>'order_carrier_code'])
			?>
			<!----------------------------------------------------------- ????????????----------------------------------------------------------->
			<?=Html::dropDownList('default_shipping_method_code',@$_REQUEST['default_shipping_method_code'],$services,['class'=>'iv-input','prompt'=>'????????????','id'=>'shipmethod'])?>
			<!----------------------------------------------------------- ??????????????? ----------------------------------------------------------->
			<?=Html::dropDownList('custom_tag',@$_REQUEST['custom_tag'],$all_tag_list,['class'=>'iv-input'])?>
			<!----------------------------------------------------------- ?????????????????? ----------------------------------------------------------->
			<?php $reorderTypeList = [''=>'??????????????????'];
			$reorderTypeList+=$OdOrder::$reorderType;?>
			<?=Html::dropDownList('reorder_type',@$_REQUEST['reorder_type'],$reorderTypeList,['class'=>'iv-input'])?>
			<!----------------------------------------------------------- ?????? ----------------------------------------------------------->
			<?=Html::dropDownList('order_evaluation',@$_REQUEST['order_evaluation'],$OdOrder::$orderEvaluation,['class'=>'iv-input','prompt'=>'??????','id'=>'order_evaluation'])?>
			<!----------------------------------------------------------- tracker?????? ----------------------------------------------------------->
			<?php $TrackerStatusList = [''=>'tracker??????'];
			 	$tmpTrackerStatusList = Tracking::getChineseStatus('',true);
				$TrackerStatusList+= $tmpTrackerStatusList?>
			 <?=Html::dropDownList('tracker_status',@$_REQUEST['tracker_status'],$TrackerStatusList,['class'=>'iv-input','style'=>'width:110px;margin:0px'])?>
			<!----------------------------------------------------------- ????????????----------------------------------------------------------->
			 <?=Html::dropDownList('timetype',@$_REQUEST['timetype'],['soldtime'=>'????????????','paidtime'=>'????????????','printtime'=>'????????????','shiptime'=>'????????????'],['class'=>'iv-input'])?>
        	<?=Html::input('date','date_from',@$_REQUEST['date_from'],['class'=>'iv-input'])?>
        	???
			<?=Html::input('date','date_to',@$_REQUEST['date_to'],['class'=>'iv-input'])?>
			<?=Html::dropDownList('ordersorttype',@$_REQUEST['ordersorttype'],['desc'=>'??????','asc'=>'??????'],['class'=>'iv-input','id'=>'ordersorttype'])?>
			<!----------------------------------------------------------- ?????? ----------------------------------------------------------->	
			<?=Html::dropDownList('customsort',@$_REQUEST['customsort'],[''=>'??????','order_id asc'=>'???????????????','order_id desc '=>'???????????????','grand_total asc '=>'????????????','grand_total desc'=>'????????????'],['class'=>'iv-input'])?>
			<!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
			???????????????	
			<?=Html::dropDownList('item_qty_compare_operators',@$_REQUEST['item_qty_compare_operators'],['>'=>'??????','='=>'??????','<'=>'??????'],['class'=>'iv-input'])?>
			<?=Html::input('text','item_qty',@$_REQUEST['item_qty'],['class'=>'iv-input'])?>
			<br>
			<div style="height:30px"></div>
			<!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
			<strong style="font-weight: bold;font-size:14px;">???????????????</strong>
			<?php 
			echo Html::checkbox('is_reverse',@$_REQUEST['is_reverse'],['label'=>TranslateHelper::t('??????')]);
			?>
			<?php 
			echo Html::CheckboxList('order_systags',@$_REQUEST['order_systags'],$OrderTagHelper::$OrderSysTagMapping);
			?>
			<div style="height:20px"></div>
			 </div> 
			 <?php //=Html::hiddenInput('trackstatus',@$_REQUEST['trackstatus'],['id'=>'trackstatus'])?>	
				
	    </div>
		</form>
	</div>
<!-- --------------------------------------------?????? end--------------------------------------------------------------- -->

<div class="form-group">
	<ul class="nav nav-pills"><!-- btn-tab -->
		<li role="presentation" ><input type="button" class ="iv-btn btn-important" onclick="setFinished()" value="??????????????????" /></li>
		<li role="presentation" >
		<?php 
			echo Html::checkbox('ignore_inventory_processing',false,['value'=>true,'label'=>TranslateHelper::t('??????????????????')]);
			?>
		</li>
		<li role="presentation" >
		<?php 
			echo Html::checkbox('is_shipped',true,['value'=>true,'label'=>TranslateHelper::t('??????????????????')]);
			?>
		</li>
		<li role="presentation" ><input type="button" class ="iv-btn btn-important" onclick="moveToUpload()" value="????????????" /></li>
		<li role="presentation" ><input type="button" class ="iv-btn btn-important" onclick="changeServerToUpload()" value="??????????????????" /></li>
		<li role="presentation" ><input type="button" class ="iv-btn btn-important" onclick="getTrackNo()" value="???????????????" /></li>
		<li role="presentation" ><?= Html::dropdownlist('shipmethod',@$_REQUEST['default_shipping_method_code'],[''=>'?????????????????????????????????']+$shippingServices,['class'=>'iv-input','style'=>'width:260px','onchange'=>'changeShippingService(this)'])?></li>
		<li role="presentation" ><input type="button" class="iv-btn <?= (isset($printMode['is_api_print']) && !empty($printMode['is_api_print']))?'btn-important':'disabled'?>" onclick="<?= (isset($printMode['is_api_print']) && !empty($printMode['is_api_print']))?"doprint('api')":''?>" value="API??????????????????"/></li>
		<li role="presentation" ><input type="button" class="iv-btn <?= (isset($printMode['is_print']) && !empty($printMode['is_print']))?'btn-important':'disabled'?>" onclick="<?= (isset($printMode['is_print']) && !empty($printMode['is_print']))?"doprint('gaofang')":''?>" value="??????????????????" /></li>
		<li role="presentation" ><input type="button" class="iv-btn <?= (isset($printMode['is_custom_print']) && !empty($printMode['is_custom_print']))?'btn-important':'disabled'?>" onclick="<?= (isset($printMode['is_custom_print']) && !empty($printMode['is_custom_print']))?"doprint('custom')":''?>" value="?????????????????????" /></li>
		<li role="presentation" ><input type="button" class="iv-btn <?= (isset($printMode['is_api_print']) && !empty($printMode['is_api_print']))?'btn-important':'disabled'?>" onclick="<?= (isset($printMode['is_api_print']) && !empty($printMode['is_api_print']))?"doprint('integrationlabel')":''?>" value="?????????????????????"/></li>
		<span qtipkey="carrier_integration_lable"></span>
	</ul>
</div>
	<?php $divTagHtml = "";?>
<table class="table table-condensed table-bordered" style="font-size:12px;table-layout: fixed;word-break: break-all;">
			<tr>
				<th width="2%">
				<input type="checkbox" check-all="e1" />
				</th>
				<th width="10%"><b>???????????????</b></th>
				<th width="4%"><b>??????</b></th>
				<th width="8%"><b>??????</b></th>
				<th width="8%"><b>???????????????</b></th>
				<th width="4%"><b>??????</b></th>
				<th width="12%"><b>SKU x ??????</b></th>
				<th width="10%"><b>?????????/????????????</b></th>
				<th width="10%"><b>??????</b></th>
				<th width="10%"><b>????????????/?????????</b></th>
				<th width="10%"><b>???????????????</b></th>
                <th width="8%"><b>???????????????</b></th>
				<th width="6%"><b>??????</b></th>
			</tr>
			<?php if (count($orders)):foreach ($orders as $order):?>
			<tr style="background-color: #f4f9fc" data="<?= $order->order_id?>" deliveryid="<?= $order->delivery_id?>">
				<td>
				<input type="checkbox" name="order_id[]" class="ck"  value="<?=$order->order_id?>" data-check="e1" />
				</td>
				<td>
					<?= TranslateHelper::t($order->order_id)?><br>
					<?php if ($order['exception_status']>0&&$order['exception_status']!='201'):?>
								<div title="<?=$OdOrder::$exceptionstatus[$order['exception_status']]?>" class="exception_<?=$order['exception_status']?>"></div>
							<?php endif;?>
							<?php if (strlen($order['user_message'])>0):?>
								<div title="<?=$OdOrder::$exceptionstatus[$OdOrder::EXCEP_HASMESSAGE]?>" class="exception_<?=$OdOrder::EXCEP_HASMESSAGE?>"></div>
							<?php endif;?>
		            <?php 
		            $divTagHtml .= '<div id="div_tag_'.$order['order_id'].'"  name="div_add_tag" class="div_space_toggle div_add_tag"></div>';
		            $TagStr = $OrderTagHelper::generateTagIconHtmlByOrderId($order['order_id']);
		            if (!empty($TagStr)){
		            	$TagStr = "<span class='btn_tag_qtip".(stripos($TagStr,'egicon-flag-gray')?" div_space_toggle":"")."' data-order-id='".$order['order_id']."' >$TagStr</span>";
		            }
		            echo $TagStr;
		            ?>
				</td>
				<td><?= TranslateHelper::t(@$OdOrder::$orderSource[@$order->order_source])?></td>
				<td><?= TranslateHelper::t($order->selleruserid)?></td>
				<td><?= TranslateHelper::t($order->order_source_order_id)?></td>
				<td>
					<label title="<?=$order->consignee_country?>"><?=$order->consignee_country_code?></label>
				</td>
				<td>
					<?php if (count($order->items)):foreach ($order->items as $item):?>
					<?php if (isset($item->sku)&&strlen($item->sku)):?>
					<?=$item->sku?>&nbsp;<b>X<?=$item->quantity?></b><br>
					<?php endif;?>
					<?php endforeach;endif;?>
				</td>
				<td>
					<?=isset($carriers[$order->default_carrier_code])?$carriers[$order->default_carrier_code]:'?????????????????????'; ?>
					<?php echo $order['customer_number'];?>
				</td>
				<td>
					<?= @$warehouse[@$order->default_warehouse_id]?>
				</td>
				<td>
				<?=isset($allshippingservices[$order->default_shipping_method_code])?$allshippingservices[$order->default_shipping_method_code]:'????????????????????????'; ?><br>
            	<?php echo CarrierOpenHelper::getOrderShippedTrackingNumber($order['order_id'],$order['customer_number'],$order['default_shipping_method_code']); ?>
            	<span class="message"></span>
				</td>
				<td>
					<?php echo $order['customer_number'];?>
					<?php //TranslateHelper::t($order->order_source_order_id)?>
				</td>
                <td>
                    <?php echo ($order->is_print_carrier == '1')?'?????????':'?????????';?>
                </td>
				<td>
					<input type="button" value="??????" class="iv-btn btn-primary" onclick="javascript:doactionForOneOrder('suspenddelivery','<?= $order->order_id?>')" />
				</td>
			</tr>
			<?php endforeach;endif;?>
			</table>
	
		<?php if($pagination):?>
		<div id="pager-group">
		    <?= \eagle\widgets\SizePager::widget(['pagination'=>$pagination , 'pageSizeOptions'=>array( 5 , 20 , 50 , 100 , 200 , 500 ) , 'class'=>'btn-group dropup']);?>
		    <div class="btn-group" style="width: 49.6%; text-align: right;">
		    	<?=\yii\widgets\LinkPager::widget(['pagination' => $pagination,'options'=>['class'=>'pagination']]);?>
			</div>
			</div>
		<?php endif;?>
	
	</div>
<?=$divTagHtml?>
<script>
//????????????
function doactionForOneOrder(val,orderid){
	//????????????????????????????????????
	if(val==""){
		$.alert('?????????????????????','info');
		return false;
    }
	if(orderid==""){
		$.alert('???????????????','info');
		return false;
    }
	var idstr = [];
	idstr.push(orderid);
	switch(val){
		case 'suspenddelivery'://????????????
			//??????
			 $.maskLayer(true);
			 $.post('<?=Url::to(['/order/order/suspenddelivery'])?>',{orders:idstr,m:'delivery',a:'???????????????->????????????'},function(result){
				 //var r = $.parseJSON(result);
				 var event = $.alert(result,'success');
				 //var event = $.confirmBox(r.message);
				 event.then(function(){
				  // ??????,????????????
				  location.reload();
				},function(){
				  // ?????????????????????
				  $.maskLayer(false);
				});
			});
			break;
		case 'outofstock'://????????????
			$.post('<?=Url::to(['/order/order/outofstock'])?>',{orders:idstr,m:'delivery',a:'???????????????->????????????'},function(result){
				//var r = $.parseJSON(result);
				 var event = $.alert(result,'success');
				 //var event = $.confirmBox(r.message);
				 event.then(function(){
				  // ??????,????????????
				  location.reload();
				},function(){
				  // ?????????????????????
				  $.maskLayer(false);
				});
				
			});
			break;
		default:
			return false;
			break;
	}
}
//?????????????????????
function setusertab(orderid,tabobj){
	var tabid = $(tabobj).val();
	$.post('<?=Url::to(['/order/aliexpressorder/setusertab'])?>',{orderid:orderid,tabid:tabid},function(result){
		if(result == 'success'){
			bootbox.alert('???????????????');
		}else{
			bootbox.alert(result);
		}
	});
}
//??????????????????
function showCustomConditionDialog(){
	var html = '<label>'+Translator.t('??????????????????')+'</label><?=Html::textInput('filter_name',@$_REQUEST['filter_name'],['class'=>'iv-input','id'=>'filter_name'])?>';
	var modalbox = bootbox.dialog({
		title: Translator.t("???????????????????????????"),
		className: "", 
		message: html,
		buttons:{
			Ok: {  
				label: Translator.t("??????"),  
				className: "btn-primary",  
				callback: function () { 
					if ($('#filter_name').val() == "" ){
						bootbox.alert(Translator.t('???????????????????????????!'));
						return false;
					}

					saveCustomCondition(modalbox , $('#filter_name').val() );
					return false;
					//result = ListTracking.AppendRemark(track_no , $('#filter_name').val());
				}
			}, 
			Cancel: {  
				label: Translator.t("??????"),  
				className: "btn-default",  
				callback: function () {  
				}
			}, 
		}
	});	
}
</script>