<?php
namespace eagle\controllers;

use Yii;
use eagle\models\LoginForm;
use frontend\models\PasswordResetRequestForm;
use frontend\models\ResetPasswordForm;
use frontend\models\SignupForm;
use frontend\models\ContactForm;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\helpers\Url;
use eagle\models\FakeUser;
use eagle\modules\util\helpers\ResultHelper;
use eagle\modules\app\apihelpers\AppApiHelper;
use eagle\models\UserBase;
use eagle\helpers\IndexHelper;
use eagle\modules\util\helpers\TranslateHelper;
use eagle\models\UserToken;
use eagle\models\User;
use eagle\modules\app\helpers\AppHelper;
use eagle\models\LoginInfo;
use eagle\modules\tracking\controllers\TrackingController;
use eagle\assets\AppAsset;
use eagle\modules\app\apihelpers\AppTrackerApiHelper;
use eagle\modules\util\helpers\UserLastActionTimeHelper;

use eagle\modules\permission\helpers\UserHelper;
/**
 * Site controller
 */
//class SiteController extends Controller
class SiteController extends \yii\web\Controller
{
	public $enableCsrfValidation = FALSE; 
	/**
	 * @inheritdoc
	 */
	public function behaviors()
	{
		return [
			'access' => [
				'class' => AccessControl::className(),
				'only' => ['logout', 'signup', 'index'],
				'rules' => [
					[
						'actions' => ['signup'],
						'allow' => true,
						'roles' => ['?'],
					],
					[
						'actions' => ['logout', 'index'],
						'allow' => true,
						'roles' => ['@'],
					],
				],
			],
			'verbs' => [
				'class' => VerbFilter::className(),
				'actions' => [
					'logout' => ['post'],
				],
			],
		];
	}
	
		
	public function actionNotice()
	{
		echo "<div style='margin-left: 50px; margin-top: 50px;  font-size: 30;'></div>";
	} 

	
	public function actionStatus(){
		echo $this->render('statusinfo');
	  //  \Yii::info("loloopen()".print_r(debug_backtrace(),true),"file");
		return;   
	}
	
	/**
	 * @inheritdoc
	 */
	public function actions()
	{
		return [
			'error' => [
				'class' => 'yii\web\ErrorAction',
			],
			'captcha' => [
				'class' => 'yii\captcha\CaptchaAction',
				'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
			],
		];
	}

	public function actionIndex()
	{
		return \Yii::$app->runAction("/dash_board/dash-board/index");
	}

	/**
	 +----------------------------------------------------------
	 * ???????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		dzt		2015/03/10				?????????
	 +----------------------------------------------------------
	 **/
	public function actionVerifyUser() {
		if (empty($_REQUEST['user_name'])) {
			return ResultHelper::getResult(400, '', '?????????????????????');
		}
		if (empty($_REQUEST['password'])) {
			return ResultHelper::getResult(405, '', '??????????????????');
		}
		$username = UserBase::findOne(['user_name'=>$_REQUEST['user_name'] , 'is_active'=> User::STATUS_ACTIVE]);
		if (empty($username)) {
			return ResultHelper::getResult(400, '', '??????????????????????????????');
		}
		
		return ResultHelper::getSuccess(null);
		
	}
	
