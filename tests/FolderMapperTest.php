<?php


namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\Folder;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\User;


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
		$this->userId = User::getUser();
	}

	/**
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testInsertAndFind() {
		$folder = new Db\Folder();
		$folder->setTitle('foobar');
		$folder->setParentFolder(-1);
		$folder->setUserId($this->userId);
		$insertedFolder = $this->folderMapper->insert($folder);
		$foundEntity = $this->folderMapper->find($insertedFolder->getId());
		$this->assertSame($foundEntity->getTitle(), $foundEntity->getTitle());
		$this->assertSame($foundEntity->getParentFolder(), $foundEntity->getParentFolder());
		return $insertedFolder;
	}

	/**
	 * @depends testInsertAndFind
	 * @param Entity $folder
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testUpdate(Entity $folder) {
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
	 */
	public function testAddBookmarks(Bookmark $bookmark, Folder $folder) {
		$bookmark->setUserId($this->userId);
		$insertedBookmark = $this->bookmarkMapper->insertOrUpdate($bookmark);
		$this->folderMapper->addToFolders($insertedBookmark->getId(), [$folder->getId()]);
		$bookmarks = $this->bookmarkMapper->findByFolder($folder->getId());
		$this->assertContains($insertedBookmark->getId(), array_map(function($bookmark) {
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
	 */
	public function testRemoveBookmarks(Bookmark $bookmark, Folder $folder) {
		$bookmark->setUserId($this->userId);
		$insertedBookmark = $this->bookmarkMapper->insertOrUpdate($bookmark);
		$this->folderMapper->removeFromFolders($insertedBookmark->getId(), [$folder->getId()]);
		$bookmarks = $this->bookmarkMapper->findByFolder($folder->getId());
		$this->assertNotContains($insertedBookmark->getId(), array_map(function($bookmark) {
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
	public function testDelete(Entity $folder) {
		$this->folderMapper->delete($folder);
		$this->expectException(DoesNotExistException::class);
		$this->folderMapper->find($folder->getId());
	}


	/**
	 * @return array
	 */
	public function singleBookmarksProvider() {
		return array_map(function($props) {
			return [Db\Bookmark::fromArray($props)];
		}, [
			'Simple URL with title and description' => ['url' => 'https://google.com/', 'title' => 'Google', 'description' => 'Search engine'],
			'Simple URL with title' => ['url' => 'https://nextcloud.com/', 'title' => 'Nextcloud'],
			'Simple URL' => ['url' => 'https://php.net/'],
			'URL with unicode' => ['url' => 'https://de.wikipedia.org/wiki/%C3%9C'],
		]);
	}
}
