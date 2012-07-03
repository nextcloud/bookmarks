<?php
/**
 * Copyright (c) 2011 Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

OCP\App::checkAppEnabled('bookmarks');

if (isset($_POST['bm_import'])) {
	$error = array();

	$file = $_FILES['bm_import']['tmp_name'];
	if($_FILES['bm_import']['type'] =='text/html')	{
		$error = OC_Bookmarks_Bookmarks::importFile($file);

	} else {
		$error[]= array('error' => 'Unsupported file type for import',
			'hint' => '');
	}

	// Any problems?
	if(count($error)){
		$tmpl = new OCP\Template('bookmarks', 'settings');
		$tmpl->assign('error',$error);
			//return $tmpl->fetchPage();
	} else {
		// Went swimmingly!
		$tmpl = new OCP\Template('bookmarks', 'settings');
			//return $tmpl->fetchPage();
	}
} else {
	$tmpl = new OCP\Template( 'bookmarks', 'settings');
	return $tmpl->fetchPage();
}
