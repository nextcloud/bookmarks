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

use OCA\Bookmarks\Events\BeforeDeleteEvent;
use OCA\Bookmarks\Events\CreateEvent;
use OCA\Bookmarks\Events\MoveEvent;
use OCA\Bookmarks\Events\UpdateEvent;
use OCA\Bookmarks\Service\HashManager;
use OCP\AppFramework\App;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IContainer;
use OCP\IUser;

class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('bookmarks', $urlParams);

		$container = $this->getContainer();

		$container->registerService('UserId', static function ($c) {
			/** @var IUser|null $user */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			/** @var IContainer $c */
			return is_null($user) ? null : $user->getUID();
		});

		$container->registerService('request', static function ($c) {
			return $c->query('Request');
		});

		$dispatcher = $this->getContainer()->query(IEventDispatcher::class);
		$dispatcher->addServiceListener(CreateEvent::class, HashManager::class);
		$dispatcher->addServiceListener(UpdateEvent::class, HashManager::class);
		$dispatcher->addServiceListener(BeforeDeleteEvent::class, HashManager::class);
		$dispatcher->addServiceListener(MoveEvent::class, HashManager::class);
	}
}
