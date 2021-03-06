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
use eagle\modules\order\models\OdOrderShipped;
use eagle\models\carrier\SysShippingService;
use yii\data\Sort;
use eagle\models\SaasCdiscountUser;
use eagle\modules\inventory\models\Warehouse;
use eagle\modules\order\helpers\OrderTagHelper;
use eagle\modules\util\helpers\StandardConst;
use eagle\modules\order\helpers\CdiscountOrderInterface;
use eagle\modules\order\helpers\OrderHelper;
use eagle\models\SysCountry;
use eagle\modules\listing\models\CdiscountOfferList;
use eagle\modules\listing\helpers\CdiscountOfferSyncHelper;
use eagle\modules\app\apihelpers\AppTrackerApiHelper;
use eagle\modules\order\helpers\CdiscountOrderHelper;
use eagle\modules\order\models\CdiscountOrder;
use eagle\modules\order\models\CdiscountOrderDetail;
use eagle\modules\listing\helpers\CdiscountProxyConnectHelper;
use common\api\cdiscountinterface\CdiscountInterface_Helper;
use eagle\modules\message\apihelpers\MessageCdiscountApiHelper;
use eagle\modules\html_catcher\helpers\HtmlCatcherHelper;
use eagle\modules\html_catcher\models\CollectRequestQueue;
use eagle\modules\util\helpers\TimeUtil;
use eagle\modules\html_catcher\helpers\CdiscountOpenApi;
use eagle\modules\platform\apihelpers\CdiscountAccountsApiHelper;
use eagle\modules\order\helpers\OrderBackgroundHelper;
use eagle\modules\catalog\models\Product;
use eagle\modules\catalog\helpers\ProductHelper;
use eagle\modules\util\helpers\DataStaticHelper;
use eagle\modules\util\helpers\ConfigHelper;
use Qiniu\json_decode;
use eagle\modules\util\helpers\SysLogHelper;
use eagle\modules\order\apihelpers\OrderApiHelper;
use eagle\modules\order\models\OrderRelation;
use eagle\modules\inventory\apihelpers\InventoryApiHelper;
use eagle\modules\order\helpers\OrderUpdateHelper;
use eagle\modules\order\helpers\OrderGetDataHelper;
use eagle\modules\util\helpers\RedisHelper;
use eagle\modules\permission\helpers\UserHelper;
use eagle\modules\platform\apihelpers\PlatformAccountApi;

class CdiscountOrderController extends \eagle\components\Controller{
	public $enableCsrfValidation = false;
	
