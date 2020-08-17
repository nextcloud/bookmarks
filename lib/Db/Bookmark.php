<?php

namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Class Bookmark
 *
 * @package OCA\Bookmarks\Db
 * @method getUrl
 * @method setUrl(string $url)
 * @method getTitle
 * @method setTitle(string $title)
 * @method getDescription
 * @method setDescription(string $description)
 * @method getLastmodified
 * @method setLastmodified(int $lastmodified)
 * @method getAdded
 * @method setAdded(int $added)
 * @method getClickcount
 * @method setClickcount(int $count)
 * @method getLastPreview
 * @method setLastPreview(int $lastpreview)
 * @method getAvailable
 * @method setAvailable(boolean $available)
 * @method getArchivedFile
 * @method setArchivedFile(int $fileId)
 * @method getUserId
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

	public static function fromArray($props) {
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

	public function markPreviewCreated() {
		$this->setLastPreview(time());
	}

	public function incrementClickcount() {
		$this->setClickcount($this->clickcount + 1);
	}
}
