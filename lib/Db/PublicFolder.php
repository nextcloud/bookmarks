<?php

namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\Entity;

class PublicFolder extends Entity {
	protected $folderId;
	protected $description;
	protected $createdAt;

	public static $columns = ['id', 'folder_id', 'description', 'created_at'];

	public function __construct() {
		// add types in constructor
		$this->addType('id', 'string');
		$this->addType('folderId', 'integer');
		$this->addType('description', 'integer');
		$this->addType('created_at', 'integer');
	}

	/*
	 * Overridden because of param type
	 */
	public function setId(string $id) {
		$this->id = $id;
		$this->markFieldUpdated('id');
	}
}
