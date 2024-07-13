<?php
// Required every files to load
$thisPath = (defined('DIR_ROOT') ? DIR_CONFIG : dirname(__FILE__).'/configs');
require_once($thisPath.'/variables.php');
require_once(DIR_CONFIG.'/functions.php');
require_once(DIR_CONFIG.'/db-handles.php');
require_once(DIR_CONFIG.'/db-queries.php');
require_once(DIR_VENDOR.'/autoload.php');
$appConfig = loadConfig(DIR_CONFIG.'/app-setting.json.php', null, 'json');
extract($appConfig, EXTR_PREFIX_ALL, 'APP');
$configMysql = array(
	'mysql_host' => $APP_CORE['db_host'],
	'mysql_username' => $APP_CORE['db_user'],
	'mysql_password' => $APP_CORE['db_pass'],
	'mysql_database' => $APP_CORE['db_name']
);

// Script
use APP\includes\classes\User;
use APP\includes\classes\EncryptionVW;

// include(DIR_APP.'/includes/check-session.inc.php'); // Check current session of User status logged-in
// include DIR_APP.'/includes/check-homepage.inc.php'; // Check primary user home-page, based on permission
// include(DIR_APP.'/includes/check-authority.inc.php'); // Check permission of current session

// $user = new User();
// $EVW = new EncryptionVW();

// $userData = array('stt', 'nik', 'tkn', 'usr');
// foreach($userData as $idx => $perData) {
// 	$userData[$perData] = $user->getSession($perData);
// 	$userData[$perData] = ($userData[$perData]['success']) ? $userData[$perData]['data'] : null;
// 	unset($userData[$idx]);
// }
// $userData = arr2Obj($userData);
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8"/>
		<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
		<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
		<title><?= $APP_CORE['app_name']; ?> | {{ htmlWebpackPlugin.options.title }}</title>
	</head>
	<body>

	</body>
</html>
