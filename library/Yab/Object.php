<?php
/**
* Yab Framework
*
* @category   Yab
* @package    Yab_Object
* @author     Yann BELLUZZI
* @copyright  (c) 2010 YBellu
* @license    http://www.ybellu.com/yab-framework/license.html
* @link       http://www.ybellu.com/yab-framework 
*/

class Yab_Object implements ArrayAccess, Iterator, Countable {

	private $_offset = -1;

	protected $_attributes = array();

	public function __construct($mixed = null) {

		if($mixed === null)
			return $this;

		return $this->populate(is_array($mixed) ? $mixed : array($mixed));

	}

	public function __set($key, $value) {

		return $this->set($key, $value);

	}

	public function __isset($key) {

		return $this->has($key);

	}

	public function __unset($key) {

		return $this->rem($key);

	}

	public function __get($key) {

		return $this->get($key);

	}

	public function shift() {

		return array_shift($this->_attributes);

	}

	public function pop() {

		return array_pop($this->_attributes);

	}

	public function map($callback) {

		$this->_attributes = array_map($callback, $this->_attributes);
	
		return $this;

	}

	public function clear() {

		$this->_attributes = array();

		return $this;

	}

	public function feed(array $attributes, $prefix = '') {

		return $this->clear()->populate($attributes, $prefix);

	}
	
	public function bind(array &$attributes) {

		$this->_attributes = &$attributes;
	
		return $this;

	}

	public function populate(array $attributes, $prefix = '') {

		foreach($attributes as $key => $value)
			$this->set($prefix.$key, $value);

		return $this;

	}

	public function set($key, $value) {

		$key = (string) $key;

		$this->_attributes[$key] = $value;

		return $this;

	}

	public function get($key, $filters = null) {

		$key = (string) $key;

		if(!$this->has($key))
			throw new Yab_Exception($key.' attribute has not been set');

		if($filters === null)
			return $this->_attributes[$key];

		if($filters instanceof Yab_Filter_Abstract)
			return $filters->filter($this->_attributes[$key]);
			
		return Yab_Loader::getInstance('Yab_Filter_Factory')->feed($this->_attributes)->set('filters', $filters)->filter($this->_attributes[$key]);

	}

	public function expression($expression, array $additionnal_attributes = array()) {

		if(array_key_exists($expression, $this->_attributes))
			return $this->_attributes[$expression];
	
		preg_match_all('#:([a-zA-Z0-9_]+)([^a-zA-Z0-9_]|$)#i', $expression, $matches1);

		foreach($matches1[1] as $match1) {
			
			$filters = array();

			if(preg_match('#'.preg_quote(':'.$match1, '#').'\[([^\]]+)\]#i', $expression, $matches2)) {			
				
				$filters = preg_split('#[^a-zA-Z0-9_\-]+#', $matches2[1]);
				
				$expression = preg_replace('#'.preg_quote($matches2[0], '#').'#', ':'.$match1, $expression, 1);
				
			}

			if(array_key_exists($match1, $this->_attributes)) {
				
				if(count($filters)) 
					$this->_attributes[$match1] = Yab_Loader::getInstance('Yab_Filter_Factory')->feed($this->_attributes + $additionnal_attributes)->set('filters', $filters)->filter($this->_attributes[$match1]);

				$expression = str_replace(':'.$match1, $this->_attributes[$match1], $expression);
				
			}

			if(array_key_exists($match1, $additionnal_attributes)) {
				
				if(count($filters)) 
					$additionnal_attributes[$match1] = Yab_Loader::getInstance('Yab_Filter_Factory')->feed($this->_attributes + $additionnal_attributes)->set('filters', $filters)->filter($additionnal_attributes[$match1]);

				$expression = str_replace(':'.$match1, $additionnal_attributes[$match1], $expression);
				
			}

		}
		
		return $expression;
		
	}
	
