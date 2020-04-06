<?php

namespace OCA\Bookmarks\Service;


use OCA\Bookmarks\Contract\IImage;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\TagMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class BookmarkService {
	/**
	 * @var BookmarkMapper
	 */
	private $bookmarkMapper;
	/**
	 * @var TreeMapper
	 */
	private $treeMapper;
	/**
	 * @var Authorizer
	 */
	private $authorizer;
	/**
	 * @var LinkExplorer
	 */
	private $linkExplorer;
	/**
	 * @var FolderMapper
	 */
	private $folderMapper;
	/**
	 * @var TagMapper
	 */
	private $tagMapper;
	/**
	 * @var BookmarkPreviewer
	 */
	private $bookmarkPreviewer;
	/**
	 * @var FaviconPreviewer
	 */
	private $faviconPreviewer;

	/**
	 * BookmarksService constructor.
	 *
	 * @param BookmarkMapper $bookmarkMapper
	 * @param FolderMapper $folderMapper
	 * @param TagMapper $tagMapper
	 * @param TreeMapper $treeMapper
	 * @param Authorizer $authorizer
	 * @param LinkExplorer $linkExplorer
	 * @param BookmarkPreviewer $bookmarkPreviewer
	 * @param FaviconPreviewer $faviconPreviewer
	 */
	public function __construct(BookmarkMapper $bookmarkMapper, FolderMapper $folderMapper, TagMapper $tagMapper, TreeMapper $treeMapper, Authorizer $authorizer, LinkExplorer $linkExplorer, BookmarkPreviewer $bookmarkPreviewer, FaviconPreviewer $faviconPreviewer) {
		$this->bookmarkMapper = $bookmarkMapper;
		$this->treeMapper = $treeMapper;
		$this->authorizer = $authorizer;
		$this->linkExplorer = $linkExplorer;
		$this->folderMapper = $folderMapper;
		$this->tagMapper = $tagMapper;
		$this->bookmarkPreviewer = $bookmarkPreviewer;
		$this->faviconPreviewer = $faviconPreviewer;
	}

	/**
	 * @param $userId
	 * @param string $url
	 * @param null $title
	 * @param string $description
	 * @param array $tags
	 * @param array $folders
	 * @return Bookmark
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function create($userId, $url = '', $title = null, $description = '', $tags = [], $folders = []): Bookmark {
		// Inspect web page (do some light scraping)
		// allow only http(s) and (s)ftp
		$protocols = '/^(https?|s?ftp)\:\/\//i';
		if (preg_match($protocols, $url)) {
			$data = $this->linkExplorer->get($url);
		} else {
			// if no allowed protocol is given, evaluate https and https
			foreach (['https://', 'http://'] as $protocol) {
				$testUrl = $protocol . $url;
				$data = $this->linkExplorer->get($testUrl);
				if (isset($data['basic']['title'])) {
					break;
				}
			}
		}

		$url = $data['url'] ?? $url;
		$title = $title ?? $data['basic']['title'] ?? $url;
		$title = trim($title);
		$description = $description ?? $data['basic']['description'] ?? '';

		foreach ($folders as $folderId) {
			try {
				/**
				 * @var $folder Folder
				 */
				$folder = $this->folderMapper->find($folderId);
			} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
				continue;
			}
			$bookmark = $this->_addBookmark($title, $url, $description, $folder->getUserId(), $tags, [$folder->getId()]);
		}
		if (isset($bookmark)) {
			return $bookmark;
		}
		return $this->_addBookmark($title, $url, $description, $userId, $tags, [$this->folderMapper->findRootFolder($userId)->getId()]);
	}

	/**
	 * @param $title
	 * @param $url
	 * @param $description
	 * @param $userId
	 * @param $tags
	 * @param $folders
	 * @return Bookmark
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws UnsupportedOperation
	 */
	private function _addBookmark($title, $url, $description, $userId, $tags, $folders): Bookmark {
		$bookmark = new Bookmark();
		$bookmark->setTitle($title);
		$bookmark->setUrl($url);
		$bookmark->setDescription($description);
		$bookmark->setUserId($userId);
		$this->bookmarkMapper->insertOrUpdate($bookmark);
		$this->tagMapper->setOn($tags, $bookmark->getId());

		$this->treeMapper->setToFolders(TreeMapper::TYPE_BOOKMARK, $bookmark->getId(), $folders);
		return $bookmark;
	}

	/**
	 * @param null $id
	 * @param string|null $url
	 * @param string|null $title
	 * @param string|null $description
	 * @param array|null $tags
	 * @param array|null $folders
	 * @return Bookmark
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function update($id = null, string $url = null, string $title = null, string $description = null, array $tags = null, array $folders = null): ?Bookmark {
		/**
		 * @var $bookmark Bookmark
		 */
		$bookmark = $this->bookmarkMapper->find($id);
		if ($url !== null) {
			$bookmark->setUrl($url);
		}
		if ($title !== null) {
			$bookmark->setTitle($title);
		}
		if ($description !== null) {
			$bookmark->setDescription($description);
		}

		if ($folders !== null) {
			$foreignFolders = array_filter($folders, function ($folderId) use ($bookmark) {
				try {
					$folder = $this->folderMapper->find($folderId);
					return ($bookmark->getUserId() !== $folder->getUserId());
				} catch (DoesNotExistException $e) {
					return false;
				} catch (MultipleObjectsReturnedException $e) {
					return false;
				}
			});
			$ownFolders = array_filter($folders, function ($folderId) use ($bookmark) {
				try {
					$folder = $this->folderMapper->find($folderId);
					return ($bookmark->getUserId() === $folder->getUserId());
				} catch (DoesNotExistException $e) {
					return false;
				} catch (MultipleObjectsReturnedException $e) {
					return false;
				}
			});
			foreach ($foreignFolders as $folderId) {
				$folder = $this->folderMapper->find($folderId);
				$bookmark->setUserId($folder->getUserId());
				$this->_addBookmark($bookmark->getTitle(), $bookmark->getUrl(), $bookmark->getDescription(), $bookmark->getUserId(), $tags ?? [], [$folder->getId()]);
			}

			$this->treeMapper->setToFolders(TreeMapper::TYPE_BOOKMARK, $bookmark->getId(), $ownFolders);
			if (count($ownFolders) === 0) {
				$this->bookmarkMapper->delete($bookmark);
				return null;
			}
		}

		if ($tags !== null) {
			$this->tagMapper->setOn($tags, $bookmark->getId());
		}
		$this->bookmarkMapper->update($bookmark);

		return $bookmark;
	}

	/**
	 * @param $id
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function delete($id) {
		$bookmark = $this->bookmarkMapper->find($id);
		$this->treeMapper->deleteEntry(TreeMapper::TYPE_BOOKMARK, $bookmark->getId());
	}

	/**
	 * @param $userId
	 * @param string $url
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 */
	public function click($userId, $url = ''): void {
		$bookmark = $this->bookmarkMapper->findByUrl($userId, $url);
		$bookmark->incrementClickcount();
		$this->bookmarkMapper->update($bookmark);
	}

	/**
	 * @param $id
	 * @return IImage
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getImage($id): IImage {
		/**
		 * @var $bookmark Bookmark
		 */
		$bookmark = $this->bookmarkMapper->find($id);
		return $this->bookmarkPreviewer->getImage($bookmark);
	}

	/**
	 * @param $id
	 * @return IImage
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function getFavicon($id): IImage {
		/**
		 * @var $bookmark Bookmark
		 */
		$bookmark = $this->bookmarkMapper->find($id);
		return $this->faviconPreviewer->getImage($bookmark);
	}
}
