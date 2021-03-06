<?php

namespace eagle\modules\order\controllers;

use eagle\modules\listing\models\OdOrderItem2;
use yii\data\Pagination;
use eagle\modules\order\models\OdOrder;
use eagle\modules\order\models\Excelmodel;
use eagle\modules\order\models\OdEbayTransaction;
use eagle\models\EbaySite;
use eagle\models\EbayShippingservice;
use common\api\ebayinterface\getorders;
use common\api\ebayinterface\getsellertransactions;
use common\helpers\Helper_Array;
use eagle\models\SaasEbayUser;
use common\api\ebayinterface\sendinvoice;
use eagle\modules\order\models\OdOrderItem;
use eagle\modules\util\helpers\OperationLogHelper;
use eagle\modules\order\helpers\OrderHelper;
use eagle\modules\util\helpers\ExcelHelper;
use eagle\modules\order\models\Usertab;
use Exception;
use eagle\modules\order\models\OdOrderShipped;
use eagle\models\carrier\SysShippingService;
use eagle\modules\inventory\helpers\InventoryApiHelper;
use eagle\models\EbayCountry;
use eagle\modules\order\helpers\OrderTagHelper;
use common\api\ebayinterface\addmembermessageaaqtopartner;
use eagle\modules\app\apihelpers\AppTrackerApiHelper;
use eagle\models\QueueGetorder;
use eagle\modules\order\model\OdPaypalTransaction;
use eagle\modules\tracking\helpers\TrackingApiHelper;
use eagle\modules\order\helpers\OrderProfitHelper;
use eagle\modules\catalog\helpers\ProductApiHelper;
use eagle\modules\util\helpers\SysLogHelper;
use eagle\modules\carrier\apihelpers\CarrierApiHelper;
use eagle\modules\util\helpers\TranslateHelper;
use eagle\modules\order\apihelpers\OrderApiHelper;
use eagle\modules\order\models\OdOrderGoods;
use eagle\modules\carrier\helpers\CarrierOpenHelper;
use eagle\modules\inventory\helpers\InventoryHelper;
use common\helpers\Helper_xml;
use eagle\models\SysCountry;
use eagle\modules\util\helpers\StandardConst;
use eagle\modules\order\helpers\CdiscountOrderInterface;
use eagle\modules\platform\apihelpers\PlatformAccountApi;
use eagle\modules\message\helpers\MessageHelper;
use yii\db\Query;
use eagle\modules\order\models\OrderRelation;
use eagle\modules\catalog\helpers\ProductHelper;
use eagle\modules\util\helpers\ConfigHelper;
use eagle\modules\util\helpers\DataStaticHelper;
use eagle\modules\carrier\models\SysCarrierCustom;
use eagle\modules\order\helpers\OrderGetDataHelper;
use eagle\modules\order\helpers\OrderBackgroundHelper;
use eagle\modules\order\helpers\OrderFrontHelper;
use eagle\modules\order\helpers\OrderUpdateHelper;
use eagle\modules\catalog\models\ProductBundleRelationship;
use eagle\modules\catalog\models\Product;
use eagle\modules\carrier\helpers\CarrierDeclaredHelper;
use eagle\widgets\SizePager;
use Qiniu\json_decode;
use eagle\modules\util\helpers\RedisHelper;
use frontend;
use Qiniu\base64_urlSafeDecode;
use eagle\modules\order\helpers\OrderListV3Helper;
use eagle\modules\order\helpers\LazadaOrderHelper;
use eagle\modules\platform\apihelpers\EbayAccountsApiHelper;
use eagle\modules\permission\helpers\UserHelper;
use PayPal\Api\Order;
use eagle\modules\util\helpers\ImageCacherHelper;


class OrderController extends \eagle\components\Controller{
	public $enableCsrfValidation = false;
	
	public function actionListebay(){
		$url = '/order/ebay-order/list';
		return $this->redirect($url);
		AppTrackerApiHelper::actionLog("Oms-ebay", "/order/listebay");
		$data=OdOrder::find();
		$data->andWhere(['order_source'=>'ebay']);
		$showsearch=0;
		if (!empty($_REQUEST['order_status'])){
			//??????????????????
			$data->andWhere('order_status = :os',[':os'=>$_REQUEST['order_status']]);
		}
		if (!empty($_REQUEST['exception_status'])){
			//????????????????????????
			$data->andWhere('exception_status = :es',[':es'=>$_REQUEST['exception_status']]);
			$data->andWhere('order_status < '.OdOrder::STATUS_WAITSEND);
		}
		if (!empty($_REQUEST['is_manual_order'])){
			//????????????????????????
			$data->andWhere('is_manual_order = :imo',[':imo'=>$_REQUEST['is_manual_order']]);
		}
		if (!empty($_REQUEST['cangku'])){
			//????????????
			$data->andWhere('default_warehouse_id = :dwi',[':dwi'=>$_REQUEST['cangku']]);
			$showsearch=1;
		}
		if (!empty($_REQUEST['shipmethod'])){
			//??????????????????
			$data->andWhere('default_shipping_method_code = :dsmc',[':dsmc'=>$_REQUEST['shipmethod']]);
			$showsearch=1;
		}
		if (!empty($_REQUEST['trackstatus'])){
			//??????????????????
			$data->andWhere(['trackstatus'=>$_REQUEST['trackstatus']]);
		}
		if (!empty($_REQUEST['fuhe'])){
			$showsearch=1;
			//??????????????????
			switch ($_REQUEST['fuhe']){
				case 'haspayed':
					$data->andWhere('pay_status = 1');
					break;
				case 'hasnotpayed':
					$data->andWhere('pay_status = 0');
					break;
				case 'pending':
					$data->andWhere('pay_status = 2');
					break;
				case 'hassend':
					$data->andWhere('shipping_status = 1');
					break;
				case 'payednotsend':
					$data->andWhere('shipping_status = 0 and pay_status = 1');
					break;
				case 'hasmessage':
					//$data->andWhere('user_message is not null');
					$data->andWhere('length(user_message)>0');
					break;
				case 'hasinvoice':
					$data->andWhere('hassendinvoice = 1');
					break;
				default:break;
			}
		}
		if (!empty($_REQUEST['searchval'])){
			//??????????????????????????????
			if (in_array($_REQUEST['keys'], ['order_id','ebay_orderid','srn','buyeid','email','consignee'])){
				$kv=[
					'order_id'=>'order_id',
					'ebay_orderid'=>'order_source_order_id',
					'srn'=>'order_source_srn',
					'buyeid'=>'source_buyer_user_id',
					'email'=>'consignee_email',
					'consignee'=>'consignee'
				];
				$key = $kv[$_REQUEST['keys']];
				$data->andWhere("$key = :val",[':val'=>$_REQUEST['searchval']]);
			}elseif ($_REQUEST['keys']=='sku'){
				$ids = Helper_Array::getCols(OdOrderItem::find()->where('sku = :sku',[':sku'=>$_REQUEST['searchval']])->select('order_id')->asArray()->all(),'order_id');
				$data->andWhere(['IN','order_id',$ids]);
			}elseif ($_REQUEST['keys']=='itemid'){
				
			}elseif ($_REQUEST['keys']=='tracknum'){
				$ids = Helper_Array::getCols(OdOrderShipped::find()->where('tracking_number = :tn',[':tn'=>$_REQUEST['searchval']])->select('order_id')->asArray()->all(),'order_id');
				$data->andWhere(['IN','order_id',$ids]);
			}
		}
		if (!empty($_REQUEST['selleruserid'])){
			//??????????????????
			$data->andWhere('selleruserid = :s',[':s'=>$_REQUEST['selleruserid']]);
		}
		if (!empty($_REQUEST['country'])){
			//??????????????????
			$data->andWhere('consignee_country_code = :c',[':c'=>$_REQUEST['country']]);
			$showsearch=1;
		}
		if (!empty($_REQUEST['startdate'])||!empty($_REQUEST['enddate'])){
			//??????????????????
			switch ($_REQUEST['timetype']){
				case 'soldtime':
					$tmp='order_source_create_time';
				break;
				case 'paidtime':
					$tmp='paid_time';
				break;
				case 'printtime':
					$tmp='printtime';
				break;
				case 'shiptime':
					$tmp='delivery_time';
				break;
			}
			if (!empty($_REQUEST['startdate'])){
				$data->andWhere("$tmp >= :stime",[':stime'=>strtotime($_REQUEST['startdate'])]);
			}
			if (!empty($_REQUEST['enddate'])){
				$data->andWhere("$tmp <= :time",[':time'=>strtotime($_REQUEST['enddate'])+24*3599]);
			}
			$showsearch=1;
		}
		if (empty($_REQUEST['ordersort'])){
			$orderstr = 'order_source_create_time';
		}else{
			switch ($_REQUEST['ordersort']){
				case 'soldtime':
					$orderstr='order_source_create_time';
					break;
				case 'paidtime':
					$orderstr='paid_time';
					break;
				case 'printtime':
					$orderstr='printtime';
					break;
				case 'shiptime':
					$orderstr='delivery_time';
					break;
			}
		}
		if (empty($_REQUEST['ordersorttype'])){
			$orderstr .= ' DESC';
		}else{
			$orderstr.=' '.$_REQUEST['ordersorttype'];
		}
		$data->orderBy($orderstr)->with('items');
	    $pages = new Pagination(['totalCount' => $data->count(),'pageSize'=>isset($_REQUEST['per-page'])?$_REQUEST['per-page']:'50','params'=>$_REQUEST]);
	    $models = $data->offset($pages->offset)
	        ->limit($pages->limit)
	        ->all();
	    
	    $excelmodel	=	new Excelmodel();
// 	    $myexcelmodel	=	new MyExcelmodel();
// 	    $models	=	$myexcelmodel->findAllBySql($sql);
	    $model_sys	=	$excelmodel->find()->all();
	    
	    $excelmodels=array(''=>'????????????');
	    if(isset($model_sys)&&!empty($model_sys)){
	    	foreach ($model_sys as $m){
	    		$excelmodels[$m->id]=$m->name;
	    	}
	    }
	    
	    //??????????????????
	    $counter[OdOrder::STATUS_NOPAY]=OdOrder::find()->where('order_source = "ebay" and order_status = '.OdOrder::STATUS_NOPAY)->count();
	    $counter[OdOrder::STATUS_PAY]=OdOrder::find()->where('order_source = "ebay" and order_status = '.OdOrder::STATUS_PAY)->count();
	    $counter[OdOrder::STATUS_WAITSEND]=OdOrder::find()->where('order_source = "ebay" and order_status = '.OdOrder::STATUS_WAITSEND)->count();
	    $counter['all']=OdOrder::find()->where('order_source = "ebay"')->count();
	    $counter['guaqi']=OdOrder::find()->where('order_source = "ebay" and is_manual_order = 1')->count();
	    
	    $counter[OdOrder::EXCEP_WAITSEND]=OdOrder::find()->where('order_source = "ebay" and exception_status = '.OdOrder::EXCEP_WAITSEND.' and order_status < '.OdOrder::STATUS_WAITSEND)->count();
	    $counter[OdOrder::EXCEP_HASNOSHIPMETHOD]=OdOrder::find()->where('order_source = "ebay" and exception_status = '.OdOrder::EXCEP_HASNOSHIPMETHOD.' and order_status < '.OdOrder::STATUS_WAITSEND)->count();
	    $counter[OdOrder::EXCEP_PAYPALWRONG]=OdOrder::find()->where('order_source = "ebay" and exception_status = '.OdOrder::EXCEP_PAYPALWRONG.' and order_status < '.OdOrder::STATUS_WAITSEND)->count();
	    $counter[OdOrder::EXCEP_SKUNOTMATCH]=OdOrder::find()->where('order_source = "ebay" and exception_status = '.OdOrder::EXCEP_SKUNOTMATCH.' and order_status < '.OdOrder::STATUS_WAITSEND)->count();
	    $counter[OdOrder::EXCEP_NOSTOCK]=OdOrder::find()->where('order_source = "ebay" and exception_status = '.OdOrder::EXCEP_NOSTOCK.' and order_status < '.OdOrder::STATUS_WAITSEND)->count();
	    $counter[OdOrder::EXCEP_WAITMERGE]=OdOrder::find()->where('order_source = "ebay" and exception_status = '.OdOrder::EXCEP_WAITMERGE.' and order_status < '.OdOrder::STATUS_WAITSEND)->count();
	    $usertabs = Helper_Array::toHashmap(Helper_Array::toArray(Usertab::find()->all()),'id','tabname');
	    $warehouseids = InventoryApiHelper::getWarehouseIdNameMap();
	    $selleruserids=Helper_Array::toHashmap(SaasEbayUser::find()->where(['uid'=>\Yii::$app->user->identity->getParentUid()])->select(['selleruserid'])->asArray()->all(),'selleruserid','selleruserid');
	    
	    //?????????????????????????????????????????????
	    if (!empty($_REQUEST['order_status'])){
	    	$countrycode=OdOrder::getDb()->createCommand('select consignee_country_code from '.OdOrder::tableName().
	    			' where order_status = :os and order_source=:order_source group by consignee_country_code',[':os'=>$_REQUEST['order_status'],':order_source'=>'ebay'])->queryColumn();
	    }else{
	    	$countrycode=OdOrder::getDb()->createCommand('select consignee_country_code from '.OdOrder::tableName().' group by consignee_country_code')->queryColumn();
	    }
	    
// 	    $countrycode=OdOrder::getDb()->createCommand('select consignee_country_code from '.OdOrder::tableName().' group by consignee_country_code')->queryColumn();
	    $countrycode=array_filter($countrycode);
	    $countrys=Helper_Array::toHashmap(EbayCountry::find()->where(['country'=>$countrycode])->orderBy('description asc')->select(['country','description'])->asArray()->all(),'country','description');
		return $this->render('list',array(
			'models' => $models,
		    'pages' => $pages,
			'excelmodels'=>$excelmodels,
			'usertabs'=>$usertabs,
			'counter'=>$counter,
			'warehouseids'=>$warehouseids,
			'selleruserids'=>$selleruserids,
			'countrys'=>$countrys,
			'showsearch'=>$showsearch,
			'tag_class_list'=> OrderTagHelper::getTagColorMapping()
		));
		
	}
	/**
	 * ??????????????????ebay??????
	 * @author fanjs
	 */
	public function actionSendinvoice(){
		AppTrackerApiHelper::actionLog("Oms-ebay", "/order/sendinvoice");
		if (\Yii::$app->request->getIsPost()){
			$order = OdOrder::findOne($_POST['order_id']);
			$transactions = OdEbayTransaction::find()->where('order_id = :oi',[':oi'=>$_POST['order_id']])->all();
			$transaction = $transactions[0];
			$user = SaasEbayUser::find()->where('selleruserid = :s',[':s'=>$transaction->selleruserid])->one();
			$isinternational = $_POST['isinternational'];
			$siteid = $_POST['siteid'];
			
			//????????????
			$api = new sendinvoice();
			$api->resetConfig($user->DevAcccountID); 
			$api->siteID = $transaction->transactionsiteid;
			$api->eBayAuthToken = $user->token;
			$ids = [
					'ItemID' => $transaction->itemid,
					'TransactionID' => $transaction->transactionid
					];
			$shippingDetail = [
					'ShippingServiceCost' => $_POST['ShippingServiceCost'],
					'ShippingServiceAdditionalCost' => isset($_POST['ShippingServiceAdditionalCost'])?$_POST['ShippingServiceAdditionalCost']:'0.00',
					'ShippingService' => $_POST['ShippingService']
					];
			Helper_Array::removeEmpty ( $shippingDetail );
			if ($isinternational) {
				@$shipping ['InternationalShippingServiceOptions'] = $shippingDetail;
			} else {
				@$shipping ['ShippingServiceOptions'] = $shippingDetail;
			}
			$api->siteID = $siteid;
			$r = $api->api ( $ids, $shipping, $_POST['EmailCopyToSeller'],$_POST['CheckoutInstructions']);
			if ($api->responseIsSuccess ()) {
				$order->hassendinvoice=1;
				$order->save();
				foreach ( $transactions as $t ) {
					$t->sendinvoice ++;
					$t->save ();
				}
				return $this->render('//successview',['title'=>'??????eBay??????']);
			}else{
				return $this->render('sendinvoice',['error'=>$r['Errors']]);
			}
		}
		$error=[];
		if (!isset($_REQUEST['orderid'])){
			$error[]='??????????????????';
			return $this->render('sendinvoice',['error'=>$error]);
		}else{
			$order = OdOrder::findOne($_REQUEST['orderid']);
			if (is_null($order)){
				$error[]='????????????????????????';
			}
			if (is_null($order->order_source_order_id)){
				$error[]='????????????????????????SendInvoice';
			}
			$transactions = OdEbayTransaction::find()->where('order_id = :oi',[':oi'=>$_REQUEST['orderid']])->all();
			if (count($transactions)>1){
				$error[]='????????????????????????SendInvoice';
			}
			$transaction = $transactions[0];
			
			$site = EbaySite::find()->where('site =:site',['site'=> $transaction->transactionsiteid])->one();
			$siteid = $site->siteid!=100?$site->siteid:0;
				
	 		$shippingservices = EbayShippingservice::find()->where('siteid = :siteid and validforsellingflow=\'true\'',['siteid'=>$siteid] );
	 		// ??????????????????
	 		$isinternational = EbayShippingservice::find()->where('shippingservice = :s',[':s'=>$transaction->shippingserviceselected['ShippingService']])->one()->internationalservice;
			if ($isinternational) {
				$shippingservices->where ( 'internationalservice = "true"' );
			} else {
				$shippingservices->where ( 'internationalservice is null' );
			}
			return $this->render('sendinvoice',['shippingservices'=>$shippingservices->all(),
										'transaction'=>$transaction,
										'order'=>$order,
										'siteid'=>$siteid,
										'isinternational'=>$isinternational,
										'error'=>$error]);
		}
// 				if (count($myoo->transactions)>0 && strlen($myoo->ebay_orderid)>0){
// 					$ids = array (
// 							'OrderID' => $myoo->ebay_orderid
// 					);
// 				}elseif (request ( 'orderid' ) > 0) {
// 					$ids = array (
// 							'OrderID' => request ( 'orderid' )
// 					);
// 				} else {
// 					$ids = array (
// 							'ItemID' => $transaction->itemid,
// 							'TransactionID' => $transaction->transactionid
// 					);
// 				}
	}
	
		
	/**
	 * ????????????????????????????????????????????????,?????????????????????????????????????????????????????????
	 * @author fanjs
	 */
	public function actionSignshippedOld(){
		AppTrackerApiHelper::actionLog("Oms-erp", "/order/signshipped");
		if (\Yii::$app->request->getIsPost()){
			//??????????????????js???????????????
			if(empty($_REQUEST['js_submit'])){
				$tmpOrders = \Yii::$app->request->post()['order_id'];
			}else{
				$tmpOrders = json_decode($_REQUEST['order_id'], true);
			}
		}else {
			$tmpOrders = [\Yii::$app->request->get('order_id')];
		}
		
		if(empty($tmpOrders))
			return $this->render('//errorview',['title'=>'????????????','error'=>'?????????????????????']);
		$orders = OdOrder::find()->where(['in','order_id',$tmpOrders])->andwhere(['order_capture'=>'N'])->all();
		if (empty($orders))
			return $this->render('//errorview',['title'=>'????????????','error'=>'?????????????????????']);
		$allPlatform = []; // ????????????
		foreach ($orders as $key=>$order){
			if (!in_array($order->order_source ,$allPlatform )){
				$allPlatform[] = $order->order_source;
			}
		
			if('sm' == $order->order_relation){// ??????????????????????????????????????????????????????
				$father_orderids = OrderRelation::findAll(['son_orderid'=>$order->order_id , 'type'=>'merge' ]);
				foreach ($father_orderids as $father_orderid){
					$tmpOrders[] = $father_orderid->father_orderid;
					$orders[] = OdOrder::findOne($father_orderid->father_orderid);
				}
		
				unset($orders[$key]);
			}
		}
			
		$allShipcodeMapping = [];
		foreach ($allPlatform as $_platform){
			list($rt , $type)	  = \eagle\modules\delivery\apihelpers\DeliveryApiHelper::getShippingCodeByPlatform($_platform);
			if('ebay' == $_platform){// ebay ???????????????????????????
				$allShipcodeMapping[$_platform] = $rt;
			}else{// ??????????????????
				$tmpShippingMethod = DataStaticHelper::getUseCountTopValuesFor("erpOms_ShippingMethod" ,$rt );
				$shippingMethods = [];
				if(!empty($tmpShippingMethod['recommended'])){
					$shippingMethods += $tmpShippingMethod['recommended'];
					$shippingMethods[''] = '---??????/????????? ?????????---';
				}
				if(!empty($tmpShippingMethod['rest']))
					$shippingMethods += $tmpShippingMethod['rest'];
				$allShipcodeMapping[$_platform] = $shippingMethods;
			}
			
		}

		$logs = OdOrderShipped::findAll(['order_id'=>$tmpOrders]);
		return $this->render('signshipped',['orders'=>$orders,'logs'=>$logs,'allShipcodeMapping'=>$allShipcodeMapping]);
	}
	
	/**
	 * ????????????????????????????????????????????????,??????????????????
	 * @author fanjs
	 */
	public function actionSignshippedsubmit(){
		if (\Yii::$app->request->getIsPost()){
			$user = \Yii::$app->user->identity;
			$postarr = \Yii::$app->request->post();
			if (count($postarr['order_id'])){
				// ???????????????
				foreach ($postarr['order_id'] as $oid){
					if(empty($postarr['shipmethod'][$oid])){
						return exit(json_encode(array('code'=>'1', 'msg'=>'??????:?????????????????????!')));
// 						return $this->render('//errorview',['title'=>'????????????','error'=>'?????????????????????!']);
					}
				}
				$checkReport = '';
				
				foreach ($postarr['order_id'] as $oid){
					try {
						$order = OdOrder::findOne($oid);
						
						list($rt , $type)	  = \eagle\modules\delivery\apihelpers\DeliveryApiHelper::getShippingCodeByPlatform($order->order_source);
						if (!empty($rt[$postarr['shipmethod'][$oid]])){
							if(strtolower($postarr['shipmethod'][$oid]) == 'other' && $order->order_source == 'cdiscount')
								$shipMethodName = $postarr['othermethod'][$oid];
							else
								$shipMethodName = $rt[$postarr['shipmethod'][$oid]];
						}else{
							$shipMethodName='';
						}
						
						if($order->order_source == 'cdiscount' && empty($postarr['trackurl'][$oid]))
							$postarr['trackurl'][$oid] = CdiscountOrderInterface::getShippingMethodDefaultURL($postarr['shipmethod'][$oid]);
						
						$signtype = (empty($postarr['signtype']) || empty($postarr['signtype'][$oid]))?"all":$postarr['signtype'][$oid];
						$description = (empty($postarr['message']) || empty($postarr['message'][$oid]))?"":$postarr['message'][$oid];
						
						$logisticInfoList=[
							'0'=>[
								'order_source'=>$order->order_source,
								'selleruserid'=>$order->selleruserid,
								'tracking_number'=>$postarr['tracknum'][$oid],
								'tracking_link'=>$postarr['trackurl'][$oid],
								'shipping_method_code'=>$postarr['shipmethod'][$oid],
								'shipping_method_name'=>$shipMethodName,//?????????????????????
								'order_source_order_id'=>$order->order_source_order_id,
								'signtype'=>$signtype,
								'description'=>$description,
								'addtype'=>'??????????????????',
							]
						];
						
						// ???????????? ??????
						if($order->order_source == 'cdiscount'){
							$checkRT = \eagle\modules\order\helpers\CdiscountOrderInterface::preCheckSignShippedInfo($postarr['tracknum'][$oid],$order->order_source_shipping_method, $postarr['shipmethod'][$oid], $shipMethodName, $postarr['trackurl'][$oid]);
							if ($checkRT['success'] == false){
								$checkReport .= "<br> ????????????".$order->order_source_order_id." ???????????????". $checkRT['message'];
								continue;
							}
						}
						
						
						if(!OrderHelper::saveTrackingNumber($oid, $logisticInfoList,0,1)){
							\Yii::error(["Order",__CLASS__,__FUNCTION__,"Online",'??????'.$oid.'????????????'],'edb\global');
						}else{
							OperationLogHelper::log('order', $oid,'????????????','???????????????????????? [?????????]='.@$postarr['tracknum'][$oid] ." [????????????]=".@$postarr['trackurl'][$oid]." [????????????]=".@$postarr['shipmethod'][$oid]."($shipMethodName)"." [????????????]=".@$description,\Yii::$app->user->identity->getFullName());
							DataStaticHelper::addUseCountFor("erpOms_ShippingMethod", $postarr['shipmethod'][$oid],8);
							
							//??????????????????
							UserHelper::insertUserOperationLog('order', '????????????, ???????????????????????? [?????????]='.@$postarr['tracknum'][$oid] ." [????????????]=".@$postarr['trackurl'][$oid]." [????????????]=".@$postarr['shipmethod'][$oid]."($shipMethodName)"." [????????????]=".@$description);
						}
						
					}catch (\Exception $ex){
						\Yii::error(["Order",__CLASS__,__FUNCTION__,"Online","save to SignShipped failure:".print_r($ex->getMessage())],'edb\global');
					}
				}
				
				if(empty($checkReport)){
					return exit(json_encode(array('code'=>'0', 'msg'=>'???????????????')));
// 					echo "<script language='javascript'>alert('???????????????,??????????????????');window.close();</script>";
				}
				
				return exit(json_encode(array('code'=>'1', 'msg'=>$checkReport)));
// 				return $this->render('//successview',['title'=>'??????????????????','message'=>$checkReport]);
			}			
		}
	}

