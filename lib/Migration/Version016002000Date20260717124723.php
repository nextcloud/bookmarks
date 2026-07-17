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

/**
 * Recovery migration for instances where the url_hash migration
 * (Version016002000Date20260201124723) was recorded in oc_migrations but never
 * actually applied to the schema — e.g. an upgrade that failed in
 * postSchemaChange on a unique-constraint violation and was then unstuck by a
 * DB snapshot restore or by manually marking the version as executed.
 * See https://github.com/nextcloud/bookmarks/issues/2475 and #2374.
 *
 * The original migration guards column creation with a `hasColumn` check and,
 * being recorded as executed, never runs again — so there is otherwise no code
 * path that will ever create the column. This migration re-establishes the
 * `url_hash` column and backfills it. The unique index is added by the
 * following migration (Version016002000Date20260717134723), which must run
 * only after duplicates have been merged and hashes written here, so that the
 * index cannot fail on colliding hashes.
 *
 * All steps are idempotent: on a healthy instance the column already exists,
 * there are no NULL hashes to backfill, and this migration is effectively a
 * no-op.
 */
class Version016002000Date20260717124723 extends Version016002000Date20260218124723 {
	public function __construct(
		private IDBConnection $db,
	) {
		parent::__construct($db);
	}

	/**
	 * @throws SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if ($schema->hasTable('bookmarks')) {
			$table = $schema->getTable('bookmarks');
			if (!$table->hasColumn('url_hash')) {
				$output->info('Recovering missing url_hash column on the bookmarks table');
				$table->addColumn('url_hash', 'string', [
					'notnull' => false,
					'length' => 32,
				]);
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		// Only do any work if there are bookmarks without a hash. On healthy
		// instances this short-circuits before the (expensive) dedup scan.
		$countQb = $this->db->getQueryBuilder();
		$countQb->select($countQb->func()->count('id'))
			->from('bookmarks')
			->where($countQb->expr()->isNull('url_hash'));
		$result = $countQb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();

		if ($count === 0) {
			return;
		}

		// Merge per-user duplicate URLs before hashing, otherwise duplicates
		// would produce colliding (user_id, url_hash) pairs and break the
		// unique index added by the next migration.
		$this->deduplicateAll($output);

		$output->info('Hashing URLs of n=' . $count . ' bookmarks');
		$output->startProgress($count);

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'url')
			->from('bookmarks')
			->where($qb->expr()->isNull('url_hash'));
		$result = $qb->executeQuery();

		$setQb = $this->db->getQueryBuilder();
		$setQb->update('bookmarks')
			->set('url_hash', $setQb->createParameter('url_hash'))
			->where($setQb->expr()->eq('id', $setQb->createParameter('id')));

		$i = 1;
		$this->db->beginTransaction();
		try {
			while ($row = $result->fetch()) {
				if ($i++ % 1000 === 0) {
					$this->db->commit();
					$this->db->beginTransaction();
				}
				$setQb->setParameter('url_hash', hash('xxh128', $row['url']));
				$setQb->setParameter('id', $row['id']);
				$setQb->executeStatement();
				$output->advance();
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
		$output->finishProgress();
		$result->closeCursor();
	}
}
