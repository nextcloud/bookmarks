<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Db;

use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PDO;

/**
 * Class TagMapper
 *
 * @package OCA\Bookmarks\Db
 */
class TagMapper {
	/**
	 * @var IDBConnection
	 */
	protected $db;

	private FolderMapper $folderMapper;

	private BookmarkMapper $bookmarkMapper;

	/**
	 * TagMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param FolderMapper $folderMapper
	 * @param BookmarkMapper $bookmarkMapper
	 */
	public function __construct(IDBConnection $db, FolderMapper $folderMapper, BookmarkMapper $bookmarkMapper) {
		$this->db = $db;
		$this->folderMapper = $folderMapper;
		$this->bookmarkMapper = $bookmarkMapper;
	}

	/**
	 * @param $userId
	 * @return array
	 * @throws Exception
	 */
	public function findAllWithCount($userId): array {
		$rootFolder = $this->folderMapper->findRootFolder($userId);
		[$cte, $cteParams, $cteParamTypes] = $this->bookmarkMapper->generateCTE($rootFolder->getId(), false);

		$qb = $this->db->getQueryBuilder();
		$qb->automaticTablePrefix(false);
		$qb
			->selectAlias('t.tag', 'name')
			->selectAlias($qb->createFunction('COUNT(DISTINCT ' . $qb->getColumnName('t.bookmark_id') . ')'), 'count')
			->from('*PREFIX*bookmarks_tags', 't')
			->innerJoin('t', 'folder_tree', 'tree', 'tree.item_id = t.bookmark_id AND tree.type = ' . $qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK) . ' AND tree.soft_deleted_at IS NULL')
			->groupBy('t.tag')
			->orderBy('count', 'DESC');

		$finalQuery = $cte . ' ' . $qb->getSQL();
		$params = array_merge($cteParams, $qb->getParameters());
		$paramTypes = array_merge($cteParamTypes, $qb->getParameterTypes());