	/**
	 * ?????????????????????,??????????????????odorderitem.???????????????log
	 * @author fanjs;
	 */
	public function actionDeleteorder(){
		if (\Yii::$app->request->getIsPost()){
			if (count($_POST['order_id'])){
				AppTrackerApiHelper::actionLog("Oms-erp", "/order/deleteorder");

				try {
					OdOrder::deleteAll(['in','order_id',$_POST['order_id']]);
					OdOrderItem::deleteAll(['in','order_id',$_POST['order_id']]);
					foreach ($_POST['order_id'] as $orderid){
						OperationLogHelper::log('order', $orderid,'????????????','????????????????????????',\Yii::$app->user->identity->getFullName());
					}
					return $this->render('//successview',['title'=>'????????????']);
				}catch (\Exception $e){
					\Yii::error(["Order",__CLASS__,__FUNCTION__,"muti delete odorder failure:".print_r($e->getMessage())],'edb\global');
				}
			}
		}else{
			$orderids  =[];
			$orderids[]=$_GET['order_id'];
			if (count($orderids)){
				try {
					OdOrder::deleteAll(['in','order_id',$orderids]);
					OdOrderItem::deleteAll(['in','order_id',$orderids]);
					foreach ($orderids as $orderid){
						OperationLogHelper::log('order', $orderid,'????????????','????????????????????????',\Yii::$app->user->identity->getFullName());
					}
					return $this->render('//successview',['title'=>'????????????']);
				}catch (\Exception $e){
					\Yii::error(["Order",__CLASS__,__FUNCTION__,"muti delete odorder failure:".print_r($e->getMessage())],'edb\global');
				}
			}
		}
	}
	
	
	
	/**
	 * ??????????????????
	 * @author fanjs
	 */
	public function actionImportordertracknum(){
		if (\yii::$app->request->isPost){
			//???????????????????????????OMS??????????????????????????????????????????
			if(!empty($_REQUEST['paltform'])) 
			    $platform = $_REQUEST['paltform'];
			else 
			    $platform = '';
			
			//????????????????????????????????????
			if(!empty($_REQUEST['autoship']))
				$autoship = $_REQUEST['autoship'];
			else
				$autoship = '';
			
			//????????????????????????
			if(!empty($_REQUEST['autoComplete']))
				$autoComplete = $_REQUEST['autoComplete'];
			else
				$autoComplete = '';
			
			
			AppTrackerApiHelper::actionLog("Oms-".$platform, "/order/tracknum");
			
			AppTrackerApiHelper::actionLog("Oms-erp", "/order/tracknum");
			if (isset($_FILES['order_tracknum'])){
				try {
					$result = OrderHelper::importtracknumfromexcel($_FILES['order_tracknum'] ,$platform, $autoship,$autoComplete);
					return $result;
				}catch(\Exception $e){
					return $e->getMessage();
				}
			}
		}
	}
	
	/**
	 * ??????????????????
	 * @author fanjs
	 */
	public function actionImportordertracknumcommon(){
		if (\yii::$app->request->isPost){
			if (!empty($_REQUEST['paltform'])) 
			    $platform = $_REQUEST['paltform'];
			else 
			    $platform = "";
			AppTrackerApiHelper::actionLog("Oms-".$platform, "/order/tracknum");
			
			if (isset($_FILES['order_tracknum'])){
				try {
					$result = OrderHelper::importtracknumfromexcel($_FILES['order_tracknum']);
					return $result;
				}catch(\Exception $e){
					return $e->getMessage();
				}
			}
		}
	}
	
	/**
	 * ?????????????????????
	 * @author fanjs
	 */
	public function actionMovestatus(){
		AppTrackerApiHelper::actionLog("Oms-erp", "/order/movestatus");
		if (\yii::$app->request->isPost){
			$message = '';
			$orderids = explode(',',$_POST['orderids']);
			$orderids = array_filter($orderids);
			if (count($orderids)){
				foreach ($orderids as $orderid){
					$order = OdOrder::findOne($orderid);
					
					//????????????????????????  liang 2015-12-26
					if($order->order_status!==$_POST['status']){//????????????????????????????????????weird_status
						OperationLogHelper::log('order', $orderid,'????????????','????????????????????????,??????:'.OdOrder::$status[$order->order_status].'->'.OdOrder::$status[$_POST['status']].(empty($order->weird_status)?'':',??????????????????????????????'),\Yii::$app->user->identity->getFullName());
						$order->weird_status = '';
					}else{
						OperationLogHelper::log('order', $orderid,'????????????','????????????????????????,??????:'.OdOrder::$status[$order->order_status].'->'.OdOrder::$status[$_POST['status']],\Yii::$app->user->identity->getFullName());
					}//????????????????????????  end
					
					$order->order_status = $_POST['status'];
					$order->save();
				}
			}
			return 'success';
		}
	}
	
	/**
	 * ???????????????????????????
	 * @author fanjs
	 */
	public function actionChangemanual(){
		if (\yii::$app->request->isPost){
			AppTrackerApiHelper::actionLog("Oms-erp", "/order/changemanual");
			$order = OdOrder::findOne($_POST['orderid']);
			if (empty($order)){
				return '?????????????????????';
			}
			if ($order->is_manual_order == 0){
				$order->is_manual_order = 1;
				//??????2.1 ????????????
				$rt = OrderApiHelper::suspendOrders([$order->order_id]);
				if ($rt['success'] ==false){
					return $rt['message'];
				}
				
			}else{
				$order->is_manual_order = 0;
				$order->order_status = OdOrder::STATUS_PAY;
				$order->save(false);
			}
			return 'success';
		}
	}
	
	/**
	 * ???????????????????????????
	 * @author fanjs
	 */
	public function actionUsertab(){
		$uids = [\Yii::$app->user->id,\Yii::$app->user->identity->getParentUid()];
		$tabs = Usertab::findAll(['uid'=>$uids]);
		return $this->render('usertab',['tabs'=>$tabs]);
	}
	
	/**
	 * ???????????????????????????
	 * @author fanjs
	 */
	public function actionEdittab(){
		AppTrackerApiHelper::actionLog("Oms-ebay", "/order/edittab");
		if(\Yii::$app->request->isPost){
			if (isset($_POST['templateid'])){
				$template = Usertab::findOne($_POST['templateid']);
			}else{
				$template = new Usertab();
			}
			try {
				$template->tabname = $_POST['tabname'];
				$template->uid = \Yii::$app->subdb->getCurrentPuid();
				$template->save();
				return $this->actionUsertab();
			}catch (\Exception $e){
				print_r($e->getMessage());
			}
		}
		if(isset($_GET['id'])&&$_GET['id']>0){
			$template = Usertab::findOne($_GET['id']);
		}else{
			$template = new Usertab();
		}
		return $this->renderPartial('tabedit',['template'=>$template]);
	}
	
	/**
	 * ???????????????????????????
	 * @author fanjs
	 */
	public function actionDeletetab(){
		if(\Yii::$app->request->isPost){
			try {
				Usertab::deleteAll('id = '.$_POST['id']);
				return 'success';
			}catch (Exception $e){
				return print_r($e->getMessage());
			}
		}
	}
	
	/**
	 * ???????????????????????????
	 * @author fanjs
	 */
	public function actionSetusertab(){
		AppTrackerApiHelper::actionLog("Oms-erp", "/order/settab");
		if(\Yii::$app->request->isPost){
			try {
				$order = OdOrder::findOne($_POST['orderid']);
				$order->order_manual_id = $_POST['tabid'];
				if ($order->save()){
					return 'success';
				}else{
					return '???????????????????????????';
				}
			}catch (Exception $e){
				return print_r($e->getMessage());
			}
		}
	}
	
	/**
	 * ????????????????????????
	 * @author fanjs
	 */
	public function actionAjaxdesc(){
		AppTrackerApiHelper::actionLog("Oms-erp", "/order/desc");
		if(\Yii::$app->request->isPost){
// 			$item = OdOrderItem::findOne($_POST['oiid']);
// 			if (!empty($item)){
// 				$olddesc = $item->desc;
// 				$item->desc = $_POST['desc'];
// 				$item->save();
// 				OperationLogHelper::log('order',$item->order_id,'????????????','????????????: ('.$olddesc.'->'.$_POST['desc'] .')',\Yii::$app->user->identity->getFullName());
// 				$ret_array = array (
// 						'result' => true,
// 						'message' => '????????????'
// 				);
// 				echo json_encode ( $ret_array );
// 				exit();
// 			}
			$order = OdOrder::findOne($_POST['oiid']);
			if (!empty($order)){
				$olddesc = $order->desc;
				$order->desc = $_POST['desc'];
				$order->save();
				OperationLogHelper::log('order',$order->order_id,'????????????','????????????: ('.$olddesc.'->'.$_POST['desc'] .')',\Yii::$app->user->identity->getFullName());
				$ret_array = array (
						'result' => true,
						'message' => '????????????'
				);
				echo json_encode ( $ret_array );
				exit();
			}
		}
	}
	
	/**
	 * ????????????
	 * @author fanjs
	 */
	public function actionEdit(){
		//overdue
		return false;// 20161012
		$url = '/order/ebay-order/edit?orderid='.$_REQUEST['orderid'];
		return $this->redirect($url);
		AppTrackerApiHelper::actionLog("Oms-ebay", "/order/edit");
		if (\Yii::$app->request->isPost){
			if (count($_POST['item']['product_name'])==0){
				return $this->render('//errorview','???????????????????????????');
			}
			$order = OdOrder::findOne($_POST['orderid']);
			if (empty($order)){
				return $this->render('//errorview','???????????????');
			}
			$item_tmp = $_POST['item'];
			$_tmp = $_POST;
			unset($_tmp['orderid']);
			unset($_tmp['item']);
			if (!empty($_tmp['default_shipping_method_code'])){
				$serviceid = SysShippingService::findOne($_tmp['default_shipping_method_code']);
				if (!empty($serviceid)||!$serviceid->isNewRecord){
					$_tmp['default_shipping_method_code']=$_tmp['default_shipping_method_code'];
					$_tmp['default_carrier_code']=$serviceid->carrier_code;
				}
			}
			$old_status = $order->order_status;
			$order->setAttributes($_tmp);
			$new_status = $order->order_status;
			$order->save();
			//????????????????????????
			foreach ($item_tmp['product_name'] as $key=>$val){
				if (strlen($item_tmp['itemid'][$key])){
					$item = OdOrderItem::findOne($item_tmp['itemid'][$key]);
				}else{
					$item = new OdOrderItem();
				}
				$item->order_id = $order->order_id;
				$item->product_name = $item_tmp['product_name'][$key];
				$item->sku = $item_tmp['sku'][$key];
				$item->ordered_quantity = $item_tmp['quantity'][$key];
				$item->quantity = $item_tmp['quantity'][$key];
				$item->order_source_srn = $item_tmp['order_source_srn'][$key];
				$item->price = $item_tmp['price'][$key];
				$item->update_time = time();
				$item->create_time = is_null($item->create_time)?time():$item->create_time;
				$item->save();
			}
			$order->checkorderstatus();
			//??????weird_status liang 2015-12-26 
			if($old_status!==$new_status && ($new_status!==500 ||$new_status!==600) ){
				$addtionLog = '';
				if(!empty($order->weird_status))
					$addtionLog = ',?????????????????????????????????';
				$order->weird_status = '';
			}//??????weird_status end
			$order->save();
			OperationLogHelper::log('order',$order->order_id,'????????????','??????????????????????????????'.$addtionLog,\Yii::$app->user->identity->getFullName());
			echo "<script language='javascript'>window.opener.location.reload();window.close();</script>";
//			return $this->render('//successview',['title'=>'????????????']);
		}
		if (!isset($_GET['orderid'])){
			return $this->render('//errorview',['title'=>'????????????','message'=>'????????????']);
		}
		$order = OdOrder::findOne($_GET['orderid']);
		$paypal_t = OdPaypalTransaction::findOne(['order_id'=>$order->order_id]);
		if (empty($order)||$order->isNewRecord){
			return $this->render('//errorview',['title'=>'????????????','message'=>'?????????????????????']);
		}
		return $this->render('edit',['order'=>$order,'paypal'=>$paypal_t,'countrys'=>StandardConst::$COUNTRIES_CODE_NAME_EN]);
	}
	
	
	
	/**
	 * ???????????????????????????
	 * @author fanjs
	 */
	public function actionCheckorderstatus(){
		AppTrackerApiHelper::actionLog("Oms-erp", "/order/checkstatus");
		if (\Yii::$app->request->isPost){
			$orderids = explode(',',$_POST['orders']);
			Helper_Array::removeEmpty($orderids);
			if (count($orderids)>0){
				try {
					$list_clear_platform = []; //????????????redisear_platform
					foreach ($orderids as $orderid){
						
						$order = OdOrder::findOne($orderid);
						$origin_exception_status = $order->exception_status;
						if ($order->order_status=='200'){
							$order->checkorderstatus(null,1);
							if ($order->save(false)){
								//???????????? ??????redis
								if ((!in_array($order->order_source, $list_clear_platform) )&& ( $order->exception_status != $origin_exception_status)){
									$list_clear_platform[] = $order->order_source;
								}
							}
						}
					}
					
					//left menu ??????redis
					if (!empty($list_clear_platform)){
						foreach ($list_clear_platform as $platform){
							//echo "$platform is reset !";
							RedisHelper::delOrderCache(\Yii::$app->subdb->getCurrentPuid(),$platform,'Menu StatisticData');
						}
						//OrderHelper::clearLeftMenuCache($list_clear_platform);
					}
					return '???????????????';
				}catch (\Exception $e){
					return $e->getMessage();
				}
			}else{
				return '????????????????????????';
			}
		}
	}
	
	/**
	 * ????????????
	 * @author fanjs
	 */
	public function actionMergeorder(){
		AppTrackerApiHelper::actionLog("Oms-erp", "/order/mergeorder");
		if (\Yii::$app->request->isPost){
			$orderIdList =  $_POST['order_id'];
			$rt = OrderHelper::mergeOrder($orderIdList);
			
			if ($rt['success'] ==false){
				return $this->render('//errorview',['title'=>'????????????','error'=>$rt['message']]);
			}else{
				echo "<script language='javascript'>alert('Success');window.opener.location.reload();window.close();</script>";
			}
			return;
		}
	}
	
	/**
	 +----------------------------------------------------------
	 * ??????????????????   action
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/06/02				?????????
	 +----------------------------------------------------------
	 **/
	public function actionCancelMergeOrder(){
		if (!empty($_POST['order_id'])){
			$orderIdList =  $_POST['order_id'];
			$rt = OrderHelper::RollbackmergeOrder($orderIdList);
			if($rt['success'] == false){
				return $this->renderJson(['response'=>['success'=>false,'code'=>400,'type'=>'message','timeout'=>2,'message'=>$rt['message']]]);
			}else{
				return $this->renderJson(['response'=>['success'=>true,'code'=>200,'type'=>'message','timeout'=>2,'message'=>'????????????????????????????????????????????????????????????','reload'=>true]]);
			}
		}else{
			return $this->renderJson(['response'=>['success'=>false,'code'=>400,'type'=>'message','timeout'=>2,'message'=>'????????????????????????']]);
		}
		
	}
	
	/**
	 * ????????????
	 * @author fanjs
	 */
	public function actionSplitorder(){
		AppTrackerApiHelper::actionLog("Oms-ebay", "/order/splitorder");
		if(\Yii::$app->request->isPost){
			$oldorder = OdOrder::findOne($_POST['orderid']);
			$orderarr = $oldorder->attributes;
			unset($orderarr['order_id']);
			$orderarr['create_time']=time();
			$orderarr['update_time']=time();
			$neworder = new OdOrder();
			//20151119 instead of setAttributes
			$attrs = $oldorder->attributes();
			foreach($orderarr as $k=>$v){
				if(in_array($k,$attrs)) {
					$neworder->$k = $v;
				}
			}
			//20151119 instead of setAttributes
			//$neworder->setAttributes($orderarr);
			$neworder->subtotal=$_POST['new_subtotal'];
			$neworder->shipping_cost=$_POST['new_shipping_cost'];
			$neworder->grand_total=$_POST['new_grand_total'];
			if($neworder->save(false)){
				$oldorder->subtotal=$_POST['old_subtotal'];
				$oldorder->shipping_cost=$_POST['old_shipping_cost'];
				$oldorder->grand_total=$_POST['old_grand_total'];
				$oldorder->save(false);
				//?????????????????????ID????????????
				foreach ($_POST['item'] as $key=>$val){
					if ($val=='new'){
						$item = OdOrderItem::findOne($key);
						$item->order_id = $neworder->order_id;
						$item->save(false);
					}
				}
				OperationLogHelper::log('order', $neworder->order_id,'????????????','????????????'.$oldorder->order_id.'?????????????????????',\Yii::$app->user->identity->getFullName());
				OperationLogHelper::log('order', $oldorder->order_id,'????????????','??????????????????,??????????????????'.$neworder->order_id,\Yii::$app->user->identity->getFullName());
				return $this->render('//successview',['title'=>'????????????']);
			}else{
				return $this->render('//errorview',['title'=>'????????????','error'=>'?????????????????????']);
			}
		}
		if (!isset($_GET['orderid'])){
			return $this->render('//errorview',['title'=>'????????????','error'=>'???????????????ID??????']);
		}
		$order = OdOrder::findOne($_GET['orderid']);
		if (empty($order)||$order->isNewRecord){
			return $this->render('//errorview',['title'=>'????????????','error'=>'?????????????????????']);
		}
		if (count($order->items)<2){
			return $this->render('//errorview',['title'=>'????????????','error'=>'????????????????????????2?????????']);
		}
		return $this->render('splitorder',['order'=>$order]);
	}
	
	/**
	 * ?????????????????????????????????????????????????????????
	 * @author fanjs
	 */
	function actionSignwaitsend(){
		AppTrackerApiHelper::actionLog("Oms-erp", "/order/signwaitsend");
		if (\Yii::$app->request->isPost){
			$orderids = explode(',',$_POST['orders']);
			Helper_Array::removeEmpty($orderids);
			if (count($orderids)>0){
				$rt = OrderApiHelper::setOrderShipped($orderids);
				if ($rt['success'] == count($orderids)){
					return "????????????";
					/*
					return "?????????<br>???????????????".$rt['success']."??????????????????????????????<br>".$rt['message']."<br>?????????????????????????????????????????????????????????????????????????????????????????????????????????
					<br>1?????????????????????
					<br>2???????????????
					<br>3??????????????????-??????????????????
					<br>4???????????????
					<br>5????????????";
					*/
				}else{
					return nl2br($rt['message']);
				}
				/*
				if ($rt['success'] == count($orderids)){
					return "????????????";
				}else{
					return nl2br($rt['message']);
				}
				*/
			}else{
				return '????????????????????????';
			}
		}
	}
	
	/**
	 * ????????????????????????????????????
	 * @author fanjs
	 */
	function actionGetOneTagInfo(){
		$tagdata = [];
		if (!empty($_REQUEST['order_id'])){
			$tagdata = OrderTagHelper::getALlTagDataByOrderId($_REQUEST['order_id']);
		}
		exit(json_encode($tagdata));
	}
	
	/**
	 * ??????????????????????????????
	 * @author fanjs
	 */
	function actionSaveOneTag(){
		AppTrackerApiHelper::actionLog("Oms-erp", "/order/savetab");
		if (!empty($_REQUEST['order_id'])){
			$order_id = $_REQUEST['order_id'];
		}else{
			exit(json_encode(['success'=>false, 'message'=>'????????????1']));
		}
		/*
		if (!empty($_REQUEST['tag_name'])){
			$tag_name = $_REQUEST['tag_name'];
		}else{
			exit(json_encode(['success'=>false, 'message'=>'????????????2']));
		}
		*/
		if (!empty($_REQUEST['operation'])){
			$operation = strtolower($_REQUEST['operation']);
		}else{
			exit(json_encode(['success'=>false, 'message'=>'????????????3']));
		}
		
		if (!empty($_REQUEST['color'])){
			$color = strtolower($_REQUEST['color']);
		}else{
			exit(json_encode(['success'=>false, 'message'=>'????????????4']));
		}
		
		$tag_name = empty($_REQUEST['tag_name']) ? '' : trim($_REQUEST['tag_name']);
		
		$result = OrderTagHelper::saveOneOrderTag($order_id, $tag_name, $operation, $color);
		exit(json_encode($result));
	}
	
	/**
	 * ???????????????????????????
	 * @author fanjs
	 */
	function actionUpdateOrderTrInfo(){
		if (!empty($_REQUEST['order_id'])){
				$row = OrderTagHelper::generateTagIconHtmlByOrderId($_REQUEST['order_id']);
				$sphtml['sphtml'] = $row;
				exit(json_encode($sphtml));
		}
	}
	
	/**
	 * ???????????????????????????(?????????)
	 * @author lzhl
	 */
	function actionUpdateOrderTagHtml(){
		if (!empty($_REQUEST['order_id'])){
			$TagStr = OrderTagHelper::generateTagIconHtmlByOrderId($_REQUEST['order_id']);
		}else 
			$TagStr = '';

		if (!empty($TagStr)){
			$TagStr = "<span class='btn_order_tag_qtip' data-order-id='".$_REQUEST['order_id']."' >$TagStr</span>";
		}
		exit($TagStr);
	}
	
	/**
	 * ??????????????????????????????
	 * @author fanjs
	 */
	function actionSendmessage(){
		if (\Yii::$app->request->isPost){
			AppTrackerApiHelper::actionLog("Oms-ebay", "/order/sendmessage");
			if (empty($_POST['orderid'])){
				return '????????????????????????ID';
			}
			$order = OdOrder::findOne(['order_id'=>$_POST['orderid']]);
			return $this->renderPartial('sendmessage',['order'=>$order]);
		}
	}
	
	/**
	  * ????????????????????????????????????
	 * @author fanjs
	 */
	function actionAjaxsendmessage(){
		if(\Yii::$app->request->isPost){
			if (empty($_POST['orderid'])){
				return '????????????????????????ID';
			}
			$order = OdOrder::findOne($_POST['orderid']);
			$item=OdEbayTransaction::findOne(['order_id'=>$_POST['orderid']]);
			$itemid = $item->itemid;
			$buyer = $order->source_buyer_user_id;
			$api = new addmembermessageaaqtopartner();
			$ebayuser = SaasEbayUser::find()->where('selleruserid=:s',[':s'=>$order->selleruserid])->one ();
			$api->resetConfig($ebayuser->DevAcccountID); //????????????
			$token = $ebayuser->token;
			$result = $api->api ($token,$itemid,$_POST['content'],$_POST['type'],$buyer,$_POST['title'],$_POST['mail']);
			if ($api->responseIsSuccess ()){
				return 'success';
			}else{
				return $result['Errors']['LongMessage'];
			}
		}
	}
	
	
	
	/**
	 * ajax???????????????????????????????????????
	 * @author fanjs
	 */
	function actionAjaxsyncmt(){
		if (\Yii::$app->request->isPost){
			try {
				QueueGetorder::updateAll(['updated'=>'1'],'status !=2 and selleruserid ="'.$_POST['selleruserid'].'"');
			}catch (Exception $e){
				return json_encode(['ack'=>'failure','msg'=>$e->getMessage()]);
			}
			return json_encode(['ack'=>'success']);
		}
	}
	
	/**
	 * ??????????????????
	 * @author
	 */
	function actionAddOrderSelf(){
		if(\Yii::$app->request->isPost){
			if (count($_POST['item']['product_name'])==0){
				return $this->render('//errorview','???????????????????????????');
			}
			$order = new OdOrder();
			$item_tmp = $_POST['item'];
			$_tmp = $_POST;
			unset($_tmp['item']);
			if (!empty($_tmp['default_shipping_method_code'])){
				$serviceid = SysShippingService::findOne($_tmp['default_shipping_method_code']);
				if (!empty($serviceid)||!$serviceid->isNewRecord){
					$_tmp['default_shipping_method_code']=$_tmp['default_shipping_method_code'];
					$_tmp['default_carrier_code']=$serviceid->carrier_code;
				}
			}
			$order->setAttributes($_tmp);
			$order->save();
			//????????????????????????
			foreach ($item_tmp['product_name'] as $key=>$val){
				if (strlen($item_tmp['itemid'][$key])){
					$item = OdOrderItem::findOne($item_tmp['itemid'][$key]);
				}else{
					$item = new OdOrderItem();
				}
				if(strlen($val)==0){
					continue;
				}
				$item->order_id = $order->order_id;
				$item->product_name = $item_tmp['product_name'][$key];
				$item->sku = $item_tmp['sku'][$key];
				$item->ordered_quantity = $item_tmp['quantity'][$key];
				$item->quantity = $item_tmp['quantity'][$key];
				$item->price = $item_tmp['price'][$key];
				$item->update_time = time();
				$item->create_time = is_null($item->create_time)?time():$item->create_time;
				$item->save();
			}
//			$order->checkorderstatus();
			$order->save();
			OperationLogHelper::log('order',$order->order_id,'??????????????????','??????????????????',\Yii::$app->user->identity->getFullName());
//			echo "<script language='javascript'>window.opener.location.reload();window.close();</script>";
			echo "<script language='javascript'>alert('?????????');</script>";
		}
		return $this->render('addorderself');
	}
	
