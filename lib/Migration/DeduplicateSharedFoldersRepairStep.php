<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Migration;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use PDO;

class DeduplicateSharedFoldersRepairStep implements IRepairStep {
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
		return 'Deduplicate shared bookmark folders';
	}

	/**
	 * @param IOutput $output
	 *
	 * @return void
	 */
	public function run(IOutput $output) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('p1.id')
			->selectAlias($qb->func()->min('p2.id'), 'kept_id')
			->from('bookmarks_shared_folders', 'p1')
			->leftJoin('p1', 'bookmarks_shared_folders', 'p2', 'p1.folder_id = p2.folder_id AND p1.user_id = p2.user_id')
			->where($qb->expr()->lt('p2.id', 'p1.id'))
			->groupBy('p1.id');
		$duplicateSharedFolders = $qb->executeQuery()->fetchAll();
		$i = 0;
		foreach ($duplicateSharedFolders as $row) {
			$sharedFolder = (int)$row['id'];
			$keptSharedFolder = (int)$row['kept_id'];
			// The kept shared folder becomes part of the shares of the duplicate
			$qb = $this->db->getQueryBuilder();
			$shareIds = $qb->select('share_id')
				->from('bookmarks_shared_to_shares')
				->where($qb->expr()->eq('shared_folder_id', $qb->createPositionalParameter($sharedFolder, IQueryBuilder::PARAM_INT)))
				->executeQuery()
				->fetchAll(PDO::FETCH_COLUMN);
			$qb = $this->db->getQueryBuilder();
			$keptShareIds = $qb->select('share_id')
				->from('bookmarks_shared_to_shares')
				->where($qb->expr()->eq('shared_folder_id', $qb->createPositionalParameter($keptSharedFolder, IQueryBuilder::PARAM_INT)))
				->executeQuery()
				->fetchAll(PDO::FETCH_COLUMN);
			foreach (array_diff($shareIds, $keptShareIds) as $shareId) {
				$qb = $this->db->getQueryBuilder();
				$qb->insert('bookmarks_shared_to_shares')->values([
					'shared_folder_id' => $qb->createPositionalParameter($keptSharedFolder, IQueryBuilder::PARAM_INT),
					'share_id' => $qb->createPositionalParameter((int)$shareId, IQueryBuilder::PARAM_INT),
				])->executeStatement();
			}
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_shared_to_shares')
				->where($qb->expr()->eq('shared_folder_id', $qb->createPositionalParameter($sharedFolder, IQueryBuilder::PARAM_INT)))
				->executeStatement();

			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_tree')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($sharedFolder)))
				->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter('share')))
				->executeStatement();
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_shared_folders')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($sharedFolder)))
				->executeStatement();
			$i++;
		}
		$output->info("Removed $i duplicate shares");
	}
}
