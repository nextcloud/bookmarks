<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Controller\Rest\SettingsController;
use \OCP\IConfig;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

/**
 * Class Test_SettingsController
 *
 * @group DB
 */
class Test_SettingsController extends TestCase {
	private $userId;
	private $appName;
	private $request;
	/** @var IConfig */
	private $config;
	/** @var SettingsController */
	private $controller;

	protected function setUp() {
		parent::setUp();

		$this->userId = "tuser";
		$this->appName = "bookmarks";
		$this->request = \OC::$server->getRequest();
		$userManager = \OC::$server->getUserManager();
		if (!$userManager->userExists($this->userId)) {
			$userManager->createUser($this->userId, 'password');
		}
		$this->config = \OC::$server->getConfig();
		$this->controller = new SettingsController("bookmarks", $this->request, $this->userId, $this->config);
	}

	/**
	 * @covers \OCA\Bookmarks\Controller\Rest\SettingsController::getSorting
	 */
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

	/**
	 * @covers \OCA\Bookmarks\Controller\Rest\SettingsController::setSorting
	 */
	public function testSetSorting() {
		$output = $this->controller->setSorting('added'); //case: set a normal sorting option
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals('added', $this->config->getUserValue($this->userId, $this->appName, 'sorting', ''));
		$output = $this->controller->setSorting('foo'); //case: set an invalid sorting option
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
	}

	protected function tearDown() {
		$this->config->deleteUserValue($this->userId, $this->appName, 'sorting');
	}
}
