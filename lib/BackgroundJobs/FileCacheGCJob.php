<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\BackgroundJobs;

use OC\BackgroundJob\TimedJob;
use OCA\Bookmarks\Service\FileCache;
use OCP\Files\NotPermittedException;
use Psr\Log\LoggerInterface;

class FileCacheGCJob extends TimedJob {
	public const INTERVAL = 30 * 60; // 30 minutes

	/**
	 * @var FileCache
	 */
	private $fileCache;
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct(
		FileCache $fileCache, LoggerInterface $logger
	) {
		$this->setInterval(self::INTERVAL);
		$this->fileCache = $fileCache;
		$this->logger = $logger;
	}

	protected function run($argument) {
		try {
			$this->fileCache->gc();
		} catch (NotPermittedException $e) {
			$this->logger->error('Could not collect garbage: '.$e->getMessage());
		}
	}
}
