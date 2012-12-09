<?php
/**
 * Yab Framework
 *
 * @category   Yab
 * @package    Yab_Loader
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

require_once 'Exception.php';

class Yab_Loader {
	
	private $_start = 0;

	static private $_instances = array();

	final private function __construct() {

		$this->_start = $this->getTime();

		spl_autoload_register(array($this, 'loadClass'));
		
		$this->addPath($this->getPath());

	}

	final public function configure($file = null, $environment = null) {

		$config = $this->getConfig();
	
		if($environment !== null)
			$config->setEnvironment($environment);

		if($file !== null)
			$config->setFile($file);

		$config->apply();
			
		return $this;

	}

	final static public function getInstance($class = __CLASS__, array $args = array(), $parent_class = null) {

		if(!array_key_exists($class, self::$_instances)) 
			self::$_instances[$class] = $class == __CLASS__ ? new self() : self::getInstance()->invoke($class, $args, $parent_class);

		return self::$_instances[$class];

	}

	final public function getPath() {

		return dirname(dirname(__FILE__));

	}

	final public function addPath($path) {

		if(!is_dir($path) || !is_readable($path))
			throw new Yab_Exception($path.' is not a valid readable directory path');

		set_include_path($path.PATH_SEPARATOR.get_include_path());

		return $this;

	}

	final public function isFile($filepath, $readable = true, $writable = false) {

		$realpath = null;

		if(is_file($filepath))
			$realpath = $filepath;

		if(!$realpath) {

			$pathes = explode(PATH_SEPARATOR, get_include_path());

			foreach($pathes as $path) {

				if(!is_file($path.DIRECTORY_SEPARATOR.$filepath))
					continue;

				$realpath = $path.DIRECTORY_SEPARATOR.$filepath;

				break;

			}

		}

		if(!$realpath)
			return false;

		if($readable && !is_readable($realpath))
			return false;

		if($writable && !is_writable($realpath))
			return false;

		return true;

	}

	final public function isDir($dirpath, $readable = true, $writable = false) {

		$realpath = null;

		if(is_dir($dirpath))
			$realpath = $dirpath;

		if(!$realpath) {

			$pathes = explode(PATH_SEPARATOR, get_include_path());

			foreach($pathes as $path) {

				if(!is_file($path.DIRECTORY_SEPARATOR.$dirpath))
					continue;

				$realpath = $path.DIRECTORY_SEPARATOR.$dirpath;

				break;

			}

		}

		if(!$realpath)
			return false;

		if($readable && !is_readable($realpath))
			return false;

		if($writable && !is_writable($realpath))
			return false;

		return true;

	}

	final public function loadClass($class_name) {

		if(class_exists($class_name, false))
			return $this;

		$class_path = $this->classToPath($class_name);

		if(!$this->isFile($class_path))
			throw new Yab_Exception($class_path.' was not found');

		require_once $class_path;

		if(!class_exists($class_name, false))
			throw new Yab_Exception($class_path.' does not define the class '.$class_name);

		return $this;

	}

	final public function classToPath($class_name) {

		return str_replace('_', DIRECTORY_SEPARATOR, $class_name).'.php';

	}

	final public function invoke($instance_or_class, $method_or_args, $args_or_parent_class = null) {

		$array_filter = new Yab_Filter_Array();
	
		# Invoke an object method
		if(is_object($instance_or_class)) {
		
			$instance = $instance_or_class;
			$method = (string) $method_or_args;
			$class = get_class($instance_or_class);
			$args = $array_filter->filter($args_or_parent_class);

			if(!method_exists($class, $method))
				throw new Yab_Exception('"'.$method.'" does not exists on '.$class);

			$reflection_method = new ReflectionMethod($class, $method);

			if(!$reflection_method->isPublic())
				throw new Yab_Exception('"'.$class.'->'.$method.'()" is not public and can not be called'); 

			if(count($args) < $reflection_method->getNumberOfRequiredParameters())
				throw new Yab_Exception($class.'->'.$method.'() needs '.$reflection_method->getNumberOfRequiredParameters().' parameters, '.count($args).' given');

			switch(count($args)) {

				case 0 : return $instance->$method();
				case 1 : return $instance->$method(array_shift($args));
				case 2 : return $instance->$method(array_shift($args), array_shift($args));
				case 3 : return $instance->$method(array_shift($args), array_shift($args), array_shift($args));
				case 4 : return $instance->$method(array_shift($args), array_shift($args), array_shift($args), array_shift($args));
				case 5 : return $instance->$method(array_shift($args), array_shift($args), array_shift($args), array_shift($args), array_shift($args));
				case 6 : return $instance->$method(array_shift($args), array_shift($args), array_shift($args), array_shift($args), array_shift($args), array_shift($args));

			}
		
			return call_user_func_array(array($instance, $method), $args);
		
		}
		
		# Invoke an object
		$class = (string) $instance_or_class;
		$args = $array_filter->filter($method_or_args);
		$parent_class = (string) $args_or_parent_class;

		$this->loadClass($class);

		if($parent_class)
			$this->loadClass($parent_class);

		$instance = null;

		switch(count($args)) {

			case 0 : $instance = new $class(); break;
			case 1 : $instance = new $class(array_shift($args)); break;
			case 2 : $instance = new $class(array_shift($args), array_shift($args)); break;
			case 3 : $instance = new $class(array_shift($args), array_shift($args), array_shift($args)); break;
			case 4 : $instance = new $class(array_shift($args), array_shift($args), array_shift($args), array_shift($args)); break;
			case 5 : $instance = new $class(array_shift($args), array_shift($args), array_shift($args), array_shift($args), array_shift($args)); break;
			case 6 : $instance = new $class(array_shift($args), array_shift($args), array_shift($args), array_shift($args), array_shift($args), array_shift($args)); break;

		}

		if(!$instance)
			throw new Yab_Exception('"invoke" with "'.count($args).'" args is not implemented');

		if($parent_class && !($instance instanceof $parent_class))
			throw new Yab_Exception('"'.$class.'" does not extends "'.$parent_class.'"');

		return $instance;

	}

	final public function getTime() {

		list($tps_usec, $tps_sec) = explode(" ", microtime());

		return  ((float) $tps_usec + (float) $tps_sec);

	}

	final public function getMemoryUsage() {

		return function_exists('memory_get_usage') ? memory_get_usage(true) : 0;

	}

	final public function getMemoryPeakUsage() {

		return function_exists('memory_get_peak_usage') ? memory_get_peak_usage(true) : 0;

	}

	final public function getScriptLength($decimals = 6) {

		return number_format($this->getTime() - $this->_start, $decimals);

	}

	final public function bench($label = null) {

		$is_cli = $this->getRequest()->isCli();
		
		$space = $is_cli ? ' ' : '&nbsp;';
		$crlf = $is_cli ? PHP_EOL : '<br />';
		$label = $label ? '['.$label.']'.$space : '';

		$benchmark = $label.'Length:'.$space.$this->getScriptLength(3).$space.'sec,'.$space.'Memory:'.$space.number_format($this->getMemoryUsage() / 1024 / 1024, 2).$space.'Mo,'.$space.'Peak:'.$space.number_format($this->getMemoryPeakUsage() / 1024 / 1024, 2).$space.'Mo'.$crlf;

		return $benchmark;

	}

	final public function dump($var, $depth = 0, SplObjectStorage $recursion = null) {

		if($this->getRequest()->isCli() || PHP_VERSION < '5.3')
			return print_r($var, true);

		$dump_type = 'color: #007700';
		$dump_value_attribute = 'color: #0000dd';
		$dump_value_string = 'color: #0000dd';
		$dump_value_numeric = 'color: #dd0000';
		$dump_value_boolean = 'color: #000000';
		$dump_operator = 'color: #007700';
		$dump_accessibility = 'color: #666666';

		$dump = '';

		$type = strtolower(gettype($var));

		switch($type) {

			case 'null' :
				$dump .= '<span style="'.$dump_value_boolean.'">NULL</span>';
			break;

			case 'resource' :
				$dump .= '<span style="'.$dump_type.'">resource</span> <span style="'.$dump_value_string.'">'.$var.'</span>';
			break;

			case 'string' :
				$dump .= '<span style="'.$dump_type.'">string(</span><span style="'.$dump_value_numeric.'">'.strlen($var).'</span><span style="'.$dump_type.'">, &quot;</span><span style="'.$dump_value_string.'">'.$var.'</span><span style="'.$dump_type.'">&quot;)</span>';
			break;

			case 'int' :
			case 'integer' :
				$dump .= '<span style="'.$dump_type.'">int(</span><span style="'.$dump_value_numeric.'">'.$var.'</span><span style="'.$dump_type.'">)</span>';
			break;

			case 'float' :
			case 'double' :
				$dump .= '<span style="'.$dump_type.'">float(</span><span style="'.$dump_value_numeric.'">'.$var.'</span><span style="'.$dump_type.'">)</span>';
			break;

			case 'bool' :
			case 'boolean' :
				$dump .= '<span style="'.$dump_type.'">bool(</span><span style="'.$dump_value_boolean.'">'.($var ? 'TRUE' : 'FALSE').'</span><span style="'.$dump_type.'">)</span>';
			break;

			case 'array' :

				$dump .= '<span style="'.$dump_type.'">array(</span><span style="'.$dump_value_numeric.'">'.count($var).'</span><span style="'.$dump_type.'">)</span>';

				foreach($var as $key => $value)
					$dump .= PHP_EOL.str_repeat("\t", $depth + 1).$this->dump($key, $depth + 1).' <span style="'.$dump_operator.'">=&gt;</span> '.$this->dump($value, $depth + 1, $recursion);

			break;

			default :

				if($recursion === null)
					$recursion = new SplObjectStorage();
		
				$dump .= '<span style="'.$dump_type.'">'.get_class($var).'</span>';
					
				if($recursion->contains($var))
					return $dump.' <span style="'.$dump_type.'">*RECURSION*</span>';
					
				$recursion->attach($var);
					
				$reflect = new ReflectionClass($var);
				
				$properties = $reflect->getProperties();

				foreach($properties as $property) {
						
					$access = 'public';
					
					if($property->isPrivate()) {
					
						$property->setAccessible(true);
						
						$access = 'private';
					
					}
					
					if($property->isProtected()) {
					
						$property->setAccessible(true);
						
						$access = 'protected';
					
					}

					$dump .= PHP_EOL.str_repeat("\t", $depth + 1).'<span style="'.$dump_accessibility.'">'.$access.($property->isStatic() ? ' static' : '').'</span> <span style="'.$dump_value_attribute.'">'.$property->getName().'</span> <span style="'.$dump_operator.'">=&gt;</span> '.$this->dump($property->getValue($var), $depth + 1, $recursion);
					
				}
	
			break;

		}

		return $dump;

	}
	
	###########################################################################
	###########################################################################
	# FUTUR trait Yab_Mvc php 5.4
	###########################################################################
	###########################################################################
	
	# use Yab_Mvc;

	final public function startMvc($uri = null) {

		if($uri !== null)
			$this->getRequest()->setUri($uri);
			
		$this->getRouter()->route($this->getRequest());

		$this->getDispatcher()->dispatch($this->getRequest(), $this->getResponse());

		$this->getResponse()->send();
    
	}

	final public function getRequest($controller_or_uri = null, $action = null, array $params = array()) {
	
		if(!$controller_or_uri) {

			$request = self::getInstance('Yab_Controller_Request', array(true));

			if($request->getUri())
				return $request;

			if($request->isCli() && $request->getServer()->has('argv'))
				return $request->setUri('/'.$request->getServer()->cast('argv')->rem(0)->map('urlencode')->join('/'));

			if($request->isHttp() && $request->getServer()->has('REQUEST_URI')) 
				return $request->setUri($request->getServer()->get('REQUEST_URI'));

			return $request;

		}

		$request = new Yab_Controller_Request();

		if(preg_match('#/\-\.#', $controller_or_uri)) {
			
			$request->setBaseUrl($this->getRequest()->getBaseUrl())
					->setUri($controller_or_uri);
			
		} else {

			if(!$action)
				$action = $this->getRouter()->getDefaultAction();
			
			$request->setBaseUrl($this->getRequest()->getBaseUrl())
					->setController($controller_or_uri)
					->setAction($action)
					->setParams($params);
					
		}

		$this->getRouter()->route($request);

		return $request;

	}

	final public function getResponse($controller = null, $action = null, array $params = array()) {

		if(!$controller || !$action) 
			return self::getInstance('Yab_Controller_Response');

		$request = new Yab_Controller_Request();

		$request->setBaseUrl($this->getRequest()->getBaseUrl())
				->setController($controller)
				->setAction($action)
				->setParams($params);

		$response = new Yab_Controller_Response();

		$this->getDispatcher()->dispatch($request, $response);

		return $response;

	}

	final public function getRouter() {

		return Yab_Loader::getInstance('Yab_Controller_Router');

	}

	final public function getDispatcher() {

		return Yab_Loader::getInstance('Yab_Controller_Dispatcher');

	}

	final public function getSession() {

		return Yab_Loader::getInstance('Yab_Session');

	}

	final public function getRegistry() {

		return Yab_Loader::getInstance('Yab_Object');

	}

	final public function getConfig() {

		return Yab_Loader::getInstance('Yab_Config');

	}

	final public function getLayout() {

		return Yab_Loader::getInstance('Yab_View');

	}

	final public function forward($controller, $action = null, array $params = array(), $code = null) {

		return $this->getResponse()->redirect($this->getRequest($controller, $action, $params), $code);

	}

	final public function redirect($uri, $code = null) {

		return $this->getResponse()->redirect($uri, $code);

	}

	###########################################################################
	###########################################################################
	###########################################################################
	###########################################################################
	###########################################################################

}

// Do not clause PHP tags unless it is really necessary