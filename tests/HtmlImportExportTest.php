<?php


namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\HtmlParseError;
use OCA\Bookmarks\Exception\UnauthorizedAccessError;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Bookmarks\Service;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\QueryException;
use OCP\IUserManager;

class HtmlImportExportTest extends TestCase {

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
	 * @var int
	 */
	private $userId;

	/**
	 * @var Service\HtmlImporter
	 */
	protected $htmlImporter;
	/**
	 * @var \stdClass
	 */
	protected $htmlExporter;
	/**
	 * @var \OC\User\Manager
	 */
	private $userManager;
	/**
	 * @var string
	 */
	private $user;
	/**
	 * @var Db\TreeMapper
	 */
	private $treeMapper;

	/**
	 * @throws QueryException
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->cleanUp();

		$this->bookmarkMapper = \OC::$server->get(Db\BookmarkMapper::class);
		$this->tagMapper = \OC::$server->get(Db\TagMapper::class);
		$this->folderMapper = \OC::$server->get(Db\FolderMapper::class);
		$this->treeMapper = \OC::$server->get(Db\TreeMapper::class);
		$this->htmlImporter = \OC::$server->get(Service\HtmlImporter::class);
		$this->htmlExporter = \OC::$server->get(Service\HtmlExporter::class);

		$this->userManager = \OC::$server->get(IUserManager::class);
		$this->user = 'test';
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();
	}

	/**
	 * @dataProvider importProvider
	 * @param string $file
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnauthorizedAccessError
	 * @throws AlreadyExistsError
	 * @throws UserLimitExceededError
	 * @throws HtmlParseError
	 */
	public function testImportFile(string $file): void {
		$result = $this->htmlImporter->importFile($this->userId, $file);

		$rootFolder = $this->folderMapper->findRootFolder($this->userId);
		$imported = $this->treeMapper->getChildrenOrder($rootFolder->getId());
		$this->assertCount(6, $imported);

		$this->assertCount(2, $this->treeMapper->findChildren(Db\TreeMapper::TYPE_BOOKMARK, $result['imported'][0]['id']));
		$this->assertCount(2, $this->treeMapper->findChildren(Db\TreeMapper::TYPE_BOOKMARK, $result['imported'][1]['id']));
		$this->assertCount(2, $this->treeMapper->findChildren(Db\TreeMapper::TYPE_BOOKMARK, $result['imported'][2]['id']));
		$this->assertCount(2, $this->treeMapper->findChildren(Db\TreeMapper::TYPE_BOOKMARK, $result['imported'][3]['id']));
		$this->assertCount(1, $this->treeMapper->findChildren(Db\TreeMapper::TYPE_BOOKMARK, $result['imported'][4]['id']));

		/**
		 * @var $firstBookmark Db\Bookmark
		 */
		$firstBookmark = $this->bookmarkMapper->find($result['imported'][0]['children'][0]['id']);
		$this->assertSame('Title 0', $firstBookmark->getTitle());
		$this->assertSame('http://url0.net/', $firstBookmark->getUrl());
		$this->assertSame('This is a description.', $firstBookmark->getDescription());
		$this->assertEquals(['tag0'], $this->tagMapper->findByBookmark($firstBookmark->getId()));
		$this->assertEquals(1231231234, $firstBookmark->getAdded());
	}

	/**
	 * @dataProvider exportProvider
	 * @param array $bookmarks
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws AlreadyExistsError
	 * @throws UserLimitExceededError
	 */
	public function testExport(...$bookmarks): void {
		$rootFolder = new Db\Folder();
		$rootFolder->setTitle('Root');
		$rootFolder->setUserId($this->userId);
		$rootFolder = $this->folderMapper->insert($rootFolder);

		// Set up database
		for ($i = 0; $i < 4; $i++) {
			$f = new Db\Folder();
			$f->setTitle($i);
			$f->setUserId($this->userId);
			$f = $this->folderMapper->insert($f);
			$this->treeMapper->move(Db\TreeMapper::TYPE_FOLDER, $f->getId(), $rootFolder->getId());
			$b = array_shift($bookmarks);
			$b->setUserId($this->userId);
			$b = $this->bookmarkMapper->insertOrUpdate($b);
			$this->treeMapper->addToFolders(Db\TreeMapper::TYPE_BOOKMARK, $b->getId(), [$f->getId()]);
		}

		$exported = $this->htmlExporter->exportFolder($this->userId, $rootFolder->getId());

		$rootFolders = $this->treeMapper->findChildren(Db\TreeMapper::TYPE_FOLDER, $rootFolder->getId());
		$this->assertCount(4, $rootFolders);
		foreach ($rootFolders as $rootFolder) {
			foreach ($this->treeMapper->findChildren(Db\TreeMapper::TYPE_BOOKMARK, $rootFolder->getId()) as $bookmark) {
				$this->assertContains($bookmark->getUrl(), $exported);
			}
		}
	}

	public function importProvider(): array {
		return [
			[
				__DIR__ . '/res/import.file',
			],
		];
	}

	public function exportProvider(): array {
		return [
			array_map(function ($props) {
				return Db\Bookmark::fromArray($props);
			}, [
				['url' => 'https://google.com/', 'title' => 'Google', 'description' => 'Search engine'],
				['url' => 'https://nextcloud.com/', 'title' => 'Nextcloud'],
				['url' => 'https://php.net/'],
				['url' => 'https://de.wikipedia.org/wiki/%C3%9C'],
				['url' => 'https://github.com/nextcloud/bookmarks/projects/1'],
			]),
		];
	}
}
