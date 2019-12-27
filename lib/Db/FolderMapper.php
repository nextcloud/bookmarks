<?php

namespace OCA\Bookmarks\Db;

use OCA\Bookmarks\Exception\ChildrenOrderValidationError;
use OCA\Bookmarks\Exception\UnauthorizedAccessError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\Entity;
use UnexpectedValueException;

/**
 * Class FolderMapper
 *
 * @package OCA\Bookmarks\Db
 */
class FolderMapper extends QBMapper {

	const TYPE_BOOKMARK = 'bookmark';
	const TYPE_FOLDER = 'folder';

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
	 * FolderMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param BookmarkMapper $bookmarkMapper
	 * @param ShareMapper $shareMapper
	 * @param SharedFolderMapper $sharedFolderMapper
	 */
	public function __construct(IDBConnection $db, BookmarkMapper $bookmarkMapper, ShareMapper $shareMapper, SharedFolderMapper $sharedFolderMapper) {
		parent::__construct($db, 'bookmarks_folders', Folder::class);
		$this->bookmarkMapper = $bookmarkMapper;
		$this->shareMapper = $shareMapper;
		$this->sharedFolderMapper = $sharedFolderMapper;
	}

	/**
	 * @param int $id
	 * @return Folder
	 * @throws DoesNotExistException if not found
	 * @throws MultipleObjectsReturnedException if more than one result
	 */
	public function find(int $id): Entity {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks_folders')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		return $this->findEntity($qb);
	}

	/**
	 * @param $userId
	 * @param int $folderId
	 * @return array|Entity[]
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnauthorizedAccessError
	 */
	public function findByUserFolder($userId, int $folderId) {
		if ($folderId !== -1) {
			if ($this->find($folderId) !== $userId) {
				throw new UnauthorizedAccessError();
			}
			return $this->findByParentFolder($folderId);
		} else {
			return $this->findByRootFolder($userId);
		}
	}

