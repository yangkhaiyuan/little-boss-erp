<?php
namespace eagle\modules\delivery\controllers;
use \Yii;
use yii\data\Pagination;
use yii\web\Controller;
use eagle\modules\order\models\OdOrder;
use common\helpers\Helper_Array;
use eagle\modules\carrier\apihelpers\ApiHelper;
use eagle\models\SaasEbayUser;
use yii\helpers\Url;
use eagle\modules\inventory\models\Warehouse;
use eagle\models\User;
use eagle\modules\order\models\OdOrderShipped;
use eagle\models\QueueSyncshipped;
use eagle\modules\order\models\OdOrderItem;
use eagle\modules\order\models\OdEbayTransaction;
use eagle\modules\order\helpers\QueueShippedHelper;
use eagle\modules\order\helpers\AmazonDeliveryApiHelper;
use yii\caching\DummyCache;
use eagle\modules\delivery\models\OdDeliveryOrder;
use eagle\modules\util\helpers\OperationLogHelper;
use eagle\modules\delivery\models\OdDelivery;
use eagle\modules\catalog\helpers\ProductApiHelper;
use eagle\modules\inventory\helpers\InventoryApiHelper;
use eagle\modules\util\models\OperationLog;
use eagle\modules\app\apihelpers\AppTrackerApiHelper;
use eagle\modules\delivery\helpers\DeliveryHelper;
use eagle\modules\order\models\Excelmodel;
use eagle\modules\order\helpers\OrderHelper;
use eagle\modules\order\models\Usertab;
use Exception;
use eagle\models\carrier\SysShippingService;
use eagle\models\EbayCountry;
use eagle\modules\order\helpers\OrderTagHelper;
use eagle\models\SaasAliexpressUser;
use common\api\aliexpressinterface\AliexpressInterface_Api;
use common\api\aliexpressinterface\AliexpressInterface_Helper;
use console\helpers\AliexpressHelper;
use eagle\modules\util\helpers\ConfigHelper;
use eagle\modules\order\helpers\AliexpressOrderHelper;
use eagle\modules\util\helpers\TranslateHelper;
use eagle\models\sys\SysCountry;
use eagle\modules\inventory\helpers\WarehouseHelper;
use yii\db\Transaction;
use eagle\modules\carrier\apihelpers\CarrierApiHelper;
use eagle\modules\order\apihelpers\OrderApiHelper;
use eagle\modules\order\models\OdOrderGoods;
use eagle\modules\carrier\helpers\CarrierOpenHelper;
use eagle\models\carrier\SysCarrier;
use eagle\modules\order\helpers\CdiscountOrderInterface;
use eagle\modules\util\helpers\ExcelHelper;
use yii\data\ActiveDataProvider;
use eagle\modules\catalog\helpers\ProductHelper;
use eagle\modules\util\models\ConfigData;
use eagle\modules\carrier\controllers\CarrierprocessController;
use eagle\modules\inventory\helpers\InventoryHelper;
use eagle\modules\platform\apihelpers\PlatformAccountApi;
use eagle\modules\carrier\models\SysCarrierCustom;
use Qiniu\json_decode;
use eagle\modules\util\helpers\TimeUtil;
use eagle\modules\delivery\apihelpers\DeliveryApiHelper;
use eagle\modules\util\helpers\ImageCacherHelper;
use eagle;
use eagle\modules\carrier\apihelpers\PrintPdfHelper;
use eagle\modules\permission\apihelpers\UserApiHelper;
use eagle\modules\carrier\helpers\CarrierDeclaredHelper;
use eagle\modules\util\helpers\RedisHelper;
use eagle\modules\permission\helpers\UserHelper;
use Jurosh\PDFMerge\PDFMerger;
class OrderController extends \eagle\components\Controller{
	public $defaultAction = 'List';
	public $enableCsrfValidation = false;
	#########################################################################2.1?????? ###########################################################
	/**
	 +----------------------------------------------------------
	 * ??????????????????????????????,??????????????????????????????????????????????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		million 	2015/01/05				?????????
	 +----------------------------------------------------------
	 **/
	function actionListnodistributionwarehouse(){
		//????????????????????????????????????URL,??????????????????????????????????????????
		return $this->redirect('/delivery/order/listplanceanorder');
		
		//$delivery_picking_mode ???????????????????????? 0:???????????????,1:????????????
		$delivery_picking_mode = ConfigHelper::getConfig('delivery_picking_mode');
		$delivery_picking_mode = empty($delivery_picking_mode) ? 0 : $delivery_picking_mode;
		
		AppTrackerApiHelper::actionLog("eagle_v2", "delivery/order/Listnodistributionwarehouse");
		$query_condition = $_REQUEST;
		$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
// 		$query_condition['no_warehouse_or_shippingservice'] = '(default_warehouse_id < 0 or default_shipping_method_code = "")';//??????????????????????????????
		$query_condition['no_warehouse_or_shippingservice'] = '(default_shipping_method_code = "")';//??????????????????????????????	???????????????????????????????????????????????????
		
		//??????????????????????????????????????????????????????
		if($delivery_picking_mode == 1){
			$counter = DeliveryHelper::getMenuStatisticData();
			$order_nav_html=DeliveryHelper::getOrderNav($counter);
		}else{
			$counter = DeliveryHelper::getMenuStatisticData('picking_mode_0');
			$order_nav_html=DeliveryHelper::getOrderNav($counter,'picking_mode_0');
		}
		
// 		$counter['?????????????????????'] = 0;
		
		//?????????????????????????????????????????????????????????????????????
		if($counter['?????????????????????'] == 0){
			if(empty($query_condition['selleruserid'])){
				return $this->redirect('/delivery/order/listalldelivery');
			}else{
				return $this->redirect('/delivery/order/listalldelivery?selleruserid='.$query_condition['selleruserid']);
			}
		}else{
			$search = array('is_comment_status'=>'???????????????');
			$carrierQtips = CarrierOpenHelper::getCarrierQtips();
			return $this->renderAuto('listnodistributionwarehouse',
					['counter'=>$counter,
					'order_nav_html'=>$order_nav_html,
					'search'=>$search,
					'carrierQtips'=>$carrierQtips,
					'delivery_picking_mode' => $delivery_picking_mode,
					]+CarrierprocessController::getlist($query_condition,'delivery/order')
			);
		}
	}
	
	/**
	 +----------------------------------------------------------
	 * ?????????????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		million 	2015/01/05				?????????
	 +----------------------------------------------------------
	 **/
	function actionListplanceanorder(){
		//$delivery_picking_mode ???????????????????????? 0:???????????????,1:????????????
// 		$delivery_picking_mode = ConfigHelper::getConfig('delivery_picking_mode');
// 		$delivery_picking_mode = empty($delivery_picking_mode) ? 0 : $delivery_picking_mode;
		$canAccessModule = UserApiHelper::checkModulePermission("delivery");
		$canAccessModule_edit = UserApiHelper::checkModulePermission("delivery_edit");
		if(!$canAccessModule){
			if($canAccessModule_edit){
				//???????????????????????????????????????
				return $this->redirect('/delivery/order/listprintdelivery');
			}
			
			exit("?????????????????????????????????!");
		}
		
		$t1 = \eagle\modules\util\helpers\TimeUtil::getCurrentTimestampMS();
 
		
		//?????????OMS??????????????????????????????????????????
		$use_mode = isset($_REQUEST['use_mode']) ? $_REQUEST['use_mode'] : '';
		
		AppTrackerApiHelper::actionLog("eagle_v2", "delivery/order/Listplanceanorder");
		$query_condition = $_REQUEST;
		$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
		$query_condition['warehouse_and_shippingservice'] = '(default_warehouse_id > -1 and default_shipping_method_code <> "")';
		
		//??????
// 		if($delivery_picking_mode == 1){
// 			if(isset($query_condition['warehouse_id']) && trim($query_condition['warehouse_id']) != ''){
// 				$warehouse_search = $query_condition['default_warehouse_id'] = $_REQUEST['default_warehouse_id'] = $query_condition['warehouse_id'];
// 			}else{
// 				$warehouse_search = $query_condition['default_warehouse_id'] = $_REQUEST['default_warehouse_id'] = 0;//?????????
// 			}
// 		}else{
			if(isset($query_condition['warehouse_id']) && trim($query_condition['warehouse_id']) != ''){
				$warehouse_search = $query_condition['default_warehouse_id'] = $_REQUEST['default_warehouse_id'] = $query_condition['warehouse_id'];
			}else{
				$warehouse_search = $query_condition['default_warehouse_id'] = $_REQUEST['default_warehouse_id'] = -2;//?????????
			}
			
			if($warehouse_search == -2){
				unset($query_condition['default_warehouse_id']);
			}
// 		}
		
		//????????????
		unset($query_condition['delivery_status']);
		
		//????????????
		$counter = DeliveryHelper::getMenuStatisticData($warehouse_search,(empty($query_condition['use_mode']) ? '' : $query_condition['use_mode']), 'listplanceanorder');
		
		//???????????????
		if(empty($_REQUEST['carrier_type']) || ($query_condition['carrier_type'] != 1 && $query_condition['carrier_type'] != 2 && $query_condition['carrier_type'] != 3)){
			if(!empty($counter['listplanceanorder'][1]['all']))
				$query_condition['carrier_type'] = $_REQUEST['carrier_type'] = 1;
			else if(!empty($counter['listplanceanorder'][2]['all']))
				$query_condition['carrier_type'] = $_REQUEST['carrier_type'] = 2;
			else if(!empty($counter['listplanceanorder'][3]['all']))
				$query_condition['carrier_type'] = $_REQUEST['carrier_type'] = 3;
			else
				$query_condition['carrier_type'] = $_REQUEST['carrier_type'] = 1;
		}
		
		//??????????????????
		if(empty($_REQUEST['carrier_step'])){
			//????????????????????????????????????????????????????????????????????????????????????
			if(!empty($counter['listplanceanorder'][$query_condition['carrier_type']])){
				$carrier_count = $counter['listplanceanorder'][$query_condition['carrier_type']];
				if(!empty($carrier_count[0]))
					$query_condition['carrier_step'] = $_REQUEST['carrier_step'] = 'UPLOAD';
				else if(!empty($carrier_count[1]))
					$query_condition['carrier_step'] = $_REQUEST['carrier_step'] = 'DELIVERY';
				else if(!empty($carrier_count[2]))
					$query_condition['carrier_step'] = $_REQUEST['carrier_step'] = 'DELIVERYED';
				else if(!empty($carrier_count[6]))
					$query_condition['carrier_step'] = $_REQUEST['carrier_step'] = 'FINISHED';
				else
					$query_condition['carrier_step'] = $_REQUEST['carrier_step'] = 'UPLOAD';
			}
		}
		
		switch ($query_condition['carrier_step']){
			case 'UPLOAD':
				$query_condition['carrier_step'] = [0,4];
				break;
			case 'DELIVERY':
				if(in_array($query_condition['carrier_type'], array(2,3))){
					$query_condition['carrier_step'] = [1,2,3];
				}else{
					$query_condition['carrier_step'] = 1;
				}
				break;
			case 'DELIVERYED':
				$query_condition['carrier_step'] = [2,3,5];
				break;
			case 'FINISHED':
				$query_condition['carrier_step'] = 6;
				break;
		}
		
		if ($_REQUEST['carrier_step'] == 'FINISHED'){
			if(empty($_REQUEST['order_abnormal'])){
				$query_condition['order_status']=OdOrder::STATUS_SHIPPED;
				unset($query_condition['carrier_step']);
			}else{
				$query_condition['order_status']=OdOrder::STATUS_WAITSEND;
				$query_condition['carrier_step'] = 6;
			}
		}
		
		//??????????????????
		$order_nav_html=DeliveryHelper::getOrderNav($counter,$warehouse_search);
		
        $carrierQtips = CarrierOpenHelper::getCarrierQtips();
        
        if(($counter['listplanceanorder']['2']['all'] == 0) && ($counter['listplanceanorder']['3']['all'] == 0)){
        	$_REQUEST['carrier_type'] = 1;
        	$query_condition['carrier_type'] = 1;
        }
        
        //?????????????????????????????????
        if(isset($query_condition['tmpdelivery_status_in'])){
        	$tmp_delivery_status_in = $query_condition['tmpdelivery_status_in'];
        	
        	if($tmp_delivery_status_in == 'Y'){
        		$query_condition['delivery_status_in'] = array(2,3,6);
        	}else if($tmp_delivery_status_in == 'N'){
        		$query_condition['delivery_status_in'] = array(0,1);
        	}
        }

        $tmpLists = CarrierprocessController::getlist($query_condition,'delivery/order', true, \Yii::$app->user->id, \Yii::$app->user->identity->getParentUid());
        
        $t2 = \eagle\modules\util\helpers\TimeUtil::getCurrentTimestampMS();
        // debug log
        \eagle\modules\util\helpers\SysBaseInfoHelper::addFrontDebugLog("DeliveryList CarrierprocessController::getlist t=".($t2-$t1));
        
        
        //?????????????????????????????????????????????????????????????????????????????????
        $order_rootsku_product_image = array();
        if(!empty($tmpLists['orders'])){
        	$order_rootsku_product_image = OrderHelper::GetRootSkuImage($tmpLists['orders']);
        }
        
        //????????????HTML??????
        $orderHtml = array();
        
        //?????????????????????????????????
        $is_smt_alionlinedelivery = false;
        
        if(isset($_REQUEST['carrier_step'])){
        	if($_REQUEST['carrier_step'] == 'UPLOAD'){
        		//????????????????????????????????????????????????
        		$tmpSearchShippingid = array();
        		$carrierAccountInfo = array();
        		
        		//?????????????????????????????????
        		$order_products = array();
        		
        		//????????????????????????????????????
        		$order_items_info = array();
        		
        		//??????API??????code
        		$default_carrier_codes = array();
        		$sys_carrier_params = array();
        		
        		foreach ($tmpLists['orders'] as $tmporders){
        			if(substr($tmporders->default_carrier_code, 0, 3) == 'lb_'){
        				$tmpSearchShippingid[$tmporders->default_shipping_method_code] = $tmporders->default_shipping_method_code;
        		
        				$default_carrier_codes[$tmporders->default_carrier_code] = $tmporders->default_carrier_code;
        				
        				if($tmporders->default_carrier_code == 'lb_alionlinedelivery'){
        					$is_smt_alionlinedelivery = true;
        				}
        				
        				foreach($tmporders->items as $item){
        					$tmp_platform_item_id = \eagle\modules\order\helpers\OrderGetDataHelper::getOrderItemSouceItemID($tmporders->order_source , $item);
        					$order_items_info[] = array('platform_type'=>$tmporders->order_source, 'order_status'=>$tmporders->order_status, 'xlb_item'=>$item->order_item_id,'sku'=>$item->sku, 'root_sku'=>$item->root_sku, 'itemID'=>$tmp_platform_item_id, 'declaration'=>json_decode($item->declaration,true));
        				}
        				CarrierOpenHelper::getCustomsDeclarationSumInfo($tmporders, $order_products);
        			}
        		}
        		
        		//????????????????????????
        		$result_item_declared_info = CarrierDeclaredHelper::getOrderDeclaredInfoBatch($order_items_info);
//         		print_r($order_items_info);exit;
        		
        		if(count($tmpSearchShippingid) > 0){
        			$carrierAccountInfo = CarrierOpenHelper::getCarrierAccountInfoByShippingId(array('shippings'=>$tmpSearchShippingid));
        		}
        		
        		if(count($default_carrier_codes) > 0){
        			$sys_carrier_params = CarrierOpenHelper::getSysCarrierParams(array('carrier_codes'=>$default_carrier_codes));
        		}
        		
        		$warehouseNameMap = InventoryApiHelper::getWarehouseIdNameMap();
        		
        		foreach ($tmpLists['orders'] as $tmporders){
        			if(substr($tmporders->default_carrier_code, 0, 3) == 'lb_'){
//         				$orderHtml[$tmporders->order_id] = '';
        				$orderHtml[$tmporders->order_id] = CarrierOpenHelper::getOrdersCarrierInfoView($tmporders, $order_products, $sys_carrier_params, $carrierAccountInfo, $warehouseNameMap, $result_item_declared_info);
        			}
        		}
        	}
        }
        
        $t3 = \eagle\modules\util\helpers\TimeUtil::getCurrentTimestampMS();
        // debug log
        \eagle\modules\util\helpers\SysBaseInfoHelper::addFrontDebugLog("DeliveryList Customs information organization t=".($t3-$t2));
        
        $tmp_REQUEST_text['REQUEST']=$query_condition;
        $tmp_REQUEST_text['order_source']=['order_source'=>'delivery'];
        $tmp_REQUEST_text['where']=Array();
        $tmp_REQUEST_text['orderBy']=Array();
        $tmp_REQUEST_text['params']=empty($tmpLists['pagination']->params)?Array():$tmpLists['pagination']->params;
        $tmp_REQUEST_text=base64_encode(json_encode($tmp_REQUEST_text));
        
        //??????????????????
        $countryListN = \eagle\modules\util\helpers\CountryHelper::getScopeCountry();
        
        if($is_smt_alionlinedelivery){
        	if(count($carrierAccountInfo) > 0){
        		foreach ($carrierAccountInfo as $tmp_carrierAccountInfo){
        			if($tmp_carrierAccountInfo['carrier_code'] == 'lb_alionlinedelivery'){
        				if(isset($tmp_carrierAccountInfo['api_params']['HomeLanshou'])){
        					if($tmp_carrierAccountInfo['api_params']['HomeLanshou'] != 'N'){
        						$is_smt_alionlinedelivery = false;
        					}
        				}
        				break;
        			}
        		}
        	}
        }
        
        $t4 = \eagle\modules\util\helpers\TimeUtil::getCurrentTimestampMS();
        // debug log
        \eagle\modules\util\helpers\SysBaseInfoHelper::addFrontDebugLog("DeliveryList Customs information organization t=".($t4-$t3));
        
        //?????????????????????lrq20171120
        $stock_list = array();
        if(!empty($tmpLists['orders'])){
        	$sku_list = array();
        	$warehouse_list = ['0'];
        	foreach($tmpLists['orders'] as $one){
        		if(!empty($one->default_warehouse_id) && !in_array($one->default_warehouse_id, $warehouse_list)){
        			$warehouse_list[] = $one->default_warehouse_id;
        		}
	        	foreach($one->items as $item){
	        		if(!empty($item->root_sku) && !in_array($item->root_sku, $sku_list)){
	        			$sku_list[] = $item->root_sku;
	        		}
	        	}
        	}
        	
        	$stock_list = \eagle\modules\inventory\apihelpers\InventoryApiHelper::GetSkuStock($sku_list, $warehouse_list);
        }
 
		return $this->renderAuto('listplanceanorder',
				['search_condition'=>$tmp_REQUEST_text,
			'search_count'=>$tmpLists['pagination']->totalCount,
				'counter'=>$counter,'order_nav_html'=>$order_nav_html,
					'carrierQtips'=>$carrierQtips,'orderHtml'=>$orderHtml,
				'order_rootsku_product_image' => $order_rootsku_product_image,'countryListN'=>$countryListN,'is_smt_alionlinedelivery'=>$is_smt_alionlinedelivery,
				'stock_list' => $stock_list
				]+$tmpLists);
	}
	
