<?php
/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Db;

use OCA\Bookmarks\Events\BeforeDeleteEvent;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Bookmarks\QueryParameters;
use OCA\Bookmarks\Service\UrlNormalizer;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IQueryFunction;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IDBConnection;
use PDO;
use function call_user_func;

/**
 * Class BookmarkMapper
 *
 * @package OCA\Bookmarks\Db
 * @template-extends QBMapper<Bookmark>
 */
class BookmarkMapper extends QBMapper {
	/** @var IConfig */
	private $config;

	/** @var IEventDispatcher */
	private $eventDispatcher;

	/** @var UrlNormalizer */
	private $urlNormalizer;
	/**
	 * @var int
	 */
	private $limit;

	/**
	 * @var PublicFolderMapper
	 */
	private $publicMapper;

	/**
	 * @var IQueryBuilder
	 */
	private $deleteTagsQuery;
	/**
	 * @var IQueryBuilder
	 */
	private $findByUrlQuery;
	/**
	 * @var ITimeFactory
	 */
	private $time;
	/**
	 * @var FolderMapper
	 */
	private $folderMapper;
	/**
	 * @var ShareMapper
	 */
	private $shareMapper;

	/**
	 * BookmarkMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param IEventDispatcher $eventDispatcher
	 * @param UrlNormalizer $urlNormalizer
	 * @param IConfig $config
	 * @param PublicFolderMapper $publicMapper
	 * @param ITimeFactory $timeFactory
	 * @param FolderMapper $folderMapper
	 * @param ShareMapper $shareMapper
	 */
	public function __construct(IDBConnection $db, IEventDispatcher $eventDispatcher, UrlNormalizer $urlNormalizer, IConfig $config, PublicFolderMapper $publicMapper, ITimeFactory $timeFactory, \OCA\Bookmarks\Db\FolderMapper $folderMapper, \OCA\Bookmarks\Db\ShareMapper $shareMapper) {
		parent::__construct($db, 'bookmarks', Bookmark::class);
		$this->eventDispatcher = $eventDispatcher;
		$this->urlNormalizer = $urlNormalizer;
		$this->config = $config;
		$this->limit = (int)$config->getAppValue('bookmarks', 'performance.maxBookmarksperAccount', 0);
		$this->publicMapper = $publicMapper;

		$this->deleteTagsQuery = $this->getDeleteTagsQuery();
		$this->findByUrlQuery = $this->getFindByUrlQuery();
		$this->time = $timeFactory;
		$this->folderMapper = $folderMapper;
		$this->shareMapper = $shareMapper;
	}

