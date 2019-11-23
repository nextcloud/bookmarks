<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Controller\SettingsController;
use \OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Class Test_SettingsController
 *
 * @group DB
 */
class SettingsControllerTest extends TestCase {
	/**
	 * @var string
	 */
	private $userId;
	/**
	 * @var string
	 */
	private $appName;
	/**
	 * @var IRequest
	 */
	private $request;
	/** @var IConfig */
	private $config;
	/** @var SettingsController */
	private $controller;

	protected function setUp() : void {
		parent::setUp();

		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks');
		$query->execute();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_tags');
		$query->execute();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_folders');
		$query->execute();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_folders_bookmarks');
		$query->execute();

		$this->userManager = \OC::$server->getUserManager();
		$this->user = 'test';
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();

		$this->appName = "bookmarks";
		$this->request = \OC::$server->getRequest();
		$userManager = \OC::$server->getUserManager();
		if (!$userManager->userExists($this->userId)) {
			$userManager->createUser($this->userId, 'password');
		}
		$this->config = \OC::$server->getConfig();
		$this->controller = new SettingsController("bookmarks", $this->request, $this->userId, $this->config);
	}

	public function testGetSorting() {
		$this->config->setUserValue($this->userId, $this->appName, 'sorting', 'clickcount'); //case: user has a normal sorting option
		$output = $this->controller->getSorting();
		$data = $output->getData();
		$this->assertEquals('clickcount', $data['sorting']);
		$this->config->deleteUserValue($this->userId, $this->appName, 'sorting'); //case: user has no sorting option
		$output = $this->controller->getSorting();
		$data = $output->getData();
		$this->assertEquals('lastmodified', $data['sorting']); //returns default
	}

	public function testSetSorting() {
		$output = $this->controller->setSorting('added'); //case: set a normal sorting option
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals('added', $this->config->getUserValue($this->userId, $this->appName, 'sorting', ''));
		$output = $this->controller->setSorting('foo'); //case: set an invalid sorting option
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
	}

	protected function tearDown() : void {
		$this->config->deleteUserValue($this->userId, $this->appName, 'sorting');
	}
}