	/**
	 +----------------------------------------------------------
	 * ???????????? ????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		million 	2016/05/27			?????????
	 +----------------------------------------------------------
	 * 
	 */
	function actionMovetopacking(){
		AppTrackerApiHelper::actionLog("eagle_v2", "delivery/order/Movetopacking");
		try {
			$orderids = $_REQUEST['orderids'];
			$orderids = array_filter($orderids);
			$orders=OdOrder::findAll(['order_id'=>$orderids]);
			
			$tmp_warehouse = array();
			$edit_log = '';
			
			foreach ($orders as $order){
				if(!isset($tmp_warehouse[$order->default_warehouse_id])){
					$tmp_warehouse_type = WarehouseHelper::getWarehouseType($order->default_warehouse_id);
					$tmp_warehouse[$order->default_warehouse_id] = $tmp_warehouse_type['is_oversea'];
				}
				
				if($tmp_warehouse[$order->default_warehouse_id] == 1){
					return json_encode(array('success'=>false,'code'=>"100005",'message'=>'??????????????????,????????????????????????','data'=>array('orderid'=>$order->order_id,'100002'=>'#')));
				}
				
				if ($order->delivery_status > 1){
					return json_encode(array('success'=>false,'code'=>"100002",'message'=>'???????????????????????????,???????????????????????????','data'=>array('orderid'=>$order->order_id,'100002'=>'#')));
				}
				$skuInfo_arr = OrderApiHelper::getProductsByOrder($order->order_id);
				$products=[];
				//?????????????????????sku??????
				foreach ($skuInfo_arr as $one){
					if (!empty($one['root_sku']) && strlen($one['root_sku'])>0){
						$products[] = array('sku'=>$one['root_sku'] ,'qty'=>$one['qty'],'order_id'=>$order->order_id);
					}
				}
				if (empty($products)){
					return json_encode(array('success'=>false,'code'=>"100003",'message'=>TranslateHelper::t('?????????????????????????????????'),'data'=>array('orderid'=>$order->order_id,'100003'=>'#')));
				}
				//????????????
				$rtn2 = InventoryApiHelper::OrderProductReserve($order->order_id, $order->default_warehouse_id, $products);
				if ($rtn2['success']!==true){
					$warehouseIdNameMap = InventoryApiHelper::getWarehouseIdNameMap();
					OperationLogHelper::log('delivery',$order->order_id,'????????????',$rtn2['message'],\Yii::$app->user->identity->getFullName());
					return json_encode(array('success'=>false,'code'=>"100004",'message'=>TranslateHelper::t($rtn2['message']),'data'=>array('orderid'=>$order->order_id,'100004'=>'#')));
				}else{
					$oldStatus = $order->delivery_status;
					$order->delivery_status = OdOrder::DELIVERY_PICKING;
					$order->save();
					OperationLogHelper::log('delivery',$order->order_id,'????????????','????????????:'.OdOrder::$deliveryStatus[$oldStatus].'->'.OdOrder::$deliveryStatus[$order->delivery_status],\Yii::$app->user->identity->getFullName());
					
					//??????????????????
					UserHelper::insertUserOperationLog('delivery', "??????????????????, ?????????: ".ltrim($order->order_id, '0'), null, $order->order_id);
					
					return json_encode(array('success'=>true,'code'=>"100000",'message'=>'??????'.$order->order_id.'?????????????????????????????????????????????????????????,?????????','data'=>array('orderid'=>$order->order_id,'100000'=>'#')));
				}
				
			}
			//$this->redirect(['/delivery/order/listpicking','warehouse_id'=>$warehouse_id,'delivery_status'=>2,'picking_status'=>0] );
			
		}catch (Exception $ex){
			return json_encode(array('success'=>false,'code'=>"99999",'message'=>'???????????????????????????????????????????????????'.$ex->getMessage(),'data'=>array('99999'=>'#')));
		}
	}
	/**
	 +----------------------------------------------------------
	 * ?????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		million 	2015/01/05				?????????
	 +----------------------------------------------------------
	 * static $pickingStatus=[
        0=>'????????????????????????????????????',
        1=>'????????????????????????',
        2=>'????????????',
        3=>'????????????'???????????????,
        ];
     */
	function actionListpicking(){
	AppTrackerApiHelper::actionLog("eagle_v2", "delivery/order/Listpicking");
        $_REQUEST['list_picking_order']=isset($_REQUEST['list_picking_order'])?$_REQUEST['list_picking_order']:0;//?????????????????????
        $_REQUEST['is_print_picking']=isset($_REQUEST['is_print_picking'])?$_REQUEST['is_print_picking']:0;//???????????????????????????????????????????????????????????????????????????
        if(isset($_REQUEST['warehouse_id']) && trim($_REQUEST['warehouse_id']) != ''){
        	$query_condition['default_warehouse_id'] = $_REQUEST['default_warehouse_id'] = $_REQUEST['warehouse_id'];
        }else{
        	$query_condition['default_warehouse_id'] = $_REQUEST['default_warehouse_id'] = 0;//?????????
        }
        #####################################???????????????##############################################################
        if ($_REQUEST['list_picking_order']){
        	$_REQUEST['picking_status']=isset($_REQUEST['picking_status'])?$_REQUEST['picking_status']:0;
	        $pageSize = isset($_GET['per-page'])?$_GET['per-page']:20;
	        $data=OdDelivery::find();
	
	        $data->andWhere(['picking_status'=>$_REQUEST['picking_status']]);
	        $data->andWhere(['warehouseid'=>$_REQUEST['warehouse_id']]);
	
	        $showsearch=0;
	        $op_code = '';
	
	        //????????????
	        $keys = isset($_REQUEST['keys'])?trim($_REQUEST['keys']):"";
	        $searchval = isset($_REQUEST['searchval'])?trim($_REQUEST['searchval']):"";
	
	        //????????????
	        if (!empty($searchval))
	        {
	            //??????????????????????????????
	            if (in_array($keys, ['deliveryid'])) {
	                $kv=[ 'deliveryid'=>'deliveryid',];
	                $key = $kv[$keys];
	                $data->andWhere("$key = :val",[':val'=>$searchval]);
	            }
	            elseif ($keys=='sku') {
	                $deliveryids = Helper_Array::getCols(OdDeliveryOrder::find()->where('sku = :sku',[':sku'=>$searchval])->select('delivery_id')->asArray()->all(),'delivery_id');
	                $data->andWhere(['IN','deliveryid',$deliveryids]);
	            }
	            elseif ($keys=='order_id') {
	                $deliveryids = Helper_Array::getCols(OdDeliveryOrder::find()->where('order_id = :order_id',[':order_id'=>$searchval])->select('delivery_id')->asArray()->all(),'delivery_id');
	                $data->andWhere(['IN','deliveryid',$deliveryids]);
	            }
	            elseif ($keys=='order_source_order_id') {
	                $order_id = OdOrder::find()->select('order_id')->where('order_source_order_id = :order_source_order_id',[':order_source_order_id'=>$searchval])->asArray()->all();
	                $deliveryids = Helper_Array::getCols(OdDeliveryOrder::find()->where('order_id = :order_id',[':order_id'=>isset($order_id[0]['order_id'])?$order_id[0]['order_id']:''])->select('delivery_id')->asArray()->all(),'delivery_id');
	                $data->andWhere(['IN','deliveryid',$deliveryids]);
	            }
	
	        }
	        //??????
	        $pagination = new Pagination([
	            'defaultPageSize' => 20,
	            'pageSize' => $pageSize,
	            'totalCount' => $data->count(),
	            'pageSizeLimit'=>[5,200],//????????????????????????
	            'params'=>$_REQUEST,
	        ]);
	        $models = $data->offset($pagination->offset)
	            ->orderBy('id DESC')
	            ->limit($pagination->limit)
	            ->all();
	
	
	
	        //??????????????????????????????????????????????????????
	        $counter = DeliveryHelper::getMenuStatisticData($_REQUEST['warehouse_id']);
	        $order_nav_html=DeliveryHelper::getOrderNav($counter,$_REQUEST['warehouse_id']);
	        $warehouseids = InventoryApiHelper::getWarehouseIdNameMap();
	        $warehouseCount = count(InventoryApiHelper::getWarehouseIdNameMap(true));
	        
	        return $this->renderAuto('listpicking',array(
	            'models' => $models,
	            'pagination' => $pagination,
	            'counter'=>$counter,
	            'order_nav_html'=>$order_nav_html,
	            'warehouseids'=>$warehouseids,
	            'showsearch'=>$showsearch,
	            'tag_class_list'=> OrderTagHelper::getTagColorMapping(),
	            'doarr'=>OrderHelper::getCurrentOperationList($op_code,'b'),
	            'doarr_one'=>OrderHelper::getCurrentOperationList($op_code,'s'),
	        	'warehouseCount'=>$warehouseCount,
	        ));
        }else{#################################################################################################################################
        	$query_condition = $_REQUEST;
			$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
			$query_condition['delivery_status'] = OdOrder::DELIVERY_PICKING;//??????
			$query_condition['warehouse_and_shippingservice'] = '(default_warehouse_id > -1 and default_shipping_method_code <> "")';
			//????????????
			$counter = DeliveryHelper::getMenuStatisticData($query_condition['default_warehouse_id']);
			//??????????????????
			$order_nav_html=DeliveryHelper::getOrderNav($counter,$query_condition['default_warehouse_id']);
			
			$carrierQtips = CarrierOpenHelper::getCarrierQtips();
			$search = array(
// 					'is_comment_status'=>'???????????????',
					'no_print_carrier'=>'?????????????????????',
					'print_carrier'=>'?????????????????????',
					'no_print_distribution'=>'??????????????????',
					'print_distribution'=>'??????????????????',
			);
			return $this->renderAuto('listpicking',[
					'counter'=>$counter,
					'order_nav_html'=>$order_nav_html,
					'search'=>$search,
					'carrierQtips'=>$carrierQtips,]+CarrierprocessController::getlist($query_condition,'delivery/order')
			);
        	
        }

	}

    /**
    +----------------------------------------------------------
     * ?????????????????????
    +----------------------------------------------------------
     * @access public
    +----------------------------------------------------------
     * log			name	    date					note
     * @author		dwg 	2015/01/19				?????????
    +----------------------------------------------------------
     *
     */
    function actionPrintpicking(){
    	AppTrackerApiHelper::actionLog("eagle_v2", "delivery/order/Printpicking");
            $deliveryid = $_REQUEST['deliveryid'];
            $odDeliveryOrderArr = OdDeliveryOrder::find()->where(['delivery_id'=>$_REQUEST['deliveryid']])->orderBy('warehouse_address_id asc')->all();
            /** start ????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????*/
            if(empty($odDeliveryOrderArr)){
                die('No order!');
            }else{
                $delivery = OdDelivery::findOne(['deliveryid'=>$_REQUEST['deliveryid']]);
                if($delivery->picking_status < 1){ 
                    $delivery->picking_status= OdDelivery::PICKING_PRINT_ALREADY;
                }
                $delivery->print_picking_operator= \Yii::$app->user->identity->getUsername();
                $delivery->print_picking_time= time();
                $delivery->save(false);
            }
            $warehouse = Warehouse::findOne(['warehouse_id'=>$delivery->warehouseid]);
            $warehouseName = $warehouse->name;
            //??????????????????-?????????????????????
//             $configData = ConfigData::find()->where(['path'=>'print_picking_type'])->one();
//             $print_type = $configData->value;
            $data = array();
            
            $orderids = array();
            	//????????????sku
            	foreach ($odDeliveryOrderArr as $odDeliveryOrder){
            		if (isset($data[$odDeliveryOrder['sku']])){
            			$data[$odDeliveryOrder['sku']]['count']+=$odDeliveryOrder['count'];
            		}else{
            			$data[$odDeliveryOrder['sku']] = $odDeliveryOrder;
            		}
            		
            		if (!in_array($odDeliveryOrder['order_id'], $orderids)){
            			$orderids[] = $odDeliveryOrder['order_id'];
            		}
            		
            	}
            	       	
    		return $this->renderAjax('skutoprintpicking',array(
    				'deliveryid'=>$deliveryid,//????????????
    				'odDeliveryDataArr'=>$delivery,//odDelivery??????
    				'odDeliveryOrderDataArr'=>$data,//odDeliveryOrder??????
    				'warehouseName'=>$warehouseName,//??????????????????
    				'orderids'=>$orderids,
    		));

    }


    /**
    ????????????????????????????????????
     */
    function actionIsexistorder(){
        //odDelivery?????????
        $odDeliveryArr = OdDelivery::find()->where(['deliveryid'=>$_REQUEST['deliveryid']])->all();
        //odDeliveryOrder?????????
        $odDeliveryOrderArr = OdDeliveryOrder::find()->where(['delivery_id'=>$_REQUEST['deliveryid']])->all();
        if(empty($odDeliveryOrderArr)){
            return json_encode(array('message'=>'false'));
        }
        else{
            return json_encode(array('message'=>'true'));
        }

    }
    

