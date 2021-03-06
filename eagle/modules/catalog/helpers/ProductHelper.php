<?php
/**
 * @link http://www.witsion.com/
 * @copyright Copyright (c) 2014 Yii Software LLC
 * @license http://www.witsion.com/
 */
namespace eagle\modules\catalog\helpers;
use yii;
use eagle\modules\util\helpers\GetControlData;
use yii\base\Exception;
use yii\data\Pagination;

use eagle\modules\catalog\models\Product;
use eagle\modules\catalog\models\ProductAliases;
use eagle\modules\catalog\models\ProductClassification;
use eagle\modules\catalog\models\Tag;
use eagle\modules\catalog\models\Attributes;
use eagle\modules\catalog\models\ProductTags;
use eagle\modules\catalog\models\ProductSuppliers;
use eagle\modules\catalog\models\ProductConfigRelationship;
use eagle\modules\catalog\models\ProductField;
use eagle\modules\catalog\models\ProductFieldValue;
use eagle\modules\catalog\helpers\ProductFieldHelper;
use eagle\modules\catalog\models\ProductBundleRelationship;
use eagle\modules\catalog\models\Brand;
use eagle\modules\purchase\models\Supplier;
use eagle\modules\inventory\models\ProductStock;
use eagle\modules\inventory\helpers\StockTakeHelper;
use eagle\modules\purchase\helpers\SupplierHelper;

use eagle\modules\listing\models\AmazonItem;
use eagle\modules\listing\models\EbayItem;
use eagle\modules\listing\models\WishFanbenVariance;
use eagle\modules\listing\models\WishFanben;
use eagle\models\SaasWishUser;
use eagle\modules\util\helpers\TranslateHelper;
use eagle\modules\util\helpers\SQLHelper;
use eagle\modules\util\helpers\SysLogHelper;
use eagle\modules\catalog\models\Photo;
use yii\grid\DataColumn;
use eagle\modules\inventory\helpers\WarehouseHelper;
use eagle\modules\util\helpers\ExcelHelper;
use yii\db\Query;
use eagle\modules\inventory\models\Warehouse;
use eagle\modules\util\helpers\ConfigHelper;
use Qiniu\json_decode;
use eagle\modules\permission\helpers\UserHelper;
use eagle\modules\util\helpers\RedisHelper;

/**
 * BaseHelper is the base class of module BaseHelpers.
 *
 */
class ProductHelper {
	
	protected static $PRODUCT_TYPE = array(
			"S" => "??????",
			"C" => "??????",
			"B" => "??????",
			"L" => "??????(???????????????)"
	);
	
	protected static $EDIT_PRODUCT_LOG_COL = array(
			'name' => '????????????',
			'prod_name_ch' => '???????????????',
			'prod_name_en' => '???????????????',
			'declaration_ch' => '???????????????',
			'declaration_en' => '???????????????',
			'declaration_value' => '????????????',
			'declaration_code' => '????????????',
			'prod_weight' => '??????',
	);
	
	/**
	 +----------------------------------------------------------
	 * ????????????????????????
	 +----------------------------------------------------------
	 * @access static
	 +----------------------------------------------------------
	 * @param id			????????????Key
	 +----------------------------------------------------------
	 * @return				??????????????????
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		ouss	2014/02/27				?????????
	 +----------------------------------------------------------
	**/
	public static function getProductType($id='')
	{
		if (is_numeric($id) or $id=="")
			return GetControlData::getValueFromArray(self::$PRODUCT_TYPE, $id);
		else
			return GetControlData::getValueFromArray(array_flip(self::$PRODUCT_TYPE), $id);
	}

	protected static $PRODUCT_STATUS = array(
			"OS" => "??????",
			"RN" => "??????",
			"DR" => "??????",
			"AC" => "??????",
			"RS" => "????????????",
	);
	
	/**
	 +----------------------------------------------------------
	 * ????????????????????????
	 +----------------------------------------------------------
	 * @access static
	 +----------------------------------------------------------
	 * @param id			????????????Key
	 +----------------------------------------------------------
	 * @return				??????????????????
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		ouss	2014/02/27				?????????
	 +----------------------------------------------------------
	**/
	public static function getProductStatus($id='')
	{
		if (is_numeric($id) or $id=="")
			return GetControlData::getValueFromArray(self::$PRODUCT_STATUS, $id);
		else
			return GetControlData::getValueFromArray(array_flip(self::$PRODUCT_STATUS), $id);
	}

	/**
	 +----------------------------------------------------------
	 * ????????????????????????
	 +----------------------------------------------------------
	 * @access static
	 +----------------------------------------------------------
	 * @param $condition	???????????? [['like'=>value] , ['in'=>value]], ['or'=>value]]
	 * @param $sort			????????????
	 * @param $order		???????????? asc/desc
	 * @param $defaultPageSize	            ??????????????????
	 * @param $isOnlyPro			????????????????????????????????????
	 * @param $isShowL			???????????????????????????
	 * @param $page			??????
	 +----------------------------------------------------------
	 * @return				??????????????????
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lkh		2015/03/09				?????????
	 +----------------------------------------------------------
	 **/
	static public function getProductlist($condition , $sort , $order,$defaultPageSize=20, $isOnlyPro = false, $isShowL = false, $page = null){
		$query = Product::find();
		
		if (! empty($condition)){
			foreach($condition as $tmp_condition){
				if (isset($tmp_condition['or']))
					$query->orWhere($tmp_condition['or']);
				
				if (isset($tmp_condition['and']))
					$query->andWhere($tmp_condition['and']);
				
				if (isset($tmp_condition['orlikelist'])){
					//?????????????????????????????????;??????
					$condition_str = str_replace('???', ';', $tmp_condition['orlikelist']);
					$condition_list = explode(';', $condition_str);
					if(count($condition_list)>1){
							$param1='';
							foreach ($condition_list as $c){
								if(trim($c)!==''){
									if($param1=='')
										$param1.=trim($c);
									else 
										$param1.='\',\''.trim($c);	
								}
							}
							if($param1!=='')
								$param1='\''.$param1.'\'';
							$query->andWhere(" sku in ($param1) ");
					}else{
						$query->andWhere("sku like '%".$tmp_condition['orlikelist']."%'   or name like '%".$tmp_condition['orlikelist']."%'  ");
    						/*or prod_name_ch like '%".$tmp_condition['orlikelist']."%'   or prod_name_en like '%".$tmp_condition['orlikelist']."%'   
    						or declaration_ch like '%".$tmp_condition['orlikelist']."%'   or declaration_en like '%".$tmp_condition['orlikelist']."%' ");*/
					}
				}
			}
			
			if(!$isShowL){
				//??????????????????????????????
				$relationship = ProductConfigRelationship::find()->select('cfsku')->where(['in', 'assku', Product::find()->select('sku')->where($query->where)->andwhere("type='L'")])->asArray()->all();
				if(!empty($relationship)){
					$cfsku = array();
					foreach($relationship as $v){
						$cfsku[] = $v['cfsku'];
					}
						
					if(!empty($cfsku)){
						$query->orWhere(['sku' => $cfsku]);
					}
				}
			}
		}
		
		//????????????????????????SKU
		if(!$isShowL){
		    $query->andwhere("type!='L'");
		}
		
		if($isOnlyPro){
		    $Size = 3000;
		    $count = $query->count();
		    //?????????????????????????????????
		    if($count < $Size){
		        $list = $query->orderBy('pd_product.'.$sort.' '.$order)
		        ->asArray()
		        ->all();
		    }
		    else{
		    	$start_page = 0;
		        $list = array();
		        $batch = $count / $Size + 1;
		        
		        if(\Yii::$app->subdb->getCurrentPuid() == 13672){
		        	$start_page = 16;
		        	//$batch = 16;
		        }
		        
		        for($n = $start_page; $n < $batch; $n++){
		            $pagination = new Pagination([
		                    'page' => $n,
		            		'pageSize' => $Size,
		            		'totalCount'=> $count,
		            		'pageSizeLimit'=>[5,$Size],
		            		]);
		            
		            $r = $query->orderBy('pd_product.'.$sort.' '.$order)
		            ->offset($pagination->offset)
		            ->limit($pagination->limit)
		            ->asArray()
		            ->all();
		            
		            $list = array_merge($list, $r);
		            unset($r);
		        }
		    }
		}
		else{
		    $pagination = new Pagination([
        		'defaultPageSize' => $defaultPageSize,
        		'totalCount'=> $query->count(),
        		'pageSizeLimit'=>[5,200],
        		]);
		    
		    if(isset($page)){
		    	$pagination->page = $page;
		    }
		    
		    $data['count'] = $query->count();
		    $data['pagination'] = $pagination;
		    $list = $query->orderBy('pd_product.'.$sort.' '.$order)
		    //->joinWith(['ordPay' => function ($query) {}])
		    ->offset($pagination->offset)
		    ->limit($pagination->limit)
		    ->asArray()
		    ->all();
		}
		
		$skus = array();
		$stock_skus = array();
		foreach ($list as $l){
		    $skus[] = $l['sku'];
		    $stock_skus[] = $l['sku'];
		}
		
		//???????????????
		$realArr = array();
		$asskus = array();
		$relationship = ProductConfigRelationship::find()->where(['cfsku'=>$skus])->asArray()->all();
		foreach($relationship as $r){
		    $realArr[$r['cfsku']][] = $r['assku'];
		    $asskus[] = $r['assku'];
		}
		$rel_pro = array();
		$pro = Product::find()->where(['sku'=>$asskus, 'type'=>'L'])->asArray()->All();
		foreach($pro as $p){
			$rel_pro[$p['sku']] = $p;
			$stock_skus[] = $p['sku'];
		}
		
		$data['data'] = array();
		foreach ($list as $p){
		    $data['data'][] = $p;
		    
		    //?????????????????????????????????????????????????????????
		    if($p['type'] == 'C'){
		        $place = count($data['data'])-1;
		        if(!empty($realArr[$p['sku']])){
		            $relationship_count = 0;
    		        foreach ($realArr[$p['sku']] as $r){
    		            if(!empty($rel_pro[$r])){
    		               $data['data'][] = $rel_pro[$r];
    		               $stock_skus[] = $r;
    		               $relationship_count++;
    		            }
    		        }
    		        $data['data'][$place]['relationship_count'] = $relationship_count + 1;
		        }
		        else{
		            $data['data'][$place]['relationship_count'] = 0;
		        }
		    }
		}
		
		unset($pro);
		unset($relationship);
		unset($list);
		
		//????????????????????????
		$pd_sp_list = ProductSuppliersHelper::getProductPurchaseLink($stock_skus);
		foreach ($data['data'] as &$one){
			//??????????????????
			$one['purchase_link_list'] = '';
			if(array_key_exists($one['sku'], $pd_sp_list)){
				$one['purchase_link'] = $pd_sp_list[$one['sku']]['purchase_link'];
				$one['purchase_link_list'] = json_encode($pd_sp_list[$one['sku']]['list']);
			}
		}
		
		if(!$isOnlyPro){
    		//$pagination->totalCount = $query->count();
    		$brandList = BrandHelper::ListBrandData();
    		
    		//echo print_r($query->createCommand()->getRawSql(),true);//test kh
    		
    		$supplierList = ProductSuppliersHelper::ListSupplierData();
    		
    		//?????????????????????????????????
    		$is_show = ConfigHelper::getConfig("is_show_overser_warehouse");
    		if(empty($is_show))
    			$is_show = 0;
    		//????????????????????????
    		if($is_show == 0){
    			$warehouseList = Warehouse::find()->select(['warehouse_id', 'name'])->where(['is_active' => 'Y', 'is_oversea' => '0'])->asArray()->all();
    		}
    		else{
    			$warehouseList = Warehouse::find()->select(['warehouse_id', 'name'])->where(['is_active' => 'Y'])->asArray()->all();
    		}
    		//????????????????????????
    		$warehouse = array();
    		$warehouseids = array();
    		if(!empty($warehouseList)){
    			foreach($warehouseList as $val){
    				$warehouse[$val['warehouse_id']] = $val['name'];
    				$warehouseids[] = $val['warehouse_id'];
    			}
    		}
    		
    		//????????????????????????
    		$stock_arr = array();
    		$stock = ProductStock::find()->select(['sku', 'warehouse_id', 'qty_in_stock'])->where(['sku' => $stock_skus, 'warehouse_id' => $warehouseids])->andWhere('qty_in_stock>0')->asArray()->all();
    		foreach($stock as $val){
    			if(array_key_exists($val['warehouse_id'], $warehouse)){
	    			$stock_arr[$val['sku']][] = [
	    				'warehouse' => $warehouse[$val['warehouse_id']],
	    				'qty_in_stock' => $val['qty_in_stock'],
	    			];
    			}
    		}
    		
    		if (empty($brandList[0]['name'])){
    			$brandList[0]['name'] = '?????????';
    		}
    		
    		if (empty($supplierList[0]['name'])){
    			$supplierList[0]['name'] = '?????????';
    		}
    		
    		//??????????????????
    		$class_arr = array();
    		$class_list = ProductClassification::find()->asArray()->all();
    		foreach($class_list as $class){
    			$class_arr[$class['ID']] = $class['name'];
    		}
    		
    		foreach ($data['data'] as $key => $val) {
    			if (!empty($brandList[$val['brand_id']]['name'])){
    				$data['data'][$key]['brand_id']=$brandList[$val['brand_id']]['name'];
    			}
    			
    			if (!empty($supplierList[$val['supplier_id']]['name'])){
    				$data['data'][$key]['supplier_id']=$supplierList[$val['supplier_id']]['name'];
    			}
    			
    			//get product alias list
    			//$alias = ProductAliases::findall(['sku' => $val['sku']]);
    			
    			$data['data'][$key]['aliaslist'] = [];
    			
    			//get product tag list
    			//$tags = []; 
    			
    			$tmpRt = TagHelper::getOneProductTags($val['sku']);
    			$tags = $tmpRt['tags'];
    			/*
    			$tags = Tag::find()
    			->andWhere(['in' , 'tag_id',(new Query())->select(['tag_id'])->from('pd_product_tags')->where(['sku'=>$val['sku']])])
    			->asArray()
    			->All();
    			*/
    			//var_dump($tags);
    			//$tags = Yii::$app->get('subdb')->createCommand("SELECT * FROM `pd_tag` where `tag_id` in (SELECT `tag_id` FROM `pd_product_tags` where sku ='".$val['sku']."')")->queryAll();
    			$data['data'][$key]['taglist'] = $tags;
    			
    			//??????
    			$other_attributes = $data['data'][$key]['other_attributes'];
    			if(!empty($other_attributes)){
    			    $data['data'][$key]['other_attributes_arr'] = explode(';', $other_attributes);
    			}
    			else{
    			    $data['data'][$key]['other_attributes_arr'] = array();
    			}
    			
    			$data['data'][$key]['stock'] = '';
    			if($val['type'] == 'C' || $val['type'] == 'B'){
    				$data['data'][$key]['purchase_price'] = '';
    			}
    			else{
    				$data['data'][$key]['purchase_price'] = empty($data['data'][$key]['purchase_price']) ? 0 : (float)$data['data'][$key]['purchase_price'];
    				
    				//??????????????????
    				if(array_key_exists($val['sku'], $stock_arr)){
    					foreach ($stock_arr[$val['sku']] as $stock){
    						$data['data'][$key]['stock'] .= $stock['warehouse'].': '.$stock['qty_in_stock'].'<br>';
    					}
    					$data['data'][$key]['stock'] = rtrim($data['data'][$key]['stock'], '<br>');
    				}
    			}
    			
    			//??????
    			$data['data'][$key]['class_name'] = empty($class_arr[$data['data'][$key]['class_id']]) ? '?????????' : $class_arr[$data['data'][$key]['class_id']];
    		}
    		
    		//?????????????????????
    		$data['bundleArr'] = array();
    		$asskus = array();
    		$proArr = array();
    		$relationship = ProductBundleRelationship::find()->where(['bdsku'=>$skus])->asArray()->all();
    		foreach ($relationship as $r){
    		    $asskus[] = $r['assku'];
    		}
    		$pro = Product::find()->select(['sku', 'name', 'photo_primary'])->where(['sku'=>$asskus])->asArray()->All();
    		foreach ($pro as $p){
    		    $proArr[$p['sku']] = $p;
    		}
    		foreach ($relationship as $r){
    		    if(!empty($proArr[$r['assku']])){
    		        $p = $proArr[$r['assku']];
    		        $data['bundleArr'][] = [
    		            'bdsku' => $r['bdsku'],
    		            'sku' => $p['sku'],
    		            'name' => $p['name'],
    		            'photo_primary' => $p['photo_primary'],
    		            'qty' => $r['qty'],
    		        ];
    		    }
    		}
		}
		return $data;
	}//end of getProductlist
	
	
	/**
	 +----------------------------------------------------------
	 * ??????????????????
	 +----------------------------------------------------------
	 * @access static
	 +----------------------------------------------------------
	 * @param model		????????????
	 * @param values	??????????????????
	 * @param isUpdate	?????????????????????
	 +----------------------------------------------------------
	 * @return				???????????????true,????????????????????????
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		ouss	2014/02/27				?????????
	 +----------------------------------------------------------
	 **/
	static public function saveProduct($model, $values, $isUpdate = false){
		ProductAliases::deleteAll(" `sku` NOT IN (SELECT `sku` FROM `pd_product` WHERE 1)");
		//???????????????alias???????????????
		if (isset($_POST['ProductAliases']['AliasStatus'])  ){
			$AddAliasList = [];
			foreach ($_POST['ProductAliases']['AliasStatus'] as $k => $alias_status){
				if($alias_status == 'add'){
					if(!empty($_POST['ProductAliases']['alias_sku'][$k])){
						$AddAliasList[] = $_POST['ProductAliases']['alias_sku'][$k];
					}
				}
			}
			 
			/*$result = self::checkProductAlias($model->sku, $AddAliasList);
			if ($result['status'] == 'failure'){
				return array('??????' => $result['message']);
			}*/
			/*
			//??????alias?????????????????????????????????
			if ($result['status'] == 'confirm'){
				//return array('??????' => $result['message']);
				if(isset($result['redundant'])){
					$updateAliasRelated=$result['redundant'];
				}
			}
			*/
		}else{
			
				
		}
		
		if(isset($values['Product']['sku'])){
			$values['Product']['sku']= trim($values['Product']['sku']);
		}
		if (empty($values['Product']['sku']) or (strpos($values['Product']['sku'], "\t") )!==false or (strpos($values['Product']['sku'], "\r"))!==false or (strpos($values['Product']['sku'], "\n"))!==false){
			$result['message'] = 'SKU ?????????????????????????????????tab????????????????????????';
			return array('??????' => $result['message']);
		}
		 
		//??????????????? brand id ??????????????????0
		if (!isset($values['Product']['brand_id']) or $values['Product']['brand_id']==null or $values['Product']['brand_id']=='')
			$values['Product']['brand_id'] = 0;
		
		//prod_weight ??????????????? , ??????????????? 
		if (empty($values['Product']['prod_weight'])) $values['Product']['prod_weight'] = 0;
		//prod_width ??????????????? , ???????????????
		if (empty($values['Product']['prod_width'])) $values['Product']['prod_width'] = 0;
		//prod_length ??????????????? , ???????????????
		if (empty($values['Product']['prod_length'])) $values['Product']['prod_length'] = 0;
		//prod_weight ??????????????? , ???????????????
		if (empty($values['Product']['prod_height'])) $values['Product']['prod_height'] = 0;
		//declaration_value ??????????????? , ???????????????
		if (empty($values['Product']['declaration_value'])) $values['Product']['declaration_value'] = 0;
		
		//purchase_price ??????????????? , ???????????????
		if (empty($values['Product']['purchase_price'])) $values['Product']['purchase_price'] = 0;
		
		//?????????????????????S ????????????
		if (empty($values['Product']['type'])) $values['Product']['type'] = 'S';
		//?????????????????????OS ??????
		if (empty($values['Product']['status'])) $values['Product']['status'] = 'OS';
		
		$model->total_stockage =0;
		$model->pending_ship_qty =0;
		/*
		 ???dsp???:???????????????????????????
		???ebay?????????ebay listing??????????????????
		???amazon?????????amazon listing??????????????????
		?????? ?????? ???manual???????????????????????????
		???excel?????????excel ?????????????????????
		* */
		if ( empty($values['Product']['create_source'])){
			$values['Product']['create_source'] = 'manual';
		}
		
		if( !in_array( 'battery', $model->attributes() )){
			if( isset($values['Product']['battery']) )
				unset($values['Product']['battery']);
		}
		$model->attributes = $values['Product'];
		
		$transaction = Yii::$app->get('subdb')->beginTransaction();
		
		//$current_time=explode(" ",microtime());//test liang
		//$step1_time=round($current_time[0]*1000+$current_time[1]*1000);//test liang
		//\Yii::info(['catalog', __CLASS__, __FUNCTION__,'Background',"LiangTest 01 beginTransaction at time ".date('Y-m-d H:i:s', time()) ],"edb\global");//test liang
		
		try
		{
			if($isUpdate)
			{
				$model->update_time = date('Y-m-d H:i:s', time());
			}
			else
			{
				$model->create_time = date('Y-m-d H:i:s', time());
				$model->update_time = date('Y-m-d H:i:s', time());
				
				//???????????????????????????
				$values['ProductAliases']['alias_sku'][] = $model->sku;
				$values['ProductAliases']['pack'][] = '1';
				$values['ProductAliases']['platform'][] = '';
				$values['ProductAliases']['selleruserid'][] = '';
				$values['ProductAliases']['comment'][] = '';
				$values['ProductAliases']['AliasStatus'][] = 'add';
			}

			if ($model->purchase_by == null) {
				$model->purchase_by = 0;
			}
			
			if (isset($values['edit_class_id'])) {
				$model->class_id = $values['edit_class_id'];
			}
			
			//$isChange = false;
			if (!empty($values['ProductAliases']))
			{
				//????????????????????????????????????
				/*if(!empty($values['ProductAliases']['AliasStatus'])){
					foreach ($values['ProductAliases']['AliasStatus'] as $p){
						if($p == 'add' || $p == 'del'){
							$isChange = true;
							break;
						}
					}
				}*/
			    
				//$update_result = self::updateSkuAliases($model->sku, $values['ProductAliases']);
				//if ($update_result == -1) throw new Exception("??????????????????!");
				//throw new Exception("??????????????????!");
				$model->is_has_alias = self::updateSkuAliases($model->sku, $values['ProductAliases'])  ? 'Y' : 'N';
			}
			else
			{
				$model->is_has_alias = 'N';
				//cleare aliases
				self::deleteAllAliases($model->sku);
				 
			}
			
			//$current_time=explode(" ",microtime());//test liang
			//$step2_time=round($current_time[0]*1000+$current_time[1]*1000);//test liang
			//\Yii::info(['catalog', __CLASS__, __FUNCTION__,'Background',"LiangTest 02 end of update Sku alias , used time:".($step2_time-$step1_time) ],"edb\global");//test liang
			
			if (!empty($values['Tag']))
			{
				$model->is_has_tag = TagHelper::updateTag($model->sku, $values['Tag']['tag_name']) ? 'Y' : 'N';
			}
			else
			{
				$model->is_has_tag = 'N';
				ProductTags::deleteAll(['sku'=>$model->sku]);
			}
			
			//$current_time=explode(" ",microtime());//test liang
			//$step3_time=round($current_time[0]*1000+$current_time[1]*1000);//test liang
			//\Yii::info(['catalog', __CLASS__, __FUNCTION__,'Background',"LiangTest 03 end of update Tags , used time:".($step3_time-$step2_time) ],"edb\global");//test liang
			
			
			if (!empty($values['Product']['other_attributes']))
			{
				ProductFieldHelper::updateField($values['Product']['other_attributes']);
				$model->other_attributes = $values['Product']['other_attributes'];
			}
			
			//$current_time=explode(" ",microtime());//test liang
			//$step4_time=round($current_time[0]*1000+$current_time[1]*1000);//test liang
			//\Yii::info(['catalog', __CLASS__, __FUNCTION__,'Background',"LiangTest 04 end of update fields , used time:".($step4_time-$step3_time) ],"edb\global");//test liang
			
			
			$photo_primary = empty($values['Product']['photo_primary']) ? '' : $values['Product']['photo_primary'];
			$photo_others = empty($values['Product']['photo_others']) ? array() : explode('@,@', $values['Product']['photo_others']);
			PhotoHelper::savePhotoByUrl($model->sku, $photo_primary, $photo_others);
			
			//$current_time=explode(" ",microtime());//test liang
			//$step5_time=round($current_time[0]*1000+$current_time[1]*1000);//test liang
			//\Yii::info(['catalog', __CLASS__, __FUNCTION__,'Background',"LiangTest 05 end of update photos , used time:".($step5_time-$step4_time) ],"edb\global");//test liang
			
			
			if (isset($values['ProductSuppliers'])) {
				$ProductSuppliersInfo = ProductSuppliersHelper::updateProductSuppliers($model->sku, $values['Product'], $values['ProductSuppliers']);
				$model->supplier_id = $ProductSuppliersInfo['supplier_id'];
				$model->purchase_price = $ProductSuppliersInfo['purchase_price'];
				//$model->purchase_link = $ProductSuppliersInfo['purchase_link'];
			}
			
			//??????????????????????????????
			if(!empty($model->addi_info)){
			    $addi_info = json_decode($model->addi_info, true);
			}
			else {
			    $addi_info = [];
			}
			$addi_info['commission_per'] = [];
			if(isset($values['commission_platform']) && isset($values['commission_value'])){
				foreach($values['commission_platform'] as $key => $plat){
				    if(isset($values['commission_value'][$key])){
				        $addi_info['commission_per'][$plat] = $values['commission_value'][$key];
				    }
				}
			}
			$model->addi_info = json_encode($addi_info);
			
			//$current_time=explode(" ",microtime());//test liang
			//$step6_time=round($current_time[0]*1000+$current_time[1]*1000);//test liang
			//\Yii::info(['catalog', __CLASS__, __FUNCTION__,'Background',"LiangTest 06 end of update suppliers , used time:".($step6_time-$step5_time) ],"edb\global");//test liang
			
			
			$model->capture_user_id = \Yii::$app->user->id;
			
			$edit_log = '';
			$log_key_id = '';
			$is_change_class = false;    //??????????????????
			//??????????????????
			$old_product = Product::findOne(['product_id' => $model->product_id]);
			if(empty($old_product)){
				$edit_log = '????????????, SKU: '.$model->sku.'; ??????: '.$model->name;
				$is_change_class = true;
			}
			else{
				foreach (self::$EDIT_PRODUCT_LOG_COL as $col_k => $col_n){
					if($model->$col_k != $old_product->$col_k){
						if(empty($edit_log)){
							$edit_log = '????????????, SKU: '.$model->sku;
							$log_key_id = $model->product_id;
						}
						$edit_log .= ', '.$col_n.'???"'.$old_product->$col_k.'"??????"'.$model->$col_k.'"';
					}
				}
				
				if($old_product->class_id != $model->class_id){
					$is_change_class = true;
				}
			}
			
			if( $model->save())
			{
				if(!empty($edit_log)){
					//??????????????????
					UserHelper::insertUserOperationLog('catalog', $edit_log, null, $log_key_id);
				}
				
				//$current_time=explode(" ",microtime());//test liang
				//$step7_time=round($current_time[0]*1000+$current_time[1]*1000);//test liang
				//\Yii::info(['catalog', __CLASS__, __FUNCTION__,'Background',"LiangTest 07 end of model save, used time:".($step7_time-$step6_time) ],"edb\global");//test liang
				
				/*????????????????????????
				if (isset($_POST['ProductAliases']['alias_sku'])  ){
					
					$merge_alias_list = Product::findall($PDAliasList);
						
					foreach($merge_alias_list as $one_merge_alias){
						//update alias related data
						$updateAliasRelated = self::updateAliasRelatedData($model->sku, $one_merge_alias->sku);
						//print_r($updateAliasRelated);
					}
				}*/
				
				//???????????????????????????????????????????????????????????????
				/*if($isUpdate == false || $isChange == true)
				{
				    WarehouseHelper::RefreshOneQtyOrdered($model->sku);
				}*/
				
				$current_time=explode(" ",microtime());//test liang
				$step8_time=round($current_time[0]*1000+$current_time[1]*1000);//test liang
				//\Yii::info(['catalog', __CLASS__, __FUNCTION__,'Background',"LiangTest 08 end of update Alias Related, used time:".($step8_time-$step7_time) ],"edb\global");//test liang
			
				
				//??????,??????config?????????
				if($values['Product']['type']=='C') {
					if(empty($values['children']['sku'])) {
						$transaction->rollBack();
						return array('??????' => TranslateHelper::t('?????????????????????') );
					}
					else{
						if(empty($values['not_delete_cli']) || $values['not_delete_cli'] === false){
							//????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????
							self::removeConfigRelationship($model->sku, 'cfsku');
						}
						
						foreach ($values['children']['sku'] as $index=>$child_sku){
							$childModel = Product::findOne(['sku'=>$child_sku]);
							if ($childModel ==null){
								$childModel = new Product();
								$childModel->attributes = $model->attributes;
								//print_r($model->attributes);
								//print_r($childModel->attributes);
								//exit();
								$childModel->type = 'L';
								$childModel->sku = $child_sku;
								$childModel->supplier_id = ($model->supplier_id !=null)?$model->supplier_id:0;
								$childModel->photo_primary = $values['children']['photo_primary'][$index];
								PhotoHelper::resetPhotoPrimary($childModel->sku, $childModel->photo_primary);
								
								$childModel->comment = ($model->comment !=null)?$model->comment:'';
								$childModel->check_standard = ($model->check_standard !=null)?$model->check_standard:'';
								
								if (isset($values['ProductSuppliers'])) {
									$ProductSuppliersInfo = ProductSuppliersHelper::updateProductSuppliers($childModel->sku, $values['Product'], $values['ProductSuppliers']);
									$childModel->supplier_id = $ProductSuppliersInfo['supplier_id'];
									$childModel->purchase_price = $ProductSuppliersInfo['purchase_price'];
								}
								//?????????????????????
								$relationAttrsIds = '';
								$attrStr = $values['Product']['other_attributes'];
								if(!empty($values['children']['config_field_1'])){
									if($attrStr=='')
										$attrStr.=$values['children']['config_field_1'].":".$values['children']['config_field_value_1'][$index];
									else 
										$attrStr.= ";".$values['children']['config_field_1'].":".$values['children']['config_field_value_1'][$index];
									$relationAttrsIds .= ProductFieldHelper::getProductFieldIdByName($values['children']['config_field_1']);
								}
								if(!empty($values['children']['config_field_2'])){
									$attrStr.= ";".$values['children']['config_field_2'].":".$values['children']['config_field_value_2'][$index];
									$relationAttrsIds .=','. ProductFieldHelper::getProductFieldIdByName($values['children']['config_field_2']);
								}
								if(!empty($values['children']['config_field_3'])){
									$attrStr.= ";".$values['children']['config_field_3'].":".$values['children']['config_field_value_3'][$index];
									$relationAttrsIds .=','. ProductFieldHelper::getProductFieldIdByName($values['children']['config_field_3']);
								}
								$attrStr = ProductFieldHelper::uniqueProductFieldStr($attrStr);
								$childModel->other_attributes = $attrStr;
								ProductFieldHelper::updateField($attrStr);//???????????????????????????????????????
								
								if (!empty($values['Tag']))
									$childModel->is_has_tag = TagHelper::updateTag($childModel->sku, $values['Tag']['tag_name']) ? 'Y' : 'N';
								else
									$childModel->is_has_tag = 'N';
								
								//???????????????????????????
								$l_alias = array();
								$l_alias['alias_sku'][] = $child_sku;
								$l_alias['pack'][] = '1';
								$l_alias['platform'][] = '';
								$l_alias['selleruserid'][] = '';
								$l_alias['comment'][] = '';
								$l_alias['AliasStatus'][] = 'add';
								$childModel->is_has_alias = self::updateSkuAliases($child_sku, $l_alias)  ? 'Y' : 'N';
								$childModel->class_id = empty($model->class_id) ? '0' : $model->class_id;
							}
							else{
								$childModel->type = 'L';
								$childModel->update_time = date('Y-m-d H:i:s', time());
								$childModel->photo_primary = $values['children']['photo_primary'][$index];
								PhotoHelper::resetPhotoPrimary($childModel->sku, $values['children']['photo_primary'][$index],'OR');//????????????????????????Primary
								
								if (!empty($values['Tag']))
									$childModel->is_has_tag = TagHelper::updateTag($childModel->sku, $values['Tag']['tag_name']) ? 'Y' : 'N';
								
								if (isset($values['ProductSuppliers'])) {
									$ProductSuppliersInfo = ProductSuppliersHelper::updateProductSuppliers($childModel->sku, $values['Product'], $values['ProductSuppliers']);
									$childModel->supplier_id = $ProductSuppliersInfo['supplier_id'];
									$childModel->purchase_price = $ProductSuppliersInfo['purchase_price'];
								}
								//???????????????????????????????????????
								$relationAttrsIds='';
								$attrStr = $values['Product']['other_attributes'];
								if(!empty($values['children']['config_field_1'])){
									if($attrStr=='')
										$attrStr.=$values['children']['config_field_1'].":".$values['children']['config_field_value_1'][$index];
									else 
										$attrStr.= ";".$values['children']['config_field_1'].":".$values['children']['config_field_value_1'][$index];
									$relationAttrsIds .=','. ProductFieldHelper::getProductFieldIdByName($values['children']['config_field_1']);
								}
								if(!empty($values['children']['config_field_2'])){
									if($attrStr=='')
										$attrStr.=$values['children']['config_field_2'].":".$values['children']['config_field_value_2'][$index];
									else
										$attrStr.= ";".$values['children']['config_field_2'].":".$values['children']['config_field_value_2'][$index];
									$relationAttrsIds .=','. ProductFieldHelper::getProductFieldIdByName($values['children']['config_field_2']);
								}
								if(!empty($values['children']['config_field_3'])){
									if($attrStr=='')
										$attrStr.=$values['children']['config_field_3'].":".$values['children']['config_field_value_3'][$index];
									else
										$attrStr.= ";".$values['children']['config_field_3'].":".$values['children']['config_field_value_3'][$index];
									$relationAttrsIds .=','. ProductFieldHelper::getProductFieldIdByName($values['children']['config_field_3']);
								}
								$oldAttr = $childModel->other_attributes;
								if($oldAttr==null or $oldAttr=='')
									$oldAttr='';
								else 
									$oldAttr .= $oldAttr.";";
								$uniqueAttrStr= ProductFieldHelper::uniqueProductFieldStr($oldAttr.$attrStr);
								$childModel->other_attributes = $uniqueAttrStr;
								ProductFieldHelper::updateField($attrStr);//???????????????????????????????????????
								$childModel->class_id = empty($model->class_id) ? '0' : $model->class_id;
							}
							if(!$childModel->save())
							{
								$transaction->rollBack();
								//echo print_r($childModel->getErrors(),true);
								return $childModel->getErrors();
							}else{//?????????????????????????????????????????????
							    //??????????????????--????????????
							    if(empty($childModel->product_id)){
							        UserHelper::insertUserOperationLog('catalog', "????????????, SKU: ".$childModel->sku."; ??????: ".$childModel->name);
							    }
							    
								$relationship = ProductConfigRelationship::findOne(['assku' => $childModel->sku ]);
								if ($relationship==null) $relationship = new ProductConfigRelationship;
								$relationship->cfsku = $model->sku;
								$relationship->assku = $childModel->sku;
								$relationship->config_field_ids = empty($relationAttrsIds) ? '1' : $relationAttrsIds;
								$relationship->create_date = date('Y-m-d H:i:s', time());

								if (!($relationship->save())){
									$transaction->rollBack();
									//echo print_r($relationship->getErrors(),true);
									return $relationship->getErrors();
								}
							}
						}
					}
				}
				
				//$current_time=explode(" ",microtime());//test liang
				//$step9_time=round($current_time[0]*1000+$current_time[1]*1000);//test liang
				//\Yii::info(['catalog', __CLASS__, __FUNCTION__,'Background',"LiangTest 09 end of save config, used time:".($step9_time-$step8_time) ],"edb\global");//test liang
			
				
				//??????,??????Bundle?????????
				if($values['Product']['type']=='B') {
					if(empty($values['children'])) {
						$transaction->rollBack();
						return array('??????' => TranslateHelper::t('?????????????????????') );
					}
					else{
						//????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????
						self::removeBundleRelationship($model->sku, 'bdsku');
				
						foreach ($values['children']['sku'] as $index=>$child_sku){
							$childModel = Product::findOne(['sku'=>$child_sku]);
							if ($childModel ==null){
								$transaction->rollBack();
								return array('??????' => TranslateHelper::t('??????????????????') );
							}
							else{
								$childModel->update_time = date('Y-m-d H:i:s', time());
							}
							if(!$childModel->save())
							{
								$transaction->rollBack();
								echo print_r($childModel->getErrors(),true);
								return $childModel->getErrors();
							}else{//?????????????????????????????????????????????
								$relationship = ProductBundleRelationship::findOne(['bdsku'=>$model->sku,'assku' => $childModel->sku ]);
								if ($relationship==null) $relationship = new ProductBundleRelationship;
								$relationship->bdsku = $model->sku;
								$relationship->assku = $childModel->sku;
								$relationship->qty = $values['children']['bundle_qty'][$index];
								$relationship->create_date = date('Y-m-d H:i:s', time());
								if (!($relationship->save())){
									$transaction->rollBack();
									echo print_r($relationship->getErrors(),true);
									return $relationship->getErrors();
								}
							}
						}
					}
				}
				
				//$current_time=explode(" ",microtime());//test liang
				//$step10_time=round($current_time[0]*1000+$current_time[1]*1000);//test liang
				//\Yii::info(['catalog', __CLASS__, __FUNCTION__,'Background',"LiangTest 10 end of save bundle, used time:".($step10_time-$step9_time) ],"edb\global");//test liang
			
				
				$transaction->commit();
				
				//????????????
				if($is_change_class){
					self::getProductClassCount(true);
				}
				
				//$current_time=explode(" ",microtime());//test liang
				//$step11_time=round($current_time[0]*1000+$current_time[1]*1000);//test liang
				//\Yii::info(['catalog', __CLASS__, __FUNCTION__,'Background',"LiangTest 11 end of transaction commit, used time:".($step11_time-$step10_time) ],"edb\global");//test liang
			
				return true;
				
			}
			else {
				$transaction->rollBack();
				//echo print_r($model->getErrors(),true);
				return $model->getErrors();
				
			}
		}
		catch(Exception $e)
		{
			$transaction->rollBack();
			return array('??????' => $e->getMessage());
		}
	}//end of saveProduct
	
