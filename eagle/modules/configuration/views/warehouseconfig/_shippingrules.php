<?php 

use yii\helpers\Html;
use yii\helpers\Url;
use eagle\modules\util\helpers\TranslateHelper;
?>

<style>
.iv-modal{
		max-width: none;
	}
	.iv-modal .modal-content{
		max-height: none;
	}
	.modal-header .close {
	    color: white;
		height:44px;
		line-height:44px;
	}
	.drop{
		width:250px;
		height:25px;
		line-height:25px;
	}
	.modal-body{
		min-height:300px;
		line-height:18px;
		font-size: 13px;
    	color: rgb(51, 51, 51);
		padding: 5px 5px;
	}
	.clear {
	    clear: both;
	    height: 0px;
	}
	.modal-dialog{
		width:1302px;
		font-family: 'Applied Font Regular', 'Applied Font';
	}
	.distributionDIV{
		width:200px;
		min-height:535px;
		border:1px solid #797979;
		float:left;
		margin-top:-35px;
	}
	.fullsetDIV{
/* 		margin:5px 0 0 220px; */
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
		padding-left:15px;
	}
	.full_leftDIV{
		width:1220px;float:left;
	}
	.full_rightDIV{
		margin-left:680px;width:0px;
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
		margin:8px 0 0 210px;
	}
	.impor_red{
		color:#ED5466 ;
		background:#F5F7F7;
		padding:0 3px;
		margin-right:3px;
	}
	
	.full_top{
		margin-left:220px;
		margin-bottom: 10px;
	}
