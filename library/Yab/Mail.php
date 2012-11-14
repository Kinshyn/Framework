<?php
/**
* Yab Framework
*
* @category   Yab
* @package    Yab_Mail
* @author     Yann BELLUZZI
* @copyright  (c) 2010 YBellu
* @license    http://www.ybellu.com/yab-framework/license.html
* @link       http://www.ybellu.com/yab-framework 
*/

class Yab_Mail {

	const CRLF = "\n";

	private $_boundary = null;

	private $_headers = array();
	private $_parts = array();

	private $_charset = 'utf-8';
	private $_encoding = 'quoted-printable';

	private $_addresses_headers = array('return-path', 'sender', 'x-sender', 'from', 'to', 'cc', 'bcc', 'reply-to');
	
	public function __construct() {

		$this->_boundary = '-----='.md5(uniqid(mt_rand()));

	}
	
	/*
	 * Proxy methods
	 */

	public function setCharset($charset) {

		$this->_charset = (string) $charset;

		return $this;

	}

	public function setEncoding($encoding) {

		$this->_encoding = (string) $encoding;

		return $this;

	}

	public function setFrom($from) {

		return $this->setHeader('From', $from);

	}

	public function setTo($to) {
	
		return $this->setHeader('To', $to);

	}
	
	public function setCc($cc) {
	
		return $this->setHeader('cc', $cc);

	}
	
	public function setBcc($bcc) {
	
		return $this->setHeader('bcc', $bcc);

	}

	public function setSubject($subject) {

		return $this->setHeader('Subject', $subject);

	}

	public function setText($text) {

		return $this->setPart(0, $text, 'text/plain');

	}

	public function getText() {

		$text = $this->getPart(0);
		
		if($text === null)
			return null;
			
		return (string) $text['content'];

	}

	public function setHtml($html) {

		return $this->setPart(1, $html, 'text/html');

	}

	public function getHtml() {

		$html = $this->getPart(1);
		
		if($html === null)
			return null;
			
		return (string) $html['content'];

	}

	public function attach($file, $file_content = null) {

		if(!($file instanceof Yab_File))
			$file = new Yab_File($file);
		
		if($file_content === null)
			$file_content = (string) $file->read();
		
		return $this->setPart(max($this->_maxPartId() + 1, 2), (string) $file_content, $file->getMimeType(), $file->getName());

	}

	public function send() {

		$to = $this->getHeader('To');
		$subject = $this->getHeader('Subject');

		$this->remHeader('To')->remHeader('Subject');

		mail($to, $this->encodeHeader($subject), $this->_parts(), $this->_headers());

		return $this->setHeader('To', $to)->setHeader('Subject', $subject);

	}
	
	/*
	 * internal methods
	 */

	public function isMultipart() {

		return 1 < count($this->_parts);

	}

	public function isAlternative() {

		return array_key_exists(0, $this->_parts) && array_key_exists(1, $this->_parts);

	}

	public function isMixed() {

		return array_key_exists(2, $this->_parts);

	}

	public function setHeader($name, $header) {

		$this->_headers[$name] = $header;

		return $this;

	}

	public function remHeader($name) {

		if(array_key_exists($name, $this->_headers))
			unset($this->_headers[$name]);

		return $this;

	}

	public function getHeader($name, $encoded = false) {

		if(!array_key_exists($name, $this->_headers))
			return null;
			
		if(in_array($name, $this->_addresses_headers))
			return $this->formatRfc822($this->_headers[$name], $encoded);
	
		return $encoded ? $this->encodeHeader($this->_headers[$name]) : $this->_headers[$name];

	}

	public function setPart($id, $content, $mime_type, $filename = null) {

		$this->_parts[$id] = array(
			'headers' => array(
				'Content-Disposition' => $filename === null ? 'inline' : 'attachment; filename="'.$filename.'"',
				'Content-Type' => $mime_type.'; charset="'.$this->_charset.'"',
				'Content-Transfer-Encoding' => $filename ? 'base64' : $this->_encoding,
				'Content-ID' => $filename ? '<'.$filename.'>' : null,
			),
			'content' => (string) $content,
		);
	
		return $this;

	}

	public function getPart($id) {

		return array_key_exists($id, $this->_parts) ? $this->_parts[$id] : null;

	}

	public function remPart($id) {

		if(array_key_exists($id, $this->_parts))
			unset($this->_parts[$id]);

		return $this;

	}

	public function __toString() {

		return $this->_headers().self::CRLF.self::CRLF.$this->_parts();

	}

