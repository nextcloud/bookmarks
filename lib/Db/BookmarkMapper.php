<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
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
	 * BookmarkMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param IEventDispatcher $eventDispatcher
	 * @param UrlNormalizer $urlNormalizer
	 * @param IConfig $config
	 * @param PublicFolderMapper $publicMapper
	 * @param TagMapper $tagMapper
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(IDBConnection $db, IEventDispatcher $eventDispatcher, UrlNormalizer $urlNormalizer, IConfig $config, PublicFolderMapper $publicMapper, TagMapper $tagMapper, ITimeFactory $timeFactory) {
		parent::__construct($db, 'bookmarks', Bookmark::class);
		$this->eventDispatcher = $eventDispatcher;
		$this->urlNormalizer = $urlNormalizer;
		$this->config = $config;
		$this->limit = (int)$config->getAppValue('bookmarks', 'performance.maxBookmarksperAccount', 0);
		$this->publicMapper = $publicMapper;
		$this->tagMapper = $tagMapper;

		$this->deleteTagsQuery = $this->getDeleteTagsQuery();
		$this->findByUrlQuery = $this->getFindByUrlQuery();
		$this->time = $timeFactory;
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
	 * @return Entity
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	protected function findByUrl($userId, $url) {
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
	 * @return Entity
	 */
	protected function mapRowToEntity(array $row): Entity {
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
	 * @param string $userId
	 * @param QueryParameters $params
	 *
	 * @return Entity[]
	 *
	 * @throws UrlParseError
	 */
	public function findAll(string $userId, QueryParameters $params): array {
		$qb = $this->db->getQueryBuilder();
		$bookmark_cols = array_map(static function ($c) {
			return 'b.' . $c;
		}, Bookmark::$columns);

		$qb->select($bookmark_cols);
		$qb->groupBy($bookmark_cols);

		$this->_selectFolders($qb);
		$this->_selectTags($qb);

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tree', 'tr', 'tr.id = b.id AND tr.type = '.$qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK))
			->leftJoin('tr', 'bookmarks_shared_folders', 'sf', $qb->expr()->eq('tr.parent_folder', 'sf.folder_id'))
			->leftJoin('tr', 'bookmarks_tree', 'tr2', 'tr2.id = tr.parent_folder AND tr2.type = '. $qb->createPositionalParameter(TreeMapper::TYPE_FOLDER))
			->leftJoin('tr2', 'bookmarks_shared_folders', 'sf2', $qb->expr()->eq('tr2.parent_folder', 'sf.folder_id'))
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('b.user_id', $qb->createPositionalParameter($userId)),
					$qb->expr()->eq('sf.user_id', $qb->createPositionalParameter($userId)),
					$qb->expr()->eq('sf2.user_id', $qb->createPositionalParameter($userId))
				)
			);

		$this->_filterUrl($qb, $params);
		$this->_filterArchived($qb, $params);
		$this->_filterUnavailable($qb, $params);
		$this->_filterFolder($qb, $params);
		$this->_filterTags($qb, $params);
		$this->_filterUntagged($qb, $params);
		$this->_filterSearch($qb, $params);
		$this->_sortAndPaginate($qb, $params);

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

		$count = $qb->execute()->fetch(PDO::FETCH_COLUMN)[0];

		return (int)$count;
	}

	private function _sortAndPaginate(IQueryBuilder $qb, QueryParameters $params): void {
		$sqlSortColumn = $params->getSortBy('lastmodified', $this->getSortByColumns());

		if ($sqlSortColumn === 'title') {
			$qb->addOrderBy($qb->createFunction('UPPER(`b`.`title`)'), 'ASC');
		} elseif ($sqlSortColumn === 'index') {
			$qb->addOrderBy('tr.'.$sqlSortColumn, 'ASC');
			$qb->addGroupBy('tr.'.$sqlSortColumn);
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
	 */
	private function _filterFolder(IQueryBuilder $qb, QueryParameters $params): void {
		if ($params->getFolder() !== null) {
			$qb->andWhere($qb->expr()->eq('tr.parent_folder', $qb->createPositionalParameter($params->getFolder(), IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('tr.type', $qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK)));
		}
	}

	/**
	 * @param IQueryBuilder $qb
	 */
	private function _selectTags(IQueryBuilder $qb): void {
		$qb->leftJoin('b', 'bookmarks_tags', 't', $qb->expr()->eq('t.bookmark_id', 'b.id'));
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
				$qb->leftJoin('b', 'bookmarks_tags', 'tg'.$i, $qb->expr()->eq('tg'.$i.'.bookmark_id', 'b.id'));
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
			->where($qb->expr()->orX(
				$qb->expr()->eq('b.user_id', $qb->createPositionalParameter($userId)),
				$qb->expr()->eq('sf.user_id', $qb->createPositionalParameter($userId))
			))
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
			->where($qb->expr()->orX(
				$qb->expr()->eq('b.user_id', $qb->createPositionalParameter($userId)),
				$qb->expr()->eq('sf.user_id', $qb->createPositionalParameter($userId))
			))
			->andWhere($qb->expr()->eq('b.available', $qb->createPositionalParameter(false, IQueryBuilder::PARAM_BOOL)));

		return $qb->execute()->fetch(PDO::FETCH_COLUMN);
	}

	/**
	 * 	 *
	 *
	 * @param string $token
	 * @param QueryParameters $params
	 *
	 *
	 * @return Entity[]
	 *
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 *
	 * @psalm-return array<array-key, Bookmark>
	 */
	public function findAllInPublicFolder(string $token, QueryParameters $params): array {
		/** @var PublicFolder $publicFolder */
		$publicFolder = $this->publicMapper->find($token);

		$qb = $this->db->getQueryBuilder();
		$bookmark_cols = array_map(static function ($c) {
			return 'b.' . $c;
		}, Bookmark::$columns);

		$qb->select($bookmark_cols);
		$qb->groupBy($bookmark_cols);

		$this->_selectFolders($qb);
		$this->_selectTags($qb);

		$qb
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tree', 'tr', 'tr.id = b.id AND tr.type = '.$qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK))
			->leftJoin('tr', 'bookmarks_tree', 'tr2', 'tr2.id = tr.parent_folder AND tr2.type = '.$qb->createPositionalParameter(TreeMapper::TYPE_FOLDER))
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('tr.parent_folder', $qb->createPositionalParameter($publicFolder->getFolderId(), IQueryBuilder::PARAM_INT)),
					$qb->expr()->eq('tr2.parent_folder', $qb->createPositionalParameter($publicFolder->getFolderId(), IQueryBuilder::PARAM_INT))
				)
			);

		$this->_filterUrl($qb, $params);
		$this->_filterArchived($qb, $params);
		$this->_filterUnavailable($qb, $params);
		$this->_filterFolder($qb, $params);
		$this->_filterTags($qb, $params);
		$this->_filterUntagged($qb, $params);
		$this->_filterSearch($qb, $params);
		$this->_sortAndPaginate($qb, $params);

		return $this->findEntities($qb);
	}

	/**
	 * @param int $limit
	 * @param int $stalePeriod
	 *
	 * @return Entity[]
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
	 * @param Entity $entity
	 *
	 * @return Entity
	 * @psalm-return Bookmark
	 */
	public function delete(Entity $entity): Entity {
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
	 * @param Entity $entity
	 * @return Entity
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError|MultipleObjectsReturnedException
	 */
	public function insertOrUpdate(Entity $entity): Entity {
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
		$qb->leftJoin('b', 'bookmarks_tree', 'tree', 'b.id =tree.id AND tree.type = '.$qb->createPositionalParameter(TreeMapper::TYPE_BOOKMARK));
		if ($this->getDbType() === 'pgsql') {
			$folders = $qb->createFunction('array_to_string(array_agg(' . $qb->getColumnName('tree.parent_folder') . "), ',')");
		} else {
			$folders = $qb->createFunction('GROUP_CONCAT(' . $qb->getColumnName('tree.parent_folder') . ')');
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
			$tagsCol = $qb->createFunction('GROUP_CONCAT(' . $qb->getColumnName('t.tag') . ')');
		}
		return $tagsCol;
	}
}
