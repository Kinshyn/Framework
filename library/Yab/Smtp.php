<?php
/**
* Yab Framework
*
* @category   Yab
* @package    Yab_Smtp
* @author     Yann BELLUZZI
* @copyright  (c) 2010 YBellu
* @license    http://www.ybellu.com/yab-framework/license.html
* @link       http://www.ybellu.com/yab-framework 
*/

class Yab_Smtp extends Yab_Socket {

	const CRLF = "\r\n";

	private $_login = null;
	private $_password = null;

	private $_dkim = array();

	public function auth($login, $password) {

		$this->_login = (string) $login;
		$this->_password = (string) $password;
		
		return $this;

	}
	
	public function decodeHeader($header) {
				
		$header = preg_replace('#(\r\n|\r|\n)\s+#i', '', $header);
	
		preg_match_all("#=\?(utf-8|iso8859-15?)\?(b|q)\?([^\?]+)\?=#i", $header, $parts);

		foreach($parts[0] as $i => $part) {
		
			$charset = strtolower($parts[1][$i]);
			$encoding = strtolower($parts[2][$i]);
			
			$string = $parts[3][$i];
		
			switch($encoding) {
			
				case 'b' :
				
					$string = base64_decode($string);
					
				break;
			
				case 'q' :
				
					$string = preg_replace('#_#i', ' ', $string);
					
					preg_match_all('#=([a-z0-9][a-z0-9])#i', $string, $chars);

					foreach($chars[0] as $j => $char) 
						$string = str_replace($char, chr(hexdec($chars[1][$j])), $string);
		
				break;
				
				default: break;
			
			}
		
			$header = str_replace($part, $string, $header);
		
		}
		
		return trim($header);

	}
	
	public function extractAddress($header) {
	
		return preg_replace('#^.*?('.Yab_Validator_Email::REGEXP.').*?$#', '$1', $header);
	
	}
	
	public function extractHeader($data, $header, $with_name = true) {
	
		$data = $this->crlf($data);
	
		$headers = $this->splitHeaders($data);

		foreach($headers as $key => $value) 
			if(preg_match('#^'.preg_quote($header, '#').'(\s|:|$)#is', $key))
				return trim($with_name ? $key.':'.$value : $value);

		return null;

	}
	
	public function splitHeaders($data) {
	
		$data = $this->crlf($data);

		$data_headers = $this->extractHeaders($data);
		$data_headers = explode(self::CRLF, $data_headers);

		$headers = array();
		$current_header = null;
		
		foreach($data_headers as $header) {

			if(preg_match('#^([a-zA-Z\-]+\s*):(.*)$#i', $header, $match)) {

				$current_header = $match[1];
		
				if(!array_key_exists($current_header, $headers))
					$headers[$current_header] = '';
					
				$headers[$current_header] .= $match[2];

			}
			
			if($current_header && preg_match('/^\s+[^\s]+/', $header))
				$headers[$current_header] .= self::CRLF.$header;
	
		}

		return $headers;

	}
	
	public function extractHeaders($data) {
	
		$data = $this->crlf($data);
	
		$headers_position = strpos($data, self::CRLF.self::CRLF);

		if(!is_numeric($headers_position)) 
			return array();

		return substr($data, 0, $headers_position);

	}
	
	public function extractBody($data) {
	
		$data = $this->crlf($data);
	
		$headers_position = strpos($data, self::CRLF.self::CRLF);

		if(!is_numeric($headers_position)) 
			return '';

		return substr($data, $headers_position + strlen(self::CRLF.self::CRLF));
		
	}
	
	public function crlf($data) {
	
		$data = (string) $data;

		$data = str_replace("\r\n", "\n", $data);
		$data = str_replace("\r", "\n", $data);
		$data = str_replace("\n", "\r\n", $data);
		
		return $data;
	
	}
	
	public function pack($data) {
	
		$data = $this->crlf($data);

		$data = str_replace(self::CRLF.self::CRLF, '', $data);
		
		return trim($data);
	
	}

	protected function _onConnect() {

		$this->_command('EHLO '.gethostbyaddr($this->getAddress()));

		if($this->_login) 
			$this->_command('AUTH LOGIN')->_command(base64_encode($this->_login))->_command(base64_encode($this->_password));

	}
	
