<?php

/**
* ownCloud - bookmarks plugin
*
* @author Arthur Schiwon
* @copyright 2011 Arthur Schiwon blizzz@arthur-schiwon.de
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Lesser General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/



// Check if we are a user
OCP\User::checkLoggedIn();
OCP\App::checkAppEnabled('bookmarks');


require_once('bookmarksHelper.php');



if(!isset($_GET['url']) || trim($_GET['url']) == '') {
	header("HTTP/1.0 404 Not Found");
	$tmpl = new OCP\Template( '', '404', 'guest' );
	$tmpl->printPage();
	exit;
}elseif(isset($_POST['url'])) {
	$tags = isset($_POST['item']['tags']) ? $_POST['item']['tags'] : array();
	$pub = isset($_POST['is_public']) ? true : false;
	$bm = addBookmark($_POST['url'], $_POST['title'], implode(',',$tags),$_POST['desc'], $pub);
	OCP\JSON::success(array('id'=>$bm));
	exit();
}
	
OCP\Util::addscript('bookmarks','tag-it');
OCP\Util::addscript('bookmarks','addBm');
OCP\Util::addStyle('bookmarks', 'bookmarks');
OCP\Util::addStyle('bookmarks', 'jquery.tagit');

if(!isset($_GET['title']) || trim($_GET['title']) == '') {
	$datas = getURLMetadata($_GET['url']);
	$title = isset($datas['title']) ? $datas['title'] : '';
}
else{
	$title = $_GET['title'];
}

$bm = array('title'=> $title,
	'url'=> $_GET['url'],
	'tags'=> array(),
	'desc'=>'',
	'is_public'=>0,
);

//Find All Tags
$qtags = OC_Bookmarks_Bookmarks::findTags();
$tags = array();
foreach($qtags as $tag) {
	$tags[] = $tag['tag'];
}

$tmpl = new OCP\Template( 'bookmarks', 'addBm', 'empty' );
$tmpl->assign('bookmark', $bm);
$tmpl->assign('tags', json_encode($tags), false);

$tmpl->printPage();
