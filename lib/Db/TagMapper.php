<?php

namespace OCA\Bookmarks\Db;

use Doctrine\DBAL\Driver\Statement;
use InvalidArgumentException;
use OCP\IDBConnection;

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

	/**
	 * TagMapper constructor.
	 *
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	/**
	 * @param $userId
	 * @return array
	 */
	public function findAllWithCount($userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('t.tag')
			->selectAlias($qb->createFunction('COUNT(' . $qb->getColumnName('t.bookmark_id') . ')'), 'nbr')
			->from('bookmarks_tags', 't')
			->innerJoin('t', 'bookmarks', 'b', $qb->expr()->eq('b.id', 't.bookmark_id'))
			->where($qb->expr()->eq('b.user_id', $qb->createNamedParameter($userId)));
		$qb
			->groupBy('t.tag')
			->orderBy('nbr', 'DESC');

		return $qb->execute()->fetchAll();
	}

	/**
	 * @param $userId
	 * @return array
	 */
	public function findAll($userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('t.tag')
			->from('bookmarks_tags', 't')
			->innerJoin('t', 'bookmarks', 'b', $qb->expr()->eq('b.id', 't.bookmark_id'))
			->where($qb->expr()->eq('b.user_id', $qb->createNamedParameter($userId)))
			->groupBy('t.tag');
		return $qb->execute()->fetchAll(\PDO::FETCH_COLUMN);
	}

	/**
	 * @param int $bookmarkId
	 * @return array
	 */
	public function findByBookmark(int $bookmarkId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('tag');

		$qb
			->from('bookmarks_tags', 't')
			->where($qb->expr()->eq('t.bookmark_id', $qb->createPositionalParameter($bookmarkId)));

		return $qb->execute()->fetchAll(\PDO::FETCH_COLUMN);
	}

	/**
	 * @param $userId
	 * @param string $tag
	 * @return Statement|int
	 */
	public function delete($userId, string $tag) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_tags', 'tgs')
			->innerJoin('tgs', 'bookmarks', 'bm', $qb->expr()->eq('tgs.bookmark_id', 'bm.id'))
			->where($qb->expr()->eq('tgs.tag', $qb->createNamedParameter($tag)))
			->andWhere($qb->expr()->eq('bm.user_id', $qb->createNamedParameter($userId)));
		return $qb->execute();
	}

	/**
	 * @param $userId
	 * @return Statement|int
	 */
	public function deleteAll(int $userId) {
		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_tags', 'tgs')
			->innerJoin('tgs', 'bookmarks', 'bm', $qb->expr()->eq('tgs.bookmark_id', 'bm.id'))
			->where($qb->expr()->eq('bm.user_id', $qb->createNamedParameter($userId)));
		return $qb->execute();
	}

	/**
	 * @param $tags
	 * @param int $bookmarkId
	 */
	public function addTo($tags, int $bookmarkId): void {
		if (is_string($tags)) {
			$tags = [$tags];
		} else if (!is_array($tags)) {
			throw new InvalidArgumentException('$tag must be string or array of strings');
		}
		if(count($tags) === 0) {
			return;
		}
		$currentTags = $this->findByBookmark($bookmarkId);
		$tags = array_filter($tags, function($tag) use($currentTags) {
			return !in_array($tag, $currentTags);
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
			$qb->execute();
		}
	}

	/**
	 * @param int $bookmarkId
	 */
	public function removeAllFrom(int $bookmarkId): void {
		// Remove old tags
		$qb = $this->db->getQueryBuilder();
		$qb
			->delete('bookmarks_tags')
			->where($qb->expr()->eq('bookmark_id', $qb->createNamedParameter($bookmarkId)));
		$qb->execute();
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
	 * @param $userId UserId
	 * @param string $old Old Tag Name
	 * @param string $new New Tag Name
	 */
	public function renameTag($userId, string $old, string $new): void {
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
		$duplicates = $qb->execute()->fetchAll(\PDO::FETCH_COLUMN);
		if (count($duplicates) !== 0) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->delete('bookmarks_tags')
				->where($qb->expr()->in('bookmark_id', array_map([$qb, 'createNamedParameter'], $duplicates)))
				->andWhere($qb->expr()->eq('tag', $qb->createNamedParameter($old)));
			$qb->execute();
		}

		// Update tags to the new label
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('tgs.bookmark_id')
			->from('bookmarks_tags', 'tgs')
			->innerJoin('tgs', 'bookmarks', 'bm', $qb->expr()->eq('tgs.bookmark_id', 'bm.id'))
			->where($qb->expr()->eq('tgs.tag', $qb->createNamedParameter($old)))
			->andWhere($qb->expr()->eq('bm.user_id', $qb->createNamedParameter($userId)));
		$bookmarks = $qb->execute()->fetchAll(\PDO::FETCH_COLUMN);
		if (count($bookmarks) !== 0) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->update('bookmarks_tags')
				->set('tag', $qb->createNamedParameter($new))
				->where($qb->expr()->eq('tag', $qb->createNamedParameter($old)))
				->andWhere($qb->expr()->in('bookmark_id', array_map([$qb, 'createNamedParameter'], $bookmarks)));
			$qb->execute();
		}
	}
}
