<?php

/*
 * Copyright (c) 2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

declare(strict_types=1);

namespace OCA\Bookmarks\Hooks;

use OCA\Bookmarks\Db\Share;
use OCA\Bookmarks\Db\SharedFolderMapper;
use OCA\Bookmarks\Db\ShareMapper;
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Service\BookmarkService;
use OCA\Bookmarks\Service\CirclesService;
use OCA\Bookmarks\Service\FolderService;
use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Events\CircleMemberAddedEvent;
use OCA\Circles\Events\CircleMemberRemovedEvent;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IGroupManager;
use OCP\Share\IShare;
use OCP\User\Events\BeforeUserDeletedEvent;

/** @template-implements IEventListener<Event|CircleDestroyedEvent|CircleMemberAddedEvent|CircleMemberRemovedEvent|BeforeUserDeletedEvent|UserAddedEvent|UserRemovedEvent|BeforeGroupDeletedEvent> */
class UsersGroupsCirclesListener implements IEventListener {
	public function __construct(
		private ShareMapper $shareMapper,
		private FolderService $folderService,
		private SharedFolderMapper $sharedFolderMapper,
		private TreeMapper $treeMapper,
		private CirclesService $circlesService,
		private IGroupManager $groupManager,
		private BookmarkService $bookmarksService,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof CircleDestroyedEvent) {
			$shares = $this->shareMapper->findByParticipant(IShare::TYPE_CIRCLE, $event->getCircle()->getSingleId());
			foreach ($shares as $share) {
				try {
					$this->folderService->deleteShare($share->getId());
				} catch (UnsupportedOperation|DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
				}
			}
		}
		if ($event instanceof CircleMemberAddedEvent || $event instanceof CircleMemberRemovedEvent) {
			if (!$event->hasMember()) {
				return;
			}
			$circleId = $event->getCircle()->getSingleId();
			$userIds = $this->getUserIdsOfCircleMember($event->getMember());
			// Shares with circles that contain this circle are affected, too
			$circleIds = array_merge([$circleId], $this->circlesService->getParentCircleIds($circleId));
			foreach ($circleIds as $affectedCircleId) {
				$shares = $this->shareMapper->findByParticipant(IShare::TYPE_CIRCLE, $affectedCircleId);
				if (count($shares) === 0) {
					continue;
				}
				if ($event instanceof CircleMemberAddedEvent) {
					$affectedUserIds = $userIds;
				} else {
					// Users may still be members of the circle by some other way
					$affectedUserIds = array_diff($userIds, $this->circlesService->getUserIdsOfCircle($affectedCircleId));
				}
				foreach ($shares as $share) {
					foreach ($affectedUserIds as $userId) {
						if ($event instanceof CircleMemberAddedEvent) {
							$this->addParticipantToShare($share, $userId);
						} else {
							$this->removeParticipantFromShare($share, $userId);
						}
					}
				}
			}
		}
		if ($event instanceof BeforeUserDeletedEvent) {
			try {
				$this->bookmarksService->deleteAll($event->getUser()->getUID());
			} catch (UnsupportedOperation|DoesNotExistException|MultipleObjectsReturnedException $e) {
				// noop
			}
			// delete dangling shares
			$sharesToDelete = $this->shareMapper->findByParticipant(IShare::TYPE_USER, $event->getUser()->getUID());
			foreach ($sharesToDelete as $share) {
				try {
					$this->folderService->deleteShare($share->getId());
				} catch (UnsupportedOperation|DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
					// noop
				}
			}
		}
		if ($event instanceof UserAddedEvent) {
			$shares = $this->shareMapper->findByParticipant(IShare::TYPE_GROUP, $event->getGroup()->getGID());
			foreach ($shares as $share) {
				$this->addParticipantToShare($share, $event->getUser()->getUID());
			}
		}
		if ($event instanceof UserRemovedEvent) {
			$shares = $this->shareMapper->findByParticipant(IShare::TYPE_GROUP, $event->getGroup()->getGID());
			foreach ($shares as $share) {
				$this->removeParticipantFromShare($share, $event->getUser()->getUID());
			}
		}
		if ($event instanceof BeforeGroupDeletedEvent) {
			$sharesToDelete = $this->shareMapper->findByParticipant(IShare::TYPE_GROUP, $event->getGroup()->getGID());
			foreach ($sharesToDelete as $share) {
				try {
					$this->folderService->deleteShare($share->getId());
				} catch (UnsupportedOperation|DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
					// noop
				}
			}
		}
	}

	/**
	 * @param \OCA\Circles\Model\Member $member
	 * @return string[] user ids
	 */
	private function getUserIdsOfCircleMember($member): array {
		switch ($member->getUserType()) {
			case CirclesService::TYPE:
				return [$member->getUserId()];
			case CirclesService::TYPE_GROUP:
				$group = $this->groupManager->get($member->getUserId());
				if ($group === null) {
					return [];
				}
				return array_map(static fn ($user) => $user->getUID(), $group->getUsers());
			case CirclesService::TYPE_CIRCLE:
				return $this->circlesService->getUserIdsOfCircle($member->getSingleId());
			default:
				return [];
		}
	}

	private function removeParticipantFromShare(Share $share, string $userId): void {
		try {
			$sharedFoldersToDelete = $this->sharedFolderMapper->findByShareAndUser($share->getId(), $userId);
		} catch (Exception $e) {
			return;
		}
		foreach ($sharedFoldersToDelete as $sharedFolder) {
			try {
				$this->treeMapper->deleteEntry(TreeMapper::TYPE_SHARE, $sharedFolder->getId());
				$this->sharedFolderMapper->delete($sharedFolder);
			} catch (UnsupportedOperation|DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			}
		}
	}

	private function addParticipantToShare(Share $share, string $userId): void {
		if ($share->getOwner() === $userId) {
			return;
		}
		try {
			if (count($this->sharedFolderMapper->findByShareAndUser($share->getId(), $userId)) > 0) {
				// the user already has this folder
				return;
			}
		} catch (Exception $e) {
			return;
		}
		try {
			$folder = $this->folderService->findById($share->getFolderId());
			$this->folderService->addSharedFolder($share, $folder, $userId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|UnsupportedOperation $e) {
		}
	}
}
