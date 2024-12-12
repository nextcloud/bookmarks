<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\TagMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\HtmlParseError;
use OCA\Bookmarks\Exception\UnauthorizedAccessError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

/**
 * Class HtmlImporter
 *
 * @package OCA\Bookmarks\Service
 */
class HtmlImporter {
	// Taken from https://stackoverflow.com/questions/33126595/what-is-the-actual-range-of-a-mysql-int-column-in-this-situation
	public const DB_MAX_INT = 2147483647;

	/**
	 * @var BookmarkMapper
	 */
	protected $bookmarkMapper;

	/**
	 * @var FolderMapper
	 */
	protected $folderMapper;

	/**
	 * @var TagMapper
	 */
	protected $tagMapper;

	/** @var BookmarksParser */
	private $bookmarksParser;
	/**
	 * @var TreeMapper
	 */
	private $treeMapper;
	/**
	 * @var TreeCacheManager
	 */
	private $hashManager;

	/**
	 * ImportService constructor.
	 *
	 * @param BookmarkMapper $bookmarkMapper
	 * @param FolderMapper $folderMapper
	 * @param TagMapper $tagMapper
	 * @param TreeMapper $treeMapper
	 * @param BookmarksParser $bookmarksParser
	 * @param TreeCacheManager $hashManager
	 */
	public function __construct(BookmarkMapper $bookmarkMapper, FolderMapper $folderMapper, TagMapper $tagMapper, TreeMapper $treeMapper, BookmarksParser $bookmarksParser, \OCA\Bookmarks\Service\TreeCacheManager $hashManager) {
		$this->bookmarkMapper = $bookmarkMapper;
		$this->folderMapper = $folderMapper;
		$this->tagMapper = $tagMapper;
		$this->treeMapper = $treeMapper;
		$this->bookmarksParser = $bookmarksParser;
		$this->hashManager = $hashManager;
	}

	/**
	 * @brief Import Bookmarks from html formatted file
	 *
	 * @param string $userId
	 * @param string $file
	 * @param int|null $rootFolder
	 *
	 * @return array
	 *
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnauthorizedAccessError
	 * @throws AlreadyExistsError
	 * @throws UserLimitExceededError
	 * @throws HtmlParseError
	 */
	public function importFile(string $userId, string $file, ?int $rootFolder = null): array {
		$content = file_get_contents($file);
		return $this->import($userId, $content, $rootFolder);
	}

	/**
	 * @brief Import Bookmarks from html
	 *
	 * @param string $userId
	 * @param string $content
	 * @param int|null $rootFolderId
	 *
	 * @return (array|mixed|string)[][]
	 *
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws HtmlParseError
	 * @throws MultipleObjectsReturnedException
	 * @throws UnauthorizedAccessError
	 * @throws UserLimitExceededError|UnsupportedOperation
	 *
	 * @psalm-return array{imported: list<array>, errors: array<array-key, mixed|string>}
	 */
	public function import(string $userId, string $content, ?int $rootFolderId = null): array {
		$imported = [];
		$errors = [];

		if ($rootFolderId === null) {
			$rootFolder = $this->folderMapper->findRootFolder($userId);
		} else {
			$rootFolder = $this->folderMapper->find($rootFolderId);
			if ($rootFolder->getUserId() !== $userId) {
				throw new UnauthorizedAccessError('Not allowed to access folder ' . $rootFolder->getId());
			}
		}

		$this->bookmarksParser->parse($content, false);

		// Disable invalidation, since we're going to add a bunch of new data to the tree at a single point
		$this->hashManager->setInvalidationEnabled(false);

		foreach ($this->bookmarksParser->currentFolder['children'] as $folder) {
			$imported[] = $this->importFolder($userId, $folder, $rootFolder->getId(), $errors);
		}
		foreach ($this->bookmarksParser->currentFolder['bookmarks'] as $bookmark) {
			try {
				$bm = $this->importBookmark($userId, $rootFolder->getId(), $bookmark);
			} catch (UrlParseError $e) {
				$errors[] = 'Failed to parse URL: ' . $bookmark['href'];
				continue;
			}
			$imported[] = ['type' => 'bookmark', 'id' => $bm->getId(), 'title' => $bookmark['title'], 'url' => $bookmark['href']];
		}

		$this->hashManager->setInvalidationEnabled(true);
		$this->hashManager->invalidateFolder($rootFolder->getId());

		return ['imported' => $imported, 'errors' => $errors];
	}

	/**
	 * @param string $userId
	 * @param array $folderParams
	 * @param int $parentId
	 * @param array $errors
	 * @param int|null $index
	 *
	 * @return (array[]|int|mixed|string)[]
	 *
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnauthorizedAccessError
	 * @throws AlreadyExistsError
	 * @throws UserLimitExceededError
	 * @throws UnsupportedOperation
	 *
	 * @psalm-return array{type: string, id: int, title: mixed, children: list<array>}
	 */
	private function importFolder(string $userId, array $folderParams, int $parentId, &$errors = [], $index = null): array {
		$folder = new Folder();
		$folder->setUserId($userId);
		$folder->setTitle($folderParams['title']);
		$folder = $this->folderMapper->insert($folder);
		$this->treeMapper->move(TreeMapper::TYPE_FOLDER, $folder->getId(), $parentId, $index);
		$newFolder = ['type' => 'folder', 'id' => $folder->getId(), 'title' => $folderParams['title'], 'children' => []];
		$index = 0;
		foreach ($folderParams['bookmarks'] as $bookmark) {
			try {
				$bm = $this->importBookmark($userId, $folder->getId(), $bookmark, $index++);
			} catch (UrlParseError $e) {
				$errors[] = 'Failed to parse URL: ' . $bookmark['href'];
				continue;
			}
			$newFolder['children'][] = ['type' => 'bookmark', 'id' => $bm->getId(), 'title' => $bookmark['title'], 'url' => $bookmark['href']];
		}
		foreach ($folderParams['children'] as $childFolder) {
			$newFolder['children'][] = $this->importFolder($userId, $childFolder, $folder->getId(), $errors, $index++);
		}
		return $newFolder;
	}

	/**
	 * @param string $userId
	 * @param int $folderId
	 * @param array $bookmark
	 * @param null|int $index
	 * @return Bookmark|Entity
	 * @throws UrlParseError|AlreadyExistsError|UnsupportedOperation|UserLimitExceededError|MultipleObjectsReturnedException
	 */
	private function importBookmark(string $userId, int $folderId, array $bookmark, $index = null) {
		$bm = new Bookmark();
		$bm->setUserId($userId);
		$bm->setUrl($bookmark['href']);
		$bm->setTitle($bookmark['title']);
		$bm->setDescription($bookmark['description']);
		if (isset($bookmark['add_date'])) {
			if ($bookmark['add_date']->getTimestamp() < self::DB_MAX_INT && $bookmark['add_date']->getTimestamp() > -self::DB_MAX_INT) {
				$bm->setAdded($bookmark['add_date']->getTimestamp());
			} else {
				$bm->setAdded(time());
			}
		}

		// insert bookmark
		$bm = $this->bookmarkMapper->insertOrUpdate($bm);
		// add to folder
		$this->treeMapper->addToFolders(TreeMapper::TYPE_BOOKMARK, $bm->getId(), [$folderId], $index);
		// add tags
		$this->tagMapper->addTo($bookmark['tags'], $bm->getId());

		return $bm;
	}
}
