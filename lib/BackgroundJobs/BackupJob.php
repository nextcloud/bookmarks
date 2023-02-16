<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\BackgroundJobs;

use OCA\Bookmarks\Service\BackupManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class BackupJob extends TimedJob {
	// MAX 2880 people's bookmarks can be backupped per day
	public const DEFAULT_BATCH_SIZE = 10; // 10 accounts
	public const DEFAULT_INTERVAL = 5 * 60; // 5 minutes

	/**
	 * @var BookmarkMapper
	 */
	private $bookmarkMapper;
	/**
	 * @var IClientService
	 */
	private $clientService;
	/**
	 * @var IClient
	 */
	private $client;
	/**
	 * @var ITimeFactory
	 */
	private $timeFactory;
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var BackupManager
	 */
	private $backupManager;
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IUserSession
	 */
	private $session;

	/**
	 * @var int
	 */
	private $batchSize;

	public function __construct(
		BookmarkMapper $bookmarkMapper, ITimeFactory $timeFactory, IUserManager $userManager, BackupManager $backupManager, LoggerInterface $logger, IConfig $config, IUserSession $session
	) {
		parent::__construct($timeFactory);
		$this->bookmarkMapper = $bookmarkMapper;

		$interval = (int) $this->config->getSystemValue('bookmarks.backupjob.interval', self::DEFAULT_INTERVAL);
		$this->setInterval($interval);
		$this->batchSize = (int) $this->config->getSystemValue('bookmarks.backupjob.batch_size', self::DEFAULT_BATCH_SIZE);
		$this->userManager = $userManager;
		$this->backupManager = $backupManager;
		$this->logger = $logger;
		$this->config = $config;
		$this->session = $session;
	}

	protected function run($argument) {
		$userIds = $this->config->getUsersForUserValue('bookmarks', 'backup.enabled', (string) true);

		$processed = 0;
		do {
			$userId = array_pop($userIds);
			$user = $this->userManager->get($userId);
			if (!$user) {
				continue;
			}
			try {
				if ($this->bookmarkMapper->countBookmarksOfUser($userId) === 0) {
					continue;
				}
				$this->session->setUser($user);
				if ($this->backupManager->backupExistsForToday($userId)) {
					continue;
				}
				$this->backupManager->runBackup($userId);
				$this->backupManager->cleanupOldBackups($userId);
				$processed++;
				
			} catch (\Exception $e) {
				$this->logger->warning('Bookmarks backup for user '.$userId.'errored');
				$this->logger->warning($e->getMessage());
				continue;
			}
		} while ($processed < $this->batchSize && count($userIds) > 0);
	}
}
