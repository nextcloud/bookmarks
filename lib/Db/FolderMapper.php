<?php

namespace OCA\Bookmarks\Db;

use OCA\Bookmarks\Exception\ChildrenOrderValidationError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use UnexpectedValueException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Class FolderMapper
 *
 * @package OCA\Bookmarks\Db
 */
class FolderMapper extends QBMapper implements IEventListener {

	public const TYPE_SHARE = 'share';
	public const TYPE_FOLDER = 'folder';
	public const TYPE_BOOKMARK = 'bookmark';

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
	 * @var ICache
	 */
	protected $cache;

	protected $cachedFolders;

	/**
	 * FolderMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param BookmarkMapper $bookmarkMapper
	 * @param ShareMapper $shareMapper
	 * @param SharedFolderMapper $sharedFolderMapper
	 * @param ICacheFactory $cacheFactory
	 */
	public function __construct(IDBConnection $db, BookmarkMapper $bookmarkMapper, ShareMapper $shareMapper, SharedFolderMapper $sharedFolderMapper, ICacheFactory $cacheFactory) {
		parent::__construct($db, 'bookmarks_folders', Folder::class);
		$this->bookmarkMapper = $bookmarkMapper;
		$this->shareMapper = $shareMapper;
		$this->sharedFolderMapper = $sharedFolderMapper;
		$this->cache = $cacheFactory->createLocal('bookmarks:hashes');
		$this->cachedFolders = [];
	}

	/**
	 * @param int $id
	 * @return Folder
	 * @throws DoesNotExistException if not found
	 * @throws MultipleObjectsReturnedException if more than one result
	 */
	public function find(int $id): Entity {
		if (isset($this->cachedFolders[$id]) && $this->cachedFolders[$id] !== null) {
			return $this->cachedFolders[$id];
		}
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks_folders')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		$this->cachedFolders[$id] = $this->findEntity($qb);
		return $this->cachedFolders[$id];
	}

