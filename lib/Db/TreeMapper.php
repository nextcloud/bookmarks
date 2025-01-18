<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Db;

use OCA\Bookmarks\Events\BeforeDeleteEvent;
use OCA\Bookmarks\Events\BeforeSoftDeleteEvent;
use OCA\Bookmarks\Events\BeforeSoftUndeleteEvent;
use OCA\Bookmarks\Events\MoveEvent;
use OCA\Bookmarks\Events\UpdateEvent;
use OCA\Bookmarks\Exception\ChildrenOrderValidationError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\QueryParameters;
use OCA\Bookmarks\Service\TreeCacheManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IUserManager;
use PDO;
use Psr\Log\LoggerInterface;
use function call_user_func;

/**
 * @psalm-extends QBMapper<Bookmark|Folder|SharedFolder>
 */
class TreeMapper extends QBMapper {
	public const TYPE_SHARE = 'share';
	public const TYPE_FOLDER = 'folder';
	public const TYPE_BOOKMARK = 'bookmark';

	protected $entityClasses = [
		TreeMapper::TYPE_SHARE => SharedFolder::class,
		TreeMapper::TYPE_FOLDER => Folder::class,
		TreeMapper::TYPE_BOOKMARK => Bookmark::class,
	];

	protected $entityTables = [
		TreeMapper::TYPE_SHARE => 'bookmarks_shared_folders',
		TreeMapper::TYPE_FOLDER => 'bookmarks_folders',
		TreeMapper::TYPE_BOOKMARK => 'bookmarks',
	];

	protected $entityColumns = [];


	private IQueryBuilder $insertQuery;

	private IQueryBuilder $parentQuery;
	private IQueryBuilder $parentQueryWithoutSoftDeletions;

	private array $getChildrenQuery;
	private array $getSoftDeletedChildrenQuery;

	private IQueryBuilder $getChildrenOrderQuery;

	/**
	 * TreeMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param IEventDispatcher $eventDispatcher
	 * @param FolderMapper $folderMapper
	 * @param BookmarkMapper $bookmarkMapper
	 * @param ShareMapper $shareMapper
	 * @param SharedFolderMapper $sharedFolderMapper
	 * @param PublicFolderMapper $publicFolderMapper
	 * @param TreeCacheManager $treeCache
	 * @param IUserManager $userManager
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(
		IDBConnection $db,
		private IEventDispatcher $eventDispatcher,
		private FolderMapper $folderMapper,
		private BookmarkMapper $bookmarkMapper,
		private ShareMapper $shareMapper,
		private SharedFolderMapper $sharedFolderMapper,
		private PublicFolderMapper $publicFolderMapper,
		private TreeCacheManager $treeCache,
		private IUserManager $userManager,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
	) {
		parent::__construct($db, 'bookmarks_tree');

		$this->entityColumns = [
			TreeMapper::TYPE_SHARE => SharedFolder::$columns,
			TreeMapper::TYPE_FOLDER => Folder::$columns,
			TreeMapper::TYPE_BOOKMARK => Bookmark::$columns,
		];

		$this->insertQuery = $this->getInsertQuery();
		$this->parentQuery = $this->getParentQuery();
		$this->parentQueryWithoutSoftDeletions = $this->getParentQueryWithoutSoftDeletions();
		$this->getChildrenOrderQuery = $this->getGetChildrenOrderQuery();
		$this->getChildrenQuery = [
			TreeMapper::TYPE_BOOKMARK => $this->getFindChildrenQuery(TreeMapper::TYPE_BOOKMARK),
			TreeMapper::TYPE_FOLDER => $this->getFindChildrenQuery(TreeMapper::TYPE_FOLDER),
			TreeMapper::TYPE_SHARE => $this->getFindChildrenQuery(TreeMapper::TYPE_SHARE)
		];
		$this->getSoftDeletedChildrenQuery = [
			TreeMapper::TYPE_BOOKMARK => $this->getFindSoftDeletedChildrenQuery(TreeMapper::TYPE_BOOKMARK),
			TreeMapper::TYPE_FOLDER => $this->getFindSoftDeletedChildrenQuery(TreeMapper::TYPE_FOLDER),
			TreeMapper::TYPE_SHARE => $this->getFindSoftDeletedChildrenQuery(TreeMapper::TYPE_SHARE)
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
	 * @psalm-param T $type
	 * @psalm-template T as TreeMapper::TYPE_*
	 * @psalm-template E as (T is TreeMapper::TYPE_FOLDER ? Folder : (T is TreeMapper::TYPE_BOOKMARK ? Bookmark : SharedFolder))
	 * @return Entity[] all fetched entities
	 * @psalm-return list<E>
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
	 * @psalm-param T $type
	 * @return E the entity
	 * @psalm-template T as TreeMapper::TYPE_*
	 * @psalm-template E as (T is TreeMapper::TYPE_FOLDER ? Folder : (T is TreeMapper::TYPE_BOOKMARK ? Bookmark : SharedFolder))
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
	protected function selectFromType(string $type, array $cols = [], ?IQueryBuilder $queryBuilder = null): IQueryBuilder {
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
			->insert('bookmarks_tree')
			->values([
				'id' => $qb->createParameter('id'),
				'parent_folder' => $qb->createParameter('parent_folder'),
				'type' => $qb->createParameter('type'),
				'index' => $qb->createParameter('index'),
			]);
		return $qb;
	}

	protected function getParentQueryWithoutSoftDeletions(): IQueryBuilder {
		$qb = $this->selectFromType(TreeMapper::TYPE_FOLDER);
		$qb
			->join('i', 'bookmarks_tree', 't', $qb->expr()->eq('t.parent_folder', 'i.id'))
			->where($qb->expr()->eq('t.id', $qb->createParameter('id')))
			->andWhere($qb->expr()->eq('t.type', $qb->createParameter('type')))
			->andWhere($qb->expr()->isNull('t.soft_deleted_at'));
		return $qb;
	}

	protected function getParentQuery(): IQueryBuilder {
		$qb = $this->selectFromType(TreeMapper::TYPE_FOLDER);
		$qb
			->join('i', 'bookmarks_tree', 't', $qb->expr()->eq('t.parent_folder', 'i.id'))
			->where($qb->expr()->eq('t.id', $qb->createParameter('id')))
			->andWhere($qb->expr()->eq('t.type', $qb->createParameter('type')));
		return $qb;
	}

	protected function getGetChildrenOrderQuery(): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id', 'type', 'index')
			->from('bookmarks_tree')
			->where($qb->expr()->eq('parent_folder', $qb->createParameter('parent_folder')))
			->andWhere($qb->expr()->isNull('soft_deleted_at'))
			->orderBy('index', 'ASC');
		return $qb;
	}

	protected function getFindChildrenQuery(string $type): IQueryBuilder {
		$qb = $this->selectFromType($type);
		$qb
			->join('i', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'i.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createParameter('parent_folder')))
			->andWhere($qb->expr()->eq('t.type', $qb->createNamedParameter($type)))
			->andWhere($qb->expr()->isNull('t.soft_deleted_at'))
			->orderBy('t.index', 'ASC');
		return $qb;
	}

	protected function getFindSoftDeletedChildrenQuery(string $type): IQueryBuilder {
		$qb = $this->selectFromType($type);
		$qb
			->join('i', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'i.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createParameter('parent_folder')))
			->andWhere($qb->expr()->eq('t.type', $qb->createNamedParameter($type)))
			->andWhere($qb->expr()->isNotNull('t.soft_deleted_at'))
			->orderBy('t.index', 'ASC');
		return $qb;
	}

	/**
	 * @param string $type
	 * @psalm-param T $type
	 * @param int $folderId
	 * @param bool $softDeleted
	 * @return Entity[]
	 * @psalm-return E[]
	 * @psalm-template T as TreeMapper::TYPE_*
	 * @psalm-template E as (T is TreeMapper::TYPE_FOLDER ? Folder : (T is TreeMapper::TYPE_BOOKMARK ? Bookmark : SharedFolder))
	 */
	public function findChildren(string $type, int $folderId, ?bool $softDeleted = null): array {
		$listSoftDeleted = $softDeleted ?? $this->isEntrySoftDeleted(self::TYPE_FOLDER, $folderId);
		$qb = $this->selectFromType($type, [], !$listSoftDeleted ? $this->getChildrenQuery[$type] : $this->getSoftDeletedChildrenQuery[$type]);
		$qb->setParameter('parent_folder', $folderId);
		return $this->findEntitiesWithType($qb, $type);
	}

