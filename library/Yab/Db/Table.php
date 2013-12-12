<?php
/**
 * Yab Framework
 *  
 * @category   Yab_Db
 * @package    Yab_Db_Table
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Db_Table extends Yab_Object {

	private $_adapter = null;
	private $_safe_primary = null;

	protected $_schema = null;
	protected $_name = null;
	
	protected $_columns = array();

	private $_saving = 0;
	
	final public function __construct($adapter = null, $name = null, $schema = null) {

		# trying to give the adapter
		if(!($adapter instanceof Yab_Db_Adapter_Abstract)) {

			$schema = $name;
			$name = $adapter;
			$adapter = Yab_Db_Adapter_Abstract::getDefaultAdapter();
				
		}

		$this->_adapter = $adapter;

		# trying to give the schema name
		if($this->_schema === null || $schema !== null) {

			if(!$schema) {

				$parts = explode('.', $this->_name ? $this->_name : $this->_adapter->unQuoteIdentifier($name));

				if(1 < count($parts)) {

					$schema = array_shift($parts);
					$schema = implode('.', $parts);

				} else {

					$schema = $this->_adapter->getSelectedSchema();

				}

			}

			$schema = $this->_adapter->unQuoteIdentifier($schema);

			if(!$schema)
				throw new Yab_Exception($schema.' is not a valid schema name');

			$this->_schema = $schema;

		}
			
		# trying to directly find by primary_key
		if($this->_name !== null && $name !== null)
			$this->feed($this->find($name)->toArray());

		# trying to give the table name
		if($this->_name === null && $name !== null) 
			$this->_name = $this->_adapter->unQuoteIdentifier($name);

		$this->_init();

	}
	
	public function init() {
		
		$this->clear();
		
		foreach($this->getColumns() as $column) {
			
			if($column->getPrimary() && !$column->getDefaultValue())
				continue;
				
			parent::set($column->getName(), $column->getDefaultValue() ? $column->getDefaultValue() : ($column->getNull() ? null : $column->getDefaultValue()));
			
		}
		
		return $this;
	
	}
	
	protected function _init() {}
	
	final public function getAdapter() {

		return $this->_adapter;

	}

	final public function getSchema() {

		return $this->_schema;

	}

	final public function getName() {

		return $this->_name;

	}

	final public function getTable($table = null) {

		if($table === null)
			return $this;

		return $this->getAdapter()->getTable($table);

	}

	final public function find($attributes) {

		return $this->search($attributes)->toRow();

	}

	final public function search($attributes) {

		return $this->fetchAll()->where($this->getSqlCondition($attributes));

	}

	final public function fetchAll() {

		return $this->_adapter->prepare('
			SELECT '.implode(', ', array_map(array($this->_adapter, 'quoteIdentifier'), $this->getColumns())).' 
			FROM '.$this
		)->setValue(clone $this);

	}

	final public function getColumns() {
	
		return $this->_adapter->getColumns($this);

	}

	final public function getColumn($name) {

		foreach($this->getColumns() as $column)
			if($column->getName() == $name)
				return $column;

		throw new Yab_Exception($name.' is not a column of '.$this.' ('.implode(', ', $this->getColumns()).')');

	}

	final public function hasColumn($name) {
		
		foreach($this->getColumns() as $column) 
			if($column->getName() == $name) 
				return true;

		return false;

	}

	final protected function addColumn(Yab_Db_Table_Column $column, $override = false) {
	
		if(!$override && $this->hasColumn($column->getName()))
			throw new Yab_Exception('Table "'.get_class($this).'" has already a column named "'.$column->getName().'"');
	
		$this->_columns[$column->getName()] = $column;
		
		return $this;
	
	}

	final public function insert(array $values) {

		return $this->_adapter->insert($this, $values);

	}

	final public function update(array $values, $where) {

		return $this->_adapter->update($this, $values, $where);

	}

	final public function truncate() {

		return $this->_adapter->truncate($this);

	}

	final public function exists() {

		try {

			if(!$this->getSafePrimary())
				return false;

			$this->find($this->getSafePrimary());

			return true;

		} catch(Yab_Exception $e) {

			return false;

		}

	}

	final public function save($attributes = array()) {

		$this->_saving++;
	
		if(method_exists($this, 'preSave') && $this->_saving < 2)
			$this->preSave($attributes);

		if($this->exists()) {

			$this->_update($attributes);

		} else {

			$this->_insert($attributes);

		}
		
		if(method_exists($this, 'postSave') && $this->_saving < 2)
			$this->postSave($attributes);

		$this->_saving--;
			
		return $this;

	}

	final private function _insert($attributes = array()) {
		
		if($this->exists())
			throw new Yab_Exception('can not insert row, this row already exists');

		$this->insert($this->getAttributes($attributes));

		foreach($this->getColumns() as $column) 
			if($column->getSequence()) 
				$this->set($column->getName(), $this->_adapter->getLastInsertId($this));

		return $this;

	}

	final private function _update($attributes = array()) {

		if(!$this->exists())
			throw new Yab_Exception('can not update row, this row does not exists');

		$where = $this->getSqlCondition();

		$attributes = $this->getAttributes($attributes);

		foreach($this->getSafePrimary() as $key => $value)
			if($value == $this->get($key))
				unset($attributes[$key]);

		$this->update($attributes, $where);

		return $this;

	}

	final public function delete($where = null) {

		if($where !== null)
			return $this->_adapter->delete($this, $where);

		if(!$this->exists())
			throw new Yab_Exception('can not delete row, this row does not exists');

		$where = $this->getSqlCondition();

		if(method_exists($this, 'preDelete'))
			$this->preDelete();
		
		$this->delete($where);

		if(method_exists($this, 'postDelete'))
			$this->postDelete();

		return $this;

	}
	
	final public function getSqlCondition($values = array(), $comparator = '=', $combinator = 'AND') {
		
		$negative_comparators = array('!=', '<>');
	
		$where = array();
		
		if(!is_array($values))
			$values = array($values);

		$primary_columns = $this->getPrimaryColumns();
		
		foreach($values as $key => $value) {
		
			if(!is_numeric($key)) 
				continue;
			
			unset($values[$key]);
		
			$key = (string) array_shift($primary_columns);
				
			$values[$key] = $value;

		}
		
		if(!count($values))
			$values = $this->getSafePrimary();
		
		$comparator = trim((string) $comparator);
		$combinator = trim((string) $combinator);

		foreach($values as $key => $value) {
		
			$condition = $this->_adapter->quoteIdentifier($key);
			
			$column = $this->getColumn($key);
			
			if(is_array($value)) {
						
				if($column->getQuotable())
					$value = implode(', ', array_map(array($this->_adapter, 'quote'), $value));

				if(in_array($comparator, $negative_comparators)) {
				
					$condition .= ' NOT IN ('.$value.')';
					
				} else {
				
					$condition .= ' IN ('.$value.')';		
					
				}

			} else {
				
				$condition .= ' '.$comparator.' ';
				$condition .= $column->getQuotable() ? $this->_adapter->quote($value) : $value;
			
			}
			
			if(in_array($comparator, $negative_comparators) && $column->getNull())
				$condition .= ' OR '.$this->_adapter->quoteIdentifier($key).' IS NULL';
	
			array_push($where, $condition);
			
		}

		$where = implode(' '.$combinator.' ', $where);
		
		return $where;
	
	}

	final public function getSafePrimary() {

		return $this->_safe_primary;

	}

	final public function getPrimary() {

		$primary = array();

		foreach($this->getPrimaryColumns() as $column) 
			$primary[$column->getName()] = $this->get($column->getName());

		return $primary;

	}
	
	final public function getPrimaryColumns() {
		
		$primary_columns = array();
		
		foreach($this->getColumns() as $column) {

			if(!$column->getPrimary())
				continue;
				
			array_push($primary_columns, $column);
			
		}
				
		return $primary_columns;
	
	}

	final public function safePopulate(array $attributes = array()) {

		foreach($attributes as $key => $value) {

			try {

				$this->set($key, $value);

			} catch(Yab_Exception $e) {

				continue;

			}

		}

		return $this;

	}

	public function getForm(array $attributes = array('method' => 'post', 'action' => '')) {

		$form = new Yab_Form($attributes);

		foreach($this->getColumns() as $column) {

			if($column->getPrimary() || $column->getSequence())
				continue;

			$form->setElement($column->getName(), array(
				'type' => 'text',
				'label' => $column->getName(),
				'id' => $column->getName(),
				'value' => $this->has($column->getName()) ? $this->get($column->getName()) : $column->getDefaultValue(),
			));

		}

		return $form;

	}

	final public function get($key, $filters = null) {

		if(!$this->hasColumn($key))
			throw new Yab_Exception($key.' is not a valid column name in table '.$this->getName());

		return parent::get($key, $filters);

	}

	final public function set($key, $value) {

		if(!$this->hasColumn($key))
			throw new Yab_Exception($key.' is not a valid column name in table '.$this->getName());

		parent::set($key, $value);

		try {

			if($this->_safe_primary === null && $this->getPrimary())
				$this->_safe_primary = $this->getPrimary();

		} catch(Yab_Exception $e) {}

		$this->_onSet($key, $value);
		
		return $this;

	}
	
	protected function _onSet($key, $value) {}

	final public function resetPrimary() {

		$this->_safe_primary = null;

		return $this;

	}            

	final public function clear() {

		parent::clear();

		return $this->resetPrimary();

	}

	final public function __toString() {

		return $this->_adapter->formatTable($this);

	}

}

// Do not clause PHP tags unless it is really necessary
