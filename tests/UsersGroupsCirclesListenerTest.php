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
	 * with another group must keep their shared folder, moved over to the other share.
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
			// The member only gets one shared folder, through the share that was created first
			$sharedFolders = $this->sharedFolderMapper->findByShareAndUser($firstShare->getId(), $memberId);
			$this->assertCount(1, $sharedFolders);
			$this->assertCount(0, $this->sharedFolderMapper->findByShareAndUser($secondShare->getId(), $memberId));
			$sharedFolderId = $sharedFolders[0]->getId();

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
			$this->assertCount(1, $this->sharedFolderMapper->findByShareAndUser($groupShare->getId(), $memberId));

			$group->removeUser($this->userManager->get($memberId));

			$this->assertCount(0, $this->sharedFolderMapper->findByShareAndUser($groupShare->getId(), $memberId));
			$sharedFolders = $this->sharedFolderMapper->findByShareAndUser($userShare->getId(), $memberId);
			$this->assertCount(1, $sharedFolders);
			$this->assertTrue($this->shareTreeRowExists($sharedFolders[0]->getId()));
		} finally {
			$group->delete();
		}
	}
}
