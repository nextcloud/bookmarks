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

class Version015000005Date20241229124721 extends SimpleMigrationStep {
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
		if ($schema->hasTable('bookmarks')) {
			$table = $schema->getTable('bookmarks');
			$table->modifyColumn('url', [
				'length' => 4096,
			]);
			$table->modifyColumn('title', [
				'length' => 4096,
			]);
			$table->modifyColumn('description', [
				'length' => 4096,
			]);
		}

		return $schema;
	}

}
