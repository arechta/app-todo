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
$user = new User();
if ($user->isLoggedIn()['success']) {
	// Redirect for User-page
	include DIR_APP.'/includes/check-homepage.inc.php';

	header(sprintf('Location: %s/%s', getURI(2), $userPage));
	// header("Location: " . sprintf('%s/dashboard%s', getURI(2), (EXT_PHP) ? '.php' : ''));
}
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<title><?= $APP_CORE['app_name']; ?> | {{ htmlWebpackPlugin.options.title }}</title>
	</head>
	<body class="bg-orange">
		<div class="container center-content vh-100">
			<!-- Card View: ForgotAccount -->
			<div id="cardForgotAccount" class="cardview position-relative d-none">
				<div id="enterYourCredentials" class="step-progress" style="opacity: 0;">
					<section class="row d-flex flex-column flex-nowrap p-5">
						<div class="col-auto mb-5">
							<div class="cardview-title center-content text-dark fs-5 mb-2 fn-s3-semibold">
								<b>Enter your Credentials</b>
							</div>
							<div class="cardview-content center-content text-center fn-s3-regular text-gray">
								<p class="text-center" style="font-size: 14px">
									Please input your account identity below <br> to select the forgotten account
								</p>
							</div>
						</div>
						<div class="col">
							<form id="formCredentials" class="position-relative mb-2" method="POST" enctype="application/x-www-form-urlencoded" novalidate>
								<div class="form-group are-input-1">
									<div class="input-group mb-3 has-validation">
										<span class="input-group-text"><img src="./asset/image/icons/bxs_id-card.svg" alt="Icons Account ID" width="18px" height="18px" /></span>
										<div class="form-floating">
											<input type="text" name="account-id" class="form-control" id="inputAccountID" placeholder="Enter your ID, Phone-number, or Email" />
											<label for="inputAccountID">Enter your ID, Phone-number, or Email</label>
										</div>
									</div>
								</div>
								<p class="alert alert-warning fnt-style1 d-block w-100 rounded mt-3 mb-0" role="alert" data-size="caption"></p>
								<div class="d-grid gap-2 mt-4">
									<button type="submit" name="login" class="btn btn-orange fn-s3-semibold p-2" style="border: none; font-size: 14px">
										Continue
									</button>
								</div>
							</form>
							<p class="center-content mt-3 text-decoration-none text-dark fst-italic fnt-style1" style="font-size: 13px">
								Remember your account? let's
								<a href="login<?= (EXT_PHP) ? '.php' : ''; ?>" class="text-blue text-decoration-underline ps-1">Login</a>
							</p>
						</div>
					</section>
				</div>
				<div id="confirmItsYou" class="step-progress d-none">
					<section class="row d-flex flex-column flex-nowrap p-5">
						<div class="col-auto mb-4">
							<div class="cardview-title center-content text-dark fs-5 mb-2 fn-s3-semibold">
								<b>Confirm it is You</b>
							</div>
							<div class="cardview-content text-center fn-s3-regular text-gray">
								<div class="form-group are-input-1 mb-2">
									<div class="switch-toggle">
										<input type="radio" name="confirm-method" value="send-code" id="confirmSendCode" class="switcher-input" checked="true">
										<label for="confirmSendCode" class="switcher-label">Send code</label>
										<input type="radio" name="confirm-method" value="recovery-keys" id="confirmRecoveryKeys" class="switcher-input">
										<label for="confirmRecoveryKeys" class="switcher-label">Recovery keys</label>
										<span class="switcher-toggle"></span>
									</div>
								</div>
							</div>
						</div>
						<div class="col">
							<div class="switch-option">
								<form id="formSendCode" class="switcher-form position-relative mb-2" method="POST" enctype="application/x-www-form-urlencoded" novalidate>
									<p class="fnt-style1 text-center text-dark mb-3" data-weight="regular" style="font-size:12px;">A verification code will be sent to your Email-address or Phone-number, check regularly. <button type="button" class="btn-send-code">still Didn't get the code?</button></p>
									<div class="form-group are-input-1">
										<div class="input-group mb-3 has-validation">
											<span class="input-group-text"><img src="./asset/image/icons/ri_shield-user-fill.svg" alt="Icons Verification" width="18px" height="18px" /></span>
											<div class="form-floating">
												<input type="text" name="verification-code" class="form-control" id="inputVerificationCode" placeholder="Enter your Verification Code" />
												<label for="inputVerificationCode">Enter your Verification Code</label>
											</div>
										</div>
									</div>
									<p class="alert alert-warning fnt-style1 d-block w-100 rounded mt-3 mb-0" role="alert" data-size="caption"></p>
									<div class="d-grid gap-2 mt-4">
										<button type="submit" class="btn btn-orange fn-s3-semibold p-2" style="border: none; font-size: 14px">
											Verify
										</button>
									</div>
								</form>
								<form id="formRecoveryKeys" class="switcher-form position-relative mb-2 d-none	" method="POST" enctype="application/x-www-form-urlencoded" novalidate>
									<p class="fnt-style1 text-center text-dark mb-3" data-weight="regular" style="font-size:12px;">Please enter the recovery key that you created and saved earlier</p>
									<div class="form-group are-input-1">
										<div class="input-group mb-3 has-validation">
											<span class="input-group-text"><img src="./asset/image/icons/carbon_code-signing-service.svg" alt="Icons Recovery-keys" width="18px" height="18px" /></span>
											<div class="form-floating">
												<input type="text" name="recovery-keys" class="form-control" id="inputRecoveryKeys" placeholder="Enter your Recovery-keys" />
												<label for="inputRecoveryKeys">Enter your Recovery-keys</label>
											</div>
										</div>
									</div>
									<p class="alert alert-warning fnt-style1 d-block w-100 rounded mt-3 mb-0" role="alert" data-size="caption"></p>
									<div class="d-grid gap-2 mt-4">
										<button type="submit" class="btn btn-orange fn-s3-semibold p-2" style="border: none; font-size: 14px">
											Verify
										</button>
									</div>
								</form>
							</div>
							<div class="center-content text-center w-100 d-block mt-2">
								<button class="btn-cancel">
									Go back?
								</button>
							</div>
						</div>
					</section>
				</div>
				<div id="createNewPassword" class="step-progress d-none">
					<section class="row d-flex flex-column flex-nowrap p-5">
						<div class="col-auto mb-5">
							<div class="cardview-title center-content text-dark fs-5 mb-2 fn-s3-semibold">
								<b>Create a new Password</b>
							</div>
							<div class="cardview-content center-content text-center fn-s3-regular text-gray">
								<p class="text-center" style="font-size: 14px">
									Make sure you create a strong password, easy to remember for you, and not easy to guess.
								</p>
							</div>
						</div>
						<div class="col">
							<form id="formCreateNewPassword" class="position-relative mb-2" method="POST" enctype="application/x-www-form-urlencoded" novalidate>
								<div class="form-group are-input-1">
									<div class="input-group mb-3 has-validation">
										<span class="input-group-text"><img src="./asset/image/icons/fluent_password-16-filled.svg" alt="Icons Account Password" width="18px" height="18px" /></span>
										<div class="form-floating">
											<input type="password" name="new-password" class="form-control" id="inputNewPassword" placeholder="Enter your new Password" />
											<label for="inputNewPassword">Enter your new Password</label>
										</div>
									</div>
								</div>
								<div class="form-group are-input-1">
									<div class="input-group mb-3 has-validation">
										<span class="input-group-text"><img src="./asset/image/icons/fluent_password-16-filled.svg" alt="Icons Account Password" width="18px" height="18px" /></span>
										<div class="form-floating">
											<input type="password" name="new-password-confirm" class="form-control" id="inputNewPasswordConfirm" placeholder="Confirm your new Password" />
											<label for="inputNewPasswordConfirm">Confirm your new Password</label>
										</div>
									</div>
								</div>
								<p class="alert alert-warning fnt-style1 d-block w-100 rounded mt-3 mb-0" role="alert" data-size="caption"></p>
								<div class="d-grid gap-2 mt-4">
									<button type="submit" class="btn btn-orange fn-s3-semibold p-2" style="border: none; font-size: 14px">
										Change password
									</button>
								</div>
							</form>
							<div class="center-content text-center w-100 d-block mt-2">
								<button class="btn-cancel">
									Go back?
								</button>
							</div>
						</div>
					</section>
				</div>
				<div id="completeMessage" class="step-progress d-none">
					<section class="row d-flex flex-column flex-nowrap p-5">
						<div class="col-auto mb-2">
							<div class="cardview-title center-content text-dark fs-5 mb-2 fn-s3-semibold">
								<b>Congratulations!</b>
							</div>
							<div class="cardview-content center-content text-center fn-s3-regular text-gray">
								<p class="text-center" style="font-size:14px;">
									You have successfully reset your password, make sure to save your password in safest place
								</p>
							</div>
						</div>
						<div class="col">
							<div class="lottie-animation"></div>
							<div class="center-content text-center w-100 d-block mt-2">
								<button class="btn-complete">
									Thank you!
								</button>
							</div>
						</div>
					</section>
				</div>
			</div>
		</div>
		<!-- Hosted app URL -->
		<input id="hostURL" type="hidden" value="<?= getURI(2); ?>"/>
	</body>
</html>