<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Migration;

use OCP\DB\QueryBuilder\IQueryBuilder;
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
	 * 	 * Returns the step's name
	 *
	 * @return string
	 */
	public function getName() {
		return 'Remove orphaned bookmark tree items';
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
			->leftJoin('t', 'bookmarks', 'b', $qb->expr()->eq('b.id', 't.id'))
			->where($qb->expr()->isNull('b.id'))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter('bookmark')));
		$orphanedBookmarks = $qb->execute();
		$i = 0;
		while ($bookmark = $orphanedBookmarks->fetchColumn()) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_tree')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($bookmark, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter('bookmark')))
				->execute();
			$i++;
		}
		$output->info("Removed $i orphaned bookmarks entries");

		$qb = $this->db->getQueryBuilder();
		$qb->select('t.id')
			->from('bookmarks_tree', 't')
			->leftJoin('t', 'bookmarks_folders', 'f', $qb->expr()->eq('f.id', 't.id'))
			->leftJoin('t', 'bookmarks_root_folders', 'r', $qb->expr()->eq('t.id', 'r.folder_id'))
			->where($qb->expr()->isNull('f.id'))
			->andWhere($qb->expr()->isNull('r.folder_id'))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter('folder')));
		$orphanedFolders = $qb->execute();
		$i = 0;
		while ($folder = $orphanedFolders->fetchColumn()) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_tree')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($folder, IQueryBuilder::PARAM_INT)))
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
			if ($treeItem['type'] === 'bookmark') {
				$qb = $this->db->getQueryBuilder();
				$bookmark = $qb->select('user_id')
					->from('bookmarks')
					->where($qb->expr()->eq('id', $qb->createNamedParameter($treeItem['id'])))
					->execute()
					->fetch();
				$userId = $bookmark['user_id'];
			} elseif ($treeItem['type'] === 'folder') {
				$qb = $this->db->getQueryBuilder();
				$folder = $qb->select('user_id')
					->from('bookmarks_folders')
					->where($qb->expr()->eq('id', $qb->createNamedParameter($treeItem['id'])))
					->execute()
					->fetch();
				$userId = $folder['user_id'];
			} elseif ($treeItem['type'] === 'share') {
				$qb = $this->db->getQueryBuilder();
				$folder = $qb->select('user_id')
					->from('bookmarks_shared_folders')
					->where($qb->expr()->eq('id', $qb->createNamedParameter($treeItem['id'])))
					->execute()
					->fetch();
				$userId = $folder['user_id'];
			}

			$rootFolder = $this->ensureRootFolder($userId);
			$qb = $this->db->getQueryBuilder();
			$qb->update('bookmarks_tree')
				->set('parent_folder', $qb->createNamedParameter($rootFolder['folder_id']))
				->set('index', $qb->createNamedParameter($rootFolder['count']))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($treeItem['id'], IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('type', $qb->createNamedParameter($treeItem['type'])))
				->execute();
			$i++;
		}
		$output->info("Reinserted $i orphaned children entries");

		$qb = $this->db->getQueryBuilder();
		$qb->select('f.id')
			->from('bookmarks_folders', 'f')
			->leftJoin('f', 'bookmarks_tree', 't', $qb->expr()->eq('t.id', 'f.id'))
			->leftJoin('f', 'bookmarks_root_folders', 'r', $qb->expr()->eq('r.folder_id', 'f.id'))
			->where($qb->expr()->isNull('t.id'))
			->andWhere($qb->expr()->isNull('r.folder_id'))
			->andWhere($qb->expr()->eq('t.type', $qb->createPositionalParameter('folder')));
		$orphanedFolders = $qb->execute();
		$i = 0;
		while ($folder = $orphanedFolders->fetchColumn()) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('bookmarks_folders')
				->where($qb->expr()->eq('id', $qb->createPositionalParameter($folder, IQueryBuilder::PARAM_INT)))
				->execute();
			$i++;
		}
		$output->info("Removed $i orphaned bookmark folders");

		$qb = $this->db->getQueryBuilder();
		$qb->select('b.id', 'b.user_id')
			->from('bookmarks', 'b')
			->leftJoin('b', 'bookmarks_tree', 't', 'b.id = t.id AND t.type = '.$qb->createPositionalParameter('bookmark'))
			->where($qb->expr()->isNull('t.id'));
		$orphanedBookmarks = $qb->execute();
		$i = 0;
		while ($bookmark = $orphanedBookmarks->fetch()) {
			$rootFolder = $this->ensureRootFolder($bookmark['user_id']);
			$qb = $this->db->getQueryBuilder();
			$qb->insert('bookmarks_tree')->values([
				'id' => $qb->createPositionalParameter($bookmark['id']),
				'type' => $qb->createPositionalParameter('bookmark'),
				'parent_folder' => $qb->createPositionalParameter($rootFolder['folder_id']),
				'index' => $qb->createPositionalParameter($rootFolder['count']),
			])->execute();
			$i++;
		}
		$output->info("Reinserted $i orphaned bookmarks");
	}

	private function ensureRootFolder($userId) {
		$qb = $this->db->getQueryBuilder();
		$rootFolder = $qb->select('r.folder_id', $qb->func()->count('t.id', 'count'))
			->from('bookmarks_root_folders', 'r')
			->innerJoin('r', 'bookmarks_folders', 'f', 'r.folder_id = f.id')
			->leftJoin('r', 'bookmarks_tree', 't', 't.parent_folder = r.folder_id')
			->where($qb->expr()->eq('r.user_id', $qb->createNamedParameter($userId)))
			->groupBy(['r.folder_id'])
			->execute()
			->fetch();
		if ($rootFolder === null || $rootFolder === false || $rootFolder['folder_id'] === null) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert('bookmarks_folders')
				->values([
					'user_id' => $qb->createNamedParameter($userId),
					'title' => $qb->createNamedParameter('')
				])
				->execute();
			$rootFolder = [
				'folder_id' => $qb->getLastInsertId(),
				'count' => 0
			];
			$qb = $this->db->getQueryBuilder();
			$oldRootFolder = $qb->select('folder_id')
				->from('bookmarks_root_folders')
				->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
				->execute()
				->fetch();
			if ($oldRootFolder) {
				$qb = $this->db->getQueryBuilder();
				$qb->update('bookmarks_root_folders')
					->set('folder_id', $qb->createNamedParameter($rootFolder['folder_id']))
					->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
					->execute();
			} else {
				$qb = $this->db->getQueryBuilder();
				$qb->insert('bookmarks_root_folders')
					->values([
						'folder_id' => $qb->createNamedParameter($rootFolder['folder_id']),
						'user_id' => $qb->createNamedParameter($userId)
					])
					->execute();
			}
		}
		return $rootFolder;
	}
}
