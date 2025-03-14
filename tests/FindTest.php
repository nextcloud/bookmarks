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
	 * @var Db\TreeMapper
	 */
	private $treeMapper;

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
		$this->treeMapper = \OC::$server->query(Db\TreeMapper::class);

		$this->userManager = \OC::$server->getUserManager();
		$this->user = 'test';
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();
		$rootFolder = $this->folderMapper->findRootFolder($this->userId);

		foreach ($this->singleBookmarksProvider() as $bookmarkEntry) {
			$bookmarkEntry[1]->setUserId($this->userId);
			$bookmark = $this->bookmarkMapper->insertOrUpdate($bookmarkEntry[1]);
			$this->tagMapper->addTo($bookmarkEntry[0], $bookmark->getId());
			$this->treeMapper->addToFolders(Db\TreeMapper::TYPE_BOOKMARK, $bookmark->getId(), [$rootFolder->getId()]);
		}
	}

	public function testFindAll() {
		$params = new QueryParameters();
		$bookmarks = $this->bookmarkMapper->findAll($this->userId, $params->setSearch(['wikipedia']));
		$this->assertCount(1, $bookmarks);
	}


	public function testFindAllWithAnd() {
		$params = new QueryParameters();
		$bookmarks = $this->bookmarkMapper->findAll($this->userId, $params->setSearch(['wikipedia', 'nextcloud']));
		$this->assertCount(0, $bookmarks);

		$params = new QueryParameters();
		$bookmarks = $this->bookmarkMapper->findAll($this->userId, $params->setSearch(['.com']));
		$this->assertCount(3, $bookmarks);
	}


	public function testFindAllWithOr() {
		$params = new QueryParameters();
		$bookmarks = $this->bookmarkMapper->findAll($this->userId, $params->setSearch(['wikipedia', 'nextcloud'])->setConjunction(QueryParameters::CONJ_OR));
		$this->assertCount(3, $bookmarks);
	}

	public function testFindByTags() {
		$params = new QueryParameters();
		$bookmarks = $this->bookmarkMapper->findAll($this->userId, $params->setTags(['one', 'three']));
		$this->assertCount(1, $bookmarks);
	}

	public function testFindByTagsAndSearch() {
		$params = new QueryParameters();
		$bookmarks = $this->bookmarkMapper->findAll($this->userId, $params->setTags(['one'])->setSearch(['php']));
		$this->assertCount(1, $bookmarks);
	}

	public function testFindByFolderSortTitle() {
		$rootFolder = $this->folderMapper->findRootFolder($this->userId);
		$params = new QueryParameters();
		$bookmarks = $this->bookmarkMapper->findAll($this->userId,
			$params
				->setFolder($rootFolder->getId())
				->setSortBy('title')
				->setSoftDeleted(false)
				->setSoftDeletedFolders(false)
				->setConjunction('or')
				->setLimit(100)
				->setOffset(0)
				->setUntagged(false)
				->setTags([])
				->setSearch([])
		);
		$this->assertCount(5, $bookmarks);
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
			[[],['url' => 'https://github.com/nextcloud/bookmarks/projects/1', 'title' => '', 'description' => '']],
		]);
	}
}
