<?php 

use yii\helpers\Html;
use yii\helpers\Url;
use eagle\modules\util\helpers\TranslateHelper;
use Qiniu\json_decode;
?>

<style>
	.modal-body{
/*   		width:1270px; */
		min-height:300px;
		line-height:18px;
		font-size: 13px;
    	color: rgb(51, 51, 51);
/* 		padding: 5px 5px; */
	}
	.clear {
	    clear: both;
	    height: 0px;
	}
	.distributionDIV{
		width:200px;
		min-height:500px;
		border:1px solid #797979;
		float:left;
/* 		margin-top:-35px; */
	}
	.leftDIVtitle{
		height:35px;
		line-height:35px;
		text-align:center;
		border-bottom:2px solid #797979;
		margin-bottom:8px;
	}
	.right-title{
		font-family: 'Applied Font Bold', 'Applied Font';
		font-weight: 700;
	    font-style: normal;
	    font-size: 24px;
	    color: #333333;
		margin-bottom:25px;
	}
	.full_leftDIV>div{
		min-height:35px;
		padding-bottom:8px;
	}
	.rules>label{
		width:100%;
		padding-left: 12px;
    	margin: 5px;
    	font-size: 15px;
	}
	.full_leftDIV{
		width:1220px;float:left;
	}
	.full_rightDIV{
		margin-left:645px;width:0px;
	}
	.rightModal{
		border:1px solid #797979;
	}
	.rightModal-header{
		height:28px;
		line-height:28px;
		text-align:center;
		background:#364655;
		color:white;
		font-weight:bold;
		border-bottom:2px solid #797979;
	}
	.rightModal-body{
		height:420px;
		padding-left:20px;
		overflow-y:auto;
	}
	.rightModal-footer{
		height:40px;
		line-height:40px;
		padding:0 8px;
	}
	.textareabody{
		padding:3px;
	}
	.right_textarea{
		width:552px;
		height:408px;
		padding:5px;
	}
	.rightModal-2{
		padding:25px 8px;
	}
	.leftlist{
		margin:0px 0 0 210px;
		border:1px solid #797979;
	}
	.impor_red{
		color:#ED5466 ;
		padding:0 3px;
		margin-right:3px;
	}
	.full_top{
		margin-left:10px;
		margin-bottom: 10px;
		font-size: 15px;
	}
	.title_label{
		width:100px;
		text-align: right;
		margin
	}
	.full_top>div{
		margin-bottom: 10px;
	}
	.selected_conditions{
		padding:0 0 10px 5px;
		border-bottom:1px solid #797979;
	}
	.sources_value>div{
		padding-left:12px;
		margin-bottom: 5px;
	}
	.currency .btn {
	    margin: 0 0 2px 8px;
	}
	
	.currency_div_w{
		width: 225px;
	}
	
	.currency_div_margin_right{
		margin-right: 15px!important;
	}
	