	/**
	 * @param string $type
	 * @psalm-param TreeMapper::TYPE_* $type
	 * @param int $itemId
	 * @return Entity
	 * @psalm-return Folder
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findParentOf(string $type, int $itemId): Entity {
		$qb = $this->parentQuery;
		$qb->setParameters([
			'id' => $itemId,
			'type' => $type,
		]);
		return $this->findEntityWithType($qb, TreeMapper::TYPE_FOLDER);
	}

	/**
	 * @param string $type
	 * @psalm-param TreeMapper::TYPE_* $type
	 * @param int $itemId
	 *
	 * @return Entity[]
	 * @psalm-return list<Folder>
	 */
	public function findParentsOf(string $type, int $itemId, $withSoftDeletions = false): array {
		if ($withSoftDeletions === true) {
			$qb = $this->parentQuery;
		} else {
			$qb = $this->parentQueryWithoutSoftDeletions;
		}
		$qb->setParameters([
			'id' => $itemId,
			'type' => $type,
		]);
		return $this->findEntitiesWithType($qb, TreeMapper::TYPE_FOLDER);
	}

	/**
	 * @param string $type
	 * @psalm-param T $type
	 * @param int $folderId
	 * @return Entity[]
	 * @psalm-return E[]
	 * @psalm-template T as TreeMapper::TYPE_*
	 * @psalm-template E as (T is TreeMapper::TYPE_FOLDER ? Folder : (T is TreeMapper::TYPE_BOOKMARK ? Bookmark : SharedFolder))
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
	 * @param int $folderId
	 * @param TreeMapper::TYPE_* $type
	 * @param int $descendantId
	 * @return bool
	 */
	public function hasDescendant(int $folderId, string $type, int $descendantId): bool {
		$ancestors = $this->findParentsOf($type, $descendantId, true);
		while (!in_array($folderId, array_map(static function (Entity $ancestor) {
			return $ancestor->getId();
		}, $ancestors), true)) {
			$ancestors = array_flatten(array_map(function (Entity $ancestor) {
				return $this->findParentsOf(TreeMapper::TYPE_FOLDER, $ancestor->getId(), true);
			}, $ancestors));
			if (count($ancestors) === 0) {
				return false;
			}
		}
		return true;
	}


