<?php 
use yii\helpers\Html;
use eagle\models\EbayCategory;
use yii\helpers\Url;
use common\helpers\Helper_Util;
use common\helpers\Helper_Array;
use eagle\modules\util\helpers\TranslateHelper;

//$this->registerJsFile(\Yii::getAlias('@web')."/js/translate.js",[\yii\web\View::POS_HEAD]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/translate.js", ['position' => \yii\web\View::POS_BEGIN]);
$this->registerJs('Translator = new Translate('. json_encode(TranslateHelper::getJsDictionary()).');', \yii\web\View::POS_BEGIN);
//$this->registerJsFile(\Yii::getAlias('@web')."/js/lib/ckeditor/ckeditor.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/ajaxfileupload.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/batchImagesUploader.js", ['depends' => ['yii\bootstrap\BootstrapPluginAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/listing/mubanedit.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/listing/mubaneditload.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/collect/collect_ebayedit.js", ['depends' => ['yii\web\JqueryAsset']]);

$this->registerCssFile(\Yii::getAlias('@web')."/css/batchImagesUploader.css");
?>
<style>
td{
	padding:2px 20px;
}
.bbar{
	position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    display: block;
    height: 65px;
    background:white;
    line-height: 17px;
    overflow: hidden;
}
.left_pannel{
	height:auto;
	float:left;
	background:url(/images/ebay/listing/profile_menu_bg1.png) -23px 0 repeat-y;
	position:fixed;
}
.left_pannel_first{
	float:left;
	background:url(/images/ebay/listing/profile_menu_bg.png) 0 -6px no-repeat;
	margin-bottom:55px;
	height:12px;
	padding-left:12px;
}
.left_pannel_last{
	float:left;
	background:url(/images/ebay/listing/profile_menu_bg.png) 0 -6px no-repeat;
	margin-top:5px;
	height:12px;
	padding-left:12px;
}
.left_pannel>p{
	margin:50px 0;
	background:url(/images/ebay/listing/profile_menu_bg.png) 0 -41px no-repeat;
	padding-left:16px;
}
.left_pannel>p>a{
	color:#333;
	font-weight:bold;
	cursor:pointer;
}
.left_pannel p:hover a{
	color:blue;
	font-weight:bold;
	cursor:pointer;
}
.left_pannel p a:hover{
	color:rgb(165,202,246);
}
.profile{
	float:right;
}
.profilelist{
	float:left;
	border:1px solid #ddd;
	border-radius:3px;
	width:100px;
	margin-right:5px;
	margin-top:1px;
}
.profile>span{
	cursor:pointer; 
	float:left; 
	padding:5px 15px;
	margin-right:4px;
} 
.profile .profile_del{
	margin-right:20px;
}
.save_name_div{
	margin:0 0;
	display:none;
	float:left;
}
.save_name_div .save_name{
	border:3px solid #ddd;
}
.save_name_div .profile_save_btn{
	float:right;
}
#changeexclude{
	font-weight:bold;
	color:rgb(82,137,228);
}

