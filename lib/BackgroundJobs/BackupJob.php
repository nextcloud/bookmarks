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
	/**
	 * @var IUserSession
	 */
	private $session;

	public function __construct(
		BookmarkMapper $bookmarkMapper, ITimeFactory $timeFactory, IUserManager $userManager, BackupManager $backupManager, LoggerInterface $logger, IConfig $config, IUserSession $session
	) {
		parent::__construct($timeFactory);
		$this->bookmarkMapper = $bookmarkMapper;

		$this->setInterval(self::INTERVAL);
		$this->userManager = $userManager;
		$this->backupManager = $backupManager;
		$this->logger = $logger;
		$this->config = $config;
		$this->session = $session;
	}

	protected function run($argument) {
		$users = [];
		$this->userManager->callForSeenUsers(function (IUser $user) use (&$users) {
			$users[] = $user;
		});

		$processed = 0;
		do {
			$user = array_pop($users);
			if (!$user) {
				return;
			}
			$userId = $user->getUID();
			try {
				if ($this->bookmarkMapper->countBookmarksOfUser($userId) === 0) {
					continue;
				}
				if ($this->config->getUserValue($userId, 'bookmarks', 'backup.enabled', (string) false) === (string) true) {
					$this->session->setUser($user);
					if ($this->backupManager->backupExistsForToday($userId)) {
						continue;
					}
					$this->backupManager->runBackup($userId);
					$this->backupManager->cleanupOldBackups($userId);
					$processed++;
				}
			} catch (\Exception $e) {
				$this->logger->warning('Bookmarks backup for user '.$userId.'errored');
				$this->logger->warning($e->getMessage());
				continue;
			}
		} while ($processed < self::BATCH_SIZE);
	}
}