	/**
	 * ??????action???
	 */
	function actionTest(){
		$odorder = OdOrder::findOne(['order_source'=>'ebay','order_source_order_id'=>'123321']);
		var_dump(empty($odorder));die();
//		$odOrder = empty($odOrder) ?OdOrder::find()->where("`order_source` = :os AND `order_source_order_id` = :osoi",[':os'=>$os['order_source'],':osoi'=>$os['order_source_order_id']])->one(): '';
	}
	
	/**
	 * order/update-order-address
	 */
	function actionUpdateOrderAddress(){
		$selleruserid=$_GET['selleruserid'];
		$orderid=$_GET['orderid'];
		if(empty($orderid)) die('No orderid Input .');
		if($selleruserid){
			$eu=SaasEbayUser::findOne(['selleruserid'=>$selleruserid]);
		}else{
			die('No selleruserid Input .');
		}
	
		$api=new getorders();
		$api->resetConfig($eu->DevAcccountID);
		$api->eBayAuthToken=$eu->token;
		$api->_before_request_xmlarray['OrderID']=$orderid;
		$r=$api->api();
		echo "<pre>";
		print_r(@$r['OrderArray']['Order']['ShippingAddress']);
		echo "</pre>";
		/**/
		if (isset($r['OrderArray']['Order']['ShippingAddress'])){
			//$orderModel = OdOrder::find()->where(['order_source_order_id'=>$orderid ])->andWhere(['order_capture'=>'N'])->One();
			$orderModel = $_GET['erp_order_id'];
			echo "orderid = ".$orderModel->order_id;
			$addressArr = $r['OrderArray']['Order']['ShippingAddress'];
			$paypalAddress = [
			'consignee'=>$addressArr['Name'],
			//'consignee_email'=>$PT->email,
			'consignee_country'=>empty($addressArr['CountryName'])?$addressArr['Country']:$addressArr['CountryName'],
			'consignee_country_code'=>$addressArr['Country'],
			'consignee_province'=>$addressArr['StateOrProvince'],
			'consignee_city'=>$addressArr['CityName'],
			'consignee_address_line1'=>$addressArr['Street1'],
			'consignee_address_line2'=>$addressArr['Street2'],
			'consignee_postal_code'=>$addressArr['PostalCode'],
			
			];
			$updateRt = \eagle\modules\order\helpers\OrderUpdateHelper::updateOrder($orderModel, $paypalAddress , false , 'System','????????????','order');
			
			echo "<pre>";
			print_r($updateRt);
			echo "</pre>";
		}
		
		die;
	}

	
	/**
	 +----------------------------------------------------------
	 * ????????????????????????   action
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2015/11/18				?????????
	 +----------------------------------------------------------
	 **/
	public function actionChangeshipmethod(){
		if (!empty($_POST['orderIDList']) && !empty($_POST['shipmethod']) ){
			AppTrackerApiHelper::actionLog("Oms-erp", "/order/changeshipmethod");
			$serviceid = SysShippingService::findOne($_POST['shipmethod']);
			if (!empty($serviceid)||!$serviceid->isNewRecord){
				$rt = OdOrder::updateAll(['default_shipping_method_code'=>$_POST['shipmethod'] , 'default_carrier_code'=>$serviceid->carrier_code ] ,['order_id'=>$_POST['orderIDList']]);
				if (!empty($rt)){
					
					exit(json_encode(['success'=>true,'message'=>'']));
				}else
					exit(json_encode(['success'=>false,'message'=>'???????????????']));
			}
			exit(json_encode(['success'=>false,'message'=>'?????????????????????????????????']));
			
		}
		exit(json_encode(['success'=>false,'message'=>'?????????????????????????????????']));
	}//end of actionChangeshipmethod
	
	/**
	 +----------------------------------------------------------
	 * ????????????????????????   action
	 +----------------------------------------------------------
	 * @access 		public
	 * @params 		$order_id		??????id
	 * @params		$app			?????????app????????????oms
	 +----------------------------------------------------------
	 * log			name	date			note
	 * @author		lzhl 	2015/12/18		?????????
	 +----------------------------------------------------------
	 **/
	public function actionOrderInvoice($order_id,$app='oms'){
		
		if(strtolower($app)=='oms')
			$uid = \Yii::$app->subdb->getCurrentPuid();
		else{
			$parmaStr = (isset($_GET['parcel']))?$_GET['parcel']:'';
			$parmaStr = MessageHelper::decryptBuyerLinkParam($parmaStr);
			if(empty($parmaStr)){
				exit('????????????!');
			}else{
				$parmas = explode('-', $parmaStr,2);
				if(count($parmas)<2){
					exit('????????????!');
				}else{
					$uid = $parmas[0];
					$order_id = $parmas[1];
				}
			}
		}
		if (empty($uid)){
			//????????????
			return $this->render('//errorview',['title'=>'????????????','message'=>'???????????????????????????????????????']);
		}
		
		
		$mpdf=new \HTML2PDF('P','A4','en');
		
		if(is_string($order_id)){
			$order_id = str_replace(';', ',', $order_id);
			$order_id_arr = explode(',', $order_id);
		}
		
		foreach ($order_id_arr as $order_id){
			//??????????????????
			$orderModel = OdOrder::findOne($order_id);
			if(!empty($orderModel->consignee_country_code)){
				$toCountry = SysCountry::findOne(strtoupper($orderModel->consignee_country_code));
				//?????????????????????
				if(!empty($toCountry->region) && in_array($toCountry->region, ['Asia','Southeast Asia']))
					$mpdf->setDefaultFont('droidsansfallback');
				//????????????????????????
				if(in_array($orderModel->consignee_country_code,['TH','LA'])){
					$mpdf->setDefaultFont('angsau');
				}
			}
			$text = OrderHelper::pdf_order_invoice($order_id);
			//exit($text);	//test liang
			$mpdf->WriteHTML($text);
		}
		
		$mpdf->Output('order_invoice_'.$orderModel->order_source_order_id.'.pdf');
		exit();
	}
	
	/**
	 +----------------------------------------------------------
	 * ????????????????????????   action
	 +----------------------------------------------------------
	 * @access 		public
	 * @params 		$orderids		??????id??????
	 * @params		$app			?????????app????????????gaoqing
	 +----------------------------------------------------------
	 * log			name	date			note
	 * @author		lrq 	2016/07/29		?????????
	 +----------------------------------------------------------
	 **/
	public function actionOrderlistInvoice($orderids,$type='G')
	{
		$mpdf=new \HTML2PDF('P','A4','en');
		$orderidlist = explode(',',$orderids);
		Helper_Array::removeEmpty($orderidlist);
		if (count($orderids)>0)
		{
			foreach ($orderidlist as $order_id)
			{
				
				//??????????????????
				$orderModel = OdOrder::findOne($order_id);
				if(!empty($orderModel->consignee_country_code)){
					$toCountry = SysCountry::findOne(strtoupper($orderModel->consignee_country_code));
					if(!empty($toCountry->region) && in_array($toCountry->region, ['Asia','Southeast Asia']))
						$mpdf->setDefaultFont('droidsansfallback');
				}
				$text = OrderHelper::pdf_order_invoice($order_id, $type);
				$mpdf->WriteHTML($text);
			}
			
			$mpdf->Output('order_invoice_'.$orderModel->order_source_order_id.'.pdf');
		}
		exit();
	}
	
	public function actionProfitOrder(){
		$uid = \Yii::$app->subdb->getCurrentPuid();
		if (empty($uid)){//????????????
			exit('???????????????????????????????????????');
		}
 
		if(empty($_REQUEST['order_ids'])){
			exit('????????????????????????????????????');
		}
		
		$order_ids = [];
		if(is_string($_REQUEST['order_ids']))
			$order_ids = explode(',',$_REQUEST['order_ids']);
		elseif(is_array($_REQUEST['order_ids']))
			$order_ids = $_REQUEST['order_ids'];
		
		$check = OrderProfitHelper::checkOrdersBeforProfit($order_ids);
		//$check['success']=true;
		
		if(!empty($check['success']) && empty($check['data']['need_set_price']) && empty($check['data']['need_logistics_cost']) ){
			//????????????????????????????????????????????????????????????????????????????????????	
			$result = OrderProfitHelper::profitOrderByOrderId($order_ids,1);
			if($result['success'])
				return '???????????????????????????????????????????????????';
			else 
				return '??????????????????????????????'.$result['message'].'???????????????????????????';
		}else{
			return $this->renderAjax('_set_orders_cost',[
					'order_ids'=>$order_ids,
					'need_set_price'=>empty($check['data']['need_set_price'])?[]:$check['data']['need_set_price'],
					'need_logistics_cost'=>empty($check['data']['need_logistics_cost'])?[]:$check['data']['need_logistics_cost'],
					'exchange_data' => empty($check['data']['exchange'])?[]:$check['data']['exchange'],
					'exchange_loss' => empty($check['data']['exchange_loss'])?[]:$check['data']['exchange_loss'],
				]);
		}
	}
	
	public function actionSetCostAndProfitOrder(){
		AppTrackerApiHelper::actionLog("Oms-erp", "/order/setCostAndProfitOrder");
		$data = $_POST;
		$order_ids = explode(',', $_POST['order_ids']);
		$journal_id = SysLogHelper::InvokeJrn_Create("Order",__CLASS__, __FUNCTION__ , array($order_ids,$data));
		$rtn = OrderProfitHelper::setOrderCost($data, $journal_id);
		if($rtn['success']){//???????????????????????????????????????????????????????????????
			
			$price_type = empty($_POST['price_type'])?0:1;
			$rtn = OrderProfitHelper::profitOrderByOrderId($order_ids,$price_type);
			
			$rtn['calculated_profit'] = true;
			
		}else{//????????????????????????????????????????????????????????????????????????????????????
			$rtn['calculated_profit'] = false;
		}
		exit(json_encode($rtn));
	}
	
	public function actionExcel2OrderCost(){
		AppTrackerApiHelper::actionLog("Oms-erp", "/order/excel2OrderCost");
		if (!empty ($_FILES["input_import_file"]))
			$files = $_FILES["input_import_file"];
		else 
			exit(json_encode(['success'=>false,'message'=>'?????????????????????']));
		
		$type = empty($_REQUEST['type'])?'':trim($_REQUEST['type']);
		if(empty($type) || ($type!=='product_cost' && $type!=='logistics_cost')){
			exit(json_encode(['success'=>false,'message'=>'?????????????????????????????????????????????']));
		}
		try {
			if($type=='product_cost'){
				$EXCEL_PRODUCT_COST_COLUMN_MAPPING = OrderProfitHelper::get_EXCEL_PRODUCT_COST_COLUMN_MAPPING();
				$productsData = \eagle\modules\util\helpers\ExcelHelper::excelToArray($files, $EXCEL_PRODUCT_COST_COLUMN_MAPPING );
				
				$result = ProductApiHelper::importProductCostData($productsData);
			}
			if($type=='logistics_cost'){
				$ORDER_LOGISTICS_COST_COLUMN_MAPPING = OrderProfitHelper::get_EXCEL_ORDER_LOGISTICS_COST_COLUMN_MAPPING();
				$logisticsData = \eagle\modules\util\helpers\ExcelHelper::excelToArray($files, $ORDER_LOGISTICS_COST_COLUMN_MAPPING );
			
				$result = OrderProfitHelper::importOrderLogisticsCostData($logisticsData);
			}
		}
		catch (Exception $e) {
			SysLogHelper::SysLog_Create('Order',__CLASS__, __FUNCTION__,'error',$e->getMessage());
			$result = ['success'=>false,'message'=>'E001??????????????????????????????????????????????????????'];
		}
		exit(json_encode($result));
	}
	public function actionOmsViewTracker(){
		$invoker = empty($_REQUEST['invoker'])?'':trim($_REQUEST['invoker']);
		if(empty($invoker)){
			exit('E001');
		}
		$uid = \Yii::$app->subdb->getCurrentPuid();
		if(empty($uid)){
			exit('E002');
		}
		
		$called_app='Tracker';
		$func_name = 'OmsViewTracker';
		/*
		$affectRows = TrackingAgentHelper::intCallSum($called_app,$invoker,$func_name,$uid);
		if($affectRows>0)
			exit('E003');
		else 
			*/
			exit(true);
	}
	
	/*
	 * ??????OMS????????????????????????????????????tracker??????
	 * @author		lzhl		2016/xx/xx		?????????
	 */
	public function actionIgnoreTrackingNo($order_id,$track_no){
		$uid = \Yii::$app->subdb->getCurrentPuid();
		if (empty($uid)){
			//????????????
			exit('???????????????????????????????????????');
		}
	 
		
		$rtn['success'] = true;
		$rtn['message'] = "";
		
		$transaction = \Yii::$app->get('subdb')->beginTransaction();
		//???????????????lt_tracking
		$rtn = TrackingApiHelper::changeTrackingStatus($track_no, 'ignored');
		if(!$rtn['success'] && $rtn['message']!=='?????????????????????'){//????????????????????????update???????????????return
			$transaction->rollBack();
			exit(json_encode($rtn));
		}
		//???????????????od_order_shipped_v2
		$rtn = OrderApiHelper::setOrderShippedInfo($order_id, $track_no,['sync_to_tracker'=>'Y','tracker_status'=>'ignored']);
		if(!$rtn['success']){
			$transaction->rollBack();
			exit(json_encode($rtn));
		}
		//??????od_order_v2
		$order = OdOrder::findOne($order_id);
		if(empty($order)){
			$transaction->rollBack();
			$rtn['success'] = false;
			$rtn['message'] = "??????????????????";
			exit(json_encode($rtn));
		}
		$order->logistic_status = 'ignored';
		$order->logistic_last_event_time = date("Y-m-d H:i:s");
		if($order->weird_status=='tuol'){
			$order->weird_status = '';
			$order->update_time = time();
		}
		if(!$order->save()){
			$transaction->rollBack();
			$rtn['success']= false;
			$rtn['message'].= "??????".$order->order_source_order_id."????????????:".print_r($order->getErrors());
			exit(json_encode($rtn));
		}
		
		if($rtn['success']){
			$transaction->commit();
			OperationLogHelper::log('order', $order_id,'??????????????????','?????????????????????????????????',\Yii::$app->user->identity->getFullName());
			exit(json_encode($rtn));
		}
	}
	
	
	/**
	 * ????????????????????????
	 * @author fanjs
	 * @selleruserid ??????????????????ebay??????
	 * @orderid  ??????????????????ebay??????ID
	 */
	public function actionMtsyncorder(){
		$selleruserid = $_REQUEST['selleruserid'];
		$orderid = $_REQUEST['orderid'];
		$ebay_user = SaasEbayUser::findOne(['selleruserid'=>$selleruserid]);
		
		$api = new getorders();
		$api->eBayAuthToken=$ebay_user->token;
		$api->_before_request_xmlarray['DetailLevel'] = 'ReturnAll';
		$api->_before_request_xmlarray['OrderID'] = [$orderid];
		$result = $api->api();
		
		if ($result['Ack']=='Warning'&&isset($result['Errors']['ErrorCode'])&&$result['Errors']['ErrorCode']=='21917182'){
			return false;
		}
		if (!$api->responseIsFailure()){
			$requestArr=$api->_last_response_xmlarray;
		
//			\Yii::info(print_r($requestArr,1),'requestOrders   _last_response_xmlarray');
		
			if (!isset($requestArr['OrderArray']['Order'])){
				return false;
			}
				
			if(isset($requestArr['OrderArray']['Order']['OrderID'])){
				$OrderArray['Order']=array($requestArr['OrderArray']['Order']);
			}elseif(Helper_xml::isArray($requestArr['OrderArray']['Order'])&&count($requestArr['OrderArray']['Order'])){
				$OrderArray['Order']=$requestArr['OrderArray']['Order'];
			}
			if(count($OrderArray['Order'])){
				$response_orderids=array();
				foreach ($OrderArray['Order'] as $o){
					if(isset($response_orderids[$o['OrderID']])){
						$response_orderids[$o['OrderID']]++;
					}else{
						$response_orderids[$o['OrderID']]=1;
					}
				}
		
				foreach ($OrderArray['Order'] as $o){
					try {
						$api->saveOneOrder($o,$o['OrderID'],$ebay_user,$ebay_user->selleruserid);
					}catch(Exception $ex){
						echo "Error Message :  ". $ex->getMessage()."\n";
					}
					//Yii::log($logstr);
				}
			}
		}else{
			break 1;
		}
	}
	
	/**
	 +----------------------------------------------------------
	 * ????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2014/05/06				?????????
	 +----------------------------------------------------------
	 **/
	public function actionCancelorder(){
		if (\Yii::$app->request->isPost){
			$orderids = $_POST['orders'];
			Helper_Array::removeEmpty($orderids);
			if (count($orderids)>0){
				try {
					$edit_log = array();
					foreach ($orderids as $order_id){
						$tmpRT = OrderHelper::CancelOneOrder($order_id);
						if ($tmpRT['success']==false){
							//????????????
							if (empty($error_message)) $error_message = '';
							
							$error_message .= $order_id.':'.$tmpRT['message'];
						}
						else{
							$edit_log[] = $order_id;
						}
					}
					
					if(!empty($edit_log)){
						//??????????????????
						UserHelper::insertUserOperationLog('order', "????????????, ?????????: ".implode(', ', $edit_log));
					}
					
					if (!empty($error_message)) return $error_message;
					return '???????????????';
				}catch (\Exception $e){
					return $e->getMessage();
				}
			}else{
				return '????????????????????????';
			}
		}//end of post
	}//end of actionCancelorder
	
	/**
	 +----------------------------------------------------------
	 * ????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2015/12/17				?????????
	 +----------------------------------------------------------
	 **/
	public function actionAbandonorder(){
		if (\Yii::$app->request->isPost){
			$orderids = $_POST['orders'];
			Helper_Array::removeEmpty($orderids);
			if (count($orderids)>0){
				try {
					foreach ($orderids as $order_id){
						$tmpRT = OrderHelper::AbandonOrder($order_id);
						
						if ($tmpRT['success']==false){
							//????????????
							if (empty($error_message)) $error_message = '';
								
							$error_message .= $order_id.':'.$tmpRT['message'];
						}
					}
						
					if (!empty($error_message)) return $error_message;
					return '???????????????';
				}catch (\Exception $e){
					return $e->getMessage();
				}
			}else{
				return '????????????????????????';
			}
		}//end of post
	}//end of actionAbandonorder
	
	/**
	 +----------------------------------------------------------
	 * ????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2015/12/17				?????????
	 +----------------------------------------------------------
	 **/
	public function actionSuspenddelivery(){
		if (\Yii::$app->request->isPost){
			$orderids = $_POST['orders'];
			$module = isset($_POST['m'])?$_POST['m']:'order';
			$action = isset($_POST['a'])?$_POST['a']:TranslateHelper::t('OMS????????????');
			Helper_Array::removeEmpty($orderids);
			if (count($orderids)>0){
				try {
					$r = OrderApiHelper::suspendOrders($orderids,$module,$action);
		
					if (!$r['success']) return $r['message'];
					return '???????????????';
				}catch (\Exception $e){
					return $e->getMessage();
				}
			}else{
				return '????????????????????????';
			}
		}//end of post
	}//end of actionSuspenddelivery
	
	/**
	 +----------------------------------------------------------
	 * ????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2015/12/17				?????????
	 +----------------------------------------------------------
	 **/
	public function actionOutofstock(){
		if (\Yii::$app->request->isPost){
			$orderids = $_POST['orders'];
			$module = isset($_POST['m'])?$_POST['m']:'order';
			$action = isset($_POST['a'])?$_POST['a']:TranslateHelper::t('OMS????????????');
			Helper_Array::removeEmpty($orderids);
			if (count($orderids)>0){
				try {
					$r = OrderHelper::setOrderOutOfStock($orderids,$module,$action);
		
					if (!$r['success']) return $r['message'];
					return '???????????????';
				}catch (\Exception $e){
					return $e->getMessage();
				}
			}else{
				return '????????????????????????';
			}
		}//end of post
	}//end of actionOutofstock
	
	/**
	 +----------------------------------------------------------
	 * ???????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2015/12/17				?????????
	 +----------------------------------------------------------
	 **/
	public function actionSkipmerge(){
		if (\Yii::$app->request->isPost){
			$orderids = $_POST['orders'];
			Helper_Array::removeEmpty($orderids);
			if (count($orderids)>0){
				try {
					$error_message = OrderHelper::skipMergeOrder($orderids);
		
					if (!empty($error_message)) return $error_message;
					return '???????????????';
				}catch (\Exception $e){
					return $e->getMessage();
				}
			}else{
				return '????????????????????????';
			}
		}//end of post
	}//end of actionSkipmerge
	
	/**
	 +----------------------------------------------------------
	 * ????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/01/15				?????????
	 +----------------------------------------------------------
	 **/
	public function actionGenerateProduct(){
		/*
		if (\Yii::$app->request->isPost){
			$orderids = \Yii::$app->request->post('orderids');
		}else{
			$orderids = \Yii::$app->request->get('order_id');
		}
		Helper_Array::removeEmpty($orderids);
		if (count($orderids)==0){
			return json_encode(array('result'=>false,'message'=>TranslateHelper::t('??????????????????')));
		}
		$orders=OdOrder::find()->where(['order_id'=>$orderids])->all();
		//??????????????????????????????  
		foreach ($orders as $order){
			$checkProductExist = OrderHelper::_autoCompleteProductInfo($order->order_id,'order','??????sku');
			if ($checkProductExist['success'] == false){
				return json_encode(array('result'=>false,'message'=> $order->order_id.' '.$checkProductExist['message'].' \n'));
			}
			
		}
		return json_encode(array('result'=>true,'message'=>TranslateHelper::t('??????sku??????')));
		*/
		
		if(!empty($_REQUEST['sku']) && !empty($_REQUEST['itemid'])){
			$item = OdOrderItem::findOne($_REQUEST['itemid']);
			
			$rt = OrderHelper::generateProductByOrderItem($item ,$_REQUEST['sku'] );
		}else{
			$rt = ['success'=>false , 'message'=>'??????????????????'];
		}
		exit(json_encode($rt));
	}
	
	
	/**
	 +----------------------------------------------------------
	 * ????????????sku??????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/08/26				?????????
	 +----------------------------------------------------------
	 **/
	public function actionGenerateProductBox(){
		if (!empty($_REQUEST['orderItemId'])){
			return $this->renderPartial('GenerateProductBox.php',['itemid'=>$_REQUEST['orderItemId']]);
		}else{
			return "?????????????????????";
		}
	}//end of function actionGenerateProductBox

	/**
	 +----------------------------------------------------------
	 * ???????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2015/12/22				?????????
	 +----------------------------------------------------------
	 **/
	public function actionShowWarehouseAndShipmentMethodBox(){
		$orderIdList = @$_REQUEST['orderIdList'];
// 		$shipmethodList = CarrierApiHelper::getShippingServices();
/*
		$warehouseList = InventoryApiHelper::getWarehouseIdNameMap(true);
		$shipmethodList = [];
		if(!empty($warehouseList)){
			foreach ($warehouseList as $k=>$name){
				$shippingMethodInfo = CarrierOpenHelper::getShippingMethodNameInfo(array('proprietary_warehouse'=>$k), -1);
				$shippingMethodInfo = $shippingMethodInfo['data'];
				if(!empty($shippingMethodInfo)){
					foreach ($shippingMethodInfo as $id=>$ship){
						$shipmethodList[$id] = $ship['service_name'];
					}
				}
				break;
			}
		}
		
		//????????????????????????????????????
		$allWHList = InventoryApiHelper::getAllWarehouseInfo();
		$locList = [];
		foreach($allWHList as $whRow){
			$locList[$whRow['warehouse_id']] = $whRow['is_oversea'];
		}
		*/
		list($shipmethodList, $warehouseList , $locList) = OrderHelper::getWarehouseAndShipmentMethodData();
		
		
		return $this->renderPartial('set_wahrehouse_and_shipment_method' , ['shipmethodList'=>$shipmethodList , 'warehouseList'=>$warehouseList , 'orderIdList'=>$orderIdList , 'locList'=>$locList ] );
	}//end of actionShowWarehouseAndShipmentMethodBox
	
