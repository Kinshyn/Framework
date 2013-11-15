<?php
/**
 * Yab Framework
 *
 * @category   Yab_Filter
 * @package    Yab_Filter_Amount
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Filter_Amount extends Yab_Filter_Abstract {

	public function _filter($value) {
	
		if(!$this->has('unit'))
			$this->set('unit', '€');
	
		return number_format($value, 2).' '.$this->get('unit');
	
	}

}

// Do not clause PHP tags unless it is really necessary
