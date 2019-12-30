<?php

namespace OCA\Bookmarks\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000014000Date20181002094721 extends SimpleMigrationStep {
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
	 */
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('bookmarks')) {
			$table = $schema->createTable('bookmarks');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('url', 'string', [
				'notnull' => true,
				'length' => 4096,
				'default' => '',
			]);
			$table->addColumn('title', 'string', [
				'notnull' => true,
				'length' => 4096,
				'default' => '',
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('description', 'string', [
				'notnull' => true,
				'length' => 4096,
				'default' => '',
			]);
			$table->addColumn('public', 'smallint', [
				'notnull' => false,
				'length' => 1,
				'default' => 0,
			]);
			$table->addColumn('added', 'integer', [
				'notnull' => false,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('lastmodified', 'integer', [
				'notnull' => false,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('clickcount', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('bookmarks_tags')) {
			$table = $schema->createTable('bookmarks_tags');
			$table->addColumn('bookmark_id', 'bigint', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('tag', 'string', [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->addUniqueIndex(['bookmark_id', 'tag'], 'bookmark_tag');
		}

		if (!$schema->hasTable('bookmarks_folders')) {
			$table = $schema->createTable('bookmarks_folders');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('parent_folder', 'bigint', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('title', 'string', [
				'notnull' => true,
				'length' => 4096,
				'default' => '',
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('bookmarks_folders_bookmarks')) {
			$table = $schema->createTable('bookmarks_folders_bookmarks');
			$table->addColumn('folder_id', 'bigint', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('bookmark_id', 'bigint', [
				'notnull' => false,
				'length' => 64,
			]);
		}
		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		$query = $this->db->getQueryBuilder();
		$query->select('id', 'user_id')->from('bookmarks');
		$bookmarks = $query->execute()->fetchAll();
		foreach ($bookmarks as $i => $bookmark) {
			$query = $this->db->getQueryBuilder();
			$query->select('bookmark_id')
				->from('bookmarks_folders_bookmarks')
				->where(
					$query->expr()->eq('bookmark_id', $query->createPositionalParameter($bookmark['id']))
				);
			if (count($query->execute()->fetchAll()) > 0) {
				continue;
			}
			$query = $this->db->getQueryBuilder();
			$query
				->insert('bookmarks_folders_bookmarks')
				->values([
					'folder_id' => $query->createNamedParameter(-1),
					'bookmark_id' => $query->createNamedParameter($bookmark['id']),
				]);
			$query->execute();
		}
	}
}
