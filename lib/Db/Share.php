<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class Share
 *
 * @package OCA\Bookmarks\Db
 *
 * @method getFolderId
 * @method setFolderId(int $folderId)
 * @method getOwner
 * @method setOwner(string $owner)
 * @method getParticipant
 * @method setParticipant(string $participant)
 * @method getType
 * @method setType(string $type)
 * @method getCanWrite
 * @method setCanWrite(bool $canWrite)
 * @method getCanShare
 * @method setCanShare(bool $canShare)
 * @method getCreatedAt
 * @method setCreatedAt(int $createdAt)
 */
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

	public function toArray(): array {
		return ['id' => $this->id, 'folderId' => $this->folderId, 'owner' => $this->owner, 'participant' => $this->participant, 'type' => $this->type, 'canWrite' => $this->canWrite, 'canShare' => $this->canShare, 'createdAt' => $this->createdAt];
	}
}
