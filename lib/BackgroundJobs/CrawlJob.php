<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\BackgroundJobs;

use OC\BackgroundJob\TimedJob;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Service\BookmarkPreviewer;
use OCA\Bookmarks\Service\CrawlService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;

class CrawlJob extends TimedJob {
	public const BATCH_SIZE = 120; // 120 bookmarks
	public const INTERVAL = 30 * 60; // 30 minutes

	/**
	 * @var BookmarkMapper
	 */
	private $bookmarkMapper;
	/**
	 * @var IConfig
	 */
	private $settings;
	/**
	 * @var IClientService
	 */
	private $clientService;
	/**
	 * @var IClient
	 */
	private $client;
	/**
	 * @var CrawlService
	 */
	private $crawler;

	public function __construct(
		IConfig $settings, BookmarkMapper $bookmarkMapper, CrawlService $crawler
	) {
		$this->settings = $settings;
		$this->bookmarkMapper = $bookmarkMapper;

		$this->setInterval(self::INTERVAL);
		$this->crawler = $crawler;
	}

	protected function run($argument) {
		if ($this->settings->getAppValue('bookmarks', 'privacy.enableScraping', 'false') !== 'true') {
			return;
		}

		/** @var Bookmark[] $bookmarks */
		$bookmarks = $this->bookmarkMapper->findPendingPreviews(self::BATCH_SIZE, BookmarkPreviewer::CACHE_TTL);
		foreach ($bookmarks as $bookmark) {
			$this->crawler->crawl($bookmark);
		}
	}
}
