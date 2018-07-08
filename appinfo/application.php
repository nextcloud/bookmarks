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

use OCA\Bookmarks\Controller\Lib\Bookmarks;
use OCA\Bookmarks\Controller\Lib\ImageService;
use OCA\Bookmarks\Controller\Lib\FaviconService;
use \OCP\AppFramework\App;
use OCP\AppFramework\Utility\ITimeFactory;
use \OCP\IContainer;
use \OCA\Bookmarks\Controller\WebViewController;
use OCA\Bookmarks\Controller\Rest\TagsController;
use OCA\Bookmarks\Controller\Rest\BookmarkController;
use OCA\Bookmarks\Controller\Rest\InternalTagsController;
use OCA\Bookmarks\Controller\Rest\InternalBookmarkController;
use OCA\Bookmarks\Controller\Rest\PublicController;
use OCA\Bookmarks\Controller\Rest\SettingsController;
use OCP\IUser;

class Application extends App {

	public function __construct(array $urlParams = array()) {
		parent::__construct('bookmarks', $urlParams);

		$container = $this->getContainer();

		/**
		 * Controllers
		 * @param IContainer $c The Container instance that handles the request
		 */
		$container->registerService('WebViewController', function($c) {
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

		$container->registerService('BookmarkController', function($c) {
			/** @var IContainer $c */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = is_null($user) ? null : $user->getUID();
			return new BookmarkController(
				$c->query('AppName'),
				$c->query('Request'),
				$uid,
				$c->query('ServerContainer')->getDatabaseConnection(),
				$c->query('ServerContainer')->getL10NFactory()->get('bookmarks'),
				$c->query('ServerContainer')->query(Bookmarks::class),
				$c->query('ServerContainer')->getUserManager()
			);
		});

		$container->registerService('InternalBookmarkController', function($c) {
			/** @var IContainer $c */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = is_null($user) ? null : $user->getUID();

			return new InternalBookmarkController(
				$c->query('AppName'),
				$c->query('Request'),
				$uid,
				$c->query('ServerContainer')->getDatabaseConnection(),
				$c->query('ServerContainer')->getL10NFactory()->get('bookmarks'),
				$c->query('ServerContainer')->query(Bookmarks::class),
				$c->query('ServerContainer')->getUserManager(),
				$c->query('ServerContainer')->query(ImageService::class),
				$c->query('ServerContainer')->query(FaviconService::class),
				$c->query('ServerContainer')->query(ITimeFactory::class)
			);
		});

		$container->registerService('TagsController', function($c) {
			/** @var IContainer $c */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = is_null($user) ? null : $user->getUID();
			return new TagsController(
				$c->query('AppName'),
				$c->query('Request'),
				$uid,
				$c->query('ServerContainer')->query(Bookmarks::class)
			);
		});

		$container->registerService('InternalTagsController', function($c) {
			/** @var IContainer $c */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = is_null($user) ? null : $user->getUID();
			return new InternalTagsController(
				$c->query('AppName'),
				$c->query('Request'),
				$uid,
				$c->query('ServerContainer')->query(Bookmarks::class)
			);
		});

		$container->registerService('PublicController', function($c) {
			/** @var IContainer $c */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = is_null($user) ? null : $user->getUID();
			return new PublicController(
				$c->query('AppName'),
				$c->query('Request'),
				$uid,
				$c->query('ServerContainer')->query(Bookmarks::class),
				$c->query('ServerContainer')->getUserManager()
			);
		});

		$container->registerService('SettingsController', function($c) {
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

		$container->registerService('RecreateAllBookmarks', function($c) {
				/** @var IContainer $c*/
				return new RecreateAllBookmarks(
					$c->query('ServerContainer')->getDb(),
					$c->query('ServerContainer')->query(Bookmarks::class),
					$c->query('ServerContainer')->getConfig()
				);
		});

	}

}
