<?php

OC_App::loadApp('bookmarks');

use \OCA\Bookmarks\Controller\Rest\PublicController;

class Test_PublicController_Bookmarks extends PHPUnit_Framework_TestCase {

	private $userid;
	private $request;
	private $db;
	private $userManager;
	private $publicController;

	protected function setUp() {
		$this->userid = "testuser";
		$this->request = \OC::$server->getRequest();
		$this->db = \OC::$server->getDb();
		$this->userManager = \OC::$server->getUserManager();
		$this->publicController = new PublicController("bookmarks", $this->request, $this->db, $this->userManager);
	}

	function testPublicQueryNoUser() {
		$output = $this->publicController->returnAsJson(null, "apassword", null);
		$data = $output->getData();
		$status = $data['status'];
                $this->assertEquals($status, 'error');
	}

	function testPublicQueryWrongUser() {
		$output = $this->publicController->returnAsJson("cqc43dr4rx3x4xatr4", "apassword", null);
		$data = $output->getData();
		$status = $data['status'];
                $this->assertEquals($status, 'error');
	}

}