.mianbaoxie{
	margin:12px 0px;
	font-weight:bold;
}
.mianbaoxietitle{
	border-color:rgb(1,189,240);
	border-width:0px 3px;
	border-style:solid;
	margin-right:5px;
}
.requirefix{
	color:red;
}
.main-area{
	width:900px;
}
.title{
	margin:10px 10px 10px 0px;
}
.btndo{
	margin-top:20px;
	padding-bottom:40px;
}
.btndo button{
	margin-left:40px;
	padding-left:30px;
	padding-right:30px;
}
.main-input{
	width:750px;
}
.main-input2{
	width:690px;
}
.main-input3{
	width:610px;
}
.subdiv{
	background-color:rgb(249,249,249);
	padding:12px;
	margin:5px 0px;
}
.forcategory{
	display:block;
	margin:5px; 0px;
}
strong{
	color:rgb(46,204,113);
	font-weight:bold;
	font-size:15px;
}
.wuliudeal{
	color:rgb(46,204,113);
	margin:0px 20px;
	font-size:13px;
	cursor:pointer;
}
.closeshipping{
	color:#ddd;
	float:right;
	margin-top:-10px;
	cursor:pointer;
}
.bianji{
	font-size:15px;
	font-weight:bold;
}
.fanyi{
	margin:10px 0px;
}
</style>
<br/>
<div class="tracking-index col2-layout">
<?=$this->render('_ebay_leftmenu',['active'=>'eBay?????????']);?>
<div class="content-wrapper" >
<form action="" method="post" id="a" name="a">
<div class="col-lg-11"><!-- ?????????????????? -->
<?php if (strlen(@$data['mubanid'])):?>
<?=Html::hiddenInput('mubanid',@$data['mubanid'])?>
<?php endif;?>
<p class="bianji">????????????</p>
<button class="fanyi iv-btn btn-search" onclick="fanyi();">????????????</button>
<!-------------------------------??????begin ------------------------------------->
<div class="main-area" id="account">
	<p class="mianbaoxie"><span class="mianbaoxietitle"></span>??????</p>
	<p class="title">eBay??????<span class="requirefix">*</span></p>
	<?php echo Html::dropDownList('selleruserid',@$data['selleruserid'],$ebayselleruserid,['prompt'=>'?????????eBay??????','id'=>'selleruserid','class'=>'iv-input main-input'])?>
	<p class="title">Paypal??????<span class="requirefix">*</span></p>
	<input class="iv-input main-input" name="paypal" list="paypallist" value="<?=@$data['paypal']?>">
	<datalist id="paypallist">
	<?php if (count($paypals)):foreach ($paypals as $p):?>
	<option value="<?=$p->paypal?>"><?=$p->paypal.'('.$p->desc.')'?></option>
	<?php endforeach;endif;?>
	</datalist>
	<p class="title">???????????????</p>
	<?php echo Html::textInput('storecategoryid',@$data['storecategoryid'],array('id'=>"storecategoryid",'class'=>'iv-input main-input2'))?>
	<?=Html::button('??????',['onclick'=>'doset("storecategoryid")','class'=>'iv-btn btn-search'])?>
	<p class="title">???????????????</p>
	<?php echo Html::textInput('storecategory2id',@$data['storecategory2id'],array('id'=>"storecategory2id",'class'=>'iv-input main-input2'))?>
	<?=Html::button('??????',['onclick'=>'doset("storecategory2id")','class'=>'iv-btn btn-search'])?>