	/**
	 +----------------------------------------------------------
	 * ???????????????SKU??????
	 +----------------------------------------------------------
	 * @access static
	 +----------------------------------------------------------
	 * @param sku			?????????????????????SKU
	 * @param aliasesList		SKU????????????
	 +----------------------------------------------------------
	 * @return				???????????????SKU????????????
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		ouss	2014/02/27				?????????
	 +----------------------------------------------------------
	**/
	public static function updateSkuAliases($sku, $aliasesList) 
	{
		if (!isset($aliasesList)){
			$aliasesList = array();
			return false;
		}
		
		$del_aliases = array();  //???????????????
		$add_aliases = array();  //???????????????
		foreach ($aliasesList['AliasStatus'] as $k => $aliastatus){
			if($aliastatus == 'add'){
				$add_aliases[] = [
					'alias_sku' => $aliasesList['alias_sku'][$k],
					'pack' => $aliasesList['pack'][$k],
					'platform' => empty($aliasesList['platform'][$k]) ? '' : $aliasesList['platform'][$k],
					'selleruserid' => empty($aliasesList['selleruserid'][$k]) ? '' : $aliasesList['selleruserid'][$k],
					'comment' => $aliasesList['comment'][$k],
				];
			}
			else if($aliastatus == 'del'){
				$del_aliases[] = [
					'alias_sku' => $aliasesList['alias_sku'][$k],
					'platform' => empty($aliasesList['platform'][$k]) ? '' : $aliasesList['platform'][$k],
					'selleruserid' => empty($aliasesList['selleruserid'][$k]) ? '' : $aliasesList['selleruserid'][$k],
				];
			}
		}
		
		//??????????????????
		foreach ($del_aliases as $alias) {
			$ali = ProductAliases::findone(['sku'=>$sku, 'alias_sku'=>$alias['alias_sku'], 'platform'=>$alias['platform'], 'selleruserid'=>$alias['selleruserid']]);
			if(!empty($ali)){
				$ali->delete();
				
				$message = "failure to delete sku is ".$sku." and  alias_sku  is ".$alias['alias_sku'] ." and platform  is ".$alias['platform']." and selleruserid is ".$alias['selleruserid']." ! ";
				\Yii::info(['Catalog',__CLASS__,__FUNCTION__,'Online',$message],"edb\global");
				
				//?????????????????????????????????
				$model = Product::findone(['sku'=>$alias['alias_sku']]);
				if(!empty($model)){
					//????????????????????????????????????????????????????????????
					$model = ProductAliases::findone(['alias_sku'=>$alias['alias_sku']]);
					if(empty($model)){
						$model = new ProductAliases();
						$model->sku = $alias['alias_sku'];
						$model->alias_sku = $alias['alias_sku'];
						$model->pack = 1;
						$model->platform = '';
						$model->selleruserid = '';
						$model->comment = '';
						
						if (! $model->save()) {
							$message .= "failure to add alone_match_alias is ".$model->sku." and  alias_sku  is ".$model->alias_sku."! ".json_encode($model->errors);
							\Yii::info(['Catalog',__CLASS__,__FUNCTION__,'Online',$message],"edb\global");
						}
					}
				}
			}
		}
		//??????????????????
		foreach ($add_aliases as $alias) {
			$message = '';
			$model = ProductAliases::findone(['alias_sku'=>$alias['alias_sku'], 'platform'=>$alias['platform'], 'selleruserid'=>$alias['selleruserid']]);
			if(empty($model)){
				$model = new ProductAliases();
			}
			else{
				$message = "failure to before of aliasinfo is ".$model->sku." and  alias_sku  is ".$model->alias_sku ." and pack  is ".$model->pack." and platform is ".$model->platform." and selleruserid is ".$model->selleruserid." and comment is ".$model->comment."! ";
			}
			
			$model->sku = $sku;
			$model->alias_sku = $alias['alias_sku'];
			$model->pack = $alias['pack'];
			$model->platform = $alias['platform'];
			$model->selleruserid = $alias['selleruserid'];
			$model->comment = $alias['comment'];
			
			if (! $model->save()) {
				//SysLogHelper::SysLog_Create("Catalog",__CLASS__, __FUNCTION__,"","failure to save sku is ".$model->sku." and  alias_sku  is ".$model->alias_sku ." and pack  is ".$model->pack." and forsite is ".$model->forsite." and comment is ".$model->comment."! ", "trace");
				
				$message .= "failure to save sku is ".$model->sku." and  alias_sku  is ".$model->alias_sku ." and pack  is ".$model->pack." and platform is ".$model->platform." and selleruserid is ".$model->selleruserid." and comment is ".$model->comment."! ".json_encode($model->errors);
				\Yii::info(['Catalog',__CLASS__,__FUNCTION__,'Online',$message],"edb\global");
			}
		}
		
		return ProductAliases::findAll(['sku' => $sku])>0;
	}
	
	/*???????????????????????????catalog\helpers\ProductApiHelper::addSkuAliases
	/**
	 * ??????????????????SKU????????????????????????????????????
	 * @access static
	 * @param sku			?????????????????????SKU
	 * @param aliasesList	SKU???????????? 	e.g.array(0=>['alias_sku'=>'alias_sku1','forsite'=>'ebay','pack'=>1,'comment'=>''],1=>[...],....)
	 * @return				array('success'=>boolean,'message'=>????????????);
	 * @author		lzhl	2015/12/28			?????????
	 +----------------------------------------------------------
	 /
	public static function addSkuAliases($sku, $aliasesList)
	{
		$result=array('success'=>true,'message'=>'');
		if (!isset($aliasesList)){
			return array('success'=>false,'message'=>'???????????????????????????');
		}
		$aliases = [];
		foreach ($aliasesList as $i=>$info){
			if(!in_array($info['alias_sku'],$aliases))
				$aliases[] = $info['alias_sku'];
			else {
				$result['success']=false;
				$result['message'].='??????'.$info['alias_sku'].'?????????????????????????????????????????????????????????????????????';
				continue;
			}
			$aliasData[$info['alias_sku']]['pack'] = $info['pack'];
			$aliasData[$info['alias_sku']]['forsite'] = $info['forsite'];
			$aliasData[$info['alias_sku']]['comment'] = $info['comment'];
		}
		if(!$result['success']){
			return $result;
		}
		
		$productAliase = ProductAliases::findAll(['sku' => $sku]);
		$existingAlias = [];
		foreach ($productAliase as $p) {
			$existingAlias[] = $p->alias_sku;
		}
		
		foreach ($aliases as $a){
			if(in_array($a,$existingAlias)){
				$result['success']=false;
				$result['message'].='??????'.$a.'???'.$sku.'??????????????????????????????????????????????????????????????????SKU????????????????????????????????????';
			}
		}
		if(!$result['success']){
			return $result;
		}

		$transaction = Yii::$app->get('subdb')->beginTransaction ();
		foreach ($aliases as $a) {
			$model = new ProductAliases();
			$model->sku = $sku;
			$model->alias_sku = $a;
			$model->pack = $aliasData[$a]['pack'];
			$model->forsite = $aliasData[$a]['forsite'];
			$model->comment = $aliasData[$a]['comment'];
				
			if (! $model->save()) {
				$transaction->rollBack();
				$message = "failure to save sku is ".$model->sku." and  alias_sku  is ".$model->alias_sku ." and pack  is ".$model->pack." and forsite is ".$model->forsite." and comment is ".$model->comment."! ".json_encode($model->errors);
				\Yii::info(['Catalog',__CLASS__,__FUNCTION__,'Online',$message],"edb\global");
				$result['success']=false;
				$result['message'].='??????'.$sku.'?????????'.$a.'????????????????????????E-OO1';
				return $result;
			}
		}
		$transaction->commit();
		return $result;
	}
	*/
    
    /**
	 +----------------------------------------------------------
	 * ???????????????????????????????????????
	 +----------------------------------------------------------
	 * @access static
	 +----------------------------------------------------------
	 * @param strAttr		???????????????????????????
	 +----------------------------------------------------------
	 * @return				???
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		ouss	2014/02/27				?????????
	 +----------------------------------------------------------
	**/
    public static function updatePdAttributes($strAttr)
    {
    	$attrList = explode(';', $strAttr);
    	//if (count($attrList) > 0) {
	    	foreach ($attrList as $attr) 
	    	{
	    		$tmpKv = explode(':', $attr);
	    		
	    		//$attrObj = Attributes::model()->findByAttributes(array('name' => $tmpKv[0]));
	    		$attrObj = Attributes::findOne(['name'=>$tmpKv[0]]);
	    		if ($attrObj == null)
	    		{
	    			$attrObj = new  Attributes();
	    			$attrObj->name = $tmpKv[0];
	    			$attrObj->values = json_encode(array(array('v' => $tmpKv[1], 't' => 1)));
	    			$attrObj->use_count = 1;
	    			$attrObj->save();
	    		}
	    		else 
	    		{
	    			$attrObj->use_count += 1;
	    			$values = json_decode($attrObj->values, true);
	    			$isExist = false;
	
	    			for ($i = 0; $i < count($values); $i++) {
	    				if ($values[$i]['v'] == $tmpKv[1]) 
	    				{
	    					$values[$i]['t'] = $values[$i]['t'] + 1;
	    					$isExist = true;
	    				};
	    			}
	    			if (!$isExist) 
	    			{
	    				$values[] = array('v' => $tmpKv[1], 't' => 1);
	    			}
	    			$valuesCount = count($values);
	    			if ($valuesCount > 20)
	    			{
	    				$minItemIx = 0;
	    				$minT = $values[$minItemIx]['t'];
	    				for ($i = 0; $i < $valuesCount; $i++)
	    				{
	    					if($values[$i]['t'] < $minT)
	    					{
	    						$minT = $values[$i]['t'];
	    						$minItemIx = $i;
	    					}
	    				}
	    				unset($values[$minItemIx]);
	    			}
	    			
	    			$attrObj->values = json_encode($values);
	    			$attrObj->save();
	    		}
	    	}
    	//}
    }
    
