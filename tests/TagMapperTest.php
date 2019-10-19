<?php


namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Db\Bookmark;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\QueryException;
use OCP\User;


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
	 * @throws QueryException
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->bookmarkMapper = \OC::$server->query(Db\BookmarkMapper::class);
		$this->tagMapper = \OC::$server->query(Db\TagMapper::class);
		$this->userId = User::getUser();
	}

	/**
	 * @dataProvider setOfBookmarksProvider
	 * @param array $bookmarks
	 */
	public function testAddToAndFind(array $bookmarks) {
		foreach($bookmarks as $bookmarkEntry) {
			$bookmarkEntry[1]->setUserId($this->userId);
			$bookmarkEntry[1] = $this->bookmarkMapper->insertOrUpdate($bookmarkEntry[1]);
			$this->tagMapper->addTo($bookmarkEntry[0], $bookmarkEntry[1]->getId());

			$tags = $this->tagMapper->findByBookmark($bookmarkEntry[1]->getId());
			foreach($bookmarkEntry[0] as $tag) {
				$this->assertContains($tag, $tags);
			}
		}
		$allTags = $this->tagMapper->findAll($this->userId);
		$this->assertContains('one', $allTags);
		$this->assertContains('two', $allTags);
		$this->assertContains('three', $allTags);
		$this->assertContains('four', $allTags);

		$allTagsWithCount = $this->tagMapper->findAllWithCount($this->userId);
		$this->assertContains(['tag' => 'one', 'nbr' => 3], $allTags);
		$this->assertContains(['tag' => 'two', 'nbr' => 2], $allTags);
		$this->assertContains(['tag' => 'three', 'nbr' => 1], $allTags);
		$this->assertContains(['tag' => 'four', 'nbr' => 1], $allTags);
	}

	/**
	 * @depends      testAddToAndFind
	 * @dataProvider setOfBookmarksProvider
	 * @param array $bookmarks
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testRemoveAllFrom(array $bookmarks) {
		$bookmark = $this->bookmarkMapper->findByUrl($this->userId, $bookmarks[2]->getUrl);
		$this->tagMapper->removeAllFrom($bookmark->getId());
		$tags = $this->tagMapper->findByBookmark($bookmark->getId());
		$this->assertEmpty($tags);
	}

	/**
	 * @depends      testRemoveAllFrom
	 * @dataProvider setOfBookmarksProvider
	 * @param array $bookmarks
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testSetOn(array $bookmarks) {
		$bookmark = $this->bookmarkMapper->findByUrl($this->userId, $bookmarks[3]->getUrl);
		$newTags = ['foo', 'bar'];
		$this->tagMapper->setOn($newTags, $bookmark->getId());
		$tags = $this->tagMapper->findByBookmark($bookmark->getId());
		foreach($tags as $tag) {
			$this->assertContains($tag, $newTags);
		}
	}

	/**
	 * @return array
	 */
	public function setOfBookmarksProvider() {
		return [
			array_map(function ($data) {
				return [$data[0], Db\Bookmark::fromArray($data[1])];
			}, [
				[['one'], ['url' => 'https://google.com/', 'title' => 'Google', 'description' => 'Search engine']],
				[['two'], ['url' => 'https://nextcloud.com/', 'title' => 'Nextcloud']],
				[['three', 'one'], ['url' => 'https://php.net/']],
				[['two', 'four', 'one'], ['url' => 'https://de.wikipedia.org/wiki/%C3%9C']],
			])
		];
	}
}
