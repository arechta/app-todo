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
use APP\includes\classes\Notification;
use APP\includes\classes\EncryptionVW;
use Ramsey\Uuid\Uuid; // ramsey/uuid : Library for creating Universally Unique Identifier alias UUID

if(session_status() === PHP_SESSION_NONE) { session_start(array('cookie_lifetime' => 0, 'cookie_path' => '/', 'cookie_httponly' => '1')); }
$jsonResponse = array(
	'success' => false,
	'message' => '',
	'datetime' => date('Y-m-d H:i:s'),
	'data' => null,
	'took' => '0ms',
	'errcode' => 1,
	'errors' => array()
);
$apiConfigWhatsapp = $APP_API['message']['provider_whatsapp'];
$user = new User();
$notif = new Notification();
$EVW = new EncryptionVW();

$userData = array('stt', 'nik', 'tkn', 'usr');
foreach($userData as $idx => $perData) {
	$userData[$perData] = $user->getSession($perData);
	$userData[$perData] = ($userData[$perData]['success']) ? $userData[$perData]['data'] : null;
	unset($userData[$idx]);
}
$userData = arr2Obj($userData);

if(!isEmptyVar($_POST['ajax']) && ($_POST['ajax'] === 'true' || $_POST['ajax'] === true)) {
	$startTime = floor(microtime(true)*1000);
	$actionType = (isset($_POST['action'])) ? ((!isEmptyVar($_POST['action'])) ? trim($_POST['action']) : null) : null;
	$methodType = (isset($_POST['method'])) ? ((!isEmptyVar($_POST['method'])) ? trim($_POST['method']) : null) : null;
	$dataRequest = (isset($_POST['data'])) ? ((!isEmptyVar($_POST['data'])) ? trim($_POST['data']) : null) : null;
	switch($actionType) {
		case 'fetch-notifications':
			/*
			 * Stage: 1
			 * Check if the user is online and has a NIK
			 */
			if(!isEmptyVar($userData->nik)) {
				/*
				 * Stage: 2
				 * Check if the account is registered with the current NIK
				 */
				$isExist = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik),
					'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) as TOTAL FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
					'param' => 's',
					'getData' => true,
					'getAllRow' => false
				));

				if(isset($isExist['TOTAL']) && $isExist['TOTAL'] >= 1) {
					$listNotifications = $notif->get('*', array('NIK' => $userData->nik), true);

					if(!isEmptyVar($listNotifications) && $listNotifications['success'] == true) {
						$jsonResponse['success'] = true;
						$jsonResponse['message'] = sprintf('Data found (%s) entries!', count($listNotifications['data']));
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						$jsonResponse['data'] = $listNotifications['data'];
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 0;
						unset($jsonResponse['errors']);
					} else {
						$jsonResponse['success'] = false;
						$jsonResponse['message'] = 'Data records empty!';
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						$jsonResponse['data'] = array();
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 1;
						unset($jsonResponse['errors']);
					}
				} else {
					$jsonResponse['success'] = false;
					$jsonResponse['message'] = 'You are not part of the Account scope!';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					unset($jsonResponse['data']);
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 3;
					unset($jsonResponse['errors']);
				}
			} else {
				$jsonResponse['success'] = false;
				$jsonResponse['message'] = 'You\'re not logged-in!';
				$jsonResponse['datetime'] = date('Y-m-d H:i:s');
				unset($jsonResponse['data']);
				$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
				$jsonResponse['errcode'] = 2;
				unset($jsonResponse['errors']);
			}
		break;
		case 'forgot-account':
			/*
			 * Stage: 1
			 * Select method to proceed
			 */
			switch($methodType) {
				case 'status-progress':
					if(array_key_exists(sprintf('%sforgot-account', $APP_CORE['session_prefix']), $_SESSION)) {
						// Data found
						$jsonResponse['success'] = true;
						$jsonResponse['message'] = "Status found!";
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						$jsonResponse['data'] = array(
							'step' => $_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]['stepProgress'],
							'pass' => $_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]['isPassed'],
							'redirect' => sprintf('%s/login%s', getURI(2), (EXT_PHP) ? '.php' : ''),
						);
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 0;
						unset($jsonResponse['errors']);
					} else {
						// Data not found
						$jsonResponse['success'] = false;
						$jsonResponse['message'] = 'Status not found!';
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						unset($jsonResponse['data']);
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 1;
						unset($jsonResponse['errors']);
					}
				break;
				case 'reset-progress':
					if(array_key_exists(sprintf('%sforgot-account', $APP_CORE['session_prefix']), $_SESSION)) {
						unset($_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]);

						// Data found
						$jsonResponse['success'] = true;
						$jsonResponse['message'] = 'Status reset!';
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						unset($jsonResponse['data']);
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 0;
						unset($jsonResponse['errors']);
					} else {
						// Data not found
						$jsonResponse['success'] = false;
						$jsonResponse['message'] = 'Status empty.';
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						unset($jsonResponse['data']);
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 1;
						unset($jsonResponse['errors']);
					}
				break;
				case 'enter-credentials':
					$data = null;
					if(!isEmptyVar($dataRequest) && strlen($dataRequest) >= 50) {
						$data = $EVW->decrypt($dataRequest, $APP_CORE['app_private_key']['app']['value']);
					}
					if(!isEmptyVar($data) && isJSON($data)) {
						$data = json_decode($data, true);
						if(is_array($data) && isAssoc($data)) {
							if(array_key_exists('account-id', $data) && !isEmptyVar($data['account-id'])) {
								/*
								 * Stage: 2
								 * Check if the account is registered with the provieded NIK
								 */
								$isExist = db_runQuery(array(
									'config_array' => $configMysql,
									'database_index' => 0,
									'input' => array($data['account-id'], $data['account-id']),
									'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) as TOTAL, NIK, JABATAN, NAMA, NAMA_PERUSAHAAN, EMAIL, EMAIL_PERUSAHAAN, NO_WA, NO_WA_PERUSAHAAN FROM %s WHERE NIK = ? OR EMAIL = ?;', $APP_CORE['tb_prefix'].'user_account'),
									'param' => 'ss',
									'getData' => true,
									'getAllRow' => false
								));

								if(isset($isExist['TOTAL']) && $isExist['TOTAL'] >= 1) {
									$_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])] = array(
										'accountData' => array(
											'id' => $isExist['NIK'],
											'name' => (strtolower(trim($isExist['JABATAN'])) === 'customer') ? $isExist['NAMA_PERUSAHAAN'] : $isExist['NAMA'],
											'emailAddress' => (strtolower(trim($isExist['JABATAN'])) === 'customer') ? $isExist['EMAIL_PERUSAHAAN'] : $isExist['EMAIL'],
											'phoneNumber' => (strtolower(trim($isExist['JABATAN'])) === 'customer') ? $isExist['NO_WA_PERUSAHAAN'] : $isExist['NO_WA'],
										),
										'stepProgress' => 2, // Go to next step
										'confirmMethod' => null,
										'confirmVerification' => null,
										'isPassed' => false
									);

									// Data found
									$jsonResponse['success'] = true;
									$jsonResponse['message'] = 'Account found!';
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									unset($jsonResponse['data']);
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 0;
									unset($jsonResponse['errors']);
								} else {
									// Data not found on Database
									$jsonResponse['success'] = false;
									$jsonResponse['message'] = 'No account found with that ID.';
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									unset($jsonResponse['data']);
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 6;
									unset($jsonResponse['errors']);
								}
							} else {
								// Data is empty or not met specific minimum requirements
								$jsonResponse['success'] = false;
								$jsonResponse['message'] = 'The following data (account-id) is empty or not met minimum requirement!';
								$jsonResponse['datetime'] = date('Y-m-d H:i:s');
								unset($jsonResponse['data']);
								$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
								$jsonResponse['errcode'] = 5;
								unset($jsonResponse['errors']);
							}
						} else {
							$jsonResponse['success'] = false;
							$jsonResponse['message'] = 'Data is a bad JSON format';
							$jsonResponse['datetime'] = date('Y-m-d H:i:s');
							unset($jsonResponse['data']);
							$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode'] = 4;
							unset($jsonResponse['errors']);
						}
					} else {
						$jsonResponse['success'] = false;
						$jsonResponse['message'] = 'Data is empty or not a JSON format!';
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						unset($jsonResponse['data']);
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 3;
						unset($jsonResponse['errors']);
					}
				break;
				case 'confirm-account':
					$data = null;
					if(!isEmptyVar($dataRequest) && strlen($dataRequest) >= 50) {
						$data = $EVW->decrypt($dataRequest, $APP_CORE['app_private_key']['app']['value']);
					}
					if(!isEmptyVar($data) && isJSON($data)) {
						$data = json_decode($data, true);
						if(is_array($data) && isAssoc($data)) {
							if(array_key_exists('confirm-method', $data) && !isEmptyVar($data['confirm-method'])) {
								switch (trim($data['confirm-method'])) {
									case 'send-code':
										if(array_key_exists('verification-code', $data) && !isEmptyVar($data['verification-code'])) {
											if(array_key_exists(sprintf('%sforgot-account', $APP_CORE['session_prefix']), $_SESSION)) {
												if($_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]['confirmMethod'] === 'send-code' && !isEmptyVar($_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]['confirmVerification'])) {
													if($_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]['confirmVerification'] === $data['verification-code']) {
														$_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]['stepProgress'] = 3;
														$_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]['isPassed'] = true;

														// Data is valid
														$jsonResponse['success'] = true;
														$jsonResponse['message'] = 'Passed!';
														$jsonResponse['datetime'] = date('Y-m-d H:i:s');
														unset($jsonResponse['data']);
														$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
														$jsonResponse['errcode'] = 0;
														unset($jsonResponse['errors']);
													} else {
														// Data is invalid
														$jsonResponse['success'] = false;
														$jsonResponse['message'] = 'The verification code does not match, if the code does not reach your contact, try to create a new code!';
														$jsonResponse['datetime'] = date('Y-m-d H:i:s');
														unset($jsonResponse['data']);
														$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
														$jsonResponse['errcode'] = 1;
														unset($jsonResponse['errors']);
													}
												} else {
													$jsonResponse['success'] = false;
													$jsonResponse['message'] = 'Confirm-method is did not match with Server-side!';
													$jsonResponse['datetime'] = date('Y-m-d H:i:s');
													unset($jsonResponse['data']);
													$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
													$jsonResponse['errcode'] = 9;
													unset($jsonResponse['errors']);
												}
											} else {
												$jsonResponse['success'] = false;
												$jsonResponse['message'] = 'Error, you tried to jump beyond the specified step!';
												$jsonResponse['datetime'] = date('Y-m-d H:i:s');
												unset($jsonResponse['data']);
												$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
												$jsonResponse['errcode'] = 9;
												unset($jsonResponse['errors']);
											}
										} else {
											$jsonResponse['success'] = false;
											$jsonResponse['message'] = 'The verification code is empty, please fill it!';
											$jsonResponse['datetime'] = date('Y-m-d H:i:s');
											unset($jsonResponse['data']);
											$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
											$jsonResponse['errcode'] = 6;
											unset($jsonResponse['errors']);
										}
									break;
									case 'recovery-keys':
										if(array_key_exists('recovery-keys', $data) && !isEmptyVar($data['recovery-keys'])) {
											if(array_key_exists(sprintf('%sforgot-account', $APP_CORE['session_prefix']), $_SESSION)) {
												$_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]['confirmMethod'] = 'recovery-keys';
												$_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]['isPassed'] = false;

												// Data is invalid
												$jsonResponse['success'] = false;
												$jsonResponse['message'] = 'Currently this feature is still in <b>Development</b>!';
												$jsonResponse['datetime'] = date('Y-m-d H:i:s');
												unset($jsonResponse['data']);
												$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
												$jsonResponse['errcode'] = 9;
												unset($jsonResponse['errors']);
											} else {
												$jsonResponse['success'] = false;
												$jsonResponse['message'] = 'Error, you tried to jump beyond the specified step!';
												$jsonResponse['datetime'] = date('Y-m-d H:i:s');
												unset($jsonResponse['data']);
												$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
												$jsonResponse['errcode'] = 9;
												unset($jsonResponse['errors']);
											}
										} else {
											$jsonResponse['success'] = false;
											$jsonResponse['message'] = 'The recovery keys is empty, please fill it!';
											$jsonResponse['datetime'] = date('Y-m-d H:i:s');
											unset($jsonResponse['data']);
											$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
											$jsonResponse['errcode'] = 6;
											unset($jsonResponse['errors']);
										}
									break;
									default:
										// Data is empty or not met specific minimum requirements
										$jsonResponse['success'] = false;
										$jsonResponse['message'] = 'The following data (confirm-method) is empty or not met minimum requirement!';
										$jsonResponse['datetime'] = date('Y-m-d H:i:s');
										unset($jsonResponse['data']);
										$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
										$jsonResponse['errcode'] = 5;
										unset($jsonResponse['errors']);
									break;
								}
							} elseif(array_key_exists('request-method', $data) && !isEmptyVar($data['request-method'])) {
								switch (trim($data['request-method'])) {
									case 'send-code':
										$uuid = Uuid::uuid4();
										$uuidInString = $uuid->toString();;
										if(array_key_exists(sprintf('%sforgot-account', $APP_CORE['session_prefix']), $_SESSION)) {
											$accountData = $_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]['accountData'];
											$_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]['confirmMethod'] = 'send-code';
											$_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]['confirmVerification'] = $uuidInString;

											$isSentSuccess = false;
											// Send message
											$messageAccount = sprintf("Hello there, dear *%s*!\nYour account is trying to Reset the Password, below are the Verification-code for reset.\n\n*Code:* %s\n*Request-date:* %s\n\nIf thats not you, ignore this message. and do not give this code to anyone else!", strtoupper($accountData['name']), $uuidInString, date('Y-m-d H:i:s'));
											$apiResponse = requestAPI(path2url(API_BASEURL.'/message/provider-whatsapp.inc.php', null, true), 'POST', array(
												'action' => 'send',
												'method' => 'message',
												'token' => $apiConfigWhatsapp['registeredTokens'][0],
												'data' => json_encode(array(
													'phone_number' => preg_replace('/[^0-9]/', '', $accountData['phoneNumber']),
													'message' => $messageAccount
												), JSON_UNESCAPED_SLASHES)
											), true, false);
											if(!isEmptyVar($apiResponse) && isJSON($apiResponse)) {
												$apiResponse = json_decode(jsonFixer($apiResponse, true), true);
												if(!isEmptyVar($apiResponse) && is_array($apiResponse)) {
													if(array_key_exists('success', $apiResponse) && array_key_exists('message', $apiResponse)) {
														if(boolval($apiResponse['success']) === true || $apiResponse['success'] === 'true') {
															$isSentSuccess = true;
														}
													}
												}
											}
											// if Fail, send only Message Text
											//if($isSentSuccess == false) {
											//}

											// Verification code is created
											$jsonResponse['success'] = true;
											$jsonResponse['message'] = 'Request success!';
											$jsonResponse['datetime'] = date('Y-m-d H:i:s');
											unset($jsonResponse['data']);
											$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
											$jsonResponse['errcode'] = 0;
											unset($jsonResponse['errors']);
										} else {
											$jsonResponse['success'] = false;
											$jsonResponse['message'] = 'Error, you tried to jump beyond the specified step!';
											$jsonResponse['datetime'] = date('Y-m-d H:i:s');
											unset($jsonResponse['data']);
											$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
											$jsonResponse['errcode'] = 9;
											unset($jsonResponse['errors']);
										}
									break;
									default:
										// Data is empty or not met specific minimum requirements
										$jsonResponse['success'] = false;
										$jsonResponse['message'] = 'The following data (request-method) is empty or not met minimum requirement!';
										$jsonResponse['datetime'] = date('Y-m-d H:i:s');
										unset($jsonResponse['data']);
										$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
										$jsonResponse['errcode'] = 5;
										unset($jsonResponse['errors']);
									break;
								}
							} else {
								// Data is empty or not met specific minimum requirements
								$jsonResponse['success'] = false;
								$jsonResponse['message'] = 'The following data (confirm-method or request-method) is empty or not met minimum requirement!';
								$jsonResponse['datetime'] = date('Y-m-d H:i:s');
								unset($jsonResponse['data']);
								$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
								$jsonResponse['errcode'] = 5;
								unset($jsonResponse['errors']);
							}
						} else {
							$jsonResponse['success'] = false;
							$jsonResponse['message'] = 'Data is a bad JSON format';
							$jsonResponse['datetime'] = date('Y-m-d H:i:s');
							unset($jsonResponse['data']);
							$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode'] = 4;
							unset($jsonResponse['errors']);
						}
					} else {
						$jsonResponse['success'] = false;
						$jsonResponse['message'] = 'Data is empty or not a JSON format!';
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						unset($jsonResponse['data']);
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 3;
						unset($jsonResponse['errors']);
					}
				break;
				case 'reset-password':
					$data = null;
					if(!isEmptyVar($dataRequest) && strlen($dataRequest) >= 50) {
						$data = $EVW->decrypt($dataRequest, $APP_CORE['app_private_key']['app']['value']);
					}
					if(!isEmptyVar($data) && isJSON($data)) {
						$data = json_decode($data, true);
						if(is_array($data) && isAssoc($data)) {
							if(array_key_exists('new-password', $data) && !isEmptyVar($data['new-password'])) {
								if(array_key_exists(sprintf('%sforgot-account', $APP_CORE['session_prefix']), $_SESSION)) {
									/*
									 * Stage: 2
									 * Check if the account is registered with the provieded NIK
									 */
									$accountData = $_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]['accountData'];
									$isExist = db_runQuery(array(
										'config_array' => $configMysql,
										'database_index' => 0,
										'input' => array($accountData['id']),
										'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) as TOTAL FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
										'param' => 's',
										'getData' => true,
										'getAllRow' => false
									));

									if(isset($isExist['TOTAL']) && $isExist['TOTAL'] >= 1) {
										$updateAccount = db_runQuery(array(
											'config_array' => $configMysql,
											'database_index' => 0,
											'input' => array($data['new-password'], $accountData['id']),
											'query' => sprintf('UPDATE %s SET PASS = ? WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
											'param' => 'ss',
											'getData' => false,
											'getAllRow' => false
										));
										if($updateAccount) {
											$_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]['stepProgress'] = 4;
											$_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]['isPassed'] = true;

											// Password is changed
											$jsonResponse['success'] = true;
											$jsonResponse['message'] = 'Reset password successfully!';
											$jsonResponse['datetime'] = date('Y-m-d H:i:s');
											unset($jsonResponse['data']);
											$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
											$jsonResponse['errcode'] = 0;
											unset($jsonResponse['errors']);
										} else {
											unset($_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]);

											$jsonResponse['success'] = false;
											$jsonResponse['message'] = 'Reset password failed, server-side error! <br><b>Sorry you will be redirected to the First-step</b>';
											$jsonResponse['datetime'] = date('Y-m-d H:i:s');
											unset($jsonResponse['data']);
											$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
											$jsonResponse['errcode'] = 9;
											unset($jsonResponse['errors']);
										}
									} else {
										unset($_SESSION[sprintf('%sforgot-account', $APP_CORE['session_prefix'])]);

										// Data not found on Database
										$jsonResponse['success'] = false;
										$jsonResponse['message'] = 'Account not found, server-side error! <br><b>Sorry you will be redirected to the First-step</b>';
										$jsonResponse['datetime'] = date('Y-m-d H:i:s');
										unset($jsonResponse['data']);
										$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
										$jsonResponse['errcode'] = 9;
										unset($jsonResponse['errors']);
									}
								} else {
									$jsonResponse['success'] = false;
									$jsonResponse['message'] = 'Error, you tried to jump beyond the specified step!';
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									unset($jsonResponse['data']);
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 9;
									unset($jsonResponse['errors']);
								}
							} else {
								// Data is empty or not met specific minimum requirements
								$jsonResponse['success'] = false;
								$jsonResponse['message'] = 'The following data (new-password) is empty or not met minimum requirement!';
								$jsonResponse['datetime'] = date('Y-m-d H:i:s');
								unset($jsonResponse['data']);
								$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
								$jsonResponse['errcode'] = 5;
								unset($jsonResponse['errors']);
							}
						} else {
							$jsonResponse['success'] = false;
							$jsonResponse['message'] = 'Data is a bad JSON format';
							$jsonResponse['datetime'] = date('Y-m-d H:i:s');
							unset($jsonResponse['data']);
							$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode'] = 4;
							unset($jsonResponse['errors']);
						}
					} else {
						$jsonResponse['success'] = false;
						$jsonResponse['message'] = 'Data is empty or not a JSON format!';
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						unset($jsonResponse['data']);
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 3;
						unset($jsonResponse['errors']);
					}
				break;
				default:
					$jsonResponse['success'] = false;
					$jsonResponse['message'] = 'Invalid method-type!';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					unset($jsonResponse['data']);
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 2;
					unset($jsonResponse['errors']);
				break;
			}
		break;
		case 'session-account':
			/*
			 * Stage: 1
			 * Check if the user is online and has a NIK
			 */
			if(!isEmptyVar($userData->nik) && $user->isLoggedin()) {
				/*
				 * Stage: 2
				 * Check if the account is registered with the current NIK
				 */
				$isExist = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik),
					'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) as TOTAL, NAMA, NAMA_PERUSAHAAN, META_AVATAR, META_LOGO FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
					'param' => 's',
					'getData' => true,
					'getAllRow' => false
				));
				if(isset($isExist['TOTAL']) && $isExist['TOTAL'] >= 1) {
					// Serve data
					$dataResponse = array(
						'id' => hash('md5', $userData->nik),
						'role' => hash('md5', $userData->usr->role),
						'avatar' => $userData->usr->avatar,
					);

					$jsonResponse['success'] = true;
					$jsonResponse['message'] = 'Success!';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					$jsonResponse['data'] = $EVW->encrypt(json_encode($dataResponse, JSON_UNESCAPED_SLASHES), $APP_CORE['app_private_key']['app']['value']);
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 0;
					unset($jsonResponse['errors']);
				} else {
					$jsonResponse['success'] = false;
					$jsonResponse['message'] = 'You are not part of the Account scope!';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					unset($jsonResponse['data']);
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 3;
					unset($jsonResponse['errors']);
				}
			} else {
				$jsonResponse['success'] = false;
				$jsonResponse['message'] = 'You\'re not logged-in!';
				$jsonResponse['datetime'] = date('Y-m-d H:i:s');
				unset($jsonResponse['data']);
				$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
				$jsonResponse['errcode'] = 2;
				unset($jsonResponse['errors']);
			}
		break;
	}
	header('Content-Type: application/json');
	echo json_encode($jsonResponse, JSON_UNESCAPED_SLASHES);
	exit(0);
} else {
	header('Location: ../index.php');
}