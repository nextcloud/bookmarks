<?php
namespace OCA\Bookmarks\BackgroundJobs;

use \OCA\Bookmarks\Previews\DefaultPreviewService;
use \OCA\Bookmarks\Previews\FaviconPreviewService;
use \OCA\Bookmarks\Previews\ScreenlyPreviewService;
use \OCA\Bookmarks\Bookmarks;
use OC\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IUserManager;

class PreviewsJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		IConfig $settings,
		IUserManager $userManager,
		Bookmarks $libBookmarks,
		DefaultPreviewService $defaultPreviews,
		FaviconPreviewService $faviconPreviews,
		ScreenlyPreviewService $screenlyPreviews
	) {
		$this->settings = $settings;
		$this->userManager = $userManager;
		$this->libBookmarks = $libBookmarks;
		$this->defaultPreviews = $defaultPreviews;
		$this->faviconPreviews = $faviconPreviews;
		$this->screenlyPreviews = $screenlyPreviews;

		$this->setInterval(60);//*60*24); //run hourly
	}

	protected function run($argument) {
		if ($this->settings->getAppValue(
			'core',
			'backgroundjobs_mode'
		) !== 'cron') {
			return;
		}
		$bookmarks = $this->libBookmarks->findBookmarksNeedingPreview(100, DefaultPreviewService::CACHE_TTL);
		foreach ($bookmarks as $bookmark) {
			if (null === $this->defaultPreviews->getImage($bookmark)) {
				$this->screenlyPreviews->getImage($bookmark);
			}
			$this->faviconPreviews->getImage($bookmark);
			$this->libBookmarks->markPreviewCreated($bookmark['bookmark_id']);
		}
	}
}
