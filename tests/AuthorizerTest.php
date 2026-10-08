<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Service\Authorizer;
use OCA\Bookmarks\Service\FolderService;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IShare;

class AuthorizerTest extends TestCase {
	private IUserManager $userManager;
	private IGroupManager $groupManager;
	private Db\FolderMapper $folderMapper;
	private Db\TreeMapper $treeMapper;
	private Db\SharedFolderMapper $sharedFolderMapper;
	private FolderService $folders;
	private Authorizer $authorizer;

	protected function setUp(): void {
		parent::setUp();
		$this->cleanUp();

		$this->userManager = \OCP\Server::get(IUserManager::class);
		$this->groupManager = \OCP\Server::get(IGroupManager::class);
		$this->folderMapper = \OCP\Server::get(Db\FolderMapper::class);
		$this->treeMapper = \OCP\Server::get(Db\TreeMapper::class);
		$this->sharedFolderMapper = \OCP\Server::get(Db\SharedFolderMapper::class);
		$this->folders = \OCP\Server::get(FolderService::class);
		$this->authorizer = \OCP\Server::get(Authorizer::class);
	}

	private function createUser(string $uid): string {
		if (!$this->userManager->userExists($uid)) {
			$this->userManager->createUser($uid, 'password');
		}
		return $this->userManager->get($uid)->getUID();
	}

	private function createFolder(string $userId, int $parentFolderId): Db\Folder {
		$folder = new Db\Folder();
		$folder->setTitle('folder');
		$folder->setUserId($userId);
		$this->folderMapper->insert($folder);
		$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $folder->getId(), $parentFolderId);
		return $folder;
	}

	/**
	 * A user who has a folder through a read-only group share and is also given
	 * write access directly only has one shared folder, which is part of both shares,
	 * and gets the permissions of both shares.
	 */
	public function testPermissionsOfAllSharesAreCombined(): void {
		$ownerId = $this->createUser('perms_combined_owner');
		$memberId = $this->createUser('perms_combined_member');
		$outsiderId = $this->createUser('perms_combined_outsider');
		$group = $this->groupManager->createGroup('perms_combined');
		$group->addUser($this->userManager->get($memberId));
		try {
			$folder = $this->createFolder($ownerId, $this->folderMapper->findRootFolder($ownerId)->getId());
			$subFolder = $this->createFolder($ownerId, $folder->getId());

			$groupShare = $this->folders->createShare($folder->getId(), $group->getGID(), IShare::TYPE_GROUP, false, false);
			$this->assertEquals(
				Authorizer::PERM_READ | Authorizer::PERM_EDIT,
				$this->authorizer->getUserPermissionsForFolder($memberId, $folder->getId())
			);
			$this->assertEquals(Authorizer::PERM_READ, $this->authorizer->getUserPermissionsForFolder($memberId, $subFolder->getId()));

			$userShare = $this->folders->createShare($folder->getId(), $memberId, IShare::TYPE_USER, true, true);
			// The member still only has one shared folder, which is now part of both shares
			$groupSharedFolders = $this->sharedFolderMapper->findByShareAndUser($groupShare->getId(), $memberId);
			$userSharedFolders = $this->sharedFolderMapper->findByShareAndUser($userShare->getId(), $memberId);
			$this->assertCount(1, $groupSharedFolders);
			$this->assertCount(1, $userSharedFolders);
			$this->assertEquals($groupSharedFolders[0]->getId(), $userSharedFolders[0]->getId());

			$this->assertEquals(Authorizer::PERM_ALL, $this->authorizer->getUserPermissionsForFolder($memberId, $folder->getId()));
			$this->assertEquals(
				Authorizer::PERM_READ | Authorizer::PERM_EDIT | Authorizer::PERM_WRITE | Authorizer::PERM_RESHARE,
				$this->authorizer->getUserPermissionsForFolder($memberId, $subFolder->getId())
			);
			$this->assertEquals(Authorizer::PERM_NONE, $this->authorizer->getUserPermissionsForFolder($outsiderId, $folder->getId()));
		} finally {
			$group->delete();
		}
	}

	/**
	 * A user who has a folder directly with read-only access, and with write access
	 * through someone who re-shared a folder containing it, gets the permissions of both.
	 */
	public function testPermissionsThroughReshareAreCombinedWithDirectAccess(): void {
		$ownerId = $this->createUser('perms_reshare_owner');
		$resharerId = $this->createUser('perms_reshare_resharer');
		$memberId = $this->createUser('perms_reshare_member');

		$folder = $this->createFolder($ownerId, $this->folderMapper->findRootFolder($ownerId)->getId());
		$subFolder = $this->createFolder($ownerId, $folder->getId());
		$this->folders->createShare($folder->getId(), $memberId, IShare::TYPE_USER, false, false);
		$this->assertEquals(Authorizer::PERM_READ, $this->authorizer->getUserPermissionsForFolder($memberId, $subFolder->getId()));

		// The resharer puts the folder they received into one of their own folders and shares that with the member
		$resharerShare = $this->folders->createShare($folder->getId(), $resharerId, IShare::TYPE_USER, true, true);
		$resharerFolder = $this->createFolder($resharerId, $this->folderMapper->findRootFolder($resharerId)->getId());
		$sharedFolder = $this->sharedFolderMapper->findByShareAndUser($resharerShare->getId(), $resharerId)[0];
		$this->treeMapper->move(Db\TreeMapper::TYPE_SHARE, $sharedFolder->getId(), $resharerFolder->getId());
		$this->folders->createShare($resharerFolder->getId(), $memberId, IShare::TYPE_USER, true, false);

		$this->assertEquals(
			Authorizer::PERM_READ | Authorizer::PERM_EDIT | Authorizer::PERM_WRITE,
			$this->authorizer->getUserPermissionsForFolder($memberId, $subFolder->getId())
		);
	}

	/**
	 * Being a participant of a share without having a shared folder for it must
	 * not grant access. This happens when sharing would create a loop: a folder
	 * that contains a folder shared by a group member is shared with that group.
	 */
	public function testParticipantWithoutSharedFolderHasNoAccess(): void {
		$ownerId = $this->createUser('perms_loop_owner');
		$memberId = $this->createUser('perms_loop_member');
		$group = $this->groupManager->createGroup('perms_loop');
		$group->addUser($this->userManager->get($memberId));
		try {
			// The member shares one of their folders with the owner, who puts it into their own folder
			$memberFolder = $this->createFolder($memberId, $this->folderMapper->findRootFolder($memberId)->getId());
			$memberShare = $this->folders->createShare($memberFolder->getId(), $ownerId, IShare::TYPE_USER);
			$ownerFolder = $this->createFolder($ownerId, $this->folderMapper->findRootFolder($ownerId)->getId());
			$sharedFolder = $this->sharedFolderMapper->findByShareAndUser($memberShare->getId(), $ownerId)[0];
			$this->treeMapper->move(Db\TreeMapper::TYPE_SHARE, $sharedFolder->getId(), $ownerFolder->getId());

			// Sharing the owner's folder with the group skips the member
			$groupShare = $this->folders->createShare($ownerFolder->getId(), $group->getGID(), IShare::TYPE_GROUP, true, true);
			$this->assertCount(0, $this->sharedFolderMapper->findByShareAndUser($groupShare->getId(), $memberId));

			$this->assertEquals(Authorizer::PERM_NONE, $this->authorizer->getUserPermissionsForFolder($memberId, $ownerFolder->getId()));
		} finally {
			$group->delete();
		}
	}
}
