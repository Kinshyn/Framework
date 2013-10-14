<?php
/**
 * Yab Framework
 *  
 * @category   Yab_Db_Adapter
 * @package    Yab_Db_Adapter_Oracle
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Db_Adapter_Oracle extends Yab_Db_Adapter_Abstract {

        const YAB_LIMIT_ROWNUM = 'YAB_LIMIT_ROWNUM';
    
	private $_connexion = null;

	private $_host = null;
	private $_login = null;
	private $_password = null;

	private $_tmp_tables = array();
	private $_selected_schema = array();

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
		
		$data = oci_fetch_assoc($rowset);
                
                if(array_key_exists(self::YAB_LIMIT_ROWNUM, $data))
                        unset($data[self::YAB_LIMIT_ROWNUM]);
                
                return $data;

	}

	public function seek($rowset, $row) {

		throw new Yab_Exception('Can not seek an Oracle rowset');

	}

	public function free($rowset) {

		return oci_free_statement($rowset);

	}

	public function getSelectedRows($rowset) {

		return oci_num_rows($rowset);

	}

	public function getSelectedSchema() {

		$rowset = $this->query("SELECT SYS_CONTEXT('USERENV','SESSION_SCHEMA') current_schema FROM dual");

		while($row = $this->fetch($rowset)) 
			$selectedSchema = array_shift($row);

		$this->free($rowset);

		return $selectedSchema;
		
	}

	public function getTables($schema = null) {

		if($schema)
			$this->setSchema($schema);
		
		$tables = array();

		$rowset = $this->query('SELECT table_name FROM user_tables');

		while($row = $this->fetch($rowset)) {

			$name = trim($row['TABLE_NAME']);

			if(!$name) continue;

			array_push($tables, new Yab_Db_Table($this, $name, $schema));

		}

		return $tables;

	}

	public function _columns($table) {

		$columns = array();

		$rowset = $this->query("
			SELECT utc.*
			FROM user_tab_columns utc
			WHERE utc.table_name = ".$this->quote($this->unQuoteIdentifier($table))."
		;");

		while($row = $this->fetch($rowset)) {

			$column = new Yab_Db_Table_Column($table, $row['COLUMN_NAME']);
			
			$column->setNull(strtolower($row['NULLABLE']) == 'y');
			$column->setQuotable(!preg_match('#int|numeric|float|decimal|number#i', $row['DATA_TYPE']));
			$column->setDefaultValue($column->getNull() && !$row['DATA_DEFAULT'] ? null : $row['DATA_DEFAULT']);
			$column->setNumber(count($columns));
			$column->setType($row['DATA_TYPE']);

			$columns[$row['COLUMN_NAME']] = clone $column;

		}
		
		$rowset = $this->query("
			SELECT ucc.*, uc.*
			FROM user_cons_columns ucc
			INNER JOIN user_constraints uc ON uc.constraint_name = ucc.constraint_name
			WHERE uc.constraint_type = 'P'
			AND uc.table_name = ".$this->quote($this->unQuoteIdentifier($table))."
		;");

		while($row = $this->fetch($rowset)) {

			$columns[$row['COLUMN_NAME']]->setPrimary(true)->addUnique('PRIMARY')->addIndex('PRIMARY');

		}

		return $columns;
		
	}

	public function formatTable(Yab_Db_Table $table) {

		return $this->quoteIdentifier($table->getName());

	}

	public function limit($sql, $from, $offset) {

            return '
                SELECT T_ORA_SQL2.*  
                FROM (
                    SELECT T_ORA_SQL1.*, ROWNUM '.self::YAB_LIMIT_ROWNUM.'
                    FROM ('.$sql.') T_ORA_SQL1
                    WHERE ROWNUM <= '.intval($from + $offset).'
                ) T_ORA_SQL2
                WHERE T_ORA_SQL2.'.self::YAB_LIMIT_ROWNUM.' > '.intval($from).'
            ';
		
	}

	public function _quoteIdentifier($text) {

		return '"'.$text.'"';

	}

	protected function _lastInsertId($sequence) {

		$result = $this->query('SELECT '.strtoupper($sequence).'.CURRVAL FROM DUAL');

		$line = $this->fetch($result);

		$this->free($result);

		return array_pop($line);
		
	}

	protected function _connect() {

		$this->_connexion = oci_connect($this->_login, $this->_password, $this->_host);

		if(!$this->isConnected())
			throw new Yab_Exception('can not connect to oracle server with this host, login, password');

		return $this;

	}
	
	public function _setSchema($schema) {

		if ($this->query('ALTER SESSION SET CURRENT_SCHEMA='.$this->quoteIdentifier($schema).';'))
			return true;
			
		return false;
	
	}

	final public function setEncoding($encoding = self::DEFAULT_ENCODING) {

		if($this->isConnected())
			throw new Yab_Exception('Oracle Adapter needs to set charset before connection');

		$this->_encoding = (string) $encoding;
			
		return $this;

	}

	protected function _disconnect() {

		return oci_close($this->_connexion);

	}

	protected function _query($sql) {

		$query_parse = oci_parse($this->_connexion, $sql);
			
		oci_execute($query_parse);
		
		$query_commit = oci_parse($this->_connexion, 'COMMIT');
			
		oci_execute($query_commit);

		return $query_parse;

	}

	protected function _error() {

		return oci_error($this->_connexion);

	}

	protected function _affectedRows() {

		return oci_num_rows($this->_connexion);

	}

	protected function _quote($text) {

		return "'".str_replace("'", "''", $text)."'";

	}

}

// Do not clause PHP tags unless it is really necessary
