<?php
/*
 * Copyright (c) 2022. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Tests;

use OC;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Service\LockManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;

class LockManagerTest extends TestCase {
	public string $user;
	public ITimeFactory $timeFactory;
	public LockManager $lockManager;
	public FolderMapper $folderMapper;

	protected function setUp(): void {
		parent::setUp();
		$this->user = 'test';
		$this->folderMapper = OC::$server->get(FolderMapper::class);
		$this->folderMapper->findRootFolder($this->user);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->lockManager = new LockManager(OC::$server->get(IDBConnection::class), $this->folderMapper, $this->timeFactory);
		$this->lockManager->setLock($this->user, false);
	}

	public function testLockUnlock(): void {
		$this->timeFactory->expects($this->atLeastOnce())->method('getDateTime')->willReturnCallback(fn ($arg) => new \DateTime($arg));
		$this->assertFalse($this->lockManager->getLock($this->user), 'should not be locked');
		$this->lockManager->setLock($this->user, true);
		$this->assertTrue($this->lockManager->getLock($this->user), 'should be locked');
		$this->lockManager->setLock($this->user, false);
		$this->assertFalse($this->lockManager->getLock($this->user), 'should not be locked');
	}

	public function testLockTimeout() {
		$this->assertFalse($this->lockManager->getLock($this->user), 'should not be locked');
		$startTime = new \DateTime();
		$startTime = $startTime->sub(new \DateInterval('PT31M'));
		$stub = $this->timeFactory->expects($this->atLeastOnce())->method('getDateTime');
		$stub->willReturnCallback(fn ($arg) => $startTime);
		$this->lockManager->setLock($this->user, true);
		$this->assertTrue($this->lockManager->getLock($this->user), 'should be locked');
		$stub->willReturnCallback(fn ($arg) => new \DateTime($arg));
		$this->assertFalse($this->lockManager->getLock($this->user), 'lock should have timed out');
	}
}
