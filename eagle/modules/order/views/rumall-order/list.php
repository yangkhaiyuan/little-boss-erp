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
use eagle\modules\util\helpers\TranslateHelper;
use eagle\modules\order\helpers\OrderTagHelper;
use eagle\modules\carrier\helpers\CarrierHelper;
use eagle\widgets\SizePager;
use eagle\models\SaasRumallUser;
use eagle\modules\tracking\helpers\TrackingApiHelper;
use eagle\modules\tracking\helpers\TrackingHelper;
use eagle\modules\order\helpers\OrderFrontHelper;
use eagle\modules\tracking\helpers\CarrierTypeOfTrackNumber;
use eagle\modules\order\helpers\RumallOrderHelper;
use eagle\modules\carrier\helpers\CarrierOpenHelper;
use eagle\modules\tracking\models\Tracking;
use eagle\modules\order\helpers\OrderHelper;
use eagle\modules\util\helpers\SysBaseInfoHelper;
use eagle\modules\order\helpers\OrderListV3Helper;

$baseUrl = \Yii::$app->urlManager->baseUrl . '/';
$this->registerCssFile($baseUrl."css/message/customer_message.css");

$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/OrderTag.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/orderOrderList.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/origin_ajaxfileupload.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/message/customer_message.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerCssFile(\Yii::getAlias('@web').'/css/order/order.css');
$this->registerJs("OrderTag.TagClassList=".json_encode($tag_class_list).";" , \yii\web\View::POS_READY);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/orderCommon.js", ['depends' => ['yii\web\JqueryAsset']]);
if (!empty($_REQUEST['country'])){
    $this->registerJs("OrderCommon.currentNation=".json_encode(array_fill_keys(explode(',', $_REQUEST['country']),true)).";" , \yii\web\View::POS_READY);
}
$this->registerJs("OrderList.initClickTip();" , \yii\web\View::POS_READY);

$this->registerJs("OrderCommon.NationList=".json_encode(@$countrys).";" , \yii\web\View::POS_READY);
$this->registerJs("OrderCommon.NationMapping=".json_encode(@$country_mapping).";" , \yii\web\View::POS_READY);
$this->registerJs("OrderCommon.initNationBox($('div[name=div-select-nation][data-role-id=0]'));" , \yii\web\View::POS_READY);

$this->registerJs("OrderCommon.customCondition=".json_encode(@$counter['custom_condition']).";" , \yii\web\View::POS_READY);
// $this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/aliexpressOrder/aliexpressOrder.js", ['depends' => ['yii\web\JqueryAsset']]);
// $this->registerJs("aliexpressOrder.OMSLeftMenuAutoLoad();" , \yii\web\View::POS_READY);
$orderStatus21 = OdOrder::getOrderStatus('oms21'); // oms 2.1 ???????????????
if(isset($orderStatus21['100'])){
    unset($orderStatus21['100']);
}

$uid = \Yii::$app->user->id;
//************************************** dash board start  ********************************************//
$now = time();

if (!empty($dash_board_cache_expire_time)){
    $dash_board_cache_expire_time = $DashBoardCache['createTime']+60*60*4;
}else{
    $dash_board_cache_expire_time = $now;
}
//$dash_board_cache_expire_time = $DashBoardCache['createTime']+60*60*4;
//no cache or caceh expire , then refresh cache
if (empty($DashBoardCache['createTime']) || (!empty($DashBoardCache['createTime']) &&  $dash_board_cache_expire_time <$now)){
    $isAutoRefreshDashBoard = true;
}else{
    $isAutoRefreshDashBoard = false;
}

//cache is active && no scan record , then auto show dash board
if ((!empty($DashBoardCache['createTime'])) && ( $dash_board_cache_expire_time >$now ) &&  empty($_SESSION['ali_oms_dash_board_last_time'])  ){
    $isAutoShowDashBoard = true;
}else{
    $isAutoShowDashBoard = false;
}


if ($isAutoShowDashBoard){
    $this->registerJs("showDashBoard();" , \yii\web\View::POS_READY); //??????dash board????????????
}

//
if ($isAutoRefreshDashBoard){
    $isDisplayDashBoardStr = 'display:none;';
    $this->registerJs("requestGenerateDashBoardData();" , \yii\web\View::POS_READY); //??????dash board????????????
}else{
    $isDisplayDashBoardStr = '';
}

//?????? ??????dash board ??????????????? ?????????????????? ????????????????????? cache data

$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/orderListV3.js?v=".\eagle\modules\order\helpers\OrderListV3Helper::$OrderCommonJSV3, ['depends' => ['yii\web\JqueryAsset']]);

//************************************** dash board end  ********************************************//
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/alitongbu.js", ['depends' => ['yii\web\JqueryAsset','eagle\assets\PublicAsset']]);
$this->registerJs("$('.prod_img').popover();" , \yii\web\View::POS_READY);

$non17Track = CarrierTypeOfTrackNumber::getNon17TrackExpressCode();

$tmpCustomsort = Odorder::$customsort;
 
#####################################	???????????????????????????start	#################################################################
// $this->registerJs("$('ul.menu-lv-1 > li > a').last().append('<span class=\"left_menu_red_new\">new</span>');" , \yii\web\View::POS_READY);
#####################################	???????????????????????????end	#################################################################
?>
<script type="text/javascript" src="//www.17track.net/externalcall.js"></script>
<style>
.popover{
	max-width: 450px;
}

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
/* height: 35px; */
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
	background-position:-199px -10px;
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
.multiitem{
	padding:0 4px 0 4px;
	background:rgb(255,153,0);
	border-radius:8px;
	color:#fff;
}

