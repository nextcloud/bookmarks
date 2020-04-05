<?php

namespace OCA\Bookmarks\Controller;

use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\PublicFolder;
use OCA\Bookmarks\Db\PublicFolderMapper;
use OCA\Bookmarks\Db\Share;
use OCA\Bookmarks\Db\SharedFolder;
use OCA\Bookmarks\Db\SharedFolderMapper;
use OCA\Bookmarks\Db\ShareMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Exception\ChildrenOrderValidationError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Service\Authorizer;
use OCA\Bookmarks\Service\HashManager;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;

class FoldersController extends ApiController {
	private $userId;

	/** @var FolderMapper */
	private $folderMapper;

	/** @var PublicFolderMapper */
	private $publicFolderMapper;

	/** @var SharedFolderMapper */
	private $sharedFolderMapper;

	/** @var ShareMapper */
	private $shareMapper;

	/**
	 * @var Authorizer
	 */
	private $authorizer;

	/**
	 * @var IGroupManager
	 */
	private $groupManager;
	/**
	 * @var TreeMapper
	 */
	private $treeMapper;

	/**
	 * @var int|null
	 */
	private $rootFolderId = null;
	/**
	 * @var HashManager
	 */
	private $hashManager;

	/**
	 * FoldersController constructor.
	 *
	 * @param $appName
	 * @param $request
	 * @param $userId
	 * @param FolderMapper $folderMapper
	 * @param PublicFolderMapper $publicFolderMapper
	 * @param SharedFolderMapper $sharedFolderMapper
	 * @param ShareMapper $shareMapper
	 * @param TreeMapper $treeMapper
	 * @param Authorizer $authorizer
	 * @param IGroupManager $groupManager
	 * @param HashManager $hashManager
	 */
	public function __construct($appName, $request, $userId, FolderMapper $folderMapper, PublicFolderMapper $publicFolderMapper, SharedFolderMapper $sharedFolderMapper, ShareMapper $shareMapper, TreeMapper $treeMapper, Authorizer $authorizer, IGroupManager $groupManager, HashManager $hashManager) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->folderMapper = $folderMapper;
		$this->publicFolderMapper = $publicFolderMapper;
		$this->sharedFolderMapper = $sharedFolderMapper;
		$this->shareMapper = $shareMapper;
		$this->treeMapper = $treeMapper;
		$this->authorizer = $authorizer;
		$this->groupManager = $groupManager;
		$this->hashManager = $hashManager;

