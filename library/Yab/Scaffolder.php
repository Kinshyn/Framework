<?php
/**
 * Yab Framework
 *
 * @category   Yab
 * @package    Yab_Scaffolder
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Scaffolder extends Yab_Object {

	private $_db_adapter = null;

	private $_directory = '';

	public function setDb(Yab_Db_Adapter_Abstract $db) {

		$this->_db_adapter = $db;

		return $this;

	}

	public function setDirectory($directory) {

		$this->_directory = (string) $directory;

		return $this;

	}

	public function scaffold($prefix = null) {

		$this->scaffoldModels($prefix);
		$this->scaffoldForms($prefix);
		$this->scaffoldControllers($prefix);
		$this->scaffoldViews($prefix);
		$this->scaffoldIndex($prefix);

		return $this;

	}

	public function scaffoldIndex($prefix = null) {

		$prefix_class = $this->getPrefixClass($prefix);
		$prefix_file = $this->getPrefixFile($prefix);

		$tables = $this->_getDbAdapter()->getTables();

		$content = '<?php'.PHP_EOL.PHP_EOL;

		$content .= 'class Controller_'.$prefix_class.'Index extends Yab_Controller_Action {'.PHP_EOL.PHP_EOL;

		$content .= "\t".'public function actionIndex() {'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'$this->_view->setFile(\'View'.DIRECTORY_SEPARATOR.'index'.DIRECTORY_SEPARATOR.'index.html\');'.PHP_EOL.PHP_EOL;
		$content .= "\t".'}'.PHP_EOL.PHP_EOL;

		$content .= '}';

		$this->_scaffoldFile('Controller'.DIRECTORY_SEPARATOR.ucfirst($prefix_file).'Index.php', $content);

		$content = '<dl>'.PHP_EOL;
		$content .= "\t".'<dt>Menu</dt>'.PHP_EOL;

		foreach($tables as $table) 
			$content .= "\t".'<dd><a href="<?php echo $this->getRequest(\''.ucfirst($table->getName()).'\', \'index\'); ?>">'.ucfirst($table->getName()).'</a></dd>'.PHP_EOL;

		$content .= '</dl>';

		return $this->_scaffoldFile('View'.DIRECTORY_SEPARATOR.$prefix_file.'index'.DIRECTORY_SEPARATOR.'index.html', $content);

	}

	public function scaffoldModels($prefix = null) {

		$tables = $this->_getDbAdapter()->getTables();

		foreach($tables as $table) 
			$this->scaffoldModel($table, $prefix);

		return $this;

	}

	public function scaffoldViews($prefix = null) {

		$tables = $this->_getDbAdapter()->getTables();

		foreach($tables as $table) 
			$this->scaffoldView($table, $prefix);

		return $this;

	}

	public function scaffoldControllers($prefix = null) {

		$tables = $this->_getDbAdapter()->getTables();

		foreach($tables as $table) 
			$this->scaffoldController($table, $prefix);

		return $this;

	}

	public function scaffoldForms($prefix = null) {

		$tables = $this->_getDbAdapter()->getTables();

		foreach($tables as $table) 
			$this->scaffoldForm($table, $prefix);

		return $this;

	}

	public function getForeignTable(Yab_Db_Table_Column $column) {

		if(!count($column->getIndexes()))
			return null;

		if(preg_match('#^id_#', $column->getName()))
			return substr($column->getName(), 3);

		if(preg_match('#_id$#', $column->getName()))
			return substr($column->getName(), 0, -3);

		return null;

	}

	public function getClass($table_name) {

		return implode('_', array_map('ucfirst', explode('_', $table_name)));

	}

	public function getPrefixClass($prefix) {

		return $prefix ? ucfirst(trim($prefix, '_')).'_' : '';

	}

	public function getPrefixFile($prefix) {

		return $prefix ? strtolower(trim($prefix, '_\\/').DIRECTORY_SEPARATOR) : '';

	}

	public function scaffoldModel(Yab_Db_Table $table, $prefix = null) {

		$class = $this->getClass($table->getName());
		$file = implode(DIRECTORY_SEPARATOR, explode('_', $class));
		$filter_pluralize = new Yab_Filter_Pluralize();
		$prefix_class = $this->getPrefixClass($prefix);
		$prefix_file = $this->getPrefixFile($prefix);

		$content = '<?php'.PHP_EOL.PHP_EOL;

		$content .= 'class Model_'.$prefix_class.$class.' extends Yab_Db_Table {'.PHP_EOL.PHP_EOL;

		$content .= "\t".'protected $_name = \''.$table->getName().'\';'.PHP_EOL.PHP_EOL;

		foreach($table->getColumns() as $column) {		

			$foreign_table = $this->getForeignTable($column);

			if($foreign_table) {

				$foreign_class = $this->getClass($foreign_table);

				$content .= "\t".'public function get'.str_replace('_', '', $foreign_class).'() {'.PHP_EOL.PHP_EOL;
				$content .= "\t\t".'return new Model_'.$foreign_class.'($this->get(\''.$column->getName().'\'));'.PHP_EOL.PHP_EOL;
				$content .= "\t".'}'.PHP_EOL.PHP_EOL;

			}

		}

		$tables = $table->getAdapter()->getTables();

		foreach($tables as $_table) {

			if($_table->getName() == $table->getName()) { 

				# A TRAITER LES REFERENCE CIRCULAIRE

				continue;

			}
		
			foreach($_table->getColumns() as $column) {
				
				$foreign_table = $this->getForeignTable($column);
			
				if($foreign_table == $table->getName()) {
			
					$foreign_class = $this->getClass($_table->getName());

					$content .= "\t".'public function get'.str_replace('_', '', $filter_pluralize->filter($foreign_class)).'() {'.PHP_EOL.PHP_EOL;
	
					foreach($table->getPrimaryColumns() as $primary_column) {

						$content .= "\t\t".'return $this->getTable(\'Model_'.$foreign_class.'\')->search(array(\''.$column->getName().'\' => $this->get(\''.$primary_column->getName().'\')));'.PHP_EOL.PHP_EOL;

						break;
					
					}
					
					$content .= "\t".'}'.PHP_EOL.PHP_EOL;
				
				}
			
			}
		
		}
		
		$content .= '}';

		return $this->_scaffoldFile('Model'.DIRECTORY_SEPARATOR.ucfirst($prefix_file).$file.'.php', $content);

	}

	public function scaffoldController(Yab_Db_Table $table, $prefix = null) {

		$filter_lc = new Yab_Filter_LowerCase(array('separator' => '_'));
		$filter_pluralize = new Yab_Filter_Pluralize();
		$prefix_class = $this->getPrefixClass($prefix);
		$prefix_file = $this->getPrefixFile($prefix);
		
		$class = $this->getClass($table->getName());
		$file = implode(DIRECTORY_SEPARATOR, explode('_', $class));
		$object = strtolower($table->getName());
		$objects = $filter_pluralize->filter($object);
		
		$content = '<?php'.PHP_EOL.PHP_EOL;
		
		$content .= 'class Controller_'.$prefix_class.$class.' extends Yab_Controller_Action {'.PHP_EOL.PHP_EOL;
		
		$content .= "\t".'public function actionIndex() {'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'$'.$object.' = new Model_'.$class.'();'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'$'.$objects.' = $'.$object.'->fetchAll();'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'$this->_view->set(\''.$objects.'\', $'.$objects.');'.PHP_EOL;
		$content .= "\t".'}'.PHP_EOL.PHP_EOL;
		
		$content .= "\t".'public function actionAdd() {'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'$'.$object.' = new Model_'.$class.'();'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'$form = new Form_'.$class.'($'.$object.');'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'if($form->isSubmitted() && $form->isValid()) {'.PHP_EOL.PHP_EOL;
		$content .= "\t\t\t".'$'.$object.'->populate($form->getValues())->save();'.PHP_EOL.PHP_EOL;
		$content .= "\t\t\t".'$this->getSession()->set(\'flash\', \''.$object.' as been added\');'.PHP_EOL.PHP_EOL;
		$content .= "\t\t\t".'$this->forward(\''.$class.'\', \'index\');'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'}'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'$this->_view->set(\'helper_form\', new Yab_Helper_Form($form));'.PHP_EOL.PHP_EOL;
		$content .= "\t".'}'.PHP_EOL.PHP_EOL;
		
		$content .= "\t".'public function actionEdit() {'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'$'.$object.' = new Model_'.$class.'($this->_request->getParams());'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'$form = new Form_'.$class.'($'.$object.');'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'if($form->isSubmitted() && $form->isValid()) {'.PHP_EOL.PHP_EOL;
		$content .= "\t\t\t".'$'.$object.'->populate($form->getValues())->save();'.PHP_EOL.PHP_EOL;
		$content .= "\t\t\t".'$this->getSession()->set(\'flash\', \''.$object.' as been edited\');'.PHP_EOL.PHP_EOL;
		$content .= "\t\t\t".'$this->forward(\''.$class.'\', \'index\');'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'}'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'$this->_view->set(\'helper_form\', new Yab_Helper_Form($form));'.PHP_EOL.PHP_EOL;
		$content .= "\t".'}'.PHP_EOL.PHP_EOL;
		
		$content .= "\t".'public function actionDelete() {'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'$'.$object.' = new Model_'.$class.'($this->_request->getParams());'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'$'.$object.'->delete();'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'$this->getSession()->set(\'flash\', \''.$object.' as been deleted\');'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'$this->forward(\''.$class.'\', \'index\');'.PHP_EOL.PHP_EOL;
		$content .= "\t".'}'.PHP_EOL.PHP_EOL;
		
		$content .= '}';
		
		return $this->_scaffoldFile('Controller'.DIRECTORY_SEPARATOR.ucfirst($prefix_file).$file.'.php', $content);

	}

	public function scaffoldView(Yab_Db_Table $table, $prefix = null) {

		$filter_lc = new Yab_Filter_LowerCase(array('separator' => '_'));
		$filter_pluralize = new Yab_Filter_Pluralize();
		$prefix_file = $this->getPrefixFile($prefix);
		
		$class = $this->getClass($table->getName());
		$file = $filter_lc->filter(implode(DIRECTORY_SEPARATOR, explode('_', $class)));
		$object = strtolower($table->getName());
		$objects = $filter_pluralize->filter($object);

		$content = '<?php'.PHP_EOL.PHP_EOL;
		$content .= '$session = $this->getSession();'.PHP_EOL;
		$content .= '$filter_html = new Yab_Filter_Html();'.PHP_EOL;
		$content .= '$pager = new Yab_Helper_Pager($'.$objects.', \'pager_'.$object.'\');'.PHP_EOL.PHP_EOL;
		$content .= '$'.$objects.' = $pager->getStatement();'.PHP_EOL.PHP_EOL;
		$content .= '?>'.PHP_EOL.PHP_EOL;
		$content .= '<table class="table table-striped">'.PHP_EOL;
		$content .= "\t".'<caption>'.$class.'</caption>'.PHP_EOL;
		$content .= "\t".'<thead>'.PHP_EOL;
		$content .= "\t\t".'<tr>'.PHP_EOL;
		
		foreach($table->getColumns() as $column)
			$content .= "\t\t\t".'<td><?php echo $pager->getSortLink(\''.$column.'\'); ?></td>'.PHP_EOL;
		
		$content .= "\t\t\t".'<td colspan="2">#</td>'.PHP_EOL;
		$content .= "\t\t".'</tr>'.PHP_EOL;
		$content .= "\t".'</thead>'.PHP_EOL;
		
		$content .= "\t".'<tbody>'.PHP_EOL;
		$content .= "\t".'<?php foreach($'.$objects.' as $'.$object.'): ?>'.PHP_EOL;
		$content .= "\t\t".'<tr>'.PHP_EOL;
		
		foreach($table->getColumns() as $column)
			$content .= "\t\t\t".'<td<?php echo $pager->getClass($'.$objects.'); ?>><?php echo $'.$object.'->get(\''.$column->getName().'\', $filter_html); ?></td>'.PHP_EOL;
		
		$content .= "\t\t\t".'<td<?php echo $pager->getClass($'.$objects.'); ?>><a href="<?php echo $this->getRequest(\''.$class.'\', \'edit\', $'.$object.'->getPrimary()); ?>">EDIT</a></td>'.PHP_EOL;
		$content .= "\t\t\t".'<td<?php echo $pager->getClass($'.$objects.'); ?>><a href="<?php echo $this->getRequest(\''.$class.'\', \'delete\', $'.$object.'->getPrimary()); ?>">DELETE</a></td>'.PHP_EOL;
		$content .= "\t\t".'</tr>'.PHP_EOL;
		$content .= "\t".'<?php endforeach; ?>'.PHP_EOL;
		$content .= "\t".'</tbody>'.PHP_EOL;

		$content .= "\t".'<tfoot>'.PHP_EOL;
		$content .= "\t\t".'<tr>'.PHP_EOL;
		$content .= "\t\t\t".'<td colspan="'.(count($table->getColumns()) + 2).'"><?php echo $pager->getPagination(); ?></td>'.PHP_EOL;
		$content .= "\t\t".'</tr>'.PHP_EOL;
		$content .= "\t\t".'<tr>'.PHP_EOL;
		$content .= "\t\t\t".'<td colspan="'.(count($table->getColumns()) + 2).'">'.PHP_EOL;
		$content .= "\t\t\t\t".'<a href="<?php echo $this->getRequest(\''.$class.'\', \'add\'); ?>">ADD</a>'.PHP_EOL;
		$content .= "\t\t\t\t".'<a href="<?php echo $this->getRequest(\'Index\', \'index\'); ?>">Go back to index</a>'.PHP_EOL;
		$content .= "\t\t\t".'</td>'.PHP_EOL;
		$content .= "\t\t".'</tr>'.PHP_EOL;
		$content .= "\t".'</tfoot>'.PHP_EOL;
		$content .= '</table>';

		$this->_scaffoldFile('View'.DIRECTORY_SEPARATOR.$prefix_file.$file.DIRECTORY_SEPARATOR.'index.html', $content);

		$this->_scaffoldFile('View'.DIRECTORY_SEPARATOR.$prefix_file.$file.DIRECTORY_SEPARATOR.'add.html', '<?php echo $helper_form->setLegend(\'Add\')->setSubmit(\'Add\')->setButton($this->getRequest(\''.$class.'\', \'index\'), \'Back\');');
		$this->_scaffoldFile('View'.DIRECTORY_SEPARATOR.$prefix_file.$file.DIRECTORY_SEPARATOR.'edit.html', '<?php echo $helper_form->setLegend(\'Edit\')->setSubmit(\'Edit\')->setButton($this->getRequest(\''.$class.'\', \'index\'), \'Back\');');

		return $this;

	}

	public function scaffoldForm(Yab_Db_Table $table, $prefix = null) {

		$filter_lc = new Yab_Filter_LowerCase(array('separator' => '_'));
		$filter_pluralize = new Yab_Filter_Pluralize();
		
		$prefix_class = $this->getPrefixClass($prefix);
		$prefix_file = $this->getPrefixFile($prefix);
		
		$class = $this->getClass($table->getName());
		$file = implode(DIRECTORY_SEPARATOR, explode('_', $class));
		$object = strtolower($table->getName());
		
		$content = '<?php'.PHP_EOL.PHP_EOL;
		
		$content .= 'class Form_'.$prefix_class.$class.' extends Yab_Form {'.PHP_EOL.PHP_EOL;
		
		$content .= "\t".'public function __construct(Model_'.$class.' $'.$object.') {'.PHP_EOL.PHP_EOL;
		$content .= "\t\t".'$this->set(\'method\', \'post\')->set(\'action\', \'\');'.PHP_EOL.PHP_EOL;
		
		foreach($table->getColumns() as $column) {

			if($column->getPrimary() || $column->getSequence())
				continue;
			
			$foreign_table = $this->getForeignTable($column);

			if($foreign_table) {
			
				$foreign_class = $this->getClass($foreign_table);
				
				try {
				
					$columns = $table->getTable($foreign_table)->getColumns();

				} catch(Yab_Exception $e) {
				
					continue;
				
				}

				$key_column = array_shift($columns);
				
				do {
				
					$value_column = array_shift($columns);
					
				} while(preg_match('#^id_|_id$#', $value_column) && count($columns));
				
				if(!$value_column)
					 $value_column = $key_column;

				$content .= "\t\t".'$this->setElement(\''.$column->getName().'\', array('.PHP_EOL;
				$content .= "\t\t\t".'\'type\' => \'select\','.PHP_EOL;
				$content .= "\t\t\t".'\'id\' => \''.$column->getName().'\','.PHP_EOL;
				$content .= "\t\t\t".'\'label\' => \''.$column->getName().'\','.PHP_EOL;
				$content .= "\t\t\t".'\'value\' => $'.$object.'->has(\''.$column->getName().'\') ? $'.$object.'->get(\''.$column->getName().'\') : '.($column->getDefaultValue() ? "'".addslashes($column->getDefaultValue())."'" : 'null').','.PHP_EOL;
				$content .= "\t\t\t".'\'fake_options\' => array(),'.PHP_EOL;
				$content .= "\t\t\t".'\'options\' => $'.$object.'->getTable(\'Model_'.$foreign_class.'\')->fetchAll()->setKey(\''.$key_column->getName().'\')->setValue(\''.$value_column->getName().'\'),'.PHP_EOL;
				$content .= "\t\t\t".'\'errors\' => array(),'.PHP_EOL;
				$content .= "\t\t".'));'.PHP_EOL.PHP_EOL;				
			
			} elseif(preg_match('#enum(\(.+)\)#', $column->getType())) {
			
				$type = preg_replace('#enum\((.+)\)#', '$1', $column->getType());
			
				$content .= "\t\t".'$this->setElement(\''.$column->getName().'\', array('.PHP_EOL;
				$content .= "\t\t\t".'\'type\' => \'radio\','.PHP_EOL;
				$content .= "\t\t\t".'\'id\' => \''.$column->getName().'\','.PHP_EOL;
				$content .= "\t\t\t".'\'label\' => \''.$column->getName().'\','.PHP_EOL;
				$content .= "\t\t\t".'\'value\' => $'.$object.'->has(\''.$column->getName().'\') ? $'.$object.'->get(\''.$column->getName().'\') : '.($column->getDefaultValue() ? "'".addslashes($column->getDefaultValue())."'" : 'null').','.PHP_EOL;
				$content .= "\t\t\t".'\'fake_options\' => array(),'.PHP_EOL;
				$content .= "\t\t\t".'\'options\' => array('.$type.'),'.PHP_EOL;
				$content .= "\t\t\t".'\'errors\' => array(),'.PHP_EOL;
				$content .= "\t\t".'));'.PHP_EOL.PHP_EOL;	
			
			} elseif(preg_match('#text#', $column->getType())) {

				$content .= "\t\t".'$this->setElement(\''.$column->getName().'\', array('.PHP_EOL;
				$content .= "\t\t\t".'\'type\' => \'textarea\','.PHP_EOL;
				$content .= "\t\t\t".'\'id\' => \''.$column->getName().'\','.PHP_EOL;
				$content .= "\t\t\t".'\'label\' => \''.$column->getName().'\','.PHP_EOL;
				$content .= "\t\t\t".'\'value\' => $'.$object.'->has(\''.$column->getName().'\') ? $'.$object.'->get(\''.$column->getName().'\') : '.($column->getDefaultValue() ? "'".addslashes($column->getDefaultValue())."'" : 'null').','.PHP_EOL;
				$content .= "\t\t\t".'\'validators\' => array('.(!$column->getNull() ? '\'NotEmpty\'' : '').'),'.PHP_EOL;
				$content .= "\t\t\t".'\'errors\' => array(),'.PHP_EOL;
				$content .= "\t\t".'));'.PHP_EOL.PHP_EOL;
						
			} elseif(preg_match('#int#', $column->getType())) {

				$content .= "\t\t".'$this->setElement(\''.$column->getName().'\', array('.PHP_EOL;
				$content .= "\t\t\t".'\'type\' => \'text\','.PHP_EOL;
				$content .= "\t\t\t".'\'id\' => \''.$column->getName().'\','.PHP_EOL;
				$content .= "\t\t\t".'\'label\' => \''.$column->getName().'\','.PHP_EOL;
				$content .= "\t\t\t".'\'value\' => $'.$object.'->has(\''.$column->getName().'\') ? $'.$object.'->get(\''.$column->getName().'\') : '.($column->getDefaultValue() ? "'".addslashes($column->getDefaultValue())."'" : 'null').','.PHP_EOL;
				$content .= "\t\t\t".'\'validators\' => array(\'Int\''.(!$column->getNull() ? ', \'NotEmpty\'' : '').'),'.PHP_EOL;
				$content .= "\t\t\t".'\'errors\' => array(),'.PHP_EOL;
				$content .= "\t\t".'));'.PHP_EOL.PHP_EOL;
						
			} elseif(preg_match('#float|decimal#', $column->getType())) {

				$content .= "\t\t".'$this->setElement(\''.$column->getName().'\', array('.PHP_EOL;
				$content .= "\t\t\t".'\'type\' => \'text\','.PHP_EOL;
				$content .= "\t\t\t".'\'id\' => \''.$column->getName().'\','.PHP_EOL;
				$content .= "\t\t\t".'\'label\' => \''.$column->getName().'\','.PHP_EOL;
				$content .= "\t\t\t".'\'value\' => $'.$object.'->has(\''.$column->getName().'\') ? $'.$object.'->get(\''.$column->getName().'\') : '.($column->getDefaultValue() ? "'".addslashes($column->getDefaultValue())."'" : 'null').','.PHP_EOL;
				$content .= "\t\t\t".'\'validators\' => array(\'Float\''.(!$column->getNull() ? ', \'NotEmpty\'' : '').'),'.PHP_EOL;
				$content .= "\t\t\t".'\'errors\' => array(),'.PHP_EOL;
				$content .= "\t\t".'));'.PHP_EOL.PHP_EOL;
			
			} else {
				
				$content .= "\t\t".'$this->setElement(\''.$column->getName().'\', array('.PHP_EOL;
				$content .= "\t\t\t".'\'type\' => \'text\','.PHP_EOL;
				$content .= "\t\t\t".'\'id\' => \''.$column->getName().'\','.PHP_EOL;
				$content .= "\t\t\t".'\'label\' => \''.$column->getName().'\','.PHP_EOL;
				$content .= "\t\t\t".'\'value\' => $'.$object.'->has(\''.$column->getName().'\') ? $'.$object.'->get(\''.$column->getName().'\') : '.($column->getDefaultValue() ? "'".addslashes($column->getDefaultValue())."'" : 'null').','.PHP_EOL;
				$content .= "\t\t\t".'\'validators\' => array('.(!$column->getNull() ? '\'NotEmpty\'' : '').'),'.PHP_EOL;
				$content .= "\t\t\t".'\'errors\' => array(),'.PHP_EOL;
				$content .= "\t\t".'));'.PHP_EOL.PHP_EOL;
				
			}

		}
		
		$content .= "\t".'}'.PHP_EOL.PHP_EOL;
		
		$content .= '}';
		
		return $this->_scaffoldFile('Form'.DIRECTORY_SEPARATOR.ucfirst($prefix_file).$file.'.php', $content);

	}

	protected function _getDbAdapter() {

		if($this->_db_adapter === null)
			$this->_db_adapter = Yab_Db_Adapter_Abstract::getDefaultAdapter();

		return $this->_db_adapter;

	}
	
	protected function _scaffoldFile($file_path, $content) {

		$file = new Yab_File($this->_directory.DIRECTORY_SEPARATOR.$file_path);
		
		$file->append($content)->write()->chmod(0777);
	
		echo 'Creation de '.$file->getPath().'<br />';
	
		return $this;
	
	}

}

// Do not clause PHP tags unless it is really necessary
