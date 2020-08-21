<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Controller\SettingsController;
use OCP\IConfig;
use OCP\IRequest;

/**
 * Class Test_SettingsController
 *
 * @group Controller
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
	/**
	 * @var \OC\User\Manager
	 */
	private $userManager;
	/**
	 * @var string
	 */
	private $user;

	protected function setUp(): void {
		parent::setUp();
		$this->cleanUp();

		$this->userManager = \OC::$server->getUserManager();
		$this->user = 'test';
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();

		$this->appName = 'bookmarks';
		$this->request = \OC::$server->getRequest();
		$userManager = \OC::$server->getUserManager();
		$l = \OC::$server->getL10N('bookmarks');
		if (!$userManager->userExists($this->userId)) {
			$userManager->createUser($this->userId, 'password');
		}
		$this->config = \OC::$server->getConfig();
		$this->controller = new SettingsController('bookmarks', $this->request, $this->userId, $this->config, $l);
	}

	/**
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function testGetSorting(): void {
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
	 *
	 */
	public function testSetSorting(): void {
		$output = $this->controller->setSorting('added'); //case: set a normal sorting option
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals('added', $this->config->getUserValue($this->userId, $this->appName, 'sorting', ''));
		$output = $this->controller->setSorting('foo'); //case: set an invalid sorting option
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
	}

	protected function tearDown(): void {
		$this->config->deleteUserValue($this->userId, $this->appName, 'sorting');
	}
}
