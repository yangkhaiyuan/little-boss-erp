<?php
use eagle\modules\util\helpers\TranslateHelper;
use eagle\modules\order\models\OdOrder;
use eagle\models\QueueDhgateGetorder;
use eagle\modules\tracking\models\Tracking;
use eagle\modules\order\helpers\OrderTrackerApiHelper;
use eagle\modules\util\helpers\StandardConst;
use yii\db\Query;
use eagle\modules\order\helpers\OrderTagHelper;
use eagle\modules\message\helpers\MessageHelper;

$this->registerJsFile(\Yii::getAlias('@web')."/js/project/message/customer_message.js", ['depends' => ['yii\web\JqueryAsset']]);
//$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/OrderTag.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/OrderTagApi.js", ['depends' => ['yii\web\JqueryAsset']]);
$tag_class_list = OrderTagHelper::getTagColorMapping();
$this->registerJs("OrderTagApi.TagClassList=".json_encode($tag_class_list).";" , \yii\web\View::POS_READY);
$this->registerJs("history_order()", \yii\web\View::POS_READY);

$baseUrl = \Yii::$app->urlManager->baseUrl . '/';
$this->registerJsFile($baseUrl."js/project/message/template/customer_mail_template.js", ['depends' => ['yii\jui\JuiAsset','yii\bootstrap\BootstrapPluginAsset']]);
$this->registerJs("Customertemplate.init();" , \yii\web\View::POS_READY);
$this->registerJs("OrderTagApi.init();" , \yii\web\View::POS_READY);
$this->registerJs("$.initQtip();" , \yii\web\View::POS_READY);
?>
<style>
.z-index{z-index: 1041 !important;}
.z-index-detail{z-index: 1042 !important;}
.order-tag-qtip{z-index: 2040 !important;max-width:800px;}

