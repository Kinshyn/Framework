<?php
/**
 * Yab Framework
 *
 * @category   Yab
 * @package    Yab_Socket
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Socket {

	private $_socket = null;

	private $_address = null;
	private $_port = null;

	private $_max_clients = 255;
	private $_clients = array();

	private $_client = null;

	protected $_socket_domain = AF_INET;
	protected $_socket_type = SOCK_STREAM;
	protected $_socket_protocol = SOL_TCP;
	
	final public function __construct($address, $port, $socket = null) {
	
		$this->_address = (string) $address;
		$this->_port = (int) $port;
		
		if(is_resource($socket)) {
		
			$this->_socket = $socket;
			
			$this->_client = true;
			
		}

	}
	
	private function _resource() {
	
		if(is_resource($this->_socket))
			return $this->_socket;
		
		$this->_socket = socket_create($this->_socket_domain, $this->_socket_type, $this->_socket_protocol);
		
		if(!is_resource($this->_socket))
			throw new Yab_Exception('can not create socket');
			
		return $this->_socket;
	
	}
	
	public function setDomain($domain) {
	
		$this->_socket_domain = (int) $domain;
		
		return $this;
	
	}
	
	public function setType($type) {
	
		$this->_socket_type = (int) $type;
		
		return $this;
	
	}
	
	public function setProtocol($protocol) {
	
		$this->_socket_protocol = (int) $protocol;
		
		return $this;
	
	}

	public function getAddress() {
	
		return $this->_address;
	
	}

	public function getPort() {
	
		return $this->_port;
	
	}

	public function read() {
	
		$this->_connect();

		if($this->_client !== true)
			throw new Yab_Exception('can not write on a server socket');
	
		$stream = @socket_read($this->_resource(), 4096);
	
		if($stream === false)
			throw new Yab_Exception('can not read on socket,  '.$this->error());
		
		return (string) $stream;
	
	}	

	public function write($stream) {
	
		$this->_connect();

		if($this->_client !== true)
			throw new Yab_Exception('can not write on a server socket');
		
		$stream = (string) $stream;

		$length = strlen($stream);
		
		$written = @socket_write($this->_resource(), $stream, $length);
		
		if($written < $length)
			throw new Yab_Exception('can not fully write on socket, '.$this->error());

		return $this;

	}

	public function broadcast($stream) {
	
		if($this->_client !== false)
			throw new Yab_Exception('can not broadcast on a client socket');

		foreach($this->_clients as $key => $client) {
		
			try {
		
				$client->write($stream);
				
			} catch(Yab_Exception $e) {
			
				$this->remClient($key)->_onDisconnect($key);
			
			}
			
		}
		
		return $this;
	
	}

	public function listen($max_clients = null) {
	
		if($this->_client)
			throw new Yab_Exception('can not listen on a client socket');

		$this->_client = false;
			
		if(is_numeric($max_clients))
			$this->_max_clients = $max_clients;
			
		if(!socket_bind($this->_resource(), $this->_address, $this->_port))
			throw new Yab_Exception('can not bind socket to "'.$this->_address.':'.$this->_port.'" '.$this->error());
		
		if(!socket_listen($this->_resource()))
			throw new Yab_Exception('can not listen socket '.$this->error());
		
		while(true) {
		
			$client = socket_accept($this->_resource());

			$address = null;
			$port = null;
			
			socket_getpeername($client, $address, $port);

			$socket = new self($address, $port, $client);

			$this->_onAccept($socket);
			
		}
	
	}

	public function addClient($key, Yab_Socket $client) {
	
		if($this->_client !== false)
			throw new Yab_Exception('can not add client on a client socket');

		if($this->hasClient($key))
			throw new Yab_Exception('can not add this client because it is already added with this identity "'.$key.'"');
		
		if($this->_max_clients <= count($this->_clients))
			throw new Yab_Exception('can not add more clients because the limit of "'.$this->_max_clients.'" is reached');

		$this->_clients[$key] = $client;
		
		return $this;
	
	}

	public function remClient($key) {
	
		if($this->_client !== false)
			throw new Yab_Exception('can not remove client on a client socket');

		if(!$this->hasClient($key))
			throw new Yab_Exception('can not remove this client because the identity "'.$key.'" does not exists');

		unset($this->_clients[$key]);
		
		return $this;
	
	}

	public function getClient($key) {
	
		if($this->_client !== false)
			throw new Yab_Exception('can not get client on a client socket');

		if(!$this->hasClient($key))
			throw new Yab_Exception('there is no client identified by "'.$key.'"');
			
		return $this->_clients[$key];
	
	}

	public function hasClient($key) {
	
		if($this->_client !== false)
			throw new Yab_Exception('can not have client on a client socket');

		return array_key_exists($key, $this->_clients);
	
	}

	public function stop() {
	
		if($this->_client !== false)
			throw new Yab_Exception('can not stop a client socket');

		$this->_onStop();
		
		return $this->_close();
	
	}

	public function bind($address) {
			
		if(!socket_bind($this->_resource(), $address))
			throw new Yab_Exception('can not bind socket to "'.$address.'" '.$this->error());
		
		return $this;
	
	}

	public function error() {
	
		$int = socket_last_error($this->_resource());
		
		$str = socket_strerror($int);
		
		return 'ERROR['.$int.'] '.$str;
	
	}

	private function _connect() {
		
		if($this->_client !== null)
			return $this;
	
		$this->_client = true;

		if(!@socket_connect($this->_resource(), $this->_address, $this->_port))
			throw new Yab_Exception('can not connect socket to "'.$this->_address.'" on port "'.$this->_port.'"');
		
		$this->_onConnect();
		
		return $this;
	
	}
	
	private function _close() {
		
		if(!is_resource($this->_resource()))
			return $this;
			
		socket_close($this->_resource());
		
		return $this;
	
	}
	
	public function __destruct() {

		try {
		
			$this->stop();
			
		} catch(Yab_Exception $e) {
	
			$this->_close();
		
		}
		
		return $this;

	}
	
	protected function _onAccept(Yab_Socket $client) {}
	protected function _onConnect() {}
	protected function _onDisconnect($key) {}
	protected function _onStop() {}
		
}