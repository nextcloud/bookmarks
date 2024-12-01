<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Migration;

use Closure;
use Doctrine\DBAL\Schema\SchemaException;
use OCA\Bookmarks\Db\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version014001000Date20240524124721 extends SimpleMigrationStep {
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
		if ($schema->hasTable('bookmarks_tree')) {
			$table = $schema->getTable('bookmarks_tree');
			$table->addColumn('soft_deleted_at', Types::DATETIME, ['notnull' => false]);
			$table->addIndex(['soft_deleted_at', 'id', 'type', 'parent_folder'], 'bookmarks_tree_deleted');
		}

		return $schema;
	}

}