    /**
    +----------------------------------------------------------
     * ?????????????????????
    +----------------------------------------------------------
     * @access public
    +----------------------------------------------------------
     * log			name	    date					note
     * @author		dwg 	2015/01/20				?????????
    +----------------------------------------------------------
     *
     */
    function actionEditpicking(){
    	AppTrackerApiHelper::actionLog("eagle_v2", "delivery/order/Editpicking");
        $deliveryid = isset($_REQUEST['deliveryid'])?trim($_REQUEST['deliveryid']):"";
        $order_id = isset($_REQUEST['order_id'])?trim($_REQUEST['order_id']):"";
        $sku = isset($_REQUEST['sku'])?trim($_REQUEST['sku']):"";
        $data = OdDeliveryOrder::find()->where(['delivery_id'=>$deliveryid]);
        if(!empty($order_id)) {
            $data->andWhere("order_id = :order_id", [':order_id' => $order_id]);
        }
        if(!empty($sku)) {
            $data->andWhere("sku = :sku", [':sku' => $sku]);
        }
        $OdDeliveryOrderArr = $data->all();
        if (count($OdDeliveryOrderArr) == 0 ){
        	die('No order!');
        }
        //??????OdDeliveryOrder???????????????????????????????????????????????????????????????
        $odDeliveryOrderDataArr = array();
        foreach($OdDeliveryOrderArr as $OdDeliveryOrder){
            $odDeliveryOrderDataArr[] = $OdDeliveryOrder->attributes;
        }
        $odDeliveryOrderDataFinal= array();
        foreach($odDeliveryOrderDataArr as $odDeliveryOrderData){
            $odDeliveryOrderDataFinal[$odDeliveryOrderData['order_id']][] = $odDeliveryOrderData;
        }

        //odDelivery?????????
        $odDeliveryArr = OdDelivery::find()->where(['deliveryid'=>$_REQUEST['deliveryid']])->all();

        //??????????????????????????????????????????????????????
        $counter = DeliveryHelper::getMenuStatisticData(0);
        $order_nav_html=DeliveryHelper::getOrderNav($counter,0);

        return $this->render('editpicking',array(
            'counter'=>$counter,//???????????????
            'order_nav_html'=>$order_nav_html,//???????????????
            'odDeliveryArr'=>$odDeliveryArr[0]->attributes, //odDelivery?????????
            'odDeliveryOrderDataFinal'=>$odDeliveryOrderDataFinal,
        ));


    }

    /**
    +----------------------------------------------------------
     * ???????????????
    +----------------------------------------------------------
     * @access public
    +----------------------------------------------------------
     * log			name	    date					note
     * @author		dwg 	  2015/01/20				?????????
    +----------------------------------------------------------
     *
     */
    function actionDeletepicking(){
    	AppTrackerApiHelper::actionLog("eagle_v2", "delivery/order/Deletepicking");
        $deliveryid = isset($_REQUEST['deliveryid'])?$_REQUEST['deliveryid']:"";
        if (empty($deliveryid)){
        	return json_encode(array('success'=>true,'message'=>'?????????????????????'));
        }
        //OdDeliveryOrder?????????
        $odDeliveryOrderArr = OdDeliveryOrder::find()->where('delivery_id = :a',[':a'=>$deliveryid])->groupBy(['order_id'])->asArray()->all();
        if (count($odDeliveryOrderArr)){
	        foreach($odDeliveryOrderArr as $odDeliveryOrder){
	            $odDeliveryOrderIdList[] = $odDeliveryOrder['order_id'];
	        }
        }
        //????????????????????????order_id??????order_v2???"?????????"??????
        if(!empty($odDeliveryOrderIdList)) {
            OdOrder::updateAll(['is_print_picking' =>0,'delivery_id'=>0],['in','order_id',$odDeliveryOrderIdList]);
        }
        //?????????????????????
        if(!empty($deliveryid)) {
            OdDeliveryOrder::deleteAll(['delivery_id' => $deliveryid]);
            OdDelivery::deleteAll(['deliveryid' => $deliveryid]);
        }
        $a = OdDelivery::find()->where('deliveryid = :a',[':a'=>$deliveryid])->count();
        $b = OdDeliveryOrder::find()->where('delivery_id = :a',[':a'=>$deliveryid])->count();
        if($a==0 && $b==0){
          return json_encode(array('success'=>true,'message'=>'???????????????'));
        }else {
        	return json_encode(array('success'=>false,'message'=>'???????????????'));
        }

    }

    /**
    +----------------------------------------------------------
     * ?????????????????????
    +----------------------------------------------------------
     * @access public
    +----------------------------------------------------------
     * log			name	    date					note
     * @author		dwg 	  2015/01/21				?????????
    +----------------------------------------------------------
     *
     */
    function actionFinishpicking(){
    	AppTrackerApiHelper::actionLog("eagle_v2", "delivery/order/Finishpicking");
        $deliveryid = isset($_REQUEST['deliveryid'])?$_REQUEST['deliveryid']:"";
        $picking_operator = isset($_REQUEST['picking_operator'])?$_REQUEST['picking_operator']:"";
        $warehouse_id = isset($_REQUEST['warehouse_id'])?$_REQUEST['warehouse_id']:"";
        //??????????????????+?????????????????????
        if(!empty($picking_operator)){
            $data = OdDelivery::findOne(['deliveryid'=>$deliveryid]);
            $data->picking_operator = $picking_operator;
            $data->picking_status = OdDelivery::PICKING_ALREADY;
            $data->save(false);

            $order_id_arr = OdDeliveryOrder::find()->select('order_id')->where(['delivery_id'=>$_REQUEST['deliveryid']])->asArray()->all();
            $res =  OdOrder::updateAll(['delivery_status'=>OdOrder::DELIVERY_DISTRIBUTION],['in','order_id',$order_id_arr]);
            //return $this->renderAjax('finishpickingconfirm',array( 'order_id_arr'=>$order_id_arr,));
            $this->redirect(['/delivery/order/listdistribution','warehouse_id'=>$warehouse_id,'delivery_status'=>3] );
        }
        
        return $this->renderAjax('finishpicking',array(
              'deliveryid'=>$deliveryid,
        	  'warehouse_id'=>$warehouse_id,
        ));
    }
    /**
     +----------------------------------------------------------
     * ??????????????????
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * log			name	    date					note
     * @author		million 	2016/05/28				?????????
     +----------------------------------------------------------
     *
     */
	function actionCompletepicking(){
		AppTrackerApiHelper::actionLog("eagle_v2", "delivery/order/Completepicking");
		try {
			$orderids = $_POST['order_id'];
			$warehouse_id = $_POST['warehouse_id'];
			$orderids = array_filter($orderids);
			$orders=OdOrder::findAll(['order_id'=>$orderids]);
			foreach ($orders as $order){
				$oldStatus = $order->delivery_status;
				$order->delivery_status = OdOrder::DELIVERY_DISTRIBUTION;
				$order->save();
				OperationLogHelper::log('delivery',$order->order_id,'????????????','????????????:'.OdOrder::$deliveryStatus[$oldStatus].'->'.OdOrder::$deliveryStatus[$order->delivery_status],\Yii::$app->user->identity->getFullName());
			}
			$this->redirect(['/delivery/order/listdistribution','warehouse_id'=>$warehouse_id,'delivery_status'=>3] );
		}catch (Exception $ex){
			print_r($ex->getMessage());die;
		}
	}




	/**
	 +----------------------------------------------------------
	 * ??????????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		million 	2015/01/05				?????????
	 +----------------------------------------------------------
	 **/
	function actionListdistribution(){
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/Listdistribution");
		if(!isset($_REQUEST['is_print_distribution'])){
			$_REQUEST['is_print_distribution'] = 0;
		}
		$query_condition = $_REQUEST;
		$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
		$query_condition['delivery_status'] = $_REQUEST['delivery_status'] = OdOrder::DELIVERY_DISTRIBUTION;
		$query_condition['warehouse_and_shippingservice'] = '(default_warehouse_id > -1 and default_shipping_method_code <> "")';
		$query_condition['is_print_distribution'] = $_REQUEST['is_print_distribution'] = isset($_REQUEST['is_print_distribution'])?$_REQUEST['is_print_distribution']:0;//??????
		if(isset($_REQUEST['warehouse_id']) && trim($_REQUEST['warehouse_id']) != ''){
			$query_condition['default_warehouse_id'] = $_REQUEST['default_warehouse_id'] = $_REQUEST['warehouse_id'];
		}else{
			$query_condition['default_warehouse_id'] = $_REQUEST['default_warehouse_id'] = 0;//?????????
		}
		//????????????
		$counter = DeliveryHelper::getMenuStatisticData($query_condition['default_warehouse_id']);
		//??????????????????
		$order_nav_html=DeliveryHelper::getOrderNav($counter,$query_condition['default_warehouse_id']);
			
		$carrierQtips = CarrierOpenHelper::getCarrierQtips();
		$search = array(
// 				'is_comment_status'=>'???????????????',
				'no_print_carrier'=>'?????????????????????',
				'print_carrier'=>'?????????????????????',
				'no_print_distribution'=>'??????????????????',
				'print_distribution'=>'??????????????????',
		);
		return $this->render('listdistribution',[
				'counter'=>$counter,
				'order_nav_html'=>$order_nav_html,
				'search'=>$search,
				'carrierQtips'=>$carrierQtips,]+CarrierprocessController::getlist($query_condition,'delivery/order')
		);
	}
	/**
	 +----------------------------------------------------------
	 * ????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		million 	2016/05/29				?????????
	 +----------------------------------------------------------
	 **/
	public function actionMoveOrderToOut(){
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/MoveOrderToOut");
		try{
			$orderids = $_POST['order_id'];
			$warehouse_id = $_POST['warehouse_id'];
			$orderids = array_filter($orderids);
			$orders=OdOrder::findAll(['order_id'=>$orderids]);
			foreach ($orders as $order){
				$oldStatus = $order->delivery_status;
				$order->delivery_status = OdOrder::DELIVERY_OUTWAREHOUSE;
				if(!$order->save()){
					die($order->order_id.'error');
				}
				OperationLogHelper::log('delivery',$order->order_id,'????????????','????????????:'.OdOrder::$deliveryStatus[$oldStatus].'->'.OdOrder::$deliveryStatus[$order->delivery_status],\Yii::$app->user->identity->getFullName());
			}
			$this->redirect(['/delivery/order/listoutwarehouse','warehouse_id'=>$warehouse_id,'delivery_status'=>6] );
		}catch (Exception $ex){
			print_r($ex->getMessage());die;
		}
	}
	/**
	 +----------------------------------------------------------
	 * ????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		million 	2015/01/05				?????????
	 +----------------------------------------------------------
	 **/
	function actionListoutwarehouse(){
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/Listoutwarehouse");
		$query_condition = $_REQUEST;
		$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
		$query_condition['delivery_status'] = $_REQUEST['delivery_status'] = OdOrder::DELIVERY_OUTWAREHOUSE;
		$query_condition['warehouse_and_shippingservice'] = '(default_warehouse_id > -1 and default_shipping_method_code <> "")';
		if(isset($_REQUEST['warehouse_id']) && trim($_REQUEST['warehouse_id']) != ''){
			$query_condition['default_warehouse_id'] = $_REQUEST['default_warehouse_id'] = $_REQUEST['warehouse_id'];
		}else{
			$query_condition['default_warehouse_id'] = $_REQUEST['default_warehouse_id'] = 0;//?????????
		}
		//????????????
		$counter = DeliveryHelper::getMenuStatisticData($query_condition['default_warehouse_id']);
		//??????????????????
		$order_nav_html=DeliveryHelper::getOrderNav($counter,$query_condition['default_warehouse_id']);
			
		$carrierQtips = CarrierOpenHelper::getCarrierQtips();
		$search = array(
// 				'is_comment_status'=>'???????????????',
				'no_print_carrier'=>'?????????????????????',
				'print_carrier'=>'?????????????????????',
				'no_print_distribution'=>'??????????????????',
				'print_distribution'=>'??????????????????',
		);
		return $this->render('listoutwarehouse',[
				'counter'=>$counter,
				'order_nav_html'=>$order_nav_html,
				'search'=>$search,
				'carrierQtips'=>$carrierQtips,]+CarrierprocessController::getlist($query_condition,'delivery/order')
		);
	}


	/**
	 +----------------------------------------------------------
	 * ??????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		million 	2015/01/05				?????????
	 +----------------------------------------------------------
	 **/
	function actionListoutofstock(){
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/Listoutofstock");
		//??????????????????????????????????????????????????????
		$counter = DeliveryHelper::getMenuStatisticData($_REQUEST['warehouse_id']);
		$order_nav_html=DeliveryHelper::getOrderNav($counter,$_REQUEST['warehouse_id']);
		
		$warehouseCount = count(InventoryApiHelper::getWarehouseIdNameMap(true));
		
		return $this->render('listoutofstock',array('counter'=>$counter,'order_nav_html'=>$order_nav_html,'warehouseCount'=>$warehouseCount));
	}
	
