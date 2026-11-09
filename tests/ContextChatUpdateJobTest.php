<?php

/*
 * Copyright (c) 2026. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

declare(strict_types=1);

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\BackgroundJobs\ContextChatUpdateJob;
use OCA\Bookmarks\ContextChat\ContextChatProvider;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Service\BookmarkService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\ContextChat\IContentManager;
use PHPUnit\Framework\MockObject\MockObject;

class ContextChatUpdateJobTest extends TestCase {
	private ITimeFactory&MockObject $timeFactory;
	private IContentManager&MockObject $contentManager;
	private ContextChatProvider&MockObject $provider;
	private BookmarkService&MockObject $bookmarkService;
	private IJobList&MockObject $jobList;
	private ContextChatUpdateJob $job;

	protected function setUp(): void {
		parent::setUp();

		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getTime')->willReturn(1710000000);
		$this->contentManager = $this->createMock(IContentManager::class);
		$this->provider = $this->createMock(ContextChatProvider::class);
		$this->bookmarkService = $this->createMock(BookmarkService::class);
		$this->jobList = $this->createMock(IJobList::class);

		$this->job = new ContextChatUpdateJob(
			$this->timeFactory,
			$this->contentManager,
			$this->provider,
			$this->bookmarkService,
		);
	}

	public function testUpdatesGivenBookmarks(): void {
		$this->contentManager->method('isContextChatAvailable')->willReturn(true);
		$this->provider->expects(self::once())
			->method('updateBookmarks')
			->with([1, 2], false);

		$this->job->setArgument(['bookmarks' => [1, 2], 'accessMayShrink' => false]);
		$this->job->start($this->jobList);
	}

	public function testUpdatesAllBookmarksVisibleToUser(): void {
		$this->contentManager->method('isContextChatAvailable')->willReturn(true);
		$this->bookmarkService->expects(self::once())
			->method('getIterator')
			->with('alice')
			->willReturn((function () {
				yield Bookmark::fromArray(['id' => 1]);
				yield Bookmark::fromArray(['id' => 2]);
			})());
		$this->provider->expects(self::once())
			->method('updateBookmarks')
			->with([1, 2], true);

		$this->job->setArgument(['user' => 'alice']);
		$this->job->start($this->jobList);
	}

	public function testDoesNothingWhenContextChatUnavailable(): void {
		$this->contentManager->method('isContextChatAvailable')->willReturn(false);
		$this->bookmarkService->expects(self::never())->method('getIterator');
		$this->provider->expects(self::never())->method('updateBookmarks');

		$this->job->setArgument(['user' => 'alice']);
		$this->job->start($this->jobList);
	}
}
