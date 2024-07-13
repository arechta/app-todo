<?php
// Required files
$thisPath = (defined('DIR_ROOT')) ? DIR_CONFIG : dirname(__FILE__);
require_once($thisPath."/variables.php");
require_once(DIR_CONFIG."/functions.php");
require_once(DIR_CONFIG."/db-handles.php");

set_time_limit(60 * 5); // 5 minutes

/* QUERY LIST */
function db_runQuery(array $optionalOptions) {
	if (array_key_exists('config_file', $optionalOptions)) { if (is_null($optionalOptions['config_file'])) unset($optionalOptions['config_file']); }
	if (array_key_exists('config_array', $optionalOptions)) { if (is_null($optionalOptions['config_array'])) unset($optionalOptions['config_array']); }
	$optionalDefault = [
		//'connection' => null,
		'config_file' => '/configs/db-setting.ini.php',
		'config_array' => null,
		'database_index' => 0,
		'input' => null,
		'query' => null,
		'param' => null,
		'getData' => false,
		'getAllRow' => false,
		'callback' => null,
		'stmtError' => ''
	];
	extract(array_merge($optionalDefault, array_intersect_key($optionalOptions, $optionalDefault)));
	if (is_null($query) && isset($query)) reportLog("db_runQuery(): Variable 'query' tidak boleh 'null/kosong'", 410, true);
	$runCallback = function($response, $dataOnly = true) use ($callback) {
		return (!is_null($callback)) ? call_user_func($callback, $response) : (($dataOnly) ? $response['data'] : $response);
	};
	$regenerateQueries = function($input = null, $query = null) {
		$result = false;
		if (!is_null($query)) {
			$arrStr = str_split($query);
			if (is_array($input)) {
				foreach ($input as $valData) {
					foreach ($arrStr as $idx => $val){
						if ($val == '?') {
							$arrStr[$idx] = (is_null($valData)) ? "NULL" : "\"$valData\"";
							break;
						}
					}
				}
				$result = (is_array($arrStr)) ? $arrStr : false;
			} else {
				$result = str_split(str_replace('?', (is_null($input)) ? "NULL" : "\"$input\"", implode($arrStr)));
			}
		}
		return ($result != false) ? implode($result) : false;
	};

	// Catch warning errors
	set_error_handler(function($errno, $errstr, $errfile, $errline) {
		// error was suppressed with the @-operator
		if (0 === error_reporting()) { return false; }
		throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
	});

	// Inisialisasi koneksi MySQLi dan mengambil raw data
	$connection = (!is_null($config_array)) ? connectToDBMS([ 'use_config' => true, 'config_array' => $config_array, 'database_index' => $database_index ]) : connectToDBMS([ 'use_config' => true, 'config_file' => $config_file, 'database_index' => $database_index ]);
	if ($connection) {
		$threadId = $connection->thread_id;
		mysqli_set_charset($connection, "utf8");
		
		$returnBind = $returnExec = $rawData = null;
		if ($statement = $connection->prepare($query)) {
			// for: Checking 'param' and 'input' keys, match the bind data
			if (!is_null($param)) {
				/* return Error, if 'input' is empty and 'param' is exist */
				if (is_null($input) || empty($input)) {
					$queryError = $regenerateQueries($input, $query);
					logQuery(DIR_REPORT, 'QUERY_FUNC_001', "Error, no 'input' for specific params! please fill 'param' key", $input, $queryError, false);
					reportLog("db_runQuery(): Error pada 'mysqli_stmt_bind_param' mendapat nilai kembali 'false'!", 411, false);
					return $runCallback([ 'sttcode' => 'QUERY_FUNC_001', 'message' => "Error, no 'input' for specific params! please fill 'param' key", 'data' => false ]);
					exit();
				}

				$returnBind = [
					'return' => false,
					'errCode' => null,
					'errMessage' => null
				];
				try {
					$returnBind['return'] = (is_array($input)) ? $statement->bind_param($param, ...$input) : $statement->bind_param($param, $input);
				} catch (Exception $e) {
					$returnBind['errCode'] = $e->getCode();
					$returnBind['errMessage'] = $e->getMessage();
				}

				/* return Error, if 'input' is not match with length of 'param' data */
				if ($returnBind['return'] === false) {
					$queryError = $regenerateQueries($input, $query);
					logQuery(DIR_REPORT, 'QUERY_FUNC_002', $returnBind['errCode'], $input, $queryError, false);
					reportLog("db_runQuery(): Error pada 'mysqli_stmt_bind_param' mendapat nilai kembali 'false'!", 411, false);
					return $runCallback([ 'sttcode' => 'QUERY_FUNC_002', 'message' => $returnBind['errMessage'], 'data' => false ]);
					exit();
				}
			}

			$returnExec = $statement->execute();
			if ($returnExec === true) {
				if ($getData === true) {
					$rawData = $statement->get_result();
					$lenData = mysqli_num_rows($rawData);
				}
			} else {
				/* return Error, if in process 'query' execute is have problem, like 'Duplicate entry of rows & etc' */
				$queryError = $regenerateQueries($input, $query);
				logQuery(DIR_REPORT, 'QUERY_STMT_'.$statement->errno, $statement->error, $input, $queryError, false);
				reportLog("db_runQuery(): Error pada 'mysqli_stmt_execute' mendapat nilai kembali 'false'!", 411, false);
				return $runCallback([ 'sttcode' => 'QUERY_STMT_'.$statement->errno, 'message' => $statement->error, 'data' => false ]);
				exit();
			}
		} else {
			/* return Error, if in checking 'query' execute is have problem, like 'Table db_something.tb_name does not exist' */
			if (isset($stmtError) || !is_null($stmtError)) header("location: $stmtError");
			$queryError = $regenerateQueries($input, $query);
			logQuery(DIR_REPORT, 'QUERY_PREP_'.$connection->errno, $connection->error, $input, $queryError, false);
			reportLog("db_runQuery(): Error pada 'mysqli_stmt_prepare' mendapat nilai kembali 'false'!", 411, false);
			return $runCallback([ 'sttcode' => 'QUERY_PREP_'.$connection->errno, 'message' => $connection->error, 'data' => false ]);
			exit();
		}

		// Menset data
		if (isset($rawData) && !is_null($rawData)) {
			if ($lenData != 0) {
				if ($getAllRow) $data = $rawData->fetch_all(MYSQLI_ASSOC);
				else $data = $rawData->fetch_assoc();
			}
		}
		
		// Tutup koneksi
		// mysqli_stmt_close($statement); mysqli_kill($connection, $threadId); mysqli_close($connection);
		$statement->close(); $connection->kill($threadId); $connection->close();
		// Restore error handler
		restore_error_handler();

		// Mengembalikan nilai true(+data)/false
		if (isset($rawData) && !is_null($rawData)) {
			if ($lenData != 0) {
				if ($getAllRow) return ($data) ? $runCallback([ 'sttcode' => 'QUERY_SUCCESS', 'message' => 'Successfully fetching the data based on the query syntax!', 'data' => $data, 'length' => $lenData ]) : false;
				else return ($data) ? $runCallback([ 'sttcode' => 'QUERY_SUCCESS', 'message' => 'Successfully fetching the data based on the query syntax!', 'data' => $data, 'length' => $lenData ]) : false;
			} else { return $runCallback([ 'sttcode' => 'QUERY_FAILURE', 'message' => 'No data!', 'data' => 'ZERO_DATA', 'length' => 0 ]); }
		} else { return $runCallback([ 'sttcode' => ($returnExec) ? 'QUERY_SUCCESS' : 'QUERY_FAILURE', 'message' => ($returnExec) ? 'Query executed successfully!' : 'Query failed to execute!', 'data' => $returnExec ]); }
	} else {
		return $runCallback([ 'sttcode' => 'QUERY_FAILURE', 'message' => 'Please check the database server connection!', 'data' => false ]);
	}
}

