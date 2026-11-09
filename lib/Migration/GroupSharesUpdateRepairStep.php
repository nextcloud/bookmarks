<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Migration;

use OCA\Bookmarks\Db\ShareMapper;
use OCA\Bookmarks\Service\FolderService;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Share\IShare;
use PDO;
use Throwable;

class GroupSharesUpdateRepairStep implements IRepairStep {
	public function __construct(
		private IDBConnection $db,
		private FolderService $folders,
		private ShareMapper $shareMapper,
	) {
	}

	/**
	 * Returns the step's name
	 *
	 * @return string
	 */
	public function getName() {
		return 'Update bookmark group shares';
	}

	/**
	 * @param IOutput $output
	 *
	 * @return void
	 */
	public function run(IOutput $output) {
		$qb = $this->db->getQueryBuilder();
		$shareIds = $qb->select('id')
			->from('bookmarks_shares')
			->where($qb->expr()->eq('type', $qb->createPositionalParameter(IShare::TYPE_GROUP)))
			->executeQuery()
			->fetchAll(PDO::FETCH_COLUMN);

		$added = 0;
		$removed = 0;
		$deleted = 0;
		foreach ($shareIds as $shareId) {
			try {
				$result = $this->folders->syncShareParticipants($this->shareMapper->find((int)$shareId));
			} catch (Throwable $e) {
				$output->warning('Could not update group share ' . $shareId . ': ' . $e->getMessage());
				continue;
			}
			$added += $result['added'];
			$removed += $result['removed'];
			$deleted += $result['deleted'] ? 1 : 0;
		}
		$output->info("Removed $removed shared folders and added $added shared folders");
		$output->info("Removed $deleted shares");
	}
}
