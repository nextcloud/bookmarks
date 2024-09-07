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
use OCA\Bookmarks\Db\SharedFolderMapper;
use OCA\Bookmarks\Db\ShareMapper;
use OCA\Bookmarks\Db\TagMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Events\ChangeEvent;
use OCA\Bookmarks\Events\MoveEvent;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

/**
 * @psalm-implements IEventListener<ChangeEvent>
 */
class TreeCacheManager implements IEventListener {
	public const TTL = 60 * 60 * 24 * 30; // one month
	public const CATEGORY_HASH = 'hashes';
	public const CATEGORY_SUBFOLDERS = 'subFolders';
	public const CATEGORY_DELETED_SUBFOLDERS = 'deletedSubFolders';
	public const CATEGORY_FOLDERCOUNT = 'folderCount';
	public const CATEGORY_CHILDREN = 'children';
	public const CATEGORY_CHILDREN_LAYER = 'children_layer';
	public const CATEGORY_CHILDORDER = 'childOrder';

	/**
	 * @var bool
	 */
	private $enabled = true;

	/**
	 * @var ICache[]
	 */
	private $caches = [];

	/**
	 * FolderMapper constructor.
	 *
	 * @param FolderMapper $folderMapper
	 * @param BookmarkMapper $bookmarkMapper
	 * @param ShareMapper $shareMapper
	 * @param SharedFolderMapper $sharedFolderMapper
	 * @param ICacheFactory $cacheFactory
	 * @param ContainerInterface $appContainer
	 * @param TagMapper $tagMapper
	 */
	public function __construct(
		protected FolderMapper $folderMapper,
		protected BookmarkMapper $bookmarkMapper,
		protected ShareMapper $shareMapper,
		protected SharedFolderMapper $sharedFolderMapper,
		protected ICacheFactory $cacheFactory,
		protected ContainerInterface $appContainer,
		protected TagMapper $tagMapper
	) {
		$this->caches[self::CATEGORY_HASH] = $cacheFactory->createDistributed('bookmarks:'.self::CATEGORY_HASH);
		$this->caches[self::CATEGORY_SUBFOLDERS] = $cacheFactory->createDistributed('bookmarks:'.self::CATEGORY_SUBFOLDERS);
		$this->caches[self::CATEGORY_DELETED_SUBFOLDERS] = $cacheFactory->createDistributed('bookmarks:'.self::CATEGORY_DELETED_SUBFOLDERS);
		$this->caches[self::CATEGORY_FOLDERCOUNT] = $cacheFactory->createDistributed('bookmarks:'.self::CATEGORY_FOLDERCOUNT);
		$this->caches[self::CATEGORY_CHILDREN] = $cacheFactory->createDistributed('bookmarks:'.self::CATEGORY_CHILDREN);
		$this->caches[self::CATEGORY_CHILDREN_LAYER] = $cacheFactory->createDistributed('bookmarks:'.self::CATEGORY_CHILDREN_LAYER);
		$this->caches[self::CATEGORY_CHILDORDER] = $cacheFactory->createDistributed('bookmarks:'.self::CATEGORY_CHILDORDER);
	}


	private function getTreeMapper(): TreeMapper {
		return $this->appContainer->get(TreeMapper::class);
	}

	/**
	 * @param string $type
	 * @param int $folderId
	 * @return string
	 */
	private function getCacheKey(string $type, int $folderId) : string {
		return $type . ':'. $folderId;
	}

	/**
	 * @param string $category
	 * @param string $type
	 * @param int $id
	 * @return mixed
	 */
	public function get(string $category, string $type, int $id) {
		$key = $this->getCacheKey($type, $id);
		return $this->caches[$category]->get($key);
	}

	/**
	 * @param string $category
	 * @param string $type
	 * @param int $id
	 * @param mixed $data
	 * @return mixed
	 */
	public function set(string $category, string $type, int $id, $data) {
		$key = $this->getCacheKey($type, $id);
		return $this->caches[$category]->set($key, $data, self::TTL);
	}

