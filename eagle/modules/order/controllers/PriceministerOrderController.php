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
use yii\data\Sort;
use eagle\models\SaasPriceministerUser;
use eagle\modules\inventory\models\Warehouse;
use eagle\modules\order\helpers\OrderTagHelper;
use eagle\modules\util\helpers\StandardConst;
use eagle\modules\order\helpers\PriceministerOrderInterface;
use eagle\modules\order\helpers\OrderHelper;
use eagle\models\SysCountry;
use eagle\models\EbayCountry;
//use eagle\modules\listing\models\PriceministerOfferList;
//use eagle\modules\listing\helpers\PriceministerOfferSyncHelper;
use eagle\modules\app\apihelpers\AppTrackerApiHelper;
use eagle\modules\order\helpers\PriceministerOrderHelper;
use eagle\modules\order\models\PriceministerOrder;
use eagle\modules\order\models\PriceministerOrderDetail;
use eagle\modules\listing\helpers\PriceministerProxyConnectHelper;
use eagle\modules\html_catcher\helpers\HtmlCatcherHelper;
use Qiniu\json_decode;
use yii\helpers\VarDumper;
use eagle\modules\util\helpers\ConfigHelper;
use eagle\modules\catalog\models\Product;
use eagle\modules\catalog\helpers\ProductHelper;
use eagle\modules\util\helpers\TimeUtil;
use eagle\modules\util\helpers\DataStaticHelper;
use eagle\modules\util\helpers\SysLogHelper;
use eagle\modules\order\apihelpers\OrderApiHelper;
use eagle\modules\order\helpers\OrderUpdateHelper;
use eagle\modules\order\helpers\OrderGetDataHelper;
use eagle\modules\order\helpers\OrderBackgroundHelper;
use eagle\modules\platform\apihelpers\PlatformAccountApi;
use eagle\modules\util\helpers\RedisHelper;

class PriceministerOrderController extends \eagle\components\Controller{
	public $enableCsrfValidation = false;
	