	/**
	 * cdiscount??????????????????
	 */
	public function actionList(){
		//check????????????
		$permission = \eagle\modules\permission\apihelpers\UserApiHelper::checkPlatformPermission('cdiscount');
		if(!$permission)
			return $this->render('//errorview_no_close',['title'=>'????????????:????????????','error'=>'?????????????????????????????????!']);
		$current_time=explode(" ",microtime()); $time1=round($current_time[0]*1000+$current_time[1]*1000);
		//???????????????????????????external ?????????????????????
		//$run_time = $time2 - $time1; //???????????????$time?????? ms ????????????
 
		//////??????????????????	//???????????????????????????,??????fix??????bug
		$target_time = strtotime('2016-07-20 00:00:00');
		if( time()<$target_time){
			$pending_checks = OdOrder::find()->where(['order_source' => 'cdiscount','order_status'=>OdOrder::STATUS_PAY])
								->andWhere("pay_order_type='pending' or pay_order_type is null")
								//->andWhere("pay_order_type='pending' or pay_order_type is null or exception_status is not null")
								->all();
			foreach ($pending_checks as $pending_check){
				$pending_check->checkorderstatus(null,1);
				$pending_check->save(false);
			}
		}
		//////??????????????????end
		
		// ??????user ???puid ????????? cdiscount ????????????
		$puid = \Yii::$app->user->identity->getParentUid();
		
		//
		//$cdiscountUsers = SaasCdiscountUser::find()->where(['uid'=>$puid])->asArray()->all();
		//$cdiscountUsersDropdownList = array();
		//foreach ($cdiscountUsers as $cdiscountUser){
		//	$cdiscountUsersDropdownList[$cdiscountUser['username']] = $cdiscountUser['store_name'];
		//}
		
		//???????????????????????????
		$cdiscountUsersDropdownList = PlatformAccountApi::getPlatformAuthorizeAccounts('cdiscount');
		
		$data=OdOrder::find();
		if (!empty($cdiscountUsersDropdownList)){
			//????????????????????????????????????
			$data->andWhere(['selleruserid'=>array_keys($cdiscountUsersDropdownList)]);
		}else{
			//??????????????????????????????????????????????????????
			$test_userid=\eagle\modules\tool\helpers\MirroringHelper::$test_userid;
			if (!in_array($puid,$test_userid['yifeng'])){
				//????????????????????????
				return $this->render('//errorview_no_close',['title'=>'????????????:????????????','error'=>'???????????????????????? Cdiscount ??????????????????!']);
			}
		}
		
		if (isset($_REQUEST['profit_calculated'])){
			if($_REQUEST['profit_calculated']==1){
				$data->andWhere(" `profit` IS NOT NULL ");
			}elseif($_REQUEST['profit_calculated']==2){
				$data->andWhere(" `profit` IS NULL ");
			}
		}

		//??????????????????????????????????????????????????????page size ???	//lzhl	2016-11-30
		$page_url = $page_url = '/'.\Yii::$app->controller->module->id.'/'.\Yii::$app->controller->id.'/'.\Yii::$app->controller->action->id;
		$last_page_size = ConfigHelper::getPageLastOpenedSize($page_url);
		if(empty($last_page_size))
			$last_page_size = 50;//???????????????
		if(empty($_REQUEST['per-page']) && empty($_REQUEST['page']))
			$pageSize = $last_page_size;
		else{
			$pageSize = empty($_REQUEST['per-page'])?50:$_REQUEST['per-page'];
		}
		ConfigHelper::setPageLastOpenedSize($page_url, $pageSize);
		
		$sortConfig = new Sort(['attributes' => ['grand_total','create_time','order_source_create_time','paid_time','delivery_time']]);
		$showsearch=0;
		$op_code = '';
		
		
		$addi_condition = ['order_source'=>'cdiscount'];
		$addi_condition['sys_uid'] = \Yii::$app->user->id;
		$addi_condition['selleruserid_tmp'] = $cdiscountUsersDropdownList;
		
		$startDateTime = empty($_REQUEST['starttime'])?'':$_REQUEST['starttime'];
		$endDateTime = empty($_REQUEST['endtime'])?'':$_REQUEST['endtime'];
		
		$tmp_REQUEST = $_REQUEST;
		if(!empty($startDateTime) && !empty($tmp_REQUEST['startdate']))
			$tmp_REQUEST['startdate'] .= ' '.$startDateTime;
		if(!empty($endDateTime) && !empty($tmp_REQUEST['enddate']))
			$tmp_REQUEST['enddate'] .= ' '.$endDateTime;
		if(isset($tmp_REQUEST['starttime']))
			unset($tmp_REQUEST['starttime']);
		if(isset($tmp_REQUEST['endtime']))
			unset($tmp_REQUEST['endtime']);
		if(!empty($tmp_REQUEST['order_type'])){
			$showsearch = 1;
			if($tmp_REQUEST['order_type']=='normal')
				$data->andWhere(['order_type'=>['',null,'normal']]);
			elseif(strtolower($tmp_REQUEST['order_type']=='fbc'))
				$data->andWhere(['order_type'=>'FBC']);
			else 
				unset($tmp_REQUEST['order_type']);
		}
		
		$tmp_REQUEST_text['where']=empty($data->where)?Array():$data->where;
		$tmp_REQUEST_text['orderBy']=empty($data->orderBy)?Array():$data->orderBy;
		$omsRT = OrderApiHelper::getOrderListByConditionOMS($tmp_REQUEST,$addi_condition,$data,$pageSize,false,'all');
// 		print_r($omsRT);die;
		if (!empty($_REQUEST['order_status'])){
			//???????????????????????????code
			$op_code = $_REQUEST['order_status'];
		}
		if (!empty($omsRT['showsearch'])) $showsearch = 1;
		
	    $pages = new Pagination([
	    		'defaultPageSize' => 50,
	    		'pageSize' => $pageSize,
	    		'totalCount' => $data->count(),
	    		'pageSizeLimit'=>[5,200],//????????????????????????
	    		'params'=>$_REQUEST,
	    		]);
	    $models = $data->offset($pages->offset)
	    	->limit($pages->limit)
	    	->all();
	    //////////////////////////////////////////////////////////////////////
	    
	    //yzq 2017-2-21, to do bulk loading the order items, not to use lazy load
	    OrderHelper::bulkLoadOrderItemsToOrderModel($models);
	    OrderHelper::bulkLoadOrderShippedModel($models);
	    
		$current_time=explode(" ",microtime()); $time5=round($current_time[0]*1000+$current_time[1]*1000);
	    //???????????????????????????external ?????????????????????
	    //$run_time = $time5 - $time4; //???????????????$time?????? ms ????????????
	    
	    //if (\Yii::$app->user->identity->getParentUid()== 3903){
		//    $journal_id = SysLogHelper::InvokeJrn_Create("OMS",__CLASS__, __FUNCTION__ , array('ys5-4',$run_time,$sqlSyntax));
	    //}
	    
	    $excelmodel	=	new Excelmodel();
	    $model_sys	=	$excelmodel->find()->all();
	    
	    $excelmodels=array(''=>'????????????');
	    if(isset($model_sys)&&!empty($model_sys)){
	    	foreach ($model_sys as $m){
	    		$excelmodels[$m->id]=$m->name;
	    	}
	    }
	    
	    $usertabs = Helper_Array::toHashmap(Helper_Array::toArray(Usertab::find()->all()),'id','tabname');
	    
	    //??????????????????
	    $counter=[];
	    
	    //??????redis???????????????????????????
	    $hitCache = "NoHit";
	    $cachedArrAll = array();
	    $uid = \Yii::$app->user->id;
	    $stroe = 'all';
	    if(!empty($_REQUEST['selleruserid']))
	    	$stroe  = trim($_REQUEST['selleruserid']);
	    
	    $isParent = \eagle\modules\permission\apihelpers\UserApiHelper::isMainAccount();
	    if($isParent){
	    	$gotCache = RedisHelper::getOrderCache2($puid,$uid,'cdiscount',"MenuStatisticData",$stroe) ;
	    }else{
	    	if (!empty($_REQUEST['selleruserid'])){
	    		$gotCache = RedisHelper::getOrderCache2($puid,$uid,'cdiscount',"MenuStatisticData",$_REQUEST['selleruserid']) ;
	    	}else{
	    		$gotCache = RedisHelper::getOrderCache2($puid,$uid,'cdiscount',"MenuStatisticData",'all') ;
	    	}
	    }
    	if (!empty($gotCache)){
    		
    		$cachedArrAll = is_string($gotCache)?json_decode($gotCache,true):$gotCache;
    		$counter = $cachedArrAll;
    		$hitCache= "Hit";
    	}
	    
	   
	    //redis???????????????????????????????????????????????????redis
	    if ($hitCache <>"Hit"){
	    	if (!empty($_REQUEST['selleruserid'])){
		    	$counter = OrderHelper::getMenuStatisticData('cdiscount',['selleruserid'=>$_REQUEST['selleruserid']]);
		    	$counter['todayorder'] = OdOrder::find()->where(['and',['order_source'=>'cdiscount'],['>=','order_source_create_time',strtotime(date('Y-m-d'))],['<','order_source_create_time',strtotime('+1 day')] ])
		    		->andWhere(['selleruserid'=>$_REQUEST['selleruserid']])->count();
		    	$counter['sendgood'] = OdOrder::find()->where(['order_source'=>'cdiscount','order_source_status'=>'WaitingForShipmentAcceptation'])->andwhere('order_status < 300')
		    		->andWhere(['selleruserid'=>$_REQUEST['selleruserid']])->count();
		    	$claimOrderIDs = MessageCdiscountApiHelper::claimOrderIDs($_REQUEST['selleruserid']);
		    	$counter['issueorder'] = OdOrder::find()->where(['order_source'=>'cdiscount','issuestatus'=>'IN_ISSUE'])->andWhere(['selleruserid'=>$_REQUEST['selleruserid']])->count();
		    }else{
		    	if(!empty($cdiscountUsersDropdownList)){
		    		$selleruserid_arr = array_keys($cdiscountUsersDropdownList);
		    		$counter = OrderHelper::getMenuStatisticData('cdiscount',['selleruserid'=>$selleruserid_arr]);
		    		$counter['todayorder'] = OdOrder::find()->where(['and',['order_source'=>'cdiscount','selleruserid'=>$selleruserid_arr],['>=','order_source_create_time',strtotime(date('Y-m-d'))],['<','order_source_create_time',strtotime('+1 day')] ])->count();
		    		$counter['sendgood'] = OdOrder::find()->where(['order_source'=>'cdiscount','selleruserid'=>$selleruserid_arr,'order_source_status'=>'WaitingForShipmentAcceptation'])->andwhere('order_status < 300')->count();
		    		$claimOrderIDs = MessageCdiscountApiHelper::claimOrderIDs();
		    		$counter['issueorder'] = OdOrder::find()->where(['order_source'=>'cdiscount','selleruserid'=>$selleruserid_arr,'issuestatus'=>'IN_ISSUE'])->count();
		    	}else{
		    		//?????????????????????
		    		$counter=[];
		    		$claimOrderIDs=[];
		    	}
		    }
			$counter['newmessage'] = empty($claimOrderIDs['unRead'])?0:count($claimOrderIDs['unRead']['orderIds']);
	    	//save the redis cache for next time use
			if (!empty($_REQUEST['selleruserid'])){
				RedisHelper::setOrderCache2($puid,$uid,'cdiscount',"MenuStatisticData",$_REQUEST['selleruserid'],$counter) ;
			}else{
				RedisHelper::setOrderCache2($puid,$uid,'cdiscount',"MenuStatisticData",'all',$counter) ;
			}
	    }
	    
		//$counter['issueorder'] = empty($claimOrderIDs['openStatus'])?0:count($claimOrderIDs['openStatus']);
	    
		
	    //??????????????????
	    $countryArr = array();
	    $countryArr['FR'] = StandardConst::getNationChineseNameByCode('FR').'(FR)';
	    
	    //end ??????????????????
	     
	    //????????????????????????????????????????????????
	    $sysCountry = [];
	    $countryModels = SysCountry::find()->asArray()->all();
		foreach ($countryModels as $countryModel){
			$sysCountry[$countryModel['country_code']] = ['country_zh'=>$countryModel['country_zh'],'country_en'=>$countryModel['country_en']];
		}
	    //end ????????????????????????????????????????????????
	     
	    //??????????????????
	    $warhouseArr = array();
	    $tmpWarhouseArr = Warehouse::find()->select(['warehouse_id','name'])->where(['is_active' => "Y"])->asArray()->all();
	    foreach ($tmpWarhouseArr as $tmpWarhouse){
	    	$warhouseArr[$tmpWarhouse['warehouse_id']] = $tmpWarhouse['name'];
	    }
	    //end ??????????????????
	    AppTrackerApiHelper::actionLog("Oms-cdiscount", "/order/cdiscount-order/list");//??????cdiscount??????????????????
	    
	    //$usertabs = Helper_Array::toHashmap(Helper_Array::toArray(Usertab::find()->all()),'id','tabname');
	    
	    //????????????
	    if (!empty($_REQUEST['order_status']))
	    	$order_nav_key_word = $_REQUEST['order_status'];
	    else
	    	$order_nav_key_word='';
	    
	    $search = array('is_comment_status'=>'WaitingForShipmentAcceptation');
	    
	    //tag ????????????
	    $allTagDataList = OrderTagHelper::getTagByTagID();
	    $allTagList = [];
	    foreach($allTagDataList as $tmpTag){
	    	$allTagList[$tmpTag['tag_id']] = $tmpTag['tag_name'];
	    }
	    
	    //?????????????????????????????? start
	    $OrderIdList = [];
	    
	    $existProductResult = OrderBackgroundHelper::getExistProductRuslt($models);
	    //?????????????????????????????? end
	   
	    
	    //??????????????????
	    $sku_List = [];
	    foreach ($models as $model){
	    	$order_items = $model->items;
	    	foreach ($order_items as $item){
	    		$sku_List[] = $item->sku;
	    	}
	    }
	    $sku_List = array_unique($sku_List);
	    $product_infos = [];
	    /*
	    $product_models = Product::find()->where(['sku'=>$sku_List])->asArray()->all();
	    foreach ($product_models as $prod_model){
	    	$product_infos[$prod_model['sku']]=$prod_model;
	    }
	    //????????????
	    foreach ($sku_List as $sku){
	    	if(!array_key_exists($sku, $product_infos)){
	    		$rootSku = ProductHelper::getRootSkuByAlias($sku);
	    		if(!empty($rootSku)){
	    			$root_prod = Product::find()->where(['sku'=>$rootSku])->asArray()->one();
	    			if(!empty($root_prod))
	    				$product_infos[$sku] = $root_prod;
	    		}
	    	}
	    }
	    */
	    $current_time=explode(" ",microtime()); $time6=round($current_time[0]*1000+$current_time[1]*1000);
	    //???????????????????????????external ?????????????????????
	    $run_time = $time6 - $time5; //???????????????$time?????? ms ????????????
	    
	    if (\Yii::$app->user->identity->getParentUid()== 3903){
	    	$journal_id = SysLogHelper::InvokeJrn_Create("OMS",__CLASS__, __FUNCTION__ , array('ys6-5',$run_time));
	    }

	    $tmp_REQUEST_text['REQUEST']=$tmp_REQUEST;
	    $tmp_REQUEST_text['order_source']=$addi_condition;
// 	    $tmp_REQUEST_text['where']=empty($data->where)?Array():$data->where;
// 	    $tmp_REQUEST_text['orderBy']=empty($data->orderBy)?Array():$data->orderBy;
	    $tmp_REQUEST_text['params']=empty($data->params)?Array():$data->params;
	   	$tmp_REQUEST_text=base64_encode(json_encode($tmp_REQUEST_text));

		return $this->render('list',array(
			'models' => $models,
			'existProductResult'=>$existProductResult,
		    'pages' => $pages,
			'sort' => $sortConfig,
			'excelmodels'=>$excelmodels,
			'cdiscountUsersDropdownList'=>$cdiscountUsersDropdownList,
			'usertabs'=>$usertabs,
			'counter'=>$counter,
			'countryArr'=>$countryArr,
			'sysCountry'=>$sysCountry,
			'warhouseArr'=>$warhouseArr,
			'showsearch'=>$showsearch,
			'tag_class_list'=> OrderTagHelper::getTagColorMapping(),
			'order_nav_html'=>CdiscountOrderHelper::getCdiscountOmsNav($order_nav_key_word),
			'search'=>$search,
			'all_tag_list'=>$allTagList,
			'doarr'=>OrderHelper::getCurrentOperationList($op_code,'b') ,
			'doarr_one'=>OrderHelper::getCurrentOperationList($op_code,'s'),
			'product_infos'=>$product_infos,
			'search_condition'=>$tmp_REQUEST_text,
			'search_count'=>$pages->totalCount,
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
			$old_status = $order->order_status;
			$old_warehouse_id = $order->default_warehouse_id;
			//??????????????????
			if((int)$_tmp['default_warehouse_id']!==$old_warehouse_id)
				$warehouse_changed = true;
			else 
				$warehouse_changed = false;
			/*??????????????????????????????????????????????????????????????????2016/10/08
			$order->setAttributes($_tmp);
			$order->save();
			 */
			$new_status = $order->order_status;
			
			$action = '????????????';
			$module = 'order';
			$fullName = \Yii::$app->user->identity->getFullName();
			$rt = OrderUpdateHelper::updateOrder($order, $_tmp , false , $fullName , $action , $module);
			
			$addtionLog = '';
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
				/*??????????????????????????????????????????????????????????????????2016/10/08
				$old_item_qty = (int)$item->quantity;
				if( $old_item_qty!==(int)$item_tmp['quantity'][$key] || $warehouse_changed ){
					$sku = empty($item->sku)?$item->product_name:$item->sku;
					if($warehouse_changed){//????????????
						//??????????????????
						InventoryApiHelper::updateQtyOrdered($old_warehouse_id, $sku, (0-$old_item_qty) );
						//??????????????????
						InventoryApiHelper::updateQtyOrdered($order->default_warehouse_id, $sku, (int)$item_tmp['quantity'][$key]);
					}
					else{
						//??????????????????????????????????????????????????????
						InventoryApiHelper::updateQtyOrdered($old_warehouse_id, $sku, $old_item_qty-(int)$item_tmp['quantity'][$key]);
					}
				}
				*/
				$item->quantity = $item_tmp['quantity'][$key];
				//$item->order_source_srn = $item_tmp['order_source_srn'][$key];
				$item->price = $item_tmp['price'][$key];
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
			
			$order->checkorderstatus();
			
			//??????weird_status liang 2015-12-26
			if($old_status!==$new_status && ($new_status!==500 ||$new_status!==600) ){
				if(!empty($order->weird_status))
					$addtionLog .= ',?????????????????????????????????';
				$order->weird_status = '';
			}//??????weird_status end
			$order->save();
			AppTrackerApiHelper::actionLog("Oms-cdiscount", "/order/cdiscount-order/edit-save");//??????cdiscount????????????
			OperationLogHelper::log('order',$order->order_id,'????????????','??????????????????????????????'.$addtionLog,\Yii::$app->user->identity->getFullName());
			echo "<script language='javascript'>window.opener.location.reload();</script>";
			return $this->render('//successview',['title'=>'????????????']);
		}
		if (!isset($_GET['orderid'])){
			return $this->render('//errorview',['title'=>'????????????','message'=>'????????????']);
		}
		$order = OdOrder::findOne($_GET['orderid']);
		if (empty($order)||$order->isNewRecord){
			return $this->render('//errorview',['title'=>'????????????','message'=>'?????????????????????']);
		}
		//$countrycode=OdOrder::getDb()->createCommand('select consignee_country_code from '.OdOrder::tableName().' group by consignee_country_code')->queryColumn();
		//$countrycode=array_filter($countrycode);
		//????????????????????????????????????????????????
		$sysCountry = [];
		$countryModels = SysCountry::find()->asArray()->all();
		foreach ($countryModels as $countryModel){
			$sysCountry[$countryModel['country_code']] = $countryModel['country_zh'];
		}
		
		AppTrackerApiHelper::actionLog("Oms-cdiscount", "/order/cdiscount-order/edit");//??????cdiscount??????????????????
		
		return $this->render('edit',['order'=>$order,'countrys'=>$sysCountry]);
	}
	
	/**
	 * ????????????????????????????????????????????????,?????????????????????????????????????????????????????????
	 * @author lzhl
	 */
	///*????????????OMS??????????????????,???????????????
	public function actionSignshipped(){
		AppTrackerApiHelper::actionLog("Oms-cdiscount", "/order/cdiscount/signshipped");//??????cdiscount??????????????????
		
		$tmpShippingMethod  = CdiscountOrderInterface::getShippingCodeNameMap();
		$tmpShippingMethod = DataStaticHelper::getUseCountTopValuesFor("CdiscountOms_ShippingMethod" ,$tmpShippingMethod );
		
		$cdiscountShippingMethod = [];
		if(!empty($tmpShippingMethod['recommended'])){
			$cdiscountShippingMethod += $tmpShippingMethod['recommended'];
			$cdiscountShippingMethod[''] = '---??????/????????? ?????????---';
		}
		if(!empty($tmpShippingMethod['rest']))
			$cdiscountShippingMethod += $tmpShippingMethod['rest'];
		
		if (\Yii::$app->request->getIsPost()){
			//$orders = OdOrder::find()->where(['in','order_id',\Yii::$app->request->post()['order_id']])->andWhere("order_capture<>'Y'")->all();
			$tmpOrders = \Yii::$app->request->post()['order_id'];
			//return $this->render('signshipped',['orders'=>$orders,'cdiscountShippingMethod'=>$cdiscountShippingMethod]);
		}else {
			//$orders = OdOrder::find()->where(['in','order_id',\Yii::$app->request->get('order_id')])->andWhere("order_capture<>'Y'")->all();
			//return $this->render('signshipped',['orders'=>$orders,'cdiscountShippingMethod'=>$cdiscountShippingMethod]);
			$tmpOrders = \Yii::$app->request->get()['order_id'];
		}
		
		
		
		$orders = OdOrder::find()->where(['in','order_id',$tmpOrders])->andwhere(['order_capture'=>'N'])->all();
		if (empty($orders))
			return $this->render('//errorview',['title'=>'????????????','error'=>'?????????????????????']);
		
		foreach ($orders as $key=>$order){
			if('sm' == $order->order_relation){// ??????????????????????????????????????????????????????
				$father_orderids = OrderRelation::findAll(['son_orderid'=>$order->order_id , 'type'=>'merge' ]);
				foreach ($father_orderids as $father_orderid){
					$tmpOrders[] = $father_orderid->father_orderid;
					$orders[] = OdOrder::findOne($father_orderid->father_orderid);
				}
					
				unset($orders[$key]);
			}
		}
		
		return $this->render('signshipped',['orders'=>$orders,'cdiscountShippingMethod'=>$cdiscountShippingMethod]);
		
	}
	//*/
	
	/**
	 * ????????????????????????????????????????????????,??????????????????
	 * @author lzhl
	 */
	///*????????????OMS??????????????????,???????????????
	public function actionSignshippedsubmit(){
		if (\Yii::$app->request->getIsPost()){
			AppTrackerApiHelper::actionLog("Oms-cdiscount", "/order/cdiscount/signshippedsubmit");//cdiscount????????????????????????????????????
			$user = \Yii::$app->user->identity;
			$postarr = \Yii::$app->request->post();
			$tracker_provider_list  = CdiscountOrderInterface::getShippingCodeNameMap();
			if (count($postarr['order_id'])){
				foreach ($postarr['order_id'] as $oid){
					try {
						$order = OdOrder::findOne($oid);
						if(empty($postarr['shipmethod'][$oid])){
							return $this->render('//errorview',['title'=>'????????????','error'=>'?????????????????????!']);
							//echo "<script language='javascript'>alert('????????????????????????!');//window.close();</script>";
							//exit();
						}
						if (!empty($tracker_provider_list[$postarr['shipmethod'][$oid]])){
							if(strtolower($postarr['shipmethod'][$oid])!=='other')
								$shipMethodName = $tracker_provider_list[$postarr['shipmethod'][$oid]];
							else 
								$shipMethodName = $postarr['othermethod'][$oid];
						}else{
							$shipMethodName='';
						}
						
						if(empty($postarr['trackurl'][$oid]))
							$postarr['trackurl'][$oid] = CdiscountOrderInterface::getShippingMethodDefaultURL($postarr['shipmethod'][$oid]);
						
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
							//?????????????????????????????????????????????	lzhl 2016-08-02
							DataStaticHelper::addUseCountFor("CdiscountOms_ShippingMethod", $postarr['shipmethod'][$oid],8);
						}
					}catch (\Exception $ex){
						\Yii::error(["Order",__CLASS__,__FUNCTION__,"Online","save to SignShipped failure:".print_r($ex->getMessage())],'edb\global');
					}
				}
			
			//echo "<script language='javascript'>alert('???????????????,??????????????????');//window.close();</script>";
			return $this->render('//successview',['title'=>'??????????????????']);
			}
		}
	}
	//*/
	
