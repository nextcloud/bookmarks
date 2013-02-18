<?php
/**
 * Copyright (c) 2013 Lukas Reschke <lukas@statuscode.ch>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

// Set the content type to Javascript
header("Content-type: text/javascript");

// Disallow caching
header("Cache-Control: no-cache, must-revalidate"); 
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 

$qtags = OC_Bookmarks_Bookmarks::findTags(array(), 0, 400);
$tags = array();
foreach($qtags as $tag) {
	$tags[] = $tag['tag'];
}

?>
fullTags = <?php echo json_encode($tags);?>;