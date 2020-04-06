<?php

namespace OCA\Bookmarks\Tests;

use OC;
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
use OCA\Bookmarks\Db\TreeMapper;
use OCA\Bookmarks\Exception\AlreadyExistsError;
use OCA\Bookmarks\Exception\UrlParseError;
use OCA\Bookmarks\Exception\UserLimitExceededError;
use OCA\Bookmarks\Service\Authorizer;
use OCA\Bookmarks\Service\FolderService;
use OCA\Bookmarks\Service\HashManager;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\QueryException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;

/**
 * Class Test_BookmarkController
 *
 * @group Controller
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

	/**
	 * @var IGroupManager
	 */
	private $groupManager;

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
	private $otherUserId;
	/**
	 * @var \stdClass
	 */
	private $shareMapper;
	/**
	 * @var SharedFolderMapper
	 */
	private $sharedFolderMapper;
	/**
	 * @var \OCP\IGroup
	 */
	private $group;
	/**
	 * @var \stdClass
	 */
	private $treeMapper;
	/**
	 * @var FoldersController
	 */
	private $otherController;
	/**
	 * @var FoldersController
	 */
	private $public;
	/**
	 * @var FoldersController
	 */
	private $noauth;
	/**
	 * @var \PHPUnit\Framework\MockObject\MockObject
	 */
	private $publicRequest;
	/**
	 * @var string
	 */
	private $user;
	/**
	 * @var Share
	 */
	private $share;
	/**
	 * @var SharedFolder
	 */
	private $sharedFolder;
	/**
	 * @var HashManager
	 */
	private $hashManager;
	/**
	 * @var Authorizer
	 */
	private $authorizer;
	/**
	 * @var FolderService
	 */
	private $folders;

	/**
	 * @throws QueryException
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->user = 'test';
		$this->otherUser = 'otheruser';
		$this->request = OC::$server->getRequest();

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

		$this->bookmarkMapper = OC::$server->query(BookmarkMapper::class);
		$this->tagMapper = OC::$server->query(TagMapper::class);
		$this->folderMapper = OC::$server->query(FolderMapper::class);
		$this->treeMapper = OC::$server->query(TreeMapper::class);
		$this->publicFolderMapper = OC::$server->query(PublicFolderMapper::class);
		$this->shareMapper = OC::$server->query(ShareMapper::class);
		$this->sharedFolderMapper = OC::$server->query(SharedFolderMapper::class);
		$this->hashManager = OC::$server->query(HashManager::class);
		$this->folders = OC::$server->query(FolderService::class);
		$this->groupManager = OC::$server->query(IGroupManager::class);

		/** @var IUserManager */
		$userManager = OC::$server->query(IUserManager::class);

		$this->group = $this->groupManager->createGroup('foobar');
		$this->group->addUser($userManager->get($this->otherUser));

		$this->authorizer = OC::$server->query(Authorizer::class);

		$this->controller = new FoldersController('bookmarks', $this->request, $this->userId, $this->folderMapper, $this->publicFolderMapper, $this->sharedFolderMapper, $this->shareMapper, $this->treeMapper, $this->authorizer, $this->hashManager, $this->folders);
		$this->otherController = new FoldersController('bookmarks', $this->request, $this->otherUserId, $this->folderMapper, $this->publicFolderMapper, $this->sharedFolderMapper, $this->shareMapper, $this->treeMapper, $this->authorizer, $this->hashManager, $this->folders);
		$this->public = new FoldersController('bookmarks', $this->publicRequest, null, $this->folderMapper, $this->publicFolderMapper, $this->sharedFolderMapper, $this->shareMapper, $this->treeMapper, $this->authorizer, $this->hashManager, $this->folders);
		$this->noauth = new FoldersController('bookmarks', $this->request, null, $this->folderMapper, $this->publicFolderMapper, $this->sharedFolderMapper, $this->shareMapper, $this->treeMapper, $this->authorizer, $this->hashManager, $this->folders);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 */
	public function setupBookmarks() {
		$this->folder1 = new Folder();
		$this->folder1->setTitle('foo');
		$this->folder1->setUserId($this->userId);
		$this->folderMapper->insert($this->folder1);

		$this->folder2 = new Folder();
		$this->folder2->setTitle('bar');
		$this->folder2->setUserId($this->userId);
		$this->folderMapper->insert($this->folder2);

		$this->treeMapper->move(TreeMapper::TYPE_FOLDER, $this->folder1->getId(), $this->folderMapper->findRootFolder($this->userId)->getId());
		$this->treeMapper->move(TreeMapper::TYPE_FOLDER, $this->folder2->getId(), $this->folder1->getId());

		$bookmark1 = Bookmark::fromArray([
			'userId' => $this->userId,
			'url' => 'https://www.golem.de',
			'title' => 'Golem',
			'description' => 'PublicNoTag',
		]);
		$bookmark1 = $this->bookmarkMapper->insertOrUpdate($bookmark1);
		$this->tagMapper->addTo(['four'], $bookmark1->getId());
		$this->treeMapper->addToFolders(TreeMapper::TYPE_BOOKMARK, $bookmark1->getId(), [$this->folder1->getId()]);

		$bookmark2 = Bookmark::fromArray([
			'userId' => $this->userId,
			'url' => 'https://9gag.com',
			'title' => '9gag',
			'description' => 'PublicTag',
		]);
		$bookmark2 = $this->bookmarkMapper->insertOrUpdate($bookmark2);
		$this->tagMapper->addTo(['four'], $bookmark2->getId());
		$this->treeMapper->addToFolders(TreeMapper::TYPE_BOOKMARK, $bookmark2->getId(), [$this->folder2->getId()]);

		$this->bookmark1Id = $bookmark1->getId();
		$this->bookmark2Id = $bookmark2->getId();
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 */
	public function setupPublicFolder(): void {
		$this->publicFolder = new PublicFolder();
		$this->publicFolder->setFolderId($this->folder1->getId());
		$this->publicFolderMapper->insert($this->publicFolder);

		// inject token into public request stub
		$this->publicRequest->method('getHeader')
			->willReturn('Bearer ' . $this->publicFolder->getId());
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 */
	public function setupSharedFolder() {
		$this->share = new Share();
		$this->share->setFolderId($this->folder1->getId());
		$this->share->setOwner($this->userId);
		$this->share->setParticipant($this->otherUser);
		$this->share->setType(\OCP\Share\IShare::TYPE_USER);
		$this->share->setCanWrite(true);
		$this->share->setCanShare(false);
		$this->shareMapper->insert($this->share);

		$this->sharedFolder = new SharedFolder();
		$this->sharedFolder->setShareId($this->share->getId());
		$this->sharedFolder->setTitle('foo');
		$this->sharedFolder->setUserId($this->otherUserId);
		$this->sharedFolderMapper->insert($this->sharedFolder);
		$this->treeMapper->move(TreeMapper::TYPE_SHARE, $this->sharedFolder->getId(), $this->folderMapper->findRootFolder($this->otherUserId)->getId());
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testRead(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		$output = $this->controller->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertEquals($this->folder1->getTitle(), $data['item']['title']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testCreate(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		$output = $this->controller->addFolder('foo', $this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$output = $this->controller->getFolder($data['item']['id']);
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertEquals('foo', $data['item']['title']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testEdit(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		$output = $this->controller->editFolder($this->folder1->getId(), 'blabla');
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$output = $this->controller->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertEquals('blabla', $data['item']['title']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testDelete(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		$output = $this->controller->deleteFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$output = $this->controller->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testGetFullHierarchy(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		// Using -1 here because this is the controller
		$output = $this->controller->getFolderChildrenOrder(-1, -1);
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertCount(1, $data['data']);
		$this->assertEquals($this->folder1->getId(), $data['data'][0]['id']);
		$this->assertCount(2, $data['data'][0]['children']);
		$this->assertEquals($this->folder2->getId(), $data['data'][0]['children'][0]['id']);
		$this->assertEquals($this->bookmark1Id, $data['data'][0]['children'][1]['id']);
		$this->assertCount(1, $data['data'][0]['children'][0]['children']);
		$this->assertEquals($this->bookmark2Id, $data['data'][0]['children'][0]['children'][0]['id']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testSetFullHierarchy(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		$output = $this->controller->setFolderChildrenOrder($this->folder1->getId(), [
			['type' => 'bookmark', 'id' => $this->bookmark1Id],
			['type' => 'folder', 'id' => $this->folder2->getId()],
		]);
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
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

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testGetFolderHierarchy(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		$output = $this->controller->getFolders(-1, -1);
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertCount(1, $data['data']);
		$this->assertEquals('foo', $data['data'][0]['title']);
		$this->assertCount(1, $data['data'][0]['children']);
		$this->assertEquals('bar', $data['data'][0]['children'][0]['title']);
		$this->assertCount(0, $data['data'][0]['children'][0]['children']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testReadNoauthFail(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$this->authorizer->setUserId(null);
		$this->authorizer->setToken(null);
		$output = $this->noauth->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testCreateNoauthFail(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$this->authorizer->setUserId(null);
		$this->authorizer->setToken(null);
		$output = $this->noauth->addFolder('bla', $this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testEditNoauthFail(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$this->authorizer->setUserId(null);
		$this->authorizer->setToken(null);
		$output = $this->noauth->editFolder($this->folder2->getId(), 'blabla');
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));
		$this->authorizer->setUserId($this->userId);
		$output = $this->controller->getFolder($this->folder2->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertEquals($this->folder2->getTitle(), $data['item']['title']); // nothing changed
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testDeleteNoauthFail(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$this->authorizer->setUserId(null);
		$this->authorizer->setToken(null);
		$output = $this->noauth->deleteFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));
		$this->authorizer->setUserId($this->userId);
		$output = $this->controller->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true)); // nothing changed
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testGetFullHierarchyNoauthFail(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId(null);
		$this->authorizer->setToken(null);
		$output = $this->noauth->getFolderChildrenOrder($this->folder1->getId(), -1);
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testSetFullHierarchyNoauthFail(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId(null);
		$this->authorizer->setToken(null);
		$output = $this->noauth->setFolderChildrenOrder($this->folder1->getId(), [
			['type' => 'noauth', 'id' => $this->bookmark1Id],
			['type' => 'folder', 'id' => $this->folder2->getId()],
		]);
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));

		$this->authorizer->setUserId($this->userId);
		$output = $this->controller->getFolderChildrenOrder(-1, -1);
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertCount(1, $data['data']);
		$this->assertEquals($this->folder1->getId(), $data['data'][0]['id']);
		$this->assertCount(2, $data['data'][0]['children']);
		$this->assertEquals($this->bookmark1Id, $data['data'][0]['children'][1]['id']);
		$this->assertEquals($this->folder2->getId(), $data['data'][0]['children'][0]['id']);
		$this->assertCount(1, $data['data'][0]['children'][0]['children']);
		$this->assertEquals($this->bookmark2Id, $data['data'][0]['children'][0]['children'][0]['id']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testGetFolderHierarchyNoauth(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId(null);
		$this->authorizer->setToken(null);
		$output = $this->noauth->getFolders($this->folder1->getId(), -1);
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testReadPublic(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$output = $this->public->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertEquals($this->folder1->getTitle(), $data['item']['title']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testReadPublicFail(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$output = $this->public->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testCreatePublicFail(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$output = $this->public->addFolder('bla', $this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testEditPublicFail(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$output = $this->public->editFolder($this->folder2->getId(), 'blabla');
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));
		$output = $this->public->getFolder($this->folder2->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertEquals($this->folder2->getTitle(), $data['item']['title']); // nothing changed
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testDeletePublicFail(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$output = $this->public->deleteFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));
		$output = $this->public->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testGetFullHierarchyPublic(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$output = $this->public->getFolderChildrenOrder($this->folder1->getId(), -1);
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertCount(2, $data['data']);
		$this->assertEquals($this->folder2->getId(), $data['data'][0]['id']);
		$this->assertEquals($this->bookmark1Id, $data['data'][1]['id']);
		$this->assertCount(1, $data['data'][0]['children']);
		$this->assertEquals($this->bookmark2Id, $data['data'][0]['children'][0]['id']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testSetFullHierarchyPublicFail(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$output = $this->public->setFolderChildrenOrder($this->folder1->getId(), [
			['type' => 'bookmark', 'id' => $this->bookmark1Id],
			['type' => 'folder', 'id' => $this->folder2->getId()],
		]);
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));

		$this->authorizer->setUserId($this->userId);
		$this->authorizer->setToken(null);
		$output = $this->controller->getFolderChildrenOrder(-1, -1);
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertCount(1, $data['data']);
		$this->assertEquals($this->folder1->getId(), $data['data'][0]['id']);
		$this->assertCount(2, $data['data'][0]['children']);
		$this->assertEquals($this->bookmark1Id, $data['data'][0]['children'][1]['id']);
		$this->assertEquals($this->folder2->getId(), $data['data'][0]['children'][0]['id']);
		$this->assertCount(1, $data['data'][0]['children'][0]['children']);
		$this->assertEquals($this->bookmark2Id, $data['data'][0]['children'][0]['children'][0]['id']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testGetFolderHierarchyPublic(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupPublicFolder();
		$output = $this->public->getFolders($this->folder1->getId(), -1);
		$data = $output->getData();
		$this->assertCount(1, $data['data']);
		$this->assertEquals('bar', $data['data'][0]['title']);
		$this->assertCount(0, $data['data'][0]['children']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testReadShared(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupSharedFolder();
		$this->authorizer->setUserId($this->otherUserId);
		$output = $this->otherController->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertEquals($this->folder1->getTitle(), $data['item']['title']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testReadSharedFail(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->otherUserId);
		$output = $this->otherController->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testCreateShared(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupSharedFolder();
		$this->authorizer->setUserId($this->otherUserId);
		$output = $this->otherController->addFolder('bla', $this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$output = $this->otherController->getFolder($data['item']['id']);
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testEditShared(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupSharedFolder();
		$this->authorizer->setUserId($this->otherUserId);
		$output = $this->otherController->editFolder($this->folder2->getId(), 'blabla');
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$output = $this->otherController->getFolder($this->folder2->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertEquals('blabla', $data['item']['title']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function testDeleteShared(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupSharedFolder();
		$this->authorizer->setUserId($this->otherUserId);
		$output = $this->otherController->deleteFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$output = $this->otherController->getFolder($this->folder1->getId());
		$data = $output->getData();
		$this->assertEquals('error', $data['status'], var_export($data, true));
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testGetFullHierarchyShared(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupSharedFolder();
		$this->authorizer->setUserId($this->otherUserId);
		$output = $this->otherController->getFolderChildrenOrder($this->folder1->getId(), -1);
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertCount(2, $data['data']);
		$this->assertEquals($this->folder2->getId(), $data['data'][0]['id']);
		$this->assertEquals($this->bookmark1Id, $data['data'][1]['id']);
		$this->assertCount(1, $data['data'][0]['children']);
		$this->assertEquals($this->bookmark2Id, $data['data'][0]['children'][0]['id']);
	}

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testSetFullHierarchyShared(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupSharedFolder();
		$this->authorizer->setUserId($this->otherUserId);
		$output = $this->otherController->setFolderChildrenOrder($this->folder1->getId(), [
			['type' => 'bookmark', 'id' => $this->bookmark1Id],
			['type' => 'folder', 'id' => $this->folder2->getId()],
		]);
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->authorizer->setUserId($this->userId);
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

	/**
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 */
	public function testGetFolderHierarchyShared(): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->setupSharedFolder();
		$this->authorizer->setUserId($this->otherUserId);
		$output = $this->otherController->getFolders($this->folder1->getId(), -1);
		$data = $output->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->assertCount(1, $data['data']);
		$this->assertEquals('bar', $data['data'][0]['title']);
		$this->assertCount(0, $data['data'][0]['children']);
	}

	/**
	 * @param $participant
	 * @param $type
	 * @param $canWrite
	 * @param $canShare
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 * @dataProvider shareDataProvider
	 */
	public function testCreateShare($participant, $type, $canWrite, $canShare): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserid($this->userId);
		$res = $this->controller->createShare($this->folder1->getId(), $participant, $type, $canWrite, $canShare);
		$data = $res->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
	}

	/**
	 * @param $participant
	 * @param $type
	 * @param $canWrite
	 * @param $canShare
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 * @dataProvider shareDataProvider
	 * @depends      testCreateShare
	 */
	public function testGetShare($participant, $type, $canWrite, $canShare): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		$res = $this->controller->createShare($this->folder1->getId(), $participant, $type, $canWrite, $canShare);
		$data = $res->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$res = $this->controller->getShare($data['item']['id']);
		$data = $res->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$this->authorizer->setUserId($this->otherUserId);
		$res = $this->otherController->getShare($data['item']['id']);
		$data = $res->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
	}

	/**
	 * @param $participant
	 * @param $type
	 * @param $canWrite
	 * @param $canShare
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 * @dataProvider shareDataProvider
	 * @depends      testCreateShare
	 */
	public function testEditShare($participant, $type, $canWrite, $canShare): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		$res = $this->controller->createShare($this->folder1->getId(), $participant, $type, $canWrite, $canShare);
		$data = $res->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$shareId = $data['item']['id'];

		$this->authorizer->setUserId($this->otherUserId);
		$res = $this->otherController->editShare($shareId, false, false);
		$data = $res->getData();
		if ($canShare) {
			$this->assertEquals('success', $data['status'], var_export($data, true));
		} else {
			$this->assertEquals('error', $data['status'], var_export($data, true));
		}

		$this->authorizer->setUserId($this->userId);
		$res = $this->controller->editShare($shareId, false, false);
		$data = $res->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
	}

	/**
	 * @param $participant
	 * @param $type
	 * @param $canWrite
	 * @param $canShare
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 * @dataProvider shareDataProvider
	 * @depends      testCreateShare
	 */
	public function testDeleteShareOwner($participant, $type, $canWrite, $canShare): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		$res = $this->controller->createShare($this->folder1->getId(), $participant, $type, $canWrite, $canShare);
		$data = $res->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$shareId = $data['item']['id'];

		$this->authorizer->setUserId($this->userId);
		$res = $this->controller->deleteShare($shareId);
		$data = $res->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
	}

	/**
	 * @param $participant
	 * @param $type
	 * @param $canWrite
	 * @param $canShare
	 * @throws AlreadyExistsError
	 * @throws UrlParseError
	 * @throws UserLimitExceededError
	 * @throws MultipleObjectsReturnedException
	 * @dataProvider shareDataProvider
	 * @depends      testCreateShare
	 */
	public function testDeleteShareSharee($participant, $type, $canWrite, $canShare): void {
		$this->cleanUp();
		$this->setupBookmarks();
		$this->authorizer->setUserId($this->userId);
		$res = $this->controller->createShare($this->folder1->getId(), $participant, $type, $canWrite, $canShare);
		$data = $res->getData();
		$this->assertEquals('success', $data['status'], var_export($data, true));
		$shareId = $data['item']['id'];

		$this->authorizer->setUserId($this->otherUserId);
		$res = $this->otherController->deleteShare($shareId);
		$data = $res->getData();
		if ($canShare) {
			$this->assertEquals('success', $data['status'], var_export($data, true));
		} else {
			$this->assertEquals('error', $data['status'], var_export($data, true));
		}
	}

	/**
	 * @return array
	 */
	public function shareDataProvider(): array {
		return [
			['otheruser', \OCP\Share\IShare::TYPE_USER, true, false],
			['otheruser', \OCP\Share\IShare::TYPE_USER, true, true],
			['foobar', \OCP\Share\IShare::TYPE_GROUP, true, false],
			['foobar', \OCP\Share\IShare::TYPE_GROUP, true, true],
		];
	}
}
