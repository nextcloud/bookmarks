<?php

namespace OCA\Bookmarks\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version003000000Date20191123094721 extends SimpleMigrationStep {
	private $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @throws \Doctrine\DBAL\Schema\SchemaException
	 */
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('bookmarks');
		$table->dropColumn('public');
		if (!$schema->hasTable('bookmarks_root_folders')) {
			$table = $schema->createTable('bookmarks_root_folders');
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('folder_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->setPrimaryKey(['user_id', 'folder_id']);
			$table->addIndex(['folder_id'], 'bookmarks_root_folder');
			$table->addIndex(['user_id'], 'bookmarks_user_root');
		}
		if (!$schema->hasTable('bookmarks_tree')) {
			$table = $schema->createTable('bookmarks_tree');
			$table->addColumn('type', 'string', [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('parent_folder', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('index', 'bigint', [
				'notnull' => true,
				'unsigned' => true
			]);
			$table->setPrimaryKey(['id', 'type', 'parent_folder'], 'bookmarks_tree_pk');
			$table->addIndex(['parent_folder'], 'bookmarks_tree_parent');
			$table->addIndex(['parent_folder', 'index'], 'bookmarks_tree_parent_i');
		}
		if (!$schema->hasTable('bookmarks_folders_public')) {
			$table = $schema->createTable('bookmarks_folders_public');
			$table->addColumn('id', 'string', [
				'notnull' => true,
				'length' => 32,
			]);
			$table->addColumn('folder_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('description', 'string', [
				'notnull' => true,
				'length' => 4096,
				'default' => '',
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => false,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id'], 'bookmarks_public_id');
			$table->addIndex(['folder_id'], 'bookmarks_public_folder_id');
			$table->addIndex(['created_at'], 'bookmarks_public_created_at');
		}
		if (!$schema->hasTable('bookmarks_shares')) {
			$table = $schema->createTable('bookmarks_shares');
			$table->addColumn('id', 'bigint', [
				'notnull' => true,
				'length' => 64,
				'autoincrement' => true,
			]);
			$table->addColumn('folder_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('owner', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('participant', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('type', 'integer', [
				'notnull' => false,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => false,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('can_write', 'boolean', [
				'notnull' => true,
				'default' => false,
			]);
			$table->addColumn('can_share', 'boolean', [
				'notnull' => true,
				'default' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['created_at'], 'bookmarks_share_created_at');
			$table->addIndex(['folder_id'], 'bookmarks_share_folder_id');
			$table->addIndex(['owner'], 'bookmarks_share_owner');
			$table->addIndex(['participant', 'type'], 'bookmarks_share_part');
		}
		if (!$schema->hasTable('bookmarks_shared_folders')) {
			$table = $schema->createTable('bookmarks_shared_folders');
			$table->addColumn('id', 'bigint', [
				'notnull' => true,
				'length' => 64,
				'autoincrement' => true,
			]);
			$table->addColumn('share_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
			]);
			$table->addColumn('title', 'string', [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id'], 'bookmarks_shared_id');
			$table->addIndex(['user_id'], 'bookmarks_shared_user');
			$table->addIndex(['share_id'], 'bookmarks_shared_share');
		}
		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('user_id')->from('bookmarks');
		$usersQuery = $qb->execute();
		while ($user = $usersQuery->fetchColumn()) {
			$qb = $this->db->getQueryBuilder();
			$rootFolderId = $qb->select('folder_id')
				->from('bookmarks_root_folders')
				->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($user)))
				->execute()
				->fetchColumn();
			if ($rootFolderId === false) {
				// Create root folders
				$qb = $this->db->getQueryBuilder();
				$qb->insert('bookmarks_folders')->values([
					'title' => $qb->createPositionalParameter(''),
					'user_id' => $qb->createPositionalParameter($user),
				]);
				$qb->execute();
				$qb = $this->db->getQueryBuilder();
				$rootFolderId = $this->db->lastInsertId();
				$qb->insert('bookmarks_root_folders')->values([
					'folder_id' => $qb->createPositionalParameter($rootFolderId),
					'user_id' => $qb->createPositionalParameter($user),
				]);
				$qb->execute();
			}

			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'parent_folder', 'index')
				->from('bookmarks_folders')
				->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($user)));
			$foldersQuery = $qb->execute();
			while ($folder = $foldersQuery->fetch()) {
				if ((string)$folder['id'] === (string)$rootFolderId || $folder['parent_folder'] === null) {
					continue;
				}
				$qb = $this->db->getQueryBuilder();
				$folderId = $qb->select('id')
					->from('bookmarks_tree')
					->where(
						$qb->expr()->eq('id', $qb->createPositionalParameter($folder['id'])),
						$qb->expr()->eq('type', $qb->createPositionalParameter('folder'))
					)
					->execute()
					->fetchColumn();
				if ($folderId === false) {
					$qb = $this->db->getQueryBuilder();
					$qb->insert('bookmarks_tree')
						->values([
							'id' => $qb->createPositionalParameter($folder['id']),
							'type' => $qb->createPositionalParameter('folder'),
							'parent_folder' => $qb->createPositionalParameter(($folder['parent_folder'] === '-1' || $folder['parent_folder'] === -1) ? $rootFolderId : $folder['parent_folder']),
							'index' => $qb->createPositionalParameter($folder['index']),
						])->execute();
				}
			}
			$qb = $this->db->getQueryBuilder();
			$qb->select('f.bookmark_id', 'f.folder_id', 'f.index')
				->from('bookmarks_folders_bookmarks', 'f')
				->leftJoin('f', 'bookmarks', 'b', $qb->expr()->eq('b.id', 'f.bookmark_id'))
				->where($qb->expr()->eq('b.user_id', $qb->createPositionalParameter($user)));
			$bookmarksQuery = $qb->execute();
			while ($bookmark = $bookmarksQuery->fetch()) {
				$qb = $this->db->getQueryBuilder();
				$parentFolder = ($bookmark['folder_id'] === '-1' || $bookmark['folder_id'] === -1) ? $rootFolderId : $bookmark['folder_id'];
				$bookmarkId = $qb->select('id')
					->from('bookmarks_tree')
					->where(
						$qb->expr()->eq('id', $qb->createPositionalParameter($bookmark['bookmark_id'])),
						$qb->expr()->eq('type', $qb->createPositionalParameter('bookmark')),
						$qb->expr()->eq('parent_folder', $qb->createPositionalParameter($parentFolder))
					)
					->execute()
					->fetchColumn();
				if ($bookmarkId === false) {
					$qb = $this->db->getQueryBuilder();
					$qb->insert('bookmarks_tree')->values([
						'id' => $qb->createPositionalParameter($bookmark['bookmark_id']),
						'type' => $qb->createPositionalParameter('bookmark'),
						'parent_folder' => $qb->createPositionalParameter($parentFolder),
						'index' => $qb->createPositionalParameter($bookmark['index']),
					])->execute();
				}
			}
		}
	}
}
