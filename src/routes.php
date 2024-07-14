<?php
require_once __DIR__.'/router.php';

// ##################################################
// ##################################################
// ##################################################
/*
 * Pages URL
 */
get('/', 'index.php'); // Landing pages
get('/index', 'index.php'); // Landing pages
// get('/login', 'login.php'); // Login form
// get('/forgot-account', 'forgot-account.php'); // Forgot account form
// get('/session-list', 'session-list.php'); // Session list pages
// get('/logout', 'app/includes/logout.inc.php'); // Logout action
// get('/app/includes/logout.inc', 'app/includes/logout.inc.php'); // Logout action
// get('/app/includes/logout.inc.php', 'app/includes/logout.inc.php'); // Logout action
// get('/profile', '/profile.php'); // Profile: General
// get('/profile/$menu', '/profile.php'); // Profile: <Menu>
// get('/register', 'register.php'); // Register pages
get('/dashboard', 'dashboard.php'); // Dashboard pages
// any('/hotfix', 'hotfix-update.php');
// any('/tester', 'tester.php');
// any('/tester/whoops', 'tester/whoops/example.php');
// any('/docs', 'docs/index.html');

/*
 * Resources URL
 */
// View private/public files
// any('/privacy-policy', function() {
// 	echo '<pre>';
// 	echo file_get_contents(__DIR__.'/PRIVACY_POLICY.txt');
// 	'</pre>';
// 	return true;
// });

/*
 * Endpoint URL
 */
// Pages
// post('/session-list', '/session-list.php');

// Includes
post('/app/includes/todo.inc.php', '/app/includes/todo.inc.php');
// post('/app/includes/accounts.inc.php', '/app/includes/accounts.inc.php');
// post('/app/includes/check-activity.inc.php', '/app/includes/check-activity.inc.php');
// post('/app/includes/check-session.inc.php', '/app/includes/check-session.inc.php');
// post('/app/includes/dashboard.inc.php', '/app/includes/dashboard.inc.php');
// post('/app/includes/login.inc.php', '/app/includes/login.inc.php');
// post('/app/includes/logout.inc.php', '/app/includes/logout.inc.php');
// post('/app/includes/register.inc.php', '/app/includes/register.inc.php');


// ##################################################
// ##################################################
// ##################################################
// any can be used for GETs or POSTs
// For GET or POST
any('/404','404.php');