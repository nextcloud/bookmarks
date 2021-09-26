<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\PublicFolder;
use OCA\Bookmarks\Db\PublicFolderMapper;
use OCA\Bookmarks\Db\Share;
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
	public const PERM_EDIT = 2;
	public const PERM_RESHARE = 4;
	public const PERM_ALL = 7;

	/**
	 * @var FolderMapper
	 */
	private $folderMapper;

	/**
	 * @var BookmarkMapper
	 */
	private $bookmarkMapper;

	/**
	 * @var ShareMapper
	 */
	private $shareMapper;


	/**
	 * @var PublicFolderMapper
	 */
	private $publicMapper;

	private $userId;
	private $token = null;

	private $cors = false;

	/**
	 * @var TreeMapper
	 */
	private $treeMapper;
	/**
	 * @var IUserSession
	 */
	private $userSession;

	public function __construct(FolderMapper $folderMapper, BookmarkMapper $bookmarkMapper, PublicFolderMapper $publicMapper, ShareMapper $shareMapper, TreeMapper $treeMapper, IUserSession $userSession) {
		$this->folderMapper = $folderMapper;
		$this->bookmarkMapper = $bookmarkMapper;
		$this->publicMapper = $publicMapper;
		$this->shareMapper = $shareMapper;
		$this->treeMapper = $treeMapper;
		$this->userSession = $userSession;
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
			if (false === $this->userSession->login($request->server['PHP_AUTH_USER'], $request->server['PHP_AUTH_PW'])) {
				return;
			}
			$this->setUserId($this->userSession->getUser()->getUID());
		} elseif ($auth !== null && $auth !== '') {
			[$type, $credentials] = explode(' ', $auth);
			if (strtolower($type) === 'basic') {
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
	 * @psalm-return positive-int
	 */
	protected function getMaskFromFlags($canWrite, $canShare): int {
		$perms = self::PERM_READ;
		if ($canWrite) {
			$perms |= self::PERM_EDIT;
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
	 */
	public function getUserPermissionsForBookmark(string $userId, int $bookmarkId): int {
		try {
			/** @var Bookmark $bookmark */
			$bookmark = $this->bookmarkMapper->find($bookmarkId);
		} catch (DoesNotExistException $e) {
			return self::PERM_EDIT;
		} catch (MultipleObjectsReturnedException $e) {
			return self::PERM_NONE;
		}
		if ($bookmark->getUserId() === $userId) {
			return self::PERM_ALL;
		}

		/** @var Share[] $shares */
		$shares = $this->shareMapper->findByOwnerAndUser($bookmark->getUserId(), $userId);
		foreach ($shares as $share) {
			if ($this->treeMapper->hasDescendant($share->getFolderId(), TreeMapper::TYPE_BOOKMARK, $bookmarkId)) {
				return $this->getMaskFromFlags($share->getCanWrite(), $share->getCanShare());
			}
		}
		return self::PERM_NONE;
	}

	/**
	 * @param string $token
	 * @param int $bookmarkId
	 *
	 * @return int
	 *
	 * @psalm-return 0|1|2
	 */
	public function getTokenPermissionsForBookmark(string $token, int $bookmarkId): int {
		try {
			/** @var PublicFolder $publicFolder */
			$publicFolder = $this->publicMapper->find($token);
		} catch (DoesNotExistException $e) {
			return self::PERM_EDIT;
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
	 */
	public function getUserPermissionsForFolder(string $userId, int $folderId): int {
		if ($folderId === -1) {
			return self::PERM_ALL;
		}
		try {
			/** @var Folder $folder */
			$folder = $this->folderMapper->find($folderId);
		} catch (DoesNotExistException $e) {
			return self::PERM_EDIT;
		} catch (MultipleObjectsReturnedException $e) {
			return self::PERM_NONE;
		}
		if ($folder->getUserId() === $userId) {
			return self::PERM_ALL;
		}

		/** @var Share[] $shares */
		$shares = $this->shareMapper->findByOwnerAndUser($folder->getUserId(), $userId);
		foreach ($shares as $share) {
			if ($share->getFolderId() === $folderId || $this->treeMapper->hasDescendant($share->getFolderId(), TreeMapper::TYPE_FOLDER, $folderId)) {
				return $this->getMaskFromFlags($share->getCanWrite(), $share->getCanShare());
			}
		}
		return self::PERM_NONE;
	}

	/**
	 * @param string $token
	 * @param int $folderId
	 *
	 * @return int
	 *
	 * @psalm-return 0|1|2
	 */
	public function getTokenPermissionsForFolder(string $token, int $folderId): int {
		try {
			/** @var PublicFolder $publicFolder */
			$publicFolder = $this->publicMapper->find($token);
		} catch (DoesNotExistException $e) {
			return self::PERM_EDIT;
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
