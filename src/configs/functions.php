<?php
include __DIR__.'/../vendor/autoload.php';

use APP\includes\classes\DumpException;

// Validasi
function emptyInput(...$inputs) {
	$empty = null;
	foreach ($inputs as $input) {
		if (empty($input)) $empty++;
	}
	return !is_null($empty);
}
function invalidEmail($input) {
	return !filter_var($input, FILTER_VALIDATE_EMAIL);
}
function isMatch($input, $input2) {
	return ($input === $input2);
}
function isEmptyVar($var) {
	return (is_null($var) || !isset($var) || $var == '');
}
// Cryptographically Secure Pseudo-Random Number Generator (CSPRNG).
/**
 * Generate a random string, using a cryptographically secure 
 * pseudorandom number generator (random_int)
 * 
 * For PHP 7, random_int is a PHP core function
 * For PHP 5.x, depends on https://github.com/paragonie/random_compat
 * 
 * @param int $length      How many characters do we want?
 * @param string $keyspace A string of all possible characters
 *                         to select from
 * @return string
 */
function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
	$str = '';
	$max = mb_strlen($keyspace, '8bit') - 1;
	if ($max < 1) {
		throw new Exception('$keyspace must be at least two characters long');
	}
	for ($i = 0; $i < $length; ++$i) {
		$str .= $keyspace[random_int(0, $max)];
	}
	return $str;
}
function jsonFixer($json, $withFormating = false) {
	$patterns     = [];
	/** Garbage removal */
	$patterns[0]  = "/([\s:,\{}\[\]])\s*'([^:,\{}\[\]]*)'\s*([\s:,\{}\[\]])/"; //Find any character except colons, commas, curly and square brackets surrounded or not by spaces preceded and followed by spaces, colons, commas, curly or square brackets...
	// $patterns[1]  = '/([^\s:,\{}\[\]]*)\{([^\s:,\{}\[\]]*)/'; //Find any left curly brackets surrounded or not by one or more of any character except spaces, colons, commas, curly and square brackets...
	// $patterns[2]  =  "/([^\s:,\{}\[\]]+)}/"; //Find any right curly brackets preceded by one or more of any character except spaces, colons, commas, curly and square brackets...
	$patterns[3]  = "/(}),\s*/"; //JSON.parse() doesn't allow trailing commas
	
	/** Reformatting */
	if ($withFormating) {
		$patterns[4]  = '/([^\s:,\{}\[\]]+\s*)*[^\s:,\{}\[\]]+/'; //Find or not one or more of any character except spaces, colons, commas, curly and square brackets followed by one or more of any character except spaces, colons, commas, curly and square brackets...
		$patterns[5]  = '/["\']+([^"\':,\{}\[\]]*)["\']+/'; //Find one or more of quotation marks or/and apostrophes surrounding any character except colons, commas, curly and square brackets...
		$patterns[6]  = '/(")([^\s:,\{}\[\]]+)(")(\s+([^\s:,\{}\[\]]+))/'; //Find or not one or more of any character except spaces, colons, commas, curly and square brackets surrounded by quotation marks followed by one or more spaces and  one or more of any character except spaces, colons, commas, curly and square brackets...
		$patterns[7]  = "/(')([^\s:,\{}\[\]]+)(')(\s+([^\s:,\{}\[\]]+))/"; //Find or not one or more of any character except spaces, colons, commas, curly and square brackets surrounded by apostrophes followed by one or more spaces and  one or more of any character except spaces, colons, commas, curly and square brackets...
		$patterns[8]  = '/(})(")/'; //Find any right curly brackets followed by quotation marks...
		$patterns[9]  = '/,\s+(})/'; //Find any comma followed by one or more spaces and a right curly bracket...
		$patterns[10] = '/\s+/'; //Find one or more spaces...
		$patterns[11] = '/^\s+/'; //Find one or more spaces at start of string...
	}

	$replacements     = [];
	/** Garbage removal */
	$replacements[0]  = '$1 "$2" $3'; //...and put quotation marks surrounded by spaces between them;
	// $replacements[1]  = '$1 { $2'; //...and put spaces between them;
	// $replacements[2]  = '$1 }'; //...and put a space between them;
	$replacements[3]  = '$1'; //...so, remove trailing commas of any right curly brackets;

	/** reformatting */
	if ($withFormating) {
		$replacements[4]  = '$0'; //...and put quotation marks surrounding them;
		$replacements[5]  = '"$1"'; //...and replace by single quotation marks;
		$replacements[6]  = '\\$1$2\\$3$4'; //...and add back slashes to its quotation marks;
		$replacements[7]  = '\\$1$2\\$3$4'; //...and add back slashes to its apostrophes;
		$replacements[8]  = '$1, $2'; //...and put a comma followed by a space character between them;
		$replacements[9]  = ' $1'; //...and replace by a space followed by a right curly bracket;
		$replacements[10] = ' '; //...and replace by one space;
		$replacements[11] = ''; //...and remove it.
	}
	$result = preg_replace($patterns, $replacements, $json);
	return $result;
}

