<?php
use yii\helpers\Html;
use yii\helpers\Url;
use eagle\modules\order\models\OdOrder;
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
$this->registerJsFile(\Yii::getAlias('@web')."/js/project/order/aliexpressOrder/aliexpressOrder.js", ['depends' => ['yii\web\JqueryAsset']]);
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

    <!-- --------------------------------------------?????? begin--------------------------------------------------------------- -->
    <div>
        <!-- ???????????? -->
        <form class="form-inline" id="form1" name="form1" action="" method="post">
            <div style="margin:30px 0px 0px 0px">
                <!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
                <?=Html::dropDownList('selleruserid',@$_REQUEST['selleruserid'],$selleruserids,['class'=>'iv-input','id'=>'selleruserid','style'=>'margin:0px','prompt'=>'????????????'])?>
                <!----------------------------------------------------------- ???????????? ----------------------------------------------------------->
                <div class="input-group iv-input">
                    <?php $sel = [
                        'order_id'=>'??????????????????',
                        'order_source_order_id'=>'???????????????',
                        'tracking_number'=>'?????????',
                    ]?>
                    <?=Html::dropDownList('keys',@$_REQUEST['keys'],$sel,['class'=>'iv-input'])?>
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
                
                	
                    echo Html::button(TranslateHelper::t('API?????????????????????'),['class'=>"iv-btn btn-important",'id'=>"do-carrier",'value'=>"gettrackingno"]);echo "&nbsp;";
                    echo Html::button(TranslateHelper::t('Excel???????????????'),['class'=>"iv-btn btn-important",'id'=>"do-import",'value'=>"importtrackingno"]);echo "&nbsp;";
                    echo Html::button(TranslateHelper::t('??????????????????'),['class'=>"iv-btn btn-important",'id'=>"do-shipment",'value'=>"signshipped",'onclick'=>"aliexpressOrder.sellerShipment('ship');"]);echo "&nbsp;";
                    //echo Html::button(TranslateHelper::t('??????????????????'),['class'=>"iv-btn btn-important",'id'=>"alter-shipment",'value'=>"sellerModifiedShipment",'onclick'=>"aliexpressOrder.sellerShipment('modify');"]);
                ?>
            </div>
            <br>
            <table class="table table-condensed table-bordered" style="font-size:12px;">
                <tr>
                    <th width="1%">
                        <span class="glyphicon glyphicon-minus" onclick="spreadorder(this);"></span><input type="checkbox" check-all="e1" />
                    </th>
                    <th width="4%"><b>???????????????</b></th>
                    <th width="15%"><b>??????????????????</b></th>
                    <th width="15%"><b>DESCRIPTION</b></th>
                    <th width="10%"><b>?????????tracking website</b></th>
                    <th width="10%"><b>????????????????????????</b></th>
                    <th width="4%"><b>?????????????????????</b></th>
                    <th width="10%"><b>?????????????????????</b></th>
                    <th width="10%"><b>??????</b></th>
                </tr>
                <?php if (count($models)):foreach ($models as $delivery_order):?>
                    <tr style="background-color: #f4f9fc">
                        <td><span class="orderspread glyphicon glyphicon-minus" onclick="spreadorder(this,'<?=$delivery_order['order_id']?>');"></span><input type="checkbox" name="order_id[]" class="order-id"  value="<?=$delivery_order['order_id']?>" data-check="e1"/>
                        </td>
                        <td><?=$delivery_order['order_id']?></td>
                        <td class="order_source_order_id"><?=$delivery_order['order_source_order_id']?></td>
                        <td><?=$delivery_order['description']?></td>
                        <td><?=$delivery_order['tracking_link']?></td>
                        <td class="old_tracking_number"><?=$delivery_order['tracking_number']?></td>
                        <td><?=$delivery_order['signtype']?></td>
                        <td>
                            <input type="hidden" class="oldServiceName" value="<?=$delivery_order['shipping_method_code']?>">
                            <?=$delivery_order['shipping_method_name']?>
                        </td>
                        <td></td>

                    </tr>
                    <tr>
                        <td colspan="3">
                            api?????????????????????:<span class="api_message"></span>
                            <br>
                            ??????????????????:<span class="shipment_message"></span>
