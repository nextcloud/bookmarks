<?php

namespace OCA\Bookmarks\Db;

use OCA\Bookmarks\Events\BeforeDeleteEvent;
use OCA\Bookmarks\Events\MoveEvent;
use OCA\Bookmarks\Events\UpdateEvent;
use OCA\Bookmarks\Exception\ChildrenOrderValidationError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\ICache;
use OCP\IConfig;
use OCP\IDBConnection;


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
	 * @var ICache
	 */
	protected $cache;

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
	 */
	public function __construct(IDBConnection $db, IEventDispatcher $eventDispatcher, FolderMapper $folderMapper, BookmarkMapper $bookmarkMapper, ShareMapper $shareMapper, SharedFolderMapper $sharedFolderMapper, TagMapper $tagMapper, IConfig $config) {
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
		return \call_user_func($entityClass . '::fromRow', $row);
	}


	/**
	 * Runs a sql query and returns an array of entities
	 *
	 * @param IQueryBuilder $query
	 * @param string $type
	 * @return Entity[] all fetched entities
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
	 * @return IQueryBuilder
	 */
	protected function selectFromType(string $type): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select(array_map(static function ($col) {
				return 'i.' . $col;
			}, $this->entityColumns[$type]))
			->from($this->entityTables[$type], 'i');
		return $qb;
	}

	/**
	 * @param int $folderId
	 * @param string $type
	 * @return array|Entity[]
	 */
	public function findChildren(string $type, int $folderId): array {
		$qb = $this->selectFromType($type);
		$qb
			->join('i', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'i.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter($type)))
			->orderBy('t.index', 'ASC');
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
		$qb = $this->selectFromType(self::TYPE_FOLDER);
		$qb
			->join('i', 'bookmarks_tree', 't', $qb->expr()->eq('t.parent_folder', 'i.id'))
			->where($qb->expr()->eq('t.id', $qb->createPositionalParameter($itemId)))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter($type)));
		return $this->findEntityWithType($qb, $type);
	}

	/**
	 * @param string $type
	 * @param int $itemId
	 * @return array|Entity[]
	 */
	public function findParentsOf(string $type, int $itemId): array {
		$qb = $this->selectFromType(self::TYPE_FOLDER);
		$qb
			->join('i', 'bookmarks_tree', 't', $qb->expr()->eq('t.parent_folder', 'i.id'))
			->where($qb->expr()->eq('t.id', $qb->createPositionalParameter($itemId)))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter($type)));
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
			$newDescendants = array_flatten(array_map(function (Entity $descendant) use ($type) {
				return $this->findChildren($type, $descendant->getId());
			}, $newDescendants));
			array_push($descendants, ...$newDescendants);
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
			$childFolders = $this->findChildren(self::TYPE_FOLDER, $id);
			foreach ($childFolders as $childFolder) {
				$this->deleteEntry(self::TYPE_FOLDER, $childFolder->getId());
			}

			$childBookmarks = $this->findChildren(self::TYPE_BOOKMARK, $id);
			foreach ($childBookmarks as $bookmark) {
				$this->deleteEntry(self::TYPE_BOOKMARK, $bookmark->getId(), $id);
			}

			$childShares = $this->findChildren(self::TYPE_SHARE, $id);
			foreach ($childShares as $share) {
				$this->deleteEntry(self::TYPE_SHARE, $share->getId(), $id);
			}

			$this->remove($type, $id);

			$folder = $this->folderMapper->find($id);
			$this->folderMapper->delete($folder);
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
	 * @param string $type
	 * @param int $itemId
	 * @param int $newParentFolderId
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function move(string $type, int $itemId, int $newParentFolderId): void {
		if ($type === self::TYPE_BOOKMARK) {
			throw new UnsupportedOperation('Cannot move Bookmark');
		}
		try {
			$currentParent = $this->findParentOf($type, $itemId);

			// Item currently has a parent => move.

			$qb = $this->db->getQueryBuilder();
			$qb
				->update('bookmarks_tree')
				->set('parent_folder', $qb->createPositionalParameter($newParentFolderId, IQueryBuilder::PARAM_INT))
				->set('index', $qb->createPositionalParameter($this->countChildren($newParentFolderId)))
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($itemId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter($type)));
			$qb->execute();
		} catch (DoesNotExistException $e) {
			// Item currently has no parent => insert into tree.
			$currentParent = null;

			$qb = $this->db->getQueryBuilder();
			$qb
				->insert('bookmarks_tree')
				->values([
					'id' => $qb->createPositionalParameter($itemId),
					'parent_folder' => $qb->createPositionalParameter($newParentFolderId, IQueryBuilder::PARAM_INT),
					'type' => $qb->createPositionalParameter($type),
					'index' => $qb->createPositionalParameter($this->countChildren($newParentFolderId)),
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
	 * @throws UnsupportedOperation
	 */
	public function addToFolders(string $type, int $itemId, array $folders): void {
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
			$qb = $this->db->getQueryBuilder();
			$qb
				->insert('bookmarks_tree')
				->values([
					'parent_folder' => $qb->createPositionalParameter($folderId, IQueryBuilder::PARAM_INT),
					'type' => $qb->createPositionalParameter($type),
					'id' => $qb->createPositionalParameter($itemId, IQueryBuilder::PARAM_INT),
					'index' => $qb->createPositionalParameter($this->countChildren($folderId)),
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
				throw new ChildrenOrderValidationError('A child is missing');
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
			->select('folder_id', 'f.id')
			->from('bookmarks_shares', 's')
			->innerJoin('s', 'bookmarks_shared_folders', 'f', $qb->expr()->eq('f.share_id', 's.id'))
			->innerJoin('f', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'f.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter(self::TYPE_SHARE)))
			->orderBy('t.index', 'ASC');
		$childShares = $qb->execute()->fetchAll();

		$foldersToShares = array_reduce($childShares, static function ($dict, $shareRec) {
			$dict[$shareRec['folder_id']] = $shareRec['f.id'];
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
	 * @param $folderId
	 * @param int $layers The amount of levels to return
	 * @return array the children each in the format ["id" => int, "type" => 'bookmark' | 'folder' ]
	 */
	public function getChildrenOrder($folderId, $layers = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id', 'type', 'index')
			->from('bookmarks_tree')
			->where($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)))
			->orderBy('index', 'ASC');
		$children = $qb->execute()->fetchAll();

		$qb = $this->db->getQueryBuilder();
		$qb
			->select('folder_id', 'index')
			->from('bookmarks_shares', 's')
			->innerJoin('s', 'bookmarks_shared_folders', 'f', $qb->expr()->eq('f.share_id', 's.id'))
			->innerJoin('f', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'f.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter(self::TYPE_SHARE)))
			->orderBy('t.index', 'ASC');
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
		return $children;
	}

	/**
	 * @param $folderId
	 * @param int $layers
	 * @return array
	 */
	public function getSubFolders($folderId, $layers = 0): array {
		$folders = array_map(function (Folder $folder) use ($layers, $folderId) {
			$array = $folder->toArray();
			$array['parent_folder'] = $folderId;
			if ($layers - 1 !== 0) {
				$array['children'] = $this->getSubFolders($folder->getId(), $layers - 1);
			}
			return $array;
		}, $this->findChildren(self::TYPE_FOLDER, $folderId));
		$shares = array_map(function (SharedFolder $sharedFolder) use ($layers, $folderId) {
			$share = $this->shareMapper->find($sharedFolder->getShareId());
			$array = $sharedFolder->toArray();
			$array['id'] = $share->getFolderId();
			$array['userId'] = $share->getOwner();
			$array['parent_folder'] = $folderId;
			if ($layers - 1 !== 0) {
				$array['children'] = $this->getSubFolders($share->getFolderId(), $layers - 1);
			}
			return $array;
		}, $this->findChildren(self::TYPE_SHARE, $folderId));
		if (count($shares) > 0) {
			array_push($folders, ...$shares);
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
		return $qb->execute()->fetch(\PDO::FETCH_COLUMN);
	}

	/**
	 * @brief Count the descendant bookmarks in the given folder
	 * @param int $folderId
	 * @return int
	 */
	public function countBookmarksInFolder(int $folderId): int {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select($qb->func()->count('b.id'))
			->from('bookmarks', 'b')
			->innerJoin('b', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'b.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter(self::TYPE_BOOKMARK)));
		$countChildren = $qb->execute()->fetch(\PDO::FETCH_COLUMN);

		$qb = $this->db->getQueryBuilder();
		$qb
			->select('f.id')
			->from('bookmarks_folders', 'f')
			->innerJoin('f', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'f.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter(self::TYPE_FOLDER)));
		$childFolders = $qb->execute()->fetchAll(\PDO::FETCH_COLUMN);

		foreach ($childFolders as $subFolderId) {
			$countChildren += $this->countBookmarksInFolder($subFolderId);
		}
		return $countChildren;
	}

	public function getChildren(int $folderId, int $layers = 0) {
		$qb = $this->db->getQueryBuilder();
		$cols = array_merge(['index', 't.type'], array_map(static function ($col) {
			return 'b.' . $col;
		}, Bookmark::$columns));
		$qb
			->select($cols)
			->from('bookmarks', 'b')
			->innerJoin('b', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'b.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter(self::TYPE_BOOKMARK)))
			->orderBy('t.index', 'ASC');
		$childBookmarks = $qb->execute()->fetchAll();

		$qb = $this->db->getQueryBuilder();
		$qb
			->select('f.id', 'title', 'user_id', 'index', 't.type')
			->from('bookmarks_folders', 'f')
			->innerJoin('f', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'f.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter(self::TYPE_FOLDER)))
			->orderBy('t.index', 'ASC');
		$childFolders = $qb->execute()->fetchAll();

		$qb = $this->db->getQueryBuilder();
		$qb
			->select('folder_id', 'f.title', 'user_id', 'index', 't.type')
			->from('bookmarks_shares', 's')
			->innerJoin('s', 'bookmarks_shared_folders', 'f', $qb->expr()->eq('f.share_id', 's.id'))
			->innerJoin('f', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'f.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter(self::TYPE_SHARE)))
			->orderBy('t.index', 'ASC');
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

		return $children;
	}
}