    /**
     +----------------------------------------------------------
     * ????????????
     +----------------------------------------------------------
     * @access static
     +----------------------------------------------------------
     * @param sku			??????SKU
     * @param alias sku     ????????????
     +----------------------------------------------------------
     * @return			na
     +----------------------------------------------------------
     * log			name	date					note
     * @author		lkh	2014/09/28				?????????
     +----------------------------------------------------------
     **/
    public static function updateAliasRelatedData($sku, $alias_sku){
    	// set up ignore update alias those table
    	//echo "<br/>enter updateAliasRelatedData<br/>";//liang test
    	$all_have_change_table = array(
    			array('TABLE_NAME'=>'dely_delivery_item','COLUMN_NAME'=>'sku'),
    			array('TABLE_NAME'=>'od_delivery_order','COLUMN_NAME'=>'sku'),
    			array('TABLE_NAME'=>'od_order_item','COLUMN_NAME'=>'sku'),
    			array('TABLE_NAME'=>'od_order_item_v2','COLUMN_NAME'=>'root_sku'),
    			array('TABLE_NAME'=>'pc_purchase_arrival_detail','COLUMN_NAME'=>'sku'),
    			array('TABLE_NAME'=>'pc_purchase_arrival_reject_detail','COLUMN_NAME'=>'sku'),
    			array('TABLE_NAME'=>'pc_purchase_items','COLUMN_NAME'=>'sku'),
    			array('TABLE_NAME'=>'pc_purchase_suggestion','COLUMN_NAME'=>'sku'),
    			array('TABLE_NAME'=>'pd_photo','COLUMN_NAME'=>'sku'),
    			array('TABLE_NAME'=>'pd_product','COLUMN_NAME'=>'sku'),
    			array('TABLE_NAME'=>'pd_product_aliases','COLUMN_NAME'=>'sku'),
    			array('TABLE_NAME'=>'pd_product_suppliers','COLUMN_NAME'=>'sku'),
    			array('TABLE_NAME'=>'pd_product_tags','COLUMN_NAME'=>'sku'),
    			array('TABLE_NAME'=>'wh_order_reserve_product','COLUMN_NAME'=>'sku'),
    			array('TABLE_NAME'=>'wh_oversea_warehouse_stock','COLUMN_NAME'=>'sku'),
    			array('TABLE_NAME'=>'wh_product_stock','COLUMN_NAME'=>'sku'),
    			array('TABLE_NAME'=>'pd_product_config_relationship','COLUMN_NAME'=>'sku'),
    			array('TABLE_NAME'=>'pd_product_bundle_relationship','COLUMN_NAME'=>'sku'),
    	);
    	$need_update_list = array(
    			'dely_delivery_item',
    			'od_delivery_order' ,
    			'od_order_item' ,
    			'od_order_item_v2' ,
    			'pc_purchase_arrival_detail' ,
    			'pc_purchase_arrival_reject_detail',
    			'pc_purchase_items',
    			'pc_purchase_suggestion',
    			'pd_photo',
    			'pd_product',
    			'pd_product_aliases',
    			'pd_product_suppliers',
    			'pd_product_tags',
    			'wh_order_reserve_product',
    			'wh_oversea_warehouse_stock', 
    			'wh_product_stock',
    	        'pd_product_config_relationship',
    	        'pd_product_bundle_relationship',
    	);
    	
    	$pd_product_column_CN_mapping=array(
    			'name'=>'??????',
    			'type'=>'????????????',
    			'status'=>'??????',
    			'prod_name_ch'=>'???????????????',
    			'prod_name_en'=>'???????????????',
    			'declaration_ch'=>'??????????????????',
    			'declaration_en'=>'??????????????????',
    			'declaration_value_currency'=>'????????????',
    			'declaration_value'=>'????????????',
    			'battery'=>'???????????????',
    			'brand_id'=>'??????id',
    			'purchase_by'=>'?????????id',
    			'prod_weight'=>'????????????',
    			'prod_width'=>'????????????',
    			'prod_length'=>'????????????',
    			'prod_height'=>'????????????',
    			'other_attributes'=>'????????????',
    			'photo_primary'=>'????????????',
    			'supplier_id'=>'???????????????id',
    			'purchase_price'=>'?????????',
    			'check_standard'=>'????????????',
    			'comment'=>'????????????',
    			'capture_user_id'=>'?????????id',
    			'create_time'=>'????????????',
    			'update_time'=>'????????????',
    			'total_stockage'=>'?????????',
    			'pending_ship_qty'=>'?????????',
    			'create_source'=>'??????',

    	);
    	 
    	$delete_list = array(
    			'pc_purchase_suggestion' ,
    			'pd_product' ,
    	        'pd_product_config_relationship' ,
    	        'pd_product_bundle_relationship' ,
    	);
    	 
    	$remove_redundant_before_update_list = array(
    			'pd_product_suppliers' ,
    			'pd_product_tags',
    			'pd_photo',
    	);
    	 
    	$remove_redundant_before_update_table_field_relation = array(
    			'pd_product_suppliers'=>'supplier_id' ,
    			'pd_product_tags'=>'tag_id',
    			'pd_photo'=>'photo_url',
    	);
    	 
    	$reorder_list = array(
    			'pd_photo' ,
    			'pd_product_suppliers' ,
    			 
    	);
    	 
    	$reorder_label_cn = array(
    			'pd_photo'=>"??????" ,
    			'pd_product_suppliers'=>"?????????" ,
    	);
    	
    	try {
    		$isChange = false;
    		
    		/*?????????????????????user??????????????????????????????table_list??????hardcode
    		$puid=\Yii::$app->user->identity->getParentUid();
    		if(isset(\Yii::$app->params["currentEnv"]) and \Yii::$app->params["currentEnv"]=='production' )
    			$userBase = 'user_'.$puid;//production ;
    		else 
    			$userBase = 'user2_'.$puid;//test ;
    		//find out all contain sku table  ".Yii::app()->muser->getPuid()."
    		$sql = "select k.TABLE_NAME , k.COLUMN_NAME
			from INFORMATION_SCHEMA.columns c
			left join INFORMATION_SCHEMA.KEY_COLUMN_USAGE k on c.TABLE_NAME = k.TABLE_NAME and c.TABLE_SCHEMA = k.TABLE_SCHEMA and k.CONSTRAINT_NAME = 'PRIMARY'
			where c.COLUMN_NAME = 'sku' and c.TABLE_SCHEMA = '".$userBase."' ";
    		$command = Yii::$app->get('subdb')->createCommand($sql);
    		$table_list = $command->queryAll();
			*/
    		//init $result_all this variable mark update result
    		$result_all = array();
    		$journal_id_list = array();
    		//loop each table start
			$table_list = $all_have_change_table;
    		foreach($table_list as $table_row){
    			
    			// if table name in ignore update list then skip it
    			if (!in_array($table_row['TABLE_NAME'], $need_update_list)){
    				continue;
    			}
    			
    			//update alias's attr to root if root's attr is null
    			if(strtolower($table_row['TABLE_NAME']) == "pd_product"){
    				$root_model = Product::findOne($sku);
    				$root_attrs=[];
    				if($root_model<>null)
    					$root_attrs = $root_model->attributes;
    				
    				$alias_model = Product::findOne($alias_sku);
    				$alias_attrs=[];
    				if($alias_model<>null)
    					$alias_attrs = $alias_model->attributes;
    				$modelChanged=false;
    				$changedKey = [];
    				$changedVal = [];
    				$addComment = [];
    				foreach ($root_attrs as $key=>&$value){
    					if(empty($value) && !empty($alias_attrs[$key])){
    						$value = $alias_attrs[$key];
    						$chagedKey[]=$key;
    						$chagedVal[]=$alias_attrs[$key];
    						$keyCN = $key;
    						if(isset($pd_product_column_CN_mapping[$key]))
    							$keyCN = $pd_product_column_CN_mapping[$key];
    						$addComment[]=$keyCN.'=>'.$alias_attrs[$key];
    						$modelChanged =true;
    					}
    				}
    				if($modelChanged){
    					$root_model->attributes = $root_attrs;
    					$root_model->comment = $root_model->comment . "//??????????????????????????????".implode(',', $addComment);
    					$root_model->save();
    					/*???????????????????????????
    					$jrn_message = "??????????????????".$alias_sku." ??? ( ".implode(',', $changedKey).") ?????????????????? ( ".implode(',', $changedVal).") ??????sku???$sku ";
    					//write update journaled
    					$journal_id = SysLogHelper::InvokeJrn_Create("Catalog",__CLASS__, __FUNCTION__ , array('attrs' ,   $sku , $alias_sku ,$table_row['TABLE_NAME'] ,'update' , json_encode($changedKey) ,  json_encode($changedVal) , $jrn_message));
    					//mark joural id
    					$journal_id_list[] = $journal_id;
    					*/
    				}
    				
    			}
    			
    			//wh_product_stock record should merge before delete
    			if (strtolower($table_row['TABLE_NAME']) == "wh_product_stock" ){
    				//find all product stock through this alias
    				$alias_stock_list = ProductStock::find()->where(['sku'=>$alias_sku])->all();
    
    				//merge product stock start
    				foreach($alias_stock_list as $alias_stock_detail){
    					$alias_stock_detail->qty_purchased_coming = empty($alias_stock_detail->qty_purchased_coming) ? 0 : $alias_stock_detail->qty_purchased_coming;
    					$alias_stock_detail->qty_ordered = empty($alias_stock_detail->qty_ordered) ? 0 : $alias_stock_detail->qty_ordered;
    					$alias_stock_detail->qty_order_reserved = empty($alias_stock_detail->qty_order_reserved) ? 0 : $alias_stock_detail->qty_order_reserved;
    					$alias_stock_detail->qty_in_stock = empty($alias_stock_detail->qty_in_stock) ? 0 : $alias_stock_detail->qty_in_stock;
    					
    					//check the root sku stock whether exist
    					$root_stock = ProductStock::find()->where(
    							'warehouse_id=:warehouse_id and sku =:sku',
    							array(':warehouse_id'=>$alias_stock_detail->warehouse_id ,
    									':sku'=>$sku))->one();
    						
    					if (count($root_stock) > 0 ){
    						//if exist then create a stock take
    						
    						//combine two gird
    						$root_sku_grid=explode(',' , $root_stock->location_grid);
    						$alias_sku_grid=explode(',' , $alias_stock_detail->location_grid);
    						$combine_grid = $root_sku_grid;
    						foreach ($alias_sku_grid as $gird){
    							if(!in_array($gird, $root_sku_grid))
    								$combine[]=$gird;
    						}
    						$combine_grid=implode(',', $combine_grid);
    						$stock_take_data = array();
    						$product_info = array(
    							'sku'=>$sku ,
    							'qty_actual'=>$root_stock->qty_in_stock + $alias_stock_detail->qty_in_stock,
    							'location_grid'=>$combine_grid ,
    						);
    						$stock_take_data['prod'][] = $product_info;//$stock_take_data['prod']?????????????????????
    						$stock_take_data['warehouse_id'] = $root_stock->warehouse_id;
    						$stock_take_data['create_time'] = date('Y-m-d', time());
    						$stock_take_data['comment'] = "$alias_sku ????????? $sku ????????????????????????(??????????????????)";
    						StockTakeHelper::insertStockTake($stock_take_data);
    						//modify purchase coming qty and order pending qty
    						$sql = "update wh_product_stock set qty_purchased_coming = qty_purchased_coming+".$alias_stock_detail->qty_purchased_coming." ,
    						qty_ordered = qty_ordered +".$alias_stock_detail->qty_ordered."  ,
    						qty_order_reserved = qty_order_reserved +".$alias_stock_detail->qty_order_reserved."
    						where sku =:sku and warehouse_id =:warehouse_id ";
    
    						$command = Yii::$app->get('subdb')->createCommand($sql);
    						$command->bindValue(":sku",$sku,\PDO::PARAM_STR);
    						$command->bindValue(":warehouse_id",$root_stock->warehouse_id,\PDO::PARAM_INT);
    						$update_result = $command->execute();
    						
    						/*???????????????????????????
    						$jrn_message = "??????????????????".$sku." ???  ".$root_stock->warehouse_id." ?????????,?????? ?????? ".$alias_stock_detail->qty_in_stock.",??????????????????  ??????".$alias_stock_detail->qty_purchased_coming.",??????????????????  ".$alias_stock_detail->qty_ordered." , ?????????????????????".$alias_stock_detail->qty_order_reserved.",??????".$update_result."??????";
    						//write update journaled
    						$journal_id = SysLogHelper::InvokeJrn_Create("Catalog",__CLASS__, __FUNCTION__ , array(json_encode($sql) ,   $sku , $alias_sku ,$table_row['TABLE_NAME'] ,'update' , $table_row['COLUMN_NAME'] ,  $update_result , $jrn_message));
    						//mark joural id
    						$journal_id_list[] = $journal_id;
    						*/
    
    						//delete redundant record
    						$sql ="delete from wh_product_stock where sku =:sku and warehouse_id =:warehouse_id " ;
    						$command = Yii::$app->get('subdb')->createCommand($sql);
    						$command->bindValue(":sku",$alias_sku,\PDO::PARAM_STR);
    						$command->bindValue(":warehouse_id",$alias_stock_detail->warehouse_id,\PDO::PARAM_INT);
    						$delete_result = $command->execute();
    						
    						/*
    						$jrn_message = "??????????????????".$sku." ????????????".$alias_sku."?????? , ??????".$delete_result."?????????"  ;
    						//write update journaled
    						$journal_id = SysLogHelper::InvokeJrn_Create("Catalog",__CLASS__, __FUNCTION__ , array(json_encode($alias_stock_detail) ,   $sku , $alias_sku ,$table_row['TABLE_NAME'] ,'delete' , $table_row['COLUMN_NAME'] ,  $delete_result,$jrn_message));
    						//mark joural id
    						$journal_id_list[] = $journal_id;
    						*/
    						$isChange = true;
    
    					}else{
    						//if not exist then update stock
    						$sql = "update wh_product_stock set sku =:sku where sku =:alias_sku and warehouse_id =:warehouse_id ";
    						$command = Yii::$app->get('subdb')->createCommand($sql);
    						$command->bindValue(":sku",$sku,\PDO::PARAM_STR);
    						$command->bindValue(":alias_sku",$alias_sku,\PDO::PARAM_STR);
    						$command->bindValue(":warehouse_id",$alias_stock_detail->warehouse_id,\PDO::PARAM_INT);
    						$update_result = $command->execute();
    
    						/*???????????????????????????
    						//write update journaled
    						$journal_id = SysLogHelper::InvokeJrn_Create("Catalog",__CLASS__, __FUNCTION__ , array(json_encode($sql) ,   $sku , $alias_sku ,$table_row['TABLE_NAME'] ,'update' , $table_row['COLUMN_NAME'] ,  $update_result));
    						//mark joural id
    						$journal_id_list[] = $journal_id;
    						*/
    						$isChange = true;
    					}
    						
    				}//end of merge product loop
    
    				if (! empty($journal_id_list))
    					$result_all[$table_row['TABLE_NAME']] = json_encode($journal_id_list);
    
    				//merge product stock end
    				 
    			}else if (strtolower($table_row['TABLE_NAME']) == "od_order_item_v2" ){
    				$sql = "select ".$table_row['COLUMN_NAME']." from  ".$table_row['TABLE_NAME']." where root_sku = :alias_sku ";
    				
    				$command = Yii::$app->get('subdb')->createCommand($sql);
    				$command->bindValue(":alias_sku",$alias_sku,\PDO::PARAM_STR);
    				$root_query_result = $command->queryAll();
    				
    				//Existing data which need updated
    				if (count($root_query_result)>0){
    					//change alias to sku
    					$sql = "update  ".$table_row['TABLE_NAME']." set root_sku = :sku  where root_sku = :alias_sku ";
    					$command = Yii::$app->get('subdb')->createCommand($sql);
    					$command->bindValue(":sku",$sku,\PDO::PARAM_STR);
    					$command->bindValue(":alias_sku",$alias_sku,\PDO::PARAM_STR);
    					$update_result = $command->execute();
    					$isChange = true;
    				}
    			}else if (in_array($table_row['TABLE_NAME'], $delete_list)){
    				//**********************  Process delete start **********************//
    				$col_name = 'sku';
    			    if (strtolower($table_row['TABLE_NAME']) == "pd_product_config_relationship" || strtolower($table_row['TABLE_NAME']) == "pd_product_bundle_relationship" ){
    				    $col_name = 'assku';
    			    }
    			    
    			    $sql = "select * from  ".$table_row['TABLE_NAME']." where ".$col_name." =:alias_sku ";
    
    				$command = Yii::$app->get('subdb')->createCommand($sql);
    				$command->bindValue(":alias_sku",$alias_sku,\PDO::PARAM_STR);
    				$delete_data = $command->queryAll();
    				
    				if (count($delete_data)>0){
    					$sql = "delete from ".$table_row['TABLE_NAME']." where ".$col_name." =:alias_sku ";
    					$command = Yii::$app->get('subdb')->createCommand($sql);
    					$command->bindValue(":alias_sku",$alias_sku,\PDO::PARAM_STR);
    					$delete_result = $command->execute();
    					
    					/*???????????????????????????
    					$jrn_message =  "??????????????????".$sku."??? ????????????".$alias_sku."??????, ??????".$delete_result."?????????";
    					//write update journaled
    					$journal_id = SysLogHelper::InvokeJrn_Create("Catalog",__CLASS__, __FUNCTION__ , array(json_encode($delete_data) ,   $sku , $alias_sku ,$table_row['TABLE_NAME'] ,'delete' , $table_row['COLUMN_NAME'] ,  $delete_result,$jrn_message));
    					 
    					//mark joural id
    					$result_all[$table_row['TABLE_NAME']] = $journal_id;
    					*/
    					$isChange = true;
    				}
    				//**********************  Process delete  end  **********************//
    				 
    			}else{
    				//**********************  Process update start **********************//
    
    				//init pk_list
    				$pk_list = array();
    
    				if (in_array($table_row['TABLE_NAME'], $remove_redundant_before_update_list)){
    					//remove redundant data before update
    					 
    					$sql = "select * from ".$table_row['TABLE_NAME']." where sku =:alias_sku and
	    				".$remove_redundant_before_update_table_field_relation[$table_row['TABLE_NAME']]." in (
	    				select ".$remove_redundant_before_update_table_field_relation[$table_row['TABLE_NAME']]." from ".$table_row['TABLE_NAME']."
	    				where sku =:sku )";
    					$command = Yii::$app->get('subdb')->createCommand($sql);
    					$command->bindValue(":alias_sku",$alias_sku,\PDO::PARAM_STR);
    					$command->bindValue(":sku",$sku,\PDO::PARAM_STR);
    					$delete_data = $command->queryAll();
    					
    					if (count($delete_data)>0){
    						foreach ($delete_data as $a_del_data){
    							$del_pk_list[] = $a_del_data[$remove_redundant_before_update_table_field_relation[$table_row['TABLE_NAME']]];
    						}
    						$sql = "delete from ".$table_row['TABLE_NAME']." where sku =:alias_sku and
		    				".$remove_redundant_before_update_table_field_relation[$table_row['TABLE_NAME']]." in ('".implode("','",$del_pk_list)."'
		    				)";
    						 
    						$command = Yii::$app->get('subdb')->createCommand($sql);
    						$command->bindValue(":alias_sku",$alias_sku,\PDO::PARAM_STR);
    						$delete_result = $command->execute();
    
    						/*???????????????????????????
    						$jrn_message =  "??????????????????".$sku."??? ????????????".$alias_sku."??????, ??????".$delete_result."?????????";
    						//write update journaled
    						$journal_id = SysLogHelper::InvokeJrn_Create("Catalog",__CLASS__, __FUNCTION__ , array(json_encode($delete_data) ,   $sku , $alias_sku ,$table_row['TABLE_NAME'] ,'delete' , $table_row['COLUMN_NAME'] ,  $delete_result,$jrn_message));
    						*/
    					}
    					
    					if (in_array($table_row['TABLE_NAME'], $reorder_list)){
    						//reset photo sort
    						$sql = "select max(priority)+1 from ".$table_row['TABLE_NAME']." where sku = :sku ";
    						$command = Yii::$app->get('subdb')->createCommand($sql);
    						$command->bindValue(":sku",$sku,\PDO::PARAM_STR);
    						$max_value = $command->queryScalar();
    						if ($max_value > 0 ){
    							$sql = "update ".$table_row['TABLE_NAME']." set priority = priority + $max_value where sku = :alias_sku  ";
    							$command = Yii::$app->get('subdb')->createCommand($sql);
    							$command->bindValue(":alias_sku",$alias_sku,\PDO::PARAM_STR);
    							$update_result = $command->execute();
    							/*???????????????????????????
    							$jrn_message =  "??????????????????  ?????????".$alias_sku." ".$reorder_label_cn[$table_row['TABLE_NAME']]."??????????????????".$update_result."?????????";
    							//write update journaled
    							$journal_id = SysLogHelper::InvokeJrn_Create("Catalog",__CLASS__, __FUNCTION__ , array($pk_list , $sku,  $alias_sku , $table_row['TABLE_NAME'], 'update' , $table_row['COLUMN_NAME'] ,$update_result,$jrn_message));
    							*/
    						}
    					}
    				}
    
    				//find all pk value
    				$sql = "select ".$table_row['COLUMN_NAME']." from  ".$table_row['TABLE_NAME']." where sku = :alias_sku ";
    
    				$command = Yii::$app->get('subdb')->createCommand($sql);
    				$command->bindValue(":alias_sku",$alias_sku,\PDO::PARAM_STR);
    				$pk_query_result = $command->queryAll();

    				//Existing data which need updated
    				if (count($pk_query_result)>0){
    					foreach($pk_query_result as $a_pk){
    						$pk_list[] = $a_pk[$table_row['COLUMN_NAME']];
    					}
    					//change alias to sku
    					$joinstr = implode("','",$pk_list );
    					$sql = "update  ".$table_row['TABLE_NAME']." set sku = :sku  where sku = :alias_sku and ".$table_row['COLUMN_NAME']." in ('".$joinstr."') ";
    					$command = Yii::$app->get('subdb')->createCommand($sql);
    					$command->bindValue(":sku",$sku,\PDO::PARAM_STR);
    					$command->bindValue(":alias_sku",$alias_sku,\PDO::PARAM_STR);
    					$update_result = $command->execute();
    					
    					/*???????????????????????????
    					$jrn_message =  "??????????????????  ?????????".$alias_sku."?????? ".$sku." , ??????".$update_result."?????????";
    					//write update journaled
    					$journal_id = SysLogHelper::InvokeJrn_Create("Catalog",__CLASS__, __FUNCTION__ , array($pk_list , $sku,  $alias_sku , $table_row['TABLE_NAME'], 'update' , $table_row['COLUMN_NAME'] ,$update_result,$jrn_message));
    					 
    					//mark joural id
    					$result_all[$table_row['TABLE_NAME']] = $journal_id;
    					*/
    					$isChange = true;
    				}else{
    					// none of data should be update
    					$result_all[$table_row['TABLE_NAME']] = "";
    				}
    				//**********************   Process update end    **********************//
    			}//end of delete process
    			
    		}//loop each table end
    		if ($isChange){
    			/*???????????????????????????
    			$journal_id = SysLogHelper::InvokeJrn_Create("Catalog",__CLASS__, __FUNCTION__ , array($result_all));
    			SysLogHelper::SysLog_Create("Catalog",__CLASS__, __FUNCTION__,"","$journal_id : Update Alias Related data ! ", "trace");
    			*/
    		}

    	} catch (Exception $e) {
    		return array('??????' => array($e->getMessage()));
    	}
    	 
    }//end of updateAliasRelatedData
    
    
    /**
     * +----------------------------------------------------------
     * ??????????????????????????????
     * +----------------------------------------------------------
     * @access static
     * +----------------------------------------------------------
     * @param sku 			??????SKU
     * +----------------------------------------------------------
     * @return			boolean
     * +----------------------------------------------------------
     * log			name	date					note
     * @author		lkh	  2014/10/23				?????????
     * +----------------------------------------------------------
     */
    static function deleteAllAliases($sku){
    	$aliasesList = ProductAliases::findall(['sku'=>$sku]);
    	if ( count($aliasesList )> 0){
    		foreach($aliasesList as $a){
    			$a->delete();
    		}
    		return true;
    	}else{
    		return true;
    	}
    }//end of deleteAllAliases
    
    public static function getFormOpt(){
    
    	$criteria = new CDbCriteria();
    	$criteria->order = "level asc";
    	$categorys = Category::model()->findAll($criteria);
    	$tags = Tag::model()->findAll();
    	$brands = Brand::model()->findAll();
    
    	$result = array();
    	$result['categorys'] = $categorys;
    	$result['tags'] = $tags;
    	$result['brands'] = $brands;
    	$result['suppliers'] = ProductHelper::getProductSuppliers();
    	$result['productTopSupplierInfo'] = SupplierHelper::getProductTopSupplierInfo();
    	$result['productType'] = ProductHelper::getProductType();
    	$result['productStatus'] = ProductHelper::getProductStatus();
    	$result['currency'] = CommonHelper::getCurrencyList();
    
    	return json_encode($result);
    }
    
    /**
     +----------------------------------------------------------
     * ????????????????????????
     +----------------------------------------------------------
     * @access static
     +----------------------------------------------------------
     * @param page			?????????
     * @param rows			????????????
     * @param sort			????????????
     * @param order			???????????? asc/desc
     * @param queryString	????????????
     +----------------------------------------------------------
     * @return				??????????????????
     +----------------------------------------------------------
     * log			name	date					note
     * @author		ouss	2014/02/27				?????????
     +----------------------------------------------------------
     **/
    public static function listData($page, $rows, $sort, $order, $queryString, $formatJson = true)
    {
    	$AndSql = ""; // init
    	$selectType ='';
    	if(!empty($queryString))
    	{
    		foreach($queryString as $query)
    		{
    			if($query['name']=='type'&& $query['value']=='all'){
    				$selectType ='all';
    				continue;
    			}
    			if ($query['condition'] == 'eq')
    			{
    				$AndSql .= " and ".$query['name']." ='".$query['value']."'";
    			}
    			elseif ($query['condition'] == 'in')
    			{
    				if ($query['name'] == 'alias_sku'){
    					$AndSql .= " and sku in (select sku from pd_product_aliases where alias_sku like '%".$query['value']."%') ";
    				}else{
    					$AndSql .= " and ".$query['name']." in (".$query['value'].")";
    				}
    					
    			}
    			elseif ($query['condition'] == 'notIn')
    			{
    				$AndSql .= " and ".$query['name']." not in (select sku from pd_product_aliases where alias_sku like '%".$query['value']."%') ";
    			}
    			elseif ($query['condition'] == 'like')
    			{
    				$AndSql .= " and ".$query['name']." like '%".$query['value']."%' ";
    			}
    			elseif ($query['condition'] == 'gt')
    			{
    				$AndSql .= " and ".$query['name']." >  '".$query['value']."' ";
    			}
    			elseif ($query['condition'] == 'lt')
    			{
    				$AndSql .= " and ".$query['name']." <  '".$query['value']."' ";
    			}
    			elseif ($query['condition'] == 'between')
    			{
    				$AndSql .= " and ".$query['name']." between  '".$query['valueStart']."' and '".$query['valueEnd']."' ";
    			}
    		}
    	}
    
    	$sql = "select * from pd_product where 1 = 1 ";
    	$command = Yii::$app->get('subdb')->createCommand("select count(1) ct from ($sql $AndSql) a ");
    
    	$result['total'] = $command->queryScalar();
    	//$command->limit = $rows;
    	//$command->offset = ($page-1) * $rows;
    	//$command->order = "$sort $order";//????????????
    	$sql .=$AndSql;
    	if($selectType ==''){
    		$sql .=" and type in ('S','L')";//??????????????????
    	}
    	$sql .=" order by $sort $order ";
    	$sql .=" limit ".($page-1) * $rows." , ".$page * $rows;
    	$command = Yii::$app->get('subdb')->createCommand($sql);
    
    	$result['rows'] = $command->queryAll();
    
    	if ($formatJson) {
    		return json_encode ( $result );
		} else {
			return $result;
		}
	}
	
	/**
	 * +----------------------------------------------------------
	 * ??????????????????
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	model		????????????
	 *+----------------------------------------------------------
	 * @return ???????????????true,???????????????????????? +----------------------------------------------------------
	 *         log			name	date					note
	 * @author ouss	2014/02/27				?????????
	 *+----------------------------------------------------------
	 *        
	 */
	public static function deleteProduct($sku) {
		$transaction = Yii::$app->get('subdb')->beginTransaction ();
		try {
			$del_condition = ['sku'=> $sku ];
			// del product_aliases
			ProductAliases::deleteAll($del_condition);
			
			// del product_tags
			ProductTags::deleteAll($del_condition);
			
			// del product_suppliers
			ProductSuppliers::deleteAll ( $del_condition );
			
			// del product photo
			// Photo::model()->deleteAll($criteria);
			
			// del config_relationship & change children's type
			self::removeConfigRelationship($sku, 'cfsku');
			self::removeConfigRelationship($sku, 'assku');

			/*
			$sql = "select cfsku, GROUP_CONCAT(assku) asskulist from pd_product_config_relationship where cfsku ='$sku' group by cfsku ";
			$command = Yii::$app->get('subdb')->createCommand ($sql);
			$C_relationship = $command->queryAll ();
			if (count ( $C_relationship ) > 0) {
				
				foreach ( $C_relationship as $agroup ) {
					ProductConfigRelationship::deleteAll( array ('cfsku' => $agroup ['cfsku']) );
					$asskuArray = explode ( ',', $agroup ['asskulist'] );
					if (! $asskuArray)
						$asskuArray = array (
								$agroup ['asskulist'] 
						);
					foreach ( $asskuArray as $assku ) {
						$childrenmodel = Product::findOne(['sku'=>$assku]) ;
						if ($childrenmodel !== null) {
							$childrenmodel->type = 'S';
							$childrenmodel->save ();
						}
					}
				}
			}
			*/
			
			// del bundle_relationship
			
			self::removeBundleRelationship($sku, 'bdsku');
			self::removeBundleRelationship($sku, 'assku');
			/*
			$sql = "select bdsku from pd_product_bundle_relationship where bdsku = '$sku'";
			$command = Yii::$app->get('subdb')->createCommand ($sql);
			$bundleFarter = $command->queryAll ();
			if (count ( $bundleFarter ) > 0) {
				foreach ( $bundleFarter as $agroup ) {
					ProductBundleRelationship::deleteAll ( array (
							'bdsku' => $agroup ['bdsku'] 
					) );
				}
			} // end of (if sku is bundle farter_sku)
			$sql = "select assku from pd_product_bundle_relationship where assku='$sku' ";
			$command = Yii::$app->get('subdb')->createCommand ($sql);
			$bundleChildren = $command->queryAll ();
			if (count ( $bundleChildren ) > 0) {
				foreach ( $bundleChildren as $agroup ) {
					ProductBundleRelationship::deleteAll (  array (
							'assku' => $agroup ['assku'] 
					) );
				}
			} // end of (if sku is bundle child_sku)
			*/
			  // del bundle_relationship end
			  
			// del product
			Product::deleteAll ( $del_condition );
			$transaction->commit ();
			
			return true;
		} catch ( Exception $e ) {
			$transaction->rollBack ();
			return false;
		}
	}
	
	/**
	 * +----------------------------------------------------------
	 * ??????????????????
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	sku		??????SKU
	 *+----------------------------------------------------------
	 * @return ???????????? +----------------------------------------------------------
	 *         log			name	date					note
	 * @author ouss	2014/02/27				?????????
	 *+----------------------------------------------------------
	 *        
	 */
	public static function getProductBySku($sku) {
		
		
		return Product::findOne(['sku'=>$sku]);
	}
	
	/**
	 * +----------------------------------------------------------
	 * ???????????????????????????
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @return ?????????????????? 
	 * +----------------------------------------------------------
	 *         log			name	date					note
	 * @author ouss	2014/02/27				?????????
	 *+----------------------------------------------------------
	 *        
	 */
	public static function getExportProductData($skuList) {
		$products = array ();
		if (count ( $skuList ) > 0) {
			$criteria = new CDbCriteria ();
			$criteria->addInCondition ( 'sku', $skuList );
			$products = Product::findAll ( $criteria );
		} else {
			$products = Product::findAll ();
		}
		$productArray = array ();
		foreach ( $products as $item ) {
			$sku = $item->sku;
			$alias = ProductAliases::findAllByAttributes ( array (
					'sku' => $sku 
			) );
			$tags = Tag::findAllBySql ( "SELECT * FROM `pd_tag` where `tag_id` in (SELECT `tag_id` FROM `pd_product_tags` where sku ='$sku')" );
			$productSuppliers = ProductSuppliers::findAllByAttributes ( array (
					'sku' => $sku 
			) );
			$photos = PhotoHelper::getPhotosBySku ( $sku, 'OR' );
			$product = $item->attributes;
			$aliasArray = array ();
			foreach ( $alias as $a ) {
				$aliasArray [] = $a->alias_sku;
			}
			$tagArray = array ();
			foreach ( $tags as $t ) {
				$tagArray [] = $t->tag_name;
			}
			$product ['alias'] = implode ( ',', $aliasArray );
			$product ['tags'] = implode ( ',', $tagArray );
			$product ['suppliers'] = json_encode ( $productSuppliers );
			$product ['photos'] = implode ( ',', $photos );
			$productArray [] = $product;
		}
		
		return $productArray;
	}
	protected static $EXCEL_PRODUCT_COLUMN_MAPPING = array (
			"A" => "sku", //SKU
			"B" => "name", // ????????????
			"C" => "category_name", // ?????? * ????????????id
			"D" => "brand_name", // ?????? * ????????????id
			"E" => "prod_name_ch", // ??????????????????
			"F" => "prod_name_en", // ??????????????????
			"G" => "declaration_ch", // ???????????????
			"H" => "declaration_en", // ???????????????
			"I" => "declaration_value",//????????????
			"J" => "declaration_value_currency",//????????????
			"K" => "prod_weight", // ????????????(g)
			"L" => "prod_length", // ????????????(???cm)
			"M" => "prod_width", // ????????????(???cm)
			"N" => "prod_height", // ????????????(???cm)
			"O" => "supplier_name", // ??????????????? * ????????????id
			"P" => "purchase_price", // ?????????(CNY)
			"Q" => "photo_primary", // ?????????
			// photo_others_* ???????????????????????????join(separator,array) ??? 'photo_others'?????????
			"R" => "photo_others_2", // ??????2
			"S" => "photo_others_3", // ??????3
			"T" => "photo_others_4", // ??????4
			"U" => "photo_others_5", // ??????5
			"V" => "photo_others_6", // ??????6
			"W" => "status_cn", // ???????????? * ????????????code
			"X" => "prod_tag",//????????????,???????????????is_has_tag,???????????????'pd_prodcut_tags'??????
			"Y" => "alias",//????????????,?????????alias???
			"Z" => "declaration_code",//?????????
			"AA"=> "purchase_link",//????????????
	
	);
	
	protected static $SELLERTOOL_EXCEL_PRODUCT_COLUMN_MAPPING = array (
			"A" => "sku", //SKU
			"B" => "name", // ????????????
			"C" => "prod_weight", // ????????????(g)
			"D" => "prod_length", // ????????????(???cm)
			"E" => "prod_width", // ????????????(???cm)
			"F" => "prod_height", // ????????????(???cm)
			"G" => "declaration_ch", // ???????????????
			"H" => "declaration_en", // ???????????????
			
			
			"I" => "declaration_value",//????????????
			"J" => "purchase_price", // ?????????(CNY)
			"K" => "prod_tag",//????????????,???????????????is_has_tag,???????????????'pd_prodcut_tags'??????
			"L" => "photo_primary", // ?????????
			"Y" => "alias",//????????????,?????????alias???
			
	);
	
	
	protected static $SELLERTOOL_EXCEL_BUNDLE_PRODUCT_COLUMN_MAPPING = array (
			"A" => "sku", //SKU
			"B" => "bundlesku", // ????????????
	);
	
