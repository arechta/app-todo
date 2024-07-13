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
get('/login', 'login.php'); // Login form
get('/forgot-account', 'forgot-account.php'); // Forgot account form
get('/session-list', 'session-list.php'); // Session list pages
get('/logout', 'app/includes/logout.inc.php'); // Logout action
get('/app/includes/logout.inc', 'app/includes/logout.inc.php'); // Logout action
get('/app/includes/logout.inc.php', 'app/includes/logout.inc.php'); // Logout action
get('/profile', '/profile.php'); // Profile: General
get('/profile/$menu', '/profile.php'); // Profile: <Menu>
// get('/register', 'register.php'); // Register pages
get('/dashboard', 'dashboard.php'); // Dashboard pages
get('/sales', 'sales.php'); // Sales pages
// get('/issues', 'issues.php'); // Issues pages
// get('/customer', 'customer.php'); // Customer pages
// any('/hotfix', 'hotfix-update.php');
any('/tester', 'tester.php');
any('/tester/whoops', 'tester/whoops/example.php');
// any('/docs', 'docs/index.html');

get('/manage/kpi', 'pages/manage-kpi.php'); // Manage KPI pages
get('/approval/kpi', 'pages/approval-kpi.php'); // Approval KPI pages
get('/master/kpi', 'pages/master-kpi.php'); // Master KPI pages
get('/master/employee', 'pages/master-employee.php'); // Master Employee pages
get('/master/position', 'pages/master-position.php'); // Master Position pages
get('/master/division', 'pages/master-division.php'); // Master Division pages
get('/master/department', 'pages/master-department.php'); // Master Department pages
get('/master/directorate', 'pages/master-directorate.php'); // Master Directorate pages

get('/maintenance/storage-temperature-humidity', 'pages/storage-temperature-humidity.php'); // Storage Temperature & Humidity pages
get('/maintenance/running-hour-battery', 'pages/running-hour-battery.php'); // Running Hour Battery pages

/*
 * Resources URL
 */
// View private/public files
get('/app/includes/view-document.inc', 'app/includes/view-document.inc.php');
get('/app/includes/view-document.inc.php', 'app/includes/view-document.inc.php');
get('/files/$uid', 'app/includes/files.inc.php');
get('/view/document/$uid', 'app/includes/view-document.inc.php');
post('/app/includes/upload-files.inc', 'app/includes/upload-files.inc.php');
post('/app/includes/upload-files.inc.php', 'app/includes/upload-files.inc.php');
post('/upload/files', 'app/includes/upload-files.inc.php');
post('/app/includes/upload-images.inc', 'app/includes/upload-images.inc.php');
post('/app/includes/upload-images.inc.php', 'app/includes/upload-images.inc.php');
post('/upload/images', 'app/includes/upload-images.inc.php');
any('/privacy-policy', function() {
	echo '<pre>';
	echo file_get_contents(__DIR__.'/PRIVACY_POLICY.txt');
	'</pre>';
	return true;
});

/*
 * Endpoint URL
 */
// Pages
post('/session-list', '/session-list.php');

// API
post('/api/v1/auth/verify-otp.inc.php', '/api/v1/auth/verify-otp.inc.php');
post('/api/v1/message/provider-whatsapp.inc.php', '/api/v1/message/provider-whatsapp.inc.php');
post('/api/v1/user/login', '/api/v1/user/login.php');
post('/api/v1/user/logout', '/api/v1/user/logout.php');
post('/api/v1/user/status', '/api/v1/user/status.php');
post('/api/v1/user/profiles', '/api/v1/user/profiles.php');
post('/api/v1/user/sessions', '/api/v1/user/sessions.php');
post('/api/v1/dc/gate-activity/activities', '/api/v1/dc/gate-activity/activities.php');
post('/api/v1/dc/gate-activity/checkpoint', '/api/v1/dc/gate-activity/checkpoint.php');
post('/api/v1/dc/vehicle-statistic/activities', '/api/v1/dc/vehicle-statistic/activities.php');
post('/api/v1/dc/vehicle-statistic/shift-hour', '/api/v1/dc/vehicle-statistic/shift-hour.php');
post('/api/v1/thread/issues', '/api/v1/thread/issues.php');
post('/api/v1/thread/issue/view', '/api/v1/thread/issue/view.php');
post('/api/v1/thread/issue/action', '/api/v1/thread/issue/action.php');
post('/api/v1/thread/issue/create', '/api/v1/thread/issue/create.php');
post('/api/v1/thread/issue/replies', '/api/v1/thread/issue/replies.php');

// Includes
post('/app/includes/accounts.inc.php', '/app/includes/accounts.inc.php');
post('/app/includes/check-activity.inc.php', '/app/includes/check-activity.inc.php');
post('/app/includes/check-session.inc.php', '/app/includes/check-session.inc.php');
post('/app/includes/approval-kpi.inc.php', '/app/includes/approval-kpi.inc.php');
post('/app/includes/manage-kpi.inc.php', '/app/includes/manage-kpi.inc.php');
post('/app/includes/master-kpi.inc.php', '/app/includes/master-kpi.inc.php');
post('/app/includes/address.inc.php', '/app/includes/address.inc.php');
post('/app/includes/customer.inc.php', '/app/includes/customer.inc.php');
post('/app/includes/dashboard.inc.php', '/app/includes/dashboard.inc.php');
post('/app/includes/gate-activity.inc.php', '/app/includes/gate-activity.inc.php');
post('/app/includes/vehicle-statistics.inc.php', '/app/includes/vehicle-statistics.inc.php');
post('/app/includes/man-power.inc.php', '/app/includes/man-power.inc.php');
post('/app/includes/throughput-io.inc.php', '/app/includes/throughput-io.inc.php');
post('/app/includes/chamber-storage.inc.php', '/app/includes/chamber-storage.inc.php');
post('/app/includes/storage-temperature-humidity.inc.php', '/app/includes/storage-temperature-humidity.inc.php');
post('/app/includes/running-hour-battery.inc.php', '/app/includes/running-hour-battery.inc.php');
post('/app/includes/issues.inc.php', '/app/includes/issues.inc.php');
post('/app/includes/login.inc.php', '/app/includes/login.inc.php');
post('/app/includes/logout.inc.php', '/app/includes/logout.inc.php');
post('/app/includes/register.inc.php', '/app/includes/register.inc.php');
post('/app/includes/tester.inc.php', '/app/includes/tester.inc.php');
// Features
post('/app/includes/features/live-chat.feat.php', '/app/includes/features/live-chat.feat.php');
post('/app/includes/features/notification.feat.php', '/app/includes/features/notification.feat.php');
// Reports
post('/app/includes/reports/throughput-io.report.php', '/app/includes/reports/throughput-io.report.php');
post('/app/includes/reports/storage-temperature-humidity.report.php', '/app/includes/reports/storage-temperature-humidity.report.php');
post('/app/includes/reports/gate-activity.report.php', '/app/includes/reports/gate-activity.report.php');
post('/app/includes/reports/customer-chamber-usage.report.php', '/app/includes/reports/customer-chamber-usage.report.php');

// ##################################################
// ##################################################
// ##################################################
// any can be used for GETs or POSTs
// For GET or POST
post('/dev', 'dev.php'); // Developer pages
post('/dev.php', 'dev.php'); // Developer pages
any('/dev', 'dev.php'); // Developer pages
any('/dev.php', 'dev.php'); // Developer pages
any('/404','404.php');