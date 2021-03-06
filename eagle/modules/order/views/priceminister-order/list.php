<?php
use yii\helpers\Html;
use yii\widgets\LinkPager;
use yii\helpers\Url;
use eagle\modules\order\models\OdOrder;
use eagle\modules\carrier\apihelpers\CarrierApiHelper;
use eagle\modules\inventory\helpers\InventoryApiHelper;
use eagle\modules\order\helpers\OrderTagHelper;
use eagle\modules\carrier\helpers\CarrierHelper;
use eagle\widgets\SizePager;
use eagle\models\SaasPriceministerUser;
use eagle\models\catalog\Product;
use eagle\modules\util\helpers\StandardConst;
use eagle\modules\order\helpers\PriceministerOrderInterface;
use eagle\modules\tracking\helpers\TrackingApiHelper;
use eagle\modules\tracking\helpers\TrackingHelper;
use eagle\modules\order\helpers\PriceministerOrderHelper;
use eagle\modules\tracking\models\Tracking;
use eagle\modules\util\helpers\ImageCacherHelper;
use eagle\modules\carrier\helpers\CarrierOpenHelper;
use eagle\modules\util\helpers\TranslateHelper;
use eagle\modules\order\helpers\OrderFrontHelper;
use eagle\modules\util\helpers\ConfigHelper;
use eagle\modules\message\apihelpers\MessageApiHelper;
use eagle\modules\order\helpers\OrderHelper;
use eagle\modules\tracking\helpers\CarrierTypeOfTrackNumber;
use eagle\modules\util\helpers\SysBaseInfoHelper;
use eagle\modules\order\helpers\OrderListV3Helper;
use eagle\modules\util\helpers\RedisHelper;

$this->registerCssFile(\Yii::getAlias('@web').'/css/order/order.css');
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/OrderTag.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/orderOrderList.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/origin_ajaxfileupload.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/orderCommon.js?v=".OrderHelper::$OrderCommonJSVersion, ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/priceministerOrder/priceministerOrderList.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJs("OrderTag.TagClassList=".json_encode($tag_class_list).";" , \yii\web\View::POS_READY);
$this->registerJs("OrderList.initClickTip();" , \yii\web\View::POS_READY);

$this->registerCssFile(\Yii::getAlias('@web')."/css/message/customer_message.css");
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/message/customer_message.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/priceministerOrder/priceminister_manual_sync.js", ['depends' => ['yii\web\JqueryAsset','eagle\assets\PublicAsset']]);


$uid = \Yii::$app->user->id;

//$next_show = \Yii::$app->redis->hget('PriceministerOms_DashBoard',"user_$uid".".next_show");
$next_show = RedisHelper::RedisGet('PriceministerOms_DashBoard',"user_$uid".".next_show");
$show=true;
if(!empty($next_show)){
	if(time()<strtotime($next_show))
		$show=false;
}
if(!empty($_REQUEST))
	$show=false;

/*
######################################	??????????????????start	#################################################################
$important_change_tip_show = false;
//$important_change_tip_times= \Yii::$app->redis->hget('PriceministerOms_VerChangeTip',"user_$uid".".oms-2-1");//redis????????????????????????
$important_change_tip_times = RedisHelper::RedisGet('PriceministerOms_VerChangeTip',"user_$uid".".oms-2-1");
if(empty($important_change_tip_times))
	$important_change_tip_times = 0;
if($important_change_tip_times<3){
	$important_change_tip_show = true;
}
if($important_change_tip_show){
	$showDashBoard = false;
	$this->registerJs("showImportantChangeTip();" , \yii\web\View::POS_READY);
	$important_change_tip_times = (int)$important_change_tip_times+1;
	//\Yii::$app->redis->hset('PriceministerOms_VerChangeTip',"user_$uid".".oms-2-1",$important_change_tip_times);;
	RedisHelper::RedisSet('PriceministerOms_VerChangeTip',"user_$uid".".oms-2-1",$important_change_tip_times);
}
#####################################	??????????????????end		#################################################################
*/

if($show)
	$this->registerJs("showDashBoard(1);" , \yii\web\View::POS_READY);

$this->registerJs("$('.prod_img').popover();" , \yii\web\View::POS_READY);
$this->registerJs("$('.icon-ignore_search').popover();" , \yii\web\View::POS_READY);

$orderStatus21 = OdOrder::getOrderStatus('oms21'); // oms 2.1 ???????????????
$pm_source_status_mapping = PriceministerOrderHelper::$orderStatus;

$non17Track = CarrierTypeOfTrackNumber::getNon17TrackExpressCode();

$tmpCustomsort = Odorder::$customsort;

//??????????????????
$showMergeOrder = 0;
if (200 == @$_REQUEST['order_status'] && 223 == @$_REQUEST['exception_status']){
	$this->registerJs("OrderCommon.showMergeRow();" , \yii\web\View::POS_READY);
	$nowMd5 = "";
	$showMergeOrder = 1;
}

$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/orderListV3.js?v=".\eagle\modules\order\helpers\OrderListV3Helper::$OrderCommonJSV3, ['depends' => ['yii\web\JqueryAsset']]);
 
?>

<script type="text/javascript" src="//www.17track.net/externalcall.js"></script>

<style>
.table td,.table th{
	text-align: center;
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
.exception_201,.exception_202,.exception_221,.exception_210,.exception_222,.exception_223,.exception_299{
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
	background-position:-200px -10px;
}
.text-invalid{
	color: #8a6d3b;
	text-decoration: line-through;
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
.pm_fbc_inco{
	width:15px;
	height:15px;
	background:url("/images/priceminister/clogpicto.jpg") no-repeat;
	display: block;
    background-size: 15px;
	float:left;
}
#dash-board-enter{
	position: fixed;
	bottom: 30px;
	left: 0px;
	width: 34px;
	height: 56px;
	padding: 3px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 0px 5px 5px 0px;	
	cursor: pointer;
}
#pm-oms-reminder-content{
	left: 0px;
	padding: 5px;
    border: 2px solid transparent;
    border-radius: 5px;
	float: left;
    width: 100%;
	padding-bottom: 10px;
}
#pm-oms-reminder-close{
	-webkit-appearance: none;
    padding: 0;
    cursor: pointer;
    background: transparent;
    border: 0;
	color: #609ec4;
	float: right;
    font-size: 21px;
    font-weight: bold;
    line-height: 1;
    text-shadow: 0 1px 0 #fff;
}
#pm-oms-reminder-close-day{
	-webkit-appearance: none;
    padding: 0;
    cursor: pointer;
    background: transparent;
    border: 0;
	color: #609ec4;
	float: right;
    font-size: 12px;
    font-weight: bold;
    line-height: 1;
    text-shadow: 0 1px 0 #fff;
}
.pm-oms-weird-status-wfs{
	background: url(/images/priceminister/priceminister_icon.png) no-repeat -1px -1627px;
    background-size: 100%;
    float: left;
    width: 18px;
    height: 18px;
}
td .popover{
	max-width: inherit;
    max-height: inherit;
}
.popover{
	min-width: 200px;
}
.text-success{
	color: #2ecc71!important;
}
.text-warning{
	color: #8a6d3b!important;
}
.iv-btn.btn-important{
	padding-right:15px!important;
}
</style>	
<div class="tracking-index col2-layout">
<?=$this->render('_leftmenu',['counter'=>$counter]);?>

<div class="hidden" id="dash-board-enter" onclick="showDashBoard(0)" style="background-color:#374655;color: white;" title="??????dash-board">????????????????????????</div>

<div class="content-wrapper" >
	<?php $autoAccept = ConfigHelper::getConfig("PriceministerOrder/AutoAccept",'NO_CACHE');?>
	
	<div style="width:100%;display:inline-block">
		<div style="font-size:14px;padding:5px 5px;float:left;margin:0px;" class="alert alert-success" role="alert">
			<label>??????????????????????????????(??????????????????)???</label>
			<label for="auto_accept_Y">???</label><input type="radio" name="auto_accept" id="auto_accept_Y" value="true" <?=(!empty($autoAccept) && $autoAccept=='true')?'checked':''?> ><span style="margin:0px 5px;"></span>
			<label for="auto_accept_N">???</label><input type="radio" name="auto_accept" id="auto_accept_N" value="false" <?=(empty($autoAccept) || $autoAccept=='false')?'checked':''?>><span style="margin:0px 5px;"></span>
			<button type="button" onclick="setAutoAccept()" class="btn-xs btn-primary">??????</button>
			<span qtipkey="pm_auto_accept_order"></span>
		</div>
	</div>
	
	<?php $problemAccounts = PriceministerOrderInterface::getUserAccountProblems($uid); ?>
	<?php if(!empty($problemAccounts['token_expired'])){ $problemAccountNames=[];?>
	<?php foreach ($problemAccounts['token_expired'] as $account){
		$problemAccountNames[] = $account['store_name'];
	}?>
	<!-- ???????????????????????? -->
	<div class="alert alert-danger" role="alert" style="width:100%;">
		<span>????????????Priceminister?????????<?=implode(' , ', $problemAccountNames) ?> ????????????token?????????<br>??????????????????????????????????????????</span>
	</div>
	<?php } ?>
	
	<!-- oms 2.1 nav start  -->
	<!-- 
	<?php 
	if (in_array(@$_REQUEST['order_status'], [OdOrder::STATUS_NOPAY, OdOrder::STATUS_PAY  ,OdOrder::STATUS_WAITSEND , OdOrder::STATUS_SHIPPED]) ){
		echo $order_nav_html;
	}
	?>
	 -->
	<!-- oms 2.1 nav end  -->
	
		
<!-- ----------------------------------??????????????????????????? start--------------------------------------------------------------------------------------------- -->	
	<?php 
		if (@$_REQUEST['order_status'] == 200 || in_array(@$_REQUEST['exception_status'], ['0','210','203','222','202','223','299']) ){
			echo '<ul class="clearfix"><li style="float: left;line-height: 22px;">???????????????</li><li style="float: left;line-height: 22px;">';
			echo OrderFrontHelper::displayOrderPaidProcessHtml($counter,'priceminister',[(string)OdOrder::PAY_PENDING]);
			echo '</li><li class="clear-both"></li></ul>';
		}
	?>
