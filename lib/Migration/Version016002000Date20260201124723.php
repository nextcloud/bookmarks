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

class Version016002000Date20260201124723 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {

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
		if ($schema->hasTable('bookmarks')) {
			$table = $schema->getTable('bookmarks');
			if (!$table->hasColumn('url_hash')) {
				$table->addColumn('url_hash', 'string', [
					'notnull' => false,
					'length' => 32,
				]);
				$table->addUniqueIndex(['user_id', 'url_hash'], 'bookmarks_uniq_url');
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		$countQb = $this->db->getQueryBuilder();
		$countQb->select($countQb->func()->count('id'))->from('bookmarks')->where($countQb->expr()->isNull('url_hash'));
		$result = $countQb->executeQuery();
		$count = $result->fetchOne();
		$output->info('Hashing URLs of n=' . $count . ' bookmarks');
		$output->startProgress($count);

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'url')->from('bookmarks')->where($qb->expr()->isNull('url_hash'));
		$result = $qb->executeQuery();
		$setQb = $this->db->getQueryBuilder();
		$setQb->update('bookmarks')
			->set('url_hash', $qb->createParameter('url_hash'))
			->where($qb->expr()->eq('id', $qb->createParameter('id')));
		while ($row = $result->fetch()) {
			$setQb->setParameter('url_hash', md5($row['url']));
			$setQb->setParameter('id', $row['id']);
			$setQb->executeStatement();
			$output->advance();
		}
		$output->finishProgress();
		$result->closeCursor();
	}

}
