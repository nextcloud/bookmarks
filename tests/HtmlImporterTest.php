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
	 * @param array $structure
	 * @throws UnauthorizedAccessError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testImportFile(string $file, array $structure) {
		$result = $this->htmlImporter->importFile($this->userId, $file);
		$this->assertEmpty($result['errors']);

		$this->assertEqualsCanonicalizing($structure, $result['imported']);

		$imported = $this->folderMapper->getRootChildren($this->userId);
		$this->assertEqualsCanonicalizing($structure, $imported);
	}

	public function importProvider() {
		return [
			[
				__DIR__.'/res/import.file',
				[
					['type' => 'folder', 'title' => '0', 'children' => [
						['type' => 'bookmark', 'title'  => 'Title 0', 'url' => 'http://url0.net/', 'tags' => ['tag0']],
						['type' => 'bookmark', 'title'  => 'Title 1', 'url' => 'http://url1.net/', 'tags' => ['tag0']],
					]],
					['type' => 'folder', 'title' => '3', 'children' => [
						['type' => 'bookmark', 'title'  => 'Title 3', 'url' => 'http://url3.net/', 'tags' => ['tag3']],
						['type' => 'bookmark', 'title'  => 'Title 4', 'url' => 'http://url4.net/', 'tags' => ['tag4']],
					]],
					['type' => 'folder', 'title' => '6', 'children' => [
						['type' => 'bookmark', 'title'  => 'Title 6', 'url' => 'http://url6.net/', 'tags' => ['tag6']],
						['type' => 'bookmark', 'title'  => 'Title 7', 'url' => 'http://url7.net/', 'tags' => ['tag7']],
					]],
					['type' => 'folder', 'title' => '9', 'children' => [
						['type' => 'bookmark', 'title'  => 'Title 9', 'url' => 'http://url9.net/', 'tags' => ['tag9']],
						['type' => 'bookmark', 'title'  => 'Title 10', 'url' => 'http://url10.net/', 'tags' => ['tag10']],
					]],
					['type' => 'folder', 'title' => '12', 'children' => [
						['type' => 'bookmark', 'title'  => 'Title 12', 'url' => 'http://url12.net/', 'tags' => ['tag12']],
						['type' => 'bookmark', 'title'  => 'Title 13', 'url' => 'http://url13.net/', 'tags' => ['tag13']],
					]],
				]
			]
		];
	}
}
