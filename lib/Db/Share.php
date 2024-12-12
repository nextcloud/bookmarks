<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Db;

use OCA\Bookmarks\Service\CirclesService;
use OCP\AppFramework\Db\Entity;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IShare;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class Share
 *
 * @package OCA\Bookmarks\Db
 *
 * @method int getFolderId()
 * @method setFolderId(int $folderId)
 * @method string getOwner()
 * @method setOwner(string $owner)
 * @method string getParticipant
 * @method setParticipant(string $participant)
 * @method string getType()
 * @method setType(string $type)
 * @method bool getCanWrite()
 * @method setCanWrite(bool $canWrite)
 * @method bool getCanShare()
 * @method setCanShare(bool $canShare)
 * @method int getCreatedAt()
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

	/**
	 * @return array
	 *
	 * @psalm-return array{id: mixed, folderId: mixed, owner: mixed, participant: mixed, type: mixed, canWrite: mixed, canShare: mixed, createdAt: mixed, participantDisplayName: string}
	 */
	public function toArray(): array {
		return [
			'id' => $this->id,
			'folderId' => $this->folderId,
			'owner' => $this->owner,
			'participant' => $this->participant,
			'type' => $this->type,
			'canWrite' => $this->canWrite,
			'canShare' => $this->canShare,
			'createdAt' => $this->createdAt,
			'participantDisplayName' => self::getParticipantDisplayName($this->type, $this->participant),
		];
	}

	private static function getParticipantDisplayName($type, $participant) {
		try {
			if ($type === IShare::TYPE_USER) {
				return \OCP\Server::get(IUserManager::class)->get($participant)->getDisplayName();
			}
			if ($type === IShare::TYPE_GROUP) {
				return \OCP\Server::get(IGroupManager::class)->get($participant)->getDisplayName();
			}
			if ($type === IShare::TYPE_CIRCLE) {
				return \OCP\Server::get(CirclesService::class)->getCircle($participant)->getName();
			}
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			return $participant;
		}
	}
}
