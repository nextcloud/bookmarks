<?php

namespace OCA\Bookmarks\Tests;

use OC\Tagging\Tag;
use OCA\Bookmarks\Controller\BookmarkController;
use OCA\Bookmarks\Controller\FoldersController;
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
 * @group DB
 */
class FolderControllerTest extends TestCase {
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

	protected function setUp(): void {
		parent::setUp();

		$this->user = 'test';
		$this->otherUser = 'otheruser';
		$this->request = \OC::$server->getRequest();

		$this->publicRequest = $this->createStub(IRequest::class);

		$this->userManager = \OC::$server->getUserManager();
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();
		if (!$this->userManager->userExists($this->otherUser)) {
			$this->userManager->createUser($this->otherUser, 'password');
		}
		$this->otherUserId = $this->userManager->get($this->otherUser)->getUID();

		$this->bookmarkMapper = \OC::$server->query(BookmarkMapper::class);
		$this->tagMapper = \OC::$server->query(TagMapper::class);
		$this->folderMapper = \OC::$server->query(FolderMapper::class);
		$this->publicFolderMapper = \OC::$server->query(PublicFolderMapper::class);
		$this->shareMapper = \OC::$server->query(ShareMapper::class);
		$this->sharedFolderMapper = \OC::$server->query(SharedFolderMapper::class);

		$authorizer1 = \OC::$server->query(Authorizer::class);
		$authorizer2 = \OC::$server->query(Authorizer::class);
		$authorizer3 = \OC::$server->query(Authorizer::class);

		$this->controller = new FoldersController('bookmarks', $this->request, $this->userId, $this->folderMapper, $this->bookmarkMapper, $this->publicFolderMapper, $this->sharedFolderMapper, $this->shareMapper, $authorizer1);
		$this->otherController = new FoldersController('bookmarks', $this->request, $this->otherUserId, $this->folderMapper, $this->bookmarkMapper, $this->publicFolderMapper, $this->sharedFolderMapper, $this->shareMapper, $authorizer2);
		$this->public = new FoldersController('bookmarks', $this->publicRequest, null, $this->folderMapper, $this->bookmarkMapper, $this->publicFolderMapper, $this->sharedFolderMapper, $this->shareMapper, $authorizer3);
		$this->noauth = new FoldersController('bookmarks', $this->request, null, $this->folderMapper, $this->bookmarkMapper, $this->publicFolderMapper, $this->sharedFolderMapper, $this->shareMapper, $authorizer3);
	}

	public function setupBookmarks() {
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

		$bookmark1 = Bookmark::fromArray([
			'userId' => $this->userId,
			'url' => "https://www.golem.de",
			'title' => "Golem",
			'description' => "PublicNoTag",
		]);
		$bookmark1 = $this->bookmarkMapper->insertOrUpdate($bookmark1);
		$this->tagMapper->addTo(['four'], $bookmark1->getId());
		$this->folderMapper->addToFolders($bookmark1->getId(), [$this->folder1->getId()]);

		$bookmark2 = Bookmark::fromArray([
			'userId' => $this->userId,
			'url' => "https://9gag.com",
			'title' => "9gag",
			'description' => "PublicTag",
		]);
		$bookmark2 = $this->bookmarkMapper->insertOrUpdate($bookmark2);
		$this->tagMapper->addTo(['four'], $bookmark2->getId());
		$this->folderMapper->addToFolders($bookmark2->getId(), [$this->folder2->getId()]);

		$this->bookmark1Id = $bookmark1->getId();
		$this->bookmark2Id = $bookmark2->getId();
	}

	public function setupPublicFolder() {
		$this->publicFolder = new PublicFolder();
		$this->publicFolder->setFolderId($this->folder1->getId());
		$this->publicFolderMapper->insert($this->publicFolder);

		// inject token into public request stub
		$this->publicRequest->method('getHeader')
			->willReturn('Bearer ' . $this->publicFolder->getId());
	}

	public function setupSharedFolder() {
		$this->share = new Share();
		$this->share->setFolderId($this->folder1->getId());
		$this->share->setOwner($this->userId);
		$this->share->setParticipant($this->otherUser);
		$this->share->setType(ShareMapper::TYPE_USER);
		$this->share->setCanWrite(true);
		$this->share->setCanShare(false);
		$this->shareMapper->insert($this->share);

		$this->sharedFolder = new SharedFolder();
		$this->sharedFolder->setShareId($this->share->getId());
		$this->sharedFolder->setParentFolder(-1);
		$this->sharedFolder->setUserId($this->otherUser);
		$this->sharedFolderMapper->insert($this->sharedFolder);
	}