	protected static $EXCEL_PRODUCT_ENNAME_CNNAME_MAPPING = array (
	        //equal??????????????????like???????????????
			"sku" => [
	                "sku"=>"equal", 
	                "SKU(??????)"=>"equal"], //SKU
			"name" => [
	                "????????????"=>"like"], // ????????????
			/*"category_name" => [
	                "??????"=>"like"], // ?????? * ????????????id */
			"brand_name" => [
	                "??????"=>"like"], // ?????? * ????????????id
			"prod_name_ch" => [
	                "??????????????????"=>"like"], // ??????????????????
			"prod_name_en" => [
	                "??????????????????"=>"like"], // ??????????????????
			"declaration_ch" => [
	                "???????????????"=>"like"], // ???????????????
			"declaration_en" => [
	                "???????????????"=>"like"], // ???????????????
			"declaration_value" => [
	                "????????????"=>"like",
	                "????????????"=>"like"],//????????????
			"declaration_value_currency" => [
	                "????????????"=>"like",
	                "????????????"=>"like"],//????????????
			"prod_weight" => [
	                "??????"=>"like"], // ????????????(g)
			"prod_length" => [
	                "???"=>"like"], // ????????????(???cm)
			"prod_width" => [
	                "???"=>"like"], // ????????????(???cm)
			"prod_height" => [
	                "???"=>"like"], // ????????????(???cm)
			"supplier_name" => [
	                "?????????"=>"like"], // ??????????????? * ????????????id
			"purchase_price" => [
	                "?????????"=>"like"], // ?????????(CNY)
			"photo_primary" => [
	                "?????????"=>"like"], // ?????????
			// photo_others_* ???????????????????????????join(separator,array) ??? 'photo_others'?????????
			"photo_others_2" => [
	                "??????2"=>"like"], // ??????2
			"photo_others_3" => [
	                "??????3"=>"like"], // ??????3
			"photo_others_4" => [
	                "??????4"=>"like"], // ??????4
			"photo_others_5" => [
	                "??????5"=>"like"], // ??????5
			"photo_others_6" => [
	                "??????6"=>"like"], // ??????6
			"status_cn" => [
	                "????????????"=>"like"], // ???????????? * ????????????code
			"prod_tag" => [
	                "??????"=>"like"],//????????????,???????????????is_has_tag,???????????????'pd_prodcut_tags'??????
			"alias" => [
	                "??????"=>"like"],//????????????,?????????alias???
			"declaration_code" => [
	                "????????????"=>"like",
	                "?????????"=>"like"],//?????????
			"purchase_link"=> [
	                "????????????"=>"like"],//????????????
	        "assku_list"=> [
	                "?????????SKU"=>"like"],//?????????SKU,?????????bundle???
	        "father_sku"=> [
	            "?????????sku"=>"like"],//???????????????SKU
	        "attribute1"=> [
	            "??????1??????"=>"like",
	            "????????????"=>"like"],//??????SKU
	        "value1"=> [
	            "??????1??????"=>"like",
	            "????????????"=>"like"],//??????SKU
	        "attribute2"=> [
	            "??????2??????"=>"like"],//??????SKU
	        "value2"=> [
	            "??????2??????"=>"like"],//??????SKU
	        "attribute3"=> [
	            "??????3??????"=>"like"],//??????SKU
	        "value3"=> [
	            "??????3??????"=>"like"],//??????SKU
			"class_name"=> [
				"??????"=>"like"],//?????? * ????????????id
	
	);
	
	/**
	 * +----------------------------------------------------------
	 * ??????????????????????????? 
	 * +----------------------------------------------------------
	 *
	 * @access static
	 *+----------------------------------------------------------
	 * @param	na
	 *+----------------------------------------------------------
	 * @return ??????????????????????????? 
	 * +----------------------------------------------------------
	 * log			name	date					note
	 * @author 		lkh		2015/03/27				?????????
	 *+----------------------------------------------------------
	 *
	 */
	public static function get_EXCEL_PRODUCT_COLUMN_MAPPING(){
		return self::$EXCEL_PRODUCT_COLUMN_MAPPING;
	}//end of get_EXCEL_PRODUCT_COLUMN_MAPPING
	
	/**
	 * +----------------------------------------------------------
	 * ???????????????????????????
	 * +----------------------------------------------------------
	 *
	 * @access static
	 *+----------------------------------------------------------
	 * @param	
	 *+----------------------------------------------------------
	 * @return ???????????????????????????
	 * +----------------------------------------------------------
	 * log			name	date					note
	 * @author 		lrq		2016/12/22				?????????
	 *+----------------------------------------------------------
	 *
	 */
	public static function get_EXCEL_PRODUCT_ENNAME_CNNAME_MAPPING(){
		return self::$EXCEL_PRODUCT_ENNAME_CNNAME_MAPPING;
	}//end of get_EXCEL_PRODUCT_ENNAME_CNNAME_MAPPING
	
	/**
	 * +----------------------------------------------------------
	 * ?????????????????????????????????
	 * +----------------------------------------------------------
	 *
	 * @access static
	 *+----------------------------------------------------------
	 * @param	na
	 *+----------------------------------------------------------
	 * @return ???????????????????????????
	 * +----------------------------------------------------------
	 * log			name	date					note
	 * @author 		lkh		2016/03/16				?????????
	 *+----------------------------------------------------------
	 *
	 */
	public static function get_SELLERTOOL_EXCEL_PRODUCT_COLUMN_MAPPING(){
		return self::$SELLERTOOL_EXCEL_PRODUCT_COLUMN_MAPPING;
	}//end of get_EXCEL_PRODUCT_COLUMN_MAPPING
	
	/**
	 * +----------------------------------------------------------
	 * ???????????????????????????????????????
	 * +----------------------------------------------------------
	 *
	 * @access static
	 *+----------------------------------------------------------
	 * @param	na
	 *+----------------------------------------------------------
	 * @return ???????????????????????????
	 * +----------------------------------------------------------
	 * log			name	date					note
	 * @author 		lkh		2016/03/16				?????????
	 *+----------------------------------------------------------
	 *
	 */
	public static function get_SELLERTOOL_EXCEL_BUNDLE_PRODUCT_COLUMN_MAPPING(){
		return self::$SELLERTOOL_EXCEL_BUNDLE_PRODUCT_COLUMN_MAPPING;
	}//end of get_EXCEL_PRODUCT_COLUMN_MAPPING
	
	/**
	 * +----------------------------------------------------------
	 * ??????????????????sku ????????????
	 * +----------------------------------------------------------
	 *
	 * @access static
	 *+----------------------------------------------------------
	 * @param	na
	 *+----------------------------------------------------------
	 * @return ???????????????????????????
	 * +----------------------------------------------------------
	 * log			name	date					note
	 * @author 		lkh		2016/03/16				?????????
	 *+----------------------------------------------------------
	 *
	 */
	public static function explodeSELLTOOLBundleProduct($bundleSKU){
		$sku_qty_list = explode('+', $bundleSKU);
		$rt = [];
		foreach($sku_qty_list as $sku_qty){
			
			$prodInfo = explode("*", $sku_qty);
			$rt[trim($prodInfo[0])] = trim($prodInfo[1]);
			
			
			
		}
		return $rt;
	}//end of explodeSELLTOOLBundleProduct
	
	/**
	 * +----------------------------------------------------------
	 * ?????????????????????
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	file		??????????????????
	 *+----------------------------------------------------------
	 * @return ?????????????????????????????? 
	 * +----------------------------------------------------------
	 *         log			name	date					note
	 * @author ouss	2014/02/27				?????????
	 *+----------------------------------------------------------
	 *        
	 */
	public static function importProductData($productsData, $itype='S') {
		global $CACHE;
		$result = array ();
		$result['error']='';
		$successInsertQty = 0;
		$successUpdateQty = 0;
		$failQty = 0;
		$allQty = 0;
		$edit_log = '';
		$add_log = '';
		//$productsData = ExcelHelper::importProductExcel ( $file, self::$EXCEL_PRODUCT_COLUMN_MAPPING, true );
	
		//????????????????????????
		ProductAliases::deleteAll(" `sku` NOT IN (SELECT `sku` FROM `pd_product` WHERE 1)");
		if (is_array ( $productsData )) {
				
			$allBrandInfo = Brand::findAll(['1'=>'1']);
			$brandNameIdMapping = array ();
			$allBrandName = array ();
				
			foreach ( $allBrandInfo as $brandInfo ) {
				$allBrandName [] = $brandInfo ['name'];
				$brandNameIdMapping [$brandInfo ['name']] = $brandInfo ['brand_id'];
			}
			
			$pdAlias = ProductAliases::find()->select("alias_sku")->where("sku!=alias_sku")->asArray()->All();
			$aliasInDb = array();
			foreach ($pdAlias as $r){
				$aliasInDb[]=$r['alias_sku'];
			}
			
			//??????????????????
			$exportCol = array();
			if(!empty($productsData)){
			    foreach ($productsData as $p){
    			    $exportCol = array_keys($p);
    			    break;
			    }
			}
			
			$importAlias = array();//?????????alias??????????????????
			$sameAlias = array();//excel??????????????????alias
			$existingAlias = array();//db???????????????alias
			$alias_EQ_importSku = array();//???????????????alias???????????????????????????sku
			$alias_EQ_existingSku = array();//???????????????alias??????db???????????????sku

			// ???????????????????????????????????? sku ??? record ??????????????????????????????
			$allProdSku = array ();      //?????????????????????SKU
			$allProdSkuUp = array ();    //?????????????????????SKU??????????????????
			$excel_alias = array();      //excel???????????? 
			$insertSkuList=array();
			$sameSkuInfo = array ();
			$aliasOk = true;
			$notExistAssku = false;    //?????????????????????????????????????????????assku_list???
			$is_not_sku = false;       //????????????????????????sku???
			$not_Exist_attribute = false;    //????????????????????????????????????????????????????????????
			$not_Exist_father_sku = false;    //??????????????????????????????????????????????????????SKU???
			
			//????????????sku???
			if(!in_array('sku',$exportCol)){
				$aliasOk = false;
				$is_not_sku = true;
			}
			else{
    			foreach ( $productsData as $key => &$item ) {
    				//??????SKU??????????????????tab
    				$item ['sku'] = str_replace('\r', '', $item ['sku']);
    				$item ['sku'] = str_replace('\n', '', $item ['sku']);
    				$item ['sku'] = str_replace('\t', '', $item ['sku']);
    				$item ['sku'] = str_replace(chr(10), '', $item ['sku']);
    				//??????SKU??????????????????
    				$item ['sku'] = trim($item ['sku']);
    				
    				if ($item ['sku'] != '') {
    					if (in_array ( strtoupper($item ['sku']), $allProdSkuUp )) {
    						$sameSkuInfo [$item ['sku']] [] = $key;
    					}
    					else {
    						$allProdSku [] = $item ['sku'];
    						$allProdSkuUp[] = strtoupper($item ['sku']);
    					}
    				}
    				
    				//???????????????alias???????????????
    				if(in_array('alias',$exportCol)){
        				if(trim($item['alias'])!=''){
        					$item['alias'] = trim($item['alias']);
        					$aa = explode(',', $item['alias']);
        					
        					//?????????SKU??????????????????
        					$alias = array();
        					$palias = ProductAliases::find()->select(['alias_sku'])->where(['sku'=>$item ['sku']])->asArray()->All();
        					foreach ($palias as $a){
        					    $alias[] = $a['alias_sku'];
        					}
        					foreach ($aa as $k => $a){
        					    $a = trim($a);
        					    
        					    if(empty($a)){
        					        unset($aa[$k]);
        					    }
        					    if (in_array ( $a, $alias )) {
        					        unset($aa[$k]);
        					    }
        					}
        					
        					foreach ($aa as $a){
        					    $a = trim($a);
        					    
        						if (in_array ( $a, $importAlias )) {
        							$sameAlias [$a] [] = $key;
        							$aliasOk = false;
        						}
        						else {
        							$importAlias [] = $a;
        						}
        						if(in_array ( $a, $aliasInDb )){
        							$aliasOk = false;
        							$existingAlias[$a][]=$key;
        						}
        						
        						$excel_alias[] = $a;
        					}
        					if($aliasOk)
        						$productsData[$key]['alias'] = $aa;
        				}
    				}
    			}
    			
    			//???????????????alias???????????????
    			if(in_array('alias',$exportCol)){
        			foreach ($importAlias as $alias){
        				if(in_array($alias,$allProdSku ) || in_array(strtoupper($alias),$allProdSkuUp )){
        					$alias_EQ_importSku[] = $alias;
        					$aliasOk = false;
        				}
        			}
        			
        			$aliasIsSku = Product::find()->where(['in','sku',$importAlias])->asArray()->all();
        			if(!empty($aliasIsSku) ){
        				foreach ($aliasIsSku as $k=>$pd){
        					$alias_EQ_existingSku[]=$pd['sku'];
        					$aliasOk = false;
        				}
        			}
    			}
    	
    			$brandAll=Brand::find()->select(['brand_id','name'])->where('brand_id<>0')->asArray()->all();
    			foreach ($brandAll as $aBrand){
    				$CACHE['brandInfo'][$aBrand['name']]=$aBrand['brand_id'];
    			}
    	
    			$supplierAll=Supplier::find()->select(['supplier_id','name'])->where('supplier_id<>0')->asArray()->all();
    			foreach ($supplierAll as $aSupplier){
    				$CACHE['supplierInfo'][$aSupplier['name']]=$aSupplier['supplier_id'];
    			}
    			
    			//????????????????????????????????????assku_list??????????????????SKU??????
    			if($itype == 'B'){
    				if(!in_array('assku_list',$exportCol)){
    				    $aliasOk = false;
    				    $notExistAssku = true;
    				}
    			}
    			
    			//???????????????????????????????????????????????????
    			if($itype == 'L'){
    				if(!in_array('attribute1',$exportCol) || !in_array('value1',$exportCol)){
    					$aliasOk = false;
    					$not_Exist_attribute = true;
    				}
    				else if(!in_array('father_sku',$exportCol)){
    					$aliasOk = false;
    					$not_Exist_father_sku = true;
    				}
    				
    				//??????????????????
    				$field_list = array();
    				$productField = ProductField::find()->select(['id', 'field_name'])->asArray()->all();
    				foreach ($productField as $p){
    				    if(!array_key_exists(strtolower($p['field_name']), $field_list)){
    				        $field_list[strtolower($p['field_name'])] = $p['id'];
    				    }
    				}
    			}
			}
			
			if (empty ( $sameSkuInfo ) && $aliasOk) {
				$prodsDatas['supplierInfo'] = array();
				$prodsDatas['info'] = array();
				$prodsDatas['tags'] = array();
				$prodsDatas['photos'] = array();
				$prodsDatas['prod_tag'] = array();
				$prodsDatas['alias'] = array();
				if(isset($item)) unset($item);
				foreach ( $productsData as $key => $item ) {
				    $allQty++;
					//$current_time=explode(" ",microtime());//test liang
					//$step3_a_1_time=round($current_time[0]*1000+$current_time[1]*1000);//test liang
					//\Yii::info(['catalog', __CLASS__, __FUNCTION__,'Background',"prodHelper foreach validate used time:".($step2_time-$step1_time)],"edb\global");//test liang
	
					$validate = true;
						
					if (empty ( $item ['sku'] )) {
						$result [$key] [] = " ?????? sku ????????? ";
						$validate = false;
					} else {
						// ?????? sku ??????
						/* 2015-5-19 khcomment start sku ??????????????????
							$pattern = '/^[0-9A-Za-z_-]+$/';
						if (! preg_match ( $pattern, $item ['sku'] )) {
						$validate = false;
						$result [$key] [] = ' SKU???????????????,???????????????????????? ';
						} else
							2015-5-19 khcomment end */
						if (mb_strlen ( $item ['sku'], 'utf-8' ) > 255) {
							$result [$key] [] = " SKU?????? (???????????? 255 ?????????) ";
							$validate = false;
						}
					}
						
					//?????????SKU???????????????
					if (empty ( $item ['sku'] )) {
						$result [$key] [] = " ?????? sku ????????? ";
						$result [$key] ['insert'] = false;
						$failQty++;
						continue;
					}
						
					if(in_array ($item ['sku'], $aliasInDb )){
						$result [$key] [] = " ???SKU?????????????????????????????? ";
						$result [$key] ['insert'] = false;
						$failQty++;
						continue;
					}
					
					$tmpP = self::getProductBySku ( $item ['sku'] );
					//$isUpdate = true;
					if (! $tmpP) {
						//$tmpP = new Product ();
						$isUpdate = false;
					} else {
						//$result [$key] [] = " ??????'" . $item ['sku'] . "'????????? ";
						$isUpdate = true;
					}
					if(isset($tmpProData)) unset($tmpProData);
					$tmpProData = array ();
					
					//???????????????????????????
					if(!$isUpdate){
					    $item ['type'] = $itype;
					}
					// ?????????????????????????????????'??????'
					//$item ['type'] = array_search ( '??????', self::getProductType () );
						
					if (!empty($item ['status_cn']))
						$item ['status'] = array_search ( $item ['status_cn'], self::getProductStatus () );
					else
						$item ['status'] = array_search ( '??????', self::getProductStatus () );
					unset($item['status_cn']);
					
					if(in_array('prod_weight',$exportCol)){
					    $item ['prod_weight'] = empty($item['prod_weight'])? 0 : round ( floatval($item ['prod_weight']) );
					}
					if(in_array('prod_length',$exportCol)){
					    $item ['prod_length'] = empty($item['prod_length'])? 0 : round ( floatval($item ['prod_length']) );
					}
					if(in_array('prod_width',$exportCol)){
					    $item ['prod_width'] = empty($item['prod_width'])? 0 : round ( floatval($item ['prod_width']) );
					}
					if(in_array('prod_height',$exportCol)){
					    $item ['prod_height'] = empty($item['prod_height'])? 0 : round ( floatval($item ['prod_height']) );
					}
						
					// ??????????????????????????????????????????
					if(!$isUpdate || in_array('name',$exportCol)){
    					if (! isset ( $item ['name'] ) || ! $item ['name']) {
    						$result [$key] [] = " ????????????????????? ";
    						$validate = false;
    					}
    					else if (mb_strlen ( $item ['name'], 'utf-8' ) > 250) {
    						$result [$key] [] = " ?????????????????? (???????????? 250 ?????????) ";
    						$validate = false;
    					}
					}
					
					// ????????????????????????????????????????????????
					if(!$isUpdate || in_array('prod_name_ch',$exportCol)){
    					if (! isset ( $item ['prod_name_ch'] ) || ! $item ['prod_name_ch']) {
    						$result [$key] [] = " ??????????????????????????? ";
    						$validate = false;
    					} 
    					else if (mb_strlen ( $item ['prod_name_ch'], 'utf-8' ) > 250) {
    						$result [$key] [] = " ???????????????????????? (???????????? 250 ?????????) ";
    						$validate = false;
    					}
					}
					
					// ????????????????????????????????????????????????
					if(!$isUpdate || in_array('prod_name_en',$exportCol)){
    					if (! isset ( $item ['prod_name_en'] ) || ! $item ['prod_name_en']) {
    						$result [$key] [] = " ??????????????????????????? ";
    						$validate = false;
    					} 
    					else if (mb_strlen ( $item ['prod_name_en'], 'utf-8' ) > 250) {
    						$result [$key] [] = " ???????????????????????? (???????????? 250 ?????????) ";
    						$validate = false;
    					}
					}
						
					// ????????????????????????????????????????????????
					if(!$isUpdate || in_array('declaration_ch',$exportCol)){
    					if (! isset ( $item ['declaration_ch'] ) || ! $item ['declaration_ch']) {
    						$result [$key] [] = " ??????????????????????????? ";
    						$validate = false;
    					} else if (mb_strlen ( $item ['declaration_ch'], 'utf-8' ) > 100) {
    						$result [$key] [] = " ???????????????????????? (???????????? 100 ?????????) ";
    						$validate = false;
    					}
					}
					
					// ????????????????????????????????????????????????
					if(!$isUpdate || in_array('declaration_en',$exportCol)){
    					if (! isset ( $item ['declaration_en'] ) || ! $item ['declaration_en']) {
    						$result [$key] [] = " ??????????????????????????? ";
    						$validate = false;
    					} else if (mb_strlen ( $item ['declaration_en'], 'utf-8' ) > 100) {
    						$result [$key] [] = " ???????????????????????? (???????????? 100 ?????????) ";
    						$validate = false;
    					}
					}
					
					if(!empty($tmpP['addi_info'])){
						$addi_info = json_decode($tmpP['addi_info'], true);
					}
					else {
						$addi_info = [];
					}
					if(empty($addi_info['commission_per'])){
						$addi_info['commission_per'] = [];
					}
					//??????????????????,addi_info
					foreach($item as $col => $col_val){
						if(strpos($col, 'commission_per_') !== false){
							$platname = str_replace('commission_per_', '', $col);
							if(!empty($col_val)){
								$addi_info['commission_per'][$platname] = $col_val;
							}
							else{
								unset($addi_info['commission_per'][$platname]);
							}
							
							unset($item[$col]);
						}
					}
					if(!empty($addi_info['commission_per'])){
						$item['addi_info'] = json_encode($addi_info);
					}
					else{
						$item['addi_info'] = '';
					}
					
					//????????????????????????
					if($itype == 'B'){
					    //?????????????????????????????????????????????
					    if($isUpdate && $tmpP['type'] != 'B'){
					        $result [$key] [] = " ".$item['sku']."??????????????????????????????????????? ";
					        $validate = false;
					    }
					    else{
    					    //assku_list????????????
    					    if( empty($item['assku_list'])){
    					        $result [$key] [] = " ?????????SKU????????? ??? ??? 0 ";
    					        $validate = false;
    					    }
    					    else{
    					        $skus = array();
    					        $assku_list = array();
    					        $item['assku_list'] = rtrim($item['assku_list'],';');
    					        $arr = explode(';', $item['assku_list']);
    					        foreach ($arr as $a){
    					            if(!empty($a)){
        					            $val = explode('=', $a);
        					            $assku['bdsku'] = $item['sku'];
        					            $assku['assku'] = trim($val[0]);
        					            if(count($val)>1 && is_numeric(trim($val[1]))){
        					                $assku['qty'] = trim($val[1]);
        					            }
        					            else{
        					                $assku['qty'] = 1;
        					            }
        					            
        					            $skus[] = $assku['assku'];
        					            $assku_list[] = $assku;
    					            }
    					        }
    					        
    					        //??????????????????????????????
    					        $not_exist_sku_str = '';
    					        $exist_skus = array();
    					        $pro = Product::find()->select('sku')->where(['sku'=>$skus, 'type'=>['S', 'L']])->asArray()->all();
    					        foreach ($pro as $p){
    					            $exist_skus[] = $p['sku'];
    					        }
    					        foreach ($skus as $s){
    					            if(!in_array($s, $exist_skus)){
    					                $not_exist_sku_str .= $s.'???';
    					            }
    					        }
    					        $not_exist_sku_str = rtrim($not_exist_sku_str, '???');
    					        if(!empty($not_exist_sku_str)){
    					            $result [$key] [] = " ?????????SKU???".$not_exist_sku_str."???????????????????????????????????? ??? ??????????????? ! ";
    					            $validate = false;
    					        }
    					        else{
    					            //??????????????????????????????????????????SKU
    					            $bd_exist_sku = array();
    					            $bundle = ProductBundleRelationship::find()->select('assku')->where(['bdsku'=>$item['sku'], 'assku'=>$skus])->asArray()->all();
    					            foreach ($bundle as $b){
    					            	$bd_exist_sku[] = $b['assku'];
    					            }
    					            foreach ($assku_list as $s){
    					            	if(in_array($s['assku'], $exist_skus) && !in_array($s['assku'], $bd_exist_sku)){
    					            		$prodsDatas['assku_list'][] = $s;
    					            	}
    					            }
    					        }
    					    }
					    }
					}
					if(in_array('assku_list',$exportCol)){
						unset($item['assku_list']);
					}
					
					//????????????????????????
					$father_sku = '';
					$attribute_list = array();
					$config = array();
					if($itype == 'L'){
					    //????????????????????????????????????????????????
					    if($isUpdate && $tmpP['type'] != 'L'){
					    	$result [$key] [] = " ".$item['sku']."?????????????????????????????????????????? ";
					    	$validate = false;
					    }
					    else{
					        //??????SKU??????????????????tab
					        $item ['father_sku'] = str_replace('\r', '', $item ['father_sku']);
					        $item ['father_sku'] = str_replace('\n', '', $item ['father_sku']);
					        $item ['father_sku'] = str_replace('\t', '', $item ['father_sku']);
					        $item ['father_sku'] = str_replace(chr(10), '', $item ['father_sku']);
					        //??????SKU??????????????????
					        $item ['father_sku'] = trim($item ['father_sku']);
					        
    					    if( empty($item['father_sku'])){
    					    	$result [$key] [] = " ?????????SKU????????? ??? ??? 0 ";
    					    	$validate = false;
    					    }
    					    if( strtoupper($item['sku']) == strtoupper($item ['father_sku'])){
    					    	$result [$key] [] = " ?????????????????????SKU???????????? ";
    					    	$validate = false;
    					    }
    					    if( empty($item['attribute1']) || !isset($item['value1'])){
    					    	$result [$key] [] = " ??????1???????????????1??????????????? ";
    					    	$validate = false;
    					    }
    					    if(in_array ($item ['father_sku'], $aliasInDb )){
    					    	$result [$key] [] = " ????????????SKU?????????????????????????????? ";
    					    	$validate = false;
    					    }
    					    if(in_array ($item ['father_sku'], $excel_alias )){
    					    	$result [$key] [] = " ????????????SKU???Excel?????????????????? ";
    					    	$validate = false;
    					    }
    					    
    					    $other_attributes = $item['attribute1'] .':'. $item['value1'];
    					    $attribute_list[] = $item['attribute1'];
    					    if( !empty($item['attribute2']) && isset($item['value2'])){
    					    	if($other_attributes != '')
    					    		$other_attributes = $other_attributes .';';
    					    	$other_attributes .= $item['attribute2'] .':'. $item['value2'];
    					    	$attribute_list[] = $item['attribute2'];
    					    }
    					    if( !empty($item['attribute3']) && isset($item['value3'])){
    					    	if($other_attributes != '')
    					    		$other_attributes = $other_attributes .';';
    					    	$other_attributes .= $item['attribute3'] .':'. $item['value3'];
    					    	$attribute_list[] = $item['attribute3'];
    					    }
    					    $item['other_attributes'] = $other_attributes;
    					    
    					    //????????????????????????????????????????????????
    					    if(!$isUpdate){
        						//???????????????SKU?????????????????????????????????
        						$father = self::getProductBySku ($item['father_sku']);
        						if(empty($father)){
        						    $father_sku = $item['father_sku'];
        						}
    						
        						if(!empty($father['type']) && $father['type'] != 'C'){
        							$result [$key] [] = " ".$father['sku']."????????????????????????????????????????????????????????? ";
        							$validate = false;
        						}
        						
    							//?????????????????????????????????
    							$config['cfsku'] = $item['father_sku'];
    							$config['assku'] = $item['sku'];
    							$config['create_date'] = date('Y-m-d H:i:s', time());
    							$config['config_field_ids'] = '';
    								
    							//???????????????????????????????????????????????????????????????????????????config_field_ids??????
    							$product_config = ProductConfigRelationship::find()->where(['cfsku'=>$item['father_sku']])->asArray()->one();
    							if(!empty($product_config)){
    								$config['config_field_ids'] = $product_config['config_field_ids'];
    							}
    							else{
    								//????????????
    								foreach ($attribute_list as $a){
    									if(array_key_exists(strtolower($a), $field_list)){
    										$config['config_field_ids'] = empty($config['config_field_ids']) ? $field_list[$a] : $config['config_field_ids'].','.$field_list[$a];
    									}
    									else{
    										$fieldModel = new ProductField();
    										$fieldModel->field_name = $a;
    										$fieldModel->use_freq = 1;
    										$fieldModel->save(false);
    											
    										$field = ProductField::findOne(['field_name'=>$a]);
    										if(!empty($field)){
        										$field_list[$a] = $field->id;
        										$config['config_field_ids'] = empty($config['config_field_ids']) ? $field->id : $config['config_field_ids'].','.$field->id;
    										}
    									}
    								}
    							}
    					    }
					    }
					}
					
					if(in_array('attribute1',$exportCol)){
						unset($item['attribute1']);
					}
					if(in_array('value1',$exportCol)){
						unset($item['value1']);
					}
					if(in_array('attribute2',$exportCol)){
						unset($item['attribute2']);
					}
					if(in_array('value2',$exportCol)){
						unset($item['value2']);
					}
					if(in_array('attribute3',$exportCol)){
						unset($item['attribute3']);
					}
					if(in_array('value3',$exportCol)){
						unset($item['value3']);
					}
					if(in_array('father_sku',$exportCol)){
						unset($item['father_sku']);
					}
						
					// 20141124 ??????????????? ??????????????????,???????????????level: 1 ,parent 0 ????????? , ?????????????????????????????????
					// ??????
					/*
						if (isset ( $item ['category_name'] ) && $item ['category_name']) {
					$productCategory = Category::find ( 'name=:name', array (
							':name' => $item ['category_name']
					) );
					if (! $productCategory) {
					$productCategory = new Category ();
					$productCategory->name = $item ['category_name'];
					$productCategory->level = 1;
					$productCategory->parent_id = 0;
					$productCategory->comment = "??????????????????";
					$productCategory->create_time = date ( 'Y-m-d H:i:s', time () );
					$productCategory-> = Yii::app ()->muser->getId ();
					if ($productCategory->save ()) {
					$item ['category_id'] = $productCategory->category_id;
					} else {
					$result [$key] [] = " ?????? '" . $item ['category_name'] . "' ????????????  ";
					$validate = false;
					}
					} else if ($productCategory->has_children) {
					$result [$key] [] = " ?????? '" . $item ['category_name'] . "' ???????????????????????? ";
					$validate = false;
					} else {
					$item ['category_id'] = $productCategory->category_id;
					}
					}
					*/
					unset($item['category_name']);
					// ??????
					if(in_array('brand_name',$exportCol)){
    					if (isset ( $item ['brand_name'] ) && trim($item ['brand_name'])!=='') {
    						$item ['brand_name'] = trim($item ['brand_name']);
    	
    						/*
    							if (in_array ( $item ['brand_name'], $allBrandName ))
    							$item ['brand_id'] = $brandNameIdMapping [$item ['brand_name']];
    						else {
    						$brand = new Brand ();
    						$brand->name = $item ['brand_name'];
    						$brand->comment = "??????????????????";
    						$tmpTime = date ( 'Y-m-d H:i:s', time () );
    						$brand->create_time = $tmpTime;
    						$brand->update_time = $tmpTime;
    						$brand->capture_user_id = Yii::$app->user->id;
    							
    						if ($brand->save ()) {
    						$item ['brand_id'] = $brand->brand_id;
    						} else {
    						$result [$key] [] = " ??????'" . $item ['brand_name'] . "' ???????????? ";
    						$validate = false;
    						}
    						}
    						*/
    							
    						if(isset($CACHE['brandInfo'][$item ['brand_name']])){
    							$item['brand_id']=$CACHE['brandInfo'][$item ['brand_name']];
    						}
    						else{
    							$tmpBrand = BrandHelper::getBrandId($item ['brand_name'],true);
    							$item ['brand_id'] = $tmpBrand['brand_id'];
    							$CACHE['brandInfo'][$item ['brand_name']]=$item ['brand_id'];
    						}
    					}else
    						$item['brand_id']=0;
    					unset($item['brand_name']);
					}
					
					//?????????????????????
					if(in_array('supplier_name',$exportCol)){
    					// ????????????????????????????????????,???????????????find???????????????????????????????????????
    					if (isset ( $item ['supplier_name'] ) && trim($item ['supplier_name'])!=='') {
    						$item ['supplier_name']=trim($item ['supplier_name']);
    						/*
    							$criteria = new CDbCriteria ();
    						$criteria->compare ( 'name', $item ['supplier_name'] );
    						$criteria->compare ( 'is_disable', 0 );
    						$productSupplier = Supplier::find()
    						->andwhere( ['name' =>$item ['supplier_name'] ] )
    						->andwhere(['is_disable'=> 0])
    						->asArray()->all();
    						if ($productSupplier == null || $productSupplier->is_disable == 1) {
    						$result [$key] [] = " ?????????'" . $item ['supplier_name'] . "' ????????? ";
    						$validate = false;
    						} else if ($productSupplier->status == 2) {
    						$result [$key] [] = " ?????????'" . $item ['supplier_name'] . "' ????????? ";
    						$validate = false;
    						} else {
    						$item ['supplier_id'] = $productSupplier->supplier_id;
    						}
    						*/
    						if(isset($CACHE['supplierInfo'][$item ['supplier_name']])){
    							$item['supplier_id']=$CACHE['supplierInfo'][$item ['supplier_name']];
    						}
    						else{
    							$tmpSupplier = SupplierHelper::getSupplierId($item ['supplier_name'],true);
    							$item ['supplier_id'] = $tmpSupplier['supplier_id'];
    							$CACHE['supplierInfo'][$item ['supplier_name']]=$item ['supplier_id'];
    						}
    					}else
    						$item['supplier_id']=0;
    					unset($item['supplier_name']);
					}
					
					// ??????
					if(in_array('prod_tag',$exportCol)){
    					if (isset ( $item ['prod_tag'] ) && $item ['prod_tag']) {
    						$tags = explode ( ",", $item ['prod_tag'] );
    						$tmpTag=array();
    						foreach ( $tags as $t=>$tag ) {
    							if (mb_strlen ( $tag, 'utf-8' ) > 100) {
    								$result [$key] [] = " ???????????? (???????????? 100 ?????????) ";
    								$validate = false;
    								unset($tmpTag);
    								break;
    							} else {
    								if(trim($tag)!==''){
    									$tmpTag[$t]['tag_name']= trim($tag);
    									$tmpTag[$t]['sku'] = $item ['sku'];
    									$prodsDatas['tags'][]=trim($tag);
    								}
    							}
    						}
    					}
    					unset($item ['prod_tag']);
    					if(in_array('is_has_tag',$exportCol)){
        					if(empty($tmpTag)){
        						$item ['is_has_tag']='N';
        					}else{
        						$item ['is_has_tag']='Y';
        					}
    					}
					}
					
					//??????
					if(in_array('class_name', $exportCol)){
						if(!empty($item['class_name'])){
							$class_arr = explode(",", rtrim($item['class_name'], ","));
							$parent_number = '';
							$class_id = 0;
							$count = 0;
							foreach($class_arr as $class_name){
								$class_name = trim($class_name);
								$node = ProductClassification::findOne(['name' => $class_name, 'parent_number' => $parent_number]);
								if(!empty($node)){
									$parent_number = $node->number;
									$class_id = $node->ID;
									$count++;
								}
								else{
									break;
								}
							}
							
							if(count($class_arr) == $count){
								$item['class_id'] = $class_id;
							}
							else{
								$item['class_id'] = 0;
							}
						}
						else{
							$item['class_id'] = 0;
						}
						
						unset($item['class_name']);
					}
					
					//??????
					//???????????????alias???????????????
					if(in_array('alias',$exportCol)){
    					if(isset($item['alias'])){
    						if(is_array($item['alias'])){
    							foreach ($item['alias'] as $a){
    								$prodsDatas['alias'][]=array(
    										'sku'=>$item['sku'],
    										'alias_sku'=>$a,
    										'platform'=>'',
    										'selleruserid'=>'',
    										'comment'=>'???excle????????????',
    								);
    							}
    							
    							//????????????????????????
    							if(!$isUpdate){
    								$prodsDatas['alias'][]=array(
    										'sku'=>$item['sku'],
    										'alias_sku'=>$item['sku'],
    										'platform'=>'',
    										'selleruserid'=>'',
    										'comment'=>'???excle????????????',
    								);
    							}
    						}
    						unset($item['alias']);
    					}
					}
					
					$photos = array ();
	
					//????????????????????????????????????
					if(!$isUpdate){
    					if (isset ( $item ['photo_primary'] ) && $item ['photo_primary'])
    						$photos [0] = $item ['photo_primary'];
    					for($i = 2; $i <= 6; $i ++) {
    						if (! empty ( $item ['photo_others_' . $i] )) {
    							if (! in_array ( $item ['photo_others_' . $i], $photos ))
    								$photos [$i] = $item ['photo_others_' . $i];
    							else {
    								$result [$key] [] = '????????????' . $i . '??????';
    								$validate = false;
    							}
    						}
    						unset($item ['photo_others_' . $i]);
    					}
					}
					//???????????????????????????
					else{
					    if (isset ( $item ['photo_primary'] ) && $item ['photo_primary']){
					        $photos [0] = $item ['photo_primary'];
					        $Photolist = Photo::find()->where(['sku'=>$item['sku']])->asArray()->All();
					        foreach ($Photolist as $ph){
					            if(trim($ph['photo_url']) != trim($item ['photo_primary'])){
					                $photos [] = $ph['photo_url'];
					            }
					        }
					    }
					}
						
					//$item ['photo_others'] = '';
						
					if (! empty ( $photos )) {
						$checkPhoto = true;
						foreach ( $photos as $photoIndex => $photo ) {
							// ???????????????????????????, ???????????????????????????????????????????????????????????????????????????
							$pattern = '/^(http)|(https):\/\//';
							if (! preg_match ( $pattern, $photo ) && strpos($photo, '/images/') !== 0) {
								$checkPhoto = false;
								$result [$key] [] = '??????' . (($photoIndex == 0) ? '?????????' : '??????' . $photoIndex) . '????????????';
							}
						}
						if ($checkPhoto){
							//??????????????????????????????photo_others????????????????????????
							//if(isset($photos[0])){
							//	unset($photos[0]);
							//}
								
							//$item ['photo_others'] = implode ( '@,@', $photos );
						}
						else
							$validate = false;
					}
						
					if (! $validate) {
						if (! empty ( $item ['sku'] )) {
							$result [$key] ['sku'] = $item ['sku'];
						}
						$result [$key] ['insert'] = false;
						$failQty++;
						continue;
					}else{
						$result [$key] ['insert'] = true;
						$insertSkuList[] = $item ['sku'];
					}
					
					if(!$isUpdate){
					    $item ['create_source'] = 'excel';
					}
						
					$tmpProData= $item;
					$result [$key] ['sku'] = $item ['sku'];
					
					//?????????????????????????????????
					if(in_array('supplier_name',$exportCol)){
    					if (isset ( $item ['purchase_price'] ) && $item ['purchase_price'] != '') {
    						$item ['purchase_price'] = (empty ( $item ['purchase_price'] ) ? 0 : floatval($item ['purchase_price']));
    						$tmpProData['purchase_price'] = $item ['purchase_price']+0;
    					}else{
    						$tmpProData['purchase_price']=0;
    					}
    					if(isset($tmpProData['supplier_id'])){
    						$prodsDatas['supplierInfo'][]=array(
    								'sku'=>$tmpProData['sku'],
    								'supplier_id'=>$tmpProData['supplier_id'],
    								'priority'=>0,
    								'purchase_price'=>$tmpProData['purchase_price'],
    								'purchase_link' => empty($tmpProData ['purchase_link']) ? '' : trim($tmpProData ['purchase_link']),
    						);
    					}
					}
					
					if(isset($tmpProData['purchase_link'])){
						unset($tmpProData['purchase_link']);
					}
						
					//$current_time=explode(" ",microtime());//test liang
					//$step3_a_2_time=round($current_time[0]*1000+$current_time[1]*1000);//test liang
					//\Yii::info(['catalog', __CLASS__, __FUNCTION__,'Background',"prodHelper foreach validate used time:".($step3_a_2_time-$step3_a_1_time)],"edb\global");//test liang
						
					//$tmpP->attributes=$tmpProData;
					//$tmpP->create_time=date('Y-m-d H:i:s', time());
					//$tmpP->update_time=date('Y-m-d H:i:s', time());
					//$tmpP->purchase_by=\Yii::$app->user->id;
					//$tmpP->capture_user_id=\Yii::$app->user->id;
					//$tmpP->total_stockage=0;
					//$tmpP->pending_ship_qty=0;
						
					$tmpProData['purchase_by']=\Yii::$app->user->id;
					$tmpProData['capture_user_id']=\Yii::$app->user->id;
					
					//????????????????????????
					if($itype == 'L' && !$isUpdate){
					    //???????????????
					    if(!empty($father_sku) && !in_array($father_sku, $allProdSku)){
					        $tmpProData_father = $tmpProData;
					        $tmpProData_father['sku'] = $father_sku;
					        $tmpProData_father['type'] = 'C';
					        $tmpProData_father ['status'] = $tmpProData['status'];
					        $tmpProData_father['create_time'] = date('Y-m-d H:i:s', time());
					        $tmpProData_father['update_time'] = date('Y-m-d H:i:s', time());
					        $tmpProData_father['total_stockage'] = 0;
					        $tmpProData_father['pending_ship_qty'] = 0;
					        $tmpProData_father['other_attributes'] = '';
					        if(in_array('is_has_tag',$exportCol)){
					            $tmpProData_father['is_has_tag'] = 'N';
					        }
					        
					        $prodsDatas['info'][] = $tmpProData_father;
					        $insertSkuList[] = $father_sku;
					        
					        //???????????????
					        if(!empty($photos)){
    			        		foreach ($photos as $pIndex=>$pUrl){
    			        			if($pIndex==0 or $pIndex==1)
    			        				$priority = $pIndex;
    			        			else
    			        				$priority = $pIndex-1;
    			        				
    			        			$tmpPhoto['sku'] = $father_sku;
    			        			$tmpPhoto['priority'] =$priority;
    			        			$tmpPhoto['photo_scale'] ='OR';
    			        			$tmpPhoto['photo_url'] =$pUrl;
    			        				
    			        			$prodsDatas['photos'][]=$tmpPhoto;
    					        }
					        }
					        
					        $allProdSku[] = $father_sku;
					    }
					    
					    //??????????????????????????????
						$prodsDatas['config_list'][] = $config;
					}
						
					if(!$isUpdate){
						//??????
						$tmpProData['create_time']=date('Y-m-d H:i:s', time());
						$tmpProData['update_time']=date('Y-m-d H:i:s', time());
						$tmpProData['total_stockage']=0;
						$tmpProData['pending_ship_qty']=0;
						$prodsDatas['info'][]=$tmpProData;
						
						$add_log .= $tmpProData['sku'].", ";
					}else{
					    $old_product = Product::findOne(['product_id' => $tmpP->product_id]);
					    
						$tmpProData['update_time']=date('Y-m-d H:i:s', time());
						$tmpP->attributes = $tmpProData;
						$tmpP->save(false);
						$result [$key] [] = '?????????'.$item ['sku'].'??????????????????????????????';
						$successUpdateQty++;
						
						//??????????????????
					    $log = '';
						if(!empty($old_product)){
							foreach (self::$EDIT_PRODUCT_LOG_COL as $col_k => $col_n){
								if($tmpP->$col_k != $old_product->$col_k){
									if(empty($log)){
										$log = $tmpP->sku;
									}
									$log .= ', '.$col_n.'???"'.$old_product->$col_k.'"??????"'.$tmpP->$col_k.'"';
								}
							}
							if(!empty($log)){
							    $edit_log .= $log."; ";
							}
						}
					}
					/*
						if( $tmpP->save() ){
					$result[$key]['insert']=true;
					}else{
					$result[$key]['insert']=false;
					foreach ($tmpP->errors as $k => $anError){
					$result[$key][] = $anError[0];
					}
					}
					*/
						
					if (!empty($tmpTag)){
						foreach ($tmpTag as $pt)
							$prodsDatas['prod_tag'][]=$pt;
						unset($tmpTag);
					}
					
					if(!empty($photos)){
						foreach ($photos as $pIndex=>$pUrl){
							if($pIndex==0 or $pIndex==1)
								$priority = $pIndex;
							else
								$priority = $pIndex-1;
								
							$tmpPhoto['sku'] = $item['sku'];
							$tmpPhoto['priority'] =$priority;
							$tmpPhoto['photo_scale'] ='OR';
							$tmpPhoto['photo_url'] =$pUrl;
								
							$prodsDatas['photos'][]=$tmpPhoto;
						}
					}
					/*
						if($result[$key]['insert']){
					if (!empty($tmpTag))
						TagHelper::updateTag($tmpP->sku, $tmpTag) ? 'Y' : 'N';
	
					$photo_primary=empty($item ['photo_primary'])?'':$item ['photo_primary'];
					if(empty($photos)) $photos=array();
	
					PhotoHelper::savePhotoByUrl($tmpP->sku, $photo_primary, $photos);
	
					}
						
						
					//$result [$key] ['insert'] = self::saveProduct ( $tmpP, $tmpProData, $isUpdate );
						
					$current_time=explode(" ",microtime());//test liang
					$step3_a_4_time=round($current_time[0]*1000+$current_time[1]*1000);//test liang
					\Yii::info(['catalog', __CLASS__, __FUNCTION__,'Background',"prodHelper foreach other_save used time:".($step3_a_4_time-$step3_a_3_time)],"edb\global");//test liang
					*/
				}
				//transaction
				//print_r($prodsDatas);die;
				$transaction = Yii::$app->get('subdb')->beginTransaction();
				try{
					SQLHelper::groupInsertToDb('pd_product', $prodsDatas['info']);
					$successInsertQty = count($prodsDatas['info']);

					//??????????????????????????????
					if(in_array('supplier_name',$exportCol)){
					    if(!empty($prodsDatas['supplierInfo'])){
        					ProductSuppliers::deleteAll(['sku'=>$insertSkuList, 'priority'=>'0']);
        					foreach ($prodsDatas['supplierInfo'] as $supplierInfo){
        					    ProductSuppliers::deleteAll(['sku'=>$supplierInfo['sku'], 'supplier_id'=>$supplierInfo['supplier_id']]);
        					}
        					SQLHelper::groupInsertToDb('pd_product_suppliers',  $prodsDatas['supplierInfo']);
					    }
					}
		
					//???????????????????????????
					if(in_array('prod_tag',$exportCol)){
    					$tagExist = Tag::find()->select(['tag_name'])->where("tag_id<>0")->asArray()->all();
    					$tagExistArray=array();
    					foreach ($tagExist as $tE){
    						$tagExistArray[]=$tE['tag_name'];
    					}
    					$allTagsPost = array_unique($prodsDatas['tags']);
    					$tagNeedToInster = array_diff($allTagsPost, $tagExistArray);
    					$tagNeedToInsterData=array();
    					foreach ($tagNeedToInster as $tag){
    						$tagNeedToInsterData[]=array('tag_name'=>$tag);
    					}
    		
    					SQLHelper::groupInsertToDb('pd_tag', $tagNeedToInsterData);
    		
    					$tagModels=Tag::find()->where(['in', 'tag_name', $allTagsPost])->asArray()->all();
    					$CACHE['tag']=$tagModels;
		
    					ProductTags::deleteAll(['sku'=>$insertSkuList]);
    					if(!empty($prodsDatas['prod_tag'])){
    						$tmpProdTags = array();
    						foreach ($prodsDatas['prod_tag'] as $prodTag){
    							foreach ($CACHE['tag'] as $index=>$tagInfo){
    								if($prodTag['tag_name']==$tagInfo['tag_name']){
    									$tmpProdTags[]=array('tag_id'=>$tagInfo['tag_id'] , 'sku'=>$prodTag['sku']);
    									break;
    								}
    							}
    						}
    						$prodsDatas['prod_tag'] = $tmpProdTags;
    						if(!empty($prodsDatas['prod_tag']))
    							SQLHelper::groupInsertToDb('pd_product_tags', $prodsDatas['prod_tag']);
    					}
					}
		
					if(!empty($prodsDatas['photos'])){
    					Photo::deleteAll(['sku'=>$insertSkuList]);
    					SQLHelper::groupInsertToDb('pd_photo', $prodsDatas['photos']);
					}
					
					SQLHelper::groupInsertToDb('pd_product_aliases', $prodsDatas['alias']);
					
					//???????????????SKU
					if($itype == 'B' && !empty($prodsDatas['assku_list'])){
					    SQLHelper::groupInsertToDb('pd_product_bundle_relationship', $prodsDatas['assku_list']);
					}
					
					//????????????????????????
					if($itype == 'L' && !empty($prodsDatas['config_list'])){
						SQLHelper::groupInsertToDb('pd_product_config_relationship', $prodsDatas['config_list']);
					}
					
					$transaction->commit();
					
					//????????????????????????
					self::getProductClassCount(true);
					
					//??????????????????
					$logs = '';
					if(!empty($add_log)){
					    $logs .= "??????: ".$add_log;
					}
					if(!empty($edit_log)){
						$logs .= "??????: ".$edit_log;
					}
					
					if(!empty($logs)){
					    $logs = "????????????, ????????????: ".$successInsertQty.", ??????: ".$successUpdateQty.", ??????: ".$failQty."???".$logs;
					    //print_r($logs);die;
					    if(strlen($logs) > 480){
					        $logs = substr($logs, 0, 480).'......';
					    }
    					//??????????????????
    					UserHelper::insertUserOperationLog('catalog', $logs);
					}
					
				}
				catch (\Exception $e) {
				    //print_r($e->getMessage());die;
    				$result['error'] .= '???????????????????????????????????????????????????????????????????????????????????????';
    				$transaction->rollBack();
    				SysLogHelper::SysLog_Create('Catalog',__CLASS__, __FUNCTION__,'error',$e->getMessage());
    				
    				$uid = \Yii::$app->subdb->getCurrentPuid();
    				\Yii::info('Catalog, puid: '. $uid .', importProductData, '.$e->getMessage().PHP_EOL."trace:".$e->getTraceAsString(), "file");
    			}
			} 
			else {
				$result ['error'] = "????????????,";
				if($is_not_sku)
				    $result ['error'] .="????????????????????????????????????SKU???<br>";
				if(!empty($sameSkuInfo))
					$result ['error'] .="?????????????????????????????? sku:" . implode ( ',', array_keys ( $sameSkuInfo ) )."<br>";
				if(!empty($sameAlias))
					$result ['error'] .="?????????????????????????????? sku??????:" . implode ( ',', array_keys ( $sameAlias ) )."<br>";
				if(!empty($existingAlias))
					$result ['error'] .="??????????????????????????????????????????:" . implode ( ',', array_keys ( $existingAlias ) )."<br>";
				
				if(!empty($alias_EQ_existingSku))
					$result ['error'] .="?????????????????????????????????????????????sku????????????excel???????????????????????????????????????:" . implode ( ',', $alias_EQ_existingSku )."<br>";
				
				if(!empty($alias_EQ_importSku))
					$result ['error'] .="??????????????????????????????????????????????????????sku????????????excel???????????????????????????????????????:" . implode ( ',', $alias_EQ_importSku )."<br>";
				
				if($notExistAssku)
				    $result ['error'] .="???????????????????????????????????????????????????SKU???<br>";
				if($not_Exist_attribute)
				    $result ['error'] .="????????????????????????????????????????????????1?????????????????????1?????????<br>";
				if($not_Exist_father_sku)
				    $result ['error'] .="???????????????????????????????????????????????????sku???<br>";
				
			}
		} else {
			$result ['error'] = $productsData;
		}
		if (isset ( $result ['error'] ) && $result ['error']) {
			//SysLogHelper::SysLog_Create ( "Catalog", __CLASS__, __FUNCTION__, "", $result ['error'], "error" );
			$moduleName = "Catalog";
			$message = $result ['error'];
			\Yii::error([$moduleName,__CLASS__,__FUNCTION__,$message],"edb\user");
		} else {
			//SysLogHelper::SysLog_Create ( "Catalog", __CLASS__, __FUNCTION__, "", "import success : $successNum , fail : $failNum ", "trace" );
				
			$moduleName = "Catalog";
			$message = "import success : ".($successInsertQty+$successUpdateQty)." , fail : $failQty  ";
			\Yii::info([$moduleName,__CLASS__,__FUNCTION__,$message],"edb\user");
		}
		
		$result['allQty'] = $allQty;
		$result['successInsertQty'] = $successInsertQty;
		$result['successUpdateQty'] = $successUpdateQty;
		$result['failQty'] = $failQty;
		
		return $result;
	}
	