	/*
	 * ??????cdiscount??????????????????List??????
	 */
	public function actionViewOfferList(){
		// ??????user ???puid ????????? cdiscount ????????????
		$puid = \Yii::$app->user->identity->getParentUid();
		$cdiscountUsers = SaasCdiscountUser::find()->where(['uid'=>$puid])->asArray()->all();
		
		$cdiscountUsersDropdownList = array();
		foreach ($cdiscountUsers as $cdiscountUser){
			$cdiscountUsersDropdownList[$cdiscountUser['username']] = $cdiscountUser['store_name'];
		}
		
		$pageSize = !empty($_REQUEST['per-page']) ? $_REQUEST['per-page'] : 20;
		$sort = !empty($_REQUEST['sort']) ? $_REQUEST['sort'] : '-creation_date';
		 
		if( '-' == substr($sort,0,1) ){
			$sort = substr($sort,1);
			$order = 'desc';
		} else {
			$order = 'asc';
		}	
		 
		$sortConfig = new Sort(['attributes' => ['stock','price','creation_date','last_15_days_sold']]);
		if(!in_array($sort, array_keys($sortConfig->attributes))){
			$sort = '';
			$order = '';
		}
		
		$query = CdiscountOfferList::find()->where(['not' ,['seller_product_id'=>'']]);
		
		if(isset($_REQUEST['seller_id']) && $_REQUEST['seller_id']!=='')
			$query->andWhere(['seller_id'=> $_REQUEST['seller_id']]);
		
		if(isset($_REQUEST['offer_state']) && $_REQUEST['offer_state']!==''){
			if($_REQUEST['offer_state']=='Active')
				$query->andWhere(['offer_state'=>'Active']);
			else 
				$query->andWhere(['not',['offer_state'=>'Active']]);
		}
		
		if(isset($_REQUEST['is_bestseller']) && $_REQUEST['is_bestseller']!=='')
			$query->andWhere(['is_bestseller'=> $_REQUEST['is_bestseller']]);
		
		if(isset($_REQUEST['min_price']) && $_REQUEST['min_price']!=='' && is_numeric($_REQUEST['min_price'])){
			$min_price = floatval($_REQUEST['min_price']);
			$query->andWhere(" price >= $min_price ");
		}
		if(isset($_REQUEST['max_price']) && $_REQUEST['max_price']!=='' && is_numeric($_REQUEST['max_price'])){
			$max_price = floatval($_REQUEST['max_price']);
			$query->andWhere(" price <= $max_price ");
		}
		
		if(isset($_REQUEST['keyword']) && $_REQUEST['keyword']!==''){
			$keyword = trim($_REQUEST['keyword']);
			$query->andWhere([ 'or', ['like','product_ean',$keyword],
									 ['like','product_id',$keyword],
									 ['like','seller_product_id',$keyword],
									 ['like','name',$keyword],
							]);
		}
		
		$pagination = new Pagination([
				'pageSize' => $pageSize,
				'totalCount' =>$query->count(),
				'pageSizeLimit'=>[5,200],//????????????????????????
				]);
		$offerList['pagination'] = $pagination;

		$offerRows = $query->orderBy("$sort $order")
						->limit($pagination->limit)
						->offset($pagination->offset)
						->asArray()
						->all();
		$offerList['rows']=$offerRows;

		return $this->render('offer_list',[
				'offerList'=>$offerList,
				'cdiscountUsersDropdownList'=>$cdiscountUsersDropdownList,
				'sort'=>$sortConfig,
			]);
	}
	
