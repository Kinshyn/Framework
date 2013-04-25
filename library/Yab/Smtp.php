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
	private $_domain_key = array();

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

		return is_numeric($headers_position) ? substr($data, 0, $headers_position) : $data;

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

		$domain_key = $this->getDomainKey($data);

		if($domain_key)
			$data = $domain_key.self::CRLF.$data;

		$dkim = $this->getDkim($data);

		if($dkim)
			$data = $dkim.self::CRLF.$data;

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

	public function setDkim($domain, $selector, $private_key, $passphrase = '', $body_canonicalization = 'relaxed', array $signed_headers = array('mime-version', 'from', 'to', 'subject', 'reply-to')) {

		if(!function_exists('openssl_pkey_get_private'))
			throw new Yab_Exception('Can not use DKIM if the PHP openssl extension is not active');
	
		$this->_dkim['domain'] = (string) $domain;
		$this->_dkim['selector'] = (string) $selector;
		$this->_dkim['private_key'] = openssl_pkey_get_private($private_key, $passphrase);
		$this->_dkim['body_canonicalization'] = (string) $body_canonicalization;
		$this->_dkim['signed_headers'] = array_map('strtolower', $signed_headers);
		
		return $this;

	}

	public function getDkim($data) {

		if(!function_exists('openssl_sign'))
			throw new Yab_Exception('Can not use DKIM if the PHP openssl extension is not active');

		if(!count($this->_dkim))
			return '';

		if(!in_array($this->_dkim['body_canonicalization'], array('relaxed', 'simple')))
			throw new Yab_Exception('Can not use DKIM with body_canonicalization "'.$this->_dkim['body_canonicalization'].'"');

		$body = $this->extractBody($data);
		$headers = $this->extractHeaders($data);
		
		$dkim_headers = $this->splitHeaders($data);

		foreach($dkim_headers as $key => $value)
			if(!in_array(strtolower($key), $this->_dkim['signed_headers']))
				unset($dkim_headers[$key]);
		
		if(in_array($this->_dkim['body_canonicalization'], array('relaxed'))) {
		
			$lines = explode(self::CRLF, $body);
			
			foreach($lines as $key => $value)
				$lines[$key] = preg_replace('#\s+#', ' ', rtrim($value));

			$body = implode(self::CRLF, $lines);

		}
		
		if(in_array($this->_dkim['body_canonicalization'], array('relaxed', 'simple'))) {

			while(substr($body, strlen($body) - strlen(self::CRLF.self::CRLF), strlen(self::CRLF.self::CRLF)) == self::CRLF.self::CRLF)
				$body = substr($body, 0, strlen($body) - strlen(self::CRLF));

			if(substr($body, strlen($body) - strlen(self::CRLF), strlen(self::CRLF)) != self::CRLF)
				$body .= self::CRLF;
		
		}

		$dkim = 'DKIM-Signature:'.self::CRLF;
		$dkim .= "\t".'v=1;'.self::CRLF;
		$dkim .= "\t".'a=rsa-sha1;'.self::CRLF;
		$dkim .= "\t".'q=dns/txt;'.self::CRLF;
		$dkim .= "\t".'s='.$this->_dkim['selector'].';'.self::CRLF;
		$dkim .= "\t".'c=relaxed/'.$this->_dkim['body_canonicalization'].';'.self::CRLF;
		$dkim .= "\t".'l='.strlen($body).';'.self::CRLF;
		$dkim .= "\t".'t='.time().';'.self::CRLF;
		$dkim .= "\t".'x='.(time() +  10200).';'.self::CRLF;
		$dkim .= "\t".'h='.implode(':', array_map('strtolower', array_map('trim', array_keys($dkim_headers)))).';'.self::CRLF;
		$dkim .= "\t".'d='.ltrim($this->_dkim['domain'], '@').';'.self::CRLF;
		$dkim .= "\t".'bh='.rtrim(chunk_split(base64_encode(pack("H*", sha1($body))), 64, self::CRLF."\t")).';'.self::CRLF;
		$dkim .= "\t".'b=';

		$relaxed_headers = '';
		
		foreach($dkim_headers as $key => $value) 
			$relaxed_headers .= trim(strtolower($key)).':'.trim(preg_replace("#\s+#", " ", $value)).self::CRLF;

		foreach($this->splitHeaders($dkim) as $key => $value)
			$relaxed_headers .= trim(strtolower($key)).':'.trim(preg_replace("#\s+#", " ", $value));

		if(!openssl_sign($relaxed_headers, $signature, $this->_dkim['private_key']))
			return '';

		return $dkim.rtrim(chunk_split(base64_encode($signature), 64, self::CRLF."\t"));

	}

	public function setDomainKey($domain, $selector, $private_key, $passphrase = '', $canonicalization = 'nofws', array $signed_headers = array('mime-version', 'from', 'to', 'subject', 'reply-to')) {

		if(!function_exists('openssl_pkey_get_private'))
			throw new Yab_Exception('Can not use DKIM if the PHP openssl extension is not active');

		$this->_domain_key['domain'] = (string) $domain;
		$this->_domain_key['selector'] = (string) $selector;
		$this->_domain_key['private_key'] = openssl_pkey_get_private($private_key, $passphrase);
		$this->_domain_key['canonicalization'] = (string) $canonicalization;
		$this->_domain_key['signed_headers'] = array_map('strtolower', $signed_headers);

		return $this;

	}

	public function getDomainKey($data) {

		if(!function_exists('openssl_sign'))
			throw new Yab_Exception('Can not use DKIM if the PHP openssl extension is not active');

		if(!count($this->_domain_key))
			return '';

		if(!in_array($this->_domain_key['canonicalization'], array('nofws', 'simple')))
			throw new Yab_Exception('Can not use DKIM with canonicalization "'.$this->_domain_key['canonicalization'].'"');

		$body = $this->extractBody($data);
		$headers = $this->extractHeaders($data, true);
		
		$dk_headers = $this->splitHeaders($data);

		foreach($dk_headers as $key => $value)
			if(!in_array(strtolower($key), $this->_domain_key['signed_headers']))
				unset($dk_headers[$key]);
		
		$domain_key = 'DomainKey-Signature:'.self::CRLF;
		$domain_key .= "\t".'a=rsa-sha1;'.self::CRLF;
		$domain_key .= "\t".'c='.$this->_domain_key['canonicalization'].';'.self::CRLF;
		$domain_key .= "\t".'d='.ltrim($this->_domain_key['domain'], '@').';'.self::CRLF;
		$domain_key .= "\t".'s='.$this->_domain_key['selector'].';'.self::CRLF;
		$domain_key .= "\t".'h='.implode(':', array_map('strtolower', array_map('trim', array_keys($dk_headers)))).';'.self::CRLF;
		$domain_key .= "\t".'b=';
		
		if(in_array($this->_domain_key['canonicalization'], array('nofws'))) {

			$data = '';
		
			foreach($dk_headers as $key => $value)
				$data .= preg_replace("/\s/", '', preg_replace("/\r\n\s+/", " ", $key.':'.$value)).self::CRLF;

			$lines = explode(self::CRLF, $body);
			
			foreach($lines as $key => $line)
				$lines[$key] = preg_replace("/\s/", '', $line);

			$body = rtrim(implode(self::CRLF, $lines)).self::CRLF;
			
			$data .= self::CRLF.$body;
			
		} elseif(in_array($this->_domain_key['canonicalization'], array('simple'))) {

			$data = '';

			foreach($dk_headers as $key => $value) 
				$data .= $key.':'.$value.self::CRLF;

			$data .= self::CRLF.$body.self::CRLF;
			
			while(substr($data, strlen($data) - strlen(self::CRLF.self::CRLF), strlen(self::CRLF.self::CRLF)) == self::CRLF.self::CRLF)
				$data = substr($data, 0, strlen($data) - strlen(self::CRLF));			

		}
		
		if(!openssl_sign($data, $signature, $this->_domain_key['private_key'], OPENSSL_ALGO_SHA1))
			return '';

		return $domain_key.rtrim(chunk_split(base64_encode($signature), 64, self::CRLF."\t"));

	}
	
}

// Do not clause PHP tags unless it is really necessary