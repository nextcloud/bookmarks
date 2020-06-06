<?php

namespace OCA\Bookmarks\Controller;

use OCA\Bookmarks\Db;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

class TagsController extends ApiController {
	private $userId;

	/**
	 * @var Db\TagMapper
	 */
	private $tagMapper;

	public function __construct($appName, $request, $userId, DB\TagMapper $tagMapper) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->tagMapper = $tagMapper;
	}

	/**
	 * @param string $old_name
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function deleteTag($old_name = "") {
		if ($old_name === "") {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		$this->tagMapper->deleteTag($this->userId, $old_name);
		return new JSONResponse(['status' => 'success']);
	}

	/**
	 * @param string $old_name
	 * @param string $new_name
	 * @param string $name
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function renameTag($old_name = "", $new_name = "", $name = '') {
		if ($new_name === '') {
			$new_name = $name;
		}

		if ($old_name === "" || $new_name === "") {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		$this->tagMapper->renameTag($this->userId, $old_name, $new_name);
		return new JSONResponse(['status' => 'success']);
	}

	/**
	 * @param bool $count whether to add the count of bookmarks per tag
	 * @return JSONResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function fullTags($count = false) {
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

		if ($count === true) {
			$tags = $this->tagMapper->findAllWithCount($this->userId);
		} else {
			$tags = $this->tagMapper->findAll($this->userId);
		}
		return new JSONResponse($tags);
	}
}
