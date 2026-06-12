<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\QueryParameters;
use OCA\Bookmarks\Service\FolderService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\QueryException;
use OCP\IUserManager;
use OCP\Share\IShare;
use PHPUnit\Framework\TestCase;

class TagMapperTest extends TestCase {
	/**
	 * @var Db\BookmarkMapper
	 */
	private $bookmarkMapper;

	/**
	 * @var Db\TagMapper
	 */
	private $tagMapper;

	/**
	 * @var string
	 */
	private $userId;
	/**
	 * @var \stdClass
	 */
	protected $folderMapper;

	/**
	 * @throws QueryException
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->bookmarkMapper = \OCP\Server::get(Db\BookmarkMapper::class);
		$this->tagMapper = \OCP\Server::get(Db\TagMapper::class);
		$this->folderMapper = \OCP\Server::get(Db\FolderMapper::class);
		$this->treeMapper = \OCP\Server::get(Db\TreeMapper::class);

		$this->userManager = \OCP\Server::get(IUserManager::class);
		$this->user = 'test';
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();
	}

	/**
	 * @dataProvider singleBookmarksProvider
	 * @param array $tags
	 * @param Bookmark $bookmark
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws \OCA\Bookmarks\Exception\AlreadyExistsError
	 * @throws \OCA\Bookmarks\Exception\UserLimitExceededError
	 */
	public function testAddToAndFind(array $tags, Bookmark $bookmark) {
		$bookmark->setUserId($this->userId);
		$bookmark = $this->bookmarkMapper->insertOrUpdate($bookmark);
		$this->tagMapper->addTo($tags, $bookmark->getId());
		$this->treeMapper->addToFolders(Db\TreeMapper::TYPE_BOOKMARK, $bookmark->getId(), [$this->folderMapper->findRootFolder($this->userId)->getId()]);

		$actualTags = $this->tagMapper->findByBookmark($bookmark->getId());
		foreach ($tags as $tag) {
			$this->assertContains($tag, $actualTags);
		}
	}

	/**
	 * @depends testAddToAndFind
	 */
	public function testFindAll() {
		$allTags = $this->tagMapper->findAll($this->userId);
		$this->assertContains('one', $allTags);
		$this->assertContains('two', $allTags);
		$this->assertContains('three', $allTags);
		$this->assertContains('four', $allTags);

		$allTagsWithCount = $this->tagMapper->findAllWithCount($this->userId);
		$this->assertTrue(in_array(['name' => 'one', 'count' => 3], $allTagsWithCount));
		$this->assertTrue(in_array(['name' => 'two', 'count' => 2], $allTagsWithCount));
		$this->assertTrue(in_array(['name' => 'three', 'count' => 1], $allTagsWithCount));
		$this->assertTrue(in_array(['name' => 'four', 'count' => 1], $allTagsWithCount));
	}

	/**
	 * @depends testAddToAndFind
	 */
	public function testRename() {
		$this->tagMapper->renameTag($this->userId, 'four', 'one');
		$allTags = $this->tagMapper->findAll($this->userId);
		$this->assertContains('one', $allTags);
		$this->assertContains('two', $allTags);
		$this->assertContains('three', $allTags);
		$this->assertNotContains('four', $allTags);

		$allTagsWithCount = $this->tagMapper->findAllWithCount($this->userId);
		$this->assertTrue(in_array(['name' => 'one', 'count' => 3], $allTagsWithCount));
		$this->assertTrue(in_array(['name' => 'two', 'count' => 2], $allTagsWithCount));
		$this->assertTrue(in_array(['name' => 'three', 'count' => 1], $allTagsWithCount));
		$this->assertFalse(in_array(['name' => 'four', 'count' => 1], $allTagsWithCount));
		$this->assertFalse(in_array(['name' => 'four', 'count' => 0], $allTagsWithCount));
	}

	/**
	 * @depends testAddToAndFind
	 */
	public function testDelete() {
		$this->tagMapper->deleteTag($this->userId, 'one');
		$allTags = $this->tagMapper->findAll($this->userId);
		$this->assertNotContains('one', $allTags);
		$this->assertContains('two', $allTags);
		$this->assertContains('three', $allTags);

		$allTagsWithCount = $this->tagMapper->findAllWithCount($this->userId);
		$this->assertTrue(in_array(['name' => 'two', 'count' => 2], $allTagsWithCount));
		$this->assertTrue(in_array(['name' => 'three', 'count' => 1], $allTagsWithCount));
	}

