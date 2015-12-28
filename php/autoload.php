<?php

###
# @name			Autoload
# @copyright	2015 by Tobias Reich
# modified by Waitman Gobble <ns@waitman.net>
###

if (!defined('LYCHEE')) exit('Error: Direct access is not allowed!');

function lycheeAutoloaderModules($class_name) {

	$modules = array('Album', 'Import', 'Module', 'Photo', 'Session', 'Settings');
	if (!in_array($class_name, $modules)) return false;

	$file = LYCHEE . 'php/modules/' . $class_name . '.php';
	if (file_exists($file)!==false) require $file;

}

function lycheeAutoloaderAccess($class_name) {

	$access = array('Access', 'Admin', 'Guest');
	if (!in_array($class_name, $access)) return false;

	$file = LYCHEE . 'php/access/' . $class_name . '.php';
	if (file_exists($file)!==false) require $file;

}

spl_autoload_register('lycheeAutoloaderModules');
spl_autoload_register('lycheeAutoloaderAccess');
