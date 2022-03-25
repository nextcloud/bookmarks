<?php
/*
 * Copyright (c) 2022. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use DateTime;
use DateTimeImmutable;
use OCP\Files\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;

class BackupManager {

	/**
	 * @var string
	 */
	public const COMMENT = '<!-- Created by Nextcloud Bookmarks -->';

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var string
	 */
	private $appName;
	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var HtmlExporter
	 */
	private $htmlExporter;
	/**
	 * @var FolderMapper
	 */
	private $folderMapper;
	/**
	 * @var ITimeFactory
	 */
	private $time;
	/**
	 * @var IRootFolder
	 */
	private $rootFolder;

	public function __construct(string $appName, IConfig $config, IL10N $l, HtmlExporter $htmlExporter, FolderMapper $folderMapper, ITimeFactory $time, IRootFolder $rootFolder) {
		$this->appName = $appName;
		$this->config = $config;
		$this->l = $l;
		$this->htmlExporter = $htmlExporter;
		$this->folderMapper = $folderMapper;
		$this->time = $time;
		$this->rootFolder = $rootFolder;
	}

	public function injectTimeFactory(ITimeFactory $time) {
		$this->time = $time;
	}

	/**
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 * @throws \Exception
	 */
	public function backupExistsForToday(string $userId) {
		$path = $this->getBackupFilePathForDate($userId, $this->time->getDateTime()->getTimestamp());
		$userFolder = $this->rootFolder->getUserFolder($userId);
		return $userFolder->nodeExists($path);
	}

	/**
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCA\Bookmarks\Exception\UnauthorizedAccessError
	 * @throws \OC\User\NoUserException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \Exception
	 */
	public function runBackup($userId) {
		$rootFolder = $this->folderMapper->findRootFolder($userId);
		$exportedHTML = $this->htmlExporter->exportFolder($userId, $rootFolder->getId());

		$userFolder = $this->rootFolder->getUserFolder($userId);
		$folderPath = $this->getBackupFolderPath($userId);
		if (!$userFolder->nodeExists($folderPath)) {
			$userFolder->newFolder($folderPath);
		}
		$backupFilePath = $this->getBackupFilePathForDate($userId, $this->time->getDateTime()->getTimestamp());
		$file = $userFolder->newFile($backupFilePath);
		$file->putContent($exportedHTML.self::COMMENT);
	}

	private function getBackupFolderPath(string $userId):string {
		return $this->config->getUserValue(
			$userId,
			$this->appName,
			'backup.filePath',
			$this->l->t('Bookmarks Backups')
		);
	}

	/**
	 * @throws \Exception
	 */
	private function getBackupFilePathForDate(string $userId, int $time) {
		$date = DateTime::createFromFormat('U', (string)$time);
		return $this->getBackupFolderPath($userId) . '/' . $date->format('Y-m-d') . '.html';
	}

	/**
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function getBackupFolder(string $userId) : ?Folder {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$backupFolder = $userFolder->get($this->getBackupFolderPath($userId));
		if (!($backupFolder instanceof Folder)) {
			return null;
		}
		return $backupFolder;
	}

	/**
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \Exception
	 */
	public function cleanupOldBackups($userId) {
		$backupFolder = $this->getBackupFolder($userId);
		if ($backupFolder === null) {
			return;
		}
		$today = DateTimeImmutable::createFromMutable($this->time->getDateTime()->setTime(0, 0));
		$daysToKeep = [];
		$weeksToKeep = [];
		$monthsToKeep = [];
		// 7 days
		for ($i = 0; $i < 7; $i++) {
			$daysToKeep[] = $today->sub(new \DateInterval('P'.$i.'D'));
		}
		// 5 weeks
		for ($i = 1; $i < 5; $i++) {
			$weeksToKeep[] = $today->modify('Monday this week')->sub(new \DateInterval('P'.$i.'W'));
		}
		// 6 months
		for ($i = 1; $i < 6; $i++) {
			$monthsToKeep[] = $today->modify('first day of')->sub(new \DateInterval('P'.$i.'M'));
		}
		$nodes = $backupFolder->getDirectoryListing();
		foreach ($nodes as $node) {
			if (!str_ends_with($node->getName(), '.html')) {
				continue;
			}
			$date = new DateTime(basename($node->getName(), '.html'));
			$matchingDays = count(array_filter($daysToKeep, function ($dayToKeep) use ($date) {
				return $date->diff($dayToKeep)->days === 0;
			}));
			$matchingWeeks = count(array_filter($weeksToKeep, function ($weekToKeep) use ($date) {
				return $date->diff($weekToKeep)->days === 0;
			}));
			$matchingMonths = count(array_filter($monthsToKeep, function ($monthToKeep) use ($date) {
				return abs($date->diff($monthToKeep)->days) < 6;
			}));
			if ($matchingDays || $matchingWeeks || $matchingMonths) {
				continue;
			}
			if (!($contents = $node->getStorage()->file_get_contents($node->getInternalPath()))) {
				continue;
			}
			if (!str_contains($contents, self::COMMENT)) {
				continue;
			}
			$node->delete();
		}
	}

	/**
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \Exception
	 */
	public function cleanupAllBackups($userId) {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		if (!$userFolder->nodeExists($this->getBackupFolderPath($userId))) {
			return;
		}
		$backupFolder = $userFolder->get($this->getBackupFolderPath($userId));
		if (!($backupFolder instanceof Folder)) {
			return;
		}
		$nodes = $backupFolder->getDirectoryListing();
		foreach ($nodes as $node) {
			if (!str_ends_with($node->getName(), '.html')) {
				continue;
			}
			$node->delete();
		}
	}
}