.dash-board .modal-dialog{
	width: 900px;
}
.nopadingAndnomagin{
	padding:0px;
	margin:0px;
}

</style>


<div class="tracking-index col2-layout">
<?=$this->render('_leftmenu',['counter'=>$counter]);?>

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
    <?php 
		 $problemAccounts = RumallOrderHelper::getUserAccountProblems($uid); 
		 if(!empty($problemAccounts['order_retrieve_failed'])){ 
		     $problemAccountNames=[];
		     $allAccounts=[];
		 }
		 if(!empty($problemAccounts['order_retrieve_failed'])){
    		 foreach ($problemAccounts['order_retrieve_failed'] as $account){
    			$problemAccountNames["name"] = $account['store_name'];
    			$problemAccountNames["order_retrieve_message"] = $account['order_retrieve_message'];
    			$allAccounts[] = $problemAccountNames;
    		}
		}
	?>
	<!-- ????????????????????????  -->
	<?php if(!empty($allAccounts)):?>
	<div class="alert alert-danger" role="alert" style="width:100%;">
	<?php foreach ($allAccounts as $detailAccounts):?>
		<span>????????????Rumall?????????<?php echo $detailAccounts["name"];?>???????????????????????????<?php echo $detailAccounts["order_retrieve_message"];?></span><br />
	<?php endforeach;?>
	</div> 
	<?php endif;?>
	<!-- ----------------------------------??????????????????????????? start--------------------------------------------------------------------------------------------- -->	
	<?php 
// 		if (@$_REQUEST['order_status'] == 200 || in_array(@$_REQUEST['exception_status'], ['0','210','203','222','202','223','299']) ):	
// 		echo '<ul class="clearfix"><li style="float: left;line-height: 22px;">???????????????</li><li style="float: left;line-height: 22px;">';
// 		echo OrderFrontHelper::displayOrderPaidProcessHtml($counter,'rumall',[(string)OdOrder::PAY_PENDING]);
// 		echo '</li><li class="clear-both"></li></ul>';
// 		endif;
	?>
    <!-- ----------------------------------??????????????????????????? end--------------------------------------------------------------------------------------------- -->
	
	<div>
		<!-- ???????????? -->
		<form class="form-inline" id="form1" name="form1" action="/order/rumall-order/list" method="post">
		<input type="hidden" name ="menu_select" value="<?php echo isset($_REQUEST['menu_select'])?$_REQUEST['menu_select']:'';?>">
		<input type="hidden" name ="select_bar" value="<?php echo isset($_REQUEST['select_bar'])?$_REQUEST['select_bar']:'';?>">
		<?=Html::hiddenInput('customsort', @$_REQUEST['customsort'],['id'=>'customsort']);?>
		<?=Html::hiddenInput('ordersorttype', @$_REQUEST['ordersorttype'],['id'=>'ordersorttype']);?>
		<div style="margin:10px 0px 0px 0px">
		<?php //echo Html::dropDownList('selleruserid',@$_REQUEST['selleruserid'],$selleruserids,['class'=>'iv-input','id'=>'selleruserid','style'=>'margin:0px;width:150px','prompt'=>'????????????'])?>
			
			<?php
			//?????????????????? S
			?>
			<input type="hidden" name ="selleruserid_combined" id="selleruserid_combined" value="<?php echo isset($_REQUEST['selleruserid_combined'])?$_REQUEST['selleruserid_combined']:'';?>">
			<?php
			$omsPlatformFinds = array();
