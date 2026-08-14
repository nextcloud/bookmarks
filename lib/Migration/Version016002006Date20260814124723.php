<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Migration;

use Closure;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Normalises the legacy `backup.enabled` user setting.
 */
class Version016002006Date20260814124723 extends SimpleMigrationStep {
	public function __construct(
		private IConfig $config,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		$userIds = $this->config->getUsersForUserValue('bookmarks', 'backup.enabled', (string)true);
		if (empty($userIds)) {
			return;
		}
		$output->info('Normalising legacy backup.enabled setting for n=' . count($userIds) . ' users');
		foreach ($userIds as $userId) {
			$this->config->setUserValue($userId, 'bookmarks', 'backup.enabled', 'true');
		}
	}
}
