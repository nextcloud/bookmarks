<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Migration\GroupSharesUpdateRepairStep;
use OCA\Bookmarks\Migration\OrphanedSharesRepairStep;
use OCA\Bookmarks\Service\FolderService;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Share\IShare;

class SharesRepairStepsTest extends TestCase {
	private IUserManager $userManager;
	private IGroupManager $groupManager;
	private IDBConnection $db;
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
		$this->db = \OCP\Server::get(IDBConnection::class);
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

	private function createFolder(string $userId): Db\Folder {
		$folder = new Db\Folder();
		$folder->setTitle('shared');
		$folder->setUserId($userId);
		$this->folderMapper->insert($folder);
		$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $folder->getId(), $this->folderMapper->findRootFolder($userId)->getId());
		return $folder;
	}

	private function countRows(string $table, string $column, int $id, ?string $type = null): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($column)
			->from($table)
			->where($qb->expr()->eq($column, $qb->createPositionalParameter($id)));
		if ($type !== null) {
			$qb->andWhere($qb->expr()->eq('type', $qb->createPositionalParameter($type)));
		}
		$result = $qb->executeQuery();
		$count = count($result->fetchAll());
		$result->closeCursor();
		return $count;
	}

	/** Removes a shared folder completely, as if it had never been created */
	private function removeSharedFolder(Db\SharedFolder $sharedFolder): void {
		$this->treeMapper->deleteEntry(Db\TreeMapper::TYPE_SHARE, $sharedFolder->getId());
		$this->sharedFolderMapper->delete($sharedFolder);
	}

	/**
	 * Earlier versions only deleted the share row when a group was deleted, leaving
	 * the members' shared folders, their tree entries and their links behind.
	 */
	public function testOrphanedSharesRepairStepRemovesSharedFoldersWithoutShare(): void {
		$ownerId = $this->createUser('repair_orphan_owner');
		$memberId = $this->createUser('repair_orphan_member');
		$group = $this->groupManager->createGroup('repair_orphan');
		$group->addUser($this->userManager->get($memberId));
		try {
			$folder = $this->createFolder($ownerId);
			$share = $this->folders->createShare($folder->getId(), $group->getGID(), IShare::TYPE_GROUP);
			$sharedFolderId = $this->sharedFolderMapper->findByShareAndUser($share->getId(), $memberId)[0]->getId();
			// what earlier versions did on group deletion
			$this->shareMapper->delete($share);

			\OCP\Server::get(OrphanedSharesRepairStep::class)->run($this->createMock(IOutput::class));

			$this->assertCount(0, $this->sharedFolderMapper->findByUser($memberId));
			$this->assertEquals(0, $this->countRows('bookmarks_tree', 'id', $sharedFolderId, Db\TreeMapper::TYPE_SHARE));
			$this->assertEquals(0, $this->countRows('bookmarks_shared_to_shares', 'shared_folder_id', $sharedFolderId));
		} finally {
			$group->delete();
		}
	}

	public function testOrphanedSharesRepairStepRemovesSharesOfMissingFolders(): void {
		$ownerId = $this->createUser('repair_missing_folder_owner');
		$memberId = $this->createUser('repair_missing_folder_member');
		$folder = $this->createFolder($ownerId);
		$share = $this->folders->createShare($folder->getId(), $memberId, IShare::TYPE_USER);
		$sharedFolderId = $this->sharedFolderMapper->findByShareAndUser($share->getId(), $memberId)[0]->getId();
		$qb = $this->db->getQueryBuilder();
		$qb->delete('bookmarks_folders')
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($folder->getId())))
			->executeStatement();

		\OCP\Server::get(OrphanedSharesRepairStep::class)->run($this->createMock(IOutput::class));

		$this->assertEquals(0, $this->countRows('bookmarks_shares', 'id', $share->getId()));
		$this->assertCount(0, $this->sharedFolderMapper->findByUser($memberId));
		$this->assertEquals(0, $this->countRows('bookmarks_tree', 'id', $sharedFolderId, Db\TreeMapper::TYPE_SHARE));
		$this->assertEquals(0, $this->countRows('bookmarks_shared_to_shares', 'shared_folder_id', $sharedFolderId));
	}

	public function testOrphanedSharesRepairStepRemovesEntriesOfMissingSharedFolders(): void {
		$ownerId = $this->createUser('repair_missing_sf_owner');
		$memberId = $this->createUser('repair_missing_sf_member');
		$folder = $this->createFolder($ownerId);
		$share = $this->folders->createShare($folder->getId(), $memberId, IShare::TYPE_USER);
		$sharedFolderId = $this->sharedFolderMapper->findByShareAndUser($share->getId(), $memberId)[0]->getId();
		$qb = $this->db->getQueryBuilder();
		$qb->delete('bookmarks_shared_folders')
			->where($qb->expr()->eq('id', $qb->createPositionalParameter($sharedFolderId)))
			->executeStatement();

		\OCP\Server::get(OrphanedSharesRepairStep::class)->run($this->createMock(IOutput::class));

		$this->assertEquals(0, $this->countRows('bookmarks_tree', 'id', $sharedFolderId, Db\TreeMapper::TYPE_SHARE));
		$this->assertEquals(0, $this->countRows('bookmarks_shared_to_shares', 'shared_folder_id', $sharedFolderId));
		// the share itself is still valid
		$this->shareMapper->find($share->getId());
	}

	public function testGroupSharesUpdateRepairStepAddsMissingMembersWithoutDuplicates(): void {
		$ownerId = $this->createUser('repair_group_owner');
		$missingId = $this->createUser('repair_group_missing_member');
		$coveredId = $this->createUser('repair_group_covered_member');
		$firstGroup = $this->groupManager->createGroup('repair_group_first');
		$secondGroup = $this->groupManager->createGroup('repair_group_second');
		$firstGroup->addUser($this->userManager->get($missingId));
		$firstGroup->addUser($this->userManager->get($coveredId));
		$secondGroup->addUser($this->userManager->get($coveredId));
		try {
			$folder = $this->createFolder($ownerId);
			$firstShare = $this->folders->createShare($folder->getId(), $firstGroup->getGID(), IShare::TYPE_GROUP);
			$this->folders->createShare($folder->getId(), $secondGroup->getGID(), IShare::TYPE_GROUP);
			// The covered member only has the folder through the first share
			$this->assertCount(1, $this->sharedFolderMapper->findByUser($coveredId));
			$this->removeSharedFolder($this->sharedFolderMapper->findByShareAndUser($firstShare->getId(), $missingId)[0]);
			$this->assertCount(0, $this->sharedFolderMapper->findByUser($missingId));

			\OCP\Server::get(GroupSharesUpdateRepairStep::class)->run($this->createMock(IOutput::class));

			$this->assertCount(1, $this->sharedFolderMapper->findByShareAndUser($firstShare->getId(), $missingId));
			$this->assertCount(1, $this->sharedFolderMapper->findByUser($coveredId));
		} finally {
			$firstGroup->delete();
			$secondGroup->delete();
		}
	}
}