// 			$omsPlatformFinds[] = array('event'=>'OrderCommon.order_platform_find(\'oms\',\'rumall\',\'\')','label'=>'??????');
			
			if(count($selleruserids) > 0){
				$selleruserids['select_shops_xlb'] = '????????????';
	
				foreach ($selleruserids as $tmp_selleruserKey => $tmp_selleruserid){
					$omsPlatformFinds[] = array('event'=>'OrderCommon.order_platform_find(\'oms\',\'rumall\',\''.$tmp_selleruserKey.'\')','label'=>$tmp_selleruserid);
				}
				
				$pcCombination = OrderListV3Helper::getPlatformCommonCombination(array('type'=>'oms','platform'=>'rumall'));
				if(count($pcCombination) > 0){
					foreach ($pcCombination as $pcCombination_K => $pcCombination_V){
						$omsPlatformFinds[] = array('event'=>'OrderCommon.order_platform_find(\'oms\',\'rumall\',\''.'com-'.$pcCombination_K.'-com'.'\')',
							'label'=>'??????-'.$pcCombination_K, 'is_combined'=>true, 'combined_event'=>'OrderCommon.platformcommon_remove(this,\'oms\',\'rumall\',\''.$pcCombination_K.'\')');
					}
				}
			}
			echo OrderListV3Helper::getDropdownToggleHtml('????????????', $omsPlatformFinds);
			if(!empty($_REQUEST['selleruserid_combined'])){
				echo '<span onclick="OrderCommon.order_platform_find(\'oms\',\'rumall\',\'\')" class="glyphicon glyphicon-remove" style="cursor:pointer;font-size: 10px;line-height: 20px;color: red;margin-right:5px;" aria-hidden="true" data-toggle="tooltip" data-placement="top" data-html="true" title="" data-original-title="??????????????????????????????"></span>';
			}
			//?????????????????? E
			?>
			
			<?php 
				$order_source_status_search=[
					'?????????'=> '?????????' , 
					'?????????'=>'?????????' , 
				];
			?>
			<div class="input-group iv-input">
		        <?php $sel = [
		        	'order_source_order_id'=>'Rumall?????????',
					'sku'=>'SKU',
					'order_source_itemid'=>'Rumall Product ID',
					'tracknum'=>'?????????',
					'buyeid'=>'????????????',
		        	'consignee'=>'????????????',
					'email'=>'??????Email',
					'order_id'=>'???????????????',
					'root_sku'=>'??????SKU',
					'product_name'=>'????????????',
				]?>
				<?=Html::dropDownList('keys',@$_REQUEST['keys'],$sel,['class'=>'iv-input','style'=>'width:150px;margin:0px'])?>
		      	<?=Html::textInput('searchval',@$_REQUEST['searchval'],['class'=>'iv-input','id'=>'num','style'=>'width:150px','placeholder'=>'????????????????????????'])?>
		    </div>
		    <?=Html::checkbox('fuzzy',@$_REQUEST['fuzzy'],['label'=>TranslateHelper::t('????????????')])?>
		    
		    <!--  
	    	<?=Html::button('??????',['class'=>"iv-btn btn-search btn-spacing-middle",'onclick'=>"javascript:cleform();"])?>
	    	-->
	    	
	    	<a id="simplesearch" href="#" style="font-size:12px;text-decoration:none;" onclick="mutisearch();">????????????<span class="glyphicon glyphicon-menu-down"></span></a>	 
	    	<?=Html::submitButton('??????',['class'=>"iv-btn btn-search btn-spacing-middle",'id'=>'search'])?>
	    	<!--  
	    	<a title="????????????" class="iv-btn btn-important" onclick="dosyncorder();" style="color:white;">????????????</a>
	    	-->
	    	<a target="_blank"  title="????????????" class="iv-btn btn-important" href="/order/order/manual-order-box?platform=rumall" >????????????</a>
			
	    	<!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
			<?php if (!empty($_REQUEST['menu_select']) && $_REQUEST['menu_select'] =='all'):?>
			<div class="pull-right" style="height: 40px;">
				<?=Html::button(TranslateHelper::t('????????????'),['class'=>"iv-btn btn-important",'onclick'=>"OrderCommon.showAccountSyncInfo(this);",'name'=>'btn_account_sync_info','data-url'=>'/order/rumall-order/order-sync-info'])?>
			</div>
			<?php endif;?>
	    	<!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
			<!--  
			<div class="pull-right" style="height: 40px;display:none;">
				<?php
		    	if (!empty($counter['custom_condition'])){
		    		$sel_custom_condition = array_merge(['??????????????????'] , array_keys($counter['custom_condition']));
		    	}else{
		    		$sel_custom_condition =['0'=>'??????????????????'];
		    	}
		    	
		    	echo Html::dropDownList('sel_custom_condition',@$_REQUEST['sel_custom_condition'],$sel_custom_condition,['class'=>'iv-input'])?>
		    	<?=Html::button('?????????????????????',['class'=>"iv-btn btn-search",'onclick'=>"showCustomConditionDialog()",'name'=>'btn_save_custom_condition'])?>
			</div>
	    	-->
	    	<div class="mutisearch" <?php if ($showsearch!='1'){?>style="display: none;"<?php }?>>
	    	
	    	<!-- ----------------------------------?????????--------------------------------------------------------------------------------------------- -->
	    	<div style="margin:20px 0px 0px 0px">
			<div class="input-group"  name="div-select-nation"  data-role-id="0"  style='margin:0px'>
				<?=Html::textInput('country',@$_REQUEST['country'],['class'=>'iv-input','placeholder'=>'???????????????','id'=>'country','style'=>'width:200px;margin:0px'])?>
			</div>
	    	
	    				<?php 
			// ?????????
			$carriersProviderList = CarrierOpenHelper::getOpenCarrierArr('2');
			
			echo Html::dropDownList('carrier_code',@$_REQUEST['carrier_code'],$carriersProviderList,['class'=>'iv-input','prompt'=>'?????????','id'=>'order_carrier_code','style'=>'width:200px;margin:0px'])
			?>

			<?php $carriers=CarrierApiHelper::getShippingServices()?>
			<?=Html::dropDownList('shipmethod',@$_REQUEST['shipmethod'],$carriers,['class'=>'iv-input','prompt'=>'????????????','id'=>'shipmethod','style'=>'width:200px;margin:0px'])?>
			<?php $TrackerStatusList = [''=>'??????????????????'];
			 	$tmpTrackerStatusList = Tracking::getChineseStatus('',true);
				$TrackerStatusList+= $tmpTrackerStatusList;
				echo Html::dropDownList('tracker_status',@$_REQUEST['tracker_status'],$TrackerStatusList,['class'=>'iv-input','style'=>'width:200px;margin:0px'])?>
			</div>

			<!-- ----------------------------------?????????--------------------------------------------------------------------------------------------- -->
			<div style="margin:20px 0px 0px 0px">
			<?=Html::dropDownList('order_status',@$_REQUEST['order_status'],$orderStatus21,['class'=>'iv-input','prompt'=>'?????????????????????','id'=>'order_status','style'=>'width:200px;margin:0px'])?>
			<?=Html::dropDownList('order_source_status',@$_REQUEST['order_source_status'],$order_source_status_search,['class'=>'iv-input','prompt'=>'Rumall??????','id'=>'order_source_status','style'=>'width:200px;margin:0px'])?>
			
			 <?php 
			    $PayOrderTypeList = [''=>'?????????????????????'];
				$PayOrderTypeList+=Odorder::$payOrderType ;
