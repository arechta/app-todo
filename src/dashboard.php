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
use APP\includes\classes\EncryptionVW;

// include(DIR_APP.'/includes/check-session.inc.php'); // Check current session of User status logged-in
// include DIR_APP.'/includes/check-homepage.inc.php'; // Check primary user home-page, based on permission
// include(DIR_APP.'/includes/check-authority.inc.php'); // Check permission of current session

// $user = new User();
// $EVW = new EncryptionVW();

// $userData = array('stt', 'nik', 'tkn', 'usr');
// foreach($userData as $idx => $perData) {
// 	$userData[$perData] = $user->getSession($perData);
// 	$userData[$perData] = ($userData[$perData]['success']) ? $userData[$perData]['data'] : null;
// 	unset($userData[$idx]);
// }
// $userData = arr2Obj($userData);
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8"/>
		<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
		<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
		<title><?= $APP_CORE['app_name']; ?> | {{ htmlWebpackPlugin.options.title }}</title>
	</head>
	<body>
		<nav id="navbar" class="navbar">
			<div class="container-fluid">
				<a class="navbar-brand" href="#!">
					<img src="./asset/image/logo/logo-white.svg" alt="Logo"/>
					<p class="navbar-brand-title">Todo List</p>
				</a>
			</div>
		</nav>
		<section id="content" class="page-content">
			<section class="list-wrapper">
				<div class="list-item">
					<div class="list-head col-auto">
						<div class="list-head-wrapper">
							<div class="list-head-title">
								<h4 class="list-title">Test</h4>
								<input type="text" value="Test" class="input-list-title d-none" />
							</div>
							<button class="btn btn-action-filter">
								<img src="./asset/image/icons/mdi_filter.svg" alt="Icons Filter" />
							</button>
						</div>
						<div class="list-head-filter d-none">
							asdasdasdasdadasdas
						</div>
					</div>
					<div class="list-body col">
						<section class="card-wrapper">
							<div class="card-item">
								Lorem ipsum dolor sit amet consectetur, adipisicing elit. Cum doloribus, earum accusamus voluptates aperiam, natus perspiciatis tempora enim voluptate suscipit fugiat. Adipisci ab quo quaerat aut ipsum quae nisi numquam!
								Harum animi culpa, fugit ullam rerum asperiores. Aspernatur ipsam, dolorum tempore, officia rem sit tempora maiores architecto molestiae incidunt deserunt maxime quo natus consectetur quae, dolore quos eum hic aliquam.
							</div>
							<div class="card-item">
								Lorem ipsum dolor sit amet consectetur, adipisicing elit. Cum doloribus, earum accusamus voluptates aperiam, natus perspiciatis tempora enim voluptate suscipit fugiat. Adipisci ab quo quaerat aut ipsum quae nisi numquam!
								Harum animi culpa, fugit ullam rerum asperiores. Aspernatur ipsam, dolorum tempore, officia rem sit tempora maiores architecto molestiae incidunt deserunt maxime quo natus consectetur quae, dolore quos eum hic aliquam.
								Iste repudiandae necessitatibus consectetur! Eos sapiente ipsa, eaque illum aliquam voluptas esse doloribus debitis similique ex quod! Repellendus quos repudiandae recusandae ratione provident quod voluptatum fugiat, molestias, commodi vel explicabo?
								Lorem ipsum dolor sit amet consectetur adipisicing elit. Itaque, iste illum? Repudiandae consequatur expedita labore asperiores perspiciatis laboriosam similique deserunt reiciendis, quae architecto dolore, non sapiente fugiat sed, omnis veritatis!
								Perferendis dolorem nam, eos voluptate dolore molestiae repellat temporibus quis ab in incidunt illum eaque perspiciatis, eligendi ducimus eum est, dolor excepturi. Doloribus accusamus quisquam cum expedita sit illum praesentium?
								Officia eligendi dolores quaerat saepe earum impedit libero, aut omnis officiis in excepturi dolorem porro repellat perferendis ipsum, quod expedita non, doloremque numquam nemo quisquam praesentium iusto amet beatae? Quos.
							</div>

						</section>
					</div>
					<div class="list-foot col-auto">
						<div class="card-input-wrapper d-none">
							<textarea type="text" value="Test" class="input-card-title" placeholder="Enter a title for this card…"></textarea>
							<div class="row w-100 gx-1">
								<div class="col-auto">
									<button class="btn btn-success btn-card-add">Add card</button>
								</div>
								<div class="col-auto">
									<button class="btn btn-card-cancel">
										<img src="./asset/image/icons/ep_close-bold.svg" alt="Icons Close" />
									</button>
								</div>
							</div>
						</div>
						<div class="card-trigger-wrapper">
							<button class="btn btn-action-add">
								<img src="./asset/image/icons/mingcute_add-fill.svg" alt="Icons Add"/>
								<p class="fnt-style1">Add a card</p>
							</button>
						</div>
					</div>
				</div>
				<div class="list-item">
					<div class="list-head col-auto">
						<div class="list-head-wrapper">
							<div class="list-head-title">
								<h4 class="list-title">Test</h4>
								<input type="text" value="Test" class="input-list-title d-none" />
							</div>
							<button class="btn btn-action-filter">
								<img src="./asset/image/icons/mdi_filter.svg" alt="Icons Filter" />
							</button>
						</div>
						<div class="list-head-filter d-none">
							asdasdasdasdadasdas
						</div>
					</div>
					<div class="list-body col">
						<section class="card-wrapper">
						</section>
					</div>
					<div class="list-foot col-auto">
						<div class="card-input-wrapper">
							<textarea type="text" value="Test" class="input-card-title" placeholder="Enter a title for this card…"></textarea>
							<div class="row w-100 gx-1">
								<div class="col-auto">
									<button class="btn btn-success btn-card-add">Add card</button>
								</div>
								<div class="col-auto">
									<button class="btn btn-card-cancel">
										<img src="./asset/image/icons/ep_close-bold.svg" alt="Icons Close" />
									</button>
								</div>
							</div>
						</div>
						<div class="card-trigger-wrapper d-none">
							<button class="btn btn-action-add">
								<img src="./asset/image/icons/mingcute_add-fill.svg" alt="Icons Add"/>
								<p class="fnt-style1">Add a card</p>
							</button>
						</div>
					</div>
				</div>
				<div class="list-item is-trigger">
					<div class="add-trigger">
						<img src="./asset/image/icons/mingcute_add-fill.svg" alt="Icons Add"/>	
						<p class="fnt-style1">Add another list</p>
					</div>
				</div>
			</section>
		</section>
	</body>
</html>
