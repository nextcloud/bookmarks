<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;

/**
 * Class FolderMapper
 *
 * @package OCA\Bookmarks\Db
 * @template-extends QBMapper<Folder>
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
	 * @param ShareMapper $shareMapper
	 * @param SharedFolderMapper $sharedFolderMapper
	 * @param IEventDispatcher $eventDispatcher
	 */
	public function __construct(IDBConnection $db, ShareMapper $shareMapper, SharedFolderMapper $sharedFolderMapper, IEventDispatcher $eventDispatcher) {
		parent::__construct($db, 'bookmarks_folders', Folder::class);
		$this->shareMapper = $shareMapper;
		$this->sharedFolderMapper = $sharedFolderMapper;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * @param int $id
	 * @return Folder
	 * @throws DoesNotExistException if not found
	 * @throws MultipleObjectsReturnedException if more than one result
	 * @throws Exception
	 */
	public function find(int $id): Folder {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks_folders')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/**
	 * @param string $userId
	 *
	 * @return Folder
	 * @throws Exception
	 */
	public function findRootFolder(string $userId): Folder {
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
					'folder_id' => $qb->createPositionalParameter($rootFolder->getId(), IQueryBuilder::PARAM_INT),
				])
				->executeStatement();
		} catch (MultipleObjectsReturnedException $e) {
			$rootFolders = $this->findEntities($qb);
			return $rootFolders[0];
		}
		return $rootFolder;
	}


	/**
	 * @param Entity $entity
	 * @psalm-param Folder $entity
	 * @return Folder
	 * @throws Exception
	 */
	public function update(Entity $entity): Folder {
		parent::update($entity);
		return $entity;
	}

	/**
	 * @param Entity $entity
	 * @psalm-param Folder $entity
	 * @return Folder
	 * @throws Exception
	 */
	public function insert(Entity $entity): Entity {
		parent::insert($entity);
		return $entity;
	}
}
