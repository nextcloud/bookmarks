<?php

/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service;

use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\PublicFolder;
use OCA\Bookmarks\Db\PublicFolderMapper;
use OCA\Bookmarks\Db\Share;
use OCA\Bookmarks\Db\SharedFolder;
use OCA\Bookmarks\Db\SharedFolderMapper;
use OCA\Bookmarks\Db\ShareMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Events\CreateEvent;
use OCA\Bookmarks\Events\UpdateEvent;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\HtmlParseError;
use OCA\Bookmarks\Exception\UnauthorizedAccessError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IGroupManager;
use OCP\Share\IShare;

class FolderService {

	/**
	 * FolderService constructor.
	 *
	 * @param FolderMapper $folderMapper
	 * @param TreeMapper $treeMapper
	 * @param ShareMapper $shareMapper
	 * @param SharedFolderMapper $sharedFolderMapper
	 * @param PublicFolderMapper $publicFolderMapper
	 * @param IGroupManager $groupManager
	 * @param HtmlImporter $htmlImporter
	 * @param IEventDispatcher $eventDispatcher
	 */
	public function __construct(
		private FolderMapper $folderMapper,
		private TreeMapper $treeMapper,
		private ShareMapper $shareMapper,
		private SharedFolderMapper $sharedFolderMapper,
		private PublicFolderMapper $publicFolderMapper,
		private IGroupManager $groupManager,
		private HtmlImporter $htmlImporter,
		private IEventDispatcher $eventDispatcher,
		private CirclesService $circlesService,
		private SettingsService $settings,
		private Authorizer $authorizer,
	) {
	}

