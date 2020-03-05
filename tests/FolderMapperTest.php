<?php


namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Bookmarks\QueryParameters;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use PHPUnit\Framework\TestCase;


class FolderMapperTest extends TestCase {

	/**
	 * @var Db\BookmarkMapper
	 */
	private $bookmarkMapper;

	/**
	 * @var Db\FolderMapper
	 */
	private $folderMapper;

	/**
	 * @var string
	 */
	private $userId;

	protected function setUp() : void {
		parent::setUp();

		$this->bookmarkMapper = \OC::$server->query(Db\BookmarkMapper::class);
		$this->folderMapper = \OC::$server->query(Db\FolderMapper::class);

		$this->userManager = \OC::$server->getUserManager();
		$this->user = 'test';
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();
	}

	/**
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCA\Bookmarks\Exception\UnsupportedOperation
	 */
	public function testInsertAndFind(): void {
		$folder = new Db\Folder();
		$folder->setTitle('foobar');
		$folder->setUserId($this->userId);
		$insertedFolder = $this->folderMapper->insert($folder);

		$rootFolder = $this->folderMapper->findRootFolder($this->userId);
		$this->folderMapper->move($insertedFolder->getId(), $rootFolder->getId());

		$foundEntity = $this->folderMapper->find($insertedFolder->getId());
		$this->assertSame($foundEntity->getTitle(), $foundEntity->getTitle());

		$parent = $this->folderMapper->findParentOfFolder($insertedFolder->getId());
		$this->assertSame($parent->getId(), $rootFolder->getId());
	}

	/**
	 * @depends testInsertAndFind
	 * @param Entity $folder
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testUpdate(Entity $folder): void {
		$folder->setTitle('barbla');
		$this->folderMapper->update($folder);
		$foundEntity = $this->folderMapper->find($folder->getId());
		$this->assertSame($folder->getTitle(), $foundEntity->getTitle());
	}

	/**
	 * @depends      testInsertAndFind
	 * @dataProvider singleBookmarksProvider
	 * @param Bookmark $bookmark
	 * @param Folder $folder
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws AlreadyExistsError
	 * @throws UserLimitExceededError
	 */
	public function testAddBookmarks(Bookmark $bookmark, Folder $folder): void {
		$bookmark->setUserId($this->userId);
		$insertedBookmark = $this->bookmarkMapper->insertOrUpdate($bookmark);
		$this->folderMapper->addToFolders($insertedBookmark->getId(), [$folder->getId()]);
		$bookmarks = $this->bookmarkMapper->findByFolder($folder->getId(), new QueryParameters());
		$this->assertContains($insertedBookmark->getId(), array_map(static function(Bookmark $bookmark) {
			return $bookmark->getId();
		}, $bookmarks));
	}

	/**
	 * @depends      testInsertAndFind
	 * @dataProvider singleBookmarksProvider
	 * @param Bookmark $bookmark
	 * @param Folder $folder
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws AlreadyExistsError
	 * @throws UserLimitExceededError
	 */
	public function testRemoveBookmarks(Bookmark $bookmark, Folder $folder): void {
		$bookmark->setUserId($this->userId);
		$insertedBookmark = $this->bookmarkMapper->insertOrUpdate($bookmark);
		$this->folderMapper->removeFromFolders($insertedBookmark->getId(), [$folder->getId()]);
		$bookmarks = $this->bookmarkMapper->findByFolder($folder->getId(), new QueryParameters());
		$this->assertNotContains($insertedBookmark->getId(), array_map(static function($bookmark) {
			return $bookmark->getId();
		}, $bookmarks));
	}

	/**
	 * @depends testInsertAndFind
	 * @param Entity $folder
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testDelete(Entity $folder): void {
		$this->folderMapper->delete($folder);
		$this->expectException(DoesNotExistException::class);
		$this->folderMapper->find($folder->getId());
	}

	/**
	 * @return array
	 */
	public function singleBookmarksProvider(): array {
		return array_map(static function($props) {
			return [Db\Bookmark::fromArray($props)];
		}, [
			'Simple URL with title and description' => ['url' => 'https://google.com/', 'title' => 'Google', 'description' => 'Search engine'],
			'Simple URL with title' => ['url' => 'https://nextcloud.com/', 'title' => 'Nextcloud'],
			'Simple URL' => ['url' => 'https://php.net/'],
			'URL with unicode' => ['url' => 'https://de.wikipedia.org/wiki/%C3%9C'],
		]);
	}
}
