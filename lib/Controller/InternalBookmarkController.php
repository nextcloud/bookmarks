<?php

/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Stefan Klemm <mail@stefan-klemm.de>
 * @copyright Stefan Klemm 2014
 */

namespace OCA\Bookmarks\Controller;

use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Exception\UrlParseError;
use OCP\AppFramework\Http\Response;
use \OCP\IRequest;
use \OCP\AppFramework\ApiController;
use \OCP\AppFramework\Http\JSONResponse;

class InternalBookmarkController extends ApiController {
	/**
	 * @var BookmarkController
	 */
	private $publicController;

	private $userId;

	/**
	 * @var FolderMapper
	 */
	private $folderMapper;

	public function __construct(
		$appName,
		$request,
		$userId,
		BookmarkController $publicController,
		FolderMapper $folderMapper
	) {
		parent::__construct($appName, $request);
		$this->publicController = $publicController;
		$this->userId = $userId;
		$this->folderMapper = $folderMapper;
	}

	/**
	 * @param int $page
	 * @param array $tags
	 * @param string $conjunction
	 * @param string $sortby
	 * @param array $search
	 * @param int $limit
	 * @param bool $untagged
	 * @param null $folder
	 * @param null $url
	 * @return JSONResponse
	 *
	 * @throws UrlParseError
	 * @NoAdminRequired
	 */
	public function getBookmarks(
		$page = 0,
		$tags = [],
		$conjunction = "or",
		$sortby = "",
		$search = [],
		$limit = 10,
		$untagged = false,
		$folder = null,
		$url = null
	) {
		return $this->publicController->getBookmarks($page, $tags, $conjunction, $sortby, $search, $limit, $untagged, $folder, $url);
	}

	/**
	 * @param string $id
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function getSingleBookmark($id) {
		return $this->publicController->getSingleBookmark($id);
	}

	/**
	 * @param string $url
	 * @param string $title
	 * @param string $description
	 * @param array $tags
	 * @return JSONResponse
	 *
	 * @throws \OCA\Bookmarks\Exception\AlreadyExistsError
	 * @throws \OCA\Bookmarks\Exception\UserLimitExceededError
	 * @NoAdminRequired
	 */
	public function newBookmark($url = "", $title = null, $description = "", $tags = []) {
		return $this->publicController->newBookmark($url, $title, $description, $tags);
	}

	/**
	 * @param int $id
	 * @param string $url
	 * @param array $item
	 * @param string $title
	 * @param bool $is_public Description
	 * @param null $record_id
	 * @param string $description
	 * @param array $tags
	 * @param array $folders
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function editBookmark($id = null, $url = null, $title = null,  $description = "", $tags = [], $folders = null) {
		return $this->publicController->editBookmark($id, $url, $title, $description, $tags, $folders);
	}

	/**
	 * @param int $id
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function deleteBookmark($id = -1) {
		return $this->publicController->deleteBookmark($id);
	}

	/**
	 * @return array
	 *
	 * @NoAdminRequired
	 */
	public function deleteAllBookmarks() {
		$this->folderMapper->deleteAll($this->userId);
		return ['status' => 'success'];
	}

	/**
	 *
	 * @param string $url
	 * @return JSONResponse
	 * @NoAdminRequired
	 * @throws UrlParseError
	 */
	public function clickBookmark($url = "") {
		return $this->publicController->clickBookmark($url);
	}

	/**
	 *
	 * @return JSONResponse
	 * @NoAdminRequired
	 */
	public function importBookmark() {
		return $this->publicController->importBookmark();
	}

	/**
	 *
	 * @return \OCP\AppFramework\Http\Response
	 * @NoAdminRequired
	 */
	public function exportBookmark() {
		return $this->publicController->exportBookmark();
	}

	/**
	 *
	 * @param int $id The id of the bookmark whose favicon shoudl be returned
	 * @return Response
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @throws \Exception
	 */
	public function getBookmarkImage($id) {
		return $this->publicController->getBookmarkImage($id);
	}

	/**
	 *
	 * @param int $id The id of the bookmark whose image shoudl be returned
	 * @return Response
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @throws \Exception
	 */
	public function getBookmarkFavicon($id) {
		return $this->publicController->getBookmarkFavicon($id);
	}
}
