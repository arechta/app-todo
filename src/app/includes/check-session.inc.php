<?php
// Required every files to load
$thisPath = (defined('DIR_ROOT') ? DIR_CONFIG : dirname(__FILE__, 3) . '/configs');
require_once($thisPath . '/variables.php');
require_once(DIR_CONFIG . '/functions.php');
require_once(DIR_VENDOR . '/autoload.php');

// Script
$defaultConfig = array(
	'CORE' => array(
		'session_user' => array(
			'expire_time' => 1800
		)
	)
);
$appConfig = loadConfig(DIR_CONFIG.'/app-setting.json.php', $defaultConfig, 'json');
extract($appConfig, EXTR_PREFIX_ALL, 'APP');
$expireTime = $APP_CORE['session_user']['expire_time'] / 60; // convert seconds -> minutes
$col_active = 'CURRENT_ACTIVE';
$col_logout = 'FLAG_LOGOUT';
$col_process = 'HAD_PROCESS';

use APP\includes\classes\User;
$user = new User();

if ($user->isLoggedIn()['success']) {
	$userData = array('stt', 'nik', 'tkn', 'usr');
	foreach($userData as $idx => $perData) {
		$userData[$perData] = $user->getSession($perData);
		$userData[$perData] = ($userData[$perData]['success']) ? $userData[$perData]['data'] : null;
		unset($userData[$idx]);
	}
	$userData = arr2Obj($userData);
	if ($userData->tkn != false) {
		if ($user->hasLoggedIn($userData->tkn)['success']) {
			// Clear old session {
			$listAllSession = $user->getLoginData($userData->tkn, true);
			$listAllSession = ($listAllSession['success']) ? $listAllSession['data'] : array();
			if (!is_null($listAllSession) && count($listAllSession) > 1) {
				foreach ($listAllSession as $i => $v) {
					if ($v['TOKEN'] != $userData->tkn) {
						// Session that have flagged logout
						//if ($v[$col_logout] == 1 && $v[$col_process] == 0) {
						//	$user->closeSession($v['TOKEN']);
						//}

						// Session that last activity is 1.5 times from expired time
						if ($v['LAST_ACTIVITY'] < (time()-($APP_CORE['session_user']['expire_time']*1.5))) {
							$user->closeLogin($v['TOKEN']);
						}
					}
				}
			}
			// Clear old session }

			if ($user->checkInactivity($userData->tkn)['success']) {
				$isStatusActive = $user->isLoginStatus($userData->tkn, 'active');
				$isStatusActive = ($isStatusActive['success']) ? $isStatusActive['data'] : false;
				$isStatusLogout = $user->isLoginStatus($userData->tkn, 'logout');
				$isStatusLogout = ($isStatusLogout['success']) ? $isStatusLogout['data'] : false;
				$isStatusProcess = $user->isLoginStatus($userData->tkn, 'process');
				$isStatusProcess = ($isStatusProcess['success']) ? $isStatusProcess['data'] : false;

				if ($isStatusActive == false) {
					// $dataToast += [ 'message' => 'Sesi aktif login terambil alih, lihat daftar sesi yang aktif menggunakan akun anda...', 'mode' => 'failure', 'expiredon' => strtotime('now') + 5 ];
					// header('location: ' . getURI(2) . '/session-list'. EXT_PHP .'?toast=' . base64_encode(json_encode($dataToast, JSON_UNESCAPED_SLASHES)));
					header('Location: '.sprintf('%s/session-list%s', getURI(2), (EXT_PHP) ? '.php' : ''));
				}
				if ($isStatusLogout == true && $isStatusProcess == false) {
					if ($user->logout()['success']) {
						$user->closeLogin($userData->tkn);
						// $dataToast += [ 'message' => 'Sesi login anda dihapus, dikarenakan anda telah ditandai <b>TUTUP-SESI</b> oleh sesi anda yang lain...', 'mode' => 'failure', 'expiredon' => strtotime('now') + 5 ];
						// header('location: ' . getURI(2) . '/login'. EXT_PHP .'?toast=' . base64_encode(json_encode($dataToast, JSON_UNESCAPED_SLASHES)));
						header('Location: '.sprintf('%s/login%s', getURI(2), (EXT_PHP) ? '.php' : ''));
						exit();
					}
				}
			} else {
				$isStatusActive = $user->isLoginStatus($userData->tkn, 'active');
				$isStatusActive = ($isStatusActive['success']) ? $isStatusActive['data'] : false;
				$isStatusLogout = $user->isLoginStatus($userData->tkn, 'logout');
				$isStatusLogout = ($isStatusLogout['success']) ? $isStatusLogout['data'] : false;
				$isStatusProcess = $user->isLoginStatus($userData->tkn, 'process');
				$isStatusProcess = ($isStatusProcess['success']) ? $isStatusProcess['data'] : false;
				if ($isStatusProcess == false) {
					if ($user->logout()['success']) {
						$user->closeLogin($userData->tkn);
						// $dataToast += [ 'message' => 'Anda telah logout dari sesi login! <br>(ALASAN: Tidak ada aktifitas selama '.  number_format($expireTime, 1, ',', '') .'-menit)', 'mode' => 'info', 'expiredon' => strtotime('now') + 5 ];
						// header('location: ' . getURI(2) . '/login'. EXT_PHP .'?toast=' . base64_encode(json_encode($dataToast, JSON_UNESCAPED_SLASHES)));
						header('Location: '.sprintf('%s/login%s', getURI(2), (EXT_PHP) ? '.php' : ''));
						exit();
					}
				} else {
					$user->setLoginStatus($userData->tkn, 'active', false);
					$user->setLoginStatus($userData->tkn, 'logout', true);
					if ($user->logout()['success']) {
						// $dataToast += [ 'message' => 'Anda telah logout dari sesi login, proses aplikasi yang berjalan di belakang tidak akan dihentikan sampai proses selesai... <br>(ALASAN: Tidak ada aktifitas selama '.  number_format($expireTime, 1, ',', '') .'-menit)', 'mode' => 'info', 'expiredon' => strtotime('now') + 5 ];
						// header('location: ' . getURI(2) . '/login'. EXT_PHP .'?toast=' . base64_encode(json_encode($dataToast, JSON_UNESCAPED_SLASHES)));
						header('Location: '.sprintf('%s/login%s', getURI(2), (EXT_PHP) ? '.php' : ''));
						exit();
					}
				}
			}
		} else {
			if ((property_exists($userData, 'stt') && $userData->stt == 'on') && property_exists($userData, 'nik')) { $user->logout(); }
			// $dataToast += [ 'message' => 'Anda tidak memiliki sesi login, silahkan untuk login terlebih dahulu!', 'mode' => 'failure', 'expiredon' => strtotime('now') + 5 ];
			// header('location: ' . getURI(2) . '/login'. EXT_PHP .'?toast=' . base64_encode(json_encode($dataToast, JSON_UNESCAPED_SLASHES)));
			header('Location: '.sprintf('%s/login%s', getURI(2), (EXT_PHP) ? '.php' : ''));
		}
	}
} else {
	// $dataToast += [ 'message' => 'Anda belum login, silahkan untuk login terlebih dahulu!', 'mode' => 'failure', 'expiredon' => strtotime('now') + 5 ];
	header('Location: ' . sprintf('%s/login%s', getURI(2), (EXT_PHP) ? '.php' : ''));
	exit();
}
