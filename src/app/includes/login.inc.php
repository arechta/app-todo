<?php
// Required every files to load
$thisPath = (defined('DIR_ROOT') ? DIR_CONFIG : dirname(__FILE__, 3).'/configs');
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
use APP\includes\classes\Blackbox;

$jsonResponse = array(
	'success' => false,
	'message' => '',
	'datetime' => date('Y-m-d H:i:s'),
	'data' => null,
	'took' => '0ms',
	'errcode' => 1,
	'errors' => array()
);
$user = null;
$EVW = new EncryptionVW();
$blackbox = new Blackbox();

if(!isEmptyVar($_POST['ajax']) && ($_POST['ajax'] === 'true' || $_POST['ajax'] === true)) {
	$actionType = (!isEmptyVar($_POST['action'])) ? trim($_POST['action']) : false;
	switch($actionType) {
		case 'login-account':
			$startTime = floor(microtime(true)*1000);
			$nik = (array_key_exists('account-id', $_POST)) ? ((!isEmptyVar($_POST['account-id'])) ? $_POST['account-id'] : null) : null;
			$password = (array_key_exists('account-password', $_POST)) ? ((!isEmptyVar($_POST['account-password'])) ? $_POST['account-password'] : null) : null;

			// Check if it's Encrypted Content
			if(!isEmptyVar($nik) && strlen($nik) >= 30) {
				$nik = $EVW->decrypt($nik, $APP_CORE['app_private_key']['app']['value']);
			}
			if(!isEmptyVar($password) && strlen($password) >= 30) {
				$password = $EVW->decrypt($password, $APP_CORE['app_private_key']['app']['value']);
			}

			// Validating user input
			if(!isEmptyVar($nik) && !isEmptyVar($password)) {
				// Masuk akun
				$user = new User(array('NIK' => $nik, 'PASS' => $password));
				// Login and set the listed data, into session 'user-data' as associative array
				$doLogin = $user->login('NIK, NAMA, JABATAN, ATASAN, NO_HP, NO_WA, EMAIL, TGL_LOGIN, JML_LOGIN, KODE_OTP, KODE_DC, FLAG, NAMA_PERUSAHAAN, FLAG, APPROVAL_FLAG, APPROVAL_FLAG_CODE');
				if($doLogin['success']) {
					$userData = arr2Obj($doLogin['data']['session']);
					$lastLogin = (!is_null($userData->usr->login->last) && !empty($userData->usr->login->last)) ? '<br>(Terakhir login: '.strftime("%A, %d %B %Y", strtotime($userData->usr->login->last)).')':'';

					// Redirect for User-page
					include DIR_APP.'/includes/check-homepage.inc.php';

					// Login Welome Message
					// $dataToast += [ 'message' => "Login berhasil, selamat datang $userData->NAMA" . $lastLogin, 'mode' => 'succeed', 'expiredon' => strtotime('now') + 10 ];
					// $loginWelcome = base64_encode(json_encode($dataToast, JSON_UNESCAPED_SLASHES));
					// $_SESSION[$APP_CORE['session_prefix'] . 'user-wm'] = true;
					// $user->registerSessionKey($APP_CORE['session_prefix'] . 'user-wm');

					/* Disabled because table, not created yet. ask Asphira Andreas for detail
					$_SESSION[$APP_CORE['session_prefix'] . 'user-data']->LOG_UID = $user->setActivity([
						'LOG_ACTIVITY' => (string) json_encode([
							'LoggedIn' => date('Y-m-d H:i:s'),
							'LoggedOut' => 'INVALID_LOGOUT',
							'Activity' => [
								date('YmdHis') . " - Has logged in."
							]
						]),
						'LOG_TARGET' => $userNIK,
						'LOG_TRIGGER' => 'USER_ONLINE',
						'LOG_FORMAT' => 'JSON'
					], 'add', true);
					*/

					// // Blackbox
					// $blackbox->create('activity', $userNIK, array(
					// 	// "actionMethod" => "update",
					// 	"actionTrigger" => "USER_LOGIN",
					// 	"actionLink" => sprintf('%s/login%s', getURI(2), (EXT_PHP) ? '.php' : ''),
					// 	"dateTime" => date('Y-m-d H:i:s'),
					// 	"dataContent" => array(
					// 		"user" => array(
					// 			"id" => $userNIK,
					// 			"token" => $userToken,
					// 		),
					// 		"description" => "User is Logged-in!"
					// 	),
					// ));

					// Redirect to Dashboard/Session-list
					if($user->countLogins($userData->nik)['success'] >= 2) {
						if($userData->tkn != false) {
							$checkLoginStatus = $user->isLoginStatus($userData->tkn, 'active');
							$checkLoginStatus = ($checkLoginStatus['success']) ? $checkLoginStatus['data'] : false;
							if($checkLoginStatus) {
								$jsonResponse['success'] = true;
								unset($jsonResponse['message']);
								$jsonResponse['datetime'] = date('Y-m-d H:i:s');
								// $jsonResponse['data'] = $EVW->encrypt(sprintf('%s/dashboard%s', getURI(2), (EXT_PHP) ? '.php' : ''), $APP_CORE['app_private_key']['app']['value']);
								$jsonResponse['data'] = $EVW->encrypt(sprintf('%s/%s', getURI(2), $userPage), $APP_CORE['app_private_key']['app']['value']);
								$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
								$jsonResponse['errcode'] = 0;
								unset($jsonResponse['errors']);
							} else {
								$jsonResponse['success'] = true;
								unset($jsonResponse['message']);
								$jsonResponse['datetime'] = date('Y-m-d H:i:s');
								$jsonResponse['data'] = $EVW->encrypt(sprintf('%s/session-list%s', getURI(2), (EXT_PHP) ? '.php' : ''), $APP_CORE['app_private_key']['app']['value']);
								$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
								$jsonResponse['errcode'] = 0;
								unset($jsonResponse['errors']);
							}
						}
					} else {
						$jsonResponse['success'] = true;
						unset($jsonResponse['message']);
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						// $jsonResponse['data'] = $EVW->encrypt(sprintf('%s/dashboard%s', getURI(2), (EXT_PHP) ? '.php' : ''), $APP_CORE['app_private_key']['app']['value']);
						$jsonResponse['data'] = $EVW->encrypt(sprintf('%s/%s', getURI(2), $userPage), $APP_CORE['app_private_key']['app']['value']);
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 0;
						unset($jsonResponse['errors']);
					}
				} else {
					$jsonResponse['success'] = false;
					$jsonResponse['message'] = $doLogin['message'];
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					unset($jsonResponse['data']);
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 3;
					unset($jsonResponse['errors']);
				}
			} else {
				$jsonResponse['success'] = false;
				$jsonResponse['message'] = "Input should not be empty, please fill in!";
				$jsonResponse['datetime'] = date('Y-m-d H:i:s');
				unset($jsonResponse['data']);
				$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
				$jsonResponse['errcode'] = 2;
				unset($jsonResponse['errors']);
			}
		break;
		case 'fetch-account';
			$fetchAccount = db_runQuery(array(
				'config_array' => $configMysql,
				'database_index' => 0,
				'input' => $dataSearch,
				'query' => sprintf('SELECT NAMA, NIK, TGL_LOGIN FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'] . 'user_account'),
				'param' => 's',
				'getData' => true,
				'callback' => function($response) {
					$output = $response['data'];
					if(!isEmptyVar($output) && $output !== 'ZERO_DATA') {
						foreach($output as $key => &$val) {
							if($key === 'NAMA') {
								$val = strtoupper(trim($val));
							}
							if($key === 'TGL_LOGIN') {
								$val = strftime('%A, %d %B %Y', strtotime($val));
							}
						}
					} else {
						$output = null;
					}
					return $output;
				}
			));
			if(!isEmptyVar($fetchAccount)) {
				$jsonResponse['success'] = true;
				$jsonResponse['data'] = $fetchAccount;
				$jsonResponse['message'] = 'Data user ditemukan!';
				$jsonResponse['errcode'] = 0;
			} else {
				$jsonResponse['success'] = false;
				unset($jsonResponse['data']);
				$jsonResponse['message'] = 'Data user tidak ditemukan.';
				$jsonResponse['errcode'] = 1;
			}
		break;
		case 'confirm-welcome':
			$_SESSION[$APP_CORE['session_prefix'] . 'user-wm'] = false;
			$jsonResponse['success'] = true;
			unset($jsonResponse['data']);
			unset($jsonResponse['message']);
			$jsonResponse['errcode'] = 0;
		break;
	}
	header('Content-Type: application/json');
	echo json_encode($jsonResponse, JSON_UNESCAPED_SLASHES);
	exit(0);
} else {
	// $dataToast += [ 'message' => 'Hmmm? what are u doing with that?', 'mode' => 'failure', 'expiredon' => strtotime('now') + 5 ];
	header('location: ' . sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
	exit(1);
}
