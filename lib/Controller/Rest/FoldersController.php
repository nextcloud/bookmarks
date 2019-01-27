<?php

namespace OCA\Bookmarks\Controller\Rest;

use \OCA\Bookmarks\Bookmarks;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\ApiController;
use \OCP\IRequest;

class FoldersController extends ApiController {
	private $userId;

	/** @var Bookmarks */
	private $bookmarks;

	public function __construct($appName, IRequest $request, $userId, Bookmarks $bookmarks) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->bookmarks = $bookmarks;
	}

	/**
	 * @param string $title
	 * @param int $parent_folder
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function addFolder($title = '', $parent_folder = -1) {
		$id = $this->bookmarks->addFolder($this->userId, $title, $parent_folder);
		if ($id === false) {
			return new JSONResponse(['status' => 'error', 'data' => 'Parent folder does not exist'], Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse(['status' => 'success', 'item' => ['id' => $id, 'title' => $title, 'parent_folder' => $parent_folder]]);
	}

	/**
	 * @param int $folderId
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function getFolder($folderId) {
		$folder = $this->bookmarks->getFolder($this->userId, $folderId);
		if (!$folder) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not get folder']);
		}
		return new JSONResponse(['status' => 'success', 'item' => $folder]);
	}

	/**
	 * @param int $folderId
	 * @param int $bookmarkId
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function addToFolder($folderId, $bookmarkId) {
		if (!$this->bookmarks->addToFolders($this->userId, $bookmarkId, [$folderId])) {
			return new JSONResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(['status' => 'success']);
	}

	/**
	 * @param int $folderId
	 * @param int $bookmarkId
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function removeFromFolder($folderId, $bookmarkId) {
		if (!($this->bookmarks->removeFromFolders($this->userId, $bookmarkId, [$folderId]))) {
			return new JSONResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse(['status' => 'success']);
	}


	/**
	 * @param int $folderId
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function deleteFolder($folderId) {
		$this->bookmarks->deleteFolder($this->userId, $folderId);
		return new JSONResponse(['status' => 'success']);
	}

	/**
	 * @param int $folderId
	 * @param string $title
	 * @param int $parent
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function editFolder($folderId, $title = null, $parent_folder = null) {
		if ($this->bookmarks->editFolder($this->userId, $folderId, $title, $parent_folder)) {
			return new JSONResponse(['status' => 'success', 'item' => $this->bookmarks->getFolder($this->userId, $folderId)]);
		} else {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not modify folder'], Http::STATUS_BAD_REQUEST);
		}
	}


	/**
	 * @param int $folderId
	 * @param int $layers
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function getFolderChildrenOrder($folderId, $layers=1) {
		$children = $this->bookmarks->getFolderChildren($this->userId, $folderId, $layers);
		return new JSONResponse(['status' => 'success', 'data' => $children]);
	}

	/**
	 * @param int $folderId
	 * @param array $data
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function setFolderChildrenOrder($folderId, $data = []) {
		try {
			$this->bookmarks->setFolderChildren($this->userId, $folderId, $data);
			return new JSONResponse(['status' => 'success']);
		} catch (Exception $e) {
			return new JSONResponse(['status' => 'error', 'data' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @param int $root the id of the root folder whose descendants to return
	 * @param int $layers the number of layers of hierarchy too return
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function getFolders($root = -1, $layers = 0) {
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

		$folders = $this->bookmarks->listFolders($this->userId, $root, $layers);
		if ($folders !== false) {
			return new JSONResponse(['status' => 'success', 'data' => $folders]);
		} else {
			return new JSONResponse(['status' => 'error', 'data' => 'Folder does not exist'], Http::STATUS_BAD_REQUEST);
		}
	}
}
