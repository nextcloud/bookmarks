<?php


namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Exception\UnauthorizedAccessError;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Service;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\QueryException;
use PHPUnit\Framework\TestCase;


class HtmlImportExportTest  extends TestCase {

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
	 * @throws QueryException
	 */
	protected function setUp(): void {
		parent::setUp();

		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks');
		$query->execute();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_tags');
		$query->execute();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_folders');
		$query->execute();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_folders_bookmarks');
		$query->execute();

		$this->bookmarkMapper = \OC::$server->query(Db\BookmarkMapper::class);
		$this->tagMapper = \OC::$server->query(Db\TagMapper::class);
		$this->folderMapper = \OC::$server->query(Db\FolderMapper::class);
		$this->htmlImporter = \OC::$server->query(Service\HtmlImporter::class);
		$this->htmlExporter = \OC::$server->query(Service\HtmlExporter::class);

		$this->userManager = \OC::$server->getUserManager();
		$this->user = 'test';
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();

		$this->folderMapper->deleteAll($this->userId);
	}

	/**
	 * @dataProvider importProvider
	 * @param string $file
	 * @throws UnauthorizedAccessError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testImportFile(string $file) {
		$result = $this->htmlImporter->importFile($this->userId, $file);

		$imported = $this->folderMapper->getRootChildren($this->userId);
		$this->assertCount(5, $imported);

		$this->assertCount(2, $this->bookmarkMapper->findByFolder($result['imported'][0]['id']));
		$this->assertCount(2, $this->bookmarkMapper->findByFolder($result['imported'][1]['id']));
		$this->assertCount(2, $this->bookmarkMapper->findByFolder($result['imported'][2]['id']));
		$this->assertCount(2, $this->bookmarkMapper->findByFolder($result['imported'][3]['id']));
		$this->assertCount(2, $this->bookmarkMapper->findByFolder($result['imported'][4]['id']));

		$firstBookmark = $this->bookmarkMapper->find($result['imported'][0]['children'][0]['id']);
		$this->assertSame('Title 0', $firstBookmark->getTitle());
		$this->assertSame('http://url0.net/', $firstBookmark->getUrl());
		$this->assertEquals(['tag0'], $this->tagMapper->findByBookmark($firstBookmark->getId()));
	}

	/**
	 * @dataProvider exportProvider
	 * @param array $bookmarks
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UnauthorizedAccessError
	 * @throws UrlParseError
	 * @throws \OCA\Bookmarks\Exception\AlreadyExistsError
	 * @throws \OCA\Bookmarks\Exception\UserLimitExceededError
	 */
	public function testExport(...$bookmarks) {
		// Set up database
		for($i=0; $i < 4; $i++) {
			$f = new Db\Folder();
			$f->setTitle($i);
			$f->setParentFolder(-1);
			$f->setUserId($this->userId);
			$f = $this->folderMapper->insert($f);
			$b = array_shift($bookmarks);
			$b->setUserId($this->userId);
			$b = $this->bookmarkMapper->insertOrUpdate($b);
			$this->folderMapper->addToFolders($b->getId(), [$f->getId()]);
		}

		$exported = $this->htmlExporter->exportFolder($this->userId, -1);

		$rootFolders = $this->folderMapper->getRootChildren($this->userId);
		$this->assertCount(4, $rootFolders);
		foreach($rootFolders as $rootFolder) {
			foreach($this->bookmarkMapper->findByFolder($rootFolder['id']) as $bookmark) {
				$this->assertContains($bookmark->getUrl(), $exported);
			}
		}
	}

	public function importProvider() {
		return [
			[
				__DIR__.'/res/import.file'
			]
		];
	}

	public function exportProvider() {
		return [
			array_map(function($props) {
				return Db\Bookmark::fromArray($props);
			}, [
				['url' => 'https://google.com/', 'title' => 'Google', 'description' => 'Search engine'],
				['url' => 'https://nextcloud.com/', 'title' => 'Nextcloud'],
				['url' => 'https://php.net/'],
				['url' => 'https://de.wikipedia.org/wiki/%C3%9C'],
				['url' => 'https://github.com/nextcloud/bookmarks/projects/1'],
			])
		];
	}
}
