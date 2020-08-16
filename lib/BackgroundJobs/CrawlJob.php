<?php

namespace OCA\Bookmarks\BackgroundJobs;

use OC\BackgroundJob\TimedJob;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Service\BookmarkPreviewer;
use OCA\Bookmarks\Service\FaviconPreviewer;
use OCP\IConfig;

class CrawlJob extends TimedJob {
	public const BATCH_SIZE = 250; // 500 bookmarks
	public const INTERVAL = 30*60; // 30 minutes
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
	/**
	 * @var \OCP\Http\Client\IClientService
	 */
	private $clientService;
	/**
	 * @var \OCP\Http\Client\IClient
	 */
	private $client;

	public function __construct(
		IConfig $settings, BookmarkMapper $bookmarkMapper, BookmarkPreviewer $bookmarkPreviewer, FaviconPreviewer $faviconPreviewer, \OCP\Http\Client\IClientService $clientService
	) {
		$this->settings = $settings;
		$this->bookmarkMapper = $bookmarkMapper;
		$this->bookmarkPreviewer = $bookmarkPreviewer;
		$this->faviconPreviewer = $faviconPreviewer;

		$this->setInterval(self::INTERVAL);
		$this->client = $clientService->newClient();
	}

	protected function run($argument) {
		if ($this->settings->getAppValue('bookmarks', 'privacy.enableScraping', 'false') !== 'true') {
			return;
		}

		/** @var Bookmark[] $bookmarks */
		$bookmarks = $this->bookmarkMapper->findPendingPreviews(self::BATCH_SIZE, BookmarkPreviewer::CACHE_TTL);
		foreach ($bookmarks as $bookmark) {
			$available = $this->checkAvailability($bookmark);
			if ($available) {
				$this->bookmarkPreviewer->getImage($bookmark);
				$this->faviconPreviewer->getImage($bookmark);
			}
			$bookmark->markPreviewCreated();
			$bookmark->setAvailable($available);
			$this->bookmarkMapper->update($bookmark);
		}
	}

	protected function checkAvailability($bookmark) {
		try {
			$resp = $this->client->get($bookmark->getUrl());
			return $resp->getStatusCode() !== 404;
		} catch (\Exception $e) {
			return false;
		}
	}
}
