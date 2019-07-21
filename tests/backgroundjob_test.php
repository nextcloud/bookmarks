<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\BackgroundJobs\PreviewsJob;
use OCA\Bookmarks\Bookmarks;

/**
 * Class Test_BackgroundJob
 */
class Test_BackgroundJob extends TestCase {
	protected function setUp() {
		parent::setUp();

		$this->libBookmarks = \OC::$server->query(Bookmarks::class);
		$this->previewsJob = \OC::$server->query(PreviewsJob::class);
		$this->userid = 'test';

		$this->libBookmarks->addBookmark($this->userid, "http://www.duckduckgo.com", "DuckDuckGo", [], "PrivateNoTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.google.de", "Google", ["one"], "PrivateTwoTags", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Heise", ["one", "two"], "PrivatTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.golem.de", "Golem", ["one"], "PublicNoTag", true);
		$this->libBookmarks->addBookmark($this->userid, "http://9gag.com", "9gag", ["two", "three"], "PublicTag", true);
	}

	public function testPreviewsJob() {
		$this->previewsJob->run([]);
	}
}