	/**
	 * priceminister??????????????????
	 * 
	 * 
	 */
	public function actionList(){
		//check????????????
		$permission = \eagle\modules\permission\apihelpers\UserApiHelper::checkPlatformPermission('priceminister');
		if(!$permission)
			return $this->render('//errorview_no_close',['title'=>'????????????:????????????','error'=>'?????????????????????????????????!']);
		 
		// ??????user ???puid ????????? priceminister ????????????
		$puid = \Yii::$app->user->identity->getParentUid();
		$priceministerUsersDropdownList = array();
		/*
		$priceministerUsers = SaasPriceministerUser::find()->where(['uid'=>$puid])->asArray()->all();
		foreach ($priceministerUsers as $priceministerUser){
			$priceministerUsersDropdownList[$priceministerUser['username']] = $priceministerUser['store_name'];
		}
		*/
		$tmpSellerIDList = PlatformAccountApi::getPlatformAuthorizeAccounts('priceminister');//??????????????????	lzhl 2017-03
		$accountList = [];
		foreach($tmpSellerIDList as $sellerloginid=>$store_name){
			$accountList[] = $sellerloginid;
			$priceministerUsersDropdownList[$sellerloginid] = $store_name;
		}
		//??????????????????????????????????????????????????????
		$test_userid=\eagle\modules\tool\helpers\MirroringHelper::$test_userid;
		if(empty($accountList)){
			if (!in_array($puid,$test_userid['yifeng'])){
				//????????????????????????
				return $this->render('//errorview_no_close',['title'=>'????????????:????????????','error'=>'???????????????????????? Priceminister ??????????????????!']);
			}
		}
		
		$data=OdOrder::find();	
		//????????? unActive??????????????????
		if (!in_array($puid,$test_userid['yifeng']))
			$data->andWhere(['selleruserid'=>$accountList]);
		
		if (isset($_REQUEST['profit_calculated'])){
			if($_REQUEST['profit_calculated']==1){
				$data->andWhere(" `profit` IS NOT NULL ");
			}elseif($_REQUEST['profit_calculated']==2){
				$data->andWhere(" `profit` IS NULL ");
			}
		}
		
		//??????????????????????????????????????????????????????page size ???	//lzhl	2016-11-30
		$page_url = '/'.\Yii::$app->controller->module->id.'/'.\Yii::$app->controller->id.'/'.\Yii::$app->controller->action->id;
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
		
		$addi_condition = ['order_source'=>'priceminister'];
		$addi_condition['sys_uid'] = \Yii::$app->user->id;
		$addi_condition['selleruserid_tmp'] = $accountList;
		
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
		
		$tmp_REQUEST_text['where']=empty($data->where)?Array():$data->where;
		$tmp_REQUEST_text['orderBy']=empty($data->orderBy)?Array():$data->orderBy;		
		$omsRT = OrderApiHelper::getOrderListByConditionOMS($tmp_REQUEST,$addi_condition,$data,$pageSize,false,'all');
		if (!empty($_REQUEST['order_status'])){
			//???????????????????????????code
			$op_code = $_REQUEST['order_status'];
		}
		if (!empty($omsRT['showsearch'])) $showsearch = 1;
		
		$pagination = new Pagination([
				'defaultPageSize' => 50,
				'pageSize' => $pageSize,
				'totalCount' => $data->count(),
				'pageSizeLimit'=>[5,200],//????????????????????????
				'params'=>$_REQUEST,
				]);
		$models = $data->offset($pagination->offset)
		->limit($pagination->limit)
		->all();
		
		//yzq 2017-2-21, to do bulk loading the order items, not to use lazy load
		OrderHelper::bulkLoadOrderItemsToOrderModel($models);
		OrderHelper::bulkLoadOrderShippedModel($models);
		
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
	    
	    $usertabs = Helper_Array::toHashmap(Helper_Array::toArray(Usertab::find()->all()),'id','tabname');

	    //??????????????????
	    $counter=[];
	    $hitCache = "NoHit";
	    $cachedArrAll = array();
	    $uid = \Yii::$app->user->id;
	    $stroe = 'all';
	    if(!empty($_REQUEST['selleruserid']))
	    	$stroe  = trim($_REQUEST['selleruserid']);
	    
	    $isParent = \eagle\modules\permission\apihelpers\UserApiHelper::isMainAccount();
	    if($isParent){
	    	$gotCache = RedisHelper::getOrderCache2($puid,$uid,'priceminister',"MenuStatisticData",$stroe) ;
	    }else{
	    	if (!empty($_REQUEST['selleruserid'])){
	    		$gotCache = RedisHelper::getOrderCache2($puid,$uid,'priceminister',"MenuStatisticData",$_REQUEST['selleruserid']) ;
	    	}else{
	    		$gotCache = RedisHelper::getOrderCache2($puid,$uid,'priceminister',"MenuStatisticData",'all') ;
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
	    		$counter = OrderHelper::getMenuStatisticData('priceminister',['selleruserid'=>$_REQUEST['selleruserid']]);
	    	}else{
	    		if(!empty($accountList)){
	    			$counter = OrderHelper::getMenuStatisticData('priceminister',['selleruserid'=>$accountList]);
	    		}else{
	    			//?????????????????????
	    			$counter=[];
	    			$claimOrderIDs=[];
	    		}
	    	}
	    	//save the redis cache for next time use
	    	if (!empty($_REQUEST['selleruserid'])){
	    		RedisHelper::setOrderCache2($puid,$uid,'priceminister',"MenuStatisticData",$_REQUEST['selleruserid'],$counter) ;
	    	}else{
	    		RedisHelper::setOrderCache2($puid,$uid,'priceminister',"MenuStatisticData",'all',$counter) ;
	    	}
	    }
	    /*
	    //??????????????????
	    if (!empty($_REQUEST['selleruserid'])){
	    	$counter = OrderHelper::getMenuStatisticData('priceminister',['selleruserid'=>$_REQUEST['selleruserid']]);
	    	$counter['todayorder'] = OdOrder::find()->where(['and',['order_source'=>'priceminister'],['>=','order_source_create_time',strtotime(date('Y-m-d'))],['<','order_source_create_time',strtotime('+1 day')] ])
	    		->andWhere(['selleruserid'=>$_REQUEST['selleruserid']])->count();
	    	$counter['sendgood'] = OdOrder::find()->where(['order_source'=>'priceminister','order_source_status'=>'current'])->andwhere('order_status < 300')
	    		->andWhere(['selleruserid'=>$_REQUEST['selleruserid']])->count();
	    	//$claimOrderIDs = MessageCdiscountApiHelper::claimOrderIDs($_REQUEST['selleruserid']);
	    	//$counter['issueorder'] = OdOrder::find()->where(['issuestatus'=>'IN_ISSUE'])->andWhere(['selleruserid'=>$_REQUEST['selleruserid']])->count();
	    }else{
	    	if(!empty($priceministerUsersDropdownList)){
	    		$selleruserid_arr = array_keys($priceministerUsersDropdownList);
	    		$counter = OrderHelper::getMenuStatisticData('priceminister',['selleruserid'=>$selleruserid_arr]);
	    		$counter['todayorder'] = OdOrder::find()->where(['and',['order_source'=>'priceminister','selleruserid'=>$selleruserid_arr],['>=','order_source_create_time',strtotime(date('Y-m-d'))],['<','order_source_create_time',strtotime('+1 day')] ])->count();
	    		$counter['sendgood'] = OdOrder::find()->where(['order_source'=>'priceminister','selleruserid'=>$selleruserid_arr,'order_source_status'=>'current'])->andwhere('order_status < 300')->count();
	    		//$claimOrderIDs = MessageCdiscountApiHelper::claimOrderIDs();
	    		//$counter['issueorder'] = OdOrder::find()->where(['issuestatus'=>'IN_ISSUE'])->count();
	    	}else{
	    		$counter=[];
	    		//$claimOrderIDs=[];
	    	}
	    }
	    //$counter['newmessage'] = empty($claimOrderIDs['unRead'])?0:count($claimOrderIDs['unRead']['orderIds']);
	    //$counter['issueorder'] = empty($claimOrderIDs['openStatus'])?0:count($claimOrderIDs['openStatus']);
	    */	  
		
		$counter['new'] = OdOrder::find()->where(['order_source' => 'priceminister','order_source_status'=>'new','isshow'=>'Y'])->count();
		/*
		$counter[OdOrder::STATUS_NOPAY] = OdOrder::find()->where(['order_source' => 'priceminister','order_status'=>OdOrder::STATUS_NOPAY])->count();
	    $counter[OdOrder::STATUS_PAY] = OdOrder::find()->where(['order_source' => 'priceminister' ,'order_status'=>OdOrder::STATUS_PAY ])->count();
	    $counter[OdOrder::STATUS_WAITSEND] = OdOrder::find()->where(['order_source' => 'priceminister','order_status'=>OdOrder::STATUS_WAITSEND ])->count();
	    $counter['all'] = OdOrder::find()->where(['order_source' => 'priceminister' ])->count();
	    $counter['guaqi'] = OdOrder::find()->where(['order_source' => 'priceminister','is_manual_order' => '1'])->count();
	    
	    $counter[OdOrder::STATUS_SHIPPED] = OdOrder::find()->where(['order_source' => 'priceminister' , 'order_status'=>OdOrder::STATUS_SHIPPED])->count();
	    $counter[OdOrder::STATUS_SHIPPING] = OdOrder::find()->where(['order_source' => 'priceminister' , 'order_status'=>OdOrder::STATUS_SHIPPING])->count();
	    $counter[OdOrder::STATUS_CANCEL] = OdOrder::find()->where(['order_source' => 'priceminister' , 'order_status'=>OdOrder::STATUS_CANCEL])->count();
	    
	    $counter[OdOrder::EXCEP_HASMESSAGE] = OdOrder::find()->where(['order_source' => 'priceminister' , 'exception_status'=>OdOrder::EXCEP_HASMESSAGE])->count();
	    $counter[OdOrder::EXCEP_HASNOSHIPMETHOD] = OdOrder::find()->where(['order_source' => 'priceminister' , 'exception_status'=>OdOrder::EXCEP_HASNOSHIPMETHOD])->andWhere(" order_status > 300 ")->count();
	    $counter[OdOrder::EXCEP_PAYPALWRONG] = OdOrder::find()->where(['order_source' => 'priceminister' , 'exception_status'=>OdOrder::EXCEP_PAYPALWRONG])->count();
	    $counter[OdOrder::EXCEP_SKUNOTMATCH] = OdOrder::find()->where(['order_source' => 'priceminister' , 'exception_status'=>OdOrder::EXCEP_SKUNOTMATCH])->andWhere(" order_status > 300 ")->count();
	    $counter[OdOrder::EXCEP_NOSTOCK] = OdOrder::find()->where(['order_source' => 'priceminister' , 'exception_status'=>OdOrder::EXCEP_NOSTOCK])->andWhere(" order_status > 300 ")->count();
	    $counter[OdOrder::EXCEP_WAITMERGE] = OdOrder::find()->where(['order_source' => 'priceminister' , 'exception_status'=>OdOrder::EXCEP_WAITMERGE])->andWhere(" order_status > 300 ")->count();
	    */
	     
	    //??????????????????
	    $countryArr = [];
	    //$countryArr['FR'] = StandardConst::getNationChineseNameByCode('FR').'(FR)';
	    $countrycodes=OdOrder::getDb()->createCommand("select distinct `consignee_country_code` from ".OdOrder::tableName()." WHERE `order_source`='priceminister' ")->queryAll();
	    foreach ($countrycodes as $countrycode){
	    	$countryArr[$countrycode['consignee_country_code']] = StandardConst::getNationChineseNameByCode($countrycode['consignee_country_code']).'('.$countrycode['consignee_country_code'].')';
	    }
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
	    AppTrackerApiHelper::actionLog("Oms-priceminister", "/order/priceminister/list");//??????priceminister??????????????????
	    
	    //????????????
	    if (!empty($_REQUEST['order_status']))
	    	$order_nav_key_word = $_REQUEST['order_status'];
	    else
	    	$order_nav_key_word='';
	    
	    $search = array('is_comment_status'=>'current');
	    
	    //tag ????????????
	    $allTagDataList = OrderTagHelper::getTagByTagID();
	    $allTagList = [];
	    foreach($allTagDataList as $tmpTag){
	    	$allTagList[$tmpTag['tag_id']] = $tmpTag['tag_name'];
	    }
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
	    $tmp_REQUEST_text['REQUEST']=$tmp_REQUEST;
	    $tmp_REQUEST_text['order_source']=$addi_condition;
	    $tmp_REQUEST_text['params']=empty($data->params)?Array():$data->params;
	    $tmp_REQUEST_text=base64_encode(json_encode($tmp_REQUEST_text));
	    
		return $this->render('list',array(
			'models' => $models,
		    'pages' => $pagination,
			'sort' => $sortConfig,
			'excelmodels'=>$excelmodels,
			'priceministerUsersDropdownList'=>$priceministerUsersDropdownList,
			'usertabs'=>$usertabs,
			'counter'=>$counter,
			'countryArr'=>$countryArr,
			'sysCountry'=>$sysCountry,
			'warhouseArr'=>$warhouseArr,
			'showsearch'=>$showsearch,
			'tag_class_list'=> OrderTagHelper::getTagColorMapping(),
			'order_nav_html'=>PriceministerOrderHelper::getPriceministerOmsNav($order_nav_key_word),
			'search'=>$search,
			'all_tag_list'=>$allTagList,
			'doarr'=>OrderHelper::getCurrentOperationList($op_code,'b') ,
			'doarr_one'=>OrderHelper::getCurrentOperationList($op_code,'s'),
			'product_infos'=>$product_infos,
			'search_condition'=>$tmp_REQUEST_text,
			'search_count'=>$pagination->totalCount,
		));
		
	}
	
