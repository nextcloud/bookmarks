<?php


namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\QueryParameters;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\QueryException;
use OCP\IUserManager;
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

		$this->bookmarkMapper = \OC::$server->get(Db\BookmarkMapper::class);
		$this->tagMapper = \OC::$server->get(Db\TagMapper::class);
		$this->folderMapper = \OC::$server->get(Db\FolderMapper::class);

		$this->userManager = \OC::$server->get(IUserManager::class);
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
		$this->assertContains(['name' => 'one', 'count' => 3], $allTagsWithCount);
		$this->assertContains(['name' => 'two', 'count' => 2], $allTagsWithCount);
		$this->assertContains(['name' => 'three', 'count' => 1], $allTagsWithCount);
		$this->assertContains(['name' => 'four', 'count' => 1], $allTagsWithCount);
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
		$this->assertContains(['name' => 'one', 'count' => 3], $allTagsWithCount);
		$this->assertContains(['name' => 'two', 'count' => 2], $allTagsWithCount);
		$this->assertContains(['name' => 'three', 'count' => 1], $allTagsWithCount);
		$this->assertNotContains(['name' => 'four', 'count' => 1], $allTagsWithCount);
		$this->assertNotContains(['name' => 'four', 'count' => 0], $allTagsWithCount);
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
		$this->assertContains(['name' => 'two', 'count' => 2], $allTagsWithCount);
		$this->assertContains(['name' => 'three', 'count' => 1], $allTagsWithCount);
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
	 * @return array
	 */
	public function singleBookmarksProvider() {
		return array_map(function ($data) {
			return [$data[0], Db\Bookmark::fromArray($data[1])];
		}, [
			[['one'], ['url' => 'https://google.com/', 'title' => 'Google', 'description' => 'Search engine']],
			[['two'], ['url' => 'https://nextcloud.com/', 'title' => 'Nextcloud']],
			[['three', 'one'], ['url' => 'https://php.net/']],
			[['two', 'four', 'one'], ['url' => 'https://de.wikipedia.org/wiki/%C3%9C']],
		]);
	}
}
