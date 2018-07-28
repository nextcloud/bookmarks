<?php

namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Controller\Lib\Bookmarks;
use OCA\Bookmarks\Controller\Lib\LinkExplorer;
use OCA\Bookmarks\Controller\Lib\UrlNormalizer;
use OCP\User;

/**
 * Class Test_LibBookmarks_Bookmarks
 *
 * @group DB
 */
class Test_LibBookmarks_Bookmarks extends TestCase {
	private $userid;

	/** @var Bookmarks */
	protected $libBookmarks;

	protected function setUp() {
		parent::setUp();

		$this->userid = User::getUser();

		$db = \OC::$server->getDatabaseConnection();
		$config = \OC::$server->getConfig();
		$l = \OC::$server->getL10N('bookmarks');
		$linkExplorer = \OC::$server->query(LinkExplorer::class);
		$urlNormalizer = \OC::$server->query(UrlNormalizer::class);
		$event = \OC::$server->getEventDispatcher();
		$logger = \OC::$server->getLogger();
		$this->libBookmarks = new Bookmarks($db, $config, $l, $linkExplorer, $urlNormalizer, $event, $logger);

		$this->otherUser = "otheruser";
		$this->userManager = \OC::$server->getUserManager();
		if (!$this->userManager->userExists($this->otherUser)) {
			$this->userManager->createUser($this->otherUser, 'password');
		}
	}

	public function testAddBookmark() {
		$this->cleanDB();
		$this->assertCount(0, $this->libBookmarks->findBookmarks($this->userid, 0, 'id', [], true, -1));
		$this->libBookmarks->addBookmark($this->userid, 'http://nextcloud.com', 'Nextcloud project', ['nc', 'cloud'], 'An awesome project');
		$this->assertCount(1, $this->libBookmarks->findBookmarks($this->userid, 0, 'id', [], true, -1));
		$this->libBookmarks->addBookmark($this->userid, 'http://de.wikipedia.org/Ü', 'Das Ü', ['encyclopedia', 'lang'], 'A terrific letter');
		$this->assertCount(2, $this->libBookmarks->findBookmarks($this->userid, 0, 'id', [], true, -1));
	}

