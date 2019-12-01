<?php
namespace OCA\Bookmarks\Service;

use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\PublicFolderMapper;
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

	public function __construct(FolderMapper $folderMapper, BookmarkMapper $bookmarkMapper, PublicFolderMapper $publicMapper) {
		$this->folderMapper = $folderMapper;
		$this->bookmarkMapper = $bookmarkMapper;
		$this->publicMapper = $publicMapper;
	}

	public function setCredentials(string $userId, IRequest $request) {
		if (isset($userId)) {
			$this->setUserId($userId);
		}else {
			$auth = $request->getHeader('Authorization');
			[$type, $token] = explode(' ', $auth);
			if (strtolower($type) !== 'bearer') {
				return;
			}
			$this->setToken($token);
		}
	}

	public function setToken(string $token) {
		$this->token = $token;
	}

	public function setUserId(string $userId) {
		$this->userId = $userId;
	}

	public function getPermissionsForFolder($folderId, $userId, $request) {
		$this->setCredentials($userId, $request);
		if (isset($this->userId)) {
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
		}
		if (isset($this->token)) {
			try {
				$publicFolder = $this->publicMapper->find($this->token);
			} catch (DoesNotExistException $e) {
				return self::PERM_NONE;
			} catch (MultipleObjectsReturnedException $e) {
				return self::PERM_NONE;
			}
			if ($this->folderMapper->hasDescendantFolder($publicFolder->getFolderId(), $folderId)) {
				return self::PERM_READ;
			}
		}
		return self::PERM_NONE;
	}

	public function getPermissionsForBookmark($bookmarkId, $userId, $request) {
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
		}
		if (isset($this->token)) {
			try {
				$publicFolder = $this->publicMapper->find($this->token);
			} catch (DoesNotExistException $e) {
				return self::PERM_NONE;
			} catch (MultipleObjectsReturnedException $e) {
				return self::PERM_NONE;
			}
			if ($this->folderMapper->hasDescendantBookmark($publicFolder->getFolderId(), $bookmarkId)) {
				return self::PERM_READ;
			}
		}
		return self::PERM_NONE;
	}

	/**
	 * Check permissions
	 * @param $perm
	 * @return boolean
	 */
	public static function hasPermission($perm, $perms) {
		return (boolean) ($perms & $perm);
	}
}