	//??????????????????????????????
	public function actionEditCustomsInfo(){
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/EditCustomsInfo");
		if (\Yii::$app->request->isPost){
	
			$infos = array(
					'name' => '',
					'prod_name_en' => '',
			);
	
			if(!empty($_POST['customsName'])){
				$infos['declaration_ch'] = $_POST['customsName'];
			}
	
			if(!empty($_POST['customsEName'])){
				$infos['declaration_en'] = $_POST['customsEName'];
			}
	
			if(!empty($_POST['customsDeclaredValue'])){
				$infos['declaration_value'] = $_POST['customsDeclaredValue'];
			}
	
			if(!empty($_POST['customsweight'])){
				$infos['prod_weight'] = $_POST['customsweight'];
			}
	
			$orderids = explode(',',$_POST['orders']);
			Helper_Array::removeEmpty($orderids);
			if (count($orderids)>0){
				try {
					foreach ($orderids as $orderid){
						$orderItems = OdOrderItem::find()->select('product_name,sku')->where('order_id=:order_id',[':order_id'=>$orderid])->asArray()->all();
							
						foreach ($orderItems as $orderItem){
							$infos['name'] = $orderItem['product_name'];
							$infos['prod_name_en'] = $orderItem['product_name'];
	
							$result = ProductApiHelper::modifyProductInfo($orderItem['sku'], $infos);
						}
					}
					return '??????????????????????????????';
				}catch (\Exception $e){
					return $e->getMessage();
				}
			}else{
				return '?????????????????????????????????????????????';
			}
		}else{
			return $this->renderAjax('editCustomsInfo');
		}
	}
	/*
	 *  ???????????????????????????????????????????????????
	*  ????????????????????????????????????
	*  ???????????????????????????????????????????????? ???????????????
	*/
	public function actionGetData(){
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/GetData");
		try{
			if(!\Yii::$app->request->getIsAjax()) {
				return false;
			}
			$timeLogArr = array();
			
			$timeLogArr['t1'] = TimeUtil::getCurrentTimestampMS();
			//??????????????????????????????
			$delivery = (isset($_GET['delivery']) && !empty($_GET['delivery']))?$_GET['delivery']:0;
			$id = $_POST['id']; //?????????
			//???????????????
			$odOrder_obj = OdOrder::findOne($id);
			
			$tmp_order_is_cancel = \eagle\modules\delivery\helpers\DeliveryHelper::getOrderIsCancel($odOrder_obj);
			if($tmp_order_is_cancel == true)
				throw new Exception('??????????????????????????????');
			
			$timeLogArr['t2'] = TimeUtil::getCurrentTimestampMS();
			if($odOrder_obj->carrier_step != 0 && $odOrder_obj->carrier_step != 4){
// 				throw new Exception('????????????????????????????????????????????????');
				return json_encode(['error'=>1,'data'=>'','msg'=>'?????????????????????????????????????????????????????????????????????!']);
			}
			
// 			if($odOrder_obj->order_source == 'cdiscount')
			$odOrder_obj->setItems(OdOrderItem::find()->where(['order_id'=>$odOrder_obj->order_id])->andwhere(['not in',"ifnull(sku,'')",\eagle\modules\order\helpers\CdiscountOrderInterface::getNonDeliverySku()])->andwhere(['and',"ifnull(delivery_status,'') != 'ban'"])->all());
			//$odOrder_obj->setItems(OdOrderItem::find()->where(['order_id'=>$odOrder_obj->order_id])->andwhere(['not in',"ifnull(sku,'')",\eagle\modules\order\helpers\CdiscountOrderInterface::getNonDeliverySku()])->all());

			//???????????????????????????
			$shippingService_obj = SysShippingService::find()->where(['id'=>$odOrder_obj->default_shipping_method_code,'is_used'=>1,'is_del'=>0])->one();
			$timeLogArr['t3'] = TimeUtil::getCurrentTimestampMS();
			if ($shippingService_obj == null) {
				throw new Exception('?????????????????????????????????(???????????????????????????????????????????????????????????????)');
			}
			
			//???????????????????????????
			if ($shippingService_obj->is_custom == 1) {
				throw new Exception('??????????????????????????????????????????????????????API????????????!');
			}
			
			//???????????????
			$carrier = SysCarrier::findOne($odOrder_obj->default_carrier_code);
			$timeLogArr['t4'] = TimeUtil::getCurrentTimestampMS();
			if ($carrier===null) {
				throw new Exception('???????????????????????????');
			}
			$class_name = '';
			//????????????????????????????????????type=1
			if($carrier->carrier_type){
				$class_name = '\common\api\overseaWarehouseAPI\\'.$carrier->api_class;
			}else{
				$class_name = '\common\api\carrierAPI\\'.$carrier->api_class;
			}
			//??????????????????????????????
			if($carrier->api_class == 'LB_RTBCOMPANYCarrierAPI'){
				//???????????????????????????
				$interface = new $class_name($carrier->carrier_code);
			}
			else{
				$interface = new $class_name;
			}
			
			if($carrier->api_class == 'LB_WANYITONGOverseaWarehouseAPI'){
				$interface::$is_delivery = $delivery;
				$delivery = 0;
			}
			
			if($carrier->api_class == 'LB_CHUKOUYIOverseaWarehouseAPI'){
				$delivery = 0;
			}
			
			if($carrier->api_class == 'LB_CHUKOUYICarrierAPI'){
				$delivery = 0;
			}
	
			$result = $interface->getOrderNO(['order'=>$odOrder_obj,'data'=>$_POST]);
			$timeLogArr['t5'] = TimeUtil::getCurrentTimestampMS();
			
			OperationLogHelper::log('order',$odOrder_obj->order_id,'????????????','??????????????????:'.$shippingService_obj->shipping_method_name.
				';??????'.($result['error']==0 ? '??????' : '????????????:'.$result['msg']),\Yii::$app->user->identity->getFullName());
			$timeLogArr['t6'] = TimeUtil::getCurrentTimestampMS();
			
			//?????????????????? ?????????shipped???
			if($result['error']==0){
				//??????????????????
				$logisticInfoList = [$result['data']];
				$odOrder_obj->carrier_error = '';
				//weird_status??????
				if(!empty($odOrder_obj->weird_status))
					OperationLogHelper::log('order',$odOrder_obj->order_id,'????????????','?????????????????????????????????????????????',\Yii::$app->user->identity->getFullName());
				$odOrder_obj->weird_status = '';
				
				$odOrder_obj->declaration_info = json_encode($_POST);
	
				$odOrder_obj->save();
				$timeLogArr['t7'] = TimeUtil::getCurrentTimestampMS();
				
				//??????????????????
				OrderHelper::saveTrackingNumber($odOrder_obj->order_id, $logisticInfoList);
				$timeLogArr['t8'] = TimeUtil::getCurrentTimestampMS();
				
				//?????????????????????????????????????????????????????????????????????????????????????????????????????????
				if($delivery && $odOrder_obj->carrier_step == 1){
					$result = $interface->doDispatch(['order'=>$odOrder_obj,'data'=>$_POST]);
					$timeLogArr['t9'] = TimeUtil::getCurrentTimestampMS();
					//????????????????????? ????????????
					if($result['error']==1){
						$odOrder_obj->carrier_error = $result['msg'];
						$odOrder_obj->save();
					}else{
						$odOrder_obj->carrier_error = '';
						$odOrder_obj->save();
					}
					$timeLogArr['t10'] = TimeUtil::getCurrentTimestampMS();
				}
				
				//??????????????????
				UserHelper::insertUserOperationLog('delivery', "????????????, ?????????????????? ".$carrier->carrier_name." -> ".$shippingService_obj->shipping_method_name.", ?????????: ".ltrim($odOrder_obj->order_id, '0'), null, $odOrder_obj->order_id);
			}
			else if($result['error']==1){
				$result['msg'] .= ' t'.time().\Yii::$app->user->identity->getParentUid();
				
				$odOrder_obj->carrier_error = $result['msg'];
				$odOrder_obj->save();
				$timeLogArr['t11'] = TimeUtil::getCurrentTimestampMS();
			}
			
			// dzt20160709 ????????????log
			DeliveryApiHelper::formatFileLog("delivery-order-get-data:puid:".\Yii::$app->user->identity->getParentUid().",??????????????????:".$shippingService_obj->shipping_method_name.',Y_order_id:'.$odOrder_obj->order_id.":",$timeLogArr);
			return json_encode($result);
		}catch(\Exception $e){
		    $log_error = $e->getMessage().$e->getFile().' '.$e->getLine();
		    \Yii::info($log_error, "carrier_api");
		    
				$tmp_error = $e->getMessage();
			$tmp_error .= ' t'.time().\Yii::$app->user->identity->getParentUid();
			
			$odOrder_obj->carrier_error = $tmp_error;
			$odOrder_obj->save();
			
			// dzt20160709 ????????????log
			DeliveryApiHelper::formatFileLog("delivery-order-get-data:puid:".\Yii::$app->user->identity->getParentUid().",??????????????????:".(empty($shippingService_obj->shipping_method_name) ? '' : $shippingService_obj->shipping_method_name).',N_order_id:'.$odOrder_obj->order_id.":",$timeLogArr);
			return json_encode(['error'=>1,'data'=>'','msg'=>$tmp_error]);
		}
	}
	/*
	 * ????????????
	*/
	public function actionDodispatchajax()
	{
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/Dodispatchajax");
		if(!\Yii::$app->request->getIsAjax())return false;
		 
		$id = $_POST['id'];
		//??????
		$odOrder_obj = OdOrder::findOne($id);
		$class_name = '';
		//?????????
		$carrier = SysCarrier::findOne($odOrder_obj->default_carrier_code);
		if($carrier->carrier_type){
			$class_name = '\common\api\overseaWarehouseAPI\\'.$carrier->api_class;
		}else{
			$class_name = '\common\api\carrierAPI\\'.$carrier->api_class;
		}
		 
		if($carrier->api_class == 'LB_RTBCOMPANYCarrierAPI'){
			//???????????????????????????
			$interface = new $class_name($carrier->carrier_code);
		}
		else{
			$interface = new $class_name;
		}
		 
		$result = $interface->doDispatch(['order'=>$odOrder_obj,'data'=>$_POST]);
		//????????????????????? ????????????
		if($result['error']==1){
			$odOrder_obj->carrier_error = $result['msg'];
			$odOrder_obj->save();
		}else{
			$odOrder_obj->carrier_error = '';
			$odOrder_obj->save();
			
			//??????????????????
			UserHelper::insertUserOperationLog('delivery', "????????????, ?????????: ".ltrim($odOrder_obj->order_id, '0'), null, $odOrder_obj->order_id);
		}
		 
		return json_encode($result);
	}
	//????????????
	public function actionAjaxMoveOrderToUpload(){
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/AjaxMoveOrderToUpload");
		if(!\Yii::$app->request->getIsAjax())return false;
		try{
			$id = $_POST['id'];
				
			$odOrder_obj = OdOrder::findOne($id);
			$old1 = $odOrder_obj->order_status;
			$old2 = $odOrder_obj->delivery_status;
			$old3 = $odOrder_obj->carrier_step;
			$odOrder_obj->order_status = OdOrder::STATUS_WAITSEND;//?????????
			$odOrder_obj->delivery_status = OdOrder::DELIVERY_PLANCEANORDER;//???????????????
			$odOrder_obj->carrier_step = OdOrder::CARRIER_CANCELED;//????????????
			
			$odOrder_obj->is_print_carrier = 0;//??????????????????
			
			$odOrder_obj->is_print_carrier = 0;//??????????????????
			$odOrder_obj->tracking_number = '';
				
			$odOrder_obj->save();
			OperationLogHelper::log('delivery',$odOrder_obj->order_id,'????????????','????????????:'.OdOrder::$status[$old1].'->'.OdOrder::$status[$odOrder_obj->order_status].' ????????????:'.OdOrder::$deliveryStatus[$old2].'->'.OdOrder::$deliveryStatus[$odOrder_obj->delivery_status].' ??????????????????:'.OdOrder::$carrier_step[$old3].'->'.OdOrder::$carrier_step[$odOrder_obj->carrier_step],\Yii::$app->user->identity->getFullName());
			
			//??????????????????
			UserHelper::insertUserOperationLog('delivery', "????????????, ?????????: ".ltrim($id, '0'), null, $id);
			return true;
		}catch (Exception $ex){
			return false;
		}
	
	}
	//????????????
	public function actionAjaxMoveOrderToUpload2(){
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/AjaxMoveOrderToUpload2");
		if(!\Yii::$app->request->getIsAjax())return false;
		try{
			$id = $_POST['id'];
	
			$odOrder_obj = OdOrder::findOne($id);
			$old1 = $odOrder_obj->order_status;
			$old2 = $odOrder_obj->delivery_status;
			$old3 = $odOrder_obj->carrier_step;
			$odOrder_obj->order_status = OdOrder::STATUS_WAITSEND;//?????????
			$odOrder_obj->delivery_status = OdOrder::DELIVERY_PLANCEANORDER;//???????????????
			$odOrder_obj->carrier_step = OdOrder::CARRIER_CANCELED;//????????????
	
			$odOrder_obj->save();
			OperationLogHelper::log('delivery',$odOrder_obj->order_id,'????????????','????????????:'.OdOrder::$status[$old1].'->'.OdOrder::$status[$odOrder_obj->order_status].' ????????????:'.OdOrder::$deliveryStatus[$old2].'->'.OdOrder::$deliveryStatus[$odOrder_obj->delivery_status].' ??????????????????:'.OdOrder::$carrier_step[$old3].'->'.OdOrder::$carrier_step[$odOrder_obj->carrier_step],\Yii::$app->user->identity->getFullName());
			
			//??????????????????
			UserHelper::insertUserOperationLog('delivery', "????????????, ?????????: ".ltrim($id, '0'), null, $id);
			return true;
		}catch (Exception $ex){
			return false;
		}
	
	}
	//????????????
	public function actionAjaxMoveOrderToUpload3(){
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/AjaxMoveOrderToUpload3");
		if(!\Yii::$app->request->getIsAjax())return false;
		try{
			$id = $_POST['id'];
	
			$odOrder_obj = OdOrder::findOne($id);
			$old1 = $odOrder_obj->order_status;
			$old2 = $odOrder_obj->delivery_status;
			$old3 = $odOrder_obj->carrier_step;
			$odOrder_obj->order_status = OdOrder::STATUS_WAITSEND;//?????????
			$odOrder_obj->delivery_status = OdOrder::DELIVERY_PLANCEANORDER;//???????????????
			$odOrder_obj->carrier_step = OdOrder::CARRIER_CANCELED;//????????????
	
			$odOrder_obj->save();
			OperationLogHelper::log('delivery',$odOrder_obj->order_id,'????????????','????????????:'.OdOrder::$status[$old1].'->'.OdOrder::$status[$odOrder_obj->order_status].' ????????????:'.OdOrder::$deliveryStatus[$old2].'->'.OdOrder::$deliveryStatus[$odOrder_obj->delivery_status].' ??????????????????:'.OdOrder::$carrier_step[$old3].'->'.OdOrder::$carrier_step[$odOrder_obj->carrier_step],\Yii::$app->user->identity->getFullName());
			
			//??????????????????
			UserHelper::insertUserOperationLog('delivery', "????????????, ?????????: ".ltrim($id, '0'), null, $id);
			return true;
		}catch (Exception $ex){
			return false;
		}
	
	}
	/**
	 * ???????????????????????????????????????orderid??????????????????
	 * @return string   json_encode(['error'=>true,'data'=>'???????????????'])
	 */
	public function actionAjaxBulidDeliveryId(){
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/AjaxBulidDeliveryId");
		if(!\Yii::$app->request->getIsAjax())return json_encode(['error'=>true,'data'=>'']);
		try{
			$orderids = $_POST['orderids'];
			$orderids = array_filter($orderids);
			$warehouseid = $_POST['warehouseid'];
			//???????????????
			$delivery=new OdDelivery();
			$delivertyid = $delivery->deliveryid=date('YmdHis',time());
			$delivery->creater=\Yii::$app->user->identity->getUsername();
			$delivery->create_picking_time = time();
            $delivery->picking_status = OdDelivery::PICKING_PRINT_NO;
            $delivery->warehouseid = $warehouseid;
			$delivery->save();
			
			//????????????,??????????????????
			$ordercount = 0;$skucount=0;$goodsCount = 0;//????????????
			$skuArray = [];
			$orders=OdOrder::findAll(['order_id'=>$orderids]);
			foreach ($orders as $order){
				if ($order->is_print_picking == 1){continue;}
				//?????????????????????????????????????????????????????????????????????
				$count = OdDeliveryOrder::find()->where(['order_id'=>$order->order_id])->count();
				if ($count > 0){
					$order->is_print_picking = 1;
					$order->save();
					continue;
				}
				//???????????????????????????
				$goods = OrderApiHelper::getProductsByOrder($order->order_id,$isSplitBundle=true,$isGetPickingInfo=true,$warehouseId=$order->default_warehouse_id);
				if (count($goods)){//od_order_goods?????????????????????
					foreach ($goods as $one){
						$attr = "";
						if (!empty($one['product_attributes'])){
							$tmpProdAttr = json_decode($one['product_attributes'],true);
							if (! is_array($tmpProdAttr)) {
								$tmp = explode('+',$one['product_attributes']);
								$tmpProdAttr = [];
								foreach($tmp as $_tmp){
									$_tmpRT = explode(':', $_tmp);
									$tmpProdAttr[$_tmpRT[0]] = $_tmpRT[1];
								}
							}
						
							foreach($tmpProdAttr as $_tmpAttrKey =>$_tmpAttrVal){
								$attr.= $_tmpAttrKey." : <b>".$_tmpAttrVal."</b><br>";
							}
						}
						$d_o = new OdDeliveryOrder();
						$d_o->delivery_id = $delivery->deliveryid;
						$d_o->order_id=$order->order_id;
						$d_o->sku = $one['sku'];
						$d_o->count =$one['qty'];
						$d_o->good_property = $attr;
						$d_o->good_name = $one['product_name'];
						$d_o->image_adress = $one['photo_primary'];
						$d_o->location_grid =$one['location_grid'];
						$d_o->save();
						
						//??????sku ?????????
						if (empty($skuArray[$d_o->sku])) $skuArray[$d_o->sku] = 0;
						$skuArray[$d_o->sku]+=$one['qty'];
						$goodsCount +=$one['qty'];
					}
					$order->delivery_id = $delivery->deliveryid;
					$order->is_print_picking = 1;
					$order->save();
					OperationLogHelper::log('delivery',$order->order_id,'???????????????','????????????:'.$order->delivery_id,\Yii::$app->user->identity->getFullName());
					$ordercount++;
				}
			}
			//????????????????????????????????????????????????
			if ($ordercount == 0){
				OdDeliveryOrder::deleteAll(['delivery_id' => $delivery->deliveryid]);
				OdDelivery::deleteAll(['deliveryid' => $delivery->deliveryid]);
				return json_encode(['success'=>false,'code'=>"100001",'message'=>'???????????????????????????????????????????????????','data'=>array('code'=>'100001')]);
			}	
			$skucount = count($skuArray);
			//?????????????????????
			$delivery->ordercount=$ordercount;
			$delivery->skucount=$skucount;
			$delivery->goodscount=$goodsCount;
			$delivery->warehouseid=$_POST['warehouseid'];
			$delivery->save();
			return json_encode(['success'=>true,'code'=>"100000",'message'=>'???????????????????????????????????????'.$delivertyid,'data'=>array('code'=>'99999','deliveryid'=>$delivertyid)]);
		}catch (Exception $ex){
			//print_r($ex->getMessage());
			return json_encode(['success'=>false,'code'=>"99999",'message'=>'???????????????????????????????????????????????????'.$ex->getMessage(),'data'=>array('code'=>'99999')]);
		}
	}
	/**
	 * ???????????????orderid???????????????excelCarrierCode??????excel???????????????
	 * @return boolean ?????????????????????false
	 */
	public function actionExportExcelFile(){
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/ExportExcelFile");
// 		print_r($_REQUEST);
		$orderids = explode(',',$_REQUEST['orderid']);
		$orderids = array_filter($orderids);
		if (count($orderids)==0)
			echo '????????????????????????';
		$excelFormatRes = CarrierOpenHelper::getCustomCarrierExcelFormat($_REQUEST['excelCarrierCode']);
		
		if($excelFormatRes['response']['code'])
			echo '?????????excel????????????';
		else{
			$excelMode = $excelFormatRes['response']['data']['excel_mode'];
			$excelFormat = $excelFormatRes['response']['data']['excel_format'];
		}
// 		echo $excelMode;
// 		print_r($excelFormat);return;
		//?????????????????????????????????????????????
		if(!empty($excelMode) && !empty($excelFormat)){
			$orders=OdOrder::findAll(['order_id'=>$orderids]);
			
			foreach ($orders as $order_obj){
				$custom = SysCarrierCustom::find()->where(['carrier_code'=>$order_obj->default_carrier_code])->one();
				if(empty($custom) || $custom->carrier_type != 1){
					echo '?????????'.$order_obj->order_id.'??????excel???????????????';
				}
				$order_obj->carrier_step = OdOrder::CARRIER_WAITING_DELIVERY;
				$order_obj->save();
			}
			$res = [];
			$titleCol = [];$line = 0;
			//???????????????????????????
			switch ($excelMode){
				//?????????????????????
				case 'orderToOneLine':
					foreach ($orders as $order_obj){
						$skuInfoList = [];
						$line++;//??????????????????
						$skuNums = -1;
						if (count($order_obj->items>1)){//????????????skuInfo
							foreach ($order_obj->items as $item){
								$skuInfos = ProductApiHelper::getSkuInfo($item->sku, $item->quantity);
								foreach ($skuInfos as $s){
									$skuInfoList[] = $s;
								}
							}
						}
						foreach ($excelFormat as $k=>$format){
							$titleCol[$k] = $format['title_column'];
							switch ($format['data_type']){
								case 'sys_data':
									$column = $format['data_value'];
									$tmpVal = '';
									$key = ($skuNums>=0)?$skuNums:0;
									if ($column == 'name_cn'){
										if(isset($skuInfoList[$key]['sku'])){
											$product = ProductApiHelper::getProductInfo($skuInfoList[$key]['sku']);
											$tmpVal = isset($product['prod_name_ch'])?$product['prod_name_ch']:'';
										}
									}else if($column == 'root_sku'){
										$skuNums++;
										$tmpVal = isset($skuInfoList[$skuNums]['sku'])?$skuInfoList[$skuNums]['sku']:'';
									}else if($column == 'quantity'){
										$tmpVal = isset($skuInfoList[$key]['qty'])?$skuInfoList[$key]['qty']:'';
									}else if ($column == 'tracknum'){
										$shipped = OdOrderShipped::find()->where(['order_id'=>$order_id ])->andwhere( " ifnull(tracking_number, '') <> '' " )->one();
										$tmpVal = empty($shipped)?'':$shipped->tracking_number;
									}else{
										$tmpVal= isset($order_obj[$column])?$order_obj[$column]:(isset($skuInfoList[$key][$column])?$skuInfoList[$key][$column]:'');
									}
									$res[$line][$k+1] = $tmpVal;
									break;
								case 'fixed_value':$res[$line][$k+1] = $format['data_value'];break;
								case 'keep_empty':$res[$line][$k+1] = '';break;
							}
						}
					}
					break;
				//??????sku?????????
				case 'orderToSku':
					foreach ($orders as $order_obj){
						if (count($order_obj->items>1)){//????????????skuInfo
							foreach ($order_obj->items as $item){
								$skuInfos = ProductApiHelper::getSkuInfo($item->sku, $item->quantity);
								foreach ($skuInfos as $s){
									$line++;//??????sku??????
									foreach ($excelFormat as $k=>$format){
										$titleCol[$k] = $format['title_column'];
										switch ($format['data_type']){
											case 'sys_data':
												$column = $format['data_value'];
												$tmpVal = '';
												if ($column == 'name_cn'){
													if(isset($s['sku'])){
														$product = ProductApiHelper::getProductInfo($s['sku']);
														$tmpVal = isset($product['prod_name_ch'])?$product['prod_name_ch']:'';
													}
												}else if($column == 'root_sku'){
													$tmpVal = isset($s['sku'])?$s['sku']:'';
												}else if($column == 'quantity'){
													$tmpVal = isset($s['qty'])?$s['qty']:'';
												}else if ($column == 'tracknum'){
													$shipped = OdOrderShipped::find()->where(['order_id'=>$order_id ])->andwhere( " ifnull(tracking_number, '') <> '' " )->one();
													$tmpVal = empty($shipped)?'':$shipped->tracking_number;
												}else{
													$tmpVal= isset($order_obj[$column])?$order_obj[$column]:(isset($s[$column])?$s[$column]:'');
												}
												$res[$line][$k+1] = $tmpVal;
												break;
											case 'fixed_value':$res[$line][$k+1] = $format['data_value'];break;
											case 'keep_empty':$res[$line][$k+1] = '';break;
										}
									}
								}
							}
						}
					}
					break;
				//??????item??????
				case 'orderToLine':
					foreach ($orders as $order_obj){
						if (count($order_obj->items>1)){
							foreach ($order_obj->items as $item){
								$skuInfoList = [];
								$line++;//??????????????????
								$skuNums = -1;
								//????????????skuInfo
								$skuInfoList = ProductApiHelper::getSkuInfo($item->sku, $item->quantity);
								foreach ($excelFormat as $k=>$format){
									$titleCol[$k] = $format['title_column'];
									switch ($format['data_type']){
										case 'sys_data':
											$column = $format['data_value'];
											$tmpVal = '';
											$key = ($skuNums>=0)?$skuNums:0;
											if ($column == 'name_cn'){
												if(isset($skuInfoList[$key]['sku'])){
													$product = ProductApiHelper::getProductInfo($skuInfoList[$key]['sku']);
													$tmpVal = isset($product['prod_name_ch'])?$product['prod_name_ch']:'';
												}
											}else if($column == 'root_sku'){
												$skuNums++;
												$tmpVal = isset($skuInfoList[$skuNums]['sku'])?$skuInfoList[$skuNums]['sku']:'';
											}else if($column == 'quantity'){
												$tmpVal = isset($skuInfoList[$key]['qty'])?$skuInfoList[$key]['qty']:'';
											}else if ($column == 'tracknum'){
												$shipped = OdOrderShipped::find()->where(['order_id'=>$order_id ])->andwhere( " ifnull(tracking_number, '') <> '' " )->one();
												$tmpVal = empty($shipped)?'':$shipped->tracking_number;
											}else{
												$tmpVal= isset($order_obj[$column])?$order_obj[$column]:(isset($skuInfoList[$key][$column])?$skuInfoList[$key][$column]:'');
											}
											$res[$line][$k+1] = $tmpVal;
											break;
										case 'fixed_value':$res[$line][$k+1] = $format['data_value'];break;
										case 'keep_empty':$res[$line][$k+1] = '';break;
									}
								}
							}
						}
					}
					break;
			}
			
// 			print_r($res);print_r($titleCol);die;
			ExcelHelper::exportToExcel($res, $titleCol, 'order_'.date('Y-m-dHis',time()).".xls");
		}
	}
	/**
	 * ???????????????
	 * @return string
	 */
	public function actionSetTrackNoToOrder(){
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/SetTrackNoToOrder");
		if(!\Yii::$app->request->getIsAjax())return json_encode(['error'=>true,'data'=>'???????????????']);
		try{
			$order_id = $_POST['orderid'];
				$order=OdOrder::findOne(['order_id'=>$order_id]);
				$custom = SysCarrierCustom::find()->where(['carrier_code'=>$order->default_carrier_code])->one();
				if($custom == null || $custom->carrier_type != 0){
					return json_encode(array('success'=>false,'code'=>'100006','message'=>'??????'.$order->order_id.'???????????????????????????????????????????????????????????????????????????','data'=>array('order_id'=>$order->order_id)));
				}else if(empty($order)){
					return json_encode(array('success'=>false,'code'=>'100007','message'=>'??????'.$order->order_id.'???????????????','data'=>array('order_id'=>$order->order_id)));
				}else {
					$shipping_service_id = $order->default_shipping_method_code;
					$response = CarrierOpenHelper::getCustomUnUseTrackingnumber($shipping_service_id);
					$response = $response['response'];
					if($response['code']==1){//???????????????
						return json_encode(array('success'=>false,'code'=>'100008','message'=>$response['msg'],'data'=>array('order_id'=>$order->order_id)));
					}else{
						$tracking_number = $response['data'];
						$res = CarrierOpenHelper::saveCustomUnUseTrackingnumber($shipping_service_id, $tracking_number, $order->order_id);
						$res = $res['response'];
						if($res['code']==1){
							return json_encode(array('success'=>false,'code'=>'100009','message'=>'??????'.$order->order_id. $res['msg'],'data'=>array('order_id'=>$order->order_id)));
						}else{
							$service = SysShippingService::find()->where(['id'=>$shipping_service_id])->one();
							$service_codeArr = $service->service_code;
							$customerNumber = \eagle\modules\carrier\helpers\CarrierAPIHelper::getCustomerNum2($order);
							$logisticInfoList = array(
									0=>array(
											'order_source'=>$order->order_source,//????????????
											'selleruserid'=>$order->selleruserid,//????????????
											'tracking_number'=>$tracking_number,//?????????????????????
											'tracking_link'=>$service->web,//????????????????????????
											'shipping_method_code'=>isset($service_codeArr[$order->order_source])?$service_codeArr[$order->order_source]:'',//????????????????????????
											'shipping_method_name'=>'',//?????????????????????
											'order_source_order_id'=>$order->order_source_order_id,//???????????????
											'return_no'=>'',//????????????????????????????????????
											'customer_number'=>$customerNumber,//????????????????????????????????????
											'shipping_service_id'=>$shipping_service_id,//????????????id????????????
											'addtype'=>'???????????????',//???????????????
											'signtype'=>'all',//???????????? all??????part????????????
											'description'=>'',//??????????????????
									)
							);
							$result=OrderHelper::saveTrackingNumber($order_id, $logisticInfoList);
							if($result){
								$order->carrier_error = '';
								$order->carrier_step = OdOrder::CARRIER_WAITING_DELIVERY;
								$order->customer_number =$customerNumber;
								$order->save();
								OperationLogHelper::log('delivery', $order->order_id,'???????????????','????????????'.$tracking_number,\Yii::$app->user->identity->getFullName());
								return json_encode(array('success'=>true,'code'=>'100000','message'=>'??????'.$order->order_id. '????????????????????????????????????'.$tracking_number,'data'=>array('order_id'=>$order->order_id)));
							}else{
								return json_encode(array('success'=>false,'code'=>'100010','message'=>'??????'.$order->order_id. $res['msg'],'data'=>array('order_id'=>$order->order_id)));
							}
						}
					}
				}
		}catch (Exception $ex){
			return json_encode(array('success'=>false,'code'=>'99999','message'=>$ex->getMessage(),'data'=>array()));
		}
	}
	/**
	 +----------------------------------------------------------
	 * ??????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		glp 	2015/01/14				?????????
	 +----------------------------------------------------------
	 **/
	function actionAjaxlistoutofstockshow(){
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/Ajaxlistoutofstockshow");
		$id=array();$ordercount=array();$sku_arr = array();$goodscounts = 0;
		if (Yii::$app->request->isPost){
			$dh= Yii::$app->request->post()['dh'];
					$order=OdOrder::find()->where('order_id=:order_id',[':order_id'=>$dh])->one();
					if(empty($order)){
						return json_encode(0);
					}
					else{
							
							$rtn = InventoryApiHelper::OrderProductReserveCancel($order->order_id,'delivery','????????????');
							
							if($rtn['success']){
								//??????????????????sku??????
								$deliveryid=OdDeliveryOrder::findOne(['order_id'=>$order->order_id])->delivery_id;
								OdDeliveryOrder::deleteAll(['order_id'=>$order->order_id]);
								$deliveryorders=OdDeliveryOrder::find()->where('delivery_id = :delivery_id',[':delivery_id'=>$deliveryid])->all();

								 foreach ($deliveryorders as $deliveryorder)
								 {
								 	$goodscounts+=$deliveryorder->count;
								 	$sku_arr[$deliveryorder->sku] =1; 
								 	$ordercount[$deliveryorder->order_id]=1;
								 }
		 
								$delivery=OdDelivery::find()->where('deliveryid = :deliveryid',[':deliveryid'=>$deliveryid])->one();
								if(!empty($delivery)){	
									$delivery->ordercount=count($ordercount);
									$delivery->skucount=count($sku_arr);
									$delivery->goodscount=$goodscounts;
									if($delivery->save()){
										$order->order_status = OdOrder::STATUS_OUTOFSTOCK;
										$order->delivery_time = time();
										$id[]= $order->order_id;
										if($order->save()){	
											OperationLogHelper::log('delivery', $order->order_id,'????????????','??????????????????',\Yii::$app->user->identity->getFullName());
										}
									}
								}
							}
							else{
								$id=[];
							}
						return json_encode($id);
					}			
		}
	}
	/**
	 * ???????????????
	 * orderId 
	 * ???????????????
	 * 	??????post??????get????????????????????????????????????????????????","?????? 
	 * 	?????? 00000006079,00000007888,00000007900,
	 * ???????????????
	 * 	????????????
	 * @return string
	 */
	public function actionPrintList(){
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/PrintList");
		$time = date('Y-m-d H:i:s',time());
		$orderIds = json_decode($_REQUEST['order_id'], true);
		$orderIds = array_filter($orderIds);
		$carriers = ApiHelper::getCarriers();
// 		$orderLists = $data=OdOrder::find()->where(['order_id'=>$orderIds])->all();
		$orderLists = \eagle\modules\delivery\helpers\DeliveryHelper::getDeliveryOrder($orderIds, true);
		$lists = [];
		foreach ($orderLists as $orderList){
			if ($orderList->is_print_distribution != 1){
				$orderList->is_print_distribution = 1;
				if(!$orderList->save()){
					die('error');
				}
				OperationLogHelper::log('delivery',$orderList->order_id,'???????????????','??????????????????',\Yii::$app->user->identity->getFullName());
			}
			
			$lists[$orderList['order_id']] = OrderApiHelper::getProductsByOrder($orderList['order_id'],$isSplitBundle=true,$isGetPickingInfo=true,$warehouseId=$orderList['default_warehouse_id'],true,true,true);
		}
		$services = CarrierApiHelper::getShippingServices();
		return $this->renderPartial('PrintList',[
					'lists'=>$lists,
					'orderLists'=>$orderLists,
					'time'=>$time,
					'carriers'=>$carriers,
					'services'=>$services,
				]);
	}
	