<!-- ----------------------------------??????????????????????????? end--------------------------------------------------------------------------------------------- -->
	
	<!-- ----------------------------------???????????? start--------------------------------------------------------------------------------------------- -->	
	<div>
		<form class="form-inline" id="form1" name="form1" action="/order/priceminister-order/list" method="post">
		<input type="hidden" name ="select_bar" value="<?php echo isset($_REQUEST['select_bar'])?$_REQUEST['select_bar']:'';?>">
		<input type="hidden" name ="order_status" value="<?php echo isset($_REQUEST['order_status'])?$_REQUEST['order_status']:'';?>">
		<input type="hidden" name ="exception_status" value="<?php echo isset($_REQUEST['exception_status'])?$_REQUEST['exception_status']:'';?>">
		<input type="hidden" name ="pay_order_type" value="<?php echo isset($_REQUEST['pay_order_type'])?$_REQUEST['pay_order_type']:'';?>">
		<input type="hidden" name ="is_merge" value="<?php echo isset($_REQUEST['is_merge'])?$_REQUEST['is_merge']:'';?>">
		<?=Html::hiddenInput('customsort', @$_REQUEST['customsort'],['id'=>'customsort']);?>
		<?=Html::hiddenInput('ordersorttype', @$_REQUEST['ordersorttype'],['id'=>'ordersorttype']);?>
		<!-- ----------------------------------????????? --------------------------------------------------------------------------------------------- -->
		<div style="margin:10px 0px 0px 0px">
		<?php //echo Html::dropDownList('selleruserid',@$_REQUEST['selleruserid'],$priceministerUsersDropdownList,['class'=>'iv-input','id'=>'selleruserid','style'=>'margin:0px;width:150px','prompt'=>'????????????'])?>
			<?php
			//?????????????????? S
			?>
			<input type="hidden" name ="selleruserid_combined" id="selleruserid_combined" value="<?php echo isset($_REQUEST['selleruserid_combined'])?$_REQUEST['selleruserid_combined']:'';?>">
			<?php
			$omsPlatformFinds = array();
// 			$omsPlatformFinds[] = array('event'=>'OrderCommon.order_platform_find(\'oms\',\'priceminister\',\'\')','label'=>'??????');
			
			if(count($priceministerUsersDropdownList) > 0){
				$priceministerUsersDropdownList['select_shops_xlb'] = '????????????';
	
				foreach ($priceministerUsersDropdownList as $tmp_selleruserKey => $tmp_selleruserid){
					$omsPlatformFinds[] = array('event'=>'OrderCommon.order_platform_find(\'oms\',\'priceminister\',\''.$tmp_selleruserKey.'\')','label'=>$tmp_selleruserid);
				}
				
				$pcCombination = OrderListV3Helper::getPlatformCommonCombination(array('type'=>'oms','platform'=>'priceminister'));
				if(count($pcCombination) > 0){
					foreach ($pcCombination as $pcCombination_K => $pcCombination_V){
						$omsPlatformFinds[] = array('event'=>'OrderCommon.order_platform_find(\'oms\',\'priceminister\',\''.'com-'.$pcCombination_K.'-com'.'\')',
							'label'=>'??????-'.$pcCombination_K, 'is_combined'=>true, 'combined_event'=>'OrderCommon.platformcommon_remove(this,\'oms\',\'priceminister\',\''.$pcCombination_K.'\')');
					}
				}
			}
			echo OrderListV3Helper::getDropdownToggleHtml('????????????', $omsPlatformFinds);
			if(!empty($_REQUEST['selleruserid_combined'])){
				echo '<span onclick="OrderCommon.order_platform_find(\'oms\',\'priceminister\',\'\')" class="glyphicon glyphicon-remove" style="cursor:pointer;font-size: 10px;line-height: 20px;color: red;margin-right:5px;" aria-hidden="true" data-toggle="tooltip" data-placement="top" data-html="true" title="" data-original-title="??????????????????????????????"></span>';
			}
			//?????????????????? E
			?>
			
			
			<div class="input-group iv-input">
		        <?php $sel = [
		        	'order_source_order_id'=>'PM?????????',
					'sku'=>'SKU',
					'tracknum'=>'?????????',
					'buyerid'=>'????????????',
		        	'consignee'=>'????????????',
					'order_id'=>'???????????????',
					'root_sku'=>'??????SKU',
					'product_name'=>'????????????',
				]?>
				<?=Html::dropDownList('keys',@$_REQUEST['keys'],$sel,['class'=>'iv-input','style'=>'width:140px;margin:0px','onchange'=>'OrderCommon.keys_change_find(this)'])?>
		      	<?=Html::textInput('searchval',@$_REQUEST['searchval'],['class'=>'iv-input','id'=>'num','style'=>'width:250px','placeholder'=>'???????????????????????????Excel????????????'])?>
		      	
		    </div>
		    <?=Html::checkbox('fuzzy',@$_REQUEST['fuzzy'],['label'=>TranslateHelper::t('????????????')])?>
		    <?=Html::submitButton('??????',['class'=>"iv-btn btn-search btn-spacing-middle",'id'=>'search'])?>
		    <!-- 
	    	<?=Html::button('??????',['class'=>"iv-btn btn-search btn-spacing-middle",'onclick'=>"javascript:cleform();"])?>
			 -->
	    	<a id="simplesearch" href="#" style="font-size:12px;text-decoration:none;" onclick="mutisearch();">????????????<span class="glyphicon glyphicon-menu-down"></span></a>	 
	    	<a target="_blank" title="????????????" class="iv-btn btn-important" href="/order/order/manual-order-box?platform=priceminister">????????????</a>
	    	
	    	<!----------------------------------------------------------- ????????????  ----------------------------------------------------------->
	    	<div style="display: inline-block;">
	    		<a id="sync-btn" href="sync-order-ready" target="_modal" title="????????????" class="iv-btn btn-important" auto-size style="color:white;" btn-resolve="false" btn-reject="false">????????????</a>
	    		<span qtipkey="pm_manual-sync-order"></span>
	    	</div>
			
	    	<!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
			<?php if (!empty($_REQUEST['menu_select']) && $_REQUEST['menu_select'] =='all'):?>
			<div class="pull-right" style="height: 40px;">
				<?=Html::button(TranslateHelper::t('????????????'),['class'=>"iv-btn btn-important",'onclick'=>"OrderCommon.showAccountSyncInfo(this);",'name'=>'btn_account_sync_info','data-url'=>'/order/priceminister-order/order-sync-info'])?>
			</div>
			<?php endif;?>
			
	    	<?php
	    	/*
	    	if (!empty($counter['custom_condition'])){
	    		$sel_custom_condition = array_merge(['??????????????????'] , array_keys($counter['custom_condition']));
	    	}else{
	    		$sel_custom_condition =['0'=>'??????????????????'];
	    	}
	    	
	    	echo Html::dropDownList('sel_custom_condition',@$_REQUEST['sel_custom_condition'],$sel_custom_condition,['class'=>'iv-input'])?>
	    	<?=Html::button('?????????????????????',['class'=>"iv-btn btn-search",'onclick'=>"showCustomConditionDialog()",'name'=>'btn_save_custom_condition'])
	    	*/
	    	?>
	    	<div class="mutisearch" <?php if ($showsearch!='1'){?>style="display: none;"<?php }?>>
			<!-- ----------------------------------?????????--------------------------------------------------------------------------------------------- -->
	    	<div style="margin:20px 0px 0px 0px">
			<?php $warehouses = InventoryApiHelper::getWarehouseIdNameMap(); $warehouses +=['-1'=>'?????????'];?>
			<?=Html::dropDownList('cangku',@$_REQUEST['cangku'],$warehouses,['class'=>'iv-input','prompt'=>'??????','style'=>'width:200px;margin:0px'])?>
			<?php echo ' '; ?>
			<?php 
			// ?????????
			$carriersProviderList = CarrierOpenHelper::getOpenCarrierArr('2');
			echo Html::dropDownList('carrier_code',@$_REQUEST['carrier_code'],$carriersProviderList,['class'=>'iv-input','prompt'=>'?????????','id'=>'order_carrier_code','style'=>'width:200px;margin:0px'])
			?>
			<?php $carriers=CarrierApiHelper::getShippingServices()?>
			<?=Html::dropDownList('shipmethod',@$_REQUEST['shipmethod'],$carriers,['class'=>'iv-input','prompt'=>'????????????','id'=>'shipmethod','style'=>'width:200px;margin:0px'])?>
			<?php //echo Html::dropDownList('profit_calculated',@$_REQUEST['profit_calculated'],[2=>'?????????',1=>'?????????'],['class'=>'iv-input','prompt'=>'?????????????????????','id'=>'profit_calculated']); ?>
			<?=Html::dropDownList('order_capture',@$_REQUEST['order_capture'],['N'=>'????????????','Y'=>'????????????'],['class'=>'iv-input','prompt'=>'????????????','id'=>'order_capture','style'=>'width:200px;margin:0px'])?>
			<?=Html::dropDownList('country',@$_REQUEST['country'],$countryArr,['class'=>'iv-input','prompt'=>'??????','id'=>'country','style'=>'width:200px;margin:0px'])?>
			</div>
			<!-- ----------------------------------?????????--------------------------------------------------------------------------------------------- -->
			<div style="margin:20px 0px 0px 0px">
			<?=Html::dropDownList('order_status',@$_REQUEST['order_status'],$orderStatus21,['class'=>'iv-input','prompt'=>'?????????????????????','id'=>'order_status','style'=>'width:200px;margin:0px'])?>
			<?=Html::dropDownList('order_source_status',@$_REQUEST['order_source_status'],$pm_source_status_mapping,['class'=>'iv-input','prompt'=>'PM????????????','id'=>'order_source_status','style'=>'width:200px;margin:0px'])?>
			
			 <?php $PayOrderTypeList = [''=>'?????????????????????'];
				$PayOrderTypeList+=Odorder::$payOrderType ;?>
			 <?=Html::dropDownList('pay_order_type',@$_REQUEST['pay_order_type'],$PayOrderTypeList,['class'=>'iv-input','style'=>'width:200px;margin:0px'])?>
			 
			<?php $reorderTypeList = [''=>'??????????????????'];
			$reorderTypeList+=Odorder::$reorderType ;?>
			<?=Html::dropDownList('reorder_type',@$_REQUEST['reorder_type'],$reorderTypeList,['class'=>'iv-input','style'=>'width:200px;margin:0px'])?>
			
			 <?php $exceptionStatusList = [''=>'????????????'];
				$exceptionStatusList+=Odorder::$exceptionstatus ;
				unset($exceptionStatusList['201']);//?????????
				unset($exceptionStatusList['299']);//?????????
				//$weirdstatus=[];
			 ?>
			 <?=Html::dropDownList('exception_status',@$_REQUEST['exception_status'],$exceptionStatusList,['class'=>'iv-input','style'=>'width:200px;margin:0px'])?>
			 <?php //echo Html::dropDownList('weird_status',@$_REQUEST['weird_status'],$weirdstatus,['class'=>'iv-input','prompt'=>'??????????????????','id'=>'weird_status','style'=>'width:250px;margin:0px']); ?>
			 </div>
			 <!-- ----------------------------------?????????--------------------------------------------------------------------------------------------- -->
			 <div style="margin:20px 0px 0px 0px">
			 <?php $TrackerStatusList = [''=>'??????????????????'];
			 	$tmpTrackerStatusList = Tracking::getChineseStatus('',true);
				$TrackerStatusList+= $tmpTrackerStatusList;?>
			 <?=Html::dropDownList('tracker_status',@$_REQUEST['tracker_status'],$TrackerStatusList,['class'=>'iv-input','style'=>'width:200px;margin:0px'])?>
			 
			 
			 <?=Html::dropDownList('timetype',@$_REQUEST['timetype'],['soldtime'=>'????????????','paidtime'=>'????????????','printtime'=>'????????????',/*'shiptime'=>'????????????'*/],['class'=>'iv-input'])?>
        	<?=Html::input('date','startdate',@$_REQUEST['startdate'],['class'=>'iv-input','style'=>'width:150px;margin:0px'])?>
        	???
			<?=Html::input('date','enddate',@$_REQUEST['enddate'],['class'=>'iv-input','style'=>'width:150px;margin:0px'])?>
			</div>
			<!-- ----------------------------------?????????--------------------------------------------------------------------------------------------- -->
			<div style="margin:20px 0px 0px 0px;display: inline-block;width:100%">
			<strong style="font-weight: bold;font-size:12px;">???????????????</strong>
			<?php foreach (OrderTagHelper::$OrderSysTagMapping as $tag_code=> $label){
				echo Html::checkbox($tag_code,@$_REQUEST[$tag_code],['label'=>TranslateHelper::t($label)]);
			}
			echo Html::checkbox('is_reverse',@$_REQUEST['is_reverse'],['label'=>TranslateHelper::t('??????')]);
			?>
			</div>
			<!-- ----------------------------------?????????--------------------------------------------------------------------------------------------- -->
			<div style="margin:20px 0px 0px 0px;display:inline-block;width:100%">

			<strong style="font-weight: bold;font-size:12px;float:left;margin:5px 0px 0px 0px;">??????????????????</strong>


			<?=Html::checkboxlist('sel_tag',@$_REQUEST['sel_tag'],$all_tag_list);?>

			</div>
			</div> 
			<!-- ---------------------------------- ???????????? ??????--------------------------------------------------------------------------------------------- -->
			
			<?php 
			if (@$_REQUEST['order_status'] != OdOrder::STATUS_CANCEL){
				\eagle\modules\order\helpers\OrderFrontHelper::displayOrderSyncShipStatusToolbar(@$_REQUEST['order_sync_ship_status'],'priceminister');
			}
			?>
			<div style="margin:20px 0px 0px 0px">
				<strong style="font-weight: bold;font-size:12px;">???????????????</strong>
				<?php
				if(empty($_REQUEST['customsort'])){
					$_REQUEST['customsort'] = 'soldtime';
				}
				
				foreach ($tmpCustomsort as $tag_code=> $label){
					echo "<a style='margin-right: 30px;' class=' ".($_REQUEST['customsort'] == $tag_code ? 'text-rever-important' : '')."' value='".$tag_code.
						"' sorttype='".@$_REQUEST['ordersorttype']."' onclick='OrderCommon.sortModeBtnClick(this)'>".$label.
						($_REQUEST['customsort'] == $tag_code ? " <span class='glyphicon glyphicon-sort-by-attributes".((empty($_REQUEST['ordersorttype']) || strtolower($_REQUEST['ordersorttype'])=='desc') ? '-alt' : '')."'></span>" : '').
						"</a>";
				}
				?>
			</div>
	    </div>
		</form>
	</div>
	<br><br>
