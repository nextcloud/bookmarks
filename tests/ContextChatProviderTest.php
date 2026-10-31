<?php

/*
 * Copyright (c) 2026. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

declare(strict_types=1);

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\BackgroundJobs\ContextChatIndexJob;
use OCA\Bookmarks\BackgroundJobs\ContextChatUpdateJob;
use OCA\Bookmarks\ContextChat\ContextChatProvider;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Events\BeforeDeleteEvent;
use OCA\Bookmarks\Events\BeforeSoftDeleteEvent;
use OCA\Bookmarks\Events\BeforeSoftUndeleteEvent;
use OCA\Bookmarks\Events\MoveEvent;
use OCA\Bookmarks\Service\BookmarkService;
use OCP\BackgroundJob\IJobList;
use OCP\ContextChat\ContentItem;
use OCP\ContextChat\Events\ContentProviderRegisterEvent;
use OCP\ContextChat\IContentManager;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;

class ContextChatProviderTest extends TestCase {
	private BookmarkService&MockObject $bookmarkService;
	private IUserManager&MockObject $userManager;
	private IContentManager&MockObject $contentManager;
	private IJobList&MockObject $jobList;
	private TreeMapper&MockObject $treeMapper;
	private IConfig&MockObject $config;
	private ContextChatProvider $provider;

	protected function setUp(): void {
		parent::setUp();

		$this->bookmarkService = $this->createMock(BookmarkService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->contentManager = $this->createMock(IContentManager::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->treeMapper = $this->createMock(TreeMapper::class);
		$this->config = $this->createMock(IConfig::class);

		$this->provider = new ContextChatProvider(
			$this->bookmarkService,
			$this->userManager,
			$this->contentManager,
			$this->jobList,
			$this->treeMapper,
			$this->config,
		);
	}

	public function testRegistersWithOcpContextChatEvent(): void {
		$event = $this->createMock(ContentProviderRegisterEvent::class);
		$event->expects(self::once())
			->method('registerContentProvider')
			->with('bookmarks', 'bookmarks', ContextChatProvider::class);

		$this->provider->handle($event);
	}

	public function testDoesNotSubmitWhenContextChatUnavailable(): void {
		$this->contentManager->expects(self::once())
			->method('isContextChatAvailable')
			->willReturn(false);
		$this->contentManager->expects(self::never())
			->method('submitContent');
		$this->treeMapper->expects(self::never())
			->method('findUsersWithAccess');

		$this->provider->handle(new MoveEvent(TreeMapper::TYPE_BOOKMARK, 42, null, 1));
	}

	public function testSubmitsBookmarkContentOnMove(): void {
		$bookmark = $this->createBookmark();

		$this->contentManager->expects(self::once())
			->method('isContextChatAvailable')
			->willReturn(true);
		$this->treeMapper->expects(self::once())
			->method('findUsersWithAccess')
			->with(TreeMapper::TYPE_BOOKMARK, 42)
			->willReturn(['alice', 'bob', 'carol']);
		$this->mockEnabledUsers(['alice', 'carol']);
		$this->bookmarkService->expects(self::once())
			->method('findById')
			->with(42)
			->willReturn($bookmark);
		$this->contentManager->expects(self::once())
			->method('submitContent')
			->with(
				'bookmarks',
				self::callback(function (array $items): bool {
					self::assertCount(1, $items);
					self::assertContainsOnlyInstancesOf(ContentItem::class, $items);
					self::assertSame('42', $items[0]->itemId);
					self::assertSame('bookmarks', $items[0]->providerId);
					self::assertSame('Example', $items[0]->title);
					self::assertSame('Indexed body', $items[0]->content);
					self::assertSame(['alice', 'carol'], $items[0]->users);
					return true;
				})
			);

		$this->provider->handle(new MoveEvent(TreeMapper::TYPE_BOOKMARK, 42, null, 1));
	}

	public function testDoesNotSubmitWhenNoUserWithAccessHasContextChatEnabled(): void {
		$this->contentManager->expects(self::once())
			->method('isContextChatAvailable')
			->willReturn(true);
		$this->treeMapper->expects(self::once())
			->method('findUsersWithAccess')
			->with(TreeMapper::TYPE_BOOKMARK, 42)
			->willReturn(['alice', 'bob']);
		$this->mockEnabledUsers([]);
		$this->bookmarkService->expects(self::once())
			->method('findById')
			->with(42)
			->willReturn($this->createBookmark());
		$this->contentManager->expects(self::never())
			->method('submitContent');

		$this->provider->handle(new MoveEvent(TreeMapper::TYPE_BOOKMARK, 42, null, 1));
	}

	public function testDeletesBookmarkContentOnDelete(): void {
		$this->contentManager->expects(self::once())
			->method('isContextChatAvailable')
			->willReturn(true);
		$this->contentManager->expects(self::once())
			->method('deleteContent')
			->with('bookmarks', 'bookmarks', ['7']);

		$this->provider->handle(new BeforeDeleteEvent(TreeMapper::TYPE_BOOKMARK, 7));
	}

	public function testDeletesContentWhenNoUserWithAccessIsLeftAfterRemoval(): void {
		$this->contentManager->expects(self::once())
			->method('isContextChatAvailable')
			->willReturn(true);
		$this->treeMapper->expects(self::once())
			->method('findUsersWithAccess')
			->with(TreeMapper::TYPE_BOOKMARK, 42)
			->willReturn(['alice']);
		$this->mockEnabledUsers([]);
		$this->bookmarkService->expects(self::once())
			->method('findById')
			->with(42)
			->willReturn($this->createBookmark());
		$this->contentManager->expects(self::never())
			->method('submitContent');
		$this->contentManager->expects(self::once())
			->method('deleteContent')
			->with('bookmarks', 'bookmarks', ['42']);

		// removed from folder 1
		$this->provider->handle(new MoveEvent(TreeMapper::TYPE_BOOKMARK, 42, 1));
	}

	public function testQueuesSubtreeUpdateWhenFolderIsMoved(): void {
		$this->contentManager->expects(self::once())
			->method('isContextChatAvailable')
			->willReturn(true);
		$this->treeMapper->expects(self::once())
			->method('findBookmarkIdsInSubtree')
			->with(TreeMapper::TYPE_FOLDER, 5)
			->willReturn(range(1, 250));
		$this->jobList->expects(self::exactly(2))
			->method('add')
			->withConsecutive(
				[ContextChatUpdateJob::class, ['bookmarks' => range(1, 200), 'accessMayShrink' => true]],
				[ContextChatUpdateJob::class, ['bookmarks' => range(201, 250), 'accessMayShrink' => true]],
			);
		$this->contentManager->expects(self::never())
			->method('submitContent');

		$this->provider->handle(new MoveEvent(TreeMapper::TYPE_FOLDER, 5, 1, 2));
	}

	public function testQueuesSubtreeUpdateWhenShareIsCreated(): void {
		$this->contentManager->expects(self::once())
			->method('isContextChatAvailable')
			->willReturn(true);
		$this->treeMapper->expects(self::once())
			->method('findBookmarkIdsInSubtree')
			->with(TreeMapper::TYPE_SHARE, 3)
			->willReturn([42]);
		$this->jobList->expects(self::once())
			->method('add')
			->with(ContextChatUpdateJob::class, ['bookmarks' => [42], 'accessMayShrink' => false]);

		$this->provider->handle(new MoveEvent(TreeMapper::TYPE_SHARE, 3, null, 1));
	}

	public function testQueuesSubtreeUpdateWhenFolderIsDeleted(): void {
		$this->contentManager->expects(self::once())
			->method('isContextChatAvailable')
			->willReturn(true);
		$this->treeMapper->expects(self::once())
			->method('findBookmarkIdsInSubtree')
			->with(TreeMapper::TYPE_FOLDER, 5)
			->willReturn([42]);
		$this->jobList->expects(self::once())
			->method('add')
			->with(ContextChatUpdateJob::class, ['bookmarks' => [42], 'accessMayShrink' => true]);
		$this->contentManager->expects(self::never())
			->method('deleteContent');

		$this->provider->handle(new BeforeDeleteEvent(TreeMapper::TYPE_FOLDER, 5));
	}

	public function testQueuesUpdateWhenBookmarkIsTrashed(): void {
		$this->contentManager->expects(self::once())
			->method('isContextChatAvailable')
			->willReturn(true);
		$this->jobList->expects(self::once())
			->method('add')
			->with(ContextChatUpdateJob::class, ['bookmarks' => [42], 'accessMayShrink' => true]);
		$this->contentManager->expects(self::never())
			->method('deleteContent');

		$this->provider->handle(new BeforeSoftDeleteEvent(TreeMapper::TYPE_BOOKMARK, 42));
	}

	public function testQueuesSubtreeUpdateWhenFolderIsTrashed(): void {
		$this->contentManager->expects(self::once())
			->method('isContextChatAvailable')
			->willReturn(true);
		$this->treeMapper->expects(self::once())
			->method('findBookmarkIdsInSubtree')
			->with(TreeMapper::TYPE_FOLDER, 5)
			->willReturn([42]);
		$this->jobList->expects(self::once())
			->method('add')
			->with(ContextChatUpdateJob::class, ['bookmarks' => [42], 'accessMayShrink' => true]);

		$this->provider->handle(new BeforeSoftDeleteEvent(TreeMapper::TYPE_FOLDER, 5));
	}

	public function testQueuesSubtreeUpdateWhenFolderIsRestored(): void {
		$this->contentManager->expects(self::once())
			->method('isContextChatAvailable')
			->willReturn(true);
		$this->treeMapper->expects(self::once())
			->method('findBookmarkIdsInSubtree')
			->with(TreeMapper::TYPE_FOLDER, 5)
			->willReturn([42]);
		$this->jobList->expects(self::once())
			->method('add')
			->with(ContextChatUpdateJob::class, ['bookmarks' => [42], 'accessMayShrink' => false]);

		$this->provider->handle(new BeforeSoftUndeleteEvent(TreeMapper::TYPE_FOLDER, 5));
	}

	public function testUpdateBookmarksDeletesBookmarksThatNoLongerExist(): void {
		$this->bookmarkService->expects(self::exactly(2))
			->method('findById')
			->willReturnMap([[42, $this->createBookmark()], [7, null]]);
		$this->treeMapper->method('findUsersWithAccess')->willReturn(['alice']);
		$this->mockEnabledUsers(['alice']);
		$this->contentManager->expects(self::once())
			->method('submitContent')
			->with('bookmarks', self::callback(fn (array $items) => count($items) === 1 && $items[0]->itemId === '42'));
		$this->contentManager->expects(self::once())
			->method('deleteContent')
			->with('bookmarks', 'bookmarks', ['7']);

		$this->provider->updateBookmarks([42, 7], true);
	}

	public function testTriggerInitialImportQueuesAJobForEachUser(): void {
		$userA = $this->createConfiguredMock(IUser::class, ['getUID' => 'alice']);
		$userB = $this->createConfiguredMock(IUser::class, ['getUID' => 'bob']);

		$this->jobList->expects(self::exactly(2))
			->method('add')
			->withConsecutive(
				[ContextChatIndexJob::class, ['user' => 'alice']],
				[ContextChatIndexJob::class, ['user' => 'bob']],
			);
		$this->userManager->expects(self::once())
			->method('callForAllUsers')
			->willReturnCallback(function (callable $callback) use ($userA, $userB): void {
				$callback($userA);
				$callback($userB);
			});

		$this->provider->triggerInitialImport();
	}

	private function createBookmark(): Bookmark {
		return Bookmark::fromArray([
			'id' => 42,
			'userId' => 'alice',
			'url' => 'https://example.com',
			'title' => 'Example',
			'description' => 'Desc',
			'lastmodified' => 1710000000,
			'added' => 1710000000,
			'clickcount' => 0,
			'lastPreview' => 0,
			'available' => true,
			'archivedFile' => 0,
			'textContent' => 'Indexed body',
			'htmlContent' => '',
			'urlHash' => 'hash',
		]);
	}

	/**
	 * @param list<string> $enabledUsers users who have ContextChat enabled for bookmarks
	 */
	private function mockEnabledUsers(array $enabledUsers): void {
		$this->config->method('getUserValue')
			->willReturnCallback(fn (string $userId, string $appId, string $key, $default) => match ($key) {
				'contextchat.enabled' => in_array($userId, $enabledUsers, true) ? 'true' : 'false',
				default => $default,
			});
	}
}
