<?php
/**
 * Yab Framework
 *
 * @category   Yab_Filter
 * @package    Yab_Filter_Factory
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Filter_Factory extends Yab_Filter_Abstract {

	final public function _filter($value) {

		$filters = $this->has('filters') ? $this->get('filters') : array();

		$filters = Yab_Loader::getInstance('Yab_Filter_Array')->filter($filters);

		foreach($filters as $filter => $options) {

			$filter_name = is_numeric($filter) ? $options : $filter;
			$filter_options = is_numeric($filter) ? array() : $options;

			try {

				$filter = Yab_Loader::getInstance($filter_name, array(), 'Yab_Filter_Abstract')->feed(array_merge($filter_options, $this->_attributes));

			} catch(Yab_Exception $e) {

				$filter = Yab_Loader::getInstance('Yab_Filter_'.$filter_name, array(), 'Yab_Filter_Abstract')->feed(array_merge($filter_options, $this->_attributes));

			}
			
			$value = $filter->filter($value);
			
			$filter->clear();

		}

		return $value;
	
	}

}

// Do not clause PHP tags unless it is really necessary
