<?php

/*
 * Copyright (c) 2020-2025. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\BackgroundJobs;

use OCA\Bookmarks\AppInfo\Application;
use OCA\Bookmarks\ContextChat\ContextChatProvider;
use OCA\Bookmarks\Service\BookmarkService;
use OCA\ContextChat\Public\ContentItem;
use OCA\ContextChat\Public\ContentManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\IUserManager;

class ContextChatIndexJob extends QueuedJob {

	public function __construct(
		ITimeFactory $timeFactory,
		private BookmarkService $bookmarkService,
		private ?ContentManager $contentManager,
		private IUserManager $userManager,
		private ContextChatProvider $provider,
	) {
		parent::__construct($timeFactory);
	}

	protected function run($argument) {
		if ($this->contentManager === null) {
			return;
		}
		if (!isset($argument['user'])) {
			return;
		}
		$user = $this->userManager->get($argument['user']);
		if ($user === null) {
			return;
		}
		$items = [];
		foreach ($this->bookmarkService->getIterator($user->getUID()) as $bookmark) {
			$items[] = new ContentItem(
				(string)$bookmark->getId(),
				$this->provider->getId(),
				$bookmark->getTitle(),
				$bookmark->getTextContent(),
				'Website',
				new \DateTime('@' . $bookmark->getLastmodified()),
				[$user->getUID()]
			);
			if (count($items) < 25) {
				continue;
			}
			$this->contentManager->submitContent(Application::APP_ID, $items);
			$items = [];
		}
		if (count($items) > 0) {
			$this->contentManager->submitContent(Application::APP_ID, $items);
		}
	}
}
