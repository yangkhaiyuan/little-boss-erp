<?php
use yii\helpers\Html;
use yii\helpers\Url;
use eagle\modules\order\models\OdOrder;
use eagle\modules\order\models\OdOrderShipped;
use eagle\modules\carrier\apihelpers\CarrierApiHelper;
use eagle\modules\inventory\helpers\InventoryApiHelper;
use eagle\modules\util\helpers\TranslateHelper;
use eagle\modules\order\helpers\OrderTagHelper;
use eagle\modules\carrier\helpers\CarrierHelper;
use eagle\modules\tracking\helpers\TrackingHelper;
use eagle\modules\carrier\helpers\CarrierOpenHelper;
use eagle\modules\tracking\models\Tracking;
use eagle\models\carrier\SysShippingService;
use common\api\aliexpressinterface\AliexpressInterface_Helper;

$baseUrl = \Yii::$app->urlManager->baseUrl . '/';
$this->registerCssFile($baseUrl."css/message/customer_message.css");

$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/OrderTag.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/orderOrderList.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/origin_ajaxfileupload.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/message/customer_message.js", ['depends' => ['yii\web\JqueryAsset']]);
$this->registerCssFile(\Yii::getAlias('@web').'/css/order/order.css');
//$this->registerJs("OrderTag.TagClassList=".json_encode($tag_class_list).";" , \yii\web\View::POS_READY);
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/orderCommon.js", ['depends' => ['yii\web\JqueryAsset']]);
if (!empty($_REQUEST['country'])){
    $this->registerJs("OrderCommon.currentNation=".json_encode(array_fill_keys(explode(',', $_REQUEST['country']),true)).";" , \yii\web\View::POS_READY);
}

$this->registerJs("OrderCommon.NationList=".json_encode(@$countrys).";" , \yii\web\View::POS_READY);
$this->registerJs("OrderCommon.NationMapping=".json_encode(@$country_mapping).";" , \yii\web\View::POS_READY);
$this->registerJs("OrderCommon.initNationBox($('div[name=div-select-nation][data-role-id=0]'));" , \yii\web\View::POS_READY);
$this->registerJs("OrderCommon.customCondition=".json_encode(@$counter['custom_condition']).";" , \yii\web\View::POS_READY);
$this->registerJs("OrderCommon.initCustomCondtionSelect();" , \yii\web\View::POS_READY);

?>


    <!------------ ???????????? start  ------------------->
    <?=$this->render('_leftmenu',['counter'=>$counter]);?>
    <!-------------  ???????????? end  ------------------->
