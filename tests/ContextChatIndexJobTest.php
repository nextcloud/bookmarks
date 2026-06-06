<?php
/*
 * Copyright (c) 2026. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

declare(strict_types=1);

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\AppInfo\Application;
use OCA\Bookmarks\BackgroundJobs\ContextChatIndexJob;
use OCA\Bookmarks\ContextChat\ContextChatProvider;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Service\BookmarkService;
use OCA\Bookmarks\Service\UserSettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\ContextChat\ContentItem;
use OCP\ContextChat\IContentManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;

class ContextChatIndexJobTest extends TestCase {
	private ITimeFactory&MockObject $timeFactory;
	private BookmarkService&MockObject $bookmarkService;
	private IContentManager&MockObject $contentManager;
	private IUserManager&MockObject $userManager;
	private ContextChatProvider&MockObject $provider;
	private UserSettingsService&MockObject $userSettings;
	private IJobList&MockObject $jobList;
	private ContextChatIndexJob $job;

	protected function setUp(): void {
		parent::setUp();

		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getTime')->willReturn(1710000000);
		$this->bookmarkService = $this->createMock(BookmarkService::class);
		$this->contentManager = $this->createMock(IContentManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->provider = $this->createMock(ContextChatProvider::class);
		$this->userSettings = $this->createMock(UserSettingsService::class);
		$this->jobList = $this->createMock(IJobList::class);

		$this->job = new ContextChatIndexJob(
			$this->timeFactory,
			$this->bookmarkService,
			$this->contentManager,
			$this->userManager,
			$this->provider,
			$this->userSettings,
		);
		$this->job->setArgument(['user' => 'alice']);
	}

	public function testDoesNothingWhenContextChatUnavailable(): void {
		$this->contentManager->expects(self::once())
			->method('isContextChatAvailable')
			->willReturn(false);
		$this->userManager->expects(self::never())->method('get');
		$this->contentManager->expects(self::never())->method('submitContent');
		$this->jobList->expects(self::once())->method('remove');
		$this->jobList->expects(self::once())->method('setLastRun');
		$this->jobList->expects(self::once())->method('setExecutionTime');

		$this->job->start($this->jobList);
	}

	public function testIndexesBookmarksForEnabledUser(): void {
		$user = $this->createConfiguredMock(IUser::class, ['getUID' => 'alice']);
		$bookmarks = [
			$this->createBookmark(1, 'alice', 'One', 'First content'),
			$this->createBookmark(2, 'alice', 'Two', 'Second content'),
		];

		$this->contentManager->expects(self::once())
			->method('isContextChatAvailable')
			->willReturn(true);
		$this->userManager->expects(self::once())
			->method('get')
			->with('alice')
			->willReturn($user);
		$this->userSettings->expects(self::once())
			->method('setUserId')
			->with('alice');
		$this->userSettings->expects(self::once())
			->method('get')
			->with('contextchat.enabled')
			->willReturn('true');
		$this->bookmarkService->expects(self::once())
			->method('getIterator')
			->with('alice')
			->willReturn((function () use ($bookmarks) {
				foreach ($bookmarks as $bookmark) {
					yield $bookmark;
				}
			})());
		$this->provider->expects(self::exactly(2))
			->method('getId')
			->willReturn('bookmarks');
		$this->contentManager->expects(self::once())
			->method('submitContent')
			->with(
				Application::APP_ID,
				self::callback(function (array $items): bool {
					self::assertCount(2, $items);
					self::assertContainsOnlyInstancesOf(ContentItem::class, $items);
					self::assertSame('1', $items[0]->itemId);
					self::assertSame('2', $items[1]->itemId);
					self::assertSame(['alice'], $items[0]->users);
					return true;
				})
			);
		$this->jobList->expects(self::once())->method('remove');
		$this->jobList->expects(self::once())->method('setLastRun');
		$this->jobList->expects(self::once())->method('setExecutionTime');

		$this->job->start($this->jobList);
	}

	public function testDoesNotIndexWhenUserDisabledContextChat(): void {
		$user = $this->createConfiguredMock(IUser::class, ['getUID' => 'alice']);

		$this->contentManager->expects(self::once())
			->method('isContextChatAvailable')
			->willReturn(true);
		$this->userManager->expects(self::once())
			->method('get')
			->with('alice')
			->willReturn($user);
		$this->userSettings->expects(self::once())
			->method('setUserId')
			->with('alice');
		$this->userSettings->expects(self::once())
			->method('get')
			->with('contextchat.enabled')
			->willReturn('false');
		$this->bookmarkService->expects(self::never())->method('getIterator');
		$this->contentManager->expects(self::never())->method('submitContent');
		$this->jobList->expects(self::once())->method('remove');
		$this->jobList->expects(self::once())->method('setLastRun');
		$this->jobList->expects(self::once())->method('setExecutionTime');

		$this->job->start($this->jobList);
	}

	private function createBookmark(int $id, string $userId, string $title, string $textContent): Bookmark {
		return Bookmark::fromArray([
			'id' => $id,
			'userId' => $userId,
			'url' => 'https://example.com/' . $id,
			'title' => $title,
			'description' => '',
			'lastmodified' => 1710000000 + $id,
			'added' => 1710000000,
			'clickcount' => 0,
			'lastPreview' => 0,
			'available' => true,
			'archivedFile' => 0,
			'textContent' => $textContent,
			'htmlContent' => '',
			'urlHash' => 'hash-' . $id,
		]);
	}
}

