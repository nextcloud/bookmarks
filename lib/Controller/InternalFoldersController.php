<?php

namespace OCA\Bookmarks\Controller;

use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;

class InternalFoldersController extends ApiController {
	private $userId;

	/** @var FoldersController */
	private $controller;

	public function __construct($appName, $request, $userId, FoldersController $controller, \OCA\Bookmarks\Service\Authorizer $authorizer) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->controller = $controller;
		if ($userId !== null) {
			$authorizer->setUserId($userId);
		}
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
	 * @param int $layers
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function getFolderChildrenOrder($folderId, $layers) {
		return $this->controller->getFolderChildrenOrder($folderId, $layers);
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
	 * @param null $parent_folder
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function editFolder($folderId, $title = null, $parent_folder = null) {
		return $this->controller->editFolder($folderId, $title, $parent_folder);
	}

	/**
	 * @param int $folderId
	 * @param string[] $fields
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function hashFolder($folderId, $fields = ['title', 'url']) {
		return $this->controller->hashFolder($folderId, $fields);
	}

	/**
	 * @param int $root the id of the root folder whose descendants to return
	 * @param int $layers the number of layers of hierarchy to return
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 */
	public function getFolders($root = -1, $layers = -1) {
		return $this->controller->getFolders($root, $layers);
	}

	/**
	 * @param int $folderId
	 * @return DataResponse
	 * @NoAdminRequired
	 */
	public function getFolderPublicToken($folderId) {
		return $this->controller->getFolderPublicToken($folderId);
	}

	/**
	 * @param int $folderId
	 * @return DataResponse
	 * @NoAdminRequired
	 */
	public function createFolderPublicToken($folderId) {
		return $this->controller->createFolderPublicToken($folderId);
	}

	/**
	 * @param int $folderId
	 * @return DataResponse
	 * @NoAdminRequired
	 */
	public function deleteFolderPublicToken($folderId) {
		return $this->controller->deleteFolderPublicToken($folderId);
	}

	/**
	 * @param int $folderId
	 * @return DataResponse
	 * @NoAdminRequired
	 */
	public function getShares($folderId) {
		return $this->controller->getShares($folderId);
	}

	/**
	 * @param int $folderId
	 * @param $participant
	 * @param $type
	 * @param bool $canWrite
	 * @param bool $canShare
	 * @return DataResponse
	 * @NoAdminRequired
	 */
	public function createShare($folderId, $participant, $type, $canWrite = false, $canShare = false) {
		return $this->controller->createShare($folderId, $participant, $type, $canWrite, $canShare);
	}

	/**
	 * @param $shareId
	 * @param bool $canWrite
	 * @param bool $canShare
	 * @return DataResponse
	 * @NoAdminRequired
	 */
	public function editShare($shareId, $canWrite = false, $canShare = false) {
		return $this->controller->editShare($shareId, $canWrite, $canShare);
	}

	/**
	 * @param int $shareId
	 * @return DataResponse
	 * @NoAdminRequired
	 */
	public function deleteShare($shareId) {
		return $this->controller->deleteShare($shareId);
	}
}
