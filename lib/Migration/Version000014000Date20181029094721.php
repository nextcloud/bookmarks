<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use PDO;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000014000Date20181029094721 extends SimpleMigrationStep {
	private $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return void
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('bookmarks_folders');
		$table->addColumn('index', 'bigint', [
			'notnull' => true,
			'length' => 64,
			'default' => 0,
		]);
		$table = $schema->getTable('bookmarks_folders_bookmarks');
		$table->addColumn('index', 'bigint', [
			'notnull' => true,
			'length' => 64,
			'default' => 0,
		]);
		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return void
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		$query = $this->db->getQueryBuilder();
		$query->select('id')->from('bookmarks_folders');
		$folders = $query->execute()->fetchAll(PDO::FETCH_COLUMN);
		$folders[] = -1;
		foreach ($folders as $folder) {
			$qb = $this->db->getQueryBuilder();
			$qb
				->select('id', 'title', 'parent_folder')
				->from('bookmarks_folders')
				->where($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folder)))
				->orderBy('title', 'DESC');
			$childFolders = $qb->execute()->fetchAll();

			$qb = $this->db->getQueryBuilder();
			$qb
				->select('bookmark_id')
				->from('bookmarks_folders_bookmarks')
				->where($qb->expr()->eq('folder_id', $qb->createPositionalParameter($folder)));
			$childBookmarks = $qb->execute()->fetchAll();

			$children = array_merge($childFolders, $childBookmarks);
			$children = array_map(static function ($child) {
				return $child['bookmark_id'] ?
					['type' => 'bookmark', 'id' => $child['bookmark_id']]
					: ['type' => 'folder', 'id' => $child['id']];
			}, $children);
			if (count($children) > 0) {
				continue;
			}

			foreach ($children as $i => $child) {
				if ($child['type'] === 'bookmark') {
					$qb = $this->db->getQueryBuilder();
					$qb
						->update('bookmarks_folders_bookmarks')
						->set('index', $qb->createPositionalParameter($i))
						->where($qb->expr()->eq('bookmark_id', $qb->createPositionalParameter($child['id'])))
						->andWhere($qb->expr()->eq('folder_id', $qb->createPositionalParameter($folder)));
					$qb->execute();
				} else {
					$qb = $this->db->getQueryBuilder();
					$qb
						->update('bookmarks_folders')
						->set('index', $qb->createPositionalParameter($i))
						->where($qb->expr()->eq('id', $qb->createPositionalParameter($child['id'])))
						->andWhere($qb->expr()->eq('parent_folder', $qb->createPositionalParameter($folder)));
					$qb->execute();
				}
			}
		}
	}
}