	/**
	 * @param int $folderId
	 * @return array|Entity[]
	 */
	public function findByParentFolder(int $folderId) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks_folders')
			->where($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)))
			->orderBy('title', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * @param $userId
	 * @return array|Entity[]
	 */
	public function findByRootFolder($userId) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks_folders')
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)))
			->andWhere($qb->expr()->eq('parent_folder', $qb->createPositionalParameter(-1)))
			->orderBy('title', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * @param $folderId
	 * @return array|Entity[]
	 */
	public function findByAncestorFolder($folderId) {
		$descendants = [];
		$newDescendants = $this->findByParentFolder($folderId);
		do {
			$newDescendants = array_flatten(array_map(function ($descendant) {
				return $this->findByParentFolder($descendant);
			}, $newDescendants));
			array_push($descendants, $newDescendants);
		} while (count($newDescendants) > 0);
		return $descendants;
	}

	/**
	 * @param $folderId
	 * @param $descendantFolderId
	 * @return bool
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function hasDescendantFolder($folderId, $descendantFolderId) {
		$descendant = $this->find($descendantFolderId);
		do {
			$descendant = $this->find($descendant->getParentFolder());
		} while ($descendant->getId() !== $folderId && $descendant->getParentFolder() !== -1);
		return ($descendant->getId() === $folderId);
	}

	/**
	 * @param int $bookmarkId
	 * @return array|Entity[]
	 */
	public function findByBookmark(int $bookmarkId) {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Folder::$columns);

		$qb
			->from('bookmarks_folders', 'f')
			->innerJoin('f', 'bookmarks_folders_bookmarks', 'b', $qb->expr()->eq('b.folder_id', 'f.id'))
			->where($qb->expr()->eq('b.bookmark_id', $qb->createPositionalParameter($bookmarkId)));

		$entities = $this->findEntities($qb);

		$qb = $this->db->getQueryBuilder();
		$qb->select('*');
		$qb
			->from('bookmarks_folders_bookmarks')
			->where($qb->expr()->eq('bookmark_id', $qb->createPositionalParameter($bookmarkId)))
			->andWhere($qb->expr()->eq('folder_id', $qb->createPositionalParameter(-1)));

		if ($qb->execute()->fetch()) {
			$root = new Folder();
			$root->setId(-1);
			array_push($entities, $root);
		}

		return $entities;
	}

	/**
	 * @param $folderId
	 * @param $descendantBookmarkId
	 * @return bool
	 */
	public function hasDescendantBookmark($folderId, $descendantBookmarkId) {
		$newAncestors = $this->findByBookmark($descendantBookmarkId);
		do {
			$newAncestors = array_map(function ($ancestor) {
				return $this->find($ancestor->getParentFolder());
			}, array_filter($newAncestors, function ($ancestor) {
				return $ancestor->getParentFolder() !== -1 && $ancestor->getId() !== -1;
			}));
			foreach ($newAncestors as $ancestor) {
				if ($ancestor->getId() === $folderId) {
					return true;
				}
			}
		} while (count($newAncestors) > 0);
		return false;
	}

	/**
	 * @param Entity $entity
	 * @return Entity
	 */
	public function delete(Entity $entity): Entity {
		$childFolders = $this->findByParentFolder($entity->id);
		foreach ($childFolders as $folder) {
			$this->delete($folder);
		}
		$childBookmarks = $this->bookmarkMapper->findByFolder($entity->id);
		foreach ($childBookmarks as $bookmark) {
			$this->bookmarkMapper->delete($bookmark);
		}
		return parent::delete($entity);
	}

	/**
	 * @param $userId
	 */
	public function deleteAll($userId) {
		$childFolders = $this->findByRootFolder($userId);
		foreach ($childFolders as $folder) {
			$this->delete($folder);
		}
		$childBookmarks = $this->bookmarkMapper->findByRootFolder($userId);
		foreach ($childBookmarks as $bookmark) {
			$this->bookmarkMapper->delete($bookmark);
		}
	}

	/**
	 * @param Entity $entity
	 * @return Entity
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function update(Entity $entity): Entity {
		if ($entity->getParentFolder() !== -1) {
			$this->find($entity->getParentFolder());
		}
		return parent::update($entity);
	}

	/**
	 * @param Entity $entity
	 * @return Entity
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function insertOrUpdate(Entity $entity): Entity {
		if ($entity->getParentFolder() !== -1) {
			$this->find($entity->getParentFolder());
		}
		return parent::insertOrUpdate($entity);
	}

	/**
	 * @param Entity $entity
	 * @return Entity
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function insert(Entity $entity): Entity {
		if ($entity->getParentFolder() !== -1) {
			$this->find($entity->getParentFolder());
		}
		return parent::insert($entity);
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 * @param $userId
	 * @param int $folderId
	 * @param array $newChildrenOrder
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws ChildrenOrderValidationError
	 */
	public function setUserFolderChildren($userId, int $folderId, array $newChildrenOrder) {
		if ($folderId !== -1) {
			$this->setChildren($folderId, $newChildrenOrder);
			return;
		} else {
			$this->setRootChildren($userId, $newChildrenOrder);
			return;
		}
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 * @param $folderId
	 * @param $newChildrenOrder
	 * @return void
	 * @throws ChildrenOrderValidationError
	 */
	public function setChildren(int $folderId, array $newChildrenOrder) {
		try {
			$folder = $this->find($folderId);
		} catch (DoesNotExistException $e) {
			throw new ChildrenOrderValidationError();
		} catch (MultipleObjectsReturnedException $e) {
			throw new ChildrenOrderValidationError();
		}
		$existingChildren = $this->getChildren($folderId);
		foreach ($existingChildren as $child) {
			if (!in_array($child, $newChildrenOrder)) {
				throw new ChildrenOrderValidationError();
			}
			if (!isset($child['id'], $child['type'])) {
				throw new ChildrenOrderValidationError();
			}
		}
		if (count($newChildrenOrder) !== count($existingChildren)) {
			throw new ChildrenOrderValidationError();
		}
		foreach ($newChildrenOrder as $i => $child) {
			switch ($child['type']) {
				case'bookmark':
					$qb = $this->db->getQueryBuilder();
					$qb
						->update('bookmarks_folders_bookmarks')
						->set('index', $qb->createPositionalParameter($i))
						->where($qb->expr()->eq('bookmark_id', $qb->createPositionalParameter($child['id'])))
						->andWhere($qb->expr()->eq('folder_id', $qb->createPositionalParameter($folderId)));
					$qb->execute();
					break;
				case 'folder':
					try {
						$childFolder = $this->find($child['id']);
					} catch (DoesNotExistException $e) {
						throw new ChildrenOrderValidationError();
					} catch (MultipleObjectsReturnedException $e) {
						throw new ChildrenOrderValidationError();
					}
					if ($childFolder->getUserId() !== $folder->getUserId()) {
						$qb = $this->db->getQueryBuilder();
						$qb
							->update('bookmarks_shared')
							->innerJoin('p', 'bookmarks_shares', 's', 's.id = p.share_id')
							->set('index', $qb->createPositionalParameter($i))
							->where($qb->expr()->eq('s.folder_id', $qb->createPositionalParameter($child['id'])))
							->andWhere($qb->expr()->eq('parent_folder', $qb->createPositionalParameter(-1)));
						$qb->execute();
						break;
					}
					$qb = $this->db->getQueryBuilder();
					$qb
						->update('bookmarks_folders')
						->set('index', $qb->createPositionalParameter($i))
						->where($qb->expr()->eq('id', $qb->createPositionalParameter($child['id'])))
						->andWhere($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)));
					$qb->execute();
					break;
			}
		}
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 * @param $userId
	 * @param array $newChildrenOrder
	 * @return void
	 * @throws ChildrenOrderValidationError
	 */
	public function setRootChildren($userId, array $newChildrenOrder) {
		$existingChildren = $this->getRootChildren($userId);
		foreach ($existingChildren as $child) {
			if (!in_array($child, $newChildrenOrder)) {
				throw new ChildrenOrderValidationError();
			}
			if (!isset($child['id'], $child['type'])) {
				throw new ChildrenOrderValidationError();
			}
		}
		if (count($newChildrenOrder) !== count($existingChildren)) {
			throw new ChildrenOrderValidationError();
		}
		foreach ($newChildrenOrder as $i => $child) {
			switch ($child['type']) {
				case'bookmark':
					$qb = $this->db->getQueryBuilder();
					$qb
						->update('bookmarks_folders_bookmarks')
						->set('index', $qb->createPositionalParameter($i))
						->where($qb->expr()->eq('bookmark_id', $qb->createPositionalParameter($child['id'])))
						->andWhere($qb->expr()->eq('folder_id', $qb->createPositionalParameter(-1)));
					$qb->execute();
					break;
				case 'folder':
					try {
						$folder = $this->find($child['id']);
					} catch (DoesNotExistException $e) {
						throw new ChildrenOrderValidationError();
					} catch (MultipleObjectsReturnedException $e) {
						throw new ChildrenOrderValidationError();
					}
					if ($folder->getUserId() !== $userId) {
						$qb = $this->db->getQueryBuilder();
						$qb
							->update('bookmarks_shared')
							->innerJoin('p', 'bookmarks_shares', 's', 's.id = p.share_id')
							->set('index', $qb->createPositionalParameter($i))
							->where($qb->expr()->eq('s.folder_id', $qb->createPositionalParameter($child['id'])))
							->andWhere($qb->expr()->eq('parent_folder', $qb->createPositionalParameter(-1)));
						$qb->execute();
						break;
					}
					$qb = $this->db->getQueryBuilder();
					$qb
						->update('bookmarks_folders')
						->set('index', $qb->createPositionalParameter($i))
						->where($qb->expr()->eq('id', $qb->createPositionalParameter($child['id'])))
						->andWhere($qb->expr()->eq('parent_folder', $qb->createPositionalParameter(-1)));
					$qb->execute();
					break;
			}
		}
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 * @param $userId
	 * @param int $folderId
	 * @param int $layers
	 * @return array
	 */
	public function getUserFolderChildren($userId, int $folderId, int $layers) {
		if ($folderId !== -1) {
			return $this->getChildren($folderId, $layers);
		} else {
			return $this->getRootChildren($userId, $layers);
		}
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 * @param $folderId
	 * @param int $layers The amount of levels to return
	 * @return array the children each in the format ["id" => int, "type" => 'bookmark' | 'folder' ]
	 */
	public function getChildren($folderId, $layers = 1) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id', 'title', 'parent_folder', 'index')
			->from('bookmarks_folders')
			->andWhere($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)))
			->orderBy('index', 'ASC');
		$childFolders = $qb->execute()->fetchAll();

		$qb = $this->db->getQueryBuilder();
		$qb
			->select('folder_id', 'index')
			->from('bookmarks_shares', 's')
			->innerJoin('s', 'bookmarks_shared', 'p', $qb->expr()->eq('s.id', 'p.share_id'))
			->where($qb->expr()->eq('p.parent_folder', $qb->createPositionalParameter($folderId)))
			->orderBy('index', 'ASC');
		$childShares = $qb->execute()->fetchAll();


		$qb = $this->db->getQueryBuilder();
		$qb
			->select('bookmark_id', 'index')
			->from('bookmarks_folders_bookmarks', 'f')
			->innerJoin('f', 'bookmarks', 'b', $qb->expr()->eq('b.id', 'f.bookmark_id'))
			->where($qb->expr()->eq('folder_id', $qb->createPositionalParameter($folderId)))
			->orderBy('index', 'ASC');
		$childBookmarks = $qb->execute()->fetchAll();


		return $this->_getChildren($childFolders, $childShares, $childBookmarks, $layers);
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 * @param $userId
	 * @param int $layers The amount of levels to return
	 * @return array the children each in the format ["id" => int, "type" => 'bookmark' | 'folder' ]
	 */
	public function getRootChildren($userId, $layers = 1) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id', 'title', 'parent_folder', 'index')
			->from('bookmarks_folders')
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)))
			->andWhere($qb->expr()->eq('parent_folder', $qb->createPositionalParameter(-1)))
			->orderBy('index', 'ASC');
		$childFolders = $qb->execute()->fetchAll();

		$qb = $this->db->getQueryBuilder();
		$qb
			->select('folder_id', 'index')
			->from('bookmarks_shares', 's')
			->innerJoin('s', 'bookmarks_shared', 'p', $qb->expr()->eq('s.id', 'p.share_id'))
			->where($qb->expr()->eq('p.parent_folder', $qb->createPositionalParameter(-1)))
			->andWhere($qb->expr()->eq('p.user_id', $qb->createPositionalParameter($userId)))
			->orderBy('index', 'ASC');
		$childShares = $qb->execute()->fetchAll();


		$qb = $this->db->getQueryBuilder();
		$qb
			->select('bookmark_id', 'index')
			->from('bookmarks_folders_bookmarks', 'f')
			->innerJoin('f', 'bookmarks', 'b', $qb->expr()->eq('b.id', 'f.bookmark_id'))
			->where($qb->expr()->eq('folder_id', $qb->createPositionalParameter(-1)))
			->andWhere($qb->expr()->eq('b.user_id', $qb->createPositionalParameter($userId)))
			->orderBy('index', 'ASC');
		$childBookmarks = $qb->execute()->fetchAll();

		return $this->_getChildren($childFolders, $childShares, $childBookmarks, $layers);
	}

	private function _getChildren($childFolders, $childShares, $childBookmarks, $layers): array {
		$children = array_merge($childFolders, $childShares, $childBookmarks);
		array_multisort(array_column($children, 'index'), \SORT_ASC, $children);
		$children = array_map(function ($child) use ($layers) {
			if (isset($child['bookmark_id'])) {
				return ['type' => self::TYPE_BOOKMARK, 'id' => $child['bookmark_id']];
			}else {
				$id = isset($child['id']) ? $child['id'] : $child['folder_id'];
				if($layers === 1) {
					return ['type' => self::TYPE_FOLDER, 'id' => $id];
				}else {
					return [
						'type' => self::TYPE_FOLDER,
						'id' => $id,
						'children' => $this->getChildren($id, $layers - 1),
					];
				}
			}
		}, $children);
		return $children;
	}

	/**
	 * @param int $folderId
	 * @param array $fields
	 * @param string $userId
	 * @return string
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function hashFolder(string $userId, int $folderId, $fields = ['title', 'url']) {
		$entity = $this->find($folderId);
		$children = $this->getChildren($folderId);
		$childHashes = array_map(function ($item) use ($fields, $entity) {
			switch ($item['type']) {
				case self::TYPE_BOOKMARK:
					return $this->bookmarkMapper->hashBookmark($item['id'], $fields);
				case self::TYPE_FOLDER:
					return $this->hashFolder($entity->getUserId(), $item['id'], $fields);
				default:
					throw new UnexpectedValueException('Expected bookmark or folder, but not ' . $item['type']);
			}
		}, $children);
		$folder = [];
		if ($entity->getUserId() !== $userId) {
			$folder['title'] =  $this->sharedFolderMapper->findByFolderAndUser($folderId, $userId)->getTitle();
		}else if ($entity->getTitle() !== null) {
			$folder['title'] = $entity->getTitle();
		}
		$folder['children'] = $childHashes;
		return hash('sha256', json_encode($folder, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	/**
	 * @param $userId
	 * @param array $fields
	 * @return string
	 */
	public function hashRootFolder($userId, $fields = ['title', 'url']) {
		$children = $this->getRootChildren($userId);
		$childHashes = array_map(function ($item) use ($fields, $userId) {
			switch ($item['type']) {
				case self::TYPE_BOOKMARK:
					return $this->bookmarkMapper->hash($item['id'], $fields);
				case self::TYPE_FOLDER:
					return $this->hashFolder($userId, $item['id'], $fields);
				default:
					throw new UnexpectedValueException('Expected bookmark or folder, but not ' . $item['type']);
			}
		}, $children);
		$folder = [];
		$folder['children'] = $childHashes;
		return hash('sha256', json_encode($folder, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	/**
	 * @param int $root
	 * @param int $layers
	 * @return array
	 */
	public function getSubFolders($root = -1, $layers = 0) {
		$folders = array_map(function (Folder $folder) use ($layers) {
			$array = $folder->toArray();
			if ($layers - 1 != 0) {
				$array['children'] = $this->getSubFolders($folder->getId(), $layers - 1);
			}
			return $array;
		}, $this->findByParentFolder($root));
		$shares = array_map(function (Share $folder) use ($layers) {
			$share = $this->shareMapper->find($folder->getShareId());
			$array = $folder->toArray();
			$array['id'] = $share->getFolderId();
			if ($layers - 1 != 0) {
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
	 * @brief Add a bookmark to a set of folders
	 * @param int $bookmarkId The bookmark reference
	 * @param array $folders Set of folders ids to add the bookmark to
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnauthorizedAccessError
	 */
	public function setToFolders(int $bookmarkId, array $folders) {
		if (0 === count($folders)) {
			return;
		}

		$currentFolders = $this->findByBookmark($bookmarkId);

		$this->addToFolders($bookmarkId, $folders);

		$this->removeFromFolders($bookmarkId, array_map(function ($f) {
			return $f->getId();
		}, array_filter($currentFolders, function ($folder) use ($folders) {
			return !in_array($folder->getId(), $folders);
		})));
	}

	/**
	 * @brief Add a bookmark to a set of folders
	 * @param int $bookmarkId The bookmark reference
	 * @param array $folders Set of folders ids to add the bookmark to
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnauthorizedAccessError
	 */
	public function addToFolders(int $bookmarkId, array $folders) {
		$bookmark = $this->bookmarkMapper->find($bookmarkId);
		foreach ($folders as $folderId) {
			// check if folder exists
			if ($folderId !== -1 && $folderId !== '-1') {
				$folder = $this->find($folderId);
				if ($folder->getUserId() !== $bookmark->getUserId()) {
					throw new UnauthorizedAccessError('Can only add bookmarks to folders of the same user');
				}
			}

			// check if this folder<->bookmark mapping already exists
			$qb = $this->db->getQueryBuilder();
			$qb
				->select('*')
				->from('bookmarks_folders_bookmarks')
				->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($bookmarkId)))
				->andWhere($qb->expr()->eq('folder_id', $qb->createNamedParameter($folderId)));

			if ($qb->execute()->fetch()) {
				continue;
			}

			$qb = $this->db->getQueryBuilder();
			$qb
				->insert('bookmarks_folders_bookmarks')
				->values([
					'folder_id' => $qb->createNamedParameter($folderId),
					'bookmark_id' => $qb->createNamedParameter($bookmarkId),
					'index' => $folderId !== -1 ? count($this->getChildren($folderId)) : count($this->getRootChildren($bookmark->getUserId())),
				]);
			$qb->execute();
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

		$foldersLeft = count($this->findByBookmark($bookmarkId));

		foreach ($folders as $folderId) {
			// check if folder exists
			if ($folderId !== -1 && $folderId !== '-1') {
				$this->find($folderId);
			}

			// check if this folder<->bookmark mapping exists
			$qb = $this->db->getQueryBuilder();
			$qb
				->select('*')
				->from('bookmarks_folders_bookmarks')
				->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($bookmarkId)))
				->andWhere($qb->expr()->eq('folder_id', $qb->createNamedParameter($folderId)));

			if (!$qb->execute()->fetch()) {
				continue;
			}

			$qb = $this->db->getQueryBuilder();
			$qb
				->delete('bookmarks_folders_bookmarks')
				->where($qb->expr()->eq('folder_id', $qb->createNamedParameter($folderId)))
				->andwhere($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($bookmarkId)));
			$qb->execute();

			$foldersLeft--;
		}
		if ($foldersLeft <= 0) {
			$this->bookmarkMapper->delete($bm);
		}
	}
}
