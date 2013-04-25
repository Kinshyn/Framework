<?php
/**
 * Yab Framework
 *  
 * @category   Yab_Db_Adapter
 * @package    Yab_Db_Adapter_Mysql
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Db_Adapter_Mysql extends Yab_Db_Adapter_Abstract {

	private $_connexion = null;

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

		return is_resource($this->_connexion);

	}

	public function fetch($rowset) {

		return mysql_fetch_assoc($rowset);

	}

	public function seek($rowset, $row) {

		if(!mysql_num_rows($rowset))
			return true;

		return mysql_data_seek($rowset, $row);

	}

	public function free($rowset) {

		return mysql_free_result($rowset);

	}

	public function getSelectedRows($rowset) {

		return mysql_num_rows($rowset);

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

		return mysql_insert_id($this->_connexion);

	}

	protected function _connect() {

		$this->_connexion = @mysql_connect($this->_host, $this->_login, $this->_password);

		if(!$this->isConnected())
			throw new Yab_Exception('can not connect to mysql server with this host, login, password');

		return $this;

	}

	protected function _setSchema($schema) {

		return $this->query('USE '.$this->quoteIdentifier($schema).';', $this->_connexion);

	}

	final public function setEncoding($encoding = self::DEFAULT_ENCODING) {

		if(!$this->isConnected())
			$this->connect();

		return $this->query('SET NAMES '.$this->quoteIdentifier($encoding).';', $this->_connexion);

	}

	protected function _disconnect() {

		return mysql_close($this->_connexion);

	}

	protected function _query($sql) {

		return mysql_query($sql, $this->_connexion);

	}

	protected function _error() {

		return mysql_error($this->_connexion);

	}

	protected function _affectedRows() {

		return mysql_affected_rows($this->_connexion);

	}

	protected function _quote($text) {

		return "'".mysql_real_escape_string($text, $this->_connexion)."'";

	}

}

// Do not clause PHP tags unless it is really necessary