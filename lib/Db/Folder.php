<?php
namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\Entity;

class Bookmark extends Entity {
	protected $parentFolder;
	protected $title;
	protected $userId;
	protected $index;


	public function __construct() {
		// add types in constructor
		$this->addType('parentFolder', 'integer');
		$this->addType('title', 'string');
		$this->addType('userId', 'integer');
		$this->addType('index', 'integer');
	}
}
