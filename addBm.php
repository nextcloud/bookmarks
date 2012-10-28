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

require_once 'bookmarksHelper.php';

// Prep screen if we come from the bookmarklet
$url ='';
if(isset($_GET['url'])) {
	$url = $_GET['url'];
}
if(!isset($_GET['title']) || trim($_GET['title']) == '') {
	$datas = getURLMetadata($url);
	$title = isset($datas['title']) ? $datas['title'] : '';
}
else{
	$title = $_GET['title'];
}


OCP\Util::addscript('bookmarks/3rdparty', 'tag-it');
OCP\Util::addscript('bookmarks', 'addBm');
OCP\Util::addStyle('bookmarks', 'bookmarks');
OCP\Util::addStyle('bookmarks/3rdparty', 'jquery.tagit');



$bm = array('title'=> $title,
	'url'=> $url,
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

$tmpl = new OCP\Template( 'bookmarks', 'addBm', 'base' );
$tmpl->assign('requesttoken', OC_Util::callRegister());
$tmpl->assign('bookmark', $bm);
$tmpl->assign('tags', json_encode($tags), false);
$tmpl->printPage();
