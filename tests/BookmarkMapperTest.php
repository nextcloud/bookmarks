<?php


namespace OCA\Bookmarks\Tests;

use OC;
use OCA\Bookmarks\Db;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IUserManager;

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
		$this->bookmarkMapper = OC::$server->get(Db\BookmarkMapper::class);
		$this->treeMapper = OC::$server->get(Db\TreeMapper::class);
		$this->folderMapper = OC::$server->get(Db\FolderMapper::class);

		$this->userManager = OC::$server->get(IUserManager::class);
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
		$this->assertSame((string) $bookmark->getTitle(), (string) $foundEntity->getTitle());
		$this->assertSame((string) $bookmark->getDescription(), (string) $foundEntity->getDescription());
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
		$this->assertSame((string) $entity->getTitle(), (string) $foundEntity->getTitle());
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
	 * @return array
	 */
	public function singleBookmarksProvider(): array {
		return array_map(static function ($props) {
			return [Db\Bookmark::fromArray($props)];
		}, [
			'Simple URL with title and description' => ['url' => 'https://google.com/', 'title' => 'Google', 'description' => 'Search engine'],
			'Simple URL with title' => ['url' => 'https://nextcloud.com/', 'title' => 'Nextcloud'],
			'Simple URL' => ['url' => 'https://php.net/'],
			'URL with unicode' => ['url' => 'https://de.wikipedia.org/wiki/%C3%9C'],
		]);
	}
}
