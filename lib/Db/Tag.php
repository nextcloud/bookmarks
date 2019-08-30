<?php
namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\Entity;

class Bookmark extends Entity {
	protected $bookmarkId;


	public function __construct() {
		// add types in constructor
		$this->addType('id', 'string');
		$this->addType('bookmarkId', 'integer');
	}

	// map attribute phoneNumber to the database column phonenumber
	public function columnToProperty($column) {
		if ($column === 'tag') {
			return 'id';
		} else {
			return parent::columnToProperty($column);
		}
	}

	public function propertyToColumn($property) {
		if ($property === 'id') {
			return 'tag';
		} else {
			return parent::propertyToColumn($property);
		}
	}
}
