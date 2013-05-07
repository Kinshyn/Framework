<?php
/**
 * Yab Framework
 *
 * @category   Yab_Form
 * @package    Yab_Form_Element
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Form_Element extends Yab_Object {

	private $_form = null;
	private $_errors = null;

	public function __construct(Yab_Form $form, array $attributes = array()) {

		$this->_form = $form;
  
		$this->populate($attributes);

		if(!$this->has('type'))
			$this->set('type', 'text');

		if($this->get('type') == 'file')
			$this->_form->set('enctype', 'multipart/form-data');
		
		if(!$this->has('value'))
			$this->set('value', '');
		
		if(!$this->has('check_value'))
			$this->set('check_value', 1);
		
		if(!$this->has('needed'))
			$this->set('needed', true);

		if(!$this->has('name'))
			throw new Yab_Exception('a form element must have a name attribute');
			
		if(!$this->has('id'))
			$this->set('id', $this->get('name'));

		if($this->isSubmitted()) {

			$name = $this->get('name');
			
			$datas = $this->_requestDatas();

			$this->set('value', $datas->has($name) ? $datas->get($name) : ($this->isMultiple() ? array() : null));

		}

	}

	private function _requestDatas() {

		$request = Yab_Loader::getInstance()->getRequest();

		if($this->get('type') == 'file') 
			return $request->getFile();

		if(preg_match('#post#i', $this->_form->get('method')))
			return $request->getPost();

		return $request->getGet();

	}
	
	public function isMultiple() {

		return $this->has('multiple') && $this->get('multiple', 'Bool');

	}

	public function isValid($value = null) {

		if($value !== null)
			return ((bool) (count($this->getValueErrors($value)) === 0));
	
		return ((bool) (count($this->getErrors()) === 0));

	}

	public function isSubmitted() {
	
		$name = $this->get('name');
			
		$datas = $this->_requestDatas();

		return $datas->has($name) || $datas->has('___'.$name.'___');

	}

	public function getErrors($filters = null) {

		if($this->_errors !== null)
			return $this->_errors;

		$this->_errors = $this->getValueErrors($this->getValue(), $filters);

		return $this->_errors;

	}

	public function getValueErrors($value, $filters = null) {

		$errors = array();

		$validators = $this->has('validators') ? $this->get('validators', 'Array') : array();
		$override_errors = $this->has('errors') ? $this->get('errors', 'Array') : array();
		
		if($this->has('options')) {
		
			$options = array();
		
			$options['options'] = $this->get('options');

			if($this->has('fake_options'))
				$options['fake_options'] = $this->get('fake_options');
			
			if($this->has('min_options')) {
			
				$options['min_options'] = $this->get('min_options', 'Int');

			} elseif($this->get('needed')) {
			
				$options['min_options'] = 1;
				
			}
				
			if($this->has('max_options'))
				$options['max_options'] = $this->get('max_options', 'Int');

			$validators['Options'] = $options;
		
		}
		
		$validator = Yab_Loader::getInstance('Yab_Validator_Factory');

		$validator->set('validators', $validators);
		$validator->set('errors', $override_errors);

		if($filters !== null)
		  $validator->set('filters', $filters);

		$validator->validate($value);
		
		if($this->get('type') == 'file') {

			if($this->get('needed'))
				$errors = $validator->getErrors();
		
		} elseif($value || $this->get('needed')) {

			$errors = $validator->getErrors();
			
		}

		return $errors;

	}

	final public function getValue($filters = true) {

		return $this->get('value', $filters && $this->has('filters') ? $this->get('filters') : null);

	}

	final public function getForm() {

		return $this->_form;

	}
	
	final public function addClass($class) {

		$classes = $this->has('class') ? $this->get('class') : '';
		
		$classes = trim($classes);
		
		$classes = $classes ? preg_split('#\s+#', trim($classes)) : array();
		
		if(!in_array($class, $classes))
			array_push($classes, $class);
			
		return $this->set('class', implode(' ', $classes));

	}

	public function getHtml() {

		$filter = new Yab_Filter_Html();
			
		$type = $this->get('type');
			
		$this->addClass($type);
		
		if($this->isSubmitted() && !$this->isValid()) 
			$this->addClass('error');
		
		$render = '';

		switch($type) {

			case 'text' :
			case 'password' :
			case 'hidden' :

				$render .= '<input type="'.$type.'"'.$this->getAttributesHtml().' value="'.$this->get('value', 'Html').'" />';

			break;
			
			case 'file' :

				$render .= '<input type="'.$type.'"'.$this->getAttributesHtml().' value="" />';

			break;

			case 'textarea' :

				$render .= '<textarea'.$this->getAttributesHtml().'>'.$this->get('value', 'Html').'</textarea>';

			break;

			case 'checkbox' :

				$id = $this->has('id') ? $this->get('id') : $this->get('name');

				$render .= '<input type="hidden" name="___'.$this->get('name').'___" value="'.$this->get('check_value').'" />';
				$render .= '<input type="'.$type.'" id="'.$id.'" value="'.$this->get('check_value').'"'.$this->getAttributesHtml().($this->get('value') == $this->get('check_value') ? ' checked="checked"' : '').' />';

			break;

			case 'groupbox' :
			case 'checkboxes' :
			case 'radio' :

				if($type != 'radio')
					$type = 'checkbox';
					
				$id = $this->has('id') ? $this->get('id') : $this->get('name');

				$render .= '<input type="hidden" name="___'.$this->get('name').'___" value="1" />';

				if($this->has('fake_options')) {

					foreach($this->get('fake_options') as $key => $value)
						$render .= '<span><label for="'.$id.$filter->filter($key).'">'.$filter->filter($value).'</label><input type="'.$type.'" id="'.$id.$filter->filter($key).'" value="'.$filter->filter($key).'"'.$this->getAttributesHtml().($this->_isSelected($key) ? ' checked="checked"' : '').' /></span>';

				}

				if($this->has('options')) {

					foreach($this->get('options') as $key => $value)
						$render .= '<span><label for="'.$id.$filter->filter($key).'">'.$filter->filter($value).'</label><input type="'.$type.'" id="'.$id.$filter->filter($key).'" value="'.$filter->filter($key).'"'.$this->getAttributesHtml().($this->_isSelected($key) ? ' checked="checked"' : '').' /></span>';

				}

			break;

			case 'select' :
			
				$render .= $this->isMultiple() ? '<input type="hidden" name="___'.$this->get('name').'___" value="1" />' : '';
				$render .= '<select'.$this->getAttributesHtml().($this->isMultiple() ? ' multiple' : '').'>';
				$render .= $this->has('fake_options') ? $this->getOptionsHtml($this->get('fake_options')) : '';
				$render .= $this->has('options') ? $this->getOptionsHtml($this->get('options')) : '';
				$render .= '</select>';
				
			break;
			
			default :
			
				throw new Yab_Exception('"'.$type.'" is not a valid Yab_Form_Element type');
			
			break;

		}

		$pre_html = $this->has('pre_html') ? $this->get('pre_html') : '';
		$post_html = $this->has('post_html') ? $this->get('post_html') : '';

		return $pre_html.$render.$post_html;

	}

	public function getAttributesHtml() {

		$attributes = '';

		foreach($this->_attributes as $key => $value) {
		
			$key = strtolower(trim($key));

			if(is_object($value) || is_array($value) || strpos($key, '_') === 0 || in_array($key, array('needed', 'label', 'filters', 'validators', 'value', 'errors', 'check_value', 'tooltip', 'pre_html', 'post_html')))
				continue;

			if(in_array($key, array('readonly')) && !$value)
				continue;
				
			if($key == 'name' && $this->isMultiple()) 
				$value .= '[]';

			$html = new Yab_Filter_Html();

			$attributes .= ' '.$key.'="'.$html->filter($value).'"';

		}

		return $attributes;

	}

	protected function getOptionsHtml($options) {

		$filter = new Yab_Filter_Html();

		$render_options = '';

		foreach($options as $key => $value) {

			if(is_array($value)) {

				$render_options .= '<optgroup label="'.$filter->filter($key).'">'.$this->getOptionsHtml($value).'</optgroup>';

			} else {

				$render_options .= '<option value="'.$filter->filter($key).'"'.($this->_isSelected($key) ? ' selected="selected"' : '').'>'.$filter->filter($value).'</option>';

			}

		}

		return $render_options;

	}
	
	protected function _isSelected($value) {
	
		if($this->isMultiple()) 
			return in_array($value, $this->get('value', 'Array'));
			
		return (string )$value === (string) $this->get('value');

	}

}

// Do not clause PHP tags unless it is really necessary