<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Bookmarks\Service\FolderService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IUserManager;
use OCP\Share\IShare;

class BookmarkMapperTest extends TestCase {
	/**
	 * @var mixed|Db\BookmarkMapper
	 */
	private $bookmarkMapper;

	/**
	 * @var Db\TreeMapper
	 */
	private $treeMapper;

	/**
	 * @var Db\FolderMapper
	 */
	private $folderMapper;

	/**
	 * @var string
	 */
	private $userId;
	/**
	 * @var \OC\User\Manager
	 */
	private $userManager;
	/**
	 * @var string
	 */
	private $user;

	/**
	 * @throws \OCP\AppFramework\QueryException
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->cleanUp();

		/**
		 * @var Db\BookmarkMapper
		 */
		$this->bookmarkMapper = \OCP\Server::get(Db\BookmarkMapper::class);
		$this->treeMapper = \OCP\Server::get(Db\TreeMapper::class);
		$this->folderMapper = \OCP\Server::get(Db\FolderMapper::class);

		$this->userManager = \OCP\Server::get(IUserManager::class);
		$this->user = 'test';
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();
	}

	/**
	 * @dataProvider singleBookmarksProvider
	 * @param Entity $bookmark
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function testInsertAndFind(Entity $bookmark) {
		$bookmark->setUserId($this->userId);
		$bookmark = $this->bookmarkMapper->insert($bookmark);
		$foundEntity = $this->bookmarkMapper->find($bookmark->getId());
		$this->assertSame($bookmark->getUrl(), $foundEntity->getUrl());
		$this->assertSame((string)$bookmark->getTitle(), (string)$foundEntity->getTitle());
		$this->assertSame((string)$bookmark->getDescription(), (string)$foundEntity->getDescription());
	}

	/**
	 * @depends      testInsertAndFind
	 * @dataProvider singleBookmarksProvider
	 * @param Entity $bookmark
	 * @return void
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function testUpdate(Entity $bookmark) {
		$bookmark->setUserId($this->userId);
		$bookmark = $this->bookmarkMapper->insert($bookmark);

		$entity = $this->bookmarkMapper->find($bookmark->getId());
		$entity->setTitle('foobar');
		$this->bookmarkMapper->update($entity);
		$foundEntity = $this->bookmarkMapper->find($entity->getId());
		$this->assertSame((string)$entity->getTitle(), (string)$foundEntity->getTitle());
	}

	/**
	 * @depends      testInsertAndFind
	 * @dataProvider singleBookmarksProvider
	 * @param Entity $bookmark
	 * @return void
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function testDelete(Entity $bookmark) {
		$bookmark->setUserId($this->userId);
		$bookmark = $this->bookmarkMapper->insert($bookmark);

		$foundEntity = $this->bookmarkMapper->find($bookmark->getId());
		$this->bookmarkMapper->delete($foundEntity);
		$this->expectException(DoesNotExistException::class);
		$this->bookmarkMapper->find($foundEntity->getId());
	}

	/**
	 * Creates an owner and a recipient user, gives the owner a folder (mounted under
	 * their root) that contains one subfolder, and shares that folder with the
	 * recipient. The recipient therefore only reaches the subfolder *through* the
	 * share — exactly the case the count*() methods used to miss because they queried
	 * the raw bookmarks_tree table instead of the recursive folder_tree CTE.
	 *
	 * @param string $suffix unique suffix so each test gets its own users/folders
	 * @return array{0: string, 1: string, 2: int, 3: int} [ownerId, recipientId, sharedFolderId, subFolderId]
	 */
	private function createSharedFolderWithSubfolder(string $suffix): array {
		$owner = 'count_share_owner_' . $suffix;
		$recipient = 'count_share_recipient_' . $suffix;
		if (!$this->userManager->userExists($owner)) {
			$this->userManager->createUser($owner, 'password');
		}
		if (!$this->userManager->userExists($recipient)) {
			$this->userManager->createUser($recipient, 'password');
		}
		$ownerId = $this->userManager->get($owner)->getUID();
		$recipientId = $this->userManager->get($recipient)->getUID();

		$sharedFolder = new Db\Folder();
		$sharedFolder->setTitle('shared-root');
		$sharedFolder->setUserId($ownerId);
		$this->folderMapper->insert($sharedFolder);
		$this->treeMapper->move(
			Db\TreeMapper::TYPE_FOLDER,
			$sharedFolder->getId(),
			$this->folderMapper->findRootFolder($ownerId)->getId(),
		);

		$subFolder = new Db\Folder();
		$subFolder->setTitle('sub');
		$subFolder->setUserId($ownerId);
		$this->folderMapper->insert($subFolder);
		$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $subFolder->getId(), $sharedFolder->getId());

		/** @var FolderService $folderService */
		$folderService = \OCP\Server::get(FolderService::class);
		$folderService->createShare(
			$sharedFolder->getId(),
			$recipient,
			IShare::TYPE_USER,
			true,
			false,
		);

		return [$ownerId, $recipientId, $sharedFolder->getId(), $subFolder->getId()];
	}

	/**
	 * Regression test: countDuplicated() must count a bookmark that is duplicated
	 * across two subfolders of a *shared* folder, for the sharee. The previous
	 * implementation queried the raw bookmarks_tree table (owner's bookmarks +
	 * bookmarks directly inside a shared folder) and therefore under-counted: it
	 * never saw duplicates living in subfolders of shared folders. The count must
	 * match what the "Duplicated" list (findAll + setDuplicated) actually shows.
	 *
	 * @throws \OCA\Bookmarks\Exception\AlreadyExistsError
	 * @throws \OCA\Bookmarks\Exception\UserLimitExceededError
	 * @throws UrlParseError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testCountDuplicatedInSubfolderOfSharedFolder() {
		[$ownerId, $recipientId, $sharedFolderId, $subFolderA] = $this->createSharedFolderWithSubfolder('dup');

		// A second subfolder, so the bookmark can live in two distinct places.
		$subFolderB = new Db\Folder();
		$subFolderB->setTitle('sub-b');
		$subFolderB->setUserId($ownerId);
		$this->folderMapper->insert($subFolderB);
		$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $subFolderB->getId(), $sharedFolderId);

		// A single bookmark placed in BOTH subfolders -> it is duplicated.
		$bookmark = Db\Bookmark::fromArray([
			'userId' => $ownerId,
			'url' => 'https://example.org/duplicated-in-shared-subfolders',
			'title' => 'Nested duplicate',
			'description' => '',
		]);
		$bookmark = $this->bookmarkMapper->insertOrUpdate($bookmark);
		$this->treeMapper->addToFolders(
			Db\TreeMapper::TYPE_BOOKMARK,
			$bookmark->getId(),
			[$subFolderA, $subFolderB->getId()],
		);

		// The owner reaches both subfolders directly -> sees 1 duplicate.
		$this->assertSame(1, $this->bookmarkMapper->countDuplicated($ownerId));

		// The sharee reaches both subfolders through the share -> must also see 1
		// duplicate. This is the case the old implementation missed (returned 0).
		$this->assertSame(1, $this->bookmarkMapper->countDuplicated($recipientId));

		// And the count must agree with the actual "Duplicated" list.
		$params = new \OCA\Bookmarks\QueryParameters();
		$duplicatedList = $this->bookmarkMapper->findAll($recipientId, $params->setDuplicated(true));
		$this->assertCount(1, $duplicatedList);
	}

	/**
	 * A bookmark that lives in two folders but whose second copy has been trashed is
	 * NOT a duplicate: only live copies count. countDuplicated() and the "Duplicated"
	 * list (findAll + setDuplicated) must both ignore the trashed copy and agree.
	 *
	 * @throws AlreadyExistsError
	 * @throws UserLimitExceededError
	 * @throws UrlParseError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testCountDuplicatedIgnoresTrashedCopies() {
		$rootFolderId = $this->folderMapper->findRootFolder($this->userId)->getId();

		$folderA = new Db\Folder();
		$folderA->setTitle('dup-a');
		$folderA->setUserId($this->userId);
		$this->folderMapper->insert($folderA);
		$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $folderA->getId(), $rootFolderId);

		$folderB = new Db\Folder();
		$folderB->setTitle('dup-b');
		$folderB->setUserId($this->userId);
		$this->folderMapper->insert($folderB);
		$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $folderB->getId(), $rootFolderId);

		// One bookmark placed in both folders -> duplicated while both copies are live.
		$bookmark = Db\Bookmark::fromArray([
			'userId' => $this->userId,
			'url' => 'https://example.org/duplicated-then-trashed',
			'title' => 'Trashed duplicate',
			'description' => '',
		]);
		$bookmark = $this->bookmarkMapper->insertOrUpdate($bookmark);
		$this->treeMapper->addToFolders(
			Db\TreeMapper::TYPE_BOOKMARK,
			$bookmark->getId(),
			[$folderA->getId(), $folderB->getId()],
		);

		$this->assertSame(1, $this->bookmarkMapper->countDuplicated($this->userId));

		// Trash the copy in folder B -> only one live copy remains -> not a duplicate.
		$this->treeMapper->softDeleteEntry(Db\TreeMapper::TYPE_BOOKMARK, $bookmark->getId(), $folderB->getId());

		$this->assertSame(0, $this->bookmarkMapper->countDuplicated($this->userId));

		$params = new \OCA\Bookmarks\QueryParameters();
		$duplicatedList = $this->bookmarkMapper->findAll($this->userId, $params->setDuplicated(true));
		$this->assertCount(0, $duplicatedList);
	}

	/**
	 * Regression test: countUnavailable() must count an unavailable bookmark living
	 * in a subfolder of a shared folder, for the sharee. The old implementation only
	 * looked at bookmarks owned by the user or *directly* in a shared folder, so it
	 * missed the nested case. The count must match the "Unavailable" list.
	 *
	 * @throws \OCA\Bookmarks\Exception\AlreadyExistsError
	 * @throws \OCA\Bookmarks\Exception\UserLimitExceededError
	 * @throws UrlParseError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testCountUnavailableInSubfolderOfSharedFolder() {
		[$ownerId, $recipientId, , $subFolderId] = $this->createSharedFolderWithSubfolder('unavail');

		$bookmark = Db\Bookmark::fromArray([
			'userId' => $ownerId,
			'url' => 'https://example.org/unavailable-in-shared-subfolder',
			'title' => 'Nested unavailable',
			'description' => '',
		]);
		$bookmark = $this->bookmarkMapper->insertOrUpdate($bookmark);
		$bookmark->setAvailable(false);
		$this->bookmarkMapper->update($bookmark);
		$this->treeMapper->addToFolders(Db\TreeMapper::TYPE_BOOKMARK, $bookmark->getId(), [$subFolderId]);

		// Owner reaches the subfolder directly; sharee reaches it through the share.
		// The old implementation missed the nested case for the sharee (returned 0).
		$this->assertSame(1, $this->bookmarkMapper->countUnavailable($ownerId));
		$this->assertSame(1, $this->bookmarkMapper->countUnavailable($recipientId));

		// And the count must agree with the actual "Unavailable" list.
		$params = new \OCA\Bookmarks\QueryParameters();
		$unavailableList = $this->bookmarkMapper->findAll($recipientId, $params->setUnavailable(true));
		$this->assertCount(1, $unavailableList);
	}

	/**
	 * Regression test: countWithClicks() must count a clicked bookmark living in a
	 * subfolder of a shared folder, for the sharee. The old implementation missed the
	 * nested case (returned 0 for the sharee).
	 *
	 * @throws \OCA\Bookmarks\Exception\AlreadyExistsError
	 * @throws \OCA\Bookmarks\Exception\UserLimitExceededError
	 * @throws UrlParseError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testCountWithClicksInSubfolderOfSharedFolder() {
		[$ownerId, $recipientId, , $subFolderId] = $this->createSharedFolderWithSubfolder('withclicks');

		$bookmark = Db\Bookmark::fromArray([
			'userId' => $ownerId,
			'url' => 'https://example.org/clicked-in-shared-subfolder',
			'title' => 'Nested clicked',
			'description' => '',
		]);
		$bookmark = $this->bookmarkMapper->insertOrUpdate($bookmark);
		$bookmark->setClickcount(3);
		$this->bookmarkMapper->update($bookmark);
		$this->treeMapper->addToFolders(Db\TreeMapper::TYPE_BOOKMARK, $bookmark->getId(), [$subFolderId]);

		// Owner reaches the subfolder directly; sharee reaches it through the share.
		$this->assertSame(1, $this->bookmarkMapper->countWithClicks($ownerId));
		$this->assertSame(1, $this->bookmarkMapper->countWithClicks($recipientId));
	}

	/**
	 * Regression test: countAllClicks() must (a) include clicks of a bookmark living
	 * in a subfolder of a shared folder for the sharee, and (b) count each bookmark's
	 * clicks only once even when it is reachable through several folders. The old
	 * implementation summed per tree row (over-counting duplicates) and missed the
	 * nested case for the sharee entirely.
	 *
	 * @throws \OCA\Bookmarks\Exception\AlreadyExistsError
	 * @throws \OCA\Bookmarks\Exception\UserLimitExceededError
	 * @throws UrlParseError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testCountAllClicksInSubfolderOfSharedFolderCountsEachBookmarkOnce() {
		[$ownerId, $recipientId, $sharedFolderId, $subFolderA] = $this->createSharedFolderWithSubfolder('allclicks');

		// A second subfolder so the same bookmark is reachable through two nested folders.
		$subFolderB = new Db\Folder();
		$subFolderB->setTitle('sub-b');
		$subFolderB->setUserId($ownerId);
		$this->folderMapper->insert($subFolderB);
		$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $subFolderB->getId(), $sharedFolderId);

		$bookmark = Db\Bookmark::fromArray([
			'userId' => $ownerId,
			'url' => 'https://example.org/clicks-in-shared-subfolders',
			'title' => 'Nested clicks',
			'description' => '',
		]);
		$bookmark = $this->bookmarkMapper->insertOrUpdate($bookmark);
		$bookmark->setClickcount(7);
		$this->bookmarkMapper->update($bookmark);
		$this->treeMapper->addToFolders(
			Db\TreeMapper::TYPE_BOOKMARK,
			$bookmark->getId(),
			[$subFolderA, $subFolderB->getId()],
		);

		// Reachable through two nested folders, but its 7 clicks must be summed once.
		// (Owner: old code summed 14; sharee: old code returned 0.)
		$this->assertSame(7, $this->bookmarkMapper->countAllClicks($ownerId));
		$this->assertSame(7, $this->bookmarkMapper->countAllClicks($recipientId));
	}

	/**
	 * @return array
	 */
	public function singleBookmarksProvider(): array {
		return array_map(function ($props) {
			return [Db\Bookmark::fromArray($props)];
		}, [
			'Simple URL with title and description' => ['url' => 'https://google.com/', 'title' => 'Google', 'description' => 'Search engine'],
			'Simple URL with title' => ['url' => 'https://nextcloud.com/', 'title' => 'Nextcloud', 'description' => ''],
			'Simple URL' => ['url' => 'https://php.net/', 'title' => '', 'description' => ''],
			'URL with unicode' => ['url' => 'https://de.wikipedia.org/wiki/%C3%9C', 'title' => '', 'description' => ''],
		]);
	}
}
