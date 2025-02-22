<?php

/*
 * Copyright (c) 2025. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\ContextChat;

use OCA\Bookmarks\AppInfo\Application;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Events\BeforeDeleteEvent;
use OCA\Bookmarks\Events\InsertEvent;
use OCA\Bookmarks\Events\ManipulateEvent;
use OCA\Bookmarks\Service\BookmarkService;
use OCA\ContextChat\Event\ContentProviderRegisterEvent;
use OCA\ContextChat\Public\ContentItem;
use OCA\ContextChat\Public\ContentManager;
use OCA\ContextChat\Public\IContentProvider;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\IUser;
use OCP\IUserManager;

/**
 * @implements IEventListener<Event>
 */
class ContextChatProvider implements IContentProvider, IEventListener {

	public function __construct(
		private BookmarkService $bookmarkService,
		private IUserManager $userManager,
		private ContentManager $contentManager,
		private IEventDispatcher $eventDispatcher,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof ContentProviderRegisterEvent) {
			$this->register();
			return;
		}
		if ($event instanceof InsertEvent || $event instanceof ManipulateEvent) {
			if ($event->getType() !== TreeMapper::TYPE_BOOKMARK) {
				return;
			}
			$bookmark = $this->bookmarkService->findById($event->getId());
			$item = new ContentItem(
				(string)$event->getId(),
				$this->getId(),
				$bookmark->getTitle(),
				$bookmark->getTextContent(),
				'Website',
				new \DateTime('@' . $bookmark->getLastmodified()),
				[$bookmark->getUserId()]
			);
			$this->contentManager->submitContent($this->getAppId(), [$item]);
			return;
		}
		if ($event instanceof BeforeDeleteEvent) {
			if ($event->getType() !== TreeMapper::TYPE_BOOKMARK) {
				return;
			}
			$this->contentManager->deleteContent($this->getAppId(), $this->getId(), [(string)$event->getId()]);
			return;
		}
	}

	public function register(): void {
		$this->contentManager->registerContentProvider($this->getAppId(), $this->getId(), self::class);
	}

	/**
	 * The ID of the provider
	 *
	 * @return string
	 * @since 1.1.0
	 */
	public function getId(): string {
		return 'bookmarks';
	}

	/**
	 * The ID of the app making the provider avaialble
	 *
	 * @return string
	 * @since 1.1.0
	 */
	public function getAppId(): string {
		return Application::APP_ID;
	}

	/**
	 * The absolute URL to the content item
	 *
	 * @param string $id
	 * @return string
	 * @since 1.1.0
	 */
	public function getItemUrl(string $id): string {
		return $this->bookmarkService->findById(intval($id))?->getUrl() ?? '';
	}

	/**
	 * Starts the initial import of content items into content chat
	 *
	 * @return void
	 * @since 1.1.0
	 */
	public function triggerInitialImport(): void {
		$this->userManager->callForAllUsers(function (IUser $user) {
			$items = [];
			foreach ($this->bookmarkService->getIterator($user->getUID()) as $bookmark) {
				$items[] = new ContentItem(
					(string)$bookmark->getId(),
					$this->getId(),
					$bookmark->getTitle(),
					$bookmark->getTextContent(),
					'Website',
					new \DateTime('@' . $bookmark->getLastmodified()),
					[$user->getUID()]
				);
				if (count($items) < 25) {
					continue;
				}
				$this->contentManager->submitContent($this->getAppId(), $items);
			}
		});
	}
}
