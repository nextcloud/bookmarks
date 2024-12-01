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
		$this->treeMapper->deleteShare($shareId);
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

		if ($userId !== null || $userId !== $folder->getUserId()) {
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
			$folder->setTitle($title);
			$this->folderMapper->update($folder);
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
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function createFolderPublicToken($folderId): string {
		$this->folderMapper->find($folderId);
		try {
			$publicFolder = $this->publicFolderMapper->findByFolder($folderId);
		} catch (DoesNotExistException $e) {
			$publicFolder = new PublicFolder();
			$publicFolder->setFolderId($folderId);
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
			$this->shareMapper->insert($share);
			$this->addSharedFolder($share, $folder, $participant);
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
	public function addSharedFolderForParticipant(Share $share, Folder $folder, int $type, string $participant): void {
		if ($type === IShare::TYPE_CIRCLE) {
			$circle = $this->circlesService->getCircle($participant);
			if ($circle === null) {
				throw new DoesNotExistException('Circle does not exist');
			}
			$this->shareMapper->insert($share);

			$members = $circle->getMembers();
			foreach ($members as $member) {
				$this->addSharedFolderForParticipant($share, $folder, $member->getUserType(), $member->getUserId());
			}
		}
		if ($type === IShare::TYPE_GROUP) {
			$group = $this->groupManager->get($participant);
			if ($group === null) {
				return;
			}
			$this->shareMapper->insert($share);

			$users = $group->getUsers();
			foreach ($users as $user) {
				// If owner is part of the group, don't add it twice
				if ($user->getUID() === $folder->getUserId()) {
					continue;
				}
				// If this folder is already shared with the user, don't add it twice.
				if ($this->treeMapper->isFolderSharedWithUser($folder->getId(), $user->getUID())) {
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
			if ($this->treeMapper->isFolderSharedWithUser($folder->getId(), $participant)) {
				return;
			}

			// If this folder already contains a share from this user, don't share it back. Would cause a loop.
			if ($this->treeMapper->containsSharedFolderFromUser($folder, $participant)) {
				return;
			}

			$this->shareMapper->insert($share);

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
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws AlreadyExistsError
	 * @throws HtmlParseError
	 * @throws UnauthorizedAccessError
	 * @throws UserLimitExceededError
	 */
	public function importFile(string $userId, $file, $folder): array {
		$importFolderId = $folder;
		return $this->htmlImporter->importFile($userId, $file, $importFolderId);
	}
}
