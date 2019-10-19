<?php
namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\Entity;

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
	 * FolderMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param BookmarkMapper $bookmarkMapper
	 */
	public function __construct(IDBConnection $db, BookmarkMapper $bookmarkMapper) {
		parent::__construct($db, 'bookmarks_folders');
		$this->bookmarkMapper = $bookmarkMapper;
	}

	/**
	 * @param int $id
	 * @return Folder
	 * @throws DoesNotExistException if not found
	 * @throws MultipleObjectsReturnedException if more than one result
	 */
	public function find(int $id) : Entity {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks_folders')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		return $this->findEntity($qb);
	}

	/**
	 * @param int $folderId
	 * @return array|Entity[]
	 */
	public function findByParentFolder(int $folderId)  {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks_folders')
			->where($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)))
			->orderBy('title', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * @param int $userId
	 * @return array|Entity[]
	 */
	public function findByRootFolder(int $userId)  {
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
	 * @param Entity $entity
	 * @return Entity
	 */
	public function delete(Entity $entity) : Entity {
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
	 * @param int $userId
	 */
	public function deleteAll(int $userId) {
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
	public function update(Entity $entity) : Entity {
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
	public function insertOrUpdate(Entity $entity) : Entity {
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
	public function insert(Entity $entity) : Entity {
		if ($entity->getParentFolder() !== -1) {
			$this->find($entity->getParentFolder());
		}
		return parent::insert($entity);
	}

	/**
	 * @param int $bookmarkId
	 * @return array|Entity[]
	 */
	public function findByBookmark(int $bookmarkId) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*');

		$qb
			->from('bookmarks_folders', 'f')
			->leftJoin('f', 'bookmarks_folders_bookmarks', 'b', $qb->expr()->eq('b.bookmark_id', 'f.id'))
			->where($qb->expr()->eq('b.bookmark_id', $qb->createPositionalParameter($bookmarkId)));

		return $this->findEntities($qb);
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 * @param $folderId
	 * @param int $layers The amount of levels to return
	 * @return array the children each in the format ["id" => int, "type" => 'bookmark' | 'folder' ]
	 */
	public function getChildren($folderId, $layers=1) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id', 'title', 'parent_folder', 'index')
			->from('bookmarks_folders')
			->andWhere($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)))
			->orderBy('index', 'ASC');
		$childFolders = $qb->execute()->fetchAll();


		$qb = $this->db->getQueryBuilder();
		$qb
			->select('bookmark_id', 'index')
			->from('bookmarks_folders_bookmarks', 'f')
			->innerJoin('f', 'bookmarks', 'b', $qb->expr()->eq('b.id', 'f.bookmark_id'))
			->where($qb->expr()->eq('folder_id', $qb->createPositionalParameter($folderId)))
			->orderBy('index', 'ASC');
		$childBookmarks = $qb->execute()->fetchAll();

		$children = array_merge($childFolders, $childBookmarks);
		array_multisort(array_column($children, 'index'), \SORT_ASC, $children);
		$children = array_map(function ($child) use ($layers) {
			return isset($child['bookmark_id'])
			  ? ['type' =>  self::TYPE_BOOKMARK, 'id' => $child['bookmark_id']]
			  : ($layers === 1
				  ? ['type' => self::TYPE_FOLDER, 'id' => $child['id']]
					: [
						 'type' => self::TYPE_FOLDER,
					   'id' => $child['id'],
						 'children' => $this->getChildren($child['id'], $layers-1)
					 ]);
		}, $children);

		return $children;
	}

	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 * @param int $userId
	 * @param int $layers The amount of levels to return
	 * @return array the children each in the format ["id" => int, "type" => 'bookmark' | 'folder' ]
	 */
	public function getRootChildren(int $userId, $layers = 1) {
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
			->select('bookmark_id', 'index')
			->from('bookmarks_folders_bookmarks', 'f')
			->innerJoin('f', 'bookmarks', 'b', $qb->expr()->eq('b.id', 'f.bookmark_id'))
			->where($qb->expr()->eq('folder_id', $qb->createPositionalParameter(-1)))
			->andWhere($qb->expr()->eq('b.user_id', $qb->createPositionalParameter($userId)))
			->orderBy('index', 'ASC');
		$childBookmarks = $qb->execute()->fetchAll();

		$children = array_merge($childFolders, $childBookmarks);
		array_multisort(array_column($children, 'index'), \SORT_ASC, $children);
		$children = array_map(function ($child) use ($layers) {
			return isset($child['bookmark_id'])
				? ['type' =>  self::TYPE_BOOKMARK, 'id' => $child['bookmark_id']]
				: ($layers === 1
					? ['type' => self::TYPE_FOLDER, 'id' => $child['id']]
					: [
						'type' => self::TYPE_FOLDER,
						'id' => $child['id'],
						'children' => $this->getChildren($child['id'], $layers-1)
					]);
		}, $children);

		return $children;
	}

	/**
	 * @param Folder $entity
	 * @param array $fields
	 * @return string
	 */
	public function hashFolder(Folder $entity, $fields = ['title', 'url']) {
		$children = $this->getChildren($entity->id);
		$childHashes = array_map(function ($item) use ($fields) {
			switch ($item['type']) {
				case self::TYPE_BOOKMARK:
				  return $this->bookmarkMapper->hashBookmark($item['id'], $fields);
				case self::TYPE_FOLDER:
				  return $this->hashFolder($item['id'], $fields);
			  default:
				  throw new UnexpectedValueException('Expected bookmark or folder, but not '.$item['type']);
			}
		}, $children);
		$folder = [];
		if ($entity->getTitle() !== null) {
			$folder['title'] = $entity->getTitle();
		}
		$folder['children'] = $childHashes;
		return hash('sha256', json_encode($folder, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	/**
	 * @brief Add a bookmark to a set of folders
	 * @param int $bookmarkId The bookmark reference
	 * @param array $folders Set of folders ids to add the bookmark to
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function addToFolders(int $bookmarkId, array $folders) {
		$bookmark = $this->bookmarkMapper->find($bookmarkId);
		foreach ($folders as $folderId) {
			// check if folder exists
			if ($folderId !== -1 && $folderId !== '-1') {
				$this->find($folderId);
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
					'index' => $folderId !== -1 ? count($this->getChildren($folderId)) : count($this->getRootChildren($bookmark->getUserId()))
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