	public function actionAjaxSetOrderIsPrintDistribution(){
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/AjaxSetOrderIsPrintDistribution");
		if(!\Yii::$app->request->getIsAjax())return false;
		try{
			$msg = [];
			$ids = $_POST['orderid'];
			$ids = explode(',', $ids);
			$ids = array_filter($ids);
			foreach ($ids as $id){
				$odOrder_obj = OdOrder::findOne($id);
	
				$odOrder_obj->is_print_distribution = 1;
	
				if(!$odOrder_obj->save()){
					return false;
				}
				OperationLogHelper::log('delivery',$odOrder_obj->order_id,'???????????????','??????????????????',\Yii::$app->user->identity->getFullName());
			}
			return true;
		}catch (Exception $ex){
			return false;
		}
	}
	
	/**
	 * ???????????????--???????????????
	 * @return Ambigous <string, string>
	 */
	public function actionOverseaslistplanceanorder(){
		AppTrackerApiHelper::actionLog("eagle_v2", "delivery/order/Overseaslistplanceanorder");
		$query_condition = $_REQUEST;
		$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
		$query_condition['warehouse_and_shippingservice'] = '(default_warehouse_id > -1 and default_shipping_method_code <> "")';
		//??????
		if(isset($query_condition['warehouse_id']) && trim($query_condition['warehouse_id']) != ''){
			$query_condition['default_warehouse_id'] = $_REQUEST['default_warehouse_id'] = $query_condition['warehouse_id'];
		}else{
			$query_condition['default_warehouse_id'] = $_REQUEST['default_warehouse_id'] = 0;//?????????
		}
		//????????????
		$query_condition['delivery_status'] = [0,1];//???????????????0????????????????????????????????????
		
		//??????????????????
		if(!isset($_REQUEST['carrier_step']) || trim($_REQUEST['carrier_step'])==''){
			$query_condition['carrier_step']=$_REQUEST['carrier_step'] = 'UPLOAD';
		}
		switch ($query_condition['carrier_step']){
			case 'UPLOAD':
				$query_condition['carrier_step'] = [0,4];
				break;
			case 'DELIVERY':
				$query_condition['carrier_step'] = 1;
				break;
			case 'DELIVERYED':
				$query_condition['carrier_step'] = [2,3,5];
				break;
			case 'FINISHED':
				$query_condition['carrier_step'] = 6;
				break;
		}
		if ($_REQUEST['carrier_step'] == 'FINISHED'){
			if(empty($_REQUEST['order_abnormal'])){
				$query_condition['order_status']=OdOrder::STATUS_SHIPPED;
				unset($query_condition['carrier_step']);
			}else{
				$query_condition['order_status']=OdOrder::STATUS_WAITSEND;
				$query_condition['carrier_step'] = 6;
			}
			
			unset($query_condition['delivery_status']);
		}
		
		
		//????????????
		$counter = DeliveryHelper::getMenuStatisticData($query_condition['default_warehouse_id']);
		//??????????????????
		$order_nav_html=DeliveryHelper::getOrderNav($counter,$query_condition['default_warehouse_id']);
		$OverseaType = InventoryHelper::getWarehouseOverseaType($query_condition['default_warehouse_id']);
		
		//???????????????????????????????????????
		$thirdSelfCarrierCode = 0;
		if($OverseaType == 1){
			$thirdSelfCarrierCode = CarrierOpenHelper::getCustomCarrierCodeByWarehouseId($query_condition['default_warehouse_id'], '');
				
			if($thirdSelfCarrierCode['response']['code'] == 0){
				$thirdSelfCarrierCode = $thirdSelfCarrierCode['response']['data'];
			}
		}
		
		$carrierQtips = CarrierOpenHelper::getCarrierQtips();
		$search = array(
				'is_comment_status'=>'???????????????',
				'no_print_carrier'=>'?????????????????????',
				'print_carrier'=>'?????????????????????',
		);
		return $this->render('oversealistplanceanorder',
				[
						'counter'=>$counter,
						'order_nav_html'=>$order_nav_html,
						'search'=>$search,
						'OverseaType'=>$OverseaType,
						'carrierQtips'=>$carrierQtips,
						'thirdSelfCarrierCode'=>$thirdSelfCarrierCode,
						]+CarrierprocessController::getlist($query_condition,'delivery/order'));
		
	}
	
