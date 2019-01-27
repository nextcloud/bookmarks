<?php

namespace OCA\Bookmarks\Controller\Rest;

use \OCA\Bookmarks\Bookmarks;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\ApiController;
use \OCP\IRequest;

class InternalTagsController extends ApiController {
	private $publicController;

	public function __construct($appName, IRequest $request, $userId, Bookmarks $bookmarks) {
		parent::__construct($appName, $request);
		$this->publicController = new TagsController($appName, $request, $userId, $bookmarks);
	}

	/**
	 * @param string $old_name
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function deleteTag($old_name = "") {
		return $this->publicController->deleteTag($old_name);
	}

	/**
	 * @param string $old_name
	 * @param string $new_name
	 * @param string $name
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function renameTag($old_name = "", $new_name = "", $name = '') {
		return $this->publicController->renameTag($old_name, $new_name, $name);
	}

	/**
	 * @param bool $count whether to add the count of bookmarks per tag
	 * @NoAdminRequired
	 */
	public function fullTags($count) {
		return $this->publicController->fullTags($count);
	}
}