	/**
	 * @param string $type
	 * @psalm-param TreeMapper::TYPE_* $type
	 * @param int $id
	 * @param int|null $folderId
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function deleteEntry(string $type, int $id, ?int $folderId = null): void {
		$this->eventDispatcher->dispatch(BeforeDeleteEvent::class, new BeforeDeleteEvent($type, $id));

		if ($type === TreeMapper::TYPE_FOLDER) {
			// First get all shares out of the way
			$descendantShares = $this->findByAncestorFolder(TreeMapper::TYPE_SHARE, $id);
			foreach ($descendantShares as $share) {
				$this->deleteEntry(TreeMapper::TYPE_SHARE, $share->getId(), $id);
			}

			// then get all folders in this sub tree
			$descendantFolders = $this->findByAncestorFolder(TreeMapper::TYPE_FOLDER, $id);
			$folder = $this->folderMapper->find($id);
			$descendantFolders[] = $folder;

			// remove all bookmarks entries from this subtree
			$qb = $this->db->getQueryBuilder();
			$qb
				->delete('bookmarks_tree')
				->where($qb->expr()->eq('type', $qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK)))
				->andWhere($qb->expr()->in('parent_folder', $qb->createPositionalParameter(array_map(static function ($folder) {
					return $folder->getId();
				}, $descendantFolders), IQueryBuilder::PARAM_INT_ARRAY)));
			$qb->execute();

			// remove all folders  entries from this subtree
			foreach ($descendantFolders as $descendantFolder) {
				$this->removeFolderTangibles($descendantFolder->getId());
				$this->remove(TreeMapper::TYPE_FOLDER, $descendantFolder->getId());
				$this->folderMapper->delete($descendantFolder);
			}

			// Remove orphaned bookmarks
			$qb = $this->db->getQueryBuilder();
			$qb->select('b.id')
				->from('bookmarks', 'b')
				->leftJoin('b', 'bookmarks_tree', 't', 'b.id = t.id AND t.type = ' . $qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK))
				->where($qb->expr()->isNull('t.id'));
			$orphanedBookmarks = $qb->execute();
			while ($bookmark = $orphanedBookmarks->fetchColumn()) {
				$qb = $this->db->getQueryBuilder();
				$qb->delete('bookmarks')
					->where($qb->expr()->eq('id', $qb->createPositionalParameter($bookmark)))
					->execute();
			}

			return;
		}

		if ($type === TreeMapper::TYPE_SHARE) {
			$this->remove($type, $id);
			// This will only be removed if the share is removed!
			//$sharedFolder = $this->sharedFolderMapper->find($id);
			//$this->sharedFolderMapper->delete($sharedFolder);
		}

		if ($type === TreeMapper::TYPE_BOOKMARK) {
			if ($folderId === null) {
				$folders = array_map(fn (Folder $folder) => $folder->getId(), $this->findParentsOf(TreeMapper::TYPE_BOOKMARK, $id, true));
			} else {
				$folders = [$folderId];
			}
			$this->removeFromFolders(TreeMapper::TYPE_BOOKMARK, $id, $folders);
		}
	}

	/**
	 * @param string $type
	 * @psalm-param TreeMapper::TYPE_* $type
	 * @param int $id
	 * @param int|null $folderId
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws Exception
	 */
	public function softDeleteEntry(string $type, int $id, ?int $folderId = null): void {
		$this->eventDispatcher->dispatchTyped(new BeforeSoftDeleteEvent($type, $id));

		if ($type === TreeMapper::TYPE_FOLDER) {
			// First get all shares out of the way
			$descendantShares = $this->findByAncestorFolder(TreeMapper::TYPE_SHARE, $id);
			foreach ($descendantShares as $share) {
				$this->softDeleteEntry(TreeMapper::TYPE_SHARE, $share->getId());
			}

			// then get all folders in this sub tree
			$descendantFolders = $this->findByAncestorFolder(TreeMapper::TYPE_FOLDER, $id);
			$folder = $this->folderMapper->find($id);
			$descendantFoldersPlusThisFolder = [...$descendantFolders, $folder];

			// soft delete all descendant bookmarks entries from this subtree
			$qb = $this->db->getQueryBuilder();
			$qb
				->update('bookmarks_tree')
				->set('soft_deleted_at', $qb->createNamedParameter($this->timeFactory->getDateTime(), IQueryBuilder::PARAM_DATE))
				->where($qb->expr()->eq('type', $qb->createNamedParameter(TreeMapper::TYPE_BOOKMARK)))
				->andWhere($qb->expr()->in('parent_folder', $qb->createNamedParameter(array_map(static function ($folder) {
					return $folder->getId();
				}, $descendantFoldersPlusThisFolder), IQueryBuilder::PARAM_INT_ARRAY)));
			$qb->execute();

			// soft delete all folder entries from this subtree
			foreach ($descendantFoldersPlusThisFolder as $descendantFolder) {
				// set entry as deleted
				// this has to come last, because otherwise findByAncestorFolder doesn't work anymore
				$qb = $this->db->getQueryBuilder();
				$qb
					->update('bookmarks_tree')
					->set('soft_deleted_at', $qb->createNamedParameter($this->timeFactory->getDateTime(), IQueryBuilder::PARAM_DATE))
					->where($qb->expr()->eq('id', $qb->createNamedParameter($descendantFolder->getId(), IQueryBuilder::PARAM_INT)))
					->andWhere($qb->expr()->eq('type', $qb->createNamedParameter($type)));
				$qb->executeStatement();
			}

			return;
		}

		// set entry as deleted
		$qb = $this->db->getQueryBuilder();
		$qb
			->update('bookmarks_tree')
			->set('soft_deleted_at', $qb->createNamedParameter($this->timeFactory->getDateTime(), IQueryBuilder::PARAM_DATE))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('type', $qb->createNamedParameter($type)));
		if ($folderId !== null) {
			$qb->andWhere($qb->expr()->eq('parent_folder', $qb->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		}
		$qb->executeStatement();
	}

	/**
	 * @param string $type
	 * @psalm-param TreeMapper::TYPE_* $type
	 * @param int $id
	 * @param int|null $folderId
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws Exception
	 */
	public function softUndeleteEntry(string $type, int $id, ?int $folderId = null): void {
		$this->eventDispatcher->dispatchTyped(new BeforeSoftUndeleteEvent($type, $id));

		if ($type === TreeMapper::TYPE_FOLDER) {
			// First get all shares out of the way
			$descendantShares = $this->findByAncestorFolder(TreeMapper::TYPE_SHARE, $id);
			foreach ($descendantShares as $share) {
				$this->softUndeleteEntry(TreeMapper::TYPE_SHARE, $share->getId());
			}

			// then get all folders in this sub tree
			$descendantFolders = $this->findByAncestorFolder(TreeMapper::TYPE_FOLDER, $id);
			$folder = $this->folderMapper->find($id);
			$descendantFoldersPlusThisFolder = [...$descendantFolders, $folder];

			$foldersToUndeleteFrom = array_map(static function ($folder) {
				return $folder->getId();
			}, $descendantFoldersPlusThisFolder);

			// undelete all descendant bookmarks entries from this subtree
			$qb = $this->db->getQueryBuilder();
			$qb
				->update('bookmarks_tree')
				->set('soft_deleted_at', $qb->createNamedParameter(null, IQueryBuilder::PARAM_DATE))
				->where($qb->expr()->eq('type', $qb->createNamedParameter(TreeMapper::TYPE_BOOKMARK)))
				->andWhere($qb->expr()->in('parent_folder', $qb->createNamedParameter($foldersToUndeleteFrom, IQueryBuilder::PARAM_INT_ARRAY)));
			$qb->executeStatement();

			// soft delete all folder entries from this subtree
			foreach ($descendantFoldersPlusThisFolder as $descendantFolder) {
				// set entry as not deleted
				$qb = $this->db->getQueryBuilder();
				$qb
					->update('bookmarks_tree')
					->set('soft_deleted_at', $qb->createNamedParameter(null, IQueryBuilder::PARAM_DATE))
					->where($qb->expr()->eq('id', $qb->createNamedParameter($descendantFolder->getId(), IQueryBuilder::PARAM_INT)))
					->andWhere($qb->expr()->eq('type', $qb->createNamedParameter(TreeMapper::TYPE_FOLDER, IQueryBuilder::PARAM_STR)));
				$qb->executeStatement();
			}

			return;
		}

		if ($this->isEntrySoftDeleted($type, $id, $folderId)) {
			// set entry as not deleted
			// has to come last to not break findByAncestorFolder
			$qb = $this->db->getQueryBuilder();
			$qb
				->update('bookmarks_tree')
				->set('soft_deleted_at', $qb->createNamedParameter(null, IQueryBuilder::PARAM_DATE))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_STR)));
			if ($folderId !== null) {
				$qb->set('index', $qb->createNamedParameter($this->countChildren($folderId)));
				$qb->andWhere($qb->expr()->eq('parent_folder', $qb->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
			}
			$qb->executeStatement();
		}
	}

