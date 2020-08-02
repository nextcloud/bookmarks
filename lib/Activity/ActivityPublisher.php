<?php


namespace OCA\Bookmarks\Activity;

use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\SharedFolder;
use OCA\Bookmarks\Db\SharedFolderMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Events\BeforeDeleteEvent;
use OCA\Bookmarks\Events\ChangeEvent;
use OCA\Bookmarks\Events\CreateEvent;
use OCA\Bookmarks\Events\MoveEvent;
use OCA\Bookmarks\Service\Authorizer;
use OCP\Activity\IManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IL10N;

class ActivityPublisher implements IEventListener {
	/**
	 * @var IManager
	 */
	private $activityManager;

	private $appName;
	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var SharedFolderMapper
	 */
	private $sharedFolderMapper;
	/**
	 * @var FolderMapper
	 */
	private $folderMapper;
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

	public function __construct($appName, IManager $activityManager, IL10N $l, SharedFolderMapper $sharedFolderMapper, FolderMapper $folderMapper, BookmarkMapper $bookmarkMapper, TreeMapper $treeMapper, Authorizer $authorizer) {
		$this->appName = $appName;
		$this->activityManager = $activityManager;
		$this->l = $l;
		$this->sharedFolderMapper = $sharedFolderMapper;
		$this->folderMapper = $folderMapper;
		$this->bookmarkMapper = $bookmarkMapper;
		$this->treeMapper = $treeMapper;
		$this->authorizer = $authorizer;
	}

	/**
	 * Handle events
	 *
	 * @param Event $event
	 */
	public function handle(Event $event): void {
		if (!($event instanceof ChangeEvent)) {
			return;
		}
		if ($this->authorizer->getUserId() === null) {
			return;
		}
		switch ($event->getType()) {
			case TreeMapper::TYPE_FOLDER:
				$this->publishFolder($event);
				break;
			case TreeMapper::TYPE_BOOKMARK:
				$this->publishBookmark($event);
				break;
			case TreeMapper::TYPE_SHARE:
				$this->publishShare($event);
				break;
		}
	}

	public function publishShare(ChangeEvent $event) {
		$activity = $this->activityManager->generateEvent();
		$activity->setApp($this->appName);
		$activity->setType('bookmarks');

		$activity->setAuthor($this->authorizer->getUserId());
		$activity->setTimestamp(time());

		/**
		 * @var $sharedFolder SharedFolder
		 */
		try {
			$sharedFolder = $this->sharedFolderMapper->find($event->getId());
		} catch (DoesNotExistException $e) {
			return;
		} catch (MultipleObjectsReturnedException $e) {
			return;
		}

		$activity->setObject(TreeMapper::TYPE_FOLDER, $sharedFolder->getFolderId());

		if ($event instanceof CreateEvent) {
			$activity->setSubject('share_created', ['folder' => $sharedFolder->getTitle(), 'sharee' => $sharedFolder->getUserId()]);
		} elseif ($event instanceof BeforeDeleteEvent) {
			$activity->setSubject('share_deleted', ['folder' => $sharedFolder->getTitle(), 'sharee' => $sharedFolder->getUserId()]);
		} else {
			return;
		}

		foreach ([$activity->getAuthor(), $sharedFolder->getUserId()] as $user) {
			$activity->setAffectedUser($user);
			$this->activityManager->publish($activity);
		}
	}

	public function publishFolder(ChangeEvent $event) {
		$activity = $this->activityManager->generateEvent();
		$activity->setApp($this->appName);
		$activity->setType('bookmarks');

		$activity->setAuthor($this->authorizer->getUserId());
		$activity->setTimestamp(time());

		/**
		 * @var $folder Folder
		 */
		try {
			$folder = $this->folderMapper->find($event->getId());
		} catch (DoesNotExistException $e) {
			return;
		} catch (MultipleObjectsReturnedException $e) {
			return;
		}

		$activity->setObject(TreeMapper::TYPE_FOLDER, $folder->getId());
		if ($event instanceof CreateEvent) {
			$activity->setSubject('folder_created', ['folder' => $folder->getTitle()]);
		} elseif ($event instanceof BeforeDeleteEvent) {
			$activity->setSubject('folder_deleted', ['folder' => $folder->getTitle()]);
		} elseif ($event instanceof MoveEvent) {
			$activity->setSubject('folder_moved', ['folder' => $folder->getTitle()]);
		} else {
			return;
		}

		/**
		 * @var $shares SharedFolder[]
		 */
		$shares = $this->sharedFolderMapper->findByOwner($this->authorizer->getUserId());
		$shares = array_merge($shares, $this->sharedFolderMapper->findByUser($this->authorizer->getUserId()));
		$affectedShares = array_filter($shares, function ($sharedFolder) use ($folder) {
			return $this->treeMapper->hasDescendant($sharedFolder->getFolderId(), TreeMapper::TYPE_FOLDER, $folder->getId());
		});
		$affectedUsers = array_map(static function ($sharedFolder) {
			return $sharedFolder->getUserId();
		}, $affectedShares);
		$affectedUsers[] = $folder->getUserId();
		$affectedUsers[] = $this->authorizer->getUserId();

		$affectedUsers = array_unique($affectedUsers);

		foreach ($affectedUsers as $user) {
			$activity->setAffectedUser($user);
			$this->activityManager->publish($activity);
		}
	}

	public function publishBookmark(ChangeEvent $event) {
		$activity = $this->activityManager->generateEvent();
		$activity->setApp($this->appName);
		$activity->setType('bookmarks');

		$activity->setAuthor($this->authorizer->getUserId());
		$activity->setTimestamp(time());

		/**
		 * @var $bookmark Bookmark
		 */
		try {
			$bookmark = $this->bookmarkMapper->find($event->getId());
		} catch (DoesNotExistException $e) {
			return;
		} catch (MultipleObjectsReturnedException $e) {
			return;
		}
		$activity->setObject(TreeMapper::TYPE_BOOKMARK, $bookmark->getId());

		if ($event instanceof CreateEvent) {
			$activity->setSubject('bookmark_created', ['bookmark' => $bookmark->getTitle()]);
		} elseif ($event instanceof BeforeDeleteEvent) {
			$activity->setSubject('bookmark_deleted', ['bookmark' => $bookmark->getTitle()]);
		} else {
			return;
		}

		/**
		 * @var $shares SharedFolder[]
		 */
		$shares = $this->sharedFolderMapper->findByOwner($this->authorizer->getUserId());
		$shares = array_merge($shares, $this->sharedFolderMapper->findByUser($this->authorizer->getUserId()));
		$affectedShares = array_filter($shares, function ($sharedFolder) use ($bookmark) {
			return $this->treeMapper->hasDescendant($sharedFolder->getFolderId(), TreeMapper::TYPE_BOOKMARK, $bookmark->getId());
		});
		$affectedUsers = array_map(static function ($sharedFolder) {
			return $sharedFolder->getUserId();
		}, $affectedShares);
		$affectedUsers[] = $bookmark->getUserId();
		$affectedUsers[] = $this->authorizer->getUserId();

		$affectedUsers = array_unique($affectedUsers);

		foreach ($affectedUsers as $user) {
			$activity->setAffectedUser($user);
			$this->activityManager->publish($activity);
		}
	}
}
