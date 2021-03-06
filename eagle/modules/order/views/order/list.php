<?php
use yii\helpers\Html;
use yii\grid\GridView;
use yii\widgets\LinkPager;
use yii\helpers\Url;
use yii\jui\JuiAsset;
use yii\web\UrlManager;
use yii\jui\DatePicker;
use eagle\modules\order\models\OdOrder;
use eagle\modules\order\models\OdOrderItem;
use eagle\modules\carrier\apihelpers\CarrierApiHelper;
use eagle\modules\inventory\helpers\InventoryApiHelper;
use eagle\modules\order\models\OdEbayOrder;
use eagle\modules\util\helpers\TranslateHelper;
use eagle\modules\order\helpers\OrderTagHelper;
use eagle\modules\carrier\helpers\CarrierHelper;
use eagle\modules\listing\models\EbayItem;
use eagle\modules\order\models\OdEbayTransaction;
use eagle\widgets\SizePager;
use eagle\modules\tracking\helpers\TrackingApiHelper;
use eagle\modules\tracking\helpers\TrackingHelper;
use eagle\modules\message\apihelpers\MessageApiHelper;

$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/OrderTag.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/orderOrderList.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/origin_ajaxfileupload.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/orderCommon.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/message/customer_message.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJs("OrderTag.TagClassList=".json_encode($tag_class_list).";" , \yii\web\View::POS_READY);
?>
 
<style>
.table th{
	text-align: center;
}
.table td{
	text-align: left;
}

table{
	font-size:12px;
}
.table>tbody td{
color:#637c99;
}
.table>tbody a{
color:#337ab7;
}
.table>thead>tr>th {
height: 35px;
vertical-align: middle;
}
.table>tbody>tr>td {
height: 35px;
vertical-align: middle;
}
.sprite_pay_0,.sprite_pay_1,.sprite_pay_2,.sprite_pay_3,.sprite_shipped_0,.sprite_shipped_1,.sprite_check_1,.sprite_check_0
{
	display:block;
	background-image:url(/images/MyEbaySprite.png);
	overflow:hidden;
	float:left;
	width:20px;
	height:20px;
	text-indent:20px;
}
.sprite_pay_0
{
	background-position:0px -92px;
}
.sprite_pay_1
{
	background-position:-50px -92px;
}
.sprite_pay_2
{
	background-position:-95px -92px;
}
.sprite_pay_3
{
	background-position:-120px -92px;
}
.sprite_shipped_0
{
	background-position:0px -67px;
}
.sprite_shipped_1
{
	background-position:-50px -67px;
}
.sprite_check_1
{
	background-position:-100px -15px;
}
.sprite_check_0
{
	background-position:-77px -15px;
}
.exception_201,.exception_202,.exception_221,.exception_210,.exception_222,.exception_223,.exception_299,.exception_manual{
	display:block;
	background-image:url(/images/icon-yichang-eBay.png);
	overflow:hidden;
	float:left;
	width:30px;
	height:15px;
	text-indent:20px;
}
.exception_201{
	background-position:-3px -10px;
}
.exception_202{
	background-position:-26px -10px;
}
.exception_221{
	background-position:-55px -10px;
	width:50px;
}
.exception_210{
	background-position:-107px -10px;
}
.exception_222{
	background-position:-135px -10px;
}
.exception_223{
	background-position:-170px -10px;
}
.exception_299{
	background-position:-199px -10px;
}
.exception_manual{
	background-position:-230px -10px;
}
.input-group-btn > button{
  padding: 0px;
  height: 28px;
  width: 30px;
  border-radius: 0px;
  border: 1px solid #b9d6e8;
}

.div-input-group{
	  width: 150px;
  display: inline-block;
  vertical-align: middle;
	margin-top:1px;

}

