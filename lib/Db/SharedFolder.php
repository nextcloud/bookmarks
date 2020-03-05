<?php

namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class SharedFolder
 *
 * @package OCA\Bookmarks\Db
 *
 * @method getShareId
 * @method setShareId(int $shareId)
 * @method getUserId
 * @method setUserId(string $userId)
 * @method getTitle
 * @method setTitle(string $title)
 */
class SharedFolder extends Entity {
	protected $shareId;
	protected $parentFolder;
	protected $userId;
	protected $title;
	protected $index;

	public static $columns = ['id', 'share_id', 'user_id', 'title'];

	public function __construct() {
		// add types in constructor
		$this->addType('id', 'integer');
		$this->addType('shareId', 'integer');
		$this->addType('userId', 'string');
		$this->addType('title', 'string');
	}

	public function toArray() {
		return ['title' => $this->title, 'userId' => $this->userId, 'shareId' => $this->shareId];
	}
}
