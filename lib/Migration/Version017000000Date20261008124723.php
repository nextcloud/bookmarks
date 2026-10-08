<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Schema\SchemaException;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\Share\IShare;

/**
 * A user who is covered by several shares of the same folder (e.g. directly and through a group)
 * has a single shared folder that is linked to all of these shares.
 */
class Version017000000Date20261008124723 extends SimpleMigrationStep {
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
		if ($schema->hasTable('bookmarks_shared_to_shares')) {
			$table = $schema->getTable('bookmarks_shared_to_shares');
			$primaryKey = $table->getPrimaryKey();
			if ($primaryKey === null || $primaryKey->getColumns() !== ['shared_folder_id', 'share_id']) {
				if ($primaryKey !== null) {
					$table->dropPrimaryKey();
				}
				// Keep the default name: Doctrine drops a changed primary key by the new key's name,
				// which only resolves to the existing key on MySQL and Postgres if it is "primary"
				$table->setPrimaryKey(['shared_folder_id', 'share_id']);
			}
		}
		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		// Users who were given a folder directly that they already had through a group or circle
		// didn't get their shared folder linked to the user share
		$qb = $this->db->getQueryBuilder();
		$qb->select('s.id')
			->selectAlias('sf.id', 'shared_folder_id')
			->from('bookmarks_shares', 's')
			->innerJoin('s', 'bookmarks_shared_folders', 'sf', $qb->expr()->andX(
				$qb->expr()->eq('sf.folder_id', 's.folder_id'),
				$qb->expr()->eq('sf.user_id', 's.participant'),
			))
			->leftJoin('sf', 'bookmarks_shared_to_shares', 't', $qb->expr()->andX(
				$qb->expr()->eq('t.shared_folder_id', 'sf.id'),
				$qb->expr()->eq('t.share_id', 's.id'),
			))
			->where($qb->expr()->eq('s.type', $qb->createPositionalParameter(IShare::TYPE_USER, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('t.share_id'));
		$result = $qb->executeQuery();
		$i = 0;
		while ($row = $result->fetch()) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert('bookmarks_shared_to_shares')->values([
				'shared_folder_id' => $qb->createPositionalParameter((int)$row['shared_folder_id'], IQueryBuilder::PARAM_INT),
				'share_id' => $qb->createPositionalParameter((int)$row['id'], IQueryBuilder::PARAM_INT),
			])->executeStatement();
			$i++;
		}
		$result->closeCursor();
		$output->info("Linked $i shared folders to user shares");
	}
}
