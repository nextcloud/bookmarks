<?php

define('PHPUNIT_RUN', 1);

require_once __DIR__ . '/../../../lib/base.php';

if (!class_exists('PHPUnit_Framework_TestCase')) {
	require_once('PHPUnit/Autoload.php');
}

\OC_App::loadApp('bookmarks');
require_once(__DIR__ . '/TestCase.php'); // stable9 compatibility
require_once(\OC::$SERVERROOT . '/tests/lib/Util/User/Dummy.php'); // OC >= 9.1 Tests?

OC_Hook::clear();
