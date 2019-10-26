<?php
namespace OCA\Bookmarks\BackgroundJobs;

use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Service\BookmarkPreviewer;
use OC\BackgroundJob\TimedJob;
use OCA\Bookmarks\Service\FaviconPreviewer;
use OCA\Bookmarks\Service\Previewers\DefaultBookmarkPreviewer;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IUserManager;

class PreviewsJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		IConfig $settings,
		IUserManager $userManager,
		BookmarkMapper $bookmarkMapper,
		BookmarkPreviewer $bookmarkPreviewer,
	    FaviconPreviewer $faviconPreviewer
	) {
		$this->settings = $settings;
		$this->userManager = $userManager;
		$this->bookmarkMapper = $bookmarkMapper;
		$this->bookmarkPreviewer = $bookmarkPreviewer;

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
			$this->previewService->getImage($bookmark);
			$this->faviconPreviewer->getImage($bookmark);
			$bookmark->markPreviewCreated();
			$this->bookmarkMapper->update($bookmark);
		}
	}
}
