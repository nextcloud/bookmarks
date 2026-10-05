<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Service\FolderService;
use OCA\Circles\CirclesManager;
use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Events\CircleMemberAddedEvent;
use OCA\Circles\Events\CircleMemberRemovedEvent;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Federated\FederatedEvent;
use OCA\Circles\Model\Member;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IShare;

/**
 * Sharing folders with circles (teams).
 *
 * In a single-instance setup the circles app only fires its member and destroy
 * events from an async loopback request, never from the CLI. These tests
 * therefore manage circles through the real CirclesManager and then dispatch
 * the event circles would have sent through the real event dispatcher, so that
 * the listener registration is covered as well.
 */
class CirclesSharingTest extends TestCase {
	private IUserManager $userManager;
	private IGroupManager $groupManager;
	private IEventDispatcher $eventDispatcher;
	private Db\FolderMapper $folderMapper;
	private Db\TreeMapper $treeMapper;
	private Db\SharedFolderMapper $sharedFolderMapper;
	private Db\ShareMapper $shareMapper;
	private FolderService $folders;
	private CirclesManager $circlesManager;

	private string $ownerId;
	/** @var string[] single ids of circles to destroy after each test */
	private array $circles = [];

	protected function setUp(): void {
		parent::setUp();
		if (!\OCP\Server::get(IAppManager::class)->isEnabledForAnyone('circles')) {
			$this->markTestSkipped('The circles app is not enabled');
		}
		$this->cleanUp();

		$this->userManager = \OCP\Server::get(IUserManager::class);
		$this->groupManager = \OCP\Server::get(IGroupManager::class);
		$this->eventDispatcher = \OCP\Server::get(IEventDispatcher::class);
		$this->folderMapper = \OCP\Server::get(Db\FolderMapper::class);
		$this->treeMapper = \OCP\Server::get(Db\TreeMapper::class);
		$this->sharedFolderMapper = \OCP\Server::get(Db\SharedFolderMapper::class);
		$this->shareMapper = \OCP\Server::get(Db\ShareMapper::class);
		$this->folders = \OCP\Server::get(FolderService::class);
		$this->circlesManager = \OCP\Server::get(CirclesManager::class);

		$this->ownerId = $this->createUser('circle_share_owner');
	}

	protected function tearDown(): void {
		if (isset($this->circlesManager)) {
			foreach (array_reverse($this->circles) as $circleId) {
				try {
					$this->startCirclesSession();
					$this->circlesManager->destroyCircle($circleId);
				} catch (\Throwable $e) {
					// already destroyed
				}
			}
			$this->circlesManager->stopSession();
		}
		$this->circles = [];
		parent::tearDown();
	}

	private function createUser(string $uid): string {
		if (!$this->userManager->userExists($uid)) {
			$this->userManager->createUser($uid, 'password');
		}
		return $this->userManager->get($uid)->getUID();
	}

	private function startCirclesSession(): void {
		$this->circlesManager->startSession($this->circlesManager->getLocalFederatedUser($this->ownerId), true);
	}

	private function createCircle(string $name): Circle {
		$this->startCirclesSession();
		$circle = $this->circlesManager->createCircle($name . '_' . uniqid(), null, false, true, false);
		$this->circles[] = $circle->getSingleId();
		return $circle;
	}

	private function addUserToCircle(Circle $circle, string $userId): Member {
		$this->startCirclesSession();
		return $this->circlesManager->addMember($circle->getSingleId(), $this->circlesManager->getLocalFederatedUser($userId));
	}

	private function addGroupToCircle(Circle $circle, string $groupId): Member {
		$this->startCirclesSession();
		return $this->circlesManager->addMember($circle->getSingleId(), $this->circlesManager->getFederatedUser($groupId, Member::TYPE_GROUP));
	}

	private function addCircleToCircle(Circle $parent, Circle $child): Member {
		$this->startCirclesSession();
		return $this->circlesManager->addMember($parent->getSingleId(), $this->circlesManager->getFederatedUser($child->getSingleId(), Member::TYPE_CIRCLE));
	}

	private function reloadCircle(Circle $circle): Circle {
		$this->startCirclesSession();
		return $this->circlesManager->getCircle($circle->getSingleId());
	}

	private function federatedEvent(Circle $circle, ?Member $member = null): FederatedEvent {
		$event = new FederatedEvent();
		$event->setCircle($this->reloadCircle($circle));
		$event->setMember($member);
		return $event;
	}

