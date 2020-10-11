<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\QueryException;
use OCP\IUserManager;

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
	 * @var Entity
	 */
	private $folder;

	/**
	 * @var Entity
	 */
	private $publicFolder;

	/**
	 * @var string
	 */
	private $userId;
	/**
	 * @var Db\TreeMapper
	 */
	private $treeMapper;
	/**
	 * @var string
	 */
	private $user;

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws QueryException
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->cleanUp();

		$this->folderPublicMapper = \OC::$server->get(Db\PublicFolderMapper::class);
		$this->folderMapper = \OC::$server->get(Db\FolderMapper::class);
		$this->treeMapper = \OC::$server->get(Db\TreeMapper::class);

		$this->userManager = \OC::$server->get(IUserManager::class);
		$this->user = 'test';
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();

		$this->folder = Db\Folder::fromParams(['title' => 'test', 'userId' => $this->userId]);
		$this->folderMapper->insert($this->folder);

		$this->publicFolder = Db\PublicFolder::fromParams(['folderId' => $this->folder->getId()]);
		$this->folderPublicMapper->insert($this->publicFolder);
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function testCreateAndFind(): void {
		$f = $this->folderPublicMapper->find($this->publicFolder->getId());
		$this->assertSame($this->folder->getId(), $f->getFolderId());
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function testFindByFolder(): void {
		$f = $this->folderPublicMapper->findByFolder($this->folder->getId());
		$this->assertSame($this->folder->getId(), $f->getFolderId());
		$this->assertSame($this->publicFolder->getId(), $f->getId());
	}

	public function testFindCreatedBefore(): void {
		$f = $this->folderPublicMapper->findAllCreatedBefore(time());
		$this->assertSame($this->folder->getId(), $f[0]->getFolderId());
		$this->assertSame($this->publicFolder->getId(), $f[0]->getId());
	}
}
