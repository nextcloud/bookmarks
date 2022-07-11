<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Db;

use OCA\Bookmarks\Events\BeforeDeleteEvent;
use OCA\Bookmarks\Events\MoveEvent;
use OCA\Bookmarks\Events\UpdateEvent;
use OCA\Bookmarks\Exception\ChildrenOrderValidationError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Service\TreeCacheManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IDBConnection;
use PDO;
use function call_user_func;

/**
 * Class TreeMapper
 *
 * @package OCA\Bookmarks\Db
 */
class TreeMapper extends QBMapper {
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

	/** @var IEventDispatcher */
	private $eventDispatcher;

	/**
	 * @var BookmarkMapper
	 */
	protected $bookmarkMapper;

	/**
	 * @var FolderMapper
	 */
	protected $folderMapper;

	/**
	 * @var TreeCacheManager
	 */
	protected $treeCache;

	/**
	 * @var ShareMapper
	 */
	private $shareMapper;
	/**
	 * @var SharedFolderMapper
	 */
	private $sharedFolderMapper;
	/**
	 * @var TagMapper
	 */
	private $tagMapper;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var PublicFolderMapper
	 */
	private $publicFolderMapper;
	/**
	 * @var IQueryBuilder
	 */
	private $insertQuery;
	/**
	 * @var IQueryBuilder
	 */
	private $parentQuery;
	/**
	 * @var array
	 */
	private $getChildrenQuery;
	/**
	 * @var IQueryBuilder
	 */
	private $getChildrenOrderQuery;

