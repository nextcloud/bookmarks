<?php

namespace OCA\Bookmarks\Controller\Rest;

use \OCA\Bookmarks\Controller\Lib\Bookmarks;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\ApiController;
use \OCP\IRequest;
use \OCP\IDb;

class TagsController extends ApiController {

	private $userId;
	private $db;

	public function __construct($appName, IRequest $request, $userId, IDb $db) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->db = $db;
	}

	/**
	 * @param string $old_name
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function deleteTag($old_name = "") {

		if ($old_name == "") {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		Bookmarks::deleteTag($this->userId, $this->db, $old_name);
		return new JSONResponse(array('status' => 'success'));
	}

	/**
	 * @param string $old_name
	 * @param string $new_name
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function renameTag($old_name = "", $new_name = "") {

		if ($old_name == "" || $new_name == "") {
			return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
		}

		Bookmarks::renameTag($this->userId, $this->db, $old_name, $new_name);
		return new JSONResponse(array('status' => 'success'));
	}

	/**
	 * @NoAdminRequired
	 */
	public function fullTags() {
		
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
		
		$qtags = Bookmarks::findTags($this->userId, $this->db, array(), 0, 400);
		$tags = array();
		foreach ($qtags as $tag) {
			$tags[] = $tag['tag'];
		}

		return new JSONResponse($tags);
	}

}
