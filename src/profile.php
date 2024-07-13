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
	'mysql_password'=> $APP_CORE['db_pass'],
	'mysql_database' => $APP_CORE['db_name']
);

// Script
use APP\includes\classes\User;
use APP\includes\classes\EncryptionVW;

include(DIR_APP.'/includes/check-session.inc.php'); // Check current session of User status logged-in
include DIR_APP.'/includes/check-homepage.inc.php'; // Check primary user home-page, based on permission
include(DIR_APP.'/includes/check-authority.inc.php'); // Check permission of current session

$user = new User();
$EVW = new EncryptionVW();

$userData = array('stt', 'nik', 'tkn', 'usr');
foreach($userData as $idx => $perData) {
	$userData[$perData] = $user->getSession($perData);
	$userData[$perData] = ($userData[$perData]['success']) ? $userData[$perData]['data'] : null;
	unset($userData[$idx]);
}
$userData = arr2Obj($userData);

/*
 * Check user Account Role, redirect if not met specific requirement
 * and change Page style color based on Account Role 
 */
$authForRole(array('c', 'cs', 'ho'));
$addStyles = '';
switch($userData->usr->role) {
	case 'c': $addStyles = 'role-customer'; break;
	case 'cs': $addStyles = 'role-customer-service'; break;
	case 'ho': $addStyles = 'role-head-office'; break;
}

// Profile selected menu
$viewMenu = 'general';
$availableMenu = array(
	'general' => 'General',
	'edit-profile' => 'Edit Profile',
	'notification' => 'Notifications',
	'accounts' => 'Account Settings',
	'security' => 'Security',
	'sessions' => 'Sessions'
);
if(isset($menu) && is_string($menu)) {
	if(in_array(strtolower(trim($menu)), array_keys($availableMenu))) {
		$viewMenu = strtolower(trim($menu));
	}
}

