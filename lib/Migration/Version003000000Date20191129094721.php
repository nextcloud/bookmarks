<?php
namespace OCA\Bookmarks\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
use OCP\IDBConnection;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version003000000Date20191129094721 extends SimpleMigrationStep {
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
		if (!$schema->hasTable('bookmarks_folders_shared')) {
			$table = $schema->createTable('bookmarks_folders_shared');
			$table->addColumn('folder_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('parent_folder', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('from_user', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('to_user', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => false,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('index', 'bigint', [
				'notnull' => true,
				'length' => 64,
				'default' => 0
			]);
			$table->addColumn('can_edit', 'boolean', [
				'notnull' => true,
				'default' => false
			]);
			$table->addColumn('can_reshare', 'boolean', [
				'notnull' => true,
				'default' => false
			]);
			$table->addIndex(['created_at'], 'bookmarks_shared_created_at');
			$table->addIndex(['folder_id'], 'bookmarks_shared_folder_id');
			$table->addIndex(['from_user'], 'bookmarks_shared_from_user');
			$table->addIndex(['to_user'], 'bookmarks_shared_to_user');
			$table->addIndex(['parent_folder'], 'bookmarks_shared_parent');
			$table->addIndex(['to_user', 'parent_folder'], 'bookmarks_shared_userparent');
			$table->addIndex(['parent_folder', 'index'], 'bookmarks_shared_parentidx');
			$table->addIndex(['to_user', 'parent_folder', 'index'], 'bookmarks_share_userparentidx');
		}
		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
	}
}
