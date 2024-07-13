<?php
// Required every files to load
$thisPath = (defined('DIR_ROOT') ? DIR_CONFIG : dirname(__FILE__, 3) . '/configs');
require_once($thisPath . '/variables.php');
require_once(DIR_CONFIG . '/functions.php');
require_once(DIR_VENDOR . '/autoload.php');
$appConfig = loadConfig(DIR_CONFIG . '/app-setting.json.php', null, 'json');
extract($appConfig, EXTR_PREFIX_ALL, 'APP');

// Script
use APP\includes\classes\User;
$user = new User();

$userData = array('stt', 'nik', 'tkn', 'usr');
foreach($userData as $idx => $perData) {
	$userData[$perData] = $user->getSession($perData);
	$userData[$perData] = ($userData[$perData]['success']) ? $userData[$perData]['data'] : null;
	unset($userData[$idx]);
}
$userData = arr2Obj($userData);
$userToken = false;
if(isset($userData->tkn) && $userData->tkn != false) {
	$userToken = $userData->tkn;
}
$startTime = floor(microtime(true)*1000);

$triggerLogout = function(bool $ajaxOutput = true) use ($user, $startTime) {
	// global $user, $userSessions;

	/* Disabled because table, not created yet. ask Asphira Andreas for detail
	$userActivity = $user->getActivityData([ 'LOG_UID' => $userSessions->LOG_UID ]);
	$userActivity = (array_key_exists('LOG_FORMAT', $userActivity)) ? ((strtoupper($userActivity['LOG_FORMAT']) == 'JSON') ? json_decode($userActivity['LOG_ACTIVITY'], true) : null) : null;
	if(is_array($userActivity)) $userActivity['Activity'][] = date('YmdHis').' - Has logged out!';
	$user->setActivity([
		'LOG_UID' => $_SESSION['user-data']->LOG_UID,
		'LOG_ACTIVITY' => (string) json_encode([
			'LoggedIn' => (is_array($userActivity)) ? $userActivity['LoggedIn'] : 'INVALID_LOGIN',
			'LoggedOut' => date('Y-m-d H:i:s'),
			'Activity' => (is_array($userActivity)) ? $userActivity['Activity'] : [ date('YmdHis').' - Has logged out!' ]
		]),
		'LOG_TRIGGER' => 'USER_OFFLINE',
		'LOG_FORMAT' => 'JSON'
	], 'update', true);
	*/

	if($user->logout()['success']) {
		// $dataToast = [ 'title' => 'MTP Session', 'message' => 'Anda telah logout dari sesi login!', 'mode' => 'info', 'timeout' => 10, 'class' => '', 'expiredon' => strtotime('now') + 5 ];
		$dataRedirect = sprintf('%s/login%s', getURI(2), (EXT_PHP) ? '.php' : '');
		if($ajaxOutput) {
			$jsonResponse['success'] = true;
			$jsonResponse['message'] = 'Success!';
			$jsonResponse['datetime'] = date('Y-m-d H:i:s');
			unset($jsonResponse['data']);
			$jsonResponse['redirect'] = $dataRedirect;
			$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$jsonResponse['errcode'] = 0;
			unset($jsonResponse['errors']);
			echo json_encode($jsonResponse);
		} else {
			header('Location: '.$dataRedirect);
		}
	}
};

// Logout confirmed, and do action
if(isset($_POST['confirm_logout']) && ($_POST['confirm_logout'] == 'true' || $_POST['confirm_logout'] == true)) {
	if($userToken != false) {
		if(is_string($userToken)) {
			$isStatusProcess = $user->isLoginStatus($userToken, 'process');
			$isStatusProcess = ($isStatusProcess['success']) ? $isStatusProcess['data'] : false;
			if($isStatusProcess) {
				$triggerLogout(true);
			} else {
				$user->setLoginStatus($userToken, 'active', false);
				$user->setLoginStatus($userToken, 'logout', true);
				$triggerLogout(true);
			}
		} else {
			// $dataToast += [ 'message' => 'Nilai Token sesi login tidak valid! (saat proses logout). Hubungi pihak IT', 'mode' => 'failure', 'expiredon' => strtotime('now') + 5 ];
			header('Location: '.sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
		}
	} else {
		$triggerLogout(true);
	}
// Check has process or note, ajax check on modal pop-up
} else if(isset($_POST['ajax']) && ($_POST['ajax'] == 'true' || $_POST['ajax'] == true)) {
	if(isset($_POST['check_process']) && $_POST['check_process'] == 'true' || $_POST['check_process'] == true) {
		if($userToken != false) {
			$isStatusProcess = $user->isLoginStatus($userToken, 'process');
			$isStatusProcess = ($isStatusProcess['success']) ? $isStatusProcess['data'] : false;

			$jsonResponse['success'] = ($isStatusProcess);
			$jsonResponse['message'] = ($isStatusProcess) ? 'In process!' : 'No activity.';
			$jsonResponse['datetime'] = date('Y-m-d H:i:s');
			unset($jsonResponse['data']);
			$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$jsonResponse['errcode'] = ($isStatusProcess);
			unset($jsonResponse['errors']);
			echo json_encode($jsonResponse);
		} else {
			$jsonResponse['success'] = false;
			$jsonResponse['message'] = 'Failed.';
			$jsonResponse['datetime'] = date('Y-m-d H:i:s');
			unset($jsonResponse['data']);
			$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$jsonResponse['errcode'] = 1;
			unset($jsonResponse['errors']);
			echo json_encode($jsonResponse);
		}
	}
} else {
	//$dataToast += [ 'message' => 'Aksi logout tidak bisa dikonfirmasi!', 'mode' => 'warning', 'expiredon' => strtotime('now') + 5 ];
	//header('location: ' . getURI(2) . '/index.php?toast=' . base64_encode(json_encode($dataToast)));
	if($userToken != false) {
		if(is_string($userToken)) {
			$isStatusProcess = $user->isLoginStatus($userToken, 'process');
			$isStatusProcess = ($isStatusProcess['success']) ? $isStatusProcess['data'] : false;

			if($isStatusProcess == false) {
				$triggerLogout(false);
			} else {
				$user->setLoginStatus($userToken, 'active', false);
				$user->setLoginStatus($userToken, 'logout', true);
				$triggerLogout(false);
			}
		} else {
			// $dataToast += [ 'message' => 'Nilai Token sesi login tidak valid! (saat proses logout). Hubungi pihak IT', 'mode' => 'failure', 'expiredon' => strtotime('now') + 5 ];
			// header('Location: ' . getURI(2) . '/index.php?toast=' . base64_encode(json_encode($dataToast)));
			header('Location: '.sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
		}
	} else {
		$triggerLogout(false);
	}
}
