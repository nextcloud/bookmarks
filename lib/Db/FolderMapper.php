<?php
namespace OCA\Bookmarks\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;

class FolderMapper extends QBMapper {

	const TYPE_BOOKMARK = 'bookmark';
	const TYPE_FOLDER = 'folder';

	public function __construct(IDBConnection $db, BookmarkMapper $bookmarkMapper) {
		parent::__construct($db, 'bookmarks_folders');
		$this->bookmarkMapper = $bookmarkMapper;
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 */
	public function find(int $id) : Bookmark {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id', 'parent_folder', 'title', 'user_id')
			->from('bookmarks_folders')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		return $this->findEntity($qb);
	}

	public function findByParentFolder(int $folderId) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id', 'title', 'parent_folder')
			->from('bookmarks_folders')
			->where($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folderId)))
			->orderBy('title', 'DESC');
		return $this->findEntities($qb);
	}

	public function findByRootFolder(int $userId) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id', 'title', 'parent_folder')
			->from('bookmarks_folders')
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)))
			->andWhere($qb->expr()->eq('parent_folder', $qb->createPositionalParameter(-1)))
			->orderBy('title', 'DESC');
		return $this->findEntities($qb);
	}

	public function delete(Folder $entity) {
		$childFolders = $this->findByParentFolder($entity->id);
		foreach ($childFolders as $folder) {
			$this->delete($folder);
		}
		$childBookmarks = $this->bookmarkMapper->findByFolder($entity->id)
		foreach ($childBookmarks as $bookmark) {
			$this->bookmarkMapper->delete($bookmark);
		}
		parent::delete($entity);
	}

	public function deleteAll(int $userId) {
		$childFolders = $this->findByRootFolder($userId);
		foreach ($childFolders as $folder) {
			$this->delete($folder);
		}
		$childBookmarks = $this->bookmarkMapper->findByRootFolder($userId)
		foreach ($childBookmarks as $bookmark) {
			$this->bookmarkMapper->delete($bookmark);
		}
		parent::delete($entity);
	}

	public function update(Folder $entity) {
		if ($entity->parentFolder !== -1) {
			$this->find($entity->parentFolder)
		}
		parent::insertOrUpdate($entity);
	}

	public function insertOrUpdate(Folder $entity) {
		if ($entity->parentFolder !== -1) {
			$this->find($entity->parentFolder)
		}
		$newEntity = parent::insertOrUpdate($entity);
	}

	public function insert(Folder $entity) {
		if ($entity->parentFolder !== -1) {
			$this->find($entity->parentFolder)
		}
		return parent::insert($entity);
	}


	/**
	 * @brief Lists bookmark folders' child folders (helper)
	 * @param int $root Root folder from which to return hierarchy, -1 for absolute root
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

	public function hashFolder(Folder $entity, $fields = ['title', 'url']) {
		$children = $this->getChildren($entity->id);
		$childHashes = array_map(function ($item) use ($userId, $fields) {
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
		if (isset($entity->title)) {
			$folder['title'] = $entity->title;
		}
		$folder['children'] = $childHashes;
		return hash('sha256', json_encode($folder, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}
}
