<?php

namespace eagle\modules\carrier\helpers;

use \Yii;
use eagle\models\carrier\CarrierUseRecord;
use eagle\modules\carrier\models\SysCarrierAccount;
use eagle\modules\carrier\models\CarrierUserAddress;
use eagle\modules\carrier\models\SysShippingService;
use eagle\modules\carrier\models\SysCarrier;
use common\helpers\Helper_Array;
use eagle\modules\carrier\models\SysCarrierParam;
use eagle\modules\carrier\models\CarrierUserUse;
use eagle\models\CrCarrierTemplate;
use eagle\models\carrier\CrTemplate;
use eagle\modules\inventory\helpers\WarehouseHelper;
use eagle\modules\platform\apihelpers\PlatformAccountApi;
use common\api\aliexpressinterface\AliexpressInterface_Helper;
use eagle\modules\carrier\models\MatchingRule;
use eagle\models\SysShippingMethod;
use common\api\aliexpressinterface\AliexpressInterface_Api;
use eagle\models\SysShippingCodeNameMap;
use eagle\modules\carrier\models\SysCarrierCustom;
use yii\caching\ArrayCache;
use eagle\modules\util\helpers\ExcelHelper;
use yii\data\Pagination;
use yii\db\Query;
use eagle\modules\util\helpers\ConfigHelper;
use eagle\modules\carrier\models\SysTrackingNumber;
use yii\data\Sort;
use eagle\modules\inventory\helpers\InventoryHelper;
use eagle\modules\util\helpers\OperationLogHelper;
use eagle\modules\catalog\helpers\ProductApiHelper;
use eagle\modules\catalog\models\ProductTags;
use eagle\modules\inventory\models\Warehouse;
use eagle\modules\inventory\helpers\InventoryApiHelper;
use eagle\modules\order\models\OdOrderShipped;
use eagle\modules\lazada\apihelpers\LazadaApiHelper;
use Qiniu\json_decode;
use eagle\modules\order\helpers\CdiscountOrderInterface;
use yii\helpers\Html;
use common\api\carrierAPI\LB_ALIONLINEDELIVERYCarrierAPI;
use yii\db\Command;
use eagle\models\carrier\CommonDeclaredInfo;
use eagle\models\CarrierTemplateHighcopy;
use eagle\models\SaasAliexpressUser;
use eagle\modules\util\helpers\RedisHelper;
use common\api\carrierAPI\LB_IEUBNewCarrierAPI;
use eagle\modules\configuration\controllers\CarrierconfigController;
use eagle\modules\carrier\controllers\CarrierController;
use common\helpers\Helper_Curl;
use eagle\modules\order\models\OdOrder;
use common\api\aliexpressinterfacev2\AliexpressInterface_Helper_Qimen;
use common\api\aliexpressinterfacev2\AliexpressInterface_Api_Qimen;
use eagle\models\SaasEbayUser;
use common\api\carrierAPI\LB_EDISCarrierAPI;


class CarrierOpenHelper {
	//?????????????????????????????????????????????
	public static $carrierPrintType = array(
			'no_print_distribution'=>'???????????????',	//??????????????????????????????????????????????????????
			'print_distribution'=>'???????????????',
			'no_print_carrier'=>'????????????',
			'print_carrier'=>'????????????');
	
	public static $platformCustomerNumberMode = array(
			'serial_random_6number'=>'???????????????+???????????????',
			'serial_date'=>'???????????????+??????',
			'platform_id'=>'???????????????',
			'platform_id_random_6number'=>'???????????????+???????????????',
			'platform_id_date'=>'???????????????+??????',
	);
	
	/**
	 * ?????????????????????????????????
	 *
	 * @param $carrier_code	????????????	?????????
	 * @param $is_show_all	???????????????????????????	?????????
	 * @param $carrier_type	?????????????????????	0???????????????	1??????????????????	3???????????????????????????
	 * @return Array
		Array
		(
		    [0] => Array
		        (
		            [id] => 1
		            [carrier_code] => lb_CNE
		            [is_active] => 1
		            [create_time] => 0
		            [update_time] => 1449727219
		            [is_del] => 0
		            [carrier_type] => 0
		            [is_show_address] => 		0???????????????table 1????????????table
		        )
		    [1] => Array
		        (
		            [id] => 2
		            [carrier_code] => lb_anjun
		            [is_active] => 0
		            [create_time] => 0
		            [update_time] => 0
		            [is_del] => 0
		            [carrier_type] => 0
		            [is_show_address] => 
		        )
        )
	 *
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/01				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getHasOpenCarrier($carrier_code = '', $is_show_all = 'N', $carrier_type = 0){
		$query = CarrierUseRecord::find();//->where(['is_active'=>1]);
		
		if(!empty($carrier_code)){
			$query->andWhere(['carrier_code'=>$carrier_code]);
		}
		
		if($is_show_all != 'Y'){
			$query->andWhere(['is_del'=>'0']);
		}
		
		if(empty($carrier_type)){
			$query->andWhere(['carrier_type'=>0]);
		}else{
			if($carrier_type == 1){
				$query->andWhere(['carrier_type'=>1]);
			}else{
			}
		}
		
		$hasOpenCarrier = $query->asArray()->all();
		
		$carrierList = Helper_Array::toHashmap($hasOpenCarrier, 'carrier_code', 'carrier_code');
		
		$carrierArr = SysCarrier::find()->select(['carrier_code','address_list','carrier_name'])->where(['in','carrier_code',$carrierList])->asArray()->all();
		
		foreach ($hasOpenCarrier as &$tmpOne){
			foreach ($carrierArr as $carrierOne){
				if($carrierOne['carrier_code'] == $tmpOne['carrier_code']){
					$tmpOne['is_show_address'] = empty($carrierOne['address_list']) ? 0 : 1;
					$tmpOne['carrier_name']  = $carrierOne['carrier_name'];
					break;
				}
			}
		}
		
		return $hasOpenCarrier;
	}
	
	/**
	 * ?????????????????????????????????
	 *
	 * @param $carrier_code	????????????	?????????
	 * @param $is_show_all	??????????????????????????????	?????????
	 * @return Array
	 Array
	(
	    [0] => Array
	        (
	            [id] => 19
	            [carrier_code] => lb_CNE
	            [carrier_name] => cne001
	            [carrier_type] => 0
	            [api_params] => Array
	                (
	                    [userkey] => 71
	                    [token] => tokeng
	                )
	
	            [create_time] => 1447125901
	            [update_time] => 1448851296
	            [user_id] => 1
	            [is_used] => 1
	            [address] => Array
	                (
	                    [shippingfrom] => Array
	                        (
	                            [country] => CN
	                            [province] => guangdongshen
	                            [city] => zhongs1
	                            [district] => xiaolan
	                            [street] => dfg
	                            [postcode] => 4
	                            [company] => 
	                            [contact] => 
	                            [mobile] => 
	                            [phone] => 
	                            [fax] => 
	                            [email] => 
	                        )
	
	                )
	
	            [warehouse] => Array
	                (
	                )
	
	            [is_default] => 0
	            [is_del] => 0
	        )
	)
	 *
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/02				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getBindingCarrierAccount($carrier_code = '',$is_show_all = 'N',$warehouse_id=-1,$is_used=-1){
		$query = SysCarrierAccount::find();

		if(!empty($carrier_code)){
			$query->andWhere(['carrier_code'=>$carrier_code]);
		}
		
		if($warehouse_id!=-1){
			$query->andWhere(['warehouse_id'=>$warehouse_id]);
		}

		if($is_show_all != 'Y'){
			$query->andWhere(['is_del'=>'0']);
		}
		
		if($is_used == 1){
			$query->andWhere(['is_used'=>'1']);
		}
						
		$query->orderBy('is_used desc');
		
		$accountArr = $query->asArray()->all();
		
		self::StrToUnserialize($accountArr,array('address','api_params','warehouse'));
		
		return $accountArr;
	}
	
	/**
	 * ??????????????????????????????????????????????????????	api??????????????????????????????
	 *
	 * @param $carrier_code	????????????	??????
	 * @return Array
		Array
		(
		    [0] => Array
		        (
		            [id] => 1
		            [carrier_code] => lb_CNE
		            [type] => 0
		            [address_name] => 
		            [is_del] => 0
		            [is_default] => 0
		            [address_params] => Array
		                (
		                    [contact] => 
		                    [company] => 
		                    [phone] => 
		                    [mobile] => 
		                    [fax] => 
		                    [email] => 
		                    [country] => 
		                    [province] => 
		                    [city] => 
		                    [district] => 
		                    [postcode] => 
		                    [street] => 
		                    [contact_en] => 
		                    [company_en] => 
		                    [province_en] => 
		                    [city_en] => 
		                    [district_en] => 
		                    [street_en] => 
		                )
		
		        )
		
		)
	 *
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/02				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCarrierAccountAdderssByCarrierCode($carrier_code){
		$query = CarrierUserAddress::find();
		
		$query->andWhere(['is_del'=>'0']);
		$query->andWhere(['type'=>'0']);
		$query->andWhere(['carrier_code'=>$carrier_code]);
		
		$addressArr = $query->asArray()->all();
		
		self::StrToUnserialize($addressArr,array('address_params'));
		
		return $addressArr;
	}
	
	/**
	 * ????????????user????????????????????????
	 *
	 * @param $carrier_code	????????????	??????
	 * @return Array
	 Array
		(
		    [0] => Array
		        (
		            [id] => 618
		            [carrier_code] => lb_CNE
		            [carrier_params] => Array
		                (
		                    [nItemType] => 
		                    [nPayWay] => 
		                    [labelstyle] => labelA46_0
		                )
		
		            [ship_address] => 
		            [return_address] => 
		            [is_used] => 1
		            [service_name] => CNE-cne001-CNE????????????
		            [service_code] => Array
		                (
		                    [ebay] => dhl
		                    [amazon] => DHL
		                    [aliexpress] => DHL
		                    [wish] => 4PX
		                    [dhgate] => DHL
		                    [cdiscount] => ba
		                    [lazada] => AS-4PX-Postal-Singpost
		                    [linio] => 
		                )
		
		            [auto_ship] => 0
		            [web] => http://www.17track.net
		            [create_time] => 1447125901
		            [update_time] => 1448422511
		            [carrier_account_id] => 19
		            [extra_carrier] => 
		            [carrier_name] => CNE
		            [shipping_method_name] => CNE????????????
		            [shipping_method_code] => CNE????????????
		            [third_party_code] => 
		            [warehouse_name] => 
		            [address] => 
		            [is_custom] => 0
		            [custom_template_print] => 
		            [transport_service_type] => 0
		            [aging] => 
		            [is_tracking_number] => 0
		            [print_params] => 
		            [proprietary_warehouse] => 
		            [declaration_max_value] => 0.00
		            [declaration_max_weight] => 0.0000
		            [customer_number_config] => 
		            [is_del] => 0
		            [rule] => Array
	                (
	                    [15] => anjun
	                    [18] => ddd
	                )
		        )
		)

	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/02				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCarrierShippingServiceUserByCarrierCode($carrier_code, $service_name = ''){
		$query = SysShippingService::find();
		
		//?????????????????????????????????????????????????????????????????????
		$sysShippingMethodClose = SysShippingMethod::find()->select(['shipping_method_code'])->where(['carrier_code'=>$carrier_code,'is_close'=>1])->asArray()->all();
		if(!empty($sysShippingMethodClose)){
			$sysShippingMethodClose = Helper_Array::toHashmap($sysShippingMethodClose, 'shipping_method_code', 'shipping_method_code');
		}else{
			$sysShippingMethodClose = array();
		}
		
		$query->andWhere(['carrier_code'=>$carrier_code,'is_del'=>0]);
		
		if(!empty($service_name)){
			$query->andWhere(['like','shipping_method_name',$service_name]);
		}
		
		if(substr($carrier_code, 0, 3) == 'lb_'){
			//?????????????????????
			$carrierAccountList = self::getCarrierAccountList($carrier_code)['response']['data'];
			$tmpCarrierAccountListIDArr = array();
			
			foreach ($carrierAccountList as $carrierAccountKey => $carrierAccountVal){
				$tmpCarrierAccountListIDArr[] = $carrierAccountKey;
			}
			
			$query->andWhere(['in', 'carrier_account_id', $tmpCarrierAccountListIDArr]);
		}
		
		$sort_arr = array('is_used'=>SORT_DESC,'carrier_code'=>SORT_ASC,'create_time'=>SORT_ASC,'shipping_method_name'=>SORT_ASC,'service_name'=>SORT_ASC,'carrier_account_id'=>SORT_DESC);
		$query->orderBy($sort_arr);
		
		$shippingServiceArr = $query->asArray()->all();
		
		self::StrToUnserialize($shippingServiceArr,array('carrier_params','ship_address','return_address','service_code','address','custom_template_print','print_params','proprietary_warehouse','customer_number_config'));
		
		foreach ($shippingServiceArr as &$shippingServiceOne){
			$rule = MatchingRule::find()->select(['id','rule_name'])->where(['transportation_service_id'=>$shippingServiceOne['id'],'is_active'=>1])->andWhere('created > 0')->asArray()->all();
			$rule = Helper_Array::toHashmap($rule,'id','rule_name');
			
			$shippingServiceOne['rule'] = $rule;
			
			$shippingServiceOne['accountNickname'] = empty($carrierAccountList[$shippingServiceOne['carrier_account_id']]) ? '' : $carrierAccountList[$shippingServiceOne['carrier_account_id']];
			
			if(isset($sysShippingMethodClose[$shippingServiceOne['shipping_method_code']])){
				$shippingServiceOne['is_close'] = 1;
			}else{
				$shippingServiceOne['is_close'] = 0;
			}
		}
		
		return $shippingServiceArr;
	}
	
	/**
	 * ?????????????????????????????????
	 *
	 * @param $carrier_type ?????????????????????	0?????????	1????????????	3???????????????
	 * @param $is_active	?????????????????????	
	 * @param $is_open		????????????????????????????????????
	 * @param $show_active	???????????????????????????????????????
	 * @return Array
		Array
		(
		    [lb_369guojikuaidirtbcompany] => 369????????????
		    [lb_4px] => ?????????
		    [lb_4pxOversea] => ?????????(?????????)
		    [lb_alionlinedelivery] => ?????????????????????
		    [lb_baishiyundartbcompany] => ????????????
		    [lb_bangliguojirtbcompany] => ????????????
		    [lb_beijingyichengrtbcompany] => ????????????
		    [lb_birdsysOversea] => ????????????(?????????)
		    [lb_chukouyi] => ?????????
		)
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/02				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getNotOpenCarrierArr($carrier_type = 0, $is_active = 0, $is_open = 0, $show_active = false){
		$query = SysCarrier::find()->select(['carrier_code','carrier_name']);
		
		if($is_open == 1)
			$query->andWhere(['is_active'=>1]);
		
		if($is_active == 0 || $is_active == 1){
			$que = CarrierUseRecord::find()->select(['carrier_code'])->where(['is_del'=>0]);
			
			if($show_active == false)
				$que->andWhere(['not in','is_active',$is_active]);
			
			$openCarrierListArr = $que->asArray()->all();
			$openCarrierListArr = Helper_Array::toHashmap($openCarrierListArr, 'carrier_code', 'carrier_code');
		}else{
			$openCarrierListArr = array();
		}
		
		if(empty($carrier_type)){
			$query->andWhere(['carrier_type' => 0]);
		}else{
			if($carrier_type == 1){
				$query->andWhere(['carrier_type' => 1]);
			}
		}
		
		$query->andWhere(['not in','carrier_code',$openCarrierListArr]);
		
		$result = $query->asArray()->all();
		$result = Helper_Array::toHashmap($result, 'carrier_code', 'carrier_name');
		
		return $result;
	}
	
	/**
	 * ???????????????user????????????
	 *
	 * @param	$carrier_code	????????????	??????
	 * @param	$is_active	???????????????	??????		1:??????	0:?????????
	 * @return Array
	 * Array
		(
		    [response] => Array
		        (
		            [code] => 0			0:???????????????1:????????????
		            [msg] => ????????????.	???????????????
		            [data] => Array
		                (
		                )
		        )
		)
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/03				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function carrierOpenOrCloseRecord($carrier_code, $is_active){
		$carrierUseRecord = CarrierUseRecord::find()->where(['carrier_code'=>$carrier_code])->one();
		
		$sysCarrier = SysCarrier::find()->where(['carrier_code'=>$carrier_code])->one();
		
		if($sysCarrier == null){
			return self::output(array(), 1, '??????????????????????????????????????????????????????.');
		}
		
		if(($carrierUseRecord == null) && ($is_active == 0)){
			return self::output(array(), 1, '??????????????????????????????????????????.');
		}
		
		if($carrierUseRecord != null){
			if(($carrierUseRecord->is_active == 1) && ($is_active == 1)){
				return self::output(array(), 1, '????????????????????????.');
			}
			
			if(($carrierUseRecord->is_active == 0) && ($is_active == 0)){
				return self::output(array(), 1, '????????????????????????.');
			}
		}
		
		if($carrierUseRecord == null){
			if($is_active == 1){
				$carrierUseRecord = new CarrierUseRecord();
					
				$carrierUseRecord->carrier_code = $carrier_code;
				$carrierUseRecord->create_time = time();
				$carrierUseRecord->is_del = 0;
				$carrierUseRecord->carrier_type = $sysCarrier->carrier_type;
			}else{
				return self::output(array(), 1, '????????????.');
			}
		}
		
		if(($carrierUseRecord->is_del == 1) && ($is_active == 1)){
			$carrierUseRecord->is_del = 0;
		}
		
		$carrierUseRecord->is_active = $is_active;
		$carrierUseRecord->update_time = time();
		
		if($carrierUseRecord->save(false)){
			if($is_active == 1)
				return self::output(array(), 0, '????????????.');
			else
				return self::output(array(), 0, '????????????.');
		}else{
			return self::output(array(), 1, '????????????.');
		}
	}
	
	/**
	 * ????????????????????????????????????
	 *
	 * @param	$carrier_code	????????????	??????
	 * @param	$accountId	????????????id	???0???????????????????????????		??????
	 * @return Array
	 * Array
		(
		    [response] => Array
		        (
		            [code] => 0
		            [msg] => 
		            [data] => Array
		                (
		                    [id] => 33
		                    [accountNickname] => ??????2
		                    [authParams] => Array
		                        (
		                            [userid] => Array
		                                (
		                                    [carrier_param_name] => ??????
		                                    [carrier_param_value] => 	?????????????????????
		                                    [carrier_display_type] => text	????????????	text/dropdownlist
		                                    [carrier_is_required] => 0	?????????????????????
		                                    [carrier_is_encrypt] => 0	???carrier_display_type???text??????????????????????????????
		                                    [param_value] => 100000	????????????
		                                )
		
		                            [token] => Array
		                                (
		                                    [carrier_param_name] => Token
		                                    [carrier_param_value] => 
		                                    [carrier_display_type] => text
		                                    [carrier_is_required] => 0
		                                    [carrier_is_encrypt] => 0
		                                    [param_value] => MTAwMDAwOjEwMDAwMQ==
		                                )
		
		                            [EName_mode] => Array
		                                (
		                                    [carrier_param_name] => ???????????????????????????
		                                    [carrier_param_value] => Array
		                                        (
		                                            [N] => ?????????
		                                            [sku] => ??????SKU+??????+???????????????
		                                        )
		
		                                    [carrier_display_type] => dropdownlist
		                                    [carrier_is_required] => 0
		                                    [carrier_is_encrypt] => 0
		                                    [param_value] => sku
		                                )
		
		                        )
		
		                    [isDefault] => 0
		                )
		
		        )
		
		)
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/04				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function carrierUserAccountShowById($carrier_code, $accountId = 0){
		if(empty($accountId)){
			$carrierAccount = new SysCarrierAccount();
		}else{
			$carrierAccount = SysCarrierAccount::find()->where(['id'=>$accountId])->one();
			
			if($carrierAccount == null){
				return self::output(array(), 1, '????????????,????????????????????????id.');
			}
			if($carrier_code != $carrierAccount['carrier_code']){
				return self::output(array(), 1, '????????????,??????????????????carrier_code???account???carrier_code?????????.');
			}
		}
		
		$sysCarrier = SysCarrier::find()->where(['carrier_code'=>$carrier_code])->one();
		
		$sysCarrierParam = SysCarrierParam::find()->where(['carrier_code'=>$carrier_code, 'type'=>0])->orderby('sort asc')->asArray()->all();
	
		$authParams = array();
		
		foreach ($sysCarrierParam as $sysCarrierParamOne){
			$authParams[$sysCarrierParamOne['carrier_param_key']] = array(
					'carrier_param_name'=>$sysCarrierParamOne['carrier_param_name'], 
					'carrier_param_value'=>unserialize($sysCarrierParamOne['carrier_param_value']),
					'carrier_display_type'=>$sysCarrierParamOne['display_type'],
					'carrier_is_required'=>$sysCarrierParamOne['is_required'],
					'carrier_is_encrypt'=>$sysCarrierParamOne['is_encrypt'],
					'param_value'=>(!isset($carrierAccount->api_params[$sysCarrierParamOne['carrier_param_key']])) ? '' : $carrierAccount->api_params[$sysCarrierParamOne['carrier_param_key']],
					'is_hidden'=>$sysCarrierParamOne['is_hidden'],
			);
		}
		
		$result = array(
				'id' => $accountId,
				'accountNickname' => empty($carrierAccount->carrier_name) ? '' : $carrierAccount->carrier_name,
				'authParams' => $authParams,
				'isDefault' => empty($carrierAccount->is_default) ? 0 : $carrierAccount->is_default,
				'help_url' => empty($sysCarrier->help_url) ? '' : $sysCarrier->help_url,
		);
		
		return self::output($result, 0, '');
	}
	
	/**
	 * ???????????????????????? ??????
	 *
	 * @param	$id	??????id ??????????????????????????????0???,??????sys_carrier_account?????????id
	 * @param	$accountParams = array(
					'accountNickname'=>'nnn',	????????????	??????
					'carrier_code'=>'lb_CNE',	???????????????	??????
					'is_used'=>1,	???????????????????????????	?????????
					'is_default'=>1	???????????????????????????	??????
					'carrier_params' => array(	?????????????????????????????????????????????????????????????????????????????????????????????????????????	?????????????????????????????????
							'userkey' => 'xxx',
							'appKey' => 'appxxx',
					),
					'warehouse' => array(),	??????????????????id ?????????
				);
	 * @return Array
	 * Array
		(
		    [response] => Array
		        (
		            [code] => 0
		            [msg] => '????????????.'
		            [data] => Array
		                (
		                )
		        )
		)
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/04				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function carrierUserAccountAddOrEdit($id, $accountParams = array()){
		if(empty($accountParams['accountNickname'])){
			return self::output(array(), 1, '??????????????????.');
		}
		
		if(empty($accountParams['carrier_code'])){
			return self::output(array(), 1, '?????????????????????.');
		}
		
		if(empty($id)){
			$account = new SysCarrierAccount();
			$account->create_time = time();
			
			if(isset($accountParams['warehouse_id']))
				$account->warehouse_id = $accountParams['warehouse_id'];
			
		}else{
			$account = SysCarrierAccount::find()->where(['id'=>$id])->one();
			
			if($account == null){
				return self::output(array(), 1, '????????????,???ID??????????????????.');
			}
			$account->update_time = time();
		}
		
		//???????????????????????? Start
		if ($account->isNewRecord){
			$count = SysCarrierAccount::find()->where(['carrier_name'=>$accountParams['accountNickname'],'is_del'=>0])->count();
		}else{
			$count = SysCarrierAccount::find()->where('carrier_name = :carrier_name and id <>:id and is_del=0',[':carrier_name'=>$accountParams['accountNickname'],':id'=>$id])->count();
		}
		
		if ($count>0){
			return self::output(array(), 1, '??????????????????.');
		}
		
		//???????????????????????? End
		
		$tmpCarrierParams = isset($accountParams['carrier_params']) ? $accountParams['carrier_params'] : array();
		
		$account->carrier_code = $accountParams['carrier_code'];
		$account->carrier_name = $accountParams['accountNickname'];
		$Carrier = SysCarrier::findOne($account->carrier_code);
		$account->carrier_type = $Carrier['carrier_type'];
		$account->user_id = \Yii::$app->user->id;
		$account->is_default = $accountParams['is_default'];
		$account->warehouse = isset($accountParams['warehouse']) ? $accountParams['warehouse'] : array();
		
		//wish???????????????????????????????????????0
		if( $accountParams['carrier_code'] == "lb_wishyou" )
		{
		    //??????????????????
		    if(empty($id))
		    {
		        $account->is_used = 0;
		        $account->api_params = array();
		    }
		    else 
		    {
	        	//access_token???refresh_token??????????????????????????????
	        	$api_params = $account->api_params;
	        	$notupdate = array('user_id', 'access_token', 'refresh_token', 'expires_in', 'expiry_time');
	        	foreach($tmpCarrierParams as $key => $val)
	        	{
	        		if(!in_array($key, $notupdate))
	        			$api_params[$key] = $val;
	        	}
	        	$account->api_params = $api_params;
		    }
		}elseif( $accountParams['carrier_code'] == "lb_newwinit" ){
			//??????????????????
			if(empty($id))
			{
				$account->is_used = 0;
				$account->api_params = array();
			}else{
				$api_params = $account->api_params;
				$notupdate = array('token');
				foreach($tmpCarrierParams as $key => $val)
				{
					if(!in_array($key, $notupdate))
						$api_params[$key] = $val;
				}
				$account->api_params = $api_params;
			}
		}elseif( $accountParams['carrier_code'] == "lb_4pxNew" ){
			//??????????????????
			if(empty($id))
			{
				$account->is_used = 0;
				$account->api_params = array();
			}else{
			    $api_params = $account->api_params;
	        	$notupdate = array('access_token', 'refresh_token', 'expires_in', 'access_token_timeout');
	        	foreach($tmpCarrierParams as $key => $val)
	        	{
	        		if(!in_array($key, $notupdate))
	        			$api_params[$key] = $val;
	        	}
				$account->api_params = $api_params;
			}
		}
		else
		{
		    $account->is_used = isset($accountParams['is_used']) ? $accountParams['is_used'] : 1;
		    $account->api_params = $tmpCarrierParams;
		    
    		//???????????????????????????????????? Start
    		if($Carrier->carrier_type){
    			$verifyAccountRepeat = SysCarrierAccount::find()->select(['api_params'])
    				->where(['carrier_code'=>$accountParams['carrier_code'],'is_del'=>0,'warehouse_id'=>$account->warehouse_id])->andWhere('id <> :id',[':id'=>$id])->asArray()->all();
    		}else{
    			$verifyAccountRepeat = SysCarrierAccount::find()->select(['api_params'])->where(['carrier_code'=>$accountParams['carrier_code'],'is_del'=>0])->andWhere('id <> :id',[':id'=>$id])->asArray()->all();
    		}
		
    		if(count($verifyAccountRepeat) >= 1){
    			$carrierParams = SysCarrierParam::find()->select(['carrier_param_key'])
    				->where(['carrier_code'=>$account->carrier_code,'type'=>0,'is_hidden'=>0])->orderBy('sort')->asArray()->all();
    			
    			if(!empty($carrierParams)){
    				$tmpInfoParams = array();
    				
    				//????????????????????????????????????????????????????????????????????????????????????
    				foreach ($carrierParams as $carrierParamsOne){
    					if(isset($tmpCarrierParams[$carrierParamsOne['carrier_param_key']]))
    						$tmpRepeatStr = $tmpCarrierParams[$carrierParamsOne['carrier_param_key']];
    				}
    				$tmpInfoParams[$tmpRepeatStr] = '';
    				
    				//????????????????????????????????????????????????
    				foreach ($verifyAccountRepeat as $verifyAccountRepeatOne){
    					$verifyAccountRepeatOne['api_params'] = unserialize($verifyAccountRepeatOne['api_params']);
    					
    					$tmpRepeatStr = '';
    					foreach ($carrierParams as $carrierParamsOne){
    						if(isset($verifyAccountRepeatOne['api_params'][$carrierParamsOne['carrier_param_key']]))
    							$tmpRepeatStr = $verifyAccountRepeatOne['api_params'][$carrierParamsOne['carrier_param_key']];
    					}
    					
    					if(isset($tmpInfoParams[$tmpRepeatStr])){
//     						return self::output(array(), 1, '?????????????????????????????????????????????');
    					}else{
    						$tmpInfoParams[$tmpRepeatStr] = '';
    					}
    				}
    			}
    		}
    		//???????????????????????????????????? End
    		
    		//??????????????????????????????????????????????????? Start
    		$class_name = '';
    		//????????????????????????????????????type=1
    		if($Carrier->carrier_type){
    			$class_name = '\common\api\overseaWarehouseAPI\\'.$Carrier->api_class;
    		}else{
    			$class_name = '\common\api\carrierAPI\\'.$Carrier->api_class;
    		}
    		//??????????????????????????????
    		if($Carrier->api_class == 'LB_RTBCOMPANYCarrierAPI'){
    			//???????????????????????????
    			$interface = new $class_name($Carrier->carrier_code);
    		}
    		else{
    			$interface = new $class_name;
    		}
    		
    		if(method_exists($interface,'getVerifyCarrierAccountInformation')){
    			$tmpVerifyInfo = $interface->getVerifyCarrierAccountInformation($tmpCarrierParams);
    		
    			if(($tmpVerifyInfo['is_support'] == 1) && ($tmpVerifyInfo['error'] == 1)){
    				$carrierParams = SysCarrierParam::find()->select(['carrier_param_name'])
    					->where(['carrier_code'=>$account->carrier_code,'type'=>0,'is_hidden'=>0])->orderBy('sort')->asArray()->all();
    				
    				$tmpError = '';
    				
    				if(!empty($carrierParams) && !empty($tmpCarrierParams)){
    					foreach ($carrierParams as $carrierParamsOne){
    						$tmpError .= $carrierParamsOne['carrier_param_name'].'??????';
    					}
    				}
    				
    				$tmpError = substr($tmpError,0,strlen($tmpError)-6);
    				
    				return self::output(array(), 1, '??????????????? ???????????????'.$tmpError.'??????????????????????????????!');
    			}
    		}
    		//??????????????????????????????????????????????????? End
		}
		
		if($account->save())
		{
			if($accountParams['is_default'] == 1){
				$command = Yii::$app->subdb->createCommand("update sys_carrier_account set is_default=0 where carrier_code = :carrier_code and id <> :id and is_default = 1 and is_del = 0 ");
				$command->bindValue(':carrier_code', $account->carrier_code, \PDO::PARAM_STR);
				$command->bindValue(':id', $account->id, \PDO::PARAM_STR);
				$affectRows = $command->execute();
			}
			
			$carriers = SysCarrier::find()->orderBy('carrier_name asc')->select(['carrier_code','carrier_name'])->asArray()->all();
			$carriers = Helper_Array::toHashmap($carriers,'carrier_code','carrier_name');
			
			//????????????code?????????????????????????????????
// 			$res = CarrierOpenHelper::refreshCarrierShippingMethod($account->carrier_code);
			
			//??????????????????User?????????????????????
			self::saveAddOrEditCarrierToManagedbRecord($account);
			
			//wish?????????????????????????????????id
			if( in_array($account->carrier_code,array("lb_wishyou","lb_chukouyi","lb_chukouyiOversea","lb_newwinit","lb_4pxNew")) && empty($id))
			{
				return self::output(array(), 0, $account->id);
			}
			else
			    return self::output(array(), 0, '????????????.');
		}else{
			return self::output(array(), 1, '????????????.');
		}
	}
	
	/**
	 * ????????????????????????
	 *
	 * @param	$id	??????id ??????sys_carrier_account?????????id
	 * @return Array
	 * Array
		 (
			 [response] => Array
			 (
				 [code] => 0
				 [msg] => '????????????.'
				 [data] => Array
				 (
				 )
			 )
		 )
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/04				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function carrierUserAccountDelById($id){
		if(empty($id)){
			return self::output(array(), 1, '????????????,??????????????????id');
		}
		
		$account = SysCarrierAccount::find()->where(['id'=>$id,'is_del'=>0])->one();
		
		if($account == null){
			return self::output(array(), 1, '????????????,??????????????????');
		}
		
		$countShippingService = SysShippingService::find()->where(['carrier_account_id'=>$id,'is_del'=>0,'is_used'=>1])->count();
		
		if($countShippingService > 0){
			return self::output(array(), 1, '????????????,?????????????????????????????????.');
		}
		
		$account->is_del = 1;
		
		if($account->save()){
			return self::output(array(), 0, '????????????.');
		}else{
			return self::output(array(), 1, '????????????.');
		}
	}
	
	/**
	 * ?????????????????? ???????????????
	 *
	 * @param	$id	??????id ??????sys_carrier_account?????????id
	 * @return Array
	 * Array
		 (
			 [response] => Array
			 (
				 [code] => 0
				 [msg] => '??????????????????.'
				 [data] => Array
				 (
				 )
			 )
		 )
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/07				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function carriserUserAccountSetDefault($id){
		if(empty($id)){
			return self::output(array(), 1, '????????????,??????????????????id');
		}
		
		try{
			$account = SysCarrierAccount::find()->where(['id'=>$id,'is_del'=>0])->one();
			
			if($account == null){
				return self::output(array(), 1, '????????????,??????????????????');
			}
			
			$accountsArr = SysCarrierAccount::find()->where(['carrier_code'=>$account->carrier_code,'is_del'=>0])->all();
			
			//???????????????????????????????????????????????????
			foreach ($accountsArr as $accountOne){
				if(($accountOne->id <> $id) && ($accountOne->is_default == 1)){
					$accountOne->is_default = 0;
					$accountOne->save(false);
				}
			}
			
			$account->is_default = 1;
			$account->save(false);
			
			return self::output(array(), 0, '??????????????????.');
		}catch(\Exception $ex){
			return self::output(array(), 1, '??????????????????.');
		}
	}
	
	/**
	 * ????????????????????????????????????
	 *
	 * @param	$addressId	??????id ??????carrier_user_address?????????id	??????
	 * @param	$type	???????????? 0:???????????????????????? 1:???????????? 2:????????????
	 * @param	$carrier_code	???????????????	?????????
	 * @return Array
	 * Array
		 (
			 [response] => Array
			 (
				 [code] => 0
				 [msg] => ''
				 [data] => Array
	                (
	                    [id] => 1
	                    [carrier_code] => lb_CNE
	                    [type] => 0
	                    [address_name] => 
	                    [is_default] => 0
	                    [address_params] => Array
	                    	(
	                    		[shippingfrom] => (
			                            [contact] => 
			                            [company] => 
			                            [phone] => 
			                            [mobile] => 
			                            [fax] => 
			                            [email] => 
			                            [country] => 
			                            [province] => 
			                            [city] => 
			                            [district] => 
			                            [postcode] => 
			                            [street] => 
			                            [contact_en] => 
			                            [company_en] => 
			                            [province_en] => 
			                            [city_en] => 
			                            [district_en] => 
			                            [street_en] => 
			                    ),
			                    [pickupaddress] => (
		                    			[contact] => 
							            [company] => 
							            [country] => 
							            [province] =>
							            [city] => 
							            [district] =>
							            [street] => 
							            [postcode] =>
							            [mobile] => 
							            [phone] => 
							            [email] => 
	                    		),
	                    		[returnaddress] => (
		                    			[contact] => 
							            [company] => 
							            [country] => 
							            [province] =>
							            [city] => 
							            [district] =>
							            [street] => 
							            [postcode] =>
							            [mobile] => 
							            [phone] => 
							            [email] => 
	                    		)
	                    	),
	                )
			 )
		 )
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/07				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCarrierAccountAdderssById($addressId, $type = 0, $carrier_code){
		if((empty($type)) && (empty($carrier_code))){
			return self::output(array(), 1, '?????????????????????.');
		}
		
		if(($type == 1) && (empty($addressId))){
			return self::output(array(), 1, '????????????,addressId?????????.');
		}
		
		if(empty($addressId)){
			$carrierAddress = Array(
					'id' => 0,
					'carrier_code'=>$carrier_code,
					'type'=>$type,
					'address_name'=>'',
					'is_default'=>0,
					'address_params'=>array(
							'shippingfrom' => array(),
					),
			);
		}else{
			$carrierAddress = CarrierUserAddress::find()->where(['id'=>$addressId, 'type'=>$type, 'is_del'=>0])->asArray()->all();
			
			if(count($carrierAddress) == 0){
				return self::output(array(), 1, '????????????,?????????ID????????????????????????.');
			}
			
			self::StrToUnserialize($carrierAddress,array('address_params'));
			
			$carrierAddress = $carrierAddress[0];
			
			if($type == 0){
				if($carrierAddress['carrier_code'] != $carrier_code){
					return self::output(array(), 1, '????????????,??????id????????????????????????.');
				}
			}
		}
		
		//??????api????????????????????????????????????????????????????????????
		if((empty($type)) && (!empty($carrier_code))){
			if(substr($carrier_code, 0, 3) == 'lb_'){
				$sysCarrier = SysCarrier::find()->select(['carrier_code','address_list'])->where(['carrier_code'=>$carrier_code])->one();
				
				if($sysCarrier == null){
					return self::output(array(), 1, '??????????????????????????????????????????????????????.');
				}
				
				if(count($sysCarrier->address_list) > 0){
					foreach ($sysCarrier->address_list as $addressType){
						if(in_array($addressType, array('pickupaddress','returnaddress'))){
							if(!isset($carrierAddress['address_params'][$addressType])){
								$carrierAddress['address_params'][$addressType] = array();
							}
						}
					}
				}
			}else{
				$sysCarrier = SysCarrierCustom::find()->select(['carrier_code','address_list'])->where(['carrier_code'=>$carrier_code])->one();
				
				if($sysCarrier == null){
					return self::output(array(), 1, '?????????????????????????????????????????????.');
				}
				
				if(!isset($carrierAddress['address_params']['pickupaddress'])){
					$carrierAddress['address_params']['pickupaddress'] = array();
				}
				
				if(!isset($carrierAddress['address_params']['returnaddress'])){
					$carrierAddress['address_params']['returnaddress'] = array();
				}
			}
		}
		
		return self::output($carrierAddress, 0, '');
	}
	
	/**
	 * ?????????????????????????????????????????????
	 *
	 * @param	$type	???????????? 0:???????????????????????? 1:???????????? 2:????????????	??????
	 * @param	$carrier_code	???????????????	?????????
	 * @return Array
	 * Array
		(
		    [response] => Array
		        (
		            [code] => 0
		            [msg] => 
		            [data] => Array
		                (
		                    [type] => 1
		                    [list] => Array
		                        (
		                            [2] => test????????????
		                            [3] => test????????????2
		                        )
		                )
		        )
		)
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/08				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCarrierAddressNameArrByType($type, $carrier_code = ''){
		if($type == 0){
			$carrierAddressAll = CarrierUserAddress::find()->select(['id','address_params'])->where(['type'=>$type, 'is_del'=>0, 'carrier_code'=>$carrier_code])->all();
			
			$carrierAddressArr = array();
			
			foreach ($carrierAddressAll as $carrierAddress){
				$tmpStreet = empty($carrierAddress->address_params['shippingfrom']['street']) ? $carrierAddress->id : $carrierAddress->address_params['shippingfrom']['street'];
				
				$carrierAddressArr[$carrierAddress->id] = empty($carrierAddress->address_params['shippingfrom']['street']) ? $tmpStreet : $carrierAddress->address_params['shippingfrom']['street'];
			}
			
// 			$carrierAddressArr = Helper_Array::toHashmap($carrierAddressArr, 'id', 'address_name');
		}else{
			$carrierAddressArr = CarrierUserAddress::find()->select(['id','address_name'])->where(['type'=>$type, 'is_del'=>0])->asArray()->all();
			$carrierAddressArr = Helper_Array::toHashmap($carrierAddressArr, 'id', 'address_name');
		}
		
		return self::output(array('type'=>$type, 'list'=>$carrierAddressArr), 0, '');
	}
	
	/**
	 * ??????????????????
	 *
	 * @param	$addressParams	??????
	 * Array
		 (
			 [id] => 1
			 [carrier_code] => lb_CNE
			 [type] => 0
			 [address_name] =>
			 [is_default] => 0
			 [address_params] => Array
			 (
                    		[shippingfrom] => (
		                            [contact] => 
		                            [company] => 
		                            [phone] => 
		                            [mobile] => 
		                            [fax] => 
		                            [email] => 
		                            [country] => 
		                            [province] => 
		                            [city] => 
		                            [district] => 
		                            [postcode] => 
		                            [street] => 
		                            [contact_en] => 
		                            [company_en] => 
		                            [province_en] => 
		                            [city_en] => 
		                            [district_en] => 
		                            [street_en] => 
		                    ),
		                    [pickupaddress] => (
	                    			[contact] => 
						            [company] => 
						            [country] => 
						            [province] =>
						            [city] => 
						            [district] =>
						            [street] => 
						            [postcode] =>
						            [mobile] => 
						            [phone] => 
						            [email] => 
                    		),
                    		[returnaddress] => (
	                    			[contact] => 
						            [company] => 
						            [country] => 
						            [province] =>
						            [city] => 
						            [district] =>
						            [street] => 
						            [postcode] =>
						            [mobile] => 
						            [phone] => 
						            [email] => 
                    		)
                    	)
			 [isSaveCommonAddress] => 0	??????????????????????????????????????????	0:???		1:???
		 )
	 * 
	 * @return Array
	 * Array
		 (
			 [response] => Array
			 (
				 [code] => 0	0:????????????,1:????????????
				 [msg] => '????????????.'
				 [data] => Array
	                (
	                )
			 )
		 )
	 * 
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/08				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function saveCarrierAddressInfo($addressParams){
		if(empty($addressParams['id'])){
			$carrierAddress = new CarrierUserAddress();
			
			$carrierAddress->carrier_code = $addressParams['carrier_code'];
			$carrierAddress->type = $addressParams['type'];
		}else{
			$carrierAddress = CarrierUserAddress::find()->where(['id'=>$addressParams['id'], 'type'=>$addressParams['type'], 'is_del'=>0])->one();
			
			if($carrierAddress == null){
				return self::output(array(), 1, '??????????????????????????????????????????????????????');
			}
		}
		
		if(($carrierAddress->type == 0) && ($addressParams['isSaveCommonAddress'] == 1)){
			if(empty($addressParams['address_name'])){
				return self::output(array(), 1, '????????????????????????????????????????????????.');
			}
			
			$tmpCount = CarrierUserAddress::find()->where(['address_name'=>$addressParams['address_name'],'type'=>'1','is_del'=>0])->count();
			
			if ($tmpCount > 0){
				return self::output(array(), 1, '??????????????????.');
			}
		}
		
// 		$carrierAddress->address_name = $addressParams['address_name'];
		$carrierAddress->address_params = $addressParams['address_params'];
		$carrierAddress->is_default = $addressParams['is_default'];
		
		try{
			if($carrierAddress->save(false)){
				//??????????????????????????????????????????
				if(($carrierAddress->type == 0) && ($addressParams['is_default'] == 1)){
					$command = Yii::$app->subdb->createCommand("update carrier_user_address set is_default=0 where type = 0 and carrier_code = :carrier_code and id <> :id and is_default = 1 and is_del = 0 ");
					$command->bindValue(':carrier_code', $addressParams['carrier_code'], \PDO::PARAM_STR);
					$command->bindValue(':id', $carrierAddress->id, \PDO::PARAM_STR);
					$affectRows = $command->execute();
				}
					
				if(($carrierAddress->type == 0) && ($addressParams['isSaveCommonAddress'] == 1)){
					$carrierCommonAddress = new CarrierUserAddress();
			
					$carrierCommonAddress->carrier_code = $carrierAddress->carrier_code;
					$carrierCommonAddress->address_name = $addressParams['address_name'];
					$carrierCommonAddress->type = 1;
					$carrierCommonAddress->address_params = $addressParams['address_params'];
			
					$carrierCommonAddress->save(false);
				}
				
				return self::output(array(), 0, '????????????.');
			}else{
				return self::output(array(), 1, '????????????.');
			}
		}catch(\Exception $ex){
			return self::output(array(), 1, '????????????,'.print_r($ex));
		}
	}
	
	/**
	 * ?????????????????????????????????
	 *
	 * @param	$type	???????????????????????????	0:??????	1:?????????		2:?????????+??????+???????????????		3:???????????????		4:??????????????????Excel??????	5:???????????????????????????????????????
	 * @param	$is_active	1:??????	0:??????	2:??????????????????????????????????????????     ???????????????:??????&&??????
	 * @return Array
	 Array
	 (
		 [lb_369guojikuaidirtbcompany] => 369????????????
		 [lb_4px] => ?????????
		 [lb_4pxOversea] => ?????????(?????????)
		 [lb_alionlinedelivery] => ?????????????????????
		 [lb_baishiyundartbcompany] => ????????????
		 [lb_bangliguojirtbcompany] => ????????????
		 [lb_beijingyichengrtbcompany] => ????????????
		 [lb_birdsysOversea] => ????????????(?????????)
		 [lb_chukouyi] => ?????????
	 )
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/08				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getOpenCarrierArr($type = 0,$is_active = -1, $is_close_type = false){
		$check = false;
		if($is_active == 1 || $is_active == 0){
			$check = true;
		}
		if(($type == 2) || ($type == 3)){
			$carrierCustom = Helper_Array::toHashmap(
					SysCarrierCustom::find()->select(['carrier_code','carrier_name'])
					->where(($check)?['is_used'=>$is_active]:'')->andWhere(($type == 3) ? ['warehouse_id'=>'-1'] : '')->asArray()->all(), 'carrier_code','carrier_name');
			
			if($type == 3)
				return $carrierCustom;
		}
		
		if(($type == 4)){
			$carrierCustom = Helper_Array::toHashmap(SysCarrierCustom::find()->select(['carrier_code','carrier_name'])->where(['carrier_type'=>1])->andWhere(($check)?['is_used'=>$is_active]:'')->asArray()->all(), 'carrier_code','carrier_name');
			return $carrierCustom;
		}
		
		if(($type == 5)){
			$carrierCustom = Helper_Array::toHashmap(SysCarrierCustom::find()->select(['carrier_code','carrier_name'])->where(['carrier_type'=>0])->andWhere(($check)?['is_used'=>$is_active]:'')->asArray()->all(), 'carrier_code','carrier_name');
			return $carrierCustom;
		}
		
		$query = SysCarrier::find()->select(['carrier_code','carrier_name','help_url'])->where(['is_active'=>1]);
		$query2 = CarrierUseRecord::find()->select(['carrier_code','is_active'])->where(['is_del'=>0]);
		if($is_active == 1 || $is_active == 0){
			$query2->andWhere(['is_active'=>$is_active]);
		}
		$openCarrierListArr = $query2->asArray()->all();
		
		if($is_close_type == true){
			$tmpCloseTypeArr = Helper_Array::toHashmap($openCarrierListArr, 'carrier_code', 'is_active');
		}
		
		$openCarrierListArr = Helper_Array::toHashmap($openCarrierListArr, 'carrier_code', 'carrier_code');
		
		$openCarrierListArr2 = Warehouse::find()->select(['carrier_code'])->where(['is_active'=>'Y','is_oversea'=>1])->groupBy('carrier_code')->asArray()->all();
		$openCarrierListArr2 = Helper_Array::toHashmap($openCarrierListArr2, 'carrier_code', 'carrier_code');
		
		if(empty($type)){
			$query->andWhere(['carrier_type'=>0]);
		}
		
		if($type == 1){
			$query->andWhere(['carrier_type'=>1]);
		}
				
		if($is_active!=2)
			$query->andWhere(['in','carrier_code',$openCarrierListArr+$openCarrierListArr2]);
				
		if($is_close_type == true){
			$tmpResult = $query->asArray()->all();
			
			foreach ($tmpResult as $tmpResultVal){
				$carrierContactArr=CarrierOpenHelper::getCarrierContact($tmpResultVal['carrier_code']);
				if($is_active==2)
					$result[$tmpResultVal['carrier_code']] = array('carrier_name'=>$tmpResultVal['carrier_name'],'is_active'=>isset($tmpCloseTypeArr[$tmpResultVal['carrier_code']])?$tmpCloseTypeArr[$tmpResultVal['carrier_code']]:-1,'help_url'=>$tmpResultVal['help_url'],'carrierContactArr'=>$carrierContactArr);
				else
					$result[$tmpResultVal['carrier_code']] = array('carrier_name'=>$tmpResultVal['carrier_name'],'is_active'=>$tmpCloseTypeArr[$tmpResultVal['carrier_code']],'help_url'=>$tmpResultVal['help_url'],'carrierContactArr'=>$carrierContactArr);
			}

			if(!empty($result)){
				foreach ($result as $resultKey => $resultVal) {
					$tmpis_active[$resultKey] = $resultVal['is_active'];
				}
					
				array_multisort($tmpis_active, SORT_DESC, $result);
			}else{
				$result = array();
			}
		}else{
			$result = $query->asArray()->all();
			$result = Helper_Array::toHashmap($result, 'carrier_code', 'carrier_name');
		}
		
		if($type == 2){
			$result = $result+$carrierCustom;
		}

		return $result;
	}
	
	/**
	 * ??????????????????
	 *
	 * @param	$id	carrier_user_address????????????ID	??????
	 * @return Array
	 * Array
		 (
		 [response] => Array
		 (
			 [code] => 0	0:????????????,1:????????????
			 [msg] => '????????????.'
			 [data] => Array
			 (
			 )
		 )
	 )
	 *
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/08				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function carrierAddressDelById($id){
		if(empty($id)){
			return self::output(array(), 1, '????????????,????????????.');
		}
		
		$carrierAddress = CarrierUserAddress::find()->where(['id'=>$id, 'is_del'=>0])->one();
		
		if($carrierAddress == null){
			return self::output(array(), 1, '????????????,??????????????????.');
		}
		
		$carrierAddress->is_del = 1;
		
		if($carrierAddress->save(false)){
			return self::output(array(), 0, '????????????.');
		}else{
			return self::output(array(), 1, '????????????.');
		}
	}
	
	/**
	 * ????????????????????????
	 *
	 * @param	$id	carrier_user_address????????????ID	??????
	 * @return Array
	 * Array
	 (
		 [response] => Array
		 (
			 [code] => 0	0:????????????,1:????????????
			 [msg] => '?????????????????????.'
			 [data] => Array
			 (
			 )
		 )
	 )
	 *
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/08				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function carriserAddressSetDefault($id){
		if(empty($id)){
			return self::output(array(), 1, '????????????,????????????.');
		}
		
		$carrierAddress = CarrierUserAddress::find()->where(['id'=>$id, 'is_del'=>0])->one();
		
		if($carrierAddress == null){
			return self::output(array(), 1, '????????????,??????????????????.');
		}
		
		$command = Yii::$app->subdb->createCommand("update carrier_user_address set is_default=0 where type = 0 and carrier_code = :carrier_code and id <> :id and is_default = 1 and is_del = 0 ");
		$command->bindValue(':carrier_code', $carrierAddress->carrier_code, \PDO::PARAM_STR);
		$command->bindValue(':id', $id, \PDO::PARAM_STR);
		$affectRows = $command->execute();
		
		$carrierAddress->is_default = 1;
		
		if($carrierAddress->save(false)){
			return self::output(array(), 0, '?????????????????????.');
		}else{
			return self::output(array(), 1, '?????????????????????.');
		}
	}
	
	/**
	 * ??????????????????????????????
	 *
	 * @param	$id	????????????sys_shipping_service??????ID	??????
	 * @param	$carrier_code	???????????????	??????
	 * @return Array
	 * Array
		(
		    [response] => Array
		        (
		            [code] => 0
		            [msg] => 
		            [data] => Array
		                (
		                    [serviceID] => 618
		                    [carrier_name] => CNE
		                    [service_name] => CNE-cne001-CNE????????????
		                    [shipping_method_code] => CNE????????????
		                    [web] => http://www.17track.net
		                    [transport_service_type] => 0
		                    [aging] => 
		                    [is_tracking_number] => 0
		                    [accountID] => 19
		                    [carrierAccountList] => Array
		                        (
		                            [19] => cne001
		                            [35] => CNE01
		                        )
		
		                    [carrierParams] => Array
		                        (
		                            [0] => Array
		                                (
		                                    [carrier_param_key] => nItemType
		                                    [carrier_param_name] => ????????????
		                                    [carrier_param_value] => Array
		                                        (
		                                            [1] => ??????
		                                            [0] => ??????
		                                            [2] => ?????????
		                                        )
		
		                                    [display_type] => dropdownlist
		                                    [param_value] => 
		                                    [carrier_is_required] => 0
		                                    [carrier_is_encrypt] => 0
		                                )
		                            [1] => Array
		                                (
		                                    [carrier_param_key] => nPayWay
		                                    [carrier_param_name] => ????????????
		                                    [carrier_param_value] => Array
		                                        (
		                                            [0] => ??????
		                                            [1] => ??????
		                                            [2] => ??????
		                                        )
		                                    [display_type] => dropdownlist
		                                    [param_value] => 
		                                    [carrier_is_required] => 0
		                                    [carrier_is_encrypt] => 0
		                                )
		                        )
		                    [is_show_address] => 1
		                    [common_address_id] => 0
		                    [print_params] => Array
		                        (
		                            [label_api] => 0
		                            [label_littleboss] => Array
		                                (
		                                    [is_selected] => 0
		                                    [carrier_lable] => 0
		                                    [declare_lable] => 0
		                                    [items_lable] => 0
		                                )
		
		                            [label_custom] => Array
		                                (
		                                    [is_selected] => 0
		                                    [carrier_lable] => 0
		                                    [declare_lable] => 0
		                                    [items_lable] => 0
		                                )
		
		                            [label_customCarrierArr] => Array
		                                (
		                                    [1] => ?????????:4px?????????????????????10cm??10cm
		                                )
		                            [label_customDeclareArr] => Array
		                                (
		                                )
		                            [label_customItemsArr] => Array
		                                (
		                                )    
		                        )
		                    [proprietary_warehouse] => Array
		                        (
		                            [0] => Array
		                                (
		                                    [name] => (????????????)
		                                    [is_selected] => 0
		                                )
		
		                            [1] => Array
		                                (
		                                    [name] => (????????????)
		                                    [is_selected] => 0
		                                )
		                        )
		                    [declaration_max_value] => 0.00
		                    [declaration_max_currency] => USD
		                    [declaration_max_weight] => 0.0000
		                    [service_code] => Array
		                        (
		                            [ebay] => Array
		                                (
		                                    [val] => dhl
		                                    [optional_value] => stdClass Object
		                                        (
		                                            [DeutschePost] => Deutsche Post
		                                            [DHL] => DHL service
		                                        )
		                                )
		                            [cdiscount] => Array
		                                (
		                                    [val] => ba
		                                    [optional_value] => 
		                                )
		                        )
		                    [customer_number_config] => Array
		                        (
		                            [ebay] => Array
		                                (
		                                    [val] => 
		                                    [optional_value] => Array
		                                        (
		                                            [] => ??????
		                                            [serial_random_6number] => ?????????+???????????????
		                                            [serial_date] => ?????????+??????
		                                            [platform_id] => ???????????????
		                                            [platform_id_random_6number] => ???????????????+???????????????
		                                            [platform_id_date] => ???????????????+??????
		                                        )
		                                )
		                            [aliexpress] => Array
		                                (
		                                    [val] => 
		                                    [optional_value] => Array
		                                        (
		                                            [] => ??????
		                                            [serial_random_6number] => ?????????+???????????????
		                                            [serial_date] => ?????????+??????
		                                            [platform_id] => ???????????????
		                                            [platform_id_random_6number] => ???????????????+???????????????
		                                            [platform_id_date] => ???????????????+??????
		                                        )
		                                )
		                        )
		                )
		        )
		)
	 *
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/16				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCarrierShippingServiceUserById($id, $carrier_code,$warehouse_id=-1,$shippingcode='',$thirdcode='',$is_used=-1){
// 		if(empty($id)){
// 			return self::output(array(), 1, '????????????????????????ID.');
// 		}
		
		if(empty($carrier_code)){
			return self::output(array(), 1, '?????????????????????.');
		}

		//??????$carrier_code????????????????????????
		$bindingCarrierAccounts = self::getBindingCarrierAccount($carrier_code,'',$warehouse_id,$is_used);
		$bindingCarrierAccounts = Helper_Array::toHashmap($bindingCarrierAccounts, 'id', 'carrier_name');

		$carrierParams = SysCarrierParam::find()->where(['carrier_code'=>$carrier_code,'type'=>1])->orderBy('sort asc')->asArray()->all();
		
		$sysCarrierOne = SysCarrier::find()->select(['carrier_code','address_list'])->where(['carrier_code' => $carrier_code])->asArray()->one();

		if(count($sysCarrierOne) == 0){
			return self::output(array(), 1, '???????????????????????????.');
		}
		
		//???????????????????????????
		$query = SysShippingService::find();
		$query->andWhere(['id'=>$id]);
		$shippingServiceArr = $query->asArray()->all();

		if($warehouse_id==-1 && empty($shippingServiceArr)){
			$accountAll = SysCarrierAccount::find()->where(['carrier_code'=>$carrier_code,'is_del'=>0])->orderBy('create_time')->asArray()->all();
			$sys_method = SysShippingMethod::find()->where(['carrier_code'=>$carrier_code,'shipping_method_code'=>$shippingcode,'third_party_code'=>$thirdcode])->asArray()->one();
			// 			print_r($sys_method);die;
			$shippingServiceArr[0]['third_party_code']=$thirdcode;
			$shippingServiceArr[0]['carrier_code']=$carrier_code;
			$shippingServiceArr[0]['shipping_method_code']=$shippingcode;
			$shippingServiceArr[0]['id']='0';
			$shippingServiceArr[0]['service_name']=$accountAll[0]['carrier_name']."-".$sys_method['shipping_method_name'];
			$shippingServiceArr[0]['web']=self::getServiceUrlByCarrierCode($carrier_code);
			$shippingServiceArr[0]['transport_service_type']='0';
			$shippingServiceArr[0]['aging']='';
			$shippingServiceArr[0]['is_tracking_number']='0';
			$shippingServiceArr[0]['carrier_account_id']=$accountAll[0]['id'];
			$shippingServiceArr[0]['common_address_id']='';
			$shippingServiceArr[0]['print_type']=$sys_method['is_api_print']==1?'0':($sys_method['is_print']==1?'1':'2');
			$shippingServiceArr[0]['carrier_params']='';
			$shippingServiceArr[0]['print_params']=$sys_method['print_params'];
			$shippingServiceArr[0]['proprietary_warehouse']='';
			$shippingServiceArr[0]['declaration_max_value']='0.00';
			$shippingServiceArr[0]['declaration_max_currency']='USD';
			$shippingServiceArr[0]['declaration_max_weight']='0.0000';
			$shippingServiceArr[0]['tracking_upload_config']='';
		}
		else
			self::StrToUnserialize($shippingServiceArr,array('carrier_params','ship_address','return_address','service_code','address','custom_template_print','print_params','proprietary_warehouse','customer_number_config'));

		if(count($shippingServiceArr) == 0){
			return self::output(array(), 1, '?????????????????????ID??????.');
		}else{
			$shippingServiceArr = $shippingServiceArr[0];
		}
		
		if($shippingServiceArr['third_party_code'] == ''){
			$tmp_third_party_code = '';
		}else{
			$tmp_third_party_code = $shippingServiceArr['third_party_code'];
		}
		
		$sys_method = SysShippingMethod::find()->where(['carrier_code'=>$shippingServiceArr['carrier_code'],'shipping_method_code'=>$shippingServiceArr['shipping_method_code'],'third_party_code'=>$tmp_third_party_code])->asArray()->one();
		
		if(count($sys_method) == 0){
			return self::output(array(), 1, '???????????????????????????.');
		}
		
		$result = array();
		
		$result['serviceID'] = $shippingServiceArr['id'];
		$result['carrier_name'] = self::getCarrierApiList()[$carrier_code];
		$result['service_name'] = $shippingServiceArr['service_name'];
		$result['shipping_method_code'] = $shippingServiceArr['shipping_method_code'];
		$result['web'] = $shippingServiceArr['web'];
		$result['transport_service_type'] = $shippingServiceArr['transport_service_type'];
		$result['aging'] = $shippingServiceArr['aging'];
		$result['is_tracking_number'] = $shippingServiceArr['is_tracking_number'];
		$result['accountID'] = $shippingServiceArr['carrier_account_id'];
		$result['carrierAccountList'] = $bindingCarrierAccounts;
		
		//?????????????????????????????????
		$result['platform_service_code'] = $sys_method['service_code'];

		foreach ($carrierParams as $carrierParamone){
			$result['carrierParams'][] = array(
					'carrier_param_key' => $carrierParamone['carrier_param_key'],
					'carrier_param_name' => $carrierParamone['carrier_param_name'],
					'carrier_param_value' => unserialize($carrierParamone['carrier_param_value']),
					'display_type' => $carrierParamone['display_type'],
					'param_value' => isset($shippingServiceArr['carrier_params'][$carrierParamone['carrier_param_key']]) ? $shippingServiceArr['carrier_params'][$carrierParamone['carrier_param_key']] : '',
					'carrier_is_required' => $carrierParamone['is_required'],
					'carrier_is_encrypt' => $carrierParamone['is_encrypt'],
					'ui_type'=>$carrierParamone['ui_type'],
			        'is_hidden'=>$carrierParamone['is_hidden'],
			);
		}
		
		$result['is_show_address'] = empty($sysCarrierOne['address_list']) ? 0 : 1;
		$result['common_address_id'] = $shippingServiceArr['common_address_id'];
		$result['commonAddressArr'] = self::getCarrierAddressNameArrByType(0, $carrier_code)['response']['data']['list'];

		if($sys_method['is_api_print'] == 1){
			$result['print_params']['label_api'] = empty($shippingServiceArr['print_type']) ? 1 : 0;
		}else{
			unset($result['print_params']['label_api']);
		}

		if($sys_method['is_print'] == 1){
			$sys_method['print_params'] = json_decode($sys_method['print_params'],true);
			$sysLable = array();
			foreach ($sys_method['print_params'] as $tmpPrintparams){
				if ($tmpPrintparams == 'label_address')
					$sysLable[$tmpPrintparams] = '?????????';
				if ($tmpPrintparams == 'label_declare')
					$sysLable[$tmpPrintparams] = '?????????';
				if ($tmpPrintparams == 'label_items')
					$sysLable[$tmpPrintparams] = '?????????';
			}
			
			$result['print_params']['label_littleboss'] = empty($shippingServiceArr['print_params']['label_littleboss']) ? array() : $shippingServiceArr['print_params']['label_littleboss'];
			$result['print_params']['label_littlebossOptionsArr'] = $sysLable;
		}else{
			unset($result['print_params']['label_littleboss']);
		}
		
		$result['print_params']['label_custom'] = empty($shippingServiceArr['print_params']['label_custom']) ? array('carrier_lable'=>0,'declare_lable'=>0,'items_lable'=>0) : $shippingServiceArr['print_params']['label_custom'];
		
		$result['print_params']['label_custom_new'] = empty($shippingServiceArr['print_params']['label_custom_new']) ? array('carrier_lable'=>0,'declare_lable'=>0,'items_lable'=>0) : $shippingServiceArr['print_params']['label_custom_new'];
		
		$result['print_params']['label_littlebossOptionsArrNew']=empty($shippingServiceArr['print_params']['label_littlebossOptionsArrNew'])?array('carrier_lable'=>'','declare_lable'=>'','items_lable'=>'','key'=>'0'):$shippingServiceArr['print_params']['label_littlebossOptionsArrNew'];
		
		$result['print_type'] = @$shippingServiceArr['print_type'];
		
		//?????????????????????
		$templates = CrTemplate::find()->select(['template_id','template_name','template_type','template_version'])->where(['in','template_type',array('?????????','?????????','?????????')])->asArray()->all();
		$templateAddress = array();
		$templateDeclare = array();
		$templateItems = array();
		
		$templateAddress2 = array();
		$templateDeclare2 = array();
		$templateItems2 = array();
		
		foreach ($templates as $templatesOne){
			if($templatesOne['template_type'] == '?????????'){
				if($templatesOne['template_version'] == 0)
					$templateAddress[$templatesOne['template_id']] = $templatesOne['template_name'];
				else 
					$templateAddress2[$templatesOne['template_id']] = $templatesOne['template_name'];
			}else if($templatesOne['template_type'] == '?????????'){
				if($templatesOne['template_version'] == 0)
					$templateDeclare[$templatesOne['template_id']] = $templatesOne['template_name'];
				else 
					$templateDeclare2[$templatesOne['template_id']] = $templatesOne['template_name'];
			}else if($templatesOne['template_type'] == '?????????'){
				if($templatesOne['template_version'] == 0)
					$templateItems[$templatesOne['template_id']] = $templatesOne['template_name'];
				else 
					$templateItems2[$templatesOne['template_id']] = $templatesOne['template_name'];
			}
		}
		
		$result['print_params']['label_customCarrierArr'] = $templateAddress;
		$result['print_params']['label_customDeclareArr'] = $templateDeclare;
		$result['print_params']['label_customItemsArr'] = $templateItems;
		
		$result['print_params']['label_custom_newCarrierArr'] = $templateAddress2;
		$result['print_params']['label_custom_newDeclareArr'] = $templateDeclare2;
		$result['print_params']['label_custom_newItemsArr'] = $templateItems2;
		
		//??????????????????
		$result['proprietary_warehouse'] = $shippingServiceArr['proprietary_warehouse'];
		$result['self_warehouse'] = InventoryHelper::getWarehouseOrOverseaIdNameMap(1, -1);
		
		$result['declaration_max_value'] = $shippingServiceArr['declaration_max_value'];
		$result['declaration_max_currency'] = $shippingServiceArr['declaration_max_currency'];
		$result['declaration_max_weight'] = $shippingServiceArr['declaration_max_weight'];

		//????????????
		$result['address'] = empty($shippingServiceArr['address']) ? array() : $shippingServiceArr['address'];
		
 		//????????????????????????
		$platformUseArr = PlatformAccountApi::getAllPlatformBindingSituation();
		unset($platformUseArr['customized']);

		//????????????????????????????????????????????????????????????
		foreach ($platformUseArr as $platformUseKey => $platformUseVal){
			if($platformUseVal == false){
				unset($shippingServiceArr['service_code'][$platformUseKey]);
				unset($shippingServiceArr['customer_number_config'][$platformUseKey]);
			}else if($platformUseVal == true)
			{
				//?????????????????????????????????????????????????????????
				switch ($platformUseKey){
					case 'ebay':
						$serviceData = json_decode(file_get_contents(Yii::getAlias('@web').'docs/ebayServiceCode.json'));
						$display_type = 'text';
						$carrier_order_id_mode = 'serial_random_6number';
						break;
					case 'aliexpress':
						$serviceData = \common\api\aliexpressinterface\AliexpressInterface_Helper::getShippingCodeNameMap();
						$display_type = 'dropdownlist';
						$carrier_order_id_mode = 'platform_id';
						break;
					case 'wish':
						$serviceData = \eagle\modules\order\helpers\WishOrderInterface::getShippingCodeNameMap();
						asort($serviceData);
						$display_type = 'dropdownlist';
						$carrier_order_id_mode = 'serial_random_6number';
						break;
					case 'amazon':
						$serviceData = \eagle\modules\amazon\apihelpers\AmazonApiHelper::getShippingCodeNameMap();
						asort($serviceData);
						$display_type = 'dropdownlist';
						if(!empty($shippingServiceArr['service_code'][$platformUseKey])){
							$serviceData[$shippingServiceArr['service_code'][$platformUseKey]] = $shippingServiceArr['service_code'][$platformUseKey];
						}
						$carrier_order_id_mode = 'platform_id';
						break;
					case 'dhgate':
						$serviceData = \eagle\modules\dhgate\apihelpers\DhgateApiHelper::getShippingCodeNameMap();
						$display_type = 'dropdownlist';
						$carrier_order_id_mode = 'platform_id';
						break;
					case 'lazada':
						$serviceData = \eagle\modules\lazada\apihelpers\LazadaApiHelper::getShippingCodeNameMap();
						$display_type = 'dropdownlist';
						$carrier_order_id_mode = 'platform_id';
						break;
					case 'linio':
						$serviceData = \eagle\modules\lazada\apihelpers\LazadaApiHelper::getLinioShippingCodeNameMap();
						$display_type = 'dropdownlist';
						$carrier_order_id_mode = 'serial_random_6number';
						break;
					case 'ensogo':
						$serviceData = \eagle\modules\order\helpers\EnsogoOrderInterface::getShippingCodeNameMap();
						$display_type = 'dropdownlist';
						$carrier_order_id_mode = 'platform_id';
						break;
					case 'priceminister':
						$serviceData = \eagle\modules\order\helpers\PriceministerOrderInterface::getShippingCodeNameMap();
						$display_type = 'dropdownlist';
						$carrier_order_id_mode = 'platform_id';
						break;
					case 'cdiscount':
						$serviceData = \eagle\modules\order\helpers\CdiscountOrderInterface::getShippingCodeNameMap();
						$display_type = 'dropdownlist';
						if(!empty($shippingServiceArr['service_code'][$platformUseKey])){
							$serviceData[$shippingServiceArr['service_code'][$platformUseKey]] = $shippingServiceArr['service_code'][$platformUseKey];
						}
						$carrier_order_id_mode = 'platform_id';
						break;
					default:
						$serviceData = '';
						$display_type = 'text';
						$carrier_order_id_mode = 'platform_id';
						break;
				}
				
				$result['service_code'][$platformUseKey] = array(
						'val'=>empty($shippingServiceArr['service_code'][$platformUseKey]) ? ShippingServiceHelper::getRecommendConfigByShippingMapping($platformUseKey) : $shippingServiceArr['service_code'][$platformUseKey],
						'optional_value'=>$serviceData,
						'display_type'=>$display_type,
				);
				if($platformUseKey == 'shopee'){
					$result['service_code'][$platformUseKey]['val'] = empty($result['service_code'][$platformUseKey]['val']) ? 'other' : $result['service_code'][$platformUseKey]['val'];
				}
				
				if(in_array($platformUseKey, array('linio'))){//??????????????????????????????????????????2017-10-31 lgw
					$result['customer_number_config'][$platformUseKey] = array(
							'val'=>$carrier_order_id_mode,
							'optional_value'=>array(
									'serial_random_6number'=>'?????????+???????????????',
							)
					);
				}
				else{
					$result['customer_number_config'][$platformUseKey] = array(
							'val'=>empty($shippingServiceArr['customer_number_config'][$platformUseKey]) ? $carrier_order_id_mode : $shippingServiceArr['customer_number_config'][$platformUseKey],
							'optional_value'=>array(
									''=>'??????',
									'serial_random_6number'=>'?????????+???????????????',
									'serial_date'=>'?????????+??????',
									'platform_id'=>'???????????????',
									'platform_id_random_6number'=>'???????????????+???????????????',
									'platform_id_date'=>'???????????????+??????',
							)
					);
				}

				
				if(in_array($platformUseKey, array('ebay','amazon'))){
					$tmp_tracking_upload_config = $shippingServiceArr['tracking_upload_config'];
					$tmp_tracking_upload_config = json_decode($tmp_tracking_upload_config, true);
					
					$result['tracking_upload_config'][$platformUseKey] = array(
						'val' => empty($tmp_tracking_upload_config[$platformUseKey]) ? 0 : $tmp_tracking_upload_config[$platformUseKey],
						'optional_value' => array(0=>'?????????????????????', 1=>'?????????????????????')
					);
					
					if($platformUseKey == 'amazon'){
						$result['tracking_upload_config'][$platformUseKey]['optional_value'] += array('2'=>'????????????????????????????????????');
					}
				}
			}
		}
		
// 		print_r($shippingServiceArr);
// 		print_r($result);
// 		exit;
		
		return self::output($result, 0, '');
	}
	
	/**
	 * ??????????????????????????????
	 *
	 * @param	$id	????????????sys_shipping_service??????ID	??????
	 * @param	$carrier_code	???????????????	??????
	 * @param	$type	????????????	????????????		open:??????	copy:??????	edit:??????	add:??????
	 * @param	$is_oversea ???????????????
	 * @param	$params	array()	??????	???????????????????????????
	 * 	$params = array(
	  		[carrierParams] => Array
                (
                    [nItemType] => 
                    [labelstyle] => labelA46_0
                )
            [service_name] => 'CNE-cne001-CNE????????????'
            [service_code] => Array
                (
                    [ebay] => dhl
                    [amazon] => DHL
                    [aliexpress] => DHL
                )
            [web] => http://www.17track.net
            [transport_service_type] => 0
            [aging] => 
            [is_tracking_number] => 0
            [proprietary_warehouse] => Array
            	(
            		[0] => 1
                    [1] => 0
            	)
            [declaration_max_value] => 0.00
            [declaration_max_currency] => USD
            [declaration_max_weight] => 0.0000
            [customer_number_config] => Array
                (
                    [ebay] => serial_random_6number
                    [aliexpress] => platform_id
                )
            [common_address_id] => 0
            [print_params] => Array
                (
                    [label_api] => 0
                    [label_littleboss] => Array
                          (
                               [is_selected] => 0
                               [carrier_lable] => 0
                               [declare_lable] => 0
                               [items_lable] => 0
                          )
                    [label_custom] => Array
                          (
                               [is_selected] => 0
                               [carrier_lable] => 0
                               [declare_lable] => 0
                               [items_lable] => 0
                          )
                )
            [accountID] => 2	?????????????????????????????????0??????
	 * 	)
	 * @return Array
	 * Array
		 (
			 [response] => Array
			 (
				 [code] => 0
				 [msg] => Array
				 	(
				 		'????????????'
				 	)
				 [data] => Array
					 (
					 )
			 )
		 )
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/18				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function saveCarrierShippingServiceUserById($id, $carrier_code, $type, $params){
		if(empty($params['service_name'])){
			return self::output(array(), 1, array('??????????????????,????????????.'));
		}

		if(substr($carrier_code, 0, 3) == 'lb_'){
			if(empty($id)){
				return self::output(array(), 1, array('????????????ID,????????????.'));
			}
		}else if(substr($carrier_code, 0, 3) != 'lb_'){
			if((empty($id)) && ($type == 'add')){
				
				$tmp_carrier_name = '';
				
				$carrierCustomOne = SysCarrierCustom::find()->where(['carrier_code'=>$carrier_code])->one();
				if($carrierCustomOne == null){
					return self::output(array(), 1, array('????????????.'));
				}
				$tmp_carrier_name = $carrierCustomOne->carrier_name;
				
				$shippingServiceOne = new SysShippingService();
				
				$shippingServiceOne->carrier_code = $carrier_code;
				$shippingServiceOne->carrier_account_id = empty($params['accountID']) ? 0 :$params['accountID'];
				$shippingServiceOne->create_time = time();
				$shippingServiceOne->shipping_method_name = $params['service_name'];
				$shippingServiceOne->carrier_name = $tmp_carrier_name;
				$shippingServiceOne->is_custom = 1;
			}
			
			if(($type != 'add') && (empty($id))){
				return self::output(array(), 1, array('?????????????????????ID.'));
			}
		}
		
		if(!empty($id)){
			$shippingServiceOne = SysShippingService::find()->where(['id'=>$id])->one();
			
			if($shippingServiceOne == null){
				return self::output(array(), 1, array('?????????????????????ID??????.'));
			}
			
			if($shippingServiceOne->carrier_code != $carrier_code){
				return self::output(array(), 1, array('????????????,??????????????????????????????.'));
			}
		}
		
		if($type == 'copy'){
			if(substr($carrier_code, 0, 3) != 'lb_'){
				if(empty($params['accountID'])){
					return self::output(array(), 1, array('???????????????id,????????????.'));
				}
					
				if($shippingServiceOne->carrier_account_id == $params['accountID']){
					return self::output(array(), 1, array('????????????,????????????????????????.'));
				}
			}
			
			$tmp = array();
			$tmp['shipping_method_code'] = $shippingServiceOne->shipping_method_code;
			$tmp['shipping_method_name'] = $shippingServiceOne->shipping_method_name;
			$tmp['carrier_name'] = $shippingServiceOne->carrier_name;
			$tmp['is_custom'] = $shippingServiceOne->is_custom;
			$tmp['third_party_code'] = $shippingServiceOne->third_party_code;
			$tmp['warehouse_name'] = $shippingServiceOne->warehouse_name;
			
			unset($shippingServiceOne);
			
			$shippingServiceOne = new SysShippingService();
			
			$shippingServiceOne->carrier_code = $carrier_code;
// 			$shippingServiceOne->carrier_account_id = empty($params['accountID']) ? 0 :$params['accountID'];
			$shippingServiceOne->create_time = time();
			$shippingServiceOne->shipping_method_code = $tmp['shipping_method_code'];
			$shippingServiceOne->shipping_method_name = $tmp['shipping_method_name'];
			$shippingServiceOne->carrier_name = $tmp['carrier_name'];
			$shippingServiceOne->is_copy = 1;
			$shippingServiceOne->is_custom = $tmp['is_custom'];
			$shippingServiceOne->third_party_code = $tmp['third_party_code'];
			$shippingServiceOne->warehouse_name = $tmp['warehouse_name'];
		}
		$shippingServiceOne->carrier_account_id = empty($params['accountID']) ? 0 :$params['accountID'];
		
		if ($shippingServiceOne->isNewRecord){
			$count = SysShippingService::find()->where(['service_name'=>$params['service_name'],'is_del'=>0])->count();
		}else{
			$count = SysShippingService::find()->where('service_name = :service_name and id <>:id and is_del=0',[':service_name'=>$params['service_name'],':id'=>$id])->count();
		}
		if ($count>0){
			$errors[] = '????????????????????????.';
		}
		
		$selleruserids_group = PlatformAccountApi::getAllPlatformOrderSelleruseridLabelMap();
		$is_customized = false;
		if(!empty($selleruserids_group['customized'])){
			$is_customized = true;
		}
		
		unset($selleruserids_group['customized']);
		//???????????????????????????????????????????????????????????????
// 		if($carrier_code != 'lb_LGS'){
		if(!in_array($carrier_code, array('lb_LGS','lb_seko'))){
			foreach ($selleruserids_group as $platform=>$selleruserids){
				if (count($selleruserids)>0){
					if (strlen($params['service_code'][$platform])==0){
						if(($carrier_code == 'lb_alionlinedelivery')){
							if($platform == 'aliexpress'){
								$errors[] = '?????????'.(empty(MatchingRule::$source[$platform]) ? '' : MatchingRule::$source[$platform]).'?????????????????????';
							}
						}else{
							$errors[] = '?????????'.(empty(MatchingRule::$source[$platform]) ? '' : MatchingRule::$source[$platform]).'?????????????????????';
						}
					}
				}
			}
		}
		
		if(empty($params['customer_number_config']) && ($is_customized == false)){
			$errors[] = '?????????????????????????????????????????????';
		}
		
		if (!empty($errors)){
			return self::output(array(), 1, $errors);
		}

		$shippingServiceOne->carrier_params = empty($params['carrierParams']) ? '' : $params['carrierParams'];
		$shippingServiceOne->is_used = 1;
		$shippingServiceOne->service_name = empty($params['service_name']) ? '' : $params['service_name'];
		$shippingServiceOne->service_code = empty($params['service_code']) ? '' : $params['service_code'];
		$shippingServiceOne->web = empty($params['web']) ? '' : $params['web'];
		$shippingServiceOne->update_time = time();
		$shippingServiceOne->transport_service_type = empty($params['transport_service_type']) ? 0 : $params['transport_service_type'];
		$shippingServiceOne->aging = empty($params['aging']) ? '' : $params['aging'];
		$shippingServiceOne->is_tracking_number = empty($params['is_tracking_number']) ? 0 : $params['is_tracking_number'];
		$shippingServiceOne->proprietary_warehouse = empty($params['proprietary_warehouse']) ? '' : $params['proprietary_warehouse'];
		$shippingServiceOne->declaration_max_value = empty($params['declaration_max_value']) ? 0 : $params['declaration_max_value'];
		$shippingServiceOne->declaration_max_currency = empty($params['declaration_max_currency']) ? 'USD' : $params['declaration_max_currency'];
		$shippingServiceOne->declaration_max_weight = empty($params['declaration_max_weight']) ? 0 : $params['declaration_max_weight'];
		$shippingServiceOne->customer_number_config = empty($params['customer_number_config']) ? array() : $params['customer_number_config'];
		$shippingServiceOne->common_address_id = empty($params['common_address_id']) ? 0 : $params['common_address_id'];
		$shippingServiceOne->print_params = empty($params['print_params']) ? '' : $params['print_params'];
		$shippingServiceOne->print_type = empty($params['print_type']) ? 0 : $params['print_type'];
		$shippingServiceOne->is_del = 0;
		
		$shippingServiceOne->tracking_upload_config = empty($params['tracking_upload_config']) ? '' : json_encode($params['tracking_upload_config']);
		
		//?????????????????????S
		if(!empty($shippingServiceOne->address)){
			$tmpAddress = $shippingServiceOne->address;
		}else{
			$tmpAddress = array();
		}
		
		$tmpAddress['aliexpressAddress'] = empty($params['address']['aliexpressAddress']) ? array() : $params['address']['aliexpressAddress'];
		$shippingServiceOne->address = $tmpAddress;
		//?????????????????????E
		
		if(substr($carrier_code, 0, 3) != 'lb_'){
			$shippingServiceOne->shipping_method_code = $params['shipping_method_code'];
		}
		
		if($shippingServiceOne->save(false)){
			/*
			 * ????????????????????????????????????
			 */
			if(!empty($params['service_code'])){
				foreach($params['service_code'] as $platform => $mapping) {
					ShippingServiceHelper::saveConfigLogByShippingMapping($platform, $mapping);
				}
			}
			
			return self::output($shippingServiceOne->carrier_code, 0, array('????????????'));
		}else{
			return self::output(array(), 1, array('????????????'));
		}
	}
	
	/**
	 * ??????????????????
	 *
	 * @param	$id	????????????sys_shipping_service??????ID	??????
	 * @param	$is_used	??????????????????	?????????	???????????????
	 * @return Array
	 * Array
		 (
		 [response] => Array
			 (
			 [code] => 0
			 [msg] => Array
				 (
				 '????????????'
				 )
			 [data] => Array
				 (
				 )
			 )
		 )
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/18				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function carrierShippingServiceOnOff($id, $is_used = 0){
		try {
			$carrierAccountObj = SysShippingService::findOne($id);
			
			if($is_used == 0){
				$count=MatchingRule::find()->andWhere(['transportation_service_id'=>$id,'is_active'=>1])->andWhere('created > 0')->count();
				if ($count>0){
					return self::output(array(), 1, '?????????????????????????????????????????????');
				}
			}
				
			$carrierAccountObj->is_used = $is_used;
			if($carrierAccountObj->save(false)){
				return self::output(array(), 0, '???????????????');
			}else{
				return self::output(array(), 1, '???????????????');
			}
		}catch (\Exception $ex){
			return self::output(array(), 1, $ex->getMessage());
		}
	}
	
	/**
	 * ??????????????????????????????
	 *
	 * @param	$id	????????????????????????ID	??????
	 * @return Array
	 * Array
		 (
			 [response] => Array
			 (
				 [code] => 0
				 [msg] => Array
					 (
					 '????????????'
					 )
				 [data] => Array
				 (
				 )
			 )
		 )
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/03/11				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function DelShippingServiceMatch($id){
		if(empty($id)){
			return self::output(array(), 1, '???????????????e_1');
		}
		
		try {
			$Obj = MatchingRule::findOne($id);
			$Obj->is_active = 0;
			$Obj->created = 0;
			$Obj->save(false);
		}catch (\Exception $ex){
			return self::output(array(), 1, '???????????????');
		}
		return self::output(array(), 0, '???????????????');
	}
	
	/**
	 * ????????????????????????????????????
	 *
	 * @param	$carrier_code	???????????????	??????
	 * @return Array
	 * Array
	 (
		 [response] => Array
		 (
			 [code] => 0
			 [msg] => Array
				 (
				 '????????????'
				 )
			 [data] => Array
				 (
				 )
		 )
	 )
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/18				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function refreshCarrierShippingMethod($carrier_code){
		$accountAll = SysCarrierAccount::find()->where(['carrier_code'=>$carrier_code,'is_del'=>0])->orderBy('create_time')->all();
		
		//???????????????
		$carrier = SysCarrier::findOne($carrier_code);
		if ($carrier===null) {
			return self::output(array(), 0, '????????????????????????');
		}
		
		$carriers = array();
		$carriers[$carrier->carrier_code] = $carrier->carrier_name;
		
		try{
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
			}else{
				$interface = new $class_name;
			}
			
			//???????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????
			foreach ($accountAll as $account){
				if($account->carrier_type == 0){
					if(method_exists($interface,'getCarrierShippingServiceStr')){
						$shippingResult = $interface->getCarrierShippingServiceStr($account);
						
						if($shippingResult['error'] == 0){
							self::saveSysShippingMethod($carrier_code, $shippingResult['data']);
						}
					}
				}
			}
		}catch(\Exception $ex){
			//?????????????????????????????????????????????????????????????????????????????????????????????
		}
		
		if(empty($shippingResult)){
			$shippingResult = array();
		}
		
		//??????????????????????????????????????????????????????,??????????????????????????????????????????????????????????????????????????????????????????????????????????????????
		foreach ($accountAll as $account){
			if($account->carrier_type == 1){
				$result = CarrierHelper::refreshShippingMethod($account,$carriers,$shippingResult);
			}
		}
		
		return self::output(array(), 0, '????????????');
	}
	
	/**
	 * ?????????????????????????????????
	 *
	 * @param	$aftAddressId	??????????????????ID
	 * @param	$shippingServiceIdArr	?????????????????????ID??????	??????: array(618,619)
	 * @param	$carrier_code	???????????????	??????
	 * @return Array
	 * Array
	 (
		 [response] => Array
		 (
			 [code] => 0
			 [msg] => Array
				 (
				 '????????????'
				 )
			 [data] => Array
				 (
				 )
		 )
	 )
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/21				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function saveShippingServiceCommonAddress($aftAddressId, $shippingServiceIdArr, $carrier_code){
		if(empty($aftAddressId)){
			return self::output(array(), 1, '??????????????????ID????????????');
		}
		
		if(!is_array($shippingServiceIdArr)){
			return self::output(array(), 1, '??????ID??????');
		}
		
		if(empty($carrier_code)){
			return self::output(array(), 1, '???????????????????????????');
		}
		
		$updateQty = \Yii::$app->get('subdb')->createCommand()->update('sys_shipping_service',
				['common_address_id' => $aftAddressId], ['and', ['id' => $shippingServiceIdArr], ['carrier_code'=>$carrier_code]])->execute();
		
		if($updateQty > 0){
			return self::output(array(), 0, '????????????');
		}else{
			return self::output(array(), 1, '????????????');
		}
	}
	
	/**
	 * ??????????????????????????????
	 *
	 * @param	$id	????????????ID
	 * @return Array
	 * Array
	 (
		 [response] => Array
			 (
			 [code] => 0
			 [msg] => Array
				 (
				 '????????????'
				 )
		 [data] => Array
			 (
			 )
		 )
	 )
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/21				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function delShippingServiceByID($id){
		$serviceOne = SysShippingService::find()->where(['id'=>$id,'is_del'=>0])->one();
		
		if($serviceOne == null){
			return self::output(array(), 1, '????????????,???????????????????????????');
		}
		
		if(substr($serviceOne->carrier_code, 0, 3) == 'lb_'){
			if($serviceOne->is_copy == 0){
				return self::output(array(), 1, '????????????,??????????????????????????????????????????????????????????????????????????????');
			}
		}
		
		$serviceOne->is_del = 1;
		
		if($serviceOne->save(false)){
			return self::output(array(), 0, '????????????');
		}else{
			return self::output(array(), 1, '????????????');
		}
	}
	
	/**
	 * ?????????????????????????????????
	 *
	 * @param	$aftWarehouseArr	????????????????????????	??????:	array(0=>1)	$key????????????ID	$value??????????????????
	 * @param	$shippingServiceIdArr	?????????????????????ID??????	??????: array(618,619)
	 * @param	$carrier_code	???????????????	??????
	 * @return Array
	 * Array
	 (
		 [response] => Array
		 (
			 [code] => 0
			 [msg] => Array
				 (
				 '????????????'
				 )
			 [data] => Array
			 (
			 )
		 )
	 )
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/21				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function saveShippingServiceProprietaryWarehouse($aftWarehouseArr, $shippingServiceIdArr, $carrier_code){
		if(empty($aftWarehouseArr)){
			return self::output(array(), 1, '??????????????????????????????');
		}
		
		if(!is_array($shippingServiceIdArr)){
			return self::output(array(), 1, '??????ID??????');
		}
		
		if(empty($carrier_code)){
			return self::output(array(), 1, '???????????????????????????');
		}
		
		$aftWarehouseSer = serialize($aftWarehouseArr); 
		
		$updateQty = \Yii::$app->get('subdb')->createCommand()->update('sys_shipping_service',
				['proprietary_warehouse' => $aftWarehouseSer], ['and', ['id' => $shippingServiceIdArr], ['carrier_code'=>$carrier_code]])->execute();
		
		if($updateQty > 0){
			return self::output(array(), 0, '????????????');
		}else{
			return self::output(array(), 1, '????????????');
		}
	}
	
	/**
	 * ??????$carrier_code??????????????????????????????????????????
	 *
	 * @param	$carrier_code	???????????????	
	 * @param	$type			????????????????????????:0????????????:1??????????????????:2????????????????????????:3???????????????:4????????????????????????????????????????????????????????????:5
	 * @param	$is_active	1:??????	0:??????	???????????????:??????&&??????
	 * @return Array
	 * Array
	 (
		 [response] => Array
		 (
			 [code] => 0
			 [msg] => Array
				 (
				 '????????????'
				 )
			 [data] => Array
			 (
			 		[0] => ???????????????
                    [1] => ?????????????????????
			 )
		 )
	 )
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/21				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getSysShippingMethodList($carrier_code = '', $type = 3, $is_used = false, $is_active=-1){
		$open_carriers = CarrierOpenHelper::getOpenCarrierArr(2,$is_active);
		$open_carriers = array_keys($open_carriers);
		$shipMethodList = array();
		
		if(($type == 3) && empty($carrier_code)){
			return self::output(array(), 1, '??????3????????????????????????????????????');
		}
		
		if($type == 2){
			$shipMethodArr = SysShippingService::find()->select(['id','service_name'])->where(['is_custom'=>1,'is_del'=>0])->andwhere(['in','carrier_code',$open_carriers])->asArray()->all();
			
			foreach ($shipMethodArr as $shipMethod){
				$shipMethodList[$shipMethod['id']] = $shipMethod['service_name'];
			}
		}
		
		if($type == 3){
			if(substr($carrier_code, 0, 3) != 'lb_'){
				$shipMethodArr = SysShippingService::find()->select(['id','service_name'])->where(['carrier_code'=>$carrier_code,'is_del'=>0])->andwhere(['in','carrier_code',$open_carriers])->asArray()->all();
					
				foreach ($shipMethodArr as $shipMethod){
					$shipMethodList[$shipMethod['id']] = $shipMethod['service_name'];
				}
			}else{
				$shipMethodArr = SysShippingMethod::find()->select(['shipping_method_code','shipping_method_name'])->where(['carrier_code'=>$carrier_code])->andwhere(['in','carrier_code',$open_carriers])->asArray()->all();
					
				foreach ($shipMethodArr as $shipMethod){
					$shipMethodList[$shipMethod['shipping_method_code']] = $shipMethod['shipping_method_name'];
				}
			}
		}
		
		if($type == 4){
			$shipMethodArr = SysShippingService::find()->select(['id','shipping_method_name'])->where(['is_used'=>1,'is_del'=>0])->andwhere(['in','carrier_code',$open_carriers])->groupBy('shipping_method_name')->asArray()->all();
			
			foreach ($shipMethodArr as $shipMethod){
				$shipMethodList[$shipMethod['id']] = $shipMethod['shipping_method_name'];
			}
		}
		
		if($type == 6){
			$shipMethodArr = SysShippingService::find()->select(['id','service_name'])->where(['is_used'=>1,'is_del'=>0])->andwhere(['in','carrier_code',$open_carriers])->groupBy('service_name')->asArray()->all();
				
			foreach ($shipMethodArr as $shipMethod){
				$shipMethodList[$shipMethod['id']] = $shipMethod['service_name'];
			}
		}
		
		if($type == 5){
			$conn=\Yii::$app->subdb;
			
			$queryTmp = new Query;
			$queryTmp->select("a.id,a.service_name")
				->from("sys_shipping_service a")
				->leftJoin("sys_carrier_custom b", "b.carrier_code = a.carrier_code");
			
			$queryTmp->andWhere(['a.is_del'=>0]);
			
			if($is_used)
				$queryTmp->andWhere(['a.is_used'=>1]);
			
			$queryTmp->andWhere(['a.is_custom'=>1]);
			$queryTmp->andWhere(['b.carrier_type'=>0]);
// 			$queryTmp->andWhere(['b.is_used'=>1]);
			$queryTmp->andwhere(['in','b.carrier_code',$open_carriers]);
			$shipMethodArr = $queryTmp->createCommand($conn)->queryAll();
			foreach ($shipMethodArr as $shipMethod){
				$shipMethodList[$shipMethod['id']] = $shipMethod['service_name'];
			}
		}
		
		return self::output($shipMethodList, 0, '');
	}
	
	/**
	 * ?????????????????????????????????????????????sys_shipping_method??????
	 * @param	$code	???????????????	??????
	 * @param	$channel_str	?????????????????????	??????		code1:name1;code2:name2;code3:name3;code4:name4;
	 * @return 
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/10				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function saveSysShippingMethod($code, $channel_str){
		//????????????????????????
		if(empty($channel_str) || !strpos($channel_str,';') || !strpos($channel_str,':'))return false;
		
		$params = explode(';',rtrim($channel_str,';'));
		Helper_Array::removeEmpty($params);
		$result = array();
		foreach($params as $v){
			$value = explode(':',$v);
			if(count($value)<2)return false;
			$result[$value[0]] = $value[1];
		}
		
		foreach ( $result as $shipcode=>$shipname){
			$ship_obj = SysShippingMethod::find()->where(['carrier_code'=>$code,'shipping_method_code'=>$shipcode])->one();
			if ($ship_obj===null){
				$ship_obj = new SysShippingMethod();
			}
			
			$ship_obj->carrier_code = (string)$code;
			$ship_obj->shipping_method_code = (string)$shipcode;
			$ship_obj->shipping_method_name= (string)$shipname;
			$ship_obj->create_time= time();
			$ship_obj->update_time= time();
			if (!$ship_obj->save()){
// 				print_r($ship_obj->getErrors());die;
				return false;
			}else{
// 				return true;
			}
		}
	} 
	
	/**
	 * ????????????????????????????????????
	 * @param
	 * @return Array
	 * Array
		(
		    [response] => Array
		        (
		            [code] => 0
		            [msg] => 
		            [data] => Array
		                (
		                    [19] => cne001
		                )
		
		        )
		
		)
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/21				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCarrierAccountList($carrier_code){
		$accountCarrierList = SysCarrierAccount::find()->select(['id','carrier_name'])->where(['carrier_code'=>$carrier_code,'is_del'=>0])->asArray()->all();
		
		$accountCarrierList = Helper_Array::toHashmap($accountCarrierList, 'id', 'carrier_name');
		
		return self::output($accountCarrierList, 0, '');
	}
	
	/**
	 * ??????????????????????????????
	 * @param
	 * @return Array
	 * Array
		(
		    [AL] => ???????????????
		    [DZ] => ???????????????
		    [AG] => ?????????????????????
		    [AW] => ?????????
		    [AU] => ????????????
		    [AT] => ?????????
		    [BH] => ??????
	    )
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/10				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCoutryList(){
		$country = Helper_Array::toHashmap(\eagle\models\SysCountry::find()->select(['country_zh','country_code'])->asArray()->all(), 'country_code','country_zh');
		
		return $country;
	}
	
	/**
	 * ??????????????????????????????????????????
	 *
	 * @param
	 * @return Array
	 * Array
		 (
			 [lb_369guojikuaidirtbcompany] => 369????????????
			 [lb_4px] => ?????????
			 [lb_4pxOversea] => ?????????(?????????)
			 [lb_alionlinedelivery] => ?????????????????????
			 [lb_baishiyundartbcompany] => ????????????
			 [lb_bangliguojirtbcompany] => ????????????
			 [lb_beijingyichengrtbcompany] => ????????????
			 [lb_birdsysOversea] => ????????????(?????????)
			 [lb_chukouyi] => ?????????
		 )
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/09				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCarrierApiList(){
		//??????????????????
		$carrierList = SysCarrier::find()->orderBy('carrier_name asc')->select(['carrier_code','carrier_name'])->asArray()->all();
		$carrierList = Helper_Array::toHashmap($carrierList,'carrier_code','carrier_name');
		
		return $carrierList;
	}
	
	/**
	 * ???????????????????????????????????????
	 *
	 * @param	$carrier_code	???????????????ID		?????????
	 * @param	$params			????????????
	 * @return Array
	 * Array
		(
		    [0] => Array
		        (
		            [carrier_code] => 2		?????????????????????ID
		            [carrier_name] => fsdf	?????????????????????
		            [carrier_type] => 0		???????????????????????? 0:??????????????? 1:Excel?????????
		            [create_time] => 1447641455
		            [update_time] => 1447641884
		            [is_used] => 0			???????????????
		            [excel_mode] => 		excel????????????
		            [excel_format] => 		excel????????????
		        )
		
		)
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/26				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getHasOpenCustomCarrier($carrier_code = '',$params = array()){
		$query = SysCarrierCustom::find();
		
		if(!empty($carrier_code)){
			$query->andWhere(['carrier_code' => $carrier_code]);
		}
		
		if(isset($params['warehouse_id'])){
			$query->andWhere(['warehouse_id' => $params['warehouse_id']]);
		}
		
		$query->orderBy('is_used desc');
		
		$result = $query->asArray()->all();
		
		return $result;
	}
	
	/**
	 * ??????????????????????????????????????????
	 *
	 * @param	$carrier_code	???????????????ID		????????????????????????
	 * @param	$params	Array()
	 * Array
	 * 	(
	 * 		[carrier_name] => ''	????????????
	 * 		[carrier_type] => 0		???????????????????????? 0:??????????????? 1:Excel?????????
	 * 		[is_used] => 1			????????????	0:????????? 1:?????????
	 * 	)
	 * @return Array
	 * Array
		(
		    [response] => Array
		        (
		            [code] => 0
		            [msg] => ????????????
		            [data] => Array
	                (
	                )
		        )
		)
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/26				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function saveCustomCarrier($carrier_code = '', $params){
		if(empty($carrier_code)){
			$carrierCustom = new SysCarrierCustom();
			$carrierCustom->create_time = time();
		}else{
			$carrierCustom = SysCarrierCustom::find()->where(['carrier_code'=>$carrier_code])->one();
			
			if($carrierCustom == null){
				return self::output(array(), 1, '???????????????????????????');
			}
		}
		
		//????????????????????????
		if ($carrierCustom->isNewRecord){
			$count = SysCarrierCustom::find()->where(['carrier_name'=>$params['carrier_name']])->count();
		}else{
			$count = SysCarrierCustom::find()->where('carrier_name = :carrier_name and carrier_code <>:carrier_code',[':carrier_name'=>$params['carrier_name'],':carrier_code'=>$carrier_code])->count();
		}
		
		if($count > 0){
			return self::output(array(), 1, '???????????????????????????');
		}
		
		$carrierCustom->carrier_name = $params['carrier_name'];
		$carrierCustom->carrier_type = $params['carrier_type'];
		$carrierCustom->update_time = time();
		$carrierCustom->is_used = $params['is_used'];
		
		if(isset($params['warehouse_id'])){
			$carrierCustom->warehouse_id = $params['warehouse_id'];
		}
		
		if($carrierCustom->save()){
			return self::output($carrierCustom->carrier_code, 0, '????????????');
		}else{
			return self::output(array(), 1, '????????????');
		}
	}
	
	/**
	 * ????????????????????????????????????
	 *
	 * @param	$carrier_code	????????????	??????
	 * @param	$is_active	???????????????	??????		1:??????	0:?????????
	 * @return Array
	 * Array
		 (
			 [response] => Array
			 (
				 [code] => 0			0:???????????????1:????????????
				 [msg] => ????????????.
				 [data] => Array
				 (
				 )
			 )
	 	)
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/26				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function customCarrierOpenOrCloseRecord($carrier_code, $is_active){
		$carrierCustom = SysCarrierCustom::find()->where(['carrier_code'=>$carrier_code])->one();
	
		if(($carrierCustom == null)){
			return self::output(array(), 1, '??????????????????????????????????????????.');
		}
	
		if(($carrierCustom->is_used == 1) && ($is_active == 1)){
			return self::output(array(), 1, '????????????????????????.');
		}
			
		if(($carrierCustom->is_used == 0) && ($is_active == 0)){
			return self::output(array(), 1, '????????????????????????.');
		}
	
		$carrierCustom->is_used = $is_active;
		$carrierCustom->update_time = time();
	
		if($carrierCustom->save(false)){
			if($is_active == 1)
				return self::output(array(), 0, '????????????.');
			else
				return self::output(array(), 0, '????????????.');
		}else{
			return self::output(array(), 1, '????????????.');
		}
	}
	
	/**
	 * ????????????????????????????????????
	 *
	 * @param	$carrier_code	????????????	??????
	 * @return Array
	 * Array
		(
		    [response] => Array
		        (
		            [code] => 0
		            [msg] => 
		            [data] => Array
		                (
		                    [carrier_code] => 2
		                    [carrier_name] => fsdf
		                    [carrier_type] => 0
		                    [create_time] => 1447641455
		                    [update_time] => 1447641884
		                    [is_used] => 0
		                    [excel_mode] => oneOrderOneLine		Excel????????????	?????????????????? orderToOneLine:??????????????????,orderToSku:??????????????????(?????????),orderToLine:?????????????????????(???????????????)
		                    [excel_format] => Array
							    (
							    	[0] => Array
								    	(
								    		[title_column] => 'OrderID'
								    		[show_datas] => Array
								    			(
								    				[data_type] => 'sys_data'	?????????????????? sys_data:????????????,fixed_value:?????????,keep_empty:????????????	?????????????????????data_value????????????,fixed_value?????????input?????????,keep_empty????????????data_value???????????????????????????????????????
								    				[data_value] => ''			??????sys_data????????????ExcelHelper::$content;??????????????????
								    			)
								    	)
							    )
		                )
		        )
		)
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/28				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCustomCarrierExcelFormat($carrier_code){
		if(empty($carrier_code)){
			return self::output(array(), 1, '????????????.');
		}
		
		$carrierCustom = SysCarrierCustom::find()->where(['carrier_code'=>$carrier_code])->asArray()->all();
		
		if(count($carrierCustom) == 0){
			return self::output(array(), 1, '??????????????????????????????????????????.');
		}
		
		self::StrToUnserialize($carrierCustom,array('excel_format'));
		
		$carrierCustom = $carrierCustom[0];
		
		return self::output($carrierCustom, 0, '');
	}
	
	/**
	 * ????????????????????????????????????
	 *
	 * @param	$carrier_code	????????????	??????
	 * @param	$params Array
	 * 					(
	 *						[excel_mode] => oneOrderOneLine		Excel????????????	?????????????????? orderToOneLine:??????????????????,orderToSku:??????????????????(?????????),orderToLine:?????????????????????(???????????????)
	 *						[excel_format] => Array
							    (
							    	[0] => Array
								    	(
								    		[title_column] => 'OrderID'
								    		[show_datas] => Array
								    			(
								    				[data_type] => 'sys_data'	?????????????????? sys_data:????????????,fixed_value:?????????,keep_empty:????????????	?????????????????????data_value????????????,fixed_value?????????input?????????,keep_empty????????????data_value???????????????????????????????????????
								    				[data_value] => ''			??????sys_data????????????ExcelHelper::$content;??????????????????
								    			)
								    	)
							    )
	 *				 	)
	 * @return Array
	 * Array
		 (
			 [response] => Array
			 (
				 [code] => 0
				 [msg] => ????????????.
				 [data] => Array
				 (
				 )
			 )
	 	)
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/28				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function saveCustomCarrierExcelFormat($carrier_code, $params){
		$carrierCustom = SysCarrierCustom::find()->where(['carrier_code'=>$carrier_code])->one();
		
		if($carrierCustom == null){
			return self::output(array(), 1, '??????????????????????????????????????????.');
		}
		
		$carrierCustom->excel_mode = $params['excel_mode'];
		$carrierCustom->excel_format = $params['excel_format'];
		
		if($carrierCustom->save()){
			return self::output(array(), 0, '????????????.');
		}else{
			return self::output(array(), 1, '????????????.');
		}
	}
	
	/**
	 * ?????????????????????????????????????????????????????????
	 *
	 * @param	$id				????????????ID	??????0??????????????????????????????
	 * @param	$carrier_code	????????????	??????
	 * @return Array
	 * Array
		(
		    [response] => Array
		        (
		            [code] => 0
		            [msg] => 
		            [data] => Array
		                (
		                    [serviceID] => 0
		                    [carrier_name] => fsdf
		                    [service_name] => 
		                    [web] => http://www.17track.net
		                    [transport_service_type] => 0
		                    [aging] => 
		                    [is_tracking_number] => 0
		                    [common_address_id] => 0
		                    [commonAddressArr] => Array
		                        (
		                        )
		                    [print_params] => Array
		                        (
		                            [label_custom] => Array
		                                (
		                                    [carrier_lable] => 0
		                                    [declare_lable] => 0
		                                    [items_lable] => 0
		                                )
		                            [label_customCarrierArr] => Array
		                                (
		                                    [1] => ?????????:4px?????????????????????10cm??10cm
		                                )
		                            [label_customDeclareArr] => Array
		                                (
		                                )
		                            [label_customItemsArr] => Array
		                                (
		                                )
		                        )
		                    [proprietary_warehouse] => Array
		                        (
		                            [0] => Array
		                                (
		                                    [name] => (????????????)
		                                    [is_selected] => 0
		                                )
		                            [2] => Array
		                                (
		                                    [name] => ?????????
		                                    [is_selected] => 0
		                                )
		                        )
		                    [declaration_max_value] => 0
		                    [declaration_max_currency] => USD
		                    [declaration_max_weight] => 0
		                    [service_code] => Array
		                        (
		                            [ebay] => Array
		                                (
		                                    [val] => dhl
		                                    [optional_value] => stdClass Object
		                                        (
		                                            [Chronopost] => Chronopost
		                                            [ColiposteDomestic] => Coliposte Domestic
		                                        )
		                                    [display_type] => text
		                                )
		                            [aliexpress] => Array
		                                (
		                                    [val] => DHL
		                                    [optional_value] => Array
		                                        (
		                                            [DHL_FR] => DHL_FR
		                                            [DHL] => DHL
		                                        )
		                                    [display_type] => dropdownlist
		                                )
		                        )
		                    [customer_number_config] => Array
		                        (
		                            [ebay] => Array
		                                (
		                                    [val] => serial_random_6number
		                                    [optional_value] => Array
		                                        (
		                                            [] => ??????
		                                            [serial_random_6number] => ?????????+???????????????
		                                            [serial_date] => ?????????+??????
		                                            [platform_id] => ???????????????
		                                            [platform_id_random_6number] => ???????????????+???????????????
		                                            [platform_id_date] => ???????????????+??????
		                                        )
		                                )
		                            [aliexpress] => Array
		                                (
		                                    [val] => platform_id
		                                    [optional_value] => Array
		                                        (
		                                            [] => ??????
		                                            [serial_random_6number] => ?????????+???????????????
		                                            [serial_date] => ?????????+??????
		                                            [platform_id] => ???????????????
		                                            [platform_id_random_6number] => ???????????????+???????????????
		                                            [platform_id_date] => ???????????????+??????
		                                        )
		                                )
		                        )
		                )
		        )
		)
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/29				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCustomCarrierShippingServiceUserById($id, $carrier_code){
		if(empty($carrier_code)){
			return self::output(array(), 1, '?????????????????????.');
		}
		
		$tmp_carrier_name = '';
		
		$sysCarrier = SysCarrierAccount::find()->select(['carrier_code','carrier_name'])->where(['carrier_code'=>$carrier_code])->one();
		if($sysCarrier == null){
			$sysCarrier = SysCarrierCustom::find()->select(['carrier_code','carrier_name'])->where(['carrier_code'=>$carrier_code])->one();
			if($sysCarrier == null){
				return self::output(array(), 1, '????????????,????????????????????????.');
			}
		}
		
		$tmp_carrier_name = $sysCarrier->carrier_name;
		
		if(empty($id)){
			$shippingServiceArr = array();
			$shippingServiceArr['id'] = 0;
			$shippingServiceArr['service_name'] = '';
			$shippingServiceArr['web'] = 'http://www.17track.net';
			$shippingServiceArr['transport_service_type'] = 0;
			$shippingServiceArr['aging'] = '';
			$shippingServiceArr['is_tracking_number'] = 1;
			$shippingServiceArr['common_address_id'] = 0;
			$shippingServiceArr['declaration_max_value'] = 0;
			$shippingServiceArr['declaration_max_currency'] = 'USD';
			$shippingServiceArr['declaration_max_weight'] = 0;
		}else{
			//???????????????????????????
			$query = SysShippingService::find();
			$query->andWhere(['id'=>$id]);
			$shippingServiceArr = $query->asArray()->all();
			
			self::StrToUnserialize($shippingServiceArr,array('service_code','print_params','proprietary_warehouse','customer_number_config'));
			
			if(count($shippingServiceArr) == 0){
				return self::output(array(), 1, '?????????????????????ID??????.');
			}else{
				$shippingServiceArr = $shippingServiceArr[0];
			}
			
			if($shippingServiceArr['carrier_code'] != $carrier_code){
				return self::output(array(), 1, '????????????????????????????????????.');
			}
		}
		
		$result = array();
		
		$result['serviceID'] = $shippingServiceArr['id'];
		$result['carrier_name'] = $tmp_carrier_name;
		$result['service_name'] = $shippingServiceArr['service_name'];
		$result['shipping_method_code'] = empty($shippingServiceArr['shipping_method_code']) ? '' : $shippingServiceArr['shipping_method_code'];
		$result['web'] = $shippingServiceArr['web'];
		$result['transport_service_type'] = $shippingServiceArr['transport_service_type'];
		$result['aging'] = $shippingServiceArr['aging'];
		$result['is_tracking_number'] = $shippingServiceArr['is_tracking_number'];
// 		$result['accountID'] = $shippingServiceArr['carrier_account_id'];
		
		//????????????????????????
		$result['common_address_id'] = $shippingServiceArr['common_address_id'];
		$result['commonAddressArr'] = self::getCarrierAddressNameArrByType(0, $carrier_code)['response']['data']['list'];
		
		$result['print_params']['label_custom'] = empty($shippingServiceArr['print_params']['label_custom']) ? array('carrier_lable'=>0,'declare_lable'=>0,'items_lable'=>0) : $shippingServiceArr['print_params']['label_custom'];
		
		$result['print_params']['label_custom_new'] = empty($shippingServiceArr['print_params']['label_custom_new']) ? array('carrier_lable'=>0,'declare_lable'=>0,'items_lable'=>0) : $shippingServiceArr['print_params']['label_custom_new'];
		
		$result['print_params']['label_littlebossOptionsArrNew']=empty($shippingServiceArr['print_params']['label_littlebossOptionsArrNew'])?array('carrier_lable'=>'','declare_lable'=>'','items_lable'=>'','key'=>'0'):$shippingServiceArr['print_params']['label_littlebossOptionsArrNew'];
		
		$result['print_type'] = @$shippingServiceArr['print_type'];
		
		//?????????????????????
		$templates = CrTemplate::find()->select(['template_id','template_name','template_type','template_version'])->where(['in','template_type',array('?????????','?????????','?????????')])->asArray()->all();
		$templateAddress = array();
		$templateDeclare = array();
		$templateItems = array();
		
		$templateAddress2 = array();
		$templateDeclare2 = array();
		$templateItems2 = array();
		
		foreach ($templates as $templatesOne){
			if($templatesOne['template_type'] == '?????????'){
				if($templatesOne['template_version'] == 0)
					$templateAddress[$templatesOne['template_id']] = $templatesOne['template_name'];
				else 
					$templateAddress2[$templatesOne['template_id']] = $templatesOne['template_name'];
			}else if($templatesOne['template_type'] == '?????????'){
				if($templatesOne['template_version'] == 0)
					$templateDeclare[$templatesOne['template_id']] = $templatesOne['template_name'];
				else 
					$templateDeclare2[$templatesOne['template_id']] = $templatesOne['template_name'];
			}else if($templatesOne['template_type'] == '?????????'){
				if($templatesOne['template_version'] == 0)
					$templateItems[$templatesOne['template_id']] = $templatesOne['template_name'];
				else
					$templateItems2[$templatesOne['template_id']] = $templatesOne['template_name'];
			}
		}
		
		$result['print_params']['label_customCarrierArr'] = $templateAddress;
		$result['print_params']['label_customDeclareArr'] = $templateDeclare;
		$result['print_params']['label_customItemsArr'] = $templateItems;
		
		$result['print_params']['label_custom_newCarrierArr'] = $templateAddress2;
		$result['print_params']['label_custom_newDeclareArr'] = $templateDeclare2;
		$result['print_params']['label_custom_newItemsArr'] = $templateItems2;
		
		//??????????????????
		$result['proprietary_warehouse'] = empty($shippingServiceArr['proprietary_warehouse']) ? '' : $shippingServiceArr['proprietary_warehouse'];
		$result['self_warehouse'] = InventoryHelper::getWarehouseOrOverseaIdNameMap(1, -1);
		
		$result['declaration_max_value'] = $shippingServiceArr['declaration_max_value'];
		$result['declaration_max_currency'] = $shippingServiceArr['declaration_max_currency'];
		$result['declaration_max_weight'] = $shippingServiceArr['declaration_max_weight'];
		
		//????????????????????????
		$platformUseArr = PlatformAccountApi::getAllPlatformBindingSituation();
		
		//????????????????????????????????????????????????????????????
		foreach ($platformUseArr as $platformUseKey => $platformUseVal){
			if($platformUseVal == false){
				unset($shippingServiceArr['service_code'][$platformUseKey]);
				unset($shippingServiceArr['customer_number_config'][$platformUseKey]);
			}else if($platformUseVal == true){
				//?????????????????????????????????????????????????????????
				switch ($platformUseKey){
					case 'ebay':
						$serviceData = json_decode(file_get_contents(Yii::getAlias('@web').'docs/ebayServiceCode.json'));
						$display_type = 'text';
						$carrier_order_id_mode = 'platform_id_date';
						break;
					case 'aliexpress':
						$serviceData = \common\api\aliexpressinterface\AliexpressInterface_Helper::getShippingCodeNameMap();
						$display_type = 'dropdownlist';
						$carrier_order_id_mode = 'platform_id';
						break;
					case 'wish':
						$serviceData = \eagle\modules\order\helpers\WishOrderInterface::getShippingCodeNameMap();
						asort($serviceData);
						$display_type = 'dropdownlist';
						$carrier_order_id_mode = 'serial_date';
						break;
					case 'amazon':
						$serviceData = \eagle\modules\amazon\apihelpers\AmazonApiHelper::getShippingCodeNameMap();
						asort($serviceData);
						$display_type = 'dropdownlist';
						if(!empty($shippingServiceArr['service_code'][$platformUseKey])){
							$serviceData[$shippingServiceArr['service_code'][$platformUseKey]] = $shippingServiceArr['service_code'][$platformUseKey];
						}
						$carrier_order_id_mode = 'platform_id';
						break;
					case 'dhgate':
						$serviceData = \eagle\modules\dhgate\apihelpers\DhgateApiHelper::getShippingCodeNameMap();
						$display_type = 'dropdownlist';
						$carrier_order_id_mode = 'platform_id';
						break;
					case 'lazada':
						$serviceData = \eagle\modules\lazada\apihelpers\LazadaApiHelper::getShippingCodeNameMap();
						$display_type = 'dropdownlist';
						$carrier_order_id_mode = 'platform_id';
						break;
					case 'linio':
						$serviceData = \eagle\modules\lazada\apihelpers\LazadaApiHelper::getLinioShippingCodeNameMap();
						$display_type = 'dropdownlist';
						$carrier_order_id_mode = 'platform_id';
						break;
					case 'ensogo':
						$serviceData = \eagle\modules\order\helpers\EnsogoOrderInterface::getShippingCodeNameMap();
						$display_type = 'dropdownlist';
						$carrier_order_id_mode = 'platform_id';
						break;
					case 'priceminister':
						$serviceData = \eagle\modules\order\helpers\PriceministerOrderInterface::getShippingCodeNameMap();
						$display_type = 'dropdownlist';
						$carrier_order_id_mode = 'platform_id';
						break;
					case 'cdiscount':
						$serviceData = \eagle\modules\order\helpers\CdiscountOrderInterface::getShippingCodeNameMap();
						$display_type = 'dropdownlist';
						if(!empty($shippingServiceArr['service_code'][$platformUseKey])){
							$serviceData[$shippingServiceArr['service_code'][$platformUseKey]] = $shippingServiceArr['service_code'][$platformUseKey];
						}
						$carrier_order_id_mode = 'platform_id';
						break;
					default:
						$serviceData = '';
						$display_type = 'text';
						$carrier_order_id_mode = 'platform_id';
						break;
				}
		
				$result['service_code'][$platformUseKey] = array(
						'val'=>empty($shippingServiceArr['service_code'][$platformUseKey]) ? ShippingServiceHelper::getRecommendConfigByShippingMapping($platformUseKey) : $shippingServiceArr['service_code'][$platformUseKey],
						'optional_value'=>$serviceData,
						'display_type'=>$display_type,
				);
		
				$result['customer_number_config'][$platformUseKey] = array(
						'val'=>empty($shippingServiceArr['customer_number_config'][$platformUseKey]) ? $carrier_order_id_mode : $shippingServiceArr['customer_number_config'][$platformUseKey],
						'optional_value'=>array(
								''=>'??????',
								'serial_random_6number'=>'???????????????+???????????????',
								'serial_date'=>'???????????????+??????',
								'platform_id'=>'???????????????',
								'platform_id_random_6number'=>'???????????????+???????????????',
								'platform_id_date'=>'???????????????+??????',
						)
				);
				
				if(in_array($platformUseKey, array('ebay','amazon'))){
					$tmp_tracking_upload_config = $shippingServiceArr['tracking_upload_config'];
					$tmp_tracking_upload_config = json_decode($tmp_tracking_upload_config, true);
						
					$result['tracking_upload_config'][$platformUseKey] = array(
							'val' => empty($tmp_tracking_upload_config[$platformUseKey]) ? 0 : $tmp_tracking_upload_config[$platformUseKey],
							'optional_value' => array(0=>'?????????????????????', 1=>'?????????????????????')
					);
						
					if($platformUseKey == 'amazon'){
						$result['tracking_upload_config'][$platformUseKey]['optional_value'] += array('2'=>'????????????????????????????????????');
					}
				}
			}
		}
		
		return self::output($result, 0, '');
	}
	
	/**
	 * ?????????????????????????????? ????????????????????????
	 *
	 * @param	$platform	????????????aliexpress
	 * @return Array
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/25				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function updateSysShippingCodeNameMap($platform){
		$puid = \Yii::$app->subdb->getCurrentPuid();
		
		if(empty($puid)){
			return self::output(array(), 1, '??????????????????');
		}
		
		switch($platform){
			case 'aliexpress':
				$result = self::aliexpressShippingCodeNameMap($puid);
				break;
			case 'lazada':
				$res = LazadaApiHelper::updateShipmentProviders();
				$res = json_decode($res,true);
				if($res['code'] == 200){
					$result = self::output(LazadaApiHelper::getShippingCodeNameMap(), 0, '????????????');
				}else{
					$result = self::output([],1,$res['msg']);
				}
				break;
			default:
				$result = self::output(array(), 0, '????????????');
				break;
		}
		
		return $result;
	}
	
	/**
	 * ??????????????????????????????
	 * @param
	 * @return 1
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/30				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getMaxMatchingRulePriority(){
		$matchingRulePriority = MatchingRule::find()->max('priority');
		
		if($matchingRulePriority == ''){
			$matchingRulePriority = 0;
		}
		
		return ++$matchingRulePriority;
	}
	
	/**
	 * ?????????????????????????????????
	 * @param	$defaultPageSize	???????????????????????????
	 * @return 
	 * Array
		(
		    [pagination] => yii\data\Pagination Object
		        (
		            [pageParam] => page
		            [pageSizeParam] => per-page
		            [forcePageParam] => 1
		            [route] => 
		            [params] => 
		            [urlManager] => 
		            [validatePage] => 1
		            [totalCount] => 10
		            [defaultPageSize] => 15
		            [pageSizeLimit] => Array
		                (
		                    [0] => 15
		                    [1] => 200
		                )
		            [_pageSize:yii\data\Pagination:private] => 5
		            [_page:yii\data\Pagination:private] => 0
		        )
		    [data] => Array
		        (
		            [0] => Array
		                (
		                    [id] => 15
		                    [uid] => 1
		                    [operator] => 1
		                    [rule_name] => anjun
		                    [rules] => a:2:{i:0;s:6:"source";i:1;s:28:"buyer_transportation_service";}
		                    [source] => a:1:{i:0;s:6:"amazon";}
		                    [site] => a:3:{s:4:"ebay";a:1:{i:0;s:2:"US";}s:6:"amazon";a:2:{i:0;s:2:"US";i:1;s:2:"CA";}s:9:"cdiscount";a:1:{i:0;s:2:"FR";}}
		                    [selleruserid] => 
		                    [buyer_transportation_service] => a:1:{s:4:"ebay";a:1:{s:2:"US";a:2:{i:0;s:24:"eBayNowImmediateDelivery";i:1;s:22:"eBayNowNextDayDelivery";}}}
		                    [warehouse] => 
		                    [receiving_country] => 
		                    [total_amount] => 
		                    [freight_amount] => 
		                    [total_weight] => 
		                    [product_tag] => 
		                    [transportation_service_id] => 751
		                    [priority] => 1
		                    [is_active] => 1
		                    [created] => 1450338217
		                    [updated] => 1450338217
		                    [volume_weight] => 
		                    [volume] => 
		                    [skus] => 
		                    [total_cost] => 
		                    [items_location_country] => 
		                    [items_location_provinces] => 
		                    [items_location_city] => 
		                    [receiving_provinces] => 
		                    [receiving_city] => 
		                )
		        )
		)
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/31				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getMatchingRuleList($defaultPageSize=15, $params = array(),$is_active = 1,$carrier_is_active = 1){
		$open_carriers = CarrierOpenHelper::getOpenCarrierArr(2,$carrier_is_active);
		$open_carriers = array_keys($open_carriers);
		$conn=\Yii::$app->subdb;
		
		$queryTmp = new Query;
		$queryTmp->select("a.id,a.rule_name,a.is_active,a.priority,b.carrier_name,b.service_name,b.proprietary_warehouse,a.proprietary_warehouse_id")
			->from("matching_rule a")
			->leftJoin("sys_shipping_service b", "b.id = a.transportation_service_id")
			->leftJoin("sys_carrier_account c","c.id = b.carrier_account_id");
		
		$queryTmp->andWhere('a.created > 0');
		
		if(!empty($params['carrier_name'])){
			$queryTmp->andWhere(['b.carrier_name'=>$params['carrier_name']]);
		}
		
		if(!empty($params['shipping_method_name'])){
			$queryTmp->andWhere(['b.shipping_method_name'=>$params['shipping_method_name']]);
		}
		
		if(isset($params['proprietary_warehouse'])){
			$queryTmp->andWhere(['a.proprietary_warehouse_id'=>$params['proprietary_warehouse']]);
		}
		if($is_active == 1 || $is_active == 0){
			$queryTmp->andWhere(['a.is_active'=>$is_active]);
		}
		$queryTmp->andwhere(['in','b.carrier_code',$open_carriers]);
		$DataCount = $queryTmp->count("1", $conn);
		
		$pagination = new Pagination([
				'defaultPageSize' => 15,
				'pageSize' => $defaultPageSize,
				'totalCount' => $DataCount,
				'pageSizeLimit'=>[15,200],//????????????????????????
				]);
		
		$list['pagination'] = $pagination;
		
		$sort = 'is_active';
		$order = 'desc';
		$sort_arr = array('is_active'=>'is_active desc','priority'=>'priority asc','transportation_service_id'=>'transportation_service_id asc','rule_name'=>'rule_name asc');
		unset($sort_arr[$sort]);
		$str = $sort.' '.$order.','.implode(',', $sort_arr);
			
		$queryTmp->orderBy($str);
		$queryTmp->limit($pagination->limit);
		$queryTmp->offset($pagination->offset);
		
		$list['data'] = $queryTmp->createCommand($conn)->queryAll();
		
//         echo $queryTmp->createCommand()->getRawSql();
        
        return $list;
	}
	
	//????????????????????????????????????  ???getMatchingRuleList??????????????????????????????is_active????????????????????????
	public static function getMatchingRuleListNew($params = array(),$is_active = 1,$carrier_is_active = 1){
		$open_carriers = CarrierOpenHelper::getOpenCarrierArr(2,$carrier_is_active);
		$open_carriers = array_keys($open_carriers);
		$conn=\Yii::$app->subdb;
	
		$queryTmp = new Query;
		$queryTmp->select("a.id,a.rule_name,a.is_active,a.priority,b.carrier_name,b.service_name,b.proprietary_warehouse,a.proprietary_warehouse_id")
		->from("matching_rule a")
		->leftJoin("sys_shipping_service b", "b.id = a.transportation_service_id")
		->leftJoin("sys_carrier_account c","c.id = b.carrier_account_id");
	
		$queryTmp->andWhere('a.created > 0');
	
		if(!empty($params['carrier_name'])){
			$queryTmp->andWhere(['b.carrier_name'=>$params['carrier_name']]);
		}
	
		if(!empty($params['shipping_method_name'])){
			$queryTmp->andWhere(['b.shipping_method_name'=>$params['shipping_method_name']]);
		}
	
		if(isset($params['proprietary_warehouse'])){
			$queryTmp->andWhere(['a.proprietary_warehouse_id'=>$params['proprietary_warehouse']]);
		}
		if($is_active == 1 || $is_active == 0){
			$queryTmp->andWhere(['a.is_active'=>$is_active]);
		}
		$queryTmp->andwhere(['in','b.carrier_code',$open_carriers]);
		$DataCount = $queryTmp->count("1", $conn);
	
		$sort_arr = array('priority'=>'priority asc','transportation_service_id'=>'transportation_service_id asc','rule_name'=>'rule_name asc');
		$str = implode(',', $sort_arr);
			
		$queryTmp->orderBy($str);
// 		$queryTmp->limit($pagination->limit);
// 		$queryTmp->offset($pagination->offset);
	
		$list['data'] = $queryTmp->createCommand($conn)->queryAll();
	
		//echo $queryTmp->createCommand()->getRawSql();
	
		return $list;
	}
	
	/**
	 * ??????????????????	??????????????????
	 *
	 * @param	$ruleIdHigh
	 * @param	$ruleIdLow
	 * @return Array
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/31				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function setMatchingRulePriority($ruleIdHigh, $ruleIdLow){
		$ruleIdHighOne = MatchingRule::find()->where(['id'=>$ruleIdHigh])->one();
		$ruleIdLowOne = MatchingRule::find()->where(['id'=>$ruleIdLow])->one();
		
		$priorityHigh = $ruleIdHighOne->priority;
		$priorityLow = $ruleIdLowOne->priority;
		
		$ruleIdHighOne->priority = $priorityLow;
		$ruleIdLowOne->priority = $priorityHigh;
		
		try{
			$ruleIdHighOne->save();
			$ruleIdLowOne->save();
		}catch (\Exception $ex){
			return self::output(array(), 1, '??????????????????');
		}
		
		return self::output(array(), 0, '??????????????????');
	}
	
	/**
	 * aliexpress????????????????????????
	 *
	 * @param	$puid	user puid
	 * @return Array
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/25				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	protected static function aliexpressShippingCodeNameMap($puid){
		$aliexpressUsers = \eagle\models\SaasAliexpressUser::find()->select(['sellerloginid'])->where(['uid'=>$puid])->asArray()->all();
		
		$aliSelleruids = \common\helpers\Helper_Array::toHashmap($aliexpressUsers , 'sellerloginid' , 'sellerloginid');
		foreach ($aliSelleruids as $aliuserOne){
			//????????????????????????????????????v2???
			$is_aliexpress_v2 = AliexpressInterface_Helper_Qimen::CheckAliexpressV2($aliuserOne);
			if($is_aliexpress_v2){
				$api = new AliexpressInterface_Api_Qimen();
				$res = $api->listLogisticsService(['id' => $aliuserOne]);
				
				if(!empty($res['error_message'])){
					$result['success'] = false;
					$result['result'] = [];
				}
				else{
					$result['success'] = true;
					$result['result'] = $res;
				}
			}
			else{
				$api = new AliexpressInterface_Api();
				$access_token = $api->getAccessToken ( $aliuserOne );
				$api->access_token = $access_token;
				$result = $api->listLogisticsService();
			}
			
			if(!isset($result['success'])){
				return self::output(array(), 1, '???????????????????????????????????????????????????');
			}
			
			if(!$result['success']){
				return self::output(array(), 1, '???????????????????????????????????????????????????e1');
			}
			
			foreach ($result['result'] as $one){
				$one['displayName'] = empty($one['display_name']) ? $one['displayName'] : $one['display_name'];
				$one['serviceName'] = empty($one['service_name']) ? $one['displayName'] : $one['service_name'];
				
				$arr[$one['serviceName']]=$one['displayName'];
					
				$sysShippingCodeMapOne = SysShippingCodeNameMap::find()->where(['platform'=>'aliexpress','shipping_code'=>$one['serviceName']])->one();
					
				if($sysShippingCodeMapOne == null){
					$sysShippingCodeMapOne = new SysShippingCodeNameMap();
			
					$sysShippingCodeMapOne->platform = 'aliexpress';
					$sysShippingCodeMapOne->shipping_code = $one['serviceName'];
					$sysShippingCodeMapOne->create_time = time();
				}
					
				$sysShippingCodeMapOne->shipping_name = $one['displayName'];
				$sysShippingCodeMapOne->update_time = time();
					
				$sysShippingCodeMapOne->save();
			}
		}
		
		//???????????????????????????????????????redis??????
		RedisHelper::RedisSet('Tracker_AppTempData', "SysShippingCodeNameMapHashMap", '');
		$serviceData = \common\api\aliexpressinterface\AliexpressInterface_Helper::getShippingCodeNameMap();
		return self::output($serviceData, 0, '????????????');
	}
	
	/**
	 * ???????????????????????????????????????????????????managedb??????user?????????????????????
	 *
	 * @param	$account	SysCarrierAccount???
	 * @return Array
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/04				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function saveAddOrEditCarrierToManagedbRecord($account){
		try{
			//??????????????????????????????managedb??????user?????????????????????
			$carrierUserUse = CarrierUserUse::find()->where(['carrier_account_id' => $account->id, 'puid' => \Yii::$app->subdb->getCurrentPuid(),'carrier_code' => $account->carrier_code])->one();
			 
			if($carrierUserUse === null){
				$carrierUserUse = new CarrierUserUse();
				$carrierUserUse->puid = \Yii::$app->subdb->getCurrentPuid();
				$carrierUserUse->carrier_code = $account->carrier_code;
				$carrierUserUse->carrier_account_id = $account->id;
			}
		
			$carrierUserUse->is_used = $account->is_used;
		
			if((substr($account->carrier_code,-10) == 'rtbcompany') || ($account->carrier_code == 'lb_haoyuan')){
				$carrierUserUse->param1 = $account->api_params['appToken'];
				$carrierUserUse->param2 = $account->api_params['appKey'];
			}
			$carrierUserUse->save(false);
		}catch(\Exception $ex){
			//????????????????????????
		}
	}
	
	/**
	 * ??????????????????????????????
	 * @param	$defaultPageSize	???????????????????????????
	 * @param	$params array(
	 * 				[tracking_number] => 
	 * 				[carrier_name] =>
	 * 				[shipping_method_name] => 
	 * 				[is_used] => 
	 * 				[create_timeStart] => 
	 * 				[create_timeEnd] => 
	 * 				[use_timeStart] => 
	 * 				[use_timeEnd] => 
	 * 				[order_id] => 
	 * 			)
	 * @return
	 * Array
		(
		    [pagination] => yii\data\Pagination Object
		        (
		            [pageParam] => page
		            [pageSizeParam] => per-page
		            [forcePageParam] => 1
		            [route] => 
		            [params] => 
		            [urlManager] => 
		            [validatePage] => 1
		            [totalCount] => 3
		            [defaultPageSize] => 15
		            [pageSizeLimit] => Array
		                (
		                    [0] => 15
		                    [1] => 200
		                )
		            [_pageSize:yii\data\Pagination:private] => 15
		            [_page:yii\data\Pagination:private] => 0
		        )
		    [data] => Array
		        (
		            [0] => Array
		                (
		                    [id] => 5
		                    [tracking_number] => 789
		                    [carrier_name] => ?????????01
		                    [shipping_method_name] => zdy01
		                    [is_used] => 0
		                    [user_name] => test
		                    [create_time] => 1451094502
		                    [use_time] => 
		                    [order_id] => 
		                )
		        )
		)
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/31				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCarrierTrackingNumberList($defaultPageSize=15, $params = array()){
		$conn=\Yii::$app->subdb;
	
		$queryTmp = new Query;
		$queryTmp->select("a.id,a.tracking_number,b.carrier_name,b.shipping_method_name,a.is_used,a.user_name,a.create_time,a.use_time,a.order_id")
			->from("sys_tracking_number a")
			->leftJoin("sys_shipping_service b", "b.id = a.shipping_service_id");
	
		if(isset($params['tracking_number'])){
			$queryTmp->andWhere(['like','a.tracking_number',$params['tracking_number']]);
		}
				
		if(!empty($params['carrier_name'])){
			$queryTmp->andWhere(['b.carrier_name'=>$params['carrier_name']]);
		}
	
		if(!empty($params['shipping_method_name'])){
			$queryTmp->andWhere(['like','b.shipping_method_name',$params['shipping_method_name']]);
		}
		
		if(isset($params['is_used'])){
			$queryTmp->andWhere(['a.is_used'=>$params['is_used']]);
		}
		
		if(!empty($params['create_timeStart'])){
			$queryTmp->andWhere('a.create_time > :create_timeStart',[':create_timeStart' => strtotime($params['create_timeStart'])]);
		}
		
		if(!empty($params['create_timeEnd'])){
			$queryTmp->andWhere('a.create_time <= :create_timeEnd',[':create_timeEnd' => strtotime($params['create_timeEnd'].' 23:59:59')]);
		}
	
		if(!empty($params['use_timeStart'])){
			$queryTmp->andWhere('a.use_time > :use_timeStart',[':use_timeStart' => strtotime($params['use_timeStart'])]);
		}
		
		if(!empty($params['use_timeEnd'])){
			$queryTmp->andWhere('a.use_time <= :use_timeEnd',[':use_timeEnd' => strtotime($params['use_timeEnd'].' 23:59:59')]);
		}
		
		if(!empty($params['order_id'])){
			$queryTmp->andWhere(['a.order_id'=>$params['order_id']]);
		}
		
		if(isset($params['carrier_code'])){
			$queryTmp->andWhere(['b.carrier_code'=>$params['carrier_code']]);
		}
	
		$DataCount = $queryTmp->count("1", $conn);
	
		$pagination = new Pagination([
				'defaultPageSize' => 15,
				'pageSize' => $defaultPageSize,
				'totalCount' => $DataCount,
				'pageSizeLimit'=>[15,200],//????????????????????????
				]);
	
		$list['pagination'] = $pagination;
	
		$sort = '';
		$order = '';
		$sort_arr = array('id'=>'a.id desc','shipping_service_id'=>'a.shipping_service_id asc');
		unset($sort_arr[$sort]);
		$str = $sort.' '.$order.','.implode(',', $sort_arr);
			
		$queryTmp->orderBy($str);
		$queryTmp->limit($pagination->limit);
		$queryTmp->offset($pagination->offset);
	
		$list['data'] = $queryTmp->createCommand($conn)->queryAll();
	
		return $list;
	}
	
	/**
	 * ????????????????????????????????????????????????
	 *
	 * @param	$shipping_service_id	????????????ID
	 * @param	$trackingNumber_str		textarea???????????????
	 * @return Array
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/01/05				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function saveCustomCarrierTrackingnumber($shipping_service_id, $trackingNumber_str){
		if (empty($shipping_service_id)){
			return self::output(array(), 1, '????????????????????????');
		}
		
		if ((strlen($trackingNumber_str)==0) || empty($trackingNumber_str)){
			return self::output(array(), 1, '?????????????????????');
		}
		
		$shippingService_obj = SysShippingService::findOne(['id'=>$shipping_service_id]);
		
		if($shippingService_obj == null){
			return self::output(array(), 1, '???????????????????????????????????????');
		}
		
		$trackingNumbers = explode("\n" ,$trackingNumber_str);
		Helper_Array::removeEmpty($trackingNumbers);
		
		$userName = \Yii::$app->user->identity->getFullName();
		if (strlen($userName)==0){
			$userName = $userName = \Yii::$app->user->identity->getUsername();
		}
		
		$exists = [];
		foreach ($trackingNumbers as $trackingNumber){
			$trackingNumber_obj = SysTrackingNumber::findOne(['tracking_number'=>$trackingNumber]);
			if ($trackingNumber_obj == null){
				$trackingNumber_obj = new SysTrackingNumber();
				$trackingNumber_obj->shipping_service_id = $shipping_service_id;
				$trackingNumber_obj->service_name = $shippingService_obj->service_name;
				$trackingNumber_obj->tracking_number = $trackingNumber;
				$trackingNumber_obj->is_used = 0;
				$trackingNumber_obj->user_name = $userName;
				$trackingNumber_obj->create_time = time();
				$trackingNumber_obj->update_time = time();
				$trackingNumber_obj->save();
			}else{
				$exists[] = $trackingNumber;
			}
		}
		
		return self::output($exists, 0, '');
	}
	
	/**
	 * ????????????????????????????????????????????????
	 *
	 * @param	$id	??????????????????ID
	 * @return Array
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/01/05				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function delCustomCarrierTrackingnumber($id){
		if(empty($id)){
			return self::output(array(), 1, '????????????Id????????????');
		}
		
		try {
			$result = SysTrackingNumber::deleteAll(['id'=>$id]);
			if ($result>0){
				return self::output(array(), 0, '????????????');
			}else{
				return self::output(array(), 1, '????????????');
			}
		}catch (\Exception $ex){
// 			exit(json_encode(array("code"=>"fail","message"=>$ex->getMessage())));
			return self::output(array(), 1, '????????????'.print_r($ex->getMessage(),true));
		}
	}
	
	/**
	 * ???????????????????????????????????????????????????
	 *
	 * @param	$id	??????????????????ID
	 * @return Array
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/01/05				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function tagDistributionCustomCarrierTrackingnumber($id, $distribution= false){
		if(empty($id)){
			return self::output(array(), 1, '????????????Id????????????');
		}
		
		$trackingNumberOne = SysTrackingNumber::find()->where(['id'=>$id])->one();
		
		if($trackingNumberOne == null){
			return self::output(array(), 1, '???????????????ID??????');
		}
		
		if($distribution == false){
			if($trackingNumberOne->is_used == 1){
				return self::output(array(), 1, '??????????????????????????????????????????.');
			}
			
			$trackingNumberOne->is_used = 1;
		}else{
			if($trackingNumberOne->is_used == 0){
				return self::output(array(), 1, '?????????????????????????????????????????????.');
			}
			
			$trackingNumberOne->is_used = 0;
			$trackingNumberOne->order_id = null;
		}
		
		$trackingNumberOne->use_time = time();
		
		if($trackingNumberOne->save(false)){
			return self::output(array(), 0, '?????????????????????.');
		}else{
			return self::output(array(), 1, '?????????????????????.');
		}
	}
	
	/**
	 * ????????????????????????????????????????????????????????????
	 *
	 * @param	$shipping_service_id	????????????ID
	 * @return Array
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/01/13				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCustomUnUseTrackingnumber($shipping_service_id){
		$trackingNumber_obj = SysTrackingNumber::find()->where(['shipping_service_id'=>$shipping_service_id])->andWhere(['is_used'=>0])->orderBy('id asc')->one();
		
		if($trackingNumber_obj == null){
			return self::output(array(), 1, '???????????????????????????????????????????????????');
		}
		
		return self::output($trackingNumber_obj->tracking_number, 0, '');
	}
		
	/**
	 * ?????????????????????????????????????????? ??????????????????
	 *
	 * @param	$shipping_service_id	????????????ID
	 * @param	$tracking_number		?????????
	 * @param	$order_id				??????????????????
	 * @return Array
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/01/13				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function saveCustomUnUseTrackingnumber($shipping_service_id, $tracking_number, $order_id){
		$trackingNumber_obj = SysTrackingNumber::find()->where(['shipping_service_id'=>$shipping_service_id,'tracking_number'=>$tracking_number])->andWhere(['is_used'=>0])->orderBy('id asc')->one();
	
		if($trackingNumber_obj == null){
			return self::output('', 1, '??????????????????????????????');
		}
	
		$trackingNumber_obj->order_id = $order_id;
		$trackingNumber_obj->is_used =1;
		$trackingNumber_obj->use_time =time();
		
		if($trackingNumber_obj->save(false)){
			return self::output('', 0, '??????');
		}else{
			return self::output('', 1, '??????');
		}
	}
	
	/**
	 * ????????????????????????
	 *
	 * @param
	 * @return 
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author				2016/01/05				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function saveShippingRules($id, $shippingRules){
// 		print_r($shippingRules);
// 		exit;
		
		$rule = MatchingRule::find()->where(['id'=>$id])->one();
		if (empty($rule)){
			$rule = new MatchingRule();
			$rule->uid = Yii::$app->user->identity->getParentUid();//??????????????????ID
		}
		$rule->operator = Yii::$app->user->identity->uid;
		$rule->rule_name=$shippingRules['name'];//?????????
		if(empty($shippingRules['priority']))
			$shippingRules['priority'] = CarrierOpenHelper::getMaxMatchingRulePriority();
		$rule->priority=$shippingRules['priority'];//?????????
		
		if(!empty($shippingRules['transportation_service_id']))
			$rule->transportation_service_id=$shippingRules['transportation_service_id'];
		
		if(isset($shippingRules['proprietary_warehouse_id']))
			$rule->proprietary_warehouse_id = empty($shippingRules['proprietary_warehouse_id']) ? 0 : $shippingRules['proprietary_warehouse_id'];
		
		$rule->is_active = $shippingRules['is_active'];
		//??????????????????
		if (!isset($shippingRules['rules']) || count($shippingRules['rules']) == 0){
			return self::output(array(), 1, '???????????????????????????!');
		}else{
			$rule->rules=$shippingRules['rules'];
			foreach ($shippingRules['rules'] as $rule_value){
				switch ($rule_value){
					case 'items_location_country':
						if (!isset($shippingRules['items_location_country']) || count($shippingRules['items_location_country']) == 0){
							return self::output(array(), 1, '????????????"??????????????????(ebay)"??????????????????????????????????????????');
						}else {
							$rule->items_location_country=$shippingRules['items_location_country'];
						};
						break;
					case 'items_location_provinces':
						if (count($shippingRules['myprovince_group']) > 0){
							$rule->items_location_provinces=json_encode($shippingRules['myprovince_group']);
						}else {
							return self::output(array(), 1, '????????????"??????????????????(ebay)"????????????????????????????????????/?????????');
						};
						break;
					case 'items_location_city':
						if (strlen($shippingRules['items_location_city']) > 0){
							$rule->items_location_city=$shippingRules['items_location_city'];
						}else {
							return self::output(array(), 1, '??????????????????????????????');
						};
						break;
					case 'receiving_country':
						if (!isset($shippingRules['receiving_country']) || count($shippingRules['receiving_country']) == 0){
							return self::output(array(), 1, '????????????"????????????"????????????????????????????????????');
						}else {
							$rule->receiving_country=$shippingRules['receiving_country'];
						};
						break;
					case 'receiving_provinces':
						if (strlen($shippingRules['receiving_provinces']) > 0){
							$rule->receiving_provinces=$shippingRules['receiving_provinces'];
						}else {
							return self::output(array(), 1, '??????????????????/?????????');
						};
						break;
					case 'receiving_city':
						if (strlen($shippingRules['receiving_city']) > 0){
							$rule->receiving_city=$shippingRules['receiving_city'];
						}else {
							return self::output(array(), 1, '????????????????????????');
						};
						break;
					case 'skus':
						if (count($shippingRules['sku_group']) > 0){
							$rule->skus=json_encode($shippingRules['sku_group']);
						}else {
							return self::output(array(), 1, '????????????"SKU"??????????????????????????????');
						};
						break;
					case 'sources':
						$tip = 0;
						if (!isset($shippingRules['sources']['source']) || count($shippingRules['sources']['source']) == 0){
							$rule->source = array();
							$tip++;
						}else {
							$rule->source=$shippingRules['sources']['source'];
						};
						if (!isset($shippingRules['sources']['site']) || count($shippingRules['sources']['site']) == 0){
							$rule->site = array();
							$tip++;
						}else {
							$rule->site=$shippingRules['sources']['site'];
						};
						if (!isset($shippingRules['sources']['selleruserid']) || count($shippingRules['sources']['selleruserid']) == 0){
							$rule->selleruserid = array();
							$tip++;
						}else {
							$rule->selleruserid=$shippingRules['sources']['selleruserid'];
						};
						if($tip >= 3){
							return self::output(array(), 1, '????????????"????????????????????????"????????????????????????????????????????????????');
						}
						break;
					case 'freight_amount':
						$freight_amount = $shippingRules['freight_amount'];
						if (strlen($freight_amount['min'])>0){
							if (preg_match("/\\D/",$freight_amount['min'])){
								return self::output(array(), 1, '?????????????????????????????????????????????');
							}
						}else{
							return self::output(array(), 1, '????????????"??????????????????"???????????????????????????????????????????????????');
						}
						if (strlen($freight_amount['max'])>0){
							if (preg_match("/\\D/",$freight_amount['max'])){
								return self::output(array(), 1, '?????????????????????????????????????????????');
							}
						}else{
							return self::output(array(), 1, '????????????"??????????????????"???????????????????????????????????????????????????');
						}
						if ($freight_amount['min'] >= $freight_amount['max']){
							return self::output(array(), 1, '???????????????????????????????????????????????????');
						}
						$rule->freight_amount=$shippingRules['freight_amount'];
						break;
					case 'buyer_transportation_service':
						if (!isset($shippingRules['buyer_transportation_service']) || count($shippingRules['buyer_transportation_service']) == 0){
							return self::output(array(), 1, '????????????"????????????????????????"????????????????????????????????????????????????');
						}else {
							$rule->buyer_transportation_service=$shippingRules['buyer_transportation_service'];
						};
						break;
					case 'total_amount':
						$total_amount = $shippingRules['total_amount'];
						if (strlen($total_amount['min'])>0){
// 							if (preg_match("/\\D/",$total_amount['min'])){
							if (!is_numeric($total_amount['min'])){
								return self::output(array(), 1, '??????????????????????????????????????????');
							}
						}else{
							return self::output(array(), 1, '????????????"?????????(USD???)"????????????????????????????????????????????????');
						}
						if (strlen($total_amount['max'])>0){
// 							if (preg_match("/\\D/",$total_amount['max'])){
							if (!is_numeric($total_amount['max'])){
								return self::output(array(), 1, '??????????????????????????????????????????');
							}
						}else{
							return self::output(array(), 1, '????????????"?????????(USD???)"????????????????????????????????????????????????');
						}
						if ($total_amount['min'] >= $total_amount['max']){
							return self::output(array(), 1, '????????????????????????????????????????????????');
						}
						$rule->total_amount=$shippingRules['total_amount'];
						break;
					case 'total_weight':
						$total_weight = $shippingRules['total_weight'];
						if (strlen($total_weight['min'])>0){
// 							if (preg_match("/\\D/",$total_weight['min'])){
							if (!is_numeric($total_weight['min'])){
								return self::output(array(), 1, '????????????????????????????????????');
							}
						}else{
							return self::output(array(), 1, '????????????"?????????"??????????????????????????????????????????');
						}
						if (strlen($total_weight['max'])>0){
// 							if (preg_match("/\\D/",$total_weight['max'])){
							if (!is_numeric($total_weight['max'])){
								return self::output(array(), 1, '????????????????????????????????????');
							}
						}else{
							return self::output(array(), 1, '????????????"?????????"??????????????????????????????????????????');
						}
						if ($total_weight['min'] >= $total_weight['max']){
							return self::output(array(), 1, '??????????????????????????????????????????');
						}
						$rule->total_weight=$shippingRules['total_weight'];
						break;
					case 'product_tag':
						if (!isset($shippingRules['product_tag']) || count($shippingRules['product_tag']) == 0){
							return self::output(array(), 1, '????????????"????????????"????????????????????????????????????');
						}else {
							$rule->product_tag=$shippingRules['product_tag'];
						};
						break;
					case 'postal_code':
						if (!isset($shippingRules['postal_code']) || count($shippingRules['postal_code']) == 0){
							return self::output(array(), 1, '????????????"??????"??????????????????????????????');
						}else {
							$rule->postal_code=$shippingRules['postal_code'];
						};
						break;
					case 'total_amount_new':
						$is_del_total_amount_new = false;
						if(isset($shippingRules['total_amount_new']) && (count($shippingRules['total_amount_new']) > 0)){
							foreach ($shippingRules['total_amount_new'] as $tmp_total_amount_new_key => $tmp_total_amount_new_val){
								if(empty($tmp_total_amount_new_val['min']) && empty($tmp_total_amount_new_val['max'])){
									unset($shippingRules['total_amount_new'][$tmp_total_amount_new_key]);
									$is_del_total_amount_new = true;
								}
							}
						}
						
						if(!isset($shippingRules['total_amount_new']) || (count($shippingRules['total_amount_new']) == 0)){
							if($is_del_total_amount_new == true){
								return self::output(array(), 1, '????????????"?????????(?????????)"?????????????????????????????????????????????????????????');
							}else{
								return self::output(array(), 1, '????????????"?????????(?????????)"?????????????????????????????????????????????');
							}
						}
						
						$rule->total_amount_new=json_encode($shippingRules['total_amount_new']);
						break;
				}
			}
		}
		$rule->created=time();
		$rule->updated=time();
		 
		 
		//?????????????????????
		if ($rule->isNewRecord){
			$count = MatchingRule::find()->where(['rule_name'=>$shippingRules['name']])->andWhere('created > 0')->count();
		}else{
			$count = MatchingRule::find()->where('rule_name = :rule_name and id <> :id',[':rule_name'=>$shippingRules['name'],':id'=>$id])->andWhere('created > 0')->count();
		}
	
		if ($count>0){
			return self::output(array(), 1, '???????????????');
		}else{
			if ($rule->save())
			{
				return self::output(array(), 0, '????????????');
			}else{
				return self::output(array(), 1, $rule->getFirstErrors());
			}
		}
	}
	
	/**
	 * ??????????????????????????????
	 *
	 * @param
	 * @return Array
	 * Array
		(
		    [carrier_memo] => Array
		        (
		            [product] => 1
		            [sku] => 0
		            [qty] => 0
		            [order_id] => 0
		        )
		    [customer_number_config] => Array
		        (
		            [ebay] => serial_random_6number
		            [aliexpress] => platform_id
		            [amazon] => platform_id
		            [dhgate] => platform_id
		            [lazada] => platform_id
		            [cdiscount] => platform_id
		            [jumia] => platform_id
		        )
		    [label_paper_size] => Array
		        (
		            [val] => 100x100
		            [template_width] => 100
		            [template_height] => 100
		        )
		    [label_optional_value] => Array
		        (
		            [100x100] => 100mm x 100mm
		            [210x297] => A4 (210mm x 297mm)
		            [customSize] => ?????????
		        )
		)
	 * 
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/01/05				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCommonCarrierConfig(){
		$carrierConfig = ConfigHelper::getConfig("CarrierOpenHelper/CommonCarrierConfig", 'NO_CACHE');

		$carrierConfig = json_decode($carrierConfig,true);
		
		if(!isset($carrierConfig['carrier_memo'])){
			$carrierConfig['carrier_memo'] = array('product' => 0,'sku' => 0,'qty' => 0,'order_id' => 0);
		}
		
		if(!isset($carrierConfig['customer_number_config'])){
			$carrierConfig['customer_number_config'] = array();
		}
		
		//????????????????????????
		$platformUseArr = PlatformAccountApi::getAllPlatformBindingSituation();
		
		//????????????????????????????????????????????????????????????
		foreach ($platformUseArr as $platformUseKey => $platformUseVal){
			if($platformUseVal == false){
				unset($carrierConfig['customer_number_config'][$platformUseKey]);
			}else if($platformUseVal == true){
				//?????????????????????????????????????????????????????????
				switch ($platformUseKey){
					case 'ebay':
						$carrier_order_id_mode = 'platform_id_date';//srn+??????
						break;
					case 'wish':
						$carrier_order_id_mode = 'serial_date';//???????????????+??????
						break;
					default:
						$carrier_order_id_mode = 'platform_id';//???????????????
						break;
				}
		
				$carrierConfig['customer_number_config'][$platformUseKey] = empty($carrierConfig['customer_number_config'][$platformUseKey]) ? $carrier_order_id_mode : $carrierConfig['customer_number_config'][$platformUseKey];
			}
		}
		
		if(!isset($carrierConfig['label_paper_size'])){
			$carrierConfig['label_paper_size'] = array('val' => '100x100','template_width' => 100,'template_height' => 100);
		}
		
		$carrierConfig['label_optional_value'] = array(
				'100x100' => '100mm x 100mm',
				'210x297' => 'A4 (210mm x 297mm)',
				'customSize' => '?????????',
		);
		
		return $carrierConfig;
	}
	
	/**
	 * ??????????????????
	 *
	 * @param	$params = array()	?????????????????? proprietary_warehouse???????????????????????????????????????
	 * @return
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/01/30				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getShippingMethodNameInfo($params = array(), $defaultPageSize = 10){
		$conn=\Yii::$app->subdb;
		
		$queryTmp = new Query;
		$queryTmp->select("a.*,IFNULL(`b`.`carrier_name`,`c`.`carrier_name`) as account_name")
			->from("sys_shipping_service a")
			->leftJoin("sys_carrier_account b", "b.id = a.carrier_account_id")
			->leftJoin("sys_carrier_custom c", "c.carrier_code = a.carrier_code")
			->where(['a.is_del'=>0]);
		
		if(isset($params['self_warehouse_id'])){
			$queryTmp->andWhere(['c.warehouse_id'=>$params['self_warehouse_id']]);
		}else if(isset($params['warehouse_id'])){
			$queryTmp->andWhere(['b.warehouse_id'=>$params['warehouse_id']]);
		}else{
			if(isset($params['not_used'])){
				if($params['not_used'] == false){
					$queryTmp->andWhere(['a.is_used'=>1]);
				}
			}else{
				$queryTmp->andWhere(['a.is_used'=>1]);
			}
		}
		
		if(isset($params['proprietary_warehouse'])){
			$warehousesType = WarehouseHelper::getWarehouseType($params['proprietary_warehouse']);
				
			if($warehousesType['is_oversea'] == 0){
				$queryTmp->andWhere(" (b.warehouse_id = -1 or c.warehouse_id = -1) ");
			}else{
				if($warehousesType['oversea_type'] == 0){
					$queryTmp->andWhere(["b.warehouse_id"=>$params['proprietary_warehouse']]);
				}else{
					$queryTmp->andWhere(["c.warehouse_id"=>$params['proprietary_warehouse']]);
				}
			}
			
			//???????????????????????????
			$queryTmp->andWhere(" (b.is_used = 1 or c.is_used = 1) ");
		}
		
		if(isset($params['not_proprietary_warehouse'])){
			$queryTmp->andWhere("ifnull(a.proprietary_warehouse,'') = '' or a.proprietary_warehouse not like :proprietary_warehouse ",[':proprietary_warehouse'=>'%"'.$params['not_proprietary_warehouse'].'"%']);
		}
		
		if(isset($params['warehouse_shipping_name'])){
			$queryTmp->andWhere(['like','a.service_name',$params['warehouse_shipping_name']]);
		}
		
		if(isset($params['warehouse_carrier_code'])){
			$queryTmp->andWhere(['a.carrier_code'=>$params['warehouse_carrier_code']]);
		}
		
		if(isset($params['shipping_id'])){
			if(!empty($params['shipping_id']))
				$queryTmp->andWhere(['a.id'=>$params['shipping_id']]);
		}
		
		if(isset($params['shipping_method_code'])){
			$queryTmp->andWhere(['a.shipping_method_code'=>$params['shipping_method_code']]);
		}
		
		if($defaultPageSize > 0){
			$DataCount = $queryTmp->count("1", $conn);
			
			$pagination = new Pagination([
					'defaultPageSize' => 10,
					'pageSize' => $defaultPageSize,
					'totalCount' => $DataCount,
					'pageSizeLimit'=>[10,200],//????????????????????????
					]);
			
			$list['pagination'] = $pagination;
			
			$queryTmp->limit($pagination->limit);
			$queryTmp->offset($pagination->offset);
		}
		
		$queryTmp->orderBy('a.is_used desc');
		
		$shipMethodArr = $queryTmp->createCommand($conn)->queryAll();
		$shipMethodList = array();
		foreach ($shipMethodArr as $shipMethod){
			$shipMethodList[$shipMethod['id']] = $shipMethod;
		}
		
		$list['data'] = $shipMethodList;
		
		return $list;
	}
	
	/**
	 * ??????????????????????????????????????????
	 *
	 * @param	$shipping_id	????????????ID
	 * @param	$warehouse_id	??????ID
	 * @return
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/02/04				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function shippingMethodRemoveWarehouse($shipping_id, $warehouse_id){
		$shipping = SysShippingService::findOne($shipping_id);
		
		if($shipping == null){
			return self::output('', 1, '?????????????????????');
		}
		
		$warehouseArr = $shipping->proprietary_warehouse;
		
		foreach ($warehouseArr as $key => $val){
			if($val == $warehouse_id){
				unset($warehouseArr[$key]);
// 				break;
			}
		}
		
		$shipping->proprietary_warehouse = $warehouseArr;
		
		if($shipping->save(false)){
			return self::output('', 0, '????????????');
		}else{
			return self::output('', 1, '????????????');
		}
	}
	
	public static function shippingMethodAddWarehouse($params){
		if(!isset($params['warehouse_id'])){
			return self::output('', 1, '????????????????????????e_1');
		}
		
		if(empty($params['selectShip'])){
			return self::output('', 1, '????????????????????????e_2');
		}
		
		$shippings = SysShippingService::find()->where(['id'=>$params['selectShip']])->all();
		
		foreach ($shippings as $shippingOne){
			$tmp_warehouse = empty($shippingOne->proprietary_warehouse) ? array() : $shippingOne->proprietary_warehouse;
			
			if(!in_array($params['warehouse_id'], $tmp_warehouse)){
				$tmp_warehouse[] = $params['warehouse_id'];
				$shippingOne->proprietary_warehouse = $tmp_warehouse;
				
				$shippingOne->save(false);
			}
		}
		
		return self::output('', 0, '????????????');
	}
	
	/**
	 * ??????????????????????????????
	 *
	 * @param	$carrierConfig = array()
	 * 			Array
				(
					'carrier_memo' => Array
						(
							'product' => 1,
							'sku' => 0,
							'qty' => 0,
							'order_id' => 0
						),
					'customer_number_config' => Array
						(
							'ebay' => 'serial_random_6number',
							'aliexpress' => 'platform_id',
							'amazon' => 'platform_id',
							'dhgate' => 'platform_id'
						),
					'label_paper_size' => Array
						(
							'val' => '100x100',
							'template_width' => 100,
							'template_height' => 100
						)
				);
	 * @return
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/01/05				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function setCommonCarrierConfig($carrierConfig){
		$result = ConfigHelper::setConfig("CarrierOpenHelper/CommonCarrierConfig", json_encode($carrierConfig));
		
		if($result){
			return self::output(array(), 0, '????????????');
		}else{
			return self::output(array(), 1, '????????????');
		}
	}
	
	/**
	 * ??????????????????????????????????????????????????????
	 *
	 * @param	$id	????????????ID
	 * @return
	 * Array
		(
		    [is_api_print] => 1		????????????API??????,1????????? 0????????????
		    [is_print] => 0			????????????????????????,1????????? 0????????????
		    [is_custom_print] => 0	???????????????????????????,1????????? 0????????????
		)
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/01/19				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getShippingServicePrintMode($id){
		$userShippingSevice = SysShippingService::find()->select(['carrier_code','shipping_method_code','third_party_code',
				'print_params','is_custom','shipping_method_name','carrier_name'])->where(['id'=>$id])->asArray()->one();
		$userShippingSevice['print_params'] = unserialize($userShippingSevice['print_params']);
		
		if((!empty($userShippingSevice['print_params']['label_custom']['carrier_lable'])) || !empty($userShippingSevice['print_params']['label_custom']['declare_lable']) || !empty($userShippingSevice['print_params']['label_custom']['items_lable']))
			$is_custom_print = 1;
		else
			$is_custom_print = 0;
		
		if($userShippingSevice['is_custom'] == 1){
			return array('is_api_print'=>0,'is_print'=>0,'is_custom_print'=>$is_custom_print,
					'shipping_method_name'=>$userShippingSevice['shipping_method_name'],'carrier_name'=>$userShippingSevice['carrier_name']
			);
		}
		
		$sys_method = SysShippingMethod::find()->where(['carrier_code'=>$userShippingSevice['carrier_code'],'shipping_method_code'=>$userShippingSevice['shipping_method_code'],'third_party_code'=>(empty($userShippingSevice['third_party_code']) ? '' : empty($userShippingSevice['third_party_code']))])->asArray()->one();
		
		$is_high_print = 0;
		
		if($sys_method['is_print'] == 1){
			if((!empty($userShippingSevice['print_params']['label_littleboss']))){
				$is_high_print = 1;
			}
		}
		
		return array('is_api_print'=>$sys_method['is_api_print'],'is_print'=>$is_high_print,'is_custom_print'=>$is_custom_print,
				'shipping_method_name'=>$userShippingSevice['shipping_method_name'],'carrier_name'=>$userShippingSevice['carrier_name']);
	}
	
	/**
	 * ????????????????????????Map
	 *
	 * @param	$carrier_code	????????????
	 * @return
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/02/15				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getShippingOrerseaWarehouseMap($carrier_code = '',$is_show_carrier_code = false){
		$conn=\Yii::$app->db;
		
		$queryTmp = new Query;
		$queryTmp->select("a.carrier_code,a.third_party_code,a.template,b.carrier_name")
		->from("sys_shipping_method a")
		->leftJoin("sys_carrier b", "b.carrier_code = a.carrier_code")
		->where('b.carrier_type=1');
		
		if(!empty($carrier_code)){
			$queryTmp->andWhere(['a.carrier_code'=>$carrier_code]);
		}
		
		$queryTmp->groupBy('a.carrier_code,a.third_party_code,a.template,b.carrier_name');
		$queryTmp->orderBy('a.carrier_code');
		
		$shipOrerseaWarehouseArr = $queryTmp->createCommand($conn)->queryAll();
		
		$shipOrerseaWarehouseList = array();
		
		foreach ($shipOrerseaWarehouseArr as $val){
			if($is_show_carrier_code == false)
				$shipOrerseaWarehouseList[$val['third_party_code']] = $val['template'];
			else{
				if(!isset($shipOrerseaWarehouseList[$val['carrier_code']]))
					$shipOrerseaWarehouseList[$val['carrier_code']] = array();
				
				$shipOrerseaWarehouseList[$val['carrier_code']][$val['third_party_code']] = $val['template'];
			}
		}
		
		return $shipOrerseaWarehouseList;
	}
	
	/**
	 * ??????????????????????????????
	 *
	 * @param	$params				??????????????????
	 * @param	$orderby_params		??????????????????
	 * @return
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/03/02				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCarrierAccountAdderss($params = array(),$orderby_params = array()){
		$query = CarrierUserAddress::find();
		
		if(isset($params['is_del']))
			$query->andWhere(['is_del'=>$params['is_del']]);
		
		if(isset($params['type'])){
			$query->andWhere(['type'=>$params['type']]);
		}
		
		if(isset($params['carrier_code'])){
			$query->andWhere(['carrier_code'=>$params['carrier_code']]);
		}
		
		if(!empty($orderby_params)){
			$tmpOrderbystr = '';
			foreach ($orderby_params as $orderby_param_key => $orderby_param_val){
				$tmpOrderbystr .= empty($tmpOrderbystr) ? ($orderby_param_key.' '.$orderby_param_val) : (','.$orderby_param_key.' '.$orderby_param_val);
			}
			
			$query->orderBy($tmpOrderbystr);
		}
	
		$tmpAddressArr = array();
		$addressArr = $query->asArray()->all();
	
		foreach ($addressArr as $address){
			$tmpAddressArr[$address['id']] = $address;
			$tmpAddressArr[$address['id']]['address_params'] = unserialize($tmpAddressArr[$address['id']]['address_params']);
		}
		
		return $tmpAddressArr;
	}
	
	/**
	 * ?????????????????????????????????????????????????????????
	 *
	 * @param	$carrier_code	???????????????
	 * @param	$address_id		????????????ID
	 * @return
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/03/02				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCarrierAccountAddressInfoByAddressId($carrier_code, $address_id = 0){
		$params['is_del'] = 0;
		$params['type'] = 0;
		$params['carrier_code'] = $carrier_code;
		
		$orderby_params['is_default'] = 'DESC';
		
		$addressArr = self::getCarrierAccountAdderss($params, $orderby_params);
		
		if(isset($addressArr[$address_id])){
			return $addressArr[$address_id]['address_params'];
		}else{
			$tmpAddress = current($addressArr);
			reset($addressArr);
			
			return $tmpAddress['address_params'];
		}
	}
	
	/**
	 * ??????????????????????????????
	 *
	 * @param	$params	????????????????????????
	 * @return
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/03/09				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getShippingServiceInfo($params = array()){
		$open_carriers = CarrierOpenHelper::getOpenCarrierArr(2, 1);
			
		$open_carriers = array_keys($open_carriers);
			
		$conn=\Yii::$app->subdb;
			
		$queryTmp = new Query;
		
		$queryTmp->select("a.*,`b`.`is_del` as `as_is_del`")
			->from("sys_shipping_service a")
			->leftJoin("sys_carrier_account b","b.id = a.carrier_account_id")
			->leftJoin("sys_carrier_custom c","c.carrier_code = a.carrier_code");
		
		$queryTmp->andWhere(['a.is_used'=>1]);
		$queryTmp->andWhere(['a.is_del'=>0]);
			
		if(isset($params['proprietary_warehouse'])){
			$warehousesType = WarehouseHelper::getWarehouseType($params['proprietary_warehouse']);
			
			if($warehousesType['is_oversea'] == 0){
				$queryTmp->andWhere(" (b.warehouse_id = -1 or c.warehouse_id = -1) ");
			}else{
				if($warehousesType['oversea_type'] == 0){
					$queryTmp->andWhere(["b.warehouse_id"=>$params['proprietary_warehouse']]);
				}else{
					$queryTmp->andWhere(["c.warehouse_id"=>$params['proprietary_warehouse']]);
				}
			}
			
			if($warehousesType['oversea_type'] == 0){
				$queryTmp->andwhere(['in','a.carrier_code',$open_carriers]);
			}
		}else{
			$queryTmp->andwhere(['in','a.carrier_code',$open_carriers]);
		}
		
		$queryTmp->andWhere(['IFNULL(`b`.`is_used`,`c`.`is_used`)'=>1]);
			
		$shipping_serviceArr = $queryTmp->createCommand($conn)->queryAll();
		$tmpshipping_serviceArr = array();
		
		foreach ($shipping_serviceArr as $shipping_serviceVal){
			if($shipping_serviceVal['as_is_del'] == 1) continue;
			
			$tmpshipping_serviceArr[$shipping_serviceVal['id']] = $shipping_serviceVal;
		}
		
		return $tmpshipping_serviceArr;
	}
	
	/**
	 * ?????????????????????????????????????????????Map
	 *
	 * @param	$warehouseId	??????ID
	 * @return
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/03/09				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getShippingServiceIdNameMapByWarehouseId($warehouseId){
		$params['proprietary_warehouse'] = $warehouseId;
		$shipping_serviceArr = self::getShippingServiceInfo($params);
		
		$shippingIdNameMap = array();
		
		//?????????????????????????????????????????????????????????????????????
		$sysShippingMethodClose = SysShippingMethod::find()->select('concat(`carrier_code`, `shipping_method_code`, `third_party_code`) code')->where(['is_close'=>1])->andWhere("carrier_code in (select carrier_code from sys_carrier where carrier_type=0)")->asArray()->all();
		if(!empty($sysShippingMethodClose)){
			$sysShippingMethodClose = Helper_Array::toHashmap($sysShippingMethodClose, 'code', 'code');
		}else{
			$sysShippingMethodClose = array();
		}
		
		foreach ($shipping_serviceArr as $shipping_service){
			//????????????????????????
			if(in_array($shipping_service['carrier_code'].$shipping_service['shipping_method_code'], $sysShippingMethodClose)){
				continue;
			}
			
			$shippingIdNameMap[$shipping_service['id']] = $shipping_service['service_name'];
		}
		
		return $shippingIdNameMap;
	}
	
	/**
	 * ?????????????????????????????????
	 *
	 * @param	$id			??????id ??????sys_carrier_account?????????id
	 * @param	$is_used	???????????????	0???????????????1????????????
	 * @return Array
	 * Array
	 (
	 [response] => Array
		 (
			 [code] => 0
			 [msg] => '????????????.'
			 [data] => Array
				 (
				 )
		 )
	 )
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/03/10				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function carrierAccountOpenOrCloseById($id, $is_used = 0){
		if($is_used == 1){
			$type = '??????';
		}else{
			$type = '??????';
		}
	
		if(empty($id)){
			return self::output(array(), 1, '????????????,??????????????????id');
		}
	
		$account = SysCarrierAccount::find()->where(['id'=>$id,'is_del'=>0])->one();
	
		if($account == null){
			return self::output(array(), 1, '????????????,??????????????????');
		}
	
		if($is_used == 0){
			//????????????????????????????????????????????????????????????????????????
			if($account['carrier_type'] == 0){
				$ship_obj_colse = SysShippingMethod::find()->select(['shipping_method_code'])->where(['carrier_code'=>$account['carrier_code'],'is_close'=>1])->asArray()->all();
				$ship_obj_colse = Helper_Array::toHashmap($ship_obj_colse, 'shipping_method_code', 'shipping_method_code');
				
				SysShippingService::updateAll(['is_used'=>'0'], ['carrier_code'=>$account['carrier_code'],'shipping_method_code'=>$ship_obj_colse]);
			}
			
			$countShippingService = SysShippingService::find()->where(['carrier_account_id'=>$id,'is_del'=>0,'is_used'=>1])->count();
		
			if($countShippingService > 0){
				return self::output(array(), 1, $type.'??????,?????????????????????????????????.');
			}
			
			$account->api_params='';
		}
	
		$account->is_used = $is_used;
	
		if($account->save()){
			return self::output(array(), 0, $type.'??????.');
		}else{
			return self::output(array(), 1, $type.'??????.');
		}
	}
	
	public static function getCarrierCustomLabelTemplates($params, $version = 0){
		$templates = CrTemplate::find();
		$templates->andWhere(['template_version'=>$version]);
		
		//??????????????????????????? ?????????????????????
		if(isset($params['template_name']) && isset($params['selftemplate']) && !empty($params['selftemplate']))
			$templates->andWhere(['like','template_name',$params['template_name']]);
		$sort = new Sort([
				'attributes' => ['template_name','update_time','template_type'],
				'params'=>$params
				]);
		$pagination = new Pagination([
				'defaultPageSize' => 20,
				'pageSizeLimit'=>[15,20,50,100,200],
				'params' => $params
				]);
		$pagination->totalCount = $templates->count();
		$data = $templates
		->offset($pagination->offset)
		->limit($pagination->limit)
		->orderBy($sort->orders)
		->all();
		return ['data'=>$data,'pagination'=>$pagination,'sort'=>$sort];
		
	}
	public static function getCarrierSysLabelTemplates($params, $version = 0){
		//?????????????????????
		$sys_templates = CrCarrierTemplate::find();
		$sys_templates->where(['is_use'=>1]);
		$sys_templates->andWhere(['template_version'=>$version]);
		$sys_sort = new Sort([
				'attributes' => ['template_name','create_time','template_type'],
				'params'=>$params
				]);
		//?????????????????????
		$size = '';
		if($size = \Yii::$app->request->get('size')){
			switch($size){
				case 0:$height = 100;$width = 100;break;
				case 1:$height = 50;$width = 100;break;
				case 2:$height = 297;$width = 210;break;
				case 3:$height = 30;$width = 80;break;
				default:$height = 100;$width = 100;break;
			}
			$sys_templates->andWhere(['template_width'=>$width,'template_height'=>$height]);
		}
		//??????????????????????????? ?????????????????????
        if(isset($params['template_name']) && empty($params['selftemplate']))
			$sys_templates->andWhere(['like','template_name',\Yii::$app->request->get('template_name')]);
		$sys_pagination = new Pagination([
				'defaultPageSize' => 20,
				'pageSizeLimit'=>[15,20,50,100,200],
				'params' => $params,
				]);
		$sys_pagination->totalCount = $sys_templates->count();
		
		$sys_data = $sys_templates
		->offset($sys_pagination->offset)
		->limit($sys_pagination->limit)
		->orderBy($sys_sort->orders)
		->all();
		
		return ['data'=>$sys_data,'pagination'=>$sys_pagination,'sort'=>$sys_sort,'size'=>$size];
	}
	public static function getCarrierTemplateById($type,$id,$params){
		if(isset($id) && !empty($id)){
			//??????????????????????????????cr_sys_template
			if($type)
				$template = CrCarrierTemplate::findOne($id);
			else
				$template = CrTemplate::findOne($id);
		}else{
			$template = new CrTemplate();
			$template->template_id = '';
			$template->template_name = @$params['template_name'];
			$template->template_type = @$params['template_type'];
			$template->template_width = $params['width'];
			$template->template_height = $params['height'];
			$template->template_content = base64_decode(@$params['template_content']);
		}
		return $template;
	}
	public static function copySysTemplateToCustom($sys_id,$name){
		//??????
		$systemplate = CrCarrierTemplate::findOne($sys_id);
		if($systemplate === null) return self::output('',1,'??????????????????????????????');
		$selftemplate = new CrTemplate;
		$selftemplate->template_name = $name;
		$selftemplate->template_content = $systemplate->template_content;
		$selftemplate->create_time = time();
		$selftemplate->template_height = $systemplate->template_height;
		$selftemplate->template_width = $systemplate->template_width;
		$selftemplate->template_type = $systemplate->template_type;
		$selftemplate->template_version = $systemplate->template_version;
		$selftemplate->template_content_json = $systemplate->template_content_json;
		
		try{
			if($selftemplate->save())
				return self::output('',0,'????????????');
		}catch(\Exception $e){return self::output('',1,$e);}
	}
	
	//?????????????????????
	public static function copyCusTemplateToCustom($sys_id,$name){
		//??????
		$systemplate = CrTemplate::findOne($sys_id);
		if($systemplate === null) return self::output('',1,'??????????????????????????????');
		$selftemplate = new CrTemplate;
		$selftemplate->template_name = $name;
		$selftemplate->template_content = $systemplate->template_content;
		$selftemplate->create_time = time();
		$selftemplate->template_height = $systemplate->template_height;
		$selftemplate->template_width = $systemplate->template_width;
		$selftemplate->template_type = $systemplate->template_type;
		$selftemplate->template_version = $systemplate->template_version;
		$selftemplate->template_content_json = $systemplate->template_content_json;
	
		try{
			if($selftemplate->save())
				return self::output('',0,'????????????');
		}catch(\Exception $e){return self::output('',1,$e);}
	}
	
	public static function saveCustomPrintLabel($params){
		if(!isset($params['id']) || !$template = CrTemplate::findOne($params['id'])){
			$template = new CrTemplate();
			$template->create_time = time();
			$template->template_width = $params['width'];
			$template->template_height = $params['height'];
			$template->template_type = $params['template_type'];
		}
		$template->template_name = $params['name'];
		$template->update_time = time();
		$template->template_content = base64_decode($params['html']);
		if($template->save()){
			$res = ['error'=>0,'data'=>$template];
		}else{
			$res = ['error'=>500,'message'=>'????????????'];
		}
		return $res;
	}
	public static function delCustomTemplate($template_id){
		$data = [];
		if(isset($template_id) && $template = CrTemplate::findOne($template_id)){
			if($template->delete()){
				$data = ['error'=>0,'message'=>'????????????'];
			}else{
				$data = ['error'=>500,'message'=>'????????????'];
			}
		}else{
			$data = ['error'=>400,'message'=>'????????????'];
		}
		return $data;
	}
	public static function Checktemplatename($name){
		return CrTemplate::find()->where(['template_name'=>$name])->one()?'exists':false;
	}
	public static function checkCarrierHasAccount($carrier_code){
		
		$query = CarrierUseRecord::find()->where(['carrier_code'=>$carrier_code,'is_del'=>0,'is_active'=>0])->one();
		
		$query2 = SysCarrierAccount::find()->where(['carrier_code'=>$carrier_code,'is_del'=>0])->one();
		
		$result = false;
		
		if(!empty($query) && !empty($query2)){
			$result = true;
		}
		
		return $result;
	}
	
	public static function getCarrierAccountInfoByWarehouseId($carrier_code, $warehouse_id){
		$query = SysCarrierAccount::find();
		$query->andWhere(['carrier_code'=>$carrier_code]);
		$query->andWhere(['warehouse_id'=>$warehouse_id]);
		
		$query->andWhere(['is_del'=>0]);
		
		$accountArr = $query->asArray()->all();
		
		self::StrToUnserialize($accountArr,array('address','api_params','warehouse'));
		
		return $accountArr;
	}
	
	/*
	 * ???????????????????????? ??????======================
	 */
	protected static function output($data, $code = 0, $msg = '') {
		$output = ['response'=>['code'=>$code, 'msg'=>$msg, 'data'=>$data]];
		return $output;
	}
	
	
	protected static function debug($msg) {
		$file = $this->log_path.get_class($this).'.debug';
		$this->write_log($msg, $file);
	}
	
	
	protected static function log($msg, $file_path = '') {
		if(!empty($file_path) && @is_writable($file_path)) {
			$file = $file_path;
		}else {
			$file = $this->log_path.get_class($this).'.log';
		}
		$this->write_log($msg, $file);
	
	}
	
	/**
	 * ???????????????????????????????????????
	 *
	 * @param	$dataArr	?????????????????????
	 * @param	$fieldArr	?????????????????????
	 * @return Array $dataArr
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2015/12/02				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function StrToUnserialize(&$dataArr, $fieldArr){
		foreach ($dataArr as &$dataOne){
			foreach ($fieldArr as $fieldOne){
				$dataOne[$fieldOne] = unserialize($dataOne[$fieldOne]);
			}
		}
	}
	/*
	 * ???????????????????????? ??????======================
	 */
	
	/**
	 * ?????????????????????qtip
	 *
	 * @return Array
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/03/10				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCarrierQtips(){
		$carrierQtips = SysCarrierParam::find()->select(['id as qtip_id','param_describe as qtip_val'])->where(" param_describe != '' ")->asArray()->all();
		
		foreach ($carrierQtips as &$carrierQtip){
			$carrierQtip['qtip_id'] = 'carrier_qtip_'.$carrierQtip['qtip_id'];
		}
		
		$carrierQtips[] = array('qtip_id'=>'carrier_qtip_ajinformation','qtip_val'=>'?????????????????????????????????????????????????????????????????????');
		
		return $carrierQtips;
	}
	
	/**
	 * ?????????????????????
	 *
	 * @return ?????????
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/04/15				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getOrderShippedTrackingNumber($order_id,$customer_number,$shipping_service_id,$carrier_code = ''){
		if(empty($carrier_code) || (substr($carrier_code, 0, 3) == 'lb_')){
			$orderShippedArr = OdOrderShipped::find()->where(['order_id'=>$order_id,'shipping_service_id'=>$shipping_service_id])->orderBy('id desc')->asArray()->all();
		}else{
			$orderShippedArr = OdOrderShipped::find()->where(['order_id'=>$order_id])->orderBy('id desc')->asArray()->all();
		}

		if((empty($orderShippedArr)) || (count($orderShippedArr) == 0)){
			return '';
		}
		
		if(count($orderShippedArr) == 1){
			return $orderShippedArr[0]['tracking_number'];
		}
		
		foreach ($orderShippedArr as $orderShipped){
			if($orderShipped['customer_number'] == $customer_number){
				return $orderShipped['tracking_number'];
			}
		}
		
		return '';
	}
	
	/**
	 * ?????????????????????????????????????????????????????????????????????????????????????????????carrier_code
	 * @param $warehouseId
	 * @param $warehouseName
	 */
	public static function getCustomCarrierCodeByWarehouseId($warehouseId, $warehouseName){
		$customOne = SysCarrierCustom::find()->where(['warehouse_id'=>$warehouseId])->one();
		
		if($customOne == null){
			if(empty($warehouseName)){
				$warehouseInfoArr=InventoryHelper::getAllWarehouseInfo(false,array('warehouse_id'=>$warehouseId));
				
				if(count($warehouseInfoArr) > 0){
					$warehouseInfo = current($warehouseInfoArr);
					reset($warehouseInfoArr);
					
					$warehouseName = $warehouseInfo['name'];
				}else{
					return self::output(array(), 1, '????????????,??????????????????');
				}
			}
			
			$customParams = array();
			$customParams['carrier_name'] = $warehouseName.'self';
			$customParams['is_used'] = 1;
			$customParams['carrier_type'] = 1;
			$customParams['warehouse_id'] = $warehouseId;
			 
			$resCustomCarrier = CarrierOpenHelper::saveCustomCarrier('', $customParams);
			
			if($resCustomCarrier['response']['code'] == 0){
				return self::output($resCustomCarrier['response']['data'], 0, '??????');
			}else{
				return self::output(array(), 1, '????????????');
			}
		}else{
			return self::output($customOne->carrier_code, 0, '');
		}
	}
	
	/**
	 * ??????????????????????????????????????????
	 *
	 * @param	$shipping_id	????????????id
	 * @param	$common_id		?????????????????????ID?????????ID
	 * @param	$edit_type		????????????
	 * @return Array
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/06/02				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function editCarrierAccountShipping($shipping_id, $common_id, $edit_type){
		if((empty($shipping_id) || empty($common_id)) && ($edit_type == 'shipping_account')){
			return self::output(array(), 1, '????????????Id????????????');
		}
	
		try {
			$shipping = SysShippingService::find()->where(['id'=>$shipping_id])->one();
			
			if($shipping == null){
				return self::output(array(), 1, '????????????_e01');
			}
			
			if($edit_type == 'shipping_account')
				$shipping->carrier_account_id = $common_id;
			else if($edit_type == 'shipping_address')
				$shipping->common_address_id = $common_id;
			
			if ($shipping->save(false)){
				return self::output(array(), 0, '????????????');
			}else{
				return self::output(array(), 1, '????????????');
			}
		}catch (\Exception $ex){
			return self::output(array(), 1, '????????????'.print_r($ex->getMessage(),true));
		}
	}
	
	/**
	 * ???????????????????????????????????????????????????????????????
	 *
	 * @param	$params	????????????id
	 * @return Array
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/06/03				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCarrierAccountInfoByShippingId($params = array()){
		$conn=\Yii::$app->subdb;
		
		$queryTmp = new Query;
		
		$queryTmp->select("a.id,a.carrier_code,a.api_params,b.id as server_id,b.carrier_params,b.shipping_method_code,b.customer_number_config,b.declaration_max_value")
			->from("sys_carrier_account a")
			->leftJoin("sys_shipping_service b", "b.carrier_account_id=a.id");
		
		if(isset($params['shippings'])){
			$queryTmp->andWhere(['b.id'=>$params['shippings']]);
		}
		
		$tmpShipAccount = $queryTmp->createCommand($conn)->queryAll();
		
		$shipAccountArr = array();
		
		//?????????????????????api_class
		$carriers = SysCarrier::find()->select(['carrier_code','api_class','carrier_type'])->asArray()->all();
		foreach ($carriers as $carrierone){
			$carrier[$carrierone['carrier_code']] = array('api_class'=>$carrierone['api_class'],'carrier_type'=>$carrierone['carrier_type']);
		}
		unset($carriers);
		
		foreach ($tmpShipAccount as $tmpShipAccountone){
			$tmpShipAccountone['api_params'] = unserialize($tmpShipAccountone['api_params']);
			$tmpShipAccountone['carrier_params'] = unserialize($tmpShipAccountone['carrier_params']);
			$tmpShipAccountone['customer_number_config'] = unserialize($tmpShipAccountone['customer_number_config']);
			$tmpShipAccountone['api_class'] = isset($carrier[$tmpShipAccountone['carrier_code']]['api_class']) ? $carrier[$tmpShipAccountone['carrier_code']]['api_class'] : '';
			$tmpShipAccountone['carrier_type'] = isset($carrier[$tmpShipAccountone['carrier_code']]['carrier_type']) ? $carrier[$tmpShipAccountone['carrier_code']]['carrier_type'] : '';
			
			switch ($tmpShipAccountone['carrier_code']){
				case 'lb_winit':
					//winit??????????????????????????????????????????code
					$winit = new \common\api\carrierAPI\LB_WANYITONGCarrierAPI;
					$warehouse = $winit->getWareHouseList($tmpShipAccountone['shipping_method_code'],$tmpShipAccountone['server_id']);
					if($tmpShipAccountone['carrier_params']['dispatchType'] === 'S' && count($warehouse)>0){
						//???????????????????????????
						if(is_array($warehouse['data'])){
							$warehouseCodeList = Helper_Array::toHashMap($warehouse['data'],'warehouseCode','warehouseName');
						}else{
							$warehouseCodeList = array();
						}
						
						$tmpShipAccountone['additional_arr'] = $warehouseCodeList;
					}
					break;
				case 'lb_winitOversea':
					//????????????????????????
					$data = [
					'deliveryWayID'=>$tmpShipAccountone['shipping_method_code'],
					'accountid'=>$tmpShipAccountone['id']
					];
					$winit = new \common\api\overseaWarehouseAPI\LB_WANYITONGOverseaWarehouseAPI;
					$return = $winit->getInsuranceType($data);
					$arr = [];
					if(count($return)>0){
						if(isset($return[0]['insuranceID'])){
							foreach($return as $v){
								$arr[$v['insuranceID']] = $v['insuranceType'];
							}
						}
					}
					$tmpShipAccountone['additional_arr'] = $arr;
					break;
			}
			
			$shipAccountArr[$tmpShipAccountone['server_id']] = $tmpShipAccountone;
		}
		
		return $shipAccountArr;
	}
	
	/**
	 * ?????????????????????????????????????????????????????????
	 *
	 * @param	$params	????????????
	 * @return Array
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/06/03				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getSysCarrierParams($params = array()){
		$sys_carrier_params = array();
		
		$tmpcarrier_params = SysCarrierParam::find()
			->select(['id','carrier_code','carrier_param_key','carrier_param_name','carrier_param_value','display_type','type','is_required','data_key','param_describe','is_hidden'])
			->where(['carrier_code'=>$params['carrier_codes']])->andWhere('type in (2,3)')->asArray()->orderBy('carrier_code,sort asc')->all();
		
		foreach ($tmpcarrier_params as $tmp_params_val){
			if(!isset($sys_carrier_params[$tmp_params_val['carrier_code']])){
				$sys_carrier_params[$tmp_params_val['carrier_code']] = array('order_params'=>array(),'item_params'=>array());
			}
			
			$tmp_params_val['carrier_param_value'] = unserialize($tmp_params_val['carrier_param_value']);
			
			if($tmp_params_val['type'] == 2){
				$sys_carrier_params[$tmp_params_val['carrier_code']]['order_params'][] = $tmp_params_val;
			}else{
				$sys_carrier_params[$tmp_params_val['carrier_code']]['item_params'][] = $tmp_params_val;
			}
		}
		
		return $sys_carrier_params;
	}
	
	/**
	 * ?????????????????????view???html??????
	 * 
	 * @param $order
	 * @param $order_products
	 * @param $sys_carrier_params
	 * @param $carrierAccountInfo
	 * @param $warehouseNameMap
	 * @return string
	 */
	public static function getOrdersCarrierInfoView($order, $order_products, $sys_carrier_params, $carrierAccountInfo, $warehouseNameMap, $item_declared_info){
// 		$current_time=explode(" ",microtime());
// 		$start1_time=round($current_time[0]*1000+$current_time[1]*1000);

		//?????????????????????????????????,?????????????????????,?????????????????????????????????
		$isCarrierNewVersion = self::getCarrierNewVersion($order->default_carrier_code);
		
		//??????????????????????????????????????????????????????$carrierAccountInfo[$order->default_shipping_method_code]?????????????????????????????????????????????
		if(!isset($carrierAccountInfo[$order->default_shipping_method_code])){
			$carrierAccountInfo[$order->default_shipping_method_code] = array();
		}
		
// 		print_r($carrierAccountInfo);
// 		exit;
		
		//4px????????????????????????????????????Item
		$other_params = array();
		if($order->default_carrier_code == 'lb_4px'){
			if(!empty($carrierAccountInfo[$order->default_shipping_method_code]['api_params']['isMoreThanTwo'])){
				$tmpCheckProductCodeCanMoreThanTwo = \common\api\carrierAPI\LB_4PXCarrierAPI::checkProductCodeCanMoreThanTwoItems($carrierAccountInfo[$order->default_shipping_method_code]['shipping_method_code'], $order->consignee_country_code);
				
				if($tmpCheckProductCodeCanMoreThanTwo == false){
					$other_params['is_merge'] = 1;
				}
			}
		}
		
		$declarationInfo = self::getCustomsDeclarationInfo($order, $order_products, $warehouseNameMap, $carrierAccountInfo[$order->default_shipping_method_code], $other_params, $item_declared_info);
		
		//???????????????????????????????????????,????????????????????????html??????
		if($isCarrierNewVersion == true){
			return self::getOrdersCarrierInfoViewNewVersion($order, $sys_carrier_params, $carrierAccountInfo, $declarationInfo);
		}
		
		$tmpHtml = '';
		$tmpHeadhtml = '';
		
		//???????????????????????????????????????
		if(in_array($order->default_carrier_code, ['lb_anjun', 'lb_4px'])){
			//???item????????????1??????????????????????????????
			if(!empty($declarationInfo['products']) && count($declarationInfo['products']) > 1){
				$tmpHeadhtml .= '
					<div class=" prod-param-group">
						<div style="float: right; margin-top: 9px; margin-right: 10px;">
							<label>
								<input type="checkbox" style="position:relative;" name="is_CustomsFormSpan" onclick="setCustomsFormSpan(this,\''.$order->default_carrier_code.'\')">
								??????????????????
							</label>
						</div>
					</div>';
			}
		}
		
		if((!empty($other_params['is_merge']))){
			$tmpHeadhtml .= "<p style='color:red;float:left;'>????????? ????????????????????????:?????????????????????????????????2,????????????????????????????????????????????????????????????????????????????????????????????????.???????????????.</p><br>";
		}
		
		$tmpHtml .= '<input type="hidden" name="id" value="'.$order->order_id.'">';
		$tmpHtml .= '<input type="hidden" name="total" value="'.$declarationInfo['total'].'">';
		$tmpHtml .= '<input type="hidden" name="currency" value="'.$declarationInfo['currency'].'">';
		$tmpHtml .= '<input type="hidden" name="total_price" value="'.$declarationInfo['total_price'].'">';
		$tmpHtml .= '<input type="hidden" name="total_weight" value="'.$declarationInfo['total_weight'].'">';
	
		if(isset($sys_carrier_params[$order->default_carrier_code])){
			foreach ($sys_carrier_params[$order->default_carrier_code]['order_params'] as $v){
				$field = $v['data_key'];
				$data = isset($order->$field)?$order->$field:'';
				
				$data = self::getCarrierOrderParamsData($order->default_carrier_code, $v['carrier_param_key'], $declarationInfo, $carrierAccountInfo[$order->default_shipping_method_code], $order, $data);
				
				if($v['carrier_param_key'] == 'total_weight_4px'){
					$data = (string)$data;
				}
				
				if($data != 'not_continue_carrier'){
					$tmpHeadhtml .= self::getCarrierViewHeadhtml($v, $data, $order->default_carrier_code, $carrierAccountInfo[$order->default_shipping_method_code], $order);
				}
			}
		}
		
		$customerNumber = \eagle\modules\carrier\helpers\CarrierAPIHelper::getCustomerNum3($order, $carrierAccountInfo[$order->default_shipping_method_code]);
		$tmpHeadhtml .= '<div class=" order-param-group" style="width: 350px;" >
        <div style="float: left;width: 120px;margin-top: 9px;margin-right: 10px;"><label qtipkey="carrier_customer_number">???????????????<span class="star" style="color: red;">*</span></label></div>
        <div style="float: left;"><input type="text"  class="eagle-form-control" name="customer_number" style="width:150px;" value ='.$customerNumber.'>';
		if(($order->carrier_step==\eagle\modules\order\models\OdOrder::CARRIER_CANCELED) && (count($order->trackinfos) > 0)){
			$tmpHeadhtml .= '<span qtipkey="carrier_order_upload_again" style="color:red; margin-left: 4px;">????????????</span>';
		}
		$tmpHeadhtml .= '</div></div>';

		if($order->default_carrier_code=='lb_dhlexpress'){
			$tmpHeadhtml.='<div class=" order-param-group" style="width: 350px;" >
					<div style="float: left;"><span style="color:red; margin-left: 43px;line-height: 43px;">*????????????????????????0.1KG???,????????????????????????????????????</span></div>
			</div>';
		}
		
		$tmpHeadhtml = '<div style="width: 100%;">'.self::getAdditionalHeadhtml($order->default_carrier_code, $carrierAccountInfo[$order->default_shipping_method_code]).$tmpHeadhtml.'</div>'.
		'';
		
		$tmpItmeshtml = '';
		
		//?????????????????????items
		$tmpManyTimes = 0;
		
		$tmpItmeshtml = '<div name="items_param" style="float:left; ">';
		
		//??????????????????????????????????????????????????????,lrq20171129
		$is_hl_order_transactionurl = false;
		$is_hl_product_imagepath = false;
		if(!empty($carrierAccountInfo[$order->default_shipping_method_code]) && !empty($carrierAccountInfo[$order->default_shipping_method_code]['carrier_params'])){
			if(!empty($carrierAccountInfo[$order->default_shipping_method_code]['carrier_params']['is_hl_order_transactionurl'])){
				$is_hl_order_transactionurl = true;
			}
			if(!empty($carrierAccountInfo[$order->default_shipping_method_code]['carrier_params']['is_hl_product_imagepath'])){
				$is_hl_product_imagepath = true;
			}
		}
		
		foreach($declarationInfo['products'] as $product){
			$tmpManyTimes++;
			$tmpItmeshtml .= '<hr style="margin-top:1px;margin-bottom:2px;clear: both;"/>'.'<h5 class="text-success" style="text-align:left;">????????????'.$product['name'].'</h5><div style="width: 100%;" name="item_param">';
			$tmpItemsonehtml = '';
			
			if(isset($sys_carrier_params[$order->default_carrier_code])){
				foreach($sys_carrier_params[$order->default_carrier_code]['item_params'] as $v){
					if($v['carrier_param_key'] == 'hl_order_transactionurl' && !$is_hl_order_transactionurl){
						continue;
					}
					else if($v['carrier_param_key'] == 'hl_product_imagepath' && !$is_hl_product_imagepath){
						continue;
					}
					
					$field = $v['data_key'];
					$data = isset($product[$field])?$product[$field]:'';
					
					if($field == 'oversea_sku'){
						$tmpOverseaWarehouseSku = \eagle\modules\inventory\apihelpers\InventoryApiHelper::Get_OverseaWarehouseSku($product['sku'], $order->default_warehouse_id, $carrierAccountInfo[$order->default_shipping_method_code]['id']);
						
						if($tmpOverseaWarehouseSku['status'] == 1){
							$data = $tmpOverseaWarehouseSku['seller_sku'];
						}
					}
					
					//????????????????????????????????????????????????Data
					$data = self::getCarrierItmesParamsData($order->default_carrier_code, $v['carrier_param_key'], $product, $carrierAccountInfo[$order->default_shipping_method_code], $order, $data, $tmpManyTimes);
					
					$placeholder = $product == null ?'???????????????SKU,???????????????':'';
					$tmpinput = '';
					
					if($v['display_type'] == 'text'){
						$tmpinput = Html::input('text',$v['carrier_param_key'].'[]',$data,[
						'style'=>'width:150px;',
						'class'=>'eagle-form-control',
						'placeholder'=>$placeholder,
						'required'=>$v['is_required']==1?'required':null
						]);
					}else if($v['display_type'] == 'dropdownlist'){
						$tmp_is_hidden = false;
						
						if(($order->default_carrier_code == 'lb_dhl') && ($v['carrier_param_key'] == 'contentIndicator')){
							if(!empty($carrierAccountInfo[$order->default_shipping_method_code]['api_params']['is_lb_enable'])){
								if($carrierAccountInfo[$order->default_shipping_method_code]['api_params']['is_lb_enable'] == 1){
									$tmp_is_hidden = true;
								}
							}else{
								$tmp_is_hidden = true;
							}
						}
						
						if($tmp_is_hidden){
							$v['display_type'] = 'hidden';
						}
						
						$tmpinput = Html::dropDownList($v['carrier_param_key'].'[]',$data,$v['carrier_param_value'],['prompt'=>$v['carrier_param_name'],'style'=>'width:150px;'.($tmp_is_hidden ? 'display:none;' : ''),'class'=>'eagle-form-control']);
					}else if($v['display_type'] == 'hidden'){
						$tmpinput = Html::hiddenInput($v['carrier_param_key'].'[]',$data);
					}
					
					if($v['display_type'] == 'hidden'){
						$tmpItemsonehtml .= $tmpinput;
					}else{
						$tmpItemsonehtml .= '<div class=" prod-param-group" '.($v['is_hidden'] == 1 ? 'style="display:none;"' : '').'><div style="float: right" >'.$tmpinput.'</div><div style="width:120px; float: right;margin-top:9px;margin-right:4px;">'.
								'<label>'.$v['carrier_param_name'].(($v['is_required']==1) ? '<span class="star" style="color: red;">*</span>' : '').
								(!empty($v['param_describe']) ? '<img style="cursor: pointer;" width="16" src="/images/questionMark.png" title="'.$v['param_describe'].'">' : '').
								'</label></div></div>';
					}
				}
			}
			
			$tmpItmeshtml = $tmpItmeshtml.$tmpItemsonehtml.'</div>';
		}
		
// 		$current_time=explode(" ",microtime());
// 		$start2_time=round($current_time[0]*1000+$current_time[1]*1000);
// 		echo "orderid:".$order->order_id." used time t2-t1 ".($start2_time-$start1_time)."\n";
		
		//????????????div
		$tmpCustomsFormSpanhtml = '<div name="CustomsFormSpanItem" style="width: 100%; float:left; "></div>';
		
		
		return $tmpHtml.$tmpHeadhtml.$tmpCustomsFormSpanhtml.$tmpItmeshtml.'</div>';
	}
	
	/**
	 * ?????????????????????????????????????????????
	 * 
	 * @param $order
	 * @param $order_products
	 * @param $warehouseNameMap
	 * @return array()
	 */
	public static function getCustomsDeclarationInfo($order, $order_products, $warehouseNameMap, $accountInfo, $other_params = array(), $item_declared_info){
		$tmp_max_declared_value = 0;
		if(!empty($accountInfo['declaration_max_value'])){
			$tmp_max_declared_value = $accountInfo['declaration_max_value'];
		}
		
		$products = array();
		$total = 0;
		$currency = $order->currency;
		$total_price = 0;
		$total_weight = 0;//???
		
		foreach($order->items as $item){
			if($item->delivery_status == 'ban') continue;
			
			$tmpSku = $item->root_sku;
			
			//cd??????????????????item??????NonDeliverySku   //liang 2015-11-17
			if(strtolower($order->order_source)=='cdiscount'){
				if(in_array($item->sku, CdiscountOrderInterface::getNonDeliverySku())){
					continue;
				}
			}
			
			if(isset($order_products[$tmpSku.$item->order_item_id])){
				$product = $order_products[$tmpSku.$item->order_item_id];
			}else{
				$product = $order_products[$tmpSku];
			}

			$tmp_item_product = $item_declared_info[$item->order_item_id]['declaration'];
// 			print_r($tmp_item_product);
// 			exit;
			
			$inventory = InventoryApiHelper::getPickingInfo(array($tmpSku),$order->default_warehouse_id);
			$product = array(
					'name'=>$product['name'],
					'photo_primary'=>$product['photo_primary'],
					'declaration_ch'=>$tmp_item_product['nameCN'],
					'declaration_en'=>$tmp_item_product['nameEN'],
					'declaration_value'=>$tmp_item_product['price'],
					'total_price'=>$tmp_item_product['price']*$item->quantity,
					'declaration_value_currency'=>$product['declaration_value_currency'],
					'prod_weight'=>$tmp_item_product['weight'],
					'total_weight'=>$tmp_item_product['weight']*$item->quantity,
					'battery'=>$product['battery'],
					'note'=>'',
					'quantity'=>$item->quantity,
					'sku'=>$product['sku'],
					'product_attributes'=>$item->product_attributes,
					'transactionid'=>$item->order_source_transactionid,//?????????
					'itemid'=>$item->order_source_itemid,//????????????????????????????????????
					'warehouse'=>$warehouseNameMap[$order->default_warehouse_id],//??????
					'location_grid'=>isset($inventory[0]['location_grid'])?$inventory[0]['location_grid']:'???',//??????
					'purchase_by'=>$product['purchase_by'],
					'prod_name_ch'=>$product['prod_name_ch'],
					'prod_name_en'=>$product['prod_name_en'],
					'pro_width'=>$product['pro_width'],
					'pro_length'=>$product['pro_length'],
					'pro_height'=>$product['pro_height'],
					'declaration_code'=>$tmp_item_product['code'],
					'order_item_id'=>$item->order_item_id,	//od_order_item_v2???order_item_id
					'product_url'=>\eagle\modules\order\helpers\OrderListV3Helper::getOrderProductUrl($order, $item),
			);
			$total+=$product['quantity'];
			$total_price+=$product['total_price'];
			$total_weight+=$product['total_weight'];
			$products[] = $product;
		}
		
		//4px??????????????????,????????????2???????????????
		if((!empty($other_params['is_merge'])) && (count($products) >= 3)){
			$tmp_products = $products;
			unset($products);
			
			$products = array();
			
			foreach ($tmp_products as $tmp_product_key => $tmp_product_val){
				if(($tmp_product_key == 0) || ($tmp_product_key == 1)){
					$products[] = $tmp_product_val;
				}else{
					$products[0]['quantity'] += $tmp_product_val['quantity'];
					$products[0]['total_price'] += $tmp_product_val['total_price'];
				}
			}
			
			$products[0]['declaration_value'] = ($products[0]['quantity'] == 0) ? 0 : $products[0]['total_price'] / $products[0]['quantity'];
			
		}
		
		//????????????????????????
		if(!empty($tmp_max_declared_value)){
			if(($total_price > $tmp_max_declared_value) && ($total_price > 0) && ($tmp_max_declared_value > 0)){
				$sum_total_price = $total_price;
				$total_price = 0;
				
				foreach ($products as $productsKey => $productsVal){
					$tmp_percentum = $tmp_max_declared_value / $sum_total_price;
// 					$tmp_percentum = round($tmp_percentum, 4);
					
					$products[$productsKey]['declaration_value'] = round(($products[$productsKey]['declaration_value'] * $tmp_percentum), 2);
					$products[$productsKey]['total_price'] = $products[$productsKey]['quantity'] * $products[$productsKey]['declaration_value'];
					
					$total_price+=$products[$productsKey]['total_price'];
				}
			}
		}
		
		return array(
				'total'=>$total,
				'currency'=>$currency,
				'total_price'=>$total_price,
				'total_weight'=>$total_weight,//???
				'products'=>$products,
		);
	}
	
	/**
	 * ??????????????????????????????????????????????????????????????????????????????
	 * 
	 * @param $order
	 * @param $products
	 */
	public static function getCustomsDeclarationSumInfo($order, &$products){
		$tmpCommonDeclaredInfo = self::getCommonDeclaredInfoByDefault();
		
		foreach($order->items as $item){
			//????????????SKU,???????????????sku?????????product_name??????????????????????????????
			$tmpSku = $item->root_sku;
			
			//cd??????????????????item??????NonDeliverySku   //liang 2015-11-17
			if(strtolower($order->order_source)=='cdiscount'){
				if(in_array($item->sku, CdiscountOrderInterface::getNonDeliverySku())){
					continue;
				}
			}
			
			if(!isset($products[$tmpSku])){
				$product = ProductApiHelper::getProductInfo($tmpSku);
				
				//sku????????????
				if($product ==null){
					$product = array(
							'name'=>$item->product_name,
							'photo_primary'=>$item->photo_primary,
							'declaration_ch'=>(empty($tmpCommonDeclaredInfo['id'])) ? $item->product_name : $tmpCommonDeclaredInfo['ch_name'],
							'declaration_en'=>(empty($tmpCommonDeclaredInfo['id'])) ? $item->product_name : $tmpCommonDeclaredInfo['en_name'],
							'declaration_value'=>(empty($tmpCommonDeclaredInfo['id'])) ? $item->price : $tmpCommonDeclaredInfo['declared_value'],
							'declaration_value_currency'=>empty($order->currency) ? 'USD' : $order->currency,
							'prod_weight'=>$tmpCommonDeclaredInfo['declared_weight'],
							'battery'=>'N',
							'note'=>'',
							'sku'=>$item->sku,
							'purchase_by'=>'',
							'prod_name_ch'=>'',
							'prod_name_en'=>'',
							'pro_width'=>'',
							'pro_length'=>'',
							'pro_height'=>'',
							'declaration_code'=>(empty($tmpCommonDeclaredInfo['id'])) ? '' : $tmpCommonDeclaredInfo['detail_hs_code'],
					);
					//???????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????items??????????????????????????????order_item_id????????????
					$products[$tmpSku.$item->order_item_id] = $product;
				}else{
					//sku??????
					$product = array(
							'name'=>$product['name'],
							'photo_primary'=>$product['photo_primary'],
							'declaration_ch'=>strlen($product['declaration_ch'])>0?$product['declaration_ch']:$tmpCommonDeclaredInfo['ch_name'],
							'declaration_en'=>strlen($product['declaration_en'])>0?$product['declaration_en']:$tmpCommonDeclaredInfo['en_name'],
							'declaration_value'=>$product['declaration_value'],
							'declaration_value_currency'=>strlen($product['declaration_value_currency'])>0?$product['declaration_value_currency']:'USD',
							'prod_weight'=>$product['prod_weight']>0?$product['prod_weight']:$tmpCommonDeclaredInfo['declared_weight'],
							'battery'=>strlen($product['battery'])>0?$product['battery']:'N',
							'note'=>'',
							'sku'=>$product['sku'],
							'purchase_by'=>$product['purchase_by'],
							'prod_name_ch'=>$product['prod_name_ch'],
							'prod_name_en'=>$product['prod_name_en'],
							'pro_width'=>$product['prod_width'],
							'pro_length'=>$product['prod_length'],
							'pro_height'=>$product['prod_height'],
							'declaration_code'=>$product['declaration_code'],
					);
					$products[$tmpSku] = $product;
				}
			}
		}
	}
	
	/**
	 * ????????????????????????Head view??????
	 * 
	 * @param $carrier_code
	 * @param $shippingService
	 * @return string
	 */
	public static function getAdditionalHeadhtml($carrier_code, $shippingService){
		$resultHtml = '';
		
		switch ($carrier_code){
			case 'lb_winit':
				if(isset($shippingService['additional_arr'])){
					$resultHtml = '<label>????????? </label>'.Html::dropDownList('warehouseCode','',$shippingService['additional_arr'],['class'=>'eagle-form-control']);
				}
				break;
			case 'lb_winitOversea':
				if(isset($shippingService['additional_arr'])){
					$resultHtml = '<div class="order-param-group"><div style="float: right">'.
							Html::dropDownList('insuranceTypeID','',$shippingService['additional_arr'] ? $shippingService['additional_arr'] : array('1000000'=>'No Insurance'),['prompt'=>'','style'=>'width:150px;','class'=>'eagle-form-control']).
							'</div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>???????????? </label></div></div>';
				}
				break;
			case 'lb_santaic':
				$isFba = empty($shippingService['carrier_params']['isFba']) ? 0 : $shippingService['carrier_params']['isFba'];
				$arr = ['FBA'=>'FBA','other'=>'??????????????????'];
				if($isFba==1){
					$resultHtml = '<div class="form-group order-param-group"><div style="float: right">'.
						Html::dropDownList('warehouseName','FBA',$arr?$arr:array(''=>'No Insurance'),['prompt'=>'','style'=>'width:150px;','class'=>'eagle-form-control']).
						'</div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>FBA?????? </label></div></div>';
				}
				
				if(in_array($shippingService['shipping_method_code'],array("MXEXP"))){
					$arr = ['0'=>'???','1'=>'???'];
					$resultHtml .= '<div class="form-group order-param-group"><div style="float: right">'.
							Html::dropDownList('withBattery','N',$arr?$arr:array(''=>'No Insurance'),['style'=>'width:150px;','class'=>'eagle-form-control']).
							'</div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>???????????? </label></div></div>';
					break;
				}
				
				
				break;
			case 'lb_alionlinedelivery':
				$isHomeLanshou = empty($shippingService['api_params']['HomeLanshou']) ? 'N' : $shippingService['api_params']['HomeLanshou'];
				$resultHtml = '<script>var isHomeLanshou = "'.$isHomeLanshou.'"</script>';
				break;
			case 'lb_yisu':
				$resultHtml='';
				if(stripos($shippingService['shipping_method_code'],'HKR-')===0){
					$arr = ['N'=>'???','Y'=>'???'];
					$resultHtml .= '<div class="form-group order-param-group"><div style="float: right">'.
							Html::dropDownList('insured','N',$arr?$arr:array(''=>'No Insurance'),['style'=>'width:150px;','class'=>'eagle-form-control']).
							'</div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>?????????????????? </label></div></div>';
				}
				$tmp=array('NLR-','CNR-','CNA-','HKR-','HKA-','SER-','CHR-','SGR-','SGA-',);
				foreach ($tmp as $tmpone){
					if(stripos($shippingService['shipping_method_code'],$tmpone)===0){
						$arr = ['N'=>'???','Y'=>'???'];
						$resultHtml .= '<div class="form-group order-param-group"><div style="float: right">'.
								Html::dropDownList('ifreturn','Y',$arr?$arr:array(''=>'No Insurance'),['style'=>'width:150px;','class'=>'eagle-form-control']).
								'</div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>?????????????????? </label></div></div>';
						break;
					}
				}
				break;
			case 'lb_postpony':
				$resultHtml='';
				if(strstr($shippingService['shipping_method_code'],'International')!=false){
					$arr = ['NoEEISED'=>'NoEEISED','PreDepartureITN'=>'PreDepartureITN'];
					$resultHtml .= '<div class="form-group order-param-group"><div style="float: right">'.
							Html::dropDownList('ElectronicExportType','NoEEISED',$arr?$arr:array(''=>'No Insurance'),['style'=>'width:150px;','class'=>'eagle-form-control']).
							'</div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>ElectronicExportType</label></div></div>';
					
					$FTRarr = [
							"30.37(a)"=>'30.37(a)',
							"30.37(f)"=>'30.37(f)',
							"30.37(g)"=>'30.37(g)',
							"30.37(h)"=>'30.37(h)',
							"30.37(i)"=>'30.37(i)',
							"30.37(j)"=>'30.37(j)',
							"30.37(k)"=>'30.37(k)',
							"30.37(o)"=>'30.37(o)',
							"30.37(s)"=>'30.37(s)',
							"30.37(t)"=>'30.37(t)',
							"30.37(u)"=>'30.37(u)',
							"30.37(v)"=>'30.37(v)',
							"30.37(w)"=>'30.37(w)',
							"30.37(x)"=>'30.37(x)',
							"30.37(y)"=>'30.37(y)',
							"30.39"=>'30.39',
							"30.40(a)"=>'30.40(a)',
							"30.40(b)"=>'30.40(b)',
							"30.40(c)"=>'30.40(c)',
							"30.40(d)"=>'30.40(d)',
							"30.2(d)(2)"=>'30.2(d)(2)',
							"30.36"=>'30.36',
					];
					$resultHtml .= '<div class="form-group order-param-group"><div style="float: right">'.
							Html::dropDownList('FTRCode','30.37(a)',$FTRarr?$FTRarr:array(''=>'No Insurance'),['style'=>'width:150px;','class'=>'eagle-form-control']).
							'</div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>FTRCode</label></div></div>';
					
					$resultHtml .='<div class="order-param-group"><div style="float: right"><input type="text" class="eagle-form-control" name="AES" value="" style="width:150px;"></div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>AES</label></div></div>';
					
					$ShipmentPurposearr_usps = [
							"Gift"=>'Gift',
							"Sample"=>'Sample',
							"Documents"=>'Documents',
							"Merchandise"=>'Merchandise',
							"ReturnedGoods"=>'ReturnedGoods',
							"HumanitarianDonation"=>'HumanitarianDonation',
							"DangerousGoods"=>'DangerousGoods',
							"Other"=>'Other',
					];
					
					$ShipmentPurposearr_fex=[
							'Gift'=>'Gift',
							'Commercial'=>'Commercial',
							'Sample'=>'Sample',
							'Return and Repair'=>'Return and Repair',
							'Personal Effects'=>'Personal Effects',
							'Personal Use'=>'Personal Use',
					];
					
					if(strpos(strtolower($shippingService['shipping_method_code']),'usps')!==0)
						$ShipmentPurposearr=$ShipmentPurposearr_fex;
					else
						$ShipmentPurposearr=$ShipmentPurposearr_usps;
					
					$resultHtml .= '<div class="form-group order-param-group"><div style="float: right">'.
							Html::dropDownList('ShipmentPurpose','Gift',$ShipmentPurposearr?$ShipmentPurposearr:array(''=>'No Insurance'),['style'=>'width:150px;','class'=>'eagle-form-control']).
							'</div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>????????????</label></div></div>';
				}
				
				$resultHtml .='<div class="order-param-group"><div style="float: right"><input type="text" class="eagle-form-control PostponyLength" name="PostponyLength" value="" style="width:150px;"></div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>???(??????)<span class="star" style="color: red;">*</span></label></div></div>';
				$resultHtml .='<div class="order-param-group"><div style="float: right"><input type="text" class="eagle-form-control PostponyWidth" name="PostponyWidth" value="" style="width:150px;"></div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>???(??????)<span class="star" style="color: red;">*</span></label></div></div>';
				$resultHtml .='<div class="order-param-group"><div style="float: right"><input type="text" class="eagle-form-control PostponyHeight" name="PostponyHeight" value="" style="width:150px;"></div><div style="width:120px; float: right;margin-top:9px; margin-right: 10px;"><label>???(??????)<span class="star" style="color: red;">*</span></label></div></div>';
				$resultHtml .='<div class="order-param-group" style="margin-left:0px;"><div style="float: left;"><button class="eagle-form-control PostponyUseallbtn" type="button" id="PostponyUseallbtn" name="PostponyUseallbtn" onclick="PostponyUseall(this)">???????????????</button></div></div>';
				
				if(strpos(strtolower($shippingService['shipping_method_code']),'usps')!==0){
					$IsResidentialAddressArr = [
					'false'=>'???',
					'true'=>'???',
					];
					$resultHtml .= '<div class="form-group order-param-group"><div style="float: right">'.
							Html::dropDownList('ShipmentPurpose','false',$IsResidentialAddressArr?$IsResidentialAddressArr:array(''=>'No Insurance'),['style'=>'width:150px;','class'=>'eagle-form-control']).
							'</div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>?????????????????????</label></div></div>';
				}
				break;
			case 'lb_zhongyouOversea':
				$resultHtml='';
				if(isset($shippingService['carrier_params']['is_insurance']) && $shippingService['carrier_params']['is_insurance']){
					$resultHtml .='<div class="order-param-group"><div style="float: right"><input type="text" class="eagle-form-control" name="insurance_value" value="" style="width:150px;"></div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>??????</label></div></div>';
				}
				break;
			case 'lb_jiewang':
				$resultHtml='';
				if(in_array($shippingService['shipping_method_code'],array("J-NET???????????????","J-NET???????????????"))){
					$resultHtml .='<div class="order-param-group"><div style="float: right"><input type="text" class="eagle-form-control" name="taxNumber" value="" style="width:150px;"></div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>??????????????????</label></div></div>';
					$resultHtml .='<div class="order-param-group"><div style="float: right"><input type="text" class="eagle-form-control" name="passportNumber" value="" style="width:150px;"></div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>???????????????</label></div></div>';
				}
				break;
		}
		
		return $resultHtml;
	}
	
	/**
	 * ????????????Viwe???order params data
	 * 
	 * @param $carrier_code
	 * @param $carrier_param_key
	 * @param $declarationInfo
	 * @param $accountInfo
	 * @param $orderObj
	 * @param $data
	 * @return $data
	 */
	public static function getCarrierOrderParamsData($carrier_code, $carrier_param_key, $declarationInfo, $accountInfo, $orderObj, $data){
		if(($carrier_code == 'lb_4px') && ($carrier_param_key == 'total_weight_4px')){
			$data = 0;
			if(empty($accountInfo['carrier_params']['weight_set_zero'])){
				foreach($declarationInfo['products'] as $product){
					$data += $product['total_weight'];
				}
			}
		}else if(($carrier_code == 'lb_winit') && ($carrier_param_key == 'weight')){
			$data = 0;
			foreach($declarationInfo['products'] as $product){
				$data += $product['total_weight'];
			}
		}else if(($carrier_code == 'lb_aipaqi') && ($carrier_param_key == 'forecastWeight')){
			$data = 0;
			foreach($declarationInfo['products'] as $product){
				$data += $product['total_weight'];
			}
		}else if(($carrier_code == 'lb_badatong') && ($carrier_param_key == 'actualWeight')){
			$data = 0;
			foreach($declarationInfo['products'] as $product){
				$data += $product['total_weight'];
			}
		}else if(($carrier_code == 'lb_shenzhenyouzheng') && ($carrier_param_key == 'fWeight')){
			$data = 0;
			foreach($declarationInfo['products'] as $product){
				$data += $product['total_weight'];
			}
		}else if(($carrier_code == 'lb_badatong') && ($carrier_param_key == 'apItemTitle')){
			if(!empty($accountInfo['api_params']['apItemTitle_server'])){
				if($accountInfo['api_params']['apItemTitle_server'] == 'Y'){
					$data = '';
					foreach($declarationInfo['products'] as $product){
						if(!empty($product['prod_name_ch']))
							$data .= $product['prod_name_ch'].'*'.$product['quantity'].',';
					}
					$data=substr($data,0,-1);
				}
			}
		}else if($carrier_code == 'lb_wishyou'){
			if($carrier_param_key == 'user_desc'){
				if(!empty($accountInfo['api_params']['user_desc_mode'])){
					if($accountInfo['api_params']['user_desc_mode'] == 'sku'){
						$data = '';
						foreach($declarationInfo['products'] as $product){
							$data .= $product['sku'].'*'.$product['quantity'].';';
						}
						$data=substr($data,0,-1);
					}else if($accountInfo['api_params']['user_desc_mode'] == 'cn_name'){
						$data = '';
						foreach($declarationInfo['products'] as $product){
							$data .= $product['declaration_ch'].'*'.$product['quantity'].';';
						}
						$data=substr($data,0,-1);
					}
				}
			}else if($carrier_param_key == 'from_country'){
				$data = 'china';
			}else if($carrier_param_key == 'trade_amount'){
				$data = 0;
				foreach($declarationInfo['products'] as $product){
					$data += $product['total_price'];
				}
			}
		}else if($carrier_code == 'lb_wanse'){
			if($carrier_param_key == 'trade_amount'){
				$data = 0;
				foreach($declarationInfo['products'] as $product){
					$data += $product['total_price'];
				}
			}
		}else if(($carrier_code == 'lb_anjun') && ($carrier_param_key == 'pickingInfo')){
                $tmpAnjunpickingInfo_mode = '';
                if(!empty($accountInfo['carrier_params']['pickingInfo_mode_service'])){
					if($accountInfo['carrier_params']['pickingInfo_mode_service'] != 'ALL')
						$tmpAnjunpickingInfo_mode = $accountInfo['carrier_params']['pickingInfo_mode_service'];
				}
				
				if(empty($tmpAnjunpickingInfo_mode)){
					if(!empty($accountInfo['api_params']['pickingInfo_mode'])){
						$tmpAnjunpickingInfo_mode = $accountInfo['api_params']['pickingInfo_mode'];
					}
				}
                
                if(!empty($tmpAnjunpickingInfo_mode)){
                    if($tmpAnjunpickingInfo_mode == 'sku'){
                        $data = '';
                        foreach($declarationInfo['products'] as $product){
                            $data .= $product['sku'].'*'.$product['quantity'].';';
                        }

                        $data=substr($data,0,-1);
                    }else
                    if($tmpAnjunpickingInfo_mode == 'orderid')
                    {
                        $data = $orderObj->order_id;
                    }else
                    if($tmpAnjunpickingInfo_mode == 'sku_prod_name'){
                    	$data = '';
                    	foreach($declarationInfo['products'] as $product){
                    		$data .= $product['sku'].' '.$product['prod_name_ch'].'*'.$product['quantity'].';';
                    	}
                    	$data=substr($data,0,-1);
                    }
                }
            }else if(($carrier_code == 'lb_esutong') && ($carrier_param_key == 'InsurValue')){
            	if(!empty($accountInfo['carrier_params']['InsurType'])){
            		if($accountInfo['carrier_params']['InsurType'] == 'N'){
            			$data = 'not_continue_carrier';
            		}
            	}else{
            		$data = 'not_continue_carrier';
            	}
            }else if(($carrier_code == 'lb_winit') && ($carrier_param_key == 'width'|| $carrier_param_key == 'length'|| $carrier_param_key == 'height')){
            	$product = current($declarationInfo['products']);reset($declarationInfo['products']);
            	
                if(!empty($accountInfo['api_params']['defaultSize'])){
                    if($accountInfo['api_params']['defaultSize'] == 'Y'){
                        if($carrier_param_key == 'width'){
                            $data = $product['pro_width'];
                        }else if($carrier_param_key == 'length'){
                            $data = $product['pro_length'];
                        }else if($carrier_param_key == 'height'){
                            $data = $product['pro_height'];
                        }
                    }
                }else{//????????????
                    if($carrier_param_key == 'width'){
                        $data = $product['pro_width'];
                    }else if($carrier_param_key == 'length'){
                        $data = $product['pro_length'];
                    }else if($carrier_param_key == 'height'){
                        $data = $product['pro_height'];
                    }
                }
            }else if(($carrier_code == 'lb_yiyunquanqiu')){
            	if($carrier_param_key == 'goods_description'){
            		$data = '';
            		foreach($declarationInfo['products'] as $product){
            			$data .= $product['sku'].'*'.$product['quantity'].';';
            		}
            		$data=substr($data,0,-1);
            	}else if($carrier_param_key == 'length'){
            		$data = '1';
            	}else if($carrier_param_key == 'width'){
            		$data = '1';
            	}else if($carrier_param_key == 'height'){
            		$data = '1';
            	}
            }else if(($carrier_code == 'lb_alionlinedelivery')){
            	if($carrier_param_key == 'is_product'){
            		$data = '';
            		
            		if(!empty($accountInfo['carrier_params']['is_product_service'])){
            			if($accountInfo['carrier_params']['is_product_service'] == 1){
            				$data = '1';
            			}
            		}
            	}else if($carrier_param_key == 'postal_code_al'){
            		$data = $orderObj->consignee_postal_code;
            		
            		if($orderObj->consignee_country_code == 'RU'){
            			$data = str_replace(' ', '' ,$data);
            			$data = str_replace('-', '' ,$data);
            		}
            	}
            }else if($carrier_code == 'lb_postpony' && $carrier_param_key == 'ShippingNotes'){
            	$data = '';
            	foreach($declarationInfo['products'] as $product){
            		$data .= $product['sku'].'*'.$product['quantity'].';';
            	}
            	$data=substr($data,0,-1);
            }else if(($carrier_code == 'lb_SF') && ($carrier_param_key == 'is_battery')){
            	$data = 'N';
            	foreach($declarationInfo['products'] as $product){
            		if($product['battery'] == 'Y'){
            			$data = 'Y';
            		}
            	}
            }else if(($carrier_code == 'lb_yaoukuaiyun' || $carrier_code == 'lb_etongshou') && ($carrier_param_key == 'total_weight_sum')){
				$data = 0;
				foreach($declarationInfo['products'] as $product){
					$data += $product['total_weight'];
				}
			}else if(($carrier_code == 'lb_winitOversea') && (($carrier_param_key == 'receiver_address1') || ($carrier_param_key == 'receiver_address2'))){
				$data = '';
				
				$addressAndPhoneParams = array(
						'address' => array(
								'consignee_address_line1_limit' => 50,
								'consignee_address_line2_limit' => 100,
								'consignee_address_line3_limit' => 100,
						),
						'consignee_district' => 1,
						'consignee_county' => 1,
						'consignee_company' => 1,
						'consignee_phone_limit' => 100
				);
				$addressAndPhone = \eagle\modules\carrier\helpers\CarrierAPIHelper::getCarrierAddressAndPhoneInfo($orderObj, $addressAndPhoneParams);
				
				if($carrier_param_key == 'receiver_address1')
					$data = $addressAndPhone['address_line1'];
				else if($carrier_param_key == 'receiver_address2')
					$data = $addressAndPhone['address_line2'];
			}else if($carrier_code == 'lb_wanou'){
				if($carrier_param_key == 'Notes'){
					$data = '';
					foreach($declarationInfo['products'] as $product){
						$data .= $product['sku'].' '.$product['prod_name_ch'].'*'.$product['quantity'].';';
					}
					$data=substr($data,0,-1);
				}
			}else if($carrier_code == 'lb_santaic'){
				if($carrier_param_key == 'goodsDescription'){
					$data = '';
					if(!empty($accountInfo['carrier_params']['goodsDescriptionSet'])){
						if($accountInfo['carrier_params']['goodsDescriptionSet'] == 1){
							foreach($declarationInfo['products'] as $product){
								$data .= $product['declaration_en'].';';
							}
							$data=substr($data,0,-1);
						}
					}
				}
			}else if($carrier_code == 'lb_chukouyi' && $carrier_param_key == 'custom'){
				$data = '';
				$data .= $orderObj->order_source_order_id.";";
				$data=substr($data,0,-1);
			}else if(($carrier_code == 'lb_edis') && ($carrier_param_key == 'packageWeight')){
				$data = 0;
				foreach($declarationInfo['products'] as $product){
					$data += $product['total_weight'];
				}
			}
			
		
		return $data;
	}
	
	/**
	 * ????????????View???items params data
	 * 
	 * @param $carrier_code
	 * @param $carrier_param_key
	 * @param $product
	 * @param $accountInfo
	 * @param $orderObj
	 * @param $data
	 * @param $manyTimes
	 * @return $data
	 */
	public static function getCarrierItmesParamsData($carrier_code, $carrier_param_key, $product, $accountInfo, $orderObj, $data, $manyTimes = 1){
		if(($carrier_code == 'lb_hulianyi') && ($carrier_param_key == 'productMemo')){
			if(!empty($accountInfo['api_params']['user_productMemo_mode'])){
				if($accountInfo['api_params']['user_productMemo_mode'] == 'sku'){
					$data = $product['sku'].'*'.$product['quantity'];
				}else if(($accountInfo['api_params']['user_productMemo_mode'] == 'orderid') && ($manyTimes == 1)){
					$data = $orderObj->order_id;
				}
			}
		}else if(($carrier_code == 'lb_SF') && ($carrier_param_key == 'diPickName')){
			if(!empty($accountInfo['api_params']['user_diPickName_mode'])){
				if($accountInfo['api_params']['user_diPickName_mode'] == 'sku'){
					$data = $product['sku'];
				}else if($accountInfo['api_params']['user_diPickName_mode'] == 'N'){
					$data = '';
				}else if($accountInfo['api_params']['user_diPickName_mode'] == 'Name'){
					$data = $product['prod_name_ch'];
				}else if($accountInfo['api_params']['user_diPickName_mode'] == 'skuNullName'){
					$data = $product['sku'].' '.$product['prod_name_ch'];
				}else if($accountInfo['api_params']['user_diPickName_mode'] == 'orderidstockName'){
					$data = preg_replace('/^0+/','',$orderObj->order_id).' '.$product['location_grid'].' '.$product['prod_name_ch'];
				}else if($accountInfo['api_params']['user_diPickName_mode'] == 'orderidstockDecName'){
					$data = preg_replace('/^0+/','',$orderObj->order_id).' '.$product['location_grid'].' '.$product['declaration_ch'];
				}
			}
		}else if(($carrier_code == 'lb_CNE') && ($carrier_param_key == 'EName')){
			if(!empty($accountInfo['api_params']['user_productMemo_mode'])){
				if($accountInfo['api_params']['user_productMemo_mode'] == 'sku'){
					$data = $product['declaration_en'].' '.$product['sku'].'*'.$product['quantity'];
				}else if(($accountInfo['api_params']['user_productMemo_mode'] == 'orderid') && ($manyTimes == 1)){
					$data = $product['declaration_en'].' '.$orderObj->order_id;
				}
			}
		}else if(($carrier_code == 'lb_feite') && ($carrier_param_key == 'ItemCode')){
			if(!empty($accountInfo['api_params']['ItemCode_mode'])){
				if($accountInfo['api_params']['ItemCode_mode'] == 'title'){
					$data = $product['name'].'*'.$product['quantity'];
				}
                if($accountInfo['api_params']['ItemCode_mode'] == 'sku'){
                    $data = $product['sku'].'*'.$product['quantity'];
                }
			}
		}else if(($carrier_code == 'lb_yanwen') && ($carrier_param_key == 'EName')){
			if(!empty($accountInfo['api_params']['EName_mode'])){
				if($accountInfo['api_params']['EName_mode'] == 'sku'){
					$data = $product['sku'].' '.$data;
				}
			}
		}else if(($carrier_code == 'lb_IEUBNew') && ($carrier_param_key == 'cnname')){
			if(!empty($accountInfo['api_params']['cnname_mode'])){
				if($accountInfo['api_params']['cnname_mode'] == 'sku'){
					$data = $data.' '.$product['sku'];
				}else if($accountInfo['api_params']['cnname_mode'] == 'skuOrderid'){
					$data = $data.' '.$product['sku'].' '.$orderObj->order_id;
				}
			}
		}else if(($carrier_code == 'lb_4px') && ($carrier_param_key == 'EName')){
			if(!empty($accountInfo['api_params']['cnname_mode'])){
				if($accountInfo['api_params']['cnname_mode'] == 'sku'){
					$data = $data.' '.$product['sku'];
				}else if($accountInfo['api_params']['cnname_mode'] == 'order'){
					$data = $data.' '.$orderObj->order_id;
				}
			}
		}else if(($carrier_code == 'lb_4px') && ($carrier_param_key == 'Name')){
			if(!empty($accountInfo['api_params']['zcnname_mode'])){
				if($accountInfo['api_params']['zcnname_mode'] == 'sku'){
					$data = $data.' '.$product['sku'];
				}
			}
		}else if(($carrier_code == 'lb_4px') && ($carrier_param_key == 'DeclareNote')){
			if(!empty($accountInfo['api_params']['declareNote_mode'])){
				if($accountInfo['api_params']['declareNote_mode'] == 'cnname_qty'){
					$data = $product['prod_name_ch'] .'*' . $product['quantity'];
				}else if($accountInfo['api_params']['declareNote_mode'] == 'none_info'){
					$data = '';
				}
			}
		}else if(($carrier_code == 'lb_epacket') && ($carrier_param_key == 'Name')){
			if(!empty($accountInfo['api_params']['cnname_mode'])){
				if($accountInfo['api_params']['cnname_mode'] == 'sku'){
					$data = $data.' '.$product['sku'];
				}
			}
		}else if($carrier_code == 'lb_alionlinedelivery'){
			if($carrier_param_key == 'isContainsBattery'){
				if($data == 'N'){
					$data = 0;
				}else{
					$data = 1;
				}
			}else if(($carrier_param_key == 'isAneroidMarkup') || ($carrier_param_key == 'isOnlyBattery')){
				if(empty($data)){
					$data = 0;
				}
			}else if($carrier_param_key == 'EName'){
				if(!empty($accountInfo['api_params']['user_EName_mode'])){
					if($accountInfo['api_params']['user_EName_mode'] == 'sku'){
						$data .= ' '.$product['sku'].'*'.$product['quantity'];
					}
				}
			}else if($carrier_param_key == 'DeclaredValue'){
				$data = $data * $product['quantity'];
			}
		}else if($carrier_code == 'lb_postpony'){
			if($carrier_param_key == 'Selectweight'){
				$data='oz';
			}else if($carrier_param_key == 'postponyweight'){
				//???????????????oz??????????????????????????????
				$data = $data * 0.035274;
			}
		}else if(($carrier_code == 'lb_yuntu') && ($carrier_param_key == 'Remark')){
            if(!empty($accountInfo['api_params']['user_Remark_mode'])){
                if($accountInfo['api_params']['user_Remark_mode'] == 'sku'){
                    $data = $product['sku'].'*'.$product['quantity'];
                }else if($accountInfo['api_params']['user_Remark_mode'] == 'prod_name_ch'){
                    $data = $product['prod_name_ch'] .'*' . $product['quantity'];
                }else if($accountInfo['api_params']['user_Remark_mode'] == 'prod_name_ch_sku'){
                    $data = $product['prod_name_ch'] .'+' .$product['sku'].'*'.$product['quantity'];
                }
            }else{
                //????????????????????????sku??????
                $data = $product['sku'];
            }
        }else if(($carrier_code == 'lb_debang') && ($carrier_param_key == 'Remark')){
            if(!empty($accountInfo['api_params']['user_Remark_mode'])){
                if($accountInfo['api_params']['user_Remark_mode'] == 'sku'){
                    $data = $product['sku'].'*'.$product['quantity'];
                }else if($accountInfo['api_params']['user_Remark_mode'] == 'prod_name_ch'){
                    $data = $product['prod_name_ch'] .'*' . $product['quantity'];
                }else if($accountInfo['api_params']['user_Remark_mode'] == 'prod_name_ch_sku'){
                    $data = $product['prod_name_ch'] .'+' .$product['sku'].'*'.$product['quantity'];
                }
            }else{
                //?????????????????????
                $data = '';
            }
        }else if($carrier_code == 'lb_dhlexpress'){
        	if($carrier_param_key == 'Weight')
        		$data=$data/1000;
        }else if($carrier_code == 'lb_chinapost'){
        	if($carrier_param_key == 'delcarevalue')
        		$data = $data*100;
        }else if(($carrier_code == 'lb_yuntu') && ($carrier_param_key == 'EName')){
			if(!empty($accountInfo['api_params']['cnname_mode'])){
				if($accountInfo['api_params']['cnname_mode'] == 'sku'){
					$data = $data.' '.$product['sku'];
				}
			}
		}else if(($carrier_code == 'lb_yitongguan') && ($carrier_param_key == 'EName')){
			if(!empty($accountInfo['api_params']['cnname_mode'])){
				if($accountInfo['api_params']['cnname_mode'] == 'sku'){
					$data = $data.' '.$product['sku'];
				}
			}
		}else if(($carrier_code == 'lb_wanbexpress') && ($carrier_param_key == 'GoodsId')){
			if(!empty($accountInfo['api_params']['isUploadSku'])){
				if($accountInfo['api_params']['isUploadSku'] == '1'){
					$data = $data.' '.$product['sku'];
				}
			}
		}

		return $data;
	}
	
	/**
	 * ????????????View Html
	 * 
	 * @param $v
	 * @param $data
	 * @param $carrier_code
	 * @param $shippingService
	 * @return string
	 */
	public static function getCarrierViewHeadhtml($v, $data, $carrier_code, $shippingService, $tmp_order){
		$tmpHeadhtml = '';
		
		if($carrier_code == 'lb_alionlinedelivery'){
			$isHomeLanshou = empty($shippingService['api_params']['HomeLanshou']) ? 'N' : $shippingService['api_params']['HomeLanshou'];
			
			if($v['carrier_param_key'] == 'AlidomesticTrackingNo'){
				//??????????????????????????????????????????????????????????????????????????????
				$data = '';
				if($isHomeLanshou == 'Y'){
					$data = 'None';
					if(in_array($shippingService['shipping_method_code'], LB_ALIONLINEDELIVERYCarrierAPI::$fourpxChannel)){
						$data = '4PX';
					}
				}else if($isHomeLanshou == 'ZS'){
					$data = 'None';
				}else if($isHomeLanshou == 'N'){
					if(!empty($tmp_order->declaration_info)){
						$tmp_declaration_info = json_decode($tmp_order->declaration_info, true);
						if(isset($tmp_declaration_info['smt_channel'][2])){
							$data = $tmp_declaration_info['smt_channel'][2];
						}
					}
				}
			}
		}
		
		if(($carrier_code == 'lb_alionlinedelivery') && ($v['carrier_param_key'] == 'AlidomesticLogisticsCompanyId')){
			$tmp_smt_companys = array('500'=>'????????????','102'=>'????????????','505'=>'????????????','2'=>'EMS','101'=>'????????????','504'=>'????????????','1152'=>'????????????','1216'=>'????????????','100'=>'????????????');
			
			$tmpHomeLanshouCN = '';
			if($isHomeLanshou == 'Y'){
				$tmpHomeLanshouCN = '????????????';
			}else if($isHomeLanshou == 'ZS'){
				$tmpHomeLanshouCN = '?????????????????????';
				$data = -1;
			}else if($isHomeLanshou == 'N'){
				if(!empty($tmp_order->declaration_info)){
					$tmp_declaration_info = json_decode($tmp_order->declaration_info, true);
					if(isset($tmp_declaration_info['smt_channel'][1])){
						$is_smt_company = false;
						
						foreach ($tmp_smt_companys as $tmp_smt_companyKey => $tmp_smt_companyVal){
							if($tmp_smt_companyVal == $tmp_declaration_info['smt_channel'][1]){
								$is_smt_company = true;
								$data = $tmp_smt_companyKey;
								break;
							}
						}
						
						if($is_smt_company == false){
							$tmpHomeLanshouCN = $tmp_declaration_info['smt_channel'][1];
							$data = -1;
						}
					}
				}
			}
			
			if(in_array($isHomeLanshou, array('Y','ZS'))){
				$tmpHeadhtml = '<div class="order-param-group" style="width:560px;"><div style="float: right">'.
						("<div style='display:inline;'>".
								Html::dropDownList($v['carrier_param_key'],$data,$v['carrier_param_value'],['onchange'=>"aliChangeCompany(this,'".$isHomeLanshou."');",'style'=>'width:150px;display:none;','class'=>'eagle-form-control']).
								"<label id='lab_ali_company' style='display:none;margin-left:10px;'>????????????????????????</label><input type='text' class='eagle-form-control' id='domesticLogisticsCompany' name='domesticLogisticsCompany' value='".$tmpHomeLanshouCN."'>".'</div>').
								'</div>'.'<div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>'.
								$v['carrier_param_name'].(($v['is_required']==1) ? '<span class="star" style="color: red;">*</span>' : '').'</label>'.
								(empty($v['param_describe']) ? '' : "<span class='carrier_qtip_".$v['id']."'></span>").'</div>'.'</div>';
			}else{
				$tmpHeadhtml = '<div class="order-param-group" style="width:560px;"><div style="float: right">'.
						("<div style='display:inline;'>".
								Html::dropDownList($v['carrier_param_key'],$data,$v['carrier_param_value'],['onchange'=>"aliChangeCompany(this,'".$isHomeLanshou."');",'style'=>'width:150px;','class'=>'eagle-form-control']).
								"<label id='lab_ali_company' style='display:none;margin-left:10px;'>????????????????????????</label><input type='text' class='eagle-form-control' id='domesticLogisticsCompany' name='domesticLogisticsCompany' value='".$tmpHomeLanshouCN."' style='display: none;'>".'</div>').
								'</div>'.'<div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>'.
								$v['carrier_param_name'].(($v['is_required']==1) ? '<span class="star" style="color: red;">*</span>' : '').'</label>'.
								(empty($v['param_describe']) ? '' : "<span class='carrier_qtip_".$v['id']."'></span>").'</div>'.'</div>';
			}
		}else{
			switch ($v['display_type']){
				case 'hidden':
					$tmpHeadhtmlType = Html::hiddenInput($v['carrier_param_key'],$data);
					break;
				case 'dropdownlist':
					$tmpHeadhtmlType = Html::dropDownList($v['carrier_param_key'],$data,$v['carrier_param_value'],['style'=>'width:150px;','class'=>'eagle-form-control']);
					break;
				default:
					$tmpHeadhtmlType = Html::input('text',$v['carrier_param_key'],$data,['style'=>'width:150px;','class'=>'eagle-form-control']);
					break;
			}
			
			if($v['display_type'] == 'hidden'){
				$tmpHeadhtml = $tmpHeadhtmlType;
			}else{
				$tmpHeadhtml = '<div class="order-param-group" '.($v['is_hidden'] == 1 ? 'style="display:none;"' : '').'><div style="float: right">'.$tmpHeadhtmlType.
					'</div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>'.
					$v['carrier_param_name'].(($v['is_required']==1) ? '<span class="star" style="color: red;">*</span>' : '').'</label>'.
					(empty($v['param_describe']) ? '' : "<span class='carrier_qtip_".$v['id']."'></span>").'</div></div>';
			}
			
// 			$tmpHeadhtml = '<div class="order-param-group"><div style="float: right">'.
// 					(($v['display_type'] == 'text') ? Html::input('text',$v['carrier_param_key'],$data,['style'=>'width:150px;','class'=>'eagle-form-control']) :
// 							Html::dropDownList($v['carrier_param_key'],$data,$v['carrier_param_value'],['style'=>'width:150px;','class'=>'eagle-form-control'])).
// 							'</div><div style="width:120px; float: right;margin-top:9px; margin-right: 4px;"><label>'.
// 							$v['carrier_param_name'].(($v['is_required']==1) ? '<span class="star" style="color: red;">*</span>' : '').'</label>'.
// 							(empty($v['param_describe']) ? '' : "<span class='carrier_qtip_".$v['id']."'></span>").'</div></div>';
		}
		
		return $tmpHeadhtml;
	}
	
	/**
	 * ??????????????????????????????????????????????????????
	 *
	 * @param	$id	????????????ID
	 * @return
	 * Array
	 (
	 [is_api_print] => 1		????????????API??????,1????????? 0????????????
	 [is_print] => 0			????????????????????????,1????????? 0????????????
	 [is_custom_print] => 0		???????????????????????????,1????????? 0????????????
	 [is_xlb_print] => 0		?????????????????????????????????????????????
	 )
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2016/01/19				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCustomShippingServicePrintMode($id, $externalV = ''){
		$userShippingSevice = SysShippingService::find()->select(['carrier_code','shipping_method_code','third_party_code',
				'print_params','is_custom','shipping_method_name','carrier_name','print_type','carrier_params'])->where(['id'=>$id])->asArray()->one();
	
		$userShippingSevice['print_params'] = unserialize($userShippingSevice['print_params']);
		$userShippingSevice['carrier_params'] = unserialize($userShippingSevice['carrier_params']);
	
		if((!empty($userShippingSevice['print_params']['label_custom']['carrier_lable'])) || !empty($userShippingSevice['print_params']['label_custom']['declare_lable']) || !empty($userShippingSevice['print_params']['label_custom']['items_lable']))
			$is_custom_print = 1;
		else
			$is_custom_print = 0;
		
		//??????????????????
		if($is_custom_print == 1){
			if($externalV == 0){
				$is_custom_print = 0;
			}
		}
	
		if($userShippingSevice['is_custom'] == 1){
			return array('is_api_print'=>0,'is_print'=>0,'is_custom_print'=>$is_custom_print,'is_xlb_print'=>($userShippingSevice['print_type'] == 3 ? 1 : 0),
					'is_custom_print_new'=>($userShippingSevice['print_type'] == 4 ? 1 : 0),
					'shipping_method_name'=>$userShippingSevice['shipping_method_name'],'carrier_name'=>$userShippingSevice['carrier_name']
			);
		}
		
		$tmp_print_type = 1;
		
		//????????????????????????????????????????????????
		if($userShippingSevice['carrier_code'] == 'lb_alionlinedelivery'){
			if(!empty($userShippingSevice['carrier_params']['print_format'])){
				if($userShippingSevice['carrier_params']['print_format'] == 1){
					$tmp_print_type = 2;
				}else{
					$tmp_print_type = 3;
				}
			}
		}
	
		return array('is_api_print'=>($userShippingSevice['print_type'] == 0 ? $tmp_print_type : 0),
				'is_print'=>($userShippingSevice['print_type'] == 1 ? 1 : 0),'is_custom_print'=>($is_custom_print),
				'is_xlb_print'=>($userShippingSevice['print_type'] == 3 ? 1 : 0),
				'is_custom_print_new'=>($userShippingSevice['print_type'] == 4 ? 1 : 0),
				'shipping_method_name'=>$userShippingSevice['shipping_method_name'],'carrier_name'=>$userShippingSevice['carrier_name']);
	}
	
	/**
	 +---------------------------------------------------------------------------------------------
	 * ??????????????????????????????????????????????????????
	 +---------------------------------------------------------------------------------------------
	 * @access static
	 +---------------------------------------------------------------------------------------------
	 * @param     $ServiceIdList					????????????ID??????
	 +---------------------------------------------------------------------------------------------
	 * @return						array 
	 * 									['id'=>'service_name']
	 *
	 * @invoking					CarrierOpenHelper::getServiceName(['1']);
	 *
	 +---------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2016/07/06				?????????
	 +---------------------------------------------------------------------------------------------
	 **/
	public static function getServiceName($ServiceIdList=[]){
		$service = SysShippingService::find()->select(['id','service_name']);
		if (!empty($ServiceIdList)){
			$service->where(['id'=>$ServiceIdList]);
		}
		return Helper_Array::toHashmap($service->asArray()->all(),'id','service_name');
	}//end of function getServiceName
	/*
	 * +-------------------------------------------------------------------------------------------
	* log			name	date					note
	* @author		lgw		2016/06/27				?????????
	*
	* @param $carrier_code	????????????	?????????
	* @param $is_show_all	???????????????????????????	?????????
	* @param $carrier_type	?????????????????????	0???????????????	1??????????????????	3???????????????????????????
	* +-------------------------------------------------------------------------------------------
	*/
	public static function getHasCarrier($carrier_code = '', $is_show_all = 'N', $carrier_type = 0){
		$query = SysCarrier::find();
		$query2 = CarrierUseRecord::find()->select(['carrier_code','is_active']);
	
		if(!empty($carrier_code)){
			$query->andWhere(['carrier_code'=>$carrier_code]);
		}
	
		if($is_show_all != 'Y'){
			$query->andWhere(['is_active'=>'1']);
			$query2->where(['is_del'=>0]);
		}
	
		if(empty($carrier_type)){
			$query->andWhere(['carrier_type'=>0]);
		}else{
			if($carrier_type == 1){
				$query->andWhere(['carrier_type'=>1]);
			}else{
			}
		}
	
		$hasOpenCarrier = $query->asArray()->all();
	
		$openCarrierListArr = $query2->asArray()->all();
		$tmpCloseTypeArr = Helper_Array::toHashmap($openCarrierListArr, 'carrier_code', 'is_active');
	
		$hasOpenCarrier[0]['is_show_address']=empty($hasOpenCarrier[0]['address_list']) ? 0 : 1;
		$hasOpenCarrier[0]['carrier_name']  = $hasOpenCarrier[0]['carrier_name'];
		$hasOpenCarrier[0]['is_useractive']=isset($tmpCloseTypeArr[$hasOpenCarrier[0]['carrier_code']])?$tmpCloseTypeArr[$hasOpenCarrier[0]['carrier_code']]:0;
	
		return $hasOpenCarrier;
	}
	
	public static function getCarrierShippingServiceUserByCarrierCodeNew($carrier_code, $service_name = ''){
		$query = SysShippingService::find();
	
		//?????????????????????????????????????????????????????????????????????
		$sysShippingMethod=SysShippingMethod::find()->where(['carrier_code'=>$carrier_code])->asArray()->all();;
		$sysShippingMethodClose = SysShippingMethod::find()->select(['shipping_method_code'])->where(['carrier_code'=>$carrier_code,'is_close'=>1])->asArray()->all();
		if(!empty($sysShippingMethodClose)){
			$sysShippingMethodClose = Helper_Array::toHashmap($sysShippingMethodClose, 'shipping_method_code', 'shipping_method_code');
		}else{
			$sysShippingMethodClose = array();
		}
	
		$query->andWhere(['carrier_code'=>$carrier_code,'is_del'=>0]);
	
		if(!empty($service_name)){
			$query->andWhere(['like','shipping_method_name',$service_name]);
		}
	
		if(substr($carrier_code, 0, 3) == 'lb_'){
			//?????????????????????
			$carrierAccountList = self::getCarrierAccountList($carrier_code)['response']['data'];
			$tmpCarrierAccountListIDArr = array();
	
			foreach ($carrierAccountList as $carrierAccountKey => $carrierAccountVal){
				$tmpCarrierAccountListIDArr[] = $carrierAccountKey;
			}
	
			$query->andWhere(['in', 'carrier_account_id', $tmpCarrierAccountListIDArr]);
		}
	
		$sort_arr = array('is_used'=>SORT_DESC,'carrier_code'=>SORT_ASC,'create_time'=>SORT_ASC,'shipping_method_name'=>SORT_ASC,'service_name'=>SORT_ASC,'carrier_account_id'=>SORT_DESC);
		$query->orderBy($sort_arr);
	
		$shippingServiceArr = $query->asArray()->all();
		$shippingServiceArrt=$shippingServiceArr;

		foreach ($sysShippingMethod as &$sysShippingMethodone){
			$tmp=0;
			foreach ($shippingServiceArrt as $keys=>&$shippingServiceArrone){
				if($sysShippingMethodone['shipping_method_code']===$shippingServiceArrone['shipping_method_code']){					
// 					$shippingServiceArrone['shipping_method_name']=$sysShippingMethodone['shipping_method_name'];
					$shippingServiceArr[$keys]['shipping_method_name']=$sysShippingMethodone['shipping_method_name'];
					$tmp=1;
					break;
				}
			}
			if($tmp==0 && $sysShippingMethodone['is_close']==0){
				$shippingServiceArr[]=[
				'id'=> 0,
				'carrier_code'=> $sysShippingMethodone['carrier_code'],
				'carrier_params'=> '',
				'ship_address'=>'',
				'return_address'=>'',
				'is_used'=> 0,
				'service_name'=> '',
				'service_code'=>'',
				'auto_ship'=> 0,
				'web'=> self::getServiceUrlByCarrierCode($carrier_code),
				'create_time'=> '',
				'update_time'=>'',
				'carrier_account_id'=> 0,
				'extra_carrier'=>'',
				'carrier_name'=>'',
				'shipping_method_name'=>$sysShippingMethodone['shipping_method_name'],
				'shipping_method_code'=>$sysShippingMethodone['shipping_method_code'],
				'third_party_code'=>$sysShippingMethodone['third_party_code'],
				'warehouse_name'=>'',
				'address'=>'',
				'is_custom'=> 0,
				'custom_template_print'=>'',
				'print_type'=> 0,
				'print_params'=>'',//$sysShippingMethodone['print_params'],
				'transport_service_type'=> 0,
				'aging'=>'',
				'is_tracking_number'=> 0,
				'proprietary_warehouse'=>'',
				'declaration_max_value'=> 0.00,
				'declaration_max_currency'=> 'USD',
				'declaration_max_weight'=> 0.0000,
				'customer_number_config'=>'',
				'is_del'=> 0,
				'common_address_id'=> 0,
				'is_copy'=> 0,
				];
			}
		}
		unset($shippingServiceArrt);

		self::StrToUnserialize($shippingServiceArr,array('carrier_params','ship_address','return_address','service_code','address','custom_template_print','print_params','proprietary_warehouse','customer_number_config'));
	
		foreach ($shippingServiceArr as &$shippingServiceOne){
			$rule = MatchingRule::find()->select(['id','rule_name'])->where(['transportation_service_id'=>$shippingServiceOne['id'],'is_active'=>1])->andWhere('created > 0')->asArray()->all();
			$rule = Helper_Array::toHashmap($rule,'id','rule_name');
	
			$shippingServiceOne['rule'] = $rule;
	
			$shippingServiceOne['accountNickname'] = empty($carrierAccountList[$shippingServiceOne['carrier_account_id']]) ? '' : $carrierAccountList[$shippingServiceOne['carrier_account_id']];
	
			if(isset($sysShippingMethodClose[$shippingServiceOne['shipping_method_code']])){
				$shippingServiceOne['is_close'] = 1;
			}else{
				$shippingServiceOne['is_close'] = 0;
			}
		}
		// 		print_r($shippingServiceArr);die;
		return $shippingServiceArr;
	}
	
	//???????????????????????????????????????????????????????????????????????????????????????????????????????????????
	public static function CheckShipping($carrier_code,$accountid,$shipcode){
		$accountAll = SysCarrierAccount::find()->where(['carrier_code'=>$carrier_code,'is_del'=>0,'id'=>$accountid])->orderBy('create_time')->all();


		//???????????????
		$carrier = SysCarrier::findOne($carrier_code);
		if ($carrier===null) {
			return 0;
		}
		$class_name = '';
		//????????????????????????????????????type=1
		if($carrier->carrier_type){
			$class_name = '\common\api\overseaWarehouseAPI\\'.$carrier->api_class;
		}else{
			$class_name = '\common\api\carrierAPI\\'.$carrier->api_class;
		}
		if($carrier->api_class == 'LB_RTBCOMPANYCarrierAPI'){
			//???????????????????????????
			$interface = new $class_name($carrier->carrier_code);
		}else{
			$interface = new $class_name;
		}

		$shippingResult='';
		foreach ($accountAll as $key=>$account){
				if(method_exists($interface,'getCarrierShippingServiceStr')){
					$shippingResult = $interface->getCarrierShippingServiceStr($account);

					if($carrier->carrier_code=='lb_aipaqi'){
						if(!empty($shippingResult)){

							$aipaqiShipping = CarrierHelper::checkValues($shippingResult['data']);
							if(!isset($aipaqiShipping[$shipcode])){
								unset($accountAll[$key]);
								$shippingResult='';
							}
							else
								break;
						}
					}
				}
		}
		
		if($carrier->carrier_code=='lb_aipaqi'){
			if(empty($shippingResult)){
				return 0;
			}
		}
		
		return $shippingResult;
	}
	
	//????????????????????????????????????
	public static function openShippingServer($carrier_code,$shipcode,$thirdcode='',$params=array(),$shippingResult=''){
		$accountAll = SysCarrierAccount::find()->where(['carrier_code'=>$carrier_code,'is_del'=>0])->orderBy('create_time')->all();
	
// 		?????????????????????????????????????????????
		if(empty($thirdcode)){
			$defaultacc=SysCarrierAccount::find()->where(['carrier_code'=>$carrier_code,'is_del'=>0,'is_default'=>1])->orderBy('create_time')->all();
		}
		else{
			$defaultacc=SysCarrierAccount::find()->where(['carrier_code'=>$carrier_code,'is_del'=>0,'is_default'=>1])->andwhere(['like','warehouse',$thirdcode])->orderBy('create_time')->all();
		}
		if(!empty($defaultacc))
			$accountAll=$defaultacc;

		//???????????????
		$carrier = SysCarrier::findOne($carrier_code);
		if ($carrier===null) {
			return self::output(array(), 0, '????????????????????????');
		}

		$carriers = array();
		$carriers[$carrier->carrier_code] = $carrier->carrier_name;

		try{
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
			}else{
				$interface = new $class_name;
			}

		}catch(\Exception $ex){
			//?????????????????????????????????????????????????????????????????????????????????????????????
		}
		

		if(empty($shippingResult)){
			$shippingResult = array();
		}
// 				print_r($shippingResult);die;
		$tmpContinue = true;
		$carrieraccdid=0;
		foreach ($accountAll as $account){
			if(($account->carrier_type == 0) && ($tmpContinue == true)){
				$carrieraccountid=$account->id;
				$result = CarrierHelper::refreshShippingMethod($account,$carriers,$shippingResult,$shipcode);
				$tmpContinue = false;
			}
			else if($account->carrier_type == 1 && $tmpContinue == true){
				$warehouse=$account->warehouse;
				if($warehouse[0]==$thirdcode){
					$carrieraccountid=$account->id;
					$result = CarrierHelper::refreshShippingMethod($account,$carriers,$shippingResult,$shipcode);
					$tmpContinue = false;
				}
			}
		}
// 		print_r($carrieraccountid);die;

		if(empty($result)){
			$id=SysShippingService::find()->select('id')->where(['carrier_code'=>$carrier_code,'is_del'=>0,'shipping_method_code'=>$shipcode,'carrier_account_id'=>$carrieraccountid])->orderBy('create_time')->asarray()->all();
			if(!empty($id))
				return $id;
			else
				return 0;
		}
		return 0;
	}
	/**
	 * ????????????????????????????????????????????????
	 * @return boolean ??????true?????????????????????    false?????????????????????
	 */
	public static function getCarrierNewVersion($carrier_code){
		if(in_array($carrier_code, array('lb_anjun','lb_IEUBNew'))){
			return true;
		}else{
			return false;
		}
	}
	
	/* ????????????????????????Map
	*
	* @param	$carrier_code	????????????
	* @param	$is_all	???????????????????????????
	* @return
	* +-------------------------------------------------------------------------------------------
	* log			name	date					note
	* @author		hqw		2016/02/15				?????????
	* +-------------------------------------------------------------------------------------------
	*/
	public static function getShippingOrerseaWarehouseMapNew($carrier_code = '',$is_show_carrier_code = false,$is_all=false){
		$conn=\Yii::$app->db;
		
		$queryTmp = new Query;
		$queryTmp->select("a.carrier_code,a.third_party_code,a.template,b.carrier_name")
			->from("sys_shipping_method a")
			->leftJoin("sys_carrier b", "b.carrier_code = a.carrier_code")
			->where('b.carrier_type=1 and a.is_close = 0');
		
			if(!empty($carrier_code)){
				$queryTmp->andWhere(['a.carrier_code'=>$carrier_code]);
		}
	
		$queryTmp->groupBy('a.carrier_code,a.third_party_code,a.template,b.carrier_name');
		$queryTmp->orderBy('a.carrier_code');
	
		$shipOrerseaWarehouseArr = $queryTmp->createCommand($conn)->queryAll();
		$query=Warehouse::find()->asArray()->All();
		$shipOrerseaWarehouseList = array();
		
		foreach ($shipOrerseaWarehouseArr as $shipOrerseaWarehouseArrone){
			$tmp=0;
			$carrierContactArr=CarrierOpenHelper::getCarrierContact($shipOrerseaWarehouseArrone['carrier_code']);
			foreach ($query as $queryone){
				if($queryone['carrier_code']==$shipOrerseaWarehouseArrone['carrier_code'] && $queryone['third_party_code']==$shipOrerseaWarehouseArrone['third_party_code']){
					$shipOrerseaWarehouseList[]=[
						'warehouse_id'=>$queryone['warehouse_id'],
						'carrier_name'=>$queryone['name'],
						'carrier_code'=>$queryone['carrier_code'],
						'is_active'=>$queryone['is_active']=='Y'?1:0,
						'third_party_code'=>$queryone['third_party_code'],
						'carrierContactArr'=>$carrierContactArr,
					];
					$tmp=1;
					break;
				}
			}
			if($tmp==0 && $is_all){
				$shipOrerseaWarehouseList[]=[
					'warehouse_id'=>-2,
					'carrier_name'=>$shipOrerseaWarehouseArrone['carrier_name'].'-'.$shipOrerseaWarehouseArrone['template'],
					'carrier_code'=>$shipOrerseaWarehouseArrone['carrier_code'],
					'is_active'=>-1,
					'third_party_code'=>$shipOrerseaWarehouseArrone['third_party_code'],
					'carrierContactArr'=>$carrierContactArr,
				];
			}
		}
		
		foreach($shipOrerseaWarehouseList as $val){
			$flag[]=$val["is_active"];
		}
		array_multisort($flag, SORT_DESC, $shipOrerseaWarehouseList);
	
		return $shipOrerseaWarehouseList;
	}
	
	
	/**
	 * ?????????????????????view???html????????????
	 * 
	 * @param $order
	 * @param $carrierAccountInfo
	 * @param $declarationInfo
	 * @return string html
	 */
	public static function getOrdersCarrierInfoViewNewVersion($order, $sys_carrier_params, $carrierAccountInfo, $declarationInfo){
		$declaredLabel = array('chName'=>'???????????????','enName'=>'???????????????','declaredWeight'=>'????????????g','declaredValue'=>'????????????(USD)',
				'detailHsCode'=>'????????????','hasBattery'=>'????????????');
		
		$tmpHiddenData = array();
		$tmpHiddenData['products'] = $declarationInfo['products'];
		
		$tmpHtml = '';
		$tmpHeadhtml = '';
		
		$tmpHtml .= '<input type="hidden" name="id" value="'.$order->order_id.'">';
		$tmpHtml .= "<input type='hidden' name='tmpHiddenData' value='".base64_encode(json_encode($tmpHiddenData))."'>";
		
		$customerNumber = \eagle\modules\carrier\helpers\CarrierAPIHelper::getCustomerNum3($order, $carrierAccountInfo[$order->default_shipping_method_code]);
		
		$tmpOrderItems = array();
		
		//?????????????????????items
		$tmpManyTimes = 0;
		
		foreach ($declarationInfo['products'] as $product){
			$tmpManyTimes++;
			
			$tmpOrderItems[$product['order_item_id']] = array('chName'=>$product['declaration_ch'],'enName'=>$product['declaration_en'],'declaredWeight'=>$product['prod_weight'],
					'declaredValue'=>$product['declaration_value'],'detailHsCode'=>$product['declaration_code'],'hasBattery'=>$product['battery'],'name'=>$product['name'],
					'sku'=>$product['sku']
			);
		}
		
		//???????????????????????????????????????
		if(in_array($order->default_carrier_code, ['lb_anjun', 'lb_4px'])){
			//???item????????????1??????????????????????????????
			if(count($tmpOrderItems) > 1){
				$tmpHeadhtml .= '
					<div class=" prod-param-group">
						<div style="float: right; margin-top: 9px; margin-right: 10px;">
							<label>
								<input type="checkbox" style="position:relative;" name="is_CustomsFormSpan" onclick="setCustomsFormSpan(this,\''.$order->default_carrier_code.'\')">
								??????????????????
							</label>
						</div>
					</div>';
			}
		}
		
		//????????????URL
		$tmp_help_url = self::getCarrierHelpSetUrl($order->default_carrier_code);
		if(!empty($tmp_help_url)){
			$tmpHeadhtml .= '<div class="order-param-group"><div style="width:185px; float: right;margin-top:9px; margin-right: 4px;">'.
				"????????????<a href='".$tmp_help_url."' target='_blank'>????????????</a>??????????????????".
				"<span class='carrier_qtip_ajinformation'></span>".'</div></div>';
		}
		
		// dzt20200105 html?????? ????????????????????????html???????????????
		if(isset($sys_carrier_params[$order->default_carrier_code])){
		    foreach ($sys_carrier_params[$order->default_carrier_code]['order_params'] as $v){
		        $field = $v['data_key'];
		        $data = isset($order->$field)?$order->$field:'';
		
		        $data = self::getCarrierOrderParamsData($order->default_carrier_code, $v['carrier_param_key'], $declarationInfo, $carrierAccountInfo[$order->default_shipping_method_code], $order, $data);
		
		        if($v['carrier_param_key'] == 'total_weight_4px'){
		            $data = (string)$data;
		        }
		
		        if($data != 'not_continue_carrier'){
		            $tmpHeadhtml .= self::getCarrierViewHeadhtml($v, $data, $order->default_carrier_code, $carrierAccountInfo[$order->default_shipping_method_code], $order);
		        }
		    }
		}
		
		$tmpHeadhtml .= '<div class=" order-param-group" style="width: 350px;" >
        <div style="float: left;width: 120px;margin-top: 9px;margin-right: 10px;"><label qtipkey="carrier_customer_number">???????????????<span class="star" style="color: red;">*</span></label></div>
        <div style="float: left;"><input type="text"  class="eagle-form-control" name="customer_number" style="width:150px;" value ='.$customerNumber.'>';
		if(($order->carrier_step==\eagle\modules\order\models\OdOrder::CARRIER_CANCELED) && (count($order->trackinfos) > 0)){
			$tmpHeadhtml .= '<span qtipkey="carrier_order_upload_again" style="color:red; margin-left: 4px;">????????????</span>';
		}
		$tmpHeadhtml .= '</div></div>';
		
		$tmpHeadhtml = '<div style="width: 100%; float:left; ">'.self::getAdditionalHeadhtml($order->default_carrier_code, $carrierAccountInfo[$order->default_shipping_method_code]).$tmpHeadhtml.'</div>';
		
		$tmpItmeshtml = '<div name="items_param" style="float:left; ">';
		
		foreach($tmpOrderItems as $tmpOrderItemId => $product){
			$tmpItmeshtml .= '<hr style="margin-top:1px;margin-bottom:2px;clear: both;"/>'.'<h5 class="text-success" style="text-align:left;">????????????'.$product['name'].'</h5><div style="width: 100%;" name="item_param">';
			$tmpItemsonehtml = '';
			
			$tmpItemsonehtml .= Html::hiddenInput('order_item_id'.'[]',$tmpOrderItemId);
				
			foreach ($product as $productFieldKey => $productFieldVal){
				if(!in_array($productFieldKey,array('chName','enName','declaredWeight','declaredValue','detailHsCode','hasBattery'))) continue;
				
				if(($order->default_carrier_code == 'lb_IEUBNew') && ($productFieldKey == 'chName')){
					//????????????????????????????????????????????????Data
					$productFieldVal = self::getCarrierItmesParamsData($order->default_carrier_code, 'cnname', $product, $carrierAccountInfo[$order->default_shipping_method_code], $order, $productFieldVal, 1);
				}
				
				if($productFieldKey == 'hasBattery')
					$tmpinput = Html::dropDownList($productFieldKey.'[]',$productFieldVal,array('N'=>'???','Y'=>'???'),['style'=>'width:150px;','class'=>'eagle-form-control']);
				else
					$tmpinput = Html::input('text',$productFieldKey.'[]',$productFieldVal,['style'=>'width:150px;','class'=>'eagle-form-control',]);
				
				$tmpItemsonehtml .= '<div class=" prod-param-group" ><div style="float: right" >'.$tmpinput.'</div><div style="width:120px; float: right;margin-top:9px;margin-right:4px;">'.
						'<label>'.$declaredLabel[$productFieldKey].
						'</label></div></div>';
			}
			
			if($order->default_carrier_code == 'lb_IEUBNew'){
				$tmpinput = Html::dropDownList('declaredUnit'.'[]','???',LB_IEUBNewCarrierAPI::$eub_unit,['style'=>'width:150px;','class'=>'eagle-form-control']);
				
				$tmpItemsonehtml .= '<div class=" prod-param-group" ><div style="float: right" >'.$tmpinput.'</div><div style="width:120px; float: right;margin-top:9px;margin-right:4px;">'.
						'<label>'.'????????????'.
						'</label></div></div>';
			}
			
			$tmpItmeshtml = $tmpItmeshtml.$tmpItemsonehtml.'</div>';
		}
		//????????????div
		$tmpCustomsFormSpanhtml = '<div name="CustomsFormSpanItem" style="width: 100%; float:left; "></div>';
		
		return $tmpHtml.$tmpHeadhtml.$tmpCustomsFormSpanhtml.$tmpItmeshtml.'</div>';
	}
	
	/**
	 * ????????????????????????????????????URL
	 * @param $carrier_code
	 */
	public static function getCarrierHelpSetUrl($carrier_code){
		$help_url = '';
		
		switch ($carrier_code){
			case 'lb_anjun':
				$help_url = '/configuration/carrierconfig/index?tcarrier_code=lb_anjun#syscarrier_show_div_lb_anjun';
				break;
			default:
				$help_url = '';
		}
		
		return $help_url;
	}
	
	/**
	 * ??????????????????????????????
	 * @param $carrier_code
	 * @param $type  0???????????? ???1????????????
	 * @return $carrierContactArr=array('???????????????'=>array(
	 * 													'pickupAddress'=>'??????',
	 * 													'telContact'=>'????????????',
	 * 													'qq'=>'qq',
	 * 													'qqtype'=>'qq??????(0??????,1??????)')
	 * 									)
	 */
	public static function getCarrierContact($carrier_code){
		$carrierContactArr = array(
				'lb_CNE'=>array('pickupAddress'=>'????????????????????????','telContact'=>'15821248696','qq'=>'2880865256','qqtype'=>'0'),
				'lb_anjun'=>array('pickupAddress'=>'?????????????????????????????????','telContact'=>'400-999-6128','qq'=>'2853253500','qqtype'=>'0'),
				'lb_diwuzhou'=>array('pickupAddress'=>'????????????????????????','telContact'=>'13713659006','qq'=>'930097651','qqtype'=>'0'),
				'lb_ande'=>array('pickupAddress'=>'?????????????????????????????????','telContact'=>'18588920007','qq'=>'2853686617','qqtype'=>'0'),
				'lb_yilong'=>array('pickupAddress'=>'??????????????????????????????????????????','telContact'=>'15921148859','qq'=>'349560526','qqtype'=>'0'),
				'lb_4px'=>array('pickupAddress'=>'????????????','telContact'=>'13602570615','qq'=>'1061796015','qqtype'=>'0'),
				'lb_4pxNew'=>array('pickupAddress'=>'????????????','telContact'=>'13602570615','qq'=>'1061796015','qqtype'=>'0'),
				'lb_wishyou'=>array('pickupAddress'=>'','telContact'=>'','qq'=>'2518043725','qqtype'=>'0'),
				'lb_yuntu'=>array('pickupAddress'=>'','telContact'=>'400-0262-126','qq'=>'2851260179','qqtype'=>'0'),
				'lb_xiapu'=>array('pickupAddress'=>'','telContact'=>'13923780976','qq'=>'2355838766','qqtype'=>'0'),
				'lb_aiseninternational'=>array('pickupAddress'=>'?????????????????????????????????','telContact'=>'13318286800','qq'=>'1436167837','qqtype'=>'0'),
				'lb_chukouyi'=>array('pickupAddress'=>'????????????','telContact'=>'15019998788','qq'=>'873217476','qqtype'=>'0'),
				'lb_yitongguan'=>array('pickupAddress'=>'??????','telContact'=>'','qq'=>'2789692976','qqtype'=>'0'),
				'lb_miaoxin'=>array('pickupAddress'=>'??????','telContact'=>'021-59881019','qq'=>'1815138386','qqtype'=>'0'),
		        'lb_miaoxinguoji'=>array('pickupAddress'=>'??????','telContact'=>'0755-84557420','qq'=>'1815138386','qqtype'=>'0'),
				'lb_xingqian'=>array('pickupAddress'=>'??????','telContact'=>'13824485784','qq'=>'','qqtype'=>'0'),
				'lb_EYYC'=>array('pickupAddress'=>'????????????','telContact'=>'13136156335','qq'=>'2194965774','qqtype'=>'0'),
		        'lb_jiateng'=>array('pickupAddress'=>'???????????????','telContact'=>'15306568810???0571-85393132','qq'=>'2885159330','qqtype'=>'0'),
		        'lb_santaic'=>array('pickupAddress'=>'???????????????????????????????????????????????????','telContact'=>'18938091519','qq'=>'947736292','qqtype'=>'0'),
				'lb_yide'=>array('pickupAddress'=>'????????????????????????','telContact'=>'020-36207679','qq'=>'515483755','qqtype'=>'0'),
				'lb_quanchengdongli'=>array('pickupAddress'=>'??????','telContact'=>'15375211183','qq'=>'124770577','qqtype'=>'0'),
				'lb_chuangyu'=>array('pickupAddress'=>'???????????????????????????????????????','telContact'=>'18675837801','qq'=>'2880522688','qqtype'=>'0'),
				'lb_xiangda'=>array('pickupAddress'=>'????????????????????????????????????????????????','telContact'=>'18150015666','qq'=>'1029381712','qqtype'=>'0'),
				'lb_xinding'=>array('pickupAddress'=>'','telContact'=>'18203687670','qq'=>'3359721522','qqtype'=>'0'),
				'lb_bantouyan'=>array('pickupAddress'=>'','telContact'=>'','qq'=>'3005655427','qqtype'=>'0'),
		        'lb_huayangtong'=>array('pickupAddress'=>'??????','telContact'=>'','qq'=>'13613019656','qqtype'=>'0'),
		        
// 				''=>array('pickupAddress'=>'','telContact'=>'','qq'=>'','qqtype'=>'0'),
		);
				
		$result=array();
		foreach ($carrierContactArr as $key=>$item){
			if($carrier_code==$key){
				$result=$item;break;
			}
		}
		
		return $result;
	}
	
	/**
	 * ?????????????????????email ????????????????????????????????????????????????????????????abc@qq.com??????
	 */
	public static function getOrderEmailResults($email){
		$result = preg_match("/^[0-9a-zA-Z]+@(([0-9a-zA-Z]+)[.])+[a-z]{2,4}$/i",$email);
		
		if($result == 0)
			return 'abc@bb.com';
		else
			return $email;
	}
	
	/**
	 * ????????????????????????
	 * @param $type ?????? 1????????????2????????????3????????????4??????????????????
	 * @param $reponse  ??????
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lgw		2016/08/12				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function saveDeclare($type,$reponse){
		if(empty($reponse))
	    	return self::output(array(), 1, array('????????????'));

		if($type==3){
			$CommonDeclaredInfoOne = CommonDeclaredInfo::find()->where(['id'=>$reponse['cid']])->one();
			$default=$CommonDeclaredInfoOne->is_default;
			if($CommonDeclaredInfoOne->delete()){
				if($default==1){
					$CommonDeclaredInfoOne = CommonDeclaredInfo::find()->one();
					if(!empty($CommonDeclaredInfoOne)){
						$CommonDeclaredInfoOne->is_default='1';
						$CommonDeclaredInfoOne->save(false);
					}
				}
				return self::output(array(), 0, array('????????????'));
			}else{
				return self::output(array(), 1, array('????????????'));
			}
		}

		if($type==2 || $type==4){
			$CommonDeclaredInfoOne = CommonDeclaredInfo::find()->where(['id'=>$reponse['cid']])->one();
		}
		else{
			$CommonDeclaredInfoOne = new CommonDeclaredInfo();
			$CommonDeclaredInfootherOne = CommonDeclaredInfo::find()->one();
			if(empty($CommonDeclaredInfootherOne))
				$CommonDeclaredInfoOne->is_default='1';
		}

		if($type==4){
			$CommonDeclaredInfoOne->is_default=isset($reponse['is_default'])?$reponse['is_default']:'0';
			
			$CommonDeclaredInfoAll = CommonDeclaredInfo::find()->where(['<>','id',$reponse['cid']])->all();
			foreach ($CommonDeclaredInfoAll as $CommonDeclaredInfoAllone){
				$CommonDeclaredInfoAllone->is_default='0';
				$CommonDeclaredInfoAllone->save(false);
			}
			
			if($CommonDeclaredInfoOne->save(false)){
				return self::output(array(), 0, array('????????????'));
			}else{
				return self::output(array(), 1, array('????????????'));
			}
		}
		else{
			if($reponse['cfName']=='' || is_null($reponse['cfName']))
				return self::output(array(), 1, array('???????????????????????????'));
			if($reponse['nameCh']=='' || is_null($reponse['nameCh']))
				return self::output(array(), 1, array('???????????????????????????'));
			if($reponse['nameEn']=='' || is_null($reponse['nameEn']))
				return self::output(array(), 1, array('???????????????????????????'));
			if($reponse['declaredValue']=='' || is_null($reponse['declaredValue']) || !is_numeric($reponse['declaredValue']))
				return self::output(array(), 1, array('????????????????????????,????????????'));
			$temp = explode('.', $reponse['declaredValue']);
			if(sizeof($temp)>1 && strlen(end($temp))>2)
				return self::output(array(), 1, array('???????????????????????????1-2???'));
			if($reponse['weight']=='' || is_null($reponse['weight']) || !is_numeric($reponse['weight']))
				return self::output(array(), 1, array('????????????????????????,????????????'));			
			if(strpos($reponse['weight'],'.') || (float)$reponse['weight']<0)
				return self::output(array(), 1, array('????????????????????????????????????'));
			
			
			
			
		    $CommonDeclaredInfoOne->custom_name=$reponse['cfName'];
		    $CommonDeclaredInfoOne->ch_name=$reponse['nameCh'];
		    $CommonDeclaredInfoOne->en_name=$reponse['nameEn'];
		    $CommonDeclaredInfoOne->declared_value=$reponse['declaredValue'];
		    $CommonDeclaredInfoOne->declared_weight=$reponse['weight'];
		    $CommonDeclaredInfoOne->detail_hs_code=$reponse['defaultHsCode'];
		    
		    if($CommonDeclaredInfoOne->save(false)){
		    	return self::output(array(), 0, array('????????????'));
		    }else{
		    	return self::output(array(), 1, array('????????????'));
		    }
		}
	}

	/**
	 * ????????????????????????
	 * @param ??????$id=0
	 * @param ??????$isdefault=true ??????????????????????????????
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lgw		2016/08/12				?????????
	 * +-------------------------------------------------------------------------------------------
	 * @return $result array
	 * id				??????0??????????????????????????????????????????????????????????????????????????? ??????0???????????????????????????????????????????????????
	 * custom_name		???????????????
	 * ch_name			???????????????
	 * en_name			???????????????
	 * declared_value	????????????
	 * declared_weight	????????????
	 * detail_hs_code	????????????
	 */
	public static function getCommonDeclaredInfoByDefault($uid=''){
		global $CACHE;
		$result=array(
				'id'=>'0',
				'custom_name'=>'',
				'ch_name'=>'??????',
				'en_name'=>'gift',
				'declared_value'=>'1',
				'declared_weight'=>'50',
				'detail_hs_code'=>'',
		);
		
		//??????????????????????????????????????????,?????????????????????????????????id??????
		if (isset($CACHE[$uid]['CommonDeclaredInfo'])){
			$CommonDeclaredInfo = $CACHE[$uid]['CommonDeclaredInfo'];
		}else{
			$CommonDeclaredInfo = CommonDeclaredInfo::find()->orderBy('is_default desc,id desc')->asArray()->one();
			$CACHE[$uid]['CommonDeclaredInfo'] = $CommonDeclaredInfo;
		}
		
		
		if(!empty($CommonDeclaredInfo)){
			$result['id']=$CommonDeclaredInfo['id'];
			$result['custom_name']=$CommonDeclaredInfo['custom_name'];
			$result['ch_name']=$CommonDeclaredInfo['ch_name'];
			$result['en_name']=$CommonDeclaredInfo['en_name'];
			$result['declared_value']=$CommonDeclaredInfo['declared_value'];
			$result['declared_weight']=$CommonDeclaredInfo['declared_weight'];
			$result['detail_hs_code']=$CommonDeclaredInfo['detail_hs_code'];
		}
		
		$result['declared_weight']=(int)$result['declared_weight'];
		
		return $result;
	}
	
	//?????????????????????????????????
	public static function getServiceUrlByCarrierCode($carrier_code){
		$carrier_url = array();
		$carrier_url['lb_wanou'] = 'https://www.17track.net/en/track?fc=100011';
		
		if(isset($carrier_url[$carrier_code])){
			return $carrier_url[$carrier_code];
		}else{
			return 'http://www.17track.net';
		}
	}
	
	/**
	 * ????????????????????????
	 *
	 *@param $print_params ??????getCarrierShippingServiceUserById??????????????????????????????
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lgw		2016/10/10				?????????
	 * +-------------------------------------------------------------------------------------------
	 * @return $result=array(
	 * 							??????'carrier_lable'=array(id,
	 * 								type ??????carrier_lable:????????????, declare_lable:????????????, items_lable:?????????
	 * 								template_name  ??????
	 * 								template_img   ????????????
	 * 								helper_class   helper???????????????????????????PrintPdfHelper'
	 * 								helper_function   ???????????????????????????
	 * 								additional_print_options=???????????????????????????
	 * 								) 
	 *  						?????????'declare_lable'=array(id,
	 * 								type
	 * 								template_name
	 * 								template_img
	 * 								helper_class
	 * 								helper_function
	 * 								additional_print_options
	 * 								) 
	 *  						?????????'items_lable'=array(id,
	 * 								type
	 * 								template_name
	 * 								template_img
	 * 								helper_class
	 * 								helper_function
	 * 								additional_print_options
	 * 								) 
	 * 							'printFormat'=>???????????? 0:A4 1:10*10
	 * 							'printAddVal'=???????????????????????????array{
	 * 															AddOrder:on;AddSku:on;addCustomsCn:on;
	 * 															Order_show:1;   ????????????????????????????????????
	 * 															Sku_show:1; ??????????????????SKU
	 * 															CustomsCn_show:1; ?????????????????????????????????
	 * 															}
	 * )
	 */
	public static function getCarrierTemplateHighcopy($print_params){
		$query_carrier_lable=array();
		$query_declare_lable=array();
		$query_items_lable=array();
				
		$arr=array();
		$printAddVal=array();
		if(isset($print_params['label_littlebossOptionsArrNew'])){
			if(!empty($print_params['label_littlebossOptionsArrNew']['carrier_lable'])){
				$query_carrier_lable=CarrierTemplateHighcopy::find()->where(['id'=>$print_params['label_littlebossOptionsArrNew']['carrier_lable']])->asArray()->all();
				$arr[]=json_decode($query_carrier_lable[0]['additional_print_options']);
			}
			if(!empty($print_params['label_littlebossOptionsArrNew']['declare_lable'])){
				$query_declare_lable=CarrierTemplateHighcopy::find()->where(['id'=>$print_params['label_littlebossOptionsArrNew']['declare_lable']])->asArray()->all();
				$arr[]=json_decode($query_declare_lable[0]['additional_print_options']);
			}
			if(!empty($print_params['label_littlebossOptionsArrNew']['items_lable'])){
				$query_items_lable=CarrierTemplateHighcopy::find()->where(['id'=>$print_params['label_littlebossOptionsArrNew']['items_lable']])->asArray()->all();
				$arr[]=json_decode($query_items_lable[0]['additional_print_options']);
			}
		}
		$str='';
		foreach ($arr as $key=>$arrone){
			if($key==0)
				$whoform='1';
			else if($key==1)
				$whoform='2';
			else if($key==2)
				$whoform='3';
			if(!empty($arrone) && in_array('isAddOrder', $arrone)){
				$printAddVal['Order_show']=1;
				$str=$str.$whoform.':Order_show,';
			}
			if(!empty($arrone) && in_array('isAddSku', $arrone)){
				$printAddVal['Sku_show']=1;
				$str=$str.$whoform.':Sku_show,';
			}
			if(!empty($arrone) && in_array('isCustomsCn', $arrone)){
				$printAddVal['CustomsCn_show']=1;
				$str=$str.$whoform.':CustomsCn_show,';
			}
			$str=$str.'|';
		}
		$printAddVal['addshow']=$str;
		
		if(!empty($print_params['label_littlebossOptionsArrNew']['printAddVal'])){
			$temp=json_decode($print_params['label_littlebossOptionsArrNew']['printAddVal']);
			if(isset($temp->addOrder))
				$printAddVal['addOrder']='on';
			if(isset($temp->addSku))
				$printAddVal['addSku']='on';
			if(isset($temp->addCustomsCn))
				$printAddVal['addCustomsCn']='on';
		}

		$result=array(
				'carrier_lable'=>$query_carrier_lable,
				'declare_lable'=>$query_declare_lable,
				'items_lable'=>$query_items_lable,
				'printFormat'=>empty($print_params['label_littlebossOptionsArrNew']['printFormat'])?'0':$print_params['label_littlebossOptionsArrNew']['printFormat'],
				'printAddVal'=>$printAddVal,
		);
		
		return $result;
	}
	
	/**
	 * ????????????????????????
	 *
	 *@param $type ?????????????????? carrier_lable:????????????, declare_lable:????????????, items_lable:?????????
	 *@param $id ????????????ID
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		lgw		2016/10/10				?????????
	 * +-------------------------------------------------------------------------------------------
	 * @return $result=array(
	 * 			$CarrierTemplateHighcopy=array() ???????????????????????????
	 * 			$CarrierTemplateHighcopyType=array() ???????????????????????????
	 * )
	 */
	public static function getCarrierTemplateHighcopyType($type,$id=0){
		$query=CarrierTemplateHighcopy::find()->where(['type'=>$type])->asArray()->all();
		foreach ($query as $key=>$queryone){
			$arr=json_decode($queryone['additional_print_options']);
			$str='';
			if(!empty($arr) && in_array('isAddOrder',$arr))
				$str=$str.'Order_show,';
			if(!empty($arr) && in_array('isAddSku',$arr))
				$str=$str.'Sku_show,';
			if(!empty($arr) && in_array('isCustomsCn',$arr))
				$str=$str.'CustomsCn_show,';
			$query[$key]['additional_print_options']=$str;
		}

		//???????????????????????????
		$CarrierTemplateHighcopy=CarrierTemplateHighcopy::find()->where(['id'=>$id])->asArray()->all();
		if(!empty($CarrierTemplateHighcopy[0]['additional_print_options'])){
			$arr=json_decode($CarrierTemplateHighcopy[0]['additional_print_options']);
			$str='';
			if(!empty($arr) && in_array('isAddOrder',$arr))
				$str=$str.'Order_show,';
			if(!empty($arr) && in_array('isAddSku',$arr))
				$str=$str.'Sku_show,';
			if(!empty($arr) && in_array('isCustomsCn',$arr))
				$str=$str.'CustomsCn_show,';
			$CarrierTemplateHighcopy[0]['additional_print_options']=$str;
		}

		$result=array(
				'CarrierTemplateHighcopy'=>$CarrierTemplateHighcopy,
				'CarrierTemplateHighcopyType'=>$query,
		);
		return $result;
	}
	
	/**
	 * ???????????????????????????
	 *
	 * @param
	 * 			$data['accountid'] 			????????????????????????????????????id
	 * 			$data['warehouse_code']		?????????????????????ID
	 * @return
	 */
	public static function getPubOverseasWarehouseStockList($data){
// 		$data = array();
// 		$data['accountid'] = '';
// 		$data['warehouse_code'] = '1000001';
		
		$account = SysCarrierAccount::find()->where(['id'=>$data['accountid']])->one();
		
		if($account == null){
			return array('error' => 1, 'data' => array(), 'msg' => '????????????,???ID??????????????????.');
		}
		
		$data['api_params'] = $account->api_params;
		
		$Carrier = SysCarrier::findOne($account->carrier_code);
		
		//??????????????????????????????????????????????????? Start
		$class_name = '';
		//????????????????????????????????????type=1
		if($Carrier->carrier_type == 0){
			return array('error' => 1, 'data' => array(), 'msg' => '??????????????????????????????!');
		}
		$class_name = '\common\api\overseaWarehouseAPI\\'.$Carrier->api_class;
		
		$interface = new $class_name;
		
		if(method_exists($interface,'getOverseasWarehouseStockList')){
			$tmpOverseasWarehouseStockList = $interface->getOverseasWarehouseStockList($data);
			
			return $tmpOverseasWarehouseStockList;
		}
		
		return array('error' => 1, 'data' => array(), 'msg' => '???????????????????????????!');
	}
	
	//?????????????????????????????????
	public static function setUpdateAliexpressAddressInof($puid, $sellerloginid = '', $isHomeLanshou = 'Y', $is_product = 1){
		$result = array('error'=>0, 'msg'=>'');
		
		$aliexpressModel = SaasAliexpressUser::find()->where(['uid'=>$puid]);
		
		if($sellerloginid != ''){
			$aliexpressModel->andWhere(['sellerloginid'=>$sellerloginid]);
		}
		 
		$aliexpressUsers = $aliexpressModel
		->orderBy('refresh_token_timeout desc')
		->all();
		
		foreach ($aliexpressUsers as $aliexpressUserVal){
			//****************????????????????????????????????????v2???    ????????????     start*************
			$is_aliexpress_v2 = AliexpressInterface_Helper_Qimen::CheckAliexpressV2($aliexpressUserVal['sellerloginid']);
			if($is_aliexpress_v2){
				$aliexpressAddressArr = LB_ALIONLINEDELIVERYCarrierAPI::getAliexpressLogisticsSellerAddresses($aliexpressUserVal['sellerloginid'], '["sender","pickup","refund"]');
			}
			else if($isHomeLanshou == 'Y'){
				$aliexpressAddressArr = LB_ALIONLINEDELIVERYCarrierAPI::getAliexpressLogisticsSellerAddresses($aliexpressUserVal['sellerloginid'], '["sender","pickup"'.(empty($is_product) ? '' : ',"refund"').']');
			}
			else{
				$aliexpressAddressArr = LB_ALIONLINEDELIVERYCarrierAPI::getAliexpressLogisticsSellerAddresses($aliexpressUserVal['sellerloginid'], '["sender"'.(empty($is_product) ? '' : ',"refund"').']');
			}
			
			$tmpAddressArr = array("sender"=>array(),"pickup"=>array(),"refund"=>array());
		
			if($aliexpressAddressArr['Ack'] == false){
				$result['error'] = 1;
				$result['msg'] .= $aliexpressAddressArr['error'];
				 
				continue;
			}
		
			//????????????????????? S
			if(isset($aliexpressAddressArr['addressInfo']['senderSellerAddressesList'])){
				if(is_array($aliexpressAddressArr['addressInfo']['senderSellerAddressesList'])){
					foreach ($aliexpressAddressArr['addressInfo']['senderSellerAddressesList'] as $tmpSenderAddress){
						if($tmpSenderAddress['isDefault'] == 1){
						}
						$tmpAddressArr['sender'][$tmpSenderAddress['addressId']] = $tmpSenderAddress;
					}
				}
			}
			//????????????????????? E
		
			//????????????????????? S
			if(isset($aliexpressAddressArr['addressInfo']['pickupSellerAddressesList'])){
				if(is_array($aliexpressAddressArr['addressInfo']['pickupSellerAddressesList'])){
					foreach ($aliexpressAddressArr['addressInfo']['pickupSellerAddressesList'] as $tmpSenderAddress){
						if($tmpSenderAddress['isDefault'] == 1){
						}
						$tmpAddressArr['pickup'][$tmpSenderAddress['addressId']] = $tmpSenderAddress;
					}
				}
			}
			//????????????????????? E
		
			//???????????? S
			$shippingfrom_refundaddressID = 0;
		
			if(isset($aliexpressAddressArr['addressInfo']['refundSellerAddressesList'])){
				if(is_array($aliexpressAddressArr['addressInfo']['refundSellerAddressesList'])){
					//???????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????0???
					foreach ($aliexpressAddressArr['addressInfo']['refundSellerAddressesList'] as $tmpSenderAddress){
						if($tmpSenderAddress['isDefault'] == 1){
						}
						$tmpAddressArr['refund'][$tmpSenderAddress['addressId']] = $tmpSenderAddress;
					}
				}
			}
			//???????????? E
		
			$aliexpressUserVal->address_info = json_encode($tmpAddressArr);
			$aliexpressUserVal->save();
		}
		 
		if($result['error'] == 0){
			$result['msg'] = '????????????';
		}
		
		return $result;
	}

	//eDis?????????????????????????????????
	public static function setUpdateEdisAddressInof($puid){
		$result = array('error'=>0, 'msg'=>'');
		
		$eDisModel = SaasEbayUser::find()->where(['uid'=>$puid]);
		
		$eDisUser = $eDisModel
		->orderBy('create_time desc')
		->all();
		
		$result=array();
		
		$edisAddressRe=array();
		$edisPreferenceRe=array();

		foreach ($eDisUser as $eDisUsercode){
			$lb_edisCarrierApi=new LB_EDISCarrierAPI();
			$edisAddressRe = $lb_edisCarrierApi->getAddressPreferenceList($eDisUsercode["selleruserid"]);  
			$edisPreferenceRe=$lb_edisCarrierApi->getConsignPreferenceList($eDisUsercode["selleruserid"]);

			$result[$eDisUsercode["selleruserid"]]["AddressPreferenceList"]=empty($edisAddressRe["data"])?$edisAddressRe["msg"]:$edisAddressRe["data"];
			$result[$eDisUsercode["selleruserid"]]["ConsignPreferenceList"]=empty($edisPreferenceRe["data"])?$edisPreferenceRe["msg"]:$edisPreferenceRe["data"];
			
		}
		
		return $result;
		
	}
	//eDis???????????????????????????????????????
	public static function getEdisAddressHtml($data,$edisAddress=array(),$edisConsign=array()){
		
		$html="";
		$html.="<tr><th>??????<input type='hidden' name='params[carrierParams][edisAddressoinfo][alledislist]' value='".json_encode($data)."'></th><th>????????????</th><th>????????????</th></tr>";
		
		if(!empty($data)){
			foreach ($data as $key=>$code){
				$html.="<tr>";
				$html.="<td>".$key."</td>";
				
				if(is_array($code["AddressPreferenceList"])){
					$html.="<td>";
					$html.="<select class='edis_select' name='params[carrierParams][edisAddressoinfo][edisAddress][".$key."]'  style='width:113px;'>";
					foreach ($code["AddressPreferenceList"] as $AddressPreferenceListcode){ 
						if(isset($edisAddress[$key]) && !empty($edisAddress[$key])){
							$edisAddress_val=explode("&",$edisAddress[$key]);
							if($edisAddress_val[0]==$AddressPreferenceListcode["addressId"])
								$check="selected='selected'";
							else 
								$check="";
							$html.="<option value='".$AddressPreferenceListcode["addressId"]."&".$AddressPreferenceListcode["name"]."' ".$check." >".$AddressPreferenceListcode["name"]."</option>";
						}
						else
							$html.="<option value='".$AddressPreferenceListcode["addressId"]."&".$AddressPreferenceListcode["name"]."'>".$AddressPreferenceListcode["name"]."</option>";
					}
					$html.="</select>";
					$html.="</td>";
				}
				else{
					$html.="<td><select class='edis_select' name='params[carrierParams][edisAddressoinfo][edisAddress][".$key."]'  style='width:113px;'><option value='0'>-</option></select></td>";
				}
				
				if(is_array($code["ConsignPreferenceList"])){
					$html.="<td>";
					$html.="<select class='edis_select' name='params[carrierParams][edisAddressoinfo][edisConsign][".$key."]' onchange='' style='width:113px;'>";
					foreach ($code["ConsignPreferenceList"] as $ConsignPreferenceListcode){
						if(isset($edisConsign[$key]) && !empty($edisConsign[$key])){
							$edisConsign_val=explode("&",$edisConsign[$key]);
							if($edisConsign_val[0]==$ConsignPreferenceListcode["consignId"])
								$check="selected='selected'";
							else
								$check="";
							$html.="<option value='".$ConsignPreferenceListcode["consignId"]."&".$ConsignPreferenceListcode["name"]."' ".$check." >".$ConsignPreferenceListcode["name"]."</option>";
						}
						else
							$html.="<option value='".$ConsignPreferenceListcode["consignId"]."&".$ConsignPreferenceListcode["name"]."'>".$ConsignPreferenceListcode["name"]."</option>";					
						
					}
					$html.="</select>";
					$html.="</td>";
				}
				else{
					$html.="<td><select class='edis_select' name='params[carrierParams][edisAddressoinfo][edisConsign][".$key."]' onchange='' style='width:113px;'><option value='0'>-</option></select></td>";
				}
				
				$html.="</tr>";
			}
		}
		
		return $html;
	}
	//eDis???????????????????????????????????????????????????
	public static function UpdateEdisAddressInof($puid,$serviceID){
		$query = SysShippingService::find();
		$query->andWhere(['id'=>$serviceID]);
		$shippingServiceArr = $query->asArray()->all();

		//?????????????????????????????????????????????
		$edisAddress=$edisConsign=array();
		if(!empty($shippingServiceArr)){
			self::StrToUnserialize($shippingServiceArr,array('carrier_params'));
			$carrier_params=empty($shippingServiceArr[0]["carrier_params"]["edisAddressoinfo"])?array():$shippingServiceArr[0]["carrier_params"]["edisAddressoinfo"];
			$edisAddress=empty($carrier_params["edisAddress"])?array():$carrier_params["edisAddress"];
			$edisConsign=empty($carrier_params["edisConsign"])?array():$carrier_params["edisConsign"];
		}
		
		$result = self::setUpdateEdisAddressInof($puid);
		$result_view = self::getEdisAddressHtml($result,$edisAddress,$edisConsign);
		
		return $result_view;
	}
	
	//?????????????????????????????????????????????
	public static function isExistCrtemplateOld(){
		return false;	//?????????????????????????????????????????????
		$cr = CrTemplate::findOne(['template_version'=>0]);
		
		if($cr === null){
			return false;
		}else{
			return true;
		}
	}
	
	//??????????????????????????????????????????????????????API serviceName ??????true??????SMT????????????
	public static function isAliexpressCarrierService($shipping_method_code){
		$tmp_arr = array('YANWEN_JYT','SGP_OMP','SINOTRANS_PY','OMNIVA_ECONOMY','ITELLA_PY','ROYAL_MAIL_PY','RUSTON_ECONOMY','SF_EPARCEL_OM','SUNYOU_ECONOMY','YANWEN_ECONOMY','CAINIAO_SAVER','CAINIAO_STANDARD','ECONOMIC139','FOURPX_RM','ASENDIA','ARAMEX','ATPOST','BPOST','CAPOST','CDEK','CPAM','CPAP','CHUKOU1','CNE','SINOTRANS_AM','EMS_SH_ZX_US','DPD','LAOPOST','EMS_ZX_ZX_US','EQUICK','FLYT','GLS','HKPAM','HKPAP','CTR_LAND_PICKUP','HUPOST','MEEST','MIUSON','MNPOST','NZPOST','EEPOST','ONEWORLD','PONY','POST_MY','ITELLA','POST_NL','RETS','RPO','CPAM_HRB','SF_EPARCEL','SFC','SGP','YANWEN_AM','SUNYOU_RM','SEP','CHP','TWPOST','TEA','THPOST','PTT','UBI','UAPOST','VNPOST','YODEL','YUNTU','CAINIAO_PREMIUM','DHL','DHLECOM','TOLL','EMS','E_EMS','GATI','SPSR_CN','SF','SPEEDPOST','TNT','UPSE','UPS','FEDEX_IE','FEDEX','Other','RUSSIAN_POST','CDEK_RU','IML','PONY_RU','SPSR_RU','OTHER_RU','USPS','UPS_US','OTHER_US','ROYAL_MAIL','DHL_UK','OTHER_UK','DEUTSCHE_POST','DHL_DE','OTHER_DE','ENVIALIA','CORREOS','DHL_ES','OTHER_ES','LAPOSTE','DHL_FR','OTHER_FR','POSTEITALIANE','DHL_IT','OTHER_IT','AUSPOST','OTHER_AU','JNE','ACOMMERCE');
		
		if(in_array($shipping_method_code, $tmp_arr)){
			return true;
		}else{
			return false;
		}
	}
	
	//????????????????????????????????????????????????
	public static function getAliexpressServerCodeMap(){
		$tmpServerCode = 'SGP_WLB_FPXGZ:SGP;SGP_WLB_FPXYW:SGP;SGP_OMP_FPXDG:SGP_OMP;SGP_WLB_FPXXM:SGP;SGP_OMP_YANWENSH:SGP_OMP;SGP_OMP_FPXFS:SGP_OMP;SGP_OMP_FPXZS:SGP_OMP;SGP_OMP_YANWENNJ:SGP_OMP;SGP_OMP_FPXXM:SGP_OMP;SGP_OMP_YANWENHZ:SGP_OMP;SGP_OMP_YANWENBJ:SGP_OMP;SGP_WLB_FPXSS:SGP;SGP_WLB_FPXSH:SGP;SGP_OMP_FPXGZ:SGP_OMP;SGP_OMP_YANWENYW:SGP_OMP;SGP_OMP_FPXQZ:SGP_OMP;SGP_OMP_FPXFZ:SGP_OMP;SGP_OMP_FPXST:SGP_OMP;SGP_OMP_FPXSZ:SGP_OMP;SGP_OMP_TS_12350033:SGP_OMP;CAINIAO_PREMIUM_YANWENSH:CAINIAO_PREMIUM;SGP_OMP_YANWENWZ:SGP_OMP;SGP_OMP_YANWENNB:SGP_OMP;SGP_OMP_TS_12720665:SGP_OMP;SGP_OMP_FPXXG:SGP_OMP;CAINIAO_PREMIUM_FPXDG:CAINIAO_PREMIUM;CAINIAO_PREMIUM_FPXZS:CAINIAO_PREMIUM;CAINIAO_PREMIUM_YANWENBJ:CAINIAO_PREMIUM;CAINIAO_PREMIUM_FPXXM:CAINIAO_PREMIUM;SGP_OMP_TS_12720681:SGP_OMP;CAINIAO_PREMIUM_YANWENYW:CAINIAO_PREMIUM;CAINIAO_PREMIUM_YANWENNJ:CAINIAO_PREMIUM;CAINIAO_PREMIUM_YANWENNB:CAINIAO_PREMIUM;CAINIAO_PREMIUM_YANWENHZ:CAINIAO_PREMIUM;CAINIAO_PREMIUM_FPXGZ:CAINIAO_PREMIUM;CAINIAO_PREMIUM_TS_12720683:CAINIAO_PREMIUM;CAINIAO_PREMIUM_FPXSS:CAINIAO_PREMIUM;SGP_OMP_FPXZH:SGP_OMP;CAINIAO_PREMIUM_YANWENWZ:CAINIAO_PREMIUM;CAINIAO_PREMIUM_FPXFS:CAINIAO_PREMIUM;CAINIAO_PREMIUM_FPXZH:CAINIAO_PREMIUM;CAINIAO_PREMIUM_FPXFZ:CAINIAO_PREMIUM;CAINIAO_PREMIUM_TS_12720688:CAINIAO_PREMIUM;SGP_OMP_FPXQD:SGP_OMP;CAINIAO_STANDARD_FPXZS:CAINIAO_STANDARD;CAINIAO_STANDARD_YANWENSH:CAINIAO_STANDARD;CAINIAO_PREMIUM_FPXQZ:CAINIAO_PREMIUM;CAINIAO_STANDARD_YANWENNJ:CAINIAO_STANDARD;CAINIAO_STANDARD_FPXDG:CAINIAO_STANDARD;CAINIAO_STANDARD_FPXGZ:CAINIAO_STANDARD;CAINIAO_PREMIUM_FPXQD:CAINIAO_PREMIUM;CAINIAO_STANDARD_TS_12720682:CAINIAO_STANDARD;CAINIAO_STANDARD_YANWENHZ:CAINIAO_STANDARD;CAINIAO_STANDARD_FPXQZ:CAINIAO_STANDARD;CAINIAO_STANDARD_FPXSS:CAINIAO_STANDARD;CAINIAO_STANDARD_FPXZH:CAINIAO_STANDARD;CAINIAO_STANDARD_FPXST:CAINIAO_STANDARD;CAINIAO_STANDARD_TS_12350032:CAINIAO_STANDARD;CAINIAO_PREMIUM_TS_12349475:CAINIAO_PREMIUM;CAINIAO_STANDARD_FPXFZ:CAINIAO_STANDARD;CAINIAO_STANDARD_FPXQD:CAINIAO_STANDARD;CAINIAO_STANDARD_FPXXG:CAINIAO_STANDARD;CAINIAO_STANDARD_YANWENYW:CAINIAO_STANDARD;CAINIAO_STANDARD_YANWENWZ:CAINIAO_STANDARD;CAINIAO_STANDARD_YANWENBJ:CAINIAO_STANDARD;CAINIAO_STANDARD_FPXXM:CAINIAO_STANDARD;CAINIAO_ECONOMY_TS_1710314:CAINIAO_ECONOMY;CAINIAO_STANDARD_YANWENNB:CAINIAO_STANDARD;CAINIAO_ECONOMY_TS_11150235:CAINIAO_ECONOMY;CAINIAO_PREMIUM_FPXST:CAINIAO_PREMIUM;CAINIAO_STANDARD_FPXFS:CAINIAO_STANDARD;CAINIAO_ECONOMY_TS_11909017:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_11169435:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_1710060:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_11174377:CAINIAO_ECONOMY;CAINIAO_STANDARD_TS_12720687:CAINIAO_STANDARD;CAINIAO_ECONOMY_TS_1731655:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_1731792:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_11174375:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_11174376:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_11169426:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_11169427:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_11991629:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_1709670:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_1710057:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_11169436:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_12720681:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_11174378:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_11174379:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_12350033:CAINIAO_ECONOMY;CAINIAO_ECONOMY_TS_11991869:CAINIAO_ECONOMY;ITELLA_WLB_YANWENSH_SPSR_CN:SPSR_CN;CAINIAO_ECONOMY_TS_12720665:CAINIAO_ECONOMY;ITELLA_WLB_YANWENNJ_SPSR_CN:SPSR_CN;ITELLA_WLB_YANWENNB_SPSR_CN:SPSR_CN;SPSR_CN_TS_12712282:SPSR_CN;ITELLA_WLB_YANWENSZ_SPSR_CN:SPSR_CN;ITELLA_WLB_YANWENWZ_SPSR_CN:SPSR_CN;ITELLA_WLB_YANWENGZ_SPSR_CN:SPSR_CN;ITELLA_WLB_YANWENBJ_SPSR_CN:SPSR_CN;HRB_WLB_ZTOSH:CPAM_HRB;HRB_WLB_RUSTONNJ:CPAM_HRB;SPSR_CN_TS_12720682:SPSR_CN;SPSR_CN_TS_12350032:SPSR_CN;HRB_WLB_RUSTONYW:CPAM_HRB;HRB_WLB_RUSTONBJ:CPAM_HRB;HRB_WLB_RUSTONNB:CPAM_HRB;CPAM_HRB_TS_12712282:CPAM_HRB;ITELLA_WLB_YANWENHZ_SPSR_CN:SPSR_CN;HRB_WLB_RUSTONSZ:CPAM_HRB;CPAM_HRB_TS_12720682:CPAM_HRB;HRB_WLB_RUSTONHZ:CPAM_HRB;HRB_WLB_RUSTONWZ:CPAM_HRB;CNOLRUSTON_Tran_Store_1710314:RUSTON_ECONOMY;ITELLA_WLB_YANWENYW_SPSR_CN:SPSR_CN;HRB_WLB_ZTOGZ:CPAM_HRB;CNOLRUSTON_Tran_Store_1710060:RUSTON_ECONOMY;CNOLRUSTON_Tran_Store_1710057:RUSTON_ECONOMY;CNOLRUSTON_Tran_Store_1731792:RUSTON_ECONOMY;CNOLRUSTON_Tran_Store_1710316:RUSTON_ECONOMY;CNOLRUSTON_Tran_Store_1731655:RUSTON_ECONOMY;CNOLRUSTON_Tran_Store_1709670:RUSTON_ECONOMY;CNOLRUSTON_Tran_Store_11991869:RUSTON_ECONOMY;CPOSPP_CZ2:YANWEN_JYT;YANWENJYT_WLB_CPAMSH:YANWEN_JYT;YANWENJYT_WLB_CPAMDG:YANWEN_JYT;CPOSPP_TZ1:YANWEN_JYT;CPAM_HRB_TS_12350032:CPAM_HRB;CPOSPP_FS:YANWEN_JYT;CPOSPP_SM:YANWEN_JYT;CNOLRUSTON_Tran_Store_1710059:RUSTON_ECONOMY;CPOSPP_ZS:YANWEN_JYT;CPOSPP_LS:YANWEN_JYT;CPOSPP_BG:YANWEN_JYT;CPOSPP_NC1:YANWEN_JYT;YANWENJYT_WLB_CPAMNJ:YANWEN_JYT;CPOSPP_NP:YANWEN_JYT;CPOSPP_NT:YANWEN_JYT;CPOSPP_NC:YANWEN_JYT;YANWENJYT_WLB_CPAMBJ:YANWEN_JYT;CPOSPP_HF:YANWEN_JYT;YANWENJYT_WLB_CPAMXM:YANWEN_JYT;CPOSPP_NY:YANWEN_JYT;CPOSPP_DL:YANWEN_JYT;CPOSPP_WH1:YANWEN_JYT;CPOSPP_JX:YANWEN_JYT;CPOSPP_ND:YANWEN_JYT;CPOSPP_TJ:YANWEN_JYT;CPOSPP_NN:YANWEN_JYT;YANWENJYT_WLB_CPAMNB:YANWEN_JYT;CPOSPP_CD1:YANWEN_JYT;CPOSPP_SQ:YANWEN_JYT;CPOSPP_YY:YANWEN_JYT;CPOSPP_HZ1:YANWEN_JYT;CPOSPP_XN:YANWEN_JYT;YANWENJYT_WLB_CPAMGZ:YANWEN_JYT;CPOSPP_CD:YANWEN_JYT;CPOSPP_YZ:YANWEN_JYT;CPOSPP_ZZ2:YANWEN_JYT;CPOSPP_WX:YANWEN_JYT;CPOSPP_XZ:YANWEN_JYT;YANWENJYT_WLB_CPAMHZ:YANWEN_JYT;CPOSPP_JJ:YANWEN_JYT;YANWENJYT_WLB_CPAMYW:YANWEN_JYT;CPOSPP_WZ1:YANWEN_JYT;CPOSPP_JDZ:YANWEN_JYT;CPOSPP_JY:YANWEN_JYT;CPOSPP_SR:YANWEN_JYT;CPOSPP_ST:YANWEN_JYT;CPOSPP_WH:YANWEN_JYT;CPOSPP_KM:YANWEN_JYT;CPOSPP_SY:YANWEN_JYT;CPOSPP_TZ:YANWEN_JYT;CPOSPP_JM:YANWEN_JYT;CPOSPP_QZ:YANWEN_JYT;CPOSPP_CZ1:YANWEN_JYT;YANWENJYT_WLB_CPAMWZ:YANWEN_JYT;CPOSPP_LY1:YANWEN_JYT;CPOSPP_ZZ:YANWEN_JYT;CPOSPP_CZ:YANWEN_JYT;CPOSPP_WF:YANWEN_JYT;CPOSPP_YT:YANWEN_JYT;CPOSPP_JN:YANWEN_JYT;CPOSPP_HZ2:YANWEN_JYT;CPOSPP_YC:YANWEN_JYT;CPOSPP_ZP:YANWEN_JYT;CPOSPP_ZH:YANWEN_JYT;YANWENJYT_WLB_CPAMSUZ:YANWEN_JYT;CPOSPP_SJZ:YANWEN_JYT;YANWENJYT_WLB_CPAMFZ:YANWEN_JYT;CPOSPP_SX:YANWEN_JYT;CPOSPP_WH2:YANWEN_JYT;CPOSPP_BB:YANWEN_JYT;CPOSPP_HLD:YANWEN_JYT;CPOSPP_PT:YANWEN_JYT;CPOSPP_HY:YANWEN_JYT;CPOSPP_HS:YANWEN_JYT;CPOSPP_SS:YANWEN_JYT;CPOSPP_JH1:YANWEN_JYT;CPOSPP_GZ1:YANWEN_JYT;CPOSPP_GY:YANWEN_JYT;CPOSPP_XC:YANWEN_JYT;CPOSPP_XA:YANWEN_JYT;YANWENJYT_WLB_CPAMSZ:YANWEN_JYT;YANWENJYT_WLB_CPAMJH:YANWEN_JYT;CPOSPP_CC:YANWEN_JYT;CPOSPP_CS:YANWEN_JYT;CPOSPP_ZJ:YANWEN_JYT;CPOSPP_XT:YANWEN_JYT;CPAM_SM:CPAM;CPAM_WLB_CPAMSH:CPAM;CPOSPP_QD:YANWEN_JYT;CPAM_WLB_CPAMDG:CPAM;CPOSPP_LY:YANWEN_JYT;CPAM_TZ1:CPAM;CPAM_BG:CPAM;CPAM_LS:CPAM;CPAM_ZS:CPAM;CPAM_WLB_CPAMYW:CPAM;CPAM_FS:CPAM;CPAM_NC1:CPAM;CPAM_WLB_CPAMBJ:CPAM;CPAM_NP:CPAM;CPAM_WLB_CPAMNJ:CPAM;CPAM_NT:CPAM;CPAM_NY:CPAM;CPAM_XM:CPAM;CPAM_NC:CPAM;CPAM_XN:CPAM;CPAM_NN:CPAM;CPOSPP_ZZ1:YANWEN_JYT;CPAM_HF:CPAM;CPAM_DL:CPAM;CPAM_WH1:CPAM;CPAM_JX:CPAM;CPAM_SQ:CPAM;CPOSPP_CQ:YANWEN_JYT;CPAM_ND:CPAM;CPAM_YY:CPAM;CPAM_XZ:CPAM;CPAM_CZ2:CPAM;CPAM_CD1:CPAM;CPAM_WLB_CPAMNB:CPAM;CPAM_HZ1:CPAM;CPAM_KM:CPAM;CPAM_WLB_CPAMGZ:CPAM;CPAM_YZ:CPAM;CPAM_JJ:CPAM;CPAM_WX:CPAM;CPAM_JDZ:CPAM;CPAM_CD:CPAM;CPAM_ZZ2:CPAM;CPAM_WLB_CPAMHZ:CPAM;CPAM_WZ1:CPAM;CPAM_WH:CPAM;CPAM_SR:CPAM;CPAM_JM:CPAM;CPAM_JY:CPAM;CPAM_SY:CPAM;CPAM_QZ:CPAM;CPAM_CZ1:CPAM;CPAM_LY1:CPAM;CPAM_WLB_CPAMSZ:CPAM;CPAM_CZ:CPAM;CPAM_HZ2:CPAM;CPAM_TJ:CPAM;CPAM_ZZ:CPAM;CPAM_ZP:CPAM;CPAM_ST:CPAM;CPAM_WZ:CPAM;CPAM_YT:CPAM;CPAM_WF:CPAM;CPAM_SS:CPAM;CPAM_SJZ:CPAM;CPAM_ZH:CPAM;CPAM_SX:CPAM;CPAM_WH2:CPAM;CPAM_WLB_CPAMFZ:CPAM;CPAM_PT:CPAM;CPAM_SZ1:CPAM;CPAM_BB:CPAM;CPAM_JN:CPAM;CPAM_HY:CPAM;CPAM_HLD:CPAM;CPAM_HS:CPAM;CPAM_YC:CPAM;CPAM_XA:CPAM;CPAM_GZ1:CPAM;CPAM_XC:CPAM;CPAM_TZ:CPAM;CPAM_CQ:CPAM;CPAM_ZZ1:CPAM;CPAM_WLB_CPAMJH:CPAM;CPAM_ZJ:CPAM;CPAM_CC:CPAM;CPAM_JH1:CPAM;CPAM_QD:CPAM;WLB_YANWENSH_ROYAL_MAIL_PY:ROYAL_MAIL_PY;CPAM_CS:CPAM;WLB_YANWENYW_ROYAL_MAIL_PY:ROYAL_MAIL_PY;CPAM_LY:CPAM;WLB_YANWENGZ_ROYAL_MAIL_PY:ROYAL_MAIL_PY;CPAM_XT:CPAM;CPAM_GY:CPAM;WLB_YANWENNJ_ROYAL_MAIL_PY:ROYAL_MAIL_PY;WLB_YANWENNB_ROYAL_MAIL_PY:ROYAL_MAIL_PY;ROYAL_MAIL_PY_TS_12720681:ROYAL_MAIL_PY;ROYAL_MAIL_PY_TS_12712281:ROYAL_MAIL_PY;WLB_YANWENHZ_ROYAL_MAIL_PY:ROYAL_MAIL_PY;WLB_YANWENWZ_ROYAL_MAIL_PY:ROYAL_MAIL_PY;ROYAL_MAIL_PY_TS_12350033:ROYAL_MAIL_PY;SINOTRANS_AM_WLB_YW:SINOTRANS_AM;SINOTRANS_AM_WLB_NB:SINOTRANS_AM;SINOTRANS_AM_WLB_BJ:SINOTRANS_AM;SINOTRANS_AM_TS_12712282:SINOTRANS_AM;SINOTRANS_AM_WLB_GZ:SINOTRANS_AM;SINOTRANS_AM_WLB_NJ:SINOTRANS_AM;SINOTRANS_AM_TS_12720682:SINOTRANS_AM;WLB_YANWENBJ_ROYAL_MAIL_PY:ROYAL_MAIL_PY;SINOTRANS_AM_WLB_SZ:SINOTRANS_AM;SINOTRANS_AM_WLB_WZ:SINOTRANS_AM;SINOTRANS_PY_WLB_SH:SINOTRANS_PY;WLB_YANWENSZ_ROYAL_MAIL_PY:ROYAL_MAIL_PY;SINOTRANS_PY_WLB_YW:SINOTRANS_PY;SINOTRANS_PY_WLB_BJ:SINOTRANS_PY;SINOTRANS_PY_WLB_NJ:SINOTRANS_AM;SINOTRANS_AM_TS_12350032:SINOTRANS_AM;SINOTRANS_PY_WLB_GZ:SINOTRANS_PY;SINOTRANS_PY_TS_12712281:SINOTRANS_PY;SINOTRANS_PY_TS_12720681:SINOTRANS_PY;SINOTRANS_PY_TS_12350033:SINOTRANS_PY;SINOTRANS_PY_WLB_WZ:SINOTRANS_PY;SINOTRANS_PY_WLB_HZ:SINOTRANS_PY;YANWEN_WLB_YANWENNB:YANWEN_AM;YANWEN_WLB_YANWENSH:YANWEN_AM;YANWEN_WLB_YANWENYW:YANWEN_AM;YANWEN_WLB_YANWENNJ:YANWEN_AM;YANWEN_WLB_YANWENGZ:YANWEN_AM;YANWEN_AM_TS_12712282:YANWEN_AM;YANWEN_WLB_YANWENBJ:YANWEN_AM;YANWEN_AM_TS_12720682:YANWEN_AM;SINOTRANS_PY_WLB_NB:SINOTRANS_PY;YANWEN_WLB_YANWENWZ:YANWEN_AM;SINOTRANS_PY_WLB_SZ:SINOTRANS_PY;YANWEN_WLB_YANWENSZ:YANWEN_AM;YANWEN_AM_TS_12350032:YANWEN_AM;YANWEN_ECONOMY_YANWENSH:YANWEN_ECONOMY;YANWEN_ECONOMY_YANWENNB:YANWEN_ECONOMY;YANWEN_ECONOMY_YANWENGZ:YANWEN_ECONOMY;YANWEN_ECONOMY_YANWENNJ:YANWEN_ECONOMY;SINOTRANS_AM_WLB_HZ:SINOTRANS_AM;YANWEN_ECONOMY_TS_12712281:YANWEN_ECONOMY;YANWEN_ECONOMY_YANWENBJ:YANWEN_ECONOMY;YANWEN_ECONOMY_YANWENHZ:YANWEN_ECONOMY;YANWEN_ECONOMY_YANWENSZ:YANWEN_ECONOMY;YANWEN_ECONOMY_YANWENWZ:YANWEN_ECONOMY;SINOTRANS_AM_WLB_SH:SINOTRANS_AM;YANWEN_ECONOMY_TS_12350033:YANWEN_ECONOMY;OMNIVA_ECONOMY_YANWENYW:OMNIVA_ECONOMY;OMNIVA_ECONOMY_YANWENSH:OMNIVA_ECONOMY;OMNIVA_ECONOMY_YANWENNJ:OMNIVA_ECONOMY;OMNIVA_ECONOMY_YANWENHZ:OMNIVA_ECONOMY;OMNIVA_ECONOMY_TS_12720681:OMNIVA_ECONOMY;YANWEN_ECONOMY_YANWENYW:YANWEN_ECONOMY;OMNIVA_ECONOMY_YANWENBJ:OMNIVA_ECONOMY;OMNIVA_ECONOMY_TS_12350033:OMNIVA_ECONOMY;OMNIVA_ECONOMY_YANWENSZ:OMNIVA_ECONOMY;OMNIVA_ECONOMY_YANWENNB:OMNIVA_ECONOMY;ITELLA_WLB_YANWENSH_ITELLA:ITELLA;OMNIVA_ECONOMY_YANWENWZ:OMNIVA_ECONOMY;ITELLA_TS_12720682:ITELLA;ITELLA_TS_12712282:ITELLA;ITELLA_TS_12350032:ITELLA;ITELLA_WLB_YANWENYW_ITELLA:ITELLA;ITELLA_WLB_YANWENNJ_ITELLA:ITELLA;ITELLA_WLB_YANWENGZ_ITELLA:ITELLA;ITELLA_WLB_YANWENBJ_ITELLA:ITELLA;ITELLA_WLB_YANWENHZ_ITELLA:ITELLA;ITELLA_WLB_YANWENSZ_ITELLA:ITELLA;ITELLA_WLB_YANWENNB_ITELLA:ITELLA;ITELLA_WLB_YANWENWZ_ITELLA:ITELLA;ITELLA_WLB_YANWENSH_ITELLA_PY:ITELLA_PY;ITELLA_WLB_YANWENBJ_ITELLA_PY:ITELLA_PY;ITELLA_WLB_YANWENYW_ITELLA_PY:ITELLA_PY;ITELLA_PY_TS_12712281:ITELLA_PY;ITELLA_WLB_YANWENGZ_ITELLA_PY:ITELLA_PY;YANWEN_WLB_YANWENHZ:YANWEN_AM;ITELLA_PY_TS_12720681:ITELLA_PY;ITELLA_WLB_YANWENHZ_ITELLA_PY:ITELLA_PY;ITELLA_WLB_YANWENNJ_ITELLA_PY:ITELLA;ITELLA_WLB_YANWENNB_ITELLA_PY:ITELLA_PY;ITELLA_WLB_YANWENSZ_ITELLA_PY:ITELLA_PY;SF_EPARCEL_OM_NATIONWIDE:SF_EPARCEL_OM;ITELLA_PY_TS_12350033:ITELLA_PY;ITELLA_WLB_YANWENWZ_ITELLA_PY:ITELLA_PY;OMNIVA_ECONOMY_YANWENGZ:OMNIVA_ECONOMY;SUNYOU_ECONOMY_YANWENNB:SUNYOU_ECONOMY;SUNYOU_ECONOMY_YANWENBJ:SUNYOU_ECONOMY;SUNYOU_ECONOMY_YANWENNJ:SUNYOU_ECONOMY;SUNYOU_ECONOMY_YANWENHZ:SUNYOU_ECONOMY;SUNYOU_ECONOMY_YANWENYW:SUNYOU_ECONOMY;SUNYOU_ECONOMY_YANWENSH:SUNYOU_ECONOMY;SUNYOU_ECONOMY_TS_12712281:SUNYOU_ECONOMY;SUNYOU_ECONOMY_TS_12720681:SUNYOU_ECONOMY;SUNYOU_ECONOMY_YANWENWZ:SUNYOU_ECONOMY;SUNYOU_ECONOMY_YANWENSZ:SUNYOU_ECONOMY;SUNYOU_ECONOMY_YANWENGZ:SUNYOU_ECONOMY;OMNIVA_ECONOMY_TS_12712281:OMNIVA_ECONOMY;SUNYOU_ECONOMY_TS_12350033:SUNYOU_ECONOMY;YANWEN_ECONOMY_TS_12720681:YANWEN_ECONOMY;';
		
		$params = explode(';',rtrim($tmpServerCode,';'));
		Helper_Array::removeEmpty($params);
		
		$aliexpressServices = array();
		foreach($params as $v){
			$value = explode(':',$v);
			if(count($value)<2)return false;
			$aliexpressServices[$value[0]] = $value[1];
		}
		
		return $aliexpressServices;
	}
	
	//?????????????????????????????????
	public static function getCrTemplateListV2(){
		$cTemplateList = array();
		
		$tmp_cTemplateList = CrTemplate::find()->select(['template_id','template_name'])->where(['template_type'=>'?????????','template_version'=>1])->asArray()->all();
		
		if(!empty($tmp_cTemplateList)){
			$cTemplateList = Helper_Array::toHashmap($tmp_cTemplateList, 'template_id', 'template_name');
		}
		
		return $cTemplateList;
	}
	
	//?????????????????????????????????
	public static function getOrderCarrierData($order_arr, $carrierAddressAndPhoneParmas){
		//??????????????????
		$query = \eagle\modules\order\models\OdOrder::find();
		
		$query->andWhere(['order_id'=>$order_arr]);
		
		$query->with(['items'=>function ($query_item){
			$query_item->andWhere(['not in',"ifnull(sku,'')",CdiscountOrderInterface::getNonDeliverySku()]);
			$query_item->andWhere(['and',"ifnull(delivery_status,'') != 'ban'"]);
		},]);
		
		$data = $query->all();
		
		
		
		//???????????????????????????
		$order_data = array();
		
		if(!empty($data)){
			foreach ($data as $order){
				//????????????????????????
				$carrierAddressAndPhoneInfo = CarrierAPIHelper::getCarrierAddressAndPhoneInfo($order, $carrierAddressAndPhoneParmas);
				
				$order_data[$order['order_id']] = array();
				
				$order_data[$order['order_id']]['list'] = array(
						'order_id'=>$order['order_id'],			//??????????????????
						'consignee'=>$order['consignee'],		//?????????
						'address_line1'=>isset($carrierAddressAndPhoneInfo['address_line1']) ? $carrierAddressAndPhoneInfo['address_line1'] : '',
						'address_line2'=>isset($carrierAddressAndPhoneInfo['address_line2']) ? $carrierAddressAndPhoneInfo['address_line2'] : '',
						'address_line3'=>isset($carrierAddressAndPhoneInfo['address_line3']) ? $carrierAddressAndPhoneInfo['address_line3'] : '',
						'consignee_city'=>$order['consignee_city'],
						'consignee_province'=>($order['consignee_province'] == '') ? $order['consignee_city'] : $order['consignee_province'],
						'consignee_postal_code'=>$order['consignee_postal_code'],
						'consignee_country_code'=>$order['consignee_country_code'],
						'phone1'=>isset($carrierAddressAndPhoneInfo['phone1']) ? $carrierAddressAndPhoneInfo['phone1'] : '',
						'phone2'=>isset($carrierAddressAndPhoneInfo['phone2']) ? $carrierAddressAndPhoneInfo['phone2'] : '',
						'consignee_email'=>$order['consignee_email'],
						'desc'=>$order['desc'],
				);
				
				//????????????????????????
				$order_declared = CarrierDeclaredHelper::getOrderDeclaredInfoByOrder($order);
				
				$order_data[$order['order_id']]['items'] = array();
				
				foreach($order->items as $k=>$v){
					$order_data[$order['order_id']]['items'][] = array(
							'sku'=>$v['sku'],
							'root_sku'=>$v['root_sku'],
							'quantity'=>$v['quantity'],
							'declaration_nameCN'=>$order_declared[$v['order_item_id']]['declaration']['nameCN'],
							'declaration_nameEN'=>$order_declared[$v['order_item_id']]['declaration']['nameEN'],
							'declaration_weight'=>$order_declared[$v['order_item_id']]['declaration']['weight'],
							'declaration_price'=>$order_declared[$v['order_item_id']]['declaration']['price'],
					);
				}				
				
			}
		}
		
		return $order_data;
	}
	
	/**
	 * ?????????????????????????????????????????????  ?????????????????????id???????????????
	 *
	 * @param	$order_id				??????????????????
	 * @param	$warehouse_id			??????ID
	 * @param	$shipping_method_code	????????????ID
	 * @return
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2017/06/08				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getCarrierOrderTrackingNoHtml($order_id, $warehouse_id, $shipping_method_code){
		$result = array('error'=>0, 'data'=>array(), 'msg'=>'');
		
		$query_condition = array();
		$query_condition['keys'] = 'order_id';
		$query_condition['searchval'] = $order_id;
		
		$rt = \eagle\modules\order\apihelpers\OrderApiHelper::getOrderListByCondition($query_condition, 0);
		
		if(!isset($rt['data'][0])){
			$result['error'] = 1;
			$result['msg'] = '?????????????????????????????????';
			return $result;
		}
		
		$order = $rt['data'][0];
		
		if($order['order_status'] != 300){
			$result['error'] = 1;
			$result['msg'] = '?????????????????????????????????';
			return $result;
		}
		
		if($order['default_shipping_method_code'] != $shipping_method_code){
			$serviceid = SysShippingService::findOne($shipping_method_code);
			
			$orderNewAttr = array(
					'order_status'=>\eagle\modules\order\models\OdOrder::STATUS_WAITSEND,
					'carrier_step'=>\eagle\modules\order\models\OdOrder::CARRIER_CANCELED,
					'tracking_number'=>'',
					'default_shipping_method_code'=>$shipping_method_code,
					'default_carrier_code'=>$serviceid->carrier_code,
					'default_warehouse_id'=>$warehouse_id
			);
			
			$orderUpdateResult = \eagle\modules\order\helpers\OrderUpdateHelper::updateOrder($order, $orderNewAttr);
			
			if($orderUpdateResult['ack'] == false){
				$result['error'] = 1;
				$result['msg'] = $orderUpdateResult['message'];
				return $result;
			}
		}
		
		//??????API??????
		if(substr($order->default_carrier_code, 0, 3) == 'lb_'){
			//??????????????????
			if(in_array($order->carrier_step, array(0, 4))){
				$tmpSearchShippingid = array();
				$carrierAccountInfo = array();
				
				//?????????????????????????????????
				$order_products = array();
				
				//????????????????????????????????????
				$order_items_info = array();
				
				//??????API??????code
				$default_carrier_codes = array();
				$sys_carrier_params = array();
				
				$tmpSearchShippingid[$order->default_shipping_method_code] = $order->default_shipping_method_code;
				$default_carrier_codes[$order->default_carrier_code] = $order->default_carrier_code;
				
				foreach($order->items as $item){
					$tmp_platform_itme_id = \eagle\modules\order\helpers\OrderGetDataHelper::getOrderItemSouceItemID($order->order_source , $item);
					$order_items_info[] = array('platform_type'=>$order->order_source, 'order_status'=>$order->order_status, 'xlb_item'=>$item->order_item_id,'sku'=>$item->sku, 'root_sku'=>$item->root_sku, 'itemID'=>$tmp_platform_itme_id, 'declaration'=>json_decode($item->declaration,true));
				}
				CarrierOpenHelper::getCustomsDeclarationSumInfo($order, $order_products);
				
				//????????????????????????
				$result_item_declared_info = CarrierDeclaredHelper::getOrderDeclaredInfoBatch($order_items_info);
				
				if(count($tmpSearchShippingid) > 0){
					$carrierAccountInfo = CarrierOpenHelper::getCarrierAccountInfoByShippingId(array('shippings'=>$tmpSearchShippingid));
				}
				
				if(count($default_carrier_codes) > 0){
					$sys_carrier_params = CarrierOpenHelper::getSysCarrierParams(array('carrier_codes'=>$default_carrier_codes));
				}
				
				$warehouseNameMap = InventoryApiHelper::getWarehouseIdNameMap();
				
				$result['data']['html'] = CarrierOpenHelper::getOrdersCarrierInfoView($order, $order_products, $sys_carrier_params, $carrierAccountInfo, $warehouseNameMap, $result_item_declared_info);
			}else if($order['carrier_step'] == 1){
				//??????????????????????????????????????????
				$resultOrderDeliver = self::orderCarrierDeliveries($order);
				
				if($resultOrderDeliver['error'] == 1){
					$order->carrier_error = $resultOrderDeliver['msg'];
					$result['error'] = 1;
					$result['msg'] = $resultOrderDeliver['msg'];
					return $result;
				}else{
					$order->carrier_error = '';
				}
				
				$order->save();
				
				$result['error'] = 0;
				$result['msg'] = '????????????';
				return $result;
			}else{
				//?????????????????????,????????????????????????????????????????????????????????????????????????????????????
				$resultOrderGetTrackingNo = self::orderCarrierGetTrackingNo($order);
				
				if($resultOrderGetTrackingNo['error'] == 1){
					$order->carrier_error = $resultOrderGetTrackingNo['msg'];
					$result['error'] = 1;
					$result['msg'] = $resultOrderGetTrackingNo['msg'];
					return $result;
				}else{
					$order->carrier_error = '';
				}
				
				$order->save();
			}
			
			return $result;
		}else{
			$tmp_carrier_custom = SysCarrierCustom::find()->where(['carrier_code'=>$order->default_carrier_code])->one();
			
			if($tmp_carrier_custom == null){
				$result['error'] = 1;
				$result['msg'] = '??????????????????????????????,????????????e2';
				return $result;
			}
			
			//???0???????????????????????????,1???????????????excel??????????????????
			if($tmp_carrier_custom->carrier_type == 0){
				$shipping_service_id = $order->default_shipping_method_code;
				$response = CarrierOpenHelper::getCustomUnUseTrackingnumber($shipping_service_id);
				$response = $response['response'];
				if($response['code']==1){//???????????????
					$result['error'] = 1;
					$result['msg'] = $response['msg'];
					return $result;
				}else{
					$tracking_number = $response['data'];
					$res = CarrierOpenHelper::saveCustomUnUseTrackingnumber($shipping_service_id, $tracking_number, $order->order_id);
					$res = $res['response'];
					if($res['code']==1){
						$result['error'] = 1;
						$result['msg'] = $res['msg'];
						return $result;
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
						$result_save=\eagle\modules\order\helpers\OrderHelper::saveTrackingNumber($order_id, $logisticInfoList);
						if($result_save){
							$order->carrier_error = '';
							$order->carrier_step = \eagle\modules\order\models\OdOrder::CARRIER_WAITING_DELIVERY;
							$order->customer_number =$customerNumber;
							$order->save();
							OperationLogHelper::log('delivery', $order->order_id,'???????????????','????????????'.$tracking_number,\Yii::$app->user->identity->getFullName());
							
							$result['tracking_number'] = $tracking_number;
							$result['error'] = 0;
							$result['msg'] = '?????????????????????';
							return $result;
						}else{
							$result['error'] = 1;
							$result['msg'] = '?????????????????????';
							return $result;
						}
					}
				}
			}else{
				$result['error'] = 1;
				$result['msg'] = '??????????????????????????????';
				return $result;
			}
		}
	}
	
	//??????????????????
	public static function orderCarrierDeliveries($order){
		$class_name = '';
		//?????????
		$carrier = SysCarrier::findOne($order['default_carrier_code']);
		if($carrier['carrier_type']){
			$class_name = '\common\api\overseaWarehouseAPI\\'.$carrier['api_class'];
		}else{
			$class_name = '\common\api\carrierAPI\\'.$carrier['api_class'];
		}
			
		if($carrier->api_class == 'LB_RTBCOMPANYCarrierAPI'){
			//???????????????????????????
			$interface = new $class_name($carrier['carrier_code']);
		}else{
			$interface = new $class_name;
		}
			
		$result = $interface->doDispatch(['order'=>$order]);
		
		return $result;
	}
	
	//???????????????????????????
	public static function orderCarrierGetTrackingNo($order){
		$class_name = '';
		//?????????
		$carrier = SysCarrier::findOne($order['default_carrier_code']);
		if($carrier->carrier_type){
			$class_name = '\common\api\overseaWarehouseAPI\\'.$carrier->api_class;
		}else{
			$class_name = '\common\api\carrierAPI\\'.$carrier->api_class;
		}
		 
		if($carrier->api_class == 'LB_RTBCOMPANYCarrierAPI'){
			//???????????????????????????
			$interface = new $class_name($carrier->carrier_code);
		}else{
			$interface = new $class_name;
		}
		 
		$result = $interface->getTrackingNO(['order'=>$order]);
		
		return $result;
	}
	
	/**
	 * ???????????????????????? ?????????????????????????????????
	 *
	 * @param	$platform	??????ebay/amazon/wish
	 * @return	$result	Array
	 * type		????????????????????????  text/dropdownlist	???text ???input??????dropdownlist???????????????
	 * shippingServices	??????????????????????????? 
	 * web_url_tyep		????????????????????????    0???????????????????????????????????????????????????1??????????????????????????????????????????????????????????????????2?????????????????????????????????????????????
	 * delivery_msg		?????????????????????????????????
	 * 
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2017/06/08				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function getShippingCodeByPlatform($platform){
		$result = array('type'=>'', 'shippingServices'=>array(), 'web_url_tyep'=>0, 'delivery_msg'=>false);
		
		list($rt , $type) = \eagle\modules\delivery\apihelpers\DeliveryApiHelper::getShippingCodeByPlatform($platform);
		
		$result['type'] = $type;
		
		switch ($platform){
			case 'ebay':
				$result['web_url_tyep'] = 1;
				$result['delivery_msg'] = true;
				break;
			case 'aliexpress':
				$result['web_url_tyep'] = 2;
				$result['delivery_msg'] = true;
				break;
			case 'wish':
				$result['delivery_msg'] = true;
				break;
			case 'amazon':
				$result['web_url_tyep'] = 1;
				break;
			case 'dhgate':
				$result['web_url_tyep'] = 1;
				$result['delivery_msg'] = true;
				break;
			case 'lazada':
			case 'linio':
			case 'jumia':
				$result['web_url_tyep'] = 1;
				break;
			case 'priceminister':
				break;
			case 'cdiscount':
				break;
			case 'rumall':
			    break;
			case 'bonanza':
				break;
			case 'newegg':
				break;
			case 'shopee':
			    break;
			default:
				break;
		}
		
		$shippingServiceArr = array();
		
		if((count($rt) > 0) && (!empty($rt))){
			foreach ($rt as $tr_service_key => $tr_service_val){
				$shippingServiceArr[$tr_service_key] = array('service_val'=>$tr_service_val);
				
				if($result['web_url_tyep'] == 2){
					$shippingServiceArr[$tr_service_key]['is_web_url'] = true;
				}
			}
		}
		
		$result['shippingServices'] = $shippingServiceArr;
		
		return $result;
	}
	
	/**
	 * ???????????????????????? ??????????????????????????????
	 *
	 * @param	$order_id	??????????????????
	 * @param	$params		array
	 * 				tracking_number			?????????
	 * 				tracking_link			????????????			????????????,???????????????????????????
	 * 				shipping_method_code	??????????????????
	 *  			shipping_method_name	?????????????????? 		??????CD????????????
	 *   			description				????????????			????????????,???????????????????????????
	 * @return	$result	Array
	 * type		????????????????????????  text/dropdownlist	???text ???input??????dropdownlist???????????????
	 * shippingServices	???????????????????????????
	 * web_url_tyep		????????????????????????    0???????????????????????????????????????????????????1??????????????????????????????????????????????????????????????????2?????????????????????????????????????????????
	 * delivery_msg		?????????????????????????????????
	 *
	 * +-------------------------------------------------------------------------------------------
	 * log			name	date					note
	 * @author		hqw		2017/06/08				?????????
	 * +-------------------------------------------------------------------------------------------
	 */
	public static function saveTrackingNoManual($order_id, $params){
		$result = array('error'=>0, 'data'=>array(), 'msg'=>'');
// 		print_r($params);
// 		exit;
		$query_condition = array();
		$query_condition['keys'] = 'order_id';
		$query_condition['searchval'] = $order_id;
		
		$rt = \eagle\modules\order\apihelpers\OrderApiHelper::getOrderListByCondition($query_condition, 0);
		
		if(!isset($rt['data'][0])){
			$result['error'] = 1;
			$result['msg'] = '?????????????????????????????????';
			return $result;
		}
		
		$order = $rt['data'][0];
		
		$is_edit_trcking = false;
		
		//?????????????????????
		if($params['tracking_number'] != $order->tracking_number){
			$customerNumber = \eagle\modules\carrier\helpers\CarrierAPIHelper::getCustomerNum2($order);
			$is_edit_trcking = true;
		}else{
			$customerNumber = $order->customer_number;
		}
		
		if($params['default_shipping_method_code'] != 'manual_tracking_no'){
			$service = \eagle\modules\carrier\models\SysShippingService::find()->where(['id'=>$params['default_shipping_method_code'],'is_used'=>1,'is_del'=>0])->one();
			
			if($service == null){
				$result['error'] = 1;
				$result['msg'] = '????????????????????????????????????';
				return $result;
			}
			
			$params['tracking_link'] = $service->web;
			$params['shipping_method_code'] = isset($service->service_code[$order->order_source]) ? $service->service_code[$order->order_source] : '';
		}
		
		$logisticInfoList=[
			'0'=>[
				'order_source'=>$order->order_source,
				'selleruserid'=>$order->selleruserid,
				'tracking_number'=>$params['tracking_number'],
				'tracking_link'=>isset($params['tracking_link']) ? $params['tracking_link'] : '',
				'shipping_method_code'=>$params['shipping_method_code'],
				'shipping_method_name'=>empty($params['shipping_method_name']) ? $params['shipping_method_code'] : $params['shipping_method_name'],//?????????????????????
				'customer_number'=>$customerNumber,
// 				'shipping_service_id'=>$order->default_shipping_method_code,
				'order_source_order_id'=>$order->order_source_order_id,
				'description'=>isset($params['description']) ? $params['description'] : '',
				'addtype'=>'????????????',
			]
		];
		
		//?????????????????????????????????
		if(!\eagle\modules\order\helpers\OrderHelper::saveTrackingNumber($order->order_id, $logisticInfoList,0,false)){
			$result['error'] = 1;
			$result['msg'] = '?????????????????????';
			return $result;
		}else{
			//????????????????????????????????????????????????????????????????????????
			$orderNewAttr = array(
					'carrier_error'=>'',
					'carrier_step'=>\eagle\modules\order\models\OdOrder::CARRIER_WAITING_PRINT,
					'customer_number'=>$customerNumber,
					'default_shipping_method_code'=>$params['default_shipping_method_code'],
			);
			$orderUpdateResult = \eagle\modules\order\helpers\OrderUpdateHelper::updateOrder($order, $orderNewAttr);
			
			if($is_edit_trcking == true)
				OperationLogHelper::log('delivery', $order->order_id,'????????????','????????????'.$params['tracking_number'],\Yii::$app->user->identity->getFullName());
		}
		
		$result['msg'] = '????????????';
		return $result;
	}

	//????????????????????????????????????modal
	public static function getCainiaoPrintModalHtml(){
		$tmp_html = '<div class="modal" id="cainiaoPrinterModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false" style="display: none;">
			<div class="modal-dialog">
				<div class="modal-content bs-example bs-example-tabs">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">??</span><span class="sr-only">Close</span></button>
						<h4 class="modal-title">??????</h4>
					</div>
					<div class="modal-body tab-content" style="padding:20px 30px;">
							??????????????????<select id="cainiaoPrinterSelect" class="form-control w300 inline-block"></select>
					</div>
					<div class="modal-footer" style="clear:both;">
						<button type="button" class="btn btn-primary" id="cainiaoPrePrintBtn">????????????</button>&nbsp;&nbsp;
						<button type="button" class="btn btn-primary" id="cainiaoPrintBtn">????????????</button>&nbsp;&nbsp;
						<button type="button" class="btn btn-default" data-dismiss="modal">??????</button>
					</div>
				</div>
			</div>
		</div>';
		
		return $tmp_html;
	}
	
	/**
	 +----------------------------------------------------------
	 * ????????????url
	 +----------------------------------------------------------
	 * log			name		date			note
	 * @author		lrq		  2017/11/07		?????????
	 +----------------------------------------------------------
	 **/
	public static function GetPrintUrl($param){
		if( empty($param['order_id'])){
			return ['success' => false, 'msg' => '???????????????'];
		}
		$order_id = ltrim($param['order_id'], '0');
		//??????5?????????????????????
		$uid = \Yii::$app->user->id;
		$key = $uid.'_'.$order_id;
		for($n = 0; $n < 10; $n++){
			sleep(2);
			$warn_record = RedisHelper::RedisGet('CsPrintPdfUrl', $key);
			if(!empty($warn_record)){
				$redis_val = json_decode($warn_record,true);
				//????????????
				RedisHelper::RedisDel('CsPrintPdfUrl', $key);
				
				if(!empty($redis_val['url']) && !empty($redis_val['carrierName'])){
					//??????20????????????
					if($redis_val['time'] < time() - 20){
						return ['success' => false, 'msg' => '?????????????????????'];
					}
					return ['success' => true, 'url' => $redis_val['url'], 'carrierName' => $redis_val['carrierName'], 'OrderId' => $order_id];
				}
			}
		}
		
		return ['success' => false, 'msg' => '?????????????????????'];
	}
	
	//???????????????????????????
	public static function getSysCarrierAddiInfo($carrier_code){
		$carrier = SysCarrier::find()->select('carrier_code, addi_infos')->where(['carrier_code'=>$carrier_code])->asArray()->one();
		
		if(empty($carrier)){
			return false;
		}
		
		return $carrier;
	}
	
	//???????????????????????????
	public static function setSysCarrierAddiInfo($carrier_code, $addi_infos = array()){
		$carrier = SysCarrier::find()->where(['carrier_code'=>$carrier_code])->one();
		
		if(empty($carrier)){
			return false;
		}

		$carrier->addi_infos = json_encode($addi_infos);
		
		return $carrier->save(false);
	}
	
}
