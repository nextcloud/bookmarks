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

$application->registerRoutes($this, ['routes' => [
	//Web Template Route
	['name' => 'web_view#index', 'url' => '/', 'verb' => 'GET'],
	['name' => 'web_view#bookmarklet', 'url' => '/bookmarklet', 'verb' => 'GET'],
	//internal REST API
	['name' => 'internal_bookmark#get_bookmarks', 'url' => '/bookmark', 'verb' => 'GET'],
	['name' => 'internal_bookmark#new_bookmark', 'url' => '/bookmark', 'verb' => 'POST'],
	['name' => 'internal_bookmark#click_bookmark', 'url' => '/bookmark/click', 'verb' => 'POST'],
	['name' => 'internal_bookmark#export_bookmark', 'url' => '/bookmark/export', 'verb' => 'GET'],
	['name' => 'internal_bookmark#import_bookmark', 'url' => '/bookmark/import', 'verb' => 'POST'],
	['name' => 'internal_bookmark#edit_bookmark', 'url' => '/bookmark/{id}', 'verb' => 'PUT'],
	['name' => 'internal_bookmark#get_single_bookmark', 'url' => '/bookmark/{id}', 'verb' => 'GET'],
	['name' => 'internal_bookmark#delete_bookmark', 'url' => '/bookmark/{id}', 'verb' => 'DELETE'],
	['name' => 'internal_bookmark#delete_all_bookmarks', 'url' => '/bookmark', 'verb' => 'DELETE'],
	['name' => 'internal_bookmark#get_bookmark_image', 'url' => '/bookmark/{id}/image', 'verb' => 'GET'],
	['name' => 'internal_bookmark#get_bookmark_favicon', 'url' => '/bookmark/{id}/favicon', 'verb' => 'GET'],
	['name' => 'internal_tags#full_tags', 'url' => '/tag', 'verb' => 'GET'],
	['name' => 'internal_tags#rename_tag', 'url' => '/tag', 'verb' => 'POST'],
	['name' => 'internal_tags#delete_tag', 'url' => '/tag', 'verb' => 'DELETE'],
	['name' => 'internal_tags#rename_tag', 'url' => '/tag/{old_name}', 'verb' => 'POST'],
	['name' => 'internal_tags#rename_tag', 'url' => '/tag/{old_name}', 'verb' => 'PUT'],
	['name' => 'internal_tags#delete_tag', 'url' => '/tag/{old_name}', 'verb' => 'DELETE'],
	['name' => 'internal_folders#get_folders', 'url' => '/foders', 'verb' => 'GET'],
	['name' => 'internal_folders#get_folder', 'url' => '/foders/{folderId}', 'verb' => 'GET'],
	['name' => 'internal_folders#add_folder', 'url' => '/foders', 'verb' => 'POST'],
	['name' => 'internal_folders#edit_folder', 'url' => '/folders/{folderId}', 'verb' => 'PUT'],
	['name' => 'internal_folders#delete_folder', 'url' => '/folders/{folderId}', 'verb' => 'DELETE'],
	['name' => 'internal_folders#add_to_folder', 'url' => '/folders/{folderId}/bookmarks/{bookmarkId}', 'verb' => 'POST'],
	['name' => 'internal_folders#remove_from_folder', 'url' => '/folders/{folderId}/bookmarks/{bookmarkId}', 'verb' => 'DELETE'],
	// Public REST API
	['name' => 'bookmark#get_bookmarks', 'url' => '/public/rest/v2/bookmark', 'verb' => 'GET'],
	['name' => 'bookmark#new_bookmark', 'url' => '/public/rest/v2/bookmark', 'verb' => 'POST'],
	['name' => 'bookmark#click_bookmark', 'url' => '/public/rest/v2/bookmark/click', 'verb' => 'POST'],
	['name' => 'bookmark#export_bookmark', 'url' => '/public/rest/v2/bookmark/export', 'verb' => 'GET'],
	['name' => 'bookmark#import_bookmark', 'url' => '/public/rest/v2/bookmark/import', 'verb' => 'POST'],
	['name' => 'bookmark#get_single_bookmark', 'url' => '/public/rest/v2/bookmark/{id}', 'verb' => 'GET'],
	['name' => 'bookmark#edit_bookmark', 'url' => '/public/rest/v2/bookmark/{id}', 'verb' => 'PUT'],
	['name' => 'bookmark#delete_bookmark', 'url' => '/public/rest/v2/bookmark/{id}', 'verb' => 'DELETE'],
	['name' => 'tags#full_tags', 'url' => '/public/rest/v2/tag', 'verb' => 'GET'],
	['name' => 'tags#rename_tag', 'url' => '/public/rest/v2/tag', 'verb' => 'POST'],
	['name' => 'tags#delete_tag', 'url' => '/public/rest/v2/tag', 'verb' => 'DELETE'],
	['name' => 'folders#get_folders', 'url' => '/public/rest/v2/foders', 'verb' => 'GET'],
	['name' => 'folders#get_folder', 'url' => '/public/rest/v2/foders/{folderId}', 'verb' => 'GET'],
	['name' => 'folders#add_folder', 'url' => '/public/rest/v2/foders', 'verb' => 'POST'],
	['name' => 'folders#edit_folder', 'url' => '/public/rest/v2/folders/{folderId}', 'verb' => 'PUT'],
	['name' => 'folders#delete_folder', 'url' => '/public/rest/v2/folders/{folderId}', 'verb' => 'DELETE'],
	['name' => 'folders#add_to_folder', 'url' => '/public/rest/v2/folders/{folderId}/bookmarks/{bookmarkId}', 'verb' => 'POST'],
	['name' => 'folders#remove_from_folder', 'url' => '/public/rest/v2/folders/{folderId}/bookmarks/{bookmarkId}', 'verb' => 'DELETE'],
	['name' => 'bookmark#preflighted_cors', 'url' => '/public/rest/v2/{path}',
		'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],
	//Settings
	['name' => 'settings#set_sorting', 'url' => '/settings/sort', 'verb' => 'POST'],
	['name' => 'settings#get_sorting', 'url' => '/settings/sort', 'verb' => 'GET'],
	// Legacy Routes
	['name' => 'public#return_as_json', 'url' => '/public/rest/v1/bookmark', 'verb' => 'GET'],

	['name' => 'bookmark#legacy_get_bookmarks', 'url' => '/ajax/updateList.php', 'verb' => 'POST'],
	['name' => 'bookmark#legacy_edit_bookmark', 'url' => '/ajax/editBookmark.php', 'verb' => 'POST'],
	['name' => 'bookmark#legacy_delete_bookmark', 'url' => '/ajax/delBookmark.php', 'verb' => 'POST'],
]]);
