<?php

namespace OCA\Bookmarks\Contract;

use OCA\Bookmarks\Db\Bookmark;

interface IBookmarkPreviewer {
	/**
	 * @param Bookmark $bookmark
	 * @return IImage|null
	 */
	public function getImage($bookmark): ?IImage;
}
