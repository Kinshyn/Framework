<?php
/**
 * Yab Framework
 *  
 * @category   Yab_Db_Adapter_Pdo
 * @package    Yab_Db_Adapter_Pdo_Mysql
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Db_Adapter_Pdo_Mysql extends Yab_Db_Adapter_Abstract {

	private $_pdo = null;

	private $_dsn = null;
	private $_username = null;
	private $_password = null;
	private $_driver_options = array();
	
	private $_affected_rows = null;

	public function construct($dsn = null, $username = null, $password = null, array $driver_options = array()) {

		if($dsn)
			$this->setDsn($dsn);

		if($username)
			$this->setUsername($username);

		if($password)
			$this->setPassword($password);

		if(count($driver_options))
			$this->setDriverOptions($driver_options);

	}
	
	public function setDsn($dsn) {
	
		$this->_dsn = (string) $dsn;
		
		return $this;
	
	}
	
	public function setUsername($username) {
	
		$this->_username = (string) $username;
		
		return $this;
	
	}
	
	public function setPassword($password) {
	
		$this->_password = (string) $password;
		
		return $this;
	
	}
	
	public function setDriverOptions(array $driver_options) {
	
		$this->_driver_options = $driver_options;
		
		return $this;
	
	}	
	
	public function addDriverOptions($key, $value) {
	
		$this->_driver_options[$key] = $value;
		
		return $this;
	
	}

	public function isConnected() {

		return (bool) ($this->_pdo !== null);

	}

	public function fetch($rowset) {

		if(!($rowset instanceof PDOStatement))
			throw new Yab_Exception('rowset must be an instance of PDOStatement');
	
		return $rowset->fetch(PDO::FETCH_ASSOC);

	}

	public function seek($rowset, $offset) {

		if(!($rowset instanceof PDOStatement))
			throw new Yab_Exception('rowset must be an instance of PDOStatement');

		$rowset->rewind();

		for($i = 0; $i < $offset; $i++)
			$rowset->fetch();
			
		return $this;

	}

	public function free($rowset) {

		if(!($rowset instanceof PDOStatement))
			throw new Yab_Exception('rowset must be an instance of PDOStatement');

		return $rowset->closeCursor();

	}

	public function getSelectedRows($rowset) {

		if(!($rowset instanceof PDOStatement))
			throw new Yab_Exception('rowset must be an instance of PDOStatement');

		return $this->_affected_rows;
		return count($rowset);

	}

	public function getSelectedSchema() {

		$rowset = $this->query('SELECT DATABASE();');

		while($row = $this->fetch($rowset)) 
			$selectedSchema = array_shift($row);

		$this->free($rowset);

		return $selectedSchema;

	}

	public function getTables($schema = null) {

		if($schema)
			$this->setSchema($schema);

		$tables = array();

		$rowset = $this->query('SHOW TABLES;');

		while($row = $this->fetch($rowset)) {

			$name = array_shift($row);

			array_push($tables, new Yab_Db_Table($this, $name, $schema));

		}

		$this->free($rowset);

		return $tables;

	}

	public function _columns($table) {

		$columns = array();

		$rowset = $this->query('DESCRIBE '.$this->quoteIdentifier($table).';');

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

		return $this->_pdo->lastInsertId();

	}

	protected function _connect() {

		$this->_pdo = new PDO($this->_dsn, $this->_username, $this->_password, $this->_driver_options);

		if(!$this->isConnected())
			throw new Yab_Exception('can not connect to mysql server with this dsn, username, password');

		return $this;

	}

	protected function _setSchema($schema) {

		return $this->query('USE '.$this->quoteIdentifier($schema).';');

	}

	final public function setEncoding($encoding = self::DEFAULT_ENCODING) {

		if(!$this->isConnected())
			$this->connect();

		return $this->query('SET NAMES '.$this->quoteIdentifier($encoding).';');

	}

	protected function _disconnect() {

		unset($this->_pdo);
	
		return true;

	}

	protected function _query($sql) {

		$statement = $this->_pdo->prepare($sql);
		
		$statement->setAttribute(PDO_MYSQL_ATTR_USE_BUFFERED_QUERY, 1);
		
		$statement->execute();
	
		$this->_affected_rows = $statement->rowCount();
	
		return $statement;

	}

	protected function _error() {

		return implode(', ', $this->_pdo->errorInfo());

	}

	protected function _affectedRows() {

		return $this->_affected_rows;

	}

	protected function _quote($text) {

		return $this->_pdo->quote($text);

	}

}

// Do not clause PHP tags unless it is really necessary