.btn.btn-disabled {
    background-color: #ccc;
    color: #fff;
    border: 1px solid #ddd;
    cursor: default;
}
</style>
<div class="letter-message" id="letter-message">
    
    <div class="message_right" <?php echo empty($product_list)?"style='display:none'":null;?>>
    <?php if(($headDatas['message_type']==1&&!empty($product_list))||($headDatas['message_type']==2)&&!empty($product_list)):?>
       <?php if($product_list['type']=="order"):?>
       <table class="letter-header">
            <tbody>
                
                    <tr><td>????????????<a onclick="GetOrderid('<?php  echo !empty($product_list['order_source_order_id'])?$product_list['order_source_order_id']:null;?>')"><?php  echo !empty($product_list['order_source_order_id'])?$product_list['order_source_order_id']:null;?></a></td></tr>
                    <tr><td>????????????<a class="message-detail-location" id="location" data-id="<?php echo !empty($product_list['track_no'])?$product_list['track_no']:null?>"><?php echo !empty($product_list['track_no'])?$product_list['track_no']:null?><?php echo !empty($product_list['status'])?"(".Tracking::getChineseStatus($product_list['status']).")":null?></a></td></tr>
                    <tr><td>???????????????<a class="message_order_list" data-id="[{source:'<?php echo empty($order_history['order_source'])?null:$order_history['order_source'];?>',id:'<?php echo !empty($order_history['buyer_id'])?$order_history['buyer_id']:null;?>',list_style:'history-detail-location'}]" >????????????(<?php echo !empty($product_list['list_num'])?$product_list['list_num']:"0"; ?>)</a></td></tr>
                    <tr><td>???????????????<?php echo !empty($product_list['consignee_country_code'])?StandardConst::$COUNTRIES_CODE_NAME_CN[$product_list['consignee_country_code']]:null;?></td></tr>
                    <tr><td>???????????????<?php echo !empty($product_list['source_buyer_user_id'])?$product_list['source_buyer_user_id']:null;?></td></tr>
                    <tr><td><?php echo empty($product_list['order_source'])?null:$product_list['order_source'];?>???????????????<span>
                        <?php 
                        if(!empty($product_list['order_source'])){
                            switch ($product_list['order_source'])
                            {
                                case "dhgate":
                                    echo QueueDhgateGetorder::$orderStatus[$product_list['order_source_status']];
                                    break;
                                case "aliexpress":
                                    echo OdOrder::$aliexpressStatus[$product_list['order_source_status']];
                                    break;
                                default:
                                    echo $product_list['order_source_status'];
                                    break;
                            }
                        }
                        ?>
                   </span></td></tr>
                    <tr><td>?????????????????????<span><?php echo !empty($product_list['order_status'])?OdOrder::$status[$product_list['order_status']]:null;?></span></td></tr>
                    <tr><td>???????????????<?php echo !empty($product_list['delivery_time'])?date("Y-m-d",$product_list['delivery_time']):null;?></td></tr>
                    <tr><td>???????????????<?php echo !empty($product_list['paid_time'])?date("Y-m-d",$product_list['paid_time']):null;?></td></tr>
                    <tr><td>????????????<?php echo !empty($product_list['currency'])?$product_list['currency']:null;?> <?php echo !empty($product_list['grand_total'])?$product_list['grand_total']:null;?></td></tr>
            </tbody>
       </table>
       <?php endif;?>
       <?php if($product_list['type']=="sku"):?>
        <div class="sku-list">
            <img src="<?= (!empty($product_list['photo_primary'])?$product_list['photo_primary']:"")?>" />
            <p>??????sku:<?= (!empty($product_list['sku'])?$product_list['sku']:"")?></p>
            <p><?= (!empty($product_list['name'])?$product_list['name']:"")?></p>
        </div>
       <?php endif;?>
    <?php endif;?>   
    
    
	    <div class="product-list">
	    	<?php if(!empty($product_list['items'])):?>
			  <?php 
			  if (is_string($product_list['items'])){
				$items = json_decode($product_list['items'],true);
			  }
			  else{
				$items = $product_list['items'];
			  }
			  foreach ($items as $anItem):?>
	        <div class="product">
	           <img src="<?= (!empty($anItem['photo_primary'])?$anItem['photo_primary']:"");?>" />
	           <p class="product_title"><?= (!empty($anItem['product_name'])?$anItem['product_name']:"")?><br ><span>?????????<?= (!empty($anItem['ordered_quantity'])?$anItem['ordered_quantity']:0)?></span> </p>  
	        </div>
	        <?php endforeach;?>
	        <?php endif;?>
	    </div>
    	
    	<div class="comment-list" id="comment_list_<?=$product_list['order_id'] ?>">
    		<div style="background-color:#d9effc;display:inline-block;clear:both;width: 100%;text-align: center;margin-top: 5px;">
    			<div style="display:inline-block;">
    				<span style="font-size: 15px;display:inline-block;float:left;">?????????????????????:</span><span qtipkey="order_tag_desc_api" style="float:right;"></span>
    			</div>
	    			
    		</div>
    		<div id="order_tag_list_<?=$product_list['order_id'] ?>" style="min-width:15px;display:inline-block;margin-top:5px;">
    		<?php 
	    		//$divTagHtml = '<div id="div_order_tag_'.$product_list['order_id'].'"  name="div_add_tag" class="div_space_toggle"></div>';
	    		$TagStr = OrderTagHelper::generateTagIconHtmlByOrderId($product_list['order_id']);
	    		
	    		if (!empty($TagStr)){
	    			$TagStr = "<span class='btn_order_tag_qtip' data-order-id='".$product_list['order_id']."' >$TagStr</span>";
	    		}
	    		echo $TagStr;
	    		?>
	    	</div>
    		<span style="width:100%;display: inline-block;margin: 10px 0px;" id="desc_content_<?=$product_list['order_id'] ?>">
    			<?=empty($product_list['desc'])?null:$product_list['desc'] ?>
    		</span>
    		<textarea rows="3" cols="30" id="add_<?=$product_list['order_id'] ?>_desc" style="display:none">
    		
    		</textarea>
    		<input type="button" class="btn btn_xs btn-success" id="addDesc_<?=$product_list['order_id'] ?>_btn" onclick="addDesc('<?=$product_list['order_id'] ?>')" value="???????????? ">
    		<input type="button" class="btn btn_xs btn-success" id="saveDesc_<?=$product_list['order_id'] ?>_btn" onclick="saveDesc('<?=$product_list['order_id'] ?>')" style="display:none" value="??????">
    		<input type="button" class="btn btn_xs btn-success" id="cancleAddDesc_<?=$product_list['order_id'] ?>_btn" onclick="cancleAddDesc('<?=$product_list['order_id'] ?>')" style="display:none" value="??????">
    	</div>
    
    </div>
    <div class="chat-message" <?php echo empty($product_list)?"style='width:100%'":null;?>>
            <?php if($headDatas['platform_source'] == 'cdiscount'  || $headDatas['platform_source'] == 'priceminister'):?>
            <div style="margin-top: -11px;">
                <span style="font-size: 13px;">????????????</span>
                <input type="button" value="??????" name="trans_button" onclick="translateLanguage('fra',this)" class="language_translate">
                <input type="button" value="??????" name="trans_button" onclick="translateLanguage('en',this)" class="language_not_translate">
                <input type="button" value="??????" name="trans_button" onclick="translateLanguage('zh',this)" class="language_not_translate">
                <span style="font-size: 13px;">????????????</span><input type="button" value="???" name="turn_button" onclick="compareContent(1,this)" class="language_not_translate trans_button_padding"><input type="button" value="???" name="turn_button" onclick="compareContent(2,this)" class="language_not_translate trans_button_padding">
            </div>
            <?php endif;?>
            <div class="all-chat" 
            <?php 
            if($headDatas['platform_source'] == 'cdiscount'){
                echo 'style="height:570px;"';
            }else if($headDatas['platform_source'] == 'priceminister'){
                echo 'style="height:424px;"';
            }
            ?>
            >
            <?php if($headDatas['message_type']==1)://?????????????> 
                <?php 
                
                foreach ($connect as $contents):
                if(!empty($contents['addi_info'])){//??????????????????
                    $error_addi_info=json_decode($contents['addi_info'],true);
                    $error_message=$error_addi_info['error'];
                }else {
                    $error_message="";
                }
                $track_no=!empty($contents['track_no'])?"({$contents['track_no']})":null;
                ?>
                    <div 
                    <?php echo $contents['send_or_receiv']==1?"class='right-message'":"class='left-message'";?>
                    <?php 
                        if(!empty($contents['addi_info'])){//priceminister???????????????claim
                            $message_addi_info = json_decode($contents['addi_info'],true);
                            if(isset($message_addi_info['is_claim'])&&$message_addi_info['is_claim'] == 1){
                                echo 'style="background-color: coral;"';
                            }
                        }
                    ?>
                    >
                        <div class="message-content" data-id=<?php echo $contents['msg_id']?>>
                        <?php echo ($contents['send_or_receiv']==1&&$contents['status']=='F')?"<div style='font-size:12px;margin-top:-5px;'><img src=''><span style='color:#fa7936;'>{$error_message}</span>&nbsp;&nbsp;<a class='resent-message' data-reid=\"[{ticket_id:'{$contents['ticket_id']}',msg_id:'{$contents['msg_id']}',source:'{$headDatas['platform_source']}',seller_id:'{$headDatas['seller_id']}',ticket_id:'{$headDatas['ticket_id']}',buyer_id:'{$headDatas['buyer_id']}',nick_name:'{$headDatas['seller_nickname']}'}]\">????????????</a>&nbsp;&nbsp;<a class='remove-message' data-id=\"[{ticket_id:'{$contents['ticket_id']}',msg_id:'{$contents['msg_id']}',source:'{$headDatas['platform_source']}',seller_id:'{$headDatas['seller_id']}',ticket_id:'{$headDatas['ticket_id']}',buyer_id:'{$headDatas['buyer_id']}'}]\">????????????</a>&nbsp;&nbsp;<a onclick=\"EditReSent('{$contents['ticket_id']}','{$contents['msg_id']}',this,'{$headDatas['platform_source']}','{$headDatas['seller_id']}','{$headDatas['ticket_id']}','{$headDatas['buyer_id']}','{$headDatas['seller_nickname']}')\" >??????????????????</a><br /></div>":null;?>
                        <p <?php echo ($headDatas['platform_source'] == 'cdiscount'||$headDatas['platform_source'] == 'priceminister')?"name='fra'":'';?> <?php echo $contents['send_or_receiv']==1?"class='seller_msg'":null;?>><?php echo $contents['content'];?></p><?php echo $contents['haveFile']==1?"<img src='{$contents['fileUrl']}'>":null;?>
                        
                        <?php if($headDatas['platform_source'] == 'cdiscount'||$headDatas['platform_source'] == 'priceminister'):?>
                        <span name="newline"></span>
                        <p name="en" style="display: none"><?php echo !empty($contents['English_content'])?$contents['English_content']:'';?></p>
                        <p name="zh" style="display: none"><?php echo !empty($contents['Chineses_content'])?$contents['Chineses_content']:'';?></p>
                        <?php endif;?>
                        
                        </div>
                        <div class="message-header">
                            <div class="message-bottom">
                                <?php echo $contents['send_or_receiv']==1?"??????":"????????????";?>&nbsp;&nbsp;<?php echo $contents['send_or_receiv']==1?null:"<a onclick=\"GetOrderid('{$contents['related_id']}')\">????????????:{$contents['related_id']}</a><a class='message-detail-location' id='location' data-id='{$contents['track_no']}'>{$track_no}</a>"?>
                            </div>
                            <div class="message-bottom">
                                <?php echo $contents['send_or_receiv']==1?$headDatas['seller_nickname']:$headDatas['buyer_nickname'];?>&nbsp;&nbsp;<?php echo $contents['platform_time'];?>
                            </div>
                        </div>
                    </div>
                <?php endforeach;?>
            <?php endif;?>
            <?php if($headDatas['message_type']==3)://?????????????> 
                <?php 
                foreach ($connect as $contents):
                if(!empty($contents['addi_info'])){//??????????????????
                    $error_addi_info=json_decode($contents['addi_info'],true);
                    $error_message=$error_addi_info['error'];
                }else {
                    $error_message="";
                }
                $track_no=!empty($contents['track_no'])?"({$contents['track_no']})":null;
                ?>
                    <div <?php echo $contents['send_or_receiv']==1?"class='right-message'":"class='left-message'";?>>
                        <div class="message-content" data-id=<?php echo $contents['msg_id']?>>
                        <?php echo ($contents['send_or_receiv']==1&&$contents['status']=='F')?"<div style='font-size:12px;margin-top:-5px;'><img src=''><span style='color:#fa7936;'>{$error_message}</span>&nbsp;&nbsp;<a class='resent-message' data-reid=\"[{ticket_id:'{$contents['ticket_id']}',msg_id:'{$contents['msg_id']}',source:'{$headDatas['platform_source']}',seller_id:'{$headDatas['seller_id']}',ticket_id:'{$headDatas['ticket_id']}',buyer_id:'{$headDatas['buyer_id']}',nick_name:'{$headDatas['seller_nickname']}'}]\">????????????</a>&nbsp;&nbsp;<a class='remove-message' data-id=\"[{ticket_id:'{$contents['ticket_id']}',msg_id:'{$contents['msg_id']}',source:'{$headDatas['platform_source']}',seller_id:'{$headDatas['seller_id']}',ticket_id:'{$headDatas['ticket_id']}',buyer_id:'{$headDatas['buyer_id']}'}]\">????????????</a>&nbsp;&nbsp;<a onclick=\"EditReSent('{$contents['ticket_id']}','{$contents['msg_id']}',this,'{$headDatas['platform_source']}','{$headDatas['seller_id']}','{$headDatas['ticket_id']}','{$headDatas['buyer_id']}','{$headDatas['seller_nickname']}')\" >??????????????????</a><br /></div>":null;?>
                        <p <?php echo ($headDatas['platform_source'] == 'cdiscount'||$headDatas['platform_source'] == 'priceminister')?"name='fra'":'';?> <?php echo $contents['send_or_receiv']==1?"class='seller_msg'":null;?>><?php echo $contents['content'];?></p><?php echo $contents['haveFile']==1?"<img src='{$contents['fileUrl']}'>":null;?>
                        
                        <?php if($headDatas['platform_source'] == 'cdiscount'||$headDatas['platform_source'] == 'priceminister'):?>
                        <span name="newline"></span>
                        <p name="en" style="display: none"><?php echo !empty($contents['English_content'])?$contents['English_content']:'';?></p>
                        <p name="zh" style="display: none"><?php echo !empty($contents['Chineses_content'])?$contents['Chineses_content']:'';?></p>
                        <?php endif;?>
                        
                        </div>
                        <div class="message-header">
                            <div class="message-bottom">
                                <?php echo $contents['send_or_receiv']==1?"??????":"????????????";?>&nbsp;&nbsp;<?php echo ($contents['send_or_receiv']==0&&!empty($contents['related_id']))?"<a onclick=\"GetOrderid('{$contents['related_id']}')\">????????????:{$contents['related_id']}</a><a class='message-detail-location' id='location' data-id='{$contents['track_no']}'>{$track_no}</a>":null;?>
                            </div>
                            <div class="message-bottom">
                                <?php echo $contents['send_or_receiv']==1?$headDatas['seller_nickname']:$headDatas['buyer_nickname'];?>&nbsp;&nbsp;<?php echo $contents['platform_time'];?>
                            </div>
                        </div>
                    </div>
                <?php endforeach;?>
            <?php endif;?>
            <?php if($headDatas['message_type']==2)://??????????>
                    
                    <?php 
                    foreach ($connect as $contents):
                        if(!empty($contents['addi_info'])){//??????????????????
                            $error_addi_info=json_decode($contents['addi_info'],true);
                            $error_message=$error_addi_info['error'];
                        }else {
                            $error_message="";
                        }
                        if($contents['related_type']=='O'){  //????????????
                            $track_no=!empty($contents['track_no'])?"({$contents['track_no']})":null;
                            $class="<a onclick=\"GetOrderid('{$contents['related_id']}')\">????????????:{$contents['related_id']}</a><a class='message-detail-location' id='location' data-id='{$contents['track_no']}'>{$track_no}</a>";
                        }else if($contents['related_type']=='P'){ //??????
                            if(!empty($headDatas['platform_source'])&&!empty($headDatas['buyer_id'])&&!empty($contents['related_id']))
                            {
                                $customerArr=['source_buyer_user_id' => $headDatas['buyer_id']];
                                $skulist=['sku'=>$contents['related_id']];
                                $result=OrderTrackerApiHelper::getOrderList($headDatas['platform_source'],$customerArr,$skulist);//??????sku??????????????????????????????
                            }else {
                                $result=array();
                                $result['success']=false;
                            }
                            $all_list=array();
                            if($result['success']==true){
                                $all_list=$result['orderArr']['data'];
                            }else{
                                $all_list=null;
                            }
                            if(!empty($all_list)){//??????????????????????????????????????????
                                $num=count($all_list);
                                $class="<a class='sku_order_list' data-id=\"[{source:'{$headDatas['platform_source']}',id:'{$headDatas['buyer_id']}',list_style:'history-detail-location',sku:'{$contents['related_id']}'}]\">????????????:{$contents['related_id']}({$num}???????????????)</a>";
                            }else{
                                if(!empty($contents['productUrl'])){
                                    $class="????????????:{$contents['related_id']}&nbsp;<a href='{$contents['productUrl']}' target='_blank'>(????????????)</a>";
                                }else{
                                    $class="????????????:{$contents['related_id']}";
                                }
                            }
                        }else{//?????????S????????????????????????????????????
                            $track_no=!empty($contents['track_no'])?"({$contents['track_no']})":null;
                            if(!empty($contents['related_id'])){
                                $class="<a onclick=\"GetOrderid('{$contents['related_id']}')\">????????????:{$contents['related_id']}</a><a class='message-detail-location' id='location' data-id='{$contents['track_no']}'>{$track_no}</a>";
                            }else{
                                $class="";
                            }    
                        }
                    ?>
                    <div <?php echo $contents['send_or_receiv']==1?"class='right-message'":"class='left-message'";?>>
                        <div class="message-content" data-id=<?php echo $contents['msg_id']?>>
                        <?php echo ($contents['send_or_receiv']==1&&$contents['status']=='F')?"<div style='font-size:12px;margin-top:-5px;'><img src=''><span style='color:#fa7936;'>{$error_message}</span>&nbsp;&nbsp;<a class='resent-message' data-reid=\"[{ticket_id:'{$contents['ticket_id']}',msg_id:'{$contents['msg_id']}',source:'{$headDatas['platform_source']}',seller_id:'{$headDatas['seller_id']}',ticket_id:'{$headDatas['ticket_id']}',buyer_id:'{$headDatas['buyer_id']}',nick_name:'{$headDatas['seller_nickname']}'}]\">????????????</a>&nbsp;&nbsp;<a class='remove-message' data-id=\"[{ticket_id:'{$contents['ticket_id']}',msg_id:'{$contents['msg_id']}',source:'{$headDatas['platform_source']}',seller_id:'{$headDatas['seller_id']}',ticket_id:'{$headDatas['ticket_id']}',buyer_id:'{$headDatas['buyer_id']}'}]\">????????????</a>&nbsp;&nbsp;<a onclick=\"EditReSent('{$contents['ticket_id']}','{$contents['msg_id']}',this,'{$headDatas['platform_source']}','{$headDatas['seller_id']}','{$headDatas['ticket_id']}','{$headDatas['buyer_id']}','{$headDatas['seller_nickname']}')\" >??????????????????</a><br /></div>":null;?>
                        <p <?php echo ($headDatas['platform_source'] == 'cdiscount'||$headDatas['platform_source'] == 'priceminister')?"name='fra'":'';?> <?php echo $contents['send_or_receiv']==1?"class='seller_msg'":null;?>><?php echo $contents['content'];?></p><?php echo $contents['haveFile']==1?"<img src='{$contents['fileUrl']}'>":null;?>
                        
                        <?php if($headDatas['platform_source'] == 'cdiscount'||$headDatas['platform_source'] == 'priceminister'):?>
                        <span name="newline"></span>
                        <p name="en" style="display: none"><?php echo !empty($contents['English_content'])?$contents['English_content']:'';?></p>
                        <p name="zh" style="display: none"><?php echo !empty($contents['Chineses_content'])?$contents['Chineses_content']:'';?></p>
                        <?php endif;?>
                        
                        </div>
                        <div class="message-header">
                            <div class="message-bottom">
                                <?php echo $contents['send_or_receiv']==1?"??????":($contents['related_type']=='O'?"????????????":($contents['related_type']=="P"?"?????????":"????????????"));?>&nbsp;&nbsp;<?php echo ($contents['send_or_receiv']==0&&!empty($contents['related_id']))?$class:null?>
                            </div>
                            <div class="message-bottom">
                                <?php echo $contents['send_or_receiv']==1?$headDatas['seller_nickname']:$headDatas['buyer_nickname'];?>&nbsp;&nbsp;<?php echo $contents['platform_time'];?>
                            </div>
                        </div>
                    </div>
                <?php endforeach;?>
            <?php endif;?>
            </div>
            <?php if($headDatas['message_type']!=3&&$headDatas['platform_source'] != 'cdiscount')://?????????????>
            <div style="float:right;">???????????????
            <select name="template_id" id="template_id" class="eagle-form-control" style="margin-bottom: 4px;max-width: 300px;">
                <option value='-1'>????????????</option>
                <?php if(!empty($data['data'])):?>
                <?php foreach ($data['data'] as $data_language):?>
                <option value="<?php echo $data_language['id']?>"><?php echo $data_language['template_name']?>???<?php echo $data_language['type']=="C"?"??????":($data_language['type']=="L"?"?????????":null)?>???</option>
                <?php endforeach;?>
                <?php endif;?>
            </select>
            <select name="template_language" id="template_language" class="eagle-form-control" style="margin-bottom: 4px;">
                <option>????????????</option>
            </select>
            <a target="_blank" href="/message/all-customer/mail-template?select_platform=template-manage&selected_type=template-manage"><input type="button" value="????????????" class="btn btn-default btn-sm"></a>
            <input type="hidden" name="language_related_id" id="language_related_id" value="<?php echo $headDatas['related_id']?>">
            <input type="hidden" name="language_seller_id" id="language_seller_id" value="<?php echo $headDatas['seller_id']?>">
			<input type="hidden" name="language_ticket_id" id="language_ticket_id" value="<?php echo $headDatas['ticket_id']?>">
            </div>
            <?php endif;?>
            <?php if(($headDatas['message_type']==1&&$headDatas['platform_source'] != 'cdiscount')||$headDatas['message_type']==2):?>   
            <div class="sent-message" <?php echo empty($product_list)?"style='width:100%'":null;?>>
                <textarea class="message-area"></textarea>
                <div class="no-qtip-icon" qtipkey="cs_do_reply" ><input type="button" onclick="SentMessge('<?php echo $headDatas['ticket_id']?>')" value="????????????" class="btn btn-success" style="float: right; margin-top:5px;"></div>
                <?php if(!empty($message_state))://??????outstanding?>
                    <?php if($message_state['os_flag']==0){//?????????outstanding??????
                        echo '';}
                    ?> 
                    <?php if($message_state['os_flag']==1||$headDatas['has_replied']==0)://????????????????????????os_flag???0???????????????has_replied?>
                        <div class="no-qtip-icon" qtipkey="cs_mark_handled"><input type="button" value="???????????????" onclick="TabMessage('<?php echo $headDatas['platform_source']?>','<?php echo $headDatas['ticket_id']?>')" class="btn btn-success" style="float:right; margin-top:5px; margin-right:10px;"></div>
                    <?php endif;?>
                <?php endif;?>
                <?php if($headDatas['platform_source'] == "aliexpress"&&$headDatas['related_type'] == "O"):?>
                    <?php if(!empty($headDatas['related_id'])){
                        $status_result = MessageHelper::searchOrderStatus($headDatas['related_id'], $headDatas['platform_source']);
                        if($status_result["success"]&&$status_result["status"] == 300){
                    ?>
                    <input type="button" value="????????????????????????" onclick="showExtendsBuyerAcceptGoodsTimeBox('<?php echo $headDatas['related_id'];?>','<?php echo $headDatas['platform_source'];?>')" class="btn btn-success" style="float: right; margin-top:5px; margin-right:10px;">
                    <?php 
                        }
                    }
                    ?>
                <?php endif;?>
                <input type="button" value="??????" data-dismiss="modal" class="btn btn-success" style="float: right; margin-top:5px; margin-right:10px;">
                <?php if($error_message_num!=0):?>
                    <input type="button" value="????????????????????????(<?php echo $error_message_num;?>)" onclick="ResentAllMessage('<?php echo $headDatas['platform_source']?>','<?php echo $headDatas['seller_id']?>','<?php echo $headDatas['ticket_id']?>','<?php echo $headDatas['buyer_id']?>')" class="btn btn-info" style="float: right; margin-top:5px; margin-right:10px;">
                <?php endif;?>
                
            </div>
            <?php endif;?>
