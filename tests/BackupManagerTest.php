<?php

namespace OCA\Bookmarks\Tests;

use DateTime;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Bookmarks\Service\BackupManager;
use OCA\Bookmarks\Service\BookmarkService;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Utility\ITimeFactory;

class BackupManagerTest extends TestCase {

	/**
	 * @var BookmarkService
	 */
	private $bookmarks;

	/**
	 * @var BackupManager
	 */
	private $backupManager;

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
	 * @throws UrlParseError
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCA\Bookmarks\Exception\UnsupportedOperation
	 * @throws AlreadyExistsError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws UserLimitExceededError
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->cleanUp();

		$this->bookmarks = \OC::$server->query(BookmarkService::class);
		$this->backupManager = \OC::$server->query(BackupManager::class);
		$this->time = $this->createStub(ITimeFactory::class);
		$this->backupManager->injectTimeFactory($this->time);

		$this->userManager = \OC::$server->getUserManager();
		$this->user = 'test';
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();

		$this->bookmarks->create($this->userId, 'https://en.wikipedia.org/');
		$this->backupManager->cleanupAllBackups($this->userId);
	}

	public function testOneDay() {
		$this->time->method('getDateTime')->willReturn(new DateTime());
		$this->assertEquals(false, $this->backupManager->backupExistsForToday($this->userId));
		$this->backupManager->runBackup($this->userId);
		$this->assertEquals(true, $this->backupManager->backupExistsForToday($this->userId));
	}

	public function testSixMonths() {
		$today = new DateTime();
		$today->modify('first day of');
		$sixMonths = \DateTimeImmutable::createFromMutable($today);
		$sixMonths = $sixMonths->add(new \DateInterval('P7M'));
		for ($i = 0; $today->diff($sixMonths)->days !== 0; $i++) {
			$this->time->method('getDateTime')->willReturn($today);
			$this->backupManager->runBackup($this->userId);
			$this->backupManager->cleanupOldBackups($this->userId);
			$today->add(new \DateInterval('P1D'));
		}
		$backupFolder = $this->backupManager->getBackupFolder($this->userId);
		$nodes = $backupFolder->getDirectoryListing();
		$backups = array_filter($nodes, function ($node) {
			return str_ends_with($node->getName(), '.html');
		});
		/*var_dump(array_map(function ($node) {
			return $node->getName();
		}, $backups));*/
		$this->assertEqualsWithDelta(count($backups), 7 + 5 + 5, 2);
	}
}
