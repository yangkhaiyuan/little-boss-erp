<?php

namespace console\controllers;

use yii\console\Controller;
use \eagle\modules\listing\helpers\BonanzaHelper;
use \eagle\modules\order\helpers\BonanzaOrderHelper;
use \eagle\modules\util\helpers\SysLogHelper;
use common\api\bonanzainterface\BonanzaInterface_Helper;
use eagle\modules\order\models\BonanzaOrder;
use eagle\modules\listing\helpers\BonanzaOfferSyncHelper;
use eagle\modules\html_catcher\helpers\HtmlCatcherHelper;
use eagle\modules\order\helpers\BonanzaOrderInterface;
use eagle\modules\tracking\helpers\TrackingAgentHelper;
/**
 * Test controller
 */
class BonanzaController extends Controller {
    /**
     * @invoking					./yii bonanza/test
     */
    public function actionTest() {
        $uid=1;
        $userStoreName='@';
        $orderId=[''];
        $createtime= date('Y-m-d\TH:i:s',strtotime("-20 days"));
        $endtime= date('Y-m-d\TH:i:s');
        $status=['Open','Closed'];
        $result_1=BonanzaOrderHelper::getOrderClaimList($uid, $userStoreName,$orderId,$createtime,$endtime,$status);
        $result_2=BonanzaOrderHelper::getOrderQuestionList($uid, $userStoreName,$orderId,$createtime,$endtime,$status);
        echo "\n getOrderClaimList result:\n";
        print_r($result_1);
        echo "\n getOrderQuestionList result:\n";
        print_r($result_2);
        exit();
    }

    /**
     * @invoking					./yii bonanza/test_2
     */
    public function actionTest_2() {
        $uid=1;
        $userStoreName='@';
        $orderId='';
        $result_1=BonanzaOrderHelper::getDiscussionMailList($uid, $userStoreName,$orderId);
        echo "\n GenerateDiscussionMailGuid result:\n";
        print_r($result_1);

        exit();
    }

    /**
     * @invoking					./yii bonanza/test_3
     */
    public function actionTest_3() {
        $SellerProductIdList='';
        $result_1=BonanzaOfferSyncHelper::GetOfferList($SellerProductIdList);
        echo "\n GetOfferList result:\n";
        print_r($result_1);
        exit();
    }
    /**
     * @invoking					./yii bonanza/test_4
     */
    public function actionTest_4() {
        echo "\n start cronGetOfferList \n";
        BonanzaOfferSyncHelper::cronGetOfferList();
    }

    /**
     * @invoking					./yii bonanza/test_5
     */
    public function actionTest_5() {
        echo "\n start AccepteOrRefuseOrders \n";
        $uid = 1;
        $storeName='';
        $orderIds=[];
        $state='Accept';
        $rtn = BonanzaOrderHelper::AccepteOrRefuseOrders($uid, $storeName , $orderIds , $state);
    }

    /**
     * @invoking					./yii bonanza/test_6
     */
    public function actionTest_6() {
        echo "\n start  \n";
        $uid = 1;
        $userStoreName='@';
        $changeProd=['','',''];
        $field_list=array('seller_product_id','img','title','description','brand');
        $site = '';
        $callback = "eagle\modules\listing\helpers\BonanzaOfferSyncHelper::webSiteInfoToDb(@uid=$uid,@prodcutInfo)";
        $rtn = HtmlCatcherHelper::requestCatchHtml($uid,$changeProd,'bonanza',$field_list,$site,$callback);
    }