</div>
<!-------------------------------????????????end ------------------------------------->
<!------------------------------ ???????????????begin ------------------------------------->
<div class="main-area" id="siteandspe">
	<p class="mianbaoxie"><span class="mianbaoxietitle"></span>???????????????</p>
	<p class="title">eBay??????<span class="requirefix">*</span></p>
	<?=Html::dropDownList('siteid',@$data['siteid'],$sitearr,['id'=>'siteid','class'=>'iv-input main-input'])?>
	<p class="title">????????????<span class="requirefix">*</span></p>
	<?=Html::dropDownList('listingtype',@$data['listingtype'],$listingtypearr,['id'=>'listingtype','class'=>'iv-input main-input'])?>
	<p class="title">ProductID</p>
	<div class="subdiv">
		<table>
		<?php if (isset($product['upcenabled']) && $product['upcenabled'] == 'Required'){?>
		<tr><td>UPC<span class="requirefix">*</span></td><td><input class="iv-input" name="upc" value="<?php echo $data['upc']?>"></td></tr>
		<?php }elseif (isset($product['isbnenabled']) && $product['isbnenabled'] == 'Required'){?>
		<tr><td>ISBN<span class="requirefix">*</span></td><td><input class="iv-input" name="isbn" value="<?php echo $data['isbn']?>"></td></tr>
		<?php }elseif (isset($product['eanenabled']) && $product['eanenabled'] == 'Required'){?>		
		<tr><td>EAN<span class="requirefix">*</span></td><td><input class="iv-input" name="ean" value="<?php echo $data['ean']?>"></td></tr>
		<?php }else{?>
		<tr><td>EPID</td><td><input class="iv-input" name="epid" value="<?php echo $data['epid']?>"></td></tr>
		<tr><td>ISBN</td><td><input class="iv-input" name="isbn" value="<?php echo $data['isbn']?>"></td></tr>
		<tr><td>UPC</td><td><input class="iv-input" name="upc" value="<?php echo $data['upc']?>"></td></tr>
		<tr><td>EAN</td><td><input class="iv-input" name="ean" value="<?php echo $data['ean']?>"></td></tr>
		<?php }?>
		</table>
	</div>
	<p class="title">???????????????<span class="requirefix">*</span></p>
	<input class="category iv-input main-input3" id="primarycategory" name="primarycategory" size="25" value="<?=$data['primarycategory']?>">
	<input type="button" class="iv-btn" value="????????????" onclick="window.open('<?=Url::to(['/listing/ebaymuban/selectebaycategory','siteid'=>$data['siteid'],'elementid'=>'primarycategory'])?>')">
	<?=Html::button('??????',['onclick'=>'searchcategory("primary")','class'=>'iv-btn btn-search'])?><br/>
	<label class="forcategory">
		<?php if(strlen($data['primarycategory'])){
			$ec=EbayCategory::findBySql('select * from ebay_category where siteid='.$data['siteid'].' AND categoryid='.$data['primarycategory'].' and leaf=1')->one();
			if (empty($ec)){
				echo "<span style='color:red;font-size:10px;'>?????????????????????,???????????????</font>";
			}else{
				echo EbayCategory::getPath($ec,$ec->name,$data['siteid']);
			}
		}
		?>
	</label>
	<p class="title">???????????????</p>
	<input class="category iv-input main-input3" id="secondarycategory" name="secondarycategory" size="25" value="<?php echo $data['secondarycategory']?>">
	<input type="button" class="iv-btn" value="????????????" onclick="window.open('<?=Url::to(['/listing/ebaymuban/selectebaycategory','siteid'=>$data['siteid'],'elementid'=>'secondarycategory'])?>')">
	<?=Html::button('??????',['onclick'=>'searchcategory("second")','class'=>'iv-btn btn-search'])?><br/>
	<label class="forcategory">
		<?php if(strlen($data['secondarycategory'])){
			$ec=EbayCategory::findBySql('select * from ebay_category where siteid='.$data['siteid'].' AND categoryid='.$data['secondarycategory'].' and leaf=1')->one();
			if (empty($ec)){
				echo "<span style='color:red;font-size:10px;'>?????????????????????,???????????????</font>";
			}else{
				echo EbayCategory::getPath($ec,$ec->name,$data['siteid']);
			}
		}
		?>
	</label>
	<?php echo $this->render('_condition',array('condition'=>$condition,'val'=>$data))?>
	<?php echo $this->render('_specific',array('specifics'=>$specifics,'val'=>$data['specific']))?>
	<?php echo $this->render('_variation',array('data'=>$data))?>
</div>
<!-------------------------------???????????????end ------------------------------------->
<!-------------------------------???????????????begin ------------------------------------->
<div class="main-area" id="titleandprice">
	<p class="mianbaoxie"><span class="mianbaoxietitle"></span>???????????????</p>
	<p class="title">???????????????<span class="requirefix">*</span></p>
	<input name="itemtitle" class="iv-input main-input" size="80" value="<?php echo $data['itemtitle']?>" id="itemtitle" onkeydown="inputbox_left('itemtitle',80)" onkeypress="inputbox_left('itemtitle',80)" onkeyup="inputbox_left('itemtitle',80)">
	<span id='length_itemtitle' style="font-weight:bold">80  </span>
	<p class="title">???????????????</p>
	<input name="itemtitle2" class="iv-input main-input" size="80" value="<?php echo $data['itemtitle2']?>">
	<p class="title">Customer Label</p>
	<?php echo Html::textInput('sku',$data['sku'],array('class'=>'iv-input main-input'))?>??????????????????,???????????????
	<p class="title">??????<span class="requirefix">*</span></p>
	<?php echo Html::textInput('quantity',$data['quantity'],array('class'=>'iv-input main-input'))?>??????????????????,???????????????
	<p class="title">LotSize<span class="requirefix">*</span></p>
	<?php echo Html::textInput('lotsize',$data['lotsize'],array('class'=>'iv-input main-input'))?>
	<?php echo $this->render('_price',array('data'=>$data))?>
	<p class="title">??????</p>
	<?php echo Html::textInput('vatpercent',$data['vatpercent'],array('size'=>8,'class'=>'iv-input main-input'))?>%
	<p class="title">????????????</p>
	<div class="subdiv">
	<?php echo Html::checkBox('privatelisting',$data['privatelisting'])?>???????????????????????????(privateListing)
	</div>
	<p class="title">????????????</p>
	<div class="subdiv">
	<?php echo Html::checkBox('outofstockcontrol',$data['outofstockcontrol'],array('uncheckValue'=>0))?>??????????????????0
	</div>
