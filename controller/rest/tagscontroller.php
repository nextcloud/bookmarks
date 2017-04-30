<?php

namespace OCA\Bookmarks\Controller\Rest;

use \OCA\Bookmarks\Controller\Lib\Bookmarks;
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
	 */
	public function deleteTag($old_name = "") {

		if ($old_name == "") {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		$this->bookmarks->deleteTag($this->userId, $old_name);
		return new JSONResponse(array('status' => 'success'));
	}

	/**
	 * @param string $old_name
	 * @param string $new_name
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function renameTag($old_name = "", $new_name = "") {

		if ($old_name == "" || $new_name == "") {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		$this->bookmarks->renameTag($this->userId, $old_name, $new_name);
		return new JSONResponse(array('status' => 'success'));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function fullTags() {
		
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
		
		$qtags = $this->bookmarks->findTags($this->userId, array(), 0, 400);
		$tags = array();
		foreach ($qtags as $tag) {
			$tags[] = $tag['tag'];
		}

		return new JSONResponse($tags);
	}

}
