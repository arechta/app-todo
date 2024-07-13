<?php
// Required every files to load
$thisPath = (defined('DIR_ROOT') ? DIR_CONFIG : dirname(__FILE__, 3) . '/configs');
require_once($thisPath . '/variables.php');
require_once(DIR_CONFIG . '/functions.php');
require_once(DIR_VENDOR . '/autoload.php');
$appConfig = loadConfig(DIR_CONFIG . '/app-setting.json.php', null, 'json');
extract($appConfig, EXTR_PREFIX_ALL, 'APP');
$configMysql = array(
	'mysql_host' => $APP_CORE['db_host'],
	'mysql_username' => $APP_CORE['db_user'],
	'mysql_password' => $APP_CORE['db_pass'],
	'mysql_database' => $APP_CORE['db_name']
);

// Script
use APP\includes\classes\User;

$jsonResponse = array(
	'success' => false,
	'message' => '',
	'datetime' => date('Y-m-d H:i:s'),
	'data' => null,
	'took' => '0ms',
	'errcode' => 1,
	'errors' => array()
);
$user = new User();

$userData = array('stt', 'nik', 'tkn', 'usr');
foreach($userData as $idx => $perData) {
	$userData[$perData] = $user->getSession($perData);
	$userData[$perData] = ($userData[$perData]['success']) ? $userData[$perData]['data'] : null;
	unset($userData[$idx]);
}
$userData = arr2Obj($userData);

if(!isEmptyVar($_POST['ajax']) && ($_POST['ajax'] === 'true' || $_POST['ajax'] === true)) {
	$startTime = floor(microtime(true)*1000);
	$actionType = (isset($_POST['action'])) ? ((!isEmptyVar($_POST['action'])) ? trim($_POST['action']) : false) : false;
	$dataRequest = (isset($_POST['data'])) ? ((!isEmptyVar($_POST['data'])) ? trim($_POST['data']) : null) : null;
	switch($actionType) {
		case 'reset-activity':
			if($userData->tkn != false) {
				$user->setLoggedIn(array('TOKEN' => $userData->tkn, 'LAST_ACTIVITY' => time()), 'edit');
				$jsonResponse['success'] = true;
				$jsonResponse['message'] = 'Successfully!';
				$jsonResponse['datetime'] = date('Y-m-d H:i:s');
				unset($jsonResponse['data']);
				$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
				$jsonResponse['errcode'] = 0;
				unset($jsonResponse['errors']);
			}
		break;
		default: break;
	}
	header('Content-Type: application/json');
	echo json_encode($jsonResponse, JSON_UNESCAPED_SLASHES);
	exit(0);
} else {
	header('Location: '.getURI(2).'/index.php');
}
