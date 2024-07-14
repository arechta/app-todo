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
// use ARE\includes\classes\User;
// use ARE\includes\classes\EncryptionVW;
// use PhpParser\Node\Stmt\Break_;
// use PHPUnit\Framework\Constraint\IsEmpty;

// if(session_status() === PHP_SESSION_NONE) { session_start(array('cookie_lifetime' => 0, 'cookie_path' => '/', 'cookie_httponly' => '1')); }
$jsonResponse = array(
	'success' => false,
	'message' => '',
	'datetime' => date('Y-m-d H:i:s'),
	'data' => null,
	'took' => '0ms',
	'errcode' => 1,
	'errors' => array()
);
// $user = new User();
// $EVW = new EncryptionVW();
// $databaseList = $APP_CORE['db_name'];

// $userData = array('stt', 'nik', 'tkn', 'usr');
// foreach($userData as $idx => $perData) {
// 	$userData[$perData] = $user->getSession($perData);
// 	$userData[$perData] = ($userData[$perData]['success']) ? $userData[$perData]['data'] : null;
// 	unset($userData[$idx]);
// }
// $userData = arr2Obj($userData);

if(!isEmptyVar($_POST['ajax']) && ($_POST['ajax'] === 'true' || $_POST['ajax'] === true)) {
	$startTime = floor(microtime(true)*1000);
	// Default request
	$actionType = (isset($_POST['action'])) ? ((!isEmptyVar($_POST['action'])) ? trim($_POST['action']) : false) : false;
	$methodType = (isset($_POST['method'])) ? ((!isEmptyVar($_POST['method'])) ? strtolower(trim($_POST['method'])) : null) : null;
	$dataRequest = (isset($_POST['data'])) ? ((!isEmptyVar($_POST['data'])) ? $_POST['data'] : null) : null;
	$confirmAction = (isset($_POST['confirm'])) ? ((!isEmptyVar($_POST['confirm'])) ? $_POST['confirm'] : null) : null;
	// Specific request
	$period = (isset($_POST['period'])) ? ((!isEmptyVar($_POST['period'])) ? $_POST['period'] : null) : null;

	if(!isEmptyVar($period)) {
		$listPeriod = explode('-', $period);
		if(count($listPeriod) == 2) {
			$period .= '-01';
		}
	} else {
		$period = date('Y-m-01');
	}

	switch ($actionType) {
		case 'list-cards':
			$queryString = "SELECT A.id_list, A.name AS name_list, B.id_card, B.name AS name_card, B.due_date, B.priority AS priority_card, B.status AS status_card FROM master_list A LEFT JOIN master_card B ON A.id_list = B.id_list;";
			$fetchData = db_runQuery(array(
				'config_array' => $configMysql,
				'database_index' => 0,
				'query' => $queryString,
				'getData' => true,
				'getAllRow' => true
			));
			if (!isEmptyVar($fetchData) && $fetchData !== 'ZERO_DATA' && count($fetchData) >= 1) {
				$jsonResponse['success'] = true;
				$jsonResponse['data'] = array();
				foreach($fetchData as $perRow) {
					if (!array_key_exists($perRow['id_list'], $jsonResponse['data'])) {
						$jsonResponse['data'][$perRow['id_list']] = array(
							'id_list' => $perRow['id_list'],
							'list_name' => $perRow['name_list'],
							'cards' => array()
						);
					}
					if (!isEmptyVar($perRow['id_card'])) {
						$jsonResponse['data'][$perRow['id_list']]['cards'][] = array(
							'id' => $perRow['id_card'],
							'name' => $perRow['name_card'],
							'due_date' => (!isEmptyVar($perRow['due_date'])) ? date('M d, Y', strtotime($perRow['due_date'])) : null,
							'priority' => $perRow['priority_card'],
							'status' => $perRow['status_card'],
						);
					}
				}

				if ($jsonResponse['success']) {
					$jsonResponse['message'] = 'Data found';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					$jsonResponse['data'] = array_values($jsonResponse['data']);
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 0;
					unset($jsonResponse['errors']);
				}
			}
			
		break;
		case 'list-create':
			if (!isEmptyVar($dataRequest) && isJSON($dataRequest)) {
				$dataRequest = json_decode($dataRequest, true);

				/*
				 * Task: Generate ID
				 * Format:
				 * [LIST] = for Prefix
				 * [YY] = 2 digit years
				 * [MM] = 2 digit month
				 * [XXX] = 3 digit Auto Increment
				 */
				$lastID = db_runQuery([
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => 'LIST'.date('ym').'%',
					'query' => sprintf('SELECT MAX(id_list) as id_list FROM %s WHERE id_list LIKE ?;', 'master_list'),
					'param' => 's',
					'getData' => true
				]);
				$newID = null;
				if($lastID != 'ZERO_DATA' && !is_null($lastID['id_list'])) {
					$prefixID = substr($lastID['id_list'], 0, 8); // Ambil 5 digit dari depan index 0 = [LIST2407]001
					$suffixID = (int) substr($lastID['id_list'], 8, 3); // Ambil 3 digit dari depan index 8 = LIST2407[001]
					$newID = sprintf("%s%03s", $prefixID, ++$suffixID);
				} else {
					$newID = 'LIST'.date('ym').'001';
				}

				// Insert new row
				$queryString = "INSERT INTO master_list (id_list, name, created_date) VALUES (?, ?, ?);";
				$insertData = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($newID, trim($dataRequest['listTitle']), date('Y-m-d H:i:s')),
					'query' => $queryString,
					'param' => 'sss',
					'getData' => false,
					'getAllRow' => false
				));
				if ($insertData) {
					$jsonResponse['success'] = true;
					$jsonResponse['message'] = 'Data created!';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					$jsonResponse['data'] = array(
						'id' => $newID,
						'title' => trim($dataRequest['listTitle']),
					);
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 0;
					unset($jsonResponse['errors']);
				} else {
					$jsonResponse['message'] = 'Failed to created!';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					unset($jsonResponse['data']);
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 1;
					// unset($jsonResponse['errors']);
				}
			}
		break;
		case 'list-add-card':
			if (!isEmptyVar($dataRequest) && isJSON($dataRequest)) {
				$dataRequest = json_decode($dataRequest, true);

				/*
				 * Task: Generate ID
				 * Format:
				 * [CARD] = for Prefix
				 * [YY] = 2 digit years
				 * [MM] = 2 digit month
				 * [XXX] = 3 digit Auto Increment
				 */
				$lastID = db_runQuery([
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => 'CARD'.date('ym').'%',
					'query' => sprintf('SELECT MAX(id_card) as id_card FROM %s WHERE id_card LIKE ?;', 'master_card'),
					'param' => 's',
					'getData' => true
				]);
				$newID = null;
				if($lastID != 'ZERO_DATA' && !is_null($lastID['id_card'])) {
					$prefixID = substr($lastID['id_card'], 0, 8); // Ambil 5 digit dari depan index 0 = [CARD2407]001
					$suffixID = (int) substr($lastID['id_card'], 8, 3); // Ambil 3 digit dari depan index 8 = CARD2407[001]
					$newID = sprintf("%s%03s", $prefixID, ++$suffixID);
				} else {
					$newID = 'CARD'.date('ym').'001';
				}

				// Insert new row
				$queryString = "INSERT INTO master_card (id_card, id_list, name, created_date) VALUES (?, ?, ?, ?);";
				$insertData = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($newID, trim($dataRequest['listID']), trim($dataRequest['cardTitle']), date('Y-m-d H:i:s')),
					'query' => $queryString,
					'param' => 'ssss',
					'getData' => false,
					'getAllRow' => false
				));
				if ($insertData) {
					$jsonResponse['success'] = true;
					$jsonResponse['message'] = 'Data created!';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					$jsonResponse['data'] = array(
						'id' => $newID,
						'title' => trim($dataRequest['cardTitle']),
					);
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 0;
					unset($jsonResponse['errors']);
				} else {
					$jsonResponse['message'] = 'Failed to created!';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					unset($jsonResponse['data']);
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 1;
					// unset($jsonResponse['errors']);
				}
			}
			
		break;
		case 'card-details':
			if (!isEmptyVar($dataRequest) && isJSON($dataRequest)) {
				$dataRequest = json_decode($dataRequest, true);

				if (array_key_exists('cardID', $dataRequest) && !isEmptyVar($dataRequest['cardID'])) {
					// Fetch data Card-item
					$queryString = "SELECT * FROM master_card WHERE id_card = ?;";
					$fetchDataCard = db_runQuery(array(
						'config_array' => $configMysql,
						'database_index' => 0,
						'input' => array(trim($dataRequest['cardID'])),
						'query' => $queryString,
						'param' => 's',
						'getData' => true,
						'getAllRow' => false
					));
					if (!isEmptyVar($fetchDataCard) && $fetchDataCard !== 'ZERO_DATA') {
						$jsonResponse['success'] = true;
						$jsonResponse['data'] = array(
							'id' => $fetchDataCard['id_card'],
							'name' => $fetchDataCard['name'],
							'description' => $fetchDataCard['description'],
							'startDate' => (!isEmptyVar($fetchDataCard['start_date'])) ? date('Y-m-d', strtotime($fetchDataCard['start_date'])) : null,
							'dueDate' => (!isEmptyVar($fetchDataCard['due_date'])) ? date('Y-m-d', strtotime($fetchDataCard['due_date'])) : null,
							'priority' => $fetchDataCard['priority'],
							'status' => $fetchDataCard['status'],
							'createdDate' => $fetchDataCard['created_date'],
							'parent' => array(
								'id' => $fetchDataCard['id_list'],
								'name' => db_runQuery(array(
									'config_array' => $configMysql,
									'database_index' => 0,
									'input' => array($fetchDataCard['id_list']),
									'query' => 'SELECT * FROM master_list WHERE id_list = ?;',
									'param' => 's',
									'getData' => true,
									'getAllRow' => false,
									'callback' => function($response) {
										$output = $response['data'];
										$result = '-';
										if (!isEmptyVar($output) && $output !== 'ZERO_DATA') {
											if (!isEmptyVar($output['name'])) {
												$result = $output['name'];
											}
										}
										return $result;
									},
								)),
							),
							'taskItems' => array(),
						);

						// Fetch data Task-item
						$queryString = "SELECT * FROM master_task WHERE id_card = ?;";
						$fetchDataTask = db_runQuery(array(
							'config_array' => $configMysql,
							'database_index' => 0,
							'input' => array(trim($dataRequest['cardID'])),
							'query' => $queryString,
							'param' => 's',
							'getData' => true,
							'getAllRow' => true
						));
						if (!isEmptyVar($fetchDataTask) && $fetchDataTask !== 'ZERO_DATA' && count($fetchDataTask) >= 1) {
							foreach($fetchDataTask as $perTask) {
								$jsonResponse['data']['taskItems'][] = array(
									'id' => $perTask['id_task'],
									'name' => $perTask['name'],
									'isDone' => $perTask['is_done'],
									'createdDate' => $perTask['created_date']
								);
							}
						}
					}
				}

				if ($jsonResponse['success']) {
					$jsonResponse['message'] = 'Data found';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 0;
					unset($jsonResponse['errors']);
				}
			}
		break;
		case 'task-create':
			if (!isEmptyVar($dataRequest) && isJSON($dataRequest)) {
				$dataRequest = json_decode($dataRequest, true);

				/*
				 * Task: Generate ID
				 * Format:
				 * [TASK] = for Prefix
				 * [YY] = 2 digit years
				 * [MM] = 2 digit month
				 * [XXX] = 3 digit Auto Increment
				 */
				$lastID = db_runQuery([
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => 'TASK'.date('ym').'%',
					'query' => sprintf('SELECT MAX(id_task) as id_task FROM %s WHERE id_task LIKE ?;', 'master_task'),
					'param' => 's',
					'getData' => true
				]);
				$newID = null;
				if($lastID != 'ZERO_DATA' && !is_null($lastID['id_task'])) {
					$prefixID = substr($lastID['id_task'], 0, 8); // Ambil 5 digit dari depan index 0 = [TASK2407]001
					$suffixID = (int) substr($lastID['id_task'], 8, 3); // Ambil 3 digit dari depan index 8 = TASK2407[001]
					$newID = sprintf("%s%03s", $prefixID, ++$suffixID);
				} else {
					$newID = 'TASK'.date('ym').'001';
				}

				// Insert new row
				$queryString = "INSERT INTO master_task (id_task, id_card, name, created_date) VALUES (?, ?, ?, ?);";
				$insertData = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($newID, trim($dataRequest['cardID']), trim($dataRequest['taskTitle']), date('Y-m-d H:i:s')),
					'query' => $queryString,
					'param' => 'ssss',
					'getData' => false,
					'getAllRow' => false
				));
				if ($insertData) {
					$jsonResponse['success'] = true;
					$jsonResponse['message'] = 'Data created!';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					$jsonResponse['data'] = array(
						'id' => $newID,
						'title' => trim($dataRequest['taskTitle']),
					);
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 0;
					unset($jsonResponse['errors']);
				} else {
					$jsonResponse['message'] = 'Failed to created!';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					unset($jsonResponse['data']);
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 1;
					// unset($jsonResponse['errors']);
				}
			}
		break;
		case 'task-update':
			if (!isEmptyVar($dataRequest) && isJSON($dataRequest)) {
				$dataRequest = json_decode($dataRequest, true);

				if (array_key_exists('cardID', $dataRequest) && !isEmptyVar($dataRequest['cardID']) && array_key_exists('taskID', $dataRequest) && !isEmptyVar($dataRequest['taskID']) && array_key_exists('taskTitle', $dataRequest) && !isEmptyVar($dataRequest['taskTitle'])) {
					// Fetch data Card-item (Check if exist)
					$queryString = "SELECT * FROM master_card WHERE id_card = ?;";
					$fetchDataCard = db_runQuery(array(
						'config_array' => $configMysql,
						'database_index' => 0,
						'input' => array(trim($dataRequest['cardID'])),
						'query' => $queryString,
						'param' => 's',
						'getData' => true,
						'getAllRow' => false
					));
					if (!isEmptyVar($fetchDataCard) && $fetchDataCard !== 'ZERO_DATA') {
						// Fetch data Task-item
						$queryString = "UPDATE master_task SET name = ? WHERE id_task = ? AND id_card = ?;";
						$updateDataTask = db_runQuery(array(
							'config_array' => $configMysql,
							'database_index' => 0,
							'input' => array(trim($dataRequest['taskTitle']), trim($dataRequest['taskID']), trim($dataRequest['cardID'])),
							'query' => $queryString,
							'param' => 'sss',
							'getData' => false,
							'getAllRow' => false
						));
						if ($updateDataTask) {
							$jsonResponse['success'] = true;
						}
					}
				}

				if ($jsonResponse['success']) {
					$jsonResponse['message'] = 'Data updated';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 0;
					unset($jsonResponse['errors']);
				}
			}
		break;
		case 'task-update-check':
			if (!isEmptyVar($dataRequest) && isJSON($dataRequest)) {
				$dataRequest = json_decode($dataRequest, true);

				if (array_key_exists('cardID', $dataRequest) && !isEmptyVar($dataRequest['cardID']) && array_key_exists('taskID', $dataRequest) && !isEmptyVar($dataRequest['taskID']) && array_key_exists('taskFlag', $dataRequest)) {
					// Fetch data Card-item (Check if exist)
					$queryString = "SELECT * FROM master_card WHERE id_card = ?;";
					$fetchDataCard = db_runQuery(array(
						'config_array' => $configMysql,
						'database_index' => 0,
						'input' => array(trim($dataRequest['cardID'])),
						'query' => $queryString,
						'param' => 's',
						'getData' => true,
						'getAllRow' => false
					));
					if (!isEmptyVar($fetchDataCard) && $fetchDataCard !== 'ZERO_DATA') {
						// Update data Task-item status
						$queryString = "UPDATE master_task SET is_done = ? WHERE id_task = ? AND id_card = ?;";
						$updateDataTask = db_runQuery(array(
							'config_array' => $configMysql,
							'database_index' => 0,
							'input' => array(boolval($dataRequest['taskFlag']), trim($dataRequest['taskID']), trim($dataRequest['cardID'])),
							'query' => $queryString,
							'param' => 'sss',
							'getData' => false,
							'getAllRow' => false
						));
						if ($updateDataTask) {
							$jsonResponse['success'] = true;
						}
					}
				}

				if ($jsonResponse['success']) {
					$jsonResponse['message'] = 'Data updated';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 0;
					unset($jsonResponse['errors']);
				}
			}
		break;
		case 'task-delete':
			if (!isEmptyVar($dataRequest) && isJSON($dataRequest)) {
				$dataRequest = json_decode($dataRequest, true);

				if (array_key_exists('cardID', $dataRequest) && !isEmptyVar($dataRequest['cardID']) && array_key_exists('taskID', $dataRequest) && !isEmptyVar($dataRequest['taskID'])) {
					// Fetch data Task-item (Check if exist)
					$queryString = "SELECT * FROM master_task WHERE id_task = ? AND id_card = ?;";
					$fetchDataTask = db_runQuery(array(
						'config_array' => $configMysql,
						'database_index' => 0,
						'input' => array(trim($dataRequest['taskID']), trim($dataRequest['cardID'])),
						'query' => $queryString,
						'param' => 'ss',
						'getData' => true,
						'getAllRow' => false
					));
					if (!isEmptyVar($fetchDataTask) && $fetchDataTask !== 'ZERO_DATA') {
						// Delete a Task-item
						$queryString = "DELETE FROM master_task WHERE id_task = ? AND id_card = ?;";
						$deleteTask = db_runQuery(array(
							'config_array' => $configMysql,
							'database_index' => 0,
							'input' => array(trim($dataRequest['taskID']), trim($dataRequest['cardID'])),
							'query' => $queryString,
							'param' => 'ss',
							'getData' => false,
							'getAllRow' => false
						));
						if ($deleteTask) {
							$jsonResponse['success'] = true;
						}
					}
				}

				if ($jsonResponse['success']) {
					$jsonResponse['message'] = 'Data deleted.';
					$jsonResponse['datetime'] = date('Y-m-d H:i:s');
					$jsonResponse['took'] = (floor(microtime(true)*1000))-$startTime.'ms';
					$jsonResponse['errcode'] = 0;
					unset($jsonResponse['errors']);
				}
			}
		break;
	}
	header('Content-Type: application/json');
	echo json_encode($jsonResponse, JSON_UNESCAPED_UNICODE);
	exit(0);
} else {
	header('Location: ../index.php');
}