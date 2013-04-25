<?php
/**
 * Yab Framework
 *
 * @category   Yab_Db_Table
 * @package    Yab_Db_Table_Column
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework
 */

class Yab_Db_Table_Column {

	private $_table = null;
	
	private $_uniques = array();
	private $_indexes = array();

	private $_name = null;
	private $_primary = false;
	private $_sequence = false;
	private $_null = false;
	private $_unsigned = false;
	private $_default_value = null;
	private $_number = null;
	private $_quotable = true;
	private $_type = null;

	public function __construct($table, $name = null) {

		$this->_table = $table;

		$this->setName($name);

	}

	final public function setName($name) {

		$name = trim((string) $name);

		if(!$name)
			throw new Yab_Exception('column name can not be empty');

		$this->_name = $name;

		return $this;

	}

	final public function setPrimary($primary) {

		$this->_primary = (bool) $primary;

		return $this;

	}

	final public function addUnique($unique) {

		$unique = (string) $unique;
		
		if(in_array($unique, $this->_uniques))
			throw new Yab_Exception('column "'.$this->_name.'" has already an unique constraint named "'.$unique.'"');
	
		array_push($this->_uniques, (string) $unique);

		return $this;

	}

	final public function setSequence($sequence) {

		$this->_sequence = (bool) $sequence;

		return $this;

	}

	final public function setNull($null) {

		$this->_null = (bool) $null;

		return $this;

	}

	final public function setUnsigned($unsigned) {

		$this->_unsigned = (bool) $unsigned;

		return $this;

	}

	final public function setDefaultValue($default_value) {

		$this->_default_value = (string) $default_value;

		return $this;

	}

	final public function addIndex($index) {

		$index = (string) $index;
		
		if(in_array($index, $this->_indexes))
			throw new Yab_Exception('column "'.$this->_name.'" has already an index named "'.$index.'"');
	
		array_push($this->_indexes, (string) $index);

		return $this;

	}

	final public function setNumber($number) {

		$this->_number = intval($number);

		return $this;

	}

	final public function setQuotable($quotable) {

		$this->_quotable = (bool) $quotable;

		return $this;

	}

	final public function setType($type) {

		$this->_type = (string) $type;

		return $this;

	}

	final public function getTable() {

		return $this->_table;

	}

	final public function getName() {

		return (string) $this->_name;

	}

	final public function getPrimary() {

		return (bool) $this->_primary;

	}

	final public function getSequence() {

		return (bool) $this->_sequence;

	}

	final public function getUniques() {

		return $this->_uniques;

	}

	final public function getNull() {

		return (bool) $this->_null;

	}

	final public function getUnsigned() {

		return (bool) $this->_unsigned;

	}

	final public function getDefaultValue() {

		return (string) $this->_default_value;

	}

	final public function getIndexes() {

		return $this->_indexes;

	}

	final public function getNumber() {

		return $this->_number;

	}

	final public function getQuotable() {

		return $this->_quotable;

	}

	final public function getType() {

		return $this->_type;

	}

	final public function __toString() {

		return $this->getName();

	}

}

// Do not clause PHP tags unless it is really necessary