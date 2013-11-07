<?php

$root = dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR;

require $root.'library/Yab/Loader.php';

try {

	$loader = Yab_Loader::getInstance();

	$loader->addPath($root.'application')->configure($root.'config/config.ini');

	$db = $loader->getRegistry()->get('db');
	
	$scaffolder = new Yab_Scaffolder();
	
	$scaffolder->setDb($db);
	
	$scaffolder->setDirectory($root.'application');

	$scaffolder->scaffold();
		
} catch(Yab_Exception $e) {

	echo $e;

}
