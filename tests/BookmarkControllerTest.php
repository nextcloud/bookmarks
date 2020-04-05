<?php

namespace OCA\Bookmarks\Tests;

use OC;
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
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\UnsupportedOperation;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Bookmarks\Service\Authorizer;
use OCA\Bookmarks\Service\BookmarkService;
use OCA\Bookmarks\Service\HtmlExporter;
use OCA\Bookmarks\Service\HtmlImporter;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use OCP\IURLGenerator;

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
	/**
	 * @var string
	 */
	private $user;
	/**
	 * @var IRequest
	 */
	private $publicRequest;
	/**
	 * @var string
	 */
	private $otherUserId;
	/**
	 * @var ShareMapper
	 */
	private $shareMapper;
	/**
	 * @var SharedFolderMapper
	 */
	private $sharedFolderMapper;
	/**
	 * @var BookmarkController
	 */
	private $otherController;
	/**
	 * @var TreeMapper
	 */
	private $treeMapper;
	/**
	 * @var SharedFolder
	 */
	private $sharedFolder;
	/**
	 * @var Share
	 */
	private $share;
	/**
	 * @var Authorizer
	 */
	private $authorizer;
	/**
	 * @var BookmarkService
	 */
	private $bookmarks;

	/**
	 * @throws \OCP\AppFramework\QueryException
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->user = 'test';
		$this->otherUser = 'otheruser';
		$this->request = OC::$server->getRequest();
		$this->otherRequest = OC::$server->getRequest();

		$this->publicRequest = $this->createMock(IRequest::class);

		$this->userManager = OC::$server->getUserManager();
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();
		if (!$this->userManager->userExists($this->otherUser)) {
			$this->userManager->createUser($this->otherUser, 'password');
		}
		$this->otherUserId = $this->userManager->get($this->otherUser)->getUID();

		$l = OC::$server->getL10N('bookmarks');
		$this->bookmarks = OC::$server->query(BookmarkService::class);
		$this->bookmarkMapper = OC::$server->query(BookmarkMapper::class);
		$this->tagMapper = OC::$server->query(TagMapper::class);
		$this->folderMapper = OC::$server->query(FolderMapper::class);
		$this->treeMapper = OC::$server->query(TreeMapper::class);
		$this->publicFolderMapper = OC::$server->query(PublicFolderMapper::class);
		$this->shareMapper = OC::$server->query(ShareMapper::class);
		$this->sharedFolderMapper = OC::$server->query(SharedFolderMapper::class);

		$timeFactory = OC::$server->query(ITimeFactory::class);
		$logger = OC::$server->getLogger();
		$urlGenerator = OC::$server->query(IURLGenerator::class);
		$htmlImporter = OC::$server->query(HtmlImporter::class);
		$htmlExporter = OC::$server->query(HtmlExporter::class);
		$this->authorizer = OC::$server->query(Authorizer::class);

		$this->controller = new BookmarkController('bookmarks', $this->request, $this->userId, $l, $this->bookmarkMapper, $this->tagMapper, $this->folderMapper, $this->treeMapper, $this->publicFolderMapper, $timeFactory, $logger, $urlGenerator, $htmlImporter, $htmlExporter, $this->authorizer, $this->bookmarks);
		$this->otherController = new BookmarkController('bookmarks', $this->request, $this->otherUserId, $l, $this->bookmarkMapper, $this->tagMapper, $this->folderMapper, $this->treeMapper, $this->publicFolderMapper, $timeFactory, $logger, $urlGenerator, $htmlImporter, $htmlExporter, $this->authorizer, $this->bookmarks);

		$this->publicController = new BookmarkController('bookmarks', $this->publicRequest, null, $l, $this->bookmarkMapper, $this->tagMapper, $this->folderMapper, $this->treeMapper, $this->publicFolderMapper, $timeFactory, $logger, $urlGenerator, $htmlImporter, $htmlExporter, $this->authorizer, $this->bookmarks);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 */
	public function setupBookmarks(): void {
		$bookmark1 = Bookmark::fromArray([
			'userId' => $this->userId,
			'url' => 'https://www.golem.de',
			'title' => 'Golem',
			'description' => 'PublicNoTag',
		]);
		$bookmark1 = $this->bookmarkMapper->insertOrUpdate($bookmark1);
		$this->tagMapper->addTo(['four'], $bookmark1->getId());

		$bookmark2 = Bookmark::fromArray([
			'userId' => $this->userId,
			'url' => 'https://9gag.com',
			'title' => '9gag',
			'description' => 'PublicTag',
		]);
		$bookmark2 = $this->bookmarkMapper->insertOrUpdate($bookmark2);
		$this->tagMapper->addTo(['four'], $bookmark2->getId());
		$this->bookmark1Id = $bookmark1->getId();
		$this->bookmark2Id = $bookmark2->getId();
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws DoesNotExistException
	 * @throws UnsupportedOperation
	 */
	public function setupBookmarksWithPublicFolder(): void {
		$this->setupBookmarks();

		$this->folder1 = new Folder();
		$this->folder1->setTitle('foo');
		$this->folder1->setUserId($this->userId);
		$this->folderMapper->insert($this->folder1);

		$this->folder2 = new Folder();
		$this->folder2->setTitle('bar');
		$this->folder2->setUserId($this->userId);
		$this->folderMapper->insert($this->folder2);
		$this->treeMapper->move(TreeMapper::TYPE_FOLDER, $this->folder2->getId(), $this->folder1->getId());

		$this->publicFolder = new PublicFolder();
		$this->publicFolder->setFolderId($this->folder1->getId());
		$this->publicFolderMapper->insert($this->publicFolder);

		// inject token into public request stub
		$this->publicRequest->method('getHeader')
			->willReturn('Bearer ' . $this->publicFolder->getId());

		$this->treeMapper->addToFolders(TreeMapper::TYPE_BOOKMARK, $this->bookmark1Id, [$this->folder1->getId()]);
		$this->treeMapper->addToFolders(TreeMapper::TYPE_BOOKMARK, $this->bookmark2Id, [$this->folder2->getId()]);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws UnsupportedOperation
	 */
	public function setupBookmarksWithSharedFolder(): void {
		$this->setupBookmarksWithPublicFolder();
		$this->share = new Share();
		$this->share->setFolderId($this->folder1->getId());
		$this->share->setOwner($this->userId);
		$this->share->setParticipant($this->otherUserId);
		$this->share->setType(\OCP\Share\IShare::TYPE_USER);
		$this->share->setCanWrite(true);
		$this->share->setCanShare(false);
		$this->shareMapper->insert($this->share);

		$this->sharedFolder = new SharedFolder();
		$this->sharedFolder->setShareId($this->share->getId());
		$this->sharedFolder->setTitle('foo');
		$this->sharedFolder->setUserId($this->otherUser);
		$this->sharedFolderMapper->insert($this->sharedFolder);

		$otherRootFolder = $this->folderMapper->findRootFolder($this->otherUserId);
		$this->treeMapper->move(TreeMapper::TYPE_SHARE, $this->sharedFolder->getId(), $otherRootFolder->getId());
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function testRead(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		$output = $this->controller->getSingleBookmark($this->bookmark2Id);
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertEquals("https://9gag.com/", $data['item']['url']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function testReadFailure(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->otherUserId);
		$output = $this->otherController->getSingleBookmark($this->bookmark1Id);
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function testPublicReadFailure(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId(null);
		$output = $this->publicController->getSingleBookmark($this->bookmark1Id);
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function testReadNotFound(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		$output = $this->controller->getSingleBookmark(987);
		$data = $output->getData();
		$this->assertSame('error', $data['status'], var_export($data, true));
		$this->assertSame(404, $output->getStatus());
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function testPrivateQuery(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		$output = $this->controller->getBookmarks();
		$data = $output->getData();
		$this->assertCount(2, $data['data']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function testCreate(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		$res = $this->controller->newBookmark('https://www.heise.de', 'Heise', 'Private', ['four']);
		$this->assertEquals('success', $res->getData()['status'], var_export($res->getData(), true));

		// the bookmark should exist
		$this->bookmarkMapper->findByUrl($this->userId, 'https://www.heise.de');

		// user should see this bookmark
		$output = $this->controller->getBookmarks();
		$data = $output->getData();
		$this->assertCount(3, $data['data']);

		// others should not see this bookmark
		$this->authorizer->setUserId($this->otherUserId);
		$output = $this->otherController->getBookmarks(-1);
		$data = $output->getData();
		$this->assertCount(0, $data['data']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function testEditBookmark(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		$res = $this->controller->newBookmark('https://www.heise.de', 'Heise', 'PublicNoTag', ['four']);
		$this->assertEquals('success', $res->getData()['status'], var_export($res->getData(), true));
		$id = $res->getData()['item']['id'];

		$this->controller->editBookmark($id, 'https://www.heise.de', '');

		$bookmark = $this->bookmarkMapper->find($id);
		$this->assertEquals('https://www.heise.de/', $bookmark->getUrl()); // normalized URL
		$this->assertEquals('', $bookmark->getTitle()); // normalized URL
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function testDeleteBookmark(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		$res = $this->controller->newBookmark('https://www.google.com', 'Google', 'PrivateTag', ['one', 'two']);
		$this->assertEquals('success', $res->getData()['status'], var_export($res->getData(), true));
		$id = $res->getData()['item']['id'];

		$this->controller->deleteBookmark($id);
		$exception = null;
		try {
			$this->bookmarkMapper->findByUrl($this->userId, 'https://www.google.com');
		} catch (\Exception $e) {
			$exception = $e;
		}
		$this->assertInstanceOf(DoesNotExistException::class, $exception, 'Expected bookmark not to exist and throw');
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function testClick(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);

		$r = $this->controller->clickBookmark('https://www.golem.de');
		$this->assertSame(Http::STATUS_OK, $r->getStatus());
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 */
	public function testClickFail(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId(null);

		$r = $this->publicController->clickBookmark('https://www.golem.de');
		$this->assertNotSame(Http::STATUS_OK, $r->getStatus());
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws UnsupportedOperation
	 */
	public function testPublicRead(): void {
		$this->cleanUp();
		$this->setupBookmarksWithPublicFolder();
		$this->authorizer->setUserId(null);
		$res = $this->publicController->getSingleBookmark($this->bookmark2Id);
		$data = $res->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertEquals('https://9gag.com/', $data['item']['url']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws UnsupportedOperation
	 */
	public function testPublicReadNotFound(): void {
		$this->cleanUp();
		$this->setupBookmarksWithPublicFolder();
		$this->authorizer->setUserId(null);
		$output = $this->publicController->getSingleBookmark(987);
		$data = $output->getData();
		$this->assertSame('error', $data['status'], var_export($data, true));
		$this->assertSame(404, $output->getStatus());
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws UnsupportedOperation
	 */
	public function testPublicQuery(): void {
		$this->cleanUp();
		$this->setupBookmarksWithPublicFolder();
		$this->authorizer->setUserId(null);
		$output = $this->publicController->getBookmarks(-1, null, 'or', '', [], 10, false, $this->folder2->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertCount(1, $data['data']); // TODO: 1-level search Limit!
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws UnsupportedOperation
	 */
	public function testPublicCreateFail(): void {
		$this->cleanUp();
		$this->setupBookmarksWithPublicFolder();
		$this->authorizer->setUserId(null);
		$res = $this->publicController->newBookmark('https://www.heise.de', 'Heise', 'Private', ['four'], [$this->folder2->getId()]);
		$this->assertEquals('error', $res->getData()['status'], var_export($res->getData(), true));
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws UnsupportedOperation
	 */
	public function testPublicEditBookmarkFail(): void {
		$this->cleanUp();
		$this->setupBookmarksWithPublicFolder();
		$this->authorizer->setUserId(null);

		$res = $this->publicController->editBookmark($this->bookmark1Id, 'https://www.heise.de', '');
		$this->assertEquals('error', $res->getData()['status'], var_export($res->getData(), true));
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws UnsupportedOperation
	 */
	public function testPublicDeleteBookmarkFail(): void {
		$this->cleanUp();
		$this->setupBookmarksWithPublicFolder();
		$this->authorizer->setUserId(null);

		$res = $this->publicController->deleteBookmark($this->bookmark1Id);
		$this->assertEquals('error', $res->getData()['status'], var_export($res->getData(), true));
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws UnsupportedOperation
	 */
	public function testReadShared(): void {
		$this->cleanUp();
		$this->setupBookmarksWithSharedFolder();
		$this->authorizer->setUserId($this->otherUserId);
		$output = $this->otherController->getSingleBookmark($this->bookmark2Id);
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertEquals('https://9gag.com/', $data['item']['url']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws UnsupportedOperation
	 */
	public function testQueryShared(): void {
		$this->cleanUp();
		$this->setupBookmarksWithSharedFolder();
		$this->authorizer->setUserId($this->otherUserId);
		$output = $this->otherController->getBookmarks();
		$data = $output->getData();
		$this->assertCount(1, $data['data']); // TODO: 1 level search Limit
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws UnsupportedOperation
	 */
	public function testCreateShared(): void {
		$this->cleanUp();
		$this->setupBookmarksWithSharedFolder();
		$this->authorizer->setUserId($this->otherUserId);
		$res = $this->otherController->newBookmark('https://www.heise.de', 'Heise', 'Private', ['four'], [$this->folder1->getId()]);
		$this->assertEquals('success', $res->getData()['status'], var_export($res->getData(), true));

		// the bookmark should exist
		$this->bookmarkMapper->findByUrl($this->userId, 'https://www.heise.de');

		// user should see this bookmark
		$this->authorizer->setUserId($this->userId);
		$output = $this->controller->getBookmarks();
		$data = $output->getData();
		$this->assertCount(3, $data['data']);

		// others should see this bookmark
		$this->authorizer->setUserId($this->otherUserId);
		$output = $this->otherController->getBookmarks();
		$data = $output->getData();
		$this->assertCount(2, $data['data']); // TODO: 1 level search limit
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws UnsupportedOperation
	 */
	public function testEditBookmarkShared(): void {
		$this->cleanUp();
		$this->setupBookmarksWithSharedFolder();
		$this->authorizer->setUserId($this->userId);
		$res = $this->controller->newBookmark('https://www.heise.de', 'Heise', 'PublicNoTag', ['four'], [$this->folder1->getId()]);
		$this->assertEquals('success', $res->getData()['status'], var_export($res->getData(), true));
		$id = $res->getData()['item']['id'];

		$this->authorizer->setUserId($this->otherUserId);
		$res = $this->otherController->editBookmark($id, 'https://www.heise.de', '');
		$this->assertEquals('success', $res->getData()['status'], var_export($res->getData(), true));

		$bookmark = $this->bookmarkMapper->find($id);
		$this->assertEquals('https://www.heise.de/', $bookmark->getUrl()); // normalized URL
		$this->assertEquals("", $bookmark->getTitle());
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws UnsupportedOperation
	 */
	public function testDeleteBookmarkShared(): void {
		$this->cleanUp();
		$this->setupBookmarksWithSharedFolder();
		$this->authorizer->setUserId($this->userId);
		$res = $this->controller->newBookmark('https://www.google.com', 'Google', 'PrivateTag', ['one', 'two'], [$this->folder1->getId()]);
		$this->assertEquals('success', $res->getData()['status'], var_export($res->getData(), true));
		$id = $res->getData()['item']['id'];

		$this->authorizer->setUserId($this->otherUserId);
		$res = $this->otherController->deleteBookmark($id);
		$this->assertEquals('success', $res->getData()['status'], var_export($res->getData(), true));

		$exception = null;
		try {
			$this->bookmarkMapper->findByUrl($this->userId, 'https://www.google.com');
		} catch (\Exception $e) {
			$exception = $e;
		}
		$this->assertInstanceOf(DoesNotExistException::class, $exception, 'Expected bookmark not to exist and throw');
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws UnsupportedOperation
	 */
	public function testClickSharedFail(): void {
		$this->cleanUp();
		$this->setupBookmarksWithSharedFolder();
		$this->authorizer->setUserId($this->otherUserId);

		$r = $this->otherController->clickBookmark('https://www.golem.de');
		$this->assertNotSame(Http::STATUS_OK, $r->getStatus());
	}
}