	/**
	 * @param $userId
	 * @return Entity
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findRootFolder($userId): Entity {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select(Folder::$columns)
			->from('bookmarks_folders', 'f')
			->join('f', 'bookmarks_root_folders', 't', $qb->expr()->eq('id', 'folder_id'))
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $this->findEntity($qb);
	}

	/**
	 * @param int $folderId
	 * @return array|Entity[]
	 */
	public function findChildFolders(int $folderId): array {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select(Folder::$columns)
			->from('bookmarks_folders', 'f')
			->join('f', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'f.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('t.type', self::TYPE_FOLDER))
			->orderBy('t.index', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * @param int $folderId
	 * @return Entity
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findParentOfFolder(int $folderId): Entity {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select(Folder::$columns)
			->from('bookmarks_folders', 'f')
			->join('f', 'bookmarks_tree', 't', $qb->expr()->eq('t.parent_folder', 'f.id'))
			->where($qb->expr()->eq('t.id', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('type', self::TYPE_FOLDER));
		return $this->findEntity($qb);
	}

	/**
	 * @param $folderId
	 * @return array|Entity[]
	 */
	public function findByAncestorFolder($folderId): array {
		$descendants = [];
		$newDescendants = $this->findChildFolders($folderId);
		do {
			$newDescendants = array_flatten(array_map(function ($descendant) {
				return $this->findChildFolders($descendant);
			}, $newDescendants));
			$descendants[] = $newDescendants;
		} while (count($newDescendants) > 0);
		return $descendants;
	}

	/**
	 * @param $folderId
	 * @param $descendantFolderId
	 * @return bool
	 * @throws MultipleObjectsReturnedException
	 */
	public function hasDescendantFolder($folderId, $descendantFolderId): bool {
		do {
			try {
				$descendant = $this->findParentOfFolder($descendantFolderId);
			} catch (DoesNotExistException $e) {
				return false;
			}
		} while ($descendant->getId() !== $folderId);
		return true;
	}

	/**
	 * @param int $bookmarkId
	 * @return array|Entity[]
	 */
	public function findParentsOfBookmark(int $bookmarkId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Folder::$columns);

		$qb
			->from('bookmarks_folders', 'f')
			->join('f', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'f.id'))
			->where($qb->expr()->eq('t.id', $qb->createPositionalParameter($bookmarkId)))
			->andWhere($qb->expr()->eq('t.type', self::TYPE_BOOKMARK));

		return $this->findEntities($qb);
	}

	/**
	 * @param $folderId
	 * @param $descendantBookmarkId
	 * @return bool
	 */
	public function hasDescendantBookmark($folderId, $descendantBookmarkId): bool {
		$newAncestors = $this->findParentsOfBookmark($descendantBookmarkId);
		foreach ($newAncestors as $ancestor) {
			if ($ancestor->getId() === $folderId) {
				return true;
			}
			try {
				if ($this->hasDescendantFolder($folderId, $ancestor->getId())) {
					return true;
				}
			} catch (MultipleObjectsReturnedException $e) {
				continue;
			}
		}
		return false;
	}

	/**
	 * @param Entity $entity
	 * @return Entity
	 */
	public function delete(Entity $entity): Entity {
		$childFolders = $this->findChildFolders($entity->getId());
		foreach ($childFolders as $folder) {
			$this->delete($folder);
		}

		$qb = $this->db->getQueryBuilder();
		$qb
			->select(array_merge(Bookmark::$columns, [$qb->func()->count('t2.parent_folder', 'parent_count')]))
			->from('bookmarks', 'b')
			->join('b', 'bookmarks_tree', 't1', $qb->expr()->eq('t1.id', 'b.id'))
			->join('t', 'bookmarks_tree', 't2', $qb->expr()->eq('t1.id', 't2.id'))
			->where($qb->expr()->eq('t2.parent_folder', $qb->createPositionalParameter($entity->getId())))
			->andWhere($qb->expr()->eq('t1.type', self::TYPE_BOOKMARK))
			->andWhere($qb->expr()->eq('t1.type', self::TYPE_BOOKMARK))
			->andWhere($qb->expr()->eq('t2.type', self::TYPE_BOOKMARK))
			->andWhere($qb->expr()->lte('parent_count', 1));
		$bookmarks = $qb->execute();

		foreach ($bookmarks as $bookmarkId) {
			try {
				$this->bookmarkMapper->delete($this->bookmarkMapper->find($bookmarkId));
			} catch (DoesNotExistException $e) {
				continue;
			} catch (MultipleObjectsReturnedException $e) {
				continue;
			}
		}

		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_tree', 't')
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($entity->getId())))
			->andWhere($qb->expr()->eq('t.type', self::TYPE_BOOKMARK));
		$qb->execute();

		$this->cachedFolders[$entity->getId()] = null;
		$this->invalidateCache($entity->getUserId(), $entity->getId());
		return parent::delete($entity);
	}

	/**
	 * @param $userId
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function deleteAll($userId) {
		$rootFolder = $this->findRootFolder($userId);
		$this->delete($rootFolder);
	}

	/**
	 * @param Entity $entity
	 * @return Entity
	 */
	public function update(Entity $entity): Entity {
		$this->cachedFolders[$entity->getId()] = $entity;
		$this->invalidateCache($entity->getUserId(), $entity->getId());
		return parent::update($entity);
	}

