<?php
/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\BackgroundJobs;

use OCA\Bookmarks\Db\TreeMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class EmptyTrashbinJob extends TimedJob {
	public const BATCH_SIZE = 1000; // 40 items
	public const INTERVAL = 5 * 60; // 5 minutes
	public const TRASHBIN_TTL = 2 * 4 * 4 * 7 * 24 * 60 * 60; // Two months


	public function __construct(
		ITimeFactory $timeFactory,
		private TreeMapper $treeMapper,
	) {
		parent::__construct($timeFactory);

		$this->setInterval(self::INTERVAL);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run($argument) {
		$this->treeMapper->deleteOldTrashbinItems(self::BATCH_SIZE, self::TRASHBIN_TTL);
	}
}