	public function actionViewOffer($id){
		$errMsg = '';
		$offer=[];
		if(!is_numeric($id))
			$errMsg = '????????????????????? id ???';
		else{
			$offer = CdiscountOfferList::find()->where(['id'=>$id])->asArray()->One();
			if(empty($offer))
				$errMsg = '??????id???cdiscount??????';
		}
		return $this->renderAjax('view_offer',[
					'offer'=>$offer,
					'errMsg'=>$errMsg,
				]);
	}
	
	public function actionPrintOffers(){
		return $this->render('print_offer');
	}
	
	/**
	 * ????????????????????????(????????????)
	 * @params string	$_REQUEST['orderIds']
	 * @return array
	 */
	public function actionGetEmail(){
		$uid = \Yii::$app->user->id;
		if(empty($_REQUEST['orderIds'])){
			exit(json_encode(array('success'=>false,'message'=>'???????????????????????????')));
		}
		$orderIds = explode(',',$_REQUEST['orderIds']);
		$ret = CdiscountOrderInterface::getOrdersEmail($uid, $orderIds);
		exit(json_encode($ret));
	}
	
	
	/**
	 * ??????????????????(??????)
	 * @return string
	 */
	public function actionCloseReminder(){
		$uid = \Yii::$app->user->id;
		$ret = CdiscountOrderInterface::CloseReminder($uid);
		exit($ret);
	}
	
