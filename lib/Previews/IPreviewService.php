<?php
namespace OCA\Bookmarks\Previews;

interface IPreviewService {
	/**
	 * @return null|array ['contentType' => 'mimetype', 'data' => binary]
	 */
	public function getImage($bookmark);
}
