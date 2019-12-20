<?php

namespace OCA\Bookmarks\Controller;

use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\PublicFolder;
use OCA\Bookmarks\Db\PublicFolderMapper;
use OCA\Bookmarks\Exception\UnauthorizedAccessError;
use OCA\Bookmarks\Service\Authorizer;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\ApiController;

class FoldersController extends ApiController {
	private $userId;

	/** @var FolderMapper */
	private $folderMapper;

	/** @var PublicFolderMapper */
	private $publicFolderMapper;

	/**
	 * @var BookmarkMapper
	 */
	private $bookmarkMapper;

	/**
	 * @var Authorizer
	 */
	private $authorizer;

	public function __construct($appName, $request, $userId, FolderMapper $folderMapper, BookmarkMapper $bookmarkMapper, PublicFolderMapper $publicFolderMapper, Authorizer $authorizer) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->folderMapper = $folderMapper;
		$this->bookmarkMapper = $bookmarkMapper;
		$this->publicFolderMapper = $publicFolderMapper;
		$this->authorizer = $authorizer;
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
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($parent_folder, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		$folder = new Folder();
		$folder->setTitle($title);
		$folder->setParentFolder($parent_folder);
		$folder->setUserId($this->userId);
		try {
			$folder = $id = $this->folderMapper->insert($folder);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Parent folder does not exist'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple parent folders found'], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(['status' => 'success', 'item' => $folder->toArray()]);
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
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForBookmark($folderId, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$folder = $this->folderMapper->find($folderId);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple folders found'], Http::STATUS_BAD_REQUEST);
		}
		if ($folder->getUserId() !== $this->userId) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse(['status' => 'success', 'item' => $folder->toArray()]);
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
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($folderId, $this->userId, $this->request)) &&
			!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($bookmarkId, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$this->folderMapper->addToFolders($bookmarkId, [$folderId]);
		} catch (UnauthorizedAccessError $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_BAD_REQUEST);
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
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($folderId, $this->userId, $this->request)) &&
			!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($bookmarkId, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$this->folderMapper->removeFromFolders($bookmarkId, [$folderId]);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_BAD_REQUEST);
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
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($folderId, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$folder = $this->folderMapper->find($folderId);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'success']);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_BAD_REQUEST);
		}
		$this->folderMapper->delete($folder);
		return new JSONResponse(['status' => 'success']);
	}

	/**
	 * @param int $folderId
	 * @param string $title
	 * @param int $parent_folder
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function editFolder($folderId, $title = null, $parent_folder = null) {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($folderId, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$folder = $this->folderMapper->find($folderId);
			if (isset($title)) $folder->setTitle($title);
			if (isset($parent_folder)) $folder->setParentFolder($parent_folder);
			$folder = $this->folderMapper->update($folder);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse(['status' => 'success', 'item' => $folder->toArray()]);
	}

	/**
	 * @param int $folderId
	 * @param string[] $fields
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function hashFolder($folderId, $fields = ['title', 'url']) {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForBookmark($folderId, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			if ($folderId !== -1 && $folderId !== '-1') {
				$folder = $this->folderMapper->find($folderId);
				if ($folder->getUserId() !== $this->userId) {
					return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
				}
				$hash = $this->folderMapper->hashFolder($folderId, $fields);
			} else {
				$hash = $this->folderMapper->hashRootFolder($this->userId, $fields);
			}
			return new JSONResponse(['status' => 'success', 'data' => $hash]);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_BAD_REQUEST);
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
	public function getFolderChildrenOrder($folderId, $layers = 1) {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForBookmark($folderId, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$children = $this->folderMapper->getUserFolderChildren($this->userId, $folderId, $layers);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_BAD_REQUEST);
		} catch (UnauthorizedAccessError $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
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
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($folderId, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$this->bookmarks->setUserFolderChildren($this->userId, $folderId, $data);
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
	 * @return JSONResponse
	 */
	public function getFolders($root = -1, $layers = 0) {
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForBookmark($root, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}

		try {
			return new JSONResponse(['status' => 'success', 'data' => $this->_getFolders($root, $layers)]);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_BAD_REQUEST);
		} catch (UnauthorizedAccessError $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @param int $root
	 * @param int $layers
	 * @return array
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnauthorizedAccessError
	 */
	private function _getFolders($root = -1, $layers = 0) {
		if ($root !== -1 && $root !== '-1') {
			$folder = $this->folderMapper->find($root);
			if ($folder->getUserId() !== $this->userId) {
				throw new UnauthorizedAccessError();
			}
			$folders = array_map(function (Folder $folder) use ($layers) {
				$array = $folder->toArray();
				if ($layers - 1 > 0) {
					$array['children'] = $this->_getFolders($folder->getId(), $layers - 1);
				}
				return $array;
			}, $this->folderMapper->findByParentFolder($root));
		} else {
			$folders = array_map(function (Folder $folder) use ($layers) {
				$array = $folder->toArray();
				if ($layers - 1 >= 0) {
					$array['children'] = $this->_getFolders($folder->getId(), $layers - 1);
				}
				return $array;
			}, $this->folderMapper->findByRootFolder($this->userId));
		}
		return $folders;
	}

	/**
	 * @param int $folderId
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function getFolderPublicToken($folderId) {
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($folderId, $this->userId, $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$publicFolder = $this->publicFolderMapper->findByFolder($folderId);
		} catch (DoesNotExistException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		return new Http\DataResponse(['status' => 'success', 'item' => $publicFolder->getId()]);
	}

	/**
	 * @param int $folderId
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @throws MultipleObjectsReturnedException
	 */
	public function createFolderPublicToken($folderId) {
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($folderId, $this->userId, $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$publicFolder = $this->publicFolderMapper->findByFolder($folderId);
		} catch (DoesNotExistException $e) {
			$publicFolder = new PublicFolder();
			$publicFolder->setFolderId($folderId);
			$this->publicFolderMapper->insert($publicFolder);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Internal error'], Http::STATUS_BAD_REQUEST);
		}
		return new Http\DataResponse(['status' => 'success', 'item' => $publicFolder->getId()]);
	}

	/**
	 * @param int $folderId
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function deleteFolderPublicToken($folderId) {
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($folderId, $this->userId, $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$publicFolder = $this->publicFolderMapper->findByFolder($folderId);
		} catch (DoesNotExistException $e) {
			return new Http\DataResponse(['status' => 'success']);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Internal error'], Http::STATUS_BAD_REQUEST);
		}
		$this->publicFolderMapper->delete($publicFolder);
		return new Http\DataResponse(['status' => 'success', 'item' => $publicFolder->getId()]);
	}
}
