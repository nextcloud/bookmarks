<?php


namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;


class FolderMapperTest extends TestCase {

	/**
	 * @var Db\BookmarkMapper
	 */
	private $bookmarkMapper;

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

	protected function setUp() : void {
		parent::setUp();
		$this->cleanUp();

		$this->folderMapper = \OC::$server->query(Db\FolderMapper::class);

		$this->userManager = \OC::$server->getUserManager();
		$this->user = 'test';
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();
	}

	/**
	 * @doesNotPerformAssertions
	 * @return Entity
	 */
	public function testInsert(): Entity {
		$folder = new Db\Folder();
		$folder->setTitle('foobar');
		$folder->setUserId($this->userId);
		return $this->folderMapper->insert($folder);
	}

	/**
	 * @depends testInsert
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testUpdate(): void {
		$folder = $this->testInsert();

		$folder->setTitle('barbla');
		$this->folderMapper->update($folder);
		$foundEntity = $this->folderMapper->find($folder->getId());
		$this->assertSame($folder->getTitle(), $foundEntity->getTitle());
	}

	/**
	 * @depends testInsert
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testDelete(): void {
		$folder = $this->testInsert();

		$this->folderMapper->delete($folder);
		$this->expectException(DoesNotExistException::class);
		$this->folderMapper->find($folder->getId());
	}
}
