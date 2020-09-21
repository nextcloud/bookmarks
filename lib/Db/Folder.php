<?php

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
 */
class Folder extends Entity {
	protected $title;
	protected $userId;
	protected $index;

	public static $columns = ['id', 'title', 'user_id'];


	public function __construct() {
		// add types in constructor
		$this->addType('title', 'string');
		$this->addType('userId', 'string');
	}

	public function toArray(): array {
		return ['id' => $this->id, 'title' => $this->title, 'userId' => $this->userId];
	}
}