// 				$PayOrderTypeList[OdOrder::PAY_EXCEPTION] = '?????????';
				?>
			 <?=Html::dropDownList('pay_order_type',@$_REQUEST['pay_order_type'],$PayOrderTypeList,['class'=>'iv-input','style'=>'width:200px;margin:0px'])?>
			 
			<?php $reorderTypeList = [''=>'??????????????????'];
			$reorderTypeList+=Odorder::$reorderType ;?>
			<?=Html::dropDownList('reorder_type',@$_REQUEST['reorder_type'],$reorderTypeList,['class'=>'iv-input','style'=>'width:200px;margin:0px'])?>
			
			 <?php 
			 /*
			 $exceptionStatusList = [''=>'????????????'];
				$exceptionStatusList+= ['223'=>'?????????'] ;
				
				?>
			 <?=Html::dropDownList('exception_status',@$_REQUEST['exception_status'],$exceptionStatusList,['class'=>'iv-input','style'=>'width:200px;margin:0px'])
			 */
			 ?>
			 </div>
			 <!-- ----------------------------------?????????--------------------------------------------------------------------------------------------- -->
			 <div style="margin:20px 0px 0px 0px">
			<?php 
			$warehouses = InventoryApiHelper::getWarehouseIdNameMap(true); 
			if (count($warehouses)>1){
				echo Html::dropDownList('cangku',@$_REQUEST['cangku'],$warehouses,['class'=>'iv-input','prompt'=>'??????','style'=>'width:200px;margin:0px']);
			}
			$warehouses +=['-1'=>'?????????'];//table ????????? ???-1??????
			$orderCaptureList = ['N'=>'????????????','Y'=>'????????????'];
			echo Html::dropDownList('order_capture',@$_REQUEST['order_capture'],$orderCaptureList,['class'=>'iv-input','prompt'=>'??????','style'=>'width:70px;']);
			?> 
			 
			 <!-- <?=Html::dropDownList('order_evaluation',@$_REQUEST['order_evaluation'],OdOrder::$orderEvaluation,['class'=>'iv-input','prompt'=>'???????????????','id'=>'order_evaluation','style'=>'width:200px;margin:0px'])?> -->
			  
			 <?=Html::dropDownList('timetype',@$_REQUEST['timetype'],['soldtime'=>'????????????','paidtime'=>'????????????','printtime'=>'????????????','shiptime'=>'????????????'],['class'=>'iv-input'])?>
        	<?=Html::input('date','startdate',@$_REQUEST['startdate'],['class'=>'iv-input','style'=>'width:150px;margin:0px'])?>
        	???
			<?=Html::input('date','enddate',@$_REQUEST['enddate'],['class'=>'iv-input','style'=>'width:150px;margin:0px'])?>
			</div>
			<!-- ----------------------------------?????????--------------------------------------------------------------------------------------------- -->
			<div id="div_sys_tag" style="margin:20px 0px 0px 0px">
			<strong style="font-weight: bold;font-size:12px;">???????????????</strong>
			<?php foreach (OrderTagHelper::$OrderSysTagMapping as $tag_code=> $label){
				echo Html::checkbox($tag_code,@$_REQUEST[$tag_code],['label'=>TranslateHelper::t($label)]);
			}
			//echo Html::checkbox('is_reverse',@$_REQUEST['is_reverse'],['label'=>TranslateHelper::t('??????')]);
			?>
			<!-- 
			<div class="btn-group" data-toggle="buttons">
			<?php foreach (OrderTagHelper::$OrderSysTagMapping as $tag_code=> $label){
				//echo Html::($tag_code,@$_REQUEST[$tag_code],['label'=>TranslateHelper::t($label)]);
				echo '<button type="button" class="btn btn-success btn-sm"><span class="glyphicon glyphicon-ok-sign" style="margin-right: 5px;"></span>'.TranslateHelper::t($label).'</button>';
				
			}
			
			//echo Html::checkbox('is_reverse',@$_REQUEST['is_reverse'],['label'=>TranslateHelper::t('??????')]);
			?>
			</div>
			-->
			</div>
			<!-- ----------------------------------?????????--------------------------------------------------------------------------------------------- -->
			<?php if (!empty($all_tag_list)):?>
			<div style="margin:20px 0px 0px 0px">
				<div class="pull-left">
				<strong style="font-weight: bold;font-size:12px;">??????????????????</strong>
				</div>
				<div class="pull-left" style="height: 40px;">
				<?=Html::checkboxlist('sel_tag',@$_REQUEST['sel_tag'],$all_tag_list);?>
				</div>
			</div>
			<?php endif; // ????????????????>
			</div> 
			
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
	
<!-- ----------------------------------dashboard start--------------------------------------------------------------------------------------- -->
<!-- 
<div class="" id="dash-board-enter" onclick="showDashBoard(0)" style="<?=$isDisplayDashBoardStr?>background-color:#374655;color: white;" title="??????dash-board">????????????????????????</div>
 -->
<!-- ----------------------------------dashboard end----------------------------------------------------------------------------------------- -->		
	
