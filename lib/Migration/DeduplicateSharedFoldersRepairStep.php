<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Migration;

use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class DeduplicateSharedFoldersRepairStep implements IRepairStep {
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
		return 'Deduplicate shared bookmark folders';
	}

	/**
	 * @param IOutput $output
	 */
	public function run(IOutput $output) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('p1.id')
			->from('bookmarks_shared_folders', 'p1')
			->leftJoin('p1', 'bookmarks_shared_folders', 'p2', $qb->expr()->andX(
				$qb->expr()->eq('p1.folder_id', 'p2.folder_id'),
				$qb->expr()->eq('p1.user_id', 'p2.user_id')
			))
			->where($qb->expr()->lt('p2.id', 'p1.id'));
		$duplicateSharedFolders = $qb->execute();
		$i = 0;
		while ($sharedFolder = $duplicateSharedFolders->fetchColumn()) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_tree')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($sharedFolder)))
				->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter('share')))
				->execute();
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_shared_folders')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($sharedFolder)))
				->execute();
			$i++;
		}
		$output->info("Removed $i duplicate shares");
	}
}