	/**
	 * @param Entity $entity
	 * @return Entity
	 */
	public function insert(Entity $entity): Entity {
		parent::insert($entity);
		$this->cachedFolders[$entity->getId()] = $entity;
		$this->invalidateCache($entity->getUserId(), $entity->getId());
		return $entity;
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 * @param $folderId
	 * @param $newChildrenOrder
	 * @return void
	 * @throws ChildrenOrderValidationError
	 */
	public function setChildren(int $folderId, array $newChildrenOrder): void {
		try {
			$folder = $this->find($folderId);
		} catch (DoesNotExistException $e) {
			throw new ChildrenOrderValidationError('Folder not found');
		} catch (MultipleObjectsReturnedException $e) {
			throw new ChildrenOrderValidationError('Multiple folders found');
		}
		$existingChildren = $this->getChildren($folderId);
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
			->select('folder_id', 'share_id')
			->from('bookmarks_shares', 's')
			->innerJoin('s', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 's.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId)))
			->where($qb->expr()->eq('t.type', self::TYPE_SHARE))
			->orderBy('t.index', 'ASC');
		$childShares = $qb->execute()->fetchAll();

		$foldersToShares = array_reduce($childShares, static function ($dict, $shareRec) {
			$dict[$shareRec['folder_id']] = $shareRec['share_id'];
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
		$this->invalidateCache($folder->getUserId(), $folderId);
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 * @param $folderId
	 * @param int $layers The amount of levels to return
	 * @return array the children each in the format ["id" => int, "type" => 'bookmark' | 'folder' ]
	 */
	public function getChildren($folderId, $layers = 1): array {
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
				$item['children'] = $this->getChildren($item['id'], $layers - 1);
			}
			return $item;
		}, $children);
		return $children;
	}

	/**
	 * @param $userId
	 * @param $folderId
	 * @return string
	 */
	private function getCacheKey(string $userId, int $folderId) : string {
		return 'folder:' . $userId . ',' . $folderId;
	}

	/**
	 * @param string $userId
	 * @param int $folderId
	 */
	public function invalidateCache(string $userId, int $folderId) {
		$key = $this->getCacheKey($userId, $folderId);
		$this->cache->remove($key);
		if ($folderId === -1) {
			return;
		}

		// Invalidate parent
		try {
			$folder = $this->find($folderId);
			$parentFolder = $this->findParentOfFolder($folderId);
			$this->invalidateCache($userId, $parentFolder->getId());
		} catch (DoesNotExistException $e) {
			return;
		} catch (MultipleObjectsReturnedException $e) {
			return;
		}

		if ($folder->getUserId() !== $userId) {
			return;
		}

		// invalidate shared folders
		$sharedFolders = $this->sharedFolderMapper->findByFolder($folderId);
		foreach ($sharedFolders as $sharedFolder) {
			$this->invalidateCache($sharedFolder->getUserId(), $folderId);
		}

	}

	/**
	 * @param int $folderId
	 * @param array $fields
	 * @param string $userId
	 * @return string
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function hashFolder(string $userId, int $folderId, $fields = ['title', 'url']) : string {
		$key = $this->getCacheKey($userId, $folderId);
		$hash = $this->cache->get($key);
		$selector = implode(',', $fields);
		if (isset($hash[$selector])) {
			return $hash[$selector];
		}
		if (!isset($hash)) {
			$hash = [];
		}

		$entity = $this->find($folderId);
		$children = $this->getChildren($folderId);
		$childHashes = array_map(function ($item) use ($fields, $entity) {
			switch ($item['type']) {
				case self::TYPE_BOOKMARK:
					return $this->bookmarkMapper->hash($item['id'], $fields);
				case self::TYPE_FOLDER:
					return $this->hashFolder($entity->getUserId(), $item['id'], $fields);
				default:
					throw new UnexpectedValueException('Expected bookmark or folder, but not ' . $item['type']);
			}
		}, $children);
		$folder = [];
		if ($entity->getUserId() !== $userId) {
			$folder['title'] = $this->sharedFolderMapper->findByFolderAndUser($folderId, $userId)->getTitle();
		} else if ($entity->getTitle() !== null) {
			$folder['title'] = $entity->getTitle();
		}
		$folder['children'] = $childHashes;
		$hash[$selector] = hash('sha256', json_encode($folder, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

		$this->cache->set($key, $hash, 60 * 60 * 24);
		return $hash[$selector];
	}

	/**
	 * @param int $root
	 * @param int $layers
	 * @return array
	 */
	public function getSubFolders($root, $layers = 0) : array {
		$folders = array_map(function (Folder $folder) use ($layers) {
			$array = $folder->toArray();
			if ($layers - 1 !== 0) {
				$array['children'] = $this->getSubFolders($folder->getId(), $layers - 1);
			}
			return $array;
		}, $this->findChildFolders($root));
		$shares = array_map(function (SharedFolder $folder) use ($layers) {
			$share = $this->shareMapper->find($folder->getShareId());
			$array = $folder->toArray();
			$array['id'] = $share->getFolderId();
			$array['userId'] = $share->getUserId();
			if ($layers - 1 !== 0) {
				$array['children'] = $this->getSubFolders($share->getFolderId(), $layers - 1);
			}
			return $array;
		}, $this->sharedFolderMapper->findByParentFolder($root));
		if (count($shares) > 0) {
			array_push($folders, ...$shares);
		}
		return $folders;
	}

	/**
	 * @param int $folderId
	 * @param int $newParentFolderId
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function move(int $folderId, int $newParentFolderId) {
		$folder = $this->find($folderId);
		try {
			$currentParent = $this->findParentOfFolder($folderId);
		}catch(DoesNotExistException $e) {
			$currentParent = null;
		}
		$newParent = $this->find($newParentFolderId);
		if (isset($currentParent)) {
			$this->invalidateCache($folder->getUserId(), $currentParent->getId());
		}
		$this->invalidateCache($folder->getUserId(), $newParent->getId());
		if ($folder->getUserId() !== $newParent->getUserId()) {
			throw new UnsupportedOperation('Cannot move between user trees');
		}

		$qb = $this->db->getQueryBuilder();
		$qb
			->update('bookmarks_tree')
			->values([
				'parent_folder' => $qb->createPositionalParameter($newParentFolderId),
			])
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($folderId)))
			->andWhere($qb->expr()->eq('type', self::TYPE_FOLDER));
		$qb->execute();
	}


	/**
	 * @brief Add a bookmark to a set of folders
	 * @param int $bookmarkId The bookmark reference
	 * @param array $folders Set of folders ids to add the bookmark to
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function setToFolders(int $bookmarkId, array $folders) {
		if (0 === count($folders)) {
			return;
		}

		$currentFolders = $this->findParentsOfBookmark($bookmarkId);
		$this->addToFolders($bookmarkId, $folders);
		$this->removeFromFolders($bookmarkId, array_map(static function (Folder $f) {
			return $f->getId();
		}, array_filter($currentFolders, static function (Folder $folder) use ($folders) {
			return !in_array($folder->getId(), $folders, true);
		})));
	}

	/**
	 * @brief Add a bookmark to a set of folders
	 * @param int $bookmarkId The bookmark reference
	 * @param array $folders Set of folders ids to add the bookmark to
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function addToFolders(int $bookmarkId, array $folders) {
		$this->bookmarkMapper->find($bookmarkId);
		$currentFolders = array_map(static function (Folder $f) {
			return $f->getId();
		}, $this->findParentsOfBookmark($bookmarkId));

		$folders = array_filter($folders, static function ($folderId) use ($currentFolders) {
			return !in_array($folderId, $currentFolders, true);
		});

		foreach ($folders as $folderId) {
			// check if folder exists
			$folder = $this->find($folderId);

			$qb = $this->db->getQueryBuilder();
			$qb
				->insert('bookmarks_tree')
				->values([
					'parent_folder' => $qb->createNamedParameter($folderId),
					'type' => self::TYPE_BOOKMARK,
					'id' => $qb->createNamedParameter($bookmarkId),
					'index' => $this->countChildren($folderId),
				]);
			$qb->execute();

			$this->invalidateCache($folder->getUserId(), $folderId);
		}
	}

	/**
	 * @brief Remove a bookmark from a set of folders
	 * @param int $bookmarkId The bookmark reference
	 * @param array $folders Set of folders ids to add the bookmark to
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function removeFromFolders(int $bookmarkId, array $folders) {
		$bm = $this->bookmarkMapper->find($bookmarkId);

		$foldersLeft = count($this->findParentsOfBookmark($bookmarkId));

		foreach ($folders as $folder) {
			$folderId = $folder->getId();
			$qb = $this->db->getQueryBuilder();
			$qb
				->delete('bookmarks_tree')
				->where($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)))
				->andWhere($qb->expr()->eq('id', $qb->createPositionalParameter($bookmarkId)))
				->andWhere($qb->expr()->eq('t.type', self::TYPE_BOOKMARK));
			$qb->execute();

			$this->invalidateCache($folder->getUserId(), $folderId);

			$foldersLeft--;
		}
		if ($foldersLeft <= 0) {
			$this->bookmarkMapper->delete($bm);
		}
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
	 * Handle onBookmark{Create,Update,Delete} events
	 *
	 * @param Event $event
	 */
	public function handle(Event $event): void {
		$bookmark = $event->getSubject();
		$folders = $this->findParentsOfBookmark($bookmark->getId());
		foreach($folders as $folder) {
			$this->invalidateCache($folder->getUserId(), $folder->getId());
		}
	}
}
