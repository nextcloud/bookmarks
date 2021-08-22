<?php


namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\HtmlParseError;
use OCA\Bookmarks\Exception\UnauthorizedAccessError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Bookmarks\Migration\OrphanedTreeItemsRepairStep;
use OCA\Bookmarks\QueryParameters;
use OCA\Bookmarks\Service;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\QueryException;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Migration\IOutput;

class OrphanedTreeItemsRepairStepTest extends TestCase {

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
	 * @var IDBConnection
	 */
	private $db;
	/**
	 * @var OrphanedTreeItemsRepairStep
	 */
	private $repairStep;

	/**
	 * @throws QueryException
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->cleanUp();

		$this->repairStep = \OC::$server->get(OrphanedTreeItemsRepairStep::class);
		$this->db = \OC::$server->get(\OCP\IDBConnection::class);
		$this->treeMapper = \OC::$server->get(Db\TreeMapper::class);
		$this->bookmarkMapper = \OC::$server->get(Db\BookmarkMapper::class);
		$this->folderMapper = \OC::$server->get(Db\FolderMapper::class);
		$this->htmlImporter = \OC::$server->get(Service\HtmlImporter::class);

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
	 * @throws \OCP\DB\Exception
	 */
	public function testRepairTreeGone(string $file): void {
		$this->cleanUp();
		$result = $this->htmlImporter->importFile($this->userId, $file);
		/** @var Db\Folder $rootFolder */
		$rootFolder = $this->folderMapper->findRootFolder($this->userId);

		// check for 5 folders + 1 bookmark
		self::assertCount(6, $this->treeMapper->getChildren($rootFolder->getId()));

		// Remove tree structure
		$this->db->executeQuery('DELETE FROM oc_bookmarks_tree');

		// check for no children
		self::assertCount(0, $this->treeMapper->getChildren($rootFolder->getId()));

		// repair
		$output = $this->getMockBuilder(IOutput::class)->getMock();
		$this->repairStep->run($output);

		// check for 10 bookmarks
		self::assertCount(10, $this->treeMapper->getChildren($rootFolder->getId()));
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
	 * @throws \OCP\DB\Exception
	 */
	public function testNoRepair(string $file): void {
		$this->cleanUp();
		$result = $this->htmlImporter->importFile($this->userId, $file);
		/** @var Db\Folder $rootFolder */
		$rootFolder = $this->folderMapper->findRootFolder($this->userId);

		// check for 5 folders + 1 bookmark
		self::assertCount(6, $this->treeMapper->getChildren($rootFolder->getId()));

		// repair
		$output = $this->getMockBuilder(IOutput::class)->getMock();
		$this->repairStep->run($output);

		// check for 5 folders + 1 bookmark
		self::assertCount(6, $this->treeMapper->getChildren($rootFolder->getId()));

		// check for 10 bookmarks
		self::assertCount(10, $this->bookmarkMapper->findAll($this->userId, new QueryParameters()));
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
	 * @throws \OCP\DB\Exception
	 */
	public function testRepairRootFolderGone(string $file): void {
		$this->cleanUp();
		$result = $this->htmlImporter->importFile($this->userId, $file);
		/** @var Db\Folder $rootFolder */
		$rootFolder = $this->folderMapper->findRootFolder($this->userId);

		// check for 5 folders + 1 bookmark
		self::assertCount(6, $this->treeMapper->getChildren($rootFolder->getId()));

		// delete root folder but not root_folders entry
		$this->folderMapper->delete($rootFolder);

		// repair
		$output = $this->getMockBuilder(IOutput::class)->getMock();
		$this->repairStep->run($output);

		/** @var Db\Folder $rootFolder */
		$rootFolder = $this->folderMapper->findRootFolder($this->userId);

		// check for 5 folders + 1 bookmark
		self::assertCount(6, $this->treeMapper->getChildren($rootFolder->getId()));

		// check for 10 bookmarks
		self::assertCount(10, $this->bookmarkMapper->findAll($this->userId, new QueryParameters()));
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
	 * @throws \OCP\DB\Exception
	 */
	public function testRepairRootFolderGone2(string $file): void {
		$this->cleanUp();
		$result = $this->htmlImporter->importFile($this->userId, $file);
		/** @var Db\Folder $rootFolder */
		$rootFolder = $this->folderMapper->findRootFolder($this->userId);

		// check for 5 folders + 1 bookmark
		self::assertCount(6, $this->treeMapper->getChildren($rootFolder->getId()));

		// delete root folder and root_folders entry
		$this->folderMapper->delete($rootFolder);
		$qb = $this->db->getQueryBuilder();
		$qb->delete('bookmarks_root_folders', 'r')->where($qb->expr()->eq('r.folder_id', $qb->createNamedParameter($rootFolder->getId())));

		// repair
		$output = $this->getMockBuilder(IOutput::class)->getMock();
		$this->repairStep->run($output);

		/** @var Db\Folder $rootFolder */
		$rootFolder = $this->folderMapper->findRootFolder($this->userId);

		// check for 5 folders + 1 bookmark
		self::assertCount(6, $this->treeMapper->getChildren($rootFolder->getId()));

		// check for 10 bookmarks
		self::assertCount(10, $this->bookmarkMapper->findAll($this->userId, new QueryParameters()));
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
	 * @throws \OCP\DB\Exception
	 * @throws \OCA\Bookmarks\Exception\UrlParseError
	 */
	public function testRepairFoldersGone(string $file): void {
		$this->cleanUp();
		$result = $this->htmlImporter->importFile($this->userId, $file);
		/** @var Db\Folder $rootFolder */
		$rootFolder = $this->folderMapper->findRootFolder($this->userId);

		// check for 5 folders + 1 bookmark
		self::assertCount(6, $this->treeMapper->getChildren($rootFolder->getId()));

		// delete root folder and root_folders entry
		$this->folderMapper->delete($rootFolder);
		$qb = $this->db->getQueryBuilder();
		$qb->delete('folders', 'r')->where($qb->expr()->neq('r.folder_id', $qb->createNamedParameter($rootFolder->getId())));

		// repair
		$output = $this->getMockBuilder(IOutput::class)->getMock();
		$this->repairStep->run($output);

		/** @var Db\Folder $rootFolder */
		$rootFolder = $this->folderMapper->findRootFolder($this->userId);

		// check for 10 bookmarks
		self::assertCount(6, $this->treeMapper->getChildren($rootFolder->getId()));

		// check for 10 bookmarks
		self::assertCount(10, $this->bookmarkMapper->findAll($this->userId, new QueryParameters()));
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
	 * @throws \OCP\DB\Exception
	 */
	public function testRepairBookmarksGone(string $file): void {
		$this->cleanUp();
		$result = $this->htmlImporter->importFile($this->userId, $file);
		/** @var Db\Folder $rootFolder */
		$rootFolder = $this->folderMapper->findRootFolder($this->userId);

		// check for 5 folders + 1 bookmark
		self::assertCount(6, $this->treeMapper->getChildren($rootFolder->getId()));

		// delete root folder and root_folders entry
		$this->bookmarkMapper->delete($this->bookmarkMapper->find($this->treeMapper->getChildren($rootFolder->getId(), -1)[0]['children'][0]['id']));
		$this->bookmarkMapper->delete($this->bookmarkMapper->find($this->treeMapper->getChildren($rootFolder->getId(), -1)[5]['id']));

		// repair
		$output = $this->getMockBuilder(IOutput::class)->getMock();
		$this->repairStep->run($output);

		/** @var Db\Folder $rootFolder */
		$rootFolder = $this->folderMapper->findRootFolder($this->userId);

		// check for 5 folders
		self::assertCount(5, $this->treeMapper->getChildren($rootFolder->getId()));

		// check for 10 bookmarks
		self::assertCount(8, $this->bookmarkMapper->findAll($this->userId, new QueryParameters()));
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
	 * @throws \OCP\DB\Exception
	 */
	public function testRepairFolderGone(string $file): void {
		$this->cleanUp();
		$result = $this->htmlImporter->importFile($this->userId, $file);
		/** @var Db\Folder $rootFolder */
		$rootFolder = $this->folderMapper->findRootFolder($this->userId);

		// check for 5 folders + 1 bookmark
		self::assertCount(6, $this->treeMapper->getChildren($rootFolder->getId()));

		// delete root folder and root_folders entry
		$this->folderMapper->delete($this->folderMapper->find($this->treeMapper->getChildren($rootFolder->getId())[0]['id']));

		// repair
		$output = $this->getMockBuilder(IOutput::class)->getMock();
		$this->repairStep->run($output);

		/** @var Db\Folder $rootFolder */
		$rootFolder = $this->folderMapper->findRootFolder($this->userId);

		// check for 4 folders + 1 bookmarks + 2 bookmarks reinserted
		self::assertCount(7, $this->treeMapper->getChildren($rootFolder->getId()));

		// check for 10 bookmarks
		self::assertCount(10, $this->bookmarkMapper->findAll($this->userId, new QueryParameters()));
	}

	public function importProvider(): array {
		return [
			[
				__DIR__ . '/res/import.file',
			],
		];
	}
}
