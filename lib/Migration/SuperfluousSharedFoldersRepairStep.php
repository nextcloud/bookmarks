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

class SuperfluousSharedFoldersRepairStep implements IRepairStep {
	/**
	 * @var IDBConnection
	 */
	private $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	/**
	 * 	 * Returns the step's name
	 *
	 * @return string
	 */
	public function getName() {
		return 'Remove superfluous shared bookmark folders';
	}

	/**
	 * @param IOutput $output
	 *
	 * @return void
	 */
	public function run(IOutput $output) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('t.id')
			->from('bookmarks_tree', 't')
			->join('t', 'bookmarks_shared_folders', 'sf', $qb->expr()->eq('t.id', 'sf.id'))
			->join('sf', 'bookmarks_shared_to_shares', 'to', $qb->expr()->eq('sf.id', 'to.shared_folder_id'))
			->join('to', 'bookmarks_shares', 's', $qb->expr()->eq('to.share_id', 's.id'))
			->where($qb->expr()->eq('t.type', $qb->createPositionalParameter('share')))
			->andWhere($qb->expr()->eq('s.owner', 'sf.user_id'));
		$superfluousSharedFolders = $qb->execute();
		$i = 0;
		while ($sharedFolder = $superfluousSharedFolders->fetchColumn()) {
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
		$output->info("Removed $i superfluous shares");
	}
}
