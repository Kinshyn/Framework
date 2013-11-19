<?php
/**
 * Yab Framework
 *  
 * @category   Yab_Helper
 * @package    Yab_Helper_Pager
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Helper_Pager {

	const FILTER_PARAM_SEPARATOR = '~';

	private $_prefix = null;

	private $_statement = null;
	private $_session = null;
	private $_request = null;
	
	private $_multi_sort = true;
	private $_first_page = 1;
	private $_current_page = null;
	private $_last_page = null;
	private $_per_page = null;
	private $_default_per_page = 25;
	private $_max_per_page = null;
	private $_total = null;
	
	private $_sort_url_tag = 's';
	private $_order_url_tag = 'o';
	private $_page_url_tag = 'p';
	private $_per_page_url_tag = 'pp';
	private $_clear_url_tag = 'c';
	private $_export_url_tag = 'e';
	private $_filter_url_tag = 'f';

	private $_mixed_filters = array();
	
	public function __construct(Yab_Db_Statement $statement, $prefix = null) {

		$this->_statement = $statement;
		
		$this->_prefix = (string) $prefix;

		$this->_request = Yab_Loader::getInstance()->getRequest();

		if($this->_prefix) {
		
			$session = Yab_Loader::getInstance()->getSession();

			if(!$session->has($this->_prefix))
				$session->set($this->_prefix, array());

			$this->_session = $session->cast($this->_prefix);
		
		} else {
		
			$this->_session = new Yab_Object();
		
		}
		
		if($this->_getParam($this->_clear_url_tag))
			$this->_clear();
		
	}

	# usage 
	
	public function getStatement($sql_limit = true) {

		$statement = $this->_getFilteredStatement();

		$order_by = $this->_getSqlOrderBy();

		if(count($order_by))
			$statement->orderBy($order_by);

		$this->_export();

		if($sql_limit) {

			$current_page = $this->getCurrentPage();

			$per_page = $this->getPerPage();
	
			return $statement->sqlLimit(($current_page - 1) * $per_page, $per_page);		
			
		}
		
		$statement->execute();
		
		$this->_total = count($statement);

		return $statement->limit(($this->getCurrentPage() - 1) * $this->getPerPage(), $this->getPerPage());		

	}

	public function getFilters(array $table_aliases) {

		$form = null;
		
		foreach($table_aliases as $column_key => $column_value) {

			if(is_numeric($column_key)) {
			
				$column_key = $column_value;
				$column_value = null;
			
			}
			
			$element = $this->getFilter($column_key, $column_value, $form);
			
			$form = $element->getForm();

		}

		return $form;

	}
	
	public function mixFilter($column_key, array $column_keys) {
	
		$this->_mixed_filters[$this->_statement->getPrefixFromColumn($column_key).'.'.$this->_statement->getColumnFromColumn($column_key)] = $column_keys;

		return $this;
	
	}
	
	public function getFilter($column_key, $column_value = null, Yab_Form $form = null) {
	
		if($form === null) {
	
			$form = new Yab_Form();
			
			$form->set('method', 'get')->set('action', '');
		
		}
		
		$table_alias = $this->_statement->getPrefixFromColumn($column_key);
		$column_key = $this->_statement->getColumnFromColumn($column_key);
	
		$filter_name = $this->_prefix.$this->_filter_url_tag.$table_alias.self::FILTER_PARAM_SEPARATOR.$column_key;
	
		$attributes = array(
			'id' => $filter_name,
			'name' => $filter_name,
			'type' => 'text',
			'value' => $this->_session->has($filter_name) ? $this->_session->get($filter_name) : null,
		);
		
		if($column_value) {

			$statement = $this->_getFilteredStatement();
			$adapter = $statement->getAdapter();
			
			$statement->select(
				'DISTINCT '.
				$this->_statement->prefixColumn($column_key).', '.
				$this->_statement->prefixColumn($column_value))
			->orderBy(array($column_value => 'asc'))
			->setKey($this->_statement->getColumnFromColumn($column_key))
			->setValue($this->_statement->getColumnFromColumn($column_value));
		
			$attributes['type'] = 'select';
			$attributes['options'] = $statement;
				
		}
				
		$form->setElement($filter_name, $attributes);

		$element = $form->getElement($filter_name);
		
		$element->set('value', $this->_session->has($filter_name) ? $this->_session->get($filter_name) : null);
		
		return $element;

	}

	public function getPagination($wave = 5, $total = true, $clear = true) {

		$wave = (int) $wave;

		$html = '<ul class="pager">';
		
		if(1 < max(1, $this->getCurrentPage() - $wave))
			$html .= '<li><a href="'.$this->getPageUrl($this->getFirstPage()).'">'.$this->getFirstPage().'</a></li>';
		
		if(2 < max(1, $this->getCurrentPage() - $wave))
			$html .= '<li class="separator"><span>...</span></li>';

		for($i = max(1, $this->getCurrentPage() - $wave); $i < $this->getCurrentPage(); $i++) 
			$html .= '<li><a href="'.$this->getPageUrl($i).'">'.$i.'</a></li>';

		$html .= '<li class="page"><span>'.$this->getCurrentPage().'</span></li>';
		
		for($i = $this->getCurrentPage() + 1; $i <= min($this->getCurrentPage() + $wave, $this->getLastPage()); $i++)
			$html .= '<li><a href="'.$this->getPageUrl($i).'">'.$i.'</a></li>';

		if($this->getCurrentPage() + $wave < $this->getLastPage() - 1)
			$html .= '<li class="separator"><span>...</span></li>';
			
		if($this->getCurrentPage() + $wave < $this->getLastPage())
			$html .= '<li><a href="'.$this->getPageUrl($this->getLastPage()).'">'.$this->getLastPage().'</a></li>';
		
		if($total)
			$html .= '<li class="total"><span>Total :</span> <a href="'.$this->getPageUrl($this->getFirstPage(), $this->getTotal()).'">'.$this->getTotal().'</a></li>';
			
		if($clear)
			$html .= '<li class="clear"><a href="'.$this->getClearUrl().'">Clear</a></li>';

		$html .= '</ul>';
		
		return $html;

	}

	public function getSortLink($sort, $label = null) {
	
		if($label === null)
			$label = $sort;
	
		$filter_html = new Yab_Filter_Html();

		$order = $this->getSortOrder($sort);
		$number = $this->getSortNumber($sort);
		
		$arrow = '';
		
		if($this->_multi_sort && $order == 'asc') $arrow = $number.'&uarr;&nbsp;';
		elseif($this->_multi_sort && $order == 'desc') $arrow = $number.'&darr;&nbsp;';
		
		return $arrow.'<a href="'.$this->getSortUrl($sort, $order == 'asc' ? 'desc' : 'asc').'" class="'.$order.'">'.$filter_html->filter($label).'</a>';
	
	}

	public function getSortUrl($sort, $order = null) {

		$number = $this->getSortNumber($sort);

		return $this->getUrl(array($this->_sort_url_tag.$number => $sort, $this->_order_url_tag.$number => $order == 'desc' ? 'desc' : 'asc'));
	
	}
	
	public function getPageUrl($page, $per_page = null) {

		return $this->getUrl(array($this->_page_url_tag => $page, $this->_per_page_url_tag => $per_page ? $per_page : $this->getPerPage()));
	
	}
	
	public function getClearUrl() {

		return $this->getUrl(array($this->_clear_url_tag => 1));
	
	}
	
	public function getExportUrl($type = 'csv') {

		return $this->getUrl(array($this->_export_url_tag => $type));

	}

	public function getSorts() {

		$sorts = array();

		$i = 1;

		while($sort = $this->_getParam($this->_sort_url_tag.$i)) {
		
			$order = $this->_getParam($this->_order_url_tag.$i) == 'desc' ? 'desc' : 'asc';
			
			if($sort)
				$sorts[$sort] = $order;
				
			$i++;
		
		}

		return $sorts;

	}
	
	public function getUrl(array $params = array()) {

		foreach($params as $key => $value) {

			unset($params[$key]);
			
			$params[$this->_prefix.$key] = $value;
		
		}
		
		$params = array_merge($this->_request->getGet()->toArray(), $params);
		
		$params = array_merge($this->_session->toArray(), $params);

		return $this->_request->getBaseUrl().$this->_request->getUri($params);
	
	}

	# protected 

	protected function _getFilteredStatement() {
	
		$adapter = $this->_statement->getAdapter();
	
		$statement = clone $this->_statement;
		
		$statement->free();

		$filters = $this->_getFilters();

		foreach($filters as $table_alias => $columns) {

			foreach($columns as $column => $value) {
				
				$conditions = array();
				
				$mixed_filters = array($table_alias.'.'.$column => $table_alias.'.'.$column);
				
				if(array_key_exists($table_alias.'.'.$column, $this->_mixed_filters))
					$mixed_filters += $this->_mixed_filters[$table_alias.'.'.$column];
			
				foreach($mixed_filters as $mixed_filter_key) {
				
					$mixed_filter_alias = $this->_statement->getPrefixFromColumn($mixed_filter_key);
					$mixed_filter_column = $this->_statement->getColumnFromColumn($mixed_filter_key);

					if(is_array($value)) {

						if(count($value))
							$conditions[] = $adapter->quoteIdentifier($mixed_filter_alias).'.'.$adapter->quoteIdentifier($mixed_filter_column).' IN ('.implode(', ', array_map(array($adapter, 'quote'), $value)).')';

					} else {

						if($value)
							$conditions[] = $adapter->quoteIdentifier($mixed_filter_alias).'.'.$adapter->quoteIdentifier($mixed_filter_column).' LIKE '.$adapter->quote('%'.$value.'%');

					}

				}

				if(count($conditions)) 
					$statement->where(implode(' OR ', $conditions));
	
			}

		}

		return $statement;

	}

	protected function _getSqlOrderBy() {

		$sorts = $this->getSorts();

		$order_by = $this->_statement->getOrderBy();
		
		foreach($order_by as $column_name => $column_order) {

			$order = true;

			foreach($sorts as $key => $value) {

				if(preg_match('#'.preg_quote($key, '#').'#is', $column_name))
					$order &= false;

			}

			if($order)
				$sorts[$column_name] = $column_order;

		}
		
		return $sorts;

	}

	protected function _export() {

		if(!($export = $this->_getParam($this->_export_url_tag)))
			return $this;

		$statement = $this->_getFilteredStatement();

		$order_by = $this->_getSqlOrderBy();

		if(count($order_by))
			$statement->orderBy($order_by);

		$file_name = $this->_prefix ? $this->_prefix : $this->_export_url_tag;
		$file_name .= '_'.date('Y-m-d-H-i-s');

		if($export == 'csv') {

			$csv = new Yab_File_Csv($file_name.'.csv');

			$csv->setDatas($statement);

			Yab_Loader::getInstance()->getResponse()->download($csv);

		} elseif($export == 'xml') {

			$xml = new Yab_File_Xml($file_name.'.xml');

			$xml->setDatas($statement);

			Yab_Loader::getInstance()->getResponse()->download($xml);

		}

		return $this;

	}
	
	protected function _clear() {
		
		$get = $this->_request->getGet();
		
		foreach($this->_session as $key => $value) {
		
			if($get->has($key))
				$get->rem($key);
		
		}
		
		$this->_session->clear();
		
		$get = $this->_prefix ? $get->toArray() : array();
		
		foreach($get as $key => $value) {
		
			if(strpos($key, $this->_prefix) === 0)
				unset($get[$key]);
		
		}

		Yab_Loader::getInstance()->redirect($this->_request->getBaseUrl().$this->_request->getUri($get));
		
	}
	
	protected function _getParam($key, $default = null) {

		if($this->_request->getRequest()->has($this->_prefix.$key)) {
		
			if(in_array($key, array($this->_export_url_tag, $this->_clear_url_tag)))
				return $this->_request->getRequest()->get($this->_prefix.$key);
		
			$this->_session->set($this->_prefix.$key, $this->_request->getRequest()->get($this->_prefix.$key));
			
		}
		
		if($this->_session->has($this->_prefix.$key))
			return $this->_session->get($this->_prefix.$key);

		return $default;
	
	}
	
	protected function _getFilters() {

		$filters = array();

		$params = $this->_request->getPost()->toArray() + $this->_request->getGet()->toArray() + $this->_session->toArray();
		
		foreach($params as $key => $value) {
		
			if(!preg_match('#'.preg_quote($this->_prefix.$this->_filter_url_tag, '#').'([^'.preg_quote(self::FILTER_PARAM_SEPARATOR, '#').']+)'.preg_quote(self::FILTER_PARAM_SEPARATOR, '#').'([^'.preg_quote(self::FILTER_PARAM_SEPARATOR, '#').']+)$#i', $key, $match))
				continue;

			if(!array_key_exists($match[1], $filters) || !is_array($filters[$match[1]]))
				$filters[$match[1]] = array();
			
			$filters[$match[1]][$match[2]] = $this->_getParam($this->_filter_url_tag.$match[1].self::FILTER_PARAM_SEPARATOR.$match[2]);

		}
		
		return $filters;
	
	}
	
	# Getters

	public function getSortOrder($asked_sort, $default = null) {

		$sorts = $this->getSorts();

		return array_key_exists($asked_sort, $sorts) ? $sorts[$asked_sort] : $default;

	}

	public function getSortNumber($asked_sort) {

		$sorts = $this->getSorts();

		$i = 1;

		foreach($sorts as $sort => $order) {
			
			if($sort == $asked_sort)
				return $i;
				
			$i++;
		
		}
			
		return $i;

	}

	public function getPerPage() {

		if($this->_per_page !== null)
			return $this->_per_page;

		$this->setPerPage($this->_getParam($this->_per_page_url_tag));

		return $this->_per_page;

	}

	public function getCurrentPage() {

		if($this->_current_page !== null)
			return $this->_current_page;

		$this->setCurrentPage($this->_getParam($this->_page_url_tag));
		
		return $this->_current_page;

	}

	public function getFirstPage() {

		return $this->_first_page;

	}

	public function getTotal() {

		return is_numeric($this->_total) ? $this->_total : count($this->_statement);

	}

	public function getLastPage() {

		if($this->_last_page !== null)
			return $this->_last_page;

		$this->_last_page = max(1, ceil($this->getTotal() / $this->getPerPage()));

		return $this->_last_page;

	}

	public function getClass(Yab_Db_Statement $statement, $alt = 2, $alt_class = 'alt') {
	
		$classes = array();

		array_push($classes, $alt_class.ceil($statement->offset() % intval($alt)));

		if($statement->isFirst())
			array_push($classes, 'first');

		if($statement->hasNext())
			array_push($classes, 'next');

		if($statement->isLast())
			array_push($classes, 'last');
	
		if(!count($classes))
			return '';
			
		return ' class="'.implode(' ', $classes).'"';

	}

	# Setters

	public function setFilterUrlTag($tag) {
	
		$this->_filter_url_tag = (string) $tag;
		
		return $this;
	
	}
	
	public function setPageUrlTag($tag) {
	
		$this->_page_url_tag = (string) $tag;
		
		return $this;
	
	}

	public function setPerPageUrlTag($tag) {
	
		$this->_per_page_url_tag = (string) $tag;
		
		return $this;
	
	}

	public function setClearUrlTag($tag) {
	
		$this->_clear_url_tag = (string) $tag;
		
		return $this;
	
	}

	public function setExportUrlTag($tag) {
	
		$this->_export_url_tag = (string) $tag;
		
		return $this;
	
	}
	
	public function setSortUrlTag($tag) {
	
		$this->_sort_url_tag = (string) $tag;
		
		return $this;
	
	}	
	
	public function setOrderUrlTag($tag) {
	
		$this->_order_url_tag = (string) $tag;
		
		return $this;
	
	}

	public function setDefaultPerPage($per_page) {
		
		$this->_default_per_page = (int) $per_page;

		return $this;

	}

	public function setMaxPerPage($max_per_page) {
		
		$this->_max_per_page = (int) $max_per_page;

		return $this;

	}
	
	public function setMultiSort($multi_sort) {
		
		$this->_multi_sort = (bool) $multi_sort;

		return $this;

	}

	public function setCurrentPage($current_page) {

		$this->_current_page = (int) $current_page;

		$this->_current_page = max(1, intval($this->_current_page));

		$this->_current_page = min($this->getLastPage(), $this->_current_page);

		return $this;

	}

	public function setPerPage($per_page) {

		$this->_per_page = (int) $per_page;

		if(!$this->_per_page)
			$this->_per_page = $this->_default_per_page;

		$this->_per_page = max(1, intval($this->_per_page));

		if($this->_max_per_page)
			$this->_per_page = min($this->_max_per_page, $this->_per_page);
		
		return $this;

	}

}

// Do not clause PHP tags unless it is really necessary