<div class="tracking-index col2-layout">
    <div style="height:10px"></div>
    <!-- --------------------------------------------?????? bigin--------------------------------------------------------------- -->
    <div>
        <!-- ???????????? -->
        <form class="form-inline" id="form1" name="form1" action="" method="post">
            <div style="margin:30px 0px 0px 0px">
                <!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
                <?=Html::dropDownList('selleruserid',@$_REQUEST['selleruserid'],$selleruserids,['class'=>'iv-input','id'=>'selleruserid','style'=>'margin:0px','prompt'=>'????????????'])?>
                <!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
                <div class="input-group iv-input" id="selleruserid">
                    <?php $sel = [
                        'order_id'=>'??????????????????',
                        'order_source_order_id'=>'???????????????',
                        'tracking_number'=>'?????????',
                    ]?>
                    <?=Html::dropDownList('keys',@$_REQUEST['keys'],$sel,['class'=>'iv-input','id'=>'keys'])?>
                    <?=Html::textInput('searchval',@$_REQUEST['searchval'],['class'=>'iv-input','id'=>'num'])?>
                </div>

                <!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
                <?=Html::submitButton('??????',['class'=>"iv-btn btn-search btn-spacing-middle",'id'=>'search'])?>
                <?=Html::button('??????',['class'=>"iv-btn btn-search btn-spacing-middle",'onclick'=>"javascript:cleform();"])?>

                <!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
                <?=Html::checkbox('fuzzy',@$_REQUEST['fuzzy'],['label'=>TranslateHelper::t('????????????')])?>

            </div>
        </form>
    </div>
    <!-- --------------------------------------------?????? end--------------------------------------------------------------- -->
    <br><br>
    <!-- --------------------------------------------??????  begin--------------------------------------------------------------- -->
    <div>
        <form name="a" id="a" action="" method="post">
            <div class="pull-left" style="height: 40px;">
                <?php
                    echo Html::button('API?????????????????????',['class'=>"iv-btn btn-important",'id'=>"do-carrier",'value'=>"gettrackingno"]);echo "&nbsp;";
                    echo Html::button('Excel???????????????',['class'=>"iv-btn btn-important",'id'=>"do-import",'value'=>"importtrackingno"]);echo "&nbsp;";
                    echo Html::button('??????????????????',['class'=>"iv-btn btn-important",'id'=>"do-shipped-notrackingnum",'value'=>"signshipped"]);
                ?>
            </div>
            <br>
            <table class="table table-condensed table-bordered" style="font-size:12px;">
                <tr>
                    <th width="1%">
                        <span class="glyphicon glyphicon-minus" onclick="spreadorder(this);"></span><input type="checkbox" check-all="e1" />
                    </th>
                    <th width="8%"><b>???????????????</b></th>
                    <th width="14%"><b>??????????????????</b></th>
                    <th width="16%"><b>?????????-????????????</b></th>
                    <th width="15%"><b>???????????????</b></th>
                    <th width="12%"><b>?????????</b></th>
                    <th width="4%"><b>????????????</b></th>
                    <th width="10%"><b>????????????</b></th>
                    <th width="12%"><b>??????????????????</b></th>
                </tr>
                <?php $carriers=CarrierApiHelper::getShippingServices(false); ?>
                <?php if (count($models)):foreach ($models as $delivery_order):?>
                    <tr style="background-color: #f4f9fc">
                        <td><span class="orderspread glyphicon glyphicon-minus" onclick="spreadorder(this,'<?=$delivery_order['order_id']?>');"></span><input type="checkbox" name="order_id[]" class="order-id"  value="<?=$delivery_order['order_id']?>" data-check="e1"/>
                        </td>
                        <td><?=$delivery_order['order_id']?></td>
                        <td><?=$delivery_order['order_source_order_id']?></td>
                        <td>
                        </td>
                        <td><?=$delivery_order['customer_number']?></td>
                        <td>
                            <input type="text" style="display: none;" name="order_source_order_id[]" id="order_source_order_id" class="orderid-<?=$delivery_order['order_id']?>" value="<?=$delivery_order['order_source_order_id']?>">
                            <?php
                            $tracking_number_arr = OdOrderShipped::find()->select('tracking_number')->where(['order_id'=>$delivery_order['order_id'],'status'=>0])->orderBy('tracking_number DESC,id DESC')->asArray()->one();
                            if (!empty($tracking_number_arr)){
                            	$tracking_number=$tracking_number_arr['tracking_number'];
                            }else{
                            	$tracking_number = '';
                            }
                            ?>
                            <input type="text" name="tracknum[]" id="logisticsNo" class="iv-input orderid-<?=$delivery_order['order_id'] ?> logisticsNo" placeholder="???????????????" value="<?php echo $tracking_number?>">
                        </td>
                        <td>
                            <select id="sendType-<?=$delivery_order['order_id']?>" name="signtype[]" class="iv-input">
                                <option value="" id="operate">-????????????-</option>
                                <option value="all" id="print">????????????</option>
                                <option value="part" id="edit">????????????</option>
                            </select>
                        </td>
                        <td><input type="text"  name="trackurl[]" id="trackingWebsite[]" class="iv-input orderid-<?=$delivery_order['order_id']?>" style="width: 180px;" placeholder="http://www.17track.net"></td>
                        <td>
                            <select id="serviceName-<?=$delivery_order['order_id']?>" name="shipmethod[]" class="iv-input" style="width: 160px;">
                                <option value="" id="operate">-????????????????????????-</option>
                                <?php $serviceName = AliexpressInterface_Helper::getShippingCodeNameMap();
                                foreach($serviceName as $serviceNameKey=>$serviceNameValue){ ?>
                                    <option value="<?=$serviceNameKey?>" id="print"><?=$serviceNameValue?></option>
                                <?php }?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2"><strong>api?????????????????????:</strong></td>
                        <td colspan="3" class="message">
                        </td>
                        <td colspan="4"><input type="text" name="message[]" id="description" class="iv-input orderid-<?=$delivery_order['order_id']?>" style="width: 601px;" placeholder="????????????"></td>
                    </tr>
                <?php endforeach;endif;?>
            </table>
        </form>
        <?php if($pages):?>
            <div id="pager-group">
                <?= \eagle\widgets\SizePager::widget(['pagination'=>$pages , 'pageSizeOptions'=>array( 5 , 20 , 50 , 100 , 200 ) , 'class'=>'btn-group dropup']);?>
                <div class="btn-group" style="width: 49.6%; text-align: right;">
                    <?=\yii\widgets\LinkPager::widget(['pagination' => $pages,'options'=>['class'=>'pagination']]);?>
                </div>
            </div>
        <?php endif;?>
    </div>
    <div style="clear: both;"></div>
    <!-- --------------------------------------------??????  begin--------------------------------------------------------------- -->

