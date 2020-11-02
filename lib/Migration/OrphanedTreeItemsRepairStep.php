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
		return 'Remove orphaned bookmark tree items';
	}

	/**
	 * @param IOutput $output
	 */
	public function run(IOutput $output) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('t.id')
			->from('bookmarks_tree', 't')
			->leftJoin('t', 'bookmarks', 'b', $qb->expr()->andX(
				$qb->expr()->eq('b.id', 't.id'),
				$qb->expr()->eq('t.type', $qb->createPositionalParameter('bookmark'))
			))
			->where($qb->expr()->isNull('b.id'));
		$orphanedBookmarks = $qb->execute();
		$i = 0;
		while ($bookmark = $orphanedBookmarks->fetchColumn()) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_tree')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($bookmark)))
				->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter('bookmark')))
				->execute();
			$i++;
		}
		$output->info("Removed $i orphaned bookmarks entries");

		$qb = $this->db->getQueryBuilder();
		$qb->select('t.id')
			->from('bookmarks_tree', 't')
			->leftJoin('t', 'bookmarks_folders', 'f', $qb->expr()->andX(
				$qb->expr()->eq('f.id', 't.id'),
				$qb->expr()->eq('t.type', $qb->createPositionalParameter('folder'))
			))
			->leftJoin('t', 'bookmarks_root_folders', 'r', $qb->expr()->andX(
				$qb->expr()->eq('t.id', 'r.folder_id'),
				$qb->expr()->eq('t.type', $qb->createPositionalParameter('folder'))
			))
			->where($qb->expr()->isNull('f.id'))
			->andWhere($qb->expr()->isNull('r.folder_id'));
		$orphanedFolders = $qb->execute();
		$i = 0;
		while ($folder = $orphanedFolders->fetchColumn()) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_tree')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($folder)))
				->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter('folder')))
				->execute();
			$i++;
		}
		$output->info("Removed $i orphaned folders entries");

		$qb = $this->db->getQueryBuilder();
		$qb->select('t.id', 't.type')
			->from('bookmarks_tree', 't')
			->leftJoin('t', 'bookmarks_folders', 'f', $qb->expr()->eq('t.parent_folder', 'f.id'))
			->where($qb->expr()->isNull('f.id'));
		$orphanedTreeItems = $qb->execute();
		$i = 0;
		while ($treeItem = $orphanedTreeItems->fetch()) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_tree')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($treeItem['id'])))
				->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter($treeItem['type'])))
				->execute();
			$i++;
		}
		$output->info("Removed $i orphaned children entries");

		$qb = $this->db->getQueryBuilder();
		$qb->select('f.id')
			->from('bookmarks_folders', 'f')
			->leftJoin('f', 'bookmarks_tree', 't', $qb->expr()->andX(
				$qb->expr()->eq('t.id', 'f.id'),
				$qb->expr()->eq('t.type', $qb->createPositionalParameter('folder'))
			))
			->leftJoin('f', 'bookmarks_root_folders', 'r', $qb->expr()->andX(
				$qb->expr()->eq('r.folder_id', 't.id'),
				$qb->expr()->eq('t.type', $qb->createPositionalParameter('folder'))
			))
			->where($qb->expr()->isNull('t.id'))
			->andWhere($qb->expr()->isNull('r.folder_id'));
		$orphanedFolders = $qb->execute();
		$i = 0;
		while ($folder = $orphanedFolders->fetchColumn()) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_folders')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($folder)))
				->execute();
			$i++;
		}
		$output->info("Removed $i orphaned bookmark folders");

		$qb = $this->db->getQueryBuilder();
		$qb->select('b.id')
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tree', 't', $qb->expr()->andX(
				$qb->expr()->eq('b.id', 't.id'),
				$qb->expr()->eq('t.type', $qb->createPositionalParameter('bookmark'))
			))
			->where($qb->expr()->isNull('t.id'));
		$orphanedBookmarks = $qb->execute();
		$i = 0;
		while ($bookmark = $orphanedBookmarks->fetchColumn()) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($bookmark)))
				->execute();
			$i++;
		}
		$output->info("Removed $i orphaned bookmarks");
	}
}
