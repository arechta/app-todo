/* eslint-disable no-lonely-if */
/* eslint-disable max-len */
/* eslint-disable no-loop-func */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint no-undef: "error" */

module.exports = {
	jsEntry: {
		404: '404.js',
		commons: 'commons.js',
		// Public Page
		index: 'index.js',
		// login: 'login.js',
		// 'forgot-account': 'forgot-account.js',
		// 'session-list': 'session-list.js',
		// profile: 'profile.js',
		// register: 'register.js',

		/* Dashboard */
		dashboard: 'dashboard.js',
	},
	pageList: [
		{
			title: '404',
			favicon: 'favicon.ico',
			template: '404.php',
			javascript: ['404'],
			output: '404.php',
		},
		{
			title: 'Index',
			favicon: 'favicon.ico',
			template: 'index.php',
			javascript: ['commons', 'index'],
			output: 'index.php',
		},
		// Dashboard
		{
			title: 'Dashboard',
			favicon: 'favicon.ico',
			template: 'dashboard.php',
			javascript: ['commons', 'dashboard'],
			output: 'dashboard.php',
		},
		// {
	],
	copyImportant: [
		// {
		// 	from: 'api',
		// 	to: 'api',
		// },
		{
			from: 'app/includes',
			to: 'app/includes',
		},
		{
			from: 'app/view',
			to: 'app/view',
		},
		{
			from: 'configs',
			to: 'configs',
		},
		{
			from: 'asset/image/logo',
			to: 'assets/image/logo',
			asset: true,
		},
		{
			from: 'asset/image/icons',
			to: 'assets/image/icons',
			asset: true,
		},
		{
			from: 'asset/image/illustrations',
			to: 'assets/image/illustrations',
			asset: true,
		},
		{
			from: 'uploads',
			to: 'uploads',
		},
		{
			from: 'vendor',
			to: 'vendor',
		},
		// {
		// 	from: 'tester',
		// 	to: 'tester',
		// },
		{
			from: 'asset/document/test-connection.php',
			to: 'assets/document/test-connection.php',
		},
		{
			from: 'routes.php',
			to: 'routes.php',
		},
		{
			from: 'router.php',
			to: 'router.php',
		},
		'.htaccess',
		'.gitignore',
		'composer.json',
		'composer.lock',
	],
};
