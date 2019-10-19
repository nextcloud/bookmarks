<?php


namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Db\Bookmark;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\User;


class BookmarkMapperTest extends TestCase {

	/**
	 * @var mixed|Db\BookmarkMapper
	 */
	private $bookmarkMapper;

	/**
	 * @var string
	 */
	private $userId;

	protected function setUp() : void {
		parent::setUp();
		$this->bookmarkMapper = \OC::$server->query(Db\BookmarkMapper::class);
		$this->userId = User::getUser();
	}

	/**
	 * @dataProvider singleBookmarksProvider
	 * @param Entity $bookmark
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testInsertAndFind(Entity $bookmark) {
		$bookmark->setUserId($this->userId);
		$bookmark = $this->bookmarkMapper->insert($bookmark);
		$foundEntity = $this->bookmarkMapper->find($bookmark->getId());
		$this->assertSame($bookmark->getUrl(), $foundEntity->getUrl());
		$this->assertSame($bookmark->getTitle(), $foundEntity->getTitle());
		$this->assertSame($bookmark->getDescription(), $foundEntity->getDescription());
	}

	/**
	 * @depends testInsertAndFind
	 * @dataProvider singleBookmarksProvider
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
	 * @dataProvider singleBookmarksProvider
	 * @param Entity $bookmark
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testUpdate(Entity $bookmark) {
		$entity = $this->bookmarkMapper->findByUrl($bookmark->getUserId(), $bookmark->getUrl());
		$entity->setTitle('foobar');
		$this->bookmarkMapper->update($entity);
		$foundEntity = $this->bookmarkMapper->find($entity->getId());
		$this->assertSame($entity->title, $foundEntity->title);
	}

	/**
	 * @depends testInsertAndFind
	 * @depends testFindByUrl
	 * @dataProvider singleBookmarksProvider
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
			'Simple URL with title and description' => ['url' => 'https://google.com/', 'title' => 'Google', 'description' => 'Search engine'],
			'Simple URL with title' => ['url' => 'https://nextcloud.com/', 'title' => 'Nextcloud'],
			'Simple URL' => ['url' => 'https://php.net/'],
			'URL with unicode' => ['url' => 'https://de.wikipedia.org/wiki/Ü'],
		]);
	}
}
