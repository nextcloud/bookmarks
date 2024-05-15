<?php
/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\BackgroundJobs;

use OC\DB\Exceptions\DbalException;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\TagMapper;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\QueryParameters;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\DB\Exception;
use OCP\PreConditionNotMetException;
use OCP\TextProcessing\IManager;
use OCP\TextProcessing\Task;
use OCP\TextProcessing\TopicsTaskType;

class InitialAutoTaggingJob extends QueuedJob {
	public function __construct(
		private BookmarkMapper $bookmarkMapper,
		private TagMapper $tagMapper,
		ITimeFactory $timeFactory,
		private IManager $textProcessing,
	) {
		parent::__construct($timeFactory);
	}

	protected function run($argument) {
		if (!in_array(TopicsTaskType::class, $this->textProcessing->getAvailableTaskTypes())) {
			return;
		}

		try {
			$bookmarks = $this->bookmarkMapper->findAll($argument, new QueryParameters());
		} catch (UrlParseError|DbalException|Exception $e) {
			return;
		}

		foreach ($bookmarks as $bookmark) {
			try {
				$topics = $this->textProcessing->runTask(new Task(
					TopicsTaskType::class,
					$bookmark->getTitle() . '\n\n' . $bookmark->getDescription() . '\n\n' . $bookmark->getTextContent(),
					'bookmarks',
					$bookmark->getUserId()
				));
				$tags = array_map(fn ($string) => trim($string), explode(',', $topics));
				$this->tagMapper->addTo($tags, $bookmark->getId());
			} catch (PreConditionNotMetException) {
				// Task type not available anymore, so we abort
				return;
			}
		}
	}
}
