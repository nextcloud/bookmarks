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
	public const APP_ID = 'bookmarks';

	public function __construct() {
		parent::__construct(self::APP_ID);

		$container = $this->getContainer();
		$server = $container->getServer();

		$container->registerService('UserId', static function ($c) {
			/** @var IUser|null $user */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			/** @var IContainer $c */
			return $user === null ? null : $user->getUID();
		});

		$container->registerService('request', static function ($c) {
			return $c->query('Request');
		});

		/** @var IEventDispatcher $eventDispatcher */
		$eventDispatcher = $server->query(IEventDispatcher::class);


		$eventDispatcher->addServiceListener(CreateEvent::class, HashManager::class);
		$eventDispatcher->addServiceListener(UpdateEvent::class, HashManager::class);
		$eventDispatcher->addServiceListener(BeforeDeleteEvent::class, HashManager::class);
		$eventDispatcher->addServiceListener(MoveEvent::class, HashManager::class);

		$eventDispatcher->addServiceListener(CreateEvent::class, ActivityPublisher::class);
		$eventDispatcher->addServiceListener(UpdateEvent::class, ActivityPublisher::class);
		$eventDispatcher->addServiceListener(BeforeDeleteEvent::class, ActivityPublisher::class);
		$eventDispatcher->addServiceListener(MoveEvent::class, ActivityPublisher::class);

		$eventDispatcher->addServiceListener(BeforeUserDeletedEvent::class, UserGroupListener::class);
		$eventDispatcher->addServiceListener(UserAddedEvent::class, UserGroupListener::class);
		$eventDispatcher->addServiceListener(UserRemovedEvent::class, UserGroupListener::class);
	}
}
