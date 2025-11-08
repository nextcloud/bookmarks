<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
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
use OCA\Bookmarks\Db\ShareMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\ChildrenOrderValidationError;
use OCA\Bookmarks\Exception\UnauthenticatedError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
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
use OCP\DB\Exception;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class FoldersController extends ApiController {
	private ?int $rootFolderId = null;

	/**
	 * FoldersController constructor.
	 *
	 * @param $appName
	 * @param $request
	 * @param FolderMapper $folderMapper
	 * @param PublicFolderMapper $publicFolderMapper
	 * @param ShareMapper $shareMapper
	 * @param TreeMapper $treeMapper
	 * @param Authorizer $authorizer
	 * @param TreeCacheManager $hashManager
	 * @param FolderService $folders
	 * @param BookmarkService $bookmarks
	 * @param LoggerInterface $logger
	 * @param IUserManager $userManager
	 */
	public function __construct(
		$appName,
		$request,
		private FolderMapper $folderMapper,
		private PublicFolderMapper $publicFolderMapper,
		private ShareMapper $shareMapper,
		private TreeMapper $treeMapper,
		private Authorizer $authorizer,
		private TreeCacheManager $hashManager,
		private FolderService $folders,
		private BookmarkService $bookmarks,
		private LoggerInterface $logger,
		private IUserManager $userManager,
	) {
		parent::__construct($appName, $request);
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
				 * @var PublicFolder $publicFolder
				 */
				$publicFolder = $this->publicFolderMapper->find($this->authorizer->getToken());
				$this->rootFolderId = $publicFolder->getFolderId();
			} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
				$this->logger->error($e->getMessage() . "\n" . $e->getMessage());
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
			$returnFolder['userDisplayName'] = $this->userManager->get($returnFolder['userId'])->getDisplayName();
			return $returnFolder;
		}
		if ($folder instanceof SharedFolder) {
			$share = $this->shareMapper->findByFolderAndUser($folder->getFolderId(), $folder->getUserId());
			$returnFolder = $folder->toArray();
			$returnFolder['id'] = $folder->getFolderId();
			$returnFolder['userId'] = $share->getOwner();
			$parent = $this->treeMapper->findParentOf(TreeMapper::TYPE_SHARE, $folder->getId());
			$returnFolder['parent_folder'] = $this->toExternalFolderId($parent->getId());
			$returnFolder['userDisplayName'] = $this->userManager->get($returnFolder['userId'])->getDisplayName();
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
	 * @BruteForceProtection
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function addFolder(string $title = '', int $parent_folder = -1): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_WRITE, $this->authorizer->getPermissionsForFolder($parent_folder, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Could not find parent folder']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		}
		try {
			$parent = $this->toInternalFolderId($parent_folder);
			if ($parent === null) {
				$res = new JSONResponse(['status' => 'error', 'data' => ['Could not find parent folder']], Http::STATUS_BAD_REQUEST);
				$res->throttle();
				return $res;
			}
			$folder = $this->folders->create($title, $parent);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Multiple parent folders found']], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Unsupported operation']], Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Could not find parent folder']], Http::STATUS_BAD_REQUEST);
		} catch (Exception $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new JSONResponse(['status' => 'success', 'item' => $this->_returnFolderAsArray($folder)]);
	}

	/**
	 * @param int $folderId
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function getFolder($folderId): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Could not find folder']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		}
		$folderId = $this->toInternalFolderId($folderId);
		if ($folderId === null) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Could not find folder']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		}
		try {
			$folder = $this->folders->findSharedFolderOrFolder($this->authorizer->getUserId(), $folderId);
			return new JSONResponse(['status' => 'success', 'item' => $this->_returnFolderAsArray($folder)]);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Could not find folder']], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Multiple objects found']], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @param int $folderId
	 * @param int $bookmarkId
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function addToFolder($folderId, $bookmarkId): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_WRITE, $this->authorizer->getPermissionsForFolder($folderId, $this->request))
			|| !Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($bookmarkId, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		$folderId = $this->toInternalFolderId($folderId);
		if ($folderId === null) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		try {
			$this->bookmarks->addToFolder($folderId, $bookmarkId);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Unsupported operation']], Http::STATUS_BAD_REQUEST);
		} catch (AlreadyExistsError $e) {
			// noop
		} catch (UrlParseError $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Malformed URL']], Http::STATUS_BAD_REQUEST);
		} catch (UserLimitExceededError $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['User limit exceeded']], Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
		} catch (MultipleObjectsReturnedException|Exception $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
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
	 * @BruteForceProtection
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function removeFromFolder($folderId, $bookmarkId, bool $hardDelete = false): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_WRITE, $this->authorizer->getPermissionsForFolder($folderId, $this->request))
			|| !Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($bookmarkId, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		}
		try {
			$folderId = $this->toInternalFolderId($folderId);
			if ($folderId === null) {
				$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
				$res->throttle();
				return $res;
			}
			$this->bookmarks->removeFromFolder($folderId, $bookmarkId, $hardDelete);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Multiple objects found']], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Unsupported operation']], Http::STATUS_BAD_REQUEST);
		} catch (Exception $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
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
	 * @BruteForceProtection(actions: 'bookmarks#undeleteFromFolder')
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function undeleteFromFolder(int $folderId, int $bookmarkId): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_WRITE, $this->authorizer->getPermissionsForFolder($folderId, $this->request))
			|| !Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForBookmark($bookmarkId, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Unauthorized']], Http::STATUS_FORBIDDEN);
			$res->throttle();
			return $res;
		}
		try {
			$folderId = $this->toInternalFolderId($folderId);
			if ($folderId === null) {
				$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
				$res->throttle();
				return $res;
			}
			$this->bookmarks->undeleteInFolder($folderId, $bookmarkId);
			return new JSONResponse(['status' => 'success']);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Multiple objects found']], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Unsupported operation']], Http::STATUS_BAD_REQUEST);
		} catch (Exception $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_BAD_REQUEST);
		}
	}


	/**
	 * @param int $folderId
	 * @param bool $hardDelete
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function deleteFolder(int $folderId, bool $hardDelete = false): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			$res = new JSONResponse(['status' => 'success']);
			$res->throttle();
			return $res;
		}

		$folderId = $this->toInternalFolderId($folderId);
		if ($folderId === null) {
			$res = new JSONResponse(['status' => 'success']);
			$res->throttle();
			return $res;
		}
		try {
			$this->folders->deleteSharedFolderOrFolder($this->authorizer->getUserId(), $folderId, $hardDelete);
			return new JSONResponse(['status' => 'success']);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Unsupported operation']], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'success']);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Multiple objects found']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @param int $folderId
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function undeleteFolder(int $folderId): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			$res = new JSONResponse(['status' => 'success']);
			$res->throttle();
			return $res;
		}

		$folderId = $this->toInternalFolderId($folderId);
		if ($folderId === null) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Could not find folder']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		try {
			$this->folders->undelete($this->authorizer->getUserId(), $folderId);
			return new JSONResponse(['status' => 'success']);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Unsupported operation']], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Could not find folder']], Http::STATUS_NOT_FOUND);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Multiple objects found']], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (Exception $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
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
	 * @BruteForceProtection
	 * @PupblicPage
	 * @throws UnauthenticatedError
	 */
	public function editFolder(int $folderId, ?string $title = null, ?int $parent_folder = null): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_EDIT, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		if ($parent_folder !== null) {
			$parent_folder = $this->toInternalFolderId($parent_folder);
			if ($parent_folder === null) {
				$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
				$res->throttle();
				return $res;
			}
		}
		try {
			$folder = $this->folders->updateSharedFolderOrFolder($this->authorizer->getUserId(), $folderId, $title, $parent_folder);
			return new JSONResponse(['status' => 'success', 'item' => $this->_returnFolderAsArray($folder)]);
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
		} catch (MultipleObjectsReturnedException|Exception $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Unsupported operation']], Http::STATUS_BAD_REQUEST);
		} catch (UrlParseError $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Error changing owner of a bookmark: UrlParseError']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @param int $folderId
	 * @param string[] $fields
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function hashFolder(int $folderId, array $fields = ['title', 'url'], string $hashFn = 'sha256'): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Could not find folder']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		}
		if (!in_array($hashFn, ['sha256', 'murmur3a', 'xxh32'], true)) {
			return new JSONResponse(['status' => 'error', 'data' => ['Unsupported hash function']], Http::STATUS_BAD_REQUEST);
		}
		try {
			$folderId = $this->toInternalFolderId($folderId);
			if ($folderId === null) {
				$res = new JSONResponse(['status' => 'error', 'data' => ['Could not find folder']], Http::STATUS_BAD_REQUEST);
				$res->throttle();
				return $res;
			}
			$hash = $this->hashManager->hashFolder($this->authorizer->getUserId(), $folderId, $fields, $hashFn);
			$res = new JSONResponse(['status' => 'success', 'data' => $hash]);
			$res->addHeader('Cache-Control', 'no-cache, must-revalidate');
			$res->addHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
			return $res;
		} catch (DoesNotExistException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Could not find folder']], Http::STATUS_BAD_REQUEST);
		} catch (MultipleObjectsReturnedException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Multiple objects found']], Http::STATUS_BAD_REQUEST);
		} catch (\JsonException $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (UnsupportedOperation $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Unsupported operation']], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @param int $folderId
	 * @param int $layers
	 * @return JSONResponse
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function getFolderChildren(int $folderId, int $layers = 0): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		$folderId = $this->toInternalFolderId($folderId);
		if ($folderId === null) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
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
	 * @BruteForceProtection
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function getFolderChildrenOrder(int $folderId, int $layers = 0): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		$folderId = $this->toInternalFolderId($folderId);
		if ($folderId === null) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		try {
			$children = $this->treeMapper->getChildrenOrder($folderId, $layers);
		} catch (Exception $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
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
	 *
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function setFolderChildrenOrder(int $folderId, array $data = []): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_WRITE, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		$folderId = $this->toInternalFolderId($folderId);
		if ($folderId === null) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		try {
			$this->treeMapper->setChildrenOrder($folderId, $data);
			return new JSONResponse(['status' => 'success']);
		} catch (ChildrenOrderValidationError $e) {
			return new JSONResponse(['status' => 'error', 'data' => ['invalid children order: ' . $e->getMessage()]], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @param int $root the id of the root folder whose descendants to return
	 * @param int $layers the number of layers of hierarchy too return
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection
	 * @PublicPage
	 * @return JSONResponse
	 * @throws UnauthenticatedError
	 */
	public function getFolders(int $root = -1, int $layers = -1): JSONResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($root, $this->request))) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		$internalRoot = $this->toInternalFolderId($root);
		if ($internalRoot === null) {
			$res = new JSONResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		$folders = $this->treeMapper->getSubFolders($internalRoot, $layers, $root === -1 ? false : null);
		if ($root === -1) {
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
	 * @BruteForceProtection
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function getFolderPublicToken(int $folderId): DataResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			$res = new Http\DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		try {
			$publicFolder = $this->publicFolderMapper->findByFolder($folderId);
		} catch (DoesNotExistException $e) {
			$res = new Http\DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new Http\DataResponse(['status' => 'success', 'item' => $publicFolder->getId()]);
	}

	/**
	 * @param int $folderId
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function createFolderPublicToken(int $folderId): DataResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			$res = new Http\DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		try {
			$token = $this->folders->createFolderPublicToken($folderId);
			return new Http\DataResponse(['status' => 'success', 'item' => $token]);
		} catch (MultipleObjectsReturnedException $e) {
			return new DataResponse(['status' => 'error', 'data' => ['Multiple objects returned']], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (DoesNotExistException $e) {
			$res = new DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		} catch (UnsupportedOperation $e) {
			return new DataResponse(['status' => 'error', 'data' => ['Unsupported operation']], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @param int $folderId
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function deleteFolderPublicToken(int $folderId): DataResponse {
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($folderId, $this->request))) {
			$res = new DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		}
		try {
			$this->folders->deleteFolderPublicToken($folderId);
			return new Http\DataResponse(['status' => 'success']);
		} catch (DoesNotExistException $e) {
			$res = new DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		} catch (MultipleObjectsReturnedException|Exception $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @param $shareId
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function getShare($shareId): DataResponse {
		try {
			$share = $this->shareMapper->find($shareId);
		} catch (DoesNotExistException $e) {
			$res = new Http\DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $this->authorizer->getPermissionsForFolder($share->getFolderId(), $this->request))) {
			$res = new Http\DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_NOT_FOUND);
			$res->throttle();
			return $res;
		}
		return new Http\DataResponse(['status' => 'success', 'item' => $share->toArray()]);
	}

	/**
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection)
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function findSharedFolders(): DataResponse {
		$permissions = $this->authorizer->getPermissionsForFolder(-1, $this->request);
		if (!Authorizer::hasPermission(Authorizer::PERM_READ, $permissions)) {
			$res = new Http\DataResponse(['status' => 'error', 'data' => ['Unauthorized']], Http::STATUS_FORBIDDEN);
			$res->throttle();
			return $res;
		}

		try {
			$shares = $this->shareMapper->findByUser($this->authorizer->getUserId());
		} catch (Exception $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
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
	 * @BruteForceProtection
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function findShares(): DataResponse {
		$permissions = $this->authorizer->getPermissionsForFolder(-1, $this->request);
		if (Authorizer::hasPermission(Authorizer::PERM_READ, $permissions)) {
			$res = new Http\DataResponse(['status' => 'error', 'data' => ['Unauthorized']], Http::STATUS_FORBIDDEN);
			$res->throttle();
			return $res;
		}

		try {
			$shares = $this->shareMapper->findByOwner($this->authorizer->getUserId());
		} catch (Exception $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new Http\DataResponse(['status' => 'success', 'data' => array_map(function ($share) {
			return $share->toArray();
		}, $shares)]);
	}

	/**
	 * @param int $folderId
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function getShares(int $folderId): DataResponse {
		$permissions = $this->authorizer->getPermissionsForFolder($folderId, $this->request);
		if (Authorizer::hasPermission(Authorizer::PERM_RESHARE, $permissions)) {
			try {
				$this->folderMapper->find($folderId);
			} catch (MultipleObjectsReturnedException $e) {
				return new DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
			} catch (DoesNotExistException $e) {
				$res = new DataResponse(['status' => 'error', 'data' => ['Could not find folder']], Http::STATUS_NOT_FOUND);
				$res->throttle();
				return $res;
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
				return new DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
			} catch (DoesNotExistException $e) {
				$res = new DataResponse(['status' => 'error', 'data' => ['Could not find folder']], Http::STATUS_NOT_FOUND);
				$res->throttle();
				return $res;
			}
			return new Http\DataResponse(['status' => 'success', 'data' => [$share->toArray()]]);
		}
		$res = new DataResponse(['status' => 'error', 'data' => ['Could not find folder']], Http::STATUS_NOT_FOUND);
		$res->throttle();
		return $res;
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
	 * @BruteForceProtection
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function createShare(int $folderId, string $participant, int $type, bool $canWrite = false, bool $canShare = false): DataResponse {
		$permissions = $this->authorizer->getPermissionsForFolder($folderId, $this->request);
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $permissions)) {
			$res = new Http\DataResponse(['status' => 'error', 'data' => ['Could not find folder']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		}
		try {
			$canWrite = $canWrite && Authorizer::hasPermission(Authorizer::PERM_WRITE, $permissions);
			$share = $this->folders->createShare($folderId, $participant, $type, $canWrite, $canShare);
			return new Http\DataResponse(['status' => 'success', 'item' => $share->toArray()]);
		} catch (DoesNotExistException $e) {
			$res = new Http\DataResponse(['status' => 'error', 'data' => ['Could not find folder']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Multiple objects returned']], Http::STATUS_INTERNAL_SERVER_ERROR);
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
	 * @BruteForceProtection
	 * @PublicPage
	 */
	public function editShare(int $shareId, bool $canWrite = false, bool $canShare = false): Http\DataResponse {
		try {
			$share = $this->shareMapper->find($shareId);
		} catch (DoesNotExistException $e) {
			$res = new Http\DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		$permissions = $this->authorizer->getPermissionsForFolder($share->getFolderId(), $this->request);
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $permissions)) {
			$res = new Http\DataResponse(['status' => 'error', 'data' => ['Not found']], Http::STATUS_BAD_REQUEST);
			$res->throttle();
			return $res;
		}

		$canWrite = $canWrite && Authorizer::hasPermission(Authorizer::PERM_WRITE, $permissions);
		$share->setCanWrite($canWrite);
		$share->setCanShare($canShare);
		try {
			$this->shareMapper->update($share);
		} catch (Exception $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new Http\DataResponse(['status' => 'success', 'item' => $share->toArray()]);
	}

	/**
	 * @param int $shareId
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection
	 * @PublicPage
	 * @throws UnauthenticatedError
	 */
	public function deleteShare(int $shareId): DataResponse {
		try {
			$share = $this->shareMapper->find($shareId);
		} catch (DoesNotExistException $e) {
			$res = new Http\DataResponse(['status' => 'success']);
			$res->throttle();
			return $res;
		} catch (MultipleObjectsReturnedException $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		if (!Authorizer::hasPermission(Authorizer::PERM_RESHARE, $this->authorizer->getPermissionsForFolder($share->getFolderId(), $this->request))) {
			$res = new Http\DataResponse(['status' => 'success']);
			$res->throttle();
			return $res;
		}

		try {
			$this->folders->deleteShare($shareId);
		} catch (UnsupportedOperation|DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new Http\DataResponse(['status' => 'success']);
	}

	/**
	 * @return Http\DataResponse
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @BruteForceProtection
	 * @PublicPage
	 */
	public function getDeletedFolders(): DataResponse {
		$this->authorizer->setCredentials($this->request);
		if ($this->authorizer->getUserId() === null) {
			$res = new Http\DataResponse(['status' => 'error', 'data' => ['Unauthorized']], Http::STATUS_FORBIDDEN);
			$res->throttle();
			return $res;
		}
		try {
			$folders = $this->treeMapper->getSoftDeletedRootItems($this->authorizer->getUserId(), TreeMapper::TYPE_FOLDER);
			$folderItems = array_map(function ($folder) {
				$array = $folder->toArray();
				$array['children'] = $this->treeMapper->getSubFolders($folder->getId(), -1, true);
				return $array;
			}, $folders);
		} catch (UrlParseError|Exception $e) {
			return new Http\DataResponse(['status' => 'error', 'data' => ['Internal error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new Http\DataResponse(['status' => 'success', 'data' => $folderItems]);
	}
}
