<?php
namespace OCA\Bookmarks\Controller\Lib\Previews;

interface IPreviewService {
	/**
	 * @return null|array ['contentType' => 'mimetype', 'data' => binary]
	 */
	public function getImage($bookmark);
}