$homePage = sprintf('%s/%s', getURI(2), $userPage);
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8"/>
		<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
		<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
		<title><?= $APP_CORE['app_name']; ?> | {{ htmlWebpackPlugin.options.title }}</title>
	</head>
	<body class="<?= $addStyles; ?>">
		<div class="container-fluid px-0 h-100">
			<!-- Content pages -->
			<div class="content g-0 no-gutters">
				<div class="content-left animate__animated animate__fadeIn" animate-delay="500">
					<!-- Dynamic Side-navbar -->
					<?= getViews('sidebar-menu.php', array(
						'active' => 'profile',
						'accountPrivileges' => $accountPrivileges,
						'userType' => $userData->usr->role,
						'buildVersion' => $APP_CORE['app_build_version'],
						'userProfile' => $userProfile
					), 'contents'); ?>
				</div>
				<!-- Content body -->
				<div class="content-right">
					<!-- Dynamic Top-navbar -->
					<?= getViews('navbar-top.php', array(
						'configMysql' => $configMysql,
						'appConfig' => $appConfig,
						'userData' => $userData,
						'userProfile' => $userProfile,
						'buildVersion' => $APP_CORE['app_build_version']
					), 'contents'); ?>
					<div class="content-body shadow-scroll" data-scrollbar>
						<div class="row g-3">
							<!-- Content block of User-role('c') -->
							<div class="col-12">
								<div id="userProfile" class="block-item bg-white p-4 rounded">
									<div class="row mb-4">
										<!-- Profile avatar -->
										<div class="col-auto">
											<div class="profile-avatar center-content flex-column">
												<div class="wrapper-avatar"><img src="<?= $userProfile->avatar; ?>" alt="Avatar"/></div>
												<button class="btn btn-change-avatar mt-2">
													<img src="./asset/image/icons/clarity_image-solid.svg" alt="Icons Image" width="13px" class="svg-orange me-1"/>
													Change profile
												</button>
											</div>
										</div>
										<!-- Profile detail -->
										<div class="col">
											<section class="row h-100">
												<!-- Profile block-1 -->
												<div class="profile-block-1 col">
													<section class="row flex-column h-100">
														<div class="col-12 flex-fill">
															<h4 class="profile-name">
																<?= $userProfile->name; ?>
																<?php if(strtolower(trim($userData->usr->role)) === 'c') : ?>
																	<img src="./asset/image/icons/material-symbols_verified.svg" alt="Icons Verified" width="15px" class="svg-blue" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-custom-class="blue-tooltip" data-bs-html="true" data-bs-title="The customer is partners with <b>MTP Logistics</b>"/>
																<?php elseif(strtolower(trim($userData->usr->role)) === 'cs') : ?>
																	<img src="./asset/image/icons/material-symbols_verified.svg" alt="Icons Verified" width="15px" class="svg-orange" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-custom-class="orange-tooltip" data-bs-html="true" data-bs-title="Verified user Customer Service of <b>MTP Logistics</b>"/>
																<?php elseif(strtolower(trim($userData->usr->role)) === 'ho') : ?>
																	<img src="./asset/image/icons/material-symbols_verified.svg" alt="Icons Verified" width="15px" class="svg-primary" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-custom-class="primary-tooltip" data-bs-html="true" data-bs-title="Verified user Head Office of <b>MTP Logistics</b>"/>
																<?php endif; ?>
															</h4>
															<?php if(!isEmptyVar($userProfile->aka) && $userProfile->aka !== '-') : ?>
																<p class="profile-aka">
																	<img src="./asset/image/icons/game-icons_polar-star.svg" alt="Icons Star" width="13px" class="me-1"/>
																	which also known as / <b class="fnt-style4" style="letter-spacing:1px;" data-weight="bold"><?= $userProfile->aka; ?></b> /
																</p>
															<?php endif; ?>
															<p class="profile-position"><?= $userProfile->company->position; ?></p>
														</div>
														<div class="col-12 flex-fill">
															<div class="row gy-1 mt-3">
																<div class="col-auto">
																	<div class="profile-location" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="blue-tooltip" data-bs-title="Live in">
																		<img class="icon me-2" src="./asset/image/icons/mdi_location.svg" alt="Icons Location"/>
																		<p class="value">Indonesia</p>
																	</div>
																</div>
																<?php if(!isEmptyVar($userProfile->company->department) && $userProfile->company->department !== '-') : ?>
																	<div class="col-auto">
																		<div class="profile-department" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="blue-tooltip" data-bs-title="Department">
																			<img class="icon me-2" src="./asset/image/icons/mingcute_department-fill.svg" alt="Icons Department"/>
																			<p class="value"><?= $userProfile->company->department; ?></p>
																		</div>
																	</div>
																<?php endif; ?>
																<?php if(!isEmptyVar($userProfile->phone_number) && $userProfile->phone_number !== '-') : ?>
																	<div class="col-auto">
																		<a class="profile-phone" href="<?= (!isEmptyVar($userProfile->phone_number) && $userProfile->phone_number !== '-') ? 'tel:'.$userProfile->phone_number : '#!'; ?>" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="blue-tooltip" data-bs-title="Phone-number">
																			<img class="icon me-2" src="./asset/image/icons/mdi_phone.svg" alt="Icons Phone"/>
																			<p class="value"><?= $userProfile->phone_number; ?></p>
																		</a>
																	</div>
																<?php endif; ?>
																<?php if(!isEmptyVar($userProfile->email) && $userProfile->email !== '-') : ?>
																	<div class="col-auto">
																		<a class="profile-email" href="<?= (!isEmptyVar($userProfile->email) && $userProfile->email !== '-') ? 'mailto:'.$userProfile->email : '#!'; ?>" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="blue-tooltip" data-bs-title="Email-address">
																			<img class="icon me-2" src="./asset/image/icons/mdi_email.svg" alt="Icons Email"/>
																			<p class="value"><?= $userProfile->email; ?></p>
																		</a>
																	</div>
																<?php endif; ?>
															</div>
														</div>
													</section>
												</div>
												<!-- Profile block-2 -->
												<div class="profile-block-2 col">
													<div class="row flex-column h-100">
														<div class="col-12">
															<div class="row pb-4">
																<div class="col-auto register-date">
																	<h6 class="title">Registered Date</h6>
																	<p class="value"><?= (!isEmptyVar($fetchUser)) ? str_replace('.', ':', strftime('%d %B %Y - %H:%M:%S', strtotime($fetchUser->TGL_DAFTAR))) : '-'; ?></p>
																</div>
																<div class="col-auto last-login">
																	<h6 class="title">Last Login</h6>
																	<p class="value"><?= (!isEmptyVar($userData)) ? str_replace('.', ':', strftime('%d %B %Y - %H:%M:%S', strtotime($userData->usr->login->last))) : '-'; ?></p>
																</div>
																<!-- <div class="col-auto ms-auto">
																	<div class="btn btn-danger btn-logout">Logout</div>
																</div> -->
															</div>
														</div>
														<div class="col-12 flex-fill">
															<div class="profile-relationship">
																<h6 class="title">One-relationship with company</h6>
																<div class="pr-list">
																	<?php
																	$maxShowItem = 5;
																	$listUsers = array();
																	switch(strtolower(trim($userData->usr->role))) {
																		case 'c':
																			// Serve data
																			$listUsers = db_runQuery(array(
																				'config_array' => $configMysql,
																				'database_index' => 0,
																				'input' => array($userData->nik, $userData->nik),
																				'query' => sprintf('SELECT NIK, NAMA, META_AVATAR FROM %s WHERE KODE_STOREKEY = (SELECT KODE_STOREKEY FROM %s WHERE NIK = ?) AND NIK != ?;', $APP_CORE['tb_prefix'].'user_account', $APP_CORE['tb_prefix'].'user_account'),
																				'param' => 'ss',
																				'getData' => true,
																				'getAllRow' => true,
																				'callback' => function($response) use($configMysql, $appConfig) {
																					$output = $response['data'];
																					$result = array(
																						'all_nik' => array(),
																						'all_user' => array() 
																					);
																					if(!isEmptyVar($output) && $output !== 'ZERO_DATA' && count($output) >= 1) {
																						foreach($output as $perUser) {
																							$result['all_nik'][] = $perUser['NIK'];
																							$result['all_user'][$perUser['NIK']] = array(
																								'nik' => $perUser['NIK'],
																								'name' => ucwords(strtolower($perUser['NAMA'])),
																								'avatar' => (!isEmptyVar($perUser['META_AVATAR'])) ? sprintf('%s/files/%s', getURI(2), $perUser['META_AVATAR']) : path2url(sprintf('%s/image/illustrations/avatar-%s.png', DIR_ASSET, $appConfig['CORE']['app_build_version'])),
																								'status' => 'offline',
																								'timeElapsed' => '-',
																								'timeStamp' => 0
																							);
																						}
																						if(count($result['all_nik']) >= 1) {
																							$listUserSession = db_runQuery(array(
																								'config_array' => $configMysql,
																								'database_index' => 0,
																								'input' => $result['all_nik'],
																								'query' => sprintf('SELECT NIK, LAST_ACTIVITY FROM %s WHERE NIK IN (%s);', $appConfig['CORE']['tb_prefix'].'loggedin_device', implode(',', array_fill(0, count($result['all_nik']), '?'))),
																								'param' => str_repeat('s', count($result['all_nik'])),
																								'getData' => true,
																								'getAllRow' => true
																							));
																							if(!isEmptyVar($listUserSession) && $listUserSession !== 'ZERO_DATA' && count($listUserSession) >= 1) {
																								foreach($listUserSession as $perUser2) {
																									if(isset($result['all_user'][$perUser2['NIK']])) {
																										$lastActivity = time() - $perUser2['LAST_ACTIVITY'];
																										if($lastActivity < (5*60)) { // If < 5-minutes user is online
																											$result['all_user'][$perUser2['NIK']]['status'] = 'online';
																											$result['all_user'][$perUser2['NIK']]['timeStamp'] = $lastActivity;
																											unset($result['all_user'][$perUser2['NIK']]['timeElapsed']);
																										} else if($lastActivity > (5*60) && $lastActivity < $appConfig['CORE']['session_user']['expire_time']) { // If > 5-minutes & < [session_expire_time]-minutes user is in idle
																											$result['all_user'][$perUser2['NIK']]['status'] = 'idle';
																											$result['all_user'][$perUser2['NIK']]['timeElapsed'] = rf_timeElapsedString(date('Y-m-d H:i:s', $perUser2['LAST_ACTIVITY']));
																											$result['all_user'][$perUser2['NIK']]['timeStamp'] = $lastActivity;
																										} else {
																											$result['all_user'][$perUser2['NIK']]['status'] = 'offline';
																											$result['all_user'][$perUser2['NIK']]['timeStamp'] = $lastActivity;
																											unset($result['all_user'][$perUser2['NIK']]['timeElapsed']);
																										}
																									}
																								}
																								foreach($result['all_user'] as &$perUser3) {
																									if(array_key_exists('status', $perUser3) && array_key_exists('timeStamp', $perUser3)) {
																										if($perUser3['status'] === 'offline' && $perUser3['timeStamp'] == 0) {
																											unset($perUser3['timeStamp']);
																										}
																									}
																								}
																							}
																						}
																					}
																					return $result['all_user'];
																				}
																			));
																			if(count($listUsers) >= 1) {
																				$_tmpArray = array(
																					'online' => array(),
																					'idle' => array(),
																					'offline' => array()
																				);
																				// Group by status
																				foreach($listUsers as $perUser) {
																					switch($perUser['status']) {
																						case 'online': $_tmpArray['online'][] = $perUser; break;
																						case 'idle': $_tmpArray['idle'][] = $perUser; break;
																						case 'offline': $_tmpArray['offline'][] = $perUser; break;
																						default: break;
																					}
																				}

																				// Clear/resets list responder
																				unset($listUsers);
																				$listUsers = array();

																				// Sort by latest activity
																				foreach($_tmpArray as $statusKey => &$statusVal) {
																					usort($statusVal, function ($item1, $item2) {
																						return (array_key_exists('timeStamp', $item1) && array_key_exists('timeStamp', $item2)) ? $item1['timeStamp'] > $item2['timeStamp'] : false;
																					});
																				}
																				unset($statusVal);

																				// Clear un-used key
																				foreach($_tmpArray as $statusKey => &$statusVal) {
																					switch($statusKey) {
																						case 'online': case 'offline':
																							foreach($statusVal as &$perRelatedUser) {
																								unset($perRelatedUser['timeElapsed']);
																								unset($perRelatedUser['timeStamp']);
																							}
																							unset($perRelatedUser);
																						break;
																						case 'idle':
																							foreach($statusVal as &$perRelatedUser) {
																								unset($perRelatedUser['timeStamp']);
																							}
																							unset($perRelatedUser);
																						break;
																					}
																				}
																				unset($statusVal);

																				// Re-insert again
																				$listUsers = array_merge(array_values($_tmpArray['online']), array_values($_tmpArray['idle']), array_values($_tmpArray['offline']));
																			}
																		break;
																		case 'cs': case 'ho':
																			// Serve data
																			$listUsers = db_runQuery(array(
																				'config_array' => $configMysql,
																				'database_index' => 0,
																				'input' => array('c', $userData->nik),
																				'query' => sprintf('SELECT NIK, NAMA, META_AVATAR FROM %s WHERE NIK IN (SELECT NIK FROM %s WHERE LEVEL_CODE != ? AND NIK != ?) AND (APPROVAL_FLAG != -1 OR APPROVAL_FLAG IS NULL);', $APP_CORE['tb_prefix'].'user_account', $APP_CORE['tb_prefix'].'user_privileges'),
																				'param' => 'ss',
																				'getData' => true,
																				'getAllRow' => true,
																				'callback' => function($response) use($configMysql, $appConfig) {
																					$output = $response['data'];
																					$result = array(
																						'all_nik' => array(),
																						'all_user' => array() 
																					);
																					if(!isEmptyVar($output) && $output !== 'ZERO_DATA' && count($output) >= 1) {
																						foreach($output as $perUser) {
																							$result['all_nik'][] = $perUser['NIK'];
																							$result['all_user'][$perUser['NIK']] = array(
																								'nik' => $perUser['NIK'],
																								'name' => ucwords(strtolower($perUser['NAMA'])),
																								'avatar' => (!isEmptyVar($perUser['META_AVATAR'])) ? sprintf('%s/files/%s', getURI(2), $perUser['META_AVATAR']) : path2url(sprintf('%s/image/illustrations/avatar-%s.png', DIR_ASSET, $appConfig['CORE']['app_build_version'])),
																								'status' => 'offline',
																								'timeElapsed' => '-',
																								'timeStamp' => 0
																							);
																						}
																						if(count($result['all_nik']) >= 1) {
																							$listUserSession = db_runQuery(array(
																								'config_array' => $configMysql,
																								'database_index' => 0,
																								'input' => $result['all_nik'],
																								'query' => sprintf('SELECT NIK, LAST_ACTIVITY FROM %s WHERE NIK IN (%s);', $appConfig['CORE']['tb_prefix'].'loggedin_device', implode(',', array_fill(0, count($result['all_nik']), '?'))),
																								'param' => str_repeat('s', count($result['all_nik'])),
																								'getData' => true,
																								'getAllRow' => true
																							));
																							if(!isEmptyVar($listUserSession) && $listUserSession !== 'ZERO_DATA' && count($listUserSession) >= 1) {
																								foreach($listUserSession as $perUser2) {
																									if(isset($result['all_user'][$perUser2['NIK']])) {
																										$lastActivity = time() - $perUser2['LAST_ACTIVITY'];
																										if($lastActivity < (5*60)) { // If < 5-minutes user is online
																											$result['all_user'][$perUser2['NIK']]['status'] = 'online';
																											$result['all_user'][$perUser2['NIK']]['timeStamp'] = $lastActivity;
																											unset($result['all_user'][$perUser2['NIK']]['timeElapsed']);
																										} else if($lastActivity > (5*60) && $lastActivity < $appConfig['CORE']['session_user']['expire_time']) { // If > 5-minutes & < [session_expire_time]-minutes user is in idle
																											$result['all_user'][$perUser2['NIK']]['status'] = 'idle';
																											$result['all_user'][$perUser2['NIK']]['timeElapsed'] = rf_timeElapsedString(date('Y-m-d H:i:s', $perUser2['LAST_ACTIVITY']));
																											$result['all_user'][$perUser2['NIK']]['timeStamp'] = $lastActivity;
																										} else {
																											$result['all_user'][$perUser2['NIK']]['status'] = 'offline';
																											$result['all_user'][$perUser2['NIK']]['timeStamp'] = $lastActivity;
																											unset($result['all_user'][$perUser2['NIK']]['timeElapsed']);
																										}
																									}
																								}
																								foreach($result['all_user'] as &$perUser3) {
																									if(array_key_exists('status', $perUser3) && array_key_exists('timeStamp', $perUser3)) {
																										if($perUser3['status'] === 'offline' && $perUser3['timeStamp'] == 0) {
																											unset($perUser3['timeStamp']);
																										}
																									}
																								}
																							}
																						}
																					}
																					return $result['all_user'];
																				}
																			));
																			if(count($listUsers) >= 1) {
																				$_tmpArray = array(
																					'online' => array(),
																					'idle' => array(),
																					'offline' => array()
																				);
																				// Group by status
																				foreach($listUsers as $perUser) {
																					switch($perUser['status']) {
																						case 'online': $_tmpArray['online'][] = $perUser; break;
																						case 'idle': $_tmpArray['idle'][] = $perUser; break;
																						case 'offline': $_tmpArray['offline'][] = $perUser; break;
																						default: break;
																					}
																				}

																				// Clear/resets list responder
																				unset($listUsers);
																				$listUsers = array();

																				// Sort by latest activity
																				foreach($_tmpArray as $statusKey => &$statusVal) {
																					usort($statusVal, function ($item1, $item2) {
																						return (array_key_exists('timeStamp', $item1) && array_key_exists('timeStamp', $item2)) ? $item1['timeStamp'] > $item2['timeStamp'] : false;
																					});
																				}
																				unset($statusVal);

																				// Clear un-used key
																				foreach($_tmpArray as $statusKey => &$statusVal) {
																					switch($statusKey) {
																						case 'online': case 'offline':
																							foreach($statusVal as &$perRelatedUser) {
																								unset($perRelatedUser['timeElapsed']);
																								unset($perRelatedUser['timeStamp']);
																							}
																							unset($perRelatedUser);
																						break;
																						case 'idle':
																							foreach($statusVal as &$perRelatedUser) {
																								unset($perRelatedUser['timeStamp']);
																							}
																							unset($perRelatedUser);
																						break;
																					}
																				}
																				unset($statusVal);

																				// Re-insert again
																				$listUsers = array_merge(array_values($_tmpArray['online']), array_values($_tmpArray['idle']), array_values($_tmpArray['offline']));
																			}
																		break;
																	}
																	// Output result
																	if(count($listUsers) >= 1) {
																		foreach($listUsers as $idx => $perRelatedUser) {
																			$contentUserStatus = sprintf('<p class="fnt-style1 d-block p-0 m-0 mt-1 activity-status is-%s" data-weight="regular" style="font-size:10px;">%s</p>', strtolower($perRelatedUser['status']), ucfirst(strtolower($perRelatedUser['status'])));
																			$contentUserActivity = '';
																			if(array_key_exists('timeElapsed', $perRelatedUser)) {
																				$contentUserActivity = sprintf('<p class="fnt-style1 d-block p-0 m-0 mt-1" data-weight="regular" style="font-size:10px;"><img src="%s" class="svg-white mr-1 me-1" style="width:13px;"/> %s</p>', path2url(sprintf('%s/image/icons/fluent_shifts-activity-16-filled-%s.svg', DIR_ASSET, $APP_CORE['app_build_version'])), $perRelatedUser['timeElapsed']);
																			}
																			$contentTitleTooltip = sprintf('<h6 class="fnt-style2 d-block p-0 m-0" data-weight="bold" style="font-size:12px;">%s</h6>%s %s', $perRelatedUser['name'], $contentUserStatus, $contentUserActivity);
																			if(($idx + 1) <= $maxShowItem) {
																				echo sprintf('<div class="pr-item is-%s" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="list-user-relationship" data-bs-html="true" data-bs-title="%s"><img src="%s" alt="Avatar"/></div>', strtolower($perRelatedUser['status']), str_replace('"', '\'', $contentTitleTooltip), $perRelatedUser['avatar']);
																			} else {
																				echo sprintf('<div class="pr-item total-more">+%s</div>', (count($listUsers)-($idx+1)));
																				break;
																			}
																		}
																	}
																	?>
																</div>
																<button type="button" class="pr-more">
																	<img class="icon me-2" src="./asset/image/icons/heroicons_user-group-20-solid.svg" alt="Icons Group"/>See more?
																</button>
															</div>
														</div>
													</div>
												</div>
											</section>
										</div>
									</div>
									<div class="profile-detail">
										<div class="row p-4">
											<div class="col-auto">
												<div class="profile-navbar p-3" style="border-radius: 10px;">	
													<ul class="profile-navbar-nav">
														<?php foreach($availableMenu as $menuKey => $menuVal) : ?>
															<li class="nav-item<?= ($menuKey === $viewMenu) ? ' active' : ''; ?>">
																<a class="nav-item-link" href="<?= sprintf('%s/profile/%s', getURI(2), $menuKey); ?>"><?= $menuVal; ?></a>
															</li>
														<?php endforeach; ?>
													</ul>
												</div>
											</div>
											<div class="col">
												<?= getViews($viewMenu.'.php', array(
													'appConfig' => $appConfig,
													'configMysql' => $configMysql,
													'userData' => $userData,
													'viewMenu' => $viewMenu,
													'accountPrivileges' => $accountPrivileges,
													'userProfile' => $userProfile,
													'buildVersion' => $APP_CORE['app_build_version']
												), 'contents/page/profile'); ?>
											</div>
											<?php if ($userData->usr->role == 'c') : ?>
												<div class="col">
													<div class="row d-flex flex-column p-3 pt-0">
														<div class="col mb-4">
															<h6 class="mb-3">Company Name</h6>
															<p class="m-0 p-0">
															<?php
																if (!isEmptyVar($fetchUser)) {
																	echo $fetchUser->NAMA_PERUSAHAAN;
																} else {
																	echo '-';
																}
															?>
															</p>
														</div>
														<div class="col mb-4">
															<h6 class="mb-3">Company Address</h6>
															<p class="m-0 p-0">
															<?php
																if (!isEmptyVar($fetchUser)) {
																	echo $fetchUser->ALAMAT_PERUSAHAAN;
																} else {
																	echo '-';
																}
															?>
															</p>
															<!-- <a class="gmaps center-content d-inline-block mt-2" href="#!">
																<img class="icon-text me-2" src="./asset/image/icons/map-marked.svg" alt="gmaps" />
																View on Google Maps
															</a> -->
														</div>
														<div class="col mb-4">
															<h6 class="mb-3">Company Logo</h6>
															<?php
																// $logoSource = path2url(sprintf('%s/image/logo/logo-%s.png', DIR_ASSET, $APP_CORE['app_build_version']));
																// if (!isEmptyVar($fetchUser)) {
																// 	$logoSource = sprintf('%s/app/includes/view-document.inc.php?uid=%s', getURI(2), $fetchUser['META_LOGO']);
																// }
															?>
															<img class="logo-company" src="<?= $userProfile->company->logo; ?>" alt="logo" />
														</div>
													</div>
												</div>
												<div class="col">
													<div class="row d-flex flex-column p-3 pt-0">
														<div class="col mb-4">
															<h6 class="mb-3">Product Type</h6>
															<?php
																$productType = array();
																if (!isEmptyVar($fetchUser)) {
																	$productType = (!isEmptyVar($fetchUser->JENIS_PRODUK_PERUSAHAAN)) ? explode(',', $fetchUser->JENIS_PRODUK_PERUSAHAAN) : array();
																}
																if (count($productType) >= 1) {
																	foreach ($productType as $perProduct) {
																		$className = '';
																		switch(strtolower(trim($perProduct))) {
																			case 'dry':
																				$productColor = 'btn-orange text-white';
																				$productIcon = path2url(sprintf('%s/image/icons/material-symbols_cool-to-dry-%s.svg', DIR_ASSET, $APP_CORE['app_build_version']));
																				break;
																			case 'chiller':
																				$productColor = 'btn-secondary text-white';
																				$productIcon = path2url(sprintf('%s/image/icons/ic_baseline-ac-unit-%s.svg', DIR_ASSET, $APP_CORE['app_build_version']));
																			break;
																			case 'frozen':
																				$productColor = 'btn-primary text-white';
																				$productIcon = path2url(sprintf('%s/image/icons/game-icons_frozen-block-%s.svg', DIR_ASSET, $APP_CORE['app_build_version']));
																			break;
																		}
																		echo sprintf('<button type="button" class="btn btn-sm %s rounded px-5 me-2 mb-2" style="pointer-events: none !important;" readonly><img src="%s" alt="" class="svg-white me-2" width="20px" />%s</button>', $productColor, $productIcon, ucfirst(strtolower($perProduct)));
																	}
																}
															?>
														</div>
													</div>
												</div>
											<?php endif; ?>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	
		<!-- Hosted app URL -->
		<input id="hostURL" type="hidden" value="<?= getURI(2); ?>"/>
	</body>
</html>