	private function createFolder(): Db\Folder {
		$folder = new Db\Folder();
		$folder->setTitle('shared with circle');
		$folder->setUserId($this->ownerId);
		$this->folderMapper->insert($folder);
		$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $folder->getId(), $this->folderMapper->findRootFolder($this->ownerId)->getId());
		return $folder;
	}

	private function shareWithCircle(Circle $circle): Db\Share {
		return $this->folders->createShare($this->createFolder()->getId(), $circle->getSingleId(), IShare::TYPE_CIRCLE);
	}

	private function assertHasSharedFolder(Db\Share $share, string $userId): void {
		$this->assertCount(1, $this->sharedFolderMapper->findByShareAndUser($share->getId(), $userId), "$userId should have the shared folder");
	}

	private function assertHasNoSharedFolder(Db\Share $share, string $userId): void {
		$this->assertCount(0, $this->sharedFolderMapper->findByShareAndUser($share->getId(), $userId), "$userId should not have the shared folder");
	}

	public function testCircleShareGivesUserMembersSharedFolder(): void {
		$memberId = $this->createUser('circle_share_user_member');
		$circle = $this->createCircle('user_members');
		$this->addUserToCircle($circle, $memberId);

		$share = $this->shareWithCircle($circle);

		$this->assertHasSharedFolder($share, $memberId);
		$this->assertHasNoSharedFolder($share, $this->ownerId);
	}

	public function testCircleShareGivesGroupMembersSharedFolder(): void {
		$memberId = $this->createUser('circle_share_group_member');
		$group = $this->groupManager->createGroup('circle_share_group');
		$group->addUser($this->userManager->get($memberId));
		try {
			$circle = $this->createCircle('group_members');
			$this->addGroupToCircle($circle, $group->getGID());

			$share = $this->shareWithCircle($circle);

			$this->assertHasSharedFolder($share, $memberId);
		} finally {
			$group->delete();
		}
	}

	public function testCircleShareGivesNestedCircleMembersSharedFolder(): void {
		$memberId = $this->createUser('circle_share_nested_member');
		$inner = $this->createCircle('nested_inner');
		$this->addUserToCircle($inner, $memberId);
		$outer = $this->createCircle('nested_outer');
		$this->addCircleToCircle($outer, $inner);

		$share = $this->shareWithCircle($outer);

		$this->assertHasSharedFolder($share, $memberId);
	}

	public function testAddedCircleMemberGetsSharedFolder(): void {
		$memberId = $this->createUser('circle_share_added_member');
		$circle = $this->createCircle('added_member');
		$share = $this->shareWithCircle($circle);
		$this->assertHasNoSharedFolder($share, $memberId);

		$member = $this->addUserToCircle($circle, $memberId);
		$this->eventDispatcher->dispatchTyped(new CircleMemberAddedEvent($this->federatedEvent($circle, $member), []));

		$this->assertHasSharedFolder($share, $memberId);
	}

	public function testRemovedCircleMemberLosesSharedFolder(): void {
		$memberId = $this->createUser('circle_share_removed_member');
		$circle = $this->createCircle('removed_member');
		$member = $this->addUserToCircle($circle, $memberId);
		$share = $this->shareWithCircle($circle);
		$this->assertHasSharedFolder($share, $memberId);

		$this->startCirclesSession();
		$this->circlesManager->removeMember($member->getId());
		$this->eventDispatcher->dispatchTyped(new CircleMemberRemovedEvent($this->federatedEvent($circle, $member), []));

		$this->assertHasNoSharedFolder($share, $memberId);
	}

	public function testMemberAddedToNestedCircleGetsSharedFolder(): void {
		$memberId = $this->createUser('circle_share_nested_added_member');
		$inner = $this->createCircle('nested_added_inner');
		$outer = $this->createCircle('nested_added_outer');
		$this->addCircleToCircle($outer, $inner);
		$share = $this->shareWithCircle($outer);
		$this->assertHasNoSharedFolder($share, $memberId);

		// circles fires the event for the circle the user was added to, not for its parents
		$member = $this->addUserToCircle($inner, $memberId);
		$this->eventDispatcher->dispatchTyped(new CircleMemberAddedEvent($this->federatedEvent($inner, $member), []));

		$this->assertHasSharedFolder($share, $memberId);
	}

	public function testMemberRemovedFromNestedCircleLosesSharedFolder(): void {
		$memberId = $this->createUser('circle_share_nested_removed_member');
		$inner = $this->createCircle('nested_removed_inner');
		$member = $this->addUserToCircle($inner, $memberId);
		$outer = $this->createCircle('nested_removed_outer');
		$this->addCircleToCircle($outer, $inner);
		$share = $this->shareWithCircle($outer);
		$this->assertHasSharedFolder($share, $memberId);

		$this->startCirclesSession();
		$this->circlesManager->removeMember($member->getId());
		$this->eventDispatcher->dispatchTyped(new CircleMemberRemovedEvent($this->federatedEvent($inner, $member), []));

		$this->assertHasNoSharedFolder($share, $memberId);
	}

	public function testDestroyedCircleRemovesShareAndSharedFolders(): void {
		$memberId = $this->createUser('circle_share_destroyed_member');
		$circle = $this->createCircle('destroyed');
		$this->addUserToCircle($circle, $memberId);
		$share = $this->shareWithCircle($circle);
		$this->assertHasSharedFolder($share, $memberId);

		$event = $this->federatedEvent($circle);
		$this->startCirclesSession();
		$this->circlesManager->destroyCircle($circle->getSingleId());
		$this->eventDispatcher->dispatchTyped(new CircleDestroyedEvent($event, []));

		try {
			$this->shareMapper->find($share->getId());
			$this->fail('Share should have been deleted together with the circle');
		} catch (DoesNotExistException $e) {
			// expected
		}
		$this->assertCount(0, $this->sharedFolderMapper->findByShare($share->getId()));
	}
}
