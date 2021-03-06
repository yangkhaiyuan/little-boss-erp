<?php
use yii\helpers\Html;
use eagle\modules\message\helpers\ResolutionEbayHelper;

// $this->registerJsFile(\Yii::getAlias('@web')."/js/project/message/customer_message.js", ['depends' => ['yii\web\JqueryAsset']]);


$baseUrl = \Yii::$app->urlManager->baseUrl . '/';
$this->registerJsFile($baseUrl."js/project/message/template/customer_mail_template.js", ['depends' => ['yii\jui\JuiAsset','yii\bootstrap\BootstrapPluginAsset']]);
$this->registerJs("Customertemplate.init();" , \yii\web\View::POS_READY);
$this->registerJs("$.initQtip();" , \yii\web\View::POS_READY);

?>
<style>
.z-index{z-index: 1041 !important;}
.z-index-detail{z-index: 1042 !important;}
.letter-message{
	float:left;
	height:360px;
	width:585px;
}
.message-content{
	padding:2px;
	font-size:12px;
}
.chat-message{
	height:360px;
	width:585px;
}
.chat-message>.all-chat{
	height:360px;
	width:585px;
}
.deal-div{
	height:220px;
	width:585px;
}
.case-div{
	float:right;
	width:235px;
	height:580px;
}
.slovetext{
	display:none;
	margin-top:5px;
}
.btn{
	margin-top:30px;
}
.form-control{
	width:70%;
}
p{
	margin:0 0 2px;
}
</style>
	<div class="letter-message" id="letter-message">
	    <div class="chat-message" >
	            <div class="all-chat">
	                    <?php 
	                    $responsehistorys = unserialize($ebayUserCaseEbpdetailone['responsehistory']);
	                    
	                    if (isset($responsehistorys['note'])){
							$_tmp = ['0'=>$responsehistorys];
							$responsehistorys = $_tmp;
						}
	                    ?>
	                    <?php if (count($responsehistorys)>0 && $responsehistorys!=false):?>
	                    <?php foreach ($responsehistorys as $responsehistory):
	                    ?>
	                    <div <?php echo $responsehistory['author']['role']=='SELLER' ? "class='right-message'":"class='left-message'";?>>
	                    	<p><strong><?=$responsehistory['author']['role'].':'.$responsehistory['activityDetail']['description'].'['.$responsehistory['creationDate'].']'?></strong></p>
	                    	<?php
								if(isset($responsehistory['note'])){
	                    	?>
	                        	<div class="message-content">
	                        	<p <?php echo $responsehistory['author']['role']=='SELLER' ? "class='seller_msg'":null;?>><?php echo $responsehistory['note'];?></p>
		                        </div>
	                        <?php } ?>
	                    </div>
	                <?php endforeach;?>
	                <?php endif;?>
	            </div>
	    </div>
	    <div class="deal-div">
	    <?php if (isset($result['error']['message'])):?>
	    <strong>????????????:<?=$result['error']['message']?></strong>
	    <?php endif;?>
	    <?php if ($ebayUserCase->status_value == 'CLOSED'):?>
	    <strong>??????????????????,???????????????</strong>
	    <?php endif;?>
	    <?php if (isset($result['ack']) && $result['ack'] == 'Failure'):?>
	    <strong>????????????:<?=$result['errorMessage']['error']['message']?></strong>
	    <?php endif;?>
	    <?php if (isset($result['ack']) && ($result['ack'] == 'Success' || $result['ack'] == 'Warning')):?>
	    <?php $doselect = [];$preference='';?>
	    <?php if (isset($result['activityOptions']) && count($result['activityOptions']) && $result['activityOptions']!=''){
	    	foreach ($result['activityOptions'] as $onekey=>$oneval){
				$doselect[$onekey] = ResolutionEbayHelper::$activity[$onekey];
				if ($oneval['buyerPreference']=='true'){
					$preference=$onekey;
				}
			}
	    }?>
	    <?php if (count($doselect)==0):?>
	    <strong>?????????????????????</strong>
	    <?php else:?>
	    <form name="a" id="a">
	    <?=Html::hiddenInput('caseid',$ebayUserCase->caseid)?>
	    <?=Html::hiddenInput('ttype',$ebayUserCase->type)?>
	    <?=Html::dropDownList('doselect','',$doselect,['onchange'=>'selectdo()','prompt'=>''])?><strong>????????????:</strong><?=@ResolutionEbayHelper::$activity[$preference]?>
	    <?php endif;?>
	    	<div id="offerothersolution" class="slovetext">
	    		<div class="form-inline">
	  			<?=Html::textarea('offerothersolutiontext','',['id'=>'offerothersolutiontext','cols'=>'60','rows'=>'4','class'=>'form-control'])?>
	  			<?=Html::Button('??????',['class'=>"btn btn-primary",'onclick'=>'dopost()'])?>
	  			</div>
	  		</div>
	  		<div  id="requestbuyertoreturn" class="slovetext">
	  			<p><strong>City</strong><?=Html::textInput('returncity','',['size'=>8])?>
	  			<strong>Country</strong><?=Html::dropDownList('returncountry','',$country)?></p>
	  			<p>
	  				<strong>Name</strong><?=Html::textInput('returnname','',['size'=>15])?>
	  				<strong>postalCode</strong><?=Html::textInput('returnpostalcode','',['size'=>8])?>
	  				<strong>stateorProvince</strong><?=Html::textInput('returnstate','',['size'=>15])?>
	  			</p>
	  			<p>
	  				<strong>street1</strong><?=Html::textInput('returnstreet1','',['size'=>50])?>
	  			</p>
	  			<p>
	  				<strong>street2</strong><?=Html::textInput('returnstreet2','',['size'=>50])?>
	  			</p>
	  				<div class="form-inline">
	  				<?=Html::textarea('requestbuyertoreturn','',['id'=>'requestbuyertoreturn','cols'=>'60','rows'=>'4','class'=>'form-control'])?>
	  				<?=Html::Button('??????',['class'=>"btn btn-primary",'onclick'=>'dopost()'])?>
	  				</div>
  			</div>
  			<div  id="escalatetocustomersupport" class="slovetext">
  				<strong>Reason</strong><select name="reason">
  					<option value="BUYER_STILL_UNHAPPY_AFTER_REFUND">BUYER_STILL_UNHAPPY_AFTER_REFUND[???????????????????????????]</option>
  					<?php if ($ebayUserCase->type=='EBP_INR'){ ?>
  					<option value="ITEM_SHIPPED_WITH_TRACKING">ITEM_SHIPPED_WITH_TRACKING[???????????????]</option>
  					<?php }?>
  					<option value="OTHER">OTHER[??????]</option>
  					<option value="TROUBLE_COMMUNICATION_WITH_SELLER">TROUBLE_COMMUNICATION_WITH_SELLER[?????????????????????]</option>
  				</select>
  				<div class="form-inline">
	  				<?=Html::textarea('reasontext','',['id'=>'reasontext','cols'=>'60','rows'=>'4','class'=>'form-control'])?>
	  				<?=Html::Button('??????',['class'=>"btn btn-primary",'onclick'=>'dopost()'])?>
	  			</div>
  			</div>
  			<div  id="providetrackinginfo" class="slovetext">
	  			trackingNumber[?????????]:<?=Html::textInput('trackingnumber','',['size'=>15])?>&nbsp;&nbsp;&nbsp;&nbsp;
	  			carrierUsed[????????????]:<?=Html::textInput('trackingcarrier','',['size'=>15])?>
	  			<div class="form-inline">
	  				<?=Html::textarea('trackingtext','',['id'=>'trackingtext','cols'=>'60','rows'=>'4','class'=>'form-control'])?>
	  				<?=Html::Button('??????',['class'=>"btn btn-primary",'onclick'=>'dopost()'])?>
	  			</div>
	  		</div>
	  		<div  id="provideshippinginfo" class="slovetext">
	  			<div class="form-inline">
	  			shippeddate[????????????]:<?=Html::input('date','shippeddate','',['class'=>'form-control'])?>&nbsp;&nbsp;&nbsp;&nbsp;
	  			carrierUsed[????????????]:<?=Html::textInput('trackingcarrier2','',['size'=>15])?>
	  			</div>
	  			<div class="form-inline">
	  				<?=Html::textarea('trackingtext2','',['id'=>'trackingtext2','cols'=>'60','rows'=>'4','class'=>'form-control'])?>
	  				<?=Html::Button('??????',['class'=>"btn btn-primary",'onclick'=>'dopost()'])?>
	  			</div>
	  		</div>
	  		<div  id="providerefundinfo" class="slovetext">
	  			<div class="form-inline">
	  				<?=Html::textarea('refundmessage','',['id'=>'refundmessage','cols'=>'60','rows'=>'4','class'=>'form-control'])?>
	  				<?=Html::Button('??????',['class'=>"btn btn-primary",'onclick'=>'dopost()'])?>
	  			</div>
	  		</div>
	  		<div  id="offerpartialrefund" class="slovetext">
	  			amount:<?=Html::textInput('amount','',['size'=>15])?>
	  			<div class="form-inline">
	  				<?=Html::textarea('partialrefundmessage','',['id'=>'partialrefundmessage','cols'=>'60','rows'=>'4','class'=>'form-control'])?>
	  				<?=Html::Button('??????',['class'=>"btn btn-primary",'onclick'=>'dopost()'])?>
	  			</div>
	  		</div>
	  		<div  id="issuepartialrefund" class="slovetext">
	  			amount:<?=Html::textInput('amount2','',['size'=>15])?>
	  			<div class="form-inline">
	  				<?=Html::textarea('partialrefundmessage2','',['id'=>'partialrefundmessage2','cols'=>'60','rows'=>'4','class'=>'form-control'])?>
	  				<?=Html::Button('??????',['class'=>"btn btn-primary",'onclick'=>'dopost()'])?>
	  			</div>
	  		</div>
	  		<div  id="appealtocustomersupport" class="slovetext">
	  			<strong>AppealReason[????????????]</strong>
	  			<select name="appealreason">
	  				<option value="DISAGREE_WITH_FINAL_DECISION">DISAGREE_WITH_FINAL_DECISION[?????????????????????]</option>
	  				<option value="NEW_INFORMATION">NEW_INFORMATION[???????????????]</option>
	  				<option value="OTHER">OTHER[??????]</option>
	  			</select>
	  			<div class="form-inline">
	  				<?=Html::textarea('appealreasontext','',['id'=>'appealreasontext','cols'=>'60','rows'=>'4','class'=>'form-control'])?>
	  				<?=Html::Button('??????',['class'=>"btn btn-primary",'onclick'=>'dopost()'])?>
	  			</div>
	  		</div>
	  		<div  id="issuefullrefund" class="slovetext">
	  			<div class="form-inline">
	  				<?=Html::textarea('issuerefundmessage','',['id'=>'issuerefundmessage','cols'=>'60','rows'=>'4','class'=>'form-control'])?>
	  				<?=Html::Button('??????',['class'=>"btn btn-primary",'onclick'=>'dopost()'])?>
	  			</div>
	  		</div>
	  		</form>
	  		<?php endif;?>
	    </div>
	</div>
	<div class="case-div">
	<p><strong>????????????</strong><?=$ebayUserCase->caseid?></p>
	<p><strong>??????</strong><?=$ebayUserCase->buyeruserid?></p>
	<p><strong>??????</strong><?=$ebayUserCase->selleruserid?></p>
	<p><strong>????????????</strong><?=ResolutionEbayHelper::$disputesType[$ebayUserCase->type]?></p>
	<p><strong>????????????</strong><?=ResolutionEbayHelper::$disputesStatus[$ebayUserCase->status_value]?></p>
	<p><strong>??????</strong><a target="_blank" href="http://www.ebay.com/itm/<?=$ebayUserCase->itemid?>"><?=$ebayUserCase->itemtitle?></a></p>
	</div>
