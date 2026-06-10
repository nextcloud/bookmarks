<?php

/*
 * Copyright (c) 2026. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

declare(strict_types=1);

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\BackgroundJobs\ContextChatIndexJob;
use OCA\Bookmarks\ContextChat\ContextChatProvider;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Events\BeforeDeleteEvent;
use OCA\Bookmarks\Events\InsertEvent;
use OCA\Bookmarks\Service\BookmarkService;
use OCA\Bookmarks\Service\UserSettingsService;
use OCP\BackgroundJob\IJobList;
use OCP\ContextChat\ContentItem;
use OCP\ContextChat\Events\ContentProviderRegisterEvent;
use OCP\ContextChat\IContentManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;

class ContextChatProviderTest extends TestCase {
	private BookmarkService&MockObject $bookmarkService;
	private IUserManager&MockObject $userManager;
	private IContentManager&MockObject $contentManager;
	private IJobList&MockObject $jobList;
	private UserSettingsService&MockObject $userSettings;
	private ContextChatProvider $provider;

	protected function setUp(): void {
		parent::setUp();

		$this->bookmarkService = $this->createMock(BookmarkService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->contentManager = $this->createMock(IContentManager::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->userSettings = $this->createMock(UserSettingsService::class);

		$this->provider = new ContextChatProvider(
			$this->bookmarkService,
			$this->userManager,
			$this->contentManager,
			$this->jobList,
			$this->userSettings,
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
		$this->userSettings->expects(self::never())
			->method('get');

		$this->provider->handle(new InsertEvent(TreeMapper::TYPE_BOOKMARK, 42));
	}

	public function testSubmitsBookmarkContentOnInsert(): void {
		$bookmark = Bookmark::fromArray([
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

		$this->contentManager->expects(self::once())
			->method('isContextChatAvailable')
			->willReturn(true);
		$this->userSettings->expects(self::once())
			->method('get')
			->with('contextchat.enabled')
			->willReturn('true');
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
					self::assertSame(['alice'], $items[0]->users);
					return true;
				})
			);

		$this->provider->handle(new InsertEvent(TreeMapper::TYPE_BOOKMARK, 42));
	}

	public function testDeletesBookmarkContentOnDelete(): void {
		$this->contentManager->expects(self::once())
			->method('isContextChatAvailable')
			->willReturn(true);
		$this->userSettings->expects(self::once())
			->method('get')
			->with('contextchat.enabled')
			->willReturn('true');
		$this->contentManager->expects(self::once())
			->method('deleteContent')
			->with('bookmarks', 'bookmarks', ['7']);

		$this->provider->handle(new BeforeDeleteEvent(TreeMapper::TYPE_BOOKMARK, 7));
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
}