<?php if(!empty($upOrDownDiv)){ ?>
                	<span data-toggle="tooltip" data-placement="top" id="upShow" data-html="true" style="display:inline-block;width:100px;margin-top: 5px;" title="<?php echo ($upOrDownDiv['cursor']==1||empty($upOrDownDiv['cursor']))?'?????????????????????':''; ?>">
						<button type="button" id="upbtnForOrder" class="btn btn-default mBottom20 <?php echo ($upOrDownDiv['cursor']==1 || empty($upOrDownDiv['cursor']))?'btn-disabled':''; ?>" onclick="<?php echo ($upOrDownDiv['cursor']==1||empty($upOrDownDiv['cursor']))?'':'ShowDetailMessage(\''.str_replace('@@', '\',\'', $upOrDownDiv['up']).'\');'; ?>"><strong>?????????</strong></button>
					</span>
					<span data-toggle="tooltip" data-placement="top" id="downShow" data-html="true" style="display:inline-block;width:110px;margin-top: 5px;" title="<?php echo ($upOrDownDiv['cursor']==3||empty($upOrDownDiv['cursor']))?'????????????????????????':''; ?>">
						<button type="button" id="downbtnForOrder" class="btn btn-default <?php echo ($upOrDownDiv['cursor']==3||empty($upOrDownDiv['cursor']))?'btn-disabled':''; ?>" onclick="<?php echo ($upOrDownDiv['cursor']==3||empty($upOrDownDiv['cursor']))?'':'ShowDetailMessage(\''.str_replace('@@', '\',\'', $upOrDownDiv['down']).'\');'; ?>"><strong>?????????</strong></button>
					</span>
                <?php } ?>
    </div>
    
</div>
<div class="order_tag_dialog_<?=$product_list['order_id'] ?>"></div>
<?php //echo $divTagHtml; ?>
<script>
</script>
