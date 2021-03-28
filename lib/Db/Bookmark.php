<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class Bookmark
 *
 * @package OCA\Bookmarks\Db
 * @method string getUrl()
 * @method setUrl(string $url)
 * @method string getTitle()
 * @method string getDescription()
 * @method setDescription(string $description)
 * @method int getLastmodified()
 * @method setLastmodified(int $lastmodified)
 * @method int getAdded()
 * @method setAdded(int $added)
 * @method int getClickcount
 * @method setClickcount(int $count)
 * @method int getLastPreview()
 * @method setLastPreview(int $lastpreview)
 * @method bool getAvailable()
 * @method setAvailable(boolean $available)
 * @method getArchivedFile
 * @method int getArchivedFile()
 * @method setArchivedFile(int $fileId)
 * @method string getUserId()
 * @method setUserId(string $userId)
 */
class Bookmark extends Entity {
	protected $url;
	protected $title;
	protected $userId;
	protected $description;
	protected $public;
	protected $added;
	protected $lastmodified;
	protected $clickcount;
	protected $lastPreview;
	protected $available;
	protected $archivedFile;

	public static $columns = ['id', 'url', 'title', 'description', 'lastmodified', 'added', 'clickcount', 'last_preview', 'available', 'archived_file', 'user_id'];
	public static $fields = ['id', 'url', 'title', 'description', 'lastmodified', 'added', 'clickcount', 'lastPreview', 'available', 'archivedFile', 'userId'];

	public static function fromArray($props): self {
		$bookmark = new Bookmark();
		foreach ($props as $prop => $val) {
			$bookmark->{'set' . $prop}($val);
		}
		return $bookmark;
	}

	public function __construct() {
		// add types in constructor
		$this->addType('id', 'integer');
		$this->addType('url', 'string');
		$this->addType('title', 'string');
		$this->addType('userId', 'string');
		$this->addType('description', 'string');
		$this->addType('added', 'integer');
		$this->addType('lastmodified', 'integer');
		$this->addType('clickcount', 'integer');
		$this->addType('lastPreview', 'integer');
		$this->addType('available', 'boolean');
		$this->addType('archivedFile', 'integer');
	}

	public function toArray(): array {
		$array = [];
		foreach (self::$fields as $field) {
			$array[$field] = $this->{$field};
		}
		return $array;
	}

	public function markPreviewCreated(): void {
		$this->setLastPreview(time());
	}

	public function incrementClickcount(): void {
		$this->setClickcount($this->clickcount + 1);
	}

	public function setTitle(string $title): void {
		// Cap title length at 255 because the DB doesn't have more space currently
		if (mb_strlen($title) > 255) {
			$title = mb_substr($title, 0, 254) . '…';
		}
		$this->setter('title', [$title]);
	}
}