	public function actionGetChildrenOfferInfo(){
		$uid = \Yii::$app->user->id;
		$offer=[];
		$child_offer = CdiscountOfferList::find()->select("product_id,parent_product_id")->where(['like','product_id','-0'])->asArray()->all();
		$count=0;
		$parent_ids = [];
		$prod_ids = [];
		foreach ($child_offer as $offer){
			$prod_ids[] = $offer['product_id'];
			if(!empty($offer['parent_product_id'])){
				$productIdStr = explode('-', $offer['parent_product_id']);
				if(!empty($productIdStr[0])){
					$parent_ids[] = $productIdStr[0];
				}
			
				$count++;
			}
		}
		$parent_ids = array_unique($parent_ids);
		CdiscountOfferSyncHelper::syncProdInfoWhenGetOrderDetail($uid,$parent_ids,$priority=1);
		CdiscountOfferSyncHelper::syncProdInfoWhenGetOrderDetail($uid,$prod_ids,$priority=3);
		return $this->renderAjax('view_offer',[
					'offer'=>$offer,
					'errMsg'=>'find '.$count.'products,put to queue.',
				]);
	}
	
	public function actionGetNonImgOfferInfo(){
		global $CACHE;
		$uid = \Yii::$app->user->id;
		$message='';
		$nonImgOffer = CdiscountOfferList::find()->select("product_id")->where(['img'=>null])->andwhere("`parent_product_id` is null or `parent_product_id`='' or `parent_product_id` like'%@attributes%' ")->asArray()->all();
		$count=0;
		$scuuessCount = 0;
		foreach ($nonImgOffer as $offer){
			$prod_id = $offer['product_id'];
			if(isset($CACHE['CollectQueueExistsRequestFor'][$prod_id]))
				echo '<br>CACHE was existing this product id !!!';
			$rtn = CdiscountOfferSyncHelper::syncProdInfoWhenGetOrderDetail($uid,$prod_id,$priority=1);
			if(empty($rtn['success']))
				$message.=empty($rtn['message'])?'':$rtn['message'];
			else {
				$scuuessCount++;
				print_r($rtn);
			}
			$count++;
		}
		echo '<br>find '.$count.'products,successed put to queue count '.$scuuessCount.';';
		if(is_string($message))
			echo $message;
		else 
			print_r($message,true);
	}
	
	
	public function actionSqlTest(){
		echo "<br>SQLTest:<br>";
		try{
			$ExistCount = CollectRequestQueue::find()->where(['puid'=>5015,'product_id'=>'AUC3835422022074','platform'=>'cdiscount'])
				->andWhere(['status'=>['P','C']])->asArray()->one();
			print_r($ExistCount);
		}catch (\Exception $e) {
			echo $e->getMessage();
		}
		try{
			$new = new CollectRequestQueue();
		}catch (\Exception $e) {
			echo $e->getMessage();
		}
		$new->product_id = 'AUC3835422022074';
		$new->platform = 'cdiscount';
		$new->subsite = '';
		$new->field_list =json_encode(['product_id','img','title','description','brand']);
		$new->callback_function = 'eagle\modules\listing\helpers\CdiscountOfferSyncHelper::webSiteInfoToDb($puid,$prodcutInfo,1,$seller);';
		$new->puid =5015;
		$new->create_time =TimeUtil::getNow();
		$new->update_time =TimeUtil::getNow();
		$new->status ="P"; // pending
		$new->priority = 1;
		$new->addi_info = '';
		try{
			$new->save();
		}catch (\Exception $e) {
			echo $e->getMessage();
		}
		exit();
	}
	
	/**
	 * ??????????????????????????????CD??????
	 * @param  $uid
	 * @param  $start	??????????????????
	 * @param  $end		??????????????????
	 * @param  $state	?????????????????????????????????(?????????????????????   ???????????????,??????)
	 * @param  $account	???????????????????????????(?????????????????????   ???????????????,??????)
	 */
	public function actionGetOrderByContoller($uid,$start,$end,$state,$account=''){
		$account_query = SaasCdiscountUser::find()->where("is_active='1' and uid= $uid");
		if($account!=='')
			$account_query->andWhere(['username'=> $account]);
		
		$SAASCDISCOUNTUSERLIST = $account_query->all();
		
		echo "\n <br>YS1 start to fetch for unfuilled uid=$uid ... ";
		if (empty($uid)){
			echo "<br>uid false";
			exit();
		}
 
		$state = explode(',', $state);
		$state_mapping =[
			'W'=>'WaitingForShipmentAcceptation',
			'C'=>'CancelledByCustomer',
			'S'=>'Shipped',
			'R1'=>'RefusedBySeller',
			'AC'=>'AutomaticCancellation',
			'SR'=>'ShipmentRefusedBySeller',
			'R2'=>'RefusedNoShipment',
			];
		$status = [];
		foreach ($state as $s){
			if(isset($state_mapping[$s]))
				$status[] = $state_mapping[$s];
		}
		
		try {
			foreach($SAASCDISCOUNTUSERLIST as $cdiscountAccount ){
				$updateTime = $end;
				$onwTimeUTC = $end;
				$sinceTimeUTC = $start;
	
				$getOrderCount = 0;
				//update this cdiscount account as last order retrieve time
				$cdiscountAccount->last_order_retrieve_time = $updateTime;
						
				if (empty($cdiscountAccount->last_order_success_retrieve_time) or $cdiscountAccount->last_order_success_retrieve_time=='0000-00-00 00:00:00'){
					//????????????????????????????????????????????????do
					echo "\n uid=$uid haven't initial_fetched !";
				}else{
					//start to get unfulfilled orders
					$orders = CdiscountOrderHelper::getOrdersByCondition($cdiscountAccount['token'], $sinceTimeUTC, $onwTimeUTC,false,$status);
						
					if (empty($orders['success'])){
						echo "\n fail to connect proxy  :".$orders['message'];
						$cdiscountAccount->save();
						continue;
					}

					if(isset($orders['proxyResponse']['orderList']['s_Body']['s_Fault']['faultstring'])){
						echo "\n".$orders['proxyResponse']['orderList']['s_Body']['s_Fault']['faultstring'];
						$cdiscountAccount->order_retrieve_message = $orders['proxyResponse']['orderList']['s_Body']['s_Fault']['faultstring'];
					}
					if (!empty ($orders['proxyResponse']['success'])){
						if(isset($orders['proxyResponse']['orderList']['s_Body']['GetOrderListResponse']['GetOrderListResult']['OrderList']['Order']))
							echo "\n isset ".'orders[proxyResponse][orderList][s_Body][GetOrderListResponse][GetOrderListResult][OrderList][Order]';
						//sync cdiscount info to cdiscount order table
						if(!empty($orders['proxyResponse']['orderList']['s_Body']['GetOrderListResponse']['GetOrderListResult']['OrderList']['Order'])){
							echo "\n !empty ".'orders[proxyResponse][orderList][s_Body][GetOrderListResponse][GetOrderListResult][OrderList][Order]';
							$rtn = CdiscountOrderHelper::_InsertCdiscountOrder($orders['proxyResponse']['orderList']['s_Body']['GetOrderListResponse']['GetOrderListResult']['OrderList']['Order'] , $cdiscountAccount);
							if($rtn['success']){
								$cdiscountAccount->last_order_success_retrieve_time = $updateTime;
							}
						}else{
							echo "\n get none order";
							$cdiscountAccount->last_order_success_retrieve_time = $updateTime;
						}//end of GetOrderListResult empty or not
								
					}else{
						if (!empty ($orders['proxyResponse']['message'])){
							echo "\n uid = $uid proxy error  :".$orders['proxyResponse']['message'].$cdiscountAccount['token'];
						}else{
							echo "\n uid = $uid proxy error  : not any respone message".$cdiscountAccount['token'];
						}
					}
					//end of getting orders from cdiscount server
								
					if (!empty ($orders['proxyResponse']['message'])){
						$cdiscountAccount->order_retrieve_message = $orders['proxyResponse']['message'];
					}else
						$cdiscountAccount->order_retrieve_message = '';//to clear the error msg if last attemption got issue
	
					if (!$cdiscountAccount->save()){
						echo "\n failure to save cdiscount account info ,error:";
						echo "\n uid:".$cdiscountAccount['uid']."error:". print_r($cdiscountAccount->getErrors(),true);
					}else{
						echo "\n CdiscountAccount model save !";
					}
				}
			}//end of each cdiscount user account
		} catch (\Exception $e) {
			echo "\n cronAutoFetchRecentOrderList Exception:".$e->getMessage();
		}
	}
	
