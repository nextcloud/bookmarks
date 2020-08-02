<?php

namespace OCA\Bookmarks\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version003001000Date20200526094721 extends SimpleMigrationStep {
	private $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @throws \Doctrine\DBAL\Schema\SchemaException
	 */
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('bookmarks_shared_to_shares')) {
			$table = $schema->createTable('bookmarks_shared_to_shares');
			$table->addColumn('shared_folder_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('share_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->setPrimaryKey(['shared_folder_id', 'share_id'], 'bookmarks_shared_to_shares');
			$table->addIndex(['shared_folder_id'], 'bookmarks_shares_to_shares');
			$table->addIndex(['share_id'], 'bookmarks_share_to_shared');
		}
		$table = $schema->getTable('bookmarks_shared_folders');
		if (!$table->hasColumn('folder_id')) {
			$table->addColumn('folder_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
				'default' => 0
			]);
			$table->addIndex(['folder_id'], 'bookmarks_shared_folder');
		}
		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		$qb = $this->db->getQueryBuilder();
		// Find all shared folders
		$sharedFolders = $qb->select('sf.share_id', 'sf.id', 's.folder_id', 'sf.user_id')
			->from('bookmarks_shared_folders', 'sf')
			->leftJoin('sf', 'bookmarks_shares', 's', $qb->expr()->eq('sf.share_id', 's.id'))
			->execute();
		while ($sharedFolder = $sharedFolders->fetch()) {
			// Find a shared folder with folder_id already set. This is gonna be the only one we will have for this folder from now on.
			$qb = $this->db->getQueryBuilder();
			$canonicalSharedFolder = $qb->select('sf.id', 'sf.folder_id')
				->from('bookmarks_shared_folders', 'sf')
				->where($qb->expr()->eq('sf.folder_id', $qb->createPositionalParameter($sharedFolder['folder_id'])))
				->andWhere($qb->expr()->eq('sf.user_id', $qb->createPositionalParameter($sharedFolder['user_id'])))
				->execute()
				->fetch();
			if (!$canonicalSharedFolder) {
				// If there's no canonical shared folder, we make this one it.
				$qb = $this->db->getQueryBuilder();
				$qb->update('bookmarks_shared_folders')
					->set('folder_id', $qb->createPositionalParameter($sharedFolder['folder_id']))
					->where($qb->expr()->eq('id', $qb->createPositionalParameter($sharedFolder['id'])))
					->execute();
				$canonicalSharedFolder = $sharedFolder;
			} else {
				// ...otherwise delete this shared folder.
				$qb = $this->db->getQueryBuilder();
				$qb->delete('bookmarks_shared_folders')
					->where($qb->expr()->eq('id', $qb->createPositionalParameter($sharedFolder['id'])))
					->execute();
			}

			// Insert into pivot table
			$qb = $this->db->getQueryBuilder();
			$qb->insert('bookmarks_shared_to_shares')
				->values([
					'shared_folder_id' => $qb->createPositionalParameter($canonicalSharedFolder['id']),
					'share_id' => $qb->createPositionalParameter($sharedFolder['share_id'])
				])
				->execute();
		}
	}
}
