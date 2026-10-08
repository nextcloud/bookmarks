<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Service\FolderService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IShare;

class UsersGroupsCirclesListenerTest extends TestCase {
	private IUserManager $userManager;
	private IGroupManager $groupManager;
	private Db\FolderMapper $folderMapper;
	private Db\TreeMapper $treeMapper;
	private Db\SharedFolderMapper $sharedFolderMapper;
	private Db\ShareMapper $shareMapper;
	private FolderService $folders;

	protected function setUp(): void {
		parent::setUp();
		$this->cleanUp();

		$this->userManager = \OCP\Server::get(IUserManager::class);
		$this->groupManager = \OCP\Server::get(IGroupManager::class);
		$this->folderMapper = \OCP\Server::get(Db\FolderMapper::class);
		$this->treeMapper = \OCP\Server::get(Db\TreeMapper::class);
		$this->sharedFolderMapper = \OCP\Server::get(Db\SharedFolderMapper::class);
		$this->shareMapper = \OCP\Server::get(Db\ShareMapper::class);
		$this->folders = \OCP\Server::get(FolderService::class);
	}

	private function createUser(string $uid): string {
		if (!$this->userManager->userExists($uid)) {
			$this->userManager->createUser($uid, 'password');
		}
		return $this->userManager->get($uid)->getUID();
	}

