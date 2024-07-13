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

$user = new User();
$userData = array('stt', 'nik', 'tkn', 'usr');
foreach($userData as $idx => $perData) {
	$userData[$perData] = $user->getSession($perData);
	$userData[$perData] = ($userData[$perData]['success']) ? $userData[$perData]['data'] : null;
	unset($userData[$idx]);
}
$userData = arr2Obj($userData);
$userProfile = arr2Obj(array(
	'nik' => $userData->nik,
	'role' => '-',
	'name' => '-',
	'aka' => '-',
	'bio' => 'No records.',
	'date_birth' => '-',
	'email' => '-',
	'phone_number' => '-',
	'avatar' => path2url(sprintf('%s/image/illustrations/avatar-%s.png', DIR_ASSET, $APP_CORE['app_build_version'])),
	'company' => array(
		'logo' => '-',
		'name' => '-',
		'position' => '-',
		'department' => '-',
		'since' => '-',
		'email' => '-',
		'phone_number' => '-',
		'telephone_number' => '-'
	),
	'approval_flag' => null
));
$accountPrivileges = null;
$fetchUser = null;

if($userData->tkn != false) {
	if($user->hasLoggedIn($userData->tkn)['success']) {
		/*
		 * User Profiles
		 */
		$fetchUser = db_runQuery(array(
			'config_array' => $configMysql,
			'database_index' => 0,
			'input' => array($userData->nik),
			'query' => sprintf('SELECT * FROM %s WHERE NIK = ?;', $APP_CORE['tb_prefix'].'user_account'),
			'param' => 's',
			'getData' => true
		));
		if(!isEmptyVar($fetchUser) && $fetchUser !== 'ZERO_DATA') {
			$fetchUser = arr2Obj($fetchUser);
			$userProfile->name = ucwords(strtolower($fetchUser->NAMA));
			$userProfile->aka = (!isEmptyVar($fetchUser->PANGGILAN)) ? strtolower($fetchUser->PANGGILAN) : $userProfile->aka;
			$userProfile->bio = (!isEmptyVar($fetchUser->BIOGRAFI)) ? trim($fetchUser->BIOGRAFI) : $userProfile->bio;
			$userProfile->date_birth = (!isEmptyVar($fetchUser->TGL_LAHIR)) ? $fetchUser->TGL_LAHIR : $userProfile->date_birth;
			$userProfile->email = (!isEmptyVar($fetchUser->EMAIL)) ? $fetchUser->EMAIL : $userProfile->email;
			$userProfile->phone_number = (!isEmptyVar($fetchUser->NO_HP)) ? $fetchUser->NO_HP : $userProfile->phone_number;
			$userProfile->avatar = (!isEmptyVar($fetchUser->META_AVATAR)) ? sprintf('%s/files/%s', getURI(2), $fetchUser->META_AVATAR) : $userProfile->avatar;
			if(!isEmptyVar($fetchUser->KODE_STOREKEY)) {
				$userProfile->company->logo = db_runQuery(array(
					'config_array' => $configMysql,
					'database_index' => 0,
					'input' => array($fetchUser->KODE_STOREKEY),
					'query' => sprintf('SELECT DISTINCT KODE_STOREKEY, META_LOGO FROM %s WHERE KODE_STOREKEY = ? AND META_LOGO IS NOT NULL;', $APP_CORE['tb_prefix'].'user_account'),
					'param' => 's',
					'getData' => true,
					'callback' => function($response) use($userProfile) {
						$output = $response['data'];
						$result = $userProfile->company->logo;
						if(!isEmptyVar($output) && $output !== 'ZERO_DATA') {
							$result = sprintf('%s/files/%s', getURI(2), $output['META_LOGO']);
						}
						return $result;
					}
				));
			}
			$userProfile->company->name = str_replace(array('pt','Pt'), array('PT', 'PT'), ucwords(strtolower($fetchUser->NAMA_PERUSAHAAN)));
			if(strtolower(trim(substr(trim($userProfile->company->name), -2))) == 'pt') {
				$userProfile->company->name = preg_replace('/\s+/', ' ', sprintf('PT %s', trim(substr($userProfile->company->name, 0, strlen($userProfile['name']) - 2))));
			}
			$userProfile->company->position = ucwords(strtolower($fetchUser->JABATAN));
			$userProfile->company->department = (!isEmptyVar($fetchUser->DEPARTMENT)) ? ((strlen($fetchUser->DEPARTMENT) > 5) ? ucwords(strtolower($fetchUser->DEPARTMENT)) : strtoupper($fetchUser->DEPARTMENT)) : $userProfile->company->email;;
			$userProfile->company->since = (!isEmptyVar($fetchUser->TGL_BEKERJA)) ? $fetchUser->TGL_BEKERJA : $userProfile->company->since;
			$userProfile->company->email = (!isEmptyVar($fetchUser->EMAIL_PERUSAHAAN)) ? $fetchUser->EMAIL_PERUSAHAAN : $userProfile->company->email;
			$userProfile->company->phone_number = (!isEmptyVar($fetchUser->NO_HP_PERUSAHAAN)) ? $fetchUser->NO_HP_PERUSAHAAN : $userProfile->company->phone_number;
			$userProfile->company->telephone_number = (!isEmptyVar($fetchUser->NO_TELP_PERUSAHAAN)) ? $fetchUser->NO_TELP_PERUSAHAAN : $userProfile->company->telephone_number;
			if(!is_null($fetchUser->APPROVAL_FLAG)) { $userProfile->approval_flag = $fetchUser->APPROVAL_FLAG; }

			// Based on user Role-type
			if($userData->usr->role === 'c') {
				$userProfile->role = 'Customer';
			}
			if($userData->usr->role === 'cs') {
				$userProfile->role = 'Customer Service';
			}
			if($userData->usr->role === 'ho') {
				$userProfile->role = 'Head Office';
			}
		}

		/*
		 * Account Privileges
		 */
		$accountPrivileges = db_runQuery(array(
			'config_array' => $configMysql,
			'database_index' => 0,
			'input' => array($userData->nik, $userData->usr->role),
			'query' => sprintf('SELECT * FROM %s WHERE NIK = ? AND LEVEL_CODE = ?;', $APP_CORE['tb_prefix'].'user_privileges'),
			'param' => 'ss',
			'getData' => true,
			'getAllRow' => false,
			'callback' => function($result) {
				$result = $result['data'];
				$output = array(
					'position' => '-',
					'permission' => array()
				);
				if(!isEmptyVar($result) && $result != 'ZERO_DATA') {
					if(array_key_exists('PERMISSIONS', $result) && !isEmptyVar($result['PERMISSIONS'])) {
						if(isJSON($result['PERMISSIONS'])) {
							$output['permission'] = json_decode($result['PERMISSIONS'], true);
						}
					}
				}
				return $output;
			}
		));
	}
}

$authForRole = function(array $role) use ($userData, $userProfile) {
	$currentPage = preg_replace('/\.php$/', '', basename(strtok(getURI(2, true), '?')));;

	/*
	 * Check user Account Role, redirect if not met specific requirement
	 */
	if(!in_array($userData->usr->role, $role)) {
		if(!wordExist(strtolower($currentPage), 'dashboard')) {
			header('Location: '.sprintf('%s/dashboard%s', getURI(2), (EXT_PHP) ? '.php' : ''));
		} else {
			// header('Location: ' . sprintf('%s/app/includes/logout.inc%s', getURI(2), (EXT_PHP) ? '.php' : ''));
			header('Location: http://mtp-logistics.com/');
		}
		exit(1);
	}

	switch($userData->usr->role) {
		case 'c':
			if((int) $userProfile->approval_flag < 2) {
				if(!wordExist(strtolower($currentPage), 'verify')) {
					header('Location: '.sprintf('%s/verify%s', getURI(2), (EXT_PHP) ? '.php' : ''));
					exit(1);
				}
			}
		break;
	}

	unset($userProfile->approval_flag);
};
