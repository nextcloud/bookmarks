<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;

/**
 * Class FolderMapper
 *
 * @package OCA\Bookmarks\Db
 */
class FolderMapper extends QBMapper {

	/**
	 * @var BookmarkMapper
	 */
	protected $bookmarkMapper;

	/**
	 * @var SharedFolderMapper
	 */
	protected $sharedFolderMapper;


	/**
	 * @var ShareMapper
	 */
	protected $shareMapper;

	/**
	 * @var IEventDispatcher
	 */
	protected $eventDispatcher;

	/**
	 * FolderMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param BookmarkMapper $bookmarkMapper
	 * @param ShareMapper $shareMapper
	 * @param SharedFolderMapper $sharedFolderMapper
	 * @param IEventDispatcher $eventDispatcher
	 */
	public function __construct(IDBConnection $db, BookmarkMapper $bookmarkMapper, ShareMapper $shareMapper, SharedFolderMapper $sharedFolderMapper, IEventDispatcher $eventDispatcher) {
		parent::__construct($db, 'bookmarks_folders', Folder::class);
		$this->bookmarkMapper = $bookmarkMapper;
		$this->shareMapper = $shareMapper;
		$this->sharedFolderMapper = $sharedFolderMapper;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * @param int $id
	 * @return Entity
	 * @throws DoesNotExistException if not found
	 * @throws MultipleObjectsReturnedException if more than one result
	 */
	public function find(int $id): Entity {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks_folders')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		return $this->findEntity($qb);
	}

	/**
	 * @param $userId
	 * @return Entity
	 * @throws MultipleObjectsReturnedException
	 */
	public function findRootFolder($userId): Entity {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select(array_map(static function ($col) {
				return 'f.' . $col;
			}, Folder::$columns))
			->from('bookmarks_folders', 'f')
			->join('f', 'bookmarks_root_folders', 'r', $qb->expr()->eq('id', 'folder_id'))
			->where($qb->expr()->eq('r.user_id', $qb->createNamedParameter($userId)));
		try {
			$rootFolder = $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			$rootFolder = new Folder();
			$rootFolder->setUserId($userId);
			$rootFolder->setTitle('');
			$this->insert($rootFolder);

			$qb = $this->db->getQueryBuilder();
			$qb
				->insert('bookmarks_root_folders')
				->values([
					'user_id' => $qb->createPositionalParameter($userId),
					'folder_id' => $qb->createPositionalParameter($rootFolder->getId()),
				])
				->execute();
		}
		return $rootFolder;
	}


	/**
	 * @param Entity $entity
	 * @return Entity
	 */
	public function update(Entity $entity): Entity {
		parent::update($entity);
		return $entity;
	}

	/**
	 * @param Entity $entity
	 * @return Entity
	 */
	public function insert(Entity $entity): Entity {
		parent::insert($entity);
		return $entity;
	}
}