<!-- ----------------------------------?????? start--------------------------------------------------------------------------------------------- -->		
<div style="clear: both;">
		<form name="a" id="a" action="" method="post">
		<div class="pull-left" style="height: 40px;">
		<!--
		<?=Html::button('??????????????????',['class'=>"iv-btn btn-refresh",'onclick'=>"refreshLeftMenu()",'name'=>'btn_refresh_left_menu'])?>
		<span qtipkey="oms_refresh_left_menu_aliexpress" class ="click-to-tip"></span>
		-->
		<?php 
		if (isset($_REQUEST['order_status']) && $_REQUEST['order_status'] =='100'){
// 			echo Html::button('????????????',['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('signpayed');"]);
// 			echo "&nbsp;";
			echo Html::button('????????????',['class'=>"iv-btn btn-important",'onclick'=>"orderCommonV3.doaction('cancelorder');"]);
		}elseif (isset($_REQUEST['order_status']) && $_REQUEST['order_status'] =='200' ){
			$PayBtnHtml = Html::button('??????????????????',['class'=>"iv-btn btn-important",'onclick'=>"orderCommonV3.oaction('changeWHSM');"])."&nbsp;";
			$PayBtnHtml .= Html::button('??????????????????',['class'=>"iv-btn btn-important",'onclick'=>"orderCommonV3.doaction('changeItemDeclarationInfo');"]). "&nbsp;";
			//$PayBtnHtml .= Html::button('???????????????',['class'=>"iv-btn btn-important",'onclick'=>"OrderCommon.getTrackNo()"]). "&nbsp;";
			$PayBtnHtml .= Html::button('???????????????',['class'=>"iv-btn btn-important",'onclick'=>"orderCommonV3.doaction('signwaitsend');"]). "&nbsp;";
			$PayBtnHtml .=  '<span data-qtipkey="oms_batch_ship_pack" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';
			
			if (in_array(@$_REQUEST['exception_status'], ['223'])){
// 				echo Html::button(TranslateHelper::t('????????????'),['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('mergeorder');"]);echo "&nbsp;";
// 				echo '<span data-qtipkey="oms_order_exception_pending_merge_merge" class ="click-to-tip"><img style="cursor:pointer;padding-top:8px;" width="16" src="/images/questionMark.png"></span>&nbsp;';			  
				
			}elseif(!empty($_REQUEST['is_merge'])){
// 			    echo Html::button(TranslateHelper::t('????????????'),['class'=>"iv-btn btn-important",'onclick'=>"javascript:doaction('cancelMergeOrder');"]).'&nbsp;';
			}
			
			echo $PayBtnHtml;
			
		}
		
		if (@$_REQUEST['pay_order_type'] == 'reorder' || in_array(@$_REQUEST['exception_status'], ['203','222','202']) ){
			//echo Html::checkbox( 'chk_refresh_force','',['label'=>TranslateHelper::t('??????????????????')]);
		}
		?>
	
		
		<?php
// 		echo Html::dropDownList('do','',$doarr,['onchange'=>"orderCommonV3.doaction2(this);",'class'=>'iv-input do','style'=>'width:200px;margin:0px']);
		$doDownListHtml = OrderListV3Helper::getDropdownToggleHtml('????????????', $doarr, 'orderCommonV3.doaction3');
		echo $doDownListHtml;
		?> 
	
		<!--<?=Html::dropDownList('do','',$excelmodels,['onchange'=>"exportorder($(this).val());",'class'=>'iv-input do','style'=>'width:200px;margin:0px']);?>--> 
		<?php 
// 		echo Html::dropDownList('orderExcelprint','',['-1'=>'????????????','0'=>'???????????????','1'=>'??????????????????'],['onchange'=>"OrderCommon.orderExcelprint($(this).val())",'class'=>'iv-input do','style'=>'width:200px;margin:0px']);
		$excelActionItems = array('0'=>array('event'=>'OrderCommon.orderExcelprint(0)','label'=>'???????????????'), '1'=>array('event'=>'OrderCommon.orderExcelprint(1)','label'=>'??????????????????'));
		$excelDownListHtml = OrderListV3Helper::getDropdownToggleHtml('????????????', $excelActionItems);
		echo $excelDownListHtml;
		?>
		
		<?php $divTagHtml = "";
			$div_event_html = "";
		?>
		</div>
		<!-----------------------??????????????????end--------------------------->
		<div class="pull-right">
		<?php
			if (isset($_REQUEST['order_status']) && $_REQUEST['order_status'] =='200' ){
				echo Html::button(TranslateHelper::t('???????????????'),['class'=>"iv-btn btn-important",'onclick'=>"OrderCommon.importTrackNoBox('amazon')"]);
			}
		
			if(@$_GET['order_status'] == 100){
				echo '<div class="col-md-2"> <a class="btn" style="background-image: url(/images/cuikuan.png);width: 151px;height: 51px;margin-top:-9px;margin-left: 200px; " type="button" href="/assistant/rule/list"></a></div>';
			}
		?>
		</div>
		
		<!--<?=Html::button('??????????????????',['class'=>'btn btn-primary','onclick'=>'dosyncorder();'])?>-->
		
        <!----------------------??????????????????end------------------------------>
		<br>
		    <?php
				echo $this->render('../order/order_list_v3',[ 'carriers'=>$carriers, 'models'=>$models,
						'warehouses'=>$warehouses, 'non17Track'=>$non17Track, 'tag_class_list'=>$tag_class_list,
						'current_order_status' => empty($_REQUEST['order_status']) ? '' : $_REQUEST['order_status'],
						'current_exception_status' => empty($_REQUEST['exception_status']) ? '' : $_REQUEST['exception_status'],
						'platform_type'=>'rumall']);
			?>
		</form>
			<!-- ----------------------------------??????start--------------------------------------------------------------------------------------------- -->			
		<?php if($pages):?>
		<div id="pager-group">
		    <?= \eagle\widgets\SizePager::widget(['pagination'=>$pages , 'pageSizeOptions'=>array( 5 , 20 , 50 , 100 , 200 ) , 'class'=>'btn-group dropup']);?>
		    <div class="btn-group" style="width: 49.6%; text-align: right;">
		    	<?=\yii\widgets\LinkPager::widget(['pagination' => $pages,'options'=>['class'=>'pagination']]);?>
			</div>
		</div>
		<?php endif;?>
