<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Controller\Rest\BookmarkController;
use OCA\Bookmarks\Controller\Lib\Bookmarks;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

/**
 * Class Test_BookmarkController
 *
 * @group DB
 */
class Test_BookmarkController extends TestCase {

	/** @var	Bookmarks */
	protected $libBookmarks;
	private $userid;
	private $otherUser;
	private $request;
	private $db;
	private $userManager;
	/** @var	BookmarkController */
	private $controller;
	/** @var	BookmarkController */
	private $publicController;

	protected function setUp() {
		parent::setUp();

		$this->userid = "testuser";
		$this->otherUser = "otheruser";
		$this->request = \OC::$server->getRequest();
		$this->db = \OC::$server->getDatabaseConnection();
		$this->userManager = \OC::$server->getUserManager();
		if (!$this->userManager->userExists($this->userid)) {
			$this->userManager->createUser($this->userid, 'password');	
		}
		if (!$this->userManager->userExists($this->otherUser)) {
			$this->userManager->createUser($this->otherUser, 'password');	
		}

		$config = \OC::$server->getConfig();
		$l = \OC::$server->getL10N('bookmarks');
		$clientService = \OC::$server->getHTTPClientService();
		$logger = \OC::$server->getLogger();
		$this->libBookmarks = new Bookmarks($this->db, $config, $l, $clientService, $logger);

		$this->controller = new BookmarkController("bookmarks", $this->request, $this->userid, $this->db, $l, $this->libBookmarks, $this->userManager);
		$this->publicController = new BookmarkController("bookmarks", $this->request, $this->otherUser, $this->db, $l, $this->libBookmarks, $this->userManager);
	}

	function setupBookmarks() {
		$this->libBookmarks->addBookmark($this->userid, "http://www.golem.de", "Golem", array("four"), "PublicNoTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.9gag.com", "9gag", array("two", "three"), "PublicTag", true);
	}

	function testPrivateQuery() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->controller->getBookmarks();
		$data = $output->getData();
		$this->assertEquals(2, count($data['data']));
	}
	
	function testPublicQuery() {
		$this->cleanDB();
		$this->setupBookmarks();

		$output = $this->publicController->getBookmarks('bookmark', '', -1, 'bookmarks_sorting_recent', $this->userid);
		$data = $output->getData();
		$this->assertEquals(1, count($data['data']));
	}
	
	function testPublicCreate() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->controller->newBookmark("http://www.heise.de", array("tags"=> array("four")), "Heise", true, "PublicNoTag");

		// the bookmark should exist
		$this->assertNotEquals(false, $this->libBookmarks->bookmarkExists("http://www.heise.de", $this->userid));
		// user should see this bookmark
		$output = $this->controller->getBookmarks();
		$data = $output->getData();
		$this->assertEquals(3, count($data['data']));

		// public should see this bookmark
		$output = $this->publicController->getBookmarks('bookmark', '', -1, 'bookmarks_sorting_recent', $this->userid);
		$data = $output->getData();
		$this->assertEquals(2, count($data['data']));
	}
	
	function testPrivateCreate() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->controller->newBookmark("http://www.heise.de", array("tags"=> array("four")), "Heise", false, "PublicNoTag");
		
		// the bookmark should exist
		$this->assertNotEquals(false, $this->libBookmarks->bookmarkExists("http://www.heise.de", $this->userid));
		
		// user should see this bookmark
		$output = $this->controller->getBookmarks();
		$data = $output->getData();
		$this->assertEquals(3, count($data['data']));

		// public should not see this bookmark
		$output = $this->publicController->getBookmarks('bookmark', '', -1, 'bookmarks_sorting_recent', $this->userid);
		$data = $output->getData();
		$this->assertEquals(1, count($data['data']));
	}
	
	function testPrivateEditBookmark() {
		$this->cleanDB();
		$this->setupBookmarks();
		$id = $this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Golem", array("four"), "PublicNoTag", true);

		$this->controller->editBookmark($id, 'https://www.heise.de', null, '', true, $id, '');
		
		$bookmark = $this->libBookmarks->findUniqueBookmark($id, $this->userid);
		$this->assertEquals("https://www.heise.de", $bookmark['url']);
	}
	
	function testPrivateDeleteBookmark() {
		$this->cleanDB();
		$this->setupBookmarks();
		$id = $this->libBookmarks->addBookmark($this->userid, "http://www.google.com", "Heise", array("one", "two"), "PrivatTag", false);
		
		$this->controller->deleteBookmark($id);
		$this->assertFalse($this->libBookmarks->bookmarkExists("http://www.google.com", $this->userid));
	}

	function testFindBookmarksEmptyTags() {
		$this->cleanDB();
		$this->setupBookmarks();
		$id = $this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Heise", []);

		$bookmarks = $this->libBookmarks->findBookmarks($this->userid, 0, 'id', [], true, -1);
		$this->assertEquals([], $bookmarks[0]['tags']);
	}

	public function testClick() {
		$this->cleanDB();
		$this->setupBookmarks();

		$r = $this->publicController->clickBookmark('http://www.golem.de');
		$this->assertInstanceOf(JSONResponse::class, $r);
		$this->assertSame(Http::STATUS_OK, $r->getStatus());
	}

	public function testClickEscapeParam() {
		$this->cleanDB();
		$url1 = 'https://example.com/?foo%bar';
		$id1 = $this->libBookmarks->addBookmark($this->userid, $url1, "Example Domain 1");
		$id2 = $this->libBookmarks->addBookmark($this->userid, "https://example.com/?foo%bier", "Example Domain 2");

		$r = $this->controller->clickBookmark('https://example.com/?foo%25bar');
		$this->assertInstanceOf(JSONResponse::class, $r);
		$this->assertSame(Http::STATUS_OK, $r->getStatus());

		$bm1 = $this->libBookmarks->findUniqueBookmark($id1, $this->userid);
		$bm2 = $this->libBookmarks->findUniqueBookmark($id2, $this->userid);

		$this->assertSame(1, intval($bm1['clickcount']));
		$this->assertSame(0, intval($bm2['clickcount']));
	}

	function cleanDB() {
		$query1 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks');
		$query1->execute();
		$query2 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_tags');
		$query2->execute();
	}

}
