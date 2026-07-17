<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
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
 * Recovery migration (part 2) for instances that lost the url_hash unique
 * index. See Version016002000Date20260717124723 and
 * https://github.com/nextcloud/bookmarks/issues/2475.
 *
 * Runs after the column has been (re-)created and hashes have been backfilled
 * and de-duplicated, so the unique index cannot fail on colliding hashes.
 * Idempotent: on a healthy instance the index already exists and this is a
 * no-op.
 */
class Version016002000Date20260717134723 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * @throws SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if ($schema->hasTable('bookmarks')) {
			$table = $schema->getTable('bookmarks');
			if ($table->hasColumn('url_hash') && !$table->hasIndex('bookmarks_uniq_url')) {
				$output->info('Recovering missing bookmarks_uniq_url unique index');
				$table->addUniqueIndex(['user_id', 'url_hash'], 'bookmarks_uniq_url');
			}
		}

		return $schema;
	}
}
