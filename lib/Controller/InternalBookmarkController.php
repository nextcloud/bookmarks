<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Controller;

use Exception;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Service\Authorizer;
use OCA\Bookmarks\Service\BookmarkService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;

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
		$authorizer->setCORS(false);
	}

	/**
	 * @param int $page
	 * @param array $tags
	 * @param string $conjunction
	 * @param string $sortby
	 * @param array $search
	 * @param int $limit
	 * @param bool $untagged
	 * @param int|null $folder
	 * @param string|null $url
	 * @param bool|null $unavailable
	 * @param bool|null $archived
	 * @param bool|null $duplicated
	 * @param bool|null $deleted
	 * @return DataResponse
	 *
	 * @NoAdminRequired
	 */
	public function getBookmarks(
		$page = 0,
		$tags = [],
		$conjunction = 'or',
		$sortby = '',
		$search = [],
		$limit = 10,
		$untagged = false,
		$folder = null,
		$url = null,
		$unavailable = null,
		$archived = null,
		$duplicated = null,
		bool $recursive = false,
		bool $deleted = false,
	): DataResponse {
		return $this->publicController->getBookmarks($page, $tags, $conjunction, $sortby, $search, $limit, $untagged, $folder, $url, $unavailable, $archived, $duplicated, $recursive, $deleted);
	}

	/**
	 * @param string $id
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function getSingleBookmark($id): JSONResponse {
		return $this->publicController->getSingleBookmark($id);
	}

	/**
	 * @param string $url
	 * @param string|null $title
	 * @param string $description
	 * @param array $tags
	 * @param array $folders
	 * @param string $target
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function newBookmark($url = '', $title = null, $description = null, $tags = null, $folders = [], $target = null): JSONResponse {
		return $this->publicController->newBookmark($url, $title, $description, $tags, $folders, $target);
	}

	/**
	 * @param int|null $id
	 * @param string|null $url
	 * @param string|null $title
	 * @param string $description
	 * @param array $tags
	 * @param array|null $folders
	 * @param string|null $target
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function editBookmark($id = null, $url = null, $title = null, $description = '', $tags = [], $folders = null, $target = null): JSONResponse {
		return $this->publicController->editBookmark($id, $url, $title, $description, $tags, $folders, $target);
	}

	/**
	 * @param int $id
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function deleteBookmark($id = -1): JSONResponse {
		return $this->publicController->deleteBookmark($id);
	}

	/**
	 * @return DataResponse
	 *
	 * @NoAdminRequired
	 */
	public function deleteAllBookmarks(): DataResponse {
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
	 */
	public function clickBookmark($url = ''): JSONResponse {
		return $this->publicController->clickBookmark($url);
	}

	/**
	 * @param int|null $folder
	 * @return JSONResponse
	 * @NoAdminRequired
	 */
	public function importBookmark($folder = null): JSONResponse {
		return $this->publicController->importBookmark($folder);
	}

	/**
	 *
	 * @return JSONResponse|\OCA\Bookmarks\ExportResponse
	 *
	 * @NoAdminRequired
	 */
	public function exportBookmark() {
		return $this->publicController->exportBookmark();
	}

	/**
	 *
	 * @param int $id The id of the bookmark whose favicon shoudl be returned
	 *
	 * @return Http\DataDisplayResponse|Http\NotFoundResponse|Http\RedirectResponse|Http\DataResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @throws Exception
	 */
	public function getBookmarkImage($id) {
		return $this->publicController->getBookmarkImage($id);
	}

	/**
	 * @param int $id The id of the bookmark whose image shoudl be returned
	 *
	 * @return Http\DataDisplayResponse|Http\NotFoundResponse|Http\RedirectResponse|Http\DataResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @throws Exception
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
	 */
	public function countBookmarks(int $folder): JSONResponse {
		return $this->publicController->countBookmarks($folder);
	}

	/**
	 *
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function countUnavailable(): JSONResponse {
		return $this->publicController->countUnavailable();
	}

	/**
	 *
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function countArchived(): JSONResponse {
		return $this->publicController->countArchived();
	}

	/**
	 *
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function countDuplicated(): JSONResponse {
		return $this->publicController->countDuplicated();
	}

	/**
	 * @return JSONResponse
	 * @NoAdminRequired
	 */
	public function acquireLock(): JSONResponse {
		return $this->publicController->acquireLock();
	}

	/**
	 * @return JSONResponse
	 * @NoAdminRequired
	 */
	public function releaseLock(): JSONResponse {
		return $this->publicController->releaseLock();
	}

	/**
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 */
	public function getDeletedBookmarks(): DataResponse {
		return $this->publicController->getDeletedBookmarks();
	}

	/**
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 */
	public function countAllClicks(): DataResponse {
		return $this->publicController->countAllClicks();
	}

	/**
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 */
	public function countWithClicks(): DataResponse {
		return $this->publicController->countWithClicks();
	}
}
