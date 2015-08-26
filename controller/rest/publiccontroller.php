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
	 * Return all or selected bookmarks
	 * @CORS
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function get($user, $password = null, $tags = array(), $conjunction = "or", $select = null, $sortby = "") {

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
			return $this->newJsonErrorMessage("No results from this query");
		}

		return new JSONResponse($output);
	}


        /**
         * Add new bookmark
         * @CORS
         * @NoAdminRequired
         * @NoCSRFRequired
         * @PublicPage
         */
	public function add($user = null, $password = null, $url = '', $title = '', $tags = array(), $description= '', $is_public = false)
	{
		if(!$this->isUser($user,$password))
		{
                        return $this->newJsonErrorMessage("User could not be identified");
		}

		$bookmark_id = Bookmarks::addBookmark($user, $this->db, $url, $title, $tags, $description, $is_public);
		
		if($bookmark_id)
		{
			$output = array();
			$output["status"] = "ok";
			$output["message"] = $bookmark_id;
			
			return new JSONResponse($output);
		}else{
			return $this->newJsonErrorMessage("Something wrong. Bookmark hasn't been added.");
		}
	}

	public function update($user = null, $password = null, $id = null, $url, $title, $tags = array(), $description, $is_public = false)
	{
		if(!$this->isUser($user, $password))
		{
			return $this->newJsonErrorMessage("User could not be identified");
		}

		Bookmarks::editBookmark($user,$this->db, $id, $url, $title, $tags, $description, $is_public);

		return new JSONResponse(array('status' => 'ok', 'message' => 'Bookmark has been updated. I hope.'));
		
	}

	/**
         * Remove bookmark
         * @CORS
         * @NoAdminRequired
         * @NoCSRFRequired
         * @PublicPage
         */
        public function delete($user = null, $password = null, $id = null)
        {
                if(!$this->isUser($user,$password))
                {
                        return $this->newJsonErrorMessage("User could not be identified");
                }

                $result = Bookmarks::deleteUrl($user, $this->db, $id);

                if($result)
                {
                        $output = array();
                        $output["status"] = "ok";
                        $output["message"] = "Bookmark has been deleted.";

                        return new JSONResponse($output);
                }else{
                        return $this->newJsonErrorMessage("Something wrong. Bookmark hasn't been deleted.");
                }
        }

	/**
	 * Check if user exists
	 * @param string $user username
	 * @param string $password user password
	 * @return bool
	 */
	private function isUser($user, $password)
	{
		if (!$user || $this->userManager->userExists($user) == false) {
                        return false;
                }elseif(!$this->userManager->checkPassword($user, $password)) {

                        $msg = 'REST API accessed with wrong password';
                        \OCP\Util::writeLog('bookmarks', $msg, \OCP\Util::WARN);

			return false;
                }else{
			return true;
		}
	}

	private function newJsonErrorMessage($message) {
		$output = array();
		$output["status"] = 'error';
		$output["message"] = $message;
		return new JSONResponse($output);
	}

}
