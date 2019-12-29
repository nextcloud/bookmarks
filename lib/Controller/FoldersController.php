<?php

namespace OCA\Bookmarks\Controller;

use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\PublicFolder;
use OCA\Bookmarks\Db\PublicFolderMapper;
use OCA\Bookmarks\Db\Share;
use OCA\Bookmarks\Db\SharedFolder;
use OCA\Bookmarks\Db\SharedFolderMapper;
use OCA\Bookmarks\Db\ShareMapper;
use OCA\Bookmarks\Exception\ChildrenOrderValidationError;
use OCA\Bookmarks\Service\Authorizer;
use OCP\IGroup;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
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
	 * @var BookmarkMapper
	 */
	private $bookmarkMapper;

	/**
	 * @var Authorizer
	 */
	private $authorizer;

	/**
	 * @var IGroupManager
	 */
	private $groupManager;

	public function __construct($appName, $request, $userId, FolderMapper $folderMapper, BookmarkMapper $bookmarkMapper, PublicFolderMapper $publicFolderMapper, SharedFolderMapper $sharedFolderMapper, ShareMapper $shareMapper, Authorizer $authorizer, IGroupManager $groupManager) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->folderMapper = $folderMapper;
		$this->bookmarkMapper = $bookmarkMapper;
		$this->publicFolderMapper = $publicFolderMapper;
		$this->sharedFolderMapper = $sharedFolderMapper;
		$this->shareMapper = $shareMapper;
		$this->authorizer = $authorizer;
		$this->groupManager = $groupManager;
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
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($parent_folder, $this->userId, $this->request))) {
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
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($folderId, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$folder = $this->folderMapper->find($folderId);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple folders found'], Http::STATUS_BAD_REQUEST);
		}
		if ($folder->getUserId() !== $this->userId && !$this->authorizer->getToken()) {
			// We are not the owner of the folder so try to find the share entry
			try {
				$share = $this->findShare($folder);
			} catch (DoesNotExistException $e) {
				return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder share'], Http::STATUS_BAD_REQUEST);
			} catch (MultipleObjectsReturnedException $e) {
				return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder share'], Http::STATUS_BAD_REQUEST);
			}
			if (is_null($share)) {
				return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder share'], Http::STATUS_BAD_REQUEST);
			}
			if ($share->getFolderId() === $folder->getId()) {
				// Every sharee can rename their folder so we return their personal data here.
				try {
					$participantFolder = $this->sharedFolderMapper->findByFolderAndUser($folderId, $this->userId);
				} catch (DoesNotExistException $e) {
					return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
				} catch (MultipleObjectsReturnedException $e) {
					return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
				}
				$folder = $participantFolder->toArray();
				$folder['id'] = $folderId;
				return new JSONResponse(['status' => 'success', 'item' => $folder]);
			}
			// else, just return the folder as we already have permission.
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
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($folderId, $this->userId, $this->request)) &&
			!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($bookmarkId, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$this->folderMapper->addToFolders($bookmarkId, [$folderId]);
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
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($folderId, $this->userId, $this->request)) &&
			!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($bookmarkId, $this->userId, $this->request))) {
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
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($folderId, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$folder = $this->folderMapper->find($folderId);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'success']);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_BAD_REQUEST);
		}
		if ($folder->getUserId() !== $this->userId) {
			// We are not the owner of the folder so try to find the share entry
			try {
				$share = $this->findShare($folder);
				if (is_null($share)) {
					return new JSONResponse(['status' => 'error', 'data' => 'Could not find shared folder'], Http::STATUS_BAD_REQUEST);
				}
				if ($share->getFolderId() === $folderId) {
					// Can't delete the actual folder, so we'll delete our share :shrug:
					$sharedFolder = $this->sharedFolderMapper->findByFolderAndUser($share->getFolderId(), $this->userId);
					$this->sharedFolderMapper->delete($sharedFolder);
					$this->shareMapper->delete($share);
					return new JSONResponse(['status' => 'success']);
				}
				// Otherwise we're good to go.
			} catch (DoesNotExistException $e) {
				return new JSONResponse(['status' => 'error', 'data' => 'Could not find shared folder'], Http::STATUS_BAD_REQUEST);
			} catch (MultipleObjectsReturnedException $e) {
				return new JSONResponse(['status' => 'error', 'data' => 'Could not find shared folder'], Http::STATUS_BAD_REQUEST);
			}
		}
		$this->folderMapper->delete($folder);
		return new JSONResponse(['status' => 'success']);
	}

	/**
	 * @param $folder
	 * @return Share
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	private function findShare($folder) {
		$shares = $this->shareMapper->findByOwnerAndUser($folder->getUserId(), $this->userId);
		foreach ($shares as $share) {
			if ($share->getFolderId() === $folder->getId() || $this->folderMapper->hasDescendantFolder($share->getFolderId(), $folder->getId())) {
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
	 */
	public function editFolder($folderId, $title = null, $parent_folder = null) {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($folderId, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$folder = $this->folderMapper->find($folderId);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_BAD_REQUEST);
		}
		if ($folder->getUserId() !== $this->userId) {
			// We don't own the folder
			try {
				$share = $this->findShare($folder);
				if (is_null($share)) {
					return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder share'], Http::STATUS_INTERNAL_SERVER_ERROR);
				}
				if ($share->getFolderId() === $folderId) {
					// We cannot alter the shared folder directly, instead we have to edit our instance of the share
					$sharedFolder = $this->sharedFolderMapper->findByFolderAndUser($folderId, $this->userId);
					if (isset($title)) $sharedFolder->setTitle($title);
					if (isset($parent_folder)) $sharedFolder->setParentFolder($parent_folder);
					$this->sharedFolderMapper->update($sharedFolder);
					$folder = $sharedFolder->toArray();
					$folder['id'] = $folderId;
					return new JSONResponse(['status' => 'success', 'item' => $folder]);
				}
				// It's a subfolder of the share, so we can manipulate it. Go with the flow
			} catch (DoesNotExistException $e) {
				return new JSONResponse(['status' => 'error', 'data' => 'Could not update folder'], Http::STATUS_INTERNAL_SERVER_ERROR);
			} catch (MultipleObjectsReturnedException $e) {
				return new JSONResponse(['status' => 'error', 'data' => 'Could not update folder'], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		}
		if (isset($title)) $folder->setTitle($title);
		if (isset($parent_folder)) $folder->setParentFolder($parent_folder);
		if ($parent_folder === -1 && $folder->getUserId() !== $this->userId) {
			return new JSONResponse(['status' => 'error', 'data' => 'Cannot move folders between different roots'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		try {
			$parentFolder = $this->folderMapper->find($folderId);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find parent folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_BAD_REQUEST);
		}
		if ($folder->getUserId() !== $parentFolder->getUserId()) {
			return new JSONResponse(['status' => 'error', 'data' => 'Cannot move folders between different user roots'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		try {
			$folder = $this->folderMapper->update($folder);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not update folder'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not update folder'], Http::STATUS_INTERNAL_SERVER_ERROR);
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
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($folderId, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			if ($folderId !== -1 && $folderId !== '-1') {
				$folder = $this->folderMapper->find($folderId);
				if ($folder->getUserId() !== $this->userId) {
					return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
				}
				$hash = $this->folderMapper->hashFolder($this->userId, $folderId, $fields);
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
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($folderId, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		$children = $this->folderMapper->getUserFolderChildren($this->userId, $folderId, $layers);
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
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($folderId, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$this->folderMapper->setUserFolderChildren($this->userId, $folderId, $data);
			return new JSONResponse(['status' => 'success']);
		} catch (ChildrenOrderValidationError $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'invalid children order'], Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
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
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($root, $this->userId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}

		$res = new JSONResponse(['status' => 'success', 'data' => $this->folderMapper->getSubFolders($root, $layers)]);
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
	 */
	public function getFolderPublicToken($folderId) {
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($folderId, $this->userId, $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
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
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
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
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		$publicFolder = $this->publicFolderMapper->findByFolder($folderId);
		$this->publicFolderMapper->delete($publicFolder);
		return new Http\DataResponse(['status' => 'success', 'item' => $publicFolder->getId()]);
	}

	/**
	 * @param $shareId
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function getShare($shareId) {
		try {
			$share = $this->shareMapper->find($shareId);
		} catch (DoesNotExistException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($share->getFolderId(), $this->userId, $this->request))) {
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
	 */
	public function getShares($folderId) {
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($folderId, $this->userId, $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		$shares = $this->shareMapper->findByFolder($folderId);
		return new Http\DataResponse(['status' => 'success', 'data' => array_map(function(Share $share) {
			return $share->toArray();
		}, $shares)]);
	}

	/**
	 * @param int $folderId
	 * @param $participant
	 * @param $type
	 * @param bool $canWrite
	 * @param bool $canShare
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function createShare($folderId, $participant, $type, $canWrite = false, $canShare = false) {
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($folderId, $this->userId, $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$folder = $this->folderMapper->find($folderId);
		} catch (DoesNotExistException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}

		$share = new Share();
		$share->setFolderId($folderId);
		$share->setOwner($folder->getUserId());
		$share->setParticipant($participant);
		if ($type !== ShareMapper::TYPE_USER && $type !== ShareMapper::TYPE_GROUP) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Invalid share type'], Http::STATUS_BAD_REQUEST);
		}
		$share->setType($type);
		$share->setCanWrite($canWrite);
		$share->setCanShare($canShare);
		$this->shareMapper->insert($share);

		if ($type === ShareMapper::TYPE_USER) {
			$this->_addSharedFolder($share, $folder, $participant);
		}else if ($type === ShareMapper::TYPE_GROUP){
			$group = $this->groupManager->get($participant);
			$users = $group->getUsers();
			foreach($users as $user) {
				$this->_addSharedFolder($share, $folder, $user->getUID());
			}
		}
		return new Http\DataResponse(['status' => 'success', 'item' => $share->toArray()]);
	}

	/**
	 * @param Share $share
	 * @param Folder $folder
	 * @param string $userId
	 */
	private function _addSharedFolder(Share $share, Folder $folder, string $userId) {
		$sharedFolder = new SharedFolder();
		$sharedFolder->setShareId($share->getId());
		$sharedFolder->setTitle($folder->getTitle());
		$sharedFolder->setParentFolder(-1);
		$sharedFolder->setUserId($userId);
		$sharedFolder->setIndex(0);
		$this->sharedFolderMapper->insert($sharedFolder);
	}

	/**
	 * @param $shareId
	 * @param bool $canWrite
	 * @param bool $canShare
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 */
	public function editShare($shareId, $canWrite = false, $canShare = false) {
		try {
			$share = $this->shareMapper->find($shareId);
		} catch (DoesNotExistException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($share->getFolderId(), $this->userId, $this->request))) {
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
	 */
	public function deleteShare($shareId) {
		try {
			$share = $this->shareMapper->find($shareId);
		} catch (DoesNotExistException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($share->getFolderId(), $this->userId, $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Insufficient permissions'], Http::STATUS_BAD_REQUEST);
		}
		$sharedFolders = $this->sharedFolderMapper->findByShare($shareId);
		foreach($sharedFolders as $sharedFolder) {
			$this->sharedFolderMapper->delete($sharedFolder);
		}
		$this->shareMapper->delete($share);
		return new Http\DataResponse(['status' => 'success']);
	}
}
