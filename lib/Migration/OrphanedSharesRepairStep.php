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
use PDO;

class OrphanedSharesRepairStep implements IRepairStep {
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
		return 'Remove orphaned bookmark shares';
	}

	/**
	 * @param IOutput $output
	 */
	public function run(IOutput $output) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('s.id')
			->from('bookmarks_shares', 's')
			->leftJoin('s', 'bookmarks_folders', 'f', $qb->expr()->eq('f.id', 's.folder_id'))
			->where($qb->expr()->isNull('f.id'));
		$shares = $qb->execute();
		$i = 0;
		while ($share = $shares->fetchColumn()) {
			$qb = $this->db->getQueryBuilder();
			$folders = $qb->select('f.id')
				->from('bookmarks_shared_folders', 'f')
				->join('f', 'bookmarks_shared_to_shares', 't', $qb->expr()->eq('f.id', 't.shared_folder_id'))
				->where($qb->expr()->eq('t.share_id', $qb->createPositionalParameter($share)))
				->execute()
				->fetchAll(PDO::FETCH_COLUMN);
			foreach ($folders as $folderId) {
				$qb = $this->db->getQueryBuilder();
				$qb->delete('bookmarks_tree')
					->where($qb->expr()->eq('type', $qb->createPositionalParameter('share')))
					->andWhere($qb->expr()->eq('id', $qb->createPositionalParameter($folderId)))
					->execute();
			}
			$this->db->executeQuery('DELETE sf FROM *PREFIX*bookmarks_shared_folders sf JOIN *PREFIX*bookmarks_shared_to_shares t ON sf.id = t.shared_folder_id WHERE t.share_id = ?', [$share]);
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_shares')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($share)))
				->execute();
			$i++;
		}
		$output->info("Removed $i orphaned shares");

		$qb = $this->db->getQueryBuilder();
		$publics = $qb->select('p.id')
			->from('bookmarks_folders_public', 'p')
			->leftJoin('p', 'bookmarks_folders', 'f', $qb->expr()->eq('f.id', 'p.folder_id'))
			->where($qb->expr()->isNull('f.id'))
			->execute()
			->fetchAll(PDO::FETCH_COLUMN);
		$i = 0;
		foreach ($publics as $publicId) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_folders_public')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($publicId)))
				->execute();
			$i++;
		}
		$output->info("Removed $i orphaned public links");
	}
}
