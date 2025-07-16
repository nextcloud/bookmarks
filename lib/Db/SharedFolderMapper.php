<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Db;

use OCA\Bookmarks\Events\CreateEvent;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;

/**
 * Class SharedFolderMapper
 *
 * @package OCA\Bookmarks\Db
 * @template-extends QBMapper<SharedFolder>
 */
class SharedFolderMapper extends QBMapper {
	/**
	 * @var IDBConnection
	 */
	protected $db;
	/**
	 * @var IEventDispatcher
	 */
	private $eventDispatcher;

	/**
	 * TagMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param IEventDispatcher $eventDispatcher
	 */
	public function __construct(IDBConnection $db, IEventDispatcher $eventDispatcher) {
		parent::__construct($db, 'bookmarks_shared_folders', SharedFolder::class);
		$this->db = $db;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * @param int $id
	 * @return SharedFolder
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id): SharedFolder {
		$qb = $this->db->getQueryBuilder();
		$qb->select(SharedFolder::$columns)
			->from('bookmarks_shared_folders', 'sf')
			->where($qb->expr()->eq('sf.id', $qb->createPositionalParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @param int $shareId
	 * @return SharedFolder[]
	 */
	public function findByShare(int $shareId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(SharedFolder::$columns)
			->from('bookmarks_shared_folders', 'sf')
			->join('sf', 'bookmarks_shared_to_shares', 't', $qb->expr()->eq('sf.id', 't.shared_folder_id'))
			->where($qb->expr()->eq('t.share_id', $qb->createPositionalParameter($shareId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * @param int $folderId
	 * @return SharedFolder[]
	 */
	public function findByFolder(int $folderId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 'sf.' . $c;
		}, SharedFolder::$columns))
			->from('bookmarks_shared_folders', 'sf')
			->where($qb->expr()->eq('sf.folder_id', $qb->createPositionalParameter($folderId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * @param string $userId
	 *
	 * @return SharedFolder[]
	 *
	 * @psalm-return list<SharedFolder>
	 */
	public function findByOwner(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 'p.' . $c;
		}, SharedFolder::$columns))
			->from('bookmarks_shared_folders', 'p')
			->leftJoin('p', 'bookmarks_shared_to_shares', 't', 't.shared_folder_id = p.id')
			->leftJoin('t', 'bookmarks_shares', 's', 't.share_id = s.id')
			->where($qb->expr()->eq('s.owner', $qb->createPositionalParameter($userId)));
		return $this->findEntities($qb);
	}

	/**
	 * @param int $type
	 * @param string $participant
	 *
	 * @return SharedFolder[]
	 *
	 * @psalm-return list<SharedFolder>
	 */
	public function findByParticipant(int $type, string $participant): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 'p.' . $c;
		}, SharedFolder::$columns))
			->from('bookmarks_shared_folders', 'p')
			->leftJoin('p', 'bookmarks_shared_to_shares', 't', 'p.id = t.shared_folder_id')
			->leftJoin('t', 'bookmarks_shares', 's', 't.share_id = s.id')
			->where($qb->expr()->eq('s.participant', $qb->createPositionalParameter($participant)))
			->andWhere($qb->expr()->eq('s.type', $qb->createPositionalParameter($type, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * @param int $folderId
	 * @param int $type
	 * @param string $participant
	 * @return SharedFolder
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByFolderAndParticipant(int $folderId, int $type, string $participant): SharedFolder {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 'p.' . $c;
		}, SharedFolder::$columns))
			->from('bookmarks_shared_folders', 'p')
			->leftJoin('p', 'bookmarks_shared_to_shares', 't', 't.shared_folder_id = p.id')
			->leftJoin('t', 'bookmarks_shares', 's', 't.share_id = s.id')
			->where($qb->expr()->eq('p.folder_id', $qb->createPositionalParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('participant', $qb->createPositionalParameter($participant)))
			->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter($type)));
		return $this->findEntity($qb);
	}

	/**
	 * @param int $folderId
	 * @param string $userId
	 * @return SharedFolder
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByFolderAndUser(int $folderId, string $userId): SharedFolder {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 'p.' . $c;
		}, SharedFolder::$columns))
			->from('bookmarks_shared_folders', 'p')
			->where($qb->expr()->eq('p.folder_id', $qb->createPositionalParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('p.user_id', $qb->createPositionalParameter($userId)));
		return $this->findEntity($qb);
	}

	/**
	 * @param string $owner
	 * @param string $userId
	 * @return SharedFolder[]
	 */
	public function findByOwnerAndUser(string $owner, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 'p.' . $c;
		}, SharedFolder::$columns))
			->from('bookmarks_shared_folders', 'p')
			->leftJoin('p', 'bookmarks_shared_to_shares', 't', 'p.id = t.shared_folder_id')
			->leftJoin('t', 'bookmarks_shares', 's', 't.share_id = s.id')
			->where($qb->expr()->eq('s.owner', $qb->createPositionalParameter($owner)))
			->andWhere($qb->expr()->eq('p.user_id', $qb->createPositionalParameter($userId)));
		return $this->findEntities($qb);
	}

	/**
	 * @param int $shareId
	 * @param string $userId
	 * @return SharedFolder
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findByShareAndUser(int $shareId, string $userId): SharedFolder {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 'p.' . $c;
		}, SharedFolder::$columns))
			->from('bookmarks_shared_folders', 'p')
			->leftJoin('p', 'bookmarks_shared_to_shares', 't', 't.shared_folder_id = p.id')
			->where($qb->expr()->eq('t.share_id', $qb->createPositionalParameter($shareId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('p.user_id', $qb->createPositionalParameter($userId)));
		return $this->findEntity($qb);
	}

	/**
	 * @return SharedFolder[]
	 *
	 * @psalm-return array<array-key, SharedFolder>
	 */
	public function findByParticipantAndUser(int $type, string $participant, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 'p.' . $c;
		}, SharedFolder::$columns))
			->from('bookmarks_shared_folders', 'p')
			->leftJoin('p', 'bookmarks_shared_to_shares', 't', 't.shared_folder_id = p.id')
			->leftJoin('t', 'bookmarks_shares', 's', 't.share_id = s.id')
			->where($qb->expr()->eq('s.participant', $qb->createPositionalParameter($participant)))
			->andWhere($qb->expr()->eq('s.type', $qb->createPositionalParameter($type, IQueryBuilder::PARAM_INTy)))
			->andWhere($qb->expr()->eq('p.user_id', $qb->createPositionalParameter($userId)));
		return $this->findEntities($qb);
	}

	public function delete(Entity $entity): SharedFolder {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('bookmarks_shared_to_shares')
			->where($qb->expr()->eq('shared_folder_id', $qb->createPositionalParameter($entity->getId(), IQueryBuilder::PARAM_INT)))
			->execute();
		return parent::delete($entity);
	}

	public function mount(int $id, int $share_id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('bookmarks_shared_to_shares')->values([
			'shared_folder_id' => $qb->createPositionalParameter($id),
			'share_id' => $qb->createPositionalParameter($share_id, IQueryBuilder::PARAM_INT)
		])->execute();
		$this->eventDispatcher->dispatch(CreateEvent::class, new CreateEvent(
			TreeMapper::TYPE_SHARE,
			$id
		));
	}

	/**
	 * @return SharedFolder[]
	 *
	 * @psalm-return array<array-key, SharedFolder>
	 * @throws Exception
	 */
	public function findByUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 'sf.' . $c;
		}, SharedFolder::$columns))
			->from('bookmarks_shared_folders', 'sf')
			->where($qb->expr()->eq('sf.user_id', $qb->createPositionalParameter($userId)));
		return $this->findEntities($qb);
	}
}
