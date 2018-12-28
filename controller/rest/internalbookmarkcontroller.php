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
use OCP\ILogger;
use \OCP\IRequest;
use \OCP\IURLGenerator;
use \OCP\AppFramework\ApiController;
use \OCP\AppFramework\Http\DataDisplayResponse;
use \OCP\AppFramework\Http\NotFoundResponse;
use \OCP\AppFramework\Http\RedirectResponse;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http;
use \OC\User\Manager;
use \OCA\Bookmarks\Controller\Lib\Bookmarks;
use \OCA\Bookmarks\Controller\Lib\Previews\IPreviewService;
use DateInterval;
use DateTime;
use OCP\AppFramework\Utility\ITimeFactory;

class InternalBookmarkController extends ApiController {
	const IMAGES_CACHE_TTL = 7 * 24 * 60 * 60;

	private $publicController;

	private $userId;
	private $libBookmarks;
	private $previewService;
	private $faviconService;
	private $timeFactory;

	public function __construct(
		$appName,
		IRequest $request,
		$userId,
		IDBConnection $db,
		IL10N $l10n,
		Bookmarks $bookmarks,
		Manager $userManager,
		IPreviewService $previewService,
		IPreviewService $faviconService,
		IPreviewService $screenshotService,
		ITimeFactory $timeFactory,
		ILogger $logger,
		IURLGenerator $url
	) {
		parent::__construct($appName, $request);
		$this->publicController = new BookmarkController($appName, $request, $userId, $db, $l10n, $bookmarks, $userManager, $logger);
		$this->userId = $userId;
		$this->libBookmarks = $bookmarks;
		$this->previewService = $previewService;
		$this->faviconService = $faviconService;
		$this->screenshotService = $screenshotService;
		$this->timeFactory = $timeFactory;
		$this->url = $url;
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
	 * @param int limit
	 * @param bool untagged
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
		$tags = [],
		$conjunction = "or",
		$sortby = "",
		$search = [],
		$limit = 10,
		$untagged = false,
		$folder = null
	) {
		return $this->publicController->getBookmarks($type, $tag, $page, $sort, $user, $tags, $conjunction, $sortby, $search, $limit, $untagged, $folder);
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
	public function newBookmark($url = "", $item = [], $title = null, $is_public = false, $description = "", $tags = []) {
		return $this->publicController->newBookmark($url, $item, $title, $is_public, $description, $tags);
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
	public function legacyEditBookmark($id = null, $url = "", $item = [], $title = "", $is_public = false, $record_id = null, $description = "") {
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
	public function editBookmark($id = null, $url = null, $item = [], $title = null, $is_public = false, $record_id = null, $description = "", $tags = [], $folders = null) {
		return $this->publicController->editBookmark($id, $url, $item, $title, $is_public, $record_id, $description, $tags, $folders);
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
	 * @return \OCP\AppFramework\Http\JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function deleteAllBookmarks() {
		$this->libBookmarks->deleteAllBookmarks($this->userId);
		return ['status' => 'success'];
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
	 * @param int $id The id of the bookmark whose favicon shoudl be returned
	 * @return \OCP\AppFramework\Http\Reponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getBookmarkImage($id) {
		$bookmark = $this->libBookmarks->findUniqueBookmark($id, $this->userId);
		$image = $this->previewService->getImage($bookmark);
		if (isset($image)) {
			return $this->doImageResponse($image);
		}

		$image = $this->screenshotService->getImage($bookmark);
		if (isset($image)) {
			return $this->doImageResponse($image);
		}

		return new NotFoundResponse();
	}

	/**
	 *
	 * @param int $id The id of the bookmark whose image shoudl be returned
	 * @return \OCP\AppFramework\Http\Reponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getBookmarkFavicon($id) {
		$bookmark = $this->libBookmarks->findUniqueBookmark($id, $this->userId);
		$image = $this->faviconService->getImage($bookmark);
		if (!isset($image)) {
			// Return a placeholder
			return new RedirectResponse($this->url->getAbsoluteURL('/svg/core/places/link?color=666666'));
		}
		return $this->doImageResponse($image);
	}

	public function doImageResponse($image) {
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
