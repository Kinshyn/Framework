<?php
/**
 * Yab Framework
 *
 * @category   Yab
 * @package    Yab_Session
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */
 
class Yab_Session extends Yab_Object {

	const SESSION_YAB_KEY = 'yab';

	public function __construct() {

		try {
		
			if(!isset($_SESSION))
				session_start();

		} catch(Yab_Exception $e) {
		
			$_SESSION = array();
		
		}
		
		$_SESSION[self::SESSION_YAB_KEY] = 1;

		$this->bind($_SESSION);		

	}

}

// Do not clause PHP tags unless it is really necessary