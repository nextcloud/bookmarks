<?php

namespace OCA\Bookmarks\Tests;

use OC\Tagging\Tag;
use OCA\Bookmarks\Controller\BookmarkController;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Db\BookmarkMapper;
use OCA\Bookmarks\Db\Folder;
use OCA\Bookmarks\Db\FolderMapper;
use OCA\Bookmarks\Db\PublicFolder;
use OCA\Bookmarks\Db\PublicFolderMapper;
use OCA\Bookmarks\Db\Share;
use OCA\Bookmarks\Db\SharedFolder;
use OCA\Bookmarks\Db\SharedFolderMapper;
use OCA\Bookmarks\Db\ShareMapper;
use OCA\Bookmarks\Db\TagMapper;
use OCA\Bookmarks\Service\Authorizer;
use OCA\Bookmarks\Service\LinkExplorer;
use OCA\Bookmarks\Service\BookmarkPreviewer;
use OCA\Bookmarks\Service\FaviconPreviewer;
use OCA\Bookmarks\Service\HtmlExporter;
use OCA\Bookmarks\Service\HtmlImporter;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use \OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Class Test_BookmarkController
 *
 * @group Controller
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

	/**
	 * @var FolderMapper
	 */
	private $folderMapper;

	/**
	 * @var TagMapper
	 */
	private $tagMapper;

	/**
	 * @var PublicFolderMapper
	 */
	private $publicFolderMapper;

	private $bookmark1Id;
	private $bookmark2Id;

	/**
	 * @var PublicFolder
	 */
	private $publicFolder;

	/**
	 * @var Folder
	 */
	private $folder1;

	/**
	 * @var Folder
	 */
	private $folder2;

	protected function setUp() : void {
		parent::setUp();

		$this->user = 'test';
		$this->otherUser = 'otheruser';
		$this->request = \OC::$server->getRequest();

		$this->publicRequest = $this->createMock(IRequest::class);

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
		$this->folderMapper = \OC::$server->query(FolderMapper::class);
		$this->publicFolderMapper = \OC::$server->query(PublicFolderMapper::class);
		$this->shareMapper = \OC::$server->query(ShareMapper::class);
		$this->sharedFolderMapper = \OC::$server->query(SharedFolderMapper::class);

		$bookmarkPreviewer = \OC::$server->query(BookmarkPreviewer::class);
		$faviconPreviewer= \OC::$server->query(FaviconPreviewer::class);
		$timeFactory = \OC::$server->query(ITimeFactory::class);
		$logger = \OC::$server->getLogger();
		$userSession = \OC::$server->getUserSession();
		$linkExplorer = \OC::$server->query(LinkExplorer::class);
		$urlGenerator = \OC::$server->query(IURLGenerator::class);
		$htmlImporter = \OC::$server->query(HtmlImporter::class);
		$htmlExporter = \OC::$server->query(HtmlExporter::class);
		$authorizer1 = \OC::$server->query(Authorizer::class);
		$authorizer2 = \OC::$server->query(Authorizer::class);
		$authorizer3 = \OC::$server->query(Authorizer::class);

		$this->controller = new BookmarkController("bookmarks", $this->request, $this->userId, $l, $this->bookmarkMapper, $this->tagMapper, $this->folderMapper, $this->userManager, $bookmarkPreviewer, $faviconPreviewer, $timeFactory, $logger, $userSession, $linkExplorer, $urlGenerator, $htmlImporter, $htmlExporter, $authorizer1);
		$this->otherController = new BookmarkController("bookmarks", $this->request, $this->otherUserId, $l, $this->bookmarkMapper, $this->tagMapper, $this->folderMapper, $this->userManager, $bookmarkPreviewer, $faviconPreviewer, $timeFactory, $logger, $userSession, $linkExplorer, $urlGenerator, $htmlImporter, $htmlExporter, $authorizer2);

		$this->publicController = new BookmarkController("bookmarks", $this->publicRequest, null, $l, $this->bookmarkMapper, $this->tagMapper, $this->folderMapper, $this->userManager, $bookmarkPreviewer, $faviconPreviewer, $timeFactory, $logger, $userSession, $linkExplorer, $urlGenerator, $htmlImporter, $htmlExporter, $authorizer3);
	}

	public function setupBookmarks() {
		$bookmark1 = Bookmark::fromArray([
			'userId' => $this->userId,
			'url' => "https://www.golem.de",
			'title' => "Golem",
			'description' => "PublicNoTag"
		]);
		$bookmark1 = $this->bookmarkMapper->insertOrUpdate($bookmark1);
		$this->tagMapper->addTo(['four'], $bookmark1->getId());

		$bookmark2 = Bookmark::fromArray([
			'userId' => $this->userId,
			'url' => "https://9gag.com",
			'title' => "9gag",
			'description' => "PublicTag"
		]);
		$bookmark2 = $this->bookmarkMapper->insertOrUpdate($bookmark2);
		$this->tagMapper->addTo(['four'], $bookmark2->getId());
		$this->bookmark1Id = $bookmark1->getId();
		$this->bookmark2Id = $bookmark2->getId();
	}

	public function setupBookmarksWithPublicFolder() {
		$this->setupBookmarks();

		$this->folder1 = new Folder();
		$this->folder1->setTitle('foo');
		$this->folder1->setParentFolder(-1);
		$this->folder1->setUserId($this->userId);
		$this->folderMapper->insert($this->folder1);

		$this->folder2 = new Folder();
		$this->folder2->setTitle('bar');
		$this->folder2->setParentFolder($this->folder1->getId());
		$this->folder2->setUserId($this->userId);
		$this->folderMapper->insert($this->folder2);

		$this->publicFolder = new PublicFolder();
		$this->publicFolder->setFolderId($this->folder1->getId());
		$this->publicFolderMapper->insert($this->publicFolder);

		// inject token into public request stub
		$this->publicRequest->method('getHeader')
			->willReturn('Bearer '.$this->publicFolder->getId());

		$this->folderMapper->addToFolders($this->bookmark1Id, [$this->folder1->getId()]);
		$this->folderMapper->addToFolders($this->bookmark2Id, [$this->folder2->getId()]);
	}

	public function setupBookmarksWithSharedFolder() {
		$this->setupBookmarksWithPublicFolder();
		$this->share = new Share();
		$this->share->setFolderId($this->folder1->getId());
		$this->share->setOwner($this->userId);
		$this->share->setParticipant($this->otherUserId);
		$this->share->setType(ShareMapper::TYPE_USER);
		$this->share->setCanWrite(true);
		$this->share->setCanShare(false);
		$this->shareMapper->insert($this->share);

		$this->sharedFolder = new SharedFolder();
		$this->sharedFolder->setShareId($this->share->getId());
		$this->sharedFolder->setTitle('foo');
		$this->sharedFolder->setParentFolder(-1);
		$this->sharedFolder->setUserId($this->otherUser);
		$this->sharedFolder->setIndex(0);
		$this->sharedFolderMapper->insert($this->sharedFolder);
	}

	public function testRead() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->controller->getSingleBookmark($this->bookmark2Id);
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals("https://9gag.com/", $data['item']['url']);
	}

	public function testReadFailure() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->otherController->getSingleBookmark($this->bookmark1Id);
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
	}

	public function testPublicReadFailure() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->publicController->getSingleBookmark($this->bookmark1Id);
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
	}

	public function testReadNotFound() {
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

	public function testCreate() {
		$this->cleanDB();
		$this->setupBookmarks();
		$res = $this->controller->newBookmark("https://www.heise.de", 'Heise', "Private", ["four"]);
		$this->assertEquals('success', $res->getData()['status']);

		// the bookmark should exist
		$this->bookmarkMapper->findByUrl($this->userId, "https://www.heise.de");

		// user should see this bookmark
		$output = $this->controller->getBookmarks();
		$data = $output->getData();
		$this->assertEquals(3, count($data['data']));

		// others should not see this bookmark
		$output = $this->otherController->getBookmarks(-1);
		$data = $output->getData();
		$this->assertEquals(0, count($data['data']));
	}

	public function testEditBookmark() {
		$this->cleanDB();
		$this->setupBookmarks();
		$res = $this->controller->newBookmark("https://www.heise.de", 'Heise', "PublicNoTag", ["four"]);
		$this->assertEquals('success', $res->getData()['status']);
		$id = $res->getData()['item']['id'];

		$this->controller->editBookmark($id, 'https://www.heise.de', '');

		$bookmark = $this->bookmarkMapper->find($id);
		$this->assertEquals("https://www.heise.de/", $bookmark->getUrl()); // normalized URL
		$this->assertEquals("", $bookmark->getTitle()); // normalized URL
	}

	public function testDeleteBookmark() {
		$this->cleanDB();
		$this->setupBookmarks();
		$res = $this->controller->newBookmark("https://www.google.com", 'Google', "PrivateTag", ["one", 'two']);
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

	public function testClickFail() {
		$this->cleanDB();
		$this->setupBookmarks();

		$r = $this->publicController->clickBookmark('https://www.golem.de');
		$this->assertInstanceOf(JSONResponse::class, $r);
		$this->assertNotSame(Http::STATUS_OK, $r->getStatus());
	}

	public function testPublicRead() {
		$this->cleanDB();
		$this->setupBookmarksWithPublicFolder();
		$res = $this->publicController->getSingleBookmark($this->bookmark2Id);
		$data = $res->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals("https://9gag.com/", $data['item']['url']);
	}

	public function testPublicReadNotFound() {
		$this->cleanDB();
		$this->setupBookmarksWithPublicFolder();
		$output = $this->publicController->getSingleBookmark(987);
		$data = $output->getData();
		$this->assertSame('error', $data['status']);
		$this->assertSame(404, $output->getStatus());
	}

	public function testPublicQuery() {
		$this->cleanDB();
		$this->setupBookmarksWithPublicFolder();
		$output = $this->publicController->getBookmarks(-1, null,"or", "", [],10,false, $this->folder2->getId());
		$data = $output->getData();
		$this->assertEquals(1, count($data['data'])); // TODO: 1-level search Limit!
	}

	public function testPublicCreateFail() {
		$this->cleanDB();
		$this->setupBookmarksWithPublicFolder();
		$res = $this->publicController->newBookmark("https://www.heise.de", 'Heise', "Private", ["four"], [$this->folder2->getId()]);
		$this->assertEquals('error', $res->getData()['status']);
	}

	public function testPublicEditBookmarkFail() {
		$this->cleanDB();
		$this->setupBookmarksWithPublicFolder();

		$res = $this->publicController->editBookmark($this->bookmark1Id, 'https://www.heise.de', '');
		$this->assertEquals('error', $res->getData()['status']);
	}

	public function testPublicDeleteBookmarkFail() {
		$this->cleanDB();
		$this->setupBookmarksWithPublicFolder();

		$res = $this->publicController->deleteBookmark($this->bookmark1Id);
		$this->assertEquals('error', $res->getData()['status']);
	}

	public function testReadShared() {
		$this->cleanDB();
		$this->setupBookmarksWithSharedFolder();
		$output = $this->otherController->getSingleBookmark($this->bookmark2Id);
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals("https://9gag.com/", $data['item']['url']);
	}

	public function testQueryShared() {
		$this->cleanDB();
		$this->setupBookmarksWithSharedFolder();
		$output = $this->otherController->getBookmarks();
		$data = $output->getData();
		$this->assertEquals(1, count($data['data'])); // TODO: 1 level search Limit
	}

	public function testCreateShared() {
		$this->cleanDB();
		$this->setupBookmarksWithSharedFolder();
		$res = $this->otherController->newBookmark("https://www.heise.de", 'Heise', "Private", ["four"],[$this->folder1->getId()]);
		$this->assertEquals('success', $res->getData()['status']);

		// the bookmark should exist
		$this->bookmarkMapper->findByUrl($this->userId, "https://www.heise.de");

		// user should see this bookmark
		$output = $this->controller->getBookmarks();
		$data = $output->getData();
		$this->assertEquals(3, count($data['data']));

		// others should see this bookmark
		$output = $this->otherController->getBookmarks();
		$data = $output->getData();
		$this->assertEquals(2, count($data['data'])); // TODO: 1 level search limit
	}

	public function testEditBookmarkShared() {
		$this->cleanDB();
		$this->setupBookmarksWithSharedFolder();
		$res = $this->controller->newBookmark("https://www.heise.de", 'Heise', "PublicNoTag", ["four"],[$this->folder1->getId()]);
		$this->assertEquals('success', $res->getData()['status']);
		$id = $res->getData()['item']['id'];

		$res = $this->otherController->editBookmark($id, 'https://www.heise.de', '');
		$this->assertEquals('success', $res->getData()['status']);

		$bookmark = $this->bookmarkMapper->find($id);
		$this->assertEquals("https://www.heise.de/", $bookmark->getUrl()); // normalized URL
		$this->assertEquals("", $bookmark->getTitle());
	}

	public function testDeleteBookmarkShared() {
		$this->cleanDB();
		$this->setupBookmarksWithSharedFolder();
		$res = $this->controller->newBookmark("https://www.google.com", 'Google', "PrivateTag", ["one", 'two'], [$this->folder1->getId()]);
		$this->assertEquals('success', $res->getData()['status']);
		$id = $res->getData()['item']['id'];

		$res = $this->otherController->deleteBookmark($id);
		$this->assertEquals('success', $res->getData()['status']);

		$exception = null;
		try {
			$this->bookmarkMapper->findByUrl($this->userId, "https://www.google.com");
		}catch(\Exception $e){
			$exception = $e;
		}
		$this->assertInstanceOf(DoesNotExistException::class, $exception, 'Expected bookmark not to exist and throw');
	}

	public function testClickSharedFail() {
		$this->cleanDB();
		$this->setupBookmarksWithSharedFolder();

		$r = $this->otherController->clickBookmark('https://www.golem.de');
		$this->assertInstanceOf(JSONResponse::class, $r);
		$this->assertNotSame(Http::STATUS_OK, $r->getStatus());
	}

	public function cleanDB() {
		$query1 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks');
		$query1->execute();
		$query2 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_tags');
		$query2->execute();
		$query3 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_folders');
		$query3->execute();
		$query4 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_folders_bookmarks');
		$query4->execute();
		$query5 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_folders_public');
		$query5->execute();
		$query6 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_shared');
		$query6->execute();
		$query7 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_shares');
		$query7->execute();
	}
}
