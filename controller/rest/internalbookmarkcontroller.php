<?php

/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Stefan Klemm <mail@stefan-klemm.de>
 * @copyright Stefan Klemm 2014
 */

namespace OCA\Bookmarks\Controller\Rest;

use OCP\IDBConnection;
use OCP\IL10N;
use \OCP\IRequest;
use \OCP\AppFramework\ApiController;
use \OCP\AppFramework\Http\DataDisplayResponse;
use \OCP\AppFramework\Http\NotFoundResponse;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http;
use \OC\User\Manager;
use \OCA\Bookmarks\Controller\Lib\Bookmarks;
use \OCA\Bookmarks\Controller\Lib\ImageService;
use DateInterval;
use DateTime;
use OCP\AppFramework\Utility\ITimeFactory;

class InternalBookmarkController extends ApiController {

	const IMAGES_CACHE_TTL = 7 * 24 * 60 * 60;

	private $publicController;
	
	private $userId;
	private $libBookmarks;
	private $imageService;
	private $timeFactory;

	public function __construct(
		$appName,
		IRequest $request,
		$userId,
		IDBConnection $db,
		IL10N $l10n,
		Bookmarks $bookmarks,
		Manager $userManager,
		ImageService $imageService,
		ITimeFactory $timeFactory
	) {
		parent::__construct($appName, $request);
		$this->publicController = new BookmarkController($appName, $request, $userId, $db, $l10n, $bookmarks, $userManager);
		$this->userId = $userId;
		$this->libBookmarks = $bookmarks;
		$this->imageService = $imageService;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @param string $type
	 * @param string $tag
	 * @param int $page
	 * @param string $sort
	 * @param string user
	 * @param array tags
	 * @param string conjunction
	 * @param string sortby
	 * @param array search
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function getBookmarks(
		$type = "bookmark",
		$tag = '', // legacy
		$page = 0,
		$sort = "bookmarks_sorting_recent", // legacy
		$user = null,
		$tags = array(),
		$conjunction = "or",
		$sortby = "",
		$search = array()
	) {
		return $this->publicController->getBookmarks($type, $tag, $page, $sort, $user, $tags, $conjunction, $sortby, $search);
	}
	
  /**
	 * @param string $id
	 * @param string $user
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function getSingleBookmark($id, $user = null) {
		return $this->publicController->getSingleBookmark($id, $user);
	}

	/**
	 * @param string $url
	 * @param array $item
	 * @param string $title
	 * @param bool $is_public
	 * @param string $description
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function newBookmark($url = "", $item = array(), $title = "", $is_public = false, $description = "") {
		return $this->publicController->newBookmark($url, $item, $title, $is_public, $description);
	}

	/**
	 * @param int $id
	 * @param string $url
	 * @param array $item
	 * @param string $title
	 * @param bool $is_public Description
	 * @param null $record_id
	 * @param string $description
	 * @return Http\TemplateResponse
	 *
	 * @NoAdminRequired
	 */
	public function legacyEditBookmark($id = null, $url = "", $item = array(), $title = "", $is_public = false, $record_id = null, $description = "") {
		return $this->publicController->legacyEditBookmark($id, $url, $item, $title, $is_public, $record_id, $description);
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
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function editBookmark($id = null, $url = "", $item = array(), $title = "", $is_public = false, $record_id = null, $description = "", $tags = []) {
		return $this->publicController->editBookmark($id, $url, $item, $title, $is_public, $record_id, $description, $tags);
	}

	/**
	 * @param int $id
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function legacyDeleteBookmark($id = -1) {
		return $this->legacyDeleteBookmark($id);
	}

	/**
	 * @param int $id
	 * @return \OCP\AppFramework\Http\JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function deleteBookmark($id = -1) {
		return $this->publicController->deleteBookmark($id);
	}

	/**
	 * 
	 * @param string $url
	 * @return \OCP\AppFramework\Http\JSONResponse
	 * @NoAdminRequired
	 */
	public function clickBookmark($url = "") {
		return $this->publicController->clickBookmark($url);
	}

	/**
	 * 
	 * @return \OCP\AppFramework\Http\JSONResponse
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
	 * @param int $id The id of the bookmark whose image shoudl be returned
	 * @return \OCP\AppFramework\Http\Reponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getBookmarkImage($id) {
		$bookmark = $this->libBookmarks->findUniqueBookmark($id, $this->userId);
		if (!isset($bookmark) || !isset($bookmark['image']) || $bookmark['image'] === '') {	
			return new NotFoundResponse();
		}

		$image = $this->imageService->getImage($bookmark['image']);
		if (!isset($image)) {	
			return new NotFoundResponse();
		}
		
		$response = new DataDisplayResponse($image['data']);
		$response->addHeader('Content-Type', $image['contentType']);	
		
		$response->cacheFor(self::IMAGES_CACHE_TTL);
		 
		$expires = new DateTime();
		$expires->setTimestamp($this->timeFactory->getTime());
		$expires->add(new DateInterval('PT' . self::IMAGES_CACHE_TTL . 'S'));
		$response->addHeader('Expires', $expires->format(DateTime::RFC1123));
		$response->addHeader('Pragma', 'cache');
		
		return $response;
	}
}