	/**
	 * +----------------------------------------------------------
	 * ?????????????????????
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @return ??????????????? +----------------------------------------------------------
	 *         log			name	date					note
	 * @author ouss	2014/02/27				?????????
	 *+----------------------------------------------------------
	 *        
	 */
	public static function getProductSuppliers() {
		$criteria = new CDbCriteria ();
		$criteria->select = 'supplier_id,name';
		$criteria->compare ( 'is_disable', 0 );
		$criteria->compare ( 'status', 1 );
		$suppliers = Supplier::findAll ( $criteria );
		$suppliersArray = array ();
		foreach ( $suppliers as $s ) {
			$suppliersArray [$s->supplier_id] = $s->name;
		}
		return $suppliersArray;
	}
	
	/**
	 * +----------------------------------------------------------
	 * ??????????????????????????????????????????
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	sku			??????SKU
	 * @param
	 *        	PDAliasList	????????????
	 *+----------------------------------------------------------
	 * @return ??????????????????
	 *+----------------------------------------------------------
	 * log			name	date					note
	 * @author 		lkh		2014/09/26				?????????
	 *+----------------------------------------------------------
	 *        
	 */
	public static function checkProductAlias($sku, $PDAliasList) {
		// get alias data
		//$pdCriteria = new CDbCriteria ();
		//$pdCriteria->addInCondition ( 'alias_sku', $PDAliasList );
		$pdCriteria = ['alias_sku'=>$PDAliasList] ;
		$aliasList = ProductAliases::find()->where($pdCriteria)->all();
		$result ['status'] = "success";
		$result ['message'] = "";
		// validate alias whether active
		foreach ( $aliasList as $anAlias ) {
			if ($anAlias->sku != $sku) {
				$result ['status'] = "failure";
				$result ['message'] .= "??????[" . $anAlias->alias_sku . "]???????????????[" . $anAlias->sku . "]??????! <br>";
			}
		}
		
		// check alias whether a product sku
		if ($result ['status'] == "success") {
			unset ( $pdCriteria );
			//$pdCriteria = new CDbCriteria ();
			//$pdCriteria->@author Administrator

			/*
			$pdCriteria = ['sku'=>$PDAliasList];
			$product_list = Product::findAll ( $pdCriteria );
			foreach ( $product_list as $a_product ) {
				if ($result ['status'] != 'confirm') {
					$result ['status'] = 'confirm';
				}
				$result['redundant'][]=$a_product->sku;
				$result ['message'] .= "??????[" . $a_product->sku . "]??????????????????  <br>";
			}*/
			
			/*kh20150504start eagle 2.0 ??????????????????????????????, ?????????????????????????????? 
			if (strlen ( $result ['message'] ) > 0) {
				
				$result ['message'] .= "?????????????????????????????? $sku ???<br> ?????????????????????????????????????????????????????????????????????????????????????????????????????????";
			}
			kh20150504end eagle 2.0 ??????????????????????????????, ?????????????????????????????? */
		}
		
		return $result;
	} // end of checkProductAlias
	
	/**
	 * +----------------------------------------------------------
	 * ?????????????????????????????????
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	sku			??????SKU
	 *+----------------------------------------------------------
	 * @return ???????????? +----------------------------------------------------------
	 *         log			name	date					note
	 * @author lkh	2014/09/28				?????????
	 *+----------------------------------------------------------
	 *        
	 */
	public static function getLastOrderID($sku) {
		// $sku = '14052201_Y';//test
		$MyorderItem = OdOrderItem::find ( 'sku=:key', array (
				':key' => $sku 
		) );
		if (isset ( $MyorderItem->order_id ))
			return $MyorderItem->order_id;
		else
			return "";
	} // end of getLastOrderID
	
	/**
	 * +----------------------------------------------------------
	 * ??????????????? alias ????????? root sku
	 * ?????????alias ???????????? root sku????????? ??????(root sku)
	 * ?????????alias ????????????sku???????????????
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	sku_alias			??????SKU?????????
	 *+----------------------------------------------------------
	 * @return root sku ??? ????????????
	 *+----------------------------------------------------------
	 * log			name	date					note
	 * @author 		lkh		2014/10/09				?????????
	 *+----------------------------------------------------------
	 *        
	 */
	public static function getRootSkuByAlias($sku_alias, $platform = '', $selleruserid = '') {
		global $CACHE;
		$sku_alias = trim ( $sku_alias );
		$platform = empty($platform) ? '' : $platform;
		$selleruserid = empty($selleruserid) ? '' : $selleruserid;
		
		//2016-07-01  ???????????????????????????global cache ??????????????? start
		$uid = \Yii::$app->subdb->getCurrentPuid();
// 		var_dump($CACHE[$uid]);
// 		exit();

		$alias_key = $sku_alias.$platform.$selleruserid;
		if (isset($CACHE[(string)$uid]['alias'])){
			//???alias ?????? ??? ??????????????????
			if (!empty($CACHE[(string)$uid]['alias'][$alias_key])){
				$result = $CACHE[(string)$uid]['alias'][$alias_key];
			}
			
			//log ?????? ??? ??????????????????start
			$GlobalCacheLogMsg = "\n puid=$uid ".(__function__).'alias has cache';
			\eagle\modules\order\helpers\OrderBackgroundHelper::OrderCheckGlobalCacheLog($GlobalCacheLogMsg);
			//log ?????? ??? ??????????????????end
		}
		
		if(empty($result)){
			//log ?????? ??? ??????????????????start
			$GlobalCacheLogMsg = "\n puid=$uid ".(__function__).'alias no cache';
			\eagle\modules\order\helpers\OrderBackgroundHelper::OrderCheckGlobalCacheLog($GlobalCacheLogMsg);
			//log ?????? ??? ??????????????????end
			
			$aliasList = ProductAliases::find()->select(['sku', 'platform', 'selleruserid'])->where(['alias_sku' => $sku_alias])->orderby('platform desc, selleruserid desc')->asarray()->all();
			if(!empty($aliasList)){
				//?????????????????????????????????????????????
				if(count($aliasList) == 1){
					return $aliasList[0]['sku'];
				}
				else{
					foreach ($aliasList as $alias){
						//????????????????????????????????????
						if($alias['platform'] == $platform && $alias['selleruserid'] == $selleruserid){
							return $alias['sku'];
						}
						//???????????????????????????????????????
						else if($alias['platform'] == $platform && empty($alias['selleruserid'])){
							return $alias['sku'];
						}
						//???????????????????????????????????????
						else if(empty($alias['platform']) && empty($alias['selleruserid'])){
							return $alias['sku'];
						}
					}
				}
			}
			else{
				//??????????????????????????????
				$pro = Product::findOne(['sku' => $sku_alias]);
				if(!empty($pro)){
					return $sku_alias;
				}
			}
		}
		
		//?????????????????????????????????
		/*if (empty($result)){
			//??????alias ????????? ??????product???????????????
			if (isset($CACHE[(string)$uid]['product'])){
				
				if (!empty($CACHE[(string)$uid]['product'][$sku_alias])){
					$result = $CACHE[(string)$uid]['product'][$sku_alias];
				}
				
				//log ?????? ??? ??????????????????start
				$GlobalCacheLogMsg = "\n puid=$uid ".(__function__).'product has cache';
				\eagle\modules\order\helpers\OrderBackgroundHelper::OrderCheckGlobalCacheLog($GlobalCacheLogMsg);
				//log ?????? ??? ??????????????????end
			}else{
				// check this sku whether root sku
				$result = Product::findOne (  array (
						'sku' => $sku_alias
				) );
				
				//log ?????? ??? ??????????????????start
				$GlobalCacheLogMsg = "\n puid=$uid ".(__function__).'product no cache';
				\eagle\modules\order\helpers\OrderBackgroundHelper::OrderCheckGlobalCacheLog($GlobalCacheLogMsg);
				//log ?????? ??? ??????????????????end
			}
		}*/
		
		//2016-07-04  ???????????????????????????global cache ??????????????? start
		
		if (empty ( $result )) {
			return "";
		} else {
			if (is_array($result)){
				$result = (Object)$result;
			}
			return $result->sku;
		}
		
	} // end of getRootSkuByAlias

