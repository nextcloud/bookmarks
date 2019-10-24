<?php

namespace OCA\Bookmarks\Service;

use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\TagMapper;
use OCA\Bookmarks\Exception\UnauthorizedAccessError;
use OCA\Bookmarks\Exception\UrlParseError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

/**
 * Class HtmlImporter
 *
 * @package OCA\Bookmarks\Service
 */
class HtmlImporter {

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
	 * ImportService constructor.
	 *
	 * @param BookmarkMapper $bookmarkMapper
	 * @param FolderMapper $folderMapper
	 * @param TagMapper $tagMapper
	 * @param BookmarksParser $bookmarksParser
	 */
	public function __construct(BookmarkMapper $bookmarkMapper, FolderMapper $folderMapper, TagMapper $tagMapper, BookmarksParser $bookmarksParser) {
		$this->bookmarkMapper = $bookmarkMapper;
		$this->folderMapper = $folderMapper;
		$this->tagMapper = $tagMapper;
		$this->bookmarksParser = $bookmarksParser;
	}

	/**
	 * @brief Import Bookmarks from html formatted file
	 * @param int $userId
	 * @param string $file Content to import
	 * @param int $rootFolder
	 * @return array
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnauthorizedAccessError
	 */
	public function importFile(int $userId, string $file, int $rootFolder = -1) {
		$content = file_get_contents($file);
		return $this->import($userId, $content, $rootFolder);
	}

	/**
	 * @brief Import Bookmarks from html
	 * @param int $userId
	 * @param string $content
	 * @param int $rootFolder
	 * @return array
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnauthorizedAccessError
	 */
	public function import(int $userId, string $content, int $rootFolder = -1) {
		$imported = [];
		$errors = [];
		if ($rootFolder !== -1) {
			$folder = $this->folderMapper->find($rootFolder);
			if ($folder->getUserId() !== $userId) {
				throw new UnauthorizedAccessError('Not allowed to access folder '.$rootFolder);
			}
		}
		$this->bookmarksParser->parse($content, false);
		foreach ($this->bookmarksParser->currentFolder['children'] as $folder) {
			$imported[] = $this->importFolder($userId, $folder, $rootFolder, $errors);
		}
		foreach ($this->bookmarksParser->currentFolder['bookmarks'] as $bookmark) {
			try {
				$bm = $this->importBookmark($userId, $rootFolder, $bookmark);
			} catch (UrlParseError $e) {
				$errors[] = 'Failed to parse URL: '.$bookmark['href'];
				continue;
			}
			$imported[] = ['type' => 'bookmark', 'id' => $bm->getId(), 'title' => $bookmark['title'], 'url' => $bookmark['href']];
		}
		return ['imported' => $imported, 'errors' => $errors];
	}

	/**
	 * @param int $userId
	 * @param array $folderParams
	 * @param int $parentId
	 * @param array $errors
	 * @return array
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	private function importFolder(int $userId, array $folderParams, int $parentId, &$errors = []) {
		$folder = new Folder();
		$folder->setUserId($userId);
		$folder->setTitle($folderParams['title']);
		$folder->setParentFolder($parentId);
		$folder = $this->folderMapper->insert($folder);
		$newFolder = ['type' => 'folder', 'id' => $folder->getId(), 'title' => $folderParams['title'], 'children' => []];
		foreach ($folderParams['bookmarks'] as $bookmark) {
			try {
				$bm = $this->importBookmark($userId, $folder->getId(), $bookmark);
			} catch (UrlParseError $e) {
				$errors[] = 'Failed to parse URL: '.$bookmark['href'];
				continue;
			}
			$newFolder['children'][] = ['type' => 'bookmark', 'id' => $bm->getId(), 'title' => $bookmark['title'], 'url' => $bookmark['href']];
		}
		foreach ($folderParams['children'] as $childFolder) {
			$newFolder['children'][] = $this->importFolder($userId, $childFolder, $folder->getId(), $errors);
		}
		return $newFolder;
	}

	/**
	 * @param int $userId
	 * @param int $folderId
	 * @param array $bookmark
	 * @return Bookmark|Entity
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 */
	private function importBookmark(int $userId, int $folderId, array $bookmark) {
		$bm = new Bookmark();
		$bm->setUserId($userId);
		$bm->setUrl($bookmark['href']);
		$bm->setTitle($bookmark['title']);
		$bm->setDescription($bookmark['title']);
		if (isset($bookmark['add_date'])) $bm->setAdded($bookmark['add_date']->getTimestamp());

		// insert bookmark
		$bm = $this->bookmarkMapper->insertOrUpdate($bm);
		// add to folder
		$this->folderMapper->addToFolders($bm->getId(), [$folderId]);
		// add tags
		$this->tagMapper->addTo($bookmark['tags'], $bm->getId());

		return $bm;
	}
}