		if ($userId !== null) {
			$this->authorizer->setUserId($userId);
		}
	}

	/**
	 * @return int|null
	 */
	private function _getRootFolderId(): int {
		if ($this->rootFolderId !== null) {
			return $this->rootFolderId;
		}
		try {
			if ($this->userId !== null) {
				$this->rootFolderId = $this->folderMapper->findRootFolder($this->userId)->getId();
			}
			if ($this->authorizer->getToken() !== null) {
				/**
				 * @var $publicFolder PublicFolder
				 */
				$publicFolder = $this->publicFolderMapper->find($this->authorizer->getToken());
				$this->rootFolderId = $publicFolder->getFolderId();
			}
		} catch (DoesNotExistException $e) {
			// noop
		} catch (MultipleObjectsReturnedException $e) {
			// noop
		}
		return $this->rootFolderId;
	}

	/**
	 * @param int $external
	 * @return int
	 */
	private function toInternalFolderId(int $external): int {
		if ($external === -1) {
			return $this->_getRootFolderId();
		}
		return $external;
	}

	/**
	 * @param int $internal
	 * @return int
	 */
	private function toExternalFolderId(int $internal): int {
		if ($internal === $this->_getRootFolderId()) {
			return -1;
		}
		return $internal;
	}

	/**
	 * @param string $title
	 * @param int $parent_folder
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @PublicPage
	 */
	public function addFolder($title = '', $parent_folder = -1): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($parent_folder, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		$folder = new Folder();
		$folder->setTitle($title);
		$folder->setUserId($this->userId);

		try {
			$folder = $this->folderMapper->insert($folder);
			$parent_folder = $this->toInternalFolderId($parent_folder);
			$this->treeMapper->move(TreeMapper::TYPE_FOLDER, $folder->getId(), $parent_folder);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple parent folders found'], Http::STATUS_BAD_REQUEST);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Unsupported operation'], Http::STATUS_BAD_REQUEST);
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
	 * @PublicPage
	 */
	public function getFolder($folderId): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		try {
			/**
			 * @var $folder Folder
			 */
			$folder = $this->folderMapper->find($folderId);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple folders found'], Http::STATUS_BAD_REQUEST);
		}
		if ($folder->getUserId() !== $this->userId && !$this->authorizer->getToken()) {
			// We are not the owner of the folder so try to find the share entry
			$share = $this->findShare($folder);
			if ($share === null) {
				return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder share'], Http::STATUS_BAD_REQUEST);
			}
			if ($share->getFolderId() === $folder->getId()) {
				// Every sharee can rename their folder so we return their personal data here.
				try {
					$participantFolder = $this->sharedFolderMapper->findByFolderAndUser($folderId, $this->userId);
				} catch (DoesNotExistException $e) {
					return new JSONResponse(['status' => 'error', 'data' => 'Could not find shared folder'], Http::STATUS_BAD_REQUEST);
				} catch (MultipleObjectsReturnedException $e) {
					return new JSONResponse(['status' => 'error', 'data' => 'Could not find shared folder'], Http::STATUS_BAD_REQUEST);
				}
				$folder = $participantFolder->toArray();
				$folder['id'] = $folderId;
				$folder['user_id'] = $share->getOwner();
				/**
				 * @var $parents Folder[]
				 */
				$parents = $this->treeMapper->findParentsOf(TreeMapper::TYPE_SHARE, $share->getId());
				foreach ($parents as $parent) {
					if ($parent->getUserId() !== $this->userId) {
						continue;
					}
					$folder['parent_folder'] = $parent->getId();
				}
			}
			// else, just return the folder as we already have permission.
		} else {
			$returnFolder = $folder->toArray();
			try {
				$parent = $this->treeMapper->findParentOf(TreeMapper::TYPE_FOLDER, $folder->getId());
				$returnFolder['parent_folder'] = $parent->getId();
			} catch (DoesNotExistException $e) {
				// noop
			} catch (MultipleObjectsReturnedException $e) {
				// noop
			}
			$folder = $returnFolder;
		}
		if (!isset($folder['parent_folder'])) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find parent folder'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		$folder['parent_folder'] = $this->toExternalFolderId($folder['parent_folder']);
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
	 * @PublicPage
	 */
	public function addToFolder($folderId, $bookmarkId): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($folderId, $this->request)) &&
			!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($bookmarkId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		$folderId = $this->toInternalFolderId($folderId);
		try {
			$this->treeMapper->addToFolders(TreeMapper::TYPE_BOOKMARK, $bookmarkId, [$folderId]);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Unsupported operation'], Http::STATUS_BAD_REQUEST);
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
	 * @PublicPage
	 */
	public function removeFromFolder($folderId, $bookmarkId): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($folderId, $this->request)) &&
			!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($bookmarkId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$folderId = $this->toInternalFolderId($folderId);
			$this->treeMapper->removeFromFolders(TreeMapper::TYPE_BOOKMARK, $bookmarkId, [$folderId]);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Unsupported operation'], Http::STATUS_BAD_REQUEST);
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
	 * @PublicPage
	 */
	public function deleteFolder($folderId): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$folderId = $this->toInternalFolderId($folderId);
			/**
			 * @var $folder Folder
			 */
			$folder = $this->folderMapper->find($folderId);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'success']);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		if ($folder->getUserId() !== $this->userId) {
			// We are not the owner of the folder so try to find the share entry
			try {
				$share = $this->findShare($folder);
				if ($share === null) {
					return new JSONResponse(['status' => 'error', 'data' => 'Could not find shared folder'], Http::STATUS_BAD_REQUEST);
				}
				if ($share->getFolderId() === $folderId) {
					// Can't delete the actual folder, so we'll delete our share :shrug:
					$sharedFolder = $this->sharedFolderMapper->findByFolderAndUser($share->getFolderId(), $this->userId);
					$this->sharedFolderMapper->delete($sharedFolder);
					return new JSONResponse(['status' => 'success']);
				}
				// Otherwise we're good to go.
			} catch (DoesNotExistException $e) {
				return new JSONResponse(['status' => 'error', 'data' => 'Could not find shared folder'], Http::STATUS_BAD_REQUEST);
			} catch (MultipleObjectsReturnedException $e) {
				return new JSONResponse(['status' => 'error', 'data' => 'Could not find shared folder'], Http::STATUS_BAD_REQUEST);
			}
		}
		try {
			$this->treeMapper->deleteEntry(TreeMapper::TYPE_FOLDER, $folder->getId());
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Unsupported operation'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find item to delete'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new JSONResponse(['status' => 'success']);
	}

	/**
	 * @param $folder
	 * @return Share
	 */
	private function findShare(Folder $folder): Share {
		$shares = $this->shareMapper->findByOwnerAndUser($folder->getUserId(), $this->userId);
		foreach ($shares as $share) {
			if ($share->getFolderId() === $folder->getId() || $this->treeMapper->hasDescendant($share->getFolderId(), TreeMapper::TYPE_FOLDER, $folder->getId())) {
				return $share;
			}
		}
		return null;
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
	 * @PupblicPage
	 */
	public function editFolder($folderId, $title = null, $parent_folder = null): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		if ($parent_folder !== null) {
			$parent_folder = $this->toInternalFolderId($parent_folder);
		}
		try {
			/**
			 * @var $folder Folder
			 */
			$folder = $this->folderMapper->find($folderId);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		if ($folder->getUserId() !== $this->userId) {
			// We don't own the folder
			// We cannot alter the shared folder directly, instead we have to edit our instance of the share
			try {
				$sharedFolder = $this->sharedFolderMapper->findByFolderAndUser($folderId, $this->userId);
				if (isset($title)) {
					$sharedFolder->setTitle($title);
					$this->sharedFolderMapper->update($sharedFolder);
				}
				if (isset($parent_folder)) {
					$this->treeMapper->move(TreeMapper::TYPE_SHARE, $sharedFolder->getId(), $parent_folder);
				}
				$folder = $sharedFolder->toArray();
				$folder['id'] = $folderId;
				return new JSONResponse(['status' => 'success', 'item' => $folder]);
			} catch (DoesNotExistException $e) {
				// noop
			} catch (MultipleObjectsReturnedException $e) {
				return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_INTERNAL_SERVER_ERROR);
			} catch (UnsupportedOperation $e) {
				return new JSONResponse(['status' => 'error', 'data' => 'Unsupported operation'], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
			// It's a subfolder of the share, so we can manipulate it. Go with the flow
		}
		if (isset($title)) {
			$folder->setTitle($title);
			$folder = $this->folderMapper->update($folder);
		}
		if (isset($parent_folder)) {
			try {
				$this->treeMapper->move(TreeMapper::TYPE_FOLDER, $folder->getId(), $parent_folder);
			} catch (MultipleObjectsReturnedException $e) {
				return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_INTERNAL_SERVER_ERROR);
			} catch (UnsupportedOperation $e) {
				return new JSONResponse(['status' => 'error', 'data' => 'Unsupported operation'], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
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
	 * @PublicPage
	 */
	public function hashFolder($folderId, $fields = ['title', 'url']): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$folderId = $this->toInternalFolderId($folderId);
			$hash = $this->hashManager->hashFolder($this->userId, $folderId, $fields);
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
	 * @PublicPage
	 */
	public function getFolderChildrenOrder($folderId, $layers = 0): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		$folderId = $this->toInternalFolderId($folderId);
		$children = $this->treeMapper->getChildrenOrder($folderId, $layers);
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
	 * @PublicPage
	 */
	public function setFolderChildrenOrder($folderId, $data = []): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		$folderId = $this->toInternalFolderId($folderId);
		try {
			$this->treeMapper->setChildrenOrder($folderId, $data);
			return new JSONResponse(['status' => 'success']);
		} catch (ChildrenOrderValidationError $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'invalid children order: ' . $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @param int $root the id of the root folder whose descendants to return
	 * @param int $layers the number of layers of hierarchy too return
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @PublicPage
	 * @return JSONResponse
	 */
	public function getFolders($root = -1, $layers = 0): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($root, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		$root = $this->toInternalFolderId($root);
		$res = new JSONResponse(['status' => 'success', 'data' => $this->treeMapper->getSubFolders($root, $layers)]);
		$res->addHeader('Cache-Control', 'no-cache, must-revalidate');
		$res->addHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
		return $res;
	}

	/**
	 * @param int $folderId
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @PublicPage
	 */
	public function getFolderPublicToken($folderId): DataResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$publicFolder = $this->publicFolderMapper->findByFolder($folderId);
		} catch (DoesNotExistException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_NOT_FOUND);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Multiple objects returned'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new Http\DataResponse(['status' => 'success', 'item' => $publicFolder->getId()]);
	}

	/**
	 * @param int $folderId
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @PublicPage
	 * @throws MultipleObjectsReturnedException
	 */
	public function createFolderPublicToken($folderId): DataResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$this->folderMapper->find($folderId);
		} catch (MultipleObjectsReturnedException $e) {
			return new DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_INTERNAL_SERVER_ERROR);
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
	 * @PublicPage
	 */
	public function deleteFolderPublicToken($folderId): DataResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$publicFolder = $this->publicFolderMapper->findByFolder($folderId);
		} catch (DoesNotExistException $e) {
			return new Http\DataResponse(['status' => 'success']);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Internal error'], Http::STATUS_BAD_REQUEST);
		}
		$this->publicFolderMapper->delete($publicFolder);
		return new Http\DataResponse(['status' => 'success']);
	}

	/**
	 * @param $shareId
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @PublicPage
	 */
	public function getShare($shareId): DataResponse {
		try {
			$share = $this->shareMapper->find($shareId);
		} catch (DoesNotExistException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($share->getFolderId(), $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		return new Http\DataResponse(['status' => 'success', 'item' => $share->toArray()]);
	}

	/**
	 * @param int $folderId
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @PublicPage
	 */
	public function getShares($folderId): DataResponse {
		$permissions = $this->authorizer->getPermissionsForFolder($folderId, $this->request);
		if (Authorizer::hasPermission(Authorizer::PERM_RESHARE, $permissions)) {
			try {
				$this->folderMapper->find($folderId);
			} catch (MultipleObjectsReturnedException $e) {
				return new DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_INTERNAL_SERVER_ERROR);
			} catch (DoesNotExistException $e) {
				return new DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
			}
			$shares = $this->shareMapper->findByFolder($folderId);
			return new Http\DataResponse(['status' => 'success', 'data' => array_map(static function (Share $share) {
				return $share->toArray();
			}, $shares)]);
		}
		if (Authorizer::hasPermission(Authorizer::PERM_READ, $permissions)) {
			try {
				$this->folderMapper->find($folderId);
				// TODO: $share = $this->shareMapper->findByDescendantFolderAndUser($folderId, $this->authorizer->getUserId());
				$share = $this->shareMapper->findByFolderAndUser($folderId, $this->authorizer->getUserId());
			} catch (MultipleObjectsReturnedException $e) {
				return new DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_INTERNAL_SERVER_ERROR);
			} catch (DoesNotExistException $e) {
				return new DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
			}
			return new Http\DataResponse(['status' => 'success', 'data' => [$share->toArray()]]);
		}
		return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
	}

	/**
	 * @param int $folderId
	 * @param $participant
	 * @param int $type
	 * @param bool $canWrite
	 * @param bool $canShare
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @PublicPage
	 */
	public function createShare($folderId, $participant, int $type, $canWrite = false, $canShare = false): DataResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		try {
			/**
			 * @var $folder Folder
			 */
			$folder = $this->folderMapper->find($folderId);
		} catch (DoesNotExistException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Multiple objects returned'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$share = new Share();
		$share->setFolderId($folderId);
		$share->setOwner($folder->getUserId());
		$share->setParticipant($participant);
		if ($type !== \OCP\Share\IShare::TYPE_USER && $type !== \OCP\Share\IShare::TYPE_GROUP) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Invalid share type'], Http::STATUS_BAD_REQUEST);
		}
		$share->setType($type);
		$share->setCanWrite($canWrite);
		$share->setCanShare($canShare);
		$this->shareMapper->insert($share);

		try {
			if ($type === \OCP\Share\IShare::TYPE_USER) {
				$this->_addSharedFolder($share, $folder, $participant);
			} else if ($type === \OCP\Share\IShare::TYPE_GROUP) {
				$group = $this->groupManager->get($participant);
				$users = $group->getUsers();
				foreach ($users as $user) {
					$this->_addSharedFolder($share, $folder, $user->getUID());
				}
			}
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Multiple objects returned'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UnsupportedOperation $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Unsupported operation'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new Http\DataResponse(['status' => 'success', 'item' => $share->toArray()]);
	}

	/**
	 * @param Share $share
	 * @param Folder $folder
	 * @param string $userId
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	private function _addSharedFolder(Share $share, Folder $folder, string $userId): void {
		$sharedFolder = new SharedFolder();
		$sharedFolder->setShareId($share->getId());
		$sharedFolder->setTitle($folder->getTitle());
		$sharedFolder->setUserId($userId);
		$rootFolder = $this->folderMapper->findRootFolder($userId);
		$this->sharedFolderMapper->insert($sharedFolder);
		$this->treeMapper->move(TreeMapper::TYPE_SHARE, $sharedFolder->getId(), $rootFolder->getId());
	}

	/**
	 * @param $shareId
	 * @param bool $canWrite
	 * @param bool $canShare
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @PublicPage
	 */
	public function editShare($shareId, $canWrite = false, $canShare = false): Http\DataResponse {
		try {
			$share = $this->shareMapper->find($shareId);
		} catch (DoesNotExistException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($share->getFolderId(), $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}

		$share->setCanWrite($canWrite);
		$share->setCanShare($canShare);
		$this->shareMapper->update($share);

		return new Http\DataResponse(['status' => 'success', 'item' => $share->toArray()]);
	}

	/**
	 * @param int $shareId
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @PublicPage
	 */
	public function deleteShare($shareId): DataResponse {
		try {
			$share = $this->shareMapper->find($shareId);
		} catch (DoesNotExistException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($share->getFolderId(), $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		$sharedFolders = $this->sharedFolderMapper->findByShare($shareId);
		try {
			foreach ($sharedFolders as $sharedFolder) {
				$this->sharedFolderMapper->delete($sharedFolder);
				$this->treeMapper->deleteEntry(TreeMapper::TYPE_SHARE, $sharedFolder->getId());
			}
		} catch (UnsupportedOperation $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Unsupported operation'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (DoesNotExistException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Unsupported operation'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Unsupported operation'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		$this->shareMapper->delete($share);
		return new Http\DataResponse(['status' => 'success']);
	}
}
