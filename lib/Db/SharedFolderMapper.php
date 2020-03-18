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
class SharedFolderMapper extends QBMapper {

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
		parent::__construct($db, 'bookmarks_shared_folders', SharedFolder::class);
		$this->db = $db;
	}

	/**
	 * @param int $shareId
	 * @return Entity[]
	 */
	public function findByShare(int $shareId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(SharedFolder::$columns)
			->from('bookmarks_shared_folders')
			->where($qb->expr()->eq('share_id', $qb->createPositionalParameter($shareId)));
		return $this->findEntities($qb);
	}

	/**
	 * @param int $folderId
	 * @return Entity[]
	 */
	public function findByFolder(int $folderId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 'p.' . $c;
		}, SharedFolder::$columns))
			->from('bookmarks_shared_folders', 'p')
			->leftJoin('p', 'bookmarks_shares', 's', 'p.share_id = s.id')
			->where($qb->expr()->eq('s.folder_id', $qb->createPositionalParameter($folderId)));
		return $this->findEntities($qb);
	}

	/**
	 * @param string $userId
	 * @return array|Entity[]
	 */
	public function findByOwner(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 'p.' . $c;
		}, SharedFolder::$columns))
			->from('bookmarks_shared_folders', 'p')
			->leftJoin('p', 'bookmarks_shares', 's', 'p.share_id = s.id')
			->where($qb->expr()->eq('s.owner', $qb->createPositionalParameter($userId)));
		return $this->findEntities($qb);
	}

	/**
	 * @param int $type
	 * @param string $participant
	 * @return array|Entity[]
	 */
	public function findByParticipant(int $type, string $participant): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(function ($c) {
			return 'p.' . $c;
		}, SharedFolder::$columns))
			->from('bookmarks_shared_folders', 'p')
			->leftJoin('p', 'bookmarks_shares', 's', 'p.share_id = s.id')
			->where($qb->expr()->eq('s.participant', $qb->createPositionalParameter($participant)))
			->andWhere($qb->expr()->eq('s.type', $qb->createPositionalParameter($type)));
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
			return 'p.' . $c;
		}, SharedFolder::$columns))
			->from('bookmarks_shared_folders', 'p')
			->leftJoin('p', 'bookmarks_shares', 's', 'p.share_id = s.id')
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
			return 'p.' . $c;
		}, SharedFolder::$columns))
			->from('bookmarks_shared_folders', 'p')
			->leftJoin('p', 'bookmarks_shares', 's', 'p.share_id = s.id')
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
		$$qb->select(array_map(static function ($c) {
			return 'p.' . $c;
		}, SharedFolder::$columns))
			->from('bookmarks_shared_folders', 'p')
			->leftJoin('p', 'bookmarks_shares', 's', 'p.share_id = s.id')
			->where($qb->expr()->eq('s.owner', $qb->createPositionalParameter($owner)))
			->andWhere($qb->expr()->eq('p.user_id', $qb->createPositionalParameter($userId)));
		return $this->findEntities($qb);
	}

	/**
	 * @param int $shareId
	 * @param int $userId
	 * @return Entity
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByShareAndUser(int $shareId, int $userId): Entity {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($c) {
			return 'p.' . $c;
		}, SharedFolder::$columns))
			->from('bookmarks_shared_folder', 'p')
			->leftJoin('p', 'bookmarks_shares', 's', 'p.share_id = s.id')
			->where($qb->expr()->eq('s.share_id', $qb->createPositionalParameter($shareId)))
			->andWhere($qb->expr()->eq('p.user_id', $qb->createPositionalParameter($userId)));
		return $this->findEntity($qb);
	}
}
