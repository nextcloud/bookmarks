<?php


namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Exception\UnauthorizedAccessError;
use OCA\Bookmarks\Service;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\QueryException;
use OCP\User;


class HtmlImporterTest  extends TestCase {

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
	 * @throws QueryException
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->bookmarkMapper = \OC::$server->query(Db\BookmarkMapper::class);
		$this->tagMapper = \OC::$server->query(Db\TagMapper::class);
		$this->folderMapper = \OC::$server->query(Db\FolderMapper::class);
		$this->htmlImporter = \OC::$server->query(Service\HtmlImporter::class);
		$this->userId = User::getUser();
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
		print('testImportFile passed!');
		var_export(libxml_get_errors());
	}

	public function importProvider() {
		return [
			[
				__DIR__.'/res/import.file'
			]
		];
	}
}