	private function _headers() {

		if(!$this->getHeader('MIME-Version'))
			$this->setHeader('MIME-Version', '1.0');

		if(!$this->getHeader('Return-Path'))
			$this->setHeader('Return-Path', $this->getHeader('From'));

		if(!$this->getHeader('Date'))
			$this->setHeader('Date', date('D, j M Y H:i:s O'));

		$headers = '';

		foreach($this->_headers as $name => $header) {
		
			if(in_array(strtolower($name), array_map('strtolower', $this->_addresses_headers))) {
		
				$headers .= $name.': '.$this->formatRfc822($header, true).self::CRLF;
			
			} else {
		
				$headers .= $name.': '.$this->encodeHeader($header).self::CRLF;
			
			}
			
		}

		if($this->isMultipart()) {

			if($this->isMixed()) {

				$headers .= 'Content-Type: multipart/mixed; boundary="'.$this->_boundary.'"'.self::CRLF;

			} else {

				$headers .= 'Content-Type: multipart/alternative; boundary="'.$this->_boundary.'"'.self::CRLF;

			}

		} else {

			$headers .= $this->_partHeaders($this->_maxPartId()).self::CRLF;

		}

		return trim($headers);

	}  

	private function _maxPartId() {

		$max_part_id = -1;

		foreach(array_keys($this->_parts) as $id)
			$max_part_id = max($max_part_id, $id);

		return $max_part_id;

	}

	private function _partHeaders($id) {

		if(!$part = $this->getPart($id))
			return '';

		$string = '';

		foreach($part['headers'] as $key => $value)
			$string .= $value ? $key.': '.$value.self::CRLF : '';

		return trim($string);

	}

	private function _partContent($id) {
		
		if(!$part = $this->getPart($id))
			return '';

		return $this->encodePart($part['content'], $this->_charset, $part['headers']['Content-Transfer-Encoding']);

	}

	private function _part($id, $boundary) {

		$headers = $this->_partHeaders($id);
		$content = $this->_partContent($id);
		
		if(!$headers && !$content)
			return '';
	
		return '--'.$boundary.self::CRLF.$headers.self::CRLF.self::CRLF.$content;

	}

	private function _parts() {

		$max_part_id = $this->_maxPartId();
	
		if(!$this->isMultipart())
			return $this->_partContent($max_part_id);  

		$parts = 'This is a message with multiple parts in MIME format.'.self::CRLF.self::CRLF;

		if($this->isMixed() && $this->isAlternative()) {

			$parts .= '--'.$this->_boundary.self::CRLF; 

			$alternative_boundary = '-----='.md5($this->_boundary);

			$parts .= 'Content-Type: multipart/alternative; boundary="'.$alternative_boundary.'"'.self::CRLF.self::CRLF;

			for($i = 0; $i < 2; $i++) 	        
				$parts .= $this->_part($i, $alternative_boundary).self::CRLF; 

			$parts .= '--'.$alternative_boundary.'--'.self::CRLF;

			for($i = 2; $i <= $max_part_id; $i++) 	      
				$parts .= $this->_part($i, $this->_boundary).self::CRLF; 

		} elseif($this->isMixed()) {

			for($i = 0; $i <= $max_part_id; $i++) 	          
				$parts .= $this->_part($i, $this->_boundary).self::CRLF;  

		} elseif($this->isAlternative()) {

			for($i = 0; $i <= $max_part_id; $i++) 	          
				$parts .= $this->_part($i, $this->_boundary).self::CRLF;  

		}

		return $parts.'--'.$this->_boundary.'--';

	}  
	
	public function splitAddresses($string, $split_chars = array(',', ';'), $quote_chars = array('"'), $escape_chars = array('\\')) {
	
		if(is_array($string))
			return $string;
			
		$length = strlen($string);

		$part = '';

		$parts = array();
		
		$escaped_char = false;
		$quoted_string = false;
		
		for($i = 0; $i < $length; $i++) {
		
			$char = $string[$i];
		
			if(in_array($char, $split_chars)) {

				if(!$quoted_string) {
				
					array_push($parts, $part);
					
					$part = '';
					
					$char = null; 
					
				}

			} elseif(in_array($char, $quote_chars)) {
		
				if(!$escaped_char) 
					$quoted_string = !$quoted_string;

			}
				
			$escaped_char = false; 

			if(in_array($char, $escape_chars) && !$escaped_char) 
				$escaped_char = true; 
	
			$part .= $char;

		}
		
		array_push($parts, $part);
	
		return $parts;

	}

