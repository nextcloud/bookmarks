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

function getDomainWithoutExt($name){
    $pos = strripos($name, '.');
    if($pos === false){
        return $name;
    }else{
        return substr($name, 0, $pos);
    }
}

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
$bookmarks = OC_Bookmarks_Bookmarks::findBookmarks(0, 'id', array(), true, -1);
foreach($bookmarks as $bm) {
	$title = $bm['title'];
	if(trim($title) ===''){
		$url_parts = parse_url($bm['url']);
		$title = isset($url_parts['host']) ? getDomainWithoutExt($url_parts['host']) : $bm['url'];
	}
	$file .= '<DT><A HREF="'.$bm['url'].'" TAGS="'.implode(',', $bm['tags']).'">';
	$file .= htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</A>';
	if($bm['description'])
		$file .= '<DD>'.htmlspecialchars($bm['description'], ENT_QUOTES, 'UTF-8');
}
$export_name = "owncloud-bookmarks-".date('Y-m-d').'.html';
header("Cache-Control: private");
header("Content-Type: application/stream");
header("Content-Length: ".$fileSize);
header("Content-Disposition: attachment; filename=".$export_name);

echo $file;
exit;