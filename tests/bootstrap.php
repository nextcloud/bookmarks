<?php

define('PHPUNIT_RUN', 1);

require_once __DIR__ . '/../../../lib/base.php';

if (!class_exists('PHPUnit_Framework_TestCase')) {
	require_once('PHPUnit/Autoload.php');
}

\OC::$loader->addValidRoot(OC::$SERVERROOT . '/tests');
\OC_App::loadApp('bookmarks');

OC_Hook::clear();
