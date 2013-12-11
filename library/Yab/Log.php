<?php
/**
 * Yab Framework
 *
 * @category   Yab
 * @package    Yab_Log
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Log {

	private $_severities = array(
		0 => 'none',
		1 => 'debug', 
		2 => 'info', 
		3 => 'notice', 
		4 => 'warning', 
		5 => 'error', 
	);

	private $_default_severity = 2;
	private $_log_severity = 0;
	
	private $_file = null;
	private $_fhandler = null;

	final public function __destruct() {

		if($this->_fhandler) 
			$this->_fhandler = fclose($this->_fhandler);

	}
	
	final public function setDefaultSeverity($severity) {

		if(!in_array($severity, $this->_severities))
			throw new Yab_Exception($severity.' is not a valid severity');

		$this->_default_severity = array_search($severity, $this->_severities);

		return $this;

	}
	
	final public function setSeverity($severity) {

		if(!in_array($severity, $this->_severities))
			throw new Yab_Exception($severity.' is not a valid severity');

		$this->_log_severity = array_search($severity, $this->_severities);

		return $this;

	}

	final public function setFile($file) {

		$file = trim((string) $file);

		$directory = dirname($file);

		if(!Yab_Loader::getInstance()->isDir($directory, true, true))
			throw new Yab_Exception($this->_directory.' is not an existing directory');

		if(Yab_Loader::getInstance()->isFile($file, false, false) && !Yab_Loader::getInstance()->isFile($file, true, true))
			throw new Yab_Exception($this->_directory.' is not a valid readable/writable log file');

		$this->_file = $file;

		return $this;

	}

	final public function write($event, $severity = null) {

		if(null === $severity)
			$severity = $this->_severities[$this->_default_severity];

		if(!in_array($severity, $this->_severities))
			throw new Yab_Exception($severity.' is not a valid severity');

		$severity_level = array_search($severity, $this->_severities);

		if(null === $this->_file || $severity_level < $this->_log_severity)
			return $this;
			
		$traces = debug_backtrace();

		$lastTrace = array_shift($traces);

		$formatted_event = date('H:i:s').' '.$_SERVER['REMOTE_ADDR'].' '.getmypid().' '.strtoupper($severity).' "'.$event.'" in '.$lastTrace['file'].', line '.$lastTrace['line'].PHP_EOL;

		if(!$this->_fhandler) {
		
			$this->_fhandler = fopen($this->_file, 'a+', true);
			
			chmod($this->_file, 0777);
			
		}
		
		if(!$this->_fhandler) 
			throw new Yab_Exception($this->_file.' can not be opened');

		fwrite($this->_fhandler, $formatted_event);

		return $this;

	}

}

// Do not clause PHP tags unless it is really necessary