</div>

<script>
//??????
function cleform(){
$(':input','#form1').not(':button, :submit, :reset, :hidden').val('').removeAttr('checked').removeAttr('selected');
}

</script>

<script>
window.onload =function()
{

	//excel??????
    $('#do-import').on('click', function () {
        var action = $(this).val();
        if (action == '') {
            return false;
        }
        
        switch (action) {
            case 'importtrackingno':
                var params = $("input").serialize();
//                alert(params);
                $.ajax({
                    type: "post",
                    dataType: 'json',
                    url: '/order/informdelivery/alreadyinformdelivery?shipping_status=1',
                    data: params,
                    success: function (result) {

                    }
                });


                $.ajax({
                    type: "GET",
                    dataType: 'html',
                    url: '/order/informdelivery/importtrackingnumpage',
                    data: {},
                    success: function (result) {
                        bootbox.dialog({
                            title: Translator.t("????????????????????????Excel??????"),
                            className: "importtrackingnumpage",
                            message: result
                        });
                    }
                });
                break;

        }
    });
    
    //????????????????????????????????????api??????????????????
    $('#do-carrier').on('click', function () {
        var allRequests = [];
        var action = $(this).val();
        if (action == '') {
            return false;
        }
        var count = $('.order-id:checked').length;
        if (count == 0) {
            $.alert('???????????????!','success');
            return false;
        }
        switch (action) {
            case 'gettrackingno':

                //??????
                $.maskLayer(true);
                $(".order-id:checked").each(function(){
                    var _this = $(this).parent().parent().next();
                    var _this_logisticsNo = $(this).parent().parent().find('#logisticsNo');
                    $(_this).find('.message').html(" ?????????,????????????????????????");
                    $id = $(this).val(),
                        allRequests.push($.ajax({
                            url: global.baseUrl + "carrier/carrieroperate/gettrackingnoajax",
                            data: {
                                id:$id,
                            },
                            type: 'post',
                            success: function(response) {
                                var result = JSON.parse(response);
                                if (result.error == 1) {
                                    $(_this).find('.message').html(result.msg);

                                } else if (result.error == 0) {
                                    $(_this).attr('result', 'Success');
                                    _this_logisticsNo.val(result.msg);
                                    createSuccessDiv2(obj);
                                }
                            },
                            error: function(XMLHttpRequest, textStatus) {
                                $.maskLayer(false);
                                $.alertBox('<p class="text-warn">???????????????.????????????,?????????!</p>');


                            }
                        }));
                });
                $.when.apply($, allRequests).then(function() {
                    $.maskLayer(false);
                    $.alertBox('<p class="text-success">?????????????????????!</p>');
                });
                break;
            default:
                return false;
                break;
        }
    });



    //??????????????????
    $('#do-shipped-notrackingnum').on('click', function () {
        var action = $(this).val();
        if (action == '') {
            return false;
        }
        var count = $('.order-id:checked').length;
        if (count == 0) {
            $.alert('???????????????!','success');
            return false;
        }
        switch (action) {
            case 'signshipped':

                $('input:checkbox').each(function() {
//                    if ($(this).attr('checked') =='checked') {
                    if ($(this).prop("checked") !=true) {   //???????????????????????????
                        var orderid = 'orderid-'+$(this).val();
                        $('.'+orderid).attr("disabled","disabled"); //?????????????????????
                        $('#sendType-'+$(this).val()).attr("disabled","disabled");  //??????class??????????????????????????????????????????id???????????????????????????
                        $('#serviceName-'+$(this).val()).attr("disabled","disabled"); //??????class??????????????????????????????????????????id???????????????????????????
                        $('#selleruserid').attr("disabled","disabled");
                        $('#keys').attr("disabled","disabled");
                        $('#num').attr("disabled","disabled");

                    }
                });

//                var params = $("form").serialize(); //?????????
//                $.ajax({
//                    type: "post",
//                    dataType: 'json',
//                    url:'/order/informdelivery/signshippedsubmit',
//                    data:params,
//                    success: function (result) {
//                        alert(11);
////                        alert(result.message);
//                    }
//                });

                document.a.target = "_blank";
                document.a.action = "/order/informdelivery/signshippedsubmit";
                document.a.submit();


                $('input:checkbox').each(function()
                {
                    var orderid = 'orderid-'+$(this).val();
                    $('.'+orderid).removeAttr("disabled");
                    $('#sendType-'+$(this).val()).removeAttr("disabled");
                    $('#serviceName-'+$(this).val()).removeAttr("disabled");
                });
                $('#selleruserid').removeAttr("disabled");
                $('#keys').removeAttr("disabled");
                $('#num').removeAttr("disabled");





        }


    });




}
</script>