	//yzq 20170221 performance tuning
	public static function getRootSkuByAliasArr($sku_aliasArr) {
		global $CACHE;
		//$sku_alias = trim ( $sku_alias );
	
		 
		// this sku not root sku
		$result1 = ProductAliases::findAll ( array (
				'alias_sku' => $sku_aliasArr
		) );

		
		$result2 = Product::findAll(  array (
				'sku' => $sku_aliasArr
			) );		
		
	
		$allResult = [];
		foreach ($result1 as $aResult){
			$allResult[strtolower($aResult->alias_sku)] = $aResult->sku;
		}
		
		foreach ($result2 as $aResult){
			if (!isset($allResult[$aResult->sku]))
			$allResult[strtolower($aResult->sku)] = $aResult->sku;
		}
	
		return $allResult; 
	
	} // end of getRootSkuByAliasArr
	
	
	/**
	 * +----------------------------------------------------------
	 * ?????????sku for ?????? site ???alias
	 * ????????????site ????????? ?????? ???????????????????????? alias model
	 * ????????????site ???????????????????????????????????? site ???alias (????????????????????????????????????)
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	sku ??????SKU?????????
	 * @param
	 *        	site ?????? ???????????????
	 *+----------------------------------------------------------
	 * @return na +----------------------------------------------------------
	 *         log			name	date					note
	 * @author lkh	2014/10/08				?????????
	 *+----------------------------------------------------------
	 *        
	 */
	public static function getAliasForSku($sku, $site = '') {
		// init
		$all_alias = array ();
		$match_alias = array ();
		
		// get all data through this sku
		$product_alias_list = ProductAliases::FindAll ( 'sku=:sku', array (
				':sku' => $sku 
		) );
		
		foreach ( $product_alias_list as $an_alias ) {
			$all_alias [] = $an_alias->alias_sku;
			
			if (strtolower ( $an_alias->forsite ) == strtolower ( $site )) {
				$match_alias [] = $an_alias->alias_sku;
			}
		}
		
		if (empty ( $site ) || empty ( $match_alias )) {
			// if site is empty then return all alias
			return $all_alias;
		} else {
			// if site is not empty then return this site alias
			return $match_alias;
		}
	} // end of getAliasForSku
	
	/**
	 * ????????????sku???????????????root?????????alias 
	 * ????????????sku?????????????????????????????????
	 * ?????????????????????
	 * @access		static
	 * @param		sku			??????SKU?????????
	 * @return		'' or array(rootsku=>[type=>'root'],alias_sku1=>['type'=>'alia','forsite'=>'ebay'],alias_sku2=>['type'=>'alia','forsite'=>'amazon'],...)
	 * @author		lzhl	2016/8/19	?????????
	 */
	public static function getAllAliasRelationBySku($sku){
		$relation = [];
		$rootSku = self::getRootSkuByAlias($sku);
		
		if(empty($rootSku))//???root sku,???????????????????????????
			return $relation;
		else //???root sku,????????????root sku???????????????
			$relation[$rootSku] = ['type'=>'root'];
		
		if(!empty($rootSku)){
			$product_alias_list = ProductAliases::find()->where(['sku'=>$rootSku])->asArray()->all();
			foreach ($product_alias_list as $an_alias){
				$relation[$an_alias['alias_sku']] = ['type'=>'alia','forsite'=>$an_alias['forsite']];
			}
		}
		
		return $relation;
	}
	
	/**
	 * +----------------------------------------------------------
	 * ????????????sku???????????????
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	sku_or_alias ??????SKU?????????
	 *+----------------------------------------------------------
	 * @return string ???C?????????????????????
	 *         ???L?????????????????????
	 *         ???S???: ????????????
	 *         ???B???: ????????????
	 *         "": ??????????????????????????????
	 *+----------------------------------------------------------
	 *         log			name	date					note
	 * @author lkh 2014/10/17				?????????
	 *+----------------------------------------------------------
	 */
	public static function getProductTypebySKU($sku_or_alias) {
		// step 1 get root sku
		$root_sku = self::getRootSkuByAlias ( $sku_or_alias );
		if (empty ( $root_sku )) {
			return "";
		} else {
			// get product type
			$result = Product::find ( 'sku =:sku', array (
					':sku' => $root_sku 
			) );
			if (empty ( $result )) {
				return "";
			} else {
				return $result->type;
			}
		}
	} // end of getProductType
	
	/**
	 * +----------------------------------------------------------
	 * ????????????????????????????????? sku
	 * ?????????????????????root sku
	 * ??????????????? alias ????????? root sku???
	 * ????????? root sku ???pd_product_config_relationship ????????? ???sku???
	 * ?????????????????? ??????????????????????????????sku
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	sku ??????SKU
	 *+----------------------------------------------------------
	 * @return sku ??? ????????????
	 *+----------------------------------------------------------
	 *         log			name	date					note
	 * @author lkh 2014/10/17				?????????
	 *+----------------------------------------------------------
	 */
	public static function getConfigFatherSKU($sku) {
		$product_type = self::getProductTypebySKU ( $sku );
		if (strtolower ( $product_type ) == "l") {
			// only child product should find father sku
			$relation_ship = ProductConfigRelationship::find ( 'assku = :sku', array (
					':sku' => $sku 
			) );
			if (empty ( $relation_ship )) {
				return $sku;
			} else {
				return $relation_ship->cfsku;
			}
		} else {
			return $sku;
		}
	} // end of getConfigFatherSKU
	
	/**
	 * +----------------------------------------------------------
	 * ????????????????????????????????? sku????????? ?????? ??????????????????AAA??? ??????????????????sku ??? array(???AAA_1???,???AAA_2???)
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	sku ??????SKU
	 *+----------------------------------------------------------
	 * @return array sku??????
	 *+----------------------------------------------------------
	 *         log			name	date					note
	 * @author lkh 2014/10/17				?????????
	 *+----------------------------------------------------------
	 */
	public static function getConfigAsSKU($sku) {
		// find father sku
		$father_sku = self::getConfigFatherSKU ( $sku );
		// get all relation ship data
		$relation_ship_list = ProductConfigRelationship::findall ( 'cfsku = :sku', array (
				':sku' => $father_sku 
		) );
		if (empty ( $relation_ship_list )) {
			return array (
					$sku 
			);
		} else {
			foreach ( $relation_ship_list as $a_relation ) {
				$reuslt [] = $a_relation->assku;
			}
			return $reuslt;
		}
	} // end of getConfigAsSKU
	
	/**
	 * +----------------------------------------------------------
	 * ??????????????????
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	names field name
	 * @param
	 *        	action ????????????(new:??????????????? add_old:????????????????????????)
	 * @param
	 *        	sku ?????????sku
	 *+----------------------------------------------------------
	 * @return array 
	 * +----------------------------------------------------------
	 * log		name	date					note
	 * @author 	lzhl	2014/10/22				?????????
	 *+----------------------------------------------------------
	 */
	public static function getConfigureField($names = false, $rows = 1, $action, $sku = false) {
		$result ['rows'] = array ();
		if ($action == 'new') {
			$AttrResult = self::getCreateingLProductAttributes ( $names, $attr_str = false, $rows );
			if (! count ( $AttrResult ['configurAttrs'] ) > 0) {
				$result ['rows'] [0] ['Attributes'] = array ();
				$result ['total'] = 1;
				$result ['rows'] [0] ['productStatus'] = 'OS';
				$result ['rows'] [0] ['sku_type'] = 'new';
				$result ['configurAttrs'] = $AttrResult ['configurAttrs'];
				foreach ( $AttrResult ['attrs'] as $key => $value ) {
					// $result['rows'][0][$key] = $value;
					$result ['rows'] [0] ['attr_names'] [] = $key;
				}
			} else {
				$result ['total'] = 1;
				$result ['rows'] [0] ['productStatus'] = 'OS';
				$result ['rows'] [0] ['sku_type'] = 'new';
				$result ['configurAttrs'] = $AttrResult ['configurAttrs'];
				foreach ( $AttrResult ['attrs'] as $key => $value ) {
					// $result['rows'][0]["$key"] = $value;
					$result ['rows'] [0] ['attr_names'] [] = $key;
				}
			}
			$countAttr = count ( $result ['configurAttrs'] );
			if ($countAttr < 3) {
				for($i = 0; $i < 3 - $countAttr; $i ++) {
					$result ['configurAttrs'] [] = array (
							'attr_id' => '',
							'name' => '',
							'values' => array () 
					);
				}
			}
			$result ['rows'] [0] ['sku'] = '';
			if (! empty ( $sku )) {
				$model = Product::findByPk ( $sku );
				if ($model !== null) {
					$result ['productType'] = $model->type;
					$result ['rows'] [0] ['sku'] = $sku;
				} else
					$result ['productType'] = '';
			} else
				$result ['productType'] = '';
			return ($result);
		}
		if ($action == 'add_old' && $sku != '') {
			$model = Product::findByPk ( $sku );
			if ($model == null) {
				$result = self::getConfigureField ( $names, $rows, $action = 'new', $sku );
			} else {
				$skuResult = $model->sku;
				$weightResult = $model->prod_weight;
				$imgResult = $model->photo_primary;
				$statusResult = $model->status;
				$attrsResult = $model->other_attributes;
				if ($attrsResult == '')
					$attrsResult = 'null';
				
				$field = array ();
				$AttrResult = self::getCreateingLProductAttributes ( $names = false, $attrsResult, $rows );
				
				$result ['configurAttrs'] = $AttrResult ['configurAttrs'];
				$result ['rows'] [0] ['sku'] = array (
						'sku' => $skuResult,
						'img' => $imgResult,
						'weight' => $weightResult,
						'Attributes' => $field,
						'productStatus' => $statusResult,
						'sku_type' => 'add_old' 
				);
				$result ['rows'] [0] ['sku'] = $skuResult;
				$result ['rows'] [0] ['img'] = $imgResult;
				$result ['rows'] [0] ['weight'] = $weightResult;
				$result ['rows'] [0] ['productStatus'] = $statusResult;
				$result ['rows'] [0] ['sku_type'] = 'add_old';
				foreach ( $AttrResult ['attrs'] as $key => $value ) {
					$result ['rows'] [0] ["$key"] = $value;
					$result ['rows'] [0] ['attr_names'] [] = $key;
				}
				$result ['productType'] = $model->type;
			}
			$countAttr = count ( $result ['configurAttrs'] );
			if ($countAttr < 3) {
				for($i = 0; $i < 3 - $countAttr; $i ++) {
					$result ['configurAttrs'] [] = array (
							'attr_id' => '',
							'name' => '',
							'values' => array () 
					);
				}
			}
			return $result;
		}
	}
	
	/**
	 * +----------------------------------------------------------
	 * ?????????????????????????????????????????????attributes???name?????????value
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	names attributes name string like('color,size,brand....')
	 * @param
	 *        	attr_str attributes name:value string('A:a;B:b,C:c....')
	 * @param
	 *        	rows ???????????????attributes??????
	 *+----------------------------------------------------------
	 * @return array 
	 * +----------------------------------------------------------
	 *	log			name	date					note
	 * @author 		lzhl 	2014/11/3				?????????
	 *+----------------------------------------------------------
	 */
	public static function getCreateingLProductAttributes($names = false, $attr_str = false, $rows) {
		$result = array ();
		$result ['configurAttrs'] = array ();
		$result ['attrs'] = array ();
		if (! $names && $attr_str) {
			if ($attr_str != '') {
				$attr_str = str_replace ( "???", ";", $attr_str );
				$attr_str = str_replace ( "???", ":", $attr_str );
				$attrsArr = explode ( ';', $attr_str );
				for($i = 0; $i < count ( $attrsArr ); $i ++) {
					$attrStr = explode ( ':', $attrsArr [$i] );
					$nameArr [] = $attrStr [0];
					$result ['attrs'] [$attrStr [0]] = $attrStr [1];
				}
				
				$attrIds = Yii::$app->get('subdb')->createCommand ()->select ( 'id,field_name' )->from ( 'pd_product_field' )->where ( array (
						'in',
						'field_name',
						$nameArr 
				) )->order ( 'use_freq DESC' );
				$attrIds = $attrIds->queryAll ();
				$c = 1;
				if (count ( $attrIds ) > 0) {
					foreach ( $attrIds as $aId ) {
						
						// for($i=0;$i<count($attrsArr);$i++){
						$attrIndex = 'Attributes' + $c;
						// $attrStr = explode(':',$attrsArr[$i]);
						// $field["$attrStr[0]"] = array($attrStr[1]);
						
						$fieldValues = Yii::$app->get('subdb')->createCommand ()->select ( 'value,use_freq' )->from ( 'pd_product_field_value' )->where ( "field_id = $aId[id]" )->order ( 'use_freq DESC' );
						$fieldValues = $fieldValues->queryAll ();
						
						if ($fieldValues) {
							foreach ( $fieldValues as $aValue ) {
								$field [$c - 1] [] = array (
										'v' => $aValue ['value'],
										't' => $aValue ['use_freq'] 
								);
							}
							$result ['configurAttrs'] [] = array (
									'attr_id' => $aId ['id'],
									'name' => $aId ['field_name'],
									'values' => $field [$c - 1] 
							);
						} else
							$result ['configurAttrs'] [] = array (
									'attr_id' => $aId ['id'],
									'name' => $aId ['field_name'],
									'values' => array () 
							);
						$c ++;
						// }
					}
				}
			}
			return $result;
		}
		if (! $attr_str) {
			$sql = "SELECT field_name , id FROM pd_product_field ";
			if ($names) {
				$nameStr = "'" . $names . "'";
				$nameStr = str_replace ( ',', "','", $nameStr );
				$sql .= "WHERE field_name in ($nameStr) ";
			}
			$sql .= "ORDER BY use_freq DESC ";
			$sql .= "LIMIT $rows ";
			$command = Yii::$app->get('subdb')->createCommand ( $sql );
			// SysLogHelper::SysLog_Create("product",__CLASS__, __FUNCTION__,"", $command->getText(), "trace");
			$fields = $command->queryAll ();
			if (count ( $fields ) > 0) {
				$c = 1;
				foreach ( $fields as $afield ) {
					$afieldname = $afield ['field_name'];
					$afieldid = $afield ['id'];
					$fieldValues = Yii::$app->get('subdb')->createCommand ()->select ( 'value,use_freq' )->from ( 'pd_product_field_value' )->where ( "field_id = '$afieldid'" )->order ( 'use_freq DESC' );
					$fieldValues = $fieldValues->queryAll ();
					$attrIndex = 'Attributes' + $c;
					if ($fieldValues) {
						foreach ( $fieldValues as $aValue ) {
							$field [$c - 1] [] = array (
									'v' => $aValue ['value'],
									't' => $aValue ['use_freq'] 
							);
						}
						$result ['attrs'] [$afield ['field_name']] = $fieldValues [0] ['value'];
						$result ['configurAttrs'] [] = array (
								'attr_id' => $afield ['id'],
								'name' => $afield ['field_name'],
								'values' => $field [$c - 1] 
						);
					} else
						$result ['configurAttrs'] [] = array (
								'attr_id' => $afield ['id'],
								'name' => $afield ['field_name'],
								'values' => array () 
						);
					$c ++;
				}
			}
			return $result;
		}
	}
	/**
	 * +----------------------------------------------------------
	 * ??????/???????????????????????????????????????
	 *+----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	arrAttr		?????????????????????
	 *+----------------------------------------------------------
	 * @return ???
	 *+----------------------------------------------------------
	 *	log			name	date					note
	 * @author 		lzhl	2014/11/6				?????????
	 *+----------------------------------------------------------
	 *        
	 */
	public static function updateAttributes($arrAttr) {
		foreach ( $arrAttr as $anAttr ) {
			$KV = explode ( ":", $anAttr );
			$K = $KV [0];
			$V = $KV [1];
			if ($K == '' || $V == '')
				continue;
			else {
				$fieldModel = ProductField::findByAttributes ( array (
						'field_name' => $K 
				) );
				if ($fieldModel == null) {
					$fieldModel = new ProductField ();
					$fieldModel->use_freq = 1;
					$fieldModel->field_name = $K;
					$fieldModel->field_name_eng = 'null'; // ?????????????????????????????????null??????
					$fieldModel->field_name_frc = 'null'; // ?????????????????????????????????null??????
					$fieldModel->field_name_ger = 'null'; // ?????????????????????????????????null??????
					$fieldModel->save ();
				} else {
					$fieldId = $fieldModel->id;
					//$use_freq = $fieldModel->use_freq;
					//$fieldModel->use_freq = $use_freq + 1;
					$fieldModel->field_name_eng = 'null'; // ?????????????????????????????????null??????
					$fieldModel->field_name_frc = 'null'; // ?????????????????????????????????null??????
					$fieldModel->field_name_ger = 'null'; // ?????????????????????????????????null??????
					$fieldModel->save ();
				}
				
				$valueModel = ProductFieldValue::findByAttributes ( array (
						'field_id' => $fieldId,
						'value' => $V 
				) );
				if ($valueModel == null) {
					$valueModel = new ProductFieldValue ();
					$valueModel->field_id = $fieldId;
					$valueModel->value = $V;
					$valueModel->use_freq = 1;
					$valueModel->save ();
				} else {
					//$use_freq = $valueModel->use_freq;
					//$valueModel->use_freq = $use_freq + 1;
					$valueModel->save ();
				}
			}
		}
	}
	
	/**
	 * +----------------------------------------------------------
	 * ??????????????????
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	model		????????????
	 * @param
	 *        	values	??????????????????
	 * @param
	 *        	isUpdate	?????????????????????
	 *+----------------------------------------------------------
	 * @return ??????????????????????????? 
	 * +----------------------------------------------------------
	 *log			name	date					note
	 * @author 		lzhl	2014/11/12				?????????
	 *+----------------------------------------------------------
	 *        
	 */
	public static function saveBundleProduct($model, $values, $isUpdate = false) {
		self::saveProduct ( $model, $values );
		while ( true ) {
			$bdsku = $values ['Product'] ['sku'];
			$bundleRelationshipStr = trim ( $values ['Product'] ['bundle'] ['relationship'] );
			$bundleRelationshipList = explode ( "&", $bundleRelationshipStr );
			for($i = 0; $i < count ( $bundleRelationshipList ); $i ++) {
				$assku = '';
				$qty = '';
				if ($bundleRelationshipList [$i] == '')
					continue;
				else {
					$RelationFields = explode ( ";", $bundleRelationshipList [$i] );
					for($j = 0; $j < count ( $RelationFields ); $j ++) {
						$FieldKV = explode ( ":", $RelationFields [$j] );
						for($k = 0; $k < count ( $FieldKV ); $k ++) {
							if ($FieldKV [0] == 'sku')
								$assku = $FieldKV [1];
							if ($FieldKV [0] == 'qty')
								$qty = $FieldKV [1];
						}
					}
				}
				$command = Yii::$app->get('subdb')->createCommand ()->insert ( 'pd_product_bundle_relationship', array (
						'bdsku' => $bdsku,
						'assku' => $assku,
						'qty' => $qty,
						'create_date' => date ( 'Y-m-d H:i:s', time () ) 
				) );
				if (! $command)
					return array (
							'??????' => 'relationship create error' 
					);
				else
					continue;
			}
			break;
		}
		return true;
	}
	
	/**
	 * +----------------------------------------------------------
	 * update????????????
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	model		????????????
	 * @param
	 *        	values	??????????????????
	 * @param
	 *        	isUpdate	?????????????????????
	 *+----------------------------------------------------------
	 * @return ??????????????????????????? 
	 *+----------------------------------------------------------
	 *	log			name	date					note
	 * @author 		lzhl	2014/11/12				?????????
	 *+----------------------------------------------------------
	 *        
	 */
	public static function updateBundleProduct($model, $values, $isUpdate = false) {
		self::saveProduct ( $model, $values, true );
		while ( true ) {
			$farter_sku = $values ['Product'] ['sku'];
			$childrenSkus_had = ProductHelper::getBundleAsSKU ( $farter_sku );
			// SysLogHelper::SysLog_Create("product",__CLASS__, __FUNCTION__,"", print_r($childrenSkus_had,true), "trace");
			$childrenSkuArr = array ();
			foreach ( $childrenSkus_had as $achild ) {
				$childrenSkuArr [] = $achild ['sku'];
			}
			$childrenSkus_had = $childrenSkuArr;
			$asskus_arr = array ();
			$bundleRelationshipStr = trim ( $values ['Product'] ['bundle'] ['relationship'] );
			$bundleRelationshipList = explode ( "&", $bundleRelationshipStr );
			for($i = 0; $i < count ( $bundleRelationshipList ); $i ++) {
				$assku = '';
				$qty = '';
				if ($bundleRelationshipList [$i] == '')
					continue;
				else {
					$RelationFields = explode ( ";", $bundleRelationshipList [$i] );
					for($j = 0; $j < count ( $RelationFields ); $j ++) {
						$FieldKV = explode ( ":", $RelationFields [$j] );
						for($k = 0; $k < count ( $FieldKV ); $k ++) {
							if ($FieldKV [0] == 'sku') {
								$assku = $FieldKV [1];
								$asskus_arr [] = $assku;
							}
							if ($FieldKV [0] == 'qty')
								$qty = $FieldKV [1];
						}
					}
				}
				$HadRelationship = ProductBundleRelationship::findByAttributes ( array (
						'assku' => $assku,
						'bdsku' => $farter_sku 
				) );
				if ($HadRelationship == null) {
					$command = Yii::$app->get('subdb')->createCommand ()->insert ( 'pd_product_bundle_relationship', array (
							'bdsku' => $farter_sku,
							'assku' => $assku,
							'qty' => $qty,
							'create_date' => date ( 'Y-m-d H:i:s', time () ) 
					) );
					if (! $command)
						return array (
								'??????' => 'relationship create error' 
						);
					else
						continue;
				} else {
					$HadRelationship->qty = $qty;
					$HadRelationship->save ();
				}
			}
			$needToDels = array_diff ( $childrenSkus_had, $asskus_arr );
			if (count ( $needToDels ) > 0) {
				foreach ( $needToDels as $delProd ) {
					$relationship = ProductBundleRelationship::findByAttributes ( array (
							'assku' => $delProd,
							'bdsku' => $farter_sku 
					) );
					$relationship->delete ();
				}
			}
			break;
		}
		return true;
	}
	
	/**
	 * +----------------------------------------------------------
	 * ????????????????????????????????? sku
	 * ????????? sku ???pd_product_bundle_relationship ????????? ?????????sku
	 * ????????????????????????????????????????????????
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	sku			??????SKU
	 *+----------------------------------------------------------
	 * @return sku?????? ??? ????????????
	 *+----------------------------------------------------------
	 *	log			name		date				note
	 * @author 		lzhl 		2014/11/12			?????????
	 *+----------------------------------------------------------
	 */
	public static function getBundleProductSKUs($sku) {
		$result = array ();
		$bundle_ships = ProductBundleRelationship::find ( 'assku = :sku', array (
				':sku' => $sku 
		) );
		if (empty ( $bundle_ships )) {
			return $result;
		} else {
			foreach ( $bundle_ships as $aship ) {
				$result [] = $aship->bdsku;
			}
		}
		return $result;
	}
	
	/**
	 * +----------------------------------------------------------
	 * ????????????????????????????????? sku?????????qty
	 * ???????????????????????????????????????????????????
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	sku			??????SKU
	 *+----------------------------------------------------------
	 * @return array sku??????
	 *+----------------------------------------------------------
	 *         log			name			date			note
	 * @author lzhl 2014/11/12			?????????
	 *+----------------------------------------------------------
	 */
	public static function getBundleAsSKU($sku) {
		$result = array ();
		$bundle_ships = ProductBundleRelationship::findAll ( 'bdsku = :sku', array (
				':sku' => $sku 
		) );
		if (empty ( $bundle_ships )) {
			return $result;
		} else {
			foreach ( $bundle_ships as $aship ) {
				$result [] = array (
						'sku' => $aship->assku,
						'qty' => $aship->qty 
				);
			}
		}
		return $result;
	}
	
