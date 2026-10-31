<?php

/*
 * Copyright (c) 2025. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\ContextChat;

use OCA\Bookmarks\AppInfo\Application;
use OCA\Bookmarks\BackgroundJobs\ContextChatIndexJob;
use OCA\Bookmarks\BackgroundJobs\ContextChatUpdateJob;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Events\BeforeDeleteEvent;
use OCA\Bookmarks\Events\ChangeEvent;
use OCA\Bookmarks\Events\ManipulateEvent;
use OCA\Bookmarks\Events\MoveEvent;
use OCA\Bookmarks\Service\BookmarkService;
use OCP\BackgroundJob\IJobList;
use OCP\ContextChat\ContentItem;
use OCP\ContextChat\Events\ContentProviderRegisterEvent;
use OCP\ContextChat\IContentManager;
use OCP\ContextChat\IContentProvider;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;

/**
 * @implements IEventListener<Event>
 */
class ContextChatProvider implements IContentProvider, IEventListener {

	public function __construct(
		private BookmarkService $bookmarkService,
		private IUserManager $userManager,
		private ?IContentManager $contentManager,
		private IJobList $jobList,
		private TreeMapper $treeMapper,
		private IConfig $config,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof ContentProviderRegisterEvent) {
			$this->register($event);
			return;
		}
		if (!$this->isContextChatAvailable()) {
			return;
		}
		if (!$event instanceof ChangeEvent) {
			return;
		}
		// Not InsertEvent: it fires before the bookmark is placed in a folder, so nobody has access yet.
		// TreeMapper::addToFolders dispatches a MoveEvent after each placement.
		if ($event instanceof MoveEvent) {
			// Without an old parent the item was only added somewhere, so access can only have grown
			$accessMayShrink = $event->getOldParent() !== null;
			if ($event->getType() === TreeMapper::TYPE_BOOKMARK) {
				$this->updateBookmarks([$event->getId()], $accessMayShrink);
				return;
			}
			$this->scheduleSubtreeUpdate($event->getType(), $event->getId(), $accessMayShrink);
			return;
		}
		if ($event instanceof ManipulateEvent) {
			if ($event->getType() !== TreeMapper::TYPE_BOOKMARK) {
				return;
			}
			$this->updateBookmarks([$event->getId()], false);
			return;
		}
		if ($event instanceof BeforeDeleteEvent) {
			if ($event->getType() === TreeMapper::TYPE_BOOKMARK) {
				$this->contentManager->deleteContent($this->getAppId(), $this->getId(), [(string)$event->getId()]);
				return;
			}
			// The subtree has to be collected now, while it still exists.
			// Bookmarks that end up orphaned by a folder deletion are removed by the job.
			$this->scheduleSubtreeUpdate($event->getType(), $event->getId(), true);
			return;
		}
	}

	/**
	 * Re-submits the given bookmarks with their current access list.
	 * Bookmarks that no longer exist or that nobody with ContextChat enabled can access anymore
	 * are removed from context chat if $accessMayShrink is set.
	 *
	 * @param list<int> $bookmarkIds
	 * @param bool $accessMayShrink
	 */
	public function updateBookmarks(array $bookmarkIds, bool $accessMayShrink): void {
		$items = [];
		$deleted = [];
		foreach ($bookmarkIds as $bookmarkId) {
			$bookmark = $this->bookmarkService->findById($bookmarkId);
			$item = $bookmark === null ? null : $this->getContentItem($bookmark);
			if ($item !== null) {
				$items[] = $item;
			} elseif ($accessMayShrink) {
				$deleted[] = (string)$bookmarkId;
			}
		}
		if (count($items) > 0) {
			$this->contentManager->submitContent($this->getAppId(), $items);
		}
		if (count($deleted) > 0) {
			$this->contentManager->deleteContent($this->getAppId(), $this->getId(), $deleted);
		}
	}

	/**
	 * Queues a re-submission of all bookmarks below the given folder or share,
	 * after the access to that subtree changed.
	 *
	 * @param string $type
	 * @param int $id
	 * @param bool $accessMayShrink
	 */
	private function scheduleSubtreeUpdate(string $type, int $id, bool $accessMayShrink): void {
		if ($type !== TreeMapper::TYPE_FOLDER && $type !== TreeMapper::TYPE_SHARE) {
			return;
		}
		$bookmarkIds = $this->treeMapper->findBookmarkIdsInSubtree($type, $id);
		// Job arguments are limited in size
		foreach (array_chunk($bookmarkIds, 200) as $chunk) {
			$this->jobList->add(ContextChatUpdateJob::class, ['bookmarks' => $chunk, 'accessMayShrink' => $accessMayShrink]);
		}
	}

	public function register(?ContentProviderRegisterEvent $event = null): void {
		if ($event !== null) {
			$event->registerContentProvider($this->getAppId(), $this->getId(), self::class);
			return;
		}

		if (!$this->isContextChatAvailable()) {
			return;
		}

		$this->contentManager->registerContentProvider($this->getAppId(), $this->getId(), self::class);
	}

	/**
	 * Builds the content item for a bookmark, granting access to everyone who can see it
	 * (including via shared folders) and who has ContextChat enabled for bookmarks.
	 * Context chat replaces the access list on every submission, so it must always be complete.
	 *
	 * @param Bookmark $bookmark
	 * @return ContentItem|null null if nobody with ContextChat enabled can access the bookmark
	 */
	public function getContentItem(Bookmark $bookmark): ?ContentItem {
		$users = array_values(array_filter(
			$this->treeMapper->findUsersWithAccess(TreeMapper::TYPE_BOOKMARK, $bookmark->getId()),
			fn (string $userId) => $this->isEnabledForUser($userId),
		));
		if (count($users) === 0) {
			return null;
		}
		return new ContentItem(
			(string)$bookmark->getId(),
			$this->getId(),
			$bookmark->getTitle(),
			$bookmark->getTextContent(),
			'Website',
			new \DateTime('@' . $bookmark->getLastmodified()),
			$users
		);
	}

	/**
	 * Mirrors UserSettingsService::get('contextchat.enabled') for an arbitrary user
	 */
	private function isEnabledForUser(string $userId): bool {
		return $this->config->getUserValue($userId, Application::APP_ID, 'archive.enabled', 'true') === 'true'
			&& $this->config->getUserValue($userId, Application::APP_ID, 'contextchat.enabled', 'false') === 'true';
	}

	private function isContextChatAvailable(): bool {
		return $this->contentManager !== null && $this->contentManager->isContextChatAvailable();
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
