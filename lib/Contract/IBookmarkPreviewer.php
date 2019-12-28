<?php

namespace OCA\Bookmarks\Contract;

use OCA\Bookmarks\Db\Bookmark;

interface IBookmarkPreviewer {
	/**
	 * @param Bookmark $bookmark
	 * @return null|array ['contentType' => 'mimetype', 'data' => binary]
	 */
	public function getImage($bookmark);
}