<!-- ----------------------------------??????end--------------------------------------------------------------------------------------------- -->	
	
	</div>

<!-- ----------------------------------??????end--------------------------------------------------------------------------------------------- -->	
<?php if (@$_REQUEST['order_status'] == 200 ):?>
<!--  
<div>
<ul>
	<li style="float: left;line-height: 60px;">????????????????????????????????????????????????</li>
	<li style="float: left;">
	<?php 
// 	    echo OrderFrontHelper::displayOrderPaidProcessHtml($counter)
	?>
	</li>
</ul>
-->
</div>
<?php endif;?>

<div style="clear: both;"></div>
</div></div>
<div style="display: none;">
<?=$divTagHtml?>
<?=$div_event_html?>
<div id="div_ShippingServices"><?=Html::dropDownList('demo_shipping_method_code',@$order->default_shipping_method_code,CarrierApiHelper::getShippingServices())?></div>
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
	<div>????????????????????? ?????? ????????????<br><b style="font-weight: 600;color:red">??????????????????????????????????????????????????????????????????????????????</b><br>??????????????????????????????????????????????????????????????????????????? ??????????????? <b style="font-weight: 600;color:blue">??????</b>?????? ?????? <b style="font-weight: 600;color:blue">??????</b>??????????????????????????????????????? ?????? ???????????? ?????? ???????????????<br>??????????????????????????? <b style="font-weight: 600;color:red">??????</b>?????????????????????????????????<b style="font-weight: 600;color:red">?????????</b>????????????????????????????????????????????????????????????.</div>
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
    	case 'cancelMergeOrder':
    		var thisOrderList =[];
    		$('input[name="order_id[]"]:checked').each(function(){
    			thisOrderList.push($(this).val());
    		});
    
    		OrderCommon.cancelMergeOrder(thisOrderList);
    		break;
		case 'ExternalDoprint':
			var thisOrderList =[];
			
			$('input[name="order_id[]"]:checked').each(function(){
				thisOrderList.push($(this).val());
			});
			OrderCommon.ExternalDoprint(thisOrderList);
			break;
		case 'changeItemDeclarationInfo':
			var thisOrderList =[];
			
			$('input[name="order_id[]"]:checked').each(function(){
				thisOrderList.push($(this).val());
			});
	
			OrderCommon.showChangeItemDeclarationInfoBox(thisOrderList);
			break;
// 		case 'extendsBuyerAcceptGoodsTime':
// 			var thisOrderList =[];
			
// 			$('input[name="order_id[]"]:checked').each(function(){
// 				thisOrderList.push($(this).val());
// 			});

