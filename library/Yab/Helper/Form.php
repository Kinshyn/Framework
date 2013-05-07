<?php
/**
 * Yab Framework
 *  
 * @category   Yab_Helper
 * @package    Yab_Helper_Form
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Helper_Form {

	private $_form = null;
	private $_readonly = false;
	private $_submits = array();
	private $_legend = null;
	private $_buttons = array();

	public function __construct(Yab_Form $form) {

		$this->_form = $form;

	}

	public function setReadonly($readonly) {

		$this->_readonly = (bool) $readonly;

		return $this;

	}

	public function setLegend($legend) {

		$this->_legend = (string) $legend;

		return $this;

	}

	public function setSubmit($submit, $name = '') {

		$this->_submits[(string) $name] = (string) $submit;

		return $this;

	}

	public function setButton($uri, $label) {

		$this->_buttons[(string) $uri] = $label;

		return $this;

	}

	public function render() {

		$filter = new Yab_Filter_Html();

		$form_submitted = $this->_form->isSubmitted();
		
		$errors = array();

		if($form_submitted)
			$errors = $this->_form->getErrors();
		
		$html = $this->_form->set('class', 'form')->getHeadHtml();

		$html .= "\t".'<fieldset>'.PHP_EOL;

		if($this->_legend)
			$html .= "\t\t".'<legend>'.$filter->filter($this->_legend).'</legend>'.PHP_EOL;

		foreach($errors as $name => $errors) {

			foreach($errors as $error) {
		
				if(in_array($this->_form->getElement($name)->get('type'), array('text', 'textarea', 'password', 'select', 'checkbox'))) {

					$html .= "\t\t".'<p class="error"><a class="error" href="#F'.$name.'" onclick="document.getElementById(\''.$name.'\').focus(); return false;">'.$filter->filter($error).'</a></p>'.PHP_EOL;

				} else {

					$html .= "\t\t".'<p class="error"><a class="error" href="#F'.$name.'">'.$filter->filter($error).'</a></p>'.PHP_EOL;

				}
				
			}

		}

		foreach($this->_form->getElements() as $element) {

			if($this->_readonly)
				$element->set('readonly', 'readonly');

			$id = $element->has('id') ? $element->get('id') : $element->get('name');

			if($element->get('type') == 'hidden') {

				$html .= "\t\t\t".$element->set('id', $id)->getHtml().PHP_EOL;

				continue;

			}

			$html .= "\t\t".'<p class="field '.$element->get('type').($form_submitted && $element->isSubmitted() && !$element->isValid() ? ' error' : '').'" id="F'.$element->get('name').'">'.PHP_EOL;

			if($element->has('label')) {
				
				$html .= "\t\t\t".'<label for="'.$id.'">'.$filter->filter($element->get('label'));
				
				if(!$element->isValid(''))
					$html .= '&nbsp;*';

				if($element->has('tooltip'))
					$html .= '<em style="display: block">'.nl2br($filter->filter($element->get('tooltip'))).'</em>';
				
				$html .= '</label>'.PHP_EOL;
				
			}

			$html .= "\t\t\t".$element->set('id', $id)->getHtml().PHP_EOL;
			$html .= "\t\t".'</p>'.PHP_EOL;

		}

		$html .= "\t\t".'<p class="button">'.PHP_EOL;
		
		if(!count($this->_submits))
			$this->setSubmit('Submit');
		
		foreach($this->_submits as $name => $submit)
			$html .= "\t\t\t".'<input class="button" '.($name ? 'name="'.$name.'" ' : '').'type="submit" value="'.$filter->filter($submit).'" />'.PHP_EOL;

		foreach($this->_buttons as $url => $label)
			$html .= "\t\t\t".'<a href="'.$url.'" class="button">'.$label.'</a>'.PHP_EOL;

		$html .= "\t\t".'</p>'.PHP_EOL;
		$html .= "\t".'</fieldset>'.PHP_EOL;
		$html .= $this->_form->getTailHtml().PHP_EOL;

		return $html;

	}

	public function __toString() {

		try {
	
			return $this->render();
			
		} catch(Yab_Exception $e) {
		
			return $e->getMessage();
		
		}

	}

}

// Do not clause PHP tags unless it is really necessary