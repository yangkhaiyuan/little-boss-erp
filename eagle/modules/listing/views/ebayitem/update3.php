<?php

use yii\helpers\Html;
use eagle\models\EbayCategory;
use common\helpers\Helper_Array;
use common\helpers\Helper_Siteinfo;
use yii\helpers\Url;
use eagle\modules\util\helpers\TranslateHelper;
//$this->registerJsFile(\Yii::getAlias('@web')."/js/lib/ckeditor/ckeditor.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/translate.js", ['position' => \yii\web\View::POS_BEGIN]);
$this->registerJs('Translator = new Translate('. json_encode(TranslateHelper::getJsDictionary()).');', \yii\web\View::POS_BEGIN);
$this->registerJsFile(\Yii::getAlias('@web')."/js/ajaxfileupload.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/batchImagesUploader.js", ['depends' => ['yii\bootstrap\BootstrapPluginAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/listing/itemmubanedit.js", ['depends' => ['yii\web\JqueryAsset']]);
?>
<style>
/* body{ */
/* 	font-size:10px; */
/* } */
/* div{ */
/* 	width:expression(document.body.clientWidth + 'px'); */
/* } */
/* .bbar{position: fixed; */
/*         bottom: 0; */
/*         left: 0; */
/*         width: 100%; */
/*         display: block; */
/*         height: 40px; */
/*         background:white; */
/*         line-height: 17px; */
/*         overflow: hidden; */
/*     } */
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
</style>
<script>
//????????????
function inputbox_left(inputId,limitLength,text){
    var o=document.getElementById(inputId);
    if(text==undefined){
        left=limitLength-o.value.length;
    }else{
        left=limitLength-text.length;
    }
    $('#length_'+inputId).html(left);
    if(left>=0){
        $('#length_'+inputId).css({'color':'green'});
    }else{
        $('#length_'+inputId).css({'color':'red'});
    }
}
//??????Ebay?????????????????? ??????json??????
function convertTree(rows){
    nodes = [];  
   // ??????????????????
   for(var i = 0; i< rows.length; i++){  
       var row = rows[i];  
       if (row.category_parentid==0){  
           nodes.push({  
               id:row.categoryid,  
               text:row.category_name
           });  
       }  
   }  
     
   var toDo = [];  
   for(var i = 0; i < nodes.length; i++){  
       toDo.push(nodes[i]);  
   }  
   while(toDo.length){  
       var node = toDo.shift();    // ????????? 
       // ??????????????? 
       for(var i=0; i<rows.length; i++){  
           var row = rows[i];  
           if (row.category_parentid == node.id){  
        	   var child = {id:row.categoryid,text:row.category_name};  
               if (node.children){  
                   node.children.push(child);  
               } else {  
                   node.children = [child];  
               }  
               toDo.push(child);  
           }  
       }  
   }
   return nodes;
}
//?????????????????????
function toEditStep3(){
    var reg =/^(0|[1-9]\d*)$/;
    var resutl1=true;
    var resutl2=true;
    if ($("input[name='quantity[]']").length >0) {//?????????????????????
        $("input[name='quantity[]']").each(function(i){
            if(i < $("input[name='quantity[]']").length){
                var value=Number($(this).val());
                if(!reg.test(value)){
                    resutl1 = false;
                    alert('?????????Quantity????????????????????????');
                    return;
                }
            }
        });
    }

    if ($("input[name='quantity']").length >0) {//????????????
        var v = Number($("input[name='quantity']").val());//??????????????????
        if(!reg.test(v)){
            resutl2 = false;
            alert('??????????????????????????????');
        }
    }

    if((resutl1===true) && (resutl2===true)){
	    document.a.action=global.baseUrl+'listing/ebayitem/update3';
	    document.a.submit();
	    document.a.action="";
    }

}


</script>
<br/>
<div class="tracking-index col2-layout">
<?=$this->render('../_ebay_leftmenu',['active'=>'??????Item']);?>
<div class="content-wrapper" >
<form action="" method="post" id="a" name="a">
<?=Html::hiddenInput('setitemvalues',implode(',', $setitemvalues))?>
<?=Html::hiddenInput('selleruserid',@$data['selleruserid'],['id'=>'selleruserid']);?>
<?php if (strlen(@$data['itemid'])):?>
<?=Html::hiddenInput('itemid',@$data['itemid'])?>
<?php endif;?>
<div class="col-lg-11"><!-- ?????????????????? -->

<!------------------------------ ???????????????begin ------------------------------------->
<div class="main-area" id="siteandspe">
	<p class="mianbaoxie"><span class="mianbaoxietitle"></span>???????????????</p>
	<?php if (in_array('category', $setitemvalues)){?>
	<p class="title">???????????????<span class="requirefix">*</span></p>
	<input class="category iv-input main-input3" id="primarycategory" name="primarycategory" size="25" value="<?=$data['primarycategory']?>">
	<input type="button" class="iv-btn" value="????????????" onclick="window.open('<?=Url::to(['/listing/ebaymuban/selectebaycategory','siteid'=>$data['siteid'],'elementid'=>'primarycategory'])?>')">
	<label class="forcategory">
		<?php if(strlen($data['primarycategory'])){
			$ec=EbayCategory::findBySql('select * from ebay_category where siteid='.$data['siteid'].' AND categoryid='.$data['primarycategory'].' and leaf=1')->one();
			if (empty($ec)){
				echo "<span style='color:red;font-size:10px;'>?????????????????????,???????????????</font>";
			}
		}
		?>
	</label>
	<p class="title">???????????????</p>
	<input class="category iv-input main-input3" id="secondarycategory" name="secondarycategory" size="25" value="<?php echo $data['secondarycategory']?>">
	<input type="button" class="iv-btn" value="????????????" onclick="window.open('<?=Url::to(['/listing/ebaymuban/selectebaycategory','siteid'=>$data['siteid'],'elementid'=>'secondarycategory'])?>')">
	<label class="forcategory">
		<?php if(strlen($data['secondarycategory'])){
			$ec=EbayCategory::findBySql('select * from ebay_category where siteid='.$data['siteid'].' AND categoryid='.$data['secondarycategory'].' and leaf=1')->one();
			if (empty($ec)){
				echo "<span style='color:red;font-size:10px;'>?????????????????????,???????????????</font>";
			}
		}
		?>
	</label>
	<?php }?>
	<?php if (in_array('conditionid',$setitemvalues)){?>
	<?=$this->render('_condition',array('condition'=>$condition,'val'=>$data))?>
	<?php }?>
	<?php if (in_array('itemspecifics',$setitemvalues)){?>
		<?php 
		if (isset($data['itemspecifics']['NameValueList']) && is_array($data['itemspecifics']['NameValueList'])){
			if (isset($data['itemspecifics']['NameValueList']['Name'])){
				$tmp = $data['itemspecifics']['NameValueList'];
				unset($data['itemspecifics']['NameValueList']);
				$data['itemspecifics']['NameValueList'][]=$tmp;
			}
			$ItemSpecific = Helper_Array::toHashmap($data['itemspecifics']['NameValueList'],'Name','Value');
		}else{
			$ItemSpecific = array();
			foreach($specifics as $onespecific){
				$ItemSpecific[$onespecific->name]='';
			}
		}
		?>	
		<?=$this->render('_specific',array('specifics'=>$specifics,'val'=>$ItemSpecific))?>
	<?php }?>
	<?php if (in_array('variation',$setitemvalues)){?>
	<?=$this->render('_variation',array('data'=>$data,'product'=>$product))?>
	<?php }?>
</div>
<!-------------------------------???????????????end ------------------------------------->

<!-------------------------------???????????????begin ------------------------------------->
<div class="main-area" id="titleandprice">
	<p class="mianbaoxie"><span class="mianbaoxietitle"></span>???????????????</p>
	<?php if (in_array('itemtitle',$setitemvalues)){?>
	<p class="title">???????????????<span class="requirefix">*</span></p>
	<input name="itemtitle" class="iv-input main-input" value="<?php echo $data['itemtitle']?>" id="itemtitle">
	<span id='length_itemtitle' style="font-weight:bold">80  </span>
	<p class="title">???????????????</p>
	<input name="subtitle" class="iv-input main-input" value="<?php echo $data['subtitle']?>">
	<?php }?>
	
	<?php if (in_array('sku',$setitemvalues)){?>
	<p class="title">Customer Label</p>
	<?php echo Html::textInput('sku',$data['sku'],array('class'=>'iv-input main-input'))?>??????????????????,???????????????
	<?php }?>
	
	<?php if (in_array('quantity',$setitemvalues) && empty($data['variation'])){?>
	<p class="title">??????<span class="requirefix">*</span></p>
	<?php echo Html::textInput('quantity',$data['quantity'],array('class'=>'iv-input main-input'))?>??????????????????,???????????????
	<p class="title">LotSize<span class="requirefix">*</span></p>
	<?php echo Html::textInput('lotsize',$data['lotsize'],array('class'=>'iv-input main-input'))?>
	<?php }?>
	
	<?php if (in_array('listingduration',$setitemvalues)){?>
	<p class="title">????????????</p>
	<?=Html::dropDownList('listingduration',$data['listingduration'],Helper_Siteinfo::getListingDuration($data['listingtype']),array('class'=>'iv-input main-input'))?>
	<?php }?>
	
	<?php if (in_array('price',$setitemvalues)){?>
	<?php echo $this->render('_price',array('data'=>$data))?>
	<?php }?>
	<?php if (in_array('bestoffer',$setitemvalues)){?>
	<?=$this->render('_bestoffer',array('data'=>$data))?>
	<?php }?>
	
	<?php if (in_array('privatelisting',$setitemvalues)){?>
	<p class="title">????????????</p>
	<div class="subdiv">
	<?php echo Html::checkBox('privatelisting',$data['privatelisting'])?>???????????????????????????(privateListing)
	</div>
	<?php }?>
</div>
<!-------------------------------???????????????end ------------------------------------->

<!-------------------------------???????????????begin ------------------------------------->
<div class="main-area" id="picanddesc">
	<p class="mianbaoxie"><span class="mianbaoxietitle"></span>???????????????</p>
	<?php if (in_array('imgurl',$setitemvalues)){?>
    <?php echo $this->render('_imgurl',array('data'=>$data));?>
    <?php }?>
    <?php if (in_array('itemdescription',$setitemvalues)){?>
    <p class="title">??????</p>
	<?=Html::textarea('itemdescription',$data['itemdescription'],array('rows'=>20,'cols'=>100,'class'=>'iv-editor','items'=>"['bold','italic', 'underline','strikethrough','|', 'forecolor', 'hilitecolor', '|', 'justifyleft', 'justifycenter','justifyright','justifyfull','|', 'insertunorderedlist', 'insertorderedlist', '|', 'outdent', 'indent', '|', 'subscript', 'superscript', '|','selectall', 'removeformat', '|','undo', 'redo','/',
					'fontname','fontsize', 'formatblock','|','cut','copy', 'paste','plainpaste','wordpaste','|','link','unlink','|','image','|'/*,'lazadaImgSpace','|'*/,'fullscreen','source']"))?>
	<?php }?>
</div>
<!-------------------------------???????????????end ------------------------------------->

<!-------------------------------????????????begin ------------------------------------->
<div class="main-area" id="shippingset">
	<p class="mianbaoxie"><span class="mianbaoxietitle"></span>????????????</p>
	<?php if (in_array('shippingdetails',$setitemvalues)){?>
    <?php echo $this->render('_shipping',array('data'=>$data,'salestaxstate'=>@$salestaxstate,'shippingserviceall'=>$shippingserviceall))?>
	<?php }?>
	<?php if (in_array('dispatchtime',$setitemvalues)){?>
    <p class="title">??????????????????</p>
	<?php echo Html::dropDownList('dispatchtime',$data['dispatchtime'], $dispatchtimemax,['class'=>'iv-input main-input'])?>
	<?php }?>
</div>
<!-------------------------------????????????end ------------------------------------->

<!-------------------------------???????????????begin ------------------------------------->
<div class="main-area" id="returnpolicy">
	<p class="mianbaoxie"><span class="mianbaoxietitle"></span>???????????????</p>
	<?php if (in_array('paymentmethods',$setitemvalues)){?>
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
	<?php }?>
	<?php if (in_array('return_policy',$setitemvalues)){?>
	<?php echo $this->render('_returnpolicy',array('data'=>$data,'return_policy'=>$returnpolicy))?>
	<?php }?>
	<?php if (in_array('location',$setitemvalues)){?>
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
	<?php }?>
</div>
<!-------------------------------???????????????end ------------------------------------->

<!-------------------------------????????????begin ------------------------------------->
<div class="main-area" id="buyerrequire">
	<p class="mianbaoxie"><span class="mianbaoxietitle"></span>????????????</p>
	<?php if (in_array('buyerrequirementdetails',$setitemvalues)){?>
    <?php echo $this->render('_buyerrequirement',array('data'=>$data,'buyerrequirementenable'=>$buyerrequirementenable))?>
	<?php }?>
</div>
<!-------------------------------????????????end ------------------------------------->


<!-------------------------------????????????begin ------------------------------------->
<div class="main-area" id="plusmodule">
	<p class="mianbaoxie"><span class="mianbaoxietitle"></span>????????????</p>
	<?php if (in_array('gallery',$setitemvalues)){?>
	<p class="title">??????????????????</p>
	<div class="subdiv">
	<?php echo Html::radioList('gallery',@$data['gallery'],array('0'=>'?????????','Featured'=>'Featured($)','Gallery'=>'Gallery($)','Plus'=>'Plus($)'))?>
	</div>
	<?php }?>
	<?php if (in_array('listingenhancement',$setitemvalues)){?>
	<p class="title">??????</p>
	<div class="subdiv">
	<?php echo Html::checkBoxList('listingenhancement',@$data['listingenhancement'],$feature_array)?>
	</div>
	<?php }?>
	<?php if (in_array('hitcounter',$setitemvalues)){?>
	<p class="title">?????????</p>
	<div class="subdiv">
	<?php echo Html::radioList('hitcounter',@$data['hitcounter'],array('NoHitCounter'=>'???????????????','BasicStyle'=>'BasicStyle','RetroStyle'=>'RetroStyle'))?>
	</div>
	<?php }?>
</div>
<!-------------------------------????????????end ------------------------------------->

<!-------------------------------??????begin ------------------------------------->
<div class="main-area" id="account">
	<p class="mianbaoxie"><span class="mianbaoxietitle"></span>??????</p>
	<?php if (in_array('paypal',$setitemvalues)){?>
	<p class="title">Paypal??????<span class="requirefix">*</span></p>
	<input class="iv-input main-input" name="paypal" value="<?=@$data['paypal']?>">
	<?php }?>
	<?php if (in_array('storecategory',$setitemvalues)){?>
	<p class="title">???????????????</p>
	<?php echo Html::textInput('storecategoryid',@$data['storecategoryid'],array('id'=>"storecategoryid",'class'=>'iv-input main-input2'))?>
	<?=Html::button('??????',['onclick'=>'doset("storecategoryid")','class'=>'iv-btn btn-search'])?>
	<p class="title">???????????????</p>
	<?php echo Html::textInput('storecategory2id',@$data['storecategory2id'],array('id'=>"storecategory2id",'class'=>'iv-input main-input2'))?>
	<?=Html::button('??????',['onclick'=>'doset("storecategory2id")','class'=>'iv-btn btn-search'])?>
	<?php }?>
</div>
<!-------------------------------????????????end ------------------------------------->

<!-- ??????????????????  START-->
<div class="btndo">
<input  type="button" value=" ??? ???  " class='donext btn btn-default' onclick='window.close();'>
<input  type="button" class='donext btn btn-success' onclick='toEditStep3()' value=" ??? ???  ">
</div>
<!-- ?????????????????? end -->

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