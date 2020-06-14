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

use OCA\Bookmarks\Activity\ActivityPublisher;
use OCA\Bookmarks\Events\BeforeDeleteEvent;
use OCA\Bookmarks\Events\CreateEvent;
use OCA\Bookmarks\Events\MoveEvent;
use OCA\Bookmarks\Events\UpdateEvent;
use OCA\Bookmarks\Hooks\UserGroupListener;
use OCA\Bookmarks\Hooks\UserHooks;
use OCA\Bookmarks\Service\HashManager;
use OCP\AppFramework\App;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IContainer;
use OCP\IUser;
use OCP\User\Events\BeforeUserDeletedEvent;

class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('bookmarks', $urlParams);

		$container = $this->getContainer();

		$container->registerService('UserId', static function ($c) {
			/** @var IUser|null $user */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			/** @var IContainer $c */
			return $user === null ? null : $user->getUID();
		});

		$container->registerService('request', static function ($c) {
			return $c->query('Request');
		});

		$dispatcher = $this->getContainer()->query(IEventDispatcher::class);

		$dispatcher->addServiceListener(CreateEvent::class, HashManager::class);
		$dispatcher->addServiceListener(UpdateEvent::class, HashManager::class);
		$dispatcher->addServiceListener(BeforeDeleteEvent::class, HashManager::class);
		$dispatcher->addServiceListener(MoveEvent::class, HashManager::class);

		$dispatcher->addServiceListener(CreateEvent::class, ActivityPublisher::class);
		$dispatcher->addServiceListener(UpdateEvent::class, ActivityPublisher::class);
		$dispatcher->addServiceListener(BeforeDeleteEvent::class, ActivityPublisher::class);
		$dispatcher->addServiceListener(MoveEvent::class, ActivityPublisher::class);

		$dispatcher->addServiceListener(BeforeUserDeletedEvent::class, UserGroupListener::class);
		$dispatcher->addServiceListener(UserAddedEvent::class, UserGroupListener::class);
		$dispatcher->addServiceListener(UserRemovedEvent::class, UserGroupListener::class);
	}
}