	/**
	 * +----------------------------------------------------------
	 * ???????????????eagle???????????????????????????
	 * 
	 * ?????? ??????????????? ?????? ???C , F ??? ??????????????????????????? 
	 *
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	productid product id
	 * @param
	 *        	sku ??????SKU
	 * @param
	 *        	type ?????????????????????
	 *+----------------------------------------------------------
	 * @return array ( message=>'???????????????
	 *         aliasexist ?????????????????? ;
	 *         skuexist' productid = sku ?????????????????????????????? ???
	 *         sku_alias ???????????????????????????;
	 *         sku :??????????????????;
	 *         alias : ?????????????????? )
	 *+----------------------------------------------------------
	 * log			name	date					note
	 * @author 		lkh 	2014/10/23				?????????
	 *+----------------------------------------------------------
	 */
	static function saveRelationProduct($productid, $sku, $type = 'ebay', $pkid = null) {
		$productid = trim ( $productid );
		$sku = trim ( $sku );
		$type = strtolower($type);
		//A check up this product id whether active
		$alias_root_sku = self::getRootSkuByAlias ( $productid );
		
		//B if this product id is active , then skip it
		if (! empty ( $alias_root_sku ))
			return array (
					'message' => 'aliasexist' 
			);
			
			//C check up this sku whether active
		$root_sku = self::getRootSkuByAlias ( $sku );
		$model = new Product ();
		
		if (empty ( $root_sku )) {
			//C here sku is not active , create this product
			if ($type == 'ebay') {
				
				//C1-1 get product data
				$get_product_data = EbayItem::find ( 'itemid=:itemid', array (
						':itemid' => $productid 
				) );
				
				//C1-2 create product
				$product_info ['Product'] ['sku'] = $sku;
				$product_info ['Product'] ['name'] = $get_product_data->itemtitle;
				// $product_info['Product']['prod_name_en'] = $get_product_data->itemtitle;
				// $product_info['Product']['prod_name_ch'] = $sku;
				// $product_info['Product']['declaration_ch'] = $sku;
				
				$product_info ['Product'] ['photo_primary'] = $get_product_data->mainimg;
				if ($root_sku != $productid) {
					$product_info ['ProductAliases'] ['alias_sku'] [] = $productid;
					$product_info ['ProductAliases'] ['pack'] [] = 1;
					$product_info ['ProductAliases'] ['forsite'] [] = '';
					$product_info ['ProductAliases'] ['comment'] [] = 'ebay:' . $get_product_data->selleruserid;
					$result ['message'] = 'sku_alias';
				} else {
					$result ['message'] = 'alias';
				}
			} else if ($type == 'amazon') {
				
				if (! empty ( $pkid )) {
					//C2-1-a get product data
					$get_product_data = AmazonItem::find( 'id=:itemid', array (
							':itemid' => $pkid 
					) );
				} else {
					//C2-1-b get product data
					$get_product_data = AmazonItem::find ( 'ASIN=:itemid', array (
							':itemid' => $productid 
					) );
				}
				
				//C2-2 create product
				$product_info ['Product'] ['sku'] = $sku;
				
				$product_info ['Product'] ['name'] = ((strlen ( $get_product_data->Title ) > 255) ? substr ( $get_product_data->Title, 0, 255 ) : $get_product_data->Title);
				
				if (! empty ( $get_product_data->SmallImage )) {
					$smallimage = json_decode ( $get_product_data->SmallImage, true );
					if (! empty ( $smallimage ['ns2_URL'] )) {
						$SmallImageUrl = $smallimage ['ns2_URL'];
					} else {
						$SmallImageUrl = '';
					}
				}
				
				$product_info ['Product'] ['photo_primary'] = $SmallImageUrl;
				if ($root_sku != $productid) {
					if ($sku == $get_product_data->ASIN) {
						$result ['message'] = 'sku';
					} else {
						$product_info ['ProductAliases'] ['alias_sku'] [] = $productid;
						$product_info ['ProductAliases'] ['pack'] [] = 1;
						$product_info ['ProductAliases'] ['forsite'] [] = '';
						$product_info ['ProductAliases'] ['comment'] [] = 'amazon';
						$result ['message'] = 'sku_alias';
					}
				} else {
					$result ['message'] = 'alias';
				}
			}elseif ($type == 'wish'){
				//wish  @todo 
				//C3-1 start to get product data
				//C3-1-a get variance data 
				$wish_variance_data = WishFanbenVariance::find()
				->andWhere(['variance_product_id'=>$productid])
				->asArray()
				->One();
				
				$wish_fanben_data = WishFanben::find()
				->andWhere(['parent_sku'=>$wish_variance_data['parent_sku']])
				->asArray()
				->One();
				//C3-1-b get fanben data
				//C3-1 end of get product data
				
				//C3-2 create product
				$product_info ['Product'] ['sku'] = $sku;
				
				$product_info ['Product'] ['name'] = ((strlen ( $wish_fanben_data['name'] ) > 255) ? substr ( $wish_fanben_data['name'], 0, 255 ) : $wish_fanben_data['name']);
				
				$product_info ['Product']['photo_others'] = '';
				
				$product_info ['Product'] ['photo_primary'] = $wish_fanben_data['main_image'];
				
				if (strtoupper($wish_variance_data['enable']) == 'Y')
					$product_info ['Product']['status'] = 'OS';
				else
					$product_info ['Product']['status'] = 'DR';
				
				//check the other photo 
				for($i=1;$i<=10;$i++){
					// extra image not empty , then set product photo others 
					if (!empty($wish_fanben_data['extra_image_'.$i])){
						$product_info ['Product']['photo_others'] .= empty($product_info ['Product']['photo_others'] )?"":"@,@";
						$product_info ['Product']['photo_others'] .= $wish_fanben_data['extra_image_'.$i];
					}
				}
				
				$product_info ['Product']['photo_others'] = [];
				
				if ($root_sku != $productid) {
					$product_info ['ProductAliases'] ['alias_sku'] [] = $productid;
					$product_info ['ProductAliases'] ['pack'] [] = 1;
					$product_info ['ProductAliases'] ['forsite'] [] = '';
					
					$store_name = SaasWishUser::find()
					->select(['store_name'])
					->Where(['site_id'=>$wish_fanben_data ['site_id']])
					->asArray()
					->One();
					$product_info ['ProductAliases']['comment'] = 'wish:'.(empty($store_name['store_name'])?"":$store_name['store_name']);
					$result ['message'] = 'sku_alias';
				} else {
					$result ['message'] = 'alias';
				}
				
			}
			
			
			$product_info ['Product'] ['create_source'] = $type; // set up create_source
			$product_info ['Product'] ['type'] = 'S';
			self::saveProduct ( $model, $product_info );
			return $result;
		} else {
			//D if root sku equal producted then skip it .
			if ($root_sku == $productid)
				return array (
						'status' => false,
						'message' => 'skuexist' 
				);
				
				//E here sku is active , then add alias
			$has_alias = ProductAliases::find ( 'alias_sku = :alias_sku', array (
					':alias_sku' => $productid 
			) );
			
			if (empty ( $has_alias )) {
				
				//F get product data
				if ($type == 'ebay') {
					
					$get_product_data = EbayItem::find ( 'itemid=:itemid', array (
							':itemid' => $productid 
					) );
					$aliasInfo ['comment'] = 'ebay:' . $get_product_data->selleruserid;
				} elseif ($type == 'amazon') {
					$get_product_data = AmazonItem::find ( 'product_id=:itemid', array (
							':itemid' => $productid 
					) );
					$aliasInfo ['comment'] = 'amazon';
				}elseif($type == 'wish'){
					//@todo
					$site_id = Yii::$app->get('subdb')->createCommand("select site_id from wish_fanben_variance v , wish_fanben f   where f.parent_sku = v.parent_sku and   v.variance_product_id = '".$productid."'")->queryScalar();
					$store_name = SaasWishUser::find()
					->select(['store_name'])
					->Where(['site_id'=>$site_id])
					->asArray()
					->One();
					$aliasInfo ['comment'] = 'wish:'.(empty($store_name['store_name'])?"":$store_name['store_name']);
				}
				
				$aliasInfo ['alias'] = $productid;
				$aliasInfo ['pack'] = 1;
				$aliasInfo ['forsite'] = '';
				
				self::addonealias ( $root_sku, $aliasInfo );
				$result ['message'] = 'alias';
				return $result;
			} else {
				return array (
						'status' => false,
						'message' => 'aliasexist' 
				);
			}
		}
	} // end of saveRelationProduct
	
	/**
	 * +----------------------------------------------------------
	 * ????????????????????????????????????????????????????????????sku ??? ????????????
	 *
	 * +----------------------------------------------------------
	 * 
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	sku ??????SKU
	 * @param
	 *        	aliasInfo		???????????? ?????? array(alias=>???????????? ???pack =>?????????forsite=>???????????????comment=>??????)
	 *+----------------------------------------------------------
	 * @return na 
	 * +----------------------------------------------------------
	 *	log			name	date					note
	 * @author 		lkh 	2014/10/28				?????????
	 *+----------------------------------------------------------
	 */
	static function addonealias($root_sku, $aliasInfo) {
		$model = new ProductAliases ();
		$model->sku = $root_sku;
		$model->alias_sku = $aliasInfo ['alias'];
		$model->pack = $aliasInfo ['pack'];
		$model->forsite = $aliasInfo ['forsite'];
		$model->comment = $aliasInfo ['comment'];
		
		if ($model->save ()) {
			// check the product id whether product sku
			$criteria = new CDbCriteria ();
			$criteria->addCondition ( "sku='" . $aliasInfo ['alias'] . "'" );
			$merge_alias_list = Product::findall ( $criteria );
			
			foreach ( $merge_alias_list as $one_merge_alias ) {
				// update alias related data
				self::updateAliasRelatedData ( $model->sku, $one_merge_alias->sku );
			}
			return array (
					'status' => true,
					'message' => '????????????');
    					}else{
    						return array('status'=>false,'message'=>'????????????');
    					}
	}//end of addonealias
	
	/**
	 * +----------------------------------------------------------
	 * product other attribute string ?????? array
	 *
	 * +----------------------------------------------------------
	 *
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	sku ??????SKU
	 * @param
	 *        	aliasInfo		???????????? ?????? array(alias=>???????????? ???pack =>?????????forsite=>???????????????comment=>??????)
	 *+----------------------------------------------------------
	 * @return na
	 * +----------------------------------------------------------
	 *	log			name	date					note
	 * @author 		lkh 	2015/03/14				?????????
	 *+----------------------------------------------------------
	 */
	static public function PordAttrconvertStringToArray($strAttr){
		$attrList = explode(';', $strAttr);
		//if (count($attrList) > 0) {
		foreach ($attrList as $attr)
		{
			$tmpKv = explode(':', $attr);
			$ArrAttr [] = array_combine(['key', 'value'] , $tmpKv);
		}
		return $ArrAttr;
	}//end of PordAttrconvertStringToArray
	
	/**
	 * +----------------------------------------------------------
	 * ????????????sku ??????????????? sku?????????????????????
	 * +----------------------------------------------------------
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 *        	sku ??????????????????sku
	 *+----------------------------------------------------------
	 * @return array(
	 *	Sku=>???sk1???,???name???=>???computer???,...  Type=???Bundle???, 
	 *	Children = ???0???=>[sku=??????, name=??????] , ???1???=>[sku=??????,name=??????]
	 *	)
	 *+----------------------------------------------------------
	 * log			name	date					note
	 * @author 		lkh 	2015/04/30				?????????
	 *+----------------------------------------------------------
	 */
	static public function getProductInfo($sku){
		global $CACHE;
		//$root_sku = self::getRootSkuByAlias($sku);
		$root_sku = $sku;
		// get product info 
		//2016-07-04  ???????????????????????????global cache ??????????????? start
		$uid = \Yii::$app->subdb->getCurrentPuid();
		if (isset($CACHE[$uid]['product'][$root_sku])){
			$prodInfo = $CACHE[$uid]['product'][$root_sku];
			
			
			//log ?????? ??? ??????????????????start
			$GlobalCacheLogMsg = "\n puid=$uid ".(__function__).' product has cache';
			\eagle\modules\order\helpers\OrderBackgroundHelper::OrderCheckGlobalCacheLog($GlobalCacheLogMsg);
			//log ?????? ??? ??????????????????end
		}else{
			$prodInfo = Product::find()->andWhere(['sku'=>$root_sku])->asArray()->One();
			
			
			//log ?????? ??? ??????????????????start
			$GlobalCacheLogMsg = "\n puid=$uid ".(__function__).' product no cache';
			\eagle\modules\order\helpers\OrderBackgroundHelper::OrderCheckGlobalCacheLog($GlobalCacheLogMsg);
			//log ?????? ??? ??????????????????end
			
		}
		
		//2016-07-04  ???????????????????????????global cache ??????????????? end
		switch (strtoupper($prodInfo['type'])){
			case "B" : 
				// bundle product , then get it children
				$prodInfo['children'] = [];
				//2016-07-04  ???????????????????????????global cache ??????????????? start
				if (isset($CACHE[$uid]['bundleRelation'])){
					$childrens = $CACHE[$uid]['bundleRelation'][$prodInfo['sku']];
					
					//log ?????? ??? ??????????????????start
					$GlobalCacheLogMsg = "\n puid=$uid ".(__function__).' bundleRelation has cache';
					\eagle\modules\order\helpers\OrderBackgroundHelper::OrderCheckGlobalCacheLog($GlobalCacheLogMsg);
					//log ?????? ??? ??????????????????end
				}else{
					$childrens = ProductBundleRelationship::find()->where(['bdsku'=>$prodInfo['sku']])->asArray()->all();
					
					//log ?????? ??? ??????????????????start
					$GlobalCacheLogMsg = "\n puid=$uid ".(__function__).' bundleRelation no cache';
					\eagle\modules\order\helpers\OrderBackgroundHelper::OrderCheckGlobalCacheLog($GlobalCacheLogMsg);
					//log ?????? ??? ??????????????????end
				}
				//2016-07-04  ???????????????????????????global cache ??????????????? end
				
				foreach ($childrens as $child){
					//2016-07-04  ???????????????????????????global cache ??????????????? start
					if (isset($CACHE[$uid]['bundleRelation'])){
						$childInfo = $CACHE[$uid]['product'][$child['assku']];
						
						//log ?????? ??? ??????????????????start
						$GlobalCacheLogMsg = "\n puid=$uid ".(__function__).' child product has cache';
						\eagle\modules\order\helpers\OrderBackgroundHelper::OrderCheckGlobalCacheLog($GlobalCacheLogMsg);
						//log ?????? ??? ??????????????????end
					}else{
						$childInfo = Product::find()->where(['sku'=>$child['assku']])->asArray()->one();
						
						//log ?????? ??? ??????????????????start
						$GlobalCacheLogMsg = "\n puid=$uid ".(__function__).' child product no cache';
						\eagle\modules\order\helpers\OrderBackgroundHelper::OrderCheckGlobalCacheLog($GlobalCacheLogMsg);
						//log ?????? ??? ??????????????????end
					}
					//2016-07-04  ???????????????????????????global cache ??????????????? end
					if(empty($childInfo))
						break;
					$row=$childInfo;
					$row['qty']=$child['qty'];
					$prodInfo['children'][]=$row;
				}
				break;
			case "C" :
				// configure product , then get it configure filed
				$prodInfo['children'] = [];
				//2016-07-04  ???????????????????????????global cache ??????????????? start
				if (isset($CACHE[$uid]['configRelation'])){
					$childrens = $CACHE[$uid]['configRelation'][$prodInfo['sku']];
					
					//log ?????? ??? ??????????????????start
					$GlobalCacheLogMsg = "\n puid=$uid ".(__function__).' configRelation has cache';
					\eagle\modules\order\helpers\OrderBackgroundHelper::OrderCheckGlobalCacheLog($GlobalCacheLogMsg);
					//log ?????? ??? ??????????????????end
				}else{
					$childrens = ProductConfigRelationship::find()->where(['cfsku'=>$prodInfo['sku']])->asArray()->all();
					
					//log ?????? ??? ??????????????????start
					$GlobalCacheLogMsg = "\n puid=$uid ".(__function__).' configRelation no cache';
					\eagle\modules\order\helpers\OrderBackgroundHelper::OrderCheckGlobalCacheLog($GlobalCacheLogMsg);
					//log ?????? ??? ??????????????????end
				}
				//2016-07-04  ???????????????????????????global cache ??????????????? end
				
				foreach ($childrens as $child){
					//2016-07-04  ???????????????????????????global cache ??????????????? start
					if (isset($CACHE[$uid]['configRelation'])){
						$childInfo = $CACHE[$uid]['product'][$child['assku']];
					}else{
						$childInfo = Product::find()->where(['sku'=>$child['assku']])->asArray()->one();
					}
					//2016-07-04  ???????????????????????????global cache ??????????????? end
					
					if(empty($childInfo))
						break;
					$row=$childInfo;
					$row['qty']=1;
					$prodInfo['children'][]=$row;
				}
				break;
			default:
				//here normal product , nothing to do 
				//$prodInfo['type_label'] = 'normal';
		}
		return $prodInfo;
	}//end of getProductInfo
	
	
	/**
	 * +----------------------------------------------------------
	 * ??????????????????????????????????????????
	 * +----------------------------------------------------------
	 * @access static
	 *+----------------------------------------------------------
	 * @param
	 * 			$sku		??????????????????sku
	 *        	$aliasList 	???????????? alias 
	 *+----------------------------------------------------------
	 * @return array(
	 *	'success'=>???true???,???message???=>????????????????????????
	 *	)
	 *	@success boolean ??????????????????  true ?????????  false ????????? 
	 *	@message string   ???alias ????????? ?????? ?????????
	 *+----------------------------------------------------------
	 * log			name	date					note
	 * @author 		lkh 	2015/05/05				?????????
	 *+----------------------------------------------------------
	 */
	static public function checkAlias($sku, $aliasList){
		try {
			// ?????? sku ????????????
			if (!empty($sku)){
				// ?????? alias ????????????
				if (isset($aliasList)   ){
					if (is_array($aliasList)){
						// ?????? ?????? alias
						foreach($aliasList as $oneAlias){
							$PDAliasList = $oneAlias;
							$result = self::checkProductAlias($sku, $PDAliasList);
							if ($result ['status'] == "failure"){
								$result ['success'] = false;
								break;
							}
						}
						 
					}else{
						// ?????? ?????? alias
						$PDAliasList = $aliasList;
						$result = self::checkProductAlias($sku, $PDAliasList);
					}
						
				}else{
					// alias ??????
					$result = array(
							'success'=>false ,
							'message'=>'??????????????????' ,
					);
				}
		
			}else{
				//sku ??????
				$result = array(
						'success'=>false ,
						'message'=>'??????????????????' ,
				);
			}
			 
		} catch (Exception $e) {
			// ????????????
			$result = array(
					'success'=>false ,
					'message'=>$e->getMessage() ,
			);
		}
		return $result;
	}// end of checkAlias
	
	/**
	 * ????????????sku?????????config??????
	 * @access static
	 * @param	$sku	??????????????????sku
	 *        	$type 	'cfsku'->???sku ???'assku'->???sku
	 * @return  true
	 * log		name	date		note
	 * @author 	lzhl 	2015/05/05	?????????
	 */
	public static function removeConfigRelationship($sku,$type){
		if($type =='cfsku'){
			//????????????????????????????????????????????????
			$relationship = ProductConfigRelationship::findAll(['cfsku'=>$sku]);
			foreach ($relationship as $relation){
				$Child = Product::findOne(['sku'=>$relation->assku]);
				if(!empty($Child)){
					$Child->type='S';
					$Child->save(false);
				}
			}
			//??????????????????
			ProductConfigRelationship::deleteAll(['cfsku'=>$sku]);
		}
		if($type =='assku'){
			ProductConfigRelationship::deleteAll(['assku'=>$sku]);
		}
	}
	
	/**
	 * ????????????sku?????????bundle??????
	 * @access static
	 * @param	$sku	??????????????????sku
	 *        	$type 	'cfsku'->???sku ???'assku'->???sku
	 * @return  true
	 * log		name	date			note
	 * @author 	lzhl 	2015/05/013		?????????
	 */
	public static function removeBundleRelationship($sku,$type){
		if($type =='bdsku'){
			//????????????????????????????????????????????????
			$relationship = ProductBundleRelationship::findAll(['bdsku'=>$sku]);
			foreach ($relationship as $relation){
				$Child = Product::findOne(['sku'=>$relation->assku]);
				if(!empty($Child)){
    				$Child->type='S';
    				$Child->save(false);
				}
			}
			//??????????????????
			ProductBundleRelationship::deleteAll(['bdsku'=>$sku]);
		}
		if($type =='assku'){
			ProductBundleRelationship::deleteAll(['assku'=>$sku]);
		}
	}
	

	/**
	 * +----------------------------------------------------------
	 * ?????????????????????????????????????????????????????????
	 * +----------------------------------------------------------
	 * @access static
	 *+----------------------------------------------------------
	 * @param	array
	 *+----------------------------------------------------------
	 * @return 	????????????Array
	 * +----------------------------------------------------------
	 * log			name	date					note
	 * @author 		lkh		2015/03/27				?????????
	 *+----------------------------------------------------------
	 *
	 */
	public static function importProductCostData($data){
		$rtn['success'] = true;
		$rtn['message'] = '';
		$errMsg = '';
		$journal_id = SysLogHelper::InvokeJrn_Create("Catalog", __CLASS__, __FUNCTION__ ,array($data));
	
		foreach ( $data as $index => $item ) {
			$sku = trim($item['sku']);
			//update to pd_product
			$pd = Product::findOne($sku);
			if(!empty($pd)){
				$purchase_price = floatval($item['purchase_price']);
				$additional_cost = floatval($item['additional_cost']);
				$transaction = Yii::$app->get('subdb')->beginTransaction();
				$pd->purchase_price = $purchase_price;
				$pd->additional_cost = $additional_cost;
				if(!$pd->save(false)){
					$rtn['success'] = false;
					$rtn['message'] .= '??????'.$sku.'?????????????????????;<br>';
					$transaction->rollBack();
					$errMsg .= print_r($pd->getErrors(),true);
					SysLogHelper::SysLog_Create('Catalog',__CLASS__, __FUNCTION__,'error',print_r($pd->getErrors(),true));
					continue;
				}
				//update to pd_product_supplier when update to pd_product successed
				$pdSupplier = ProductSuppliers::find()->where(['sku'=>$sku])->orderBy("priority ASC")->limit(1)->offset(0)->One();
				if(!empty($pdSupplier)){
					//????????????????????????????????????????????????????????????????????????
					$pdSupplier->purchase_price = $purchase_price;
					if(!$pdSupplier->save()){
						$errMsg .= print_r($pdSupplier->getErrors(),true);
						$transaction->rollBack();
						SysLogHelper::SysLog_Create('Catalog',__CLASS__, __FUNCTION__,'error',print_r($pdSupplier->getErrors(),true));
						$rtn['success'] = false;
						$rtn['message'] .= '??????'.$sku.'????????????????????????????????????;<br>';
						continue;
					}
				}
				$transaction->commit();
			}else{
				$rtn['message'] .= '??????'.$sku.'??????????????????????????????????????????;<br>';
			}
		}
	
		$rtn['errMsg'] = $errMsg;
		SysLogHelper::InvokeJrn_UpdateResult($journal_id, $rtn);
	
		return $rtn;
	}
	
