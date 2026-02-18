<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Migration;

use Closure;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version016002000Date20260218134723 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {

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
			->set('url_hash', $setQb->createParameter('url_hash'))
			->where($setQb->expr()->eq('id', $setQb->createParameter('id')));
		$i = 1;
		$this->db->beginTransaction();
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
		$output->finishProgress();
		$result->closeCursor();
	}
}
