<?php

namespace OCA\Bookmarks\Hooks;

use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\Share;
use OCA\Bookmarks\Db\SharedFolderMapper;
use OCA\Bookmarks\Db\ShareMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Service\BookmarkService;
use OCA\Bookmarks\Service\FolderService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IGroup;
use OCP\IUser;
use OCP\Share\IShare;
use OCP\User\Events\BeforeUserDeletedEvent;

class UserGroupListener implements IEventListener {
	private $userManager;
	/**
	 * @var ShareMapper
	 */
	private $shareMapper;
	/**
	 * @var SharedFolderMapper
	 */
	private $sharedFolderMapper;
	/**
	 * @var FolderService
	 */
	private $folders;
	/**
	 * @var FolderMapper
	 */
	private $folderMapper;
	/**
	 * @var TreeMapper
	 */
	private $treeMapper;
	/**
	 * @var BookmarkService
	 */
	private $bookmarks;

	public function __construct(ShareMapper $shareMapper, SharedFolderMapper $sharedFolderMapper, FolderService $folders, FolderMapper $folderMapper, TreeMapper $treeMapper, BookmarkService $bookmarks) {
		$this->shareMapper = $shareMapper;
		$this->sharedFolderMapper = $sharedFolderMapper;
		$this->folders = $folders;
		$this->folderMapper = $folderMapper;
		$this->treeMapper = $treeMapper;
		$this->bookmarks = $bookmarks;
	}

	/**
	 * @param Event $event
	 * @throws UnsupportedOperation
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function handle(Event $event): void {
		if ($event instanceof BeforeUserDeletedEvent) {
			$this->preDeleteUser($event->getUser());
		}
		if ($event instanceof UserAddedEvent) {
			$this->postAddUser($event->getGroup(), $event->getUser());
		}
		if ($event instanceof UserRemovedEvent) {
			$this->preRemoveUser($event->getGroup(), $event->getUser());
		}
		if ($event instanceof BeforeGroupDeletedEvent) {
			$this->preDeleteGroup($event->getGroup());
		}
	}

	/**
	 * @param IUser $user
	 * @throws UnsupportedOperation
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function preDeleteUser(IUser $user): void {
		$this->bookmarks->deleteAll($user->getUID());
		// delete dangling shares
		$sharesToDelete = $this->shareMapper->findByParticipant(IShare::TYPE_USER, $user->getUID());
		foreach ($sharesToDelete as $share) {
			$this->shareMapper->delete($share);
		}
	}

	/**
	 * @param IGroup $group
	 */
	public function preDeleteGroup(IGroup $group): void {
		$sharesToDelete = $this->shareMapper->findByParticipant(IShare::TYPE_GROUP, $group->getGID());
		foreach ($sharesToDelete as $share) {
			$this->shareMapper->delete($share);
		}
	}

	/**
	 * @param IGroup $group
	 * @param IUser $user
	 * @throws UnsupportedOperation
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function preRemoveUser(IGroup $group, IUser $user): void {
		$sharedFoldersToDelete = $this->sharedFolderMapper->findByParticipantAndUser(IShare::TYPE_GROUP, $group->getGID(), $user->getUID());
		foreach ($sharedFoldersToDelete as $sharedFolder) {
			$this->treeMapper->deleteEntry(TreeMapper::TYPE_SHARE, $sharedFolder->getId());
		}
	}

	/**
	 * @param IGroup $group
	 * @param IUser $user
	 * @throws UnsupportedOperation
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function postAddUser(IGroup $group, IUser $user): void {
		/**
		 * @var Share[] $shares
		 */
		$shares = $this->shareMapper->findByParticipant(IShare::TYPE_GROUP, $group->getGID());
		foreach ($shares as $share) {
			if ($share->getOwner() === $user->getUID()) {
				continue;
			}
			/**
			 * @var Folder $folder
			 */
			$folder = $this->folderMapper->find($share->getFolderId());
			$this->folders->addSharedFolder($share, $folder, $user->getUID());
		}
	}
}
