<?php

namespace OCA\Bookmarks\Db;

/**
 * Class Bookmark
 *
 * @package OCA\Bookmarks\Db
 * @method getTags
 */
class BookmarkWithTags extends Bookmark {
	protected $tags;

	public static $columns = ['id', 'url', 'title', 'description', 'lastmodified', 'added', 'clickcount', 'last_preview', 'available', 'archived_file', 'user_id', 'tags'];
	public static $fields = ['id', 'url', 'title', 'description', 'lastmodified', 'added', 'clickcount', 'lastPreview', 'available', 'archivedFile', 'userId', 'tags'];

	public static function fromArray($props) {
		$bookmark = new Bookmark();
		foreach ($props as $prop => $val) {
			$bookmark->{'set' . $prop}($val);
		}
		return $bookmark;
	}

	public function __construct() {
		parent::__construct();
	}

	public function toArray(): array {
		$array = [];
		foreach (self::$fields as $field) {
			if ($field === 'tags') {
				$array[$field] = $this->{$field} === ''? [] : explode(',',$this->{$field});
				continue;
			}
			$array[$field] = $this->{$field};
		}
		return $array;
	}
}
