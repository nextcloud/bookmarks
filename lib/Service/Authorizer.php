<?php

namespace OCA\Bookmarks\Service;

use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\PublicFolderMapper;
use OCA\Bookmarks\Db\ShareMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IRequest;

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

	private $userId = null;
	private $token = null;

	/**
	 * @var TreeMapper
	 */
	private $treeMapper;

	public function __construct(FolderMapper $folderMapper, BookmarkMapper $bookmarkMapper, PublicFolderMapper $publicMapper, ShareMapper $shareMapper, TreeMapper $treeMapper) {
		$this->folderMapper = $folderMapper;
		$this->bookmarkMapper = $bookmarkMapper;
		$this->publicMapper = $publicMapper;
		$this->shareMapper = $shareMapper;
		$this->treeMapper = $treeMapper;
	}

	/**
	 * @param string $userId
	 * @param IRequest $request
	 */
	public function setCredentials($userId, IRequest $request): void {
		$this->setUserId($userId);
		$auth = $request->getHeader('Authorization');
		if ($auth === '') {
			return;
		}
		[$type, $token] = explode(' ', $auth);
		if (strtolower($type) !== 'bearer') {
			return;
		}
		$this->setToken($token);
	}

	public function setToken(string $token): void {
		$this->token = $token;
	}

	/**
	 * @return null|string
	 */
	public function getToken(): ?string {
		return $this->token;
	}

	public function setUserId($userId): void {
		$this->userId = $userId;
	}

	/**
	 * @param $folderId
	 * @param $userId
	 * @param $request
	 * @return int
	 */
	public function getPermissionsForFolder(int $folderId, $userId, $request): int {
		$this->setCredentials($userId, $request);
		if (isset($this->userId)) {
			if ($folderId === -1) {
				return self::PERM_ALL;
			}
			try {
				$folder = $this->folderMapper->find($folderId);
			} catch (DoesNotExistException $e) {
				return self::PERM_NONE;
			} catch (MultipleObjectsReturnedException $e) {
				return self::PERM_NONE;
			}
			if ($folder->getUserId() === $this->userId) {
				return self::PERM_ALL;
			}

			$shares = $this->shareMapper->findByOwnerAndUser($folder->getUserId(), $this->userId);
			foreach ($shares as $share) {
				if ($share->getFolderId() === $folderId || $this->treeMapper->hasDescendant($share->getFolderId(), TreeMapper::TYPE_FOLDER, $folderId)) {
					return $this->getMaskFromFlags($share->getCanWrite(), $share->getCanShare());
				}
			}
		}
		if (isset($this->token)) {
			try {
				$publicFolder = $this->publicMapper->find($this->token);
			} catch (DoesNotExistException $e) {
				return self::PERM_NONE;
			} catch (MultipleObjectsReturnedException $e) {
				return self::PERM_NONE;
			}
			if ($publicFolder->getFolderId() === $folderId || $this->treeMapper->hasDescendant($publicFolder->getFolderId(), TreeMapper::TYPE_FOLDER, $folderId)) {
				return self::PERM_READ;
			}
		}
		return self::PERM_NONE;
	}

	/**
	 * @param $bookmarkId
	 * @param $userId
	 * @param $request
	 * @return int
	 */
	public function getPermissionsForBookmark(int $bookmarkId, $userId, $request): int {
		$this->setCredentials($userId, $request);
		if (isset($this->userId)) {
			try {
				$bookmark = $this->bookmarkMapper->find($bookmarkId);
			} catch (DoesNotExistException $e) {
				return self::PERM_NONE;
			} catch (MultipleObjectsReturnedException $e) {
				return self::PERM_NONE;
			}
			if ($bookmark->getUserId() === $this->userId) {
				return self::PERM_ALL;
			}

			$shares = $this->shareMapper->findByOwnerAndUser($bookmark->getUserId(), $userId);
			foreach ($shares as $share) {
				if ($this->treeMapper->hasDescendant($share->getFolderId(), TreeMapper::TYPE_BOOKMARK, $bookmarkId)) {
					return $this->getMaskFromFlags($share->getCanWrite(), $share->getCanShare());
				}
			}
			return self::PERM_NONE;
		}
		if (isset($this->token)) {
			try {
				$publicFolder = $this->publicMapper->find($this->token);
			} catch (DoesNotExistException $e) {
				return self::PERM_NONE;
			} catch (MultipleObjectsReturnedException $e) {
				return self::PERM_NONE;
			}
			if ($this->treeMapper->hasDescendant($publicFolder->getFolderId(), TreeMapper::TYPE_BOOKMARK, $bookmarkId)) {
				return self::PERM_READ;
			}
		}
		return self::PERM_NONE;
	}

	/**
	 * @param $canWrite
	 * @param $canShare
	 * @return int
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
	 * @param $perm
	 * @param $perms
	 * @return boolean
	 */
	public static function hasPermission($perm, $perms): bool {
		return (boolean)($perms & $perm);
	}
}
