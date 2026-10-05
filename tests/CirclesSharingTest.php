<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Migration\CircleSharesUpdateRepairStep;
use OCA\Bookmarks\Service\FolderService;
use OCA\Circles\CirclesManager;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Share\IShare;

/**
 * Sharing folders with circles (teams).
 *
 * Circles are managed through the real CirclesManager with a forced synchronous
 * session, so the memberships events that the listener relies on are fired
 * from within the test process.
 */
class CirclesSharingTest extends TestCase {
	private IUserManager $userManager;
	private IGroupManager $groupManager;
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

		$this->addUserToCircle($circle, $memberId);

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

		$this->assertHasNoSharedFolder($share, $memberId);
	}

	public function testMemberAddedToNestedCircleGetsSharedFolder(): void {
		$memberId = $this->createUser('circle_share_nested_added_member');
		$inner = $this->createCircle('nested_added_inner');
		$outer = $this->createCircle('nested_added_outer');
		$this->addCircleToCircle($outer, $inner);
		$share = $this->shareWithCircle($outer);
		$this->assertHasNoSharedFolder($share, $memberId);

		$this->addUserToCircle($inner, $memberId);

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

		$this->assertHasNoSharedFolder($share, $memberId);
	}

	public function testDestroyedCircleRemovesShareAndSharedFolders(): void {
		$memberId = $this->createUser('circle_share_destroyed_member');
		$circle = $this->createCircle('destroyed');
		$this->addUserToCircle($circle, $memberId);
		$share = $this->shareWithCircle($circle);
		$this->assertHasSharedFolder($share, $memberId);

		$this->startCirclesSession();
		$this->circlesManager->destroyCircle($circle->getSingleId());

		try {
			$this->shareMapper->find($share->getId());
			$this->fail('Share should have been deleted together with the circle');
		} catch (DoesNotExistException $e) {
			// expected
		}
		$this->assertCount(0, $this->sharedFolderMapper->findByShare($share->getId()));
	}

	public function testUserAddedToGroupInCircleGetsSharedFolder(): void {
		$memberId = $this->createUser('circle_share_group_added_member');
		$group = $this->groupManager->createGroup('circle_share_group_added');
		try {
			$circle = $this->createCircle('group_added');
			$this->addGroupToCircle($circle, $group->getGID());
			$share = $this->shareWithCircle($circle);
			$this->assertHasNoSharedFolder($share, $memberId);

			$group->addUser($this->userManager->get($memberId));

			$this->assertHasSharedFolder($share, $memberId);
		} finally {
			$group->delete();
		}
	}

	public function testUserRemovedFromGroupInCircleLosesSharedFolder(): void {
		$memberId = $this->createUser('circle_share_group_removed_member');
		$group = $this->groupManager->createGroup('circle_share_group_removed');
		$group->addUser($this->userManager->get($memberId));
		try {
			$circle = $this->createCircle('group_removed');
			$this->addGroupToCircle($circle, $group->getGID());
			$share = $this->shareWithCircle($circle);
			$this->assertHasSharedFolder($share, $memberId);

			$group->removeUser($this->userManager->get($memberId));

			$this->assertHasNoSharedFolder($share, $memberId);
		} finally {
			$group->delete();
		}
	}

	public function testUserRemovedFromGroupInCircleKeepsSharedFolderAsDirectMember(): void {
		$memberId = $this->createUser('circle_share_group_and_direct_member');
		$group = $this->groupManager->createGroup('circle_share_group_and_direct');
		$group->addUser($this->userManager->get($memberId));
		try {
			$circle = $this->createCircle('group_and_direct');
			$this->addGroupToCircle($circle, $group->getGID());
			$this->addUserToCircle($circle, $memberId);
			$share = $this->shareWithCircle($circle);
			$this->assertHasSharedFolder($share, $memberId);

			$group->removeUser($this->userManager->get($memberId));

			$this->assertHasSharedFolder($share, $memberId);
		} finally {
			$group->delete();
		}
	}

	/**
	 * A user who leaves a group but is still covered by a circle share of the same
	 * folder must keep their shared folder, moved over to the circle share, and
	 * vice versa.
	 */
	public function testUserKeepsFolderSharedWithGroupAndCircleUntilRemovedFromBoth(): void {
		$memberId = $this->createUser('circle_share_group_or_circle_member');
		$group = $this->groupManager->createGroup('circle_share_group_or_circle');
		$group->addUser($this->userManager->get($memberId));
		try {
			$circle = $this->createCircle('group_or_circle');
			$member = $this->addUserToCircle($circle, $memberId);
			$folder = $this->createFolder();
			$groupShare = $this->folders->createShare($folder->getId(), $group->getGID(), IShare::TYPE_GROUP);
			$circleShare = $this->folders->createShare($folder->getId(), $circle->getSingleId(), IShare::TYPE_CIRCLE);
			$this->assertHasSharedFolder($groupShare, $memberId);
			$this->assertHasNoSharedFolder($circleShare, $memberId);
			$sharedFolderId = $this->sharedFolderMapper->findByShareAndUser($groupShare->getId(), $memberId)[0]->getId();

			$group->removeUser($this->userManager->get($memberId));

			$this->assertHasNoSharedFolder($groupShare, $memberId);
			$this->assertHasSharedFolder($circleShare, $memberId);
			$this->assertEquals($sharedFolderId, $this->sharedFolderMapper->findByShareAndUser($circleShare->getId(), $memberId)[0]->getId());

			$this->startCirclesSession();
			$this->circlesManager->removeMember($member->getId());

			$this->assertHasNoSharedFolder($circleShare, $memberId);
		} finally {
			$group->delete();
		}
	}

	public function testDeletedUserLosesFolderSharedWithCircle(): void {
		$memberId = $this->createUser('circle_share_deleted_member');
		$remainingId = $this->createUser('circle_share_remaining_member');
		$circle = $this->createCircle('deleted_member');
		$this->addUserToCircle($circle, $memberId);
		$this->addUserToCircle($circle, $remainingId);
		$share = $this->shareWithCircle($circle);
		$this->assertHasSharedFolder($share, $memberId);

		$this->userManager->get($memberId)->delete();

		$this->assertHasNoSharedFolder($share, $memberId);
		$this->assertCount(0, $this->sharedFolderMapper->findByUser($memberId));
		// The share and the other members' shared folders are untouched
		$this->shareMapper->find($share->getId());
		$this->assertHasSharedFolder($share, $remainingId);
	}

	/**
	 * Circle shares created by earlier versions gave none of the circle's members
	 * the shared folder. The repair step brings them in line with the circle.
	 */
	public function testCircleSharesUpdateRepairStepSyncsMembers(): void {
		$missingId = $this->createUser('circle_share_repair_missing_member');
		$formerId = $this->createUser('circle_share_repair_former_member');
		$circle = $this->createCircle('repair');
		$this->addUserToCircle($circle, $missingId);
		$share = $this->shareWithCircle($circle);
		$folder = $this->folders->findById($share->getFolderId());
		// what earlier versions left behind: a member without the folder, and a non-member with it
		$sharedFolder = $this->sharedFolderMapper->findByShareAndUser($share->getId(), $missingId)[0];
		$this->treeMapper->deleteEntry(Db\TreeMapper::TYPE_SHARE, $sharedFolder->getId());
		$this->sharedFolderMapper->delete($sharedFolder);
		$this->folders->addSharedFolder($share, $folder, $formerId);
		$this->assertHasNoSharedFolder($share, $missingId);
		$this->assertHasSharedFolder($share, $formerId);

		\OCP\Server::get(CircleSharesUpdateRepairStep::class)->run($this->createMock(IOutput::class));

		$this->assertHasSharedFolder($share, $missingId);
		$this->assertHasNoSharedFolder($share, $formerId);
		$this->assertHasNoSharedFolder($share, $this->ownerId);
	}

	public function testCircleSharesUpdateRepairStepDeletesSharesOfMissingCircles(): void {
		$memberId = $this->createUser('circle_share_repair_missing_circle_member');
		$folder = $this->createFolder();
		// A share of a circle that was destroyed while earlier versions didn't notice
		$share = new Db\Share();
		$share->setFolderId($folder->getId());
		$share->setOwner($this->ownerId);
		$share->setParticipant('nonexistentcircle' . uniqid());
		$share->setType(IShare::TYPE_CIRCLE);
		$share->setCanWrite(false);
		$share->setCanShare(false);
		$this->shareMapper->insert($share);
		$this->folders->addSharedFolder($share, $folder, $memberId);
		$this->assertHasSharedFolder($share, $memberId);

		\OCP\Server::get(CircleSharesUpdateRepairStep::class)->run($this->createMock(IOutput::class));

		try {
			$this->shareMapper->find($share->getId());
			$this->fail('Share of a missing circle should have been deleted');
		} catch (DoesNotExistException $e) {
			// expected
		}
		$this->assertCount(0, $this->sharedFolderMapper->findByUser($memberId));
	}
}
