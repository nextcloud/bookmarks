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

	/** @var	Bookmarks */
	protected $libBookmarks;
	private $userid;
	private $request;
	private $db;
	private $userManager;
	/** @var	PublicController */
	private $publicController;

	protected function setUp() {
		parent::setUp();

		$this->userid = "testuser";
		$this->request = \OC::$server->getRequest();
		$this->db = \OC::$server->getDatabaseConnection();
		$this->userManager = \OC::$server->getUserManager();
		if (!$this->userManager->userExists($this->userid)) {
			$this->userManager->createUser($this->userid, 'password');	
		}

		$config = \OC::$server->getConfig();
		$l = \OC::$server->getL10N('bookmarks');
		$clientService = \OC::$server->getHTTPClientService();
		$logger = \OC::$server->getLogger();
		$this->libBookmarks = new Bookmarks($this->db, $config, $l, $clientService, $logger);

		$this->publicController = new PublicController("bookmarks", $this->request, $this->userid, $this->libBookmarks, $this->userManager);
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
	}
	
	function testPublicCreate() {
		$this->publicController->newBookmark("http://www.heise.de", array("tags"=> array("four")), "Heise", true, "PublicNoTag");
		
		// the bookmark should exist
		$this->assertNotEquals(false, $this->libBookmarks->bookmarkExists("http://www.heise.de", $this->userid));

    // public should see this bookmark
    $output = $this->publicController->returnAsJson($this->userid);
		$data = $output->getData();
		$this->assertEquals(3, count($data));
	}
	
	function testPrivateCreate() {
		$this->publicController->newBookmark("http://www.private-heise.de", array("tags"=> array("four")), "Heise", false, "PublicNoTag");
		
		// the bookmark should exist
		$this->assertNotEquals(false, $this->libBookmarks->bookmarkExists("http://www.private-heise.de", $this->userid));

		// public should not see this bookmark
		$output = $this->publicController->returnAsJson($this->userid);
		$data = $output->getData();
		$this->assertEquals(3, count($data));
	}
	
	function testPrivateEditBookmark() {
		$id = $this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Golem", array("four"), "PublicNoTag", true);

		$this->publicController->editBookmark($id, 'https://www.heise.de');
		
		$bookmark = $this->libBookmarks->findUniqueBookmark($id, $this->userid);
		$this->assertEquals("https://www.heise.de", $bookmark['url']);
	}
	
	function testPrivateDeleteBookmark() {
		$id = $this->libBookmarks->addBookmark($this->userid, "http://www.google.com", "Heise", array("one", "two"), "PrivatTag", false);
		$this->assertNotEquals(false, $this->libBookmarks->bookmarkExists("http://www.google.com", $this->userid));
		$this->publicController->deleteBookmark($id);
		$this->assertFalse($this->libBookmarks->bookmarkExists("http://www.google.com", $this->userid));
		$this->cleanDB();
	}

	function cleanDB() {
		$query1 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks');
		$query1->execute();
		$query2 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_tags');
		$query2->execute();
	}

}