	public function getRootFolder(string $userId) : Folder {
		return $this->folderMapper->findRootFolder($userId);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findById(int $id) : Folder {
		return $this->folderMapper->find($id);
	}

	/**
	 * @param $title
	 * @param $parentFolderId
	 * @return Folder
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws Exception
	 */
	public function create($title, $parentFolderId): Folder {
		$parentFolder = $this->folderMapper->find($parentFolderId);
		$folder = new Folder();
		$folder->setTitle($title);
		$folder->setUserId($parentFolder->getUserId());

		$this->folderMapper->insert($folder);
		$this->treeMapper->move(TreeMapper::TYPE_FOLDER, $folder->getId(), $parentFolderId);

		$this->eventDispatcher->dispatchTyped(new CreateEvent(TreeMapper::TYPE_FOLDER, $folder->getId()));
		return $folder;
	}

	/**
	 * @param Folder $folder
	 * @param $userId
	 * @return Share|null
	 */
	public function findShareByDescendantAndUser(Folder $folder, $userId): ?Share {
		$shares = $this->shareMapper->findByOwnerAndUser($folder->getUserId(), $userId);
		foreach ($shares as $share) {
			if ($share->getFolderId() === $folder->getId() || $this->treeMapper->hasDescendant($share->getFolderId(), TreeMapper::TYPE_FOLDER, $folder->getId())) {
				return $share;
			}
		}
		return null;
	}

	/**
	 * @param $userId
	 * @param $folderId
	 * @return Folder|SharedFolder
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findSharedFolderOrFolder($userId, $folderId): Folder|SharedFolder {
		$folder = $this->folderMapper->find($folderId);
		if ($userId === null || $userId === $folder->getUserId()) {
			return $folder;
		}

		try {
			$sharedFolder = $this->sharedFolderMapper->findByFolderAndUser($folder->getId(), $userId);
			return $sharedFolder;
		} catch (DoesNotExistException $e) {
			// noop
		}

		return $folder;
	}

	/**
	 * @param string $userId
	 * @param int $folderId
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws Exception
	 */
	public function deleteSharedFolderOrFolder(?string $userId, int $folderId, bool $hardDelete): void {
		$folder = $this->folderMapper->find($folderId);

		if ($userId === null || $userId === $folder->getUserId()) {
			if ($hardDelete) {
				$this->treeMapper->deleteEntry(TreeMapper::TYPE_FOLDER, $folder->getId());
			} else {
				$this->treeMapper->softDeleteEntry(TreeMapper::TYPE_FOLDER, $folder->getId());
			}
			return;
		}

		try {
			// folder is shared folder
			$sharedFolder = $this->sharedFolderMapper->findByFolderAndUser($folder->getId(), $userId);
			if ($hardDelete) {
				$this->treeMapper->deleteEntry(TreeMapper::TYPE_SHARE, $sharedFolder->getId());
			} else {
				$this->treeMapper->softDeleteEntry(TreeMapper::TYPE_SHARE, $sharedFolder->getId());
			}
			return;
		} catch (DoesNotExistException $e) {
			// noop
		}

		// folder is subfolder of share
		if ($hardDelete) {
			$this->treeMapper->deleteEntry(TreeMapper::TYPE_FOLDER, $folder->getId());
			$this->folderMapper->delete($folder);
		} else {
			$this->treeMapper->softDeleteEntry(TreeMapper::TYPE_FOLDER, $folder->getId());
		}
	}

	/**
	 * @param $shareId
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws Exception
	 */
	public function deleteShare($shareId): void {
		// Participants that still have access to the folder through another share keep their shared folder
		$share = $this->shareMapper->find($shareId);
		$userIds = array_unique(array_map(static fn (SharedFolder $sharedFolder) => $sharedFolder->getUserId(), $this->sharedFolderMapper->findByShare($share->getId())));
		foreach ($userIds as $userId) {
			$this->removeSharedFolderOfUser($share, $userId);
		}
		$this->treeMapper->deleteShare($shareId);
	}

	/**
	 * Removes the shared folder that the user received through the given share. If the user still
	 * has access to the folder through another share, their shared folder is moved over to that share
	 * instead of being deleted, so that it stays where the user put it.
	 */
	public function removeSharedFolderOfUser(Share $share, string $userId): void {
		try {
			$sharedFolders = $this->sharedFolderMapper->findByShareAndUser($share->getId(), $userId);
		} catch (Exception $e) {
			return;
		}
		if (count($sharedFolders) === 0) {
			return;
		}
		try {
			$otherShare = $this->findOtherShareOfUser($share, $userId);
			if ($otherShare !== null && count($this->sharedFolderMapper->findByShareAndUser($otherShare->getId(), $userId)) > 0) {
				// the user already has the folder through the other share
				$otherShare = null;
			}
		} catch (Exception $e) {
			$otherShare = null;
		}
		foreach ($sharedFolders as $sharedFolder) {
			try {
				if ($otherShare !== null) {
					$this->sharedFolderMapper->remount($sharedFolder->getId(), $otherShare->getId());
					$otherShare = null;
					continue;
				}
				$this->treeMapper->deleteEntry(TreeMapper::TYPE_SHARE, $sharedFolder->getId());
				$this->sharedFolderMapper->delete($sharedFolder);
			} catch (UnsupportedOperation|DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			}
		}
	}

	/**
	 * Makes sure that the current members of a group or circle share have the shared folder and that
	 * former members don't. Deletes the share if its group or circle doesn't exist anymore.
	 *
	 * @return array{added: int, removed: int, deleted: bool}
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws Exception
	 */
	public function syncShareParticipants(Share $share): array {
		$result = ['added' => 0, 'removed' => 0, 'deleted' => false];
		$userIds = null;
		if ($share->getType() === IShare::TYPE_GROUP) {
			$group = $this->groupManager->get($share->getParticipant());
			if ($group !== null) {
				$userIds = array_map(static fn ($user) => $user->getUID(), $group->getUsers());
			}
		} elseif ($share->getType() === IShare::TYPE_CIRCLE) {
			$circleExists = $this->circlesService->circleExists($share->getParticipant());
			if ($circleExists === null) {
				// We can't tell right now, so better not touch anything
				return $result;
			}
			if ($circleExists) {
				$userIds = $this->circlesService->getUserIdsOfCircle($share->getParticipant());
				if (count($userIds) === 0) {
					// A circle always has an owner, so looking up its members probably failed
					return $result;
				}
			}
		} else {
			throw new UnsupportedOperation('Only group and circle shares can be synced');
		}

		if ($userIds === null) {
			$this->deleteShare($share->getId());
			$result['deleted'] = true;
			return $result;
		}

		$formerUserIds = [];
		foreach ($this->sharedFolderMapper->findByShare($share->getId()) as $sharedFolder) {
			if (!in_array($sharedFolder->getUserId(), $userIds, true)) {
				$formerUserIds[] = $sharedFolder->getUserId();
			}
		}
		foreach (array_unique($formerUserIds) as $userId) {
			$this->removeSharedFolderOfUser($share, $userId);
			$result['removed']++;
		}

		$folder = $this->folderMapper->find($share->getFolderId());
		foreach ($userIds as $userId) {
			if ($userId === $folder->getUserId()) {
				continue;
			}
			// This also covers users who have the folder through another share
			if ($this->hasSharedFolderOfFolder($folder->getId(), $userId)) {
				continue;
			}
			// If this folder already contains a share from this user, don't share it back. Would cause a loop.
			if ($this->treeMapper->containsSharedFolderFromUser($folder, $userId)) {
				continue;
			}
			$this->addSharedFolder($share, $folder, $userId);
			$result['added']++;
		}

		return $result;
	}

	/**
	 * Gives a user who just became a participant of a group or circle share the shared folder,
	 * unless they already have the folder through another share
	 *
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws Exception
	 */
	public function addParticipantToShare(Share $share, string $userId): void {
		if ($share->getOwner() === $userId) {
			return;
		}
		if ($this->hasSharedFolderOfFolder($share->getFolderId(), $userId)) {
			return;
		}
		$folder = $this->folderMapper->find($share->getFolderId());
		// If this folder already contains a share from this user, don't share it back. Would cause a loop.
		if ($this->treeMapper->containsSharedFolderFromUser($folder, $userId)) {
			return;
		}
		$this->addSharedFolder($share, $folder, $userId);
	}

	/**
	 * Whether the user has a shared folder of exactly this folder, through any share.
	 * Shared ancestor folders don't count, because they can be unshared independently.
	 *
	 * @throws Exception
	 */
	private function hasSharedFolderOfFolder(int $folderId, string $userId): bool {
		try {
			$this->sharedFolderMapper->findByFolderAndUser($folderId, $userId);
			return true;
		} catch (DoesNotExistException) {
			return false;
		} catch (MultipleObjectsReturnedException) {
			// Older versions could create several
			return true;
		}
	}

	/**
	 * Finds another share of the same folder that the user is a participant of
	 *
	 * @throws Exception
	 */
	private function findOtherShareOfUser(Share $share, string $userId): ?Share {
		foreach ($this->shareMapper->findByFolder($share->getFolderId()) as $otherShare) {
			if ($otherShare->getId() !== $share->getId() && $this->authorizer->isUserParticipantOfShare($otherShare, $userId)) {
				return $otherShare;
			}
		}
		return null;
	}

	/**
	 * @throws UnsupportedOperation
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException|Exception
	 */
	public function undelete(?string $userId, int $folderId): void {
		$folder = $this->folderMapper->find($folderId);
		if ($userId === null || $userId === $folder->getUserId()) {
			$this->treeMapper->softUndeleteEntry(TreeMapper::TYPE_FOLDER, $folderId);
			return;
		}

		try {
			// folder is shared folder
			$sharedFolder = $this->sharedFolderMapper->findByFolderAndUser($folder->getId(), $userId);
			$this->treeMapper->softUndeleteEntry(TreeMapper::TYPE_SHARE, $sharedFolder->getId());
			return;
		} catch (DoesNotExistException $e) {
			// noop
		}

		// folder is subfolder of share
		$this->treeMapper->softUndeleteEntry(TreeMapper::TYPE_FOLDER, $folder->getId());
	}

	/**
	 * @param string|null $userId
	 * @param int $folderId
	 * @param string $title
	 * @param int $parent_folder
	 * @return Folder|SharedFolder
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws \OCA\Bookmarks\Exception\UrlParseError
	 * @throws Exception
	 */
	public function updateSharedFolderOrFolder(?string $userId, int $folderId, ?string $title = null, ?int $parent_folder = null) {
		$folder = $this->folderMapper->find($folderId);

		if ($userId !== null && $userId !== $folder->getUserId()) {
			try {
				// folder is shared folder
				$sharedFolder = $this->sharedFolderMapper->findByFolderAndUser($folder->getId(), $userId);
				if (isset($title)) {
					$sharedFolder->setTitle($title);
					$this->sharedFolderMapper->update($sharedFolder);
				}
				if (isset($parent_folder)) {
					$this->treeMapper->move(TreeMapper::TYPE_SHARE, $sharedFolder->getId(), $parent_folder);
				}
				return $sharedFolder;
			} catch (DoesNotExistException $e) {
				// noop
			}
		}
		if (isset($title)) {
			$oldTitle = $folder->getTitle();
			$folder->setTitle($title);
			$this->folderMapper->update($folder);
			if ($oldTitle !== $title) {
				// Propagate the rename to sharees who haven't renamed their copy themselves.
				// A SharedFolder still carrying the old folder title hasn't been customized.
				foreach ($this->sharedFolderMapper->findByFolder($folder->getId()) as $sharedFolder) {
					if ($sharedFolder->getTitle() === $oldTitle) {
						$sharedFolder->setTitle($title);
						$this->sharedFolderMapper->update($sharedFolder);
					}
				}
			}
			$this->eventDispatcher->dispatchTyped(new UpdateEvent(TreeMapper::TYPE_FOLDER, $folder->getId()));
		}
		if (isset($parent_folder)) {
			$parentFolder = $this->folderMapper->find($parent_folder);
			if ($parentFolder->getUserId() !== $folder->getUserId()) {
				if ($this->treeMapper->containsFoldersSharedToUser($folder, $parentFolder->getUserId())) {
					throw new UnsupportedOperation('Cannot move a folder by user A into a folder shared from user B if it already contains folders shared with B.');
				}
				$this->treeMapper->changeFolderOwner($folder, $parentFolder->getUserId());
			}
			$this->treeMapper->move(TreeMapper::TYPE_FOLDER, $folder->getId(), $parent_folder);
		}

		return $folder;
	}

	/**
	 * @param $folderId
	 * @return string
	 * @throws DoesNotExistException|MultipleObjectsReturnedException|UnsupportedOperation
	 */
	public function createFolderPublicToken($folderId): string {
		if (!$this->settings->getLinkSharingAllowed()) {
			throw new UnsupportedOperation('Link sharing is not enabled');
		}
		$this->folderMapper->find($folderId);
		try {
			$publicFolder = $this->publicFolderMapper->findByFolder($folderId);
		} catch (DoesNotExistException $e) {
			$publicFolder = new PublicFolder();
			$publicFolder->setFolderId($folderId);
			$publicFolder->setDescription('');
			$this->publicFolderMapper->insert($publicFolder);
		}
		return $publicFolder->getId();
	}

	/**
	 * @param $folderId
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function deleteFolderPublicToken($folderId): void {
		$publicFolder = $this->publicFolderMapper->findByFolder($folderId);
		$this->publicFolderMapper->delete($publicFolder);
	}

	/**
	 * @param $folderId
	 * @param $participant
	 * @param int $type
	 * @param bool $canWrite
	 * @param bool $canShare
	 * @return Share
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws Exception
	 */
	public function createShare($folderId, $participant, int $type, bool $canWrite = false, bool $canShare = false): Share {
		$folder = $this->folderMapper->find($folderId);

		try {
			$this->shareMapper->findByFolderAndParticipant($folderId, $type, $participant);
			throw new UnsupportedOperation('Cannot create the same share twice');
		} catch (DoesNotExistException) {
			// pass
		}

		$share = new Share();
		$share->setFolderId($folderId);
		$share->setOwner($folder->getUserId());
		$share->setParticipant($participant);
		$share->setType($type);
		$share->setCanWrite($canWrite);
		$share->setCanShare($canShare);

		if ($type === IShare::TYPE_USER) {
			if ($participant === $folder->getUserId()) {
				throw new UnsupportedOperation('Cannot share with oneself');
			}
			// If this folder already contains a share from this user, don't share it back. Would cause a loop.
			if ($this->treeMapper->containsSharedFolderFromUser($folder, $participant)) {
				throw new UnsupportedOperation('Cannot share this with user that shared some of its contents');
			}
			// If the user already has this folder, e.g. through a group, don't add it twice.
			$hasSharedFolder = $this->hasSharedFolderOfFolder($folder->getId(), $participant);
			$this->shareMapper->insert($share);
			if (!$hasSharedFolder) {
				$this->addSharedFolder($share, $folder, $participant);
			}
		} else {
			$this->addSharedFolderForParticipant($share, $folder, $type, $participant);
		}

		return $share;
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function addSharedFolderForParticipant(Share $share, Folder $folder, int $type, string $participant, bool $insertShare = true): void {
		if ($type === IShare::TYPE_CIRCLE) {
			$circle = $this->circlesService->getCircle($participant);
			if ($circle === null) {
				throw new DoesNotExistException('Circle does not exist');
			}
			if ($insertShare) {
				$this->shareMapper->insert($share);
			}

			foreach ($this->circlesService->getUserIdsOfCircle($participant) as $userId) {
				$this->addSharedFolderForParticipant($share, $folder, IShare::TYPE_USER, $userId, false);
			}
		}
		if ($type === IShare::TYPE_GROUP) {
			$group = $this->groupManager->get($participant);
			if ($group === null) {
				return;
			}
			if ($insertShare) {
				$this->shareMapper->insert($share);
			}

			$users = $group->getUsers();
			foreach ($users as $user) {
				// If owner is part of the group, don't add it twice
				if ($user->getUID() === $folder->getUserId()) {
					continue;
				}
				// If this folder is already shared with the user, don't add it twice.
				if ($this->hasSharedFolderOfFolder($folder->getId(), $user->getUID())) {
					continue;
				}

				// If this folder already contains a share from this user, don't share it back. Would cause a loop.
				if ($this->treeMapper->containsSharedFolderFromUser($folder, $user->getUID())) {
					continue;
				}

				$this->addSharedFolder($share, $folder, $user->getUID());
			}
		}
		if ($type === IShare::TYPE_USER) {
			// User is already owner of folder
			if ($participant === $folder->getUserId()) {
				return;
			}
			// If this folder is already shared with the user, don't add it twice.
			if ($this->hasSharedFolderOfFolder($folder->getId(), $participant)) {
				return;
			}

			// If this folder already contains a share from this user, don't share it back. Would cause a loop.
			if ($this->treeMapper->containsSharedFolderFromUser($folder, $participant)) {
				return;
			}

			if ($insertShare) {
				$this->shareMapper->insert($share);
			}

			$this->addSharedFolder($share, $folder, $participant);
		}
	}

	/**
	 * @param Share $share
	 * @param Folder $folder
	 * @param string $userId
	 * @throws MultipleObjectsReturnedException
	 * @throws UnsupportedOperation
	 */
	public function addSharedFolder(Share $share, Folder $folder, string $userId): void {
		$sharedFolder = new SharedFolder();
		$sharedFolder->setTitle($folder->getTitle());
		$sharedFolder->setFolderId($folder->getId());
		$sharedFolder->setUserId($userId);
		$rootFolder = $this->folderMapper->findRootFolder($userId);
		$sharedFolder = $this->sharedFolderMapper->insert($sharedFolder);
		$this->sharedFolderMapper->mount($sharedFolder->getId(), $share->getId());
		$this->treeMapper->move(TreeMapper::TYPE_SHARE, $sharedFolder->getId(), $rootFolder->getId());
	}

	/**
	 * @param string $userId
	 * @param $file
	 * @param int $folder
	 * @return array
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws HtmlParseError
	 * @throws MultipleObjectsReturnedException
	 * @throws UnauthorizedAccessError
	 * @throws UnsupportedOperation
	 * @throws UserLimitExceededError
	 */
	public function importFile(string $userId, $file, $folder): array {
		$importFolderId = $folder;
		return $this->htmlImporter->importFile($userId, $file, $importFolderId);
	}
}
