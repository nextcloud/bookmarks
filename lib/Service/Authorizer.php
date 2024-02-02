<?php
/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\PublicFolderMapper;
use OCA\Bookmarks\Db\Share;
use OCA\Bookmarks\Db\SharedFolderMapper;
use OCA\Bookmarks\Db\ShareMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Exception\UnauthenticatedError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IRequest;
use OCP\IUserSession;

class Authorizer {
	public const PERM_NONE = 0;
	public const PERM_READ = 1;
	public const PERM_EDIT = 2; // Allows editing the direct item
	public const PERM_RESHARE = 4;
	public const PERM_WRITE = 8; // Allows adding and editing the item's descendants
	public const PERM_ALL = 15;

	private $userId;
	private $token = null;

	private $cors = false;


	public function __construct(
		private FolderMapper $folderMapper,
		private BookmarkMapper $bookmarkMapper,
		private PublicFolderMapper $publicMapper,
		private ShareMapper $shareMapper,
		private TreeMapper $treeMapper,
		private IUserSession $userSession,
		private SharedFolderMapper $sharedFolderMapper
	) {
	}

	/**
	 * @param bool $cors
	 */
	public function setCORS($cors) {
		$this->cors = $cors;
	}

	/**
	 * @param IRequest $request
	 */
	public function setCredentials(IRequest $request): void {
		$queryParam = $request->getParam('token');
		if ($queryParam !== null) {
			$this->setToken($queryParam);
		}

		$auth = $request->getHeader('Authorization');

		if ($auth !== null && $auth !== '') {
			[$type, $credentials] = explode(' ', $auth);
			if (strtolower($type) === 'bearer') {
				$this->setToken($credentials);
			}
		}

		if (!$this->cors && $this->userSession->isLoggedIn()) {
			$this->setUserId($this->userSession->getUser()->getUID());
		} elseif (isset($request->server['PHP_AUTH_USER'], $request->server['PHP_AUTH_PW'])) {
			if ($this->userSession->getUser() !== null) {
				$this->setUserId($this->userSession->getUser()->getUID());
				return;
			}
			if (false === $this->userSession->login($request->server['PHP_AUTH_USER'], $request->server['PHP_AUTH_PW'])) {
				return;
			}
			$this->setUserId($this->userSession->getUser()->getUID());
		} elseif ($auth !== null && $auth !== '') {
			[$type, $credentials] = explode(' ', $auth);
			if (strtolower($type) === 'basic') {
				if ($this->userSession->getUser() !== null) {
					$this->setUserId($this->userSession->getUser()->getUID());
					return;
				}
				[$username, $password] = explode(':', base64_decode($credentials));
				if (false === $this->userSession->login($username, $password)) {
					return;
				}
				$this->setUserId($this->userSession->getUser()->getUID());
			}
		}
	}

	/**
	 * @param null|string $token
	 */
	public function setToken(?string $token): void {
		$this->token = $token;
	}

	/**
	 * @return null|string
	 */
	public function getToken(): ?string {
		return $this->token;
	}

	/**
	 * @param string|null $userId
	 */
	public function setUserId(?string $userId): void {
		$this->userId = $userId;
	}

	/**
	 * @return null|string
	 */
	public function getUserId(): ?string {
		return $this->userId;
	}

	/**
	 * @param int $folderId
	 * @param IRequest $request
	 * @return int
	 * @throws UnauthenticatedError
	 */
	public function getPermissionsForFolder(int $folderId, IRequest $request): int {
		$this->setCredentials($request);
		$perms = self::PERM_NONE;
		if (isset($this->userId)) {
			$perms |= $this->getUserPermissionsForFolder($this->userId, $folderId);
		} elseif (isset($this->token)) {
			$perms |= $this->getTokenPermissionsForFolder($this->token, $folderId);
		} else {
			throw new UnauthenticatedError();
		}
		return $perms;
	}

	/**
	 * @param int $bookmarkId
	 * @param IRequest $request
	 * @return int
	 * @throws UnauthenticatedError
	 */
	public function getPermissionsForBookmark(int $bookmarkId, IRequest $request): int {
		$this->setCredentials($request);
		$perms = self::PERM_NONE;
		if (isset($this->userId)) {
			$perms |= $this->getUserPermissionsForBookmark($this->userId, $bookmarkId);
		} elseif (isset($this->token)) {
			$perms |= $this->getTokenPermissionsForBookmark($this->token, $bookmarkId);
		} else {
			throw new UnauthenticatedError();
		}
		return $perms;
	}

	/**
	 * @param $canWrite
	 * @param $canShare
	 *
	 * @return int
	 *
	 * @psalm-return 0|positive-int
	 */
	protected function getMaskFromFlags($canWrite, $canShare): int {
		$perms = self::PERM_READ;
		if ($canWrite) {
			$perms |= self::PERM_EDIT;
			$perms |= self::PERM_WRITE;
		}
		if ($canShare) {
			$perms |= self::PERM_RESHARE;
		}
		return $perms;
	}

