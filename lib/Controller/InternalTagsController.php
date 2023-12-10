<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Controller;

use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\JSONResponse;

class InternalTagsController extends ApiController {
	private $publicController;

	public function __construct($appName, $request, TagsController $publicController) {
		parent::__construct($appName, $request);
		$this->publicController = $publicController;
	}

	/**
	 * @param string $old_name
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function deleteTag($old_name = ""): JSONResponse {
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
	public function renameTag($old_name = "", $new_name = "", $name = ''): JSONResponse {
		return $this->publicController->renameTag($old_name, $new_name, $name);
	}

	/**
	 * @param bool $count whether to add the count of bookmarks per tag
	 * @NoAdminRequired
	 * @return JSONResponse
	 */
	public function fullTags($count): JSONResponse {
		return $this->publicController->fullTags($count);
	}
}
