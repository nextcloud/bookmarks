<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\AppInfo;

use OCA\Bookmarks\Activity\ActivityPublisher;
use OCA\Bookmarks\ContextChat\ContextChatProvider;
use OCA\Bookmarks\Dashboard\Frequent;
use OCA\Bookmarks\Dashboard\Recent;
use OCA\Bookmarks\Events\BeforeDeleteEvent;
use OCA\Bookmarks\Events\BeforeSoftDeleteEvent;
use OCA\Bookmarks\Events\BeforeSoftUndeleteEvent;
use OCA\Bookmarks\Events\CreateEvent;
use OCA\Bookmarks\Events\InsertEvent;
use OCA\Bookmarks\Events\ManipulateEvent;
use OCA\Bookmarks\Events\MoveEvent;
use OCA\Bookmarks\Events\UpdateEvent;
use OCA\Bookmarks\Flow\CreateBookmark;
use OCA\Bookmarks\Hooks\BeforeTemplateRenderedListener;
use OCA\Bookmarks\Hooks\UsersGroupsCirclesListener;
use OCA\Bookmarks\Middleware\ExceptionMiddleware;
use OCA\Bookmarks\Reference\BookmarkReferenceProvider;
use OCA\Bookmarks\Search\Provider;
use OCA\Bookmarks\Service\TreeCacheManager;
use OCA\ContextChat\Event\ContentProviderRegisterEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'bookmarks';

	public function __construct() {
		parent::__construct(self::APP_ID);

		// TODO move this back to ::register after fixing the autoload issue
		// (and use a listener class)
		$container = $this->getContainer();
		$eventDispatcher = $container->get(IEventDispatcher::class);
		$eventDispatcher->addListener(RenderReferenceEvent::class, function () {
			Util::addScript(self::APP_ID, self::APP_ID . '-references');
		});
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
		$context->registerDashboardWidget(Recent::class);
		$context->registerDashboardWidget(Frequent::class);

		$context->registerReferenceProvider(BookmarkReferenceProvider::class);

		$context->registerEventListener(CreateEvent::class, TreeCacheManager::class);
		$context->registerEventListener(UpdateEvent::class, TreeCacheManager::class);
		$context->registerEventListener(BeforeDeleteEvent::class, TreeCacheManager::class);
		$context->registerEventListener(MoveEvent::class, TreeCacheManager::class);
		$context->registerEventListener(BeforeSoftDeleteEvent::class, TreeCacheManager::class);
		$context->registerEventListener(BeforeSoftUndeleteEvent::class, TreeCacheManager::class);

		$context->registerEventListener(CreateEvent::class, ActivityPublisher::class);
		$context->registerEventListener(UpdateEvent::class, ActivityPublisher::class);
		$context->registerEventListener(BeforeDeleteEvent::class, ActivityPublisher::class);
		$context->registerEventListener(MoveEvent::class, ActivityPublisher::class);

		$context->registerEventListener(BeforeUserDeletedEvent::class, UsersGroupsCirclesListener::class);
		$context->registerEventListener(UserAddedEvent::class, UsersGroupsCirclesListener::class);
		$context->registerEventListener(UserRemovedEvent::class, UsersGroupsCirclesListener::class);
		$context->registerEventListener(BeforeGroupDeletedEvent::class, UsersGroupsCirclesListener::class);
		$context->registerEventListener('\OCA\Circles\Events\CircleMemberAddedEvent', UsersGroupsCirclesListener::class);
		$context->registerEventListener('\OCA\Circles\Events\CircleMemberRemovedEvent', UsersGroupsCirclesListener::class);
		$context->registerEventListener('\OCA\Circles\Events\CircleDestroyedEvent', UsersGroupsCirclesListener::class);

		$context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);

		$context->registerMiddleware(ExceptionMiddleware::class);

		$context->registerEventListener(InsertEvent::class, ContextChatProvider::class);
		$context->registerEventListener(ManipulateEvent::class, ContextChatProvider::class);
		$context->registerEventListener(BeforeDeleteEvent::class, ContextChatProvider::class);

		$context->registerEventListener(ContentProviderRegisterEvent::class, ContextChatProvider::class);
	}

	/**
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 * @throws \Throwable
	 */
	public function boot(IBootContext $context): void {
		$this->getContainer()->get(ContextChatProvider::class)->register();
		$container = $context->getServerContainer();
		CreateBookmark::register($container->get(IEventDispatcher::class));
	}
}
