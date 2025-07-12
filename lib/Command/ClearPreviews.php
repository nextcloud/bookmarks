<?php

/*
 * Copyright (c) 2022. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Command;

use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Service\FileCache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearPreviews extends Command {
	public function __construct(
		private FileCache $fileCache,
		private BookmarkMapper $bookmarkMapper,
	) {
		parent::__construct();
	}

	/**
	 * Configure the command
	 *
	 * @return void
	 */
	protected function configure() {
		$this->setName('bookmarks:clear-previews')
			->setDescription('Clear all cached bookmarks previews so that they have to be regenerated');
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$this->fileCache->clear();
			$this->bookmarkMapper->clearLastPreviews();
		} catch (\Exception $ex) {
			$output->writeln('<error>Failed to clear previews</error>');
			$output->writeln($ex->getMessage());
			return 1;
		}

		return 0;
	}
}
