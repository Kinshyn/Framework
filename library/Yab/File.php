<?php
/**
 * Yab Framework
 *  
 * @category   Yab
 * @package    Yab_File
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_File {

	private $_line_ending = "\n";
	private $_line_endings = array(
		'Windows' => "\r\n",
		'Mac' => "\r",
		'Unix' => "\n",
	);

	private $_encoding = 'UTF-8';
	private $_encodings = array(
		'UTF-8',
		'ISO-8859-15',
		'ISO-8859-1',
	);
	
	private $_path = null;

	private $_mime_types = array(
		'txt' => 'text/plain',
		'htm' => 'text/html',
		'html' => 'text/html',
		'php' => 'text/html',
		'css' => 'text/css',
		'js' => 'application/javascript',
		'json' => 'application/json',
		'xml' => 'application/xml',
		'swf' => 'application/x-shockwave-flash',
		'flv' => 'video/x-flv',
		'png' => 'image/png',
		'jpe' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'jpg' => 'image/jpeg',
		'gif' => 'image/gif',
		'bmp' => 'image/bmp',
		'ico' => 'image/vnd.microsoft.icon',
		'tiff' => 'image/tiff',
		'tif' => 'image/tiff',
		'svg' => 'image/svg+xml',
		'svgz' => 'image/svg+xml',
		'zip' => 'application/zip',
		'rar' => 'application/x-rar-compressed',
		'exe' => 'application/x-msdownload',
		'msi' => 'application/x-msdownload',
		'cab' => 'application/vnd.ms-cab-compressed',
		'mp3' => 'audio/mpeg',
		'qt' => 'video/quicktime',
		'mov' => 'video/quicktime',
		'pdf' => 'application/pdf',
		'psd' => 'image/vnd.adobe.photoshop',
		'ai' => 'application/postscript',
		'eps' => 'application/postscript',
		'ps' => 'application/postscript',
		'doc' => 'application/msword',
		'rtf' => 'application/rtf',
		'xls' => 'application/vnd.ms-excel',
		'ppt' => 'application/vnd.ms-powerpoint',
		'odt' => 'application/vnd.oasis.opendocument.text',
		'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
	);
	
	protected $_content = null;

	final public function __construct($path = null) {

		if($path !== null)
			$this->setPath($path);

	}

	final public function setPath($path) {

		$this->_path = (string) $path;

		return $this;

	}

	final public function setName($name) {

		$this->_path = dirname($path).DIRECTORY_SEPARATOR.$name;

		return $this;

	}

	final public function setEncoding($encoding) {

		if(!in_array($encoding, $this->_encodings))
			throw new Yab_Exception('"'.$encoding.'" is not a valid encoding');
	
		$this->_encoding = strtoupper((string) $encoding);

		return $this;

	}

	final public function setLineEnding($line_ending) {

		$line_ending = (string) $line_ending;
	
		if(array_key_exists($line_ending, $this->_line_endings)) {
		
			$this->_line_ending = $this->_line_endings[$line_ending];
		
		} elseif(in_array($line_ending, $this->_line_endings)) {
		
			$this->_line_ending = $line_ending;
		
		} else {
		
			throw new Yab_Exception('"'.$line_ending.'" is not a valid line_ending');
		
		}
	
		return $this;

	}
	
	final public function clear() {

		$this->_content = null;
		
		return $this;

	}

	final public function append($content) {

		$this->_content = (string) $content;
		
		return $this;

	}

	final public function getPath() {

		return $this->_path;

	}

	final public function getName() {

		return basename($this->_path);

	}

	final public function getEncoding() {

		return $this->_encoding;

	}

	final public function getLineEnding() {

		return $this->_line_ending;

	}
  
	final public function getSystem() {

		if(!Yab_Loader::getInstance()->isFile($this->_path))
			throw new Yab_Exception('can not detect system on a file that doesn t exists');
			
		$part = '';

		$fh = fopen($this->_path, 'r');

		if($fh) {

			$part = fread($fh, 8192);

			fclose($fh);

		}
		
		foreach($this->_line_endings as $system => $line_ending)
			if(is_numeric(strpos($part, $line_ending)))
				return $system;

		throw new Yab_Exception('can not detect system on file "'.$this->_path.'"');

	}

	final public function getMimeType() {

		if(Yab_Loader::getInstance()->isFile($this->_path)) {
	
			if(function_exists('mime_content_type'))
				return mime_content_type($file);
				
			if(function_exists('finfo_open')) {
			
				$finfo = finfo_open(FILEINFO_MIME);
				
				$mime_type = finfo_file($finfo, $file);
				
				finfo_close($finfo);
				
				return $mime_type;
				
			}
			
		}
		
		$extension = $this->getExtension();
		
		return array_key_exists($extension, $this->_mime_types) ? $this->_mime_types[$extension] : 'application/octet-stream';

	}

	final public function read($path = null) {

		if($path !== null)
			$this->setPath($path);

		if(!Yab_Loader::getInstance()->isFile($this->_path))
			throw new Yab_Exception('can not read the file "'.$this->_path.'"');
			
		$this->_content = file_get_contents($this->_path);
			
		return $this;

	}

	final public function write($path = null) {

		if($path !== null)
			$this->setPath($path);
			
		$this->createDirectory();

		if(Yab_Loader::getInstance()->isFile($this->_path) && !Yab_Loader::getInstance()->isFile($this->_path, true, true))
			throw new Yab_Exception('can not write the file "'.$this->_path.'"');

		file_put_contents($this->_path, (string) $this);
			
		return $this;

	}

	final public function delete($path = null) {

		if($path !== null)
			$this->setPath($path);
			
		if(!Yab_Loader::getInstance()->isFile($this->_path) || !is_writable($this->_path))
			throw new Yab_Exception('can not delete the file "'.$this->_path.'"');

		unlink($this->_path);
			
		return $this;

	}

	final public function createDirectory() {
	
		if(Yab_Loader::getInstance()->isFile($this->_path))
			return $this;
		
		$parts = explode(DIRECTORY_SEPARATOR, $this->_path);
		
		array_pop($parts);
		
		$directory = null;
		
		while(count($parts)) {
		
			$part = array_shift($parts);
			
			//if(!$part) continue;
		
			$directory .= $part.DIRECTORY_SEPARATOR;

			if(!Yab_Loader::getInstance()->isDir($directory)) 
				mkdir($directory, 0755);	

			if(!Yab_Loader::getInstance()->isDir($directory))
				throw new Yab_Exception('can not create the directory "'.$directory.'"');
		
		}

		return $this;
	
	}

	final public function chmod($chmod) {

		chmod($this->getPath(), $chmod);
		
		return $this;

	}
	
	final public function getExtension() {

		if(!$this->_path)
			throw new Yab_Exception('can not detect system on a file with no file path');
			
		return substr($this->_path, strrpos($this->_path, '.') + 1);

	}

	final public function encode($text) {

		switch($this->_encoding) {
			
			case 'ISO-8859-1' :
			case 'ISO-8859-15' :
				return utf8_decode($text);
				
		}
		
		return $text;

	}

	final public function convert($text) {

		if($this->_line_ending == "\n") {
		
			$text = str_replace("\r\n", "\r", $text);
			$text = str_replace("\r", "\n", $text);
		
		} elseif($this->_line_ending == "\r\n") {
		
			$text = str_replace("\r\n", "\r", $text);
			$text = str_replace("\n", "\r", $text);
			$text = str_replace("\r", "\r\n", $text);
		
		} elseif($this->_line_ending == "\r") {
		
			$text = str_replace("\r\n", "\n", $text);
			$text = str_replace("\r", "\r", $text);
		
		}
		
		return $text;

	}
	
	final public function __toString() {

		try {

			$string = $this->getContent();

		} catch(Yab_Exception $e) {

			$string = $e->getMessage();

		}

		return $string;

	}

	protected function getContent() {

		$content = $this->_content;

		$content = $this->encode($content);
		$content = $this->convert($content);
		
		return $content;

	}	

}

// Do not clause PHP tags unless it is really necessary