<!-- ----------------------------------???????????? end--------------------------------------------------------------------------------------------- -->	

	<br>
	<div style="">
		<form name="a" id="a" action="" method="post">
		<?php 
		/*
		$doarr=[
			''=>'????????????',
			'checkorder'=>'????????????',
			'signshipped'=>'priceminister????????????',
			'mergeorder'=>'?????????????????????',
			'changeshipmethod'=>'??????????????????',
			//'getEmail'=>'???????????????????????????',
		];
		*/
		/* if (isset($_REQUEST['order_status'])&&$_REQUEST['order_status']>='300'){
			unset($doarr['signshipped']);
		} */
		?>
		<!-- 
		<div class="col-md-2">
		<?//=Html::dropDownList('do','',$doarr,['onchange'=>"doaction($(this).val());",'class'=>'form-control input-sm do']);?> 
		</div>
		 -->
		<?php if (isset($_REQUEST['order_status'])&&$_REQUEST['order_status']<'300'):?>
 		<!-- 
 		<div class="col-md-2" style="">
		<?php 
		/*
		$doCarrier=[
			''=>'????????????',
			'getorderno'=>'????????????',
			'signwaitsend'=>'????????????',
		];
		*/
		?>
		<?//=Html::dropDownList('do','',$doCarrier,['class'=>'form-control input-sm do-carrier do']);?>
		</div> 
		 -->
		<?php endif;?>
		<!-- 
		<div class="col-md-2">
		<?php 
		/*
			$movearr = [''=>'?????????']+OdOrder::$status;
			unset($movearr[100]);
			*/
		?>
			<?//=Html::dropDownList('do','',$movearr,['onchange'=>"movestatus($(this).val());",'class'=>'form-control input-sm do']);?> 
		</div>
		 -->
		<div style="width:100%;">
		<?php if (isset($_REQUEST['order_status']) && $_REQUEST['order_status'] =='200' ){
			
			//??????????????????????????????????????????????????????????????????,?????????????????????
			if( 1== 0){
			$PayBtnHtml = Html::button('??????????????????',['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('changeWHSM');"])."&nbsp;";
			$PayBtnHtml .= Html::button('??????????????????',['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('changeItemDeclarationInfo');"]). "&nbsp;";
			//$PayBtnHtml .= Html::button('???????????????',['class'=>"iv-btn btn-important",'onclick'=>"OrderCommon.getTrackNo()"]). "&nbsp;";
			//$PayBtnHtml .= Html::button('???????????????',['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('signwaitsend');"]). "&nbsp;";
				
			$doarr += ['changeWHSM'=>'???????????????????????????'];
			$doarr += ['outOfStock'=>'???????????????'];
			if (in_array(@$_REQUEST['exception_status'], ['223'])){
				echo Html::button(TranslateHelper::t('????????????'),['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('mergeorder');"]);
				echo '<span data-qtipkey="oms_order_exception_pending_merge_merge" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			
				//echo Html::button(TranslateHelper::t('???????????????'),['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('skipMerge');"]);
				//echo '<span data-qtipkey="oms_order_exception_pending_merge_skip" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			
				//echo Html::button(TranslateHelper::t('????????????'),['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('checkorder');"]);
				//echo '<span data-qtipkey="oms_order_status_check" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			}elseif (in_array(@$_REQUEST['exception_status'], ['202'])){
				echo Html::button(TranslateHelper::t('??????????????????'),['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('changeWHSM');"]);
				echo '<span data-qtipkey="oms_order_exception_no_shipment_assign" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			
				//echo Html::button(TranslateHelper::t('????????????'),['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('checkorder');"]);
				//echo '<span data-qtipkey="oms_order_status_check" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			}elseif (in_array(@$_REQUEST['exception_status'], ['222'])){
				echo Html::button(TranslateHelper::t('????????????'),['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('stockManage');"]);
				echo '<span data-qtipkey="oms_order_exception_out_of_stock_manage" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			
				//echo Html::button(TranslateHelper::t('????????????'),['class'=>"iv-btn click-to-tip btn-important",'onclick'=>"javascript:doaction('assignstock');"]);echo "&nbsp;";
				echo Html::button(TranslateHelper::t('????????????'),['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('outOfStock');"]);
				echo '<span data-qtipkey="oms_order_exception_out_of_stock_mark" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			
				//echo Html::button(TranslateHelper::t('????????????'),['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('checkorder');"]);
				//echo '<span data-qtipkey="oms_order_status_check" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			}elseif (in_array(@$_REQUEST['exception_status'], ['203'])){
				echo Html::button(TranslateHelper::t('????????????'),['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('changeWHSM');"]);echo "&nbsp;";
				echo '<span data-qtipkey="oms_order_exception_no_warehouse_assign" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			
				echo Html::button(TranslateHelper::t('????????????'),['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('checkorder');"]);
				echo '<span data-qtipkey="oms_order_status_check" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			}elseif (in_array(@$_REQUEST['exception_status'], ['210'])){
				echo Html::button(TranslateHelper::t('??????SKU'),['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('generateProduct');"]);echo "&nbsp;";
				echo '<span data-qtipkey="oms_order_exception_sku_not_exist_create" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			
				//echo Html::button(TranslateHelper::t('????????????'),['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('checkorder');"]);
				//echo '<span data-qtipkey="oms_order_status_check" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			}elseif (in_array(@$_REQUEST['exception_status'], ['299']) || @$_REQUEST['pay_order_type'] == 'ship' || empty($_REQUEST['pay_order_type'])){
				echo Html::button('???????????????',['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('signwaitsend');"]);
				echo '<span data-qtipkey="oms_batch_ship_pack" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			}else{
				//echo Html::button('????????????',['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('checkorder');"]);
				//echo '<span data-qtipkey="oms_order_status_check" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			}
			echo $PayBtnHtml;
			}
			
			$PayBtnHtml = Html::button('??????????????????',['class'=>"iv-btn btn-important",'onclick'=>"orderCommonV3.doaction('changeWHSM');"])."&nbsp;";
			$PayBtnHtml .= Html::button('??????????????????',['class'=>"iv-btn btn-important",'onclick'=>"orderCommonV3.doaction('changeItemDeclarationInfo');"]). "&nbsp;";
				
			$doarr += ['changeWHSM'=>'???????????????????????????'];
			$doarr += ['outOfStock'=>'???????????????'];
			if (in_array(@$_REQUEST['exception_status'], ['223'])){
				echo Html::button(TranslateHelper::t('????????????'),['class'=>"iv-btn btn-important",'onclick'=>"orderCommonV3.doaction('mergeorder');"]);
				echo '<span data-qtipkey="oms_order_exception_pending_merge_merge" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			}elseif (in_array(@$_REQUEST['exception_status'], ['299']) || @$_REQUEST['pay_order_type'] == 'ship' || empty($_REQUEST['pay_order_type'])){
				echo Html::button('???????????????',['class'=>"iv-btn btn-important",'onclick'=>"orderCommonV3.doaction('signwaitsend');"]);
				echo '<span data-qtipkey="oms_batch_ship_pack" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			}
			
			if (!empty($_REQUEST['is_merge'])){
				echo Html::button(TranslateHelper::t('????????????'),['class'=>"iv-btn btn-important",'onclick'=>"orderCommonV3.doaction('cancelMergeOrder');"]).'&nbsp;';
			}
			
			echo $PayBtnHtml;
		}
		if (@$_REQUEST['pay_order_type'] == 'reorder' || in_array(@$_REQUEST['exception_status'], ['203','222','202']) ){
			//echo Html::checkbox( 'chk_refresh_force','',['label'=>TranslateHelper::t('??????????????????')]);
		}
		$doarr += ['signshipped'=>'????????????(????????????)',/*'calculat_profit'=>'??????????????????'*/];
		if(isset($doarr['givefeedback']))
			unset($doarr['givefeedback']);
		if(isset($doarr['refreshOrder']))
			unset($doarr['refreshOrder']);
		?>
		
		<?php 
			$doDownListHtml = OrderListV3Helper::getDropdownToggleHtml('????????????', $doarr, 'orderCommonV3.doaction3');
			echo $doDownListHtml;
			//Html::dropDownList('do','',$doarr,['onchange'=>"orderCommonV3.doaction2(this);",'class'=>'iv-input do','style'=>'width:200px;margin:0px']);
		?> 
	
		<?php 
			$excelActionItems = array(
					'0'=>array('event'=>'OrderCommon.orderExcelprint(0)','label'=>'???????????????'),
					'1'=>array('event'=>'OrderCommon.orderExcelprint(1)','label'=>'??????????????????')
			);
			$excelDownListHtml = OrderListV3Helper::getDropdownToggleHtml('????????????', $excelActionItems);
			echo $excelDownListHtml;
			//echo Html::dropDownList('orderExcelprint','',['-1'=>'????????????','0'=>'???????????????','1'=>'??????????????????'],['onchange'=>"OrderCommon.orderExcelprint($(this).val())",'class'=>'iv-input do','style'=>'width:200px;margin:0px']);
		?>
		
		<div class="" style="line-height:20px;position: relative;-moz-border-radius: 3px;-webkit-border-radius: 3px;border-radius: 3px;display: inline-block;vertical-align: middle;">
			<button type="button" class="btn" style="padding:3px 10px!important;" onclick="syncAllUnClosedOrderStatus()">????????????????????????????????????</button>
		</div>
		<?php 
		if (isset($_REQUEST['order_status']) && $_REQUEST['order_status'] =='200' ){
			echo "<div style='float:right'>";
			echo Html::button(TranslateHelper::t('???????????????'),['class'=>"iv-btn btn-important",'onclick'=>"OrderCommon.importTrackNoBox('priceminister')"]);
			echo "</div>";
		}
		?>
		</div>
		<?php $divTagHtml = "";?>
		<?php $div_event_html = "";?>
		<br>
			<?php
			if (!empty($showMergeOrder) && 1 == 0){
			?>
		
			<table class="table table-condensed table-bordered" style="font-size:12px;">
			<tr>
				<th width="1%">
				<input id="ck_all" class="ck_0" type="checkbox">
				</th>
				<th width="10%"><b>PM?????????</b></th>
				<th width="12%"><b>??????SKU</b></th>
				<th width="10%"><b>????????????</b></th>
				<th width="17%"><b>????????????</b><span qtipkey="pm_oms_order_paid_time"></span></th>
				<th width="10%">
				<?=Html::dropDownList('country',@$_REQUEST['country'],$countryArr,['prompt'=>'????????????','style'=>'width:100px','onchange'=>"dosearch('country',$(this).val());"])?>
				</th>
				<th width="10%">
				<?=Html::dropDownList('shipmethod',@$_REQUEST['shipmethod'],$carriers,['prompt'=>'????????????','style'=>'width:100px','onchange'=>"dosearch('shipmethod',$(this).val());"])?>
				</th>
				<th width="10%" title="Priceminister??????"><b>PM??????</b><span qtipkey="oms_order_platform_status_priceminister"></span></th></th>
				<th width="10%">
					<b>???????????????</b><span qtipkey="oms_order_lb_status_description"></span>
				</th>
				<th width="10%"><b>????????????</b><span qtipkey="oms_order_carrier_status_description"></span></th>
				<th ><b>??????</b><span qtipkey="oms_order_action_priceminister"></span></th>
			</tr>
			<?php $carriers=CarrierApiHelper::getShippingServices(false)?>
			<?php $pm_customer_shipped_method = CarrierHelper::getPriceministerBuyerShippingServices()?>
			<?php 
				$allUserAccounts = SaasPriceministerUser::find()->where(['uid'=>$uid])->all();
				$saasAccounts = [];
				foreach ($allUserAccounts as $account){
					$saasAccounts[$account->username] = $account;
				}
			?>
			<?php if (count($models)):foreach ($models as $order):?>
			<?php
			$showLastMessage=0;
			if (!empty($showMergeOrder)){
				$groupOrderMd5 = md5($order->selleruserid.$order->consignee.$order->consignee_address_line1.$order->consignee_address_line2.$order->consignee_address_line3);
			}
			?>
			<tr style="background-color: #f4f9fc" <?= empty($groupOrderMd5)?"":"merge-row-tag='".$groupOrderMd5."'"; ?> >
				<td><label><input type="checkbox" class="ck" name="order_id[]" value="<?=$order->order_id?>"></label>
				</td>
				<td>
					<span><b><?=$order->order_source_order_id ?></b><br>?????????id???<?=(int)$order->order_id?></span><?=($order->order_type=='FBC')?"<span class='pm_fbc_inco' title='Priceminister FBC ??????'></span>":''?><br>
					<?php if ($order->seller_commenttype=='Positive'):?>
						<span style='background:green;'><a style="color: white" title="<?=$order->seller_commenttext?>">??????</a></span><br>
					<?php elseif($order->seller_commenttype=='Neutral'):?>
						<span style='background:yellow;'><a title="<?=$order->seller_commenttext?>">??????</a></span><br>
					<?php elseif($order->seller_commenttype=='Negative'):?>
						<span style='background:red;'><a title="<?=$order->seller_commenttext?>">??????</a></span><br>
					<?php endif;?>
					<?php if (!empty($order->weird_status)):?>
						<div class="no-qtip-icon" qtipkey="pm_order_weird_status_<?=$order->weird_status ?>"><span class="pm-oms-weird-status-wfs"></span></div>
					<?php endif;?>
					<?php if ($order->exception_status>0&&$order->exception_status!='201'):?>
						<div title="<?=OdOrder::$exceptionstatus[$order->exception_status]?>" class="exception_<?=$order->exception_status?>"></div>
					<?php endif;?>
					<?php if (strlen($order->user_message)>0):?>
						<div title="<?=OdOrder::$exceptionstatus[OdOrder::EXCEP_HASMESSAGE]?>" class="exception_<?=OdOrder::EXCEP_HASMESSAGE?>"></div>
					<?php endif;?>
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
					<?php if (count($order->items)):foreach ($order->items as $item):?>
					<?php if (!empty($item->sku)&&strlen($item->sku)):?>
					<?=$item->sku?>&nbsp;X<b style="padding:2px;border-radius:5px;<?=((int)$item->quantity >1 )?'background-color:orange;color:white;':'' ?>"><?=$item->quantity?></b><br>
					<?php endif;?>
					<?php endforeach;endif;?>
				</td>
				<td style="padding:0px">
					<?php
						$currencySing = $order->currency;
						$currencyInfo = StandardConst::getCurrencyInfoByCode($currencySing);
						if(!empty($currencyInfo) && !empty($currencyInfo['html'])){
							$currencySing = $currencyInfo['html'];
						}
					?>
					<div style="float:left;width:100%;"><span style="float:left;width:40px;">??????+</span><span style="float:left;"><?=$order->subtotal?>&nbsp;<?=$currencySing?></span></div>
					<!-- 
					<div style="float:left;width:100%;"><span style="float:left;width:40px;">??????-</span><span style="float:left;"><?=!empty($order->commission_total)?$order->commission_total:$order->discount_amount ?>&nbsp;<?=$currencySing?></span></div>
					 -->
					<div style="float:left;width:100%;"><span style="float:left;width:40px;">??????+</span><span style="float:left;"><?=$order->shipping_cost?>&nbsp;<?=$currencySing?></span></div>
					<div style="float:left;width:100%;font-weight:bold;"><span style="float:left;;width:40px;">??????=</span><span style="float:left;"><?=$order->grand_total?>&nbsp;<?=$currencySing?></span></div>
				</td>
				<td>
					<?=(empty($order->order_source_create_time)?'':'????????????:<b>'.date('Y-m-d H:i:s',$order->order_source_create_time).'</b><br>') ?>
					<?=(empty($order->paid_time)?'':'????????????:<b>'.date('Y-m-d H:i:s',$order->paid_time).'</b><br>') ?>
					<?=(empty($order->update_time)?'':'??????????????????:<b>'.date('Y-m-d H:i:s',$order->update_time).'</b><br>') ?>
				</td>
				<td>
					<label title="<?=(isset($sysCountry[$order->consignee_country]))?$sysCountry[$order->consignee_country]['country_zh']:$order->consignee_country?>">
					<?=(isset($sysCountry[$order->consignee_country_code]))?$sysCountry[$order->consignee_country_code]['country_zh']:''?>(<?=$order->consignee_country_code?>)<br>
					<?=$order->consignee_country?>
					</label>
				</td>
				<td>
					[????????????:<?=isset($pm_customer_shipped_method[$order->order_source_shipping_method])?$pm_customer_shipped_method[$order->order_source_shipping_method]:$order->order_source_shipping_method ?>]<br>
					<?php if (strlen($order->default_shipping_method_code)){?>[<?=empty($carriers[$order->default_shipping_method_code])?'??????':$carriers[$order->default_shipping_method_code] ?>]<?php }?>
				</td>
				<td>
					<!-- ?????????????????? -->
					<!-- 
					<?php if ($order->pay_status==0):?>
					
					<?php elseif ($order->pay_status==1):?>
					<div title="?????????" class="sprite_pay_1"></div>
					<?php elseif ($order->pay_status==2):?>
					<div title="?????????" class="sprite_pay_2"></div>
					<?php elseif ($order->pay_status==3):?>
					<div title="?????????" class="sprite_pay_3"></div>
					<?php endif;?>
					 -->
					<!-- ???????????? -->
					<!-- 					<?php if ($order->shipping_status==1):?>
					<div title="?????????" class="sprite_shipped_1"></div>
					<?php else:?>
					<div title="?????????" class="sprite_shipped_0"></div>
					<?php endif;?>
					 -->
					<b>
					 <?php	
					  if(!empty($order->order_source_status)){
					  	$source_status_mapping = PriceministerOrderHelper::$orderStatus;
					  	if(!empty($source_status_mapping[$order->order_source_status]))
					  		echo $source_status_mapping[$order->order_source_status];
					  	else 
					  		echo $order->order_source_status;
					  }else 
					  	echo '--';
					 ?>
					 </b>
				</td>
				<td>
					<b><?=OdOrder::$status[$order->order_status]?></b>
					<?=!empty(odorder::$exceptionstatus[$order->exception_status])?'<br><b style="color:#FFB0B0">'.odorder::$exceptionstatus[$order->exception_status].'</b>':'' ?>
				</td>
				<td>
					<?php 
					$carrierErrorHtml = '';
					if (!empty($order->carrier_error)){
						$carrierErrorHtml .= $order->carrier_error;
						//echo 'rt='.stripos('123'.$order->carrier_error,'?????????????????????????????????????????????????????????????????????');
						if (stripos('123'.$order->carrier_error,'?????????????????????????????????????????????????????????????????????')){
							//echo "<br><br>************<br>".$order->default_carrier_code;
							if (!empty($order->default_carrier_code)){
								$carrierErrorHtml .= '<br><a  target="_blank" href="/configuration/carrierconfig/index?carrier_code='.$order->default_carrier_code.'">'.TranslateHelper::t('??????????????????').'</a>';
							}
						}
					}
					if (!empty($carrierErrorHtml)) $carrierErrorHtml.='<br>';
					
					$shipmentHealthCheckHtml = '';
					if ($order->order_status==OdOrder::STATUS_PAY){
						if (empty($order->default_shipping_method_code) ||empty($order->default_carrier_code) ){
							$shipmentHealthCheckHtml .=  '<b style="color:red;">'.TranslateHelper::t('?????????????????????').'</b>';
						}
						if ($order->default_warehouse_id <0){
							if (!empty($shipmentHealthCheckHtml)) $shipmentHealthCheckHtml.='<br>';
							$shipmentHealthCheckHtml .=  '<b style="color:red;">'.TranslateHelper::t('???????????????').'</b>';
						}
						if (!empty($shipmentHealthCheckHtml))
							$shipmentHealthCheckHtml.='<br><a onclick="doactionone(\'changeWHSM\',\''.$order->order_id.'\');">'.TranslateHelper::t('???????????????????????????').'</a>';
					}
					
					if (!empty($shipmentHealthCheckHtml) || !empty($carrierErrorHtml)){
						if ( ($order->order_type!='FBC') && ($order->order_status ==OdOrder::STATUS_PAY))
							echo '<div class="nopadingAndnomagin alert alert-danger">'.$carrierErrorHtml.$shipmentHealthCheckHtml."</div>";
					}
					?>
				
					<?php if ($order->order_status=='300'):?>
					<?php echo CarrierHelper::$carrier_step[$order->carrier_step].'<br>';?>
					<?php endif;?>
					<?php 
					$odOrderShipInfo = array();
					if('sm' == $order->order_relation){
						$odOrderShipInfo = OrderHelper::getMergeOrderShippingInfo($order->order_id);
// 						var_dump($odOrderShipInfo);
					}else{
						$odOrderShipInfo = $order->trackinfos;
					}
					?>
					<?php if (count($order->trackinfos)):foreach ($order->trackinfos as $ot):?>
						<?php 
						$class = 'text-info';
						$qtip = '';
						if ($ot->status==1){
							$class = 'text-success';
							$qtip = '<span qtipkey="tracking_number_with_non_error"></span>';
						}elseif ($ot->status==0){
							$class = 'text-warning';
							$qtip = '<span qtipkey="tracking_number_with_pending_status"></span>';
						}elseif($ot->status==2){
							$class = 'text-danger';
							$qtip = '<span qtipkey="tracking_number_with_error"></span>';
						}elseif($ot->status==4){
							$class='text-invalid';
							$qtip = '<span qtipkey="tracking_number_with_invalid_status"></span>';
						}
						?>
						<?php if(!empty($ot->errors)):?>
						<br><b style="color:red;"><?=($ot->addtype=='??????????????????')?'????????????????????????:':'??????????????????:'?><?=$ot->errors ?><br></b>	
						<?php endif; ?>
						<!--  <a href="<?=$ot->tracking_link?>" title="<?=$ot->shipping_method_name?>" target="_blank" ><font class="<?php echo $class?>"><?=$ot->tracking_number?></font></a>-->
						<?php if (strlen($ot->tracking_number)):
							$track_info = TrackingApiHelper::getTrackingInfo($ot->tracking_number);
						?>
						<?php if ($track_info['success']==TRUE):?>
							<b><?=$track_info['data']['status']?></b><br>
							<?php 
							//?????????  carrier_type ?????????0  , ?????????????????????
							if (isset($track_info['data']['carrier_type']) && ! in_array(strtolower($track_info['data']['status']) , ['checking',"?????????","???????????????"]) ){
								if (isset(CarrierTypeOfTrackNumber::$expressCode[$track_info['data']['carrier_type']]))
									echo "<span >(".TranslateHelper::t('??????').CarrierTypeOfTrackNumber::$expressCode[$track_info['data']['carrier_type']].TranslateHelper::t('??????????????????').")</span><br>";
							}
							?>
						<?php endif;?>
						<?php
						$trackingOne=Tracking::find()->where(['track_no'=>$ot->tracking_number,'order_id'=>$order->order_source_order_id])
							->orderBy(['update_time'=>SORT_DESC])->one();
						if(!empty($trackingOne)) $carrier_type = $trackingOne->carrier_type;
						else $carrier_type = '';
						if(!in_array($carrier_type, $non17Track)) $tracking_info_type='17track';
							  else $tracking_info_type = '';
						?>
						<a href="javascript:void(0);" onclick="OmsViewTracker(this,'<?=$ot->tracking_number ?>')" title="<?=$ot->shipping_method_name?>" data-info-type='<?=$tracking_info_type ?>'><span class="order-info"><font class="<?php echo $class?>"><?=$ot->tracking_number?></font><?=$qtip ?></span></a>
						
						<?php //Tracker????????????????????????	liang 16-02-27 start
						//??????????????????????????????????????????????????????
						if ($ot->status==1 && $order->logistic_status!=='ignored'){
						?>
						<span class="iconfont icon-ignore_search" onclick="ignoreTrackingNo(<?=$order->order_id?>,'<?=$ot->tracking_number?>')" data-toggle="popover" data-content="???????????????????????????????????????(???????????????)??????????????????????????????????????????????????????????????????????????????????????????????????????" data-html="true" data-trigger="hover" data-placement="top" style="vertical-align:baseline;cursor:pointer;"></span>
						<?php }	?>
						<br>
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
					
					<?php if (!empty($order->seller_weight) && (int)$order->seller_weight!==0){
						echo "???????????????".(int)$order->seller_weight." g";
					}?>
				</td>
				<?php if(!empty($showMergeOrder)){?>
				<?php if(!empty($groupOrderMd5) && (empty($nowMd5) || $nowMd5!=$groupOrderMd5) ){
					$nowMd5 = $groupOrderMd5;
					echo "<td>";
					echo Html::button(TranslateHelper::t('??????'),['class'=>"iv-btn btn-important",'style'=>"width: 78px;",'onclick'=>"OrderCommon.mergeSameRowOrder('".$nowMd5."');"]);
					echo "</td>";
				}?>
				<?php }else{?>
				<td>
					<a href="<?=Url::to(['/order/priceminister-order/edit','orderid'=>$order->order_id])?>" target="_blank" qtipkey="cd_order_action_editorder" class="no-qtip-icon"><span class="egicon-edit toggleMenuL" title="????????????"></span></a>
					<?php if(in_array($order->order_source_status,['new','current','tracking','claim'])){ ?>
					<a href="#" onclick="javascript:syncOneOrderStatus('<?=$order->order_id ?>')"><span class="glyphicon glyphicon-refresh toggleMenuL" style="top:3px" title="????????????????????????"></span></a>
					<?php } ?>
					
					<?php //??????????????????dialog?????????icon -- start
						$detailMessageType=MessageApiHelper::orderSessionStatus('priceminister',$order->order_source_order_id);
						if(!empty($detailMessageType['data']) && !is_null($detailMessageType['data'])){ 
						if(!empty($detailMessageType['data']['hasRead']) && !empty($detailMessageType['data']['hasReplied']))
							$envelope_class="egicon-envelope";
						else 
							$envelope_class="egicon-envelope-remove";
					?>
					<a href="javascript:void(0);" onclick="ShowDetailMessage('<?=$order->selleruserid?>','<?=$order->source_buyer_user_id?>','priceminister','','O','','','','<?=$order->order_source_order_id ?>')" title="??????????????????"><span class="<?=$envelope_class?>"></span></a>
					<?php }  //??????????????????dialog?????????icon -- end?>
					
					<!-- 
					<?php if ($order->is_manual_order==1):?>
					<a href="#" onclick="javascript:changemanual('<?=$order->order_id ?>',this)"><span class="glyphicon glyphicon-save toggleMenuL" title="????????????"></span></a>
					<?php else:?>
					<a href="#" onclick="javascript:changemanual('<?=$order->order_id ?>',this)" qtipkey="cd_order_action_changemanual" class="no-qtip-icon" ><span class="glyphicon glyphicon-open toggleMenuL" title="??????"></span></a>
					<?php endif;?>
					 -->
					<?php 
					$doarr_one+=['signshipped'=>'????????????(????????????)'];
					if(isset($doarr_one['givefeedback']))
						unset($doarr_one['givefeedback']);
					if(isset($doarr_one['refreshOrder']))
						unset($doarr_one['refreshOrder']);
					if(isset($doarr_one['signcomplete']))
						unset($doarr_one['signcomplete']);
					
					if ($order->order_status=='200'){
 						$doarr_one+=[
 							'getorderno'=>'???????????????',
							'outOfStock'=>'???????????????',
 						];	
					}
					$doarr_one+=['invoiced' => '??????'];
					$this_doarr_one = $doarr_one;
					if($order->order_capture=='Y'){
						if(isset($this_doarr_one['signshipped']))
							unset($this_doarr_one['signshipped']);
					}
					if(!(($order->order_capture == 'Y') && ($order->order_relation == 'normal'))){
						if(isset($this_doarr_one['delete_manual_order']))
							unset($this_doarr_one['delete_manual_order']);
					}
					?>
					<?=Html::dropDownList('do','',$this_doarr_one,['onchange'=>"doactionone($(this).val(),'".$order->order_id."');",'class'=>'form-control input-sm do','style'=>'width:70px;']);?>
				</td>
				<?php }?>
			</tr>
				<?php if (count($order->items)):
				//PM?????????????????????show?????????orderItem,?????????????????????
					$showItems=0;
					$nonDeliverySku=PriceministerOrderInterface::getNonDeliverySku();
					foreach ($order->items as $key=>$item){
						if ( !empty($item->sku) && !in_array(strtoupper($item->sku),$nonDeliverySku) )
							$showItems++;
					}
				//?????????end
				$first_item_key = '';
				foreach ($order->items as $key=>$item):?>
				<?php 
				//?????????????????????????????? start
					if(empty($showLastMessage)){
						$lastMessageInfo = MessageApiHelper::getOrderLastMessage($order->order_source_order_id);
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
				<?php if(empty($item->sku) or in_array(strtoupper($item->sku),$nonDeliverySku) ) continue;
					else {
						if($first_item_key=='')
							$first_item_key = $key;
					}
				?>
				<tr class="xiangqing <?=$order->order_id?> <?= ($key==$first_item_key)?"first-item":"" ?>" <?= empty($groupOrderMd5)?"":"merge-row-tag='".$groupOrderMd5."'"; ?>>
				<?php 
				$prodInfo = !empty($product_infos[$item->sku])?$product_infos[$item->sku]:[];
				//var_dump($prodInfo);
				$photo_primary = $item->photo_primary;
				/*
				if(empty($photo_primary)){
					$prodInfo = PriceministerOfferList::find()->where(['product_id'=>$item->order_source_itemid])->andWhere(['not',['img'=>null]])->one();
					if($prodInfo<>null && !empty($prodInfo->img)){
						$photos = json_decode($prodInfo->img,true);
						$photo_primary = empty($photos[0])?'':$photos[0];
					}
				}
				*/
				if(empty($photo_primary)){
					if(!empty($prodInfo))
						$photo_primary = $prodInfo['photo_primary'];
				}
				$photo_primary = ImageCacherHelper::getImageCacheUrl($photo_primary,$uid,1);
				?>
					<td style="border:1px solid #d9effc;"><img class="prod_img" src="<?=$photo_primary?>" width="60px" height="60px" data-toggle="popover" data-content="<img src='<?=$photo_primary?>'>" data-html="true" data-trigger="hover"></td>
					<td colspan="2" style="border:1px solid #d9effc;text-align:justify;">
						<?=!empty($item->product_url)?'<a href="'.((stripos($item->product_url, 'http://www.priceminister.com')===false)?'http://www.priceminister.com'.$item->product_url:$item->product_url).'" target="_blank" title="????????????????????????" style="cursor:pointer;">':'' ?>
						SKU:<b><?=$item->sku?></b><br>
						<?=$item->product_name?><br>
						<?=!empty($item->product_url)?'</a>':''?>
					</td>
					<td  style="border:1px solid #d9effc">
						<?=$item->quantity?>
						<?php
						if(!empty($prodInfo) && !empty($prodInfo['purchase_link'])){
							echo "<a href='".$prodInfo['purchase_link']."' target='_blank'><span class='glyphicon glyphicon-shopping-cart' title='?????????????????????????????????????????????????????????????????????' style='cursor:pointer;color:#2ecc71;margin-left:5px;'></span></a>";	
						}elseif(!empty($prodInfo) && empty($prodInfo['purchase_link'])){
							echo "<a href='/catalog/product/list?txt_search=".$prodInfo['sku']."' target='_blank'><span class='glyphicon glyphicon-shopping-cart' style='color:rgb(139, 139, 139);cursor:pointer;margin-left:5px;' title='??????????????????????????????????????????????????????????????????????????????'></span></a>";
						}else{
							echo "<a href='/catalog/product/list' target='_blank'><span class='glyphicon glyphicon-shopping-cart' style='color:rgb(139, 139, 139);cursor:pointer;margin-left:5px;' title='????????????????????????????????????????????????????????????'></span></a>";
						}?>
					</td>
					<?php if ($key=='0'):?>
					<td rowspan="<?=$showItems?>" style="border:1px solid #d9effc">
						<font color="#8b8b8b">??????:</font><br>
						<b><?php if ($order->default_warehouse_id>0&&count($warehouses)){echo $warehouses[$order->default_warehouse_id];}?></b>
					</td>
					<td rowspan="<?=$showItems?>" style="border:1px solid #d9effc">
						<font color="#8b8b8b">priceminister????????? / ????????????</font><br>
						<b><?= empty($saasAccounts[$order->selleruserid]) ? "" : $saasAccounts[$order->selleruserid]->store_name;?> / <?=str_replace(' ', '&nbsp;', $order->consignee);?></b>
					</td>
					<?php endif;?>
					<td colspan="2" style="border:1px solid #d9effc;   text-align: center; vertical-align: middle;">
						<?php if(!empty($item->order_source_order_item_id)){ 
							//$pm_order_item = PriceministerOrderDetail::find()->where(['purchaseid'=>$item->order_source_order_id,'itemid'=>$item->order_source_order_item_id])->orderBy('id desc')->asArray()->one();
							$item_status='';
							if(!empty($item['source_item_id']))
								echo "????????????id???".$item['source_item_id'].'<br>';
							
							if(!empty($item['platform_status'])){
								$item_status = $item['platform_status'];
							}
							
							if(empty($item_status)) 
								$item_status = '--';
							echo '????????????:<b>'.$item_status.'</b>';
							$addi_info = $item->addi_info;
							if(!empty($addi_info))
								$addi_info = json_decode($addi_info,true);
							if(($item_status=='TO_CONFIRM'|| $item_status=='REQUESTED' || $item_status=='REMINDED') && empty($addi_info['userOperated'])){
								echo '<br><button type="button" class="btn-info" onclick="pmOrder.list.operateNewSaleItem(\'accept\',\''.$item['source_item_id'].'\',\''.$order->selleruserid.'\')">??????</button><button type="button" class="btn-danger" onclick="pmOrder.list.operateNewSaleItem(\'refuse\',\''.$item['source_item_id'].'\',\''.$order->selleruserid.'\')">??????</button>';
							}
							if(($item_status=='TO_CONFIRM'|| $item_status=='REQUESTED' || $item_status=='REMINDED') && !empty($addi_info['isNewSale']) && !empty($addi_info['userOperated'])){
								echo '<br><b>??????????????????/????????????????????????????????????</b>';
								if(!empty($addi_info['operate_time']))
									echo '<br>????????????:'.$addi_info['operate_time'];
							}
						?>
						<?php } ?>
					</td>
					<?php if ($key=='0'):?>
					<td colspan="2"  rowspan="<?=$showItems?>"  width="150px" style="word-break:break-all;word-wrap:break-word;border:1px solid #d9effc">
						<font color="#8b8b8b">????????????:</font><br>
						<b><?=!empty($order->user_message)?$order->user_message:''?>
							<?php if(!empty($lastMessage) && empty($showLastMessage)){
								echo '<a href="javascript:void(0);" onclick="ShowDetailMessage(\''.$order->selleruserid.'\',\''.$order->source_buyer_user_id.'\',\'priceminister\',\'\',\'O\',\'\',\'\',\'\',\''.$order->order_source_order_id.'\')">';
								echo $lastMessage;
								echo '</a>';
								$showLastMessage=1;
							} ?>
						</b>
					</td>
					<?php if(empty($showMergeOrder)){?>
					<td  rowspan="<?=$showItems?>" width="150" style="word-break:break-all;word-wrap:break-word;border:1px solid #d9effc">
						<span><font color="red"><?=$order->desc?></font></span>
						<a href="javascript:void(0)" style="border:1px solid #00bb9b;" onclick="updatedesc('<?=$order->order_id?>',this)" oiid="<?=$order->order_id?>"><font style="white-space: nowrap;" color="00bb9b">??????</font></a>
					</td>
					<?php }?>
					<?php endif;?>
				</tr>	
				<?php endforeach;endif;?>
				<?php if (empty($showMergeOrder)){ ?>
				<tr style="background-color: #d9d9d9;" class="xiangqing <?=$order->order_id;?>">
					<td colspan="11" class="row" id="dataline-<?=$order->order_id;?>" style="word-break:break-all;word-wrap:break-word;border:1px solid #d1d1d1;height:11px;"></td>
				</tr>
				<?php } ?>
			<?php endforeach;endif;?>
			</table>
			
			<?php
				}else{
					echo $this->render('../order/order_list_v3',[ 'carriers'=>$carriers, 'models'=>$models,
							'warehouses'=>$warehouses, 'non17Track'=>$non17Track, 'tag_class_list'=>$tag_class_list,
							'current_order_status' => empty($_REQUEST['order_status']) ? '' : $_REQUEST['order_status'],
							'current_exception_status' => empty($_REQUEST['exception_status']) ? '' : $_REQUEST['exception_status'],
							'platform_type'=>'priceminister']);
				}
			?>
			
			
			<div class="btn-group" >
			<?=LinkPager::widget([
			    'pagination' => $pages,
			]);
			?>
			</div>
			<?=SizePager::widget(['pagination'=>$pages , 'pageSizeOptions'=>array( 5 , 20 , 50 , 100 , 200 ,500), 'class'=>'btn-group dropup'])?>
		</form>
	</div>
	<?php if (@$_REQUEST['order_status'] == 200 ):?>
	<div>
	<ul>
		<li style="float: left;line-height: 60px;">????????????????????????????????????????????????</li>
		<li style="float: left;"><?php echo OrderFrontHelper::displayOrderPaidProcessHtml($counter,'priceminister')?></li>
	</ul>
	
	</div>
	<?php endif;?>
<div style="clear: both;"></div>
</div></div>
<div style="display: none;">
<?=$divTagHtml?>
<?=$div_event_html?>
<div id="div_ShippingServices"><?=Html::dropDownList('demo_shipping_method_code',@$order->default_shipping_method_code,CarrierApiHelper::getShippingServices())?></div>
<div class="dash-board"></div>
<div class="important-change-tip-win"></div>
</div>


<div id="oms_order_pending_check" style="display: none">
	<div>??????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????</div>
</div>
<div id="oms_order_reorder" style="display: none">
	<div>???????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????</div>
</div>
<div id="oms_order_exception" style="display: none">
	<div>????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????<br>?????????????????????????????????<br><a href="<?=SysBaseInfoHelper::getHelpdocumentUrl('faq_66.html')?>" target="_blank"><?=SysBaseInfoHelper::getHelpdocumentUrl('faq_66.html')?></a></div>
</div>
<div id="oms_order_can_ship" style="display: none">
	<div>???????????????????????????????????????????????????</div>
</div>
<div id="oms_order_status_check" style="display: none">
	<div>???????????????????????????????????????<br>??????????????????????????????????????????????????????<br><a href="<?=SysBaseInfoHelper::getHelpdocumentUrl('faq_61.html')?>" target="_blank"><?=SysBaseInfoHelper::getHelpdocumentUrl('faq_61.html')?></a></div>
</div>
<div id="oms_batch_ship_pack" style="display: none">
	<div>????????????????????? ?????? ????????????<br>??????????????????????????????????????????????????????????????????????????? ??????????????? <b style="font-weight: 600;color:blue">??????</b>?????? ?????? <b style="font-weight: 600;color:blue">??????</b>??????????????????????????????????????? ?????? ???????????? ?????? ???????????????<br>??????????????????????????? <b style="font-weight: 600;color:red">??????</b>?????????????????????????????????<b style="font-weight: 600;color:red">?????????</b>????????????????????????????????????????????????????????????.</div>
</div>
<div id="oms_order_exception_pending_merge" style="display: none">
	<div>??????????????????????????????????????????????????????????????????<br>????????????????????????<br><a href="<?=SysBaseInfoHelper::getHelpdocumentUrl('faq_62.html')?>" target="_blank"><?=SysBaseInfoHelper::getHelpdocumentUrl('faq_62.html')?></a></div>
</div>
<div id="oms_order_exception_pending_merge_merge" style="display: none">
	<div>????????????????????????????????????????????????????????????????????????????????????</div>
</div>
<div id="ms_order_exception_pending_merge_skip" style="display: none">
	<div>????????????????????????????????????????????????????????????????????????????????????????????????</div>
</div>
<div id="oms_order_exception_sku_not_exist" style="display: none">
	<div>???????????????SKU????????????????????????<br>????????????????????????<br><a href="<?=SysBaseInfoHelper::getHelpdocumentUrl('faq_63.html')?>" target="_blank"><?=SysBaseInfoHelper::getHelpdocumentUrl('faq_63.html')?></a></div>
</div>
<div id="oms_order_exception_sku_not_exist_create" style="display: none">
	<div>??????????????????????????????????????????????????????sku??????????????????????????????????????????????????????????????????</div>
</div>
<div id="oms_order_exception_no_warehouse" style="display: none">
	<div>????????????????????????????????????<br>????????????????????????<br><a href="<?=SysBaseInfoHelper::getHelpdocumentUrl('faq_64.html')?>" target="_blank"><?=SysBaseInfoHelper::getHelpdocumentUrl('faq_64.html')?></a></div>
</div>
<div id="oms_order_exception_no_warehouse_assign" style="display: none">
	<div>????????????????????????????????????????????????<br>???????????????????????????????????????????????????????????????????????????</div>
</div>
<div id="oms_order_exception_out_of_stock" style="display: none">
	<div>????????????SKU????????????<br>????????????????????????<br><a href="<?=SysBaseInfoHelper::getHelpdocumentUrl('faq_65.html')?>" target="_blank"><?=SysBaseInfoHelper::getHelpdocumentUrl('faq_65.html')?></a></div>
</div>
<div id="oms_order_exception_out_of_stock_manage" style="display: none">
	<div>??????????????????????????????????????? ????????????????????????????????????</div>
</div>
<div id="oms_order_exception_out_of_stock_mark" style="display: none">
	<div>?????????????????????????????????????????????????????????????????????????????????</div>
</div>
<div id="oms_order_exception_no_shipment" style="display: none">
	<div>????????????????????????????????????????????????????????????????????????????????????<br>????????????????????????<br><a href="<?=SysBaseInfoHelper::getHelpdocumentUrl('faq_67.html')?>" target="_blank"><?=SysBaseInfoHelper::getHelpdocumentUrl('faq_67.html')?></a></div>
</div>
<div id="oms_order_exception_no_shipment_assign" style="display: none">
	<div>????????????????????????????????????,<br>????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????</div>
</div>
<div><input type='hidden' id='search_condition' value='<?=$search_condition ?>'>
	    	 <input type='hidden' id='search_count' value='<?=$search_count ?>'></div>


<script>
function doaction2(obj){
	var val = $(obj).val();
	if (val != ''){
		doaction(val );
		$(obj).val('');
	}
}
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
		case 'setSyncShipComplete':
			var thisOrderList =[];
			$('input[name="order_id[]"]:checked').each(function(){
				thisOrderList.push($(this).val());
			});
	
			OrderCommon.setOrderSyncShipStatusComplete(thisOrderList);
			break;
		case 'changeItemDeclarationInfo':
			var thisOrderList =[];
		
			$('input[name="order_id[]"]:checked').each(function(){
				thisOrderList.push($(this).val());
			});

			OrderCommon.showChangeItemDeclarationInfoBox(thisOrderList);
			break;
		case 'addMemo':
			var idstr = [];
			$('input[name="order_id[]"]:checked').each(function(){
				idstr.push($(this).val());
			});
			OrderCommon.showAddMemoBox(idstr);
			break;
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
		case 'generateProduct':
			idstr = [];
			$('input[name="order_id[]"]:checked').each(function(){
				idstr.push($(this).val());
			});
			
			//??????
			$.maskLayer(true);
			$.ajax({
				url: "<?=Url::to(['/order/order/generateproduct'])?>",
				data: {
					orderids:idstr,
				},
				type: 'post',
				success: function(response) {
					 var r = $.parseJSON(response);
					 if(r.result){
						 var event = $.confirmBox('<p class="text-success">'+r.message+'</p>');
					 }else{
						 var event = $.confirmBox('<p class="text-warn">'+r.message+'</p>');
					 } 
					 event.then(function(){
					  // ??????,????????????
					  location.reload();
					},function(){
					  // ?????????????????????
					  $.maskLayer(false);
					});
				},
				error: function(XMLHttpRequest, textStatus) {
					$.maskLayer(false);
					$.alert('<p class="text-warn">???????????????.????????????,?????????!</p>','danger');
				}
			})
			break;
		case 'signcomplete':
			idstr = [];
			$('input[name="order_id[]"]:checked').each(function(){
				idstr.push($(this).val());
			});
			
			//??????
			$.maskLayer(true);
			$.ajax({
				url: "<?=Url::to(['/order/order/signcomplete'])?>",
				data: {
					orderids:idstr,
				},
				type: 'post',
				success: function(response) {
					 var r = $.parseJSON(response);
					 if(r.result){
						 var event = $.confirmBox('<p class="text-success">'+r.message+'</p>');
					 }else{
						 var event = $.confirmBox('<p class="text-warn">'+r.message+'</p>');
					 } 
					 event.then(function(){
					  // ??????,????????????
					  location.reload();
					},function(){
					  // ?????????????????????
					  $.maskLayer(false);
					});
				},
				error: function(XMLHttpRequest, textStatus) {
					$.maskLayer(false);
					$.alert('<p class="text-warn">???????????????.????????????,?????????!</p>','danger');
				}
			})
			break;
		case 'suspendDelivery':
			idstr = [];
			$('input[name="order_id[]"]:checked').each(function(){
				idstr.push($(this).val());
			});
			
			$.post('<?=Url::to(['/order/order/suspenddelivery'])?>',{orders:idstr},function(result){
				bootbox.alert({  
		            buttons: {  
		                ok: {  
		                     label: '??????',  
		                     className: 'btn-myStyle'  
		                 }  
		             },  
		             message: result,  
		             callback: function() {  
		                 location.reload();
		             },  
		         });
				
			});
			break;
		case 'deleteorder':
			if(confirm('????????????????????????????????????????????????????????????????????????????')){
				document.a.target="_blank";
    			document.a.action="<?=Url::to(['/order/order/deleteorder'])?>";
    			document.a.submit();
    			document.a.action="";
			}
			break;
		case 'cancelorder':
			idstr = [];
			$('input[name="order_id[]"]:checked').each(function(){
				idstr.push($(this).val());
			});
			
			$.post('<?=Url::to(['/order/order/cancelorder'])?>',{orders:idstr},function(result){
				bootbox.alert(result);
				location.reload();
			});
			break;
		case 'signwaitsend':
			idstr='';
			$('input[name="order_id[]"]:checked').each(function(){
				idstr+=','+$(this).val();
			});
			OrderCommon.shipOrderOMS(idstr);
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
		case 'reorder':
			var thisOrderList = [];
			$('input[name="order_id[]"]:checked').each(function(){
				thisOrderList.push($(this).val());
			});
			OrderCommon.reorder(thisOrderList);
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
		case 'skipMerge':
			idstr = [];
			$('input[name="order_id[]"]:checked').each(function(){
				idstr.push($(this).val());
			});
			
			$.post('<?=Url::to(['/order/order/skipmerge'])?>',{orders:idstr},function(result){
				bootbox.alert({  
		            buttons: {  
		                ok: {  
		                     label: '??????',  
		                     className: 'btn-myStyle'  
		                 }  
		             },  
		             message: result,  
		             callback: function() {  
		                 location.reload();
		             },  
		         });
				
			});
			break;
		case 'changeWHSM':
			var thisOrderList =[];
			$('input[name="order_id[]"]:checked').each(function(){
				if ($(this).parents("tr:contains('?????????')").length == 0) return;
				thisOrderList.push($(this).val());
			});
			if (thisOrderList.length == 0){
				bootbox.alert('??????????????????????????????????????????');
				return;
			}
			OrderCommon.showWarehouseAndShipmentMethodBox(thisOrderList);
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
		case 'outOfStock':
			idstr = [];
			$('input[name="order_id[]"]:checked').each(function(){
				idstr.push($(this).val());
			});
			
			$.post('<?=Url::to(['/order/order/outofstock'])?>',{orders:idstr},function(result){
				bootbox.alert({  
		            buttons: {  
		                ok: {  
		                     label: '??????',  
		                     className: 'btn-myStyle'  
		                 }  
		             },  
		             message: result,  
		             callback: function() {  
		                 location.reload();
		             },  
		         });
				
			});
			break;
		case 'calculat_profit':
			idstr='';
			$('input[name="order_id[]"]:checked').each(function(){
				idstr+=','+$(this).val();
			});
			$.showLoading();
			$.ajax({
				type: "POST",
					//dataType: 'json',
					url:'/order/order/profit-order', 
					data: {order_ids : idstr},
					success: function (result) {
						$.hideLoading();
						bootbox.dialog({
							className : "profit-order",
							//title: ''
							message: result,
						});
					},
					error: function(){
						$.hideLoading();
						bootbox.alert("????????????????????????????????????...");
						return false;
					}
			});
			break;
		case 'ExternalDoprint':
			var thisOrderList =[];
			$('input[name="order_id[]"]:checked').each(function(){
				thisOrderList.push($(this).val());
			});
			OrderCommon.ExternalDoprint(thisOrderList);
			break;
		case 'delete_manual_order':
			idstr = [];
			$('input[name="order_id[]"]:checked').each(function(){
				idstr.push($(this).val());
			});
			OrderCommon.deleteManualOrder(idstr);
			break;
		default:
			return false;
			break;
	}
}
//??????????????????

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

//????????????
function doactionone(val,orderid){
	//????????????????????????????????????
	if(val==""){
        bootbox.alert("?????????????????????");return false;
    }
	if(orderid == ""){ bootbox.alert("???????????????");return false;}
	switch(val){
		case 'setSyncShipComplete':
			var thisOrderList =[];
			thisOrderList.push(orderid);
			OrderCommon.setOrderSyncShipStatusComplete(thisOrderList);
			break;
		case 'changeWHSM':
			var thisOrderList =[];
			thisOrderList.push(orderid);
			OrderCommon.showWarehouseAndShipmentMethodBox(thisOrderList);
			break;
		case 'changeItemDeclarationInfo':
			var thisOrderList =[];
			thisOrderList.push(orderid);
			OrderCommon.showChangeItemDeclarationInfoBox(thisOrderList);
			break;
		case 'checkorder':
			$.post('<?=Url::to(['/order/order/checkorderstatus'])?>',{orders:orderid},function(result){
				bootbox.alert(result);
				location.reload();
			});
			break;
		case 'addMemo':
			var idstr = [];
			idstr.push(orderid);
			OrderCommon.showAddMemoBox(idstr);
			break;
		case 'editOrder':
			idstr = [];
			idstr.push(orderid);
			window.open("<?=Url::to(['/order/priceminister-order/edit'])?>"+"?orderid="+orderid,'_blank')
			break;
		case 'signshipped':
			window.open("<?=Url::to(['/order/order/signshipped'])?>"+"?order_id="+orderid,'_blank')
			break;
		case 'generateProduct':
			document.a.action="<?=Url::to(['/order/order/generateproduct'])?>"+"?order_id="+orderid;
			idstr = [];
			idstr.push(orderid);
			
			//??????
			$.maskLayer(true);
			$.ajax({
				url: "<?=Url::to(['/order/order/generateproduct'])?>",
				data: {
					orderids:idstr,
				},
				type: 'post',
				success: function(response) {
					 var r = $.parseJSON(response);
					 if(r.result){
						 var event = $.confirmBox('<p class="text-success">'+r.message+'</p>');
					 }else{
						 var event = $.confirmBox('<p class="text-warn">'+r.message+'</p>');
					 } 
					 event.then(function(){
					  // ??????,????????????
					  location.reload();
					},function(){
					  // ?????????????????????
					  $.maskLayer(false);
					});
				},
				error: function(XMLHttpRequest, textStatus) {
					$.maskLayer(false);
					$.alert('<p class="text-warn">???????????????.????????????,?????????!</p>','danger');
				}
			})
			break;
		case 'suspendDelivery':
			idstr = [];
			idstr.push(orderid);
			
			$.post('<?=Url::to(['/order/order/suspenddelivery'])?>',{orders:idstr},function(result){
				bootbox.alert({  
		            buttons: {  
		                ok: {  
		                     label: '??????',  
		                     className: 'btn-myStyle'  
		                 }  
		             },  
		             message: result,  
		             callback: function() {  
		                 location.reload();
		             },  
		         });
				
			});
			break;
		case 'deleteorder':
			if(confirm('????????????????????????????????????????????????????????????????????????????')){
				window.open("<?=Url::to(['/order/order/deleteorder'])?>"+"?order_id="+orderid,'_blank')
			}
			break;
		case 'cancelorder':
			idstr = [];
			idstr.push(orderid);
			$.post('<?=Url::to(['/order/order/cancelorder'])?>',{orders:idstr},function(result){
				bootbox.alert(result);
			});
			break;
		case 'signpayed':
			$.post('<?=Url::to(['/order/order/signpayed'])?>',{orders:orderid},function(result){
				bootbox.alert(result);
				location.reload();
			});
			break;
		case 'reorder':
			idstr = [];
			idstr.push(orderid);
			OrderCommon.reorder(idstr);
			break;
		case 'givefeedback':
			window.open("<?=Url::to(['/order/order/feedback'])?>"+"?order_id="+orderid,'_blank');
			break;
		case 'dispute':
			window.open("<?=Url::to(['/order/order/dispute'])?>"+"?order_id="+orderid,'_blank');
			break;
		case 'mergeorder':
			window.open("<?=Url::to(['/order/order/mergeorder'])?>"+"?order_id="+orderid,'_blank');
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
		case 'invoiced':
			window.open("<?=Url::to(['/order/order/order-invoice'])?>"+"?order_id="+orderid,'_blank');
			break;
		case 'completecarrier':
			completecarrier(orderid);
			break;
		case 'outOfStock':
			var thisOrderList =[];
			thisOrderList.push(orderid);
			
			$.post('<?=Url::to(['/order/order/outofstock'])?>',{orders:thisOrderList},function(result){
				bootbox.alert({  
		            buttons: {  
		                ok: {  
		                     label: '??????',  
		                     className: 'btn-myStyle'  
		                 }  
		             },  
		             message: result,  
		             callback: function() {  
		                 location.reload();
		             },  
		         });
				
			});
			break;
		case 'ExternalDoprint':
			var thisOrderList =[];
			thisOrderList.push(orderid);
			OrderCommon.ExternalDoprint(thisOrderList);
			break;
		case 'delete_manual_order':
			idstr = [];
			idstr.push(orderid);
			OrderCommon.deleteManualOrder(idstr);
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
	$.post('<?=Url::to(['/order/order/changemanual'])?>',{orderid:orderid},function(result){
		if(result == 'success'){
			bootbox.alert('???????????????');
			var str;
			str=$(obj).html();
			if(str.indexOf('????????????')>=0){
				$(obj).html('<span class="glyphicon glyphicon-open toggleMenuL" title="??????"><\/span>');			
			}else{
				$(obj).html('<span class="glyphicon glyphicon-save toggleMenuL" title="????????????"><\/span>');	
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
function completecarrier(orderid){
	$.showLoading();
	var url = '/carrier/default/completecarrier?order_id='+orderid;
	$.get(url,function (data){
			$.hideLoading();
			var retinfo = eval('(' + data + ')');
			if (retinfo["code"]=="fail")  {
				bootbox.alert({title:Translator.t('????????????') , message:retinfo["message"] });	
				return false;
			}else{
				bootbox.alert({title:Translator.t('??????'),message:retinfo["message"],callback:function(){
					window.location.reload();
					$.showLoading();
				}});
			}
		}
	);
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

function closeReminder(){
	var child=document.getElementById("pm-oms-reminder-content");
	var reminder=document.getElementById("pm-oms-reminder");
	reminder.removeChild(child);
}

function closeReminderToday(){
	var child=document.getElementById("pm-oms-reminder-content");
	var reminder=document.getElementById("pm-oms-reminder");
	$.post('<?=Url::to(['/order/priceminister-order/close-reminder'])?>',{},function(result){
		if(result == 'success'){
			reminder.removeChild(child);
		}else{
			console(result);
			reminder.removeChild(child);
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

function OmsViewTracker(obj,num){
	var s_trackingNo = $(obj).has('.text-success');
	if(typeof(s_trackingNo)!=='undefined' && s_trackingNo.length>0){
		var tracking_info_type=$(obj).data("info-type");
		if(tracking_info_type !== '17track'){
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
				data: {invoker: 'Priceminister-Oms'},
				success: function (result) {
					return true;
				},
				error :function () {
					return false;
				}
			});
		}else{
			$.showLoading();
			show17Track(num);
			$.hideLoading();
			$.ajax({
				type: "POST",
				dataType: 'json',
				url:'/order/order/oms-view-tracker', 
				data: {invoker: 'Priceminister-Oms'},
				success: function (result) {
					return true;
				},
				error :function () {
					return false;
				}
			});
		}
	}
}

function show17Track(num){
	$.ajax({
		type: "GET",
		url:'/tracking/tracking/show17-track-tracking-info?num='+num,
		success: function (result) {
			$.hideLoading();
			bootbox.dialog({
				className : "17track-trackin-info-win",
				title: Translator.t('17track????????????'),
				message: result,
			});
		},
		error :function () {
			$.hideLoading();
			bootbox.alert(Translator.t('????????????,??????????????????'));
			return false;
		}
	});
}

function doTrack(num) {
    if(num===""){
        alert("Enter your number."); 
        return;
    }
    YQV5.trackSingle({
        YQ_ContainerId:"YQContainer",       //????????????????????????????????????ID???
        YQ_Height:400,      //???????????????????????????????????????????????????800px????????????????????????
        YQ_Fc:"0",       //???????????????????????????????????????????????????
        YQ_Lang:"zh-cn",       //???????????????UI?????????????????????????????????????????????
        YQ_Num:num     //????????????????????????????????????
    });
}

function showDashBoard(autoShow){
	$.showLoading();
	$.ajax({
		type: "GET",
		url:'/order/priceminister-order/user-dash-board?autoShow='+autoShow, 
		success: function (result) {
			$.hideLoading();
			$("#dash-board-enter").toggle();
			bootbox.dialog({
				className : "dash-board",
				title: Translator.t('Priceminister??????????????????'),
				message: result,
				closeButton:false,
			});
			return true;
		},
		error :function () {
			$.hideLoading();
			bootbox.alert(Translator.t('??????Priceminister????????????????????????'));
			return false;
		}
	});
}

function ignoreTrackingNo(order_id,track_no){
	$.showLoading();
	$.ajax({
		type: "GET",
		url:'/order/order/ignore-tracking-no?order_id='+order_id+'&track_no='+track_no,
		dataType:'json',
		success: function (result) {
			$.hideLoading();
			if(result.success===true){
				bootbox.alert({
		            message: '????????????',  
		            callback: function() {  
		            	window.location.reload();
		            }
				});
			}else{
				bootbox.alert(result.message);
				return false;
			}
		},
		error :function () {
			$.hideLoading();
			bootbox.alert(Translator.t('????????????,??????????????????'));
			return false;
		}
	});
}

function syncOneOrderStatus(order_id){
	$.showLoading();
	$.ajax({
		type: "GET",
		url:'/order/priceminister-order/sync-one-order-status?order_id='+order_id,
		dataType:'json',
		success: function (result) {
			$.hideLoading();
			if(result.success===true){
				bootbox.alert({
		            message: '????????????',  
		            callback: function() {  
		            	window.location.reload();
		            }
				});
			}else{
				bootbox.alert(result.message);
				return false;
			}
		},
		error :function () {
			$.hideLoading();
			bootbox.alert(Translator.t('????????????,??????????????????'));
			return false;
		}
	});
}

function syncAllUnClosedOrderStatus(){
	$.showLoading();
	$.ajax({
		type: "GET",
		url:'/order/priceminister-order/sync-all-un-closed-order-status',
		dataType:'json',
		success: function (result) {
			$.hideLoading();
			if(result.success===true){
				bootbox.alert({
		            message: '????????????,???????????????????????????,???????????????????????????????????????',  
		            callback: function() {  
		            	window.location.reload();
		            }
				});
			}else{
				bootbox.alert(result.message);
				return false;
			}
		},
		error :function () {
			$.hideLoading();
			bootbox.alert(Translator.t('????????????,??????????????????'));
			return false;
		}
	});
}

function showImportantChangeTip(){
	$.showLoading();
	$.ajax({
		type: "GET",
		url:'/order/priceminister-order/important-change', 
		success: function (result) {
			$.hideLoading();
			bootbox.dialog({
				className : "important-change-tip-win",
				title: Translator.t('????????????'),
				message: result,
				closeButton:true,
			});
			return true;
		},
		error :function () {
			$.hideLoading();
			bootbox.alert(Translator.t('???????????????????????????????????????'));
			return false;
		}
	});
}
function setAutoAccept(){
	var auto_accept = $("input[name='auto_accept']:checked").val();
	$.showLoading();
	$.ajax({
		type: "POST",
		dataType:'json',
		url:'/order/priceminister-order/set-auto-accept-order',
		data:{auto_accept:auto_accept},
		success: function (result) {
			$.hideLoading();
			if(result.success==true){
				bootbox.alert(Translator.t('?????????????????????????????????'));
				window.location.reload();
			}else{
				bootbox.alert(result.message);
				return false;
			}
		},
		error :function () {
			$.hideLoading();
			bootbox.alert(Translator.t('????????????,??????????????????'));
			return false;
		}
	});
}
</script>