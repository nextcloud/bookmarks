<?php

namespace OCA\Bookmarks\Db;

use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Bookmarks\Service\UrlNormalizer;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class BookmarkMapper
 *
 * @package OCA\Bookmarks\Db
 */
class BookmarkMapper extends QBMapper {

	/** @var IConfig */
	private $config;

	/** @var EventDispatcherInterface */
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
	 * @param EventDispatcherInterface $eventDispatcher
	 * @param UrlNormalizer $urlNormalizer
	 * @param IConfig $config
	 * @param PublicFolderMapper $publicMapper
	 */
	public function __construct(IDBConnection $db, EventDispatcherInterface $eventDispatcher, UrlNormalizer $urlNormalizer, IConfig $config, PublicFolderMapper $publicMapper, TagMapper $tagMapper) {
		parent::__construct($db, 'bookmarks', Bookmark::class);
		$this->eventDispatcher = $eventDispatcher;
		$this->urlNormalizer = $urlNormalizer;
		$this->config = $config;
		$this->limit = intval($config->getAppValue('bookmarks', 'performance.maxBookmarksperAccount', 0));
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
			->select('*')
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
	 * @param string $conjunction
	 * @param string $sortBy
	 * @param int $offset
	 * @param int $limit
	 * @return array|Entity[]
	 */
	public function findAll($userId, array $filters, string $conjunction = 'and', string $sortBy = 'lastmodified', int $offset = 0, int $limit = -1) {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Bookmark::$columns);
		$qb->groupBy(Bookmark::$columns);

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tags', 't', $qb->expr()->eq('t.bookmark_id', 'b.id'))
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)));

		$this->_findBookmarksBuildFilter($qb, $filters, $conjunction);
		$this->_queryBuilderSortAndPaginate($qb, $sortBy, $offset, $limit);

		return $this->findEntities($qb);
	}

	private function _queryBuilderSortAndPaginate(IQueryBuilder $qb, string $sortBy = 'lastmodified', int $offset = 0, int $limit = -1) {
		if (!in_array($sortBy, Bookmark::$columns)) {
			$sqlSortColumn = 'lastmodified';
		} else {
			$sqlSortColumn = $sortBy;
		}

		if ($sqlSortColumn === 'title') {
			$qb->orderBy($qb->createFunction('UPPER(`title`)'), 'ASC');
		} else {
			$qb->orderBy($sqlSortColumn, 'DESC');
		}

		if ($limit !== -1) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== 0) {
			$qb->setFirstResult($offset);
		}
	}

	/**
	 * @param IQueryBuilder $qb
	 * @param array $filters
	 * @param string $tagFilterConjunction
	 */
	private function _findBookmarksBuildFilter(&$qb, $filters, $tagFilterConjunction) {
		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');
		$connectWord = 'AND';
		if ($tagFilterConjunction === 'or') {
			$connectWord = 'OR';
		}
		if (count($filters) === 0) {
			return;
		}
		if ($dbType === 'pgsql') {
			$tags = $qb->createFunction("array_to_string(array_agg(" . $qb->getColumnName('t.tag') . "), ',')");
		} else {
			$tags = $qb->createFunction('GROUP_CONCAT(' . $qb->getColumnName('t.tag') . ')');
		}
		$filterExpressions = [];
		$otherColumns = ['b.url', 'b.title', 'b.description'];
		$i = 0;
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
			$i++;
		}
		if ($connectWord === 'AND') {
			$filterExpression = call_user_func_array([$qb->expr(), 'andX'], $filterExpressions);
		} else {
			$filterExpression = call_user_func_array([$qb->expr(), 'orX'], $filterExpressions);
		}
		$qb->having($filterExpression);
	}

	/**
	 * @param string $userId
	 * @param string $tag
	 * @param string $sortBy
	 * @param int $offset
	 * @param int $limit
	 * @return array|Entity[]
	 */
	public function findByTag($userId, string $tag, string $sortBy = 'lastmodified', int $offset = 0, int $limit = 10) {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Bookmark::$columns);

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tags', 't', $qb->expr()->eq('t.bookmark_id', 'b.id'))
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)))
			->andWhere($qb->expr()->eq('t.tag', $qb->createPositionalParameter($tag)));

		$this->_queryBuilderSortAndPaginate($qb, $sortBy, $offset, $limit);

		return $this->findEntities($qb);
	}

	/**
	 * @param string $userId
	 * @param array $tags
	 * @param string $sortBy
	 * @param int $offset
	 * @param int $limit
	 * @return array|Entity[]
	 */
	public function findByTags($userId, array $tags, string $sortBy = 'lastmodified', int $offset = 0, int $limit = 10) {
		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');
		$qb = $this->db->getQueryBuilder();
		$qb->select(Bookmark::$columns);

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tags', 't', $qb->expr()->eq('t.bookmark_id', 'b.id'))
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)));

		if ($dbType === 'pgsql') {
			$tagsCol = $qb->createFunction("array_to_string(array_agg(" . $qb->getColumnName('t.tag') . "), ',')");
		} else {
			$tagsCol = $qb->createFunction('GROUP_CONCAT(' . $qb->getColumnName('t.tag') . ')');
		}

		$expr = [];
		foreach ($tags as $tag) {
			$expr[] = $qb->expr()->iLike($tagsCol, $qb->createPositionalParameter('%' . $this->db->escapeLikeParameter($tag) . '%'));
		}
		$filterExpression = call_user_func_array([$qb->expr(), 'andX'], $expr);
		$qb->groupBy(...Bookmark::$columns);
		$qb->having($filterExpression);

		$this->_queryBuilderSortAndPaginate($qb, $sortBy, $offset, $limit);

		return $this->findEntities($qb);
	}

	/**
	 * @param $userId
	 * @param string $sortBy
	 * @param int $offset
	 * @param int $limit
	 * @return array|Entity[]
	 */
	public function findUntagged($userId, string $sortBy = 'lastmodified', int $offset = 0, int $limit = 10) {
		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');
		$qb = $this->db->getQueryBuilder();
		$qb->select(Bookmark::$columns);

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tags', 't', $qb->expr()->eq('t.bookmark_id', 'b.id'))
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)));

		if ($dbType === 'pgsql') {
			$tagsCol = $qb->createFunction("array_to_string(array_agg(" . $qb->getColumnName('t.tag') . "), ',')");
		} else {
			$tagsCol = $qb->createFunction('GROUP_CONCAT(' . $qb->getColumnName('t.tag') . ')');
		}

		$qb->groupBy(...Bookmark::$columns);
		$qb->having($qb->expr()->eql($tagsCol, $qb->createPositionalParameter('')));

		$this->_queryBuilderSortAndPaginate($qb, $sortBy, $offset, $limit);
		return $this->findEntities($qb);
	}

	/**
	 *
	 * @param $token
	 * @param array $filters
	 * @param string $conjunction
	 * @param string $sortBy
	 * @param int $offset
	 * @param int $limit
	 * @return array|Entity[]
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findAllInPublicFolder($token, array $filters, string $conjunction = 'and', string $sortBy = 'lastmodified', int $offset = 0, int $limit = 10) {
		$publicFolder = $this->publicMapper->find($token);
		$bookmarks = $this->findByFolder($publicFolder->getFolderId(), $sortBy, $offset, $limit);
		// Really inefficient, but what can you do.
		return array_filter($bookmarks, function ($bookmark) use ($filters, $conjunction) {
			$tagsFound = $this->tagMapper->findByBookmark($bookmark->getId());
			return array_reduce($filters, function ($isMatch, $filter) use ($bookmark, $tagsFound, $conjunction) {
				$filter = strtolower($filter);
				$res = in_array($filter, $tagsFound)
					|| str_contains($filter, strtolower($bookmark->getTitle()))
					|| str_contains($filter, strtolower($bookmark->getDescription()))
					|| str_contains($filter, strtolower($bookmark->getUrl()));
				return $conjunction === 'and' ? $res && $isMatch : $res || $isMatch;
			}, $conjunction === 'and' ? true : false);
		});
	}

	/**
	 *
	 * @param $token
	 * @param array $tags
	 * @param string $sortBy
	 * @param int $offset
	 * @param int $limit
	 * @return array|Entity[]
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByTagsInPublicFolder($token, array $tags = [], string $sortBy = 'lastmodified', int $offset = 0, int $limit = 10) {
		$publicFolder = $this->publicMapper->find($token);
		$bookmarks = $this->findByFolder($publicFolder->getFolderId(), $sortBy, $offset, $limit);
		// Really inefficient, but what can you do.
		return array_filter($bookmarks, function ($bookmark) use ($tags) {
			$tagsFound = $this->tagMapper->findByBookmark($bookmark->getId());
			return array_reduce($tags, function ($isFound, $tag) use ($tagsFound) {
				return in_array($tag, $tagsFound) && $isFound;
			}, true);
		});
	}

	/**
	 *
	 * @param $token
	 * @param string $sortBy
	 * @param int $offset
	 * @param int $limit
	 * @return array|Entity[]
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findUntaggedInPublicFolder($token, string $sortBy = 'lastmodified', int $offset = 0, int $limit = 10) {
		$publicFolder = $this->publicMapper->find($token);
		$bookmarks = $this->findByFolder($publicFolder->getFolderId(), $sortBy, $offset, $limit);
		// Really inefficient, but what can you do.
		return array_filter($bookmarks, function ($bookmark) {
			$tags = $this->tagMapper->findByBookmark($bookmark->getId());
			return count($tags) === 0;
		});
	}


	/**
	 * @param $userId
	 * @param int $folderId
	 * @return array|Entity[]
	 */
	public function findByUserFolder($userId, int $folderId, string $sortBy = 'lastmodified', int $offset = 0, int $limit = 10) {
		if ($folderId !== -1) {
			return $this->findByFolder($folderId, $sortBy, $offset, $limit);
		} else {
			return $this->findByRootFolder($userId, $sortBy, $offset, $limit);
		}
	}

	/**
	 * @param int $folderId
	 * @param string $sortBy
	 * @param int $offset
	 * @param int $limit
	 * @return array|Entity[]
	 */
	public function findByFolder(int $folderId, string $sortBy = 'lastmodified', int $offset = 0, int $limit = 10) {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Bookmark::$columns)
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_folders_bookmarks', 'f', $qb->expr()->eq('f.bookmark_id', 'b.id'))
			->where($qb->expr()->eq('f.folder_id', $qb->createPositionalParameter($folderId)));
		$this->_queryBuilderSortAndPaginate($qb, $sortBy, $offset, $limit);
		return $this->findEntities($qb);
	}

	/**
	 * @param $userId
	 * @param string $sortBy
	 * @param int $offset
	 * @param int $limit
	 * @return array|Entity[]
	 */
	public function findByRootFolder($userId, string $sortBy = 'lastmodified', int $offset = 0, int $limit = 10) {
		$qb = $this->db->getQueryBuilder();
		$qb->select(Bookmark::$columns)
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_folders_bookmarks', 'f', $qb->expr()->eq('f.bookmark_id', 'b.id'))
			->where($qb->expr()->eq('f.folder_id', $qb->createPositionalParameter(-1)))
			->andWhere($qb->expr()->eq('b.user_id', $qb->createPositionalParameter($userId)));
		$this->_queryBuilderSortAndPaginate($qb, $sortBy, $offset, $limit);
		return $this->findEntities($qb);
	}

	/**
	 * @param int $limit
	 * @param int $stalePeriod
	 * @return array|Entity[]
	 */
	public function findPendingPreviews(int $limit, int $stalePeriod) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*');
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
		$returnedEntity = parent::delete($entity);

		$id = $entity->getId();
		$userId = $entity->getUserId();

		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_tags')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($id)));
		$qb->execute();

		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_folders_bookmarks')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($id)));
		$qb->execute();

		$this->eventDispatcher->dispatch(
			'\OCA\Bookmarks::onBookmarkDelete',
			new GenericEvent(null, ['id' => $id, 'userId' => $userId])
		);

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

		$newEntity = parent::update($entity);

		// trigger event
		$this->eventDispatcher->dispatch(
			'\OCA\Bookmarks::onBookmarkUpdate',
			new GenericEvent(null, ['id' => $entity->getId(), 'userId' => $entity->getUserId()])
		);

		return $newEntity;
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
		if ($this->limit > 0 && $this->limit <= count($this->findAll($entity->getUserId(), []))) {
			throw new UserLimitExceededError();
		}

		// normalize url
		$entity->setUrl($this->urlNormalizer->normalize($entity->getUrl()));
		if ($entity->getAdded() === null) $entity->setAdded(time());
		$entity->setLastmodified(time());
		$entity->setAdded(time());
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


		$this->eventDispatcher->dispatch(
			'\OCA\Bookmarks::onBookmarkCreate',
			new GenericEvent(null, ['id' => $entity->getId(), 'userId' => $entity->getUserId()])
		);
		return $entity;
	}

	/**
	 * @param Entity $entity
	 * @return Entity
	 * @throws AlreadyExistsError
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function insertOrUpdate(Entity $entity): Entity {
		// normalize url
		$entity->setUrl($this->urlNormalizer->normalize($entity->getUrl()));
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
	 * @param int $bookmarkId
	 * @param $fields
	 * @return string
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function hash(int $bookmarkId, $fields) {
		$entity = $this->find($bookmarkId);
		$bookmark = [];
		foreach ($fields as $field) {
			if (isset($entity->{$field})) {
				$bookmark[$field] = $entity->{'get' . $field}();
			}
		}
		return hash('sha256', json_encode($bookmark, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}
}
