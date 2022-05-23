<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use OCA\Bookmarks\BackgroundJobs\IndividualCrawlJob;
use OCA\Bookmarks\Contract\IImage;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\TagMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Events\CreateEvent;
use OCA\Bookmarks\Events\UpdateEvent;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Bookmarks\QueryParameters;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\IEventDispatcher;

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
	 * @var FolderService
	 */
	private $folders;
	/**
	 * @var IEventDispatcher
	 */
	private $eventDispatcher;
	/**
	 * @var TreeCacheManager
	 */
	private $hashManager;
	private $urlNormalizer;
	/**
	 * @var CrawlService
	 */
	private $crawler;
	/**
	 * @var IJobList
	 */
	private $jobList;

	/**
	 * BookmarksService constructor.
	 *
	 * @param BookmarkMapper $bookmarkMapper
	 * @param FolderMapper $folderMapper
	 * @param TagMapper $tagMapper
	 * @param TreeMapper $treeMapper
	 * @param LinkExplorer $linkExplorer
	 * @param BookmarkPreviewer $bookmarkPreviewer
	 * @param FaviconPreviewer $faviconPreviewer
	 * @param FolderService $folders
	 * @param IEventDispatcher $eventDispatcher
	 * @param TreeCacheManager $hashManager
	 * @param Authorizer $authorizer
	 * @param CrawlService $crawler
	 * @param IJobList $jobList
	 */
	public function __construct(BookmarkMapper $bookmarkMapper, FolderMapper $folderMapper, TagMapper $tagMapper, TreeMapper $treeMapper, LinkExplorer $linkExplorer, BookmarkPreviewer $bookmarkPreviewer, FaviconPreviewer $faviconPreviewer, FolderService $folders, IEventDispatcher $eventDispatcher, \OCA\Bookmarks\Service\TreeCacheManager $hashManager, UrlNormalizer $urlNormalizer, CrawlService $crawler, IJobList $jobList) {
		$this->bookmarkMapper = $bookmarkMapper;
		$this->treeMapper = $treeMapper;
		$this->linkExplorer = $linkExplorer;
		$this->folderMapper = $folderMapper;
		$this->tagMapper = $tagMapper;
		$this->bookmarkPreviewer = $bookmarkPreviewer;
		$this->faviconPreviewer = $faviconPreviewer;
		$this->folders = $folders;
		$this->eventDispatcher = $eventDispatcher;
		$this->hashManager = $hashManager;
		$this->urlNormalizer = $urlNormalizer;
		$this->crawler = $crawler;
		$this->jobList = $jobList;
	}

	/**
	 * @param $userId
	 * @param string $url
	 * @param string $title
	 * @param string $description
	 * @param array $tags
	 * @param array $folders
	 * @param string $userId
	 *
	 * @return Bookmark
	 *
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function create(string $userId, string $url = '', string $title = null, string $description = null, array $tags = null, $folders = []): Bookmark {
		$bookmark = null;
		$ownFolders = array_filter($folders, function ($folderId) use ($userId) {
			/**
			 * @var $folder Folder
			 */
			$folder = $this->folderMapper->find($folderId);
			return $folder->getUserId() === $userId;
		});
		$foreignFolders = array_diff($folders, $ownFolders);
		foreach ($foreignFolders as $folderId) {
			/**
			 * @var $folder Folder
			 */
			$folder = $this->folderMapper->find($folderId);
			$bookmark = $this->_addBookmark($folder->getUserId(), $url, $title, $description, $tags, [$folder->getId()]);
		}
		if (!empty($ownFolders)) {
			$bookmark = $this->_addBookmark($userId, $url, $title, $description, $tags, $ownFolders);
		}
		if ($bookmark === null) {
			$bookmark = $this->_addBookmark($userId, $url, $title, $description, $tags, [$this->folderMapper->findRootFolder($userId)->getId()]);
		}

		// Crawl this bookmark in a crawl job
		$this->jobList->add(IndividualCrawlJob::class, $bookmark->getId());

		return $bookmark;
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
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws UnsupportedOperation
	 */
	private function _addBookmark($userId, $url, string $title = null, $description = null, array $tags = null, array $folders = []): Bookmark {
		$bookmark = null;
		try {
			$bookmark = $this->bookmarkMapper->findByUrl($userId, $url);
		} catch (DoesNotExistException $e) {
			$protocols = '/^(https?|s?ftp):\/\//i';
			if (!preg_match($protocols, $url)) {
				// if no allowed protocol is given, evaluate https and https
				foreach (['https://', 'http://'] as $protocol) {
					$testUrl = $this->urlNormalizer->normalize($protocol . $url);
					try {
						$bookmark = $this->bookmarkMapper->findByUrl($userId, $testUrl);
						break;
					} catch (DoesNotExistException $e) {
						continue;
					}
				}
			}
		}

		if (!isset($bookmark)) {
			$bookmark = new Bookmark();

			if (!isset($title, $description)) {
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
			}

			$url = $data['url'] ?? $url;
			$title = $title ?? $data['basic']['title'] ?? $url;
			$title = trim($title);
			$description = $description ?? $data['basic']['description'] ?? '';

			$bookmark->setUrl($url);
		}

		if (isset($title)) {
			$bookmark->setTitle($title);
		}

		if (isset($description)) {
			$bookmark->setDescription($description);
		}
		$bookmark->setUserId($userId);
		$this->bookmarkMapper->insertOrUpdate($bookmark);

		if (isset($tags)) {
			$this->tagMapper->addTo($tags, $bookmark->getId());
		}

		$this->treeMapper->addToFolders(TreeMapper::TYPE_BOOKMARK, $bookmark->getId(), $folders);
		$this->eventDispatcher->dispatch(CreateEvent::class,
			new CreateEvent(TreeMapper::TYPE_BOOKMARK, $bookmark->getId())
		);
		return $bookmark;
	}

	/**
	 * @param $userId
	 * @param null $id
	 * @param string|null $url
	 * @param string|null $title
	 * @param string|null $description
	 * @param array|null $tags
	 * @param array|null $folders
	 * @param string $userId
	 *
	 * @return Bookmark
	 *
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function update(string $userId, $id, string $url = null, string $title = null, string $description = null, array $tags = null, array $folders = null): ?Bookmark {
		/**
		 * @var $bookmark Bookmark
		 */
		$bookmark = $this->bookmarkMapper->find($id);

		// Guard for no-op changes

		if (!isset($url) && !isset($title) && !isset($description) && !isset($tags) && !isset($folders)) {
			return $bookmark;
		}

		if (!isset($tags) && !isset($folders) && $url === $bookmark->getUrl() && $title === $bookmark->getTitle() && $description === $bookmark->getDescription()) {
			return $bookmark;
		}

		if ($url !== null) {
			if ($url !== $bookmark->getUrl()) {
				$bookmark->setAvailable(true);
			}
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
				$this->_addBookmark($bookmark->getUserId(), $bookmark->getUrl(), $bookmark->getTitle(), $bookmark->getDescription(), $tags ?? [], [$folder->getId()]);
			}

			/**
			 * @var $currentOwnFolders Folder[]
			 */
			$currentOwnFolders = $this->treeMapper->findParentsOf(TreeMapper::TYPE_BOOKMARK, $bookmark->getId());
			if ($bookmark->getUserId() !== $userId) {
				$currentInaccessibleOwnFolders = array_map(static function ($f) {
					return $f->getId();
				}, array_filter($currentOwnFolders, function ($folder) use ($userId) {
					return $this->folders->findShareByDescendantAndUser($folder, $userId) === null;
				})
				);
			} else {
				$currentInaccessibleOwnFolders = [];
			}

			$ownFolders = array_unique(array_merge($currentInaccessibleOwnFolders, $ownFolders));
			$this->treeMapper->setToFolders(TreeMapper::TYPE_BOOKMARK, $bookmark->getId(), $ownFolders);
			if (count($ownFolders) === 0) {
				$this->bookmarkMapper->delete($bookmark);
				return null;
			}
		}

		if ($tags !== null) {
			$this->tagMapper->setOn($tags, $bookmark->getId());
		}

		// trigger event
		$this->eventDispatcher->dispatch(
			UpdateEvent::class,
			new UpdateEvent(TreeMapper::TYPE_BOOKMARK, $bookmark->getId())
		);

		$this->bookmarkMapper->update($bookmark);

		return $bookmark;
	}

	/**
	 * @param $bookmarkId
	 * @param int $folderId
	 *
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function removeFromFolder(int $folderId, int $bookmarkId): void {
		$this->treeMapper->removeFromFolders(TreeMapper::TYPE_BOOKMARK, $bookmarkId, [$folderId]);
	}

	/**
	 * @param $bookmarkId
	 * @param int $folderId
	 *
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function addToFolder(int $folderId, int $bookmarkId): void {
		/**
		 * @var $folder Folder
		 */
		$folder = $this->folderMapper->find($folderId);
		/**
		 * @var $bookmark Bookmark
		 */
		$bookmark = $this->bookmarkMapper->find($bookmarkId);
		if ($folder->getUserId() === $bookmark->getUserId()) {
			$this->treeMapper->addToFolders(TreeMapper::TYPE_BOOKMARK, $bookmarkId, [$folderId]);
		} else {
			$tags = $this->tagMapper->findByBookmark($bookmarkId);
			$this->_addBookmark($folder->getUserId(), $bookmark->getUrl(), $bookmark->getTitle(), $bookmark->getDescription(), $tags, [$folder->getId()]);
		}
	}

	/**
	 * @param int $id
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function delete(int $id): void {
		$bookmark = $this->bookmarkMapper->find($id);
		$parents = $this->treeMapper->findParentsOf(TreeMapper::TYPE_BOOKMARK, $id);
		foreach ($parents as $parent) {
			$this->treeMapper->deleteEntry(TreeMapper::TYPE_BOOKMARK, $bookmark->getId(), $parent->getId());
		}
		if (count($parents) === 0) {
			$this->bookmarkMapper->delete($bookmark);
		}
	}

	/**
	 * @param $userId
	 * @param string $url
	 * @param string $userId
	 *
	 * @return Bookmark
	 *
	 * @throws DoesNotExistException|UrlParseError
	 */
	public function findByUrl(string $userId, string $url = ''): Bookmark {
		$params = new QueryParameters();
		/** @var Bookmark[] $bookmarks */
		$bookmarks = $this->bookmarkMapper->findAll($userId, $params->setUrl($url));
		if (isset($bookmarks[0])) {
			return $bookmarks[0];
		}

		throw new DoesNotExistException('URL does not exist');
	}

	/**
	 * @param int $id
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 */
	public function click(int $id): void {
		/** @var Bookmark $bookmark */
		$bookmark = $this->bookmarkMapper->find($id);
		$bookmark->incrementClickcount();
		$this->bookmarkMapper->update($bookmark);
	}

	/**
	 * @param int $id
	 * @return IImage|null
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getImage(int $id): ?IImage {
		/**
		 * @var $bookmark Bookmark
		 */
		$bookmark = $this->bookmarkMapper->find($id);
		return $this->bookmarkPreviewer->getImage($bookmark);
	}

	/**
	 * @param int $id
	 * @return IImage|null
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getFavicon(int $id): ?IImage {
		/**
		 * @var $bookmark Bookmark
		 */
		$bookmark = $this->bookmarkMapper->find($id);
		return $this->faviconPreviewer->getImage($bookmark);
	}

	/**
	 * @param string $userId
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function deleteAll(string $userId): void {
		$this->hashManager->setInvalidationEnabled(false);
		$rootFolder = $this->folderMapper->findRootFolder($userId);
		$bookmarks = $this->treeMapper->findChildren(TreeMapper::TYPE_BOOKMARK, $rootFolder->getId());
		foreach ($bookmarks as $bookmark) {
			$this->treeMapper->deleteEntry(TreeMapper::TYPE_BOOKMARK, $bookmark->getId(), $rootFolder->getId());
		}
		$folders = $this->treeMapper->findChildren(TreeMapper::TYPE_FOLDER, $rootFolder->getId());
		foreach ($folders as $folder) {
			$this->treeMapper->deleteEntry(TreeMapper::TYPE_FOLDER, $folder->getId());
		}
		$this->bookmarkMapper->deleteAll($userId);
		$this->hashManager->setInvalidationEnabled(true);
		$this->hashManager->invalidateFolder($rootFolder->getId());
	}
}
