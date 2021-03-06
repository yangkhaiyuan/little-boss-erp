<?php
namespace eagle\modules\carrier\controllers;

use Yii;
use yii\web\Controller;
use common\helpers\Helper_Array;
use eagle\models\SaasAliexpressUser;
use eagle\modules\delivery\helpers\DeliveryHelper;
use eagle\modules\order\models\OdOrder;
use eagle\modules\carrier\helpers\CarrierOpenHelper;
use eagle\modules\carrier\apihelpers\CarrierApiHelper;
use eagle\modules\order\helpers\OrderTagHelper;
use eagle\modules\util\helpers\ConfigHelper;
use eagle\modules\carrier\apihelpers\ApiHelper;
use eagle\modules\inventory\helpers\InventoryHelper;
use eagle\models\EbayCountry;
use eagle\models\SysCountry;
use eagle\modules\carrier\models\SysCarrier;
use eagle\modules\app\apihelpers\AppTrackerApiHelper;
use eagle\modules\carrier\models\SysShippingService;
use eagle\modules\util\helpers\TranslateHelper;
use eagle\modules\inventory\apihelpers\InventoryApiHelper;
use eagle\modules\order\apihelpers\OrderApiHelper;
use eagle\modules\order\models\OdOrderShipped;
use eagle\models\QueueSyncshipped;
use eagle\modules\order\openapi\OrderApi;
use eagle\modules\order\helpers\OrderHelper;
use eagle\modules\util\helpers\OperationLogHelper;
use eagle\modules\platform\apihelpers\PlatformAccountApi;
use eagle\models\CarrierUserLabel;
use eagle\modules\util\helpers\PDFMergeHelper;
use common\helpers\Helper_Curl;
use eagle\models\CrCarrierTemplate;
use eagle\models\SysShippingMethod;
use eagle\models\carrier\CrTemplate;
use eagle\modules\order\models\Excelmodel;
use yii\helpers\Url;
use eagle\modules\inventory\models\Warehouse;
use eagle\modules\util\helpers\CountryHelper;
use eagle\modules\order\models\OrderRelation;
use eagle\modules\carrier\apihelpers\PrintPdfHelper;
use eagle\modules\util\helpers\TimeUtil;
use eagle\modules\util\helpers\RedisHelper;
use Qiniu\json_decode;
use eagle\modules\permission\helpers\UserHelper;
use eagle\modules\lazada\apihelpers\LazadaApiHelper;
use eagle\models\SaasLazadaUser;
use common\api\lazadainterface\LazadaInterface_Helper;

