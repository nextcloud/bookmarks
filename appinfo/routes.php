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
return [
	'routes' => [
		//Web Template Route
		['name' => 'web_view#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'web_view#index', 'url' => '/recent', 'verb' => 'GET', 'postfix' => 'recent'],
		['name' => 'web_view#index', 'url' => '/search/{search}', 'verb' => 'GET', 'postfix' => 'search'],
		['name' => 'web_view#index', 'url' => '/folders/{folder}', 'verb' => 'GET', 'postfix' => 'folder'],
		['name' => 'web_view#index', 'url' => '/bookmarks/{bookmark}', 'verb' => 'GET', 'postfix' => 'bookmark'],
		['name' => 'web_view#index', 'url' => '/tags/{tags}', 'verb' => 'GET', 'postfix' => 'tags'],
		['name' => 'web_view#index', 'url' => '/untagged', 'verb' => 'GET', 'postfix' => 'untagged'],
		['name' => 'web_view#index', 'url' => '/unavailable', 'verb' => 'GET', 'postfix' => 'unavailable'],
		['name' => 'web_view#index', 'url' => '/archived', 'verb' => 'GET', 'postfix' => 'archived'],
		['name' => 'web_view#index', 'url' => '/deleted', 'verb' => 'GET', 'postfix' => 'deleted'],
		['name' => 'web_view#index', 'url' => '/bookmarklet', 'verb' => 'GET', 'postfix' => 'bookmarklet'],
		['name' => 'web_view#service_worker', 'url' => '/service-worker.js', 'verb' => 'GET'],
		['name' => 'web_view#manifest', 'url' => '/manifest.webmanifest', 'verb' => 'GET'],

		//internal REST API
		['name' => 'internal_bookmark#get_bookmarks', 'url' => '/bookmark', 'verb' => 'GET'],
		['name' => 'internal_bookmark#new_bookmark', 'url' => '/bookmark', 'verb' => 'POST'],
		['name' => 'internal_bookmark#click_bookmark', 'url' => '/bookmark/click', 'verb' => 'POST'],
		['name' => 'internal_bookmark#export_bookmark', 'url' => '/bookmark/export', 'verb' => 'GET'],
		['name' => 'internal_bookmark#import_bookmark', 'url' => '/bookmark/import', 'verb' => 'POST'],
		['name' => 'internal_bookmark#count_unavailable', 'url' => '/bookmark/unavailable', 'verb' => 'GET'],
		['name' => 'internal_bookmark#count_archived', 'url' => '/bookmark/archived', 'verb' => 'GET'],
		['name' => 'internal_bookmark#count_deleted', 'url' => '/bookmark/deleted', 'verb' => 'GET'],
		['name' => 'internal_bookmark#edit_bookmark', 'url' => '/bookmark/{id}', 'verb' => 'PUT'],
		['name' => 'internal_bookmark#get_single_bookmark', 'url' => '/bookmark/{id}', 'verb' => 'GET'],
		['name' => 'internal_bookmark#delete_bookmark', 'url' => '/bookmark/{id}', 'verb' => 'DELETE'],
		['name' => 'internal_bookmark#delete_bookmark_permanently', 'url' => '/bookmark/permanent/{id}', 'verb' => 'DELETE'],
		['name' => 'internal_bookmark#restore_bookmark', 'url' => '/bookmark/restore/{id}', 'verb' => 'PUT'],
		['name' => 'internal_bookmark#delete_all_bookmarks', 'url' => '/bookmark', 'verb' => 'DELETE'],
		['name' => 'internal_bookmark#get_bookmark_image', 'url' => '/bookmark/{id}/image', 'verb' => 'GET'],
		['name' => 'internal_bookmark#get_bookmark_favicon', 'url' => '/bookmark/{id}/favicon', 'verb' => 'GET'],
		['name' => 'internal_bookmark#count_bookmarks', 'url' => '/folder/{folder}/count', 'verb' => 'GET'],
		['name' => 'internal_tags#full_tags', 'url' => '/tag', 'verb' => 'GET'],
		['name' => 'internal_tags#rename_tag', 'url' => '/tag', 'verb' => 'POST'],
		['name' => 'internal_tags#delete_tag', 'url' => '/tag', 'verb' => 'DELETE'],
		['name' => 'internal_tags#rename_tag', 'url' => '/tag/{old_name}', 'verb' => 'POST'],
		['name' => 'internal_tags#rename_tag', 'url' => '/tag/{old_name}', 'verb' => 'PUT'],
		['name' => 'internal_tags#delete_tag', 'url' => '/tag/{old_name}', 'verb' => 'DELETE'],
		['name' => 'internal_folders#get_folders', 'url' => '/folder', 'verb' => 'GET'],
		['name' => 'internal_folders#get_folder', 'url' => '/folder/{folderId}', 'verb' => 'GET'],
		['name' => 'internal_folders#add_folder', 'url' => '/folder', 'verb' => 'POST'],
		['name' => 'internal_folders#edit_folder', 'url' => '/folder/{folderId}', 'verb' => 'PUT'],
		['name' => 'internal_folders#delete_folder', 'url' => '/folder/{folderId}', 'verb' => 'DELETE'],
		['name' => 'internal_folders#hash_folder', 'url' => '/folder/{folderId}/hash', 'verb' => 'GET'],
		['name' => 'internal_bookmark#import_bookmark', 'url' => '/folder/{folder}/import', 'verb' => 'POST'],
		['name' => 'internal_folders#get_folder_children', 'url' => '/folder/{folderId}/children', 'verb' => 'GET'],
		['name' => 'internal_folders#get_folder_children_order', 'url' => '/folder/{folderId}/childorder', 'verb' => 'GET'],
		['name' => 'internal_folders#set_folder_children_order', 'url' => '/folder/{folderId}/childorder', 'verb' => 'PATCH'],
		['name' => 'internal_folders#add_to_folder', 'url' => '/folder/{folderId}/bookmarks/{bookmarkId}', 'verb' => 'POST'],
		['name' => 'internal_folders#remove_from_folder', 'url' => '/folder/{folderId}/bookmarks/{bookmarkId}', 'verb' => 'DELETE'],
		['name' => 'internal_folders#get_folder_public_token', 'url' => '/folder/{folderId}/publictoken', 'verb' => 'GET'],
		['name' => 'internal_folders#create_folder_public_token', 'url' => '/folder/{folderId}/publictoken', 'verb' => 'POST'],
		['name' => 'internal_folders#delete_folder_public_token', 'url' => '/folder/{folderId}/publictoken', 'verb' => 'DELETE'],
		['name' => 'internal_folders#get_shares', 'url' => '/folder/{folderId}/shares', 'verb' => 'GET'],
		['name' => 'internal_folders#create_share', 'url' => '/folder/{folderId}/shares', 'verb' => 'POST'],
		['name' => 'internal_folders#get_share', 'url' => '/share/{shareId}', 'verb' => 'GET'],
		['name' => 'internal_folders#edit_share', 'url' => '/share/{shareId}', 'verb' => 'PUT'],
		['name' => 'internal_folders#delete_share', 'url' => '/share/{shareId}', 'verb' => 'DELETE'],

		// Public REST API
		['name' => 'bookmark#get_bookmarks', 'url' => '/public/rest/v2/bookmark', 'verb' => 'GET'],
		['name' => 'bookmark#new_bookmark', 'url' => '/public/rest/v2/bookmark', 'verb' => 'POST'],
		['name' => 'bookmark#click_bookmark', 'url' => '/public/rest/v2/bookmark/click', 'verb' => 'POST'],
		['name' => 'bookmark#export_bookmark', 'url' => '/public/rest/v2/bookmark/export', 'verb' => 'GET'],
		['name' => 'bookmark#import_bookmark', 'url' => '/public/rest/v2/bookmark/import', 'verb' => 'POST'],
		['name' => 'bookmark#count_unavailable', 'url' => '/public/rest/v2/bookmark/unavailable', 'verb' => 'GET'],
		['name' => 'bookmark#get_single_bookmark', 'url' => '/public/rest/v2/bookmark/{id}', 'verb' => 'GET'],
		['name' => 'bookmark#edit_bookmark', 'url' => '/public/rest/v2/bookmark/{id}', 'verb' => 'PUT'],
		['name' => 'bookmark#delete_bookmark', 'url' => '/public/rest/v2/bookmark/{id}', 'verb' => 'DELETE'],
		['name' => 'bookmark#get_bookmark_image', 'url' => '/public/rest/v2/bookmark/{id}/image', 'verb' => 'GET'],
		['name' => 'bookmark#get_bookmark_favicon', 'url' => '/public/rest/v2/bookmark/{id}/favicon', 'verb' => 'GET'],
		['name' => 'bookmark#count_bookmarks', 'url' => '/public/rest/v2/folder/{folder}/count', 'verb' => 'GET'],
		['name' => 'tags#full_tags', 'url' => '/public/rest/v2/tag', 'verb' => 'GET'],
		['name' => 'tags#rename_tag', 'url' => '/public/rest/v2/tag', 'verb' => 'POST'],
		['name' => 'tags#delete_tag', 'url' => '/public/rest/v2/tag', 'verb' => 'DELETE'],
		['name' => 'tags#rename_tag', 'url' => '/tag/{old_name}', 'verb' => 'POST'],
		['name' => 'tags#rename_tag', 'url' => '/tag/{old_name}', 'verb' => 'PUT'],
		['name' => 'tags#delete_tag', 'url' => '/tag/{old_name}', 'verb' => 'DELETE'],
		['name' => 'folders#get_folders', 'url' => '/public/rest/v2/folder', 'verb' => 'GET'],
		['name' => 'folders#get_folder', 'url' => '/public/rest/v2/folder/{folderId}', 'verb' => 'GET'],
		['name' => 'folders#add_folder', 'url' => '/public/rest/v2/folder', 'verb' => 'POST'],
		['name' => 'folders#edit_folder', 'url' => '/public/rest/v2/folder/{folderId}', 'verb' => 'PUT'],
		['name' => 'folders#delete_folder', 'url' => '/public/rest/v2/folder/{folderId}', 'verb' => 'DELETE'],
		['name' => 'folders#hash_folder', 'url' => '/public/rest/v2/folder/{folderId}/hash', 'verb' => 'GET'],
		['name' => 'bookmark#import_bookmark', 'url' => '/public/rest/v2/folder/{folder}/import', 'verb' => 'POST'],
		['name' => 'folders#get_folder_children', 'url' => '/public/rest/v2/folder/{folderId}/children', 'verb' => 'GET'],
		['name' => 'folders#get_folder_children_order', 'url' => '/public/rest/v2/folder/{folderId}/childorder', 'verb' => 'GET'],
		['name' => 'folders#set_folder_children_order', 'url' => '/public/rest/v2/folder/{folderId}/childorder', 'verb' => 'PATCH'],
		['name' => 'folders#add_to_folder', 'url' => '/public/rest/v2/folder/{folderId}/bookmarks/{bookmarkId}', 'verb' => 'POST'],
		['name' => 'folders#remove_from_folder', 'url' => '/public/rest/v2/folder/{folderId}/bookmarks/{bookmarkId}', 'verb' => 'DELETE'],
		['name' => 'bookmark#preflighted_cors', 'url' => '/public/rest/v2/{path}',
			'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],
		['name' => 'folders#get_folder_public_token', 'url' => '/public/rest/v2/folder/{folderId}/publictoken', 'verb' => 'GET'],
		['name' => 'folders#create_folder_public_token', 'url' => '/public/rest/v2/folder/{folderId}/publictoken', 'verb' => 'POST'],
		['name' => 'folders#delete_folder_public_token', 'url' => '/public/rest/v2/folder/{folderId}/publictoken', 'verb' => 'DELETE'],
		['name' => 'folders#get_shares', 'url' => '/public/rest/v2/folder/{folderId}/shares', 'verb' => 'GET'],
		['name' => 'folders#create_share', 'url' => '/public/rest/v2/folder/{folderId}/shares', 'verb' => 'POST'],
		['name' => 'folders#get_share', 'url' => '/public/rest/v2/share/{shareId}', 'verb' => 'GET'],
		['name' => 'folders#edit_share', 'url' => '/public/rest/v2/share/{shareId}', 'verb' => 'PUT'],
		['name' => 'folders#delete_share', 'url' => '/public/rest/v2/share/{shareId}', 'verb' => 'DELETE'],

		//Settings
		['name' => 'settings#set_sorting', 'url' => '/settings/sorting', 'verb' => 'POST'],
		['name' => 'settings#get_sorting', 'url' => '/settings/sorting', 'verb' => 'GET'],
		['name' => 'settings#set_view_mode', 'url' => '/settings/viewMode', 'verb' => 'POST'],
		['name' => 'settings#get_view_mode', 'url' => '/settings/viewMode', 'verb' => 'GET'],
		['name' => 'settings#set_archive_path', 'url' => '/settings/archivePath', 'verb' => 'POST'],
		['name' => 'settings#get_archive_path', 'url' => '/settings/archivePath', 'verb' => 'GET'],
		['name' => 'settings#get_limit', 'url' => '/settings/limit', 'verb' => 'GET'],

		# public link web view
		['name' => 'web_view#link', 'url' => '/public/{token}', 'verb' => 'GET'],
	],
];
