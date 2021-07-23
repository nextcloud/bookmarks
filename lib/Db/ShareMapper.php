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
use OCP\IDBConnection;

/**
 * Class SharedFolderMapper
 *
 * @package OCA\Bookmarks\Db
 */
class ShareMapper extends QBMapper {

	/**
	 * @var IDBConnection
	 */
	protected $db;

	/**
	 * TagMapper constructor.
	 *
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bookmarks_shares', Share::class);
		$this->db = $db;
	}

	/**
	 * @param int $shareId
	 * @return Entity
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $shareId): Entity {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Share::$columns)
			->from('bookmarks_shares')
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($shareId)));
		return $this->findEntity($qb);
	}

	/**
	 * @param int $folderId
	 * @return Entity[]
	 */
	public function findByFolder(int $folderId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Share::$columns)
			->from('bookmarks_shares')
			->where($qb->expr()->eq('folder_id', $qb->createPositionalParameter($folderId)));
		return $this->findEntities($qb);
	}

	/**
	 * @param string $userId
	 *
	 * @return Entity[]
	 *
	 * @psalm-return array<array-key, Share>
	 */
	public function findByOwner(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Share::$columns)
			->from('bookmarks_shares')
			->where($qb->expr()->eq('owner', $qb->createPositionalParameter($userId)));
		return $this->findEntities($qb);
	}

	/**
	 * @param int $type
	 * @param string $participant
	 *
	 * @return Entity[]
	 *
	 * @psalm-return array<array-key, Share>
	 */
	public function findByParticipant(int $type, string $participant): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Share::$columns)
			->from('bookmarks_shares')
			->where($qb->expr()->eq('participant', $qb->createPositionalParameter($participant)))
			->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter($type)));
		return $this->findEntities($qb);
	}

	/**
	 * @param int $folderId
	 * @param int $type
	 * @param string $participant
	 * @return Entity
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByFolderAndParticipant(int $folderId, int $type, string $participant): Entity {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 's.' . $c;
		}, Share::$columns))
			->from('bookmarks_shares')
			->where($qb->expr()->eq('folder_id', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('participant', $qb->createPositionalParameter($participant)))
			->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter($type)));
		return $this->findEntity($qb);
	}

	/**
	 * @param int $folderId
	 * @param string $userId
	 * @return Entity
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByFolderAndUser(int $folderId, string $userId): Entity {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 's.' . $c;
		}, Share::$columns))
			->from('bookmarks_shares', 's')
			->leftJoin('s', 'bookmarks_shared_to_shares', 't', 's.id = t.share_id')
			->leftJoin('t', 'bookmarks_shared_folders', 'sf', 'sf.id = t.shared_folder_id')
			->where($qb->expr()->eq('s.folder_id', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('sf.user_id', $qb->createPositionalParameter($userId)));
		return $this->findEntity($qb);
	}

	/**
	 * @param string $owner
	 * @param string $userId
	 * @return Entity[]
	 */
	public function findByOwnerAndUser(string $owner, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 's.' . $c;
		}, Share::$columns))
			->from('bookmarks_shares', 's')
			->leftJoin('s', 'bookmarks_shared_to_shares', 't', 's.id = t.share_id')
			->leftJoin('t', 'bookmarks_shared_folders', 'sf', 't.shared_folder_id = sf.id')
			->where($qb->expr()->eq('s.owner', $qb->createPositionalParameter($owner)))
			->andWhere($qb->expr()->eq('sf.user_id', $qb->createPositionalParameter($userId)));
		return $this->findEntities($qb);
	}

	public function insert(Entity $entity): Entity {
		$entity->setCreatedAt(time());
		return parent::insert($entity);
	}

	public function insertOrUpdate(Entity $entity): Entity {
		$entity->setCreatedAt(time());
		return parent::insertOrUpdate($entity);
	}

	public function findBySharedFolder(int $id): Entity {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 's.' . $c;
		}, Share::$columns))
			->from('bookmarks_shares', 's')
			->innerJoin('s', 'bookmarks_shared_to_shares', 't', 's.id = t.share_id')
			->where($qb->expr()->eq('t.shared_folder_id', $qb->createPositionalParameter($id)));
		return $this->findEntity($qb);
	}

	/**
	 * @param string $userId
	 * @return array
	 */
	public function findByUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 's.' . $c;
		}, Share::$columns))
			->from('bookmarks_shares', 's')
			->leftJoin('s', 'bookmarks_shared_to_shares', 't', 's.id = t.share_id')
			->leftJoin('t', 'bookmarks_shared_folders', 'sf', 'sf.id = t.shared_folder_id')
			->where($qb->expr()->eq('sf.user_id', $qb->createPositionalParameter($userId)));

		return $this->findEntities($qb);
	}
}