	/**
	 * Check permissions
	 *
	 *
	 * @param int $perm
	 * @param int $perms
	 *
	 * @return boolean
	 */
	public static function hasPermission(int $perm, int $perms): bool {
		return (boolean)($perms & $perm);
	}

	/**
	 * @param string $userId
	 * @param int $bookmarkId
	 * @return int
	 * @psalm-return 0|positive-int
	 */
	public function getUserPermissionsForBookmark(string $userId, int $bookmarkId): int {
		return $this->findPermissionsByUserAndItem($userId, TreeMapper::TYPE_BOOKMARK, $bookmarkId);
	}

	/**
	 * @param string $token
	 * @param int $bookmarkId
	 *
	 * @return int
	 * @psalm-return 0|positive-int
	 */
	public function getTokenPermissionsForBookmark(string $token, int $bookmarkId): int {
		try {
			$publicFolder = $this->publicMapper->find($token);
		} catch (DoesNotExistException $e) {
			return self::PERM_NONE;
		} catch (MultipleObjectsReturnedException $e) {
			return self::PERM_NONE;
		}
		if ($this->treeMapper->hasDescendant($publicFolder->getFolderId(), TreeMapper::TYPE_BOOKMARK, $bookmarkId)) {
			return self::PERM_READ;
		}
		return self::PERM_NONE;
	}

	/**
	 * @param string $userId
	 * @param int $folderId
	 * @return int
	 * @psalm-return 0|positive-int
	 */
	public function getUserPermissionsForFolder(string $userId, int $folderId): int {
		if ($folderId === -1) {
			return self::PERM_ALL;
		}

		return $this->findPermissionsByUserAndItem($userId, TreeMapper::TYPE_FOLDER, $folderId);
	}

	/**
	 * @param string $userId
	 * @param string $type
	 * @param int $itemId
	 * @return 0|positive-int
	 */
	private function findPermissionsByUserAndItem(string $userId, string $type, int $itemId): int {
		try {
			if ($type === TreeMapper::TYPE_FOLDER) {
				$item = $this->folderMapper->find($itemId);
			} elseif ($type === TreeMapper::TYPE_BOOKMARK) {
				$item = $this->bookmarkMapper->find($itemId);
			} else {
				$item = $this->sharedFolderMapper->find($itemId);
			}
		} catch (DoesNotExistException) {
			return self::PERM_ALL;
		} catch (MultipleObjectsReturnedException) {
			return self::PERM_NONE;
		}
		if ($item->getUserId() === $userId) {
			return self::PERM_ALL;
		}

		$shares = $this->shareMapper->findByOwner($item->getUserId());
		foreach ($shares as $share) {
			if ($share->getFolderId() === $itemId && $type === TreeMapper::TYPE_FOLDER) {
				// If the sought folder is the root folder of the share, we give EDIT permissions + optionally RESHARE
				// because the user can edit the shared folder
				$perms = $this->getMaskFromFlags($share->getCanWrite(), $share->getCanShare()) | self::PERM_EDIT;
			} elseif ($this->treeMapper->hasDescendant($share->getFolderId(), $type, $itemId)) {
				$perms = $this->getMaskFromFlags($share->getCanWrite(), $share->getCanShare());
			} else {
				continue;
			}

			$sharedFolders = $this->sharedFolderMapper->findByShare($share->getId());
			foreach ($sharedFolders as $sharedFolder) {
				if ($sharedFolder->getUserId() === $userId) {
					return $perms;
				}
				$secondLevelPerms = $this->findPermissionsByUserAndItem($userId, TreeMapper::TYPE_SHARE, $sharedFolder->getId());
				if ($secondLevelPerms !== self::PERM_NONE) {
					return $perms & $secondLevelPerms;
				}
			}
		}

		return self::PERM_NONE;
	}

	/**
	 * @param string $token
	 * @param int $folderId
	 *
	 * @return int
	 * @psalm-return 0|positive-int
	 *
	 */
	public function getTokenPermissionsForFolder(string $token, int $folderId): int {
		try {
			$publicFolder = $this->publicMapper->find($token);
		} catch (DoesNotExistException $e) {
			return self::PERM_NONE;
		} catch (MultipleObjectsReturnedException $e) {
			return self::PERM_NONE;
		}
		if ($folderId === -1) {
			return self::PERM_READ;
		}
		if ($publicFolder->getFolderId() === $folderId || $this->treeMapper->hasDescendant($publicFolder->getFolderId(), TreeMapper::TYPE_FOLDER, $folderId)) {
			return self::PERM_READ;
		}
		return self::PERM_NONE;
	}
}