// General
function isAssoc($arr) {
	if (array() === $arr) return false;
	return array_keys($arr) !== range(0, count($arr) - 1);
}
function isComment($str) {
	$str = trim($str);
	$first_two_chars = substr($str, 0, 2);
	$last_two_chars = substr($str, -2);
	return $first_two_chars == '//' || $first_two_chars == '/*' || $last_two_chars == '*/' || substr($str, 0, 1) == '#' || $first_two_chars == '<?' || $last_two_chars == '?>' || ($first_two_chars == '/*' && $last_two_chars == '*/');
}
function isJSON($string, $returnData = false) {
	json_decode($string);
	return (json_last_error() === JSON_ERROR_NONE) ? (($returnData === true) ? json_decode($string, true) : true) : false;
}
function isAjax() {
	return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}
function arr2Obj($arr) {
	return (is_array($arr)) ? json_decode(json_encode($arr)) : null;
}
function obj2Arr($obj) {
	return (is_object($obj)) ? json_decode(json_encode($obj), true) : null;
}
function searchArrAssoc($array, $key, $value) {
	$results = array();
	if (is_array($array)) {
		if (isset($array[$key]) && $array[$key] == $value) {
			$results[] = $array;
		}

		foreach ($array as $subarray) {
			$results = array_merge($results, searchArrAssoc($subarray, $key, $value));
		}
	}
	return $results;
}
function uniqueAssocByKey($array, $keyName){
	$newArray = array();
	foreach ($array as $key => $value) {
		if (!isset($newArray[$value[$keyName]])) {
			$newArray[$value[$keyName]] = $value;
		}
	}
	$newArray = array_values($newArray);
	return $newArray;
}
function sumArrAssoc() {
	$data = array();
	array_walk($args = func_get_args(), function (array $arg) use (&$data) {
		array_walk($arg, function ($value, $key) use (&$data) {
			if (isset($data[$key])) {
				$data[$key] += $value;
			} else {
				$data[$key] = $value;
			}
		});
	});

	return $data;
}
function pre_dump($var, $tag = 'pre', $exit = false) {
	echo "<br><$tag>" . print_r($var, 1) . "</$tag><br>";
	if ($exit) {
		exit();
	}
}
function getHeads($data) {
	if (is_array($data)) {
		foreach ($data as $value) {
			if (preg_match("/\b.css\b/", $value)) {
				echo '<link href="app/css/' . $value . '" rel="stylesheet">';
			}
			if (preg_match("/\b.js\b/", $value)) {
				echo '<script src="app/js/' . $value . '"></script>';
			}
		}
	} else {
		if (preg_match("/\b.css\b/", $data)) {
			echo '<link href="app/css/' . $data . '" rel="stylesheet">';
		}
		if (preg_match("/\b.js\b/", $data)) {
			echo '<script src="app/js/' . $data . '"></script>';
		}
	}
}
function getViews(string $view, array $data = [], string $type = 'contents') {
	$output = null;
	$path = DIR_APP . '/view/' . $type . '/' . $view;
	if (!is_dir(DIR_APP . '/view/' . $type)) mkdir(DIR_APP . '/view/' . $type, 0755, true);
	if (!is_null($type)) {
		if (file_exists($path)) {
			// Extract the $data to a local namespace
			extract($data, EXTR_REFS);
			// Start output buffering
			ob_start();
			// Include the template file
			include_once $path;
			// End buffering and return its contents
			$output = ob_get_clean();
			return $output;
		} else {
			return false;
		}
	} else {
		return false;
	}
}
function getFoots($data) {
	if (is_array($data)) {
		for ($i = 0; $i <= (count($data) - 1); $i++) {
			if (preg_match("/\b.js\b/", $data[$i])) {
				echo '<script src="app/js/' . $data[$i] . '"></script>';
			}
		}
	} else {
		if (preg_match("/\b.js\b/", $data)) {
			echo '<script src="app/js/' . $data . '"></script>';
		}
	}
}
function execPHP(string $fileName) {
	$output = null;
	if (file_exists($fileName)) {
		// Start output buffering
		ob_start();
		// Include the template file
		include $fileName;
		// End buffering and return its contents
		$output = ob_get_clean();
		return $output;
	} else {
		return false;
	}
}
function loadConfig(string $path, $defaultConfig = null, $mode = 'ini') {
	$result = null;
	if (!is_null($path) && file_exists($path)) {
		$clearCommentLines = function ($content) {
			$result = '';
			foreach ($content as $lines) {
				if (!isComment($lines)) {
					$result .= $lines;
				}
			}
			return $result;
		};
		$fileContent = $clearCommentLines(file($path));

		switch (strtolower($mode)) {
			case 'json':
				$result = json_decode(jsonFixer($fileContent, true), true);
				break;
			default:
				$result = parse_ini_file($path, true);
				break;
		}
		if (is_array($defaultConfig) && !is_null($defaultConfig)) {
			$result = ($result != false) ? array_replace_recursive($defaultConfig, array_intersect_key($result, $defaultConfig)) : false;
		}
	} else $result = false;

	return $result;
}
function substrwords($text, $maxChar, $end = '...') {
	if (strlen($text) > $maxChar) {
		$words = preg_split('/\s/', $text);
		$output = '';
		$i = 0;
		while (1) {
			$length = strlen($output) + strlen($words[$i]);
			if ($length > $maxChar) {
				break;
			} else {
				$output .= " " . $words[$i];
				++$i;
			}
		}
		$output .= $end;
	} else {
		$output = $text;
	}
	return $output;
}
function wordExist(string $string, string $word) {
	if (!is_string($string) && !is_string($word)) return false;
	if (strpos($string, $word) !== false) {
		return true;
	} else {
		return false;
	}
}
function strposa($haystack, $needle, $offset = 0) {
	$needle = (!is_array($needle)) ? array($needle) : $needle;
	foreach ($needle as $query) {
		if (strpos($haystack, $query, $offset) !== false) return true; // stop on first true result
	}
	return false;
}
function rf_newline($text, $from = '\n', $to = PHP_EOL) {
	$resultFormat = null;
	$textExploded = explode($from, $text);
	for ($i = 0; $i <= (count($textExploded) - 1); $i++) {
		$resultFormat .= ($i != (count($textExploded) - 1)) ? $textExploded[$i] . $to : $textExploded[$i];
	}
	return $resultFormat;
}
function rf_shortword(string $string, int $length = 3) {
	return preg_replace_callback("/\b\w{1,$length}\b/", function ($matches) {
		return strtoupper($matches[0]);
	}, $string);
}
function rf_sectotime(int $seconds) {
	$init = $seconds;
	$hours = floor($init / 3600);
	$minutes = floor(($init / 60) % 60);
	$seconds = $init % 60;
	return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}