	/**
	 +----------------------------------------------------------
	 * ???????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2015/12/30				?????????
	 +----------------------------------------------------------
	 **/
	public function actionChangeWarehouseAndShipmentMethod(){
		if (!empty($_REQUEST['orderIdList']) && isset($_REQUEST['warehouse']) && ! empty($_REQUEST['shipmentMethod'])){
			
			AppTrackerApiHelper::actionLog("Oms-erp", "/order/changeshipmethod");
			$serviceid = SysShippingService::findOne($_REQUEST['shipmentMethod']);
			
			if (isset($_REQUEST['isUpload'])){
				$isUpload = $_REQUEST['isUpload'];
			}else{
				$isUpload = '0';
			}
			
			//??????????????????????????????????????????????????????????????? S  20170904hqw
			$tmp_error_orderid = array();
			$tmp_Orders = OdOrder::find()->select('order_id,reorder_type,order_capture')->where(['order_id'=>$_REQUEST['orderIdList']])->asArray()->all();
			
			if(count($tmp_Orders) > 0){
				foreach ($tmp_Orders as $tmp_Order_one){
					if(($tmp_Order_one['order_capture'] == 'Y') && ($tmp_Order_one['reorder_type'] == 'after_shipment')){
						if(in_array($serviceid->carrier_code, array('lb_epacket','lb_ebaytnt','lb_ebayubi'))){
							$tmp_error_orderid[] = $tmp_Order_one['order_id'];
						}
					}
				}
			}
			
			if(count($tmp_error_orderid) > 0){
				exit(json_encode(['success'=>false,'message'=>'?????????????????????????????????????????????????????????????????????'.implode(',', $tmp_error_orderid)]));
			}
			//??????????????????????????????????????????????????????????????? E
			
			if ($isUpload ==='1'){
				$rt = OdOrder::updateAll(['order_status'=>OdOrder::STATUS_WAITSEND, 'carrier_step'=>OdOrder::CARRIER_CANCELED,'tracking_number'=>''  ] ,['order_id'=>$_REQUEST['orderIdList']]);
				OperationLogHelper::batchInsertLog('order', $_REQUEST['orderIdList'], '????????????','?????????????????????'.OdOrder::$status[OdOrder::STATUS_WAITSEND].'???');
				$tmpMsg = "???????????????";
			}else{
				$tmpMsg ='';
			}
			
			if (!empty($serviceid)||!$serviceid->isNewRecord){
				//should update require order qty item
				$errorMsg = '';
				try{
					$changeWarehouseOrder = OdOrder::find()->where(['order_id'=>$_REQUEST['orderIdList']])->andWhere(['<>','default_warehouse_id',$_REQUEST['warehouse']])->all();
					$updateQtyItemList = [];
					foreach($changeWarehouseOrder as $tmpOrder){
						$updateQtyItemList [$tmpOrder->default_warehouse_id] = [];
						foreach($tmpOrder->items as $item){
							if (!empty($item->root_sku)){
								$rootSKU = $item->root_sku;
								if (isset($updateQtyItemList [$tmpOrder->default_warehouse_id] [$rootSKU])){
									$updateQtyItemList [$tmpOrder->default_warehouse_id] [$rootSKU] += $item['quantity'];
								}else{
									$updateQtyItemList [$tmpOrder->default_warehouse_id] [$rootSKU] = $item['quantity'];
								}
							}
							
							//$sku = empty($item['sku'])?$item['product_name']:$item['sku'];
							/*20170321start
							 
							list($ack , $message , $code , $rootSKU ) = array_values(OrderGetDataHelper::getRootSKUByOrderItem($item));
							if (empty($ack)) $errorMsg .= " ".$message ;
							
							if (isset($updateQtyItemList [$tmpOrder->default_warehouse_id] [$rootSKU])){
								$updateQtyItemList [$tmpOrder->default_warehouse_id] [$rootSKU] += $item['quantity'];
							}else{
								$updateQtyItemList [$tmpOrder->default_warehouse_id] [$rootSKU] = $item['quantity'];
							}
							20170321end*/
							
							
						}
					}
						
					if (!empty($updateQtyItemList)){
						foreach($updateQtyItemList as $OriginWHID=>$tmpItemList){
							foreach($tmpItemList as $sku=>$qty){
								list($ack , $code , $message  )  = array_values(OrderBackgroundHelper::updateUnshippedQtyOMS($sku, $OriginWHID, $_REQUEST['warehouse'], $qty, $qty));
								if (empty($ack)) $errorMsg .= " ".$message ;
							}
						}
					}
						
				}catch(\Exception $e){
					$errorMsg .= " ????????????";
					\Yii::error(__FUNCTION__." Error :".$e->getMessage()." line no ".$e->getLine(),'file');
				}

				$TrackArr=empty($_REQUEST['trackArr'])?'':$_REQUEST['trackArr'];
				if(!empty($TrackArr) && !empty($TrackArr[0])){
					$TrackingNoManual_paramsArr=array(
							'tracking_number'=>'',
							'tracking_link'=>'',
							'shipping_method_code'=>'',
							'shipping_method_name'=>'',
							'description'=>'',
					);
				
					foreach ($TrackArr as $trKeys=>$TrackArrone){
						if($trKeys===0)
							$TrackingNoManual_paramsArr['tracking_number']=$TrackArrone;
						else if($trKeys===1)
							$TrackingNoManual_paramsArr['shipping_method_name']=$TrackArrone;
						else if($trKeys===2)
							$TrackingNoManual_paramsArr['tracking_link']=$TrackArrone;
						else if($trKeys===3)
							$TrackingNoManual_paramsArr['shipping_method_code']=$TrackArrone;
						else if($trKeys===4){
							if($TrackArrone=='Manual'){
								$TrackingNoManual_paramsArr['default_shipping_method_code'] = 'manual_tracking_no';
								$_REQUEST['shipmentMethod']='manual_tracking_no';
							}else{
								$TrackingNoManual_paramsArr['default_shipping_method_code'] = $_REQUEST['shipmentMethod'];
							}
						}
					}
				
					$TrackingNoManual_Orderid=empty($_REQUEST['orderIdList'][0])?'':$_REQUEST['orderIdList'][0]; //??????????????????????????????????????????
					$TrackingNoManual_rt=\eagle\modules\carrier\helpers\CarrierOpenHelper::saveTrackingNoManual($TrackingNoManual_Orderid,$TrackingNoManual_paramsArr);
				}

				//$OldOrderList = OdOrder::find()->select(['order_id','default_shipping_method_code','default_carrier_code','default_warehouse_id' ])->where(['order_id'=>$_REQUEST['orderIDList']])->asArray()->all();
				$rt = OdOrder::updateAll(['default_shipping_method_code'=>$_REQUEST['shipmentMethod'], 'default_carrier_code'=>$serviceid->carrier_code   , 'default_warehouse_id'=>$_REQUEST['warehouse'] ] ,['order_id'=>$_REQUEST['orderIdList']]);
				
				if (!empty($rt)){
					
					
					foreach($_REQUEST['orderIdList'] as $orderid){
						OperationLogHelper::log('order',$orderid,'???????????????????????????','???????????????:'.@$_REQUEST['warehouseName'].' ,?????????????????? ???:'.@$_REQUEST['shipmentMethodName'].$tmpMsg,\Yii::$app->user->identity->getFullName());
					}
					
					//???????????????????????????????????????????????????????????????
					$rt_carrier_error = OdOrder::updateAll(['carrier_error'=>''] ,['order_id'=>$_REQUEST['orderIdList']]);
					
					if (empty($errorMsg)){
						exit(json_encode(['success'=>true,'message'=>'']));
					}else{
						exit(json_encode(['success'=>false,'message'=>$errorMsg]));
					}
					
				}else
					exit(json_encode(['success'=>true,'message'=>'?????????????????????????????????']));
			}
			exit(json_encode(['success'=>false,'message'=>'?????????????????????????????????']));
			
		}else{
			exit(json_encode(['success'=>false , 'message'=>'E1 ?????????????????????????????????????????????']));
		}
	}//end of actionChangeWarehouseAndShipmentMethod
	
	/**
	 +----------------------------------------------------------
	 * ????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2015/12/23				?????????
	 +----------------------------------------------------------
	 **/
	public function actionReorder(){
		if (!empty($_REQUEST['orderIdList'])){
				
			if (is_array($_REQUEST['orderIdList'])){
				$rt = OrderHelper::reorder($_REQUEST['orderIdList']);
				exit(json_encode(['success'=>($rt['success'] == count($_REQUEST['orderIdList'])),'message'=>$rt['message']]));
				
			}else{
				
				exit(json_encode(['success'=>false,'message'=>'E001 ??????????????? ??????????????????']));
			}
		}else{
			exit(json_encode(['success'=>false,'message'=>'?????????????????????']));
		}
	}//end of actionReorder
	
	/**
	 +----------------------------------------------------------
	 * ????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2015/12/23				?????????
	 +----------------------------------------------------------
	 **/
	public function actionCopyOrder(){
		if (!empty($_REQUEST['orderIDList'])){
	
			if (is_array($_REQUEST['orderIDList'])){
				$rt = OrderHelper::copyOrder($_REQUEST['orderIDList']);
				exit(json_encode(['success'=>($rt['success'] == count($_REQUEST['orderIDList'])),'message'=>$rt['message']]));
	
			}else{
	
				exit(json_encode(['success'=>false,'message'=>'E001 ??????????????? ??????????????????']));
			}
		}else{
			exit(json_encode(['success'=>false,'message'=>'?????????????????????']));
		}
	}//end of actionCopyOrder
	
	/**
	 +----------------------------------------------------------
	 * ??????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2015/12/24				?????????
	 +----------------------------------------------------------
	 **/
	public  function actionShowAddMemoBox(){
		if (!empty($_REQUEST['orderIdList'])){
			
			if (is_array($_REQUEST['orderIdList'])){
				$orderList  = OdOrder::find()->where(['order_id'=>$_REQUEST['orderIdList']])->asArray()->all();
				return $this->renderPartial('_addMemoBox.php' , ['orderList'=>$orderList] );
			}else{
				return $this->renderPartial('//errorview','E001 ??????????????? ??????????????????');
			}
		}else{
			return $this->renderPartial('//errorview','?????????????????????');
		}
	}//end of actionShowAddMemoBox
	
	/**
	 +----------------------------------------------------------
	 * ????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2015/12/25				?????????
	 +----------------------------------------------------------
	 **/
	public function actionBatchSaveOrderDesc(){
		if (!empty($_REQUEST['orderList'])){
			$orderIdList = [];
			$MemoList = [];
			$err_msg = "";
			foreach ($_REQUEST['orderList'] as $row){
				$orderIdList[] = $row['order_id'];
				$MemoList[(int)$row['order_id']]  = $row['memo']; // linux ??????00????????????
			}
			
			$OrderList = OdOrder::find()->where(['order_id'=>$orderIdList])->all();
			foreach($OrderList as $OrderModel){
				if (isset($MemoList[(int)$OrderModel->order_id])){
					$rt = OrderHelper::addOrderDescByModel($OrderModel, $MemoList[(int)$OrderModel->order_id], 'order', '????????????');
					
					$OrderModel->desc = $MemoList[(int)$OrderModel->order_id];
					if ($rt['success'] == true){
						//OperationLogHelper::log('order',$OrderModel->order_id,'????????????','????????????: ('.$olddesc.'->'.$OrderModel->desc .')',\Yii::$app->user->identity->getFullName());
					}else{
						$err_msg .= $OrderModel->order_id." ?????????????????????";
					}
				}else{
					$err_msg .= $OrderModel->order_id.'????????????????????????<br>';
				}
				
			}
			if (!empty($OrderList)){
				$result = ['success'=>empty($err_msg) , 'message'=>$err_msg];
			}else{
				$result = ['success'=>false , 'message'=>'E002??????????????? ????????????'];
			}
			
		}else{
			$result = ['success'=>false , 'message'=>'E001??????????????? ??????????????????'];
		}
		exit(json_encode($result));
	}//end of actionBatchSaveOrderDesc


	/**
	 * ????????????????????????
	 * @return string
	 * akirametero
	 */
	public  function actionShowAddPointOrigin(){
		if (!empty($_REQUEST['orderIdList'])){
			if (is_array($_REQUEST['orderIdList'])){
				$countryList = StandardConst::$COUNTRIES_CODE_NAME_EN;
				$orderList  = OdOrder::find()->where(['order_id'=>$_REQUEST['orderIdList']])->asArray()->all();
				return $this->renderPartial('_addPointOrigin.php' , ['orderList'=>$orderList,'countryList'=>$countryList] );
			}else{
				return $this->renderPartial('//errorview','E001 ??????????????? ??????????????????');
			}
		}else{
			return $this->renderPartial('//errorview','?????????????????????');
		}
	}//end of actionShowAddPointOrigin

	/**
	 * ????????????????????????
	 * akirametero
	 */
	public function actionBatchSaveOrderPointOrigin(){
		if (!empty($_REQUEST['orderList'])){

			$orderIdList = [];
			$OriginList = [];
			$err_msg = "";
			foreach ($_REQUEST['orderList'] as $row){
				$orderIdList[] = $row['order_id'];
				$OriginList[(int)$row['order_id']]  = $row['select_country']; // linux ??????00????????????
			}


			$OrderList = OdOrder::find()->where(['order_id'=>$orderIdList])->all();
			foreach($OrderList as $OrderModel){
				if (isset($OriginList[(int)$OrderModel->order_id])){
					$origin= $OriginList[(int)$OrderModel->order_id];

					if (!empty( $OriginList[(int)$OrderModel->addi_info] )){
						$addInfo = json_decode($OriginList[(int)$OrderModel->addi_info],true);
					}else{
						$addInfo = [];
					}
					$addInfo['order_point_origin']= $origin;
					$update= OdOrder::findOne( (int)$OrderModel->order_id );
					$update->addi_info= json_encode( $addInfo );
					$update->update(false);
				}else{
					$err_msg .= $OrderModel->order_id.'????????????????????????<br>';
				}

			}
			if (!empty($OrderList)){
				$result = ['success'=>empty($err_msg) , 'message'=>$err_msg];
			}else{
				$result = ['success'=>false , 'message'=>'E002??????????????? ????????????'];
			}

		}else{
			$result = ['success'=>false , 'message'=>'E001??????????????? ??????????????????'];
		}
		exit(json_encode($result));
	}//end of actionBatchSaveOrderDesc


	
	/**
	 +----------------------------------------------------------
	 * ????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2015/12/30				?????????
	 +----------------------------------------------------------
	 **/
	public function actionShowStockManageBox(){
		if (!empty($_REQUEST['orderIdList'])){
			$orderIdList = $_REQUEST['orderIdList'];
			$OrderList = OdOrder::find()->select(['order_id', 'default_warehouse_id'])->where(['order_id'=>$orderIdList])->asArray()->all();
			
			$OrderGroup = [];
			//?????????????????????
			foreach($OrderList as $oneOrder){
				$OrderGroup [$oneOrder['default_warehouse_id']] [] = $oneOrder['order_id'];
				//??????????????????????????????
				OrderApiHelper::saveOrderGoods($oneOrder['order_id']);
			}
			
			$OrderItemList = OdOrderItem::find()->where(['order_id'=>$orderIdList])->asArray()->all();
			//????????????order item ??????
			$ItemListMapping = [];
			$ItemInfoGroup = []; //??????????????????
			foreach($OrderItemList as $anItem){
				$ItemListMapping[$anItem['order_id']][] = $anItem;
				if (empty($ItemInfoGroup[$anItem['sku']]))
					$ItemInfoGroup[$anItem['sku']] = $anItem;
			}
			
			//???????????????????????????
			$ItemGroup = [];//??????????????????????????????
			
			$ProductStock = []; //????????????
			
			foreach($OrderGroup as $warehouse_id => $aGroup){
				//?????????????????????
				$ItemGroup[$warehouse_id] = [];
				foreach($aGroup as $order_id){
					$skus = OdOrderGoods::findAll(['order_id'=>$order_id]);
					foreach($skus as $tmpItem){
						if (isset($ItemGroup[$warehouse_id][$tmpItem['sku']])){
							$ItemGroup[$warehouse_id][$tmpItem['sku']] += $tmpItem['quantity'];
						}else{
							$ItemGroup[$warehouse_id][$tmpItem['sku']] = $tmpItem['quantity'];
						}
					}
				}
				
				//????????????
				foreach ($ItemGroup[$warehouse_id] as $sku=>$qty){
					//??????????????????
					$ProductStock[$warehouse_id][$sku] = InventoryHelper::getProductInventory($sku,$warehouse_id);
				}
			}
			
			
			
		}
		$warehouseids = InventoryApiHelper::getWarehouseIdNameMap();
		
		
		
		return $this->renderPartial('order_item_import_stock.php' , ['ItemGroup'=>$ItemGroup ,  'warehouseids'=>$warehouseids , 'ItemInfoGroup'=>$ItemInfoGroup , 'ProductStock'=>$ProductStock ] );
	}//end of actionShowInStockBox
	
	/**
	 +----------------------------------------------------------
	 * oms???????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2015/12/30				?????????
	 +----------------------------------------------------------
	 **/
	public function actionCreateStockIn(){
		
		if (!empty($_REQUEST['stockInList'])){
			$rtn = ['success'=>true, 'message'=>''];
			$ischeck = false;
			foreach($_REQUEST['stockInList'] as $warehouseID=>$items){
				$info = [
				'stockchangetype'=>1, //"??????"
				'stockchangereason'=>101,//"????????????"
				'stock_change_id'=>'OMS'.$warehouseID."T".time(),
				'prods'=>$items,
				'comment'=>'',
				'warehouse_id'=>$warehouseID,
					
				];
				$result = InventoryApiHelper::createNewStockIn($info);
				
				if ($result['success']){
					//???????????????
					$ischeck = true; //????????????order ?????????
					
				}else{
					//??????????????????????????????
					$rtn['success'] = false;
					$rtn['message'] .= $result['message'];
				}
			}
			
			if ($ischeck && ! (empty($_REQUEST['orderIdList']))){
				//??????order ?????????
				$OrderList = OdOrder::findAll($_REQUEST['orderIdList']);
				foreach($OrderList as $order){
					$order->checkorderstatus('System');
				}
			}
			exit(json_encode($rtn));
			
		}else{
			exit(json_encode(['success'=>false, 'message'=>'E001 ??????????????????????????????']));
		}
		
		
	}//end of actionCreateStockIn
	
	/**
	 +----------------------------------------------------------
	 * ????????????id????????????????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		zwd 	2016/3/10				?????????
	 +----------------------------------------------------------
	 **/
	public function actionGetShippingMethodByWarehouseid(){
		/*
		 $shippingMethodInfo = CarrierOpenHelper::getShippingMethodNameInfo(array('proprietary_warehouse'=>$_POST['warehouse_id']), -1);
		 $shippingMethodInfo = $shippingMethodInfo['data'];
		
		if(!empty($shippingMethodInfo)){
			$shipp_arr = [];
			foreach ($shippingMethodInfo as $id=>$ship){
				$shipp_arr[$id] = $ship['service_name'];
			}
			return json_encode($shipp_arr);
		}
		return '';
		 */
		
		if (isset($_POST['warehouse_id']) && trim($_POST['warehouse_id'] != '')){
			$result = CarrierOpenHelper::getShippingServiceIdNameMapByWarehouseId($_POST['warehouse_id']);
			exit(json_encode($result));
		}else{
			exit(json_encode([]));
			
		}
	}
	/**
	 +----------------------------------------------------------
	 * ????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		million 	2016/3/18				?????????
	 +----------------------------------------------------------
	 **/
	public function actionSigncomplete(){
		if (\Yii::$app->request->isPost){
			$orderids = \Yii::$app->request->post('orderids');
		}else{
			$orderids = \Yii::$app->request->get('order_id');
		}
		Helper_Array::removeEmpty($orderids);
		if (count($orderids)==0){
			return json_encode(array('result'=>false,'message'=>TranslateHelper::t('??????????????????')));
		}
		
		OrderApiHelper::completeOrder($orderids);
		return json_encode(array('result'=>true,'message'=>TranslateHelper::t('????????????!???????????????????')));
	}
	
	/**
	 +----------------------------------------------------------
	 * oms ?????????????????? ??????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2015/12/30				?????????
	 +----------------------------------------------------------
	 **/
	 public function actionImportTracknoBox(){
		 return $this->renderPartial('importTracknoBox');
	 }

	/**
	 +----------------------------------------------------------
	 * ????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/05/27				?????????
	 +----------------------------------------------------------
	 **/
	public function actionShowChangeItemDeclarationInfoBox(){
		if (!empty($_REQUEST['orderIdList'])){
			$NotSKUList = CdiscountOrderInterface::getNonDeliverySku();
			$ItemList = OdOrderItem::find()->select(['photo_primary','sku','product_name','order_id','order_item_id'])->distinct(true)->where(['order_id'=>$_REQUEST['orderIdList']])->andwhere(['not in',"ifnull(sku,'')",$NotSKUList])->asArray()->all();
			
			//??????????????????
			$declaration_list=array();
			$List = OdOrder::find()->where(['order_id'=>$_REQUEST['orderIdList']])->all();
			foreach ($List as $Listone){
				$declaration_list[intval($Listone->order_id)]=CarrierDeclaredHelper::getOrderDeclaredInfoByOrder($Listone);
			}
			//echo OdOrderItem::find()->select(['photo_primary','sku' , 'product_name'])->distinct(true)->where(['order_id'=>$_REQUEST['orderIdList']])->andwhere(['not in',"ifnull(sku,'')",$NotSKUList])->createCommand()->getRawSql();
			
			//OrderHelper::_autoCompleteProductInfo($_REQUEST['orderIdList'],'order','????????????');
			// $extendData ?????????
			$productData = [];
							
			//??????????????????????????????
			foreach($ItemList as &$oditem){
				//????????????????????????????????? ????????????????????????????????? ???????????? ??????
// 				if (!empty($oditem['sku']))
// 					$key = $oditem['sku'];
// 				else 
// 					$key = $oditem['product_name']; //sku ????????? ??????product name
// 				$productData[$oditem['order_item_id']] = ProductApiHelper::getProductInfo($key);
				
				$productData[$oditem['order_item_id']]['order_id'] = $oditem['order_id'];
				$productData[$oditem['order_item_id']]['order_item_id'] = $oditem['order_item_id'];
				//????????????????????????????????????
				$order_id_int=intval($oditem['order_id']);
				if(isset($declaration_list[$order_id_int]) && $declaration_list[$order_id_int][$oditem['order_item_id']]['not_declaration']==0){
					$declaration = $declaration_list[$order_id_int][$oditem['order_item_id']]['declaration'];
					foreach($declaration as $declaration_keys=>$declarationone)
						$productData[$oditem['order_item_id']][$declaration_keys]=$declarationone;
				}
			}//end of each orderItem

			return $this->renderPartial('showchangeitemdeclarationinfobox',['items'=>$ItemList  , 'productData'=>$productData ,'OrderIdList'=>$_REQUEST['orderIdList']]);
		}else{
			return '????????????????????????';
		}
	
	}
	
	/**
	 +----------------------------------------------------------
	 * ?????? ????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/05/27				?????????
	 +----------------------------------------------------------
	 **/
	public function actionBatchSaveDeclarationInfo(){
		$NameCNList = array_combine($_REQUEST['order_itemid'], $_REQUEST['nameCN']);
		$NameENList = array_combine($_REQUEST['order_itemid'], $_REQUEST['nameEN']);
		$WeightList = array_combine($_REQUEST['order_itemid'], $_REQUEST['weight']);
		$PriceList = array_combine($_REQUEST['order_itemid'], $_REQUEST['price']);
		$Order_itemid = array_combine($_REQUEST['order_itemid'], $_REQUEST['order_itemid']);
		//$ProdNameCNList = array_combine($_REQUEST['order_itemid'], $_REQUEST['ProdNameCN']);
		$ischangeList = array_combine($_REQUEST['order_itemid'], $_REQUEST['json_itemid']);
		$skuList=array_combine($_REQUEST['order_itemid'], $_REQUEST['sku']);
		$codeList=array_combine($_REQUEST['order_itemid'], $_REQUEST['code']);
		
		$influencescopeList_this=array();
		$influencescopeList_all=array();
		foreach ($_REQUEST['influencescope'] as $keys=>$influencescopeone){
			if(!$influencescopeone)
				$influencescopeList_this[$_REQUEST['order_itemid'][$keys]]=$influencescopeone;
			else{
				$influencescopeList_all[$_REQUEST['order_itemid'][$keys]]=$influencescopeone;
			}
		}
		$success = true;
		$msg="";
		$status=0;

		//???????????????????????????
		$identical_sku=array();
		foreach ($influencescopeList_all as $keys=>$influencescopeList_allone){	
			//??????sku?????????????????????		
			if(isset($identical_sku[$skuList[$keys]])){
				$NameCN_temp=$identical_sku[$skuList[$keys]]['NameCN'];
				$NameEN_temp=$identical_sku[$skuList[$keys]]['NameEN'];
				$Price_temp=$identical_sku[$skuList[$keys]]['Price'];
				$Weight_temp=$identical_sku[$skuList[$keys]]['Weight'];
				$code_temp=$identical_sku[$skuList[$keys]]['code'];
			}
			else{
				$NameCN_temp=$NameCNList[$keys];
				$NameEN_temp=$NameENList[$keys];
				$Price_temp=$PriceList[$keys];
				$Weight_temp=$WeightList[$keys];
				$code_temp=$codeList[$keys];
				
				$identical_sku[$skuList[$keys]]=array(
						'NameCN'=>$NameCNList[$keys],
						'NameEN'=>$NameENList[$keys],
						'Price'=>$PriceList[$keys],
						'Weight'=>$WeightList[$keys],
						'code'=>$codeList[$keys],
				);
			}
						
			$item = OdOrderItem::find()->where(['order_item_id'=>$keys])->one();
			$order_source = OdOrder::find()->select(['order_source'])->where(['order_id'=>$item->order_id])->one();
			if(strpos($Weight_temp,'.') || (float)$Weight_temp<0){
				$success=false;
				$msg.='<span style="color:red;">'.$item->order_id.':'.$item->product_name.'????????????????????????????????????????????????????????????????????????;</span><br/>';
			}
			else{
				$result=OrderUpdateHelper::setOrderItemDeclaration($keys,$NameCN_temp,$NameEN_temp,$Price_temp,$Weight_temp,$code_temp,'Y');
				if(isset($result['ack']) && $result['ack']==false){
					$success=false;
					$msg.='<span style="color:red;">'.$item->order_id.':'.$item->product_name.'????????????????????????????????????'.$result['message'].";</span><br/>";
				}
				
				$items=OrderGetDataHelper::getPayOrderItemBySKU($skuList[$keys]);
				foreach ($items as $itemsone){
					$result=OrderUpdateHelper::setOrderItemDeclaration($itemsone->order_item_id,$NameCN_temp,$NameEN_temp,$Price_temp,$Weight_temp,$code_temp,'Y');
					if(isset($result['ack']) && $result['ack']==false){
						$msg.=$itemsone->order_id.':'.$result['message']." err1;";
						$success=false;
					}
				}
					
				if($success==true){
					$tmp_platform_itme_id = \eagle\modules\order\helpers\OrderGetDataHelper::getOrderItemSouceItemID($order_source->order_source, $item);
					$declared_params[]=array(
							'platform_type'=>$order_source->order_source,
							'itemID'=>$tmp_platform_itme_id,
							'sku'=>$skuList[$keys],
							'ch_name'=>$NameCN_temp,
							'en_name'=>$NameEN_temp,
							'declared_value'=>$Price_temp,
							'declared_weight'=>$Weight_temp,
							'detail_hs_code'=>$code_temp,
					);
					$result=CarrierDeclaredHelper::setOrderSkuDeclaredInfoBatch($declared_params);
					if($result==false){
						$msg.=$itemsone->order_id.':'."????????????err2;";
						$success=false;
					}
				
					if(!empty($item->root_sku)){
						$info=array(
								'declaration_ch'=>$NameCN_temp,
								'declaration_en'=>$NameEN_temp,
								'declaration_value'=>$Price_temp,
								'prod_weight'=>$Weight_temp,
								'declaration_code'=>$code_temp,
						);
						$rt = \eagle\modules\catalog\helpers\ProductApiHelper::modifyProductInfo($item->root_sku,$info);
						if($rt['success']==false){
							$msg.='??????????????????????????????:'.$rt['message']." err3;";
							$success=false;
						}
					}
				}	
			}
		}
		unset($identical_sku);
		
		//?????????????????????
		foreach ($influencescopeList_this as $keys=>$influencescopeList_allone){
			$item = OdOrderItem::find()->where(['order_item_id'=>$keys])->one();
			if(strpos($WeightList[$keys],'.') || (float)$WeightList[$keys]<0){
				$success=false;
				$msg.='<span style="color:red;">'.$item->order_id.':'.$item->product_name.'????????????????????????????????????????????????????????????????????????;</span><br/>';
			}
			else{
				$result=OrderUpdateHelper::setOrderItemDeclaration($keys,$NameCNList[$keys],$NameENList[$keys],$PriceList[$keys],$WeightList[$keys],$codeList[$keys],'Y');
				if(isset($result['ack']) && $result['ack']==false){
					$success=false;
					$msg.='<span style="color:red;">'.$item->order_id.':'.$item->product_name.'????????????????????????????????????'.$result['message'].";</span><br/>";
				}
			}
		}
		
		return json_encode(['success'=>$success, 'message'=>$msg]);
	}
	