		$cursor = $this->db->executeQuery($finalQuery, $params, $paramTypes);
		$rows = [];
		while ($row = $cursor->fetch()) {
			$rows[] = $row;
		}
		$cursor->closeCursor();
		return $rows;
	}

	/**
	 * @param $userId
	 * @return array
	 * @throws Exception
	 */
	public function findAll($userId): array {
		$rootFolder = $this->folderMapper->findRootFolder($userId);
		[$cte, $cteParams, $cteParamTypes] = $this->bookmarkMapper->generateCTE($rootFolder->getId(), false);

		$qb = $this->db->getQueryBuilder();
		$qb->automaticTablePrefix(false);
		$qb
			->select('t.tag')
			->from('*PREFIX*bookmarks_tags', 't')
			->innerJoin('t', 'folder_tree', 'tree', 'tree.item_id = t.bookmark_id AND tree.type = ' . $qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK) . ' AND tree.soft_deleted_at IS NULL')
			->groupBy('t.tag');

		$finalQuery = $cte . ' ' . $qb->getSQL();
		$params = array_merge($cteParams, $qb->getParameters());
		$paramTypes = array_merge($cteParamTypes, $qb->getParameterTypes());

		$cursor = $this->db->executeQuery($finalQuery, $params, $paramTypes);
		$tags = [];
		while ($row = $cursor->fetch()) {
			$tags[] = $row['tag'];
		}
		$cursor->closeCursor();
		return $tags;
	}

	/**
	 * @param int $bookmarkId
	 * @return array
	 * @throws Exception
	 */
	public function findByBookmark(int $bookmarkId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('tag');

		$qb
			->from('bookmarks_tags', 't')
			->where($qb->expr()->eq('t.bookmark_id', $qb->createPositionalParameter($bookmarkId, IQueryBuilder::PARAM_INT)));

		return $qb->executeQuery()->fetchAll(PDO::FETCH_COLUMN);
	}

	/**
	 * @param $userId
	 * @param string $tag
	 * @throws Exception
	 */
	public function delete($userId, string $tag) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_tags', 'tgs')
			->innerJoin('tgs', 'bookmarks', 'bm', $qb->expr()->eq('tgs.bookmark_id', 'bm.id'))
			->where($qb->expr()->eq('tgs.tag', $qb->createNamedParameter($tag)))
			->andWhere($qb->expr()->eq('bm.user_id', $qb->createNamedParameter($userId)));
		return $qb->executeStatement();
	}

	/**
	 * @param $userId
	 * @throws Exception
	 */
	public function deleteAll(int $userId) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_tags', 'tgs')
			->innerJoin('tgs', 'bookmarks', 'bm', $qb->expr()->eq('tgs.bookmark_id', 'bm.id'))
			->where($qb->expr()->eq('bm.user_id', $qb->createNamedParameter($userId)));
		return $qb->executeStatement();
	}

	/**
	 * @param $tags
	 * @param int $bookmarkId
	 * @throws Exception
	 */
	public function addTo(array $tags, int $bookmarkId): void {
		if (count($tags) === 0) {
			return;
		}
		$currentTags = $this->findByBookmark($bookmarkId);
		$tags = array_filter($tags, static function ($tag) use ($currentTags) {
			return !in_array($tag, $currentTags, true);
		});
		foreach ($tags as $tag) {
			$tag = trim($tag);
			if (empty($tag)) {
				//avoid saving white spaces
				continue;
			}

			$qb = $this->db->getQueryBuilder();
			$qb
				->insert('bookmarks_tags')
				->values([
					'tag' => $qb->createNamedParameter($tag),
					'bookmark_id' => $qb->createNamedParameter($bookmarkId),
				]);
			$qb->executeStatement();
		}
	}

	/**
	 * @param int $bookmarkId
	 * @throws Exception
	 */
	public function removeAllFrom(int $bookmarkId): void {
		// Remove old tags
		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_tags')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($bookmarkId)));
		$qb->executeStatement();
	}

	/**
	 * @param array $tags
	 * @param int $bookmarkId
	 */
	public function setOn(array $tags, int $bookmarkId): void {
		$this->removeAllFrom($bookmarkId);
		$this->addTo($tags, $bookmarkId);
	}

	/**
	 * @brief Rename a tag
	 * @param string $userId UserId
	 * @param string $old Old Tag Name
	 * @param string $new New Tag Name
	 * @throws Exception
	 */
	public function renameTag(string $userId, string $old, string $new): void {
		// Remove about-to-be duplicated tags
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('tgs.bookmark_id')
			->from('bookmarks_tags', 'tgs')
			->innerJoin('tgs', 'bookmarks', 'bm', $qb->expr()->eq('tgs.bookmark_id', 'bm.id'))
			->innerJoin('tgs', 'bookmarks_tags', 't', $qb->expr()->eq('tgs.bookmark_id', 't.bookmark_id'))
			->where($qb->expr()->eq('tgs.tag', $qb->createNamedParameter($new)))
			->andWhere($qb->expr()->eq('bm.user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('t.tag', $qb->createNamedParameter($old)));
		$duplicates = $qb->executeQuery()->fetchAll(PDO::FETCH_COLUMN);
		if (count($duplicates) !== 0) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->delete('bookmarks_tags')
				->where($qb->expr()->in('bookmark_id', array_map([$qb, 'createNamedParameter'], $duplicates)))
				->andWhere($qb->expr()->eq('tag', $qb->createNamedParameter($old)));
			$qb->executeStatement();
		}

		// Update tags to the new label
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('tgs.bookmark_id')
			->from('bookmarks_tags', 'tgs')
			->innerJoin('tgs', 'bookmarks', 'bm', $qb->expr()->eq('tgs.bookmark_id', 'bm.id'))
			->where($qb->expr()->eq('tgs.tag', $qb->createNamedParameter($old)))
			->andWhere($qb->expr()->eq('bm.user_id', $qb->createNamedParameter($userId)));
		$bookmarks = $qb->executeQuery()->fetchAll(PDO::FETCH_COLUMN);
		if (count($bookmarks) !== 0) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->update('bookmarks_tags')
				->set('tag', $qb->createNamedParameter($new))
				->where($qb->expr()->eq('tag', $qb->createNamedParameter($old)))
				->andWhere($qb->expr()->in('bookmark_id', array_map([$qb, 'createNamedParameter'], $bookmarks)));
			$qb->executeStatement();
		}
	}

	/**
	 * @param $userId
	 * @param string $old
	 * @throws Exception
	 */
	public function deleteTag($userId, string $old): void {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('t.bookmark_id')
			->from('bookmarks_tags', 't')
			->innerJoin('t', 'bookmarks', 'bm', $qb->expr()->eq('t.bookmark_id', 'bm.id'))
			->where($qb->expr()->eq('t.tag', $qb->createNamedParameter($old)))
			->andWhere($qb->expr()->eq('bm.user_id', $qb->createNamedParameter($userId)));
		$affectedBookmarks = $qb->executeQuery()->fetchAll(PDO::FETCH_COLUMN);
		if (count($affectedBookmarks) !== 0) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->delete('bookmarks_tags')
				->where($qb->expr()->in('bookmark_id', array_map([$qb, 'createNamedParameter'], $affectedBookmarks)))
				->andWhere($qb->expr()->eq('tag', $qb->createNamedParameter($old)));
			$qb->executeStatement();
		}
	}
}