	/**
	 * ???????????? 
	 * 
	 */	
	public function actionLogin()
	{
		//????????????
		if (!\Yii::$app->user->isGuest) {
			return $this->goHome();
		}	
		
		//????????????
		$model = new LoginForm();
		
		if (count(Yii::$app->request->post())==0) {
			return $this->render('login', ['model' => $model,]);
		}else{
			//????????????
			$postData=Yii::$app->request->post();
			$model->username = $postData["user_name"];
			$model->password = md5($postData["password"]);
			if(!empty($postData["rememberMe"]))
				$model->rememberMe = true;
			else 
			$model->rememberMe = false;
			
			\Yii::info('username,password:'.$model->username.",".$model->password,"file");

			if ($model->login()){
				
				$user = UserBase::findOne(['user_name' => $model->username , 'is_active' => User::STATUS_ACTIVE]);
				if($user == null){
					Yii::$app->user->logout();
					return $this->goHome();
				}
//	 			var_dump($user);exit();
				$user->last_login_date = time();
				$user->last_login_ip = IndexHelper::getClientIP();
				$user->save();

				AppTrackerApiHelper::actionLog("eagle_v2","/eagle_v2/loginsuccess");					
				UserLastActionTimeHelper::saveLastActionTime();
				 
				
				//??????login_info(????????????)
				$logininfodb = new LoginInfo();
				$logininfodb->ip = $user->last_login_ip;
				$logininfodb->userid = $user->uid;
				$logininfodb->username = $user->user_name;
				$logininfodb->memo = 'normal';
				$logininfodb->logintime = $user->last_login_date;
				if(!$logininfodb->save()){
					\Yii::trace("???????????????????????????".print_r($logininfodb->getErrors(),true) , "file");
				}
				//????????????????????????
				UserHelper::insertUserOperationLog('system', "????????????");
				
				return $this->goHome();
//  				return $this->goBack();// ???????????????????????????????????????
				
			}else{// dzt20150311 add else case
				
				Yii::$app->session->setFlash('error', $model->getFirstErrors());
				return $this->render('login', ['model' => $model,]);
			}
			
		}
	}
	
	//??????
	public function actionLogout()
	{
		//????????????????????????
		UserHelper::insertUserOperationLog('system', "????????????");
		
		Yii::$app->user->logout();
		
//		 if (isset(\Yii::$app->params["isOnlyForOneApp"]) and \Yii::$app->params["isOnlyForOneApp"]==1 )
//		 return	$this->redirect(['site/login']);

		return $this->goHome();
	}

	public function actionContact()
	{
		$model = new ContactForm();
		if ($model->load(Yii::$app->request->post()) && $model->validate()) {
			if ($model->sendEmail(Yii::$app->params['adminEmail'])) {
				Yii::$app->session->setFlash('success', 'Thank you for contacting us. We will respond to you as soon as possible.');
			} else {
				Yii::$app->session->setFlash('error', 'There was an error sending email.');
			}

			return $this->refresh();
		} else {
			return $this->render('contact', [
				'model' => $model,
			]);
		}
	}

	public function actionAbout()
	{
		return $this->render('about');
	}

	// ??????yii2.0 ?????????????????????, ?????????
	public function actionSignup()
	{
		$model = new SignupForm();
		if ($model->load(Yii::$app->request->post())) {
			if ($user = $model->signup()) {
				if (Yii::$app->getUser()->login($user)) {
					return $this->goHome();
				}
			}
		}

		return $this->render('signup', [
			'model' => $model,
		]);
	}

	// ??????yii2.0 ???????????????????????????, ?????????
	public function actionRequestPasswordReset2()
	{
		$model = new PasswordResetRequestForm();
		if ($model->load(Yii::$app->request->post()) && $model->validate()) {
			if ($model->sendEmail()) {
				Yii::$app->getSession()->setFlash('success', 'Check your email for further instructions.');

				return $this->goHome();
			} else {
				Yii::$app->getSession()->setFlash('error', 'Sorry, we are unable to reset password for email provided.');
			}
		}

		return $this->render('requestPasswordResetToken', [
			'model' => $model,
		]);
	}

	// ??????yii2.0 ???????????????????????????, ?????????
	public function actionResetPassword2($token)
	{
		try {
			$model = new ResetPasswordForm($token);
		} catch (InvalidParamException $e) {
			throw new BadRequestHttpException($e->getMessage());
		}

		if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->resetPassword()) {
			Yii::$app->getSession()->setFlash('success', 'New password was saved.');

			return $this->goHome();
		}

