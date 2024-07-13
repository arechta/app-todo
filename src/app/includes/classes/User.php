<?php namespace APP\includes\classes;
// Required every files to load
$thisPath = (defined('DIR_ROOT')) ? DIR_CONFIG : dirname(__FILE__, 4).'/configs';
require_once($thisPath.'/variables.php');
require_once(DIR_CONFIG.'/db-handles.php');
require_once(DIR_CONFIG.'/db-queries.php');
require_once(DIR_CONFIG.'/functions.php');
require_once(DIR_VENDOR.'/autoload.php');

// Memuat class lain
use APP\includes\classes\EncryptionVW;
use APP\includes\classes\DumpException;
use Jenssegers\Agent\Agent;
use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;
use Firebase\JWT\Key as JWTKey;
use Exception;
// use Spatie\Backtrace\Backtrace;

class User {
	public $initConfig = array(); // Config yang dipilih
	private $appConfig = null;
	private $isBulk = false;
	private $bulkAction = [];
	private $userData = null; // Data user
	private $returnMethod = array(
		'success' => false,
		'message' => '',
		'datetime' => null,
		'data' => null,
		'took' => 0,
		'errcode' => 0,
		'errors' => array()
	);

	// X MAIN CODE
	const X_DATA_FOUND = 1000;
	const X_NO_DATA_FOUND = 1001;
	const X_FOUND = 1002;
	const X_NOT_FOUND = 1003;
	const X_VALIDATE_ERROR = 1004;
	const X_VALIDATE_SUCCESS = 1005;
	const X_SELECTED = 1006;
	const X_NOT_SELECTED = 1007;
	const X_EXECUTE_FAILED = 1008;
	const X_EXECUTE_SUCCESS = 1009;

	// SESSION CODE
	const SESSION_LIMIT_REACHED = 1101;
	const SESSION_ACTIVE = 1102;
	const SESSION_EXPIRED = 1103;

	// USER CODE
	const USER_EXIST = 1201;
	const USER_NOT_EXIST = 1202;
	const USER_ACTIVE = 1203;
	const USER_NOT_ACTIVE = 1203;
	const USER_LOGIN_ERROR = 1203;
	const USER_INVALID_CREDENTIALS = 1203;

	public function __construct (array $_data = array(), $_configs = array()) {
		// Ambil konfigurasi
		$defaultConfig = array(
			'init' => array(
				'dbms' => array(
					'mysql_host' => 'localhost',
					'mysql_username' => 'root',
					'mysql_password' => '',
					'mysql_database' => array(),
					'mysql_selected' => array(
						'db' => 0,
						'tb' => 'user_account'
					)
				),
				'session' => array(
					'method' => 'php',
					'prefix' => 'ARE_'
				),
				'valid' => array(
					'column_data' => array()
				)
			),
			'app' => array(
				'CORE' => array(
					'session_method' => 'php',
					'session_prefix' => 'ARE_',
					'session_user' => array(
						'expire_time' => 3600,
						'max_user_device' => 3
					),
				)
			)
		);
		$this->appConfig = loadConfig(DIR_CONFIG . '/app-setting.json.php', $defaultConfig['app'], 'json');

		if (array_key_exists('CORE', $this->appConfig)) {
			$_initConfig = array(
				'dbms' => array(
					'mysql_host' => $this->appConfig['CORE']['db_host'],
					'mysql_username' => $this->appConfig['CORE']['db_user'],
					'mysql_password' => $this->appConfig['CORE']['db_pass'],
					'mysql_database' => $this->appConfig['CORE']['db_name'],
					'mysql_selected' => array(
						'db' => 0,
						'tb' => $this->appConfig['CORE']['tb_prefix'] . 'user_account'
					)
				),
				'session' => array(
					'method' => $this->appConfig['CORE']['session_method'],
					'prefix' => $this->appConfig['CORE']['session_prefix']
				)
			);
			$this->initConfig = array_replace_recursive($defaultConfig['init'], array_intersect_key($_initConfig, $defaultConfig['init']));

			if (array_key_exists('valid', $this->initConfig)) {
				if (array_key_exists('column_data', $this->initConfig['valid'])) {
					$this->initConfig['valid']['column_data'] = db_runQuery(array(
						'config_array' => $this->initConfig['dbms'],
						'database_index' => $this->initConfig['dbms']['mysql_selected']['db'],
						'input' => array($this->initConfig['dbms']['mysql_database'][$this->initConfig['dbms']['mysql_selected']['db']], $this->initConfig['dbms']['mysql_selected']['tb']),
						'query' => 'SELECT GROUP_CONCAT(COLUMN_NAME) as COLUMN_LIST FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?;',
						'param' => 'ss',
						'getData' => true,
						'callback' => function($result) {
							$output = $result['data'];
							if (isset($output['COLUMN_LIST']) && $output !== 'ZERO_DATA') {
								$output = explode(',', $output['COLUMN_LIST']);
							} else {
								$output = array();
							}
							return $output;
						}
					));
				}
			}
		}
		$this->initConfig = array_replace_recursive($this->initConfig, array_intersect_key($_configs, $this->initConfig));

		if (count($_data) >= 1) {
			$this->userData = arr2Obj($_data);
		}
	}

	private function isValid_ColumnData($_data) {
		/*
		 * Memeriksa apakah pasangan key/value pada
		 * $_data adalah valid dengan "$this->initConfig['valid']['column_data']"
		 * 
		 * return: true => data, false => null
		*/
		if (!is_null($_data) && is_array($_data)) {
			$_data = array_filter($_data);
			$lenData = count($_data);
			$lenValid = 0;
			if (isAssoc($_data)) {
				foreach ($_data as $k => $v) {
					if (in_array($k, $this->initConfig['valid']['column_data']) && (!empty($v) && $v != '')) $lenValid++;
					else $this->returnMethod['errors'][__FUNCTION__][] = sprintf("Data '%s' yang diteruskan tidak lulus validasi!", $k);
				}
			} else {
				if (is_array($_data)) {
					foreach($_data as $k) {
						if (in_array(trim($k), $this->initConfig['valid']['column_data'])) $lenValid++;
						else $this->returnMethod['errors'][__FUNCTION__][] = sprintf("Data '%s' yang diteruskan tidak lulus validasi!", trim($k));
					}
				}
				if (is_string($_data)) {
					if (in_array(trim($_data), $this->initConfig['valid']['column_data'])) $lenValid++;
					else $this->returnMethod['errors'][__FUNCTION__][] = sprintf("Data '%s' yang diteruskan tidak lulus validasi!", trim($_data));
				}
			}

			return ($lenData == $lenValid) ? $_data : null;
		}

		return null;
	}

