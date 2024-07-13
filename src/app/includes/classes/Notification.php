<?php namespace APP\includes\classes;
// Required every files to load
$thisPath = (defined('DIR_ROOT')) ? DIR_CONFIG : dirname(__FILE__, 4) . '/configs';
require_once($thisPath . '/variables.php');
require_once(DIR_CONFIG . '/db-handles.php');
require_once(DIR_CONFIG . '/db-queries.php');
require_once(DIR_CONFIG . '/functions.php');
require_once(DIR_VENDOR . '/autoload.php');

// Memuat class lain
use Ramsey\Uuid\Uuid; // ramsey/uuid : Library for creating Universally Unique Identifier alias UUID
use APP\includes\classes\EncryptionVW;

class Notification {
	public $selectDBMS = null; // Database config yang dipilih
	public $selectDB = null; // Database yang dipilih
	public $selectTBL = null; // Tabel user
	public $sessionPrefix = "";
	public $catchMsgs = []; // Koleksi pesan error/succes
	private $isInit = false;
	private $validData = []; // Default valid user data
	private $bulkAction = [];
	private $appConfig = null;

	public function __construct (array $_config = [], $_dbSettings = array(0, 'crm_user_notifications')) {
		$this->selectDB = $_dbSettings[0];
		$this->selectTBL = $_dbSettings[1];

		// Ambil konfigurasi
		$defaultConfig = array(
			"CORE" => array(
				"session_prefix" => "CRM_",
				"session_user" => array(
					"expire_time" => 3600,
					"max_user_device" => 3
				),
			)
		);
		$this->appConfig = loadConfig(DIR_CONFIG . '/app-setting.json.php', $defaultConfig, 'json');
		if (array_key_exists('CORE', $this->appConfig)) {
			$this->selectDBMS = array(
				'mysql_host' => $this->appConfig['CORE']['db_host'],
				'mysql_username' => $this->appConfig['CORE']['db_user'],
				'mysql_password' => $this->appConfig['CORE']['db_pass'],
				'mysql_database' => $this->appConfig['CORE']['db_name']
			);
			$this->validData = db_runQuery(array(
				'config_array' => $this->selectDBMS,
				'database_index' => $this->selectDB,
				'input' => array($this->selectDBMS['mysql_database'][$this->selectDB], $this->selectTBL),
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
			$this->sessionPrefix = (!isEmptyVar($this->appConfig['CORE']['session_prefix'])) ? ((is_string($this->appConfig['CORE']['session_prefix'])) ? $this->appConfig['CORE']['session_prefix'] : $defaultConfig['CORE']['session_prefix']) : $defaultConfig['CORE']['session_prefix'];
		}
	}

	private function isValid_Data (array $_data, array $_validData) {
		/*
		 * Memeriksa apakah pasangan key/value pada
		 * $_data adalah valid dengan $_validData..
		 * 
		 * return: true => data, false => null
		*/

		if (!is_null($_data)) {
			$lenData = count($_data);
			$lenValid = 0;
			if (isAssoc($_data)) {
				foreach ($_data as $k => $v) {
					if (in_array($k, $_validData) && $v !== '') $lenValid++;
					else $this->catchMsgs['error'][] = "isValid_Data(".__LINE__."): Data '$k' yang diteruskan tidak benar pada validasi!";
				}
			} else {
				for ($i = 0; $i <= (count($_data)-1); $i++) {
					if (in_array(trim($_data[$i]), $_validData)) $lenValid++;
					else $this->catchMsgs['error'][] = "isValid_Data(".__LINE__."): Data '".trim($_data[$i])."' yang diteruskan tidak benar pada validasi!";
				}
			}
			return ($lenData == $lenValid) ? $_data : null;
		}
		return null;
	}

	public function init () {
		$result = array(
			'success' => array(),
			'data' => array(),
			'errcode' => array(),
			'message' => array()
		);

		if ($this->isInit == false && count($this->bulkAction) != 0) {
			$this->isInit = true;
			foreach ($this->bulkAction as $action => $data) {
				if (is_array($data)) {
					for ($i = 0; $i <= (count($data)-1); $i++) {
						$response = [];
						switch ($action) {
							case 'add':
								$response = $this->add($data[$i]) ?? [];
							break;
							case 'get':
								$response = $this->get($data[$i]) ?? [];
							break;
							case 'update':
								$response = $this->update($data[$i]) ?? [];
							break;
							case 'delete':
								$response = $this->delete($data[$i]) ?? [];
							break;
						}

						// Serve to result
						if (!isEmptyVar($response)) {
							if (array_key_exists('success', $response)) {
								$result['success'][$action][$i] = $response['success'];
								$result['data'][$action][$i] = $response['data'];
								$result['errcode'][$action][$i] = $response['errcode'];
								$result['message'][$action][$i] = $response['message'];
							} else {
								$result['success'][$action][$i] = false;
								$result['data'][$action][$i] = null;
								$result['errcode'][$action][$i] = 99;
								$result['message'][$action][$i] = 'Invalid format response!';
							}
						} else {
							$result['success'][$action][$i] = false;
							$result['data'][$action][$i] = null;
							$result['errcode'][$action][$i] = 99;
							$result['message'][$action][$i] = 'Empty response from procedure!';
						}
						unset($this->bulkAction[$action][$i]);
					}
				} else {
					$response = [];
					switch ($action) {
						case 'add':
							$response = $this->add($data);
						break;
						case 'get':
							$response = $this->get($data);
						break;
						case 'update':
							$response = $this->update($data);
						break;
						case 'delete':
							$response = $this->delete($data);
						break;
					}
					// Serve to result
					if (!isEmptyVar($response)) {
						if (array_key_exists('success', $response)) {
							$result['success'][$action] = $response['success'];
							$result['data'][$action] = $response['data'];
							$result['errcode'][$action] = $response['errcode'];
							$result['message'][$action] = $response['message'];
						} else {
							$result['success'][$action] = false;
							$result['data'][$action] = null;
							$result['errcode'][$action] = 99;
							$result['message'][$action] = 'Invalid format response!';
						}
					} else {
						$result['success'][$action] = false;
						$result['data'][$action] = null;
						$result['errcode'][$action] = 99;
						$result['message'][$action] = 'Empty response from procedure!';
					}
				}
				unset($this->bulkAction[$action]);
			}
			$this->bulkAction = [];
			$this->isInit = false;
		}
		return (!is_null($result)) ? arr2Obj($result) : false;
	}

	// CREATE
	public function add () {
		$_data = null;
		$_singleInit = false;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData
		$_args = func_get_args();
		if (func_num_args() <= 3) {
			foreach ($_args as $idx => $val) {
				if (is_array($val)) {
					if (isAssoc($val)) $_data = $val;
				}
				if (is_bool($val)) $_singleInit = $val;
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// Menyiapkan data sebelum di proses, apakah init(bulk action) atau singleInit(run-once)
		if ($this->isInit == false || $_singleInit == true) {
			// #checkingData
			if (is_null($_data)) {
				// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				reportLog("Notification::add(".__LINE__."): Error 'data' kosong tidak ditemukan pada 'Constructor/Parameter'!", 412);
				// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				header('Location: ' . sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
				exit();
			}
			$_data = $this->isValid_Data($_data, $this->validData);
			if (is_null($_data)) {
				// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				reportLog("Notification::add(".__LINE__."): Error 'data' kosong tidak ditemukan pada 'Constructor/Parameter'!", 412);
				// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				header('Location: ' . sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
				exit();
			}

			// #re-prepareData
			if (array_key_exists('UID', $_data)) {
				unset($_data['UID']);
			}
			$dataRequired = ['NIK', 'TITLE', 'CONTENT'];
			foreach ($_data as $k => $v) {
				if (is_null($v) || empty($v)) {
					if (in_array($k, $dataRequired)) {
						// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
						reportLog("Notification::add(".__LINE__."): Error data '$k' nilai tidak ada, tolong isi!", 410);
						// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
						header('Location: ' . sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
						exit();
					}
				}
			}

			$dataMerge = $_data;
			if ($_singleInit == false) $this->bulkAction['add'][] = $dataMerge;
		}

		// Memproses data
		if ($this->isInit == true || $_singleInit == true) {
			$responseData = array(
				'success' => false,
				'data' => array(),
				'message' => '-',
				'errcode' => 1
			);
			$prepareData = ($this->isInit) ? $_data : $dataMerge;

			// Generate UID
			$uuid = '';
			do {
				$uuid = Uuid::uuid4();
				// $uuidInBytes = $uuid->getBytes();
				$uuidInString = $uuid->toString();
				$queryResult = db_runQuery(array(
					'config_array' => $this->selectDBMS,
					'database_index' => $this->selectDB,
					'input' => $uuidInString,
					'query' => sprintf('SELECT COUNT(true) as FOUND FROM %s WHERE UID = ?;', $this->selectTBL),
					'param' => 's',
					'getData' => true,
					'getAllRow' => false
				));
			} while ((int) $queryResult['FOUND'] >= 1);
			$prepareData['UID'] = $uuid->toString();

			$queryInput = $prepareData;
			$queryString = sprintf('INSERT INTO %s (%s) VALUES (%s);', $this->selectTBL, implode(',', array_keys($queryInput)), rtrim(str_repeat('?,', count($queryInput)), ','));
			$queryParam = str_repeat('s', count($queryInput));

			// Memeriksa data, jika tidak ada kesalahan mengembalikan nilai 'true (+data)' jika tidak 'false'
			$addNotification = db_runQuery(array(
				'config_array' => $this->selectDBMS,
				'database_index' => $this->selectDB,
				'input' => array_values($queryInput),
				'query' => $queryString,
				'param' => $queryParam,
				'getData' => false,
				'getAllRow' => false
			));

			if ($addNotification) {
				$responseData['success'] = true;
				$responseData['errcode'] = 0;
				$responseData['message'] = 'Success insert data (1) entries!';
			} else {
				$responseData['message'] = 'Data not found!';
				$responseData['errcode'] = 2;
			}
			return $responseData;
		}
	}
	public function new (...$args) {
		return $this->add(...$args);
	}
	public function create (...$args) {
		return $this->add(...$args);
	}

	// READ/GET //
	public function get () {
		$_where = null;
		$_select = '*';
		$_singleInit = false;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData
		$_args = func_get_args();
		if (func_num_args() <= 4) {
			foreach ($_args as $idx => $val) {
				if (is_array($val)) {
					if (isAssoc($val)) $_where = $val;
					else $_select = $val;
				}
				if (is_string($val)) $_select = $val;
				if (is_bool($val)) $_singleInit = $val;
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// Menyiapkan data sebelum di proses, apakah init(bulk action) atau singleInit(run-once)
		if ($this->isInit == false || $_singleInit == true) {
			// #checkingData
			if (is_null($_where)) {
				// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				reportLog("Notification::get(".__LINE__."): Error data 'where' tidak ditemukan pada 'Constructor/Parameter'!", 412);
				// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				header('Location: ' . sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
				exit();
			}
			$_where = $this->isValid_Data($_where, $this->validData);
			if (is_null($_where)) {
				// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				reportLog("Notification::get(".__LINE__."): Error data 'where' tidak ditemukan pada 'Constructor/Parameter'!", 412);
				// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				header('Location: ' . sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
				exit();
			}
			if (is_string($_select)) {
				if (!(wordExist($_select, ',') || $_select == '*')) {
					// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
					reportLog("Notification::get(".__LINE__."): Error data 'select' bukan salah satu data dari (Array Index, String '*', atau String 'key1, key2') pada 'Constructor/Parameter'!", 406);
					// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
					header('Location: ' . sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
					exit();
				}
				if (wordExist($_select, ',')) {
					$_select = $this->isValid_Data(explode(',', $_select), $this->validData);
				}
			} else {
				$_select = (is_array($_select)) ? $this->isValid_Data($_select, $this->validData) : $_select;
			}
			if (is_null($_select)) {
				// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				reportLog("Notification::get(".__LINE__."): Error data 'select' tidak ditemukan pada 'Constructor/Parameter'!", 412);
				// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				header('Location: ' . sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
				exit();
			}

			// #re-prepareData
			$whereRequired = ['NIK'];
			$where = [
				'NIK' => $_where['NIK'],
				'UID' => null
			];
			$where = array_merge($where, array_intersect_key($_where, $where));
			foreach ($where as $k => $v) {
				if (is_null($v) || empty($v)) {
					if (in_array($k, $whereRequired)) {
						// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
						reportLog("Notification::get(".__LINE__."): Error data '$k' nilai tidak ada, tolong isi!", 410);
						// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
						header('Location: ' . sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
						exit();
					} else {
						unset($where[$k]);
					}
				}
			}

			$dataMerge = [ $where, $_select ];
			if ($_singleInit == false) $this->bulkAction['get'][] = $dataMerge;
		}

		// Memproses data
		if ($this->isInit == true || $_singleInit == true) {
			$responseData = array(
				'success' => false,
				'data' => array(),
				'message' => '-',
				'errcode' => 1
			);
			$prepareData = ($this->isInit) ? $_select : $dataMerge;
			
			$queryWhere = $prepareData[0];
			$queryString = sprintf('SELECT %s FROM %s WHERE %s;', (is_array($prepareData[1])) ? trim(implode(', ', $prepareData[1])) : $prepareData[1], $this->selectTBL, trim(implode(' = ? AND ', array_keys($queryWhere))) . ' = ?');
			$queryParam = str_repeat('s', count($queryWhere));

			// Memeriksa data, jika tidak ada kesalahan mengembalikan nilai 'true (+data)' jika tidak 'false'
			$fetchNotification = db_runQuery(array(
				'config_array' => $this->selectDBMS,
				'database_index' => $this->selectDB,
				'input' => array_values($queryWhere),
				'query' => $queryString,
				'param' => $queryParam,
				'getData' => true,
				'getAllRow' => (!array_key_exists('UID', $queryWhere)) ? true : false,
				// 'stmtError' => getURI(2) . '/index.php?toast=' . base64_encode(json_encode($this->dataToast))
			));

			if (!isEmptyVar($fetchNotification) && $fetchNotification !== 'ZERO_DATA') {
				$responseData['success'] = true;
				$responseData['errcode'] = 0;
				if (!array_key_exists('UID', $queryWhere)) {
					$responseData['data'] = $fetchNotification; // All row
				} else {
					$responseData['data'][] = $fetchNotification; // Single row
				}
				$responseData['message'] = sprintf('Data found (%s) entries!', count($responseData['data']));
			} else {
				$responseData['message'] = 'Data not found!';
				$responseData['errcode'] = 2;
			}

			return $responseData;
		}
	}
	public function fetch (...$args) {
		return $this->get(...$args);
	}
	public function pull (...$args) {
		return $this->get(...$args);
	}

	// UPDATE //
	public function update () {
		$_data = null;
		$_where = null;
		$_singleInit = false;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData
		$_args = func_get_args();
		if (func_num_args() <= 4) {
			foreach ($_args as $idx => $val) {
				if (is_array($val)) {
					if (isAssoc($val)) {
						if (is_null($_where) && array_key_exists('UID', $val)) {
							$_where = $val;
						}
						if (is_null($_data)) {
							$_data = $val;
							if (array_key_exists('UID', $_data)) {
								unset($_data['UID']);
							}
						}
					} else {
						if (count($val) <= 2) {
							$_data = $val;
						}
					}
				}
				if (is_bool($val)) $_singleInit = $val;
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// Menyiapkan data sebelum di proses, apakah init(bulk action) atau singleInit(run-once)
		if ($this->isInit == false || $_singleInit == true) {
			// #checkingData
			if (!is_null($_data) || !is_null($_where)) {
				$_data = $this->isValid_Data($_data, $this->validData);
				$_where = $this->isValid_Data($_where, $this->validData);	
			}
			if (is_null($_data)) {
				// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				reportLog("Notification::update(".__LINE__."): Error 'data' untuk notification tidak ditemukan pada 'Constructor/Parameter'!", 412);
				header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				exit();
			}
			if (is_null($_where)) {
				// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				reportLog("Notification::update(".__LINE__."): Error 'where' untuk notification tidak ditemukan pada 'Constructor/Parameter'!", 412);
				header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				exit();
			}

			// #re-prepareData
			$whereRequired = ['UID'];
			foreach ($_where as $k => $v) {
				if (is_null($v) || empty($v)) {
					if (in_array($k, $whereRequired)) {
						// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
						reportLog("Notification::update(".__LINE__."): Error '$k' pada 'where' nilai tidak ada, tolong isi!", 410);
						// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
						header('Location: ' . sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
						exit();
					}
				}
			}

			$dataRequired = ['NIK', 'TITLE', 'CONTENT'];
			foreach ($_data as $k => $v) {
				if (is_null($v) || empty($v)) {
					if (in_array($k, $dataRequired)) {
						// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
						reportLog("Notification::update(".__LINE__."): Error '$k' pada 'data' nilai tidak boleh kosong, tolong isi!", 410);
						// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
						header('Location: ' . sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
						exit();
					}
				}
			}

			$dataMerge = [ $_data, $_where ];
			if ($_singleInit == false) $this->bulkAction['update'][] = $dataMerge;
		}

		// Memproses data
		if ($this->isInit == true || $_singleInit == true) {
			$responseData = array(
				'success' => false,
				'data' => array(),
				'message' => '-',
				'errcode' => 1
			);
			$prepareData = ($this->isInit) ? $_data : $dataMerge;

			// Check data is Exist or Not
			$queryWhere = $prepareData[1];
			$queryString = sprintf('SELECT COUNT(true) as FOUND FROM %s WHERE %s;', $this->selectTBL, trim(implode(' = ? AND ', array_keys($queryWhere))) . ' = ?');
			$queryParam = str_repeat('s', count($queryWhere));

			$isExist = db_runQuery(array(
				'config_array' => $this->selectDBMS,
				'database_index' => 0,
				'input' => array_values($queryWhere),
				'query' => $queryString,
				'param' => $queryParam,
				'getData' => true,
				'getAllRow' => false
			));
			if (isset($isExist['FOUND']) && $isExist !== 'ZERO_DATA' && $isExist['FOUND'] >= 1) {
				// Update data, if Exist
				$queryInput = $prepareData[0];
				$queryWhere = $prepareData[1];
				$queryString = sprintf('UPDATE %s SET %s WHERE %s;', $this->selectTBL, rtrim(implode(' = ?, ', array_keys($queryInput)), ', ') . ' = ?', trim(implode(' = ? AND ', array_keys($queryWhere))) . ' = ?');
				$queryParam = str_repeat('s', count($queryInput)) . str_repeat('s', count($queryWhere));

				$queryInput = array_merge($queryInput, $queryWhere);
				$updateNotification = db_runQuery(array(
					'config_array' => $this->selectDBMS,
					'database_index' => 0,
					'input' => array_values($queryInput),
					'query' => $queryString,
					'param' => $queryParam,
					'getData' => false,
					'getAllRow' => false
				));
				if ($updateNotification) {
					$responseData['success'] = true;
					$responseData['message'] = 'Success update data!';
					$responseData['errcode'] = 0;
				} else {
					$responseData['message'] = 'Failed update data!';
					$responseData['errcode'] = 3;
				}
			} else {
				$responseData['message'] = 'Data not found!';
				$responseData['errcode'] = 2;
			}

			return $responseData;
		}
	}
	public function edit (...$args) {
		return $this->update(...$args);
	}
	public function change (...$args) {
		return $this->update(...$args);
	}

	// DELETE //
	public function delete () {
		$_where = null;
		$_singleInit = false;

		// Saya menggunakan dinamis Argument/Parameter (karena, fungsi parameter kadang diisi 1)
		// #prepareData
		$_args = func_get_args();
		if (func_num_args() <= 3) {
			foreach ($_args as $idx => $val) {
				if (is_array($val)) {
					if (isAssoc($val)) $_where = $val;
				}
				if (is_bool($val)) $_singleInit = $val;
				unset($_args[$idx]);
			}
			unset($_args);
		}

		// Menyiapkan data sebelum di proses, apakah init(bulk action) atau singleInit(run-once)
		if ($this->isInit == false || $_singleInit == true) {
			// #checkingData
			if (!is_null($_where)) {
				$_where = $this->isValid_Data($_where, $this->validData);
			}
			if (is_null($_where)) {
				// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				reportLog("Notification::delete(".__LINE__."): Error data 'where' tidak ditemukan pada 'Constructor/Parameter'!", 412);
				// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				header('Location: ' . sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
				exit();
			}

			// #re-prepareData
			$whereRequired = ['UID'];
			$where = [
				'NIK' => null,
				'UID' => $_where['UID']
			];
			$where = array_merge($where, array_intersect_key($_where, $where));
			foreach ($where as $k => $v) {
				if (is_null($v) || empty($v)) {
					if (in_array($k, $whereRequired)) {
						// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
						reportLog("Notification::delete(".__LINE__."): Error data '$k' nilai tidak ada, tolong isi!", 410);
						// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
						header('Location: ' . sprintf('%s/index%s', getURI(2), (EXT_PHP) ? '.php' : ''));
						exit();
					} else {
						unset($where[$k]);
					}
				}
			}

			$dataMerge = $where;
			if ($_singleInit == false) $this->bulkAction['delete'][] = $dataMerge;
		}

		// Memproses data
		if ($this->isInit == true || $_singleInit == true) {
			$responseData = array(
				'success' => false,
				'data' => array(),
				'message' => '-',
				'errcode' => 1
			);
			$prepareData = ($this->isInit) ? $_where : $dataMerge;
			
			// Check data is Exist or Not
			$queryWhere = $prepareData;
			$queryString = sprintf('SELECT COUNT(true) as FOUND FROM %s WHERE %s;', $this->selectTBL, trim(implode(' = ? AND ', array_keys($queryWhere))) . ' = ?');
			$queryParam = str_repeat('s', count($queryWhere));

			$isExist = db_runQuery(array(
				'config_array' => $this->selectDBMS,
				'database_index' => 0,
				'input' => array_values($queryWhere),
				'query' => $queryString,
				'param' => $queryParam,
				'getData' => true,
				'getAllRow' => false
			));
			if (isset($isExist['FOUND']) && $isExist !== 'ZERO_DATA' && $isExist['FOUND'] >= 1) {
				// Delete data, if Exist
				$queryWhere = $prepareData;
				$queryString = sprintf('DELETE FROM %s WHERE %s;', $this->selectTBL, trim(implode(' = ? AND ', array_keys($queryWhere))) . ' = ?');
				$queryParam = str_repeat('s', count($queryWhere));

				$deleteNotification = db_runQuery(array(
					'config_array' => $this->selectDBMS,
					'database_index' => 0,
					'input' => array_values($queryWhere),
					'query' => $queryString,
					'param' => $queryParam,
					'getData' => false,
					'getAllRow' => false
				));
				if ($deleteNotification) {
					$responseData['success'] = true;
					$responseData['message'] = 'Success delete data!';
					$responseData['errcode'] = 0;
				} else {
					$responseData['message'] = 'Failed delete data!';
					$responseData['errcode'] = 3;
				}
			} else {
				$responseData['message'] = 'Data not found!';
				$responseData['errcode'] = 2;
			}

			return $responseData;
		}
	}
	public function remove (...$args) {
		return $this->delete(...$args);
	}
}