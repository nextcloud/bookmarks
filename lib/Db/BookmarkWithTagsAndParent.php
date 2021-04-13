<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Db;

/**
 * Class Bookmark
 *
 * @package OCA\Bookmarks\Db
 * @method getTags
 */
class BookmarkWithTagsAndParent extends Bookmark {
	protected $tags;
	protected $folders;

	public static $columns = ['id', 'url', 'title', 'description', 'lastmodified', 'added', 'clickcount', 'last_preview', 'available', 'archived_file', 'user_id', 'tags', 'folders', 'text_content', 'html_content'];
	public static $fields = ['id', 'url', 'title', 'description', 'lastmodified', 'added', 'clickcount', 'lastPreview', 'available', 'archivedFile', 'userId', 'tags', 'folders', 'textContent', 'htmlContent'];

	public function toArray(): array {
		$array = [];
		foreach (self::$fields as $field) {
			if ($field === 'tags' && is_string($this->{$field})) {
				$array[$field] = $this->{$field} === ''? [] : array_values(array_unique(explode(',',$this->{$field})));
				continue;
			}
			if ($field === 'folders') {
				if ($this->{$field} === '') {
					$array[$field] = [];
				} else {
					$array[$field] = array_values(array_unique(array_map(static function ($id) {
						return (int) $id;
					},explode(',',$this->{$field}))));
				}
				continue;
			}
			$array[$field] = $this->{$field};
		}
		return $array;
	}
}
