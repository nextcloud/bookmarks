<?php

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::callCheck();
OCP\JSON::checkAppEnabled('bookmarks');

$l = new OC_l10n('bookmarks');
if(empty($_FILES)) {
	 OCP\Util::writeLog('bookmarks',"No file provided for import", \OCP\Util::WARN);
	$error[]= $l->t('No file provided for import');
}elseif (isset($_FILES['bm_import'])) {
	$file = $_FILES['bm_import']['tmp_name'];
	if($_FILES['bm_import']['type'] =='text/html')	{
		$error = OC_Bookmarks_Bookmarks::importFile($file);
		if( empty($errors) ) {
			OCP\JSON::success();
			//force charset as not set by OC_JSON
			header('Content-Type: application/json; charset=utf-8');
			exit();
		}
	} else {
		$error[]= $l->t('Unsupported file type for import');
	}
}

OC_JSON::error(array('data'=>$error));
//force charset as not set by OC_JSON
header('Content-Type: application/json; charset=utf-8');
exit();
