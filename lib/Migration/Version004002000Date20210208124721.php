<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Migration;

use Closure;
use Doctrine\DBAL\Schema\SchemaException;
use OCA\Bookmarks\Db\Types;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version004002000Date20210208124721 extends SimpleMigrationStep {
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
	 *
	 * @throws SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		// Add text content columns
		if ($schema->hasTable('bookmarks')) {
			$table = $schema->getTable('bookmarks');
			$table->addColumn('html_content', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('text_content', Types::TEXT, [
				'notnull' => false,
			]);
		}

		// Ensure all tables have primary keys
		if ($schema->hasTable('bookmarks_tags')) {
			$table = $schema->getTable('bookmarks_tags');
			$table->setPrimaryKey(['bookmark_id', 'tag']);
		}
		if ($schema->hasTable('bookmarks_shared_to_shares')) {
			$table = $schema->getTable('bookmarks_shared_to_shares');
			if (!$table->hasPrimaryKey()) {
				if ($table->hasIndex('bookmarks_shares_to_shares')) {
					$table->dropIndex('bookmarks_shares_to_shares');
				}
				$table->setPrimaryKey(['shared_folder_id'], 'bookmarks_shared_to_shares');
			}
		}

		// Ensure boolean columns are nullable
		$this->ensureColumnIsNullable($schema, 'bookmarks', 'available');
		$this->ensureColumnIsNullable($schema, 'bookmarks_shares', 'can_share');
		$this->ensureColumnIsNullable($schema, 'bookmarks_shares', 'can_write');

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
		// Reset last_preview of all bookmarks to trigger re-visiting them
		$qb = $this->db->getQueryBuilder();
		$qb->update('bookmarks')->set('last_preview', $qb->createPositionalParameter(0,IQueryBuilder::PARAM_INT))->execute();
	}

	protected function ensureColumnIsNullable(ISchemaWrapper $schema, string $tableName, string $columnName): bool {
		$table = $schema->getTable($tableName);
		$column = $table->getColumn($columnName);

		if ($column->getNotnull()) {
			$column->setNotnull(false);
			return true;
		}

		return false;
	}
}
