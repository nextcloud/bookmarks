<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class SharedFolder
 *
 * @package OCA\Bookmarks\Db
 *
 * @method int getShareId()
 * @method setFolderId(int $shareId)
 * @method int getFolderId()
 * @method string getUserId()
 * @method setUserId(string $userId)
 * @method string getTitle()
 * @method setTitle(string $title)
 */
class SharedFolder extends Entity {
	protected $shareId;
	protected $userId;
	protected $title;
	protected $folderId;

	public static $columns = ['id', 'user_id', 'title', 'folder_id'];

	public function __construct() {
		// add types in constructor
		$this->addType('id', 'integer');
		$this->addType('userId', 'string');
		$this->addType('folderId', 'integer');
		$this->addType('title', 'string');
	}

	/**
	 * @return array
	 *
	 * @psalm-return array{title: mixed, userId: mixed}
	 */
	public function toArray(): array {
		return ['title' => $this->title, 'userId' => $this->userId];
	}
}
