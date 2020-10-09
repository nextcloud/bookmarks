<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Migration;

use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class UninstallRepairStep implements IRepairStep {
	/**
	 * @var IDBConnection
	 */
	private $db;

	private $tables = [
		'bookmarks',
		'bookmarks_tags',
		'bookmarks_folders',
		'bookmarks_root_folders',
		'bookmarks_shared_folders',
		'bookmarks_folders_public',
		'bookmarks_shares',
		'bookmarks_tree',
	];

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	/**
	 * Returns the step's name
	 */
	public function getName() {
		return 'Uninstall routine';
	}

	/**
	 * @param IOutput $output
	 */
	public function run(IOutput $output) {
		foreach ($this->tables as $table) {
			$query = $this->db->prepare("DROP TABLE *PREFIX*$table");
			$query->execute();
		}
		$query = $this->db->prepare("DELETE from *PREFIX*migrations WHERE app = 'bookmarks'");
		$query->execute();
	}
}
