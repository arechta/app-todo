<?php namespace APP\includes\classes;
// Required every files to load
$thisPath = (defined('DIR_ROOT')) ? DIR_CONFIG : dirname(__FILE__, 4) . '/configs';
require_once($thisPath . '/variables.php');
require_once(DIR_CONFIG . '/db-handles.php');
require_once(DIR_CONFIG . '/db-queries.php');
require_once(DIR_CONFIG . '/functions.php');
require_once(DIR_VENDOR . '/autoload.php');

// Memuat class lain
use APP\includes\classes\EncryptionVW;
use Ramsey\Uuid\Uuid; // ramsey/uuid : Library for creating Universally Unique Identifier alias UUID
use Ifsnop\Mysqldump as IMysqldump; // Ifsnop/Mysqldump : Library for creating MySQL Dump Backup

class Blackbox {
	public $isLoggedin = false; // Kondisi user login
	//private $isDBAlive = false; // koneksi Database
	public $selectDBMS = null; // Database config yang dipilih
	public $sessionPrefix = "";
	private $appConfig = null;

	public function __construct () {
		if (session_status() === PHP_SESSION_NONE) { session_start(array('cookie_lifetime' => 0, 'cookie_path' => '/', 'cookie_httponly' => '1')); }
		$this->isLoggedin = $this->isLoggedin();

		// Ambil konfigurasi
		$defaultConfig = array(
			"CORE" => array(
				"session_prefix" => "CRM_",
				"session_user" => array(
					"expire_time" => 3600,
					"max_user_device" => 3
				),
			),
			"BLACKBOX" => array(
				"data" => array(
					"logHistory" => array(
						"active" => true,
						"keepDataInMonth" => 3,
						"maxBackupFiles" => 99999,
						"outputPath" => './.blackbox'
					)
				),
				"file" => array(
					"logHistory" => array(
						"active" => true,
						"keepDataInMonth" => 3,
						"maxBackupFiles" => 99999,
						"outputPath" => './.blackbox'
					)
				),
				"activity" => array(
					"logHistory" => array(
						"active" => true,
						"keepDataInMonth" => 3,
						"maxBackupFiles" => 99999,
						"outputPath" => './.blackbox'
					)
				),
			),
		);
		$this->appConfig = loadConfig(DIR_CONFIG . '/app-setting.json.php', $defaultConfig, 'json');
		if (array_key_exists('CORE', $this->appConfig)) {
			$this->selectDBMS = array(
				'mysql_host' => $this->appConfig['CORE']['db_host'],
				'mysql_username' => $this->appConfig['CORE']['db_user'],
				'mysql_password' => $this->appConfig['CORE']['db_pass'],
				'mysql_database' => $this->appConfig['CORE']['db_name']
			);
			$this->sessionPrefix = (!isEmptyVar($this->appConfig['CORE']['session_prefix'])) ? ((is_string($this->appConfig['CORE']['session_prefix'])) ? $this->appConfig['CORE']['session_prefix'] : $defaultConfig['CORE']['session_prefix']) : $defaultConfig['CORE']['session_prefix'];
		}
	}

	/**
	 * Fungsi untuk memeriksa apakah sesi user ada singkat-nya
	 * ini mencari sesi user berdasarkan Token user login
	 * 
	 * @param string $type	Tipe blackbox yang dibuat
	 * @param array $data	Inputan data untuk di buat
	 * @param array $config	Konfigurasi untuk fungsi
	 * 
	 * @return boolean		true (+UID) | false
	 */
	public function todayUID ($type = 'data', $userID) {
		// #validateData
		// Check 'type'
		if (isEmptyVar($type) || !in_array($type, array('data', 'file', 'activity'))) {
			// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
			reportLog("Blackbox::create(".__LINE__."): Error 'type' pada parameter 1, pilihan tidak valid!", 410);
			// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
			exit();
		}
		// Check 'userID'
		if (!isEmptyVar($userID)) {
			$isExistUser = db_runQuery(array(
				'config_array' => $this->selectDBMS,
				'database_index' => 0,
				'input' => array($userID),
				'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) AS FOUND FROM %s WHERE NIK = ?;', $this->appConfig['CORE']['tb_prefix'] . 'user_account'),
				'param' => 's',
				'getData' => true,
				'getAllRow' => false
			));
			if (isEmptyVar($isExistUser['FOUND']) || $isExistUser === 'ZERO_DATA' || (int) $isExistUser['FOUND'] <= 0) {
				// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				reportLog("Blackbox::todayUID(".__LINE__."): Error 'userID' tidak ditemukan pada server!", 406);
				// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				exit();
			}
		} else {
			// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
			reportLog("Blackbox::todayUID(".__LINE__."): Error 'userID' pada parameter 2, tidak boleh kosong!", 406);
			// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
			exit();
		}

