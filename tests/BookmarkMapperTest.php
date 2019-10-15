<?php


namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Db\Bookmark;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;


class BookmarkMapperTest extends TestCase {

	/**
	 * @var mixed|Db\BookmarkMapper
	 */
	private $bookmarkMapper;

	/**
	 * @var string
	 */
	private $userId;

	protected function setUp() {
		parent::setUp();
		$this->bookmarkMapper = \OC::$server->query(Db\BookmarkMapper::class);
	}

	/**
	 * @dataProvider singleBookmarks
	 * @param Entity $bookmark
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testInsertAndFind(Entity $bookmark) {
		$bookmark = $this->bookmarkMapper->insert($bookmark);
		$foundEntity = $this->bookmarkMapper->find($bookmark->getUserId(), $bookmark->getId());
		$this->assertSame($bookmark->getUrl(), $foundEntity->getUrl());
		$this->assertSame($bookmark->getTitle(), $foundEntity->getTitle());
		$this->assertSame($bookmark->getDescription(), $foundEntity->getDescription());
	}

	/**
	 * @depends testInsertAndFind
	 * @dataProvider singleBookmarks
	 * @param Entity $bookmark
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testFindByUrl(Entity $bookmark) {
		$foundEntity = $this->bookmarkMapper->findByUrl($bookmark->getUserId(), $bookmark->getUrl());
		$this->assertSame($bookmark->getUrl(), $foundEntity->getUrl());
	}

	/**
	 * @depends testInsertAndFind
	 * @depends testFindByUrl
	 * @dataProvider singleBookmarks
	 * @param Entity $bookmark
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testDelete(Entity $bookmark) {
		$foundEntity = $this->bookmarkMapper->findByUrl($bookmark->getUserId(), $bookmark->getUrl());
		$this->bookmarkMapper->delete($foundEntity);
		$this->expectException(DoesNotExistException::class);
		$this->bookmarkMapper->find($foundEntity->getId());
	}

	/**
	 * @return array
	 */
	public function singleBookmarksProvider() {
		return array_map(function($props) {
			return Db\Bookmark::fromArray($props);
		}, [
			'Simple URL with title and description' => ['url' => 'https://google.com/', 'title' => 'Google', 'description' => 'Search engine', 'userId' => $this->userId],
			'Simple URL with title' => ['url' => 'https://nextcloud.com/', 'title' => 'Nextcloud', 'userId' => $this->userId],
			'Simple URL' => ['url' => 'https://php.net/', 'userId' => $this->userId],
		]);
	}
}
