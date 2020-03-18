<?php

if (!defined('PHPUNIT_RUN')) {
	define('PHPUNIT_RUN', 1);
}

require_once __DIR__ . '/../../../lib/base.php';

if (!class_exists('\PHPUnit\Framework\TestCase')) {
	require_once('PHPUnit/Autoload.php');
}

\OC_App::loadApp('bookmarks');
//require_once(__DIR__ . '/TestCase.php'); // stable9 compatibility

// OC >= 9.1 Tests?
$dummyClass = \OC::$SERVERROOT . '/tests/lib/Util/User/Dummy.php';
if(file_exists($dummyClass)) {
	require_once($dummyClass);
}


OC_Hook::clear();
