<?php

/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 * @author Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Stefan Klemm <mail@stefan-klemm.de>
 * @copyright (c) 2011, Marvin Thomas Rabe
 * @copyright (c) 2011, Arthur Schiwon
 * @copyright (c) 2014, Stefan Klemm
 */

namespace OCA\Bookmarks\AppInfo;

use OCA\Bookmarks\Bookmarks;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\TagMapper;
use OCA\Bookmarks\Previews\DefaultPreviewService;
use OCA\Bookmarks\Previews\FaviconPreviewService;
use OCA\Bookmarks\Previews\ScreenlyPreviewService;
use OCA\Bookmarks\Service\BookmarkPreviewer;
use OCA\Bookmarks\Service\FaviconPreviewer;
use OCA\Bookmarks\Service\HtmlExporter;
use OCA\Bookmarks\Service\HtmlImporter;
use \OCP\AppFramework\App;
use OCP\AppFramework\Utility\ITimeFactory;
use \OCP\IContainer;
use \OCA\Bookmarks\Controller\WebViewController;
use OCA\Bookmarks\Controller\Rest\BookmarkController;
use OCA\Bookmarks\Controller\Rest\InternalBookmarkController;
use OCA\Bookmarks\Controller\Rest\TagsController;
use OCA\Bookmarks\Controller\Rest\InternalTagsController;
use OCA\Bookmarks\Controller\Rest\FoldersController;
use OCA\Bookmarks\Controller\Rest\InternalFoldersController;
use OCA\Bookmarks\Controller\Rest\PublicController;
use OCA\Bookmarks\Controller\Rest\SettingsController;
use OCP\IUser;
use OCP\IURLGenerator;

class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('bookmarks', $urlParams);
	}
}