</style>
	<div class="fullsetDIV" style='width:1250px;'>
		<div class='full_top'>
			<div>
				<label class='title_label'><span class="impor_red">*</span>???????????????</label>
				<?= Html::input('text','newname',@$rule->rule_name,['placeholder'=>'????????????????????????','style'=>'width:300px;margin-right:20px;'])?>
				<a target="_blank" href="http://www.littleboss.com/word_list_188_134.html">????????????????????????????????????</a>
			</div>
			<div>
				<label class='title_label'>???????????????</label>
				<?php $isactive = strlen($rule->is_active)?$rule->is_active:1;?>
				<label><input type="radio" name="is_actives" value="1" <?php if($isactive === 1){echo 'checked';}?>>??????</label>
				<label><input type="radio" name="is_actives" value="0" <?php if($isactive != 1){echo 'checked';}?>>??????</label>
			</div>
			
			<div>
				<label class='title_label'>?????????</label><?= Html::dropDownList('sysWarehouseId',@$proprietary_warehouse_id,$warehouseIdNameMap,[])?>
				<label>???????????????</label><?= Html::dropDownList('shippingMethodId',$transportation_service_id,$shippingMethods,['style'=>'width:300px;'])?>
				
				<button type="button" class="btn btn-success" onclick="saveAllRulesByAjax()" style="margin-left:20px;">??????</button>
				<button type="button" class="btn btn-default modal-close">??????</button>
			</div>
		</div>
	
		<div class="full_leftDIV">
			<form id="rulesForm">
			<div class="distributionDIV">
				<div class="leftDIVtitle">????????????</div>
				<?php
					if(empty($rule->receiving_provinces)){
						unset($rules['receiving_provinces']);
					}
					if(empty($rule->receiving_city)){
						unset($rules['receiving_city']);
					}
				?>
				<?=Html::checkboxList('rules',@$rule->rules,$rules,['class'=>'rules'])?>
			</div>
			<?= Html::hiddenInput('priority',@$rule->priority)?>
			<?= Html::hiddenInput('id',@$rule->id)?>
			<?= Html::hiddenInput('is_active',isset($rule->is_active)?$rule->is_active:1)?>
			<?= Html::hiddenInput('transportation_service_id',@$transportation_service_id)?>
			<?= Html::hiddenInput('name',@$rule->rule_name)?>
			<?= Html::hiddenInput('receiving_provinces',@$rule->receiving_provinces)?>
			<?= Html::hiddenInput('receiving_city',@$rule->receiving_city)?>
			<?= Html::hiddenInput('freight_amount[min]',@$rule->freight_amount[min])?>
			<?= Html::hiddenInput('freight_amount[max]',@$rule->freight_amount[max])?>
			<?= Html::hiddenInput('total_amount[min]',@$rule->total_amount[min])?>
			<?= Html::hiddenInput('total_amount[max]',@$rule->total_amount[max])?>
			<?= Html::hiddenInput('total_weight[min]',@$rule->total_weight[min])?>
			<?= Html::hiddenInput('total_weight[max]',@$rule->total_weight[max])?>
			<?= Html::hiddenInput('proprietary_warehouse_id',@$proprietary_warehouse_id)?>
			<div class="leftlist">
				<div class="leftDIVtitle" style='text-align: left;padding-left: 5px;'>????????????<span style="color: red; margin-left: 50px; ">???????????????????????????????????????????????????????????????</span></div>
				<div class='selected_conditions' id = 'receiving_country' style="display:<?=empty($rule->rules)?'none':(in_array('receiving_country', $rule->rules)?'block':'none');?>"><label>??????????????????</label><a class="btn btn-xs " onclick=selected_country_rule('receiving_country')>???????????????</a>
					<div class="receiving_value">
						<?php
							if(!empty($rule->receiving_country)){
							foreach ($rule->receiving_country as $tmp_receiving_country){
								if(isset($countrys[$tmp_receiving_country])){
						?>
						<label><input type="checkbox" name="receiving_country[]" value="<?=$tmp_receiving_country ?>" checked><?=$countrys[$tmp_receiving_country]['cn'] ?></label>
						<?php
							}}}
						?>
					</div>
				</div>
				<div class='selected_conditions' id = 'receiving_provinces' style="display:<?=empty($rule->rules)?'none':(in_array('receiving_provinces', $rule->rules)?'block':'none');?>"><label>????????????/??????</label><a class="btn btn-xs ruleshows">????????????/???</a><div class="recprovince_value"></div></div>
				<div class='selected_conditions' id = 'receiving_city' style="display:<?=empty($rule->rules)?'none':(in_array('receiving_city', $rule->rules)?'block':'none');?>"><label>??????????????????</label><a class="btn btn-xs ruleshows">???????????????</a><div class="reccity_value"></div></div>
				<div class='selected_conditions' id = 'skus' style="display:<?=empty($rule->rules)?'none':(in_array('skus', $rule->rules)?'block':'none');?>"><label>SKU???<a class="btn btn-xs " onclick=selectProdRules()>???????????????</a><p style="color: red">????????????????????????SKU????????????????????????????????????</p></label>
					<div class="sku_value">
						<?php
						if(!empty($rule->skus)){
							$tmp_skusArr = json_decode($rule->skus, true);
								
							foreach ($tmp_skusArr as $tmp_skus){
						?>
						<label><input type="checkbox" name="sku_group[]" value="<?=$tmp_skus ?>" checked><?=$tmp_skus ?></label>
						<?php
							}
						}
						?>
					</div>
					<div style="width:300px;margin-top:10px;display:none;" class="input-group" >
						<input name="txt_sku_add" type="text" class="form-control" style="height:34px;float:left;width:100%;" onkeypress='if(event.keyCode==13){txt_sku_add_click();return false;}' placeholder="??????SKU" value="">
						<span class="input-group-btn" style="">
							<button type="button" class="btn btn-default" onclick='txt_sku_add_click()'>
								<span class="glyphicon glyphicon-plus" aria-hidden="true"></span>
						    </button>
					    </span>
					</div>
				</div>
				<div class='selected_conditions' id = 'sources' style="display:<?=empty($rule->rules)?'none':(in_array('sources', $rule->rules)?'block':'none');?>"><label>???????????????????????????</label><a class="btn btn-xs ruleshows">?????????????????????????????????</a><div class="sources_value"></div></div>
				<div class='selected_conditions' id = 'freight_amount' style="display:<?=empty($rule->rules)?'none':(in_array('freight_amount', $rule->rules)?'block':'none');?>"><label>?????????????????????</label><a class="btn btn-xs ruleshows">???????????????????????????????????????</a><div class="freight_value"></div></div>
				<div class='selected_conditions' id = 'buyer_transportation_service' style="display:<?=empty($rule->rules)?'none':(in_array('buyer_transportation_service', $rule->rules)?'block':'none');?>"><label>???????????????????????????</label><a class="btn btn-xs " onclick=buyer_transportation_service_rule()>?????????????????????????????????</a>
					<div class="buyer_transportation_service_value">
					<?php
						if(!empty($rule->buyer_transportation_service)){
							foreach ($rule->buyer_transportation_service as $platform_key => $buyer_transportation_serviceArr){
								foreach ($buyer_transportation_serviceArr as $tmp_site => $buyer_transportation_serviceOne){
									if($platform_key != 'ebay'){
					?>
					<label><input type="checkbox" name="buyer_transportation_service[<?=$platform_key ?>][]" value="<?=$buyer_transportation_serviceOne ?>" checked><?='['.$platform_key.']'.$buyer_transportation_services[$platform_key][$buyer_transportation_serviceOne] ?></label>
					<?php
									}else{
										foreach ($buyer_transportation_serviceOne as $tmp_val){
					?>
					<label><input type="checkbox" name="buyer_transportation_service[<?=$platform_key ?>][<?=$tmp_site ?>][]" value="<?=$tmp_val ?>" checked><?='['.$platform_key.']['.$tmp_site.']'.$buyer_transportation_services[$platform_key][$tmp_site][$tmp_val] ?></label>
					<?php
										}
									}
								}
							}
						}
					?>
					</div>
				</div>
				<div class='selected_conditions' id = 'total_amount' style="display:<?=empty($rule->rules)?'none':(in_array('total_amount', $rule->rules)?'block':'none');?>"><label>?????????(USD???)???</label><a class="btn btn-xs ruleshows">????????????????????????</a><div class="totalamount_value"></div></div>
				
				<div class='selected_conditions' id = 'total_amount_new' style="display:<?=empty($rule->rules)?'none':(in_array('total_amount_new', $rule->rules)?'block':'none');?>"><label>?????????(?????????)???</label>
					<div class="totalamount_value_new">
						<table class="table table-bordered" id='table_currency' >
							<thead><tr><th width="120">??????</th><th width="50">??????</th><th>??????</th><th width="70">??????</th></tr></thead>
							<tbody>
							<?php
							$have_currency_type = array();
							if(!empty($rule->total_amount_new)){
								$tmp_total_amount_news = json_decode($rule->total_amount_new, true);
								
								foreach ($tmp_total_amount_news as $tmp_total_amount_new_key => $tmp_total_amount_new_val){
									echo '<tr>'.
											'<td>'.$currency_type[$tmp_total_amount_new_key].'</td>'.
											'<td>'.$tmp_total_amount_new_key.'</td>'.
											'<td class="form-inline">'.
												'<div class="input-group input-group-sm currency_div_w currency_div_margin_right">'.
													'<input value='.$tmp_total_amount_new_val['min'].' data="min" type="number" name="total_amount_new['.$tmp_total_amount_new_key.'][min]" class="form-control" onkeyup="currencyNumChange(this)" onchange="currencyNumChange(this)">'.
													'<span class="input-group-addon">-</span>'.
													'<input value='.$tmp_total_amount_new_val['max'].' data="max" type="number" name="total_amount_new['.$tmp_total_amount_new_key.'][max]" class="form-control" onkeyup="currencyNumChange(this)" onchange="currencyNumChange(this)">'.
												'</div>'.
												'<span></span>'.
											'</td>'.
											'<td><button type="button" onclick="removeCurrency(this)" class="btn btn-sm btn-default">??????</button></td>'.
										'</tr>';

									$have_currency_type[$tmp_total_amount_new_key] = $tmp_total_amount_new_key;
								}
							}							
							?>
								
								
								
							</tbody>
						</table>
					</div>
					<div style="margin-top:10px;" class="currency" >
						<span>???????????????</span>
						<?php
						foreach ($currency_type as $tag_code => $label){
							echo "<button type='button' style='".(isset($have_currency_type[$tag_code]) ? 'display:none;' : '')."' class='btn btn-xs btn-default btn_currency_".$tag_code."' onclick='addCurrency(\"".$tag_code."\",this)' >".$label."</button>";
						}
						?>
					</div>
				</div>
				
				<div class='selected_conditions' id = 'total_weight' style="display:<?=empty($rule->rules)?'none':(in_array('total_weight', $rule->rules)?'block':'none');?>"><label>????????????</label><a class="btn btn-xs ruleshows">????????????????????????</a><div class="totalweight_value"></div></div>
				<div class='selected_conditions' id = 'product_tag' style="display:<?=empty($rule->rules)?'none':(in_array('product_tag', $rule->rules)?'block':'none');?>"><label>???????????????</label><a class="btn btn-xs ruleshows">?????????????????????</a><div class="product_tag_value"></div></div>
				<div class='selected_conditions' id = 'postal_code' style="display:<?=empty($rule->rules)?'none':(in_array('postal_code', $rule->rules)?'block':'none');?>"><label>?????????</label><a class="btn btn-xs ruleshows">?????????????????????</a>
				    <div class="postal_code_value">
				         <?php
				            if(!empty($rule->postal_code)){
                                foreach($rule->postal_code as $postal_code){
                                    $str = explode(',',$postal_code);
                                    $type = $str[0];
                                    if(!empty($type)){
                                        if($type == 'type_start'){
                                            if(count($str) == 2){
                                                $val = $str[1];
                                                $text = '??????: '.$val;
                                        }}
                                        else if($type == 'type_contains'){
                                            if(count($str) == 2){
                                                $val = $str[1];
                                                $text = '??????: '.$val;
                                        }}
                                        else if($type == 'type_start_contains'){
                                            if(count($str) == 3){
                                                $val = $str[1].",".$str[2];
                                                $text = '?????????????????????: '.$str[1].' , '.$str[2];
                                        }}
                                        else if($type == 'type_range'){
                                            if(count($str) == 4){
                                                $val = $str[1].",".$str[2].",".$str[3];
                                                $text = '??????: ??? '.$str[1].'???, '.$str[2]." - ".$str[3];
                                        }}
                                        
                                        if(!empty($val) && !empty($text)){
                                            echo "<label style='padding-right:10px;'><input type='checkbox' name='postal_code[]' value='".$type.','.$val."' checked> ".$text."</label>";
                                        }
                            }}}
				        ?>
				    </div>
			    </div>
				<div class='selected_conditions' id = 'items_location_country' style="display:<?= empty($rule->rules)?'none':(in_array('items_location_country', $rule->rules)?'block':'none');?>"><label>?????????????????????(ebay)???</label><a class="btn btn-xs " onclick=selected_country_rule('items_location_country')>???????????????</a>
					<div class="mycountry_value">
						<?php 
							if(!empty($rule->items_location_country)){
							foreach ($rule->items_location_country as $tmp_items_location_country){
						?>
						<label><input type="checkbox" name="items_location_country[]" value="<?=$tmp_items_location_country ?>" checked><?=$countrys[$tmp_items_location_country]['cn'] ?></label>
						<?php
							}}
						?>
					</div>
				</div>
				<div class='selected_conditions' id = 'items_location_provinces' style="display:<?=empty($rule->rules)?'none':(in_array('items_location_provinces', $rule->rules)?'block':'none');?>">
					<label>??????????????????(ebay)???</label>
					<div class="myprovince_value">
						<?php
						if(!empty($rule->items_location_provinces)){
							$tmp_myprovinceArr = json_decode($rule->items_location_provinces, true);
								
							foreach ($tmp_myprovinceArr as $tmp_myprovince){
						?>
						<label><input type="checkbox" name="myprovince_group[]" value="<?=$tmp_myprovince ?>" checked><?=$tmp_myprovince ?></label>
						<?php
							}
						}
						?>
					</div>
					<div style="width:300px;margin-top:10px;" class="input-group">
						<input name="txt_myprovince_add" type="text" class="form-control" style="height:34px;float:left;width:100%;" onkeypress='if(event.keyCode==13){txt_myprovince_add_click();return false;}' placeholder="????????????????????????(ebay)" value="">
						<span class="input-group-btn" style="">
							<button type="button" class="btn btn-default" onclick='txt_myprovince_add_click()'>
								<span class="glyphicon glyphicon-plus" aria-hidden="true"></span>
						    </button>
					    </span>
					</div>
				</div>
			</div>
			</form>
		</div>
		
		<div class="full_rightDIV">
			<div class="rightModal receiving_provinces" style="display: none">
				<div class="rightModal-header">?????????/???</div>
				<div class="rightModal-body textareabody">
					<?= Html::textarea('Recprovince',$rule->receiving_provinces,['placeholder'=>'???/????????????????????????','class'=>'right_textarea iv-input'])?>
				</div>
				<div class="rightModal-footer">
					<button type="button" class="btn btn-default pull-right rightModal-close" style="margin-left:8px;">??????</button>
					<button type="button" class="btn btn-success pull-right" onclick='setRecProvince()'>??????</button>
				</div>
			</div>
			<div class="rightModal receiving_city" style="display: none">
				<div class="rightModal-header">????????????</div>
				<div class="rightModal-body textareabody">
					<?= Html::textarea('Reccity',$rule->receiving_city,['placeholder'=>'???????????????????????????','class'=>'right_textarea iv-input'])?>
				</div>
				<div class="rightModal-footer">
					<button type="button" class="btn btn-default pull-right rightModal-close" style="margin-left:8px;">??????</button>
					<button type="button" class="btn btn-success pull-right" onclick='setRecCity()'>??????</button>
				</div>
			</div>
			<div class="rightModal sources" style="display: none">
				<div class="rightModal-header">??????????????????????????????</div>
				<div class="rightModal-body">
					<div class='source' id ='source' style="padding-top:8px;">
						<label>?????????</label>
						<?=Html::checkboxList('sourceCheck',$rule->source,$source)?>
					</div>
					<div class='site' id="site"  style="padding-top:8px;">
						<label>?????????</label>
						<input class="btn btn-success btn-xs  site" type="button" value="<?=TranslateHelper::t('????????????');?>" onclick="$('input[name^=sites]:visible').prop('checked',true);">
						<input class="btn btn-danger btn-xs  site" type="button" value="<?=TranslateHelper::t('????????????');?>" onclick="$('input[name^=sites]:visible').removeAttr('checked');">
						<BUTTON type="button" class="btn btn-xs btn-warning openOrCloseNextDIV">??????????????????/??????</BUTTON>
						<div class="siteDIV" style="display:none;">
							<?php foreach ($sites as $platform=>$site){?>
							<div class="<?=$platform?> <?= empty($rule->source)?'sr-only':(in_array($platform, $rule->source)?'':'sr-only');?>">
							<hr/>
							<input class="btn btn-success btn-xs site" platform=<?=$platform?> type="button" value="<?=TranslateHelper::t($platform.'??????');?>" onclick="$(this).parent().find('input[type=checkbox]:visible').prop('checked','checked');siteChange();">
							<input class="btn btn-danger btn-xs site" type="button" value="<?=TranslateHelper::t('??????');?>" onclick="$(this).parent().find('input[type=checkbox]:visible').removeAttr('checked');siteChange();">
							<?=Html::checkboxList('sites['.$platform.']',@$rule->site[$platform],$site,['platform'=>$platform])?>
							</div>
							<?php }?>
						</div>
					</div>
					<div class='selleruserid' id="selleruserid" style="padding-top:8px;">
						<label>?????????</label>
						<input class="btn btn-success btn-xs" type="button" value="<?=TranslateHelper::t('????????????');?>" onclick="$('input[name^=selleruserids]:visible').prop('checked',true);">
						<input class="btn btn-danger btn-xs" type="button" value="<?=TranslateHelper::t('????????????');?>" onclick="$('input[name^=selleruserids]:visible').removeAttr('checked');">
						<BUTTON type="button" class="btn btn-xs btn-warning openOrCloseNextDIV">??????????????????/??????</BUTTON>
						<div class="selleruseridDIV" style="display:none;">
							<?php foreach ($selleruserids as $platform=>$selleruserid){?>
							<div class="<?=$platform?> <?= empty($rule->source)?'sr-only':(in_array($platform, $rule->source)?'':'sr-only');?>">
							<hr/>
							<input class="btn btn-success btn-xs site" type="button" value="<?=TranslateHelper::t($platform.'??????');?>" onclick="$(this).parent().find('input[type=checkbox]:visible').prop('checked','checked');">
							<input class="btn btn-danger btn-xs site" type="button" value="<?=TranslateHelper::t('??????');?>" onclick="$(this).parent().find('input[type=checkbox]:visible').removeAttr('checked');">
							<?=Html::checkboxList('selleruserids['.$platform.']',@$rule->selleruserid[$platform],$selleruserid,['platform'=>$platform])?>
							</div>
							<?php }?>
						</div>
					</div>
					
				</div>
				<div class="rightModal-footer">
					<button type="button" class="btn btn-default pull-right rightModal-close" style="margin-left:8px;">??????</button>
					<button type="button" class="btn btn-success pull-right" onclick="setSources();">??????</button>
				</div>
			</div>
			<div class="rightModal freight_amount" style="display: none">
				<div class="rightModal-header">????????????????????????</div>
				<div class="rightModal-body">
					<div class="rightModal-2">
						<label style="width:130px;text-align:right;">????????????????????????</label>
						<?= Html::input('text','minfreight',$rule->freight_amount['min'],['placeholder'=>'???????????????????????????','class'=>'iv-input'])?>
						<label>USD</label>
					</div>
					<div class="rightModal-2">
						<label style="width:130px;text-align:right;">??????????????????</label>
						<?= Html::input('text','maxfreight',$rule->freight_amount['max'],['placeholder'=>'???????????????????????????','class'=>'iv-input'])?>
						<label>USD</label>
					</div>
				</div>
				<div class="rightModal-footer">
					<button type="button" class="btn btn-default pull-right rightModal-close" style="margin-left:8px;">??????</button>
					<button type="button" class="btn btn-success pull-right" onclick='set_freight()'>??????</button>
				</div>
			</div>
			<div class="rightModal total_amount" style="display: none">
				<div class="rightModal-header">???????????????</div>
				<p style="color: red">???????????????????????????????????????USD,??????????????????USD?????????????????????</p>
				<div class="rightModal-body">
					<div class="rightModal-2">
						<label style="width:130px;text-align:right;">????????????????????????</label>
						<?= Html::input('text','minamount',$rule->total_amount['min'],['placeholder'=>'????????????????????????','class'=>'iv-input'])?>
						<label>USD</label>
					</div>
					<div class="rightModal-2">
						<label style="width:130px;text-align:right;">??????????????????</label>
						<?= Html::input('text','maxamount',$rule->total_amount['max'],['placeholder'=>'????????????????????????','class'=>'iv-input'])?>
						<label>USD</label>
					</div>
				</div>
				<div class="rightModal-footer">
					<button type="button" class="btn btn-default pull-right rightModal-close" style="margin-left:8px;">??????</button>
					<button type="button" class="btn btn-success pull-right" onclick="set_totalamount()">??????</button>
				</div>
			</div>
			<div class="rightModal total_weight" style="display: none">
				<div class="rightModal-header">???????????????</div>
				<div class="rightModal-body">
					<div class="rightModal-2">
						<label style="width:130px;text-align:right;">????????????????????????</label>
						<?= Html::input('text','minweight',$rule->total_weight['min'],['placeholder'=>'??????????????????','class'=>'iv-input'])?>
						<label>g</label>
					</div>
					<div class="rightModal-2">
						<label style="width:130px;text-align:right;">??????????????????</label>
						<?= Html::input('text','maxweight',$rule->total_weight['max'],['placeholder'=>'??????????????????','class'=>'iv-input'])?>
						<label>g</label>
					</div>
				</div>
				<div class="rightModal-footer">
					<button type="button" class="btn btn-default pull-right rightModal-close" style="margin-left:8px;">??????</button>
					<button type="button" class="btn btn-success pull-right" onclick="set_totalweight()">??????</button>
				</div>
			</div>
			<div class="rightModal product_tag" style="display: none">
				<div class="rightModal-header">??????????????????</div>
				<p style="color: red">????????????????????????????????????????????????????????????????????????</p>
				<div class="rightModal-body">
					<div class=' product_tag' id="product_tag" style="padding-top:8px;">
						<input class="btn btn-success btn-xs" type="button" value="<?=TranslateHelper::t('????????????');?>" onclick="$('input[name^=product_tags]').prop('checked',true);">
						<input class="btn btn-danger btn-xs" type="button" value="<?=TranslateHelper::t('????????????');?>" onclick="$('input[name^=product_tags]').removeAttr('checked');">
						<hr/>
						<?=Html::checkboxList('product_tags',$rule->product_tag,$product_tags)?>
					</div>
				</div>
				<div class="rightModal-footer">
					<button type="button" class="btn btn-default pull-right rightModal-close" style="margin-left:8px;">??????</button>
					<button type="button" class="btn btn-success pull-right" onclick="setTag();">??????</button>
				</div>
			</div>
			<div class="rightModal postal_code" style="display: none">
				<div class="rightModal-header">??????????????????</div>
				<p style="color: red">???????????????????????????????????????????????????????????????????????????</p>
				<div class="rightModal-body">
				    <div style="padding: 25px 80px;">
				        <SELECT name="postal_code_type" class="eagle-form-control" style="width:200px;margin:0px">
				            <OPTION>?????????????????????</OPTION>
				            <OPTION value="type_start">????????????</OPTION>
				            <OPTION value="type_contains">????????????</OPTION>
				            <OPTION value="type_start_contains">?????????????????????</OPTION>
				            <OPTION value="type_range">??????</OPTION>
				        </SELECT>
				    </div>
					<div class="rightModal-2 type_start type_start_contains" style="display: none">
						<label style="width:130px;text-align:right;">???????????????</label>
						<?= Html::input('text','start_str','',['placeholder'=>'?????????????????????','class'=>'iv-input'])?>
					</div>
					<div class="rightModal-2 type_contains type_start_contains" style="display: none">
						<label style="width:130px;text-align:right;">???????????????</label>
						<?= Html::input('text','contains_str','',['placeholder'=>'?????????????????????','class'=>'iv-input'])?>
					</div>
					<div class="rightModal-2 type_range" style="display: none">
						<label style="width:130px;text-align:right;">???????????????</label>
						<?= Html::input('number','range_count','',['placeholder'=>'?????????????????????','class'=>'form-control','style'=>'width:150px; display:inline-block;'])?>
					</div>
					<div class="rightModal-2 type_range" style="display: none">
						<label style="width:130px;text-align:right;">???????????????</label>
						<?= Html::input('number','range_min','',['placeholder'=>'??????????????????','class'=>'form-control','style'=>'width:150px; display:inline-block;'])?>
						<span style="background-color:#eee; border:1px solid #ccc; padding:6px 12px; ">-</span>
						<?= Html::input('number','range_max','',['placeholder'=>'??????????????????','class'=>'form-control','style'=>'width:150px; display:inline-block;'])?>
					</div>
					<p id="postal_code_err" style="color:red;width:130px;text-align:right;display:none; margin:0px 0px 20px 50px;">?????????????????????</p>
					<p class="type_range" style="color: blue; display: none">???????????????????????????????????????????????????200???300???????????????????????????3?????????????????????????????? 200 - 300 ???(?????????0??????????????????)???</p>
				</div>
				<div class="rightModal-footer">
					<button type="button" class="btn btn-default pull-right rightModal-close" style="margin-left:8px;">??????</button>
					<button type="button" class="btn btn-success pull-right" onclick="setPostalcode()">??????</button>
				</div>
			</div>
		</div>
	</div>
	<div class="clear"></div>