	public function formatRfc822($addresses, $encoded = false) {

		$validator = new Yab_Validator_Email();

		$addresses = $this->splitAddresses($addresses);
		
		$rfc822_addresses = array();
		
		foreach($addresses as $address) {
			
			$address = trim($address);
			$address = preg_replace('#(<[^>]+>)#s', ' $1', $address);
			$address = preg_replace('#"([^\s"]+@[^\s"]+)#s', '" $1', $address);
			$address = preg_split('#\s+#s', $address);
			
			$rfc822_address = array_pop($address);
			$rfc822_address = trim($rfc822_address, '<>');

			if(!$validator->validate($rfc822_address))
				throw new Yab_Exception('"'.$rfc822_address.'" is not a valid email');
			
			$rfc822_address = '<'.$rfc822_address.'>';
			
			$rfc822_alias = implode(' ', $address);
			
			$rfc822_alias = trim($rfc822_alias);
			
			if($rfc822_alias) {
				
				if(!preg_match('#^".+"$#s', $rfc822_alias))
					$rfc822_alias = '"'.str_replace('"', '\"', $rfc822_alias).'"';
	
				if($encoded)
					$rfc822_alias = $this->encodeHeader($rfc822_alias);
	
				$rfc822_address = $rfc822_alias.' '.$rfc822_address;
				
			}
				
			array_push($rfc822_addresses, $rfc822_address);

		}
			
		return implode(', ', $rfc822_addresses);

	}

	public function encodeHeader($header, $charset = null, $encoding = null, $max_length = 76) {

		$html = new Yab_Filter_Html();

		$regexp = '#([\\x00-\\x1F\\x3D\\x3F\\x7F-\\xFF])#e';

		if(!preg_match($regexp, $header))
			return $header;

		if($encoding === null)
			$encoding = $this->_encoding;

		if(!in_array($encoding, array('base64', 'quoted-printable')))
			return $header;

		if($charset === null)
			$charset = $this->_charset;

		$prefix = '=?'.$charset.'?'.strtoupper(substr($encoding, 0, 1)).'?';
		$suffix = '?=';

		$line_length = $max_length - strlen($prefix) - strlen($suffix);

		$header = trim($header);

		// if(preg_match('#(.+)(<.+@.+\..+>)#Uis', $header, $match)) {
		
			// $header = $match[1];
			// $header = preg_replace($regexp, '"=".strtoupper(dechex(ord("\1")))', $header);
			// $header = preg_replace('#\s#', '_', $header);
			// $header = array($prefix.$header.$suffix.$match[2]);
			
		if($encoding == 'quoted-printable') {

			$header = preg_replace($regexp, '"=".strtoupper(dechex(ord("\1")))', $header);
			$header = preg_replace('#\s#', '_', $header);
	
			preg_match_all('#.{1,'.($line_length - 2).'}([^=]{0,2})?#', $header, $header);
			$header = $header[0];

			foreach($header as $key => $value)
				$header[$key] = $prefix.$value.$suffix;
			
		} elseif($encoding == 'base64') {

			$header = base64_encode($header);
			
			$header = str_split($header, $line_length);
			
			foreach($header as $key => $value)
				$header[$key] = $prefix.$value.$suffix;

		}

		return implode(self::CRLF."\t", $header);

	}

	public function encodePart($part, $charset = null, $encoding = null, $max_length = 76) {

		if($encoding === null)
			$encoding = $this->_encoding;

		if($encoding == 'base64')
			return chunk_split(base64_encode($part), $max_length);

		if($encoding == 'quoted-printable') {

			$emulate_imap_8bit = true;

			$regexp = '#[^\x09\x20\x21-\x3C\x3E-\x7E]#e';

			if($emulate_imap_8bit)
				$regexp = '#[^\x20\x21-\x3C\x3E-\x7E]#e';

			$lines = preg_split('#(\r\n|\r|\n)#', $part);

			foreach($lines as $line_number => $line) {

				if(strlen($line) === 0) 
					continue;

				$line = preg_replace($regexp, 'sprintf("=%02X", ord("$0"));', $line); 

				$line_length = strlen($line);
				$last_char = ord($line[$line_length - 1]);

				if(!($emulate_imap_8bit && ($line_number == count($lines) - 1))) {
					if(($last_char == 0x09) || ($last_char == 0x20)) {
						$line[$line_length - 1] = '=';
						$line .= $last_char == 0x09 ? '09' : '20';					
					}
				}

				if($emulate_imap_8bit) 
					$line = str_replace(' =0D', '=20=0D', $line);

				preg_match_all('#.{1,'.($max_length - 3).'}([^=]{0,2})?#', $line, $match);

				$line = implode('='.self::CRLF, $match[0]);

				$lines[$line_number] = $line;

			}

			return implode(self::CRLF, $lines);

		}

		return $part;

	}

}

// Do not clause PHP tags unless it is really necessary