	public function testRead() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->controller->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals($this->folder1->getTitle(), $data['item']['title']);
	}

	public function testCreate() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->controller->addFolder('foo', $this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$output = $this->controller->getFolder($data['item']['id']);
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals('foo', $data['item']['title']);
	}

	public function testEdit() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->controller->editFolder($this->folder1->getId(), 'blabla');
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$output = $this->controller->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals('blabla', $data['item']['title']);
	}

	public function testDelete() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->controller->deleteFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$output = $this->controller->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
	}

	public function testGetFullHierarchy() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->controller->getFolderChildrenOrder(-1, -1);
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertCount(1, $data['data']);
		$this->assertEquals($this->folder1->getId(), $data['data'][0]['id']);
		$this->assertCount(2, $data['data'][0]['children']);
		$this->assertEquals($this->folder2->getId(), $data['data'][0]['children'][0]['id']);
		$this->assertEquals($this->bookmark1Id, $data['data'][0]['children'][1]['id']);
		$this->assertCount(1, $data['data'][0]['children'][0]['children']);
		$this->assertEquals($this->bookmark2Id, $data['data'][0]['children'][0]['children'][0]['id']);
	}

	public function testSetFullHierarchy() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->controller->setFolderChildrenOrder($this->folder1->getId(), [
			['type' => 'bookmark', 'id' => $this->bookmark1Id],
			['type' => 'folder', 'id' => $this->folder2->getId()]
		]);
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$output = $this->controller->getFolderChildrenOrder(-1, -1);
		$data = $output->getData();
		$this->assertCount(1, $data['data']);
		$this->assertEquals($this->folder1->getId(), $data['data'][0]['id']);
		$this->assertCount(2, $data['data'][0]['children']);
		$this->assertEquals($this->bookmark1Id, $data['data'][0]['children'][0]['id']);
		$this->assertEquals($this->folder2->getId(), $data['data'][0]['children'][1]['id']);
		$this->assertCount(1, $data['data'][0]['children'][1]['children']);
		$this->assertEquals($this->bookmark2Id, $data['data'][0]['children'][1]['children'][0]['id']);
	}

	public function testGetFolderHierarchy() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->controller->getFolders(-1, -1);
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertCount(1, $data['data']);
		$this->assertEquals('foo', $data['data'][0]['title']);
		$this->assertCount(1, $data['data'][0]['children']);
		$this->assertEquals('bar', $data['data'][0]['children'][0]['title']);
		$this->assertCount(0, $data['data'][0]['children'][0]['children']);
	}

	public function testReadNoauthFail() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$output = $this->noauth->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
	}

	public function testCreateNoauthFail() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$output = $this->noauth->addFolder('bla', $this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
	}

	public function testEditNoauthFail() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$output = $this->noauth->editFolder($this->folder2->getId(), 'blabla');
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
		$output = $this->controller->getFolder($this->folder2->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals($this->folder2->getTitle(), $data['item']['title']); // nothing changed
	}

	public function testDeleteNoauthFail() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$output = $this->noauth->deleteFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
		$output = $this->controller->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status']); // nothing changed
	}

	public function testGetFullHierarchyNoauthFail() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->noauth->getFolderChildrenOrder($this->folder1->getId(), -1);
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
	}

	public function testSetFullHierarchyNoauthFail() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->noauth->setFolderChildrenOrder($this->folder1->getId(), [
			['type' => 'noauth', 'id' => $this->bookmark1Id],
			['type' => 'folder', 'id' => $this->folder2->getId()]
		]);
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
		$output = $this->controller->getFolderChildrenOrder(-1, -1);
		$data = $output->getData();
		$this->assertCount(1, $data['data']);
		$this->assertEquals($this->folder1->getId(), $data['data'][0]['id']);
		$this->assertCount(2, $data['data'][0]['children']);
		$this->assertEquals($this->bookmark1Id, $data['data'][0]['children'][1]['id']);
		$this->assertEquals($this->folder2->getId(), $data['data'][0]['children'][0]['id']);
		$this->assertCount(1, $data['data'][0]['children'][0]['children']);
		$this->assertEquals($this->bookmark2Id, $data['data'][0]['children'][0]['children'][0]['id']);
	}

	public function testGetFolderHierarchyNoauth() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->noauth->getFolders($this->folder1->getId(), -1);
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
	}

	public function testReadPublic() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$output = $this->public->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals($this->folder1->getTitle(), $data['item']['title']);
	}

	public function testReadPublicFail() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->public->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
	}

	public function testCreatePublicFail() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$output = $this->public->addFolder('bla', $this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
	}

	public function testEditPublicFail() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$output = $this->public->editFolder($this->folder2->getId(), 'blabla');
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
		$output = $this->public->getFolder($this->folder2->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals($this->folder2->getTitle(), $data['item']['title']); // nothing changed
	}

	public function testDeletePublicFail() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$output = $this->public->deleteFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
		$output = $this->public->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
	}

	public function testGetFullHierarchyPublic() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$output = $this->public->getFolderChildrenOrder($this->folder1->getId(), -1);
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertCount(2, $data['data']);
		$this->assertEquals($this->folder2->getId(), $data['data'][0]['id']);
		$this->assertEquals($this->bookmark1Id, $data['data'][1]['id']);
		$this->assertCount(1, $data['data'][0]['children']);
		$this->assertEquals($this->bookmark2Id, $data['data'][0]['children'][0]['id']);
	}

	public function testSetFullHierarchyPublicFail() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->public->setFolderChildrenOrder($this->folder1->getId(), [
			['type' => 'bookmark', 'id' => $this->bookmark1Id],
			['type' => 'folder', 'id' => $this->folder2->getId()]
		]);
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
		$output = $this->controller->getFolderChildrenOrder(-1, -1);
		$data = $output->getData();
		$this->assertCount(1, $data['data']);
		$this->assertEquals($this->folder1->getId(), $data['data'][0]['id']);
		$this->assertCount(2, $data['data'][0]['children']);
		$this->assertEquals($this->bookmark1Id, $data['data'][0]['children'][1]['id']);
		$this->assertEquals($this->folder2->getId(), $data['data'][0]['children'][0]['id']);
		$this->assertCount(1, $data['data'][0]['children'][0]['children']);
		$this->assertEquals($this->bookmark2Id, $data['data'][0]['children'][0]['children'][0]['id']);
	}

	public function testGetFolderHierarchyPublic() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$output = $this->public->getFolders($this->folder1->getId(), -1);
		$data = $output->getData();
		$this->assertCount(1, $data['data']);
		$this->assertEquals('bar', $data['data'][0]['title']);
		$this->assertCount(0, $data['data'][0]['children']);
	}

	public function testReadShared() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupSharedFolder();
		$output = $this->otherController->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals($this->folder1->getTitle(), $data['item']['title']);
	}

	public function testReadSharedFail() {
		$this->cleanDB();
		$this->setupBookmarks();
		$output = $this->otherController->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
	}

	public function testCreateShared() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupSharedFolder();
		$output = $this->otherController->addFolder('bla', $this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$output = $this->otherController->getFolder($data['item']['id']);
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
	}

	public function testEditShared() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupSharedFolder();
		$output = $this->otherController->editFolder($this->folder2->getId(), 'blabla');
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$output = $this->otherController->getFolder($this->folder2->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertEquals('blabla', $data['item']['title']);
	}

	public function testDeleteShared() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupSharedFolder();
		$output = $this->otherController->deleteFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$output = $this->otherController->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status']);
	}

	public function testGetFullHierarchyShared() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupSharedFolder();
		$output = $this->otherController->getFolderChildrenOrder($this->folder1->getId(), -1);
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertCount(2, $data['data']);
		$this->assertEquals($this->folder2->getId(), $data['data'][0]['id']);
		$this->assertEquals($this->bookmark1Id, $data['data'][1]['id']);
		$this->assertCount(1, $data['data'][0]['children']);
		$this->assertEquals($this->bookmark2Id, $data['data'][0]['children'][0]['id']);
	}

	public function testSetFullHierarchyShared() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupSharedFolder();
		$output = $this->otherController->setFolderChildrenOrder($this->folder1->getId(), [
			['type' => 'bookmark', 'id' => $this->bookmark1Id],
			['type' => 'folder', 'id' => $this->folder2->getId()]
		]);
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$output = $this->controller->getFolderChildrenOrder(-1, -1);
		$data = $output->getData();
		$this->assertCount(1, $data['data']);
		$this->assertEquals($this->folder1->getId(), $data['data'][0]['id']);
		$this->assertCount(2, $data['data'][0]['children']);
		$this->assertEquals($this->bookmark1Id, $data['data'][0]['children'][0]['id']);
		$this->assertEquals($this->folder2->getId(), $data['data'][0]['children'][1]['id']);
		$this->assertCount(1, $data['data'][0]['children'][1]['children']);
		$this->assertEquals($this->bookmark2Id, $data['data'][0]['children'][1]['children'][0]['id']);
	}

	public function testGetFolderHierarchyShared() {
		$this->cleanDB();
		$this->setupBookmarks();
		$this->setupSharedFolder();
		$output = $this->otherController->getFolders($this->folder1->getId(), -1);
		$data = $output->getData();
		$this->assertEquals('success', $data['status']);
		$this->assertCount(1, $data['data']);
		$this->assertEquals('bar', $data['data'][0]['title']);
		$this->assertCount(0, $data['data'][0]['children']);
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
