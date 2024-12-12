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
use OCA\Circles\Events\CircleMemberGenericEvent;
use OCA\Circles\Events\CircleMemberRemovedEvent;
use OCA\Circles\Model\Federated\FederatedEvent;
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

/** @template-implements IEventListener<Event|CircleDestroyedEvent|CircleMemberGenericEvent|BeforeUserDeletedEvent|UserAddedEvent|UserRemovedEvent|BeforeGroupDeletedEvent> */
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
				} catch (UnsupportedOperation|DoesNotExistException|MultipleObjectsReturnedException $e) {
				}
			}
		}
		if ($event instanceof CircleMemberGenericEvent) {
			if ($event instanceof CircleMemberAddedEvent) {
				$shares = $this->shareMapper->findByParticipant(IShare::TYPE_CIRCLE, $event->getCircle()->getSingleId());
				foreach ($shares as $share) {
					$this->addParticipantToShare($share, $event->getMember()->getUserType(), $event->getMember()->getUserId());
				}
				// propagate upward
				foreach ($event->getCircle()->getMemberships() as $membership) {
					$circle = $this->circlesService->getCircle($membership->getSingleId());
					$federatedEvent = new FederatedEvent();
					$federatedEvent->setCircle($circle);
					$federatedEvent->setMember($event->getMember());
					$this->handle(new CircleMemberAddedEvent($federatedEvent));
				}
			}
			if ($event instanceof CircleMemberRemovedEvent) {
				$shares = $this->shareMapper->findByParticipant(IShare::TYPE_CIRCLE, $event->getCircle()->getSingleId());
				foreach ($shares as $share) {
					$this->removeParticipantFromShare($share, $event->getMember()->getUserType(), $event->getMember()->getUserId());
				}
				// propagate upward
				foreach ($event->getCircle()->getMemberships() as $membership) {
					$circle = $this->circlesService->getCircle($membership->getSingleId());
					$federatedEvent = new FederatedEvent();
					$federatedEvent->setCircle($circle);
					$federatedEvent->setMember($event->getMember());
					$this->handle(new CircleMemberRemovedEvent($federatedEvent));
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
					$this->shareMapper->delete($share);
				} catch (Exception $e) {
					// noop
				}
			}
		}
		if ($event instanceof UserAddedEvent) {
			$shares = $this->shareMapper->findByParticipant(IShare::TYPE_GROUP, $event->getGroup()->getGID());
			foreach ($shares as $share) {
				$this->addParticipantToShare($share, IShare::TYPE_USER, $event->getUser()->getUID());
			}
		}
		if ($event instanceof UserRemovedEvent) {
			$shares = $this->shareMapper->findByParticipant(IShare::TYPE_GROUP, $event->getGroup()->getGID());
			foreach ($shares as $share) {
				$this->removeParticipantFromShare($share, IShare::TYPE_USER, $event->getUser()->getUID());
			}
		}
		if ($event instanceof BeforeGroupDeletedEvent) {
			$sharesToDelete = $this->shareMapper->findByParticipant(IShare::TYPE_GROUP, $event->getGroup()->getGID());
			foreach ($sharesToDelete as $share) {
				$this->shareMapper->delete($share);
			}
		}
	}

	private function removeParticipantFromShare(Share $share, int $type, string $participant): void {
		if ($type === IShare::TYPE_CIRCLE) {
			$circle = $this->circlesService->getCircle($participant);
			if ($circle === null) {
				return;
			}
			foreach ($circle->getMembers() as $member) {
				$this->removeParticipantFromShare($share, $member->getUserType(), $member->getUserId());
			}
		} elseif ($type === IShare::TYPE_GROUP) {
			$group = $this->groupManager->get($participant);
			if ($group === null) {
				return;
			}
			foreach ($group->getUsers() as $user) {
				$this->removeParticipantFromShare($share, IShare::TYPE_USER, $user->getUID());
			}
		} elseif ($type === IShare::TYPE_USER) {
			try {
				$sharedFoldersToDelete = $this->sharedFolderMapper->findByShareAndUser($share->getId(), $participant);
			} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
				return;
			}
			foreach ($sharedFoldersToDelete as $sharedFolder) {
				try {
					$this->treeMapper->deleteEntry(TreeMapper::TYPE_SHARE, $sharedFolder->getId());
				} catch (UnsupportedOperation|DoesNotExistException|MultipleObjectsReturnedException $e) {
				}
			}
		}
	}

	private function addParticipantToShare(Share $share, int $type, string $participant): void {
		if ($type === IShare::TYPE_CIRCLE) {
			$circle = $this->circlesService->getCircle($participant);
			if ($circle === null) {
				return;
			}
			foreach ($circle->getMembers() as $member) {
				$this->addParticipantToShare($share, $member->getUserType(), $member->getUserId());
			}
		} elseif ($type === IShare::TYPE_GROUP) {
			$group = $this->groupManager->get($participant);
			if ($group === null) {
				return;
			}
			foreach ($group->getUsers() as $user) {
				$this->addParticipantToShare($share, IShare::TYPE_USER, $user->getUID());
			}
		} elseif ($type === IShare::TYPE_USER) {
			if ($share->getOwner() === $participant) {
				return;
			}
			try {
				$this->sharedFolderMapper->findByShareAndUser($share->getId(), $participant);
				// if this does not throw, the user already has this folder
				return;
			} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
				// noop
			}
			try {
				$folder = $this->folderService->findById($share->getFolderId());
				$this->folderService->addSharedFolder($share, $folder, $participant);
			} catch (DoesNotExistException|MultipleObjectsReturnedException|UnsupportedOperation $e) {
			}
		}
	}
}
