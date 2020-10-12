<?php

/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\AppInfo;

use OCA\Bookmarks\Activity\ActivityPublisher;
use OCA\Bookmarks\Dashboard\Widget;
use OCA\Bookmarks\Events\BeforeDeleteEvent;
use OCA\Bookmarks\Events\CreateEvent;
use OCA\Bookmarks\Events\MoveEvent;
use OCA\Bookmarks\Events\UpdateEvent;
use OCA\Bookmarks\Flow\CreateBookmark;
use OCA\Bookmarks\Hooks\UserGroupListener;
use OCA\Bookmarks\Search\Provider;
use OCA\Bookmarks\Service\HashManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\User\Events\BeforeUserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'bookmarks';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		@include_once __DIR__ . '/../../vendor/autoload.php';

		$context->registerService('UserId', static function ($c) {
			/** @var IUser|null $user */
			$user = $c->get(IUserSession::class)->getUser();
			return $user === null ? null : $user->getUID();
		});

		$context->registerService('request', static function ($c) {
			return $c->get(IRequest::class);
		});

		$context->registerSearchProvider(Provider::class);
		$context->registerDashboardWidget(Widget::class);

		$context->registerEventListener(CreateEvent::class, HashManager::class);
		$context->registerEventListener(UpdateEvent::class, HashManager::class);
		$context->registerEventListener(BeforeDeleteEvent::class, HashManager::class);
		$context->registerEventListener(MoveEvent::class, HashManager::class);

		$context->registerEventListener(CreateEvent::class, ActivityPublisher::class);
		$context->registerEventListener(UpdateEvent::class, ActivityPublisher::class);
		$context->registerEventListener(BeforeDeleteEvent::class, ActivityPublisher::class);
		$context->registerEventListener(MoveEvent::class, ActivityPublisher::class);

		$context->registerEventListener(BeforeUserDeletedEvent::class, UserGroupListener::class);
		$context->registerEventListener(UserAddedEvent::class, UserGroupListener::class);
		$context->registerEventListener(UserRemovedEvent::class, UserGroupListener::class);
	}

	public function boot(IBootContext $context): void {
		$container = $context->getServerContainer();
		CreateBookmark::register($container->get(IEventDispatcher::class));
	}
}