	/**
	 * +----------------------------------------------------------
	 * ????????????Excel
	 * +----------------------------------------------------------
	 * @access static
	 *+----------------------------------------------------------
	 * @param	$data    ??????????????????Id??????????????????
	 * @param	$type    ??????????????????
	 *+----------------------------------------------------------
	 * @return 	
	 * +----------------------------------------------------------
	 * log			name	date					note
	 * @author 		lrq		2016/12/14				?????????
	 *+----------------------------------------------------------
	 *
	 */
	public static function ExportProductExcel($data, $type = false){
		$rtn['success'] = 1;
		$rtn['message'] = '';
		
		try{
		    $products = array();
    		$journal_id = SysLogHelper::InvokeJrn_Create("Catalog", __CLASS__, __FUNCTION__ ,array($data));
    		
    		$product_ids = array();
    		if(!empty($data)){
    		    foreach($data as $v){
    		        $product_ids[] = $v;
    		    }
    		}
    		
    		if(count($product_ids) == 11 && $product_ids[0] == 'search contidion'){
    		    
    		    $condition = array();
    		    if (isset($product_ids[1])){
    		        $val = $product_ids[1];
    		    	if (trim($val)!="" ){
    		    		$val = trim($val);
    		    		if (!empty($product_ids[9])){
    		    			if($product_ids[9] == 'sku'){
    		    				$condition [] = ['or'=>['like','sku', $val]];
    		    				$condition [] = ['or'=>'sku in (select `sku` from `pd_product_aliases` where `alias_sku`=\''.$val.'\')'];
    		    			}
    		    			else{
    		    				$condition [] = ['or'=>['like',$product_ids[9], $val]];
    		    			}
    		    		}
    		    		else{
	    		    		$condition [] = ['or'=>['like','sku', $val]];
	    		    		$condition [] = ['or'=>['like','name', $val]];
	    		    		$condition [] = ['or'=>['like','prod_name_ch', $val]];
	    		    		$condition [] = ['or'=>['like','prod_name_en', $val]];
	    		    		$condition [] = ['or'=>['like','declaration_ch', $val]];
	    		    		$condition [] = ['or'=>['like','declaration_en', $val]];
	    		    		$condition [] = ['or'=>'sku in (select `sku` from `pd_product_aliases` where `alias_sku`=\''.$val.'\')'];
	    		    		//$condition [] = ['or'=>'sku=(select `cfsku` from `pd_product_config_relationship` where `assku`=\''.$val.'\')'];
    		    		}
    		    	}
    		    }
    		    
    		    if (isset($product_ids[2])){
    		        $val = $product_ids[2];
    		    	if (trim($val)!="" && $val != "all"){
    		    		//?????? tag ????????????
    		    		$condition [] = ['and'=>['in', 'sku', (new Query())->select('sku')->from('pd_product_tags')->where(['tag_id' => $val])]];
    		    	}
    		    }
    		    
    		    if (isset($product_ids[3])){
    		        $val = $product_ids[3];
    		    	if (trim($val)!="" && $val != "all"){
    		    		//?????? brand ????????????
    		    		$condition [] = ['and'=>['brand_id'=> $val ]];
    		    	}
    		    }
    		    
    		    if (isset($product_ids[4])){
    		        $val = $product_ids[4];
    		    	if (trim($val)!="" && $val != "all"){
    		    		//?????? supplier ????????????
    		    		if(is_numeric($val) && !empty($val)){
    		    			$condition [] = ['and'=>['in', 'sku', (new Query())->select('sku')->from('pd_product_suppliers')->where(['supplier_id' => $val])]];
    		    		}
    		    		if(empty($val)){
    		    			$condition [] = ['and'=>[
    		    			'or',['sku'=>(new Query())->select('sku')->from('pd_product_suppliers')->where(['supplier_id' => $val])],['supplier_id'=>0]
    		    					]];
    		    		}
    		    	}
    		    }
    		    
    	    	if (isset($product_ids[5])){
    	    	    $val = $product_ids[5];
    		    	if (trim($val)!="" && $val != "all"){
    		    	//?????? status ????????????
    		    	$condition [] = ['and'=>['status'=>$val ]];
    		    	}
    	    	}
    	    	if (isset($product_ids[6])){
    	    	    $val = $product_ids[6];
    		    	if (trim($val) != "" && $val != "all"){
        			//?????? type ????????????
    		        	$condition [] = ['and'=>['type'=>$val ]];
    		    	}
    	    	}
    	    	if (isset($product_ids[10]) && $product_ids[10] != ''){
    	    		//?????? class_id ????????????
    	    		$condition [] = ['and'=>['class_id'=>$product_ids[10] ]];
    	    	}
    		    
    	    	$data = ProductHelper::getProductlist($condition, $product_ids[7], $product_ids[8], 20, true);
    		    $product_ids = array();
    		    foreach ($data['data'] as $d){
    		        //????????????????????????????????????????????????
    		        if($d['type'] == 'C')
    		            continue;
    		        
    		        $product_ids[] = $d['product_id'];
    		    	$products[$d['product_id']] = $d;
    		    }
    		    unset($data);
    		}
    		else{
    		    $skus = array();
    		    $pro_sku = array();
    		    $condition = array();
    		    foreach($product_ids as $id){
    		    	$condition[] = ['or'=>"product_id=$id"];
    		    }
    		    $data = ProductHelper::getProductlist($condition, 'sku', 'asc', 20, true);
        		foreach ($data['data'] as $d){
        		    $products[$d['product_id']] = $d;
        		    $skus[] = $d['sku'];
        		    $pro_sku[$d['sku']] = $d;
        		}
        		unset($data);
        		
        		//???????????????
        		$realArr = array();
        		$relationship = ProductConfigRelationship::find()->where(['cfsku'=>$skus])->asArray()->all();
        		foreach($relationship as $r){
        			$realArr[$r['cfsku']][] = $r['assku'];
        		}
        		
        		//????????????????????????????????????????????????????????????????????????
        		$ids = $product_ids;
        		$product_ids = array();
        		foreach ($ids as $id){
        		    if(!empty($products[$id])){
        		        $p = $products[$id];
        		        if($p['type'] == 'C' && !empty($realArr[$p['sku']])){
        		            foreach ($realArr[$p['sku']] as $r){
        		                if(!empty($pro_sku[$r])){
        		                    $product_ids[] = $pro_sku[$r]['product_id'];
        		                }
        		            }
        		        }
        		        else{
        		            $product_ids[] = $id;
        		        }
        		    }
        		}
        		unset($pro_sku);
    		}
    		
    		$items_arr = ['sku'=>'SKU', 'name'=>'????????????', 'class_name'=>'??????', 'brand_name'=>'??????', 'prod_name_ch'=>'??????????????????', 'prod_name_en'=>'??????????????????', 'declaration_ch'=>'???????????????', 'declaration_en'=>'???????????????', 'declaration_value'=>'????????????', 'declaration_value_currency'=>'????????????', 'prod_weight'=>'??????', 'prod_length'=>'???(cm)', 'prod_width'=>'???(cm)', 
    		                'prod_height'=>'???(cm)', 'other_attributes'=>'??????', 'supplier_name'=>'???????????????', 'purchase_price'=>'?????????(CNY)', 'photo_primary'=>'?????????', 'photo_2'=>'??????2', 'photo_3'=>'??????3', 'photo_4'=>'??????4', 'photo_5'=>'??????5', 'tags'=>'????????????', 'alias_sku'=>'????????????', 'declaration_code'=>'????????????', 'purchase_link'=>'????????????', 'comment'=>'??????'];
    		
    		$excel_file_name = array();
    		$excel_data = array();
    		$skus = array();
    		$keys = array_keys($items_arr);
    		
    		//sku??????
    		$sku_list = array();
    		foreach ($products as $val){
    		    $sku_list[] = $val['sku'];
    		}
    		//??????
    		$brandList = BrandHelper::ListBrandData();
    		//?????????
    		$supplierList = ProductSuppliersHelper::ListSupplierData();
    		//??????
    		$tags = array();
    		$tagArr = Tag::find()->asArray()->All();
    		foreach ($tagArr as $t){
    		    $tags[$t['tag_id']] = $t['tag_name'];
    		}
    		//??????????????????
    		$class_id_arr = array();
    		$class_number_arr = array();
    		$classlist = ProductClassification::find()->asArray()->All();
    		foreach ($classlist as $class){
    			$class_id_arr[$class['ID']] = $class['number'];
    			$class_number_arr[$class['number']] = $class;
    		}
    		
    		$ptagList = array();
    		$pro_tagArr = ProductTags::find()->select(['tag_id', 'sku'])->Where(['sku'=>$sku_list])->asArray()->All();
    		foreach ($pro_tagArr as $t){
    		    if(!empty($tags[$t['tag_id']])){
    		        $sku = strtolower($t['sku']);
    		        $name = $tags[$t['tag_id']];
    		        if(empty($ptagList[$sku]) || !in_array($name, $ptagList[$sku])){
    		            $ptagList[$sku][] = $name;
    		        }
    		    }
    		}
    		
    		//??????
    		$aliasList = array();
    		$aliasArr = ProductAliases::find()->select(['sku','alias_sku'])
    			->Where(['sku'=>$sku_list])
    			->andWhere("sku!=alias_sku")
    			->asArray()->All();
    		foreach ($aliasArr as $a){
    			$aliasList[strtolower($a['sku'])][] = $a['alias_sku'];
    		}
    		
    		//???2???3
    		$photoList = array();
    		$photoArr = Photo::find()->select(['sku','photo_url'])
	    		->Where(['sku'=>$sku_list])
	    		->andWhere("priority!=0")
	    		->orderBy("priority")
	    		->asArray()->All();
    		foreach ($photoArr as $p){
    			$photoList[strtolower($p['sku'])][] = $p['photo_url'];
    		}
    		
    		foreach ($product_ids as $index => $id){
    		    $p = $products[$id];
    		    $sku_low = strtolower($p['sku']);
    		    
    		    if(in_array($sku_low, $skus))
    		        continue;
    		    
    		    $tmp = [];
    		    foreach ($keys as $key){
    		        if(isset($p[$key])){
    		            if(in_array($key, ['sku'])){
    		                $tmp[$key] = ' '.$p[$key];
    		            }
    		            else{
    		                $tmp[$key] = $p[$key];
    		            }
    		        }
    		        else{
    		            $tmp[$key] = ' ';
    		        }
    		        
    		        if($index == 0){
    		        	$excel_file_name[] = $items_arr[$key];
    		        }
    		    }
    		    
    		    //????????????no-img.png???????????????
    		    if(!empty($tmp['photo_primary']) && str_replace("no-img.png", "", $tmp['photo_primary']) != $tmp['photo_primary']){
    		        $tmp['photo_primary'] = '';
    		    }
    		    
    		    //??????
    		    if (!empty($ptagList[$sku_low])){
    		        foreach ($ptagList[$sku_low] as $v){
    		            $tmp['tags'] = $tmp['tags'] == ' ' ? $v :$tmp['tags'].','.$v; 
    		        }
    		    }
    		    
    		    //???????????????
    		    if (!empty($supplierList[$p['supplier_id']]['name'])){
    		    	$tmp['supplier_name'] = $supplierList[$p['supplier_id']]['name'];
    		    }
    		    
    		    //??????
    		    if (!empty($brandList[$p['brand_id']]['name'])){
    		    	$tmp['brand_name'] = $brandList[$p['brand_id']]['name'];
    		    }
    		    
    		    //??????
    		    if (!empty($aliasList[$sku_low])){
    		    	foreach ($aliasList[$sku_low] as $v){
    		    		$tmp['alias_sku'] = $tmp['alias_sku'] == ' ' ? $v :$tmp['alias_sku'].','.$v;
    		    	}
    		    }
    		    //??????
    		    if (!empty($p['class_id']) && array_key_exists($p['class_id'], $class_id_arr)){
    		    	$number = $class_id_arr[$p['class_id']];
    		    	$name_str = '';
    		    	//????????????????????????
    		    	for($n = 1; $n < 6; $n++){
    		    		if(array_key_exists($number, $class_number_arr)){
    		    			$name_str = $class_number_arr[$number]['name'].','.$name_str;
    		    			$number = $class_number_arr[$number]['parent_number'];
    		    			
    		    			if(empty($number)){
    		    				break;
    		    			}
    		    		}
    		    		else{
    		    			break;
    		    		}
    		    	}
    		    	
	    		    $tmp['class_name'] = rtrim($name_str, ",");
    		    }
    		    
    		    //???2???3???4
    		    $tmp['photo_2'] = empty($photoList[$sku_low][0]) ? '' : $photoList[$sku_low][0];
    		    $tmp['photo_3'] = empty($photoList[$sku_low][1]) ? '' : $photoList[$sku_low][1];
    		    $tmp['photo_4'] = empty($photoList[$sku_low][2]) ? '' : $photoList[$sku_low][2];
    		    $tmp['photo_5'] = empty($photoList[$sku_low][3]) ? '' : $photoList[$sku_low][3];
    		    
    		    $skus[] = $sku_low;
    		    $excel_data[$index] = $tmp;
    		    
    		    unset($p);
    		    unset($tmp);
    		}
    		unset($product_ids);
    		
    		\Yii::info('ExportProductExcel, puid: '.\Yii::$app->subdb->getCurrentPuid().', count: '. count($excel_data), "file");
    		
    		//$sheetInfo = [['data_array'=>$excel_data , 'filed_array'=>$excel_file_name,'title'=>'Sheet1']];
    		//$rtn = ExcelHelper::exportToExcel($excel_data, $excel_file_name,'productL_'.date('Y-m-dHis',time()).".xls", ['photo_primary'=>['width'=>50,'height'=>50]],$type);
    		$rtn = ExcelHelper::exportToExcel($excel_data, $excel_file_name,'productL_'.date('Y-m-dHis',time()).".xls", [], $type);
    		
    		SysLogHelper::InvokeJrn_UpdateResult($journal_id, $rtn);
    		$rtn['count'] = count($excel_data);
    		unset($excel_data);
		}
		catch (\Exception $e) {
			$rtn['success'] = 0;
			$rtn['message'] = '???????????????'.$e->getMessage();
		}
		
		return $rtn;
	}
	/**
	 * +----------------------------------------------------------
	 * ????????????
	 * +----------------------------------------------------------
	 * @access static
	 *+----------------------------------------------------------
	 * @param	$merge_sku    string    ?????????SKU
	 * @param	$be_sku_arr   array     ?????????SKU???
	 *+----------------------------------------------------------
	 * @return
	 * +----------------------------------------------------------
	 * log			name	date					note
	 * @author 		lrq		2017/05/02				?????????
	 *+----------------------------------------------------------
	 *
	 */
	public static function MergeProduct($merge_sku, $be_sku_arr){
	    $ret['success'] = true;
	    $ret['msg'] = '';
	    $edit_log = '';
	    $log_key_id = '';
	    
	    if(in_array(trim($merge_sku), $be_sku_arr)){
	    	$ret['success'] = false;
	    	$ret['msg'] .= "??????SKU???????????????????????????";
	    	return $ret;
	    }
	    //??????????????????????????????????????????
	    $model = Product::find()->where(['sku' => $merge_sku])->andWhere("type='S'||type='L'")->one();
	    if(!empty($model)){
	    	$exist_sku = array();
    	    foreach($be_sku_arr as $sku){
    	    	if(in_array($sku, $exist_sku)){
    	    		continue;
    	    	}
    	        //???????????????????????????
    	        $model = Product::find()->where(['sku' => $sku])->andWhere("type='S'||type='L'")->one();
    	        if(!empty($model)){
    	            $journal_id = SysLogHelper::InvokeJrn_Create("Catalog", __CLASS__, __FUNCTION__ ,['merge_sku' => $merge_sku, 'sku' => $sku]);
    	            
        	        $re = self::updateAliasRelatedData($merge_sku, $sku);
        	        
        	        if(!empty($re) && !empty($re['??????'][0])){
        	            $ret['success'] = false;
        	            $ret['msg'] .= "?????????SKU???<span style='color: red;'>$sku </span>???????????????".$re['??????'][0]."<br>";
        	            SysLogHelper::InvokeJrn_UpdateResult($journal_id, $ret);
        	        }
        	        else{
        	            SysLogHelper::InvokeJrn_UpdateResult($journal_id, ['success' => 1, 'msg' => '']);
        	            
        	            //??????????????????
        	            $edit_log .= $sku.", ";
        	        }
    	        }
    	        else{
    	        	$ret['success'] = false;
    	        	$ret['msg'] .= "?????????SKU???<span style='color: red;'>$sku </span>??????????????????????????????????????????????????????<br>";
    	        }
    	        $exist_sku[] = $sku;
    	    }
    	    
    	    if(!empty($edit_log)){
    	    	//??????????????????
    	    	$edit_log = "????????????, ??????SKU: $merge_sku, ?????????SKU: $edit_log";
    	    	$log_key_id = $model->product_id;
    	    	UserHelper::insertUserOperationLog('catalog', $edit_log, null, $log_key_id);
    	    }
	    }
	    else{
	        $ret['success'] = false;
	        $ret['msg'] = "????????????SKU???<span style='color: red;'>$merge_sku </span>??????????????????????????????????????????????????????";
	    }
	    
	    return $ret;
	}
	
	//????????????????????????
	public static function GetProductClass($type = 0, $classCount = array()){
	    //???????????????
	    $query = self::GetProductClassQuery();
	    $class = $query->asArray()->all();
	    $class_arr = self::GetChliNode('', $class);
		
		//????????????html
		if($type == 1){
			$html = self::ProductClassificaSettingHtml($class_arr);
		}
		else{
			$html = self::ProductClassificaHtml($class_arr, $classCount);
		}
		//print_r($html);die;
		return $html;
	}
	
	public static function GetProductClassQuery(){
		$query = ProductClassification::find()->where('length(number)<=6')->orderBy('number');
		return $query;
	}
	
	public static function GetChliNode($number, $list){
	    $node = [];
	    foreach($list as $key => $arr){
	        $parent_number = empty($arr['parent_number']) ? '' : $arr['parent_number'];
	        if($parent_number == $number){
	            $chil_node = self::GetChliNode($arr['number'], $list);
	            $node[] = [
	            	'node_id' => $arr['ID'],
    	            'number' => $arr['number'],
    	            'name' => $arr['name'],
    	            'chil_node' => $chil_node,
	            ];
	            unset($list[$key]);
	        }
	    }
	    return $node;
	}
	
	//????????????html
	public static function ProductClassificaHtml($node, $classCount){
		$node_html = '';
		foreach($node as $key => $val){
			$count = '';
			if(!empty($classCount)){
				$count = empty($classCount[$val['number']]) ? ' (0)' : ' ('.$classCount[$val['number']].')';
			}
			//??????????????????????????????????????????????????????html
			$chli_html = '';
			$open_html = '';
			if(!empty($val['chil_node'])){
				$chli_html = self::ProductClassificaHtml($val['chil_node'], $classCount);
				
				if(!empty($chli_html)){
					$chli_html = '<ul data-cid="0" style="display: block;">'.$chli_html.'</ul>';
					$open_html = '<span class="gly glyphicon pull-left glyphicon-triangle-bottom" data-isleaf="open"></span>';
				}
			}
			
			$choose_html = (!empty($_REQUEST['class_id']) && $_REQUEST['class_id'] == $val['node_id']) ? 'choose_class' : '';
			$node_html .= 
				'<li>
					<div class="'.$choose_html.'">
						'.$open_html.'
						<label>
							<span class="chooseTreeName" onclick="null" class_id="'.$val['node_id'].'">'.$val['name'].$count.'</span>
						</label>
					</div>
					'.$chli_html.'
				</li>';
		}
		return $node_html;
	}
	
	//??????????????????html
	public static function ProductClassificaSettingHtml($node){
		$node_html = '';
		foreach($node as $key => $val){
			//??????????????????????????????????????????????????????html
			$chli_html = '';
			$open_html = '';
			if(!empty($val['chil_node'])){
				$chli_html = self::ProductClassificaSettingHtml($val['chil_node']);
		
				if(!empty($chli_html)){
					$chli_html = '<ul style="display:block;margin-left:10px;margin-top:0px;">'.$chli_html.'</ul>';
					$open_html = '<span class="gly1 glyphicon-triangle-bottom class_tree_swith" data-isleaf="open"></span>';
				}
			}
			
			//??????????????????????????????????????????
			$add_html = strlen($val['number']) > 5 ? '' : '<span class="button class_add glyphicon glyphicon-plus" title="????????????" ></span>';
			
			$node_html .=
			'<li node_number="'.$val['number'].'" node_id="'.$val['node_id'].'" >
				'.$open_html.'
				<a target="_blank" style="">
					<span class="class_name">'.$val['name'].'</span>
					<span class="button class_remove glyphicon glyphicon-remove" title="????????????" ></span>
					<span class="button class_edit glyphicon glyphicon-edit" title="???????????????" ></span>
					'.$add_html.'
				</a>
				'.$chli_html.'
			</li>';
		}
		return $node_html;
	}
	
	public static function AddClassifica($father_number){
	    try{
    	    //??????????????????????????????????????????
    	    $query = ProductClassification::find();
    	    if(empty($father_number)){
    	        $query->where("parent_number='' or parent_number is null");
    	    }
    	    else{
    	        $query->where(['parent_number' => $father_number]);
    	    }
    	    $max_node = $query->orderBy("number desc")->asArray()->one();
    	    if(!empty($max_node)){
    	        $number = $max_node['parent_number'].sprintf("%02d", (substr($max_node['number'], strlen($max_node['number']) - 2)) + 1);
    	    }
    	    else{
    	        $number = $father_number.'01';
    	    }
    	    
    	    //???????????????????????????????????????????????????????????????5???
    	    for($n = 0; $n < 5; $n++){
        	    $old_node = ProductClassification::findOne(['number' => $number]);
        	    if(!empty($old_node)){
        	        $number = $old_node['parent_number'].sprintf("%02d", (substr($old_node['number'], strlen($old_node['number']) - 2)) + 10);
        	    }
        	    else{
        	        break;
        	    }
    	    }
    	    
    	    $new_node = new ProductClassification();
    	    $new_node->number = $number;
    	    $new_node->parent_number = $father_number;
    	    $new_node->name = '?????????';
    	    if(!$new_node->save()){
    	        return ['success' => false, 'msg' => '?????????????????????e1'];
    	    }
    	    return ['success' => true, 'msg' => '', 'number' => $number, 'node_id' => $new_node['ID']];
	    }
	    catch(\Exception $ex){
	        return ['success' => false, 'msg' => '????????????????????????e2'];
	    }
	}
	
	public static function EditClassifica($node_id, $name){
		$node = ProductClassification::findOne(['ID' => $node_id]);
		if(!empty($node)){
			$node->name = $name;
			if(!$node->save()){
				return ['success' => false, 'msg' => '?????????????????????e1'];
			}
			return ['success' => true, 'msg' => ''];
		}
		else{
			return ['success' => false, 'msg' => '????????????????????????'];
		}
	}
	
	public static function DeleteClassifica($node_id){
		try{
			$node = ProductClassification::findOne(['ID' => $node_id]);
			if(!empty($node)){
				//?????????????????????????????????
				$nodes = ProductClassification::find()->where("substring(number, 1, length('".$node['number']."'))='".$node['number']."'")->asArray()->all();
				$id_arr = array();
				foreach($nodes as $val){
					$id_arr[] = $val['ID'];
				}
				//?????????????????????????????????Id
				$parent_class_id = 0;
				$parent_node = ProductClassification::findOne(['number' => $node['parent_number']]);
				if(!empty($parent_node)){
					$parent_class_id = $parent_node['ID'];
				}
				if(!empty($id_arr)){
					//?????????????????????????????????????????????
					Product::updateAll(['class_id' => $parent_class_id], ['class_id' => $id_arr]);
					//??????????????????
					ProductClassification::deleteAll(['ID' => $id_arr]);
					
					//????????????????????????
					self::getProductClassCount(true);
				}
				return ['success' => true, 'msg' => ''];
			}
			else{
				return ['success' => false, 'msg' => '????????????????????????'];
			}
		}
		catch(\Exception $ex){
			return ['success' => false, 'msg' => '????????????????????????'];
		}
	}
	
	public static function ChangeClassifica($class_id, $skulist){
	    $skulist = json_decode($skulist);
	    foreach($skulist as $key => $sku){
	        $skulist[$key] = base64_decode($sku);
	    }
	    
	    //?????????????????????????????????
	    $configR = ProductConfigRelationship::find()->where(['cfsku' => $skulist])->asArray()->all();
	    foreach($configR as $sku){
	        $skulist[] = $sku['assku'];
	    }
	    
	    //?????????????????????????????????????????????
	    Product::updateAll(['class_id' => $class_id], ['sku' => $skulist]);
	    
	    //????????????????????????
	    self::getProductClassCount(true);
	    
	    //??????????????????
	    $str = self::getProductClassAllLevel($class_id);
	    $edit_log = '????????????, ??????????????????????????? "'.$str.' ", SKU: '.implode($skulist, ", ");
	    UserHelper::insertUserOperationLog('catalog', $edit_log);
	    
	    return ['success' => true, 'msg' => ''];
	}
	
	protected static $BATH_EDIT_PRODUCT_DECLARATION = array(
			"declaration_ch" => "???????????????",
			"declaration_en" => "???????????????",
			"declaration_value_currency" => "????????????",
			"declaration_value" => "????????????",
			"battery" => "???????????????",
			'declaration_code' => "????????????",
	);
	
	protected static $BATH_EDIT_PRODUCT_BASIC = array(
			"name" => "????????????",
			"prod_name_ch" => "???????????????",
			"prod_name_en" => "???????????????",
			"prod_weight" => "?????? (g)",
	        "commission_per" => "????????????",
	);
	
	/**
	 * +----------------------------------------------------------
	 * ???????????????????????????????????????
	 *+----------------------------------------------------------
	 * @param	$edit_type         string    ????????????
	 * @param	$product_id_list   array     product_id??????
	 * +----------------------------------------------------------
	 * log			name	date					note
	 * @author 		lrq		2017/10/31				?????????
	 *+----------------------------------------------------------
	 */
	public static function GetBathEditInfo($edit_type, $product_id_list){
		$pro_list = array();
		$skus = [];
		$sku_list = array();
		
		//??????????????????
		$col_name = ['product_id', 'sku', 'name', 'photo_primary', 'purchase_link', 'addi_info'];
		$edit_col_name = self::$BATH_EDIT_PRODUCT_BASIC;
		if($edit_type == 'declaration'){
			$edit_col_name = self::$BATH_EDIT_PRODUCT_DECLARATION;
		}
		foreach ($edit_col_name as $col => $val){
			if(!in_array($col, $col_name) && !in_array($col, ['commission_per'])){
				$col_name[] = $col;
			}
		}
		
		$pros = Product::find()->select($col_name)
			->where(['product_id' => $product_id_list])->asArray()->all();
		foreach($pros as $pro){
			$pro_list[$pro['sku']] = $pro;
			$skus[$pro['product_id']] = $pro['sku'];
			$sku_list[] = $pro['sku'];
		}
		
		//??????????????????????????????
		$relationship_list = array();
		$relationship = ProductConfigRelationship::find(['cfsku' => $skus])->asArray()->all();
		if(!empty($relationship)){
			$assku = [];
			foreach($relationship as $one){
				$assku[] = $one['assku'];
				$sku_list[] = $one['assku'];
				$relationship_list[$one['cfsku']][] = $one['assku'];
			}
			
			$pros = Product::find()->select($col_name)
				->where(['sku' => $assku])->asArray()->all();
			foreach($pros as $pro){
				$pro_list[$pro['sku']] = $pro;
			}
		}
		
		//????????????????????????
		$pd_sp_list = ProductSuppliersHelper::getProductPurchaseLink($sku_list);
		foreach ($pro_list as &$one){
			$one['purchase_link_list'] = '';
			if(array_key_exists($one['sku'], $pd_sp_list)){
				$one['purchase_link'] = $pd_sp_list[$one['sku']]['purchase_link'];
				$one['purchase_link_list'] = json_encode($pd_sp_list[$one['sku']]['list']);
			}
		}
		
		//??????????????????????????????
		$edit_info = array();
		foreach($product_id_list as $product_id){
			if(array_key_exists($product_id, $skus)){
				$sku = $skus[$product_id];
				
				//???????????????????????????????????????????????????
				if(array_key_exists($sku, $relationship_list)){
					foreach($relationship_list[$sku] as $assku){
						if(array_key_exists($assku, $pro_list)){
							$edit_info[] = $pro_list[$assku];
						}
					}
				}
				else if(array_key_exists($sku, $pro_list)){
					$edit_info[] = $pro_list[$sku];
				}
			}
			
			//????????????200???
			if(count($edit_info) > 200){
			    break;
			}
		}
		
		$data['edit_info'] = $edit_info;
		$data['edit_col_name'] = $edit_col_name;
		
		return $data;
	}
	
	/**
	 * +----------------------------------------------------------
	 * ???????????????????????????
	 * +----------------------------------------------------------
	 * log			name	date			note
	 * @author 		lrq		2017/10/31		?????????
	 *+----------------------------------------------------------
	 */
	public static function SaveBathEdit($data){
		$ret['success'] = true;
		$ret['msg'] = '';
		if(empty($data) || empty($data['item'])){
			return ['success' => false, 'error' => '??????????????????????????????'];
		}
		$pro_info = $data['item'];
		
		//??????????????????
		$col_list = self::$BATH_EDIT_PRODUCT_BASIC + self::$BATH_EDIT_PRODUCT_DECLARATION;
		
		$err_list = array();
		$successQty = 0;
		$failQty = 0;
		$edit_log = '';
		foreach($pro_info as $one){
			$update_sku = '';
			$err_msg = array();
			try{
				if(!empty($one['product_id'])){
					$pro = Product::findOne(['product_id' => $one['product_id']]);
					if(!empty($pro)){
						$update_sku = $pro->sku;
						$is_update = true;
						
						foreach($one as $col => $val){
						    $val = trim($val);
							if(in_array($col, ['product_id'])){
								continue;
							}
							if(!array_key_exists($col, $col_list)){
								continue;
							}
							
							//?????????
							if(empty($val)){
								switch($col){
									case 'prod_weight':
									case 'commission_per':
									case 'declaration_value':
										$val = 0;
										break;
									case 'battery':
										$val = 'N';
										break;
									case 'declaration_value_currency':
										$val = 'USD';
										break;
									default:
										break;
								}
							}
							
							//????????????
							if(empty($val) && in_array($col, ['declaration_ch', 'declaration_en', 'name', 'prod_name_ch', 'prod_name_en'])){
								$is_update = false;
								$err_msg[] = $col_list[$col]." ???????????????";
								continue;
							}
							if(in_array($col, ['prod_weight', 'commission_per']) && (!is_numeric($val) || floor($val) != $val)){
								$is_update = false;
								$err_msg[] = $col_list[$col]." ??????????????????";
								continue;
							}
							if(in_array($col, ['declaration_value']) && !is_numeric($val)){
								$is_update = false;
								$err_msg[] = $col_list[$col]." ??????????????????";
								continue;
							}
							
							switch($col){
								case 'commission_per':
									if(!empty($data['bath_edit_commission_platform'])){
										$platform = $data['bath_edit_commission_platform'];
										$addi_info = array();
										if(!empty($pro->addi_info)){
											$addi_info = json_decode($pro->addi_info, true);
											if(empty($addi_info)){
												$addi_info = array();
											}
										}
										if(empty($val)){
											unset($addi_info['commission_per'][$platform]);
										}
										else{
											$addi_info['commission_per'][$platform] = $val;
										}
										
										$pro->addi_info = json_encode($addi_info);
									}
									break;
								default:
									$pro->$col = $val;
									break;
							}
						}
						
						$old_product = Product::findOne(['product_id' => $pro->product_id]);
						if($is_update){
							if(!$pro->save()){
								$err_msg[] = print_r($pro->errors,true);
							}
							else{
    							//??????????????????
        					    $log = '';
        						if(!empty($old_product)){
        							foreach (self::$EDIT_PRODUCT_LOG_COL as $col_k => $col_n){
        								if($pro->$col_k != $old_product->$col_k){
        									if(empty($log)){
        										$log = $pro->sku;
        									}
        									$log .= ', '.$col_n.'???"'.$old_product->$col_k.'"??????"'.$pro->$col_k.'"';
        								}
        							}
        							if(!empty($log)){
        							    $edit_log .= $log."; ";
        							}
        						}
							}
						}
					}
				}
			}
			catch(\Exception $ex){
				$err_msg[] = $ex->getMessage();
			}
			
			if(!empty($err_msg)){
			    $failQty++;
				$err_list[] = [
    				'sku' => $update_sku,
    				'list' => $err_msg,
				];
			}
			else{
			    $successQty++;
			}
		}
		
		if(!empty($edit_log)){
			$edit_log = "??????????????????: ".$edit_log;
			//print_r($logs);die;
			if(strlen($edit_log) > 480){
				$edit_log = substr($edit_log, 0, 480).'......';
			}
			//print_r($edit_log);die;
			//??????????????????
			UserHelper::insertUserOperationLog('catalog', $edit_log);
		}
		
		$ret['successQty'] = $successQty;
		$ret['failQty'] = $failQty;
		$ret['msg'] = $err_list;
		return $ret;
	}
	
	public static function getProductClassAllLevel($class_id){
		$name_str = '';
		//??????????????????
		$class_id_arr = array();
		$class_number_arr = array();
		$classlist = ProductClassification::find()->asArray()->All();
		foreach ($classlist as $class){
			$class_id_arr[$class['ID']] = $class['number'];
			$class_number_arr[$class['number']] = $class;
		}
		
		if (!empty($class_id) && array_key_exists($class_id, $class_id_arr)){
			$number = $class_id_arr[$class_id];
			//????????????????????????
			for($n = 1; $n < 6; $n++){
				if(array_key_exists($number, $class_number_arr)){
					$name_str = $class_number_arr[$number]['name'].','.$name_str;
					$number = $class_number_arr[$number]['parent_number'];
					 
					if(empty($number)){
						break;
					}
				}
				else{
					break;
				}
			}
		}
		
		return rtrim($name_str, ",");
	}
	
	/**
	 * +----------------------------------------------------------
	 * ???????????????????????????
	 * +----------------------------------------------------------
	 * log			name	date			note
	 * @author 		lrq		2017/12/25		?????????
	 *+----------------------------------------------------------
	 */
	public static function getProductClassCount($is_refresh = false){
		$class_count_list = array();
		try{
			//???redis???????????????????????????
			$puid = \Yii::$app->user->identity->getParentUid();
			$redis_key_lv1 = 'ProductClassCount';
			$redis_key_lv2 = $puid;
			if(!$is_refresh){
				$warn_record = RedisHelper::RedisGet($redis_key_lv1, $redis_key_lv2);
				if(!empty($warn_record)){
					$redis_val = json_decode($warn_record,true);
				}
				//??????????????????????????????????????????????????????
				if(!empty($redis_val) && !empty($redis_val['create_time']) && time() - $redis_val['create_time'] < 3600 * 12){
					return $redis_val;
				}
			}
			//****************????????????**********
			//??????????????????
			$class_id_arr = array();
			$classlist = self::GetProductClassQuery()->asArray()->all();
			foreach ($classlist as $class){
				$class_id_arr[$class['ID']] = $class['number'];
			}
			//???????????????????????????????????????
			$pd_count_list = Yii::$app->get('subdb')->createCommand("SELECT class_id, count(1) count FROM `pd_product` WHERE type!='L' group by class_id")->queryAll();
			$class_count_list['all'] = 0;
			foreach($pd_count_list as $one){
				if(array_key_exists($one['class_id'], $class_id_arr)){
					$num = $class_id_arr[$one['class_id']];
					$class_count_list[$num] = $one['count'];
					//??????????????????
					$count = 1;
					while(strlen($num) > 2){
						$num = substr($num, 0, strlen($num) - 2);
						$class_count_list[$num] = empty($class_count_list[$num]) ? $one['count'] : $class_count_list[$num] + $one['count'];
						
						$count++;
						if($count > 3){
							$num = '';
						}
					}
				}
				else{
					$class_count_list[0] = empty($class_count_list[0]) ? $one['count'] : $class_count_list[0] + $one['count'];
				}
				
				$class_count_list['all'] += $one['count'];
			}
			
			//?????????redis
			$class_count_list['create_time'] = time();
			$ret = RedisHelper::RedisSet($redis_key_lv1, $redis_key_lv2, json_encode($class_count_list));
		}
		catch(\Exception $ex){
			
		}
		
		return $class_count_list;
	}
	
}//end of ProductHelper





