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

		$container = $this->getContainer();

		/**
		 * Controllers
		 * @param IContainer $c The Container instance that handles the request
		 */
		$container->registerService('WebViewController', function ($c) {
			/** @var IUser|null $user */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = is_null($user) ? null : $user->getUID();

			/** @var IContainer $c */
			return new WebViewController(
				$c->query('AppName'),
				$c->query('Request'),
				$uid,
				$c->query('ServerContainer')->getURLGenerator(),
				$c->query('ServerContainer')->query(Bookmarks::class),
				$c->query('ServerContainer')->getEventDispatcher()
			);
		});

		$container->registerService('BookmarkController', function ($c) {
			/** @var IContainer $c */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = is_null($user) ? null : $user->getUID();
			return new BookmarkController(
				$c->query('AppName'),
				$c->query('Request'),
				$uid,
				$c->query('ServerContainer')->getL10NFactory()->get('bookmarks'),
				$c->query('ServerContainer')->query(BookmarkMapper::class),
				$c->query('ServerContainer')->query(TagMapper::class),
				$c->query('ServerContainer')->query(FolderMapper::class),
				$c->query('ServerContainer')->getUserManager(),
				$c->query('ServerContainer')->query(BookmarkPreviewer::class),
				$c->query('ServerContainer')->query(FaviconPreviewer::class),
				$c->query('ServerContainer')->query(ITimeFactory::class),
				$c->query('ServerContainer')->getLogger(),
				$c->query('ServerContainer')->getUserSession(),
				$c->query('ServerContainer')->query(IURLGenerator::class),
				$c->query('ServerContainer')->query(HtmlImporter::class),
				$c->query('ServerContainer')->query(HtmlExporter::class)
			);
		});

		$container->registerService('InternalBookmarkController', function ($c) {
			/** @var IContainer $c */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = is_null($user) ? null : $user->getUID();

			return new InternalBookmarkController(
				$c->query('AppName'),
				$c->query('Request'),
				$uid,
				$c->query('ServerContainer')->query(BookmarkController::class)
			);
		});

		$container->registerService('TagsController', function ($c) {
			/** @var IContainer $c */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = is_null($user) ? null : $user->getUID();
			return new TagsController(
				$c->query('AppName'),
				$c->query('Request'),
				$uid,
				$c->query('ServerContainer')->query(TagMapper::class)
			);
		});

		$container->registerService('InternalTagsController', function ($c) {
			/** @var IContainer $c */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = is_null($user) ? null : $user->getUID();
			return new InternalTagsController(
				$c->query('AppName'),
				$c->query('Request'),
				$uid,
				$c->query('ServerContainer')->query(TagsController::class)
			);
		});

		$container->registerService('FoldersController', function ($c) {
			/** @var IContainer $c */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = is_null($user) ? null : $user->getUID();
			return new FoldersController(
				$c->query('AppName'),
				$c->query('Request'),
				$uid,
				$c->query('ServerContainer')->query(FolderMapper::class),
				$c->query('ServerContainer')->query(BookmarkMapper::class)
			);
		});

		$container->registerService('InternalFoldersController', function ($c) {
			/** @var IContainer $c */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = is_null($user) ? null : $user->getUID();
			return new InternalFoldersController(
				$c->query('AppName'),
				$c->query('Request'),
				$uid,
				$c->query(FoldersController::class)
			);
		});

		$container->registerService('SettingsController', function ($c) {
			/** @var IContainer $c */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = is_null($user) ? null : $user->getUID();
			return new SettingsController(
				$c->query('AppName'),
				$c->query('Request'),
				$uid,
				$c->query('ServerContainer')->getConfig()
			);
		});
	}
}