function db_currentUse($connection) {
	$result = mysqli_query($connection, 'SELECT DATABASE()');
	$row = mysqli_fetch_row($result);
	return (!is_null($row) && !empty($row[0])) ? $row[0] : false;
}

function db_isTableExist(array $configMysql = array(), string $searchTable = '', bool $verbose = false) {
	$output = ($verbose) ? 'NOT_EXIST' : false;
	// Check 'configMysql'
	if (is_array($configMysql) && isAssoc($configMysql)) {
		$defaultMysql = array('mysql_host', 'mysql_username', 'mysql_password', 'mysql_database');
		foreach($configMysql as $key => $val) {
			if (!in_array($key, $defaultMysql)) {
				return ($verbose) ? "INVALID_MYSQL_CONFIG" : false;
			}
		}
	} else {
		return ($verbose) ? "INVALID_MYSQL_VAR" : false;
	}
	// Check 'searchTable'
	if (!is_string($searchTable)) {
		return ($verbose) ? "INVALID_TABLE_VAR" : false;
	} else {
		if (strlen($searchTable) <= 0) {
			return ($verbose) ? "EMPTY_TABLE_NAME" : false;
		}
	}
	
	$isExistTable = db_runQuery(array(
		'config_array' => $configMysql,
		'database_index' => 0,
		'query' => "SHOW TABLES LIKE '". $searchTable ."';",
		'getData' => true,
		'getAllRow' => false,
	));
	if (is_string($isExistTable) && $isExistTable === 'ZERO_DATA') {
		return ($verbose) ? "NOT_EXIST" : false;
	} else {
		return ($verbose) ? "EXIST" : true;
	}
}

