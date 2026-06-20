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
		$owner = 'dup_share_owner';
		$recipient = 'dup_share_recipient';
		if (!$this->userManager->userExists($owner)) {
			$this->userManager->createUser($owner, 'password');
		}
		if (!$this->userManager->userExists($recipient)) {
			$this->userManager->createUser($recipient, 'password');
		}
		$ownerId = $this->userManager->get($owner)->getUID();
		$recipientId = $this->userManager->get($recipient)->getUID();

		/** @var FolderService $folderService */
		$folderService = \OCP\Server::get(FolderService::class);

		// Owner creates a folder that will be shared...
		$sharedFolder = new Db\Folder();
		$sharedFolder->setTitle('shared-root');
		$sharedFolder->setUserId($ownerId);
		$this->folderMapper->insert($sharedFolder);
		$this->treeMapper->move(
			Db\TreeMapper::TYPE_FOLDER,
			$sharedFolder->getId(),
			$this->folderMapper->findRootFolder($ownerId)->getId(),
		);

		// ...with two subfolders inside it.
		$subFolderA = new Db\Folder();
		$subFolderA->setTitle('sub-a');
		$subFolderA->setUserId($ownerId);
		$this->folderMapper->insert($subFolderA);
		$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $subFolderA->getId(), $sharedFolder->getId());

		$subFolderB = new Db\Folder();
		$subFolderB->setTitle('sub-b');
		$subFolderB->setUserId($ownerId);
		$this->folderMapper->insert($subFolderB);
		$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $subFolderB->getId(), $sharedFolder->getId());

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
			[$subFolderA->getId(), $subFolderB->getId()],
		);

		$folderService->createShare(
			$sharedFolder->getId(),
			$recipient,
			IShare::TYPE_USER,
			true,
			false,
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