	/**
	 * ?????????????????????CD?????????generate???v2???
	 */
	public function actionGenerateSrcOrderToOmsIfNotExisting(){
		
		$start=empty($_REQUEST['s'])?$_REQUEST['s']:'';
		$end=empty($_REQUEST['e'])?$_REQUEST['s']:'';
		$uids = $_REQUEST['uids'];
		$uid_arr = explode(',', $uids);
		
		foreach ($uid_arr as $uid){
			if (empty($uid))
				continue;
 
			
			$query = CdiscountOrder::find()->where(" (SELECT COUNT( 1 ) AS num FROM od_order_v2 WHERE cdiscount_order.`ordernumber` = od_order_v2.order_source_order_id) =0 ");
			if(!empty($start))
				$query->where(" creationdate>='$start' ");
			if(!empty($end))
				$query->where(" creationdate<='$end' ");
			
			$omsNotExistingOrders = $query->asArray()->all();
			
			$user_cd_accounts = SaasCdiscountUser::find()->where(['uid'=>$uid])->asArray()->all();
			$cd_accounts = [];
			foreach ($user_cd_accounts as $a){
				$cd_accounts[$a['username']] = $a;
			}
			
			foreach ($omsNotExistingOrders as $nE){
				if(!isset($cd_accounts[$nE['seller_id']]))
					continue;
				
				$formated_order_data = CdiscountOrderHelper::formatSrcOrderDataToOmsOrder($nE, $cd_accounts[$nE['seller_id']],true);
				$items = CdiscountOrderDetail::find()->where(['ordernumber'=>$nE['ordernumber']])->asArray()->all();
				$formated_order_detail_data = CdiscountOrderHelper::formatSrcOrderItemsDataToOmsOrder($items, $nE['ordernumber']);
				
				$subtotal=0; //???????????????
				if(isset($formated_order_data['total_amount']) && isset($formated_order_data['shipping_cost']) ){
					$subtotal = $formated_order_data['total_amount'] - $formated_order_data['shipping_cost'];
				}else
					$subtotal=$formated_order_detail_data['total_amount'];
				
				if(empty($formated_order_data['shipping_cost']))
					$formated_order_data['shipping_cost'] = $formated_order_detail_data['delivery_amonut']; //????????????????????????empty,?????????items??????????????????
				
				$formated_order_data['grand_total'] = $formated_order_data['subtotal'] + $formated_order_data['shipping_cost'] - $formated_order_data['discount_amount'] - $formated_order_data['commission_total'] ;//????????????
				$formated_order_data['items']=$formated_order_detail_data['items'];
				
				$reqInfo[$uid]=array_merge(OrderHelper::$order_demo,$formated_order_data);
				
				try{
					$ret =  OrderHelper::importPlatformOrder($reqInfo,-1);
				}catch(\Exception $e){
					$message = "uid=$uid importPlatformOrder fails. Exception error:".$e->getMessage()."data: \n ".print_r($reqInfo,true);
					echo $message;
				}
			}
		}
		
		exit();	
	}
	
	
	/**
	 * CD OMS dash-board
	 * @param string $user
	 * @return remix
	 */
	public function actionJobMonitor($user){
		if($user!=='eagle-liang')
			return $this->render('monitor',[]);
		
		$MonitoData = CdiscountOrderInterface::getMonitorData();
		return $this->render('monitor',[
				'data'=>$MonitoData,
			]);
			
		
	}
	
	public function actionUserOrderCount($user){
		if($user!=='eagle-liang')
			return $this->render('monitor',[]);
	
		$UserOrderCountDatas = CdiscountOrderInterface::getUserOrderCountDatas();
		return $this->render('_user_order_count',[
				'datas'=>$UserOrderCountDatas['count_datas'],
				'pages'=>$UserOrderCountDatas['pagination'],
				'tops'=>$UserOrderCountDatas['tops'],
				]);
			
	
	}

	/**
	 * ?????????????????????dash-board
	 * @param	int		$autoShow	???????????????0:???????????????1:????????????
	 * @return	mixed
	 */
	public function actionUserDashBoard($autoShow=1){
		$uid = \Yii::$app->user->id;
		if (empty($uid))
			exit('????????????!');
		$chartData['order_count'] = CdiscountOrderInterface::getChartDataByUid_Order($uid,10);
		$chartData['profit_count'] = CdiscountOrderInterface::getChartDataByUid_Profit($uid,10);
		$advertData = CdiscountOrderInterface::getAdvertDataByUid($uid,2);
		
		$autoShow = (int)$autoShow;
		if(!empty($autoShow)){//?????????????????????????????????????????????dashboard???????????????????????????????????????????????????oms?????????????????????????????????
			//??????????????????????????????next time,????????????now
			//$set_redis = \Yii::$app->redis->hset('CdiscountOms_DashBoard',"user_$uid".".next_show",date("Y-m-d H:i:s",time()));
		}else{//??????????????????dashboard?????????????????????????????????dashboard??????????????????4?????????
			//$set_redis = \Yii::$app->redis->hset('CdiscountOms_DashBoard',"user_$uid".".next_show",date("Y-m-d H:i:s",time()+3600*4));
			$set_redis = RedisHelper::RedisSet('CdiscountOms_DashBoard',"user_$uid".".next_show",date("Y-m-d H:i:s",time()+3600*4));
		}
		
		return $this->renderAjax('_dash_board',[
				'chartData'=>$chartData,
				'advertData'=>$advertData,
			]);
	}
	
	
	public function actionHideDashBoard(){
		$uid = \Yii::$app->user->id;
		if (empty($uid))
			return false;
		//$set_redis = \Yii::$app->redis->hset('CdiscountOms_DashBoard',"user_$uid".".next_show",date("Y-m-d H:i:s",time()+3600*4));
		$set_redis = RedisHelper::RedisSet('CdiscountOms_DashBoard',"user_$uid".".next_show",date("Y-m-d H:i:s",time()+3600*4));
		return $set_redis;
	}
	
	
	public function actionLiangTest(){
		if(empty($_REQUEST['pw']) || $_REQUEST['pw']!=='eagleasd123')
			exit('password error');
		if(empty($_REQUEST['act']))
			exit('act empty');
		$action = $_REQUEST['act'];
		switch ($action){
			case 'getEmailByOrderID':
				if(empty($_REQUEST['p1']) || empty($_REQUEST['p2'])){
					echo "parma1(uid) or parma2(order_id) undefined";
					break;
				}
				$uid = (int)$_REQUEST['p1'];
				$cdAccount = SaasCdiscountUser::find()->where(['uid'=>$uid])->asArray()->one();
				$orderid = $_REQUEST['p2'];
				$email = CdiscountOrderInterface::getEmailByOrderID($cdAccount, $orderid);
				echo $email;
				break;
			case 'orderDailySummary':
				if(empty($_REQUEST['p1'])){
					echo "parma1(summary_date) undefined";
					break;
				}
				$summary_date = $_REQUEST['p1'];
				CdiscountOrderInterface::cronCdiscountOrderDailySummary($summary_date);
				break;
			default:
				echo "non act default case";
				break;
		}
		exit();
	}
	
	public function actionTestHcSingShipped(){
		CdiscountInterface_Helper::hcCdiscountOrderSingShipped();
		exit();
	}
	
