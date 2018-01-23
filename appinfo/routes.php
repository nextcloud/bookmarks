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
	//internal REST API
	array('name' => 'internal_bookmark#get_bookmarks', 'url' => '/bookmark', 'verb' => 'GET'),
	array('name' => 'internal_bookmark#new_bookmark', 'url' => '/bookmark', 'verb' => 'POST'),
	array('name' => 'internal_bookmark#click_bookmark', 'url' => '/bookmark/click', 'verb' => 'POST'),
	array('name' => 'internal_bookmark#export_bookmark', 'url' => '/bookmark/export', 'verb' => 'GET'),
	array('name' => 'internal_bookmark#import_bookmark', 'url' => '/bookmark/import', 'verb' => 'POST'),
	array('name' => 'internal_bookmark#edit_bookmark', 'url' => '/bookmark/{id}', 'verb' => 'PUT'),
	array('name' => 'internal_bookmark#get_single_bookmark', 'url' => '/bookmark/{id}', 'verb' => 'GET'),
	array('name' => 'internal_bookmark#delete_bookmark', 'url' => '/bookmark/{id}', 'verb' => 'DELETE'),
	array('name' => 'internal_tags#full_tags', 'url' => '/tag', 'verb' => 'GET'),
	array('name' => 'internal_tags#rename_tag', 'url' => '/tag', 'verb' => 'POST'),
	array('name' => 'internal_tags#delete_tag', 'url' => '/tag', 'verb' => 'DELETE'),
	array('name' => 'internal_tags#rename_tag', 'url' => '/tag/{old_name}', 'verb' => 'POST'),
	array('name' => 'internal_tags#rename_tag', 'url' => '/tag/{old_name}', 'verb' => 'PUT'),
	array('name' => 'internal_tags#delete_tag', 'url' => '/tag/{old_name}', 'verb' => 'DELETE'),
	// Public REST API
	array('name' => 'bookmark#get_bookmarks', 'url' => '/public/rest/v2/bookmark', 'verb' => 'GET'),
	array('name' => 'bookmark#new_bookmark', 'url' => '/public/rest/v2/bookmark', 'verb' => 'POST'),
	array('name' => 'bookmark#click_bookmark', 'url' => '/public/rest/v2/bookmark/click', 'verb' => 'POST'),
	array('name' => 'bookmark#export_bookmark', 'url' => '/public/rest/v2/bookmark/export', 'verb' => 'GET'),
	array('name' => 'bookmark#import_bookmark', 'url' => '/public/rest/v2/bookmark/import', 'verb' => 'POST'),
	array('name' => 'bookmark#get_single_bookmark', 'url' => '/public/rest/v2/bookmark/{id}', 'verb' => 'GET'),
	array('name' => 'bookmark#edit_bookmark', 'url' => '/public/rest/v2/bookmark/{id}', 'verb' => 'PUT'),
	array('name' => 'bookmark#delete_bookmark', 'url' => '/public/rest/v2/bookmark/{id}', 'verb' => 'DELETE'),
	array('name' => 'tags#full_tags', 'url' => '/public/rest/v2/tag', 'verb' => 'GET'),
	array('name' => 'tags#rename_tag', 'url' => '/public/rest/v2/tag', 'verb' => 'POST'),
	array('name' => 'tags#delete_tag', 'url' => '/public/rest/v2/tag', 'verb' => 'DELETE'),
	array('name' => 'bookmark#preflighted_cors', 'url' => '/public/rest/v2/{path}',
		'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']),
	// Legacy Routes
	array('name' => 'public#return_as_json', 'url' => '/public/rest/v1/bookmark', 'verb' => 'GET'),
	
	array('name' => 'bookmark#legacy_get_bookmarks', 'url' => '/ajax/updateList.php', 'verb' => 'POST'),
	array('name' => 'bookmark#legacy_edit_bookmark', 'url' => '/ajax/editBookmark.php', 'verb' => 'POST'),
	array('name' => 'bookmark#legacy_delete_bookmark', 'url' => '/ajax/delBookmark.php', 'verb' => 'POST'),
)));
