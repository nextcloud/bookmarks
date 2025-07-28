<?php

/*
 * Copyright (c) 2025. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\ContextChat;

use OCA\Bookmarks\AppInfo\Application;
use OCA\Bookmarks\BackgroundJobs\ContextChatIndexJob;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Events\BeforeDeleteEvent;
use OCA\Bookmarks\Events\ChangeEvent;
use OCA\Bookmarks\Events\InsertEvent;
use OCA\Bookmarks\Events\ManipulateEvent;
use OCA\Bookmarks\Service\BookmarkService;
use OCA\Bookmarks\Service\UserSettingsService;
use OCA\ContextChat\Event\ContentProviderRegisterEvent;
use OCA\ContextChat\Public\ContentItem;
use OCA\ContextChat\Public\ContentManager;
use OCA\ContextChat\Public\IContentProvider;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
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
		private ?ContentManager $contentManager,
		private IJobList $jobList,
		private UserSettingsService $userSettings,
	) {
	}

	public function handle(Event $event): void {
		if ($this->contentManager === null) {
			return;
		}
		if ($event instanceof ContentProviderRegisterEvent) {
			$this->register();
			return;
		}
		if (!$event instanceof ChangeEvent) {
			return;
		}
		if ($this->userSettings->get('contextchat.enabled') !== 'true') {
			return;
		}
		if ($event instanceof InsertEvent || $event instanceof ManipulateEvent) {
			if ($event->getType() !== TreeMapper::TYPE_BOOKMARK) {
				return;
			}
			$bookmark = $this->bookmarkService->findById($event->getId());
			if ($bookmark === null) {
				return;
			}
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
	 */
	public function getId(): string {
		return 'bookmarks';
	}

	/**
	 * The ID of the app making the provider available
	 *
	 * @return string
	 */
	public function getAppId(): string {
		return Application::APP_ID;
	}

	/**
	 * The absolute URL to the content item
	 *
	 * @param string $id
	 * @return string
	 */
	public function getItemUrl(string $id): string {
		return $this->bookmarkService->findById(intval($id))?->getUrl() ?? '';
	}

	/**
	 * Starts the initial import of content items into content chat
	 *
	 * @return void
	 */
	public function triggerInitialImport(): void {
		$this->userManager->callForAllUsers(function (IUser $user) {
			$this->jobList->add(ContextChatIndexJob::class, ['user' => $user->getUID()]);
		});
	}
}
