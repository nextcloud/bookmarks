<?php

/*
 * Copyright (c) 2025. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks;

use OCP\Capabilities\IPublicCapability;

class Capabilities implements IPublicCapability {
	public function getCapabilities() {
		return [
			'bookmarks' => [
				'javascript-bookmarks' => true,
				'hash-functions' => ['xxh32', 'murmur3a', 'sha256'], // ordered by preference
			]
		];
	}
}