	public function flash($key, $filters = null) {

		$value = $this->get($key, $filters);

		$this->rem($key);

		return $value;

	}

	public function inc($key, $step = 1, $max = null) {

		$step = (int) $step;

		$value = $this->has($key) ? $this->get($key, 'Int') : 0;

		return $this->set($key, is_numeric($max) ? min($max, $value + $step) : $value + $step);

	}

	public function dec($key, $step = 1, $min = null) {

		$step = (int) $step;

		$value = $this->has($key) ? $this->get($key, 'Int') : 0;

		return $this->set($key, is_numeric($min) ? max($min, $value - $step) : $value - $step);

	}

	public function prepend($key, $value) {

		$old_value = $this->get($key);

		if(!is_string($old_value) || !is_string($value))
			throw new Yab_Exception('can not prepend the value to "'.$key.'" strings are needed');

		return $this->set($key, $value.$old_value);

	}

	public function append($key, $value) {

		$old_value = $this->get($key);

		if(!is_string($old_value) || !is_string($value))
			throw new Yab_Exception('can not append the value to "'.$key.'" strings are needed');

		return $this->set($key, $old_value.$value);

	}

	public function has($key) {

		return array_key_exists($key, $this->_attributes);

	}

	public function rem($key) {

		if(!$this->has($key))
			throw new Yab_Exception($key.' attribute has not been set');

		unset($this->_attributes[$key]);

		return $this;

	}

	public function getAttributes(array $name_attributes = array()) {

		if(!count($name_attributes))
			return $this->_attributes;

		$attributes = array();

		foreach($name_attributes as $name_attribute) {

			if(!$this->has($name_attribute))
				continue;

			$attributes[$name_attribute] = $this->get($name_attribute);

		}

		return $attributes;

	}

	public function count() {

		return count($this->_attributes);

	}

	public function offsetGet($offset) {

		return $this->get($offset);

	}

	public function offsetSet($offset, $value) {

		return $this->set($offset, $value);

	}

	public function offsetExists($offset) {

		return  $this->has($offset);

	}

	public function offsetUnset($offset) {

		return $this->rem($offset);

	}

	public function valid() {

		return array_key_exists(key($this->_attributes), $this->_attributes);

	}

	public function next() {

		$this->_offset++;

		next($this->_attributes);

		return $this->current();

	}

	public function rewind() {

		$this->_offset = 0;

		reset($this->_attributes);

		return $this->current();

	}

	public function key() {

		return key($this->_attributes);

	}

	public function current() {

		return current($this->_attributes);

	}

	public function offset() {

		return $this->_offset;

	}

	public function isFirst() {

		return ((bool) ($this->_offset < 0));

	}

	public function isLast() {

		return ((bool) ($this->_offset === $this->count() - 2));

	}

	public function join($pattern = '') {

		return new self(implode($pattern, $this->_attributes));

	}

	public function split($pattern = '') {

		if(!$pattern)
			return new self(str_split($this->toString()));

		if(strlen($pattern) < 3 || substr($pattern, 0, 1) !== substr($pattern, -1, 1))
			$pattern = '#'.preg_quote($pattern, '#').'#';

		return new self(preg_split($pattern, $this->toString()));

	}

	public function slice($start, $offset) {

		return new self(array_slice($this->toArray(), $start, $offset));

	}

	public function cast($key, $bind = false) {

		$value = $this->get($key);

		if(is_object($value))
			return $value;
			
		if(is_array($value)) {
		
			$cast = new self();
			
			return $cast->bind($this->_attributes[$key]);
		
		}
		
		return new self($value);

	}

	public function toString() {

		return implode('', $this->_attributes);

	}

	public function toArray() {

		return $this->_attributes;

	}

	public function toInt() {

		return intval($this->toString());

	}

	public function toFloat() {

		return floatval($this->toString());

	}

	public function __toString() {

		return $this->toString();

	}

}

// Do not clause PHP tags unless it is really necessary