</style>

	<div class="fullsetDIV">
		<div class='full_top'>
			<label><span class="impor_red">*</span>????????????</label>
			<?= Html::input('text','newname',@$rule->rule_name,['placeholder'=>'?????????????????????','style'=>'width:150px;margin-right:20px;'])?>
			<label>?????????</label>
			<?php $isactive = strlen($rule->is_active)?$rule->is_active:1;?>
			<label><input type="radio" name="is_actives" value="1" <?php if($isactive === 1){echo 'checked';}?>> ??????</label>
			<label><input type="radio" name="is_actives" value="0" <?php if($isactive != 1){echo 'checked';}?>> ??????</label>
			<button type="button" class="btn btn-success btn-xs" onclick="saveAllRulesByAjaxShipping()" style="margin-left:20px;">??????</button>
			<button type="button" class="btn btn-default btn-xs modal-close">??????</button>
		</div>
	
		<div class="full_leftDIV">
			<form id="rulesForm">
			<div class="distributionDIV">
				<div class="leftDIVtitle">?????????</div>
				<?=Html::checkboxList('rules',@$rule->rules,$rules,['class'=>'rules'])?>
			</div>
			<?= Html::hiddenInput('priority',@$rule->priority)?>
			<?= Html::hiddenInput('id',@$rule->id)?>
			<?= Html::hiddenInput('is_active',isset($rule->is_active)?$rule->is_active:1)?>
			<?= Html::hiddenInput('transportation_service_id',@$transportation_service_id)?>
			<?= Html::hiddenInput('name',@$rule->rule_name)?>
			<?= Html::hiddenInput('items_location_provinces',@$rule->items_location_provinces)?>
			<?= Html::hiddenInput('items_location_city',@$rule->items_location_city)?>
			<?= Html::hiddenInput('receiving_provinces',@$rule->receiving_provinces)?>
			<?= Html::hiddenInput('receiving_city',@$rule->receiving_city)?>
			<?= Html::hiddenInput('skus',@$rule->skus)?>
			<?= Html::hiddenInput('freight_amount[min]',@$rule->freight_amount[min])?>
			<?= Html::hiddenInput('freight_amount[max]',@$rule->freight_amount[max])?>
			<?= Html::hiddenInput('total_amount[min]',@$rule->total_amount[min])?>
			<?= Html::hiddenInput('total_amount[max]',@$rule->total_amount[max])?>
			<?= Html::hiddenInput('total_weight[min]',@$rule->total_weight[min])?>
			<?= Html::hiddenInput('total_weight[max]',@$rule->total_weight[max])?>
			<?= Html::hiddenInput('proprietary_warehouse_id',$proprietary_warehouse_id)?>
			<div class="leftlist">
				<?php $emptyRules = empty($rule->rules)?'none':''?>
				<div id = 'items_location_country' style="display:<?= empty($rule->rules)?'none':(in_array('items_location_country', $rule->rules)?'block':'none');?>"><label>????????????????????????</label><a class="btn btn-xs ruleshows">???????????????</a><a class="btn btn-xs btn-warning openOrCloseTextValue">??????</a><div class="mycountry_value"></div></div>
				<div id = 'items_location_provinces' style="display:<?=empty($rule->rules)?'none':(in_array('items_location_provinces', $rule->rules)?'block':'none');?>"><label>??????????????????/??????</label><a class="btn btn-xs ruleshows">????????????/???</a><a class="btn btn-xs btn-warning openOrCloseTextValue">??????</a><div class="myprovince_value"></div></div>
				<div id = 'items_location_city' style="display:<?=empty($rule->rules)?'none':(in_array('items_location_city', $rule->rules)?'block':'none');?>"><label>????????????????????????</label><a class="btn btn-xs ruleshows">???????????????</a><a class="btn btn-xs btn-warning openOrCloseTextValue">??????</a><div class="mycity_value"></div></div>
				<div id = 'receiving_country' style="display:<?=empty($rule->rules)?'none':(in_array('receiving_country', $rule->rules)?'block':'none');?>"><label>??????????????????</label><a class="btn btn-xs ruleshows">???????????????</a><a class="btn btn-xs btn-warning openOrCloseTextValue">??????</a><div class="receiving_value"></div></div>
				<div id = 'receiving_provinces' style="display:<?=empty($rule->rules)?'none':(in_array('receiving_provinces', $rule->rules)?'block':'none');?>"><label>????????????/??????</label><a class="btn btn-xs ruleshows">????????????/???</a><a class="btn btn-xs btn-warning openOrCloseTextValue">??????</a><div class="recprovince_value"></div></div>
				<div id = 'receiving_city' style="display:<?=empty($rule->rules)?'none':(in_array('receiving_city', $rule->rules)?'block':'none');?>"><label>??????????????????</label><a class="btn btn-xs ruleshows">???????????????</a><a class="btn btn-xs btn-warning openOrCloseTextValue">??????</a><div class="reccity_value"></div></div>
				<div id = 'skus' style="display:<?=empty($rule->rules)?'none':(in_array('skus', $rule->rules)?'block':'none');?>"><label>SKU???</label><a class="btn btn-xs ruleshows">??????????????????SKU</a><a class="btn btn-xs btn-warning openOrCloseTextValue">??????</a><div class="sku_value"></div></div>
				<div id = 'sources' style="display:<?=empty($rule->rules)?'none':(in_array('sources', $rule->rules)?'block':'none');?>"><label>???????????????????????????</label><a class="btn btn-xs ruleshows">?????????????????????????????????</a><a class="btn btn-xs btn-warning openOrCloseTextValue">??????</a><div class="sources_value"></div></div>
				<div id = 'freight_amount' style="display:<?=empty($rule->rules)?'none':(in_array('freight_amount', $rule->rules)?'block':'none');?>"><label>?????????????????????</label><a class="btn btn-xs ruleshows">???????????????????????????????????????</a><a class="btn btn-xs btn-warning openOrCloseTextValue">??????</a><div class="freight_value"></div></div>
				<div id = 'buyer_transportation_service' style="display:<?=empty($rule->rules)?'none':(in_array('buyer_transportation_service', $rule->rules)?'block':'none');?>"><label>???????????????????????????</label><a class="btn btn-xs ruleshows">?????????????????????????????????</a><a class="btn btn-xs btn-warning openOrCloseTextValue">??????</a><div class="buyer_transportation_service_value"></div></div>
				<div id = 'total_amount' style="display:<?=empty($rule->rules)?'none':(in_array('total_amount', $rule->rules)?'block':'none');?>"><label>????????????</label><a class="btn btn-xs ruleshows">????????????????????????</a><a class="btn btn-xs btn-warning openOrCloseTextValue">??????</a><div class="totalamount_value"></div></div>
				<div id = 'total_weight' style="display:<?=empty($rule->rules)?'none':(in_array('total_weight', $rule->rules)?'block':'none');?>"><label>????????????</label><a class="btn btn-xs ruleshows">????????????????????????</a><a class="btn btn-xs btn-warning openOrCloseTextValue">??????</a><div class="totalweight_value"></div></div>
				<div id = 'product_tag' style="display:<?=empty($rule->rules)?'none':(in_array('product_tag', $rule->rules)?'block':'none');?>"><label>???????????????</label><a class="btn btn-xs ruleshows">?????????????????????</a><a class="btn btn-xs btn-warning openOrCloseTextValue">??????</a><div class="product_tag_value"></div></div>
			</div>
			</form>
		</div>
		<div class="full_rightDIV">
			<div class="rightModal items_location_country" style="display: none">
				<div class="rightModal-header">????????????</div>
				<div class="rightModal-body">
					<div class=' my_country' id="my_country" style="padding-top:8px;">
						<input class="btn btn-success btn-xs" type="button" value="<?=TranslateHelper::t('????????????');?>" onclick="$('input[name^=my_country]:visible').prop('checked',true);">
						<input class="btn btn-danger btn-xs" type="button" value="<?=TranslateHelper::t('????????????');?>" onclick="$('input[name^=my_country]:visible').removeAttr('checked');">
						<button type="button" class="btn btn-warning btn-xs display_all_country_toggle">????????????/??????</button>
						<button type="button" class="btn btn-warning btn-xs display_all_country_open">????????????</button>
						<button type="button" class="btn btn-warning btn-xs display_all_country_close">????????????</button>
						<?php foreach ($countrys as $one){?>
						<div>
						<hr/>
						<input class="btn btn-success btn-xs" type="button" value="<?=TranslateHelper::t($region[$one['name']].'('.$one['name'].')'.'??????');?>" onclick="$(this).parent().find('input[type=checkbox]:visible').prop('checked','checked');">
						<input class="btn btn-danger btn-xs" type="button" value="<?=TranslateHelper::t('??????');?>" onclick="$(this).parent().find('input[type=checkbox]:visible').removeAttr('checked');">
						<button type="button" class="btn btn-warning btn-xs display_toggle">??????/??????</button>
						<div style="display:none" class="region_country">
						<?=Html::checkboxList('my_country', $rule->items_location_country,$one['value'])?>
						</div>
						</div>
						<?php }?>
					</div>
				</div>
				<div class="rightModal-footer">
					<button type="button" class="btn btn-default pull-right rightModal-close" style="margin-left:8px;">??????</button>
					<button type="button" class="btn btn-success pull-right" onclick="setMyCountry();">??????</button>
				</div>
			</div>
			<div class="rightModal items_location_provinces" style="display: none">
				<div class="rightModal-header">?????????/???</div>
				<div class="rightModal-body textareabody">
					<?= Html::textarea('Myprovince',@$rule->items_location_provinces,['placeholder'=>'???/????????????????????????','class'=>'right_textarea iv-input'])?>
				</div>
				<div class="rightModal-footer">
					<button type="button" class="btn btn-default pull-right rightModal-close" style="margin-left:8px;">??????</button>
					<button type="button" class="btn btn-success pull-right" onclick='setMyProvince()'>??????</button>
				</div>
			</div>
			<div class="rightModal items_location_city" style="display: none">
				<div class="rightModal-header">????????????</div>
				<div class="rightModal-body textareabody">
					<?= Html::textarea('Mycity',$rule->items_location_city,['placeholder'=>'???????????????????????????','class'=>'right_textarea iv-input'])?>
				</div>
				<div class="rightModal-footer">
					<button type="button" class="btn btn-default pull-right rightModal-close" style="margin-left:8px;">??????</button>
					<button type="button" class="btn btn-success pull-right" onclick='setMyCity()'>??????</button>
				</div>
			</div>
			<div class="rightModal receiving_country" data='rule4' style="display: none">
				<div class="rightModal-header">????????????</div>
				<div class="rightModal-body">
					<div class='receiving_country' id="receiving_country" style="padding-top:8px;">
						<input class="btn btn-success btn-xs" type="button" value="<?=TranslateHelper::t('????????????');?>" onclick="$('input[name^=receiving_country]:visible').prop('checked',true);">
						<input class="btn btn-danger btn-xs" type="button" value="<?=TranslateHelper::t('????????????');?>" onclick="$('input[name^=receiving_country]:visible').removeAttr('checked');">
						<button type="button" class="btn btn-warning btn-xs display_all_country_toggle">????????????/??????</button>
						<button type="button" class="btn btn-warning btn-xs display_all_country_open">????????????</button>
						<button type="button" class="btn btn-warning btn-xs display_all_country_close">????????????</button>
						<?php foreach ($countrys as $one){?>
						<div>
						<hr/>
						<input class="btn btn-success btn-xs" type="button" value="<?=TranslateHelper::t($region[$one['name']].'('.$one['name'].')'.'??????');?>" onclick="$(this).parent().find('input[type=checkbox]:visible').prop('checked','checked');">
						<input class="btn btn-danger btn-xs" type="button" value="<?=TranslateHelper::t('??????');?>" onclick="$(this).parent().find('input[type=checkbox]:visible').removeAttr('checked');">
						<button type="button" class="btn btn-warning btn-xs display_toggle">??????/??????</button>
						<div style="display:none" class="region_country">
						<?=Html::checkboxList('receiving_countrys', $rule->receiving_country,$one['value'])?>
						</div>
						</div>
						<?php }?>
					</div>
				</div>
				<div class="rightModal-footer">
					<button type="button" class="btn btn-default pull-right rightModal-close" style="margin-left:8px;">??????</button>
					<button type="button" class="btn btn-success pull-right" onclick="setReceivingCountry();">??????</button>
				</div>
			</div>
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
			<div class="rightModal skus" style="display: none">
				<div class="rightModal-header">??????SKU</div>
				<div class="rightModal-body textareabody">
					<?= Html::textarea('sku',$rule->skus,['placeholder'=>'SKU?????????????????????','class'=>'right_textarea iv-input'])?>
				</div>
				<div class="rightModal-footer">
					<button type="button" class="btn btn-default pull-right rightModal-close" style="margin-left:8px;">??????</button>
					<button type="button" class="btn btn-success pull-right" onclick='setSKU()'>??????</button>
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
			<div class="rightModal buyer_transportation_service" style="display: none">
				<div class="rightModal-header">???????????????????????????</div>
				<div class="rightModal-body">
					<div class='buyer_service' id="buyer_service" style="padding-top:8px;">
						<input class="btn btn-success btn-xs" type="button" value="<?=TranslateHelper::t('????????????');?>" onclick="$('input[name^=buyer_transportation_services]:visible').prop('checked',true);">
						<input class="btn btn-danger btn-xs" type="button" value="<?=TranslateHelper::t('????????????');?>" onclick="$('input[name^=buyer_transportation_services]:visible').removeAttr('checked');">
						<button type="button" class="btn btn-warning btn-xs display_all_transportation_toggle">????????????/??????</button>
						<?php foreach ($buyer_transportation_services as $platform=>$buyer_transportation_service){?>
						<div class="<?=$platform?>">
						<?php foreach ($buyer_transportation_service as $site=>$site_services){?>
						<div class="<?=$platform.$site?> <?php if (isset($rule->site[$platform])&&is_array($rule->site[$platform])) {echo in_array($site, @$rule->site[$platform])?'':'sr-only';}else{echo 'sr-only';}?>">
						<hr/>
						<input class="btn btn-success btn-xs" type="button" value="<?=TranslateHelper::t($site.'????????????');?>" onclick="$(this).parent().find('input[type=checkbox]:visible').prop('checked','checked');">
						<input class="btn btn-danger btn-xs" type="button" value="<?=TranslateHelper::t('??????');?>" onclick="$(this).parent().find('input[type=checkbox]:visible').removeAttr('checked');">
						<button type="button" class="btn btn-warning btn-xs display_toggle">??????/??????</button>
						<div style="display:none" class="transportation">
						<?=Html::checkboxList('buyer_transportation_services['.$platform.']['.$site.']',@$rule->buyer_transportation_service[$platform][$site],$site_services,['platform'=>$platform,'site'=>$site])?>
						</div>
						</div>
						<?php }?>
						</div>
						<?php }?>
					</div>
				</div>
				<div class="rightModal-footer">
					<button type="button" class="btn btn-default pull-right rightModal-close" style="margin-left:8px;">??????</button>
					<button type="button" class="btn btn-success pull-right" onclick="setbuyer_transportation_service();">??????</button>
				</div>
			</div>
			<div class="rightModal total_amount" style="display: none">
				<div class="rightModal-header">???????????????</div>
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
		</div>
	</div>
	<div class="clear"></div>

