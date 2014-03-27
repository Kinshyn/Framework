<?php
/**
 * Yab Framework
 *
 * @category   Yab_Controller_Plugin
 * @package    Yab_Controller_Plugin_Abstract
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework
 */

abstract class Yab_Controller_Plugin_Abstract {

	abstract public function preDispatch(Yab_Controller_Request $request, Yab_Controller_Response $response);
	abstract public function preRender(Yab_Controller_Request $request, Yab_Controller_Response $response);
	abstract public function postRender(Yab_Controller_Request $request, Yab_Controller_Response $response);
	abstract public function postDispatch(Yab_Controller_Request $request, Yab_Controller_Response $response);
	
	# Proxy to loader
	final public function __call($method, array $args) {
	
		$loader = Yab_Loader::getInstance();

		return $loader->invoke($loader, $method, $args);

	}

}

// Do not clause PHP tags unless it is really necessary