	/**
	 * ????????????
	 * @author lzhl
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
				$item->quantity = $item_tmp['quantity'][$key];

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
			AppTrackerApiHelper::actionLog("Oms-priceminister", "/order/priceminister/edit-save");//??????priceminister????????????
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
		$countrycode=OdOrder::getDb()->createCommand('select consignee_country_code from '.OdOrder::tableName().' group by consignee_country_code')->queryColumn();
		$countrycode=array_filter($countrycode);
		//????????????????????????????????????????????????
		$sysCountry = [];
		$countryModels = SysCountry::find()->asArray()->all();
		foreach ($countryModels as $countryModel){
			$sysCountry[$countryModel['country_code']] = $countryModel['country_zh'];
		}
		
		AppTrackerApiHelper::actionLog("Oms-priceminister", "/order/priceminister-order/edit");//??????priceminister??????????????????
		
		return $this->render('edit',['order'=>$order,'countrys'=>$sysCountry]);
	}
	
	/**
	 * ????????????????????????????????????????????????,?????????????????????????????????????????????????????????
	 * @author lzhl
	 */
	//????????????OMS?????????????????????,???????????????
	public function actionSignshipped(){
		AppTrackerApiHelper::actionLog("Oms-priceminister", "/order/priceminister/signshipped");//??????priceminister??????????????????
		
		$tmpShippingMethod  = PriceministerOrderInterface::getShippingCodeNameMap();
		$tmpShippingMethod = DataStaticHelper::getUseCountTopValuesFor("PriceministerOms_ShippingMethod" ,$tmpShippingMethod );
		
		$priceministerShippingMethod = [];
		if(!empty($tmpShippingMethod['recommended'])){
			$priceministerShippingMethod += $tmpShippingMethod['recommended'];
			$priceministerShippingMethod[''] = '---??????/????????? ?????????---';
		}
		if(!empty($tmpShippingMethod['rest']))
			$priceministerShippingMethod += $tmpShippingMethod['rest'];
		
		if (\Yii::$app->request->getIsPost()){
			$orders = OdOrder::find()->where(['in','order_id',\Yii::$app->request->post()['order_id']])->andWhere("order_capture<>'Y'")->all();
			return $this->render('signshipped',['orders'=>$orders,'priceministerShippingMethod'=>$priceministerShippingMethod]);
		}else {
			$orders = OdOrder::find()->where(['in','order_id',\Yii::$app->request->get('order_id')])->andWhere("order_capture<>'Y'")->all();
			return $this->render('signshipped',['orders'=>$orders,'priceministerShippingMethod'=>$priceministerShippingMethod]);
		}
	}
	