	private function shareTreeRowExists(int $sharedFolderId): bool {
		$qb = \OCP\Server::get(IDBConnection::class)->getQueryBuilder();
		$qb->select('id')
			->from('bookmarks_tree')
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($sharedFolderId)))
			->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter(Db\TreeMapper::TYPE_SHARE)));
		$result = $qb->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();
		return $exists;
	}

	/**
	 * Creates an owner and two members of a fresh group, and shares one of the
	 * owner's folders with that group.
	 *
	 * @return array{0: IGroup, 1: Db\Share, 2: string, 3: string} [group, share, firstMemberId, secondMemberId]
	 */
	private function createGroupShare(string $suffix): array {
		$ownerId = $this->createUser('group_share_owner_' . $suffix);
		$firstMemberId = $this->createUser('group_share_member1_' . $suffix);
		$secondMemberId = $this->createUser('group_share_member2_' . $suffix);

		$group = $this->groupManager->createGroup('group_share_' . $suffix);
		$group->addUser($this->userManager->get($firstMemberId));
		$group->addUser($this->userManager->get($secondMemberId));

		$folder = new Db\Folder();
		$folder->setTitle('shared with group');
		$folder->setUserId($ownerId);
		$this->folderMapper->insert($folder);
		$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $folder->getId(), $this->folderMapper->findRootFolder($ownerId)->getId());

		$share = $this->folders->createShare($folder->getId(), $group->getGID(), IShare::TYPE_GROUP);

		return [$group, $share, $firstMemberId, $secondMemberId];
	}

	/**
	 * Deleting a user who received a folder through a group share must remove
	 * their SharedFolder and its tree row, while leaving the share and the other
	 * group members' SharedFolders intact.
	 */
	public function testDeletedGroupMemberLosesSharedFolder(): void {
		[$group, $share, $deletedId, $remainingId] = $this->createGroupShare('userdel');

		$deletedSharedFolders = $this->sharedFolderMapper->findByShareAndUser($share->getId(), $deletedId);
		$this->assertCount(1, $deletedSharedFolders);
		$deletedSharedFolderId = $deletedSharedFolders[0]->getId();
		$this->assertTrue($this->shareTreeRowExists($deletedSharedFolderId));
		$this->assertCount(1, $this->sharedFolderMapper->findByShareAndUser($share->getId(), $remainingId));

		try {
			$this->userManager->get($deletedId)->delete();

			$this->assertCount(0, $this->sharedFolderMapper->findByShareAndUser($share->getId(), $deletedId));
			$this->assertFalse($this->shareTreeRowExists($deletedSharedFolderId));

			// The share itself and the other member's SharedFolder are untouched
			$this->shareMapper->find($share->getId());
			$this->assertCount(1, $this->sharedFolderMapper->findByShareAndUser($share->getId(), $remainingId));
		} finally {
			$group->delete();
		}
	}

	/**
	 * Deleting a group that a folder was shared with must remove the share and
	 * every member's SharedFolder and tree row.
	 */
	public function testDeletedGroupRemovesShareAndSharedFolders(): void {
		[$group, $share, $firstMemberId, $secondMemberId] = $this->createGroupShare('groupdel');

		$sharedFolderIds = [];
		foreach ([$firstMemberId, $secondMemberId] as $memberId) {
			$sharedFolders = $this->sharedFolderMapper->findByShareAndUser($share->getId(), $memberId);
			$this->assertCount(1, $sharedFolders);
			$this->assertTrue($this->shareTreeRowExists($sharedFolders[0]->getId()));
			$sharedFolderIds[] = $sharedFolders[0]->getId();
		}

		$group->delete();

		try {
			$this->shareMapper->find($share->getId());
			$this->fail('Share should have been deleted together with the group');
		} catch (DoesNotExistException $e) {
			// expected
		}
		$this->assertCount(0, $this->sharedFolderMapper->findByShare($share->getId()));
		foreach ([$firstMemberId, $secondMemberId] as $memberId) {
			$this->assertCount(0, $this->sharedFolderMapper->findByShareAndUser($share->getId(), $memberId));
		}
		foreach ($sharedFolderIds as $sharedFolderId) {
			$this->assertFalse($this->shareTreeRowExists($sharedFolderId));
		}
	}

	/**
	 * Deleting a user that a folder was shared with directly must remove that
	 * share along with their SharedFolder and tree row, while leaving a share of
	 * the same folder with another user intact.
	 */
	public function testDeletedUserRemovesDirectShareAndSharedFolder(): void {
		$ownerId = $this->createUser('user_share_owner');
		$deletedId = $this->createUser('user_share_deleted_recipient');
		$remainingId = $this->createUser('user_share_remaining_recipient');

		$folder = new Db\Folder();
		$folder->setTitle('shared with users');
		$folder->setUserId($ownerId);
		$this->folderMapper->insert($folder);
		$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $folder->getId(), $this->folderMapper->findRootFolder($ownerId)->getId());

		$deletedShare = $this->folders->createShare($folder->getId(), $deletedId, IShare::TYPE_USER);
		$remainingShare = $this->folders->createShare($folder->getId(), $remainingId, IShare::TYPE_USER);

		$deletedSharedFolders = $this->sharedFolderMapper->findByShareAndUser($deletedShare->getId(), $deletedId);
		$this->assertCount(1, $deletedSharedFolders);
		$deletedSharedFolderId = $deletedSharedFolders[0]->getId();
		$this->assertTrue($this->shareTreeRowExists($deletedSharedFolderId));

		$this->userManager->get($deletedId)->delete();

		try {
			$this->shareMapper->find($deletedShare->getId());
			$this->fail('Share should have been deleted together with the recipient');
		} catch (DoesNotExistException $e) {
			// expected
		}
		$this->assertCount(0, $this->sharedFolderMapper->findByShare($deletedShare->getId()));
		$this->assertFalse($this->shareTreeRowExists($deletedSharedFolderId));

		// The other recipient's share is untouched
		$this->shareMapper->find($remainingShare->getId());
		$remainingSharedFolders = $this->sharedFolderMapper->findByShareAndUser($remainingShare->getId(), $remainingId);
		$this->assertCount(1, $remainingSharedFolders);
		$this->assertTrue($this->shareTreeRowExists($remainingSharedFolders[0]->getId()));
	}

	private function createFolder(string $ownerId): Db\Folder {
		$folder = new Db\Folder();
		$folder->setTitle('shared twice');
		$folder->setUserId($ownerId);
		$this->folderMapper->insert($folder);
		$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $folder->getId(), $this->folderMapper->findRootFolder($ownerId)->getId());
		return $folder;
	}

	/**
	 * A user who leaves a group but still has access to the folder through a share
	 * with another group must keep their shared folder, which is part of both shares.
	 */
	public function testUserRemovedFromGroupKeepsFolderSharedWithOtherGroup(): void {
		$ownerId = $this->createUser('two_groups_owner');
		$memberId = $this->createUser('two_groups_member');
		$firstGroup = $this->groupManager->createGroup('two_groups_first');
		$secondGroup = $this->groupManager->createGroup('two_groups_second');
		$firstGroup->addUser($this->userManager->get($memberId));
		$secondGroup->addUser($this->userManager->get($memberId));
		try {
			$folder = $this->createFolder($ownerId);
			$firstShare = $this->folders->createShare($folder->getId(), $firstGroup->getGID(), IShare::TYPE_GROUP);
			$secondShare = $this->folders->createShare($folder->getId(), $secondGroup->getGID(), IShare::TYPE_GROUP);
			// The member only gets one shared folder, which is part of both shares
			$sharedFolders = $this->sharedFolderMapper->findByShareAndUser($firstShare->getId(), $memberId);
			$this->assertCount(1, $sharedFolders);
			$sharedFolderId = $sharedFolders[0]->getId();
			$this->assertEquals($sharedFolderId, $this->sharedFolderMapper->findByShareAndUser($secondShare->getId(), $memberId)[0]->getId());
			$this->assertCount(1, $this->sharedFolderMapper->findByUser($memberId));

			$firstGroup->removeUser($this->userManager->get($memberId));

			$this->assertCount(0, $this->sharedFolderMapper->findByShareAndUser($firstShare->getId(), $memberId));
			$sharedFolders = $this->sharedFolderMapper->findByShareAndUser($secondShare->getId(), $memberId);
			$this->assertCount(1, $sharedFolders);
			// It's the same shared folder, still in place
			$this->assertEquals($sharedFolderId, $sharedFolders[0]->getId());
			$this->assertTrue($this->shareTreeRowExists($sharedFolderId));

			$secondGroup->removeUser($this->userManager->get($memberId));

			$this->assertCount(0, $this->sharedFolderMapper->findByShareAndUser($secondShare->getId(), $memberId));
			$this->assertFalse($this->shareTreeRowExists($sharedFolderId));
		} finally {
			$firstGroup->delete();
			$secondGroup->delete();
		}
	}

	/**
	 * A user who leaves a group but also has the folder shared with them directly
	 * must keep exactly one shared folder.
	 */
	public function testUserRemovedFromGroupKeepsFolderSharedDirectly(): void {
		$ownerId = $this->createUser('group_and_user_owner');
		$memberId = $this->createUser('group_and_user_member');
		$group = $this->groupManager->createGroup('group_and_user');
		$group->addUser($this->userManager->get($memberId));
		try {
			$folder = $this->createFolder($ownerId);
			$groupShare = $this->folders->createShare($folder->getId(), $group->getGID(), IShare::TYPE_GROUP);
			$userShare = $this->folders->createShare($folder->getId(), $memberId, IShare::TYPE_USER);
			// The member already has the folder through the group, so the direct share doesn't add it again,
			// but the existing shared folder becomes part of the direct share
			$this->assertCount(1, $this->sharedFolderMapper->findByShareAndUser($groupShare->getId(), $memberId));
			$this->assertCount(1, $this->sharedFolderMapper->findByShareAndUser($userShare->getId(), $memberId));
			$this->assertCount(1, $this->sharedFolderMapper->findByUser($memberId));

			$group->removeUser($this->userManager->get($memberId));

			$this->assertCount(0, $this->sharedFolderMapper->findByShareAndUser($groupShare->getId(), $memberId));
			$sharedFolders = $this->sharedFolderMapper->findByShareAndUser($userShare->getId(), $memberId);
			$this->assertCount(1, $sharedFolders);
			$this->assertTrue($this->shareTreeRowExists($sharedFolders[0]->getId()));
		} finally {
			$group->delete();
		}
	}

	/**
	 * A user who removed a folder shared through a group must get it back
	 * when the folder is then shared with them directly.
	 */
	public function testDirectShareRestoresFolderRemovedFromGroupShare(): void {
		$ownerId = $this->createUser('removed_then_direct_owner');
		$memberId = $this->createUser('removed_then_direct_member');
		$group = $this->groupManager->createGroup('removed_then_direct');
		$group->addUser($this->userManager->get($memberId));
		try {
			$folder = $this->createFolder($ownerId);
			$groupShare = $this->folders->createShare($folder->getId(), $group->getGID(), IShare::TYPE_GROUP);
			$sharedFolderId = $this->sharedFolderMapper->findByShareAndUser($groupShare->getId(), $memberId)[0]->getId();

			$this->folders->deleteSharedFolderOrFolder($memberId, $folder->getId(), true);
			$this->assertFalse($this->shareTreeRowExists($sharedFolderId));

			$this->folders->createShare($folder->getId(), $memberId, IShare::TYPE_USER);

			$sharedFolders = $this->sharedFolderMapper->findByUser($memberId);
			$this->assertCount(1, $sharedFolders);
			$this->assertEquals($sharedFolderId, $sharedFolders[0]->getId());
			$this->assertTrue($this->shareTreeRowExists($sharedFolderId));
			$rootFolder = $this->folderMapper->findRootFolder($memberId);
			$this->assertEquals($rootFolder->getId(), $this->treeMapper->findParentOf(Db\TreeMapper::TYPE_SHARE, $sharedFolderId)->getId());
		} finally {
			$group->delete();
		}
	}

	/**
	 * A user who removed a folder shared with them directly must get it back
	 * when the folder is then shared with a group they are a member of.
	 */
	public function testGroupShareRestoresFolderRemovedFromDirectShare(): void {
		$ownerId = $this->createUser('removed_then_group_owner');
		$memberId = $this->createUser('removed_then_group_member');
		$group = $this->groupManager->createGroup('removed_then_group');
		$group->addUser($this->userManager->get($memberId));
		try {
			$folder = $this->createFolder($ownerId);
			$userShare = $this->folders->createShare($folder->getId(), $memberId, IShare::TYPE_USER);
			$sharedFolderId = $this->sharedFolderMapper->findByShareAndUser($userShare->getId(), $memberId)[0]->getId();

			$this->folders->deleteSharedFolderOrFolder($memberId, $folder->getId(), true);
			$this->assertFalse($this->shareTreeRowExists($sharedFolderId));

			$this->folders->createShare($folder->getId(), $group->getGID(), IShare::TYPE_GROUP);

			$sharedFolders = $this->sharedFolderMapper->findByUser($memberId);
			$this->assertCount(1, $sharedFolders);
			$this->assertEquals($sharedFolderId, $sharedFolders[0]->getId());
			$rootFolder = $this->folderMapper->findRootFolder($memberId);
			$this->assertEquals($rootFolder->getId(), $this->treeMapper->findParentOf(Db\TreeMapper::TYPE_SHARE, $sharedFolderId)->getId());
		} finally {
			$group->delete();
		}
	}

	/**
	 * Deleting a share must keep the shared folder of participants who still have
	 * access to the folder through another share, and remove everyone else's.
	 */
	public function testDeletedShareKeepsFolderForUsersCoveredByOtherShare(): void {
		$ownerId = $this->createUser('deleted_share_owner');
		$coveredId = $this->createUser('deleted_share_covered_member');
		$otherId = $this->createUser('deleted_share_other_member');
		$group = $this->groupManager->createGroup('deleted_share');
		$group->addUser($this->userManager->get($coveredId));
		$group->addUser($this->userManager->get($otherId));
		try {
			$folder = $this->createFolder($ownerId);
			$groupShare = $this->folders->createShare($folder->getId(), $group->getGID(), IShare::TYPE_GROUP);
			$userShare = $this->folders->createShare($folder->getId(), $coveredId, IShare::TYPE_USER);
			$coveredSharedFolderId = $this->sharedFolderMapper->findByShareAndUser($groupShare->getId(), $coveredId)[0]->getId();
			$otherSharedFolderId = $this->sharedFolderMapper->findByShareAndUser($groupShare->getId(), $otherId)[0]->getId();

			$this->folders->deleteShare($groupShare->getId());

			try {
				$this->shareMapper->find($groupShare->getId());
				$this->fail('Share should have been deleted');
			} catch (DoesNotExistException $e) {
				// expected
			}
			$this->assertCount(0, $this->sharedFolderMapper->findByShare($groupShare->getId()));
			// The covered member keeps the same shared folder, which is still part of the direct share
			$sharedFolders = $this->sharedFolderMapper->findByShareAndUser($userShare->getId(), $coveredId);
			$this->assertCount(1, $sharedFolders);
			$this->assertEquals($coveredSharedFolderId, $sharedFolders[0]->getId());
			$this->assertTrue($this->shareTreeRowExists($coveredSharedFolderId));
			// The other member loses it
			$this->assertCount(0, $this->sharedFolderMapper->findByUser($otherId));
			$this->assertFalse($this->shareTreeRowExists($otherSharedFolderId));
		} finally {
			$group->delete();
		}
	}

	/**
	 * A user who already has a folder through a direct share and then joins a group
	 * the folder is also shared with must not get a second shared folder.
	 */
	public function testUserAddedToGroupKeepsSingleSharedFolderOfDirectShare(): void {
		$ownerId = $this->createUser('direct_then_group_owner');
		$memberId = $this->createUser('direct_then_group_member');
		$group = $this->groupManager->createGroup('direct_then_group');
		try {
			$folder = $this->createFolder($ownerId);
			$userShare = $this->folders->createShare($folder->getId(), $memberId, IShare::TYPE_USER);
			$groupShare = $this->folders->createShare($folder->getId(), $group->getGID(), IShare::TYPE_GROUP);
			$this->assertCount(1, $this->sharedFolderMapper->findByShareAndUser($userShare->getId(), $memberId));

			$group->addUser($this->userManager->get($memberId));

			// The existing shared folder becomes part of the group share
			$this->assertCount(1, $this->sharedFolderMapper->findByShareAndUser($groupShare->getId(), $memberId));
			$this->assertCount(1, $this->sharedFolderMapper->findByUser($memberId));
			// Looking up the user's shared folder of this folder still works
			$this->sharedFolderMapper->findByFolderAndUser($folder->getId(), $memberId);
		} finally {
			$group->delete();
		}
	}

	/**
	 * A user who joins a group must not get a folder shared with that group if it contains
	 * a folder they shared themselves. Would cause a loop.
	 */
	public function testUserAddedToGroupDoesNotGetFolderContainingTheirOwnShare(): void {
		$ownerId = $this->createUser('loop_group_owner');
		$memberId = $this->createUser('loop_group_member');
		$group = $this->groupManager->createGroup('loop_group');
		try {
			// The member shares one of their folders with the owner, who puts it into a folder of their own
			$memberFolder = $this->createFolder($memberId);
			$memberShare = $this->folders->createShare($memberFolder->getId(), $ownerId, IShare::TYPE_USER);
			$ownerSharedFolder = $this->sharedFolderMapper->findByShareAndUser($memberShare->getId(), $ownerId)[0];
			$folder = $this->createFolder($ownerId);
			$this->treeMapper->move(Db\TreeMapper::TYPE_SHARE, $ownerSharedFolder->getId(), $folder->getId());
			$groupShare = $this->folders->createShare($folder->getId(), $group->getGID(), IShare::TYPE_GROUP);

			$group->addUser($this->userManager->get($memberId));

			$this->assertCount(0, $this->sharedFolderMapper->findByShareAndUser($groupShare->getId(), $memberId));
			$this->assertCount(0, $this->sharedFolderMapper->findByUser($memberId));
		} finally {
			$group->delete();
		}
	}

	/**
	 * Sharing a subfolder directly with a user who already has its parent folder must give
	 * them a shared folder of the subfolder, so they keep it when the parent is unshared.
	 */
	public function testDirectShareOfSubfolderSurvivesUnsharingParentFolder(): void {
		$ownerId = $this->createUser('nested_shares_owner');
		$memberId = $this->createUser('nested_shares_member');

		$parentFolder = $this->createFolder($ownerId);
		$subFolder = new Db\Folder();
		$subFolder->setTitle('subfolder');
		$subFolder->setUserId($ownerId);
		$this->folderMapper->insert($subFolder);
		$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $subFolder->getId(), $parentFolder->getId());

		$parentShare = $this->folders->createShare($parentFolder->getId(), $memberId, IShare::TYPE_USER);
		$subShare = $this->folders->createShare($subFolder->getId(), $memberId, IShare::TYPE_USER, true);
		$subSharedFolders = $this->sharedFolderMapper->findByShareAndUser($subShare->getId(), $memberId);
		$this->assertCount(1, $subSharedFolders);

		$this->folders->deleteShare($parentShare->getId());

		$this->assertCount(0, $this->sharedFolderMapper->findByShareAndUser($parentShare->getId(), $memberId));
		$sharedFolders = $this->sharedFolderMapper->findByShareAndUser($subShare->getId(), $memberId);
		$this->assertCount(1, $sharedFolders);
		$this->assertEquals($subSharedFolders[0]->getId(), $sharedFolders[0]->getId());
		$this->assertTrue($this->shareTreeRowExists($sharedFolders[0]->getId()));
	}

	/**
	 * Sharing a folder directly with a user who has several shared folders of it, as older
	 * versions could create, must work and not add yet another one.
	 */
	public function testDirectShareWithUserWhoHasDuplicateSharedFolders(): void {
		$ownerId = $this->createUser('duplicates_owner');
		$memberId = $this->createUser('duplicates_member');
		$firstGroup = $this->groupManager->createGroup('duplicates_first');
		$secondGroup = $this->groupManager->createGroup('duplicates_second');
		try {
			$folder = $this->createFolder($ownerId);
			$firstShare = $this->folders->createShare($folder->getId(), $firstGroup->getGID(), IShare::TYPE_GROUP);
			$secondShare = $this->folders->createShare($folder->getId(), $secondGroup->getGID(), IShare::TYPE_GROUP);
			$this->folders->addSharedFolder($firstShare, $folder, $memberId);
			$this->folders->addSharedFolder($secondShare, $folder, $memberId);
			$this->assertCount(2, $this->sharedFolderMapper->findByUser($memberId));

			$userShare = $this->folders->createShare($folder->getId(), $memberId, IShare::TYPE_USER);

			$this->shareMapper->find($userShare->getId());
			$this->assertCount(0, $this->sharedFolderMapper->findByShareAndUser($userShare->getId(), $memberId));
			$this->assertCount(2, $this->sharedFolderMapper->findByUser($memberId));
		} finally {
			$firstGroup->delete();
			$secondGroup->delete();
		}
	}

	/**
	 * Sharing a subfolder with a group must give a member who already has its parent folder
	 * a shared folder of the subfolder, so they keep it when the parent is unshared.
	 * The same goes for a user who joins the group afterwards.
	 */
	public function testGroupShareOfSubfolderSurvivesUnsharingParentFolder(): void {
		$ownerId = $this->createUser('nested_group_share_owner');
		$memberId = $this->createUser('nested_group_share_member');
		$joinerId = $this->createUser('nested_group_share_joiner');
		$group = $this->groupManager->createGroup('nested_group_share');
		$group->addUser($this->userManager->get($memberId));
		try {
			$parentFolder = $this->createFolder($ownerId);
			$subFolder = $this->createFolder($ownerId);
			$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $subFolder->getId(), $parentFolder->getId());

			$memberParentShare = $this->folders->createShare($parentFolder->getId(), $memberId, IShare::TYPE_USER);
			$joinerParentShare = $this->folders->createShare($parentFolder->getId(), $joinerId, IShare::TYPE_USER);
			$groupShare = $this->folders->createShare($subFolder->getId(), $group->getGID(), IShare::TYPE_GROUP);
			$group->addUser($this->userManager->get($joinerId));
			$this->assertCount(1, $this->sharedFolderMapper->findByShareAndUser($groupShare->getId(), $memberId));
			$this->assertCount(1, $this->sharedFolderMapper->findByShareAndUser($groupShare->getId(), $joinerId));

			$this->folders->deleteShare($memberParentShare->getId());
			$this->folders->deleteShare($joinerParentShare->getId());

			foreach ([$memberId, $joinerId] as $userId) {
				$sharedFolders = $this->sharedFolderMapper->findByShareAndUser($groupShare->getId(), $userId);
				$this->assertCount(1, $sharedFolders);
				$this->assertTrue($this->shareTreeRowExists($sharedFolders[0]->getId()));
			}
		} finally {
			$group->delete();
		}
	}
}
