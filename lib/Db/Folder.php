<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class Folder
 *
 * @package OCA\Bookmarks\Db
 * @method getTitle()
 * @method setTitle(string $title)
 * @method getUserId
 * @method setUserId(string $userId)
 * @method getDeleted
 * @method setDeleted(boolean $deleted)
 */
class Folder extends Entity {
	protected $title;
	protected $userId;
	protected $index;
	protected $deleted;

	public static $columns = ['id', 'title', 'user_id', 'deleted'];


	public function __construct() {
		// add types in constructor
		$this->addType('title', 'string');
		$this->addType('userId', 'string');
		$this->addType('deleted', 'boolean');
	}

	public function toArray(): array {
		return ['id' => $this->id, 'title' => $this->title, 'userId' => $this->userId, 'deleted' => $this->deleted];
	}
}
