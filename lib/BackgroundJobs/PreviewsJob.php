<?php

namespace OCA\Bookmarks\BackgroundJobs;

use OC\BackgroundJob\TimedJob;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Service\BookmarkPreviewer;
use OCA\Bookmarks\Service\FaviconPreviewer;
use OCA\Bookmarks\Service\Previewers\DefaultBookmarkPreviewer;
use OCP\IConfig;

class PreviewsJob extends TimedJob {
	/**
	 * @var BookmarkPreviewer
	 */
	private $bookmarkPreviewer;
	/**
	 * @var FaviconPreviewer
	 */
	private $faviconPreviewer;
	/**
	 * @var BookmarkMapper
	 */
	private $bookmarkMapper;
	/**
	 * @var IConfig
	 */
	private $settings;

	public function __construct(
		IConfig $settings, BookmarkMapper $bookmarkMapper, BookmarkPreviewer $bookmarkPreviewer, FaviconPreviewer $faviconPreviewer
	) {
		$this->settings = $settings;
		$this->bookmarkMapper = $bookmarkMapper;
		$this->bookmarkPreviewer = $bookmarkPreviewer;
		$this->faviconPreviewer = $faviconPreviewer;

		$this->setInterval(60);//*60*24); //run hourly
	}

	protected function run($argument) {
		if ($this->settings->getAppValue(
				'core',
				'backgroundjobs_mode'
			) !== 'cron') {
			return;
		}
		$bookmarks = $this->bookmarkMapper->findPendingPreviews(100, DefaultBookmarkPreviewer::CACHE_TTL);
		foreach ($bookmarks as $bookmark) {
			$this->bookmarkPreviewer->getImage($bookmark);
			$this->faviconPreviewer->getImage($bookmark);
			$bookmark->markPreviewCreated();
			$this->bookmarkMapper->update($bookmark);
		}
	}
}