	/**
	 * ???????????????
	 */
	function actionFinishdeliveredlist(){
		AppTrackerApiHelper::actionLog("eagle_v2", "delivery/order/Finishdeliveredlist");
		$query_condition = $_REQUEST;
		$query_condition['warehouse_and_shippingservice'] = '(default_shipping_method_code <> "")';
		
		//??????
		if(isset($query_condition['warehouse_id']) && trim($query_condition['warehouse_id']) != ''){
			$query_condition['default_warehouse_id'] = $_REQUEST['default_warehouse_id'] = $query_condition['warehouse_id'];
		}
		/*else{
			$query_condition['default_warehouse_id'] = $_REQUEST['default_warehouse_id'] = 0;//?????????
		}*/
	
		$query_condition['order_status']=OdOrder::STATUS_SHIPPED;
	
		//????????????
		$counter = DeliveryHelper::getMenuStatisticData();
		
		//??????????????????
		$order_nav_html=DeliveryHelper::getOrderNav($counter);
	
		$carrierQtips = CarrierOpenHelper::getCarrierQtips();
		$search = array(
// 				'is_comment_status'=>'???????????????',
				'no_print_carrier'=>'?????????????????????',
				'print_carrier'=>'?????????????????????',
		);
	
		$tmpLists = CarrierprocessController::getlist($query_condition,'delivery/order');

		$tmp_REQUEST_text['REQUEST']=$query_condition;
		$tmp_REQUEST_text['order_source']=['order_source'=>'delivery'];
		$tmp_REQUEST_text['where']=Array();
		$tmp_REQUEST_text['orderBy']=Array();
		$tmp_REQUEST_text['params']=empty($tmpLists['pagination']->params)?Array():$tmpLists['pagination']->params;
		$tmp_REQUEST_text=base64_encode(json_encode($tmp_REQUEST_text));
		
		//?????????????????????????????????????????????????????????????????????????????????
		$order_rootsku_product_image = array();
		if(!empty($tmpLists['orders'])){
			$order_rootsku_product_image = OrderHelper::GetRootSkuImage($tmpLists['orders']);
		}

		return $this->renderAuto('finishdeliveredlist',
				['search_condition'=>$tmp_REQUEST_text,
			'search_count'=>$tmpLists['pagination']->totalCount,
				'counter'=>$counter,'order_nav_html'=>$order_nav_html,'search'=>$search,
		        'order_rootsku_product_image'=>$order_rootsku_product_image,
				'carrierQtips'=>$carrierQtips]+$tmpLists);
	}
	
	/**
	 * ?????????????????????
	 */
	function actionListalldelivery(){
		return $this->redirect('/delivery/order/listplanceanorder');
		
		AppTrackerApiHelper::actionLog("eagle_v2", "delivery/order/Listalldelivery");
		$query_condition = $_REQUEST;
		
		$query_condition['order_status']=OdOrder::STATUS_WAITSEND;
		
		//????????????
		$counter = DeliveryHelper::getMenuStatisticData();
		//??????????????????
		$order_nav_html=DeliveryHelper::getOrderNav($counter);
		
		$carrierQtips = CarrierOpenHelper::getCarrierQtips();
		$search = array(
				'no_print_carrier'=>'?????????????????????',
				'print_carrier'=>'?????????????????????',
		);
		
		$tmpLists = CarrierprocessController::getlist($query_condition,'delivery/order');
		
		return $this->render('listalldelivery',
				['counter'=>$counter,'order_nav_html'=>$order_nav_html,'search'=>$search,
				'carrierQtips'=>$carrierQtips]+$tmpLists);
	}
	
	/**
	 +----------------------------------------------------------
	 * ????????????????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		lrq 	2016/07/28				?????????
	 +----------------------------------------------------------
	 **/
	public function actionScanningTrackingNumber()
	{
		AppTrackerApiHelper::actionLog("eagle_v2","/delivery/order/ScanningTrackingNumber");
		if(!\Yii::$app->request->getIsAjax())
			return json_encode(['error'=>true,'data'=>'???????????????']);
		try
		{
			$order_id = $_POST['orderid'];
			$trackingnumber = $_POST['trackingnumber'];
			
			$order = OdOrder::findOne(['order_id'=>$order_id]);
			$shipping_service_id = $order->default_shipping_method_code;
			$service = SysShippingService::find()->where(['id'=>$shipping_service_id])->one();
			$service_codeArr = $service->service_code;
			$customerNumber = \eagle\modules\carrier\helpers\CarrierAPIHelper::getCustomerNum2($order);
			$logisticInfoList = array(
				0=>array(
						'order_source'=>$order->order_source,//????????????
						'selleruserid'=>$order->selleruserid,//????????????
						'tracking_number'=>$trackingnumber,//?????????????????????
						'tracking_link'=>$service->web,//????????????????????????
						'shipping_method_code'=>isset($service_codeArr[$order->order_source])?$service_codeArr[$order->order_source]:'',//????????????????????????
						'shipping_method_name'=>'',//?????????????????????
						'order_source_order_id'=>$order->order_source_order_id,//???????????????
						'return_no'=>'',//????????????????????????????????????
						'customer_number'=>$customerNumber,//????????????????????????????????????
						'shipping_service_id'=>$shipping_service_id,//????????????id????????????
						'addtype'=>'???????????????',//???????????????
						'signtype'=>'all',//???????????? all??????part????????????
						'description'=>'',//??????????????????
				)
			);
			$result=OrderHelper::saveTrackingNumber($order_id, $logisticInfoList);
			if($result)
			{
				$order->carrier_error = '';
				$order->carrier_step = OdOrder::CARRIER_WAITING_DELIVERY;
				$order->customer_number =$customerNumber;
				$order->save();
				OperationLogHelper::log('delivery', $order->order_id,'?????????????????????','????????????'.$trackingnumber,\Yii::$app->user->identity->getFullName());
				return json_encode(array('success'=>true,'code'=>'100000','message'=>'?????????'));
			}
			else
			{
				return json_encode(array('success'=>false,'code'=>'100010','message'=>'????????????'));
			}
		}catch (Exception $ex){
			return json_encode(array('success'=>false,'code'=>'99999','message'=>$ex->getMessage()));
		}
	}

	/**
	 +----------------------------------------------------------
	 * ???????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		lgw 	  2016/08/08				?????????
	 +----------------------------------------------------------
	 * @param
	 * order_id			??????id(??????)
	 * warehouse_id     ??????id
	 * type             ?????? 1:?????????(??????) 2:?????????(??????+??????)
	 +----------------------------------------------------------
	 **/
	public function actionSkuOrderPrintlist(){
		AppTrackerApiHelper::actionLog("eagle_v2", "delivery/order/SkuOrderPrintlist");
// 		$orderIds = json_decode($_REQUEST['order_id'], true);
// 		print_r($_REQUEST);die;
				
		$orderIds=$_REQUEST['orders'];
		$orderIds = rtrim($orderIds,',');

		$OrderArr=OrderHelper::getOrderListByCondition('','','',['order_id'=>$orderIds],'','','',200, false, 'productOnly');

		$user=\Yii::$app->user->identity;
		$puid = $user->getParentUid();
		
		if(empty($OrderArr)){
			die('No order!');
		}else{
// 			$delivery = OdDelivery::findOne(['deliveryid'=>$_REQUEST['deliveryid']]);
// 			if($delivery->picking_status < 1){
// 				$delivery->picking_status= OdDelivery::PICKING_PRINT_ALREADY;
// 			}
// 			$delivery->print_picking_operator= \Yii::$app->user->identity->getUsername();
// 			$delivery->print_picking_time= time();
// 			$delivery->save(false);
		}
		$OrderArr=$OrderArr['data'];
		
		
		//????????????
		$order_arr = explode(',', $orderIds);
		$tmp_orderlist = $OrderArr;
		unset($OrderArr);
		$OrderArr = array();
			
		foreach ($order_arr as $order_arr_one){
			foreach ($tmp_orderlist as $tmp_orderlist_one){
				if($order_arr_one == $tmp_orderlist_one['order_id']){
					$OrderArr[] = $tmp_orderlist_one;
					break;
				}
			}
		}
		
		$productcount=0;
		$quantitycount=0;
		$OrderItemArr=array();
		$warehouseArr=array();
		$warehouselistArr=array();
		$TrackingNumberArr=array();
		$locationgridArr=array();
		$OrderCountArr=array();
		$orderIdss='';
		foreach ($OrderArr as $OrderArrone){
			//??????
			$warehouse = Warehouse::findOne(['warehouse_id'=>$OrderArrone['default_warehouse_id']]);
			$warehouseArr[$OrderArrone['order_id']]=$warehouse->name;
			//???????????????
			if(!isset($warehouselistArr[$OrderArrone['default_warehouse_id']])){
				$warehouselistArr[$OrderArrone['default_warehouse_id']]['warehouse']=$warehouse->name;
				$warehouselistArr[$OrderArrone['default_warehouse_id']]['quantitycount']=0;
				$warehouselistArr[$OrderArrone['default_warehouse_id']]['skuproduct']=array();
			}
			
			$orderitemsku=0;
			$orderitemphoto=array(); //??????????????????,??????????????????????????????????????????????????????
			$OrderItemArr_=$OrderArrone['items'];
			foreach ($OrderItemArr_ as $OrderItemArrone){
				$orderitemphoto[$OrderItemArrone['order_item_id']][]=$OrderItemArrone['photo_primary'];
			}
// 			print_r($orderitemphoto);die;
			
			//??????????????????
			$Products=OrderApiHelper::getProductsByOrder($OrderArrone['order_id'],$isSplitBundle=true,$isGetPickingInfo=true,$warehouseId=$OrderArrone['default_warehouse_id'],false,false,true);
			$OrderArrone['items']=$Products;
// 			print_r($Products);die;
			//????????????
			foreach ($Products as $OrderItemArr_one){
				$locationgridArr[$OrderItemArr_one['order_item_id']]=$OrderItemArr_one;
				$productcount++;
				$quantitycount +=$OrderItemArr_one['qty'];
				
				//????????????
				$order_photo=empty($orderitemphoto[$OrderItemArr_one['order_item_id']][0])?'':$orderitemphoto[$OrderItemArr_one['order_item_id']][0];

				if(!isset($OrderCountArr[$OrderArrone['order_id']]) || !isset($OrderCountArr[$OrderArrone['order_id']]['sku'][$OrderItemArr_one['sku']])){
					//???????????????sku
					$OrderCountArr[$OrderArrone['order_id']]['sku'][$OrderItemArr_one['sku']]=array( 
							'photo_primary'=>empty($OrderItemArr_one['photo_primary'])?$order_photo:(in_array($OrderArrone->order_source, array('cdiscount','priceminister'))?ImageCacherHelper::getImageCacheUrl($OrderItemArr_one['photo_primary'], $puid, 1):$OrderItemArr_one['photo_primary']),
							'quantity'=>$OrderItemArr_one['qty'],
							'sku'=>$OrderItemArr_one['sku'],
							'product_name'=>$OrderItemArr_one['product_name'],
							'product_attributes'=>$OrderItemArr_one['product_attributes'],
							'location_grid'=>$OrderItemArr_one['location_grid'],
							'prod_name_ch'=>isset($OrderItemArr_one['prod_name_ch'])?$OrderItemArr_one['prod_name_ch']:$OrderItemArr_one['product_name'],
					);
				}
				else{
						$OrderCountArr[$OrderArrone['order_id']]['sku'][$OrderItemArr_one['sku']]['quantity']+=$OrderItemArr_one['qty'];
				}
				$OrderCountArr[$OrderArrone['order_id']]['quantity']=isset($OrderCountArr[$OrderArrone['order_id']]['quantity'])?$OrderCountArr[$OrderArrone['order_id']]['quantity']+=$OrderItemArr_one['qty']:$OrderItemArr_one['qty'];

				//??????sku??????
				if(!isset($OrderItemArr[$OrderItemArr_one['sku']])){
					$OrderItemArr[$OrderItemArr_one['sku']]=array(
							'photo_primary'=>empty($OrderItemArr_one['photo_primary'])?$order_photo:(in_array($OrderArrone->order_source, array('cdiscount','priceminister'))?ImageCacherHelper::getImageCacheUrl($OrderItemArr_one['photo_primary'], $puid, 1):$OrderItemArr_one['photo_primary']),
							'quantity'=>$OrderItemArr_one['qty'],
							'sku'=>$OrderItemArr_one['sku'],
							'product_name'=>$OrderItemArr_one['product_name'],
							'product_attributes'=>$OrderItemArr_one['product_attributes'],
							'location_grid'=>$OrderItemArr_one['location_grid'],
					);
				}
				else{
					$OrderItemArr[$OrderItemArr_one['sku']]['quantity']+=$OrderItemArr_one['qty'];
				}
				
				//???????????????sku
				$warehouselistArr[$OrderArrone['default_warehouse_id']]['quantitycount'] +=$OrderItemArr_one['qty'];
				$warehouselistArr[$OrderArrone['default_warehouse_id']]['skuproduct'][$OrderItemArr_one['sku']]=array(
						'order_item_id'=>isset($warehouselistArr[$OrderArrone['default_warehouse_id']]['skuproduct'][$OrderItemArr_one['sku']]['order_item_id'])?$warehouselistArr[$OrderArrone['default_warehouse_id']]['skuproduct'][$OrderItemArr_one['sku']]['order_item_id'].$OrderItemArr_one['order_item_id'].',':$OrderItemArr_one['order_item_id'].',',
						'photo_primary'=>empty($OrderItemArr_one['photo_primary'])?$order_photo:(in_array($OrderArrone->order_source, array('cdiscount','priceminister'))?ImageCacherHelper::getImageCacheUrl($OrderItemArr_one['photo_primary'], $puid, 1):$OrderItemArr_one['photo_primary']),
						'quantity'=>isset($warehouselistArr[$OrderArrone['default_warehouse_id']]['skuproduct'][$OrderItemArr_one['sku']]['quantity'])?$warehouselistArr[$OrderArrone['default_warehouse_id']]['skuproduct'][$OrderItemArr_one['sku']]['quantity']+$OrderItemArr_one['qty']:$OrderItemArr_one['qty'],
						'sku'=>$OrderItemArr_one['sku'],
						'product_name'=>$OrderItemArr_one['product_name'],
						'product_attributes'=>$OrderItemArr_one['product_attributes'],
						'location_grid'=>$OrderItemArr_one['location_grid'],
						'prod_name_ch'=>$OrderItemArr_one['product_name'],
				);
			}

			//?????????
// 			$TrackingNumber=CarrierOpenHelper::getOrderShippedTrackingNumber($OrderArrone['order_id'],$OrderArrone['customer_number'],$OrderArrone['default_shipping_method_code']);
// 			$TrackingNumberArr[$OrderArrone['order_id']]=$TrackingNumber;
			$TrackingNumberArr[$OrderArrone['order_id']]=$OrderArrone['tracking_number'];

			//??????,????????????????????????
// 			$goods = OrderApiHelper::getProductsByOrder($OrderArrone['order_id'],$isSplitBundle=true,$isGetPickingInfo=true,$warehouseId=$OrderArrone['default_warehouse_id']);
// 			foreach ($goods as $goodsone){ 
// 				$locationgridArr[$goodsone['order_item_id']]=$goodsone;
// 				foreach ($warehouselistArr as $ks=>$warehouselistArrone){
// 					$skuproduct=$warehouselistArrone['skuproduct'];
// 					foreach ($skuproduct as $k=>$skuproductone){
// 						print_r($skuproduct);die;
// 						$arr = explode(',',$skuproductone['order_item_id']);
// 						foreach ($arr as $arrone){
// 							if($goodsone['order_item_id']==$arrone){
// 								//???????????????sku
// 								$OrderCountArr[$OrderArrone['order_id']]['sku'][$goodsone['sku']]['prod_name_ch']=isset($goodsone['prod_name_ch'])?$goodsone['prod_name_ch']:$goodsone['product_name'];
// 								if(!empty($goodsone['photo_primary']))
// 									$OrderCountArr[$OrderArrone['order_id']]['sku'][$goodsone['sku']]['photo_primary']=$goodsone['photo_primary'];
								
// 								//????????????
// 								if(!strstr($warehouselistArr[$ks]['skuproduct'][$k]['product_attributes']," ".$goodsone['product_attributes']." "))
// 									$warehouselistArr[$ks]['skuproduct'][$k]['product_attributes']=$warehouselistArr[$ks]['skuproduct'][$k]['product_attributes'].$goodsone['product_attributes'].' ';
// 								if(!strstr($warehouselistArr[$ks]['skuproduct'][$k]['location_grid']," ".$goodsone['location_grid']." "))
// 									$warehouselistArr[$ks]['skuproduct'][$k]['location_grid']=$warehouselistArr[$ks]['skuproduct'][$k]['location_grid'].$goodsone['location_grid'].' ';
// 								$warehouselistArr[$ks]['skuproduct'][$k]['prod_name_ch']=$goodsone['product_name'];
// 								if(!empty($goodsone['photo_primary']))
// 									$warehouselistArr[$ks]['skuproduct'][$k]['photo_primary']=$goodsone['photo_primary'];
								
// 								//sku?????????
// 								if(!strstr($OrderItemArr[$k]['product_attributes']," ".$goodsone['location_grid']." "))
// 									$OrderItemArr[$k]['product_attributes']=$OrderItemArr[$k]['product_attributes'].$goodsone['product_attributes'].' ';
// 								if(!empty($goodsone['photo_primary']))
// 									$OrderItemArr[$k]['photo_primary']=$goodsone['photo_primary'];
// 							}
// 						}
// 					}
// 				}
// 				//????????????
// 				try{
// 				$OrderCountArr[$OrderArrone['order_id']]['sku'][$goodsone['sku']]['location_grid'].=$goodsone['location_grid']." ";
// 				$OrderCountArr[$OrderArrone['order_id']]['sku'][$goodsone['sku']]['product_attributes'].=$goodsone['product_attributes']." ";
// 				}
// 				catch(Exception $e){}
// 			}
		}
		
		$services = CarrierApiHelper::getShippingServices();

		//??????SKU??????
		if(!empty($warehouselistArr)){
			if(count($warehouselistArr) > 0){
				foreach ($warehouselistArr as $warehouselistKey => $warehouselistVal){
					unset($tmp_skus);
					$tmp_skus = array();
					
					if(count($warehouselistVal['skuproduct']) > 0){
						foreach ($warehouselistVal['skuproduct'] as $tmp_skuVal){
							$tmp_skus[] = $tmp_skuVal['sku'];
						}
						
						rsort($tmp_skus);
						
						unset($tmp_skuproducts);
						$tmp_skuproducts_set = array();
						foreach ($tmp_skus as $tmp_skusVal){
							$tmp_skuproducts_set[$tmp_skusVal] = $warehouselistVal['skuproduct'][$tmp_skusVal];
						}
						
						unset($warehouselistArr[$warehouselistKey]['skuproduct']);
						$warehouselistArr[$warehouselistKey]['skuproduct'] = $tmp_skuproducts_set;
						
					}
				}
			}
		}
		
		

		$htmlpage="skuprintpicklist";
		if($_REQUEST['type']==2)
			$htmlpage='skuorderprintlist';
		else if($_REQUEST['type']==3)
			$htmlpage='distributionprintlist';
		return $this->renderAjax($htmlpage,array(
				'warehouseArr'=>$warehouseArr,//?????????????????? array([orderid]=>warehousename)
				'OrderArr'=>$OrderArr,//??????????????????
				'TrackingNumberArr'=>$TrackingNumberArr, //?????????????????????  array([orderid]=>TrackingNumber)
				'locationgridArr'=>$locationgridArr, //????????????
				'OrderItemArr'=>$OrderItemArr,  //sku????????????  array([sku]=>array())
				'orderIdlist'=>$orderIds,     //????????????id
				'productcount'=>$productcount,   //?????????????????????
				'quantitycount'=>$quantitycount, //??????????????????
				'warehouselistArr'=>$warehouselistArr,  //????????????   array([warehouseid]=>array())
				'services'=>$services, //????????????
				'OrderCountArr'=>$OrderCountArr,//????????????sku
		));
	
	}
	
	
	/**
	 * ?????????????????????????????????????????????????????????,is_print_distribution????????????????????????????????????????????????,????????????is_print_picking?????????????????????????????????????????????
	 */
	public function actionPickingPrintConfirm(){
		$puid = \Yii::$app->user->identity->getParentUid();
		 
		if(isset($_POST['orders'])&&!empty($_POST['orders'])){
			$orders	=	$_POST['orders'];
		}else{
			return "???????????????????????????(send none order)";
		}
		
		$tmp_printed = 1;
		if(isset($_POST['printed'])){
			if($_POST['printed'] != ''){
				$tmp_printed = $_POST['printed'];
			}
		}
		 
		$orders = rtrim($orders,',');
		$orderlist = OdOrder::find()->where("order_id in ({$orders})")->all();
		 
		//??????????????????????????????
		foreach($orderlist as $order){
			$order->is_print_distribution = $tmp_printed;
			$order->print_distribution_operator = $puid;
			$order->print_distribution_time = time();
			$order->save(false);
		}
		
		if($tmp_printed == 1){
			return '?????????????????????';
		}else{
			return '?????????????????????';
		}
	}
	
