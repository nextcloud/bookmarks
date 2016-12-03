<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Controller\Rest\PublicController;
use OCA\Bookmarks\Controller\Lib\Bookmarks;

/**
 * Class Test_PublicController_Bookmarks
 *
 * @group DB
 */
class Test_PublicController_Bookmarks extends TestCase {

	/** @var  Bookmarks */
	protected $libBookmarks;
	private $userid;
	private $request;
	private $db;
	private $userManager;
	/** @var  PublicController */
	private $publicController;

	protected function setUp() {
		parent::setUp();

		$this->userid = "testuser";
		$this->request = \OC::$server->getRequest();
		$this->db = \OC::$server->getDb();
		$this->userManager = \OC::$server->getUserManager();

		$config = \OC::$server->getConfig();
		$l = \OC::$server->getL10N('bookmarks');
		$clientService = \OC::$server->getHTTPClientService();
		$logger = \OC::$server->getLogger();
		$this->libBookmarks = new Bookmarks($this->db, $config, $l, $clientService, $logger);

		$this->publicController = new PublicController("bookmarks", $this->request, $this->libBookmarks, $this->userManager);
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

	function testPublicQuery() {

		$this->libBookmarks->addBookmark($this->userid, "http://www.golem.de", "Golem", array("four"), "PublicNoTag", true);
		$this->libBookmarks->addBookmark($this->userid, "http://www.9gag.com", "9gag", array("two", "three"), "PublicTag", true);

		$output = $this->publicController->returnAsJson($this->userid);
		$data = $output->getData();
		$this->assertEquals(2, count($data));
		
		$this->cleanDB();
	}

	function cleanDB() {
		$query1 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks');
		$query1->execute();
		$query2 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_tags');
		$query2->execute();
	}

}
