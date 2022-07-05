<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Db;

use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PDO;
use function call_user_func;

/**
 * Class TreeMapper
 *
 * @package OCA\Bookmarks\Db
 */
class TrashMapper extends QBMapper {
	public const TYPE_SHARE = 'share';
	public const TYPE_FOLDER = 'folder';
	public const TYPE_BOOKMARK = 'bookmark';

	protected $entityClasses = [
		self::TYPE_SHARE => SharedFolder::class,
		self::TYPE_FOLDER => Folder::class,
		self::TYPE_BOOKMARK => Bookmark::class,
	];

	protected $entityTables = [
		self::TYPE_SHARE => 'bookmarks_shared_folders',
		self::TYPE_FOLDER => 'bookmarks_folders',
		self::TYPE_BOOKMARK => 'bookmarks',
	];

	protected $entityColumns = [];

	/**
	 * @var BookmarkMapper
	 */
	protected $bookmarkMapper;

	/**
	 * @var FolderMapper
	 */
	protected $folderMapper;

	/**
	 * @var IQueryBuilder
	 */
	private $parentQuery;
	/**
	 * @var array
	 */
	private $getChildrenQuery;

	/**
	 * FolderMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param FolderMapper $folderMapper
	 * @param BookmarkMapper $bookmarkMapper
	 */
	public function __construct(IDBConnection $db, FolderMapper $folderMapper, BookmarkMapper $bookmarkMapper) {
		parent::__construct($db, 'bookmarks_trash');
		$this->folderMapper = $folderMapper;
		$this->bookmarkMapper = $bookmarkMapper;

		$this->entityColumns = [
			self::TYPE_SHARE => SharedFolder::$columns,
			self::TYPE_FOLDER => Folder::$columns,
			self::TYPE_BOOKMARK => Bookmark::$columns,
		];

		$this->parentQuery = $this->getParentQuery();
		$this->getChildrenQuery = [
			self::TYPE_BOOKMARK => $this->getFindChildrenQuery(self::TYPE_BOOKMARK),
			self::TYPE_FOLDER => $this->getFindChildrenQuery(self::TYPE_FOLDER),
			self::TYPE_SHARE => $this->getFindChildrenQuery(self::TYPE_SHARE)
		];
	}

	/**
	 * Creates an entity from a row. Automatically determines the entity class
	 * from the current mapper name (MyEntityMapper -> MyEntity)
	 *
	 * @param array $row the row which should be converted to an entity
	 * @param string $entityClass
	 * @return Entity the entity
	 */
	protected function mapRowToEntityWithClass(array $row, string $entityClass): Entity {
		return call_user_func($entityClass . '::fromRow', $row);
	}


	/**
	 * 	 * Runs a sql query and returns an array of entities
	 * 	 *
	 *
	 * @param IQueryBuilder $query
	 * @param string $type
	 *
	 * @return Entity[] all fetched entities
	 *
	 * @psalm-return list<Entity>
	 */
	protected function findEntitiesWithType(IQueryBuilder $query, string $type): array {
		$cursor = $query->execute();

		$entities = [];

		while ($row = $cursor->fetch()) {
			$entities[] = $this->mapRowToEntityWithClass($row, $this->entityClasses[$type]);
		}

		$cursor->closeCursor();

		return $entities;
	}

	/**
	 * Returns an db result and throws exceptions when there are more or less
	 * results
	 *
	 * @param IQueryBuilder $query
	 * @param string $type
	 * @return Entity the entity
	 * @throws DoesNotExistException if the item does not exist
	 * @throws MultipleObjectsReturnedException if more than one item exist
	 */
	protected function findEntityWithType(IQueryBuilder $query, string $type): Entity {
		return $this->mapRowToEntityWithClass($this->findOneQuery($query), $this->entityClasses[$type]);
	}

	/**
	 * @param string $type
	 * @param array $cols
	 * @param IQueryBuilder|null $queryBuilder
	 * @return IQueryBuilder
	 */
	protected function selectFromType(string $type, array $cols = [], IQueryBuilder $queryBuilder = null): IQueryBuilder {
		$qb = $queryBuilder ?? $this->db->getQueryBuilder();
		$qb->resetQueryPart('from');
		$qb
			->select(array_merge(array_map(static function ($col) {
				return 'i.' . $col;
			}, $this->entityColumns[$type]), $cols))
			->from($this->entityTables[$type], 'i');
		return $qb;
	}