.div-input-group>.input-group>input{
	height: 28px;
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

.div_new_tag {
  display: inline-block;
}

.span-click-btn{
	cursor: pointer;
}

.btn_tag_qtip a {
  margin-right: 5px;
}

.div_add_tag{
	width: 600px;
}
/*???????????????????????????1?????????span??????*/
.multiitem{
	padding:0 4px 0 4px;
	background:rgb(255,153,0);
	border-radius:8px;
	color:#fff;
}
.checkbox{
	display: block;
    float: left;
 	margin-top: 2px;
}
</style>	
<div class="tracking-index col2-layout">
<?=$this->render('_leftmenu',['counter'=>$counter]);?>

<!-- Modal -->
<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <form  enctype="multipart/form-data"?>">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="myModalLabel">??????????????????</h4>
      </div>
      <div class="modal-body">
        <input type="file" name="order_tracknum" id="order_tracknum" ><br>
        <a href="<?=Url::home()."template/ordertracknum_sample.xls"?>">????????????</a>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">??????</button>
        <button type="button" class="btn btn-primary" id="save" onclick="importordertracknum()">??????</button>
      </div>
    </div>
  </div>
  </form>
</div>

<!-- Modal ????????????????????????modal-->
<div class="modal fade" id="myMessage" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <form  enctype="multipart/form-data"?>">
  <div class="modal-dialog">
    <div class="modal-content">
      
    </div>
  </div>
  </form>
</div>
<!-- ?????????????????????modal -->
<div class="modal fade" id="syncorderModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
   <div class="modal-dialog">
      <div class="modal-content">
         
      </div><!-- /.modal-content -->
	</div><!-- /.modal -->
</div>
<div class="content-wrapper" >
	<div>
		<!-- ???????????? -->
		<form class="form-inline" id="form1" name="form1" action="" method="post">
		<div style="margin:5px">
		<?=Html::dropDownList('selleruserid',@$_REQUEST['selleruserid'],$selleruserids,['class'=>'form-control input-sm','id'=>'selleruserid','style'=>'margin:0px','prompt'=>'????????????'])?>
			<?php 
				$search=[
					'haspayed'=>'eBay?????????',
					'hasnotpayed'=>'eBay?????????',
					'pending'=>'???????????????',
					'hassend'=>'eBay?????????',
					'payednotsend'=>'??????????????????',
					'hasmessage'=>'???eBay??????',
					'hasinvoice'=>'???????????????',
				];
			?>
			<div class="input-group">
		        <?php $sel = [
		        	'ebay_orderid'=>'eBay?????????',
					'sku'=>'SKU',
					'srn'=>'SRN',
					//'itemid'=>'ItemID',
					'tracknum'=>'?????????',
					'buyeid'=>'????????????',
		        	'consignee'=>'?????????',
					'email'=>'??????Email',
					'order_id'=>'??????????????????',
				]?>
				<?=Html::dropDownList('keys',@$_REQUEST['keys'],$sel,['class'=>'form-control input-sm','style'=>'width:120px;margin:0px'])?>
		      	<?=Html::textInput('searchval',@$_REQUEST['searchval'],['class'=>'form-control input-sm','id'=>'num','style'=>'width:120px'])?>
		    </div>
		    <?=Html::submitButton('??????',['class'=>"btn-xs",'id'=>'search'])?>
	    	<?=Html::button('??????',['class'=>"btn-xs",'onclick'=>"javascript:cleform();"])?>
	    	<a id="simplesearch" href="#" style="font-size:12px;text-decoration:none;" onclick="mutisearch();">????????????<span class="glyphicon glyphicon-menu-down"></span></a>	 
	    	<br>
	    	<div class="mutisearch" <?php if ($showsearch!='1'){?>style="display: none;"<?php }?>>
	    	<?=Html::dropDownList('fuhe',@$_REQUEST['fuhe'],$search,['class'=>'form-control input-sm','prompt'=>'????????????','id'=>'fuhe'])?>
			<?=Html::dropDownList('country',@$_REQUEST['country'],$countrys,['class'=>'form-control input-sm','prompt'=>'??????','id'=>'country'])?>
			<?php $warehouses = InventoryApiHelper::getWarehouseIdNameMap()?>
			<?=Html::dropDownList('cangku',@$_REQUEST['cangku'],$warehouses,['class'=>'form-control input-sm','prompt'=>'??????'])?>
			<?php $carriers=CarrierApiHelper::getShippingServices()?>
			<?=Html::dropDownList('shipmethod',@$_REQUEST['shipmethod'],$carriers,['class'=>'form-control input-sm','prompt'=>'????????????','id'=>'shipmethod'])?>
			<br>
			<div class="input-group">
	        	<?=Html::dropDownList('timetype',@$_REQUEST['timetype'],['soldtime'=>'????????????','paidtime'=>'????????????','printtime'=>'????????????','shiptime'=>'????????????'],['class'=>'form-control input-sm','style'=>'width:90px;margin:0px'])?>
	        	<?=Html::input('date','startdate',@$_REQUEST['startdate'],['class'=>'form-control','style'=>'width:130px'])?>
	        </div>	
	        	???
			<?=Html::input('date','enddate',@$_REQUEST['enddate'],['class'=>'form-control','style'=>'width:130px;margin:0px'])?>
			<div class="input-group">
				<?=Html::dropDownList('ordersort',@$_REQUEST['ordersort'],['soldtime'=>'????????????','paidtime'=>'????????????','printtime'=>'????????????','shiptime'=>'????????????'],['class'=>'form-control input-sm','style'=>'width:90px;margin:0px'])?>
				<?=Html::dropDownList('ordersorttype',@$_REQUEST['ordersorttype'],['desc'=>'??????','asc'=>'??????'],['class'=>'form-control input-sm','id'=>'ordersorttype','style'=>'width:60px;margin:0px'])?>
			</div>
			 </div> 
			 <?=Html::hiddenInput('trackstatus',@$_REQUEST['trackstatus'],['id'=>'trackstatus'])?>	
	    </div>
		</form>
	</div>
	<br>
	<div style="">
		<form name="a" id="a" action="" method="post">
		<?php $doarr=[
			''=>'????????????',
			'checkorder'=>'????????????',
			'signshipped'=>'eBay????????????',
			//'signwaitsend'=>'????????????',
			'signpayed'=>'??????????????????',
			//'deleteorder'=>'????????????',
			'mergeorder'=>'?????????????????????',
			'givefeedback'=>'??????',
			'dispute'=>'????????????eBay??????',
			'changeshipmethod'=>'??????????????????',
		];
		/* if (isset($_REQUEST['order_status'])&&$_REQUEST['order_status']>='300'){
			unset($doarr['signshipped']);
		} */
		?>
		<div class="col-md-2">
		<?=Html::dropDownList('do','',$doarr,['onchange'=>"doaction($(this).val());",'class'=>'form-control input-sm do']);?> 
		</div>
		<?php if (isset($_REQUEST['order_status'])&&$_REQUEST['order_status']<'300'):?>
 		<div class="col-md-2">
		<?php $doCarrier=[
			''=>'????????????',
			'getorderno'=>'????????????',
			'signwaitsend'=>'????????????',
//			'dodispatch'=>'????????????',
// 			'gettrackingno'=>'???????????????',
// 			'doprint'=>'???????????????',
// 			'cancelorderno'=>'????????????',
	];
	?>
		<?=Html::dropDownList('do','',$doCarrier,['class'=>'form-control input-sm do-carrier do']);?>
		</div> 
		<?php endif;?>
		<div class="col-md-2">
		<?php 
			$movearr = [''=>'?????????']+OdOrder::$status;
		?>
			<?=Html::dropDownList('do','',$movearr,['onchange'=>"movestatus($(this).val());",'class'=>'form-control input-sm do']);?> 
		</div>
		<div class="col-md-2">
			<?=Html::dropDownList('do','',$excelmodels,['onchange'=>"exportorder($(this).val());",'class'=>'form-control input-sm do']);?> 
		</div>
		<div class="col-md-2">
			<?=Html::button('??????????????????',['class'=>'btn btn-primary','onclick'=>'dosyncorder();'])?>
		</div>
		
		<?php $divTagHtml = "";
			$div_event_html = "";
		?>
		<br>
			<table class="table table-condensed table-bordered" style="font-size:12px;">
			<tr>
				<th width="1%" style="text-align:left;vertical-align:middle;">
				<span><span class="glyphicon glyphicon-minus checkbox" onclick="spreadorder(this);"></span></span><input id="ck_all" class="ck_0" type="checkbox">
				</th>
				<th width="4%"><b>???????????????</b></th>
				<th width="12%"><b>??????SKU</b></th>
				<th width="10%"><b>??????</b></th>
				<th width="18%"><b>????????????</b></th>
				<th width="6%">
				<?=Html::dropDownList('country',@$_REQUEST['country'],$countrys,['prompt'=>'????????????','style'=>'width:100px','onchange'=>"dosearch('country',$(this).val());"])?>
				</th>
				<th width="6%">
				<?=Html::dropDownList('shipmethod',@$_REQUEST['shipmethod'],$carriers,['prompt'=>'????????????','style'=>'width:100px','onchange'=>"dosearch('shipmethod',$(this).val());"])?>
				</th>
				<th width="10%"><b>eBay??????</b></th>
				<th width="10%">
					<b>????????????</b>
				</th>
				<th width="13%">
				<b>????????????</b>
				</th>
				<th ><b>??????</b></th>
			</tr>
			<?php $carriers=CarrierApiHelper::getShippingServices(false)?>
			<?php if (count($models)):foreach ($models as $order):?>
			<tr style="background-color: #f4f9fc">
				<td><span><span class="orderspread glyphicon glyphicon-minus checkbox" onclick="spreadorder(this,'<?=$order->order_id?>');"></span></span><input type="checkbox" class="ck" name="order_id[]" value="<?=$order->order_id?>">
				</td>
				<td style="text-align:left;">
					<?=$order->order_id?><br>
					<?php if ($order->seller_commenttype=='Positive'):?>
						<span style='background:green;'><a style="color: white" title="<?=$order->seller_commenttext?>">??????</a></span><br>
					<?php elseif($order->seller_commenttype=='Neutral'):?>
						<span style='background:yellow;'><a title="<?=$order->seller_commenttext?>">??????</a></span><br>
					<?php elseif($order->seller_commenttype=='Negative'):?>
						<span style='background:red;'><a title="<?=$order->seller_commenttext?>">??????</a></span><br>
					<?php endif;?>
					<?php if ($order->exception_status>0&&$order->exception_status!='201'):?>
						<span title="<?=OdOrder::$exceptionstatus[$order->exception_status]?>" class="exception_<?=$order->exception_status?>"></span>
					<?php endif;?>
					<?php if (strlen($order->user_message)>0):?>
						<div title="<?=OdOrder::$exceptionstatus[OdOrder::EXCEP_HASMESSAGE]?>" class="exception_<?=OdOrder::EXCEP_HASMESSAGE?>"></div>
					<?php endif;?>
					<span id="manual_<?=$order->order_id?>">
					<?php if ($order->is_manual_order==1):?>
						<span title="??????" class="exception_manual"></span>
					<?php endif;?>
					</span>
					<?php 
				$divTagHtml .= '<div id="div_tag_'.$order->order_id.'"  name="div_add_tag" class="div_space_toggle div_add_tag"></div>';
				$TagStr = OrderFrontHelper::generateTagIconHtmlByOrderId($order);
				//$TagStr = OrderTagHelper::generateTagIconHtmlByOrderId($order->order_id);
				
				if (!empty($TagStr)){
					$TagStr = "<span class='btn_tag_qtip".(stripos($TagStr,'egicon-flag-gray')?" div_space_toggle":"")."' data-order-id='".$order->order_id."' >$TagStr</span>";
				}
				echo $TagStr;
				?>
				
				</td>
				<td>
					<?php if (count($order->items)):
					$order_source_itemid_list = [];
					foreach ($order->items as $item):
					if (isset($item->order_source_itemid)&&strlen($item->order_source_itemid))
						$order_source_itemid_list[]=$item->order_source_itemid;
					?>
					<?php if (isset($item->sku)&&strlen($item->sku)):?>
					<?=$item->sku?>&nbsp;<b>X<span <?php if ($item->quantity>1){echo 'class="multiitem"';}?>><?=$item->quantity?></span></b><br>
					<?php endif;?>
					<?php endforeach;endif;?>
				</td>
				<td>
					<?=$order->grand_total?>&nbsp;<?=$order->currency?>
				</td>
				<td>
					<?=$order->paid_time>0?date('Y-m-d H:i:s',$order->paid_time):''?>
					<?php 
					if (in_array($order->order_status , [OdOrder::STATUS_PAY , OdOrder::STATUS_WAITSEND  , OdOrder::STATUS_SHIPPING])){
						$tmpTimeLeft =  ((!empty($order->fulfill_deadline))?'<br><span id="timeleft_'.$order->order_id.'" class="fulfill_timeleft" data-order-id="'.$order->order_id.'" data-time="'.($order->fulfill_deadline-time()).'"></span>':"");
						echo $tmpTimeLeft;
					}
					?>
				</td>
				<td>
					<label title="<?=$order->consignee_country?>"><?=$order->consignee_country_code?></label>
				</td>
				<td>
					<?php if (isset($order->order_source_shipping_method)&&strlen($order->order_source_shipping_method)){?>[????????????:<?=$order->order_source_shipping_method?>]<?php }?>
					<?php if (strlen($order->default_shipping_method_code)){?><br>[<?=@$carriers[$order->default_shipping_method_code]?>]<?php }?>
				</td>
				<td>
					<!-- check?????? -->
					<?php $check = OdEbayOrder::findOne(['ebay_orderid'=>$order->order_source_order_id]);?>
					<?php if (!empty($check)&&$check->checkoutstatus=='Complete'):?>
					<div title="?????????" class="sprite_check_1"></div>
					<?php else:?>
					<div title="?????????" class="sprite_check_0"></div>
					<?php endif;?>
					<!-- ?????????????????? -->
					<?php if ($order->pay_status==0 || $order->paid_time==0):?>
					<div title="?????????" class="sprite_pay_0"></div>
					<?php elseif ($order->pay_status==1 && $order->paid_time>0):?>
					<div title="?????????" class="sprite_pay_1"></div>
					<?php elseif ($order->pay_status==2 ):?>
					<div title="?????????" class="sprite_pay_2"></div>
					<?php elseif ($order->pay_status==3):?>
					<div title="?????????" class="sprite_pay_3"></div>
					<?php endif;?>
					<!-- ???????????? -->
					<?php if ($order->shipping_status==1):?>
					<div title="?????????" class="sprite_shipped_1"></div>
					<?php else:?>
					<div title="?????????" class="sprite_shipped_0"></div>
					<?php endif;?>
				</td>
				<td>
					<b><?=OdOrder::$status[$order->order_status]?></b>
				</td>
				<td>
					<?php if ($order->order_status=='300'):?>
					<?php echo CarrierHelper::$carrier_step[$order->carrier_step].'<br>';?>
					<?php endif;?>
					<?php if (count($order->trackinfos)):foreach ($order->trackinfos as $ot):?>
						<?php 
						$class = 'text-info';
						if ($ot->status==1){
							$class = 'text-success';
						}elseif ($ot->status==0){
							$class = 'text-warning';
						}elseif($ot->status==2){
							$class = 'text-danger';
						}
						?>
						<?php if (strlen($ot->tracking_number)):
							$track_info = TrackingApiHelper::getTrackingInfo($ot->tracking_number);
						?>
						<?php if ($track_info['success']==TRUE):?>
							<b><?=$track_info['data']['status']?></b><br>
						<?php endif;?>
						<a href="javascript:void(0);" onclick="OmsViewTracker(this)" title="<?=$ot->shipping_method_name?>" target="_blank" ><span class="order-info"><font class="<?php echo $class?>"><?=$ot->tracking_number?></font></span></a><br>
						<?php 
						//?????????????????????????????????
						$div_event_html .= "<div id='div_more_info_".$ot->tracking_number."' class='div_more_tracking_info div_space_toggle'>";
						
							
						$all_events_str = "";
							
						$all_events_rt = TrackingHelper::generateTrackingEventHTML([$ot->tracking_number],[],true);
						if (!empty($all_events_rt[$ot->tracking_number])){
							$all_events_str = $all_events_rt[$ot->tracking_number];
						}
							
						$div_event_html .=  $all_events_str;
						
						$div_event_html .= "</div>";
						?>
						<?php endif;?>
					<?php endforeach;endif;?>
				</td>
				<td style="text-align: left">
					<a href="<?=Url::to(['/order/order/edit','orderid'=>$order->order_id])?>" target="_blank"><span class="egicon-edit" title="????????????"></span></a>
					<?php //??????????????????dialog?????????icon -- start
						$detailMessageType=MessageApiHelper::orderSessionStatus('ebay',$order->order_source_order_id);
						if(!empty($detailMessageType['data']) && !is_null($detailMessageType['data'])){ 
						if(!empty($detailMessageType['data']['hasRead']) && !empty($detailMessageType['data']['hasReplied']))
							$envelope_class="egicon-envelope";
						else 
							$envelope_class="egicon-envelope-remove";
					?>
					<a href="javascript:void(0);" onclick="ShowDetailMessage('<?=$order->selleruserid?>','<?=$order->source_buyer_user_id?>','ebay','','O','','','','<?=$order->order_source_order_id ?>')" title="??????????????????"><span class="<?=$envelope_class?>"></span></a>
					<?php }  //??????????????????dialog?????????icon -- end?>
					
					<a href="<?=Url::to(['/order/order/feedback','order_id'=>$order->order_id])?>" target="_blank"><span class="egicon-appraise" title="??????"></span></a>
					<?php if ($order->order_source=='ebay'):?>
						<a href="javascript:void(0)" onclick="javascript:sendmessage('<?=$order->order_id?>');" title="???????????????"><span class="egicon-envelope2" aria-hidden="true"></span></a>
					<?php endif;?>
					<!--
					<?php if ($order->is_manual_order==1):?>
					<a href="#" onclick="javascript:changemanual('<?=$order->order_id ?>',this)"><span class="glyphicon glyphicon-save" title="????????????"></span></a>
					<?php else:?>
					<a href="#" onclick="javascript:changemanual('<?=$order->order_id ?>',this)"><span class="glyphicon glyphicon-open" title="??????"></span></a>
					<?php endif;?>-->
					<?php if ($order->order_source=='ebay'&&$order->order_status==100):?>
					<a href="<?=Url::to(['/order/order/sendinvoice','orderid'=>$order->order_id])?>" target="_blank"><span class="egicon-export2" title="????????????"></span></a>
					<a href="#" onclick="javascript:doactionone('signpayed','<?=$order->order_id ?>')"><span class="egicon-bestoffer" title="??????????????????"></span></a>
					<?php endif;?>
					<!-- <a title="????????????" href="<?=Url::to(['/order/logshow/list','orderid'=>$order->order_id])?>" target="_blank"><span class="glyphicon glyphicon-file toggleMenuL"></span></a> -->
					<?php $doarr_one=[
						''=>'??????',
						'checkorder'=>'????????????',
						'signshipped'=>'eBay????????????',
						//'signpayed'=>'??????????????????',
						//'deleteorder'=>'????????????',
						//'mergeorder'=>'?????????????????????',
						//'givefeedback'=>'??????',
						'changemanual'=>'??????/????????????',
						'dispute'=>'????????????eBay??????',
						'history'=>'????????????'
					];
					/* if ($order->order_status>='300'){
						unset($doarr_one['signshipped']);
					} */
					if ($order->order_status<='200'){
						$doarr_one+=[
							'getorderno'=>'????????????',
							'signwaitsend'=>'????????????',
						];	
					}
					?>
					<?=Html::dropDownList('do','',$doarr_one,['onchange'=>"doactionone($(this).val(),'".$order->order_id."');",'class'=>'form-control input-sm do','style'=>'width:70px;']);?>
					
					
				</td>
			</tr>
				<?php if (count($order->items)):foreach ($order->items as $key=>$item):?>
				
				<?php //?????????????????????????????? start
					$showLastMessage=0;
					if(empty($showLastMessage)){
						$lastMessageInfo = MessageApiHelper::getOrderLastMessage($order->order_source_order_id,$order_source_itemid_list,$order->source_buyer_user_id.$order->selleruserid);
						if(empty($lastMessageInfo)){
							$lastMessage = '';
							$showLastMessage = 1;
						}else{
							$lastMessage='N/A';
							if(isset($lastMessageInfo['send_or_receiv']))
								if((int)$lastMessageInfo['send_or_receiv']==1){
									$talk='???';
									$talkTo ='??????';
								}else{
									$talk='??????';
									$talkTo ='???';
								}
								if(!empty($lastMessageInfo['last_time']))
									$lastTime=$lastMessageInfo['last_time'];
								else 
									$lastTime='--???--???--???';
								if(!empty($lastMessageInfo['content']))
									$lastMessage=$lastMessageInfo['content'];
								if(strlen($lastMessage)>200){
									$lastMessage = substr($lastMessage,0,200).'...';
								}
								$lastMessage = $talk.'???'.$lastTime.'???'.$talkTo.'??????<br>'.$lastMessage;
								
								if(!empty($envelope_class) && $envelope_class=='egicon-envelope-remove')
									$lastMessage = '<span style="color:red">'.$lastMessage.'</span>';
								else 
									$lastMessage = '<span style="">'.$lastMessage.'</span>';
						}
					}
					//?????????????????????????????? end
				?>
				
				<tr class="xiangqing <?=$order->order_id?>">
					<td style="border:1px solid #d9effc;"><img src="<?=$item->photo_primary?>" width="60px" height="60px"></td>
					<td colspan="2" style="border:1px solid #d9effc;text-align:justify;">
						SKU:<b><?=$item->sku?></b><br>
						[<?=$item->order_source_itemid?>]<a target="_blank" href="http://www.ebay.com/itm/<?=$item->order_source_itemid?>"><?=$item->product_name?></a><br>
					</td>
					<td  style="border:1px solid #d9effc">
						<?=$item->quantity?>
						<?php if (strlen($item->shipping_price)):?>
						<p><strong title="??????"><?=$item->shipping_price?>&nbsp;<?=$order->currency?></strong></p>
						<?php endif;?>
					</td>
					<?php if ($key=='0'):?>
					<td rowspan="<?=count($order->items)?>" style="border:1px solid #d9effc">
						<font color="#8b8b8b">??????:</font><br>
						<b><?php if ($order->default_warehouse_id>0&&count($warehouses)){echo $warehouses[$order->default_warehouse_id];}?></b>
					</td>
					<td rowspan="<?=count($order->items)?>" style="border:1px solid #d9effc">
						<font color="#8b8b8b">?????????/??????:</font><br>
						<b><?=$order->source_buyer_user_id?>
						<br><?=$order->consignee_email?></b>
					</td>
					<?php endif;?>
					<td colspan="2" style="border:1px solid #d9effc">
						<font color="#8b8b8b">SRN:</font><b><?=$item->order_source_srn?>[<?=$order->selleruserid?>]</b><br>
						<font color="#8b8b8b">????????????:</font><b><?=$order->order_source_create_time>0?date('Y-m-d H:i:s',$order->order_source_create_time):''?></b>
					</td>
					<?php if ($key=='0'):?>
					<td colspan="2"  rowspan="<?=count($order->items)?>"  width="150px" style="word-break:break-all;word-wrap:break-word;border:1px solid #d9effc">
						<font color="#8b8b8b">????????????:</font>
						<b><?=!empty($order->user_message)?$order->user_message:''?>
							<?php if(!empty($lastMessage) && empty($showLastMessage)){
								echo '<a href="javascript:void(0);" onclick="ShowDetailMessage(\''.$order->selleruserid.'\',\''.$order->source_buyer_user_id.'\',\'ebay\',\'\',\'\',\'\',\'\',\'\',\''.$order->order_source_order_id.'\')">';
								echo $lastMessage;
								echo '</a>';
								$showLastMessage=1;
							} ?>
						</b>
					</td>
					<td  rowspan="<?=count($order->items)?>" width="150" style="word-break:break-all;word-wrap:break-word;border:1px solid #d9effc">
					<span><font color="red"><?=$order->desc?></font></span>
						<a href="javascript:void(0)" style="border:1px solid #00bb9b;" onclick="updatedesc('<?=$order->order_id?>',this)" oiid="<?=$order->order_id?>"><font color="00bb9b">??????</font></a>
					</td>
					<?php endif;?>
				</tr>	
				<?php endforeach;endif;?>
			<?php endforeach;endif;?>
			</table>
			<div class="btn-group" >
			<?=LinkPager::widget([
			    'pagination' => $pages,
			]);
			?>
			</div>
			<?=SizePager::widget(['pagination'=>$pages , 'pageSizeOptions'=>array( 5 , 20 , 50 , 100 , 200 ,500), 'class'=>'btn-group dropup'])?>
		</form>
	</div>

<div style="clear: both;"></div>
</div></div>
<div style="display: none;">
<?=$divTagHtml?>
<?=$div_event_html?>
<div id="div_ShippingServices"><?=Html::dropDownList('demo_shipping_method_code',@$order->default_shipping_method_code,CarrierApiHelper::getShippingServices())?></div>
</div>

<script>
//????????????
function doaction(val){
	//????????????????????????????????????
	if(val==""){
        bootbox.alert("?????????????????????");return false;
    }
    if($('.ck:checked').length==0&&val!=''){
    	bootbox.alert("???????????????????????????");return false;
    }
	switch(val){
		case 'checkorder':
			idstr='';
			$('input[name="order_id[]"]:checked').each(function(){
				idstr+=','+$(this).val();
			});
			$.post('<?=Url::to(['/order/order/checkorderstatus'])?>',{orders:idstr},function(result){
				bootbox.alert(result);
				location.reload();
			});
			break;
		case 'signshipped':
			document.a.target="_blank";
			document.a.action="<?=Url::to(['/order/order/signshipped'])?>";
			document.a.submit();
			document.a.action="";
			break;
		case 'deleteorder':
			if(confirm('????????????????????????????????????????????????????????????????????????????')){
				document.a.target="_blank";
    			document.a.action="<?=Url::to(['/order/order/deleteorder'])?>";
    			document.a.submit();
    			document.a.action="";
			}
			break;
		case 'signwaitsend':
			idstr='';
			$('input[name="order_id[]"]:checked').each(function(){
				idstr+=','+$(this).val();
			});
			$.post('<?=Url::to(['/order/order/signwaitsend'])?>',{orders:idstr},function(result){
				bootbox.alert(result);
				location.reload();
			});
			break;
		case 'signpayed':
			idstr='';
			$('input[name="order_id[]"]:checked').each(function(){
				idstr+=','+$(this).val();
			});
			$.post('<?=Url::to(['/order/order/signpayed'])?>',{orders:idstr},function(result){
				bootbox.alert(result);
				location.reload();
			});
			break;
		case 'givefeedback':
			document.a.target="_blank";
			document.a.action="<?=Url::to(['/order/order/feedback'])?>";
			document.a.submit();
			document.a.action="";
			break;
		case 'dispute':
			document.a.target="_blank";
			document.a.action="<?=Url::to(['/order/order/dispute'])?>";
			document.a.submit();
			document.a.action="";
			break;
		case 'mergeorder':
			document.a.target="_blank";
			document.a.action="<?=Url::to(['/order/order/mergeorder'])?>";
			document.a.submit();
			document.a.action="";
			break;
		case 'changeshipmethod':
			var thisOrderList =[];
			idstr='';
			$('input[name="order_id[]"]:checked').each(function(){
				
				if ($(this).parents("tr:contains('?????????')").length == 0) return;
				
				thisOrderList.push($(this).val());
				if (idstr != '') idstr+=',';
				idstr+=$(this).val();
			});

			if (idstr ==''){
				bootbox.alert('??????????????????????????????????????????');
				return;
			}

			var html  = '????????????????????????????????????<br>'+idstr +'<br><br>??????????????????????????????????????? ???<select name="change_shipping_method_code">'+$('select[name=demo_shipping_method_code]').html()+'</select>';
			bootbox.dialog({
				title: Translator.t("????????????"),
				className: "order_info", 
				message: html,
				buttons:{
					Ok: {  
						label: Translator.t("??????"),  
						className: "btn-primary",  
						callback: function () { 
							return changeShipMethod(thisOrderList , $('select[name=change_shipping_method_code]').val());
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
			
			break;
		default:
			return false;
			break;
	}
}
//????????????
function doactionone(val,orderid){
	//????????????????????????????????????
	if(val==""){
        bootbox.alert("?????????????????????");return false;
    }
	if(orderid == ""){ bootbox.alert("???????????????");return false;}
	switch(val){
		case 'checkorder':
			$.post('<?=Url::to(['/order/order/checkorderstatus'])?>',{orders:orderid},function(result){
				bootbox.alert(result);
				location.reload();
			});
			break;
		case 'signshipped':
			window.open("<?=Url::to(['/order/order/signshipped'])?>"+"?order_id="+orderid,'_blank')
			break;
		case 'deleteorder':
			if(confirm('????????????????????????????????????????????????????????????????????????????')){
				window.open("<?=Url::to(['/order/order/deleteorder'])?>"+"?order_id="+orderid,'_blank')
			}
			break;
		case 'signpayed':
			$.post('<?=Url::to(['/order/order/signpayed'])?>',{orders:orderid},function(result){
				bootbox.alert(result);
				location.reload();
			});
			break;
		case 'givefeedback':
			window.open("<?=Url::to(['/order/order/feedback'])?>"+"?order_id="+orderid,'_blank')
			break;
		case 'dispute':
			window.open("<?=Url::to(['/order/order/dispute'])?>"+"?order_id="+orderid,'_blank')
			break;
		case 'mergeorder':
			window.open("<?=Url::to(['/order/order/mergeorder'])?>"+"?order_id="+orderid,'_blank')
			break;
		case 'history':
			window.open("<?=Url::to(['/order/logshow/list'])?>"+"?orderid="+orderid,'_blank');
			break;
		case 'getorderno':
			OrderCommon.setShipmentMethod(orderid);
			break;
		case 'signwaitsend':
			OrderCommon.shipOrder(orderid);
			break;
		case 'changemanual':
			changemanual(orderid);
			break;
		default:
			return false;
			break;
	}
}
//????????????
function exportorder(type){
	if(type==""){
		bootbox.alert("?????????????????????");return false;
    }
	if($('.ck:checked').length==0&&type!=''){
		bootbox.alert("???????????????????????????");return false;
    }
    var idstr='';
	$('input[name="order_id[]"]:checked').each(function(){
		idstr+=','+$(this).val();
	});
	window.open('<?=Url::to(['/order/excel/export-excel'])?>'+'?orderids='+idstr+'&excelmodelid='+type);
}

//?????????????????????????????????
function movestatus(val){
	if(val==""){
		bootbox.alert("?????????????????????");return false;
    }
	if($('.ck:checked').length==0&&val!=''){
		bootbox.alert("???????????????????????????");return false;
    }
    var idstr='';
	$('input[name="order_id[]"]:checked').each(function(){
		idstr+=','+$(this).val();
	});
	$.post('<?=Url::to(['/order/order/movestatus'])?>',{orderids:idstr,status:val},function(result){
		bootbox.alert('???????????????');
	});
}
//??????????????????
function importordertracknum(){
	if($("#order_tracknum").val()){
		$.ajaxFileUpload({  
			 url:'<?=Url::to(['/order/order/importordertracknum'])?>',
		     fileElementId:'order_tracknum',
		     type:'post',
		     dataType:'json',
		     success: function (result){
			     if(result.ack=='failure'){
					bootbox.alert(result.message);
				 }else{
					bootbox.alert('???????????????');
				 }
		     },  
			 error: function ( xhr , status , messages ){
				 bootbox.alert(messages);
		     }  
		});  
	}else{
		bootbox.alert("???????????????");
	}
}
//???????????????????????????
function changemanual(orderid,obj){
	$.showLoading();
	$.post('<?=Url::to(['/order/order/changemanual'])?>',{orderid:orderid},function(result){
		$.hideLoading();
		if(result == 'success'){
			bootbox.alert('???????????????');
			var str;
			str=$('#manual_'+orderid).html();
			if(str.indexOf('??????')>=0){
				$('#manual_'+orderid).html('');			
			}else{
				$('#manual_'+orderid).html('<span class="exception_manual" title="??????"><\/span>');	
			}
		}else{
			bootbox.alert(result);
		}
	});
}

//?????????????????????
function setusertab(orderid,tabobj){
	var tabid = $(tabobj).val();
	$.post('<?=Url::to(['/order/order/setusertab'])?>',{orderid:orderid,tabid:tabid},function(result){
		if(result == 'success'){
			bootbox.alert('???????????????');
		}else{
			bootbox.alert(result);
		}
	});
}

//????????????
function updatedesc(orderid,obj){
	var desc=$(obj).prev();
    var oiid=$(obj).attr('oiid');
	var html="<textarea name='desc' style='width:200xp;height:60px'>"+desc.text()+"</textarea><input type='button' onclick='ajaxdesc(this)' value='??????' oiid='"+oiid+"'>";	
    desc.html(html);
    $(obj).toggle();
}
function ajaxdesc(obj){
	 var obj=$(obj);
	 var desc=$(obj).prev().val();
	 var oiid=$(obj).attr('oiid');
	  $.post('<?=Url::to(['/order/order/ajaxdesc'])?>',{desc:desc,oiid:oiid},function(r){
		  retArray=$.parseJSON(r);
		  if(retArray['result']){
		      obj.parent().next().toggle();
		      var html="<font color='red'>"+desc+"</font> <span id='showresult' style='background:yellow;'>"+retArray['message']+"</span>"
		      obj.parent().html(html);
		      setTimeout("showresult()",3000);
		  }else{
		      alert(retArray['message']);
		  }
	  })
}
function showresult(){
    $('#showresult').remove();
}

function dosearch(name,val){
	$('#'+name).val(val);
	document.form1.submit();
}
//????????????????????????

function cleform(){
	$(':input','#form1').not(':button, :submit, :reset, :hidden').val('').removeAttr('checked').removeAttr('selected');
	$('select[name=keys]').val('order_id');
	$('select[name=timetype]').val('soldtime');
	$('select[name=ordersort]').val('soldtime');
	$('select[name=ordersorttype]').val('desc');
}

//?????????????????????
function sendmessage(orderid){
	var Url='<?=Url::to(['/order/order/sendmessage'])?>';
	$.ajax({
        type : 'post',
        cache : 'false',
        data : {orderid : orderid},
		url: Url,
        success:function(response) {
        	$('#myMessage .modal-content').html(response);
        	$('#myMessage').modal('show');
        }
    });
}

//????????????
function mutisearch(){
	var status = $('.mutisearch').is(':hidden');
	if(status == true){
		//?????????
		$('.mutisearch').show();
		$('#simplesearch').html('??????<span class="glyphicon glyphicon-menu-up"></span>');
		return false;
	}else{
		$('.mutisearch').hide();
		$('#simplesearch').html('????????????<span class="glyphicon glyphicon-menu-down"></span>');
		return false;
	}
	
}

//???????????????????????????
function spreadorder(obj,id){
	if(typeof(id)=='undefined'){
		//??????????????????????????????????????????
		var html = $(obj).parent().html();
		if(html.indexOf('minus')!=-1){
			//???????????????????????????,'-'?????????
			$('.xiangqing').hide();
			$(obj).parent().html('<span class="glyphicon glyphicon-plus checkbox" onclick="spreadorder(this);">');
			$('.orderspread').attr('class','orderspread glyphicon glyphicon-plus checkbox');
			return false;
		}else{
			//???????????????????????????,'+'?????????
			$('.xiangqing').show();
			$(obj).parent().html('<span class="glyphicon glyphicon-minus checkbox" onclick="spreadorder(this);">');
			$('.orderspread').attr('class','orderspread glyphicon glyphicon-minus checkbox');
			return false;
		}
	}else{
		//????????????ID??????????????????????????????????????????
		var html = $(obj).parent().html();
		if(html.indexOf('minus')!=-1){
			//???????????????????????????,'-'?????????
			$('.'+id).hide();
			$(obj).attr('class','orderspread glyphicon glyphicon-plus checkbox');
			return false;
		}else{
			//???????????????????????????,'+'?????????
			$('.'+id).show();
			$(obj).attr('class','orderspread glyphicon glyphicon-minus checkbox');
			return false;
		}
	}
}

//??????????????????
function dosyncorder(){
	var Url=global.baseUrl +'order/order/syncmt';
	$.ajax({
        type : 'get',
        cache : 'false',
        data : {},
		url: Url,
        success:function(response) {
        	$('#syncorderModal .modal-content').html(response);
        	$('#syncorderModal').modal('show');
        }
    });
}

function changeShipMethod(orderIDList , shipmethod){
	$.ajax({
		type: "POST",
			dataType: 'json',
			url:'/order/order/changeshipmethod', 
			data: {orderIDList : orderIDList , shipmethod : shipmethod },
			success: function (result) {
				//bootbox.alert(result.message);
				if (result.success == false) 
					bootbox.alert(result.message);
				else{
					bootbox.alert({message:Translator.t("???????????????") , callback: function() {  
		                window.location.reload(); 
		            },  
		            });
				}
				return false;
			},
			error: function(){
				bootbox.alert("Internal Error");
				return false;
			}
	});
	return false;
}

function OmsViewTracker(obj){
	var s_trackingNo = $(obj).has('.text-success');
	if(typeof(s_trackingNo)!=='undefined' && s_trackingNo.length>0){
		var qtip = $(obj).find(".order-info").data('hasqtip');
		if(typeof(qtip)=='undefined')
			return false;
		var opened = $("#qtip-"+qtip).css("display");
		if(opened=='block')
			return true;
		$.ajax({
			type: "POST",
			dataType: 'json',
			url:'/order/order/oms-view-tracker', 
			data: {invoker: 'ebay-Oms'},
			success: function (result) {
				return true;
			},
			error :function () {
				return false;
			}
		});	
	}
}
</script>