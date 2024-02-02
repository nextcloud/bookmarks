<?php
/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\BackgroundJobs;

use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Service\CrawlService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\Job;
use OCP\IConfig;

class IndividualCrawlJob extends Job {
	/**
	 * @var BookmarkMapper
	 */
	private $bookmarkMapper;
	/**
	 * @var IConfig
	 */
	private $settings;
	/**
	 * @var CrawlService
	 */
	private $crawler;
	/**
	 * @var IJobList
	 */
	private $jobList;

	public function __construct(
		IConfig $settings, BookmarkMapper $bookmarkMapper, CrawlService $crawler, ITimeFactory $timeFactory, IJobList $jobList
	) {
		parent::__construct($timeFactory);
		$this->settings = $settings;
		$this->bookmarkMapper = $bookmarkMapper;
		$this->crawler = $crawler;
		$this->jobList = $jobList;
	}

	protected function run($argument) {
		$this->jobList->remove($this, $argument);
		if ($this->settings->getAppValue('bookmarks', 'privacy.enableScraping', 'false') !== 'true') {
			return;
		}

		/** @var Bookmark $bookmarks */
		try {
			$bookmark = $this->bookmarkMapper->find($argument);
		} catch (DoesNotExistException $e) {
			return;
		} catch (MultipleObjectsReturnedException $e) {
			return;
		}
		$this->crawler->crawl($bookmark);
	}
}