		return $this->render('resetPassword', [
			'model' => $model,
		]);
	}
	
	
	/**
	 +----------------------------------------------------------
	 * ????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		dzt		2015/01/12				?????????
	 +----------------------------------------------------------
	 **/
	public function actionChangeLanguage(){
		if(!empty($_REQUEST['lan'])){
			setcookie('lan', $_REQUEST['lan'], time() + 3600 );
		}
	}
	
	/**
	 +----------------------------------------------------------
	 * ????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		dzt		2015/03/06				?????????
	 +----------------------------------------------------------
	 **/
	public function actionVerifyEmail() {
		header("Access-Control-Allow-Origin: *");// ????????????????????????
		if (empty($_GET['email'])) {
			return ResultHelper::getResult(400, '', '??????????????????');
		}
		
		$email = trim($_GET['email']);
		\Yii::info("actionVerifyEmail:".$email,"file");
		
		// dzt20170807 ?????????????????? email?????????
		if(stripos($email, '@hotmail.com') !== false || strpos($email, '@qq.com') !== false
		        || strpos($email, '@163.com') !== false || strpos($email, '@sina.com') !== false
		        || strpos($email, '@126.com') !== false || strpos($email, '@gmail.com') !== false
		        || strpos($email, '@foxmail.com') !== false || strpos($email, '@Aliyun.com') !== false
		        || strpos($email, '@outlook.com') !== false || strpos($email, '@sohu.com') !== false
		        || strpos($email, '@yahoo.com') !== false || strpos($email, '@vip.qq.com') !== false
		        )
		{}else
		    return ResultHelper::getResult(400, '', $email.":".TranslateHelper::t('??????????????????????????????????????????????????????????????????????????????'));
		
		    
		
		if(strpos($email, '@139.com') !== false)
		    return ResultHelper::getResult(400, '', TranslateHelper::t('???????????????????????????139.com????????????????????????????????????????????????'));
		
		$count = UserBase::find()->where("`email`='".$email."'")->count();
		if($count > 0) {
			return ResultHelper::getResult(400, '', TranslateHelper::t('?????????????????????????????????'));
		}
		
		$data = IndexHelper::verifyEmail($email);
		if($data != 1){
			return ResultHelper::getResult(400, '', $data);
		}
		
		return ResultHelper::getResult(200, '', '????????????????????????');
	}
	
	/**
	 +----------------------------------------------------------
	 * vip?????? ?????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name			date			note
	 * @author		dzt				2016/07/13		?????????
	 +----------------------------------------------------------
	 **/
	public function actionVerifyPhone(){
		
		$now = time();
		if (empty($_POST['cellphone'])) 
			return ResultHelper::getResult(400, '', '????????????????????????');
		
		// 60?????????
		if(!empty(\Yii::$app->session['verify_phone_interval']) && $now<\Yii::$app->session['verify_phone_interval']+60){
			return ResultHelper::getResult(401, array('timeLeft'=>(\Yii::$app->session['verify_phone_interval']+60-$now),'a'=>\Yii::$app->session['verify_phone_interval']), '??????????????????????????????');
		}
		
		$phoneNumber = $_POST['cellphone'];
		\Yii::info("actionVerifyPhone:".$phoneNumber,"file");
		list($ret,$msg) = IndexHelper::verifyPhone($phoneNumber);
		if($ret){
			\Yii::$app->session['verify_phone_interval'] = time();
			return ResultHelper::getResult(200, '', '????????????????????????');
		}else{
			return ResultHelper::getResult(400, '', $msg);
		}
	}//end of actionPhonecodesend
	
	/**
	 +----------------------------------------------------------
	 * ?????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		dzt		2015/03/06				?????????
	 +----------------------------------------------------------
	 **/
	public function actionVericode() {
		IndexHelper::vericode();
	}
	
	// ????????????token?????????????????????
	public function actionRequestPasswordReset() {
		header("Access-Control-Allow-Origin: *");// ????????????????????????
		$result = '';
		
		if ( count(Yii::$app->request->get()) != 0 ) {
			$get = Yii::$app->request->get();
			if (empty($get['email'])) {
				return ResultHelper::getResult(400, '', '??????????????????');
			}
			
			$user = UserBase::findOne([
				'is_active'=>1,	
				'email'=>$get['email']]
			);
			if($user == null){
				return ResultHelper::getResult(400, '', '???????????????????????????');
			}else {
			    $userTokenObj = UserToken::findOne(['key' => $user->email]);
			    if(empty($userTokenObj)){// dzt20170327 ??????????????????????????????????????????????????????????????????????????????????????????????????????
			        $usertoken = new UserToken();
			        $usertoken->key = $user->email;
			        $usertoken->create_time = time();
			        $usertoken->save();
			    }
			     
			    if (IndexHelper::sendResetTokenLinkToUserMail($get)) {
					return ResultHelper::getResult(200, '', '??????????????????');
				} else {
					return ResultHelper::getResult(400, '', '??????????????????????????????');
				}
			}
		}
		
		return $this->render('requestPasswordResetToken');
	}
	
	// ????????????
	public function actionResetPassword() {
		if( isset(\Yii::$app->params["isOnlyForOneApp"]) and \Yii::$app->params["isOnlyForOneApp"]==1 ){
			define('BASE_URL', '/');
			define('ERP_URL', '');
			$this->layout='main_for_tracker_index';
		}
		
		$token = !empty($_REQUEST['token'])?$_REQUEST['token']:'';
		// ????????????????????????????????????????????????????????????token
		if (empty($token) || !is_string($token)) {
			if( \Yii::$app->request->isAjax ){//\Yii::$app->request->isAjax ???????????????????????????????????????????????????
				return ResultHelper::getResult(400, '', '????????????????????????');
//	 			return ResultHelper::getResult(400, '', 'Password reset token cannot be blank.');
			}else if( isset(\Yii::$app->params["isOnlyForOneApp"]) and \Yii::$app->params["isOnlyForOneApp"]==1 ){
				return $this->render('tracker_resetPassword', ['token'=>$token , 'initError'=>ResultHelper::getResult(400, '', '????????????????????????')]);
			} else
				return $this->render('resetPassword', ['token'=>$token , 'initError'=>ResultHelper::getResult(400, '', '????????????????????????'/*'Password reset token cannot be blank.'*/)]);
		}
		
		$result = IndexHelper::findUserByPasswordResetToken($token);
		$model = $result['data'];
		if ( !$model ) {
			if( \Yii::$app->request->isAjax )
				return json_encode($result);
			else if( isset(\Yii::$app->params["isOnlyForOneApp"]) and \Yii::$app->params["isOnlyForOneApp"]==1 ){
				return $this->render('tracker_resetPassword', ['token'=>$token , 'initError'=>json_encode($result)]);
			}else
				return $this->render('resetPassword', ['token'=>$token , 'initError'=>json_encode($result) ]);
		}

		if( \Yii::$app->request->isAjax ){
//	 		$param = \Yii::$app->request->get();
			$param = $_REQUEST;
			if(strlen($param['password']) < 6)
				return ResultHelper::getResult(400 , '' , TranslateHelper::t('???????????????????????????????????????'));
			if($param['repassword'] != $param['password'])
				return ResultHelper::getResult(400 , '' , TranslateHelper::t('??????????????????????????????'));
			 
			$model->password = md5($param['password']);
			$model->auth_key = Yii::$app->getSecurity()->generateRandomString();
			
			if ($model->save(false)) {
				$token = UserToken::findOne(['find_pass_token'=>$token]);
				$token->find_pass_token = null;
				$token->save(false);
				return ResultHelper::getResult(200 , '' , TranslateHelper::t('?????????????????????'));
				// return $this->goHome();
			}else{
				return ResultHelper::getResult(400 , '' , TranslateHelper::t('?????????????????????????????????'));
			}
		}
		
		if( isset(\Yii::$app->params["isOnlyForOneApp"]) and \Yii::$app->params["isOnlyForOneApp"]==1 ){
			return $this->render('tracker_resetPassword', ['token'=>$token]);
		}
		
		return $this->render('resetPassword', ['token'=>$token]);
	}
	
	// user guide ????????????????????????
	public function actionUserGuide1() {
		return $this->renderAjax('userGuide1', []);
	}
	
	// user guide ?????????????????????????????????????????? ????????????app
	public function actionUserGuide2() {
		$question1 = array();
		$question2 = array();
		foreach ($_POST as $appkey=>$fromQuestion){
			if(1 == $fromQuestion){// ????????????????????????
				$question1[] = $appkey;
			}
			
			if(2 == $fromQuestion){// ????????????????????????
				$question2[] = $appkey;
			}
		}
		
		// ???????????? ??????app
		$advisedAppKeyList = AppApiHelper::getAdvisedAppListFromAnswer(array($question1,$question2));
		
		$advisedAppList = array();
		foreach (AppApiHelper::getAllAppList() as $app){
			if (in_array($app["key"], $advisedAppKeyList)){
				$advisedAppList[] = $app;
			}
		}
		
		foreach($advisedAppList as &$appInfo){
			$appInfo["installed"]='N';
			$appInfo["is_active"]='N';
		}
		return $this->renderAjax('userGuide2', ['allAppList'=>$advisedAppList]);
	}
	
	// ??????app 
	public function actionUserGuide3() {
		
		$allAppList = AppApiHelper::getAllAppList();
		$allKeyList = array();
		foreach($allAppList as $installedAppInfo){
			$allKeyList[] = $installedAppInfo->key;
		}
		
		$readyToInstallAppKeyList = array();
		foreach ($_POST as $appKey=>$val){
			if(in_array($appKey, $allKeyList))
				$readyToInstallAppKeyList[] = $appKey;
		}
		
		$rtn = AppApiHelper::installFromAppKeyList($readyToInstallAppKeyList);
		if($rtn)
			return ResultHelper::getSuccess();
		else 
			return ResultHelper::getFailed();
	}
	
	
	/**
	 +----------------------------------------------------------
	 * ???????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		dzt		2015/03/28				?????????
	 +----------------------------------------------------------
	 **/
	public function actionGetNotice() {
		if(empty($_POST)) ResultHelper::getResult(404, '', '??????');
		$notice = PortalHelper::getNotice($_POST);//????????????
		exit(CJSON::ENCODE($notice));
	}
	
	/**
	 +----------------------------------------------------------
	 * ???????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		dzt		2015/03/28				?????????
	 +----------------------------------------------------------
	 **/
	public function actionError() {		
		if($error = Yii::app()->errorHandler->error) {
			if(Yii::app()->request->isAjaxRequest) {
				exit($error['message']);
			} else {
				$this->renderPartial('error', array(), false, true);
			}
		}
	}

	/**
	 * dev test tool
	 * @author hqf
	 * @version 2016-05-12
	 * @return [type] [description]
	 */
	public function actionDev(){
		return $this->render('//basic/testpage');
	}

	/**
	 +----------------------------------------------------------
	 * ??????Amazon???????????????????????????????????????
	 +----------------------------------------------------------
	 * @access public
	 +----------------------------------------------------------
	 * log			name	date					note
	 * @author		lrq		2017/03/21				?????????
	 +----------------------------------------------------------
	 **/
	public function actionReturnAmazonClientVersion(){
		return "amazon_client_version:1.0,http://v2.littleboss.com/template/AmazonClient_Update.rar";
	}
 
	
	//?????????????????????
	private function  is_mobile() {
	    $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
	    $is_pc = (strpos($agent, 'windows nt')) ? true : false;
	    // $is_mac = (strpos($agent, 'mac os')) ? true : false;
	    $is_iphone = (strpos($agent, 'iphone')) ? true : false;
	    $is_android = (strpos($agent, 'android')) ? true : false;
	    $is_ipad = (strpos($agent, 'ipad')) ? true : false;
	
	
	    if($is_pc){
	        return  false;
	    }
	
	    //    if($is_mac){
	    //         return  true;
	    //  }
	
	    if($is_iphone){
	        return  true;
	    }
	
	    if($is_android){
	        return  true;
	    }
	
	    if($is_ipad){
	        return  true;
	    }
	}
 
	
	/**
	 +----------------------------------------------------------
	 * ???????????????????????????????????????????????????????????????
	 +----------------------------------------------------------
	 * log			name	date			note
	 * @author		lrq		2017/11/15		?????????
	 +----------------------------------------------------------
	 **/
	public function actionReturnScanningPrintClientVersion(){
		return "scanning_print_version:1.2,http://v2.littleboss.com/template/XLBPrint_Update.rar";
	}
	
}
