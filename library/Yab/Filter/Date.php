<?php
/**
 * Yab Framework
 *
 * @category   Yab_Filter
 * @package    Yab_Filter_Date
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Filter_Date extends Yab_Filter_Abstract {

	public function _filter($value) {

		setlocale(LC_TIME, array(
			'fr_FR.utf8',
			'fr_FR.UTF-8',
			'fr_FR.UTF-8',
			'fr_FR.UTF8',
			'fr_FR',
		), 'fra');

		$format = $this->has('format') ? $this->get('format') : '%A %d %B %Y - %Hh%M';

		return strftime($format, is_numeric($value) ? $value : strtotime($value));

	}

}

// Do not clause PHP tags unless it is really necessary