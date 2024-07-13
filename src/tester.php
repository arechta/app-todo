<?php
// Required every files to load
$thisPath = (defined('DIR_ROOT') ? DIR_CONFIG : dirname(__FILE__).'/configs');
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
use APP\includes\classes\User;
use APP\includes\classes\Notification;
use APP\includes\classes\EncryptionVW;
use \Ifsnop\Mysqldump as IMysqldump;
use APP\includes\classes\Blackbox;
use Ramsey\Uuid\Uuid;

session_start();

$list = array(
	'some_first_category' => Array(
		'id' => 'asdasd',
		'json' => '{}',
		'id_kpi' => array(
			0 => 'first@email.com',
			1 => 'second@email.com',
			2 => 'third@email.com',
			3 => 'fourth@email.com'
		)
	),
	'some_second_category' => array(
		'id' => 'bcdasda',
		'json' => '{}',
		'id_kpi' => array(
			0 => 'first@email.com',
			1 => 'second@email.com'
		)
	),
	'some_three_category' => array(
		'id' => 'cdaddasd',
		'json' => '{}',
		'id_kpi' => Array(
			0 => 'first@email.com',
			1 => 'second@email.com',
			2 => 'third@email.com',
			3 => 'fourth@email.com',
			4 => 'fourth@email.com'
		)
	)
);

// $list = array_map(function ($v) {
// 	// pre_dump($v);
//     uasort($v['id_kpi'], function ($a, $b) {
//         $a = count($a);
//         $b = count($b);
//         return ($a == $b) ? 0 : (($a < $b) ? 1 : - 1);
//     });
//     return $v['id_kpi'];
// }, $list);

function cmp($a, $b){
    return (count($b['id_kpi']) - count($a['id_kpi']));
}
usort($list, 'cmp');
pre_dump($list);
exit();
/*
// Method URL Scrapping Content 
use \GuzzleHttp as Guzzle;
$httpClient = new Guzzle\Client();
$response = $httpClient->get('https://books.toscrape.com/');
$htmlString = (string) $response->getBody();
//add this line to suppress any warnings
libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->loadHTML($htmlString);
$xpath = new DOMXPath($doc);

$titles = $xpath->evaluate('//ol[@class="row"]//li//article//h3/a');
$prices = $xpath->evaluate('//ol[@class="row"]//li//article//div[@class="product_price"]/p');
$bookDetail = [];
foreach ($titles as $idx => $title) {
	$bookDetail[$idx]['title'] = trim($title->textContent);
}
foreach ($prices as $idx2 => $price) {
	if(isset($bookDetail[$idx2]) && array_key_exists('title', $bookDetail[$idx2])) {
		$bookDetail[$idx2]['price'] = trim($price->textContent);
	}
}
foreach ($bookDetail as $perBook) {
	echo 'Book: ' . $perBook['title'] . ' (Price: '.$perBook['price'].')<br>';
}
exit();
*/
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?= $APP_CORE['app_name']; ?> | {{ htmlWebpackPlugin.options.title }}</title>
	</head>
	<body class="bg-primary">
		<canvas id="dotGrid"></canvas>
	</body>
</html>
<?php exit(); ?>
		<section class="container mt-3">
			<div id="tester" class="card">
				<div class="card-body">
					<nav class="navbar navbar-light animate__animated animate__fadeIn">
						<div class="container-fluid">
							<a href="http://www.mtp-logistics.com/" class="navbar-brand w-100">
								<img src="./asset/image/logo/logo.png" alt="Logo MTP" width="60" class="d-inline-block me-2" />
								<h6 class="d-inline-block fnt-style1 text-primary" data-weight="bold"><?= $APP_CORE['company_name']; ?></h6>
							</a>
						</div>
					</nav>
					<section class="tester-content">
						<div class="form-group mb-2">
							<h1>
								<div class="badge bg-primary">DATA Test</div>
							</h1>
							<?php
							?>
						</div>
						<div class="form-group mb-2">
							<h1>
								<div class="badge bg-primary">$_SESSION</div>
							</h1>
							<?php pre_dump($_SESSION); ?>
						</div>
						<div class="form-group mb-2">
							<h1>
								<div class="badge bg-primary">$_POST</div>
							</h1>
							<?php pre_dump($_POST); ?>
						</div>
						<div class="form-group mb-2">
							<h1>
								<div class="badge bg-primary">$_GET</div>
							</h1>
							<?php pre_dump($_GET); ?>
							<?php
								$request_body = file_get_contents('php://input');
								$data = json_decode($request_body, true);
								pre_dump($data);
							?>
						</div>
						<div class="form-group mb-2">
							<h1>
								<div class="badge bg-primary">$_REQUEST</div>
							</h1>
							<?php pre_dump($_REQUEST); ?>
						<div>
						<div class="form-group mb-2">
							<h1>
								<div class="badge bg-primary">$_SERVER</div>
							</h1>
							<?php pre_dump($_SERVER); ?>
						<div>
					</section>
				</div>
			</div>
		</section>
	</body>
</html>