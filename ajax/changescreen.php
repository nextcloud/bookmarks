<?php

OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('bookmarks');
if(isset($_POST['view'])) {
	$view = $_POST['view'];
	switch($view){
		case 'list':
		case 'image';
			break;
		default:
			OCP\JSON::error(array('message'=>'unexspected parameter: ' . $view));
			exit;
	}
	OCP\Config::setUserValue(OCP\USER::getUser(), 'bookmarks', 'currentview', $view);
	OCP\JSON::success();
}elseif(isset($_POST['sidebar'])) {
	$view = $_POST['sidebar'];
	switch($view){
		case 'true':
		case 'false';
			break;
		default:
			OCP\JSON::error(array('message'=>'unexspected parameter: ' . $view));
			exit;
	}
	OCP\Config::setUserValue(OCP\USER::getUser(), 'bookmarks', 'sidebar', $view);
	OCP\JSON::success();
}