    /**
     +---------------------------------------------------------------------------------------------
     * Bonanza ?????????????????? job0???
     * ???cron call ??????????????????????????????Bonanza???????????????????????????order
     +---------------------------------------------------------------------------------------------
     * @access static
     +---------------------------------------------------------------------------------------------
     * @param
     +---------------------------------------------------------------------------------------------
     * @return
     *
     * @invoking					./yii bonanza/fetch-recent-order-list-eagle2-job0
     *
     +---------------------------------------------------------------------------------------------
     * log		name	date		note
     * @author	lzhl	2015/7/2	?????????
     +---------------------------------------------------------------------------------------------
     **/
    public function actionFetchRecentOrderListEagle2Job0() {
        $start_time = date('Y-m-d H:i:s');
        $the_day = date('Y-m-d');
        echo "\n cron service runnning for Bonanza cronAutoFetchUnFulfilledOrderList at $start_time";
        $job_id=0;
        $rtn = BonanzaOrderHelper::cronAutoFetchRecentOrderList($job_id,$the_day);
        $command = \Yii::$app->db_queue->createCommand("select * from app_it_dash_board where app='BNOMS' and info_type='runtime-GetOrder' and `addinfo`='job_id=".$job_id."' and `the_day`='".$the_day."' "  );
        $record = $command->queryOne();
        if(!empty($record)){
            $run_times = json_decode($record['addinfo2'],true);
            if(!is_array($run_times))
                $run_times = [];
            $error_message = $record['error_message'];
            if (!$rtn['success']){
                $run_times['end_times'] = empty($run_times['end_times'])?0 : $run_times['end_times'];
                $sql = "update app_it_dash_board set `update_time`='".date("Y-m-d H:i:s",time())."', `addinfo2`='".json_encode($run_times)."',`error_message`='".$error_message.$rtn['message'].' @ '.date("Y-m-d H:i:s",time())."; '  where app='BNOMS' and info_type='runtime-GetOrder' and `addinfo`='job_id=".$job_id."' and `the_day`='".$the_day."' ";
            }else{
                $run_times['end_times'] = empty($run_times['end_times'])?1 : $run_times['end_times']+1;
                $sql = "update app_it_dash_board set `update_time`='".date("Y-m-d H:i:s",time())."', `addinfo2`='".json_encode($run_times)."'  where app='BNOMS' and info_type='runtime-GetOrder' and `addinfo`='job_id=".$job_id."' and `the_day`='".$the_day."'  ";
            }
        }
        $command = \Yii::$app->db_queue->createCommand($sql);
        $affect_record = $command->execute();
        if(empty($affect_record))
            echo "\n set runtime log falied, sql:".$sql;

        //write the memery used into it as well.
        $memUsed = floor (memory_get_usage() / 1024 / 1024);
        $comment =  "\n cron service stops for Bonanza cronAutoFetchUnFulfilledOrderList at ".date('Y-m-d H:i:s');
        $comment .= " - RAM Used: ".$memUsed."M";
        echo $comment;
        TrackingAgentHelper::extCallSum("");
        \Yii::info(['Bonanza',__CLASS__,__FUNCTION__,'Background',$comment],"file");
    }

