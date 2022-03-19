<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Migration;

use Closure;
use DateTime;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Types\Type;
use OCA\Bookmarks\Db\Types;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version010002000Date20220319124721 extends SimpleMigrationStep {
	private $db;
	/**
	 * @var mixed[]
	 */
	private $rootFolders;

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
		$qb = $this->db->getQueryBuilder();
		$this->rootFolders = $qb->select('user_id', 'locked')->from('bookmarks_root_folders')->executeQuery()->fetchAll();
		$qb = $this->db->getQueryBuilder();
		$qb->update('bookmarks_root_folders')->set('locked', $qb->createPositionalParameter(false, Types::BOOLEAN))->executeStatement();
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
		if ($schema->hasTable('bookmarks_root_folders')) {
			$table = $schema->getTable('bookmarks_root_folders');
			$table->changeColumn('locked', ['type' => Type::getType(Types::DATETIME)]);
		}

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
		foreach ($this->rootFolders as $rootFolder) {
			if ($rootFolder['locked'] != true) {
				continue;
			}
			$qb = $this->db->getQueryBuilder();
			$qb->update('bookmarks_root_folders')
				->set('locked', $qb->createPositionalParameter(new DateTime(), Types::DATETIME))
				->where($qb->expr()->eq('user_id', $qb->createPositionalParameter($rootFolder['user_id'])))
				->executeStatement();
		}
	}
}
