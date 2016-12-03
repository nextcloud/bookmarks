<?php

define('PHPUNIT_RUN', 1);

require_once __DIR__ . '/../../../lib/base.php';

if (!class_exists('PHPUnit_Framework_TestCase')) {
	require_once('PHPUnit/Autoload.php');
}

\OC_App::loadApp('bookmarks');
require_once(__DIR__ . '/TestCase.php'); // stable9 compatibility
OC::$loader->addValidRoot(OC::$SERVERROOT . '/tests'); // Hope it helps run OC >= 9.1 tests…

OC_Hook::clear();
