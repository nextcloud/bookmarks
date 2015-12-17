<?php

namespace OCA\Bookmarks\Controller\Rest;

use \OCP\AppFramework\ApiController;
use \OCP\IRequest;
use \OCP\IDb;
use \OCP\AppFramework\Http\JSONResponse;
use \OC\User\Manager;
use OCA\Bookmarks\Controller\Lib\Bookmarks;

class PublicController extends ApiController
{

    private $db;
    private $userManager;

    public function __construct($appName, IRequest $request, IDb $db, Manager $userManager)
    {
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
     */
    public function returnAsJson($user, $password = null, $tags = array(), $conjunction = "or", $select = null, $sortby = "")
    {

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


    /**
     * @CORS
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function returnPrivateAsJson($user, $tags = array(), $conjunction = "or", $select = null, $sortby = "")
    {

        if ($tags != null && $tags[0] == "") {
            $tags = array();
        }

        $attributesToSelect = array('url', 'title');

        if ($select != null) {
            $attributesToSelect = array_merge($attributesToSelect, $select);
            $attributesToSelect = array_unique($attributesToSelect);
        }

        $output = Bookmarks::findBookmarks($user, $this->db, 0, $sortby, $tags, true, -1, false, $attributesToSelect, $conjunction);

        if (count($output) == 0) {
            $output["status"] = 'error';
            $output["message"] = "No results from this query";
            return new JSONResponse($output);
        }

        return new JSONResponse($output);
    }


    /**
     * @CORS
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function returnAddAsJson($user, $url = "", $tags = array(), $title = "", $description = "")
    {

        if ($tags[0] == "") {
            $tags = array();
        }

        $output = Bookmarks::addBookmark($user, $this->db, $url, $title, $tags, $description, false);

        if (count($output) == 0) {
            $output["status"] = 'error';
            $output["message"] = "No results from this query";
            return new JSONResponse($output);
        }

        $output = Bookmarks::findUniqueBookmark($output, $user, $this->db);

        return new JSONResponse($output);
    }

    /**
     * @CORS
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function returnUpdateAsJson($user, $id, $url = "", $tags = array(), $title = "", $description = "")
    {

        if ($tags[0] == "") {
            $tags = array();
        }

        $output = Bookmarks::editBookmark($user, $this->db, $id, $url, $title, $tags, $description, false);

        if (count($output) == 0) {
            $output["status"] = 'error';
            $output["message"] = "No results from this query";
            return new JSONResponse($output);
        }

        $output = Bookmarks::findUniqueBookmark($output, $user, $this->db);

        return new JSONResponse($output);
    }

    /**
     * @CORS
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function returnDeleteAsJson($user, $id)
    {
        $output = Bookmarks::deleteUrl($user, $this->db, $id);

        if (!$output) {
            $output = array();
            $output["status"] = 'error';
            $output["message"] = "Cannot delete bookmark";
            return new JSONResponse($output);
        } else {
            $output = array();
            $output["status"] = 'success';
            $output["message"] = "Bookmark deleted";
            return new JSONResponse($output);
        }
    }

    /**
     * @CORS
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function returnClickBookmarkAsJson($user, $url)
    {

        // Check if it is a valid URL
        if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
            return new JSONResponse(array(), Http::STATUS_BAD_REQUEST);
        }

        $query = $this->db->prepareQuery('
            UPDATE `*PREFIX*bookmarks`
            SET `clickcount` = `clickcount` + 1
            WHERE `user_id` = ?
                AND `url` LIKE ?
            ');

        $params = array($user, htmlspecialchars_decode($url));
        $query->execute($params);

        $output = array();
        $output["status"] = "success";
        return new JSONResponse($output);
    }

    public function newJsonErrorMessage($message)
    {
        $output = array();
        $output["status"] = 'error';
        $output["message"] = $message;
        return new JSONResponse($output);
    }

}
