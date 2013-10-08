<?php
/**
 * Yab Framework
 *  
 * @category   Yab_Db_Adapter
 * @package    Yab_Db_Adapter_Mysqli
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Db_Adapter_Mysqli extends Yab_Db_Adapter_Abstract {

	private $_mysqli = null;

	private $_host = null;
	private $_login = null;
	private $_password = null;

	public function construct($host = null, $login = null, $password = null, $encoding = null, $schema = null) {

		if($host)
			$this->setHost($host);

		if($login)
			$this->setLogin($login);

		if($password)
			$this->setPassword($password);

		if($encoding)
			$this->setEncoding($encoding);

		if($schema)
			$this->setSchema($schema);

	}
	
	public function setHost($host) {
	
		$this->_host = (string) $host;
		
		return $this;
	
	}
	
	public function setLogin($login) {
	
		$this->_login = (string) $login;
		
		return $this;
	
	}
	
	public function setPassword($password) {
	
		$this->_password = (string) $password;
		
		return $this;
	
	}

	public function isConnected() {

		return (bool) ($this->_mysqli instanceof mysqli);

	}

	public function fetch($rowset) {

		if(!($rowset instanceof mysqli_result))
			throw new Yab_Exception('rowset must be an instance of mysqli_result');
	
		return $rowset->fetch_assoc();

	}

	public function seek($rowset, $row) {

		if(!($rowset instanceof mysqli_result))
			throw new Yab_Exception('rowset must be an instance of mysqli_result');
	
		return $rowset->data_seek($row);

	}

	public function free($rowset) {

		if(!($rowset instanceof mysqli_result))
			throw new Yab_Exception('rowset must be an instance of mysqli_result');

		return $rowset->free();

	}

	public function getSelectedRows($rowset) {

		if(!($rowset instanceof mysqli_result))
			throw new Yab_Exception('rowset must be an instance of mysqli_result');

		return $rowset->num_rows;

	}

	public function getSelectedSchema() {

		$rowset = $this->query('SELECT DATABASE()');

		while($row = $this->fetch($rowset)) 
			$selectedSchema = array_shift($row);

		$this->free($rowset);

		return $selectedSchema;

	}

	public function getTables($schema = null) {

		if($schema)
			$this->setSchema($schema);

		$tables = array();

		$rowset = $this->query('SHOW TABLES');

		while($row = $this->fetch($rowset)) {

			$name = array_shift($row);

			array_push($tables, new Yab_Db_Table($this, $name, $schema));

		}

		$this->free($rowset);

		return $tables;

	}

	public function _columns($table) {

		$columns = array();

		$rowset = $this->query('DESCRIBE '.$this->quoteIdentifier($table));

		while($row = $this->fetch($rowset)) {

			$column = new Yab_Db_Table_Column($table, $row['Field']);
			$column->setPrimary($row['Key'] == 'PRI');
			$column->addUnique($row['Key'] == 'PRI' || $row['Key'] == 'UNI');
			$column->addIndex($row['Key']);
			$column->setUnsigned(is_numeric(stripos($row['Type'], 'unsigned')));
			$column->setSequence($row['Extra'] == 'auto_increment');
			$column->setNull($row['Null'] == 'YES');
			$column->setDefaultValue($column->getNull() && !$row['Default'] ? null : $row['Default']);
			$column->setNumber(count($columns));
			$column->setQuotable(!preg_match('#int|numeric|float|decimal#i', $row['Type']));
			$column->setType($row['Type']);

			$columns[$row['Field']] = clone $column;

		}

		return $columns;

	}

	public function formatTable(Yab_Db_Table $table) {

		return $this->quoteIdentifier($table->getSchema()).'.'.$this->quoteIdentifier($table->getName());

	}

	public function limit($sql, $from, $offset) {

		$from = intval($from);
		$offset = intval($offset);

		if(!$offset && !$from)
			return trim($sql, ';');

		if(!$offset)
			return trim($sql).PHP_EOL.'LIMIT '.$from;

		return trim($sql).PHP_EOL.'LIMIT '.$from.', '.$offset;

	}

	public function _quoteIdentifier($text) {

		return '`'.$text.'`';

	}

	protected function _lastInsertId($table) {

		return $this->_mysqli->insert_id;

	}

	protected function _connect() {

		$this->_mysqli = new mysqli($this->_host, $this->_login, $this->_password);
		
		if(!$this->isConnected())
			throw new Yab_Exception('can not connect to mysql server with this host, login, password');

		return $this;

	}

	protected function _setSchema($schema) {

		return $this->_mysqli->select_db($schema);

	}

	final public function setEncoding($encoding = self::DEFAULT_ENCODING) {

		if(!$this->isConnected())
			$this->connect();

		return $this->query('SET NAMES '.$this->quoteIdentifier($encoding).';', $this->_mysqli);

	}

	protected function _disconnect() {

		$this->_mysqli->close();
		
		unset($this->_mysqli);
		
		$this->_mysqli = null;
		
		return $this;

	}

	protected function _query($sql) {

		$rowset = $this->_mysqli->query($sql);

		$this->_affected_rows = isset($rowset->affected_rows) ? $rowset->affected_rows : 0;

		return $rowset;

	}

	protected function _error() {

		return $this->_mysqli->error;

	}

	protected function _affectedRows() {

		return $this->_affected_rows;

	}

	protected function _quote($text) {

		return "'".str_replace("'", "\'", $text)."'";

	}

}

// Do not clause PHP tags unless it is really necessary
