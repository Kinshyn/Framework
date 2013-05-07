<?php
/**
 * Yab Framework
 *
 * @category   Yab_Filter
 * @package    Yab_Filter_Ipv6
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Filter_Ipv6 extends Yab_Filter_Abstract {

	public function _filter($value) {

		$semi_colon = false;
		$in_semi_colon = false;
	
		$parts = preg_split('#:#', $value);

		foreach($parts as $key => $value) {
		
			while($value[0] == '0') {
			
				if(strlen($value) == 1 && $semi_colon && !$in_semi_colon)
					break;
			
				$value = substr($value, 1);
				
			}
			
			if(strlen($value) == 0) {
			
				$semi_colon = true;
				$in_semi_colon = true;

			} else {
			
				$in_semi_colon = false;
			
			}
			
			$parts[$key] = $value;
	
		}

		$ipv6 = implode(':', $parts);
		
		$ipv6 = preg_replace('#::+#', '::', $ipv6);

		return strtolower($ipv6);

	}

}

// Do not clause PHP tags unless it is really necessary