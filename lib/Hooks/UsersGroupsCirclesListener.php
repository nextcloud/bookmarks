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
use OCA\Circles\Events\DestroyingCircleEvent;
use OCA\Circles\Events\MembershipsCreatedEvent;
use OCA\Circles\Events\MembershipsRemovedEvent;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\Share\IShare;
use OCP\User\Events\BeforeUserDeletedEvent;

/** @template-implements IEventListener<Event|DestroyingCircleEvent|MembershipsCreatedEvent|MembershipsRemovedEvent|BeforeUserDeletedEvent|UserAddedEvent|UserRemovedEvent|BeforeGroupDeletedEvent> */
class UsersGroupsCirclesListener implements IEventListener {
	public function __construct(
		private ShareMapper $shareMapper,
		private FolderService $folderService,
		private SharedFolderMapper $sharedFolderMapper,
		private TreeMapper $treeMapper,
		private CirclesService $circlesService,
		private BookmarkService $bookmarksService,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof DestroyingCircleEvent) {
			$shares = $this->shareMapper->findByParticipant(IShare::TYPE_CIRCLE, $event->getCircle()->getSingleId());
			foreach ($shares as $share) {
				try {
					$this->folderService->deleteShare($share->getId());
				} catch (UnsupportedOperation|DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
				}
			}
		}
		if ($event instanceof MembershipsCreatedEvent || $event instanceof MembershipsRemovedEvent) {
			// Memberships are flattened: they include memberships through groups and nested circles,
			// and a membership is only removed once the user isn't part of the circle in any way anymore
			foreach ($event->getMemberships() as $membership) {
				$shares = $this->shareMapper->findByParticipant(IShare::TYPE_CIRCLE, $membership->getCircleId());
				if (count($shares) === 0) {
					continue;
				}
				$userId = $this->circlesService->getLocalUserIdOfSingleId($membership->getSingleId());
				if ($userId === null) {
					continue;
				}
				foreach ($shares as $share) {
					if ($event instanceof MembershipsCreatedEvent) {
						$this->addParticipantToShare($share, $userId);
					} else {
						$this->folderService->removeSharedFolderOfUser($share, $userId);
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
			// delete the shared folders the user received through groups and circles
			try {
				$sharedFoldersToDelete = $this->sharedFolderMapper->findByUser($event->getUser()->getUID());
			} catch (Exception $e) {
				$sharedFoldersToDelete = [];
			}
			foreach ($sharedFoldersToDelete as $sharedFolder) {
				try {
					$this->treeMapper->deleteEntry(TreeMapper::TYPE_SHARE, $sharedFolder->getId());
					$this->sharedFolderMapper->delete($sharedFolder);
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
				$this->folderService->removeSharedFolderOfUser($share, $event->getUser()->getUID());
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

	private function addParticipantToShare(Share $share, string $userId): void {
		try {
			$this->folderService->addParticipantToShare($share, $userId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|UnsupportedOperation|Exception $e) {
		}
	}
}