    /**
     +---------------------------------------------------------------------------------------------
     * Bonanza ?????????????????? job1???
     * ???cron call ??????????????????????????????Bonanza???????????????????????????order
     +---------------------------------------------------------------------------------------------
     * @access static
     +---------------------------------------------------------------------------------------------
     * @param
     +---------------------------------------------------------------------------------------------
     * @return
     *
     * @invoking					./yii bonanza/fetch-recent-order-list-eagle2-job1
     *
     +---------------------------------------------------------------------------------------------
     * log		name	date		note
     * @author	lzhl	2015/7/2	?????????
     +---------------------------------------------------------------------------------------------
     **/
    public function actionFetchRecentOrderListEagle2Job1() {
        $start_time = date('Y-m-d H:i:s');
        $the_day = date('Y-m-d');
        echo "\n cron service runnning for Bonanza cronAutoFetchUnFulfilledOrderList at $start_time";
        $job_id=1;
        $rtn = BonanzaOrderHelper::cronAutoFetchRecentOrderList($job_id,$the_day);

        $command = \Yii::$app->db_queue->createCommand("select * from app_it_dash_board where app='BNOMS' and info_type='runtime-GetOrder' and `addinfo`='job_id=".$job_id."' and `the_day`='".$the_day."' "  );
        $record = $command->queryOne();
        if(!empty($record)){
            $run_times = json_decode($record['addinfo2'],true);
            if(!is_array($run_times))
                $run_times = [];
            $error_message = $record['error_message'];
            if (!$rtn['success']){
                $run_times['end_times'] = empty($run_times['end_times'])?0 : $run_times['end_times'];
                $sql = "update app_it_dash_board set `update_time`='".date("Y-m-d H:i:s",time())."', `addinfo2`='".json_encode($run_times)."',`error_message`='".$error_message.$rtn['message'].' @ '.date("Y-m-d H:i:s",time())."; '  where app='BNOMS' and info_type='runtime-GetOrder' and `addinfo`='job_id=".$job_id."' and `the_day`='".$the_day."' ";
            }else{
                $run_times['end_times'] = empty($run_times['end_times'])?1 : $run_times['end_times']+1;
                $sql = "update app_it_dash_board set `update_time`='".date("Y-m-d H:i:s",time())."', `addinfo2`='".json_encode($run_times)."'  where app='BNOMS' and info_type='runtime-GetOrder' and `addinfo`='job_id=".$job_id."' and `the_day`='".$the_day."'  ";
            }
        }
        $command = \Yii::$app->db_queue->createCommand( $sql );
        $affect_record = $command->execute();
        if(empty($affect_record))
            echo "\n set runtime log falied, sql:".$sql;
         
        //write the memery used into it as well.
        $memUsed = floor (memory_get_usage() / 1024 / 1024);
        $comment =  "\n cron service stops for Bonanza cronAutoFetchUnFulfilledOrderList at ".date('Y-m-d H:i:s');
        $comment .= " - RAM Used: ".$memUsed."M";
        echo $comment;
        TrackingAgentHelper::extCallSum("");
        \Yii::info(['Bonanza',__CLASS__,__FUNCTION__,'Background',$comment],"file");
    }

    /**
     +---------------------------------------------------------------------------------------------
     * Bonanza ?????????????????? job2???
     * ???cron call ??????????????????????????????Bonanza???????????????????????????order
     +---------------------------------------------------------------------------------------------
     * @access static
     +---------------------------------------------------------------------------------------------
     * @param
     +---------------------------------------------------------------------------------------------
     * @return
     *
     * @invoking					./yii bonanza/fetch-recent-order-list-eagle2-job2
     *
     +---------------------------------------------------------------------------------------------
     * log		name	date		note
     * @author	lzhl	2015/7/2	?????????
     +---------------------------------------------------------------------------------------------
     **/
    public function actionFetchRecentOrderListEagle2Job2() {
        $start_time = date('Y-m-d H:i:s');
        $the_day = date('Y-m-d');
        echo "\n cron service runnning for Bonanza cronAutoFetchUnFulfilledOrderList at $start_time";
        $job_id=2;
        $rtn = BonanzaOrderHelper::cronAutoFetchRecentOrderList($job_id,$the_day);

        $command = \Yii::$app->db_queue->createCommand("select * from app_it_dash_board where app='BNOMS' and info_type='runtime-GetOrder' and `addinfo`='job_id=".$job_id."' and `the_day`='".$the_day."' "  );
        $record = $command->queryOne();
        if(!empty($record)){
            $run_times = json_decode($record['addinfo2'],true);
            if(!is_array($run_times))
                $run_times = [];
            $error_message = $record['error_message'];
            if (!$rtn['success']){
                $run_times['end_times'] = empty($run_times['end_times'])?0 : $run_times['end_times'];
                $sql = "update app_it_dash_board set `update_time`='".date("Y-m-d H:i:s",time())."', `addinfo2`='".json_encode($run_times)."',`error_message`='".$error_message.$rtn['message'].' @ '.date("Y-m-d H:i:s",time())."; '  where app='BNOMS' and info_type='runtime-GetOrder' and `addinfo`='job_id=".$job_id."' and `the_day`='".$the_day."' ";
            }else{
                $run_times['end_times'] = empty($run_times['end_times'])?1 : $run_times['end_times']+1;
                $sql = "update app_it_dash_board set `update_time`='".date("Y-m-d H:i:s",time())."', `addinfo2`='".json_encode($run_times)."'  where app='BNOMS' and info_type='runtime-GetOrder' and `addinfo`='job_id=".$job_id."' and `the_day`='".$the_day."'  ";
            }
        }
        $command = \Yii::$app->db_queue->createCommand( $sql );
        $affect_record = $command->execute();
        if(empty($affect_record))
            echo "\n set runtime log falied, sql:".$sql;
         
        //write the memery used into it as well.
        $memUsed = floor (memory_get_usage() / 1024 / 1024);
        $comment =  "\n cron service stops for Bonanza cronAutoFetchUnFulfilledOrderList at ".date('Y-m-d H:i:s');
        $comment .= " - RAM Used: ".$memUsed."M";
        echo $comment;
        \Yii::info(['Bonanza',__CLASS__,__FUNCTION__,'Background',$comment],"file");
        TrackingAgentHelper::extCallSum("");
    }