<script>

$(function() {
	setMyCity();
	setRecProvince();
	setRecCity();
	setSources();
	set_freight();
	set_totalamount();
	set_totalweight();
	setTag();
	//?????????????????????????????????????????????
	show_site_empty_msg();
	set_totalamount_new();


	//????????????
	$('input[name^=sourceCheck]').change(function(){
		$('input[name^="sourceCheck"]').each(function(){
			var n = $(this).val();
			
			if($(this).prop('checked')){
				$("."+n).removeClass('sr-only');

				if(n == 'aliexpress'){
					$('.'+n+'aliexpress').removeClass('sr-only');
				}
				else if(n == 'cdiscount'){
					$('.'+n+'FR').removeClass('sr-only');
				}
				else if(n == 'priceminister'){
					$('.'+n+'FR').removeClass('sr-only');
				}
			}else{
				$("."+n).addClass('sr-only');

				if(n == 'aliexpress'){
					$('.'+n+'aliexpress').addClass('sr-only');
				}
				else if(n == 'cdiscount'){
					$('.'+n+'FR').addClass('sr-only');
				}
				else if(n == 'priceminister'){
					$('.'+n+'FR').addClass('sr-only');
				}
			}
		})

		//?????????????????????????????????????????????
		show_site_empty_msg();
	})
	//????????????
	$('input[name^=site]').click(function(){
		$('input[name^=site]').each(function(){
			var n = $(this).val();
			var platform = $(this).parent().parent().attr('platform');
			if($(this).prop('checked')){
				$("."+platform+n).removeClass('sr-only');
			}else{
				$("."+platform+n).addClass('sr-only');
			}
		})

		//?????????????????????????????????????????????
		show_site_empty_msg();
	})
	$('input[name=is_actives]').change(function(){
		$('input[name=is_active]').val($(this).val());
	});
	$('input[name=newname]').change(function(){
		$('input[name=name]').val($(this).val());
	});
	$('.openOrCloseTextValue').click(function(){
		obj = $(this).next();
		var hidden = obj.css('display');
		if(typeof(hidden)=='undefined' || hidden=='none'){
			$(this).text('??????');
			obj.css('display','block');
		}else{
			$(this).text('??????');
			obj.css('display','none');
		}
	});
	$('.openOrCloseNextDIV').click(function(){
		obj = $(this).next();
		var hidden = obj.css('display');
		if(typeof(hidden)=='undefined' || hidden=='none'){
			obj.css('display','block');
		}else{
			obj.css('display','none');
		}
	});
	$('.ruleshows').click(function(){
		sobj=$(this).parent();
		var id = sobj.attr('id');
		obj = $('.'+id);
		var hidden = obj.css('display');
		if(typeof(hidden)=='undefined' || hidden=='none'){
			closeAllRightModals();
			obj.css('display','block');
			$('.full_rightDIV').css('width','560');
			$('.full_leftDIV').css('width','620');
		}else if( hidden=='block'){
			obj.css('display','none');
			$('.full_rightDIV').css('width','0');
			$('.full_leftDIV').css('width','1220');
		}
	});
	$('.rightModal-close').click(function(){
		obj=$(this).parent().parent();
		obj.hide();
		$('.full_rightDIV').css('width','0');
		$('.full_leftDIV').css('width','1220');
	});
	
	$('input[name^=rules]').click(function(){
		var n = $(this).val();

// 		alert(n);

		if((n == 'total_amount') || (n == 'total_amount_new')){
			var is_back_currency = false;
			
			$(this).parent().parent().children().each(function(){
	
				if((n == 'total_amount_new')){
					
					
					if($(this).children().val() == 'total_amount'){
						if($(this).children().prop('checked')){
							is_back_currency = true;
						}
					}
				}else{
					if($(this).children().val() == 'total_amount_new'){
						if($(this).children().prop('checked')){
							is_back_currency = true;
						}
					}
				}
			});

			if(is_back_currency == true){
				$.alertBox('<p class="text-warn">?????????(USD)????????????(?????????)??????????????????,??????????????????</p>');
				
				$(this).prop('checked',false);
				return false;
			}
		}

		
		if($(this).prop('checked')){
			$('#'+n).show();
			closeAllRightModals();
			
			if((n == 'skus') || (n == 'items_location_provinces') || (n == 'buyer_transportation_service') || (n == 'total_amount_new')){
			}else{
				$('.full_rightDIV').css('width','560');
				$('.full_leftDIV').css('width','620');

				if((n == 'receiving_country') || (n == 'items_location_country')){
					selected_country_rule(n);
				}else{
					$('.'+n).show();
				}
			}
		}else{
			closeAllRightModals();
			$('#'+n).hide();
			$('.full_rightDIV').css('width','0');
			$('.full_leftDIV').css('width','1220');
		}
	});
	
	$(".display_all_transportation_toggle").click(function(){
		$('.transportation').each(function(){
			btn=$(this).prev();
			obj=$(this);
			p=$(this).next();
			var hidden = obj.css('display');
			if(typeof(hidden)=='undefined' || hidden=='none'){
				btn.html("??????");
				obj.css('display','block');
				p.css('display','none');
			}else if( hidden=='block'){
				btn.html("??????");
				obj.css('display','none');
				p.css('display','block');
			}
		})
	});
	$(".display_all_transportation_open").click(function(){
		$('.transportation').each(function(){
			btn=$(this).prev();
			obj=$(this);
			p=$(this).next();
			
			btn.html("??????");
			obj.css('display','block');
			p.css('display','none');
		})
	});
	$(".display_all_transportation_close").click(function(){
		$('.transportation').each(function(){
			btn=$(this).prev();
			obj=$(this);
			p=$(this).next();

			btn.html("??????");
			obj.css('display','none');
			p.css('display','block');
		})
	});
	$('.display_toggle').click(function(){
		obj=$(this).next();
		var hidden = obj.css('display');
		if(typeof(hidden)=='undefined' || hidden=='none'){
			obj.css('display','block');
		}else if( hidden=='block'){
			obj.css('display','none');
		}
	});
	$('.display_toggle_service').click(function(){
		obj=$(this).next();
		p=$(this).next().next();
		var hidden = obj.css('display');
		if(typeof(hidden)=='undefined' || hidden=='none'){
			$(this).html("??????");
			obj.css('display','block');
			p.css('display','none');
		}else if( hidden=='block'){
			$(this).html("??????");
			obj.css('display','none');
			p.css('display','block');
		}
	});
	$(".display_all_country_toggle").click(function(){
		$(this).parent().children('div').find('.region_country').each(function(){
			obj=$(this);
			var hidden = obj.css('display');
			if(typeof(hidden)=='undefined' || hidden=='none'){
				obj.css('display','block');
			}else if( hidden=='block'){
				obj.css('display','none');
			}
		})
	});
	$(".display_all_country_open").click(function(){
		$(this).parent().children('div').find('.region_country').each(function(){
			$(this).css('display','block');
		});
	});
	$(".display_all_country_close").click(function(){
		$(this).parent().children('div').find('.region_country').each(function(){
			$(this).css('display','none');
		});
	});
	
	$('select[name=sysWarehouseId]').change(function(){
		$('input[name=proprietary_warehouse_id]').val($(this).val());

		shippingID = $('select[name=shippingMethodId]').val();

		$warehouse_id = $(this).val();
		$change_shipping_method_code = $('select[name=shippingMethodId]');
		$.ajax({
			url: global.baseUrl + "order/order/get-shipping-method-by-warehouseid",
			data: {
				warehouse_id:$warehouse_id,
			},
			type: 'post',
			dataType: 'json',
			success: function(response) {
				$change_shipping_method_code.html('');
				var options = '';
				$.each(response , function(index , value){
					if(shippingID == index)
						options += '<option value="'+index+'" selected>'+value+'</option>';
					else
						options += '<option value="'+index+'">'+value+'</option>';
				});
				$change_shipping_method_code.html(options);
			}
		});
	});
	
	$('select[name=shippingMethodId]').change(function(){
		$('input[name=transportation_service_id]').val($(this).val());
	});

	$('select[name=postal_code_type]').change(function(){
		$('#postal_code_err').css('display','none');
		var val = $(this).val();
		if(val == 'type_start'){
			$('.postal_code').find('div.type_start').css('display','block');
			$('.postal_code').find('div.rightModal-2:not(.type_start)').css('display','none');
			$('.postal_code').find('.type_range').css('display','none');
		}
		else if(val == 'type_contains'){
			$('.postal_code').find('div.type_contains').css('display','block');
			$('.postal_code').find('div.rightModal-2:not(.type_contains)').css('display','none');
			$('.postal_code').find('.type_range').css('display','none');
		}
		else if(val == 'type_start_contains'){
			$('.postal_code').find('div.type_start_contains').css('display','block');
			$('.postal_code').find('div.rightModal-2:not(.type_start_contains)').css('display','none');
			$('.postal_code').find('.type_range').css('display','none');
		}
		else if(val == 'type_range'){
			$('.postal_code').find('div.type_range').css('display','block');
			$('.postal_code').find('div.rightModal-2:not(.type_range)').css('display','none');
			$('.postal_code').find('.type_range').css('display','block');
		}
		else{
			$('.postal_code').find('div.rightModal-2').css('display','none');
			$('.postal_code').find('.type_range').css('display','none');
		}
		$('.postal_code').find('input').val("");
	});

	//?????????????????????????????????????????????
	function show_site_empty_msg(){
		var count = 0;
		$('input[name^="sourceCheck"]').each(function(){
			var n = $(this).val();
			
			if(n == 'aliexpress'){
				if( !$('.'+n+'aliexpress').hasClass('sr-only')){
					count++;
				}
			}
			else if(n == 'cdiscount'){
				if( !$('.'+n+'FR').hasClass('sr-only')){
					count++;
				}
			}
			else if(n == 'priceminister'){
				if( !$('.'+n+'FR').hasClass('sr-only')){
					count++;
				}
			}
			else if( !$('.'+n).hasClass('sr-only')){
				$("input[name='sites["+ n +"][]']").each(function(){
					var n = $(this).val();
					var platform = $(this).parent().parent().attr('platform');
					$("."+platform+n).each(function(){
						if( !$(this).hasClass('sr-only')){
							count++;
						}
					})
				})
			}
		})
		
        if(count == 0)
        {
        	$('p[name="site_empty_msg"]').css("display", "block");
            
        }
        else
        {
        	$('p[name="site_empty_msg"]').css("display", "none");
        }
	}
});

</script>