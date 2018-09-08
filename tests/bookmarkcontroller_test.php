<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Controller\Rest\BookmarkController;
use OCA\Bookmarks\Controller\Lib\Bookmarks;
use OCA\Bookmarks\Controller\Lib\LinkExplorer;
use OCA\Bookmarks\Controller\Lib\UrlNormalizer;
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
		$linkExplorer = \OC::$server->query(LinkExplorer::class);
		$urlNormalizer = \OC::$server->query(UrlNormalizer::class);
		$event = \OC::$server->getEventDispatcher();
		$logger = \OC::$server->getLogger();
		$this->libBookmarks = new Bookmarks($this->db, $config, $l, $linkExplorer, $urlNormalizer, $event, $logger);

		$this->controller = new BookmarkController("bookmarks", $this->request, $this->userid, $this->db, $l, $this->libBookmarks, $this->userManager);
		$this->publicController = new BookmarkController("bookmarks", $this->request, $this->otherUser, $this->db, $l, $this->libBookmarks, $this->userManager);
	}

	public function setupBookmarks() {
		$this->testSubjectPrivateBmId = $this->libBookmarks->addBookmark($this->userid, "https://www.golem.de", "Golem", ["four"], "PublicNoTag", false);
		$this->testSubjectPublicBmId = $this->libBookmarks->addBookmark($this->userid, "https://9gag.com", "9gag", ["two", "three"], "PublicTag", true);
	}

	public function testPrivateRead() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->controller->getSingleBookmark($this->testSubjectPublicBmId);
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals("https://9gag.com/", $data['item']['url']);
	}

	public function testPublicReadSuccess() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->publicController->getSingleBookmark($this->testSubjectPublicBmId, $this->userid);
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals("https://9gag.com/", $data['item']['url']);
	}

	public function testPublicReadFailure() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->publicController->getSingleBookmark($this->testSubjectPrivateBmId, $this->userid);
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
	}

	public function testPrivateReadNotFound() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->controller->getSingleBookmark(987);
		$data = $output->getData();
		$this->assertSame('error', $data['status']);
		$this->assertSame(404, $output->getStatus());
	}

	public function testPrivateQuery() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->controller->getBookmarks();
		$data = $output->getData();
		$this->assertEquals(2, count($data['data']));
	}

	public function testPublicQuery() {
		$this->cleanDB();
		$this->setupBookmarks();

		$output = $this->publicController->getBookmarks('bookmark', '', -1, 'bookmarks_sorting_recent', $this->userid);
		$data = $output->getData();
		$this->assertEquals(1, count($data['data']));
	}

	public function testPublicCreate() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->controller->newBookmark("https://www.heise.de", ["tags"=> ["four"]], "Heise", true, "PublicNoTag");

		// the bookmark should exist
		$this->assertNotEquals(false, $this->libBookmarks->bookmarkExists("https://www.heise.de", $this->userid));
		// user should see this bookmark
		$output = $this->controller->getBookmarks();
		$data = $output->getData();
		$this->assertEquals(3, count($data['data']));

		// public should see this bookmark
		$output = $this->publicController->getBookmarks('bookmark', '', -1, 'bookmarks_sorting_recent', $this->userid);
		$data = $output->getData();
		$this->assertEquals(2, count($data['data']));
	}

	public function testPrivateCreate() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->controller->newBookmark("https://www.heise.de", ["tags"=> ["four"]], "Heise", false, "PublicNoTag");

		// the bookmark should exist
		$this->assertNotEquals(false, $this->libBookmarks->bookmarkExists("https://www.heise.de", $this->userid));

		// user should see this bookmark
		$output = $this->controller->getBookmarks();
		$data = $output->getData();
		$this->assertEquals(3, count($data['data']));

		// public should not see this bookmark
		$output = $this->publicController->getBookmarks('bookmark', '', -1, 'bookmarks_sorting_recent', $this->userid);
		$data = $output->getData();
		$this->assertEquals(1, count($data['data']));
	}

	public function testPrivateEditBookmark() {
		$this->cleanDB();
		$this->setupBookmarks();
		$id = $this->libBookmarks->addBookmark($this->userid, "https://www.heise.de", "Golem", ["four"], "PublicNoTag", true);

		$this->controller->editBookmark($id, 'https://www.heise.de', null, '', true, $id, '');

		$bookmark = $this->libBookmarks->findUniqueBookmark($id, $this->userid);
		$this->assertEquals("https://www.heise.de/", $bookmark['url']); // normalized URL
	}

	public function testPrivateDeleteBookmark() {
		$this->cleanDB();
		$this->setupBookmarks();
		$id = $this->libBookmarks->addBookmark($this->userid, "https://www.google.com", "Heise", ["one", "two"], "PrivatTag", false);

		$this->controller->deleteBookmark($id);
		$this->assertFalse($this->libBookmarks->bookmarkExists("https://www.google.com", $this->userid));
	}

	public function testClick() {
		$this->cleanDB();
		$this->setupBookmarks();

		$r = $this->publicController->clickBookmark('https://www.golem.de');
		$this->assertInstanceOf(JSONResponse::class, $r);
		$this->assertSame(Http::STATUS_OK, $r->getStatus());
	}

	public function cleanDB() {
		$query1 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks');
		$query1->execute();
		$query2 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_tags');
		$query2->execute();
	}
}
