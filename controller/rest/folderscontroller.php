<?php

namespace OCA\Bookmarks\Controller\Rest;

use \OCA\Bookmarks\Controller\Lib\Bookmarks;
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
	 * @param int $parent
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function addFolder($title = '', $parent = -1) {
		$this->bookmarks->addFolder($this->userId, $title, $parent);
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
	public function getFolder($folderId) {
		$folder = $this->bookmarks->getFolder($this->userId, $folderId);
		if (!$folder) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not get folder']);
		}
		return new JSONResponse(['status' => 'success', 'data' => $folder]);
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
	public function editFolder($folderId, $title = null, $parent = null) {
		if ($this->bookmarks->editFolder($this->userId, $folderId, $title, $parent)) {
			return new JSONResponse(['status' => 'success']);
		} else {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not modify folder'], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @param int $root the id of the root folder whose descendants to return
	 * @param int $layers the number of layers of hierarchy too return
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function getFolders($root = -1, $layers = -1) {
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

		if ($folders = $this->bookmarks->listFolders($this->userId, $root, $layers)) {
			return new JSONResponse(['status' => 'success', 'data' => $folders]);
		} else {
			return new JSONResponse(['status' => 'error', 'data' => 'Folder does not exist'], Http::STATUS_BAD_REQUEST);
		}
	}
}
