<?php


namespace OCA\Bookmarks\Migration;


use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class OrphanedTreeItemsRepairStep implements IRepairStep {
	/**
	 * @var IDBConnection
	 */
	private $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	/**
	 * Returns the step's name
	 */
	public function getName() {
		return 'Remove orphaned tree items';
	}

	/**
	 * @param IOutput $output
	 */
	public function run(IOutput $output) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('t.id')
			->from('bookmarks_tree', 't')
			->leftJoin('t', 'bookmarks', 'b', $qb->expr()->eq('b.id', 't.id'))
			->where($qb->expr()->isNull('b.id'));
		$orphanedBookmarks = $qb->execute();
		while ($bookmark = $orphanedBookmarks->fetchColumn()) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_tree')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($bookmark)))
				->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter('bookmark')))
				->execute();
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('t.id')
			->from('bookmarks_tree', 't')
			->leftJoin('t', 'bookmarks_folders', 'f', $qb->expr()->eq('t.id', 'f.id'))
			->leftJoin('t', 'bookmarks_root_folders', 'r', $qb->expr()->eq('t.id', 'r.folder_id'))
			->where($qb->expr()->isNull('f.id'))
			->andWhere($qb->expr()->isNull('r.folder_id'));
		$orphanedFolders = $qb->execute();
		while ($folder = $orphanedFolders->fetchColumn()) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_tree')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($folder)))
				->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter('folder')))
				->execute();
		}
	}
}
