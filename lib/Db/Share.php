<?php
namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\Entity;

class Share extends Entity {
	protected $folderId;
	protected $owner;
	protected $participant;
	protected $type;
	protected $canWrite;
	protected $canShare;
	protected $createdAt;

	public static $columns = ['id', 'folder_id', 'owner', 'participant', 'type', 'can_write', 'can_share', 'created_at'];

	public function __construct() {
		// add types in constructor
		$this->addType('folderId', 'integer');
		$this->addType('parentId', 'integer');
		$this->addType('owner', 'string');
		$this->addType('type', 'integer');
		$this->addType('participant', 'string');
		$this->addType('canWrite', 'boolean');
		$this->addType('canShare', 'boolean');
		$this->addType('createdAt', 'integer');
	}
}
