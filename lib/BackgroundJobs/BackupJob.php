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
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class BackupJob extends TimedJob {
	public const INTERVAL = 15 * 60; // 15 minutes
	public const MAX_RUN_TIME = 5 * 60; // 5 minutes

	private BookmarkMapper $bookmarkMapper;
	private ITimeFactory $timeFactory;
	private IUserManager $userManager;
	private BackupManager $backupManager;
	private LoggerInterface $logger;
	private IConfig $config;
	private IUserSession $session;

	public function __construct(
		BookmarkMapper $bookmarkMapper, ITimeFactory $timeFactory, IUserManager $userManager, BackupManager $backupManager, LoggerInterface $logger, IConfig $config, IUserSession $session
	) {
		parent::__construct($timeFactory);
		$this->bookmarkMapper = $bookmarkMapper;

		$this->setInterval(self::INTERVAL);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
		$this->timeFactory = $timeFactory;
		$this->userManager = $userManager;
		$this->backupManager = $backupManager;
		$this->logger = $logger;
		$this->config = $config;
		$this->session = $session;
	}

	protected function run($argument) {
		$userIds = $this->config->getUsersForUserValue('bookmarks', 'backup.enabled', (string) true);
		if (empty($userIds)) {
			return;
		}

		$startTime = $this->timeFactory->getTime();
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

				if ($this->backupManager->backupExistsForToday($userId)) {
					continue;
				}
				$this->session->setUser($user);
				$this->backupManager->runBackup($userId);
				$this->backupManager->cleanupOldBackups($userId);
			} catch (\Exception $e) {
				$this->logger->error('Bookmarks backup for user '.$userId.' errored');
				$this->logger->error($e->getMessage());
				continue;
			}
		} while ($startTime + self::MAX_RUN_TIME > $this->timeFactory->getTime() && !empty($userIds));
	}
}