	/**
	 * @depends      testAddToAndFind
	 * @dataProvider singleBookmarksProvider
	 * @param array $tags
	 * @param Bookmark $bookmark
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 */
	public function testRemoveAllFrom(array $tags, Bookmark $bookmark) {
		$params = new QueryParameters();
		$bookmark = $this->bookmarkMapper->findAll($this->userId, $params->setUrl($bookmark->getUrl()))[0];
		$this->tagMapper->removeAllFrom($bookmark->getId());
		$tags = $this->tagMapper->findByBookmark($bookmark->getId());
		$this->assertEmpty($tags);
	}

	/**
	 * @depends      testRemoveAllFrom
	 * @dataProvider singleBookmarksProvider
	 * @param array $tags
	 * @param Bookmark $bookmark
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 */
	public function testSetOn(array $tags, Bookmark $bookmark) {
		$params = new QueryParameters();
		$bookmark = $this->bookmarkMapper->findAll($this->userId, $params->setUrl($bookmark->getUrl()))[0];
		$newTags = ['foo', 'bar'];
		$this->tagMapper->setOn($newTags, $bookmark->getId());
		$actualTags = $this->tagMapper->findByBookmark($bookmark->getId());
		foreach ($newTags as $tag) {
			$this->assertContains($tag, $actualTags);
		}
	}

	/**
	 * Regression test for https://github.com/nextcloud/bookmarks/issues/1982:
	 * tags on bookmarks placed inside a subfolder of a shared folder must be
	 * visible to the sharee, not just tags on bookmarks directly in the shared
	 * folder.
	 *
	 * @throws \OCA\Bookmarks\Exception\AlreadyExistsError
	 * @throws \OCA\Bookmarks\Exception\UnsupportedOperation
	 * @throws \OCA\Bookmarks\Exception\UserLimitExceededError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws \OCP\DB\Exception
	 */
	public function testFindAllSeesTagsInSubfolderOfSharedFolder() {
		$owner = 'tag_share_owner';
		$recipient = 'tag_share_recipient';
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
		$this->treeMapper->move(
			Db\TreeMapper::TYPE_FOLDER,
			$subFolder->getId(),
			$sharedFolder->getId(),
		);

		$bookmark = Bookmark::fromArray([
			'userId' => $ownerId,
			'url' => 'https://example.org/issue-1982',
			'title' => 'Nested',
			'description' => '',
		]);
		$bookmark = $this->bookmarkMapper->insertOrUpdate($bookmark);
		$nestedTag = 'nested-shared-1982';
		$this->tagMapper->addTo([$nestedTag], $bookmark->getId());
		$this->treeMapper->addToFolders(
			Db\TreeMapper::TYPE_BOOKMARK,
			$bookmark->getId(),
			[$subFolder->getId()],
		);

		$folderService->createShare(
			$sharedFolder->getId(),
			$recipient,
			IShare::TYPE_USER,
			true,
			false,
		);

		$recipientTags = $this->tagMapper->findAll($recipientId);
		$this->assertContains(
			$nestedTag,
			$recipientTags,
			'Tag on a bookmark inside a subfolder of a shared folder must be visible to the sharee',
		);

		$recipientTagsWithCount = $this->tagMapper->findAllWithCount($recipientId);
		$matched = array_values(array_filter(
			$recipientTagsWithCount,
			static fn ($row) => $row['name'] === $nestedTag,
		));
		$this->assertCount(1, $matched);
		$this->assertEquals(1, (int)$matched[0]['count']);
	}

	/**
	 * @return array
	 */
	public function singleBookmarksProvider() {
		return array_map(function ($data) {
			return [$data[0], Db\Bookmark::fromArray($data[1])];
		}, [
			[['one'], ['url' => 'https://google.com/', 'title' => 'Google', 'description' => 'Search engine']],
			[['two'], ['url' => 'https://nextcloud.com/', 'title' => 'Nextcloud', 'description' => '']],
			[['three', 'one'], ['url' => 'https://php.net/', 'title' => '', 'description' => '']],
			[['two', 'four', 'one'], ['url' => 'https://de.wikipedia.org/wiki/%C3%9C', 'title' => '', 'description' => '']],
		]);
	}
}