class CarrierprocessController extends \eagle\components\Controller
{
	public $enableCsrfValidation = false;
	/**
	 +----------------------------------------------------------
	 * ??????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		zwd 		2016/03/14				?????????
	 +----------------------------------------------------------
	 **/
	public function actionIndex(){
		return $this->render('index');
	}
	/**
	 +----------------------------------------------------------
	 * ?????????????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		zwd 		2016/03/14				?????????
	 +----------------------------------------------------------
	 **/
	public function actionWaitingmatch(){
		AppTrackerApiHelper::actionLog("eagle_v2", "/carrier/Carrierprocess/Waitingmatch");
		$query_condition = $_REQUEST;
		$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
		$query_condition['no_warehouse_or_shippingservice'] = '(default_warehouse_id < 0 or default_shipping_method_code = "")';//??????????????????????????????
		$search = array('is_comment_status'=>'???????????????');
		$carrierQtips = CarrierOpenHelper::getCarrierQtips();
		return $this->render('waitingmatch',
				[
				'search'=>$search,
				'carrierQtips'=>$carrierQtips,
				]+self::getlist($query_condition)
		);
	}
	/**
	 +----------------------------------------------------------
	 * api???????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		zwd 		2016/03/14				?????????
	 +----------------------------------------------------------
	 **/
	public function actionWaitingpost(){
		//???2016-08-23 23:45:00 ?????????????????????????????????,?????????????????????
		if(time() > 1471967100)
			return $this->redirect('/delivery/order/listplanceanorder');
		
		AppTrackerApiHelper::actionLog("eagle_v2", "/carrier/Carrierprocess/Waitingpost");
		$query_condition = $_REQUEST;
		$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
		//???????????????
		$query_condition['carrier_type'] = $_REQUEST['carrier_type'] = isset($_REQUEST['carrier_type'])?$_REQUEST['carrier_type']:1;
		if($query_condition['carrier_type'] != 1 && $query_condition['carrier_type'] != 2 && $query_condition['carrier_type'] != 3){
			$query_condition['carrier_type'] = 1;
		}
		//??????????????????
		if(empty($_REQUEST['carrier_step'])){
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
		
		$query_condition['warehouse_and_shippingservice'] = '(default_warehouse_id > -1 and default_shipping_method_code <> "")';
		$carrierQtips = CarrierOpenHelper::getCarrierQtips();
		$search = array(
				'is_comment_status'=>'???????????????',
				'no_print_carrier'=>'?????????????????????',
				'print_carrier'=>'?????????????????????',
		);
		
		
		$tmpLists = self::getlist($query_condition);
		
		//????????????????????????????????????????????????
		$tmpSearchShippingid = array();
		$carrierAccountInfo = array();
		
		//?????????????????????????????????
		$order_products = array();
		
		//??????API??????code
		$default_carrier_codes = array();
		$sys_carrier_params = array();
		
		foreach ($tmpLists['orders'] as $tmporders){
			if(substr($tmporders->default_carrier_code, 0, 3) == 'lb_'){
				$tmpSearchShippingid[$tmporders->default_shipping_method_code] = $tmporders->default_shipping_method_code;
		
				$default_carrier_codes[$tmporders->default_carrier_code] = $tmporders->default_carrier_code;
		
				CarrierOpenHelper::getCustomsDeclarationSumInfo($tmporders, $order_products);
			}
		}
		
		if(count($tmpSearchShippingid) > 0){
			$carrierAccountInfo = CarrierOpenHelper::getCarrierAccountInfoByShippingId(array('shippings'=>$tmpSearchShippingid));
		}
		
		if(count($default_carrier_codes) > 0){
			$sys_carrier_params = CarrierOpenHelper::getSysCarrierParams(array('carrier_codes'=>$default_carrier_codes));
		}
		
		$warehouseNameMap = \eagle\modules\inventory\helpers\InventoryApiHelper::getWarehouseIdNameMap();
		
		//????????????HTML??????
		$orderHtml = array();
		
		foreach ($tmpLists['orders'] as $tmporders){
			if(substr($tmporders->default_carrier_code, 0, 3) == 'lb_'){
				$orderHtml[$tmporders->order_id] = CarrierOpenHelper::getOrdersCarrierInfoView($tmporders, $order_products, $sys_carrier_params, $carrierAccountInfo, $warehouseNameMap);
			}
		}
		
		return $this->render('waitingpost',
				['search'=>$search,'carrierQtips'=>$carrierQtips,'orderHtml'=>$orderHtml]+$tmpLists);
	}
	/**
	 +----------------------------------------------------------
	 * api???????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		zwd 		2016/03/14				?????????
	 +----------------------------------------------------------
	 **/
	public function actionWaitingdelivery(){
		AppTrackerApiHelper::actionLog("eagle_v2", "/carrier/Carrierprocess/Waitingdelivery");
		$query_condition = $_REQUEST;
		$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
		//???????????????
		$query_condition['carrier_type'] = $_REQUEST['carrier_type'] = isset($_REQUEST['carrier_type'])?$_REQUEST['carrier_type']:1;
		if($query_condition['carrier_type'] != 1 && $query_condition['carrier_type'] != 2 && $query_condition['carrier_type'] != 3){
			$query_condition['carrier_type'] = 1;
		}
		//??????????????????
		if(!isset($_REQUEST['carrier_step']) || trim($_REQUEST['carrier_step'])==''){
			$query_condition['carrier_step']=$_REQUEST['carrier_step'] = 'DELIVERY';
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
		$query_condition['warehouse_and_shippingservice'] = '(default_warehouse_id > -1 and default_shipping_method_code <> "")';
		$carrierQtips = CarrierOpenHelper::getCarrierQtips();
		$search = array(
				'is_comment_status'=>'???????????????',
				'no_print_carrier'=>'?????????????????????',
				'print_carrier'=>'?????????????????????',
		);
		return $this->render('waitingpost',
				[
						'search'=>$search,
						'carrierQtips'=>$carrierQtips,
						]+self::getlist($query_condition));
	}
	/**
	 +----------------------------------------------------------
	 * api???????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		zwd 		2016/03/14				?????????
	 +----------------------------------------------------------
	 **/
	public function actionDelivered(){
		AppTrackerApiHelper::actionLog("eagle_v2", "/carrier/Carrierprocess/Delivered");
		$query_condition = $_REQUEST;
		$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
		//???????????????
		$query_condition['carrier_type'] = $_REQUEST['carrier_type'] = isset($_REQUEST['carrier_type'])?$_REQUEST['carrier_type']:1;
		if($query_condition['carrier_type'] != 1 && $query_condition['carrier_type'] != 2 && $query_condition['carrier_type'] != 3){
			$query_condition['carrier_type'] = 1;
		}
		//??????????????????
		if(empty($_REQUEST['carrier_step'])){
			$query_condition['carrier_step']=$_REQUEST['carrier_step'] = 'DELIVERYED';
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
		$query_condition['warehouse_and_shippingservice'] = '(default_warehouse_id > -1 and default_shipping_method_code <> "")';
		$carrierQtips = CarrierOpenHelper::getCarrierQtips();
		$search = array(
				'is_comment_status'=>'???????????????',
				'no_print_carrier'=>'?????????????????????',
				'print_carrier'=>'?????????????????????',
		);
		return $this->render('waitingpost',
				[
						'search'=>$search,
						'carrierQtips'=>$carrierQtips,
						]+self::getlist($query_condition));
	}
	/**
	 +----------------------------------------------------------
	 * api???????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		zwd 		2016/03/14				?????????
	 +----------------------------------------------------------
	 **/
	public function actionCompleted(){
		AppTrackerApiHelper::actionLog("eagle_v2", "/carrier/Carrierprocess/Completed");
		$query_condition = $_REQUEST;
		//$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
		//???????????????
		$query_condition['carrier_type'] = $_REQUEST['carrier_type'] = isset($_REQUEST['carrier_type'])?$_REQUEST['carrier_type']:1;
		if($query_condition['carrier_type'] != 1 && $query_condition['carrier_type'] != 2 && $query_condition['carrier_type'] != 3){
			$query_condition['carrier_type'] = 1;
		}
		//??????????????????
		if(!isset($_REQUEST['carrier_step']) || trim($_REQUEST['carrier_step'])==''){
			$query_condition['carrier_step']=$_REQUEST['carrier_step'] = 'FINISHED';
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
		$query_condition['warehouse_and_shippingservice'] = '(default_warehouse_id > -1 and default_shipping_method_code <> "")';
		$carrierQtips = CarrierOpenHelper::getCarrierQtips();
		$search = array(
				'is_comment_status'=>'???????????????',
				'no_print_carrier'=>'?????????????????????',
				'print_carrier'=>'?????????????????????',
		);
		return $this->render('waitingpost',
				[
						'search'=>$search,
						'carrierQtips'=>$carrierQtips,
						]+self::getlist($query_condition));
	}
	/**
	 +----------------------------------------------------------
	 * excel???????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		zwd 		2016/03/14				?????????
	 +----------------------------------------------------------
	 **/
	public function actionExcelexport(){
		AppTrackerApiHelper::actionLog("eagle_v2", "/carrier/Carrierprocess/Excelexport");
		$query_condition = $_REQUEST;
		$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
		//???????????????
		$query_condition['carrier_type'] = $_REQUEST['carrier_type'] = isset($_REQUEST['carrier_type'])?$_REQUEST['carrier_type']:2;
		if($query_condition['carrier_type'] != 1 && $query_condition['carrier_type'] != 2 && $query_condition['carrier_type'] != 3){
			$query_condition['carrier_type'] = 2;
		}
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
		$query_condition['warehouse_and_shippingservice'] = '(default_warehouse_id > -1 and default_shipping_method_code <> "")';
		$carrierQtips = CarrierOpenHelper::getCarrierQtips();
		$search = array(
				'is_comment_status'=>'???????????????',
				'no_print_carrier'=>'?????????????????????',
				'print_carrier'=>'?????????????????????',
		);
		return $this->render('waitingpost',
				[
						'search'=>$search,
						'carrierQtips'=>$carrierQtips,
						]+self::getlist($query_condition));
	}
	/**
	 +----------------------------------------------------------
	 * excel???????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		zwd 		2016/03/14				?????????
	 +----------------------------------------------------------
	 **/
	public function actionExcelexported(){
		AppTrackerApiHelper::actionLog("eagle_v2", "/carrier/Carrierprocess/Excelexported");
		$query_condition = $_REQUEST;
		$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
		//???????????????
		$query_condition['carrier_type'] = $_REQUEST['carrier_type'] = isset($_REQUEST['carrier_type'])?$_REQUEST['carrier_type']:2;
		if($query_condition['carrier_type'] != 1 && $query_condition['carrier_type'] != 2 && $query_condition['carrier_type'] != 3){
			$query_condition['carrier_type'] = 2;
		}
		//??????????????????
		if(!isset($_REQUEST['carrier_step']) || trim($_REQUEST['carrier_step'])==''){
			$query_condition['carrier_step']=$_REQUEST['carrier_step'] = 'DELIVERY';
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
		$query_condition['warehouse_and_shippingservice'] = '(default_warehouse_id > -1 and default_shipping_method_code <> "")';
		$carrierQtips = CarrierOpenHelper::getCarrierQtips();
		$search = array(
				'is_comment_status'=>'???????????????',
				'no_print_carrier'=>'?????????????????????',
				'print_carrier'=>'?????????????????????',
		);
		return $this->render('waitingpost',
				[
						'search'=>$search,
						'carrierQtips'=>$carrierQtips,
						]+self::getlist($query_condition));
	}
	/**
	 +----------------------------------------------------------
	 * excel???????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		zwd 		2016/03/14				?????????
	 +----------------------------------------------------------
	 **/
	public function actionExcelcompleted(){
		AppTrackerApiHelper::actionLog("eagle_v2", "/carrier/Carrierprocess/Excelcompleted");
		$query_condition = $_REQUEST;
		//$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
		//???????????????
		$query_condition['carrier_type'] = $_REQUEST['carrier_type'] = isset($_REQUEST['carrier_type'])?$_REQUEST['carrier_type']:2;
		if($query_condition['carrier_type'] != 1 && $query_condition['carrier_type'] != 2 && $query_condition['carrier_type'] != 3){
			$query_condition['carrier_type'] = 2;
		}
		//??????????????????
		if(!isset($_REQUEST['carrier_step']) || trim($_REQUEST['carrier_step'])==''){
			$query_condition['carrier_step']=$_REQUEST['carrier_step'] = 'FINISHED';
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
		$query_condition['warehouse_and_shippingservice'] = '(default_warehouse_id > -1 and default_shipping_method_code <> "")';
		$carrierQtips = CarrierOpenHelper::getCarrierQtips();
		$search = array(
				'is_comment_status'=>'???????????????',
				'no_print_carrier'=>'?????????????????????',
				'print_carrier'=>'?????????????????????',
		);
		return $this->render('waitingpost',
				[
						'search'=>$search,
						'carrierQtips'=>$carrierQtips,
						]+self::getlist($query_condition));
	}
	/**
	 +----------------------------------------------------------
	 * ???????????????   ???????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		zwd 		2016/03/14				?????????
	 +----------------------------------------------------------
	 **/
	public function actionTracknoExport(){
		AppTrackerApiHelper::actionLog("eagle_v2", "/carrier/Carrierprocess/TracknoExport");
		$query_condition = $_REQUEST;
		$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
		//???????????????
		$query_condition['carrier_type'] = $_REQUEST['carrier_type'] = isset($_REQUEST['carrier_type'])?$_REQUEST['carrier_type']:3;
		if($query_condition['carrier_type'] != 1 && $query_condition['carrier_type'] != 2 && $query_condition['carrier_type'] != 3){
			$query_condition['carrier_type'] = 3;
		}
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
		$query_condition['warehouse_and_shippingservice'] = '(default_warehouse_id > -1 and default_shipping_method_code <> "")';
		$carrierQtips = CarrierOpenHelper::getCarrierQtips();
		$search = array(
				'is_comment_status'=>'???????????????',
				'no_print_carrier'=>'?????????????????????',
				'print_carrier'=>'?????????????????????',
		);
		return $this->render('waitingpost',
				[
						'search'=>$search,
						'carrierQtips'=>$carrierQtips,
						]+self::getlist($query_condition));
	}
	/**
	 +----------------------------------------------------------
	 * ???????????????   ???????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		zwd 		2016/03/14				?????????
	 +----------------------------------------------------------
	 **/
	public function actionTracknoExported(){
		AppTrackerApiHelper::actionLog("eagle_v2", "/carrier/Carrierprocess/TracknoExported");
		$query_condition = $_REQUEST;
		$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
		//???????????????
		$query_condition['carrier_type'] = $_REQUEST['carrier_type'] = isset($_REQUEST['carrier_type'])?$_REQUEST['carrier_type']:3;
		if($query_condition['carrier_type'] != 1 && $query_condition['carrier_type'] != 2 && $query_condition['carrier_type'] != 3){
			$query_condition['carrier_type'] = 3;
		}
		//??????????????????
		if(!isset($_REQUEST['carrier_step']) || trim($_REQUEST['carrier_step'])==''){
			$query_condition['carrier_step']=$_REQUEST['carrier_step'] = 'DELIVERY';
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
		$query_condition['warehouse_and_shippingservice'] = '(default_warehouse_id > -1 and default_shipping_method_code <> "")';
		$carrierQtips = CarrierOpenHelper::getCarrierQtips();
		$search = array(
				'is_comment_status'=>'???????????????',
				'no_print_carrier'=>'?????????????????????',
				'print_carrier'=>'?????????????????????',
		);
		return $this->render('waitingpost',
				[
						'search'=>$search,
						'carrierQtips'=>$carrierQtips,
						]+self::getlist($query_condition));
	}
	/**
	 +----------------------------------------------------------
	 * ???????????????   ???????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		zwd 		2016/03/14				?????????
	 +----------------------------------------------------------
	 **/
	public function actionTracknoCompleted(){
		AppTrackerApiHelper::actionLog("eagle_v2", "/carrier/Carrierprocess/TracknoCompleted");
		$query_condition = $_REQUEST;
		//$query_condition['order_status'] = OdOrder::STATUS_WAITSEND;//?????????
		//???????????????
		$query_condition['carrier_type'] = $_REQUEST['carrier_type'] = isset($_REQUEST['carrier_type'])?$_REQUEST['carrier_type']:3;
		if($query_condition['carrier_type'] != 1 && $query_condition['carrier_type'] != 2 && $query_condition['carrier_type'] != 3){
			$query_condition['carrier_type'] = 3;
		}
		//??????????????????
		if(!isset($_REQUEST['carrier_step']) || trim($_REQUEST['carrier_step'])==''){
			$query_condition['carrier_step']=$_REQUEST['carrier_step'] = 'FINISHED';
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
		$query_condition['warehouse_and_shippingservice'] = '(default_warehouse_id > -1 and default_shipping_method_code <> "")';
		$carrierQtips = CarrierOpenHelper::getCarrierQtips();
		$search = array(
				'is_comment_status'=>'???????????????',
				'no_print_carrier'=>'?????????????????????',
				'print_carrier'=>'?????????????????????',
		);
		return $this->render('waitingpost',
				[
						'search'=>$search,
						'carrierQtips'=>$carrierQtips,
						]+self::getlist($query_condition));
	}
	/**
	 * ??????????????????
	 * @description  
	 * 	step 1. ????????????
	 *  step 2. ?????????
	 *  step 3. ?????????????????????
	 *  step 4. ??????????????????
	 * @return json ???????????????
	 * auth: Mei Liang
	 */
	public function actionSetfinished(){
		if (\Yii::$app->request->isPost){
			$orderids = Yii::$app->request->post('orderids');
		}else{
			$orderids = Yii::$app->request->get('order_id');
		}
		Helper_Array::removeEmpty($orderids);
		if (count($orderids)==0){
			return json_encode(array('success'=>false,'message'=>TranslateHelper::t('??????????????????')));
		}
		$orders=OdOrder::find()->where(['order_id'=>$orderids])->all();
		
		//???????????????????????????
		$is_manual_tracking_no = false;
		
		//????????????code
		$tmp_shipping_method_code = array();
		
		//??????????????????????????????EUB ??????????????????????????????????????????????????????
		foreach ($orders as $order){
			if($order->default_carrier_code == 'lb_epacket'){
				if($order->carrier_step == OdOrder::CARRIER_WAITING_DELIVERY){
					return json_encode(array('success'=>false,'message'=>'??????'.$order->order_id.' ????????????EUB??????????????????????????????????????????!'));
				}
			}
			
			$tmp_shipping_method_code[] = $order->default_shipping_method_code;
			
			if($order->default_shipping_method_code == 'manual_tracking_no'){
				$is_manual_tracking_no = true;
			}
		}
		
		//?????????????????????????????????????????????????????????????????????ebay,amazon Start
		$tmp_tracking_upload_config = 0;
		if(count($tmp_shipping_method_code) > 0){
			$tmp_SysShippingService = SysShippingService::find()->select(['id','tracking_upload_config'])->where(['id'=>$tmp_shipping_method_code])->asArray()->all();
			
			if(count($tmp_SysShippingService) > 0){
				foreach ($tmp_SysShippingService as $tmp_SysShippingServiceKey => $tmp_SysShippingServiceVal){
					$tmp_SysShippingService[$tmp_SysShippingServiceKey]['tracking_upload_config'] = json_decode($tmp_SysShippingServiceVal['tracking_upload_config'], true);
				}
			}
		}
		
		if($is_manual_tracking_no == false){
			if(empty($tmp_SysShippingService)){
				return json_encode(array('success'=>false,'message'=>'??????'.$order->order_id.' ????????????????????????,???????????????????????????????????????!'));
			}
		
			$tmp_SysShippingService2 = $tmp_SysShippingService;
			unset($tmp_SysShippingService);
			$tmp_SysShippingService = array();
			foreach ($tmp_SysShippingService2 as $tmp_SysShippingService2Val){
				$tmp_SysShippingService[$tmp_SysShippingService2Val['id']] = $tmp_SysShippingService2Val['tracking_upload_config'];
			}
			
			foreach ($orders as $order){
				if(in_array($order->order_source, array('ebay','amazon'))){
					if(empty($tmp_SysShippingService[$order->default_shipping_method_code][$order->order_source])){
						$tmp_tracking_upload_config = 0;
					}else{
						$tmp_tracking_upload_config = $tmp_SysShippingService[$order->default_shipping_method_code][$order->order_source];
					}
				}
			}
		}
		//?????????????????????????????????????????????????????????????????????ebay,amazon End
		
		
		// dzt20160812 for ??????????????????????????????????????????
		$allOrders = array();
		$mergeFSOrderIdMap = array();
		$mergeSFOrderIdMap = array();
		$mergeOrderShipInfo = array();
		$successSignShipFMOrder = array();
		$stock_order = array();    //??????????????????order??????
		// @todo ???????????????tracking ?????????????????????????????????
		foreach ($orders as $order){
			if('sm' == $order->order_relation){// ??????????????????????????????
				$orderRels = OrderRelation::findAll(['son_orderid'=>$order->order_id , 'type'=>'merge' ]);
				foreach ($orderRels as $orderRel){
					$originOrder = OdOrder::findOne($orderRel->father_orderid);
					$mergeFSOrderIdMap[$originOrder->order_id] = $order->order_id;
					$mergeSFOrderIdMap[$order->order_id][] = $originOrder->order_id;
					$allOrders[] = $originOrder;
				}
				
				
				$default_value = [
				'order_source'=>$order->order_source,
				'selleruserid'=>$order->selleruserid,
				'tracking_number'=>'',
				'tracking_link'=>'',
				'shipping_method_code'=>'',
				'shipping_method_name'=>'',
				'order_source_order_id'=>$order->order_source_order_id,
				'description'=>'',
				'signtype'=>'',
				'addtype'=>'??????????????????',
				];
				$odship = OdOrderShipped::find()->where(['order_id'=>$order->order_id,'status'=>0])->orderBy('id DESC')->one();
				if ($odship==null){
					$logisticInfoList=['0'=>$default_value];
				}else{
					$tmp_arr = array();
					foreach ($default_value as $k=>$v){
						$tmp_arr[$k]=$odship[$k];
					}
					$logisticInfoList=['0'=>$tmp_arr];
				}
				
				$mergeOrderShipInfo[$order->order_id] = $logisticInfoList;
			}else{
				$allOrders[] = $order;
			}
			
			$stock_order[] = $order;
		}
		$message = "";
		$checkReport = '';
		foreach ($allOrders as $order){
			$old = $order->order_status;
			
			######################################??????????????????begin#############################################
			//??????????????????????????????
			$no_auto_mark_shipment = json_decode(ConfigHelper::getConfig('no_auto_mark_shipment'));
			$no_auto_mark_shipment = empty($no_auto_mark_shipment)?OdOrder::$no_autoShippingPlatform:$no_auto_mark_shipment;
			if ( ! in_array($order->order_source, $no_auto_mark_shipment) && $order->order_relation!='ss'){
				//step 1  ????????????
				try {
					//????????????????????????
					$is_shipped = true;
					//1????????????????????????????????????
					$condition1 = false;
					if ($order->shipping_status >0  || $order->delivery_time > 0){
						$condition1 = true;
					}
					//2shipped??????status=1???????????????????????????????????????????????????????????????
					$condition2 = false;
					$count = OdOrderShipped::find()->where(['order_id'=>$order->order_id,'status'=>1])->count();
					if ($count>0){
						$condition2 = true;
					}
					//3???????????????????????????????????????,???????????????????????????????????????		?????????????????????selleruserid?????????????????????????????????PM?????????????????????????????????????????????
					$condition3 = false;
					$count2 = QueueSyncshipped::find()->where(['order_source_order_id'=>$order->order_source_order_id,'selleruserid'=>$order->selleruserid])->count();
					if ($count2 > 0 ){
						$condition3 = true;
					}
					
					//????????????????????????????????????????????????API?????????????????????????????????????????? lrq20180402
					if($order->order_source == 'aliexpress' && $order->order_source_status == 'SELLER_PART_SEND_GOODS'){
						$condition1 = false;
						$count = OdOrderShipped::find()->where(['order_id'=>$order->order_id, 'status'=>1, 'addtype' => '??????API'])->count();
						if ($count == 0){
							$condition2 = false;
						}
					}
					
					//??????????????????????????????????????????????????????????????????
					$default_value = [
					'order_source'=>$order->order_source,
					'selleruserid'=>$order->selleruserid,
					'tracking_number'=>'',
					'tracking_link'=>'',
					'shipping_method_code'=>'',
					'shipping_method_name'=>'',
					'order_source_order_id'=>$order->order_source_order_id,
					'description'=>'',
					'signtype'=>'',
					'addtype'=>'??????????????????',
					];
					if ($condition1 || $condition2 || $condition3){
// 						$odship = OdOrderShipped::find()->where(['order_id'=>$order->order_id,'status'=>0])->andWhere('length(tracking_number)>0')->orderBy('id DESC')->one();
// 						if ($odship!==null){
// 							$tmp_arr = array();
// 							foreach ($default_value as $k=>$v){
// 								$tmp_arr[$k]=$odship[$k];
// 							}
// 							$logisticInfoList=['0'=>$tmp_arr];
// 						}else{
// 							//??????????????????????????? ?????????unset $logisticInfoList ?????? saveTrackingNumber ????????? ???????????????????????????order_shipped ??????
// 							unset($logisticInfoList);
// 						}
						//???????????????????????????????????????????????????????????????
						unset($logisticInfoList);
					}else{//???????????????
						if('fm' == $order->order_relation){// dzt20160812 ????????????????????????shiping ???????????????????????????
							$smOrderId =  $mergeFSOrderIdMap[$order->order_id];
							$logisticInfoList = $mergeOrderShipInfo[$smOrderId];
						}else{
							$odship = OdOrderShipped::find()->where(['order_id'=>$order->order_id,'status'=>0])->orderBy('id DESC')->one();
							if ($odship==null){
								$logisticInfoList=['0'=>$default_value];
							}else{
								$tmp_arr = array();
								foreach ($default_value as $k=>$v){
									$tmp_arr[$k]=$odship[$k];
								}
								$logisticInfoList=['0'=>$tmp_arr];
							}
						}
					}
					if (isset($logisticInfoList)){
						
						//????????????amazon??????????????????????????????????????????????????????
						$tmp_auto_trackNum = 1;
						
						// ???????????? ?????? 
						if($order->order_source == 'cdiscount'){
							$checkRT = \eagle\modules\order\helpers\CdiscountOrderInterface::preCheckSignShippedInfo($logisticInfoList[0]['tracking_number'],$order->order_source_shipping_method, $logisticInfoList[0]['shipping_method_code'], $logisticInfoList[0]['shipping_method_name'], $logisticInfoList[0]['tracking_link']);
							if ($checkRT['success'] == false){
								$checkReport .= "<br> ????????????".$order->order_source_order_id." ???????????????". $checkRT['message'];
								return json_encode(array('success'=>false,'message'=>'E5 ??????'.$order->order_id.'??????????????????:'.$checkReport.'!'));
							}
						}else if(in_array($order->order_source, array('ebay', 'amazon'))){
							if(($tmp_tracking_upload_config == 0) && (empty($logisticInfoList[0]['tracking_number']))){
								return json_encode(array('success'=>false,'message'=>'E5 ??????'.$order->order_id.'??????????????????:'.' ???????????????????????????????????????,????????????????????????????????????->???????????????????????????????????????????????????'.'!'));
							}
							
							if(($order->order_source == 'amazon') && ($tmp_tracking_upload_config == 2) && (empty($logisticInfoList[0]['tracking_number']))){
								$tmp_auto_trackNum = 2;
							}
						}
						
						$success = OrderHelper::saveTrackingNumber($order->order_id,$logisticInfoList,0,$is_shipped, $tmp_auto_trackNum);
						if ($success){
							OrderApiHelper::unsetPlatformShippedTag($order->order_id);//????????????????????????
						}else{
							return json_encode(array('success'=>false,'message'=>'E1 ??????'.$order->order_id.'??????????????????!'));
						}
					}
				} catch (\Exception $e) {
					\Yii::error(__FUNCTION__." E2 failure to shipped order ".print_r($e->getMessage(),true),"file");
					return json_encode(array('success'=>false,'message'=>'E2 ??????'.$order->order_id.'??????????????????!'));
				}
			}
			######################################??????????????????end#############################################
			
			//???????????????????????????????????????????????????
			foreach($stock_order as $s_k => $s_order){
				//step 2 ?????????
				$rtn = \eagle\modules\inventory\helpers\InventoryApiHelper::OrderProductStockOut($s_order->order_id);
				if (isset($rtn['success'])&&$rtn['success']==false){
					//??????
					return json_encode(array('success'=>false,'message'=>'E3 ??????'.$s_order->order_id.$rtn['message']));
				}
				
				//step 3  ?????????????????????
				$warehouseid = $s_order->default_warehouse_id;
				if(empty($warehouseid))
					$warehouseid = 0;
				foreach($s_order->items as $item)
				{
				    if(!empty($item['root_sku'])){
    					//???????????????????????????
    					if(empty($item['delivery_status']) || $item['delivery_status'] != 'ban'){
    						$Qty = $item['quantity'];
    						$sku = $item['root_sku'];
							$rt = \eagle\modules\inventory\apihelpers\InventoryApiHelper::updateQtyOrdered($warehouseid, $sku, -$Qty );
							if($rt['status'] == 0)
							    return json_encode(array('success'=>false,'message'=>$s_order->order_id.' ??????????????????????????????'.$rt['msg']));
    					}
				    }
				}
				
				unset($stock_order[$s_k]);
			}
			
			$order->order_status = OdOrder::STATUS_SHIPPED;
			$order->carrier_step = OdOrder::CARRIER_FINISHED;
			
			//step 4 ??????????????????
			$order->complete_ship_time = time();
			if(!$order->save()){
				return json_encode(array('success'=>false,'message'=>'E4 ??????'.$order->order_id.'????????????????????????!'));
			}else{
				// dzt20160812 ????????????????????????????????????????????????????????????????????????????????????????????????????????????
				if(array_key_exists($order->order_id, $mergeFSOrderIdMap)){
					$successSignShipFMOrder[] = $order->order_id;
				}
				
				//??????dashboard??????????????????????????????
				OrderApiHelper::adjustStatusCount($order->order_source, $order->selleruserid, $order->order_status, $old, $order->order_id);
				
				OperationLogHelper::log('delivery', $order->order_id,'??????????????????','??????????????????,??????:'.OdOrder::$status[$old].'->'.OdOrder::$status[$order->order_status], \Yii::$app->user->identity->getFullName());
			}
			//????????????????????????
			if(in_array( $order->order_source, $no_auto_mark_shipment ) ){
// 				$message .= '<a href="'.Url::to(['/order/order/signshipped','order_id'=>$order->order_id]).'" target="_blank" class="alert-link">'.'??????'.$order->order_id.'?????????????????????????????????,??????:'.OdOrder::$status[$old].'->'.OdOrder::$status[$order->order_status].','.$order->order_source.'???????????????????????????????????????????????????????????????????????????!</a>';
				$message .= '<a class="alert-link">'.'??????'.$order->order_id.'?????????????????????????????????,??????:'.OdOrder::$status[$old].'->'.OdOrder::$status[$order->order_status].','.$order->order_source.'???????????????????????????????????????????????????????????????????????????!</a>';
			}else{
				$message .= '??????'.$order->order_id.'?????????????????????????????????,??????:'.OdOrder::$status[$old].'->'.OdOrder::$status[$order->order_status].';';
			}
			
			//??????????????????
			UserHelper::insertUserOperationLog('delivery', "??????????????????, ?????????: ".ltrim($order->order_id, '0'), null, $order->order_id);
		}//end of for each order
		
		// dzt20160812 ????????????????????????????????????????????????????????????????????????
// 		\Yii::info(__FUNCTION__." order:".$order->order_id.":".print_r($mergeSFOrderIdMap,true),"file");
// 		\Yii::info(__FUNCTION__." order:".$order->order_id.":".print_r($mergeFSOrderIdMap,true),"file");
// 		\Yii::info(__FUNCTION__." order:".$order->order_id.":".print_r($successSignShipFMOrder,true),"file");
		if(!empty($successSignShipFMOrder) && !empty($mergeSFOrderIdMap)){
			foreach ($mergeSFOrderIdMap as $smOrderId=>$fmOrderIds){
				$allSignSuccess = true;
				foreach ($fmOrderIds as $fmOrderId){
					if(!in_array($fmOrderId, $successSignShipFMOrder)){
						$allSignSuccess = false;
					}
				}
				
				if($allSignSuccess){
					$smOrder = OdOrder::findOne($smOrderId);
					$old = $smOrder->order_status;
					$smOrder->order_status = OdOrder::STATUS_SHIPPED;
					$smOrder->carrier_step = OdOrder::CARRIER_FINISHED;
					$smOrder->complete_ship_time = time();	//????????????????????????  20171009 hqw
					if(!$smOrder->save()){
						return json_encode(array('success'=>false,'message'=>'E4 ??????'.$smOrder->order_id.'????????????????????????!'));
					}else{
						OperationLogHelper::log('delivery', $smOrder->order_id,'??????????????????','??????????????????,??????:'.OdOrder::$status[$old].'->'.OdOrder::$status[$smOrder->order_status], \Yii::$app->user->identity->getFullName());
					}
					
					//????????????????????????
					if(in_array( $order->order_source, $no_auto_mark_shipment ) ){
// 						$message .= '<a href="'.Url::to(['/order/order/signshipped','order_id'=>$smOrder->order_id]).'" target="_blank" class="alert-link">'.'??????'.$smOrder->order_id.'?????????????????????????????????,??????:'.OdOrder::$status[$old].'->'.OdOrder::$status[$smOrder->order_status].','.$smOrder->order_source.'???????????????????????????????????????????????????????????????????????????!</a>';
						$message .= '<a class="alert-link">'.'??????'.$smOrder->order_id.'?????????????????????????????????,??????:'.OdOrder::$status[$old].'->'.OdOrder::$status[$smOrder->order_status].','.$smOrder->order_source.'???????????????????????????????????????????????????????????????????????????!</a>';
					}else{
						$message .= '??????'.$smOrder->order_id.'?????????????????????????????????,??????:'.OdOrder::$status[$old].'->'.OdOrder::$status[$smOrder->order_status];
					}
				}
			}
		}
		
		return json_encode(array('success'=>true,'message'=>$message));
	}
	/**
	 +----------------------------------------------------------
	 * ??????????????????????????? 
	 * 1??????????????????????????????
	 * 2???????????????????????????????????????????????????
	 * 3??????????????????????????????
	 * 
	 * ??????$is_query ?????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		zwd 		2016/03/14				?????????
	 +----------------------------------------------------------
	 **/
	public static function getlist($query_condition,$config='carrier/carrierprocess',$is_query = true, $uid = 0, $puid = false){
		//??????????????????????????????
		$query_condition['order_relation'] = ['normal','sm','fs','ss'];
		
		//?????????OMS?????????????????????????????????????????????????????????????????????????????????????????????
		if((!empty($query_condition['use_mode'])) && (empty($query_condition['selleruserid']))){
			$query_condition['selleruserid'] = $query_condition['use_mode'];
		}
		
		//?????????????????????????????????????????????????????????????????????????????????
		if(!empty($query_condition['selleruserid'])){
			if(isset(OdOrder::$orderSource[$query_condition['selleruserid']])){
				$query_condition['order_source'] = $query_condition['selleruserid'];
				unset($query_condition['selleruserid']);
			}
		}
		
		//?????????????????????????????????????????????????????????????????????????????????
		if(!empty($query_condition['selleruserid_combined'])){
			if(isset(OdOrder::$orderSource[$query_condition['selleruserid_combined']])){
				$query_condition['order_source'] = $query_condition['selleruserid_combined'];
				unset($query_condition['selleruserid_combined']);
			}
		}

		if($is_query == true){
			$tmp_selleruseridMap = PlatformAccountApi::getAllAuthorizePlatformOrderSelleruseridLabelMap($puid, $uid, true);//???????????????????????????
			$rt = \eagle\modules\order\apihelpers\OrderApiHelper::getOrderListByCondition($query_condition, $uid, array(), array('selleruserid_tmp'=>$tmp_selleruseridMap));
		}else{
			$rt = array('data'=>array(),'pagination'=>array());
		}

		$tmp_query_condition = $query_condition;
		
		if(!empty($query_condition['order_source'])){
			$query_condition['selleruserid'] = $query_condition['order_source'];
			unset($query_condition['order_source']);
		}
		
		$data = $rt['data'];
		$pagination = $rt['pagination'];
		#####################################1.???????????????????????????##################################################################
		//???????????????????????????
		if (isset($query_condition['showsearch'])){
			$showsearch = $query_condition['showsearch'];
		}else{
			$showsearch = 0;
		}
		######################################2.????????????#################################################################
		//????????????
		//$selleruseridMap = PlatformAccountApi::getAllPlatformOrderSelleruseridLabelMap(false, true);//???????????????????????????
		$selleruseridMap = PlatformAccountApi::getAllAuthorizePlatformOrderSelleruseridLabelMap(false, false, true, true);//???????????????????????????
		
		if(!empty($selleruseridMap['wish'])){
			$tmp_wishM = $selleruseridMap['wish'];
			unset($selleruseridMap['wish']);
			
			$selleruseridMap['wish'] = \eagle\modules\platform\apihelpers\WishAccountsApiHelper::getWishAliasAccount($tmp_wishM);
		}
		
		$selleruserids=array();
		foreach ($selleruseridMap as $platform =>$value){
			if (!empty($value)){
				//???????????????OMS??????????????????????????????????????????????????????
				if(!empty($query_condition['use_mode'])){
					if($query_condition['use_mode'] != $platform)
						continue;
				}
				$selleruserids[$platform] = $platform.'????????????';
				foreach ($value as $value_key => $value_one){
					$selleruserids[$value_key] = '--'.$value_one;
				}
			}
		}
		
		######################################3.???????????????#################################################################
		//???????????????
		$keys = [
				'order_source_order_id'=>'???????????????',
				'sku'=>'??????SKU',
				'order_source_itemid'=>'???????????????',
				'tracknum'=>'?????????',
				'source_buyer_user_id'=>'????????????',
				'consignee'=>'????????????',
				'consignee_email'=>'??????Email',
// 				'delivery_id'=>'????????????',
				'order_id'=>'???????????????',
				'root_sku'=>'??????SKU',
				'product_name'=>'????????????',
				'prod_name_ch'=>'???????????????',
		];
		########################################4.????????????###############################################################
		//????????????
		$custom_condition = ConfigHelper::getConfig($config);
		if (!empty($custom_condition) && is_string($custom_condition)){
			$custom_condition = json_decode($custom_condition,true);
		}
		if (!empty($custom_condition)){
			$sel_custom_condition = array_keys($custom_condition);
		}else{
			$sel_custom_condition =array();
		}
		###########################################5.??????############################################################
		//??????????????????
		$countrys = CountryHelper::getRegionCountry();
		$country_mapping = [];
		##########################################6.??????#############################################################
		//??????,????????????????????????????????????????????????????????????????????????????????????????????????????????????
		$warehouseIdNameMap = InventoryHelper::getWarehouseOrOverseaIdNameMap(-1, -1);
		##########################################7.?????????#############################################################
		//?????????????????????????????????
		$allcarriers = [];
		if(isset($query_condition['carrier_type']) && !empty($query_condition['carrier_type'])){
			if($query_condition['carrier_type'] == 1){//??????+?????????
				$allcarriers = CarrierApiHelper::getCarrierList(2,-1);
			}
			else if($query_condition['carrier_type'] == 2){//excel
				$allcarriers = CarrierApiHelper::getCarrierList(3,-1);
			}
			else if($query_condition['carrier_type'] == 3){//track
				$allcarriers = CarrierApiHelper::getCarrierList(4,-1);
			}
			else if($query_condition['carrier_type'] == 4){//??????
				$allcarriers = CarrierApiHelper::getCarrierList(1,-1);
			}
			else if($query_condition['carrier_type'] == 5){//??????
				$allcarriers = CarrierApiHelper::getCarrierList(0,-1);
			}
			else{
				$allcarriers = CarrierApiHelper::getCarrierList(6,-1);
			}
		}
		else{//???????????????
			$allcarriers = CarrierApiHelper::getCarrierList(6,-1);
		}
		##########################################8.??????????????????#############################################################
		$allshippingservices = CarrierApiHelper::getShippingServiceList(-1,-1);
		##########################################9.???????????????#############################################################
		//tag ????????????
		$allTagDataList = OrderTagHelper::getTagByTagID();
		$allTagList = [];
		foreach($allTagDataList as $tmpTag){
			$allTagList[$tmpTag['tag_id']] = $tmpTag['tag_name'];
		}
		##########################################10.???????????????excel#############################################################
		//???????????????excel
		$excelmodels = Helper_Array::toHashmap(Excelmodel::find()->select(['id','name'])->asArray()->all(), 'id','name');
		##########################################11.???????????????????????????????????????#############################################################
		$shippingServices = [];
		//????????????
		$countryArr = array();
		
		//???????????????????????????????????????
// 		$tmp_query_condition = $query_condition;
		if(isset($tmp_query_condition['default_shipping_method_code'])){
			unset($tmp_query_condition['default_shipping_method_code']);
		}
		//??????????????????????????????
		if(isset($tmp_query_condition['consignee_country_code'])){
			unset($tmp_query_condition['consignee_country_code']);
		}
		//??????????????????????????????
		if(isset($tmp_query_condition['selected_country_code'])){
			unset($tmp_query_condition['selected_country_code']);
		}
		
		//??????????????????groupBy
		$moreGroup = array('default_shipping_method_code','consignee_country_code','consignee_country');

		$shippingServicesList = OrderApiHelper::getShippingMethodCodeByCondition($tmp_query_condition, $moreGroup);
		
		foreach ($shippingServicesList as $one_ship){
			$code = @$one_ship['default_shipping_method_code'];
			$shippingServices[$code] = @$allshippingservices[$code];
			
			$countryArr[$one_ship['consignee_country_code']] = $one_ship['consignee_country'];
		}
		
		//?????????????????????
		if(!empty($countryArr)){
			foreach ($countryArr as $key => $value) {
				if (empty($value)) {
					unset($countryArr[$key]);
				}
			}
		}
		
		//ebay ????????????  check out ??????
		$orderCheckOutList = [];
		
		$OrderSourceOrderIdList=array();
		foreach ($data as $tmp_order){
			if($tmp_order->order_source == 'ebay'){
				$OrderSourceOrderIdList[] = $tmp_order->order_source_order_id;
			}
		}
		if(!empty($OrderSourceOrderIdList)){
			$orderCheckOutList = OrderApiHelper::getEbayCheckOutInfo($OrderSourceOrderIdList);
		}
		
		##########################################12.???????????????????????????????????????#############################################################
		//???????????????
		$printMode = [];
		if(isset($query_condition['default_shipping_method_code']) && !empty($query_condition['default_shipping_method_code'])){
			$printMode = CarrierOpenHelper::getShippingServicePrintMode($query_condition['default_shipping_method_code']);
		}
		##########################################13.??????????????????#############################################################
		$warehouseCount = Warehouse::find()->where('is_active = :is_active',[':is_active'=>'Y'])->count();
		return ['orders'=>$data,
				'pagination'=>$pagination,
				'showsearch'=>$showsearch,
				'selleruserids'=>$selleruserids,
				'keys'=>$keys,
				'custom_condition'=>$custom_condition,
				'sel_custom_condition'=>$sel_custom_condition,
				'countrys'=>$countrys,
				'country_mapping'=>$country_mapping,
				'warehouseIdNameMap'=>$warehouseIdNameMap,
				'allcarriers'=>$allcarriers,
				'allshippingservices'=>$allshippingservices,
				'shippingServices'=>$shippingServices,//???????????????????????????????????????
				'all_tag_list'=>$allTagList,
				'excelmodels'=>$excelmodels,
				'printMode'=>$printMode,
				'query_condition'=>$query_condition,
				'warehouseCount'=>$warehouseCount,
				'countryArr'=>$countryArr,
				'orderCheckOutList'=>$orderCheckOutList
				];
	}
	/**
	 +----------------------------------------------------------
	 * api????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		zwd 		2016/03/14				?????????
	 +----------------------------------------------------------
	 **/
	public function actionDoprintapi(){
		if(isset($_GET['orders'])&&!empty($_GET['orders'])){
			$orders	=	$_GET['orders'];
		}else{
			echo "???????????????????????????(send none order)";die;
		}
		$is_generate_pdf = empty($_GET['is_generate_pdf']) ? 0 : 1;  //????????????pdf
		
		//???????????????????????? S
		$order_md5_str = md5($orders);
		
		$user=\Yii::$app->user->identity;
		$puid = $user->getParentUid();
		
		$classification = "Tracker_AppTempData";
		$key = date("Y-m-d").'_print_api_'.$puid.'_'.$order_md5_str;
		
		$lastSetCarrierTypeTime = RedisHelper::RedisGet($classification, $key);
		
		if(!empty($lastSetCarrierTypeTime) && ((time()-(int)$lastSetCarrierTypeTime) < 5)){
			$tmp_result = array();
			$tmp_result['result'] = array('error' => 1, 'data' => '', 'msg' => '????????????????????????????????????e1');;
			$tmp_result['carrier_name'] = "";
		
			return $this->render('doprint2',['data'=>$tmp_result]);
		}
		
		RedisHelper::RedisSet($classification, $key, time());
		//???????????????????????? E
		
		$orders = rtrim($orders,',');
		$tmp_orderlist = OdOrder::find()->where("order_id in ({$orders})")->all();
// 		$orderlist
		
		$order_arr = explode(',', $orders);
		
		//????????????
		$orderlist = array();
			
		foreach ($order_arr as $order_arr_one){
			foreach ($tmp_orderlist as $tmp_orderlist_one){
				if($order_arr_one == $tmp_orderlist_one['order_id']){
					$orderlist[] = $tmp_orderlist_one;
					break;
				}
			}
		}
		
		//???????????????????????????
		$carrier = SysCarrier::findOne($orderlist[0]['default_carrier_code']);
		if(empty($carrier)){
			echo "can't find this carrier";die;
		}
		if($carrier->carrier_type){
			$class_name = '\common\api\overseaWarehouseAPI\\'.$carrier->api_class;
		}else{
			$class_name = '\common\api\carrierAPI\\'.$carrier->api_class;
		}
		$arr = array();
		 
		//??????????????????????????????
		foreach($orderlist as $v){
			$arr[]['order']=$v;
		}
		 
		if($carrier->api_class == 'LB_RTBCOMPANYCarrierAPI'){
			//???????????????????????????
			$interface = new $class_name($carrier->carrier_code);
		}
		else{
			$interface = new $class_name;
		}
		 
		$result['result'] = $interface->doPrint($arr);
		 
		if(isset($_GET['ems'])&&!empty($_GET['ems'])){
			$result['carrier_name'] = $_GET['ems'];
		}else{
			$result['carrier_name'] = "";
		}
		
		//??????log
		\Yii::info('actionDoprintapi,puid:'.$puid.',order_ids:'.$orders.' '.json_encode($result),"carrier_api");
		
		//?????????pdf
		if($is_generate_pdf && !empty($orderlist[0]['order_id']) && !empty($result['result']['data']['pdfUrl'])){
			$uid = \Yii::$app->user->id;
			$key = $uid.'_'.ltrim($orderlist[0]['order_id'],'0');
		
			$redis_val['url'] = $result['result']['data']['pdfUrl'];
			$redis_val['carrierName'] = $carrier->carrier_name;
			$redis_val['time'] = time();
			RedisHelper::RedisSet('CsPrintPdfUrl', $key, json_encode($redis_val));
		
			return [];
		}
		
		$this->layout = 'carrier';
		if(in_array($carrier->api_class, ['LB_IEUBNewCarrierAPI','LB_WISHYOUCarrierAPI','LB_LINLONGCarrierAPI','LB_DONGGUANEMSCarrierAPI'])){
			if($result['result']['error']){
				return $this->render('doprint2',['data'=>$result]);
			}else{
				//??????EUB?????????????????????????????????Headers???????????????Frame??????
				if($carrier->api_class == 'LB_IEUBNewCarrierAPI'){
					usleep(600000);
				}else{
					usleep(300000);
				}
				$this->redirect($result['result']['data']['pdfUrl']);
			}
		}
		else if(in_array($carrier->api_class, ['LB_AIPAQICarrierAPI'])){
			if($result['result']['error']){
				return $this->render('doprint2',['data'=>$result]);
			}else{
				print_r($result['result']['data']);
			}
		}
		else if(in_array($carrier->api_class, ['LB_ANJUNCarrierAPI','LB_YITONGGUANCarrierAPI','LB_TAIJIACarrierAPI']) && !empty($result['result']['data']['type'])){
			if($result['result']['error']){
				return $this->render('doprint2',['data'=>$result]);
			}else{
				//??????EUB?????????????????????????????????Headers???????????????Frame??????
				usleep(300000);
				$this->redirect($result['result']['data']['pdfUrl']);
			}
		}
		else{
			return $this->render('doprint2',['data'=>$result]);
		}
	}
	/**
	 +----------------------------------------------------------
	 * ?????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		zwd 		2016/03/14				?????????
	 +----------------------------------------------------------
	 **/
	public function actionDoprintcustom(){
		if(isset($_GET['orders'])&&!empty($_GET['orders'])){
			$orders	=	$_GET['orders'];
		}else{
			echo "???????????????????????????(send none order)";die;
		}
		$is_generate_pdf = empty($_GET['is_generate_pdf']) ? 0 : 1;  //????????????pdf
		
		$orders = rtrim($orders,',');
		$orderlist	=	OdOrder::find()->where("order_id in ({$orders})")->all();
		
		$carrierConfig = ConfigHelper::getConfig("CarrierOpenHelper/CommonCarrierConfig", 'NO_CACHE');
		$carrierConfig = json_decode($carrierConfig,true);
		
		if(!isset($carrierConfig['label_paper_size'])){
			$carrierConfig['label_paper_size'] = array('val' => '100x100','template_width' => 100,'template_height' => 100);
		}
		 
		$printType = '100x100';
		if($carrierConfig['label_paper_size']['val'] == '210x297'){
			$printType = 'A4';
		}
		 
		AppTrackerApiHelper::actionLog("eagle_v2","/carrier/carrieroperate/doprint2");
		$puid = \Yii::$app->user->identity->getParentUid();
		$orders = rtrim($orders,',');
		$orderlist	=	OdOrder::find()->where("order_id in ({$orders})")->all();
		if(empty($orderlist)){
			echo "can't find this order";die;
		}
		$shippingServece_obj = SysShippingService::findOne(['id'=>$orderlist[0]['default_shipping_method_code']]);
		if(empty($shippingServece_obj)){
			echo "can't find this shippingservice";die;
		}
		
// 		if(empty($shippingServece_obj->custom_template_print)){
// 			$shippingServece_obj->custom_template_print = empty($shippingServece_obj->print_params['label_custom']) ? array() : $shippingServece_obj->print_params['label_custom'];
// 		}

		if(!empty($shippingServece_obj->print_params['label_custom'])){
			$shippingServece_obj->custom_template_print = $shippingServece_obj->print_params['label_custom'];
		}
		
		if(empty($shippingServece_obj->custom_template_print)){
			echo "?????????????????????????????????";die;
		}
		
		$pageHeight = 100;
		$pageWidth = 100;
		
		foreach ($shippingServece_obj->custom_template_print as $type=>$one){
			if (empty($one)){continue;}
			$tmp_template = CrTemplate::findOne($one);
			
			if(count($tmp_template) > 0){
				$pageHeight = $tmp_template['template_height'];
				$pageWidth = $tmp_template['template_width'];
			}
		}
		
// 		$this->layout = 'carrier';
		$this->layout='/mainPrint';
		$html = $this->render('doprintcustom',['data'=>$orderlist,'shippingService'=>$shippingServece_obj,'carrierConfig'=>$carrierConfig]);
		$result = Helper_Curl::post("http://120.55.86.71/wkhtmltopdf.php",['html'=>$html,'uid'=>$puid,'pringType'=>$printType,'pageHeight'=>$pageHeight,'pageWidth'=>$pageWidth]);// ???A4???????????????
		if(false !== $result){
			$rtn = json_decode($result,true);
			//     			print_r($rtn) ;
			if(1 == $rtn['success']){
				$response = Helper_Curl::get($rtn['url']);
				$pdfUrl = \eagle\modules\carrier\helpers\CarrierAPIHelper::savePDF($response, $puid, md5("wkhtmltopdf")."_".time());
				
				//?????????pdf url???redis
				if($is_generate_pdf && !empty($orderlist[0]['order_id'])){
					$uid = \Yii::$app->user->id;
					$key = $uid.'_'.ltrim($orderlist[0]['order_id'],'0');
					$redis_val['url'] = $pdfUrl;
					$redis_val['carrierName'] = $shippingServece_obj->carrier_name;
					$redis_val['time'] = time();
					RedisHelper::RedisSet('CsPrintPdfUrl', $key, json_encode($redis_val));
						
					return [];
				}
				
				$this->redirect($pdfUrl);
			}else{
				return "??????????????????????????????????????????";
			}
		}else{
			return "?????????????????????????????????????????????????????????";
		}
	}
	/**
	 +----------------------------------------------------------
	 * ??????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		zwd 		2016/03/14				?????????
	 +----------------------------------------------------------
	 **/
	public function actionDoprint(){
		$do_custom_print = false;
		if(isset($_GET['orders'])&&!empty($_GET['orders'])){
			$orders	=	$_GET['orders'];
		}else{
			echo "???????????????????????????(send none order)";die;
		}
		 
		$carrierConfig = ConfigHelper::getConfig("CarrierOpenHelper/CommonCarrierConfig", 'NO_CACHE');
		$carrierConfig = json_decode($carrierConfig,true);
		
		if(!isset($carrierConfig['label_paper_size'])){
			$carrierConfig['label_paper_size'] = array('val' => '100x100','template_width' => 100,'template_height' => 100);
		}
		 
		$printType = '100x100';
		if($carrierConfig['label_paper_size']['val'] == '210x297'){
			$printType = 'A4';
		}
		 
		AppTrackerApiHelper::actionLog("eagle_v2","/carrier/carrieroperate/doprint2");
		$puid = \Yii::$app->user->identity->getParentUid();
		$orders = rtrim($orders,',');
		$orderlist	=	OdOrder::find()->where("order_id in ({$orders})")->all();
		$shippingServece_obj = SysShippingService::findOne(['id'=>$orderlist[0]['default_shipping_method_code']]);
		if(empty($shippingServece_obj)){
			echo "can't find this shippingservice";die;
		}
		if ($shippingServece_obj->is_custom==0 && $do_custom_print==false){
			$lable_type = array('label_address'=>'?????????','label_declare'=>'?????????','label_items'=>'?????????');

			$sysShippMethods = SysShippingMethod::find()->where(['carrier_code'=>$shippingServece_obj->carrier_code,'shipping_method_code'=>$shippingServece_obj->shipping_method_code])->one();
			
			$sysShippingMethod['print_params'] = $shippingServece_obj->print_params['label_littleboss'];
			
			$print_params = array();
			 
			//?????????shipping_method_id???0???????????????????????????0?????????????????????
			$print_params['shipping_method_id'] = array($sysShippMethods->id, '0');
// 			$print_params['lable_type'] = $shippingServece_obj->print_params['label_littleboss'];
			$templateArr = array();
			$tmpLabel = array();
			
			foreach ($shippingServece_obj->print_params['label_littleboss'] as $print_paramone){
				if(in_array($print_paramone, $sysShippingMethod['print_params'])){
					$print_params['lable_type'][$print_paramone] = $lable_type[$print_paramone];
					$templateArr[$print_paramone] = '';
					$tmpLabel[] = $lable_type[$print_paramone];
				}
			}
			
			//xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
// 			$print_params['shipping_method_id'][0] = 2209;
			
			$templateAll = CrCarrierTemplate::find()
			->where(['carrier_code'=>$shippingServece_obj->carrier_code,'shipping_method_id'=>$print_params['shipping_method_id'],'template_type'=>$tmpLabel,'is_use'=>1])
			->orderBy('template_type,country_codes desc,shipping_method_id desc')->all();
			
			foreach ($print_params['lable_type'] as $print_paramkey => $print_paramval){
				foreach ($templateAll as $templateAllone){
					if($print_paramval == $templateAllone['template_type']){
						if(empty($templateAllone['country_codes'])){
							$templateArr[$print_paramkey] = $templateAllone;
							break;
						}else
						if (strpos($templateAllone['country_codes'], $orderlist[0]->consignee_country_code) !== false){
							$templateArr[$print_paramkey] = $templateAllone;
							break;
						}
					}
				}
			}
			
			//???????????????????????????,???????????????????????????????????????????????????
			if(isset($templateArr['label_items'])){
				if(empty($templateArr['label_items'])){
					$templateArr['label_items'] = CrCarrierTemplate::find()
					->where(['template_name'=>'ST?????????10cm??10cm','shipping_method_id'=>0,'template_type'=>'?????????','is_use'=>1])->one();
				}
			}
			
			$tmpIsCustom = true;
			foreach ($templateArr as $tmp1){
				if(empty($tmp1)){
					$tmpIsCustom = false;
				}
			}
			}
			
			if(($shippingServece_obj->print_type == 1) && (!empty($sysShippingMethod)) && (!empty($shippingServece_obj->print_params)) && ($tmpIsCustom)){
				//     			return $this->render('doprinthighcopy',['data'=>$orderlist,'shippingService'=>$shippingServece_obj,'carrierConfig'=>$carrierConfig,'print_params'=>$print_params,'templateArr'=>$templateArr]);
// 				$this->layout = 'carrier';
				$this->layout='/mainPrint';
				$html = $this->render('doprinthighcopy',['data'=>$orderlist,'shippingService'=>$shippingServece_obj,'carrierConfig'=>$carrierConfig,'print_params'=>$print_params,'templateArr'=>$templateArr]);
				$result = Helper_Curl::post("http://120.55.86.71/wkhtmltopdf.php",['html'=>$html,'uid'=>$puid,'pringType'=>$printType]);// ???A4???????????????
				if(false !== $result){
					$rtn = json_decode($result,true);
					// 					echo $html;
					if(1 == $rtn['success']){
						$response = Helper_Curl::get($rtn['url']);
						$pdfUrl = \eagle\modules\carrier\helpers\CarrierAPIHelper::savePDF($response, $puid, md5("wkhtmltopdf")."_".time());
						$this->redirect($pdfUrl);
					}else{
						return "??????????????????????????????????????????";
					}
				}else{
					return "?????????????????????????????????????????????????????????";
				}
			}else{
			}
	}
	/**
	 +----------------------------------------------------------
	 * ??????????????????????????????   action
	 * @param $_REQUEST['configPath'] ??????????????????????????????????????????
	 * 
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		zwd 	2016/2/22				?????????
	 +----------------------------------------------------------
	 **/
	public function actionAppendCustomCondition(){
		$config = "carrier/carrierprocess";
		if(isset($_REQUEST['configPath']) && !empty($_REQUEST['configPath']) && $_REQUEST['configPath'] != 'undefined'){
			$config = $_REQUEST['configPath'];
		}
		$conditionList = ConfigHelper::getConfig($config);
	
		if (is_string($conditionList)){
			$conditionList = json_decode($conditionList,true);
		}
	
		if (empty($conditionList)) $conditionList = [];
	
		if (array_key_exists($_REQUEST['custom_name'],$conditionList)){
			exit(json_encode(['success'=>false , 'message'=>$_REQUEST['custom_name'].'???????????????????????????????????????']));
		}else{
			$params = $_REQUEST;
			unset($params['custom_name']);
			unset($params['order_id']);
			unset($params['sel_custom_condition']);
			foreach($params as $key=>$value){
				if (!empty($value))
					$conditionList[$_REQUEST['custom_name']][$key] = $value;
			}
			//$conditionList[$_REQUEST['custom_name']] = $params;
		}
	
		ConfigHelper::setConfig($config, json_encode($conditionList));
		exit(json_encode(['success'=>true , 'message'=>'']));
	}//end of actionAppendCustomCondition
	
	/**
	 +----------------------------------------------------------
	 * ?????????????????????
	 *
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw 	2016/03/21				?????????
	 +----------------------------------------------------------
	 **/
	public function actionDoprintIntegrationLabel(){
		if(isset($_GET['orders'])&&!empty($_GET['orders'])){
			$orders	=	$_GET['orders'];
		}else{
			echo "???????????????????????????";die;
		}
		
		$timeMS1 = TimeUtil::getCurrentTimestampMS();
	
		AppTrackerApiHelper::actionLog("eagle_v2","/carrier/carrieroperate/doprint-saitu");
		$puid = \Yii::$app->user->identity->getParentUid();
		$orders = rtrim($orders,',');
		$orderlists = OdOrder::find()->where("order_id in ({$orders})")->orderBy('default_carrier_code,default_shipping_method_code')->asArray()->all();
		
		$timeMS2 = TimeUtil::getCurrentTimestampMS();
		
		//???????????????????????????
		$is_aliexpress_carrier = false;
		
		//????????????????????????1??????????????????????????????S
		$tmpOrderID = array();
		foreach ($orderlists as $orderlist){
			$tmpOrderID[] = $orderlist['order_id'];
			
			if($orderlist['default_carrier_code'] == 'lb_alionlinedelivery'){
				$is_aliexpress_carrier = true;
			}
		}
		if(count($tmpOrderID) > 0){
			$exResult = \eagle\modules\carrier\helpers\CarrierAPIHelper::updateAbnormalCarrierLabel($puid, $tmpOrderID);
		}
		//????????????????????????1??????????????????????????????E
		
		$timeMS3 = TimeUtil::getCurrentTimestampMS();
		
		//????????????PDF?????????????????????????????????????????????
		$orderUnCarrierLabelLists = CarrierUserLabel::find()->select(['id'])->where(['uid'=>$puid,'run_status'=>array(0,3,4)])->andWhere("order_id in ({$orders})")->asArray()->all();
		
		$timeMS4 = TimeUtil::getCurrentTimestampMS();
		
		if(count($orderUnCarrierLabelLists) > 0){
			$rtn = \eagle\modules\carrier\helpers\CarrierAPIHelper::getCarrierLabelApiAndItemsByNow($orderUnCarrierLabelLists);
		}
		
		$timeMS5 = TimeUtil::getCurrentTimestampMS();
			
		$orderCarrierLabelLists = CarrierUserLabel::find()->where(['uid'=>$puid,'run_status'=>2])->andWhere("order_id in ({$orders})")->asArray()->all();
			
		$timeMS6 = TimeUtil::getCurrentTimestampMS();
		
		$tmpPdfArr = array();
			
		foreach ($orderlists as $orderlist){
			foreach($orderCarrierLabelLists as $orderCarrierLabelList){
				if(($orderlist['order_id'] == $orderCarrierLabelList['order_id']) && ($orderlist['customer_number'] == $orderCarrierLabelList['customer_number'])){
					$tmpPdfArr[] = \eagle\modules\carrier\helpers\CarrierAPIHelper::getPdfPathString(false).$orderCarrierLabelList['merge_pdf_file_path'];
				}
			}
		}
		
		$timeMS7 = TimeUtil::getCurrentTimestampMS();
			
		$result = ['error'=>1,'data'=>'','msg'=>''];
			
		if((!empty($tmpPdfArr)) && (count($orderlists) == count($tmpPdfArr))){
			if(count($tmpPdfArr) == 1){
				$pdfmergeResult['success'] = true;
				$url = Yii::$app->request->hostinfo.str_replace(\eagle\modules\carrier\helpers\CarrierAPIHelper::getPdfPathString(false), "", $tmpPdfArr[0]);
			}else{
				$pathPDF = \eagle\modules\carrier\helpers\CarrierAPIHelper::createCarrierLabelDir();
				$tmpName = $puid.'_summerge_'.rand(10,99).time().'.pdf';
				$pdfmergeResult = PDFMergeHelper::PDFMerge($pathPDF.'/'.$tmpName , $tmpPdfArr);
					
				$url = Yii::$app->request->hostinfo.str_replace(\eagle\modules\carrier\helpers\CarrierAPIHelper::getPdfPathString(false), "", $pathPDF).'/'.$tmpName;
			}
			
			$timeMS8 = TimeUtil::getCurrentTimestampMS();
	
// 			\Yii::info('actionDoprintSaitu'.$puid.''.$url, "file");
	
			if($pdfmergeResult['success'] == true){
				$result['data'] = ['pdfUrl'=>$url];
				$result['error'] = 0;
			}else{
				$result['msg'] = $pdfmergeResult['message'];
				$result['error'] = 1;
			}
			
			$timeMS9 = TimeUtil::getCurrentTimestampMS();
			\Yii::info('ShowPrintPdf_0411:'.'time9-8:'.($timeMS9-$timeMS8).'time8-7:'.($timeMS8-$timeMS7).'time7-6:'.($timeMS7-$timeMS6)
					.'time6-5:'.($timeMS6-$timeMS5).'time5-4:'.($timeMS5-$timeMS4).'time4-3:'.($timeMS4-$timeMS3).'time3-2:'.($timeMS3-$timeMS2)
					.'time2-1:'.($timeMS2-$timeMS1), "carrier_api");
		}else{
			if($is_aliexpress_carrier == true){
				$result['msg'] = '??????????????????????????????????????????????????????????????????????????????????????????????????????!';
			}else{
				$result['msg'] = '????????????????????????????????????????????????????????????';
			}
		}
			
		$this->layout = 'carrier';
		return $this->render('doprint2',['data'=>array('result'=>$result,'carrier_name'=>'')]);
	}
	
	//???????????????????????????
	public function actionDoprintIntegrationLabel_muti(){
		$result = ['error'=>1,'data'=>'','msg'=>''];
		
		if(isset($_GET['orders'])&&!empty($_GET['orders'])){
			$orders	= $_GET['orders'];
		}else{
			echo "???????????????????????????";die;
		}
		
		$timeMS1 = TimeUtil::getCurrentTimestampMS();
	
		$puid = \Yii::$app->user->identity->getParentUid();
		$orders = rtrim($orders,',');
		$order_arr=explode(',', $orders);
		
		if(count($order_arr) > 0){
			$result_by_now = \eagle\modules\carrier\helpers\CarrierAPIHelper::getCarrierLabelApiAndItemsByNow_1($order_arr, $puid);
		}
		
		$tmpPdfArr = array();
		
		if($result_by_now['error'] == 0){
			foreach ($result_by_now['data'] as $result_by_now_file_path){
				$tmpPdfArr[] = \eagle\modules\carrier\helpers\CarrierAPIHelper::getPdfPathString(false).$result_by_now_file_path;
			}
		}
		
		$timeMS2 = TimeUtil::getCurrentTimestampMS();
		
// 		print_r($result_by_now);
// 		exit;
		
		if((!empty($tmpPdfArr)) && (count($order_arr) == count($tmpPdfArr))){
			if(count($tmpPdfArr) == 1){
				$pdfmergeResult['success'] = true;
				$url = Yii::$app->request->hostinfo.str_replace(\eagle\modules\carrier\helpers\CarrierAPIHelper::getPdfPathString(false), "", $tmpPdfArr[0]);
			}else{
				$pathPDF = \eagle\modules\carrier\helpers\CarrierAPIHelper::createCarrierLabelDir();
				$tmpName = $puid.'_summerge_'.rand(10,99).time().'.pdf';
				$pdfmergeResult = PDFMergeHelper::PDFMerge($pathPDF.'/'.$tmpName , $tmpPdfArr);
					
				$url = Yii::$app->request->hostinfo.str_replace(\eagle\modules\carrier\helpers\CarrierAPIHelper::getPdfPathString(false), "", $pathPDF).'/'.$tmpName;
			}
	
			if($pdfmergeResult['success'] == true){
				$result['data'] = ['pdfUrl'=>$url];
				$result['error'] = 0;
			}else{
				$result['msg'] = $pdfmergeResult['message'];
				$result['error'] = 1;
			}
		}else{
			$result['msg'] = $result_by_now['msg'];
		}
		
		$timeMS3 = TimeUtil::getCurrentTimestampMS();
		\Yii::info('pdf_print:'.' time3-2:'.($timeMS3-$timeMS2).' time2-1:'.($timeMS2-$timeMS1).' time3-1:'.($timeMS3-$timeMS1)
				.' order_count:'.(count($order_arr)).' order_json:'.(json_encode($order_arr)), "carrier_api");
		
		$this->layout = 'carrier';
		return $this->render('doprint2',['data'=>array('result'=>$result,'carrier_name'=>'')]);
	}
	
	/**
	 +----------------------------------------------------------
	 * ???????????????????????????????????????????????????
	 *
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw 	2016/06/27				?????????
	 +----------------------------------------------------------
	 **/
	public function actionExternalDoprint(){
// 		AppTrackerApiHelper::actionLog("eagle_v2","/carrier/carrierprocess/ExternalDoprint");
		
// 		$_REQUEST['order_ids'] = '2819,2810,4197';//4197,

		//???????????????????????????md5???
		$order_md5_str = '';
		
		if(isset($_REQUEST['order_ids'])){
			$order_ids = explode(',',$_REQUEST['order_ids']);
// 			$order_ids = $_GET['order_ids'];

			$order_md5_str = md5($_REQUEST['order_ids']);
			
			$tmp_orderlist = OdOrder::find()->where(['in','order_id',$order_ids])->orderBy('default_shipping_method_code asc')->all();
// 			$odorders
			
			$order_arr = $order_ids;
			
			//????????????
			$odorders = array();
				
			foreach ($order_arr as $order_arr_one){
				foreach ($tmp_orderlist as $tmp_orderlist_one){
					if($order_arr_one == $tmp_orderlist_one['order_id']){
						$odorders[] = $tmp_orderlist_one;
						break;
					}
				}
			}
			
			AppTrackerApiHelper::actionLog("eagle_v2","/carrier/carrierprocess/ExternalDoprint",array('paramstr1'=>$_REQUEST['order_ids']));
		}
		
		$is_generate_pdf = empty($_REQUEST['is_generate_pdf']) ? 0 : 1;  //????????????pdf
		
		//???????????????????????? S
		$user=\Yii::$app->user->identity;
		$puid = $user->getParentUid();
		
		$classification = "Tracker_AppTempData";
		$key = date("Y-m-d").'_print_'.$puid.'_'.$order_md5_str;
		
		$lastSetCarrierTypeTime = RedisHelper::RedisGet($classification, $key);
		
		if(!empty($lastSetCarrierTypeTime) && ((time()-(int)$lastSetCarrierTypeTime) < 5)){
			$tmp_result = array();
			$tmp_result['result'] = array('error' => 1, 'data' => '', 'msg' => '????????????????????????????????????');;
			$tmp_result['carrier_name'] = "";
			
			return $this->render('doprint2',['data'=>$tmp_result]);
		}
		
		RedisHelper::RedisSet($classification, $key, time());
		//???????????????????????? E
		
		$externalV = '';
		if(isset($_REQUEST['externalV'])){
			$externalV = $_REQUEST['externalV'];
		}
		
		$emslist = [];
		$is_searched = [];
		$list = [];
		
		$notMethodOrder = array();
		
		if(!empty($odorders)){
			foreach($odorders as $v){
				if(!isset($is_searched[$v->default_shipping_method_code])){
					//???????????????????????????????????????????????????
					if(empty($v->default_shipping_method_code)){
						$notMethodOrder[] = $v->order_id;
						continue;
					}
					
					$printMode = CarrierOpenHelper::getCustomShippingServicePrintMode($v->default_shipping_method_code, $externalV);
					
					$is_searched[$v->default_shipping_method_code] = $printMode;
					
					unset($printMode);
				}
				
				//LGS????????????????????????
				if($v->default_carrier_code == 'lb_LGS'){
					if(empty($is_searched[$v->default_shipping_method_code]['is_print'])){
						if(empty($is_searched[$v->default_shipping_method_code]['is_api_print']))
							$is_searched[$v->default_shipping_method_code]['is_print'] = 1;
					}
				}
				
				$priorityPrint = '';
				
				if($is_searched[$v->default_shipping_method_code]['is_custom_print'] == 1){
					$method_name = $is_searched[$v->default_shipping_method_code]['shipping_method_name'];
					$priorityPrint = 'is_custom_print';
				}else if($is_searched[$v->default_shipping_method_code]['is_xlb_print'] == 1){
					$method_name = $is_searched[$v->default_shipping_method_code]['shipping_method_name'];
					$priorityPrint = 'is_xlb_print';
				}else if($is_searched[$v->default_shipping_method_code]['is_custom_print_new'] == 1){
					$method_name = $is_searched[$v->default_shipping_method_code]['shipping_method_name'];
					$priorityPrint = 'is_custom_print_new';
				}else if($is_searched[$v->default_shipping_method_code]['is_print'] == 1){
					$method_name = $is_searched[$v->default_shipping_method_code]['shipping_method_name'].$v->consignee_country_code;
					$priorityPrint = 'is_print';
				}else{
					$method_name = $is_searched[$v->default_shipping_method_code]['shipping_method_name'];
					$priorityPrint = 'is_api_print';
					
					if($is_searched[$v->default_shipping_method_code]['is_api_print'] == 2){
						$priorityPrint = 'is_api_print_smt_2';
					}
				}
				
				$carrier_name = $is_searched[$v->default_shipping_method_code]['carrier_name'];
				//???????????????????????????????????????
				isset($count_shipping_service[$method_name])?++$count_shipping_service[$method_name]:($count_shipping_service[$method_name] = 1);
				//?????????id????????????????????????
				if(!isset($emslist[$method_name]))$emslist[$method_name] = [];
				isset($emslist[$method_name]['order_ids'])?'':$emslist[$method_name]['order_ids'] = '';
				$emslist[$method_name]['order_ids'] .= $v->order_id.',';
				$emslist[$method_name]['display_name'] = $carrier_name.' >>> '.$method_name;
				$emslist[$method_name]['priorityPrint'] = $priorityPrint;
			}
			
			foreach($emslist as $k=>$v){
				$name = $v['display_name'].' X '.$count_shipping_service[$k];
				$list[$name] = array('order_ids'=>$v['order_ids'], 'priorityPrint'=>$v['priorityPrint']);
			}
		}
		
		$result = array();
		$result['emslist']=$list;
		$result['notMethodOrder'] = $notMethodOrder;
		
		if((count($result['emslist']) > 1) || (!empty($notMethodOrder))){
			return $this->render('createPDF2print',['data'=>$result]);
		}else if(count($result['emslist']) == 1){
			//????????????pdf
			if($is_generate_pdf){
				$_GET['orders'] = $result['emslist'][$name]['order_ids'];
				$_GET['ems'] = $name;
				$_GET['v1'] = rand(10,99);
				$_GET['is_generate_pdf'] = $is_generate_pdf;
				
				if($result['emslist'][$name]['priorityPrint'] == 'is_custom_print')
					self::actionDoprintcustom();
				else if($result['emslist'][$name]['priorityPrint'] == 'is_print')
					self::actionDoprint();
				else if($result['emslist'][$name]['priorityPrint'] == 'is_xlb_print')
					self::actionDoprintNew();
				else if($result['emslist'][$name]['priorityPrint'] == 'is_custom_print_new')
					self::actionDoprintcustomNew();
				else if($result['emslist'][$name]['priorityPrint'] == 'is_api_print_smt_2')
					self::actionDoprintapi();
				else
					self::actionDoprintapi();
			}
			else{
				if($result['emslist'][$name]['priorityPrint'] == 'is_custom_print')
					return $this->redirect('/carrier/carrierprocess/doprintcustom?orders='.$result['emslist'][$name]['order_ids'].'&ems='.$name.'&v1='.rand(10,99));
				else if($result['emslist'][$name]['priorityPrint'] == 'is_print')
					return $this->redirect('/carrier/carrierprocess/doprint?orders='.$result['emslist'][$name]['order_ids'].'&ems='.$name.'&v1='.rand(10,99));
				else if($result['emslist'][$name]['priorityPrint'] == 'is_xlb_print')
					return $this->redirect('/carrier/carrierprocess/doprint-new?orders='.$result['emslist'][$name]['order_ids'].'&ems='.$name.'&v1='.rand(10,99));
				else if($result['emslist'][$name]['priorityPrint'] == 'is_custom_print_new')
					return $this->redirect('/carrier/carrierprocess/doprintcustom-new?orders='.$result['emslist'][$name]['order_ids'].'&ems='.$name.'&v1='.rand(10,99));
				else if($result['emslist'][$name]['priorityPrint'] == 'is_api_print_smt_2')
					return $this->redirect('/carrier/carrierprocess/doprintapi?orders='.$result['emslist'][$name]['order_ids'].'&ems='.$name.'&v1='.rand(10,99));
				else
					return $this->redirect('/carrier/carrierprocess/doprintapi?orders='.$result['emslist'][$name]['order_ids'].'&ems='.$name.'&v1='.rand(10,99));
			}
			
		}else{
			return $this->render('createPDF2print',['data'=>$result]);
		}
	}
	
	/**
	 +----------------------------------------------------------
	 * ????????? ????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		hqw 		2016/10/22				?????????
	 +----------------------------------------------------------
	 **/
	public function actionDoprintNew(){
		$is_generate_pdf = empty($_GET['is_generate_pdf']) ? 0 : 1;  //????????????pdf
		
		if(isset($_GET['orders'])&&!empty($_GET['orders'])){
			$orders	=	$_GET['orders'];
		}else{
			echo "???????????????????????????(send none order)";die;
		}
		$orders = rtrim($orders,',');
		$orderlist	=	OdOrder::find()->where("order_id in ({$orders})")->all();
		
		if(count($orderlist) > 0){
			foreach ($orderlist as $tmp_order){
				if(($tmp_order->default_carrier_code == 'lb_seko') && (empty($tmp_order->tracking_number))){
					$tmpResult = array();
					$tmpResult['carrier_name'] = empty($_GET['ems']) ? '' : $_GET['ems'];
					$tmpResult['result'] = array('error' => 1, 'data' => '', 'msg' => 'Seko??????????????????????????????????????????');
						
					return $this->render('doprint2',['data'=>$tmpResult]);
				}
			}
		}
		
		$result = PrintPdfHelper::getHighcopyFormatPDF($orderlist, $is_generate_pdf);
		
		if(isset($result['error'])){
			$tmpResult = array();
			$tmpResult['carrier_name'] = empty($_GET['ems']) ? '' : $_GET['ems'];
			$tmpResult['result'] = array('error' => 1, 'data' => '', 'msg' => $result['msg']);
			
			return $this->render('doprint2',['data'=>$tmpResult]);
		}
	}
	
	/**
	 +----------------------------------------------------------
	 * ????????? ???????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	    date					note
	 * @author		hqw 		2017/01/09				?????????
	 +----------------------------------------------------------
	 **/
	public function actionDoprintcustomNew(){
		if(isset($_GET['orders'])&&!empty($_GET['orders'])){
			$orders	=	$_GET['orders'];
		}else{
			echo "???????????????????????????(send none order)";die;
		}
		$is_generate_pdf = empty($_GET['is_generate_pdf']) ? 0 : 1;  //????????????pdf
		
		$orders = rtrim($orders,',');
		$tmp_orderlist	=	OdOrder::find()->where("order_id in ({$orders})")->all();
// 		$orderlist
		
		$order_arr = explode(',', $orders);
		
		//????????????
		$orderlist = array();
			
		foreach ($order_arr as $order_arr_one){
			foreach ($tmp_orderlist as $tmp_orderlist_one){
				if($order_arr_one == $tmp_orderlist_one['order_id']){
					$orderlist[] = $tmp_orderlist_one;
					break;
				}
			}
		}
		
		$result = PrintPdfHelper::getCustomFormatPDF($orderlist, '', array(), $is_generate_pdf);
		
		if(isset($result['error'])){
			$tmpResult = array();
			$tmpResult['carrier_name'] = empty($_GET['ems']) ? '' : $_GET['ems'];
			$tmpResult['result'] = array('error' => 1, 'data' => '', 'msg' => $result['msg']);
				
			return $this->render('doprint2',['data'=>$tmpResult]);
		}
	}
	
	//????????????????????????????????????
	public function actionThermalPickingPrint(){
		if(isset($_GET['orders'])&&!empty($_GET['orders'])){
			$orders = $_GET['orders'];
		}else{
			echo "???????????????????????????(send none order)";die;
		}
		
		$orders = rtrim($orders,',');
		$tmp_orderlist = OdOrder::find()->where("order_id in ({$orders})")->all();
// 		$orderlist
		
		$order_arr = explode(',', $orders);
		
		//????????????
		$orderlist = array();
			
		foreach ($order_arr as $order_arr_one){
			foreach ($tmp_orderlist as $tmp_orderlist_one){
				if($order_arr_one == $tmp_orderlist_one['order_id']){
					$orderlist[] = $tmp_orderlist_one;
					break;
				}
			}
		}
		
		$result = PrintPdfHelper::getThermalPickingFormatPDF($orderlist);
		
		if(isset($result['error'])){
			$tmpResult = array();
			$tmpResult['carrier_name'] = empty($_GET['ems']) ? '' : $_GET['ems'];
			$tmpResult['result'] = array('error' => 1, 'data' => '', 'msg' => $result['msg']);
		
			return $this->render('doprint2',['data'=>$tmpResult]);
		}
	}
	
	//???????????????????????????
	public function actionCheckCainiao(){
		$result = array('code'=>0, 'msg'=>'');

		$order_ids = explode(',',$_REQUEST['order_ids']);
		$shipping_list = OdOrder::find()->select('default_carrier_code,default_shipping_method_code')
			->where(['in','order_id',$order_ids])->groupBy('default_carrier_code,default_shipping_method_code')->asArray()->all();
		
		if(count($shipping_list) == 0){
			$result['msg'] = '??????????????????';
			exit(json_encode($result));
		}
		
		$shipping_smt_list = array();
		
		foreach ($shipping_list as $shipping_list_V){
			if($shipping_list_V['default_carrier_code'] == 'lb_alionlinedelivery'){
				$shipping_smt_list[$shipping_list_V['default_shipping_method_code']] = $shipping_list_V['default_shipping_method_code'];
			}
		}
		
		if(count($shipping_smt_list) == 0){
			$result['code'] = 1;
			exit(json_encode($result));
		}
		
		$is_smt_shipping_print = false;
		
		foreach ($shipping_smt_list as $shipping_smt_list_V){
			$printMode = CarrierOpenHelper::getCustomShippingServicePrintMode($shipping_smt_list_V);
			
			$priorityPrint = '';
			
			if($printMode['is_custom_print'] == 1){
				$priorityPrint = 'is_custom_print';
			}else if($printMode['is_xlb_print'] == 1){
				$priorityPrint = 'is_xlb_print';
			}else if($printMode['is_custom_print_new'] == 1){
				$priorityPrint = 'is_custom_print_new';
			}else if($printMode['is_print'] == 1){
				$priorityPrint = 'is_print';
			}else{
				$priorityPrint = 'is_api_print';
				
				if(($printMode['is_api_print'] == 2) || ($printMode['is_api_print'] == 3)){
					$priorityPrint = 'is_api_print_smt_2';
				}
			}
			
			if($priorityPrint == 'is_api_print_smt_2'){
				$is_smt_shipping_print = true;
				if(count($shipping_list) != count($shipping_smt_list)){
					$result['msg'] = '???????????????????????????????????????????????????????????????????????????????????????';
					exit(json_encode($result));
				}
			}
		}
		
		if($is_smt_shipping_print == false){
			$result['code'] = 1;
		}else{
			$result['code'] = 2;
		}
		
		exit(json_encode($result));
	}
	
	//?????????????????????
	public function actionGetCloudPrintData(){
		$result = array('code'=>0, 'msg'=>'', 'data' => array());
		
		$orderIds = explode(',',$_REQUEST['orderIds']);
		$order_lists = OdOrder::find()->where(['in','order_id', $orderIds])->all();
		
		$print_order_lists = array();
		
		$print_order_data = array();
		
		if(count($order_lists) == 0){
			$result['msg'] = '??????????????????????????????';
			exit(json_encode($result));
		}
		
		foreach ($order_lists as $order){
			if(!isset($print_order_lists[$order['selleruserid']])){
				$print_order_lists[$order['selleruserid']] = array();
			}
			
			$checkResult = \eagle\modules\carrier\helpers\CarrierAPIHelper::validate(1, 1, $order);
			$shipped = $checkResult['data']['shipped'];
			
			if(empty($shipped->tracking_number)){
				$result['msg'] = '??????????????????'.$order->order_source_order_id.'??????????????????????????????';
				exit(json_encode($result));
			}
			
			if($shipped->addtype != '??????API'){
				$result['msg'] = '??????????????????'.$order->order_source_order_id.' ????????????API???????????????????????????';
				exit(json_encode($result));
			}
			
			$items_arr = array();
			
			foreach ($order->items as $tmp_item){
				$tmpSku = $tmp_item->root_sku;
				$tmp_product = \eagle\modules\catalog\apihelpers\ProductApiHelper::getProductInfo($tmpSku);
				
				if($tmp_item->delivery_status == 'ban'){
					continue;
				}
				
				$tmp_product_attributes = '';
				if (!empty($tmp_item->product_attributes)){
					$tmpProdctAttrbutes = explode(' + ' ,$tmp_item->product_attributes );
					if (!empty($tmpProdctAttrbutes)){
						$tmp_product_attributes = "\n\t";
						foreach($tmpProdctAttrbutes as $_tmpAttr){
							$tmp_product_attributes .= $_tmpAttr;
						}
					}
				}
				
				$tmp_photo_primary = $tmp_item->photo_primary;
				if($tmp_photo_primary == 'http://g03.a.alicdn.com/kf/images/eng/no_photo.gif'){
					$tmp_photo_primary = '';
				}
				
				$items_arr[] = array('prod_name_ch'=>$tmp_product['prod_name_ch'].' '.$tmpSku. ' * '.$tmp_item->quantity.$tmp_product_attributes , 'image_url'=>$tmp_photo_primary);
			}
			
			try {
				$items_arr[0]['prod_name_ch'] = '??????????????????:'.$order['order_id']."\n\t".$items_arr[0]['prod_name_ch'];
				
				if(count($items_arr) > 0){
					$tmp_count = count($items_arr)-1;
					$items_arr[$tmp_count]['prod_name_ch'] .= "\n\t".$order['desc'];
				}
			}catch(\Exception $ex){}
			
			$print_order_lists[$order['selleruserid']][] = array('tracking_number'=>$shipped->tracking_number,'items'=>$items_arr);
		}
		
		//??????????????????
		$userShippingSevice = SysShippingService::find()->select(['carrier_code','shipping_method_code','third_party_code',
				'print_params','is_custom','shipping_method_name','carrier_name','print_type','carrier_params'])->where(['id'=>$order->default_shipping_method_code])->asArray()->one();
		
		$userShippingSevice['carrier_params'] = unserialize($userShippingSevice['carrier_params']);
		$tmp_print_type = 2;
		
		if(!empty($userShippingSevice['carrier_params']['print_format'])){
			if($userShippingSevice['carrier_params']['print_format'] == 1){
				$tmp_print_type = 2;
			}else{
				$tmp_print_type = 3;
			}
		}
		
		if(count($print_order_lists) > 0){
			foreach ($print_order_lists as $print_order_list_K => $print_order_listV){
				unset($resultGetApiPrint);
				unset($params_smt_account);
				$params_smt_account = array();
				
				if(count($print_order_listV) > 0){
					foreach ($print_order_listV as $print_order_listV_V){
						if($tmp_print_type == 3){
							unset($tmp_extendData);
							$tmp_extendData = array();

							foreach ($print_order_listV_V['items'] as $tmp_item_Val){
								$tmp_extendData[] = array('imageUrl'=>$tmp_item_Val['image_url'], 'productDescription'=>$tmp_item_Val['prod_name_ch']);
							}
							
							$tmp_extendData = json_encode($tmp_extendData);
							
							$params_smt_account[] = array('extendData'=>$tmp_extendData,'internationalLogisticsId'=>$print_order_listV_V['tracking_number']);
						}else{
							$params_smt_account[] = array('internationalLogisticsId'=>$print_order_listV_V['tracking_number']);
						}
					}
				}
				
				$params_smt_account = json_encode($params_smt_account);
				
				$params_smt = array('printDetail'=>(($tmp_print_type == 3) ? 'true' : 'false'), 'warehouseOrderQueryDTOs' => ($params_smt_account));
				
				$resultGetApiPrint = \common\api\carrierAPI\LB_ALIONLINEDELIVERYCarrierAPI::getAliexpressCloudPrintInfo($print_order_list_K, $params_smt);
				
// 				print_r($resultGetApiPrint);
// 				exit;
				
				if($resultGetApiPrint['Ack'] == false){
					$result['msg'] = $resultGetApiPrint['error'];
					exit(json_encode($result));
				}
				
				if(!isset($resultGetApiPrint['printInfo']['success'])){
					$result['msg'] = '??????????????????????????????????????????????????????e1_1';
					exit(json_encode($result));
				}
				
				if($resultGetApiPrint['printInfo']['success'] == false){
					$result['msg'] = $resultGetApiPrint['printInfo']['errorCode'];
					exit(json_encode($result));
				}
				
				if(!isset($resultGetApiPrint['printInfo']['aeopCloudPrintDataResponseList'])){
					$result['msg'] = '??????????????????????????????????????????????????????e1';
					exit(json_encode($result));
				}
				
				if(count($resultGetApiPrint['printInfo']['aeopCloudPrintDataResponseList']) == 0){
					$result['msg'] = '??????????????????????????????????????????????????????e2';
					exit(json_encode($result));
				}
				
				foreach ($resultGetApiPrint['printInfo']['aeopCloudPrintDataResponseList'] as $tmp_aeopCloudPrintDataResponseListOne){
					if(!isset($tmp_aeopCloudPrintDataResponseListOne['cloudPrintDataList'][0])){
						$result['msg'] = '??????????????????????????????????????????????????????e3';
						exit(json_encode($result));
					}
					
					foreach ($tmp_aeopCloudPrintDataResponseListOne['cloudPrintDataList'] as $tmp_cloudPrintData_Val){
						unset($tmp_printData);
						$tmp_printData = json_decode($tmp_cloudPrintData_Val['printData'], true);
						$print_order_data[] = array('orderCode'=>$tmp_aeopCloudPrintDataResponseListOne['orderCode'], 'printData'=>$tmp_printData);
// 						$tmp_printData['data']['goodsInfo'] = '???????????????';
					}
				}
			}
		}
		
		$result['code'] = 1;
		$result['data'] = $print_order_data;
		exit(json_encode($result));
	}
	
	/**
	 +----------------------------------------------------------
	 * ????????????url
	 +----------------------------------------------------------
	 * log			name		date			note
	 * @author		lrq		  2017/11/07		?????????
	 +----------------------------------------------------------
	 **/
	public function actionGetPrintUrl(){
		$ret = CarrierOpenHelper::GetPrintUrl($_REQUEST);
		
		return json_encode($ret);
	}
	/**
	 +----------------------------------------------------------
	 * ??????jumia ????????????
	 +----------------------------------------------------------
	 * log			name		date			note
	 * @author		lwj		  2018/07/12		?????????
	 +----------------------------------------------------------
	 **/
	public function actionInvoiceDoprint(){
	    if(isset($_REQUEST['order_ids'])){
	        $order_ids = explode(',',$_REQUEST['order_ids']);
	        $tmp_orderlist = OdOrder::find()->where(['in','order_id',$order_ids])->all();
	        
            if(!empty($tmp_orderlist)){
                //??????????????????
                $code2CodeMap = ['eg' => '??????','ci' => '????????????','ma' => '?????????',];
                
                //?????????????????????
                $lazada_account_site = array();
                
                foreach ($tmp_orderlist as $order){
                    if (empty($code2CodeMap[strtolower($order->order_source_site_id)])){
                        header("content-type:text/html;charset=utf-8");
                        echo '??????:'.$order->order_id." ??????" . $order->order_source_site_id . "?????? jumia??????????????????????????????";
                        exit();
                    }
                   
                    if(!isset($lazada_account_site[$order->selleruserid])){
                        $lazada_account_site[$order->selleruserid] = array();
                    }
                     
                    if(!isset($lazada_account_site[$order->selleruserid][strtolower($order->order_source_site_id)])){
                        $lazada_account_site[$order->selleruserid][strtolower($order->order_source_site_id)] = '';
                    }
                     
                    $tmp_item_ids = $lazada_account_site[$order->selleruserid][strtolower($order->order_source_site_id)];
                     
                    foreach($order->items as $item){
                        $tmp_item_ids .= empty($tmp_item_ids) ? $item->order_source_order_item_id : ','.$item->order_source_order_item_id;
                    }
                     
                    $lazada_account_site[$order->selleruserid][strtolower($order->order_source_site_id)] = $tmp_item_ids;
                }
                
                //???????????????base64?????????
                $tmp_base64_str_a = array();
                
                //????????????lazada???????????????
                foreach ($lazada_account_site as $lazada_account_key => $lazada_account_val){
                    foreach ($lazada_account_val as $lazada_site_key => $lazada_site_val){
                        $SLU = SaasLazadaUser::findOne(['platform_userid' => $lazada_account_key, 'lazada_site' => $lazada_site_key]);
                
                        if (empty($SLU)) {
                            header("content-type:text/html;charset=utf-8");
                            echo $lazada_account_key . " ???????????????" .' '. $lazada_site_key.'???????????????';
                            exit();
                        }
                
                        $lazada_config = array(
                            "userId" => $SLU->platform_userid,
                            "apiKey" => $SLU->token,
                            "countryCode" => $SLU->lazada_site
                        );
                
                        $lazada_appParams = array(
                            'OrderItemIds' => $lazada_site_val,
                            'DocumentType' => 'invoice'
                        );
                
                        $result = LazadaInterface_Helper::getOrderShippingLabel($lazada_config, $lazada_appParams);
                        
                        if ($result['success'] && $result['response']['success'] == true) { // ??????
                            $tmp_base64_str_a[] = $result["response"]["body"]["Body"]['Documents']["Document"]["File"];
                
                        } else {
                            header("content-type:text/html;charset=utf-8");
                            echo '?????????????????????'.$result['message'];
                            exit();
                        }
                    }
                }
                
                //???????????????HTML
                $tmp_html = '';
                
                foreach ($tmp_base64_str_a as $tmp_base64_val){
                    $tmp_html .= empty($tmp_html) ? base64_decode($tmp_base64_val) : '<hr style="page-break-after: always;border-top: 3px dashed;">'.base64_decode($tmp_base64_val);
                }
                //LGS ????????????html??????????????????????????????
                echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /></head><body style="margin:0px;">'.$tmp_html.''.'</body>';
                exit;
            }
	    }
	}
}
?>