// 			showExtendsBuyerAcceptGoodsTimeBoxOMS(thisOrderList);
// 			break;
		case 'invoiced':
			window.open("<?=Url::to(['/order/order/order-invoice'])?>"+"?order_id="+orderid,'_blank');
			break;
		case 'refreshOrder':
			var thisOrderList =[];
			
			$('input[name="order_id[]"]:checked').each(function(){
				thisOrderList.push($(this).val());
			});
			$.post('<?=Url::to(['/order/rumall-order/refreshorder'])?>',{orders:thisOrderList},function(result){
				bootbox.alert(result);
				location.reload();
			});
			break; 
		case 'stockManage':
			var thisOrderList =[];
			
			$('input[name="order_id[]"]:checked').each(function(){
				thisOrderList.push($(this).val());
			});
			
			OrderCommon.showStockManageBox(thisOrderList);
			break;
		case 'addMemo':
			var idstr = [];
			$('input[name="order_id[]"]:checked').each(function(){
				idstr.push($(this).val());
			});
			OrderCommon.showAddMemoBox(idstr);
			break;
		case 'reorder':
		var thisOrderList = [];
			$('input[name="order_id[]"]:checked').each(function(){
				thisOrderList.push($(this).val());
			});
			OrderCommon.reorder(thisOrderList);
			break;
		case 'changeWHSM':
			var thisOrderList =[];
			
			$('input[name="order_id[]"]:checked').each(function(){
				
				if ($(this).parents("tr:contains('?????????')").length == 0) return;
				
				thisOrderList.push($(this).val());
				//if (idstr != '') idstr+=',';
				//idstr+=$(this).val();

			});

			if (thisOrderList.length == 0){
				bootbox.alert('??????????????????????????????????????????');
				return;
			}

			OrderCommon.showWarehouseAndShipmentMethodBox(thisOrderList);
			
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
		case 'checkorder':
			idstr='';
			$('input[name="order_id[]"]:checked').each(function(){
				idstr+=','+$(this).val();
			});
			if ($('[name=chk_refresh_force]').prop('checked') == undefined) {
				var refresh_force = false;
			}else{
				var refresh_force = $('[name=chk_refresh_force]').prop('checked');
			}
			
			$.post('<?=Url::to(['/order/rumall-order/checkorderstatus'])?>',{orders:idstr , 'refresh_force':refresh_force},function(result){
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
		case 'signwaitsend':
			idstr='';
			$('input[name="order_id[]"]:checked').each(function(){
				idstr+=','+$(this).val();
			});
			$.post('<?=Url::to(['/order/order/signwaitsend'])?>',{orders:idstr},function(result){
				bootbox.alert({  
					buttons: {  
					   ok: {  
							label: Translator.t('??????'),  
							className: 'iv-btn btn-search'  
						}  
					},  
					message: result,  
					callback: function() {  
						location.reload(); 
					},  
					title: "????????????",  
				});
			});
			break;
		case 'mergeorder':
			document.a.target="_blank";
			document.a.action="<?=Url::to(['/order/order/mergeorder'])?>";
			document.a.submit();
			document.a.action="";
			break;
// 		case 'givefeedback':
// 			idstr = [];
// 			$('input[name="order_id[]"]:checked').each(function(){
// 				idstr.push($(this).val());
// 			});
// 			OrderCommon.givefeedback(idstr);
// 			break;
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

function doactionone2(obj,orderid){
	var val = $(obj).val();
	if (val != ''){
		doactionone(val,orderid);
		$(obj).val('');
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
    	case 'cancelMergeOrder':
    		var thisOrderList =[];
    		thisOrderList.push(orderid);
    		OrderCommon.cancelMergeOrder(thisOrderList);
    		break;
		case 'ExternalDoprint':
			var thisOrderList =[];
			thisOrderList.push(orderid);
			OrderCommon.ExternalDoprint(thisOrderList);
			break;
		case 'changeItemDeclarationInfo':
			var thisOrderList =[];
			thisOrderList.push(orderid);
			OrderCommon.showChangeItemDeclarationInfoBox(thisOrderList);
			break;
		case 'signcomplete':
			idstr = [];
			
			idstr.push(orderid);
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
		case 'invoiced':
			window.open("<?=Url::to(['/order/order/order-invoice'])?>"+"?order_id="+orderid,'_blank');
			break;
// 		case 'extendsBuyerAcceptGoodsTime':
// 			var thisOrderList =[];
// 			thisOrderList.push(orderid);
// 			showExtendsBuyerAcceptGoodsTimeBoxOMS(thisOrderList);
// 			break;
		
		case 'stockManage':
			var thisOrderList =[];
			thisOrderList.push(orderid);
			
			OrderCommon.showStockManageBox(thisOrderList);
			break;
	
		case 'skipMerge':
			var thisOrderList =[];
			thisOrderList.push(orderid);
			
			$.post('<?=Url::to(['/order/order/skipmerge'])?>',{orders:thisOrderList},function(result){
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
		case 'changeWHSM':
			var thisOrderList =[];
			thisOrderList.push(orderid);
	
			OrderCommon.showWarehouseAndShipmentMethodBox(thisOrderList);
			
			break;
// 		case 'extendsBuyerAcceptGoodsTime':
// 			var thisOrderList =[];
// 			thisOrderList.push(orderid);
// 			showExtendsBuyerAcceptGoodsTimeBoxOMS(thisOrderList);
// 			break;
		case 'reorder':
			idstr = [];
			idstr.push(orderid);
			OrderCommon.reorder(idstr);
			break;
		case 'editOrder':
			idstr = [];
			idstr.push(orderid);
			window.open("<?=Url::to(['/order/rumall-order/edit'])?>"+"?orderid="+orderid,'_blank')
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
		case 'addMemo':
			var idstr = [];
			idstr.push(orderid);
			OrderCommon.showAddMemoBox(idstr);
			break;
		case 'cancelorder':
			idstr = [];
			idstr.push(orderid);
			
			$.post('<?=Url::to(['/order/order/cancelorder'])?>',{orders:idstr},function(result){
				bootbox.alert(result);
				location.reload();
			});
			break;
// 		case 'givefeedback':
// 			/**/
// 			idstr = [];
// 			idstr.push(orderid);
// 			OrderCommon.givefeedback(idstr);
// 			break;
		case 'abandonorder':
			idstr = [];
			idstr.push(orderid);
			$.post('<?=Url::to(['/order/order/abandonorder'])?>',{orders:idstr},function(result){
				bootbox.alert(result);
				location.reload();
			});
			break;
		case 'refreshOrder':
			idstr = [];
			idstr.push(orderid);
			$.post('<?=Url::to(['/order/rumall-order/refreshorder'])?>',{orders:idstr},function(result){
				bootbox.alert(result);
				location.reload();
			});
			break; 
		case 'cancelorder':
			idstr = [];
			idstr.push(orderid);
			$.post('<?=Url::to(['/order/order/cancelorder'])?>',{orders:idstr},function(result){
				bootbox.alert(result);
				location.reload();
			});
			break;
		case 'checkorder':
			if ($('[name=chk_refresh_force]').prop('checked') == undefined) {
				var refresh_force = false;
			}else{
				var refresh_force = $('[name=chk_refresh_force]').prop('checked');
			}
			
			$.post('<?=Url::to(['/order/rumall-order/checkorderstatus'])?>',{orders:orderid , 'refresh_force':refresh_force},function(result){
				bootbox.alert(result);
				location.reload();
			});
			break;
		case 'signshipped':
			window.open("<?=Url::to(['/order/order/signshipped'])?>"+"?order_id="+orderid,'_blank')
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
	$.post('<?=Url::to(['/order/rumall-order/movestatus'])?>',{orderids:idstr,status:val},function(result){
		bootbox.alert('???????????????');
	});
}
//??????????????????
function importordertracknum(){
	if($("#order_tracknum").val()){
		$.ajaxFileUpload({  
			 url:'<?=Url::to(['/order/rumall-order/importordertracknum'])?>',
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
	$.post('<?=Url::to(['/order/rumall-order/changemanual'])?>',{orderid:orderid},function(result){
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
	$.post('<?=Url::to(['/order/rumall-order/setusertab'])?>',{orderid:orderid,tabid:tabid},function(result){
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
	  $.post('<?=Url::to(['/order/rumall-order/ajaxdesc'])?>',{desc:desc,oiid:oiid},function(r){
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
// 	$(':input','#form1').not(':button, :submit, :reset, :hidden').val('').removeAttr('checked').removeAttr('selected');
// 	$('select[name=keys]').val('order_id');
// 	$('select[name=timetype]').val('soldtime');
// 	$('select[name=ordersort]').val('soldtime');
// 	$('select[name=ordersorttype]').val('desc');

	$(':input','#form1').not(':button, :submit, :reset, :hidden').val('').removeAttr('checked').removeAttr('selected');

	$('#form1 select[name!=sel_custom_condition]').selectVal('');
	$('select[name=keys]').selectVal('order_id');
	$('select[name=timetype]').selectVal('soldtime');
	$('select[name=ordersort]').selectVal('soldtime');
	$('select[name=ordersorttype]').selectVal('desc');
	$('select[name=sel_tag]').selectVal('0');
	$('select[name=sel_custom_condition]').selectVal('0');
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
			$(obj).parent().html('<span class="glyphicon glyphicon-plus" onclick="spreadorder(this);">');
			$('.orderspread').attr('class','orderspread glyphicon glyphicon-plus');
			return false;
		}else{
			//???????????????????????????,'+'?????????
			$('.xiangqing').show();
			$(obj).parent().html('<span class="glyphicon glyphicon-minus" onclick="spreadorder(this);">');
			$('.orderspread').attr('class','orderspread glyphicon glyphicon-minus');
			return false;
		}
	}else{
		//????????????ID??????????????????????????????????????????
		var html = $(obj).parent().html();
		if(html.indexOf('minus')!=-1){
			//???????????????????????????,'-'?????????
			$('.'+id).hide();
			$(obj).attr('class','orderspread glyphicon glyphicon-plus');
			return false;
		}else{
			//???????????????????????????,'+'?????????
			$('.'+id).show();
			$(obj).attr('class','orderspread glyphicon glyphicon-minus');
			return false;
		}
	}
}

//??????????????????
function dosyncorder(){
	var Url=global.baseUrl +'order/rumall-order/syncmt';
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
				data: {invoker: 'Rumall-Oms'},
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
				data: {invoker: 'Rumall-Oms'},
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
		url:'/order/rumall-order/user-dash-board?autoShow='+autoShow, 
		success: function (result) {
			$.hideLoading();
			$("#dash-board-enter").toggle();
			bootbox.dialog({
				className : "dash-board",
				title: Translator.t('Rumall??????????????????'),
				message: result,
				closeButton:false,
				buttons:{
					
					Cancel: {  
						label: Translator.t("??????"),  
						className: "btn-default",  
						callback: function () {  
							hideDashBoard();
						}
					}, 
				}
			});
			return true;
		},
		error :function () {
			$.hideLoading();
			bootbox.alert(Translator.t('??????Aliexpress????????????????????????'));
			return false;
		}
	});
}

function requestGenerateDashBoardData(){
	if ($('#dash-board-enter').css('display') != 'none'){
		$('#dash-board-enter').toggle(1000);
	}
	
	$.ajax({
		type: "GET",
		url:'/order/rumall-order/genrate-user-dash-board', 
		success: function (result) {
			if ($('#dash-board-enter').css('display') == 'none'){
				$('#dash-board-enter').toggle(1000);
			}
			return true;
		},
		error :function () {
			return false;
		}
	});
	
}

function hideDashBoard(){
	$("#dash-board-enter").toggle();
	var dash_board_top = $("#dash-board-enter").offset().top;
	var  dash_board_height= $("#dash-board-enter").height();
	if(typeof(dash_board_height)=='undefined')
		dash_board_height = 0;
	if(typeof(dash_board_top)=='undefined')
		var dash_board_top = 800;
	else 
		top = dash_board_top + dash_board_height/2;
}

function ignoreTrackingNo(order_id,track_no){
	bootbox.confirm({
		buttons: {  
			confirm: {  
				label: Translator.t("??????"),  
				className: 'btn-danger'  
			},  
			cancel: {  
				label: Translator.t("??????"),
				className: 'btn-primary'  
			}  
		}, 
		message: Translator.t("????????????????????????<br><br>???????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????"),
		callback: function(r){
			if(r){
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
					            },  
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
		}
	});
}

</script>