<?php

namespace OCA\Bookmarks\Controller\Rest;

use \OCA\Bookmarks\Bookmarks;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\ApiController;
use \OCP\IRequest;

class TagsController extends ApiController {
	private $userId;

	/** @var Bookmarks */
	private $bookmarks;

	public function __construct($appName, IRequest $request, $userId, Bookmarks $bookmarks) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->bookmarks = $bookmarks;
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

		$this->bookmarks->deleteTag($this->userId, $old_name);
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

		$this->bookmarks->renameTag($this->userId, $old_name, $new_name);
		return new JSONResponse(['status' => 'success']);
	}

	/**
	 * @param bool $count whether to add the count of bookmarks per tag
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function fullTags($count=false) {
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

		$qtags = $this->bookmarks->findTags($this->userId, [], 0);
		$tags = [];
		foreach ($qtags as $tag) {
			if ($count === true) {
				$tags[] = ['name' => $tag['tag'], 'count' => $tag['nbr']];
			} else {
				$tags[] = $tag['tag'];
			}
		}

		return new JSONResponse($tags);
	}
}