	protected function getFindByUrlQuery(): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks')
			->where($qb->expr()->eq('user_id', $qb->createParameter('user_id')))
			->andWhere($qb->expr()->eq('url', $qb->createParameter('url')));
		return $qb;
	}

	protected function getDeleteTagsQuery(): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_tags')
			->where($qb->expr()->eq('bookmark_id', $qb->createParameter('id')));
		return $qb;
	}

	/**
	 * @param $userId
	 * @param $url
	 * @return Bookmark
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByUrl($userId, $url): Bookmark {
		$qb = $this->findByUrlQuery;
		$qb->setParameters([
			'user_id' => $userId,
			'url' => $url
		]);
		return $this->findEntity($qb);
	}

	/**
	 * @param string $userId
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function deleteAll(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('b.id')
			->from('bookmarks', 'b')
			->where($qb->expr()->eq('b.user_id', $qb->createPositionalParameter($userId)));
		$orphanedBookmarks = $qb->execute();
		while ($bookmark = $orphanedBookmarks->fetchColumn()) {
			$bm = $this->find($bookmark);
			$this->delete($bm);
		}
	}

	/**
	 * Magic to use BookmarkWithTags if possible
	 * @param array $row
	 * @return Bookmark
	 */
	protected function mapRowToEntity(array $row): Bookmark {
		$hasTags = false;
		foreach (array_keys($row) as $field) {
			if (preg_match('#.*tag|folder.*#i', $field, $matches) === 1) { // 1 means it matches, 0 means it doesn't.
				$hasTags = true;
				break;
			}
		}
		if ($hasTags !== false) {
			return BookmarkWithTagsAndParent::fromRow($row);
		}
		return call_user_func($this->entityClass .'::fromRow', $row);
	}


	/**
	 * Find a specific bookmark by Id
	 *
	 * @param int $id
	 * @return Bookmark
	 * @throws DoesNotExistException if not found
	 * @throws MultipleObjectsReturnedException if more than one result
	 */
	public function find(int $id): Bookmark {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select(Bookmark::$columns)
			->from('bookmarks')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		return $this->findEntity($qb);
	}

	/**
	 * @param string $userId
	 * @param QueryParameters $queryParams
	 *
	 * @return Bookmark[]
	 *
	 * @throws UrlParseError
	 * @throws \OC\DB\Exceptions\DbalException
	 * @throws Exception
	 */
	public function findAll(string $userId, QueryParameters $queryParams, bool $withGroupBy = true): array {
		$rootFolder = $this->folderMapper->findRootFolder($userId);
		// gives us all bookmarks in this folder, recursively
		[$cte, $params, $paramTypes] = $this->_generateCTE($rootFolder->getId(), $queryParams->getSoftDeleted());

		$qb = $this->db->getQueryBuilder();
		$bookmark_cols = array_map(static function ($c) {
			return 'b.' . $c;
		}, Bookmark::$columns);

		$qb->select($bookmark_cols);
		$qb->groupBy($bookmark_cols);

		if ($withGroupBy) {
			$this->_selectFolders($qb);
			$this->_selectTags($qb);
		}
		$qb->automaticTablePrefix(false);

		$qb
			->from('*PREFIX*bookmarks', 'b')
			->join('b', 'folder_tree', 'tree', 'tree.item_id = b.id AND tree.type = '.$qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK));

		$this->_filterUrl($qb, $queryParams);
		$this->_filterArchived($qb, $queryParams);
		$this->_filterUnavailable($qb, $queryParams);
		$this->_filterDuplicated($qb, $queryParams);
		$this->_filterFolder($qb, $queryParams);
		$this->_filterTags($qb, $queryParams);
		$this->_filterUntagged($qb, $queryParams);
		$this->_filterSearch($qb, $queryParams);
		$this->_sortAndPaginate($qb, $queryParams);

		$finalQuery = $cte . ' ' . $qb->getSQL();

		$params = array_merge($params, $qb->getParameters());
		$paramTypes = array_merge($paramTypes, $qb->getParameterTypes());
		return $this->findEntitiesWithRawQuery($finalQuery, $params, $paramTypes);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	protected function findEntitiesWithRawQuery(string $query, array $params, array $types) {
		$cursor = $this->db->executeQuery($query, $params, $types);

		$entities = [];

		while ($row = $cursor->fetch()) {
			$entities[] = $this->mapRowToEntity($row);
		}

		$cursor->closeCursor();

		return $entities;
	}

	/**
	 * Common table expression that lists all items in a given folder, recursively
	 * @param int $folderId
	 * @return array
	 */
	private function _generateCTE(int $folderId, bool $withSoftDeleted = false) : array {
		// The base case of the recursion is just the folder we're given
		$baseCase = $this->db->getQueryBuilder();
		$baseCase
			->selectAlias($baseCase->createFunction($this->getDbType() === 'mysql'? 'cast('.$baseCase->createPositionalParameter($folderId, IQueryBuilder::PARAM_INT).' as UNSIGNED)' : 'cast('.$baseCase->createPositionalParameter($folderId, IQueryBuilder::PARAM_INT).' as BIGINT)'), 'item_id')
			->selectAlias($baseCase->createFunction($this->getDbType() === 'mysql'? 'cast(0 as UNSIGNED)' : 'cast(0 as BIGINT)'), 'parent_folder')
			->selectAlias($baseCase->createFunction($this->getDbType() === 'mysql'? 'cast('.$baseCase->createPositionalParameter(TreeMapper::TYPE_FOLDER).' as CHAR(20))' : 'cast('.$baseCase->createPositionalParameter(TreeMapper::TYPE_FOLDER).' as TEXT)'), 'type')
			->selectAlias($baseCase->createFunction($this->getDbType() === 'mysql'? 'cast(0 as UNSIGNED)' : 'cast(0 as BIGINT)'), 'idx');

		// The first recursive case lists all children of folders we've already found
		$recursiveCase = $this->db->getQueryBuilder();
		$recursiveCase->automaticTablePrefix(false);
		$recursiveCase
			->selectAlias('tr.id', 'item_id')
			->selectAlias('tr.parent_folder', 'parent_folder')
			->selectAlias('tr.type', 'type')
			->selectAlias('tr.index', 'idx')
			->from('*PREFIX*bookmarks_tree', 'tr')
			->join('tr', $this->getDbType() === 'mysql'? 'folder_tree' : 'inner_folder_tree', 'e', 'e.item_id = tr.parent_folder AND e.type = '.$recursiveCase->createPositionalParameter(TreeMapper::TYPE_FOLDER) . (!$withSoftDeleted ? ' AND e.soft_deleted_at is NULL' : ''));

		// The second recursive case lists all children of shared folders we've already found
		$recursiveCaseShares = $this->db->getQueryBuilder();
		$recursiveCaseShares->automaticTablePrefix(false);
		$recursiveCaseShares
			->selectAlias('s.folder_id', 'item_id')
			->addSelect('e.parent_folder')
			->selectAlias($recursiveCaseShares->createFunction($recursiveCaseShares->createPositionalParameter(TreeMapper::TYPE_FOLDER)), 'type')
			->selectAlias('e.idx', 'idx')
			->from(($this->getDbType() === 'mysql'? 'folder_tree' : 'second_folder_tree'), 'e')
			->join('e', '*PREFIX*bookmarks_shared_folders', 's', 's.id = e.item_id AND e.type = '.$recursiveCaseShares->createPositionalParameter(TreeMapper::TYPE_SHARE) . (!$withSoftDeleted ? ' AND e.soft_deleted_at is NULL' : ''));

		if ($this->getDbType() === 'mysql') {
			// For mysql we can just throw these three queries together in a CTE
			$withRecursiveQuery = 'WITH RECURSIVE folder_tree(item_id, parent_folder, type, idx) AS ( ' .
				$baseCase->getSQL() . ' UNION ALL ' . $recursiveCase->getSQL() .
				' UNION ALL ' . $recursiveCaseShares->getSQL() . ')';
		} else {
			// Postgres loves us dearly and doesn't allow two recursive references in one CTE, aaah.
			// So we nest them:

			$secondBaseCase = $this->db->getQueryBuilder();
			$secondBaseCase->automaticTablePrefix(false);
			$secondBaseCase
				->select('item_id', 'parent_folder', 'type', 'idx')
				->from('inner_folder_tree');

			$thirdBaseCase = $this->db->getQueryBuilder();
			$thirdBaseCase->automaticTablePrefix(false);
			$thirdBaseCase
				->select('item_id', 'parent_folder', 'type', 'idx')
				->from('second_folder_tree');

			$secondRecursiveCase = $this->db->getQueryBuilder();
			$secondRecursiveCase->automaticTablePrefix(false);
			$secondRecursiveCase
				->selectAlias('tr.id', 'item_id')
				->selectAlias('tr.parent_folder', 'parent_folder')
				->selectAlias('tr.type', 'type')
				->selectAlias('tr.index', 'idx')
				->from('*PREFIX*bookmarks_tree', 'tr')
				->join('tr', 'folder_tree', 'e', 'e.item_id = tr.parent_folder AND e.type = '.$secondRecursiveCase->createPositionalParameter(TreeMapper::TYPE_FOLDER) . (!$withSoftDeleted ? ' AND e.soft_deleted_at is NULL' : ''));

			// First the base case together with the normal recurisve case
			// Then the second helper base case together with the recursive shares case
			// then we need another instance of the first recursive case, duplicated here as secondRecursive case
			// to recurse into child folders of shared folders
			// Note: This doesn't cover cases where a shared folder is inside a shared folder.
			$withRecursiveQuery = 'WITH RECURSIVE folder_tree(item_id, parent_folder, type, idx) AS ( ' .
				'WITH RECURSIVE second_folder_tree(item_id, parent_folder, type, idx) AS (' .
				'WITH RECURSIVE inner_folder_tree(item_id, parent_folder, type, idx) AS ( ' .
				$baseCase->getSQL() . ' UNION ALL ' . $recursiveCase->getSQL() . ')' .
				' ' . $secondBaseCase->getSQL() . ' UNION ALL '. $recursiveCaseShares->getSQL() .')'.
				' ' . $thirdBaseCase->getSQL() . ' UNION ALL ' .  $secondRecursiveCase->getSQL(). ')';
		}

		// Now we need to concatenate the params of all these queries for downstream assembly of the greater query
		if ($this->getDbType() === 'mysql') {
			$params = array_merge($baseCase->getParameters(), $recursiveCase->getParameters(), $recursiveCaseShares->getParameters());
			$paramTypes = array_merge($baseCase->getParameterTypes(), $recursiveCase->getParameterTypes(), $recursiveCaseShares->getParameterTypes());
		} else {
			$params = array_merge($baseCase->getParameters(), $recursiveCase->getParameters(), $secondBaseCase->getParameters(), $recursiveCaseShares->getParameters(), $thirdBaseCase->getParameters(), $secondRecursiveCase->getParameters());
			$paramTypes = array_merge($baseCase->getParameterTypes(), $recursiveCase->getParameterTypes(), $secondBaseCase->getParameterTypes(), $recursiveCaseShares->getParameterTypes(), $thirdBaseCase->getParameterTypes(), $secondRecursiveCase->getParameterTypes());
		}

		return [$withRecursiveQuery, $params, $paramTypes];
	}

	private function _sortAndPaginate(IQueryBuilder $qb, QueryParameters $params): void {
		$sqlSortColumn = $params->getSortBy('lastmodified', $this->getSortByColumns());

		if ($sqlSortColumn === 'title') {
			$qb->addOrderBy($qb->createFunction('UPPER(`b`.`title`)'), 'ASC');
		} elseif ($sqlSortColumn === 'index') {
			$qb->addOrderBy('tree.idx', 'ASC');
			$qb->addGroupBy('tree.idx');
		} elseif ($sqlSortColumn === 'url') {
			$qb->addOrderBy('b.url', 'ASC');
		} else {
			$qb->addOrderBy('b.'.$sqlSortColumn, 'DESC');
		}
		// Always sort by id additionally, so the ordering is stable
		$qb->addOrderBy('b.id', 'ASC');
		$qb->addGroupBy('b.id');

		if ($params->getLimit() !== -1) {
			$qb->setMaxResults($params->getLimit());
		}
		if ($params->getOffset() !== 0) {
			$qb->setFirstResult($params->getOffset());
		}
	}

	/**
	 * @throws UrlParseError
	 */
	private function _filterUrl(IQueryBuilder $qb, QueryParameters $params): void {
		if (($url = $params->getUrl()) !== null) {
			$normalized = $this->urlNormalizer->normalize($url);
			$qb->andWhere($qb->expr()->eq('b.url', $qb->createPositionalParameter($normalized)));
		}
	}

	/**
	 * @param IQueryBuilder $qb
	 * @param QueryParameters $params
	 */
	private function _filterSearch(IQueryBuilder $qb, QueryParameters $params): void {
		$connectWord = 'AND';
		if ($params->getConjunction() === 'or') {
			$connectWord = 'OR';
		}

		$filters = $params->getSearch();

		if (count($filters) === 0) {
			return;
		}

		$tagsCol = $this->_getTagsColumn($qb);
		$filterExpressions = [];
		$otherColumns = ['b.url', 'b.title', 'b.description', 'b.text_content'];
		foreach ($filters as $filter) {
			$expr = [];
			$expr[] = $qb->expr()->iLike($tagsCol, $qb->createPositionalParameter('%' . $this->db->escapeLikeParameter($filter) . '%'));
			foreach ($otherColumns as $col) {
				$expr[] = $qb->expr()->iLike(
					$qb->createFunction($qb->getColumnName($col)),
					$qb->createPositionalParameter('%' . $this->db->escapeLikeParameter(strtolower($filter)) . '%')
				);
			}
			$filterExpressions[] = call_user_func_array([$qb->expr(), 'orX'], $expr);
		}
		if ($connectWord === 'AND') {
			$filterExpression = call_user_func_array([$qb->expr(), 'andX'], $filterExpressions);
		} else {
			$filterExpression = call_user_func_array([$qb->expr(), 'orX'], $filterExpressions);
		}
		$qb->andHaving($filterExpression);
	}

	/**
	 * @param IQueryBuilder $qb
	 * @param QueryParameters $params
	 */
	private function _filterArchived(IQueryBuilder $qb, QueryParameters $params): void {
		if ($params->getArchived()) {
			$qb->andWhere($qb->expr()->isNotNull('b.archived_file'));
		}
	}

	/**
	 * @param IQueryBuilder $qb
	 * @param QueryParameters $params
	 */
	private function _filterUnavailable(IQueryBuilder $qb, QueryParameters $params): void {
		if ($params->getUnavailable()) {
			$qb->andWhere($qb->expr()->eq('b.available', $qb->createPositionalParameter(false, IQueryBuilder::PARAM_BOOL)));
		}
	}

	/**
	 * @param IQueryBuilder $qb
	 * @param QueryParameters $params
	 * @return void
	 */
	private function _filterDuplicated(IQueryBuilder $qb, QueryParameters $params) {
		if ($params->getDuplicated()) {
			$subQuery = $this->db->getQueryBuilder();
			$subQuery->select('trdup.parent_folder')
			->from('*PREFIX*bookmarks_tree', 'trdup')
				->where($subQuery->expr()->eq('b.id', 'trdup.id'))
				->andWhere($subQuery->expr()->neq('trdup.parent_folder', 'tree.parent_folder'))
				->andWhere($subQuery->expr()->eq('trdup.type', $qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK)));
			$qb->andWhere($qb->createFunction('EXISTS('.$subQuery->getSQL().')'));
		}
	}

	/**
	 * @param IQueryBuilder $qb
	 * @param QueryParameters $params
	 */
	private function _filterFolder(IQueryBuilder $qb, QueryParameters $params): void {
		if ($params->getFolder() !== null) {
			if ($params->getRecursive()) {
				$childFolders = \OC::$server->get(TreeMapper::class)->findByAncestorFolder(TreeMapper::TYPE_FOLDER, $params->getFolder());
				$ids = array_map(fn (Folder $folder) => $folder->getId(), $childFolders);
				$qb->andWhere($qb->expr()->in('tree.parent_folder', $qb->createPositionalParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
			} else {
				$qb->andWhere($qb->expr()->eq('tree.parent_folder', $qb->createPositionalParameter($params->getFolder(), IQueryBuilder::PARAM_INT)));
			}
		}
	}

	/**
	 * @param IQueryBuilder $qb
	 */
	private function _selectTags(IQueryBuilder $qb): void {
		$qb->leftJoin('b', '*PREFIX*bookmarks_tags', 't', $qb->expr()->eq('t.bookmark_id', 'b.id'));
		$qb->selectAlias($this->_getTagsColumn($qb), 'tags');
	}

	/**
	 * @param IQueryBuilder $qb
	 * @param QueryParameters $params
	 */
	private function _filterUntagged(IQueryBuilder $qb, QueryParameters $params): void {
		if ($params->getUntagged()) {
			$qb->andWhere($qb->expr()->isNull('t.bookmark_id'));
		}
	}

	/**
	 * @param IQueryBuilder $qb
	 * @param QueryParameters $params
	 */
	private function _filterTags(IQueryBuilder $qb, QueryParameters $params): void {
		if (count($params->getTags())) {
			foreach ($params->getTags() as $i => $tag) {
				$qb->leftJoin('b', '*PREFIX*bookmarks_tags', 'tg'.$i, $qb->expr()->eq('tg'.$i.'.bookmark_id', 'b.id'));
				$qb->andWhere($qb->expr()->eq('tg'.$i.'.tag', $qb->createPositionalParameter($tag)));
			}
		}
	}

	/**
	 * @param string $userId
	 * @return int
	 */
	public function countArchived(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->count('b.id'), 'count');

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tree', 'tr', 'b.id = tr.id AND tr.type = '.$qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK))
			->leftJoin('tr', 'bookmarks_shared_folders', 'sf', $qb->expr()->eq('tr.parent_folder', 'sf.folder_id'))
			->where(
				$qb->expr()->andX(
					$qb->expr()->orX(
						$qb->expr()->eq('b.user_id', $qb->createPositionalParameter($userId)),
						$qb->expr()->eq('sf.user_id', $qb->createPositionalParameter($userId))
					),
					$qb->expr()->in('b.user_id', array_map([$qb, 'createPositionalParameter'], array_merge($this->_findSharersFor($userId), [$userId])))
				)
			)
			->andWhere($qb->expr()->isNotNull('b.archived_file'));

		return $qb->execute()->fetch(PDO::FETCH_COLUMN);
	}

	/**
	 * @param string $userId
	 * @return int
	 */
	public function countUnavailable(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->count('b.id'), 'count');

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tree', 'tr', 'b.id = tr.id AND tr.type = '.$qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK))
			->leftJoin('tr', 'bookmarks_shared_folders', 'sf', $qb->expr()->eq('tr.parent_folder', 'sf.folder_id'))
			->where(
				$qb->expr()->andX(
					$qb->expr()->orX(
						$qb->expr()->eq('b.user_id', $qb->createPositionalParameter($userId)),
						$qb->expr()->eq('sf.user_id', $qb->createPositionalParameter($userId))
					),
					$qb->expr()->in('b.user_id', array_map([$qb, 'createPositionalParameter'], array_merge($this->_findSharersFor($userId), [$userId])))
				)
			)
			->andWhere($qb->expr()->eq('b.available', $qb->createPositionalParameter(false, IQueryBuilder::PARAM_BOOL)));

		return $qb->execute()->fetch(PDO::FETCH_COLUMN);
	}

	/**
	 * @param string $userId
	 * @return int
	 */
	public function countDuplicated(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct($qb->func()->count('b.id'));

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tree', 'tr', 'b.id = tr.id AND tr.type = '.$qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK))
			->leftJoin('tr', 'bookmarks_shared_folders', 'sf', $qb->expr()->eq('tr.parent_folder', 'sf.folder_id'))
			->where(
				$qb->expr()->andX(
					$qb->expr()->orX(
						$qb->expr()->eq('b.user_id', $qb->createPositionalParameter($userId)),
						$qb->expr()->eq('sf.user_id', $qb->createPositionalParameter($userId))
					),
					$qb->expr()->in('b.user_id', array_map([$qb, 'createPositionalParameter'], array_merge($this->_findSharersFor($userId), [$userId])))
				)
			);
		$subQuery = $this->db->getQueryBuilder();
		$subQuery->select('trdup.parent_folder')
			->from('bookmarks_tree', 'trdup')
			->where($subQuery->expr()->eq('b.id', 'trdup.id'))
			->andWhere($subQuery->expr()->neq('trdup.parent_folder', 'tr.parent_folder'))
			->andWhere($subQuery->expr()->eq('trdup.type', $qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK)));
		$qb->andWhere($qb->createFunction('EXISTS('.$subQuery->getSQL().')'));

		return $qb->execute()->fetch(PDO::FETCH_COLUMN);
	}

	/**
	 * @param string $token
	 * @param QueryParameters $queryParams
	 *
	 *
	 * @return Bookmark[]
	 *
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 *
	 * @psalm-return array<array-key, Bookmark>
	 */
	public function findAllInPublicFolder(string $token, QueryParameters $queryParams, $withGroupBy = true): array {
		/** @var PublicFolder $publicFolder */
		$publicFolder = $this->publicMapper->find($token);
		/** @var Folder $folder */
		$folder = $this->folderMapper->find($publicFolder->getFolderId());

		// gives us all bookmarks in this folder, recursively
		[$cte, $params, $paramTypes] = $this->_generateCTE($folder->getId(), false);

		$qb = $this->db->getQueryBuilder();
		$qb->automaticTablePrefix(false);
		$bookmark_cols = array_map(static function ($c) {
			return 'b.' . $c;
		}, Bookmark::$columns);

		$qb->select($bookmark_cols);
		$qb->groupBy($bookmark_cols);

		if ($withGroupBy) {
			$this->_selectFolders($qb);
			$this->_selectTags($qb);
		}

		$qb
			->from('*PREFIX*bookmarks', 'b')
			->join('b', 'folder_tree', 'tree', 'tree.item_id = b.id AND tree.type = '.$qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK));


		$this->_filterUrl($qb, $queryParams);
		$this->_filterArchived($qb, $queryParams);
		$this->_filterUnavailable($qb, $queryParams);
		$this->_filterDuplicated($qb, $queryParams);
		$this->_filterFolder($qb, $queryParams);
		$this->_filterTags($qb, $queryParams);
		$this->_filterUntagged($qb, $queryParams);
		$this->_filterSearch($qb, $queryParams);
		$this->_sortAndPaginate($qb, $queryParams);

		$finalQuery = $cte . ' '. $qb->getSQL();
		$params = array_merge($params, $qb->getParameters());
		$paramTypes = array_merge($paramTypes, $qb->getParameterTypes());

		return $this->findEntitiesWithRawQuery($finalQuery, $params, $paramTypes);
	}

	/**
	 * @param int $limit
	 * @param int $stalePeriod
	 *
	 * @return Bookmark[]
	 *
	 * @psalm-return array<array-key, Bookmark>
	 */
	public function findPendingPreviews(int $limit, int $stalePeriod): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Bookmark::$columns);
		$qb->from('bookmarks', 'b');
		$qb->where($qb->expr()->lt('last_preview', $qb->createPositionalParameter($this->time->getTime() - $stalePeriod)));
		$qb->orWhere($qb->expr()->isNull('last_preview'));
		$qb->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/**
	 * @psalm-param Bookmark $entity
	 * @param Entity $entity
	 *
	 * @return Bookmark
	 * @psalm-return Bookmark
	 */
	public function delete(Entity $entity): Bookmark {
		$this->eventDispatcher->dispatch(
			BeforeDeleteEvent::class,
			new BeforeDeleteEvent(TreeMapper::TYPE_BOOKMARK, $entity->getId())
		);

		$returnedEntity = parent::delete($entity);

		$id = $entity->getId();

		$qb = $this->deleteTagsQuery;
		$qb->setParameter('id', $id);
		$qb->execute();

		return $returnedEntity;
	}

	/**
	 * @psalm-param Bookmark $entity
	 * @param Entity $entity
	 * @return Bookmark
	 * @throws UrlParseError
	 */
	public function update(Entity $entity): Bookmark {
		// normalize url
		if ($entity->isWebLink()) {
			$entity->setUrl($this->urlNormalizer->normalize($entity->getUrl()));
		}
		$entity->setLastmodified(time());
		return parent::update($entity);
	}

	/**
	 * @psalm-param Bookmark $entity
	 * @param Entity $entity
	 * @return Bookmark
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function insert(Entity $entity): Bookmark {
		// Enforce user limit
		if ($this->limit > 0 && $this->limit <= $this->countBookmarksOfUser($entity->getUserId())) {
			throw new UserLimitExceededError('Exceeded user limit of ' . $this->limit . ' bookmarks');
		}

		// normalize url
		if ($entity->isWebLink()) {
			$entity->setUrl($this->urlNormalizer->normalize($entity->getUrl()));
		}

		if ($entity->getAdded() === null) {
			$entity->setAdded(time());
		}
		$entity->setLastmodified(time());
		$entity->setLastPreview(0);
		$entity->setClickcount(0);

		try {
			$this->findByUrl($entity->getUserId(), $entity->getUrl());
		} catch (DoesNotExistException $e) {
			parent::insert($entity);
			return $entity;
		} catch (MultipleObjectsReturnedException $e) {
			// noop
		}

		throw new AlreadyExistsError('A bookmark with this URL already exists');
	}

	/**
	 * @psalm-param Bookmark $entity
	 * @param Entity $entity
	 * @return Bookmark
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError|MultipleObjectsReturnedException
	 */
	public function insertOrUpdate(Entity $entity): Bookmark {
		try {
			$newEntity = $this->insert($entity);
		} catch (AlreadyExistsError $e) {
			$bookmark = $this->findByUrl($entity->getUserId(), $entity->getUrl());
			$entity->setId($bookmark->getId());
			$newEntity = $this->update($entity);
		}

		return $newEntity;
	}

	/**
	 * @param $userId
	 * @param string $userId
	 *
	 * @return int
	 */
	public function countBookmarksOfUser(string $userId) : int {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select($qb->func()->count('id'))
			->from('bookmarks')
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)));
		return $qb->execute()->fetch(PDO::FETCH_COLUMN);
	}

	/**
	 * Returns the list of possible sort by columns.
	 *
	 * @return string[]
	 */
	private function getSortByColumns(): array {
		$treeFields = [
			'index',
		];
		return array_merge(Bookmark::$columns, $treeFields);
	}

	/**
	 * @return string
	 */
	private function getDbType(): string {
		return $this->config->getSystemValue('dbtype', 'sqlite');
	}

	/**
	 * @param IQueryBuilder $qb
	 */
	private function _selectFolders(IQueryBuilder $qb): void {
		$qb->leftJoin('b', '*PREFIX*bookmarks_tree', 'tr2', 'b.id = tr2.id AND tr2.type = '.$qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK));
		if ($this->getDbType() === 'pgsql') {
			$folders = $qb->createFunction('array_to_string(array_agg(' . $qb->getColumnName('tr2.parent_folder') . "), ',')");
		} else {
			$folders = $qb->createFunction('GROUP_CONCAT(' . $qb->getColumnName('tr2.parent_folder') . ')');
		}
		$qb->selectAlias($folders, 'folders');
	}

	/**
	 * @param IQueryBuilder $qb
	 * @return IQueryFunction
	 */
	private function _getTagsColumn(IQueryBuilder $qb) : IQueryFunction {
		$dbType = $this->getDbType();
		if ($dbType === 'pgsql') {
			$tagsCol = $qb->createFunction('array_to_string(array_agg(' . $qb->getColumnName('t.tag') . "), ',')");
		} else {
			$tagsCol = $qb->createFunction('IFNULL(GROUP_CONCAT(' . $qb->getColumnName('t.tag') . '), "")');
		}
		return $tagsCol;
	}

	/**
	 * @param string $userId
	 * @return string[]
	 */
	private function _findSharersFor(string $userId) :array {
		return array_map(static function (Share $share) {
			return  $share->getOwner();
		}, $this->shareMapper->findByUser($userId));
	}
}
