<?php
namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\QueryException;
use PHPUnit\Framework\TestCase;

class PublicFolderTest extends TestCase {

	/**
	 * @var Db\FolderMapper
	 */
	private $folderMapper;

	/**
	 * @var Db\PublicFolderMapper
	 */
	private $folderPublicMapper;

	/**
	 * @var \OCP\IUserManager
	 */
	private $userManager;

	/**
	 * @var \OCP\AppFramework\Db\Entity
	 */
	private $folder;

	/**
	 * @var \OCP\AppFramework\Db\Entity
	 */
	private $publicFolder;

	/**
	 * @var string
	 */
	private $userId;

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws QueryException
	 * @throws \OC\DatabaseException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
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
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_folders_public');
		$query->execute();

		$this->folderPublicMapper = \OC::$server->query(Db\PublicFolderMapper::class);
		$this->folderMapper = \OC::$server->query(Db\FolderMapper::class);

		$this->userManager = \OC::$server->getUserManager();
		$this->user = 'test';
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();

		$this->folder = Db\Folder::fromParams(['title' => 'test', 'parentFolder' => -1, 'userId' => $this->userId]);
		$this->folderMapper->insert($this->folder);

		$this->publicFolder = Db\PublicFolder::fromParams(['folderId' => $this->folder->getId()]);
		$this->folderPublicMapper->insert($this->publicFolder);
	}

	public function testCreateAndFind() {
		$f = $this->folderPublicMapper->find($this->publicFolder->getId());
		$this->assertSame($this->folder->getId(), $f->getFolderId());
	}

	public function testFindByFolder() {
		$f = $this->folderPublicMapper->findByFolder($this->folder->getId());
		$this->assertSame($this->folder->getId(), $f->getFolderId());
		$this->assertSame($this->publicFolder->getId(), $f->getId());
	}

	public function testFindCreatedBefore() {
		$f = $this->folderPublicMapper->findAllCreatedBefore(time());
		$this->assertSame($this->folder->getId(), $f[0]->getFolderId());
		$this->assertSame($this->publicFolder->getId(), $f[0]->getId());
	}
}
