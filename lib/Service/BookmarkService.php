<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
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
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;

class BookmarkService {
	public const PROTOCOLS_REGEX = '/^(https?|s?ftp|file|javascript):/i';

	public function __construct(
		private BookmarkMapper $bookmarkMapper,
		private FolderMapper $folderMapper,
		private TagMapper $tagMapper,
		private TreeMapper $treeMapper,
		private LinkExplorer $linkExplorer,
		private BookmarkPreviewer $bookmarkPreviewer,
		private FaviconPreviewer $faviconPreviewer,
		private FolderService $folders,
		private IEventDispatcher $eventDispatcher,
		private \OCA\Bookmarks\Service\TreeCacheManager $hashManager,
		private UrlNormalizer $urlNormalizer,
		private IJobList $jobList,
	) {
	}

	/**
	 * @param string $userId
	 * @param string $url
	 * @param string $title
	 * @param string $description
	 * @param array $tags
	 * @param array $folders
	 *
	 * @return Bookmark
	 *
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws Exception
	 */
	public function create(string $userId, string $url = '', ?string $title = null, ?string $description = null, ?array $tags = null, $folders = []): Bookmark {
		$bookmark = null;
		$ownFolders = array_filter($folders, function ($folderId) use ($userId) {
			/**
			 * @var Folder $folder
			 */
			$folder = $this->folderMapper->find($folderId);
			return $folder->getUserId() === $userId;
		});
		$foreignFolders = array_diff($folders, $ownFolders);
		foreach ($foreignFolders as $folderId) {
			/**
			 * @var Folder $folder
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
	 * @param $userId
	 * @param $url
	 * @param string|null $title
	 * @param string|null $description
	 * @param array|null $tags
	 * @param array $folders
	 * @return Bookmark
	 * @throws AlreadyExistsError
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws DoesNotExistException
	 */
	private function _addBookmark($userId, $url, ?string $title = null, ?string $description = null, ?array $tags = null, array $folders = []): Bookmark {
		$isInsert = true;
		$bookmark = null;

		try {
			$bookmark = $this->bookmarkMapper->findByUrl($userId, $url);
			$isInsert = false;
		} catch (DoesNotExistException) {
			if (!preg_match(self::PROTOCOLS_REGEX, $url)) {
				// if no allowed protocol is given, evaluate https and https
				foreach (['https://', 'http://'] as $protocol) {
					try {
						$testUrl = $this->urlNormalizer->normalize($protocol . $url);
						$bookmark = $this->bookmarkMapper->findByUrl($userId, $testUrl);
						$isInsert = false;
						break;
					} catch (UrlParseError|DoesNotExistException) {
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
				if (preg_match('/^https?:\/\//i', $url)) {
					$testUrl = $this->urlNormalizer->normalize($url);
					$data = $this->linkExplorer->get($testUrl);
				} else {
					// if no allowed protocol is given, evaluate https and http
					foreach (['https://', 'http://', ''] as $protocol) {
						$testUrl = $protocol . $url;
						$data = $this->linkExplorer->get($testUrl);
						if (isset($data['basic']['title'])) {
							$url = $protocol . $url;
							break;
						}
					}
				}
			}

			$url = $data['url'] ?? $url;
			if (!preg_match(self::PROTOCOLS_REGEX, $url)) {
				$url = 'http://' . $url;
			}
			$url = $this->urlNormalizer->normalize($url);

			$title = $title ?? trim($data['basic']['title']) ?? trim($url);
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
		if ($isInsert) {
			$bookmark = $this->bookmarkMapper->insertOrUpdate($bookmark);
		} else {
			$bookmark = $this->bookmarkMapper->update($bookmark);
		}

		if (isset($tags)) {
			$this->tagMapper->addTo($tags, $bookmark->getId());
		}

		$this->treeMapper->addToFolders(TreeMapper::TYPE_BOOKMARK, $bookmark->getId(), $folders);
		foreach ($folders as $folderId) {
			$this->treeMapper->softUndeleteEntry(TreeMapper::TYPE_BOOKMARK, $bookmark->getId(), $folderId);
		}
		$this->eventDispatcher->dispatch(CreateEvent::class,
			new CreateEvent(TreeMapper::TYPE_BOOKMARK, $bookmark->getId())
		);
		return $bookmark;
	}

	/**
	 * @param string $userId
	 * @param int $id
	 * @param string|null $url
	 * @param string|null $title
	 * @param string|null $description
	 * @param array|null $tags
	 * @param array|null $folders
	 *
	 * @return Bookmark
	 *
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws Exception
	 */
	public function update(string $userId, int $id, ?string $url = null, ?string $title = null, ?string $description = null, ?array $tags = null, ?array $folders = null): ?Bookmark {
		/**
		 * @var Bookmark $bookmark
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
			try {
				$oldBookmark = $this->bookmarkMapper->findByUrl($userId, $url);
				if (count($this->treeMapper->findParentsOf(TreeMapper::TYPE_BOOKMARK, $oldBookmark->getId(), false)) === 0) {
					$this->treeMapper->deleteEntry(TreeMapper::TYPE_BOOKMARK, $oldBookmark->getId());
				} elseif ($oldBookmark->getId() !== $bookmark->getId()) {
					throw new AlreadyExistsError('Bookmark already exists');
				}
			} catch (DoesNotExistException $e) {
				// pass
			}
			if (!preg_match(self::PROTOCOLS_REGEX, $url)) {
				throw new UrlParseError();
			}
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
			 * @var Folder[] $currentOwnFolders
			 */
			$currentOwnFolders = $this->treeMapper->findParentsOf(TreeMapper::TYPE_BOOKMARK, $bookmark->getId());
			// Updating user may not be the owner of the bookmark
			// We have to keep the bookmark in folders that are inaccessible to the current user
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
			} else {
				foreach ($ownFolders as $folderId) {
					$this->treeMapper->softUndeleteEntry(TreeMapper::TYPE_BOOKMARK, $bookmark->getId(), $folderId);
				}
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
	 * @throws Exception
	 */
	public function removeFromFolder(int $folderId, int $bookmarkId, bool $hardDelete = false): void {
		if ($hardDelete) {
			$this->treeMapper->removeFromFolders(TreeMapper::TYPE_BOOKMARK, $bookmarkId, [$folderId]);
		} else {
			$this->treeMapper->softDeleteEntry(TreeMapper::TYPE_BOOKMARK, $bookmarkId, $folderId);
		}
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
	 * @throws Exception
	 */
	public function addToFolder(int $folderId, int $bookmarkId): void {
		$folder = $this->folderMapper->find($folderId);
		$bookmark = $this->bookmarkMapper->find($bookmarkId);
		if ($folder->getUserId() === $bookmark->getUserId()) {
			$this->treeMapper->addToFolders(TreeMapper::TYPE_BOOKMARK, $bookmarkId, [$folderId]);
		} else {
			$tags = $this->tagMapper->findByBookmark($bookmarkId);
			$this->_addBookmark($folder->getUserId(), $bookmark->getUrl(), $bookmark->getTitle(), $bookmark->getDescription(), $tags, [$folder->getId()]);
		}
	}

	/**
	 * @param int $folderId
	 * @param int $bookmarkId
	 * @throws DoesNotExistException|MultipleObjectsReturnedException|UnsupportedOperation|Exception
	 */
	public function undeleteInFolder(int $folderId, int $bookmarkId): void {
		$this->folderMapper->find($folderId);
		$this->bookmarkMapper->find($bookmarkId);
		$this->treeMapper->softUndeleteEntry(TreeMapper::TYPE_BOOKMARK, $bookmarkId, $folderId);
	}

	/**
	 * @param int $id
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function delete(int $id): void {
		$bookmark = $this->bookmarkMapper->find($id);
		$parents = $this->treeMapper->findParentsOf(TreeMapper::TYPE_BOOKMARK, $id, true);
		foreach ($parents as $parent) {
			$this->treeMapper->deleteEntry(TreeMapper::TYPE_BOOKMARK, $bookmark->getId(), $parent->getId());
		}
		if (count($parents) === 0) {
			$this->bookmarkMapper->delete($bookmark);
		}
	}

	/**
	 * @param int $id
	 * @return Bookmark|null
	 */
	public function findById(int $id) : ?Bookmark {
		try {
			return $this->bookmarkMapper->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	/**
	 * @param string $userId
	 *
	 * @param string $url
	 * @return Bookmark
	 *
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws UrlParseError
	 */
	public function findByUrl(string $userId, string $url = ''): Bookmark {
		$params = new QueryParameters();
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
		$bookmark = $this->bookmarkMapper->find($id);
		return $this->bookmarkPreviewer->getImage($bookmark, true);
	}

	/**
	 * @param int $id
	 * @return IImage|null
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getFavicon(int $id): ?IImage {
		$bookmark = $this->bookmarkMapper->find($id);
		return $this->faviconPreviewer->getImage($bookmark, true);
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

	/**
	 * @param string $userId
	 * @return \Generator<void, Bookmark>
	 */
	public function getIterator(string $userId): \Generator {
		return $this->bookmarkMapper->getIterator($userId, new QueryParameters());
	}
}
