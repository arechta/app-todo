<?php
// Required every files to load
$thisPath = (defined('DIR_ROOT') ? DIR_CONFIG : dirname(__FILE__, 4).'/configs');
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
	'mysql_password'=> $APP_CORE['db_pass'],
	'mysql_database' => $APP_CORE['db_name']
);

// Script
if (isAjax() || count($_POST) >= 1 || array_key_exists('HTTP_POSTMAN_TOKEN', $_SERVER) || (array_key_exists('CONTENT_TYPE', $_SERVER) && wordExist($_SERVER['CONTENT_TYPE'], 'multipart')) || wordExist($_SERVER['REQUEST_URI'], 'api')) {
	$jsonResponse = array(
		'success' => false,
		'message' => 'Page not found!',
		'datetime' => date('Y-m-d H:i:s'),
		'data' => null,
		'took' => '0ms',
		'errcode' => 404,
		'errors' => array()
	);
	http_response_code(404);
	header('Content-Type: application/json');
	echo json_encode($jsonResponse, JSON_UNESCAPED_SLASHES);
	exit(0);
}
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>404</title>
	</head>
	<body>
		<section class="error-body">
			<video preload="auto" class="background" src="./asset/video/error.mp4" autoplay muted loop></video>
			<div class="message">
				<h1 t="404">404</h1>
				<div class="bottom mt-3">
					<p class="mb-2">Page not found!</p>
					<a href="<?= getURI(2); ?>">BACK</a>
				</div>
			</div>
		</section>
	</body>
</html>
