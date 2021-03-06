<?php

namespace eagle\modules\order\controllers;

use yii\data\Pagination;
use yii\web\Controller;
use eagle\modules\order\models\OdOrder;
use eagle\modules\order\models\Excelmodel;

use common\helpers\Helper_Array;
use eagle\modules\order\models\OdOrderItem;
use eagle\modules\util\helpers\OperationLogHelper;
use eagle\modules\order\models\Usertab;
use Exception;
use eagle\modules\order\models\OdOrderShipped;
use eagle\models\carrier\SysShippingService;
// use eagle\models\SaasAmazonUser;
use yii\data\Sort;
use eagle\models\SaasRumallUser;
use eagle\modules\inventory\models\Warehouse;
use eagle\modules\order\helpers\OrderTagHelper;
use eagle\modules\util\helpers\ConfigHelper;
use eagle\modules\util\helpers\StandardConst;
use eagle\modules\order\helpers\RumallOrderInterface;
use eagle\modules\order\helpers\OrderHelper;
use eagle\models\SysCountry;
use eagle\modules\app\apihelpers\AppTrackerApiHelper;
use eagle\modules\platform\helpers\RumallAccountsV2Helper;
use eagle\modules\order\helpers\RumallOrderHelper;
use eagle\modules\util\helpers\TimeUtil;
use eagle\modules\platform\apihelpers\RumallAccountsApiHelper;
use eagle\modules\inventory\helpers\InventoryApiHelper;
use eagle\modules\order\helpers\OrderBackgroundHelper;
use eagle\modules\inventory\helpers\WarehouseHelper;
use eagle\modules\app\apihelpers\AppApiHelper;
use eagle\modules\carrier\helpers\CarrierOpenHelper;
use eagle\modules\order\apihelpers\OrderApiHelper;
use eagle\modules\order\helpers\OrderGetDataHelper;
use eagle\modules\order\helpers\OrderUpdateHelper;
use eagle\modules\platform\apihelpers\PlatformAccountApi;
use eagle\modules\util\helpers\RedisHelper;

class RumallOrderController extends Controller{
	public $enableCsrfValidation = false;
	
