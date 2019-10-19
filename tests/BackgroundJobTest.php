<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\BackgroundJobs\PreviewsJob;
use OCA\Bookmarks\Bookmarks;
use OC\BackgroundJob\JobList;

/**
 * Class Test_BackgroundJob
 */
class BackgroundJobTest extends TestCase {
	protected function setUp() :void {
		parent::setUp();

		$this->libBookmarks = \OC::$server->query(Bookmarks::class);
		$this->previewsJob = \OC::$server->query(PreviewsJob::class);
		$this->jobList = \OC::$server->query(JobList::class);
		$this->userid = 'test';

		$this->libBookmarks->addBookmark($this->userid, "http://www.duckduckgo.com", "DuckDuckGo", [], "PrivateNoTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.google.de", "Google", ["one"], "PrivateTwoTags", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Heise", ["one", "two"], "PrivatTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.golem.de", "Golem", ["one"], "PublicNoTag", true);
		$this->libBookmarks->addBookmark($this->userid, "http://9gag.com", "9gag", ["two", "three"], "PublicTag", true);
	}

	public function testPreviewsJob() {
		$this->previewsJob->execute($this->jobList);
	}
}
