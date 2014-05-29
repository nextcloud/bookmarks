<?php

/**
* ownCloud - bookmarks plugin
*
* @author Brice Maron
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
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('bookmarks');

$req_type=isset($_GET['type']) ? $_GET['type'] : '';

if($req_type == 'url_info' && $_GET['url']) {
	$datas = OC_Bookmarks_Bookmarks::getURLMetadata($_GET['url']);
	$title = isset($datas['title']) ? $datas['title'] : '';
	OCP\JSON::success(array('title' => $title));
	exit();
}

OC_JSON::error();
exit();
