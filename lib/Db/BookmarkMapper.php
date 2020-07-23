<?php

namespace OCA\Bookmarks\Db;

use OCA\Bookmarks\Events\BeforeDeleteEvent;
use OCA\Bookmarks\Events\CreateEvent;
use OCA\Bookmarks\Events\UpdateEvent;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Bookmarks\QueryParameters;
use OCA\Bookmarks\Service\UrlNormalizer;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IDBConnection;

/**
 * Class BookmarkMapper
 *
 * @package OCA\Bookmarks\Db
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
	 * @var TagMapper
	 */
	private $tagMapper;

	/**
	 * BookmarkMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param IEventDispatcher $eventDispatcher
	 * @param UrlNormalizer $urlNormalizer
	 * @param IConfig $config
	 * @param PublicFolderMapper $publicMapper
	 * @param TagMapper $tagMapper
	 */
	public function __construct(IDBConnection $db, IEventDispatcher $eventDispatcher, UrlNormalizer $urlNormalizer, IConfig $config, PublicFolderMapper $publicMapper, TagMapper $tagMapper) {
		parent::__construct($db, 'bookmarks', Bookmark::class);
		$this->eventDispatcher = $eventDispatcher;
		$this->urlNormalizer = $urlNormalizer;
		$this->config = $config;
		$this->limit = (int)$config->getAppValue('bookmarks', 'performance.maxBookmarksperAccount', 0);
		$this->publicMapper = $publicMapper;
		$this->tagMapper = $tagMapper;
	}


	/**
	 * Find a specific bookmark by Id
	 *
	 * @param int $id
	 * @return Entity
	 * @throws DoesNotExistException if not found
	 * @throws MultipleObjectsReturnedException if more than one result
	 */
	public function find(int $id): Entity {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select(Bookmark::$columns)
			->from('bookmarks')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		return $this->findEntity($qb);
	}

	/**
	 * @param $userId
	 * @param string $url
	 * @return Entity
	 * @throws DoesNotExistException if not found
	 * @throws MultipleObjectsReturnedException if more than one result
	 * @throws UrlParseError
	 */
	public function findByUrl($userId, string $url): Entity {
		$normalized = $this->urlNormalizer->normalize($url);
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('url', $qb->createNamedParameter($normalized)));

		return $this->findEntity($qb);
	}

	/**
	 * @param $userId
	 * @param array $filters
	 * @param QueryParameters $params
	 * @return array|Entity[]
	 */
	public function findAll($userId, array $filters, QueryParameters $params): array {
		$qb = $this->db->getQueryBuilder();
		$bookmark_cols = array_map(static function ($c) {
			return 'b.' . $c;
		}, Bookmark::$columns);

		$qb->select($bookmark_cols);
		$qb->groupBy($bookmark_cols);

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tags', 't', $qb->expr()->eq('t.bookmark_id', 'b.id'))
			->leftJoin('b', 'bookmarks_tree', 'tr', $qb->expr()->eq('tr.id', 'b.id'))
			->leftJoin('tr', 'bookmarks_shared_folders', 'sf', $qb->expr()->eq('tr.parent_folder', 'sf.folder_id'))
			->where($qb->expr()->eq('b.user_id', $qb->createPositionalParameter($userId)))
			->orWhere($qb->expr()->eq('sf.user_id', $qb->createPositionalParameter($userId)));

		$this->_findBookmarksBuildFilter($qb, $filters, $params);
		$this->_queryBuilderSortAndPaginate($qb, $params);

		return $this->findEntities($qb);
	}

	/**
	 * @param $userId
	 * @return int
	 */
	public function countAll($userId): int {
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->func()->count('b.id'));

		// Finds bookmarks in 2-levels nested shares only
		$qb
			->from('bookmarks', 'b')
			->where($qb->expr()->eq('b.user_id', $qb->createPositionalParameter($userId)));

		$count = $qb->execute()->fetch(\PDO::FETCH_COLUMN)[0];

		return (int)$count;
	}

	private function _queryBuilderSortAndPaginate(IQueryBuilder $qb, QueryParameters $params): void {
		$sqlSortColumn = $params->getSortBy('lastmodified', Bookmark::$columns);

		if ($sqlSortColumn === 'title') {
			$qb->addOrderBy($qb->createFunction('UPPER(`b`.`title`)'), 'ASC');
		} else {
			$qb->addOrderBy('b.'.$sqlSortColumn, 'DESC');
		}
		// Always sort by id additionally, so the ordering is stable
		$qb->addOrderBy('b.id', 'ASC');

		if ($params->getLimit() !== -1) {
			$qb->setMaxResults($params->getLimit());
		}
		if ($params->getOffset() !== 0) {
			$qb->setFirstResult($params->getOffset());
		}
	}

	/**
	 * @param IQueryBuilder $qb
	 * @param array $filters
	 * @param QueryParameters $params
	 */
	private function _findBookmarksBuildFilter(&$qb, $filters, QueryParameters $params): void {
		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');
		$connectWord = 'AND';
		if ($params->getConjunction() === 'or') {
			$connectWord = 'OR';
		}
		if (count($filters) === 0) {
			return;
		}
		if ($dbType === 'pgsql') {
			$tags = $qb->createFunction('array_to_string(array_agg(' . $qb->getColumnName('t.tag') . "), ',')");
		} else {
			$tags = $qb->createFunction('GROUP_CONCAT(' . $qb->getColumnName('t.tag') . ')');
		}
		$filterExpressions = [];
		$otherColumns = ['b.url', 'b.title', 'b.description'];
		foreach ($filters as $filter) {
			$expr = [];
			$expr[] = $qb->expr()->iLike($tags, $qb->createPositionalParameter('%' . $this->db->escapeLikeParameter($filter) . '%'));
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
		$qb->having($filterExpression);
	}

	/**
	 * @param int $folderId
	 * @param QueryParameters $params
	 * @return array|Entity[]
	 */
	public function findByFolder(int $folderId, QueryParameters $params): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function ($col) {
			return 'b.' . $col;
		}, Bookmark::$columns));

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'b.id'))
			->where($qb->expr()->eq('t.parent_folder', $qb->createPositionalParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK)));

		$this->_queryBuilderSortAndPaginate($qb, $params);

		return $this->findEntities($qb);
	}

	/**
	 * @param string $userId
	 * @param string $tag
	 * @param QueryParameters $params
	 * @return array|Entity[]
	 */
	public function findByTag($userId, string $tag, QueryParameters $params): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function($col) {
			return 'b.'.$col;
		}, Bookmark::$columns));

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tags', 't', $qb->expr()->eq('t.bookmark_id', 'b.id'))
			->leftJoin('b', 'bookmarks_tree', 'tr', $qb->expr()->eq('tr.id', 'b.id'))
			->leftJoin('tr', 'bookmarks_shared_folders', 'sf', $qb->expr()->eq('tr.parent_folder', 'sf.folder_id'))
			->where($qb->expr()->orX(
				$qb->expr()->eq('b.user_id', $qb->createPositionalParameter($userId)),
				$qb->expr()->eq('sf.user_id', $qb->createPositionalParameter($userId))
			))
			->andWhere($qb->expr()->eq('t.tag', $qb->createPositionalParameter($tag)));

		$this->_queryBuilderSortAndPaginate($qb, $params);

		return $this->findEntities($qb);
	}

	private function _findByTags($userId): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb->select(array_map(static function($col) {
			return 'b.'.$col;
		}, Bookmark::$columns));

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tags', 't', $qb->expr()->eq('t.bookmark_id', 'b.id'))
			->leftJoin('b', 'bookmarks_tree', 'tr', $qb->expr()->eq('tr.id', 'b.id'))
			->leftJoin('tr', 'bookmarks_shared_folders', 'sf', $qb->expr()->eq('tr.parent_folder', 'sf.folder_id'))
			->where($qb->expr()->orX(
				$qb->expr()->eq('b.user_id', $qb->createPositionalParameter($userId)),
				$qb->expr()->eq('sf.user_id', $qb->createPositionalParameter($userId))
			));

		return $qb;
	}

	/**
	 * @param string $userId
	 * @param array $tags
	 * @param QueryParameters $params
	 * @return array|Entity[]
	 */
	public function findByTags($userId, array $tags, QueryParameters $params): array {
		$qb = $this->_findByTags($userId);

		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');
		if ($dbType === 'pgsql') {
			$tagsCol = $qb->createFunction('array_to_string(array_agg(' . $qb->getColumnName('t.tag') . "), ',')");
		} else {
			$tagsCol = $qb->createFunction('GROUP_CONCAT(' . $qb->getColumnName('t.tag') . ')');
		}

		$expr = [];
		foreach ($tags as $tag) {
			$expr[] = $qb->expr()->iLike($tagsCol, $qb->createPositionalParameter('%' . $this->db->escapeLikeParameter($tag) . '%'));
		}
		$filterExpression = call_user_func_array([$qb->expr(), 'andX'], $expr);
		$qb->groupBy(...array_map(static function($col) {
			return 'b.'.$col;
		}, Bookmark::$columns));
		$qb->having($filterExpression);

		$this->_queryBuilderSortAndPaginate($qb, $params);

		return $this->findEntities($qb);
	}

	/**
	 * @param $userId
	 * @param QueryParameters $params
	 * @return array|Entity[]
	 */
	public function findUntagged($userId, QueryParameters $params): array {
		// select b.id from oc_bookmarks b LEFT JOIN oc_bookmarks_tags t ON b.id = t.bookmark_id WHERE t.bookmark_id IS NULL
		$qb = $this->_findByTags($userId);
		$qb->andWhere($qb->expr()->isNull('t.bookmark_id'));

		$this->_queryBuilderSortAndPaginate($qb, $params);
		return $this->findEntities($qb);
	}

	/**
	 *
	 * @param $token
	 * @param array $filters
	 * @param QueryParameters $params
	 * @return array|Entity[]
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findAllInPublicFolder($token, array $filters, QueryParameters $params): array {
		$publicFolder = $this->publicMapper->find($token);

		$bookmarks = $this->findByFolder($publicFolder->getFolderId(), $params);
		// Really inefficient, but what can you do.
		return array_filter($bookmarks, function (Bookmark $bookmark) use ($filters, $params) {
			$tagsFound = $this->tagMapper->findByBookmark($bookmark->getId());
			return array_reduce($filters, static function ($isMatch, $filter) use ($bookmark, $tagsFound, $params) {
				$filter = strtolower($filter);

				$res = in_array($filter, $tagsFound, true)
					|| str_contains($filter, strtolower($bookmark->getTitle()))
					|| str_contains($filter, strtolower($bookmark->getDescription()))
					|| str_contains($filter, strtolower($bookmark->getUrl()));
				return $params->getConjunction() === 'and' ? $res && $isMatch : $res || $isMatch;
			}, $params->getConjunction() === 'and');
		});
	}

	/**
	 *
	 * @param $token
	 * @param array $tags
	 * @param QueryParameters $params
	 * @return array|Entity[]
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByTagsInPublicFolder($token, array $tags, QueryParameters $params): array {
		$publicFolder = $this->publicMapper->find($token);

		$bookmarks = $this->findByFolder($publicFolder->getFolderId(), $params);
		// Really inefficient, but what can you do.
		return array_filter($bookmarks, function (Bookmark $bookmark) use ($tags) {
			$tagsFound = $this->tagMapper->findByBookmark($bookmark->getId());
			return array_reduce($tags, static function ($isFound, $tag) use ($tagsFound) {
				return in_array($tag, $tagsFound, true) && $isFound;
			}, true);
		});
	}

	/**
	 *
	 * @param $token
	 * @param QueryParameters $params
	 * @return array|Entity[]
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findUntaggedInPublicFolder($token, QueryParameters $params): array {
		$publicFolder = $this->publicMapper->find($token);

		$bookmarks = $this->findByFolder($publicFolder->getFolderId(), $params);
		// Really inefficient, but what can you do.
		return array_filter($bookmarks, function (Bookmark $bookmark) {
			$tags = $this->tagMapper->findByBookmark($bookmark->getId());
			return count($tags) === 0;
		});
	}

	/**
	 * @param int $limit
	 * @param int $stalePeriod
	 * @return array|Entity[]
	 */
	public function findPendingPreviews(int $limit, int $stalePeriod): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Bookmark::$columns);
		$qb->from('bookmarks', 'b');
		$qb->where($qb->expr()->lt('last_preview', $qb->createPositionalParameter(time() - $stalePeriod)));
		$qb->orWhere($qb->expr()->isNull('last_preview'));
		$qb->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/**
	 * @param Entity $entity
	 * @return Entity|void
	 */
	public function delete(Entity $entity): Entity {
		$this->eventDispatcher->dispatch(
			BeforeDeleteEvent::class,
			new BeforeDeleteEvent(TreeMapper::TYPE_BOOKMARK, $entity->getId())
		);

		$returnedEntity = parent::delete($entity);

		$id = $entity->getId();

		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_tags')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($id)));
		$qb->execute();

		return $returnedEntity;
	}

	/**
	 * @param Entity $entity
	 * @return Entity
	 * @throws UrlParseError
	 */
	public function update(Entity $entity): Entity {
		// normalize url
		$entity->setUrl($this->urlNormalizer->normalize($entity->getUrl()));
		$entity->setLastmodified(time());
		return parent::update($entity);
	}

	/**
	 * @param Entity $entity
	 * @return Entity
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function insert(Entity $entity): Entity {
		// Enforce user limit
		if ($this->limit > 0 && $this->limit <= $this->countAll($entity->getUserId())) {
			throw new UserLimitExceededError('Exceeded user limit of ' . $this->limit . ' bookmarks');
		}

		// normalize url
		$entity->setUrl($this->urlNormalizer->normalize($entity->getUrl()));

		if ($entity->getAdded() === null) {
			$entity->setAdded(time());
		}
		$entity->setLastmodified(time());
		$entity->setLastPreview(0);
		$entity->setClickcount(0);

		$exists = true;
		try {

			$this->findByUrl($entity->getUserId(), $entity->getUrl());
		} catch (DoesNotExistException $e) {
			$exists = false;
		} catch (MultipleObjectsReturnedException $e) {
			$exists = true;
		}

		if ($exists) {
			throw new AlreadyExistsError('A bookmark with this URL already exists');
		}

		parent::insert($entity);
		return $entity;
	}

	/**
	 * @param Entity $entity
	 * @return Entity
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws AlreadyExistsError
	 */
	public function insertOrUpdate(Entity $entity): Entity {
		$exists = true;
		try {
			$existing = $this->findByUrl($entity->getUserId(), $entity->getUrl());
			$entity->setId($existing->getId());
		} catch (DoesNotExistException $e) {
			// This bookmark doesn't already exist. That's ok.
			$exists = false;
		}

		if ($exists) {
			$newEntity = $this->update($entity);
		} else {
			$newEntity = $this->insert($entity);
		}

		return $newEntity;
	}

	/**
	 * @param $userId
	 * @return int
	 */
	public function countBookmarksOfUser($userId) : int {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select($qb->func()->count('id'))
			->from('bookmarks')
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)));
		return $qb->execute()->fetch(\PDO::FETCH_COLUMN);
	}

}
