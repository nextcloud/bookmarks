<?php
namespace OCA\Bookmarks\Lib\BackgroundJobs;

use \OCA\Bookmarks\Controller\Lib\Previews\DefaultPreviewService;
use \OCA\Bookmarks\Controller\Lib\Previews\FaviconPreviewService;
use \OCA\Bookmarks\Controller\Lib\Previews\ScreenlyPreviewService;
use \OCA\Bookmarks\Controller\Lib\Bookmarks;
use OC\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUserSession;
use OCP\IUserManager;

class PreviewsJob extends TimedJob {
	public function __construct(
		IConfig $settings,
		IUserManager $userManager,
		IUserSession $userSession,
		Bookmarks $libBookmarks,
		DefaultPreviewService $defaultPreviews,
		FaviconPreviewService $faviconPreviews,
		ScreenlyPreviewService $screenlyPreviews
	) {
		$this->settings = $settings;
		$this->userManager = $userManager;
		$this->userSession = $userSession;
		$this->libBookmarks = $libBookmarks;
		$this->defaultPreviews = $defaultPreviews;
		$this->faviconPreviews = $faviconPreviews;
		$this->screenlyPreviews = $screenlyPreviews;

		$this->setInterval(60*60*24); //run daily
	}

	protected function run($argument) {
		if ($this->settings->getAppValue(
			'core',
			'backgroundjobs_mode'
		) !== 'cron') {
			return;
		}
		\OCP\Util::writeLog('bookmarks', 'starting PreviewsJob', \OCP\Util::WARN);
		$allBookmarks = $this->libBookmarks->findBookmarks(-1, 0, 'lastmodified', [], true, -1);
		foreach ($allBookmarks as $bookmark) {
			$this->userSession->setUser($this->userManager->get($bookmark['user_id']));
			if (null === $this->defaultPreviews->getImage($bookmark)) {
				$this->screenlyPreviews->getImage($bookmark);
			}
			$this->faviconPreviews->getImage($bookmark);
		}
		$this->userSession->logout();
	}
}