	public function testFindBookmarks() {
		$this->cleanDB();
		$this->libBookmarks->addBookmark($this->userid, "http://www.duckduckgo.com", "DuckDuckGo", [], "PrivateNoTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.google.de", "Google", ["one"], "PrivateTwoTags", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Heise", ["one", "two"], "PrivatTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.golem.de", "Golem", ["one"], "PublicNoTag", true);
		$this->libBookmarks->addBookmark($this->userid, "http://9gag.com", "9gag", ["two", "three"], "PublicTag", true);
		$outputPrivate = $this->libBookmarks->findBookmarks($this->userid, 0, "", [], true, -1, false);
		$this->assertCount(5, $outputPrivate);
		$outputPrivateFiltered = $this->libBookmarks->findBookmarks($this->userid, 0, "", ["one"], true, -1, false);
		$this->assertCount(3, $outputPrivateFiltered);
		$outputPublic = $this->libBookmarks->findBookmarks($this->userid, 0, "", [], true, -1, true);
		$this->assertCount(2, $outputPublic);
		$outputPublicFiltered = $this->libBookmarks->findBookmarks($this->userid, 0, "", ["two"], true, -1, true);
		$this->assertCount(1, $outputPublicFiltered);
	}

	public function testFindBookmarksSelectAndOrFilteredTags() {
		$this->cleanDB();
		$secondUser = $this->userid . "andHisClone435";
		$this->libBookmarks->addBookmark($this->userid, "http://www.google.de", "Google", ["one"], "PrivateNoTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Heise", ["one", "two"], "PrivatTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.golem.de", "Golem", ["four"], "PublicNoTag", true);
		$this->libBookmarks->addBookmark($this->userid, "http://9gag.com", "9gag", ["two", "three"], "PublicTag", true);
		$this->libBookmarks->addBookmark($secondUser, "http://www.google.de", "Google", ["one"], "PrivateNoTag", false);
		$this->libBookmarks->addBookmark($secondUser, "http://www.heise.de", "Heise", ["one", "two"], "PrivatTag", false);
		$this->libBookmarks->addBookmark($secondUser, "http://www.golem.de", "Golem", ["four"], "PublicNoTag", true);
		$this->libBookmarks->addBookmark($secondUser, "http://9gag.com", "9gag", ["two", "three"], "PublicTag", true);
		$resultSetOne = $this->libBookmarks->findBookmarks($this->userid, 0, 'lastmodified', ['one', 'three'], true, -1, false, ['url', 'title', 'tags'], 'or');
		$this->assertEquals(3, count($resultSetOne));
		$resultOne = $resultSetOne[0];
		$this->assertFalse(isset($resultOne['lastmodified']));
		$this->assertCount(2, $resultOne['tags']);
		$this->assertTrue(in_array('two', $resultOne['tags']));
		$this->assertTrue(in_array('three', $resultOne['tags']));
	}

	public function testFindBookmarksUntagged() {
		$this->cleanDB();
		$secondUser = $this->userid . "andHisClone435";
		$this->libBookmarks->addBookmark($this->userid, "http://www.google.de", "Google", [], "PrivateNoTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Heise", ["one", "two"], "PrivatTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.golem.de", "Golem", [], "PublicNoTag", true);
		$this->libBookmarks->addBookmark($this->userid, "http://9gag.com", "9gag", ["two", "three"], "PublicTag", true);
		$this->libBookmarks->addBookmark($secondUser, "http://www.google.de", "Google", ["one"], "PrivateNoTag", false);
		$this->libBookmarks->addBookmark($secondUser, "http://www.heise.de", "Heise", ["one", "two"], "PrivatTag", false);
		$this->libBookmarks->addBookmark($secondUser, "http://www.golem.de", "Golem", [], "PublicNoTag", true);
		$this->libBookmarks->addBookmark($secondUser, "http://9gag.com", "9gag", ["two", "three"], "PublicTag", true);

		$resultSet = $this->libBookmarks->findBookmarks($this->userid, 0, 'lastmodified', [], false, -1, false, ['url', 'title', 'tags'], null, true);
		$this->assertEquals(2, count($resultSet));

		$resultOne = $resultSet[0];
		$this->assertFalse(isset($resultOne['lastmodified']));
		$this->assertCount(0, $resultOne['tags']);
		$this->assertEquals('Golem', $resultOne['title']);
		$this->assertEquals('https://www.golem.de/', $resultOne['url']);

		$resultTwo = $resultSet[1];
		$this->assertFalse(isset($resultTwo['lastmodified']));
		$this->assertCount(0, $resultTwo['tags']);
		$this->assertEquals('Google', $resultTwo['title']);
		$this->assertEquals('http://www.google.de/', $resultTwo['url']);
	}

	public function testFindTags() {
		$this->cleanDB();
		$this->assertEquals($this->libBookmarks->findTags($this->userid), []);
		$this->libBookmarks->addBookmark($this->userid, 'http://nextcloud.com', 'Nextcloud project', ['oc', 'cloud'], 'An awesome project');

		$tags = $this->libBookmarks->findTags($this->userid);
		$this->assertTrue(in_array(['tag' => 'cloud', 'nbr' => 1], $tags));
		$this->assertTrue(in_array(['tag' => 'oc', 'nbr' => 1], $tags));
		$this->assertEquals(2, count($tags));
		$this->assertEquals([], $this->libBookmarks->findTags($this->otherUser));
	}

	public function testFindTagsFilter() {
		$this->cleanDB();
		$this->assertEquals($this->libBookmarks->findTags($this->userid), []);
		$this->libBookmarks->addBookmark($this->userid, "http://www.google.de", "Google", ["one"], "PrivateNoTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Heise", ["one", "two"], "PrivatTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.golem.de", "Golem", ["four"], "PublicNoTag", true);

		$findTags = $this->libBookmarks->findTags($this->userid, ["two", "one"]);
		$this->assertEquals([['tag' => 'four', 'nbr' => 1]], $findTags);
		$this->assertEquals([], $this->libBookmarks->findTags($this->otherUser, ["two", "one"]));
	}

	public function testRenameTag() {
		$this->cleanDB();
		$secondUser = $this->userid . "andHisClone435";
		$this->libBookmarks->addBookmark($this->userid, "http://www.google.de", "Google", ["one"], "PrivateNoTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Heise", ["one", "two"], "PrivatTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.golem.de", "Golem", ["four"], "PublicNoTag", true);
		$this->libBookmarks->addBookmark($this->userid, "http://9gag.com", "9gag", ["two", "three"], "PublicTag", true);
		$this->libBookmarks->addBookmark($secondUser, "http://www.google.de", "Google", ["one"], "PrivateNoTag", false);
		$this->libBookmarks->addBookmark($secondUser, "http://www.heise.de", "Heise", ["one", "two"], "PrivatTag", false);
		$this->libBookmarks->addBookmark($secondUser, "http://www.golem.de", "Golem", ["four"], "PublicNoTag", true);
		$this->libBookmarks->addBookmark($secondUser, "http://9gag.com", "9gag", ["two", "three"], "PublicTag", true);

		$firstUserTags = $this->libBookmarks->findTags($this->userid);
		$this->assertTrue(in_array(['tag' => 'one', 'nbr' => 2], $firstUserTags));
		$this->assertTrue(in_array(['tag' => 'two', 'nbr' => 2], $firstUserTags));
		$this->assertTrue(in_array(['tag' => 'four', 'nbr' => 1], $firstUserTags));
		$this->assertTrue(in_array(['tag' => 'three', 'nbr' => 1], $firstUserTags));
		$this->assertEquals(count($firstUserTags), 4);
		$secondUserTags = $this->libBookmarks->findTags($secondUser);
		$this->assertTrue(in_array(['tag' => 'one', 'nbr' => 2], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'two', 'nbr' => 2], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'four', 'nbr' => 1], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'three', 'nbr' => 1], $secondUserTags));
		$this->assertEquals(count($secondUserTags), 4);

		$this->libBookmarks->renameTag($this->userid, 'four', 'one');

		$firstUserTags = $this->libBookmarks->findTags($this->userid);
		$this->assertTrue(in_array(['tag' => 'one', 'nbr' => 3], $firstUserTags));
		$this->assertTrue(in_array(['tag' => 'two', 'nbr' => 2], $firstUserTags));
		$this->assertTrue(in_array(['tag' => 'three', 'nbr' => 1], $firstUserTags));
		$this->assertEquals(count($firstUserTags), 3);
		$secondUserTags = $this->libBookmarks->findTags($secondUser);
		$this->assertTrue(in_array(['tag' => 'one', 'nbr' => 2], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'two', 'nbr' => 2], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'four', 'nbr' => 1], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'three', 'nbr' => 1], $secondUserTags));
		$this->assertEquals(count($secondUserTags), 4);
	}

	public function testDeleteTag() {
		$this->cleanDB();
		$secondUser = $this->userid . "andHisClone435";
		$this->libBookmarks->addBookmark($this->userid, "http://www.google.de", "Google", ["one"], "PrivateNoTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Heise", ["one", "two"], "PrivatTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.golem.de", "Golem", ["four"], "PublicNoTag", true);
		$this->libBookmarks->addBookmark($this->userid, "http://9gag.com", "9gag", ["two", "three"], "PublicTag", true);
		$this->libBookmarks->addBookmark($secondUser, "http://www.google.de", "Google", ["one"], "PrivateNoTag", false);
		$this->libBookmarks->addBookmark($secondUser, "http://www.heise.de", "Heise", ["one", "two"], "PrivatTag", false);
		$this->libBookmarks->addBookmark($secondUser, "http://www.golem.de", "Golem", ["four"], "PublicNoTag", true);
		$this->libBookmarks->addBookmark($secondUser, "http://9gag.com", "9gag", ["two", "three"], "PublicTag", true);

		$firstUserTags = $this->libBookmarks->findTags($this->userid);
		$this->assertTrue(in_array(['tag' => 'one', 'nbr' => 2], $firstUserTags));
		$this->assertTrue(in_array(['tag' => 'two', 'nbr' => 2], $firstUserTags));
		$this->assertTrue(in_array(['tag' => 'four', 'nbr' => 1], $firstUserTags));
		$this->assertTrue(in_array(['tag' => 'three', 'nbr' => 1], $firstUserTags));
		$this->assertEquals(count($firstUserTags), 4);
		$secondUserTags = $this->libBookmarks->findTags($secondUser);
		$this->assertTrue(in_array(['tag' => 'one', 'nbr' => 2], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'two', 'nbr' => 2], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'four', 'nbr' => 1], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'three', 'nbr' => 1], $secondUserTags));
		$this->assertEquals(count($secondUserTags), 4);

		$this->libBookmarks->deleteTag($this->userid, 'one');

		$firstUserTags = $this->libBookmarks->findTags($this->userid);
		$this->assertFalse(in_array(['tag' => 'one', 'nbr' => 2], $firstUserTags));
		$this->assertTrue(in_array(['tag' => 'two', 'nbr' => 2], $firstUserTags));
		$this->assertTrue(in_array(['tag' => 'four', 'nbr' => 1], $firstUserTags));
		$this->assertTrue(in_array(['tag' => 'three', 'nbr' => 1], $firstUserTags));
		$this->assertEquals(count($firstUserTags), 3);
		$secondUserTags = $this->libBookmarks->findTags($secondUser);
		$this->assertTrue(in_array(['tag' => 'one', 'nbr' => 2], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'two', 'nbr' => 2], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'four', 'nbr' => 1], $secondUserTags));
		$this->assertTrue(in_array(['tag' => 'three', 'nbr' => 1], $secondUserTags));
		$this->assertEquals(count($secondUserTags), 4);
	}

	public function testFindUniqueBookmark() {
		$this->cleanDB();
		$id = $this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Heise", ["one", "two"], "PrivatTag", false);
		$bookmark = $this->libBookmarks->findUniqueBookmark($id, $this->userid);
		$this->assertEquals($id, $bookmark['id']);
		$this->assertEquals("Heise", $bookmark['title']);
	}

	public function testEditBookmark() {
		$this->cleanDB();
		$control_bm_id = $this->libBookmarks->addBookmark($this->userid, "https://www.golem.de/", "Golem", ["four"], "PublicNoTag", true);
		$this->libBookmarks->addBookmark($this->userid, "https://9gag.com", "9gag", ["two", "three"], "PublicTag", true);
		$id = $this->libBookmarks->addBookmark($this->userid, "https://www.heise.de", "Heise", ["one", "two"], "PrivatTag", false);
		$this->libBookmarks->editBookmark($this->userid, $id, "https://www.google.de/", "NewTitle", ["three", "four"]);
		$bookmark = $this->libBookmarks->findUniqueBookmark($id, $this->userid);
		$this->assertEquals("NewTitle", $bookmark['title']);
		$this->assertEquals("https://www.google.de/", $bookmark['url']);
		$this->assertCount(2, $bookmark['tags']);
		$this->assertTrue(in_array('four', $bookmark['tags']));
		$this->assertTrue(in_array('three', $bookmark['tags']));

		// Make sure nothing else changed
		$control_bookmark = $this->libBookmarks->findUniqueBookmark($control_bm_id, $this->userid);
		$this->assertEquals("Golem", $control_bookmark['title']);
		$this->assertEquals("https://www.golem.de/", $control_bookmark['url']);
		$this->assertEquals($control_bookmark['tags'], ['four']);
	}

	public function testDeleteBookmark() {
		$this->cleanDB();
		$this->libBookmarks->addBookmark($this->userid, "https://www.google.de", "Google", ["one"], "PrivateNoTag", false);
		$id = $this->libBookmarks->addBookmark($this->userid, "https://www.heise.de", "Heise", ["one", "two"], "PrivatTag", false);
		$this->assertNotEquals(false, $this->libBookmarks->bookmarkExists("https://www.google.de", $this->userid));
		$this->assertNotEquals(false, $this->libBookmarks->bookmarkExists("https://www.heise.de", $this->userid));
		$this->libBookmarks->deleteUrl($this->userid, $id);
		$this->assertFalse($this->libBookmarks->bookmarkExists("https://www.heise.de", $this->userid));
	}
	
	protected function tearDown() {
		$this->cleanDB();
	}

	public function cleanDB() {
		$query1 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks');
		$query1->execute();
		$query2 = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_tags');
		$query2->execute();
	}

	/**
	 * Register an http service mock for testing purposes.
	 *
	 * @param IClientService $service
	 */
	private function registerHttpService($service) {
		\OC::$server->registerService('HttpClientService', function () use ($service) {
			return $service;
		});
	}
}