	protected function getInsertQuery(): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb
			->insert('bookmarks_trash')
			->values([
				'id' => $qb->createParameter('id'),
				'parent_folder' => $qb->createParameter('parent_folder'),
				'type' => $qb->createParameter('type'),
			]);
		return $qb;
	}

	protected function getParentQuery(): IQueryBuilder {
		$qb = $this->selectFromType(self::TYPE_FOLDER);
		$qb
			->join('i', 'bookmarks_tree', 't', $qb->expr()->eq('t.parent_folder', 'i.id'))
			->where($qb->expr()->eq('t.id', $qb->createParameter('id')))
			->andWhere($qb->expr()->eq('t.type', $qb->createParameter('type')));
		return $qb;
	}

	protected function getGetChildrenOrderQuery(): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id', 'type')
			->from('bookmarks_trash')
			->where($qb->expr()->eq('parent_folder', $qb->createParameter('parent_folder')))
			->orderBy('index', 'ASC');
		return $qb;
	}

	protected function getFindChildrenQuery(string $type): IQueryBuilder {
		$qb = $this->selectFromType($type);
		$qb
				->join('i', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'i.id'))
				->where($qb->expr()->eq('t.parent_folder', $qb->createParameter('parent_folder')))
				->andWhere($qb->expr()->eq('t.type', $qb->createNamedParameter($type)))
				->orderBy('t.index', 'ASC');
		return $qb;
	}

	/**
	 * @param int $folderId
	 * @param string $type
	 *
	 * @return Entity[]
	 *
	 * @psalm-return array<array-key, Entity>
	 */
	public function findChildren(string $type, int $folderId): array {
		$qb = $this->getChildrenQuery[$type];
		$qb->setParameter('parent_folder', $folderId);
		return $this->findEntitiesWithType($qb, $type);
	}

	/**
	 * @param string $type
	 * @param int $itemId
	 * @return Entity
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findParentOf(string $type, int $itemId): Entity {
		$qb = $this->parentQuery;
		$qb->setParameters([
			'id' => $itemId,
			'type' => $type,
		]);
		return $this->findEntityWithType($qb, $type);
	}

	/**
	 * @param string $type
	 * @param int $itemId
	 *
	 * @return Entity[]
	 *
	 * @psalm-return array<array-key, Entity>
	 */
	public function findParentsOf(string $type, int $itemId): array {
		$qb = $this->parentQuery;
		$qb->setParameters([
			'id' => $itemId,
			'type' => $type,
		]);
		return $this->findEntitiesWithType($qb, self::TYPE_FOLDER);
	}

	/**
	 * @param string $type
	 * @param int $folderId
	 * @return array|Entity[]
	 */
	public function findByAncestorFolder(string $type, int $folderId): array {
		$descendants = [];
		$newDescendants = $this->findChildren($type, $folderId);
		do {
			array_push($descendants, ...$newDescendants);
			$newDescendants = array_flatten(array_map(function (Entity $descendant) use ($type) {
				return $this->findChildren($type, $descendant->getId());
			}, $newDescendants));
		} while (count($newDescendants) > 0);
		return $descendants;
	}


	/**
	 * @param string $type
	 * @param int $id
	 * @param int|null $folderId
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function deleteEntry(string $type, int $id, int $folderId = null): void {
		if ($type === self::TYPE_FOLDER) {
			// First get all shared folders out of the way
			$descendantShares = $this->findByAncestorFolder(self::TYPE_SHARE, $id);
			foreach ($descendantShares as $share) {
				$this->deleteEntry(self::TYPE_SHARE, $share->getId(), $id);
			}

			// then get all folders in this sub tree
			$descendantFolders = $this->findByAncestorFolder(self::TYPE_FOLDER, $id);
			/** @var Folder $folder */
			$folder = $this->folderMapper->find($id);
			$descendantFolders[] = $folder;

			// remove all bookmarks entries from this subtree
			$qb = $this->db->getQueryBuilder();
			$qb
				->delete('bookmarks_tree')
				->where($qb->expr()->eq('type', $qb->createPositionalParameter(self::TYPE_BOOKMARK)))
				->andWhere($qb->expr()->in('parent_folder', $qb->createPositionalParameter(array_map(static function ($folder) {
					return $folder->getId();
				}, $descendantFolders), IQueryBuilder::PARAM_INT_ARRAY)));
			$qb->execute();

			$qb = $this->db->getQueryBuilder();
			$qb
				->delete('bookmarks_trash')
				->where($qb->expr()->eq('type', $qb->createPositionalParameter(self::TYPE_BOOKMARK)))
				->andWhere($qb->expr()->in('parent_folder', $qb->createPositionalParameter(array_map(static function ($folder) {
					return $folder->getId();
				}, $descendantFolders), IQueryBuilder::PARAM_INT_ARRAY)));
			$qb->execute();

			// remove all folders  entries from this subtree
			foreach ($descendantFolders as $descendantFolder) {
				$this->remove(self::TYPE_FOLDER, $descendantFolder->getId());
				$this->folderMapper->delete($descendantFolder);
			}

			// Remove orphaned bookmarks
			$qb = $this->db->getQueryBuilder();
			$qb->select('b.id')
				->from('bookmarks', 'b')
				->leftJoin('b', 'bookmarks_tree', 't', 'b.id = t.id AND t.type = '.$qb->createPositionalParameter(self::TYPE_BOOKMARK))
				->where($qb->expr()->isNull('t.id'));
			$orphanedBookmarks = $qb->execute();
			while ($bookmark = $orphanedBookmarks->fetch(\PDO::FETCH_COLUMN)) {
				$qb = $this->db->getQueryBuilder();
				$qb->delete('bookmarks')
					->where($qb->expr()->eq('id', $qb->createPositionalParameter($bookmark)))
					->execute();
			}

			return;
		}

		if ($type === self::TYPE_SHARE) {
			// This removes the shared folder from the tree, but not the original share
			// nor the original folder
			$this->remove($type, $id);
		}

		if ($type === self::TYPE_BOOKMARK) {
			$this->removeFromFolders(self::TYPE_BOOKMARK, $id, [$folderId]);
		}
	}

	/**
	 * @param string $type
	 * @param int $itemId
	 * @return void
	 */
	public function remove(string $type, int $itemId): void {
		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_trash')
			->where($qb->expr()->eq('type', $qb->createPositionalParameter($type)))
			->andWhere($qb->expr()->eq('id', $qb->createPositionalParameter($itemId, IQueryBuilder::PARAM_INT)));
		$qb->execute();
	}

	/**
	 * @brief Remove a bookmark from a set of folders
	 * @param string $type
	 * @param int $itemId The bookmark reference
	 * @param array $folders Set of folders ids to add the bookmark to
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function removeFromFolders(string $type, int $itemId, array $folders): void {
		if ($type !== self::TYPE_BOOKMARK) {
			throw new UnsupportedOperation('Only bookmarks can be in multiple folders');
		}
		$foldersLeft = count($this->findParentsOf($type, $itemId));

		foreach ($folders as $folderId) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->delete('bookmarks_trash')
				->where($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)))
				->andWhere($qb->expr()->eq('id', $qb->createPositionalParameter($itemId)))
				->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter($type)));
			$qb->execute();
		}
		if ($foldersLeft <= 0 && $type === self::TYPE_BOOKMARK) {
			$bm = $this->bookmarkMapper->find($itemId);
			$this->bookmarkMapper->delete($bm);
		}
	}

	/**
	 * @brief Add a bookmark to a set of folders
	 * @param string $type
	 * @param int $itemId The bookmark reference
	 * @param array $folders Set of folders ids to add the bookmark to
	 * @param int|null $index
	 * @throws UnsupportedOperation
	 */
	public function addToFolders(string $type, int $itemId, array $folders): void {
		if ($type !== self::TYPE_BOOKMARK) {
			throw new UnsupportedOperation('Only bookmarks can be in multiple folders');
		}

		foreach ($folders as $folderId) {
			$qb = $this->insertQuery;
			$qb
				->setParameters([
					'parent_folder' => $folderId,
					'type' => $type,
					'id' => $itemId,
				]);
			$qb->execute();
		}
	}

	/**
	 * @param string $type
	 * @param int $itemId
	 * @param int $newParentFolderId
	 * @param int|null $index
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function add(string $type, int $itemId): void {
		if ($type === self::TYPE_BOOKMARK) {
			throw new UnsupportedOperation('Cannot use this function for Bookmarks');
		}
		try {
			// Try to find current parent
			/** @var Folder $currentParent */
			$currentParent = $this->findParentOf($type, $itemId);

			// Item currently has a parent => move.

			$qb = $this->db->getQueryBuilder();
			$qb
				->update('bookmarks_trash')
				->set('parent_folder', $qb->createPositionalParameter($currentParent, IQueryBuilder::PARAM_INT))
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($itemId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter($type)));
			$qb->execute();
		} catch (DoesNotExistException $e) {
			// Item currently has no parent. Odd. We'll ignore it then.
			return;
		}
	}

	/**
	 * @brief Count the items in the trash
	 * @return mixed
	 */
	public function countTrash() {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select($qb->func()->count('id'))
			->from('bookmarks_trash');
		return $qb->execute()->fetch(PDO::FETCH_COLUMN);
	}
}
