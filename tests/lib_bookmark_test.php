<?php

OC_App::loadApp('bookmarks');

use \OCA\Bookmarks\Controller\Lib\Bookmarks;

class Test_LibBookmarks_Bookmarks extends PHPUnit_Framework_TestCase {

	private $userid;
	private $db;

	protected function setUp() {
		$this->userid = \OCP\User::getUser();
		$this->db = \OC::$server->getDb();
	}

	function testAddBookmark() {
		$this->cleanDB();
		$this->assertCount(0, Bookmarks::findBookmarks($this->userid, $this->db, 0, 'id', array(), true, -1));
		Bookmarks::addBookmark($this->userid, $this->db, 'http://owncloud.org', 'owncloud project', array('oc', 'cloud'), 'An Awesome project');
		$this->assertCount(1, Bookmarks::findBookmarks($this->userid, $this->db, 0, 'id', array(), true, -1));
		Bookmarks::addBookmark($this->userid, $this->db, 'http://de.wikipedia.org/Ü', 'Das Ü', array('encyclopedia', 'lang'), 'A terrific letter');
		$this->assertCount(2, Bookmarks::findBookmarks($this->userid, $this->db, 0, 'id', array(), true, -1));
	}

	function testFindBookmarks() {
		$this->cleanDB();
		Bookmarks::addBookmark($this->userid, $this->db, "http://www.google.de", "Google", array("one"), "PrivateNoTag", false);
		Bookmarks::addBookmark($this->userid, $this->db, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		Bookmarks::addBookmark($this->userid, $this->db, "http://www.golem.de", "Golem", array("one"), "PublicNoTag", true);
		Bookmarks::addBookmark($this->userid, $this->db, "http://www.9gag.com", "9gag", array("two", "three"), "PublicTag", true);
		$outputPrivate = Bookmarks::findBookmarks($this->userid, $this->db, 0, "", array(), true, -1, false);
		$this->assertCount(4, $outputPrivate);
		$outputPrivateFiltered = Bookmarks::findBookmarks($this->userid, $this->db, 0, "", array("one"), true, -1, false);
		$this->assertCount(3, $outputPrivateFiltered);
		$outputPublic = Bookmarks::findBookmarks($this->userid, $this->db, 0, "", array(), true, -1, true);
		$this->assertCount(2, $outputPublic);
		$outputPublicFiltered = Bookmarks::findBookmarks($this->userid, $this->db, 0, "", array("two"), true, -1, true);
		$this->assertCount(1, $outputPublicFiltered);
	}

	function testFindBookmarksSelectAndOrFilteredTags() {
		$this->cleanDB();
		$secondUser = $this->userid . "andHisClone435";
		Bookmarks::addBookmark($this->userid, $this->db, "http://www.google.de", "Google", array("one"), "PrivateNoTag", false);
		Bookmarks::addBookmark($this->userid, $this->db, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		Bookmarks::addBookmark($this->userid, $this->db, "http://www.golem.de", "Golem", array("four"), "PublicNoTag", true);
		Bookmarks::addBookmark($this->userid, $this->db, "http://www.9gag.com", "9gag", array("two", "three"), "PublicTag", true);
		Bookmarks::addBookmark($secondUser, $this->db, "http://www.google.de", "Google", array("one"), "PrivateNoTag", false);
		Bookmarks::addBookmark($secondUser, $this->db, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		Bookmarks::addBookmark($secondUser, $this->db, "http://www.golem.de", "Golem", array("four"), "PublicNoTag", true);
		Bookmarks::addBookmark($secondUser, $this->db, "http://www.9gag.com", "9gag", array("two", "three"), "PublicTag", true);
		$resultSetOne = Bookmarks::findBookmarks($this->userid, $this->db, 0, 'lastmodified', array('one', 'three'), true, -1, false, array('url', 'title'), 'or');
		$this->assertEquals(3, count($resultSetOne));
		$resultOne = $resultSetOne[0];
		$this->assertFalse(isset($resultOne['lastmodified']));
		$this->assertFalse(isset($resultOne['tags']));
	}

	function testFindTags() {
		$this->cleanDB();
		$this->assertEquals(Bookmarks::findTags($this->userid, $this->db), array());
		Bookmarks::addBookmark($this->userid, $this->db, 'http://owncloud.org', 'Owncloud project', array('oc', 'cloud'), 'An Awesome project');
		$this->assertEquals(array(0 => array('tag' => 'cloud', 'nbr' => 1), 1 => array('tag' => 'oc', 'nbr' => 1)), Bookmarks::findTags($this->userid, $this->db));
	}

	function testFindUniqueBookmark() {
		$this->cleanDB();
		$id = Bookmarks::addBookmark($this->userid, $this->db, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		$bookmark = Bookmarks::findUniqueBookmark($id, $this->userid, $this->db);
		$this->assertEquals($id, $bookmark['id']);
		$this->assertEquals("Heise", $bookmark['title']);
	}

	function testEditBookmark() {
		$this->cleanDB();
		$id = Bookmarks::addBookmark($this->userid, $this->db, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		Bookmarks::editBookmark($this->userid, $this->db, $id, "http://www.google.de", "NewTitle", array("three"));
		$bookmark = Bookmarks::findUniqueBookmark($id, $this->userid, $this->db);
		$this->assertEquals("NewTitle", $bookmark['title']);
		$this->assertEquals("http://www.google.de", $bookmark['url']);
		$this->assertEquals(1, count($bookmark['tags']));
	}

	function testDeleteBookmark() {
		$this->cleanDB();
		Bookmarks::addBookmark($this->userid, $this->db, "http://www.google.de", "Google", array("one"), "PrivateNoTag", false);
		$id = Bookmarks::addBookmark($this->userid, $this->db, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		$this->assertNotEquals(false, Bookmarks::bookmarkExists("http://www.google.de", $this->userid, $this->db));
		$this->assertNotEquals(false, Bookmarks::bookmarkExists("http://www.heise.de", $this->userid, $this->db));
		Bookmarks::deleteUrl($this->userid, $this->db, $id);
		$this->assertFalse(Bookmarks::bookmarkExists("http://www.heise.de", $this->userid, $this->db));
	}

	function testGetURLMetadata() {

		$config = $this->getMockBuilder('\OCP\IConfig')
						->disableOriginalConstructor()->getMock();
		$certificateManager = $this->getMock('\OCP\ICertificateManager');
		$httpHelperMock = $this->getMockBuilder('\OC\HTTPHelper')
				->setConstructorArgs(array($config, $certificateManager))
				->getMock();
		$returnAmazonDe = file_get_contents(__DIR__ . '/res/amazonHtml.file');
		$returnGolemDe = file_get_contents(__DIR__ . '/res/golemHtml.file');
		$httpHelperMock->expects($this->any())->method('getUrlContent')->with($this->anything())->will($this->onConsecutiveCalls($returnAmazonDe, $returnGolemDe));
		$this->registerHttpHelper($httpHelperMock);

		$metadataAmazon = Bookmarks::getURLMetadata('amazonHtml');
		$this->assertTrue($metadataAmazon['url'] == 'amazonHtml');
		$this->assertTrue(strpos($metadataAmazon['title'], 'ü') !== false);

		$metadataGolem = Bookmarks::getURLMetadata('golemHtml');
		$this->assertTrue($metadataGolem['url'] == 'golemHtml');
		$this->assertTrue(strpos($metadataGolem['title'], 'f&uuml;r') == false);
	}

	protected function tearDown() {
		$this->cleanDB();
	}

	function cleanDB() {
		$query1 = OC_DB::prepare('DELETE FROM *PREFIX*bookmarks');
		$query1->execute();
		$query2 = OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_tags');
		$query2->execute();
	}

	/**
	 * Register an http helper mock for testing purposes.
	 * @param $httpHelper http helper mock
	 */
	private function registerHttpHelper($httpHelper) {
		$this->oldHttpHelper = \OC::$server->query('HTTPHelper');
		\OC::$server->registerService('HTTPHelper', function () use ($httpHelper) {
			return $httpHelper;
		});
	}

}
