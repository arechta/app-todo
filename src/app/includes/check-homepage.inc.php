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

$userData = (isset($userData)) ? $userData : null;
if (!isset($userData) || !is_object($userData)) {
	$user = new User();
	$userData = array('stt', 'nik', 'tkn', 'usr');
	foreach($userData as $idx => $perData) {
		$userData[$perData] = $user->getSession($perData);
		$userData[$perData] = ($userData[$perData]['success']) ? $userData[$perData]['data'] : null;
		unset($userData[$idx]);
	}
	$userData = arr2Obj($userData);
}

/*
 * Check the account privileges
 * and set redirected homepage user
 */
$userPage = null;
$accountPrivileges = db_runQuery(array(
	'config_array' => $configMysql,
	'database_index' => 0,
	'input' => array($userData->nik),
	'query' => sprintf('SELECT * FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_privileges'),
	'param' => 's',
	'getData' => true,
	'getAllRow' => false
));
if (!isEmptyVar($accountPrivileges) && $accountPrivileges !== 'ZERO_DATA') {
	if (array_key_exists('PERMISSIONS', $accountPrivileges) && !isEmptyVar($accountPrivileges['PERMISSIONS'])) {
		if (isJSON($accountPrivileges['PERMISSIONS'])) {
			$accountPrivileges = json_decode($accountPrivileges['PERMISSIONS'], true);
			if (is_array($accountPrivileges) && isAssoc($accountPrivileges)) {
				foreach($accountPrivileges['privileges']['pages'] as $perPages) {
					if(array_key_exists('link', $perPages) && array_key_exists('view', $perPages)) {
						if($perPages['link'] === 'dashboard' && boolval($perPages['view']) === true) {
							$userPage = 'dashboard';
							break;
						} else {
							if (boolval($perPages['view']) === true) {
								$userPage = $perPages['link'];
								break;
							}
						} 
					}
				}
				if($userPage !== 'dashboard') {
					$availablePages = array(
						'dc' => array(
							'gate-activity', 'vehicle-statistics', 'man-power',
							'throughput-io', 'chamber-storage'
						),
						'maintenance' => array(
							'storage-temperature-humidity', 'running-hour-battery'
						),
						'hrd' => array(
							'drum-beat-performance'
						)
					);
					if (in_array($userPage, $availablePages['dc'])) {
						$userPage = 'dc/'.$userPage;
					}
					if (in_array($userPage, $availablePages['maintenance'])) {
						$userPage = 'maintenance/'.$userPage;
					}
					if (in_array($userPage, $availablePages['hrd'])) {
						$userPage = 'hrd/'.$userPage;
					}
				}
			}
		}
	}
}
?>