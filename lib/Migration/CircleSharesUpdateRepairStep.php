<?php

/*
 * Copyright (c) 2026. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Migration;

use OCA\Bookmarks\Db\ShareMapper;
use OCA\Bookmarks\Service\CirclesService;
use OCA\Bookmarks\Service\FolderService;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Share\IShare;
use PDO;
use Throwable;

/**
 * Brings circle shares in line with the members of their circles. Earlier versions didn't add the
 * members of a circle to a share with that circle, and didn't react to circle membership changes.
 */
class CircleSharesUpdateRepairStep implements IRepairStep {
	public function __construct(
		private IDBConnection $db,
		private FolderService $folders,
		private ShareMapper $shareMapper,
		private CirclesService $circlesService,
	) {
	}

	public function getName() {
		return 'Update bookmark circle shares';
	}

	public function run(IOutput $output) {
		if (!$this->circlesService->isCirclesEnabled()) {
			$output->info('Circles app is not enabled, skipping');
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$shareIds = $qb->select('id')
			->from('bookmarks_shares')
			->where($qb->expr()->eq('type', $qb->createPositionalParameter(IShare::TYPE_CIRCLE)))
			->executeQuery()
			->fetchAll(PDO::FETCH_COLUMN);

		$added = 0;
		$removed = 0;
		$deleted = 0;
		foreach ($shareIds as $shareId) {
			try {
				$result = $this->folders->syncShareParticipants($this->shareMapper->find((int)$shareId));
			} catch (Throwable $e) {
				$output->warning('Could not update circle share ' . $shareId . ': ' . $e->getMessage());
				continue;
			}
			$added += $result['added'];
			$removed += $result['removed'];
			$deleted += $result['deleted'] ? 1 : 0;
		}
		$output->info("Added $added and removed $removed users in " . count($shareIds) . " circle shares, deleted $deleted shares of circles that don't exist anymore");
	}
}