		// #processData
		$isExistBlackbox = db_runQuery(array(
			'config_array' => $this->selectDBMS,
			'database_index' => 0,
			'input' => array($userID, date('Y-m-d')),
			'query' => sprintf('SELECT COUNT(DISTINCT(BLACKBOX_UID)) AS FOUND, BLACKBOX_UID FROM %s WHERE OWNER = ? AND DATE(DATE_CREATED) = ?;', $this->appConfig['CORE']['tb_prefix'] . 'blackbox_' . strtolower(trim($type))),
			'param' => 'ss',
			'getData' => true,
			'getAllRow' => false
		));
		if (!isEmptyVar($isExistBlackbox['FOUND']) && $isExistBlackbox !== 'ZERO_DATA' && (int) $isExistBlackbox['FOUND'] >= 1) {
			return Uuid::fromString(bin2hex($isExistBlackbox['BLACKBOX_UID']));
		} else {
			return false;
		}
	}

	/**
	 * Fungsi untuk membuat History Blackbox pada user tertentu
	 * blackbox dibuat berdasarkan per-hari, jika kondisi hari ini
	 * blackbox ada maka akan update history berdasarkan data yang
	 * ditambahkan
	 * 
	 * @param string	$type		Tipe blackbox yang dibuat
	 * @param string	$userID		ID User untuk kepemilikan Blackbox
	 * @param array		$data		Inputan data untuk di catat
	 * @param array		$config		Konfigurasi untuk fungsi (Not available yet)
	 * 
	 * @return boolean	true (+Array) | false
	 */
	public function create ($type = 'data', $userID, $data = array(), $config = array()) {
		// #validateData
		// Check 'type'
		if (isEmptyVar($type) || !in_array($type, array('data', 'file', 'activity'))) {
			// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
			reportLog("Blackbox::create(".__LINE__."): Error 'type' pada parameter 1, pilihan tidak valid!", 410);
			// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
			exit();
		}
		// Check 'userID'
		if (!isEmptyVar($userID)) {
			$isExistUser = db_runQuery(array(
				'config_array' => $this->selectDBMS,
				'database_index' => 0,
				'input' => array($userID),
				'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) AS FOUND FROM %s WHERE NIK = ?;', $this->appConfig['CORE']['tb_prefix'] . 'user_account'),
				'param' => 's',
				'getData' => true,
				'getAllRow' => false
			));
			if (isEmptyVar($isExistUser['FOUND']) || $isExistUser === 'ZERO_DATA' || (int) $isExistUser['FOUND'] <= 0) {
				// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				reportLog("Blackbox::create(".__LINE__."): Error 'userID' tidak ditemukan pada server!", 406);
				// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				exit();
			}
		} else {
			// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
			reportLog("Blackbox::create(".__LINE__."): Error 'userID' pada parameter 2, tidak boleh kosong!", 406);
			// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
			exit();
		}
		// Check 'data'
		if (!is_array($data) || !isAssoc($data)) {
			// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
			reportLog("Blackbox::create(".__LINE__."): Error 'data' pada parameter 3, data tidak valid!", 410);
			// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
			exit();
		}

		// #prepareData
		$isCreateNew = false;
		// Generate Blackbox UID or get current UID
		$uid = $this->todayUID($type, $userID);
		if (isEmptyVar($uid) || $uid === null) {
			$isCreateNew = true;
			do {
				$uid = Uuid::uuid4();
				$isExistUID = db_runQuery(array(
					'config_array' => $this->selectDBMS,
					'database_index' => 0,
					'input' => array(hex2bin(str_replace('-','',$uid->toString()))),
					'query' => sprintf('SELECT COUNT(DISTINCT(BLACKBOX_UID)) AS FOUND FROM %s WHERE BLACKBOX_UID = ?;', $this->appConfig['CORE']['tb_prefix'] . 'blackbox_' . strtolower(trim($type))),
					'param' => 's',
					'getData' => true,
					'getAllRow' => false
				));
			} while ((int) $isExistUID['FOUND'] >= 1);
		}

		// #processData
		// Create new row or append to a existing one
		if ($isCreateNew) {
			$historyJSON = json_encode(array( $data ), JSON_UNESCAPED_SLASHES);

			$queryInput = array(
				'BLACKBOX_UID' => hex2bin(str_replace('-','',$uid->toString())),
				'OWNER' => $userID,
				'HISTORY' => $historyJSON,
				'HASH_MD5' => hash('md5', $historyJSON),
				'HASH_SHA256' => hash('sha256', $historyJSON),
				'TOTAL_CHANGES' => 1,
				'DATE_CREATED' => date('Y-m-d H:i:s'),
				'DATE_UPDATED' => NULL
			);

			$createBlackbox = db_runQuery(array(
				'config_array' => $this->selectDBMS,
				'database_index' => 0,
				'input' => array_values($queryInput),
				'query' => sprintf("INSERT INTO %s (%s) VALUES (%s)", $this->appConfig['CORE']['tb_prefix'] . 'blackbox_' . strtolower(trim($type)), '`' . implode('`, `', array_keys($queryInput)) . '`', rtrim(trim(str_repeat('?, ', count(array_keys($queryInput)))), ',')),
				'param' => str_repeat('s', count(array_keys($queryInput))),
				'getData' => false,
				'getAllRow' => false,
			));
			return ($createBlackbox) ? array('blackboxUID' => $uid->toString(), 'dataIndex' => 0) : false;
		} else {
			$currentBlackbox = db_runQuery(array(
				'config_array' => $this->selectDBMS,
				'database_index' => 0,
				'input' => array(hex2bin(str_replace('-','',$uid->toString()))),
				'query' => sprintf("SELECT * FROM %s WHERE BLACKBOX_UID = ?;", $this->appConfig['CORE']['tb_prefix'] . 'blackbox_' . strtolower(trim($type))),
				'param' => 's',
				'getData' => true,
				'getAllRow' => false
			));
			if (!isEmptyVar($currentBlackbox) && $currentBlackbox !== 'ZERO_DATA') {
				$historyModified = array_merge(json_decode($currentBlackbox['HISTORY'], true), array( $data ));
				$historyJSON = json_encode($historyModified, JSON_UNESCAPED_SLASHES);

				// Prepare data
				$queryWhere = array(
					'BLACKBOX_UID' => hex2bin(str_replace('-','',$uid->toString())),
				);
				$queryInput = array(
					'HISTORY' => $historyJSON,
					'HASH_MD5' => hash('md5', $historyJSON),
					'HASH_SHA256' => hash('sha256', $historyJSON),
					'TOTAL_CHANGES' => intval($currentBlackbox['TOTAL_CHANGES']) + 1,
					'DATE_UPDATED' => date('Y-m-d H:i:s')
				);
				$queryString = sprintf('UPDATE %s SET %s WHERE %s;', $this->appConfig['CORE']['tb_prefix'] . 'blackbox_' . strtolower(trim($type)), rtrim(implode(' = ?, ', array_keys($queryInput)), ', ') . ' = ?', rtrim(implode(' = ? ', array_keys($queryWhere)), ' = ? ') . ' = ?');
				$queryParam = str_repeat('s', count($queryInput)) . str_repeat('s', count($queryWhere));

				$appendBlackbox = db_runQuery(array(
					'config_array' => $this->selectDBMS,
					'database_index' => 0,
					'input' => array_merge(array_values($queryInput), array_values($queryWhere)),
					'query' => $queryString,
					'param' => $queryParam,
					'getData' => false,
					'getAllRow' => false,
				));
				
				return ($appendBlackbox) ? array('blackboxUID' => $uid->toString(), 'dataIndex' => (count($historyModified) - 1)) : false;
			}
		}
	}

	/**
	 * Fungsi untuk megambil History Blackbox pada user tertentu
	 * 
	 * @param string		$type		Tipe blackbox yang dibuat
	 * @param string		$userID		ID User untuk kepemilikan Blackbox
	 * @param string|array	$select		Default: all, array(<index_history>)
	 * @param array			$config		Konfigurasi untuk fungsi
	 * 
	 * @return boolean		true (+Array) | false
	 */
	public function get ($type = 'data', $userID, $select = 'all', $config = array()) {
		// #validateData
		// Check 'type'
		if (isEmptyVar($type) || !in_array($type, array('data', 'file', 'activity'))) {
			// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
			reportLog("Blackbox::get(".__LINE__."): Error 'type' pada parameter 1, pilihan tidak valid!", 410);
			// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
			exit();
		}
		// Check 'userID'
		if (!isEmptyVar($userID)) {
			$isExistUser = db_runQuery(array(
				'config_array' => $this->selectDBMS,
				'database_index' => 0,
				'input' => array($userID),
				'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) AS FOUND FROM %s WHERE NIK = ?;', $this->appConfig['CORE']['tb_prefix'] . 'user_account'),
				'param' => 's',
				'getData' => true,
				'getAllRow' => false
			));
			if (isEmptyVar($isExistUser['FOUND']) || $isExistUser === 'ZERO_DATA' || (int) $isExistUser['FOUND'] <= 0) {
				// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				reportLog("Blackbox::get(".__LINE__."): Error 'userID' tidak ditemukan pada server!", 406);
				// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
				exit();
			}
		} else {
			// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
			reportLog("Blackbox::get(".__LINE__."): Error 'userID' pada parameter 2, tidak boleh kosong!", 406);
			// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
			exit();
		}
		// Check 'select'
		if (strtolower(trim($select)) !== 'all' || (!is_array($select) && $select !== 'all')) {
			// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
			reportLog("Blackbox::get(".__LINE__."): Error 'select' pada parameter 3, pilihan tidak valid!", 410);
			// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
			exit();
		}
		// Check 'config'
		if (!is_array($config) && !isAssoc($config)) {
			// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
			reportLog("Blackbox::get(".__LINE__."): Error 'config' pada parameter 4, data tidak valid!", 410);
			// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
			exit();
		}

		// #prepareData
		$defaultConfig = array(
			'blackboxUID' => array(),
			'blackboxDate' => array( date('Y-m-d') ),
			'withRowHead' => false,
		);
		$todayUID = $this->todayUID($type, $userID);
		if (!isEmptyVar($todayUID) && is_object($todayUID)) {
			$defaultConfig['blackboxUID'][] = $todayUID->toString();
		}
		if (is_array($config) && isAssoc($config)) {
			if (array_key_exists('blackboxUID', $config)) {
				if (is_array($config['blackboxUID']) && count($config['blackboxUID']) === 0) {
					$defaultConfig['blackboxUID'] = array();
				}
			}
			if (array_key_exists('blackboxDate', $config)) {
				if (is_array($config['blackboxDate']) && count($config['blackboxDate']) === 0) {
					$defaultConfig['blackboxDate'] = array();
				}
			}
		}
		// Merge config, with default one
		$config = array_replace_recursive($defaultConfig, array_intersect_key($config, $defaultConfig));

		// #processData
		if (count($config['blackboxUID']) >= 1 || count($config['blackboxDate']) >= 1) {
			$queryInput = array();
			$queryWhereInput = array(
				'BLACKBOX_UID' => array(),
				'DATE(DATE_CREATED)' => array()
			);
			$querySelectString = '*';
			$queryWhereString = array(
				'BLACKBOX_UID' => 'IN (%s)',
				'DATE(DATE_CREATED)' => 'IN (%s)'
			);
			$queryWhereCondition = array('OR');
			if (count($config['blackboxUID']) >= 1) {
				foreach ($config['blackboxUID'] as $perUID) {
					if (!isEmptyVar($perUID) && is_string($perUID) && Uuid::isValid($perUID)) {
						$uid = Uuid::fromString($perUID);
						$queryWhereInput['BLACKBOX_UID'][] = hex2bin(str_replace('-','',$uid->toString()));
					}
				}
				if (count($queryWhereInput['BLACKBOX_UID']) >= 1) {
					$queryWhereString['BLACKBOX_UID'] = sprintf($queryWhereString['BLACKBOX_UID'], rtrim(trim(str_repeat('?, ', count($queryWhereInput['BLACKBOX_UID']))), ','));
				}
			}
			if (count($config['blackboxDate']) >= 1) {
				foreach ($config['blackboxDate'] as $perDate) {
					if (!isEmptyVar($perDate) && is_string($perDate) && ($perDate === date('Y-m-d', strtotime($perDate)))) {
						$queryWhereInput['DATE(DATE_CREATED)'][] = $perDate;
					}
				}
				if (count($queryWhereInput['DATE(DATE_CREATED)']) >= 1) {
					$queryWhereString['DATE(DATE_CREATED)'] = sprintf($queryWhereString['DATE(DATE_CREATED)'], rtrim(trim(str_repeat('?, ', count($queryWhereInput['DATE(DATE_CREATED)']))), ','));
				}
			}
			$merge_queryWhereInput = array();
			$merge_queryWhereString = '';
			foreach ($queryWhereString as $keyWhere => $condWhere) {
				if (count($queryWhereInput[$keyWhere]) >= 1) {
					$merge_queryWhereInput = array_merge($merge_queryWhereInput, $queryWhereInput[$keyWhere]);
					if (strlen(trim($merge_queryWhereString)) === 0) {
						$merge_queryWhereString .= sprintf('%s %s', $keyWhere, $condWhere);
					} else {
						$merge_queryWhereString .= ' %s ' . sprintf('%s %s', $keyWhere, $condWhere);
					}
				}
			}
			$queryWhereInput = $merge_queryWhereInput;
			$queryWhereString = vsprintf($merge_queryWhereString, $queryWhereCondition);
			unset($merge_queryWhereInput);
			unset($merge_queryWhereString);

			if ($config['withRowHead']) {
				$querySelectString = '*';
			} else {
				$querySelectString = 'BLACKBOX_UID, HISTORY';
			}

			$queryInput = $queryWhereInput;
			$queryString = sprintf("
				SELECT %s
				FROM %s
				WHERE DATE_CREATED IN (
					SELECT CONCAT(A.DATE,' ',MAX(A.TIME)) AS 'DATE_CREATED'
					FROM (
						SELECT DATE_CREATED, DATE(DATE_CREATED) AS 'DATE', TIME(DATE_CREATED) AS 'TIME'
						FROM %s
						WHERE %s ORDER BY DATE_CREATED DESC
					) A GROUP BY A.DATE ORDER BY DATE_CREATED DESC
				) ORDER BY DATE_CREATED DESC",
				$querySelectString,
				$this->appConfig['CORE']['tb_prefix'] . 'blackbox_' . strtolower(trim($type)),
				$this->appConfig['CORE']['tb_prefix'] . 'blackbox_' . strtolower(trim($type)),
				$queryWhereString
			);
			$queryParam = str_repeat('s', count($queryInput));

			$listBlackbox = db_runQuery(array(
				'config_array' => $this->selectDBMS,
				'database_index' => 0,
				'input' => $queryInput,
				'query' => $queryString,
				'param' => $queryParam,
				'getData' => true,
				'getAllRow' => true
			));
			if (!isEmptyVar($listBlackbox) && $listBlackbox !==  'ZERO_DATA' && count($listBlackbox) >= 1) {
				$result = array();
				foreach ($listBlackbox as &$perBlackbox) {
					$uid = Uuid::fromString(bin2hex($perBlackbox['BLACKBOX_UID']));
					if ($config['withRowHead']) {
						$perBlackbox['BLACKBOX_UID'] = $uid->toString();
						$perBlackbox['HISTORY'] = json_decode($perBlackbox['HISTORY'], true);
						$result[] = $perBlackbox;
					} else {
						$result[$uid->toString()] = json_decode($perBlackbox['HISTORY'], true);
					}
				}
				return $result;
			} else {
				return false;
			}
		} else {
			// $this->dataToast += [ 'expiredon' => strtotime('now') + 5 ];
			reportLog("Blackbox::get(".__LINE__."): Error 'config' pada parameter 4, salah satu dari 'blackboxUID' atau 'blackboxDate' harus di isi!", 410);
			// header('location: '. getURI(2) .'/index.php?toast=' . base64_encode(json_encode($this->dataToast)));
			exit();
		}
	}

	public function isLoggedin ($returnNIK = false) {
		if (session_status() === PHP_SESSION_NONE) { session_start(array('cookie_lifetime' => 0, 'cookie_path' => '/', 'cookie_httponly' => '1')); }
		// Check user login atau belum
		if (isset($_SESSION[$this->sessionPrefix . 'user-online']) && $_SESSION[$this->sessionPrefix . 'user-online'] === true) { 
			return ($returnNIK === false) ? true : $_SESSION[$this->sessionPrefix . 'user-nik'];
		} else { return false; }
	}
}