	public function bulkExecute() {
		$result = null;
		if ($this->isBulk == false && count($this->bulkAction) != 0) {
			$this->isBulk = true;
			foreach ($this->bulkAction as $action => $data) {
				if (is_array($data)) {
					for ($i = 0; $i <= (count($data)-1); $i++) {
						switch ($action) {
							case 'add':
								$result[$action][$i] = $this->add($data[$i]);
							break;
							case 'update':
								$result[$action][$i] = $this->update($data[$i]);
							break;
							case 'get':
								$result[$action][$i] = $this->get($data[$i]);
							break;
						}
						unset($this->bulkAction[$action][$i]);
					}
				} else {
					switch ($action) {
						case 'add':
							$result[$action] = $this->add($data);
						break;
						case 'update':
							$result[$action] = $this->update($data);
						break;
						case 'get':
							$result[$action] = $this->get($data);
						break;
					}
				}
				unset($this->bulkAction[$action]);
			}
			$this->bulkAction = null;
			$this->isBulk = false;
		}
		return (!is_null($result)) ? arr2Obj($result) : false;
	}
	public function isLoggedIn($returnNIK = false) {
		$startTime = floor(microtime(true)*1000);
		$userToken = $this->getSession('tkn');
		$userToken = ($userToken['success']) ? $userToken['data'] : false;
		$userStatus = $this->getSession('stt');
		$userStatus = ($userStatus['success']) ? $userStatus['data'] : 'off';
		$userNIK = $this->getSession('nik');
		$userNIK = ($userNIK['success']) ? $userNIK['data'] : null;
		
		// Check user login atau belum
		$clearSession = function() {
			$sessionMethod = strtolower(trim($this->initConfig['session']['method']));
			switch($sessionMethod) {
				case 'php': default:
					if(session_status() === PHP_SESSION_NONE) { session_start(array('cookie_lifetime' => 0, 'cookie_path' => '/', 'cookie_httponly' => '1')); }
					$isSessionClose = true;
					$registeredSessionKey = $this->takeSessionKey();
					$registeredSessionKey = ($registeredSessionKey['success']) ? $registeredSessionKey['data'] : array();
					if (is_array($registeredSessionKey) && count($registeredSessionKey) >= 1) {
						foreach ($registeredSessionKey as $sessionKey) {
							unset($_SESSION[$sessionKey]);
						}
					}
				break;
				case 'jwt':
					$isSessionClose = true;
					$sessionName = 'X-ARE-SESSION';
					if(isset($_COOKIE[$sessionName])) {
						setcookie($sessionName, 'INVALID', time()-3600, '/');
						unset($_COOKIE[$sessionName]);
						setcookie($sessionName, '', time()-3600, '/');
					}
				break;
			}
		};
		if ($userToken != false) {
			if ($this->hasLoggedIn($userToken)['success']) {
				if (isset($userStatus) && $userStatus == 'on') { 
					$this->returnMethod['success'] = true;
					$this->returnMethod['data'] = ($returnNIK) ? $userNIK : null;
				} else {
					$this->returnMethod['success'] = false;
				}
			} else {
				$clearSession();
				$this->returnMethod['success'] = false;
			}
		} else {
			$clearSession();
			$this->returnMethod['success'] = false;
		}

		$this->returnMethod['message'] = ($this->returnMethod['success']) ? 'Success!' : 'Failed.';
		$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
		if(isset($this->returnMethod['data']) && is_null($this->returnMethod['data'])) {
			unset($this->returnMethod['data']);
		}
		$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
		$this->returnMethod['errcode'] = ($this->returnMethod['success']) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED;
		unset($this->returnMethod['errors']);
		return $this->returnMethod;
	}
	public function login() {
		$startTime = floor(microtime(true)*1000);
		$_data = null;
		$_select = '*';

		// Saya menggunakan dinamis Argumen/Parameter (karena, fungsi parameter kadang diisi 1 atau berurutan salah)
		// #prepareData
		$_args = func_get_args();
		if(func_num_args() <= 2) {
			foreach($_args as $idx => $val) {
				if(is_array($val)) {
					if(isAssoc($val)) $_data = $val;
					else $_select = $val;
				}
				if(is_string($val)) $_select = $val;
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// Check User is already logged in
		if ($this->isLoggedIn()['success']) {
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error User is already logged-in.");
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_EXECUTE_FAILED;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}

		// #validateData
		if(is_null($_data)) {
			if(is_null($this->userData)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'data' untuk user tidak ditemukan pada Method '%s'" , end(explode('\\', __METHOD__))),'message' => sprintf("Pada saat melakukan pemeriksaan 'data' user, parameter pada Method '%s' tidak di temukan. kemudian beralih pemeriksaan ke parameter di '%s' dan tidak di temukan kembali", end(explode('\\', __METHOD__)), end(explode('\\', __CLASS__)).'::__construct'),'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__), 'user-error', true); /* STOP_HIDE */
				$this->returnMethod['message'] = sprintf("Error on '%s' no data found.", end(explode('\\', __METHOD__)));
				unset($this->returnMethod['data']);
				$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
				$this->returnMethod['errcode'] = self::X_NO_DATA_FOUND;
				// unset($this->returnMethod['errors']);
				return $this->returnMethod;
			} else { $_data = (array) $this->userData; }
		}
		$_dataTest = $this->isValid_ColumnData($_data);
		if(is_null($_dataTest)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'data' untuk user tidak ditemukan pada Method '%s'" , end(explode('\\', __METHOD__))),'message' => "Pada saat proses melakukan Validasi 'data', method mengembalikan nilai 'null'",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => $_data), 'user-error', true);/* STOP_HIDE */
			$this->returnMethod['message'] = sprintf("Error on '%s' validate 'data' fail.", end(explode('\\', __METHOD__)));
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			// unset($this->returnMethod['errors']);
			return $this->returnMethod;
		} else { $_data = $_dataTest; }
		$_selectTest = $_select;
		if(is_string($_selectTest)) {
			if(!(substr_count($_selectTest, ',') >=  1 || $_selectTest == '*' || strlen($_selectTest) >= 3)) {/* START_HIDE */ exceptionLog(array('title' => sprintf("Error 'select' untuk user bukan salah satu data (Array/String '*'/String 'key1, key2') pada Method '%s'", end(explode('\\', __METHOD__))),'message' => "Parameter 'select' perlu di isi untuk mengembalikan nilai jika user berhasil melakukan autentikasi Login, Contoh: 'NAMA'",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => $_selectTest),'user-error',true);/* STOP_HIDE */
				$this->returnMethod['success'] = false;
				$this->returnMethod['message'] = sprintf("Error on '%s' selected data not filled.", end(explode('\\', __METHOD__)));
				$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
				unset($this->returnMethod['data']);
				$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
				$this->returnMethod['errcode'] = self::X_NOT_SELECTED;
				// unset($this->returnMethod['errors']);
				return $this->returnMethod;
			}
			if(substr_count($_selectTest, ',') >= 1) {
				$_selectTest = $this->isValid_ColumnData(explode(',', $_selectTest));
			}
		} else { $_selectTest = (is_array($_selectTest)) ? $this->isValid_ColumnData($_selectTest) : $_selectTest; }
		if(is_null($_selectTest)) {/* START_HIDE */ exceptionLog(array('title' => sprintf("Error 'select' untuk user tidak ditemukan pada Method '%s'", end(explode('\\', __METHOD__))),'message' => "Pada saat proses melakukan Validasi 'select', method mengembalikan nilai 'null'",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => $_select), 'user-error', true);/* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s' validate 'select' fail.", end(explode('\\', __METHOD__)));
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			// unset($this->returnMethod['errors']);
			return $this->returnMethod;
		} else {
			$_select = $_selectTest;
		}

		// #re-prepareData
		$dataRequired = [
			'NIK' => null,
			'ID_CARD' => $_data['NIK'] ?? null,
			'NO_HP' => $_data['NIK'] ?? null,
			'EMAIL' => $_data['NIK'] ?? null,
			'PASS' => null
		];
		$dataTest = array_merge($dataRequired, array_intersect_key($_data, $dataRequired));
		$isDataEmpty = false;
		foreach($dataTest as $k => $v) {
			if(isEmptyVar($v)) {
				$isDataEmpty = true;
				$this->returnMethod['errors'][__FUNCTION__][] = sprintf("Error data '%s' nilai tidak ada, tolong isi!", $k);
			}
		}
		if($isDataEmpty) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'data' user yang di serahkan, tidak di isi pada Method '%s'" , end(explode('\\', __METHOD__))),'message' => "Pada saat proses melakukan Validasi 'data', salah satu dari data wajib/mandatory seperti 'NIK' atau 'PASS' bernilai kosong",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => $_data), 'user-error', true);/* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s' currently 'data' passed from params is empty.", end(explode('\\', __METHOD__)));
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			// unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}
		$dataPrepare = array($dataTest, $_select);

		// Memproses data
		$pw_cmpr = $dataPrepare[0]['PASS'];
		unset($dataPrepare[0]['PASS']);
		if(is_array($dataPrepare[1])) {
			if(array_key_exists('NIK', $dataPrepare[1])) unset($dataPrepare[1]['NIK']);
			if(array_key_exists('PASS', $dataPrepare[1])) unset($dataPrepare[1]['PASS']);
		}
		$dataSearch = array_values($dataPrepare[0]);
		$dataSelect = (!is_string($dataPrepare[1]) && is_array($dataPrepare[1])) ? implode(',', $dataPrepare[1]) : $dataPrepare[1];
		// Memeriksa data, jika tidak ada kesalahan mengembalikan nilai 'true (+data)' jika tidak 'false'
		$user = db_runQuery(array(
			'config_array' => $this->initConfig['dbms'],
			'database_index' => $this->initConfig['dbms']['mysql_selected']['db'],
			'input' => $dataSearch,
			'query' => sprintf("SELECT $dataSelect, NIK, NAMA, PASS, META_LOGO, META_AVATAR, JML_LOGIN, TGL_LOGIN FROM %s WHERE NIK = ? OR ID_CARD = ? OR NO_HP = ? OR EMAIL = ?;", $this->initConfig['dbms']['mysql_selected']['tb']),
			'param' => 'ssss',
			'getData' => true,
			'getAllRow' => false
		));

		if(!isEmptyVar($user) && $user !== 'ZERO_DATA') {
			$user = arr2Obj($user); // Convert to Object
			$pw_hash = $user->PASS;

			//if (password_verify($pw_cmpr, $pw_hash)) {
			if((string) $pw_cmpr === (string) $pw_hash) {
				// Check jumlah max user login device
				if(!is_null($this->appConfig['CORE']['session_user']['max_user_device'])) {
					$maxUD = (int) $this->appConfig['CORE']['session_user']['max_user_device'];
					if(!empty($maxUD) && is_int($maxUD)) {
						$sessionCount = $this->countLogins($user->NIK);
						$sessionCount = ($sessionCount['success']) ? $sessionCount['data'] : 0;
						if($sessionCount >= (int) $maxUD) {/* START_HIDE */exceptionLog(array('title' => sprintf("Sorry the user has reached the maximum (%s) login session, please log out on one of the devices. and try again..." , $maxUD),'message' => "Pada saat proses melakukan Login, akun pada user sudah melebihi batas limit sesi login dan menjadikan alasan gagal untuk melakukan autentikasi akun...",'severity' => E_USER_WARNING,'filename' => __FILE__,'line' => __LINE__,'data' => array('user' => (array) $user, 'session' => $sessionCount)), 'user-warning', true);/* STOP_HIDE */
							$this->returnMethod['success'] = false;
							$this->returnMethod['message'] = sprintf("Warning on '%s' currently login session of User is full!", end(explode('\\', __METHOD__)));
							$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
							unset($this->returnMethod['data']);
							$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$this->returnMethod['errcode'] = self::SESSION_LIMIT_REACHED;
							unset($this->returnMethod['errors']);
							return $this->returnMethod;
						}
					}
				}

				/* Buat login device & session */
				$createLoginDevice = $this->setLoggedIn(array('NIK' => $user->NIK), 'add', true);
				$createUserSession = false;
				if($createLoginDevice['success']) {
					// Set sessions data
					$sessionData = array(
						'stt' => 'on',
						'nik' => $user->NIK,
						'tkn' => ($createLoginDevice['success']) ? $createLoginDevice['data'] : false,
						'usr' => array(
							'name' => $user->NAMA,
							'role' => db_runQuery(array(
								'config_array' => $this->initConfig['dbms'],
								'database_index' => $this->initConfig['dbms']['mysql_selected']['db'],
								'input' => $user->NIK,
								'query' => sprintf('SELECT * FROM %s WHERE NIK = ?;', $this->appConfig['CORE']['tb_prefix'].'user_privileges'),
								'param' => 's',
								'getData' => true,
								'getAllRow' => false,
								'callback' => function($response) {
									$output = $response['data'];
									if (!isEmptyVar($output) && $output !== 'ZERO_DATA') {
										if (array_key_exists('LEVEL', $output) || array_key_exists('LEVEL_CODE', $output)) {
											if (!is_null($output['LEVEL']) && strlen($output['LEVEL']) >= 1) {
												switch((int) $output['LEVEL']) {
													case 0: $output = 'c'; break;
													case 1: $output = 'cs'; break;
													case 2: $output = 'ho'; break;
													case 99: $output = 'admin'; break;
													default: $output = '-'; break;
												}
											} elseif (!isEmptyVar($output['LEVEL_CODE'])) {
												$output = $output['LEVEL_CODE'];
											} else {
												$output = '-';
											}
										}
									}
									return $output;
								}
							)),
							'avatar' => db_runQuery(array(
								'config_array' => $this->initConfig['dbms'],
								'database_index' => $this->initConfig['dbms']['mysql_selected']['db'],
								'input' => array($user->META_LOGO, $user->META_AVATAR),
								'query' => sprintf("SELECT FILE_UID FROM %s WHERE FILE_UID = ? OR FILE_UID = ?;", $this->appConfig['CORE']['tb_prefix'].'filemeta_data'),
								'param' => 'ss',
								'getData' => true,
								'getAllRow' => false,
								'callback' => function($response) {
									$output = $response['data'];
									$result = path2url(sprintf('%s/image/illustrations/avatar-%s.png', DIR_ASSET, $this->appConfig['CORE']['app_build_version']));
									if ($output !== 'ZERO_DATA' && !is_null($output) && isset($output)) {
										$result = sprintf('%s/app/includes/view-document.inc.php?uid=%s', getURI(2), $output['FILE_UID']);
									}
									return $result;
								}
							)),
							'login' => array(
								'total' => $user->JML_LOGIN,
								'last' => $user->TGL_LOGIN
							)
						)
					);
					$createUserSession = $this->setSession($sessionData, 'add');
				}

				// Don't pass sensitive data
				unset($user->PASS);
				unset($user->META_LOGO);
				unset($user->META_AVATAR);
	
				$updateLogin = db_runQuery([
					'config_array' => $this->initConfig['dbms'],
					'database_index' => $this->initConfig['dbms']['mysql_selected']['db'],
					'input' => array(((int) $user->JML_LOGIN + 1), date('Y-m-d H:i:s'), (string) $user->NIK),
					'query' => sprintf('UPDATE %s SET JML_LOGIN = ?, TGL_LOGIN = ? WHERE NIK = ?;', $this->initConfig['dbms']['mysql_selected']['tb']),
					'param' => 'sss',
					'getData' => false
				]);
	
				$this->returnMethod['success'] = ($createLoginDevice['success'] && $createUserSession['success']);
				$this->returnMethod['message'] = ($this->returnMethod['success']) ? 'Success!' : 'Failed.';
				$this->returnMethod['data'] = ($this->returnMethod['success']) ? array('selected' => (array) $user, 'session' => $sessionData) : null;
				$this->returnMethod['errcode'] = ($this->returnMethod['success']) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED;
			} else {
				$this->returnMethod['success'] = false;
				$this->returnMethod['message'] = 'Invalid ID or Password!';
				$this->returnMethod['errcode'] = self::USER_INVALID_CREDENTIALS;
			}
		} else {
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = 'User account not exist!';
			$this->returnMethod['errcode'] = self::USER_NOT_EXIST;
		}
		$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
		$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
		if(!$this->returnMethod['success']) {
			unset($this->returnMethod['data']);
			unset($this->returnMethod['errors']);
		}
		return $this->returnMethod;
	}
	public function logout() {
		$startTime = floor(microtime(true)*1000);
		$userToken = $this->getSession('tkn');
		$userToken = ($userToken['success']) ? $userToken['data'] : false;
		$isSessionClose = false;
		if($userToken != false) {
			if($this->hasLoggedIn($userToken)['success']) {
				if($this->closeLogin($userToken)['success']) {
					$sessionMethod = strtolower(trim($this->initConfig['session']['method']));
					switch($sessionMethod) {
						case 'php': default:
							if(session_status() === PHP_SESSION_NONE) { session_start(array('cookie_lifetime' => 0, 'cookie_path' => '/', 'cookie_httponly' => '1')); }
							$isSessionClose = true;
							$registeredSessionKey = $this->takeSessionKey();
							$registeredSessionKey = ($registeredSessionKey['success']) ? $registeredSessionKey['data'] : array();
							if (is_array($registeredSessionKey) && count($registeredSessionKey) >= 1) {
								foreach ($registeredSessionKey as $sessionKey) {
									unset($_SESSION[$sessionKey]);
								}
							}
						break;
						case 'jwt':
							$isSessionClose = true;
							$sessionName = 'X-ARE-SESSION';
							if(isset($_COOKIE[$sessionName])) {
								setcookie($sessionName, 'INVALID', time()-3600, '/');
								unset($_COOKIE[$sessionName]);
								setcookie($sessionName, '', time()-3600, '/');
							}
						break;
					}
				}
			}
		}
		//session_unset();
		//session_destroy();
		//session_write_close();
		//setcookie(session_name(),'',0,'/');
		//session_regenerate_id(true);
		$checkLogin = $this->isLoggedIn();
		$this->returnMethod['success'] = (!$checkLogin['success'] && $isSessionClose);
		$this->returnMethod['message'] = ($this->returnMethod['success']) ? 'Success!' : 'Failed.';
		$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
		unset($this->returnMethod['data']);
		$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
		$this->returnMethod['errcode'] = ($this->returnMethod['success']) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED;
		unset($this->returnMethod['errors']);
		return $this->returnMethod;
	}

	// CREATE // Belum
	public function add () {
		//empty
	}
	public function new ($args) {
		return $this->add($args);
	}
	public function create ($args) {
		return $this->add($args);
	}

	// READ/GET //
	public function get () {
		$_data = $_select = null;
		$_singleInit = false;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData
		$_args = func_get_args();
		if (func_num_args() <= 4) {
			foreach ($_args as $idx => $val) {
				if (is_array($val) && isAssoc($val)) $_data = $val;
				if (is_string($val)) $_select = $val;
				if (is_bool($val)) $_singleInit = $val;
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// Menyiapkan data sebelum di proses, apakah init(bulk action) atau singleInit(run-once)
		if ($this->isBulk == false || $_singleInit == true) {
			// #checkingData
			if (is_null($_data)) {
				if (is_null($this->userData)) {
					$this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
					reportLog("User::get(".__LINE__."): Error 'data' untuk user tidak ditemukan pada 'Constructor/Parameter'!", 412);
					header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
					exit();
				} else { $_data = (array) $this->userData; }
			}
			$_data = $this->isValid_ColumnData($_data, $this->validData);
			if (is_null($_data)) {
				$this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				reportLog("User::get(".__LINE__."): Error 'data' untuk user tidak ditemukan pada 'Constructor/Parameter'!", 412);
				header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				exit();
			}
			if (is_string($_select) && $_select != '*') {
				$this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				reportLog("User::get(".__LINE__."): Error 'select' untuk user bukan data Array/String '*' pada 'Constructor/Parameter'!", 406);
				header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				exit();
			} else { $_select = (is_array($_select)) ? $this->isValid_ColumnData($_select, $this->validData) : $_select ; }
			if (is_null($_select)) {
				$this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				reportLog("User::get(".__LINE__."): Error 'select' untuk user tidak ditemukan pada 'Constructor/Parameter'!", 412);
				header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				exit();
			}

			// #re-prepareData
			$data = [ 'NIK' => null, 'NO_HP' => null, 'EMAIL' => null ];
			$data = array_merge($data, array_intersect_key($_data, $data));
			$dataMerge = [ $data, $_select ];
			if ($_singleInit == false) $this->bulkAction['get'][] = $dataMerge;
		}

		// Memproses data
		if ($this->isBulk == true || $_singleInit == true) {
			$data = ($this->isBulk) ? $_data : $dataMerge;
			//if ($_singleInit) { $this->isDBAlive = connectToDBMS(['use_config' => true, 'database_index' => $this->selectDB]); }
			$dataSearch = array_values($data[0]);
			$dataSelect = (!is_string($data[1])) ? implode(",", $data[1]) : $data[1];
			// Memeriksa data, jika tidak ada kesalahan mengembalikan nilai 'true (+data)' jika tidak 'false'
			//if ($this->isDBAlive) {
				$this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				$user = arr2Obj(db_runQuery([
					'config_array' => $this->selectDBMS,
					//'connection' => $this->isDBAlive,
					'database_index' => $this->selectDB,
					'input' => $dataSearch,
					'query' => "SELECT $dataSelect FROM ". $this->selectTBL ." WHERE NIK = ? OR NO_HP = ? OR EMAIL = ?;",
					'param' => "sss",
					'getData' => true,
					'stmtError' => getURI(2) . '/index.php?toast=' . base64_encode(json_encode($this->dataToast))
				]));
				//mysqli_close($this->isDBAlive);
				//unset($this->isDBAlive);
				return $user;
			//} else {
			//	reportLog("User::init(".__LINE__."): Error periksa konfigurasi konesi database!", 413);
			//	$this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
			//	header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
			//	exit();
			//}
		}
	}

	// UPDATE //
	public function update () {
		$_data = null;
		$_select = null;
		$_singleInit = false;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData
		$_args = func_get_args();
		if (func_num_args() <= 4) {
			foreach ($_args as $idx => $val) {
				if (is_array($val) && isAssoc($val)) $_data = $val;
				if (is_string($val)) $_select = $val;
				if (is_bool($val)) $_singleInit = $val;
				unset($_args[$idx]);
			}
			unset($_args);
		}
		// Menyiapkan data sebelum di proses, apakah init(bulk action) atau singleInit(run-once)
		if ($this->isBulk == false || $_singleInit == true) {
			// #checkingData
			if (is_null($_data)) {
				if (is_null($this->userData)) {
					$this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
					reportLog("User::update(".__LINE__."): Error 'data' untuk user tidak ditemukan pada 'Constructor/Parameter'!", 412);
					header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
					exit();
				} else { $_data = (array) $this->userData; }
			}
			$_data = $this->isValid_ColumnData($_data, $this->validData);
			if (is_null($_data)) {
				reportLog("User::update(".__LINE__."): Error 'data' untuk user tidak ditemukan pada 'Constructor/Parameter'!", 412);
				$this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				exit();
			}
			if (is_string($_select) && $_select != '*') {
				$this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				reportLog("User::get(".__LINE__."): Error 'select' untuk user bukan data Array/String '*' pada 'Constructor/Parameter'!", 406);
				header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				exit();
			} else { $_select = (is_array($_select)) ? $this->isValid_ColumnData($_select, $this->validData) : $_select ; }
			if (is_null($_select)) {
				reportLog("User::update(".__LINE__."): Error 'select' untuk user tidak ditemukan pada 'Constructor/Parameter'!", 412);
				$this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				exit();
			}

			// #prepareData
			$data = [ 'NIK' => null, 'NO_HP' => null, 'EMAIL' => null ];
			$data = array_merge($data, array_intersect_key($_data, $data));
			$dataPermanent = [ 'NIK' ];
			for ($i=0; $i<=(count($dataPermanent)-1); $i++) {
				if (($idx = array_search($dataPermanent[$i], $_select)) !== false) {
					unset($select[$idx]);
				}
			}

			// FOR DEBUG (IF CONTINUE)
			//pre_dump($_select);
			//exit();
			
			$dataMerge = [ $data, $_select ];
			if ($_singleInit == false) $this->bulkAction['update'][] = $dataMerge;
		}

		// Memproses data
		if ($this->isBulk == true || $_singleInit == true) {
			$data = ($this->isBulk) ? $_data : $dataMerge;
			//if ($_singleInit) { $this->isDBAlive = connectToDBMS(['use_config' => true, 'database_index' => $this->selectDB]); }
			$dataUpdate = array_values($data[0]);
			$dataSelect = (!is_string($data[1])) ? implode(" = ?, ", $data[1]) : $data[1];

			// FOR DEBUG (IF CONTINUE)
			//pre_dump($dataSelect);
			//exit();

			// Memeriksa data, jika tidak ada kesalahan mengembalikan nilai 'true (+data)' jika tidak 'false'
			//if ($this->isDBAlive) {
				$user = arr2Obj(db_runQuery([
					'config_array' => $this->selectDBMS,
					//'connection' => $this->isDBAlive,
					'database_index' => $this->selectDB,
					'input' => $dataUpdate,
					'query' => "UPDATE ". $this->selectTBL ." SET $dataSelect WHERE NIK = ? OR NO_HP = ? OR EMAIL = ?;",
					'param' => "sss",
					'getData' => true,
					'stmtError' => getURI(2) . '/index.php?toast=' . base64_encode(json_encode($this->dataToast))
				]));
				//mysqli_close($this->isDBAlive);
				//unset($this->isDBAlive);

				return $user;
			//} else {
			//	reportLog("User::init(".__LINE__."): Error periksa konfigurasi konesi database!", 413);
			//	$this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
			//	header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
			//	exit();
			//}
		}
	}
	public function edit ($args) {
		return $this->update($args);
	}

	// DELETE //
	public function delete () {
		// still empty
	}
	public function remove ($args) {
		return $this->delete($args);
	}

	// LOGGED IN DEVICE //
	/**
	 * Fungsi untuk menghitung jumlah user login singkat-nya
	 * ini menghitung berapa user login, dalam beberapa device
	 * 
	 * @param string $NIK		NIK User yang dicari
	 * 
	 * @return boolean			Total Login | FALSE
	 */
	public function countLogins() {
		$startTime = floor(microtime(true)*1000);
		$_data = null;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData
		$_args = func_get_args();
		if(func_num_args() >= 1) {
			foreach($_args as $idx => $val) {
				if(is_string($val)) $_data = $val;
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// #checkingData
		if(is_null($_data) || empty($_data)) {
			if (is_null($this->userData->NIK)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'nik' untuk user(device) tidak ditemukan pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => sprintf("Pada saat melakukan pemeriksaan 'nik' user, parameter pada Method '%s(%s)' tidak di temukan. tolong harap periksa kembali lagi", end(explode('\\', __METHOD__)), __LINE__),'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_data)), 'user-error', true); /* STOP_HIDE */
				$this->returnMethod['success'] = false;
				$this->returnMethod['message'] = sprintf("Error on '%s' no data found.", end(explode('\\', __METHOD__)));
				$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
				unset($this->returnMethod['data']);
				$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
				$this->returnMethod['errcode'] = self::X_NO_DATA_FOUND;
				unset($this->returnMethod['errors']);
				return $this->returnMethod;
			} else { $_data = $this->userData->NIK; }
		}

		// Memeriksa data, jika tidak ada kesalahan mengembalikan nilai 'total user login device' jika tidak FALSE
		$getLoggedInDevice = db_runQuery(array(
			'config_array' => $this->initConfig['dbms'],
			'database_index' => $this->initConfig['dbms']['mysql_selected']['db'],
			'input' => $_data,
			'query' => sprintf("SELECT count(1) as TOTAL FROM %s WHERE NIK = ?;", $this->appConfig['CORE']['tb_prefix'] . 'loggedin_device'),
			'param' => 's',
			'getData' => true
		));

		$this->returnMethod['success'] = ($getLoggedInDevice !== 'ZERO_DATA' && isset($getLoggedInDevice['TOTAL']) && $getLoggedInDevice['TOTAL'] >= 1);
		$this->returnMethod['message'] = ($this->returnMethod['success']) ? 'Data found!' : 'Data not found.';
		$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
		if ($this->returnMethod['success']) {
			$this->returnMethod['data'] = $getLoggedInDevice['TOTAL']; 
		} else {
			unset($this->returnMethod['data']);
		}
		$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
		$this->returnMethod['errcode'] = ($this->returnMethod['success']) ? self::X_DATA_FOUND : self::X_NO_DATA_FOUND;
		unset($this->returnMethod['errors']);
		return $this->returnMethod;
	}

	/**
	 * Fungsi untuk memeriksa apakah login user ada singkat-nya
	 * ini mencari login device user berdasarkan Token user login
	 * 
	 * @param string $token		Token User yang dicari
	 * @param boolean $return	(Opsional: true|false) jika ingin mendapatkan datanya juga
	 * 
	 * @return boolean			TRUE | FALSE
	 */
	public function hasLoggedIn() {
		$startTime = floor(microtime(true)*1000);
		$_identity = null;
		$_return = false;
		
		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData
		$_args = func_get_args();
		if(func_num_args() >= 1 && func_num_args() <= 2) {
			foreach($_args as $idx => $val) {
				if(is_string($val)) $_identity = $val;
				if(is_bool($val)) $_return = $val;
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// #prepareData
		if(is_null($_identity) || empty($_identity)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'token' user(device) yang di serahkan, tidak di isi pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => "Pada saat proses melakukan Validasi 'token', salah satu dari data wajib/mandatory seperti 'TOKEN' bernilai kosong",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_identity)), 'user-error', true);/* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' currently 'token' passed from params is empty.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['success'] = date('Y-m-d H:i:s', $startTime);
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}
		if(is_null($_return)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'return' user(device) yang di serahkan, tidak di isi pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => "Pada saat proses melakukan Validasi 'return', bukan bernilai TRUE/FALSE",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_identity, $_return)), 'user-error', true);/* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' currently 'return' passed from params is invalid.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['success'] = date('Y-m-d H:i:s', $startTime);
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}

		// Memeriksa data, jika tidak ada kesalahan mengembalikan nilai 'true (+data)' jika tidak 'false'
		$getLoggedInDevice = db_runQuery(array(
			'config_array' => $this->initConfig['dbms'],
			'database_index' => $this->initConfig['dbms']['mysql_selected']['db'],
			'input' => $_identity,
			'query' => sprintf('SELECT * FROM %s WHERE TOKEN = ?;', $this->appConfig['CORE']['tb_prefix'].'loggedin_device'),
			'param' => 's',
			'getData' => true
		));

		$this->returnMethod['success'] = ($getLoggedInDevice !== 'ZERO_DATA' && !is_null($getLoggedInDevice));
		$this->returnMethod['message'] = ($this->returnMethod['success']) ? 'Data found!' : 'Data not found.';
		$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
		if($_return && $this->returnMethod['success']) {
			$this->returnMethod['data'] = $getLoggedInDevice; 
		} else {
			unset($this->returnMethod['data']);
		}
		$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
		$this->returnMethod['errcode'] = ($this->returnMethod['success']) ? self::X_DATA_FOUND : self::X_NO_DATA_FOUND;
		unset($this->returnMethod['errors']);
		return $this->returnMethod;
	}

	/**
	 * Fungsi untuk membuat/memperbaharui data login device singkat-nya
	 * ini membuat baris pada database tabel '[prefix]_loggedin_device'
	 * dengan data yang di input, ini digunakan untuk mencatat login
	 * device user yang sedang login, diperangkat lain
	 * 
	 * @param array $data		Data login (key-value pairs), isi sesuai dengan nama KOLOM TABEL dalam bentuk associative-arrays. Contoh: $data = [ 'NIK' => 'xxxxxxxx',  => 'TOKEN', ... ];
	 * @param string $mode		Mode yang di pilih (add/edit)
	 * @param string $return	(Opsional untuk mode: add|new|create|make) mengembalikan nilai TOKEN jika sukses menambahkan login
	 * 
	 * @return boolean			TRUE | FALSE
	 */
	public function setLoggedIn() {
		$startTime = floor(microtime(true)*1000);
		$_data = $_mode = null;
		$_return = false;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData (draft)
		$_args = func_get_args();
		if(func_num_args() <= 3) {
			foreach($_args as $idx => $val) {
				if(is_array($val) && isAssoc($val)) $_data = $val;
				if(is_string($val) && preg_match('/\b(add|new|create|make|update|edit|modify|change)\b/', strtolower($val))) $_mode = strtolower($val);
				if(is_bool($val)) $_return = $val;
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// #checkingData
		if(is_null($_data) || empty($_data)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'data' untuk user(device) tidak ditemukan pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => sprintf("Pada saat melakukan pemeriksaan 'data' user, parameter pada Method '%s(%s)' tidak di temukan. tolong harap periksa kembali lagi", end(explode('\\', __METHOD__)), __LINE__),'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_data)), 'user-error', true); /* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' no data found.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}
		if (is_null($_mode) || empty($_mode)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'mode' untuk user(device) tidak ditemukan pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => sprintf("Pada saat melakukan pemeriksaan 'mode' user, parameter pada Method '%s(%s)' tidak di temukan. tolong harap periksa kembali lagi", end(explode('\\', __METHOD__)), __LINE__),'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_data, $_mode)), 'user-error', true); /* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' no data found.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}
		if (is_null($_return) || !is_bool($_return)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'return' untuk user(device) tidak ditemukan pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => sprintf("Pada saat melakukan pemeriksaan 'return' user, parameter pada Method '%s(%s)' tidak di temukan. tolong harap periksa kembali lagi", end(explode('\\', __METHOD__)), __LINE__),'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_data, $_mode, $_return)), 'user-error', true); /* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' no data found.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}

		// #re-prepareData (draft-2)
		$dataRequired = array(
			'NIK' => $_data['NIK'] ?? null,
			'TOKEN' => $_data['TOKEN'] ?? null // important part (must fill)
		);
		$dataTest = array_merge($dataRequired, array_intersect_key($_data, $dataRequired));
		$isDataEmpty = false;
		foreach($dataTest as $k => $v) {
			if($k === 'TOKEN') {
				if(!preg_match('/\b(add|new|create|make)\b/', $_mode) && isEmptyVar($v)) {
					$isDataEmpty = true;
					$this->returnMethod['errors'][__FUNCTION__][] = sprintf("Error data '%s' nilai tidak ada, tolong isi!", $k);
				}
			}
		}
		if($isDataEmpty) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'data' user(device) yang di serahkan, tidak di isi pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => "Pada saat proses melakukan Validasi 'data', salah satu dari data wajib/mandatory seperti 'NIK' atau 'TOKEN' bernilai kosong",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_data, $_mode, $_return)), 'user-error', true);/* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' currently 'data' passed from params is empty.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			// unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}
		$dataPrepare = $dataTest;

		// Memeriksa data, jika tidak ada kesalahan mengembalikan nilai 'true' jika tidak 'false'
		$status = null;
		if(preg_match('/\b(add|new|create|make)\b/', $_mode)) {
			if(!is_null($dataPrepare['NIK']) && !empty($dataPrepare['NIK'])) {
				$countLogins = $this->countLogins($dataPrepare['NIK']);
				$countLogins = ($countLogins['success']) ? $countLogins['data'] : 0;
				$dataPrepare['CURRENT_ACTIVE'] = ((int) $countLogins === 0) ? 1 : 0;
			}
			// #re-prepareData (release)
			$agent = new Agent();

			if(is_null($dataPrepare['TOKEN']) || !empty($dataPrepare['TOKEN'])) unset($dataPrepare['TOKEN']);
			$uuid = Uuid::uuid6(); // V6: Reordered Time
			$data = array(
				'NIK' => null,
				'TOKEN' => $uuid->toString(), // important part (primary key)
				'IP_ADDRESS' => (string) getUserIP(),
				'DEVICE_NAME' => $agent->device(),
				'DEVICE_OS' => $agent->platform(),
				'DEVICE_TYPE' => $agent->isDesktop() ? 'Desktop' : 'Phone',
				'BROWSER_NAME' => $agent->browser(),
				'BROWSER_VERSION' => (string) $agent->version($agent->browser()),
				'LAST_ACTIVITY' => (string) time(),
				'CURRENT_ACTIVE' => 0,
				'FLAG_LOGOUT' => 0,
				'HAD_PROCESS' => 0
			);
			$data = array_merge($data, $dataPrepare);
			if(is_null($data['NIK']) || empty($data['NIK'])) unset($data['NIK']);
			else $data['NIK'] = (string) $data['NIK'];

			$dataColumn = implode(', ', array_keys($data));
			$dataValues = array_values($data);
			$dataMarked = rtrim(str_repeat('?, ', count($data)), ', ');
			//$dataBinded = str_repeat('s', count($data));
			$dataBinded = '';
			foreach($data as $k => $v) {
				if(is_bool($v) || is_int($v)) { $dataBinded .= 'i'; }
				else if(is_string($v)) { $dataBinded .= 's'; }
				else { $dataBinded .= 's'; }
			}

			// Add new login device
			// tb -> [prefix]_loggedin_device
			$status = db_runQuery([
				'config_array' => $this->initConfig['dbms'],
				'database_index' => $this->initConfig['dbms']['mysql_selected']['db'],
				'input' => $dataValues,
				'query' => sprintf('INSERT INTO %s (%s) VALUES (%s);', $this->appConfig['CORE']['tb_prefix'].'loggedin_device', $dataColumn, $dataMarked),
				'param' => $dataBinded,
				'getData' => false
			]);

			//if (!$status) {
			//	$this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
			//	reportLog("User::setLoggedin(".__LINE__."): Error pada 'mode: add|run: db_runQuery' mendapat nilai kembali 'false'!", 411);
			//	header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
			//	exit();
			//}

			// Return data-user TOKEN, if 'return' set to true, only for mode 'add|new|create|make'
			if($status) {
				$listLoginDevices = $this->getLoginData($data['TOKEN'], true);
				if($listLoginDevices['success'] && count($listLoginDevices['data']) >= 1) {
					$countChecked = $countNotActive = 0;
					foreach($listLoginDevices['data'] as $perLoggedIn) {
						if($perLoggedIn['CURRENT_ACTIVE'] == 0) $countNotActive++;
						$countChecked++;
					}
					if($countChecked === $countNotActive) {
						$this->setLoginStatus($data['TOKEN'], 'active', true);
					}
				}
				if($_return) {
					$this->returnMethod['data'] = $data['TOKEN'];
				}
			}
		} else if(preg_match('/\b(update|edit|modify|change)\b/', $_mode)) {
			// #re-prepareData (release)
			$token = null;
			if(is_null($dataPrepare['NIK']) || !empty($dataPrepare['NIK'])) unset($dataPrepare['NIK']);
			$data = array(
				'IP_ADDRESS' => (string) getUserIP() ?? '127.0.0.1',
				'LAST_ACTIVITY' => (string) time()
			);
			$data = array_merge($data, $dataPrepare);
			if(!is_null($data['TOKEN']) && !empty($data['TOKEN'])) $token = $data['TOKEN'];
			if(!is_null($token) && !empty($token)) unset($data['TOKEN']);

			$checkLoggedIn = $this->hasLoggedIn($token);
			if(!is_null($token) && $checkLoggedIn['success']) {
				$dataColumn = implode(', ', array_map(function($name) { return $name.' = ?'; }, array_keys($data)));
				$dataValues = array_merge(array_values($data), array($token));
				$dataMarked = rtrim(str_repeat('?, ', count($data)), ', ');
				//$dataBinded = str_repeat('s', count($data));
				$dataBinded = '';
				foreach($data as $k => $v) {
					if(is_bool($v) || is_int($v)) { $dataBinded .= 'i'; }
					else if(is_string($v)) { $dataBinded .= 's'; }
					else { $dataBinded .= 's'; }
				}

				// Update selected login device
				// tb -> crm_loggedin_device
				$status = db_runQuery(array(
					'config_array' => $this->initConfig['dbms'],
					'database_index' => $this->initConfig['dbms']['mysql_selected']['db'],
					'input' => $dataValues,
					'query' => sprintf('UPDATE %s SET %s WHERE TOKEN = ?;', $this->appConfig['CORE']['tb_prefix'].'loggedin_device', $dataColumn),
					'param' => $dataBinded.'s'
				));
			} else {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'mode: update|method: User::hasLoggedIn' mendapat nilai kembali 'false' atau 'token' bernilai null, pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => "Pada saat melakukan pemeriksaan 'token' user, salah satu dari data wajib/mandatory seperti 'TOKEN' bernilai kosong",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_data, $_mode, $_return)), 'user-error', true);/* STOP_HIDE */
				$this->returnMethod['success'] = false;
				$this->returnMethod['message'] = sprintf("Error on '%s(%s)' currently 'token' passed from params/method 'User::hasLoggedIn' is empty.", end(explode('\\', __METHOD__)), __LINE__);
				$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
				unset($this->returnMethod['data']);
				$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
				$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
				unset($this->returnMethod['errors']);
				return $this->returnMethod;
			}
		} else {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'mode: %s' yang dipilih invalid, pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => "Pada saat melakukan pemeriksaan 'mode', nilai di dapati tidak cocok atau invalid.",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_data, $_mode, $_return)), 'user-error', true);/* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' currently 'mode' passed from params is invalid.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}

		$this->returnMethod['success'] = ($status);
		$this->returnMethod['message'] = ($status) ? 'Success!' : 'Failed.';
		$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
		if(!$_return) {
			unset($this->returnMethod['data']);
		}
		$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
		$this->returnMethod['errcode'] = ($status) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED;
		unset($this->returnMethod['errors']);
		return $this->returnMethod;
	}

	/**
	 * Fungsi untuk menghapus login dari user singkat-nya
	 * ini menghapus login device yang telah dibuat dengan fungsi
	 * 'setLoggedIn'
	 * 
	 * @param array $token	Token User yang dicari
	 * 
	 * @return boolean		TRUE | FALSE
	 */
	public function closeLogin() {
		$startTime = floor(microtime(true)*1000);
		$_identity = null;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData (draft)
		$_args = func_get_args();
		if(func_num_args() >= 1) {
			foreach($_args as $idx => $val) {
				if(is_string($val)) $_identity = $val;
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// #checkingData
		if(is_null($_identity) || empty($_identity)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'token' user(device) yang di serahkan, tidak di isi pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => "Pada saat proses melakukan Validasi 'token', salah satu dari data wajib/mandatory seperti 'TOKEN' bernilai kosong",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => $_identity), 'user-error', true);/* STOP_HIDE */
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' currently 'token' passed from params is empty.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}

		// Memeriksa data, jika tidak ada kesalahan mengembalikan nilai 'true (+data)' jika tidak 'false'
		$removeLoginDevice = db_runQuery(array(
			'config_array' => $this->initConfig['dbms'],
			'database_index' => $this->initConfig['dbms']['mysql_selected']['db'],
			'input' => $_identity,
			'query' => sprintf('DELETE FROM %s WHERE TOKEN = ?;', $this->appConfig['CORE']['tb_prefix'].'loggedin_device'),
			'param' => 's'
		));

		$this->returnMethod['success'] = ($removeLoginDevice);
		$this->returnMethod['message'] = ($removeLoginDevice) ? 'Success!' : 'Failed.';
		$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
		unset($this->returnMethod['data']);
		$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
		$this->returnMethod['errcode'] = ($removeLoginDevice) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED;
		unset($this->returnMethod['errors']);
		return $this->returnMethod;
	}

	/**
	 * Fungsi untuk merubah status pada login singkat-nya
	 * ini memperbaharui tabel pada spesifik kolom, yaitu:
	 * - 'CURRENT_ACTIVE'	alias 'active'
	 * - 'FLAG_LOGOUT'		alias 'logout'
	 * - 'HAD_PROCESS'		alias 'process'
	 * dan untuk nilainya, 0 = FALSE | 1 = TRUE
	 * 
	 * @param string $token		Token User yang dicari
	 * @param string $status	Status yang di pilih
	 * @param boolean $data		Data, yaitu TRUE/FALSE
	 * atau, single param
	 * @param array $all		Contoh param: $all = [ 'token' => 'xxxxxxxx', 'status' => 'active', 'data' => false ];
	 * 
	 * @return boolean			TRUE | FALSE
	 */
	public function setLoginStatus() {
		$startTime = floor(microtime(true)*1000);
		$tblColumnName = [
			'active' => 'CURRENT_ACTIVE',
			'logout' => 'FLAG_LOGOUT',
			'process' => 'HAD_PROCESS'
		];
		$_identity = $_status = $_data = null;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData
		$_args = func_get_args();
		if(func_num_args() <= 3) {
			foreach($_args as $idx => $val) {
				if(func_num_args() == 1 && isAssoc($val)) {
					foreach($val as $k => $v) {
						switch(strtolower($k)) {
							case 'token': $_identity = $v; break;
							case 'status':
								$lV = strtoLower($v);
								$_status = (in_array($lV, array('active', 'logout', 'process'))) ? $lV : null;
							break;
							case 'data': $_data = (boolval($v)) ? 1 : 0; break;
							default: break;
						}
					}
				} else {
					if(is_string($val)) {
						$lVal = strtoLower($val);
						if(in_array($lVal, array('active', 'logout', 'process'))) { $_status = $lVal; }
						else { $_identity = $val; }
					}
					if(is_bool($val)) { $_data = ($val == true) ? 1 : 0; }
				}
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// #checkingData
		if(is_null($_identity) || empty($_identity)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'token' user(device) yang di serahkan, tidak di isi pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => "Pada saat proses melakukan Validasi 'token', salah satu dari data wajib/mandatory seperti 'TOKEN' bernilai kosong",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => $_identity), 'user-error', true);/* STOP_HIDE */
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' currently 'token' passed from params is empty.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}
		if(is_null($_status) || empty($_status)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'status' untuk user(device) tidak ditemukan pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => sprintf("Pada saat proses Validasi 'status', parameter pada Method '%s(%s)' tidak di temukan. tolong harap periksa kembali lagi", end(explode('\\', __METHOD__)), __LINE__),'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__, 'data' => array($_identity, $_status)), 'user-error', true); /* STOP_HIDE */
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' currently 'status' passed from params is empty.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}
		if(is_null($_data)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'data' user(device) yang di serahkan, tidak di isi pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => "Pada saat proses melakukan Validasi 'data', bukan bernilai TRUE/FALSE",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_identity, $_status, $_data)), 'user-error', true);/* STOP_HIDE */
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' currently 'data' passed from params is invalid.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}

		// Memeriksa data, jika tidak ada kesalahan mengembalikan nilai 'true' jika tidak 'false'
		$getStatus = db_runQuery(array(
			'config_array' => $this->initConfig['dbms'],
			'database_index' => $this->initConfig['dbms']['mysql_selected']['db'],
			'input' => array($_identity),
			'query' => sprintf('SELECT NIK FROM %s WHERE TOKEN = ?;', $this->appConfig['CORE']['tb_prefix'].'loggedin_device'),
			'param' => 's',
			'getData' => true
		));

		$status = false;
		if ($getStatus !== 'ZERO_DATA' && isset($getStatus['NIK'])) {
			// Reset active sessions to all row of speciic NIK
			if ($_status === 'active') {
				$_status = $tblColumnName[$_status];
				$resetActiveStatus = db_runQuery(array(
					'config_array' => $this->initConfig['dbms'],
					'database_index' => $this->initConfig['dbms']['mysql_selected']['db'],
					'input' => array(0, $getStatus['NIK']),
					'query' => sprintf('UPDATE %s SET %s = ? WHERE NIK = ?;', $this->appConfig['CORE']['tb_prefix'].'loggedin_device', $_status),
					'param' => 'is'
				));
				if (!$resetActiveStatus) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'resetActiveStatus' untuk user(device) gagal pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => sprintf("Pada saat proses Eksekusi 'resetActiveStatus', gagal pada saat menjalankan kueri dan mengembalikan nilai FALSE!", end(explode('\\', __METHOD__)), __LINE__),'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__, 'data' => array($_identity, $_status, $_data)), 'user-error', true); /* STOP_HIDE */
					$this->returnMethod['success'] = false;
					$this->returnMethod['message'] = sprintf("Error on '%s(%s)' execute 'resetActiveStatus' failed.", end(explode('\\', __METHOD__)), __LINE__);
					$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
					unset($this->returnMethod['data']);
					$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$this->returnMethod['errcode'] = self::X_EXECUTE_FAILED;
					unset($this->returnMethod['errors']);
					return $this->returnMethod;
				}
			} else { $_status = $tblColumnName[$_status]; }

			// Update status login
			$status = db_runQuery(array(
				'config_array' => $this->initConfig['dbms'],
				'database_index' => $this->initConfig['dbms']['mysql_selected']['db'],
				'input' => array($_data, $_identity),
				'query' => sprintf('UPDATE %s SET %s = ? WHERE TOKEN = ?;', $this->appConfig['CORE']['tb_prefix'].'loggedin_device', $_status),
				'param' => 'is'
			));
		}

		$this->returnMethod['success'] = ($status);
		$this->returnMethod['message'] = ($status) ? 'Success!' : 'Failed.';
		$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
		unset($this->returnMethod['data']);
		$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
		$this->returnMethod['errcode'] = ($status) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED;
		unset($this->returnMethod['errors']);
		return $this->returnMethod;
	}

	/**
	 * Fungsi untuk memeriksa apakah login status mengembalikan nilai TRUE, singkat-nya
	 * ini memeriksa apakah status yang dipilih dalam kondisi TRUE atau FALSE
	 * - 'CURRENT_ACTIVE'	alias 'active'
	 * - 'FLAG_LOGOUT'		alias 'logout'
	 * - 'HAD_PROCESS'		alias 'process'
	 * 
	 * @param string $token		Token User yang dicari
	 * @param string $status	Status yang di cari, <active|logout|process>
	 * 
	 * atau, single param
	 * 
	 * @param array $all		Contoh param: $all = [ 'token' => 'xxxxxxxx', 'status' => 'active' ];
	 * 
	 * @return boolean			TRUE | FALSE
	 */
	public function isLoginStatus() {
		$startTime = floor(microtime(true)*1000);
		$tblColumnName = [
			'active' => 'CURRENT_ACTIVE',
			'logout' => 'FLAG_LOGOUT',
			'process' => 'HAD_PROCESS'
		];
		$_identity = $_status = null;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData
		$_args = func_get_args();
		if(func_num_args() <= 2) {
			foreach ($_args as $idx => $val) {
				if(func_num_args() == 1 && isAssoc($val)) {
					foreach($val as $k => $v) {
						switch(strtolower($k)) {
							case 'token': $_identity = $v; break;
							case 'status':
								$lV = strtolower($v);
								$_status = (in_array($lV, array('active', 'logout', 'process'))) ? $lV : null;
							break;
							default: break;
						}
					}
				} else {
					if(is_string($val)) {
						$lVal = strtolower($val);
						if(in_array($lVal, array('active', 'logout', 'process'))) { $_status = $lVal; }
						else { $_identity = $val; }
					}
				}
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// #checkingData
		if(is_null($_identity) || empty($_identity)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'token' user(device) yang di serahkan, tidak di isi pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => "Pada saat proses melakukan Validasi 'token', salah satu dari data wajib/mandatory seperti 'TOKEN' bernilai kosong",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => $_identity), 'user-error', true);/* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' currently 'token' passed from params is empty.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}
		if(is_null($_status) || empty($_status)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'status' untuk user(device) tidak ditemukan pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => sprintf("Pada saat proses Validasi 'status', parameter pada Method '%s(%s)' tidak di temukan. tolong harap periksa kembali lagi", end(explode('\\', __METHOD__)), __LINE__),'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__, 'data' => array($_identity, $_status)), 'user-error', true); /* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' currently 'status' passed from params is empty.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		} else {
			$_status = $tblColumnName[$_status];
		}

		// Memeriksa data, jika tidak ada kesalahan mengembalikan nilai 'true' jika tidak 'false'
		if($this->hasLoggedIn($_identity)['success']) {
			// Check status
			$checkStatus = db_runQuery(array(
				'config_array' => $this->initConfig['dbms'],
				'database_index' => $this->initConfig['dbms']['mysql_selected']['db'],
				'input' => $_identity,
				'query' => sprintf('SELECT %s FROM %s WHERE TOKEN = ?;', $_status, $this->appConfig['CORE']['tb_prefix'].'loggedin_device'),
				'param' => 's',
				'getData' => true
			));

			if($checkStatus !== 'ZERO_DATA' && isset($checkStatus[$_status])) {
				$this->returnMethod['success'] = true;
				$this->returnMethod['data'] = boolval($checkStatus[$_status]);
			} else {
				$this->returnMethod['success'] = false;
				unset($this->returnMethod['data']);
			}
		}

		$this->returnMethod['message'] = ($this->returnMethod['success']) ? 'Success!' : 'Failed.';
		$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
		$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
		$this->returnMethod['errcode'] = ($this->returnMethod['success']) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED;
		unset($this->returnMethod['errors']);
		return $this->returnMethod;
	}

	/**
	 * Fungsi untuk mengambil data login user berdasarkan NIK/TOKEN, singkat-nya
	 * ini mencari login device user dalam database tabel '[prefix]_loggedin_device'
	 * jika ada, mengambil dan mengembalikan nilai data login. jika tidak FALSE
	 * 
	 * @param string $token		Token User yang dicari
	 * @param boolean $return	(Opsional: true|false) jika ingin mendapatkan semua data NIK dari TOKEN tersebut
	 * 
	 * @return boolean			TRUE | FALSE
	 */
	public function getLoginData() {
		$startTime = floor(microtime(true)*1000);
		$_identity = null;
		$_return = false;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData
		$_args = func_get_args();
		if (func_num_args() >= 1 && func_num_args() <= 2) {
			foreach ($_args as $idx => $val) {
				if (is_string($val)) $_identity = $val;
				if (is_bool($val)) $_return = boolval($val);
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// #prepareData
		if (is_null($_identity) || empty($_identity)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'token' user(device) yang di serahkan, tidak di isi pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => "Pada saat proses melakukan Validasi 'token', salah satu dari data wajib/mandatory seperti 'NIK' atau 'TOKEN' bernilai kosong",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_identity, $_return)), 'user-error', true);/* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' currently 'token' passed from params is empty.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}
		if (is_null($_return) && !is_bool($_return)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'return' user(device) yang di serahkan, tidak di isi pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => "Pada saat proses melakukan Validasi 'return', bukan bernilai TRUE/FALSE",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_identity, $_return)), 'user-error', true);/* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' currently 'return' passed from params is invalid.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}

		// Memeriksa data, jika tidak ada kesalahan mengembalikan nilai 'TRUE (+data)' jika tidak 'FALSE'
		$hasData = $this->hasLoggedIn($_identity, true);
		if ($hasData['success']) {
			$allData = null;
			if ($_return) {
				$allData = db_runQuery(array(
					'config_array' => $this->initConfig['dbms'],
					'database_index' => $this->initConfig['dbms']['mysql_selected']['db'],
					'input' => $hasData['data']['NIK'],
					'query' => sprintf('SELECT * FROM %s WHERE NIK = ?;', $this->appConfig['CORE']['tb_prefix'] . 'loggedin_device'),
					'param' => 's',
					'getData' => true,
					'getAllRow' => true
				));
			}

			$this->returnMethod['success'] = true;
			$this->returnMethod['message'] = 'Data found!';
			$this->returnMethod['data'] = ($allData !== 'ZERO_DATA' && !is_null($allData)) ? $allData : $hasData;
			$this->returnMethod['errcode'] = self::X_DATA_FOUND;
		} else {
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = 'Data not found.';
			unset($this->returnMethod['data']);
			$this->returnMethod['errcode'] = self::X_NO_DATA_FOUND;
		}
		$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
		$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
		unset($this->returnMethod['errors']);
		return $this->returnMethod;
	}

	/**
	 * Fungsi untuk memeriksa apakah user tidak ada aktifitas, singkat-nya
	 * ini membandingkan batas waktu sesi user dengan waktu sekarang, jika
	 * waktu sekarang lebih besar dari batas waktu sesi user, maka tidak ada
	 * aktifitas selama kurun waktu tersebut. (disarankan user untuk logout)
	 * 
	 * @param string $token		Token User yang dicari
	 * 
	 * @return boolean			TRUE | FALSE
	 */
	public function checkInactivity() {
		$startTime = floor(microtime(true)*1000);
		$_identity = null;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData
		$_args = func_get_args();
		if(func_num_args() >= 1) {
			foreach($_args as $idx => $val) {
				if(is_string($val)) $_identity = $val;
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// #checkingData
		if(is_null($_identity) || empty($_identity)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'token' user(device) yang di serahkan, tidak di isi pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => "Pada saat proses melakukan Validasi 'token', salah satu dari data wajib/mandatory seperti 'TOKEN' bernilai kosong",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_identity)), 'user-error', true);/* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' currently 'token' passed from params is empty.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}

		// Memeriksa data, jika tidak ada kesalahan mengembalikan nilai 'true' jika tidak 'false'
		$checkLoggedIn = $this->hasLoggedIn($_identity, true);
		if ($checkLoggedIn['success'] && isset($checkLoggedIn['data']['NIK'])) {
			$lastActivity = $checkLoggedIn['data']['LAST_ACTIVITY'];
			$this->returnMethod['success'] = !($lastActivity < (time() - $this->appConfig['CORE']['session_user']['expire_time']));
			$this->returnMethod['message'] = ($this->returnMethod['success']) ? 'Session is in Active.' : 'Session is Expired!';
			$this->returnMethod['errcode'] = ($this->returnMethod['success']) ? self::SESSION_ACTIVE : self::SESSION_EXPIRED;
		} else {
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = 'Data not found.';
			$this->returnMethod['errcode'] = self::X_NO_DATA_FOUND;
		}
		unset($this->returnMethod['data']);
		$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
		$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
		unset($this->returnMethod['errors']);
		return $this->returnMethod;
	}

	// SESSION MANAGEMENT //
	/**
	 * Fungsi untuk membuat/memperbaharui data session singkat-nya
	 * ini membuat sesi untuk user setelah login, ataupun mencatat 
	 * data untuk terus di pakai kembali
	 * 
	 * @param string $key		Key pairs, nama untuk variabel data
	 * @param mixed $value		Value pairs, nilai untuk variabel data
	 * @param string $mode		Mode yang di pilih (add/edit)
	 * atau, single param
	 * @param array $all		Contoh param: $all = [ 'key1' => 'value1', 'key2' => 'value2', ... ];
	 * @param string $mode		Mode yang di pilih (add/edit)
	 * 
	 * @return boolean			TRUE | FALSE
	 */
	public function setSession() {
		$startTime = floor(microtime(true)*1000);
		$_data = array();
		$_key = $_val = $_all = $_mode = null;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData (draft)
		$_args = func_get_args();
		if(func_num_args() <= 3) {
			if(func_num_args() >= 3) {
				$isKeyFilled = false;
				foreach($_args as $idx => $val) {
					if(is_string($val) && preg_match('/\b(add|new|create|make|update|edit|modify|change)\b/', strtolower($val))) {
						$_mode = strtolower($val);
					} else if(is_string($val) && $isKeyFilled == false) {
						$_key = $val;
						$isKeyFilled = true;
					} else {
						$_val = $val;
					}
					unset($_args[$idx]);
				}
			}
			if(func_num_args() == 2) {
				foreach($_args as $idx => $val) {
					if(is_array($val) && isAssoc($val)) $_all = $val; 
					if(is_string($val) && preg_match('/\b(add|new|create|make|update|edit|modify|change)\b/', strtolower($val))) $_mode = strtolower($val);
					unset($_args[$idx]);
				}
			}
			unset($_args);
		}

		// #checkingData
		$isDataExist = array(
			'KVPairs' => array(
				'key' => false,
				'val' => false
			),
			'KVAssoc' => false
		);
		if(is_null($_key) || empty($_key)) {
			$isDataExist['KVPairs']['key'] = false;
			$this->returnMethod['errors'][__FUNCTION__][] = sprintf("Error parameter 'key' nilai tidak ada, tolong isi!");
		} else {
			$isDataExist['KVPairs']['key'] = true;
		}
		if(is_null($_val) || empty($_val)) {
			$isDataExist['KVPairs']['val'] = false;
			$this->returnMethod['errors'][__FUNCTION__][] = sprintf("Error parameter 'val' nilai tidak ada, tolong isi!");
		} else {
			$isDataExist['KVPairs']['val'] = true;
		}
		if(is_null($_all) || empty($_all)) {
			$isDataExist['KVAssoc'] = false;
			$this->returnMethod['errors'][__FUNCTION__][] = sprintf("Error parameter 'all' nilai tidak ada, tolong isi!");
		} else {
			$isDataExist['KVAssoc'] = true;
		}
		if(($isDataExist['KVPairs']['key'] === false && $isDataExist['KVPairs']['val'] === false) && $isDataExist['KVAssoc'] === false) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'data' untuk user(session) tidak ditemukan pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => sprintf("Pada saat melakukan pemeriksaan 'data', parameter pada Method '%s(%s)' tidak di temukan. tolong harap periksa kembali lagi", end(explode('\\', __METHOD__)), __LINE__),'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array(array('key' => $_key, 'val' => $_val), $_all, $_mode)), 'user-error', true); /* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' no data found.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			// unset($this->returnMethod['errors']);
			return $this->returnMethod;
		} else {
			if($isDataExist['KVAssoc'] === false) {
				if(!($isDataExist['KVPairs']['key'] === true && $isDataExist['KVPairs']['val'] === true)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'data' untuk user(session) tidak ditemukan pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => sprintf("Pada saat melakukan pemeriksaan 'data', parameter pada Method '%s(%s)' tidak di temukan. salah satu dari KEY/VAL pada parameter kosong atau tidak di cantumkan nilai nya, tolong harap periksa kembali lagi", end(explode('\\', __METHOD__)), __LINE__),'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array(array('key' => $_key, 'val' => $_val), $_mode)), 'user-error', true); /* STOP_HIDE */
					$this->returnMethod['success'] = false;
					$this->returnMethod['message'] = sprintf("Error on '%s(%s)' no data found.", end(explode('\\', __METHOD__)), __LINE__);
					$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
					unset($this->returnMethod['data']);
					$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
					// unset($this->returnMethod['errors']);
					return $this->returnMethod;
					// Generate Exception error
				} else {
					$_data[$_key] = $_val;
					$isDataExist = true;
					unset($this->returnMethod['errors'][__FUNCTION__]);
				}
			}
			if(!(is_bool($isDataExist) && $isDataExist === true)) {
				if($isDataExist['KVAssoc'] === false) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'data' untuk user(session) tidak ditemukan pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => sprintf("Pada saat melakukan pemeriksaan 'data', parameter pada Method '%s(%s)' tidak di temukan. tolong harap periksa kembali lagi", end(explode('\\', __METHOD__)), __LINE__),'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_all, $_mode)), 'user-error', true); /* STOP_HIDE */
					$this->returnMethod['success'] = false;
					$this->returnMethod['message'] = sprintf("Error on '%s(%s)' no data found.", end(explode('\\', __METHOD__)), __LINE__);
					$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
					unset($this->returnMethod['data']);
					$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
					// unset($this->returnMethod['errors']);
					return $this->returnMethod;
				} else {
					$_data = $_all;
					$isDataExist = true;
					unset($this->returnMethod['errors'][__FUNCTION__]);
				}
			}
		}
		if(is_null($_mode) || empty($_mode)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'mode' untuk user(session) tidak ditemukan pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => sprintf("Pada saat melakukan pemeriksaan 'mode', parameter pada Method '%s(%s)' tidak di temukan. tolong harap periksa kembali lagi", end(explode('\\', __METHOD__)), __LINE__),'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_data, $_mode)), 'user-error', true); /* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' no data found.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}

		// Proses data, jika tidak ada kesalahan mengembalikan nilai 'true' jika tidak 'false'
		if(preg_match('/\b(add|new|create|make)\b/', $_mode)) {
			$sessionMethod = strtolower(trim($this->initConfig['session']['method']));
			switch ($sessionMethod) {
				case 'php': default:
					if(session_status() === PHP_SESSION_NONE) { session_start(array('cookie_lifetime' => 0, 'cookie_path' => '/', 'cookie_httponly' => '1')); }
					if(is_array($_data) && count($_data) >= 1) {
						$totalKey = count(array_keys($_data));
						$countKey = 0;
						foreach($_data as $keySession => $valSession) {
							if(!array_key_exists($this->initConfig['session']['prefix'].$keySession, $_SESSION)) {
								$_SESSION[$this->initConfig['session']['prefix'].$keySession] = $valSession;
								$this->registerSessionKey($this->initConfig['session']['prefix'].$keySession);
								$countKey += 1;
							} else {
								$this->returnMethod['errors'][__FUNCTION__][] = sprintf("Skipped, session-key '%s' already exist!", $keySession);
							}
						}
						$this->returnMethod['success'] = ($countKey >= 1);
						$this->returnMethod['message'] = ($totalKey == $countKey) ? 'Session is fully set!' : (($this->returnMethod['success']) ? 'Sessions are partially created, some already exist.': 'Session failed to create!');
						$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
						unset($this->returnMethod['data']);
						$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$this->returnMethod['errcode'] = ($this->returnMethod['success']) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED;
						if ($totalKey === $countKey) {
							unset($this->returnMethod['errors']);
						}
					}
				break;
				case 'jwt':
					$jwt = null;
					$payload = array();
					$sessionName = 'X-ARE-SESSION';
					if(isset($_COOKIE[$sessionName])) {
						$jwt = $_COOKIE[$sessionName];
						if (!is_null($jwt) && !empty($jwt)) {
							try {
								$payload = (array) JWT::decode($jwt, new JWTKey($this->appConfig['CORE']['app_private_key']['jwt']['value'], 'HS256'));
							} catch(Exception $exception) {
								$payload = array();
								setcookie($sessionName, 'INVALID', time()-3600, '/');
								unset($_COOKIE[$sessionName]);
								setcookie($sessionName, '', time()-3600, '/');
							}
						}
					}
					if(is_array($_data) && count($_data) >= 1) {
						$totalKey = count(array_keys($_data));
						$countKey = 0;
						foreach($_data as $keySession => $valSession) {
							if(!array_key_exists($this->initConfig['session']['prefix'].$keySession, $payload)) {
								$payload[$this->initConfig['session']['prefix'].$keySession] = $valSession;
								$countKey += 1;
							} else {
								$this->returnMethod['errors'][__FUNCTION__][] = sprintf("Skipped, session-key '%s' already exist!", $keySession);
							}
						}
						$this->returnMethod['success'] = ($countKey >= 1);
						$this->returnMethod['message'] = ($totalKey == $countKey) ? 'Session is fully set!' : (($this->returnMethod['success']) ? 'Sessions are partially created, some already exist.': 'Session failed to create!');
						$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
						unset($this->returnMethod['data']);
						$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$this->returnMethod['errcode'] = ($this->returnMethod['success']) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED;
						if ($totalKey === $countKey) {
							unset($this->returnMethod['errors']);
						}
						if ($this->returnMethod['success']) {
							$jwt = JWT::encode($payload, $this->appConfig['CORE']['app_private_key']['jwt']['value'], 'HS256');
							setcookie($sessionName, $jwt, 0, '/');
						}
					}
				break;
			}
		} else if(preg_match('/\b(update|edit|modify|change)\b/', $_mode)) {
			$sessionMethod = strtolower(trim($this->initConfig['session']['method']));
			switch ($sessionMethod) {
				case 'php': default:
					if(session_status() === PHP_SESSION_NONE) { session_start(array('cookie_lifetime' => 0, 'cookie_path' => '/', 'cookie_httponly' => '1')); }
					if(is_array($_data) && count($_data) >= 1) {
						$totalKey = count(array_keys($_data));
						$countKey = 0;
						foreach($_data as $keySession => $valSession) {
							if(!array_key_exists($this->initConfig['session']['prefix'].$keySession, $_SESSION)) {
								$this->returnMethod['errors'][__FUNCTION__][] = sprintf("Creating new one, session-key '%s' not exist!", $keySession);
							}
							$_SESSION[$this->initConfig['session']['prefix'].$keySession] = $valSession;
							$countKey += 1;
						}
						$this->returnMethod['success'] = ($countKey >= 1);
						$this->returnMethod['message'] = ($totalKey == $countKey) ? 'Session is fully updated!' : (($this->returnMethod['success']) ? 'Sessions are partially created, some have been updated.': 'Session failed to update!');
						$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
						unset($this->returnMethod['data']);
						$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$this->returnMethod['errcode'] = ($this->returnMethod['success']) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED;
						if (!array_key_exists(__FUNCTION__, $this->returnMethod['errors'])) {
							unset($this->returnMethod['errors']);
						}
					}
				break;
				case 'jwt':
					$jwt = null;
					$payload = array();
					$sessionName = 'X-ARE-SESSION';
					if(isset($_COOKIE[$sessionName])) {
						$jwt = $_COOKIE[$sessionName];
						if (!is_null($jwt) && !empty($jwt)) {
							try {
								$payload = (array) JWT::decode($jwt, new JWTKey($this->appConfig['CORE']['app_private_key']['jwt']['value'], 'HS256'));
							} catch(Exception $exception) {
								setcookie($sessionName, 'INVALID', time()-3600, '/');
								unset($_COOKIE[$sessionName]);
								setcookie($sessionName, '', time()-3600, '/');
							}
						}
					}
					if(is_array($_data) && count($_data) >= 1) {
						$totalKey = count(array_keys($_data));
						$countKey = 0;
						foreach($_data as $keySession => $valSession) {
							if(!array_key_exists($this->initConfig['session']['prefix'].$keySession, $payload)) {
								$this->returnMethod['errors'][__FUNCTION__][] = sprintf("Creating new one, session-key '%s' not exist!", $keySession);
							}
							$payload[$this->initConfig['session']['prefix'].$keySession] = $valSession;
							$countKey += 1;
						}
						$this->returnMethod['success'] = ($countKey >= 1);
						$this->returnMethod['message'] = ($totalKey == $countKey) ? 'Session is fully updated!' : (($this->returnMethod['success']) ? 'Sessions are partially created, some have been updated.': 'Session failed to update!');
						$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
						unset($this->returnMethod['data']);
						$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$this->returnMethod['errcode'] = ($this->returnMethod['success']) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED;
						if ($this->returnMethod['success']) {
							$jwt = JWT::encode($payload, $this->appConfig['CORE']['app_private_key']['jwt']['value'], 'HS256');
							setcookie($sessionName, $jwt, 0, '/');
						}
					}
				break;
			}
		} else {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'mode: %s' yang dipilih invalid, pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => "Pada saat melakukan pemeriksaan 'mode', nilai di dapati tidak cocok atau invalid.",'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_data, $_mode)), 'user-error', true);/* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' currently 'mode' passed from params is invalid.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}

		return $this->returnMethod;
	}

	/**
	 * Fungsi untuk menghapus data session singkat-nya
	 * ini menghapus spesifik key sesi untuk user
	 * 
	 * @param string $key		Key pairs, nama untuk dicari dan dihapus
	 * 
	 * @return boolean			TRUE | FALSE
	 */
	public function unsetSession() {
		$startTime = floor(microtime(true)*1000);
		$_data = array();
		$_key = null;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData (draft)
		$_args = func_get_args();
		if(func_num_args() <= 2) {
			foreach($_args as $idx => $val) {
				if(is_string($val)) {
					$_key = $val;
				}
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// #checkingData
		if (is_null($_key) || empty($_key)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'key' untuk user(session) tidak ditemukan pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => sprintf("Pada saat melakukan pemeriksaan 'key', parameter pada Method '%s(%s)' tidak di temukan. tolong harap periksa kembali lagi", end(explode('\\', __METHOD__)), __LINE__),'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_key)), 'user-error', true); /* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' no data found.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}

		// Proses data, jika tidak ada kesalahan mengembalikan nilai 'true' jika tidak 'false'
		$status = false;
		$sessionMethod = strtolower(trim($this->initConfig['session']['method']));
		switch($sessionMethod) {
			case 'php': default:
				if(session_status() === PHP_SESSION_NONE) { session_start(array('cookie_lifetime' => 0, 'cookie_path' => '/', 'cookie_httponly' => '1')); }
				if(array_key_exists($this->initConfig['session']['prefix'].$_key, $_SESSION)) {
					unset($_SESSION[$this->initConfig['session']['prefix'].$_key]);
					if(!array_key_exists($this->initConfig['session']['prefix'].$_key, $_SESSION)) {
						$status = true;
					}
				}
				$this->returnMethod['success'] = $status;
				$this->returnMethod['message'] = ($status) ? 'Session is unset!' : sprintf("Session-key '%s' is not exist!", $_key);
				$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
				unset($this->returnMethod['data']);
				$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
				$this->returnMethod['errcode'] = ($status) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED;
				unset($this->returnMethod['errors']);
			break;
			case 'jwt':
				$jwt = null;
				$payload = array();
				$sessionName = 'X-ARE-SESSION';
				if(isset($_COOKIE[$sessionName])) {
					$jwt = $_COOKIE[$sessionName];
					if (!is_null($jwt) && !empty($jwt)) {
						try {
							$payload = (array) JWT::decode($jwt, new JWTKey($this->appConfig['CORE']['app_private_key']['jwt']['value'], 'HS256'));
						} catch(Exception $exception) {
							$payload = array();
							setcookie($sessionName, 'INVALID', time()-3600, '/');
							unset($_COOKIE[$sessionName]);
							setcookie($sessionName, '', time()-3600, '/');
						}
					}
				}
				if(is_array($payload) && count($payload) >= 1) {
					if(array_key_exists($this->initConfig['session']['prefix'].$_key, $payload)) {
						unset($payload[$this->initConfig['session']['prefix'].$_key]);
						if(!array_key_exists($this->initConfig['session']['prefix'].$_key, $payload)) {
							$status = true;
						}
					}
					$this->returnMethod['success'] = $status;
					$this->returnMethod['message'] = ($status) ? 'Session is unset!' : sprintf("Session-key '%s' is not exist!", $_key);
					$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
					unset($this->returnMethod['data']);
					$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$this->returnMethod['errcode'] = ($status) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED;
					unset($this->returnMethod['errors']);
					if ($this->returnMethod['success']) {
						$jwt = JWT::encode($payload, $this->appConfig['CORE']['app_private_key']['jwt']['value'], 'HS256');
						setcookie($sessionName, $jwt, 0, '/');
					}
				} else {
					setcookie($sessionName, 'INVALID', time()-3600, '/');
					unset($_COOKIE[$sessionName]);
					setcookie($sessionName, '', time()-3600, '/');
					$this->returnMethod['success'] = $status;
					$this->returnMethod['message'] = 'Session is empty!';
					$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
					unset($this->returnMethod['data']);
					$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$this->returnMethod['errcode'] = self::X_NO_DATA_FOUND;
					unset($this->returnMethod['errors']);
				}
			break;
		}

		return $this->returnMethod;
	}

	/**
	 * Fungsi untuk mengambil data session singkat-nya
	 * ini mengambil spesifik key sesi untuk kebutuhan
	 * penggunaan user dalam kondisi tertentu
	 * 
	 * @param string $key		Key pairs, nama untuk dicari dan diambil
	 * 
	 * @return boolean			TRUE | FALSE
	 */
	public function getSession() {
		$startTime = floor(microtime(true)*1000);
		$_data = array();
		$_key = null;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData (draft)
		$_args = func_get_args();
		if(func_num_args() <= 2) {
			foreach($_args as $idx => $val) {
				if(is_string($val)) {
					$_key = $val;
				}
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// #checkingData
		if (is_null($_key) || empty($_key)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'key' untuk user(session) tidak ditemukan pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => sprintf("Pada saat melakukan pemeriksaan 'key', parameter pada Method '%s(%s)' tidak di temukan. tolong harap periksa kembali lagi", end(explode('\\', __METHOD__)), __LINE__),'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_key)), 'user-error', true); /* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s(%s)' no data found.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}

		// Proses data, jika tidak ada kesalahan mengembalikan nilai 'true' jika tidak 'false'
		$status = false;
		$sessionMethod = strtolower(trim($this->initConfig['session']['method']));
		switch($sessionMethod) {
			case 'php': default:
				if(session_status() === PHP_SESSION_NONE) { session_start(array('cookie_lifetime' => 0, 'cookie_path' => '/', 'cookie_httponly' => '1')); }
				if(array_key_exists($this->initConfig['session']['prefix'].$_key, $_SESSION)) {
					$this->returnMethod['data'] = $_SESSION[$this->initConfig['session']['prefix'].$_key];
					$status = true;
				}
				$this->returnMethod['success'] = $status;
				$this->returnMethod['message'] = ($status) ? 'Session key data retrieved!' : sprintf("Session-key '%s' is not exist!", $_key);
				$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
				if (!$this->returnMethod['success']) {
					unset($this->returnMethod['data']);
				}
				$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
				$this->returnMethod['errcode'] = ($status) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED;
				unset($this->returnMethod['errors']);
			break;
			case 'jwt':
				$jwt = null;
				$payload = array();
				$sessionName = 'X-ARE-SESSION';
				if(isset($_COOKIE[$sessionName])) {
					$jwt = $_COOKIE[$sessionName];
					if (!is_null($jwt) && !empty($jwt)) {
						try {
							$payload = (array) JWT::decode($jwt, new JWTKey($this->appConfig['CORE']['app_private_key']['jwt']['value'], 'HS256'));
						} catch(Exception $exception) {
							$payload = array();
							setcookie($sessionName, 'INVALID', time()-3600, '/');
							unset($_COOKIE[$sessionName]);
							setcookie($sessionName, '', time()-3600,'/');
						}
					}
				}
				if(is_array($payload) && count($payload) >= 1) {
					if(array_key_exists($this->initConfig['session']['prefix'].$_key, $payload)) {
						$this->returnMethod['data'] = $payload[$this->initConfig['session']['prefix'].$_key];
						$status = true;
					}
					$this->returnMethod['success'] = $status;
					$this->returnMethod['message'] = ($status) ? 'Session key data retrieved!' : sprintf("Session-key '%s' is not exist!", $_key);
					$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
					if (!$this->returnMethod['success']) {
						unset($this->returnMethod['data']);
					}
					$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$this->returnMethod['errcode'] = ($status) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED;
					unset($this->returnMethod['errors']);
				} else {
					$this->returnMethod['success'] = $status;
					$this->returnMethod['message'] = 'Session is empty!';
					$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
					unset($this->returnMethod['data']);
					$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$this->returnMethod['errcode'] = self::X_NO_DATA_FOUND;
					unset($this->returnMethod['errors']);
				}
			break;
		}

		return $this->returnMethod;
	}

	/**
	 * Fungsi untuk mendaftarkan $_SESSION key, untuk keperluan
	 * saat user melakukan logout maka $_SESSION key yang di daftarkan
	 * akan di hapus/reset, maka tidak akan meninggalkan sisa di $_SESSION
	 * setelah logout
	 * 
	 * @param array|string $sessionKey	Key $_SESSION yang ingin di daftarkan
	 * 
	 * @return boolean					TRUE | FALSE
	 */
	public function registerSessionKey() {
		$startTime = floor(microtime(true)*1000);
		$_sessionKey = null;
		$_result = false;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData
		$_args = func_get_args();
		if(func_num_args() >= 2) {
			$_sessionKey = [];
			foreach ($_args as $idx => $val) {
				if (is_string($val)) $_sessionKey[$idx] = $val;
				unset($_args[$idx]);
			}
			unset($_args);
		} else if(func_num_args() == 1) {
			foreach ($_args as $idx => $val) {
				if (is_string($val) || is_array($val)) $_sessionKey = $val;
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// #checkingData
		if (is_null($_sessionKey) || empty($_sessionKey)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'sessionKey' untuk user(session) tidak ditemukan pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => sprintf("Pada saat melakukan pemeriksaan 'sessionKey', parameter pada Method '%s(%s)' tidak di temukan. tolong harap periksa kembali lagi", end(explode('\\', __METHOD__)), __LINE__),'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_sessionKey)), 'user-error', true); /* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s' currently 'sessionKey' passed from params is empty.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}

		if (session_status() === PHP_SESSION_NONE) { session_start(array('cookie_lifetime' => 0, 'cookie_path' => '/', 'cookie_httponly' => '1')); }
		$Encryption = new EncryptionVW;
		// Decrypt
		$userSessionKey = $_SESSION[$this->initConfig['session']['prefix'].'session_key'] ?? '';
		if (is_string($userSessionKey) || strlen($userSessionKey) >= 30) {
			$userSessionKey = $Encryption->decrypt($userSessionKey, $this->appConfig['CORE']['app_private_key']['app']['value']);
			if ($userSessionKey == false || $userSessionKey == null) {
				$userSessionKey = '';
			}
			if (is_string($userSessionKey) || wordExist($userSessionKey, '|+|')) {
				$userSessionKey = explode('|+|', $userSessionKey);
				if ($userSessionKey == false || $userSessionKey == null) {
					$userSessionKey = array();
				}
			}
		}

		// Change data
		if (is_array($userSessionKey)) {
			if (is_string($_sessionKey)) {
				$userSessionKey[] = $_sessionKey;
				$_result = (array_key_exists($_sessionKey, $userSessionKey));
			}
			if (is_array($_sessionKey)) {
				if (count($_sessionKey) >= 1) {
					foreach ($_sessionKey as $key) {
						$userSessionKey[] = $key;
						$_result = (array_key_exists($key, $userSessionKey));
					}
				}
			}
			$userSessionKey = array_filter($userSessionKey, function($value) { return !is_null($value) && $value !== ''; });
		}

		// Encrypt
		if (is_array($userSessionKey) && count($userSessionKey) >= 1) {
			$userSessionKey = implode('|+|', $userSessionKey);
			if (is_string($userSessionKey) || wordExist($userSessionKey, '|+|')) {
				$userSessionKey = $Encryption->encrypt($userSessionKey, $this->appConfig['CORE']['app_private_key']['app']['value']);
				if (is_string($userSessionKey) || strlen($userSessionKey) >= 30) {
					$_SESSION[$this->initConfig['session']['prefix'].'session_key'] = $userSessionKey;
				}
			}
		}

		$this->returnMethod['success'] = $_result;
		$this->returnMethod['message'] = ($_result) ? 'Success!' : 'Failed.';
		$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
		unset($this->returnMethod['data']);
		$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
		$this->returnMethod['errcode'] = ($_result) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED; 
		unset($this->returnMethod['errors']);
		return $this->returnMethod;
	}

	/**
	 * Fungsi untuk menghapus $_SESSION key, untuk keperluan
	 * saat user melakukan logout maka $_SESSION key yang di hapus
	 * tidak akan terhapus/reset, maka akan meninggalkan sisa di $_SESSION
	 * setelah logout
	 * 
	 * @param array|string $sessionKey	Key $_SESSION yang ingin di daftarkan
	 * 
	 * @return boolean					TRUE | FALSE
	 */
	public function removeSessionKey() {
		$startTime = floor(microtime(true)*1000);
		$_sessionKey = null;
		$_result = false;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData
		$_args = func_get_args();
		if(func_num_args() >= 2) {
			$_sessionKey = array();
			foreach($_args as $idx => $val) {
				if(is_string($val)) $_sessionKey[$idx] = $val;
				unset($_args[$idx]);
			}
			unset($_args);
		} else if(func_num_args() == 1) {
			foreach($_args as $idx => $val) {
				if(is_string($val) || is_array($val)) $_sessionKey = $val;
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// #prepareData
		if(is_null($_sessionKey) || empty($_sessionKey)) {/* START_HIDE */exceptionLog(array('title' => sprintf("Error 'sessionKey' untuk user(session) tidak ditemukan pada Method '%s(%s)'" , end(explode('\\', __METHOD__)), __LINE__),'message' => sprintf("Pada saat melakukan pemeriksaan 'sessionKey', parameter pada Method '%s(%s)' tidak di temukan. tolong harap periksa kembali lagi", end(explode('\\', __METHOD__)), __LINE__),'severity' => E_USER_ERROR,'filename' => __FILE__,'line' => __LINE__,'data' => array($_sessionKey)), 'user-error', true); /* STOP_HIDE */
			$this->returnMethod['success'] = false;
			$this->returnMethod['message'] = sprintf("Error on '%s' currently 'sessionKey' passed from params is empty.", end(explode('\\', __METHOD__)), __LINE__);
			$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
			unset($this->returnMethod['data']);
			$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
			$this->returnMethod['errcode'] = self::X_VALIDATE_ERROR;
			unset($this->returnMethod['errors']);
			return $this->returnMethod;
		}

		if(session_status() === PHP_SESSION_NONE) { session_start(array('cookie_lifetime' => 0, 'cookie_path' => '/', 'cookie_httponly' => '1')); }
		$Encryption = new EncryptionVW;
		// Decrypt
		$userSessionKey = $_SESSION[$this->initConfig['session']['prefix'].'session_key'] ?? '';
		if(is_string($userSessionKey) || strlen($userSessionKey) >= 30) {
			$userSessionKey = $Encryption->decrypt($userSessionKey, $this->appConfig['CORE']['app_private_key']['app']['value']);
			if($userSessionKey == false || $userSessionKey == null) {
				$userSessionKey = '';
			}
			if(is_string($userSessionKey) || wordExist($userSessionKey, '|+|')) {
				$userSessionKey = explode('|+|', $userSessionKey);
				if ($userSessionKey == false || $userSessionKey == null) {
					$userSessionKey = array();
				}
			}
			$userSessionKey = array_filter($userSessionKey, function($value) { return !is_null($value) && $value !== ''; });
		}

		// Change data
		if(is_array($userSessionKey)) {
			if(is_string($_sessionKey)) {
				if(($_key = array_search($_sessionKey, $userSessionKey)) !== false) {
					unset($userSessionKey[$_key]);
				}
				$_result = (!array_key_exists($_sessionKey, $userSessionKey));
			}
			if(is_array($_sessionKey)) {
				if(count($_sessionKey) >= 1) {
					foreach($_sessionKey as $key) {
						if(($_key = array_search($key, $userSessionKey)) !== false) {
							unset($userSessionKey[$_key]);
						}
						$_result = (!array_key_exists($key, $userSessionKey));
					}
				}
			}
		}

		// Encrypt
		if(is_array($userSessionKey)) {
			$userSessionKey = implode('|+|', $userSessionKey);
			if(is_string($userSessionKey) || wordExist($userSessionKey, '|+|')) {
				$userSessionKey = $Encryption->encrypt($userSessionKey, $this->appConfig['CORE']['app_private_key']['app']['value']);
				if(is_string($userSessionKey) || strlen($userSessionKey) >= 30) {
					$_SESSION[$this->initConfig['session']['prefix'].'session_key'] = $userSessionKey;
				}
			}
		}

		$this->returnMethod['success'] = $_result;
		$this->returnMethod['message'] = ($_result) ? 'Success!' : 'Failed.';
		$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
		unset($this->returnMethod['data']);
		$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
		$this->returnMethod['errcode'] = ($_result) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED; 
		unset($this->returnMethod['errors']);
		return $this->returnMethod;
	}

	/**
	 * Fungsi untuk mengambil $_SESSION key, untuk keperluan
	 * saat user melakukan logout maka $_SESSION key yang di ambil
	 * akan terhapus/reset, maka tidak akan meninggalkan sisa di $_SESSION
	 * setelah logout
	 * 
	 * @return array|boolean	Data | FALSE
	 */
	public function takeSessionKey() {
		$startTime = floor(microtime(true)*1000);
		$_result = false;

		if(session_status() === PHP_SESSION_NONE) { session_start(array('cookie_lifetime' => 0, 'cookie_path' => '/', 'cookie_httponly' => '1')); }
		$Encryption = new EncryptionVW();
		// Decrypt
		$userSessionKey = $_SESSION[$this->initConfig['session']['prefix'].'session_key'];
		if(is_string($userSessionKey) && strlen($userSessionKey) >= 30) {
			$userSessionKey = $Encryption->decrypt($userSessionKey, $this->appConfig['CORE']['app_private_key']['app']['value']);
			if(is_string($userSessionKey) || wordExist($userSessionKey, '|+|')) {
				$this->returnMethod['data'] = explode('|+|', $userSessionKey);
				$_result = true;
			}
		}

		$this->returnMethod['success'] = $_result;
		$this->returnMethod['message'] = ($_result) ? 'Success!' : 'Failed.';
		$this->returnMethod['datetime'] = date('Y-m-d H:i:s');
		if (!$_result) {
			unset($this->returnMethod['data']);
		}
		$this->returnMethod['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
		$this->returnMethod['errcode'] = ($_result) ? self::X_EXECUTE_SUCCESS : self::X_EXECUTE_FAILED; 
		unset($this->returnMethod['errors']);
		return $this->returnMethod;
	}
}