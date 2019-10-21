<?php
namespace OCA\Bookmarks\Db;

use OCA\Bookmarks\UrlNormalizer;
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
	 * BookmarkMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param EventDispatcherInterface $eventDispatcher
	 * @param UrlNormalizer $urlNormalizer
	 */
	public function __construct(IDBConnection $db, EventDispatcherInterface $eventDispatcher, UrlNormalizer $urlNormalizer, IConfig $config) {
		parent::__construct($db, 'bookmarks');
		$this->eventDispatcher = $eventDispatcher;
		$this->urlNormalizer = $urlNormalizer;
		$this->config = $config;
	}

	/**
	 * Find a specific bookmark by Id
	 *
	 * @param int $id
	 * @return Entity
	 * @throws DoesNotExistException if not found
	 * @throws MultipleObjectsReturnedException if more than one result
	 */
	public function find(int $id) : Entity {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		return $this->findEntity($qb);
	}

	/**
	 * @param int $userId
	 * @param string $url
	 * @return Entity
	 * @throws DoesNotExistException if not found
	 * @throws MultipleObjectsReturnedException if more than one result
	 */
	public function findByUrl(int $userId, string $url) : Entity {
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
	 * @param int $userId
	 * @param array $filters
	 * @param string $conjunction
	 * @param string $sortBy
	 * @param int $offset
	 * @param int $limit
	 * @return array|Entity[]
	 */
	public function findAll(int $userId, array $filters, string $conjunction = 'and', string $sortBy='lastmodified', int $offset = 0, int $limit = 10) {
		$tableAttributes = ['url', 'title', 'user_id', 'description',
			'public', 'added', 'lastmodified', 'clickcount', 'last_preview'];

		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');

		if (!in_array($sortBy, $tableAttributes)) {
			$sqlSortColumn = 'lastmodified';
		}else{
			$sqlSortColumn = $sortBy;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*');

		if ($dbType === 'pgsql') {
			$qb->selectAlias($qb->createFunction("array_to_string(array_agg(" . $qb->getColumnName('t.tag') . "), ',')"), 'tags');
		} else {
			$qb->selectAlias($qb->createFunction('GROUP_CONCAT(' . $qb->getColumnName('t.tag') . ')'), 'tags');
		}

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tags', 't', $qb->expr()->eq('t.bookmark_id', 'b.id'))
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)));

		$this->findBookmarksBuildFilter($qb, $filters, $conjunction);

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

		return $this->findEntities($qb);
	}

	/**
	 * @param IQueryBuilder $qb
	 * @param array $filters
	 * @param string $tagFilterConjunction
	 */
	private function findBookmarksBuildFilter(&$qb, $filters, $tagFilterConjunction) {
		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');
		$connectWord = 'AND';
		if ($tagFilterConjunction === 'or') {
			$connectWord = 'OR';
		}
		if (count($filters) === 0) {
			return;
		}
		$filterExpressions = [];
		$otherColumns = ['b.url', 'b.title', 'b.description'];
		$i = 0;
		foreach ($filters as $filter) {
			$expr = [];
			if ($dbType === 'pgsql') {
				$expr[] = $qb->expr()->iLike(
					// Postgres doesn't like select aliases in HAVING clauses, well f*** you too!
					$qb->createFunction("array_to_string(array_agg(" . $qb->getColumnName('t.tag') . "), ',')"),
					$qb->createPositionalParameter('%'.$this->db->escapeLikeParameter($filter).'%')
				);
			} else {
				$expr[] = $qb->expr()->iLike('tags', $qb->createPositionalParameter('%'.$this->db->escapeLikeParameter($filter).'%'));
			}
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
	 * @param int $userId
	 * @param string $tag
	 * @return array|Entity[]
	 */
	public function findByTag(int $userId, string $tag) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*');

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tags', 't', $qb->expr()->eq('t.bookmark_id', 'b.id'))
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)))
			->andWhere($qb->expr()->eq('t.tag', $qb->createPositionalParameter($tag)));

		return $this->findEntities($qb);
	}

	/**
	 * @param int $userId
	 * @param string $tag
	 * @return array|Entity[]
	 */
	public function findByTags(int $userId, array $tags) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*');

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tags', 't', $qb->expr()->eq('t.bookmark_id', 'b.id'))
			->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($userId)));
		foreach($tags as $tag) {
			$qb->andWhere($qb->expr()->eq('t.tag', $qb->createPositionalParameter($tag)));
		}
		$qb->groupBy('b.id');

		return $this->findEntities($qb);
	}

	/**
	 * @param int $folderId
	 * @return array|Entity[]
	 */
	public function findByFolder(int $folderId) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'url', 'title', 'description', 'lastmodified', 'added', 'clickcount')
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_folders_bookmarks', 'f', $qb->expr()->eq('f.bookmark_id', 'b.id'))
			->where($qb->expr()->eq('f.folder_id', $qb->createPositionalParameter($folderId)));
		return $this->findEntities($qb);
	}

	/**
	 * @param int $userId
	 * @return array|Entity[]
	 */
	public function findByRootFolder(int $userId) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'url', 'title', 'description', 'lastmodified', 'added', 'clickcount')
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_folders_bookmarks', 'f', $qb->expr()->eq('f.bookmark_id', 'b.id'))
			->where($qb->expr()->eq('f.folder_id', $qb->createPositionalParameter(-1)))
			->where($qb->expr()->eq('b.user_id', $qb->createPositionalParameter($userId)));
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
		$qb->where($qb->expr()->lt('last_preview', $qb->createPositionalParameter(time()-$stalePeriod)));
		$qb->orWhere($qb->expr()->isNull('last_preview'));
		$qb->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/**
	 * @param Entity $entity
	 * @return Entity|void
	 */
	public function delete(Entity $entity) : Entity {
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
	 */
	public function update(Entity $entity) : Entity {
		// normalize url
		$entity->setUrl($this->urlNormalizer->normalize($entity->getUrl()));

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
	 * @throws \Exception
	 */
	public function insert(Entity $entity) : Entity {
		// normalize url
		$entity->setUrl($this->urlNormalizer->normalize($entity->getUrl()));

		$exists = true;
		try {
			$this->findByUrl($entity->getUserId(), $entity->getUrl());
		} catch (DoesNotExistException $e) {
			$exists = false;
		} catch (MultipleObjectsReturnedException $e) {
			$exists = true;
		}

		if ($exists) {
			// TODO: Create a separate Exception for this!
			throw new \Exception('A bookmark with this URL already exists');
		}

		$newEntity = parent::insert($entity);
		$this->eventDispatcher->dispatch(
			'\OCA\Bookmarks::onBookmarkCreate',
			new GenericEvent(null, ['id' => $newEntity->getId(), 'userId' => $newEntity->getUserId()])
		);
		return $newEntity;
	}

	/**
	 * @param Entity $entity
	 * @return Entity
	 * @throws MultipleObjectsReturnedException
	 */
	public function insertOrUpdate(Entity $entity) : Entity {
		// normalize url
		$entity->setUrl($this->urlNormalizer->normalize($entity->getUrl()));

		try {
			$existing = $this->findByUrl($entity->getUserId(), $entity->getUrl());
			$entity->setId($existing->getId());
		} catch (DoesNotExistException $e) {
			// This bookmark doesn't already exist. That's ok.
		}

		$newEntity = parent::insertOrUpdate($entity);
		$this->eventDispatcher->dispatch(
			'\OCA\Bookmarks::onBookmarkCreate',
			new GenericEvent(null, ['id' => $newEntity->getId(), 'userId' => $newEntity->getUserId()])
		);
		return $newEntity;
	}

	/**
	 * @param Entity $entity
	 * @param $fields
	 * @return string
	 */
	public function hash(Entity $entity, $fields) {
		$bookmark = [];
		foreach ($fields as $field) {
			if (isset($entity->{$field})) {
				$bookmark[$field] = $entity->{$field};
			}
		}
		return hash('sha256', json_encode($bookmark, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}


	/**
	 * @param Entity $entity
	 * @return Entity
	 */
	public function markPreviewCreated(Entity $entity) {
		$entity->setLastPreview(time());
		return $this->update($entity);
	}
}