function rf_sectohuman(int $seconds, string $locale = 'en_US', string $type = "short") {
	$output = '';
	$formatTime = array(
		'month' => floor($seconds/2592000),
		'day' => floor(($seconds%2592000)/86400),
		'hour' => floor(($seconds%86400)/3600),
		'minute' => floor(($seconds%3600)/60),
		'second' => $seconds%60,
	);
	$formatString = array(
		'en_US' => array(
			'month' => array(
				'long' => 'month',
				'short' => 'mon'
			),
			'day' => array(
				'long' => 'day',
				'short' => 'd'
			),
			'hour' => array(
				'long' => 'hour',
				'short' => 'h'
			),
			'minute' => array(
				'long' => 'minute',
				'short' => 'min'
			),
			'second' => array(
				'long' => 'second',
				'short' => 's'
			),
		),
		'id_ID' => array(
			'month' => array(
				'long' => 'bulan',
				'short' => 'b',
			),
			'day' => array(
				'long' => 'hari',
				'short' => 'h',
			),
			'hour' => array(
				'long' => 'jam',
				'short' => 'j',
			),
			'minute' => array(
				'long' => 'menit',
				'short' => 'm',
			),
			'second' => array(
				'long' => 'detik',
				'short' => 'd'
			)
		)
	);
	foreach ($formatTime as $key => $val) {
		if ($val != 0) {
			$output .= sprintf('%s%s%s, ', $val, ($type == 'long') ? ' ':'', $formatString[$locale][$key][$type]);
		}
	}
	return rtrim(trim($output), ',');
}
function rf_rupiah($number) {
	return "Rp " . number_format($number, 2, ',', '.');
}
function rf_rupiah_pronun($bilangan, $sufix = null) {
	if ($bilangan == '' || $bilangan == null || $bilangan == 'null') {
		return '';
	} else {
		$bilangan = preg_replace("/[^,\d]/", '', $bilangan);
		$kalimat = "";
		$angka = array('0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0');
		$kata = array('', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan');
		$tingkat = array('', 'Ribu', 'Juta', 'Milyar', 'Triliun');
		$panjang_bilangan = strlen($bilangan);

		/* Pengujian panjang bilangan */
		if ($panjang_bilangan > 15) {
			$kalimat = "Diluar Batas";
		} else {
			/* Mengambil angka-angka yang ada dalam bilangan, dimasukkan ke dalam array */
			for ($i = 1; $i <= $panjang_bilangan; $i++) {
				$angka[$i] = substr($bilangan, - ($i), 1);
			}

			$i = 1;
			$j = 0;

			/* Mulai proses iterasi terhadap array angka */
			while ($i <= $panjang_bilangan) {
				$subkalimat = "";
				$kata1 = "";
				$kata2 = "";
				$kata3 = "";

				/* Untuk Ratusan */
				if ($angka[$i + 2] != "0") {
					if ($angka[$i + 2] == "1") {
						$kata1 = "Seratus";
					} else {
						$kata1 = $kata[$angka[$i + 2]] + " Ratus";
					}
				}

				/* Untuk Puluhan atau Belasan */
				if ($angka[$i + 1] != "0") {
					if ($angka[$i + 1] == "1") {
						if ($angka[$i] == "0") {
							$kata2 = "Sepuluh";
						} else if ($angka[$i] == "1") {
							$kata2 = "Sebelas";
						} else {
							$kata2 = $kata[$angka[$i]] + " Belas";
						}
					} else {
						$kata2 = $kata[$angka[$i + 1]] + " Puluh";
					}
				}

				/* Untuk Satuan */
				if ($angka[$i] != "0") {
					if ($angka[$i + 1] != "1") {
						$kata3 = $kata[$angka[$i]];
					}
				}

				/* Pengujian angka apakah tidak nol semua, lalu ditambahkan tingkat */
				if (($angka[$i] != "0") || ($angka[$i + 1] != "0") || ($angka[$i + 2] != "0")) {
					$subkalimat = $kata1 . " " . $kata2 . " " . $kata3 . " " . $tingkat[$j] . " ";
				}

				/* Gabungkan variabe sub kalimat (untuk Satu blok 3 angka) ke variabel kalimat */
				$kalimat = $subkalimat . $kalimat;
				$i = $i + 3;
				$j = $j + 1;
			}

			/* Mengganti Satu Ribu jadi Seribu jika diperlukan */
			if (($angka[5] == "0") && ($angka[6] == "0")) {
				$kalimat = str_replace("Satu Ribu", "Seribu", $kalimat);
			}
		}
		return $sufix == null ? trim(preg_replace("/\s+/", ' ', $kalimat)) : trim(preg_replace("/\s+/", ' ', $kalimat)) . $sufix;
	}
}
function rf_stringToSecret(string $string = null) {
	if (!$string) {
		return null;
	}
	$length = strlen($string);
	$visibleCount = (int) round($length / 4);
	$hiddenCount = $length - ($visibleCount * 2);
	return substr($string, 0, $visibleCount) . str_repeat('*', $hiddenCount) . substr($string, ($visibleCount * -1), $visibleCount);
}
function rf_datetime($fromDateTime, $fromTimezone, $toTimezone) {
	$fromTZ = new DateTimeZone($fromTimezone);
	$toTZ = new DateTimeZone($toTimezone);
	$originDateTime = new DateTime($fromDateTime, $fromTZ);
	$toTime = new DateTime($originDateTime->format("c"));
	$toTime->setTimezone($toTZ);
	return $toTime;
}
function rf_num2roman($number) {
	$map = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
	$returnValue = '';
	while ($number > 0){
		foreach ($map as $roman => $int){
			if($number >= $int){
				$number -= $int;
				$returnValue .= $roman;
				break;
			}
		}
	}
	return $returnValue;
}
function rf_getShortName($fname, $lname) {
	$name = '';
	foreach(explode(' ',  $fname) as $fn) {
		$name .= strtoupper($fn[0]) . '. ';
	}
	$name .= ucfirst($lname);
	return $name;
}
function rf_timeElapsedString($datetime, $level = 1) {
	$now = new DateTime;
	$ago = new DateTime($datetime);
	$diff = (object) $now->diff($ago);

	$diff->w = floor($diff->d / 7);
	$diff->d -= $diff->w * 7;

	$string = array(
		'y' => 'year',
		'm' => 'month',
		'w' => 'week',
		'd' => 'day',
		'h' => 'hour',
		'i' => 'minute',
		's' => 'second',
	);
	foreach ($string as $k => &$v) {
		if ($diff->$k) {
			$v = $diff->$k 	. ' ' . $v . ($diff->$k > 1 ? 's' : '');
		} else {
			unset($string[$k]);
		}
	}

	$string = array_slice($string, 0, $level);
	return $string ? implode(', ', $string) . ' ago' : 'just now';
}
function greetingsDay() {
	$hour = date("G", time());
	$grettings = "";

	if ($hour >= 0 && $hour <= 11) {
		$grettings = "Selamat pagi";
	} elseif ($hour >= 12 && $hour <= 14) {
		$grettings = "Selamat siang";
	} elseif ($hour >= 15 && $hour <= 17) {
		$grettings = "Selamat sore";
	} elseif ($hour >= 17 && $hour <= 18) {
		$grettings = "Selamat petang";
	} elseif ($hour >= 19 && $hour <= 23) {
		$grettings = "Selamat malam";
	}
	return $grettings;
}
function hp62or08($number, $reverse = false) {
	// kadang ada penulisan no hp +62811 239 345
	$number = str_replace(" ", "", $number);
	// kadang ada penulisan no hp (0274) 778787
	$number = str_replace("(", "", $number);
	// kadang ada penulisan no hp (0274) 778787
	$number = str_replace(")", "", $number);
	// kadang ada penulisan no hp +62.811.239.345
	$number = str_replace(".", "", $number);
	// trim blank space
	$number = trim($number);

	// cek apakah no hp mengandung karakter + dan 0-9
	if (!preg_match('/[^+0-9]/', $number)) {
		if ($reverse) {
			// cek apakah no hp karakter 1 adalah 0
			if (substr($number, 0, 1) == '0') {
				$number = '+62' . substr($number, 1);
			}
		} else {
			// cek apakah no hp karakter 1-3 adalah +62
			if (substr($number, 0, 3) == '+62') {
				$number = '0' . substr($number, 3, (strlen($number) - 3));
			} else if (substr($number, 0, 2) == '62') {
				$number = '0' . substr($number, 2, (strlen($number) - 2));
			}
		}
	}
	return $number;
}
function removeTagsByID($html, $ids) {
	$doc = new DOMDocument('1.0', 'UTF-8');
	$internalErrors = libxml_use_internal_errors(true);
	$doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
	$xpath = new DOMXPath($doc);
	libxml_use_internal_errors($internalErrors);
	// find element with specified ID values
	foreach ($ids as $id) {
		$tags = $xpath->query("//*[@id='$id']");
		// and remove them
		foreach ($tags as $tag) {
			$tag->parentNode->removeChild($tag);
		}
	}
	return $doc->saveHTML();
}
function get_absolute_path($path) {
	$path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
	$parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
	$absolutes = array();
	foreach ($parts as $part) {
		if ('.' == $part) {
			continue;
		}
		if ('..' == $part) array_pop($absolutes);
		else $absolutes[] = $part;
	}
	$path = implode(DIRECTORY_SEPARATOR, $absolutes);
	return (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? $path : '/' . $path;
}
function path2url($file, $protocol = null, $changeToLocalhost = false) {
	$output = '';
	$protocol = $protocol ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
	$pathRequest = str_replace(get_absolute_path($_SERVER['DOCUMENT_ROOT']), '', get_absolute_path($file));
	$pathFixes = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? str_replace('\\', '/', $pathRequest) : $pathRequest;
	$output = $protocol . $_SERVER['HTTP_HOST'] . implode('/', array_map('rawurlencode', explode('/', $pathFixes)));
	if ($changeToLocalhost) {
		$output = str_replace($_SERVER['HTTP_HOST'], 'localhost', $output);
	}
	return $output;
}
function getURI(int $mode = 1, bool $withCurrentPage = false) {
	$currentURI = null;
	switch ($mode) {
		case 1:
			$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
			$currentURI = $protocol . $_SERVER['HTTP_HOST'];
			break;
		case 2:
			$dirRoot = (defined('DIR_ROOT')) ? DIR_ROOT : $_SERVER['DOCUMENT_ROOT'];
			$currentURI = path2url($dirRoot);
			break;
		default:
			return false;
			break;
	}
	if ($withCurrentPage != false) $currentURI .= $_SERVER['REQUEST_URI'];
	return $currentURI;
}
function getHostIP(string $uri) {
	// Get ip of link
	$dnsLookup = gethostbyname($uri);
	$parseURI = parse_url($uri);
	if ($dnsLookup = dns_get_record((array_key_exists('host', $parseURI)) ? $parseURI['host'] : $uri, DNS_A)) {
		$dnsLookup = array_reduce($dnsLookup, 'array_merge', array());
		$dnsLookup = $dnsLookup['ip']; // get only ip address
	}
	return $dnsLookup;
}
function isExistDir($path, $createIt = false) {
	if (!file_exists($path)) {
		if ($createIt == true) {
			mkdir($path, 0777, true);
			return (file_exists($path)) ? true : false;
		} else {
			return false;
		}
	} else {
		return true;
	}
}
function getUserIP() {
	// Get real visitor IP behind CloudFlare network
	if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
		$_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
		$_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
	}
	$client = @$_SERVER['HTTP_CLIENT_IP'];
	$forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
	$remote = $_SERVER['REMOTE_ADDR'];

	if (filter_var($client, FILTER_VALIDATE_IP)) $ip = $client;
	else if (filter_var($forward, FILTER_VALIDATE_IP)) $ip = $forward;
	else $ip = $remote;

	return $ip;
}
function getClientIP() {
	return getUserIP();
}
function reportLog($message, $error_code, $exit = false) {
	date_default_timezone_set("Asia/Jakarta");
	if (!is_dir(DIR_REPORT)) mkdir(DIR_REPORT, 0755, true);
	$file = fopen(DIR_REPORT . "/REPORT_[" . date("d-m-Y]+[H.i.s") . "].log", "w");
	$lenTitle = 14;
	$lenMessage = (strlen($message) > 30) ? strlen($message) : 30;
	$strips1 = ($lenMessage % 2 == 0) ? $lenMessage : $lenMessage - 1;
	$strips2 = $lenMessage / 2;
	$str1 = $str2 = '';
	$spaces = array(null, null, null, null);
	if (($lenTitle + $strips1) <= $lenMessage + 13) {
		for ($count = 1; $count <= $strips1 + $lenTitle; $count++) $str1 = $str1 . '=';
		for ($count = 1; $count <= $strips2; $count++) $str2 = $str2 . '=';
	} else {
		for ($count = 1; $count <= $strips1 + $lenTitle - 1; $count++) $str1 = $str1 . '=';
		for ($count = 1; $count <= $strips2; $count++) $str2 = $str2 . '=';
	}
	switch ($error_code) {
		case 404:
			$error_message = "404 (EXTERNAL NOT FOUND!)";
			break;
		case 405:
			$error_message = "405 (INTERNAL NOT FOUND!)";
			break;
		case 406:
			$error_message = "406 (VALUE MISMATCH!)";
			break;
		case 407:
			$error_message = "407 (IS NOT NUMERIC!)";
			break;
		case 408:
			$error_message = "408 (IS NOT ALPHABET!)";
			break;
		case 409:
			$error_message = "409 (IS NOT ALPHANUMERIC!)";
			break;
		case 410:
			$error_message = "410 (IS NOT VALID!)";
			break;
		case 411:
			$error_message = "411 (RETURN FALSE!)";
			break;
		case 411:
			$error_message = "412 (FUNCTION ERROR!)";
			break;
		case 411:
			$error_message = "413 (ERROR CONNECTION!)";
			break;
		default:
			$error_message = "??? (UNKOW ERROR!)";
			break;
	}
	$error_len = strlen($error_message);

	if (($lenTitle + $strips1) <= $lenMessage + 13) {
		if (($error_len + 16) <= strlen($str1)) {
			$to = strlen($str1) - ($error_len + 16);
			for ($count = 0; $count < $to; $count++) {
				if (!isset($spaces[0])) $spaces[0] = null;
				$spaces[0] = $spaces[0] . ' ';
			}
			$txt = "$str1\r\n$str2 REPORT/ERROR $str2\r\n$str1\r\n= MESSAGE: $message =\r\n= ERROR_CODE: $error_message $spaces[0]=\r\n$str1";
		} else {
			$to = strlen($str1) - ($error_len + 16);
			for ($count1 = 0; $count1 < ($error_len + 16) - (strlen($message) + 13); $count1++) {
				if (!isset($spaces[0])) $spaces[0] = null;
				$spaces[0] = $spaces[0] . ' ';
			}
			for ($count2 = 0; $count2 < $to; $count2++) {
				if (!isset($spaces[1])) $spaces[1] = null;
				$spaces[1] = $spaces[1] . ' ';
			}
			for ($count3 = 1; $count3 <= $error_len + 16; $count3++) {
				if (!isset($spaces[2])) $spaces[2] = null;
				$spaces[2] = $spaces[2] . '=';
			}
			$txt = "$spaces[2]\r\n$str2 REPORT/ERROR " . substr($str2, 1) . "\r\n$spaces[2]\r\n= MESSAGE: $message $spaces[0]=\r\n= ERROR_CODE: $error_message $spaces[1]=\r\n$spaces[2]";
		}
	} else {
		if (30 > strlen($message)) {
			if (($error_len + 16) <= strlen($str1)) {
				$to = strlen($str1) - ($error_len + 16);
				for ($count1 = 0; $count1 < 30 - strlen($message); $count1++) {
					if (!isset($spaces[0])) $spaces[0] = null;
					$spaces[0] = $spaces[0] . ' ';
				}
				for ($count2 = 0; $count2 < $to; $count2++) {
					if (!isset($spaces[1])) $spaces[1] = null;
					$spaces[1] = $spaces[1] . ' ';
				}
				$txt = "$str1\r\n$str2 REPORT/ERROR " . substr($str2, 1) . "\r\n$str1\r\n= MESSAGE: $message $spaces[0]=\r\n= ERROR_CODE: $error_message $spaces[1]=\r\n$str1";
			} else {
				$to = strlen($str1) - ($error_len + 16);
				for ($count1 = 0; $count1 < ($error_len + 16) - (strlen($message) + 13); $count1++) {
					if (!isset($spaces[0])) $spaces[0] = null;
					$spaces[0] = $spaces[0] . ' ';
				}
				for ($count2 = 0; $count2 < $to; $count2++) {
					if (!isset($spaces[1])) $spaces[1] = null;
					$spaces[1] = $spaces[1] . ' ';
				}
				for ($count3 = 1; $count3 <= $error_len + 16; $count3++) {
					if (!isset($spaces[2])) $spaces[2] = null;
					$spaces[2] = $spaces[2] . '=';
				}
				$txt = "$spaces[2]\r\n$str2 REPORT/ERROR " . substr($str2, 1) . "\r\n$spaces[2]\r\n= MESSAGE: $message $spaces[0]=\r\n= ERROR_CODE: $error_message $spaces[1]=\r\n$spaces[2]";
			}
		} else {
			if (($error_len + 16) <= strlen($str1)) {
				$to = strlen($str1) - ($error_len + 16);
				for ($count1 = 0; $count1 < 30 - strlen($message); $count1++) {
					if (!isset($spaces[0])) $spaces[0] = null;
					$spaces[0] = $spaces[0] . ' ';
				}
				for ($count2 = 0; $count2 < $to; $count2++) {
					if (!isset($spaces[1])) $spaces[1] = null;
					$spaces[1] = $spaces[1] . ' ';
				}
				$txt = "$str1\r\n$str2 REPORT/ERROR " . substr($str2, 1) . "\r\n$str1\r\n= MESSAGE: $message $spaces[0]=\r\n= ERROR_CODE: $error_message $spaces[1]=\r\n$str1";
			} else {
				$to = strlen($str1) - ($error_len + 16);
				for ($count1 = 0; $count1 < ($error_len + 16) - (strlen($message) + 13); $count1++) {
					if (!isset($spaces[0])) $spaces[0] = null;
					$spaces[0] = $spaces[0] . ' ';
				}
				for ($count2 = 0; $count2 < $to; $count2++) {
					if (!isset($spaces[1])) $spaces[1] = null;
					$spaces[1] = $spaces[1] . ' ';
				}
				for ($count3 = 1; $count3 <= $error_len + 16; $count3++) {
					if (!isset($spaces[2])) $spaces[2] = null;
					$spaces[2] = $spaces[2] . '=';
				}
			}
		}
	}
	fwrite($file, $txt);
	fclose($file);
	if ($exit === true) exit();
}
function delete_all_between($beginning, $end, $string) {
	$beginningPos = strpos($string, $beginning);
	$endPos = strpos($string, $end);
	if($beginningPos === false || $endPos === false) {
		return $string;
	}
	$textToDelete = substr($string, $beginningPos, ($endPos + strlen($end)) - $beginningPos);

	return delete_all_between($beginning, $end, str_replace($textToDelete, '', $string)); // recursion to ensure all occurrences are replaced
}
function exceptionLog(array $config, string $type = 'warning', bool $hideTrigger = false) {
	$defaultConfig = array(
		'title' => '',
		'message' => '',
		'code' => 1,
		'severity' => E_USER_WARNING,
		'filename' => null,
		'line' => null,
		'data' => array(),
		'previous' => null
	);
	$_config = arr2Obj(array_replace_recursive($defaultConfig, array_intersect_key($config, $defaultConfig)));
	$htmlOutput = $GLOBALS['whoops']->handleException(new DumpException($_config->title, $_config->message, $_config->code, $_config->severity, $_config->filename, $_config->line, $_config->previous, $_config->data));
	if ($hideTrigger) {
		$htmlOutput = delete_all_between('/* START_HIDE */', '/* STOP_HIDE */', $htmlOutput);
	}
	$dirWhoops = sprintf('%s/debug', DIR_REPORT);
	isExistDir($dirWhoops, true);
	$fileOutput = sprintf('%s/%s_debug-%s.html', $dirWhoops, $type, date('YmdHis'));
	file_put_contents($fileOutput, $htmlOutput, FILE_APPEND | LOCK_EX);
	return file_exists($fileOutput);
}
function logQuery($pathToWrite = '.', $theCode = null, $theMessage = null, $theData = '', $theQuery = '', $outputToPage = false) {
	if ($pathToWrite == '' || $theQuery == '') {
		return false;
	}
	ob_flush();
	ob_start(); // Start buffering
	echo 'ERROR_INFO:' . PHP_EOL;
	echo ' - Code: ' . $theCode . PHP_EOL;
	echo ' - Message: ' . $theMessage . PHP_EOL;
	echo PHP_EOL;
	echo 'ERROR_QUERY:' . PHP_EOL;
	echo $theQuery;
	echo PHP_EOL . PHP_EOL;
	echo 'ERROR_DATA:' . PHP_EOL;
	var_dump($theData);
	$output = ob_get_contents(); // Get the result from buffer
	if (!$outputToPage) {
		ob_end_clean();
	} // Close buffer
	isExistDir($pathToWrite, true);
	file_put_contents($pathToWrite . '/QUERY-LOG_[' . date('d-m-Y') . ']+[' . date('H.i.s') . '].log', $output);
}
function daysNumeric($val, $locale = 'id_ID') {
	if (strtolower(trim($val)) != 'all' && !is_int($val)) return false;

	// Days in Number
	$daysNumeric = array(
		'id_ID' => array(
			1 => 'Senin',
			2 => 'Selasa',
			3 => 'Rabu',
			4 => 'Kamis',
			5 => 'Jumat',
			6 => 'Sabtu',
			7 => 'Minggu'
		),
		'en_US' => array(
			1 => 'Monday',
			2 => 'Tuesday',
			3 => 'Wednesday',
			4 => 'Thursday',
			5 => 'Friday',
			6 => 'Saturday',
			7 => 'Sunday'
		)
	);

	return (strtolower(trim($val)) == 'all') ? $daysNumeric[$locale] : $daysNumeric[$locale][$val];
}
/**
 * Generate an array of string dates between 2 dates
 *
 * @param string $start Start date
 * @param string $end End date
 * @param string $format Output format (Default: Y-m-d)
 *
 * @return array
 */
function getDatesFromRange($start, $end, $format = 'Y-m-d') {
	$array = array();
	$interval = new DateInterval('P1D');

	$realEnd = new DateTime($end);
	$realEnd->add($interval);

	$period = new DatePeriod(new DateTime($start), $interval, $realEnd);

	foreach($period as $date) { 
		$array[] = $date->format($format); 
	}

	return $array;
}
function getMimeType($filename) {
	$idx = explode( '.', $filename );
	$count_explode = count($idx);
	$idx = strtolower($idx[$count_explode-1]);

	$mimet = array( 
		'txt' => 'text/plain',
		'htm' => 'text/html',
		'html' => 'text/html',
		'php' => 'text/html',
		'css' => 'text/css',
		'js' => 'application/javascript',
		'json' => 'application/json',
		'xml' => 'application/xml',
		'swf' => 'application/x-shockwave-flash',
		'flv' => 'video/x-flv',

		// images
		'png' => 'image/png',
		'jpe' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'jpg' => 'image/jpeg',
		'gif' => 'image/gif',
		'bmp' => 'image/bmp',
		'ico' => 'image/vnd.microsoft.icon',
		'tiff' => 'image/tiff',
		'tif' => 'image/tiff',
		'svg' => 'image/svg+xml',
		'svgz' => 'image/svg+xml',

		// archives
		'zip' => 'application/zip',
		'rar' => 'application/x-rar-compressed',
		'exe' => 'application/x-msdownload',
		'msi' => 'application/x-msdownload',
		'cab' => 'application/vnd.ms-cab-compressed',

		// audio/video
		'mp3' => 'audio/mpeg',
		'qt' => 'video/quicktime',
		'mov' => 'video/quicktime',

		// adobe
		'pdf' => 'application/pdf',
		'psd' => 'image/vnd.adobe.photoshop',
		'ai' => 'application/postscript',
		'eps' => 'application/postscript',
		'ps' => 'application/postscript',

		// ms office
		'doc' => 'application/msword',
		'rtf' => 'application/rtf',
		'xls' => 'application/vnd.ms-excel',
		'ppt' => 'application/vnd.ms-powerpoint',
		'docx' => 'application/msword',
		'xlsx' => 'application/vnd.ms-excel',
		'pptx' => 'application/vnd.ms-powerpoint',

		// open office
		'odt' => 'application/vnd.oasis.opendocument.text',
		'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
	);

	if (isset($mimet[$idx])) {
		return $mimet[$idx];
	} else {
		return 'application/octet-stream';
	}
}

/*** API Functions ***/
// Check tanggal merah
// Input: YYYYMMDD / 20220631
function fetchTanggalMerah() {
	$dataJSON = json_decode(file_get_contents("https://raw.githubusercontent.com/guangrei/Json-Indonesia-holidays/master/calendar.json"), true);
	return $dataJSON;
}
function isTanggalMerah($value) {
	$dataJSON = fetchTanggalMerah();
	$output = false;

	// Check tanggal merah berdasarkan libur nasional
	if (isset($dataJSON[$value])) {
		$output = true;
	}
	// Check tanggal merah berdasarkan hari minggu
	elseif (date("D", strtotime($value)) === "Sun") {
		$output = true;
	}
	// Bukan tanggal merah
	else {
		$output = false;
	}
}
