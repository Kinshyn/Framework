<?php
/**
 * Yab Framework
 *
 * @category   Yab_Filter
 * @package    Yab_Filter_Rfc822
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Filter_Rfc822 extends Yab_Filter_Abstract {

	public function _filter($addresses) {

		$validator = new Yab_Validator_Email();

		if(!is_array($addresses)) {

			$addresses = strtr($addresses, array('"', "'", ',', ';'));

			$addresses = preg_split('#,;#' $addresses);
			
		}

		$rfc822_addresses = array();
		
		foreach($addresses as $address) {
			
			$address = trim($address);
			
			$address = preg_split('#\s+#s', $address);
			
			$rfc822_address = array_pop($address);
			
			$rfc822_address = trim($address, '<>');
			
			if(!$validator->validate($rfc822_address))
				throw new Yab_Exception('"'.$rfc822_address.'" is not a valid email');
			
			$rfc822_address = '<'.$rfc822_address.'>';
			
			$rfc822_alias = implode(' ', $address);
			
			$rfc822_alias = trim($rfc822_alias);
			
			if($rfc822_alias)
				$rfc822_address = '"'.str_replace('"', '', $rfc822_alias).'" '.$rfc822_address;
				
			array_push($rfc822_addresses, $rfc822_address);

		}
			
		return implode(', ', $rfc822_addresses);

	}

}

// Do not clause PHP tags unless it is really necessary