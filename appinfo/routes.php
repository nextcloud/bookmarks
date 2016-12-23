<?php

/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Stefan Klemm <mail@stefan-klemm.de>
 * @copyright (c) 2014, Stefan Klemm
 */

namespace OCA\Bookmarks\AppInfo;

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
$application = new Application();

$application->registerRoutes($this, array('routes' => array(
		//Web Template Route
		array('name' => 'web_view#index', 'url' => '/', 'verb' => 'GET'),
		array('name' => 'web_view#bookmarklet', 'url' => '/bookmarklet', 'verb' => 'GET'),
		//Session Based and CSRF secured Routes
		array('name' => 'bookmark#get_bookmarks', 'url' => '/bookmark', 'verb' => 'GET'),
		array('name' => 'bookmark#new_bookmark', 'url' => '/bookmark', 'verb' => 'POST'),
		array('name' => 'bookmark#edit_bookmark', 'url' => '/bookmark/{id}', 'verb' => 'PUT'),
		array('name' => 'bookmark#delete_bookmark', 'url' => '/bookmark/{id}', 'verb' => 'DELETE'),
		array('name' => 'bookmark#click_bookmark', 'url' => '/bookmark/click', 'verb' => 'POST'),
		array('name' => 'bookmark#export_bookmark', 'url' => '/bookmark/export', 'verb' => 'GET'),
		array('name' => 'bookmark#import_bookmark', 'url' => '/bookmark/import', 'verb' => 'POST'),
		array('name' => 'tags#full_tags', 'url' => '/tag', 'verb' => 'GET'),
		array('name' => 'tags#rename_tag', 'url' => '/tag', 'verb' => 'POST'),
		array('name' => 'tags#delete_tag', 'url' => '/tag', 'verb' => 'DELETE'),
		//Public Rest Api
		array('name' => 'public#return_as_json', 'url' => '/public/rest/v1/bookmark', 'verb' => 'GET'),
		array('name' => 'public#new_bookmark', 'url' => '/public/rest/v1/bookmark', 'verb' => 'POST'),
		array('name' => 'public#edit_bookmark', 'url' => '/public/rest/v1/bookmark/{id}', 'verb' => 'PUT'),
		array('name' => 'public#delete_bookmark', 'url' => '/public/rest/v1/bookmark/{id}', 'verb' => 'DELETE'),
		//Legacy Routes
		array('name' => 'bookmark#legacy_get_bookmarks', 'url' => '/ajax/updateList.php', 'verb' => 'POST'),
		array('name' => 'bookmark#legacy_edit_bookmark', 'url' => '/ajax/editBookmark.php', 'verb' => 'POST'),
		array('name' => 'bookmark#legacy_delete_bookmark', 'url' => '/ajax/delBookmark.php', 'verb' => 'POST'),
)));