	/**
	 * FolderMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param IEventDispatcher $eventDispatcher
	 * @param FolderMapper $folderMapper
	 * @param BookmarkMapper $bookmarkMapper
	 * @param ShareMapper $shareMapper
	 * @param SharedFolderMapper $sharedFolderMapper
	 * @param TagMapper $tagMapper
	 * @param IConfig $config
	 * @param PublicFolderMapper $publicFolderMapper
	 * @param TreeCacheManager $treeCache
	 */
	public function __construct(IDBConnection $db, IEventDispatcher $eventDispatcher, FolderMapper $folderMapper, BookmarkMapper $bookmarkMapper, ShareMapper $shareMapper, SharedFolderMapper $sharedFolderMapper, TagMapper $tagMapper, IConfig $config, \OCA\Bookmarks\Db\PublicFolderMapper $publicFolderMapper, TreeCacheManager $treeCache) {
		parent::__construct($db, 'bookmarks_tree');
		$this->eventDispatcher = $eventDispatcher;
		$this->folderMapper = $folderMapper;
		$this->bookmarkMapper = $bookmarkMapper;
		$this->shareMapper = $shareMapper;
		$this->sharedFolderMapper = $sharedFolderMapper;

		$this->entityColumns = [
			self::TYPE_SHARE => SharedFolder::$columns,
			self::TYPE_FOLDER => Folder::$columns,
			self::TYPE_BOOKMARK => Bookmark::$columns,
		];
		$this->tagMapper = $tagMapper;
		$this->config = $config;
		$this->publicFolderMapper = $publicFolderMapper;

		$this->insertQuery = $this->getInsertQuery();
		$this->parentQuery = $this->getParentQuery();
		$this->getChildrenOrderQuery = $this->getGetChildrenOrderQuery();
		$this->getChildrenQuery = [
			self::TYPE_BOOKMARK => $this->getFindChildrenQuery(self::TYPE_BOOKMARK),
			self::TYPE_FOLDER => $this->getFindChildrenQuery(self::TYPE_FOLDER),
			self::TYPE_SHARE => $this->getFindChildrenQuery(self::TYPE_SHARE)
		];

		$this->treeCache = $treeCache;
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
			->insert('bookmarks_tree')
			->values([
				'id' => $qb->createParameter('id'),
				'parent_folder' => $qb->createParameter('parent_folder'),
				'type' => $qb->createParameter('type'),
				'index' => $qb->createParameter('index'),
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
			->select('id', 'type', 'index')
			->from('bookmarks_tree')
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
	 * @param int $folderId
	 * @param string $type
	 * @param int $descendantId
	 * @return bool
	 */
	public function hasDescendant(int $folderId, string $type, int $descendantId): bool {
		$ancestors = $this->findParentsOf($type, $descendantId);
		while (!in_array($folderId, array_map(static function (Entity $ancestor) {
			return $ancestor->getId();
		}, $ancestors), true)) {
			$ancestors = array_flatten(array_map(function (Entity $ancestor) {
				return $this->findParentsOf(self::TYPE_FOLDER, $ancestor->getId());
			}, $ancestors));
			if (0 === count($ancestors)) {
				return false;
			}
		}
		return true;
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
		$this->eventDispatcher->dispatch(BeforeDeleteEvent::class, new BeforeDeleteEvent($type, $id));

		if ($type === self::TYPE_FOLDER) {
			// First get all shares out of the way
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

			// remove all folders  entries from this subtree
			foreach ($descendantFolders as $descendantFolder) {
				$this->removeFolderTangibles($descendantFolder->getId());
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
			while ($bookmark = $orphanedBookmarks->fetchColumn()) {
				$qb = $this->db->getQueryBuilder();
				$qb->delete('bookmarks')
					->where($qb->expr()->eq('id', $qb->createPositionalParameter($bookmark)))
					->execute();
			}

			return;
		}

		if ($type === self::TYPE_SHARE) {
			$this->remove($type, $id);
			// This will only be removed if the share is removed!
			//$sharedFolder = $this->sharedFolderMapper->find($id);
			//$this->sharedFolderMapper->delete($sharedFolder);
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
			->delete('bookmarks_tree')
			->where($qb->expr()->eq('type', $qb->createPositionalParameter($type)))
			->andWhere($qb->expr()->eq('id', $qb->createPositionalParameter($itemId, IQueryBuilder::PARAM_INT)));
		$qb->execute();
	}

	/**
	 * @param int $shareId
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function deleteShare(int $shareId): void {
		$share = $this->shareMapper->find($shareId);
		$sharedFolders = $this->sharedFolderMapper->findByShare($shareId);
		foreach ($sharedFolders as $sharedFolder) {
			$this->sharedFolderMapper->delete($sharedFolder);
			$this->deleteEntry(self::TYPE_SHARE, $sharedFolder->getId());
		}
		$this->shareMapper->delete($share);
	}

	/**
	 * @param string $type
	 * @param int $itemId
	 * @param int $newParentFolderId
	 * @param int|null $index
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function move(string $type, int $itemId, int $newParentFolderId, int $index = null): void {
		if ($type === self::TYPE_BOOKMARK) {
			throw new UnsupportedOperation('Cannot move Bookmark');
		}
		try {
			// Try to find current parent
			/** @var Folder $currentParent */
			$currentParent = $this->findParentOf($type, $itemId);

			if ($this->hasDescendant($itemId, self::TYPE_FOLDER, $newParentFolderId)) {
				throw new UnsupportedOperation('Cannot nest a folder inside one of its descendants');
			}

			// Item currently has a parent => move.

			$qb = $this->db->getQueryBuilder();
			$qb
				->update('bookmarks_tree')
				->set('parent_folder', $qb->createPositionalParameter($newParentFolderId, IQueryBuilder::PARAM_INT))
				->set('index', $qb->createPositionalParameter($index ?? $this->countChildren($newParentFolderId)))
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($itemId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter($type)));
			$qb->execute();
		} catch (DoesNotExistException $e) {
			// Item currently has no parent => insert into tree.
			$currentParent = null;

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
	 * @param int $itemId
	 * @param array $folders Set of folders ids to add the bookmark to
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function setToFolders(string $type, int $itemId, array $folders): void {
		if ($type !== self::TYPE_BOOKMARK) {
			throw new UnsupportedOperation('Only bookmarks can be in multiple folders');
		}
		if (0 === count($folders)) {
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
	 * @param int $itemId The bookmark reference
	 * @param array $folders Set of folders ids to add the bookmark to
	 * @param int|null $index
	 * @throws UnsupportedOperation
	 */
	public function addToFolders(string $type, int $itemId, array $folders, int $index = null): void {
		if ($type !== self::TYPE_BOOKMARK) {
			throw new UnsupportedOperation('Only bookmarks can be in multiple folders');
		}
		$currentFolders = array_map(static function (Folder $f) {
			return $f->getId();
		}, $this->findParentsOf($type, $itemId));

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
				->delete('bookmarks_tree')
				->where($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)))
				->andWhere($qb->expr()->eq('id', $qb->createPositionalParameter($itemId)))
				->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter($type)));
			$qb->execute();

			$this->eventDispatcher->dispatch(MoveEvent::class, new MoveEvent($type, $itemId, $folderId));

			$foldersLeft--;
		}
		if ($foldersLeft <= 0 && $type === self::TYPE_BOOKMARK) {
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
				throw new ChildrenOrderValidationError('A child is missing: '.$child['type'].':'.$child['id']);
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
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter(self::TYPE_SHARE)))
			->orderBy('t.index', 'ASC');
		$childShares = $qb->execute()->fetchAll();

		$foldersToShares = array_reduce($childShares, static function ($dict, $shareRec) {
			$dict[$shareRec['folder_id']] = $shareRec['id'];
			return $dict;
		}, []);

		foreach ($newChildrenOrder as $i => $child) {
			if (!in_array($child['type'], [self::TYPE_FOLDER, self::TYPE_BOOKMARK], true)) {
				continue;
			}

			if (($child['type'] === self::TYPE_FOLDER) && isset($foldersToShares[$child['id']])) {
				$child['type'] = self::TYPE_SHARE;
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

		$this->eventDispatcher->dispatch(UpdateEvent::class, new UpdateEvent(self::TYPE_FOLDER, $folderId));
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 *
	 * @param $folderId
	 * @param int $layers The amount of levels to return
	 *
	 * @return (array|int|mixed|string)[][] the children each in the format ["id" => int, "type" => 'bookmark' | 'folder' ]
	 *
	 * @psalm-return array<array-key, array{type: mixed|string, id: int, children?: array}>
	 */
	public function getChildrenOrder(int $folderId, $layers = 0): array {
		$children = $this->treeCache->get(TreeCacheManager::CATEGORY_CHILDORDER, TreeMapper::TYPE_FOLDER, $folderId);
		if ($children !== null) {
			return $children;
		}
		$qb = $this->getChildrenOrderQuery;
		$qb->setParameter('parent_folder', $folderId);
		$children = $qb->execute()->fetchAll();

		$qb = $this->getChildrenQuery[self::TYPE_SHARE];
		$this->selectFromType(self::TYPE_SHARE, ['t.index'], $qb);
		$qb->setParameter('parent_folder', $folderId);
		$childShares = $qb->execute()->fetchAll();

		$children = array_map(function ($child) use ($layers, $childShares) {
			$item = ['type' => $child['type'], 'id' => (int)$child['id']];

			if ($item['type'] === self::TYPE_SHARE) {
				$item['type'] = self::TYPE_FOLDER;
				$item['id'] = (int)array_shift($childShares)['folder_id'];
			}

			if ($item['type'] === self::TYPE_FOLDER && $layers !== 0) {
				$item['children'] = $this->getChildrenOrder($item['id'], $layers - 1);
			}
			return $item;
		}, $children);

		if ($layers < 0) {
			$this->treeCache->set(TreeCacheManager::CATEGORY_CHILDORDER, TreeMapper::TYPE_FOLDER, $folderId, $children);
		}

		return $children;
	}

	/**
	 * @param int $folderId
	 * @param int $layers [-1, inf]
	 *
	 * @return (int|mixed)[][]
	 *
	 * @psalm-return array<array-key, array{parent_folder: int}>
	 */
	public function getSubFolders(int $folderId, $layers = 0): array {
		$folders = $this->treeCache->get(TreeCacheManager::CATEGORY_SUBFOLDERS, TreeMapper::TYPE_FOLDER, $folderId);
		if ($folders !== null) {
			return $folders;
		}
		$folders = array_map(function (Folder $folder) use ($layers, $folderId) {
			$array = $folder->toArray();
			$array['parent_folder'] = $folderId;
			if ($layers !== 0) {
				$array['children'] = $this->getSubFolders($folder->getId(), $layers - 1);
			}
			return $array;
		}, $this->findChildren(self::TYPE_FOLDER, $folderId));
		$shares = array_map(function (SharedFolder $sharedFolder) use ($layers, $folderId) {
			/**
			 * @var $share Share
			 */
			$share = $this->shareMapper->findBySharedFolder($sharedFolder->getId());
			$array = $sharedFolder->toArray();
			$array['id'] = $share->getFolderId();
			$array['userId'] = $share->getOwner();
			$array['parent_folder'] = $folderId;
			if ($layers !== 0) {
				$array['children'] = $this->getSubFolders($share->getFolderId(), $layers - 1);
			}
			return $array;
		}, $this->findChildren(self::TYPE_SHARE, $folderId));
		if (count($shares) > 0) {
			array_push($folders, ...$shares);
		}
		if ($layers < 0) {
			$this->treeCache->set(TreeCacheManager::CATEGORY_SUBFOLDERS, TreeMapper::TYPE_FOLDER, $folderId, $folders);
		}
		return $folders;
	}

	/**
	 * @brief Count the children in the given folder
	 * @param int $folderId
	 * @return mixed
	 */
	public function countChildren(int $folderId) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select($qb->func()->count('index', 'count'))
			->from('bookmarks_tree')
			->where($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)));
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
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter(self::TYPE_BOOKMARK)));
		$countChildren = $qb->execute()->fetch(PDO::FETCH_COLUMN);

		$qb = $this->db->getQueryBuilder();
		$qb
			->select('f.id')
			->from('bookmarks_folders', 'f')
			->innerJoin('f', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'f.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter(self::TYPE_FOLDER)));
		$childFolders = $qb->execute()->fetchAll(PDO::FETCH_COLUMN);

		foreach ($childFolders as $subFolderId) {
			$countChildren += $this->countBookmarksInFolder($subFolderId);
		}
		$this->treeCache->set(TreeCacheManager::CATEGORY_FOLDERCOUNT, TreeMapper::TYPE_FOLDER, $folderId, $countChildren);
		return $countChildren;
	}

	/**
	 * @return (int|mixed|string)[][]
	 *
	 * @psalm-return array<array-key, array<string, int|mixed|string>>
	 */
	public function getChildren(int $folderId, int $layers = 0): array {
		$children = $this->treeCache->get(TreeCacheManager::CATEGORY_CHILDREN, TreeMapper::TYPE_FOLDER, $folderId);
		if ($children !== null) {
			return $children;
		}
		$qb = $this->getChildrenQuery[self::TYPE_BOOKMARK];
		$this->selectFromType(self::TYPE_BOOKMARK, ['t.index', 't.type'], $qb);
		$qb->setParameter('parent_folder', $folderId);
		$childBookmarks = $qb->execute()->fetchAll();

		$qb = $this->getChildrenQuery[self::TYPE_FOLDER];
		$this->selectFromType(self::TYPE_FOLDER, ['t.index', 't.type'], $qb);
		$qb->setParameter('parent_folder', $folderId);
		$childFolders = $qb->execute()->fetchAll();

		$qb = $this->getChildrenQuery[self::TYPE_SHARE];
		$this->selectFromType(self::TYPE_SHARE, ['t.index', 't.type'], $qb);
		$qb->setParameter('parent_folder', $folderId);
		$childShares = $qb->execute()->fetchAll();

		$children = array_merge($childBookmarks, $childFolders, $childShares);
		$indices = array_column($children, 'index');
		array_multisort($indices, $children);

		$bookmark = new Bookmark();

		$children = array_map(function ($child) use ($layers, $bookmark) {
			$item = ['type' => $child['type'], 'id' => (int)$child['id'], 'title' => $child['title'], 'userId' => $child['user_id']];

			if ($item['type'] === self::TYPE_SHARE) {
				$item['type'] = self::TYPE_FOLDER;
				$item['id'] = (int)$child['folder_id'];
			}

			if ($item['type'] === self::TYPE_BOOKMARK) {
				foreach (Bookmark::$columns as $col) {
					$item[$bookmark->columnToProperty($col)] = $child[$col];
				}
			}

			if ($item['type'] === self::TYPE_FOLDER && $layers !== 0) {
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
				/** @var Bookmark $bookmark */
				$bookmark = $this->bookmarkMapper->find($child['id']);
				$bookmark->setUserId($userId);
				$this->bookmarkMapper->update($bookmark);
			} elseif ($child['type'] === 'folder') {
				/** @var Folder $folder */
				$folder = $this->folderMapper->find($child['id']);
				$this->changeFolderOwner($folder, $userId);
			}
		}
	}
}
