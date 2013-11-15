<?php
/**
 * Yab Framework
 *  
 * @category   Yab_Db_Adapter
 * @package    Yab_Db_Adapter_Abstract
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

abstract class Yab_Db_Adapter_Abstract {

	const DEFAULT_ENCODING = 'UTF-8';

	static private $_default_adapter = null;

	private $_columns = array();
	
	final public function __construct() {

		if(!self::hasDefaultAdapter())
			self::setDefaultAdapter($this);
	
		$args = func_get_args();
			
		Yab_Loader::getInstance()->invoke($this, 'construct', $args);
	
	}
	
	public function construct() {}
	
	abstract public function setEncoding($encoding = self::DEFAULT_ENCODING);
	abstract public function isConnected();
	abstract public function _quoteIdentifier($text);
	abstract public function fetch($rowset);
	abstract public function free($rowset);
	abstract public function limit($sql, $from, $offset);
	abstract public function getSelectedRows($rowset);
	abstract public function getSelectedSchema();
	abstract public function getTables($schema = null);
	abstract public function _columns($table);
	abstract public function formatTable(Yab_Db_Table $table);

	abstract protected function _lastInsertId($table);
	abstract protected function _disconnect();
	abstract protected function _setSchema($schema);
	abstract protected function _connect();
	abstract protected function _error();
	abstract protected function _query($sql);
	abstract protected function _affectedRows();
	abstract protected function _quote($text);

	final public function setDefault() {

		self::setDefaultAdapter($this);

		return $this;

	}

	final public function quote($text) {

		if(!$this->isConnected())
			$this->connect();

		return $this->_quote($text);

	}

	final public function quoteIdentifier($text) {

		return $this->_quoteIdentifier($this->unQuoteIdentifier($text));

	}

	final public function getAffectedRows() {

		if(!$this->isConnected())
			$this->connect();

		return $this->_affectedRows();

	}

	final public function getLastInsertId($table) {

		if(!$this->isConnected())
			$this->connect();

		return $this->_lastInsertId($table);

	}

	final public function disconnect() {

		if(!$this->isConnected())
			throw new Yab_Exception('can not disconnect from db server, no connexion');

		$this->_disconnect();

		return $this;

	}

	final public function setSchema($schema) {

		if(!$this->isConnected())
			$this->connect();

		$this->_setSchema($schema);

		return $this;

	}

	final public function connect() {

		if($this->isConnected())
			throw new Yab_Exception('already connected to the db server');

		$this->_connect();

		return $this;

	}
	
	final static public function hasDefaultAdapter() {

		return self::$_default_adapter instanceof Yab_Db_Adapter_Abstract;

	}

	final static public function setDefaultAdapter(Yab_Db_Adapter_Abstract $adapter) {

		self::$_default_adapter = $adapter;

	}

	final static public function getDefaultAdapter() {

		if(!self::$_default_adapter)
			throw new Yab_Exception('no default db adapter defined');
	
		return self::$_default_adapter;

	}

	final public function getError() {

		if(!$this->isConnected())
			$this->connect();

		return $this->_error();

	}

	final public function query($sql) {

		if(!$this->isConnected())
			$this->connect();

		Yab_Loader::getInstance('Yab_Observer')->notify('SQL_QUERY', array($sql));
		
		$sql = rtrim($sql, ';');

		$rowset = $this->_query($sql);

		if(!$rowset)
			throw new Yab_Exception($this->getError());

		return $rowset;

	}

	final public function __destruct() {

		if($this->isConnected())
			$this->disconnect();

	}
	
	final public function resetColumns($table = null) {
		
		$table_name = (string) $table;
		
		if($table_name) {
		
			if(array_key_exists($table_name, $this->_columns))
				unset($this->_columns[$table_name]);
				
		} else {
		
			$this->_columns = array();
		
		}
		
		return $this;
	
	}
	
	final public function getColumns($table) {
		
		$table_name = (string) $table;
		
		if(array_key_exists($table_name, $this->_columns))
			return $this->_columns[$table_name];
	
		$this->_columns[$table_name] = $this->_columns($table);
		
		return $this->_columns[$table_name];
	
	}

	final public function getTable($name, $schema = null) {

		if($name instanceof Yab_Db_Table)
			return $name;

		try {

			if($schema === null)
				return Yab_Loader::getInstance()->invoke($name, array($this), 'Yab_Db_Table');

		} catch(Yab_Exception $e) {}

		$name = $this->quoteIdentifier($name);
		$schema = $this->quoteIdentifier($schema);

		$key = $this->unQuoteIdentifier($schema.'.'.$name);

		return new Yab_Db_Table($this, $key);

	}

	final public function prepare($sql) {

		return new Yab_Db_Statement($this, $sql);

	}

	final public function insert($table, array $values) {

		$table = $this->getTable($table);

		foreach($values as $key => $value)
			$values[$key] = $value !== null ? ($table->getColumn($key)->getQuotable() ? $this->quote($value) : (is_numeric($value) ? $value : 'NULL')) : 'NULL';

		$this->query('INSERT INTO '.$this->quoteIdentifier($table).' ('.implode(', ', array_map(array($this, 'quoteIdentifier'), array_keys($values))).') VALUES ('.implode(', ', $values).')');

		return $this->getAffectedRows();

	}

	final public function update($table, array $values, $where) {

		$table = $this->getTable($table);

		foreach($values as $key => $value)
			$values[$key] = $this->quoteIdentifier($key).' = '.($value !== null ? ($table->getColumn($key)->getQuotable() ? $this->quote($value) : (is_numeric($value) ? $value : 'NULL')) : 'NULL');

		$this->query('UPDATE '.$this->quoteIdentifier($table).' SET '.implode(', ', $values).' WHERE '.$where);

		return $this->getAffectedRows();

	}

	final public function delete($table, $where) {

		$this->query('DELETE FROM '.$this->quoteIdentifier($table).' WHERE '.$where);

		return $this->getAffectedRows();

	}

	final public function truncate($table) {

		$this->query('DELETE FROM '.$this->quoteIdentifier($table));

		return $this->getAffectedRows();

	}

	final public function unQuoteIdentifier($text) {

		$text = preg_replace('#^[^a-zA-Z0-9_\-]*#', '', trim((string) $text));
		$text = preg_replace('#[^a-zA-Z0-9_\-]*$#', '', trim((string) $text));

		return trim((string) $text);

	}

}

// Do not clause PHP tags unless it is really necessary