	public function send($data) {

		$data = $this->crlf($data);

		$from = $this->extractHeader($data, 'return-path', false);
		$from = $this->extractAddress($from);
		$from = preg_replace('#=3D#i', '=', $from);

		$to = $this->extractHeader($data, 'to', false);
		$to = $this->extractAddress($to);
		$to = preg_replace('#=3D#i', '=', $to);

		$this->_command('MAIL FROM:'.$from);
		$this->_command('RCPT TO:'.$to);
		$this->_command('DATA');

		$data = $this->signDkim($data);

		$data = str_replace("\n.", "\n..", $data);
		$data = str_replace("\r.", "\r..", $data);

		if(substr($data, 0, 1) == '.')
			$data = '.'.$data;
		
		$data = $data.self::CRLF.'.';

		$this->_command($data);

		return $this;

	}

	private function _command($command) {

		$command .= self::CRLF;
			
		$this->write($command);

		$response = $this->_readResponse();

		if($response != '' && !in_array($response[0], array('2', '3'))) {

			$this->write('RSET'.self::CRLF);

			$response = $this->_readResponse();
			
			return false;

		}

		return true;

	}

	private function _readResponse() {

		$data = "";

		while($str = $this->read()) {

			$data .= $str;

			if(substr($str, 3, 1) == " ")
				break;

		}

		return $data;

	}

	public function setDkim($domain, $selector, $private_key) {

		$this->_dkim['domain'] = $domain;
		$this->_dkim['selector'] = $selector;
		$this->_dkim['private_key'] = $private_key;

		return $this;

	}

	public function signDkim($data) {

		if(!count($this->_dkim))
			return $data;

		$body = $this->extractBody($data);
		$headers = $this->extractHeaders($data);
		
		$dkim_headers = $this->splitHeaders($data);

		if(array_key_exists('return-path', $dkim_headers))
			unset($dkim_headers['return-path']);
		
		if(array_key_exists('subject', $dkim_headers))
			unset($dkim_headers['subject']);
			
		foreach($dkim_headers as $key => $value)
			if(preg_match('#^X\-#i', $key))
				unset($dkim_headers[$key]);

		while(substr($body, strlen($body) - strlen(self::CRLF.self::CRLF), strlen(self::CRLF.self::CRLF)) == self::CRLF.self::CRLF)
			$body = substr($body, 0, strlen($body) - strlen(self::CRLF));

		$dkim = "v=1; a=rsa-sha1; q=dns/txt; s=".$this->_dkim['selector']."; c=relaxed/simple;".self::CRLF.
		"\tl=".strlen($body)."; t=".time()."; x=".(time() +  10200)."; h=".implode(':', array_map('strtolower', array_map('trim', array_keys($dkim_headers)))).";".self::CRLF.
		"\td=".ltrim($this->_dkim['domain'], '@')."; bh=".base64_encode(pack("H*", sha1($body))).";".self::CRLF.
		"\tb=";

		$relaxed_headers = '';
		
		foreach($dkim_headers as $key => $value) 
			$relaxed_headers .= trim(strtolower($key)).':'.trim(preg_replace("#\s+#", " ", $value)).self::CRLF;

		$relaxed_headers .= 'dkim-signature:'.trim(preg_replace("#\s+#", " ", $dkim));

		openssl_sign($relaxed_headers, $signature, $this->_dkim['private_key']);

		return $headers.self::CRLF.'DKIM-Signature: '.$dkim.base64_encode($signature).self::CRLF.self::CRLF.$body;

	}

	public function setDomainKey($domain, $selector, $private_key) {

		$this->_dk['domain'] = $domain;
		$this->_dk['selector'] = $selector;
		$this->_dk['private_key'] = $private_key;

		return $this;

	}

	public function signDomainKey($data) {

		if(!count($this->_dk))
			return $data;

		$body = $this->extractBody($data);
		$headers = $this->extractHeaders($data);

		$dk = "a=rsa-sha1; s=".$this->_dk['selector']."; d=".ltrim($this->_dk['domain'], '@')."; q=dns;".self::CRLF."\tb=";

		openssl_sign($data, $signature, $this->_dk['private_key']);

		return $headers.self::CRLF.'DomainKey-Signature: '.$dk.base64_encode($signature).self::CRLF.self::CRLF.$body;

	}

}

// Do not clause PHP tags unless it is really necessary