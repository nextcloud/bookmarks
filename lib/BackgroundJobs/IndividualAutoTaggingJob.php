<?php
/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\BackgroundJobs;

use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\TagMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\PreConditionNotMetException;
use OCP\TextProcessing\FreePromptTaskType;
use OCP\TextProcessing\IManager;
use OCP\TextProcessing\Task;
use OCP\TextProcessing\TopicsTaskType;

class IndividualAutoTaggingJob extends QueuedJob {
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
			$bookmark = $this->bookmarkMapper->find($argument);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return;
		}
		$allTags = $this->tagMapper->findByBookmark($bookmark->getId());

		try {
			$topics = $this->textProcessing->runTask(new Task(
				FreePromptTaskType::class,
				"You are an AI that generates content tags for a website bookmark collection. " .
				"Output only the tags separated by commas, nothing else, no explanation, no other punctuation, no emojis." .
				"Generate only up to four tags. Choose them so that it's likely that other websites will have those tags as well. For example, good tags are 'Health, Software, Development, DIY', bad tags are 'fix, new, following, joined'.\n" .
				( count($allTags) > 0 ? "If possible refrain from creating new tags and instead choose your tags for the website from these already existing tags: " . implode(', ', $allTags) . "\n\n" : "" ) .
				"Now, choose or generate tags for the website with the following description: \n========\n\n" . '  ' . $bookmark->getTitle() . "\n\n" . $bookmark->getDescription() . "\n\n" . $bookmark->getTextContent() . "\n\n========\n\nHere go your tags, separated by commas: ",
				'bookmarks',
				$bookmark->getUserId()
			));
			print($topics);
			$tags = array_map(fn ($string) => trim($string), explode(',', $topics));
			$this->tagMapper->addTo($tags, $bookmark->getId());
		} catch (PreConditionNotMetException) {
			// Task type not available anymore, so we abort
			return;
		}
	}
}
