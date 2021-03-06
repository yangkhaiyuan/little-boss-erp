<?php
/**
 * @link http://www.witsion.com/
 * @copyright Copyright (c) 2014 Yii Software LLC
 * @license http://www.witsion.com/
 */
namespace eagle\modules\tracking\helpers;

use yii;
use yii\data\Pagination;
use eagle\modules\util\helpers\GoogleHelper;
use eagle\modules\tracking\models\Tracking;
use eagle\modules\util\models\GlobalLog;
use eagle\modules\util\helpers\SQLHelper;
use eagle\modules\util\helpers\RedisHelper;
use eagle\modules\util\helpers\HttpHelper;
use eagle\modules\tracking\helpers\CarrierTypeOfTrackNumber;
use eagle\modules\util\helpers\UserLastActionTimeHelper;
use eagle\modules\tracking\models\TrackerApiQueue;
use eagle\modules\tracking\models\TrackerApiSubQueue;
use eagle\modules\tracking\models\TrackerGenerateRequest2queue;
use eagle\modules\util\helpers\StandardConst;
use eagle\modules\util\helpers\TranslateHelper;
use eagle\modules\util\helpers\ExcelHelper;
use eagle\modules\util\helpers\ConfigHelper;
use eagle\modules\util\helpers\ResultHelper;
use yii\base\Exception;
use eagle\modules\util\helpers\OperationLogHelper;
use eagle\modules\platform\apihelpers\PlatformAccountApi;
use yii\caching\DbDependency;
use eagle\models\SaasAliexpressUser;
use eagle\modules\carrier\apihelpers\CarrierApiHelper;
use eagle\models\SaasCdiscountUser;
use eagle\models\SaasLazadaUser;
use eagle\models\SaasDhgateUser;
use eagle\modules\platform\apihelpers\EbayAccountsApiHelper;
use eagle\modules\message\helpers\MessageHelper;
use eagle\modules\util\helpers\TimeUtil;
use eagle\models\SaasWishUser;
use eagle\modules\order\helpers\OrderTrackerApiHelper;
use eagle\modules\message\models\MsgTemplate;
use eagle\modules\message\helpers\TrackingMsgHelper;
use eagle\modules\tracking\models\Tag;
use eagle\modules\order\models\OdOrder;
use eagle\modules\util\helpers\SysLogHelper;
use eagle\modules\order\apihelpers\OrderApiHelper;
use eagle\modules\app\apihelpers\AppApiHelper;
use yii\helpers\Url;
use eagle\modules\dash_board\helpers\DashBoardHelper;
use common\helpers\Helper_Array;
use eagle\models\SaasAmazonUser;
use eagle\modules\permission\helpers\UserHelper;
use eagle\models\SaasEbayUser;
use eagle\modules\amazoncs\helpers\AmazoncsHelper; 

/**
 * BaseHelper is the base class of module BaseHelpers.
 *
 */
class TrackingHelper {
//??????
	public static $TRACKER_FILE_LOG = false;
	const CONST_1= 1; //Sample
	private static $Insert_Api_Queue_Buffer = array();
	private static $mainQueueVersion = '';	
	
	private static $subQueueVersion = '';
	private static $putIntoTrackQueueVersion = '';
	
	public static $vip_tracker_excel_import_limit = [
		'1150'=>5000,
		'3110'=>5000,
	];
	
	public static $tracker_import_limit = 50;
	public static $tracker_guest_import_limit = 10;
	
	private static $EXCEL_COLUMN_MAPPING = [
	"A" => "ship_by",//????????????
	"B" => "ship_out_date",//??????????????? 
	"C" => "track_no",//?????????
	"D" => "order_id",//?????????
	"E" => "delivery_fee",//?????? 
	];
	
	public static $EXPORT_EXCEL_FIELD_LABEL = [
	"ship_by" => "?????????",
	"ship_out_date" => "????????????",
	"track_no" => "?????????",
	"order_id" => "?????????",
	"delivery_fee" => "??????",
	"status"=>"????????????",
	'total_days'=>"????????????",
	'to_nation'=>'???????????????',
	'last_event_date'=>"????????????",
	'last_event'=>'????????????',
	'tags'=>'??????',
	'remark'=>'??????',
	];
	
	/*Data Conversion:
	 * For those tracking number comes from OMS, and without ship out date, try to ask OMS and 
	 * complete the ship out date.
	 * OBSOLLETE
	 * */
	static public function fixOMSTrackingShipOutDate(){
		$rtn['data'] = array();
		$rtn['success'] = true;
		$puid = \Yii::$app->subdb->getCurrentPuid();
		$rtn['puid'] = $puid;
		$aTrackings = Tracking::find()
			->andWhere("source = 'O' and state<>'complete' and  (ship_out_date is null or ship_out_date='') " )
			->all();
		
		 foreach ($aTrackings as $aTracking){
		//step 3.0????????????order id ???????????????????????? order date ??????4?????????????????????????????????????????????????????????		
			if (empty($aTracking->ship_out_date) ){
				//????????????????????? ?????????????????????oms???????????? order date???????????????ship out date ???????????????
				$getOmsOrder = self::getOrderDetailFromOMSByTrackNo($aTracking->track_no);
				if ($getOmsOrder['success']){
					$order_time = !empty($getOmsOrder['order']['paid_time'])?$getOmsOrder['order']['paid_time']:(!empty($getOmsOrder['order']['1428548196'])?$getOmsOrder['order']['1428548196']:"");
					if ($order_time <> ""){
						$order_time = date('Y-m-d H:i:s',$order_time);
						$aTracking->ship_out_date = $order_time;
						$aTracking->save();
						$rtn['data'][]=$aTracking->track_no."-".$order_time;
					}
				}
			}//end of when ship out date is empty
		}//end of each
		return $rtn;
	}
	
	/*
	 +---------------------------------------------------------------------------------------------
	 * ??????Tracking?????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     $puid				puid, default 0 = all
	 * @param     $keyword			???????????????text
	 * @param     action type       string of user selected,default "" = get all
     * @param     $date_from		?????????????????????xxx??????
	 * @param     $date_to          ?????????????????????xxx??????
	 * @param     $sort             ????????????field
	 * @param     $order            ????????????
	 * @param     $pageSize         ??????????????????????????????40
	 * @param     $pageNo         ??????????????????????????????1
	 * @param     $params           =array ('')
	 * @return    array( success=>true/false
               			data => array of rows
		 	   			action_types =>array('Excel??????','????????????','?????????????????????')
		 	   			puids => array('1'=>199.'2'=>50) //id=>total_records 
		 				total_rows=> 500		 		
	       				)
	*/
	static public function getUserActionTrackList($puid=0,$keyword='',$action_type='',$date_from='',$date_to='', $sort='' , $order='' , $pageSize = 40,$pageNo=1,$params=array() )
	{	$rtn['data'] = array();
		$rtn['success'] = true;
		$action_type_array = ['Excel??????','????????????','????????????','????????????'];
		$rtn['success'] = true;
		$rtn['action_types'] = $action_type_array;
		$query = GlobalLog::find();
		if(empty($sort)){
			$sort = 'create_time';
			$order = 'desc';
		}
	
		$condition = " remark like '%??????%' and remark  not like '%backdoor%' "; //??????????????????puid ?????????
		$condition_puid =' and 1 ';
		
		//??????puid = 0?????????for ??????????????????????????????0???????????????for????????????????????????
		if ($puid <> 0){
			$condition_puid .= " and (remark like '%?????? $puid %'  )";
		}
		
		//??????keyword???????????????????????????????????????
		if(!empty($keyword)){
			//??????keyword??????????????????SQL??????
			$keyword = str_replace("'","",$keyword);
			$keyword = str_replace('"',"",$keyword);
			$condition .= " and (remark like '%$keyword%'  )";
		}
		
		//??????from????????????to????????????????????????filter
		if(!empty($date_from)){
			//??????keyword??????????????????SQL??????
			$date_from = str_replace("'","",$date_from);
			$date_from = str_replace('"',"",$date_from);
			$condition .= " and ( date(create_time) >='$date_from' )";
		}
		if(!empty($date_to)){
			//??????keyword??????????????????SQL??????
			$date_to = str_replace("'","",$date_to);
			$date_to = str_replace('"',"",$date_to);
			$condition .= " and ( date(create_time)<= '$date_to' )";
		}
		
		//??????state???????????????????????????????????????
		foreach ($params as $fieldName=>$val){
			if(!empty($val)){
				//??????keyword??????????????????SQL??????
				$val = str_replace("'","",$val);
				$val = str_replace('"',"",$val);
				$val_array = explode(",",$val);
				$condi_internal =" and ( 0 ";
				
				foreach ($val_array as $aVal){
					$condi_internal .= " or $fieldName='$aVal'";
				}
				
				$condi_internal .= ")";
				
				$condition .= $condi_internal;
			}
		}//end of each filter

		//action_type: ['Excel??????','????????????','????????????','????????????']
		if (!empty($action_type)){
			if ($action_type == 'Excel??????'){
				$condition .= " and remark like '%by excel%'";
			}
			if ($action_type == '????????????'){
				$condition .= " and remark like '%????????????%'";
			}
			if ($action_type == '????????????'){
				$condition .= " and remark like '%????????????????????????%'";
			}
			if ($action_type == '????????????'){
				$condition .= " and remark like '%????????????%'";
			}
		}
		
		$rtn['condition'] = $condition.$condition_puid;
		$rtn['total_rows'] = $query->andWhere($condition.$condition_puid)->count();
		$rtn['data'] = $query
			->andWhere($condition.$condition_puid)
			->offset(($pageNo - 1 )*$pageSize) 
			->limit( $pageSize)
			->orderBy(" $sort $order")
			->asArray()
			->all();

		foreach ($rtn['data'] as &$row){
			$row['remark'] =  str_ireplace('??????????????????,', '', $row['remark']);
		}
		
		//try to work out how many puid for this condition and each puid has how many records
		$command = Yii::$app->db->createCommand("select substring(remark,9,LOCATE(' ', remark,9) -9 ) as puid,count(*) as record_count  from ut_global_log where  $condition  group by substring(remark,9,LOCATE(' ', remark,9) -9 )") ;
		$puids_arr = $command->queryAll();
		
		$rtn['puids'] = array();
		foreach ($puids_arr as $aRow){
			$rtn['puids'][$aRow['puid']] =  $aRow['record_count'];
		}	 
		 return $rtn;
	}
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????Tracking???Listing
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     $keyword			???????????????text
	 * @param     $params           ?????????????????????fields????????????field name???????????????????????????????????????????????????????????????
	 *                              ?????? array( state=>'initial,normal',
	 *                                         status=>'shipping',
	 *                                         source=>'O,M',
	 *                                         platform =>... ,
	 *                                         batch_no =>
	 *                                         hasComment =>'Y'/'N'
	 *                                         mark_handled =>'Y'/'N',
	 *                                         deleted =>'Y'/'N',
	 *                                       )				
	 * @param     $date_from		?????????????????????xxx??????
	 * @param     $date_to          ?????????????????????xxx??????	 
	 * @param     $sort             ????????????field
	 * @param     $order            ????????????
	 * @param     $pageSize         ??????????????????????????????40
	 +---------------------------------------------------------------------------------------------
	 * @return						$data
	 *
	 * @invoking					TrackingHelper::getListDataByCondition();
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/2/9				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function getListDataByCondition($keyword='',$params=array(),$date_from='',$date_to='', $sort='' , $order='' , $pageSize = 50,$noPagination = false  )
	{	 
		$query = Tracking::find();
		$date_filter_field_name = 'ship_out_date';
		
		//kh20160218 start ????????????????????????
		if (isset($params['export_track_no_list'])){
			//??????export_track_no_list ???????????????????????????????????????????????????excel 
			$export_track_no_list = $params['export_track_no_list'];
			unset($params['export_track_no_list']);// ??????????????? ?????????logic????????? 
		}
		//kh20160218 end   ????????????????????????
		if (isset($params['export_track_id_list'])){
			//??????export_track_no_list ???????????????????????????????????????????????????excel
			$export_track_id_list = $params['export_track_id_list'];
			unset($params['export_track_id_list']);// ??????????????? ?????????logic?????????
		}
		
		
		// $params pos????????????????????????
		if (!empty($params['pos'])){
			//?????????????????? = RPF ,  ?????????????????? = RRJ ,  ????????????=DE, ????????????????????? = RGE
			//??????????????????????????? last_event_date
			if (in_array($params['pos'], ['RPF' , 'RGE','DF','RRJ'])){
				$date_filter_field_name = 'last_event_date';
			}
			
			unset ($params['pos']);
			//????????????????????????,??????????????????????????????????????????????????? ??????
			$query->andWhere(" `first_event_date` IS NOT NULL  and (`first_event_date` <> `last_event_date`) ");
		}else if (@$params['status'] == 'no_info,checking'){
			//kh20160119 ???????????? ?????????5??????????????????????????? ??? ship_out_date ?????????????????? ??? ?????????????????? ????????????
			$date_filter_field_name = 'create_time';
		}
		//Pagination ???????????????Post??????get?????????page number???????????????offset
		$pagination = new Pagination([
				'totalCount'=> $query->count(),
				'defaultPageSize'=> 50,
				'pageSize'=> $pageSize,
				'pageSizeLimit'=>  [5,  ( $noPagination ? 50000 : 200 )  ],
				]);
		
		$data['pagination'] = $pagination;
		
		if(empty($sort)){
			$sort = $date_filter_field_name.' desc , create_time desc';
			$order = '';
		}
	
		$condition=' 1 ';
		//??????keyword???????????????????????????????????????
		if(!empty($keyword)){
			//??????keyword??????????????????SQL??????
			$keyword = str_replace("'","",$keyword);
			$keyword = str_replace('"',"",$keyword);
			$condition .= " and (order_id like '$keyword' or track_no like '$keyword' )";
		}
		
		//??????from????????????to????????????????????????filter
		if(!empty($date_from)){
			//??????keyword??????????????????SQL??????
			$date_from = str_replace("'","",$date_from);
			$date_from = str_replace('"',"",$date_from);
			$condition .= " and ( $date_filter_field_name >='$date_from' )";
		}
		if(!empty($date_to)){
			//??????keyword??????????????????SQL??????
			$date_to = str_replace("'","",$date_to);
			$date_to = str_replace('"',"",$date_to);
			$condition .= " and ( $date_filter_field_name<= '$date_to' )";
		}
		
		//??????state???????????????????????????????????????
		$bindVals = array();
		
		if(isset($params['page'])) unset($params['page']);
		if(isset($params['pre-page'])) unset($params['pre-page']);
		
		foreach ($params as $fieldName=>$val){
			if(!empty($val)){
			 	
				if($fieldName == 'is_send'){
					//??????keyword??????????????????SQL??????
					$val = str_replace("'","",$val);  $val = str_replace('"',"",$val);
					$condi_internal = " and ( ( status in ( 'received' ,  'platform_confirmed') and received_notified='$val') ".
							" or (status = 'arrived_pending_fetch' and pending_fetch_notified='$val') ".
							" or (status = 'delivery_failed' and delivery_failed_notified='$val') ".
							" or (status = 'rejected' and rejected_notified='$val') ".
							" or (status = 'shipping' and shipping_notified='$val') )";
					
				}elseif($fieldName == 'tagid'){
					//??????keyword??????????????????SQL??????
					$val = str_replace("'","",$val);  $val = str_replace('"',"",$val);
					$condi_internal = " and id in ( select tracking_id from lt_tracking_tags where tag_id = '$val')";
				}elseif ($fieldName == 'hasComment'){
					//??????keyword??????????????????SQL??????
					$val = str_replace("'","",$val);  $val = str_replace('"',"",$val);
					if ($val == "Y")
						$condi_internal  = " and remark <> '' and remark is not null ";

					if ($val == "N")
						$condi_internal  = " and (remark = '' or remark is null )";	
				}elseif($fieldName == 'deleted'){
					if ($val == "N")
						$condi_internal  = " and state <> 'deleted'";
					if ($val == "Y")
						$condi_internal  = " and state ='deleted'";
				}
				//???????????? ????????????	2017-10-31	lzhl
				elseif($fieldName=='stay_days'){
					if(!is_array($val)){
						$condi_internal = " and stay_days=$val ";
					}else{
						$condi_internal = '';
						foreach ($val as $vv){
							if(isset($vv['operator']) && isset($vv['days'])){
								$condi_internal .= " and stay_days".$vv['operator'].$vv['days']." ";
							}
						}
					}
				}//???????????????	 ????????????	2017-12-01	lzhl
				elseif($fieldName=='total_days'){
					if(!is_array($val)){
						$condi_internal = " and total_days=$val ";
					}else{
						$condi_internal = '';
						foreach ($val as $vv){
							if(isset($vv['operator']) && isset($vv['days'])){
								$condi_internal .= " and total_days".$vv['operator'].$vv['days']." ";
							}
						}
					}
				}else{
					//???????????????????????????
					$val_array = explode(",",$val);
					$condi_internal =" and ( 0 ";
				    
					$i = 10000;
					foreach ($val_array as $aVal){
						if (in_array($aVal,[TranslateHelper::t('????????????')])){
							$aVal = '';
						}
						$i++;
						$condi_internal .= (" or $fieldName=:". $fieldName.$i);
						$bindVals[":".$fieldName.$i] = $aVal;
					}
				
					$condi_internal .= ")";
				}
				
				$condition .= $condi_internal;
			}
		}//end of each filter
		
		$current_time=explode(" ",microtime()); $time1=round($current_time[0]*1000+$current_time[1]*1000);
		
		if (!empty($export_track_no_list)){
			//kh20160218 start   ????????????????????????
			$data ['condition'] = $export_track_no_list;
			$query->andWhere(['track_no'=>$export_track_no_list])
				->orderBy(" $sort $order  , id $order ");
			$uid = \Yii::$app->user->id;
			//if($uid==7394){
			//	$commandQuery = clone $query;
			//	echo $commandQuery->createCommand()->getRawSql();
			//}
			$data['data'] = $query->asArray()
				->all();
			//kh20160218 end   ????????????????????????
		}elseif(!empty($export_track_id_list)){
			$data ['condition'] = $export_track_id_list;
			$query->andWhere(['id'=>$export_track_id_list])
			->orderBy(" $sort $order  , id $order ");
			$uid = \Yii::$app->user->id;
			//if($uid==7394){
			//	$commandQuery = clone $query;
			//	echo $commandQuery->createCommand()->getRawSql();
			//}
			$data['data'] = $query->asArray()
			->all();
		}else{
			$data ['condition'] = $condition;
			$query->andWhere($condition,$bindVals)
				->offset($pagination->offset)
				->limit($pagination->limit)
				->orderBy(" $sort $order  , id $order ");
			$uid = \Yii::$app->user->id;
			//if($uid==7394){
			//	$commandQuery = clone $query;
			//	echo $commandQuery->createCommand()->getRawSql();
			//}
			$data['data'] = $query	->asArray()
				->all();
		}
		
		$current_time=explode(" ",microtime()); $time2=round($current_time[0]*1000+$current_time[1]*1000);
		
		// ??????sql    
	 //ysperformance
		 $tmpCommand = $query->createCommand();
// 		echo "<br>Used Query time ".($time2 - $time1)." ms<br>".$tmpCommand->getRawSql();
		$finalSql = $tmpCommand->getRawSql();
	 	
		//??????????????????data?????????search keyword????????????????????????????????????sample data
		//do not use sample data anyway
		/*
		if ( count($data['data']) == 0 and empty($keyword) and empty($date_from) and empty($date_to)){
			$existingCount = Tracking::find()->count();
			if ($existingCount == 0)
				$data = Tracking::getTrackingSampleData();
		}
		*/
		
		//??????????????????????????????keyword????????????????????????ignore ????????????????????????????????????track no only
		if ( count($data['data']) == 0 and !empty($keyword) ){
			$query = Tracking::find();
			$data['data'] = $query
				->where("order_id =:kw1 or track_no=:kw2",array(":kw1"=>$keyword,":kw2"=>$keyword))
				->offset($pagination->offset)
				->limit($pagination->limit)
				->orderBy(" $sort $order , id $order ")
				->asArray()
				->all();
		}
		
		foreach ($data['data'] as $key => $val) {
			$data['data'][$key]['status'] =Tracking::getChineseStatus($val['status']);
			$data['data'][$key]['state'] =Tracking::getChineseState($val['state']);
		}
		$pagination->totalCount = $query->count();
		//\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Online',"ListTracking ".print_r($params,true) . " SQL:$condition , order by  $sort $order "],"edb\global");
		//SysLogHelper::SysLog_Create('Tracking',__CLASS__, __FUNCTION__,'info',"ListTracking final SQL:$finalSql , order by  $sort $order ");
		//\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Online',"Got data ".print_r($data,true) ],"edb\global");
		return $data;
	}
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????????????? ???????????? ???????????? ??? Tracking Listing 
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     $keyword				???????????????text
	 * @param     $params           	?????????????????????fields????????????field name???????????????????????????????????????????????????????????????
	 *                              	?????? array( state=>'initial,normal',
	 *                                         status=>'shipping',
	 *                                         source=>'O,M',
	 *                                         platform =>... ,
	 *                                         batch_no =>
	 *                                       )
	 * @param     $date_from			?????????????????????xxx??????
	 * @param     $date_to         		?????????????????????xxx??????
	 * @param     $sort             	????????????field
	 * @param     $order            	????????????
	 * @param     $field_label_list     ????????????????????? (??????excel ?????? )
	 * @param	  $maxCount				?????????????????? ?????? ???????????? 5W???	
	 * @param	  $thispageLimit		??????????????? ??????(?????????????????? ?????????)
	 +---------------------------------------------------------------------------------------------
	 * @return						$data
	 *
	 * @invoking					TrackingHelper::getListDataByConditionNoPagination();
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/6/19				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function getListDataByConditionNoPagination($keyword='',$params=array(), $date_from='',$date_to='', $sort='' , $order='' , $field_label_list=[] , $maxCount = 50000 , $thispageLimit = 3000)
	{	
		$noPagination = true;
		$sumGetCount = 1;
		// ?????? excel ??? header
		$data [] = $field_label_list;
		$TrackingData = self::getListDataByCondition($keyword ,$params ,$date_from ,$date_to , $sort  , $order, $thispageLimit, $noPagination );
		
		//?????? field  
		if (!empty($field_label_list)){
			$totalCount = $TrackingData['pagination']->totalCount;
			//???????????? ???????????? ?????? , ???????????????????????? , ?????? ????????????
			if ($maxCount < $totalCount) $totalCount = $maxCount;
			
			//?????? ???????????????
			if ($totalCount > $thispageLimit ){
				$sumGetCount = ceil($totalCount / $thispageLimit);
			}
			//???model ??????????????? ???????????? , ????????????(model ?????? ?????????  ??????????????? , ?????????????????????)
			self::_convertTrackerDataToActiveData($TrackingData, $data, $field_label_list);
		}else{
			return $TrackingData;
		}
		
		//?????????????????????
		for ($i = 1; $i < $sumGetCount; $i++) {
			//????????????log
			/*
			if (isset(\Yii::$app->params["currentEnv"]) and \Yii::$app->params["currentEnv"]=="test" ){
				$logTimeMS1=TimeUtil::getCurrentTimestampMS();
				$logMemoryMS1 = (memory_get_usage()/1024/1024);
				echo __FUNCTION__.' step get all '.$i.'.1  :'.(memory_get_usage()/1024/1024). 'M<br>'; //test kh
			}
			*/
			//????????????
			$_GET['page'] =  $i+1;
			//???????????? ????????????
			$TrackingData = self::getListDataByCondition($keyword ,$params ,$date_from ,$date_to , $sort  , $order, $thispageLimit, $noPagination );
			if (!empty($TrackingData)){
				self::_convertTrackerDataToActiveData($TrackingData, $data, $field_label_list);
			}
			
			//????????????log
			/*
			if (isset(\Yii::$app->params["currentEnv"]) and \Yii::$app->params["currentEnv"]=="test" ){
				$logTimeMS2=TimeUtil::getCurrentTimestampMS();
				$logMemoryMS2 = (memory_get_usage()/1024/1024);
					
				echo __FUNCTION__.' step get all  '.$i.'.2  T=('.($logTimeMS2-$logTimeMS1).') and M='.($logMemoryMS2-$logMemoryMS1).'M and Now Memory='.(memory_get_usage()/1024/1024). 'M and total '.count($data).'<br>'; //test kh
				\Yii::info("get lt_tracking data  total=".count($data).",t2_1=".($logTimeMS2-$logTimeMS1).
						",memory=".($logMemoryMS2-$logMemoryMS1)."M ","file");
				
			}
			*/
		}
		return $data;
	}//end of getListDataByConditionNoPagination
	
	static public function getListDataByConditionByPagination($keyword='',$params=array(), $date_from='',$date_to='', $sort='' , $order='' , $field_label_list=[])
	{
		$noPagination = false;
		$thispageLimit = empty($_REQUEST['per-page'])?50:(int)$_REQUEST['per-page'];
		// ?????? excel ??? header
		$data [] = $field_label_list;
		$TrackingData = self::getListDataByCondition($keyword ,$params ,$date_from ,$date_to , $sort  , $order, $thispageLimit, $noPagination );
	
		//?????? field
		if (!empty($field_label_list)){
			//???model ??????????????? ???????????? , ????????????(model ?????? ?????????  ??????????????? , ?????????????????????)
			self::_convertTrackerDataToActiveData($TrackingData, $data, $field_label_list);
		}else{
			return $TrackingData;
		}
		return $data;
	}
	
	static private function _convertTrackerDataToActiveData(&$TrackingData , &$data ,&$field_label_list){
		foreach($TrackingData['data'] as &$oneTracking):
			
			//$EXPORT_EXCEL_FIELD_LABEL ??????????????????field  , array_flip????????????????????????field name
			foreach(array_flip($field_label_list) as $field_name){
				if ($field_name == 'last_event_date'){
					//??????????????????   '????????????'  ?????????
					if (in_array($oneTracking['status'],['received', '????????????' ]))
						$row['last_event_date'] = $oneTracking['last_event_date'];
					else{
						$row['last_event_date'] = '';
						//continue;
					}
					continue;
				}
				//??????????????????
				if (in_array($field_name, ['to_nation', 'from_nation'])){
					if (!empty($oneTracking[$field_name])){
						$row[$field_name] = self::autoSetCountriesNameMapping($oneTracking[$field_name]);
					}else{
						$row[$field_name] = $oneTracking[$field_name];
					}
					continue;
				}
				
				if ($field_name == 'total_days'){
					//?????????????????????total_days ??? 0 ????????? ?????? ?????????????????????????????????????????? ????????????
					if ($oneTracking['total_days'] <= 0  || in_array($oneTracking['status'],['platform_confirmed',  '???????????????' ]) )
						$row['total_days'] = "";
					else{
						$row[$field_name] = $oneTracking[$field_name];
					}
					
					continue;
				}
				//???????????? liang 2016-01-20
				if($field_name == 'last_event'){
					$row['last_event']='';
					if(!empty($oneTracking['all_event'])){
						$all_event = json_decode($oneTracking['all_event'],true);
						if(!empty($all_event)){
							$last_event = $all_event[0];
							$last_event_when = empty($last_event['when'])?'':$last_event['when'];
							$last_event_where = empty($last_event['where'])?'':base64_decode($last_event['where']);
							$last_event_what = empty($last_event['what'])?'':base64_decode($last_event['what']);
							$row['last_event']=$last_event_when.'  '.(empty($last_event_where)?'':$last_event_where.' - ').$last_event_what;
						}
					}	
					continue;
				}
				//?????? liang 2016-01-21
				if($field_name == 'remark'){
					$row['remark']='';
					if(!empty($oneTracking['remark'])){
						$remarks = json_decode($oneTracking['remark']);
						if(!empty($remarks)){
							foreach ($remarks as $r){
								$row['remark'].= (empty($r->who)?'??????':$r->who).'???'.(empty($r->when)?' ??? ???  ???':$r->when).'??????????????????'.(empty($r->what)?'':$r->what).';';
							}
						}
					}
					continue;
				}
				//tags liang 2016-01-21
				if($field_name == 'tags'){
					$row['tags']='N/A';
					$tag_data = TrackingTagHelper::getTrackingTagsByTrackId($oneTracking['id']);
					$tag_ids = [];
					foreach ($tag_data as $tracking_tag){
						$tag_ids[] = $tracking_tag['tag_id'];
					}
					$TagList = Tag::find()->where(['tag_id'=>$tag_ids])->asArray()->all();
					foreach ($TagList as $tag){
						if(empty($row['tags']) || $row['tags']=='N/A')
							$row['tags'] = $tag['tag_name'];
						else 
							$row['tags'] = $row['tags'].','.$tag['tag_name'];
					}
					continue;
				}
					
				//???????????????????????????field ????????? ?????????????????? row ???
				$row[$field_name] = $oneTracking[$field_name];
				//test if it is numeric, add " " in front of it, in case excel ???????????????
				if (is_numeric($row[$field_name]) and strlen($row[$field_name]) > 8 )
					$row[$field_name] = ' '.$row[$field_name];
					
			}
			
			// ????????????tracking ???????????????  $data_array ???
			$data [] = $row;
			// ????????????
			$oneTracking=[];
			unset($oneTracking);
			$row = [];
		endforeach;
		unset($TrackingData);
	}//end of _convertTrackerDataToActiveData
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ????????????Tracking???All Events?????????????????????????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param   tracking number     ?????????
	 * @param   lang				???????????????????????????zh-CN,zh-TW,zh-TW,en,fr ???
	 *                              "" ???????????????
	 +---------------------------------------------------------------------------------------------
	 * @return						array('success'=true,'message'='',allEvents=>array() )
	 *
	 * @invoking					TrackingHelper::getTrackingAllEvents($track_no, $lang ='');
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/2/9				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function getTrackingAllEvents($track_no, $lang =''){
		$rtn['message']="";
		$rtn['success'] = false;
		$rtn['allEvents'] = array();
		$now_str = date('Y-m-d H:i:s');

		if (empty($track_no) ){
			$rtn['message']="????????????Tracking No??????";
			$rtn['success'] = false;
			return $rtn;
		}
				
		$model = Tracking::find()->andWhere("track_no=:track_no",array(":track_no"=>$track_no))->one();
			
		//step 1: when not found such record, skip it
		if ($model == null){
			$rtn['message']="????????????Tracking No????????????$track_no";
			$rtn['success'] = false;
			return $rtn;
		}
		
		//step 2: ??????all events?????????????????????
		$allEvents = json_decode($model->all_event , true);
		if (empty($allEvents)) $allEvents = array();
		//$rtn['original'] = $allEvents;
		 
		//do the transalte
		if ($lang <> ''){
			$translated_Events = array();
			foreach ($allEvents as $anEvent){
				$anEvent['what'] = base64_decode($anEvent['what']);
				$anEvent['where'] = base64_decode($anEvent['where']);
				if ($anEvent['lang'] <>'' and substr( strtolower($anEvent['lang']),0,3) <>'zh-'){	
					$anEvent['where'] = GoogleHelper::google_translate(strtolower($anEvent['where']),$anEvent['lang'],$lang);
					//??????????????????????????????????????????????????????
					if (strtoupper( $anEvent['what'])  === $anEvent['what'])
						$anEvent['what'] = str_replace("post","Post",strtolower( $anEvent['what']));
					$anEvent['what'] = GoogleHelper::google_translate( $anEvent['what'] ,$anEvent['lang'],$lang);
				}
				$translated_Events[] = $anEvent;
			}
			$allEvents = $translated_Events;
		}//end of need translate
		
		$rtn['allEvents'] = $allEvents;
		return $rtn;
	}
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????Tracking??????????????????????????????,?????????????????????????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param   tracking_no_array   array of tracking no, e.g.: array('RGdfsdfsdf','RG342342','RG34234234') 
	 +---------------------------------------------------------------------------------------------
	 * @return						array('success'=true,'message'='')
	 *
	 * @invoking					TrackingHelper::markTrackingHandled($tracking_no_array);
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/2/9				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function markTrackingHandled($tracking_no_array){
		$rtn['message']="";
		$rtn['success'] = false;
		$now_str = date('Y-m-d H:i:s');
	
		//??????????????????????????????track no??????update
		foreach ($tracking_no_array as $track_no){
			if (!isset($track_no) or trim($track_no)==''){
		 		continue;
			}
			
			$model = Tracking::find()->andWhere("id=:id",array(":id"=>$track_no))->one();
			
			//step 1: when not found such record, skip it
			if ($model == null){
				continue;
			}
			
			//step 2 : when neither exception  nor uhshipped  , skip it
			if (!in_array(strtolower($model->state) ,['exception' , 'unshipped'] )){
				continue;
			}
			
			//step 3 : when mark_handled already equal to Y , skip it 
			if (strtoupper($model->mark_handled) == 'Y'){
				continue;
			}
			 
			//step 4, mark the flag as handled
			$model->update_time = $now_str;
			$model->mark_handled = "Y";
			
			//step 5: save the data to Tracking table
			if ( $model->save() ){//save successfull
				$rtn['success']=true;
				$rtn['message'] = TranslateHelper::t("????????????!?????????????????????????????????!") ;
			}else{
				$rtn['success']=false;
				$rtn['message'] .= TranslateHelper::t("ETRK014 ??????Tracking????????????") ;
				foreach ($model->errors as $k => $anError){
					$rtn['message'] .= "ETRK002". ($rtn['message']==""?"":"<br>"). $k.":".$anError[0];
				}
			}//end of save failed	
		}//end of each track number
		return $rtn;
	}//end of mark tracking handled
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????Tracking????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param   tracking_no_array   array of tracking no, e.g.: array('RGdfsdfsdf','RG342342','RG34234234') 
	 +---------------------------------------------------------------------------------------------
	 * @return						array('success'=true,'message'='')
	 *
	 * @invoking					TrackingHelper::markTrackingCompleted($tracking_no_array);
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2016/7/14				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function markTrackingCompleted($tracking_no_array){
		$rtn['message']="";
		$rtn['success'] = false;
		$now_str = date('Y-m-d H:i:s');
		$puid = \Yii::$app->subdb->getCurrentPuid();
		$userName = \Yii::$app->user->identity->getFullName();
		//??????????????????????????????track no??????update
		foreach ($tracking_no_array as $track_no){
			if (!isset($track_no) or trim($track_no)==''){
		 		continue;
			}
			
			$model = Tracking::find()->andWhere("id=:id",array(":id"=>$track_no))->one();
			
			//step 1: when not found such record, skip it
			if ($model == null){
				continue;
			}
			
			//step 2 : when state eaqual to complete , skip it
			if (in_array(strtolower($model->state) ,['complete' ] )){
				continue;
			}
			 
			//step 3, mark the flag as handled
			$model->update_time = $now_str;
			$old_status = $model->status;
			$model->state = "complete";
			$model->status ='received'; // platform_confirmed,received
			
			//????????????????????????log
			$addi_info = $model->addi_info;
			$addi_info = json_decode($addi_info,true);
			if(empty($addi_info)) $addi_info = [];
			$addi_info['manual_status_move_logs'][] = [
				'capture_user_name'=>$userName,
				'old_status'=>$old_status,
				'new_status'=>'complete',
				'time'=>$now_str,
			];
			$model->addi_info = json_encode($addi_info);
			
			//step 4: save the data to Tracking table
			if ( $model->save() ){//save successfull
				$rtn['success']=true;
				$rtn['message'] = TranslateHelper::t("????????????!?????????????????????????????????!") ;
				//push oms 
				self::pushToOMS($puid, $model->order_id,$model->status,$model->last_event_date);
			}else{
				$rtn['success']=false;
				$rtn['message'] .= TranslateHelper::t("ETRK014 ??????Tracking????????????") ;
				foreach ($model->errors as $k => $anError){
					$rtn['message'] .= "ETRK002". ($rtn['message']==""?"":"<br>"). $k.":".$anError[0];
				}
			}//end of save failed	
		}//end of each track number
		return $rtn;
	}//end of function markTrackingCompleted
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????Tracking ????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param   tracking_no_array   array of tracking no, e.g.: array('RGdfsdfsdf','RG342342','RG34234234') 
	 +---------------------------------------------------------------------------------------------
	 * @return						array('success'=true,'message'='')
	 *
	 * @invoking					TrackingHelper::markTrackingCompleted($tracking_no_array);
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2016/7/14				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function markTrackingShipping($tracking_no_array){
		$rtn['message']="";
		$rtn['success'] = false;
		$now_str = date('Y-m-d H:i:s');
		$puid = \Yii::$app->subdb->getCurrentPuid();
		$userName = \Yii::$app->user->identity->getFullName();
		//??????????????????????????????track no??????update
		foreach ($tracking_no_array as $track_no){
			if (!isset($track_no) or trim($track_no)==''){
		 		continue;
			}
			
			$model = Tracking::find()->andWhere("id=:id",array(":id"=>$track_no))->one();
			
			//step 1: when not found such record, skip it
			if ($model == null){
				continue;
			}
			 
			//step 2, mark the flag as handled
			$model->update_time = $now_str;
			$old_status = $model->status;
			$model->state = "normal";
			$model->status ='shipping'; // shipping
			
			//????????????????????????log
			$addi_info = $model->addi_info;
			$addi_info = json_decode($addi_info,true);
			if(empty($addi_info)) $addi_info = [];
			$addi_info['manual_status_move_logs'][] = [
				'capture_user_name'=>$userName,
				'old_status'=>$old_status,
				'new_status'=>'shipping',
				'time'=>$now_str,
			];
			$model->addi_info = json_encode($addi_info);
			
			//step 3: save the data to Tracking table
			if ( $model->save() ){//save successfull
				$rtn['success']=true;
				$rtn['message'] = TranslateHelper::t("????????????!?????????????????????????????????!") ;
				//push oms 
				self::pushToOMS($puid, $model->order_id,$model->status,$model->last_event_date);
			}else{
				$rtn['success']=false;
				$rtn['message'] .= TranslateHelper::t("ETRK014 ??????Tracking????????????") ;
				foreach ($model->errors as $k => $anError){
					$rtn['message'] .= "ETRK002". ($rtn['message']==""?"":"<br>"). $k.":".$anError[0];
				}
			}//end of save failed	
		}//end of each track number
		return $rtn;
	}//end of function markTrackingShipping
		
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????Track No ???????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param   tracking_no_array   array of tracking no, e.g.: array('RGdfsdfsdf','RG342342','RG34234234')
	 +---------------------------------------------------------------------------------------------
	 * @return						array('success'=true,'message'='')
	 +---------------------------------------------------------------------------------------------
	 * log			name		date					note
	 * @author		lzhl		2016/11/07				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function markTrackingIgnore($tracking_no_array){
		$rtn['message']="";
		$rtn['success'] = false;
		$now_str = date('Y-m-d H:i:s');
		$puid = \Yii::$app->subdb->getCurrentPuid();
		$userName = \Yii::$app->user->identity->getFullName();
		//??????????????????????????????track no??????update
		foreach ($tracking_no_array as $track_no){
			if (!isset($track_no) or trim($track_no)==''){
				continue;
			}
			
			$model = Tracking::find()->andWhere("id=:id",array(":id"=>$track_no))->one();
			
			//step 1: when not found such record, skip it
			if ($model == null){
				continue;
			}
	
			//step 2, mark the flag as handled
			$model->update_time = $now_str;
			$old_status = $model->status;
			$model->state = "normal";
			$model->status ='ignored';
				
			//????????????????????????log
			$addi_info = $model->addi_info;
			$addi_info = json_decode($addi_info,true);
			if(empty($addi_info)) $addi_info = [];
			$addi_info['manual_status_move_logs'][] = [
			'capture_user_name'=>$userName,
			'old_status'=>$old_status,
			'new_status'=>'ignored',
			'time'=>$now_str,
			];
			$model->addi_info = json_encode($addi_info);
				
			//step 3: save the data to Tracking table
			if ( $model->save() ){//save successfull
				$rtn['success']=true;
				$rtn['message'] = TranslateHelper::t("????????????!?????????????????????????????????!") ;
				//push oms
				self::pushToOMS($puid, $model->order_id,$model->status,$model->last_event_date);
			}else{
				$rtn['success']=false;
				$rtn['message'] .= TranslateHelper::t("ETRK014 ??????Tracking????????????") ;
				foreach ($model->errors as $k => $anError){
					$rtn['message'] .= "ETRK002". ($rtn['message']==""?"":"<br>"). $k.":".$anError[0];
				}
			}//end of save failed
		}//end of each track number
		return $rtn;
	}//end of function markTrackingIgnore
	

	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????????????????????????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param   tracking_no_array   array of tracking id
	 +---------------------------------------------------------------------------------------------
	 * @return						array('success'=true,'message'='')
	 +---------------------------------------------------------------------------------------------
	 * log			name		date					note
	 * @author		lzhl		2017/09/28				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function ignoredTrackingReSearch($tracking_no_array){
		$rtn['message']="";
		$rtn['success'] = false;
		$now_str = date('Y-m-d H:i:s');
		$puid = \Yii::$app->subdb->getCurrentPuid();
		$userName = \Yii::$app->user->identity->getFullName();
		$shipByArr = [];//??????????????????
		//??????????????????????????????track no??????update
		foreach ($tracking_no_array as $track_no){
			if (!isset($track_no) or trim($track_no)==''){
				continue;
			}
				
			$model = Tracking::find()->andWhere("id=:id",array(":id"=>$track_no))->one();
				
			//step 1: when not found such record, skip it
			if ($model == null){
				continue;
			}
			if(!empty($model->ship_by) && !in_array($model->ship_by,$shipByArr))
				$shipByArr[] = $model->ship_by;
			
			//step 2, mark the flag as handled
			$model->update_time = $now_str;
			$old_status = $model->status;
			$model->state = "initial";
			$model->status ='checking';
	
			//????????????????????????log
			$addi_info = $model->addi_info;
			$addi_info = json_decode($addi_info,true);
			if(empty($addi_info)) $addi_info = [];
			$addi_info['manual_status_move_logs'][] = [
			'capture_user_name'=>$userName,
			'old_status'=>$old_status,
			'new_status'=>'checking',
			'time'=>$now_str,
			];
			$model->addi_info = json_encode($addi_info);
	
			//step 3: save the data to Tracking table
			if ( $model->save() ){//save successfull
				$rtn['success']=true;
				$rtn['message'] = TranslateHelper::t("????????????!?????????????????????????????????!") ;
				$generateOneRequest = self::generateOneRequestForTracking($model,true);
				if(!empty($generateOneRequest['message']) && empty($generateOneRequest['success'])){
					$rtn['message'] .= $generateOneRequest['message'];
					$rtn['success'] = false;
				}
				else 
					TrackingHelper::postTrackingApiQueueBufferToDb();
				//push oms
				self::pushToOMS($puid, $model->order_id,$model->status,$model->last_event_date);
			}else{
				$rtn['success']=false;
				$rtn['message'] .= TranslateHelper::t("ETRK014 ??????Tracking????????????") ;
				foreach ($model->errors as $k => $anError){
					$rtn['message'] .= "ETRK002". ($rtn['message']==""?"":"<br>"). $k.":".$anError[0];
				}
			}//end of save failed
		}//end of each track number

		//reActive ship_by
		try{
			$ignroedSetting = self::getUserIgnoredCheckCarriers($puid);
			if($ignroedSetting['success']){
				$settedShipBy = empty($ignroedSetting['data'])?[]:$ignroedSetting['data'];
				$newIgnr = [];
				foreach ($settedShipBy as $setted){
					if(!in_array($setted,$shipByArr))
						$newIgnr[] = $setted;
				}
				$key = 'userIgnoredCheckCarriers';
				RedisHelper::RedisSet('Tracker_AppTempData', 'uid_'.$puid.'_'.$key,json_encode($newIgnr));
				ConfigHelper::setConfig('IgnoreToCheck_ShipType', json_encode($newIgnr));
			}
		}catch (Exception $e) {
			$rtn['message'] .= '??????????????????????????????????????????';
		}
		return $rtn;
	}//end of function markTrackingIgnore
	
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????Track No ??????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @params   tracking_no_array	array of tracking no, e.g.: array('RGdfsdfsdf','RG342342','RG34234234')
	 * @params   $status			'shipping' or 'arrived_pending_fetch' or 'rejected' or 'received'
	 +---------------------------------------------------------------------------------------------
	 * @return						array('success'=true,'message'='')
	 +---------------------------------------------------------------------------------------------
	 * log			name		date					note
	 * @author		lzhl		2016/11/17				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function markTrackingIsSent($tracking_no_array,$status){
		$rtn['message']="";
		$rtn['success'] = false;
		$now_str = date('Y-m-d H:i:s');
		$puid = \Yii::$app->subdb->getCurrentPuid();
		$userName = \Yii::$app->user->identity->getFullName();
		//??????????????????????????????track no??????update
		foreach ($tracking_no_array as $track_no){
			if (!isset($track_no) or trim($track_no)==''){
				continue;
			}
				
			$model = Tracking::find()->andWhere("id=:id",array(":id"=>$track_no))->one();
				
			//step 1: when not found such record, skip it
			if ($model == null){
				continue;
			}
	
			//step 2, mark the notified as handled (Y)
			$NSM = TrackingHelper::getNotifiedFieldNameByStatus($status);
			$model->$NSM = 'Y';
	
			//????????????????????????log
			$addi_info = $model->addi_info;
			$addi_info = json_decode($addi_info,true);
			if(empty($addi_info)) $addi_info = [];
			$addi_info['manual_notified_mark_sent']= [
				'capture_user_name'=>$userName,
				'time'=>$now_str,
			];
			$model->addi_info = json_encode($addi_info);
	
			//step 3: save the data to Tracking table
			if ( $model->save() ){//save successfull
				$rtn['success']=true;
				$rtn['message'] = TranslateHelper::t("????????????!?????????????????????????????????!") ;
				//push oms
				if(!empty($model->order_id))
					OdOrder::updateAll([$NSM=>'Y'], ['order_source_order_id'=>$model->order_id,'order_source'=>$model->platform]);
			}else{
				$rtn['success']=false;
				$rtn['message'] .= TranslateHelper::t("ETRK014 ??????Tracking????????????") ;
				foreach ($model->errors as $k => $anError){
					$rtn['message'] .= "ETRK002". ($rtn['message']==""?"":"<br>"). $k.":".$anError[0];
				}
			}//end of save failed
		}//end of each track number
		return $rtn;
	}//end of function markTrackingIgnore
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ????????????????????????OMS????????????????????????tracking???
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     $data
	 * @param     $source             ??????????????????M=???????????????E=Excel?????????O ???OMS ?????????
	 +---------------------------------------------------------------------------------------------
	 * @return						array('success'=true,'message'='')
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/2/9				?????????
	 +---------------------------------------------------------------------------------------------
	 **/	
	static public function addTracking($data,$source='',$total=0){
		global $CACHE;
		$puid  = \Yii::$app->subdb->getCurrentPuid();
		$rtn['message']="";
		$rtn['success'] = false;
		$now_str = date('Y-m-d H:i:s');
		
		if (empty($data['track_no'])){
			$rtn['message']= TranslateHelper::t("ETRK009 ??????????????? ????????? ??????????????????" );
			$rtn['success'] = false;
			return $rtn;
		}
		
		$data['track_no'] = trim(str_ireplace(array("\r\n", "\r", "\n"), '', $data['track_no']));
		if (! empty($data['order_id']))
		$data['order_id'] = trim(str_ireplace(array("\r\n", "\r", "\n"), '', $data['order_id']));
		//step 1, check if there is valid tracking number post, if not, return with error
		if (!isset($data['track_no']) or trim($data['track_no'])==''){
			$rtn['message']= TranslateHelper::t("ETRK001 ??????????????? ????????? ??????????????????");
			$rtn['success'] = false;
			return $rtn;
		}
		
		//step 2, try to load this track no record
		$model = Tracking::find()->andWhere("track_no=:track_no",
						array(":track_no"=>$data['track_no']  ) )->one();
		$isCreate= false;
		if ($model == null){
			 
			//??????????????????Model?????????????????????
			$isCreate = true;
			if (Tracking::$A_New_Record == null)
				Tracking::$A_New_Record = new Tracking();
			
			$model = Tracking::$A_New_Record;
			$model->create_time = $now_str;
			//????????????????????? ?????????
			if (empty($CACHE['Tracking']['Status']["???????????????"])){
				$CACHE['Tracking']['Status']["???????????????"] = Tracking::getSysStatus("???????????????");
				$CACHE['Tracking']['State']["??????"] = Tracking::getSysState("??????");
			}
			$model->status= $CACHE['Tracking']['Status']["???????????????"] ;
			$model->state = $CACHE['Tracking']['State']["??????"];
			
			//check quotao sufficient?
			/*
			$used_count = TrackingHelper::getTrackerUsedQuota($puid);
			$max_import_limit = TrackingHelper::getTrackerQuota($puid);
			if ($max_import_limit <= $used_count){
				$model->status= 'quota_insufficient' ;
				$model->state = 'exception';
			}else{//??????
				TrackingHelper::addTrackerQuotaToRedis("trackerImportLimit_".$CACHE['TrackerSuffix'.$puid] ,   1);
				
			}
			*/
			//??????????????????????????????????????????????????????
			
			//???????????????????????????????????????????????????M ??????Manual?????????
			if ($source == '')
				$source = "M";
		}//end of not existing such record, create it
		else{//for existing record, do not override the batch no field
			//we want to overwrite batch no, 2015-3-18 
			//unset($data['batch_no']);
		}
		
		if (empty($model->source) or $model->source <> 'O') //OMS ????????????????????????
			$model->source = $source;
		
		//step 3, put the data into model
		//??????excel??????????????????????????????????????????????????????????????????????????????????????????????????????????????????
		//??????excel???????????????????????????????????????OMS??????????????????????????????????????????????????????
		foreach ($data as $key=>$val){
			if (empty($val))
				unset($data[$key]);
		}
		$model->setAttributes($data);
		
		//\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Online',"set tracking data ".print_r($data,true)],"edb\global");
		
		$model->update_time = $now_str;
		
		//???????????? ship out date ?????????????????????OMS?????????????????????????????? now
		if (empty($model->ship_out_date) or $model->ship_out_date >  date('Y-m-d') )
			$model->ship_out_date = date('Y-m-d');
			
		//step 4: save the data to Tracking table
		if ($isCreate){ //when new a record, user buffer and post, not one by one
			$tempData = $model->getAttributes();
			$orderid = $tempData['order_id']; 
			$orderid = str_replace("'","",$orderid);
			$orderid = str_replace('"',"",$orderid);
			$tempData['order_id'] = $orderid;
			
			$track_no = $tempData['track_no'];
			$track_no = str_replace("'","",$track_no);
			$track_no = str_replace('"',"",$track_no);
			$tempData['track_no'] = $track_no;
			//?????????????????? unique key ?????????
			Tracking::$Insert_Data_Buffer["$orderid - $track_no"] = $tempData;
			$rtn['success']=true;
		}else {
			//ystest starts
			//check whether there is such orderid - trackno combination in data aleady, otherwise, updating would violate the unique index
			$model_already = Tracking::find()->andWhere("track_no=:track_no and order_id=:orderid",
					array(":track_no"=>$data['track_no'], ":orderid"=>$model->order_id) )->one();
			if (!empty($model_already) and $model_already->id <> $model->id ){
				 
				$model->delete();
			} else {
				//ystest ends
				
			if ( $model->save(false) ){//save successfull
				$rtn['success']=true;
			}else{
				$rtn['success']=false;
				$rtn['message'] .= TranslateHelper::t("ETRK014 ??????Tracking????????????") ;
				foreach ($model->errors as $k => $anError){
					$rtn['message'] .= "ETRK002". ($rtn['message']==""?"":"<br>"). $k.":".$anError[0];
				}
			}//end of save failed
			}//ystest
		}//end of when upadte a record
		return $rtn;
	}//end of addTracking
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????Tracking???Monitor?????????job??????3????????????cronjob ???????????????
	 * ??????????????????puid??????????????????Tracking?????????????????????Tracking ???????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param 
	 +---------------------------------------------------------------------------------------------
	 * @return						array('success'=true,'message'='')
	 *
	 * @invoking					TrackingHelper::generateTrackingRequest();
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/2/9				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function generateTrackingRequest($target_puid=0){
		$rtn['message'] = "";
		$rtn['success'] = true;
		$now_str = date('Y-m-d H:i:s');
		$todayDate = date('Y-m-d');
		$yesterday = date('Y-m-d',strtotime('-1 day'));
		$days4ago = date('Y-m-d',strtotime('-4 day'));
		$days2ago = date('Y-m-d',strtotime('-2 day'));
		$days180ago = date('Y-m-d',strtotime('-180 day'));
		$daytime90ago = date('Y-m-d H:i:s',strtotime('-90 day'));
		
		$message = "Cronb Job Started generateTrackingRequest";
		\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Online',$message],"edb\global");

		$csld_report = ConfigHelper::getGlobalConfig("Tracking/csld_format_distribute_$yesterday" ,'NO_CACHE');
		
		if ( empty($csld_report) )
			$first_run_for_today = true;
		else
			$first_run_for_today = false;
		
		//step 1, get all puid from managedb
		//step 1.1, get all puid having activity during last 30 days
		$connection = Yii::$app->db;
		/*$command = $connection->createCommand(
				"SELECT distinct puid FROM `user_last_activity_time` WHERE `last_activity_time` >='". date('Y-m-d',strtotime('-30 days')) ."'"
						) ;
		$rows = $command->queryAll();
		*/
		$puids_live_recent_30days = UserLastActionTimeHelper::getPuidArrByInterval(30*24);
		$puids_live_recent_5days1 = UserLastActionTimeHelper::getPuidArrByInterval(5*24);
		$puids_live_recent_5days = [];
		foreach ($puids_live_recent_5days1 as $puid)
			$puids_live_recent_5days[strval($puid)] =$puid;
			
		//step 1.2, ??????5?????????????????????
		
		//step 1.5?????????5????????????????????????????????????for new account ???job ???????????????????????????3?????????????????????????????????
		//???job?????????????????????????????????5???????????????????????????
		//step 1, get all puid having new account during last 12 hours
		$puids_platforms = PlatformAccountApi::getLastestBindingPuidTimeMap(5);
		$puidsCreated5Hours = array();
		foreach ($puids_platforms as $platform=>$ids){
			foreach ($ids as $id=>$create_time){
				$puidsCreated5Hours[$id] = $create_time;
			}
		}
		
		//step 2, for each puid, call to request for each active tracking
		foreach ($puids_live_recent_30days as $puid){
			//$puid = $row['puid'] ;	
			 
			
			//??????????????????5?????????????????????????????????????????????????????????????????? oms copy???????????????????????????????????????
			if (!$first_run_for_today and !isset($puids_live_recent_5days[strval($puid)]))
				continue;
			 
			
			//step 2.0, check whether this database exists user_x
  			  //???????????????????????????????????????????????????????????????????????????????????????
		/*	$sql = "select count(1) from `INFORMATION_SCHEMA`.`TABLES` where table_name ='lt_tracking' and TABLE_SCHEMA='user_$puid'";
			$command = $connection->createCommand($sql);
			$puidDbCount = $command->queryScalar();
			if ( $puidDbCount <= 0 ){
				continue;
			}
	 	*/

			//Step 2.1 Todo: get OMS shipped/completed orders into our tracking
		 
			if (!isset($puidsCreated5Hours[$puid])){
				//echo "try to get oms for $puid /";
				do {//?????????????????????300????????????????????????????????????????????????????????????300?????????????????????????????????????????????????????????
					$rtn = self::copyTrackingFromOmsShippedComplete( $puid );
					echo " Copy OMS shipped Order for puid $puid , got records count=".$rtn['count'];
				}while($rtn['count'] > 290);
			}
			 
		 
			//echo "Step 2.4 .";
			//Step 2.15, ???????????????????????????????????????????????? ?????? 00???00 ??????????????????????????????
			if ( $first_run_for_today ){
				echo "try to gen report for $puid /";
				$reports[$puid] = self::generateResultReportForPuid($yesterday,$puid);//,'RecommendProd'
				
				
				//step 2.16 ??????????????????tracker??????????????? platform sellerid ????????????????????????????????????
				$platform_seller_ids = Yii::$app->subdb->createCommand(//run_time = -10, ???????????????????????????smt api ??????????????????????????????
						"select distinct platform,seller_id from lt_tracking   ")->queryAll();
				foreach ($platform_seller_ids as $aPlatformSellerid)
					self::summaryForThisAccountForLastNdays('Tracker',$aPlatformSellerid['platform'],$aPlatformSellerid['seller_id']);
			
				//Tracker data 6 months 180 days only
				$command = Yii::$app->subdb->createCommand("delete FROM `lt_tracking` where create_time <'$days180ago'  " );
				$command->execute();
			} 
			//echo "Step 2.5 .";
			//Step 2.2 Generate api request for all tracking
		 
			if (!isset($puidsCreated5Hours[$puid])){
				//write a request is enough, other jobs will do that
				$command = Yii::$app->db_queue->createCommand("replace into `tracker_gen_request_for_puid` (
								`puid` ,`create_time` ,	`status`) VALUES ('$puid',  '$now_str',  'P' ) " );
				$command->execute();
				 
			}  
			//echo "Step 2.6 .";
		}//end of each puid
		
		//"Step 2.7 . Generate api request for all tracking ,for each puid";
		//Step final, ???????????????report
		if ( $first_run_for_today ){
			//echo "Try to gen CONSOLIDATED report HEHE";
			$message = "Try to gen CONSOLIDATED report for $yesterday";
			echo $message;
			\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Online',$message],"edb\global");
			self::generateConsolidatedReport($yesterday,$reports);
			//Housekeeping ?????????????????????
			$command = Yii::$app->db_queue->createCommand("delete FROM `tracker_api_queue` where create_time <'$days2ago'" );
			$command->execute();
			$command = Yii::$app->db_queue->createCommand("delete FROM `tracker_api_sub_queue` where create_time <'$days2ago'" );
			$command->execute();
			
			$command = Yii::$app->db->createCommand("delete FROM `ut_global_log` where create_time <'$days2ago'" );
			$command->execute();
			//journal 
			$command = Yii::$app->db_queue->createCommand("delete FROM `ut_sys_invoke_jrn` where create_time <'$days2ago'" );
			$command->execute();
			
			//CD ???????????? ??????job
			$command = Yii::$app->db_queue2->createCommand("delete FROM `hc_collect_request_queue` where create_time <'$days2ago' and status in ('C','S','F')" );
			$command->execute();
			
			// amazon fba ??????report
			$command = Yii::$app->db_queue2->createCommand("DELETE FROM  `amazon_report_requset` WHERE
			(`process_status`='RD' AND `create_time`<'$days2ago' ) or
			(`process_status`='GF' AND `create_time`<'$days2ago' and (`get_report_id_count`=10 or `get_report_data_count`=10))" );
			$command->execute();

			//message Queue
			$command = Yii::$app->db->createCommand("delete FROM `message_api_queue` where create_time <'$days2ago' and status in ('C','S', 'F')" );
			$command->execute();
			
			
			
			//app data push queue
			$command = Yii::$app->db_queue->createCommand("delete FROM `ut_app_push_queue` where create_time <'$days2ago'   " );
			$command->execute();
			
			//redis housekeeping
			$keys = RedisHelper::RedisExe ('hkeys',array('Tracker_AppTempData'));
			
			if (!empty($keys)){
				foreach ($keys as $keyName){
					if (strpos($keyName, $days4ago) !== false  or strpos($keyName, $days2ago."_print") !== false)
						RedisHelper::RedisExe ('hdel',array('Tracker_AppTempData',$keyName));
				}//end of each key name, like user_1.carrier_frequency
			}
			
			//purge 2?????? tracker MainQueue ?????????redis
			$prefixDate = substr($days2ago, 5,2).substr($days2ago, 8,2); //2011-05-20 
			$prefixDateToday = substr($todayDate, 5,2).substr($todayDate, 8,2); //2011-05-20  
			$keys = RedisHelper::RedisExe ('hkeys',array('TrackMainQ'));
			if (!empty($keys)){
				foreach ($keys as $keyName){
					if (substr($keyName, 0,4) <= $prefixDate or substr($keyName, 0,4) > $prefixDateToday )
						RedisHelper::RedisExe ('hdel',array('TrackMainQ',$keyName));
				}//end of each key name, like user_1.carrier_frequency
			}
			
			$keys = RedisHelper::RedisExe ('hkeys',array('TrackerCommitQueue_LowP'));
			if (!empty($keys)){
				foreach ($keys as $keyName){
					if (substr($keyName, 0,4) <= $prefixDate or substr($keyName, 0,4) > $prefixDateToday )
						RedisHelper::RedisExe ('hdel',array('TrackerCommitQueue_LowP',$keyName));
				}//end of each key name, like user_1.carrier_frequency
			}
			
			$keys = RedisHelper::RedisExe ('hkeys',array('TrackerCommitQueue_HighP'));
			if (!empty($keys)){
				foreach ($keys as $keyName){
					if (substr($keyName, 0,4) <= $prefixDate or substr($keyName, 0,4) > $prefixDateToday )
						RedisHelper::RedisExe ('hdel',array('TrackerCommitQueue_HighP',$keyName));
				}//end of each key name, like user_1.carrier_frequency
			}
			
			$timeStamp2hoursAgo = time() - 2*60*60;
			$keys = RedisHelper::RedisExe ('hkeys',array('PDF_TASK_DONE_URL'));
			if (!empty($keys)){
				foreach ($keys as $keyName){
					$arr1 = explode("_",$keyName);
					if (isset($arr1[0]) and is_numeric($arr1[0]) and $arr1[0]<$timeStamp2hoursAgo)
						RedisHelper::RedisExe ('hdel',array('PDF_TASK_DONE_URL',$keyName));
				}//end of each key name, like user_1.carrier_frequency
			}
			
			$keys = RedisHelper::RedisExe ('hkeys',array('PDF_TASK_DETAIL'));
			if (!empty($keys)){
				foreach ($keys as $keyName){
					$arr1 = explode("_",$keyName);
					if (isset($arr1[0]) and is_numeric($arr1[0]) and $arr1[0]<$timeStamp2hoursAgo)
						RedisHelper::RedisExe ('hdel',array('PDF_TASK_DETAIL',$keyName));
				}//end of each key name, like user_1.carrier_frequency
			}
			
			DashBoardHelper::houseKeepingJobData();
			
			echo "try to do Amazon CS task Generating at".date('Y-m-d H:i:s')."\n";
			AmazoncsHelper::cronAutoGenerateAmzCsTemplateQuest();
			echo "finished doing Amazon CS task Generating at".date('Y-m-d H:i:s')."\n";
			
			
			
			$todayDate = date('Y-m-d');
			if (substr($todayDate, 8,2) == '02'){ // do this only once per month  2017-05-01
				//???????????????data?????????90?????????
				$command = Yii::$app->db->createCommand("insert into   app_user_action_log_bk2017Q1  select * from  app_user_action_log where log_time <'$daytime90ago'" );
				$command->execute();
				
				$command = Yii::$app->db->createCommand("delete from  app_user_action_log where log_time <'$daytime90ago'" );
				$command->execute();
				
				//Lazada Listing clean up
				\eagle\modules\lazada\apihelpers\LazadaApiHelper::clearLazadaListingBeforeThreeMonth();
			}
		}//end of $first_run_for_today
		 
	}//end of batch job for generating api request for tracking
	
	static public function generateProdReports($target_puid=0){
		$rtn['message'] = "";
		$rtn['success'] = true;
		$now_str = date('Y-m-d H:i:s');
		$todayDate = date('Y-m-d');
		$reports = array();
	
		$message = "Cronb Job Started generateProdReport";
		 
		//step 1, get all puid from managedb
		//step 1.1, get all puid having activity during last 30 days
		$connection = Yii::$app->db;
		$command = $connection->createCommand(
				"SELECT distinct puid FROM `user_last_activity_time` WHERE `last_activity_time` >='". date('Y-m-d',strtotime('-90 days')) ."'"
		) ;
		$rows = $command->queryAll();

		//step 2, for each puid, call to request for each active tracking
		foreach ($rows as $row){
			$puid = $row['puid'] ;
			 
		//echo "Step 2.4 .";
		//Step 2.15, ???????????????????????????????????????????????? ?????? 00???00 ??????????????????????????????
		 
			echo "try to gen report for $puid /";
			$reports[$puid] = self::generateProdSalesReportForPuid($puid);//,'RecommendProd'
		 
		// 
		//echo "Step 2.6 .";
		}//end of each puid
	
		//"Step 2.7 . Generate api request for all tracking ,for each puid";
		 
		//Step final, ???????????????report
	 
		return $reports;	
		}//end of batch job for generating api request for tracking
	
	
	static public function doGenerateTrackingForPuid(){
		$command = Yii::$app->db_queue->createCommand("select * from `tracker_gen_request_for_puid` order by create_time " );
		$rows = $command->queryAll();
		foreach ($rows as $row){
			$puid = $row['puid'];
 
			
			echo "try to purgeUnbindedPlatformTrackingNo for $puid /";
			self::purgeUnbindedPlatformTrackingNo();
			echo "try to requestTrackingForUid for $puid /";
			$rtn = self::requestTrackingForUid($puid );
			$command = Yii::$app->db_queue->createCommand("delete  from `tracker_gen_request_for_puid` where puid= $puid " );
			$command->execute();
		}
	}
	
	static public function purgeUnbindedPlatformTrackingNo(){
		$puid = \Yii::$app->subdb->getCurrentPuid();
		$sellerIds = array();
		//step 1: Load all binded smt and ebay account user ids
		$connection = Yii::$app->db;  
		$command = $connection->createCommand(
				"select selleruserid from saas_ebay_user where uid=$puid " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "'".str_replace("'","\'",$aRow['selleruserid'])  ."'";
		}
		
		$command = $connection->createCommand("select sellerloginid from saas_aliexpress_user where  uid=$puid  " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "'". str_replace("'","\'",$aRow['sellerloginid']) ."'";
		}
		
		$command = $connection->createCommand("select merchant_id from saas_amazon_user where  uid=$puid  " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "'". str_replace("'","\'",$aRow['merchant_id']) ."'";
		}
		
		$command = $connection->createCommand("select sellerloginid from saas_dhgate_user where  uid=$puid  " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "'".str_replace("'","\'",$aRow['sellerloginid'])  ."'";
		}
		
		$command = $connection->createCommand("select username from saas_cdiscount_user where  uid=$puid  " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "'".str_replace("'","\'",$aRow['username'])  ."'";
		}
		
		$command = $connection->createCommand("select username from saas_priceminister_user where  uid=$puid  " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "'".str_replace("'","\'",$aRow['username'])  ."'";
		}
		
		$command = $connection->createCommand("select store_name from saas_bonanza_user where  uid=$puid  " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "'".str_replace("'","\'",$aRow['store_name'])  ."'";
		}
		
		$command = $connection->createCommand("select platform_userid from saas_lazada_user where  puid=$puid  " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "'".str_replace("'","\'",$aRow['platform_userid'])  ."'";
		}
				 
		$command = $connection->createCommand("select store_name from saas_wish_user where  uid=$puid " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "'".str_replace("'","\'",$aRow['store_name'])  ."'";
		}
		
		$command = $connection->createCommand(
				"select store_name from saas_ensogo_user where uid=$puid " );
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "'".str_replace("'","\'",$aRow['store_name'])  ."'";
		}
		
		//step 1.5, make sure the OMS tracking has got its seller id
		//$initSellerIdSql = "update `lt_tracking`, od_order_shipped set `seller_id` = selleruserid  WHERE seller_id is null and  source='O' and tracking_number =`track_no`";
		//$command = Yii::$app->subdb->createCommand($initSellerIdSql ) ;
		//$affectRows = $command->execute();
		 
		//step 2, delete those Oms type tracking, not from the above seller ids
		$sql = '';
		 
		$allSelleridStr = implode(",", $sellerIds);
		if (trim($allSelleridStr)  =='')
			$allSelleridStr = "'x'";
		$sql = "delete  FROM lt_tracking
				WHERE source =  'O'
				and seller_id is not null AND seller_id <>'' and seller_id not   
				IN ( 
					$allSelleridStr 
					)";
		$connection = Yii::$app->subdb;
		$command = $connection->createCommand($sql ) ;
		$affectRows = $command->execute();			 
		 
		return $sql;
	}
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????Tracking???Monitor?????????job?????? 3?????? ???????????????
	 * ????????????12?????????????????????copy OMS ???????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param
	 +---------------------------------------------------------------------------------------------
	 * @return						array('success'=true,'message'='')
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/2/9				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function initTrackingForNewAccounts(){
		$rtn['message'] = "";
		$rtn['success'] = true;
		$now_str = date('Y-m-d H:i:s');
		//step 1, get all puid having new account during last 12 hours
		$puids_platforms = PlatformAccountApi::getLastestBindingPuidTimeMap(3);
	   // echo "got puids during last 3 hours ".print_r($puids_platforms,true) ."\n";
		//step 1.5, ???????????????????????????unique ???puid.
		$puids = array();
		foreach ($puids_platforms as $platform=>$ids){
			foreach ($ids as $id=>$create_time){
				$puids[$id] = $create_time;
			}
		}
		
		//step 2, for each puid, call to request for each active tracking
		foreach ($puids as $puid=>$create_time){
			 
			echo "start to retrieve oms for puid $puid \n";
			do {//?????????????????????300????????????????????????????????????????????????????????????300?????????????????????????????????????????????????????????
				$rtn = self::copyTrackingFromOmsShippedComplete( $puid );
				//echo "got oms for $puid , result=".print_r($rtn,true)."\n";
			}while($rtn['count'] > 290);

		//Step 2.2 Generate api request for all tracking
		$rtn = self::requestTrackingForUid($puid );	
		}//end of each puid
			
	}//end of batch job for generating api request for New accounts binded
		
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????????puid ??????????????????????????? ?????????????????????????????????????????????????????????????????????????????????tracking request????????????
	 *
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param $platform				'':all
	 *                              aliexpress: ?????????????????????????????????????????????
	 *                              ebay: ?????????????????????????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @return						????????????????????????????????????????????????success=false
	 *                                     array('success'=true,'message'='',
	 *                                     state_distribution=[ ['state'='complete' cc=20],['state'='exception' cc=60]])
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/2/9				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function progressOfTrackingDataInit($platform=''){
		$rtn['message'] = "";
		$rtn['success'] = true;
		
		//call to ??????????????????5?????????????????????????????????????????????????????????????????????
		$puid = \Yii::$app->subdb->getCurrentPuid();
		$ebay_aliexpress_new = PlatformAccountApi::getAcountBindingInfo($puid,5);
		
		if (empty($ebay_aliexpress_new['ebay']) and empty( $ebay_aliexpress_new['dhgate'] ) and  empty($ebay_aliexpress_new['aliexpress'])
				and  empty($ebay_aliexpress_new['wish']) and  empty($ebay_aliexpress_new['cdiscount']) and  empty($ebay_aliexpress_new['lazada'])
		      or ($platform<>'' and empty($ebay_aliexpress_new[$platform]) )  ){
			$rtn['success'] = false;
			return $rtn;
		}
					
		$criteria = "";
		if ($platform <> ''){
			$criteria = " and platform='$platform' ";
		}
		
		$isNotCompleted =  Tracking::find()->where("source='O' and state='initial' $criteria  ")->exists();

		if ($isNotCompleted){
			//	???????????????????????????????????????state ??????
			$rtn['state_distribution'] = Yii::$app->get('subdb')->createCommand(
				" select state,count(*) as cc from lt_tracking where source='O' $criteria group by state"
				        )->queryAll();
		}else{
			$rtn['success'] = false;
		}	
		return $rtn;
	}
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????????puid ??????????????????????????? ?????????????????????????????????????????????????????????????????????????????????tracking request????????????
	 * 
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param $puid					???????????????????????????
	 * @param $call by online       ??????Online User??????????????????????????????????????????Excel?????????????????????API???????????????
	 *                              ????????? ????????????????????????????????????API?????????
	 +---------------------------------------------------------------------------------------------
	 * @return						array('success'=true,'message'='')
	 *
	 * @invoking					TrackingHelper::requestTrackingForUid(1);
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/2/9				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function requestTrackingForUid($puid = 0 ,$call_by_online = false){
		//echo "\n requestTrackingForUid 1";
		$rtn['message'] = "";
		$rtn['success'] = true;
		$now_str = date('Y-m-d H:i:s');
		$six_hours_ago = date('Y-m-d H:i:s',strtotime('-6 hours'));
		$ten_days_ago = date('Y-m-d',strtotime('-10 days'));
		$days90_ago = date('Y-m-d',strtotime('-90 days'));
		$days120_ago = date('Y-m-d',strtotime('-120 days'));
		
		//step 1: change to this puid database
		if ($puid == 0)
			$puid = \Yii::$app->subdb->getCurrentPuid();
		
		 
	//	echo "\n requestTrackingForUid 2";
		//step 2: for those tracking ????????????, while tried for 10 days, set them as ???????????????????????????????????????
		$command = Yii::$app->subdb->createCommand("update lt_tracking set state='".Tracking::getSysState("????????????")."'
								,status='".Tracking::getSysStatus("????????????")."'
								, update_time='$now_str' where status='".Tracking::getSysStatus("????????????")."' 
								and ( create_time <='$ten_days_ago'  )" ); //or ship_out_date<='$ten_days_ago'
		
		$affectRows = $command->execute();
		 
		//step 2.2: 90?????????????????? ????????????????????????,?????????????????????????????????????????? 120??????
		$countriesMax120days = array("'BR'");
		$command = Yii::$app->subdb->createCommand("update lt_tracking set state='".Tracking::getSysState("?????????")."'
								,status='".Tracking::getSysStatus("???????????????")."', update_time='$now_str' where status in ('".Tracking::getSysStatus("???????????????")."','".Tracking::getSysStatus("????????????")."')
								and (ship_out_date <='$days90_ago' and  ship_out_date >'1990-1-1' or create_time <='$days90_ago')
								and to_nation not in ( " .implode(",", $countriesMax120days). " )" );
		
		$affectRows = $command->execute();
		//echo "\n requestTrackingForUid 3";
		//???????????????????????????????????????120days
		$command = Yii::$app->subdb->createCommand("update lt_tracking set state='".Tracking::getSysState("?????????")."'
								,status='".Tracking::getSysStatus("???????????????")."', update_time='$now_str' where status in ('".Tracking::getSysStatus("???????????????")."','".Tracking::getSysStatus("????????????")."')
				and (ship_out_date <='$days120_ago' and  ship_out_date >'1990-1-1' or create_time <='$days120_ago')   
				and to_nation in ( " .implode(",", $countriesMax120days). " )" );
		
		$affectRows = $command->execute();
		//echo "\n requestTrackingForUid 3.1";
		//step 2.3: ??????ship by ????????????????????????????????? ????????????????????????state?????????????????????
		$command = Yii::$app->subdb->createCommand("update lt_tracking set state='".Tracking::getSysState("?????????")."'
								,status='".Tracking::getSysStatus("?????????")."', update_time='$now_str' where status in 
								('".Tracking::getSysStatus("???????????????")."','".Tracking::getSysStatus("????????????")."')
								and  (  ship_by like '%??????%' or  ship_by like '%?????????%' or  ship_by like '%?????????%' )" );
		
		$affectRows = $command->execute();
		//echo "\n requestTrackingForUid 3.2";
		// 2.4 : ??????Aliexpress??????FINISH????????????update ??????????????????????????????????????????
		//new version do not use order original table, but order v2
		$select_str=" select track_no,all_event from  lt_tracking, od_order_v2  
										where  status not in ('".Tracking::getSysStatus("????????????")."','".Tracking::getSysStatus("???????????????")."')  
												and order_source_status in ('FINISH') and
								order_source_order_id = lt_tracking.order_id and lt_tracking.source='O' and platform='aliexpress'    ";
		//echo "\n requestTrackingForUid 3.3";
		$command = Yii::$app->subdb->createCommand( $select_str );
	//	echo "\n requestTrackingForUid 3.4";
		$rows = $command->queryAll();
	//	echo "\n requestTrackingForUid 3.5";
		foreach ($rows as $row){
			if( $puid=='18870' && $row['all_event']!='' ){
				continue;
			}
			self::manualSetOneTrackingComplete( $row['track_no']);

		}
	//	echo "\n requestTrackingForUid 4";
		//2.4.b : ??????Dhgate??????FINISH????????????update ??????????????????????????????????????????
		//new version do not use order original table, but order v2
		$select_str=" select track_no,all_event from  lt_tracking, od_order_v2
										where  status not in ('".Tracking::getSysStatus("????????????")."','".Tracking::getSysStatus("???????????????")."')  and
												order_source_status in ('102006','102007','102111','111111') and
												order_source_order_id = lt_tracking.order_id and lt_tracking.source='O' and platform='dhgate'    ";
		
		$command = Yii::$app->subdb->createCommand( $select_str );
		$rows = $command->queryAll();
		foreach ($rows as $row){
			if( $puid=='18870' && $row['all_event']!='' ){
				continue;
			}
			self::manualSetOneTrackingComplete( $row['track_no']);
		}
		//echo "\n requestTrackingForUid 5";
		//2.4.c : ???????????????????????????????????????????????????
		$ignoreList1 = self::getUserIgnoredCheckCarriers($puid);
		if ($ignoreList1['success']){
			$ignoreList= $ignoreList1['data'];
		}else
			$ignoreList=[];
		foreach ($ignoreList as $ship_by){
			Tracking::updateAll(['status'=>'ignored']," ship_by=:ship_by and state!='complete' and state!='deleted' ",[':ship_by'=>$ship_by]);
		}
	 
		
		//Step 2.5, ???????????????????????????????????????????????????????????????????????????
		//$last_gen_time = self::getTrackerTempDataFromRedis("last_gen_track_request_time");
		$lastTouch = UserLastActionTimeHelper::getLastTouchTimeByPuid($puid);
		$checkInterval = date('Y-m-d H:i:s',strtotime('-12 hours'));;
		if (empty($lastTouch))
			$lastTouch = date('Y-m-d H:i:s',strtotime('-6 days'));
		
		//1.??????6???????????????????????????????????????3???????????????
		//2.??????3???????????????????????????????????????1???????????????
		//3.??????2???????????????????????????????????????12????????????
		//3.??????2???????????????????????????????????????6????????????
		if ($lastTouch <= date('Y-m-d H:i:s',strtotime('-30 days')) )
			$checkInterval = date('Y-m-d H:i:s',strtotime('-60 days'));
				
		elseif ($lastTouch <= date('Y-m-d H:i:s',strtotime('-20 days')) )
			$checkInterval = date('Y-m-d H:i:s',strtotime('-30 days'));
		
		elseif ($lastTouch <= date('Y-m-d H:i:s',strtotime('-15 days')) )
			$checkInterval = date('Y-m-d H:i:s',strtotime('-20 days'));
						
		elseif ($lastTouch <= date('Y-m-d H:i:s',strtotime('-10 days')) )
			$checkInterval = date('Y-m-d H:i:s',strtotime('-10 days'));
								
		elseif ($lastTouch <= date('Y-m-d H:i:s',strtotime('-6 days')) )
			$checkInterval = date('Y-m-d H:i:s',strtotime('-3 days'));
		
		elseif ($lastTouch < date('Y-m-d H:i:s',strtotime('-3 days')) )
			$checkInterval = date('Y-m-d H:i:s',strtotime('-1 day'));
				
		elseif ($lastTouch < date('Y-m-d H:i:s',strtotime('-2 days')) )
			$checkInterval = date('Y-m-d H:i:s',strtotime('-18 hours'));
		
		elseif ($lastTouch < date('Y-m-d H:i:s',strtotime('-1 day')) )
			$checkInterval = date('Y-m-d H:i:s',strtotime('-12 hours'));
		//echo "\n requestTrackingForUid 7";
		$message ="Generate Puid $puid , lastTouch is $lastTouch, work for those updated before $checkInterval ";
		//\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Background',$message ],"edb\global");
		 
		//step 3: get all tracking 
		 
		//?????????????????????????????????????????????check??????
		//??????????????????????????????,state=??????
		$condition = "";
		$thoseCanDo = array();
		//below canDos are Or ??????between each other
		//3.1 ???????????????????????????????????????
		$thoseCanDo[] = "state='".Tracking::getSysState("??????")."'";
		//3.2: ????????????  ???????????????24??????
		$thoseCanDo[] = "status='no_info' and update_time < '". date('Y-m-d H:i:s',strtotime('-24 hours'))."' and update_time<'$checkInterval'"; //ystest
		//3.3: ???????????? ???????????????24??????
		$thoseCanDo[] = "status='suspend' and update_time < '". date('Y-m-d H:i:s',strtotime('-24 hours'))."' and update_time<'$checkInterval'"; //ystest
		//3.4: ?????????  ????????????????????????????????????????????????2??????????????????2??????????????????
		//3.4????????????????????????????????????????????????????????? ??????N????????????
		$thoseCanDo[] = "status='shipping' and first_event_date < '". date('Y-m-d H:i:s',strtotime('-2 days'))."' and first_event_date>='". date('Y-m-d H:i:s',strtotime('-60 days'))."' and update_time<'$checkInterval'";
		$thoseCanDo[] = "status='shipping' and first_event_date < '". date('Y-m-d H:i:s',strtotime('-2 days'))."' and first_event_date>='". date('Y-m-d H:i:s',strtotime('-80 days'))."' and update_time<'". date('Y-m-d H:i:s',strtotime('-3 days'))."'";
		$thoseCanDo[] = "status='shipping' and first_event_date < '". date('Y-m-d H:i:s',strtotime('-2 days'))."' and first_event_date>='". date('Y-m-d H:i:s',strtotime('-150 days'))."' and update_time<'". date('Y-m-d H:i:s',strtotime('-5 days'))."'";
		//3.5: state????????????????????????24?????????
		$thoseCanDo[] = "state='".Tracking::getSysState("??????")."' and update_time < '$checkInterval'";
		$thoseCanDo[] = "status='".Tracking::getSysStatus("???????????????")."' and update_time < '". date('Y-m-d H:i:s',strtotime('-3 hours'))."'";
		//3.6??? ???????????? ??? ???10-30??????????????????10-20??????????????????1??????21-30????????????2???????????????
		/*yzq, 20150802, ???????????????????????????????????????????????????
		$thoseCanDo[] = "state='".Tracking::getSysState("????????????")."' and create_time <  '". date('Y-m-d H:i:s',strtotime('-10 days'))."'
									  and create_time >=  '". date('Y-m-d H:i:s',strtotime('-20 days'))."'  and update_time < '". date('Y-m-d H:i:s',strtotime('-2 day'))."' ";
		$thoseCanDo[] = "state='".Tracking::getSysState("????????????")."' and create_time <  '". date('Y-m-d H:i:s',strtotime('-20 days'))."'
									  and create_time >=  '". date('Y-m-d H:i:s',strtotime('-30 days'))."'  and update_time < '". date('Y-m-d H:i:s',strtotime('-3 days'))."' ";		
		$thoseCanDo[] = "state='".Tracking::getSysState("????????????")."' and create_time <  '". date('Y-m-d H:i:s',strtotime('-30 days'))."'
									  and create_time >=  '". date('Y-m-d H:i:s',strtotime('-60 days'))."'  and update_time < '". date('Y-m-d H:i:s',strtotime('-10 days'))."' ";
		*/
		$thoseCanIgnore = array();
		//Below are and ?????????between ??????????????? thoseCanDo ??????And ??????
		//3.99, ????????????state??????????????????????????????????????????????????????
		$thoseCanIgnore[] = "( state not in ('".Tracking::getSysState("?????????")."'
											,'".Tracking::getSysState("?????????")."'
											,'".Tracking::getSysState("????????????")."'	) 
													or  status='".Tracking::getSysStatus("???????????????")."' )";
		//3.98,??????????????????????????????120??????120????????????????????????
		$thoseCanIgnore[] = "ship_out_date >= '". date('Y-m-d',strtotime('-120 days')) ."' or ship_out_date is null or ship_out_date='1970-01-01' or status='".Tracking::getSysStatus("???????????????")."'" ;
		//3.99???????????????????????????
		$thoseCanIgnore[] = "status!='ignored'";
		$condition ="(";
		foreach ($thoseCanDo as $canDo){
			$condition .= ($condition =="(" ?"":" or ");
			$condition .= " ( $canDo )"; 
		}
		
		$condition .=")";
		
		foreach ($thoseCanIgnore as $canIgnore){
			$condition .= " and ( $canIgnore )";
		}
		
		//??????Online User??????????????????????????????????????????Excel?????????????????????API???????????????
		if ($call_by_online){
			$condition .= " and source in ('M','E')";
		}		
		/*
		$trackingArray = Tracking::find()
							->select("id,track_no , addi_info") //ystest
							->andWhere($condition)
							->asArray()
							->all();
       */
	//	echo "SELECT `id`, `track_no`, `addi_info` FROM `lt_tracking`  	force index (status_state)  WHERE $condition";
		$command = Yii::$app->subdb->createCommand( "SELECT `id`, `track_no`, `addi_info`,`all_event` FROM `lt_tracking`  
								force index (status_state) 		 WHERE $condition   and status<>'quota_insufficient' " ); //
		$trackingArray = $command->queryAll();
		
		//step 4, for each tracking models need to be rechecked, write one request for each
		$track_list = array();
		$unregistered_track_list = array();
		$addinfos = array();
		$ids = array();//ystest
		foreach ($trackingArray as $aTracking){
			if( $puid=='18870' && $aTracking['all_event']!='' ){
				continue;
			}
				$track_list[] = $aTracking['track_no'];
				$ids[] = $aTracking['id']; //ystest
		} //end of each tracking

	
		// ??????buffer ????????????????????????job?????????????????????
		self::putIntoTrackQueueBuffer($track_list, ! Tracking::$IS_USER_REQUIRE_UPDATE );
		
		//ystest starts
		//update the lt_tracking update time so that when this job run again, do not reGenerate requests for them, they are in queue already
		if (!empty($ids)){
			$command = Yii::$app->subdb->createCommand("update lt_tracking set update_time='$now_str' where id in (".implode(",", $ids)." )" );
			$affectRows = $command->execute();
		}
		//ystest ends
		
		$rtn['condition'] = $condition;
		//force update the top menu statistics
		self::setTrackerTempDataToRedis("left_menu_statistics", json_encode(array()));
		self::setTrackerTempDataToRedis("top_menu_statistics", json_encode(array()));
		
		return $rtn;
		
	}//end of requestTracking
	
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????????????????????????????????????????????????????????????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param $track_no	   
	 +---------------------------------------------------------------------------------------------
	 * @return						array('success'=true,'message'='')
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/2/9				?????????
	 +---------------------------------------------------------------------------------------------
	 **/	
	static public function manualSetOneTrackingComplete($track_no ){
		//array('parcel_type'=>1,'status'=>1,'carrier_type'=>1,'from_nation'=>1,'to_nation'=>1,'all_event'=>1,'total_days'=>1,'first_event_date'=>1,'from_lang'=>1,'to_lang'=>1);
		$data = array();
		$now_str = date('Y-m-d H:i:s');
		
		$data['track_no'] = $track_no;
		$data['status'] =Tracking::getSysStatus("???????????????");
		$data['state'] =Tracking::getSysState("?????????");
		$data['update_time'] = $now_str;
	//	$carriers = self::getCandidateCarrierType($track_no);
	//	$data['carrier_type'] = (isset($carriers[0])  ? $carriers[0] : 0);
		$aTracking = Tracking::find()
			->andWhere("track_no=:trackno",array(':trackno'=>$track_no) )->asArray()
			->one();
		
		$aTracking['addi_info'] = str_replace("`",'"',$aTracking['addi_info']);
		$addinfo = json_decode(   $aTracking['addi_info'],true);
		if (!empty($addinfo['consignee_country_code']))
			$data['to_nation'] = $addinfo['consignee_country_code'];
 
		if (empty($aTracking['first_event_date'])){
			$aTracking['first_event_date'] = $aTracking['ship_out_date'];
			$data['first_event_date'] = $aTracking['ship_out_date'];
		}
		
		if (!empty($aTracking['first_event_date']) and strlen($aTracking['first_event_date']) >= 10){
			$datetime1 = strtotime (date('Y-m-d H:i:s'));
			$datetime2 = strtotime (substr($aTracking['first_event_date'], 0,10)." 00:00:00");
			$days =ceil(($datetime1-$datetime2)/86400); //60s*60min*24h
			$data['total_days'] =  $days  ;
		}
		
		$data['last_event_date'] = $now_str;
	 
		//Do not use this man-made event, $data['all_event'] = json_encode($allEvents);
		TrackingQueueHelper::commitTrackingResultUsingValue($data );
	}
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????????????????tracking???????????????API track request?????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param $aTrackingOrTrackNo	?????????Tracking???Model????????????tracking no
	 * @param $user_require_update	????????????????????????update??????????????????????????????request?????????????????????			
	 * @param $addi_info            addition info, ????????? timeout???????????????????????????????????????????????????????????????	
	 * @param $addi_params			addition parameter  ????????????    ???????????? eg.['batchupdate' =>true/false , .... ] 
	 +---------------------------------------------------------------------------------------------
	 * @return						array('success'=true,'message'='')
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/2/9				?????????
	 +---------------------------------------------------------------------------------------------
	 **/	
	static public function generateOneRequestForTracking($aTrackingOrTrackNo, $user_require_update = false ,$addi_info='' , $addi_params=[] ){
		global $CACHE;
		$rtn['message'] = "";
		$rtn['success'] = true;
		if ($user_require_update == "Y")
			$user_require_update = true;
		
		$puid = \Yii::$app->subdb->getCurrentPuid();
		
		if (is_numeric($aTrackingOrTrackNo)){
			$aTrackingOrTrackNo = (String)$aTrackingOrTrackNo;
		}
		
		//???????????????????????????Tracking ???model ??????????????? ??????tracking no
		if (! is_string($aTrackingOrTrackNo))
			$aTracking = $aTrackingOrTrackNo;
		else //?????????track no?????????Load??????record
			$aTracking = Tracking::find()
					->andWhere("track_no=:trackno",array(':trackno'=>$aTrackingOrTrackNo) )
					->one();
		
		if (!isset($aTracking['track_no']) or $aTracking['track_no'] ==''){
			$rtn['message'] = "????????????????????????????????????????????????????????????????????????Model";
			$rtn['success'] = false;
			return $rtn;
		}
		
		if ($aTracking['status']=='quota_insufficient'){
			$used_count = TrackingHelper::getTrackerUsedQuota($puid);
			$max_import_limit = TrackingHelper::getTrackerQuota($puid);
			if ($max_import_limit <= $used_count){
				$rtn['message'] = "????????????????????????????????????????????????????????????????????????????????????.";
				$rtn['success'] = false;
			}else{//??????
				//????????????????????????????????? 
				//TrackingHelper::addTrackerQuotaToRedis("trackerImportLimit_".$CACHE['TrackerSuffix'] ,   1);	
			}
		}
		//?????????18870????????????????????????all_event???????????????????????????????????????????????????
		if( $puid=='18870' ){
			if( $aTracking['all_event']!='' ){
				$rtn['message'] = "????????????????????????????????????????????????";
				$rtn['success'] = false;
			}
		}

		//?????? ?????????????????? ???????????? ??????????????????????????????????????? 
		if ($aTracking['state'] == Tracking::getSysState("????????????") ){
			//?????????????????????????????? ?????????????????????????????????????????????20????????? ???????????????8????????????
			$limit_hours = '20';
			$limit_hours_ago = date('Y-m-d H:i:s',strtotime('-'.$limit_hours.' hours')); 
			$limit_hours_ago2 = date('Y-m-d H:i:s',time()-3600); 									//???????????? ??????????????????????????????????????? ????????? ?????????1??????
			$limit_hours2 = '1??????';
		}else{
			$limit_hours = '8';
			$limit_hours_ago = date('Y-m-d H:i:s',strtotime('-'.$limit_hours.' hours'));
			$limit_hours_ago2 = date('Y-m-d H:i:s',time()-600); 									//???????????? ??????????????????????????????????????? ????????? ?????????10??????
			$limit_hours2 = '10??????';
		}
		
		$tracking_addi_info = json_decode($aTracking['addi_info'],true);	
		$tracking_addi_info['consignee_country_code'] = $aTracking->getConsignee_country_code();
		
		if(empty($addi_params['setCarrierType'])){
			if (!empty($tracking_addi_info['last_manual_refresh_time']) && $tracking_addi_info['last_manual_refresh_time'] > $limit_hours_ago ){
				// 
				$rtn['message'] = $aTracking['track_no']." ?????????????????? ??? ".$tracking_addi_info['last_manual_refresh_time'] .',??????'.$limit_hours.'??????????????????!';
				$rtn['success'] = false;
				return $rtn;
			}
		}else{//????????????????????????????????????????????????
			if (!empty($tracking_addi_info['last_set_carrier_type_time']) && $tracking_addi_info['last_set_carrier_type_time'] > $limit_hours_ago2 ){
				$rtn['message'] = $aTracking['track_no']." ???????????????????????????????????????????????? ??? ".$tracking_addi_info['last_set_carrier_type_time'] .',??????'.$limit_hours2.'????????????!';
				$rtn['success'] = false;
				return $rtn;
			}
		}
		//step 3.0?????????OMS and ???order id ???????????????????????? order date ??????4?????????????????????????????????????????????????????????
		if ($aTracking['source'] =='O' and !empty($aTracking['order_id'])){		
			if (empty($aTracking['ship_out_date']) ){				
				//????????????????????? ?????????????????????oms???????????? order date???????????????ship out date ??????????????? 
				$getOmsOrder = self::getOrderDetailFromOMSByTrackNo($aTracking['track_no']);
				if ($getOmsOrder['success']){					
					$order_time = !empty($getOmsOrder['order']['paid_time'])?$getOmsOrder['order']['paid_time']:(!empty($getOmsOrder['order']['delivery_time'])?$getOmsOrder['order']['delivery_time']:"");
					if ($order_time <> ""){
						$order_time = date('Y-m-d H:i:s',$order_time);
						$aTracking->ship_out_date = $order_time;						
						$aTracking->save(false);
						$aTracking['ship_out_date'] = $order_time;
					}
				}
			}//end of when ship out date is empty
			
			if ( !$user_require_update and !empty($aTracking['ship_out_date'])  ){
				$days_120_ago = date('Y-m-d H:i:s',strtotime('-90 days'));
				//??????????????????ship out date ???120 ?????????????????????skip
				if ($aTracking['ship_out_date'] < $days_120_ago   ){
					$aTracking->update_time = date('Y-m-d H:i:s');
					$aTracking->status = Tracking::getSysStatus("???????????????");					
					$aTracking->state = Tracking::getSysState("?????????");
					//$aTracking->save(false);
			 
					$command = Yii::$app->subdb->createCommand("update lt_tracking set 
							update_time='".date('Y-m-d H:i:s')."' ,
							status='".$aTracking->status."',
							state='".$aTracking->state."' where track_no = '".$aTracking->track_no . "' "  );
								
					$affectRows = $command->execute();

					$rtn['message'] = "?????????".$aTracking['track_no']." ???4??????????????????".$aTracking['ship_out_date'].",?????????,???????????????";
	//				\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Background',$rtn['message'] ],"edb\global");
					return $rtn;
				}
			}
		}//end of order id presents
		
		if ($user_require_update){
			//force update the top menu statistics
			$track_statistics_str = self::getTrackerTempDataFromRedis("left_menu_statistics");
			$track_statistics = json_decode($track_statistics_str,true);
			if(isset($track_statistics[$aTracking->seller_id]))
				unset($track_statistics[$aTracking->seller_id]);
			if(isset($track_statistics['all']))
				unset($track_statistics['all']);
			self::setTrackerTempDataToRedis("left_menu_statistics", json_encode($track_statistics));			
			self::setTrackerTempDataToRedis("top_menu_statistics", json_encode(array()));
		}
	 
		//step 4.1, check queue, if there is a such one pending, skip writing a new one
		$theExistingQueueReq = TrackerApiQueue::find()
						->andWhere("track_no=:trackno and status='P' and puid=$puid",array(':trackno'=>$aTracking['track_no']))
						->one();
		
		/* ?????? 
		$tmpCommand = TrackerApiQueue::find()
		->andWhere("track_no=:trackno and status='P' and puid=$puid",array(':trackno'=>$aTracking['track_no']))
		->createCommand();
		echo "<br>".$tmpCommand->getRawSql();
			*/
		//if already having pending api request, just skip this
		if ($theExistingQueueReq != null){
			//??????????????????????????????????????????????????????????????????
			if ($user_require_update){
				if ($aTracking['state'] == Tracking::getSysState("????????????")  &&  !empty($addi_params['batchupdate']) ){
					$theExistingQueueReq->priority = 2;
				}else{
					$theExistingQueueReq->priority = 1;
				}
				
				//??????????????????????????????????????????????????????????????????
				if(!empty($addi_params['setCarrierType']) && isset($addi_params['CarrierType'])){
					$theExistingQueueReq->selected_carrier = -100;//?????????default value
					$theExistingQueueReq->candidate_carriers = $addi_params['CarrierType'];
				}
				
				$theExistingQueueReq->save();
				//ystest starts
				//?????????????????????????????? addiinfo
				if ($aTracking->status <> Tracking::getSysStatus("???????????????")){
					$addi_info1 = [];
					if (!empty($aTracking->addi_info))
						$addi_info1 = json_decode($aTracking->addi_info,true);
				
					$addi_info1['last_status'] = $aTracking->status;
					$aTracking->addi_info = json_encode($addi_info1);
				}
				//ystest end
				
				$aTracking->status = Tracking::getSysStatus("???????????????");
				$aTracking->save(false);
			}else{
				if ($theExistingQueueReq->priority >2 and $aTracking->source=='M'){
					$theExistingQueueReq->priority = 2;
					$theExistingQueueReq->save(false);			
				}
			}
			return $rtn;
		}
		
		//step 4.2, check queue, if there is a such one processing but no respond for 5 minutes, update it to failed
		$five_minutes_ago = date('Y-m-d H:i:s',strtotime('-5 minutes'));
		$existingOne = TrackerApiQueue::find()
						->andWhere("track_no=:trackno and status='S'  and puid=$puid ",array(':trackno'=>$aTracking['track_no']) )
						->one();
			
		if ($existingOne){
			//check if it is with 5 minutes, if yes, do nothing, leave it processing
			if ($existingOne->update_time > $five_minutes_ago){
				$rtn['message'] = "???????????????, ???????????????!";
				$rtn['success'] = false;
				return $rtn;
			}
		
			//if it is 5 minutes ago, kill it and make a new request
			$existingOne->update_time = date('Y-m-d H:i:s');
			$existingOne->status = 'F';
			$addi_info1 = json_decode($existingOne->addinfo,true);
			$addi_info1['failReason'] = 'E1';
			$existingOne->addinfo = json_encode($addi_info1);
			
			if ( $existingOne->save(false) ){//save successfull
				//$rtn['message'] = "?????????????????????,????????????????????????!";
			}else{
				$rtn['success'] = false;
				$rtn['message'] .= TranslateHelper::t($existingOne->track_no."ETRK004????????????5???????????????Track request??????????????????????????????.");
				foreach ($existingOne->errors as $k => $anError){
					$rtn['message'] .= ($rtn['message']==""?"":"<br>"). $k.":".$anError[0];
				}//end of each error
				\Yii::error(['Tracking',__CLASS__,__FUNCTION__,'Background',$rtn['message']],"edb\global");
				return $rtn;
			}//end of save failed
		}//end of if there is S sattus
		
		//step 4.3, write a new request in queue
		$now_str = date('Y-m-d H:i:s');
		//??????Cache ?????????????????????New ??????Object?????????
		global $CACHE;
		if (!isset($CACHE['Tracking']['TrackerApiQueue_NewRecord']))
			$CACHE['Tracking']['TrackerApiQueue_NewRecord'] = new TrackerApiQueue();
		$ApiRequestModel = $CACHE['Tracking']['TrackerApiQueue_NewRecord'];
		$ApiRequestModel->selected_carrier = -100; //default value
		$ApiRequestModel->create_time = $now_str;
		$ApiRequestModel->update_time = $now_str;
		//????????????????????? ?????????
		$ApiRequestModel->status = "P" ;
		$ApiRequestModel->track_no = $aTracking['track_no'];
 
		$ApiRequestModel->addinfo = $aTracking['addi_info'];
		
		//ys0922 remove ??????
		$ApiRequestModel->selected_carrier = 0;
		$ApiRequestModel->candidate_carriers = '';
		
		
		if(!isset($tracking_addi_info['set_carrier_type'])){
			//??????????????? selected carrier ???????????????????????????????????????????????? //????????????????????????????????????
			if ($aTracking['status']<>'no_info' and  $aTracking['carrier_type'] <> ''
					and ($aTracking['state']=='normal' or $aTracking['state']=='exception') ) {
				$ApiRequestModel->selected_carrier = $aTracking['carrier_type'];
				$ApiRequestModel->candidate_carriers = "".$aTracking['carrier_type']."";
			}
		}else{
			//??????????????????????????????????????????????????? selected carrier??????carrier_type??????candidate_carriers
			/*
			if ($aTracking['carrier_type']<>'' and $aTracking['state']!=='complete' && $aTracking['state']!=='deleted'){
				$ApiRequestModel->selected_carrier = -100;//?????????default value
				$ApiRequestModel->candidate_carriers = "".$aTracking['carrier_type']."";
			}*/
			//???????????????setCarrierType???????????????
			$ApiRequestModel->selected_carrier = $tracking_addi_info['set_carrier_type']; 
			$ApiRequestModel->candidate_carriers = $tracking_addi_info['set_carrier_type'];
		}
		
		
		//?????????????????????????????????,?????????1-5??? 1??????
		//default is 5????????????????????????
		$ApiRequestModel->priority = 5;
			
		//???????????????????????????????????????????????????????????????
		
		if ($aTracking['source'] =='M' and $aTracking['state']== Tracking::getSysState("??????") )
			$ApiRequestModel->priority = 2;
			
		//Excel ????????????????????????????????????????????????
		if ($aTracking['source'] =='E' and $aTracking['state']== Tracking::getSysState("??????") )
			$ApiRequestModel->priority = 3;
			
		//OMS ????????????????????????????????????????????????
		if ($aTracking['source'] =='O' and $aTracking['state']== Tracking::getSysState("??????") ){
			$ApiRequestModel->priority = 4;
			//?????????2?????? 2?????? ??????????????????
			if ($aTracking['ship_out_date'] <= date('Y-m-d',strtotime('-1 day')) 
				and $aTracking['ship_out_date'] > date('Y-m-d',strtotime('-14 days'))){
				$ApiRequestModel->priority -- ;
			}
		}
		if ($user_require_update){
			$ApiRequestModel->priority = 1;
		}
		
		//????????????????????????????????????????????????, ??????????????? ???2
		if ($aTracking['state'] == Tracking::getSysState("????????????")  &&  !empty($addi_params['batchupdate']) ){
			$ApiRequestModel->priority = 2;
		}
		
		
		//?????????????????????oms?????????????????????shipping method code???????????????????????????????????????????????????
		
		if (  $aTracking->source =='O' ){// and $aTracking->platform =='aliexpress' yzq 2017-3-17
			$addi_info1 = json_decode($aTracking->addi_info,true);
			$array1 = json_decode($ApiRequestModel->addinfo,true);
			
			
			if (!isset($addi_info1['shipping_method_code'])){
				//copy from order_sihpping_v2,yzq 20170123
				$sql = "select * from  od_order_shipped_v2  where  tracking_number ='".$ApiRequestModel->track_no."'";

				$command = Yii::$app->get('subdb')->createCommand($sql);
				$shipped_row = $command->queryOne();
				
				if (!empty($shipped_row)){
					
					//20170123 this can be removed after 2017-2-5
					//????????????????????????mapping????????? shipping method code
					if ( empty($shipped_row['shipping_method_code'])){
$mapping1=[
'China Post Ordinary Small Packet Plus'=>'YANWEN_JYT',
'4PX Singapore Post OM Pro'=>'SGP_OMP',
'Correos Economy'=>'SINOTRANS_PY',
'OMNIVA Economic Air Mail'=>'OMNIVA_ECONOMY',
'Posti Finland Economy'=>'ITELLA_PY',
'Royal Mail Economy'=>'ROYAL_MAIL_PY',
'Ruston Economic Air Mail'=>'RUSTON_ECONOMY',
'SF Economic Air Mail'=>'SF_EPARCEL_OM',
'SunYou Economic Air Mail'=>'SUNYOU_ECONOMY',
'Yanwen Economic Air Mail'=>'YANWEN_ECONOMY',
'AliExpress Saver Shipping'=>'CAINIAO_SAVER',
'AliExpress Standard Shipping'=>'CAINIAO_STANDARD',
'139 ECONOMIC Package'=>'ECONOMIC139',
'4PX RM'=>'FOURPX_RM',
'Asendia'=>'ASENDIA',
'Aramex'=>'ARAMEX',
'Austrian Post'=>'ATPOST',
'Bpost International'=>'BPOST',
'Canada Post'=>'CAPOST',
'CDEK'=>'CDEK',
'China Post Registered Air Mail'=>'CPAM',
'China Post Air Parcel'=>'CPAP',
'Chukou1'=>'CHUKOU1',
'CNE Express'=>'CNE',
'CORREOS PAQ 72'=>'SINOTRANS_AM',
'DHL Global Mail'=>'EMS_SH_ZX_US',
'DPD'=>'DPD',
'Enterprise des Poste Lao'=>'LAOPOST',
'ePacket'=>'EMS_ZX_ZX_US',
'Equick'=>'EQUICK',
'Flyt Express'=>'FLYT',
'GLS'=>'GLS',
'HongKong Post Air Mail'=>'HKPAM',
'HongKong Post Air Parcel'=>'HKPAP',
'J-NET'=>'CTR_LAND_PICKUP',
'Magyar Post'=>'HUPOST',
'Meest'=>'MEEST',
'Miuson Europe'=>'MIUSON',
'Mongol Post'=>'MNPOST',
'New Zealand Post'=>'NZPOST',
'Omniva'=>'EEPOST',
'One World Express'=>'ONEWORLD',
'PONY'=>'PONY',
'POS Malaysia'=>'POST_MY',
'Posti Finland'=>'ITELLA',
'PostNL'=>'POST_NL',
'RETS-EXPRESS'=>'RETS',
'Russia Parcel Online'=>'RPO',
'Russian Air'=>'CPAM_HRB',
'SF eParcel'=>'SF_EPARCEL',
'SFCService'=>'SFC',
'Singapore Post'=>'SGP',
'Special Line-YW'=>'YANWEN_AM',
'SunYou'=>'SUNYOU_RM',
'Sweden Post'=>'SEP',
'Swiss Post'=>'CHP',
'TaiwanPost'=>'TWPOST',
'TEA-POST'=>'TEA',
'Thailand Post'=>'THPOST',
'Turkey Post'=>'PTT',
'UBI'=>'UBI',
'Ukrposhta'=>'UAPOST',
'VietNam Post'=>'VNPOST',
'YODEL'=>'YODEL',
'YunExpress'=>'YUNTU',
'AliExpress Premium Shipping'=>'CAINIAO_PREMIUM',
'DHL'=>'DHL',
'DHL e-commerce'=>'DHLECOM',
'DPEX'=>'TOLL',
'EMS'=>'EMS',
'e-EMS'=>'E_EMS',
'GATI'=>'GATI',
'Russia Express-SPSR'=>'SPSR_CN',
'SF Express'=>'SF',
'Speedpost'=>'SPEEDPOST',
'TNT'=>'TNT',
'UPS Expedited'=>'UPSE',
'UPS Express Saver'=>'UPS',
'Fedex IE'=>'FEDEX_IE',
'Fedex IP'=>'FEDEX',
"Seller's Shipping Method"=>'Other',
'Russian Post'=>'RUSSIAN_POST',
'CDEK_RU'=>'CDEK_RU',
'IML Express'=>'IML',
'PONY_RU'=>'PONY_RU',
'SPSR_RU'=>'SPSR_RU',
"Seller's Shipping Method - RU"=>'OTHER_RU',
'USPS'=>'USPS',
'UPS'=>'UPS_US',
"Seller's Shipping Method - US"=>'OTHER_US',
'Royal Mail'=>'ROYAL_MAIL',
'DHL_UK'=>'DHL_UK',
"Seller's Shipping Method - UK"=>'OTHER_UK',
"Deutsche Post"=>'DEUTSCHE_POST',
'DHL_DE'=>'DHL_DE',
"Seller's Shipping Method - DE"=>'OTHER_DE',
'Envialia'=>'ENVIALIA',
'Correos'=>'CORREOS',
'DHL_ES'=>'DHL_ES',
"Seller's Shipping Method - ES"=>'OTHER_ES',
'LAPOSTE'=>'LAPOSTE',
'DHL_FR'=>'DHL_FR',
"Seller's Shipping Method - FR"=>'OTHER_FR',
'Posteitaliane'=>'POSTEITALIANE',
'DHL_IT'=>'DHL_IT',
"Seller's Shipping Method - IT"=>'OTHER_IT',
'AUSPOST'=>'AUSPOST',
"Seller's Shipping Method - AU"=>'OTHER_AU',
'JNE'=>'JNE',
'aCommerce'=>'ACOMMERCE'
];
					
				if (isset($mapping1[trim($shipped_row['shipping_method_name'])]) ){
						
					$shipping_method_code =$mapping1[trim($shipped_row['shipping_method_name']) ];
					$shipped_row['shipping_method_code']= $mapping1[trim($shipped_row['shipping_method_name']) ];
					
					$sql = "update od_order_shipped_v2 set shipping_method_code='$shipping_method_code' where  tracking_number ='".$ApiRequestModel->track_no."'";
					
					$command = Yii::$app->get('subdb')->createCommand($sql);
					$command->execute();
				}
					}
					//end of to be commented
					
					
					
					$shipping_method_code = $shipped_row['shipping_method_code'];
					$addi_info1['shipping_method_code'] = $shipping_method_code;
					$array1['shipping_method_code'] = $shipping_method_code;
					$aTracking->addi_info = json_encode($addi_info1);
				}
			}
			$array1['order_id'] = $aTracking->order_id;
			$ApiRequestModel->addinfo = json_encode($array1);
		}
		
		// ?????????????????????puid???  ???????????????false?????????????????????puid????????? 
		$puid = \Yii::$app->subdb->getCurrentPuid();
		$ApiRequestModel->puid = $puid;
		$key = $ApiRequestModel->puid ."-".$ApiRequestModel->track_no;
		
		//????????????????????????smt api ???????????????????????????????????????run time????????? -10?????? handler1 ???????????????
		$toNation2Code = $aTracking->getConsignee_country_code();
		if ($aTracking->source =='O' and $aTracking->platform =='aliexpress' and !empty($toNation2Code ) ){
			$ApiRequestModel->run_time = -10;
		}
		
		//???????????????
		if (!isset($CACHE["isVIP"]["p".$puid])){
			$CACHE["isVIP"]["p".$puid] = 0;
		}
		
		$ApiRequestModel->priority += $CACHE["isVIP"]["p".$puid];
		
		self::$Insert_Api_Queue_Buffer[$key] = $ApiRequestModel->getAttributes();
		$rtn['success'] = true;
		$rtn['message'] = "?????????????????????,????????????????????????!";
		//??????????????????????????????????????????????????? ?????????
		if ($user_require_update){
			//ystest starts
			//?????????????????????????????? addiinfo
			if ($aTracking->status <> Tracking::getSysStatus("???????????????")){
				$addi_info1 = [];
				if (!empty($aTracking->addi_info))
					$addi_info1 = json_decode($aTracking->addi_info,true);
			
				$addi_info1['last_status'] = $aTracking->status;
				$aTracking->addi_info = json_encode($addi_info1);
			}
			//ystest end
			
			$aTracking->status = Tracking::getSysStatus("???????????????");	
		}
		
		//?????? ??????????????????????????? 
		$tracking_addi_info = [];
		if (!empty($aTracking->addi_info))
			$tracking_addi_info = json_decode($aTracking->addi_info,true);
		
		$tracking_addi_info['last_manual_refresh_time'] = date('Y-m-d H:i:s');
		
		//??????create time ???2017-9-17 ?????????????????????????????????quota????????????????????????
		if ($aTracking->create_time >= '2017-09-17' and empty($tracking_addi_info['quotaUsed'])  ){

			//?????????????????????????????????????????????quota???,check quota sufficient when put to query queue
			$puid1 = $puid;
			$used_count = TrackingHelper::getTrackerUsedQuota($puid);
			$max_import_limit = TrackingHelper::getTrackerQuota($puid);
			if ($max_import_limit <= $used_count){
				$rtn['message'] = "????????????????????????????????????????????????????????????????????????????????????.";
				$rtn['success'] = false;
				unset(self::$Insert_Api_Queue_Buffer[$key]);
				$aTracking->status= 'quota_insufficient' ;
				$aTracking->state = 'exception';
			}else{
				$suffix = $CACHE['TrackerSuffix'.$puid1];
				TrackingHelper::addTrackerQuotaToRedis("trackerImportLimit_".$suffix ,  1);//20170912 ??????redisadd ????????????
				$tracking_addi_info['quotaUsed'] = 1;
			}
		}
		
		
		$aTracking->addi_info = json_encode($tracking_addi_info);
		$aTracking->save(false);
		return $rtn;		
	}//end of function generate One Request For Tracking
	
	
	
	static public function getSuccessCarrierFromHistoryByCodePattern($pattern,$forDate){
		$selected_carrier_code='';
		$successCount = 0;
		$carrier_success_rate = self::getTrackerTempDataFromRedis("carrier_success_rate_$forDate");
		$carrier_success_rate = json_decode($carrier_success_rate,true);
		if (isset($carrier_success_rate[$pattern])){
			//????????????code pattern????????????????????????????????????????????????carrier
			foreach($carrier_success_rate[$pattern] as $carrierCode=>$SuccessOrFail){
				if (isset($SuccessOrFail['Success']) and $SuccessOrFail['Success'] > $successCount)
					$selected_carrier_code = $carrierCode;
			}//end of each carrier in history for this pattern
		}
		$rtn['selected'] = $selected_carrier_code;
		$rtn['pattern']=$pattern;
		$rtn['forDate']=$forDate;
		return $selected_carrier_code;
	}
	
	static public function andForPuidLastTouchDuringHours($hours){
		global $CACHE;
		$now_str = date('Y-m-d H:i:s');
		$five_minutes_ago = date('Y-m-d H:i:s',strtotime('-5 minutes'));
		//??????3??????????????????
		//Step 1.1, ??????????????????????????????????????????????????????????????? request
		//??????3?????????????????????????????????????????????????????????????????????????????????????????????????????????
		if (!isset($CACHE['getPuidArrByInterval']["$hours hours"]['cache_time']) or
		$CACHE['getPuidArrByInterval']["$hours hours"]['cache_time'] < $five_minutes_ago ){
			$CACHE['getPuidArrByInterval']["$hours hours"]['puids'] = UserLastActionTimeHelper::getPuidArrByInterval($hours);
			$CACHE['getPuidArrByInterval']["$hours hours"]['cache_time'] = $now_str;
		}
		$puidsXHour = $CACHE['getPuidArrByInterval']["$hours hours"]['puids'];
			
		$puidXHourCriteria = "";
		if (count($puidsXHour) > 0)
			$puidXHourCriteria = " and puid in (".implode(",", $puidsXHour).") ";
			
		return $puidXHourCriteria;
	}


	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????????????????????????????? ????????????????????????????????????????????????????????????????????? ut_configData ???
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param  forDate               ????????????????????????????????????
	 * @param  puid			         puid
	 +---------------------------------------------------------------------------------------------
	 * @return						Array ( [created] => Array ( [Success] => Array ( [********************] => 1 [##*********##] => 12 [**************#] => 7 [**********] => 6 [*********] => 5 [***************] => 1 [**************] => 2 [**********************] => 4 [*##***************] => 1 ) ) [updated] => Array ( ) )
	 *
	 * @invoking					
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/3/2        		?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	public static function generateResultReportForPuid($forDate='',$puid='',$forApp=''){
		echo "\n generateResultReportForPuid 1";
		global $CAHCE;
		$result = array();
		if (empty($forDate))
			$forDate = date('Y-m-d');
		
		if (empty($puid)){
			$puid = \Yii::$app->subdb->getCurrentPuid();
		}
 
		if ($forApp=='' or $forApp=='Tracker'){
			 
		//Step 1??????????????????????????????????????????
		$created_order = array();			
		$TrackingAll = Tracking::find()->where("date(create_time)='$forDate'")->all();

		foreach ($TrackingAll as $aTracking){
			$code_format = CarrierTypeOfTrackNumber::getCodeFormatOfString($aTracking->track_no);
			//????????????0			
			if ($aTracking->status == Tracking::getSysStatus("????????????")){
				if (!isset($created_order['Fail'][$code_format]))
					$created_order['Fail'][$code_format] = 0;
				$created_order['Fail'][$code_format] ++;				
			}
			
			if ( !in_array( $aTracking->status, [Tracking::getSysStatus("????????????"),Tracking::getSysStatus("?????????"),
						Tracking::getSysStatus("???????????????") ,Tracking::getSysStatus("???????????????")
					   ,Tracking::getSysStatus("????????????") ]  ) ){
				if (!isset($created_order['Success'][$code_format]))
					$created_order['Success'][$code_format] = 0;
				$created_order['Success'][$code_format] ++;
			}
		}//end of each tracking

		//Step 2?????????????????????????????????????????????????????????????????????
		$updated_order = array();
		$TrackingAll = Tracking::find()->where("date(create_time)<>'$forDate' and date(update_time)='$forDate'")->all();
		foreach ($TrackingAll as $aTracking){
			$code_format = CarrierTypeOfTrackNumber::getCodeFormatOfString($aTracking->track_no);
			//????????????0
			if ($aTracking->status == Tracking::getSysStatus("????????????")){
				if (!isset($updated_order['Fail'][$code_format]))
					$updated_order['Fail'][$code_format] = 0;
				$updated_order['Fail'][$code_format] ++;
			}
				
			if (!in_array($aTracking->status, [Tracking::getSysStatus("????????????"),Tracking::getSysStatus("???????????????") ,
							Tracking::getSysStatus("????????????") ,Tracking::getSysStatus("???????????????") ,Tracking::getSysStatus("????????????"),
							Tracking::getSysStatus("?????????"), Tracking::getSysStatus("???????????????") ])    ){
				if (!isset($updated_order['Success'][$code_format]))
					$updated_order['Success'][$code_format] = 0;
				$updated_order['Success'][$code_format] ++;
			}
		}//end of each Tracking
		
		$result = array('created'=>$created_order,'updated'=>$updated_order);
		
		//step 3, ???????????????????????? ????????????????????????????????????????????????????????? complete state ??????????????? no_info ???
		$TrackingAll = Tracking::find()
					->select("carrier_type,count(1) as cc")
					->where(" state <>'".Tracking::getSysState("?????????")."' or status ='".Tracking::getSysStatus("????????????")."'")
					->andWhere(" status <>'".Tracking::getSysStatus("????????????")."'")
					->andWhere(" date(update_time)='$forDate'")
					->asArray()->groupBy("carrier_type")->all();
		
		$carrier_nation_distribute['carrier'] =array();
		foreach ($TrackingAll as $aTracking){
			$carrier_nation_distribute['carrier'][$aTracking['carrier_type'].""] = $aTracking['cc'];
		}

		//step 4, ???????????????????????? ??????????????????????????????????????????????????????????????? complete state ??????????????? no_info ???
		$TrackingAll = Tracking::find()
					->select("to_nation,count(1) as cc")
					->where(" state <>'".Tracking::getSysState("?????????")."' or status ='".Tracking::getSysStatus("????????????")."'")
					->andWhere(" status <>'".Tracking::getSysStatus("????????????")."'")
					->andWhere(" date(update_time)='$forDate'")
					->asArray()->groupBy("to_nation")->all();
		
		$carrier_nation_distribute['to_nation'] =array();
		foreach ($TrackingAll as $aTracking){
			$carrier_nation_distribute['to_nation'][$aTracking['to_nation'].""] = $aTracking['cc'];
		}
		
		//step 5, check how many is still pending, not complete
		$sql = "select count(1) from lt_tracking where status not in ('rejected') and state not in ('complete','unshipped') ";
		$command = Yii::$app->subdb->createCommand($sql);
		$os_count = $command->queryScalar();
		$carrier_nation_distribute['os_count'] = $os_count; 

		self::setTrackerTempDataToRedis("format_distribute_$forDate",json_encode($result));
		self::setTrackerTempDataToRedis("carrier_nation_distribute_$forDate",json_encode($carrier_nation_distribute));
		
		$result['carrier_nation_distribute'] = $carrier_nation_distribute;
		
		//step 6, ???????????????????????????tracking status ??????
		$sql = "select count(1) as total_count,status from lt_tracking  group by status ";
		$command = Yii::$app->subdb->createCommand($sql);
		$rows = $command->queryAll();
		foreach ($rows as $row){
			$result['status_pie'][$row['status']] = $row['total_count'];
		}
		
		//step 7, ??????input??????source????????? pie
		$sql = "select count(1) as total_count,status,source from lt_tracking  group by source,status ";
		$command = Yii::$app->subdb->createCommand($sql);
		$rows = $command->queryAll();
		foreach ($rows as $row){
			$result['source_pie'][$row['source']][$row['status']] = $row['total_count'];
		}
		
		//step 8, ??????????????????????????????
		$sql = "select count(1) as total_count  from lt_tracking  where state='".Tracking::getSysState("????????????")."'
					 and create_time >='".date('Y-m-d H:i:s',strtotime('-30 days'))."'";
		$command = Yii::$app->subdb->createCommand($sql);
		$result['Unshipped']['Days30_Count']  = $command->queryScalar();
		 
		$sql = "select count(1) as total_count  from lt_tracking  where state='".Tracking::getSysState("????????????")."'
					 and create_time >='".date('Y-m-d H:i:s',strtotime('-20 days'))."'";
		$command = Yii::$app->subdb->createCommand($sql);
		$result['Unshipped']['Days20_Count']  = $command->queryScalar();
		
		$sql = "select count(1) as total_count  from lt_tracking  where state='".Tracking::getSysState("????????????")."'
					 and create_time >='".date('Y-m-d H:i:s',strtotime('-10 days'))."'";
		$command = Yii::$app->subdb->createCommand($sql);
		$result['Unshipped']['Days10_Count']  = $command->queryScalar();
		
		self::setTrackerTempDataToRedis("Unshipped_pie_$forDate",json_encode($result['Unshipped']));
		
		if (isset($result['status_pie']))
			self::setTrackerTempDataToRedis("status_pie_$forDate",json_encode($result['status_pie']));
		
		}
		
		if ($forApp=='' or $forApp=='RecommendProd'){
			//global $CAHCE;
			//step 8, ??????????????????????????????????????????????????????????????? ?????????????????????
			$recommend_prod_sts = array();
			$result['Recm_prod_perform'] = array();
			$recommend_prod_sts['browse_count'] = 0;//Recommend_prod_browse_count_
			$browse_count_str = self::getTrackerTempDataFromRedis("Recommend_prod_browse_count_$forDate");
			if (!empty($browse_count_str)){
				$Recommend_prod_browse_count = json_decode( $browse_count_str,true);
			}else 
				$Recommend_prod_browse_count = array();

			//get send count
			$sql = "select count(1) as send_count,platform  from  message_api_queue where puid=$puid and status in ('C') and date(`update_time`) ='$forDate'   and content like '%littleboss.17track.net%' group by platform";
			$command1 = Yii::$app->db->createCommand($sql);
			$rows  = $command1->queryAll();
			$sendCount = array();
			foreach ($rows as $row){
				$sendCount[$row['platform']] = $row['send_count'];
			}
			//??????????????????in case???user?????? cs recommend prod??????prod perform?????????????????????????????????
			foreach ($rows as $row){
				if (empty($row['platform']))
					continue;
				$recommend_prod_sts = array();
				$recommend_prod_sts['prod_show_count'] = empty($row['v'])?0:$row['v'];
				$recommend_prod_sts['prod_click_count'] = empty($row['c'])?0:$row['c'];
				$recommend_prod_sts['browse_count'] = empty($Recommend_prod_browse_count[$row['platform']]) ?0:$Recommend_prod_browse_count[$row['platform']];
			
				$recommend_prod_sts['send_count'] = empty($sendCount[$row['platform']]) ?0:$sendCount[$row['platform']];
			
				$result['Recm_prod_perform'][$row['platform']] = $recommend_prod_sts;
			}
			
			//user?????? cs recommend prod??????prod perform ??????
			$sql = "select platform,sum(view_count) as v, sum(click_count) as c from cs_recm_product_perform , cs_recommend_product 
					where product_id = cs_recommend_product.id  and theday='$forDate' ";
			$command = Yii::$app->subdb->createCommand($sql);
			$rows = $command->queryAll();
			foreach ($rows as $row){
				if (empty($row['platform']))
					continue;
				$recommend_prod_sts = array();
				if (!empty($result['Recm_prod_perform'][$row['platform']]))
					$recommend_prod_sts = $result['Recm_prod_perform'][$row['platform']];
				$recommend_prod_sts['prod_show_count'] = empty($row['v'])?0:$row['v'];
				$recommend_prod_sts['prod_click_count'] = empty($row['c'])?0:$row['c'];
				$recommend_prod_sts['browse_count'] = empty($Recommend_prod_browse_count[$row['platform']]) ?0:$Recommend_prod_browse_count[$row['platform']];
				
				$recommend_prod_sts['send_count'] = empty($sendCount[$row['platform']]) ?0:$sendCount[$row['platform']];
				
				$result['Recm_prod_perform'][$row['platform']] = $recommend_prod_sts;
			}
			self::setTrackerTempDataToRedis("Recm_prod_perform_$forDate",json_encode($result['Recm_prod_perform']));
		}
		return $result;
	}
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????????????????????????????? ????????????????????????????????????????????????????????????????????? ut_configData ???
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param  forDate               ????????????????????????????????????
	 * @param  puid			         puid
	 +---------------------------------------------------------------------------------------------
	 * @return						Array ( [created] => Array ( [Success] => Array ( [********************] => 1 [##*********##] => 12 [**************#] => 7 [**********] => 6 [*********] => 5 [***************] => 1 [**************] => 2 [**********************] => 4 [*##***************] => 1 ) ) [updated] => Array ( ) )
	 *
	 * @invoking
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/3/2        		?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	public static function generateProdSalesReportForPuid($puid, $forApp=''){
		global $CAHCE;
		$result = array();
		if (empty($forDate))
			$forDate = date('Y-m-d');
	
		if (empty($puid)){
			$puid = \Yii::$app->subdb->getCurrentPuid();
		}
	
 
		//15 days before YS2 
	    $fromTime =strtotime('-15 days');
		
		if ($forApp=='' or $forApp=='RecommendProd'){
			//global $CAHCE;
			$recommend_prod_sts = array();
			$result['prodSales'] = array();

			//get send count
			$sql = "
					SELECT  order_source,platform_sku,sum(ordered_quantity) as qty, product_name, max(price) as price1, 
					photo_primary,product_url  FROM `od_order_v2` a, od_order_item_v2 b 
					WHERE a.`order_id` = b.order_id and order_source_create_time > $fromTime 
					and (consignee_country_code like 'FR' or consignee_country like 'FR')
					group by  order_source,platform_sku, 
					 product_name, photo_primary ,product_url order by sum(ordered_quantity)  desc
					"; //and (consignee_country_code like 'FR' or consignee_country like 'FR')
			//echo "for $puid, query this $sql \n";
			$command1 = Yii::$app->subdb->createCommand($sql);
			$rows  = $command1->queryAll();
		    $i = 0;
		    
		    $insertSQL="INSERT INTO `recprod` (`id`, puid, `order_source`, `platform_sku`, `qty`, `product_name`,
		    		 `price`, `photo_primary`, `product_url`) VALUES
						 ";
			//??????????????????in case???user?????? cs recommend prod??????prod perform?????????????????????????????????
			foreach ($rows as $row){
				if (    empty($row['order_source']) or $row['price1'] < 4)
					continue;
			    
			 
				
				$insertSQL .= ($i>0?",":'')."(NULL, $puid,'".self::removeYinHao($row['order_source'])."', 
						    '".self::removeYinHao($row['platform_sku'])."',
						    '".self::removeYinHao($row['qty'])."',
						    '".self::removeYinHao($row['product_name'])."',
						    '".self::removeYinHao($row['price1'])."', 
						    '".self::removeYinHao($row['photo_primary'])."',
						    '".self::removeYinHao($row['product_url'])."')";
				
				$i ++;
				if ($i > 10) break;
			}
			
			if ($i > 0){
				//echo "for $puid, insert this $insertSQL \n";
				$command = Yii::$app->db_queue->createCommand( $insertSQL );
				$affectRows = $command->execute();
			}
		}
		return $result;
	}
		
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ????????????????????????????????? ?????????????????????????????????????????????????????????Global??? ?????????????????? ???
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param  forDate               ????????????????????????????????????
	 * @param  reports		         array of each puid's report
	 * 									each report like:
	 * 									Array ( [created] => Array ( [Success] => Array ( [********************] => 1 [##*********##] => 12 [**************#] => 7 [**********] => 6 [*********] => 5 [***************] => 1 [**************] => 2 [**********************] => 4 [*##***************] => 1 ) ) [updated] => Array ( ) )
	 +---------------------------------------------------------------------------------------------
	 * @return						result consolidated, like Array ( [created] => Array ( [Success] => Array ( [********************] => 1 [##*********##] => 12 [**************#] => 7 [**********] => 6 [*********] => 5 [***************] => 1 [**************] => 2 [**********************] => 4 [*##***************] => 1 ) ) [updated] => Array ( ) )
	 *
	 * @invoking					
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/3/2        		?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	public static function generateConsolidatedReport($forDate,$reports){
		$consolidatedReport = array();
		foreach ($reports as $puid=>$aReport){			 
			self::arrayPlus($aReport, $consolidatedReport);
		}//end of each report
	 
		ConfigHelper::setGlobalConfig("Tracking/csld_format_distribute_$forDate",json_encode($consolidatedReport));
		return $consolidatedReport;
	}
	
	private static function arrayPlus($anArray, &$bigArray){
		foreach ($anArray as $k => $v){
			if (is_array($v)){
				self::arrayPlus($v, $bigArray[$k]);
			}else{
				//when it is number
				if (!isset($bigArray[$k]))
					$bigArray[$k] = 0;
				
				$bigArray[$k] += $v;
			}	
		}
	}
	
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????OMS???????????????OMS??????????????????????????? Shipped???Complete?????????
	 * OMS?????????????????????Shipped???Complete???????????????????????????????????????????????????????????????update_time???
	 * ?????????????????????update_time?????????????????????????????????supposed????????????????????????????????????
	 +---------------------------------------------------------------------------------------------
	
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param  puid                    ?????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @return						array('success'=true,'message'='')
	 *
	 * @invoking					TrackingHelper::copyTrackingFromOmsShippedComplete();
	 * @Call Eagle 1                http://erp.littleboss.com/api/GetOrderList?update_time=2015-2-1 13:51:25&puid=1
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/2/9				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function copyTrackingFromOmsShippedComplete($puid=''){
		$rtn['message'] = "";
		$rtn['success'] = true;
		$now_str = date('Y-m-d H:i:s');
	
		if (empty($puid))
			$puid = \Yii::$app->subdb->getCurrentPuid();

		/* ??????????????????
		//conversion for data bug fix.
		$conversion_sql = "update  `od_order_shipped_v2`  set `sync_to_tracker` ='' where sync_to_tracker='Y' 
							and created >=1456884000
							and `tracking_number` not in (select track_no from lt_tracking)";
		$command = Yii::$app->subdb->createCommand($conversion_sql );
		$affectRows = $command->execute();
		*/
		//$message = "???OMS??????puid $puid ????????????????????????";
		
		$all_orders = OrderTrackerApiHelper::getShippedOrderListModifiedAfter($puid);
		
		if (empty($all_orders)) $all_orders = array();
		
		$rtn['all_orders'] = $all_orders;
		
		//get all overdue data
		$del_orders =OrderTrackerApiHelper::getOverdueOrderShippedListModifiedAfter($puid);
		foreach($del_orders as &$del_order){
			//echo " order_id='".$del_order['order_id']."' and track_no='".$del_order['tracking_no']."' <br>";
			Tracking::deleteAll(['order_id'=>$del_order['order_id'] , 'track_no'=>$del_order['tracking_no']]);
			$rtn['deleted'][] = ['order_id'=>$del_order['order_id'] , 'track_no'=>$del_order['tracking_no'] ];
			$del_order = [];//release memory
			
		}//end of delete overdue data
		
		unset($del_orders);//release memory
//ystest
		//step 3.5, check if the seller id is unbinded
		$sellerIds = array();
		$sqls = array();
		//step 3.5.1: Load all binded smt and ebay account user ids
		$connection = Yii::$app->db;  
		$command = $connection->createCommand(
				"select selleruserid from saas_ebay_user where uid=$puid " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "".$aRow['selleruserid']."";
		//skip this,otherwise user deleted ones will show again.	$sqls [] = "update `od_order_shipped_v2` set `sync_to_tracker`='N'  WHERE `selleruserid`='".$aRow['selleruserid']."' and `tracking_number` not in (select track_no from lt_tracking where seller_id='".$aRow['selleruserid']."')";
		}
		
		$command = $connection->createCommand("select sellerloginid from saas_aliexpress_user where  uid=$puid " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "".$aRow['sellerloginid']."";
		//skip this,otherwise user deleted ones will show again.	$sqls [] = "update `od_order_shipped_v2` set `sync_to_tracker`='N'  WHERE `selleruserid`='".$aRow['sellerloginid']."' and `tracking_number` not in (select track_no from lt_tracking where seller_id='".$aRow['sellerloginid']."')";
		}
		$command = $connection->createCommand("select sellerloginid from saas_dhgate_user where  uid=$puid " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "".$aRow['sellerloginid']."";
			//skip this,otherwise user deleted ones will show again.	$sqls [] = "update `od_order_shipped_v2` set `sync_to_tracker`='N'  WHERE `selleruserid`='".$aRow['sellerloginid']."' and `tracking_number` not in (select track_no from lt_tracking where seller_id='".$aRow['sellerloginid']."')";
		}	

		$command = $connection->createCommand("select platform_userid from saas_lazada_user where  puid=$puid  " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){		
			$sellerIds[] = "".$aRow['platform_userid']."";
		}
		
		$command = $connection->createCommand("select username from saas_cdiscount_user where  uid=$puid " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "".$aRow['username']."";		
		}
				
		$command = $connection->createCommand("select store_name from saas_wish_user where  uid=$puid " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "".$aRow['store_name']."";
		}
		
		$command = $connection->createCommand("select store_name from saas_ensogo_user where  uid=$puid " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "".$aRow['store_name']."";
		}
		
		$command = $connection->createCommand("select merchant_id  from saas_amazon_user where  uid=$puid " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "".  str_replace("'","\'",$aRow['merchant_id'])  ."";
		}
		
		$command = $connection->createCommand("select username from saas_priceminister_user where  uid=$puid  " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "".str_replace("'","\'",$aRow['username'])  ."";
		}
		
		$command = $connection->createCommand("select store_name from saas_bonanza_user where  uid=$puid  " ) ;
		$rows = $command->queryAll();
		foreach ($rows as $aRow ){
			$sellerIds[] = "".str_replace("'","\'",$aRow['store_name'])  ."";
		}
		
		foreach ($sqls as $sql){
			$command = Yii::$app->subdb->createCommand($sql );
			$affectRows = $command->execute();
		}
		//ystest
		 
		
		//step 4, for each order, get its tracking number and add to tracking in this module
		$insertedCount = 0;
		
		if (!empty($all_orders) and is_array($all_orders))
		foreach ($all_orders as $anOrder){
			if (empty($anOrder['tracking_no']))
				continue;
			$rtn['inserted'][] = ['tracking no'=>$anOrder['tracking_no'] ,"order id"=>$anOrder['order_id'] ];
			//step 4.1 , ?????????????????????????????????copy???tracker
			if (! in_array($anOrder['selleruserid'], $sellerIds))
				continue;
			
			$aTracking = array();
			$return_no = array();
			$aTracking['track_no'] = $anOrder['tracking_no'];
			$aTracking['order_id'] = $anOrder['order_id'];
			$aTracking['seller_id'] = $anOrder['selleruserid'];
			$aTracking['platform'] = strtolower( $anOrder['order_source'] );	
			$aTracking['ship_by'] = $anOrder['carrier_name']. (empty($anOrder['carrier'])?"":$anOrder['carrier']);
			$aTracking['ship_out_date'] = date('Y-m-d H:i:s',$anOrder['paid_time']);
			if (empty($aTracking['ship_out_date'] ) or $aTracking['ship_out_date'] <'1990-01-01')
				$aTracking['ship_out_date'] = date('Y-m-d',time());
			
			//$aTracking['paid_time'] = date('Y-m-d H:i:s',$anOrder['paid_time']);
			$addi_info['consignee_country_code'] = $anOrder['consignee_country_code'];
			$addi_info['carrier_name'] = $anOrder['carrier_name'];
			$addi_info['shipping_method_code'] = $anOrder['shipping_method_code'];
			
			if (!empty($anOrder['return_no']))
				$return_no = unserialize($anOrder['return_no']);
			
			if (!empty($return_no['TrackingNo']))
				$addi_info['return_no'] = $return_no['TrackingNo'];//????????????????????????????????????????????????????????????????????????tracking
			
			//??????order????????????????????????????????????
			if (!empty($anOrder['paid_time']))
				$addi_info['order_paid_time'] = $anOrder['paid_time'];
			
			$aTracking['addi_info'] = json_encode($addi_info,true);
			
			//???????????????new tracking ????????????????????????????????????DB
			$rtn1 = self::addTracking($aTracking,"O");
			if (!$rtn1['success']){
				$message = "Failed to insert tracking for $puid - ".$aTracking['track_no'];
				$rtn['message'] .= $message;
				\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Background',$message],"edb\global");			
			}else 
				$insertedCount ++;
			
		}//end of each order
		
		//call this to put all Tracking into DB
		self::postTrackingBufferToDb();
		
		//\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Background',"Copy from OMS for $puid, got $insertedCount" ],"edb\global");
		//step 5, mark the last order retrieve time as this shot
		//update the last retrieve time only when we really got result, if empty returned or failed connetion, do not update, so that next run will retry this time frame
		if (!empty($all_orders) and is_array($all_orders) and count($all_orders)>0){
			self::setTrackerTempDataToRedis("last_retrieve_shipped_order_time",$now_str);
			
		 
			$count = self::getTrackerTempDataFromRedis(date('Y-m-d')."_inserted");
			if (empty($count))
				$count = 0;
			
			$count += $insertedCount;
			self::setTrackerTempDataToRedis(date('Y-m-d')."_inserted", $count);
			
			//delete yesterday record
			self::delTrackerTempDataToRedis(date('Y-m-d',strtotime('-1 day'))."_inserted");
			
			$rtn['count'] = $insertedCount;
		}else
			$rtn['count'] = 0;
		
		//force update the top menu statistics
		self::setTrackerTempDataToRedis("left_menu_statistics", json_encode(array()));			
		self::setTrackerTempDataToRedis("top_menu_statistics", json_encode(array()));

		return $rtn;
	}
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????OMS???????????????Order???????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param  track_no             ????????????
	 +---------------------------------------------------------------------------------------------
	 * @return				array('success'=true,'message'='',order='')
	 *
	 * @invoking			TrackingHelper::getOrderDetailFromOMSByTrackNo();
	 * @Call Eagle 1        http://erp.littleboss.com/api/GetOrderDetail?track_no=RG234234232CN&puid=1
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/2/9				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function getOrderDetailFromOMSByTrackNo($track_no){
		$detail = OrderTrackerApiHelper::getOrderDetailByTrackNo($track_no);
		$rtn = ['success'=>true , 'message'=>'' , 'order'=>$detail , 'url'=>''];
		return $rtn;
		
		/* test kh  */
		/*
		if (isset(\Yii::$app->params["currentEnv"]) and \Yii::$app->params["currentEnv"]=="test" ){
			$DUMP_OMS_DATA =  '{"order_id":"00000037852","order_status":"500","pay_status":"0","order_source_status":"WAIT_BUYER_ACCEPT_GOODS","order_manual_id":"0","is_manual_order":"0","shipping_status":"0","exception_status":"0","order_source":"aliexpress","order_type":"","order_source_order_id":"67319632656486","order_source_site_id":"","selleruserid":"cn118912741","saas_platform_user_id":"0","order_source_srn":"0","customer_id":"26693","source_buyer_user_id":"cr1094836136","order_source_shipping_method":"","order_source_create_time":"1431953581","subtotal":"11.37","shipping_cost":"0.00","discount_amount":"0.00","grand_total":"11.37","returned_total":"0.00","price_adjustment":"0.00","currency":"USD","consignee":"Dania Espinoza","consignee_postal_code":"08805","consignee_phone":"1 732-7898222","consignee_mobile":"","consignee_email":"macha.89@hotmail.com","consignee_company":"","consignee_country":"United States","consignee_country_code":"US","consignee_city":"Boun Brook","consignee_province":"New Jersey","consignee_district":"","consignee_county":"","consignee_address_line1":"302 East","consignee_address_line2":"","consignee_address_line3":"","default_warehouse_id":"0","default_carrier_code":"","default_shipping_method_code":"","paid_time":"1431953722","delivery_time":"1432486623","create_time":"1433367347","update_time":"1434400045","user_message":"","carrier_type":"0","hassendinvoice":"0","seller_commenttype":"","seller_commenttext":"","status_dispute":"0","is_feedback":"0","rule_id":null,"customer_number":null,"carrier_step":"0","is_print_picking":"0","print_picking_operator":null,"print_picking_time":null,"is_print_distribution":"0","print_distribution_operator":null,"print_distribution_time":null,"is_print_carrier":"0","print_carrier_operator":null,"printtime":"0","delivery_status":"0","items":[{"order_item_id":"59522","order_id":"00000037852","order_source_srn":"0","order_source_order_item_id":"59556","source_item_id":"","sku":"AW-SB-1128","product_name":"New Fashion Floral Flower GENEVA Watch GARDEN BEAUTY BRACELET WATCH Women Dress Watches Quartz Wristwatch Watches AW-SB-1128","photo_primary":"http:\/\/g02.a.alicdn.com\/kf\/HTB1IwuDGFXXXXb_XVXXq6xXFXXXx.jpg_50x50.jpg","shipping_price":"0.00","shipping_discount":"0.00","price":"3.79","promotion_discount":"0.00","ordered_quantity":"3","quantity":"3","sent_quantity":"0","packed_quantity":"0","returned_quantity":"0","invoice_requirement":"","buyer_selected_invoice_category":"","invoice_title":"","invoice_information":"","remark":null,"create_time":"1433367346","update_time":"1433367346","platform_sku":"AW-SB-1128","is_bundle":"0","bdsku":""}]}';
			return ['success'=>true,'order'=>json_decode($DUMP_OMS_DATA,true)];
		}

//		\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Online',"Try to load oms for ship out date ".$track_no ],"edb\global");
		$puid = \Yii::$app->subdb->getCurrentPuid();
		$rtn['message'] = "";
		$rtn['success'] = true;
		$rtn['order'] = array();
		$now_str = date('Y-m-d H:i:s');

		$EAGLE_1_API_URL = "https://erp.littleboss.com/api/GetOrderDetail?token=tyhedfgS823_E2348,DFdgy&track_no=@track_no&puid=$puid";
		$target_url = str_replace("@track_no",$track_no,$EAGLE_1_API_URL);
	 	
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $target_url);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);//??????????????????
		$resultStr = curl_exec($curl);
		//$resultStr = curl_getinfo($curl);
		curl_close($curl);
		
		$rtn['order'] = json_decode($resultStr,true);	
		$rtn['url']=$target_url;
		return $rtn;
		*/
	}
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param string $date ??????
	 * @param string $formats ???????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @return boolean
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/2/14				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function checkDateIsValid($date, $formats = array("Y-m-d", "Y/m/d")) {
		$unixTime = strtotime($date);
		if (!$unixTime) { //strtotime??????????????????????????????????????????
			return false;
		}else{
			return true;
		}
		//?????????????????????????????????
		//????????????????????????????????????????????????????????????OK
		foreach ($formats as $format) {
			if (date($format, $unixTime) == $date) {
				return true;
			}
		}
	
		return false;
	}//end of checkDateIsValid

	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ???????????? ?????????????????? ????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param array $data ???????????????excel?????????????????????
	 * 				[
	 * 					'0'=> ['4px', '2015-02-14','RXXXXCN' , 'OD001'] ,......
	 * 				]
	 +---------------------------------------------------------------------------------------------
	 * @return array	$result ['success'] boolean
	 * 					$result ['message'] string ???????????????????????????
	 * 					$result ['ImportDataFieldMapping']  array ???????????????excel???????????????????????????col????????????????????????
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/2/14				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function checkManualImportFormat($data){
		$result ['success'] = true;
		$result ['message'] = '';
		$result ['ImportData'] = [];
		
		if (! is_array($data)){
			$result ['success'] = false;
			$result ['message'] = TranslateHelper::t('???????????????');
			return $result;
		}
		
		//1.????????????????????? 
		
		//????????? ??? ????????????
		if (count($data) == 0){
			$result ['success'] = false;
			$result ['message'] = TranslateHelper::t('???????????????');
			return $result;
		}
		
		//2.???????????????????????????
		foreach($data as $onetrack){
			//????????? ??? ????????????
			if (count($onetrack) == 0){
				$result ['success'] = false;
				$result ['message'] = TranslateHelper::t('???????????????');
				return $result;
			}
			
			// ??????????????????????????????
			if (count($onetrack)==1){
				$fieldArr['track_no'] = 0;
				$result ['ImportDataFieldMapping'] = $fieldArr;
				return $result;
			}
		}
		

		$fieldArr = [];
		// ?????????????????? ????????????????????? ??????????????????????????????
		foreach($data as $onetrack){
			//?????????4???array
			for($i = 0;$i<count($onetrack);$i++){
				
				$sortList[$i][] =trim($onetrack[$i]);
			}
		
		}
		 
		//3.??????????????????
		 
		//??????????????? ???????????????
		
		$ShipByList = []; // ??????????????????????????? 
		//$ShipByResult = Yii::$app->get('subdb')->createCommand("select distinct ship_by from lt_tracking where ifnull(ship_by,'')<> ''")->queryAll();
		$ShipByList = json_decode (self::getTrackerTempDataFromRedis("using_carriers" ),true);
		/*
		foreach($ShipByResult as $tmprow){
			$ShipByList[] =  $tmprow['ship_by']; 
		}
		*/
		
		$repeatColumn = []; //???????????????????????? 
		
		for($i = 0;$i<count($sortList);$i++):
			//???????????? ?????? 
			$tmpNoRepeat[$i] = array_unique($sortList[$i]);
			//????????????????????????
			if (count($tmpNoRepeat[$i]) < count($sortList[$i])){
				$repeatColumn[] = $i;
			}
			
			foreach($sortList[$i] as $value){
				if (trim($value)=="") continue;
				//??????????????????
				if (self::checkDateIsValid($value)):
					//$fieldArr[$i] = 'ship_out_date';
					$fieldArr['ship_out_date'] = $i;
					break;
				endif;
				
				//?????????????????????
				if (is_numeric($value)):
					//$fieldArr[$i] = 'delivery_fee';
					$fieldArr['delivery_fee'] = $i;
					break;
				endif;
				 
				//???????????????????????????
				if (in_array($value, $ShipByList)):
					//$fieldArr[$i] = 'ship_by';
					$fieldArr['ship_by'] = $i;
					break;
				endif;
				
				//????????????????????????
				unset($is_track_no);
				
				$is_track_no = CarrierTypeOfTrackNumber::checkExpressOftrackingNo($value);
				if (! empty($is_track_no)){
					$fieldArr['track_no'] = $i;
					break;
				}
				
			}
		
			//$sort_result[$i] = array_count_values($sortList[$i]);
		endfor;
		
		//???????????????column ?????????ship_by
		
		/******************            ?????????????????????, ?????????????????????                           *****************/
		
		//--- ?????????column?????? ?????? ????????????($fieldArr) ????????? ???????????????, ???????????????column
		$repeat_diff_result =  array_diff($repeatColumn, $fieldArr);
		
		/*	
		 * ????????? , ???????????????????????????????????????????????? , ???????????????"??????"??????
		 * ????????????:
		 * 		??????1: ???????????????????????? :
		 * 			???????????? :A ?????????????????? , ???????????????????????????  , ?????????????????? ;
		 * 					B ????????????????????????????????? , ?????????????????????????????????column , ?????????????????????column , ?????? ?????????????????????;
		 * 		??????2: ?????????????????????:
		 * 			?????? :A ???????????? 
		 * 						1.track no ????????????????????? , ????????????track no 
		 * 						2.track no ????????????  ???????????????????????????  
		 * 							????????????: 1.?????? ??????1?????????   ??????????????????????????? , ?????????????????????????????????
		 * 									2.?????? ??????1?????????  ??????????????? 
		 * 				B ???????????? 
		 * 						1.	track no ????????????????????? 
		 * 							????????????:1.???????????????(ship_by)??????????????????, ?????????????????????track no , ?????????order id 
		 * 						2.  track no ???????????? 
		 * 							????????????: ???????????????????????????????????? ?????? , ??????????????????	
		 * 				C ???????????? 
		 * 						1.	track no ????????????????????? 
		 * 							????????????:1.?????????????????????????????????
		 * 								   2.?????????????????????, ??????????????????????????? ,??????????????????, ???????????????
		 * 						2.  track no ???????????? 
		 * 							????????????:1. ???????????? ,
		 * 				D ???????????? 	
		 * 						1.	track no ????????????????????? 
		 * 							????????????:1. ????????????  
		 * 						2.  track no ???????????? 
		 * 							????????????:1. ???????????? 
		 * 						???????????? ???????????? ??????	
		 * 
		 */ 
		
		
		// ??????????????? ?????????column 
		if (isset($repeat_diff_result) ){
			// ??????1: ????????????????????????  :???????????? ????????????????????????????????????????????? , ?????????????????????????????????
			if (count($repeat_diff_result)>0){
				//??????1-A ?????????????????? , ???????????????????????????  , ?????????????????? 
				if (count($repeat_diff_result) == 1 && empty($fieldArr['ship_by'])){
					$fieldArr['ship_by'] = current($repeat_diff_result);
				}else{
					//??????1-B?????????????????????????????????column , ?????????????????????column , ?????? ?????????????????????
					$result ['success'] = false;
					$result ['message'] = TranslateHelper::t(' ????????????????????? ?????? ????????????????????????,?????????excel??????');
					return $result;
				}
			}
		}
		
		//??????2: ?????????????????????:
		
		//?????????column???????????? 
		$rest_count = count($sortList)-count($fieldArr);
		//??????????????????????????????
		$tmpfieldArr = array_flip($fieldArr);
		//????????????????????? 
		if ($rest_count == 1 ){
			//??????2-A-1: ???????????? ??????????????? ??? track no ????????????????????? , ????????????track no 
			if ((! isset($fieldArr['track_no']) )){
				for($i = 0;$i<count($sortList);$i++):
				if (!array_key_exists($i,$tmpfieldArr)):
				$fieldArr['track_no'] = $i;
				break;
				endif;
				endfor;
					
				$result ['ImportDataFieldMapping'] = $fieldArr;
				return $result;
			}else{
				//??????2-A-2 track no ????????????  ???????????????????????????   (?????? ????????????????????????????????????????????????????????? , ?????????????????????????????????)
				$existTrackNo_restColumn =  array_diff(self::$EXCEL_COLUMN_MAPPING , $tmpfieldArr);
				//?????? ????????????????????????column ?????? ????????????????????????
				if (!empty ($existTrackNo_restColumn)){
					//?????? ?????????column index
					for($i = 0;$i<count($sortList);$i++):
						if (!array_key_exists($i,$tmpfieldArr)):
							if (count($sortList[$i])>1){
								//??????2-A-2-1 ?????? ????????????????????????????????????????????????????????? , ?????????????????????????????????
								$fieldArr['order_id'] = $i;
								break;
							}else{
								//??????2-A-2-2 ???????????????????????? ,???????????? 
								$result ['success'] = false;
								$result ['message'] = TranslateHelper::t('????????????????????????,?????????excel??????');
								return $result;
							}
						endif;
					endfor;
				}else{
					// ??????????????????????????? , ?????????????????? ??????
					$result ['success'] = false;
					$result ['message'] = TranslateHelper::t('????????????????????????,?????????excel??????');
					return $result;
				}
				
			}
			
		
		}elseif ($rest_count == 2 ){
			//??????2-B-1 track no ?????????????????????
			if ((! isset($fieldArr['track_no']) )){
				
				if (isset($fieldArr['ship_by'])){
					//??????ship by
					for($i = 0;$i<count($sortList);$i++):
					if (!array_key_exists($i,$tmpfieldArr)):
				
					if(!isset($fieldArr['track_no']))
						$fieldArr['track_no'] = $i;
					else
						$fieldArr['order_id'] = $i;
						
					//??????????????????????????????
					$tmpfieldArr = array_flip($fieldArr);
					endif;
					endfor;
				}else{
					// ?????????ship by ??????????????? 1.?????????????????????????????????????????????,2????????????????????????
					$result ['success'] = false;
					$result ['message'] = TranslateHelper::t('????????????????????????,?????????excel??????');
					return $result;
				}
			}else{
				//??????2-B-2 track no ???????????? 
				$result ['success'] = false;
				$result ['message'] = TranslateHelper::t('????????????????????????,?????????excel??????');
				return $result;
			}
				
		}elseif ($rest_count == 3  ){
			//??????2-C-1 track no ????????????????????? 
			if ((! isset($fieldArr['track_no']) )){
				//?????????3????????????
				if (isset($fieldArr['ship_by'])){
					//?????????????????????????????????
					$result ['success'] = false;
					$result ['message'] = TranslateHelper::t('????????????????????????,?????????excel??????');
					return $result;
				}else{
					//?????????, ??????????????? ,??????????????????, ???????????????
					for($i = 0;$i<count($sortList);$i++):
					if (!array_key_exists($i,$tmpfieldArr)):
				
					if (!isset($fieldArr['ship_by'])){
						$fieldArr['ship_by'] = $i;
						$tmpfieldArr = array_flip($fieldArr);
						continue;
					}
						
					if(!isset($fieldArr['track_no']))
						$fieldArr['track_no'] = $i;
					else
						$fieldArr['order_id'] = $i;
				
					//??????????????????????????????
					$tmpfieldArr = array_flip($fieldArr);
					endif;
					endfor;
						
				}
			}else{
				//??????2-C-2
				$result ['success'] = false;
				$result ['message'] = TranslateHelper::t('????????????????????????,?????????excel??????');
			}
				
		}elseif($rest_count > 3  ){
			//??????2-D-1
			if ((! isset($fieldArr['track_no']) )){
				
				//??????3????????????
				$result ['success'] = false;
				$result ['message'] = TranslateHelper::t('????????????????????????,?????????excel??????');
				return $result;
			}else{
				//??????2-D-2
				$result ['success'] = false;
				$result ['message'] = TranslateHelper::t('????????????????????????,?????????excel??????');
				return $result;
			}
			
		}
		
		$result ['ImportDataFieldMapping'] = $fieldArr;
		return $result;
	}//end of checkManualImportFormat
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? ?????? ???HTML ??????  , ??????????????? ?????? , ????????????????????????view?????? , ?????????????????????function ???
	 +---------------------------------------------------------------------------------------------
	 * @access static 
	 +---------------------------------------------------------------------------------------------
	 * @param string $remark  
	 * @param string $sort  desc ???????????? (default) , asc ???????????????
	 +---------------------------------------------------------------------------------------------
	 * @return array	remark HTML STR
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/2/15				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function generateRemarkHTML($remark , $sort = 'desc'){
		//????????????????????????json decode
		if (is_array($remark)){
			$RemarkList = $remark;
		}else{
			$RemarkList = json_decode($remark,true);
		}
		
		if (!empty($RemarkList)){
			if (strtolower($sort)== 'desc' )
				$reSortRemarklist = $reSortRemarklist = array_reverse($RemarkList);
			else
				$reSortRemarklist = $RemarkList;
		}else $reSortRemarklist = [];
		
		
		$result = "<section>";
			foreach($reSortRemarklist as $oneRemark):
			//<dt><small>".$oneRemark['who']."</small> </dt>
			$result .="
				<dl>
					<dt><small><time>".$oneRemark['when']."</time></small></dt>
					<dd><small>".nl2br($oneRemark['what'])."</small></dd>
				</dl>";
			endforeach;
			$result .= "</section>"; 
		return $result;
	}//end of generateRemarkHTML
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? ???????????? ????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param array $track no  ????????????,?????????????????? ,??????????????????????????????????????? 
	 * @param array $langList  = [['123'=>'zh-cn']] ????????????????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @return array	$result [$track no] HTML STR
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/2/15				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function generateTrackingEventHTML($TrackingList,$langList=[],$is_vip=false){
		
		$all_events_str = [];
		$translateBtn = "";
		$platFormTitle = "";
		
		if (empty($toLang))
			$tolang = TranslateHelper::getCurrentLanguague();
		
		foreach($TrackingList as $track_no):
		if ( !empty($langList[$track_no]))
			$lang = $langList[$track_no];
		else 
			$lang = "";
		$model = Tracking::find()->andWhere("track_no=:track_no",array(":track_no"=>$track_no))->one();
		
		//????????? ??????
		if (empty($model)) continue;
		//?????? ????????????
		$CarrierTypeStr='';
		//?????????  carrier_type ?????????0  , ?????????????????????
		if (isset($model->carrier_type) && ! in_array(strtolower($model->status) , ['checking',"?????????","???????????????"]) ){
			if (isset(CarrierTypeOfTrackNumber::$expressCode[$model->carrier_type]))
				$CarrierTypeStr = " <h6><span style='margin-right:24px;font-size:14px;'></span><span class='text-muted'>(".TranslateHelper::t('??????')."</span>".CarrierTypeOfTrackNumber::$expressCode[$model->carrier_type]."<span class='text-muted'>".TranslateHelper::t('??????????????????').")</span></h6>";
		}
		
		$tmp_rt = self::getTrackingAllEvents($track_no,$lang);
		
		if (!empty($tmp_rt['allEvents']))
			$all_events = $tmp_rt['allEvents'];
		else 
			$all_events = [];
		
		//??????
		if (! empty($model->platform)){
			$platFormMapping = [
				'ebay'=>'Ebay',
				'sms'=>'?????????',
			];
			
			if (! empty($platFormMapping[$model->platform])){
				$platFormTitle = "<span class=\"label label-default\" style=\"margin-left: 30px;\">".$platFormMapping[$model->platform]."</span>";
			}
		}
		if (empty($model->to_nation)||  $model->to_nation == '--'){
			$model->to_nation = $model->getConsignee_country_code();
		}
		
		//?????????
		if (! empty($model->to_nation)){
			$to_nation = self::autoSetCountriesNameMapping($model->to_nation);	
		}else{
			$to_nation = self::autoSetCountriesNameMapping('--');
		}
		
		//?????????
		if (! empty($model->from_nation)){
			$from_nation = self::autoSetCountriesNameMapping($model->from_nation);		
		}else {
			$from_nation = self::autoSetCountriesNameMapping('--');
		}
		
		//parlce type is?????????????????????EMS 
		$parcel_type_label = Tracking::get17TrackParcelTypeLabel($model->parcel_type);
	/*
		if (empty($lang)){
			$translateBtn = "<div class=\"col-md-2\" > <input id='tsl_$track_no' class='btn btn-info' style='font-size: 12px' data-translate-code='' data-loading-text='".TranslateHelper::t('?????????')."' value='".TranslateHelper::t('???????????????')."' type=\"button\"  onclick=\"ListTracking.translateContent('".$track_no."','$tolang',this)\"/>   </div>";
		}else{
			$translateBtn = "<div class=\"col-md-2\" > <input id='tsl_$track_no' class='btn btn-info' data-translate-code='$tolang' data-loading-text='".TranslateHelper::t('?????????')."'  value='".TranslateHelper::t('????????????')."' type=\"button\"  onclick=\"ListTracking.translateContent('".$track_no."','',this)\"/>   </div>";
		}
	*/	
		/*
		 * 
		 * ????????????link????????????????????? lt_tracking.carrier_type, 
		 * 1. ??????carrier_type >0, url = EXPRESS_ENUM. b, where EXPRESS_ENUM.a = carrier_type .
		 * 2. if carrier_type =0, url = POST_ENUM.x.b,  x = 17TrackNationCode * 10 + parcel_type.
		 *  
		 * */
		$FromOfficialLink =""; // ???????????????link
		$ToOfficialLink = "";  // ???????????????link
		if (isset($model->carrier_type)){
			//carrier_type ?????? ?????????????????? ??????link
			if ($model->carrier_type > 0 ){
				// carrier_type > 0 ???dhl ????????? ??? ?????????????????? ?????????
				$FromOfficialLink = Tracking::get17TrackExpressUrlByCode($model->carrier_type);
				$ToOfficialLink = $FromOfficialLink;
			}elseif ($model->carrier_type == 0 ){
				// carrier_type = 0 ???ems ????????? ??? ?????????????????? ????????????
				if (!empty($model->from_nation)){
					unset($from_nation_code);
					if (isset($model->parcel_type)){
						if (is_numeric($model->parcel_type)){
							$nation_code = Tracking::get17TrackNationCodeByStandardNationCode($model->from_nation);
							$from_nation_code = $nation_code*10+ $model->parcel_type;
						}
					}
					
					if (!empty($from_nation_code))
						$FromOfficialLink = Tracking::get17TrackNationUrlByCode($from_nation_code);
				}
				
				if (!empty($model->to_nation)){
					unset($to_nation_code);
					if (isset($model->parcel_type)){
						if (is_numeric($model->parcel_type)){
							$nation_code = Tracking::get17TrackNationCodeByStandardNationCode($model->to_nation);
							$to_nation_code = $nation_code*10+ $model->parcel_type;
						}
					}
						
					if (!empty($to_nation_code))
					$ToOfficialLink = Tracking::get17TrackNationUrlByCode($to_nation_code);
				}
				
				
			}
			
		}
		
		
		if (!empty($FromOfficialLink)){
			$fromOfficialLinkHtml = "<small style=\"margin-left: 42px;\"><a href='$FromOfficialLink' target=\"target\">".TranslateHelper::t('??????????????????')."</a></small>";
		}else{
			$fromOfficialLinkHtml = "";
		}
		
		
		if (!empty($ToOfficialLink)){
			$toOfficialLinkHtml = "<small style=\"margin-left: 42px;\"><a href='$ToOfficialLink' target=\"target\">".TranslateHelper::t('??????????????????')."</a></small>";
		}else{
			$toOfficialLinkHtml = "";
		} 
		
		
		// ?????? ????????? : ????????? , ?????????
		/*
		$all_events_str[$track_no] = '<dd>'.
				'<div class="col-md-12 toNation">'. 
						'<h6> <span class="glyphicon glyphicon-gift" aria-hidden="true" style="margin-right: 12px;font-size: 14px;"></span>'.
						'<span class="text-muted">'.TranslateHelper::t('????????????').
						'</span>'.
						': '.$to_nation.$toOfficialLinkHtml.$platFormTitle.'</h6>'.
				'</div>'.
				$translateBtn.'</dd>';
				*/
		$all_events_str[$track_no] = "";
		
		//??????????????????
		if (is_array($all_events)){
			foreach($all_events as $anEvent){
				//??????lang ?????????????????? , ????????????base 64 ?????? , ?????????????????? 
				if (empty($lang)){
					$anEvent['where'] = base64_decode($anEvent['where']);
					$anEvent['what'] = base64_decode($anEvent['what']);
					//??????????????????????????????????????????????????? 1900 ???
					if (!empty($anEvent['when']) and strlen($anEvent['when']) >=10 and substr($anEvent['when'],0,10)<'2014-01-01' )
						$anEvent['when'] = ''; 
					/*
					if (empty($all_events_str[$track_no]))
						$translateBtn = "<div class=\"col-md-2\" > <input id='tsl_$track_no' class='btn btn-info' data-translate-code='' data-loading-text='".TranslateHelper::t('?????????')."' value='".TranslateHelper::t('???????????????')."' type=\"button\"  onclick=\"ListTracking.translateContent('".$track_no."','$tolang',this)\"/>   </div>";
					else 
						$translateBtn = "";
						*/
				}else{
					/*
					if (empty($all_events_str[$track_no]))
						$translateBtn = "<div class=\"col-md-2\" > <input id='tsl_$track_no' class='btn btn-info' data-translate-code='$tolang' data-loading-text='".TranslateHelper::t('?????????')."'  value='".TranslateHelper::t('????????????')."' type=\"button\"  onclick=\"ListTracking.translateContent('".$track_no."','',this)\"/>   </div>";
					else
						$translateBtn = "";
						*/
				}
				
				if (!empty($anEvent['type'])){
					$class_nation = $anEvent['type'];
				}
				
				if (empty($className)){
					$className = 'orange_bold';
				}else{
					$className = 'font_normal';
				}
				
				//detail view message
				//$all_events_str[$track_no] .= $anEvent['when'].$anEvent['where'].$anEvent['what'].".<br>";
				
				$all_events_str[$track_no]  .= '<dd>'.
						'<div class="col-md-12 '.$className.'">'.
						'<i class="'.(($className=='orange_bold')?"egicon-arrow-on-yellow":"egicon-arrow-on-gray").'"></i>'.
						'<time '.(($className=='orange_bold')?'style="color: #f0ad4e;" ':'').'>'. $anEvent['when'].'</time>'.
						'<p>'.$anEvent['where'].((empty($anEvent['where']))?"":",").
						$anEvent['what']."</p></div>".
						"</dd>";
			}
		
		}
		
		$all_events_str[$track_no] = "<dl lang='src'>".$all_events_str[$track_no].'</dl>';
		
		//?????????2
		$addi_info = json_decode($model->addi_info,true);
		if(!empty($addi_info['return_no']))
			$abroad_no_str = '<span style="color:blue"> (???????????????:'.$addi_info['return_no'].') </span>';
		else 
			$abroad_no_str='';
		// ?????? ????????? : ????????? , ?????????
		$all_events_str[$track_no] = 
				'<div class="toNation">'.
				'<h6> <span class="glyphicon glyphicon-gift" aria-hidden="true" style="margin-right: 12px;font-size: 14px;"></span>'.
				'<span class="text-muted">'.TranslateHelper::t('????????????').
				'</span>'.
				': '.$to_nation.$abroad_no_str.$toOfficialLinkHtml.$platFormTitle.'</h6>'.
				'</div>' . $all_events_str[$track_no];
		
		// ?????? ???????????? : ????????? 
		$all_events_str[$track_no] .= 
				'<div class="fromNation">'.
					'<h6><span class="glyphicon glyphicon-send" aria-hidden="true" style="margin-right: 12px;font-size: 14px;"></span>'.
					'<span class="text-muted">'.TranslateHelper::t('????????????').
					'</span>'.
					': '.$from_nation.$fromOfficialLinkHtml.$CarrierTypeStr.'</h6>
				</div>';
		if($is_vip){
			$all_events_str[$track_no] .= '<div class="col-md-12"><span class="text-muted" style="float:left;padding-top:3px;line-height:24px;padding-left: 10px;padding-right: 10px;color:#3c763d;background-color:#dff0d8;border-color:#d6e9c6;">'.TranslateHelper::t('????????????').'</span>';
			$all_events_str[$track_no] .= '<button type="button" class="translateBtn_'.$model->id.' translate-btn translate-btn-checked" lang="src" onclick="ListTracking.translateEventsToZh(this,\''.$track_no.'\','.$model->id.')">??????</button>';
			$all_events_str[$track_no] .= '<button type="button" class="translateBtn_'.$model->id.' translate-btn" lang="zh" onclick="ListTracking.translateEventsToZh(this,\''.$track_no.'\','.$model->id.')">??????</button>';
			$all_events_str[$track_no] .= '<button type="button" class="translateBtn_'.$model->id.' translate-btn" lang="en" onclick="ListTracking.translateEventsToZh(this,\''.$track_no.'\','.$model->id.')">??????</button>';
			$all_events_str[$track_no] .= '</div>';
		}
		
		endforeach; //end of each track no list
		
		
		return $all_events_str;
	}//end of generateTrackingEventHTML
	
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? ???????????? ???????????? ?????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param array $track no  ????????????,?????????????????? ,???????????????????????????????????????
	 * @param array $toCountry ?????????????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @return array	$result [$track no] HTML STR
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/2/15				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function generateTrackingEventHTML_forMSG($TrackingList,$langList=[],$formNationStr='Origin Country',$toNationStr='Destination Country',$from_nation,$to_nation){
		 
		$all_events_str = [];
		$translateBtn = "";
		$platFormTitle = "";
	
		if (empty($toLang))
			$tolang = TranslateHelper::getCurrentLanguague();
	
		foreach($TrackingList as $track_no):
		if ( !empty($langList[$track_no]))
			$lang = $langList[$track_no];
		else
			$lang = "";
		$model = Tracking::find()->andWhere("track_no=:track_no",array(":track_no"=>$track_no))->one();
	
		//????????? ??????
		if (empty($model)) continue;
		//?????? ????????????
	
		$tmp_rt = self::getTrackingAllEvents($track_no,$lang);
	
		if (!empty($tmp_rt['allEvents']))
			$all_events = $tmp_rt['allEvents'];
		else
			$all_events = [];
	
		//??????
		if (! empty($model->platform)){
			$platFormMapping = [
			'ebay'=>'Ebay',
			'sms'=>'?????????',
			];
				
			if (! empty($platFormMapping[$model->platform])){
				$platFormTitle = "<span class=\"label label-default\" style=\"margin-left: 30px;\">".$platFormMapping[$model->platform]."</span>";
			}
		}
		
		/*
		//?????????
		if (! empty($model->to_nation)){
			$to_nation = self::autoSetCountriesNameMapping($model->to_nation);
		}else{
			$to_nation = self::autoSetCountriesNameMapping('--');
		}
	
		//?????????
		if (! empty($model->from_nation)){
			$from_nation = self::autoSetCountriesNameMapping($model->from_nation);
		}else {
			$from_nation = self::autoSetCountriesNameMapping('--');
		}
		*/
	
		//parlce type is?????????????????????EMS
		$parcel_type_label = Tracking::get17TrackParcelTypeLabel($model->parcel_type);
		/*
			if (empty($lang)){
		$translateBtn = "<div class=\"col-md-2\" > <input id='tsl_$track_no' class='btn btn-info' style='font-size: 12px' data-translate-code='' data-loading-text='".TranslateHelper::t('?????????')."' value='".TranslateHelper::t('???????????????')."' type=\"button\"  onclick=\"ListTracking.translateContent('".$track_no."','$tolang',this)\"/>   </div>";
		}else{
		$translateBtn = "<div class=\"col-md-2\" > <input id='tsl_$track_no' class='btn btn-info' data-translate-code='$tolang' data-loading-text='".TranslateHelper::t('?????????')."'  value='".TranslateHelper::t('????????????')."' type=\"button\"  onclick=\"ListTracking.translateContent('".$track_no."','',this)\"/>   </div>";
		}
		*/
		/*
		 *
		* ????????????link????????????????????? lt_tracking.carrier_type,
		* 1. ??????carrier_type >0, url = EXPRESS_ENUM. b, where EXPRESS_ENUM.a = carrier_type .
		* 2. if carrier_type =0, url = POST_ENUM.x.b,  x = 17TrackNationCode * 10 + parcel_type.
		*
		* */
		$FromOfficialLink =""; // ???????????????link
		$ToOfficialLink = "";  // ???????????????link
		if (isset($model->carrier_type)){
			//carrier_type ?????? ?????????????????? ??????link
			if ($model->carrier_type > 0 ){
				// carrier_type > 0 ???dhl ????????? ??? ?????????????????? ?????????
				$FromOfficialLink = Tracking::get17TrackExpressUrlByCode($model->carrier_type);
				$ToOfficialLink = $FromOfficialLink;
			}elseif ($model->carrier_type == 0 ){
				// carrier_type = 0 ???ems ????????? ??? ?????????????????? ????????????
				if (!empty($model->from_nation)){
					unset($from_nation_code);
					if (isset($model->parcel_type)){
						if (is_numeric($model->parcel_type)){
							$nation_code = Tracking::get17TrackNationCodeByStandardNationCode($model->from_nation);
							$from_nation_code = $nation_code*10+ $model->parcel_type;
						}
					}
						
					if (!empty($from_nation_code))
						$FromOfficialLink = Tracking::get17TrackNationUrlByCode($from_nation_code);
				}
	
				if (!empty($model->to_nation)){
					unset($to_nation_code);
					if (isset($model->parcel_type)){
						if (is_numeric($model->parcel_type)){
							$nation_code = Tracking::get17TrackNationCodeByStandardNationCode($model->to_nation);
							$to_nation_code = $nation_code*10+ $model->parcel_type;
						}
					}
	
					if (!empty($to_nation_code))
						$ToOfficialLink = Tracking::get17TrackNationUrlByCode($to_nation_code);
				}
	
	
			}
				
		}
	
		$TranslateMappings = TrackingMsgHelper::getTranslateMapping();
		$display_lang = TrackingMsgHelper::getToNationLanguage($to_nation);
		if(isset($TranslateMappings[$display_lang]))
			$translateMapping = $TranslateMappings[$display_lang];
		else 
			$translateMapping = $TranslateMappings['EN'];
		if (!empty($FromOfficialLink)){
			$fromOfficialLinkHtml = "<a style=\"margin-left: 30px;\" href='$FromOfficialLink' target=\"target\">".$translateMapping['Go to official website']."</a>";
		}else{
			$fromOfficialLinkHtml = "";
		}
	
	
		if (!empty($ToOfficialLink)){
			$toOfficialLinkHtml = "<a href='$ToOfficialLink' target=\"target\" style=\"margin-left:30px;\">".$translateMapping['Go to official website']."</a>";
		}else{
			$toOfficialLinkHtml = "";
		}
		
	
		// ?????? ????????? : ????????? , ?????????
		/*
			$all_events_str[$track_no] = '<dd>'.
		'<div class="col-md-12 toNation">'.
		'<h6> <span class="glyphicon glyphicon-gift" aria-hidden="true" style="margin-right: 12px;font-size: 14px;"></span>'.
		'<span class="text-muted">'.TranslateHelper::t('????????????').
		'</span>'.
		': '.$to_nation.$toOfficialLinkHtml.$platFormTitle.'</h6>'.
		'</div>'.
		$translateBtn.'</dd>';
		*/
		$all_events_str[$track_no] = "";
	
		//??????????????????
		if (is_array($all_events)){
			$c=count($all_events);
			$index=0;
			foreach($all_events as $anEvent){
				//??????lang ?????????????????? , ????????????base 64 ?????? , ??????????????????
				if (empty($lang)){
					$anEvent['where'] = base64_decode($anEvent['where']);
					$anEvent['what'] = base64_decode($anEvent['what']);
					//??????????????????????????????????????????????????? 1900 ???
					if (!empty($anEvent['when']) and strlen($anEvent['when']) >=10 and substr($anEvent['when'],0,10)<'2014-01-01' )
						$anEvent['when'] = '';
					/*
						if (empty($all_events_str[$track_no]))
						$translateBtn = "<div class=\"col-md-2\" > <input id='tsl_$track_no' class='btn btn-info' data-translate-code='' data-loading-text='".TranslateHelper::t('?????????')."' value='".TranslateHelper::t('???????????????')."' type=\"button\"  onclick=\"ListTracking.translateContent('".$track_no."','$tolang',this)\"/>   </div>";
					else
						$translateBtn = "";
					*/
				}else{
					/*
						if (empty($all_events_str[$track_no]))
						$translateBtn = "<div class=\"col-md-2\" > <input id='tsl_$track_no' class='btn btn-info' data-translate-code='$tolang' data-loading-text='".TranslateHelper::t('?????????')."'  value='".TranslateHelper::t('????????????')."' type=\"button\"  onclick=\"ListTracking.translateContent('".$track_no."','',this)\"/>   </div>";
					else
						$translateBtn = "";
					*/
				}
	
				if (!empty($anEvent['type'])){
					$class_nation = $anEvent['type'];
				}
	
				if ($index==0){
					$className = 'new';
				}elseif($index==($c-1)){
					$className = 'begin';
				}else{
					$className = '';
				}
				$index++;
				//detail view message
				//$all_events_str[$track_no] .= $anEvent['when'].$anEvent['where'].$anEvent['what'].".<br>";
	
				$all_events_str[$track_no]  .= '<dd class="'.$className.'">'.
						'<i></i>'.
						'<span>'. $anEvent['when'].'</span>'.
						'<p style="margin:0 0 10px !important;">'.$anEvent['where'].((empty($anEvent['where']))?"":",").
						$anEvent['what']."</p>".
						"</dd>";
			}
	
		}
	
		$all_events_str[$track_no] = "<dl class='all_events'>".$all_events_str[$track_no].'</dl>';
	
	
		// ?????? ????????? : ?????????
		$all_events_str[$track_no] =
		'<div class="toNation" style="padding-left:0px;background-color:transparent;">'.
		'<dl><dt>'.$toNationStr.' - '.$to_nation.$toOfficialLinkHtml.'</dt></dl>'.
		/*
		'<h6> <span class="toNationIcon" aria-hidden="true" style="margin-right: 12px;font-size: 14px;"></span>'.
		'<span class="text-muted">'.$toNationStr.
		'</span>'.
		': '.$to_nation.$toOfficialLinkHtml.$platFormTitle.'</h6>'.
		
		 */
		'</div>' .
	
		//  ?????????
		'<div class="fromNation" style="padding-left:0px;background-color:transparent;">'.
		'<dl><dt>'.$formNationStr.' - '.$from_nation.$fromOfficialLinkHtml.'</dt></dl>'.
		/*
		'<h6><span class="glyphicon glyphicon-send" aria-hidden="true" style="margin-right: 12px;font-size: 14px;"></span>'.
		'<span class="text-muted">'.$formNationStr.
		'</span>'.
		': '.$from_nation.$fromOfficialLinkHtml.'</h6>
		*/
		'</div>'. $all_events_str[$track_no];
	
		if(true){
			$all_events_str[$track_no] .= '<div class="col-md-12"><span class="text-muted" style="float:left;padding-top:3px;line-height:24px;padding-left: 10px;padding-right: 10px;color:#3c763d;background-color:#dff0d8;border-color:#d6e9c6;">'.TranslateHelper::t('????????????').'</span>';
			$all_events_str[$track_no] .= '<button type="button" class="translateBtn_'.$model->id.' translate-btn translate-btn-checked" lang="src" onclick="ListTracking.translateEventsToZh(this,\''.$track_no.'\','.$model->id.')">??????</button>';
			$all_events_str[$track_no] .= '<button type="button" class="translateBtn_'.$model->id.' translate-btn" lang="zh" onclick="ListTracking.translateEventsToZh(this,\''.$track_no.'\','.$model->id.')">??????</button>';
			$all_events_str[$track_no] .= '<button type="button" class="translateBtn_'.$model->id.' translate-btn" lang="en" onclick="ListTracking.translateEventsToZh(this,\''.$track_no.'\','.$model->id.')">??????</button>';
			$all_events_str[$track_no] .= '</div>';
		}
		
		endforeach; //end of each track no list
		return $all_events_str;
	}//end of generateTrackingEventHTML
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? ?????? ?????? ?????? ???????????? ????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param array $track no  ????????????,?????????????????? ,???????????????????????????????????????
	 *  @param array $langList  = [['123'=>'zh-cn']] ????????????????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @return array	$result [$track no]  HTML
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/3/3				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static function generateTrackingInfoHTML($TrackingList, $langlist=[]){
		$HTMLStr = [];
		$status_class_mapping = [
			"checking"=>"label label-default",
			"shipping"=>"label label-primary",
			"no_info"=>"label label-default",
			"ship_over_time"=>"label label-danger",
			"arrived_pending_fetch"=>"label label-danger",
			"received"=>"label label-success",
			"rejected"=>"label label-danger"
			
		];
		
		foreach($TrackingList as $track_no):
			if ( !empty($langList[$track_no]))
				$lang = $langList[$track_no];
			else
				$lang = "";
			$model = Tracking::find()->andWhere("track_no=:track_no",array(":track_no"=>$track_no))->one();
			//????????? ??????
			if (empty($model)) continue;
			$row = $model->attributes;
			
			//?????? ????????????
			$all_events = json_decode($row['all_event'],true);
			if (empty($all_events)) $all_events = array();
			$all_events_str = "";
			$events_str = '';
			
			if (is_array($all_events)){
				foreach($all_events as $anEvent){
					if (empty($lang)){
						$anEvent['where'] = base64_decode($anEvent['where']);
						$anEvent['what'] = base64_decode($anEvent['what']);
					}
					
					//????????????
					if (empty($events_str)){
						$events_str = $anEvent['when'].'<p>'.$anEvent['where'].((empty($anEvent['where']))?"":",").$anEvent['what'].".</p>";
					}else{
						continue;
					}
				}
			
			}else{
				$events_str = "";
			}
			
			//????????????
			if (!empty($row['from_nation'])){
				$from_nation = self::autoSetCountriesNameMapping($row['from_nation']);
			}else{
				$from_nation = StandardConst::$COUNTRIES_CODE_NAME_CN['--'];
			}
			//????????????
			if (!empty($row['to_nation'])){
				$to_nation = self::autoSetCountriesNameMapping($row['to_nation']);
			}else{
				$to_nation = StandardConst::$COUNTRIES_CODE_NAME_CN['--'];
			}
			//carrier type //?????????  carrier_type ?????????0  , ?????????????????????
			$CarrierTypeStr = "";
			$parcel_type_label = "";
			if (isset($row['carrier_type'])){
				//??????17track ?????? ????????????
				if (!empty($model->parcel_type)){
					$parcel_type_label = Tracking::get17TrackParcelTypeLabel($model->parcel_type);
				}
				
				if (isset(CarrierTypeOfTrackNumber::$expressCode[$row['carrier_type']]) && ! in_array(strtolower($row['status']) , ['checking',"?????????","???????????????"])  )
					$CarrierTypeStr = "(".CarrierTypeOfTrackNumber::$expressCode[$row['carrier_type']].".".$parcel_type_label.")";
			}
			// ??????status ?????? class 
			if (!empty($status_class_mapping[$row['status']]))
				$status_class = $status_class_mapping[$row['status']];
			else 
				$status_class = 'label label-default';
			
			//?????? ????????????
			$total_days = 0;
			if (isset($row['total_days'])){
				if ($row['total_days']>0)
				$total_days = $row['total_days'];
			}
			
			if (empty($total_days)){
				$time= time();
				$total_days  = ceil(($time-strtotime($row['create_time']))/(24*3600));
			}
			$total_days_html_str = "";
			//???????????? ????????????????????????
			if ($row['status'] != "checking")
			$total_days_html_str = "<br><p style=\"margin: 5px 0 0px 25px;\"><small>".$total_days.TranslateHelper::t('???')."</small></p>";
			 
			$status_class .= " status_label";
			$HTMLStr [$row['track_no']] =   "
				<td>".$row['order_id']."</td>
				<td>".$row['track_no'].(empty($row['track_no'])?"":"<br>").$CarrierTypeStr."</td>
				<td>".$from_nation."</td>
				<td>".$to_nation."</td>
				<td>".$events_str."</td>
				<td nowrap data-status='".$row['status']."'><strong>".Tracking::getChineseStatus($row['status'])."</strong>$total_days_html_str</td>
				<td nowrap><a id='a_".$row['track_no']."'  class=\"btn-qty-memo\" data-track-id='".$row['id']."'  title='".TranslateHelper::t('??????')."'>".'<span class="egicon-eye" aria-hidden="true"></span>'."</a>"
						." <a onclick=\"manual_import.list.showRemarkBox('". $row['track_no'] ."')\" title='".TranslateHelper::t('????????????')."'>".'<span class="egicon-memo-blue" aria-hidden="true"></span>'."</a>"
						." <a onclick=\"manual_import.list.DelTrack('". $row['track_no'] ."')\" title='".TranslateHelper::t('??????')."'>".'<span class="egicon-trash" aria-hidden="true"></span>'."</a>"
						
						."</td>
			";
		endforeach;
		return $HTMLStr;
	} // end of generateTrackingInfoHTML
	
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? ????????????, ???????????? ?????? ???????????? ????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param array $track no  ????????????,?????????????????? ,???????????????????????????????????????
	 * @param boolean  ????????????????????????
	 *  @param array $langList  = [['123'=>'zh-cn']] ????????????????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @return array	$result [$track no]  HTML
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/3/3				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function generateQueryTrackingInfoHTML($oneTracking , $isPlatform = true, $langlist=[]){
		$status_class_mapping = [
		"?????????"  =>"label status_label label-default",
		"???????????????"=>"label status_label label-default",
		"????????????"=>"label status_label label-primary",
		"????????????"=>"label status_label label-default",
		"????????????"=>"label status_label label-danger",
		"????????????"=>"label status_label label-danger",
		"????????????"=>"label status_label label-success",
		"????????????"=>"label status_label label-danger",
		"???????????????"=>"label status_label label-success",
		];
		
		$status_qtip_mapping = [
		"?????????"  =>"",
		"???????????????"=>"",
		"????????????"=>"tracker_shipping",
		"????????????"=>"tracker_no_info",
		"????????????"=>"tracker_ship_over_time",
		"????????????"=>"tracker_arrived_pending_fetch",
		"????????????"=>"tracker_complete_parcel",
		"????????????"=>"tracker_rejected" , 
		"????????????"=>"tracker_suspend_parcel",
		"????????????"=>"tracker_unshipped",
		"?????????"=>"tracker_unregistered",
		"???????????????"=>"tracker_platform_confirmed",
		"??????(????????????)"=>"",
		];
		
		$model = new Tracking();
		$CarrierTypeStr = "";
		$parcel_type_label = "";
		
		if (!empty($oneTracking['parcel_type'])){
			//??????17track ?????? ????????????
			if (!empty($oneTracking['parcel_type'])){
				$parcel_type_label = Tracking::get17TrackParcelTypeLabel($oneTracking['parcel_type']);
			}
		}
		// ??????status ?????? class
		if (!empty($status_class_mapping[$oneTracking['status']]))
			$status_class = $status_class_mapping[$oneTracking['status']];
		else
			$status_class = 'label status_label label-default';
		//?????????  carrier_type ?????????0  , ?????????????????????
		if (isset($oneTracking['carrier_type']) && ! in_array(strtolower($oneTracking['status']) , ['checking',"?????????","???????????????"]) ){
			if (isset(CarrierTypeOfTrackNumber::$expressCode[$oneTracking['carrier_type']]))
				$CarrierTypeStr = "<span class='font-color-1'>(".CarrierTypeOfTrackNumber::$expressCode[$oneTracking['carrier_type']].")</span>";
		}
		
		//?????? ????????????
		$total_days = 0;
		if ( isset($oneTracking['total_days'])){
			if ($oneTracking['total_days']>0)
				$total_days = $oneTracking['total_days'];
		}
			
		if (empty($total_days)){
			$time= time();
			$total_days  = ceil(($time-strtotime($oneTracking['create_time']))/(24*3600));
		}
		
		//$HtmlStr = "<tr id=\"tr_info_".$oneTracking['track_no']."\" track_no=\"".$oneTracking['track_no']."\">";
		$HtmlStr = "<td>";
		
		$HtmlStr .="<input type='checkbox' name='chk_tracking_record' value =".base64_encode($oneTracking['track_no'])." data-track-id='".$oneTracking['id']."' data-order-platform='".$oneTracking['platform']."'>";
	
		if (strtoupper($oneTracking['mark_handled'])=='Y' && (in_array($oneTracking['state'], ['??????' , '????????????']))) {
			$markHandleStr = '<a title="'.TranslateHelper::t('?????????').'"><span class="egicon-ok-blue"></span></a>';
			$markHandleLink = '';
		}else{
			$markHandleStr = '';
			if (strtoupper($oneTracking['mark_handled'])=='N' && (in_array($oneTracking['state'], ['??????' , '????????????'])))
				$markHandleLink = " <a onclick=\"ListTracking.MarkOneHndled('". $oneTracking['id'] ."')\" title='".TranslateHelper::t('???????????????')."'>".'<span class="egicon-ok-blue" aria-hidden="true"></span>'."</a>";
			else 
				$markHandleLink = "";
		}
		if (empty($oneTracking['remark']))
			$imgStr = "";
		else
			$imgStr = '<span style="cursor: pointer;" class="egicon-memo-orange" data-track-id="'.$oneTracking['id'].'" ></span> <div class="div_space_toggle">'.self::generateRemarkHTML($oneTracking['remark']).'</div>';
			
		$msgIconStr = "";
		if ($oneTracking['msg_sent_error'] == 'Y'){
			$ct = MessageHelper::getFailureMessageCount();
			$msgIconStr = '<a style="cursor: pointer;" onclick="StationLetter.showMessageBox(\''.$oneTracking['order_id'].'\''.",'". $oneTracking['track_no'] ."'".',\'history\' ,'.$ct.')"><span class="egicon-envelope-remove"></span></a>';
		}			
		elseif ($oneTracking['msg_sent_error'] == 'C')
			$msgIconStr = '<a style="cursor: pointer;" onclick="StationLetter.showMessageBox(\''.$oneTracking['order_id'].'\''.",'". $oneTracking['track_no'] ."'".',\'history\' , 0)"><span class="egicon-envelope-ok"></span></a>';
		
		//var_dump($oneTracking['msg_sent_error']);
		$TagStr = TrackingTagHelper::generateTagIconHtmlByTrackingId($oneTracking['id']);

		if (!empty($TagStr)){
			$TagStr = "<span class='btn_tag_qtip".(stripos($TagStr,'egicon-flag-gray')?" div_space_toggle":"")."' data-track-id='".$oneTracking['id']."' >$TagStr</span>";
		}
					$HtmlStr .=" </td>".
							"<td><p class='noBottom font-color-1'>".$oneTracking['track_no']."</p>".$imgStr.$msgIconStr.$markHandleStr.$TagStr."</td>".
					"<td class='font-color-2'>".(empty($oneTracking['order_id'])?"<span  qtipkey='tracker_no_order_id'></span>":'<a title="????????????" onclick="ListTracking.ShowOrderInfo(\''.$oneTracking['track_no'].'\')">'.$oneTracking['order_id']."</a>");
					
					$HtmlStr .= "</td>";
					
					//seller id 
					//$HtmlStr .="<td>".$oneTracking['seller_id']."</td>";
					
					/**/
					if (empty($oneTracking['to_nation']) || $oneTracking['to_nation'] =='--' || $oneTracking['to_nation'] =='??????'){
						$model->attributes = $oneTracking;
						$oneTracking['to_nation'] = $model->getConsignee_country_code();
					}
					
					$toNation = self::autoSetCountriesNameMapping($oneTracking['to_nation']);
					$fromNation = self::autoSetCountriesNameMapping($oneTracking['from_nation']);
					
					if (in_array($oneTracking['status'], ['????????????']))
						$arrivedClass = "";
					else{
						$arrivedClass = "arrived";
						
					}
					$toNationEn = StandardConst::getNationEnglishNameByCode($oneTracking['to_nation']);
					$all_event = json_decode($oneTracking['all_event'],true);
					
					if (is_array($all_event)){
						foreach( $all_event as &$an_event){
							$an_event['what'] = base64_decode($an_event['what']);
							$an_event['where'] = base64_decode($an_event['where']);
						}
					}
					
					if ( stripos($oneTracking['all_event'],'toNation')>0  || stripos(json_encode($all_event),$toNationEn)>0){
						//?????????????????????????????????????????? ?????????  ?????? ???????????????????????? , ???????????? ??????
						$HtmlStr .= "<td><small> <span class='btn_qtip_from_nation font-color-1'>".
							(($oneTracking['from_nation']<>'' and $oneTracking['from_nation']<>'--')?$oneTracking['from_nation']:'')."</span>".
						'<div class="div_space_toggle">'.(($fromNation<>'' and $fromNation<>'--')?$fromNation:'').'</div>'.
						(($oneTracking['from_nation']<>'' and $oneTracking['from_nation']<>'--')?' - ':"").
						"<span class='btn_qtip_to_nation $arrivedClass'>".( ($oneTracking['to_nation']<>'' and $oneTracking['to_nation']<>'--')?$oneTracking['to_nation']:'')."</span>".
						'<div class="div_space_toggle">'.(($toNation<>'' and $toNation<>'--')?$toNation:'').'</div>'."</small></td>";
					}else{
						$HtmlStr .= "<td><small><span class='btn_qtip_from_nation $arrivedClass' >".
							(($oneTracking['from_nation']<>'' and $oneTracking['from_nation']<>'--')?$oneTracking['from_nation']:'')."</span>".'<div class="div_space_toggle">'.(($fromNation<>'' and $fromNation<>'--')?$fromNation:'').'</div>'.(($oneTracking['from_nation']<>'' and $oneTracking['from_nation']<>'--')?' - ':"")."<span  class='btn_qtip_to_nation font-color-1'>".( ($oneTracking['to_nation']<>'' and $oneTracking['to_nation']<>'--')?$oneTracking['to_nation']:'')."</span>".'<div class="div_space_toggle">'.(($toNation<>'' and $toNation<>'--')?$toNation:'').'</div>'."</small></td>";
					}
					
					if ( !empty($oneTracking['addi_info']) ){
						$addi_info = json_decode($oneTracking['addi_info'],true);
						if(empty($addi_info)) $addi_info = [];
					}else 
						$addi_info = [];
					//oms  ?????? ship out date ?????? 
					if (in_array($oneTracking['source'], ['O'])){
						$HtmlStr .="<td class='font-color-2' nowrap>";
						//ys0929,?????? ??????????????????????????????
						if (!empty($addi_info['order_paid_time'])){
							$HtmlStr .= TranslateHelper::t('????????????:')."<br>".date('Y-m-d',$addi_info['order_paid_time']);
						}
						$HtmlStr .= "</td>";//ship_out_date
					}else{
						//???????????? ?????? ship out date ??????
						$HtmlStr .="<td class='font-color-2' nowrap>".TranslateHelper::t('????????????:')."<br>".$oneTracking['create_time']."</td>";
					}
					
					$HtmlStr .= "<td class='font-color-2'>". substr($oneTracking['update_time'], 0 , 16)."</td>";
					//
					$tmp_onclick_function = 'ListTracking.ignoreShipType';
					$tmp_class_name = 'egicon-ok-blue';
					$tmp_class_title = '??????????????????????????????????????????????????????';
					global $CACHE;
					if (!empty($CACHE['IgnoreToCheck_ShipType'])){
						if(in_array($oneTracking['ship_by'],$CACHE['IgnoreToCheck_ShipType'])){
							$tmp_onclick_function = 'ListTracking.reActiveShipType';
							$tmp_class_name = 'iconfont icon-guanbi';
							$tmp_class_title = '???????????????????????????????????????????????????';
						}
					}
					//
					$HtmlStr .= "<td>".
							(
								empty($oneTracking['ship_by'])? '':"<span class='font-color-2'>". $oneTracking['ship_by'].'</span>'.
								'<span class=\''.$tmp_class_name.'\' onclick="'.$tmp_onclick_function.'(\''.base64_encode($oneTracking['ship_by']).'\')" title="'.$tmp_class_title.'" style="cursor:pointer;margin-left:5px;"></span>'
							).
							//?????????????????????????????????????????????	//liang 2017-01-10
						" <span onclick=\"ListTracking.setCarrierType('". $oneTracking['id'] ."','')\" title='".TranslateHelper::t('???????????????????????????')."' class=\"egicon-binding\" aria-hidden=\"true\" style=\"cursor:pointer;\"></span>".
						((!empty($addi_info['set_carrier_type']) && !empty($addi_info['set_carrier_type_time']))?"<br><span style='cursor:pointer;color:#00bb4f;font-style:italic;' title='???".$addi_info['set_carrier_type_time']."???????????????'>????????????".@CarrierTypeOfTrackNumber::$expressCode[$addi_info['set_carrier_type']]."??????</span>":"").	
					"</td>";
					
					$canIgnoreStatus = Tracking::getCanIgnoreStatus('ZH');
					$HtmlStr .= "<td nowrap data-status='".$oneTracking['status']."'><strong ".(empty($status_qtip_mapping[$oneTracking['status']])?"":" qtipkey='".$status_qtip_mapping[$oneTracking['status']]."'")." class='no-qtip-icon font-color-1' >". $oneTracking['status']."</strong>";
					
					$HtmlStr .= (!empty($status_qtip_mapping[$oneTracking['status']]) && $status_qtip_mapping[$oneTracking['status']]=='tracker_no_info')?'<span onclick="reportTrackerNoInfo('.$oneTracking['id'].')" class="egicon-people" style="cursor:pointer;" title="????????????"></span>':'';
					$HtmlStr .= in_array($oneTracking['status'],$canIgnoreStatus)?'<span onclick="ignoreTrackingNo('.$oneTracking['id'].')" class="iconfont icon-ignore_search" style="cursor:pointer;vertical-align:middle;font-size:14px" title="??????(????????????)"></span>':'';
					
					//???????????????????????????log
					if(!empty($addi_info['manual_status_move_logs'])){
						foreach ($addi_info['manual_status_move_logs'] as $move_log){
							$HtmlStr .= '<br>('.@$move_log['time'].'???'.$move_log['capture_user_name'].'???"'
									.Tracking::getChineseStatus($move_log['old_status']).'"?????????"'
									.Tracking::getChineseStatus($move_log['new_status']).'")';
						}
					}
					
					$HtmlStr .= "<p class='font-color-2'><small>(".$total_days.TranslateHelper::t('???').")</small></p></td>";
					// 
					//2015-07-10 liang start 
					$stay_days='-';
					if(is_numeric($oneTracking['stay_days']) && $oneTracking['stay_days']>0) $stay_days=$oneTracking['stay_days'].TranslateHelper::t("???");
					$HtmlStr .="<td class='font-color-2' nowrap>".$stay_days."</td>";//ship_out_date	
					//2015-07-10 liang end
					
					//$HtmlStr .= "<td nowrap><span class='". $status_class."' ".(empty($status_qtip_mapping[$oneTracking['status']])?"":" qtipkey='".$status_qtip_mapping[$oneTracking['status']]."'")." >". $oneTracking['status']."</span><br><p style=\"margin: 5px 0 0px 25px;\"><small>".$markHandleStr.$total_days.TranslateHelper::t('???')."</small></p></td>";
					
					$HtmlStr .= "<td>";
						//????????????   ??????
						if (! in_array(strtolower($oneTracking['status']), [TranslateHelper::t("?????????") , TranslateHelper::t("???????????????"), TranslateHelper::t("?????????"),TranslateHelper::t("??????(????????????)")]) ):
						$HtmlStr .=" <a onclick=\"ListTracking.UpdateTrackRequest('". $oneTracking['track_no'] ."',this)\"  title='".TranslateHelper::t('????????????')."'>".'<span class="egicon-refresh"></span>'."</a>";
						endif;
					
						// ??????  ??????
						//if (! in_array(strtolower($oneTracking['status']), [TranslateHelper::t("?????????") , TranslateHelper::t("????????????")]) ):

						//khcomment20150610 $HtmlStr .=" <a onclick=\"ListTracking.ShowDetailView(this)\" title='".TranslateHelper::t('??????')."'>".'<span class="glyphicon glyphicon-eye-open" aria-hidden="true"></span>'."</a>";
						
						//liang 2016-03-24 17track iframe start
						$addi_info = json_decode($oneTracking['addi_info'],true);
						if(!empty($addi_info['return_no']))
							$abroad_no = $addi_info['return_no'];
						else
							$abroad_no='';
						$non17Track = CarrierTypeOfTrackNumber::getNon17TrackExpressCode();
						if (! in_array(strtolower($oneTracking['status']), [TranslateHelper::t("????????????") , TranslateHelper::t("????????????")]) ){
							if(!in_array($oneTracking['carrier_type'],$non17Track)){
								$HtmlStr .=' <a title="'.TranslateHelper::t('??????').'" onclick="iframe_17Track(\''.(empty($abroad_no)?$oneTracking['track_no']:$abroad_no).'\',this)" data-track-id="'.$oneTracking['id'].'">'.'<span class="egicon-eye"></span>'."</a>";
							}else{
								$HtmlStr .=" <a title='".TranslateHelper::t('??????')."' class='btn-qty-memo' data-track-id='".$oneTracking['id']."'>".'<span class="egicon-eye"></span>'."</a>";
							}
						}//liang 2016-03-24 17track iframe end

						//endif;
						
						if ( !empty($oneTracking['seller_id']) && !empty($oneTracking['order_id'])):
						//????????????   ??????
						$HtmlStr .=" <a onclick=\"ListTracking.ShowOrderInfo('". $oneTracking['track_no'] ."')\" title='".TranslateHelper::t('????????????')."' >".'<span class="egicon-notepad" aria-hidden="true"></span>'."</a>";
						endif;
						//???????????? ??????
						$HtmlStr .=" <a onclick=\"ListTracking.showRemarkBox('". $oneTracking['track_no'] ."')\" title='".TranslateHelper::t('??????????????????')."'>".'<span class="egicon-memo-blue" aria-hidden="true"></span>'."</a>";
						//???????????? ?????? (??????)
						//$HtmlStr .=" <a onclick=\"ListTracking.showTagBox('". $oneTracking['id'] ."','". $oneTracking['track_no'] ."')\" title='".TranslateHelper::t('????????????')."'>".'<span class="glyphicon glyphicon-tags" aria-hidden="true"></span>'."</a>";
						if ( !empty($oneTracking['seller_id']) && !empty($oneTracking['order_id']) && in_array($oneTracking['platform'], ['ebay','aliexpress','amazon','cdiscount'])):
						
						//????????? ??????
						$HtmlStr .=" <a onclick=\"StationLetter.showMessageBox('". $oneTracking['order_id'] ."','". $oneTracking['track_no'] ."','role')\" title='".TranslateHelper::t('???????????????')."'>".'<span class="egicon-envelope" aria-hidden="true"></span>'."</a>";
						endif;
						//??????  ??????
						$HtmlStr .=" <a onclick=\"ListTracking.DelTrack('". $oneTracking['track_no'] ."')\" title='".TranslateHelper::t('??????')."'>".'<span class="egicon-trash" aria-hidden="true"></span>'."</a>".$markHandleLink;
						
					$HtmlStr .=" </td>".
				"</tr>";
				
				
			$result[$oneTracking['track_no']] = $HtmlStr;
							
			return $result;
			
	}//end of generateQueryTrackingInfoHTML
	
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * excel ?????? ????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param array $ExcelFile ????????????excel?????? ??????????????? excel?????? ???xls ??????
	 +---------------------------------------------------------------------------------------------
	 * @return array	$result ['success'] boolean
	 * 					$result ['message'] string  ???????????????????????????
	 * 					$result ['ImportDataFieldMapping']  array ???????????????excel???????????????????????????col????????????????????????
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/2/17				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function importTrackingDataByExcel($ExcelFile){
		try {
			$puid = \Yii::$app->subdb->getCurrentPuid();
			//????????? ????????????
			$result['success'] = true;
			
			//?????? excel??????
			
			$TrackingData = ExcelHelper::excelToArray($ExcelFile , [
					"A" => "A",//"ship_by",//????????????
					"B" => "B",// "ship_out_date",//???????????????
					"C" => "C",// "track_no",//?????????
					"D" => "D",// "order_id",//?????????
					"E" => "E",// "delivery_fee",//??????
					], false); //false is to keep first row
			
			$map = [
			"????????????" => "ship_by",//????????????
			"???????????????" =>   "ship_out_date",//
			"????????????" =>   "track_no",//
			"?????????" =>  "order_id",//
			"????????????(CNY)" =>  "delivery_fee",//
			"?????????" =>   "track_no",//
			"????????????" =>   "track_no",//
			"?????????" =>   "track_no",//
			"????????????" =>   "track_no",//
			];
			
			//2015-09-17 start  ??????excel?????????????????????500????????????????????????1000???
			if( $puid=='18870' ){
				$per_limit= '5000';
			}else{
				$per_limit = 100; //??????excel?????????????????????500???,
			}


			//ys0919, ??????????????????????????????????????????????????????????????????????????????????????????
			foreach($TrackingData[1] as $value){
				if (array_key_exists($value,$map)){
					$per_limit ++;
					break;
				}
			}
			
			//??????excel?????????????????????500???
			if (count($TrackingData)-1>$per_limit){
				$result['success'] = false;
				$result['message'] = TranslateHelper::t(" excel?????????????????????".$per_limit."???! ");
				return $result;
			}
			
			
			$VipLevel = 'v0';
			 
    		
    		if ($VipLevel == 'v0'){
    			//????????????
    			$suffix = date('Ymd');
    		}else{
    			$suffix = 'vip';
    		}
			
			$limt_count =  self::getTrackerTempDataFromRedis("trackerImportLimit_".$suffix );
			if (empty($limt_count)) $limt_count=0;
			
 
			$max_import_limit = TrackingHelper::getTrackerQuota($puid);
			if ( $limt_count + count($TrackingData)-1 > $max_import_limit ){
					
				if (TrackingHelper::$tracker_guest_import_limit == $max_import_limit){
					$tips = "?????????????????????????????????????????????".TrackingHelper::$tracker_import_limit.",????????????????????????";
				}else{
					$tips = '';
				}
				
				$result['success'] = false;
				if ($limt_count == 0 ){
					$result['message'] = TranslateHelper::t("?????????????????????excel??????????????????".$max_import_limit."???! "." ?????????????????????????????????".$tips);
				}else{
					$result['message'] = TranslateHelper::t("??????????????????".$max_import_limit."???,??????????????????".$limt_count."?????????! "." ????????????".(count($TrackingData)-1)."??????????????????".$tips);
				}
				
				return $result;
			}
			
			//2015-09-17 end  ??????excel?????????????????????300????????????????????????1000???
			
			
			$track_no_list = [];
			$row_no = 0;
			$colMapFields=[];
			$TrackingData2 = [];
			$repeat_track_no_list = []; //???????????????track no ???????????????????????? 201501016kh
			//??????excel ?????????????????????  ????????????
			foreach($TrackingData as $oneTracking1):
			$row_no ++;
			//???????????????????????? column header????????????????????????col?????????????????????
			if ($row_no == 1){
					
				foreach ($oneTracking1 as $key => $val){
					$val=trim($val);
					if (isset($map[$val]))
						$colMapFields[$key] = $map[$val]; //key='A', $val=???????????? ??? $map[$val] = track_no
				}
				continue;
			}
			
			//for ?????????????????????????????????mapping
			foreach ($oneTracking1 as $key=>$val){
				if (isset($colMapFields[$key]))
					$oneTracking[$colMapFields[$key]] = $val;
			}
			
			// 201501016kh ????????????????????? ????????????
			if (empty($oneTracking['track_no'])) continue;
			
			//\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Online',"Try to import track no ".print_r($oneTracking,true)  ],"edb\global");
			if (! in_array($oneTracking['track_no'], $track_no_list)){
				$track_no_list[] = $oneTracking['track_no'];
			}else{
				// 201501016kh  ?????????track no ?????????????????? ???????????????????????????????????????????????????
				if (isset($repeat_track_no_list[$oneTracking['track_no']]))
					$repeat_track_no_list[$oneTracking['track_no']] ++ ;
				else 
					$repeat_track_no_list[$oneTracking['track_no']] =2 ;
			}
			$TrackingData2[] = $oneTracking;
			endforeach;
			
			//201501016kh ???????????????????????????tracker
			if (!empty($repeat_track_no_list)){
				$repeat_message = "";
				foreach($repeat_track_no_list as $repeat_track_no => $repeat_count){
					
					$repeat_message .= "$repeat_track_no ?????????$repeat_count ???<br>";
				}
				$result['success'] = false;
				$result['message'] = TranslateHelper::t($repeat_message." ??????????????????????????????????????????.");
				return $result;
			}
			
			
			$TrackingData = $TrackingData2;
			
			$batch_no = "E".date('YmdHi');
			
			//step 1 ?????? track ??????
			$doneCount = 0;
			$totalCountExcel = count($TrackingData);
			$track_nos = array();
			\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Background',"Start to Import 1000 tracking"],"edb\global");
			foreach ($TrackingData as $oneTracking):
			$oneTracking['batch_no'] = $batch_no;
			//below is just to add Tracking to Buffer first
			$rtn1 = self::addTracking($oneTracking,'E',$totalCountExcel);
				
			if (isset($rtn1['success']) and $rtn1['success']){
				$doneCount ++;
				$track_nos[] = $oneTracking['track_no'];
			}
			endforeach;
			
			//call this to put all Tracking into DB
			self::postTrackingBufferToDb();
			
			\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Background',"Complete to Import 1000 tracking"],"edb\global");
			//step 2 ??????api queue?????????????????????????????????????????????????????????tracking????????????????????????????????????php ?????????
			//???????????????????????? step 1 ??????????????????step 2?????????apache??????????????????????????????cronb job ???????????????queue api request ???
			if ($doneCount < 30){ //when <= 40 records, ????????????tracking ??????
				foreach ($TrackingData as $oneTracking):
				self::generateOneRequestForTracking($oneTracking['track_no']);
				endforeach;
				//???Api Request Buffer ?????????insert ???db
				self::postTrackingApiQueueBufferToDb();
			} else //when ????????????????????? 40 ????????????buffer ????????????????????????job?????????????????????
				self::putIntoTrackQueueBuffer($track_nos );
			
			$result['count'] = $doneCount ;
			$result['batch_no'] = $batch_no;
			$result['message'] = TranslateHelper::t('??????'.count($TrackingData).'????????????????????????????????????????????????<br>?????????????????????'.($max_import_limit-$limt_count - count($TrackingData)).'??????????????????');
			//???????????????????????????
// 			self::setTrackerTempDataToRedis("trackerImportLimit_".$suffix , $limt_count + count($TrackingData)-1);//??????????????????????????? ???????????????
			TrackingHelper::addTrackerQuotaToRedis("trackerImportLimit_".$suffix ,  count($TrackingData)-1);//20170912 ??????redisadd ????????????
		} catch (\Exception $e) {
			$result = ['success'=>false , 'message'=> TranslateHelper::t('excel?????????????????? ????????????????????????' ).$e->getMessage()." error code:".$e->getLine()];
		}
		return $result;
		
	}//end of importTrackingDataByExcel 
	

	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????????????????? :
	 * 		???????????????????????? , ?????????????????????????????????????????? .
	 * 		 ??????   [????????????]  ???????????????????????? ,??????????????????  ;
	 * 		 ??????????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param date $from ?????????????????? ?????????????????????  e.g. 2015-02-28 
	 * @param date $to ?????????????????? ?????????????????????   e.g. 2015-02-28
	 * @param int $max_interval ?????????????????? ????????????????????????   e.g. 90
	 * 
	 +---------------------------------------------------------------------------------------------
	 * @return array	$result ['success'] boolean
	 * 					$result ['message'] string  ???????????????????????????
	 * 					$result ['data']  array ??????????????????
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/2/28				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function getDeliveryStatisticalAnalysis($from , $to , $to_nation , $max_interval=90){
		$result ['success'] = true;
		$result ['message'] = "";
		$result ['data'] = [];
		//?????? ???????????? ????????????
		if (empty($from)){
			$result ['success'] = false;
			$result ['message'] = TranslateHelper::t('????????????????????????!');
			return $result;
		}
		
		//?????? ????????????  ?????? ????????????????????????????????? strtotime ???????????? ???, ??????????????????
		if (strtotime("- "+($max_interval+1)+" day") > strtotime($from)){
			$result ['success'] = false;
			$result ['message'] = TranslateHelper::t('????????????'.$max_interval.'???????????????!');
			return $result;
		}
		
		$andSql = "";
		if (!empty($from)){
			$andSql .= " and ship_out_date >= '$from' ";
		}
		
		if (!empty($to)){
			$andSql .= " and ship_out_date <= '$to' ";
		}
		
		if (!empty($to_nation)){
			$andSql .= " and to_nation = '$to_nation' ";
		}
		//$whereSql = " where 1=1 and '$from' '$to'";
			
		// ?????? ?????? ????????? ???  ???????????? , ???????????? , ????????? , ????????????
		$sql = "select ship_by , count(1) as total_count,  avg(total_days) as avg_day , sum(delivery_fee) as total_delivery_fee ,  avg(delivery_fee) as avg_delivery_fee from lt_tracking where 1 $andSql group by ship_by";
		$ShipByResult = Yii::$app->get('subdb')->createCommand($sql)->queryAll();
		//echo "".$sql;//test kh
		$Tracking = new Tracking();
		
		// ??????  ??????????????????  ????????????
		$parcel_classification = 'received_parcel';
		
		self::_set_delivery_statistical_analysis_data($parcel_classification, $ShipByResult, $Tracking,$from , $to , $to_nation);
		
		// ??????  ??????????????????  ????????????
		
		$parcel_classification = 'exception_parcel';
		
		self::_set_delivery_statistical_analysis_data($parcel_classification, $ShipByResult, $Tracking,$from , $to, $to_nation);
		
		// ??????  ?????????????????? ????????????
		
		$parcel_classification = 'shipping_parcel';
		
		self::_set_delivery_statistical_analysis_data($parcel_classification, $ShipByResult, $Tracking,$from , $to, $to_nation);
		
		// ??????  ?????????????????? ????????????
		
		$parcel_classification = 'ship_over_time_parcel';
		
		self::_set_delivery_statistical_analysis_data($parcel_classification, $ShipByResult, $Tracking,$from , $to, $to_nation);
		
		// ??????  ?????????????????? ????????????
		
		$parcel_classification = 'unshipped_parcel';
		
		self::_set_delivery_statistical_analysis_data($parcel_classification, $ShipByResult, $Tracking,$from , $to, $to_nation);
		
		//????????????
		$result ['data'] = $ShipByResult;
		return $result;
		
	}//end of getDeliveryStatisticalAnalysis
	
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????????????????? :
	 * 		???????????????????????? , ?????????????????????????????????????????? .
	 * 		 ??????   [????????????]  ???????????????????????? ,??????????????????  ;
	 * 		 ??????????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param string $parcel_classification ????????????
	 * @param array $ShipByResult ????????????????????????
	 * @param model $Tracking tracking model 
	 *
	 +---------------------------------------------------------------------------------------------
	 * @return null
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/2/28				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static private function _set_delivery_statistical_analysis_data($parcel_classification , &$ShipByResult , &$Tracking, $from='' , $to='' , $to_nation=''){
		try {
			$condition = Tracking::getTrackingConditionByClassification ( $parcel_classification );
			
			foreach($ShipByResult as &$oneShipBy){
				$tmp_condition = $condition;
				$tmp_condition['ship_by'] = $oneShipBy['ship_by'];
				/*
				$tmp_result = $Tracking->find()->andwhere($tmp_condition)->count();
				*/
				$query = $Tracking->find();
				if (!empty($from))
					$query = $query->andWhere(['>=','create_time', $from]);
				
				if (!empty($to))
					$query = $query->andWhere(['<=','create_time', $to]);
				
				if (!empty($to_nation)){
					$query = $query->andWhere(['to_nation'=>$to_nation]);
				}
				
				$oneShipBy[$parcel_classification] = $query->andwhere($tmp_condition)->count();
				
				/* 
				if (isset(\Yii::$app->params["currentEnv"]) and \Yii::$app->params["currentEnv"]=="test" ):
				$tmpCommand = $query->createCommand();
				echo "<br>".$tmpCommand->getRawSql();
				endif;
				*/
				
				
			}
		} catch (Exception $e) {
			echo $e->getMessage();
		}
		
	}//end of _set_delivery_statistical_analysis_data
	
	static public function getCandidateCarrierType($track_no, $ship_by1=''){
		
		//priority 1: ???user ?????????????????????, ??????????????????????????????
		$userSpecified = self::getUserShipByAndCarrierTypeMappingFromRedisForShipBy($ship_by1);
		if ($userSpecified <> ''){
			$carrier_types["".$userSpecified.""] = $userSpecified;
			return $carrier_types;
		}
		
		//priority 2??????????????????
		$results =  CarrierTypeOfTrackNumber::checkExpressOftrackingNo($track_no);
		$carrier_types = array();
		foreach ($results as $carrier=>$carrerName){
			$carrier_types["".$carrier.""] = $carrier;
		}
		
		//??????global?????????????????????match
		$globalSpecified = self::getGlobalShipByAndCarrierTypeMappingFromRedisForShipBy($ship_by1);
		if ($globalSpecified <> '')
			$carrier_types["".$globalSpecified.""] = $globalSpecified;
		
		//????????????????????????????????????????????????????????????
		if (empty($carrier_types))
			$carrier_types['0']='0';
		
		return $carrier_types;
	}
	
	static public function getAllCandidateCarrierType(){
		$results =  CarrierTypeOfTrackNumber::getAllExpressCode( );
		//array('0'=>'????????????',	'100001'=>'DHL', ... )
		$carrier_types = array();
		foreach ($results as $carrier=>$carrerName){			
			$carrier_types["".$carrier.""] = $carrier;
		}
		
		if (isset($carrier_types["888000001"]))
			unset( $carrier_types["888000001"] );
		
		if (isset($carrier_types["888000002"]))
			unset( $carrier_types["888000002"] );
	
		return $carrier_types;
	}	
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ????????????????????????:
	 * 		?????? ???????????? ???????????? ????????????, ??????????????????, ?????????????????? , ???????????????????????? ?????? "??????"
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param string $country_code ????????????
	 *
	 +---------------------------------------------------------------------------------------------
	 * @return string ?????????
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/3/10				?????????
	 +---------------------------------------------------------------------------------------------
	 **/    
	static public function autoSetCountriesNameMapping($country_code = "--"){
		if (isset(StandardConst::$COUNTRIES_CODE_NAME_CN[$country_code])){
			//?????????????????????
			$country_name = StandardConst::$COUNTRIES_CODE_NAME_CN[$country_code];
		}else{
			//?????????????????????????????????????????????
			if(isset(StandardConst::$COUNTRIES_CODE_NAME_EN[$country_code]))
				$country_name = StandardConst::$COUNTRIES_CODE_NAME_EN[$country_code];
		}
		
		//???????????????????????????????????????
		if (empty($country_name))
			$country_name = StandardConst::$COUNTRIES_CODE_NAME_CN['--'];
			
		return $country_name;
		
		
	}//end of autoSetCountriesNameMapping
	
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ???????????????:
	 * 		?????? ???????????? ???????????? ????????????, ??????????????????, ?????????????????? , ???????????????????????? ?????? "??????"
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param array $arr   ???????????????????????? 
	 *
	 +---------------------------------------------------------------------------------------------
	 * @return array  $arr
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/3/13				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function array_no_empty($arr) {
		if (is_array($arr)) {
			foreach ( $arr as $k => $v ) {
				if (empty($v)) unset($arr[$k]);
				elseif (is_array($v)) {
					$arr[$k] = array_no_empty($v);
				}
			}
		}
		return $arr;
	}//end of array_no_empty
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ????????????????????????????????????excel
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param array $arr   ????????????????????????
	 *
	 +---------------------------------------------------------------------------------------------
	 * @return array  $arr
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/3/17				?????????
	 +---------------------------------------------------------------------------------------------
	 **/	
	static public function showTrackingReportFor($date_from,$date_to){
		$csld_format_distribute = ConfigHelper::getGlobalConfig("Tracking/csld_format_distribute_$date_from",'NO_CACHE');
		$csld_format_distribute = json_decode($csld_format_distribute,true);
		if (!isset($csld_format_distribute))
			$csld_format_distribute = array();
		
		//step 1????????????????????????????????????????????????????????????????????????????????????
		$createOrUpdate = ['created','updated'];
		$SuccessOrFail = ['Success','Fail'];
		$carrierNation_all = isset($csld_format_distribute['carrier_nation_distribute']) ? $csld_format_distribute['carrier_nation_distribute'] : array();
		
		foreach ($createOrUpdate as $createOrUpdateLabel)
			foreach ($SuccessOrFail as $SuccessOrFailLabel){
				$CountFor[$createOrUpdateLabel][$SuccessOrFailLabel] = 0;
				if (isset($csld_format_distribute[$createOrUpdateLabel][$SuccessOrFailLabel]))
				foreach($csld_format_distribute[$createOrUpdateLabel][$SuccessOrFailLabel] as $codeFormat=>$count){
					$CountFor[$createOrUpdateLabel][$SuccessOrFailLabel] += $count;	
					$CountFor['Total'][$codeFormat] = (isset($CountFor['Total'][$codeFormat])?$CountFor['Total'][$codeFormat]:0 ) + $count;
					$CountFor[$SuccessOrFailLabel][$codeFormat] = (isset($CountFor[$SuccessOrFailLabel][$codeFormat])?$CountFor[$SuccessOrFailLabel][$codeFormat]:0 ) + $count;
				}

				if (!isset($CountFor[$createOrUpdateLabel][$SuccessOrFailLabel])) 
					$CountFor[$createOrUpdateLabel][$SuccessOrFailLabel] = 0;
		}
		
		$status_pie = isset($csld_format_distribute['status_pie']) ? $csld_format_distribute['status_pie'] : array(); 
		$source_pie = isset($csld_format_distribute['source_pie']) ? $csld_format_distribute['source_pie'] : array();
		$recommend_pie = isset($csld_format_distribute['Recm_prod_perform']) ? $csld_format_distribute['Recm_prod_perform'] : array();
		
		//Step 2, ??????make a report
		$excelRows = array();
		$excelRows [] = array("$date_from Tracking Report"); //line 1
		$excelRows [] = array();  //line 2
		
		//step 2.0, show section for ?????????????????????????????????view count???click count
		$excelRows [] = array("??????????????????????????????????????????????????????????????????????????? $date_from"." 23:59:59"); //line 1 of section
		$excelRows [] = array("????????????","????????????","????????????", "??????????????????" , "??????????????????",'??????/??????'); //table header
		 /*$recommend_pie = array('aliexpress'=>array(browse_count=>411,view_count=>30,click_count=10))
		  * */
		$Summary['platform'] = 0;
		$Summary['send_count'] = 0;
		$Summary['browse_count'] = 0;
		$Summary['prod_show_count'] = 0;
		$Summary['prod_click_count'] = 0;		
		foreach ( $recommend_pie as $platform=>$count){
		 		if (empty($count['send_count'])) $count['send_count'] = 0;
		 		if (empty($count['browse_count'])) $count['browse_count'] = 0;
		 		if (empty($count['prod_show_count'])) $count['prod_show_count'] = 0;
		 		if (empty($count['prod_click_count'])) $count['prod_click_count'] = 0;
		 		
		 		$Summary['send_count'] += $count['send_count'];
		 		$Summary['browse_count'] += $count['browse_count'];
		 		$Summary['prod_show_count'] += $count['prod_show_count'];
		 		$Summary['prod_click_count'] += $count['prod_click_count'];
		 		
				$excelRows [] = array($platform , number_format($count['send_count'],0) ,
						number_format($count['browse_count'],0)  ,  
						number_format($count['prod_show_count'],0),
						number_format($count['prod_click_count'],0),
						empty($count['prod_show_count'])?"": number_format($count['prod_click_count'] *100 /$count['prod_show_count'],2) ."%" ,
						);
		}//end of each status
		
		$excelRows [] = array("Total" , number_format($Summary['send_count'],0) ,
				number_format($Summary['browse_count'],0)  ,
				number_format($Summary['prod_show_count'],0),
				number_format($Summary['prod_click_count'],0),
				empty($Summary['prod_show_count'])?"": number_format($Summary['prod_click_count'] *100 /$Summary['prod_show_count'],2) ."%" ,
		);
		
		$excelRows [] = array();  //line gap
		//step 2.1 ????????????????????????
		$excelRows [] = array("????????????????????????????????????????????????????????????????????? $date_from"." 23:59:59"); //line 1 of section
		$status_total = 0;
		$status_call_total = 0;
		foreach ( $status_pie as $status=>$count){
			$status_total += $count;
		}
		//echo "got data ".print_r($csld_format_distribute,true)."<br>";
		//echo "status count ".count($status_pie)." total count $status_total <br>";
		//step 2.1.a, Load all ??????????????????for this date
		$command = Yii::$app->db->createCommand("select * from ut_ext_call_summary where substr(time_slot,1,10)='$date_from' and ext_call like 'Tracking.17@%'") ;
		$extCalls = $command->queryAll();
		$extCallsForStatus = [];
		foreach ($extCalls as $aStatusCall){
			$status_call_total += $aStatusCall['total_count'];
			if (!isset($extCallsForStatus[ $aStatusCall['ext_call'] ])) 
				$extCallsForStatus[ $aStatusCall['ext_call'] ] = 0;
			
			$extCallsForStatus[ $aStatusCall['ext_call'] ] += $aStatusCall['total_count'];
		}
		
		$excelRows [] = array("????????????","??????","??????", "????????????" , "??????",'???????????? OMS/????????????'); //table header

		$Summary['count'] = 0;
		$Summary['oms'] = 0;
		$Summary['manual'] = 0;
		foreach ( $status_pie as $status=>$count){
			//calculate this status ????????????
			$callCount = 0;
		//	echo "try to do for $status $count <br>";
			if ($status_call_total > 0 and isset($extCallsForStatus[ 'Tracking.17@'.Tracking::getChineseStatus($status) ]  )){
				$callCount = $extCallsForStatus[ 'Tracking.17@'.Tracking::getChineseStatus($status) ];				
				$excelRows [] = array(Tracking::getChineseStatus($status), number_format($count,0) , 
						number_format($count * 100 /$status_total,2)
						 ."%" ,  number_format($callCount,0) ,  
						number_format($callCount * 100 /$status_call_total,2) ."%" , 
						   number_format( (isset($source_pie['O'][$status])?$source_pie['O'][$status]:0),0) . " / ".
							number_format(((isset($source_pie['E'][$status])?$source_pie['E'][$status]:0)  + 
							  (isset($source_pie['M'][$status])?$source_pie['M'][$status]:0) ) ,0)  );
				
				$Summary['count'] += $count;
				$Summary['oms'] += (isset($source_pie['O'][$status])?$source_pie['O'][$status]:0);
				$Summary['manual'] += ((isset($source_pie['E'][$status])?$source_pie['E'][$status]:0)  + 
							  (isset($source_pie['M'][$status])?$source_pie['M'][$status]:0) );
			}
		}//end of each status
		//summary
		$excelRows [] = array("??????", number_format($Summary['count'],0) ,
						'' , '' ,	'' , number_format($Summary['oms'],0). " / ".	number_format($Summary['manual'],0) );
		
		$excelRows [] = array();  //line gap
		
		//step 2.1.5 ????????????(OMS???Excel?????????)?????????pie
		/* 
		$excelRows [] = array("?????????????????????????????? $date_from"." 23:59:59"); //line 1 of section
		$colHeader = array("????????????" );
		$all_souce_vs_status_report = array();
		 
		foreach ( $source_pie as $source_code=>$source_code_has){
			self::arrayPlus($source_code_has, $all_souce_vs_status_report);
		}
		
		foreach ($all_souce_vs_status_report as $status_code =>$total_count){
			$colHeader[] = Tracking::getChineseStatus($status_code);
		}
		
		$excelRows [] = $colHeader;
		
		$status_total = 0;
		$status_call_total = 0;
		foreach ( $source_pie as $source_code=>$source_code_has){
			$aRow = array();
			if ($source_code=='O')
				$aRow[] = 'OMS';
			else
				$aRow[] = '????????????';
			
			foreach ($all_souce_vs_status_report as $status_code =>$count){
				//add a column for this status count
				$theCount = 0;
				if (isset($source_code_has[$status_code]))
					$theCount = $source_code_has[$status_code];

				$aRow[] = $theCount;
				
			}//end of each status
			$excelRows [] = $aRow;
			
		}//end of each source code
		
		//subtotal
		$aRow = array();
		$aRow[] = '??????';
		foreach ($all_souce_vs_status_report as $status_code =>$count){
			//add a column for this status count
			$aRow[] = $count;
		}//end of each status
		$excelRows [] = $aRow;
		*/
		 
		$excelRows [] = array();  //line gap
		
		//step 2.2 ????????????????????????????????????
		$excelRows [] = array("","???????????????","????????????",	"?????????"	,"????????????",	"?????????");
		
		$createdTotal = $CountFor['created']['Success'] +$CountFor['created']['Fail'];
		$createdSuccessPercent = ResultHelper::formatPercentage( $createdTotal> 0 ? $CountFor['created']['Success']/$createdTotal : 0);
		$createdFailPercent = ResultHelper::formatPercentage( $createdTotal> 0 ? $CountFor['created']['Fail']/$createdTotal : 0);		
		$excelRows [] = ["????????????",$createdTotal,$CountFor['created']['Success'], $createdSuccessPercent,$CountFor['created']['Fail'], $createdFailPercent ];
		
		$updatedTotal = $CountFor['updated']['Success'] +$CountFor['updated']['Fail'];
		$updatedSuccessPercent = ResultHelper::formatPercentage( $updatedTotal> 0 ? $CountFor['updated']['Success']/$updatedTotal : 0);
		$updatedFailPercent = ResultHelper::formatPercentage( $updatedTotal> 0 ? $CountFor['updated']['Fail']/$updatedTotal : 0);
		$excelRows [] = ["????????????",$updatedTotal,$CountFor['updated']['Success'], $updatedSuccessPercent,$CountFor['updated']['Fail'], $updatedFailPercent ];
		
		$Total = $updatedTotal+$createdTotal;
		$SuccessPercent = ResultHelper::formatPercentage( $Total> 0 ? ($CountFor['updated']['Success']+$CountFor['created']['Success'])/$Total : 0);
		$FailPercent = ResultHelper::formatPercentage( $Total> 0 ? ($CountFor['updated']['Fail']+$CountFor['created']['Fail'])/$Total : 0);
		$excelRows [] = ["??????",$Total,$CountFor['updated']['Success'] + $CountFor['created']['Success'] , 
							$SuccessPercent,$CountFor['updated']['Fail']+$CountFor['created']['Fail'], $FailPercent ];
		
		//step 3, show ????????????code format top 5
		$FailCodeFormats = isset($CountFor['Fail'])?$CountFor['Fail'] : array();
		//	???????????????????????? key ??? value ?????????
		arsort ($FailCodeFormats);
		$excelRows [] = array();
		$excelRows [] = ["???????????????????????????",'',"????????????","????????????",'????????????','????????????','???????????????','??????????????????'];
		$ind = 0;
		foreach($FailCodeFormats as $codeFormat => $count){
			$ind++;
			if ($ind > 5) continue;
			
			if (isset($csld_format_distribute['created']['Fail'][$codeFormat]))
				$createdErrorCount = $csld_format_distribute['created']['Fail'][$codeFormat];
			else 
				$createdErrorCount = 0;
			
			if (isset($csld_format_distribute['updated']['Fail'][$codeFormat]))
				$updatedErrorCount = $csld_format_distribute['updated']['Fail'][$codeFormat];
			else
				$updatedErrorCount = 0;
			
			if (isset($csld_format_distribute['created']['Success'][$codeFormat]))
				$createdSuccessCount = $csld_format_distribute['created']['Success'][$codeFormat];
			else
				$createdSuccessCount = 0;
				
			if (isset($csld_format_distribute['updated']['Success'][$codeFormat]))
				$updatedSuccessCount = $csld_format_distribute['updated']['Success'][$codeFormat];
			else
				$updatedSuccessCount = 0;			
			
			$code1 = str_replace("#","A",$codeFormat);
			$code1 = str_replace("*","1",$code1);
			$carriers = CarrierTypeOfTrackNumber::checkExpressOftrackingNo($code1);
			
			$excelRows [] = ["$ind",$codeFormat,$updatedSuccessCount+$createdSuccessCount, $count,$createdErrorCount,$updatedErrorCount,'????????????', implode(",", $carriers)];
		}
		
		//step 3.5, show total os count
		$excelRows [] = [""];
		if(!isset($carrierNation_all['os_count']))
			$carrierNation_all['os_count'] = 0;
		$excelRows [] = ["??????????????????????????????  ".$carrierNation_all['os_count']. " ???"];
		
		//step 4, try to show distribution for Nations
		$excelRows [] = [""];
		$excelRows [] = ["??????????????????"];
		$maxRow = 20;
		
		$excelRows [] = ["??????","??????Code","??????","??????","??????"];
		if (isset($carrierNation_all['to_nation'])){
			arsort($carrierNation_all['to_nation']);
			$rowCount=0;
			$totalCount = 0;
			foreach ($carrierNation_all['to_nation'] as $nationCode=>$nationCount)
				$totalCount +=  $nationCount;
			
			foreach ($carrierNation_all['to_nation'] as $nationCode=>$nationCount){
				$rowCount++;
				if ($rowCount>$maxRow) 
					break;
				if (trim($nationCode)=='')
					continue;
				
				$excelRows [] = [$rowCount ,$nationCode,
								StandardConst::getNationChineseNameByCode($nationCode)  ,
								$nationCount, round($nationCount *100 /$totalCount,2) ." %"];
				
			}
		}
		
		//Step 5, try to show distribution for Carrier Types
		$excelRows [] = [""];
		$excelRows [] = ["??????????????????"];
		$maxRow = 20;
		
		$excelRows [] = ["??????", "?????????","??????","??????"];
		if (isset($carrierNation_all['carrier'])){
			arsort($carrierNation_all['carrier']);
			$rowCount=0;
			$totalCount = 0;
			$allCarriers =  CarrierTypeOfTrackNumber::getAllExpressCode( );
			//array('0'=>'????????????',	'100001'=>'DHL', ... )
			
			foreach ($carrierNation_all['carrier'] as $carrierCode=>$carrierCount)
				$totalCount +=  $carrierCount;
				
			foreach ($carrierNation_all['carrier'] as $carrierCode=>$carrierCount){
				$rowCount++;
				if ($rowCount>$maxRow)
					break;
				if (trim($carrierCode)=='')
					continue;
		
				$excelRows [] = [$rowCount ,
				isset($allCarriers[$carrierCode])?$allCarriers[$carrierCode]: $carrierCode ,
				$carrierCount, round($carrierCount *100 /$totalCount,2) ." %"];
		
			}
		}
		
		return $excelRows;		
	}
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????Left menu ??????tracking ??????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param na  
	 +---------------------------------------------------------------------------------------------
	 * @return array  $menuLabelList ????????????tracking ???
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/3/21				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function getMenuStatisticData(){
		//step 1, ??????load ?????????cache????????????????????????????????????????????????cache
		$track_statistics_str = self::getTrackerTempDataFromRedis("left_menu_statistics");
		$track_statistics = json_decode($track_statistics_str,true);
		if (empty($track_statistics)) $track_statistics = array();
		
		$scope = 'all';
		if(!empty($_GET['sellerid']))
			$scope = $_GET['sellerid'];
		
		if($scope == 'all')
		$track_statistics = array();
			
		if (!isset($track_statistics[$scope]['all']) ){
		
			$menuLabelList = [
				'normal_parcel'=>0 , 
					'shipping_parcel'=>0 ,
					'no_info_parcel'=>0 ,
					'suspend_parcel'=>0 ,
				'exception_parcel'=>0 ,
					'ship_over_time_parcel'=>0 ,
					'rejected_parcel'=>0 ,
					'arrived_pending_fetch_parcel'=>0 ,
					'delivery_failed_parcel'=>0,
					'unshipped_parcel'=>0 ,
				'all'=>0 ,
				'received_message'=>0,
				'arrived_pending_message'=>0,
				'delivery_failed_message'=>0,
				'rejected_message'=>0,
				'shipping_message'=>0,
				'ignored_parcel'=>0,
				'quota_insufficient'=>1
			];
			$Tracking = new Tracking();
		
			$d=strtotime("-7 days");
			$startdate = date("Y-m-d", $d);
			
			$menuCondition = [
				'normal_parcel'=>['not',['mark_handled'=>'Y']],
				'shipping_parcel'=>['not',['mark_handled'=>'Y']],
				'no_info_parcel'=>['not',['mark_handled'=>'Y']] ,
				'suspend_parcel'=>['not',['mark_handled'=>'Y']] ,
				'exception_parcel'=>['not',['mark_handled'=>'Y']] ,
				'ship_over_time_parcel'=>['not',['mark_handled'=>'Y']] ,
				'rejected_parcel'=>['not',['mark_handled'=>'Y']] ,
				'arrived_pending_fetch_parcel'=>['not',['mark_handled'=>'Y']] ,
				'delivery_failed_parcel'=>['not',['mark_handled'=>'Y']] ,
				'unshipped_parcel'=>['not',['mark_handled'=>'Y']] ,
				'all'=>['not',['mark_handled'=>'Y']] ,
				'received_message'=>['status'=>['platform_confirmed','received'], 'received_notified'=>'N' ,'source'=>'O'],
				'arrived_pending_message'=>['status'=>'arrived_pending_fetch', 'pending_fetch_notified'=>'N','source'=>'O'],
				'delivery_failed_message'=>['status'=>'delivery_failed', 'delivery_failed_notified'=>'N','source'=>'O'],
				'rejected_message'=>['status'=>'rejected', 'rejected_notified'=>'N', 'source'=>'O'],
				'shipping_message'=>['status'=>'shipping', 'shipping_notified'=>'N', 'source'=>'O'],
				'ignored_parcel'=>['status'=>'ignored'],
				'quota_insufficient'=>['status'=>'quota_insufficient'],
			];
			
			//????????????????????????????????? ?????? complete state??????????????? marked handled ??????????????????
			foreach($menuLabelList as $menu_type=>&$value){
				if (! empty ($menuCondition[$menu_type])){
					
					if (stripos($menu_type, 'message')){
						if ($menu_type== 'shipping_message')
							$sevenDayAgoSql  = " and `ship_out_date` >= '$startdate' ";
						else 
							$sevenDayAgoSql  = " and `last_event_date` >= '$startdate' ";
						
						$sevenDayAgoSql .=" and first_event_date IS NOT NULL and first_event_date <> last_event_date ";
					}else{
						$sevenDayAgoSql = "";
					}
						
						
					$TrackingQuery = Tracking::find();
										
					$TrackingQuery
						->andWhere($menuCondition[$menu_type])
						->andWhere(Tracking::getTrackingConditionByClassification($menu_type))
						->andWhere(" 1=1 $sevenDayAgoSql ")// state<>'complete' and mark_handled <> 'Y'
						->andWhere("state<>'deleted' ");
					
				
					
					if(!empty($_GET['sellerid']))
						$TrackingQuery->andWhere(['seller_id'=>$_GET['sellerid']]);
					
					$value = $TrackingQuery->count();
					
					/* ??????sql  
					// ?????????????????????
					 $TrackingQuery = Tracking::find()->andWhere($menuCondition[$menu_type])
								->andWhere(Tracking::getTrackingConditionByClassification($menu_type))
								->andWhere("1=1 $sevenDayAgoSql ")// state<>'complete' and mark_handled <> 'Y'
								->andWhere("state<>'deleted' ");
					
					
					 
					 if(!empty($_GET['sellerid']))
					 	$TrackingQuery->andWhere(['seller_id'=>$_GET['sellerid']]);
					 
					$tmpCommand = $TrackingQuery->createCommand();
					echo "<br><br> $menu_type : ".$tmpCommand->getRawSql();
					*/
					
					
				}
			}
		
			$track_statistics_scope = $menuLabelList;
			
			if(empty($_GET['sellerid']))
				$track_statistics_scope['completed_parcel'] = Tracking::find()->andWhere(Tracking::getTrackingConditionByClassification('completed_parcel'))->count();
			else 
				$track_statistics_scope['completed_parcel'] = Tracking::find()->andWhere(Tracking::getTrackingConditionByClassification('completed_parcel'))->andWhere(['seller_id'=>$_GET['sellerid']])->count();
			
			$track_statistics[$scope] = $track_statistics_scope;
			
			self::setTrackerTempDataToRedis("left_menu_statistics", json_encode($track_statistics));
		}//end of not cached
		return $track_statistics;
	} //end of getMenuStatisticData	
	
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????tracking???????????????, ???????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param track_no string or list   ????????????  required
	 +---------------------------------------------------------------------------------------------
	 * @return array  
	 * 					success  boolean  ?????? ????????????
	 * 					message  string   ??????????????????
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/4/9				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function deleteTracking($trackNoOrList){
		$result ['success'] = true;
		$result ['message'] = '';
		$delItems = [];
		
		//????????????redis??????
		$track_statistics_str = self::getTrackerTempDataFromRedis("left_menu_statistics");
		$track_statistics = json_decode($track_statistics_str,true);
		
		try {
			// ?????? ????????????????????? 
			$TrackingList = Tracking::findAll(['track_no'=>$trackNoOrList]);
			
			//???????????? ?????????puid
			$puid = \Yii::$app->subdb->getCurrentPuid();
			$transaction = Yii::$app->get('subdb')->beginTransaction();
			$userName = Yii::$app->user->identity->getUsername();
			foreach($TrackingList as $aTrack){
				$track_no = $aTrack->track_no;
				$aTrack->state =  Tracking::getSysState("?????????");
				$delRt = $aTrack->delete();
				 
					//???log
					$type = 'tracking';
					$key = $aTrack->track_no;
					$operation = "??????";
				//	OperationLogHelper::log($type, $key, $operation , '' , $userName);
				//}
				 
				//??????????????????
				$delItems[] = $track_no;
				
				if(isset($track_statistics[$aTrack->seller_id]))
					unset($track_statistics[$aTrack->seller_id]);
				if(isset($track_statistics['all']))
					unset($track_statistics['all']);
			}//end of foreach
			$result ['success'] = (count($delItems) != 0 );
			
			$DelApiQRt = TrackerApiQueue::deleteAll(['track_no'=>$delItems,'puid'=>$puid,'status'=>'P']);
			$DelApiSubQRt = TrackerApiSubQueue::deleteAll(['track_no'=>$delItems,'puid'=>$puid,'sub_queue_status'=>'P']);
			
			/*
			if ( count($delItem) == 0 ){
				$result ['success'] = false;
			}else{
				$result ['success'] = true;
			}
			*/
			// ?????? trackNoOrList ?????????????????????????????????
			if (is_array($trackNoOrList)){
				//????????????
				$result['message'] = TranslateHelper::t("?????????????????????") .count($trackNoOrList) . TranslateHelper::t('???, ?????????????????????????????????') . count($delItem).TranslateHelper::t('???');
			}else{
				if ($result ['success'] ){
					$result['message'] = $trackNoOrList . (TranslateHelper::t('??????????????????!'));
				}else{
					$result ['message'] = $trackNoOrList . (TranslateHelper::t('???????????????????????????!'));
				}
			}
			
			//force update the top menu statistics
			
			self::setTrackerTempDataToRedis("left_menu_statistics", json_encode($track_statistics));
			self::setTrackerTempDataToRedis("top_menu_statistics", json_encode(array()));

			$transaction->commit ();
		} catch (Exception $e) {
			$transaction->rollBack ();
			$result ['success'] = false;
			$result ['message'] = $e->getMessage();
		}
		return $result;
	}//end of DeleteTracking
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????  tracking ??????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param track_no 	string   ????????????  		required
	 * @param remark 	string 	  ??????			required
	 +---------------------------------------------------------------------------------------------
	 * @return array  
	 * 					success  boolean  ?????? ????????????
	 * 					message  string   ??????????????????
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/4/9				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function appendTrackingRemark($track_no , $remark){
		try {
			//get user name
			$userName = Yii::$app->user->identity->getUsername();
			
			//get tracking
			$model = Tracking::findOne(['track_no'=>$track_no]);
			
			//check model empty or not , if empty , return error message
			if (empty($model)){
				$result ['success'] = false;
				$result ['message'] = $track_no . TranslateHelper::t('????????????????????????');
				return $result;
			}
			$remarkArr = [];
			// get origin remark and decode it
			if (!empty($model->remark) )
				$remarkArr =  json_decode($model->remark);
			
			//set append remark
			$row['who'] = $userName;
			$row['when'] = date('Y-m-d H:i:s');
			$row['what'] = $remark;
			
			//push new remark into origin remark
			$remarkArr [] = $row;

			//save remark,???????????????json encode??????????????????????????????model????????????			
			$affectedRows = 0;
			$models = Tracking::findAll(['track_no'=>$track_no]);
			foreach ($models as $model1){
				$model1->remark = json_encode($remarkArr,true);
				$model1->save(false);
				$affectedRows++;
			}
			
			if ($affectedRows > 0){
			
				$result ['success'] = true;
				$result ['message'] = TranslateHelper::t('????????????');
				return $result;
			}else{
				$result ['success'] = false;
				
				return $result;
			}
		} catch (Exception $e) {
			$result ['success'] = false;
			$result ['message'] = print_r($e->getMessage(),true);
			return $result;
		}
	}//end of AppendTrackingRemark

	/**
	 +---------------------------------------------------------------------------------------------
	 * ????????????tracking no ?????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     $order_id    platform order id
	 * @param     $error       string of ????????????
	 +---------------------------------------------------------------------------------------------
	 * @return					array ('success' => true, 'message'='')
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		yzq		2015/5/30				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function setMsgSendError($order_id,$error=''){
		$result ['success'] = true;
		$result ['message'] = '';

			//Load the object
		 
			$track_obj = Tracking::find()->where(['order_id'=>$order_id])->asArray()->one();
			if (empty($track_obj)){
				$result ['success'] = false;
				$result ['message'] = 'Failed to Load object for Tracking orderid '.$order_id;
				return $result;
			}
			 
		if (!empty($error)){
			$addi_info = json_decode($track_obj['addi_info'],true);
			$addi_info['send_msg_error'] = $error;
			$track_obj['addi_info'] = json_encode($addi_info);
		}
		
		//msg_sent_error = "Y";
		$command = Yii::$app->subdb->createCommand("update lt_tracking set msg_sent_error='Y', addi_info=:addi_info where order_id  = :order_id"  );
		$command->bindValue(':addi_info', $track_obj['addi_info'], \PDO::PARAM_STR);
		$command->bindValue(':order_id', $order_id, \PDO::PARAM_STR);
		$affectRows = $command->execute();

		return $result;	
	}//end of  function
	
	/**
		 +---------------------------------------------------------------------------------------------
		 * ???????????????????????????????????????track api request ???tracking no????????????????????????track queue???
		 * ??????????????????job
		 +---------------------------------------------------------------------------------------------
		 * @access static
		 +---------------------------------------------------------------------------------------------
		 +---------------------------------------------------------------------------------------------
		 * @return array
		 * 					success  boolean  ?????? ????????????
		 * 					message  string   ??????????????????
		 +---------------------------------------------------------------------------------------------
		 * log			name	date					note
		 * @author		yzq		2015/4/9				?????????
		 +---------------------------------------------------------------------------------------------
		 **/
		static public function postBufferIntoTrackQueue(){
			global $CACHE;
			$now_str = date('Y-m-d H:i:s');
			$rtn['message'] = "";
			$rtn['success'] = true;
			//Step 0, ???????????????job??????????????????????????????????????????????????????exit???
			$currentMainQueueVersion = ConfigHelper::getGlobalConfig("Tracking/postBufferIntoTrackQueueVersion",'NO_CACHE');
			if (empty($currentMainQueueVersion))
				$currentMainQueueVersion = 0;
			
			//???????????????????????????????????????global config??????????????????
			if (empty(self::$putIntoTrackQueueVersion))
				self::$putIntoTrackQueueVersion = $currentMainQueueVersion;
				
			//???????????????version????????????????????????version???????????????????????????job??????????????????
			if (self::$putIntoTrackQueueVersion <> $currentMainQueueVersion){
				TrackingAgentHelper::markJobUpDown("Trk.PostBufferTrackQueueDown",$CACHE['jobStartTime']);
				DashBoardHelper::WatchMeDown();
				exit("Version new $currentMainQueueVersion , this job ver ".self::$putIntoTrackQueueVersion." exits for using new version $currentMainQueueVersion.");
			}
			//?????? pending records
			$command = Yii::$app->db_queue->createCommand("select * from tracker_generate_request2queue order by user_require_update desc limit 300") ;
			$pendings = $command->queryAll();

			//if no pending one found, return true, message = 'n/a';
			if (empty($pendings) or count($pendings) < 1){
				$rtn['message'] = "n/a";
				$rtn['success'] = true;
				//echo "No pending, idle 4 sec... ";
				return $rtn;
			}

			//step 2, ????????????
			$doneIds = array();
			$changedPuid = array();
			$this_puid = 0;
			foreach ($pendings as $aPending){
 
				if (strtoupper($aPending['user_require_update']) == "B"){
					//2015-08-25 kh user_require_update ==   B ?????????????????????????????????
					self::generateOneRequestForTracking($aPending['track_no'],true,'',['batchupdate' =>true]);
				}else{
					self::generateOneRequestForTracking($aPending['track_no'], $aPending['user_require_update'] =='Y' );
				}
				
				$changedPuid[$this_puid] = $this_puid;
				$doneIds[] = $aPending['id'];
			}//end of each pending

			//???Api Request Buffer ?????????insert ???db
			self::postTrackingApiQueueBufferToDb();
			
			$command = Yii::$app->db_queue->createCommand("delete from tracker_generate_request2queue  where id in ( -1,". implode(",", $doneIds) .")");
			$command->execute();
			return $rtn;
		}//end of function putIntoTrackQueue
		
		/**
		 +---------------------------------------------------------------------------------------------
		 * ?????????????????????track no?????????????????????????????????????????????background job ??????????????????
		 * ?????????????????????????????????????????????
		 +---------------------------------------------------------------------------------------------
		 * @access static
		 +---------------------------------------------------------------------------------------------
	 	 * @param Tracking Nos 	           a string of tracking code, or array of many Tracking codes
	 	 * @param User_require_update      an indicator, default false, when true???higher priority will 
	 	 *                                 be adopt when putting in API request Queue                 
		 +---------------------------------------------------------------------------------------------
		 * @return array
		 * 					
		 +---------------------------------------------------------------------------------------------
		 * log			name	date					note
		 * @author		yzq		2015/4/9				?????????
		 +---------------------------------------------------------------------------------------------
		 **/
		static public function putIntoTrackQueueBuffer($track_nos , $user_require_update=false ){
			/*
			//?????? ?????? log
			$logTimeMS1=TimeUtil::getCurrentTimestampMS();
			$logMemoryMS1 = (memory_get_usage()/1024/1024);
			*/
			
			if (! in_array($user_require_update, ['Y','B']))
				$user_require_update ="";
			
			$now_str = date('Y-m-d H:i:s');
			$puid = \Yii::$app->subdb->getCurrentPuid();
			//use sql PDO, not Model here, for best performance
			$sql = " replace INTO  `tracker_generate_request2queue` 
					( `puid`, `track_no`,`create_time`,user_require_update) VALUES ";
			
			$sql_values = '';
			if (!is_array($track_nos)){
				$track_no1 = $track_nos;
				$track_nos = array();
				$track_nos[] = $track_no1;
			}
			
			foreach ($track_nos as $track_no){
				$puid = self::removeYinHao($puid);
				$track_no = self::removeYinHao($track_no);
				
				$sql_values .= ($sql_values==''?'':","). "('$puid','$track_no','$now_str','$user_require_update' )";
				if (strlen($sql_values) > 3000){
					//one sql syntax do not exceed 4800, so make 3000 as a cut here
					//?????? memeroy table ?????????????????????????????? ??????30000 ?????????????????????
					
					$command = Yii::$app->db_queue->createCommand("select count(1) from tracker_generate_request2queue");
					$QueueDepth = $command->queryScalar();
					while ($QueueDepth > 30000){
						sleep(10);
						$command = Yii::$app->db_queue->createCommand("select count(1) from tracker_generate_request2queue");
						$QueueDepth = $command->queryScalar();
					}
					
					$command = Yii::$app->db_queue->createCommand($sql.$sql_values .";");
					$command->execute();
					$sql_values = '';
				}
			}//end of each track no
			
			/*
			//?????? ?????? log
			$logTimeMS2=TimeUtil::getCurrentTimestampMS();
			$logMemoryMS2 = (memory_get_usage()/1024/1024);
			$current_time_cost = $logTimeMS2-$logTimeMS1;
			$current_memory_cost = $logMemoryMS2-$logMemoryMS1;
			$msg = (__FUNCTION__)."   ,t1_2=".($current_time_cost).",memory=".($current_memory_cost)."M ";
			\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Background',$msg],"edb\global");
			*/
			//\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Background',"SQL to be execute:".$sql.$sql_values ],"edb\global");
			if ($sql_values <> ''){
				$command = Yii::$app->db_queue->createCommand($sql.$sql_values.";");
				$command->execute();
			}
			/*
			//?????? ?????? log
			$logTimeMS3=TimeUtil::getCurrentTimestampMS();
			$logMemoryMS3 = (memory_get_usage()/1024/1024);
			$current_time_cost = $logTimeMS3-$logTimeMS2;
			$current_memory_cost = $logMemoryMS3-$logMemoryMS2;
			$msg = (__FUNCTION__)."   ,t2_3=".($current_time_cost).",memory=".($current_memory_cost)."M ";
			\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Background',$msg],"edb\global");
			*/
		}//end of function wantIntoTrackQueue
	
		/**
		 +---------------------------------------------------------------------------------------------
		 * ???Tracking??????buffer ?????????????????????Tracking ???????????????insert ???????????????
		 +---------------------------------------------------------------------------------------------
		 * @access static
		 +---------------------------------------------------------------------------------------------
		 +---------------------------------------------------------------------------------------------
		 * @return array
		 *
		 +---------------------------------------------------------------------------------------------
		 * log			name	date					note
		 * @author		yzq		2015/4/9				?????????
		 +---------------------------------------------------------------------------------------------
		 **/
		static public function postTrackingBufferToDb(){	 
			//use sql PDO, not Model here, for best performance
			/*
			$sql = " INSERT INTO  `lt_tracking`
					( `order_id`, seller_id, `track_no`,`status`,state,source,platform,
					batch_no,create_time,update_time,ship_by,delivery_fee,
					ship_out_date,addi_info) VALUES ";
*/
			$updateFields = array('order_id'=>1,'seller_id'=>1, 'track_no'=>1,
					 'status'=>1, 'state'=>1, 'source'=>1, 'platform'=>1,
					'batch_no'=>1, 'create_time'=>1, 'update_time'=>1, 'ship_by'=>1,
					'ship_out_date'=>1 , 'delivery_fee'=>1, 'addi_info'=>1
			  );
			$Trackings = Tracking::$Insert_Data_Buffer;
			Tracking::$Insert_Data_Buffer = array();
			
			SQLHelper::groupInsertToDb(Tracking::tableName(), $Trackings,'subdb', $updateFields);

		}//end of function postTrackingBufferToDb
		
			
		/**
		 +---------------------------------------------------------------------------------------------
		 * ???Buffer data ?????? Tracking Queue db?????? ?????????insert ???????????????
		 +---------------------------------------------------------------------------------------------
		 * @access static
		 +---------------------------------------------------------------------------------------------
		 +---------------------------------------------------------------------------------------------
		 * @return array
		 *
		 +---------------------------------------------------------------------------------------------
		 * log			name	date					note
		 * @author		yzq		2015/4/9				?????????
		 +---------------------------------------------------------------------------------------------
		 **/
		static public function postTrackingApiQueueBufferToDb(){			
			//use sql PDO, not Model here, for best performance
			global $CACHE;
			$sql = " INSERT INTO  `tracker_api_queue`".
					"( `priority`, `puid`,`track_no`,status,candidate_carriers,".
					"selected_carrier, create_time,update_time,addinfo ) VALUES ";
		
			$TrackingQueueReqs = self::$Insert_Api_Queue_Buffer;
			self::$Insert_Api_Queue_Buffer = array();
			
			$updateFields = array('priority'=>1,'puid'=>1, 'track_no'=>1,
					'status'=>1, 'candidate_carriers'=>1, 'selected_carrier'=>1, 
					'create_time'=>1,'update_time'=>1, 'addinfo'=>1 
			);
		 
			
			
			SQLHelper::groupInsertToDb(TrackerApiQueue::tableName(), $TrackingQueueReqs,'db_queue', $updateFields);
			
		}//end of function postTrackingBufferToDb

		public static function removeYinHao($keyword){
			$keyword = str_replace("'","`",$keyword);
			$keyword = str_replace('"',"`",$keyword);
			return $keyword;
		}
		
		public static function healthCheckEach(){
		
		}
		
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? ?????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     $platform			??????  eg : all , ebay , aliexpress
	 +---------------------------------------------------------------------------------------------
	 * @return						$data [
	 * 									0 =>[
	 * 									id => ?????? lt_tracking.sellerid
	 * 									platform=> ??????
	 *									name=> ????????? ]...... ]
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/5/16				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function getAccountFilterData($platform='all'){
	 
		$uid = \Yii::$app->subdb->getCurrentPuid(); //ystest
		//????????????	//liang
		$AllAuthorizePlatformAccounts = UserHelper::getUserAllAuthorizePlatformAccountsArr();
		$result = [];
		if (in_array(strtolower($platform),['ebay','all'])){
			if(!empty($AllAuthorizePlatformAccounts['ebay'])){
			//$ebayUserList  = EbayAccountsApiHelper::helpList('expiration_time' , 'asc');
				$ebayUserList = SaasEbayUser::find()->where("uid = '$uid'")->andWhere(['selleruserid'=>array_keys($AllAuthorizePlatformAccounts['ebay'])])
				->asArray()->all();
				foreach($ebayUserList as $row){
					$account = [];
					$account['id'] = $row['ebay_uid'];
					$account['name'] = $row['selleruserid'];
					$account['platform'] = 'ebay'; 
					$result[] = $account;
				}
			}
		}
		
		if (in_array(strtolower($platform),['aliexpress','all'])){
			if(!empty($AllAuthorizePlatformAccounts['aliexpress'])){
				$AliexpressUserList = SaasAliexpressUser::find()->where('uid ='.$uid)
				->andWhere(['sellerloginid'=>array_keys($AllAuthorizePlatformAccounts['aliexpress'])])
				->orderBy('refresh_token_timeout desc')
				->asArray()
				->all();
				
				foreach($AliexpressUserList as $row){
					$account = [];
					$account['id'] = $row['aliexpress_uid'];
					$account['name'] = $row['sellerloginid'];
					$account['store_name'] = $row['store_name'];
					$account['platform'] = 'aliexpress';
					$result[] = $account;
				}
			}
		}
		
		if (in_array(strtolower($platform),['dhgate','all'])){
			if(!empty($AllAuthorizePlatformAccounts['dhgate'])){
				$DhgateUserList = SaasDhgateUser::find()->where('uid ='.$uid)
				->andWhere(['sellerloginid'=>array_keys($AllAuthorizePlatformAccounts['dhgate'])])
				->orderBy('refresh_token_timeout desc')
				->asArray()
				->all();
					
				foreach($DhgateUserList as $row){
					$account = [];
					$account['id'] = $row['dhgate_uid'];
					$account['name'] = $row['sellerloginid'];
					$account['platform'] = 'dhgate';
					$result[] = $account;
				}
			}
		}
		
		if (in_array(strtolower($platform),['wish','all'])){
			if(!empty($AllAuthorizePlatformAccounts['wish'])){
				$wishUserList = SaasWishUser::find()->where(['uid'=>$uid , 'is_active'=>'1'])
				->andWhere(['store_name'=>array_keys($AllAuthorizePlatformAccounts['wish'])])
				->asArray()->all();
				
				foreach($wishUserList as $row){
					$account = [];
					$account['id'] = $row['site_id'];
					$account['name'] = $row['store_name'];
					$account['platform'] = 'wish';
					$result[] = $account;
				}
			}
		}
		
		if (in_array(strtolower($platform),['lazada','all'])){
			if(!empty($AllAuthorizePlatformAccounts['lazada'])){
				$lazdaaUserList = SaasLazadaUser::find()->where(['puid'=>$uid , 'status'=>'1'])
				->andWhere(['platform_userid'=>array_keys($AllAuthorizePlatformAccounts['lazada'])])
				->asArray()->all();
				
				foreach($lazdaaUserList as $row){
					$account = [];
					$account['id'] = $row['lazada_uid'];
					$account['name'] = $row['platform_userid'];
					$account['platform'] = 'lazada';
					$result[] = $account;
				}
			}
		}
		
		if (in_array(strtolower($platform),['cdiscount','all'])){
			if(!empty($AllAuthorizePlatformAccounts['cdiscount'])){
				$lazdaaUserList = SaasCdiscountUser::find()->where(['uid'=>$uid , 'is_active'=>'1'])
				->andWhere(['username'=>array_keys($AllAuthorizePlatformAccounts['cdiscount'])])
				->asArray()->all();
					
				foreach($lazdaaUserList as $row){
					$account = [];
					$account['id'] = $row['site_id'];
					$account['name'] = $row['username'];
					//$account['store_name'] = $row['store_name'];
					$account['platform'] = 'cdiscount';
					$result[] = $account;
				}
			}
		}
		
		if (in_array(strtolower($platform),['amazon','all'])){
			if(!empty($AllAuthorizePlatformAccounts['amazon'])){
				$amzUserList = SaasAmazonUser::find()->where(['uid'=>$uid , 'is_active'=>'1'])
				->andWhere(['merchant_id'=>array_keys($AllAuthorizePlatformAccounts['amazon'])])
				->asArray()->all();
			
				foreach($amzUserList as $row){
					$account = [];
					$account['id'] = $row['amazon_uid'];
					$account['merchant_id'] = $row['merchant_id'];
					$account['name'] = $row['store_name'];
					//$account['store_name'] = $row['store_name'];
					$account['platform'] = 'amazon';
					$result[] = $account;
				}
			}
		}
		
		return $result;
	}//end of getAccountFilterData
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? ??????????????? ????????? ????????? 
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     $platform			??????  eg : all , ebay , aliexpress
	 +---------------------------------------------------------------------------------------------
	 * @return						$data [
	 * 									platform =>[
	 * 									id => name
	 * 									 ]...... ]
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/5/16				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function getAccountMappingNameData($platform='all'){
		$target = self::getAccountFilterData($platform);
		$result = [];
		foreach($target as $row){
			$result[$row['platform']][$row['id']] = $row['name'];
		}
		unset($target);
		return $result;
	}//end of getAccountMappingNameData
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? ??????????????? ????????? ?????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     $platform			??????  eg : all , ebay , aliexpress
	 +---------------------------------------------------------------------------------------------
	 * @return						$data [
	 * 									platform =>[
	 * 									name  => id
	 * 									 ]...... ]
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/5/16				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function getAccountMappingIDData($platform='all'){
		$target = self::getAccountFilterData($platform);
		$result = [];
		foreach($target as $row){
			$result[$row['platform']][$row['name']] = $row['id'];
		}
		unset($target);
		return $result;
	}//end of getAccountMappingNameData
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? ???????????????????????? template id
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     $platform			??????  eg : all , ebay , aliexpress
	 +---------------------------------------------------------------------------------------------
	 * @return						$data [
	 * 									0 =>[
	 * 									id => ?????? lt_tracking.sellerid
	 * 									platform=> ??????
	 *									name=> ????????? ]...... ]
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/5/16				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function getDefaultTemplate($track_no_list){
		$TrackingData = Tracking::find()->andWhere(['track_no'=>$track_no_list])->asArray()->One();
		$platform = empty($TrackingData['platform'])?"na":$TrackingData['platform'];
		$seller_id = empty($TrackingData['seller_id'])?"na":$TrackingData['seller_id'];
		$status = empty($TrackingData['status'])?"na":$TrackingData['status'];
		//step 1 platform + sellerid + ??????
		$pathlist[] = 'Tracking/DT_'.$platform.'_'.$seller_id.'_'.$status;
		//step 2 platform + ??????
		$pathlist[] = 'Tracking/DT_'.$platform.'_'.$status;
		//step 3 sellerid + ??????
		$pathlist[] = 'Tracking/DT_'.$seller_id.'_'.$status;
		//step 4 ??????
		$pathlist[] = 'Tracking/DT_'.$status;
		foreach($pathlist as $path){
			$result = self::getTrackerTempDataFromRedis($path );
			if (!empty($result))
				return ['path'=>$path , 'template_id'=>$result];
		}
		return ['path'=>'Tracking/DT_'.$platform.'_'.$seller_id.'_'.$status , 'template_id'=>1];
	}//end of getDefaultTemplate
	
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? ???????????????  ?????? ???????????????  ??????????????????????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     $status			??????   eg : received , arrived_pending_fetch , rejected
	 +---------------------------------------------------------------------------------------------
	 * @return						string or array 
	 * 								received_notified or [
	 * 														'received'=> 'received_notified',
	 * 														'arrived_pending_fetch'=>'pending_fetch_notified',
	 * 														'rejected'=>'rejected_notified',
	 * 														'shipping'=>'shipping_notified',
	 * 													];
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/5/16				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function getNotifiedFieldNameByStatus($status=''){
		$NOTIFIED_STATUS_MAPPING = [
		'received'=> 'received_notified',
		'arrived_pending_fetch'=>'pending_fetch_notified',
		'delivery_failed'=>'delivery_failed_notified',
		'rejected'=>'rejected_notified',
		'shipping'=>'shipping_notified',
		];
		
		if (!empty($status)){
			if (!empty($NOTIFIED_STATUS_MAPPING[$status])){
				return $NOTIFIED_STATUS_MAPPING[$status];
			}else{
				return '';
			}
		}else{
			return $NOTIFIED_STATUS_MAPPING;
		}
	}//end of getNotifiedFieldNameByStatus
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? ??????????????????  ?????? ???????????????  ???????????? ?????????  ????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     $Parcel			??????   eg : completed_parcel , arrived_pending_fetch_parcel , rejected_parcel
	 +---------------------------------------------------------------------------------------------
	 * @return						string or array
	 * 								received or [
	 * 														'completed_parcel'=>'received' ,
	 * 														'arrived_pending_fetch_parcel'=>'arrived_pending_fetch' ,
	 * 														'rejected_parcel'=>'rejected' ,
	 * 														'shipping_parcel'=>'shipping' ,
	 * 													];
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/5/16				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function getStatusByParcel($Parcel=''){ 
		$pacel_mapping = [
		'completed_parcel'=>'received' ,
		'arrived_pending_fetch_parcel'=>'arrived_pending_fetch' ,
		'delivery_failed_parcel'=>'delivery_failed' ,
		'rejected_parcel'=>'rejected' ,
		'shipping_parcel'=>'shipping' ,
		];
		
		if (!empty($Parcel)){
			if (!empty($pacel_mapping[$Parcel])){
				return $pacel_mapping[$Parcel];
			}else{
				return '';
			}
		}else{
			return $pacel_mapping;
		}
	}//end of getStatusByParcel
	
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? ?????????track no ???????????? ????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     $TrackNoList		string/array			?????????   eg : '123' or ['123','124']
	 +---------------------------------------------------------------------------------------------
	 * @return						string or array
	 * 								'123' or ['123','124']
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/6/5				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function getActiveTrackNo($TrackNoList){
		//????????????ebay ??? aliexpress???????????????
		$result = Tracking::find()
					->select(['track_no'])
					->andWhere(['track_no'=>$TrackNoList])
					->andWhere([ 'platform'=>['ebay','aliexpress','amazon','cdiscount']])
					->andWhere(' LENGTH(order_id) >0')
					->andWhere(' LENGTH(seller_id) >0')
					->asArray()->all();
		
		$rt = [];
		foreach($result as $row){
			$rt[] = $row['track_no'];
		}
		return $rt;
	}//end of getTrackNoExistOrderId
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????????????????list
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param   	$sort
	 * @param		$order
	 +---------------------------------------------------------------------------------------------
	 * @return		array
	 +---------------------------------------------------------------------------------------------
	 * log			name		date			note
	 * @author		lzhl		2015/7/24		?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function getMsgTemplate($sort,$order){
		$data=[];
		$query = MsgTemplate::find()->where(['not',['id'=>0]]);
		if(!empty($sort) && !empty($order))
			$query->orderBy("$sort $order");
		
		$pagination = new Pagination([
				'defaultPageSize' => 20,
				'totalCount' => $query->count(),
				'pageSizeLimit'=>[5,200],
				]);
		$query->limit($pagination->limit);
		$query->offset($pagination->offset);
		
		$data['data']=$query->asArray()->all();
		$data['pagination']=$pagination;
		
		return $data;
	}//end of getMsgTemplate

	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param   	array|$ids
	 +---------------------------------------------------------------------------------------------
	 * @return		array
	 +---------------------------------------------------------------------------------------------
	 * log			name		date			note
	 * @author		lzhl		2015/7/25		?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function deleteMsgTemplate($ids){
		$result['success']=true;
		$result['message']='';
		
		try{
			MsgTemplate::deleteAll(['in','id',$ids]);
		}catch (Exception $e) {
			$result['success'] = false;
			$result['message'] = $e->getMessage();
		}
		return $result;
	}//end of deleteMsgTemplate
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? ???????????? ?????????????????? ??? ?????? ?????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     $TrackNoList		string/array			?????????   eg : '123' or ['123','124']
	 * 			  $LayOutId			int						???????????? eg:1,2,3
	 * 			  $ReComProdCount	int						?????? ?????????    eg:1,2,3		
	 +---------------------------------------------------------------------------------------------
	 * @return						na
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/7/22				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function setMessageConfig($TrackNoList , $LayOutId =1 , $ReComProdCount=8, $ReComGroup=0){
		$query = Tracking::find();
		$result = $query->andWhere(['track_no'=>$TrackNoList])->all();
		foreach($result as $row){
			//?????????????????????
			$row['addi_info'] = str_ireplace('`', '"', $row['addi_info']);
			$addi_info = json_decode($row['addi_info'],true);
			if (isset($addi_info['layout_id'])) unset($addi_info['layout_id']);
			$addi_info['layout'] = $LayOutId;
			$addi_info['recom_prod_count'] = $ReComProdCount;
			$addi_info['recom_prod_group'] = $ReComGroup;
			
			$row['addi_info'] = json_encode($addi_info);
			if (! $row->save()){
				$message =  [ 'param'=>[$TrackNoList , $LayOutId , $ReComProdCount] , 'message'=>$row->errors];
				$message = json_encode($message);
				//???????????? , ?????????????????????
				\Yii::error(['Tracking',__CLASS__,__FUNCTION__,'Online',$message],"edb\global");
			}
		}
	}//end of setMessageConfig
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? ???????????? ?????????????????? ??? ?????? ????????? BY OMS
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     $orderIdList		string/array			?????????   eg : '123' or ['123','124']
	 * 			  $LayOutId			int						???????????? eg:1,2,3
	 * 			  $ReComProdCount	int						?????? ?????????    eg:1,2,3
	 +---------------------------------------------------------------------------------------------
	 * @return						na
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/7/22				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function setMessageConfigByOms($orderIdList , $LayOutId =1 , $ReComProdCount=8, $ReComGroup=0){
		//step1  ???????????????order???
		$query = OdOrder::find();
		$result = $query->where(['order_source_order_id'=>$orderIdList])->all();
		$orderNoList=[];
		foreach($result as $row){
			$orderNoList[] = $row->order_source_order_id;
			if(empty($row->addi_info))
				$addi_info = json_decode($row->addi_info,true);
			if(empty($addi_info))
				$addi_info = [];
			$addi_info['layout'] = $LayOutId;
			$addi_info['recom_prod_count'] = $ReComProdCount;
			$addi_info['recom_prod_group'] = $ReComGroup;
			
			$row->addi_info = json_encode($addi_info);
			if (! $row->save()){
				$message =  [ 'param'=>[$orderIdList , $LayOutId , $ReComProdCount, $ReComGroup] , 'message'=>$row->errors];
				$message = json_encode($message);
				//???????????? , ?????????????????????
				\Yii::error(['Order',__CLASS__,__FUNCTION__,'Online',$message],"edb\global");
			}
		}
		
		//step2 ???????????????lt_tracking
		$result = Tracking::find()->where(['order_id'=>$orderNoList])->all();
		foreach($result as $row){
			//?????????????????????
			$addi_info = str_ireplace('`', '"', $row->addi_info);
			$addi_info = json_decode($addi_info,true);
			if (isset($addi_info['layout_id'])) unset($addi_info['layout_id']);
			$addi_info['layout'] = $LayOutId;
			$addi_info['recom_prod_count'] = $ReComProdCount;
			$addi_info['recom_prod_group'] = $ReComGroup;
				
			$row->addi_info = json_encode($addi_info);
			if (! $row->save()){
				$message =  [ 'param'=>[$orderIdList , $LayOutId , $ReComProdCount, $ReComGroup] , 'message'=>$row->errors];
				$message = json_encode($message);
				//???????????? , ?????????????????????
				\Yii::error(['Tracking',__CLASS__,__FUNCTION__,'Online',$message],"edb\global");
			}
		}
	}//end of setMessageConfig
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? track no ?????????????????????????????????????????? ??????????????? ????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     $TrackNoList		string/array			?????????   eg : '123' or ['123','124']
	 * 			
	 +---------------------------------------------------------------------------------------------
	 * @return						['matchRoleTracking'=>[0=>['track_no'=>'123',
	 *															'role_name'=>'role name',
	 *															'platform'=>'ebay', // ebay , wish , aliexpress , dhgate ... 
	 *															'order_id'=>'123',
	 *															'nation'=>'??????',
	 *															'template_id'=>1, ],..... ] , 
	 * 								'unMatchRoleTracking'=>[0=>['track_no'=>'123',
	 *															'role_name'=>'role name',
	 *															'platform'=>'ebay', // ebay , wish , aliexpress , dhgate ... 
	 *															'order_id'=>'123',
	 *															'nation'=>'??????', ] , .....]	
	 * 									]
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/7/31				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function matchMessageRole($TracknoList){
		$query = Tracking::find();
		//$trackInfo =  $query->andWhere(['track_no'=>$TracknoList])->asArray()->all();
		$trackInfo =  $query->andWhere(['track_no'=>$TracknoList])->all();
		
		$AccountMapping = self::getAccountMappingIDData();
		$data['matchRoleTracking'] = [];
		$data['unMatchRoleTracking'] = [];
		$order_source_arr = [];//????????????????????????
		$order_seller_arr = [];//??????????????????seller
		$isSameSeller = false;//??????????????????????????????????????????
		foreach($trackInfo as $tracking ){
			if (!empty($AccountMapping[$tracking['platform']][$tracking['seller_id']]))
				$account_id = $AccountMapping[$tracking['platform']][$tracking['seller_id']];
			else{
				$account_id = 0;
			}
			/**/
			
			$tmp_platform = (!empty($tracking['platform']))?$tracking['platform']:'';
			if (empty($tracking['to_nation']) || $tracking['to_nation'] =='--')
				$tracking['to_nation'] = $tracking->getConsignee_country_code();
			$tmp_to_nation = (!empty($tracking['to_nation']) && empty($tmp_to_nation) )?$tracking['to_nation']:'';
			$tmp_to_nation = (!empty($tracking['to_nation']))?$tracking['to_nation']:'';
			
			if(!in_array($tmp_platform, $order_source_arr))
				$order_source_arr[] = $tmp_platform;
			if(!in_array($account_id, $order_seller_arr))
				$order_seller_arr[] = $account_id;
			
			$tmp_status = (!empty($tracking['status']))?$tracking['status']:'';
			$role = MessageHelper::getTopTrackerAuotRule($tmp_platform, $account_id, $tmp_to_nation, $tmp_status);
			//echo $tracking['platform']." =". $account_id." =". $tracking['to_nation']." =". $tracking['status'].'<br>';
			if (!empty($role['name']))
				$roleName = $role['name'];
			else
				$roleName ='';
				
			$roleName = MessageHelper::getTopTrackerAuotRuleName($tracking['platform'], $account_id, $tracking['to_nation'], $tracking['status']);
			//echo $tracking['track_no'].":".$tracking['platform']." =". $account_id." =". $tracking['to_nation']." =". $tracking['status'].'<br>';
			if (!empty($role['template_id'] ))
				$templateId = $role['template_id'] ;
			else
				$templateId = 0;
			if (!empty($roleName)){
				//matched role
				$data['matchRoleTracking'][] = [
				'track_no'=>$tracking['track_no'],
				'role_name'=>$roleName,
				'platform'=>$tracking['platform'],
				'order_id'=>$tracking['order_id'],
				'nation'=>$label = self::autoSetCountriesNameMapping($tracking['to_nation']),
				'template_id'=>$templateId,
				];
			}else{
				//unmatch role
				$data['unMatchRoleTracking'][] = [
				'track_no'=>$tracking['track_no'],
				'role_name'=>'',
				'platform'=>$tracking['platform'],
				'order_id'=>$tracking['order_id'],
				'nation'=>self::autoSetCountriesNameMapping($tracking['to_nation']),
				];
			}
		}
		if(count($order_source_arr)==1 && count($order_seller_arr)==1)
			$isSameSeller = true;
		$data['isSameSeller'] = $isSameSeller;
		
		return $data;
	}//end of matchMessageRole
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param string $date ??????
	 * @param string $formats ???????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @return boolean
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/2/14				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function batchUpdateUnshipParcel(){
		$params = [];
		//???????????? ?????????
		$params = Tracking::getTrackingConditionByClassification ('unshipped_parcel');
		/*
		//?????? ?????? log
		$logTimeMS1=TimeUtil::getCurrentTimestampMS();
		$logMemoryMS1 = (memory_get_usage()/1024/1024);
		*/
		
		//??????90???????????????????????????
		$RecentDate = date('Y-m-d',strtotime('-90 day'));;
		$RecentDateCondition = ['>=','create_time', $RecentDate];
		$UnshipParcelList = Tracking::find()
			->select(['track_no'])
			->andWhere($params)
			->andWhere($RecentDateCondition)
			->asArray()
			->all();
		/*
		//?????? ?????? log
		$logTimeMS2=TimeUtil::getCurrentTimestampMS();
		$logMemoryMS2 = (memory_get_usage()/1024/1024);
		$current_time_cost = $logTimeMS2-$logTimeMS1;
		$current_memory_cost = $logMemoryMS2-$logMemoryMS1;
		$msg = (__FUNCTION__)."   ,t1_2=".($current_time_cost).",memory=".($current_memory_cost)."M ";
		\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Background',$msg],"edb\global");
		*/
		
		
		$track_nos = [];
		//?????? ?????????
		foreach($UnshipParcelList as $row){
			$track_nos[] = $row['track_no'];
			$row = []; //release memory
		}
		self::putIntoTrackQueueBuffer($track_nos , 'B');
		/*
		//?????? ?????? log
		$logTimeMS3=TimeUtil::getCurrentTimestampMS();
		$logMemoryMS3 = (memory_get_usage()/1024/1024);
		$current_time_cost = $logTimeMS3-$logTimeMS2;
		$current_memory_cost = $logMemoryMS3-$logMemoryMS2;
		$msg = (__FUNCTION__)."   ,t2_3=".($current_time_cost).",memory=".($current_memory_cost)."M ";
		\Yii::info(['Tracking',__CLASS__,__FUNCTION__,'Background',$msg],"edb\global");
		*/
	}//end of batchUpdateUnshipParcel

	static public function summaryForThisAccountForLastNdays($app='Tracker',$platform="",$seller_id="",$puid=0){
		$now_date = date('Y-m-d');

		if (empty($seller_id))
			$seller_id = '';
		
		if ($puid == 0)
			$puid = \Yii::$app->subdb->getCurrentPuid();
		
		$days_10_ago = date('Y-m-d',strtotime('-10 days'));

		$command= Yii::$app->db->createCommand(//run_time = -10, ???????????????????????????smt api ??????????????????????????????
		"select * from ut_app_summary_daily   where app='Tracker' and platform='$platform' and puid=$puid and  
				seller_id =:seller_id and thedate >='$days_10_ago'
								 ");
		$command->bindValue(':seller_id', $seller_id, \PDO::PARAM_STR);
		$recent_10_days = $command->queryAll();
		
		$recent_10_days_Sorted = [];
		foreach($recent_10_days as $aDayRec){
			$recent_10_days_Sorted[$aDayRec['thedate']] = $aDayRec;
		}
		
		//???????????????????????????????????????????????????????????????????????????
		for($i=1;  $i<=10 ; $i++){
			$targetDate = date('Y-m-d',strtotime('-'.$i.' days'));
			if (isset($recent_10_days_Sorted[$targetDate]))
				continue;

			$command= Yii::$app->subdb->createCommand(//run_time = -10, ???????????????????????????smt api ??????????????????????????????
				"select count(1) from lt_tracking where platform='$platform' and 
					 seller_id =:seller_id and date(create_time)='$targetDate' ");
			$command->bindValue(':seller_id', $seller_id, \PDO::PARAM_STR);
			$totalCount = $command->queryScalar();
				
			$command= Yii::$app->db->createCommand("insert into ut_app_summary_daily (app,platform,puid,seller_id,thedate,create_count) values
				('Tracker','$platform','$puid',:seller_id,'$targetDate',$totalCount)");
			$command->bindValue(':seller_id', $seller_id, \PDO::PARAM_STR);
			$command->execute();	
		}
	} 
	
	//??????????????????????????????????????? oms???
	public static function pushToOMS($puid, $order_id,$status,$last_event_date){
		$puid0 = \Yii::$app->subdb->getCurrentPuid();
		
		 
		if (empty($last_event_date))
			$last_event_date = "2012-01-01"; //hardcode in case this is empty
		
		Yii::$app->subdb->createCommand( 
		//???????????????????????????status??????????????????status??????????????????????????????????????????????????????
			"update od_order_v2 set logistic_status='$status', logistic_last_event_time='$last_event_date' 
			where order_source_order_id = '$order_id' and ( logistic_status is NULL or 
			not ('$status' in ('untrackable','expired','no_info','suspend') and logistic_status
			not in ('untrackable','expired','no_info','suspend','checking','')	
				) 
			)")
			->execute();
	}
	
	public static function getTrackerTempDataFromRedis($key,$puid1=0){
		//???????????? ?????????puid
		if ($puid1 > 0)
			$puid = $puid1;
		else
			$puid = \Yii::$app->subdb->getCurrentPuid();
		
		$classification = "Tracker_AppTempData";
		//return RedisHelper::RedisGet($classification,"user_$puid".".".$key);
		$TrackerTempData =  RedisHelper::RedisGet($classification,"user_$puid".".".$key);
		
		if(empty($TrackerTempData) && $key=='using_carriers'){
			//??????????????????????????????		lzhl 	2017-02-27
			$using_carriers = array();
			$allCarriers = Yii::$app->subdb->createCommand(//run_time = -10, ???????????????????????????smt api ??????????????????????????????
					"select distinct ship_by from lt_tracking   ")->queryAll();
			foreach ($allCarriers as $aCarrier){
				$using_carriers[ $aCarrier['ship_by']  ] = $aCarrier['ship_by'] ;
			}
			TrackingHelper::setTrackerTempDataToRedis("using_carriers", json_encode($using_carriers),$puid);
			$TrackerTempData = RedisHelper::RedisGet($classification,"user_$puid".".".$key);
		}
		return $TrackerTempData;
	}
	
	/*
	 * ?????? ??????tracker quota ??? reids
	 */
	public static function addTrackerQuotaToRedis($key,$val,$puid1=0){
		//???????????? ?????????puid
		if ($puid1 > 0)
			$puid = $puid1;
		else
			$puid = \Yii::$app->subdb->getCurrentPuid();
		
		$classification = "Tracker_AppTempData";
		return RedisHelper::RedisAdd($classification,"user_$puid".".".$key,$val);
		 
	}//end of function addTrackerQuotaToRedis
	
	public static function setTrackerTempDataToRedis($key,$val,$puid1=0){
		//???????????? ?????????puid
		if ($puid1 > 0)
			$puid = $puid1;
		else
			$puid = \Yii::$app->subdb->getCurrentPuid();
		
		$classification = "Tracker_AppTempData";
		return RedisHelper::RedisSet($classification,"user_$puid".".".$key,$val);
		 
	}
	
	public static function delTrackerTempDataToRedis($key){
		//???????????? ?????????puid
		$puid = \Yii::$app->subdb->getCurrentPuid();
		$classification = "Tracker_AppTempData";
		return RedisHelper::RedisDel($classification,"user_$puid".".".$key);
	}
	
	static public function queueHandlerProcessing1($target_track_no=''){
		return TrackingQueueHelper::queueHandlerProcessing1($target_track_no);
	}
	
	static public function subqHandlerByCarrierNon17Track($sub_id1='' ){
		return TrackingQueueHelper::subqHandlerByCarrierNon17Track($sub_id1  );
	}
	
	static public function subqHandlerByCarrier17Track($sub_id1='' ){
		return TrackingQueueHelper::subqHandlerByCarrier17Track($sub_id1  );
	}
	
	
	public static function getTrackerChartDataByUid($uid,$days){
		$today = date('Y-m-d');
		$daysAgo = date('Y-m-d', strtotime($today)-3600*24*$days);
		//????????????????????????????????????
		$query = "SELECT * FROM `ut_app_summary_daily` WHERE `puid`=$uid and `thedate`>='".$daysAgo."' and `thedate`<'".$today."'";
		$command = Yii::$app->db->createCommand($query);
		$records = $command->queryAll();

		$chart['type'] = 'column';
		$chart['title'] = '???'.$days.'???Tracker????????????';
		$chart['subtitle'] = '';
		$chart['xAxis'] = [];
		$chart['yAxis'] = '????????????';
		$chart['series'] = [];
		
		$series = [];
		for ($i=$days;$i>=0;$i--){
			$total=0;
			$theday = date('Y-m-d', strtotime($today)-3600*24*$i);
			if($i==0){
				$chart['xAxis'][] = '??????';
				$total = self::getTrackerTempDataFromRedis(date('Y-m-d')."_inserted");
				if (empty($total))
					$total = 0;
				//$chart['series'][] = ['name'=>'??????','datqa'=>$total];
				$series[] = $total;
			}
			else{
				$chart['xAxis'][] = date('m-d', strtotime($theday));//??????????????????
				foreach ($records as &$record){
					if($record['thedate']==$theday){
						$total += (int)$record['create_count'];
						unset($record);
					}
				}
				//$chart['series'][] = ['name'=>$theday,'data'=>$total];
				$series[] = $total;
			}
		}
		$chart['series'][]=['name'=>'??????????????????','data'=>$series];
		
		return $chart;
	}
	/**
	 * ??????tracker dash-board??????
	 * @param unknown	$uid
	 * @param number 	$every_time_shows	?????????????????????
	 */
	public static function getAdvertDataByUid($uid,$every_time_shows=2){
		$advertData = [];
		$last_advert_id = RedisHelper::RedisGet('Tracker_DashBoard',"user_$uid".".last_advert");
		if(empty($last_advert_id))
			$last_advert_id=0;
		$new_last_advert_id = $last_advert_id;
		if(!empty($last_advert_id)){
			$query = "SELECT * FROM `od_dash_advert` WHERE (`app`='Tracker') and `id`>".(int)$last_advert_id."  ORDER BY `id` ASC limit 0,$every_time_shows ";
			$command = Yii::$app->db->createCommand($query);
			$advert_records = $command->queryAll();
			foreach ($advert_records as $advert){
				$advertData[$advert['id']] = $advert;
				$new_last_advert_id = $advert['id'];
			}
			if(count($advertData)<$every_time_shows){
				$reLimit = $every_time_shows - count($advertData);
				$query_r = "SELECT * FROM `od_dash_advert` WHERE `app`='Tracker' ORDER BY `id` ASC limit 0,$reLimit ";
				$command = Yii::$app->db->createCommand($query_r);
				$advert_records_r = $command->queryAll();
				foreach ($advert_records_r as $advert_r){
					if(in_array($advert_r['id'],array_keys($advertData)))
						continue;
					$advertData[$advert_r['id']] = $advert_r;
					$new_last_advert_id = $advert_r['id'];
				}
			}
		}else{
			$query = "SELECT * FROM `od_dash_advert` WHERE `app`='Tracker'  ORDER BY `id` ASC limit 0,$every_time_shows ";
			$command = Yii::$app->db->createCommand($query);
			$advert_records = $command->queryAll();
			foreach ($advert_records as $advert){
				$advertData[$advert['id']] = $advert;
				$new_last_advert_id = $advert['id'];
			}
		}
	
		$set_advert_redis = RedisHelper::RedisSet('Tracker_DashBoard',"user_$uid".".last_advert",$new_last_advert_id);
		return $advertData;
	}
	
	public static function saveCase($uid,$data){
		$result['success']=true;
		$result['message']='';
		$track_no = empty($data['track_no'])?'':$data['track_no'];
		$order_id = empty($data['order_id'])?'':$data['order_id'];
		if(empty($track_no)){
			$result['success']=false;
			$result['message']='E001???????????????????????????';
		}
			
		$carrier_type = !isset($data['carrier_type'])?'':$data['carrier_type'];
		$customer_url = empty($data['customer_url'])?'':$data['customer_url'];
		if($carrier_type=='' || empty($customer_url)){
			$result['success']=false;
			$result['message']='E002?????????????????????????????????????????????';
			return $result;
		}
		$desc = trim($data['desc']);
		try{
			$query = "SELECT * FROM `tracker_cases` WHERE `uid`=$uid and `track_no`=:track_no ";
			$command = Yii::$app->db->createCommand($query);
			$command->bindValue(':track_no', $track_no, \PDO::PARAM_STR);
			$record = $command->queryOne();
			
			if(empty($data['act']) || $data['act']=='add'){
				if(!empty($record)){
					$result['success']=false;
					$result['message']='??????:'.$track_no.'???????????????????????????????????????E003 ';
					return $result;
				}
				$query = "INSERT INTO `tracker_cases`
						(`uid`, `track_no`, `order_id`, `carrier_type`, `customer_url`, `desc`, `status`,  `create_time`, `update_time`) VALUES 
						($uid,:track_no,:order_id,:carrier_type,:customer_url,:desc,'P','".date("Y-m-d H:i:s")."','".date("Y-m-d H:i:s")."')";
				$command = Yii::$app->db->createCommand($query);
				$command->bindValue(':track_no', $track_no, \PDO::PARAM_STR);
				$command->bindValue(':order_id', $order_id, \PDO::PARAM_STR);
				$command->bindValue(':carrier_type', $carrier_type, \PDO::PARAM_STR);
				$command->bindValue(':customer_url', $customer_url, \PDO::PARAM_STR);
				$command->bindValue(':desc', $desc, \PDO::PARAM_STR);
				$insert = $command->execute();
				if(!empty($insert)){
					return $result;
				}else{
					$result['success']=false;
					$result['message']='????????????:?????????????????? ???E004';
					return $result;
				}
			}else{
				if(!empty($record)){
					$query = "UPDATE `tracker_cases` SET `carrier_type`=:carrier_type,`customer_url`=:customer_url,`desc`=:desc,`status`='P',`update_time`='".date("Y-m-d H:i:s")."' 
						WHERE `uid`=$uid and `track_no`=:track_no";
					$command = Yii::$app->db->createCommand($query);
					$command->bindValue(':track_no', $track_no, \PDO::PARAM_STR);
					$command->bindValue(':carrier_type', $carrier_type, \PDO::PARAM_STR);
					$command->bindValue(':customer_url', $customer_url, \PDO::PARAM_STR);
					$command->bindValue(':desc', $desc, \PDO::PARAM_STR);
					$update = $command->execute();
					if(!empty($update)){
						return $result;
					}else{
						$result['success']=false;
						$result['message']='????????????:?????????????????? ???E005';
						return $result;
					}
				}
			}
		}catch (Exception $e) {
			$result['success']=false;
			$result['message']= $e->getMessage();
			return $result;
		}
	}
	
	public static function ignoreTrackerNo($track_id){
		$rtn['message']="";
		$rtn['success'] = true;
		if(empty($track_id)){
			$rtn['message']="???????????????????????????????????????";
			$rtn['success'] = false;
			return $rtn;
		}
		
		$uid = \Yii::$app->user->id;
		
		$journal_id = SysLogHelper::InvokeJrn_Create("Tracker",__CLASS__, __FUNCTION__ , array($track_id));
		
		$canIgnoreStatus = Tracking::getCanIgnoreStatus('EN');
		$tracks = Tracking::find()->where(['id'=>$track_id,'status'=>$canIgnoreStatus])->all();
		foreach ($tracks as $aTrack){
			$aTrack->ignored_time = date("Y-m-d H:i:s");
			$aTrack->status = 'ignored';
			
			//????????????????????????log
			$addi_info = $aTrack->addi_info;
			$addi_info = json_decode($addi_info,true);
			if(empty($addi_info)) $addi_info = [];
			$addi_info['manual_status_move_logs'][] = [
			'capture_user_name'=>$userName,
			'old_status'=>$old_status,
			'new_status'=>'ignored',
			'time'=>$now_str,
			];
			$aTrack->addi_info = json_encode($addi_info);
			
			//??????lt_tracking???status
			if($aTrack->save()){
				//??????status????????????od_order_v2???5??????????????????
				$order = OdOrder::find()->where(['order_source_order_id'=>$aTrack->order_id])->asArray()->one();
				if(!empty($order)){
					//??????od_order_v2???5??????????????????
					if($order['weird_status']=='tuol')
						$weird_status='';
					else 
						$weird_status = $order['weird_status'];
					OdOrder::updateAll(['weird_status'=>$weird_status,'logistic_status'=>'ignored'],['order_source_order_id'=>$aTrack->order_id]);
					//??????od_order_shipped_v2??????????????????
					OrderApiHelper::setOrderShippedInfo($order['order_id'],$aTrack->track_no, ['sync_to_tracker'=>'Y','tracker_status'=>'ignored']);
				}
				
				//??????????????????????????????????????????????????????
				$query = "UPDATE `tracker_cases` SET `status`='C',`update_time`='".date("Y-m-d H:i:s")."',`comment`='???????????????????????????'
				WHERE `uid`=$uid and `track_no`=:track_no";
				$command = Yii::$app->db->createCommand($query);
				$command->bindValue(':track_no', $aTrack->track_no, \PDO::PARAM_STR);
				$update = $command->execute();
			}else{
				$rtn['message'] .= $aTrack->track_no."???????????????E001???";
				$rtn['success'] = false;
				continue;
			}
		}
		SysLogHelper::InvokeJrn_UpdateResult($journal_id, $rtn);
		return $rtn;
	}
	
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????tracker ??????????????? ????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     na				??????$_GET ????????????
	 +---------------------------------------------------------------------------------------------
	 * @return						[menu array , active string]
	 *
	 * @invoking					TrackingHelper::getMenuParams();
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2016/04/07				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function getMenuParams(){
		$menu_platform = (!empty($_GET['platform'])?strtolower($_GET['platform']):"");
		$menu_parcel_classification = (!empty($_GET['parcel_classification'])?strtolower($_GET['parcel_classification']):"");
		
		
		//var_dump($menu_parcel_classification);
		$seller_statistics_span='';
		$menu_label_count = TrackingHelper::getMenuStatisticData();
		$get_sellerid='';
		if(!empty($_GET['sellerid'])){
			$get_sellerid = $_GET['sellerid'];
			$menu_label_count = $menu_label_count[$_GET['sellerid']];
			$seller_statistics_span = "<span class='no-qtip-icon' title='".$_GET['sellerid']."' qtipkey='cs_filtered_account'>(".(strlen($_GET['sellerid'])>13?substr($_GET['sellerid'],0,9)."..":$_GET['sellerid']). ")</span>";
		}
		else
			$menu_label_count = $menu_label_count['all'];
		
		
		$active = '';
		$d=strtotime("-7 days");
		$startdate = date("Y-m-d", $d);
		$RequestGoodEvaluationLink = Url::to(['/tracking/tracking/list-tracking?platform=all&pos=RGE&parcel_classification=received_parcel&select_parcel_classification=received_parcel&is_send=N&startdate='.$startdate]);
		$RequestPendingFetchLink = Url::to(['/tracking/tracking/list-tracking?platform=all&pos=RPF&parcel_classification=arrived_pending_fetch_parcel&select_parcel_classification=arrived_pending_fetch_parcel&is_send=N&startdate='.$startdate]);
		$DeliveryFailedFetchLink = Url::to(['/tracking/tracking/list-tracking?platform=all&pos=DF&parcel_classification=delivery_failed_parcel&select_parcel_classification=delivery_failed_parcel&is_send=N&startdate='.$startdate]);
		$RequestShippingLink = Url::to(['/tracking/tracking/list-tracking?platform=all&pos=RSHP&parcel_classification=shipping_parcel&select_parcel_classification=shipping_parcel&is_send=N&startdate='.$startdate]);
		$RequestRejectedLink = Url::to(['/tracking/tracking/list-tracking?platform=all&pos=RRJ&parcel_classification=rejected_parcel&select_parcel_classification=rejected_parcel&is_send=N&startdate=' . $startdate
				] );
		 
		// ????????????
		list($bindingLink,$label) = AppApiHelper::getPlatformMenuData();
		
		
		$normalParcelItem = [];
		if ($menu_parcel_classification == 'shipping_parcel' || (!empty($menu_label_count['shipping_parcel'])) ){
			$normalParcelItem[TranslateHelper::t('????????????')] = [
			'url'=>Url::to(['/tracking/tracking/list-tracking?parcel_classification=shipping_parcel&sellerid='.$get_sellerid]),
			'tabbar'=>$menu_label_count['shipping_parcel'],
			'qtipkey'=>'@tracker_shipping',
			];
		}
		
		if ($menu_parcel_classification == 'shipping_parcel'&& empty($menu_platform)){
			$active = TranslateHelper::t('????????????');
		}
		
		if ($menu_parcel_classification == 'no_info_parcel' || (!empty($menu_label_count['no_info_parcel'])) ){
			$normalParcelItem[TranslateHelper::t('????????????')] = [
			'url'=>Url::to(['/tracking/tracking/list-tracking?parcel_classification=no_info_parcel&sellerid='.$get_sellerid]),
			'tabbar'=>$menu_label_count['no_info_parcel'],
			'qtipkey'=>'@tracker_no_info',
			];
		}
		if ($menu_parcel_classification == 'no_info_parcel'&& empty($menu_platform)){
			$active = TranslateHelper::t('????????????');
		}
		
		if ($menu_parcel_classification == 'suspend_parcel' || (!empty($menu_label_count['suspend_parcel'])) ){
			$normalParcelItem[TranslateHelper::t('????????????')]=[
			'url'=>Url::to(['/tracking/tracking/list-tracking?parcel_classification=suspend_parcel&sellerid='.$get_sellerid]),
			'tabbar'=>$menu_label_count['suspend_parcel'],
			'qtipkey'=>'@tracker_suspend_parcel',
			];
		}
		if ($menu_parcel_classification == 'suspend_parcel'&& empty($menu_platform)){
			$active = TranslateHelper::t('????????????');
		}
		
		$normalParcelItem[TranslateHelper::t('?????????')]=[
		'url'=>Url::to(['/tracking/tracking/list-tracking?parcel_classification=completed_parcel&sellerid='.$get_sellerid]),
		'tabbar'=>$menu_label_count['completed_parcel'],
		'qtipkey'=>'@tracker_complete_parcel',
		];
		
		if ($menu_parcel_classification == 'completed_parcel'&& empty($menu_platform)){
			$active = TranslateHelper::t('?????????');
		}
		
		
		$exceptionParcelItem = [];
		if ($menu_parcel_classification == 'rejected_parcel' || (!empty($menu_label_count['rejected_parcel'])) ){
			$exceptionParcelItem[TranslateHelper::t('???????????? ')] = [
			'url'=>Url::to(['/tracking/tracking/list-tracking?parcel_classification=rejected_parcel&sellerid='.$get_sellerid]),
			'tabbar'=>$menu_label_count['rejected_parcel'],
			'qtipkey'=>'@tracker_rejected',
			];
		}
		if ($menu_parcel_classification == 'rejected_parcel'&& empty($menu_platform)){
			$active = TranslateHelper::t ( '????????????' );
		}
		
		if ($menu_parcel_classification == 'ship_over_time_parcel' || (!empty($menu_label_count['ship_over_time_parcel'])) ){
			$exceptionParcelItem[TranslateHelper::t('????????????')] = [
			'url'=>Url::to(['/tracking/tracking/list-tracking?parcel_classification=ship_over_time_parcel&sellerid='.$get_sellerid]),
			'tabbar'=>$menu_label_count['ship_over_time_parcel'],
			'qtipkey'=>'@tracker_ship_over_time',
			];
		}
		
		if ($menu_parcel_classification == 'ship_over_time_parcel'&& empty($menu_platform)){
			$active = TranslateHelper::t ( '????????????' );
		}
		
		if ($menu_parcel_classification == 'arrived_pending_fetch_parcel' || (!empty($menu_label_count['arrived_pending_fetch_parcel'])) ){
			$exceptionParcelItem[TranslateHelper::t('????????????')] = [
			'url'=>Url::to(['/tracking/tracking/list-tracking?parcel_classification=arrived_pending_fetch_parcel&sellerid='.$get_sellerid]),
			'tabbar'=>$menu_label_count['arrived_pending_fetch_parcel'],
			'qtipkey'=>'@tracker_arrived_pending_fetch',
			];
		}
		
		if ($menu_parcel_classification == 'arrived_pending_fetch_parcel'&& empty($menu_platform)){
			$active = TranslateHelper::t ( '????????????' );
		}
		
		if ($menu_parcel_classification == 'delivery_failed_parcel' || (!empty($menu_label_count['delivery_failed_parcel'])) ){
			$exceptionParcelItem[TranslateHelper::t('????????????')] = [
			'url'=>Url::to(['/tracking/tracking/list-tracking?parcel_classification=delivery_failed_parcel&sellerid='.$get_sellerid]),
			'tabbar'=>$menu_label_count['delivery_failed_parcel'],
			'qtipkey'=>'@tracker_delivery_failed',
			];
		}
		
		if ($menu_parcel_classification == 'delivery_failed_parcel'&& empty($menu_platform)){
			$active = TranslateHelper::t ( '????????????' );
		}
		
		if ($menu_parcel_classification == 'unshipped_parcel' || (!empty($menu_label_count['unshipped_parcel'])) ){
			$exceptionParcelItem[TranslateHelper::t('????????????')] = [
			'url'=>Url::to(['/tracking/tracking/list-tracking?parcel_classification=unshipped_parcel&sellerid='.$get_sellerid]),
			'tabbar'=>$menu_label_count['unshipped_parcel'],
			'qtipkey'=>'@tracker_unshipped',
			];
		}
		if ($menu_parcel_classification == 'ignored_parcel' || (!empty($menu_label_count['ignored_parcel'])) ){
			$exceptionParcelItem[TranslateHelper::t('?????????')] = [
			'url'=>Url::to(['/tracking/tracking/list-tracking?parcel_classification=ignored_parcel&sellerid='.$get_sellerid]),
			'tabbar'=>$menu_label_count['ignored_parcel'],
			];
		}
		//????????????
		if ($menu_parcel_classification == 'quota_insufficient' || !empty($menu_label_count['quota_insufficient']) ){
			$exceptionParcelItem[TranslateHelper::t('????????????')] = [
			'url'=>Url::to(['/tracking/tracking/list-tracking?parcel_classification=quota_insufficient&sellerid='.$get_sellerid]),
			'tabbar'=>$menu_label_count['quota_insufficient'],
			'qtipkey'=>'tracker_quota_insufficient',
			];
		}
		
		$customerRemandItem = [
			TranslateHelper::t('????????????')=>[
				'url'=>$RequestShippingLink,
				'tabbar'=>$menu_label_count['shipping_message'],
				'qtipkey'=>'@tracker_request_shipping',
			],
			TranslateHelper::t('??????????????????')=>[
				'url'=>$RequestPendingFetchLink,
				'tabbar'=>$menu_label_count['arrived_pending_message'],
				'qtipkey'=>'@tracker_request_pending_fetch',
			],
			TranslateHelper::t('??????????????????')=>[
				'url'=>$DeliveryFailedFetchLink,
				'tabbar'=>$menu_label_count['delivery_failed_message'],
				'qtipkey'=>'@tracker_delivery_failed',
			],
			TranslateHelper::t('??????????????????')=>[
				'url'=>$RequestRejectedLink,
				'tabbar'=>$menu_label_count['rejected_message'],
				'qtipkey'=>'@tracker_request_rejected',
			],
			TranslateHelper::t('?????????????????????')=>[
				'url'=>$RequestGoodEvaluationLink,
				'tabbar'=>$menu_label_count['received_message'],
				'qtipkey'=>'@tracker_request_good_evaluation',
			],
		];
		
		if (!empty($_GET['pos']) && $menu_parcel_classification == 'shipping_parcel' ){
			$active = TranslateHelper::t ( '????????????' );
		}
		if (!empty($_GET['pos']) && $menu_parcel_classification == 'arrived_pending_fetch_parcel' ){
		$active = TranslateHelper::t ( '??????????????????' );
		}
		if (!empty($_GET['pos']) && $menu_parcel_classification == 'delivery_failed_parcel' ){
			$active = TranslateHelper::t ( '??????????????????' );
		}
		if (!empty($_GET['pos']) && $menu_parcel_classification == 'rejected_parcel' ){
		$active = TranslateHelper::t ( '??????????????????' );
		}
		if (!empty($_GET['pos']) && $menu_parcel_classification == 'completed_parcel' ){
		$active = TranslateHelper::t ( '?????????????????????' );
		}
		
		$menu = [
			TranslateHelper::t ( '????????????' )=>[
			'icon'=>'icon-sousuo1',
			'url'=>Url::to(['/tracking/tracking/index']),
				
			],
			TranslateHelper::t ( '????????????' )=>[
				'icon'=>'icon-liebiao1',
				'url'=>Url::to(['/tracking/tracking/list-tracking?parcel_classification=all_parcel']),
				'qtipkey'=>'@tracker_cx_all',
				'items'=>[
						TranslateHelper::t('????????????')=>[
							'items'=>$normalParcelItem,
							'url'=>Url::to(['/tracking/tracking/list-tracking?parcel_classification=normal_parcel&sellerid='.$get_sellerid]),
							],
						TranslateHelper::t('????????????')=>[
								'items'=>$exceptionParcelItem,
							],
						],
			],
			TranslateHelper::t('??????????????????')=>[
				'icon'=>'icon-fa-mail',
				'items'=>$customerRemandItem,
			],
			TranslateHelper::t('????????????')=>[
				'icon'=>'icon-iconfontshujutongji',
				'items'=>[
					TranslateHelper::t('??????????????????')=>[
						'url'=>Url::to(['/tracking/tracking/delivery_statistical_analysis']),
					],
					TranslateHelper::t('??????????????????')=>[
						'url'=>Url::to(['/tracking/tracking-recommend-product/product-list']),
					],
				],
			],
			TranslateHelper::t('??????')=>[
				'icon'=>'icon-shezhi',
				'items'=>[
					TranslateHelper::t('????????????')=>[
						'url'=>$bindingLink,
						'target'=>'_blank',
						'qtipkey'=>'@tracker_setting_platform_binding',
					],
					TranslateHelper::t('??????????????????')=>[
						'url'=>Url::to(['/tracking/tracking/mail_template_setting']),
					],
				    TranslateHelper::t('????????????????????????')=>[
				        'url'=>Url::to(['/tracking/tracking-recommend-product/custom-product-list']),
				    ],
				    TranslateHelper::t('???????????????????????????')=>[
				        'url'=>Url::to(['/tracking/tracking-recommend-product/group-list']),
				    ],
				    TranslateHelper::t('???????????????????????????')=>[
				    	'url'=>Url::to(['/tracking/tracking/get-od-trackno-days-set']),
		    		],
				],
			],
		
		];
		
		if (($menu_parcel_classification == 'all_parcel' && empty($menu_platform) )){
			$active = TranslateHelper::t ( '????????????' );
		}
		if ($menu_parcel_classification == 'normal_parcel'&& empty($menu_platform)){
			$active = TranslateHelper::t ( '????????????' );
		}
		if ($menu_parcel_classification == 'exception_parcel'&& empty($menu_platform)){
			$active = TranslateHelper::t ( '????????????' );
		}
			
		if ($menu_parcel_classification == 'all_parcel' && empty($menu_platform) ){
			$active = TranslateHelper::t ( '????????????' );
		}
		if (yii::$app->controller->action->id == 'delivery_statistical_analysis'){
			$active = TranslateHelper::t ( '??????????????????' );
		}
		if (yii::$app->controller->action->id == 'product-list'){
			$active = TranslateHelper::t ( '??????????????????' );
		}
		if (yii::$app->controller->action->id == 'platform_account_binding'){
			$active = TranslateHelper::t ( '????????????' );
		}
		if (yii::$app->controller->action->id == 'mail_template_setting'){
			$active = TranslateHelper::t ( '??????????????????' );
		}
			
		return [$menu , $active];
		
	}//end of getMenuParams
	
	/**
	 *??????????????????????????????????????????????????????????????????	
	 **/
	public static function getAutoIgnoreToCheckShipType(){
		global $CACHE;
		if(isset($CACHE['IgnoreToCheck_ShipType']))
			return $CACHE['IgnoreToCheck_ShipType'];
		
		$config = ConfigHelper::getConfig('IgnoreToCheck_ShipType','NO_CACHE');
		if(!empty($config))
			$config = json_decode($config,true);
		else
			$config = [];
		
		return $config;
	}//end of getAutoIgnoreToCheckShipType
	
	/*
	 * ?????????????????????????????????????????????????????? mapping?????????redis -- set
	 * @param	array	$mapping	like:['4px express'=>'999000002','NG-JG-Seko-China'=>'0',]
	 * @param	int		$puid
	 * @author	lzhl	2017/01		?????????
	 */
	public static function setUserShipByAndCarrierTypeMappingToRedis($mapping,$puid=0){
		//???????????? ?????????puid
		if (empty($puid))
			$puid = \Yii::$app->subdb->getCurrentPuid();
		
		$classification = "Tracker_AppTempData";
		$key = "ShipByMapping";
		return RedisHelper::RedisSet($classification,"user_$puid".".".$key,json_encode($mapping));
	}
	
	/*
	 * ?????????????????????????????????????????????????????? mapping?????????redis -- add
	 * @param	array	$mapping	like:['4px express'=>'999000002','NG-JG-Seko-China'=>'0',]
	 * @param	int		$puid
	 * @author	lzhl	2017/01		?????????
	 */
	public static function addUserShipByAndCarrierTypeMappingToRedis($mapping,$puid=0){
		if (empty($puid))
			$puid = \Yii::$app->subdb->getCurrentPuid();
		
		$classification = "Tracker_AppTempData";
		$key = "ShipByMapping";
		$oldRedisData = self::getUserShipByAndCarrierTypeMappingFromRedis($puid);
		$newRedisData = [];
		
		$is_changed = false;
	 
		if(!empty($oldRedisData)){	 
			$newRedisData = $oldRedisData; 
		}
		foreach ($mapping as $ship_by=>$carrier_type){
			$ship_by= strtolower(trim($ship_by));
			if (empty($ship_by))
				continue;
			
			if (!isset($newRedisData[$ship_by]) or $newRedisData[$ship_by]<>$carrier_type)
				$is_changed = true;
			
			$newRedisData[$ship_by] = $carrier_type;
		}
		
		if(!empty($newRedisData) and $is_changed)
			return RedisHelper::RedisSet($classification,"user_$puid".".".$key,json_encode($newRedisData));
		else 
			return 0;
	}
	
	/*
	 * ???redis?????? ?????????????????????????????????????????????????????? mapping
	 * @author	lzhl	2017/01		????????? 
	 */
	public static function getUserShipByAndCarrierTypeMappingFromRedis($puid=0){
		if (empty($puid))
			$puid = \Yii::$app->subdb->getCurrentPuid();
		
		$classification = "Tracker_AppTempData";
		$key = "ShipByMapping";
		$redisData = RedisHelper::RedisGet($classification,"user_$puid".".".$key);
		
		if(!empty($redisData))
			$aa = json_decode($redisData,true);
		else
			$aa = [];
		
		return $aa;
	}
	
	/*
	 * ???redis??????  ?????????????????????????????????????????????????????? mapping
	 * @author	lzhl	2017/01		?????????
	 */
	public static function delUserShipByAndCarrierTypeMappingFromRedis(){
		if (empty($puid))
			$puid = \Yii::$app->subdb->getCurrentPuid();
		
		$classification = "Tracker_AppTempData";
		$key = "ShipByMapping";
		return RedisHelper::RedisDel($classification,"user_$puid".".".$key);
	}
	
	/*
	 * ?????????????????????????????????????????? ?????? mapping?????????redis -- add
	 * @param	array	$mapping
	 *          $free_text_name =>$carrier_type, ...	
	 *      like:['4px express'=>'999000002','NG-JG-Seko-China'=>'0','DHL'=>'10003',...]
	 * @return	int		RedisSet?????????or 0
	 * @author	lzhl	2017/01		?????????
	 */
	public static function addGlobalShipByAndCarrierTypeMappingToRedis($mapping){
		//global $CACHE;
		$classification = "Tracker_AppTempData";
		$key = "GlobalShipByMapping";
		$oldRedisData = self::getGlobalShipByAndCarrierTypeMappingFromRedis();
		$newRedisData = [];
		if(!empty($oldRedisData)){
			$newRedisData = $oldRedisData;
		}
		$is_changed = false;
		foreach ($mapping as $ship_by=>$carrier_type){
			//??????????????????free text DHL dhl ???????????????????????????????????????
			$ship_by = trim(strtolower($ship_by));
			if (empty($ship_by))
				continue;
			
			if (!isset($newRedisData[$ship_by]) or $newRedisData[$ship_by]<>$carrier_type)
				$is_changed = true;
			
			$newRedisData[$ship_by] = $carrier_type;
		}
	 
		$CACHE['GlobalShipByMapping']['MappingData'] = $newRedisData;
		
		if(!empty($newRedisData) and $is_changed)
			return RedisHelper::RedisSet($classification,$key,json_encode($newRedisData));
		else
			return 0;
	}
	
	/*
	 * ???redis?????? ?????? ?????????????????????????????????????????? mapping
	 * @return	array	$CACHE['GlobalShipByMapping']['MappingData']
	 * @author	lzhl	2017/01		?????????
	 */
	public static function getGlobalShipByAndCarrierTypeMappingFromRedis(){
		//????????????????????????????????????????????????  global $CACHE;
		//??????????????????????????????????????????1?????????????????????????????????
		/*
		if( !empty($CACHE['GlobalShipByMapping']['CacheTime']) && $CACHE['GlobalShipByMapping']['CacheTime']<( time()-60 ) && 
			isset($CACHE['GlobalShipByMapping']['MappingData']) 
		){
			//echo "<br><br> CACHE['GlobalShipByMapping']['MappingData'] isset";
			return $CACHE['GlobalShipByMapping']['MappingData'];
		}
		*/
		//????????????????????????redis??????redis??????????????????
		$classification = "Tracker_AppTempData";
		$key = "GlobalShipByMapping";
		$redisData = RedisHelper::RedisGet($classification,$key);
		
		if(!empty($redisData))
			$CACHE['GlobalShipByMapping']['MappingData'] = json_decode($redisData,true);
		else 
			$CACHE['GlobalShipByMapping']['MappingData'] = [];
		
		//echo "<br><br> CACHE['GlobalShipByMapping']['MappingData'] create";
		$CACHE['GlobalShipByMapping']['CacheTime'] = time();
		//var_dump($CACHE['GlobalShipByMapping']);
		return $CACHE['GlobalShipByMapping']['MappingData'];
	}
	
	/*
	 * ??????ship by ???free text????????????mapping?????????????????????????????????????????????
	 * */
	public static function getGlobalShipByAndCarrierTypeMappingFromRedisForShipBy($ship_by){
		$allShipBy = self::getGlobalShipByAndCarrierTypeMappingFromRedis();
		//??????????????????free text DHL dhl ???????????????????????????????????????
		$ship_by = trim(strtolower($ship_by));
		$mappingResult = "";	
		if (isset($allShipBy[$ship_by])){
			$mappingResult = $allShipBy[$ship_by];
		}
		return $mappingResult;
	}

	public static function getUserShipByAndCarrierTypeMappingFromRedisForShipBy($ship_by){
		global $CACHE;
		$allShipBy = self::getUserShipByAndCarrierTypeMappingFromRedis($CACHE['puid']);
		//??????????????????free text DHL dhl ???????????????????????????????????????
		$ship_by = trim(strtolower($ship_by));
		$mappingResult = "";
		if (isset($allShipBy[$ship_by])){
			$mappingResult = $allShipBy[$ship_by];
		}
		return $mappingResult;
	}
	
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? ????????????????????? tracker ???????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param $uid 							???????????? ???id
	 +---------------------------------------------------------------------------------------------
	 * @return	int				tracker quota
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2017/03/27				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	public static function getTrackerQuota($uid = ''){
	 
		$account = PlatformAccountApi::getPlatformInfoInRedis($uid);
		$isbind = false;
		$accountList = json_decode($account,true);
		if (!empty($accountList)){
			foreach($accountList as $pf=>$v){
				//??????????????????????????? ??????
				if ($pf == 'customized') continue;
				if ($v){
					$isbind = true;
					break;
				}
			}
		}
		
		if ($isbind){
			return self::$tracker_import_limit;
		}else{
			return self::$tracker_guest_import_limit;
		}
 
		
	}//end of getTrackerQuota
	
	/**
	 * ?????????????????????????????????tracker?????????????????????????????????????????????
	 * @param	string	$platform
	 * @param	string	$selleruser
	 * @param	int		$puid
	 * @return	int		//how many days
	 * @author	lzhl	2017-09-14
	 */
	public static function getPlatformGetHowLongAgoOrderTrackNo($platform,$puid=0){
		if(empty($puid))
			$puid = \Yii::$app->user->identity->getParentUid();
		$platform = strtolower($platform);
		$key = 'PlatformGetOrderTrackNoDays';
		$setting = RedisHelper::RedisGet('Tracker_AppTempData', 'uid_'.$puid.'_'.$key);
		if(!empty($setting))
			$setting = json_decode($setting,true);
		else
			$setting = [];
		
		if(!isset($setting[$platform]))
			return 7;
		else
			$days = (int)$setting[$platform];
		return $days;
	}
	
	/**
	 * ?????????????????????????????????tracker?????????????????????????????????????????????
	 * @param	string	$platform
	 * @param	string	$selleruser
	 * @param	int		$days
	 * @param	int		$puid
	 * @return	boolean	redis set result
	 * @author	lzhl	2017-09-14
	 */
	public static function setPlatformGetHowLongAgoOrderTrackNo($platform,$days=7,$puid=0){
		if(empty($puid))
			$puid = \Yii::$app->user->identity->getParentUid();
		$key = 'PlatformGetOrderTrackNoDays';
		$platform = strtolower($platform);
		
		$setting = RedisHelper::RedisGet('Tracker_AppTempData', 'uid_'.$puid.'_'.$key);
		if(!empty($setting))
			$setting = json_decode($setting,true);
		else
			$setting = [];
		
		$setting[$platform] = $days;
		$rtn = RedisHelper::RedisSet('Tracker_AppTempData', 'uid_'.$puid.'_'.$key,json_encode($setting));
		if($rtn==-1)
			return false;
		return true;
	}
	
	/**
	 * ??????????????????????????????????????????
	 * @param	int		$puid
	 * @return	mixed
	 * @author	lzhl	2017-09-14
	 */
	public static function getUserIgnoredCheckCarriers($puid=0){
		if(empty($puid))
			$puid = \Yii::$app->user->identity->getParentUid();
		$key = 'userIgnoredCheckCarriers';
		$rtn = RedisHelper::RedisGet('Tracker_AppTempData', 'uid_'.$puid.'_'.$key);
		if($rtn==-1)
			return ['success'=>false,'message'=>'??????????????????','data'=>''];
		$data = json_decode($rtn,true);
		if(empty($data)) $data = [];
			return ['success'=>true,'message'=>'','data'=>$data];
	}
	
	/**
	 * ?????????????????????????????????
	 * @param	int		$puid
	 * @param	array	$carriers
	 * @return	mixed
	 * @author	lzhl	2017-09-14
	 */
	public static function setUserIgnoredCheckCarriers($puid=0,$carriers=[]){
		if(empty($puid))
			$puid = \Yii::$app->user->identity->getParentUid();
		$key = 'userIgnoredCheckCarriers';
		$rtn = RedisHelper::RedisSet('Tracker_AppTempData', 'uid_'.$puid.'_'.$key,json_encode($carriers));
		if($rtn==-1)
			return ['success'=>false,'message'=>'????????????'];
		
		try{
			foreach ($carriers as $carrier){
				Tracking::updateAll(['status'=>'ignored']," ship_by=:ship_by and state!='complete' and state!='deleted' ",[':ship_by'=>$carrier]);
			}
		}catch(\Exception $e) {
			$result = ['success'=>false , 'message'=> 'update db failed'.$e->getMessage()];
		}
		return ['success'=>true,'message'=>''];
	}


	public static function getTrackerUsedQuota($puid1){
		global $CACHE;
		if (empty($CACHE['TrackerSuffix'.$puid1])){
 
			$VipLevel = 'v0';
			if ($VipLevel == 'v0'){
				$suffix = date('Ymd');
			}else{
				$suffix = 'vip';
			}
			
			$CACHE['TrackerSuffix'.$puid1] = $suffix;
		}
		$suffix = $CACHE['TrackerSuffix'.$puid1];
		//$limt_count =  ConfigHelper::getConfig("Tracking/trackerImportLimit_".$suffix , 'NO_CACHE');
		$limt_count =  TrackingHelper::getTrackerTempDataFromRedis("trackerImportLimit_".$suffix );
		if (empty($limt_count)) $limt_count=0;
		
		return $limt_count;
	}	

	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ?????? ???????????? ????????????
	 * @param string $track_no
	 * @param string $lang
	 * @return
	 * @author		lzhl		2017/10/15		?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	static public function translateTrackingEvent($track_no,$to_lang){
		$all_events_str = [];
	
		$model = Tracking::find()->andWhere(["track_no"=>$track_no])->one();
	
		//????????? ??????
		if (empty($model)) 
			return ['success'=>false,'message'=>'???????????????????????????!','eventHtml'=>''];
		
		//?????? ????????????
		$tmp_rt = self::getTranslatedEvents($track_no, 'auto', $to_lang,true);
		
		if(!$tmp_rt['success']){
			return ['success'=>false,'message'=>@$tmp_rt['message'],'eventHtml'=>''];
		}
		
		if (!empty($tmp_rt['allEvents']))
			$all_events = $tmp_rt['allEvents'];
		else
			$all_events = [];
	
		$all_events_str = "";
		//??????????????????
		if (is_array($all_events)){
			foreach($all_events as $anEvent){
				$anEvent['where'] = base64_decode($anEvent['where']);
// 				$anEvent['what'] = base64_decode($anEvent['what']);
				//??????????????????????????????????????????????????? 1900 ???
				if (!empty($anEvent['when']) and strlen($anEvent['when']) >=10 and substr($anEvent['when'],0,10)<'2014-01-01' )
					$anEvent['when'] = '';
	
				if (!empty($anEvent['type'])){
					$class_nation = $anEvent['type'];
				}
	
				if (empty($className)){
					$className = 'orange_bold';
				}else{
					$className = 'font_normal';
				}
				$all_events_str  .= '<dd>'.
						'<div class="col-md-12 '.$className.'">'.
						'<i class="'.(($className=='orange_bold')?"egicon-arrow-on-yellow":"egicon-arrow-on-gray").'"></i>'.
						'<time '.(($className=='orange_bold')?'style="color: #f0ad4e;" ':'').'>'. $anEvent['when'].'</time>'.
						'<p>'.$anEvent['where'].((empty($anEvent['where']))?"":",").
						$anEvent['what']."</p></div>".
						"</dd>";
			}
		}
		
		$all_events_str = "<dl lang='".$to_lang."'>".$all_events_str.'</dl>';
		return ['success'=>true,'message'=>'','eventHtml'=>$all_events_str];
	}//end of generateTrackingEventHTML
	
	
	
	public static function getTranslatedEvents($track_no,$from_lang,$to_lang,$save_to_info=false){
		$rtn['message']="";
		$rtn['success'] = true;
		$rtn['allEvents'] = array();
		$now_str = date('Y-m-d H:i:s');
		
		if (empty($track_no) ){
			$rtn['message']="????????????Tracking No??????";
			$rtn['success'] = false;
			return $rtn;
		}
		try{	
			$model = Tracking::find()->andWhere("track_no=:track_no",array(":track_no"=>$track_no))->one();
			//step 1: when not found such record, skip it
			if ($model == null){
				$rtn['message']="????????????Tracking No????????????$track_no";
				$rtn['success'] = false;
				return $rtn;
			}
			
			//step 2: ??????all events?????????????????????
			$allEvents = json_decode($model->all_event , true);
			if (empty($allEvents)) $allEvents = array();
			$new_event_md5 = md5(json_encode($allEvents));
			//$rtn['original'] = $allEvents;
			global $CACHE;
			$translated_Events = array();//?????????????????????
			$events_for_save= [];//???????????????db????????????????????????
			$track_addi_info = empty($model->addi_info)?[]:json_decode($model->addi_info,true);
			
			//case 1a:track_no???????????????
			if(!empty($CACHE['trackerBaiduTranslate'][$from_lang][$track_no])){
				$old_event_md5 = @$CACHE['trackerBaiduTranslate'][$to_lang][$track_no]['md5'];
				$tmp_translated_Events =  @$CACHE['trackerBaiduTranslate'][$to_lang][$track_no]['events'];
				
				if($new_event_md5!==$old_event_md5){
					//case 1a.2a:rack_no????????????????????????????????????
					$translated_md5s = array_keys($tmp_translated_Events['events']);
					foreach ($allEvents as $src_event){
						$src_event_64 = empty($src_event['what'])?'':$src_event['what'];
						$src_md5 = md5($src_event_64);
						$tmp_e_arr = $src_event;
						if(in_array($scr_md5, $translated_md5s)){
							//?????????
							$tmp_e_arr['what'] = $tmp_translated_Events['events'][$scr_md5];
							$translated_Events[]= $tmp_e_arr;
							$events_for_save[$src_md5] = $tmp_translated_Events['events'][$scr_md5];
						}else{
							//?????????
							$r = TranslateHelper::translate(base64_decode($src_event_64), $from_lang, $to_lang);
							//????????????????????????
							if(isset($r['error_code'])){
								$rtn['message'] = "????????????,??????".$r['error_msg'];
								$rtn['success'] = false;
								$events_for_db[$src_md5] = base64_decode($src_event_64);
								$translated_Events[] =  $src_event;
							}else{
								$tmp_e_arr['what'] = $r['trans_result'][0]['dst'];
								$translated_Events[] = $tmp_e_arr;
								$events_for_save[$src_md5] = $r['trans_result'][0]['dst'];
							}
						}
					}
				}else{
					//case 1a.2b:rack_no????????????????????????????????????
					if(!empty($tmp_translated_Events)){
						foreach ($allEvents as $src_event){
							$src_event_64 = empty($src_event['what'])?'':$src_event['what'];
							$src_md5 = md5($src_event_64);
							$tmp_e_arr = $src_event;
							if(isset($tmp_translated_Events['events'][$src_md5]))
								$tmp_e_arr['what'] = $tmp_translated_Events['events'][$scr_md5];
							
							$translated_Events[] = $tmp_e_arr;
							$events_for_save[$src_md5] = $tmp_e_arr['what'];
						}
					}
				}
			}else{
			//case 1b:track_no???????????????
				if(!empty($track_addi_info['translated_events'][$to_lang])){
					//case 1b.2a:track_no?????????db??????
					if(@$track_addi_info['translated_events'][$to_lang]['md5']==$new_event_md5){
						//case 1b.2a.3a:rack_no????????????????????????????????????
						$tmp_translated_Events = @$track_addi_info['translated_events'][$to_lang]['events'];
						if(!empty($tmp_translated_Events)){
							foreach ($allEvents as $src_event){
								$src_event_64 = empty($src_event['what'])?'':$src_event['what'];
								$src_md5 = md5($src_event_64);
								$tmp_e_arr = $src_event;
								if(isset($tmp_translated_Events[$src_md5]))
									$tmp_e_arr['what'] = $tmp_translated_Events[$src_md5];
								
								$translated_Events[] = $tmp_e_arr;
								$events_for_save[$src_md5] = $tmp_e_arr['what'];
							}
						}
					}else{
						//case 1b.2a.3b:rack_no????????????????????????????????????
						$translated_md5s = array_keys($track_addi_info['translated_events'][$to_lang]['events']);
						$tmp_translated_Events = @$track_addi_info['translated_events'][$to_lang]['events'];
						foreach ($allEvents as $src_event){
							$src_event_64 = empty($src_event['what'])?'':$src_event['what'];
							$src_md5 = md5($src_event_64);
							$tmp_e_arr = $src_event;
							if(in_array($src_md5, $translated_md5s)){
								//?????????
								$tmp_e_arr['what'] = $tmp_translated_Events[$src_md5];
								$translated_Events[]= $tmp_e_arr;
								$events_for_save[$src_md5] = $tmp_e_arr['what'];
							}else{
								//?????????
								$r = TranslateHelper::translate(base64_decode($src_event_64), $from_lang, $to_lang);
								//????????????????????????
								if(isset($r['error_code'])){
									$rtn['message'] = "????????????,??????".$r['error_msg'];
									$rtn['success'] = false;
									$events_for_db[$src_md5] = base64_decode($src_event_64);
									$translated_Events[] =  $tmp_e_arr;
								}else{
									$tmp_e_arr['what'] = $r['trans_result'][0]['dst'];
									$translated_Events[] = $tmp_e_arr;
									$events_for_save[$src_md5] = $tmp_e_arr['what'];
								}
							}
						}
					}
				}else{
					//case 1b.2a:track_no?????????db??????
					foreach ($allEvents as $src_event){
						$src_event_64 = empty($src_event['what'])?'':$src_event['what'];
						$src_md5 = md5($src_event_64);
						$tmp_e_arr = $src_event;
						$r = TranslateHelper::translate(base64_decode($src_event_64), $from_lang, $to_lang);
						//????????????????????????
						if(isset($r['error_code'])){
							$rtn['message'] = "????????????,??????".$r['error_msg'];
							$rtn['success'] = false;
							$events_for_db[$src_md5] = base64_decode($src_event_64);
							$translated_Events[] =  $tmp_e_arr;
						}else{
							$tmp_e_arr['what'] = $r['trans_result'][0]['dst'];
							$translated_Events[] = $tmp_e_arr;
							$events_for_save[$src_md5] = $tmp_e_arr['what'];
						}
						
					}
				}
			}
			//????????????????????????????????????????????????db
			if($rtn['success']){
				$rtn['allEvents'] = $translated_Events;
				$new_event_md5 = md5(json_encode($allEvents));
				$CACHE['trackerBaiduTranslate'][$to_lang][$track_no]['md5'] = $new_event_md5;
				$CACHE['trackerBaiduTranslate'][$to_lang][$track_no]['events'] = $events_for_save;
				if($save_to_info){
					$track_addi_info['translated_events'][$to_lang]['md5'] = $new_event_md5;
					$track_addi_info['translated_events'][$to_lang]['events'] = $events_for_save;
					$model->addi_info = json_encode($track_addi_info);
					$model->save();
				}
			}
		}catch (Exception $e) {
			$rtn['success'] = false;
			$rtn['message'] .= '????????????';
		}
		return $rtn;
	}
}