	/**
	 +----------------------------------------------------------
	 * ?????? ????????????????????? ??????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lrq 	2016/07/27				?????????
	 +----------------------------------------------------------
	 **/
	public function actionShowScanningTrackingNumberBox()
	{
	
		if (!empty($_REQUEST['orderIdList']))
		{
			$conn=\Yii::$app->subdb;
			$queryTmp = new Query;
			
			$queryTmp->select("a.order_id,b.shipping_method_name,b.is_tracking_number")
			->from("od_order_v2 a")
			->leftJoin("sys_shipping_service b", "a.default_shipping_method_code = b.id")
			->where(['a.order_id'=>$_REQUEST['orderIdList']]);
			
			$OrderInfo = $queryTmp->createCommand($conn)->queryAll();
			
			//??????????????????????????????????????????
			$orderlist = array();
			$is_tracking_numberlist = array();
			foreach ( $_REQUEST['orderIdList'] as $orderid)
			{
				foreach ( $OrderInfo as $order)
				{
					if($orderid == $order['order_id'])
					{
						$orderlist[$orderid] = $order['shipping_method_name'];
						$is_tracking_numberlist[$orderid] = $order['is_tracking_number'];
					}
				}
			}
			
			return $this->renderPartial('showscanningtrackingnumberbox',['OrderList'=>$orderlist, 'is_tracking_numberlist'=>$is_tracking_numberlist]);
		}else{
			return '????????????????????????';
		}
	}
	
	/**
	 +----------------------------------------------------------
	 * ????????????????????????????????????SKU ??????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lrq 	2016/08/11				?????????
	 +----------------------------------------------------------
	 **/
	public function actionGetOrderListByCondition()
	{
	    try 
	    {
	        $scaning_val = '';
	        $start_time = date('Y-m-d H:i:s');
	        
	        if( $_REQUEST['type'] == 1)
	        {
	            $scaning_val = $_REQUEST['val'];
    	        //?????????????????????
        	    $order['keys'] = 'order_id';
        	    $order['searchval'] = $_REQUEST['val'];
        	    $orders = OrderApiHelper::getOrderListByCondition($order);
        	    $datas = $orders['data'];
        	    
        	    //???????????????
        	    $order['keys'] = 'tracknum';
        	    $order['searchval'] = $_REQUEST['val'];
        	    $orders = OrderApiHelper::getOrderListByCondition($order);
        	    $datas += $orders['data'];
	        }
	        else if( $_REQUEST['type'] == 2)
	        {
	        	//?????????
	        	$order['order_status'] = 300;
	        	//???????????????
	        	$order['carrierPrintType'] = 'no_print_carrier';
	        	
	            //root_sku??????
	            $order['keys'] = 'root_sku';
	            $order['searchval'] = $_REQUEST['val'];
	            $orders = OrderApiHelper::getOrderListByCondition($order);
	            if(empty($orders)){
	            	//sku??????
	            	$order['keys'] = 'sku';
	            	$order['searchval'] = $_REQUEST['val'];
	            	$orders = OrderApiHelper::getOrderListByCondition($order);
	            }
	            foreach ($orders['data'] as $index => $o){
	            	$datas[$o->order_id] = $o;
	            }
	            
	            //???????????????????????????
	            $bundlelist_root = ProductBundleRelationship::find()->select(['bdsku'])->where(["assku"=>$_REQUEST['val']])->asArray()->all();
	            if(!empty($bundlelist_root)){
	                $bdsku = $bundlelist_root[0]['bdsku'];
	                
	                $order['searchval'] = $bdsku;
	                $orders = OrderApiHelper::getOrderListByCondition($order);
	                foreach ($orders['data'] as $index => $o){
	                	$datas[$o->order_id] = $o;
	                }
	            }
	            
	            //??????sku????????????
	            ksort($datas);
	        }
	        
    	    if(!empty($datas))
    	    {
        	    foreach ($datas as $order_key => $order)
        	    {
        	        $data[0] =
        	        [
            	        'order_id' => preg_replace('/^0+/','',$order['order_id']),         //??????????????????
            	        'order_source_order_id' => $order['order_source_order_id'],   //???????????????
            	        'customer_number' => $order['customer_number'],     //???????????????
            	        'track_number' => CarrierOpenHelper::getOrderShippedTrackingNumber($order['order_id'],$order['customer_number'],$order['default_shipping_method_code']),   //?????????
            	        'desc' => empty($order['desc']) ? '' : $order['desc'],       //????????????
            	        'order_source' => $order['order_source'],           //????????????
            	        'logistics_weight' => empty($order['logistics_weight']) ? '' : $order['logistics_weight'],    //??????????????????
            	        'seller_weight' => empty($order['seller_weight']) ? '' : $order['seller_weight'],    //??????????????????
            	        'delivery_status' => empty( OdOrder::$status[$order['order_status']]) ? '' : OdOrder::$status[$order['order_status']],    //????????????
            	        'carrier_step' => $order['carrier_step'],    //????????????
            	        'default_carrier_code' => $order['default_carrier_code'],    //???????????????
            	        'carrier_type' => '1',    //??????????????????1api?????????2Excel?????????3???????????????
        	        ];
        	        
        	        //?????????????????????????????????----------start
        	        $carrier_type = 'api';
        	        if(isset($data[0]['default_carrier_code']) && strpos($data[0]['default_carrier_code'], 'lb_') === false)
        	        {
        	            //??????Excel????????????????????????
        	            $custom = SysCarrierCustom::find()->where(['carrier_code'=>$data[0]['default_carrier_code']])->one();
        	            if(!empty($custom))
        	            {
        	                if($custom['carrier_type'] == 1)
        	                {
        	                    $carrier_type = 'excel';
        	                    $data[0]['carrier_type'] = '2';
        	                }
        	                else 
        	                {
        	                    $carrier_type = 'trackno';
        	                    $data[0]['carrier_type'] = '3';
        	                }
        	            }
        	        }
        	        
        	        $carrierprocess_carrier_step = OdOrder::$carrierprocess_carrier_step[$carrier_type];
        	        if(!empty($carrierprocess_carrier_step))
        	        {
        	            foreach ($carrierprocess_carrier_step as $s_key => $s_val)
        	            {
        	            	if(is_array($s_val['value']))
        	            	{
        	            		if(in_array($data[0]['carrier_step'], $s_val['value']))
        	            		{
        	            			$data[0]['carrier_step'] = $s_val['name'];
        	            			break;
        	            		}
        	            	}
        	            	else if($s_val['value'] == $data[0]['carrier_step'])
        	            	{
        	            		$data[0]['carrier_step'] = $s_val['name'];
        	            		break;
        	            	}
        	            }
        	        }
        	        //?????????????????????????????????----------end
        	       
        	        //??????SKU??????????????????
        	        if( $_REQUEST['type'] == 2)
        	        {
        	        	//?????????????????????
        	        	$skip_val = explode(',', $_REQUEST['skip_val']);
        	        	foreach ($skip_val as $val)
        	        	{
        	        		if( $val == $order['order_id'])
        	        			continue 2;
        	        	}
        	        	 
        	        	//?????????????????????
        	        	if($order['is_print_carrier'] == 1)
        	        		continue;
        	        	
        	        	//?????????????????????
        	        	if(trim($data[0]['delivery_status']) != '?????????')
        	        	    continue;
        	        	
        	        	//?????????????????????????????????????????????
        	        	$carrier_step = $data[0]['carrier_step'];
        	        	if(!($carrier_step == '?????????' || $carrier_step == '?????????' || $carrier_step == '?????????'))
        	        	    continue;
        	        }
        	        
        	        foreach ($order->items as $item_key => $item)
        	        {
        	            $status = 0;
        	            $rootSku = empty($item['root_sku']) ? $item['sku'] : $item['root_sku'];
        	            
        	            //??????SKU?????????????????????????????????
        	            if( $_REQUEST['type'] == 2)
        	            {
            	            $sku = empty($item['root_sku']) ? $item['sku'] : $item['root_sku'];
            	            //??????????????????SKU
            	            $rootSku = ProductHelper::getRootSkuByAlias($sku);
            	            if(empty($rootSku))
            	                $rootSku = $sku;
            	            //???????????????????????????????????????????????????
            	            $prod = Product::findOne(['sku'=>$rootSku]);
            	            if(!empty($prod) && $prod->type == "B")
            	            {
            	                $qty = $item['quantity'];
            	                //???????????????
            	                $bundlelist = ProductBundleRelationship::find()->select(['assku','qty'])->where(["bdsku"=>$rootSku])->asArray()->all();
            	                if(!empty($bundlelist)){
            	                	foreach ($bundlelist as $bundle){
            	                	    //???????????????
            	                	    $prod_assku = Product::findOne(['sku'=>$bundle['assku']]);
            	                	    if(!empty($prod_assku)){
            	                	        $product_name = $prod_assku->name;
            	                	        $photo_primary = $prod_assku->photo_primary;
            	                	    }
            	                	    
            	                	    $data[0]['items'][] =
            	                	    [
                	                	    'sku' => $bundle['assku'],
                	                	    'quantity' => $bundle['qty'] * $qty,         //??????
                	                	    'product_url' => empty($item['product_url']) ? '' : $item['product_url'],   //????????????
                	                	    'product_name' => empty($product_name) ? $item['product_name'] : $product_name, //????????????
                	                	    'photo_primary' => empty($photo_primary) ? $item['photo_primary'] : $photo_primary, //????????????
            	                	    ];
            	                	}
            	                	$status = 1;
            	                }
            	            }
        	            }
        	            
        	            if($status == 0){
        	                $data[0]['items'][] =
        	                [
            	                'sku' => $rootSku,
            	                'quantity' => $item['quantity'],         //??????
            	                'product_url' => empty($item['product_url']) ? '' : $item['product_url'],   //????????????
            	                'product_name' => $item['product_name'], //????????????
            	                'photo_primary' => $item['photo_primary'], //????????????
        	                ];
        	            }
        	        }
        	        
        	        //??????p4760???????????????
        	        $puid = \Yii::$app->subdb->getCurrentPuid();
        	        if(!empty($puid) && $puid == '4760'){
            	        $end_time = date('Y-m-d H:i:s');
            	        $dis = strtotime($end_time) - strtotime($start_time);
            	        \Yii::info('GetOrderListByCondition puid:'.$puid.'???starttime:'.$start_time.'???val:'.$scaning_val.'???time:'.$dis, "file");
        	        }
        	        
        	        return json_encode(['code'=>'0', 'data'=>$data]);
        	    }
        	    
        	    return json_encode(['code'=>'1', 'data'=>'??????????????????????????????????????????']);
    	    }
    	    else 
    	    {
    	        return json_encode(['code'=>'1', 'data'=>'??????????????????????????????????????????']);
    	    }
	    }
	    catch(\exception $ex)
	    {
	        return json_encode(['code'=>'1', 'data'=>$ex->getMessage()]);
	    }
	}
	
	/**
	 +----------------------------------------------------------
	 * ?????????????????? ??????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/08/06				?????????
	 +----------------------------------------------------------
	 **/
	public function actionManualOrderBox(){
		$platform = '';
		if (!empty($_REQUEST['platform'])){
			$platform = $_REQUEST['platform'];
		}
		AppTrackerApiHelper::actionLog("Oms-erp", "/order/manualorder");
		$uid = \Yii::$app->subdb->getCurrentPuid();
		
		
		//??????
		/*$account_result = PlatformAccountApi::getPlatformAllAccount($uid, $platform);
		
		
		if($account_result['success']){
			foreach ($account_result['data'] as $seller_key => $seller_value){
				$seller_array[$seller_key] = $seller_value;
			}
		}else{
			return $this->render('//errorview',['title'=>'????????????','error'=>'??????????????????????????? ???????????? ?????? ?????????']);
			echo "??????????????????????????? ???????????? ?????? ?????????";
			return ;
		}*/
		//??????????????????????????????lrq20170828
		$seller_array = PlatformAccountApi::getPlatformAuthorizeAccounts(strtolower($platform));
		if(empty($seller_array)){
			return $this->render('//errorview',['title'=>'????????????','error'=>'??????????????????????????? ???????????? ?????? ?????????']);
			echo "??????????????????????????? ???????????? ?????? ?????????";
			return ;
		}
		//?????????
		$path = "/order/order/".ucfirst(strtolower($platform))."ManualCaptureOrderDefault";
		$defaultRT = ConfigHelper::getConfig($path);
		
		if (!empty($defaultRT) && is_string($defaultRT)){
			$defaultRT = json_decode($defaultRT,true);
		}
		
		//??????
		$country = StandardConst::$COUNTRIES_CODE_NAME_CN;
		
		foreach($country as $country_code=>&$country_label){
			$country_label = $country_code."(".$country_label.")";
		}
		
		unset($country['--']);
		
// 		$country = DataStaticHelper::getUseCountTopValuesFor(OrderHelper::$ManualOrderFrequencyCountryPath ,$country );
		
// 		$countryFormatter = [];
// 		if (!empty($country['recommended'])){
// 			$countryFormatter =['?????????'=>$country['recommended'],'?????????'=>$country['rest']];
// 		}else{
// 			$countryFormatter = $country['rest'];
// 		}
		
		//??????
		$ALLsites = PlatformAccountApi::getAllPlatformOrderSite();
		
		if (!empty($ALLsites[$platform])){
			$sites = $ALLsites[$platform];
		}else{
			$sites = [];
		}
		
		if($platform == 'wish'){
			//??????wish????????????
			$selleruserids_new = \eagle\modules\platform\apihelpers\WishAccountsApiHelper::getWishAliasAccount($seller_array);
			unset($seller_array);
			$seller_array = $selleruserids_new;
		}else if($platform == 'ebay'){
			//??????ebay????????????
			$selleruserids_new = EbayAccountsApiHelper::getEbayAliasAccount($seller_array);
			unset($seller_array);
			$seller_array = $selleruserids_new;
		}
		
		return $this->render('manualorder',['platform'=>$platform , 'seller_array'=>$seller_array , 'defaultRT'=>$defaultRT , 'country'=>$country , 'sites'=>$sites]);
	}//end of function actionManualOrderBox
	
	/**
	 +----------------------------------------------------------
	 * ??????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/08/06				?????????
	 +----------------------------------------------------------
	 **/
	public function actionSaveManualOrder(){
		$uid = \Yii::$app->subdb->getCurrentPuid();
		$order = [];
		
		$requireColumn = ['order_source_order_id'=>'?????????' ,'selleruserid'=>'??????' , 'currency'=>'??????' , 'consignee'=>'????????????' , 'consignee_country_code'=>'??????' ,'consignee_address_line1'=>'??????1' , 'consignee_postal_code'=>'??????','consignee_city'=>'??????','consignee_province'=>'???/???'];
		$optionColumn = ['consignee_address_line2'=>'??????2','consignee_phone'=>'??????','consignee_address_line3'=>'??????3','consignee_mobile'=>'??????','desc'=>'????????????','shipping_cost'=>'??????' ,'consignee_email'=>'????????????' , 'order_source_site_id'=>'??????'];
		
		foreach($_REQUEST as $key=>$value){
			
			if (array_key_exists($key ,$requireColumn )){
				//?????? ???
				if (!empty($_REQUEST[$key])){
					$order[$uid][$key]=$value;
				}else{
					exit('failure'.$requireColumn[$key]."????????????");
				}
			}elseif(array_key_exists($key ,$optionColumn )){
				//?????????
				if (!empty($_REQUEST[$key])){
					$order[$uid][$key]=$value;
				}
			}
		}
		
		//???????????????????????? 
		$checkRT = OdOrder::find()->where(['order_source_order_id'=>$_REQUEST['order_source_order_id'] ])->asArray()->one();
		if (!empty($checkRT)){
			exit('failure'.$_REQUEST['order_source_order_id']."????????????????????????".$checkRT['order_source']."????????????????????????");
		}
		
		//detail ?????? 
		if (!empty($_REQUEST['item'])){
			$item_count = count($_REQUEST['item']['sku']);
		}
		
		$subTotal = 0;
		for($index =0;$index<$item_count;$index++){
			//????????????
			$sku = $_REQUEST['item']['sku'][$index];
			$productInfo = ProductHelper::getProductInfo($sku);
			$currentItem = [
				'order_source_order_id'=>$_REQUEST['order_source_order_id'] , 
				'sku'=>$sku , 
				'ordered_quantity'=> $_REQUEST['item']['qty'][$index],
				'quantity'=> $_REQUEST['item']['qty'][$index],
				'price'=>$_REQUEST['item']['price'][$index],
				'order_source_itemid'=>'',
				'delivery_status'=>'allow',
			];
			
			if (!empty($productInfo)){
				$currentItem['product_name'] =  $productInfo['name'];
				$currentItem['photo_primary'] =  $productInfo['photo_primary'];
			}
			
			if (empty($currentItem['product_name'])) $currentItem['product_name'] = $sku;
			
			$subTotal += $_REQUEST['item']['qty'][$index]*$_REQUEST['item']['price'][$index];
			$order[$uid]['items'][]=$currentItem;
		}
		$order[$uid]['order_status'] =OdOrder::STATUS_PAY; //????????????
		$order[$uid]['subtotal'] = $subTotal; //
		$order[$uid]['grand_total'] = $subTotal+$_REQUEST['shipping_cost'];
		$order[$uid]['order_source'] = $_REQUEST['order_source'];
		$order[$uid]['order_source_create_time'] = time();
		$order[$uid]['consignee_country'] = StandardConst::$COUNTRIES_CODE_NAME_EN[$_REQUEST['consignee_country_code']];
		$order[$uid]['order_capture'] = 'Y';  //?????? ????????????
		$order[$uid]['paid_time'] = time(); //????????????
		
		$rt = OrderHelper::importPlatformOrder($order);
		
		if ($rt['success'] ===0){
			$path = "/order/order/".ucfirst(strtolower($order[$uid]['order_source']))."ManualCaptureOrderDefault";
			$default = ['selleruserid'=>$_REQUEST['selleruserid'] , 'currency'=>$_REQUEST['currency'] ,'consignee_country_code'=>$_REQUEST['consignee_country_code']];
			if (!empty($_REQUEST['order_source_site_id'])){
				$default['order_source_site_id'] = $_REQUEST['order_source_site_id'];
			}
			ConfigHelper::setConfig($path, json_encode($default));
			DataStaticHelper::addUseCountFor(OrderHelper::$ManualOrderFrequencyCountryPath, $order[$uid]["consignee_country_code"]);
			exit('success');
		}else{
			exit('failure'.$rt['message']);
		}
		
	}//end of function actionSaveManualOrder
	
	/**
	 +----------------------------------------------------------
	 * excel?????????????????? ?????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/08/06				?????????
	 +----------------------------------------------------------
	 **/
	public function actionImportManualOrderModal(){
		$platform = '';
		if (!empty($_REQUEST['platform'])){
			$platform = $_REQUEST['platform'];
		}
		AppTrackerApiHelper::actionLog("Oms-erp", "/order/manualorder");
		$uid = \Yii::$app->subdb->getCurrentPuid();
		
		$account_result = PlatformAccountApi::getPlatformAllAccount($uid, $platform);
		
		
		if($account_result['success']){
			foreach ($account_result['data'] as $seller_key => $seller_value){
				$seller_array[$seller_key] = $seller_value;
			}
		}else{
			return $this->render('//errorview',['title'=>'????????????','error'=>'??????????????????????????? ???????????? ?????? ?????????']);
			echo "??????????????????????????? ???????????? ?????? ?????????";
			return ;
		}
		
		//????????? 
		$path = "/order/order/".ucfirst(strtolower($platform))."ManualCaptureOrderDefault";
		$defaultRT = ConfigHelper::getConfig($path);
		
		if (!empty($defaultRT) && is_string($defaultRT)){
			$defaultRT = json_decode($defaultRT,true);
		}
		
		//??????
		$ALLsites = PlatformAccountApi::getAllPlatformOrderSite();
		
		if (!empty($ALLsites[$platform])){
			$sites = $ALLsites[$platform];
		}else{
			$sites = [];
		}
		return $this->renderPartial('importManualOrderModal',['platform'=>$platform , 'seller_array'=>$seller_array , 'defaultRT'=>$defaultRT ,'sites'=>$sites]);
	}//end of function actionImportManualOrderModal
	