    /**
     +---------------------------------------------------------------------------------------------
     * Bonanza ????????????(offer)?????????
     +---------------------------------------------------------------------------------------------
     * @invoking					./yii bonanza/get-bonanza-offer-list-eagle2
     +---------------------------------------------------------------------------------------------
     * log		name	date		note
     * @author	lzhl	2015/7/2	?????????
     +---------------------------------------------------------------------------------------------
     **/
    public function actionGetBonanzaOfferListEagle2() {
        //return true;//?????????????????????io???????????????????????????function
        $runtimes=0;
        	
        $rtn = BonanzaOfferSyncHelper::cronGetOfferList();

        //write the memery used into it as well.
        $memUsed = floor (memory_get_usage() / 1024 / 1024);
        $comment =  "\n cron service stops for bonanza GetBonanzaOfferListEagle2 at ".date('Y-m-d H:i:s');
        $comment .= " - RAM Used: ".$memUsed."M";
        echo $comment;
        \Yii::info(['bonanza',__CLASS__,__FUNCTION__,'Background',$comment],"file");
        TrackingAgentHelper::extCallSum("");
    }

    /**
     +---------------------------------------------------------------------------------------------
     * bonanza ???????????????????????????????????????
     * ???cron call ??????????????????????????????bonanza???????????????????????????order
     +---------------------------------------------------------------------------------------------
     * @invoking					./yii bonanza/fetch-new-account-orders-eagle2
     +---------------------------------------------------------------------------------------------
     * log			name	date					note
     * @author		yzq		2015/7/29				?????????
     +---------------------------------------------------------------------------------------------
     **/
    public function actionFetchNewAccountOrdersEagle2() {

        $start_time = date('Y-m-d H:i:s');
        echo "\n cron service runnning for bonanza FetchNewAccountOrders at $start_time";

        $rtn = BonanzaOrderHelper::cronAutoFetchNewAccountOrderList();

        //write the memery used into it as well.
        $memUsed = floor (memory_get_usage() / 1024 / 1024);
        $comment =  "\n cron service stops for bonanza FetchNewAccountOrders at ".date('Y-m-d H:i:s');
        $comment .= " - RAM Used: ".$memUsed."M";
        echo $comment;
        \Yii::info(['bonanza',__CLASS__,__FUNCTION__,'Background',$comment],"file");
        TrackingAgentHelper::extCallSum("");
    }//end of function FetchNewAccountOrdersEagle2

    /**
     * Bonanza ????????????????????????????????????????????????tag???
     * ???cron call ??????????????????????????????Bonanza??????????????????
     * @access static
     * @invoking					./yii bonanza/auto-add-tag-to-bonanza-order-job0
     * @author	lzhl	2015/12/25	?????????
     **/
    public function actionAutoAddTagToBonanzaOrderJob0() {
        $start_time = date('Y-m-d H:i:s');
        echo "\n cron service runnning for Bonanza autoAddTagToBonanzaOrder Job0 at $start_time";
        BonanzaOrderHelper::cronAutoAddTagToBonanzaOrder($job_id=0);
        //write the memery used into it as well.
        $memUsed = floor (memory_get_usage() / 1024 / 1024);
        $comment =  "\n cron service stops for Bonanza autoAddTagToBonanzaOrder Job0 at ".date('Y-m-d H:i:s');
        $comment .= " - RAM Used: ".$memUsed."M";
        echo $comment;
        \Yii::info(['Bonanza',__CLASS__,__FUNCTION__,'Background',$comment],"file");
        TrackingAgentHelper::extCallSum("");
    }//end of function AutoAddTagToBonanzaOrderJob0

