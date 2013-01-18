<?php
/**
 * Yab Framework
 *  
 * @category   Yab_Db_Adapter
 * @package    Yab_Db_Adapter_Sybase
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Db_Adapter_Sybase extends Yab_Db_Adapter_Abstract {

	private $_connexion = null;

	private $_host = null;
	private $_login = null;
	private $_password = null;
	private $_appname = null;

	private $_tmp_tables = array();
	private $_selected_schema = array();

	public function construct($host = null, $login = null, $password = null, $encoding = null, $schema = null, $appname = null) {

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

		if($appname)
			$this->setAppname($appname);

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
	
	public function setAppname($appname) {
	
		$this->_appname = (string) $appname;
		
		return $this;
	
	}

	public function isConnected() {

		return is_resource($this->_connexion);

	}

	public function fetch($rowset) {

		return sybase_fetch_assoc($rowset);

	}

	public function seek($rowset, $row) {

		return @sybase_data_seek($rowset, $row);

	}

	public function free($rowset) {

		return sybase_free_result($rowset);

	}

	public function getSelectedRows($rowset) {

		return sybase_num_rows($rowset);

	}

	public function getSelectedSchema() {

		if($this->_selected_schema !== null)
			return $this->_selected_schema;

		$rowset = $this->query('SELECT DB_NAME()');

		while($row = $this->fetch($rowset)) 
			$this->_selected_schema = array_shift($row);

		$this->free($rowset);

		return $this->_selected_schema;

	}

	public function getTables($schema = null) {

		if($schema)
			$this->setSchema($schema);

		$tables = array();

		$rowset = $this->query('sp_tables');

		while($row = $this->fetch($rowset)) {

			if($schema && $row['table_qualifier'] != $schema)
				throw new Yab_Exception('table_qualifier is different from "'.$schema.'"');

			if($row['table_type'] != 'TABLE')
				continue;

			$name = trim($row['table_name']);

			if(!$name) continue;

			array_push($tables, new Yab_Db_Table($this, $name, $schema));

		}

		return $tables;

	}

	public function _columns($table) {

		$columns = array();
		$indexes = array();
		$uniques = array();

		$rowset = @$this->query('sp_helpindex '.$this->quoteIdentifier($table));

		while($row = $this->fetch($rowset)) {

			if(!array_key_exists('index_keys', $row))
				continue;

			foreach(explode(',', $row['index_keys']) as $index_key) {

				array_push($indexes, trim($index_key));

				if(is_numeric(strpos($row['index_description'], 'unique')))
					array_push($uniques, trim($index_key));

			}

		}

		$this->free($rowset);

		$rowset = $this->query('sp_columns '.$this->quoteIdentifier($table));

		while($row = $this->fetch($rowset)) {

			$column = new Yab_Db_Table_Column($table, trim($row['column_name']));

			$column->setPrimary(is_numeric(strpos($row['type_name'], 'identity')));
			$column->setUnique($column->getPrimary());
			$column->setUnsigned(true);
			$column->setSequence($column->getPrimary());
			$column->setNull($row['is_nullable'] == 'YES');
			$column->setDefaultValue($column->getNull() ? null : '');
			$column->setNumber(count($columns));
			$column->setQuotable(!preg_match('#(numeric|int|float)#is', $row['type_name']));

			if(in_array($column, $indexes))
				$column->setIndexed(true);

			if(!$column->getUnique() && in_array($column, $uniques))
				$column->setUnique(true);

			$columns[$row['column_name']] = clone $column;

		}

		$this->free($rowset);

		return $columns;

	}

	public function formatTable(Yab_Db_Table $table) {

		return $this->quoteIdentifier($table->getName());

	}

	public function limit($sql, $from, $offset) {

		if(!is_numeric($from))
			return $sql;

		$from = max(intval($from), 0);
		$offset = intval($offset);

		if($from < 0)
			throw new Yab_Exception('LIMIT argument from='.$from.' is not valid');

		if($offset < 0)
			throw new Yab_Exception('LIMIT argument offset='.$offset.' is not valid');

		$order_by = preg_match('#\sorder\s+by\s#i', $sql, $matches) ? $matches[0] : '';

		$full_order_by = $order_by ? stristr($sql, $order_by) : '';

		if($full_order_by)
			$sql = str_replace($full_order_by, '', $sql);

		if(preg_match('#\sUNION\s#i', $sql)) {

			$sql = 'SELECT * FROM ('.$sql.') as SBU';

			# stip aliases
			$full_order_by = preg_replace('#(\s)([a-zA-Z0-9_\-]+\.)([a-zA-Z0-9_\-]+)(\s)#is', '$1$3$4', $full_order_by);

		}

		$full_reversed_order_by_parts = explode(',', $full_order_by);

		foreach($full_reversed_order_by_parts as $key => $full_reversed_order_by_part) {

			$full_reversed_order_by_part = trim($full_reversed_order_by_part);

			if(!$full_reversed_order_by_part)
				continue;

			if(preg_match('#\s+desc$#i', $full_reversed_order_by_part)) {

				$full_reversed_order_by_part = preg_replace('#\s+desc$#i', ' ASC', $full_reversed_order_by_part);

			} else {

				$full_reversed_order_by_part = preg_replace('#\s+asc#i', '', $full_reversed_order_by_part);

				$full_reversed_order_by_part = $full_reversed_order_by_part.' DESC';

			}

			$full_reversed_order_by_parts[$key] = $full_reversed_order_by_part;

		}

		$full_reversed_order_by = implode(', ', $full_reversed_order_by_parts);

		# Strip table eventual table prefix
		$full_reversed_order_by = preg_replace('#(\s)([^\s]\.)([^\s]+)#', '$1$3', $full_reversed_order_by);

		if(!preg_match('#^select(.+)(from.+)$#Uis', ltrim($sql), $matches))
			throw new Yab_Exception('Can not locate any SELECT ... FROM statement in this SQL : "'.ltrim($sql).'"');

		$distinct = (preg_match('#^\s*distinct\s*#i', $matches[1]));

		if($distinct)
			$matches[1] = preg_replace('#^\s*distinct\s#i', '', $matches[1]);

		$tmp_table = '#tmp_table_'.substr(md5($sql), 0, 12);

		if(!in_array($tmp_table, $this->_tmp_tables)) {

			$this->query('SELECT'.($distinct ? ' DISTINCT' : '').' TOP '.($from + $offset).' '.trim($matches[1]).' INTO '.$tmp_table.' '.$matches[2].' '.$full_order_by);

			array_push($this->_tmp_tables, $tmp_table);

			$total = $this->getAffectedRows();

			# Readaptation éventuelle de l'offset si on est à la derniere page
			if($total < ($from + $offset))
				$offset -= ($from + $offset - $total);

		}

		$tmp_table_2 = '#tmp_table_2_'.substr(md5($sql), 0, 12);

		if(!in_array($tmp_table_2, $this->_tmp_tables)) {

			$this->query('SELECT TOP '.$offset.' * INTO '.$tmp_table_2.' FROM '.$tmp_table.' '.$full_reversed_order_by);

			array_push($this->_tmp_tables, $tmp_table_2);

		}

		# Strip table eventual table prefix
		$full_order_by = preg_replace('#(\s)([^\s]\.)([^\s]+)#', '$1$3', $full_order_by);

		return 'SELECT TOP '.$offset.' * FROM '.$tmp_table_2.' '.$full_order_by;

	}

	public function _quoteIdentifier($text) {

		return '['.$text.']';

	}

	protected function _lastInsertId($table) {

		$result = $this->query('SELECT @@IDENTITY');

		$line = $this->fetch($result);

		$this->free($result);

		return array_pop($line);

	}

	protected function _connect() {

		$this->_connexion = @sybase_connect($this->_host, $this->_login, $this->_password, $this->_encoding, $this->_appname);

		if(!$this->isConnected())
			throw new Yab_Exception('can not connect to sybase server with this host, login, password');

		return $this;

	}

	protected function _setSchema($schema) {

		$this->_selected_schema = null;

		return @sybase_select_db($schema, $this->_connexion);

	}

	final public function setEncoding($encoding = self::DEFAULT_ENCODING) {

		if($this->isConnected())
			throw new Yab_Exception('Sybase Adapter needs to set charset before connection');

		$this->_encoding = (string) $encoding;
			
		return $this;

	}

	protected function _disconnect() {

		return sybase_close($this->_connexion);

	}

	protected function _query($sql) {

		return @sybase_query($sql, $this->_connexion);

	}

	protected function _error() {

		return sybase_get_last_message($this->_connexion);

	}

	protected function _affectedRows() {

		return sybase_affected_rows($this->_connexion);

	}

	protected function _quote($text) {

		return "'".str_replace("'", "''", $text)."'";

	}

}

// Do not clause PHP tags unless it is really necessary
