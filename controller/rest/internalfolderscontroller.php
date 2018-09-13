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
	 * @param int $parent
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function addFolder($title = '', $parent = -1) {
		return $this->controller->addFolder($title, $parent);
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
	 * @NoCSRFRequired
	 * @CORS
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
	 * @NoCSRFRequired
	 * @CORS
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
	public function editFolder($folderId, $title = null, $parent = null) {
		return $this->controller->editFolder($folderId, $title, $parent);
	}

	/**
	 * @param int $root the id of the root folder whose descendants to return
	 * @param int $layers the number of layers of hierarchy too return
	 * @NoAdminRequired
	 */
	public function getFolders($root = -1, $layers = -1) {
		return $this->controller->getFolders($root, $layers);
	}
}