    /**
     * Bonanza ????????????????????????????????????????????????tag???
     * ???cron call ??????????????????????????????Bonanza??????????????????
     * @access static
     * @invoking					./yii bonanza/auto-add-tag-to-bonanza-order-job1
     * @author	lzhl	2015/12/25	?????????
     **/
    public function actionAutoAddTagToBonanzaOrderJob1() {
        $start_time = date('Y-m-d H:i:s');
        echo "\n cron service runnning for Bonanza autoAddTagToBonanzaOrder Job1 at $start_time";
        BonanzaOrderHelper::cronAutoAddTagToBonanzaOrder($job_id=1);
        //write the memery used into it as well.
        $memUsed = floor (memory_get_usage() / 1024 / 1024);
        $comment =  "\n cron service stops for Bonanza autoAddTagToBonanzaOrder Job1 at ".date('Y-m-d H:i:s');
        $comment .= " - RAM Used: ".$memUsed."M";
        echo $comment;
        \Yii::info(['Bonanza',__CLASS__,__FUNCTION__,'Background',$comment],"file");
        TrackingAgentHelper::extCallSum("");
    }//end of function AutoAddTagToBonanzaOrderJob1

    /**
     * Bonanza ????????????????????????????????????????????????tag???
     * ???cron call ??????????????????????????????Bonanza??????????????????
     * @access static
     * @invoking					./yii bonanza/auto-add-tag-to-bonanza-order-job2
     * @author	lzhl	2015/12/25	?????????
     **/
    public function actionAutoAddTagToBonanzaOrderJob2() {
        $start_time = date('Y-m-d H:i:s');
        echo "\n cron service runnning for Bonanza autoAddTagToBonanzaOrder Job2 at $start_time";
        BonanzaOrderHelper::cronAutoAddTagToBonanzaOrder($job_id=2);
        //write the memery used into it as well.
        $memUsed = floor (memory_get_usage() / 1024 / 1024);
        $comment =  "\n cron service stops for Bonanza autoAddTagToBonanzaOrder Job2 at ".date('Y-m-d H:i:s');
        $comment .= " - RAM Used: ".$memUsed."M";
        echo $comment;
        \Yii::info(['Bonanza',__CLASS__,__FUNCTION__,'Background',$comment],"file");
        TrackingAgentHelper::extCallSum("");
    }//end of function AutoAddTagToBonanzaOrderJob2

    /**
     * Bonanza ????????????????????????????????????
     * ???cron call ??????????????????????????????Bonanza??????????????????
     * @access static
     * @invoking					./yii bonanza/user-bonanza-order-daily-summary
     * @author	lzhl	2016/01/15	?????????
     **/
    public function actionUserBonanzaOrderDailySummary() {
        $start_time = date('Y-m-d H:i:s');
        echo "\n cron service runnning for Bonanza UserBonanzaOrderDailySummary at $start_time";
        BonanzaOrderInterface::cronBonanzaOrderDailySummary($start_time);
        //write the memery used into it as well.
        $memUsed = floor (memory_get_usage() / 1024 / 1024);
        $comment =  "\n cron service stops for Bonanza UserBonanzaOrderDailySummary at ".date('Y-m-d H:i:s');
        $comment .= " - RAM Used: ".$memUsed."M";
        echo $comment;
        \Yii::info(['Bonanza',__CLASS__,__FUNCTION__,'Background',$comment],"file");
        TrackingAgentHelper::extCallSum("");
    }//end of function AutoAddTagToBonanzaOrderJob2



}