<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\BackgroundJobs\PreviewsJob;
use OC\BackgroundJob\JobList;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use PHPUnit\Framework\TestCase;

/**
 * Class Test_BackgroundJob
 */
class BackgroundJobTest extends TestCase {
	protected function setUp() :void {
		parent::setUp();

		$this->bookmarkMapper = \OC::$server->query(BookmarkMapper::class);
		$this->previewsJob = \OC::$server->query(PreviewsJob::class);
		$this->jobList = \OC::$server->query(JobList::class);
		$this->userId = 'test';

		array_map(function($bm) {
			$this->bookmarkMapper->insert($bm);
		}, $this->singleBookmarksProvider());
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function testPreviewsJob() {
		$this->previewsJob->execute($this->jobList);
	}

	/**
	 * @return array
	 */
	public function singleBookmarksProvider() {
		return array_map(function($props) {
			return Bookmark::fromArray($props);
		}, [
			'Simple URL with title and description' => ['url' => 'https://google.com/', 'title' => 'Google', 'description' => 'Search engine'],
			'Simple URL with title' => ['url' => 'https://nextcloud.com/', 'title' => 'Nextcloud'],
			'Simple URL' => ['url' => 'https://php.net/'],
			'URL with unicode' => ['url' => 'https://de.wikipedia.org/wiki/%C3%9C'],
		]);
	}
}