	/**
	 +----------------------------------------------------------
	 * ?????? ?????????????????? ??????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lrq 	2016/08/08				?????????
	 +----------------------------------------------------------
	 **/
	public function actionShowScanningListDistributionBox(){
		//??????????????????????????????
		$uid = 0;
		$userInfo = \Yii::$app->user->identity;
		if ($userInfo['puid'] == 0){
			$uid = $userInfo['uid'];
		}else {
			$uid = $userInfo['puid'];
		}
		
		$automatic_print_enable = 0;
		$redis_key_lv1 = 'AutomaticPrintEnable';
		$redis_key_lv2 = $uid;
		$warn_record = RedisHelper::RedisGet($redis_key_lv1, $redis_key_lv2);
		if(!empty($warn_record))
			$automatic_print_enable = $warn_record;
		
		return $this->renderPartial('showscanninglistdistributionbox', ['automatic_print_enable' => $automatic_print_enable]);
	}
	
	/**
	 +----------------------------------------------------------
	 * ?????? ?????????????????? ??????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lrq 	2016/08/08				?????????
	 +----------------------------------------------------------
	 **/
	public function actionShowScanningDeliveryOneBox()
	{
		return $this->renderPartial('showscanninglistdeliveryonebox');
	}
	
	/**
	 +----------------------------------------------------------
	 * ?????? ?????????????????? ??????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lrq 	2016/08/08				?????????
	 +----------------------------------------------------------
	 **/
	public function actionShowScanningDeliveryChooseBox()
	{
		return $this->renderPartial('showscanninglistdeliverychoosebox');
	}
	
	/**
	 +----------------------------------------------------------
	 * ???????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		lrq 	2016/08/13				           ?????????
	 +----------------------------------------------------------
	 **/
	public function actionUpdateSellerWeight()
	{
		try
		{
			$order = OdOrder::findOne(['order_id'=>$_POST['order_id']]);
			if(!empty($order))
			{
				$order->seller_weight = $_POST['seller_weight'];
				$order->save();
				OperationLogHelper::log('delivery', $order->order_id,'??????????????????','???????????????',\Yii::$app->user->identity->getFullName());
				return json_encode(array('success'=>true,'code'=>'100000','message'=>'????????????'));
			}
			else
			{
				return json_encode(array('success'=>false,'code'=>'100010','message'=>'??????????????????'));
			}
		}catch (Exception $ex){
			return json_encode(array('success'=>false,'code'=>'99999','message'=>$ex->getMessage()));
		}
	}
	
	/**
	 +----------------------------------------------------------
	 * ?????????????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		lrq 	2016/08/15				           ?????????
	 +----------------------------------------------------------
	 **/
	public function actionWeighingEnable()
	{
		$r = ConfigHelper::setConfig("weighing_enable", $_POST["weighing_enable"]);
		
		return json_encode(array('success'=>true,'code'=>'100010','message'=>'??????????????????'));
	}
	
	/**
	 +----------------------------------------------------------
	 * ????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		hqw 	2016/08/22				           ?????????
	 +----------------------------------------------------------
	 **/
	public function actionListprintdelivery(){
		//?????????OMS??????????????????????????????????????????
		$use_mode = isset($_REQUEST['use_mode']) ? $_REQUEST['use_mode'] : '';
		
		AppTrackerApiHelper::actionLog("eagle_v2", "delivery/order/Listprintdelivery");
		$query_condition = $_REQUEST;
		$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
		$query_condition['warehouse_and_shippingservice'] = '(default_warehouse_id > -1 and default_shipping_method_code <> "")';
		
		$deliveryStatus = OdOrder::$deliveryStatus;
		unset($deliveryStatus[0]);
		unset($deliveryStatus[1]);
		
		$query_condition['delivery_status_in'] = array_keys($deliveryStatus);
		
		//???????????? S
		if(!isset($query_condition['default_warehouse_id'])){
			$warehouse_search = -2;
		}else{
			if($query_condition['default_warehouse_id'] == ''){
				$warehouse_search = -2;
			}else{
				$warehouse_search = $query_condition['default_warehouse_id'];
			}
		}
		
		if($warehouse_search == -2){
			unset($query_condition['default_warehouse_id']);
		}
		//???????????? E
		
		//????????????
		$counter = DeliveryHelper::getMenuStatisticData($warehouse_search,(empty($query_condition['use_mode']) ? '' : $query_condition['use_mode']), 'listprintdelivery');
		
		//??????????????????
		$order_nav_html=DeliveryHelper::getOrderNav($counter,0);
		
		$tmpLists = CarrierprocessController::getlist($query_condition,'delivery/order');
		
		$tmp_REQUEST_text['REQUEST']=$query_condition;
		$tmp_REQUEST_text['order_source']=['order_source'=>'delivery'];
		$tmp_REQUEST_text['where']=Array();
		$tmp_REQUEST_text['orderBy']=Array();
		$tmp_REQUEST_text['params']=empty($tmpLists['pagination']->params)?Array():$tmpLists['pagination']->params;
		$tmp_REQUEST_text=base64_encode(json_encode($tmp_REQUEST_text));
		
		//?????????????????????????????????????????????????????????????????????????????????
		$order_rootsku_product_image = array();
		if(!empty($tmpLists['orders'])){
			$order_rootsku_product_image = OrderHelper::GetRootSkuImage($tmpLists['orders']);
		}
		
		return $this->renderAuto('listprintdelivery',
				['search_condition'=>$tmp_REQUEST_text,
				'search_count'=>$tmpLists['pagination']->totalCount,
				'order_rootsku_product_image' => $order_rootsku_product_image,
				'counter'=>$counter,'order_nav_html'=>$order_nav_html]+$tmpLists);
	}

