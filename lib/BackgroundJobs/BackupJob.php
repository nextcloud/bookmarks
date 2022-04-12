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
use Psr\Log\LoggerInterface;

class BackupJob extends TimedJob {
	// MAX 2880 people's bookmarks can be backupped per day
	public const BATCH_SIZE = 10; // 10 accounts
	public const INTERVAL = 5 * 60; // 5 minutes

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

	public function __construct(
		BookmarkMapper $bookmarkMapper, ITimeFactory $timeFactory, IUserManager $userManager, BackupManager $backupManager, LoggerInterface $logger, IConfig $config
	) {
		parent::__construct($timeFactory);
		$this->bookmarkMapper = $bookmarkMapper;

		$this->setInterval(self::INTERVAL);
		$this->userManager = $userManager;
		$this->backupManager = $backupManager;
		$this->logger = $logger;
		$this->config = $config;
	}

	protected function run($argument) {
		$users = [];
		$this->userManager->callForSeenUsers(function (IUser $user) use (&$users) {
			$users[] = $user->getUID();
		});

		$processed = 0;
		do {
			$user = array_pop($users);
			if (!$user) {
				return;
			}
			try {
				if ($this->bookmarkMapper->countBookmarksOfUser($user) === 0) {
					continue;
				}
				if (!$this->config->getUserValue($user, 'bookmarks', 'backup.enabled', true)) {
					continue;
				}
				if ($this->backupManager->backupExistsForToday($user)) {
					continue;
				}
				$this->backupManager->runBackup($user);
				$this->backupManager->cleanupOldBackups($user);
				$processed++;
			} catch (\Exception $e) {
				$this->logger->warning('Bookmarks backup for user '.$user.'errored');
				$this->logger->warning($e->getMessage());
				continue;
			}
		} while ($processed < self::BATCH_SIZE);
	}
}