	/**
	 +----------------------------------------------------------
	 * ?????? ???????????? ??????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/08/16				?????????
	 +----------------------------------------------------------
	 **/
	public function actionEditOrderModal(){
		if (!empty($_REQUEST['orderid'])){
			AppTrackerApiHelper::actionLog("Oms-erp", "/order/EditOrderModal");
			$order = OdOrder::findOne($_REQUEST['orderid']);
			if (!empty($order)){
				//$warehouses = InventoryApiHelper::getWarehouseIdNameMap();
				$ShippingServices = CarrierApiHelper::getShippingServices2_1();
				$countryList = StandardConst::$COUNTRIES_CODE_NAME_EN;
				$ordershipped = $order->getTrackinfos();
				//??????  ????????????????????? ??? ???????????????
				list($shipmethodList, $warehouseList , $locList) = OrderHelper::getWarehouseAndShipmentMethodData();
				
				$customerShippingMethod = OrderApiHelper::getCustomerShippingMethod($_REQUEST['orderid']);
				
				
				//?????????????????????????????? start
				$existProductResult = OrderBackgroundHelper::getExitProductRootSKU([$order]);
				//?????????????????????????????? end
				
				//????????????(???????????????????????? ??????)??????????????? health check
				$HealthCheckClassList = ['consignee'=>'glyphicon glyphicon-ok text-success'];
				
				//????????????????????????????????????????????????1??????????????????
				$declaration=0;
				$List = OdOrder::find()->where(['order_id'=>$_REQUEST['orderid']])->one();
				$declaration_list=CarrierDeclaredHelper::getOrderDeclaredInfoByOrder($List);
				foreach($declaration_list as $declaration_listone){
					if($declaration_listone['not_declaration']==1)
						$declaration=1;
				}
				if ($declaration==1){
					$HealthCheckClassList['declaration'] = 'glyphicon glyphicon-remove text-warn';
				}else{
					$HealthCheckClassList['declaration'] = 'glyphicon glyphicon-ok text-success';
				}
				
				//??????ebay  check out ??????
				$orderCheckOutList = [];//ebay ?????????
				$paypal=[];//ebay ?????????
				if ($order->order_source == 'ebay'){
					//?????? ??????
					$orderCheckOutList = OrderApiHelper::getEbayCheckOutInfo($order->order_source_order_id);
					//paypay ???????????????
					$paypal = OdPaypalTransaction::findOne(['order_id'=>$order->order_id]);
				}
				
    			//?????????????????????????????????????????????????????????????????????????????????
                $order_rootsku_product_image = OrderHelper::GetRootSkuImage(['0' => $order]);
				
				if (empty($order->default_shipping_method_code) ||empty($order->default_carrier_code) ){
					$HealthCheckClassList['shipmethod'] = 'glyphicon glyphicon-remove text-warn';
				}else{
					$HealthCheckClassList['shipmethod'] = 'glyphicon glyphicon-ok text-success';
				}
				
				//????????????????????????
				$upOrDownDiv=array('cursor'=>0,'up'=>'','down'=>'');
				$rtnjson='';
				if(!empty($_REQUEST['upOrDownDivtxt'])){
					$is_json=json_decode(base64_decode($_REQUEST['upOrDownDivtxt']));
					if(!empty($is_json)){
						$rtnjson=base64_decode($_REQUEST['upOrDownDivtxt']);
					}
					else{
						$orderAllId=array();
						$orderAllId['order_id']=explode(',',$_REQUEST['upOrDownDivtxt']); 
						$rtnjson=json_encode($orderAllId);
					}
					if(!empty($rtnjson)){
						$rtn=json_decode($rtnjson,true);
						foreach ($rtn['order_id'] as $rtnkeys=> $rtnone){
							$rtnone_str=preg_replace('/^0+/','',$rtnone);
							$rtnone_id=preg_replace('/^0+/','',$_REQUEST['orderid']);
							if($rtnone_str==$rtnone_id){
								if($rtnkeys==0)
									$upOrDownDiv['cursor']=1;
								else if($rtnkeys==(count($rtn['order_id'])-1))
									$upOrDownDiv['cursor']=3;
								else
									$upOrDownDiv['cursor']=2;
								
								$upOrDownDiv['up']=empty($rtn['order_id'][$rtnkeys-1])?'':$rtn['order_id'][$rtnkeys-1];
								$upOrDownDiv['down']=empty($rtn['order_id'][$rtnkeys+1])?'':$rtn['order_id'][$rtnkeys+1];
								
								if(empty($upOrDownDiv['up']) && empty($upOrDownDiv['down']))
									$upOrDownDiv['cursor']=0;
								break;
							}
						}
					}
				}
				
				$selleruserids_new = array();
				
				//??????ebay???????????? S
				if($order->order_source == 'ebay'){
					$selleruserids = array();
					$selleruserids[$order->selleruserid] = $order->selleruserid;
					
					$selleruserids_new = EbayAccountsApiHelper::getEbayAliasAccount($selleruserids);
				}
				//??????ebay???????????? E
				
				//??????wish????????????
				if($order->order_source == 'wish'){
					$selleruserids_new = \eagle\modules\platform\apihelpers\WishAccountsApiHelper::getWishAliasAccount(array($order->selleruserid=>$order->selleruserid));
				}

				return $this->renderPartial('editOrderModal.php',['order'=>$order , 'warehouses'=>$warehouseList , 
						'ShippingServices'=>$ShippingServices , 'countryList'=>$countryList , 'ordershipped'=>$ordershipped , 
						'shipmethodList'=>$shipmethodList , 'customerShippingMethod'=>$customerShippingMethod['data'] ,'existProductResult'=>$existProductResult , 
						'HealthCheckClassList'=>$HealthCheckClassList , 'orderCheckOutList'=>$orderCheckOutList ,'paypal'=>$paypal,'upOrDownDiv'=>$upOrDownDiv,
						'upOrDownDivtxt'=>base64_encode($rtnjson), 'order_rootsku_product_image' => $order_rootsku_product_image, 'selleruserids_new'=>$selleruserids_new]);
			}else{
				return $_REQUEST['orderid']."??????????????????";
			}
			
		}else{
			return "??????????????????";
		}
		
	}//end of function actionEditOrderModal
	
	
	/**
	 +----------------------------------------------------------
	 * ??????????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/08/16				?????????
	 +----------------------------------------------------------
	 **/
	public function actionGetOrderShipStatusSituation(){
		$rt = OrderGetDataHelper::getOrderSyncShipSituation(@$_REQUEST['platform'],@$_REQUEST['order_status']);
		return json_encode($rt);
	}//end of function actionCalcOrderShipStatusCount
	
	/**
	 +----------------------------------------------------------
	 * ?????????????????? ?????? ????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/09/28				?????????
	 +----------------------------------------------------------
	 **/
	public function actionSetOrderSyncShipStatusComplete(){
		
		if (!empty($_REQUEST['orderIdList'])){
			$orderIdList = $_REQUEST['orderIdList'];
			if (count($orderIdList)==0){
				return json_encode(array('ack'=>false,'message'=>TranslateHelper::t('??????????????????')));
			}
			AppTrackerApiHelper::actionLog("Oms-erp", "/order/setordershipstatuscomplete");
			$rt = OrderApiHelper::setOrderSyncShipStatusComplete($orderIdList);
			return json_encode($rt);
		}else{
			return json_encode(array('ack'=>false,'message'=>TranslateHelper::t('??????????????????')));
		}
		
		
	}//end of function actionSetOrderShipStatusComplete
	
	
	/**
	 +----------------------------------------------------------
	 * ?????????????????? ??? ???????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/10/10				?????????
	 +----------------------------------------------------------
	 **/
	public function actionSaveWarehouseShipservice(){
		$fullName = \Yii::$app->user->identity->getFullName();
		$serviceid = SysShippingService::findOne($_REQUEST['shipmentMethod']);
		$newAttr = ['default_shipping_method_code'=>$_REQUEST['shipmentMethod'], 'default_carrier_code'=>$serviceid->carrier_code   , 'default_warehouse_id'=>$_REQUEST['warehouse'] ] ;
		$action = '????????????';
		$module = 'order';
		$rt = \eagle\modules\order\helpers\OrderUpdateHelper::updateOrder($_REQUEST['orderId'], $newAttr,false ,$fullName  , $action , $module );
		$rt['success'] = $rt['ack'];
		exit(json_encode($rt));
	}//end of function actionSaveWarehouseShipservice
	
	
	/**
	 +----------------------------------------------------------
	 * ajax ????????? ?????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2014/10/26				?????????
	 +----------------------------------------------------------
	 **/
	public function actionAjaxSaveOrder(){
		$fullName = \Yii::$app->user->identity->getFullName();
		if (isset($_REQUEST['consignee_email']) && $_REQUEST['orderId']){
			$newAttr = ['consignee_email'=>$_REQUEST['consignee_email'] ] ;
			$action = '????????????';
			$module = 'order';
			$rt = \eagle\modules\order\helpers\OrderUpdateHelper::updateOrder($_REQUEST['orderId'], $newAttr,false ,$fullName  , $action , $module );
			$rt['success'] = $rt['ack'];
		}else{
			$rt = ['success'=>false , 'message'=>'???????????????'];
		}
		
		exit(json_encode($rt));
	}//end of function function actionAjaxSaveOrder
	
	
	/**
	 +----------------------------------------------------------
	 * ?????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2014/05/06				?????????
	 +----------------------------------------------------------
	 **/
	public function actionSaveConsigneeInfo(){
		if (!empty($_REQUEST['order_id'])){
			AppTrackerApiHelper::actionLog("Oms-erp", "/order/SaveConsigneeInfo");
			$OrderModel = OdOrder::find()->where(['order_id'=>$_REQUEST['order_id']])->One();
				
			
			if (empty($OrderModel)) exit(json_encode(['success'=>false , 'message'=>'E002 ???????????????!']));
				
			/*
			$rt = OrderHelper::setOriginShipmentDetail($OrderModel);
			if ($rt['success'] ==false){
				exit(json_encode(['success'=>false , 'message'=>'E003??????????????????????????????!']));
			}
			*/
			$_tmp = $_POST;
			unset($_tmp['order_id']);
			
			if (empty($OrderModel->origin_shipment_detail)){
				//?????????????????????????????????????????????????????????
				$shipment_info = [
				'consignee'=>$OrderModel->consignee,
				'consignee_postal_code'=>$OrderModel->consignee_postal_code,
				'consignee_phone'=>$OrderModel->consignee_phone,
				'consignee_mobile'=>$OrderModel->consignee_mobile,
				'consignee_fax'=>$OrderModel->consignee_fax,
				'consignee_email'=>$OrderModel->consignee_email,
				'consignee_company'=>$OrderModel->consignee_company,
				'consignee_country'=>$OrderModel->consignee_country,
				'consignee_country_code'=>$OrderModel->consignee_country_code,
				'consignee_city'=>$OrderModel->consignee_city,
				'consignee_province'=>$OrderModel->consignee_province,
				'consignee_district'=>$OrderModel->consignee_district,
				'consignee_county'=>$OrderModel->consignee_county,
				'consignee_address_line1'=>$OrderModel->consignee_address_line1,
				'consignee_address_line2'=>$OrderModel->consignee_address_line2,
				'consignee_address_line3'=>$OrderModel->consignee_address_line3,
				];
					
				$_tmp['origin_shipment_detail'] = json_encode($shipment_info);
			}
			$fullName = \Yii::$app->user->identity->getFullName();
			$action = '????????????';
			$module = 'order';
			
			$rt = OrderUpdateHelper::updateOrder($OrderModel, $_tmp ,  false , $fullName  , $action , $module  );
			
			
			if ($rt['ack']){
				//AppTrackerApiHelper::actionLog("Oms-aliexpress", "/order/aliexpress/edit-save");
				//OperationLogHelper::log('order',$OrderModel->order_id,'????????????','??????????????????????????????',\Yii::$app->user->identity->getFullName());
				exit(json_encode(['success'=>true , 'message'=>'']));
			}else{
				exit(json_encode(['success'=>false , 'message'=>'E004??????????????????????????????!'.$rt['message']]));
			}
		}else{
			exit(json_encode(['success'=>false , 'message'=>'E001??????????????????????????????!']));
		}
	}//end of actionSaveConsigneeInfo
	
	/**
	 +----------------------------------------------------------
	 * ????????????????????? ??????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/10/08				?????????
	 +----------------------------------------------------------
	 **/
	public function actionSaveMemoInfo(){
		try {
			if (!empty($_REQUEST['order_id'])){
				
				AppTrackerApiHelper::actionLog("Oms-erp", "/order/SaveMemoInfo");
				$OrderModel = OdOrder::find()->where(['order_id'=>$_REQUEST['order_id']])->One();
				
					
				if (empty($OrderModel)) exit(json_encode(['success'=>false , 'message'=>'E002 ???????????????!']));
				$err_msg = '';
				
				$rt = OrderHelper::addOrderDescByModel($OrderModel, $_REQUEST['desc'], 'order', '????????????');
					
				if ($rt['success'] == true){
					//OperationLogHelper::log('order',$OrderModel->order_id,'????????????','????????????: ('.$olddesc.'->'.$OrderModel->desc .')',\Yii::$app->user->identity->getFullName());
				}else{
					$err_msg .= $OrderModel->order_id." ?????????????????????";
				}
				exit(json_encode(['success'=>$rt['success'] , 'message'=>$err_msg]));
			}else{
				exit(json_encode(['success'=>false , 'message'=>'E001??????????????????????????????!']));
			}
		} catch (\Exception $e) {
			
			exit(json_encode(['success'=>false , 'message'=>'E003 '.$e->getMessage()]));
		}
		
		
	}//end of function actionSaveMemoInfo 
	
	
	/**
	 +----------------------------------------------------------
	 * ????????????????????? ????????????billing??????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lzhl 	2017/03/17				?????????
	 +----------------------------------------------------------
	 **/
	public function actionSaveBillingInfo(){
		try {
			if (!empty($_REQUEST['order_id'])){
	
				AppTrackerApiHelper::actionLog("Oms-erp", "/order/SaveBillingInfo");
				$OrderModel = OdOrder::find()->where(['order_id'=>$_REQUEST['order_id']])->One();
	
					
				if (empty($OrderModel)) exit(json_encode(['success'=>false , 'message'=>'E002 ???????????????!']));
				$err_msg = '';
				
				$billing_info = empty($_REQUEST['billing_info'])?[]:$_REQUEST['billing_info'];
				$OrderModel->billing_info = json_encode($billing_info);
				//
				//$rt = OrderHelper::addOrderDescByModel($OrderModel, $_REQUEST['desc'], 'order', '????????????');
					
				if ($OrderModel->save(false)){
					$rt = true;
					//OperationLogHelper::log('order',$OrderModel->order_id,'????????????','????????????: ('.$olddesc.'->'.$OrderModel->desc .')',\Yii::$app->user->identity->getFullName());
				}else{
					$rt = false;
					$err_msg .= $OrderModel->order_id." ?????????????????????";
				}
				exit(json_encode(['success'=>$rt , 'message'=>$err_msg]));
			}else{
				exit(json_encode(['success'=>false , 'message'=>'E001??????????????????????????????!']));
			}
		} catch (\Exception $e) {
				
			exit(json_encode(['success'=>false , 'message'=>'E003 '.$e->getMessage()]));
		}
	
	
	}//end of function actionSaveMemoInfo
	
	/**
	 +----------------------------------------------------------
	 * ????????????????????? ????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/10/12				?????????
	 +----------------------------------------------------------
	 **/
	public function actionSaveEditOrderItem(){
		if (isset($_POST['item']) && isset($_REQUEST['order_id'])){
			$item_tmp = $_POST['item'];
			$order = OdOrder::findOne($_REQUEST['order_id']);
			
			//?????? ??????????????????
			if (empty($order)) exit(json_encode(['success'=>false ,'message'=>$_REQUEST['order_id'].'?????????????????????????????? ???']));
			
			$result = ['success'=>true , 'message'=>''];
			$addtionLog = '';
			
			//??????item ????????????
			if (!empty($_REQUEST['deldetailstr'])){
				$delOrderItemIdList = explode(',',$_REQUEST['deldetailstr']);
			}else{
				$delOrderItemIdList = [];
			}
			
			$isUpdateFirstSKU = false;
			$isUpdateMultiProduct = false;
			$firstSKU = '';
			
			$ignoreSKUList = CdiscountOrderInterface::getNonDeliverySku();
			 
			//????????????????????????
			$subtotal = 0;
			$is_edit_price = false;   //??????????????????
			foreach ($item_tmp['sku'] as $key=>$val){
				$currentSKUMsg = '';
				if (isset($item_tmp['itemid'][$key])){
					$item = OdOrderItem::findOne($item_tmp['itemid'][$key]);
					
					//??????????????? ????????? ?????? ?????????????????????????????????
					if (in_array($item->order_item_id ,$delOrderItemIdList )){
						continue;
					}else{
						if (empty($firstSKU) && in_array($item_tmp['sku'][$key],$ignoreSKUList ) ==false){
							$firstSKU = $item_tmp['sku'][$key];
						}
						if ($item->manual_status =='enable'){
							$OriginQty = $item->quantity; //??????????????????
						}else{
							$OriginQty = 0; // ????????????????????????????????????
						}
						
						//??????sku
						$OriginSKU = $item->sku ;
						$item->sku = $item_tmp['sku'][$key];
						//??????????????????????????????
						$item->quantity = $item_tmp['quantity'][$key];
						$item->manual_status = $item_tmp['manual_status'][$key];
						if ($item->item_source == 'platform' ){
							if ($item->manual_status == 'enable' ){
								$item->delivery_status = 'allow';//????????????
							}else{
								$item->delivery_status = 'ban';//????????????
							}
						}
					}
					
					
				}else{
					$item = new OdOrderItem();
					$OriginQty = 0; //??????????????????
					
					//????????????????????????????????????
					$item->order_id = $order->order_id;
					$item->product_name = $item_tmp['product_name'][$key];
					$item->sku = $item_tmp['sku'][$key];
					$item->ordered_quantity = $item_tmp['quantity'][$key];
					$item->quantity = $item_tmp['quantity'][$key];
					$item->order_source_srn =  empty($item_tmp['order_source_srn'][$key])?$order->order_source_srn:$item_tmp['order_source_srn'][$key];
					$item->price = $item_tmp['price'][$key];
					$item->update_time = time();
					$item->create_time = is_null($item->create_time)?time():$item->create_time;
					$item->manual_status = 'enable';// ??????
					$item->item_source = 'local';  //????????????
					$currentSKUMsg = '?????????';
					$item->delivery_status = 'allow'; //????????????
					$item->root_sku = ProductHelper::getRootSkuByAlias($item->sku,$order->order_source ,$order->selleruserid );
					
					if (!empty($item->root_sku)){
						$productInfo = \eagle\modules\catalog\helpers\ProductHelper::getProductInfo($item->root_sku);
						if (isset($productInfo['photo_primary'])){
							$item->photo_primary = $productInfo['photo_primary'];
						}
					}
					
					
					if (empty($firstSKU) && in_array($item->sku,$ignoreSKUList ) ==false){
						$firstSKU = $item->sku;
					}
				}
				
				//???????????????????????????????????????????????????
				if($order->order_status < 500 && $order->order_capture == 'Y' && $order['reorder_type'] != 'after_shipment' && isset($item_tmp['edit_price'][$key])){
					$OriginPrice = $item->price;
					$item->price = (empty($item_tmp['edit_price'][$key]) ? 0 : $item_tmp['edit_price'][$key]);
					if($OriginPrice != $item->price){
						$is_edit_price = true;
						$subtotal += (empty($item->quantity) ? 0 : $item->quantity) * (empty($item->price) ? 0 : $item->price);
						
						$addtionLog .= " ".$item->sku."  price $OriginPrice=>".$item->price;
					}
				}
				
				if ($item->save()){
					//?????? ??????sku ???????????????
					if ($OriginQty != $item_tmp['quantity'][$key] && !empty($item->root_sku)){
					
						list($ack , $code , $message  )  = array_values( OrderBackgroundHelper::updateUnshippedQtyOMS($item->root_sku, $order->default_warehouse_id, $order->default_warehouse_id, $OriginQty, $item_tmp['quantity'][$key]));
						if ($ack){
							//$addtionLog .= "$currentSKUMsg ".$item->root_sku." $OriginQty=>".$item_tmp['quantity'][$key];
						}
					}
					
					/*
					
					//??????sku
					if ($OriginSKU != $item_tmp['sku'][$key]){
						//??????sku??? ??????????????????????????????????????????????????????
						list($ack , $code , $message  )  = array_values( OrderBackgroundHelper::updateUnshippedQtyOMS($OriginSKU, $order->default_warehouse_id, $order->default_warehouse_id, $OriginQty, 0));
						if ($ack){
							$addtionLog .= ' ??????sku ???'.$OriginSKU."=>".$item_tmp['sku'][$key];
							$addtionLog .= " ???????????? ???sku $OriginSKU ??????????????? $OriginQty=>0";
						}
						
						list($ack , $message , $code , $rootSKU ) = array_values(OrderGetDataHelper::getRootSKUByOrderItem($item));
							
						list($ack , $code , $message  )  = array_values( OrderBackgroundHelper::updateUnshippedQtyOMS($rootSKU, $order->default_warehouse_id, $order->default_warehouse_id, 0, $item_tmp['quantity'][$key]));
						if ($ack){
							$addtionLog .= "???????????? sku $rootSKU 0=>".$item_tmp['quantity'][$key];
						}
						
					}else{
						//?????? ??????sku ???????????????
						if ($OriginQty != $item_tmp['quantity'][$key]){
							list($ack , $message , $code , $rootSKU ) = array_values(OrderGetDataHelper::getRootSKUByOrderItem($item));
								
							list($ack , $code , $message  )  = array_values( OrderBackgroundHelper::updateUnshippedQtyOMS($rootSKU, $order->default_warehouse_id, $order->default_warehouse_id, $OriginQty, $item_tmp['quantity'][$key]));
							if ($ack){
								$addtionLog .= "$currentSKUMsg $rootSKU $OriginQty=>".$item_tmp['quantity'][$key];
							}
						}
					}
					*/
					
					//?????? ??????sku ??????????????? 
					if ($OriginQty != $item_tmp['quantity'][$key]){
						$addtionLog .= " $currentSKUMsg ".$item_tmp['sku'][$key] ." qty $OriginQty=>".$item_tmp['quantity'][$key];
					}
				}//end of item save 
				else {
					$result['success'] = false;
					foreach($item->getErrors() as $row ){
						$result['message'] .= $row;
					}
					
				}
			}//end of each item 
			
			//????????????????????????????????????????????????
			if($is_edit_price && $order->order_status < 500 && $order->order_capture == 'Y' && $order['reorder_type'] != 'after_shipment'){
				$order->subtotal = $subtotal;
				$order->grand_total = $order->subtotal + $order->shipping_cost;
				$order->save(false);
			}
			
			//???????????? ,
			foreach($delOrderItemIdList as $delOrderItemID){
				$delRT = OrderUpdateHelper::deleteOrderItem($delOrderItemID,$order->default_warehouse_id);
				$isUpdateFirstSKU = true;
			}
			$updateData = [];
			//????????????????????????sku
			if ($order->first_sku != $firstSKU){
				$updateData['first_sku'] = $firstSKU;
			}
			
			//??????????????????????????????
			if (count($item_tmp['sku']) >1 && $order->ismultipleProduct !='Y'){
				// ??????????????????????????? 1 ?????????????????????N?????????????????????Y
				$updateData['ismultipleProduct'] = 'Y'; 
			}else if (count($item_tmp['sku']) == 1 && $order->ismultipleProduct =='Y'){
				// ?????????????????????????????? 1 ?????????????????????Y?????????????????????N
				$updateData['ismultipleProduct'] = 'N';
			}
			$fullName = \Yii::$app->user->identity->getFullName();
			$action = '????????????';
			$module = 'order';
			
			$updateOrderRT = OrderUpdateHelper::updateOrder($order, $updateData , false , $fullName  , $action, $module );
			
			//?????????????????????
			if (!empty($addtionLog)){
    			OperationLogHelper::log($module,$order->order_id,$action,'??????????????????????????????'.$addtionLog,$fullName);
    		}
    		
			exit(json_encode($result));
		}//end of validate  $_POST
		else{
			exit(json_encode(['success'=>false ,'message'=>'???????????? ???']));
		}
	}//end of function actionSaveEditOrderItem
	
	/**
	 +----------------------------------------------------------
	 * ?????? ?????????????????????item ??????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/10/12				?????????
	 +----------------------------------------------------------
	 **/
	public function actionRefreshEditOrderItemInfo(){
		if (!empty($_REQUEST['order_id'])){
			AppTrackerApiHelper::actionLog("Oms-erp", "/order/EditOrderModal");
			$order = OdOrder::findOne($_REQUEST['order_id']);
			if (!empty($order)){
				//?????????????????????????????? start
				$existProductResult = OrderBackgroundHelper::getExitProductRootSKU([$order]);
				//?????????????????????????????? end
		
				return OrderFrontHelper::displayEditOrderItemInfo($order, $existProductResult);
			}else{
				return $_REQUEST['orderid']."??????????????????";
			}
				
		}else{
			return "??????????????????";
		}
	}//end of function actionRefreshEditOrderItemInfo
	
	/**
	 +----------------------------------------------------------
	 * ???????????? ??????????????? ???  ebay ?????????paypal???????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/12/14				?????????
	 +----------------------------------------------------------
	 **/
	public function actionSetOrderVerifyPass(){
		if (!empty($_REQUEST['orderIdList'])){
			$orderIdList = $_REQUEST['orderIdList'];
			if (count($orderIdList)==0){
				return json_encode(array('ack'=>false,'message'=>TranslateHelper::t('??????????????????')));
			}
			AppTrackerApiHelper::actionLog("Oms-erp", "/order/setorderverifypass");
			$rt = OrderApiHelper::setOrderVerifyPass($orderIdList);
			return json_encode($rt);
		}else{
			return json_encode(array('ack'=>false,'message'=>TranslateHelper::t('??????????????????')));
		}
	}//end of actionSetOrderVerifyPass
	
	/**
	 +----------------------------------------------------------
	 * ??????????????????   action
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw 	2016/12/15				?????????
	 +----------------------------------------------------------
	 **/
	public function actionDeleteManualOrder(){
		if (!empty($_POST['orders'])){
			$orderIdList =  $_POST['orders'];
			$rt = OrderHelper::deleteManualOrder($orderIdList);
			
			if($rt['success'] == false){
				return $this->renderJson(['success'=>false,'code'=>400,'type'=>'message','timeout'=>2,'message'=>$rt['message']]);
			}else{
				return $this->renderJson(['success'=>true,'code'=>200,'type'=>'message','timeout'=>2,'message'=>'???????????????','reload'=>true]);
			}
		}else{
			return $this->renderJson(['success'=>false,'code'=>400,'type'=>'message','timeout'=>2,'message'=>'????????????????????????']);
		}
	}
	
	/**
	 +----------------------------------------------------------
	 * ?????? ???????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/10/26				?????????
	 +----------------------------------------------------------
	 **/
	public function actionUnbindingProductAlias(){
		
	}//end of function actionUnbindingProductAlias

