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

	const LEFT_PACK_BOUNDARY = '-L_-';
	const RIGHT_PACK_BOUNDARY = '-_R-';

	const EXPRESSION_ROWNUM = 'ROWNUM';
	
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
	
	public function isExecuted() {
	
		return (bool) ($this->_result !== null);
	
	}

	public function execute() {

		if($this->isExecuted())
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

				$statement = new self($this->_adapter, 'SELECT COUNT(*) FROM ('.$this->_sql.') T');

				$this->_nb_rows = $statement->toRow()->pop();

				unset($statement);

			} else {

				$this->_nb_rows = $this->_adapter->getSelectedRows($this->_result);

			}

		} else {

			$this->execute();
			
			$this->_nb_rows = $this->_adapter->getAffectedRows();
		
		}

		return $this->_nb_rows;

	}

	public function rewind() {

		$executed = $this->isExecuted();
	
		$this->execute();

		try {
				
			$this->_adapter->seek($this->_result, $this->_start);

		} catch(Yab_Exception $e) {
			
			// must reexecute if already executed and seek not implemented (ex: Oracle)
			if($executed)
				$this->free()->execute();
			
		}
                
		$this->_offset = $this->_start - 1;

		return $this->next();

	}

	public function next() {

		$this->execute();

		if($row = $this->_adapter->fetch($this->_result)) {
		
			if(!$this->_row)
				$this->_row = new Yab_Object();
		
			$this->_row->clear()->feed($row);
		
		} else {
		
			$this->_row = null;
		
		}
		
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
			return $this->_row->expression($this->_key, array(self::EXPRESSION_ROWNUM => $this->_offset));

		return $this->_offset;

	}

	public function current() {

		if(is_array($this->_value))
			return $this->_row->toArray();

		if(is_object($this->_value))
			return $this->_value->feed($this->_row->toArray());

		if($this->_value !== null) 
			return $this->_row->expression($this->_value, array(self::EXPRESSION_ROWNUM => $this->_offset));

		return $this->_row;

	}

	public function getSql() {

		return $this->_sql;

	}

	public function getAdapter() {

		return $this->_adapter;

	}

	public function offset() {

		return $this->_offset;

	}

	public function trim() {

		$this->_sql = trim($this->_sql);
		
		while(preg_match('#^\(#', $this->_sql) && preg_match('#\)\s*[a-zA-Z0-9\-_]*\s*$#', $this->_sql)) 
			$this->_sql = trim(substr($this->_sql, 1, -1));

		return $this;

	}
	
	public function getPackedSql() {

		$this->pack();
	
		$packed_sql = $this->_sql;
		
		$this->unpack();
		
		return $packed_sql;

	}
	
	public function pack() {

		if(count($this->_packs))
			throw new Yab_Exception('You can not pack an already packed statement');

		$regexps = array();
		
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
	
	public function unpack($sql_or_index = null) {

		if($sql_or_index === null) {
		
			while(preg_match('#'.preg_quote(self::LEFT_PACK_BOUNDARY, '#').'([0-9]+)'.preg_quote(self::RIGHT_PACK_BOUNDARY, '#').'#is', $this->_sql, $match) && array_key_exists($match[1], $this->_packs))
				$this->_sql = str_replace(self::LEFT_PACK_BOUNDARY.$match[1].self::RIGHT_PACK_BOUNDARY, $this->_packs[$match[1]], $this->_sql);

			$this->_packs = array();

			return $this;
		
		}
		
		$this->pack();

		if(is_numeric($sql_or_index)) {
		
			$sql = $this->_packs[$sql_or_index];
			
		} else {
		
			$sql = (string) $sql_or_index;
			
		}

		while(preg_match('#'.preg_quote(self::LEFT_PACK_BOUNDARY, '#').'([0-9]+)'.preg_quote(self::RIGHT_PACK_BOUNDARY, '#').'#is', $sql, $match) && array_key_exists($match[1], $this->_packs))
			$sql = str_replace(self::LEFT_PACK_BOUNDARY.$match[1].self::RIGHT_PACK_BOUNDARY, $this->_packs[$match[1]], $sql);

		$this->unpack();
		
		return $sql;

	}
	
	public function isSelect() {
	
		return preg_match('#^\s*select\s+.*\s+from\s+#is', $this->getPackedSql());
	
	}
	
	public function hasUnion() {
	
		return preg_match('#^\s+union\s+#is', $this->getPackedSql());
	
	}
	
	public function select($select) {
	
		$this->pack();
		
		$this->_sql = preg_replace('#^\s*select\s+.*\s+from\s+#is', 'SELECT '.$select.' FROM ', $this->_sql, 1);
		
		$this->unpack();
		
		return $this;
	
	}
	
	public function getSelect($explain_wildcards = false) {
	
		$select = array();
		
		$sql_selects = preg_replace('#^\s*SELECT\s(.+)\sFROM\s+.*$#Uis', '$1', $this->getPackedSql());

		$sql_selects = explode(',', $sql_selects);
		$sql_selects = array_map('trim', $sql_selects);
		
		foreach($sql_selects as $sql_select) {
		
			$sql_select = preg_split('#\s+AS\s+#is', $sql_select);
			$sql_select = array_map('trim', $sql_select);

			$expression = $this->unpack(array_shift($sql_select));
			
			$alias = array_pop($sql_select);
			
			if(!$alias) 
				$alias = $this->_adapter->unQuoteIdentifier(array_pop(explode('.', $expression)));

			if($explain_wildcards && preg_match('#^(.+)\.\s*\*$#', $expression, $match)) {
			
				$table_alias = $this->_adapter->unQuoteIdentifier($match[1]);
			
				$tables = $this->getTables();
			
				if(!array_key_exists($table_alias, $tables))
					throw new Yab_Exception('table_alias "'.$table_alias.'" not found in the SQL statement [aliases: ('.implode(', ', array_keys($tables)).')]');
			
				$table = $tables[$table_alias];
				
				if($table instanceof self) {
				
					$select += $table->getSelect();
				
				} else {
				
					$columns = $table->getColumns();
					
					foreach($columns as $column)
						$select[$this->_adapter->unQuoteIdentifier($column->getName())] = $this->_adapter->quoteIdentifier($table_alias).'.'.$this->_adapter->quoteIdentifier($column->getName());
					
				}

			} else {
			
				$select[$alias] = $expression;
				
			}
			
		}

		return $select;
	
	}
	
	public function isPacked($sql) {
	
		return preg_match('#'.preg_quote(self::LEFT_PACK_BOUNDARY, '#').'([0-9]+)'.preg_quote(self::RIGHT_PACK_BOUNDARY, '#').'#is', $sql);
	
	}
	
	public function getTables() {

		$tables = array();

		if(preg_match('#\s*SELECT\s+.+\s+FROM\s+(.+)\s*(ORDER\s+BY|LIMIT|GROUP|WHERE|INNER|LEFT|RIGHT|JOIN|FULL|NATURAL|CROSS|$)#Uis', $this->getPackedSql(), $match)) {
			
			$from = trim($match[1]);
			$unpack_from = $this->unpack($from);

			if($from != $unpack_from) {
			
				$alias = $unpack_from;
			
				if(preg_match('#(\s+[^\s]+)$#', $unpack_from, $match))
					$alias = $match[1];
				
				$tables[trim($this->_adapter->unQuoteIdentifier($alias))] = new self($this->_adapter, $unpack_from);
	
			} else {

				foreach(preg_split('#\s*,\s*#s', $from) as $table) {

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

					$tables[$alias] = $this->_adapter->getTable($name);

				}
			
			}

		}

		preg_match_all('#\s+JOIN\s+(.+)\s+ON\s+.+(ORDER\s+BY|LIMIT|GROUP|WHERE|INNER|LEFT|RIGHT|JOIN|FULL|NATURAL|CROSS|$)#Uis', $this->getPackedSql(), $match);

		foreach($match[1] as $join) {
		
			$join = trim($join);
			$unpack_join= $this->unpack($join);
			
			if($join != $unpack_join) {
	
				$alias = $unpack_join;
			
				if(preg_match('#(\s+[^\s]+)$#', $unpack_join, $match))
					$alias = $match[1];
				
				$tables[trim($this->_adapter->unQuoteIdentifier($alias))] = new self($this->_adapter, $unpack_join);

			} else {
			
				$table = trim($join);
			
				$table = preg_split('#\s+#s', $table);
			
				$name = array_shift($table);
				
				$name = explode('.', $name);
				$name = array_pop($name);
				
				$alias = array_shift($table);
				
				if(!$alias)
					$alias = $name;
			
				$name = $this->_adapter->unquoteIdentifier($name);
				$alias = $this->_adapter->unquoteIdentifier($alias);

				$tables[$alias] = $this->_adapter->getTable($name);
			
			}
		
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

		if(!is_array($order_by))
			$order_by = array($order_by => 'ASC');

		$tables = $this->getTables();
		$select = $this->getSelect(true);
		
		foreach($order_by as $field => $order) {
			
			unset($order_by[$field]);
			
			$field = trim($field);
			
			$order = strtoupper(trim($order));
			
			if(!in_array($order, array('ASC', 'DESC')))
				$order = 'ASC';
			
			$field = preg_replace('#^[a-zA-Z0-9\-_]+\.#', '', $field);

			$valid = false;
			
			foreach($select as $alias => $expression) {
			
				if($alias == $field || $expression == $field) {
						
					$order_by[$field] = $this->_adapter->quoteIdentifier($field).' '.$order;	
					$valid = true;
					break;
					
				}
			
			}
			
			if($valid)
				continue;
			
			foreach($tables as $alias => $table) {

				if($table instanceof self) {
				
					foreach($table->getSelect() as $ralias => $_expression) {
					
						if($ralias == $field || $_expression == $field) {
						
							$order_by[$field] = $this->_adapter->quoteIdentifier($alias).'.'.$this->_adapter->quoteIdentifier($field).' '.$order;	
							$valid = true;
							break;
							
						}
					
					}
	
				} else {
				
					foreach($table->getColumns() as $column) {
					
						if($column->getName() == $field) {
						
							$order_by[$field] = $this->_adapter->quoteIdentifier($alias).'.'.$this->_adapter->quoteIdentifier($field).' '.$order;	
							$valid = true;
							break;
							
						}
					
					}

				}
				
				if($valid)
					break;
			
			}

			if(!$valid)
				throw new Yab_Exception('"'.$field.'" is not a valid sort for this query');

		}
		
		$this->_sql = $this->unpack(preg_replace('#\s+ORDER\s+BY\s.*$#s', '', $this->getPackedSql())).' ORDER BY '.implode(', ', $order_by);

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
