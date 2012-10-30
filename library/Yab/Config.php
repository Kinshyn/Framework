<?php
/**
 * Yab Framework
 *
 * @category   Yab
 * @package    Yab_Config
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework
 */

class Yab_Config extends Yab_Object {

	private $_file = null;
	private $_environment = null;
	
	public function __construct() {
	
		$loader = Yab_Loader::getInstance();
		
		$server = $loader->getRequest()->getServer();
		
		if(!defined('YAB_PATH'))
			define('YAB_PATH', $loader->getPath()); 
	
		if(!defined('YAB_PUBLIC_PATH') && $server->has('DOCUMENT_ROOT'))
			define('YAB_PUBLIC_PATH', $server->get('DOCUMENT_ROOT')); 
	
		if(!defined('YAB_BOOTSTRAP_PATH') && $server->has('SCRIPT_FILENAME'))
			define('YAB_BOOTSTRAP_PATH', dirname($server->get('SCRIPT_FILENAME'))); 
	
	}
 
	final public function setEnvironment($environment) {

		$this->_environment = trim((string) $environment);

		return $this;
	
	}
 
	final public function setFile($file) {

		$file = trim((string) $file);
		
		if(!Yab_Loader::getInstance()->isFile($file))
			throw new Yab_Exception('"'.$file.'" is not a valid readable config file');
	
		$this->_file = $file;
	
		if($this->_environment !== null) {
	
			$attributes = parse_ini_file($this->_file, true);
			
			if(!array_key_exists($this->_environment, $attributes))
				throw new Yab_Exception($this->_environment.' does not exists as an environment in the config file : '.$this->_file);
		
			$this->populate($attributes[$this->_environment]);
		
		} else {
	
			$attributes = parse_ini_file($this->_file);

			$this->populate($attributes);
		
		}

		return $this;
	
	}
 
	final public function getEnvironment() {

		return $this->_environment;
	
	}
 
	final public function getFile() {

		return $this->_file;
	
	}

	final public function apply() {

		$loader = Yab_Loader::getInstance();
		$registry = $loader->getRegistry();
		$filter_ucc = new Yab_Filter_PascalCase();
		$filter_s = new Yab_Filter_Singularize();
		$filter_pc = new Yab_Filter_PascalCase(array('separator' => '_'));

		foreach($this as $key => $value) {
		
			if(preg_match('#^([a-zA-Z0-9]+)\.(yab_[a-zA-Z0-9_]+_adapter)$#i', $key, $match)) {
			
				$adapter_name = $match[1];
				$abstract_name = $filter_pc->filter($match[2]).'_Abstract';
				$class_name = $filter_pc->filter($match[2]).'_'.$filter_pc->filter($value);

				if($registry->has($adapter_name))
					continue;
					
				$adapter = $loader->invoke($class_name, array(), $abstract_name);	

				$registry->set($adapter_name, $adapter);

			} elseif(preg_match('#php\.([a-zA-Z0-9_\.]+)$#', $key, $match)) {
			
				ini_set($match[1], $value);

			} elseif(preg_match('#^([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)$#', $key, $match)) {

				try {
				
					$object = $loader->invoke($loader, 'get'.$filter_ucc->filter($match[1]));
				
				} catch(Yab_Exception $e) {
				
					$object = $registry->get($match[1]);
				
				}

				try {
					
					$loader->invoke($object, 'set'.$filter_ucc->filter($match[2]), array($value));
	
				} catch(Yab_Exception $e) {
				
					if(!is_array($value))
						throw $e;

					foreach($value as $k => $v) {
					
						try {
					
							$loader->invoke($object, 'add'.$filter_ucc->filter($filter_s->filter($match[2])), array($v));
	
						} catch(Yab_Exception $e) {
						
							$loader->invoke($object, 'add'.$filter_ucc->filter($filter_s->filter($match[2])), array($k, $v));
						
						}
						
					}
						
				}
	
			} elseif(preg_match('#^yab_[a-zA-Z0-9_]+$#i', $value, $match)) {

				try {
			
					$object = $loader->invoke($value, array());
			
					$registry->set($key, $object);

				} catch(Yab_Exception $e) {
				
					continue;
				
				}

			}

		}
			
		return $this;

	}

}

// Do not clause PHP tags unless it is really necessary