	public function behaviors() {
        return [
         	'access' => [
                'class' => \yii\filters\AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => \yii\filters\VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }
	/**
	 * rumall??????????????????
	 * 
	 * 
	 */
	public function actionList(){
		//check????????????
		$permission = \eagle\modules\permission\apihelpers\UserApiHelper::checkPlatformPermission('rumall');
		if(!$permission)
			return $this->render('//errorview_no_close',['title'=>'????????????:????????????','error'=>'?????????????????????????????????!']);
 
		AppTrackerApiHelper::actionLog("Oms-rumall", "/order/rumall/list");
		
		//??????????????????????????????????????????????????????page size ???	//lzhl	2016-11-30
		$page_url = '/'.\Yii::$app->controller->module->id.'/'.\Yii::$app->controller->id.'/'.\Yii::$app->controller->action->id;
		$last_page_size = ConfigHelper::getPageLastOpenedSize($page_url);
		if(empty($last_page_size))
			$last_page_size = 20;//???????????????
		if(empty($_REQUEST['per-page']) && empty($_REQUEST['page']))
			$pageSize = $last_page_size;
		else{
			$pageSize = empty($_REQUEST['per-page'])?50:$_REQUEST['per-page'];
		}
		ConfigHelper::setPageLastOpenedSize($page_url, $pageSize);
		
		$data = OdOrder::find()->where(['order_source' => 'rumall' ]);
		
		
		$showsearch=0;
		$op_code = '';
		
		
		$tmpSellerIDList = PlatformAccountApi::getPlatformAuthorizeAccounts('rumall');//??????????????????	lzhl 2017-03
		$accountList = [];
		$rumallUsersDropdownList = [];
		foreach($tmpSellerIDList as $sellerloginid=>$store_name){
			$accountList[] = $sellerloginid;
			$rumallUsersDropdownList[$sellerloginid] = $store_name;
		}
		if(empty($accountList)){
			//??????????????????????????????????????????????????????
			$test_userid=\eagle\modules\tool\helpers\MirroringHelper::$test_userid;
			if (!in_array(\Yii::$app->user->identity->getParentUid(),$test_userid['yifeng'])){
				//????????????????????????
				return $this->render('//errorview_no_close',['title'=>'????????????:????????????','error'=>'???????????????????????? ?????????  ??????????????????!']);
			}
		}

		$addi_condition = ['order_source'=>'rumall'];
		$addi_condition['sys_uid'] = \Yii::$app->user->id;
		$addi_condition['selleruserid_tmp'] = $rumallUsersDropdownList;
		
		$tmp_REQUEST_text['where']=empty($data->where)?Array():$data->where;
		$tmp_REQUEST_text['orderBy']=empty($data->orderBy)?Array():$data->orderBy;
		$omsRT = OrderApiHelper::getOrderListByConditionOMS($_REQUEST,$addi_condition,$data,$pageSize,false,'all');
		if (!empty($_REQUEST['order_status'])){
		    //???????????????????????????code
		    $op_code = $_REQUEST['order_status'];
		}
		if (!empty($omsRT['showsearch'])) $showsearch = 1;
			
        $pagination = new Pagination([
				'defaultPageSize' => 20,
				'pageSize' => $pageSize,
				'totalCount' => $data->count(),
				'pageSizeLimit'=>[5,200],//????????????????????????
				'params'=>$_REQUEST,
				]);
        $models = $data->offset($pagination->offset)
	        ->limit($pagination->limit)
	        ->all();
	    
	    // ??????sql
	    /*
	     $tmpCommand = $data->createCommand();
	    echo "<br>".$tmpCommand->getRawSql();
	    
	    */
	    
	    $excelmodel	=	new Excelmodel();
	    $model_sys	=	$excelmodel->find()->all();
	    
	    $excelmodels=array(''=>'????????????');
	    if(isset($model_sys)&&!empty($model_sys)){
	    	foreach ($model_sys as $m){
	    		$excelmodels[$m->id]=$m->name;
	    	}
	    }
	    
	    // ??????user ???puid ????????? rumall ????????????
	    $puid = \Yii::$app->user->identity->getParentUid();
	    /*
	    $rumallUsers = SaasRumallUser::find()->where(['uid'=>$puid])->asArray()->all();
	    $rumallUsersDropdownList = array();
	    foreach ($rumallUsers as $rumallUser){
	    	$rumallUsersDropdownList[$rumallUser['company_code']] = $rumallUser['store_name'];
	    }
	    */
	    $usertabs = Helper_Array::toHashmap(Helper_Array::toArray(Usertab::find()->all()),'id','tabname');
	    $warehouseids = InventoryApiHelper::getWarehouseIdNameMap();
	    
	    //??????????????????
	    $counter = [];
	    $hitCache = "NoHit";
	    $cachedArr = array();
	    $uid = \Yii::$app->user->id;
	    $stroe = 'all';
	    if(!empty($_REQUEST['selleruserid']))
	    	$stroe  = trim($_REQUEST['selleruserid']);
	    
	    $isParent = \eagle\modules\permission\apihelpers\UserApiHelper::isMainAccount();
	    if($isParent){
	    	$gotCache = RedisHelper::getOrderCache2($puid,$uid,'rumall',"MenuStatisticData",$stroe) ;
	    }else{
	    	if (!empty($_REQUEST['selleruserid'])){
	    		$gotCache = RedisHelper::getOrderCache2($puid,$uid,'rumall',"MenuStatisticData",$_REQUEST['selleruserid']) ;
	    	}else{
	    		$gotCache = RedisHelper::getOrderCache2($puid,$uid,'rumall',"MenuStatisticData",'all') ;
	    	}
	    }
	    if (!empty($gotCache)){
	    	$cachedArr = is_string($gotCache)?json_decode($gotCache,true):$gotCache;
	    	$counter = $cachedArr;
	    	$hitCache= "Hit";
	    }
	    	
	    //redis???????????????????????????????????????????????????redis
	    if ($hitCache <>"Hit"){
	    	if (!empty($_REQUEST['selleruserid'])){
	    		$counter = OrderHelper::getMenuStatisticData('rumall',['selleruserid'=>$_REQUEST['selleruserid']]);
	    	}else{
	    		if(!empty($accountList)){
	    			$counter = OrderHelper::getMenuStatisticData('rumall',['selleruserid'=>$accountList]);
	    		}else{
	    			//?????????????????????
	    			$counter=[];
	    			$claimOrderIDs=[];
	    		}
	    	}
	    	//save the redis cache for next time use
	    	if (!empty($_REQUEST['selleruserid'])){
	    		RedisHelper::setOrderCache2($puid,$uid,'rumall',"MenuStatisticData",$_REQUEST['selleruserid'],$counter) ;
	    	}else{
	    		RedisHelper::setOrderCache2($puid,$uid,'rumall',"MenuStatisticData",'all',$counter) ;
	    	}
	    }
	    /*
	    //??????????????????
	    if (!empty($rumallAccountList)){
	        //????????? ????????????????????????
	        $counter = OrderHelper::getMenuStatisticData('rumall',['selleruserid'=>$rumallAccountList]);
	    }else{
	        $counter = OrderHelper::getMenuStatisticData('rumall');
	    }
// 	    $countrycode=OdOrder::getDb()->createCommand('select consignee_country_code from '.OdOrder::tableName().' group by consignee_country_code')->queryColumn();
// 	    $countrycode=array_filter($countrycode);
		*/
	    $search = array('is_comment_status'=>'???????????????');
	    
	    //tag ????????????
	    $allTagDataList = OrderTagHelper::getTagByTagID();
	    $allTagList = [];
	    foreach($allTagDataList as $tmpTag){
	        $allTagList[$tmpTag['tag_id']] = $tmpTag['tag_name'];
	    }
	    
	    //????????????
// 	    if (!empty($_REQUEST['order_status']))
// 	        $order_nav_key_word = $_REQUEST['order_status'];
// 	    else
// 	        $order_nav_key_word='';
	    
	    //??????
	    $query = SysCountry::find();
	    $regions = $query->orderBy('region')->groupBy('region')->select('region')->asArray()->all();
	    $countrys =[];
	    foreach ($regions as $region){
	        $arr['name']= $region['region'];
	        $arr['value']=Helper_Array::toHashmap(SysCountry::find()->where(['region'=>$region['region']])->orderBy('country_en')->select(['country_code', "CONCAT( country_zh ,'(', country_en ,')' ) as country_name "])->asArray()->all(),'country_code','country_name');
	        $countrys[]= $arr;
	    }
	    
	    $country_list=[];
	    
	    //??????dash board cache ??????
	    $uid = \Yii::$app->user->identity->getParentUid();
	    $DashBoardCache = RumallOrderHelper::getOmsDashBoardCache($uid);
	    
	    //?????????????????????????????? start
	    $OrderIdList = [];
	    $existProductResult = OrderBackgroundHelper::getExistProductRuslt($models);
	    //?????????????????????????????? end
	    
	    //??????????????????
	    $countryArr = array();
// 	    $tmpCountryArr = \Yii::$app->get('db')->createCommand("select a.amazon_site_code,a.country_label,a.country_code from amazon_site a")->queryAll();
	     
// 	    foreach ($tmpCountryArr as $tmpCountry){
// 	    	$countryArr[$tmpCountry['country_code']] = $tmpCountry['country_label']."(".$tmpCountry['country_code'].")";
// 	    }
	    
	    $nations_array = json_decode(ConfigHelper::getConfig("RumallOMS/nations"),true);
	    
	    if(empty($nations_array)){
	        $new_nations_array = array();
	        if(count($models)){//?????????????????????????????????
	            foreach ($models as $detail_order){
	                if(!empty($detail_order->consignee_country_code)){
	                    $new_nations_array[$detail_order->consignee_country_code] = $detail_order->consignee_country_code;
	                }
	            }
	            if(!empty($new_nations_array)){
	                ConfigHelper::setConfig("RumallOMS/nations", json_encode($new_nations_array));
	            }
	        }
	        $nations_array = $new_nations_array;
	    }else if(is_array($nations_array)){
	       $hasChange = false; 
	       if(count($models)){//?????????????????????????????????
	            foreach ($models as $detail_order){
	                if(!empty($detail_order->consignee_country_code)){
	                    if(!isset($nations_array[$detail_order->consignee_country_code])){
	                        $nations_array[$detail_order->consignee_country_code] = $detail_order->consignee_country_code;
	                        $hasChange = true;
	                    }
	                }
	            }
	        }
	        if($hasChange){
	            ConfigHelper::setConfig("RumallOMS/nations", json_encode($nations_array));
	        }
	    }
	    
	    foreach ($nations_array as $nation_code=>$val){
	        $countryArr[$nation_code] = StandardConst::getNationChineseNameByCode($nation_code).'('.$nation_code.')';
	    }
	    //end ??????????????????
	     
	    //????????????????????????????????????????????????
// 	    $sysCountry = [];
// 	    $countryModels = SysCountry::find()->asArray()->all();
// 		foreach ($countryModels as $countryModel){
// 			$sysCountry[$countryModel['country_code']] = $countryModel['country_zh'];
// 		}
	    //end ????????????????????????????????????????????????
	     
	    //??????????????????
// 	    $warhouseArr = array();
// 	    $tmpWarhouseArr = Warehouse::find()->select(['warehouse_id','name'])->where(['is_active' => "Y"])->asArray()->all();
// 	    foreach ($tmpWarhouseArr as $tmpWarhouse){
// 	    	$warhouseArr[$tmpWarhouse['warehouse_id']] = $tmpWarhouse['name'];
// 	    }
	    //end ??????????????????
	    

	    $tmp_REQUEST_text['REQUEST']=$_REQUEST;
	    $tmp_REQUEST_text['order_source']=$addi_condition;
	    $tmp_REQUEST_text['params']=empty($data->params)?Array():$data->params;
	    $tmp_REQUEST_text=base64_encode(json_encode($tmp_REQUEST_text));
	    
		return $this->render('list',array(
			'models' => $models,
		    'existProductResult'=>$existProductResult,
		    'pages' => $pagination,
// 			'sort' => $sortConfig,
			'excelmodels'=>$excelmodels,
// 			'rumallUsersDropdownList'=>$rumallUsersDropdownList,
			'usertabs'=>$usertabs,
			'counter'=>$counter,
		    'warehouseids'=>$warehouseids,
		    'selleruserids'=>$rumallUsersDropdownList,
		    'countrys'=>$countrys,
			'countryArr'=>$countryArr,
// 			'sysCountry'=>$sysCountry,
// 			'warhouseArr'=>$warhouseArr,
			'showsearch'=>$showsearch,
			'tag_class_list'=> OrderTagHelper::getTagColorMapping(),
		    'all_tag_list'=>$allTagList,
		    'doarr'=>RumallOrderHelper::getRumallCurrentOperationList($op_code,'b') ,
		    'doarr_one'=>RumallOrderHelper::getRumallCurrentOperationList($op_code,'s'),
		    'country_mapping'=>$country_list,
		    'region'=>WarehouseHelper::countryRegionChName(),
		    'search'=>$search,
			'search_condition'=>$tmp_REQUEST_text,
			'search_count'=>$pagination->totalCount,
		));
		
	}
	
	/**
	 * ????????????
	 * @author fanjs
	 */
	public function actionEdit(){
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
			
			$rt = OrderHelper::setOriginShipmentDetail($order);
			if ($rt['success'] ==false){
			    return $this->render('//errorview',['title'=>'????????????','message'=>'??????????????????????????????']);
			}
			
			$action = '????????????';
			$module = 'order';
			$fullName = \Yii::$app->user->identity->getFullName();
			$rt = OrderUpdateHelper::updateOrder($order, $_tmp , false , $fullName , $action , $module);
			
// 			foreach($_tmp as $key=>$value){
// 			    $order->$key = $value;
// 			}
			
// 			$order->setAttributes($_tmp);
// 			$order->save();
			//????????????????????????
			foreach ($item_tmp['product_name'] as $key=>$val){
				if (strlen($item_tmp['itemid'][$key])){
					$item = OdOrderItem::findOne($item_tmp['itemid'][$key]);
					$OriginQty = $item->quantity; //??????????????????
				}else{
					$item = new OdOrderItem();
					$OriginQty = 0; //??????????????????
				}
				$item->order_id = $order->order_id;
				$item->product_name = $item_tmp['product_name'][$key];
				$item->sku = $item_tmp['sku'][$key];
				$item->quantity = $item_tmp['quantity'][$key];
				//$item->order_source_srn = $item_tmp['order_source_srn'][$key];
// 				$item->price = $item_tmp['price'][$key];
				$item->update_time = time();
				$item->create_time = is_null($item->create_time)?time():$item->create_time;
			    if ($item->save()){
    				if ($OriginQty != $item_tmp['quantity'][$key]){
    					list($ack , $message , $code , $rootSKU ) = array_values(OrderGetDataHelper::getRootSKUByOrderItem($item));
    					
    					list($ack , $code , $message  )  = array_values( OrderBackgroundHelper::updateUnshippedQtyOMS($rootSKU, $order->default_warehouse_id, $order->default_warehouse_id, $OriginQty, $item_tmp['quantity'][$key]));
    					if ($ack){
    						$addtionLog .= "$rootSKU $OriginQty=>".$item_tmp['quantity'][$key];
    					}
    				}
    			}
			}
// 			$order->checkorderstatus();
			$order->save();
			AppTrackerApiHelper::actionLog("Oms-rumall", "/order/rumall/edit-save");
			OperationLogHelper::log('order',$order->order_id,'????????????','??????????????????????????????',\Yii::$app->user->identity->getFullName());
// 			echo "<script language='javascript'>window.opener.location.reload();</script>";
			return $this->render('//successview',['title'=>'????????????']);
		}
		
		if (!isset($_GET['orderid'])){
			return $this->render('//errorview',['title'=>'????????????','message'=>'????????????']);
		}
		$order = OdOrder::findOne($_GET['orderid']);
		if (empty($order)||$order->isNewRecord){
			return $this->render('//errorview',['title'=>'????????????','message'=>'?????????????????????']);
		}
		AppTrackerApiHelper::actionLog("Oms-rumall", "/order/rumall/edit-page");
		
		$orderShipped = OdOrderShipped::find()->where(['order_id'=>$_GET['orderid']])->asArray()->all();
		return $this->render('edit',['order'=>$order,'ordershipped'=>$orderShipped]);
	}
	
	/**
	 * ????????????????????????????????????????????????,?????????????????????????????????????????????????????????
	 * @author fanjs
	 */
	public function actionSignshipped(){
		AppTrackerApiHelper::actionLog("Oms-rumall", "/order/rumall/signshipped");
		$rumallShippingMethod = RumallOrderHelper::getShippingCodeNameMap();
		if (\Yii::$app->request->getIsPost()){
			$orders = OdOrder::find()->where(['in','order_id',\Yii::$app->request->post()['order_id']])->all();
			return $this->render('signshipped',['orders'=>$orders,'rumallShippingMethod'=>$rumallShippingMethod]);
		}else {
			$orders = OdOrder::find()->where(['in','order_id',\Yii::$app->request->get('order_id')])->all();
			return $this->render('signshipped',['orders'=>$orders,'rumallShippingMethod'=>$rumallShippingMethod]);
		}
	}
	
	/**
	 * ????????????????????????????????????????????????,??????????????????
	 * @author fanjs
	 */
	public function actionSignshippedsubmit(){
		if (\Yii::$app->request->getIsPost()){
			AppTrackerApiHelper::actionLog("Oms-rumall", "/order/rumall/signshippedsubmit");
			$user = \Yii::$app->user->identity;
			$postarr = \Yii::$app->request->post();
			$tracker_provider_list  = RumallOrderHelper::getShippingCodeNameMap();
			if (count($postarr['order_id'])){
				foreach ($postarr['order_id'] as $oid){
					try {
						$order = OdOrder::findOne($oid);
						if (!empty($tracker_provider_list[$postarr['shipmethod'][$oid]])){
							$shipMethodName = $tracker_provider_list[$postarr['shipmethod'][$oid]];
						}else{
							echo "<script language='javascript'>alert('????????????????????????!');window.close();</script>";
							exit();
						}
						
						$logisticInfoList=[
							'0'=>[
							'order_source'=>$order->order_source,
							'selleruserid'=>$order->selleruserid,
							'tracking_number'=>$postarr['tracknum'][$oid],
							'tracking_link'=>$postarr['trackurl'][$oid],
							'shipping_method_code'=>$postarr['shipmethod'][$oid],
							'shipping_method_name'=>$shipMethodName,//?????????????????????
							'order_source_order_id'=>$order->order_source_order_id,
							'description'=>$postarr['message'][$oid],
							'addtype'=>'??????????????????',
							
						]
						];
						//echo print_r($logisticInfoList,true);
					
						if(!OrderHelper::saveTrackingNumber($oid, $logisticInfoList,0,1)){
							\Yii::error(["Order",__CLASS__,__FUNCTION__,"Online",'??????'.$oid.'????????????'],'edb\global');
						}else{
						OperationLogHelper::log('order', $oid,'????????????','????????????????????????',\Yii::$app->user->identity->getFullName());
					}
	
				}catch (\Exception $ex){
					\Yii::error(["Order",__CLASS__,__FUNCTION__,"Online","save to SignShipped failure:".print_r($ex->getMessage())],'edb\global');
				}
			}
			
			echo "<script language='javascript'>alert('???????????????,??????????????????');window.close();</script>";
			return $this->render('//successview',['title'=>'??????????????????']);
		}
		}
	}
	
// 	/**
// 	 +---------------------------------------------------------------------------------------------
// 	 * ????????????rumall??????,?????????????????????
// 	 +---------------------------------------------------------------------------------------------
// 	 * @access static
// 	 +---------------------------------------------------------------------------------------------
// 	 * @param
// 	 +---------------------------------------------------------------------------------------------
// 	 * @return
// 	 +---------------------------------------------------------------------------------------------
// 	 * log			name	date					note
// 	 * @author		lkh		2015/8/18				?????????
// 	 +---------------------------------------------------------------------------------------------
// 	 **/
// 	function actionSyncmt(){
		
// 		$sync = SaasRumallUser::find()->where(['is_active'=>'1' , 'uid'=>\Yii::$app->user->id,])->all();
		
// 		return $this->renderPartial('syncmt',['sync'=>$sync]);
// 	}
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ajax???????????????????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param
	 +---------------------------------------------------------------------------------------------
	 * @return
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/8/18				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	function actionAjaxsyncmt(){
		if (\Yii::$app->request->isPost){
			try {
				//??????site id ????????????
				if (empty($_POST['site_id'])){
					return json_encode(['ack'=>'failure','msg'=>'??????????????? ??????????????????']);
				}
				
				//????????????????????????
				$model = SaasRumallUser::findOne(['site_id'=>$_POST['site_id']]);
				if (!empty($model)){
					$result = RumallAccountsV2Helper::setManualRetrieveOrder($_POST['site_id']);
					if ($result['success'] == false){
						return json_encode(['ack'=>'failure','msg'=>$result['message']]);
					}
				}else{
					return json_encode(['ack'=>'failure','msg'=>'??????????????? ??????????????????']);
				}
				
			}catch (\Exception $e){
				return json_encode(['ack'=>'failure','msg'=>$e->getMessage()]);
			}
			return json_encode(['ack'=>'success']);
		}
	}
	
	function actionUnlockOrderQueue(){
		// ??????user ???puid ????????? rumall ????????????
		$puid = \Yii::$app->user->identity->getParentUid();
		if ($puid !="1"){
			exit('no found');
		}
		
		if (!empty($_REQUEST['site_id'])   ){
			$msg = empty($_REQUEST['msg'])?"":$_REQUEST['msg'];
			$status = empty($_REQUEST['oq_status'])?"":$_REQUEST['oq_status'];
			RumallOrderHelper::unlockRumallOrderQueue($_REQUEST['site_id'],$msg , $status);
			exit('OK');
		}else{
			exit('no site id');
		}
	}
	
	/**
	 +----------------------------------------------------------
	 * ????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2014/05/06				?????????
	 +----------------------------------------------------------
	 **/
	public function actionRefreshorder(){
	    if (\Yii::$app->request->isPost){
	        $orderids = $_POST['orders'];
	        Helper_Array::removeEmpty($orderids);
	        $uid = \Yii::$app->user->identity->getParentUid();
	        $error_message = "";
	        if (count($orderids)>0){
	            try {
	                //\Yii::info("\n".(__FUNCTION__)." $uid refreshorder 1","file");
	                foreach ($orderids as $order_id){
	                    //\Yii::info("\n".(__FUNCTION__)." $uid refreshorder 2:".$order_id,"file");
	                    $tmpRT = OrderHelper::requestRefreshOrderQueue($uid,  $order_id, 'rumall');
	                    //\Yii::info("\n".(__FUNCTION__)." $uid refreshorder 3:".json_encode($tmpRT),"file");
	                    if ($tmpRT['success']==false){
	                        //??????????????????
	                        $error_message .= $order_id.':'.$tmpRT['message'];
	                    }else{
	                        //??????????????????????????????
	                        OdOrderItem::deleteAll(['order_id'=>$order_id]);
	                        OdOrder::deleteAll(['order_id'=>$order_id]);
	                    }
	                }
	
	                if (!empty($error_message)) return $error_message;
	                return '???????????????,???????????????';
	            }catch (\Exception $e){
	                \Yii::info("\n".(__FUNCTION__)." $uid refreshorder E:".print_r($e->getMessage(),true),"file");
	                return $e->getMessage();
	            }
	        }else{
	            return '????????????????????????';
	        }
	    }//end of post
	}//end of actionRefreshorder
	
	/**
	 * ???????????????????????????
	 * @author million
	 */
	public function actionCheckorderstatus(){
	    if (\Yii::$app->request->isPost){
	        $orderids = explode(',',$_POST['orders']);
	        Helper_Array::removeEmpty($orderids);
	        if (count($orderids)>0){
	            try {
	                foreach ($orderids as $orderid){
	                    $order = OdOrder::findOne($orderid);
	                    if ($order->order_status=='200'){
	                        	
	                        if (!empty($_REQUEST['refresh_force']) && $_REQUEST['refresh_force'] =='true'){
	                            $isreset = 1;
	                        }else{
	                            $isreset = 0;
	                        }
	                        $order->checkorderstatus(NULL,$isreset);
	                        $order->save(false);
	                    }
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
	 * ??????????????????
	 * @author million
	 */
	public function actionImportordertracknum(){
	    if (\yii::$app->request->isPost){
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
	 * ???????????????????????????
	 * @author million
	 */
	public function actionChangemanual(){
	    if (\yii::$app->request->isPost){
	        $order = OdOrder::findOne($_POST['orderid']);
	        if (empty($order)){
	            return '?????????????????????';
	        }
	        if ($order->is_manual_order == 0){
	            $order->is_manual_order = 1;
	        }else{
	            $order->is_manual_order = 0;
	        }
	        $order->save();
	        return 'success';
	    }
	}
	
	/**
	 * ???????????????????????????
	 * @author million
	 */
	public function actionSetusertab(){
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
	 * @author million
	 */
	public function actionAjaxdesc(){
	    if(\Yii::$app->request->isPost){
	        $order = OdOrder::findOne($_POST['oiid']);
	        if (!empty($order)){
	            $rt = OrderHelper::addOrderDescByModel($order,  $_POST['desc'], 'order', '????????????');
	            /*
	             $olddesc = $order->desc;
	             $order->desc = $_POST['desc'];
	             $order->save();
	             OperationLogHelper::log('order',$order->order_id,'????????????','????????????: ('.$olddesc.'->'.$_POST['desc'] .')',\Yii::$app->user->identity->getFullName());
	
	            */
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
	 +----------------------------------------------------------
	 * ?????????????????????dash-board
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/05/17				?????????
	 +----------------------------------------------------------
	 **/
	public function actionUserDashBoard(){
	    $uid = \Yii::$app->user->id;
	    if (empty($uid))
	        exit('????????????!');
	
	    if (!empty($_REQUEST['isrefresh'])){
	        $isRefresh = true;
	    }else{
	        $isRefresh = false;
	    }
	
	    $cacheData = RumallOrderHelper::getOmsDashBoardData($uid,$isRefresh);
	
	    $platform = 'rumall';
	    $chartData['order_count'] = $cacheData['order_count'];
	    //$chartData['profit_count'] = CdiscountOrderInterface::getChartDataByUid_Profit($uid,10);//oms ???????????? aliexpress ???????????????
	    $advertData =  $cacheData['advertData'];// ??????OMS dashboard??????
	    $AccountProblems = RumallOrderHelper::getUserAccountProblems($uid); //??????????????????
	    $ret=$cacheData['reminderData'];
	    $_SESSION['ali_oms_dash_board_last_time'] = time();//????????????dash board ?????? ??????????????????
	
	    list($platformUrl,$label)=AppApiHelper::getPlatformMenuData();
	
	    return $this->renderAjax('_dash_board',[
	        'chartData'=>$chartData,
	        'advertData'=>$advertData,
	        'AccountProblems'=>$AccountProblems,
	        'ret'=>$ret,
	        'platformUrl'=>$platformUrl,
	    ]);
	}
	
	/**
	 +----------------------------------------------------------
	 * ?????? dashboard ??????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/05/19				?????????
	 +----------------------------------------------------------
	 **/
	public function actionGenrateUserDashBoard(){
	    $platform = 'rumall';
	    $uid = \Yii::$app->user->id;
	    if (empty($uid))
	        exit('????????????!');
	
	    if (!empty($_REQUEST['isrefresh'])){
	        $isRefresh = true;
	    }else{
	        $isRefresh = false;
	    }
	    $cacheData = RumallOrderHelper::getOmsDashBoardData($uid,$isRefresh);
	}
	
	/**
	 +----------------------------------------------------------
	 * rumall????????????   action
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2015/11/23				?????????
	 +----------------------------------------------------------
	 **/
	public function actionOrderSyncInfo(){
	
	    if (isset($_REQUEST['sync_status'])){
	        $status = $_REQUEST['sync_status'];
	    }else{
	        $status = "";
	    }
	
	    if (isset($_REQUEST['last_sync_time'])){
	        $last_sync_time = $_REQUEST['last_sync_time'];
	    }else{
	        $last_sync_time = "";
	    }
	
	    $detail = RumallOrderHelper::getOrderSyncInfoDataList($status,$last_sync_time);
	
	    if (!empty($_REQUEST['order_status']))
	        $order_nav_key_word = $_REQUEST['order_status'];
	    else
	        $order_nav_key_word='';
	
// 	    $counter = OrderHelper::getMenuStatisticData('bonanza');
	
	    return $this->renderAjax('order_sync',[
	        'sync_list'=>$detail,
// 	        'order_nav_html'=>BonanzaOrderHelper::getBonanzaOmsNav($order_nav_key_word),
// 	        'counter'=>$counter,
	    ]);
	}//end of actionOrderSyncInfo
	
	
	
}

?>