	/**
	 * @param string $type
	 * @param int $id
	 */
	public function remove(string $type, int $id, array $previousFolders = []): void {
		$key = $this->getCacheKey($type, $id);
		foreach ($this->caches as $cacheType => $cache) {
			if ($cacheType === self::CATEGORY_CHILDREN_LAYER && count($previousFolders) > 1) {
				continue;
			}
			$cache->remove($key);
		}
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
		$this->remove(TreeMapper::TYPE_FOLDER, $folderId, $previousFolders);
		$previousFolders[] = $folderId;

		// Invalidate parent
		try {
			$parentFolder = $this->getTreeMapper()->findParentOf(TreeMapper::TYPE_FOLDER, $folderId);
			$this->invalidateFolder($parentFolder->getId(), $previousFolders);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return;
		}

		// Invalidate share participants
		$sharedFolders = $this->sharedFolderMapper->findByFolder($folderId);
		foreach ($sharedFolders as $sharedFolder) {
			try {
				$parentFolder = $this->getTreeMapper()->findParentOf(TreeMapper::TYPE_SHARE, $sharedFolder->getId());
				$this->invalidateFolder($parentFolder->getId(), $previousFolders);
			} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
				continue;
			}
		}
	}

	public function invalidateBookmark(int $bookmarkId): void {
		$this->remove(TreeMapper::TYPE_BOOKMARK, $bookmarkId);

		// Invalidate parent
		$parentFolders = $this->getTreeMapper()->findParentsOf(TreeMapper::TYPE_BOOKMARK, $bookmarkId);
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
	 * @throws MultipleObjectsReturnedException|\JsonException
	 * @throws UnsupportedOperation
	 */
	public function hashFolder($userId, int $folderId, array $fields = ['title', 'url']) : string {
		$hash = $this->get(self::CATEGORY_HASH, TreeMapper::TYPE_FOLDER, $folderId);
		$selector = $userId . ':' . implode(',', $fields);
		if (isset($hash[$selector])) {
			return $hash[$selector];
		}
		if (!isset($hash)) {
			$hash = [];
		}

		/** @var Folder $entity */
		$entity = $this->folderMapper->find($folderId);
		$rootFolder = $this->folderMapper->findRootFolder($userId);
		$children = $this->getTreeMapper()->getChildrenOrder($folderId);
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
		} elseif ($entity->getTitle() !== null && $entity->getId() !== $rootFolder->getId()) {
			$folder['title'] = $entity->getTitle();
		}
		$folder['children'] = $childHashes;
		$hash[$selector] = hash('sha256', json_encode($folder, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

		$this->set(self::CATEGORY_HASH, TreeMapper::TYPE_FOLDER, $folderId, $hash);
		return $hash[$selector];
	}

	/**
	 * @param int $bookmarkId
	 * @param array $fields
	 * @return string
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException|\JsonException
	 * @throws UnsupportedOperation
	 */
	public function hashBookmark(int $bookmarkId, array $fields = ['title', 'url']): string {
		$hash = $this->get(self::CATEGORY_HASH, TreeMapper::TYPE_BOOKMARK, $bookmarkId);
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
			if ($field === 'tags') {
				$bookmark[$field] = $this->tagMapper->findByBookmark($bookmarkId);
				continue;
			}
			try {
				$bookmark[$field] = $entity->{'get' . $field}();
			} catch (\BadFunctionCallException $e) {
				throw new UnsupportedOperation('Field '.$field.' does not exist');
			}
		}
		$hash[$selector] = hash('sha256', json_encode($bookmark, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$this->set(self::CATEGORY_HASH, TreeMapper::TYPE_BOOKMARK, $bookmarkId, $hash);
		return $hash[$selector];
	}

	/**
	 * Handle events
	 *
	 * @param Event $event
	 */
	public function handle(Event $event): void {
		if ($this->enabled === false) {
			return;
		}
		if (!($event instanceof ChangeEvent)) {
			return;
		}
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

	public function setInvalidationEnabled(bool $enabled): void {
		$this->enabled = $enabled;
	}

	public function invalidateAll() {
		foreach ($this->caches as $cache) {
			$cache->clear();
		}
	}
}
