<?php
namespace OCA\Bookmarks\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class BookmarkMapper extends QBMapper {

	/** @var EventDispatcherInterface */
	private $eventDispatcher;

	/** @var UrlNormalizer */
	private $urlNormalizer;

	public function __construct(IDBConnection $db, EventDispatcherInterface $eventDispatcher, UrlNormalizer $urlNormalizer) {
		parent::__construct($db, 'bookmarks');
		$this->eventDispatcher = $eventDispatcher;
		$this->urlNormalizer = $urlNormalizer;
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 */
	public function find(int $userId, int $id) : Bookmark {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from('bookmarks')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		return $this->findEntity($qb);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 */
	public function findByUrl(int $userId, string $url) : Bookmark {
		$normalized = $this->urlNormalizer->normalize($url);
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('id')
			->from('bookmarks')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('url', $qb->createNamedParameter($normalized)));

		return $this->findEntity($qb);
	}

	public function findAll(int $userId, array $filters, string $conjunction = 'and', bool $filterTagsOnly = false, string $sortBy='lastmodified', int $offset = 0, int $limit = 10) {
		$tableAttributes = ['url', 'title', 'user_id', 'description',
			'public', 'added', 'lastmodified', 'clickcount', 'last_preview'];


		if (!in_array($sortBy, $tableAttributes)) {
			$sqlSortColumn = 'lastmodified';
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

		$this->findBookmarksBuildFilter($qb, $filters, $filterTagOnly, $conjunction);

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
	 * @param bool $filterTagOnly
	 * @param string $tagFilterConjunction
	 */
	private function findBookmarksBuildFilter(&$qb, $filters, $filterTagOnly, $tagFilterConjunction) {
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
			if (!$filterTagOnly) {
				foreach ($otherColumns as $col) {
					$expr[] = $qb->expr()->iLike(
						$qb->createFunction($qb->getColumnName($col)),
						$qb->createPositionalParameter('%' . $this->db->escapeLikeParameter(strtolower($filter)) . '%')
					);
				}
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

	public function findByFolder(int $folderId) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*');

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_folders_bookmarks', 'f', $qb->expr()->eq('f.bookmark_id', 'b.id'))
			->where($qb->expr()->eq('f.folder_id', $qb->createPositionalParameter($folderId)));
		return $this->findEntities($qb);
	}

	public function findByRootFolder(int $userId) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_folders_bookmarks', 'f', $qb->expr()->eq('f.bookmark_id', 'b.id'))
			->where($qb->expr()->eq('f.folder_id', $qb->createPositionalParameter(-1)));
			->where($qb->expr()->eq('b.user_id', $qb->createPositionalParameter($userId)));
		return $this->findEntities($qb);
	}

	public function findPendingPreviews(int $limit, int $stalePeriod) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*');
		$qb->from('bookmarks', 'b');
		$qb->where($qb->expr()->lt('last_preview', $qb->createPositionalParameter(time()-$stalePeriod)));
		$qb->orWhere($qb->expr()->isNull('last_preview'));
		$qb->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	public function delete(Bookmark $entity) {
		parent::delete($entity);

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
	}

	public function update(Bookmark $entity) {
		// normalize url
		$entity->setUrl($this->urlNormalizer->normalize($entity->getUrl()));

		parent::update($entity);

		// trigger event
		$this->eventDispatcher->dispatch(
			'\OCA\Bookmarks::onBookmarkUpdate',
			new GenericEvent(null, ['id' => $entity->getId(), 'userId' => $entity->getUserId()])
		);
	}

	public function insertOrUpdate(Bookmark $entity) {
		// normalize url
		$entity->setUrl($this->urlNormalizer->normalize($entity->getUrl()));
		$newEntity = parent::insertOrUpdate($entity);
		$this->eventDispatcher->dispatch(
			'\OCA\Bookmarks::onBookmarkCreate',
			new GenericEvent(null, ['id' => $newEntity->getId(), 'userId' => $newEntity->getUserId()])
		);
	}

	public function hash(Bookmark $entity, $fields) {
		$bookmark = [];
		foreach ($fields as $field) {
			if (isset($entity->{$field})) {
				$bookmark[$field] = $entity->{$field};
			}
		}
		return hash('sha256', json_encode($bookmark, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}


	public function markPreviewCreated(Bookmark $entity) {
		$entity->setLastPreview(time());
		return $this->update($entity);
	}
}
