<?php

/*
 * Copyright (c) 2026. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\BackgroundJobs;

use OCA\Bookmarks\ContextChat\ContextChatProvider;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\ContextChat\IContentManager;

/**
 * Re-submits a set of bookmarks to context chat after the access to them changed,
 * e.g. because a folder containing them was moved, shared, unshared or deleted.
 */
class ContextChatUpdateJob extends QueuedJob {

	public function __construct(
		ITimeFactory $timeFactory,
		private ?IContentManager $contentManager,
		private ContextChatProvider $provider,
	) {
		parent::__construct($timeFactory);
	}

	protected function run($argument) {
		if ($this->contentManager === null || !$this->contentManager->isContextChatAvailable()) {
			return;
		}
		if (!isset($argument['bookmarks']) || !is_array($argument['bookmarks'])) {
			return;
		}
		$this->provider->updateBookmarks(
			array_map('intval', $argument['bookmarks']),
			(bool)($argument['accessMayShrink'] ?? true),
		);
	}
}
