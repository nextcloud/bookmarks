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
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Bookmarks\Service\Authorizer;
use OCA\Bookmarks\Service\BookmarkService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;

class InternalBookmarkController extends ApiController {

	/**
	 * @var BookmarkController
	 */
	private $publicController;

	private $userId;

	/**
	 * @var BookmarkService
	 */
	private $bookmarks;

	public function __construct(
		$appName, $request, $userId, BookmarkController $publicController, BookmarkService $bookmarks, Authorizer $authorizer
	) {
		parent::__construct($appName, $request);
		$this->publicController = $publicController;
		$this->userId = $userId;
		$this->bookmarks = $bookmarks;
		if ($this->userId !== null) {
			$authorizer->setUserId($this->userId);
		}
	}

	/**
	 * @param int $page
	 * @param array $tags
	 * @param string $conjunction
	 * @param string $sortby
	 * @param array $search
	 * @param int $limit
	 * @param bool $untagged
	 * @param int $folder
	 * @param string $url
	 * @return DataResponse
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
	 * @throws AlreadyExistsError
	 * @throws UserLimitExceededError
	 * @NoAdminRequired
	 */
	public function newBookmark($url = "", $title = null, $description = "", $tags = [], $folders = []) {
		return $this->publicController->newBookmark($url, $title, $description, $tags, $folders);
	}

	/**
	 * @param int $id
	 * @param string $url
	 * @param string $title
	 * @param string $description
	 * @param array $tags
	 * @param array $folders
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function editBookmark($id = null, $url = null, $title = null, $description = "", $tags = [], $folders = null) {
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
	 * @return DataResponse
	 *
	 * @NoAdminRequired
	 */
	public function deleteAllBookmarks() {
		try {
			$this->bookmarks->deleteAll($this->userId);
		} catch (UnsupportedOperation|DoesNotExistException|MultipleObjectsReturnedException $e) {
			return new DataResponse(['status' => 'error', 'data' => ['Internal server error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new DataResponse(['status' => 'success']);
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
	 * @param int|null $folder
	 * @return JSONResponse
	 * @NoAdminRequired
	 */
	public function importBookmark($folder = null) {
		return $this->publicController->importBookmark($folder);
	}

	/**
	 *
	 * @return Response
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

	/**
	 *
	 * @param int $folder
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function countBookmarks(int $folder): JSONResponse {
		return $this->publicController->countBookmarks($folder);
	}
}
