<?php
// Required every files to load
$thisPath = (defined('DIR_ROOT') ? DIR_CONFIG : dirname(__FILE__, 3) . '/configs');
require_once($thisPath . '/variables.php');
require_once(DIR_CONFIG . '/functions.php');
require_once(DIR_CONFIG . '/db-handles.php');
require_once(DIR_CONFIG . '/db-queries.php');
require_once(DIR_VENDOR . '/autoload.php');
$appConfig = loadConfig(DIR_CONFIG . '/app-setting.json.php', null, 'json');
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
$user = new User();
$EVW = new EncryptionVW();
$databaseList = $APP_CORE['db_name'];

$userData = array('stt', 'nik', 'tkn', 'usr');
foreach($userData as $idx => $perData) {
	$userData[$perData] = $user->getSession($perData);
	$userData[$perData] = ($userData[$perData]['success']) ? $userData[$perData]['data'] : null;
	unset($userData[$idx]);
}
$userData = arr2Obj($userData);

if(!isEmptyVar($_POST['ajax']) && ($_POST['ajax'] === 'true' || $_POST['ajax'] === true)) {
	$startTime = floor(microtime(true)*1000);
	// Default request
	$actionType = (isset($_POST['action'])) ? ((!isEmptyVar($_POST['action'])) ? trim($_POST['action']) : false) : false;
	$dataRequest = (isset($_POST['data'])) ? ((!isEmptyVar($_POST['data'])) ? $_POST['data'] : null) : null;
	// Specific request
	$customer = (isset($_POST['customer'])) ? ((!isEmptyVar($_POST['customer'])) ? $_POST['customer'] : null) : null;
	$presensi = (isset($_POST['presensi'])) ? ((!isEmptyVar($_POST['presensi'])) ? $_POST['presensi'] : null) : null;
	$period = (isset($_POST['period'])) ? ((!isEmptyVar($_POST['period'])) ? $_POST['period'] : null) : null;

	if(!isEmptyVar($period)) {
		$listPeriod = explode('-', $period);
		if(count($listPeriod) == 2) {
			$period .= '-01';
		}
	} else {
		$period = date('Y-m-01');
	}

	switch($actionType) {
		/* User-type: Customer */
		case 'init-customer':
			/*
			 * Stage: 1
			 * Check if the user is online and has a NIK
			 */
			if(!isEmptyVar($userData->nik)) {
				/*
				 * Stage: 2 A
				 * Check if the account is registered with the current NIK
				 */
				$isExist = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik),
					'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) as TOTAL, NAMA, NAMA_PERUSAHAAN, KODE_DC, KODE_CUSTOMER, KODE_STOREKEY FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
					'param' => 's',
					'getData' => true,
					'getAllRow' => false
				));

				/*
				 * Stage: 2 B
				 * Check if the account is had access to some privileges
				 */
				$isHadAccess = false;
				$isPartPrivileges = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik, 0, 'c'),
					'query' => sprintf('SELECT * FROM %s WHERE NIK = ? AND (LEVEL = ? OR LEVEL_CODE = ?);', $APP_CORE['tb_prefix'].'user_privileges'),
					'param' => 'sss',
					'getData' => true,
					'getAllRow' => false
				));
				if(!isEmptyVar($isPartPrivileges) && $isPartPrivileges !== 'ZERO_DATA') {
					if(array_key_exists('PERMISSIONS', $isPartPrivileges) && !isEmptyVar($isPartPrivileges['PERMISSIONS'])) {
						if(isJSON($isPartPrivileges['PERMISSIONS'])) {
							$accountPrivileges = json_decode($isPartPrivileges['PERMISSIONS'], true);
							if(is_array($accountPrivileges) && isAssoc($accountPrivileges)) {
								$restrictPage = searchArrAssoc($accountPrivileges['privileges']['pages'], 'link', 'dashboard');
								if(count($restrictPage) >= 1 && is_array($restrictPage)) {
									$restrictPage = $restrictPage[0]; // Reset array
									if(is_array($restrictPage) && $restrictPage['view'] === true) {
										$isHadAccess = true;
										$isPartPrivileges = $restrictPage;
									}
								}
							}
						}
					}
				}
				if(isset($isExist['TOTAL']) && $isExist['TOTAL'] >= 1 && $isHadAccess === true) {
					if(!isEmptyVar($isExist['KODE_DC']) && wordExist(strtoupper(trim($isExist['KODE_DC'])), 'MTP')) {
						/*
						 * Stage: 3 A
						 * Get config DBMS based on user KODE_DC, from database MTP_CENTRAL.mtp_master_koneksi
						 */
						$configDBMS = db_runQuery(array(
							'config_array' => $configMysql,
							'database_index' => 1, // db.mtp_central
							'input' => array($isExist['KODE_DC'], 1),
							'query' => sprintf('SELECT * FROM %s WHERE KODE_DC = ? AND FLAG = ?;', 'mtp_master_koneksi'),
							'param' => 'ss',
							'getData' => true,
							'getAllRow' => false,
							'callback' => function($result) {
								$result = $result['data'];
								$output = null;
								if(!isEmptyVar($result) && $result !== 'ZERO_DATA') {
									$output = array(
										'host' => $result['DB_HOST'],
										'port' => $result['DB_PORT'],
										'user' => $result['DB_USER'],
										'pass' => (isEmptyVar($result['DB_PASS'])) ? '' : $result['DB_PASS'],
										'name' => $result['DB_NAME']
									);
								}
								return $output;
							}
						));
						if(!isEmptyVar($configDBMS) && is_array($configDBMS)) {
							$customerDBMS = array(
								'mysql_host' => $configDBMS['host'],
								'mysql_username' => $configDBMS['user'],
								'mysql_password' => $configDBMS['pass'],
								'mysql_database' => array( $configDBMS['name'] )
							);

							/*
							 * Stage: 3 B
							 * Checking the Customer, to make sure it was in the database configuration it fetched earlier (Stage 3 A)
							 */
							$isExistCustomer = db_runQuery(array(
								'config_array' => $customerDBMS,
								'database_index' => 0,
								'input' => array($isExist['KODE_CUSTOMER'], $isExist['KODE_STOREKEY'], 1),
								'query' => sprintf('SELECT KODE_DC, STOREKEY FROM %s WHERE (KODE_DC = ? OR STOREKEY = ?) AND AKTIF = ?;', 'mtp_master_dc'),
								'param' => 'sss',
								'getData' => true,
								'getAllRow' => false
							));
							if(!isEmptyVar($isExistCustomer) && $isExistCustomer !== 'ZERO_DATA') {
								$jsonResponse['success'] = array();
								$jsonResponse['message'] = array();
								$jsonResponse['datetime'] = array();
								$jsonResponse['data'] = array();
								$jsonResponse['took'] = array();
								$jsonResponse['errcode'] = array();
								$jsonResponse['errors'] = array();

								$serveData = array(
									'ThroughputIO' => true, // alias: Throughput Items Inbound Outbound
									'ChamberUsage' => true, // alias: Chamber Usage of Customer
									'StorageTempHum' => true // alias: Storage Temperature & Humidity
								);

								foreach($serveData as $key => $val) {
									$ruleBlock = searchArrAssoc($isPartPrivileges['blocks'], 'id', $key);
									if(count($ruleBlock) >= 1 && is_array($ruleBlock)) {
										$ruleBlock = $ruleBlock[0]; // Reset array
										$serveData[$key] = (is_array($ruleBlock) && $ruleBlock['view'] === true) ? true : false;
									}
								}

								if(!isEmptyVar($dataRequest) && is_string($dataRequest) && strlen($dataRequest) >= 30) {
									$dataRequest = json_decode(jsonFixer($EVW->decrypt($dataRequest, $APP_CORE['app_key_encrypt']), true), true) ?? null;
									if(!isEmptyVar($dataRequest) && isAssoc($dataRequest)) {
										$defaultData = array(
											'ThroughputIO' => array(
												'uom' => 'lpn'
											),
											'StorageTempHum' => array(
												'period' => date('Y-m-d'),
												'cs' => 'all',
												'view' => 'compact'
											),
											'ChamberUsage' => array(
												'customer' => null
											)
										);
										$dataRequest = array_replace_recursive($defaultData, array_intersect_key($dataRequest, $defaultData));

										// Check is valid data
										if(!in_array($dataRequest['ThroughputIO']['uom'], array('lpn', 'multi'))) {
											$dataRequest['ThroughputIO']['uom'] = 'lpn';
										}
										// if(!in_array($dataRequest['StorageTempHum']['view'], array('raw', 'compact'))) {
										// 	$dataRequest['StorageTempHum']['view'] = 'compact';
										// }
									}
								}

								/*
								* Default values
								*/
								// Serve for: ThroughputIO 
								if(boolval($serveData['ThroughputIO']) === true) {
									// Result in JSON
									$jsonResponse['success']['ThroughputIO'] = array('charts' => false);
									$jsonResponse['message']['ThroughputIO'] = array('charts' => 'Data not found!');
									$jsonResponse['datetime']['ThroughputIO'] = date('Y-m-d H:i:s');
									$jsonResponse['data']['ThroughputIO'] = array('summary' => false, 'charts' => array());
									$jsonResponse['took']['ThroughputIO'] = (floor(microtime(true)*1000))-$startTime;
									$jsonResponse['errcode']['ThroughputIO'] = array('charts' => 1);
									unset($jsonResponse['errors']);

									// Default summary values
									$jsonResponse['data']['ThroughputIO']['summary'] = array(
										'on_date' => date('F Y'),
										'total_inbound' => 0,
										'total_outbound' => 0,
										'uom' => $dataRequest['ThroughputIO']['uom']
									);

									// Default chart values
									$currentDays = date('d');
									// $totalDays = date('t');
									for ($i = 0; $i < $currentDays; $i++) {
										$jsonResponse['data']['ThroughputIO']['charts'][$i] = array(
											'inbound' => 0,
											'outbound' => 0
										);
									}
								}
								// Serve for: ChamberUsage 
								if(boolval($serveData['ChamberUsage']) === true) {
									// Result in JSON
									$jsonResponse['success']['ChamberUsage'] = false;
									$jsonResponse['message']['ChamberUsage'] = 'Data not found!';
									$jsonResponse['datetime']['ChamberUsage'] = date('Y-m-d H:i:s');
									$jsonResponse['data']['ChamberUsage'] = array('charts' => array(), 'labels' => array());
									$jsonResponse['took']['ChamberUsage'] = (floor(microtime(true)*1000))-$startTime;
									$jsonResponse['errcode']['ChamberUsage'] = 1;
									unset($jsonResponse['errors']);

									$jsonResponse['data']['ChamberUsage']['charts'] = array(
										'datasets' => array(),
										'total' => array(
											'available' => 0,
											'usage' => 0,
										),
										'lastUpdate' => 'EMPTY',
									);
								}
								// Serve for: StorageTempHum 
								if(boolval($serveData['StorageTempHum']) === true) {
									// Result in JSON
									$jsonResponse['success']['StorageTempHum'] = array('charts' => array('temperature' => 0, 'humidity' => 0), 'listPeriod' => false, 'listCS' => false, 'listView' => true);
									$jsonResponse['message']['StorageTempHum'] = array('charts' => array('temperature' => 'Data not found!', 'humidity' => 'Data not found!'), 'listPeriod' => 'Data not found!', 'listCS' => 'Data not found!', 'listView' => 'Data not found!');
									$jsonResponse['datetime']['StorageTempHum'] = date('Y-m-d H:i:s');
									$jsonResponse['data']['StorageTempHum'] = array('charts' => array('temperature' => array(), 'humidity' => array()), 'listPeriod' => array(), 'listCS' => array(), 'listView' => array(), 'datePicked' => null);
									$jsonResponse['took']['StorageTempHum'] = (floor(microtime(true)*1000))-$startTime;
									$jsonResponse['errcode']['StorageTempHum'] = array('charts' => array('temperature' => 1, 'humidity' => 1), 'listPeriod' => 1, 'listCS' => 1, 'listView' => 0);
									unset($jsonResponse['errors']);

									// Default charts
									$jsonResponse['data']['StorageTempHum']['charts'] = array(
										'temperature' => array(
											0 => array(
												'name' => '-',
												'label' => array(),
												'datasets' => array(),
												'stableRate' => 0,
											),
										),
										'humidity' => array(
											0 => array(
												'name' => '-',
												'label' => array(),
												'datasets' => array(),
												'stableRate' => 0,
											),
										),
									);
									for ($i = 0; $i < 24; $i++) {
										$jsonResponse['data']['StorageTempHum']['charts']['temperature'][0]['label'][$i] = sprintf('%s:00', str_pad($i, 2, '0', STR_PAD_LEFT));
										$jsonResponse['data']['StorageTempHum']['charts']['temperature'][0]['datasets'][$i] = 0;
										$jsonResponse['data']['StorageTempHum']['charts']['humidity'][0]['label'][$i] = sprintf('%s:00', str_pad($i, 2, '0', STR_PAD_LEFT));
										$jsonResponse['data']['StorageTempHum']['charts']['humidity'][0]['datasets'][$i] = 0;
									}
									
									// Default listPeriod
									$jsonResponse['data']['StorageTempHum']['listPeriod'] = array(
										'maxRange' => 100, // per date/days
										'totalPeriod' => 100, // per date/days
										'availableDate' => array(
											0 => array(
												'name' => 'Today',
												'code' => hash('md5', sprintf('%s%s', date('Y-m-d'), $APP_CORE['app_key_encrypt']))
											) // Insert current today
										)
									);

									// Default listCS
									$jsonResponse['data']['StorageTempHum']['listCS'][] = array(
										'name' => 'All',
										'code' => hash('md5', sprintf('%s%s', 'all', $APP_CORE['app_key_encrypt']))
									);

									// Default listView
									$jsonResponse['data']['StorageTempHum']['listView'] = array(
										0 => array(
											'name' => 'Compact',
											'code' => hash('md5', sprintf('%s%s', 'compact', $APP_CORE['app_key_encrypt']))
										),
										1 => array(
											'name' => 'RAW',
											'code' => hash('md5', sprintf('%s%s', 'raw', $APP_CORE['app_key_encrypt']))
										),
									);

									// Default datePicked
									$jsonResponse['data']['StorageTempHum']['datePicked'] = date('F d, Y');
									// $jsonResponse['data']['StorageTempHum']['datePicked'] = date('F d, Y', strtotime('-1 days')); // Yesterday
								}

								/*
								* Processing data
								*/
								// Serve for: ThroughputIO
								if(boolval($serveData['ThroughputIO']) === true) {
									$prefixTable = 'pallet';
									switch(strtolower(trim($dataRequest['ThroughputIO']['uom']))) {
										case 'lpn': $prefixTable = 'pallet'; break;
										case 'multi': $prefixTable = 'pcs'; break;
									}

									$queryInput = array(
										$isExistCustomer['STOREKEY']
									);
									$queryString = sprintf('
										SELECT
											CUSTOMER STOREKEY
											, SUM(QTY_IN_1) QTY_IN_1, SUM(QTY_OUT_1) QTY_OUT_1
											, SUM(QTY_IN_2) QTY_IN_2, SUM(QTY_OUT_2) QTY_OUT_2
											, SUM(QTY_IN_3) QTY_IN_3, SUM(QTY_OUT_3) QTY_OUT_3
											, SUM(QTY_IN_4) QTY_IN_4, SUM(QTY_OUT_4) QTY_OUT_4
											, SUM(QTY_IN_5) QTY_IN_5, SUM(QTY_OUT_5) QTY_OUT_5
											, SUM(QTY_IN_6) QTY_IN_6, SUM(QTY_OUT_6) QTY_OUT_6
											, SUM(QTY_IN_7) QTY_IN_7, SUM(QTY_OUT_7) QTY_OUT_7
											, SUM(QTY_IN_8) QTY_IN_8, SUM(QTY_OUT_8) QTY_OUT_8
											, SUM(QTY_IN_9) QTY_IN_9, SUM(QTY_OUT_9) QTY_OUT_9
											, SUM(QTY_IN_10) QTY_IN_10, SUM(QTY_OUT_10) QTY_OUT_10
											, SUM(QTY_IN_11) QTY_IN_11, SUM(QTY_OUT_11) QTY_OUT_11
											, SUM(QTY_IN_12) QTY_IN_12, SUM(QTY_OUT_12) QTY_OUT_12
											, SUM(QTY_IN_13) QTY_IN_13, SUM(QTY_OUT_13) QTY_OUT_13
											, SUM(QTY_IN_14) QTY_IN_14, SUM(QTY_OUT_14) QTY_OUT_14
											, SUM(QTY_IN_15) QTY_IN_15, SUM(QTY_OUT_15) QTY_OUT_15
											, SUM(QTY_IN_16) QTY_IN_16, SUM(QTY_OUT_16) QTY_OUT_16
											, SUM(QTY_IN_17) QTY_IN_17, SUM(QTY_OUT_17) QTY_OUT_17
											, SUM(QTY_IN_18) QTY_IN_18, SUM(QTY_OUT_18) QTY_OUT_18
											, SUM(QTY_IN_19) QTY_IN_19, SUM(QTY_OUT_19) QTY_OUT_19
											, SUM(QTY_IN_20) QTY_IN_20, SUM(QTY_OUT_20) QTY_OUT_20
											, SUM(QTY_IN_21) QTY_IN_21, SUM(QTY_OUT_21) QTY_OUT_21
											, SUM(QTY_IN_22) QTY_IN_22, SUM(QTY_OUT_22) QTY_OUT_22
											, SUM(QTY_IN_23) QTY_IN_23, SUM(QTY_OUT_23) QTY_OUT_23
											, SUM(QTY_IN_24) QTY_IN_24, SUM(QTY_OUT_24) QTY_OUT_24
											, SUM(QTY_IN_25) QTY_IN_25, SUM(QTY_OUT_25) QTY_OUT_25
											, SUM(QTY_IN_26) QTY_IN_26, SUM(QTY_OUT_26) QTY_OUT_26
											, SUM(QTY_IN_27) QTY_IN_27, SUM(QTY_OUT_27) QTY_OUT_27
											, SUM(QTY_IN_28) QTY_IN_28, SUM(QTY_OUT_28) QTY_OUT_28
											, SUM(QTY_IN_29) QTY_IN_29, SUM(QTY_OUT_29) QTY_OUT_29
											, SUM(QTY_IN_30) QTY_IN_30, SUM(QTY_OUT_30) QTY_OUT_30
											, SUM(QTY_IN_31) QTY_IN_31, SUM(QTY_OUT_31) QTY_OUT_31
										FROM %s WHERE CUSTOMER = ?
										GROUP BY CUSTOMER
										ORDER BY CUSTOMER;
									', 'mtp_in_out_resume_'.$prefixTable.'_'.date('ym'));
									$queryParam = str_repeat('s', count($queryInput));

									$fetchThroughputIO = db_runQuery(array(
										'config_array' => $customerDBMS,
										'database_index' => 0,
										'input' => $queryInput,
										'query' => $queryString,
										'param' => $queryParam,
										'getData' => true
									));
									if(!isEmptyVar($fetchThroughputIO) && $fetchThroughputIO !== 'ZERO_DATA') {
										$jsonResponse['success']['ThroughputIO'] = true;
										$jsonResponse['message']['ThroughputIO'] = sprintf('Data found (%s) entries!', 1);
										$jsonResponse['datetime']['ThroughputIO'] = date('Y-m-d H:i:s');
										$currentDays = date('d');
										$totalDays = date('t');
										$totalInbound = 0;
										$totalOutbound = 0;
										$customerKey = $fetchThroughputIO['STOREKEY'];
										unset($fetchThroughputIO['STOREKEY']);
										foreach($fetchThroughputIO as $key => $val) {
											$idx = preg_replace('/[^0-9]/', '', $key);
											if(intval($idx) > $totalDays || intval($idx) > $currentDays) { break; }
											$type = (wordExist(strtoupper($key), 'IN')) ? 'inbound' : 'outbound';
											$jsonResponse['data']['ThroughputIO']['charts'][intval($idx) - 1][$type] = $val;
											if($type === 'inbound') {
												$totalInbound += $val;
											}
											if($type === 'outbound') {
												$totalOutbound += $val;
											}
										}
										$jsonResponse['data']['ThroughputIO']['summary']['total_inbound'] = $totalInbound;
										$jsonResponse['data']['ThroughputIO']['summary']['total_outbound'] = $totalOutbound;
										$jsonResponse['took']['ThroughputIO'] = (floor(microtime(true)*1000))-$startTime;
										$jsonResponse['errcode']['ThroughputIO'] = 0;
									}
								}
								// Serve for: ChamberUsage
								if(boolval($serveData['ChamberUsage']) === true) {	
									// Chamber last update storage
									$queryInput = array(
										$isExistCustomer['STOREKEY']
									);
									$queryString = 'SELECT MIN(tgl_upload) AS LAST_UPLOAD FROM mtp_inventory_balance_infor_rep WHERE own = ?;';
									$queryParam = str_repeat('s', count($queryInput));
									$fetchLastUpdate = db_runQuery(array(
										'config_array' => $customerDBMS,
										'database_index' => 0,
										'input' => $queryInput,
										'query' => $queryString,
										'param' => $queryParam,
										'getData' => true,
										'getAllRow' => false
									));
									if(!isEmptyVar($fetchLastUpdate) && $fetchLastUpdate !== 'ZERO_DATA') {
										if(array_key_exists('LAST_UPLOAD', $fetchLastUpdate) && !isEmptyVar($fetchLastUpdate['LAST_UPLOAD'])) {
											$jsonResponse['data']['ChamberUsage']['charts']['lastUpdate'] = date('l, d M Y', strtotime($fetchLastUpdate['LAST_UPLOAD'])).' on '.date('H:i:s', strtotime($fetchLastUpdate['LAST_UPLOAD']));
										}
									}

									// Chamber current usage
									$queryInput = array(
										$isExistCustomer['STOREKEY'],
										$isExist['KODE_DC']
									);
									$queryString = "
										SELECT '" . $isExistCustomer['STOREKEY'] . "' CUSTOMER, X.KET CHAMBER, IFNULL(Y.total_storage,0) TOTAL_STORAGE
										FROM
										(
											SELECT DISTINCT ket FROM mtp_master_storage
										) X
										LEFT JOIN
										(
											SELECT z.customer, COUNT(*) total_storage, z.ket CHAMBER
											FROM
											(
												SELECT a.own CUSTOMER, a.location, b.KET
												FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
												WHERE DATE(a.tgl_cut_off) >= DATE(NOW() - INTERVAL 2 DAY) AND a.own = ? AND b.kode_dc = ? AND a.location = b.lokasi_storage
											) Z
											GROUP BY z.customer, z.ket
										) Y
										ON X.KET = Y.CHAMBER
										ORDER BY X.KET
									";
									$queryParam = str_repeat('s', count($queryInput));
									$fetchChamberUsage = db_runQuery(array(
										'config_array' => $customerDBMS,
										'database_index' => 0,
										'input' => $queryInput,
										'query' => $queryString,
										'param' => $queryParam,
										'getData' => true,
										'getAllRow' => true
									));
									if(!isEmptyVar($fetchChamberUsage) && $fetchChamberUsage !== 'ZERO_DATA' && count($fetchChamberUsage) >= 1) {
										$jsonResponse['success']['ChamberUsage'] = true;
										$jsonResponse['message']['ChamberUsage'] = sprintf('Data found (%s) entries!', count($fetchChamberUsage));
										$jsonResponse['datetime']['ChamberUsage'] = date('Y-m-d H:i:s');
										foreach($fetchChamberUsage as $row) {
											$chamberName = strtoupper($row['CHAMBER']);
											if(wordExist($chamberName, 'CHAMBER')) {
												$chamberName = str_replace('CHAMBER', 'CHAMBER ', $chamberName);
											} elseif(wordExist($chamberName, 'CHILLER')) {
												$chamberName = str_replace('CHILLER', 'CHILLER ', $chamberName);
											} elseif(wordExist($chamberName, 'CH')) {
												$chamberName = str_replace('CH', 'CH ', $chamberName);
											} else {
												$chamberName = null;
											}
											if($chamberName !== null) {
												$jsonResponse['data']['ChamberUsage']['labels'][] = $chamberName;
												$jsonResponse['data']['ChamberUsage']['charts']['datasets'][strtolower(str_replace(' ', '_', $chamberName))] = array(
													'dedicated' => 0,
													'dedicated_intersect' => 0,
													'available' => 0,
													'usage' => (int) $row['TOTAL_STORAGE'],
													'over' => 0,
													'over_intersect' => 0,
													'temperature' => array(
														'min' => 0,
														'max' => 0,
													)
												);
											}
										}
										$jsonResponse['took']['ChamberUsage'] = (floor(microtime(true)*1000))-$startTime;
										$jsonResponse['errcode']['ChamberUsage'] = 0;
									}

									// Chamber customer max storage
									$queryInput = array(
										$isExistCustomer['STOREKEY'],
									);
									$queryString = sprintf('SELECT CHAMBER, JML_STORAGE AS MAX_STORAGE FROM %s WHERE CUSTOMER = ?;', 'mtp_master_dedicated_storage');
									$queryParam = str_repeat('s', count($queryInput));
									$fetchMaxStorage = db_runQuery(array(
										'config_array' => $customerDBMS,
										'database_index' => 0,
										'input' => $queryInput,
										'query' => $queryString,
										'param' => $queryParam,
										'getData' => true,
										'getAllRow' => true
									));
									if(!isEmptyVar($fetchMaxStorage) && $fetchMaxStorage !== 'ZERO_DATA' && count($fetchMaxStorage) >= 1) {
										$higherValue = 0;
										foreach($fetchMaxStorage as $row) {
											$chamberName = strtoupper($row['CHAMBER']);
											if(wordExist($chamberName, 'CHAMBER')) {
												$chamberName = str_replace('CHAMBER', 'CHAMBER ', $chamberName);
											} elseif(wordExist($chamberName, 'CHILLER')) {
												$chamberName = str_replace('CHILLER', 'CHILLER ', $chamberName);
											} elseif(wordExist($chamberName, 'CH')) {
												$chamberName = str_replace('CH', 'CH ', $chamberName);
											} else {
												$chamberName = null;
											}
											if($chamberName !== null) {
												// Asphira Andreas <arechta911@gmail.com>
												// Dev Note: Special conditions, because the data is not fixed
												$isSingleName = false;
												if(count(explode(' ', trim($chamberName))) < 2) {
													$isSingleName = true;
													$chamberName = trim($chamberName);
												}
												if(!$isSingleName) {
													$chamberName = str_replace(' ', '_', $chamberName);
												}
												$chamberName = trim(strtolower($chamberName));
												$dedicatedStorage = (int) $row['MAX_STORAGE'];
												if(array_key_exists($chamberName, $jsonResponse['data']['ChamberUsage']['charts']['datasets'])) {
													$chamberSelected = $jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName];
													if($dedicatedStorage !== 0) {
														if($dedicatedStorage >= $chamberSelected['usage']) {
															$chamberAvailable = $dedicatedStorage - (int) $chamberSelected['usage'];
															$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['available'] = $chamberAvailable;
															if(is_int($chamberAvailable) && (int) $chamberAvailable >= 1) {
																$jsonResponse['data']['ChamberUsage']['charts']['total']['available'] += (int) $chamberAvailable;
															}
														}
														if($dedicatedStorage < $chamberSelected['usage']) {
															$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['available'] = 0;
															$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['over'] = (int) $chamberSelected['usage'] - $dedicatedStorage;
															$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['over_intersect'] = 0;
															// $jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['usage'] = $dedicatedStorage;
														}
														$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated'] = $dedicatedStorage;
														$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated_intersect'] = 0;

														$jsonResponse['data']['ChamberUsage']['charts']['total']['usage'] += (int) $chamberSelected['usage'];
													} else {
														$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName] = array(
															'dedicated' => 0,
															'dedicated_intersect' => 0,
															'available' => 0,
															'usage' => 0,
															'over' => 0,
															'over_intersect' => 0,
															'temperature' => array(
																'min' => 0,
																'max' => 0
															)
														);
													}
												} else {
													// Asphira Andreas <arechta911@gmail.com>
													// Dev Note: Special conditions, because the data is not fixed
													if($isSingleName && strtoupper($chamberName) === 'CHILLER') {
														$chamberName = 'ch_01';
														if(array_key_exists($chamberName, $jsonResponse['data']['ChamberUsage']['charts']['datasets'])) {
															$chamberSelected = $jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName];
															if($dedicatedStorage !== 0) {
																if($dedicatedStorage >= $chamberSelected['usage']) {
																	$chamberAvailable = $dedicatedStorage - (int) $chamberSelected['usage'];
																	$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['available'] = $chamberAvailable;
																	if(is_int($chamberAvailable) && (int) $chamberAvailable >= 1) {
																		$jsonResponse['data']['ChamberUsage']['charts']['total']['available'] += (int) $chamberAvailable;
																	}
																}
																if($dedicatedStorage < $chamberSelected['usage']) {
																	$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['available'] = 0;
																	$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['over'] = (int) $chamberSelected['usage'] - $dedicatedStorage;
																	$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['over_intersect'] = 0;
																	// $jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['usage'] = $dedicatedStorage;
																}
																$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated'] = $dedicatedStorage;
																$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated_intersect'] = 0;

																$jsonResponse['data']['ChamberUsage']['charts']['total']['usage'] += (int) $chamberSelected['usage'];
															} else {
																$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName] = array(
																	'dedicated' => 0,
																	'dedicated_intersect' => 0,
																	'available' => 0,
																	'usage' => 0,
																	'over' => 0,
																	'over_intersect' => 0,
																	'temperature' => array(
																		'min' => 0,
																		'max' => 0,
																	)
																);
															}
														}											
													}
												}
											}
										}
										$jsonResponse['message']['ChamberUsage'] = sprintf('Data found (%s) entries!', count($fetchChamberUsage));
										$jsonResponse['datetime']['ChamberUsage'] = date('Y-m-d H:i:s');
										$jsonResponse['took']['ChamberUsage'] = (floor(microtime(true)*1000))-$startTime;
										$jsonResponse['errcode']['ChamberUsage'] = 0;
									}

									// Chamber dedicated storage
									/*
									$queryString = sprintf('SELECT ket AS CHAMBER, COUNT(*) AS TOTAL_STORAGE FROM %s GROUP BY ket;', 'mtp_master_storage');
									$fetchDedicatedStorage = db_runQuery(array(
										'config_array' => $configMysql,
										'database_index' => 2, // db.mtp
										'query' => $queryString,
										'getData' => true,
										'getAllRow' => true
									));
									if(!isEmptyVar($fetchDedicatedStorage) && $fetchDedicatedStorage !== 'ZERO_DATA' && count($fetchDedicatedStorage) >= 1) {
										foreach($fetchDedicatedStorage as $row) {
											$chamberName = strtoupper($row['CHAMBER']);
											if(wordExist($chamberName, 'CHAMBER')) {
												$chamberName = str_replace('CHAMBER', 'CHAMBER ', $chamberName);
											} elseif(wordExist($chamberName, 'CHILLER')) {
												$chamberName = str_replace('CHILLER', 'CHILLER ', $chamberName);
											} elseif(wordExist($chamberName, 'CH')) {
												$chamberName = str_replace('CH', 'CH ', $chamberName);
											} else {
												$chamberName = null;
											}
											if($chamberName !== null) {
												// Asphira Andreas <arechta911@gmail.com>
												// Dev Note: Special conditions, because the data is not fixed
												$isSingleName = false;
												if(count(explode(' ', trim($chamberName))) < 2) {
													$isSingleName = true;
													$chamberName = trim($chamberName);
												}
												if(!$isSingleName) {
													$chamberName = str_replace(' ', '_', $chamberName);
												}
												$chamberName = trim(strtolower($chamberName));
												$dedicatedStorage = (int) $row['TOTAL_STORAGE'];
												if(array_key_exists($chamberName, $jsonResponse['data']['ChamberUsage']['charts']['datasets'])) {
													if($jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated'] === 0) {
														$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated'] = $dedicatedStorage;
														$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated_intersect'] = $dedicatedStorage;
													}
												}
											}
										}
									}
									*/

									// Chamber temperature
									$queryString = "SELECT * FROM mtp_mesin_temperature GROUP BY KET ASC;";
									$fetchChamberTemperature = db_runQuery(array(
										'config_array' => $customerDBMS,
										'database_index' => 0,
										'query' => $queryString,
										'getData' => true,
										'getAllRow' => true
									));
									if(!isEmptyVar($fetchChamberTemperature) && $fetchChamberTemperature !== 'ZERO_DATA') {
										foreach($fetchChamberTemperature as $row) {
											$chamberName = strtoupper($row['KET']);
											if(wordExist($chamberName, 'CHAMBER')) {
												$chamberName = str_replace('CHAMBER', 'CHAMBER ', $chamberName);
											} elseif(wordExist($chamberName, 'CHILLER')) {
												$chamberName = str_replace('CHILLER', 'CHILLER ', $chamberName);
											} elseif(wordExist($chamberName, 'CH')) {
												$chamberName = str_replace('CH', 'CH ', $chamberName);
											} else {
												$chamberName = null;
											}
											if($chamberName !== null) {
												// Asphira Andreas <arechta911@gmail.com>
												// Dev Note: Special conditions, because the data is not fixed
												$isSingleName = false;
												if(count(explode(' ', trim($chamberName))) < 2) {
													$isSingleName = true;
													$chamberName = trim($chamberName);
												}
												if(!$isSingleName) {
													$chamberName = str_replace(' ', '_', $chamberName);
												}
												$chamberName = trim(strtolower($chamberName));
												if(array_key_exists($chamberName, $jsonResponse['data']['ChamberUsage']['charts']['datasets'])) {
													$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['temperature']['min'] = $row['TEMP_MIN'];
													$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['temperature']['max'] = $row['TEMP_MAX'];
												}
											}
										}
									}
									$jsonResponse['datetime']['ChamberUsage'] = date('Y-m-d H:i:s');
									$jsonResponse['took']['ChamberUsage'] = (floor(microtime(true)*1000))-$startTime;
								}
								// Serve for: StorageTempHum
								if(boolval($serveData['StorageTempHum']) === true) {
									// Fetch list of available Period
									$queryString = sprintf('SELECT DISTINCT(DATE(TGL)) AS DATE_PERIOD FROM %s GROUP BY DATE(TGL) ORDER BY TGL DESC LIMIT 0,%s;', 'mtp_report_temperature', $jsonResponse['data']['StorageTempHum']['listPeriod']['maxRange']);
									$fetchAvailablePeriod = db_runQuery(array(
										'config_array' => $customerDBMS,
										'database_index' => 0,
										'query' => $queryString,
										'getData' => true,
										'getAllRow' => true
									));
									if(!isEmptyVar($fetchAvailablePeriod) && $fetchAvailablePeriod !== 'ZERO_DATA' && count($fetchAvailablePeriod) >= 1) {
										$jsonResponse['success']['StorageTempHum']['listPeriod'] = 0;
										foreach($fetchAvailablePeriod as $perRow) {
											$jsonResponse['success']['StorageTempHum']['listPeriod'] += 1;
											if(!in_array($perRow['DATE_PERIOD'], $jsonResponse['data']['StorageTempHum']['listPeriod']['availableDate'])) {
												$jsonResponse['data']['StorageTempHum']['listPeriod']['availableDate'][] = array(
													'name' => $perRow['DATE_PERIOD'],
													'code' => hash('md5', sprintf('%s%s', $perRow['DATE_PERIOD'], $APP_CORE['app_key_encrypt']))
												);
											}
										}
										$jsonResponse['data']['StorageTempHum']['listPeriod']['totalPeriod'] = count($fetchAvailablePeriod);
									}

									$jsonResponse['success']['StorageTempHum']['listCS'] = 0;
									// Fetch list of available Cold Storage (CS)
									$queryString = sprintf('SELECT DISTINCT(MESIN) AS CS_NAME FROM %s GROUP BY MESIN;', 'mtp_report_temperature');
									$fetchAvailableCS = db_runQuery(array(
										'config_array' => $customerDBMS,
										'database_index' => 0,
										'query' => $queryString,
										'getData' => true,
										'getAllRow' => true
									));
									if(!isEmptyVar($fetchAvailableCS) && $fetchAvailableCS !== 'ZERO_DATA' && count($fetchAvailableCS) >= 1) {
										foreach($fetchAvailableCS as $perRow) {
											$jsonResponse['success']['StorageTempHum']['listCS'] += 1;
											$jsonResponse['data']['StorageTempHum']['listCS'][] = array(
												'name' => $perRow['CS_NAME'],
												'code' => hash('md5', sprintf('%s%s', $perRow['CS_NAME'], $APP_CORE['app_key_encrypt']))
											);
										}
									}
									// Fetch list of available Cold Storage (CS) MEKAWA
									$transformCSToChamber = function($data, $reverse = false) {
										$result = trim($data);
										if($reverse == false) {
											if($result == 'CS1') { return 'CHAMBER A'; }
											if($result == 'CS2') { return 'CHAMBER B'; }
											if($result == 'CS3') { return 'CHAMBER C'; }
											if($result == 'CS4') { return 'CHAMBER D'; }
											if($result == 'CS5') { return 'CHAMBER E'; }
											if($result == 'CS6') { return 'CHAMBER F'; }
											if($result == 'CS7') { return 'CHAMBER G'; }
											if($result == 'CS8') { return 'CHAMBER H'; }
											if($result == 'CS9') { return 'CHAMBER I'; }
											if($result == 'CS10') { return 'CHAMBER J'; }
											if($result == 'CS11') { return 'CHAMBER K'; }
										} else {
											if($result == 'CHAMBER A') { return 'CS2'; }
											if($result == 'CHAMBER B') { return 'CS1'; }
											if($result == 'CHAMBER C') { return 'CS3'; }
											if($result == 'CHAMBER D') { return 'CS4'; }
											if($result == 'CHAMBER E') { return 'CS5'; }
											if($result == 'CHAMBER F') { return 'CS6'; }
											if($result == 'CHAMBER G') { return 'CS7'; }
											if($result == 'CHAMBER H') { return 'CS8'; }
											if($result == 'CHAMBER I') { return 'CS9'; }
											if($result == 'CHAMBER J') { return 'CS10'; }
											if($result == 'CHAMBER K') { return 'CS11'; }
										}
										return $result;
									};
									$queryString = sprintf('SELECT DISTINCT(MESIN) AS CS_NAME FROM %s GROUP BY MESIN;', 'mtp_report_temperature_mkw');
									$fetchAvailableCSMKW = db_runQuery(array(
										'config_array' => $customerDBMS,
										'database_index' => 0,
										'query' => $queryString,
										'getData' => true,
										'getAllRow' => true
									));
									if(!isEmptyVar($fetchAvailableCSMKW) && $fetchAvailableCSMKW !== 'ZERO_DATA' && count($fetchAvailableCSMKW) >= 1) {
										foreach($fetchAvailableCSMKW as $idx => $perRow) {
											$perRow['CS_NAME'] = $transformCSToChamber($perRow['CS_NAME']);

											$isExistItem = searchArrAssoc($jsonResponse['data']['StorageTempHum']['listCS'], 'name', $perRow['CS_NAME']);
											if(count($isExistItem) == 0) {
												$jsonResponse['success']['StorageTempHum']['listCS'] += 1;
												$jsonResponse['data']['StorageTempHum']['listCS'][] = array(
													'name' => $perRow['CS_NAME'],
													'code' => hash('md5', sprintf('%s%s', $perRow['CS_NAME'], $APP_CORE['app_key_encrypt']))
												);
											}
										}
									}

									// Fetch datasets
									$queryInput = array();
									if(!isEmptyVar($dataRequest['StorageTempHum']['period'])) {
										$isFound = searchArrAssoc($jsonResponse['data']['StorageTempHum']['listPeriod']['availableDate'], 'code', $dataRequest['StorageTempHum']['period']) ?? null;
										if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
											if(strtolower(trim($isFound[0]['name'])) === 'today') {
												$queryInput[] = date('Y-m-d'); // Current today
												// $queryInput[] = date('Y-m-d', strtotime('-1 days')); // Get yesterday
											} else {
												$queryInput[] = $isFound[0]['name']; // YYYY-MM-DD
												$jsonResponse['data']['StorageTempHum']['datePicked'] = date('F d, Y', strtotime($isFound[0]['name']));
											}
										} else {
											$queryInput[] = date('Y-m-d'); // Current today
											// $queryInput[] = date('Y-m-d', strtotime('-1 days')); // Get yesterday
										}
									} else {
										$queryInput[] = date('Y-m-d'); // Current today
										// $queryInput[] = date('Y-m-d', strtotime('-1 days')); // Get yesterday
									}
									$queryStringAdd = '';
									if(!isEmptyVar($dataRequest['StorageTempHum']['cs'])) {
										if($dataRequest['StorageTempHum']['cs'] !== $jsonResponse['data']['StorageTempHum']['listCS'][0]['code']) {
											$isFound = searchArrAssoc($jsonResponse['data']['StorageTempHum']['listCS'], 'code', $dataRequest['StorageTempHum']['cs']) ?? null;
											if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
												// $queryStringAdd = 'AND MESIN = ?';
												$queryStringAdd = 'WHERE CS_NAME = ?';
												$queryInput[] = $isFound[0]['name'];
											}
											// else {
											// 	$queryInput[] = $jsonResponse['data']['coldStorageTemperature']['listCS'][0]['name'];
											// }
										}
									}
									// $queryString = sprintf('SELECT MESIN AS CS_NAME, TGL AS DATE_PERIOD, SUHU AS TEMPERATURE, HUMIDITY FROM %s WHERE DATE(TGL) = ? %s ORDER BY MESIN, TGL;', 'mtp_report_temperature', $queryStringAdd);
									/*
									$queryString = "
									SELECT XYZ.* FROM (
										SELECT MESIN AS CS_NAME, TGL AS DATE_PERIOD, SUHU AS TEMPERATURE, HUMIDITY
										FROM mtp_report_temperature WHERE DATE(TGL) = ?
										UNION
										SELECT
												IF(mesin='CS1','CHAMBER A',
												IF(mesin='CS2','CHAMBER B', 
												IF(mesin='CS3','CHAMBER C',
												IF(mesin='CS4','CHAMBER D',
												IF(mesin='CS5','CHAMBER E',
												IF(mesin='CS6','CHAMBER F',
												IF(mesin='CS7','CHAMBER G',
												IF(mesin='CS8','CHAMBER H',
												IF(mesin='CS9','CHAMBER I',
												IF(mesin='CS10','CHAMBER J',
												IF(mesin='CS11','CHAMBER K',
												mesin))))))))))) AS CS_NAME,
										TGL AS DATE_PERIOD, SUHU AS TEMPERATURE, HUMIDITY
										FROM mtp_report_temperature_mkw_" . date('ymd', strtotime($queryInput[0])) . "
										WHERE TIME_FORMAT(TGL,'%i') MOD 5 = 0
										ORDER BY CS_NAME, DATE_PERIOD
									) XYZ " . $queryStringAdd;
									*/
									$queryInput[] = $queryInput[0];
									$queryInput[] = $isExist['KODE_STOREKEY'];
									$queryString = "
										SELECT XYZ.* FROM (
											SELECT MESIN AS CS_NAME, TGL AS DATE_PERIOD, SUHU AS TEMPERATURE, HUMIDITY
											FROM mtp_report_temperature WHERE DATE(TGL) = ?
											UNION
											SELECT
													IF(mesin='CS1','CHAMBER A',
													IF(mesin='CS2','CHAMBER B', 
													IF(mesin='CS3','CHAMBER C',
													IF(mesin='CS4','CHAMBER D',
													IF(mesin='CS5','CHAMBER E',
													IF(mesin='CS6','CHAMBER F',
													IF(mesin='CS7','CHAMBER G',
													IF(mesin='CS8','CHAMBER H',
													IF(mesin='CS9','CHAMBER I',
													IF(mesin='CS10','CHAMBER J',
													IF(mesin='CS11','CHAMBER K',
													mesin))))))))))) AS CS_NAME,
											TGL AS DATE_PERIOD, SUHU AS TEMPERATURE, HUMIDITY
											FROM mtp_report_temperature_mkw_" . date('ymd', strtotime($queryInput[0])) . "
											WHERE TIME_FORMAT(TGL,'%i') MOD 5 = 0
											ORDER BY CS_NAME, DATE_PERIOD
										) XYZ
										WHERE XYZ.CS_NAME IN (
										SELECT DISTINCT FF.CS_NAME
										FROM
										(
										SELECT BBB.*, TTT.MESIN_1, TTT.MESIN_2, TTT.MESIN_3, TTT.MESIN_4, TTT.MESIN_5,
											TTT.MESIN_6, TTT.MESIN_7, TTT.MESIN_8, TTT.MESIN_9, TTT.MESIN_10,
											TTT.MESIN_11
										FROM
										(
										SELECT 1 DT, XYZ.* FROM (
											SELECT MESIN AS CS_NAME, TGL AS DATE_PERIOD, SUHU AS TEMPERATURE, HUMIDITY
											FROM mtp_report_temperature WHERE DATE(TGL) = ?
											UNION
											SELECT
													IF(mesin='CS1','CHAMBER A',
													IF(mesin='CS2','CHAMBER B', 
													IF(mesin='CS3','CHAMBER C',
													IF(mesin='CS4','CHAMBER D',
													IF(mesin='CS5','CHAMBER E',
													IF(mesin='CS6','CHAMBER F',
													IF(mesin='CS7','CHAMBER G',
													IF(mesin='CS8','CHAMBER H',
													IF(mesin='CS9','CHAMBER I',
													IF(mesin='CS10','CHAMBER J',
													IF(mesin='CS11','CHAMBER K',
													mesin))))))))))) AS CS_NAME,
											TGL AS DATE_PERIOD, SUHU AS TEMPERATURE, HUMIDITY
											FROM mtp_report_temperature_mkw_" . date('ymd', strtotime($queryInput[0])) . "
											WHERE TIME_FORMAT(TGL,'%i') MOD 5 = 0
											ORDER BY CS_NAME, DATE_PERIOD
										) XYZ
										) BBB
										LEFT JOIN
										(
										SELECT 1 DT, OWN, 
											IF(CHAMBERA>0,'CHAMBER A','') MESIN_1, 
											IF(CHAMBERB>0,'CHAMBER B','') MESIN_2, 
											IF(CHAMBERC>0,'CHAMBER C','') MESIN_3, 
											IF(CHAMBERD>0,'CHAMBER D','') MESIN_4, 
											IF(CHAMBERE>0,'CHAMBER E','') MESIN_5, 
											IF(CHAMBERF>0,'CHAMBER F','') MESIN_6, 
											IF(CHAMBERG>0,'CHAMBER G','') MESIN_7, 
											IF(CHAMBERH>0,'CHAMBER H','') MESIN_8, 
											IF(CHAMBERI>0,'CHAMBER I','') MESIN_9, 
											IF(CHAMBERJ>0,'CHAMBER J','') MESIN_10, 
											IF(CHAMBERK>0,'CHAMBER K','') MESIN_11 
										FROM mtp_dedicate_chamber
										WHERE OWN = ? 
										) TTT
										ON BBB.DT = TTT.DT
										) FF
										WHERE FF.CS_NAME = FF.MESIN_1 OR
											FF.CS_NAME = FF.MESIN_2 OR
											FF.CS_NAME = FF.MESIN_3 OR
											FF.CS_NAME = FF.MESIN_4 OR
											FF.CS_NAME = FF.MESIN_5 OR
											FF.CS_NAME = FF.MESIN_6 OR
											FF.CS_NAME = FF.MESIN_7 OR
											FF.CS_NAME = FF.MESIN_8 OR
											FF.CS_NAME = FF.MESIN_9 OR
											FF.CS_NAME = FF.MESIN_10 OR
											FF.CS_NAME = FF.MESIN_11 
										)
										ORDER BY XYZ.CS_NAME;
									";
									$queryParam = str_repeat('s', count($queryInput));

									$fetchChartData = db_runQuery(array(
										'config_array' => $customerDBMS,
										'database_index' => 0,
										'input' => $queryInput,
										'query' => $queryString,
										'param' => $queryParam,
										'getData' => true,
										'getAllRow' => true
									));

									if(!isEmptyVar($fetchChartData) && $fetchChartData !== 'ZERO_DATA' && count($fetchChartData) >= 1) {
										$presentData = array(
											'temperature' => array(),
											'humidity' => array()
										);

										if(!isEmptyVar($dataRequest['StorageTempHum']['view'])) {
											$isFound = searchArrAssoc($jsonResponse['data']['StorageTempHum']['listView'], 'code', $dataRequest['StorageTempHum']['view']) ?? null;
											if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
												$isFound = strtolower(trim($isFound[0]['name']));
											} else {
												$isFound = 'compact';
											}

											switch($isFound) {
												default:
												case 'compact':
													foreach($fetchChartData as $perRow) {
														if(!array_key_exists($perRow['CS_NAME'], $presentData['temperature']) && !array_key_exists($perRow['CS_NAME'], $presentData['temperature'])) {
															$presentData['temperature'][$perRow['CS_NAME']] = array(
																'labels' => array(),
																'datasets' => array(),
															);
															$presentData['humidity'][$perRow['CS_NAME']] = array(
																'labels' => array(),
																'datasets' => array(),
															);
															for ($i = 0; $i < 24; $i++) {
																$presentData['temperature'][$perRow['CS_NAME']]['labels'][$i] = sprintf('%s:00', str_pad($i, 2, '0', STR_PAD_LEFT));
																$presentData['temperature'][$perRow['CS_NAME']]['datasets'][$i] = array(
																	'total' => 0,
																	'count' => 0,
																);
																$presentData['humidity'][$perRow['CS_NAME']]['labels'][$i] = sprintf('%s:00', str_pad($i, 2, '0', STR_PAD_LEFT));
																$presentData['humidity'][$perRow['CS_NAME']]['datasets'][$i] = array(
																	'total' => 0,
																	'count' => 0,
																);
															}
														}
														$hourIn24 = (int) trim(date('H', strtotime($perRow['DATE_PERIOD'])));
														$presentData['temperature'][$perRow['CS_NAME']]['datasets'][$hourIn24]['total'] += (!isEmptyVar($perRow['TEMPERATURE'])) ? (float) $perRow['TEMPERATURE'] : 0;
														$presentData['temperature'][$perRow['CS_NAME']]['datasets'][$hourIn24]['count'] += 1;
														$presentData['humidity'][$perRow['CS_NAME']]['datasets'][$hourIn24]['total'] += (!isEmptyVar($perRow['HUMIDITY'])) ? (float) $perRow['HUMIDITY'] : 0;
														$presentData['humidity'][$perRow['CS_NAME']]['datasets'][$hourIn24]['count'] += 1;
													}
													if(count($presentData['temperature']) >= 1) {
														foreach($presentData['temperature'] as $csName => $perData) {
															if(is_array($perData) && array_key_exists('datasets', $perData)) {
																foreach($perData['datasets'] as $idx => $val) {
																	if($val['count'] >= 1) {
																		$presentData['temperature'][$csName]['datasets'][$idx] = $val['total'] / $val['count'] ;
																	} else {
																		$presentData['temperature'][$csName]['datasets'][$idx] = 0;
																	}
																}
															}
														}
													}
													if(count($presentData['humidity']) >= 1) {
														foreach($presentData['humidity'] as $csName => $perData) {
															if(is_array($perData) && array_key_exists('datasets', $perData)) {
																foreach($perData['datasets'] as $idx => $val) {
																	if($val['total'] >= 0 && $val['count'] >= 1) {
																		$presentData['humidity'][$csName]['datasets'][$idx] = $val['total'] / $val['count'] ;
																	} else {
																		$presentData['humidity'][$csName]['datasets'][$idx] = 0;
																	}
																}
															}
														}
													}
													break;
												case 'raw':
													foreach($fetchChartData as $perRow) {
														if(!array_key_exists($perRow['CS_NAME'], $presentData['temperature']) && !array_key_exists($perRow['CS_NAME'], $presentData['humidity'])) {
															$presentData['temperature'][$perRow['CS_NAME']] = array(
																'labels' => array(),
																'datasets' => array(),
															);
															$presentData['humidity'][$perRow['CS_NAME']] = array(
																'labels' => array(),
																'datasets' => array(),
															);
														}
														$labelHourMinute = date('H:i', strtotime($perRow['DATE_PERIOD']));
														if(!in_array($labelHourMinute, $presentData['temperature'][$perRow['CS_NAME']]['labels'])) {
															$presentData['temperature'][$perRow['CS_NAME']]['labels'][] = $labelHourMinute;
															$presentData['temperature'][$perRow['CS_NAME']]['datasets'][] = $perRow['TEMPERATURE'];
														}
														if(!in_array($labelHourMinute, $presentData['humidity'][$perRow['CS_NAME']]['labels'])) {
															$presentData['humidity'][$perRow['CS_NAME']]['labels'][] = $labelHourMinute;
															$presentData['humidity'][$perRow['CS_NAME']]['datasets'][] = $perRow['HUMIDITY'];
														}
													}
													break;
											}

											if(count($presentData['temperature']) >= 1) {
												$jsonResponse['data']['StorageTempHum']['charts']['temperature'] = array();
												foreach($presentData['temperature'] as $csName => $perData) {
													$stableRate = array_filter($perData['datasets'], function($val) { return $val != 0; });
													$jsonResponse['success']['StorageTempHum']['charts']['temperature'] += 1;
													$jsonResponse['data']['StorageTempHum']['charts']['temperature'][] = array(
														'name' => $csName,
														'label' => $perData['labels'],
														'datasets' => $perData['datasets'],
														// 'stableRate' => $stableRate,
														'stableRate' => (float) @sprintf('%.02f', array_sum($stableRate) / count($stableRate)),
													);
												}
											}
											if(count($presentData['humidity']) >= 1) {
												$jsonResponse['data']['StorageTempHum']['charts']['humidity'] = array();
												foreach($presentData['humidity'] as $csName => $perData) {
													$stableRate = array_filter($perData['datasets'], function($val) { return $val > 0; });
													$jsonResponse['success']['StorageTempHum']['charts']['humidity'] += 1;
													$jsonResponse['data']['StorageTempHum']['charts']['humidity'][] = array(
														'name' => $csName,
														'label' => $perData['labels'],
														'datasets' => $perData['datasets'],
														'stableRate' => (float) @sprintf('%.02f', array_sum($stableRate) / count($stableRate)),
													);
												}
											}
										}
									}
								}

								/*
								* Finishing response
								*/
								// Serve for: ThroughputIO
								if(boolval($serveData['ThroughputIO']) === true) {
									// Result in JSON
									if(count($jsonResponse['data']['ThroughputIO']) >= 1) {
										$jsonResponse['message']['ThroughputIO'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['ThroughputIO']));
										$jsonResponse['success']['ThroughputIO'] = array('charts' => true);
										$jsonResponse['errcode']['ThroughputIO'] = array('charts' => 0);
									}
								}
								// Serve for: ChamberUsage
								if(boolval($serveData['ChamberUsage']) === true) {
									// Result in JSON
									if(count($jsonResponse['data']['ChamberUsage']['charts']['datasets']) >= 1) {
										$jsonResponse['message']['ChamberUsage'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['ChamberUsage']['charts']['datasets']));
										$jsonResponse['success']['ChamberUsage'] = true;
										$jsonResponse['errcode']['ChamberUsage'] = 0;

										$idx = 0;
										foreach($jsonResponse['data']['ChamberUsage']['charts']['datasets'] as $storageKey => $storageVal) {
											if($storageVal['dedicated'] === 0 && $storageVal['usage'] === 0) {
												unset($jsonResponse['data']['ChamberUsage']['charts']['datasets'][$storageKey]);
												unset($jsonResponse['data']['ChamberUsage']['labels'][$idx]);
											}
											$idx++;
										}
										$jsonResponse['data']['ChamberUsage']['labels'] = array_values($jsonResponse['data']['ChamberUsage']['labels']);
									}
								}
								// Serve for: StorageTempHum
								if(boolval($serveData['StorageTempHum']) === true) {
									// Result fix JSON
									if(intval($jsonResponse['success']['StorageTempHum']['listPeriod']) >= 1 && count($jsonResponse['data']['StorageTempHum']['listPeriod']['availableDate']) >= 1) {
										$jsonResponse['success']['StorageTempHum']['listPeriod'] = true;
										$jsonResponse['message']['StorageTempHum']['listPeriod'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['StorageTempHum']['listPeriod']['availableDate']));
										$jsonResponse['errcode']['StorageTempHum']['listPeriod'] = 0;
									}
									if(intval($jsonResponse['success']['StorageTempHum']['listCS']) >= 1 && count($jsonResponse['data']['StorageTempHum']['listCS']) >= 1) {
										$jsonResponse['success']['StorageTempHum']['listCS'] = true;
										$jsonResponse['message']['StorageTempHum']['listCS'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['StorageTempHum']['listCS']));
										$jsonResponse['errcode']['StorageTempHum']['listCS'] = 0;
									}
									if(intval($jsonResponse['success']['StorageTempHum']['charts']['temperature']) >= 1 && count($jsonResponse['data']['StorageTempHum']['charts']['temperature']) >= 1) {
										$jsonResponse['success']['StorageTempHum']['charts']['temperature'] = true;
										$jsonResponse['message']['StorageTempHum']['charts']['temperature'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['StorageTempHum']['charts']['temperature']));
										$jsonResponse['errcode']['StorageTempHum']['charts']['temperature'] = 0;
									}
									if(intval($jsonResponse['success']['StorageTempHum']['charts']['humidity']) >= 1 && count($jsonResponse['data']['StorageTempHum']['charts']['humidity']) >= 1) {
										$jsonResponse['success']['StorageTempHum']['charts']['humidity'] = true;
										$jsonResponse['message']['StorageTempHum']['charts']['humidity'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['StorageTempHum']['charts']['humidity']));
										$jsonResponse['errcode']['StorageTempHum']['charts']['humidity'] = 0;
									}
								}
							} else {
								$jsonResponse['success'] = false;
								$jsonResponse['message'] = 'Customer not found in registered DC or not yet active!';
								$jsonResponse['datetime'] = date('Y-m-d H:i:s');
								unset($jsonResponse['data']);
								$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
								$jsonResponse['errcode'] = 6;
								unset($jsonResponse['errors']);
							}
						} else {
							$jsonResponse['success'] = false;
							$jsonResponse['message'] = 'Error, configuration database not found with code provided, (code DC)!';
							$jsonResponse['datetime'] = date('Y-m-d H:i:s');
							unset($jsonResponse['data']);
							$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode'] = 5;
							unset($jsonResponse['errors']);
						}
					} else {
						$jsonResponse['success'] = false;
						$jsonResponse['message'] = 'The customer is not yet officially active, (code DC)!';
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						unset($jsonResponse['data']);
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 4;
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
		case 'throughput-io':
			/*
			 * Stage: 1
			 * Check if the user is online and has a NIK
			 */
			if(!isEmptyVar($userData->nik)) {
				/*
				 * Stage: 2 A
				 * Check if the account is registered with the current NIK
				 */
				$isExist = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik),
					'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) as TOTAL, NAMA, NAMA_PERUSAHAAN, KODE_STOREKEY FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
					'param' => 's',
					'getData' => true,
					'getAllRow' => false
				));

				/*
				 * Stage: 2 B
				 * Check if the account is had access to some privileges
				 */
				$isPartPrivileges = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik, 0, 'c'),
					'query' => sprintf('SELECT * FROM %s WHERE NIK = ? AND (LEVEL = ? OR LEVEL_CODE = ?);', $APP_CORE['tb_prefix'].'user_privileges'),
					'param' => 'sss',
					'getData' => true,
					'getAllRow' => false
				));
				if(isset($isExist['TOTAL']) && $isExist['TOTAL'] >= 1 && !isEmptyVar($isPartPrivileges) && $isPartPrivileges !== 'ZERO_DATA') {
					$queryInput = array(
						$isExist['KODE_STOREKEY']
					);
					$queryString = '
					SELECT
						SUM(QTY_IN_1) QTY_IN_1, SUM(QTY_OUT_1) QTY_OUT_1
						, SUM(QTY_IN_2) QTY_IN_2, SUM(QTY_OUT_2) QTY_OUT_2
						, SUM(QTY_IN_3) QTY_IN_3, SUM(QTY_OUT_3) QTY_OUT_3
						, SUM(QTY_IN_4) QTY_IN_4, SUM(QTY_OUT_4) QTY_OUT_4
						, SUM(QTY_IN_5) QTY_IN_5, SUM(QTY_OUT_5) QTY_OUT_5
						, SUM(QTY_IN_6) QTY_IN_6, SUM(QTY_OUT_6) QTY_OUT_6
						, SUM(QTY_IN_7) QTY_IN_7, SUM(QTY_OUT_7) QTY_OUT_7
						, SUM(QTY_IN_8) QTY_IN_8, SUM(QTY_OUT_8) QTY_OUT_8
						, SUM(QTY_IN_9) QTY_IN_9, SUM(QTY_OUT_9) QTY_OUT_9
						, SUM(QTY_IN_10) QTY_IN_10, SUM(QTY_OUT_10) QTY_OUT_10
						, SUM(QTY_IN_11) QTY_IN_11, SUM(QTY_OUT_11) QTY_OUT_11
						, SUM(QTY_IN_12) QTY_IN_12, SUM(QTY_OUT_12) QTY_OUT_12
						, SUM(QTY_IN_13) QTY_IN_13, SUM(QTY_OUT_13) QTY_OUT_13
						, SUM(QTY_IN_14) QTY_IN_14, SUM(QTY_OUT_14) QTY_OUT_14
						, SUM(QTY_IN_15) QTY_IN_15, SUM(QTY_OUT_15) QTY_OUT_15
						, SUM(QTY_IN_16) QTY_IN_16, SUM(QTY_OUT_16) QTY_OUT_16
						, SUM(QTY_IN_17) QTY_IN_17, SUM(QTY_OUT_17) QTY_OUT_17
						, SUM(QTY_IN_18) QTY_IN_18, SUM(QTY_OUT_18) QTY_OUT_18
						, SUM(QTY_IN_19) QTY_IN_19, SUM(QTY_OUT_19) QTY_OUT_19
						, SUM(QTY_IN_20) QTY_IN_20, SUM(QTY_OUT_20) QTY_OUT_20
						, SUM(QTY_IN_21) QTY_IN_21, SUM(QTY_OUT_21) QTY_OUT_21
						, SUM(QTY_IN_22) QTY_IN_22, SUM(QTY_OUT_22) QTY_OUT_22
						, SUM(QTY_IN_23) QTY_IN_23, SUM(QTY_OUT_23) QTY_OUT_23
						, SUM(QTY_IN_24) QTY_IN_24, SUM(QTY_OUT_24) QTY_OUT_24
						, SUM(QTY_IN_25) QTY_IN_25, SUM(QTY_OUT_25) QTY_OUT_25
						, SUM(QTY_IN_26) QTY_IN_26, SUM(QTY_OUT_26) QTY_OUT_26
						, SUM(QTY_IN_27) QTY_IN_27, SUM(QTY_OUT_27) QTY_OUT_27
						, SUM(QTY_IN_28) QTY_IN_28, SUM(QTY_OUT_28) QTY_OUT_28
						, SUM(QTY_IN_29) QTY_IN_29, SUM(QTY_OUT_29) QTY_OUT_29
						, SUM(QTY_IN_30) QTY_IN_30, SUM(QTY_OUT_30) QTY_OUT_30
						, SUM(QTY_IN_31) QTY_IN_31, SUM(QTY_OUT_31) QTY_OUT_31
					FROM mtp_in_out_resume WHERE CUSTOMER = ?
					GROUP BY CUSTOMER
					ORDER BY CUSTOMER
					';
					$queryParam = str_repeat('s', count($queryInput));

					$fetchThroughputIO = db_runQuery(array(
						'config_array' => $configMysql,
						'database_index' => 2,
						'input' => $queryInput,
						'query' => $queryString,
						'param' => $queryParam,
						'getData' => true
					));
					if(!isEmptyVar($fetchThroughputIO) && $fetchThroughputIO !== 'ZERO_DATA') {
						$jsonResponse['success'] = true;
						$jsonResponse['message'] = sprintf('Data found (%s) entries!', 1);
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						$totalDays = date('t');
						foreach($fetchThroughputIO as $key => $val) {
							$idx = preg_replace('/[^0-9]/', '', $key);
							if((intval($idx) - 1) >= $totalDays) { break; }
							$type = (wordExist(strtoupper($key), 'IN')) ? 'inbound' : 'outbound';
							$jsonResponse['data'][intval($idx) - 1][$type] = $val;
						}
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 0;
						unset($jsonResponse['errors']);
					} else {
						$jsonResponse['success'] = false;
						$jsonResponse['message'] = 'Data not found, returning default data!';
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						for ($i = 0; $i <= 30; $i++) {
							$jsonResponse['data'][$i]['inbound'] = 0;
							$jsonResponse['data'][$i]['outbound'] = 0;
						}
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
		case 'chamber-usage':
			/*
			 * Stage: 1
			 * Check if the user is online and has a NIK
			 */
			if(!isEmptyVar($userData->nik)) {
				/*
				 * Stage: 2 A
				 * Check if the account is registered with the current NIK
				 */
				$isExist = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik),
					'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) as TOTAL, NAMA, NAMA_PERUSAHAAN, KODE_STOREKEY FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
					'param' => 's',
					'getData' => true,
					'getAllRow' => false
				));

				/*
				 * Stage: 2 B
				 * Check if the account is had access to some privileges
				 */
				$isPartPrivileges = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik, 0, 'c'),
					'query' => sprintf('SELECT * FROM %s WHERE NIK = ? AND (LEVEL = ? OR LEVEL_CODE = ?);', $APP_CORE['tb_prefix'].'user_privileges'),
					'param' => 'sss',
					'getData' => true,
					'getAllRow' => false
				));
				if(isset($isExist['TOTAL']) && $isExist['TOTAL'] >= 1 && !isEmptyVar($isPartPrivileges) && $isPartPrivileges !== 'ZERO_DATA') {
					// $queryInput = array(
					// 	$isExist['KODE_STOREKEY'],
					// 	date('Y-m-t'),
					// 	date('Y-m-t'),
					// 	date('Y-m-t'),
					// 	date('Y-m-t'),
					// 	date('Y-m-t'),
					// 	date('Y-m-t'),
					// 	date('Y-m-t'),
					// 	date('Y-m-t'),
					// 	date('Y-m-t'),
					// 	date('Y-m-t'),
					// 	date('Y-m-t'),
					// 	date('Y-m-t'),
					// 	date('Y-m-t'),
					// 	date('Y-m-t'),
					// 	date('Y-m-t')
					// );
					$queryInput = array(
						$isExist['KODE_STOREKEY'],
						'MTP001'
					);
					// $queryString = "
					// 	SELECT
					// 		A.OWN, IFNULL(X0.JML_USED,0) AS CH01, IFNULL(X1.JML_USED,0) AS CHAMBERA, IFNULL(X2.JML_USED,0) AS CHAMBERB,
					// 		IFNULL(X3.JML_USED,0) AS CHAMBERC, IFNULL(X4.JML_USED,0) AS CHAMBERD, IFNULL(X5.JML_USED,0) AS CHAMBERE,
					// 		IFNULL(X6.JML_USED,0) AS CHAMBERF, IFNULL(X7.JML_USED,0) AS CHAMBERG, IFNULL(X8.JML_USED,0) AS CHAMBERH,
					// 		IFNULL(X9.JML_USED,0) AS CHAMBERI, IFNULL(X10.JML_USED,0) AS CHAMBERJ, IFNULL(X11.JML_USED,0) AS CHAMBERK,
					// 		IFNULL(X12.JML_USED,0) AS CHILLERA, IFNULL(X13.JML_USED,0) AS CHILLERB, IFNULL(X14.JML_USED,0) AS CHILLERC
					// 	FROM (
					// 		SELECT DISTINCT OWN FROM mtp_inventory_balance_infor_rep WHERE own = ?
					// 	) A
					// 	LEFT JOIN (
					// 		SELECT Z.OWN, Z.KET AS CH01, COUNT(Z.OWN) JML_USED FROM (
					// 			SELECT DISTINCT b.lokasi_storage, a.own, b.ket
					// 			FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
					// 			WHERE a.tgl_cut_off = ? AND a.location = b.lokasi_storage AND b.ket = 'CH01'
					// 		) Z
					// 		GROUP BY Z.OWN, Z.KET
					// 	) X0 ON A.OWN = X0.OWN
					// 	LEFT JOIN (
					// 		SELECT Z.OWN, Z.KET AS CHAMBERA, COUNT(Z.OWN) JML_USED FROM (
					// 			SELECT DISTINCT b.lokasi_storage, a.own, b.ket
					// 			FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
					// 			WHERE a.tgl_cut_off = ? AND a.location = b.lokasi_storage AND b.ket = 'CHAMBERA'
					// 		) Z GROUP BY Z.OWN, Z.KET
					// 	) X1 ON A.OWN = X1.OWN
					// 	LEFT JOIN (
					// 		SELECT Z.OWN, Z.KET AS CHAMBERB, COUNT(Z.OWN) JML_USED
					// 		FROM (
					// 			SELECT DISTINCT b.lokasi_storage, a.own, b.ket
					// 			FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
					// 			WHERE a.tgl_cut_off = ? AND a.location = b.lokasi_storage AND b.ket = 'CHAMBERB'
					// 		) Z GROUP BY Z.OWN, Z.KET
					// 	) X2 ON A.OWN = X2.OWN
					// 	LEFT JOIN (
					// 		SELECT Z.OWN, Z.KET AS CHAMBERC, COUNT(Z.OWN) JML_USED
					// 		FROM (
					// 			SELECT DISTINCT b.lokasi_storage, a.own, b.ket
					// 			FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
					// 			WHERE a.tgl_cut_off = ? AND a.location = b.lokasi_storage AND b.ket = 'CHAMBERC'
					// 		) Z GROUP BY Z.OWN, Z.KET
					// 	) X3 ON A.OWN = X3.OWN
					// 	LEFT JOIN (
					// 		SELECT Z.OWN, Z.KET AS CHAMBERD, COUNT(Z.OWN) JML_USED
					// 		FROM (
					// 			SELECT DISTINCT b.lokasi_storage, a.own, b.ket
					// 			FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
					// 			WHERE a.tgl_cut_off = ? AND a.location = b.lokasi_storage AND b.ket = 'CHAMBERD'
					// 		) Z GROUP BY Z.OWN, Z.KET
					// 	) X4 ON A.OWN = X4.OWN
					// 	LEFT JOIN (
					// 		SELECT Z.OWN, Z.KET AS CHAMBERE, COUNT(Z.OWN) JML_USED
					// 		FROM (
					// 			SELECT DISTINCT b.lokasi_storage, a.own, b.ket
					// 			FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
					// 			WHERE a.tgl_cut_off = ? AND a.location = b.lokasi_storage AND b.ket = 'CHAMBERE'
					// 		) Z GROUP BY Z.OWN, Z.KET
					// 	) X5 ON A.OWN = X5.OWN
					// 	LEFT JOIN (
					// 		SELECT Z.OWN, Z.KET AS CHAMBERF, COUNT(Z.OWN) JML_USED
					// 		FROM (
					// 			SELECT DISTINCT b.lokasi_storage, a.own, b.ket
					// 			FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
					// 			WHERE a.tgl_cut_off = ? AND a.location = b.lokasi_storage AND b.ket = 'CHAMBERF'
					// 		) Z GROUP BY Z.OWN, Z.KET
					// 	) X6 ON A.OWN = X6.OWN
					// 	LEFT JOIN (
					// 		SELECT Z.OWN, Z.KET AS CHAMBERG, COUNT(Z.OWN) JML_USED
					// 		FROM (
					// 			SELECT DISTINCT b.lokasi_storage, a.own, b.ket
					// 			FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
					// 			WHERE a.tgl_cut_off = ? AND a.location = b.lokasi_storage AND b.ket = 'CHAMBERG'
					// 		) Z GROUP BY Z.OWN, Z.KET
					// 	) X7 ON A.OWN = X7.OWN
					// 	LEFT JOIN (
					// 		SELECT Z.OWN, Z.KET AS CHAMBERH, COUNT(Z.OWN) JML_USED
					// 		FROM (
					// 			SELECT DISTINCT b.lokasi_storage, a.own, b.ket
					// 			FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
					// 			WHERE a.tgl_cut_off = ? AND a.location = b.lokasi_storage AND b.ket = 'CHAMBERH'
					// 		) Z GROUP BY Z.OWN, Z.KET
					// 	) X8 ON A.OWN = X8.OWN
					// 	LEFT JOIN (
					// 		SELECT Z.OWN, Z.KET AS CHAMBERI, COUNT(Z.OWN) JML_USED
					// 		FROM (
					// 			SELECT DISTINCT b.lokasi_storage, a.own, b.ket
					// 			FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
					// 			WHERE a.tgl_cut_off = ? AND a.location = b.lokasi_storage AND b.ket = 'CHAMBERI'
					// 		) Z GROUP BY Z.OWN, Z.KET
					// 	) X9 ON A.OWN = X9.OWN
					// 	LEFT JOIN (
					// 		SELECT Z.OWN, Z.KET AS CHAMBERJ, COUNT(Z.OWN) JML_USED
					// 		FROM (
					// 			SELECT DISTINCT b.lokasi_storage, a.own, b.ket
					// 			FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
					// 			WHERE a.tgl_cut_off = ? AND a.location = b.lokasi_storage AND b.ket = 'CHAMBERJ'
					// 		) Z GROUP BY Z.OWN, Z.KET
					// 	) X10 ON A.OWN = X10.OWN
					// 	LEFT JOIN (
					// 		SELECT Z.OWN, Z.KET AS CHAMBERK, COUNT(Z.OWN) JML_USED
					// 		FROM (
					// 			SELECT DISTINCT b.lokasi_storage, a.own, b.ket
					// 			FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
					// 			WHERE a.tgl_cut_off = ? AND a.location = b.lokasi_storage AND b.ket = 'CHAMBERK'
					// 		) Z GROUP BY Z.OWN, Z.KET
					// 	) X11 ON A.OWN = X11.OWN
					// 	LEFT JOIN (
					// 		SELECT Z.OWN, Z.KET AS CHILLERA, COUNT(Z.OWN) JML_USED
					// 		FROM (
					// 			SELECT DISTINCT b.lokasi_storage, a.own, b.ket
					// 			FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
					// 			WHERE a.tgl_cut_off = ? AND a.location = b.lokasi_storage AND b.ket = 'CHILLERA'
					// 		) Z GROUP BY Z.OWN, Z.KET
					// 	) X12 ON A.OWN = X12.OWN
					// 	LEFT JOIN (
					// 		SELECT Z.OWN, Z.KET AS CHILLERB, COUNT(Z.OWN) JML_USED
					// 		FROM (
					// 			SELECT DISTINCT b.lokasi_storage, a.own, b.ket FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
					// 			WHERE a.tgl_cut_off = ? AND a.location = b.lokasi_storage AND b.ket = 'CHILLERB'
					// 		) Z GROUP BY Z.OWN, Z.KET
					// 	) X13 ON A.OWN = X13.OWN
					// 	LEFT JOIN (
					// 		SELECT Z.OWN, Z.KET AS CHILLERC, COUNT(Z.OWN) JML_USED
					// 		FROM (
					// 			SELECT DISTINCT b.lokasi_storage, a.own, b.ket
					// 			FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
					// 			WHERE a.tgl_cut_off = ? AND a.location = b.lokasi_storage AND b.ket = 'CHILLERC'
					// 		) Z GROUP BY Z.OWN, Z.KET
					// 	) X14 ON A.OWN = X14.OWN 
					// ";
					$queryString = "
						SELECT '" . $isExist['KODE_STOREKEY'] . "' CUSTOMER, X.KET CHAMBER, IFNULL(Y.total_storage,0) TOTAL_STORAGE
						FROM
						(
							SELECT DISTINCT ket FROM mtp_master_storage
						) X
						LEFT JOIN
						(
							SELECT z.customer, COUNT(*) total_storage, z.ket CHAMBER
							FROM
							(
								SELECT a.own CUSTOMER, a.location, b.KET
								FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
								WHERE DATE(a.tgl_cut_off) >= DATE(NOW() - INTERVAL 1 DAY) AND a.own = ? AND b.kode_dc = ? AND a.location = b.lokasi_storage
							) Z
							GROUP BY z.customer, z.ket
						) Y
						ON X.KET = Y.CHAMBER
						ORDER BY X.KET
					";
					$queryParam = str_repeat('s', count($queryInput));

					$fetchChamberUsage = db_runQuery(array(
						'config_array' => $configMysql,
						'database_index' => 2,
						'input' => $queryInput,
						'query' => $queryString,
						'param' => $queryParam,
						'getData' => true,
						'getAllRow' => true
					));
					if(!isEmptyVar($fetchChamberUsage) && $fetchChamberUsage !== 'ZERO_DATA' && count($fetchChamberUsage) >= 1) {
						$jsonResponse['success'] = true;
						$jsonResponse['message'] = sprintf('Data found (%s) entries!', count($fetchChamberUsage));
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						$jsonResponse['data']['labels'] = [];
						$jsonResponse['data']['datasets'] = [];
						foreach($fetchChamberUsage as $row) {
							$keyModified = strtoupper($row['CHAMBER']);
							if(wordExist($keyModified, 'CHAMBER')) {
								$keyModified = str_replace('CHAMBER', 'CHAMBER ', $keyModified);
							} elseif(wordExist($keyModified, 'CHILLER')) {
								$keyModified = str_replace('CHILLER', 'CHILLER ', $keyModified);
							} elseif(wordExist($keyModified, 'CH')) {
								$keyModified = str_replace('CH', 'CH ', $keyModified);
							}
							$jsonResponse['data']['labels'][] = $keyModified;
							$jsonResponse['data']['datasets'][strtolower(str_replace(' ', '_', $keyModified))] = (int) $row['TOTAL_STORAGE'];
						}
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 0;
						unset($jsonResponse['errors']);
					} else {
						$jsonResponse['success'] = false;
						$jsonResponse['message'] = 'Data not found, returning default data!';
						$jsonResponse['data'] = array(
							'labels' => array(
								'CH 01',
								'CHAMBER A', 'CHAMBER B', 'CHAMBER C', 'CHAMBER D', 'CHAMBER E', 'CHAMBER F', 'CHAMBER G',
								'CHAMBER H', 'CHAMBER I', 'CHAMBER J', 'CHAMBER K', 'CHILLER A', 'CHILLER B', 'CHILLER C'
							),
							'datasets' => array(
								'ch_01',
								'chamber_a' => 0, 'chamber_b' => 0, 'chamber_c' => 0, 'chamber_d' => 0, 'chamber_e' => 0, 'chamber_f' => 0, 'chamber_g' => 0,
								'chamber_h' => 0, 'chamber_i' => 0, 'chamber_j' => 0, 'chamber_k' => 0, 'chiller_a' => 0, 'chiller_b' => 0, 'chiller_c' => 0,
							)
						);
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
				$jsonResponse['errcode'] = 2;
				unset($jsonResponse['errors']);
			}
		break;
		case 'master-products':
			/*
			 * Stage: 1
			 * Check if the user is online and has a NIK
			 */
			if(!isEmptyVar($userData->nik)) {
				/*
				 * Stage: 2 A
				 * Check if the account is registered with the current NIK
				 */
				$isExist = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik),
					'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) as TOTAL, NAMA, NAMA_PERUSAHAAN, KODE_DC, KODE_CUSTOMER, KODE_STOREKEY FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
					'param' => 's',
					'getData' => true,
					'getAllRow' => false
				));

				/*
				 * Stage: 2 B
				 * Check if the account is had access to some privileges
				 */
				$isPartPrivileges = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik, 0, 'c'),
					'query' => sprintf('SELECT * FROM %s WHERE NIK = ? AND (LEVEL = ? OR LEVEL_CODE = ?);', $APP_CORE['tb_prefix'].'user_privileges'),
					'param' => 'sss',
					'getData' => true,
					'getAllRow' => false
				));
				if(isset($isExist['TOTAL']) && $isExist['TOTAL'] >= 1 && !isEmptyVar($isPartPrivileges) && $isPartPrivileges !== 'ZERO_DATA') {
					if(!isEmptyVar($isExist['KODE_DC']) && wordExist(strtoupper(trim($isExist['KODE_DC'])), 'MTP')) {
						/*
						 * Stage: 3 A
						 * Get config DBMS based on user KODE_DC, from database MTP_CENTRAL.mtp_master_koneksi
						 */
						$configDBMS = db_runQuery(array(
							'config_array' => $configMysql,
							'database_index' => 1, // db.mtp_central
							'input' => array($isExist['KODE_DC'], 1),
							'query' => sprintf('SELECT * FROM %s WHERE KODE_DC = ? AND FLAG = ?;', 'mtp_master_koneksi'),
							'param' => 'ss',
							'getData' => true,
							'getAllRow' => false,
							'callback' => function($result) {
								$result = $result['data'];
								$output = null;
								if(!isEmptyVar($result) && $result !== 'ZERO_DATA') {
									$output = array(
										'host' => $result['DB_HOST'],
										'port' => $result['DB_PORT'],
										'user' => $result['DB_USER'],
										'pass' => (isEmptyVar($result['DB_PASS'])) ? '' : $result['DB_PASS'],
										'name' => $result['DB_NAME']
									);
								}
								return $output;
							}
						));
						if(!isEmptyVar($configDBMS) && is_array($configDBMS)) {
							$customerDBMS = array(
								'mysql_host' => $configDBMS['host'],
								'mysql_username' => $configDBMS['user'],
								'mysql_password' => $configDBMS['pass'],
								'mysql_database' => array($configDBMS['name'])
							);

							/*
							 * Stage: 3 B
							 * Checking the Customer, to make sure it was in the database configuration it fetched earlier (Stage 3 A)
							 */
							$isExistCustomer = db_runQuery(array(
								'config_array' => $customerDBMS,
								'database_index' => 0,
								'input' => array($isExist['KODE_CUSTOMER'], $isExist['KODE_STOREKEY'], 1),
								'query' => sprintf('SELECT KODE_DC, STOREKEY FROM %s WHERE (KODE_DC = ? OR STOREKEY = ?) AND AKTIF = ?;', 'mtp_master_dc'),
								'param' => 'sss',
								'getData' => true,
								'getAllRow' => false
							));
							if(!isEmptyVar($isExistCustomer) && $isExistCustomer !== 'ZERO_DATA') {
								$queryInput = array(
									$isExistCustomer['STOREKEY']
								);
								$queryString = "
								SELECT a.STORERKEY, a.SKU, a.DESCR, a.PACKKEY,  a.RFDEFAULTPACK, a.SUSR5,
									b.PACKUOM3 UOM, CONCAT(b.PALLET, '/', PACKUOM4) QTY,
									b.WEIGHTUOM3, b.HEIGHTUOM3, b.LENGTHUOM3, b.WIDTHUOM3, b.CUBE,
									b.GROSSWGT, b.PACKUOM6
								FROM infor_master_sku a, infor_master_upp b
								WHERE a.PACKKEY = b.PACKKEY AND a.STORERKEY  = ?
								ORDER BY a.STORERKEY, a.DESCR, a.SKU
								";
								$queryParam = str_repeat('s', count($queryInput));

								$fetchMasterProducts = db_runQuery(array(
									'config_array' => $customerDBMS,
									'database_index' => 0,
									'input' => $queryInput,
									'query' => $queryString,
									'param' => $queryParam,
									'getData' => true,
									'getAllRow' => true
								));
								if(!isEmptyVar($fetchMasterProducts) && $fetchMasterProducts !== 'ZERO_DATA' && count($fetchMasterProducts) >= 1) {
									$jsonResponse['success'] = true;
									$jsonResponse['message'] = sprintf('Data found (%s) entries!', count($fetchMasterProducts));
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									foreach($fetchMasterProducts as $idx => $row) {
										$jsonResponse['data'][$idx] = array(
											'sku' => $row['SKU'],
											'description' => $row['DESCR'],
											'uom' => $row['UOM'],
											'temperature' => $row['SUSR5']
										);
									}
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 0;
									unset($jsonResponse['errors']);
								} else {
									$jsonResponse['success'] = false;
									$jsonResponse['message'] = 'Data not found, returning default data!';
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									$jsonResponse['data'] = array();
									$jsonResponse['errcode'] = 1;
									unset($jsonResponse['errors']);
								}
							} else {
								$jsonResponse['success'] = false;
								$jsonResponse['message'] = 'Customer not found in registered DC or not yet active!';
								$jsonResponse['datetime'] = date('Y-m-d H:i:s');
								unset($jsonResponse['data']);
								$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
								$jsonResponse['errcode'] = 6;
								unset($jsonResponse['errors']);
							}
						} else {
							$jsonResponse['success'] = false;
							$jsonResponse['message'] = 'Error, configuration database not found with code provided, (code DC)!';
							$jsonResponse['datetime'] = date('Y-m-d H:i:s');
							unset($jsonResponse['data']);
							$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode'] = 5;
							unset($jsonResponse['errors']);
						}
					} else {
						$jsonResponse['success'] = false;
						$jsonResponse['message'] = 'The customer is not yet officially active, (code DC)!';
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						unset($jsonResponse['data']);
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 4;
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
		case 'temperature-humidity':
			/*
			 * Stage: 1
			 * Check if the user is online and has a NIK
			 */
			if(!isEmptyVar($userData->nik)) {
				/*
				 * Stage: 2 A
				 * Check if the account is registered with the current NIK
				 */
				$isExist = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik),
					'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) as TOTAL, NAMA, NAMA_PERUSAHAAN, KODE_DC, KODE_CUSTOMER, KODE_STOREKEY FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
					'param' => 's',
					'getData' => true,
					'getAllRow' => false
				));

				/*
				 * Stage: 2 B
				 * Check if the account is had access to some privileges
				 */
				$isPartPrivileges = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik, 0, 'c'),
					'query' => sprintf('SELECT * FROM %s WHERE NIK = ? AND (LEVEL = ? OR LEVEL_CODE = ?);', $APP_CORE['tb_prefix'].'user_privileges'),
					'param' => 'sss',
					'getData' => true,
					'getAllRow' => false
				));
				if(isset($isExist['TOTAL']) && $isExist['TOTAL'] >= 1 && !isEmptyVar($isPartPrivileges) && $isPartPrivileges !== 'ZERO_DATA') {
					if(!isEmptyVar($isExist['KODE_DC']) && wordExist(strtoupper(trim($isExist['KODE_DC'])), 'MTP')) {
						/*
						 * Stage: 3 A
						 * Get config DBMS based on user KODE_DC, from database MTP_CENTRAL.mtp_master_koneksi
						 */
						$configDBMS = db_runQuery(array(
							'config_array' => $configMysql,
							'database_index' => 1, // db.mtp_central
							'input' => array($isExist['KODE_DC'], 1),
							'query' => sprintf('SELECT * FROM %s WHERE KODE_DC = ? AND FLAG = ?;', 'mtp_master_koneksi'),
							'param' => 'ss',
							'getData' => true,
							'getAllRow' => false,
							'callback' => function($result) {
								$result = $result['data'];
								$output = null;
								if(!isEmptyVar($result) && $result !== 'ZERO_DATA') {
									$output = array(
										'host' => $result['DB_HOST'],
										'port' => $result['DB_PORT'],
										'user' => $result['DB_USER'],
										'pass' => (isEmptyVar($result['DB_PASS'])) ? '' : $result['DB_PASS'],
										'name' => $result['DB_NAME']
									);
								}
								return $output;
							}
						));
						if(!isEmptyVar($configDBMS) && is_array($configDBMS)) {
							$customerDBMS = array(
								'mysql_host' => $configDBMS['host'],
								'mysql_username' => $configDBMS['user'],
								'mysql_password' => $configDBMS['pass'],
								'mysql_database' => array( $configDBMS['name'] )
							);

							/*
							 * Stage: 3 B
							 * Checking the Customer, to make sure it was in the database configuration it fetched earlier (Stage 3 A)
							 */
							$isExistCustomer = db_runQuery(array(
								'config_array' => $customerDBMS,
								'database_index' => 0,
								'input' => array($isExist['KODE_CUSTOMER'], $isExist['KODE_STOREKEY'], 1),
								'query' => sprintf('SELECT KODE_DC, STOREKEY FROM %s WHERE (KODE_DC = ? OR STOREKEY = ?) AND AKTIF = ?;', 'mtp_master_dc'),
								'param' => 'sss',
								'getData' => true,
								'getAllRow' => false
							));
							if(!isEmptyVar($isExistCustomer) && $isExistCustomer !== 'ZERO_DATA') {
								$jsonResponse['success'] = array();
								$jsonResponse['message'] = array();
								$jsonResponse['datetime'] = array();
								$jsonResponse['data'] = array();
								$jsonResponse['took'] = array();
								$jsonResponse['errcode'] = array();
								$jsonResponse['errors'] = array();

								/*
								* Default values
								*/
								// Result in JSON
								$jsonResponse['success']['coldStorageTempHum'] = array('charts' => array('temperature' => 0, 'humidity' => 0), 'listPeriod' => false, 'listCS' => false, 'listView' => true);
								$jsonResponse['message']['coldStorageTempHum'] = array('charts' => array('temperature' => 'Data not found!', 'humidity' => 'Data not found!'), 'listPeriod' => 'Data not found!', 'listCS' => 'Data not found!', 'listView' => 'Data not found!');
								$jsonResponse['datetime']['coldStorageTempHum'] = date('Y-m-d H:i:s');
								$jsonResponse['data']['coldStorageTempHum'] = array('charts' => array('temperature' => array(), 'humidity' => array()), 'listPeriod' => array(), 'listCS' => array(), 'listView' => array(), 'datePicked' => null);
								$jsonResponse['took']['coldStorageTempHum'] = (floor(microtime(true)*1000))-$startTime;
								$jsonResponse['errcode']['coldStorageTempHum'] = array('charts' => array('temperature' => 1, 'humidity' => 1), 'listPeriod' => 1, 'listCS' => 1, 'listView' => 0);

								// Default charts
								$jsonResponse['data']['coldStorageTempHum']['charts'] = array(
									'temperature' => array(
										0 => array(
											'name' => '-',
											'label' => array(),
											'datasets' => array(),
											'stableRate' => 0,
										),
									),
									'humidity' => array(
										0 => array(
											'name' => '-',
											'label' => array(),
											'datasets' => array(),
											'stableRate' => 0,
										),
									),
								);
								for ($i = 0; $i < 24; $i++) {
									$jsonResponse['data']['coldStorageTempHum']['charts']['temperature'][0]['label'][$i] = sprintf('%s:00', str_pad($i, 2, '0', STR_PAD_LEFT));
									$jsonResponse['data']['coldStorageTempHum']['charts']['temperature'][0]['datasets'][$i] = 0;
									$jsonResponse['data']['coldStorageTempHum']['charts']['humidity'][0]['label'][$i] = sprintf('%s:00', str_pad($i, 2, '0', STR_PAD_LEFT));
									$jsonResponse['data']['coldStorageTempHum']['charts']['humidity'][0]['datasets'][$i] = 0;
								}
								
								// Default listPeriod
								$jsonResponse['data']['coldStorageTempHum']['listPeriod'] = array(
									'maxRange' => 100, // per date/days
									'totalPeriod' => 100, // per date/days
									'availableDate' => array(
										0 => array(
											'name' => 'Today',
											'code' => hash('md5', sprintf('%s%s', date('Y-m-d'), $APP_CORE['app_key_encrypt']))
										) // Insert current today
									)
								);

								// Default listCS
								$jsonResponse['data']['coldStorageTempHum']['listCS'][] = array(
									'name' => 'All',
									'code' => hash('md5', sprintf('%s%s', 'all', $APP_CORE['app_key_encrypt']))
								);

								// Default listView
								$jsonResponse['data']['coldStorageTempHum']['listView'] = array(
									0 => array(
										'name' => 'Compact',
										'code' => hash('md5', sprintf('%s%s', 'compact', $APP_CORE['app_key_encrypt']))
									),
									1 => array(
										'name' => 'RAW',
										'code' => hash('md5', sprintf('%s%s', 'raw', $APP_CORE['app_key_encrypt']))
									),
								);

								// Default datePicked
								$jsonResponse['data']['coldStorageTempHum']['datePicked'] = date('F d, Y');
								// $jsonResponse['data']['coldStorageTempHum']['datePicked'] = date('F d, Y', strtotime('-1 days')); // Yesterday

								/*
								* Processing data
								*/
								if(!isEmptyVar($dataRequest) && isJSON($dataRequest)) {
									$dataRequest = json_decode(jsonFixer($dataRequest, true), true) ?? null;
									if(!isEmptyVar($dataRequest)) {
										// // Set period to default if is NULL
										// $period = (isset($period)) ? $period : null;
										// if(isEmptyVar($period)) {
										// 	$period = date('Y-m-d');
										// }
					
										// Validate data
										$passData = 0;
										$errorData = [];
										$totalValidate = 8;
					
										// Temperature
										if(array_key_exists('temperature', $dataRequest)) {
											$passData += 1;
											if(array_key_exists('periodDate', $dataRequest['temperature']) && !isEmptyVar($dataRequest['temperature']['periodDate']) && is_string($dataRequest['temperature']['periodDate']) && strlen($dataRequest['temperature']['periodDate']) >= 5) {
												$passData += 1;
											} else {
												$errorData[] = 'temperature: periodDate';
											}
											if(array_key_exists('selectCS', $dataRequest['temperature']) && !isEmptyVar($dataRequest['temperature']['selectCS']) && is_string($dataRequest['temperature']['selectCS']) && strlen($dataRequest['temperature']['selectCS']) >= 3) {
												$passData += 1;
											} else {
												$errorData[] = 'temperature: selectCS';
											}
											if(array_key_exists('dataView', $dataRequest['temperature']) && !isEmptyVar($dataRequest['temperature']['dataView']) && is_string($dataRequest['temperature']['dataView']) && strlen($dataRequest['temperature']['dataView']) >= 3) {
												$passData += 1;
											} else {
												$errorData[] = 'temperature: dataView';
											}
										} else {
											$errorData[] = 'temperature';
										}
					
										// Humidity
										if(array_key_exists('humidity', $dataRequest)) {
											$passData += 1;
											if(array_key_exists('periodDate', $dataRequest['humidity']) && !isEmptyVar($dataRequest['humidity']['periodDate']) && is_string($dataRequest['humidity']['periodDate']) && strlen($dataRequest['humidity']['periodDate']) >= 5) {
												$passData += 1;
											} else {
												$errorData[] = 'humidity: periodDate';
											}
											if(array_key_exists('selectCS', $dataRequest['humidity']) && !isEmptyVar($dataRequest['humidity']['selectCS']) && is_string($dataRequest['humidity']['selectCS']) && strlen($dataRequest['humidity']['selectCS']) >= 3) {
												$passData += 1;
											} else {
												$errorData[] = 'humidity: selectCS';
											}
											if(array_key_exists('dataView', $dataRequest['humidity']) && !isEmptyVar($dataRequest['humidity']['dataView']) && is_string($dataRequest['humidity']['dataView']) && strlen($dataRequest['humidity']['dataView']) >= 3) {
												$passData += 1;
											} else {
												$errorData[] = 'humidity: dataView';
											}
										} else {
											$errorData[] = 'humidity';
										}

										if($passData >= $totalValidate) {
											// Fetch list of available Period
											$queryString = sprintf('SELECT DISTINCT(DATE(TGL)) AS DATE_PERIOD FROM %s GROUP BY DATE(TGL) ORDER BY TGL DESC LIMIT 0,%s;', 'mtp_report_temperature', $jsonResponse['data']['coldStorageTempHum']['listPeriod']['maxRange']);
											$fetchAvailablePeriod = db_runQuery(array(
												'config_array' => $customerDBMS,
												'database_index' => 0,
												'query' => $queryString,
												'getData' => true,
												'getAllRow' => true
											));
											if(!isEmptyVar($fetchAvailablePeriod) && $fetchAvailablePeriod !== 'ZERO_DATA' && count($fetchAvailablePeriod) >= 1) {
												$jsonResponse['success']['coldStorageTempHum']['listPeriod'] = 0;
												foreach($fetchAvailablePeriod as $perRow) {
													$jsonResponse['success']['coldStorageTempHum']['listPeriod'] += 1;
													if(!in_array($perRow['DATE_PERIOD'], $jsonResponse['data']['coldStorageTempHum']['listPeriod']['availableDate'])) {
														$jsonResponse['data']['coldStorageTempHum']['listPeriod']['availableDate'][] = array(
															'name' => $perRow['DATE_PERIOD'],
															'code' => hash('md5', sprintf('%s%s', $perRow['DATE_PERIOD'], $APP_CORE['app_key_encrypt']))
														);
													}
												}
												$jsonResponse['data']['coldStorageTempHum']['listPeriod']['totalPeriod'] = count($fetchAvailablePeriod);
											}

											// Fetch list of available Cold Storage (CS)
											$queryString = sprintf('SELECT DISTINCT(MESIN) AS CS_NAME FROM %s GROUP BY MESIN;', 'mtp_report_temperature');
											$fetchAvailableCS = db_runQuery(array(
												'config_array' => $customerDBMS,
												'database_index' => 0,
												'query' => $queryString,
												'getData' => true,
												'getAllRow' => true
											));
											if(!isEmptyVar($fetchAvailableCS) && $fetchAvailableCS !== 'ZERO_DATA' && count($fetchAvailableCS) >= 1) {
												$jsonResponse['success']['coldStorageTempHum']['listCS'] = 0;
												foreach($fetchAvailableCS as $perRow) {
													$jsonResponse['success']['coldStorageTempHum']['listCS'] += 1;
													$jsonResponse['data']['coldStorageTempHum']['listCS'][] = array(
														'name' => $perRow['CS_NAME'],
														'code' => hash('md5', sprintf('%s%s', $perRow['CS_NAME'], $APP_CORE['app_key_encrypt']))
													);
												}
											}

											// Fetch datasets
											$queryInput = array();
											if(!isEmptyVar($dataRequest['temperature']['periodDate'])) {
												$isFound = searchArrAssoc($jsonResponse['data']['coldStorageTempHum']['listPeriod']['availableDate'], 'code', $dataRequest['temperature']['periodDate']) ?? null;
												if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
													if(strtolower(trim($isFound[0]['name'])) === 'today') {
														$queryInput[] = date('Y-m-d'); // Current today
														// $queryInput[] = date('Y-m-d', strtotime('-1 days')); // Get yesterday
													} else {
														$queryInput[] = $isFound[0]['name']; // YYYY-MM-DD
														$jsonResponse['data']['coldStorageTempHum']['datePicked'] = date('F d, Y', strtotime($isFound[0]['name']));
													}
												} else {
													$queryInput[] = date('Y-m-d'); // Current today
													// $queryInput[] = date('Y-m-d', strtotime('-1 days')); // Get yesterday
												}
											} else {
												$queryInput[] = date('Y-m-d'); // Current today
												// $queryInput[] = date('Y-m-d', strtotime('-1 days')); // Get yesterday
											}
											$queryStringAdd = '';
											if(!isEmptyVar($dataRequest['temperature']['selectCS'])) {
												if($dataRequest['temperature']['selectCS'] !== $jsonResponse['data']['coldStorageTempHum']['listCS'][0]['code']) {
													$isFound = searchArrAssoc($jsonResponse['data']['coldStorageTempHum']['listCS'], 'code', $dataRequest['temperature']['selectCS']) ?? null;
													if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
														$queryStringAdd = 'AND MESIN = ?';
														$queryInput[] = $isFound[0]['name'];
													}
													// else {
													// 	$queryInput[] = $jsonResponse['data']['coldStorageTemperature']['listCS'][0]['name'];
													// }
												}
											}
											$queryString = sprintf('SELECT MESIN AS CS_NAME, TGL AS DATE_PERIOD, SUHU AS TEMPERATURE, HUMIDITY FROM %s WHERE DATE(TGL) = ? %s ORDER BY MESIN, TGL;', 'mtp_report_temperature', $queryStringAdd);
											$queryParam = str_repeat('s', count($queryInput));

											$fetchChartData = db_runQuery(array(
												'config_array' => $customerDBMS,
												'database_index' => 0,
												'input' => $queryInput,
												'query' => $queryString,
												'param' => $queryParam,
												'getData' => true,
												'getAllRow' => true
											));
											// pre_dump($fetchChartData);
											// exit(0);

											if(!isEmptyVar($fetchChartData) && $fetchChartData !== 'ZERO_DATA' && count($fetchChartData) >= 1) {
												$presentData = array(
													'temperature' => array(),
													'humidity' => array()
												);

												if(!isEmptyVar($dataRequest['temperature']['dataView'])) {
													$isFound = searchArrAssoc($jsonResponse['data']['coldStorageTempHum']['listView'], 'code', $dataRequest['temperature']['dataView']) ?? null;
													if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
														$isFound = strtolower(trim($isFound[0]['name']));
													} else {
														$isFound = 'compact';
													}

													switch($isFound) {
														default:
														case 'compact':
															foreach($fetchChartData as $perRow) {
																if(!array_key_exists($perRow['CS_NAME'], $presentData['temperature']) && !array_key_exists($perRow['CS_NAME'], $presentData['temperature'])) {
																	$presentData['temperature'][$perRow['CS_NAME']] = array(
																		'labels' => array(),
																		'datasets' => array(),
																	);
																	$presentData['humidity'][$perRow['CS_NAME']] = array(
																		'labels' => array(),
																		'datasets' => array(),
																	);
																	for ($i = 0; $i < 24; $i++) {
																		$presentData['temperature'][$perRow['CS_NAME']]['labels'][$i] = sprintf('%s:00', str_pad($i, 2, '0', STR_PAD_LEFT));
																		$presentData['temperature'][$perRow['CS_NAME']]['datasets'][$i] = array(
																			'total' => 0,
																			'count' => 0,
																		);
																		$presentData['humidity'][$perRow['CS_NAME']]['labels'][$i] = sprintf('%s:00', str_pad($i, 2, '0', STR_PAD_LEFT));
																		$presentData['humidity'][$perRow['CS_NAME']]['datasets'][$i] = array(
																			'total' => 0,
																			'count' => 0,
																		);
																	}
																}
																$hourIn24 = (int) trim(date('H', strtotime($perRow['DATE_PERIOD'])));
																$presentData['temperature'][$perRow['CS_NAME']]['datasets'][$hourIn24]['total'] += (!isEmptyVar($perRow['TEMPERATURE'])) ? (float) $perRow['TEMPERATURE'] : 0;
																$presentData['temperature'][$perRow['CS_NAME']]['datasets'][$hourIn24]['count'] += 1;
																$presentData['humidity'][$perRow['CS_NAME']]['datasets'][$hourIn24]['total'] += (!isEmptyVar($perRow['HUMIDITY'])) ? (float) $perRow['HUMIDITY'] : 0;
																$presentData['humidity'][$perRow['CS_NAME']]['datasets'][$hourIn24]['count'] += 1;
															}
															if(count($presentData['temperature']) >= 1) {
																foreach($presentData['temperature'] as $csName => $perData) {
																	if(is_array($perData) && array_key_exists('datasets', $perData)) {
																		foreach($perData['datasets'] as $idx => $val) {
																			if($val['total'] >= 0 && $val['count'] >= 1) {
																				$presentData['temperature'][$csName]['datasets'][$idx] = $val['total'] / $val['count'] ;
																			} else {
																				$presentData['temperature'][$csName]['datasets'][$idx] = 0;
																			}
																		}
																	}
																}
															}
															if(count($presentData['humidity']) >= 1) {
																foreach($presentData['humidity'] as $csName => $perData) {
																	if(is_array($perData) && array_key_exists('datasets', $perData)) {
																		foreach($perData['datasets'] as $idx => $val) {
																			if($val['total'] >= 0 && $val['count'] >= 1) {
																				$presentData['humidity'][$csName]['datasets'][$idx] = $val['total'] / $val['count'] ;
																			} else {
																				$presentData['humidity'][$csName]['datasets'][$idx] = 0;
																			}
																		}
																	}
																}
															}
															break;
														case 'raw':
															foreach($fetchChartData as $perRow) {
																if(!array_key_exists($perRow['CS_NAME'], $presentData['temperature']) && !array_key_exists($perRow['CS_NAME'], $presentData['humidity'])) {
																	$presentData['temperature'][$perRow['CS_NAME']] = array(
																		'labels' => array(),
																		'datasets' => array(),
																	);
																	$presentData['humidity'][$perRow['CS_NAME']] = array(
																		'labels' => array(),
																		'datasets' => array(),
																	);
																}
																$labelHourMinute = date('H:i', strtotime($perRow['DATE_PERIOD']));
																if(!in_array($labelHourMinute, $presentData['temperature'][$perRow['CS_NAME']]['labels'])) {
																	$presentData['temperature'][$perRow['CS_NAME']]['labels'][] = $labelHourMinute;
																	$presentData['temperature'][$perRow['CS_NAME']]['datasets'][] = $perRow['TEMPERATURE'];
																}
																if(!in_array($labelHourMinute, $presentData['humidity'][$perRow['CS_NAME']]['labels'])) {
																	$presentData['humidity'][$perRow['CS_NAME']]['labels'][] = $labelHourMinute;
																	$presentData['humidity'][$perRow['CS_NAME']]['datasets'][] = $perRow['HUMIDITY'];
																}
															}
															break;
													}
					
													if(count($presentData['temperature']) >= 1) {
														$jsonResponse['data']['coldStorageTempHum']['charts']['temperature'] = array();
														foreach($presentData['temperature'] as $csName => $perData) {
															$stableRate = array_filter($perData['datasets'], function($val) { return $val > 0; });
															$jsonResponse['success']['coldStorageTempHum']['charts']['temperature'] += 1;
															$jsonResponse['data']['coldStorageTempHum']['charts']['temperature'][] = array(
																'name' => $csName,
																'label' => $perData['labels'],
																'datasets' => $perData['datasets'],
																'stableRate' => (float) sprintf('%.02f', array_sum($stableRate) / count($stableRate)),
															);
														}
													}
													if(count($presentData['humidity']) >= 1) {
														$jsonResponse['data']['coldStorageTempHum']['charts']['humidity'] = array();
														foreach($presentData['humidity'] as $csName => $perData) {
															$stableRate = array_filter($perData['datasets'], function($val) { return $val > 0; });
															$jsonResponse['success']['coldStorageTempHum']['charts']['humidity'] += 1;
															$jsonResponse['data']['coldStorageTempHum']['charts']['humidity'][] = array(
																'name' => $csName,
																'label' => $perData['labels'],
																'datasets' => $perData['datasets'],
																'stableRate' => (float) sprintf('%.02f', array_sum($stableRate) / count($stableRate)),
															);
														}
													}
												}
											}
										}
									}
								}

								/*
								* Finishing response
								*/
								// Result fix JSON
								if(intval($jsonResponse['success']['coldStorageTempHum']['listPeriod']) >= 1 && count($jsonResponse['data']['coldStorageTempHum']['listPeriod']['availableDate']) >= 1) {
									$jsonResponse['success']['coldStorageTempHum']['listPeriod'] = true;
									$jsonResponse['message']['coldStorageTempHum']['listPeriod'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['coldStorageTempHum']['listPeriod']['availableDate']));
									$jsonResponse['errcode']['coldStorageTempHum']['listPeriod'] = 0;
								}
								if(intval($jsonResponse['success']['coldStorageTempHum']['listCS']) >= 1 && count($jsonResponse['data']['coldStorageTempHum']['listCS']) >= 1) {
									$jsonResponse['success']['coldStorageTempHum']['listCS'] = true;
									$jsonResponse['message']['coldStorageTempHum']['listCS'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['coldStorageTempHum']['listCS']));
									$jsonResponse['errcode']['coldStorageTempHum']['listCS'] = 0;
								}
								if(intval($jsonResponse['success']['coldStorageTempHum']['charts']['temperature']) >= 1 && count($jsonResponse['data']['coldStorageTempHum']['charts']['temperature']) >= 1) {
									$jsonResponse['success']['coldStorageTempHum']['charts']['temperature'] = true;
									$jsonResponse['message']['coldStorageTempHum']['charts']['temperature'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['coldStorageTempHum']['charts']['temperature']));
									$jsonResponse['errcode']['coldStorageTempHum']['charts']['temperature'] = 0;
								} else {
									$jsonResponse['success']['coldStorageTempHum']['charts']['temperature'] = false;
									$jsonResponse['message']['coldStorageTempHum']['charts']['temperature'] = 'Data not found';
									$jsonResponse['errcode']['coldStorageTempHum']['charts']['temperature'] = 1;
								}
								if(intval($jsonResponse['success']['coldStorageTempHum']['charts']['humidity']) >= 1 && count($jsonResponse['data']['coldStorageTempHum']['charts']['humidity']) >= 1) {
									$jsonResponse['success']['coldStorageTempHum']['charts']['humidity'] = true;
									$jsonResponse['message']['coldStorageTempHum']['charts']['humidity'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['coldStorageTempHum']['charts']['humidity']));
									$jsonResponse['errcode']['coldStorageTempHum']['charts']['humidity'] = 0;
								} else {
									$jsonResponse['success']['coldStorageTempHum']['charts']['humidity'] = false;
									$jsonResponse['message']['coldStorageTempHum']['charts']['humidity'] = 'Data not found';
									$jsonResponse['errcode']['coldStorageTempHum']['charts']['humidity'] = 1;
								}
								$jsonResponse['datetime']['coldStorageTempHum'] = date('Y-m-d H:i:s');
								$jsonResponse['took']['coldStorageTempHum'] = (floor(microtime(true)*1000))-$startTime;
							} else {
								$jsonResponse['success'] = false;
								$jsonResponse['message'] = 'Customer not found in registered DC or not yet active!';
								$jsonResponse['datetime'] = date('Y-m-d H:i:s');
								unset($jsonResponse['data']);
								$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
								$jsonResponse['errcode'] = 6;
								unset($jsonResponse['errors']);
							}
						} else {
							$jsonResponse['success'] = false;
							$jsonResponse['message'] = 'Error, configuration database not found with code provided, (code DC)!';
							$jsonResponse['datetime'] = date('Y-m-d H:i:s');
							unset($jsonResponse['data']);
							$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode'] = 5;
							unset($jsonResponse['errors']);
						}
					} else {
						$jsonResponse['success'] = false;
						$jsonResponse['message'] = 'The customer is not yet officially active, (code DC)!';
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						unset($jsonResponse['data']);
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 4;
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

		case 'ga-io-bound':
			/*
			 * Stage: 1
			 * Check if the user is online and has a NIK
			 */
			if(!isEmptyVar($userData->nik)) {
				/*
				 * Stage: 2 A
				 * Check if the account is registered with the current NIK
				 */
				$isExist = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik),
					'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) as TOTAL, NAMA, NAMA_PERUSAHAAN, KODE_DC, KODE_CUSTOMER, KODE_STOREKEY FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
					'param' => 's',
					'getData' => true,
					'getAllRow' => false
				));

				/*
				 * Stage: 2 B
				 * Check if the account is had access to some privileges
				 */
				$isPartPrivileges = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik, 0, 'c'),
					'query' => sprintf('SELECT * FROM %s WHERE NIK = ? AND (LEVEL = ? OR LEVEL_CODE = ?);', $APP_CORE['tb_prefix'].'user_privileges'),
					'param' => 'sss',
					'getData' => true,
					'getAllRow' => false
				));
				if(isset($isExist['TOTAL']) && $isExist['TOTAL'] >= 1 && !isEmptyVar($isPartPrivileges) && $isPartPrivileges !== 'ZERO_DATA') {
					if(!isEmptyVar($isExist['KODE_DC']) && wordExist(strtoupper(trim($isExist['KODE_DC'])), 'MTP')) {
						/*
						 * Stage: 3 A
						 * Get config DBMS based on user KODE_DC, from database MTP_CENTRAL.mtp_master_koneksi
						 */
						$configDBMS = db_runQuery(array(
							'config_array' => $configMysql,
							'database_index' => 1, // db.mtp_central
							'input' => array($isExist['KODE_DC'], 1),
							'query' => sprintf('SELECT * FROM %s WHERE KODE_DC = ? AND FLAG = ?;', 'mtp_master_koneksi'),
							'param' => 'ss',
							'getData' => true,
							'getAllRow' => false,
							'callback' => function($result) {
								$result = $result['data'];
								$output = null;
								if(!isEmptyVar($result) && $result !== 'ZERO_DATA') {
									$output = array(
										'host' => $result['DB_HOST'],
										'port' => $result['DB_PORT'],
										'user' => $result['DB_USER'],
										'pass' => (isEmptyVar($result['DB_PASS'])) ? '' : $result['DB_PASS'],
										'name' => $result['DB_NAME']
									);
								}
								return $output;
							}
						));
						if(!isEmptyVar($configDBMS) && is_array($configDBMS)) {
							$customerDBMS = array(
								'mysql_host' => $configDBMS['host'],
								'mysql_username' => $configDBMS['user'],
								'mysql_password' => $configDBMS['pass'],
								'mysql_database' => array( $configDBMS['name'] )
							);

							/*
							 * Stage: 3 B
							 * Checking the Customer, to make sure it was in the database configuration it fetched earlier (Stage 3 A)
							 */
							$isExistCustomer = db_runQuery(array(
								'config_array' => $customerDBMS,
								'database_index' => 0,
								'input' => array($isExist['KODE_CUSTOMER'], $isExist['KODE_STOREKEY'], 1),
								'query' => sprintf('SELECT KODE_DC, STOREKEY FROM %s WHERE (KODE_DC = ? OR STOREKEY = ?) AND AKTIF = ?;', 'mtp_master_dc'),
								'param' => 'sss',
								'getData' => true,
								'getAllRow' => false
							));
							if(!isEmptyVar($isExistCustomer) && $isExistCustomer !== 'ZERO_DATA') {
								$customer = implode(',', array( $isExistCustomer['KODE_DC'] ));
								$queryInput = explode(',', $customer);
								$queryString = "SELECT URUTAN, PROSES, SUM(IFNULL(GATE_IN, 0)) GATE_IN, SUM(IFNULL(TUNGGU, 0)) TUNGGU, 
										SUM(IFNULL(PROSES_ADMIN, 0)) PROSES_ADMIN, SUM(IFNULL(LOADING_UNLOADING, 0)) LOADING_UNLOADING,
										SUM(IFNULL(ADMIN_OUT, 0)) ADMIN_OUT, SUM(IFNULL(GATE_OUT, 0)) GATE_OUT,
										IFNULL(MAX(TGL_UPDATE), NOW()) TGL_UPDATE,
										SUM(IFNULL(TUNGGU_0, 0)) TUNGGU_0,
										SUM(IFNULL(TUNGGU_1, 0)) TUNGGU_1,
										SUM(IFNULL(TUNGGU_2, 0)) TUNGGU_2,
										SUM(IFNULL(TUNGGU_3, 0)) TUNGGU_3,
										SUM(IFNULL(TUNGGU_4, 0)) TUNGGU_4
									FROM DASHBOARD_DISPLAY_CEK_POINT_cust
									WHERE kode_cust IN (". implode(',', array_fill(0, count(explode(',', $customer)), '?')) .")
									GROUP BY URUTAN, PROSES";
								$queryParam = str_repeat('s', count($queryInput));
			
								// $test = implode(',', array_fill(0, count($array_values), '?'));
			
								$fetchData = db_runQuery(array(
									'config_array' => $customerDBMS,
									'database_index' => 0,
									'input' => $queryInput,
									'query' => $queryString,
									'param' => $queryParam,
									'getData' => true,
									'getAllRow' => true
								));
								if(!isEmptyVar($fetchData) && $fetchData !== 'ZERO_DATA' && count($fetchData) >= 1) {
									$jsonResponse['success'] = true;
									$jsonResponse['message'] = sprintf('Data found (%s) entries!', count($fetchData));
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									// Serve data
									$jsonResponse['data'] = array();
									foreach($fetchData as $idx => $row) {
										$jsonResponse['data']['InOutBound'][$idx] = array(
											'urutan' => $row["URUTAN"],
											'proses' => $row["PROSES"],
											'gate_in' => $row["GATE_IN"],
											'tunggu_1' => $row["TUNGGU"],
											'admin_in' => $row["PROSES_ADMIN"],
											'load_unload' => $row["LOADING_UNLOADING"],
											'tunggu_2' => (int) $row["TUNGGU_2"],
											'admin_out' => $row["ADMIN_OUT"],
											'tunggu_3' => (int) $row["TUNGGU_3"],
											'gate_out' => $row["GATE_OUT"],
											'tunggu_4' => (int) $row["TUNGGU_4"],
											'tanggal_update' => $row["TGL_UPDATE"],
										);
									}
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 0;
									unset($jsonResponse['errors']);
								} else {
									$jsonResponse['success'] = false;
									$jsonResponse['message'] = 'Sorry, data not found/not exist!';
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									unset($jsonResponse['data']);
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 1;
									unset($jsonResponse['errors']);
								}
							} else {
								$jsonResponse['success'] = false;
								$jsonResponse['message'] = 'Customer not found in registered DC or not yet active!';
								$jsonResponse['datetime'] = date('Y-m-d H:i:s');
								unset($jsonResponse['data']);
								$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
								$jsonResponse['errcode'] = 6;
								unset($jsonResponse['errors']);
							}
						} else {
							$jsonResponse['success'] = false;
							$jsonResponse['message'] = 'Error, configuration database not found with code provided, (code DC)!';
							$jsonResponse['datetime'] = date('Y-m-d H:i:s');
							unset($jsonResponse['data']);
							$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode'] = 5;
							unset($jsonResponse['errors']);
						}
					} else {
						$jsonResponse['success'] = false;
						$jsonResponse['message'] = 'The customer is not yet officially active, (code DC)!';
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						unset($jsonResponse['data']);
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 4;
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
		case 'ga-list-used':
			/*
			 * Stage: 1
			 * Check if the user is online and has a NIK
			 */
			if(!isEmptyVar($userData->nik)) {
				/*
				 * Stage: 2 A
				 * Check if the account is registered with the current NIK
				 */
				$isExist = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik),
					'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) as TOTAL, NAMA, NAMA_PERUSAHAAN, KODE_DC, KODE_CUSTOMER, KODE_STOREKEY FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
					'param' => 's',
					'getData' => true,
					'getAllRow' => false
				));

				/*
				 * Stage: 2 B
				 * Check if the account is had access to some privileges
				 */
				$isPartPrivileges = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik, 0, 'c'),
					'query' => sprintf('SELECT * FROM %s WHERE NIK = ? AND (LEVEL = ? OR LEVEL_CODE = ?);', $APP_CORE['tb_prefix'].'user_privileges'),
					'param' => 'sss',
					'getData' => true,
					'getAllRow' => false
				));
				if(isset($isExist['TOTAL']) && $isExist['TOTAL'] >= 1 && !isEmptyVar($isPartPrivileges) && $isPartPrivileges !== 'ZERO_DATA') {
					if(!isEmptyVar($isExist['KODE_DC']) && wordExist(strtoupper(trim($isExist['KODE_DC'])), 'MTP')) {
						/*
						 * Stage: 3 A
						 * Get config DBMS based on user KODE_DC, from database MTP_CENTRAL.mtp_master_koneksi
						 */
						$configDBMS = db_runQuery(array(
							'config_array' => $configMysql,
							'database_index' => 1, // db.mtp_central
							'input' => array($isExist['KODE_DC'], 1),
							'query' => sprintf('SELECT * FROM %s WHERE KODE_DC = ? AND FLAG = ?;', 'mtp_master_koneksi'),
							'param' => 'ss',
							'getData' => true,
							'getAllRow' => false,
							'callback' => function($result) {
								$result = $result['data'];
								$output = null;
								if(!isEmptyVar($result) && $result !== 'ZERO_DATA') {
									$output = array(
										'host' => $result['DB_HOST'],
										'port' => $result['DB_PORT'],
										'user' => $result['DB_USER'],
										'pass' => (isEmptyVar($result['DB_PASS'])) ? '' : $result['DB_PASS'],
										'name' => $result['DB_NAME']
									);
								}
								return $output;
							}
						));
						if(!isEmptyVar($configDBMS) && is_array($configDBMS)) {
							$customerDBMS = array(
								'mysql_host' => $configDBMS['host'],
								'mysql_username' => $configDBMS['user'],
								'mysql_password' => $configDBMS['pass'],
								'mysql_database' => array( $configDBMS['name'] )
							);

							/*
							 * Stage: 3 B
							 * Checking the Customer, to make sure it was in the database configuration it fetched earlier (Stage 3 A)
							 */
							$isExistCustomer = db_runQuery(array(
								'config_array' => $customerDBMS,
								'database_index' => 0,
								'input' => array($isExist['KODE_CUSTOMER'], $isExist['KODE_STOREKEY'], 1),
								'query' => sprintf('SELECT KODE_DC, STOREKEY FROM %s WHERE (KODE_DC = ? OR STOREKEY = ?) AND AKTIF = ?;', 'mtp_master_dc'),
								'param' => 'sss',
								'getData' => true,
								'getAllRow' => false
							));
							if(!isEmptyVar($isExistCustomer) && $isExistCustomer !== 'ZERO_DATA') {
								$customer = implode(',', array( $isExistCustomer['KODE_DC'] ));
								$queryInput = explode(',', $customer);
								$queryString = "SELECT ZZ.*, IFNULL(YY.EST_INBOUND,30) EST_INBOOUND, IFNULL(IF(ZZ.JENIS_PRESENSI='INBOUND', YY.EST_INBOUND, YY.EST_OUTBOUND),0) CMP_WAKTU
									FROM
									(
									SELECT 
										A.*,
										IFNULL(B.NO_KENDARAAN,'-') NO_KENDARAAN, 
										IFNULL(B.JENIS_KENDARAAN,'-') JENIS_KENDARAAN, 
										IFNULL(B.KODE_CUSTOMER,'-') KODE_CUSTOMER, 
										IFNULL(B.jenis_presensi, '-') jenis_presensi,
										IFNULL(B.NO_SURAT_JALAN,'-') NO_SURAT_JALAN,
										IFNULL(B.LOADING,'-') LOADING, 
										TIME_FORMAT( TIMEDIFF(TIME(NOW()), TIME(IFNULL(B.LOADING,TIME(NOW())))),'%H') * 60 SELISIH_JAM,
										TIME_FORMAT( TIMEDIFF(TIME(NOW()), TIME(IFNULL(B.LOADING,TIME(NOW())))),'%i') SELISIH_MENIT,
										(TIME_FORMAT( TIMEDIFF(TIME(NOW()), TIME(IFNULL(B.LOADING,TIME(NOW())))),'%H') * 60) + (
										TIME_FORMAT( TIMEDIFF(TIME(NOW()), TIME(IFNULL(B.LOADING,TIME(NOW())))),'%i')) TOT_MENIT
									FROM
										(
											SELECT URUTAN, NAMA_GATE FROM mtp_master_gate
										) A
									LEFT JOIN
									(
										SELECT
											NO_KENDARAAN, JENIS_KENDARAAN, KODE_CUSTOMER, jenis_presensi, GATE, 
											IFNULL(jam_awal_loading,IFNULL(`JAM_BONGKAR_AWAL`,'-')) LOADING, 
											NO_SURAT_JALAN
										FROM mtp_trs_kendaraan_customer
										WHERE tgl_keluar IS NULL AND AWAL_ADMIN IS NOT NULL AND AKHIR_ADMIN IS NULL AND KODE_CUSTOMER IN (SELECT NAMA FROM MTP_MASTER_DC WHERE KODE_DC IN (". implode(',', array_fill(0, count(explode(',', $customer)), '?')) ."))
									) B
									ON A.NAMA_GATE = B.GATE
									) ZZ
									LEFT JOIN
									(
										SELECT a.EST_INBOUND, a.EST_OUTBOUND, a.JENIS_KENDARAAN
										FROM mtp_master_jenis_kendaraan a
									) YY
									ON ZZ.JENIS_KENDARAAN = YY.JENIS_KENDARAAN
									ORDER BY ZZ.URUTAN";

								$queryParam = str_repeat('s', count($queryInput));
								$fetchData = db_runQuery(array(
									'config_array' => $customerDBMS,
									'database_index' => 0,
									'input' => $queryInput,
									'query' => $queryString,
									'param' => $queryParam,
									'getData' => true,
									'getAllRow' => true
								));
								if(!isEmptyVar($fetchData) && $fetchData !== 'ZERO_DATA' && count($fetchData) > 0) {
									$jsonResponse['success'] = true;
									$jsonResponse['message'] = sprintf('Data found (%s) entries!', count($fetchData));
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									// Serve data
									$jsonResponse['data'] = array();
									foreach($fetchData as $idx => $row) {
										if(trim($row['jenis_presensi']) == 'PRESENSI SUPPLIER') {
											$row['jenis_presensi'] = 'INBOUND';
										}
										if(trim($row['jenis_presensi']) == 'PRESENSI PENGIRIMAN') {
											$row['jenis_presensi'] = 'OUTBOUND';
										}
										$jsonResponse['data']['GateActivity'][$idx] = array(
											'urutan' => $row['URUTAN'],
											'nama_gate' => $row['NAMA_GATE'],
											'no_kendaraan' => $row['NO_KENDARAAN'],
											'jenis_kendaraan' => $row['JENIS_KENDARAAN'].' ('.$row['CMP_WAKTU'].')',
											'kode_customer' => $row['KODE_CUSTOMER'],
											'jenis_presensi' => $row['jenis_presensi'],
											'no_surat_jalan' => $row['NO_SURAT_JALAN'],
											'loading' => $row['LOADING'],
											'tot_menit' => $row['TOT_MENIT'],
											'cmp_waktu' => $row['CMP_WAKTU'],
											'alert' => 0
										);
										if((int) $row['TOT_MENIT'] > (int) $row['CMP_WAKTU']) {
											$jsonResponse['data']['GateActivity'][$idx]['alert'] = 2;
										} else if((int) $row['TOT_MENIT'] < (int) $row['CMP_WAKTU']) {
											$jsonResponse['data']['GateActivity'][$idx]['alert'] = 1;
										} else {
											$jsonResponse['data']['GateActivity'][$idx]['alert'] = 0;
										}
									}
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 0;
									unset($jsonResponse['errors']);
								} else {
									$jsonResponse['success'] = false;
									$jsonResponse['message'] = 'Sorry, data not found/not exist!';
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									unset($jsonResponse['data']);
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 404;
									unset($jsonResponse['errors']);
								}
							} else {
								$jsonResponse['success'] = false;
								$jsonResponse['message'] = 'Customer not found in registered DC or not yet active!';
								$jsonResponse['datetime'] = date('Y-m-d H:i:s');
								unset($jsonResponse['data']);
								$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
								$jsonResponse['errcode'] = 6;
								unset($jsonResponse['errors']);
							}
						} else {
							$jsonResponse['success'] = false;
							$jsonResponse['message'] = 'Error, configuration database not found with code provided, (code DC)!';
							$jsonResponse['datetime'] = date('Y-m-d H:i:s');
							unset($jsonResponse['data']);
							$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode'] = 5;
							unset($jsonResponse['errors']);
						}
					} else {
						$jsonResponse['success'] = false;
						$jsonResponse['message'] = 'The customer is not yet officially active, (code DC)!';
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						unset($jsonResponse['data']);
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 4;
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
		case 'ga-live-history':
			/*
			 * Stage: 1
			 * Check if the user is online and has a NIK
			 */
			if(!isEmptyVar($userData->nik)) {
				/*
				 * Stage: 2 A
				 * Check if the account is registered with the current NIK
				 */
				$isExist = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik),
					'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) as TOTAL, NAMA, NAMA_PERUSAHAAN, KODE_DC, KODE_CUSTOMER, KODE_STOREKEY FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
					'param' => 's',
					'getData' => true,
					'getAllRow' => false
				));

				/*
				 * Stage: 2 B
				 * Check if the account is had access to some privileges
				 */
				$isPartPrivileges = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik, 0, 'c'),
					'query' => sprintf('SELECT * FROM %s WHERE NIK = ? AND (LEVEL = ? OR LEVEL_CODE = ?);', $APP_CORE['tb_prefix'].'user_privileges'),
					'param' => 'sss',
					'getData' => true,
					'getAllRow' => false
				));
				if(isset($isExist['TOTAL']) && $isExist['TOTAL'] >= 1 && !isEmptyVar($isPartPrivileges) && $isPartPrivileges !== 'ZERO_DATA') {
					if(!isEmptyVar($isExist['KODE_DC']) && wordExist(strtoupper(trim($isExist['KODE_DC'])), 'MTP')) {
						/*
						 * Stage: 3 A
						 * Get config DBMS based on user KODE_DC, from database MTP_CENTRAL.mtp_master_koneksi
						 */
						$configDBMS = db_runQuery(array(
							'config_array' => $configMysql,
							'database_index' => 1, // db.mtp_central
							'input' => array($isExist['KODE_DC'], 1),
							'query' => sprintf('SELECT * FROM %s WHERE KODE_DC = ? AND FLAG = ?;', 'mtp_master_koneksi'),
							'param' => 'ss',
							'getData' => true,
							'getAllRow' => false,
							'callback' => function($result) {
								$result = $result['data'];
								$output = null;
								if(!isEmptyVar($result) && $result !== 'ZERO_DATA') {
									$output = array(
										'host' => $result['DB_HOST'],
										'port' => $result['DB_PORT'],
										'user' => $result['DB_USER'],
										'pass' => (isEmptyVar($result['DB_PASS'])) ? '' : $result['DB_PASS'],
										'name' => $result['DB_NAME']
									);
								}
								return $output;
							}
						));
						if(!isEmptyVar($configDBMS) && is_array($configDBMS)) {
							$customerDBMS = array(
								'mysql_host' => $configDBMS['host'],
								'mysql_username' => $configDBMS['user'],
								'mysql_password' => $configDBMS['pass'],
								'mysql_database' => array( $configDBMS['name'] )
							);

							/*
							 * Stage: 3 B
							 * Checking the Customer, to make sure it was in the database configuration it fetched earlier (Stage 3 A)
							 */
							$isExistCustomer = db_runQuery(array(
								'config_array' => $customerDBMS,
								'database_index' => 0,
								'input' => array($isExist['KODE_CUSTOMER'], $isExist['KODE_STOREKEY'], 1),
								'query' => sprintf('SELECT KODE_DC, STOREKEY FROM %s WHERE (KODE_DC = ? OR STOREKEY = ?) AND AKTIF = ?;', 'mtp_master_dc'),
								'param' => 'sss',
								'getData' => true,
								'getAllRow' => false
							));
							if(!isEmptyVar($isExistCustomer) && $isExistCustomer !== 'ZERO_DATA') {
								$customer = implode(',', array( $isExistCustomer['KODE_DC'] ));

								// Serve init
								$serveData = array(
									'masterPresence' => true,
									'masterCustomer' => false, // Disabled because for 1 Customer only (HO feature)
									'LiveHistory' => true
								);

								// Default values
								$jsonResponse['success'] = array(
									'masterPresence' => false,
									'masterCustomer' => false,
									'LiveHistory' => false
								);
								$jsonResponse['message'] = array(
									'masterPresence' => sprintf('Data found (%s) entries!', count($jsonResponse['data']['masterPresence'])),
									'masterCustomer' => 'Data not found!',
									'LiveHistory' => 'Data not found!'
								);
								$jsonResponse['datetime'] = array(
									'masterPresence' => date('Y-m-d H:i:s'),
									'masterCustomer' => date('Y-m-d H:i:s'),
									'LiveHistory' => date('Y-m-d H:i:s')
								);
								$jsonResponse['data'] = array(
									'masterPresence' => array(),
									'masterCustomer' => array(),
									'LiveHistory' => array()
								);
								$jsonResponse['took'] = array(
									'masterPresence' => '0ms',
									'masterCustomer' => '0ms',
									'LiveHistory' => '0ms'
								);
								$jsonResponse['errcode'] = array(
									'masterPresence' => 0,
									'masterCustomer' => 1,
									'LiveHistory' => 1
								);
								unset($jsonResponse['errors']);

								// Serve for: masterPresence
								if(boolval($serveData['masterPresence']) === true) {
									$jsonResponse['success']['masterPresence'] = true;
									$jsonResponse['datetime']['masterPresence'] = date('Y-m-d H:i:s');
									$jsonResponse['data']['masterPresence'] = array(
										0 => array('name' => 'ALL', 'code' => 'ALL'),
										1 => array('name' => 'INBOUND', 'code' => 'INBOUND'),
										2 => array('name' => 'OUTBOUND', 'code' => 'OUTBOUND')
									);
									$jsonResponse['took']['masterPresence'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['message']['masterPresence'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['masterPresence']));
									$jsonResponse['errcode']['masterPresence'] = 0;
								} else {
									foreach(array('success','data','errcode','message') as $perResponse) {
										unset($jsonResponse[$perResponse]['masterPresence']);
									}
								}
								// Serve for: masterCustomer
								if(boolval($serveData['masterCustomer']) === true) {
									// Query string: Fetch all available Customer
									$queryString = '';
									if($customer !== 'ALL') {
										$queryInput = explode(',', $customer);
										$queryString = "SELECT Z.*
											FROM (SELECT KODE_DC, NAMA, 2 URUTAN FROM mtp_master_dc UNION SELECT KODE_DC, NAMA, 0 URUTAN FROM mtp_master_dc
											WHERE kode_dc = ?
											UNION
											SELECT DISTINCT 'ALL' KODE_DC, 'ALL' NAMA, 1 URUTAN FROM mtp_master_dc) Z
											ORDER BY Z.URUTAN, Z.NAMA ";
										$queryParam = str_repeat('s', count($queryInput));
									} else {
										$queryInput = null;
										$queryString = "SELECT Z.* FROM (SELECT KODE_DC, NAMA, 1 URUTAN FROM mtp_master_dc UNION SELECT DISTINCT 'ALL' KODE_DC, 'ALL' NAMA, 0 URUTAN FROM mtp_master_dc) Z ORDER BY Z.URUTAN, Z.NAMA ";
										$queryParam = null;
									}

									// Query data: Fetch all available Customer
									$fetchDataCustomer = db_runQuery(array(
										'config_array' => $customerDBMS,
										'database_index' => 0,
										'input' => $queryInput,
										'query' => $queryString,
										'param' => $queryParam,
										'getData' => true,
										'getAllRow' => true
									));
									if(!isEmptyVar($fetchDataCustomer) && $fetchDataCustomer !== 'ZERO_DATA' && count($fetchDataCustomer) >= 1) {
										$jsonResponse['success']['masterCustomer'] = true;
										$jsonResponse['message']['masterCustomer'] = sprintf('Data found (%s) entries!', count($fetchDataCustomer));
										$jsonResponse['datetime']['masterCustomer'] = date('Y-m-d H:i:s');

										// Serve data
										foreach($fetchDataCustomer as $idx => $row) {
											$jsonResponse['data']['masterCustomer'][] = array(
												'name' => $row['NAMA'],
												'code' => $row['KODE_DC'],
											);
										}

										$jsonResponse['took']['masterCustomer'] = (floor(microtime(true)*1000))-$startTime.'ms';
										$jsonResponse['errcode']['masterCustomer'] = 0;
									}
								} else {
									foreach(array('success','data','errcode','message') as $perResponse) {
										unset($jsonResponse[$perResponse]['masterCustomer']);
									}
								}
								// Serve for: LiveHistory
								if(boolval($serveData['LiveHistory']) === true) {
									// Query string: Fetch live history of Customer transaction
									$queryInput = null;
									$queryString = "SELECT B.*
										FROM
										(
											SELECT KODE_CUSTOMER, NO_KENDARAAN, NO_SURAT_JALAN,
												IFNULL(jenis_presensi,'-') jenis_presensi,
												tgl_masuk, IFNULL(awal_admin,'-') awal_admin,
												IF(jam_awal_loading IS NULL,
													IF(jam_bongkar_awal IS NULL,'',
														IF(jam_bongkar_akhir IS NULL,CONCAT(GATE,'<br>',jam_bongkar_awal),
														CONCAT('Mulai : ', jam_bongkar_awal,'<br>','Akhir : ', jam_bongkar_akhir))),
													IF(jam_akhir_loading IS NULL,CONCAT(GATE,'<br>',jam_awal_loading),
														CONCAT('Mulai : ', jam_awal_loading,'<br>','Akhir : ', jam_akhir_loading))) LOADING,
												IFNULL(akhir_admin,'-') akhir_admin,
												IFNULL(tgl_keluar,'-') tgl_keluar,
												IFNULL(LAST_TRANSAKSI,'-') LAST_TRANSAKSI,
												IFNULL(tgl_keluar,IFNULL(awal_admin,0)) STT_MERAH
											FROM mtp_trs_kendaraan_customer
											WHERE DATE(NOW())>DATE(TGL_MASUK) AND tgl_keluar IS NULL
										UNION
											SELECT KODE_CUSTOMER, NO_KENDARAAN, NO_SURAT_JALAN,
												IFNULL(jenis_presensi,'-') jenis_presensi,
												tgl_masuk, IFNULL(awal_admin,'-') awal_admin,
												IF(jam_awal_loading IS NULL,
													IF(jam_bongkar_awal IS NULL,'',
														IF(jam_bongkar_akhir IS NULL,CONCAT(GATE,'<br>',jam_bongkar_awal),
														CONCAT('Mulai : ', jam_bongkar_awal,'<br>','Akhir : ', jam_bongkar_akhir))),
													IF(jam_akhir_loading IS NULL,CONCAT(GATE,'<br>',jam_awal_loading),
														CONCAT('Mulai : ', jam_awal_loading,'<br>','Akhir : ', jam_akhir_loading))) LOADING,
												IFNULL(akhir_admin,'-') akhir_admin,
												IFNULL(tgl_keluar,'-') tgl_keluar,
												IFNULL(LAST_TRANSAKSI,'-') LAST_TRANSAKSI,
												IFNULL(tgl_keluar,IFNULL(awal_admin,0)) STT_MERAH
											FROM mtp_trs_kendaraan_customer
											WHERE DATE(TGL_MASUK)=DATE(NOW())
											ORDER BY STT_MERAH, LAST_TRANSAKSI, tgl_masuk, TGL_MASUK 
										) B	";
									$queryParam = null;
									if($presensi === 'INBOUND') {
										$queryString = $queryString." WHERE B.jenis_presensi='INBOUND' ";

										if($customer !== 'ALL') {
											$queryInput = explode(',', $customer);
											$queryString = $queryString." AND B.KODE_CUSTOMER IN (SELECT NAMA FROM MTP_MASTER_DC WHERE KODE_DC IN (". implode(',', array_fill(0, count(explode(',', $customer)), '?')) .")) ";
											$queryParam = str_repeat('s', count($queryInput));
										}
									} else {
										if($presensi === 'OUTBOUND') {
											$queryString = $queryString." WHERE B.jenis_presensi='OUTBOUND' ";

											if($customer !== 'ALL') {
												$queryInput = explode(',', $customer);
												$queryString = $queryString." AND B.KODE_CUSTOMER IN (SELECT NAMA FROM MTP_MASTER_DC WHERE KODE_DC IN (". implode(',', array_fill(0, count(explode(',', $customer)), '?')) .")) ";
												$queryParam = str_repeat('s', count($queryInput));
											}
										} else {
											if($customer != 'ALL') {
												$queryInput = explode(',', $customer);
												$queryString = $queryString." WHERE B.KODE_CUSTOMER IN (SELECT NAMA FROM MTP_MASTER_DC WHERE KODE_DC IN (". implode(',', array_fill(0, count(explode(',', $customer)), '?')) .")) ";
												$queryParam = str_repeat('s', count($queryInput));
											}
										}
									}
									$queryString = $queryString.' ORDER BY B.STT_MERAH, B.LAST_TRANSAKSI, B.tgl_masuk, B.TGL_MASUK ';

									// Query data: Fetch live history of Customer transaction
									$fetchDataTrs = db_runQuery(array(
										'config_array' => $customerDBMS,
										'database_index' => 0,
										'input' => $queryInput,
										'query' => $queryString,
										'param' => $queryParam,
										'getData' => true,
										'getAllRow' => true
									));
									if(!isEmptyVar($fetchDataTrs) && $fetchDataTrs !== 'ZERO_DATA' && count($fetchDataTrs) > 0) {
										$jsonResponse['success']['LiveHistory'] = true;
										$jsonResponse['message']['LiveHistory'] = sprintf('Data found (%s) entries!', count($fetchDataTrs));
										$jsonResponse['datetime']['LiveHistory'] = date('Y-m-d H:i:s');

										// Serve data
										foreach($fetchDataTrs as $idx => $row) {
											if(strtoupper(trim($row['jenis_presensi'])) == 'PRESENSI SUPPLIER') {
												$row['jenis_presensi'] = 'INBOUND';
											}
											if(strtoupper(trim($row['jenis_presensi'])) == 'PRESENSI PENGIRIMAN') {
												$row['jenis_presensi'] = 'OUTBOUND';
											}
											if(isEmptyVar(trim($row['LOADING']))) {
												$row['LOADING'] = '-';
											}
											if(!isEmptyVar(trim($row['NO_SURAT_JALAN'])) || trim($row['NO_SURAT_JALAN']) === '.') {
												$row['NO_SURAT_JALAN'] = '-';
											}
											$jsonResponse['data']['LiveHistory'][$idx] = array(
												'kode_customer' => strtoupper($row["KODE_CUSTOMER"]),
												'no_kendaraan' => $row["NO_KENDARAAN"],
												'no_surat_jalan' => $row["NO_SURAT_JALAN"],
												'jenis_presensi' => $row["jenis_presensi"],
												'tgl_masuk' => $row["tgl_masuk"],
												'tgl_keluar' => $row["tgl_keluar"],
												'loading' => $row["LOADING"],
												'admin_in' => $row["awal_admin"],
												'admin_out' => $row["akhir_admin"],
												'red_flag' => false
											);
											if(trim($row["awal_admin"]) === '-' && trim($row["tgl_keluar"]) === '-') {
												$jsonResponse['data']['LiveHistory'][$idx]['red_flag'] = true;
											} else {
												$jsonResponse['data']['LiveHistory'][$idx]['red_flag'] = false;
											}
										}

										$jsonResponse['took']['LiveHistory'] = (floor(microtime(true)*1000))-$startTime.'ms';
										$jsonResponse['errcode']['LiveHistory'] = 0;
									}
								} else {
									foreach(array('success','data','errcode','message') as $perResponse) {
										unset($jsonResponse[$perResponse]['LiveHistory']);
									}
								}
							} else {
								$jsonResponse['success'] = false;
								$jsonResponse['message'] = 'Customer not found in registered DC or not yet active!';
								$jsonResponse['datetime'] = date('Y-m-d H:i:s');
								unset($jsonResponse['data']);
								$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
								$jsonResponse['errcode'] = 6;
								unset($jsonResponse['errors']);
							}
						} else {
							$jsonResponse['success'] = false;
							$jsonResponse['message'] = 'Error, configuration database not found with code provided, (code DC)!';
							$jsonResponse['datetime'] = date('Y-m-d H:i:s');
							unset($jsonResponse['data']);
							$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode'] = 5;
							unset($jsonResponse['errors']);
						}
					} else {
						$jsonResponse['success'] = false;
						$jsonResponse['message'] = 'The customer is not yet officially active, (code DC)!';
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						unset($jsonResponse['data']);
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 4;
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

		/* User-type: Customer-service */
		case 'init-cs':
			/*
			 * Stage: 1
			 * Check if the user is online and has a NIK
			 */
			if(!isEmptyVar($userData->nik)) {
				/*
				 * Stage: 2 A
				 * Check if the account is registered with the current NIK
				 */
				$isExist = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik),
					'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) as TOTAL, NAMA, NAMA_PERUSAHAAN, KODE_STOREKEY FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
					'param' => 's',
					'getData' => true,
					'getAllRow' => false
				));

				/*
				 * Stage: 2 B
				 * Check if the account is had access to some privileges
				 */
				$isHadAccess = false;
				$isPartPrivileges = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik, 1, 'cs'),
					'query' => sprintf('SELECT * FROM %s WHERE NIK = ? AND (LEVEL = ? OR LEVEL_CODE = ?);', $APP_CORE['tb_prefix'].'user_privileges'),
					'param' => 'sss',
					'getData' => true,
					'getAllRow' => false
				));
				if(!isEmptyVar($isPartPrivileges) && $isPartPrivileges !== 'ZERO_DATA') {
					if(array_key_exists('PERMISSIONS', $isPartPrivileges) && !isEmptyVar($isPartPrivileges['PERMISSIONS'])) {
						if(isJSON($isPartPrivileges['PERMISSIONS'])) {
							$accountPrivileges = json_decode($isPartPrivileges['PERMISSIONS'], true);
							if(is_array($accountPrivileges) && isAssoc($accountPrivileges)) {
								$restrictPage = searchArrAssoc($accountPrivileges['privileges']['pages'], 'link', 'dashboard');
								if(count($restrictPage) >= 1 && is_array($restrictPage)) {
									$restrictPage = $restrictPage[0]; // Reset array
									if(is_array($restrictPage) && $restrictPage['view'] === true) {
										$isHadAccess = true;
										$isPartPrivileges = $restrictPage;
									}
								}
							}
						}
					}
				}
				if(isset($isExist['TOTAL']) && $isExist['TOTAL'] >= 1 && $isHadAccess === true) {
					$jsonResponse['success'] = array();
					$jsonResponse['message'] = array();
					$jsonResponse['datetime'] = array();
					$jsonResponse['data'] = array();
					$jsonResponse['took'] = array();
					$jsonResponse['errcode'] = array();
					$jsonResponse['errors'] = array();

					$serveData = array(
						'FilterDCaC' => true, // alias: Filter DC and Customer
						'ThroughputIO' => true,
						'StorageTempHum' => true,
						'ChamberUsage' => true,
						'AccumulativeIssues' => true // alias: Accumulative total Issues (Open/Closed)
					);

					foreach($serveData as $key => $val) {
						$ruleBlock = searchArrAssoc($isPartPrivileges['blocks'], 'id', $key);
						if(count($ruleBlock) >= 1 && is_array($ruleBlock)) {
							$ruleBlock = $ruleBlock[0]; // Reset array
							$serveData[$key] = (is_array($ruleBlock) && $ruleBlock['view'] === true) ? true : false;
						}
					}

					if(!isEmptyVar($dataRequest) && is_string($dataRequest) && strlen($dataRequest) >= 30) {
						$dataRequest = json_decode(jsonFixer($EVW->decrypt($dataRequest, $APP_CORE['app_key_encrypt']), true), true) ?? null;
						if(!isEmptyVar($dataRequest) && isAssoc($dataRequest)) {
							$defaultData = array(
								'ThroughputIO' => array(
									'uom' => 'multi'
								),
								'StorageTempHum' => array(
									'period' => date('Y-m-d'),
									'cs' => 'all',
									'view' => 'compact'
								),
								'ChamberUsage' => array(
									'customer' => ''
								)
							);
							$dataRequest = array_replace_recursive($defaultData, array_intersect_key($dataRequest, $defaultData));

							// Check is valid data
							if(!in_array($dataRequest['ThroughputIO']['uom'], array('multi', 'lpn'))) {
								$dataRequest['ThroughputIO']['uom'] = 'multi';
							}
							// if(!in_array($dataRequest['StorageTempHum']['view'], array('raw', 'compact'))) {
							// 	$dataRequest['StorageTempHum']['view'] = 'compact';
							// }
						}
					}

					/*
					 * Default values
					 */
					if(boolval($serveData['FilterDCaC']) === true) {
						// Result in JSON
						$jsonResponse['success']['FilterDCaC'] = array('dc' => false, 'customer' => false);
						$jsonResponse['message']['FilterDCaC'] = array('dc' => 'Data not found!', 'customer' => 'Data not found!');
						$jsonResponse['datetime']['FilterDCaC'] = date('Y-m-d H:i:s');
						$jsonResponse['data']['FilterDCaC'] = array('dc' => array(), 'customer' => array());
						$jsonResponse['took']['FilterDCaC'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode']['FilterDCaC'] = array('dc' => 1, 'customer' => 1);

						// Query: Get all DC list, with prefix 'MTP'
						$queryInput = array('MTP%', 1, 1);
						$queryString = "SELECT KODE_DC, NAMA, DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME FROM mtp_master_koneksi WHERE KODE_DC IN (SELECT KODE_DC FROM mtp_master_dc WHERE KODE_DC LIKE ? AND AKTIF_DC = ?) AND FLAG = ?;";
						$queryParam = str_repeat('s', count($queryInput));

						$fetchDCList = db_runQuery(array(
							'config_array' => $configMysql,
							'database_index' => 1,
							'input' => $queryInput,
							'query' => $queryString,
							'param' => $queryParam,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchDCList) && $fetchDCList !== 'ZERO_DATA' && count($fetchDCList) >= 1) {
							$jsonResponse['success']['FilterDCaC']['dc'] = true;
							$jsonResponse['message']['FilterDCaC']['dc'] = sprintf('Data found (%s) entries!', count($fetchDCList) - 1);
							$jsonResponse['datetime']['FilterDCaC'] = date('Y-m-d H:i:s');
							// Serve data
							$jsonResponse['data']['FilterDCaC']['dc'][] = array(
								'code' => 'all',
								'name' => 'All',
								'selected' => true
							);
							foreach($fetchDCList as $row) {
								$jsonResponse['data']['FilterDCaC']['dc'][] = array(
									'code' => $row['KODE_DC'],
									'name' => trim(str_replace(['DC', 'MTP'], ['',''], $row['NAMA'])),
									'selected' => false,
									'dbms' => array(
										'host' => $row['DB_HOST'],
										'port' => $row['DB_PORT'],
										'user' => $row['DB_USER'],
										'pass' => $row['DB_PASS'],
										'name' => $row['DB_NAME']
									)
								);
							}
							$jsonResponse['took']['FilterDCaC'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode']['FilterDCaC']['dc'] = 0;
						} else {
							$jsonResponse['success']['FilterDCaC']['dc'] = false;
							$jsonResponse['message']['FilterDCaC']['dc'] = 'Data not found!';
							$jsonResponse['datetime']['FilterDCaC'] = date('Y-m-d H:i:s');
							$jsonResponse['took']['FilterDCaC'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode']['FilterDCaC']['dc'] = 1;
						}

						// Query: Get all Customer list, without prefix 'MTP'
						$sessionKey = $APP_CORE['session_prefix'].'user-filter';
						$sessionRegistered = $user->takeSessionKey();
						$sessionData = array();
						if(is_array($sessionRegistered)) {
							$jsonResponse['data']['FilterDCaC']['customer'][] = array(
								'code' => 'all',
								'name' => 'All',
								'selected' => true
							);
							$filterAllCustomer = function($isCustomerSelected) use (&$sessionData, $sessionKey, &$jsonResponse, $databaseList, $configMysql) {
								$filterExistCustomer = array();
								foreach($jsonResponse['data']['FilterDCaC']['dc'] as $perDC) {
									if(array_key_exists('dbms', $perDC)) {
										$customerDBMS = array(
											'mysql_host' => $perDC['dbms']['host'],
											'mysql_username' => $perDC['dbms']['user'],
											'mysql_password' => (isEmptyVar($perDC['dbms']['pass'])) ? '' : $perDC['dbms']['pass'],
											'mysql_database' => array( $perDC['dbms']['name'] )
										);

										$queryInput = array('MTP%', 1);
										$queryString = "SELECT * FROM mtp_master_dc WHERE KODE_DC NOT LIKE ? AND AKTIF = ?;";
										$queryParam = str_repeat('s', count($queryInput));

										$fetchCustomerList = db_runQuery(array(
											'config_array' => $customerDBMS,
											'database_index' => 0,
											'input' => $queryInput,
											'query' => $queryString,
											'param' => $queryParam,
											'getData' => true,
											'getAllRow' => true
										));
										if(!isEmptyVar($fetchCustomerList) && $fetchCustomerList !== 'ZERO_DATA' && count($fetchCustomerList) >= 1) {
											foreach($fetchCustomerList as $idx => $row) {
												$codeCustomer = sprintf('%s.%s', $perDC['code'], $row['KODE_DC']);
												$jsonResponse['data']['FilterDCaC']['customer'][] = array(
													'code' => $codeCustomer,
													'parent' => ucwords(trim(str_replace(['DC', 'MTP'], ['', ''], $perDC['name']))),
													'name' => $row['NAMA'],
													'selected' => ($isCustomerSelected) ? ((in_array($codeCustomer, $sessionData['customer'])) ? true : false) : false
												);
												$idxLast = count($jsonResponse['data']['FilterDCaC']['customer']) - 1;
												if($jsonResponse['data']['FilterDCaC']['customer'][$idxLast]['selected']) {
													$filterExistCustomer[] = $codeCustomer;
												}
											}
										}
									}
								}
								if(is_array($sessionData['customer']) && count($sessionData['customer']) >= 1 && $sessionData['customer'][0] !== 'all') {
									foreach($sessionData['customer'] as $idx => $val) {
										if(!in_array($val, $filterExistCustomer)) {
											unset($sessionData['customer'][$idx]);
										}
									}
									if(count($sessionData['customer']) === 0) {
										$sessionData['customer'] = array('all');
									}
									$_SESSION[$sessionKey] = $sessionData;
								}
							};

							if(in_array($sessionKey, $sessionRegistered)) {
								$sessionData = $_SESSION[$sessionKey];
								$isCustomerSelected = false;
								if(array_key_exists('customer', $sessionData) && is_array($sessionData['customer']) && count($sessionData['customer']) >= 1) {
									if($sessionData['customer'][0] !== 'all') {
										$isCustomerSelected = true;
									}
								}

								if(array_key_exists('dc', $sessionData) && is_array($sessionData['dc']) && count($sessionData['dc']) >= 1) {
									if(trim(strtolower($sessionData['dc'][0])) === 'all') {
										$filterAllCustomer($isCustomerSelected);

										$jsonResponse['success']['FilterDCaC']['customer'] = ((count($jsonResponse['data']['FilterDCaC']['customer']) - 1) !== 0) ? true : false;
										$jsonResponse['message']['FilterDCaC']['customer'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['FilterDCaC']['customer']) - 1);
										$jsonResponse['errcode']['FilterDCaC']['customer'] = ((count($jsonResponse['data']['FilterDCaC']['customer']) - 1) !== 0) ? 0 : 1;
									} else {
										$totalSelectedDC = 0;
										foreach($jsonResponse['data']['FilterDCaC']['dc'] as $idx => $perDC) {
											if(in_array($perDC['code'], $sessionData['dc'])) {
												$jsonResponse['data']['FilterDCaC']['dc'][$idx]['selected'] = true;
												$totalSelectedDC += 1;
											}
										}
										if($totalSelectedDC >= 1) {
											$jsonResponse['data']['FilterDCaC']['dc'][0]['selected'] = false;
										}

										$listFilterDC = db_runQuery(array(
											'config_array' => $configMysql,
											'database_index' => 1,
											'input' => array_merge($sessionData['dc'], array(1, 1)),
											// 'query' => sprintf('SELECT KODE_DC, NAMA, DB_NAME FROM %s WHERE KODE_DC IN (%s) AND AKTIF_DC = ?;', 'mtp_master_dc', implode(',', array_fill(0, count($sessionData['dc']), '?'))),
											'query' => sprintf('SELECT KODE_DC, NAMA, DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME FROM %s WHERE KODE_DC IN (SELECT KODE_DC FROM %s WHERE KODE_DC IN (%s) AND AKTIF_DC = ?) AND FLAG = ?;', 'mtp_master_koneksi', 'mtp_master_dc', implode(',', array_fill(0, count($sessionData['dc']), '?'))),
											'param' => str_repeat('s', count($sessionData['dc']) + 2),
											'getData' => true,
											'getAllRow' => true
										));
										if(!isEmptyVar($listFilterDC) && $listFilterDC !== 'ZERO_DATA' && count($listFilterDC) >= 1) {
											foreach($listFilterDC as $perDC) {
												$customerDBMS = array(
													'mysql_host' => $perDC['DB_HOST'],
													'mysql_username' => $perDC['DB_USER'],
													'mysql_password' => (isEmptyVar($perDC['DB_PASS'])) ? '' : $perDC['DB_PASS'],
													'mysql_database' => array( $perDC['DB_NAME'] )
												);

												$queryInput = array('MTP%', 1);
												$queryString = "SELECT * FROM mtp_master_dc WHERE KODE_DC NOT LIKE ? AND AKTIF = ?;";
												$queryParam = str_repeat('s', count($queryInput));

												$fetchCustomerList = db_runQuery(array(
													'config_array' => $customerDBMS,
													'database_index' => 0,
													'input' => $queryInput,
													'query' => $queryString,
													'param' => $queryParam,
													'getData' => true,
													'getAllRow' => true
												));
												if(!isEmptyVar($fetchCustomerList) && $fetchCustomerList !== 'ZERO_DATA' && count($fetchCustomerList) >= 1) {
													$filterExistCustomer = array();
													foreach($fetchCustomerList as $idx => $row) {
														$codeCustomer = sprintf('%s.%s', $perDC['KODE_DC'], $row['KODE_DC']);
														$jsonResponse['data']['FilterDCaC']['customer'][] = array(
															'code' => $codeCustomer,
															'parent' => ucwords(trim(str_replace(['DC', 'MTP'], ['', ''], $perDC['NAMA']))),
															'name' => $row['NAMA'],
															'selected' => ($isCustomerSelected) ? ((in_array($codeCustomer, $sessionData['customer'])) ? true : false) : false
														);
														$idxLast = count($jsonResponse['data']['FilterDCaC']['customer']) - 1;
														if($jsonResponse['data']['FilterDCaC']['customer'][$idxLast]['selected'] === true) {
															$filterExistCustomer[] = $codeCustomer;
														}
													}
													if(is_array($sessionData['customer']) && count($sessionData['customer']) >= 1 && $sessionData['customer'][0] !== 'all') {
														foreach($sessionData['customer'] as $idx => $val) {
															if(!in_array($val, $filterExistCustomer)) {
																unset($sessionData['customer'][$idx]);
															}
														}
														$_SESSION[$sessionKey] = $sessionData;
													}
												}
											}
										}

										$jsonResponse['success']['FilterDCaC']['customer'] = ((count($jsonResponse['data']['FilterDCaC']['customer']) - 1) !== 0) ? true : false;
										$jsonResponse['message']['FilterDCaC']['customer'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['FilterDCaC']['customer']) - 1);
										$jsonResponse['took']['FilterDCaC'] = (floor(microtime(true)*1000))-$startTime.'ms';
										$jsonResponse['errcode']['FilterDCaC']['customer'] = ((count($jsonResponse['data']['FilterDCaC']['customer']) - 1) !== 0) ? 0 : 1;
									}
								}
							} else {
								$_SESSION[$sessionKey] = array(
									'dc' => array('all'),
									'customer' => array('all')
								);
								$user->registerSessionKey($sessionKey);

								$filterAllCustomer($isCustomerSelected);

								$jsonResponse['success']['FilterDCaC']['customer'] = ((count($jsonResponse['data']['FilterDCaC']['customer']) - 1) !== 0) ? true : false;
								$jsonResponse['message']['FilterDCaC']['customer'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['FilterDCaC']['customer']) - 1);
								$jsonResponse['datetime']['FilterDCaC'] = date('Y-m-d H:i:s');
								$jsonResponse['took']['FilterDCaC'] = (floor(microtime(true)*1000))-$startTime.'ms';
								$jsonResponse['errcode']['FilterDCaC']['customer'] = ((count($jsonResponse['data']['FilterDCaC']['customer']) - 1) !== 0) ? 0 : 1;
							}
							if(array_key_exists('customer', $sessionData) && is_array($sessionData['customer']) && count($sessionData['customer']) >= 1) {
								if($sessionData['customer'][0] !== 'all') {
									$totalSelectedCustomer = 0;
									foreach($jsonResponse['data']['FilterDCaC']['customer'] as $idx => $perCustomer) {
										if(in_array($perCustomer['code'], $sessionData['customer'])) {
											// $jsonResponse['data']['FilterDCaC']['customer'][$idx]['selected'] = true;
											$totalSelectedCustomer += 1;
										}
									}
									if($totalSelectedCustomer >= 1) {
										$jsonResponse['data']['FilterDCaC']['customer'][0]['selected'] = false;
									}
								}
							}

							// Remove duplicate Unique ID
							if(count($jsonResponse['data']['FilterDCaC']['customer']) >= 2) { // 2 rows, because first is 'All'
								$jsonResponse['data']['FilterDCaC']['customer'] = uniqueAssocByKey($jsonResponse['data']['FilterDCaC']['customer'], 'code');
							}

							$jsonResponse['success']['FilterDCaC']['customer'] = ((count($jsonResponse['data']['FilterDCaC']['customer']) - 1) !== 0) ? true : false;
							$jsonResponse['message']['FilterDCaC']['customer'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['FilterDCaC']['customer']) - 1);
							$jsonResponse['datetime']['FilterDCaC'] = date('Y-m-d H:i:s');
							$jsonResponse['took']['FilterDCaC'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode']['FilterDCaC']['customer'] = ((count($jsonResponse['data']['FilterDCaC']['customer']) - 1) !== 0) ? 0 : 1;
						}

						// Clear important value
						foreach($jsonResponse['data']['FilterDCaC']['dc'] as $key => $row) {
							if(array_key_exists('dbms', $row)) {
								unset($jsonResponse['data']['FilterDCaC']['dc'][$key]['dbms']);
							}
						}
					}
					// Serve for: ThroughputIO 
					if(boolval($serveData['ThroughputIO']) === true) {
						// Result in JSON
						$jsonResponse['success']['ThroughputIO'] = array('charts' => false, 'tables' => false);
						$jsonResponse['message']['ThroughputIO'] = array('charts' => 'Data not found!', 'tables' => 'Data not found!');
						$jsonResponse['datetime']['ThroughputIO'] = date('Y-m-d H:i:s');
						$jsonResponse['data']['ThroughputIO'] = array('summary' => array(), 'charts' => array(), 'tables' => array());
						$jsonResponse['took']['ThroughputIO'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode']['ThroughputIO'] = array('charts' => 1, 'tables' => 1);

						// Default summary values
						$jsonResponse['data']['ThroughputIO']['summary'] = array(
							'on_date' => date('F Y'),
							'total_inbound' => 0,
							'total_outbound' => 0,
							'uom' => $dataRequest['ThroughputIO']['uom']
						);

						// Default chart values
						$totalDays = date('t');
						for ($i = 0; $i < $totalDays; $i++) {
							$jsonResponse['data']['ThroughputIO']['charts'][$i] = array(
								'inbound' => 0,
								'outbound' => 0
							);
						}
					}
					// Serve for: StorageTempHum 
					if(boolval($serveData['StorageTempHum']) === true) {
						// Result in JSON
						$jsonResponse['success']['StorageTempHum'] = array('charts' => array('temperature' => 0, 'humidity' => 0), 'listPeriod' => false, 'listCS' => false, 'listView' => true);
						$jsonResponse['message']['StorageTempHum'] = array('charts' => array('temperature' => 'Data not found!', 'humidity' => 'Data not found!'), 'listPeriod' => 'Data not found!', 'listCS' => 'Data not found!', 'listView' => 'Data not found!');
						$jsonResponse['datetime']['StorageTempHum'] = date('Y-m-d H:i:s');
						$jsonResponse['data']['StorageTempHum'] = array('charts' => array('temperature' => array(), 'humidity' => array()), 'listPeriod' => array(), 'listCS' => array(), 'listView' => array(), 'datePicked' => null);
						$jsonResponse['took']['ThroughputIO'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode']['StorageTempHum'] = array('charts' => array('temperature' => 1, 'humidity' => 1), 'listPeriod' => 1, 'listCS' => 1, 'listView' => 0);

						// Default charts
						$jsonResponse['data']['StorageTempHum']['charts'] = array(
							'temperature' => array(
								0 => array(
									'name' => '-',
									'label' => array(),
									'datasets' => array(),
									'stableRate' => 0,
								),
							),
							'humidity' => array(
								0 => array(
									'name' => '-',
									'label' => array(),
									'datasets' => array(),
									'stableRate' => 0,
								),
							),
						);
						for ($i = 0; $i < 24; $i++) {
							$jsonResponse['data']['StorageTempHum']['charts']['temperature'][0]['label'][$i] = sprintf('%s:00', str_pad($i, 2, '0', STR_PAD_LEFT));
							$jsonResponse['data']['StorageTempHum']['charts']['temperature'][0]['datasets'][$i] = 0;
							$jsonResponse['data']['StorageTempHum']['charts']['humidity'][0]['label'][$i] = sprintf('%s:00', str_pad($i, 2, '0', STR_PAD_LEFT));
							$jsonResponse['data']['StorageTempHum']['charts']['humidity'][0]['datasets'][$i] = 0;
						}
						
						// Default listPeriod
						$jsonResponse['data']['StorageTempHum']['listPeriod'] = array(
							'maxRange' => 100, // per date/days
							'totalPeriod' => 100, // per date/days
							'availableDate' => array(
								0 => array(
									'name' => 'Today',
									'code' => hash('md5', sprintf('%s%s', date('Y-m-d'), $APP_CORE['app_key_encrypt']))
								) // Insert current today
							)
						);

						// Default listCS
						$jsonResponse['data']['StorageTempHum']['listCS'][] = array(
							'name' => 'All',
							'code' => hash('md5', sprintf('%s%s', 'all', $APP_CORE['app_key_encrypt']))
						);

						// Default listView
						$jsonResponse['data']['StorageTempHum']['listView'] = array(
							0 => array(
								'name' => 'Compact',
								'code' => hash('md5', sprintf('%s%s', 'compact', $APP_CORE['app_key_encrypt']))
							),
							1 => array(
								'name' => 'RAW',
								'code' => hash('md5', sprintf('%s%s', 'raw', $APP_CORE['app_key_encrypt']))
							),
						);

						// Default datePicked
						$jsonResponse['data']['StorageTempHum']['datePicked'] = date('F d, Y');
						// $jsonResponse['data']['StorageTempHum']['datePicked'] = date('F d, Y', strtotime('-1 days')); // Yesterday
					}
					// Serve for: ChamberUsage 
					if(boolval($serveData['ChamberUsage']) === true) {
						// Result in JSON
						$jsonResponse['success']['ChamberUsage'] = false;
						$jsonResponse['message']['ChamberUsage'] = 'Data not found!';
						$jsonResponse['datetime']['ChamberUsage'] = date('Y-m-d H:i:s');
						$jsonResponse['data']['ChamberUsage'] = array('summary' => array(), 'charts' => array(), 'labels' => array(), 'listCustomer' => array());
						$jsonResponse['took']['ChamberUsage'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode']['ChamberUsage'] = 1;

						$jsonResponse['data']['ChamberUsage']['summary'] = array(
							'customer_name' => '-',
							'customer_logo' => path2url(sprintf('%s/image/illustrations/avatar-%s.png', DIR_ASSET, $appConfig['CORE']['app_build_version'])),
							'total_available' => 0,
							'total_used' => 0
						);

						$jsonResponse['data']['ChamberUsage']['charts'] = array(
							'datasets' => array(),
							'totalUsage' => 0,
							'lastUpdate' => 'EMPTY',
						);
					}
					// Serve for: AccumulativeIssues 
					if(boolval($serveData['AccumulativeIssues']) === true) {
						// Result in JSON
						$jsonResponse['success']['AccumulativeIssues'] = false;
						$jsonResponse['message']['AccumulativeIssues'] = 'Data not found!';
						$jsonResponse['datetime']['AccumulativeIssues'] = date('Y-m-d H:i:s');
						$jsonResponse['data']['AccumulativeIssues'] = array('charts' => array(), 'total' => array(), 'averageSolved' => '-');
						$jsonResponse['took']['AccumulativeIssues'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode']['AccumulativeIssues'] = 1;

						$totalDays = date('t');
						for ($i = 0; $i < $totalDays; $i++) {
							$jsonResponse['data']['AccumulativeIssues']['charts'][$i] = array(
								'open' => 0,
								'closed' => 0
							);
						}
						$jsonResponse['data']['AccumulativeIssues']['total'] = array(
							'open' => 0,
							'closed' => 0,
						);
					}

					/*
					 * Processing data
					 */
					$sessionKey = $APP_CORE['session_prefix'].'user-filter';
					if(!array_key_exists($sessionKey, $_SESSION)) {
						$_SESSION[$sessionKey] = array(
							'dc' => array(
								0 => 'MTP001'
							),
							'customer' => array(
								0 => 'all'
							)
						);
						$user->registerSessionKey($sessionKey);
					}
					$sessionRegistered = $user->takeSessionKey();
					$sessionData = array();

					$isPerCustomer = false;
					$doCustomer = function($customerData, $dcData, $customerDBMS, $isPerCustomer) use (&$jsonResponse, $configMysql, $appConfig, $isPartPrivileges, $serveData, $dataRequest) {
						if(boolval($isPerCustomer) === false) {
							if(array_key_exists('customer', $customerData) && count($customerData['customer']) >= 1) {
								if(boolval($serveData['ThroughputIO']) === true) {
									// Check privileges before proceeding get data
									$ruleBlock = searchArrAssoc($isPartPrivileges['blocks'], 'id', 'ThroughputIO');
									if(count($ruleBlock) >= 1 && is_array($ruleBlock)) {
										$ruleBlock = $ruleBlock[0]; // Reset array
									}

									$prefixTable = 'pallet';
									switch(strtolower(trim($dataRequest['ThroughputIO']['uom']))) {
										case 'multi': $prefixTable = 'pcs'; break;
										case 'lpn': $prefixTable = 'pallet'; break;
									}

									// Create default tables value
									foreach($customerData['customer'] as $perCustomer) {
										// Reverse Company [PT] Name, from end to start
										if(substr(strtolower(trim($perCustomer)), -2) === 'pt') {
											$perCustomer = 'PT. ' . trim(substr(trim($perCustomer), 0, -2));
										}

										$jsonResponse['data']['ThroughputIO']['tables'][] = array(
											'customer' => strtoupper($perCustomer),
											'inbound' => 0,
											'outbound' => 0,
											'total' => 0,
										);
									}

									// Serve data
									$queryInput = $customerData['storekey'];
									$queryString = sprintf('
										SELECT
											CUSTOMER STOREKEY
											, SUM(QTY_IN_1) QTY_IN_1, SUM(QTY_OUT_1) QTY_OUT_1
											, SUM(QTY_IN_2) QTY_IN_2, SUM(QTY_OUT_2) QTY_OUT_2
											, SUM(QTY_IN_3) QTY_IN_3, SUM(QTY_OUT_3) QTY_OUT_3
											, SUM(QTY_IN_4) QTY_IN_4, SUM(QTY_OUT_4) QTY_OUT_4
											, SUM(QTY_IN_5) QTY_IN_5, SUM(QTY_OUT_5) QTY_OUT_5
											, SUM(QTY_IN_6) QTY_IN_6, SUM(QTY_OUT_6) QTY_OUT_6
											, SUM(QTY_IN_7) QTY_IN_7, SUM(QTY_OUT_7) QTY_OUT_7
											, SUM(QTY_IN_8) QTY_IN_8, SUM(QTY_OUT_8) QTY_OUT_8
											, SUM(QTY_IN_9) QTY_IN_9, SUM(QTY_OUT_9) QTY_OUT_9
											, SUM(QTY_IN_10) QTY_IN_10, SUM(QTY_OUT_10) QTY_OUT_10
											, SUM(QTY_IN_11) QTY_IN_11, SUM(QTY_OUT_11) QTY_OUT_11
											, SUM(QTY_IN_12) QTY_IN_12, SUM(QTY_OUT_12) QTY_OUT_12
											, SUM(QTY_IN_13) QTY_IN_13, SUM(QTY_OUT_13) QTY_OUT_13
											, SUM(QTY_IN_14) QTY_IN_14, SUM(QTY_OUT_14) QTY_OUT_14
											, SUM(QTY_IN_15) QTY_IN_15, SUM(QTY_OUT_15) QTY_OUT_15
											, SUM(QTY_IN_16) QTY_IN_16, SUM(QTY_OUT_16) QTY_OUT_16
											, SUM(QTY_IN_17) QTY_IN_17, SUM(QTY_OUT_17) QTY_OUT_17
											, SUM(QTY_IN_18) QTY_IN_18, SUM(QTY_OUT_18) QTY_OUT_18
											, SUM(QTY_IN_19) QTY_IN_19, SUM(QTY_OUT_19) QTY_OUT_19
											, SUM(QTY_IN_20) QTY_IN_20, SUM(QTY_OUT_20) QTY_OUT_20
											, SUM(QTY_IN_21) QTY_IN_21, SUM(QTY_OUT_21) QTY_OUT_21
											, SUM(QTY_IN_22) QTY_IN_22, SUM(QTY_OUT_22) QTY_OUT_22
											, SUM(QTY_IN_23) QTY_IN_23, SUM(QTY_OUT_23) QTY_OUT_23
											, SUM(QTY_IN_24) QTY_IN_24, SUM(QTY_OUT_24) QTY_OUT_24
											, SUM(QTY_IN_25) QTY_IN_25, SUM(QTY_OUT_25) QTY_OUT_25
											, SUM(QTY_IN_26) QTY_IN_26, SUM(QTY_OUT_26) QTY_OUT_26
											, SUM(QTY_IN_27) QTY_IN_27, SUM(QTY_OUT_27) QTY_OUT_27
											, SUM(QTY_IN_28) QTY_IN_28, SUM(QTY_OUT_28) QTY_OUT_28
											, SUM(QTY_IN_29) QTY_IN_29, SUM(QTY_OUT_29) QTY_OUT_29
											, SUM(QTY_IN_30) QTY_IN_30, SUM(QTY_OUT_30) QTY_OUT_30
											, SUM(QTY_IN_31) QTY_IN_31, SUM(QTY_OUT_31) QTY_OUT_31
										FROM %s WHERE CUSTOMER IN (%s)
										GROUP BY CUSTOMER
										ORDER BY CUSTOMER;
									', 'mtp_in_out_resume_'.$prefixTable.'_'.date('ym'), rtrim(trim(str_repeat('?, ', count(array_keys($queryInput)))), ','));
									$queryParam = str_repeat('s', count(array_keys($queryInput)));

									$fetchThroughputIO = db_runQuery(array(
										'config_array' => $customerDBMS,
										'database_index' => 0,
										'input' => $queryInput,
										'query' => $queryString,
										'param' => $queryParam,
										'getData' => true,
										'getAllRow' => true
									));
									if(!isEmptyVar($fetchThroughputIO) && $fetchThroughputIO !== 'ZERO_DATA' && count($fetchThroughputIO) >= 1) {
										$jsonResponse['success']['ThroughputIO']['charts'] += 1;
										$jsonResponse['success']['ThroughputIO']['tables'] += 1;
										$jsonResponse['datetime']['ThroughputIO'] = date('Y-m-d H:i:s');

										// Serve data
										foreach($fetchThroughputIO as $perRow) {
											$totalDays = date('t');
											$totalInbound = 0;
											$totalOutbound = 0;
											$customerKey = $perRow['STOREKEY'];
											unset($perRow['STOREKEY']);

											foreach($perRow as $key => $val) {
												$idx = preg_replace('/[^0-9]/', '', $key);
												if((intval($idx) - 1) >= $totalDays) { break; }
												$type = (wordExist(strtoupper($key), 'IN')) ? 'inbound' : 'outbound';
												$jsonResponse['data']['ThroughputIO']['charts'][intval($idx) - 1][$type] += $val;
												if($type === 'inbound') {
													$totalInbound += $val;
												}
												if($type === 'outbound') {
													$totalOutbound += $val;
												}
											}

											$indexOfCustomer = array_search($customerKey, $customerData['storekey']);
											$jsonResponse['data']['ThroughputIO']['tables'][$indexOfCustomer]['inbound'] = $totalInbound;
											$jsonResponse['data']['ThroughputIO']['tables'][$indexOfCustomer]['outbound'] = $totalOutbound;
											$jsonResponse['data']['ThroughputIO']['tables'][$indexOfCustomer]['total'] = $totalInbound + $totalOutbound;
											$jsonResponse['data']['ThroughputIO']['summary']['total_inbound'] += $totalInbound;
											$jsonResponse['data']['ThroughputIO']['summary']['total_outbound'] += $totalOutbound;
										}
									}
								}
							}
						}
					};
					// Filter data by Customer/DC
					if(is_array($sessionRegistered)) {
						if(in_array($sessionKey, $sessionRegistered)) {
							$sessionData = $_SESSION[$sessionKey];
							if(array_key_exists('dc', $sessionData) && is_array($sessionData['dc']) && count($sessionData['dc']) >= 1) {
								if(trim(strtolower($sessionData['dc'][0])) === 'all') {
									$listAllDC = db_runQuery(array(
										'config_array' => $configMysql,
										'database_index' => 1,
										'input' => array('MTP%', 1, 1),
										// 'query' => sprintf('SELECT KODE_DC, NAMA, DB_NAME FROM %s WHERE KODE_DC LIKE ? AND AKTIF_DC = ?;', 'mtp_master_dc'),
										'query' => sprintf('SELECT KODE_DC, NAMA, DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME FROM %s WHERE KODE_DC IN (SELECT KODE_DC FROM %s WHERE KODE_DC LIKE ? AND AKTIF_DC = ?) AND FLAG = ?;', 'mtp_master_koneksi', 'mtp_master_dc'),
										'param' => 'sii',
										'getData' => true,
										'getAllRow' => true
									));
									if(!isEmptyVar($listAllDC) && $listAllDC !== 'ZERO_DATA' && count($listAllDC) >= 1) {
										foreach($listAllDC as $perDC) {
											$customerDBMS = array(
												'mysql_host' => $perDC['DB_HOST'],
												'mysql_username' => $perDC['DB_USER'],
												'mysql_password' => (isEmptyVar($perDC['DB_PASS'])) ? '' : $perDC['DB_PASS'],
												'mysql_database' => array( $perDC['DB_NAME'] )
											);

											if(trim(strtolower($sessionData['customer'][0])) === 'all') {
												$queryInput = array('MTP%', 1);
												$queryString = "SELECT * FROM mtp_master_dc WHERE KODE_DC NOT LIKE ? AND AKTIF = ?;";
												$queryParam = str_repeat('s', count($queryInput));

												$listAllCustomer = db_runQuery(array(
													'config_array' => $customerDBMS,
													'database_index' => 0,
													'input' => $queryInput,
													'query' => $queryString,
													'param' => $queryParam,
													'getData' => true,
													'getAllRow' => true
												));
												if(!isEmptyVar($listAllCustomer) && $listAllCustomer !== 'ZERO_DATA' && count($listAllCustomer) >= 1) {
													$listCodeCustomer = array(
														'customer' => array(),
														'kode_dc' => array(),
														'storekey' => array()
													);
													foreach($listAllCustomer as $perCustomer) {
														if($isPerCustomer) {
															$doCustomer($perCustomer, $perDC, $customerDBMS, $isPerCustomer);
														} else {
															$listCodeCustomer['customer'][] = $perCustomer['NAMA'];
															$listCodeCustomer['kode_dc'][] = $perCustomer['KODE_DC'];
															$listCodeCustomer['storekey'][] = $perCustomer['STOREKEY'];
														}
													}
													if(boolval($isPerCustomer) === false) {
														$doCustomer($listCodeCustomer, $perDC, $customerDBMS, $isPerCustomer);
													}
												}
											} else {
												$listCodeCustomer = array(
													'customer' => array(),
													'kode_dc' => array(),
													'storekey' => array()
												);
												foreach($sessionData['customer'] as $perCustomer) {
													list($codeDC, $codeCustomer) = array_pad(explode('.', $perCustomer), 2, NULL);
													if(!isEmptyVar($codeDC) && !isEmptyVar($codeCustomer)) {
														if($codeDC === $perDC['KODE_DC']) {
															$isCustomerExist = db_runQuery(array(
																'config_array' => $customerDBMS,
																'database_index' => 0,
																'input' => array($codeCustomer, 1),
																'query' => sprintf('SELECT COUNT(true) as FOUND, KODE_DC, NAMA, STOREKEY FROM %s WHERE KODE_DC = ? AND AKTIF = ?;', 'mtp_master_dc'),
																'param' => 'si',
																'getData' => true,
																'getAllRow' => false
															));
															if(isset($isCustomerExist['FOUND']) && $isCustomerExist !== 'ZERO_DATA' && $isCustomerExist['FOUND'] >= 1) {
																if($isPerCustomer) {
																	$doCustomer($isCustomerExist, $perDC, $customerDBMS, $isPerCustomer);
																} else {
																	$listCodeCustomer['customer'][] = $isCustomerExist['NAMA'];
																	$listCodeCustomer['kode_dc'][] = $isCustomerExist['KODE_DC'];
																	$listCodeCustomer['storekey'][] = $isCustomerExist['STOREKEY'];
																}
															}
														}
													}
												}
												if(boolval($isPerCustomer) === false) {
													$doCustomer($listCodeCustomer, $perDC, $customerDBMS, $isPerCustomer);
												}
											}
										}
									}
								} else {
									$listFilterDC = db_runQuery(array(
										'config_array' => $configMysql,
										'database_index' => 1,
										'input' => array_merge($sessionData['dc'], array(1, 1)),
										// 'query' => sprintf('SELECT KODE_DC, NAMA, DB_NAME FROM %s WHERE KODE_DC IN (%s) AND AKTIF_DC = ?;', 'mtp_master_dc', implode(',', array_fill(0, count($sessionData['dc']), '?'))),
										'query' => sprintf('SELECT KODE_DC, NAMA, DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME FROM %s WHERE KODE_DC IN (SELECT KODE_DC FROM %s WHERE KODE_DC IN (%s) AND AKTIF_DC = ?) AND FLAG = ?;', 'mtp_master_koneksi', 'mtp_master_dc', implode(',', array_fill(0, count($sessionData['dc']), '?'))),
										'param' => str_repeat('s', count($sessionData['dc']) + 2),
										'getData' => true,
										'getAllRow' => true
									));
									if(!isEmptyVar($listFilterDC) && $listFilterDC !== 'ZERO_DATA' && count($listFilterDC) >= 1) {
										foreach($listFilterDC as $perDC) {
											$customerDBMS = array(
												'mysql_host' => $perDC['DB_HOST'],
												'mysql_username' => $perDC['DB_USER'],
												'mysql_password' => (isEmptyVar($perDC['DB_PASS'])) ? '' : $perDC['DB_PASS'],
												'mysql_database' => array( $perDC['DB_NAME'] )
											);

											if(trim(strtolower($sessionData['customer'][0])) === 'all') {
												$queryInput = array('MTP%', 1);
												$queryString = "SELECT * FROM mtp_master_dc WHERE KODE_DC NOT LIKE ? AND AKTIF = ?;";
												$queryParam = str_repeat('s', count($queryInput));

												$listAllCustomer = db_runQuery(array(
													'config_array' => $customerDBMS,
													'database_index' => 0,
													'input' => $queryInput,
													'query' => $queryString,
													'param' => $queryParam,
													'getData' => true,
													'getAllRow' => true
												));
												if(!isEmptyVar($listAllCustomer) && $listAllCustomer !== 'ZERO_DATA' && count($listAllCustomer) >= 1) {
													$listCodeCustomer = array(
														'customer' => array(),
														'kode_dc' => array(),
														'storekey' => array()
													);
													foreach($listAllCustomer as $perCustomer) {
														if($isPerCustomer) {
															$doCustomer($perCustomer, $perDC, $customerDBMS, $isPerCustomer);
														} else {
															$listCodeCustomer['customer'][] = $perCustomer['NAMA'];
															$listCodeCustomer['kode_dc'][] = $perCustomer['KODE_DC'];
															$listCodeCustomer['storekey'][] = $perCustomer['STOREKEY'];
														}
													}
													if(boolval($isPerCustomer) === false) {
														$doCustomer($listCodeCustomer, $perDC, $customerDBMS, $isPerCustomer);
													}
												}
											} else {
												$listCodeCustomer = array(
													'customer' => array(),
													'kode_dc' => array(),
													'storekey' => array()
												);
												foreach($sessionData['customer'] as $perCustomer) {
													list($codeDC, $codeCustomer) = array_pad(explode('.', $perCustomer), 2, NULL);
													if(!isEmptyVar($codeDC) && !isEmptyVar($codeCustomer)) {
														if($codeDC === $perDC['KODE_DC']) {
															$isCustomerExist = db_runQuery(array(
																'config_array' => $customerDBMS,
																'database_index' => 0,
																'input' => array($codeCustomer, 1),
																'query' => sprintf('SELECT COUNT(true) as FOUND, KODE_DC, NAMA, STOREKEY FROM %s WHERE KODE_DC = ? AND AKTIF = ?;', 'mtp_master_dc'),
																'param' => 'si',
																'getData' => true,
																'getAllRow' => false
															));
															if(isset($isCustomerExist['FOUND']) && $isCustomerExist !== 'ZERO_DATA' && $isCustomerExist['FOUND'] >= 1) {
																if($isPerCustomer) {
																	$doCustomer($isCustomerExist, $perDC, $customerDBMS, $isPerCustomer);
																} else {
																	$listCodeCustomer['customer'][] = $isCustomerExist['NAMA'];
																	$listCodeCustomer['kode_dc'][] = $isCustomerExist['KODE_DC'];
																	$listCodeCustomer['storekey'][] = $isCustomerExist['STOREKEY'];
																}
															}
														}
													}
												}
												if(boolval($isPerCustomer) === false) {
													$doCustomer($listCodeCustomer, $perDC, $customerDBMS, $isPerCustomer);
												}
											}
										}
									}
								}
							}
						}
					}

					// Serve for: StorageTempHum
					if(boolval($serveData['StorageTempHum']) === true) {
						// Fetch list of available Period
						$queryString = sprintf('SELECT DISTINCT(DATE(TGL)) AS DATE_PERIOD FROM %s GROUP BY DATE(TGL) ORDER BY TGL DESC LIMIT 0,%s;', 'mtp_report_temperature', $jsonResponse['data']['StorageTempHum']['listPeriod']['maxRange']);
						$fetchAvailablePeriod = db_runQuery(array(
							'config_array' => $customerDBMS,
							'database_index' => 0,
							'query' => $queryString,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchAvailablePeriod) && $fetchAvailablePeriod !== 'ZERO_DATA' && count($fetchAvailablePeriod) >= 1) {
							$jsonResponse['success']['StorageTempHum']['listPeriod'] = 0;
							$jsonResponse['datetime']['StorageTempHum'] = date('Y-m-d H:i:s');
							$jsonResponse['took']['StorageTempHum'] = (floor(microtime(true)*1000))-$startTime.'ms';
							foreach($fetchAvailablePeriod as $perRow) {
								$jsonResponse['success']['StorageTempHum']['listPeriod'] += 1;
								if(!in_array($perRow['DATE_PERIOD'], $jsonResponse['data']['StorageTempHum']['listPeriod']['availableDate'])) {
									$jsonResponse['data']['StorageTempHum']['listPeriod']['availableDate'][] = array(
										'name' => $perRow['DATE_PERIOD'],
										'code' => hash('md5', sprintf('%s%s', $perRow['DATE_PERIOD'], $APP_CORE['app_key_encrypt']))
									);
								}
							}
							$jsonResponse['data']['StorageTempHum']['listPeriod']['totalPeriod'] = count($fetchAvailablePeriod);
						}

						// Fetch list of available Cold Storage (CS)
						$queryString = sprintf('SELECT DISTINCT(MESIN) AS CS_NAME FROM %s GROUP BY MESIN;', 'mtp_report_temperature');
						$fetchAvailableCS = db_runQuery(array(
							'config_array' => $customerDBMS,
							'database_index' => 0,
							'query' => $queryString,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchAvailableCS) && $fetchAvailableCS !== 'ZERO_DATA' && count($fetchAvailableCS) >= 1) {
							$jsonResponse['success']['StorageTempHum']['listCS'] = 0;
							$jsonResponse['datetime']['StorageTempHum'] = date('Y-m-d H:i:s');
							$jsonResponse['took']['StorageTempHum'] = (floor(microtime(true)*1000))-$startTime.'ms';
							foreach($fetchAvailableCS as $perRow) {
								$jsonResponse['success']['StorageTempHum']['listCS'] += 1;
								$jsonResponse['data']['StorageTempHum']['listCS'][] = array(
									'name' => $perRow['CS_NAME'],
									'code' => hash('md5', sprintf('%s%s', $perRow['CS_NAME'], $APP_CORE['app_key_encrypt']))
								);
							}
						}

						// Fetch datasets
						$queryInput = array();
						if(!isEmptyVar($dataRequest['StorageTempHum']['period'])) {
							$isFound = searchArrAssoc($jsonResponse['data']['StorageTempHum']['listPeriod']['availableDate'], 'code', $dataRequest['StorageTempHum']['period']) ?? null;
							if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
								if(strtolower(trim($isFound[0]['name'])) === 'today') {
									$queryInput[] = date('Y-m-d'); // Current today
									// $queryInput[] = date('Y-m-d', strtotime('-1 days')); // Get yesterday
								} else {
									$queryInput[] = $isFound[0]['name']; // YYYY-MM-DD
									$jsonResponse['data']['StorageTempHum']['datePicked'] = date('F d, Y', strtotime($isFound[0]['name']));
								}
							} else {
								$queryInput[] = date('Y-m-d'); // Current today
								// $queryInput[] = date('Y-m-d', strtotime('-1 days')); // Get yesterday
							}
						} else {
							$queryInput[] = date('Y-m-d'); // Current today
							// $queryInput[] = date('Y-m-d', strtotime('-1 days')); // Get yesterday
						}
						$queryStringAdd = '';
						if(!isEmptyVar($dataRequest['StorageTempHum']['cs'])) {
							if($dataRequest['StorageTempHum']['cs'] !== $jsonResponse['data']['StorageTempHum']['listCS'][0]['code']) {
								$isFound = searchArrAssoc($jsonResponse['data']['StorageTempHum']['listCS'], 'code', $dataRequest['StorageTempHum']['cs']) ?? null;
								if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
									$queryStringAdd = 'AND MESIN = ?';
									$queryInput[] = $isFound[0]['name'];
								}
								// else {
								// 	$queryInput[] = $jsonResponse['data']['coldStorageTemperature']['listCS'][0]['name'];
								// }
							}
						}
						$queryString = sprintf('SELECT MESIN AS CS_NAME, TGL AS DATE_PERIOD, SUHU AS TEMPERATURE, HUMIDITY FROM %s WHERE DATE(TGL) = ? %s ORDER BY MESIN, TGL;', 'mtp_report_temperature', $queryStringAdd);
						$queryParam = str_repeat('s', count($queryInput));

						$fetchChartData = db_runQuery(array(
							'config_array' => $customerDBMS,
							'database_index' => 0,
							'input' => $queryInput,
							'query' => $queryString,
							'param' => $queryParam,
							'getData' => true,
							'getAllRow' => true
						));
						// pre_dump($fetchChartData);
						// exit(0);

						if(!isEmptyVar($fetchChartData) && $fetchChartData !== 'ZERO_DATA' && count($fetchChartData) >= 1) {
							$presentData = array(
								'temperature' => array(),
								'humidity' => array()
							);

							if(!isEmptyVar($dataRequest['StorageTempHum']['view'])) {
								$isFound = searchArrAssoc($jsonResponse['data']['StorageTempHum']['listView'], 'code', $dataRequest['StorageTempHum']['view']) ?? null;
								if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
									$isFound = strtolower(trim($isFound[0]['name']));
								} else {
									$isFound = 'compact';
								}

								switch($isFound) {
									default:
									case 'compact':
										foreach($fetchChartData as $perRow) {
											if(!array_key_exists($perRow['CS_NAME'], $presentData['temperature']) && !array_key_exists($perRow['CS_NAME'], $presentData['temperature'])) {
												$presentData['temperature'][$perRow['CS_NAME']] = array(
													'labels' => array(),
													'datasets' => array(),
												);
												$presentData['humidity'][$perRow['CS_NAME']] = array(
													'labels' => array(),
													'datasets' => array(),
												);
												for ($i = 0; $i < 24; $i++) {
													$presentData['temperature'][$perRow['CS_NAME']]['labels'][$i] = sprintf('%s:00', str_pad($i, 2, '0', STR_PAD_LEFT));
													$presentData['temperature'][$perRow['CS_NAME']]['datasets'][$i] = array(
														'total' => 0,
														'count' => 0,
													);
													$presentData['humidity'][$perRow['CS_NAME']]['labels'][$i] = sprintf('%s:00', str_pad($i, 2, '0', STR_PAD_LEFT));
													$presentData['humidity'][$perRow['CS_NAME']]['datasets'][$i] = array(
														'total' => 0,
														'count' => 0,
													);
												}
											}
											$hourIn24 = (int) trim(date('H', strtotime($perRow['DATE_PERIOD'])));
											$presentData['temperature'][$perRow['CS_NAME']]['datasets'][$hourIn24]['total'] += (!isEmptyVar($perRow['TEMPERATURE'])) ? (float) $perRow['TEMPERATURE'] : 0;
											$presentData['temperature'][$perRow['CS_NAME']]['datasets'][$hourIn24]['count'] += 1;
											$presentData['humidity'][$perRow['CS_NAME']]['datasets'][$hourIn24]['total'] += (!isEmptyVar($perRow['HUMIDITY'])) ? (float) $perRow['HUMIDITY'] : 0;
											$presentData['humidity'][$perRow['CS_NAME']]['datasets'][$hourIn24]['count'] += 1;
										}
										if(count($presentData['temperature']) >= 1) {
											foreach($presentData['temperature'] as $csName => $perData) {
												if(is_array($perData) && array_key_exists('datasets', $perData)) {
													foreach($perData['datasets'] as $idx => $val) {
														if($val['total'] >= 0 && $val['count'] >= 1) {
															$presentData['temperature'][$csName]['datasets'][$idx] = $val['total'] / $val['count'] ;
														} else {
															$presentData['temperature'][$csName]['datasets'][$idx] = 0;
														}
													}
												}
											}
										}
										if(count($presentData['humidity']) >= 1) {
											foreach($presentData['humidity'] as $csName => $perData) {
												if(is_array($perData) && array_key_exists('datasets', $perData)) {
													foreach($perData['datasets'] as $idx => $val) {
														if($val['total'] >= 0 && $val['count'] >= 1) {
															$presentData['humidity'][$csName]['datasets'][$idx] = $val['total'] / $val['count'] ;
														} else {
															$presentData['humidity'][$csName]['datasets'][$idx] = 0;
														}
													}
												}
											}
										}
										break;
									case 'raw':
										foreach($fetchChartData as $perRow) {
											if(!array_key_exists($perRow['CS_NAME'], $presentData['temperature']) && !array_key_exists($perRow['CS_NAME'], $presentData['humidity'])) {
												$presentData['temperature'][$perRow['CS_NAME']] = array(
													'labels' => array(),
													'datasets' => array(),
												);
												$presentData['humidity'][$perRow['CS_NAME']] = array(
													'labels' => array(),
													'datasets' => array(),
												);
											}
											$labelHourMinute = date('H:i', strtotime($perRow['DATE_PERIOD']));
											if(!in_array($labelHourMinute, $presentData['temperature'][$perRow['CS_NAME']]['labels'])) {
												$presentData['temperature'][$perRow['CS_NAME']]['labels'][] = $labelHourMinute;
												$presentData['temperature'][$perRow['CS_NAME']]['datasets'][] = $perRow['TEMPERATURE'];
											}
											if(!in_array($labelHourMinute, $presentData['humidity'][$perRow['CS_NAME']]['labels'])) {
												$presentData['humidity'][$perRow['CS_NAME']]['labels'][] = $labelHourMinute;
												$presentData['humidity'][$perRow['CS_NAME']]['datasets'][] = $perRow['HUMIDITY'];
											}
										}
										break;
								}

								if(count($presentData['temperature']) >= 1) {
									$jsonResponse['data']['StorageTempHum']['charts']['temperature'] = array();
									foreach($presentData['temperature'] as $csName => $perData) {
										$stableRate = array_filter($perData['datasets'], function($val) { return $val > 0; });
										$jsonResponse['success']['StorageTempHum']['charts']['temperature'] += 1;
										$jsonResponse['data']['StorageTempHum']['charts']['temperature'][] = array(
											'name' => $csName,
											'label' => $perData['labels'],
											'datasets' => $perData['datasets'],
											'stableRate' => (float) sprintf('%.02f', array_sum($stableRate) / count($stableRate)),
										);
									}
									$jsonResponse['took']['StorageTempHum'] = ((floor(microtime(true)*1000))-$startTime).'ms';
								}
								if(count($presentData['humidity']) >= 1) {
									$jsonResponse['data']['StorageTempHum']['charts']['humidity'] = array();
									foreach($presentData['humidity'] as $csName => $perData) {
										$stableRate = array_filter($perData['datasets'], function($val) { return $val > 0; });
										$jsonResponse['success']['StorageTempHum']['charts']['humidity'] += 1;
										$jsonResponse['data']['StorageTempHum']['charts']['humidity'][] = array(
											'name' => $csName,
											'label' => $perData['labels'],
											'datasets' => $perData['datasets'],
											'stableRate' => (float) sprintf('%.02f', array_sum($stableRate) / count($stableRate)),
										);
									}
									$jsonResponse['took']['StorageTempHum'] = (floor(microtime(true)*1000))-$startTime.'ms';
								}
							}
						}
					}
					// Serve for: ChamberUsage
					if(boolval($serveData['ChamberUsage']) === true) {	
						// Fetch list of available Customer
						$queryString = sprintf('SELECT DISTINCT(CUSTOMER) AS STOREKEY FROM %s;', 'mtp_master_dedicated_storage');
						$fetchAvailableCustomer = db_runQuery(array(
							'config_array' => $customerDBMS,
							'database_index' => 0,
							'query' => $queryString,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchAvailableCustomer) && $fetchAvailableCustomer !== 'ZERO_DATA' && count($fetchAvailableCustomer) >= 1) {
							$jsonResponse['success']['ChamberUsage']['listCustomer'] = 0;
							foreach($fetchAvailableCustomer as $perRow) {
								$getCustomerProfil = db_runQuery(array(
									'config_array' => $configMysql,
									'database_index' => 0, // db.mtp_crm
									'input' => array($perRow['STOREKEY']),
									'query' => sprintf('SELECT NAMA_PERUSAHAAN, META_LOGO, KODE_DC, KODE_CUSTOMER FROM %s WHERE KODE_STOREKEY = ?;', $APP_CORE['tb_prefix'].'user_account'),
									'param' => 's',
									'getData' => true,
									'getAllRow' => false,
									'callback' => function($response) use ($customerDBMS, $perRow, $appConfig) {
										$result = $response['data'];
										$output = array();
										if(!isEmptyVar($result) && $result !== 'ZERO_DATA') {
											$output['name'] = $result['NAMA_PERUSAHAAN'];
											$output['logo'] = sprintf('%s/app/includes/view-document.inc%s?uid=%s', getURI(2), (EXT_PHP) ? '.php' : '', $result['META_LOGO']);
											$output['dc'] = $result['KODE_DC'];
										} else {
											$output = db_runQuery(array(
												'config_array' => $customerDBMS,
												'database_index' => 0,
												'input' => array($perRow['STOREKEY']),
												'query' => sprintf('SELECT KODE_DC, NAMA FROM %s WHERE STOREKEY = ?;', 'mtp_master_dc'),
												'param' => 's',
												'getData' => true,
												'getAllRow' => false,
												'callback' => function($response2) use ($perRow, $appConfig) {
													$result2 = $response2['data'];
													$output2 = array(
														'name' => $perRow['STOREKEY'],
														'logo' => path2url(sprintf('%s/image/illustrations/avatar-%s.png', DIR_ASSET, $appConfig['CORE']['app_build_version'])),
														'dc' => 'MTP001'
													);
													if(!isEmptyVar($result2) && $result2 !== 'ZERO_DATA') {
														$output['name'] = $result2['NAMA'];
														$output['dc'] = $result2['KODE_DC'];
													}
													return $output2;
												}
											));
										}
										return $output;
									}
								));

								$jsonResponse['success']['ChamberUsage']['listCustomer'] += 1;
								$jsonResponse['data']['ChamberUsage']['listCustomer'][] = array(
									'name' => $getCustomerProfil['name'],
									'code' => hash('md5', sprintf('%s%s', $perRow['STOREKEY'], $APP_CORE['app_key_encrypt'])),
									'codeRAW' => $perRow['STOREKEY'],
									'dc' => $getCustomerProfil['dc'],
									'logo' => $getCustomerProfil['logo']
								);
								$jsonResponse['took']['ChamberUsage'] = (floor(microtime(true)*1000))-$startTime.'ms';
							}
						}

						// Summary
						if(!isEmptyVar($dataRequest['ChamberUsage']['customer'])) {
							$isFound = searchArrAssoc($jsonResponse['data']['ChamberUsage']['listCustomer'], 'code', $dataRequest['ChamberUsage']['customer']) ?? null;
							if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
								$jsonResponse['data']['ChamberUsage']['summary']['customer_name'] = $isFound[0]['name']; // Customer Name
								$jsonResponse['data']['ChamberUsage']['summary']['customer_logo'] = $isFound[0]['logo']; // Customer Logo
							} else {
								$jsonResponse['data']['ChamberUsage']['summary']['customer_name'] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['name']; // Customer Name
								$jsonResponse['data']['ChamberUsage']['summary']['customer_logo'] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['logo']; // Customer Logo
							}
						} else {
							$jsonResponse['data']['ChamberUsage']['summary']['customer_name'] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['name']; // Customer Name
							$jsonResponse['data']['ChamberUsage']['summary']['customer_logo'] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['logo']; // Customer Logo
						}

						// Chamber last update storage
						$queryInput = array();
						if(!isEmptyVar($dataRequest['ChamberUsage']['customer'])) {
							$isFound = searchArrAssoc($jsonResponse['data']['ChamberUsage']['listCustomer'], 'code', $dataRequest['ChamberUsage']['customer']) ?? null;
							if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
								$queryInput[] = $isFound[0]['codeRAW']; // Customer STOREKEY
							} else {
								$queryInput[] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['codeRAW']; // Get first customer
							}
						} else {
							$queryInput[] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['codeRAW']; // Get first customer
						}
						$queryString = 'SELECT MIN(tgl_upload) AS LAST_UPLOAD FROM mtp_inventory_balance_infor_rep WHERE own = ?;';
						$queryParam = str_repeat('s', count($queryInput));
						$fetchLastUpdate = db_runQuery(array(
							'config_array' => $customerDBMS,
							'database_index' => 0,
							'input' => $queryInput,
							'query' => $queryString,
							'param' => $queryParam,
							'getData' => true,
							'getAllRow' => false
						));
						if(!isEmptyVar($fetchLastUpdate) && $fetchLastUpdate !== 'ZERO_DATA') {
							if(array_key_exists('LAST_UPLOAD', $fetchLastUpdate) && !isEmptyVar($fetchLastUpdate['LAST_UPLOAD'])) {
								$jsonResponse['data']['ChamberUsage']['charts']['lastUpdate'] = date('l, d M Y', strtotime($fetchLastUpdate['LAST_UPLOAD'])) . ' on ' . date('H:i:s', strtotime($fetchLastUpdate['LAST_UPLOAD']));
							}
						}

						// Chamber current usage
						$queryInput = array(
							// $isExistCustomer['STOREKEY'],
							// $isExist['KODE_DC']
						);
						if(!isEmptyVar($dataRequest['ChamberUsage']['customer'])) {
							$isFound = searchArrAssoc($jsonResponse['data']['ChamberUsage']['listCustomer'], 'code', $dataRequest['ChamberUsage']['customer']) ?? null;
							if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
								$queryInput[] = $isFound[0]['codeRAW']; // Customer STOREKEY
								$queryInput[] = $isFound[0]['dc']; // Customer STOREKEY
							} else {
								$queryInput[] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['codeRAW']; // Get first customer
								$queryInput[] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['dc']; // Get first customer
							}
						} else {
							$queryInput[] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['codeRAW']; // Get first customer
							$queryInput[] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['dc']; // Get first customer
						}
						$queryString = "
							SELECT '" . $queryInput[0] . "' CUSTOMER, X.KET CHAMBER, IFNULL(Y.total_storage,0) TOTAL_STORAGE
							FROM
							(
								SELECT DISTINCT ket FROM mtp_master_storage
							) X
							LEFT JOIN
							(
								SELECT z.customer, COUNT(*) total_storage, z.ket CHAMBER
								FROM
								(
									SELECT a.own CUSTOMER, a.location, b.KET
									FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
									WHERE DATE(a.tgl_cut_off) >= DATE(NOW() - INTERVAL 18 DAY) AND a.own = ? AND b.kode_dc = ? AND a.location = b.lokasi_storage
								) Z
								GROUP BY z.customer, z.ket
							) Y
							ON X.KET = Y.CHAMBER
							ORDER BY X.KET
						";
						$queryParam = str_repeat('s', count($queryInput));
						$fetchChamberUsage = db_runQuery(array(
							'config_array' => $customerDBMS,
							'database_index' => 0,
							'input' => $queryInput,
							'query' => $queryString,
							'param' => $queryParam,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchChamberUsage) && $fetchChamberUsage !== 'ZERO_DATA' && count($fetchChamberUsage) >= 1) {
							$jsonResponse['success']['ChamberUsage'] = true;
							$jsonResponse['message']['ChamberUsage'] = sprintf('Data found (%s) entries!', count($fetchChamberUsage));
							$jsonResponse['datetime']['ChamberUsage'] = date('Y-m-d H:i:s');

							$totalUsage = 0;
							foreach($fetchChamberUsage as $row) {
								$chamberName = strtoupper($row['CHAMBER']);
								if(wordExist($chamberName, 'CHAMBER')) {
									$chamberName = str_replace('CHAMBER', 'CHAMBER ', $chamberName);
								} elseif(wordExist($chamberName, 'CHILLER')) {
									$chamberName = str_replace('CHILLER', 'CHILLER ', $chamberName);
								} elseif(wordExist($chamberName, 'CH')) {
									$chamberName = str_replace('CH', 'CH ', $chamberName);
								} else {
									$chamberName = null;
								}
								if($chamberName !== null) {
									$jsonResponse['data']['ChamberUsage']['labels'][] = $chamberName;
									$jsonResponse['data']['ChamberUsage']['charts']['datasets'][strtolower(str_replace(' ', '_', $chamberName))] = array(
										'dedicated' => 0,
										'dedicated_intersect' => 0,
										'available' => 0,
										'usage' => (int) $row['TOTAL_STORAGE'],
										'over' => 0,
										'over_intersect' => 0,
										'temperature' => array(
											'min' => 0,
											'max' => 0,
										)
									);
								}
								$totalUsage += $row['TOTAL_STORAGE'];
							}

							$jsonResponse['data']['ChamberUsage']['charts']['totalUsage'] = $totalUsage;
							$jsonResponse['data']['ChamberUsage']['summary']['total_usage'] = $totalUsage;
							$jsonResponse['took']['ChamberUsage'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode']['ChamberUsage'] = 0;
						}

						// Chamber customer max storage
						$queryInput = array(
							// $isExistCustomer['STOREKEY'],
						);
						if(!isEmptyVar($dataRequest['ChamberUsage']['customer'])) {
							$isFound = searchArrAssoc($jsonResponse['data']['ChamberUsage']['listCustomer'], 'code', $dataRequest['ChamberUsage']['customer']) ?? null;
							if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
								$queryInput[] = $isFound[0]['codeRAW']; // Customer STOREKEY
							} else {
								$queryInput[] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['codeRAW']; // Get first customer
							}
						} else {
							$queryInput[] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['codeRAW']; // Get first customer
						}
						$queryString = sprintf('SELECT CHAMBER, JML_STORAGE AS MAX_STORAGE FROM %s WHERE CUSTOMER = ?;', 'mtp_master_dedicated_storage');
						$queryParam = str_repeat('s', count($queryInput));
						$fetchMaxStorage = db_runQuery(array(
							'config_array' => $customerDBMS,
							'database_index' => 0,
							'input' => $queryInput,
							'query' => $queryString,
							'param' => $queryParam,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchMaxStorage) && $fetchMaxStorage !== 'ZERO_DATA' && count($fetchMaxStorage) >= 1) {
							$higherValue = 0;
							foreach($fetchMaxStorage as $row) {
								$chamberName = strtoupper($row['CHAMBER']);
								if(wordExist($chamberName, 'CHAMBER')) {
									$chamberName = str_replace('CHAMBER', 'CHAMBER ', $chamberName);
								} elseif(wordExist($chamberName, 'CHILLER')) {
									$chamberName = str_replace('CHILLER', 'CHILLER ', $chamberName);
								} elseif(wordExist($chamberName, 'CH')) {
									$chamberName = str_replace('CH', 'CH ', $chamberName);
								} else {
									$chamberName = null;
								}
								if($chamberName !== null) {
									// Asphira Andreas <arechta911@gmail.com>
									// Dev Note: Special conditions, because the data is not fixed
									$isSingleName = false;
									if(count(explode(' ', trim($chamberName))) < 2) {
										$isSingleName = true;
										$chamberName = trim($chamberName);
									}
									if(!$isSingleName) {
										$chamberName = str_replace(' ', '_', $chamberName);
									}
									$chamberName = trim(strtolower($chamberName));
									$dedicatedStorage = (int) $row['MAX_STORAGE'];
									if(array_key_exists($chamberName, $jsonResponse['data']['ChamberUsage']['charts']['datasets'])) {
										$chamberSelected = $jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName];
										if($dedicatedStorage !== 0) {
											if($dedicatedStorage >= $chamberSelected['usage']) {
												$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['available'] = $dedicatedStorage - (int) $chamberSelected['usage'];
												$jsonResponse['data']['ChamberUsage']['summary']['total_available'] += $dedicatedStorage - (int) $chamberSelected['usage'];
											}
											if($dedicatedStorage < $chamberSelected['usage']) {
												$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['available'] = 0;
												$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['over'] = (int) $chamberSelected['usage'] - $dedicatedStorage;
												$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['over_intersect'] = 0;
												// $jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['usage'] = $dedicatedStorage;
											}
											$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated'] = $dedicatedStorage;
											$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated_intersect'] = 0;
										} else {
											$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName] = array(
												'dedicated' => 0,
												'dedicated_intersect' => 0,
												'available' => 0,
												'usage' => 0,
												'over' => 0,
												'over_intersect' => 0,
												'temperature' => array(
													'min' => 0,
													'max' => 0
												)
											);
										}
									} else {
										// Asphira Andreas <arechta911@gmail.com>
										// Dev Note: Special conditions, because the data is not fixed
										if($isSingleName && strtoupper($chamberName) === 'CHILLER') {
											$chamberName = 'ch_01';
											if(array_key_exists($chamberName, $jsonResponse['data']['ChamberUsage']['charts']['datasets'])) {
												$chamberSelected = $jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName];
												if($dedicatedStorage !== 0) {
													if($dedicatedStorage >= $chamberSelected['usage']) {
														$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['available'] = $dedicatedStorage - (int) $chamberSelected['usage'];
													}
													if($dedicatedStorage < $chamberSelected['usage']) {
														$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['available'] = 0;
														$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['over'] = (int) $chamberSelected['usage'] - $dedicatedStorage;
														$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['over_intersect'] = 0;
														// $jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['usage'] = $dedicatedStorage;
													}
													$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated'] = $dedicatedStorage;
													$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated_intersect'] = 0;
												} else {
													$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName] = array(
														'dedicated' => 0,
														'dedicated_intersect' => 0,
														'available' => 0,
														'usage' => 0,
														'over' => 0,
														'over_intersect' => 0,
														'temperature' => array(
															'min' => 0,
															'max' => 0,
														)
													);
												}
											}
										}
									}
								}
							}
							$jsonResponse['message']['ChamberUsage'] = sprintf('Data found (%s) entries!', count($fetchChamberUsage));
							$jsonResponse['datetime']['ChamberUsage'] = date('Y-m-d H:i:s');
							$jsonResponse['data']['ChamberUsage']['charts']['maxUsage'] = $higherValue;
							$jsonResponse['took']['ChamberUsage'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode']['ChamberUsage'] = 0;
						}

						// Chamber dedicated storage
						/*
						$queryString = sprintf('SELECT ket AS CHAMBER, COUNT(*) AS TOTAL_STORAGE FROM %s GROUP BY ket;', 'mtp_master_storage');
						$fetchDedicatedStorage = db_runQuery(array(
							'config_array' => $configMysql,
							'database_index' => 2, // db.mtp
							'query' => $queryString,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchDedicatedStorage) && $fetchDedicatedStorage !== 'ZERO_DATA' && count($fetchDedicatedStorage) >= 1) {
							foreach($fetchDedicatedStorage as $row) {
								$chamberName = strtoupper($row['CHAMBER']);
								if(wordExist($chamberName, 'CHAMBER')) {
									$chamberName = str_replace('CHAMBER', 'CHAMBER ', $chamberName);
								} elseif(wordExist($chamberName, 'CHILLER')) {
									$chamberName = str_replace('CHILLER', 'CHILLER ', $chamberName);
								} elseif(wordExist($chamberName, 'CH')) {
									$chamberName = str_replace('CH', 'CH ', $chamberName);
								} else {
									$chamberName = null;
								}
								if($chamberName !== null) {
									// Asphira Andreas <arechta911@gmail.com>
									// Dev Note: Special conditions, because the data is not fixed
									$isSingleName = false;
									if(count(explode(' ', trim($chamberName))) < 2) {
										$isSingleName = true;
										$chamberName = trim($chamberName);
									}
									if(!$isSingleName) {
										$chamberName = str_replace(' ', '_', $chamberName);
									}
									$chamberName = trim(strtolower($chamberName));
									$dedicatedStorage = (int) $row['TOTAL_STORAGE'];
									if(array_key_exists($chamberName, $jsonResponse['data']['ChamberUsage']['charts']['datasets'])) {
										if($jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated'] === 0) {
											$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated'] = $dedicatedStorage;
											$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated_intersect'] = $dedicatedStorage;
										}
									}
								}
							}
						}
						*/

						// Chamber temperature
						$queryString = "SELECT * FROM mtp_mesin_temperature GROUP BY KET ASC;";
						$fetchChamberTemperature = db_runQuery(array(
							'config_array' => $customerDBMS,
							'database_index' => 0,
							'query' => $queryString,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchChamberTemperature) && $fetchChamberTemperature !== 'ZERO_DATA') {
							foreach($fetchChamberTemperature as $row) {
								$chamberName = strtoupper($row['KET']);
								if(wordExist($chamberName, 'CHAMBER')) {
									$chamberName = str_replace('CHAMBER', 'CHAMBER ', $chamberName);
								} elseif(wordExist($chamberName, 'CHILLER')) {
									$chamberName = str_replace('CHILLER', 'CHILLER ', $chamberName);
								} elseif(wordExist($chamberName, 'CH')) {
									$chamberName = str_replace('CH', 'CH ', $chamberName);
								} else {
									$chamberName = null;
								}
								if($chamberName !== null) {
									// Asphira Andreas <arechta911@gmail.com>
									// Dev Note: Special conditions, because the data is not fixed
									$isSingleName = false;
									if(count(explode(' ', trim($chamberName))) < 2) {
										$isSingleName = true;
										$chamberName = trim($chamberName);
									}
									if(!$isSingleName) {
										$chamberName = str_replace(' ', '_', $chamberName);
									}
									$chamberName = trim(strtolower($chamberName));
									if(array_key_exists($chamberName, $jsonResponse['data']['ChamberUsage']['charts']['datasets'])) {
										$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['temperature']['min'] = $row['TEMP_MIN'];
										$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['temperature']['max'] = $row['TEMP_MAX'];
									}
								}
							}
						}
					}
					// Serve for: AccumulativeIssues
					if(boolval($serveData['AccumulativeIssues']) === true) {
						$queryInput = array(
							date('Y-m')
						);
						$queryString = "
							SELECT FLAG, DATE(DATE_CREATED) AS DATE_CREATED, DATE(FLAG_DATE) AS FLAG_DATE, COUNT(*) AS TOTAL
							FROM " . $APP_CORE['tb_prefix'] . 'thread_issues' . " WHERE DATE_FORMAT(DATE_CREATED, '%Y-%m') = ?
							GROUP BY FLAG, DATE(DATE_CREATED), DATE(FLAG_DATE)
						";
						$queryParam = str_repeat('s', count($queryInput));
	
						$fetchAccumulativeIssues = db_runQuery(array(
							'config_array' => $configMysql,
							'database_index' => 0,
							'input' => $queryInput,
							'query' => $queryString,
							'param' => $queryParam,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchAccumulativeIssues) && $fetchAccumulativeIssues !== 'ZERO_DATA' && count($fetchAccumulativeIssues) >= 1) {
							$jsonResponse['success']['AccumulativeIssues'] = true;
							$jsonResponse['datetime']['AccumulativeIssues'] = date('Y-m-d H:i:s');

							$totalDays = date('t', strtotime($queryInput[0].'01'));
							foreach($fetchAccumulativeIssues as $perRow) {
								if(array_key_exists('FLAG', $perRow) && array_key_exists('DATE_CREATED', $perRow) && array_key_exists('TOTAL', $perRow)) {
									$dateIndex = explode('-', $perRow['DATE_CREATED']);
									switch((int) $perRow['FLAG']) {
										case 0: $dateIndex = explode('-', $perRow['DATE_CREATED']); break;
										case 1: $dateIndex = explode('-', $perRow['FLAG_DATE']); break;
										default: $dateIndex = explode('-', $perRow['DATE_CREATED']); break;
									}
									if(count($dateIndex) >= 3) {  // YYYY-MM-DD
										$dateIndex = (int) $dateIndex[2] - 1; // Get only DD, last items
									}
									switch((int) $perRow['FLAG']) {
										case 0: $jsonResponse['data']['AccumulativeIssues']['charts'][$dateIndex]['open'] += (int) $perRow['TOTAL']; break;
										case 1: $jsonResponse['data']['AccumulativeIssues']['charts'][$dateIndex]['closed'] += (int) $perRow['TOTAL']; break;
									}
									switch((int) $perRow['FLAG']) {
										case 0: $jsonResponse['data']['AccumulativeIssues']['total']['open'] += (int) $perRow['TOTAL']; break;
										case 1: $jsonResponse['data']['AccumulativeIssues']['total']['closed'] += (int) $perRow['TOTAL']; break;
									}
								}
							}
	
							$jsonResponse['message']['AccumulativeIssues'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['AccumulativeIssues']));
							$jsonResponse['took']['AccumulativeIssues'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode']['AccumulativeIssues'] = 0;
						}

						$listCustomer = db_runQuery(array(
							'config_array' => $configMysql,
							'database_index' => 0,
							'input' => array(0, 'c'),
							'query' => sprintf('SELECT NIK FROM %s WHERE LEVEL = ? OR LEVEL_CODE = ?;', $APP_CORE['tb_prefix'].'user_privileges'),
							'param' => 'ss',
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($listCustomer) && $listCustomer !== 'ZERO_DATA' && count($listCustomer) >= 1) {
							// Serve data
							$lengthLoop = count($listCustomer);
							$jsonResponse['data']['AccumulativeIssues']['averageSolved'] = '-';
							$averageEstimatedSolved = array();
							foreach($listCustomer as $idx => $perCustomer) {
								$fetchCustomer = db_runQuery(array(
									'config_array' => $configMysql,
									'database_index' => 0,
									'input' => array($perCustomer['NIK'], 2),
									'query' => sprintf('SELECT * FROM %s WHERE NIK = ? AND APPROVAL_FLAG >= ?;', $APP_CORE['tb_prefix'].'user_account'),
									'param' => 'ss',
									'getData' => true,
									'getAllRow' => false
								));
								if(!isEmptyVar($fetchCustomer) && $fetchCustomer !== 'ZERO_DATA') {
									$datasets_averageEstimatedSolved = db_runQuery(array(
										'config_array' => $configMysql,
										'database_index' => 0,
										'input' => array($fetchCustomer['NIK'], 1),
										'query' => "SELECT DATE_CREATED as 'start', FLAG_DATE as 'end' FROM ".$APP_CORE['tb_prefix']."thread_issues WHERE OWNER = ? AND FLAG = ?;",
										'param' => 'ss',
										'getData' => true,
										'getAllRow' => true,
										'callback' => function($response) {
											$output = $response['data'];
											$result = array();
											if(!isEmptyVar($output) && $output !== 'ZERO_DATA' && count($output) >= 1) {
												foreach($output as $perRow) {
													if(array_key_exists('start', $perRow) && array_key_exists('end', $perRow)) {
														if(!isEmptyVar($perRow['start']) && !isEmptyVar($perRow['end'])) {
															if($perRow['start'] === date('Y-m-d H:i:s', strtotime($perRow['start'])) && $perRow['end'] === date('Y-m-d H:i:s', strtotime($perRow['end']))) {
																// Current month
																if(date('Y-m-01', strtotime($perRow['start'])) === date('Y-m-01', strtotime('now'))) {
																	$result[] = array(
																		'start' => $perRow['start'],
																		'end' => $perRow['end'],
																	);
																}
															}
														}
													}
												}
											}
											return $result;
										}
									));
									if(is_array($datasets_averageEstimatedSolved)) {
										if(count($datasets_averageEstimatedSolved) >= 1) {
											$averageEstimatedSolved = array_merge($averageEstimatedSolved, $datasets_averageEstimatedSolved);
										}
									}
								}
								$lengthLoop -= 1;
							}
							// Calculate data Average estimated Solved
							if(count($averageEstimatedSolved) >= 1) {
								$_tmpArray = array();
								foreach($averageEstimatedSolved as $perItem) {
									$_tmpArray[] = round(strtotime($perItem['end']) - strtotime($perItem['start']));
								}
								$jsonResponse['data']['AccumulativeIssues']['averageSolved'] = rf_timeElapsedString(date('Y-m-d H:i:s', strtotime(sprintf('-%s seconds', round(array_sum($_tmpArray) / count($_tmpArray))))), 2);
							}
						}
					}

					/*
					 * Finishing response
					 */
					// Serve for: ThroughputIO
					if(boolval($serveData['ThroughputIO']) === true) {
						// Result in JSON
						if(count($jsonResponse['data']['ThroughputIO']['charts']) >= 1) {
							$jsonResponse['success']['ThroughputIO']['charts'] = true;
							$jsonResponse['message']['ThroughputIO']['charts'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['ThroughputIO']['charts']));
							$jsonResponse['errcode']['ThroughputIO']['charts'] = 0;
						}
						if(count($jsonResponse['data']['ThroughputIO']['tables']) >= 1) {
							$jsonResponse['success']['ThroughputIO']['tables'] = true;
							$jsonResponse['message']['ThroughputIO']['tables'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['ThroughputIO']['tables']));
							$jsonResponse['errcode']['ThroughputIO']['tables'] = 0;

							// Sort rows by High value
							usort($jsonResponse['data']['ThroughputIO']['tables'], function($x, $y) {
								return $x['total'] < $y['total'];
							});
						}
					}
					// Serve for: StorageTempHum
					if(boolval($serveData['StorageTempHum']) === true) {
						// Result fix JSON
						if(intval($jsonResponse['success']['StorageTempHum']['listPeriod']) >= 1 && count($jsonResponse['data']['StorageTempHum']['listPeriod']['availableDate']) >= 1) {
							$jsonResponse['success']['StorageTempHum']['listPeriod'] = true;
							$jsonResponse['message']['StorageTempHum']['listPeriod'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['StorageTempHum']['listPeriod']['availableDate']));
							$jsonResponse['errcode']['StorageTempHum']['listPeriod'] = 0;
						}
						if(intval($jsonResponse['success']['StorageTempHum']['listCS']) >= 1 && count($jsonResponse['data']['StorageTempHum']['listCS']) >= 1) {
							$jsonResponse['success']['StorageTempHum']['listCS'] = true;
							$jsonResponse['message']['StorageTempHum']['listCS'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['StorageTempHum']['listCS']));
							$jsonResponse['errcode']['StorageTempHum']['listCS'] = 0;
						}
						if(intval($jsonResponse['success']['StorageTempHum']['charts']['temperature']) >= 1 && count($jsonResponse['data']['StorageTempHum']['charts']['temperature']) >= 1) {
							$jsonResponse['success']['StorageTempHum']['charts']['temperature'] = true;
							$jsonResponse['message']['StorageTempHum']['charts']['temperature'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['StorageTempHum']['charts']['temperature']));
							$jsonResponse['errcode']['StorageTempHum']['charts']['temperature'] = 0;
						}
						if(intval($jsonResponse['success']['StorageTempHum']['charts']['humidity']) >= 1 && count($jsonResponse['data']['StorageTempHum']['charts']['humidity']) >= 1) {
							$jsonResponse['success']['StorageTempHum']['charts']['humidity'] = true;
							$jsonResponse['message']['StorageTempHum']['charts']['humidity'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['StorageTempHum']['charts']['humidity']));
							$jsonResponse['errcode']['StorageTempHum']['charts']['humidity'] = 0;
						}
					}
					// Serve for: ChamberUsage
					if(boolval($serveData['ChamberUsage']) === true) {
						// Result in JSON
						if(count($jsonResponse['data']['ChamberUsage']['charts']['datasets']) >= 1) {
							$jsonResponse['success']['ChamberUsage'] = true;
							$jsonResponse['message']['ChamberUsage'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['ChamberUsage']['charts']['datasets']));
							$jsonResponse['errcode']['ChamberUsage'] = 0;

							// Unset key dont share with client
							foreach($jsonResponse['data']['ChamberUsage']['listCustomer'] as &$perRow) {
								unset($perRow['codeRAW']);
								unset($perRow['dc']);
								unset($perRow['logo']);
							}
						}
					}
					// Serve for: AccumulativeIssues
					if(boolval($serveData['AccumulativeIssues']) === true) {
						// Result in JSON
						if(count($jsonResponse['data']['AccumulativeIssues']) >= 1) {
							$jsonResponse['success']['AccumulativeIssues'] = true;
							$jsonResponse['message']['AccumulativeIssues'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['AccumulativeIssues']));
							$jsonResponse['errcode']['AccumulativeIssues'] = 0;
						}
					}
				} else {
					$jsonResponse['success'] = false;
					$jsonResponse['message'] = 'You are not part of the Account scope!';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 3;
				}
			} else {
				$jsonResponse['success'] = false;
				$jsonResponse['message'] = 'You\'re not logged-in!';
				$jsonResponse['datetime'] = date('Y-m-d H:i:s');
				$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
				$jsonResponse['errcode'] = 2;
			}
		break;

		/* User-type: Head-office */
		case 'init-ho':
			/*
			 * Stage: 1
			 * Check if the user is online and has a NIK
			 */
			if(!isEmptyVar($userData->nik)) {
				/*
				 * Stage: 2 A
				 * Check if the account is registered with the current NIK
				 */
				$isExist = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik),
					'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) as TOTAL, NAMA, NAMA_PERUSAHAAN, KODE_STOREKEY FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
					'param' => 's',
					'getData' => true,
					'getAllRow' => false
				));

				/*
				 * Stage: 2 B
				 * Check if the account is had access to some privileges
				 */
				$isHadAccess = false;
				$isPartPrivileges = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik, 2, 'ho'),
					'query' => sprintf('SELECT * FROM %s WHERE NIK = ? AND (LEVEL = ? OR LEVEL_CODE = ?);', $APP_CORE['tb_prefix'].'user_privileges'),
					'param' => 'sss',
					'getData' => true,
					'getAllRow' => false
				));
				if(!isEmptyVar($isPartPrivileges) && $isPartPrivileges !== 'ZERO_DATA') {
					if(array_key_exists('PERMISSIONS', $isPartPrivileges) && !isEmptyVar($isPartPrivileges['PERMISSIONS'])) {
						if(isJSON($isPartPrivileges['PERMISSIONS'])) {
							$accountPrivileges = json_decode($isPartPrivileges['PERMISSIONS'], true);
							if(is_array($accountPrivileges) && isAssoc($accountPrivileges)) {
								$restrictPage = searchArrAssoc($accountPrivileges['privileges']['pages'], 'link', 'dashboard');
								if(count($restrictPage) >= 1 && is_array($restrictPage)) {
									$restrictPage = $restrictPage[0]; // Reset array
									if(is_array($restrictPage) && $restrictPage['view'] === true) {
										$isHadAccess = true;
										$isPartPrivileges = $restrictPage;
									}
								}
							}
						}
					}
				}
				if(isset($isExist['TOTAL']) && $isExist['TOTAL'] >= 1 && $isHadAccess === true) {
					$jsonResponse['success'] = array();
					$jsonResponse['message'] = array();
					$jsonResponse['datetime'] = array();
					$jsonResponse['data'] = array();
					$jsonResponse['took'] = array();
					$jsonResponse['errcode'] = array();
					$jsonResponse['errors'] = array();

					$serveData = array(
						'FilterDCaC' => true, // alias: Filter DC and Customer
						'ThroughputIO' => true, // alias: Throughput Items Inbound Outbound
						'PartnershipCustomer' => true,
						'ActivitiesTbM' => true, // alias: Activities Truck by Monthly
						'StorageTempHum' => true, // alias: Storage Temperature & Humidity
						'ChamberUsage' => true // alias: Chamber Usage of Customer
					);

					foreach($serveData as $key => $val) {
						$ruleBlock = searchArrAssoc($isPartPrivileges['blocks'], 'id', $key);
						if(count($ruleBlock) >= 1 && is_array($ruleBlock)) {
							$ruleBlock = $ruleBlock[0]; // Reset array
							$serveData[$key] = (is_array($ruleBlock) && $ruleBlock['view'] === true) ? true : false;
						}
					}

					if(!isEmptyVar($dataRequest) && is_string($dataRequest) && strlen($dataRequest) >= 30) {
						$dataRequest = json_decode(jsonFixer($EVW->decrypt($dataRequest, $APP_CORE['app_key_encrypt']), true), true) ?? null;
						if(!isEmptyVar($dataRequest) && isAssoc($dataRequest)) {
							$defaultData = array(
								'ThroughputIO' => array(
									'uom' => 'multi'
								),
								'StorageTempHum' => array(
									'period' => date('Y-m-d'),
									'cs' => 'all',
									'view' => 'compact'
								),
								'ChamberUsage' => array(
									'customer' => ''
								)
							);
							$dataRequest = array_replace_recursive($defaultData, array_intersect_key($dataRequest, $defaultData));

							// Check is valid data
							if(!in_array($dataRequest['ThroughputIO']['uom'], array('multi', 'lpn'))) {
								$dataRequest['ThroughputIO']['uom'] = 'multi';
							}
							// if(!in_array($dataRequest['StorageTempHum']['view'], array('raw', 'compact'))) {
							// 	$dataRequest['StorageTempHum']['view'] = 'compact';
							// }
						}
					}

					/*
					 * Default values
					 */
					// Serve for: FilterDCaC 
					if(boolval($serveData['FilterDCaC']) === true) {
						// Result in JSON
						$jsonResponse['success']['FilterDCaC'] = array('dc' => false, 'customer' => false);
						$jsonResponse['message']['FilterDCaC'] = array('dc' => 'Data not found!', 'customer' => 'Data not found!');
						$jsonResponse['datetime']['FilterDCaC'] = date('Y-m-d H:i:s');
						$jsonResponse['data']['FilterDCaC'] = array('dc' => array(), 'customer' => array());
						$jsonResponse['took']['FilterDCaC'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode']['FilterDCaC'] = array('dc' => 1, 'customer' => 1);

						// Query: Get all DC list, with prefix 'MTP'
						$queryInput = array('MTP%', 1, 1);
						$queryString = 'SELECT KODE_DC, NAMA, DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME FROM mtp_master_koneksi WHERE KODE_DC IN (SELECT KODE_DC FROM mtp_master_dc WHERE KODE_DC LIKE ? AND AKTIF_DC = ?) AND FLAG = ?;';
						$queryParam = str_repeat('s', count($queryInput));

						$fetchDCList = db_runQuery(array(
							'config_array' => $configMysql,
							'database_index' => 1,
							'input' => $queryInput,
							'query' => $queryString,
							'param' => $queryParam,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchDCList) && $fetchDCList !== 'ZERO_DATA' && count($fetchDCList) >= 1) {
							$jsonResponse['success']['FilterDCaC']['dc'] = true;
							$jsonResponse['message']['FilterDCaC']['dc'] = sprintf('Data found (%s) entries!', count($fetchDCList)-1);
							$jsonResponse['datetime']['FilterDCaC'] = date('Y-m-d H:i:s');

							// Serve data
							$jsonResponse['data']['FilterDCaC']['dc'][] = array(
								'code' => 'all',
								'name' => 'All',
								'selected' => true
							);
							foreach($fetchDCList as $row) {
								$jsonResponse['data']['FilterDCaC']['dc'][] = array(
									'code' => $row['KODE_DC'],
									'name' => trim(str_replace(['DC', 'MTP'], ['',''], $row['NAMA'])),
									'selected' => false,
									'dbms' => array(
										'host' => $row['DB_HOST'],
										'port' => $row['DB_PORT'],
										'user' => $row['DB_USER'],
										'pass' => $row['DB_PASS'],
										'name' => $row['DB_NAME']
									)
								);
							}

							$jsonResponse['took']['FilterDCaC'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode']['FilterDCaC']['dc'] = 0;
						} else {
							$jsonResponse['success']['FilterDCaC']['dc'] = false;
							$jsonResponse['message']['FilterDCaC']['dc'] = 'Data not found!';
							$jsonResponse['datetime']['FilterDCaC'] = date('Y-m-d H:i:s');
							$jsonResponse['took']['FilterDCaC'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode']['FilterDCaC']['dc'] = 1;
						}

						// Query: Get all Customer list, without prefix 'MTP'
						$sessionKey = $APP_CORE['session_prefix'].'user-filter';
						$sessionRegistered = $user->takeSessionKey();
						$sessionData = array();
						if(is_array($sessionRegistered)) {
							$jsonResponse['data']['FilterDCaC']['customer'][] = array(
								'code' => 'all',
								'name' => 'All',
								'selected' => true
							);
							$filterAllCustomer = function($isCustomerSelected) use (&$sessionData, $sessionKey, &$jsonResponse, $databaseList, $configMysql) {
								$filterExistCustomer = array();
								foreach($jsonResponse['data']['FilterDCaC']['dc'] as $perDC) {
									if(array_key_exists('dbms', $perDC)) {
										$customerDBMS = array(
											'mysql_host' => $perDC['dbms']['host'],
											'mysql_username' => $perDC['dbms']['user'],
											'mysql_password' => (isEmptyVar($perDC['dbms']['pass'])) ? '' : $perDC['dbms']['pass'],
											'mysql_database' => array( $perDC['dbms']['name'] )
										);

										$queryInput = array('MTP%', 1);
										$queryString = "SELECT * FROM mtp_master_dc WHERE KODE_DC NOT LIKE ? AND AKTIF = ?;";
										$queryParam = str_repeat('s', count($queryInput));

										$fetchCustomerList = db_runQuery(array(
											'config_array' => $customerDBMS,
											'database_index' => 0,
											'input' => $queryInput,
											'query' => $queryString,
											'param' => $queryParam,
											'getData' => true,
											'getAllRow' => true
										));
										if(!isEmptyVar($fetchCustomerList) && $fetchCustomerList !== 'ZERO_DATA' && count($fetchCustomerList) >= 1) {
											foreach($fetchCustomerList as $idx => $row) {
												$codeCustomer = sprintf('%s.%s', $perDC['code'], $row['KODE_DC']);
												$jsonResponse['data']['FilterDCaC']['customer'][] = array(
													'code' => $codeCustomer,
													'parent' => ucwords(trim(str_replace(['DC', 'MTP'], ['', ''], $perDC['name']))),
													'name' => $row['NAMA'],
													'selected' => ($isCustomerSelected) ? ((in_array($codeCustomer, $sessionData['customer'])) ? true : false) : false
												);
												$idxLast = count($jsonResponse['data']['FilterDCaC']['customer']) - 1;
												if($jsonResponse['data']['FilterDCaC']['customer'][$idxLast]['selected']) {
													$filterExistCustomer[] = $codeCustomer;
												}
											}
										}
									}
								}
								if(is_array($sessionData['customer']) && count($sessionData['customer']) >= 1 && $sessionData['customer'][0] !== 'all') {
									foreach($sessionData['customer'] as $idx => $val) {
										if(!in_array($val, $filterExistCustomer)) {
											unset($sessionData['customer'][$idx]);
										}
									}
									if(count($sessionData['customer']) === 0) {
										$sessionData['customer'] = array('all');
									}
									$_SESSION[$sessionKey] = $sessionData;
								}
							};

							if(in_array($sessionKey, $sessionRegistered)) {
								$sessionData = $_SESSION[$sessionKey];
								$isCustomerSelected = false;
								if(array_key_exists('customer', $sessionData) && is_array($sessionData['customer']) && count($sessionData['customer']) >= 1) {
									if($sessionData['customer'][0] !== 'all') {
										$isCustomerSelected = true;
									}
								}

								if(array_key_exists('dc', $sessionData) && is_array($sessionData['dc']) && count($sessionData['dc']) >= 1) {
									if(trim(strtolower($sessionData['dc'][0])) === 'all') {
										$filterAllCustomer($isCustomerSelected);

										$jsonResponse['success']['FilterDCaC']['customer'] = ((count($jsonResponse['data']['FilterDCaC']['customer']) - 1) !== 0) ? true : false;
										$jsonResponse['message']['FilterDCaC']['customer'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['FilterDCaC']['customer']) - 1);
										$jsonResponse['datetime']['FilterDCaC'] = date('Y-m-d H:i:s');
										$jsonResponse['took']['FilterDCaC'] = (floor(microtime(true)*1000))-$startTime.'ms';
										$jsonResponse['errcode']['FilterDCaC']['customer'] = ((count($jsonResponse['data']['FilterDCaC']['customer']) - 1) !== 0) ? 0 : 1;
									} else {
										$totalSelectedDC = 0;
										foreach($jsonResponse['data']['FilterDCaC']['dc'] as $idx => $perDC) {
											if(in_array($perDC['code'], $sessionData['dc'])) {
												$jsonResponse['data']['FilterDCaC']['dc'][$idx]['selected'] = true;
												$totalSelectedDC += 1;
											}
										}
										if($totalSelectedDC >= 1) {
											$jsonResponse['data']['FilterDCaC']['dc'][0]['selected'] = false;
										}

										$listFilterDC = db_runQuery(array(
											'config_array' => $configMysql,
											'database_index' => 1,
											'input' => array_merge($sessionData['dc'], array(1, 1)),
											// 'query' => sprintf('SELECT KODE_DC, NAMA, DB_NAME FROM %s WHERE KODE_DC IN (%s) AND AKTIF_DC = ?;', 'mtp_master_dc', implode(',', array_fill(0, count($sessionData['dc']), '?'))),
											'query' => sprintf('SELECT KODE_DC, NAMA, DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME FROM %s WHERE KODE_DC IN (SELECT KODE_DC FROM %s WHERE KODE_DC IN (%s) AND AKTIF_DC = ?) AND FLAG = ?;', 'mtp_master_koneksi', 'mtp_master_dc', implode(',', array_fill(0, count($sessionData['dc']), '?'))),
											'param' => str_repeat('s', count($sessionData['dc']) + 2),
											'getData' => true,
											'getAllRow' => true
										));
										if(!isEmptyVar($listFilterDC) && $listFilterDC !== 'ZERO_DATA' && count($listFilterDC) >= 1) {
											foreach($listFilterDC as $perDC) {
												$customerDBMS = array(
													'mysql_host' => $perDC['DB_HOST'],
													'mysql_username' => $perDC['DB_USER'],
													'mysql_password' => (isEmptyVar($perDC['DB_PASS'])) ? '' : $perDC['DB_PASS'],
													'mysql_database' => array( $perDC['DB_NAME'] )
												);

												$queryInput = array('MTP%', 1);
												$queryString = "SELECT * FROM mtp_master_dc WHERE KODE_DC NOT LIKE ? AND AKTIF = ?;";
												$queryParam = str_repeat('s', count($queryInput));

												$fetchCustomerList = db_runQuery(array(
													'config_array' => $customerDBMS,
													'database_index' => 0,
													'input' => $queryInput,
													'query' => $queryString,
													'param' => $queryParam,
													'getData' => true,
													'getAllRow' => true
												));
												if(!isEmptyVar($fetchCustomerList) && $fetchCustomerList !== 'ZERO_DATA' && count($fetchCustomerList) >= 1) {
													$filterExistCustomer = array();
													foreach($fetchCustomerList as $idx => $row) {
														$codeCustomer = sprintf('%s.%s', $perDC['KODE_DC'], $row['KODE_DC']);
														$jsonResponse['data']['FilterDCaC']['customer'][] = array(
															'code' => $codeCustomer,
															'parent' => ucwords(trim(str_replace(['DC', 'MTP'], ['', ''], $perDC['NAMA']))),
															'name' => $row['NAMA'],
															'selected' => ($isCustomerSelected) ? ((in_array($codeCustomer, $sessionData['customer'])) ? true : false) : false
														);
														$idxLast = count($jsonResponse['data']['FilterDCaC']['customer']) - 1;
														if($jsonResponse['data']['FilterDCaC']['customer'][$idxLast]['selected'] === true) {
															$filterExistCustomer[] = $codeCustomer;
														}
													}
													if(is_array($sessionData['customer']) && count($sessionData['customer']) >= 1 && $sessionData['customer'][0] !== 'all') {
														foreach($sessionData['customer'] as $idx => $val) {
															if(!in_array($val, $filterExistCustomer)) {
																unset($sessionData['customer'][$idx]);
															}
														}
														$_SESSION[$sessionKey] = $sessionData;
													}
												}
											}
										}

										$jsonResponse['success']['FilterDCaC']['customer'] = ((count($jsonResponse['data']['FilterDCaC']['customer']) - 1) !== 0) ? true : false;
										$jsonResponse['message']['FilterDCaC']['customer'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['FilterDCaC']['customer']) - 1);
										$jsonResponse['datetime']['FilterDCaC'] = date('Y-m-d H:i:s');
										$jsonResponse['took']['FilterDCaC'] = (floor(microtime(true)*1000))-$startTime.'ms';
										$jsonResponse['errcode']['FilterDCaC']['customer'] = ((count($jsonResponse['data']['FilterDCaC']['customer']) - 1) !== 0) ? 0 : 1;
									}
								}
							} else {
								$_SESSION[$sessionKey] = array(
									'dc' => array('all'),
									'customer' => array('all')
								);
								$user->registerSessionKey($sessionKey);

								$filterAllCustomer($isCustomerSelected);

								$jsonResponse['success']['FilterDCaC']['customer'] = ((count($jsonResponse['data']['FilterDCaC']['customer']) - 1) !== 0) ? true : false;
								$jsonResponse['message']['FilterDCaC']['customer'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['FilterDCaC']['customer']) - 1);
								$jsonResponse['datetime']['FilterDCaC'] = date('Y-m-d H:i:s');
								$jsonResponse['took']['FilterDCaC'] = (floor(microtime(true)*1000))-$startTime.'ms';
								$jsonResponse['errcode']['FilterDCaC']['customer'] = ((count($jsonResponse['data']['FilterDCaC']['customer']) - 1) !== 0) ? 0 : 1;
							}
							if(array_key_exists('customer', $sessionData) && is_array($sessionData['customer']) && count($sessionData['customer']) >= 1) {
								if($sessionData['customer'][0] !== 'all') {
									$totalSelectedCustomer = 0;
									foreach($jsonResponse['data']['FilterDCaC']['customer'] as $idx => $perCustomer) {
										if(in_array($perCustomer['code'], $sessionData['customer'])) {
											// $jsonResponse['data']['FilterDCaC']['customer'][$idx]['selected'] = true;
											$totalSelectedCustomer += 1;
										}
									}
									if($totalSelectedCustomer >= 1) {
										$jsonResponse['data']['FilterDCaC']['customer'][0]['selected'] = false;
									}
								}
							}

							// Remove duplicate Unique ID
							if(count($jsonResponse['data']['FilterDCaC']['customer']) >= 2) { // 2 rows, because first is 'All'
								$jsonResponse['data']['FilterDCaC']['customer'] = uniqueAssocByKey($jsonResponse['data']['FilterDCaC']['customer'], 'code');
							}

							$jsonResponse['success']['FilterDCaC']['customer'] = ((count($jsonResponse['data']['FilterDCaC']['customer']) - 1) !== 0) ? true : false;
							$jsonResponse['message']['FilterDCaC']['customer'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['FilterDCaC']['customer']) - 1);
							$jsonResponse['datetime']['FilterDCaC'] = date('Y-m-d H:i:s');
							$jsonResponse['took']['FilterDCaC'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode']['FilterDCaC']['customer'] = ((count($jsonResponse['data']['FilterDCaC']['customer']) - 1) !== 0) ? 0 : 1;
						}

						// Clear important value
						foreach($jsonResponse['data']['FilterDCaC']['dc'] as $key => $row) {
							if(array_key_exists('dbms', $row)) {
								unset($jsonResponse['data']['FilterDCaC']['dc'][$key]['dbms']);
							}
						}
					}
					// Serve for: ThroughputIO 
					if(boolval($serveData['ThroughputIO']) === true) {
						// Result in JSON
						$jsonResponse['success']['ThroughputIO'] = array('charts' => false, 'tables' => false);
						$jsonResponse['message']['ThroughputIO'] = array('charts' => 'Data not found!', 'tables' => 'Data not found!');
						$jsonResponse['datetime']['ThroughputIO'] = date('Y-m-d H:i:s');
						$jsonResponse['data']['ThroughputIO'] = array('summary' => array(), 'charts' => array(), 'tables' => array());
						$jsonResponse['took']['ThroughputIO'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode']['ThroughputIO'] = array('charts' => 1, 'tables' => 1);

						// Default summary values
						$jsonResponse['data']['ThroughputIO']['summary'] = array(
							'on_date' => date('F Y'),
							'total_inbound' => 0,
							'total_outbound' => 0,
							'uom' => $dataRequest['ThroughputIO']['uom']
						);

						// Default chart values
						$totalDays = date('t');
						for ($i = 0; $i < $totalDays; $i++) {
							$jsonResponse['data']['ThroughputIO']['charts'][$i] = array(
								'inbound' => 0,
								'outbound' => 0
							);
						}
					}
					// Serve for: PartnershipCustomer 
					if(boolval($serveData['PartnershipCustomer']) === true) {
						// Result in JSON
						$jsonResponse['success']['PartnershipCustomer'] = false;
						$jsonResponse['message']['PartnershipCustomer'] = 'Data not found!';
						$jsonResponse['datetime']['PartnershipCustomer'] = date('Y-m-d H:i:s');
						$jsonResponse['data']['PartnershipCustomer'] = array();
						$jsonResponse['took']['ThroughputIO'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode']['PartnershipCustomer'] = 1;
					}
					// Serve for: ActivitiesTbM 
					if(boolval($serveData['ActivitiesTbM']) === true) {
						// Result in JSON
						$jsonResponse['success']['ActivitiesTbM'] = array('listMonth' => false, 'charts' => false, 'tables' => false);
						$jsonResponse['message']['ActivitiesTbM'] = array('listMonth' => 'Data not found!', 'charts' => 'Data not found!', 'tables' => 'Data not found!');
						$jsonResponse['datetime']['ActivitiesTbM'] = date('Y-m-d H:i:s');
						$jsonResponse['data']['ActivitiesTbM'] = array('listMonth' => array(), 'charts' => array(), 'tables' => array());
						$jsonResponse['took']['ActivitiesTbM'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode']['ActivitiesTbM'] = array('listMonth' => 1, 'charts' => 1, 'tables' => 1);

						$totalDays = date('t', strtotime($period));
						for ($i = 0; $i < $totalDays; $i++) {
							$jsonResponse['data']['ActivitiesTbM']['charts']['data'][$i] = array(
								'order' => null,
								'day' => null,
								'date' => null,
								'inbound' => 0,
								'outbound' => 0,
								'total' => 0,
							);
						}
						$jsonResponse['data']['ActivitiesTbM']['charts']['firstDay'] = null;
						$jsonResponse['data']['ActivitiesTbM']['charts']['totalDay'] = $totalDays;
						$jsonResponse['data']['ActivitiesTbM']['charts']['periodDate'] = $period;

						$jsonResponse['data']['ActivitiesTbM']['tables'] = array(
							'data' => array(),
							'total' => array(
								'inbound' => 0,
								'outbound' => 0,
								'total' => 0
							)
						);
					}
					// Serve for: StorageTempHum 
					if(boolval($serveData['StorageTempHum']) === true) {
						// Result in JSON
						$jsonResponse['success']['StorageTempHum'] = array('charts' => array('temperature' => 0, 'humidity' => 0), 'listPeriod' => false, 'listCS' => false, 'listView' => true);
						$jsonResponse['message']['StorageTempHum'] = array('charts' => array('temperature' => 'Data not found!', 'humidity' => 'Data not found!'), 'listPeriod' => 'Data not found!', 'listCS' => 'Data not found!', 'listView' => 'Data not found!');
						$jsonResponse['datetime']['StorageTempHum'] = date('Y-m-d H:i:s');
						$jsonResponse['data']['StorageTempHum'] = array('charts' => array('temperature' => array(), 'humidity' => array()), 'listPeriod' => array(), 'listCS' => array(), 'listView' => array(), 'datePicked' => null);
						$jsonResponse['took']['StorageTempHum'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode']['StorageTempHum'] = array('charts' => array('temperature' => 1, 'humidity' => 1), 'listPeriod' => 1, 'listCS' => 1, 'listView' => 0);

						// Default charts
						$jsonResponse['data']['StorageTempHum']['charts'] = array(
							'temperature' => array(
								0 => array(
									'name' => '-',
									'label' => array(),
									'datasets' => array(),
									'stableRate' => 0,
								),
							),
							'humidity' => array(
								0 => array(
									'name' => '-',
									'label' => array(),
									'datasets' => array(),
									'stableRate' => 0,
								),
							),
						);
						for ($i = 0; $i < 24; $i++) {
							$jsonResponse['data']['StorageTempHum']['charts']['temperature'][0]['label'][$i] = sprintf('%s:00', str_pad($i, 2, '0', STR_PAD_LEFT));
							$jsonResponse['data']['StorageTempHum']['charts']['temperature'][0]['datasets'][$i] = 0;
							$jsonResponse['data']['StorageTempHum']['charts']['humidity'][0]['label'][$i] = sprintf('%s:00', str_pad($i, 2, '0', STR_PAD_LEFT));
							$jsonResponse['data']['StorageTempHum']['charts']['humidity'][0]['datasets'][$i] = 0;
						}
						
						// Default listPeriod
						$jsonResponse['data']['StorageTempHum']['listPeriod'] = array(
							'maxRange' => 100, // per date/days
							'totalPeriod' => 100, // per date/days
							'availableDate' => array(
								0 => array(
									'name' => 'Today',
									'code' => hash('md5', sprintf('%s%s', date('Y-m-d'), $APP_CORE['app_key_encrypt']))
								) // Insert current today
							)
						);

						// Default listCS
						$jsonResponse['data']['StorageTempHum']['listCS'][] = array(
							'name' => 'All',
							'code' => hash('md5', sprintf('%s%s', 'all', $APP_CORE['app_key_encrypt']))
						);

						// Default listView
						$jsonResponse['data']['StorageTempHum']['listView'] = array(
							0 => array(
								'name' => 'Compact',
								'code' => hash('md5', sprintf('%s%s', 'compact', $APP_CORE['app_key_encrypt']))
							),
							1 => array(
								'name' => 'RAW',
								'code' => hash('md5', sprintf('%s%s', 'raw', $APP_CORE['app_key_encrypt']))
							),
						);

						// Default datePicked
						$jsonResponse['data']['StorageTempHum']['datePicked'] = date('F d, Y');
						// $jsonResponse['data']['StorageTempHum']['datePicked'] = date('F d, Y', strtotime('-1 days')); // Yesterday
					}
					// Serve for: ChamberUsage 
					if(boolval($serveData['ChamberUsage']) === true) {
						// Result in JSON
						$jsonResponse['success']['ChamberUsage'] = false;
						$jsonResponse['message']['ChamberUsage'] = 'Data not found!';
						$jsonResponse['datetime']['ChamberUsage'] = date('Y-m-d H:i:s');
						$jsonResponse['data']['ChamberUsage'] = array('summary' => array(), 'charts' => array(), 'labels' => array(), 'listCustomer' => array());
						$jsonResponse['took']['ChamberUsage'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode']['ChamberUsage'] = 1;

						$jsonResponse['data']['ChamberUsage']['summary'] = array(
							'customer_name' => '-',
							'customer_logo' => path2url(sprintf('%s/image/illustrations/avatar-%s.png', DIR_ASSET, $appConfig['CORE']['app_build_version'])),
							'total_available' => 0,
							'total_used' => 0
						);

						$jsonResponse['data']['ChamberUsage']['charts'] = array(
							'datasets' => array(),
							'totalUsage' => 0,
							'lastUpdate' => 'EMPTY',
						);
					}

					/*
					 * Processing data
					 */
					$sessionKey = $APP_CORE['session_prefix'] . 'user-filter';
					$sessionRegistered = $user->takeSessionKey();
					$sessionData = array();

					$isPerCustomer = true;
					$doCustomer = function($customerData, $dcData, $customerDBMS, $isPerCustomer) use (&$jsonResponse, $configMysql, $appConfig, $isPartPrivileges, $serveData, $dataRequest, &$customer, &$presensi, &$period, $startTime) {
						if(boolval($isPerCustomer) === true) {
							// Serve for: ThroughputIO
							if(boolval($serveData['ThroughputIO']) === true) {
								$prefixTable = 'pallet';
								switch(strtolower(trim($dataRequest['ThroughputIO']['uom']))) {
									case 'multi': $prefixTable = 'pcs'; break;
									case 'lpn': $prefixTable = 'pallet'; break;
								}

								// Reverse Company [PT] Name, from end to start
								if(substr(strtolower(trim($customerData['NAMA'])), -2) === 'pt') {
									$customerData['NAMA'] = 'PT. ' . trim(substr(trim($customerData['NAMA']), 0, -2));
								}

								$queryInput = array(
									$customerData['STOREKEY']
								);
								$queryString = sprintf('
									SELECT
										CUSTOMER STOREKEY
										, SUM(QTY_IN_1) QTY_IN_1, SUM(QTY_OUT_1) QTY_OUT_1
										, SUM(QTY_IN_2) QTY_IN_2, SUM(QTY_OUT_2) QTY_OUT_2
										, SUM(QTY_IN_3) QTY_IN_3, SUM(QTY_OUT_3) QTY_OUT_3
										, SUM(QTY_IN_4) QTY_IN_4, SUM(QTY_OUT_4) QTY_OUT_4
										, SUM(QTY_IN_5) QTY_IN_5, SUM(QTY_OUT_5) QTY_OUT_5
										, SUM(QTY_IN_6) QTY_IN_6, SUM(QTY_OUT_6) QTY_OUT_6
										, SUM(QTY_IN_7) QTY_IN_7, SUM(QTY_OUT_7) QTY_OUT_7
										, SUM(QTY_IN_8) QTY_IN_8, SUM(QTY_OUT_8) QTY_OUT_8
										, SUM(QTY_IN_9) QTY_IN_9, SUM(QTY_OUT_9) QTY_OUT_9
										, SUM(QTY_IN_10) QTY_IN_10, SUM(QTY_OUT_10) QTY_OUT_10
										, SUM(QTY_IN_11) QTY_IN_11, SUM(QTY_OUT_11) QTY_OUT_11
										, SUM(QTY_IN_12) QTY_IN_12, SUM(QTY_OUT_12) QTY_OUT_12
										, SUM(QTY_IN_13) QTY_IN_13, SUM(QTY_OUT_13) QTY_OUT_13
										, SUM(QTY_IN_14) QTY_IN_14, SUM(QTY_OUT_14) QTY_OUT_14
										, SUM(QTY_IN_15) QTY_IN_15, SUM(QTY_OUT_15) QTY_OUT_15
										, SUM(QTY_IN_16) QTY_IN_16, SUM(QTY_OUT_16) QTY_OUT_16
										, SUM(QTY_IN_17) QTY_IN_17, SUM(QTY_OUT_17) QTY_OUT_17
										, SUM(QTY_IN_18) QTY_IN_18, SUM(QTY_OUT_18) QTY_OUT_18
										, SUM(QTY_IN_19) QTY_IN_19, SUM(QTY_OUT_19) QTY_OUT_19
										, SUM(QTY_IN_20) QTY_IN_20, SUM(QTY_OUT_20) QTY_OUT_20
										, SUM(QTY_IN_21) QTY_IN_21, SUM(QTY_OUT_21) QTY_OUT_21
										, SUM(QTY_IN_22) QTY_IN_22, SUM(QTY_OUT_22) QTY_OUT_22
										, SUM(QTY_IN_23) QTY_IN_23, SUM(QTY_OUT_23) QTY_OUT_23
										, SUM(QTY_IN_24) QTY_IN_24, SUM(QTY_OUT_24) QTY_OUT_24
										, SUM(QTY_IN_25) QTY_IN_25, SUM(QTY_OUT_25) QTY_OUT_25
										, SUM(QTY_IN_26) QTY_IN_26, SUM(QTY_OUT_26) QTY_OUT_26
										, SUM(QTY_IN_27) QTY_IN_27, SUM(QTY_OUT_27) QTY_OUT_27
										, SUM(QTY_IN_28) QTY_IN_28, SUM(QTY_OUT_28) QTY_OUT_28
										, SUM(QTY_IN_29) QTY_IN_29, SUM(QTY_OUT_29) QTY_OUT_29
										, SUM(QTY_IN_30) QTY_IN_30, SUM(QTY_OUT_30) QTY_OUT_30
										, SUM(QTY_IN_31) QTY_IN_31, SUM(QTY_OUT_31) QTY_OUT_31
									FROM %s WHERE CUSTOMER = ?
									GROUP BY CUSTOMER
									ORDER BY CUSTOMER;
								', 'mtp_in_out_resume_'.$prefixTable.'_'.date('ym'));
								$queryParam = str_repeat('s', count($queryInput));

								$fetchThroughputIO = db_runQuery(array(
									'config_array' => $customerDBMS,
									'database_index' => 0,
									'input' => $queryInput,
									'query' => $queryString,
									'param' => $queryParam,
									'getData' => true,
									'getAllRow' => false
								));
								if(!isEmptyVar($fetchThroughputIO) && $fetchThroughputIO !== 'ZERO_DATA') {
									$jsonResponse['success']['ThroughputIO']['charts'] += 1;
									$jsonResponse['success']['ThroughputIO']['tables'] += 1;
									$jsonResponse['datetime']['ThroughputIO'] = date('Y-m-d H:i:s');

									// Serve data
									$totalDays = date('t');
									$totalInbound = 0;
									$totalOutbound = 0;
									$customerKey = $fetchThroughputIO['STOREKEY'];
									unset($fetchThroughputIO['STOREKEY']);

									foreach($fetchThroughputIO as $key => $val) {
										$idx = preg_replace('/[^0-9]/', '', $key);
										if((intval($idx) - 1) >= $totalDays) { break; }
										$type = (wordExist(strtoupper($key), 'IN')) ? 'inbound' : 'outbound';
										$jsonResponse['data']['ThroughputIO']['charts'][intval($idx) - 1][$type] += $val;
										if($type === 'inbound') {
											$totalInbound += $val;
										}
										if($type === 'outbound') {
											$totalOutbound += $val;
										}
									}
									$jsonResponse['data']['ThroughputIO']['tables'][] = array(
										'customer' => strtoupper($customerData['NAMA']),
										'inbound' => $totalInbound,
										'outbound' => $totalOutbound,
										'total' => $totalInbound + $totalOutbound
									);
									$jsonResponse['data']['ThroughputIO']['summary']['total_inbound'] += $totalInbound;
									$jsonResponse['data']['ThroughputIO']['summary']['total_outbound'] += $totalOutbound;
									$jsonResponse['took']['ThroughputIO'] = (floor(microtime(true)*1000))-$startTime.'ms';
								} else {
									$jsonResponse['datetime']['ThroughputIO'] = date('Y-m-d H:i:s');
									$jsonResponse['data']['ThroughputIO']['tables'][] = array(
										'customer' => strtoupper($customerData['NAMA']),
										'inbound' => 0,
										'outbound' => 0,
										'total' => 0
									);
									$jsonResponse['took']['ThroughputIO'] = (floor(microtime(true)*1000))-$startTime.'ms';
								}
							}
							// Serve for: PartnershipCustomer
							if(boolval($serveData['PartnershipCustomer']) === true) {
								$queryInput = array(
									$customerData['KODE_DC'],
									$customerData['STOREKEY'],
									2
								);
								$queryString = sprintf('SELECT * FROM %s WHERE (KODE_CUSTOMER = ? OR KODE_STOREKEY = ?) AND APPROVAL_FLAG >= ?;', $appConfig['CORE']['tb_prefix'].'user_account');
								$queryParam = str_repeat('s', count($queryInput));

								$fetchUserAccount = db_runQuery(array(
									'config_array' => $configMysql,
									'database_index' => 0, // db.mtp_crm
									'input' => $queryInput,
									'query' => $queryString,
									'param' => $queryParam,
									'getData' => true
								));
								if(!isEmptyVar($fetchUserAccount) && $fetchUserAccount !== 'ZERO_DATA') {
									$jsonResponse['success']['PartnershipCustomer'] = true;
									$jsonResponse['datetime']['PartnershipCustomer'] = date('Y-m-d H:i:s');

									$jsonResponse['data']['PartnershipCustomer'][] = array(
										'code' => hash('md5', sprintf('%s.%s', $dcData['KODE_DC'], $customerData['KODE_DC'])),
										'name' => $fetchUserAccount['NAMA_PERUSAHAAN'],
										'avatar' => path2url(sprintf('%s/image/illustrations/avatar-%s.png', DIR_ASSET, $appConfig['CORE']['app_build_version'])),
										'registerDate' => strftime('%A, %d %B %Y', strtotime($fetchUserAccount['TGL_DAFTAR']))
									);
									$lastIdx = count($jsonResponse['data']['PartnershipCustomer']) - 1;
									if(!isEmptyVar($fetchUserAccount['META_LOGO'])) {
										$jsonResponse['data']['PartnershipCustomer'][$lastIdx]['avatar'] = sprintf('%s/app/includes/view-document.inc.php?uid=%s', getURI(2), $fetchUserAccount['META_LOGO']);
									} else {
										if(!isEmptyVar($fetchUserAccount['META_AVATAR'])) {
											$jsonResponse['data']['PartnershipCustomer'][$lastIdx]['avatar'] = sprintf('%s/app/includes/view-document.inc.php?uid=%s', getURI(2), $fetchUserAccount['META_AVATAR']);
										}
									}

									$jsonResponse['took']['PartnershipCustomer'] = (floor(microtime(true)*1000))-$startTime.'ms';
								} else {
									$jsonResponse['datetime']['PartnershipCustomer'] = date('Y-m-d H:i:s');
									$jsonResponse['data']['PartnershipCustomer'][] = array(
										'code' => hash('md5', sprintf('%s.%s', $dcData['KODE_DC'], $customerData['KODE_DC'])),
										'name' => $customerData['NAMA'],
										'avatar' => path2url(sprintf('%s/image/illustrations/avatar-%s.png', DIR_ASSET, $appConfig['CORE']['app_build_version'])),
										'registerDate' => '-'
									);
									$jsonResponse['took']['PartnershipCustomer'] = (floor(microtime(true)*1000))-$startTime.'ms';
								}
							}
							// Serve for: ActivitiesTbM
							if(boolval($serveData['ActivitiesTbM']) === true) {
								$customerList = $customerData['KODE_DC'];
								$totalDays = date('t', strtotime($period));

								//////////////////////////////////////////////////////
								/// Generate month list for selectpicker
								//////////////////////////////////////////////////////
								// Data di simpan ke: data => listMonth
								// Format data: Application/JSON
								//
								$queryString = "SELECT DISTINCT DATE_FORMAT(A.TGL_MASUK,'%M %Y') PERIODE, DATE_FORMAT(A.TGL_MASUK,'%Y-%m') PERIODE2
									FROM MTP_TRS_KENDARAAN_CUSTOMER A
									ORDER BY DATE_FORMAT(A.TGL_MASUK,'%Y-%m') DESC";

								$fetchMonthList = db_runQuery(array(
									'config_array' => $customerDBMS,
									'database_index' => 0,
									'query' => $queryString,
									'getData' => true,
									'getAllRow' => true
								));
								if(!isEmptyVar($fetchMonthList) && $fetchMonthList !== 'ZERO_DATA' && count($fetchMonthList) > 0) {
									$jsonResponse['success']['ActivitiesTbM']['listMonth'] = true;
									$jsonResponse['message']['ActivitiesTbM']['listMonth'] = sprintf('Data found (%s) entries!', count($fetchMonthList));
									$jsonResponse['datetime']['ActivitiesTbM'] = date('Y-m-d H:i:s');

									// Serve data
									foreach($fetchMonthList as $idx => $row) {
										$jsonResponse['data']['ActivitiesTbM']['listMonth'][trim($row["PERIODE2"])] = trim($row["PERIODE"]);
									}

									$jsonResponse['took']['ActivitiesTbM'] = (floor(microtime(true)*1000))-$startTime.'ms';
								}

								//////////////////////////////////////////////////////
								////// Ambil data untuk Chart/Grafik bulanan
								//////////////////////////////////////////////////////
								// Data di simpan ke: data => charts
								// Format data: Application/JSON
								//
								$queryInput = array(
									$period,
									$period
								);
								$queryInput = array_merge($queryInput, explode(',', $customerList));
								$queryInput = array_merge($queryInput, array($period, $period));
								$queryInput = array_merge($queryInput, explode(',', $customerList));
								$queryInput = array_merge($queryInput, array($period, $period));

								$queryString = "SELECT P.*, IFNULL(Q.OUTBOUND,0) OUTBOUND, IFNULL(P.INBOUND,0) + IFNULL(Q.OUTBOUND,0) TOTAL, DATE_FORMAT(P.TGL,'%M %Y') PERIODE
									FROM
									(
									SELECT A.URUTAN, A.TGL, A.HARI, IFNULL(B.INBOUND,0) INBOUND
									FROM
									(
										SELECT x.*, date_format(tgl,'%d') URUTAN FROM mtp_list_tanggal x
										WHERE TGL BETWEEN DATE(?) AND LAST_DAY(DATE(?))
									) A
									LEFT JOIN
									(
										SELECT DISTINCT DATE(TGL_MASUK) TANGGAL, COUNT(*) INBOUND FROM mtp_trs_kendaraan_customer
										WHERE KODE_CUSTOMER IN (SELECT NAMA FROM mtp_master_dc WHERE KODE_DC IN (". implode(',', array_fill(0, count(explode(',', $customerList)), '?')) .")) AND DATE(TGL_MASUK) BETWEEN DATE(?) AND LAST_DAY(DATE(?)) AND jenis_presensi='INBOUND'
										GROUP BY DATE(TGL_MASUK)
									) B
									ON A.TGL = B.TANGGAL
									) P
									LEFT JOIN
									(
										SELECT DISTINCT DATE(TGL_MASUK) TANGGAL, COUNT(*) OUTBOUND FROM mtp_trs_kendaraan_customer
										WHERE KODE_CUSTOMER IN (SELECT NAMA FROM mtp_master_dc WHERE KODE_DC IN (". implode(',', array_fill(0, count(explode(',', $customerList)), '?')) .")) AND DATE(tgl_masuk) BETWEEN DATE(?) AND LAST_DAY(DATE(?)) AND jenis_presensi='OUTBOUND'
										GROUP BY DATE(TGL_MASUK)
									) Q
									ON P.TGL = Q.TANGGAL
									ORDER BY P.TGL";
								$queryParam = str_repeat('s', count($queryInput));

								$fetchChartData = db_runQuery(array(
									'config_array' => $customerDBMS,
									'database_index' => 0,
									'input' => $queryInput,
									'query' => $queryString,
									'param' => $queryParam,
									'getData' => true,
									'getAllRow' => true
								));
								if(!isEmptyVar($fetchChartData) && $fetchChartData !== 'ZERO_DATA' && count($fetchChartData) > 0) {
									$jsonResponse['success']['ActivitiesTbM']['charts'] = true;
									$jsonResponse['message']['ActivitiesTbM']['charts'] = sprintf('Data found (%s) entries!', count($fetchChartData));
									$jsonResponse['datetime']['ActivitiesTbM'] = date('Y-m-d H:i:s');

									// Serve data
									$nilaiTop = 0;
									foreach($fetchChartData as $idx => $row) {
										if($idx <= 0) {
											$jsonResponse['data']['ActivitiesTbM']['charts']['firstDay'] = strtolower(trim($row["HARI"]));
											$jsonResponse['data']['ActivitiesTbM']['charts']['periodDate'] = trim($row["PERIODE"]);
										}
										if(intval(trim($row['TOTAL'])) > $nilaiTop) { $nilaiTop = intval(trim($row['TOTAL'])); }
										$jsonResponse['data']['ActivitiesTbM']['charts']['data'][$idx]['order'] = trim($row["URUTAN"]);
										$jsonResponse['data']['ActivitiesTbM']['charts']['data'][$idx]['day'] = trim($row["HARI"]);
										$jsonResponse['data']['ActivitiesTbM']['charts']['data'][$idx]['date'] = trim($row["TGL"]);
										$jsonResponse['data']['ActivitiesTbM']['charts']['data'][$idx]['inbound'] += (int) trim($row["INBOUND"]);
										$jsonResponse['data']['ActivitiesTbM']['charts']['data'][$idx]['outbound'] += (int) trim($row["OUTBOUND"]);
										$jsonResponse['data']['ActivitiesTbM']['charts']['data'][$idx]['total'] += (int) trim($row["TOTAL"]);
									}

									$jsonResponse['took']['ActivitiesTbM'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode']['ActivitiesTbM']['charts'] = 0;
								}

								//////////////////////////////////////////////////////
								////// Ambil data untuk tabel Chart/Grafik bulanan
								//////////////////////////////////////////////////////
								// Data di simpan ke: data => tables
								// Format data: Application/JSON
								//
								$queryInput = explode(',', $customerList);
								$queryInput = array_merge($queryInput, array($period, $period));
								$queryInput = array_merge($queryInput, explode(',', $customerList));
								$queryInput = array_merge($queryInput, array($period, $period));
								$queryInput = array_merge($queryInput, explode(',', $customerList));
								$queryInput = array_merge($queryInput, array($period, $period));

								$queryString = "SELECT P.*, IFNULL(Q.OUTBOUND,0) OUTBOUND, IFNULL(P.INBOUND,0)+IFNULL(Q.OUTBOUND,0) TOTAL
									FROM
									(		
										SELECT A.*, IFNULL(B.INBOUND,0) INBOUND
										FROM
										(		
											SELECT DISTINCT UCASE(TRIM(KODE_CUSTOMER)) KODE_CUSTOMER 
											FROM mtp_trs_kendaraan_customer
											WHERE KODE_CUSTOMER IN (SELECT NAMA FROM mtp_master_dc WHERE KODE_DC IN (". implode(',', array_fill(0, count(explode(',', $customerList)), '?')) .")) AND DATE(TGL_MASUK) BETWEEN DATE(?) AND LAST_DAY(DATE(?)) AND jenis_presensi IN ('INBOUND','OUTBOUND')
											GROUP BY KODE_CUSTOMER
										) A
										LEFT JOIN
										(
											SELECT DISTINCT UCASE(TRIM(KODE_CUSTOMER)) KODE_CUSTOMER, COUNT(*) INBOUND
											FROM mtp_trs_kendaraan_customer
											WHERE KODE_CUSTOMER IN (SELECT NAMA FROM mtp_master_dc WHERE KODE_DC IN (". implode(',', array_fill(0, count(explode(',', $customerList)), '?')) .")) AND DATE(TGL_MASUK) BETWEEN DATE(?) AND LAST_DAY(DATE(?)) AND jenis_presensi = 'INBOUND'
											GROUP BY KODE_CUSTOMER
										) B
										ON A.KODE_CUSTOMER = B.KODE_CUSTOMER			
									) P
									LEFT JOIN
									(
										SELECT DISTINCT UCASE(TRIM(KODE_CUSTOMER)) KODE_CUSTOMER, COUNT(*) OUTBOUND
										FROM mtp_trs_kendaraan_customer
										WHERE KODE_CUSTOMER IN (SELECT NAMA FROM mtp_master_dc WHERE KODE_DC IN (". implode(',', array_fill(0, count(explode(',', $customerList)), '?')) .")) AND DATE(TGL_MASUK) BETWEEN DATE(?) AND LAST_DAY(DATE(?)) AND jenis_presensi = 'OUTBOUND'
										GROUP BY KODE_CUSTOMER
									) Q 
									ON P.KODE_CUSTOMER = Q.KODE_CUSTOMER
									ORDER BY P.KODE_CUSTOMER";
								$queryParam = str_repeat('s', count($queryInput));

								$fetchTableData = db_runQuery(array(
									'config_array' => $customerDBMS,
									'database_index' => 0,
									'input' => $queryInput,
									'query' => $queryString,
									'param' => $queryParam,
									'getData' => true,
									'getAllRow' => true
								));
								if(!isEmptyVar($fetchTableData) && $fetchTableData !== 'ZERO_DATA' && count($fetchTableData) > 0) {
									$jsonResponse['success']['ActivitiesTbM']['tables'] = true;
									$jsonResponse['datetime']['ActivitiesTbM'] = date('Y-m-d H:i:s');

									// Serve data
									foreach($fetchTableData as $idx => $row) {
										$jsonResponse['data']['ActivitiesTbM']['tables']['total']['inbound'] += intval(trim($row["INBOUND"]));
										$jsonResponse['data']['ActivitiesTbM']['tables']['total']['outbound'] += intval(trim($row["OUTBOUND"]));
										$jsonResponse['data']['ActivitiesTbM']['tables']['total']['total'] += intval(trim($row["TOTAL"]));
										$jsonResponse['data']['ActivitiesTbM']['tables']['data'][] = array(
											'customer' => trim($row["KODE_CUSTOMER"]),
											'inbound' => intval(trim($row["INBOUND"])),
											'outbound' => intval(trim($row["OUTBOUND"])),
											'total' => intval(trim($row["TOTAL"])),
										);
									}

									$jsonResponse['took']['ActivitiesTbM'] = (floor(microtime(true)*1000))-$startTime.'ms';
								}
							}
						}
					};
					// Filter data by Customer/DC
					if(is_array($sessionRegistered)) {
						if(in_array($sessionKey, $sessionRegistered)) {
							$sessionData = $_SESSION[$sessionKey];
							if(array_key_exists('dc', $sessionData) && is_array($sessionData['dc']) && count($sessionData['dc']) >= 1) {
								if(trim(strtolower($sessionData['dc'][0])) === 'all') {
									$listAllDC = db_runQuery(array(
										'config_array' => $configMysql,
										'database_index' => 1,
										'input' => array('MTP%', 1, 1),
										// 'query' => sprintf('SELECT KODE_DC, NAMA, DB_NAME FROM %s WHERE KODE_DC LIKE ? AND AKTIF_DC = ?;', 'mtp_master_dc'),
										'query' => sprintf('SELECT KODE_DC, NAMA, DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME FROM %s WHERE KODE_DC IN (SELECT KODE_DC FROM %s WHERE KODE_DC LIKE ? AND AKTIF_DC = ?) AND FLAG = ?;', 'mtp_master_koneksi', 'mtp_master_dc'),
										'param' => 'sii',
										'getData' => true,
										'getAllRow' => true
									));
									if(!isEmptyVar($listAllDC) && $listAllDC !== 'ZERO_DATA' && count($listAllDC) >= 1) {
										foreach($listAllDC as $perDC) {
											$customerDBMS = array(
												'mysql_host' => $perDC['DB_HOST'],
												'mysql_username' => $perDC['DB_USER'],
												'mysql_password' => (isEmptyVar($perDC['DB_PASS'])) ? '' : $perDC['DB_PASS'],
												'mysql_database' => array( $perDC['DB_NAME'] )
											);

											if(trim(strtolower($sessionData['customer'][0])) === 'all') {
												$queryInput = array('MTP%', 1);
												$queryString = "SELECT * FROM mtp_master_dc WHERE KODE_DC NOT LIKE ? AND AKTIF = ?;";
												$queryParam = str_repeat('s', count($queryInput));

												$listAllCustomer = db_runQuery(array(
													'config_array' => $customerDBMS,
													'database_index' => 0,
													'input' => $queryInput,
													'query' => $queryString,
													'param' => $queryParam,
													'getData' => true,
													'getAllRow' => true
												));
												if(!isEmptyVar($listAllCustomer) && $listAllCustomer !== 'ZERO_DATA' && count($listAllCustomer) >= 1) {
													$listCodeCustomer = array(
														'customer' => array(),
														'storekey' => array()
													);
													foreach($listAllCustomer as $perCustomer) {
														if($isPerCustomer) {
															$doCustomer($perCustomer, $perDC, $customerDBMS, $isPerCustomer);
														} else {
															$listCodeCustomer['customer'][] = $perCustomer['KODE_DC'];
															$listCodeCustomer['storekey'][] = $perCustomer['STOREKEY'];
														}
													}
													if(boolval($isPerCustomer) === false) {
														$doCustomer($listCodeCustomer, $perDC, $customerDBMS, $isPerCustomer);
													}
												}
											} else {
												$listCodeCustomer = array(
													'customer' => array(),
													'storekey' => array()
												);
												foreach($sessionData['customer'] as $perCustomer) {
													list($codeDC, $codeCustomer) = array_pad(explode('.', $perCustomer), 2, NULL);
													if(!isEmptyVar($codeDC) && !isEmptyVar($codeCustomer)) {
														if($codeDC === $perDC['KODE_DC']) {
															$isCustomerExist = db_runQuery(array(
																'config_array' => $customerDBMS,
																'database_index' => 0,
																'input' => array($codeCustomer, 1),
																'query' => sprintf('SELECT COUNT(true) as FOUND, KODE_DC, NAMA, STOREKEY FROM %s WHERE KODE_DC = ? AND AKTIF = ?;', 'mtp_master_dc'),
																'param' => 'si',
																'getData' => true,
																'getAllRow' => false
															));
															if(isset($isCustomerExist['FOUND']) && $isCustomerExist !== 'ZERO_DATA' && $isCustomerExist['FOUND'] >= 1) {
																if($isPerCustomer) {
																	$doCustomer($isCustomerExist, $perDC, $customerDBMS, $isPerCustomer);
																} else {
																	$listCodeCustomer['customer'][] = $isCustomerExist['KODE_DC'];
																	$listCodeCustomer['storekey'][] = $isCustomerExist['STOREKEY'];
																}
															}
														}
													}
												}
												if(boolval($isPerCustomer) === false) {
													$doCustomer($listCodeCustomer, $perDC, $customerDBMS, $isPerCustomer);
												}
											}
										}
									}
								} else {
									$listFilterDC = db_runQuery(array(
										'config_array' => $configMysql,
										'database_index' => 1,
										'input' => array_merge($sessionData['dc'], array(1, 1)),
										// 'query' => sprintf('SELECT KODE_DC, NAMA, DB_NAME FROM %s WHERE KODE_DC IN (%s) AND AKTIF_DC = ?;', 'mtp_master_dc', implode(',', array_fill(0, count($sessionData['dc']), '?'))),
										'query' => sprintf('SELECT KODE_DC, NAMA, DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME FROM %s WHERE KODE_DC IN (SELECT KODE_DC FROM %s WHERE KODE_DC IN (%s) AND AKTIF_DC = ?) AND FLAG = ?;', 'mtp_master_koneksi', 'mtp_master_dc', implode(',', array_fill(0, count($sessionData['dc']), '?'))),
										'param' => str_repeat('s', count($sessionData['dc']) + 2),
										'getData' => true,
										'getAllRow' => true
									));
									if(!isEmptyVar($listFilterDC) && $listFilterDC !== 'ZERO_DATA' && count($listFilterDC) >= 1) {
										foreach($listFilterDC as $perDC) {
											$customerDBMS = array(
												'mysql_host' => $perDC['DB_HOST'],
												'mysql_username' => $perDC['DB_USER'],
												'mysql_password' => (isEmptyVar($perDC['DB_PASS'])) ? '' : $perDC['DB_PASS'],
												'mysql_database' => array( $perDC['DB_NAME'] )
											);

											if(trim(strtolower($sessionData['customer'][0])) === 'all') {
												$queryInput = array('MTP%', 1);
												$queryString = "SELECT * FROM mtp_master_dc WHERE KODE_DC NOT LIKE ? AND AKTIF = ?;";
												$queryParam = str_repeat('s', count($queryInput));

												$listAllCustomer = db_runQuery(array(
													'config_array' => $customerDBMS,
													'database_index' => 0,
													'input' => $queryInput,
													'query' => $queryString,
													'param' => $queryParam,
													'getData' => true,
													'getAllRow' => true
												));
												if(!isEmptyVar($listAllCustomer) && $listAllCustomer !== 'ZERO_DATA' && count($listAllCustomer) >= 1) {
													$listCodeCustomer = array(
														'customer' => array(),
														'storekey' => array()
													);
													foreach($listAllCustomer as $perCustomer) {
														if($isPerCustomer) {
															$doCustomer($perCustomer, $perDC, $customerDBMS, $isPerCustomer);
														} else {
															$listCodeCustomer['customer'][] = $perCustomer['KODE_DC'];
															$listCodeCustomer['storekey'][] = $perCustomer['STOREKEY'];
														}
													}
													if(boolval($isPerCustomer) === false) {
														$doCustomer($listCodeCustomer, $perDC, $customerDBMS, $isPerCustomer);
													}
												}
											} else {
												$listCodeCustomer = array(
													'customer' => array(),
													'storekey' => array()
												);
												foreach($sessionData['customer'] as $perCustomer) {
													list($codeDC, $codeCustomer) = array_pad(explode('.', $perCustomer), 2, NULL);
													if(!isEmptyVar($codeDC) && !isEmptyVar($codeCustomer)) {
														if($codeDC === $perDC['KODE_DC']) {
															$isCustomerExist = db_runQuery(array(
																'config_array' => $customerDBMS,
																'database_index' => 0,
																'input' => array($codeCustomer, 1),
																'query' => sprintf('SELECT COUNT(true) as FOUND, KODE_DC, NAMA, STOREKEY FROM %s WHERE KODE_DC = ? AND AKTIF = ?;', 'mtp_master_dc'),
																'param' => 'si',
																'getData' => true,
																'getAllRow' => false
															));
															if(isset($isCustomerExist['FOUND']) && $isCustomerExist !== 'ZERO_DATA' && $isCustomerExist['FOUND'] >= 1) {
																if($isPerCustomer) {
																	$doCustomer($isCustomerExist, $perDC, $customerDBMS, $isPerCustomer);
																} else {
																	$listCodeCustomer['customer'][] = $isCustomerExist['KODE_DC'];
																	$listCodeCustomer['storekey'][] = $isCustomerExist['STOREKEY'];
																}
															}
														}
													}
												}
												if(boolval($isPerCustomer) === false) {
													$doCustomer($listCodeCustomer, $perDC, $customerDBMS, $isPerCustomer);
												}
											}
										}
									}
								}
							}
						}
					}

					// Serve for: StorageTempHum
					if(boolval($serveData['StorageTempHum']) === true) {
						// Fetch list of available Period
						$queryString = sprintf('SELECT DISTINCT(DATE(TGL)) AS DATE_PERIOD FROM %s GROUP BY DATE(TGL) ORDER BY TGL DESC LIMIT 0,%s;', 'mtp_report_temperature', $jsonResponse['data']['StorageTempHum']['listPeriod']['maxRange']);
						$fetchAvailablePeriod = db_runQuery(array(
							'config_array' => $customerDBMS,
							'database_index' => 0,
							'query' => $queryString,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchAvailablePeriod) && $fetchAvailablePeriod !== 'ZERO_DATA' && count($fetchAvailablePeriod) >= 1) {
							$jsonResponse['success']['StorageTempHum']['listPeriod'] = 0;
							$jsonResponse['datetime']['StorageTempHum'] = date('Y-m-d H:i:s');
							foreach($fetchAvailablePeriod as $perRow) {
								$jsonResponse['success']['StorageTempHum']['listPeriod'] += 1;
								if(!in_array($perRow['DATE_PERIOD'], $jsonResponse['data']['StorageTempHum']['listPeriod']['availableDate'])) {
									$jsonResponse['data']['StorageTempHum']['listPeriod']['availableDate'][] = array(
										'name' => $perRow['DATE_PERIOD'],
										'code' => hash('md5', sprintf('%s%s', $perRow['DATE_PERIOD'], $APP_CORE['app_key_encrypt']))
									);
								}
							}
							$jsonResponse['data']['StorageTempHum']['listPeriod']['totalPeriod'] = count($fetchAvailablePeriod);
							$jsonResponse['took']['StorageTempHum'] = (floor(microtime(true)*1000))-$startTime.'ms';
						}

						// Fetch list of available Cold Storage (CS)
						$queryString = sprintf('SELECT DISTINCT(MESIN) AS CS_NAME FROM %s GROUP BY MESIN;', 'mtp_report_temperature');
						$fetchAvailableCS = db_runQuery(array(
							'config_array' => $customerDBMS,
							'database_index' => 0,
							'query' => $queryString,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchAvailableCS) && $fetchAvailableCS !== 'ZERO_DATA' && count($fetchAvailableCS) >= 1) {
							$jsonResponse['success']['StorageTempHum']['listCS'] = 0;
							$jsonResponse['datetime']['StorageTempHum'] = date('Y-m-d H:i:s');
							foreach($fetchAvailableCS as $perRow) {
								$jsonResponse['success']['StorageTempHum']['listCS'] += 1;
								$jsonResponse['data']['StorageTempHum']['listCS'][] = array(
									'name' => $perRow['CS_NAME'],
									'code' => hash('md5', sprintf('%s%s', $perRow['CS_NAME'], $APP_CORE['app_key_encrypt']))
								);
							}
							$jsonResponse['took']['StorageTempHum'] = (floor(microtime(true)*1000))-$startTime.'ms';
						}

						// Fetch datasets
						$queryInput = array();
						if(!isEmptyVar($dataRequest['StorageTempHum']['period'])) {
							$isFound = searchArrAssoc($jsonResponse['data']['StorageTempHum']['listPeriod']['availableDate'], 'code', $dataRequest['StorageTempHum']['period']) ?? null;
							if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
								if(strtolower(trim($isFound[0]['name'])) === 'today') {
									$queryInput[] = date('Y-m-d'); // Current today
									// $queryInput[] = date('Y-m-d', strtotime('-1 days')); // Get yesterday
								} else {
									$queryInput[] = $isFound[0]['name']; // YYYY-MM-DD
									$jsonResponse['data']['StorageTempHum']['datePicked'] = date('F d, Y', strtotime($isFound[0]['name']));
								}
							} else {
								$queryInput[] = date('Y-m-d'); // Current today
								// $queryInput[] = date('Y-m-d', strtotime('-1 days')); // Get yesterday
							}
						} else {
							$queryInput[] = date('Y-m-d'); // Current today
							// $queryInput[] = date('Y-m-d', strtotime('-1 days')); // Get yesterday
						}
						$queryStringAdd = '';
						if(!isEmptyVar($dataRequest['StorageTempHum']['cs'])) {
							if($dataRequest['StorageTempHum']['cs'] !== $jsonResponse['data']['StorageTempHum']['listCS'][0]['code']) {
								$isFound = searchArrAssoc($jsonResponse['data']['StorageTempHum']['listCS'], 'code', $dataRequest['StorageTempHum']['cs']) ?? null;
								if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
									$queryStringAdd = 'AND MESIN = ?';
									$queryInput[] = $isFound[0]['name'];
								}
								// else {
								// 	$queryInput[] = $jsonResponse['data']['coldStorageTemperature']['listCS'][0]['name'];
								// }
							}
						}
						$queryString = sprintf('SELECT MESIN AS CS_NAME, TGL AS DATE_PERIOD, SUHU AS TEMPERATURE, HUMIDITY FROM %s WHERE DATE(TGL) = ? %s ORDER BY MESIN, TGL;', 'mtp_report_temperature', $queryStringAdd);
						$queryParam = str_repeat('s', count($queryInput));

						$fetchChartData = db_runQuery(array(
							'config_array' => $customerDBMS,
							'database_index' => 0,
							'input' => $queryInput,
							'query' => $queryString,
							'param' => $queryParam,
							'getData' => true,
							'getAllRow' => true
						));
						// pre_dump($fetchChartData);
						// exit(0);

						if(!isEmptyVar($fetchChartData) && $fetchChartData !== 'ZERO_DATA' && count($fetchChartData) >= 1) {
							$presentData = array(
								'temperature' => array(),
								'humidity' => array()
							);

							if(!isEmptyVar($dataRequest['StorageTempHum']['view'])) {
								$isFound = searchArrAssoc($jsonResponse['data']['StorageTempHum']['listView'], 'code', $dataRequest['StorageTempHum']['view']) ?? null;
								if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
									$isFound = strtolower(trim($isFound[0]['name']));
								} else {
									$isFound = 'compact';
								}

								switch($isFound) {
									default:
									case 'compact':
										foreach($fetchChartData as $perRow) {
											if(!array_key_exists($perRow['CS_NAME'], $presentData['temperature']) && !array_key_exists($perRow['CS_NAME'], $presentData['temperature'])) {
												$presentData['temperature'][$perRow['CS_NAME']] = array(
													'labels' => array(),
													'datasets' => array(),
												);
												$presentData['humidity'][$perRow['CS_NAME']] = array(
													'labels' => array(),
													'datasets' => array(),
												);
												for ($i = 0; $i < 24; $i++) {
													$presentData['temperature'][$perRow['CS_NAME']]['labels'][$i] = sprintf('%s:00', str_pad($i, 2, '0', STR_PAD_LEFT));
													$presentData['temperature'][$perRow['CS_NAME']]['datasets'][$i] = array(
														'total' => 0,
														'count' => 0,
													);
													$presentData['humidity'][$perRow['CS_NAME']]['labels'][$i] = sprintf('%s:00', str_pad($i, 2, '0', STR_PAD_LEFT));
													$presentData['humidity'][$perRow['CS_NAME']]['datasets'][$i] = array(
														'total' => 0,
														'count' => 0,
													);
												}
											}
											$hourIn24 = (int) trim(date('H', strtotime($perRow['DATE_PERIOD'])));
											$presentData['temperature'][$perRow['CS_NAME']]['datasets'][$hourIn24]['total'] += (!isEmptyVar($perRow['TEMPERATURE'])) ? (float) $perRow['TEMPERATURE'] : 0;
											$presentData['temperature'][$perRow['CS_NAME']]['datasets'][$hourIn24]['count'] += 1;
											$presentData['humidity'][$perRow['CS_NAME']]['datasets'][$hourIn24]['total'] += (!isEmptyVar($perRow['HUMIDITY'])) ? (float) $perRow['HUMIDITY'] : 0;
											$presentData['humidity'][$perRow['CS_NAME']]['datasets'][$hourIn24]['count'] += 1;
										}
										if(count($presentData['temperature']) >= 1) {
											foreach($presentData['temperature'] as $csName => $perData) {
												if(is_array($perData) && array_key_exists('datasets', $perData)) {
													foreach($perData['datasets'] as $idx => $val) {
														if($val['total'] >= 0 && $val['count'] >= 1) {
															$presentData['temperature'][$csName]['datasets'][$idx] = $val['total'] / $val['count'] ;
														} else {
															$presentData['temperature'][$csName]['datasets'][$idx] = 0;
														}
													}
												}
											}
										}
										if(count($presentData['humidity']) >= 1) {
											foreach($presentData['humidity'] as $csName => $perData) {
												if(is_array($perData) && array_key_exists('datasets', $perData)) {
													foreach($perData['datasets'] as $idx => $val) {
														if($val['total'] >= 0 && $val['count'] >= 1) {
															$presentData['humidity'][$csName]['datasets'][$idx] = $val['total'] / $val['count'] ;
														} else {
															$presentData['humidity'][$csName]['datasets'][$idx] = 0;
														}
													}
												}
											}
										}
										break;
									case 'raw':
										foreach($fetchChartData as $perRow) {
											if(!array_key_exists($perRow['CS_NAME'], $presentData['temperature']) && !array_key_exists($perRow['CS_NAME'], $presentData['humidity'])) {
												$presentData['temperature'][$perRow['CS_NAME']] = array(
													'labels' => array(),
													'datasets' => array(),
												);
												$presentData['humidity'][$perRow['CS_NAME']] = array(
													'labels' => array(),
													'datasets' => array(),
												);
											}
											$labelHourMinute = date('H:i', strtotime($perRow['DATE_PERIOD']));
											if(!in_array($labelHourMinute, $presentData['temperature'][$perRow['CS_NAME']]['labels'])) {
												$presentData['temperature'][$perRow['CS_NAME']]['labels'][] = $labelHourMinute;
												$presentData['temperature'][$perRow['CS_NAME']]['datasets'][] = $perRow['TEMPERATURE'];
											}
											if(!in_array($labelHourMinute, $presentData['humidity'][$perRow['CS_NAME']]['labels'])) {
												$presentData['humidity'][$perRow['CS_NAME']]['labels'][] = $labelHourMinute;
												$presentData['humidity'][$perRow['CS_NAME']]['datasets'][] = $perRow['HUMIDITY'];
											}
										}
										break;
								}

								if(count($presentData['temperature']) >= 1) {
									$jsonResponse['datetime']['StorageTempHum'] = date('Y-m-d H:i:s');
									$jsonResponse['data']['StorageTempHum']['charts']['temperature'] = array();
									foreach($presentData['temperature'] as $csName => $perData) {
										$stableRate = array_filter($perData['datasets'], function($val) { return $val > 0; });
										$jsonResponse['success']['StorageTempHum']['charts']['temperature'] += 1;
										$jsonResponse['data']['StorageTempHum']['charts']['temperature'][] = array(
											'name' => $csName,
											'label' => $perData['labels'],
											'datasets' => $perData['datasets'],
											'stableRate' => (float) sprintf('%.02f', array_sum($stableRate) / count($stableRate)),
										);
									}
									$jsonResponse['took']['StorageTempHum'] = (floor(microtime(true)*1000))-$startTime.'ms';
								}
								if(count($presentData['humidity']) >= 1) {
									$jsonResponse['datetime']['StorageTempHum'] = date('Y-m-d H:i:s');
									$jsonResponse['data']['StorageTempHum']['charts']['humidity'] = array();
									foreach($presentData['humidity'] as $csName => $perData) {
										$stableRate = array_filter($perData['datasets'], function($val) { return $val > 0; });
										$jsonResponse['success']['StorageTempHum']['charts']['humidity'] += 1;
										$jsonResponse['data']['StorageTempHum']['charts']['humidity'][] = array(
											'name' => $csName,
											'label' => $perData['labels'],
											'datasets' => $perData['datasets'],
											'stableRate' => (float) sprintf('%.02f', array_sum($stableRate) / count($stableRate)),
										);
									}
									$jsonResponse['took']['StorageTempHum'] = (floor(microtime(true)*1000))-$startTime.'ms';
								}
							}
						}
					}
					// Serve for: ChamberUsage
					if(boolval($serveData['ChamberUsage']) === true) {
						// Fetch list of available Customer
						$queryString = sprintf('SELECT DISTINCT(CUSTOMER) AS STOREKEY FROM %s;', 'mtp_master_dedicated_storage');
						$fetchAvailableCustomer = db_runQuery(array(
							'config_array' => $customerDBMS,
							'database_index' => 0,
							'query' => $queryString,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchAvailableCustomer) && $fetchAvailableCustomer !== 'ZERO_DATA' && count($fetchAvailableCustomer) >= 1) {
							$jsonResponse['success']['ChamberUsage']['listCustomer'] = 0;
							foreach($fetchAvailableCustomer as $perRow) {
								$getCustomerProfil = db_runQuery(array(
									'config_array' => $configMysql,
									'database_index' => 0, // db.mtp_crm
									'input' => array($perRow['STOREKEY']),
									'query' => sprintf('SELECT NAMA_PERUSAHAAN, META_LOGO, KODE_DC, KODE_CUSTOMER FROM %s WHERE KODE_STOREKEY = ?;', $APP_CORE['tb_prefix'].'user_account'),
									'param' => 's',
									'getData' => true,
									'getAllRow' => false,
									'callback' => function($response) use ($customerDBMS, $perRow, $appConfig) {
										$result = $response['data'];
										$output = array();
										if(!isEmptyVar($result) && $result !== 'ZERO_DATA') {
											$output['name'] = $result['NAMA_PERUSAHAAN'];
											$output['logo'] = sprintf('%s/app/includes/view-document.inc%s?uid=%s', getURI(2), (EXT_PHP) ? '.php' : '', $result['META_LOGO']);
											$output['dc'] = $result['KODE_DC'];
										} else {
											$output = db_runQuery(array(
												'config_array' => $customerDBMS,
												'database_index' => 0,
												'input' => array($perRow['STOREKEY']),
												'query' => sprintf('SELECT KODE_DC, NAMA FROM %s WHERE STOREKEY = ?;', 'mtp_master_dc'),
												'param' => 's',
												'getData' => true,
												'getAllRow' => false,
												'callback' => function($response2) use ($perRow, $appConfig) {
													$result2 = $response2['data'];
													$output2 = array(
														'name' => $perRow['STOREKEY'],
														'logo' => path2url(sprintf('%s/image/illustrations/avatar-%s.png', DIR_ASSET, $appConfig['CORE']['app_build_version'])),
														'dc' => 'MTP001'
													);
													if(!isEmptyVar($result2) && $result2 !== 'ZERO_DATA') {
														$output['name'] = $result2['NAMA'];
														$output['dc'] = $result2['KODE_DC'];
													}
													return $output2;
												}
											));
										}
										return $output;
									}
								));

								$jsonResponse['success']['ChamberUsage']['listCustomer'] += 1;
								$jsonResponse['datetime']['ChamberUsage'] = date('Y-m-d H:i:s');
								$jsonResponse['data']['ChamberUsage']['listCustomer'][] = array(
									'name' => $getCustomerProfil['name'],
									'code' => hash('md5', sprintf('%s%s', $perRow['STOREKEY'], $APP_CORE['app_key_encrypt'])),
									'codeRAW' => $perRow['STOREKEY'],
									'dc' => $getCustomerProfil['dc'],
									'logo' => $getCustomerProfil['logo']
								);
								$jsonResponse['took']['ChamberUsage'] = (floor(microtime(true)*1000))-$startTime.'ms';
							}
						}

						// Summary
						if(!isEmptyVar($dataRequest['ChamberUsage']['customer'])) {
							$isFound = searchArrAssoc($jsonResponse['data']['ChamberUsage']['listCustomer'], 'code', $dataRequest['ChamberUsage']['customer']) ?? null;
							if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
								$jsonResponse['data']['ChamberUsage']['summary']['customer_name'] = $isFound[0]['name']; // Customer Name
								$jsonResponse['data']['ChamberUsage']['summary']['customer_logo'] = $isFound[0]['logo']; // Customer Logo
							} else {
								$jsonResponse['data']['ChamberUsage']['summary']['customer_name'] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['name']; // Customer Name
								$jsonResponse['data']['ChamberUsage']['summary']['customer_logo'] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['logo']; // Customer Logo
							}
						} else {
							$jsonResponse['data']['ChamberUsage']['summary']['customer_name'] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['name']; // Customer Name
							$jsonResponse['data']['ChamberUsage']['summary']['customer_logo'] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['logo']; // Customer Logo
						}

						// Chamber last update storage
						$queryInput = array();
						if(!isEmptyVar($dataRequest['ChamberUsage']['customer'])) {
							$isFound = searchArrAssoc($jsonResponse['data']['ChamberUsage']['listCustomer'], 'code', $dataRequest['ChamberUsage']['customer']) ?? null;
							if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
								$queryInput[] = $isFound[0]['codeRAW']; // Customer STOREKEY
							} else {
								$queryInput[] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['codeRAW']; // Get first customer
							}
						} else {
							$queryInput[] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['codeRAW']; // Get first customer
						}
						$queryString = "SELECT MIN(tgl_upload) AS LAST_UPLOAD FROM mtp_inventory_balance_infor_rep WHERE own = ?;";
						$queryParam = str_repeat('s', count($queryInput));
						$fetchLastUpdate = db_runQuery(array(
							'config_array' => $customerDBMS,
							'database_index' => 0,
							'input' => $queryInput,
							'query' => $queryString,
							'param' => $queryParam,
							'getData' => true,
							'getAllRow' => false
						));
						if(!isEmptyVar($fetchLastUpdate) && $fetchLastUpdate !== 'ZERO_DATA') {
							if(array_key_exists('LAST_UPLOAD', $fetchLastUpdate) && !isEmptyVar($fetchLastUpdate['LAST_UPLOAD'])) {
								$jsonResponse['data']['ChamberUsage']['charts']['lastUpdate'] = date('l, d M Y', strtotime($fetchLastUpdate['LAST_UPLOAD'])) . ' on ' . date('H:i:s', strtotime($fetchLastUpdate['LAST_UPLOAD']));
							}
						}

						// Chamber current usage
						$queryInput = array(
							// $isExistCustomer['STOREKEY'],
							// $isExist['KODE_DC']
						);
						if(!isEmptyVar($dataRequest['ChamberUsage']['customer'])) {
							$isFound = searchArrAssoc($jsonResponse['data']['ChamberUsage']['listCustomer'], 'code', $dataRequest['ChamberUsage']['customer']) ?? null;
							if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
								$queryInput[] = $isFound[0]['codeRAW']; // Customer STOREKEY
								$queryInput[] = $isFound[0]['dc']; // Customer STOREKEY
							} else {
								$queryInput[] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['codeRAW']; // Get first customer
								$queryInput[] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['dc']; // Get first customer
							}
						} else {
							$queryInput[] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['codeRAW']; // Get first customer
							$queryInput[] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['dc']; // Get first customer
						}
						$queryString = "
							SELECT '" . $queryInput[0] . "' CUSTOMER, X.KET CHAMBER, IFNULL(Y.total_storage,0) TOTAL_STORAGE
							FROM
							(
								SELECT DISTINCT ket FROM mtp_master_storage
							) X
							LEFT JOIN
							(
								SELECT z.customer, COUNT(*) total_storage, z.ket CHAMBER
								FROM
								(
									SELECT a.own CUSTOMER, a.location, b.KET
									FROM mtp_inventory_balance_infor_rep a, mtp_master_storage b
									WHERE DATE(a.tgl_cut_off) >= DATE(NOW() - INTERVAL 18 DAY) AND a.own = ? AND b.kode_dc = ? AND a.location = b.lokasi_storage
								) Z
								GROUP BY z.customer, z.ket
							) Y
							ON X.KET = Y.CHAMBER
							ORDER BY X.KET
						";
						$queryParam = str_repeat('s', count($queryInput));
						$fetchChamberUsage = db_runQuery(array(
							'config_array' => $customerDBMS,
							'database_index' => 0,
							'input' => $queryInput,
							'query' => $queryString,
							'param' => $queryParam,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchChamberUsage) && $fetchChamberUsage !== 'ZERO_DATA' && count($fetchChamberUsage) >= 1) {
							$jsonResponse['success']['ChamberUsage'] = true;
							$jsonResponse['message']['ChamberUsage'] = sprintf('Data found (%s) entries!', count($fetchChamberUsage));
							$jsonResponse['datetime']['ChamberUsage'] = date('Y-m-d H:i:s');
							$totalUsage = 0;
							foreach($fetchChamberUsage as $row) {
								$chamberName = strtoupper($row['CHAMBER']);
								if(wordExist($chamberName, 'CHAMBER')) {
									$chamberName = str_replace('CHAMBER', 'CHAMBER ', $chamberName);
								} elseif(wordExist($chamberName, 'CHILLER')) {
									$chamberName = str_replace('CHILLER', 'CHILLER ', $chamberName);
								} elseif(wordExist($chamberName, 'CH')) {
									$chamberName = str_replace('CH', 'CH ', $chamberName);
								} else {
									$chamberName = null;
								}
								if($chamberName !== null) {
									$jsonResponse['data']['ChamberUsage']['labels'][] = $chamberName;
									$jsonResponse['data']['ChamberUsage']['charts']['datasets'][strtolower(str_replace(' ', '_', $chamberName))] = array(
										'dedicated' => 0,
										'dedicated_intersect' => 0,
										'available' => 0,
										'usage' => (int) $row['TOTAL_STORAGE'],
										'over' => 0,
										'over_intersect' => 0,
										'temperature' => array(
											'min' => 0,
											'max' => 0,
										)
									);
								}
								$totalUsage += $row['TOTAL_STORAGE'];
							}
							$jsonResponse['data']['ChamberUsage']['charts']['totalUsage'] = $totalUsage;
							$jsonResponse['data']['ChamberUsage']['summary']['total_usage'] = $totalUsage;
							$jsonResponse['took']['ChamberUsage'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode']['ChamberUsage'] = 0;
						}

						// Chamber customer max storage
						$queryInput = array(
							// $isExistCustomer['STOREKEY'],
						);
						if(!isEmptyVar($dataRequest['ChamberUsage']['customer'])) {
							$isFound = searchArrAssoc($jsonResponse['data']['ChamberUsage']['listCustomer'], 'code', $dataRequest['ChamberUsage']['customer']) ?? null;
							if(!isEmptyVar($isFound) && is_array($isFound) && count($isFound) >= 1) {
								$queryInput[] = $isFound[0]['codeRAW']; // Customer STOREKEY
							} else {
								$queryInput[] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['codeRAW']; // Get first customer
							}
						} else {
							$queryInput[] = $jsonResponse['data']['ChamberUsage']['listCustomer'][0]['codeRAW']; // Get first customer
						}
						$queryString = sprintf('SELECT CHAMBER, JML_STORAGE AS MAX_STORAGE FROM %s WHERE CUSTOMER = ?;', 'mtp_master_dedicated_storage');
						$queryParam = str_repeat('s', count($queryInput));
						$fetchMaxStorage = db_runQuery(array(
							'config_array' => $customerDBMS,
							'database_index' => 0,
							'input' => $queryInput,
							'query' => $queryString,
							'param' => $queryParam,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchMaxStorage) && $fetchMaxStorage !== 'ZERO_DATA' && count($fetchMaxStorage) >= 1) {
							$higherValue = 0;
							$jsonResponse['message']['ChamberUsage'] = sprintf('Data found (%s) entries!', count($fetchChamberUsage));
							foreach($fetchMaxStorage as $row) {
								$chamberName = strtoupper($row['CHAMBER']);
								if(wordExist($chamberName, 'CHAMBER')) {
									$chamberName = str_replace('CHAMBER', 'CHAMBER ', $chamberName);
								} elseif(wordExist($chamberName, 'CHILLER')) {
									$chamberName = str_replace('CHILLER', 'CHILLER ', $chamberName);
								} elseif(wordExist($chamberName, 'CH')) {
									$chamberName = str_replace('CH', 'CH ', $chamberName);
								} else {
									$chamberName = null;
								}
								if($chamberName !== null) {
									// Asphira Andreas <arechta911@gmail.com>
									// Dev Note: Special conditions, because the data is not fixed
									$isSingleName = false;
									if(count(explode(' ', trim($chamberName))) < 2) {
										$isSingleName = true;
										$chamberName = trim($chamberName);
									}
									if(!$isSingleName) {
										$chamberName = str_replace(' ', '_', $chamberName);
									}
									$chamberName = trim(strtolower($chamberName));
									$dedicatedStorage = (int) $row['MAX_STORAGE'];
									if(array_key_exists($chamberName, $jsonResponse['data']['ChamberUsage']['charts']['datasets'])) {
										$chamberSelected = $jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName];
										if($dedicatedStorage !== 0) {
											if($dedicatedStorage >= $chamberSelected['usage']) {
												$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['available'] = $dedicatedStorage - (int) $chamberSelected['usage'];
												$jsonResponse['data']['ChamberUsage']['summary']['total_available'] += $dedicatedStorage - (int) $chamberSelected['usage'];
											}
											if($dedicatedStorage < $chamberSelected['usage']) {
												$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['available'] = 0;
												$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['over'] = (int) $chamberSelected['usage'] - $dedicatedStorage;
												$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['over_intersect'] = 0;
												// $jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['usage'] = $dedicatedStorage;
											}
											$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated'] = $dedicatedStorage;
											$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated_intersect'] = 0;
										} else {
											$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName] = array(
												'dedicated' => 0,
												'dedicated_intersect' => 0,
												'available' => 0,
												'usage' => 0,
												'over' => 0,
												'over_intersect' => 0,
												'temperature' => array(
													'min' => 0,
													'max' => 0
												)
											);
										}
									} else {
										// Asphira Andreas <arechta911@gmail.com>
										// Dev Note: Special conditions, because the data is not fixed
										if($isSingleName && strtoupper($chamberName) === 'CHILLER') {
											$chamberName = 'ch_01';
											if(array_key_exists($chamberName, $jsonResponse['data']['ChamberUsage']['charts']['datasets'])) {
												$chamberSelected = $jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName];
												if($dedicatedStorage !== 0) {
													if($dedicatedStorage >= $chamberSelected['usage']) {
														$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['available'] = $dedicatedStorage - (int) $chamberSelected['usage'];
													}
													if($dedicatedStorage < $chamberSelected['usage']) {
														$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['available'] = 0;
														$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['over'] = (int) $chamberSelected['usage'] - $dedicatedStorage;
														$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['over_intersect'] = 0;
														// $jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['usage'] = $dedicatedStorage;
													}
													$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated'] = $dedicatedStorage;
													$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated_intersect'] = 0;
												} else {
													$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName] = array(
														'dedicated' => 0,
														'dedicated_intersect' => 0,
														'available' => 0,
														'usage' => 0,
														'over' => 0,
														'over_intersect' => 0,
														'temperature' => array(
															'min' => 0,
															'max' => 0,
														)
													);
												}
											}											
										}
									}
								}
							}
							$jsonResponse['data']['ChamberUsage']['charts']['maxUsage'] = $higherValue;
							$jsonResponse['took']['ChamberUsage'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode']['ChamberUsage'] = 0;
						}

						// Chamber dedicated storage
						/*
						$queryString = sprintf('SELECT ket AS CHAMBER, COUNT(*) AS TOTAL_STORAGE FROM %s GROUP BY ket;', 'mtp_master_storage');
						$fetchDedicatedStorage = db_runQuery(array(
							'config_array' => $configMysql,
							'database_index' => 2, // db.mtp
							'query' => $queryString,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchDedicatedStorage) && $fetchDedicatedStorage !== 'ZERO_DATA' && count($fetchDedicatedStorage) >= 1) {
							foreach($fetchDedicatedStorage as $row) {
								$chamberName = strtoupper($row['CHAMBER']);
								if(wordExist($chamberName, 'CHAMBER')) {
									$chamberName = str_replace('CHAMBER', 'CHAMBER ', $chamberName);
								} elseif(wordExist($chamberName, 'CHILLER')) {
									$chamberName = str_replace('CHILLER', 'CHILLER ', $chamberName);
								} elseif(wordExist($chamberName, 'CH')) {
									$chamberName = str_replace('CH', 'CH ', $chamberName);
								} else {
									$chamberName = null;
								}
								if($chamberName !== null) {
									// Asphira Andreas <arechta911@gmail.com>
									// Dev Note: Special conditions, because the data is not fixed
									$isSingleName = false;
									if(count(explode(' ', trim($chamberName))) < 2) {
										$isSingleName = true;
										$chamberName = trim($chamberName);
									}
									if(!$isSingleName) {
										$chamberName = str_replace(' ', '_', $chamberName);
									}
									$chamberName = trim(strtolower($chamberName));
									$dedicatedStorage = (int) $row['TOTAL_STORAGE'];
									if(array_key_exists($chamberName, $jsonResponse['data']['ChamberUsage']['charts']['datasets'])) {
										if($jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated'] === 0) {
											$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated'] = $dedicatedStorage;
											$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['dedicated_intersect'] = $dedicatedStorage;
										}
									}
								}
							}
						}
						*/

						// Chamber temperature
						$queryString = "SELECT * FROM mtp_mesin_temperature GROUP BY KET ASC;";
						$fetchChamberTemperature = db_runQuery(array(
							'config_array' => $customerDBMS,
							'database_index' => 0,
							'query' => $queryString,
							'getData' => true,
							'getAllRow' => true
						));
						if(!isEmptyVar($fetchChamberTemperature) && $fetchChamberTemperature !== 'ZERO_DATA') {
							foreach($fetchChamberTemperature as $row) {
								$chamberName = strtoupper($row['KET']);
								if(wordExist($chamberName, 'CHAMBER')) {
									$chamberName = str_replace('CHAMBER', 'CHAMBER ', $chamberName);
								} elseif(wordExist($chamberName, 'CHILLER')) {
									$chamberName = str_replace('CHILLER', 'CHILLER ', $chamberName);
								} elseif(wordExist($chamberName, 'CH')) {
									$chamberName = str_replace('CH', 'CH ', $chamberName);
								} else {
									$chamberName = null;
								}
								if($chamberName !== null) {
									// Asphira Andreas <arechta911@gmail.com>
									// Dev Note: Special conditions, because the data is not fixed
									$isSingleName = false;
									if(count(explode(' ', trim($chamberName))) < 2) {
										$isSingleName = true;
										$chamberName = trim($chamberName);
									}
									if(!$isSingleName) {
										$chamberName = str_replace(' ', '_', $chamberName);
									}
									$chamberName = trim(strtolower($chamberName));
									if(array_key_exists($chamberName, $jsonResponse['data']['ChamberUsage']['charts']['datasets'])) {
										$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['temperature']['min'] = $row['TEMP_MIN'];
										$jsonResponse['data']['ChamberUsage']['charts']['datasets'][$chamberName]['temperature']['max'] = $row['TEMP_MAX'];
									}
								}
							}
						}
					}

					/*
					 * Finishing response
					 */
					// Serve for: ThroughputIO
					if(boolval($serveData['ThroughputIO']) === true) {
						// Result in JSON
						if(count($jsonResponse['data']['ThroughputIO']['charts']) >= 1) {
							$jsonResponse['success']['ThroughputIO']['charts'] = true;
							$jsonResponse['message']['ThroughputIO']['charts'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['ThroughputIO']['charts']));
							$jsonResponse['errcode']['ThroughputIO']['charts'] = 0;
						}
						if(count($jsonResponse['data']['ThroughputIO']['tables']) >= 1) {
							$jsonResponse['success']['ThroughputIO']['tables'] = true;
							$jsonResponse['message']['ThroughputIO']['tables'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['ThroughputIO']['tables']));
							$jsonResponse['errcode']['ThroughputIO']['tables'] = 0;

							// Sort rows by High value
							usort($jsonResponse['data']['ThroughputIO']['tables'], function($x, $y) {
								return $x['total'] < $y['total'];
							});
						}
					}
					// Serve for: PartnershipCustomer
					if(boolval($serveData['PartnershipCustomer']) === true) {
						// Result in JSON
						if(count($jsonResponse['data']['PartnershipCustomer']) >= 1) {
							$jsonResponse['success']['PartnershipCustomer'] = true;
							$jsonResponse['message']['PartnershipCustomer'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['PartnershipCustomer']));
							$jsonResponse['errcode']['PartnershipCustomer'] = 0;
						}
					}
					// Serve for: ActivitiesTbM
					if(boolval($serveData['ActivitiesTbM']) === true) {
						// Result in JSON
						if(count($jsonResponse['data']['ActivitiesTbM']['charts']['data']) >= 1) {
							$jsonResponse['success']['ActivitiesTbM']['charts'] = true;
							$jsonResponse['message']['ActivitiesTbM']['charts'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['ActivitiesTbM']['charts']['data']));
							$jsonResponse['errcode']['ActivitiesTbM']['charts'] = 0;
						}
						if(count($jsonResponse['data']['ActivitiesTbM']['tables']['data']) >= 1) {
							$jsonResponse['success']['ActivitiesTbM']['tables'] = true;
							$jsonResponse['message']['ActivitiesTbM']['tables'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['ActivitiesTbM']['tables']['data']));
							$jsonResponse['errcode']['ActivitiesTbM']['tables'] = 0;
						}
					}
					// Serve for: StorageTempHum
					if(boolval($serveData['StorageTempHum']) === true) {
						// Result fix JSON
						if(intval($jsonResponse['success']['StorageTempHum']['listPeriod']) >= 1 && count($jsonResponse['data']['StorageTempHum']['listPeriod']['availableDate']) >= 1) {
							$jsonResponse['success']['StorageTempHum']['listPeriod'] = true;
							$jsonResponse['message']['StorageTempHum']['listPeriod'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['StorageTempHum']['listPeriod']['availableDate']));
							$jsonResponse['errcode']['StorageTempHum']['listPeriod'] = 0;
						}
						if(intval($jsonResponse['success']['StorageTempHum']['listCS']) >= 1 && count($jsonResponse['data']['StorageTempHum']['listCS']) >= 1) {
							$jsonResponse['success']['StorageTempHum']['listCS'] = true;
							$jsonResponse['message']['StorageTempHum']['listCS'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['StorageTempHum']['listCS']));
							$jsonResponse['errcode']['StorageTempHum']['listCS'] = 0;
						}
						if(intval($jsonResponse['success']['StorageTempHum']['charts']['temperature']) >= 1 && count($jsonResponse['data']['StorageTempHum']['charts']['temperature']) >= 1) {
							$jsonResponse['success']['StorageTempHum']['charts']['temperature'] = true;
							$jsonResponse['message']['StorageTempHum']['charts']['temperature'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['StorageTempHum']['charts']['temperature']));
							$jsonResponse['errcode']['StorageTempHum']['charts']['temperature'] = 0;
						}
						if(intval($jsonResponse['success']['StorageTempHum']['charts']['humidity']) >= 1 && count($jsonResponse['data']['StorageTempHum']['charts']['humidity']) >= 1) {
							$jsonResponse['success']['StorageTempHum']['charts']['humidity'] = true;
							$jsonResponse['message']['StorageTempHum']['charts']['humidity'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['StorageTempHum']['charts']['humidity']));
							$jsonResponse['errcode']['StorageTempHum']['charts']['humidity'] = 0;
						}
					}
					// Serve for: ChamberUsage
					if(boolval($serveData['ChamberUsage']) === true) {
						// Result in JSON
						if(count($jsonResponse['data']['ChamberUsage']['charts']['datasets']) >= 1) {
							$jsonResponse['success']['ChamberUsage'] = true;
							$jsonResponse['message']['ChamberUsage'] = sprintf('Data found (%s) entries!', count($jsonResponse['data']['ChamberUsage']['charts']['datasets']));
							$jsonResponse['errcode']['ChamberUsage'] = 0;

							// Unset key dont share with client
							foreach($jsonResponse['data']['ChamberUsage']['listCustomer'] as &$perRow) {
								unset($perRow['codeRAW']);
								unset($perRow['dc']);
								unset($perRow['logo']);
							}
						}
					}
				} else {
					$jsonResponse['success'] = false;
					$jsonResponse['message'] = 'You are not part of the Account scope!';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 3;
					unset($jsonResponse['errors']);
				}
			} else {
				$jsonResponse['success'] = false;
				$jsonResponse['message'] = 'You\'re not logged-in!';
				$jsonResponse['datetime'] = date('Y-m-d H:i:s');
				$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
				$jsonResponse['errcode'] = 2;
				unset($jsonResponse['errors']);
			}
		break;
		case 'update-dc-list':
			/*
			 * Stage: 1
			 * Check if the user is online and has a NIK
			 */
			if(!isEmptyVar($userData->nik)) {
				/*
				 * Stage: 2 A
				 * Check if the account is registered with the current NIK
				 */
				$isExist = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik),
					'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) as TOTAL, NAMA, NAMA_PERUSAHAAN, KODE_STOREKEY FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
					'param' => 's',
					'getData' => true,
					'getAllRow' => false
				));

				/*
				 * Stage: 2 B
				 * Check if the account is had access to some privileges
				 */
				$isPartPrivileges = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik, 1, 2, 'cs', 'ho'),
					'query' => sprintf('SELECT * FROM %s WHERE NIK = ? AND ((LEVEL = ? OR LEVEL = ?) OR (LEVEL_CODE = ? OR LEVEL_CODE = ?));', $APP_CORE['tb_prefix'].'user_privileges'),
					'param' => 'sssss',
					'getData' => true,
					'getAllRow' => false
				));
				if(isset($isExist['TOTAL']) && $isExist['TOTAL'] >= 1 && !isEmptyVar($isPartPrivileges) && $isPartPrivileges !== 'ZERO_DATA') {
					// Validate incomming data
					if(!isEmptyVar($dataRequest)) {
						$data = $dataRequest;
						$passData = 0;
						$errorData = array();
						$totalValidate = 1;

						if(!isEmptyVar($data) && strlen($data) >= 3) {
							$listSelected = explode(',', $data);
							$filterExist = array();
							if($listSelected[0] === 'all') {
								$filterExist = array('all');
							} else {
								foreach($listSelected as $perDC) {
									$isExistDC = db_runQuery(array(
										'config_array' => $configMysql,
										'database_index' => 1, // db.mtp_central
										'input' => array($perDC, 1),
										'query' => sprintf('SELECT COUNT(DISTINCT(KODE_DC)) as FOUND FROM %s WHERE KODE_DC = ? AND AKTIF_DC  = ?;', 'mtp_master_dc'),
										'param' => 'si',
										'getData' => true,
										'getAllRow' => false
									));
									if(isset($isExistDC['FOUND']) && (int) $isExistDC['FOUND'] >= 1) {
										$filterExist[] = $perDC;
									}
								}
							}
							if(count($filterExist) >= 1) {
								$passData += 1;
								$data = $filterExist;
							}
						} else {
							$errorData[] = 'data';
						}

						// Next step to process the data
						if($passData >= $totalValidate) {
							$sessionKey = $APP_CORE['session_prefix'].'user-filter';
							if(!array_key_exists($sessionKey, $_SESSION)) {
								$_SESSION[$sessionKey] = array(
									'dc' => $data
								);
								$user->registerSessionKey($sessionKey);
							} else {
								$_SESSION[$sessionKey]['dc'] = $data;
							}

							// Query: Get all Customer list, without prefix 'MTP'
							$sessionRegistered = $user->takeSessionKey();
							$sessionData = array();
							if(is_array($sessionRegistered)) {
								$jsonResponse['data'][] = array(
									'code' => 'all',
									'name' => 'All'
								);
								if(in_array($sessionKey, $sessionRegistered)) {
									$sessionData = $_SESSION[$sessionKey];
									$isCustomerSelected = false;
									if(array_key_exists('customer', $sessionData) && is_array($sessionData['customer']) && count($sessionData['customer']) >= 1) {
										if($sessionData['customer'][0] !== 'all') {
											$isCustomerSelected = true;
										}
									}
									if(array_key_exists('dc', $sessionData) && is_array($sessionData['dc']) && count($sessionData['dc']) >= 1) {
										if(trim(strtolower($sessionData['dc'][0])) === 'all') {
											$listAllDC = db_runQuery(array(
												'config_array' => $configMysql,
												'database_index' => 1,
												'input' => array('MTP%', 1, 1),
												// 'query' => sprintf('SELECT KODE_DC, NAMA, DB_NAME FROM %s WHERE KODE_DC LIKE ? AND AKTIF_DC = ?;', 'mtp_master_dc'),
												'query' => sprintf('SELECT KODE_DC, NAMA, DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME FROM %s WHERE KODE_DC IN (SELECT KODE_DC FROM %s WHERE KODE_DC LIKE ? AND AKTIF_DC = ?) AND FLAG = ?;', 'mtp_master_koneksi', 'mtp_master_dc'),
												'param' => 'sii',
												'getData' => true,
												'getAllRow' => true
											));
											if(!isEmptyVar($listAllDC) && $listAllDC !== 'ZERO_DATA' && count($listAllDC) >= 1) {
												$filterExistCustomer = array();
												foreach($listAllDC as $perDC) {
													$customerDBMS = array(
														'mysql_host' => $perDC['DB_HOST'],
														'mysql_username' => $perDC['DB_USER'],
														'mysql_password' => (isEmptyVar($perDC['DB_PASS'])) ? '' : $perDC['DB_PASS'],
														'mysql_database' => array( $perDC['DB_NAME'] )
													);
	
													$queryInput = array('MTP%', 1);
													$queryString = "SELECT * FROM mtp_master_dc WHERE KODE_DC NOT LIKE ? AND AKTIF = ?;";
													$queryParam = str_repeat('s', count($queryInput));

													$fetchCustomerList = db_runQuery(array(
														'config_array' => $customerDBMS,
														'database_index' => 0,
														'input' => $queryInput,
														'query' => $queryString,
														'param' => $queryParam,
														'getData' => true,
														'getAllRow' => true
													));
													if(!isEmptyVar($fetchCustomerList) && $fetchCustomerList !== 'ZERO_DATA' && count($fetchCustomerList) >= 1) {
														foreach($fetchCustomerList as $idx => $row) {
															$jsonResponse['data'][] = array(
																'code' => sprintf('%s.%s', $perDC['KODE_DC'], $row['KODE_DC']),
																'parent' => ucwords(trim(str_replace(['DC', 'MTP'], ['', ''], $perDC['NAMA']))),
																'name' => $row['NAMA'],
																'selected' => ($isCustomerSelected) ? ((in_array(sprintf('%s.%s', $perDC['KODE_DC'], $row['KODE_DC']), $sessionData['customer'])) ? true : false) : false
															);
															$idxLast = count($jsonResponse['data']) - 1;
															if($jsonResponse['data'][$idxLast]['selected']) {
																$filterExistCustomer[] = sprintf('%s.%s', $perDC['KODE_DC'], $row['KODE_DC']);
															}
														}
													}
												}
												if(is_array($sessionData['customer']) && count($sessionData['customer']) >= 1 && $sessionData['customer'][0] !== 'all') {
													foreach($sessionData['customer'] as $idx => $val) {
														if(!in_array($val, $filterExistCustomer)) {
															unset($sessionData['customer'][$idx]);
														}
													}
													if(count($sessionData['customer']) === 0) {
														$sessionData['customer'] = array('all');
													}
													$_SESSION[$sessionKey] = $sessionData;
												}
											}
										} else {
											$listFilterDC = db_runQuery(array(
												'config_array' => $configMysql,
												'database_index' => 1,
												'input' => array_merge($sessionData['dc'], array(1, 1)),
												// 'query' => sprintf('SELECT KODE_DC, NAMA, DB_NAME FROM %s WHERE KODE_DC IN (%s) AND AKTIF_DC = ?;', 'mtp_master_dc', implode(',', array_fill(0, count($sessionData['dc']), '?'))),
												'query' => sprintf('SELECT KODE_DC, NAMA, DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME FROM %s WHERE KODE_DC IN (SELECT KODE_DC FROM %s WHERE KODE_DC IN (%s) AND AKTIF_DC = ?) AND FLAG = ?;', 'mtp_master_koneksi', 'mtp_master_dc', implode(',', array_fill(0, count($sessionData['dc']), '?'))),
												'param' => str_repeat('s', count($sessionData['dc']) + 2),
												'getData' => true,
												'getAllRow' => true
											));
											if(!isEmptyVar($listFilterDC) && $listFilterDC !== 'ZERO_DATA' && count($listFilterDC) >= 1) {
												$filterExistCustomer = array();
												foreach($listFilterDC as $perDC) {
													$customerDBMS = array(
														'mysql_host' => $perDC['DB_HOST'],
														'mysql_username' => $perDC['DB_USER'],
														'mysql_password' => (isEmptyVar($perDC['DB_PASS'])) ? '' : $perDC['DB_PASS'],
														'mysql_database' => array( $perDC['DB_NAME'] )
													);

													$queryInput = array('MTP%', 1);
													$queryString = "SELECT * FROM mtp_master_dc WHERE KODE_DC NOT LIKE ? AND AKTIF = ?;";
													$queryParam = str_repeat('s', count($queryInput));

													$fetchCustomerList = db_runQuery(array(
														'config_array' => $customerDBMS,
														'database_index' => 0,
														'input' => $queryInput,
														'query' => $queryString,
														'param' => $queryParam,
														'getData' => true,
														'getAllRow' => true
													));
													if(!isEmptyVar($fetchCustomerList) && $fetchCustomerList !== 'ZERO_DATA' && count($fetchCustomerList) >= 1) {
														foreach($fetchCustomerList as $idx => $row) {
															$jsonResponse['data'][] = array(
																'code' => sprintf('%s.%s', $perDC['KODE_DC'], $row['KODE_DC']),
																'parent' => ucwords(trim(str_replace(['DC', 'MTP'], ['', ''], $perDC['NAMA']))),
																'name' => $row['NAMA'],
																'selected' => ($isCustomerSelected) ? ((in_array(sprintf('%s.%s', $perDC['KODE_DC'], $row['KODE_DC']), $sessionData['customer'])) ? true : false) : false
															);
															$idxLast = count($jsonResponse['data']) - 1;
															if($jsonResponse['data'][$idxLast]['selected']) {
																$filterExistCustomer[] = sprintf('%s.%s', $perDC['KODE_DC'], $row['KODE_DC']);
															}
														}
													}
												}
												if(is_array($sessionData['customer']) && count($sessionData['customer']) >= 1 && $sessionData['customer'][0] !== 'all') {
													foreach($sessionData['customer'] as $idx => $val) {
														if(!in_array($val, $filterExistCustomer)) {
															unset($sessionData['customer'][$idx]);
														}
													}
													if(count($sessionData['customer']) === 0) {
														$sessionData['customer'] = array('all');
													}
													$_SESSION[$sessionKey] = $sessionData;
												}
											}
										}
									}

									// Remove duplicate Unique ID
									if(count($jsonResponse['data']) >= 2) { // 2 rows, because first is 'All'
										$jsonResponse['data'] = uniqueAssocByKey($jsonResponse['data'], 'code');
									}

									$jsonResponse['success'] = ((count($jsonResponse['data']) - 1) !== 0) ? true : false;
									$jsonResponse['message'] = sprintf('Data found (%s) entries!', count($jsonResponse['data'])-1);
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = ((count($jsonResponse['data']) - 1) !== 0) ? 0 : 1;
								} else {
									$jsonResponse['success'] = false;
									$jsonResponse['message'] = 'Error session not Exist';
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									unset($jsonResponse['data']);
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 6;
									unset($jsonResponse['errors']);
								}
							}
						} else {
							$jsonResponse['success'] = false;
							$jsonResponse['message'] = sprintf('The following data (%) is empty or not met minimum requirement!', implode(',', $errorData));
							$jsonResponse['datetime'] = date('Y-m-d H:i:s');
							unset($jsonResponse['data']);
							$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode'] = 5;
							unset($jsonResponse['errors']);
						}
					} else {
						$jsonResponse['success'] = false;
						$jsonResponse['message'] = 'Data is invalid or not has required data!';
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						unset($jsonResponse['data']);
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 4;
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
		case 'update-customer-list':
			/*
			 * Stage: 1
			 * Check if the user is online and has a NIK
			 */
			if(!isEmptyVar($userData->nik)) {
				/*
				 * Stage: 2 A
				 * Check if the account is registered with the current NIK
				 */
				$isExist = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik),
					'query' => sprintf('SELECT COUNT(DISTINCT(NIK)) as TOTAL, NAMA, NAMA_PERUSAHAAN, KODE_STOREKEY FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
					'param' => 's',
					'getData' => true,
					'getAllRow' => false
				));

				/*
				 * Stage: 2 B
				 * Check if the account is had access to some privileges
				 */
				$isPartPrivileges = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($userData->nik, 1, 2, 'cs', 'ho'),
					'query' => sprintf('SELECT * FROM %s WHERE NIK = ? AND ((LEVEL = ? OR LEVEL = ?) OR (LEVEL_CODE = ? OR LEVEL_CODE = ?));', $APP_CORE['tb_prefix'].'user_privileges'),
					'param' => 'sssss',
					'getData' => true,
					'getAllRow' => false
				));
				if(isset($isExist['TOTAL']) && $isExist['TOTAL'] >= 1 && !isEmptyVar($isPartPrivileges) && $isPartPrivileges !== 'ZERO_DATA') {
					// Validate incomming data
					if(!isEmptyVar($dataRequest)) {
						$data = $dataRequest;
						$passData = 0;
						$errorData = [];
						$totalValidate = 1;

						if(!isEmptyVar($data) && strlen($data) >= 3) {
							$listSelected = explode(',', $data);
							$filterExist = array();
							if($listSelected[0] === 'all') {
								$filterExist = array('all');
							} else {
								foreach($listSelected as $perCustomer) {
									list($codeDC, $codeCustomer) = array_pad(explode('.', $perCustomer), 2, NULL);
									if(!isEmptyVar($codeDC) && !isEmptyVar($codeCustomer)) {
										$isExistDC = db_runQuery(array(
											'config_array' => $configMysql,
											'database_index' => 1,
											'input' => array($codeDC, 1, 1),
											'query' => sprintf('SELECT COUNT(DISTINCT(KODE_DC)) as FOUND, KODE_DC, NAMA, DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME FROM %s WHERE KODE_DC IN (SELECT KODE_DC FROM %s WHERE KODE_DC = ? AND AKTIF_DC = ?) AND FLAG = ?;', 'mtp_master_koneksi', 'mtp_master_dc'),
											'param' => 'sii',
											'getData' => true,
											'getAllRow' => false
										));
										if(isset($isExistDC['FOUND']) && (int) $isExistDC['FOUND'] >= 1) {
											$customerDBMS = array(
												'mysql_host' => $isExistDC['DB_HOST'],
												'mysql_username' => $isExistDC['DB_USER'],
												'mysql_password' => (isEmptyVar($isExistDC['DB_PASS'])) ? '' : $isExistDC['DB_PASS'],
												'mysql_database' => array( $isExistDC['DB_NAME'] )
											);

											$isExistCustomer = db_runQuery(array(
												'config_array' => $customerDBMS,
												'database_index' => 0,
												'input' => array($codeCustomer),
												'query' => sprintf('SELECT COUNT(DISTINCT(KODE_DC)) as FOUND FROM %s WHERE KODE_DC = ?;', 'mtp_master_dc'),
												'param' => 's',
												'getData' => true,
												'getAllRow' => false
											));
											if(isset($isExistCustomer['FOUND']) && (int) $isExistCustomer['FOUND'] >= 1) {
												$filterExist[] = $perCustomer;
											}
										}
									}
								}
							}
							if(count($filterExist) >= 1) {
								$passData += 1;
								$data = $filterExist;
							}
						} else {
							$errorData[] = 'data';
						}

						// Next step to process the data
						if($passData >= $totalValidate) {
							$sessionKey = $APP_CORE['session_prefix'] . 'user-filter';
							if(!array_key_exists($sessionKey, $_SESSION)) {
								$_SESSION[$sessionKey] = array(
									'customer' => $data
								);
								$user->registerSessionKey($sessionKey);
							} else {
								$_SESSION[$sessionKey]['customer'] = $data;
							}

							$sessionRegistered = $user->takeSessionKey();
							$sessionData = array();
							if(is_array($sessionRegistered)) {
								if(in_array($sessionKey, $sessionRegistered)) {
									$sessionData = $_SESSION[$sessionKey];
									if(array_key_exists('dc', $sessionData) && is_array($sessionData['dc']) && count($sessionData['dc']) >= 1 && array_key_exists('customer', $sessionData) && is_array($sessionData['customer']) && count($sessionData['customer']) >= 1) {
										if(trim(strtolower($sessionData['dc'][0])) !== 'all') {
											if(trim(strtolower($sessionData['customer'][0])) !== 'all') {
												foreach($sessionData['customer'] as $idx => $perCustomer) {
													list($codeDC, $codeCustomer) = array_pad(explode('.', $perCustomer), 2, NULL);
													if(!isEmptyVar($codeDC) && !isEmptyVar($codeCustomer)) {
														if(!in_array($codeDC, $sessionData['dc'])) {
															unset($sessionData['customer'][$idx]);
														}
													}
												}
											}
										}
									}

									$jsonResponse['success'] = true;
									$jsonResponse['message'] = 'Success!';
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									unset($jsonResponse['data']);
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 0;
									unset($jsonResponse['errors']);
								} else {
									$jsonResponse['success'] = false;
									$jsonResponse['message'] = 'Error session not Exist';
									$jsonResponse['datetime'] = date('Y-m-d H:i:s');
									unset($jsonResponse['data']);
									$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
									$jsonResponse['errcode'] = 6;
									unset($jsonResponse['errors']);
								}
							}
						} else {
							$jsonResponse['success'] = false;
							$jsonResponse['message'] = sprintf('The following data (%s) is empty or not met minimum requirement!', implode(',', $errorData));
							$jsonResponse['datetime'] = date('Y-m-d H:i:s');
							unset($jsonResponse['data']);
							$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
							$jsonResponse['errcode'] = 5;
							unset($jsonResponse['errors']);
						}
					} else {
						$jsonResponse['success'] = false;
						$jsonResponse['message'] = 'Data is invalid or not has required data!';
						$jsonResponse['datetime'] = date('Y-m-d H:i:s');
						unset($jsonResponse['data']);
						$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
						$jsonResponse['errcode'] = 4;
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
	}
	header('Content-Type: application/json');
	echo json_encode($jsonResponse, JSON_UNESCAPED_UNICODE);
	exit(0);
} else {
	header('Location: ../index.php');
}