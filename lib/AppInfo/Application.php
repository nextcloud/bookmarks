<?php

/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Stefan Klemm <mail@stefan-klemm.de>
 * @copyright (c) 2011, Marvin Thomas Rabe
 * @copyright (c) 2011, Arthur Schiwon
 * @copyright (c) 2014, Stefan Klemm
 */

namespace OCA\Bookmarks\AppInfo;

use OCA\Bookmarks\Db\FolderMapper;
use OCP\AppFramework\App;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IContainer;
use OCP\IUser;

class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('bookmarks', $urlParams);

		$container = $this->getContainer();

		$container->registerService('UserId', function ($c) {
			/** @var IUser|null $user */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			/** @var IContainer $c */
			return is_null($user) ? null : $user->getUID();
		});

		$container->registerService('request', function ($c) {
			return $c->query('Request');
		});

		$dispatcher = $this->getContainer()->query(IEventDispatcher::class);
		$dispatcher->addServiceListener('\OCA\Bookmarks::onBookmarkDelete', FolderMapper::class);
		$dispatcher->addServiceListener('\OCA\Bookmarks::onBookmarkUpdate', FolderMapper::class);
		$dispatcher->addServiceListener('\OCA\Bookmarks::onBookmarkCreate', FolderMapper::class);
	}
}