<script>

$(function() {
	setMyCountry();
	setMyProvince();
	setMyCity();
	setReceivingCountry();
	setRecProvince();
	setRecCity();
	setSKU();
	setbuyer_transportation_service();
	setSources();
	set_freight();
	set_totalamount();
	set_totalweight();
	setTag();


	//????????????
	$('input[name^=sourceCheck]').change(function(){
		$('input[name^="sourceCheck"]').each(function(){
			var n = $(this).val();
			if($(this).prop('checked')){
				$("."+n).removeClass('sr-only');
			}else{
				$("."+n).addClass('sr-only');
			}
		})
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
			$('.full_rightDIV').css('width','550');
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
		if($(this).prop('checked')){
			$('.full_rightDIV').css('width','550');
			$('.full_leftDIV').css('width','620');
			$('#'+n).show();
			closeAllRightModals();
			$('.'+n).show();
// 			$('#rulesDIV').html($(this).parent().parent().html());
		}else{
			closeAllRightModals();
			$('#'+n).hide();
			$('.full_rightDIV').css('width','0');
			$('.full_leftDIV').css('width','1220');
		}
	});
	$(".display_all_transportation_toggle").click(function(){
		$('.transportation').each(function(){
			obj=$(this);
			var hidden = obj.css('display');
			if(typeof(hidden)=='undefined' || hidden=='none'){
				obj.css('display','block');
			}else if( hidden=='block'){
				obj.css('display','none');
			}
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
});

</script>