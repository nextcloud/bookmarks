<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Migration;

use Closure;
use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version011002000Date20220705124721 extends SimpleMigrationStep {
	private $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
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
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if ($schema->hasTable('bookmarks_trash')) {
			$table = $schema->createTable('bookmarks_trash');
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
			$table->addColumn('deleted_at', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->setPrimaryKey(['id', 'type', 'parent_folder'], 'bookmarks_trash_pk');
			$table->addIndex(['parent_folder'], 'bookmarks_trash_parent');
			$table->addIndex(['user_id', 'deleted_at'], 'bookmarks_trash_user');
		}

		return $schema;
	}
}
