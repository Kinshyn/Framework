<?php
/**
 * Yab Framework
 *  
 * @category   Yab_Helper
 * @package    Yab_Helper_Menu
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Helper_Menu {

	protected $_parent = null;
	
	protected $_url = null;
	protected $_match = null;
	protected $_label = null;
	protected $_visible = true;
	
	protected $_childs = array();

	public function setUrl($url) {

		$this->_url = (string) $url;

		return $this;

	}

	public function setLabel($label) {

		$this->_label = (string) $label;

		return $this;

	}

	public function setMatch($match) {

		$this->_match = (string) $match;

		return $this;

	}

	public function setVisible($visible) {

		$this->_visible = (bool) $visible;

		return $this;

	}

	public function getUrl() {

		return (string) $this->_url;

	}

	public function getLabel() {

		return (string) $this->_label;

	}

	public function getMatch() {

		return (string) $this->_match;

	}

	public function getVisible() {

		return (bool) $this->_visible;

	}

	public function getChilds() {

		return $this->_childs;

	}

	public function addChild($url = null, $label = null, $match = null, $visible = true) {

		$menu = new Yab_Helper_Menu();
		
		$menu->setUrl($url);
		$menu->setLabel($label);
		$menu->setMatch($match);
		$menu->setVisible($visible);
		
		$menu->_parent = $this;
		
		array_push($this->_childs, $menu);

		return $menu;

	}

	public function getChild($url, $depth = 0, $current_depth = 0) {

		if($depth && $depth <= $current_depth)
			throw new Yab_Exception($url.' is not an existing child "'.$depth.' <= '.$current_depth.'"');

		if($this->_visible && ((string) $url) == ((string) $this->_url))
			return $this;

		foreach($this->_childs as $child) {

			try {

				return $child->getChild($url, $depth, $current_depth + 1);

			} catch(Yab_Exception $e) {

				continue;

			}

		}

		if($this->_visible && $this->_match && preg_match($this->_match, (string) $url))
			return $this;

		throw new Yab_Exception($url.' is not an existing child "no more childs"');

	}

	public function getParent() {

		if($this->_parent instanceof Yab_Helper_Menu)
			return $this->_parent;

		throw new Yab_Exception('"'.$this->_url.'" does not have any parent');

	}

	protected function _match($url) {

		if(!$this->_visible)
			return false;

		if(((string) $url) == ((string) $this->_url))
			return true;

		if($this->_match && preg_match($this->_match, (string) $url))
			return true;

		return false;

	}

	public function getHtml($url = '', $depth = 0, $current_depth = 0, $first = true) {

		$html = '';

		if(!$this->_visible)
			return $html;

		if($this->_label)
			$html .= '<a href="'.$this->_url.'"'.(!$current_depth ? ' class="navigation_title"' : '').'>'.$this->_label.'</a>';

		if($depth && $depth <= $current_depth)
			return $html;

		if(count($this->_childs)) {

			$html .= PHP_EOL.'<ul class="depth'.$current_depth.'">'.PHP_EOL;

			foreach($this->_childs as $i => $child) {

				$child_html = $child->getHtml($url, $depth, $current_depth + 1, $first);

				if(!$child_html)
					continue;

				$classes = array('child'.$i);

				if($child->_match($url))
					array_push($classes, 'selected');

				if($first)
					array_push($classes, 'first');

				$html .= "\t".'<li'.(count($classes) ? ' class="'.implode(' ', $classes).'"' : '').'>'.$child_html.'</li>'.PHP_EOL;

				$first = false;

			}

			$html .= '</ul>'.PHP_EOL;

		} 

		return $html;

	}

}

// Do not clause PHP tags unless it is really necessary