	/**
	 * @param string $type
	 * @psalm-param TreeMapper::TYPE_* $type
	 * @param int $itemId
	 * @return void
	 * @throws Exception
	 */
	public function remove(string $type, int $itemId): void {
		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_tree')
			->where($qb->expr()->eq('type', $qb->createPositionalParameter($type)))
			->andWhere($qb->expr()->eq('id', $qb->createPositionalParameter($itemId, IQueryBuilder::PARAM_INT)));
		$qb->execute();
	}

	/**
	 * @param int $shareId
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation|Exception
	 */
	public function deleteShare(int $shareId): void {
		$share = $this->shareMapper->find($shareId);
		$sharedFolders = $this->sharedFolderMapper->findByShare($shareId);
		foreach ($sharedFolders as $sharedFolder) {
			$this->sharedFolderMapper->delete($sharedFolder);
			$this->deleteEntry(TreeMapper::TYPE_SHARE, $sharedFolder->getId());
		}
		$this->shareMapper->delete($share);
	}

	/**
	 * @param string $type
	 * @psalm-param TreeMapper::TYPE_* $type
	 * @param int $itemId
	 * @param int $newParentFolderId
	 * @param int|null $index
	 * @psalm-param 0|positive-int|null $index
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function move(string $type, int $itemId, int $newParentFolderId, ?int $index = null): void {
		if ($type === TreeMapper::TYPE_BOOKMARK) {
			throw new UnsupportedOperation('Cannot move Bookmark');
		}
		try {
			// Try to find current parent
			$currentParent = $this->findParentOf($type, $itemId);
		} catch (DoesNotExistException $e) {
			$currentParent = null;
		}

		if ($type !== TreeMapper::TYPE_SHARE) {
			$folderId = $itemId;
		} else {
			$sharedFolder = $this->sharedFolderMapper->find($itemId);
			$folderId = $sharedFolder->getFolderId();
			$share = $this->shareMapper->findBySharedFolder($sharedFolder->getId());

			// Make sure that the sharer of this share doesn't have a share of the target folder or one of its parents
			// would make a share loop very probable, which would be very bad. Breaks the whole app.

			if ($this->isFolderSharedWithUser($newParentFolderId, $share->getOwner())) {
				throw new UnsupportedOperation('Cannot nest a folder shared from user A inside a folder shared with user A');
			}
		}

		if ($this->hasDescendant($folderId, TreeMapper::TYPE_FOLDER, $newParentFolderId)) {
			throw new UnsupportedOperation('Cannot nest a folder inside one of its descendants');
		}

		if ($currentParent !== null) {
			// Item currently has a parent => move.

			$qb = $this->db->getQueryBuilder();
			$qb
				->update('bookmarks_tree')
				->set('parent_folder', $qb->createPositionalParameter($newParentFolderId, IQueryBuilder::PARAM_INT))
				->set('index', $qb->createPositionalParameter($index ?? $this->countChildren($newParentFolderId)))
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($itemId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter($type)));
			$qb->execute();
		} else {
			// Item currently has no parent => insert into tree.
			$qb = $this->insertQuery;
			$qb
				->setParameters(['id' => $itemId,
					'parent_folder' => $newParentFolderId,
					'type' => $type,
					'index' => $index ?? $this->countChildren($newParentFolderId),
				]);
			$qb->execute();
		}

		$this->eventDispatcher->dispatch(MoveEvent::class, new MoveEvent(
			$type,
			$itemId,
			$currentParent ? $currentParent->getId() : null,
			$newParentFolderId
		));
	}


	/**
	 * @brief Add a bookmark to a set of folders
	 * @param string $type
	 * @psalm-param TreeMapper::TYPE_BOOKMARK $type
	 * @param int $itemId
	 * @param array $folders Set of folders ids to add the bookmark to
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation|Exception
	 */
	public function setToFolders(string $type, int $itemId, array $folders): void {
		if ($type !== TreeMapper::TYPE_BOOKMARK) {
			throw new UnsupportedOperation('Only bookmarks can be in multiple folders');
		}
		if (count($folders) === 0) {
			return;
		}

		$currentFolders = $this->findParentsOf($type, $itemId);
		$this->addToFolders($type, $itemId, $folders);
		$this->removeFromFolders($type, $itemId, array_map(static function (Folder $f) {
			return $f->getId();
		}, array_filter($currentFolders, static function (Folder $folder) use ($folders) {
			return !in_array($folder->getId(), $folders, true);
		})));
	}