	/**
	 +----------------------------------------------------------
	 * CD????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lzhl 	2016/04/27				?????????
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
	
		$detail = CdiscountOrderHelper::getOrderSyncInfoDataList($status,$last_sync_time );
		
		//??????????????????????????????lrq20170828
		$account_data = \eagle\modules\platform\apihelpers\PlatformAccountApi::getPlatformAuthorizeAccounts('cdiscount');
		$selleruserids = array();
		foreach($account_data as $key => $val){
			$selleruserids[$key] = $val;
		}
		foreach($detail as $key => $val){
			if(!array_key_exists($key, $selleruserids)){
				unset($detail[$key]);
			}
		}
	
		if (!empty($_REQUEST['order_status']))
			$order_nav_key_word = $_REQUEST['order_status'];
		else
			$order_nav_key_word='';
		
		$counter = OrderHelper::getMenuStatisticData('cdiscount');
	
		return $this->renderAjax('order_sync',[
				'sync_list'=>$detail,
				'counter'=>$counter,
				]);
	}//end of actionOrderSyncInfo
	
	public function actionImportantChange(){
		return $this->renderPartial('_important_change',[]);
	}
	
	public function actionProxyTest(){
		$config = array('tokenid' => '613911a87ab444e5ab854bcca88ee7d9');
		$get_param['config'] = json_encode($config);
		$newBinding=false;
		$createtime = '2016-05-16T00:00:00';
		$endtime='2016-05-18T10:00:00';
		if($newBinding){
			if ($createtime !== ''){
				$params['begincreationdate'] = $createtime;
			}
			if ($endtime !== ''){
				$params['endcreationdate'] = $endtime;
			}
		}else{
			if ($createtime !== ''){
				$params['beginmodificationdate'] = $createtime;
			}
			if ($endtime !== ''){
				$params['endmodificationdate'] = $endtime;
			}
		}
		
		//$params['state'] = array();
		$params['state'] = CdiscountOrderHelper::$orderStatus;
		$get_param['query_params'] = json_encode($params);
		$rtn = $proxyResponse=CdiscountProxyConnectHelper::call_Cdiscount_api("getOrderList",$get_param,$post_params=array(),$timeout=600 );
		
	}
	
	public function actionTestT(){
		$rtn = OrderApiHelper::getOrderProductCostByOrderId('19190','KB8414082102C');
		print_r($rtn);
		exit();
	}
	
	public function actionTestOffer(){
		$rtn = CdiscountOfferSyncHelper::getOfferListPaginated(1,'gameboxamazon@gmail.com',[]);
		exit();
	}
	
	public function actionTestOpenApi(){
		$ct_rt = CdiscountOpenApi::getCdiscountProduct(['WOF2009849298898']);
		print_r($ct_rt);
		
		if (!empty($ct_rt['WOF2009849298898']['success']) && isset($ct_rt['WOF2009849298898']['product']) ){
			echo  "<br>thisStatus = C <br>;";
		}else{
			//????????????
			echo  "<br>thisStatus = F <br>;";
				
			$error_message = (!empty($ct_rt['message'])?$ct_rt['message']:"");
			echo  "<br>$error_message <br>;";
		}
		
		exit();
	}
	
	public function actionTestVip(){
		if(empty($_REQUEST['user']) || $_REQUEST['user']!=='ealge-liang')
			exit("???????????????");
		$rtn = CdiscountAccountsApiHelper::setCdAccountVipRank(1, 2);
		print_r($rtn);
		exit();
	}
	
	public function actionTest01(){
		$accounts = SaasCdiscountUser::find()->where("1")->orderBy("uid ASC")->all();
    	echo "\n get ".count($accounts)." accounts;";
    	$user_sellers=[];
    	foreach ($accounts as $account){
    		$user_sellers[$account->uid][] = $account->username;
    	}
    	
    	//print_r($user_sellers);
    	//exit();
    	foreach ($user_sellers as $uid=>$sellers){
    		$uid = $account->uid;
    		if(empty($uid))
    			continue;
    		 
    		echo "\n start to do data fix uid=$uid;";
    		//CdiscountOfferSyncHelper::DataFix_TerminatorHistory($uid);
    		CdiscountOfferSyncHelper::DataFix_OfferList($uid,$sellers);
    	}
    	echo "\n end foreach uid;";
	}
	
	/**
	 +----------------------------------------------------------
	 * ???????????? left menu ??????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lzhl 	2016/08/06				?????????
	 +----------------------------------------------------------
	 **/
	public function actionLeftMenuAutoLoad(){
		//????????? ???????????????????????? start
		$puid = \Yii::$app->user->identity->getParentUid();
		$tmpSellerIDList =  CdiscountAccountsApiHelper::listActiveAccounts($puid);
		$cdAccountList = [];
		foreach($tmpSellerIDList as $tmpSellerRow){
			$cdAccountList[] = $tmpSellerRow['username'];
		}
		/*
		//??????????????????
		if (!empty($cdAccountList)){
			//????????? ????????????????????????
			$counter = CdiscountOrderHelper::getMenuStatisticData(['selleruserid'=>$cdAccountList]);
		}else{
			$counter = [];
		}
		*/

		if (!empty($_REQUEST['selleruserid'])){
			$counter = OrderHelper::getMenuStatisticData('cdiscount',['selleruserid'=>$_REQUEST['selleruserid']]);
			$counter['todayorder'] = OdOrder::find()->where(['and',['order_source'=>'cdiscount'],['>=','order_source_create_time',strtotime(date('Y-m-d'))],['<','order_source_create_time',strtotime('+1 day')] ])
			->andWhere(['selleruserid'=>$_REQUEST['selleruserid']])->count();
			$counter['sendgood'] = OdOrder::find()->where(['order_source'=>'cdiscount','order_source_status'=>'WaitingForShipmentAcceptation'])->andwhere('order_status < 300')
			->andWhere(['selleruserid'=>$_REQUEST['selleruserid']])->count();
			$claimOrderIDs = MessageCdiscountApiHelper::claimOrderIDs($_REQUEST['selleruserid']);
			$counter['issueorder'] = OdOrder::find()->where(['order_source'=>'cdiscount','issuestatus'=>'IN_ISSUE'])->andWhere(['selleruserid'=>$_REQUEST['selleruserid']])->count();
		}else{
			if(!empty($cdAccountList)){
				$counter = OrderHelper::getMenuStatisticData('cdiscount',['selleruserid'=>$cdAccountList]);
				$counter['todayorder'] = OdOrder::find()->where(['and',['order_source'=>'cdiscount','selleruserid'=>$cdAccountList],['>=','order_source_create_time',strtotime(date('Y-m-d'))],['<','order_source_create_time',strtotime('+1 day')] ])->count();
				$counter['sendgood'] = OdOrder::find()->where(['order_source'=>'cdiscount','selleruserid'=>$cdAccountList,'order_source_status'=>'WaitingForShipmentAcceptation'])->andwhere('order_status < 300')->count();
				$claimOrderIDs = MessageCdiscountApiHelper::claimOrderIDs();
				$counter['issueorder'] = OdOrder::find()->where(['order_source'=>'cdiscount','selleruserid'=>$cdAccountList,'issuestatus'=>'IN_ISSUE'])->count();
			}else{
				//?????????????????????
				$counter=[];
				$claimOrderIDs=[];
			}
		}
		$counter['newmessage'] = empty($claimOrderIDs['unRead'])?0:count($claimOrderIDs['unRead']['orderIds']);
		//$counter['issueorder'] = empty($claimOrderIDs['openStatus'])?0:count($claimOrderIDs['openStatus']);
			  
		
		
		
		exit( json_encode($counter) );
	}
	
	/*
	 * ????????????????????????
	 * @author		lzhl 	2016/08/18		?????????
	 */
	public function actionManualSyncOrder(){
		AppTrackerApiHelper::actionLog("Oms-cdiscount", "/order/cdiscount-order/manual-sync-order");
		
		$puid = \Yii::$app->user->identity->getParentUid();
		$sync_job_info = CdiscountOrderInterface::checkSyncJobState($puid);
		
		return $this->renderAjax('_manual-sync-order',[
				'sync_job_info'=>$sync_job_info,
			]);
	}
	
	public function actionAjaxCheckSyncInfo(){
		$puid = \Yii::$app->user->identity->getParentUid();
		$sync_job_info = CdiscountOrderInterface::checkSyncJobState($puid);
		exit(json_encode($sync_job_info));
	}
	
