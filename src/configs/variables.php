<?php
// error_reporting(E_ALL & ~(E_STRICT|E_NOTICE));
setlocale(LC_TIME, 'id_ID.utf8', 'id_ID.utf-8', 'id_ID.8859-1', 'id_ID', 'IND.UTF8', 'IND.utf-8', 'IND.8859-1', 'IND', 'Indonesian.UTF8', 'Indonesian.utf-8', 'Indonesian.8859-1', 'Indonesian', 'Indonesia', 'id', 'ID', 'en_US.UTF8', 'en_US.utf-8', 'en_US.8859-1', 'en_US', 'American', 'ENG', 'English');
setlocale(LC_MONETARY, 'id_ID.utf8', 'id_ID.utf-8', 'id_ID.8859-1', 'id_ID', 'IND.UTF8', 'IND.utf-8', 'IND.8859-1', 'IND', 'Indonesian.UTF8', 'Indonesian.utf-8', 'Indonesian.8859-1', 'Indonesian', 'Indonesia', 'id', 'ID', 'en_US.UTF8', 'en_US.utf-8', 'en_US.8859-1', 'en_US', 'American', 'ENG', 'English');
date_default_timezone_set('Asia/Jakarta');

/*/////////////////////////////////////
// MENDEFINISIKAN VARIABEL KONSTANTA //
/////////////////////////////////////*/
$webURL = $_SERVER['DOCUMENT_ROOT'];
$docRoot = (strtoupper(substr(PHP_OS,0,3)) === 'WIN') ? str_replace('\\', '/', dirname(__FILE__, 2)) : dirname(__FILE__, 2);
// DIRECTORY
define('DIR_ROOT', ($webURL != $docRoot) ? $webURL.str_replace($webURL, '', $docRoot) : $webURL);
define('DIR_APP', DIR_ROOT.'/app');
define('DIR_CONFIG', DIR_ROOT.'/configs');
define('DIR_ASSET', DIR_ROOT.'/assets');
define('DIR_UPLOAD', DIR_ROOT.'/uploads');
define('DIR_PLUGIN', DIR_ROOT.'/plugin');
define('DIR_VENDOR', DIR_ROOT.'/vendor');
define('DIR_REPORT', DIR_ROOT.'/report');
// FILES
define('FILE_SETTING', DIR_CONFIG.'/app-setting.json.php');
// URL
define('URL_HOST', 'http://localhost/');
// RULES
define('EXT_PHP', false);
// API
define('API_VERSION', 'v1');
define('API_BASEURL', DIR_ROOT.'/api/'.API_VERSION);
define('API_USE_HOST', true);

// Whoops Library
include __DIR__.'/../vendor/autoload.php';

use APP\includes\classes\DumpException;
use Whoops\Run as Whoops;
use Whoops\Handler\PrettyPageHandler;
$whoops = new Whoops;
$handler = new PrettyPageHandler;
$handler->setEditor('vscode');
$handler->addDataTableCallback('Details', function (\Whoops\Exception\Inspector $inspector) {
	$data = array();
	$exception = $inspector->getException();
	$data['Exception class'] = get_class($exception);
	if ($exception instanceof DumpException) {
		$data['Exception data'] = json_encode($exception->getData(), JSON_UNESCAPED_SLASHES);
		$allFrames = $inspector->getFrames();
		$lastFrame = $allFrames->offsetGet(0);
		$lastFrame->addComment(sprintf("%s", $exception->getDevNote()), 'Developer Note: ');
	}
	$data['Exception code'] = $exception->getCode();
	return $data;
});
$whoops->allowQuit(false);
$whoops->writeToOutput(false);
$whoops->pushHandler($handler);
$whoops->pushHandler(function($exception, $inspector, $run) {
	$frames = $inspector->getFrames();
	// Filter existing frames so we only keep the ones inside the app/ folder 
	$frames->filter(function($frame) {
		$filePath = $frame->getFile();
		$fileName = basename($filePath, '.php');
		$funcName = $frame->getFunction() ?? null;
		$isCustomFunction = (!is_null($funcName) && $funcName === 'exceptionLog');

		// Condition
		return !in_array($fileName, array('router', 'routes')) && !$isCustomFunction;
	});
	$frames->map(function ($frame) {
		$arguments = $frame->getArgs();
		$funcName2 = null;
		$className = null;
		if ($funcName2 = $frame->getFunction()) {
			$funcName2 = sprintf("Function '%s'", $funcName2);
		}
		if ($className = $frame->getClass()) {
			$className = !preg_match('/DumpException/i', $className) ? sprintf("Class '%s'", $className) : null;
		}
		if (!is_null($funcName2) || !is_null($className)) {
			$frame->addComment(sprintf("This frame is within %s%s%s", $funcName2, (!is_null($funcName2)) ? ' from ':'', $className), 'Explanation: ');
		}
		return $frame;
	});
});
// $whoops->register();
$GLOBALS['whoops'] = &$whoops;