	/**
	 +----------------------------------------------------------
	 * ????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lgw 	2017/03/07				?????????
	 +----------------------------------------------------------
	 **/
	public function actionOrderSaveDeclarationInfo(){
		$type = empty($_REQUEST['type'])?'':$_REQUEST['type'];   //????????????
		$order_source=empty($_REQUEST['data'][0])?'':$_REQUEST['data'][0];      //????????????
		$Order_itemid = empty($_REQUEST['data'][1])?'':$_REQUEST['data'][1];        //itemsid
		$sku=empty($_REQUEST['data'][2])?'':$_REQUEST['data'][2];       //sku
		$NameCNList = empty($_REQUEST['data'][3])?'':$_REQUEST['data'][3];        //???????????????
		$NameENList = empty($_REQUEST['data'][4])?'':$_REQUEST['data'][4]; 		//???????????????
		$PriceList = empty($_REQUEST['data'][5])?'0':$_REQUEST['data'][5]; 		//??????
		$WeightList = empty($_REQUEST['data'][6])?'0':$_REQUEST['data'][6];			 //??????
		$detailHsCodeList = empty($_REQUEST['data'][7])?'':$_REQUEST['data'][7];			 //????????????
		
		if(strpos($WeightList,'.') || (float)$WeightList<0){
			return json_encode(['success'=>false, 'message'=>'????????????????????????????????????','data'=>'']);
		}

		$success = true;
		$err="";

		//???????????????????????????????????????
		$item = OdOrderItem::find()->where(['order_item_id'=>$Order_itemid])->one();
		$declaration = json_decode($item->declaration,true);
		if(!empty($declaration['isChange']) && $declaration['isChange']=='Y')
			$ischange='Y';
		else
			$ischange = empty($_REQUEST['ischange'])?'N':'Y';			 //?????????????????????
			
		$msg = "???????????????"; //????????????
		$log=array($Order_itemid,$NameCNList,$NameENList,$PriceList,$WeightList,$detailHsCodeList);
		OperationLogHelper::batchInsertLog('order', $log, '??????????????????',$sku.'?????????????????????'.json_encode($log));
		$result=OrderUpdateHelper::setOrderItemDeclaration($Order_itemid,$NameCNList,$NameENList,$PriceList,$WeightList,$detailHsCodeList,$ischange);
		if(isset($result['ack']) && $result['ack']==false){
			$err.=$result['message']." err1;";
			$success=false;
		}

		if($type==1 || $type==2){
			$items=OrderGetDataHelper::getPayOrderItemBySKU($sku);
			foreach ($items as $itemsone){
				$result=OrderUpdateHelper::setOrderItemDeclaration($itemsone->order_item_id,$NameCNList,$NameENList,$PriceList,$WeightList,$detailHsCodeList,'Y');
				if(isset($result['ack']) && $result['ack']==false){
					$err.=$itemsone->order_id.':'.$result['message']." err1;";
					$success=false;
				}
			}
			
			if($type==2 && $success==true){
				$item = OdOrderItem::find()->where(['order_item_id'=>$Order_itemid])->one();
				$tmp_platform_itme_id = \eagle\modules\order\helpers\OrderGetDataHelper::getOrderItemSouceItemID($order_source, $item);
				$declared_params[]=array(
						'platform_type'=>$order_source,
						'itemID'=>$tmp_platform_itme_id,
						'sku'=>$sku,
						'ch_name'=>$NameCNList,
						'en_name'=>$NameENList,
						'declared_value'=>$PriceList,
						'declared_weight'=>$WeightList,
						'detail_hs_code'=>$detailHsCodeList,
				);
				$result=CarrierDeclaredHelper::setOrderSkuDeclaredInfoBatch($declared_params);
				if($result==false){
					$err.=$itemsone->order_id.':'."????????????err2;";
					$success=false;
				}
				
				if(!empty($item->root_sku)){
					$info=array(
							'declaration_ch'=>$NameCNList,
							'declaration_en'=>$NameENList,
							'declaration_value'=>$PriceList,
							'prod_weight'=>$WeightList,
							'declaration_code'=>$detailHsCodeList,
					);
					OperationLogHelper::batchInsertLog('order', $info, '??????????????????',$sku.'???????????????????????????'.json_encode($info));
					$rt = \eagle\modules\catalog\helpers\ProductApiHelper::modifyProductInfo($item->root_sku,$info);
					if($rt['success']==false){
						$err.='??????????????????????????????:'.$rt['message']." err3;";
						$success=false;
					}
				}
			}
				
		}
		
		$html='<span class="nameChSpan">'.$NameCNList.'</span>&nbsp;/&nbsp;<span class="nameEnSpan">'.$NameENList.'</span>&nbsp;/&nbsp;$<span class="deValSpan">'.$PriceList.'</span>&nbsp;/&nbsp;<span class="weightSpan">'.$WeightList.'</span>???g???&nbsp;/&nbsp;<span class="hsCodeSpan">'.$detailHsCodeList.'</span>';
	
		return json_encode(['success'=>$success, 'message'=>$err,'data'=>$html]);
	}
	
	/**
	 +----------------------------------------------------------
	 * ????????????sku
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lgw 	2017/03/07				?????????
	 +----------------------------------------------------------
	 **/
	public function actionPairProduct(){
		$orderitemid=empty($_POST['orderitemid'])?'':$_POST['orderitemid'];
		$sku=empty($_POST['sku'])?'':$_POST['sku'];
		$type=empty($_POST['type'])?'':$_POST['type'];   //??????/??????/??????
	
		return $this->renderPartial('pairproduct',['orderitemid'=>$orderitemid  , 'sku'=>$sku,'type'=>$type]);
	}
	
	/**
	 +----------------------------------------------------------
	 * ????????????sku??????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lgw 	2017/03/07				?????????
	 +----------------------------------------------------------
	 **/
	public function actionSelectWareHoseProducts(){
		$page=empty($_POST['page'])?1:$_POST['page'];
		$conditionList=empty($_POST['condition'])?[]:$_POST['condition'];
		$type=empty($_POST['type'])?[]:$_POST['type'];
		
		if(empty($conditionList))  //????????????????????????
			$condition=[];
		else{
			if($type==1){
				$condition [] = ['or'=>['like','sku', $conditionList]];
			}
			else {
				$condition [] = ['or'=>['like','name', $conditionList]];
				$condition [] = ['or'=>['like','prod_name_ch', $conditionList]];
				$condition [] = ['or'=>['like','prod_name_en', $conditionList]];
				$condition [] = ['or'=>['like','declaration_ch', $conditionList]];
				$condition [] = ['or'=>['like','declaration_en', $conditionList]];
			}
		}

		$condition [] = ['and'=>"type!='C'"];
		$productData =ProductHelper::getProductlist($condition,'sku','asc',10,null,true,$page-1);

		if(empty($productData['data'])){
			$html='<div id="productbody_nosearch" class="modal-body tab-content col-xs-12"><span>????????????????????????,??????<a href="/catalog/product/index" target="_blank">????????????</a></span></div>';
		}
		else{
			$html='<table class="table table-condensed table-bordered myj-table">
			<thead>
			<tr class="text-center">
			<th>????????????</th>
			<th>??????</th>
			<th>????????????</th>
			<th>??????</th>
			</tr>
			</thead>
			<tbody>';
			$Wrap=0;
			if(!empty($productData['data'])){
				foreach($productData['data'] as $index=>$row):
				if($Wrap==0){
					$html.='<tr>';
			    } 
			                       $html.='<td style="width:309px;border: 1px solid #ccc;">
			                            <table>
			                                <tbody><tr>
			                                    <td>
			                                        <div class="quoteImgDivOut" style="margin-left: 6px;">
			                                            <div class="quoteImgDivIn">
			                                                        <img id="search_photo" src="'.$row['photo_primary'].'" class="imgCss" style="cursor: wait">
			                                            </div>
			                                        </div>
			                                    </td>
			                                    <td class="vAlignTop" style="padding-left: 6px;">
			                                                <p class="m0 txtleft">
			                                                    <span id="search_name" data-placement="right" data-toggle="popover" data-content="" data-original-title="" title="">
			                                                    '.$row['name'].'
			                                                    </span>
			                                                </p>
			                                                <p class="m0 txtleft">
			                                                    <span id="search_sku" data-placement="right" data-toggle="popover" data-content="" data-original-title="" title="">
			                                                    '.$row['sku'].'
			                                                    </span>
			                                                </p>
			                                    </td>
			                                </tr>
			                            </tbody></table>
			                        </td>
			                        <td style="width:77px;border: 1px solid #ccc;">
			                            <a class="Choice" href="javascript:javascript:OrderCommon.Choice(\''.$row['sku'].'\');" >??????</a>
			                        </td>'; 
			                if($Wrap==1)
			                	$Wrap=0;
			                else 
			                	$Wrap=1;
			                if($Wrap==0){ 
			                	$html.='</tr>';
			                } 
			                 endforeach;
			                 }
			           $html.='</tbody>
			        </table>';
			if(! empty($productData['pagination'])):
			//SizePager::widget(['pagination'=>$productData['pagination'] , 'pageSizeOptions'=>array( 5 , 20 , 50 , 100 , 200 ) , 'class'=>'btn-group dropup'])
			$html.='<div>
			    <div id="pagination" class="btn-group" style="width: 100%;text-align: center;">'.
			    	\yii\widgets\LinkPager::widget(['pagination' => $productData['pagination'],'options'=>['class'=>'pagination']]).
				'</div>
			</div>';
			endif;
		}
		return $html;
	}
	
	/**
	 +----------------------------------------------------------
	 * ????????????sku
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lgw 	2017/03/07				?????????
	 +----------------------------------------------------------
	 **/
	public function actionSaveRealtion(){
		$orderitemid=empty($_POST['orderitemid'])?'':$_POST['orderitemid'];
		$rootsku=empty($_POST['rootsku'])?'':$_POST['rootsku'];
		$sku=empty($_POST['sku'])?'':$_POST['sku'];
		$ordertype=empty($_POST['ordertype'])?'':$_POST['ordertype'];    //???????????? 0??????????????????1???????????????
		$type=empty($_POST['type'])?'':$_POST['type'];    //???????????? 0???????????????1?????????????????????2???????????????????????????
		$fullName = \Yii::$app->user->identity->getFullName();
		$msg='';
		
		$data_rootsku=($ordertype==0?'':$rootsku);    
		$tmp_type = !$type; // ??????????????????????????? ??? ????????????????????????????????? ?????? ??????????????????????????????????????????  ???????????????????????????????????????
		$result=array('ack'=>true,'success'=>true);
		
		$item = OdOrderItem::find()->select(['root_sku', 'order_id'])->where(['order_item_id'=>$orderitemid])->one();
		$old_root_sku=$item->root_sku;
		
		$log=array($orderitemid,$data_rootsku,$tmp_type,$fullName);
		OperationLogHelper::batchInsertLog('order', $log, '??????SKU-1','['.$sku.']?????????['.$data_rootsku.']');
		$rt=OrderUpdateHelper::saveItemRootSKU($orderitemid,$data_rootsku,$tmp_type , $fullName ,'sku??????');
		if($rt['ack']==false){
			$result['ack']=false;
			$msg.=$rt['message'].';';
		}
		
		//??????first_sku
		OrderUpdateHelper::resetFirstSku($item->order_id);

		if($type==1 || $type==2){
			$rt=OrderUpdateHelper::batchSaveItemRootSKU($sku,$data_rootsku ,$old_root_sku, $type, $fullName ,'sku??????');
			if($rt['ack']==false){
				$result['ack']=false;
				$msg.=$rt['message'].';';
			}
			
			if($type==2 && $result['ack']==true){
				if($ordertype==0){
					//????????????
					$log=array($rootsku,$sku);
					OperationLogHelper::batchInsertLog('order', $log, '????????????','['.$rootsku.']????????????:['.$sku.']');
					$rt=\eagle\modules\catalog\helpers\ProductApiHelper::deleteSkuAliases($rootsku,$sku);
					if($rt['success']==false){
						$msg.='??????????????????:'.$rt['msg'].';';
						$result['success']=false;
					}	
				}
				else{
					//??????????????????????????????
					if(!empty($old_root_sku)){
						$log=array($old_root_sku,$sku);
						OperationLogHelper::batchInsertLog('order', $log, '????????????','['.$old_root_sku.']????????????:['.$sku.']');
						$rt=\eagle\modules\catalog\helpers\ProductApiHelper::deleteSkuAliases($old_root_sku,$sku);
						if($rt['success']==false){
							$msg.='??????????????????:'.$rt['msg'].';';
							$result['success']=false;
						}
					}
						
					//????????????
					$aliasesList[$sku]=array(
							'alias_sku'=>$sku,
							'forsite'=>'',
							'pack'=>1,
							'comment'=>''
					);
					OperationLogHelper::batchInsertLog('order', $aliasesList[$sku], '????????????','['.$data_rootsku.']????????????['.$sku.']');
					$rt=\eagle\modules\catalog\helpers\ProductApiHelper::addSkuAliases($data_rootsku,$aliasesList);
					if($rt['success']==false){
						$msg.='??????????????????:'.$rt['message'].';';
						$result['success']=false;
					}
				}
			}
		}

		if($result['ack']==true){
			if($ordertype==0){
				$html=$data_rootsku."<br>
						<button type='button' class='iv-btn btn-important rootskubtn-pd' onclick='OrderCommon.PairProduct(\"".$orderitemid."\",\"".$sku."\",\"".$rootsku."\",1)'>????????????</button>
								";
			}
			else{
				//??????????????????
				$stock_list = array();
				$default_warehouse_id = 0;
				if(!empty($item)){
					$row = OdOrder::find()->select(['default_warehouse_id'])->where(['order_id' => $item->order_id])->one();
					if (!empty($row)){
						$default_warehouse_id = $row->default_warehouse_id;
						$warehouse_list[] = $row->default_warehouse_id;
						$sku_list[] = $data_rootsku;
						$stock_list = \eagle\modules\inventory\apihelpers\InventoryApiHelper::GetSkuStock($sku_list, $warehouse_list);
					}
				}
				
				$html=$data_rootsku."<br>
						<span style='color: #999999;'> ????????????: ".(empty($stock_list[$default_warehouse_id][$data_rootsku]) ? '0' : $stock_list[$default_warehouse_id][$data_rootsku])."</span>
						<br>
						<button type='button' class='iv-btn btn-important rootskubtn-pd' onclick='OrderCommon.PairProduct(\"".$orderitemid."\",\"".$sku."\",\"".$rootsku."\",1)'>????????????</button>
						<button type='button' class='iv-btn btn-warn rootskubtn-pd' style='margin-left:5px;' onclick='OrderCommon.PairProduct(\"".$orderitemid."\",\"".$sku."\",\"".$rootsku."\",0)'>????????????</button>
								";
			}
		}
	
		$json=array(
				'result'=>$result,
				'html'=>isset($html)?$html:'',
				'message'=>$msg,
		);
	
		return json_encode($json);
	}
	/**
	 +----------------------------------------------------------
	 * ????????????????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lgw 	2017/03/07				?????????
	 +----------------------------------------------------------
	 **/
	public function actionRefreshOrderDeclarationEdit(){
		if (!empty($_REQUEST['order_id'])){
			$order = OdOrder::findOne($_REQUEST['order_id']);
			if (!empty($order)){	
				return OrderFrontHelper::displayViewOrderDeclarationInfo($order);
			}else{
				return '';
			}
	
		}else{
			return '';
		}
	}//end of function actionRefreshEditOrderItemInfo
	public function actionGetOrderRedisData(){
		$platform = empty($_REQUEST['platform'])?'all':$_REQUEST['platform'];
		$category = empty($_REQUEST['category'])?'':$_REQUEST['category'];
		
		$uid = \Yii::$app->user->id;
		$puid = \Yii::$app->user->identity->getParentUid();
		var_dump($puid);
		var_dump($uid);
		var_dump($platform);
		var_dump($category);
		$rtn = RedisHelper::getOrderCache2($puid, $uid, $platform, $category);
		var_dump($rtn);
		exit();
	}
	
	public function actionDelOrderRedisData(){
		$platform = empty($_REQUEST['platform'])?'all':$_REQUEST['platform'];
		$category = empty($_REQUEST['category'])?'':$_REQUEST['category'];
	
		$uid = \Yii::$app->user->id;
		$puid = \Yii::$app->user->identity->getParentUid();
	
		$rtn = RedisHelper::delOrderCache($puid, $platform, $category);
		var_dump($rtn);
		
		$rtn = RedisHelper::delSubAccountOrderCache($puid, $uid, $platform, $category);
		var_dump($rtn);
		exit();
	}
	
	/**
	 +----------------------------------------------------------
	 * ??????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2017/04/27				?????????
	 +----------------------------------------------------------
	 **/
	public function actionBatchMergeOrder(){
		
		AppTrackerApiHelper::actionLog("Oms-erp", "/order/mergeorder");
		if (\Yii::$app->request->isPost){
			$errorMsg = '';
			if (!empty($_REQUEST['orderIdList'])){
				foreach($_REQUEST['orderIdList'] as $orderIdList){
					$rt = OrderHelper::mergeOrder($orderIdList);
					if ($rt['success'] ==false){
						$idStr = '';
						foreach($orderIdList  as $orderid){
							$idStr .= $orderid.",";
						}
						$errorMsg .= $idStr.$rt['message'];
					}
				}
			}
			
			if (!empty($errorMsg)){
// 				return $this->render('//errorview',['title'=>'????????????','error'=>$errorMsg]);
				echo $errorMsg;
			}else{
				echo "MergeSuccess";
				//echo "<script language='javascript'>alert('Success');window.location.reload();</script>";
			}
			return;
		}
		
		
		
	}//end of function actionBatchMergeOrder
	
	/**
	 * ???30??? ???????????? ???????????????????????????????????????
	 * @param string $auth
	 * @param string $platform
	 */
	public function actionUserOrderCount($auth,$platform){
		if($auth!=='eagle-admin')
			exit('auth denied !');
		
		if(empty($platform))
			exit('platform cant not be empty !');
		
		$sql = "select puid, update_date, ".$platform."_orders as orders, oms_action_logs,addi_info from user_30day_order_statistic where ".$platform."_orders <> 0";
		if(empty($_REQUEST['sort'])){
			$sql .= " order by ".$platform."_orders DESC ";
		}else{
			if( '-' == substr($_REQUEST['sort'],0,1) ){
				$sort = substr($_REQUEST['sort'],1);
				$order = 'desc';
			} else {
				$sort = $_REQUEST['sort'];
				$order = 'asc';
			}
			
			$sql .= " order by $sort $order ";
		}
		$command = \Yii::$app->db_queue->createCommand( $sql );
		$pagination = new Pagination([
			'totalCount' => count( $command->queryAll() ),
			'pageSize'=>isset($_REQUEST['per-page'])?$_REQUEST['per-page']:50,
			'pageSizeLimit'=>[50,500],//????????????????????????
		]);
		
		$sql .= " LIMIT ".$pagination->offset.",".$pagination->limit;
		//echo "<br><br>".$sql."<br><br>";
		$command = \Yii::$app->db_queue->createCommand( $sql );
		$datas = $command->queryAll();
		
		return $this->render('_user_order_count',[
			'datas'=>$datas,
			'pages'=>$pagination,
		]);
	}

	//??????????????????split-order-new
	public function actionSplitOrderNew(){
		$orderid = empty($_POST['orderid'])?'':$_POST['orderid'];
		
// 		$orderid[1]="55673";

		$obOrderRelation=OrderRelation::find()->where(['son_orderid'=>$orderid,'type'=>'merge'])->asArray()->all();
		$obOrderRelation2=OrderRelation::find()->where(['son_orderid'=>$orderid,'type'=>'split'])->asArray()->all();
		$obOrderRelation3=OrderRelation::find()->where(['father_orderid'=>$orderid,'type'=>'split'])->asArray()->all();
		$odOrder=OdOrder::find()->where(['order_id'=>$orderid,'order_capture'=>'Y'])->asArray()->all();
		if(!empty($obOrderRelation) || !empty($obOrderRelation2) || !empty($obOrderRelation3) || !empty($odOrder)){
			$msg="";
			foreach ($obOrderRelation as $obOrderRelationone){
				$msg.='<div class="bootbox-body">??????????????????:'.$obOrderRelationone['father_orderid'].'??????????????????;</div>';
			}
			foreach ($obOrderRelation2 as $obOrderRelation2one){
				$msg.='<div class="bootbox-body">??????????????????:'.$obOrderRelation2one['son_orderid'].'??????????????????;</div>';
			}
			foreach ($obOrderRelation3 as $obOrderRelation3one){
				$msg.='<div class="bootbox-body">??????????????????:'.$obOrderRelation3one['father_orderid'].'??????????????????;</div>';
			}
			foreach ($odOrder as $odOrderone){
				$msg.='<div class="bootbox-body">??????????????????:'.$odOrderone['order_id'].'??????????????????;</div>';
			}
			return $msg;
		}
			
		
		
		$item = OdOrderItem::find()->where(['order_id'=>$orderid,'manual_status'=>'enable'])->asArray()->all();
		
		$delarr=array();
		$splitarr=array();
		$temp=array();
		foreach ($item as $itemone){
			$orderid_len11=preg_replace('/^0+/','',$itemone['order_id']);
			
			if(array_key_exists($orderid_len11,$temp)){
				$delarr[$orderid_len11][0][$itemone['order_item_id']]=array(
								'photo'=>'',
								'sku'=>'',
								'quantity'=>'',
				);
			}
			else{
				$delarr[$orderid_len11]=array(
								'0'=>array(
										$itemone['order_item_id']=>array(
												'photo'=>'',
												'sku'=>'',
												'quantity'=>0,
										),
								),
				);
				
				$temp[$orderid_len11]=$orderid_len11;
			}
			
			$splitarr[$orderid_len11][$itemone['order_item_id']]['qty']=$itemone['quantity'];
		}

		return $this->renderPartial('splitordernew',['orderid'=>$orderid,
													'order_item_list'=>$item,
													'deldata'=>base64_encode(json_encode($delarr)),
													'splitqty'=>base64_encode(json_encode($splitarr)),
				]);
	}
	
	//???????????????????????????
	public function actionSplotOrderChildren(){
		$order_item_id = empty($_POST['order_item_id'])?'':$_POST['order_item_id'];
		$dellist = empty($_POST['dellist'])?'':json_decode(base64_decode($_POST['dellist']),true);     //??????????????????
		$splitqty = empty($_POST['splitqty'])?'':json_decode(base64_decode($_POST['splitqty']),true); //???????????????
		$signt = $_POST['signt'];   //?????????????????????
		$sign = $_POST['sign'];   //?????? 0:+ 1???- 2:??????
		
		$item = OdOrderItem::find()->where(['order_item_id'=>$order_item_id])->asArray()->all();
		$item=$item[0];

		$html="";
		$orderitemid_len11=preg_replace('/^0+/','',$item['order_id']);

		$dellist[$orderitemid_len11][$signt][$order_item_id]['photo']=$item['photo_primary'];
		$dellist[$orderitemid_len11][$signt][$order_item_id]['sku']=$item['sku'];

		if($sign==0){
			$dellist[$orderitemid_len11][$signt][$order_item_id]['quantity']=$dellist[$orderitemid_len11][$signt][$order_item_id]['quantity']+1;
			$splitqty[$orderitemid_len11][$order_item_id]['qty']=$splitqty[$orderitemid_len11][$order_item_id]['qty']-1;
		}
		else if($sign==2){
			$dellist[$orderitemid_len11][$signt][$order_item_id]['photo']='';
			$dellist[$orderitemid_len11][$signt][$order_item_id]['sku']='';
			$splitqty[$orderitemid_len11][$order_item_id]['qty']=$splitqty[$orderitemid_len11][$order_item_id]['qty']+$dellist[$orderitemid_len11][$signt][$order_item_id]['quantity'];
			$dellist[$orderitemid_len11][$signt][$order_item_id]['quantity']=0;
		}
		else{
			$dellist[$orderitemid_len11][$signt][$order_item_id]['quantity']=$dellist[$orderitemid_len11][$signt][$order_item_id]['quantity']-1;
			$splitqty[$orderitemid_len11][$order_item_id]['qty']=$splitqty[$orderitemid_len11][$order_item_id]['qty']+1;
		}
		
		$li_html='';
		foreach ($dellist[$orderitemid_len11][$signt] as $keys=>$dellistone){
			if(!empty($dellistone['sku']) && $dellistone['quantity']>0){
				$li_html.='
							<li class="prd ng-scope pre">
		                        <div class="mui-media">
		                                <img class="mui-media-object" src="'.$dellistone['photo'].'">
		                                <div class="mui-media-body" style="width: 85%;">
		                                    <div class="ng-binding">'.$dellistone['sku'].'</div>
		                                    <div class="input-group input-group-sm" style="margin-top:7px;">
                                        		<span class="input-group-btn"><button style="padding: 0px;" type="button" id="splitleft" class="btn btn-default input-group-btn2" onclick="OrderCommon.splitOrderChildren(this,\''.$orderitemid_len11.'\',\''.$keys.'\',1,'.$signt.')"><i class="glyphicon glyphicon-minus"></i></button></span>
                                        		<h4 class="ng-binding" id="quantity" style="font-size: 17px;text-align: center;margin-top: 6px;">'.$dellistone['quantity'].'</h4>
                                        		<span class="input-group-btn"><button style="padding: 0px;" type="button" id="splitright" class="btn btn-default input-group-btn2" onclick="OrderCommon.splitOrderChildren(this,\''.$orderitemid_len11.'\',\''.$keys.'\',0,'.$signt.')"><i class="glyphicon glyphicon-plus"></i></button></span>
                                    		</div>
		                                </div>
                                        <a href="javascript:void(0)" class="del display" data-order="'.$orderitemid_len11.'" data-item="'.$keys.'" data-index="'.$signt.'"><i class="glyphicon glyphicon-remove red"></i>??????</a>
		                           </div>
		                    </li>
					';
			}
		}
		
		$html.='<div class="panel-heading ng-binding">
	                    	'.$orderitemid_len11.'-'.($signt+1).'
	                    	<div class="pull-right"><button type="button" class="btn btn-xs btn-danger" onclick="OrderCommon.splitPackageDel(this,\''.$orderitemid_len11.'\')">??????</button></div>
	                	</div>
	                	<div class="panel-body">';
		
		if(!empty($li_html)){
			$html.='<ul class="list-unstyled">'.$li_html.'</ul>';
		}
		else{
			$html.='</div>';
		}
		
		$oldqty=0;
		foreach ($splitqty as $splitqtyone){
			foreach ($splitqtyone as $splitqtyoneone){
				$oldqty+=$splitqtyoneone['qty'];
			}
		}
		if(empty($oldqty))
			return json_encode(['code'=>'false','message'=>'?????????????????????????????????']);

		return json_encode(['code'=>'true','dellist'=>base64_encode(json_encode($dellist)),'splitqty'=>base64_encode(json_encode($splitqty)),'html'=>$html,'signr'=>$signt,'splitqtylist'=>json_encode($splitqty)]);
		
	}
	