function db_createColumn(array $configMysql = array(), string $onTable = '', array $createColumns = array(), bool $verbose = false) {
	$output = ($verbose) ? [] : false;
	// Check 'configMysql'
	if (is_array($configMysql) && isAssoc($configMysql)) {
		$defaultMysql = array('mysql_host', 'mysql_username', 'mysql_password', 'mysql_database');
		foreach($configMysql as $key => $val) {
			if (!in_array($key, $defaultMysql)) {
				return ($verbose) ? "INVALID_MYSQL_CONFIG" : false;
			}
		}
	} else {
		return ($verbose) ? "INVALID_MYSQL_VAR" : false;
	}
	// Check 'onTable', table exist or not
	if (is_string($onTable)) {
		$isExistTable = db_runQuery(array(
			'config_array' => $configMysql,
			'database_index' => 0,
			'query' => "SHOW TABLES LIKE '". $onTable ."';",
			'getData' => true,
			'getAllRow' => false,
		));
		if (is_string($isExistTable) && $isExistTable === 'ZERO_DATA') {
			return ($verbose) ? "TABLE_NOT_EXIST" : false;
		}
	} else {
		return ($verbose) ? "INVALID_TABLE_VAR" : false;
	}
	// Check 'createColumns'
	if (is_array($createColumns) && isAssoc($createColumns)) {
		$defaultFormat = array('DATA_TYPE', 'DEFAULT');
		foreach($createColumns as $columnName => $columnRule) {
			foreach($columnRule as $key => $item) {
				if (!in_array($key, $defaultFormat)) {
					return ($verbose) ? "INVALID_COLUMN_RULE" : false;
				}
			}
		}
	} else {
		return ($verbose) ? "INVALID_COLUMN_VAR" : false;
	}
	foreach ($createColumns as $columnName => $columnRule) {
		$queryString = sprintf("ALTER TABLE %s ADD %s %s DEFAULT %s;", $onTable, $columnName, $columnRule['DATA_TYPE'], $columnRule['DEFAULT']);
		$isColumnAdd = db_runQuery(array(
			'config_array' => $configMysql,
			'database_index' => 0,
			'query' => $queryString,
			'getData' => false,
			'getAllRow' => false,
		));
		if ($isColumnAdd) {
			if ($verbose) {
				$output[$columnName] = true;
			} else {
				$output = true;
			}
		} else {
			if ($verbose) {
				$output[$columnName] = false;
			} else {
				$output = false;
			}
		}
	}
	return $output;
}

