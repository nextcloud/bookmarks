<?php

namespace OCA\Bookmarks\Db;

use OCA\Bookmarks\Events\Delete;
use OCA\Bookmarks\Events\Move;
use OCA\Bookmarks\Events\Update;
use OCA\Bookmarks\Exception\ChildrenOrderValidationError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\ICache;
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
		self::TYPE_SHARE => Share::class,
		self::TYPE_FOLDER => Folder::class,
		self::TYPE_BOOKMARK => Bookmark::class,
	];

	/** @var IEventDispatcher */
	private $eventDispatcher;

	/**
	 * @var BookmarkMapper
	 */
	protected $bookmarkMapper;

	/**
	 * @var SharedFolderMapper
	 */
	protected $sharedFolderMapper;


	/**
	 * @var FolderMapper
	 */
	protected $folderMapper;

	/**
	 * @var ICache
	 */
	protected $cache;

	/**
	 * FolderMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param IEventDispatcher $eventDispatcher
	 * @param FolderMapper $folderMapper
	 */
	public function __construct(IDBConnection $db, IEventDispatcher $eventDispatcher, FolderMapper $folderMapper) {
		parent::__construct($db, 'bookmarks_tree');
		$this->folderMapper = $folderMapper;
		$this->eventDispatcher = $eventDispatcher;
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
			->select('*')
			->from('bookmarks_'.$type.'s', 'i');
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
		$qb = $this->selectFromType($type);
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
		$qb = $this->selectFromType($type);
		$qb
			->join('i', 'bookmarks_tree', 't', $qb->expr()->eq('t.parent_folder', 'i.id'))
			->where($qb->expr()->eq('t.id', $qb->createPositionalParameter($itemId)))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter($type)));
		return $this->findEntitiesWithType($qb, $type);
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
			$descendants[] = $newDescendants;
		} while (count($newDescendants) > 0);
		return $descendants;
	}

	/**
	 * @param int $folderId
	 * @param string $type
	 * @param int $descendantFolderId
	 * @return bool
	 * @throws MultipleObjectsReturnedException
	 */
	public function hasDescendant(int $folderId, string $type, int $descendantFolderId): bool {
		do {
			try {
				$descendant = $this->findParentOf($type, $descendantFolderId);
			} catch (DoesNotExistException $e) {
				return false;
			}
		} while ($descendant->getId() !== $folderId);
		return true;
	}


	/**
	 * @param string $type
	 * @param int $id
	 * @param int|null $folderId
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function deleteEntry(string $type, int $id, int $folderId = null): void {
		$this->eventDispatcher->dispatch(Delete::class, new Delete(null, [
			'id' => $id,
			'type' => $type,
		]));

		if ($type === self::TYPE_FOLDER) {
			$folder = $this->folderMapper->find($id);
			$this->folderMapper->delete($folder);

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
			return;
		}

		if ($type === self::TYPE_BOOKMARK) {
			$this->removeFromFolders(self::TYPE_BOOKMARK, $id, [$folderId]);
		}

		if ($type === self::TYPE_SHARE) {
			$this->removeFromFolders(self::TYPE_SHARE, $id, [$folderId]);
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
			->delete('bookmarks_tree', 't')
			->where($qb->expr()->eq('t.type', $qb->createPositionalParameter($type)))
			->andWhere($qb->expr()->eq('t.id', $qb->createPositionalParameter($itemId)));
		$qb->execute();
	}

	/**
	 * @param string $type
	 * @param int $itemId
	 * @param int $newParentFolderId
	 * @throws MultipleObjectsReturnedException
	 */
	public function move(string $type, int $itemId, int $newParentFolderId): void {
		try {
			$currentParent = $this->findParentOf($type, $itemId);
		} catch (DoesNotExistException $e) {
			$currentParent = null;
		}
		$this->remove($type, $itemId);

		$qb = $this->db->getQueryBuilder();
		$qb
			->update('bookmarks_tree')
			->values([
				'parent_folder' => $qb->createPositionalParameter($newParentFolderId),
			])
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($itemId)))
			->andWhere($qb->expr()->eq('type', self::TYPE_FOLDER));
		$qb->execute();

		$this->eventDispatcher->dispatch(Move::class, new Move(null, [
			'type' => $type,
			'id' => $itemId,
			'newParent' => $newParentFolderId,
			'oldParent' => $currentParent ? $currentParent->getId() : null,
		]));
	}


	/**
	 * @brief Add a bookmark to a set of folders
	 * @param string $type
	 * @param int $itemId
	 * @param array $folders Set of folders ids to add the bookmark to
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function setToFolders(string $type, int $itemId, array $folders): void {
		if ($type === self::TYPE_FOLDER) {
			throw new \InvalidArgumentException('Type must not be folder');
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
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function addToFolders(string $type, int $itemId, array $folders): void {
		if ($type === self::TYPE_FOLDER) {
			throw new \InvalidArgumentException('Type must not be folder');
		}
		$this->bookmarkMapper->find($itemId);
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
					'parent_folder' => $qb->createNamedParameter($folderId),
					'type' => $qb->createPositionalParameter($type),
					'id' => $qb->createNamedParameter($itemId),
					'index' => $this->countChildren($folderId),
				]);
			$qb->execute();

			$this->eventDispatcher->dispatch(Move::class, new Move(null, [
				'type' => $type,
				'id' => $itemId,
				'newParent' => $folderId,
			]));
		}
	}

	/**
	 * @brief Remove a bookmark from a set of folders
	 * @param string $type
	 * @param int $itemId The bookmark reference
	 * @param array $folders Set of folders ids to add the bookmark to
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function removeFromFolders(string $type, int $itemId, array $folders): void {
		if ($type === self::TYPE_BOOKMARK) {
			throw new \InvalidArgumentException('Type must not be folder');
		}
		$foldersLeft = count($this->findParentsOf($type, $itemId));

		foreach ($folders as $folderId) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->delete('bookmarks_tree')
				->where($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)))
				->andWhere($qb->expr()->eq('id', $qb->createPositionalParameter($itemId)))
				->andWhere($qb->expr()->eq('t.type', $type));
			$qb->execute();

			$this->eventDispatcher->dispatch(Move::class, new Move(null, [
				'type' => $type,
				'id' => $itemId,
				'oldParent' => $folderId,
			]));

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
			if (!in_array($child, $newChildrenOrder, true)) {
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
			->select('folder_id', 'id')
			->from('bookmarks_shares', 's')
			->innerJoin('s', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 's.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId)))
			->where($qb->expr()->eq('t.type', self::TYPE_SHARE))
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
				->andWhere($qb->expr()->eq('type', $child['type']));
			$qb->execute();
		}

		$this->eventDispatcher->dispatch(Update::class, new Update(null, [
			'id' => $folderId,
			'type' => self::TYPE_FOLDER,
		]));
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 * @param $folderId
	 * @param int $layers The amount of levels to return
	 * @return array the children each in the format ["id" => int, "type" => 'bookmark' | 'folder' ]
	 */
	public function getChildrenOrder($folderId, $layers = 1): array {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id', 'type', 'index')
			->from('bookmarks_tree')
			->andWhere($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)))
			->orderBy('index', 'ASC');
		$children = $qb->execute()->fetchAll();

		$qb = $this->db->getQueryBuilder();
		$qb
			->select('folder_id', 'index')
			->from('bookmarks_shares', 's')
			->innerJoin('s', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 's.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId)))
			->where($qb->expr()->eq('t.type', self::TYPE_SHARE))
			->orderBy('t.index', 'ASC');
		$childShares = $qb->execute()->fetchAll();

		$children = array_map(function ($child) use ($layers, $childShares) {
			if (isset($child['bookmark_id'])) {
				return ['type' => self::TYPE_BOOKMARK, 'id' => (int)$child['bookmark_id']];
			}

			$item = $item = ['type' => $child['type'], 'id' => $child['id']];

			if ($item['type'] === self::TYPE_SHARE) {
				$item['type'] = 'folder';
				$item['id'] = array_shift($childShares)['folder_id'];
			}

			if ($item['type'] === self::TYPE_FOLDER && $layers > 1) {
				$item['children'] = $this->getChildrenOrder($item['id'], $layers - 1);
			}
			return $item;
		}, $children);
		return $children;
	}

	/**
	 * @param int $root
	 * @param int $layers
	 * @return array
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getSubFolders($root, $layers = 0) : array {
		$folders = array_map(function (Folder $folder) use ($layers) {
			$array = $folder->toArray();
			if ($layers - 1 !== 0) {
				$array['children'] = $this->getSubFolders($folder->getId(), $layers - 1);
			}
			return $array;
		}, $this->findChildren(self::TYPE_FOLDER, $root));
		$folder = $this->folderMapper->find($root);
		$shares = array_map(function (Share $share) use ($layers, $root, $folder) {
			$folder = $this->sharedFolderMapper->findByShareAndUser($share->getId(), $folder->getUserId());
			$array = $folder->toArray();
			$array['id'] = $share->getFolderId();
			$array['userId'] = $share->getOwner();
			if ($layers - 1 !== 0) {
				$array['children'] = $this->getSubFolders($share->getFolderId(), $layers - 1);
			}
			return $array;
		}, $this->findChildren(self::TYPE_SHARE, $root));
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
}
