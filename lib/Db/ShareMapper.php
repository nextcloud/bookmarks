<?php

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

	public const TYPE_USER = 1;
	public const TYPE_GROUP = 2;

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
	 * @return array|Entity[]
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
	 * @return array|Entity[]
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
		$qb->select(array_map(function ($c) {
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
		$qb->select(array_map(function ($c) {
			return 's.' . $c;
		}, Share::$columns))
			->from('bookmarks_shares', 's')
			->leftJoin('s', 'bookmarks_shared', 'p', 's.id = p.share_id')
			->where($qb->expr()->eq('s.folder_id', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('p.user_id', $qb->createPositionalParameter($userId)));
		return $this->findEntity($qb);
	}

	/**
	 * @param string $owner
	 * @param string $userId
	 * @return Entity[]
	 */
	public function findByOwnerAndUser(string $owner, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(function ($c) {
			return 's.' . $c;
		}, Share::$columns))
			->from('bookmarks_shares', 's')
			->leftJoin('s', 'bookmarks_shared', 'p', 's.id = p.share_id')
			->where($qb->expr()->eq('s.owner', $qb->createPositionalParameter($owner)))
			->andWhere($qb->expr()->eq('p.user_id', $qb->createPositionalParameter($userId)));
		return $this->findEntities($qb);
	}

	public function insert(Entity $sharedFolder): Entity {
		$sharedFolder->setCreatedAt(time());
		return parent::insert($sharedFolder);
	}

	public function insertOrUpdate(Entity $sharedFolder): Entity {
		$sharedFolder->setCreatedAt(time());
		return parent::insertOrUpdate($sharedFolder);
	}
}
