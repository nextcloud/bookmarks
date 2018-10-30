<?php

namespace OCA\Bookmarks\Controller\Rest;

use \OCA\Bookmarks\Controller\Lib\Bookmarks;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\ApiController;
use \OCP\IRequest;

class InternalFoldersController extends ApiController {
	private $userId;

	/** @var FoldersController */
	private $controller;

	public function __construct($appName, IRequest $request, $userId, FoldersController $controller) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->controller = $controller;
	}

	/**
	 * @param string $title
	 * @param int $parent_folder
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function addFolder($title = '', $parent_folder = -1) {
		return $this->controller->addFolder($title, $parent_folder);
	}


	/**
	 * @param int $folderId
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function getFolderChildrenOrder($folderId) {
		return $this->controller->getFolderChildrenOrder($folderId);
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
		return $this->controller->setFolderChildrenOrder($folderId, $data);
	}

	/**
	 * @param int $folderId
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function deleteFolder($folderId) {
		return $this->controller->deleteFolder($folderId);
	}

	/**
	 * @param int $folderId
	 * @param int $bookmarkId
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function addToFolder($folderId, $bookmarkId) {
		return $this->controller->addToFolder($folderId, $bookmarkId);
	}

	/**
	 * @param int $folderId
	 * @param int $bookmarkId
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function removeFromFolder($folderId, $bookmarkId) {
		return $this->controller->removeFromFolder($folderId, $bookmarkId);
	}

	/**
	 * @param int $folderId
	 * @param string $title
	 * @param int $parent
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function editFolder($folderId, $title = null, $parent_folder = null) {
		return $this->controller->editFolder($folderId, $title, $parent_folder);
	}

	/**
	 * @param int $root the id of the root folder whose descendants to return
	 * @param int $layers the number of layers of hierarchy too return
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function getFolders($root = -1, $layers = 0) {
		return $this->controller->getFolders($root, $layers);
	}
}
