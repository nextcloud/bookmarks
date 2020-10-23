<?php

namespace OCA\Bookmarks\Service;

use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\SharedFolderMapper;
use OCA\Bookmarks\Db\ShareMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Events\ChangeEvent;
use OCA\Bookmarks\Events\MoveEvent;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\ICache;
use OCP\ICacheFactory;
use UnexpectedValueException;

class HashManager {

	/**
	 * @var ICache
	 */
	protected $cache;
	/**
	 * @var BookmarkMapper
	 */
	protected $bookmarkMapper;
	/**
	 * @var ShareMapper
	 */
	protected $shareMapper;
	/**
	 * @var SharedFolderMapper
	 */
	protected $sharedFolderMapper;
	/**
	 * @var TreeMapper
	 */
	protected $treeMapper;
	/**
	 * @var FolderMapper
	 */
	protected $folderMapper;

	/**
	 * FolderMapper constructor.
	 *
	 * @param TreeMapper $treeMapper
	 * @param FolderMapper $folderMapper
	 * @param BookmarkMapper $bookmarkMapper
	 * @param ShareMapper $shareMapper
	 * @param SharedFolderMapper $sharedFolderMapper
	 * @param ICacheFactory $cacheFactory
	 */
	public function __construct(TreeMapper $treeMapper, FolderMapper $folderMapper, BookmarkMapper $bookmarkMapper, ShareMapper $shareMapper, SharedFolderMapper $sharedFolderMapper, ICacheFactory $cacheFactory) {
		$this->treeMapper = $treeMapper;
		$this->folderMapper = $folderMapper;
		$this->bookmarkMapper = $bookmarkMapper;
		$this->shareMapper = $shareMapper;
		$this->sharedFolderMapper = $sharedFolderMapper;
		$this->cache = $cacheFactory->createLocal('bookmarks:hashes');
	}

	/**
	 * @param string $type
	 * @param int $folderId
	 * @param string $userId
	 * @return string
	 */
	private function getCacheKey(string $type, int $folderId) : string {
		return $type . ':'. ',' . $folderId;
	}

	/**
	 * @param int $folderId
	 * @param array $previousFolders
	 */
	public function invalidateFolder(int $folderId, array $previousFolders = []): void {
		if (in_array($folderId, $previousFolders, true)) {
			// In case we have run into a folder loop
			return;
		}
		$key = $this->getCacheKey(TreeMapper::TYPE_FOLDER, $folderId);
		$this->cache->remove($key);
		$previousFolders[] = $folderId;

		// Invalidate parent
		try {
			$parentFolder = $this->treeMapper->findParentOf(TreeMapper::TYPE_FOLDER, $folderId);
			$this->invalidateFolder($parentFolder->getId(), $previousFolders);
		} catch (DoesNotExistException $e) {
			return;
		} catch (MultipleObjectsReturnedException $e) {
			return;
		}
	}

	public function invalidateBookmark(int $bookmarkId): void {
		$key = $this->getCacheKey(TreeMapper::TYPE_BOOKMARK, $bookmarkId);
		$this->cache->remove($key);

		// Invalidate parent
		$parentFolders = $this->treeMapper->findParentsOf(TreeMapper::TYPE_BOOKMARK, $bookmarkId);
		foreach ($parentFolders as $parentFolder) {
			$this->invalidateFolder($parentFolder->getId());
		}
	}

	/**
	 * @param int $folderId
	 * @param array $fields
	 * @param string $userId
	 * @return string
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function hashFolder($userId, int $folderId, $fields = ['title', 'url']) : string {
		$key = $this->getCacheKey(TreeMapper::TYPE_FOLDER, $folderId);
		$hash = $this->cache->get($key);
		$selector = $userId . ':' . implode(',', $fields);
		if (isset($hash[$selector])) {
			return $hash[$selector];
		}
		if (!isset($hash)) {
			$hash = [];
		}

		$entity = $this->folderMapper->find($folderId);
		$children = $this->treeMapper->getChildrenOrder($folderId);
		$childHashes = array_map(function ($item) use ($fields, $entity) {
			switch ($item['type']) {
				case TreeMapper::TYPE_BOOKMARK:
					return $this->hashBookmark($item['id'], $fields);
				case TreeMapper::TYPE_FOLDER:
					return $this->hashFolder($entity->getUserId(), $item['id'], $fields);
				default:
					throw new UnexpectedValueException('Expected bookmark or folder, but not ' . $item['type']);
			}
		}, $children);
		$folder = [];
		if ($entity->getUserId() !== $userId) {
			$folder['title'] = $this->sharedFolderMapper->findByFolderAndUser($folderId, $userId)->getTitle();
		} elseif ($entity->getTitle() !== null) {
			$folder['title'] = $entity->getTitle();
		}
		$folder['children'] = $childHashes;
		$hash[$selector] = hash('sha256', json_encode($folder, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

		$this->cache->set($key, $hash, 60 * 60 * 24);
		return $hash[$selector];
	}

	/**
	 * @param int $bookmarkId
	 * @param array $fields
	 * @return string
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function hashBookmark(int $bookmarkId, array $fields = ['title', 'url']): string {
		$key = $this->getCacheKey(TreeMapper::TYPE_BOOKMARK, $bookmarkId);
		$hash = $this->cache->get($key);
		$selector = implode(',', $fields);
		if (isset($hash[$selector])) {
			return $hash[$selector];
		}
		if (!isset($hash)) {
			$hash = [];
		}

		$entity = $this->bookmarkMapper->find($bookmarkId);
		$bookmark = [];
		foreach ($fields as $field) {
			$bookmark[$field] = $entity->{'get' . $field}();
		}
		$hash[$selector] = hash('sha256', json_encode($bookmark, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$this->cache->set($key, $hash, 60 * 60 * 24);
		return $hash[$selector];
	}

	/**
	 * Handle events
	 *
	 * @param ChangeEvent $event
	 */
	public function handle(ChangeEvent $event): void {
		switch ($event->getType()) {
			case TreeMapper::TYPE_FOLDER:
				$this->invalidateFolder($event->getId());
				break;
			case TreeMapper::TYPE_BOOKMARK:
				$this->invalidateBookmark($event->getId());
				break;
		}
		if ($event instanceof MoveEvent) {
			if ($event->getNewParent() !== null) {
				$this->invalidateFolder($event->getNewParent());
			}
			if ($event->getOldParent() !== null) {
				$this->invalidateFolder($event->getOldParent());
			}
		}
	}
}