	/*
	 * ??????????????????
	 * @author		lzhl 	2016/08/18		?????????
	 */
	public function actionManualSyncOrderSubmit(){
		AppTrackerApiHelper::actionLog("Oms-cdiscount", "/order/cdiscount-order/manual-sync-order-submit");
		
		if(empty($_REQUEST['id']))
			exit(json_encode(['success'=>false,'message'=>'????????????????????????']));
		
		$site_id = (int)$_REQUEST['id'];
		$puid = \Yii::$app->user->identity->getParentUid();
		
		$this_saas_account = SaasCdiscountUser::find()->where(['site_id'=>$site_id,'uid'=>$puid])->one();
		if(empty($this_saas_account))
			exit(json_encode(['success'=>false,'message'=>'??????????????????????????????????????????']));
		
		if(empty($_REQUEST['sync_order_time'])){
			exit(json_encode(['success'=>false,'message'=>'?????????????????????????????????']));
		}
		$sync_order_time = trim($_REQUEST['sync_order_time']);
		if(!preg_match('/^\d{4}\-\d{1,2}-\d{1,2}$/',$sync_order_time)){
			exit(json_encode(['success'=>false,'message'=>'???????????????????????? ????????????']));
		}
		$begincreationdate = date("Y-m-d H:m:s",strtotime($sync_order_time)-3600*24*2);
		$endcreationdate = date("Y-m-d H:m:s",strtotime($sync_order_time)+3600*24*2);
		
		
		if($this_saas_account->sync_status!=='R'){
			try{
				$rtn = CdiscountOrderInterface::markSaasAccountOrderSynching($this_saas_account, 'M',$begincreationdate,$endcreationdate);
				if(!$rtn['success']){
					$journal_id = SysLogHelper::InvokeJrn_Create("OMS",__CLASS__, __FUNCTION__ , array('Exception',$rtn['message']));
					exit(json_encode(['success'=>false,'message'=>'??????????????????????????????,???????????????']));
				}
			}catch (\Exception $e) {
				$journal_id = SysLogHelper::InvokeJrn_Create("OMS",__CLASS__, __FUNCTION__ , array('Exception',$e->getMessage()));
				exit(json_encode(['success'=>false,'message'=>'?????????????????????????????????']));
			}
		}else{
			if( time() - strtotime($this_saas_account->last_order_retrieve_time) < 3600 ){
				$rtn = CdiscountOrderInterface::markSaasAccountOrderSynching($this_saas_account, 'M',$begincreationdate,$endcreationdate);
				if(!$rtn['success']){
					$journal_id = SysLogHelper::InvokeJrn_Create("OMS",__CLASS__, __FUNCTION__ , array('Exception',$rtn['message']));
					exit(json_encode(['success'=>false,'message'=>'??????????????????????????????,???????????????']));
				}
			}else 
				exit(json_encode(['success'=>false,'message'=>'?????????????????????????????????????????????????????????????????????']));
		}
		
		exit(json_encode(['success'=>true,'message'=>'????????????,???????????????????????????']));
	}
	
	
	/**
	 * ??????????????????
	 */
	public function actionSyncOrderReady(){
		$puid =\Yii::$app->subdb->getCurrentPuid();
		// ????????????
		$accounts = SaasCdiscountUser::find()->where([
				'uid'=>$puid,'is_active'=>1,
				])->all();
		
		//??????????????????????????????lrq20170828
		$account_data = \eagle\modules\platform\apihelpers\PlatformAccountApi::getPlatformAuthorizeAccounts('cdiscount');
		foreach($accounts as $key => $val){
			if(!in_array($val->store_name, $account_data)){
				unset($accounts[$key]);
			}
		}
		
		AppTrackerApiHelper::actionLog("Oms-cdiscount", "/order/cdiscount-order/sync-order-ready");
		return $this->renderAuto('start-sync',[
				'accounts'=>$accounts
				]);
	}
	
	/*
	 * ????????????????????????
	 * ????????????stm??????????????????????????????????????????????????????queue???????????????????????????????????????
	 * ????????????cd????????????????????????????????????????????????????????????
	 */
	public function actionGetQueue(){
		AppTrackerApiHelper::actionLog("Oms-cdiscount", "/order/cdiscount-order/get-queue");
		$result = [
			'success'=>true,
			'status'=>'P',
			'progress'=>0,
			'message'=>'',
		];
		
		if(empty($_REQUEST['site_id'])){
			$result['success'] = false;
			$result['message'] ='?????????????????????';
			return $this->renderJson($result);
		}
			
		$site_id = (int)$_REQUEST['site_id'];
		$puid = \Yii::$app->user->identity->getParentUid();
		
		try{
			$this_saas_account = SaasCdiscountUser::find()->where(['site_id'=>$site_id,'uid'=>$puid])->one();
			if(empty($this_saas_account)){
				$result['success']=false;
				$result['message'] ='??????????????????????????????????????????';
				return $this->renderJson($result);
			}
			
			if(empty($_REQUEST['sync_order_time'])){
				$result['success']=false;
				$result['message'] ='?????????????????????????????????';
				return $this->renderJson($result);
			}
			
			$sync_order_time = trim($_REQUEST['sync_order_time']);
			if(!preg_match('/^\d{4}\-\d{1,2}-\d{1,2}$/',$sync_order_time) && !preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/',$sync_order_time)){
				$result['success']=false;
				$result['message'] ='???????????????????????? ????????????';
				return $this->renderJson($result);
			}
			$begincreationdate = date("Y-m-d H:m:s",strtotime($sync_order_time)-3600*24*2);
			$endcreationdate = date("Y-m-d H:m:s",strtotime($sync_order_time)+3600*24*2);
			
			if($this_saas_account->sync_status!=='R'){
				$rtn = CdiscountOrderInterface::markSaasAccountOrderSynching($this_saas_account, 'M',$begincreationdate,$endcreationdate);
				if(!$rtn['success']){
					$journal_id = SysLogHelper::InvokeJrn_Create("OMS",__CLASS__, __FUNCTION__ , array('Exception',$rtn['message']));
					$result['success']=false;
					$result['message'] ='??????????????????????????????,???????????????';
					return $this->renderJson($result);
				}
			}else{
				$result['success']=false;
				$result['message'] ='?????????????????????????????????????????????????????????????????????';
				return $this->renderJson($result);
			}
		}catch(\Exception $e){
			$journal_id = SysLogHelper::InvokeJrn_Create("OMS",__CLASS__, __FUNCTION__ , array('Exception',$e->getMessage()));
			$result['success']=false;
			$result['message'] =$e->getMessage();
			$result['code'] =$e->getCode();
		}
		
		if($result['success'])
			$journal_id = SysLogHelper::InvokeJrn_Create("OMS",__CLASS__, __FUNCTION__ , array('success',$puid,$site_id));
		return $this->renderJson($result);
	}
	
	/*
	 * ??????????????????
	 */
	public function actionGetProgress(){
		$result = [
			'success'=>true,
			'status'=>'P',
			'progress'=>0,
			'message'=>'',
		];
		
		if(empty($_REQUEST['site_id'])){
			$result['success'] = false;
			$result['message'] ='?????????????????????';
			return $this->renderJson($result);
		}
		$site_id = (int)$_REQUEST['site_id'];
		$puid = \Yii::$app->user->identity->getParentUid();
		
		try{
			$this_saas_account = SaasCdiscountUser::find()->where(['site_id'=>$site_id,'uid'=>$puid])->one();
			if(empty($this_saas_account)){
				$result['success']=false;
				$result['message'] ='??????????????????????????????????????????';
				return $this->renderJson($result);
			}	
			
			if($this_saas_account->sync_status=='R'){
				$result['success']=true;
				$result['status']='P';
				$result['progress']=0;
				$result['message']='???????????????';
			}elseif($this_saas_account->sync_status=='F'){
				$result['success']=true;
				$result['status']='F';
				$result['progress']=0;
				$result['message']='????????????';
			}elseif($this_saas_account->sync_status=='C'){
				$addi_info = json_decode($this_saas_account->sync_info,true);
				if(!empty($addi_info['order_count'])) $result['progress']=(int)$addi_info['order_count'];
				$result['success']=true;
				$result['status']='C';
				$result['message']='';
			}
		}catch(\Exception $e){
			$journal_id = SysLogHelper::InvokeJrn_Create("OMS",__CLASS__, __FUNCTION__ , array('Exception',$e->getMessage()));
			$result['success']=false;
			$result['message'] =$e->getMessage();
			$result['code'] =$e->getCode();
			
		}
		
		return $this->renderJson($result);
	}
}

?>