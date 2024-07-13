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

$listAllSession = array();
$col_active = 'CURRENT_ACTIVE';
$col_logout = 'FLAG_LOGOUT';
$col_process = 'HAD_PROCESS';

$user = new User();

$jsonResponse = array(
	'success' => false,
	'message' => '',
	'datetime' => date('Y-m-d H:i:s'),
	'data' => null,
	'took' => '0ms',
	'errcode' => 1,
	'errors' => array()
);
$jsonRequest =  json_decode(file_get_contents('php://input'), true);

if($user->isLoggedIn()['success']) {
	$userData = array('stt', 'nik', 'tkn', 'usr');
	foreach($userData as $idx => $perData) {
		$userData[$perData] = $user->getSession($perData);
		$userData[$perData] = ($userData[$perData]['success']) ? $userData[$perData]['data'] : null;
		unset($userData[$idx]);
	}
	$userData = arr2Obj($userData);

	if($userData->tkn != false) {
		$dataSession = $user->hasLoggedIn($userData->tkn, true);
		if($dataSession['success']) {
			// Redirect for User-page
			include DIR_APP.'/includes/check-homepage.inc.php';

			$dataSession = $dataSession['data'];
			$userData->nik = ($userData->nik != null) ? $userData->nik : $dataSession['NIK'];
			$countLogin = $user->countLogins($userData->nik);
			$countLogin = ($countLogin['success']) ? $countLogin['data'] : 0;
			if($countLogin > 1) {
				$isStatusActive = $user->isLoginStatus($userData->tkn, 'active');
				$isStatusActive = ($isStatusActive['success']) ? $isStatusActive['data'] : false;
				if($isStatusActive == false) {
					$listAllSession = $user->getLoginData($userData->tkn, true);
					$listAllSession = ($listAllSession['success']) ? $listAllSession['data'] : array();
					if(!is_null($listAllSession) && is_array($listAllSession)) {
						$sessionYou = $sessionActive = $sessionProcess = $sessionLogout = $sessionOther = array();
						foreach($listAllSession as $i => $sessionItem) {
							if($sessionItem['TOKEN'] == $userData->tkn) $sessionYou += $listAllSession[$i];
							else if($sessionItem[$col_active] == 1) $sessionActive += $listAllSession[$i];
							else if($sessionItem[$col_logout] == 1) $sessionProcess += $listAllSession[$i];
							else if($sessionItem[$col_process] == 1) $sessionLogout += $listAllSession[$i];
							else $sessionOther += $listAllSession[$i];
						}
						$listAllSession = array_values(array_filter(array($sessionYou, $sessionActive, $sessionProcess, $sessionLogout, $sessionOther)));
					} else {
						$user->logout();
						// $dataToast += [ 'message' => 'Terjadi kesalahan saat mengamil semua data sesi, sesi login saat ini akan dikeluarkan silahkan hubungi pihak IT', 'mode' => 'failure', 'expiredon' => strtotime('now') + 5 ];
						// header('location: ' . getURI(2) . '/index.php?toast=' . base64_encode(json_encode($dataToast, JSON_UNESCAPED_SLASHES)));
						header('Location: '.sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
					}
				} else {
					header('Location: '.sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
				}
			} else {
				$isStatusActive = $user->isLoginStatus($userData->tkn, 'active');
				$isStatusActive = ($isStatusActive['success']) ? $isStatusActive['data'] : false;
				if($isStatusActive == false) {
					if($user->setLoginStatus($userData->tkn, 'active', true)['success']) {
						// $dataToast += [ 'message' => 'Sesi aktif login saat ini anda.', 'mode' => 'info', 'expiredon' => strtotime('now') + 5 ];
						// header('location: ' . getURI(2) . '/dashboard.php?toast=' . base64_encode(json_encode($dataToast, JSON_UNESCAPED_SLASHES)));

						// header('Location: '.sprintf('%s/dashboard%s', getURI(2), (EXT_PHP) ? '.php' : ''));
						header('Location: '.sprintf('%s/%s', getURI(2), $userPage));
					} else {
						$user->logout();
						// $dataToast += [ 'message' => 'Terjadi kesalahan saat merubah sesi aktif, sesi login saat ini akan dikeluarkan silahkan hubungi pihak IT', 'mode' => 'failure', 'expiredon' => strtotime('now') + 5 ];
						// header('location: ' . getURI(2) . '/session-list.php?toast=' . base64_encode(json_encode($dataToast, JSON_UNESCAPED_SLASHES)));
						header('Location: '.sprintf('%s/session-list%s', getURI(2), (EXT_PHP) ? '.php' : ''));
					}
				} else {
					// $dataToast += [ 'message' => 'Sesi aktif login saat ini anda.', 'mode' => 'info', 'expiredon' => strtotime('now') + 5 ];
					// header('location: ' . getURI(2) . '/dashboard.php?toast=' . base64_encode(json_encode($dataToast, JSON_UNESCAPED_SLASHES)));

					// header('Location: '.sprintf('%s/dashboard%s', getURI(2), (EXT_PHP) ? '.php':''));
					header('Location: '.sprintf('%s/%s', getURI(2), $userPage));
				}
			}
		} else {
			// $dataToast += [ 'message' => 'Anda bahkan tidak memiliki sesi login, silahkan untuk login terlebih dahulu!', 'mode' => 'failure', 'expiredon' => strtotime('now') + 5 ];
			// header('location: ' . getURI(2) . '/login.php?toast=' . base64_encode(json_encode($dataToast, JSON_UNESCAPED_SLASHES)));
			header('Location: '.sprintf('%s/login%s', getURI(2), (EXT_PHP) ? '.php' : ''));
		}
	}
	if(isset($jsonRequest['ajax']) && ($jsonRequest['ajax'] == 'true' || $jsonRequest['ajax'] == true)) {
		$startTime = floor(microtime(true)*1000);
		header('Content-Type: application/json');
		if(isset($jsonRequest['session_clear']) && ($jsonRequest['session_clear'] == 'true' || $jsonRequest['session_clear'] == true)) {
			if(isset($jsonRequest['session_index']) && isset($jsonRequest['session_identity'])) {
				$idx = (int) $jsonRequest['session_index'];
				$identity = (string) $jsonRequest['session_identity'];
				if(is_int($idx) && is_string($identity)) {
					if(isset($listAllSession) && !is_null($listAllSession)) {
						// Find and set target token
						$targetToken = null;
						if(array_key_exists($idx, $listAllSession)) {
							$checkToken = $listAllSession[$idx]['TOKEN'] ?? null;
							if(substr($checkToken, 0, 5) == substr($identity, 0, 5) && substr($checkToken, -5) == substr($identity, -5)) {
								$targetToken = $checkToken;
							} else {
								foreach($listAllSession as $a => $b) {
									if(substr($b['TOKEN'], 0, 5) == substr($identity, 0, 5) && substr($b['TOKEN'], -5) == substr($identity, -5)) {
										$targetToken = $b['TOKEN'];
										break;
									}
								}
							}
						} else {
							foreach($listAllSession as $a => $b) {
								if(substr($b['TOKEN'], 0, 5) == substr($identity, 0, 5) && substr($b['TOKEN'], -5) == substr($identity, -5)) {
									$targetToken = $b['TOKEN'];
									break;
								}
							}
						}
						// Procced to session clear
						if(!is_null($targetToken) && $user->hasLoggedIn($targetToken)['success']) {
							$isStatusActive = $user->isLoginStatus($targetToken, 'active');
							$isStatusActive = ($isStatusActive['success']) ? $isStatusActive['data'] : false;
							$isStatusProcess = $user->isLoginStatus($targetToken, 'process');
							$isStatusProcess = ($isStatusProcess['success']) ? $isStatusProcess['data'] : false;
							if($isStatusProcess == true) {
								if($user->setLoginStatus($targetToken, 'logout', true)['success'] == true) {
									$jsonResponse['success'] = true;
									$jsonResponse['message'] = 'Sesi akan di hapus, setelah proses aplikasi selesai...';
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									unset($jsonResponse['data']);
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 0;
									unset($jsonResponse['errors']);
									echo json_encode($jsonResponse, JSON_UNESCAPED_SLASHES);
								} else {
									$jsonResponse['success'] = false;
									$jsonResponse['message'] = 'Terjadi kesalahan dalam aplikasi, harap hubungi tim IT...';
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									unset($jsonResponse['data']);
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 9;
									unset($jsonResponse['errors']);
									echo json_encode($jsonResponse, JSON_UNESCAPED_SLASHES);
								}
								exit();
							} else if($isStatusActive == true) {
								if($user->setLoginStatus($targetToken, 'logout', true)['success'] == true) {
									$jsonResponse['success'] = true;
									$jsonResponse['message'] = 'Sesi akan di hapus, setelah pemeriksaan sesi...';
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									unset($jsonResponse['data']);
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 0;
									unset($jsonResponse['errors']);
									echo json_encode($jsonResponse, JSON_UNESCAPED_SLASHES);
								} else {
									$jsonResponse['success'] = false;
									$jsonResponse['message'] = 'Terjadi kesalahan dalam aplikasi, harap hubungi tim IT...';
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									unset($jsonResponse['data']);
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 9;
									unset($jsonResponse['errors']);
									echo json_encode($jsonResponse, JSON_UNESCAPED_SLASHES);
								}
								exit();
						 	} else {
								if($user->closeLogin($targetToken)['success']) {
									$jsonResponse['success'] = true;
									$jsonResponse['message'] = 'Sesi telah di hapus!';
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									unset($jsonResponse['data']);
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 0;
									unset($jsonResponse['errors']);
									echo json_encode($jsonResponse, JSON_UNESCAPED_SLASHES);
								} else {
									$jsonResponse['success'] = false;
									$jsonResponse['message'] = 'Terjadi kesalahan dalam aplikasi, harap hubungi tim IT...';
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									unset($jsonResponse['data']);
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 9;
									unset($jsonResponse['errors']);
									echo json_encode($jsonResponse, JSON_UNESCAPED_SLASHES);
								}
								exit();
							}
						} else {
							$jsonResponse['success'] = false;
							$jsonResponse['message'] = 'Sesi tidak ditemukan!';
							$jsonResponse['datetime'] = date('Y-m-d H:i:s');
							unset($jsonResponse['data']);
							$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode'] = 1;
							unset($jsonResponse['errors']);
							echo json_encode($jsonResponse, JSON_UNESCAPED_SLASHES);
							exit();
						}
					}
				}
			}
		}
		if(isset($jsonRequest['session_take']) && ($jsonRequest['session_take'] == 'true' || $jsonRequest['session_take'] == true)) {
			if(!is_null($userData->tkn) && $user->hasLoggedIn($userData->tkn)['success']) {
				$isStatusActive = $user->isLoginStatus($userData->tkn, 'active');
				$isStatusActive = ($isStatusActive['success']) ? $isStatusActive['data'] : false;
				if($isStatusActive == false) {
					if($user->setLoginStatus($userData->tkn, 'active', true)['success']) {
						// $dataToast += [ 'title' => 'MTP Session', 'message' => 'Sesi aktif login saat ini anda.', 'mode' => 'info', 'expiredon' => strtotime('now') + 5 ];
						// $dataRedirect = getURI(2) . '/dashboard.php?toast=' . base64_encode(json_encode($dataToast));
						// $dataRedirect = sprintf('%s/dashboard%s', getURI(2), (EXT_PHP) ? '.php' : '');
						$dataRedirect = sprintf('%s/%s', getURI(2), $userPage);
						$jsonResponse['success'] = true;
						unset($jsonResponse['message']);
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						unset($jsonResponse['data']);
						$jsonResponse['redirect'] = $dataRedirect;
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 0;
						unset($jsonResponse['errors']);
						echo json_encode($jsonResponse, JSON_UNESCAPED_SLASHES);
					} else {
						// $dataToast += [ 'title' => 'MTP Session', 'message' => 'Terjadi kesalahan dalam aplikasi, harap hubungi tim IT...', 'mode' => 'failure', 'expiredon' => strtotime('now') + 10 ];
						$dataRedirect = sprintf('%s/session-list%s', getURI(2), (EXT_PHP) ? '.php' : '');
						// $dataRedirect = getURI(2) . '/session-list.php?toast=' . base64_encode(json_encode($dataToast));
						$jsonResponse['success'] = false;
						unset($jsonResponse['message']);
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						unset($jsonResponse['data']);
						$jsonResponse['redirect'] = $dataRedirect;
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 1;
						unset($jsonResponse['errors']);
						echo json_encode($jsonResponse, JSON_UNESCAPED_SLASHES);
					}
					exit();
				} else {
					// $dataToast += [ 'title' => 'MTP Session', 'message' => 'Sesi aktif login saat ini anda.', 'mode' => 'info', 'expiredon' => strtotime('now') + 5 ];
					// $dataRedirect = getURI(2) . '/dashboard.php?toast=' . base64_encode(json_encode($dataToast));
					// $dataRedirect = sprintf('%s/dashboard%s', getURI(2), (EXT_PHP) ? '.php' : '');
					$dataRedirect = sprintf('%s/%s', getURI(2), $userPage);
					$jsonResponse['success'] = true;
					unset($jsonResponse['message']);
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					unset($jsonResponse['data']);
					$jsonResponse['redirect'] = $dataRedirect;
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 0;
					unset($jsonResponse['errors']);
					echo json_encode($jsonResponse, JSON_UNESCAPED_SLASHES);
					exit();
				}
			} else {
				$jsonResponse['success'] = false;
				$jsonResponse['message'] = 'Sesi tidak ditemukan!';
				$jsonResponse['datetime'] = date('Y-m-d H:i:s');
				unset($jsonResponse['data']);
				$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
				$jsonResponse['errcode'] = 1;
				unset($jsonResponse['errors']);
				echo json_encode($jsonResponse, JSON_UNESCAPED_SLASHES);
				exit();
			}
		}
		exit();
	}
} else {
	header(sprintf('Location: %s/login', getURI(2)));
}
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?= $APP_CORE['app_name']; ?> | {{ htmlWebpackPlugin.options.title }}</title>
	</head>
	<body class="center-content">
		<div class="container pt-0 px-0 px-lg-5">
			<div class="row bg-white session-container mx-0 mx-xs-3 mx-sm-5 animate__animated animate__zoomIn">
				<div class="col-md-6 session-welcome d-xs-none d-md-block">
					<a href="<?= getURI(2).'/index'; ?>" class="session-logo d-flex align-items-center text-decoration-none">
						<img src="./asset/image/logo/logo-fullwide-colorblack.svg" alt="Logo Company" class="mr-2 me-2"/>
					</a>
					<section class="session-illust my-5"></section>
					<p class="fnt-style1 session-info mt-xs-1 mt-md-3" data-weight="regular">Anda telah masuk di beberapa perangkat yang berbeda...</p>
				</div>
				<div class="col-md-6 session-block align-self-center">
					<section class="my-xs-4 my-md-0 d-none d-xs-block d-md-none text-center">
						<a href="<?= getURI(2).'/index'; ?>" class="session-logo"><img src="./asset/image/logo/logo.png" alt="Logo Company"></a>
					</section>
					<h3 class="fnt-style2 mb-2" data-weight="bold">Anda, masuk diperangkat yang berbeda!</h3>
					<p>Silahkan pilih tindakan, sebelum masuk...</p>
					<ul class="session-devices shadow-scroll pe-3 pr-3 my-3" data-scrollbar>
						<?php
							foreach($listAllSession as $i => $sessionItem) {
								if($sessionItem[$col_logout] == 1) { continue; }
								else if($sessionItem[$col_active] == 1) { $sttTo = 'stt-current'; $youORactive = ' - Aktif'; }
								else if($sessionItem[$col_process] == 1) { $sttTo = 'stt-process'; }
								else { $sttTo = ''; }
								$sttTo = ($sessionItem['TOKEN'] == $userData->tkn) ? 'stt-me' : $sttTo;
								if($sttTo == 'stt-me') { $youORactive = ' - Anda'; }
								$lastActivity = time() - $sessionItem['LAST_ACTIVITY'];
								$lastActivity = ($lastActivity < 60) ? $lastActivity.' detik yang lalu' : date("i", $lastActivity).' menit yang lalu';
								$identity = substr($sessionItem['TOKEN'], 0, 5).'XXXXXXXXXX'.substr($sessionItem['TOKEN'], -5);
						?>
							<li class="device-item <?= $sttTo ?>">
								<div class="icon-session"><img src="./asset/image/icons/cil_fingerprint.svg" class="iconify" /></div>
								<div class="content p-3">
									<h6 class="title fnt-style1" data-weight="semibold">Perangkat <?= $sessionItem['DEVICE_TYPE'] ?><?= $youORactive ?? ''; ?></h6>
									<ul class="detail">
										<li><img src="./asset/image/icons/eva_browser-fill.svg" class="iconify" /> <span data-weight="medium">Browser :</span> <?= $sessionItem['BROWSER_NAME']; ?> <small><b>(<?= $sessionItem['BROWSER_VERSION']; ?>)</b></small></li>
										<li><img src="./asset/image/icons/ph_desktop-tower-fill.svg" class="iconify" /> <span data-weight="medium">Devices :</span> <?= $sessionItem['DEVICE_NAME']; ?>/<?= $sessionItem['DEVICE_OS']; ?>/<?= $sessionItem['DEVICE_TYPE']; ?></li>
										<li><img src="./asset/image/icons/carbon_network-4.svg" class="iconify" /> <span data-weight="medium">IP :</span> <?= $sessionItem['IP_ADDRESS']; ?></li>
										<li><img src="./asset/image/icons/ph_activity-bold.svg" class="iconify" /> <span data-weight="medium">Last activity :</span> <?= $lastActivity ?? '(ERROR_CALCULATE_TIME)'?></li>
										<!-- <li><i class="iconify" data-icon="ic:round-date-range"></i> <span data-weight="medium">Logged in:</span> Kamis, 03 September 2020</li> -->
									</ul>
								</div>
								<div class="clear-session">
									<button class="btn btn-sm btn-block d-block w-100 btn-danger text-white rounded" data-identity="<?= $identity ?>"><i class="iconify" data-icon="fa-solid:sign-out-alt" data-inline="false"></i> Close session</button>
								</div>
							</li>
						<?php
							}
						?>
					</ul>
					<section class="session-actions row no-gutters">
						<button id="sessionsThisUse" class="btn btn-orange fnt-style1 text-white col-12 col-xl-auto px-3 mr-0 mr-xl-3 me-0 me-xl-3 text-white" data-weight="semibold"><i class="iconify mr-1" data-icon="ls:login" data-inline="false"></i> Use current session</button>
						<button class="btn btn-dark fnt-style1 text-white col-12 col-xl mt-2 mt-xl-0" data-weight="semibold"><i class="iconify mr-1" data-icon="fa:refresh"></i> Clear session, and continue</button>
					</section>
				</div>
			</div>
		</div>

		<!-- Hosted app URL -->
		<input id="hostURL" type="hidden" value="<?= getURI(2); ?>"/>
	</body>
</html>
