<?php
/**
 * Yab Framework
 *
 * @category   Yab
 * @package    Yab_Observer
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Observer {

	const PARAM = 'Yab_Observer::notificationParam';
	const NOTIFY = 'Yab_Observer::notify';

	private $_events = array();
	
	public function notify($event, array $params = array()) {

		if($event != self::NOTIFY)
			$this->notify(self::NOTIFY, array($event));

		if(!array_key_exists($event, $this->_events))
			return $this;

		if(!is_array($this->_events[$event]))
			return $this;

		usort($this->_events[$event], array($this, 'prioritize'));

		foreach($this->_events[$event] as $callback) {

		 	$event_params = $params;

			foreach($callback['params'] as $key => $param) {

				if(is_numeric(strpos($param, self::PARAM)))
					$param = str_replace(self::PARAM, array_shift($event_params), $param);

				$callback['params'][$key] = $param;

			}

			Yab_Loader::getInstance()->invoke($callback['object'], $callback['method'], $callback['params']);

		}

		return $this;

	}

	public function observe($event, $object, $method, array $params = array(), $priority = 20) {

		if(!array_key_exists($event, $this->_events))
			$this->_events[$event] = array();

		$this->_events[$event][] = array(
			'object' => $object,
			'method' => $method,
			'params' => $params,
			'priority' => intval($priority),
		);

		return $this;

	}

	private function prioritize(array $callback_a, array $callback_b) {

		return $callback_a['priority'] <= $callback_b['priority'];

	}

}

// Do not clause PHP tags unless it is really necessary