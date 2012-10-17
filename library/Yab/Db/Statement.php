<?php
/**
 * Yab Framework
 *  
 * @category   Yab_Db
 * @package    Yab_Db_Statement
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Db_Statement implements Iterator, Countable {

	const LEFT_PACK_BOUNDARY = '###-_-';
	const RIGHT_PACK_BOUNDARY = '-_-###';

	private $_adapter = null;

	private $_sql = null;
	private $_result = null;
	private $_nb_rows = null;

	private $_row = null;

	private $_start = 0;
	private $_length = 0;
	
	private $_offset = -1;

	private $_key = null;
	private $_value = null;
	
	private $_packs = array();

	public function __construct(Yab_Db_Adapter_Abstract $adapter, $sql) {

		$this->_adapter = $adapter;

		$this->_sql = (string) $sql;
			
		$this->trim();

	}

	public function setKey($key) {

		$this->_key = (string) $key;

		return $this;

	}

	public function setValue($mixed) {

		if(is_object($mixed) && !($mixed instanceof Yab_Object))
			throw new Yab_Exception('value must be instance of Yab_Object');

		$this->_value = is_object($mixed) ? clone $mixed : $mixed;

		return $this;

	}

	public function getKey() {

		return $this->_key;

	}

	public function getValue() {

		return $this->_value;

	}

	public function toArray() {

		$array = array();

		foreach($this as $key => $value)
			$array[$key] = is_object($value) ? clone $value : $value;

		return $array;

	}

	public function toRow() {

		foreach($this as $row)
			return $row;

		throw new Yab_Exception('Can not return row because statement does not return any rows');

	}

	public function bind($key, $value, $quote = true) {

		if(is_bool($value) && $quote === true) {
		
			$value = $key;
			
			$key = '?';
			
		}
			
		$this->trim();
		
		$this->pack(false);
	
		if(is_array($value)) {
	
			$this->_sql = str_replace($key, $quote ? implode(', ', array_map(array($this->_adapter, 'quote'), $value)) : implode(', ', $value), $this->_sql);

		} else {
	
			$this->_sql = str_replace($key, $quote ? $this->_adapter->quote($value) : $value, $this->_sql);

		}
			
		$this->unpack();
		
		return $this;

	}

	public function sqlLimit($start, $length) {

		$this->_sql = $this->_adapter->limit($this->_sql, max(0, intval($start)), max(1, intval($length)));

		return $this;

	}

	public function limit($start, $length) {

		$this->_start = max(0, intval($start));
		$this->_length = max(1, intval($length));

		return $this;

	}

	public function noLimit() {

		$this->_start = 0;
		$this->_length = 0;

		return $this;

	}

	public function free() {

		$this->_result = null;
		$this->_nb_rows = null;

		return $this;

	}

	public function query() {

		if($this->_result !== null)
			return $this->_result;

		$this->_result = $this->_adapter->query($this->_sql);
		
		return $this->_result;

	}

	public function hasNext() {

		return (bool) ($this->_offset - $this->_start + 1 < min(count($this), $this->_length));

	}

	public function isFirst() {

		return (bool) ($this->_offset - $this->_start == 0);

	}

	public function isLast() {

		return !$this->hasNext();

	}
	
	public function count() {

		if(is_numeric($this->_nb_rows))
			return $this->_nb_rows;
	
		if($this->isSelect()) {
			
			if($this->_result === null) {

				$statement = new self($this->_adapter, 'SELECT COUNT(*) FROM ('.$this->_sql.') as T');
			
				$this->_nb_rows = $statement->toRow()->pop();
				
				unset($statement);
				
			} else {

				$this->_nb_rows = $this->_adapter->getSelectedRows($this->_result);
				
			}

		} else {
			
			$this->query();
			
			$this->_nb_rows = $this->_adapter->getAffectedRows();
		
		}

		return $this->_nb_rows;

	}

	public function rewind() {

		$this->query();

		$this->_adapter->seek($this->_result, $this->_start);

		$this->_offset = $this->_start - 1;

		return $this->next();

	}

	public function next() {

		$this->query();

		$this->_row = $this->_adapter->fetch($this->_result);

		$this->_offset++;

		return $this;

	}

	public function valid() {

		if($this->_length && $this->_length <= $this->_offset - $this->_start)
			return false;

		return (bool) $this->_row;

	}

	public function key() {

		if($this->_key !== null)
			return (string) $this->_row[$this->_key];

		return $this->_offset;

	}

	public function current() {

		if(is_array($this->_value))
			return $this->_row;

		if(is_object($this->_value))
			return $this->_value->feed($this->_row);

		if($this->_value !== null)
			return $this->_row[$this->_value];

		return new Yab_Object($this->_row);

	}

	public function getSql() {

		return $this->_sql;

	}
	
	public function getPackedSql() {

		$this->pack();
	
		$packed_sql = $this->_sql;
		
		$this->unpack();
		
		return $packed_sql;

	}

	public function getAdapter() {

		return $this->_adapter;

	}

	public function offset() {

		return $this->_offset;

	}

	public function trim() {

		$this->_sql = trim($this->_sql);
		
		while(preg_match('#^\(#', $this->_sql) && preg_match('#\)$#', $this->_sql)) 
			$this->_sql = trim(substr($this->_sql, 1, -1));

		return $this;

	}
	
	public function pack($recursive_pack = true) {

		if(count($this->_packs))
			throw new Yab_Exception('You can not pack an already packed statement');

		$regexps = array();
		
		if($recursive_pack)
			array_unshift($regexps, '#(\([^\)\(]*\))#');
			
		array_push($regexps, '#('.preg_quote(substr($this->_adapter->quote('"'), 1, -1), '#').')#');
		array_push($regexps, '#("[^"]*")#');
		array_push($regexps, '#('.preg_quote(substr($this->_adapter->quote("'"), 1, -1), '#').')#');
		array_push($regexps, "#('[^']*')#");

		$i = 0;
		
		$this->_packs = array();

		foreach($regexps as $regexp) {
		
			while(preg_match($regexp, $this->_sql, $matches)) {
			
				$this->_packs[$i] = $matches[1];
			
				$this->_sql = str_replace($matches[1], self::LEFT_PACK_BOUNDARY.$i.self::RIGHT_PACK_BOUNDARY, $this->_sql);
			
				$i++;
				
			}
		
		}
	
		return $this;
	
	}
	
	public function unpack() {

		while(preg_match('#'.preg_quote(self::LEFT_PACK_BOUNDARY, '#').'([0-9]+)'.preg_quote(self::RIGHT_PACK_BOUNDARY, '#').'#is', $this->_sql, $match) && array_key_exists($match[1], $this->_packs))
			$this->_sql = str_replace(self::LEFT_PACK_BOUNDARY.$match[1].self::RIGHT_PACK_BOUNDARY, $this->_packs[$match[1]], $this->_sql);

		$this->_packs = array();
		
		return $this;
	
	}
	
	public function isSelect() {
	
		return preg_match('#^\s*select\s+.*\s+from\s+#is', $this->getPackedSql());
	
	}
	
	public function select($select) {
	
		$this->pack();
		
		$this->_sql = preg_replace('#^\s*select\s+.*\s+from\s+#is', 'SELECT '.$select.' FROM ', $this->_sql);
		
		$this->unpack();
		
		return $this;
	
	}
	
	public function getSelect() {
	
		$select = array();
		
		$sql_selects = preg_replace('#^\s*SELECT\s(.+)\sFROM\s+.*$#is', '$1', $this->getPackedSql());

		$sql_selects = explode(',', $sql_selects);
		$sql_selects = array_map('trim', $sql_selects);
		
		foreach($sql_selects as $sql_select) {
		
			$sql_select = preg_split('#\s+AS\s+#is', $sql_select);
			$sql_select = array_map('trim', $sql_select);

			array_push($select, array_pop($sql_select));
			
		}

		return $select;
	
	}
	
	public function getTables() {

		$tables = array();

		if(preg_match('#\s*SELECT\s+.+\s+FROM\s+(.+)\s*(ORDER\s+BY|LIMIT|GROUP|WHERE|INNER|LEFT|RIGHT|JOIN|$)#Uis', $this->getPackedSql(), $match))
			$tables += $this->_extractTables(preg_split('#\s*,\s*#is', $match[1]));

		preg_match_all('#\s+JOIN\s+(.+)\s+ON\s+(.+)(ORDER\s+BY|LIMIT|GROUP|WHERE|INNER|LEFT|RIGHT|JOIN|$)#Uis', $this->getPackedSql(), $match);

		$tables += $this->_extractTables($match[1]);

		return $tables;
	
	}	
	
	private function _extractTables($aliased_tables) {
	
		if(!is_array($aliased_tables))
			$aliased_tables = array($aliased_tables);
	
		$tables = array();
	
		foreach($aliased_tables as $table) {

			$table = trim($table);
		
			$table = preg_split('#\s+#s', $table);
		
			$name = array_shift($table);
			
			$name = explode('.', $name);
			$name = array_pop($name);
			
			$alias = array_shift($table);
			
			if(!$alias)
				$alias = $name;
		
			$name = $this->_adapter->unquoteIdentifier($name);
			$alias = $this->_adapter->unquoteIdentifier($alias);
				
			$tables[$alias] =  $this->_adapter->getTable($name);
		
		}
		
		return $tables;
	
	}	

	public function where($where, $operator = 'AND') {

		if(!$where)
			return $this;
			
		$this->trim();
		
		$this->pack();
		
		$matches = array(
			'#\sWHERE\s#i' => ' WHERE ('.$where.') '.$operator.' ',
			'#\sGROUP\s+BY\s#i' => ' WHERE ('.$where.') GROUP BY ',
			'#\sORDER\s+BY\s#i' => ' WHERE ('.$where.') ORDER BY ',
			'#$#i' => ' WHERE '.$where,
		);
		
		foreach($matches as $search => $replace) {

			if(!preg_match($search, $this->_sql))
				continue;

			$this->_sql = preg_replace($search, $replace, $this->_sql, 1);

			break;

		}		
		
		$this->unpack();

		return $this;

	}

	public function orderBy($order_by) {

		$sql_parts = preg_split('#\s+ORDER\s+BY\s+#', $this->_sql);
	
		$order_by_clause = array_pop($sql_parts);
		
		if(!preg_match('#^[a-zA-Z0-9\s\._,]+$#', $order_by_clause))
			array_push($sql_parts, $order_by_clause);
			
		$sql = implode(' ORDER BY ', $sql_parts);
		
		foreach($order_by as $column => $order)
			$order_by[$column] = $column.' '.$order;

		$sql .= ' ORDER BY '.implode(', ', $order_by);

		$this->_sql = $sql;
	
		return $this;

	}
	
	public function getOrderBy() {
	
		$order_by = array();

		$sql_parts = preg_split('#\s+ORDER\s+BY\s+#', $this->getPackedSql());

		if(count($sql_parts) < 2)
			return $order_by;
			
		$order_by_clauses = array_pop($sql_parts);
		
		if(!preg_match('#^[a-zA-Z0-9\s\._,]+$#', $order_by_clauses))
			return $order_by;

		$order_by_clauses = explode(',', $order_by_clauses);
		$order_by_clauses = array_map('trim', $order_by_clauses);

		foreach($order_by_clauses as $order_by_clause) {

			$order_by_clause = preg_split('#\s+#', $order_by_clause);

			$column_name = array_shift($order_by_clause);
			$column_order = array_shift($order_by_clause);

			$order = true;

			foreach($order_by as $key => $value) {

				if(preg_match('#'.preg_quote($key, '#').'#is', $column_name))
					$order &= false;

			}

			if($order)
				$order_by[$column_name] = $column_order;

		}

		return $order_by;
	
	}
	
	public function getGroupBy() {
	
		$group_by = array();

		if(!preg_match('#\s+GROUP\s+BY\s+([a-zA-Z0-9\-_.,\s]+)\s*(ORDER\s+BY|LIMIT|HAVING|$)#is', $this->getPackedSql(), $match))
			return $group_by;
	
		$parts = preg_split('#\s*,\s*#is', $match[1]);
		
		foreach($parts as $part) {
		
			$part = trim($part);
		
			$group_by[$part] = $part;
		
		}

		return $group_by;
	
	}

	public function __toString() {

		return $this->_sql;

	}

	public function __destruct() {

		if($this->_result !== null)
			$this->_adapter->free($this->_result);

	}

}

// Do not clause PHP tags unless it is really necessary