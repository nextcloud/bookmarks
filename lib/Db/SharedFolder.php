<?php

namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class SharedFolder
 *
 * @package OCA\Bookmarks\Db
 *
 * @method getShareId
 * @method setFolderId(int $shareId)
 * @method getFolderId()
 * @method getUserId
 * @method setUserId(string $userId)
 * @method getTitle
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

	public function toArray(): array {
		return ['title' => $this->title, 'userId' => $this->userId];
	}
}
