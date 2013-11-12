<?php
/**
* Yab Framework
*
* @category   Yab_Controller
* @package    Yab_Controller_Request
* @author     Yann BELLUZZI
* @copyright  (c) 2010 YBellu
* @license    http://www.ybellu.com/yab-framework/license.html
* @link       http://www.ybellu.com/yab-framework 
*/

class Yab_Controller_Request {

	const CONTROLLER_PREFIX = 'Controller';
	const ACTION_PREFIX = 'action';

	private $_base_url = '';

	private $_uri = null;

	private $_controller = null;
	private $_action = null;
	private $_params = array();
	
	final public function __construct($auto_detect_base_url = false) {
	
		if($auto_detect_base_url)
			$this->autoDetectBaseUrl();
	
	}
	
	final public function autoDetectBaseUrl() {
	
		if($this->iscli())
			return $this;
	
		$server = $this->getServer();
	
		if(!$server->has('SCRIPT_FILENAME'))
			return $this;
	
		$script_dirname = rtrim(dirname($server->get('SCRIPT_FILENAME')), '\\/');

		if(!$server->has('DOCUMENT_ROOT'))
			return $this;
			
		$document_root = rtrim($server->get('DOCUMENT_ROOT'), '\\/');

		$base_url = str_replace($document_root, '', $script_dirname);

		return $this->setBaseUrl($base_url);

	}

	final private function _cleanGPC(array $datas) {

		if(get_magic_quotes_gpc() == 1) 
			foreach($datas as $key => $value) 
				$datas[$key] = is_array($value) ? $this->_cleanGPC($value) : stripslashes($value);

		return $datas;

	}

	final public function isRouted() {

		return $this->_uri && $this->_controller && $this->_action;

	}

	final public function isCli() {

		return  ((bool) (PHP_SAPI == 'cli'));

	}

	final public function isHttp() {

		return !$this->isCli();

	}

	final public function isAjax() {
	
		$server = $this->getServer();
	
		return $server->has('HTTP_X_REQUESTED_WITH') && $server->get('HTTP_X_REQUESTED_WITH', 'LowerCase') == 'xmlhttprequest';

	}
	
	final public function isMobile() {

		if(!$server->has('HTTP_USER_AGENT'))
			return false;

		return preg_match('#(iphone|ipod|android|blackberry|smartphone|wap|xoom|symbian|mobile)#i', $server->get('HTTP_USER_AGENT'));

	}

	final public function isGet() {

		return $this->isHttp() && !$this->isPost();

	}

	final public function isPost() {

		return $this->isHttp() && ((bool) count($_POST));

	}
	
	final public function getStdin() {
	
		$stdin = '';
		
		$fh = fopen("php://stdin", "r");

		if($fh) {

			while(!feof($fh)) 
				$stdin .= fgets($fh);

			fclose($fh);

		}
		
		return $stdin;
	
	}
	
	final public function getGet() {
	
		return new Yab_Object($this->_cleanGPC($_GET));
	
	}
	
	final public function getPost() {
	
		return new Yab_Object($this->_cleanGPC($_POST));
	
	}
	
	final public function getCookie() {
	
		return new Yab_Object($this->_cleanGPC($_COOKIE));
	
	}
	
	final public function getRequest() {
	
		return new Yab_Object($this->_cleanGPC($_REQUEST));
	
	}
	
	final public function getFile() {
	
		return new Yab_Object($_FILES);
	
	}
	
	final public function getServer() {
	
		return new Yab_Object($_SERVER);
	
	}

	final public function setUri($uri) {

		$this->_uri = preg_replace('#^'.preg_quote($this->_base_url, '#').'#i', '', trim((string) $uri));

		return $this;

	}

	final public function setBaseUrl($base_url) {

		$this->_base_url = (string) $base_url;

		return $this->setUri($this->_uri);

	}

	final public function setController($controller) {

		$controller = (string) $controller;
	
		$controller = trim($controller);
		$controller = trim($controller, '_');
		$controller = explode('_', $controller);
		$controller = array_map('ucfirst', $controller);
		$controller = implode('_', $controller);
		
		$this->_controller = $controller;

		return $this;

	}

	final public function setAction($action) {

		$action = (string) $action;
	
		$action = trim($action);
		$action = strtolower(substr($action, 0, 1)).substr($action, 1);
		
		$this->_action = $action;

		return $this;

	}

	final public function setParams(array $params) {

		$this->_params = $params;

		return $this;

	}

	final public function getBaseUrl() {

		return $this->_base_url;

	}

	final public function getUri($query_string = true) {

		$uri = $this->_uri;

		if($query_string !== true) {

			$query_string_pos = strpos($uri, '?');

			if(is_numeric($query_string_pos))
				$uri = substr($uri, 0, $query_string_pos);
			
			if(is_array($query_string)) {
			
				$filter_query_string = new Yab_Filter_QueryString();
			
				$query_string = $filter_query_string->filter($query_string);
			
			}
			
			if(is_string($query_string) && 0 < strlen($query_string))
				$uri .= '?'.$query_string;

		}

		return $uri;

	}

	final public function getControllerClass() {

		return self::CONTROLLER_PREFIX.'_'.$this->_controller;

	}

	final public function getActionMethod() {

		return self::ACTION_PREFIX.ucfirst($this->_action);

	}

	final public function getController() {

		return $this->_controller;

	}

	final public function getAction() {

		return $this->_action;

	}

	final public function getParams() {

		return $this->_params;

	}

	final public function getParam($key, $default = null) {

		return array_key_exists($key, $this->_params) ? $this->_params[$key] : $default;

	}

	final public function __toString() {

		return $this->getBaseUrl().$this->getUri();

	}

	final public function toHttp() {

		$http = ($this->isPost() ? 'POST' : 'GET').' '.$this->getBaseUrl().$this->getUri().' HTTP/1.0'.PHP_EOL;
		$http .= 'User-Agent: PHP - Yab_Request'.PHP_EOL;
		$http .= PHP_EOL;

		$query_string = new Yab_Filter_QueryString();

		$http .= $query_string->filter($this->getPost()->toArray());

		return $http;

	}

}

// Do not clause PHP tags unless it is really necessary
