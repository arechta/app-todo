<?php
// Memuat file yang dibutuhkan
$thisPath = (defined('DIR_ROOT')) ? DIR_CONFIG : dirname(__FILE__);
require_once($thisPath."/variables.php");
require_once($thisPath."/functions.php");

function connectToDBMS(array $optionalOptions) {
	// static $online = null;
	$optionalDefault = [
		'host' => 'localhost',
		'username' => 'root',
		'password' => '',
		'use_config' => null,
		'config_file' => null,
		'config_array' => null,
		// 'database_name' => null,
		'database_index' => 0
	];
	extract(array_merge($optionalDefault, array_intersect_key($optionalOptions, $optionalDefault)));
	$dataToast = [ 'title' => 'MTP Core', 'message' => 'Terdapat kesalahan internal pada Aplikasi, tolong hubungi IT!', 'mode' => 'failure', 'timeout' => 10, 'class' => '' ];
	$conn = null;
	// if (!($online == true && isset($online))) {
		if (!is_null($use_config) && $use_config == true) {
			$config = null;
			if (!is_null($config_array) && is_array($config_array)) {
				if (!isAssoc($optionalOptions['config_array'])) {
					$dataToast += [ 'expiredon' => strtotime('now') + 5 ];
					header('location: ' . getURI(2) . '/index.php?toast=' . base64_encode(json_encode($dataToast, JSON_UNESCAPED_SLASHES)));
					reportLog("Periksa kembali 'connectToDBMS();' config_array bukan associative array!", 412, true);
				}
				$config['DATABASE_SERVER'] = $config_array;
			} else {
				if (file_exists(get_absolute_path(DIR_ROOT.$config_file))) $config = parse_ini_file(get_absolute_path(DIR_ROOT.$config_file), true);
				else reportLog("File (".get_absolute_path(DIR_ROOT.$config_file).") 'db-setting.ini.php' tidak ditemukan!", 404, true);
			}

			if (isset($config['DATABASE_SERVER'])) {
				if (isset($config['DATABASE_SERVER']['mysql_host']) && isset($config['DATABASE_SERVER']['mysql_username']) && isset($config['DATABASE_SERVER']['mysql_password']) && isset($config['DATABASE_SERVER']['mysql_database'])) {
					$conn = new mysqli($config['DATABASE_SERVER']['mysql_host'], $config['DATABASE_SERVER']['mysql_username'], $config['DATABASE_SERVER']['mysql_password'], $config['DATABASE_SERVER']['mysql_database'][$database_index]);
					// Check connection mysqli
					if (mysqli_connect_errno()) {
						// Unset session app regional
						unset($_SESSION['app-note']);
						unset($_SESSION['app-region']);

						$dataToast += [ 'expiredon' => strtotime('now') + 5 ];
						header('location: ' . getURI(2) . '/index.php?toast=' . base64_encode(json_encode($dataToast, JSON_UNESCAPED_SLASHES)));
						reportLog("Periksa kembali 'connectToDBMS();' koneksi ERROR: ".mysqli_connect_errno()." (".mysqli_connect_error().")", 406, false);
						exit();
						// $online = false;
					}
					// else { $online = true; }
					// Check mysql_database is exists or not
					//if (isset($config['DATABASE_SERVER']['mysql_database'])) {
					//	if (is_int($database_index)) {
					//		$db_select = mysqli_select_db($conn, isset($config['DATABASE_SERVER']['mysql_database'][$database_index]));
					//		if (!$db_select) {
					//			reportLog("Periksa kembali 'connectToDBMS();' parameter pada 'database_index' nilai tidak ditemukan!", 406, true);
					//			// $online = false;
					//		}
					//	} else {
					//		reportLog("Periksa kembali 'connectToDBMS();' parameter pada 'database_index' nilai harus berupa angka!", 407, true);
					//		// $online = false;
					//	}
					//} else {
					//	if(!(is_null($database_name) && !isset($database_name))) {
					//		$db_select = mysqli_select_db($conn, $database_name);
					//		if(!$db_select) {
					//			reportLog("Periksa kembali 'connectToDBMS();' parameter pada 'database_name' nilai tidak ditemukan!", 406, true);
					//			// $online = false;
					//		}
					//	}
					//}
				} else {
					$dataToast += [ 'expiredon' => strtotime('now') + 5 ];
					header('location: ' . getURI(2) . '/index.php?toast=' . base64_encode(json_encode($dataToast, JSON_UNESCAPED_SLASHES)));
					reportLog("File 'db-setting.ini.php' tidak mempunyai data 'mysql_(host, username, password, dan database)'!", 405, false);
					exit();
					// $online = false;
				}
			} else {
				$dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				header('location: ' . getURI(2) . '/index.php?toast=' . base64_encode(json_encode($dataToast, JSON_UNESCAPED_SLASHES)));
				reportLog("File 'db-setting.ini.php' tidak mempunyai konfigurasi '[DATABASE_SERVER]'!", 405, true);
				exit();
				// $online = false;
			}
		} else {
			$conn = new mysqli($host, $username, $password, $database_name);
			if (mysqli_connect_errno()) {
				$dataToast += [ 'expiredon' => strtotime('now') + 5 ];
				header('location: ' . getURI(2) . '/index.php?toast=' . base64_encode(json_encode($dataToast, JSON_UNESCAPED_SLASHES)));
				reportLog("Periksa kembali 'connectToDBMS();' koneksi ERROR: ".mysqli_connect_errno()." (".mysqli_connect_error().")", 406, true);
				exit();
				// $online = false;
			}
			// else { $online = true; }
			// if (!is_null($database_name) && isset($database_name) && $online == true) {
			// 	$db_select = mysqli_select_db($conn, $database_name);
			// 	if (!$db_select) {
			// 		reportLog("Periksa kembali 'connectToDBMS();' parameter pada 'database_name' nilai tidak ditemukan!", 406, true);
			// 		// $online = false;
			// 	}
			// }
		}
	// }
	return $conn;
}