	//????????????
	public function actionSplitPackage(){
		$orderid = empty($_POST['orderid'])?'':$_POST['orderid'];
		$sum = $_POST['sum'];
		$dellist = empty($_POST['dellist'])?'':json_decode(base64_decode($_POST['dellist']),true);     //??????????????????

		if(empty($sum)){
			$item = OdOrderItem::find()->where(['order_id'=>$orderid])->asArray()->all();
			$temp=array();
			foreach ($item as $itemone){
				$orderitemid_len11=preg_replace('/^0+/','',$itemone['order_id']);
// 				while(strlen($orderitemid_len11)<11){
// 					$orderitemid_len11='0'.$orderitemid_len11;
// 				}
				
				if(array_key_exists($orderitemid_len11,$temp)){
					$dellist[$orderitemid_len11][0][$itemone['order_item_id']]=array(
							'photo'=>'',
							'sku'=>'',
							'quantity'=>'',
					);
				}
				else{
					$dellist[$orderitemid_len11]=array(
							'0'=>array(
									$itemone['order_item_id']=>array(
											'photo'=>'',
											'sku'=>'',
											'quantity'=>0,
									),
							),
					);
			
					$temp[$orderitemid_len11]=$orderitemid_len11;
				}
			}
			
		}
		else{
			$dellist[$orderid][$sum]=$dellist[$orderid][$sum-1];
			foreach ($dellist[$orderid][$sum] as $keys=>$dellistone){
				foreach($dellistone as $title=>$dellistoneone)
					$dellist[$orderid][$sum][$keys][$title]='';
			}
		}
		
		$html='
				<div class="panel panel-default ng-scope" id="pk'.$orderid.'-'.$sum.'" data-number="'.$orderid.'">
                <div class="panel-heading ng-binding">
                    '.$orderid.'-'.($sum+1).'
                    <div class="pull-right"><button type="button" class="btn btn-xs btn-danger" onclick="OrderCommon.splitPackageDel(this,\''.$orderid.'\')">??????</button></div>
                </div>
                <div class="panel-body">
                </div>
    			</div>
		';
		
		return json_encode(['dellist'=>base64_encode(json_encode($dellist)),'html'=>$html]);
		
	}
	
	//????????????
	public function actionSplitPackageDel(){
		$divid = empty($_POST['divid'])?'':$_POST['divid'];
		$dellist = empty($_POST['dellist'])?'':json_decode(base64_decode($_POST['dellist']),true);     //??????????????????
		$splitqty = empty($_POST['splitqty'])?'':json_decode(base64_decode($_POST['splitqty']),true);
		
		$divid_arr=explode('-',$divid);
		$delindex=$divid_arr[1];
		$orderid=substr($divid_arr[0], 2);
		
		$dellist_tmp=$dellist;
		
		foreach ($dellist_tmp[$orderid][$delindex] as $keys=>$dellist_tmpone){
			$splitqty[$orderid][$keys]['qty']=$splitqty[$orderid][$keys]['qty']+$dellist_tmpone['quantity'];
		}
		unset($dellist_tmp[$orderid][$delindex]);
		
		unset($dellist[$orderid]);

		//????????????????????????
		$newsign=0;
		foreach ($dellist_tmp[$orderid] as $keys=>$dellist_tmpone){
			$dellist[$orderid][$newsign]=$dellist_tmpone;
			$newsign++;
		}
		
		return json_encode(['dellist'=>base64_encode(json_encode($dellist)),'splitqty'=>base64_encode(json_encode($splitqty)),'splitqtylist'=>json_encode($splitqty)]);
	}
	
	//????????????
	public function actionSplitOrderReorder(){
		if (!empty($_REQUEST['orderIdList'])){

			if (is_array($_REQUEST['orderIdList'])){
				$splotOrderDelList=empty($_POST['splotOrderDelList'])?array():json_decode(base64_decode($_POST['splotOrderDelList']),true);
				$splotOrderqtyList=empty($_POST['splotOrderqtyList'])?array():json_decode(base64_decode($_POST['splotOrderqtyList']),true);
				
				$qtysum=0;
				foreach ($splotOrderDelList as $splotOrderDelListone){
					foreach ($splotOrderDelListone as $splotOrderDelListoneone){
						foreach ($splotOrderDelListoneone as $splotOrderDelListoneoneone){
							$qtysum+=$splotOrderDelListoneoneone['quantity'];
						}
					}
				}
				if(empty($qtysum))
					exit(json_encode(['success'=>false,'message'=>'?????????????????????']));
				
				$rt = OrderHelper::splitOrderReorder($_REQUEST['orderIdList'],'order','????????????',$splotOrderDelList,$splotOrderqtyList);
				exit(json_encode(['success'=>(empty($rt['failure'])?$rt['success']:0),'message'=>$rt['message']]));
	
			}else{
	
				exit(json_encode(['success'=>false,'message'=>'E001 ??????????????? ??????????????????']));
			}
		}else{
			exit(json_encode(['success'=>false,'message'=>'?????????????????????']));
		}
	}

	//????????????????????????
	public function actionSplitOrderCancel(){
		$orderid = empty($_POST['orderid'])?'':$_POST['orderid'];

		/*2017-07-08???bug
		$orderList=array();
		$orderChildrenList=array();
		
		foreach ($orderid as $orderidone){
			$OrderRelation=OrderRelation::find()->select(['father_orderid'])->where(['father_orderid'=>$orderidone,'type'=>'split'])->orWhere(['son_orderid'=>$orderidone,'type'=>'split'])->asArray()->one();	
			if(!empty($OrderRelation)){
				$obOrderRelation=OrderRelation::find()->where(['father_orderid'=>$OrderRelation['father_orderid'],'type'=>'split'])->asArray()->all();

				if(!empty($obOrderRelation) && !array_key_exists($obOrderRelation[0]['father_orderid'], $orderList)){				
					$order=OdOrder::find()->select(['order_status'])->where(['order_id'=>$obOrderRelation[0]['father_orderid']])->asArray()->all();
					$orderitem=OdOrderItem::find()->select(['order_item_id','sku','photo_primary','ordered_quantity','quantity'])->where(['order_id'=>$obOrderRelation[0]['father_orderid'],'manual_status'=>'enable'])->asArray()->all();
					$orderList[$obOrderRelation[0]['father_orderid']]['order_status']=OdOrder::$status[$order[0]['order_status']];
					foreach ($orderitem as $orderitemone){
						$orderList[$obOrderRelation[0]['father_orderid']]['items'][]=array(
								'sku'=>$orderitemone['sku'],
								'photo_primary'=>$orderitemone['photo_primary'],
								'quantity'=>$orderitemone['quantity'],
						);
					}
					$orderChildrenList[$obOrderRelation[0]['father_orderid']]=array();
					foreach ($obOrderRelation as $obOrderRelationone){
						$order=OdOrder::find()->select(['order_status'])->where(['order_id'=>$obOrderRelationone['son_orderid']])->asArray()->all();
						$orderitem=OdOrderItem::find()->select(['order_item_id','sku','photo_primary','ordered_quantity','quantity'])->where(['order_id'=>$obOrderRelationone['son_orderid']])->asArray()->all();
						$orderChildrenList[$obOrderRelation[0]['father_orderid']][$obOrderRelationone['son_orderid']]['order_status']=OdOrder::$status[$order[0]['order_status']];
						foreach ($orderitem as $orderitemone){
							$orderChildrenList[$obOrderRelation[0]['father_orderid']][$obOrderRelationone['son_orderid']]['items'][]=array(
									'sku'=>$orderitemone['sku'],
									'photo_primary'=>$orderitemone['photo_primary'],
									'quantity'=>$orderitemone['quantity'],
							);
						}
					}
				}
				
			}
			
			
		}*/
		
		//////////////////////////////////////////////////2017-07-08
		$OrderRelation=OrderRelation::find()->select(['father_orderid'])->where(['father_orderid'=>$orderid,'type'=>'split'])->orWhere(['son_orderid'=>$orderid,'type'=>'split'])->asArray()->one();
		
		$orderList=array();
		$orderChildrenList=array();
		if(!empty($OrderRelation)){
			$obOrderRelation=OrderRelation::find()->where(['father_orderid'=>$OrderRelation['father_orderid'],'type'=>'split'])->asArray()->all();
		
			if(!empty($obOrderRelation)){
		
				$order=OdOrder::find()->select(['order_status'])->where(['order_id'=>$obOrderRelation[0]['father_orderid']])->asArray()->all();
				$orderitem=OdOrderItem::find()->select(['order_item_id','sku','photo_primary','ordered_quantity','quantity'])->where(['order_id'=>$obOrderRelation[0]['father_orderid'],'manual_status'=>'enable'])->asArray()->all();
				$orderList[$obOrderRelation[0]['father_orderid']]['order_status']=OdOrder::$status[$order[0]['order_status']];
				foreach ($orderitem as $orderitemone){
					$orderList[$obOrderRelation[0]['father_orderid']]['items'][]=array(
							'sku'=>$orderitemone['sku'],
							'photo_primary'=>$orderitemone['photo_primary'],
							'quantity'=>$orderitemone['quantity'],
					);
				}
		
				foreach ($obOrderRelation as $obOrderRelationone){
					$order=OdOrder::find()->select(['order_status'])->where(['order_id'=>$obOrderRelationone['son_orderid']])->asArray()->all();
					$orderitem=OdOrderItem::find()->select(['order_item_id','sku','photo_primary','ordered_quantity','quantity'])->where(['order_id'=>$obOrderRelationone['son_orderid']])->asArray()->all();
					$orderChildrenList[$obOrderRelationone['son_orderid']]['order_status']=OdOrder::$status[$order[0]['order_status']];
					foreach ($orderitem as $orderitemone){
						$orderChildrenList[$obOrderRelationone['son_orderid']]['items'][]=array(
								'sku'=>$orderitemone['sku'],
								'photo_primary'=>$orderitemone['photo_primary'],
								'quantity'=>$orderitemone['quantity'],
						);
					}
				}
			}
				
		}
		
		///////////////////////////////////////////////////
		
		
		

		return $this->renderPartial('splitordercancel',['orderList'=>$orderList,
				'orderChildrenList'=>$orderChildrenList,
				]);
		
	}
	
	//??????????????????
	public function actionSplitorderCancels(){
		try{
			$orderid = empty($_POST['orderIdList'])?'':$_POST['orderIdList'];
			
			$rt = OrderHelper::splitOrderReorderCancel($orderid);

			return json_encode($rt);
		}
		catch (\Exception $err){
			return json_encode(['code'=>1,'message'=>$err->getMessage()]);
		}
	}
	
	
	/**
	 +----------------------------------------------------------
	 * ????????????????????????????????????????????????????????????user1?????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2017/04/27				?????????
	 +----------------------------------------------------------
	 **/
	public function actionSetDemoData(){
		 
		if (!empty($_REQUEST['orderid'])){
			$updateSql = "update  od_order_v2  set order_capture = 'N' where order_id  = '".$_REQUEST['orderid']."'";
			$updateRT = \Yii::$app->subdb->createCommand($updateSql)->execute();
			if ($updateRT){
				echo "done!";
			}else{
				echo "orderid not exist or had done ???";
			}
			
		}else{
			echo "not any order id ???";
		}
	}

	/**
	 +----------------------------------------------------------
	 * ???????????????????????????????????????html
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lgw 	2017/06/12				?????????
	 +----------------------------------------------------------
	 **/
	public function actionReApplyTrackNum(){
		try{
			$shipping_method_code=empty($_POST['shipping_method_code'])?'0':$_POST['shipping_method_code'];
			$change_warehouse=empty($_POST['change_warehouse'])?'0':$_POST['change_warehouse'];
			$orderid=empty($_POST['orderid'])?'':$_POST['orderid'];
			$html='';

			$rt=CarrierOpenHelper::getCarrierOrderTrackingNoHtml($orderid,$change_warehouse,$shipping_method_code);
			if($rt['error']==1){
				$html='<div>'.$rt['msg'].'</div>';
			}
			else{
				$html = isset($rt['data']['html']) ? $rt['data']['html'] : '';
				
// 				if(!empty($rt['data'])){
// 					$html=$rt['data']['html'];
// 				}
				
// 				if(empty($html)){
// // 					$html='<div>????????????????????????</div>';
// // 					$rt['error']=1;
// 				}
			}
		}
		catch (\Exception $err){
			$html='<div>????????????????????????,'.$err->getMessage().'</div>';
			$rt['error']=1;
		}

		return $this->renderPartial('applyTrackNum',['html'=>$html,'error'=>$rt['error'],'msg'=>$rt['msg'],
				'order_id'=>$orderid, 'tracking_number'=>(isset($rt['tracking_number']) ? $rt['tracking_number'] : '')]);
	}
	/**
	 +----------------------------------------------------------
	 * ??????????????????????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lgw 	2017/06/12				?????????
	 +----------------------------------------------------------
	 **/
	public function actionChangeshippingmethodcode(){
		$selectval=$_POST['selectval'];
		$platForm=$_POST['platForm'];
		$html='';
		
		$rt=\eagle\modules\carrier\helpers\CarrierOpenHelper::getShippingCodeByPlatform($platForm);
		if($rt['web_url_tyep']===1){
			$html='<label class="col-md-2 control-label p0" style="line-height: 40px;width:13%;">???????????????</label>
								<input type="text" class="form-control" id="change_web2" style="width: 300px;" value="http://www.17track.net">';
		}
		else if($rt['web_url_tyep']===2){
			$shippingServicesArr=empty($selectval)?reset($rt['shippingServices']):$rt['shippingServices'][$selectval];
			if($shippingServicesArr['is_web_url']==1){
				$html='<label class="col-md-2 control-label p0" style="line-height: 40px;width:13%;">???????????????</label>
									<input type="text" class="form-control" id="change_web2" style="width: 300px;"  value="http://www.17track.net">';
			}
		}
		
		return $html;
		
	}
	
	/**
	 * ????????????????????????????????????????????????,?????????????????????????????????????????????????????????
	 * @author fanjs
	 */
	public function actionSignshipped(){
		AppTrackerApiHelper::actionLog("Oms-erp", "/order/signshipped");
		if (\Yii::$app->request->getIsPost()){
			//??????????????????js???????????????
			if(empty($_REQUEST['js_submit'])){
				$tmpOrders = \Yii::$app->request->post()['order_id'];
			}else{
				$tmpOrders = json_decode($_REQUEST['order_id'], true);
			}
		}else {
			$tmpOrders = [\Yii::$app->request->get('order_id')];
		}
		
		if(empty($tmpOrders)){
			return $this->renderPartial('signshipped_new', ['error_arr'=>['title'=>'????????????','error'=>'?????????????????????']]);
// 			return $this->render('//errorview',['title'=>'????????????','error'=>'?????????????????????']);
		}
		$orders = OdOrder::find()->where(['in','order_id',$tmpOrders])->andwhere(['order_capture'=>'N'])->all();
		if (empty($orders)){
			return $this->renderPartial('signshipped_new', ['error_arr'=>['title'=>'????????????','error'=>'?????????????????????']]);
// 			return $this->render('//errorview',['title'=>'????????????','error'=>'?????????????????????']);
		}
			
		$allPlatform = []; // ????????????
		foreach ($orders as $key=>$order){
			if (!in_array($order->order_source ,$allPlatform )){
				$allPlatform[] = $order->order_source;
			}
		
			if('sm' == $order->order_relation){// ??????????????????????????????????????????????????????
				$father_orderids = OrderRelation::findAll(['son_orderid'=>$order->order_id , 'type'=>'merge' ]);
				foreach ($father_orderids as $father_orderid){
					$tmpOrders[] = $father_orderid->father_orderid;
					$orders[] = OdOrder::findOne($father_orderid->father_orderid);
				}
		
				unset($orders[$key]);
			}
		}
			
		$allShipcodeMapping = [];
		foreach ($allPlatform as $_platform){
			$tmp_c_ShippingMethod = \eagle\modules\carrier\helpers\CarrierOpenHelper::getShippingCodeByPlatform($_platform);
			
			if($tmp_c_ShippingMethod['type'] == 'text'){
				$allShipcodeMapping[$_platform] = $tmp_c_ShippingMethod;
			}else{
				$tmpShippingMethod = DataStaticHelper::getUseCountTopValuesFor("erpOms_ShippingMethod", $tmp_c_ShippingMethod['shippingServices']);
				$shippingMethods = [];
				
				if(!empty($tmpShippingMethod['recommended'])){
					$shippingMethods += $tmpShippingMethod['recommended'];
					$shippingMethods[''] = '---??????/????????? ?????????---';
				}
				
				if(!empty($tmpShippingMethod['rest']))
					$shippingMethods += $tmpShippingMethod['rest'];
				
				$allShipcodeMapping[$_platform]['shippingServices'] = $shippingMethods;
				unset($tmp_c_ShippingMethod['shippingServices']);
				$allShipcodeMapping[$_platform] += $tmp_c_ShippingMethod;
			}
		}
		
		$logs = OdOrderShipped::findAll(['order_id'=>$tmpOrders]);
		
		return $this->renderPartial('signshipped_new', ['orders'=>$orders,'logs'=>$logs,'allShipcodeMapping'=>$allShipcodeMapping]);
	}
	
	/**
	 +----------------------------------------------------------
	 * ???????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw 	2017/08/15				?????????
	 +----------------------------------------------------------
	 **/
	public function actionRepulsePaid(){
		if (\Yii::$app->request->isPost){
			$orderids = $_POST['orders'];
			$module = isset($_POST['m'])?$_POST['m']:'order';
			$action = isset($_POST['a'])?$_POST['a']:TranslateHelper::t('???????????????');
			Helper_Array::removeEmpty($orderids);
			if (count($orderids)>0){
				try {
					$r = OrderApiHelper::repulsePaidOrders($orderids,$module,$action);
	
					if (!$r['success']) return $r['message'];
					return '???????????????';
				}catch (\Exception $e){
					return $e->getMessage();
				}
			}else{
				return '????????????????????????';
			}
		}//end of post
	}
	
	//????????????
	public function actionGetPlatformSelected(){
		$type = empty($_GET['type']) ? '' : $_GET['type'];
		$platform = empty($_GET['platform']) ? '' : $_GET['platform'];
		
		$selleruseridMap = PlatformAccountApi::getAllAuthorizePlatformOrderSelleruseridLabelMap(false, false, true, true);//???????????????????????????
		
		//lazada??????????????????
		if(!empty($selleruseridMap['lazada'])){
			$tmp_lazadaMap = $selleruseridMap['lazada'];
			unset($selleruseridMap['lazada']);
			
			$selleruseridMap['lazada'] = LazadaOrderHelper::getAccountStoreNameMapByEmail($tmp_lazadaMap);
		}
		
		if(!empty($selleruseridMap['wish'])){
			$tmp_wishMap = $selleruseridMap['wish'];
			unset($selleruseridMap['wish']);
				
			$selleruseridMap['wish'] = \eagle\modules\platform\apihelpers\WishAccountsApiHelper::getWishAliasAccount($tmp_wishMap);
		}
		
		return $this->renderPartial('getPlatformSelected', ['type'=>$type, 'platform'=>$platform, 'selleruseridMap'=>$selleruseridMap]);
	}
	
	/**
	 * ???????????????????????????
	 */
	public function actionPlatformCommonCombinationList(){
		return $this->renderPartial('platformCommonCombinationList',[]);
	}
	
	//????????????????????????
	public function actionSetPlatformCommonCombination(){
		$result = array('error'=>false, 'msg'=>'');
		if(empty($_POST)){
			$result['error'] = true;
			$result['msg'] = '??????????????????????????????';
			exit(json_encode($result));
		}
		
		$result = OrderListV3Helper::setPlatformCommonCombination($_POST);
		
		exit(json_encode($result));
	}
	
	//????????????????????????
	public function actionRemovePlatformCommonCombination(){
		$result = array('error'=>false, 'msg'=>'');
		if(empty($_POST)){
			$result['error'] = true;
			$result['msg'] = '??????????????????????????????';
			exit(json_encode($result));
		}
	
		$result = OrderListV3Helper::removePlatformCommonCombination($_POST);
	
		exit(json_encode($result));
	}
	
	/**
	 +----------------------------------------------------------
	 * ????????????????????????????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lrq 	2017/09/25				?????????
	 +----------------------------------------------------------
	 **/
	public function actionRecovery(){
		if (\Yii::$app->request->isPost){
			$orderids = $_POST['orders'];
			$module = isset($_POST['m'])?$_POST['m']:'order';
			$action = isset($_POST['a'])?$_POST['a']:TranslateHelper::t('????????????');
			Helper_Array::removeEmpty($orderids);
			if (count($orderids)>0){
				try {
					$r = OrderApiHelper::recoveryOrders($orderids,$module,$action);
	
					if (!$r['success']) return $r['message'];
					return '???????????????';
				}catch (\Exception $e){
					return $e->getMessage();
				}
			}else{
				return '????????????????????????';
			}
		}//end of post
	}
	
	//???????????????????????????????????????
	public function actionAllPlatformOrderList(){
		$puid = \Yii::$app->subdb->getCurrentPuid();
		AppTrackerApiHelper::actionLog("eagle_v2", "/order/allPlatformOrderList");
		
		
		
		
	}
	
	public function actionDelItemImgCacher(){
		$rtn = ['success'=>true,'message'=>''];
		$order_ids = explode(',', $_REQUEST['order_ids']);
		try{
			$items = OdOrderItem::find()->where(['order_id' => $order_ids])->asArray()->all();
			foreach ($items as $item){
				if(!empty($item['photo_primary'])){
					$orig_url = trim($item['photo_primary']);
					ImageCacherHelper::delImageRedisCacheUrl($orig_url);
				}
			}
		} catch(\Exception $ex){
			$rtn = ['success'=>false,'message'=>'??????????????????????????????'];
			SysLogHelper::SysLog_Create('Order',__CLASS__, __FUNCTION__,'error',print_r($ex->getMessage()));
			$rtn = ['success'=>false,'message'=>'?????????????????????????????????????????????'];
		}
		exit(json_encode($rtn));
	}



	/**
	+----------------------------------------------------------
	 * ???????????????????????? wish ???
	 * akirametero
	+----------------------------------------------------------
	+----------------------------------------------------------
	 **/
	public  function actionShowProductUrlAddBox(){
		if (!empty($_REQUEST['orderIdList'])){

			if (is_array($_REQUEST['orderIdList'])){
				$orderList= OdOrderItem::find()->where(['order_id'=>$_REQUEST['orderIdList']])->asArray()->all();
				
				//$orderList  = OdOrder::find()->where(['order_id'=>$_REQUEST['orderIdList']])->asArray()->all();
				return $this->renderPartial('_addProductUrlBox.php' , ['orderList'=>$orderList] );
			}else{
				return $this->renderPartial('//errorview','E001 ??????????????? ??????????????????');
			}
		}else{
			return $this->renderPartial('//errorview','?????????????????????');
		}
	}//end of actionShowAddMemoBox

	/**
	+----------------------------------------------------------
	 * ??????????????????
	+----------------------------------------------------------
	 * @access public
	 * akirametero
	+----------------------------------------------------------
	+----------------------------------------------------------
	 **/
	public function actionBatchSaveOrderProductUrl(){
		if (!empty($_REQUEST['orderList'])){
			$orderIdList = [];
			$MemoList = [];
			$err_msg = "";
			foreach ($_REQUEST['orderList'] as $row){
				$orderIdList[] = $row['order_id'];
				$MemoList[(int)$row['order_id']]  = $row['memo']; // linux ??????00????????????
			}

			$OrderList = OdOrderItem::find()->where(['order_id'=>$orderIdList])->all();
			foreach($OrderList as $OrderModel ){

				$update= OdOrderItem::findOne($OrderModel['order_item_id']);
				$update->product_url= $MemoList[(int)$OrderModel->order_id];
				$update->update(false);

			}
			if (!empty($OrderList)){
				$result = ['success'=>empty($err_msg) , 'message'=>$err_msg];
			}else{
				$result = ['success'=>false , 'message'=>'E002??????????????? ????????????'];
			}

		}else{
			$result = ['success'=>false , 'message'=>'E001??????????????? ??????????????????'];
		}
		exit(json_encode($result));
	}//end of actionBatchSaveOrderDesc
}

?>