	/**
	 * @brief Add a bookmark to a set of folders
	 * @param string $type
	 * @psalm-param TreeMapper::TYPE_BOOKMARK $type
	 * @param int $itemId The bookmark reference
	 * @param array $folders Set of folders ids to add the bookmark to
	 * @param int|null $index
	 * @throws UnsupportedOperation|Exception
	 */
	public function addToFolders(string $type, int $itemId, array $folders, ?int $index = null): void {
		if ($type !== TreeMapper::TYPE_BOOKMARK) {
			throw new UnsupportedOperation('Only bookmarks can be in multiple folders');
		}
		$currentFolders = array_map(static function (Folder $f) {
			return $f->getId();
		}, $this->findParentsOf($type, $itemId, true));

		$folders = array_filter($folders, static function ($folderId) use ($currentFolders) {
			return !in_array($folderId, $currentFolders, true);
		});
		foreach ($folders as $folderId) {
			$qb = $this->insertQuery;
			$qb
				->setParameters([
					'parent_folder' => $folderId,
					'type' => $type,
					'id' => $itemId,
					'index' => $index ?? $this->countChildren($folderId),
				]);
			$qb->execute();

			$this->eventDispatcher->dispatch(MoveEvent::class, new MoveEvent($type, $itemId, null, $folderId));
		}
	}