	/**
	 +----------------------------------------------------------
	 * DHL????????????
	 +----------------------------------------------------------
	 @param orders ??????id??????
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		lgw 	   2017/01/12			        ?????????
	 +----------------------------------------------------------
	 **/
	public function actionDhlInvoicePrint($orders){
		$orderIds=empty($_REQUEST['orders'])?$orders:$_REQUEST['orders'];
		$orderIds = rtrim($orderIds,',');
		$orderIds_arr=explode(',', $orderIds);
		
		//??????
		$pdf = new \TCPDF("P", "mm", 'A4', true, 'UTF-8', false);
		
		//???????????????
		$pdf->SetMargins(0, 0, 0);
		$pdf->setCellPaddings(0, 0, 0, 0);
		
		//???????????????????????? ??????/??????
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		
		//?????????????????????,??????????????????0
		$pdf->SetAutoPageBreak(true, 0);
			
		$pdf->setCellHeightRatio(1.1); //????????????
		$order = OrderApiHelper::getOrderListByCondition(['keys'=>'order_id','searchval'=>$orderIds_arr]);
		if(!empty($order)){
			$order=$order['data'];
			foreach ($order as $rt){			
				$pdf->AddPage();
				
				//left?????????
				$pdf->Line(10, 20, 10, 285, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
				//top?????????
				$pdf->Line(10, 20, 200, 20, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
				//right?????????
				$pdf->Line(200, 20, 200, 285, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
				//bottom?????????
				$pdf->Line(10, 285, 200, 285, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
							
				//proforma invoice???????????????
				$pdf->Line(10, 70, 200, 70, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
				//??????????????????????????????
				$pdf->Line(10, 130, 200, 130, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
				//???????????????????????????
				$pdf->Line(10, 200, 200, 200, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
				//????????????
				$pdf->Line(105, 20, 105, 200, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
							
				//??????:proforma invoice
				$pdf->SetFont('msyhbd', '', 20);
				$pdf->writeHTMLCell(95, 0, 105, 40, 'PROFORMA INVOICE', 0, 1, 0, true, 'C', true);
					
				//??????:shipper:
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(14, 0, 10, 21, 'Shipper:', 0, 1, 0, true, 'C', true);
				
				$info = eagle\modules\carrier\helpers\CarrierAPIHelper::getAllInfo($rt);
				$senderAddressInfo=$info['senderAddressInfo']['shippingfrom'];
				//??????????????????
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(85, 0, 17, 25, $senderAddressInfo['company_en'], 0, 1, 0, true, 'L', true);
				
				//????????????
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(85, 0, 17, 29, $senderAddressInfo['contact_en'], 0, 1, 0, true, 'L', true);
				
				//???????????????1
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(85, 0, 17, 33, $senderAddressInfo['street_en'], 0, 1, 0, true, 'L', true);
				
				//???????????????
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(55, 0, 17, 52, $senderAddressInfo['city_en'], 0, 1, 0, true, 'L', true);
				
				//???????????????
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(20, 0, 75, 52, $senderAddressInfo['postcode'], 0, 1, 0, true, 'L', true);
				
				//???????????????
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(85, 0, 17, 56, $senderAddressInfo['country'], 0, 1, 0, true, 'L', true);
				
				//??????:?????????Phone:
				$pdf->SetFont('msyh', '', 10);
				$pdf->writeHTMLCell(13, 0, 17, 60, 'Phone:', 0, 1, 0, true, 'L', true);
				
				//???????????????
				$pdf->SetFont('msyh', '', 10);
				$pdf->writeHTMLCell(73, 0, 30, 60, (empty($senderAddressInfo['phone'])?$senderAddressInfo['mobile']:$senderAddressInfo['phone']), 0, 1, 0, true, 'L', true);
				
				//??????:?????????VAT/GST No:
				$pdf->SetFont('msyh', '', 10);
				$pdf->writeHTMLCell(85, 0, 17, 65, 'VAT/GST No:', 0, 1, 0, true, 'L', true);

				//??????:Date:
				$pdf->SetFont('msyh', '', 12);
				$pdf->writeHTMLCell(14, 0, 105, 74, 'Date:', 0, 1, 0, true, 'C', true);
								
				//??????$rt->printtime
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(40, 0, 120, 75, date('Y-m-d',time()), 0, 1, 0, true, 'L', true);
				
				//??????:Invoice Number:
				$pdf->SetFont('msyh', '', 12);
				$pdf->writeHTMLCell(34, 0, 106, 87, 'Invoice Number:', 0, 1, 0, true, 'C', true);
				
				//??????:airwaybill Number:
				$pdf->SetFont('msyh', '', 12);
				$pdf->writeHTMLCell(39, 0, 106, 99, 'Airwaybill Number:', 0, 1, 0, true, 'C', true);
				
				//?????????
				$checkResult = eagle\modules\carrier\helpers\CarrierAPIHelper::validate(1,1,$rt);
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(53, 0, 146, 100, (empty($checkResult['data']['shipped']['tracking_number'])?'':$checkResult['data']['shipped']['tracking_number']), 0, 1, 0, true, 'L', true);
				
				//??????:Receiver:
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(14, 0, 11, 71, 'Receiver:', 0, 1, 0, true, 'C', true);
				
				//??????????????????
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(85, 0, 17, 75, $rt->consignee_company, 0, 1, 0, true, 'L', true);
				
				//????????????
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(85, 0, 17, 79, $rt->consignee, 0, 1, 0, true, 'L', true);
				
				//???????????????1
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(85, 0, 17, 83, $rt->consignee_address_line1, 0, 1, 0, true, 'L', true);
				
				//???????????????2
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(85, 0, 17, 93, $rt->consignee_address_line2, 0, 1, 0, true, 'L', true);
				
				//???????????????3
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(85, 0, 17, 103, $rt->consignee_address_line3, 0, 1, 0, true, 'L', true);
				
				//???????????????
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(55, 0, 17, 112, $rt->consignee_city, 0, 1, 0, true, 'L', true);
				
				//???????????????
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(20, 0, 75, 112, $rt->consignee_postal_code, 0, 1, 0, true, 'L', true);
				
				//???????????????
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(85, 0, 17, 116, $rt->consignee_country, 0, 1, 0, true, 'L', true);
				
				//??????:Phone:
				$pdf->SetFont('msyh', '', 10);
				$pdf->writeHTMLCell(13, 0, 17, 120, 'Phone:', 0, 1, 0, true, 'L', true);
				
				//???????????????
				$pdf->SetFont('msyh', '', 10);
				$pdf->writeHTMLCell(73, 0, 30, 120, (empty($rt->consignee_phone)?$rt->consignee_mobile:$rt->consignee_phone), 0, 1, 0, true, 'L', true);
				
				//??????:VAT/GST No:
				$pdf->SetFont('msyh', '', 10);
				$pdf->writeHTMLCell(85, 0, 17, 125, 'VAT/GST No:', 0, 1, 0, true, 'L', true);
				
				//??????:Comments:
				$pdf->SetFont('msyh', '', 12);
				$pdf->writeHTMLCell(24, 0, 106, 113, 'Comments:', 0, 1, 0, true, 'C', true);
				
				//Date???????????????
				$pdf->Line(105, 83, 200, 83, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
				//invoice number???????????????
				$pdf->Line(105, 95, 200, 95, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
				//airwaybill number???????????????
				$pdf->Line(105, 108, 200, 108, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
				
				//??????:No
				$pdf->SetFont('msyh', '', 10);
				$pdf->writeHTMLCell(10, 0, 10, 133, 'No.', 0, 1, 0, true, 'C', true);
				
				//??????:Full Description of Goods
				$pdf->SetFont('msyh', '', 10);
				$pdf->writeHTMLCell(62, 0, 22, 133, 'Full Description of Goods', 0, 1, 0, true, 'C', true);
	
				//??????:qty
				$pdf->SetFont('msyh', '', 10);
				$pdf->writeHTMLCell(10, 0, 90, 133, 'QTY', 0, 1, 0, true, 'C', true);
				
				//??????:Commodity Code
				$pdf->SetFont('msyh', '', 10);
				$pdf->writeHTMLCell(40, 0, 105, 133, 'Commodity Code', 0, 1, 0, true, 'C', true);
				
				//??????:UnitValue
				$pdf->SetFont('msyh', '', 10);
				$pdf->writeHTMLCell(10, 0, 150, 131, 'Unit Value', 0, 1, 0, true, 'C', true);
				
				//??????:Country of Origin
				$pdf->SetFont('msyh', '', 10);
				$pdf->writeHTMLCell(40, 0, 163, 133, 'Country of Origin', 0, 1, 0, true, 'C', true);
				
				//???????????????????????????
				$pdf->Line(20, 130, 20,200, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
				//???????????????????????????
				$pdf->Line(86, 130, 86,200, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
				//???????????????????????????
				$pdf->Line(145, 130, 145,224, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
				//???????????????????????????
				$pdf->Line(165, 130, 165,200, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
	
				//??????????????????????????????
				$pdf->Line(10, 140, 200,140, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
				
				//??????:Total Declared Value:
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(34, 0, 145, 202, 'Total Declared Value:', 0, 1, 0, true, 'C', true);
				
				//Total Declared Value????????????
				$pdf->Line(145, 208, 200, 208, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
					
				//??????:Total Pieces:
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(21, 0, 145, 210, 'Total Pieces:', 0, 1, 0, true, 'C', true);
				
				//Total Pieces????????????
				$pdf->Line(145, 216, 200, 216, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
				
				//??????:Total Gross Weight:
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(31, 0, 145, 218, 'Total Gross Weight:', 0, 1, 0, true, 'C', true);
				
				//??????:kg
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(6, 0, 195, 218, 'kg', 0, 1, 0, true, 'C', true);
				
				//Total Gross Weight????????????
				$pdf->Line(145, 224, 200, 224, $style=array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
	
				//????????????
				$itemListDetailInfo=$rt->declaration_info;
				$items=json_decode($itemListDetailInfo,true);
				//print_r($items);die;
				$totalvalue=0;
				$totalweight=0;
				if(!empty($items)){
					for($key=0;$key<$items['total'];$key++){
						$jiange=$key*8;
						//No
						$pdf->SetFont('msyh', '', 10);
						$pdf->writeHTMLCell(10, 0, 10, 140+$jiange, ($key+1), 0, 1, 0, true, 'C', true);
						
						//??????
						$pdf->SetFont('msyh', '', 10);
						$pdf->writeHTMLCell(64, 0, 21, 140+$jiange, empty($items['EName'][$key])?'':$items['EName'][$key], 0, 1, 0, true, 'C', true);
						
						//??????
						$pdf->SetFont('msyh', '', 10);
						$pdf->writeHTMLCell(12, 0, 89, 140+$jiange, empty($items['quantity'][$key])?'':$items['quantity'][$key], 0, 1, 0, true, 'C', true);
						
						//????????????
						$pdf->SetFont('msyh', '', 10);
						$pdf->writeHTMLCell(40, 0, 105, 140+$jiange, empty($items['sku'][$key])?'':$items['sku'][$key], 0, 1, 0, true, 'C', true);
						
						//??????
						$pdf->SetFont('msyh', '', 10);
						$pdf->writeHTMLCell(16, 0, 147, 140+$jiange, empty($items['DeclaredValue'][$key])?'':sprintf("%.2f",$items['DeclaredValue'][$key]), 0, 1, 0, true, 'C', true);
						
						//??????
						$pdf->SetFont('msyh', '', 10);
						$pdf->writeHTMLCell(33, 0, 166, 140+$jiange, 'China', 0, 1, 0, true, 'C', true);
						
						$totalvalue+=(empty($items['DeclaredValue'][$key])?0:$items['DeclaredValue'][$key]) * (empty($items['quantity'][$key])?0:$items['quantity'][$key]);
						$totalweight+=(empty($items['Weight'][$key])?0:($items['Weight'][$key])) * (empty($items['quantity'][$key])?0:$items['quantity'][$key]);
					}
				}
				//Total Declared Value:
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(20, 0, 179, 202, sprintf("%.2f",$totalvalue), 0, 1, 0, true, 'C', true);
								
				//Total Pieces
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(30, 0, 166, 210, '1', 0, 1, 0, true, 'C', true);
				
				//Total Gross Weight
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(18, 0, 177, 218, $totalweight, 0, 1, 0, true, 'C', true);
				
				//??????:Type of Export
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(25, 0, 10, 228, 'Type of Export:', 0, 1, 0, true, 'C', true);
				
				//??????:Permanent
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(60, 0, 40, 228, 'Permanent', 0, 1, 0, true, 'L', true);
				
				//??????:Currency Code
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(25, 0, 100, 228, 'Currency Code:', 0, 1, 0, true, 'C', true);
				
				//??????:USD
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(55, 0, 140, 228, 'USD', 0, 1, 0, true, 'L', true);
				
				//??????:Terms of
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(16, 0, 10, 236, 'Terms of:', 0, 1, 0, true, 'C', true);
				
				//??????:Incoterm
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(16, 0, 100, 236, 'Incoterm:', 0, 1, 0, true, 'C', true);
				
				//??????:DAP-Delivered at Place
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(55, 0, 140, 236, 'DAP-Delivered at Place', 0, 1, 0, true, 'L', true);
				
				//??????:?????????
				$pdf->SetFont('msyh', '', 7.5);
				$pdf->writeHTMLCell(171, 0, 10, 250, 'I/We hereby certify that the information of this invoice is true and correct and that the contents of this shipment are as stated above.', 0, 1, 0, true, 'C', true);
				
				//??????:Signature
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(16, 0, 10, 265, 'Signature:', 0, 1, 0, true, 'C', true);
				
				//??????:?????????
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(50, 0, 43, 265, '_________________________', 0, 1, 0, true, 'L', true);
				
				//??????:Name of Company
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(30, 0, 10, 272, 'Name of Company:', 0, 1, 0, true, 'C', true);
				
				//??????????????????
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(55, 0, 43, 272, (empty($senderAddressInfo['company_en'])?$senderAddressInfo['contact_en']:$senderAddressInfo['company_en']), 0, 1, 0, true, 'L', true);
				
				//??????:Company Stamp
				$pdf->SetFont('msyh', '', 9);
				$pdf->writeHTMLCell(30, 0, 100, 272, 'Company Stamp:', 0, 1, 0, true, 'C', true);
			}
			$pdf->Output('print.pdf', 'I');
		}
	}
	
	/**
	 +----------------------------------------------------------
	 * DHL????????????(??????????????????)
	 +----------------------------------------------------------
	 @param orders ??????id??????
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		lgw 	   2018/04/10			        ?????????
	 +----------------------------------------------------------
	 **/
	public function actionDhlInvoiceImgPrint(){
		$orderIds=$_REQUEST['orders'];
		$orderIds = rtrim($orderIds,',');
		$orderIds_arr=explode(',', $orderIds);
		
		$pdf = new PDFMerger();
		
		$basepath = Yii::getAlias('@webroot');
		
		$order = OrderApiHelper::getOrderListByCondition(['keys'=>'order_id','searchval'=>$orderIds_arr]);
		if(!empty($order)){
			$order=$order['data'];
						
			$puid=$account="";
			foreach ($order as $rt){
				
				$checkResult = \eagle\modules\carrier\helpers\CarrierAPIHelper::validate(1,1,$rt);
				$shipped = $checkResult['data']['shipped'];
				$puid = $checkResult['data']['puid'];
				
				$info = \eagle\modules\carrier\helpers\CarrierAPIHelper::getAllInfo($rt);
				$account = $info['account'];

				if(empty($shipped->tracking_number)){
					return 'order:'.$rt->order_id .' ???????????????,??????????????????????????????' ;
				}
				\Yii::info('dhl_invoice return_no:'.json_encode($shipped->return_no), "file");
				if(!isset($shipped->return_no['invoiceLabelPdfPath'])){
					//??????????????????????????????
					self::actionDhlInvoicePrint($orderIds);
					return;
				}
				else
					$pdf->addPDF($basepath.$shipped->return_no['invoiceLabelPdfPath'],'all');
				
				$rt->is_print_carrier = 1;
				$rt->print_carrier_operator = $puid;
				$rt->printtime = time();
				$rt->save();
				
			}
			
			$pdfUrl = \eagle\modules\carrier\helpers\CarrierAPIHelper::savePDF('1',$puid,$account->carrier_code.'_', 0);
				
			isset($pdfUrl)?$pdf->merge('file', $pdfUrl['filePath']):$pdfUrl='';
			
			$this->redirect($pdfUrl['pdfUrl']);
		}
		
		
		
	}
	
	/**
	 +----------------------------------------------------------
	 * ??????????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lrq 	2017/03/15				?????????
	 +----------------------------------------------------------
	 **/
	public function actionCheckOrderStockOut()
	{
		if(empty($_POST['order_id'])){
			$rtn['success'] = false;
			$rtn['message'] = '?????????????????????';
		}
		else{
			$rtn = \eagle\modules\inventory\helpers\InventoryApiHelper::CheckOrderStockOut($_POST['order_id']);
		}
		
		return json_encode($rtn);
	}
	
	public function actionTest11(){
		$parmaStr = \eagle\modules\message\helpers\MessageHelper ::decryptBuyerLinkParam('MTI5LTY3ZGYtMTJjNC0x');
		print_r($parmaStr);
		exit();
	}
	
	//????????????????????????????????????????????? ?????????????????????????????????????????????
	public function actionSmtEditDomesticList(){
		return $this->renderPartial('smtEditDomesticList',[]);
	}
	
	//????????????????????????????????????????????? ????????????????????????????????????????????? ????????????
	public function actionSmtEditDomesticSave(){
		$result = array('success'=>0, 'msg'=>'');
		
		$tmp_smt_str = isset($_POST['smt_str']) ? $_POST['smt_str'] : '';
		
		if(empty($tmp_smt_str)){
			$result['msg'] = '???????????????!';
			exit(json_encode($result));
		}
		
		$tmp_smt_arr = explode("\n", $tmp_smt_str);
		
		if(count($tmp_smt_arr) == 0){
			$result['msg'] = '?????????????????????,?????????????????????????????????';
			exit(json_encode($result));
		}
		
		//?????????????????????
		$smt_arr = array();
		
		//????????????????????????
		$tmp_order_arr = array();
		
		foreach ($tmp_smt_arr as $tmp_smt_key => $tmp_smt_val){
			unset($tmp_Line);
			
			if (stripos('1'.$tmp_smt_val , ' ')>0){
				$tmp_Line = explode(" ", $tmp_smt_val);
			}else{
				$tmp_Line = explode("\t", $tmp_smt_val);
			}
			
			if(count($tmp_Line) != 3){
				$result['msg'] = '??? '.$tmp_smt_key.' ????????????????????????,?????????????????????????????????';
				exit(json_encode($result));
			}
			
			$tmp_order_arr[(int)$tmp_Line[0]] = (int)$tmp_Line[0];
			
			$smt_arr[(int)$tmp_Line[0]] = $tmp_Line;
		}
		
		unset($tmp_smt_str);
		unset($tmp_smt_arr);
		
		$order_lists = OdOrder::find()->where(['order_id'=>$tmp_order_arr])->all();
		
		if(count($order_lists) == 0){
			$result['msg'] = '?????????????????????????????????';
			exit(json_encode($result));
		}
		
		$tmp_error_msg = '';
		
		foreach ($order_lists as $order){
			if($order->order_source != 'aliexpress'){
				$tmp_error_msg .= '???????????????:'.$order->order_id.' ?????????????????????<br>';
				continue;
			}else if($order->order_status != 300){
				$tmp_error_msg .= '???????????????:'.$order->order_id.' ???????????????????????????<br>';
				continue;
			}else if(!in_array($order->carrier_step, array(0, 4))){
				$tmp_error_msg .= '???????????????:'.$order->order_id.' ?????????????????????????????????<br>';
				continue;
			}else if($order->default_carrier_code != 'lb_alionlinedelivery'){
				$tmp_error_msg .= '???????????????:'.$order->order_id.' ??????????????????????????????????????????<br>';
				continue;
			}else if(!isset($smt_arr[(int)$order->order_id])){
				$tmp_error_msg .= '???????????????:'.$order->order_id.' ????????????,????????????????????????<br>';
				continue;
			}
			
			$order->declaration_info = json_encode(array('smt_channel'=>$smt_arr[(int)$order->order_id]));
			
			if(!$order->save(false)){
				$tmp_error_msg .= '???????????????:'.$order->order_id.' ??????????????????,????????????????????????<br>';
				continue;
			}
		}
		
		if($tmp_error_msg != ''){
			$result['success'] = 1;
			$result['msg'] = '?????????????????????<br> '.$tmp_error_msg;
		}else{
			$result['success'] = 2;
		}
		
		exit(json_encode($result));
	}
	
	/**
	 +----------------------------------------------------------
	 * ?????????????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date		note
	 * @author		lrq 	2017/11/13	 ?????????
	 +----------------------------------------------------------
	 **/
	public function actionAutomaticPrintEnable()
	{
		$automatic_print_enable = empty($_POST['automatic_print_enable']) ? 0 : 1; 
		$uid = 0;
		$userInfo = \Yii::$app->user->identity;
		if ($userInfo['puid'] == 0){
			$uid = $userInfo['uid'];
		}else {
			$uid = $userInfo['puid'];
		}
		
		$redis_key_lv1 = 'AutomaticPrintEnable';
		$redis_key_lv2 = $uid;
		$ret = RedisHelper::RedisSet($redis_key_lv1, $redis_key_lv2, $automatic_print_enable);
		if(empty($ret))
			RedisHelper::RedisSet($redis_key_lv1, $redis_key_lv2, $automatic_print_enable);
		
		return json_encode(array('success'=>true));
	}
	
}

?>