function db_isColumnExist(array $configMysql = array(), string $onTable = '', string $searchColumn = '', bool $verbose = false) {
	$output = ($verbose) ? 'NOT_EXIST' : false;
	// Check 'configMysql'
	if (is_array($configMysql) && isAssoc($configMysql)) {
		$defaultMysql = array('mysql_host', 'mysql_username', 'mysql_password', 'mysql_database');
		foreach($configMysql as $key => $val) {
			if (!in_array($key, $defaultMysql)) {
				return ($verbose) ? "INVALID_MYSQL_CONFIG" : false;
			}
		}
	} else {
		return ($verbose) ? "INVALID_MYSQL_VAR" : false;
	}
	// Check 'onTable', table exist or not
	if (is_string($onTable)) {
		$isExistTable = db_runQuery(array(
			'config_array' => $configMysql,
			'database_index' => 0,
			'query' => "SHOW TABLES LIKE '". $onTable ."';",
			'getData' => true,
			'getAllRow' => false,
		));
		if (is_string($isExistTable) && $isExistTable === 'ZERO_DATA') {
			return ($verbose) ? "TABLE_NOT_EXIST" : false;
		}
	} else {
		return ($verbose) ? "INVALID_TABLE_VAR" : false;
	}
	// Check 'searchColumn'
	if (!is_string($searchColumn)) {
		return ($verbose) ? "INVALID_COLUMN_VAR" : false;
	} else {
		if (strlen($searchColumn) <= 0) {
			return ($verbose) ? "EMPTY_COLUMN_NAME" : false;
		}
	}
	
	$isColumnExist = db_runQuery(array(
		'config_array' => $configMysql,
		'database_index' => 0,
		'query' => 'SHOW COLUMNS FROM `' . trim($onTable) . '` LIKE "' . trim($searchColumn) . '";',
		'getData' => true,
		'getAllRow' => false,
	));
	if (is_string($isColumnExist) && $isColumnExist === 'ZERO_DATA') {
		return ($verbose) ? "NOT_EXIST" : false;
	} else {
		return ($verbose) ? "EXIST" : true;
	}
}

function requestAPI(string $url, string $method = 'get', array $data = null, bool $usingCurl = true, bool $sendJSON = false, int $timeoutConnect = 300, int $timeoutProcess = 300) {
	$resultAPI = null; $wrongMethod = 0;
	if (!is_string($url) && !is_string($method) && !isAssoc($data)) return false;
	if (strtolower($method) != 'post' && strtolower($method) != 'get') return false;
	if (!is_null($data) && $sendJSON == false) $content = http_build_query($data);
	if ($sendJSON == true) $content = json_encode($data);
	if ($usingCurl) {
		$ch = curl_init();
		if (strtolower($method) == 'post') {
			if ($sendJSON) { curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Accept:application/json')); curl_setopt($ch, CURLOPT_POST, 1); }
			else { curl_setopt($ch, CURLOPT_HEADER, false); curl_setopt($ch, CURLOPT_POST, strlen($content)); }
			curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
		}
		if(strtolower($method) == 'get') {
			if (!is_null($data)) $url .= '?'.$content;
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (is_int($timeoutConnect)) ? $timeoutConnect : 300); // Default from curl 5 minutes (in-second)
		curl_setopt($ch, CURLOPT_TIMEOUT, (is_int($timeoutProcess)) ? $timeoutProcess : 0); // Default from curl indefinite, until process done (in-second)
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$resultAPI = curl_exec($ch);
		curl_close($ch);
	} else {
		$options = [ 'method' => $method ];
		if (strtolower($method) == 'post') {
			$options += [
				'header'  => "Content-type: application/x-www-form-urlencoded\r\n".
							 "Content-Length: ".strlen($content)."\r\n",
				'content' => $content
			];
		}
		if (strtolower($method) == 'get') { if (!is_null($data)) $url .= '?'.$content; }
		$context  = stream_context_create([ 'http' => $options ]);
		$resultAPI = file_get_contents($url, false, $context);
	}
	return $resultAPI;
}
