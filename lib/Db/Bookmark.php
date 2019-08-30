<?php
namespace OCA\Bookmarks\Db;

use OCP\AppFramework\Db\Entity;

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


	public function __construct() {
		// add types in constructor
		$this->addType('id', 'integer');
		$this->addType('url', 'string');
		$this->addType('title', 'string');
		$this->addType('userId', 'integer');
		$this->addType('description', 'string');
		$this->addType('public', 'boolean');
		$this->addType('added', 'integer');
		$this->addType('lastmodified', 'integer');
		$this->addType('clickcount', 'integer');
		$this->addType('lastPreview', 'integer');
	}

	// todo
	public function hashBookmark($userId, $bookmarkId, $fields) {
		$bookmarkRecord = $this->findUniqueBookmark($bookmarkId, $userId);
		$bookmark = [];
		foreach ($fields as $field) {
			if (isset($bookmarkRecord[$field])) {
				$bookmark[$field] = $bookmarkRecord[$field];
			}
		}
		return hash('sha256', json_encode($bookmark, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	public function markPreviewCreated() {
		$this->setLastPreview(time());
	}
}
