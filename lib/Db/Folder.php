<?php
namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\Entity;

class Folder extends Entity {
	protected $parentFolder;
	protected $title;
	protected $userId;
	protected $index;


	public function __construct() {
		// add types in constructor
		$this->addType('parentFolder', 'integer');
		$this->addType('title', 'string');
		$this->addType('userId', 'string');
		$this->addType('index', 'integer');
	}

	public function toArray() {
		return ['id' => $this->id, 'title' => $this->title, 'parent_folder' => $this->parentFolder];
	}
}
