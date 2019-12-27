<?php
namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\Entity;

class SharedFolder extends Entity {
	protected $shareId;
	protected $parentFolder;
	protected $userId;
	protected $index;

	public static $columns = ['id', 'share_id', 'parent_folder', 'user_id'];

	public function __construct() {
		// add types in constructor
		$this->addType('id', 'integer');
		$this->addType('shareId', 'integer');
		$this->addType('parentFolder', 'integer');
		$this->addType('userId', 'string');
		$this->addType('index', 'integer');
	}

	public function toArray() {
		return ['title' => $this->title, 'parent_folder' => $this->parentFolder];
	}
}
