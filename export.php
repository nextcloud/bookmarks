<?php
/**
 * Copyright (c) 2012 Brice Maron < brice __At__ bmaron dot net >
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

// Check if we are a user
OCP\User::checkLoggedIn();
OCP\App::checkAppEnabled('bookmarks');


$file = <<<EOT
<!DOCTYPE NETSCAPE-Bookmark-file-1>
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
<!-- This is an automatically generated file.
It will be read and overwritten.
Do Not Edit! -->
<TITLE>Bookmarks</TITLE>
<H1>Bookmarks</H1>
<DL><p>
EOT;

$bookmarks = OC_Bookmarks_Bookmarks::findBookmarks(0, 'id', array(), true,-1);
foreach($bookmarks as $bm) {
	$file .= '<DT><A HREF="'.$bm['url'].'" TAGS="'.implode(',',$bm['tags']).'">'.htmlspecialchars($bm['title'], ENT_QUOTES, 'UTF-8').'</A>';
	if($bm['description'])
		$file .= '<DD>'.htmlspecialchars($bm['description'], ENT_QUOTES, 'UTF-8');
}

echo $file;