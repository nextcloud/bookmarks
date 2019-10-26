<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Controller\Rest\BookmarkController;
use OCA\Bookmarks\Bookmarks;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\TagMapper;
use OCA\Bookmarks\Service\LinkExplorer;
use OCA\Bookmarks\Service\BookmarkPreviewer;
use OCA\Bookmarks\Service\FaviconPreviewer;
use OCA\Bookmarks\Service\HtmlExporter;
use OCA\Bookmarks\Service\HtmlImporter;
use OCA\Bookmarks\UrlNormalizer;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use \OCA\Bookmarks\Previews\IPreviewService;
use OCA\Bookmarks\Previews\DefaultPreviewService;
use OCA\Bookmarks\Previews\ScreenlyPreviewService;
use OCA\Bookmarks\Previews\FaviconPreviewService;
use OCP\AppFramework\Utility\ITimeFactory;
use \OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Class Test_BookmarkController
 *
 * @group DB
 */
class BookmarkControllerTest extends TestCase {
	private $userId;

	private $otherUser;
	/**
	 * @var \OCP\IRequest
	 */
	private $request;
	/**
	 * @var \OC\User\Manager
	 */
	private $userManager;
	/**
	 * @var BookmarkController
	 */
	private $controller;
	/**
	 * @var BookmarkController
	 */
	private $publicController;
	/**
	 * @var BookmarkMapper
	 */
	private $bookmarkMapper;

	protected function setUp() : void {
		parent::setUp();

		$this->user = 'test';
		$this->otherUser = 'otheruser';
		$this->request = \OC::$server->getRequest();
		$this->db = \OC::$server->getDatabaseConnection();

		$this->userManager = \OC::$server->getUserManager();
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();
		if (!$this->userManager->userExists($this->otherUser)) {
			$this->userManager->createUser($this->otherUser, 'password');
		}
		$this->otherUserId = $this->userManager->get($this->otherUser)->getUID();

		$l = \OC::$server->getL10N('bookmarks');
		$this->bookmarkMapper = \OC::$server->query(BookmarkMapper::class);
		$this->tagMapper = \OC::$server->query(TagMapper::class);
		$folderMapper = \OC::$server->query(FolderMapper::class);

		$bookmarkPreviewer = \OC::$server->query(BookmarkPreviewer::class);
		$faviconPreviewer= \OC::$server->query(FaviconPreviewer::class);
		$timeFactory = \OC::$server->query(ITimeFactory::class);
		$logger = \OC::$server->getLogger();
		$userSession = \OC::$server->getUserSession();
		$linkExplorer = \OC::$server->query(LinkExplorer::class);
		$urlGenerator = \OC::$server->query(IURLGenerator::class);
		$htmlImporter = \OC::$server->query(HtmlImporter::class);
		$htmlExporter = \OC::$server->query(HtmlExporter::class);

		$this->controller = new BookmarkController("bookmarks", $this->request, $this->userId, $l, $this->bookmarkMapper, $this->tagMapper, $folderMapper, $this->userManager, $bookmarkPreviewer, $faviconPreviewer, $timeFactory, $logger, $userSession, $linkExplorer, $urlGenerator, $htmlImporter, $htmlExporter);
		$this->publicController = new BookmarkController("bookmarks", $this->request, $this->otherUserId, $l, $this->bookmarkMapper, $this->tagMapper, $folderMapper, $this->userManager, $bookmarkPreviewer, $faviconPreviewer, $timeFactory, $logger, $userSession, $linkExplorer, $urlGenerator, $htmlImporter, $htmlExporter);
	}

	public function setupBookmarks() {
		$bookmark1 = Bookmark::fromArray([
			'userId' => $this->userId,
			'url' => "https://www.golem.de",
			'title' => "Golem",
			'description' => "PublicNoTag",
			'public' => false
		]);
		$bookmark1 = $this->bookmarkMapper->insertOrUpdate($bookmark1);
		$this->tagMapper->addTo(['four'], $bookmark1->getId());

		$bookmark2 = Bookmark::fromArray([
			'userId' => $this->userId,
			'url' => "https://9gag.com",
			'title' => "9gag",
			'description' => "PublicTag",
			'public' => true
		]);
		$bookmark2 = $this->bookmarkMapper->insertOrUpdate($bookmark2);
		$this->tagMapper->addTo(['four'], $bookmark2->getId());
		$this->testSubjectPrivateBmId = $bookmark1->getId();
		$this->testSubjectPublicBmId = $bookmark2->getId();
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
		$output = $this->publicController->getSingleBookmark($this->testSubjectPublicBmId, $this->userId);
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals("https://9gag.com/", $data['item']['url']);
	}

