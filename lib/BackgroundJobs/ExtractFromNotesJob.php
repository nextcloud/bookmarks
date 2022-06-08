<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\BackgroundJobs;

use OCA\Bookmarks\Service\NotesService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class ExtractFromNotesJob extends TimedJob {
	public const INTERVAL = 5 * 60; // 5 minutes
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var NotesService
	 */
	private $notes;

	public function __construct(
		ITimeFactory $timeFactory, IUserManager $userManager, LoggerInterface $logger, NotesService $notes
	) {
		parent::__construct($timeFactory);

		$this->setInterval(self::INTERVAL);
		$this->userManager = $userManager;
		$this->logger = $logger;
		$this->notes = $notes;
	}

	protected function run($argument) {
		if (!$this->notes->isAvailable()) {
			return;
		}

		/**
		 * @var $users IUser[]
		 */
		$users = [];
		$this->userManager->callForSeenUsers(function (IUser $user) use (&$users) {
			$users[] = $user;
		});

		$processed = 0;
		do {
			$user = array_pop($users);
			if (!$user) {
				return;
			}
			try {
				$this->notes->extractBookmarksFromNotes($user);
				$processed++;
			} catch (\Exception|\Throwable $e) {
				$this->logger->debug('Extracting notes from user '.$user->getUID().' errored');
				$this->logger->debug($e->getMessage());
				continue;
			}
		} while (count($users) > 0);
	}
}
