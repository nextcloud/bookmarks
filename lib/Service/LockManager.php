<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use DateTime;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\Types;
use OCP\IDBConnection;

class LockManager {
	public const TIMEOUT = 60 * 30; // half an hour

	/**
	 * @var IDBConnection
	 */
	private $db;
	/**
	 * @var FolderMapper
	 */
	private $folderMapper;

	public function __construct(IDBConnection $db, FolderMapper $folderMapper) {
		$this->db = $db;
		$this->folderMapper = $folderMapper;
	}

	/**
	 * @param string $userId
	 * @param bool $locked
	 */
	public function setLock(string $userId, bool $locked): void {
		$this->folderMapper->findRootFolder($userId);
		$value = $locked ? new DateTime() : new DateTime('@0'); // now or begin of UNIX time
		$qb = $this->db->getQueryBuilder();
		$qb->update('bookmarks_root_folders')
			->set('locked_time', $qb->createNamedParameter($value, Types::DATETIME))
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->execute();
	}

	/**
	 * @param string $userId
	 * @return bool
	 */
	public function getLock(string $userId): bool {
		$this->folderMapper->findRootFolder($userId);
		$qb = $this->db->getQueryBuilder();
		$lockedAt = $qb->select('locked_time')
			->from('bookmarks_root_folders')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->execute()
			->fetch(\PDO::FETCH_COLUMN);
		if ($lockedAt === null) {
			return false;
		}
		try {
			$dateTime = new DateTime($lockedAt);
		} catch (\Exception $e) {
			return false;
		}
		return time() - $dateTime->getTimestamp() < self::TIMEOUT;
	}
}
