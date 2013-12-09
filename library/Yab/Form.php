<?php
/**
 * Yab Framework
 *
 * @category   Yab
 * @package    Yab_Form
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Form extends Yab_Object {
	
	private $_csrf = null;

	private $_elements = array();

	final public function getElements() {

		return $this->_elements;

	}

	final public function hasElement($name) {

		return array_key_exists($name, $this->_elements);

	}

	final public function remElement($name) {

		if(!$this->hasElement($name))
			throw new Yab_Exception('"'.$name.'" is not defined is this form');

		unset($this->_elements[$name]);

		return $this;

	}

	final public function setElement($name, array $attributes = array()) {

		$element = new Yab_Form_Element($this, array_merge($attributes, array('name' => $name)));

		if($this->hasElement($name))
			throw new Yab_Exception('"'.$name.'" has already been defined is this form');

		$this->_elements[$name] = $element;

		return $this;

	}

	final public function getElement($name) {

		if(!$this->hasElement($name))
			throw new Yab_Exception('"'.$name.'" is not defined is this form');

		return $this->_elements[$name];

	}

	final public function getErrors($filters = null) {

		$errors = array();

		if($this->_csrf && !$this->_csrf->isSubmitted()) 
			return $errors;
					
		if($this->_csrf && !$this->_csrf->isValid()) 
			return $errors;

		if($this->isSubmitted()) {
		
			foreach($this->_elements as $element) {
				
				foreach($element->getErrors($filters) as $error_code => $message) {
				
					if(!array_key_exists($element->get('name'), $errors)) 
						$errors[$element->get('name')] = array();
						
					$errors[$element->get('name')][$error_code] = $message;
					
				}
			
			}
			
		}

		return $errors;

	}

	final public function csrf($token = null) {
	
		if($token === null)
			$token = get_class($this);
			
		$token = strtolower($token);
	
		$request = Yab_Loader::getInstance()->getRequest();	
		$session = Yab_Loader::getInstance()->getSession();	
		
		$csrf_name = 'yab_'.$token.'_csrf_token';
		$csrf_value = md5($csrf_name.$request->getServer()->get('REMOTE_ADDR').$request->getServer()->get('HTTP_USER_AGENT'));

		if(!$session->has($csrf_name))
			$session->set($csrf_name, $csrf_value);

		$this->_csrf = new Yab_Form_Element($this, array(
			'name' => $csrf_name,
			'type' => 'hidden',
			'id' => $csrf_name,
			'value' => $csrf_value,
			'validators' => array(
				'Equal' => array(
					'to' => $session->get($csrf_name)
				),
			),
		));
			
		return $this;
		
	}

	final public function isSubmitted() {
		
		if($this->_csrf && !$this->_csrf->isSubmitted()) 
			return false;

		foreach($this->_elements as $element) { 

			if($element->has('disabled') && strtolower($element->get('disabled')) == 'disabled')
				continue;

			if(!$element->isSubmitted()) 
				return false;

		}

		return true;

	}

	final public function isValid() {
		
		if($this->_csrf && !$this->_csrf->isValid()) 
			return false;

		foreach($this->_elements as $element) {

			if(!$element->isValid()) 
				return false;

		}
		
		return true;

	}

	final public function getValues($filters = true) {

		$values = array();

		foreach($this->_elements as $element) 
			$values[$element->get('name')] = $element->getValue($filters);

		return $values;

	}

	final public function getHeadHtml() {

		$html = '<form';

		foreach($this->getAttributes() as $key => $value)
			$html .= ' '.strtolower($key).'="'.$this->get($key, 'Html').'"';

		return $html.'>';

	}

	final public function getTailHtml() {

		$html = '</form>';
		
		if($this->_csrf) 
			$html = $this->_csrf->getHtml().$html;

		return $html;
	}

}

// Do not clause PHP tags unless it is really necessary
