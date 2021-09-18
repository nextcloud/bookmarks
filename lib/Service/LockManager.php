<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\Types;
use OCP\IDBConnection;

class LockManager {

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
		$qb = $this->db->getQueryBuilder();
		$qb->update('bookmarks_root_folders')
			->set('locked', $qb->createNamedParameter($locked, Types::BOOLEAN))
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
		$locked = $qb->select('locked')
			->from('bookmarks_root_folders')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->execute()
			->fetch(\PDO::FETCH_COLUMN);
		return (bool) $locked;
	}
}