<!--                                ???????????????--><?//=$delivery_order['result']?><!--<br>-->
<!--                                ???????????????--><?//=$delivery_order['errors']?>
                        </td>
                        <td><input type="text" name="message[]" id="description" class="iv-input orderid-<?=$delivery_order['order_id']?> description"  placeholder="????????????"></td>
                        <td><input type="text"  name="trackurl[]" id="trackingWebsite[]" class="iv-input orderid-<?=$delivery_order['order_id']?> trackingWebsite"  placeholder="http://www.17track.net"></td>
                        <td><input type="text" name="tracknum[]" id="logisticsNo" class="iv-input orderid-<?=$delivery_order['order_id']?> logisticsNo" placeholder="???????????????" value="<?php
                            if(!empty($order_ids)) {
                                foreach ($order_ids as $order_id) {
                                    if ($delivery_order['order_id'] == $order_id) {
                                        foreach ($excel_data as $excel) {
                                            if ($excel['orderid'] == ((int)$order_id)) {
                                                echo $excel['tracknum'];
                                            }
                                        }
                                    }
                                }
                            }
                            ?>"></td>
                        <td>
                            <select id="sendType-<?=$delivery_order['order_id']?>" name="signtype[]" class="iv-input sendType">
                                <option value="" id="operate">-????????????-</option>
                                <option value="all" id="print">????????????</option>
                                <option value="part" id="edit">????????????</option>
                            </select>
                        </td>
                        <td>
                            <select id="serviceName-<?=$delivery_order['order_id']?>" name="shipmethod[]" class="iv-input serviceName" style="width: 160px;">
                                <option value="" id="operate">-????????????????????????-</option>
                                <?php $serviceName = AliexpressInterface_Helper::getShippingCodeNameMap();
                                foreach($serviceName as $serviceNameKey=>$serviceNameValue){ ?>
                                    <option value="<?=$serviceNameKey?>" id="print"><?=$serviceNameValue?></option>
                                <?php }?>
                            </select>
                        </td>
                        <td>
                            <select id="operateType-<?=$delivery_order['order_id']?>" name="signtype[]" class="iv-input operateType" onchange="doaction(this ,'<?=$delivery_order['order_id']?>','<?=str_pad($delivery_order['order_id'], 11, "0", STR_PAD_LEFT);?>','<?=$delivery_order['order_source_order_id']?>')">
                            <option value="operate" id="operate">-????????????-</option>
                                <option value="order_detail" id="order_detail">????????????</option>
                                <option value="restart_delivery" id="restart_delivery">??????????????????</option>
                                <!--  <option value="update_delivery" id="update_delivery">??????????????????</option>-->
                            </select>
                        </td>
                    </tr>
                <?php endforeach;endif;?>
            </table>
        </form>
        <?php if($pages):?>
            <div id="pager-group">
                <?= \eagle\widgets\SizePager::widget(['pagination'=>$pages , 'pageSizeOptions'=>array( 5 , 20 , 50 , 100 , 200 , 500 ) , 'class'=>'btn-group dropup']);?>
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

    function doaction(obj,orderid,longorderid,order_source_order_id)
    {
        var val = $(obj).val();
        switch(val)
        {
            case 'order_detail': //????????????
                $(".operateType option[value='operate']").attr("selected", "selected");
                window.open(global.baseUrl + "order/aliexpressorder/edit?orderid="+longorderid);
                break;

            case 'restart_delivery': //??????????????????
                $(".operateType option[value='operate']").attr("selected", "selected");
                var description = $(obj).parents("tr").find(".orderid-"+orderid+":eq(0)").val();
                var tracking_website = $(obj).parents("tr").find(".orderid-"+orderid+":eq(1)").val();
                var tracking_number = $(obj).parents("tr").find(".orderid-"+orderid+":eq(2)").val();
                var sendType = $("#sendType-"+orderid).val();
                var serviceName = $("#serviceName-"+orderid).val();

                $.ajax({
                    type: "post",
                    dataType: 'json',
                    url: '/order/informdelivery/sellershipment',
                    data: {orderid:orderid,order_source_order_id:order_source_order_id,description:description,tracking_website:tracking_website,tracking_number:tracking_number,sendType:sendType,serviceName:serviceName},
                    success: function (result) {
                        bootbox.alert(result.message);
                    }
                });
                break;

            case'update_delivery': //??????????????????
                $(".operateType option[value='operate']").attr("selected", "selected");
                var description = $(obj).parents("tr").find(".orderid-"+orderid+":eq(0)").val();
                var tracking_website = $(obj).parents("tr").find(".orderid-"+orderid+":eq(1)").val();
                var sendType = $("#sendType-"+orderid).val();
                var old_tracking_number = $(obj).parents("tr").find(".orderid-"+orderid+":eq(2)").val();
                var old_serviceName = $("#serviceName-"+orderid).val();
                var new_tracking_number = $(obj).parents("tr").prev().find("td:eq(5)").text();
                var new_serviceName = $(obj).parents("tr").prev().find("td:eq(6)").text();

                $.ajax({
                    type: "post",
                    dataType: 'json',
                    url: '/order/informdelivery/sellermodifiedshipment',
                    data: {orderid:orderid,order_source_order_id:order_source_order_id,description:description,tracking_website:tracking_website,old_tracking_number:old_tracking_number,sendType:sendType,old_serviceName:old_serviceName,new_tracking_number:new_tracking_number,new_serviceName:new_serviceName},
                    success: function (result) {
                        bootbox.alert(result.message);
                    }
                });

                break;

            default:
                return false;
                break;
        }
    }


</script>