<script>
// //????????????
// function SentMessge(ticket_id){
// 	if($(".message-area").val() == ''){
// 		   bootbox.alert('???????????????????????????');
//             return;
// 		}
// 	$.ajax({
// 		type:"GET",
// 		url:'/message/all-customer/sent-message?ticket_id='+ticket_id+'&message='+$(".message-area").val(),
// 		success:function(data){
// 			var content=$(".all-chat");
// 			content.append(data);
// 			$(".message-area").val("");
// 			$(".detail_letter .modal-body").scrollTop($(".detail_letter .modal-body").height());
// 		}
// 	});
// }
//???????????????post???api????????????
function dopost(){
	if($('#doselect').val == 'undefined'){
		bootbox.alert('???????????????');
		return false;
	}
	var obj = $('#a');
	var val = obj.find('input[type=text],input[type=hidden],select,textarea').serialize();
	val=val.replace(/%5B%5D/g,'----').replace(/%5B/g,'\\\\').replace(/%5D/g,'//').replace(/----/g,'[]');
	$.showLoading();
	$.post(global.baseUrl+'message/all-customer/ajax-ebpcase',val,function(r){
		$.hideLoading(); 
		var _r = eval('('+r+')');
        if (_r.ack == 'success'){
            bootbox.alert('???????????????');
        }else if(_r.ack == 'failure'){
        	bootbox.alert(_r.msg);
        }
        
    });
}

//????????????
function selectdo(){
	val = $('select[name=doselect]').val().toLowerCase();
	$('.slovetext').hide();
	$("#"+val).show();
}
</script>
