<?php

namespace OCA\Bookmarks\Controller\Rest;

use \OCP\AppFramework\ApiController;
use \OCP\IRequest;
use \OCP\IDb;
use \OCP\AppFramework\Http\JSONResponse;
use \OC\User\Manager;
use OCA\Bookmarks\Controller\Lib\Bookmarks;

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
	 * @CORS
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @param string $user Id of owner of requested bookmarks
	 * @param string $password Password of the bookmark owner to request private bookmarks
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
			\OCP\Util::writeLog('bookmarks', $msg, \OCP\Util::WARN);

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

	/* @brief Create a Json error message
	 * @param string $message Individual message to be returned inside the JsonResponse
	 */
	public function newJsonErrorMessage($message) {
		$output = array();
		$output["status"] = 'error';
		$output["message"] = $message;
		return new JSONResponse($output);
	}

}
