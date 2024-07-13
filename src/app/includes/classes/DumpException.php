<?php namespace APP\includes\classes;
// Required every files to load
$thisPath = (defined('DIR_ROOT')) ? DIR_CONFIG : dirname(__FILE__, 4) . '/configs';
require_once($thisPath . '/variables.php');
require_once(DIR_CONFIG . '/db-handles.php');
require_once(DIR_CONFIG . '/db-queries.php');
require_once(DIR_CONFIG . '/functions.php');
require_once(DIR_VENDOR . '/autoload.php');

// Memuat class lain
use \Exception as Exception;
use \ErrorException as ErrorException;

class DumpException extends ErrorException {
	protected $_data;
	protected $_note;

	public function __construct(string $title='', string $message='', int $code=0 , int $severity=E_USER_NOTICE, string $filename=null, int $line=null, Exception $previous=NULL, $data=NULL) {
		$this->_data = $data;
		$this->_note = $message;
		parent::__construct($title, $code, $severity, $filename, $line, $previous);
	}
	public function getData() {
		return $this->_data;
	}
	public function getDevNote() {
		return $this->_note;
	}
}
