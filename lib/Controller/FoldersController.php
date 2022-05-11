<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

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
use OCA\Bookmarks\Exception\UnauthenticatedError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Service\Authorizer;
use OCA\Bookmarks\Service\BookmarkService;
use OCA\Bookmarks\Service\FolderService;
use OCA\Bookmarks\Service\TreeCacheManager;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;

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
	 * @var TreeMapper
	 */
	private $treeMapper;

	/**
	 * @var int|null
	 */
	private $rootFolderId;
	/**
	 * @var TreeCacheManager
	 */
	private $hashManager;
	/**
	 * @var FolderService
	 */
	private $folders;
	/**
	 * @var BookmarkService
	 */
	private $bookmarks;
	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	private $logger;

	/**
	 * FoldersController constructor.
	 *
	 * @param $appName
	 * @param $request
	 * @param FolderMapper $folderMapper
	 * @param PublicFolderMapper $publicFolderMapper
	 * @param SharedFolderMapper $sharedFolderMapper
	 * @param ShareMapper $shareMapper
	 * @param TreeMapper $treeMapper
	 * @param Authorizer $authorizer
	 * @param TreeCacheManager $hashManager
	 * @param FolderService $folders
	 * @param BookmarkService $bookmarks
	 * @param \Psr\Log\LoggerInterface $logger
	 */
	public function __construct($appName, $request, FolderMapper $folderMapper, PublicFolderMapper $publicFolderMapper, SharedFolderMapper $sharedFolderMapper, ShareMapper $shareMapper, TreeMapper $treeMapper, Authorizer $authorizer, TreeCacheManager $hashManager, FolderService $folders, BookmarkService $bookmarks, \Psr\Log\LoggerInterface $logger) {
		parent::__construct($appName, $request);
		$this->folderMapper = $folderMapper;
		$this->publicFolderMapper = $publicFolderMapper;
		$this->sharedFolderMapper = $sharedFolderMapper;
		$this->shareMapper = $shareMapper;
		$this->treeMapper = $treeMapper;
		$this->authorizer = $authorizer;
		$this->hashManager = $hashManager;
		$this->folders = $folders;
		$this->bookmarks = $bookmarks;
		$this->logger = $logger;

		$this->authorizer->setCORS(true);
	}

	/**
	 * @return int|null
	 */
	private function _getRootFolderId(): ?int {
		if ($this->rootFolderId !== null) {
			return $this->rootFolderId;
		}
		if ($this->authorizer->getUserId() !== null) {
			$this->rootFolderId = $this->folderMapper->findRootFolder($this->authorizer->getUserId())->getId();
		}
		if ($this->authorizer->getToken() !== null) {
			try {
				/**
				 * @var $publicFolder PublicFolder
				 */
				$publicFolder = $this->publicFolderMapper->find($this->authorizer->getToken());
				$this->rootFolderId = $publicFolder->getFolderId();
			} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
				$this->logger->error($e->getMessage()."\n".$e->getMessage());
			}
		}
		return $this->rootFolderId;
	}

	/**
	 * @param int $external
	 * @return int|null
	 */
	private function toInternalFolderId(int $external): ?int {
		if ($external === -1) {
			return $this->_getRootFolderId();
		}
		return $external;
	}

	/**
	 * @param int $internal
	 * @return int|null
	 */
	private function toExternalFolderId(int $internal): ?int {
		if ($internal === $this->_getRootFolderId()) {
			return -1;
		}
		return $internal;
	}

	/**
	 * @param $folder
	 * @return array
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException|UnsupportedOperation
	 */
	private function _returnFolderAsArray($folder): array {
		if ($folder instanceof Folder) {
			$returnFolder = $folder->toArray();
			$parent = $this->treeMapper->findParentOf(TreeMapper::TYPE_FOLDER, $folder->getId());
			$returnFolder['parent_folder'] = $this->toExternalFolderId($parent->getId());
			return $returnFolder;
		}
		if ($folder instanceof SharedFolder) {
			/**
			 * @var $share Share
			 */
			$share = $this->shareMapper->findByFolderAndUser($folder->getFolderId(), $folder->getUserId());
			$returnFolder = $folder->toArray();
			$returnFolder['id'] = $folder->getFolderId();
			$returnFolder['user_id'] = $share->getOwner();
			$parent = $this->treeMapper->findParentOf(TreeMapper::TYPE_SHARE, $folder->getId());
			$returnFolder['parent_folder'] = $this->toExternalFolderId($parent->getId());
			return $returnFolder;
		}

		throw new UnsupportedOperation('Expected folder or Shared Folder');
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
			return new JSONResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		try {
			$parent_folder = $this->toInternalFolderId($parent_folder);
			$folder = $this->folders->create($title, $parent_folder);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple parent folders found'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Unsupported operation'], Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find parent folder'], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(['status' => 'success', 'item' => $this->_returnFolderAsArray($folder)]);
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
			return new JSONResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		$folderId = $this->toInternalFolderId($folderId);
		try {
			$folder = $this->folders->findSharedFolderOrFolder($this->authorizer->getUserId(), $folderId);
			return new JSONResponse(['status' => 'success', 'item' => $this->_returnFolderAsArray($folder)]);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
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
			return new JSONResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		$folderId = $this->toInternalFolderId($folderId);
		try {
			$this->bookmarks->addToFolder($folderId, $bookmarkId);
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
			return new JSONResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		try {
			$folderId = $this->toInternalFolderId($folderId);
			$this->bookmarks->removeFromFolder($folderId, $bookmarkId);
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
			return new JSONResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}

		$folderId = $this->toInternalFolderId($folderId);
		if ($folderId === null) {
			return new JSONResponse(['status' => 'success']);
		}
		try {
			$this->folders->deleteSharedFolderOrFolder($this->authorizer->getUserId(), $folderId);
			return new JSONResponse(['status' => 'success']);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Unsupported operation'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'success']);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @param int $folderId
	 * @param string|null $title
	 * @param int|null $parent_folder
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @PupblicPage
	 */
	public function editFolder($folderId, $title = null, $parent_folder = null): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		if ($parent_folder !== null) {
			$parent_folder = $this->toInternalFolderId($parent_folder);
		}
		try {
			$folder = $this->folders->updateSharedFolderOrFolder($this->authorizer->getUserId(), $folderId, $title, $parent_folder);
			return new JSONResponse(['status' => 'success', 'item' => $this->_returnFolderAsArray($folder)]);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Unsupported operation'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UrlParseError $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Error changing owner of a bookmark: UrlParseError'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
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
			return new JSONResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		try {
			$folderId = $this->toInternalFolderId($folderId);
			$hash = $this->hashManager->hashFolder($this->authorizer->getUserId(), $folderId, $fields);
			$res = new JSONResponse(['status' => 'success', 'data' => $hash]);
			$res->addHeader('Cache-Control', 'no-cache, must-revalidate');
			$res->addHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
			return $res;
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Multiple objects found'], Http::STATUS_BAD_REQUEST);
		} catch (\JsonException $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Unsupported operation: '.$e->getMessage()], Http::STATUS_BAD_REQUEST);
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
	public function getFolderChildren($folderId, $layers = 0): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		$folderId = $this->toInternalFolderId($folderId);
		$children = $this->treeMapper->getChildren($folderId, $layers);
		$res = new JSONResponse(['status' => 'success', 'data' => $children]);
		$res->addHeader('Cache-Control', 'no-cache, must-revalidate');
		$res->addHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
		return $res;
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
			return new JSONResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		$folderId = $this->toInternalFolderId($folderId);
		$children = $this->treeMapper->getChildrenOrder($folderId, $layers);
		$res = new JSONResponse(['status' => 'success', 'data' => $children]);
		$res->addHeader('Cache-Control', 'no-cache, must-revalidate');
		$res->addHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
		return $res;
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
			return new JSONResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
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
	public function getFolders($root = -1, $layers = -1): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($root, $this->request))) {
			return new JSONResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		$internalRoot = $this->toInternalFolderId($root);
		$folders = $this->treeMapper->getSubFolders($internalRoot, $layers);
		if ($root === -1 || $root === '-1') {
			foreach ($folders as &$folder) {
				$folder['parent_folder'] = -1;
			}
		}
		$res = new JSONResponse(['status' => 'success', 'data' => $folders]);
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
			return new Http\DataResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
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
	 */
	public function createFolderPublicToken($folderId): DataResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		try {
			$token = $this->folders->createFolderPublicToken($folderId);
			return new Http\DataResponse(['status' => 'success', 'item' => $token]);
		} catch (MultipleObjectsReturnedException $e) {
			return new DataResponse(['status' => 'error', 'data' => 'Multiple objects returned'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		}
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
			return new Http\DataResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		try {
			$this->folders->deleteFolderPublicToken($folderId);
			return new Http\DataResponse(['status' => 'success']);
		} catch (DoesNotExistException $e) {
			return new Http\DataResponse(['status' => 'success']);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Internal error'], Http::STATUS_BAD_REQUEST);
		}
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
			return new Http\DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Mutliple objects found']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($share->getFolderId(), $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		return new Http\DataResponse(['status' => 'success', 'item' => $share->toArray()]);
	}

	/**
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function findSharedFolders(): DataResponse {
		$permissions = $this->authorizer->getPermissionsForFolder(-1, $this->request);
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $permissions)) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}

		$shares = $this->shareMapper->findByUser($this->authorizer->getUserId());
		return new Http\DataResponse(['status' => 'success', 'data' => array_map(function (Share $share) {
			return [
				'id' => $share->getFolderId(),
			];
		}, $shares)]);
	}

	/**
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function findShares(): DataResponse {
		$permissions = $this->authorizer->getPermissionsForFolder(-1, $this->request);
		if (Authorizer::hasPermission(Authorizer::PERM_READ, $permissions)) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}

		$shares = $this->shareMapper->findByOwner($this->authorizer->getUserId());
		return new Http\DataResponse(['status' => 'success', 'data' => array_map(function ($share) {
			return $share->toArray();
		}, $shares)]);
	}

	/**
	 * @param int $folderId
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @PublicPage
	 * @throws UnauthenticatedError
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
		if (Authorizer::hasPermission(Authorizer::PERM_READ, $permissions) && $this->authorizer->getUserId() !== null) {
			try {
				$this->folderMapper->find($folderId);
				$share = $this->shareMapper->findByFolderAndUser($folderId, $this->authorizer->getUserId());
			} catch (MultipleObjectsReturnedException $e) {
				return new DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_INTERNAL_SERVER_ERROR);
			} catch (DoesNotExistException $e) {
				return new DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
			}
			return new Http\DataResponse(['status' => 'success', 'data' => [$share->toArray()]]);
		}
		return new Http\DataResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
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
		$permissions = $this->authorizer->getPermissionsForFolder($folderId, $this->request);
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $permissions)) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}
		try {
			$canWrite = $canWrite && Authorizer::hasPermission(Authorizer::PERM_EDIT, $permissions);
			$share = $this->folders->createShare($folderId, $participant, $type, $canWrite, $canShare);
			return new Http\DataResponse(['status' => 'success', 'item' => $share->toArray()]);
		} catch (DoesNotExistException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Could not find folder'], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Multiple objects returned'], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UnsupportedOperation $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
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
			return new Http\DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Multiple objects found']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		$permissions = $this->authorizer->getPermissionsForFolder($share->getFolderId(), $this->request);
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $permissions)) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}


		$canWrite = $canWrite && Authorizer::hasPermission(Authorizer::PERM_EDIT, $permissions);
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
			return new Http\DataResponse(['status' => 'success']);
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Multiple objects found']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($share->getFolderId(), $this->request))) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Unauthorized'], Http::STATUS_FORBIDDEN);
		}

		try {
			$this->folders->deleteShare($shareId);
		} catch (UnsupportedOperation | DoesNotExistException | MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => 'Unsupported operation'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new Http\DataResponse(['status' => 'success']);
	}
}