<script>
window.onload =function() {
    //????????????????????????????????????api??????????????????
    $('#do-carrier').on('click', function () {
        var allRequests = [];
        var action = $(this).val();
        if (action == '') {
            return false;
        }
        var count = $('.order-id:checked').length;
        if (count == 0) {
            $.alert('???????????????!', 'success');
            return false;
        }
        switch (action) {
            case 'gettrackingno':

                //??????
                $.maskLayer(true);
                $(".order-id:checked").each(function(){
                    var _this = $(this).parent().parent().next();
                    var _this_logisticsNo = $(this).parent().parent().find('#logisticsNo');
                    $(_this).find('.api_message').html(" ?????????,????????????????????????");
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
                                    $(_this).find('.api_message').html(result.msg);

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


//                document.a.target = "_blank";
//                document.a.action = global.baseUrl + "carrier/carrieroperate/gettrackingno";
//                document.a.submit();
//                document.a.action = "";
                break;
            default:
                return false;
                break;
        }
    })

    //excel??????
    $('#do-import').on('click', function () {
        var action = $(this).val();
        if (action == '') {
            return false;
        }
        var count = $('.order-id:checked').length;
        if (count == 0) {
            $.alert('???????????????!', 'success');
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
    })


    //??????????????????
    
    /*
    $('#do-shipment').on('click', function () {
        var allRequests = [];
        var action = $(this).val();
        if (action == '') {
            return false;
        }
        var count = $('.order-id:checked').length;
        if (count == 0) {
            $.alert('???????????????!', 'success');
            return false;
        }
        switch (action) {
            case 'signshipped':
			debugger;
                //??????
                $.maskLayer(true);
                $(".order-id:checked").each(function(){
                    var _this = $(this).parent().parent().next();
//                    var _this_logisticsNo = $(this).parent().parent().find('#logisticsNo');

                    $(_this).find('.shipment_message').html(" ?????????,????????????????????????");
                    $orderid = $(this).val(),
                    $order_source_order_id = $(this).parent().parent().find('.order_source_order_id').html(),
                    $description = _this.find(".description").val(),
                    $tracking_website = _this.find(".trackingWebsite").val(),
                    $tracking_number = _this.find(".logisticsNo").val(),
                    $sendType = _this.find("#sendType-"+$orderid).val(),
                    $serviceName = _this.find("#serviceName-"+$orderid).val(),

                    allRequests.push($.ajax({
                        url: global.baseUrl + "/order/informdelivery/signshippedsubmit",
                        data: {
                            orderid:$orderid,
                            order_source_order_id:$order_source_order_id,
                            description:$description,
                            tracking_website:$tracking_website,
                            tracking_number:$tracking_number,
                            sendType:$sendType,
                            serviceName:$serviceName,
                        },
                        type: 'post',
                        dataType:"json",
                        success: function(result) {
//                                debugger
//                                var result = JSON.parse(response);
                            if (result.error == 1) {
                                $(_this).find('.shipment_message').html(result.message);

                            } else if (result.error == 0) {
                                $(_this).attr('result', 'Success');
                                $(_this).find('.shipment_message').html(result.message);
//                                    _this_logisticsNo.val(result.msg);
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
	*/
/*
    //????????????????????????
    $('#alter-shipment').on('click', function () {
        var allRequests = [];
        var action = $(this).val();
        if (action == '') {
            return false;
        }
        var count = $('.order-id:checked').length;
        if (count == 0) {
            $.alert('???????????????!', 'success');
            return false;
        }
        switch (action) {
            case 'sellerModifiedShipment':
                //??????
                $.maskLayer(true);
                $(".order-id:checked").each(function(){
                    var _this = $(this).parent().parent().next();
//                    var _this_logisticsNo = $(this).parent().parent().find('#logisticsNo');

                    $(_this).find('.shipment_message').html(" ?????????,????????????????????????");
                        $orderid = $(this).val(),
                        $order_source_order_id = $(this).parent().parent().find('.order_source_order_id').html(),
                        $old_tracking_number = $(this).parent().parent().find('.old_tracking_number').html(),
                        $oldServiceName = $(this).parent().parent().find('.oldServiceName').val(),
                        $description = _this.find(".description").val(),
                        $tracking_website = _this.find(".trackingWebsite").val(),
                        $tracking_number = _this.find(".logisticsNo").val(),
                        $sendType = _this.find("#sendType-"+$orderid).val(),
                        $serviceName = _this.find("#serviceName-"+$orderid).val(),

                        allRequests.push($.ajax({
                            url: global.baseUrl + "order/informdelivery/sellermodifiedshipment",
                            data: {
                                orderid:$orderid,
                                order_source_order_id:$order_source_order_id,
                                description:$description,
                                tracking_website:$tracking_website,
                                new_tracking_number:$tracking_number,
                                sendType:$sendType,
                                new_serviceName:$serviceName,
                                old_tracking_number:$old_tracking_number,
                                old_serviceName:$oldServiceName,
                            },
                            type: 'post',
                            dataType:"json",
                            success: function(result) {
//                                debugger
//                                var result = JSON.parse(response);
                                if (result.error == 1) {
                                    $(_this).find('.shipment_message').html(result.message);

                                } else if (result.error == 0) {
                                    $(_this).attr('result', 'Success');
                                    $(_this).find('.shipment_message').html(result.message);
//                                    _this_logisticsNo.val(result.msg);
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

        }
    })
    */
}
</script>