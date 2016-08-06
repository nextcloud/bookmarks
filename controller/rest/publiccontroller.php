<?php

namespace OCA\Bookmarks\Controller\Rest;

use \OCP\AppFramework\ApiController;
use \OCP\IRequest;
use \OCP\IDb;
use \OCP\AppFramework\Http\JSONResponse;
use \OC\User\Manager;
use OCA\Bookmarks\Controller\Lib\Bookmarks;
use OCP\Util;

class PublicController extends ApiController {

	private $db;
	private $userManager;

	public function __construct($appName, IRequest $request, IDb $db, Manager $userManager) {
		parent::__construct(
				$appName, $request);

		$this->db = $db;
		$this->userManager = $userManager;
	}

	/**
	 * @param string $user
	 * @param string $password
	 * @param array $tags
	 * @param string $conjunction
	 * @param array $select
	 * @param string $sortby
	 * @return JSONResponse
	 *
	 * @CORS
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function returnAsJson($user, $password = null, $tags = array(), $conjunction = "or", $select = null, $sortby = "") {

		if ($user == null || $this->userManager->userExists($user) == false) {
			return $this->newJsonErrorMessage("User could not be identified");
		}

		if ($tags[0] == "") {
			$tags = array();
		}

		$public = true;

		if ($password != null) {
			$public = false;
		}


		if (!$public && !$this->userManager->checkPassword($user, $password)) {

			$msg = 'REST API accessed with wrong password';
			Util::writeLog('bookmarks', $msg, Util::WARN);

			return $this->newJsonErrorMessage("Wrong password for user " . $user);
		}

		$attributesToSelect = array('url', 'title');

		if ($select != null) {
			$attributesToSelect = array_merge($attributesToSelect, $select);
			$attributesToSelect = array_unique($attributesToSelect);
		}

		$output = Bookmarks::findBookmarks($user, $this->db, 0, $sortby, $tags, true, -1, $public, $attributesToSelect, $conjunction);

		if (count($output) == 0) {
			$output["status"] = 'error';
			$output["message"] = "No results from this query";
			return new JSONResponse($output);
		}

		return new JSONResponse($output);
	}

	/**
	 * @param string $message
	 * @return JSONResponse
	 */
	public function newJsonErrorMessage($message) {
		$output = array();
		$output["status"] = 'error';
		$output["message"] = $message;
		return new JSONResponse($output);
	}

}
