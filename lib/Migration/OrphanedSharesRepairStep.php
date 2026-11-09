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

class OrphanedSharesRepairStep implements IRepairStep {
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
		return 'Remove orphaned bookmark shares';
	}

	/**
	 * @param IOutput $output
	 *
	 * @return void
	 */
	public function run(IOutput $output) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('s.id')
			->from('bookmarks_shares', 's')
			->leftJoin('s', 'bookmarks_folders', 'f', $qb->expr()->eq('f.id', 's.folder_id'))
			->where($qb->expr()->isNull('f.id'));
		$shares = $qb->executeQuery();
		$i = 0;
		while ($share = $shares->fetch(\PDO::FETCH_COLUMN)) {
			$qb = $this->db->getQueryBuilder();
			$folders = $qb->select('f.id')
				->from('bookmarks_shared_folders', 'f')
				->join('f', 'bookmarks_shared_to_shares', 't', $qb->expr()->eq('f.id', 't.shared_folder_id'))
				->where($qb->expr()->eq('t.share_id', $qb->createPositionalParameter($share, IQueryBuilder::PARAM_INT)))
				->executeQuery()
				->fetchAll(PDO::FETCH_COLUMN);
			foreach ($folders as $folderId) {
				$qb = $this->db->getQueryBuilder();
				$qb->delete('bookmarks_tree')
					->where($qb->expr()->eq('type', $qb->createPositionalParameter('share')))
					->andWhere($qb->expr()->eq('id', $qb->createPositionalParameter($folderId, IQueryBuilder::PARAM_INT)))
					->executeStatement();
				$qb = $this->db->getQueryBuilder();
				$qb->delete('bookmarks_shared_folders')
					->where($qb->expr()->eq('id', $qb->createPositionalParameter($folderId, IQueryBuilder::PARAM_INT)))
					->executeStatement();
			}
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_shared_to_shares')
				->where($qb->expr()->eq('share_id', $qb->createPositionalParameter($share, IQueryBuilder::PARAM_INT)))
				->executeStatement();
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_shares')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($share)))
				->executeStatement();
			$i++;
		}
		$output->info("Removed $i orphaned shares");

		$qb = $this->db->getQueryBuilder();
		$publics = $qb->select('p.id')
			->from('bookmarks_folders_public', 'p')
			->leftJoin('p', 'bookmarks_folders', 'f', $qb->expr()->eq('f.id', 'p.folder_id'))
			->where($qb->expr()->isNull('f.id'))
			->executeQuery()
			->fetchAll(PDO::FETCH_COLUMN);
		$i = 0;
		foreach ($publics as $publicId) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_folders_public')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($publicId)))
				->executeStatement();
			$i++;
		}
		$output->info("Removed $i orphaned public links");

		// Links between shares and shared folders whose share doesn't exist anymore, left behind by earlier versions when a group or user was deleted
		$qb = $this->db->getQueryBuilder();
		$shareIds = $qb->selectDistinct('t.share_id')
			->from('bookmarks_shared_to_shares', 't')
			->leftJoin('t', 'bookmarks_shares', 's', $qb->expr()->eq('s.id', 't.share_id'))
			->where($qb->expr()->isNull('s.id'))
			->executeQuery()
			->fetchAll(PDO::FETCH_COLUMN);
		foreach ($shareIds as $shareId) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_shared_to_shares')
				->where($qb->expr()->eq('share_id', $qb->createPositionalParameter($shareId, IQueryBuilder::PARAM_INT)))
				->executeStatement();
		}
		$output->info('Removed links to ' . count($shareIds) . ' shares that don\'t exist anymore');

		// Shared folders that aren't part of any share anymore
		$qb = $this->db->getQueryBuilder();
		$sharedFolderIds = $qb->select('sf.id')
			->from('bookmarks_shared_folders', 'sf')
			->leftJoin('sf', 'bookmarks_shared_to_shares', 't', $qb->expr()->eq('t.shared_folder_id', 'sf.id'))
			->where($qb->expr()->isNull('t.share_id'))
			->executeQuery()
			->fetchAll(PDO::FETCH_COLUMN);
		foreach ($sharedFolderIds as $sharedFolderId) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_tree')
				->where($qb->expr()->eq('type', $qb->createPositionalParameter('share')))
				->andWhere($qb->expr()->eq('id', $qb->createPositionalParameter($sharedFolderId, IQueryBuilder::PARAM_INT)))
				->executeStatement();
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_shared_folders')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($sharedFolderId, IQueryBuilder::PARAM_INT)))
				->executeStatement();
		}
		$output->info('Removed ' . count($sharedFolderIds) . ' shared folders without a share');

		// Links between shares and shared folders whose shared folder doesn't exist anymore
		$qb = $this->db->getQueryBuilder();
		$linkIds = $qb->selectDistinct('t.shared_folder_id')
			->from('bookmarks_shared_to_shares', 't')
			->leftJoin('t', 'bookmarks_shared_folders', 'sf', $qb->expr()->eq('sf.id', 't.shared_folder_id'))
			->where($qb->expr()->isNull('sf.id'))
			->executeQuery()
			->fetchAll(PDO::FETCH_COLUMN);
		foreach ($linkIds as $sharedFolderId) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_shared_to_shares')
				->where($qb->expr()->eq('shared_folder_id', $qb->createPositionalParameter($sharedFolderId, IQueryBuilder::PARAM_INT)))
				->executeStatement();
		}
		$output->info('Removed ' . count($linkIds) . ' orphaned links between shares and shared folders');

		// Tree entries of shared folders that don't exist anymore
		$qb = $this->db->getQueryBuilder();
		$treeIds = $qb->select('t.id')
			->from('bookmarks_tree', 't')
			->leftJoin('t', 'bookmarks_shared_folders', 'sf', $qb->expr()->eq('sf.id', 't.id'))
			->where($qb->expr()->eq('t.type', $qb->createPositionalParameter('share')))
			->andWhere($qb->expr()->isNull('sf.id'))
			->executeQuery()
			->fetchAll(PDO::FETCH_COLUMN);
		foreach ($treeIds as $sharedFolderId) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_tree')
				->where($qb->expr()->eq('type', $qb->createPositionalParameter('share')))
				->andWhere($qb->expr()->eq('id', $qb->createPositionalParameter($sharedFolderId, IQueryBuilder::PARAM_INT)))
				->executeStatement();
		}
		$output->info('Removed ' . count($treeIds) . ' orphaned shared folder entries');
	}
}
