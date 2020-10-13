<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\BackgroundJobs\CrawlJob;
use OC\BackgroundJob\JobList;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * Class Test_BackgroundJob
 */
class BackgroundJobTest extends TestCase {
	/**
	 * @var mixed|\stdClass
	 */
	private $bookmarkMapper;
	/**
	 * @var mixed|\stdClass
	 */
	private $previewsJob;
	/**
	 * @var mixed|\stdClass
	 */
	private $jobList;
	/**
	 * @var mixed|\stdClass
	 */
	private $settings;
	/**
	 * @var string
	 */
	private $userId;

	protected function setUp() :void {
		parent::setUp();

		$this->bookmarkMapper = \OC::$server->get(BookmarkMapper::class);
		$this->previewsJob = \OC::$server->get(CrawlJob::class);
		$this->jobList = \OC::$server->get(JobList::class);
		$this->settings = \OC::$server->get(IConfig::class);
		$this->userId = 'test';

		$this->settings->setAppValue('bookmarks', 'privacy.enableScraping', 'true');

		array_map(function ($bm) {
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
		return array_map(function ($props) {
			return Bookmark::fromArray($props);
		}, [
			'Simple URL with title and description' => ['url' => 'https://google.com/', 'title' => 'Google', 'description' => 'Search engine'],
			'Simple URL with title' => ['url' => 'https://nextcloud.com/', 'title' => 'Nextcloud'],
			'Simple URL' => ['url' => 'https://php.net/'],
			'URL with unicode' => ['url' => 'https://de.wikipedia.org/wiki/%C3%9C'],
			'Non-existent URL' => ['url' => 'https://http://www.bllaala.com/'],
		]);
	}
}
