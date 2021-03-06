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
    <!-- --------------------------------------------?????? bigin--------------------------------------------------------------- -->
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
                        'tracknum'=>'?????????',
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
    <br>
    <!-- --------------------------------------------??????  begin--------------------------------------------------------------- -->
    <div>
        <form name="a" id="a" action="" method="post">
            <div class="nav nav-pills">
                <?php echo Html::button(TranslateHelper::t('??????????????????'),['class'=>"iv-btn btn-important",'onclick'=>"javascript:batchstopdelivery();"]);echo "&nbsp;";?>
            </div>
            <br>
            <table class="table table-condensed table-bordered" style="font-size:12px;">
                <tr>
                    <th width="1%">
                        <span class="glyphicon glyphicon-minus" onclick="spreadorder(this);"></span><input type="checkbox" check-all="e1" />
                    </th>
                    <th width="6%"><b>???????????????</b></th>
                    <th width="10%"><b>??????????????????</b></th>
                    <th width="13%"><b>???????????????</b></th>
                    <th width="8%"><b>????????????</b></th>
                    <th width="17%"><b>????????????</b></th>
                    <th width="15%"><b>DESCRIPTION</b></th>
                    <th width="8%"><b>??????</b></th>
                    <th width="10%"><b>????????????</b></th>
                    <th width="12%"><b>??????</b></th>
                </tr>
                <?php if (count($models)):foreach ($models as $delivery_order):?>
                    <tr style="background-color: #f4f9fc">
                        <td><span class="orderspread glyphicon glyphicon-minus" onclick="spreadorder(this,'<?=$delivery_order['order_id']?>');"></span><input type="checkbox" name="order_id[]" class="order-id"  value="<?=$delivery_order['order_id']?>" data-check="e1"/>
                        </td>
                        <td><?=$delivery_order['order_id']?></td>
                        <td><?=$delivery_order['order_source_order_id']?></td>
                        <td><?=$delivery_order['tracking_number']?></td>
                        <td><?=(in_array($delivery_order['signtype'], ['all','part'])?(($delivery_order['signtype'] == 'all' )?TranslateHelper::t('????????????'):TranslateHelper::t('????????????')):TranslateHelper::t('??????'))?></td>
                        <td><?=$delivery_order['shipping_method_name']?></td>
                        <td><?=$delivery_order['description']?></td>
                        <td><?=$delivery_order['status']==0?'?????????':($delivery_order['status']==1?'????????????':'????????????')?></td>
                        <td><??></td>
                        <td>
                            <select id="operateType-<?=$delivery_order['order_id']?>" name="operateType[]" class="iv-input sendType" onchange="doaction(this.value , '<?=$delivery_order['order_id']?>','<?=str_pad($delivery_order['order_id'], 11, "0", STR_PAD_LEFT);?>')">
                                <option value="" id="operate">-????????????-</option>
                                <option value="stop_delivery" id="stop_delivery">??????????????????</option>
                                <option value="order_detail" id="order_detail">????????????</option>
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
    function cleform() {
        $(':input', '#form1').not(':button, :submit, :reset, :hidden').val('').removeAttr('checked').removeAttr('selected');
    }

    //????????????????????????
    function doaction(val, orderid,longorderid) {
        switch (val) {
            case 'stop_delivery': //??????????????????
                $(".operateType option[value='operate']").attr("selected", "selected");
                $.ajax({
                    type: "POST",
                    dataType: 'json',
                    url: '/order/informdelivery/stopdelivery',
                    data: {order_id: orderid},
                    success: function (result) {
                        var event1 = $.confirmBox(result.message);
                        $.maskLayer(true);//??????
                        event1.then(function () {
                            //??????
                            window.location.reload();//??????????????????.
                        }, function () {
                            // ?????????????????????
                            $.maskLayer(false);
                        });

                    }
                });
                break;

            case 'order_detail': //????????????
                $(".operateType option[value='operate']").attr("selected", "selected");
                window.open(global.baseUrl + "order/aliexpressorder/edit?orderid="+longorderid);
                break;

            default:
                return false;
                break;
        }
    }

    //??????????????????
    function batchstopdelivery() {

        var count = $('.order-id:checked').length;
        if (count == 0) {
            $.alert('???????????????!', 'success');
            return false;
        }

        var params = $("form").serialize(); //?????????

        $.ajax({
            type: "POST",
            dataType: 'json',
            url: '/order/informdelivery/stopdelivery',
            data: params,
            success: function (result) {
                var event1 = $.confirmBox(result.message);
                $.maskLayer(true);//??????
                event1.then(function () {
                    //??????
                    window.location.reload();//??????????????????.
                }, function () {
                    // ?????????????????????
                    $.maskLayer(false);
                });

            }
        });
    }


</script>
