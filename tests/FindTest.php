<?php
namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Bookmarks\QueryParameters;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\QueryException;


class FindTest extends TestCase {

	/**
	 * @var Db\BookmarkMapper
	 */
	private $bookmarkMapper;

	/**
	 * @var Db\TagMapper
	 */
	private $tagMapper;

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
	 * @throws MultipleObjectsReturnedException
	 * @throws QueryException
	 * @throws UrlParseError
	 * @throws AlreadyExistsError
	 * @throws UserLimitExceededError
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->cleanUp();

		$this->bookmarkMapper = \OC::$server->query(Db\BookmarkMapper::class);
		$this->tagMapper = \OC::$server->query(Db\TagMapper::class);
		$this->folderMapper = \OC::$server->query(Db\FolderMapper::class);

		$this->userManager = \OC::$server->getUserManager();
		$this->user = 'test';
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();

		foreach($this->singleBookmarksProvider() as $bookmarkEntry) {
			$bookmarkEntry[1]->setUserId($this->userId);
			$bookmark = $this->bookmarkMapper->insertOrUpdate($bookmarkEntry[1]);
			$this->tagMapper->addTo($bookmarkEntry[0], $bookmark->getId());
		}
	}

	public function testFindAll() {
		$bookmarks = $this->bookmarkMapper->findAll($this->userId, ['wikipedia'], new QueryParameters());
		$this->assertCount(1, $bookmarks);
	}


	public function testFindAllWithAnd() {
		$bookmarks = $this->bookmarkMapper->findAll($this->userId, ['wikipedia', 'nextcloud'], new QueryParameters());
		$this->assertCount(0, $bookmarks);

		$bookmarks = $this->bookmarkMapper->findAll($this->userId, ['.com'], new QueryParameters());
		$this->assertCount(2, $bookmarks);
	}


	public function testFindAllWithOr() {
		$params = new QueryParameters();
		$bookmarks = $this->bookmarkMapper->findAll($this->userId, ['wikipedia', 'nextcloud'], $params->setConjunction(QueryParameters::CONJ_OR));
		$this->assertCount(2, $bookmarks);
	}

	public function testFindByTag() {
		$bookmarks = $this->bookmarkMapper->findByTag($this->userId, 'one', new QueryParameters());
		$this->assertCount(3, $bookmarks);
	}

	public function testFindByTags() {
		$bookmarks = $this->bookmarkMapper->findByTags($this->userId, ['one', 'three'], new QueryParameters());
		$this->assertCount(1, $bookmarks);
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