	/**
	 * @brief Remove a bookmark from a set of folders
	 * @param string $type
	 * @psalm-param TreeMapper::TYPE_BOOKMARK $type
	 * @param int $itemId The bookmark reference
	 * @param array $folders Set of folders ids to add the bookmark to
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation|Exception
	 */
	public function removeFromFolders(string $type, int $itemId, array $folders): void {
		if ($type !== TreeMapper::TYPE_BOOKMARK) {
			throw new UnsupportedOperation('Only bookmarks can be in multiple folders');
		}
		$foldersLeft = count($this->findParentsOf($type, $itemId, true));

		foreach ($folders as $folderId) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->delete('bookmarks_tree')
				->where($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)))
				->andWhere($qb->expr()->eq('id', $qb->createPositionalParameter($itemId)))
				->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter($type)));
			$qb->execute();

			$this->eventDispatcher->dispatch(MoveEvent::class, new MoveEvent($type, $itemId, $folderId));

			$foldersLeft--;
		}
		if ($foldersLeft <= 0) {
			$bm = $this->bookmarkMapper->find($itemId);
			$this->bookmarkMapper->delete($bm);
		}
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 * @param $folderId
	 * @param $newChildrenOrder
	 * @return void
	 * @throws ChildrenOrderValidationError
	 */
	public function setChildrenOrder(int $folderId, array $newChildrenOrder): void {
		$existingChildren = $this->getChildrenOrder($folderId);
		foreach ($existingChildren as $child) {
			if (!in_array($child, $newChildrenOrder, false)) {
				throw new ChildrenOrderValidationError('A child is missing: ' . $child['type'] . ':' . $child['id']);
			}
			if (!isset($child['id'], $child['type'])) {
				throw new ChildrenOrderValidationError('A child item is missing properties');
			}
		}
		if (count($newChildrenOrder) !== count($existingChildren)) {
			throw new ChildrenOrderValidationError('To many children');
		}

		$qb = $this->db->getQueryBuilder();
		$qb
			->select('s.folder_id', 's.id')
			->from('bookmarks_shared_folders', 's')
			->innerJoin('s', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 's.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter(TreeMapper::TYPE_SHARE)))
			->orderBy('t.index', 'ASC');
		$childShares = $qb->execute()->fetchAll();

		$foldersToShares = array_reduce($childShares, static function ($dict, $shareRec) {
			$dict[$shareRec['folder_id']] = $shareRec['id'];
			return $dict;
		}, []);

		foreach ($newChildrenOrder as $i => $child) {
			if (!in_array($child['type'], [TreeMapper::TYPE_FOLDER, TreeMapper::TYPE_BOOKMARK], true)) {
				continue;
			}

			if (($child['type'] === TreeMapper::TYPE_FOLDER) && isset($foldersToShares[$child['id']])) {
				$child['type'] = TreeMapper::TYPE_SHARE;
				$child['id'] = $foldersToShares[$child['id']];
			}

			$qb = $this->db->getQueryBuilder();
			$qb
				->update('bookmarks_tree')
				->set('index', $qb->createPositionalParameter($i))
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($child['id'])))
				->andWhere($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)))
				->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter($child['type'])));
			$qb->execute();
		}

		$this->eventDispatcher->dispatch(UpdateEvent::class, new UpdateEvent(TreeMapper::TYPE_FOLDER, $folderId));
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 *
	 * @param $folderId
	 * @param int $layers The amount of levels to return
	 *
	 * @return array the children each in the format ["id" => int, "type" => 'bookmark' | 'folder' ]
	 *
	 * @psalm-return list<array{type: 'bookmark'|'folder', id: int, children?: array}>
	 * @throws Exception
	 */
	public function getChildrenOrder(int $folderId, int $layers = 0): array {
		$children = $this->treeCache->get(TreeCacheManager::CATEGORY_CHILDORDER, TreeMapper::TYPE_FOLDER, $folderId);
		if ($children !== null) {
			return $children;
		}
		$qb = $this->getChildrenOrderQuery;
		$qb->setParameter('parent_folder', $folderId);
		$children = $qb->execute()->fetchAll();

		$qb = $this->getChildrenQuery[TreeMapper::TYPE_SHARE];
		$this->selectFromType(TreeMapper::TYPE_SHARE, ['t.index'], $qb);
		$qb->setParameter('parent_folder', $folderId);
		$childShares = $qb->execute()->fetchAll() ?? [];

		$children = array_map(function ($child) use ($layers, $childShares) {
			$item = ['type' => $child['type'], 'id' => (int)$child['id']];

			if ($item['type'] === TreeMapper::TYPE_SHARE) {
				$item['type'] = TreeMapper::TYPE_FOLDER;
				$item['id'] = (int)array_shift($childShares)['folder_id'];
			}

			if ($item['type'] === TreeMapper::TYPE_FOLDER && $layers !== 0) {
				$item['children'] = $this->getChildrenOrder($item['id'], $layers - 1);
			}
			return $item;
		}, $children);

		if ($layers < 0) {
			$this->treeCache->set(TreeCacheManager::CATEGORY_CHILDORDER, TreeMapper::TYPE_FOLDER, $folderId, $children);
		}

		return $children;
	}

	public function isEntrySoftDeleted(string $type, int $id, ?int $folderId = null) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('soft_deleted_at')
			->from('bookmarks_tree')
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter($type, IQueryBuilder::PARAM_STR)))
			->setMaxResults(1);
		if ($folderId !== null) {
			$qb->andWhere($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId, IQueryBuilder::PARAM_INT)));
		}
		$result = $qb->executeQuery();
		$results = $result->fetchAll();
		return count($results) >= 1 && $results[0]['soft_deleted_at'] !== null;
	}

	/**
	 * @param int $folderId
	 * @param int $layers [-1, inf]
	 * @param bool|null $isSoftDeleted
	 *
	 * @return array
	 *
	 * @psalm-return list<array{parent_folder: int, id: int, userId: string, userDisplayName: string, children?: array}>
	 */
	public function getSubFolders(int $folderId, $layers = 0, ?bool $isSoftDeleted = null): array {
		$isSoftDeleted = $isSoftDeleted ?? $this->isEntrySoftDeleted(TreeMapper::TYPE_FOLDER, $folderId);
		if (!$isSoftDeleted) {
			$folders = $this->treeCache->get(TreeCacheManager::CATEGORY_SUBFOLDERS, TreeMapper::TYPE_FOLDER, $folderId);
			if ($folders !== null) {
				return $folders;
			}
		} else {
			$folders = $this->treeCache->get(TreeCacheManager::CATEGORY_DELETED_SUBFOLDERS, TreeMapper::TYPE_FOLDER, $folderId);
			if ($folders !== null) {
				return $folders;
			}
		}
		$folders = array_map(function (Folder $folder) use ($layers, $folderId, $isSoftDeleted) {
			$array = $folder->toArray();
			$array['userDisplayName'] = $this->userManager->get($array['userId'])->getDisplayName();
			$array['parent_folder'] = $folderId;
			if ($layers !== 0) {
				$array['children'] = $this->getSubFolders($folder->getId(), $layers - 1, $isSoftDeleted);
			}
			return $array;
		}, $this->findChildren(TreeMapper::TYPE_FOLDER, $folderId, $isSoftDeleted));
		$shares = array_map(function (SharedFolder $sharedFolder) use ($layers, $folderId, $isSoftDeleted) {
			$share = $this->shareMapper->findBySharedFolder($sharedFolder->getId());
			$array = $sharedFolder->toArray();
			$array['id'] = $share->getFolderId();
			$array['userId'] = $share->getOwner();
			$array['userDisplayName'] = $this->userManager->get($array['userId'])->getDisplayName();
			$array['parent_folder'] = $folderId;
			if ($layers !== 0) {
				$array['children'] = $this->getSubFolders($share->getFolderId(), $layers - 1, $isSoftDeleted);
			}
			return $array;
		}, $this->findChildren(TreeMapper::TYPE_SHARE, $folderId, $isSoftDeleted));
		if (count($shares) > 0) {
			array_push($folders, ...$shares);
		}
		if ($layers < 0) {
			if (!$isSoftDeleted) {
				$this->treeCache->set(TreeCacheManager::CATEGORY_SUBFOLDERS, TreeMapper::TYPE_FOLDER, $folderId, $folders);
			} else {
				$this->treeCache->set(TreeCacheManager::CATEGORY_DELETED_SUBFOLDERS, TreeMapper::TYPE_FOLDER, $folderId, $folders);
			}
		}
		return $folders;
	}

	/**
	 * @param string $userId
	 * @param string $type
	 * @psalm-param T $type
	 * @return array
	 * @psalm-return E[]
	 * @psalm-template T as TreeMapper::TYPE_*
	 * @psalm-template E as (T is TreeMapper::TYPE_FOLDER ? Folder : (T is TreeMapper::TYPE_BOOKMARK ? Bookmark : SharedFolder))
	 * @throws UrlParseError|Exception
	 */
	public function getSoftDeletedRootItems(string $userId, string $type): array {
		if ($type === TreeMapper::TYPE_FOLDER || $type === TreeMapper::TYPE_SHARE) {
			$qb = $this->selectFromType($type);
			$qb
				->innerJoin('i', 'bookmarks_tree', 't', $qb->expr()->eq('i.id', 't.id'))
				->leftJoin('t', 'bookmarks_tree', 't2', $qb->expr()->eq('t.parent_folder', 't2.id'))
				->leftJoin('t', 'bookmarks_root_folders', 'r', $qb->expr()->eq('t.parent_folder', 'r.folder_id'))
				->where($qb->expr()->isNotNull('t.soft_deleted_at'))
				->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter($type, IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('i.user_id', $qb->createPositionalParameter($userId, IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->orX(
					$qb->expr()->isNull('t2.soft_deleted_at'),
					$qb->expr()->isNotNull('r.folder_id'),
				));
			return $this->findEntitiesWithType($qb, $type);
		}
		if ($type === TreeMapper::TYPE_BOOKMARK) {
			$params = new QueryParameters();
			$params->setLimit(-1);
			$params->setSoftDeleted(true);
			$params->setSoftDeletedFolders(false);
			return $this->bookmarkMapper->findAll($userId, $params);
		}
		throw new \RuntimeException('Given item type does not exist');
	}

	/**
	 * @brief Count the children in the given folder
	 * @param int $folderId
	 * @return int
	 */
	public function countChildren(int $folderId): int {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select($qb->func()->count('index', 'count'))
			->from('bookmarks_tree')
			->where($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->isNull('soft_deleted_at'));
		return $qb->execute()->fetch(PDO::FETCH_COLUMN);
	}

	/**
	 * @brief Count the descendant bookmarks in the given folder
	 * @param int $folderId
	 * @return int
	 */
	public function countBookmarksInFolder(int $folderId): int {
		$count = $this->treeCache->get(TreeCacheManager::CATEGORY_FOLDERCOUNT, TreeMapper::TYPE_FOLDER, $folderId);
		if ($count !== null) {
			return $count;
		}
		$qb = $this->db->getQueryBuilder();
		$qb
			->select($qb->func()->count('b.id'))
			->from('bookmarks', 'b')
			->innerJoin('b', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'b.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK)))
			->andWhere($qb->expr()->isNull('t.soft_deleted_at'));
		$countChildren = $qb->execute()->fetch(PDO::FETCH_COLUMN);

		$qb = $this->db->getQueryBuilder();
		$qb
			->select('f.id')
			->from('bookmarks_folders', 'f')
			->innerJoin('f', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'f.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter(TreeMapper::TYPE_FOLDER)))
			->andWhere($qb->expr()->isNull('t.soft_deleted_at'));
		;
		$childFolders = $qb->execute()->fetchAll(PDO::FETCH_COLUMN);

		foreach ($childFolders as $subFolderId) {
			$countChildren += $this->countBookmarksInFolder($subFolderId);
		}
		$this->treeCache->set(TreeCacheManager::CATEGORY_FOLDERCOUNT, TreeMapper::TYPE_FOLDER, $folderId, $countChildren);
		return $countChildren;
	}

	/**
	 * @return array
	 *
	 * @psalm-return list<array{children?: list<array{children?: array<array-key, mixed>, id: int, type: 'bookmark'|'folder'}>, id: int, title: mixed, type: 'folder'|'bookmark', userId: string, ...<array-key, mixed>}>
	 */
	public function getChildren(int $folderId, int $layers = 0): array {
		$children = $this->treeCache->get(TreeCacheManager::CATEGORY_CHILDREN, TreeMapper::TYPE_FOLDER, $folderId);
		if ($children !== null) {
			return $children;
		}

		$children = $this->treeCache->get(TreeCacheManager::CATEGORY_CHILDREN_LAYER, TreeMapper::TYPE_FOLDER, $folderId);

		if ($children === null) {
			$qb = $this->getChildrenQuery[TreeMapper::TYPE_BOOKMARK];
			$this->selectFromType(TreeMapper::TYPE_BOOKMARK, ['t.index', 't.type'], $qb);
			$qb->setParameter('parent_folder', $folderId);
			$childBookmarks = $qb->execute()->fetchAll();

			$qb = $this->getChildrenQuery[TreeMapper::TYPE_FOLDER];
			$this->selectFromType(TreeMapper::TYPE_FOLDER, ['t.index', 't.type'], $qb);
			$qb->setParameter('parent_folder', $folderId);
			$childFolders = $qb->execute()->fetchAll();

			$qb = $this->getChildrenQuery[TreeMapper::TYPE_SHARE];
			$this->selectFromType(TreeMapper::TYPE_SHARE, ['t.index', 't.type'], $qb);
			$qb->setParameter('parent_folder', $folderId);
			$childShares = $qb->execute()->fetchAll();

			$children = array_merge($childBookmarks, $childFolders, $childShares);
			$indices = array_column($children, 'index');
			array_multisort($indices, $children);

			$this->treeCache->set(TreeCacheManager::CATEGORY_CHILDREN_LAYER, TreeMapper::TYPE_FOLDER, $folderId, $children);
		}

		$children = array_map(function ($child) use ($layers) {
			$item = ['type' => $child['type'], 'id' => (int)$child['id'], 'title' => $child['title'], 'userId' => $child['user_id']];

			if ($item['type'] === TreeMapper::TYPE_SHARE) {
				$item['type'] = TreeMapper::TYPE_FOLDER;
				$item['id'] = (int)$child['folder_id'];
			}

			if ($item['type'] === TreeMapper::TYPE_BOOKMARK) {
				$item = array_merge(Bookmark::fromRow(array_intersect_key($child, array_flip(Bookmark::$columns)))->toArray(), $item);
			}

			if ($item['type'] === TreeMapper::TYPE_FOLDER && $layers !== 0) {
				$item['children'] = $this->getChildren($item['id'], $layers - 1);
			}

			return $item;
		}, $children);

		if ($layers < 0) {
			$this->treeCache->set(TreeCacheManager::CATEGORY_CHILDREN, TreeMapper::TYPE_FOLDER, $folderId, $children);
		}

		return $children;
	}

	/**
	 * @param int $folderId
	 * @return void
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	private function removeFolderTangibles(int $folderId): void {
		try {
			// Remove shares of this folder
			$shares = $this->shareMapper->findByFolder($folderId);
			foreach ($shares as $share) {
				$this->deleteShare($share->getId());
			}

			// remove public folder
			$publicFolder = $this->publicFolderMapper->findByFolder($folderId);
			$this->publicFolderMapper->delete($publicFolder);
		} catch (DoesNotExistException $e) {
			// noop
		}
	}

	/**
	 * @param Folder $folder
	 * @param $userId
	 *
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 *
	 * @return void
	 */
	public function changeFolderOwner(Folder $folder, $userId): void {
		$folder->setUserId($userId);
		$this->folderMapper->update($folder);

		$children = $this->getChildren($folder->getId());
		foreach ($children as $child) {
			if ($child['type'] === 'bookmark') {

				$bookmark = $this->bookmarkMapper->find($child['id']);
				$bookmark->setUserId($userId);
				$this->bookmarkMapper->update($bookmark);
			} elseif ($child['type'] === 'folder') {
				$folder = $this->folderMapper->find($child['id']);
				$this->changeFolderOwner($folder, $userId);
			}
		}
	}

	/**
	 * @param int $folderId
	 * @param Share $share
	 * @return boolean
	 * @throws MultipleObjectsReturnedException
	 */
	public function isFolderSharedWithUser(int $folderId, string $userId): bool {
		try {
			$this->sharedFolderMapper->findByFolderAndUser($folderId, $userId);
			return true;
		} catch (DoesNotExistException) {
			// noop
		}

		try {
			while ($ancestorFolder = $this->findParentOf(TreeMapper::TYPE_FOLDER, $folderId)) {
				try {
					$this->sharedFolderMapper->findByFolderAndUser($ancestorFolder->getId(), $userId);
					return true;
				} catch (DoesNotExistException) {
					// noop
				}
				$folderId = $ancestorFolder->getId();
			}
		} catch (DoesNotExistException $e) {
			// noop
		}

		return false;
	}

	/**
	 * @param Folder $folder
	 * @param string $userId
	 * @return boolean
	 */
	public function containsSharedFolderFromUser(Folder $folder, string $userId): bool {
		$sharedFolders = $this->sharedFolderMapper->findByOwnerAndUser($userId, $folder->getUserId());

		foreach ($sharedFolders as $sharedFolder) {
			if ($this->hasDescendant($folder->getId(), TreeMapper::TYPE_SHARE, $sharedFolder->getId())) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param Folder $folder
	 * @param string $userId
	 * @return bool
	 */
	public function containsFoldersSharedToUser(Folder $folder, string $userId): bool {
		$sharedFolders = $this->sharedFolderMapper->findByOwnerAndUser($folder->getUserId(), $userId);

		foreach ($sharedFolders as $sharedFolder) {
			if ($folder->getId() === $sharedFolder->getFolderId()) {
				return true;
			}
			if ($this->hasDescendant($folder->getId(), TreeMapper::TYPE_FOLDER, $sharedFolder->getFolderId())) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param int $limit
	 * @param float|int $maxAge
	 * @return void
	 */
	public function deleteOldTrashbinItems(int $limit, float|int $maxAge): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('type', 'id', 'parent_folder')->from('bookmarks_tree');
		$qb->where($qb->expr()->neq('type', $qb->createNamedParameter(TreeMapper::TYPE_SHARE, IQueryBuilder::PARAM_STR)));
		$cutoffDate = $this->timeFactory->getDateTime();
		$cutoffDate->modify('- ' . $maxAge . ' seconds');
		$qb->andWhere($qb->expr()->lt('soft_deleted_at', $qb->createNamedParameter($cutoffDate, IQueryBuilder::PARAM_DATE)));
		$qb->setMaxResults($limit);
		try {
			$result = $qb->executeQuery();
		} catch (Exception $e) {
			$this->logger->error('Could not query for old trash bin items', ['exception' => $e]);
			return;
		}
		while ($row = $result->fetch()) {
			try {
				$this->deleteEntry($row['type'], $row['id'], $row['parent_folder']);
			} catch (DoesNotExistException $e) {
				// noop
			} catch (UnsupportedOperation|MultipleObjectsReturnedException $e) {
				$this->logger->error('Could not delete old trash bin item: ' . var_export($row, true), ['exception' => $e]);
			}
		}
	}
}