</div>

<!-------------------------------???????????????end ------------------------------------->
<!-------------------------------???????????????begin ------------------------------------->
<div class="main-area" id="picanddesc">
	<p class="mianbaoxie"><span class="mianbaoxietitle"></span>???????????????</p>
    <?php echo $this->render('_imgurl',array('data'=>$data));?>
    <p class="title">??????</p>
	<?php echo Html::textArea('itemdescription',$data['itemdescription'],array('rows'=>40,'cols'=>100,'id'=>'itemdescription','class'=>'iv-editor','items'=>"['bold','italic', 'underline','strikethrough','|', 'forecolor', 'hilitecolor', '|', 'justifyleft', 'justifycenter','justifyright','justifyfull','|', 'insertunorderedlist', 'insertorderedlist', '|', 'outdent', 'indent', '|', 'subscript', 'superscript', '|','selectall', 'removeformat', '|','undo', 'redo','/',
					'fontname','fontsize', 'formatblock','|','cut','copy', 'paste','plainpaste','wordpaste','|','link','unlink','|','image','|'/*,'lazadaImgSpace','|'*/,'fullscreen','source']"))?>
	<p class="title">????????????</p>
	<?php echo Html::dropDownList('template',$data['template'],$mytemplates,array('prompt'=>'','class'=>'iv-input main-input'))?>
	<p class="title">??????????????????</p>
	<?php echo Html::dropDownList('basicinfo',$data['basicinfo'],$basicinfos,array('prompt'=>'','class'=>'iv-input main-input'))?>
	<p class="title">????????????</p>
	<?php echo Html::dropDownList('crossselling',$data['crossselling'],$crosssellings,array('prompt'=>'','class'=>'iv-input main-input'))?>
	<p class="title">????????????(???)</p>
	<?php echo Html::dropDownList('crossselling_two',$data['crossselling_two'],$crosssellings,array('prompt'=>'','class'=>'iv-input main-input'))?>
</div>
<div id="cacheDiv" style="display:none;"></div>
<!-------------------------------???????????????end ------------------------------------->

<!-------------------------------????????????begin ------------------------------------->
<div class="main-area" id="shippingset">
	<div class="profile">
  		<?=Html::dropDownList('profile','',Helper_Array::toHashmap($profile['shippingset'], 'id','savename'),['id'=>'shippingset_profile','class'=>'profilelist','prompt'=>''])?>
  		<span class="iv-btn btn-default profile_load">??????</span>
		<span class="iv-btn btn-default profile_del">??????</span>
		<div class="save_name_div">
			<?=Html::textInput('save_name','',['class'=>'save_name'])?>
			<span class="iv-btn btn-primary profile_save_btn">??????</span>
		</div>
		<span class="iv-btn btn-search profile_save">??????</span>
  	</div>
	<p class="mianbaoxie"><span class="mianbaoxietitle"></span>????????????</p>
    <?php echo $this->render('_shipping',array('data'=>$data,'salestaxstate'=>@$salestaxstate,'shippingserviceall'=>$shippingserviceall))?>
    <p class="title">??????????????????</p>
	<?php echo Html::dropDownList('dispatchtime',$data['dispatchtime'], $dispatchtimemax,['class'=>'iv-input main-input'])?>
</div>
<!-------------------------------????????????end ------------------------------------->
<!-------------------------------???????????????begin ------------------------------------->
<div class="main-area" id="returnpolicy">
	<div class="profile">
  		<?=Html::dropDownList('profile','',Helper_Array::toHashmap($profile['returnpolicy'], 'id','savename'),['id'=>'returnpolicy_profile','class'=>'profilelist','prompt'=>''])?>
  		<span class="iv-btn btn-default profile_load">??????</span>
		<span class="iv-btn btn-default profile_del">??????</span>
		<div class="save_name_div">
			<?=Html::textInput('save_name','',['class'=>'save_name'])?>
			<span class="iv-btn btn-primary profile_save_btn">??????</span>
		</div>
		<span class="iv-btn btn-search profile_save">??????</span>
  	</div>
	<p class="mianbaoxie"><span class="mianbaoxietitle"></span>???????????????</p>
	<p class="title">????????????</p>
	<div class="subdiv">
	<?php echo Html::checkBoxList('paymentmethods',@$data['paymentmethods'], $paymentoption)?>
	</div>
	<p class="title">????????????</p>
	<div class="subdiv">
	<?php echo Html::checkBox('autopay',@$data['autopay'],array('uncheckValue'=>0))?>??????????????????????????????
	</div>
	<p class="title">????????????</p>
	<div class="subdiv">
	<?php echo Html::textArea('shippingdetails[PaymentInstructions]',@$data['shippingdetails']['PaymentInstructions'],array('rows'=>5,'cols'=>60,'class'=>'iv-input'))?>
	</div>
	<?php echo $this->render('_returnpolicy',array('data'=>$data,'return_policy'=>$returnpolicy))?>
	<p class="title">???????????????</p>
	<div class="subdiv">
	  	<table>
		<tr>
		<th>??????</th>
		<td>
		<?php echo Html::dropDownList('country',@$data['country'],$locationarr)?>
		</td>
		</tr>
		<tr>
		<th>??????</th>
		<td>
		<?php echo Html::textInput('location',@$data['location'])?>
		</td>
		</tr>
		<tr>
		<th>??????</th>
		<td>
		<?php echo Html::textInput('postalcode',@$data['postalcode'])?>
		</td>
		</tr>
		</table>
	</div>
</div>
<!-------------------------------???????????????end ------------------------------------->

<!-------------------------------????????????begin ------------------------------------->
<div class="main-area" id="buyerrequire">
	<div class="profile">
		<?=Html::dropDownList('profile','',Helper_Array::toHashmap($profile['buyerrequire'], 'id','savename'),['id'=>'buyerrequire_profile','class'=>'profilelist','prompt'=>''])?>
		<span class="iv-btn btn-default profile_load">??????</span>
		<span class="iv-btn btn-default profile_del">??????</span>
		<div class="save_name_div">
			<?=Html::textInput('save_name','',['class'=>'save_name'])?>
			<span class="iv-btn btn-primary profile_save_btn">??????</span>
		</div>
		<span class="iv-btn btn-search profile_save">??????</span>
	</div>
	<p class="mianbaoxie"><span class="mianbaoxietitle"></span>????????????</p>
    <?php echo $this->render('_buyerrequirement',array('data'=>$data,'buyerrequirementenable'=>$buyerrequirementenable))?>
</div>

<!-------------------------------????????????end ------------------------------------->
<!-------------------------------????????????begin ------------------------------------->
<div class="main-area" id="plusmodule">
	<div class="profile">
		<?=Html::dropDownList('profile','',Helper_Array::toHashmap($profile['plusmodule'], 'id','savename'),['id'=>'plusmodule_profile','class'=>'profilelist','prompt'=>''])?>
		<span class="iv-btn btn-default profile_load">??????</span>
		<span class="iv-btn btn-default profile_del">??????</span>
		<div class="save_name_div">
			<?=Html::textInput('save_name','',['class'=>'save_name'])?>
			<span class="iv-btn btn-primary profile_save_btn">??????</span>
		</div>
		<span class="iv-btn btn-search profile_save">??????</span>
	</div>
	<p class="mianbaoxie"><span class="mianbaoxietitle"></span>????????????</p>
	<p class="title">??????????????????</p>
	<div class="subdiv">
	<?php echo Html::radioList('gallery',@$data['gallery'],array('0'=>'?????????','Featured'=>'Featured($)','Gallery'=>'Gallery($)','Plus'=>'Plus($)'))?>
	</div>
	<p class="title">??????</p>
	<div class="subdiv">
	<?php echo Html::checkBoxList('listingenhancement',@$data['listingenhancement'],$feature_array)?>
	</div>
	<p class="title">?????????</p>
	<div class="subdiv">
	<?php echo Html::radioList('hitcounter',@$data['hitcounter'],array('NoHitCounter'=>'???????????????','BasicStyle'=>'BasicStyle','RetroStyle'=>'RetroStyle'))?>
	</div>
	<p class="title">????????????</p>
	<div class="subdiv">
	<?php echo Html::checkBox('crossbordertrade',@$data['crossbordertrade'],array('uncheckValue'=>0));?>
		<label for="crossbordertrade">
		<?php echo in_array($data['siteid'],array(0,2))?'ebay.co.uk':'ebay.com and ebay.ca'?>
		</label>
	</div>
	<p class="title">??????</p>
	<?php echo Html::textInput('desc',$data['desc'],['class'=>'iv-input main-input'])?>
</div>
<!-------------------------------????????????end ------------------------------------->
<!-- ??????????????????  START-->
<div class="btndo">
<?php echo Html::hiddenInput('act','',['id'=>'act'])?>
<?php echo Html::button('??????',array('onclick'=>'saveebay("verify")','class'=>'donext btn btn-warning'))?>
<?php echo Html::button('??????',array('onclick'=>'saveebay("save")','class'=>'donext btn btn-success'))?>
<?php echo Html::button('??????',array('onclick'=>'preview()','class'=>'donext btn'))?>
<?php echo Html::button('??????????????????',array('onclick'=>'checkitem()','class'=>'donext btn btn-default'))?>
<?=Html::submitButton('',['style'=>'display:none;'])?>
<?php echo Html::hiddenInput('uuid',Helper_Util::getLongUuid())?>
</div>
<!-- ?????????????????? end -->
</div>
<div class="col-lg-1">
<!-- ???????????? -->
	<div class="left_pannel" id="floatnav">
		<div class="left_pannel_first"></div>
		<p onclick="goto('account')"><a>??????</a></p>
		<p onclick="goto('siteandspe')"><a>???????????????</a></p>
		<p onclick="goto('titleandprice')"><a>???????????????</a></p>
		<p onclick="goto('picanddesc')"><a>???????????????</a></p>
		<p onclick="goto('shippingset')"><a>????????????</a></p>
		<p onclick="goto('returnpolicy')"><a>???????????????</a></p>
		<p onclick="goto('buyerrequire')"><a>????????????</a></p>
		<p onclick="goto('plusmodule')"><a>????????????</a></p>
		<div class="left_pannel_last"></div>
	</div>
</div>
</form>
</div>
</div>

<!-- ?????????????????????modal -->
<!-- ????????????Modal??? -->
<div class="modal fade" id="categorysetModal" tabindex="-1" role="dialog" 
   aria-labelledby="myModalLabel" aria-hidden="true">
   <div class="modal-dialog">
      <div class="modal-content">
         
      </div><!-- /.modal-content -->
	</div><!-- /.modal -->
</div>

<!-- ?????????????????????modal -->
<div class="modal fade" id="searchcategoryModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
   <div class="modal-dialog" style="width: 800px;">
      <div class="modal-content">
         
      </div><!-- /.modal-content -->
	</div><!-- /.modal -->
</div>
<script>
function saveebay(str){
	$('#a').bootstrapValidator('validate');
	if($('#a').data('bootstrapValidator').isValid()==false){
		return false;
	}
	$('input[name=act]').val(str);
	document.a.submit();
	document.a.target="";
    document.a.action="";
	$("#act").attr("value","");
}

//??????????????????title?????????
function fanyi(){
	$.showLoading();
	var title = $.trim($('#itemtitle').val());
	if(title.length>0){
		$.post(global.baseUrl+"collect/collect/translate",{str:title},function(r){
			res = eval("("+r+")");
			if(res.response.code == 0){
				$('#itemtitle').val(res.response.data);
			}
		});
	}
	detailT = transition();	
	var splitStr = '|||';
	var detailStr = trimLabel(detailT,splitStr);
    var regP1 = / [a-zA-Z0-9_-]+=("([^\"]*[\u4e00-\u9fa5]+.*?)"|'([^\']*[\u4e00-\u9fa5]+.*?)')/i;

//     var titleStr = getTitle1(detailT,splitStr,regP1);
// 	detailStr = detailStr+splitStr+titleStr;
	var detailArr = detailStr.split(splitStr);
	for(var i=0;i<detailArr.length;i++){
		var s = detailArr[i];
		if($.trim(s)!=""){
			setDetailTransate(s);
		}
		if(i == detailArr.length-1){
			$.hideLoading();
		}
	}
	
//	var description = $('#itemdescription').val();
// 	if(description.length>0){
// 		$.post(global.baseUrl+"collect/collect/translate",{str:description},function(r){
// 			res = eval("("+r+")");
// 			if(res.response.code == 0){
// 				KindEditor.html('#itemdescription',res.response.data);
// 				//$('#itemdescription').html(res.response.data);
// 			}
// 		});
// 	}
//	$.hideLoading();
}
</script>