	public function testPublicReadFailure() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->publicController->getSingleBookmark($this->testSubjectPrivateBmId, $this->userId);
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

		$output = $this->publicController->getBookmarks(-1, $this->userId);
		$data = $output->getData();
		$this->assertEquals(1, count($data['data']));
	}

	public function testPublicCreate() {
		$this->cleanDB();
		$this->setupBookmarks();
		$res = $this->controller->newBookmark("https://www.heise.de", 'Heise', true, "Public", ["four"]);
		$this->assertEquals('success', $res->getData()['status']);

		// the bookmark should exist
		$this->bookmarkMapper->findByUrl($this->userId, "https://www.heise.de");
		// user should see this bookmark
		$output = $this->controller->getBookmarks();
		$data = $output->getData();
		$this->assertEquals(3, count($data['data']));

		// public should see this bookmark
		$output = $this->publicController->getBookmarks(-1, $this->userId);
		$data = $output->getData();
		$this->assertEquals(2, count($data['data']));
	}

	public function testPrivateCreate() {
		$this->cleanDB();
		$this->setupBookmarks();
		$res = $this->controller->newBookmark("https://www.heise.de", 'Heise', false, "Private", ["four"]);
		$this->assertEquals('success', $res->getData()['status']);

		// the bookmark should exist
		$this->bookmarkMapper->findByUrl($this->userId, "https://www.heise.de");

		// user should see this bookmark
		$output = $this->controller->getBookmarks();
		$data = $output->getData();
		$this->assertEquals(3, count($data['data']));

		// public should not see this bookmark
		$output = $this->publicController->getBookmarks(-1, $this->userId);
		$data = $output->getData();
		$this->assertEquals(1, count($data['data']));
	}

	public function testPrivateEditBookmark() {
		$this->cleanDB();
		$this->setupBookmarks();
		$res = $this->controller->newBookmark("https://www.heise.de", 'Heise', false, "PublicNoTag", ["four"]);
		$this->assertEquals('success', $res->getData()['status']);
		$id = $res->getData()['item']['id'];

		$this->controller->editBookmark($id, 'https://www.heise.de', null, '', true, $id, '');

		$bookmark = $this->bookmarkMapper->find($id);
		$this->assertEquals("https://www.heise.de/", $bookmark->getUrl()); // normalized URL
	}

	public function testPrivateDeleteBookmark() {
		$this->cleanDB();
		$this->setupBookmarks();
		$res = $this->controller->newBookmark("https://www.google.com", 'Google', false, "PrivateTag", ["one", 'two']);
		$this->assertEquals('success', $res->getData()['status']);
		$id = $res->getData()['item']['id'];

		$this->controller->deleteBookmark($id);
		$exception = null;
		try {
			$this->bookmarkMapper->findByUrl($this->userId, "https://www.google.com");
		}catch(\Exception $e){
			$exception = $e;
		}
		$this->assertInstanceOf(DoesNotExistException::class, $exception, 'Expected bookmark not to exist and throw');
	}

	public function testClick() {
		$this->cleanDB();
		$this->setupBookmarks();

		$r = $this->controller->clickBookmark('https://www.golem.de');
		$this->assertInstanceOf(JSONResponse::class, $r);
		$this->assertSame(Http::STATUS_OK, $r->getStatus());
	}

	public function testPublicClickFail() {
		$this->cleanDB();
		$this->setupBookmarks();

		$r = $this->publicController->clickBookmark('https://www.golem.de');
		$this->assertInstanceOf(JSONResponse::class, $r);
		$this->assertNotSame(Http::STATUS_OK, $r->getStatus());
	}

	public function cleanDB() {
		$query1 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks');
		$query1->execute();
		$query2 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_tags');
		$query2->execute();
	}
}