	/**
	 * ????????????????????????????????????????????????,??????????????????
	 * @author lzhl
	 */
	//????????????OMS?????????????????????,???????????????
	public function actionSignshippedsubmit(){
		if (\Yii::$app->request->getIsPost()){
			AppTrackerApiHelper::actionLog("Oms-priceminister", "/order/priceminister/signshippedsubmit");//priceminister????????????????????????????????????
			$user = \Yii::$app->user->identity;
			$postarr = \Yii::$app->request->post();
			$tracker_provider_list  = PriceministerOrderInterface::getShippingCodeNameMap();
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
							$shipMethodName = $tracker_provider_list[$postarr['shipmethod'][$oid]];
						}else{
							$shipMethodName='';
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
							//?????????????????????????????????????????????	lzhl 2016-08-02
							DataStaticHelper::addUseCountFor("PriceministerOms_ShippingMethod", $postarr['shipmethod'][$oid],8);
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
	
	/*
	 * ??????priceminister??????????????????List??????
	 */
	public function actionViewOfferList(){
	}
	
	public function actionViewOffer($id){
	}
	
	public function actionPrintOffers(){
		return $this->render('print_offer');
	}
	
	/**
	 * ??????????????????(??????)
	 * @return string
	 */
	public function actionCloseReminder(){
		$uid = \Yii::$app->user->id;
		$ret = PriceministerOrderInterface::CloseReminder($uid);
		exit($ret);
	}
	
	/**
	 * ??????????????????????????????PM??????
	 * @param  $uid
	 * @param  $start	??????????????????
	 * @param  $end		??????????????????
	 * @param  $state	?????????????????????????????????(?????????????????????   ???????????????,??????)
	 * @param  $account	???????????????????????????(?????????????????????   ???????????????,??????)
	 */
	public function actionGetOrderByContoller($uid,$start,$end,$account='',$type){
		$account_query = SaasPriceministerUser::find()->where("is_active='1' and uid= $uid");
		if($account!=='')
			$account_query->andWhere(['username'=> $account]);
		
		$saasPmUserList = $account_query->all();
		
		echo "\n <br>YS1 start to fetch for unfuilled uid=$uid ... ";
		if (empty($uid)){
			echo "<br>uid false";
			exit();
		}


		try {
			foreach($saasPmUserList as $priceministerAccount ){
				$updateTime = $end;
				$onwTimeUTC = $end;
				$sinceTimeUTC = $start;
	
				$getOrderCount = 0;
				//update this priceminister account as last order retrieve time
				$priceministerAccount->last_order_retrieve_time = $updateTime;
						
				if (empty($priceministerAccount->last_order_success_retrieve_time) or $priceministerAccount->last_order_success_retrieve_time=='0000-00-00 00:00:00'){
					//????????????????????????????????????????????????do
					echo "\n uid=$uid haven't initial_fetched !";
				}else{
					//start to get unfulfilled orders
					$apiReturn = PriceministerOrderHelper::getOrdersByCondition($priceministerAccount['token'],$priceministerAccount['username'],$sinceTimeUTC, $onwTimeUTC,$type);
					if (empty($apiReturn['success'])){//proxy error
						echo "\n fail to connect proxy  :".$apiReturn['message'];
						$priceministerAccount->$order_retrieve_message = $apiReturn['message'];
						$priceministerAccount->save();
						continue;
					}

					if(stripos($apiReturn['message'], 'Done,got sales for seller id:')===false){//api get order error
						echo "\n".$apiReturn['message'];
						$priceministerAccount->order_retrieve_message = $apiReturn['message'];
						$priceministerAccount->save();
						continue;
					}
					
					if (isset($apiReturn['orders'])){
						if(!empty($apiReturn['orders'])){
							echo "\n api return  ".count($apiReturn['orders'])." orders;" ;
							//sync priceminister info to priceminister order table
							if(!empty($apiReturn['seller_id']))
								$seller_id = $apiReturn['seller_id'];
							else 
								$seller_id = '';
							$rtn = PriceministerOrderHelper::_InsertPriceministerOrder($apiReturn['orders'],$priceministerAccount,$seller_id);
							if($rtn['success']){//insert to oms done
								$priceministerAccount->last_order_success_retrieve_time = $updateTime;
							}else{//insert to oms failed
								
							}
						}
						else{
							echo "\n api return  null orders;" ;
						}
						$priceministerAccount->last_order_success_retrieve_time = $updateTime;
					}else{
						$priceministerAccount->order_retrieve_message = '????????????api,?????????orders????????????!';
					}
					//end of getting orders from priceminister server
					if (!$priceministerAccount->save()){
						echo "\n failure to save priceminister account info ,error:";
						echo "\n uid:".$priceministerAccount['uid']."error:". print_r($priceministerAccount->getErrors(),true);
					}else{
						echo "\n PriceministerAccount model save !";
					}
				}
			}//end of each priceminister user account
		} catch (\Exception $e) {
			echo "\n cronAutoFetchRecentOrderList Exception:".$e->getMessage();
		}
	}
	
	/**
	 * Accept Or Refuse simple order item
	 */
	public function actionOperateNewSale(){
		$rtn['success']=true;
		$rtn['message']='';
		$uid = \Yii::$app->user->id;
		if(empty($uid)){
			$rtn['success']=false;
			$rtn['message']='????????????';
			exit(json_encode($rtn));
		}
		if(empty($_POST['operate'])){
			$rtn['success']=false;
			$rtn['message']='????????????????????????';
			exit(json_encode($rtn));
		}
		else {
			$operate = trim($_POST['operate']);
			$operate = strtolower($operate);
		}
		if($operate!=='accept' && $operate!=='refuse'){
			$rtn['success']=false;
			$rtn['message']='????????????????????????????????????????????????';
			exit(json_encode($rtn));
		}
		
		if(empty($_POST['itemid']) || trim($_POST['itemid'])==''){
			$rtn['success']=false;
			$rtn['message']='????????????id?????????';
			exit(json_encode($rtn));
		}else 
			$itemid = trim($_POST['itemid']);
		if(empty($_POST['sellerid'])|| trim($_POST['sellerid'])==''){
			$rtn['success']=false;
			$rtn['message']='??????????????????????????????????????????';
			exit(json_encode($rtn));
		}else
			$sellerid = trim($_POST['sellerid']);
		
		$itemModle = OdOrderItem::find()->where(['order_source_order_item_id'=>$itemid])->one();
		if(empty($itemModle)){
			$rtn['success']=false;
			$rtn['message']='<br>????????????????????????????????????';
			exit(json_encode($rtn));
		}
		
		$result = PriceministerOrderHelper::AcceptOrRefuseItem($uid, $itemid, $sellerid, $operate);
		if(!$result['success']){
			$rtn['success']=false;
			if(stripos($result['message'],'CAPTURED - Required status : REQUESTED')){
				$msg = '????????????????????????????????????????????????/???????????????';
			}elseif(stripos($result['message'],'Current status : EMPTIED ??? Required status : REQUESTED')){
				$msg = '????????????????????????????????????????????????/???????????????';
			}
			else $msg = $result['message'];
			$rtn['message'].=$msg;
		}else{
			$rtn = PriceministerOrderHelper::updateItemStatusAfterAcceptOrRefuse($uid, $itemid, $sellerid, $operate);
		}
		exit(json_encode($rtn));
	}
	
	/**
	 * ????????????????????? new sale
	 */
	public function actionAcceptOrRefuseOrders(){
		$rtn['success']=true;
		$rtn['message']='';
		$uid = \Yii::$app->user->id;
		if(empty($uid)){
			$rtn['success']=false;
			$rtn['message']='????????????';
			exit(json_encode($rtn));
		}
		if(empty($_REQUEST['act'])){
			$rtn['success']=false;
			$rtn['message']='????????????????????????';
			exit(json_encode($rtn));
		}
		else {
			$action = trim($_REQUEST['act']);
			$action = strtolower($action);	
		}
		if($action!=='accept' && $action!=='refuse'){
			$rtn['success']=false;
			$rtn['message']='????????????????????????????????????????????????';
			exit(json_encode($rtn));
		}
		$order_ids = explode($_REQUEST['orderids'], ';');
		foreach ($order_ids as $orderid){
			if(empty($orderid))
				continue;
			$odOrder = OdOrder::findOne($orderid);
			if(empty($odOrder)){
				$rtn['success']=false;
				$rtn['message'].='<br>???????????????,??????????????????:'.$orderid;
				continue;
			}
			if(empty($odOrder['selleruserid'])){
				$rtn['success']=false;
				$rtn['message'].='<br>????????????????????????,????????????!?????????:'.$odOrder->order_source_order_id;
				continue;
			}
			$result = PriceministerOrderHelper::AcceptOrRefuseOrders($uid, $odOrder['selleruserid'],$orderid,$action);
			if(!$result['success']){
				$rtn['success']=false;
				$rtn['message'].=$result['message'];
			}
		}
		exit(json_encode($rtn));
	}
	
	/**
	 * CD OMS dash-board
	 * @param string $user
	 * @return remix
	 */
	public function actionJobMonitor($user){
		if($user!=='eagle-liang')
			return $this->render('monitor',[]);
		
		$MonitoData = PriceministerOrderInterface::getMonitorData();
		return $this->render('monitor',[
				'data'=>$MonitoData,
			]);
			
		
	}
	
	public function actionUserOrderCount($user){
		if($user!=='eagle-liang')
			return $this->render('monitor',[]);
	
		$UserOrderCountDatas = PriceministerOrderInterface::getUserOrderCountDatas();
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
		$chartData['order_count'] = PriceministerOrderInterface::getChartDataByUid_Order($uid,10);
		$chartData['profit_count'] = PriceministerOrderInterface::getChartDataByUid_Profit($uid,10);
		$advertData = PriceministerOrderInterface::getAdvertDataByUid($uid,2);
		
		$autoShow = (int)$autoShow;
		if(!empty($autoShow)){//?????????????????????????????????????????????dashboard???????????????????????????????????????????????????oms?????????????????????????????????
			//??????????????????????????????next time,????????????now
			//$set_redis = \Yii::$app->redis->hset('CdiscountOms_DashBoard',"user_$uid".".next_show",date("Y-m-d H:i:s",time()));
		}else{//??????????????????dashboard?????????????????????????????????dashboard??????????????????4?????????
			//$set_redis = \Yii::$app->redis->hset('CdiscountOms_DashBoard',"user_$uid".".next_show",date("Y-m-d H:i:s",time()+3600*4));
			$set_redis = RedisHelper::RedisSet('PriceministerOms_DashBoard',"user_$uid".".next_show",date("Y-m-d H:i:s",time()+3600*4));
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
		//$set_redis = \Yii::$app->redis->hset('PriceministerOms_DashBoard',"user_$uid".".next_show",date("Y-m-d H:i:s",time()+3600*4));
		$set_redis = RedisHelper::RedisSet('PriceministerOms_DashBoard',"user_$uid".".next_show",date("Y-m-d H:i:s",time()+3600*4));
		return $set_redis;
	}
	
	/*
	 * ???????????????????????????????????????
	 */
	public function actionSyncOneOrderStatus(){
		$order_id = empty($_REQUEST['order_id'])?'':trim($_REQUEST['order_id']);
		
		if(empty($order_id)){
			$rtn['success'] = false;
			$rtn['message'] = '??????????????????!';
			
		}else
			$rtn = PriceministerOrderHelper::SyncOrderItemStatusByOrder($order_id);
		exit(json_encode($rtn));
	}
	
	/*
	 * ??????call cron?????????????????????/????????? ???????????????
	 */
	public function actionSyncAllUnClosedOrderStatus(){
		$uid = \Yii::$app->user->id;
		$rtn = PriceministerOrderHelper::userSyncOrderItemStatus($uid);
		exit(json_encode($rtn));
	}
	
	
	/*
	 * ??????call ??????????????????
	*/
	public function actionHcOrderStatus(){
		$uid = \Yii::$app->user->id;
		echo "\n start to hc order status for uid=$uid;";
    		
    	$orders = OdOrder::find()->where(['order_source'=>'priceminister'])->andWhere("order_status<500")->all();
    	echo "\n query ".count($orders)." orders;";
    	$counter = 0;
    	foreach ($orders as $od){
    		$rtn = PriceministerOrderHelper::SyncOrderItemStatusByOrder($od->order_id,$uid);
    		if($rtn['success'])
    			$counter++;
    	}
    	echo "\n hc $counter orders;";
	}
	
	/**
	 +----------------------------------------------------------
	 * PM????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh 	2016/05/27				?????????
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
	
		$detail = PriceministerOrderHelper::getOrderSyncInfoDataList($status,$last_sync_time );
		
		//??????????????????????????????lrq20170828
		$account_data = \eagle\modules\platform\apihelpers\PlatformAccountApi::getPlatformAuthorizeAccounts('priceminister');
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
	
		$counter = OrderHelper::getMenuStatisticData('priceminister');
	
		return $this->renderAjax('order_sync',[
				'sync_list'=>$detail,
				'counter'=>$counter,
				]);
	}//end of actionOrderSyncInfo
	
	/*
	 * ????????????/?????? ????????????
	 */
	public function actionImportantChange(){
		return $this->renderPartial('_important_change',[]);
	}
	
	public function actionSetAutoAcceptOrder(){
		
		if(empty($_REQUEST['auto_accept'])) $autoAccept = 'false';
		else $autoAccept = $_REQUEST['auto_accept'];
		
		if($autoAccept!=='true' && $autoAccept!=='false')
			exit(json_encode(['success'=>false,'message'=>'????????????????????????????????????']));
		//$calculateSales = ConfigHelper::getConfig("PriceministerOrder/AutoAccept",'NO_CACHE');
		$set = ConfigHelper::setConfig("PriceministerOrder/AutoAccept",$autoAccept);
		if(!$set)
			exit(json_encode(['success'=>false,'message'=>'????????????!']));
		else
			exit(json_encode(['success'=>true,'message'=>'????????????!']));
	}
	
	public function actionWebOrderDailySummary(){
		$puid = \Yii::$app->user->identity->getParentUid();
		$time = empty($_REQUEST['time'])?TimeUtil::getNow():$_REQUEST['time'];
		PriceministerOrderInterface::cronPriceministerOrderDailySummary($time,$puid);
		exit();
	}
	
	public function actionTest(){
		$set = ConfigHelper::getConfig("PriceministerOrder/AutoAccept",'NO_CACHE');
		if(empty($set))
			echo "set = true";
		else 
			var_dump($set);
		exit();
	}
	
	
	/**
	 * ??????????????????
	 */
	public function actionSyncOrderReady(){
		$puid =\Yii::$app->subdb->getCurrentPuid();
		// ????????????
		$accounts = SaasPriceministerUser::find()->where([
				'uid'=>$puid,'is_active'=>1,
		])->all();
		//??????????????????????????????lrq20170828
		$account_data = \eagle\modules\platform\apihelpers\PlatformAccountApi::getPlatformAuthorizeAccounts('priceminister');
		foreach($accounts as $key => $val){
			if(!array_key_exists($val->username, $account_data)){
				unset($accounts[$key]);
			}
		}
		
		AppTrackerApiHelper::actionLog("Oms-cdiscount", "/order/priceminister-order/sync-order-ready");
		return $this->renderAuto('start-sync',[
			'accounts'=>$accounts
		]);
	}
	
	/*
	* ????????????????????????
	* ????????????stm??????????????????????????????????????????????????????queue???????????????????????????????????????
	* ????????????PM????????????????????????????????????????????????????????????
	*/
	public function actionGetQueue(){
		AppTrackerApiHelper::actionLog("Oms-cdiscount", "/order/priceminister-order/get-queue");
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
			$this_saas_account = SaasPriceministerUser::find()->where(['site_id'=>$site_id,'uid'=>$puid])->one();
			if(empty($this_saas_account)){
				$result['success']=false;
				$result['message'] ='??????????????????????????????????????????';
				return $this->renderJson($result);
			}
				
			if($this_saas_account->sync_status!=='R'){
				$rtn = PriceministerOrderHelper::markSaasAccountOrderSynching($this_saas_account, 'M');
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
			$this_saas_account = SaasPriceministerUser::find()->where(['site_id'=>$site_id,'uid'=>$puid])->one();
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